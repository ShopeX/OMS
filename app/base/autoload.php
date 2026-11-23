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

// require_once ROOT_DIR.'/vendor/autoload.php';

function omsAutoloader($class_name) 
{

    $class_name = strip_tags($class_name);
    $trait = false;
    if(strpos($class_name, 'trait') === 0) {
        $trait = true;
        $class_name = substr($class_name, 6);
    }
    $p          = strpos($class_name, '_');

    if ($p) {
        $owner      = substr($class_name, 0, $p);
        $class_name = substr($class_name, $p + 1);
        $tick       = substr($class_name, 0, 4);
        switch ($tick) {
            case 'ctl_':
                if($trait) {
                    $path = TRAIT_DIR . '/' . $owner . '/controller/' . str_replace('_', '/', substr($class_name, 4)) . '.php';
                } elseif (defined('CUSTOM_CORE_DIR') && file_exists(CUSTOM_CORE_DIR . '/' . $owner . '/controller/' . str_replace('_', '/', substr($class_name, 4)) . '.php')) {
                    $path = CUSTOM_CORE_DIR . '/' . $owner . '/controller/' . str_replace('_', '/', substr($class_name, 4)) . '.php';
                } else {
                    $path = APP_DIR . '/' . $owner . '/controller/' . str_replace('_', '/', substr($class_name, 4)) . '.php';
                }
                if (file_exists($path)) {
                    return require_once $path;
                } else {
                    throw new exception('Don\'t find controller file');
                    exit;
                }
            case 'mdl_':
                if($trait) {
                    $path = TRAIT_DIR . '/' . $owner . '/model/' . str_replace('_', '/', substr($class_name, 4)) . '.php';
                } elseif (defined('CUSTOM_CORE_DIR') && file_exists(CUSTOM_CORE_DIR . '/' . $owner . '/model/' . str_replace('_', '/', substr($class_name, 4)) . '.php')) {
                    $path = CUSTOM_CORE_DIR . '/' . $owner . '/model/' . str_replace('_', '/', substr($class_name, 4)) . '.php';
                } else {
                    $path = APP_DIR . '/' . $owner . '/model/' . str_replace('_', '/', substr($class_name, 4)) . '.php';
                }
                if (file_exists($path)) {
                    return require_once $path;
                } elseif (file_exists(APP_DIR . '/' . $owner . '/dbschema/' . substr($class_name, 4) . '.php') || file_exists(CUSTOM_CORE_DIR . '/' . $owner . '/dbschema/' . substr($class_name, 4) . '.php')) {
                    $parent_model_class = app::get($owner)->get_parent_model_class();
                    eval("class {$owner}_{$class_name} extends {$parent_model_class}{ }");
                    return true;
                } else {
                    throw new exception('Don\'t find model file "' . $class_name . '"');
                    exit;
                }
            default:
                if($trait) {
                    $path = TRAIT_DIR . '/' . $owner . '/lib/' . str_replace('_', '/', $class_name) . '.php';
                } elseif (defined('CUSTOM_CORE_DIR') && file_exists(CUSTOM_CORE_DIR . '/' . $owner . '/lib/' . str_replace('_', '/', $class_name) . '.php')) {
                    $path = CUSTOM_CORE_DIR . '/' . $owner . '/lib/' . str_replace('_', '/', $class_name) . '.php';
                } else {
                    $path = APP_DIR . '/' . $owner . '/lib/' . str_replace('_', '/', $class_name) . '.php';
                }
                if (file_exists($path)) {
                    return require_once $path;
                } else {
                    throw new exception('Don\'t find lib file "' . $class_name . '"');
                    return false;
                }
        }
    } elseif (file_exists($path = APP_DIR . '/base/lib/static/' . $class_name . '.php')) {
        if($trait) {
            $path = TRAIT_DIR . '/base/lib/static/' . $class_name . '.php';
        } elseif (defined('CUSTOM_CORE_DIR') && file_exists(CUSTOM_CORE_DIR . '/base/lib/static/' . $class_name . '.php')) {
            $path = CUSTOM_CORE_DIR . '/base/lib/static/' . $class_name . '.php';
        }
        return require_once $path;
    } else {
        throw new exception('Don\'t find static file "' . $class_name . '"');
        return false;
    }
}//End Function

spl_autoload_register('omsAutoloader');
