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
 * @Author: xueding@shopex.cn
 * @Datetime: 2022/4/20
 * @Describe: 外部erp 优仓 模型
 */
class dchain_mdl_branch extends channel_mdl_channel{
    
    public function table_name($real = false){
        if($real){
            $table_name = 'sdb_channel_channel';
        }else{
            $table_name = 'channel';
        }
        return $table_name;
    }
    
    public function get_schema(){
        return app::get('channel')->model('channel')->get_schema();
    }
    
    public function _filter($filter,$tableAlias=null,$baseWhere=null) {
        $filter['channel_type'] = 'dchain';
        return parent::_filter($filter,$tableAlias=null,$baseWhere=null);
    }
}