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

class base_initial
{

    public function __construct($app_id)
    {
        $this->app = app::get($app_id);
    } //End Function

    public function init()
    {
        if (defined('CUSTOM_CORE_DIR') && file_exists(CUSTOM_CORE_DIR . '/' . $this->app->app_id . '/initial')) {
            $demo_dir = CUSTOM_CORE_DIR . '/' . $this->app->app_id . '/initial';
        } else {
            $demo_dir = $this->app->app_dir . '/initial';
        }
        if (is_dir($demo_dir)) {
            $handle = opendir($demo_dir);
            while ($file = readdir($handle)) {
                $realfile = $demo_dir . '/' . $file;
                if (is_file($realfile)) {
                    list($app_id, $model, $ext) = explode('.', $file);
                    if ($ext == 'sdf') {
                        $this->init_sdf($app_id, $model, $realfile);
                    } elseif ($ext == 'php' && $model == 'setting') {
                        $setting = include $realfile;
                        $this->init_setting($app_id, $setting);
                    } elseif ($ext == 'sql') {
                        $this->init_sql($app_id, $model, $realfile);
                    }
                }
            }
            closedir($handle);
        }
    } //End Function

    public function init_setting($app_id, $setting)
    {
        $app = app::get($app_id);
        if (is_array($setting)) {
            foreach ($setting as $key => $value) {
                $app->setConf($key, $value);
            }
        }
    } //End Function

    public function init_sdf($app_id, $model, $file)
    {
        $handle = fopen($file, 'r');
        if ($handle) {
            while (!feof($handle)) {
                $buffer .= fgets($handle);
                if (!($sdf = unserialize($buffer))) {
                    continue;
                }
                app::get($app_id)->model($model)->db_save($sdf);
                $buffer = '';
            }
            fclose($handle);
        }
    } //End Function

    /**
     * undocumented function
     *
     * @return void
     * @author
     **/
    public function init_sql($app_id, $model, $file)
    {
        if ($sqls = file_get_contents($file)) {
            foreach (array_filter(preg_split("/;[\r\n]/", $sqls)) as $sql) {
                $sql = trim($sql);
                if (!$sql) {
                    continue;
                }
                kernel::database()->exec($sql);
            }
        }
    }

} //End Class
