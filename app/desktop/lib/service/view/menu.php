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
 
class desktop_service_view_menu{
    function function_menu(){
        //$html[] = "<a href='index.php?ctl=shoprelation&act=index&p[0]=apply'>网店邻居</a>";
        if(!defined('WITHOUT_DESKTOP_APPMGR') || !constant('WITHOUT_DESKTOP_APPMGR')){

            $html[] = "<a href='index.php?app=desktop&ctl=appmgr&act=index'>".app::get('desktop')->_('应用中心')."</a>";
        }
        $html[] = "<a href='index.php?ctl=adminpanel'>".app::get('desktop')->_('控制面板')."</a>";
        $html[] = "<a href='index.php?app=desktop&ctl=default&act=alertpages&goto=".urlencode('index.php?app=desktop&ctl=recycle&act=index&nobuttion=1')."' target='_blank'>".app::get('desktop')->_('回收站')."</a>";
        $html[] = "<a href='index.php?ctl=dashboard&act=index'>".app::get('desktop')->_('桌面')."</a>";
        return $html;
    }
}