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
 
class desktop_widgets_appolymer implements desktop_interface_widget{
    
    
    function __construct($app){
        $this->app = $app; 
        $this->render =  new base_render(app::get('desktop'));  
    }
    
    function get_title(){
            
        return app::get('desktop')->_("应用程序");
        
    }
    function get_html(){ 
        $render = $this->render;
        $render->pagedata['data'] = '';
        return $render->fetch('widgets/appolymer.html');
    }
    function get_className(){
        
          return "";
    }
    function get_width(){
          
          return "normal";
        
    }
    
}

?>