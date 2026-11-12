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

class ome_finder_extend_supply_product {
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    
    function get_extend_colums(){
        
        //供应商
        $oSupplier = app::get('purchase')->model('supplier');
        $suppliers = $oSupplier -> getList('supplier_id,name');
        foreach($suppliers as $v) {
            $v['name'] = str_replace('有限公司','',$v['name']);
            $v['name'] = str_replace('公司','',$v['name']);
            $v['name'] = mb_substr($v['name'],0,10,'utf-8');
            $supplier_list[$v['supplier_id']] = $v['name'];
        }
        
        $db['supply_product']=array (
          'columns' => 
          array (
            'supplier_id' => 
            array (
              'type' => $supplier_list,
              'default' => 0,
              'label' => '供应商',
              'filtertype' => 'normal',
              'filterdefault' => true,
            ),
            
            'filter_type' => 
            array (
              'type' => array(
                1=>'可用库存 小于 安全库存',
                2=>'真实库存 小于 安全库存',
                3=>'可用库存 小于 指定数量',
                4=>'真实库存 小于 指定数量',
              ),
              'default' => 0,
              'label' => '筛选条件',
              'filtertype' => 'normal',
              'filterdefault' => true,
            ),
            
            'appoint_store' => 
            array (
              'type' => 'int(32)',
              'default' => 0,
              'label' => '指定数量',
              'filtertype' => 'normal',
              'filterdefault' => true,
            ),
            
          ),
        );
        return $db;
    }
}
