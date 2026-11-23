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

class console_autotask_timer_invsnapshot
{
    public function process($params, &$error_msg = '')
    {
        set_time_limit(0);
        ignore_user_abort(1);
        @ini_set('memory_limit', '1024M');
        // $snapshot = app::get('ome')->getConf('wms.snapshot.dailystock');
        // if($snapshot != 'on') {
        //     $error_msg = '未开启';
        //     return false;
        // }
        $invMdl = app::get('console')->model('inventory_snapshot');

        if ($params['invs_id']) {
            $inventory = $invMdl->dump($params['invs_id']);

            if ($inventory) {
                $this->_syncQty($inventory);
            }

            return true;
        }

        $stock_date = date('Y-m-d', strtotime('yesterday'));

        $channelList = app::get('channel')->model('channel')->getList('channel_id,channel_bn,crop_config', [
            'channel_type' => 'wms',
            'node_type'    => 'qimen',
            'filter_sql'   => 'node_id is not null AND node_id !=""',
        ]);
        
        foreach ($channelList as $key => $value) {
            $crop_config = $value['crop_config'];
            if (is_string($value['crop_config'])) {
                $crop_config = @unserialize($value['crop_config']);
            }
            
            if (!$crop_config) {
                $crop_config = [];
            }

            if ($crop_config['stock_query'] != '1') {
                unset($channelList[$key]);
            }
        }

        if (!$channelList) {
            return true;
        }
        $channelList = array_column($channelList, null, 'channel_id');

        $branchList = app::get('ome')->model('branch')->getList('branch_id,branch_bn,wms_id,storage_code', [
            'wms_id'           => array_column($channelList, 'channel_id'),
            'check_permission' => 'false',
        ]);

        if (!$branchList) {
            return true;
        }

        $wmsList = [];
        foreach ($branchList as $branch) {
            $rel = app::get('wmsmgr')->model('branch_relation')->dump([
                'wms_id'        => $branch['wms_id'],
                'sys_branch_bn' => $branch['branch_bn'],
            ]);

            $warehouseCode = $rel['wms_branch_bn'] ?: $branch['branch_bn'];

            $wmsList[$warehouseCode]['wms_id'] = $branch['wms_id'];
            $wmsList[$warehouseCode]['wms_bn'] = $channelList[$branch['wms_id']]['channel_bn'];

            $wmsList[$warehouseCode]['branches'][$branch['branch_id']] = $branch;
        }


        foreach ($wmsList as $warehouseCode => $value) {
            $inventory = $invMdl->dump([
                'stock_date'     => $stock_date,
                'warehouse_code' => $warehouseCode,
                'wms_id'         => $value['wms_id'],
            ]);

            if (!$inventory) {


                $inventory = [
                    'stock_date'     => $stock_date,
                    'warehouse_code' => $warehouseCode,
                    'wms_id'         => $value['wms_id'],
                    'wms_bn'         => $value['wms_bn'],
                    'status'         => '1',
                    'branch_bn'      => implode(',', array_column($value['branches'], 'branch_bn')),
                    'branch_id'      => implode(',', array_column($value['branches'], 'branch_id')),
                    'storage_code'      => implode(',', array_column($value['branches'], 'storage_code')),
                ];

                $invMdl->insert($inventory);
            }

            // 加入队列
            kernel::single('taskmgr_interface_connecter')->push([
                'data' => [
                    'task_type'    => 'wms_sync_inv',
                    'invs_id'      => $inventory['id'],
                ],
                'url'  => kernel::openapi_url('openapi.autotask', 'service'),
            ]);

            // $this->_syncQty($inventory);
        }

        return true;
    }

    /**
     * 同步库存
     *
     * @return void
     * @author
     **/
    private function _syncQty($inventory)
    {

        if ($inventory['status'] == '2') {
            return;
        }

        $invMdl     = app::get('console')->model('inventory_snapshot');
        $invItemMdl = app::get('console')->model('inventory_snapshot_items');

        $pageSize = 100;

        $get_count = (int) $inventory['get_count'];
        $pageNo    = floor($get_count / $pageSize) + 1;
        $total_count = 0;

        $wms_stock = (int) $inventory['wms_stock'];
        do {
            $sdf['page_no']       = $pageNo;
            $sdf['page_size']     = $pageSize;
            $sdf['wms_branch_bn'] = $inventory['warehouse_code'];

            $result = kernel::single('erpapi_router_request')->set('wms', $inventory['wms_id'])->goods_stockQuery($sdf);

            if ($result['rsp'] == 'fail') {
                $invMdl->update(['status' => '3', 'errmsg' => $result['msg']], [
                    'id' => $inventory['id'],
                ]);

                return ;
            }

            if (!$result['data']) {
                $invMdl->update(['status' => '2'], [
                    'id' => $inventory['id'],
                ]);
                break;
            }

            if ($pageNo == 1) {
                $invMdl->update(['total_count' => $result['total_count']], [
                    'id' => $inventory['id'],
                ]);
            }

            $invItems = [];
            foreach ($result['data'] as $key => $value) {
                $invItems[] = [
                    'invs_id'        => $inventory['id'],
                    'wms_id'         => $inventory['wms_id'],
                    'wms_bn'         => $inventory['wms_bn'],
                    'stock_date'     => $inventory['stock_date'],
                    'warehouse_code' => $inventory['warehouse_code'],
                    'item_code'      => $value['item_code'],
                    'item_id'        => $value['item_id'],
                    'inventory_type' => $value['inventory_type'],
                    'quantity'       => $value['quantity'],
                    'lock_quantity'  => $value['lock_quantity'],
                    'batch_code'     => $value['batch_code'],
                    'produce_code'   => $value['produce_code'],
                    'product_date'   => $value['product_date'],
                    'expire_date'    => $value['expire_date'],
                ];
            }

            $affectRs = kernel::database()->exec(ome_func::get_insert_sql($invItemMdl, $invItems));

            if ($affectRs) {
                $get_count += count($invItems);
                $wms_stock = array_sum(array_column($invItems, 'quantity'));

                $invMdl->update(['get_count' => $get_count, 'wms_stock' => $wms_stock,'errmsg'=>''], [
                    'id' => $inventory['id'],
                ]);
            }

            $pageNo++;
        } while (true);

        // 生成日盘
        kernel::single('taskmgr_interface_connecter')->push([
            'data' => [
                'task_type'    => 'dailyinventory',
                'channel_type' => 'wms',
                'invs_id'      => $inventory['id'],
            ],
            'url'  => kernel::openapi_url('openapi.autotask', 'service'),
        ]);

        return;
    }

}
