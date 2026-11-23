<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

class console_autotask_timer_dailyinventory
{
    /**
     * 处理
     * @param mixed $params 参数
     * @param mixed $error_msg error_msg
     * @return mixed 返回值
     */
    public function process($params, &$error_msg = '')
    {
        set_time_limit(0);
        ignore_user_abort(1);
        @ini_set('memory_limit', '1024M');

        $channel_type = $params['channel_type'];
        if (!in_array($channel_type, ['wms', 'store'])) {
            $error_msg = 'channel_type只支持wms|store';
            return true;
        }

        if (!$params['invs_id']) {
            $error_msg = '缺少invs_id字段';
            return true;
        }

        switch ($channel_type) {
            case 'wms':
                list($rs, $error_msg) = $this->_process_wms($params);
                break;
            case 'store':
                list($rs, $error_msg) = $this->_process_store($params);
                break;
        }

        return true;
    }

    private function _process_wms($params)
    {
        $invs = app::get('console')->model('inventory_snapshot')->dump($params['invs_id']);
        if (!$invs) {
            return [true, '缺少WMS库存快照'];
        }

        $dlyInvMdl = app::get('console')->model('dailyinventory');

        $dailyInv = $dlyInvMdl->dump([
            'stock_date'   => $invs['stock_date'],
            'channel_id'   => $invs['wms_id'],
            'channel_type' => 'wms',
        ]);

        if (!$dailyInv) {
            $dailyInv = [
                'dailyinventory_bn' => $dlyInvMdl->gen_id($invs['wms_bn']),
                'channel_id'        => $invs['wms_id'],
                'channel_bn'        => $invs['wms_bn'],
                'channel_type'      => 'wms',
                'stock_date'        => $invs['stock_date'],
            ];

            $dlyInvMdl->insert($dailyInv);
        }

        // 循环处理
        $dlyInvItemMdl = app::get('console')->model('dailyinventory_items');
        $invsItemMdl   = app::get('console')->model('inventory_snapshot_items');

        // WMS不存在的 只支持与WMS一对一，一对多需要再调整 BEGIN
        $branchIds     = explode(',', (string)$invs['branch_id']);
        $str_branch_id = count($branchIds) > 1 ? '"' . implode('","', $branchIds) . '"' : '"' . $branchIds[0] . '"';
        $dateTime      = date('Y-m-d H:i:s');
        if (count($branchIds) > 1) {
            $sql = <<<SQL
            REPLACE INTO `sdb_console_dailyinventory_items`(`dlyinv_id`,`stock_date`,`warehouse_code`,`bm_id`,`material_bn`,`oms_stock`,`outer_stock`,`storage_code`,`diff_stock`,`diff_type`,`at_time`) SELECT "{$dailyInv['id']}","{$dailyInv['stock_date']}","{$invs['warehouse_code']}",`product_id`,`product_bn`,SUM(`stock_num`) stock_num,0,`storage_code`,SUM(`stock_num`) `stock_num`,"1","$dateTime"  FROM `sdb_ome_dailystock` WHERE `stock_date`="{$invs['stock_date']}" AND `branch_id` IN ($str_branch_id) GROUP BY `product_id`
SQL;
        } else {
            $sql = <<<SQL
            REPLACE INTO `sdb_console_dailyinventory_items`(`dlyinv_id`,`stock_date`,`warehouse_code`,`bm_id`,`material_bn`,`oms_stock`,`outer_stock`,`storage_code`,`diff_stock`,`diff_type`,`at_time`) SELECT "{$dailyInv['id']}","{$dailyInv['stock_date']}","{$invs['warehouse_code']}",`product_id`,`product_bn`,`stock_num`,0,`storage_code`,`stock_num`,"1","$dateTime"  FROM `sdb_ome_dailystock` WHERE `stock_date`="{$invs['stock_date']}" AND `branch_id` ={$str_branch_id}
SQL;
        }
        kernel::database()->exec($sql);
        // WMS不存在的 END

        $offset = 0;
        $limit  = 800;

        $errmsg = [];

        // 按库存
        do {
            $sql = <<<SQL
                SELECT `item_code`,SUM(`quantity`) quantity FROM  `sdb_console_inventory_snapshot_items`
                WHERE invs_id="{$invs['id']}"
                GROUP BY `item_code`
SQL;

            $rows = kernel::database()->selectlimit($sql, $limit, $offset);
            if (!$rows) {
                break;
            }

            $item_code = [];
            foreach ($rows as $row) {
                $item_code[] = kernel::database()->quote($row['item_code']);
            }

            if (!$item_code) {
                continue;
            }

            $product_bn = implode(',', $item_code);

            $omsDailyStock = [];

            $sql = <<<SQL
            SELECT `product_id`,`product_bn`,SUM(`stock_num`) stock_num FROM `sdb_ome_dailystock`
            WHERE `stock_date`="{$invs['stock_date']}" 
            AND `branch_id` IN ($str_branch_id)
            AND `product_bn` IN ($product_bn) GROUP BY `product_bn`
SQL;
            // $omsDailyStock = kernel::database()->select($sql);
            // $omsDailyStock = array_column($omsDailyStock, null, 'product_bn');

            foreach (kernel::database()->select($sql) as $value) {
                $omsDailyStock[strtoupper($value['product_bn'])] = $value;
            }


            $data = [];
            foreach ($rows as $row) {
                $os = $omsDailyStock[strtoupper($row['item_code'])];

                $item = [
                    'dlyinv_id'      => $dailyInv['id'],
                    'stock_date'     => $dailyInv['stock_date'],
                    'warehouse_code' => $invs['warehouse_code'],
                    'bm_id'          => $os['product_id'],
                    'material_bn'    => $row['item_code'],
                    'oms_stock'      => (int) $os['stock_num'],
                    'outer_stock'    => $row['quantity'],
                    'invs_id'        => $invs['id'],
                    'storage_code'   => $invs['storage_code'],
                    'diff_type'      => '1',
                    'at_time'        => $dateTime,
                    'up_time'        => $dateTime,
                ];

                $item['diff_stock'] = $item['oms_stock'] - $item['outer_stock'];

                $data[] = $item;

                if ($item['diff_stock'] != 0) {
                    $errmsg[] = "货号：{$item['material_bn']}\t系统库存：{$item['oms_stock']}\tWMS库存：{$item['outer_stock']}";
                }
            }

            kernel::database()->exec(ome_func::get_replace_sql($dlyInvItemMdl, $data));

            $offset += $limit;
        } while (true);


        $sql = <<<SQL
            SELECT SUM(`oms_stock`) oms_stock,SUM(`outer_stock`) outer_stock,SUM(ABS(`diff_stock`)) diff_stock
            FROM `sdb_console_dailyinventory_items`
            WHERE dlyinv_id="{$dailyInv['id']}"
SQL;

        $row = kernel::database()->selectrow($sql);

        $dlyInvMdl->update([
            'oms_stock'   => (int)$row['oms_stock'],
            'outer_stock' => (int)$row['outer_stock'],
            'diff_stock'  => (int)$row['diff_stock'],
            'is_diff'     => $row['diff_stock'] != 0 ? 1 : 0,
            'diff_type'   => '1',
        ], ['id' => $dailyInv['id']]);

        // 报警
        // if ($errmsg && $row['diff_stock'] != 0) {
        //     $errmsg = array_slice($errmsg, 0, 10);
        //     kernel::single('monitor_event_notify')->addNotify('stock_diff_alarm', [
        //         'channel_bn'     => $invs['wms_bn'],
        //         'warehouse_code' => $invs['warehouse_code'],
        //         'stock_date'     => $invs['stock_date'],
        //         'errmsg'         => implode(PHP_EOL,$errmsg),
        //     ]);
        // }

        return [true];
    }

