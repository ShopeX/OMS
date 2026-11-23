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

class desktop_keyboard_initdata {
    //配置数组
    public $setting = array();
    
    
    public function __construct( &$app ) {
        $o = $app->router();
        
        
        $setting['界面操作'][] = array('title'=>'刷新主区域','type'=>'refresh','params'=>array('control'=>array('ctrl'=>'true'),'keyword'=>'R'));
        $setting['界面操作'][] = array('title'=>'关闭对话框或列表明细','type'=>'close','params'=>array('keyword'=>'F','control'=>array('ctrl'=>'true')));
        $setting['界面操作'][] = array('title'=>'列表中下个条目明细','type'=>'detail','arg'=>'getNext','params'=>array('keyword'=>'H'));
        $setting['界面操作'][] = array('title'=>'列表中上个条目明细','type'=>'detail','arg'=>'getPrevious','params'=>array('keyword'=>'L'));
        $setting['界面操作'][] = array('title'=>'列表中下个条目明细中上个Tab','type'=>'tabs','arg'=>'getNext','params'=>array('keyword'=>'J'));
        $setting['界面操作'][] = array('title'=>'列表中上个条目明细中上个Tab','type'=>'tabs','arg'=>'getPrevious','params'=>array('keyword'=>'K'));
        $setting['界面操作'][] = array('title'=>'隐藏左侧导航栏','type'=>'event','arg'=>'#leftToggler','params'=>array('keyword'=>'Q'));
        $setting['界面操作'][] = array('title'=>'打开/隐藏高级筛选','type'=>'event','arg'=>'.finder-filter-action-handle a','params'=>array('keyword'=>'W'));
        
        
        $url = $o->gen_url( array('app'=>'desktop','act'=>'index','ctl'=>'dashboard') );
        $setting['全局操作'][] = array('title'=>'查看桌面','params'=>array('keyword'=>'D'),'arg'=>$url);
        
        $url = $o->gen_url( array('app'=>'desktop','act'=>'index','ctl'=>'appmgr') );
        $setting['全局操作'][] = array('title'=>'打开应用中心','params'=>array('keyword'=>'A'),'arg'=>$url);
        
        $url = $o->gen_url( array('app'=>'desktop','act'=>'index','ctl'=>'adminpanel') );
        $setting['全局操作'][] = array('title'=>'打开控制面板','params'=>array('keyword'=>'B'),'arg'=>$url);
        $setting['全局操作'][] = array('title'=>'打开帮助','type'=>'open','params'=>array('keyword'=>'C'),'arg'=>'http://www.shopex.cn/help/ecstore');
        
        $url = $o->gen_url( array('app'=>'desktop','act'=>'maintenance','ctl'=>'appmgr') );
        $setting['全局操作'][] = array('title'=>'维护','type'=>'cmd','params'=>array('keyword'=>'E'),'arg'=>$url,'options'=>array('title'=>'维护'));
        
        $this->setting = $setting;
    }
}
