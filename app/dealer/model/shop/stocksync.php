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

class dealer_mdl_shop_stocksync extends dbeav_model {

    public function table_name($real=false)
    {
        $table_name = 'shop';
        if($real){
            return kernel::database()->prefix.app::get('ome')->app_id.'_'.$table_name;
        }else{
            return $table_name;
        }
    }

    public function get_schema()
    {
        $schema = app::get('ome')->model('shop')->get_schema();
        
        # 重新排序
        $schema['columns']['name']['order'] = 10;
        $schema['columns']['shop_type']['order'] = 20;
        $schema['columns']['bs_id']['label'] = '经销商';

        # 重新进行FINDER排序
        unset($schema['in_list'],$schema['default_in_list']);
        $schema['in_list'] = array(
            0 => 'name',    
            1 => 'shop_type',
            2 => 'bs_id',
        );

        $schema['default_in_list'] = array(
            0 => 'name',
            1 => 'shop_type',
            2 => 'bs_id',
        );

        return $schema;
    }

    public function modifier_shop_type($row)
    {
        $row = $row ? ome_shop_type::shop_name($row) : '';
        return $row;
    }

    public function modifier_bs_id($col)
    {
        $col = app::get('dealer')->model('business')->db_dump($col, 'name')['name'];
        return $col;
    }
}