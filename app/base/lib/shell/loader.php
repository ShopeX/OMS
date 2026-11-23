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
 
class base_shell_loader{

    var $commands_dir;
    var $app_id;
    var $trigger = array();
    var $status = array();
    var $input = array();

    function __construct(){
        set_time_limit(0);
        cachemgr::init(false);
        kernel::$console_output = true;
        if(file_exists(ROOT_DIR.'/config/config.php')){
            require(ROOT_DIR.'/config/config.php');
            $this->status['config'] = ROOT_DIR.'/config/config.php';
        }else{
            $this->status['config'] = 'false';
        }
        include(APP_DIR.'/base/defined.php');
        date_default_timezone_set(
            defined('DEFAULT_TIMEZONE') ? ('Etc/GMT'.(DEFAULT_TIMEZONE>=0?(DEFAULT_TIMEZONE*-1):'+'.(DEFAULT_TIMEZONE*-1))):'UTC'
        );
        $this->buildin_commander = new base_shell_buildin($this);;
    }

    function run(){
        ignore_user_abort(false);
        ob_implicit_flush(1);
        ini_set('implicit_flush',true);
        cachemgr::init(false);

        if(strpos(strtolower(PHP_OS), 'win') === 0){
            if(function_exists('mb_internal_encoding')){
                mb_internal_encoding("UTF-8");
                mb_http_output("GBK");
                ob_start("mb_output_handler",2);
            }elseif(function_exists('iconv_set_encoding')){
                iconv_set_encoding("internal_encoding", "UTF-8");
                iconv_set_encoding("output_encoding", "GBK");
                ob_start("ob_iconv_handler",2);
            }
        }

        if(isset($_SERVER['argv'][1])){
            $args = array_shift($_SERVER['argv']);
            $rst = $this->exec_command(implode(' ',$_SERVER['argv']));
            if($rst===false){
                exit(-1);
            }
        }else{
            $this->interactive();
        }
    }

    function print_banner(){
        $version = 1.3;
        echo "Ecos shell V{$version} (abort with ^C), Root: ",ROOT_DIR;
    }
    
    static public function get_width() { 
      $output = @strtolower(exec('stty -a |grep columns'));
      if($output){
          foreach(explode(';',$output) as $part){
              if(strpos($part,'columns')!==false){
                  return intval(str_replace('columns', '', $part));
              }
          }
      }
      return 80;
    }

    function &get_commander($app_id,$shell_command){
        $shell_command = strtolower($shell_command);
        if(!$app_id && isset($this->cmdlibs[$shell_command])){
            return $this->cmdlibs[$shell_command];
        }
        if(file_exists(APP_DIR.'/'.$app_id.'/lib/command/'.$shell_command.'.php')){
            require_once(APP_DIR.'/'.$app_id.'/lib/command/'.$shell_command.'.php');
            $class_name = $app_id.'_command_'.$shell_command;
            $this->cmdlibs[$shell_command] = new $class_name($this);
            $this->cmdlibs[$shell_command]->app = app::get($app_id);
            return $this->cmdlibs[$shell_command];
        }else{
            $commander = false;
            return $commander;
        }
    }

    function interactive(){
        $this->print_banner();
        $i=1;

        while (true) {
            $line = readline("\n".($this->app_id?($i++).':'.$this->app_id:$i++).'> ');
            readline_add_history($line);

            foreach($this->trigger as $trigger_name => &$trigger){
                $trigger->{'begin_'.$trigger_name}($line);
            }

            $this->exec($line); 

            foreach($this->trigger as $trigger_name => &$trigger){
                $trigger->{'end_'.$trigger_name}($line);
            }
            //ob_flush();

        }
    }
    
