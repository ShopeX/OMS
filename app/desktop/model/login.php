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
 * ============================
 * @Author:   yaokangming
 * @Version:  1.0 
 * @DateTime: 2021/1/20 14:36:16
 * @describe: model层
 * ============================
 */
class desktop_mdl_login extends dbeav_model {

    public function table_name($real = false)
    {
        if($real){
           $table_name = 'sdb_pam_log';
        }else{
           $table_name = 'log';
        }
        
        return $table_name;
    }
    
    public function get_schema(){
        $return = app::get('pam')->model('log')->get_schema();
        foreach ($return['columns'] as $key => $value) {
            $return['columns'][$key]['label'] = $value['comment'];
        }
        $return['in_list'] = ['event_time','event_data'];
        $return['default_in_list'] = ['event_time','event_data'];
        return $return;
    }

    public function modifier_event_time($col) {
        return date('Y-m-d H:i:s', $col);
    }
}