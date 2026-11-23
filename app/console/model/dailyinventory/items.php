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

class console_mdl_dailyinventory_items extends dbeav_model
{
    public $has_export_cnf = true;

    public function modifier_stock_date($value)
    {
        return $value;
    }

    public function modifier_warehouse_code($value, $list)
    {
        list($s, $b) = explode('_', $value);


        return $b ?: $s;
    }
    
    public function exportName(&$data)
    {
        $data['name'] = '日盘明细-'.date('Ymd');
    }
    
    public function fgetlist_csv(&$data, $filter, $offset, $exportType = 1)
    {
        $limit = 200;
        $items = $this->getList('*', $filter, $offset * $limit, $limit);
        if (!$items) {
            return false;
        }
        $export_fields = 'column_material_name';
        $finderObj = kernel::single('console_finder_dailyinventory_items');
        foreach ($items as $key=>$aFilter) {
            foreach (explode(',', $export_fields) as $v) {
                if ('column_' == substr($v, 0, 7) && method_exists($finderObj, $v)) {
                    $cv = $finderObj->{$v}($aFilter, $items);
                    $items[$key][substr($v,7)] = $cv;
                }
            }
        }
        
        if (!$data['title']) {
            $title = array();
            foreach ($this->io_title() as $k => $v) {
                $title[] = $v;
            }
            
            $data['title']['items'] = mb_convert_encoding('"' . implode('","', $title) . '"', 'GBK', 'UTF-8');
        }
        
        foreach ($items as $k => $aFilter) {
            $row['日期'] = $aFilter['stock_date'];
            $row['仓编码'] = $aFilter['warehouse_code'];
            $row['物料编码'] = $aFilter['material_bn'];
            $row['物料名称'] = $aFilter['material_name'];
            $row['库位'] = $aFilter['storage_code'];
            $row['系统库存'] = $aFilter['oms_stock'];
            $row['WMS库存'] = $aFilter['outer_stock'];
            $row['库存差异'] = $aFilter['diff_stock'];
            $row['对比方式'] = $aFilter['diff_type'] == '2' ? '按颗对比':'按条对比';
            $data['content']['items'][] = mb_convert_encoding('"' . implode('","', $row) . '"', 'GBK', 'UTF-8');
        }
        
        return true;
    }
    
    public function export_csv($data, $exportType = 1)
    {
        
        $output = array();
        
        $output[] = $data['title']['items'] . "\n" . implode("\n", (array) $data['content']['items']);
        
        echo implode("\n", $output);
    }
    
    public function io_title($filter = null, $ioType = 'csv')
    {
        switch ($ioType) {
            case 'csv':
            default:
                $this->oSchema['csv']['main'] = array(
                    '日期' => 'stock_date',
                    '仓编码' => 'warehouse_code',
                    '物料编码' => 'material_bn',
                    '物料名称' => 'material_name',
                    '库位' => 'storage_code',
                    '系统库存' => 'oms_stock',
                    'WMS库存'  => 'outer_stock',
                    '库存差异' => 'diff_stock',
                    '对比方式' => 'diff_type',
                );
        }
        $this->ioTitle[$ioType][$filter] = array_keys($this->oSchema[$ioType]['main']);
        return $this->ioTitle[$ioType][$filter];
    }

    function modifier_diff_type($cols){
        return $cols == '2' ? '按颗对比':'按条对比';
    }

}