    function input_option($options,$part,$message=null){
        if(PHP_SAPI!='cli'){
            trigger_error('Can\'t start options editor',E_ERROR);
            exit;
        }
        do{
            echo "\n";
            $output= array();
            $i = 1;
            foreach($options as $key=>$option){
                $map[$i] = $key;
                
                if(!array_key_exists($key,(array)$this->input[$part]) && array_key_exists('default',$option)){
                    $this->input[$part][$key] = $option['default'];
                }

                if($option['type']=='password'){
                    $current_value = $this->input[$part][$key]?str_repeat('*',strlen($current_value)):'(empty)';
                }elseif($option['type']=='select'){
                    if(is_array($option['options_callback'])){
                        $option['options'] = app::get($option['options_callback']['app'])->runtask($option['options_callback']['method'], $this->input);
                        $options[$key]['options'] = $option['options'];
                    }
                    $current_value = $option['options'][$this->input[$part][$key]];
                }elseif($this->input[$part][$key]==''){
                    $current_value = '(empty)';
                }else{
                    $current_value = $this->input[$part][$key];
                }
                
                $output[] = array(
                    str_pad($i,3,' ',STR_PAD_LEFT).'. '.$option['title'],
                    $current_value
                    );
                $i++;
            }
            $this->buildin_commander->output_table( $output );
            $line = readline(str_repeat('_',40)."\n".app::get('base')->_('输入项目编号,或输入井号').'"#"'.app::get('')->_('确认').": ");
        }while($this->save_input_option($options,$map,trim($line),$part));
    }
    
    function save_input_option($options,$map,$input,$part){
        if($input=='#'){
            return false;
        }else{
            $option = $options[$map[$input]];
            switch($option['type']){
                
                case 'password':
                @system('stty -echo');
                $this->input[$part][$map[$input]] = trim(readline("\n".app::get('base')->_('输入')."{$option['title']}: "));
                @system('stty echo');
                break;
                
                case 'select':
                $i=1;
                $output = "\n".app::get('base')->_('选择合适的')."{$option['title']}:\n";
                foreach($options[$map[$input]]['options'] as $k=>$v){
                    $optmap[$i] = $k;
                    $output.= str_pad($i,3,' ',STR_PAD_LEFT).'. '.$v."\n";
                    $i++;
                }
                $output.=app::get('base')->_("输入合适的")."{$option['title']}".app::get('base')->_('编号').": ";
                $this->input[$part][$map[$input]] = $optmap[trim(readline($output))];
                break;
                
                default:
                $this->input[$part][$map[$input]] = trim(readline("\n".app::get('base')->_('输入')."{$option['title']}: "));
                break;
            }
            return true;
        }
    }

    function exec_command($line){
        $this->exec($line);
    }

    function exec($line){
        try{
            $line = trim($line);
            if(substr($line,-1,1)==';'){
                return $this->buildin_commander->php_call($line);
            }else{
                $command_parts = preg_split ("/[\s]+/", $line);
                $shell_command = array_shift($command_parts);
                list($app_id,$commander) = explode(':',$shell_command);
                if(!$commander){
                    $commander = $app_id;
                    if(method_exists($this->buildin_commander,'command_'.$commander)){
                        array_unshift($command_parts,$shell_command);
                        return $this->buildin_commander->exec($command_parts);
                    }else{
                        echo $shell_command.": Command not found.";
                    }
                }else{
                    $commander = $this->get_commander($app_id,$commander);
                    if($commander){
                        return $commander->exec($command_parts);
                    }else{
                        echo $shell_command.": Command not found.";
                        return false;
                    }
                }
            }
        }catch (Exception $e){
            echo 'Error: ',  $e->getMessage(), "";
        }
    }

}

if(!function_exists('readline')){

    function readline($prompt){
        echo $prompt;
        //ob_flush();
        $input = '';
        while(1){
            $key = fgetc(STDIN);
            switch($key){
            case "\n":
                return $input;

            default:
                $input.=$key;
            }
        }
    }

    function readline_add_history($line){
        //...
    }

    function readline_completion_function($callback){

    }
}
