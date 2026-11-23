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
 
class base_setup_config{

    function __construct(){
        if(file_exists(ROOT_DIR.'/config/config.php')){
            $this->set_sample_file(ROOT_DIR.'/config/config.php');
        }else{
            $this->set_sample_file(ROOT_DIR.'/app/base/examples/config.php');
        }
    }

    function set_sample_file($file){
        $this->sample_file = $file;
    }

    function write($config){

        $this->sample_file = realpath($this->sample_file);

        $sample = file_get_contents($this->sample_file);

        foreach($config as $k=>$v){
            $arr['#(define\\s*\\(\\s*[\'"]'.strtoupper($k).'[\'"]\\s*,\\s*)[^;]+;#i'] = '\\1\''.str_replace('\'','\\\'',$v).'\');';
        }

        kernel::log('Using sample :'.$this->sample_file);
        kernel::log('Writing config file... ok.');
        if(file_put_contents(ROOT_DIR.'/config/config.php',preg_replace(array_keys($arr),array_values($arr),$sample))){
            $this->write_compat();
            return true;
        }else{
            return false;
        }
    }
    
    static function deploy_info(){
        $deploy = file_get_contents(ROOT_DIR.'/config/deploy.xml');
        return kernel::single('base_xml')->xml2array($deploy,'base_deploy');
    }

    function write_compat() 
    {
        $file = ROOT_DIR.'/config/config.php';
        if(file_exists($file)){
            kernel::log('Writing config compat... ok.');
            $sample = preg_replace('/('.preg_quote('/**************** compat functions begin ****************/', '/').')(.*)('.preg_quote('/**************** compat functions end ****************/', '/').')/isU', "\\1" .  "\r\n" . join("\r\n", $this->check_compat()) . "\r\n" . '\\3', file_get_contents($file));
            return file_put_contents($file, $sample);
        }else{
            kernel::log('Writing config compat... failure.');
            return false;
        }
    }//End Function

    function check_compat() 
    {
        $ret = array("#此处程序自动生成，请勿修改\n");
        $ret = array_merge($ret, (array)$this->check_json());   //todo:检查json
        //todo:今后可以加入其它兼容
        return $ret;
    }//End Function

    function check_json() 
    {
        if(!function_exists('json_encode')){
            $ret[] = file_get_contents(dirname(__FILE__) . '/compat/json_encode.txt');
        }
        if(!function_exists('json_decode')){
            $ret[] = file_get_contents(dirname(__FILE__) . '/compat/json_decode.txt');
        }
        return $ret;
    }//End Function
}