    private function _process_store($params)
    {
        $invsMdl     = app::get('pos')->model('inventory_snapshot');
        $invsItemMdl = app::get('pos')->model('inventory_snapshot_items');

        $invs = $invsMdl->dump($params['invs_id']);
        if (!$invs) {
            return [true, '缺少POS库存快照'];
        }

        $dlyInvMdl = app::get('console')->model('dailyinventory');

        $dailyInv = $dlyInvMdl->dump([
            'stock_date'   => $invs['stock_date'],
            'channel_id'   => $invs['store_id'],
            'channel_type' => 'store',
        ]);

        if (!$dailyInv) {
            $dailyInv = [
                'dailyinventory_bn' => $dlyInvMdl->gen_id($invs['store_bn']),
                'channel_id'        => $invs['store_id'],
                'channel_bn'        => $invs['store_bn'],
                'channel_type'      => 'store',
                'stock_date'        => $invs['stock_date'],
            ];

            $dlyInvMdl->insert($dailyInv);
        }

        $dlyInvItemMdl = app::get('console')->model('dailyinventory_items');
        
        // check
        if(isset($dailyInv['id']) && $dailyInv['id']){
            // POS不存在的 BEGIN
            $sql = <<<SQL
            REPLACE INTO `sdb_console_dailyinventory_items`(`dlyinv_id`,`stock_date`,`warehouse_code`,`bm_id`,`material_bn`,`oms_stock`,`outer_stock`,`storage_code`,`diff_stock`,`diff_type`) SELECT "{$dailyInv['id']}","{$dailyInv['stock_date']}",`branch_bn`,`product_id`,`product_bn`,`stock_num`,0,`storage_code`,`stock_num`,"1" FROM `sdb_ome_dailystock` WHERE `stock_date`="{$invs['stock_date']}" AND `branch_id` ="{$invs['branch_id']}"
SQL;
            kernel::database()->exec($sql);
            // POS不存在的 END
        }
        
        $offset = 0;
        $limit  = 800;
        $errmsg = [];

        do {
            $rows = $invsItemMdl->getList('item_code,quantity', [
                'invs_id' => $invs['id'],
            ], $offset, $limit);

            if (!$rows) {
                break;
            }

            $item_code = [];
            foreach ($rows as $row) {
                $item_code[] = kernel::database()->quote($row['item_code']);
            }

            if (!$item_code) {
                continue;
            }

            $product_bn = implode(',', $item_code);

            $omsDailyStock = [];

            $sql = <<<SQL
            SELECT `product_id`,`product_bn`,SUM(`stock_num`) stock_num FROM `sdb_ome_dailystock`
            WHERE `stock_date`="{$invs['stock_date']}" 
            AND `branch_id`="{$invs['branch_id']}" 
            AND `product_bn` IN($product_bn) GROUP BY `product_bn`
SQL;
            $omsDailyStock = kernel::database()->select($sql);
            $omsDailyStock = array_column($omsDailyStock, null, 'product_bn');

            $data = [];
            foreach ($rows as $row) {
                $os = $omsDailyStock[$row['item_code']];

                $item = [
                    'dlyinv_id'      => $dailyInv['id'],
                    'stock_date'     => $dailyInv['stock_date'],
                    'warehouse_code' => $invs['branch_bn'],
                    'bm_id'          => $os['product_id'],
                    'material_bn'    => $row['item_code'],
                    'oms_stock'      => (int) $os['stock_num'],
                    'outer_stock'    => $row['quantity'],
                    'invs_id'        => $invs['id'],
                    'storage_code'   => $invs['storage_code'],
                    'diff_type'      => '1',
                ];

                $item['diff_stock'] = $item['oms_stock'] - $item['outer_stock'];

                $data[] = $item;

                if ($item['diff_stock'] != 0) {
                    $errmsg[] = "货号：{$item['material_bn']}\t系统库存：{$item['oms_stock']}\t门店仓库存：{$item['outer_stock']}";
                }
            }

            kernel::database()->exec(ome_func::get_replace_sql($dlyInvItemMdl, $data));

            $offset += $limit;
        } while (true);

        $sql = <<<SQL
            SELECT SUM(`oms_stock`) oms_stock,SUM(`outer_stock`) outer_stock,SUM(ABS(`diff_stock`)) diff_stock
            FROM `sdb_console_dailyinventory_items`
            WHERE dlyinv_id="{$dailyInv['id']}"
SQL;

        $row = kernel::database()->selectrow($sql);

        $dlyInvMdl->update([
            'oms_stock'   => (int)$row['oms_stock'],
            'outer_stock' => (int)$row['outer_stock'],
            'diff_stock'  => (int)$row['diff_stock'],
            'is_diff'     => $row['diff_stock'] != 0 ? 1 : 0,
        ], ['id' => $dailyInv['id']]);

        // 报警
        // if ($errmsg && $row['diff_stock'] != 0) {
        //     $errmsg = array_slice($errmsg, 0, 10);
        //     kernel::single('monitor_event_notify')->addNotify('stock_diff_alarm', [
        //         'channel_bn'     => $invs['store_bn'],
        //         'warehouse_code' => $invs['branch_bn'],
        //         'stock_date'     => $invs['stock_date'],
        //         'errmsg'         => implode(PHP_EOL,$errmsg),
        //     ]);
        // }

        return [true];
    }
}
