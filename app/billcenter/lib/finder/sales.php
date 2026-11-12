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

class billcenter_finder_sales 
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $detail_items = '明细信息';
    
    function detail_items($sale_id){

        $render   = app::get('billcenter')->render();
        
        $saleMdl = app::get('billcenter')->model('sales');
        $columns = $saleMdl->_columns();
        
        $sales = $saleMdl->db_dump($sale_id);
        $sales['in_ar'] = $columns['in_ar']['type'][$sales['in_ar']];

        foreach ($columns as $key => $column) {
            if ($column['type'] == 'time') {
                $sales[$key] = $sales[$key] ? date("Y-m-d H:i:s", $sales[$key]) : '';
            }
        }
        
        $render->pagedata['data'] = [
            'header' => $columns,
            'body' => $sales,
        ];
        

        $itemMdl = app::get('billcenter')->model('sales_items');
        $items = app::get('billcenter')->model('sales_items')->getList('*', ['sale_id'  => $sale_id]);
        $render->pagedata['lines'] = [
            'header' => $itemMdl->_columns(),
            'body' => $items,
        ];

        // 前后端分离（Vue）模式：返回结构化数据，交给框架 FinderDetailTemplate 渲染
        if ($this->vue_mode) {
            return array(
                'header' => $columns,
                'body'   => $sales,
                'lines'  => array(
                    'header' => $itemMdl->_columns(),
                    'body'   => is_array($items) ? $items : array(),
                ),
            );
        }

        return $render->fetch('finder/detail.html', 'desktop');
    }
}