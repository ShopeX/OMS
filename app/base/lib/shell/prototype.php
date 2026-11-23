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
 
class base_shell_prototype{

    static $options = array();

    function __construct($shell){
        $this->shell = &$shell;
    }

    function parse_command_args($command_parts,$command_options){
        $args = array();
        self::$options = array();

        foreach($command_options as $option_name => $option){
            if(isset($option['short'])){
                $option_short[$option['short']] = $option_name;
            }
        }

        foreach($command_parts as $part){
            if($part[0]=='-'){
                if($value_option_name){
                    trigger_error('--'.$option_name.' need value',E_USER_ERROR);
                    return false;
                }
                if($part[1]=='-'){
                    $option_name = substr($part,2);
                    if(isset($command_options[$option_name])){
                        if(isset($command_options[$option_name]['need_value']) 
                            && $command_options[$option_name]['need_value']){
                                $value_option_name = $option_name;
                            }else{
                                self::$options[$option_name] = true;
                            }
                    }else{
                        trigger_error('--'.$option_name.' bad option',E_USER_ERROR);
                        return false;
                    }
                }else{
                    $params_len = strlen($part);
                    for($i=1;$i<$params_len;$i++){
                        if($value_option_name){
                            trigger_error('-'.$part[$i].' need value',E_USER_ERROR);
                            return false;
                        }else{
                            if(isset($option_short[$part[$i]])){
                                $option_name = $option_short[$part[$i]];
                                if(isset($command_options[$option_name])){
                                    if(isset($command_options[$option_name]['need_value']) 
                                        && $command_options[$option_name]['need_value']){
                                            $value_option_name = $option_name;
                                        }else{
                                            self::$options[$option_name] = true;
                                        }
                                }else{
                                    trigger_error('--'.$option_name.' bad option',E_USER_ERROR);
                                    return false;
                                }
                            }else{
                                trigger_error('-'.$part[$i].' bad option',E_USER_ERROR);
                                return false;
                            }
                        }
                    }
                }
            }elseif($value_option_name){
                self::$options[$value_option_name] = $part;
                $value_option_name = false;
            }else{
                $args[] = $part;
            }
        }
        if($value_option_name){
            trigger_error('-'.$value_option_name.' need value', E_USER_ERROR);	    
        }
        return $args;
    }

    function get_option($item){
        return isset(self::$options[$item])?self::$options[$item]:null;
    }

    function get_options(){
        return self::$options;
    }

    function exec($command_parts){
        $action = 'command_'.array_shift($command_parts);
        if($action=='command_'){
            echo app::get('base')->_("请输入要执行的指令：")."\n";
            $this->help();
        }elseif(is_callable(array($this,$action))){
            $command_options_define = $this->get_options_define($action);
            $args = $this->parse_command_args($command_parts,$command_options_define);
            return call_user_func_array(array(&$this,$action),$args);
        }else{
            echo substr(get_class($this),8).'::'.substr($action,8)." Command not found.\n";
            return false;
        }
    }

    function get_options_define($action){
        $attr_action_option = $action.'_options';
        if(!isset($this->$attr_action_option)){
            $this->$attr_action_option = array();
        }
        return $this->$attr_action_option;
    }

    function help($command=null){

        $name_prefix = $this->name_prefix();
        $verbose = $this->get_option('verbose');

        foreach(get_object_vars($this) as $k=>$v){
            if(is_string($v) && substr($k,0,8)=='command_'){
                if(method_exists($this,$k)){
                    $name_append = '';
                }else{
                    $name_append = ' //todo';
                }
                $func_name = ($name_prefix?$name_prefix.' ':'').substr($k,8).$name_append;
                $define = $v;
                echo str_pad($func_name,40),$define,"\n";
                if($verbose){
                    $command_options_define = $this->get_options_define($k);
                    if($command_options_define){
                        foreach($command_options_define as $option=>$define){
                            $option_name = '--'.$option;
                            if($define['short']){
                                $option_name .= ' / -'.$define['short'];
                            }
                            if($define['need_value']){
                                $option_name .= ' ['.$define['need_value'].']';
                            }
                            echo str_repeat(' ',10),str_pad($option_name,30),$define['title'],"\n";
                        }
                        echo "\n";
                    }
                }
            }
        }
    }

    function name_prefix(){
        return $this->app->app_id.':'.substr(get_class($this),strlen($this->app->app_id)+9);
    }

    function register_trigger($trigger){
        $this->shell->trigger[$trigger] = &$this;
    }

    function unregister_trigger($trigger){
        unset($this->shell->trigger[$trigger]);
    }

    static function output_table($rows,$cellspace_max=array()){
        $out = array();
        foreach($rows as $i=>$r){
            foreach($r as $k=>$v){
                $cellspace[$k][$i] = strlen($v);
                $cellspace_max[$k] = max($cellspace_max[$k],$cellspace[$k][$i]);
                $out[] = $v;
                $out[] = &$cellspace[$k][$i];
            }
            $out[] = "\n";
        }
        if(is_array($r)){
            foreach($r as $k=>$v){
                foreach($cellspace[$k] as $i=>$len){
                    $cellspace[$k][$i] = str_repeat(' ',$cellspace_max[$k]+2-$len);
                }
            }
        }
        echo implode('',$out);
    }

    static function output(){
        $args = func_get_args();
        foreach($args as $data){
            switch(gettype($data)){
            case 'object':
                echo 'Object<'.get_class($data).">\n";
                break;

            case 'integer':
                case 'double':
                    case 'resource':
                        case 'string':
                            echo $data;
                            break;

                        case 'array':
                            print_r($data);

                        default:
                            var_dump($data);
            }
        }
    }

    static function output_line($string=null){
        echo "\n".str_pad(($string?$string.' ':''),self::shell_width(), "-")."\n\n";
    }

    static function shell_width(){
        $env_width = getenv('COLUMNS');
        return $env_width?$env_width:80;
    }

}
