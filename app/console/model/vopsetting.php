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
 * 唯品会JIT配置mdl类
 *
 * @access public
 * @author wangbiao<wangbiao@shopex.cn>
 * @version $Id: vopsetting.php
 */
class console_mdl_vopsetting extends dbeav_model{
    var $defaultOrder = array('create_time',' DESC');
    
    public function table_name($real = false)
    {
        if($real){
           $table_name = 'sdb_purchase_setting';
        }else{
           $table_name = 'setting';
        }
        
        return $table_name;
    }
    
    public function get_schema(){
        return app::get('purchase')->model('setting')->get_schema();
    }
}
?>
