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

/**
 * 库存差异监控
 * 
 */
class console_autotask_timer_dailyinvmonitor
{
    public function process($params, &$error_msg = '')
    {
        set_time_limit(0);
        ignore_user_abort(1);
        @ini_set('memory_limit', '1024M');

        $stock_date = $params['stock_date'] ?: date('Y-m-d', strtotime('-1 day'));

        // WMS库存
        $file1 = $this->_wms_daily_inventory($stock_date);

        // 门店库存
        $file2 = $this->_store_daily_inventory($stock_date);

        if ($file1 || $file2){
            $file_path = [];
            if (is_file($file1)){
                $file_path[] = $file1;
            }

            if (is_file($file2)){
                $file_path[] = $file2;
            }

            $errmsg = [];

            $wmsInvs = app::get('console')->model('dailyinventory')->getList('id,diff_stock', [
                'stock_date' => $stock_date,
                'channel_type' => 'wms',
                'is_diff' => '1',
            ]);

            $errmsg[] = $wmsInvs ? 'WMS差异总数：'.array_sum(array_column($wmsInvs, 'diff_stock')).'；' : 'WMS无库存差异；';

            $storeInvs = app::get('console')->model('dailyinventory')->getList('id,diff_stock', [
                'stock_date' => $stock_date,
                'channel_type' => 'store',
                'is_diff' => '1',
            ]);

            $errmsg[] = $storeInvs ? '门店差异总数：'.array_sum(array_column($storeInvs, 'diff_stock')).'；' : '门店无库存差异；';

            kernel::single('monitor_event_notify')->addNotify('stock_diff_alarm', [
                'channel_bn'     => '',
                'warehouse_code' => '',
                'stock_date'     => $stock_date,
                'errmsg'         => implode(PHP_EOL,$errmsg),
                'file_path'      => $file_path,
            ],true);
        }



        return true;
    }

    /**
     * WMS库存差异监控
     *
     *
     * @param string $stock_date
     * @return void
     **/
    public function _wms_daily_inventory($stock_date)
    {
        $invs = app::get('console')->model('dailyinventory')->getList('id', [
            'stock_date' => $stock_date,
            'channel_type' => 'wms',
        ]);

        if (!$invs){
            return false;
        }

        $items = app::get('console')->model('dailyinventory_items')->getList('*', [
            'stock_date' => $stock_date,
            'diff_stock|noequal' => '0',
            'dlyinv_id' => array_column($invs, 'id'),
        ]);

        if (!$items){
            return true;
        }

        $config = [
            'path' => kernel::single('ome_func')->getTmpDir() . '/',
        ];
        $xlsxObject = new \Vtiful\Kernel\Excel($config);


        $fileName = sprintf('WMS库存差异%s.xlsx', $stock_date);

        
        $fileObject = $xlsxObject->fileName($fileName, $items[0]['diff_type']=='2'?'按颗对比':'按条对比');

        $title = ['日期', '库位', '物料编码', 'OMS库存', 'WMS库存', '差异库存'];
        $fileObject->header($title);

        $data = [];
        foreach ($items as $item){
            $data[] = [$item['stock_date'], $item['warehouse_code'], $item['material_bn'], $item['oms_stock'], $item['outer_stock'], $item['diff_stock']];
        }

        $fileObject->data($data);

        return $fileObject->output();
    }

    /**
     * POS库存差异监控
     *
     *
     * @param string $stock_date
     * @return void
     **/
    public function _store_daily_inventory($stock_date)
    {
        $invs = app::get('console')->model('dailyinventory')->getList('id', [
            'stock_date' => $stock_date,
            'channel_type' => 'store',
        ]);

        if (!$invs){
            return false;
        }

        $items = app::get('console')->model('dailyinventory_items')->getList('*', [
            'stock_date' => $stock_date,
            'diff_stock|noequal' => '0',
            'dlyinv_id' => array_column($invs, 'id'),
        ]);

        if (!$items){
            return true;
        }

        $config = [
            'path' => kernel::single('ome_func')->getTmpDir() . '/',
        ];
        $xlsxObject = new \Vtiful\Kernel\Excel($config);


        $fileName = sprintf('门店库存差异%s.xlsx', $stock_date);

        
        $fileObject = $xlsxObject->fileName($fileName, $items[0]['diff_type']=='2'?'按颗对比':'按最小销售单位对比');

        $title = ['日期', '门店编码','门店名称', '库位类型', '库位', '物料编码', 'OMS库存', '门店库存', '差异库存'];
        $fileObject->header($title);

        $branchList = app::get('ome')->model('branch')->getList('branch_bn,storage_code,name,type', [
            'branch_bn' => array_column($items, 'warehouse_code'),
        ]);
        $branchList = array_column($branchList, null, 'branch_bn');

        $branchType = kernel::single('o2o_store')->getBranchType();


        $data = [];
        foreach ($items as $item){
            $branch = $branchList[$item['warehouse_code']];

            $name = $branch['name'];
            if (preg_match('/\[.*?\](.*?)-.*?/', $branch['name'], $m)){
                $name = $m[1];
            }

            $type = $branchType[$branch['type']]['text'];
            if ($type == '主仓'){
                $type = '销售库位';
            }

            list($store_bn) = explode('_', $item['warehouse_code']);

            $data[] = [$item['stock_date'], $store_bn, $name, $type,  $item['storage_code'], $item['material_bn'], $item['oms_stock'], $item['outer_stock'], $item['diff_stock']];
        }

        $fileObject->data($data);

        return $fileObject->output();
    }
}
