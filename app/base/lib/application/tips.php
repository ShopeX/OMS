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
 
class base_application_tips{
    
    static function tip_apps(){
        $apps = array();
        $lang = kernel::get_lang();
        if ($handle = opendir(APP_DIR)) {
            while (false !== ($file = readdir($handle))) {
                if($file[0]!='.' && is_dir(APP_DIR.'/'.$file) && file_exists(APP_DIR.'/'.$file.'/lang/'.$lang.'/tips.txt')){
                    $apps[] = $file;
                }
                if(defined('CUSTOM_CORE_DIR') && $file[0]!='.' && is_dir(CUSTOM_CORE_DIR.'/'.$file) && file_exists(CUSTOM_CORE_DIR.'/'.$file.'/lang/'.$lang.'/tips.txt')){
                    $apps[] = $file;
                }
            }
            closedir($handle);
        }
        return $apps;
    }
    
    static function tips_item_by_app($app_id){
        $lang = kernel::get_lang();
        $tips = array();
        foreach(file(APP_DIR.'/'.$app_id.'/lang/'.$lang.'/tips.txt')  as $tip){
            $tip = trim($tip);
            if($tip){
                $tips[] = $tip;
            }
        }
        return $tips;
    }
    
    static function tip(){

        $apps = self::tip_apps();

        if(empty($apps)) return '';

        $key = array_rand($apps);
        $app_id = $apps[$key];
        if(empty($app_id)) return '';
        
        $tips = self::tips_item_by_app($app_id);
        $key = array_rand($tips);
        return $tips[$key];
    }
    
}