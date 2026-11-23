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

class base_command_i18n extends base_shell_prototype 
{
    
    var $command_create_convert = '生成替换文件 app_id (etc: base)';
    public function command_create_convert() 
    {
        $options = func_get_args();
        $app_id = $options[0];
        if($app_id){
            $this->parse_app($app_id);
        }else{
            $rows = app::get('base')->model('apps')->getList('app_id');
            foreach($rows AS $row){
                $this->parse_app($row['app_id']);
            }
        }
    }//End Function

    private function parse_app($app_id) 
    {
        $app = app::get($app_id);
        $dir = $app->lang_dir . '/' . $lang;
        if(!is_dir($dir))   mkdir($dir, 0775, true);
        $file = $dir . '/convert.php';
        $array = array();

        $this->parse_php($app, $app->app_dir . '/controller', $array);
        $this->parse_php($app, $app->app_dir . '/dbschema', $array);
        $this->parse_php($app, $app->app_dir . '/model', $array);
        $this->parse_php($app, $app->app_dir . '/lib', $array);
        
        $this->parse_html($app, $app->app_dir . '/view', $array);
        $this->parse_html($app, $app->app_dir . '/lang/js', $array);

        if(!empty($array)){
            $content = '<?php' . "\n";
            $content .= 'return ' . var_export($array, true) . ';';
            $content .= "\n" . '//' . count($array) . ' items';
            $content .= "\n" . '?>';

            file_put_contents($file, $content);
            kernel::log(sprintf('%s 语言包转换文件生成成功，共 %d items', $app->app_id, count($array)));
        }else{
            kernel::log(sprintf('%s 没有语言包', $app->app_id));
        }
    }//End Function

    private function parse_php($app, $dir, &$array) 
    {
        if(is_dir($dir)){
            if($handle = opendir($dir)){
                while(($file = readdir($handle)) !== false){
                    if(substr($file, 0, 1) != '.'){
                        if(is_dir($dir . '/' . $file)){
                            $this->parse_php($app, $dir . '/' . $file, $array);
                        }elseif(substr($file, -3, 3) == 'php'){
                            $t = substr($dir . '/' . $file, strlen($app->app_dir));
                            foreach(file($dir . '/' . $file) AS $line=>$subject){
                                if(preg_match_all('/\-\>_\([\'"](.+)[\'"]\)/isU', $subject, $match)){
                                    foreach($match[1] as $word){
                                        $array[stripslashes($word)]['conv'] = '';
                                        $array[stripslashes($word)]['file'][] = $t . ':' . ($line+1);
                                    }
                                }
                            }
                        }
                    }
                }
                closedir($handle);
            }
        }
    }//End Function

    private function parse_html($app, $dir, &$array) 
    {
        if(is_dir($dir)){
            if($handle = opendir($dir)){
                while(($file = readdir($handle)) !== false){
                    if(substr($file, 0, 1) != '.'){
                        if(is_dir($dir . '/' . $file)){
                            $this->parse_html($app, $dir . '/' . $file, $array);
                        }else{
                            $t = substr($dir . '/' . $file, strlen($app->app_dir));
                            foreach(file($dir . '/' . $file) AS $line=>$subject){
                                if(preg_match_all('/\<\{.*[=]{1}[\'"](.+)[\'"]\|t[:]?.*\}\>/is', $subject, $match)){
                                    foreach($match[1] as $word){
                                        $array[stripslashes($word)]['conv'] = '';
                                        $array[stripslashes($word)]['file'][] = $t . ':' . ($line+1);
                                    }
                                }
                                if(preg_match_all('/\<\{t\}\>(.+)<\{\/t\}\>/isU', $subject, $match)){
                                    foreach($match[1] as $word){
                                        $array[stripslashes($word)]['conv'] = '';
                                        $array[stripslashes($word)]['file'][] = $t . ':' . ($line+1);
                                    }
                                }
                            }
                        }
                    }
                }
                closedir($handle);
            }
        }
    }//End Function
    
}//End Class