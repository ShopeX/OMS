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
 * 分销售后单model类
 *
 * @author wangbiao@shopex.cn
 * @version 2024.04.28
 */
class dealer_mdl_aftersale extends sales_mdl_aftersale
{
    
    public function table_name($real = false)
    {
        if($real){
           $table_name = 'sdb_sales_aftersale';
        }else{
           $table_name = 'aftersale';
        }
        
        return $table_name;
    }

     public function get_schema(){
        return app::get('sales')->model('aftersale')->get_schema();
    }

}
