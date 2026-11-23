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

class console_mdl_map_goods extends dbeav_model{

    public function table_name($real = false){
        if($real){
           $table_name = 'sdb_inventorydepth_shop_skus';
        }else{
           $table_name = 'shop_skus';
        }
        return $table_name;
    }

    public function get_schema(){
        $schema = app::get('inventorydepth')->model('shop_skus')->get_schema();
        if($schema['columns']['shop_id']) {
            $schema['columns']['shop_id']['label'] = '店铺名';
            $schema['columns']['shop_id']['filterdefault'] = true;
            $schema['columns']['shop_id']['filtertype'] = 'normal';
            $schema['columns']['shop_id']['searchtype'] = 'has';
        }
        return $schema;
    }

    public function _filter($filter,$tableAlias=null,$baseWhere=null) {
        $shopFilter = array('node_type' => 'taobao');
        if($filter['shop_id']) {
            $shopFilter['name|has'] = $filter['shop_id'];
        }
        $shop = app::get('ome')->model('shop')->getList('shop_id', $shopFilter);
        $filter['shop_id'] = array('-1');
        foreach($shop as $val) {
            $filter['shop_id'][] = $val['shop_id'];
        }
        return parent::_filter($filter,$tableAlias=null,$baseWhere=null);
    }
}
