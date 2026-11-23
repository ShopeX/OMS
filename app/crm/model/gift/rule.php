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

class crm_mdl_gift_rule extends dbeav_model{
    
   public function table_name($real = false){
        if($real){
           $table_name = 'sdb_ome_gift_rule';
        }else{
           $table_name = 'gift_rule';
        }
        return $table_name;
    }

    public function get_schema(){
        return app::get('ome')->model('gift_rule')->get_schema();
    }

    public function modifier_shop_ids($col, $list) {
        if($col == '_ALL_') {
            return '所有店铺';
        }
        $col = explode(',', $col);
        static $shopName = array();
        if(!$shopName) {
            $shopId = array();
            foreach ($list as $val) {
                $shopList = explode(',', $val['shop_ids']);
                foreach ($shopList as $k => $v) {
                    $shopId[$v] = $v;
                }
            }
            $shop = app::get('ome')->model('shop')->getList('shop_id, name',array('shop_id'=>$shopId));
            foreach ($shop as $v) {
                $shopName[$v['shop_id']] = $v['name'];
            }
        }
        $str = '';
        foreach ($col as $k => $v) {
            $str .= $shopName[$v] . ';';
        }
        return $str;
    }

    function _filter($filter,$tableAlias=null,$baseWhere=array ())
    {
        if ($filter['shop_id']) {
            $baseWhere[] = ' (shop_ids="_ALL_" OR FIND_IN_SET("'.$filter['shop_id'].'",shop_ids)) ';
            unset($filter['shop_id']);
        }

        if (isset($filter['ac_status'])) {
            
            switch ($filter['ac_status']) {
                case '0':
                    $baseWhere[] = ' start_time > '.time();
                    break;
                case '1':
                    $baseWhere[] = ' start_time <= '.time().' AND end_time > '.time();
                    break;
                case '2':
                    $baseWhere[] = ' end_time <= '.time();
                    break;
            }
            unset($filter['ac_status']);
        }
        if(!isset($filter['disable'])) {
            $filter['disable'] = 'false';
        }
        return parent::_filter($filter, $tableAlias, $baseWhere);
    }
}