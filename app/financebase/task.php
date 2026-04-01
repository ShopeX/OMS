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

class financebase_task{

    function post_install(){
    
    	// 接口下载地址
        if(!is_dir(DATA_DIR.'/financebase/settlement')){
            utils::mkdir_p(DATA_DIR.'/financebase/settlement');
        }

        //财务本地缓存目录
        if(!is_dir(DATA_DIR.'/financebase/tmp_local')){
            utils::mkdir_p(DATA_DIR.'/financebase/tmp_local');
        }


        //kernel::single('base_initial', 'financebase')->init();

   
  
    }


    function install_options(){
        return array(
                
            );
    }
}
