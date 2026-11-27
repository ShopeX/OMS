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

class desktop_ctl_createshortcuts extends base_controller{
    function index(){
        $furl = kernel::base_url(1).kernel::url_prefix().'/shopadmin';
        $content = '[InternetShortcut]
        URL='.$furl.'
        IDList=[{000214A0-0000-0000-C000-000000000046}]
        Prop3=19,2
        ';
        
        header("Content-type: charset=utf-8");
        header("Content-type: application/octet-stream");
        
        /** 兼容各个浏览器 **/
        $filename = app::get('desktop')->getConf('background.title').".url";
        $encoded_filename = urlencode($filename);
        $encoded_filename = str_replace("+", "%20", $encoded_filename);

        if (preg_match("/MSIE/", $_SERVER['HTTP_USER_AGENT']) ) 
        {
            header('Content-Disposition:  attachment; filename="' . $encoded_filename . '"');
        }
        elseif (preg_match("/Firefox/", $_SERVER['HTTP_USER_AGENT']))
        {
            header('Content-Disposition: attachment; filename*="utf8' .  $filename . '"');
        }
        else 
        {
            header('Content-Disposition: attachment; filename="' .  $filename . '"');
        }
        /** end **/

        echo $content;        
    }
}
