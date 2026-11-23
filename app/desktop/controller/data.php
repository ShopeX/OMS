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
 * ShopEx licence
 *
 * @copyright  Copyright (c) 2005-2010 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license  http://ecos.shopex.cn/ ShopEx License
 */
 
class desktop_ctl_data extends desktop_controller{

    function index(){echo '已关闭';exit;
        header("cache-control: no-store, no-cache, must-revalidate");
        ini_set("max_exection_time", 0);
        
        $oDSB = kernel::single("desktop_system_backup");
        $arr = $oDSB->getList();
        if( $arr ) {
            foreach( (array)$arr as $row ) {   //去除重复数据 
                $key = md5($row['name']);
                if( !$arr_list[$key] )
                    $arr_list[$key] = $row;
                
                $last_backup_time = ($last_backup_time?time():$row['time']);
                $last_backup_time = $last_backup_time>$row['time'] ? $row['time'] : $last_backup_time;
            }
            $this->pagedata['last_backup_time'] = date('Y-m-d H:i:s',$last_backup_time);
        }
        if( !$arr_list ) $this->pagedata['last_backup_time'] = null;
        $this->pagedata['archive'] = $arr_list;

        $this->page('system/data_run.html');
    }


}
