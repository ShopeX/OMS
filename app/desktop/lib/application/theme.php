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
 
class desktop_application_theme extends base_application_prototype_filepath{

    var $path = 'desktop_themes';

    function filter(){
        $dirname = $this->iterator()->getFilename();
        return $dirname[0]!='.' && is_dir($this->getPathname());
    }

    function content_typename(){
        return 'desktop theme';
    }
    
    static function get_files($theme_define){
        list($theme_app,$theme_dir) = explode('/',$theme_define);
        $handle = opendir(app::get($theme_app)->app_dir.'/desktop_themes/'.$theme_dir);
        $theme_base_url = kernel::base_url().'/app/'.$theme_app.'/desktop_themes/'.$theme_dir;
        while(false!==($file=readdir($handle))){
            if($file[0]!='.'){
                if(substr($file,-4,4)=='.css'){
                    $css[] = $theme_base_url.'/'.$file;
                }elseif(substr($file,-3,3)=='.js'){
                    $js[] = $theme_base_url.'/'.$file;                    
                }
            }
        }
        closedir($handle);
        return array($js,$css);
    }

}