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

class desktop_finder
{
    public $detail_items='明细信息';
    public $detail_items_cols_per_row=5;

    public $detail_operation_log='操作日志';

    public function detail_items($id)
    {
        $render   = app::get('desktop')->render();
        
        list($app, $model) = explode('_finder_', get_class($this));

        $masterMdl = app::get($app)->model($model);    
        $columns = $masterMdl->_columns();
        
        $dlysale = $masterMdl->db_dump($id);
        foreach ($columns as $key => $column) {
            if ($column['type'] == 'time') {
                $dlysale[$key] = $dlysale[$key] ? date("Y-m-d H:i:s", $dlysale[$key]) : '';
            }

            if (is_array($column['type'])) {
                $dlysale[$key] = $column['type'][$dlysale[$key]];
            }
        }
        
        // 按照 order 字段排序 columns
        $sortedColumns = $columns;
        uasort($sortedColumns, function($a, $b) {
            $orderA = isset($a['order']) ? intval($a['order']) : 999;
            $orderB = isset($b['order']) ? intval($b['order']) : 999;
            return $orderA - $orderB;
        });

        $render->pagedata['data'] = [
            'header' => $sortedColumns,
            'body' => $dlysale,
        ];
        
        // 传递列数配置到模板
        $render->pagedata['cols_per_row'] = $this->detail_items_cols_per_row;
        

        if ($masterMdl->relate_item_entity) {
            $itemMdl = app::get($app)->model($masterMdl->relate_item_entity['entity']);
            $itemColumns = $itemMdl->_columns();

            // $masterPrimary = null;
            // foreach ($itemColumns as $key => $column) {
            //     if ($column['type'] == 'table:'.$model.'@'.$app) {
            //         $masterPrimary = $key;
            //         break;
            //     }
            // }

            if ($masterMdl->relate_item_entity['foreign_primary_id']) {
                $items = $itemMdl->getList('*', [$masterMdl->relate_item_entity['foreign_primary_id'] => $id]);
                
                // 按照 order 字段排序明细 columns
                $sortedItemColumns = $itemColumns;
                uasort($sortedItemColumns, function($a, $b) {
                    $orderA = isset($a['order']) ? intval($a['order']) : 999;
                    $orderB = isset($b['order']) ? intval($b['order']) : 999;
                    return $orderA - $orderB;
                });
                
                $render->pagedata['lines'] = [
                    'header' => $sortedItemColumns,
                    'body' => $items,
                ];
            }
        }
        
        return $render->fetch('finder/detail.html', 'desktop');
    }

    function detail_operation_log($id){
        $render = app::get('desktop')->render();

        list($app, $model) = explode('_finder_', get_class($this));

        $obj_type = $model . '@' . $app;

        $rows = app::get('ome')->model('operation_log')->getList('*',[
            'obj_id'=>$id, 
            'obj_type' => $obj_type
        ], 0, -1, 'log_id DESC');

        $operations = [];
        if(base_kvstore::instance('service')->fetch('operation_log',$service_define)){
            foreach($service_define['list'] as $k => $v) {
                try {
                    $newObj = new $v();
                    if(method_exists($newObj,'get_operations')){
                        $operations = $newObj->get_operations();
                        if (isset($operations[$app])) {
                            $operations = $operations[$app];
                            break;
                        }
                    }
                } catch (\Throwable $th) {
                    //throw $th;
                } 
            }
        }

        foreach($rows as $k => $v){
            list($opt, $app) = explode('@', $v['operation']);
            if (isset($operations[$opt])) {
                $rows[$k]['operation'] = $operations[$opt]['name'];
            }
        }

        $render->pagedata['rows'] = $rows;

        return $render->fetch('finder/operation_log.html','desktop');
    }


}
