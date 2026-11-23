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
 
class base_component_compiler{

    var $left_delimiter            = "<{";
    var $right_delimiter            = "}>";
    var $enable_strip_whitespace = false;
    var $_vars            =    array();
    //    var $_plugins            =    array();    // stores all internal plugins
    var $_file            =    "";        // the current file we are processing
    var $_literal            =    array();    // stores all literal blocks
    var $_foreachelse_stack        =    array();
    var $_for_stack            =    0;
    var $_if_stack            =   '';
    var $_sectionelse_stack     =   array();    // keeps track of whether section had 'else' part
    var $_switch_stack        =    array();
    var $_tag_stack            =    array();
    var $_require_stack        =    array();    // stores all files that are "required" inside of the template
    var $_block_stack = array();
    var $_head_stack = array();
    var $bundle_vars = array();
    var $compile_helper = array();
    var $view_helper = array();

    function __construct(&$controller){
        $this->controller = $controller;

        foreach(kernel::servicelist('view_compile_helper') as $helper){
            foreach(get_class_methods($helper) as $method){
                if(substr($method,0,8)=='compile_'){
                     $this->set_compile_helper($method,$helper);
                }
            }
        }

        foreach(kernel::servicelist('view_helper') as $helper_path=>$helper){
            foreach(get_class_methods($helper) as $method){
                $this->set_view_helper($method,$helper_path);
            }
        }
    }

    function set_compile_helper($method,&$helper){
        $this->compile_helper[$method] = $helper;
    }

    function set_view_helper($method,$helper_path){
        $this->view_helper[$method] = $helper_path;
    }

    function compile_file($file_path){
        if(file_exists($file_path)){
            return $this->compile(file_get_contents($file_path));
        }else{
            trigger_error('compile file does\'s not exists ['.$file_path.']', E_USER_ERROR);
            return false;
        }
    }

    function &compile($file_contents){
        $this->_if_stack = '';
        $compiled_text = '';
        $this->_block_stack = array();

        foreach((array)kernel::servicelist('view_compile_prefilter') as $object){
            $file_contents = $object->process($func,$file_contents);
        }

        $ldq = preg_quote($this->left_delimiter,'!');
        $rdq = preg_quote($this->right_delimiter,'!');
        $file_contents = preg_replace("!{$ldq}\*.*?\*{$rdq}!su",'',$file_contents);
        $file_contents = preg_replace("!(\<\?|\?\>)!",'<?php echo \'\1\'; ?>',$file_contents);
        if($this->bundle_vars){
            $this->bundle_vars_re = '!\$('.implode('|',$this->bundle_vars).')!';
        }

        foreach(preg_split('!'.$ldq.'(\s*(?:\/|)[a-z][a-z\_0-9]*|)(.*?)'.$rdq.'!isu',$file_contents,-1,PREG_SPLIT_DELIM_CAPTURE) as $i=>$v){
            $i = $i%3;
            if($i==0){
                if(strpos($this->_if_stack,'2')===false){
                    $compiled_text.=$v;
                }
            }elseif($i==1){
                $func = trim($v);
            }else{

                $bundle_var_only = null;
                $argments = $this->_parse($v,$bundle_var_only);
                if($bundle_var_only===null){
                    $bundle_var_only = strpos(str_replace('$this->bundle_vars','',$argments),'$')===false;
                }

                if($func){
                    $_result = $this->_parse_function($func,$argments,$bundle_var_only);
                    if(strpos($this->_if_stack,'2')===false){
                        $compiled_text.=$_result;
                    }
                }elseif(strpos($this->_if_stack,'2')===false){
                    ob_start();
                    $argments = $this->_fix_modifier($argments,true,$bundle_var_only);
                    $a = ob_get_contents();
                    ob_end_clean();
                    if($bundle_var_only){
                        if($argments){
                            eval('$out = '.$argments.';');
                        }else{
                            echo $a;
                        }
                        $compiled_text.= $out ;
                    }else{
                        $compiled_text.='<?php echo '.$argments.'; ?>';
                    }
                }
            }
        }
        if($this->_block_stack){
            trigger_error("Block ".implode(',',$this->_block_stack)." not closed", E_USER_ERROR);
        }
        $this->post_compile($compiled_text);
        return $compiled_text;
    }

    function post_compile(&$compiled_text){
        foreach((array)kernel::servicelist('view_compile_postfilter') as $object){
            $file_contents = $object->process($func,$file_contents);
        }
        
        preg_match_all("/" . preg_quote('$this->__view_helper_model[\'') . "([^']*)" . preg_quote('\']->') . "/isu", $compiled_text, $matchs);
        if(count($matchs[1])){
            $helpers = array_unique($matchs[1]);
            foreach($helpers AS $helper){
                $compiled_text = '<?php $this->__view_helper_model[\'' . $helper . '\'] = kernel::single(\'' . $helper . '\'); ?>' . $compiled_text;
            }
        }//todo: include所需的view_helper_model

        if($this->enable_strip_whitespace){
            $compiled_text = $this->strip_whitespace($compiled_text);
        }

        $this->_require_stack = array();
        $this->_head_stack = array();
        $compiled_text = preg_replace(array('/\<\?php\s*\?\>/','/\?\>\s*\<\?php/'),'',$compiled_text);
    }


    function _parse_function($function,$arguments,$bundle_var_only=false){
        switch ($function) {
            //case 't':
            //case '/t':
            //    return;

            case 'ldelim':
                return $this->left_delimiter;

            case 'rdelim':
                return $this->right_delimiter;

            case 'dump':
                $args = $this->_parse_arguments($arguments,false);
                return '<?php var_'.'dump('.$args['var'].'); ?>';

            case 'link':
                $_args = $this->_parse_arguments($arguments);
                if(!isset($_args['app'])){
                    $_args['app'] = "'".$this->controller->app->app_id."'";
                }
                foreach($_args as $key => $value) {
                    if (is_bool($value)){
                        $value = $value ? 'true' : 'false';
                    }elseif (is_null($value)){
                        $value = 'null';
                    }
                    $_args[$key] = "'$key' => $value";
                }
                return '<?php echo kernel::router()->gen_url(array(' . implode(',', (array)$_args) . ')); ?>';

            case 'foreachelse':
                return "<?php }else{ ?>";

            case 'break':
            case 'continue':
                return '<?php ' . $function . '; ?>';
                break;

            case 'if':
                $arguments = $this->_arguments_if($arguments,$bundle_var_only);
                if($bundle_var_only){
                    eval('$arguments=('.$arguments.');');
                    $this->_if_stack = ($arguments?'106':'206').$this->_if_stack;
                    return;
                }else{
                    $this->_if_stack = '000'.$this->_if_stack;
                    return '<?php if(' . $arguments . '){ ?>';
                }
            case 'else':
                if($this->_if_stack[1]=='5'){
                    $this->_if_stack[0] = '2';
                    return;
                }elseif($this->_if_stack[0]=='0'){
                    $this->_if_stack[1] = '5';
                    return '<?php }else{ ?>';
                }elseif($this->_if_stack[0]=='1'){
                    $this->_if_stack[0] = '2';
                    $this->_if_stack[1] = '5';
                    return;
                }elseif($this->_if_stack[0]=='2'){
                    $this->_if_stack[0] = '1';
                    return;
                }
            case 'elseif':
                if($this->_if_stack[1]=='5'){
                    $this->_if_stack[0] = '2';
                    return;
                }elseif($this->_if_stack[0]=='0'){ //之前存在if判断
                    $arguments = $this->_arguments_if($arguments,$bundle_var_only);
                    if($bundle_var_only){
                        eval('$arguments=('.$arguments.');');
                        if($arguments){
                            return $this->_parse_function('else',$arguments,$bundle_var_only);
                        }else{
                            $this->_if_stack[0]='2';
                            $this->_if_stack[2]='6';
                            return;
                        }
                    }else{
                        return '<?php }elseif('. $arguments . '){ ?>';
                    }
                }elseif($this->_if_stack[0]=='2'){ //之前为否
                    $this->_parse_function('/if',$arguments,$bundle_var_only);
                    return $this->_parse_function('if',$arguments,$bundle_var_only);
                }elseif($this->_if_stack[0]=='1'){ //之前为绝对是， 禁掉今后所有
                    return $this->_parse_function('else',$arguments,$bundle_var_only);
                }
            case '/if':
                if($this->_if_stack[2] == '6'){
                    $_result = '';
                }else{
                    $_result = '<?php } ?>';
                }
                $this->_if_stack = isset($this->_if_stack[5])?substr($this->_if_stack,3):'';
                return $_result;

            case 'foreach':
                $_args = $this->_parse_arguments($arguments,$bundle_var_only);
                if (!isset($_args['from'])){
                    trigger_error("missing 'from' attribute in 'foreach' in " . __FILE__ . ' on line ' . __LINE__, E_USER_ERROR);
                }
                if (!isset($_args['value']) && !isset($_args['item'])){
                    trigger_error("missing 'value' attribute in 'foreach' in " . __FILE__ . ' on line ' . __LINE__, E_USER_ERROR);
                }
                if (isset($_args['value'])){
                    $_args['value'] = $this->_dequote($_args['value']);
                }elseif (isset($_args['item'])){
                    $_args['value'] = $this->_dequote($_args['item']);
                }
                isset($_args['key']) ? $_args['key'] = "\$this->_vars['".$this->_dequote($_args['key'])."'] => " : $_args['key'] = '';
                if($_args['name']){
                    array_push($this->_foreachelse_stack, $_args['name']);
                    $_result = '<?php if(is_array('.$_args['from'].'))$this->_env_vars[\'foreach\'][\''.$_args['name'].'\']=array(\'total\'=>count('.$_args['from'].'),\'iteration\'=>0);foreach ((array)' . $_args['from'] . ' as ' . $_args['key'] . '$this->_vars[\'' . $_args['value'] . '\']){
                        $this->_env_vars[\'foreach\'][\''.$_args['name'].'\'][\'first\'] = ($this->_env_vars[\'foreach\'][\''.$_args['name'].'\'][\'iteration\']==0);
                        $this->_env_vars[\'foreach\'][\''.$_args['name'].'\'][\'iteration\']++;
                        $this->_env_vars[\'foreach\'][\''.$_args['name'].'\'][\'last\'] = ($this->_env_vars[\'foreach\'][\''.$_args['name'].'\'][\'iteration\']==$this->_env_vars[\'foreach\'][\''.$_args['name'].'\'][\'total\']);
?>';
                }else{
                    array_push($this->_foreachelse_stack, false);
                    $_result = '<?php if('.$_args['from'].')foreach ((array)' . $_args['from'] . ' as ' . $_args['key'] . '$this->_vars[\'' . $_args['value'] . '\']){ ?>';
                }
                return $_result;

            case '/foreach':
                if ($name = array_pop($this->_foreachelse_stack)){
                    return '<?php } unset($this->_env_vars[\'foreach\'][\''.$name.'\']); ?>';
                }else{
                    return '<?php } ?>';
                }

            case 'literal':
            case 'for':
            case '/for':
            case 'section':
            case 'sectionelse':
            case '/section':
            case 'while':
            case '/while':
                return;

            case 'switch':
                $_args = $this->_parse_arguments($arguments,$bundle_var_only);
                if (!isset($_args['from'])){
                    trigger_error("missing 'from' attribute in 'switch' in " . __FILE__ . ' on line ' . __LINE__, E_USER_ERROR);
                }
                array_push($this->_switch_stack, array("matched" => false, "var" => $this->_dequote($_args['from'])));
                return;

            case '/switch':
                array_pop($this->_switch_stack);
                return '<?php break; endswitch; ?>';

            case 'case':
                if (count($this->_switch_stack) > 0){
                    $_result = "<?php ";
                    $_args = $this->_parse_arguments($arguments,$bundle_var_only);
                    $_index = count($this->_switch_stack) - 1;
                    if (!$this->_switch_stack[$_index]["matched"])
                    {
                        $_result .= 'switch(' . $this->_switch_stack[$_index]["var"] . '): ';
                        $this->_switch_stack[$_index]["matched"] = true;
                    }else{
                        $_result .= 'break; ';
                    }
                    if (!empty($_args['value']))
                    {
                        $_result .= 'case '.$_args['value'].': ';
                    }else{
                        $_result .= 'default: ';
                    }
                    return $_result . ' ?>';
                }else{
                    trigger_error("unexpected 'case', 'case' can only be in a 'switch' in " . __FILE__ . ' on line ' . __LINE__, E_USER_ERROR);
                }
                break;

            case 'assign':
                $_args = $this->_parse_arguments($arguments,$bundle_var_only);

                if (!isset($_args['var'])){
                    trigger_error("missing 'var' attribute in 'pagedata' in " . __FILE__ . ' on line ' . __LINE__, E_USER_ERROR);
                }
                if (!isset($_args['value'])){
                    trigger_error("missing 'value' attribute in 'pagedata' in " . __FILE__ . ' on line ' . __LINE__, E_USER_ERROR);
                }
                if(false===$_args['value']){
                    $_args['value']='false';
                }elseif(null===$_args['value']){
                    $_args['value']='null';
                }elseif(''===$_args['value']){
                    $_args['value']='';
                }
                return '<?php $this->_vars[' . $_args['var'] . ']='. $_args['value'].'; ?>';

            case 'include':
                return $this->tpl_compile_include($arguments, $this);

            default:
                $_result = "";
                if ($this->_compile_ui_function($function, $arguments, $_result)){
                    return $_result;
                }elseif ($this->_compile_compiler_function($function, $arguments,$bundle_var_only, $_result)){
                    return $_result;
                }elseif($this->_compile_custom_block($function, $arguments, $_result)){
                    if($function[0]=='/'){
                        if(substr($function,1)!=array_pop($this->_block_stack)){
                            trigger_error('template: block function '.$function.'not closed',E_USER_ERROR);
                        }
                    }else{
                        $this->_block_stack[] = $function;
                    }
                    return $_result;
                }elseif ($this->_compile_custom_function($function, $arguments, $_result)){
                    return $_result;
                }else{
                    trigger_error($function." function does not exist in " . __FILE__ . ' on line ' . __LINE__, E_USER_ERROR);
                }
        }
    }


    function tpl_compile_include($arguments, &$object){
        $_args = $object->_parse_arguments($arguments);
        $app = "'".$this->controller->app->app_id."'";

        $arg_list = array();
        if (empty($_args['file'])){
            $object->trigger_error("missing 'file' attribute in include tag in " . __FILE__ . ' on line ' . __LINE__, E_USER_ERROR);
        }

        foreach ($_args as $arg_name => $arg_value)
        {
            if ($arg_name == 'file'){
                $include_file = $arg_value;
                continue;
            }elseif($arg_name == 'app'){
                $app = $arg_value;
                continue;
            }elseif ($arg_name == 'assign'){
                $assign_var = $arg_value;
                continue;
            }
            if (is_bool($arg_value)){
                $arg_value = $arg_value ? 'true' : 'false';
            }
            $arg_list[] = "'$arg_name' => $arg_value";
        }

        if (isset($assign_var)){
            $output = '<?php $_tpl_tpl_vars = $this->_vars;' .
                "\n\$this->_vars[" . $assign_var . "] = \$this->_fetch_compile_include(".$app.',' . $include_file . ", array(".implode(',', (array)$arg_list)."));\n" .
                "\$this->_vars = \$_tpl_tpl_vars;\n" .
                "unset(\$_tpl_tpl_vars);\n" .
                ' ?>';
        }else{
            $output = '<?php $_tpl_tpl_vars = $this->_vars;' .
                "\necho \$this->_fetch_compile_include(".$app.',' . $include_file . ", array(".implode(',', (array)$arg_list)."));\n" .
                "\$this->_vars = \$_tpl_tpl_vars;\n" .
                "unset(\$_tpl_tpl_vars);\n" .
                ' ?>';
        }
        return $output;
    }

    function _arguments_if($arguments,$bundle_var_only){
        if(!$this->_if_replace){
            $to_replace = array(
                'is\s+not\s+odd'=>'%2==0',
                'is\s+odd'=>'%2==1',
                'neq'=>'!=',
                'eq'=>'==',
                'ne'=>'!=',
                'lt'=>'<',
                'gt'=>'>',
                'lte'=>'<=',
                'le'=>'<=',
                'ge'=>'>=',
                'and'=>'&&',
                'not'=>'!',
                'mod'=>'%',
                'is'=>'==',
            );
            foreach($to_replace as $k=>$v){
                $this->_if_replace[0][] = '!(\s+)'.$k.'(\s+)!i';
                $this->_if_replace[1][] = '\1'.$v.'\2';
            }
        }

        $this->_begin_fix_quote($arguments);
        $arguments = str_replace('||',' or ',$arguments);
        $arguments = preg_replace($this->_if_replace[0],$this->_if_replace[1],$arguments.' ');
        $a = explode(' ',$arguments);
        foreach($a as $i=>$line){
            $a[$i] = $this->_fix_modifier($line,false,$bundle_var_only);
        }
        $arguments = implode(' ',$a);
        $this->_end_fix_quote($arguments);

        return $arguments;
    }

    function _parse_arguments($arguments,$bundle_var_only=false){
        preg_match_all('/([a-z0-9\_]+)=(\'|"|)(.*?(?:[^\\\\]|))\2\s/isu',$arguments.' ',$matches,PREG_SET_ORDER);
        $ret = array();
        foreach($matches as $match){
            if($match[2]){
                $ret[$match[1]] = $match[2].$match[3].$match[2];
            }else{
                $ret[$match[1]] = $this->_fix_modifier($match[3],true,$bundle_var_only);
            }
        }
        return $ret;
    }

    function _prepare_fix_quote($match){
        $this->_fix_quotes[$this->_fix_quotes_seq] = $match[0];
        return '_!ok'.($this->_fix_quotes_seq++).'!_';
    }

    function _restone_fix_quote($match){
        return $this->_fix_quotes[$match[1]];
    }

    function _begin_fix_quote(&$variable){
        $this->_fix_quotes_seq=0;
        $this->_fix_quotes = array();
        $variable = preg_replace_callback('/([\'"]).*?(?:[^\\\\]|)\1/u',array(&$this,'_prepare_fix_quote'),$variable);
    }

    function _end_fix_quote(&$variable){
        if($this->_fix_quotes){
            $variable = preg_replace_callback('/_!ok([0-9]+)!_/u',array(&$this,'_restone_fix_quote'),$variable);
        }
    }

    function getRuntimeFunc($function, $real=true){
        if(isset($this->view_helper[$function])){
            if($real === true){
                return 'kernel::single(\''.$this->view_helper[$function].'\')->'.$function;
            }else{
                return '$this->__view_helper_model[\''.$this->view_helper[$function].'\']->'.$function;
            }
        }else{
            return false;
        }
    }

    function _fix_modifier($variable,$fix_quote = true,&$bundle_var_only){
        if(strpos($variable,'|')){
            if($fix_quote)$this->_begin_fix_quote($variable);
            $_mods = explode('|',$variable);
            $variable = array_shift($_mods);
            foreach($_mods as $mod){
                if($p=strpos($mod,':')){
                    $_arg = $variable.str_replace(':',',',substr($mod,$p));
                    $mod = substr($mod,0,$p);
                }else{
                    $_arg = $variable;
                }
                if($mod[0]=='@'){
                    $mod = substr($mod,1);
                }
                if(isset($this->compile_helper['compile_modifier_'.$mod])){
                    $variable = $this->compile_helper['compile_modifier_'.$mod]->{'compile_modifier_'.$mod}($_arg,$this,$bundle_var_only);
                }elseif($func = $this->getRuntimeFunc('modifier_'.$mod)){
                    $variable = $func.'('.$_arg.')';
                }elseif(function_exists($mod)){
                    $variable = $mod.'('.$_arg.')';
                }else{
                    $variable = "trigger_error(\"'" . $mod . "' modifier does not exist\", E_USER_NOTICE);";
                }
            }
            if($fix_quote)$this->_end_fix_quote($variable);
        } else {
            $arrChange = ['count', 'in_array'];
            foreach($arrChange as $v) {
                if(strpos($variable, $v) === 0 && $func = $this->getRuntimeFunc('modifier_'.$v)) {
                    $variable = $func.substr($variable, strlen($v));
                    break;
                }
            }
        }

        return $variable;
    }

    function _parse($cmd_line,&$bundle_var_only){
        $this->i = 0;
        $this->lib=array();
        $res = preg_replace_callback('!(\$[a-z0-9\_\.\$\[\]\"\\\']+)!isu',array(&$this,'_in_str'),$cmd_line);
        foreach($this->lib as $i=>$var){
            $var_ns = '';
            if($p=strpos($var,'.')){
                $first = substr($var,0,$p);

                if($first=='$smarty'){
                    $first='$env';
                    $var = '$env'.substr($var,7);
                    $p=4;
                }

                if($first=='$env'){
                    $bundle_var_only = false;

                    $p = strpos($var,'.',$p+1);
                    if(!$p){
                        $p = strlen($var);
                    }
                    $second = strtoupper(substr($var,5,$p-5));

                    switch($second){
                    case 'CONF':
                        if($p2 = strpos($var,'.',$p+1)){
                            $sub = substr($var,$p+1,$p2-$p-1);
                            $var = 'app::get(\''.$sub.'\')->getConf(\''.substr($var,$p2+1).'\')';
                        }else{
                            $var = '$this->app->getConf(\''.substr($var,$p+1).'\')';
                        }
                        
                        $var_ns = -1;
                        break;
                    case 'GET':
                    case 'POST':
                    case 'COOKIE':
                    case 'ENV':
                    case 'SERVER':
                    case 'SESSION':
                        if($p){
                            $var = substr($var,$p+1);
                        }else{
                            $var = '';
                        }
                        $var_ns = '$_'.$second;
                        break;
                    case 'BASE_URL':
                        $var = '';
                        $var_ns = 'kernel::base_url()';
                        break;
                    case 'NOW':
                        $var = '';
                        $var_ns = 'time()';
                        break;
                    case 'SECTION':
                        $var = substr($var,$p+1);
                        $var_ns = '$this->_sections';
                        break;
                    case 'LDELIM':
                        $var = '';
                        $var_ns = '$this->left_delimiter';
                        break;
                    case 'RDELIM':
                        $var = '';
                        $var_ns = '$this->right_delimiter';
                        break;
                    case 'TEMPLATE':
                        $var = '';
                        $var_ns = '$this->_file';
                        break;
                    case 'CONST':
                        $var_ns = 'constant(\''.substr($var,$p+1).'\')';
                        $var = '';
                        break;
                    case 'APP':
                        if(isset($var[$p+1])){
                            if($p2 = strpos($var,'.',$p+1)){
                                $sub = substr($var,$p+1,$p2-$p-1);
                                if(strtoupper($sub)=='CONF'){
                                    $var = '$this->app->getConf(\''.substr($var,$p2+1).'\')';
                                    $var_ns = -1;
                                }else{
                                    $var_ns = '$this->app->'.$sub;
                                    $var = substr($var,$p2);
                                }
                            }else{
                                $var_ns = '$this->app->'.substr($var,$p+1);
                                $var = '';
                            }
                        }else{
                            $var_ns = '\'App-\'.$this->app->ident';
                            $var = '';
                        }
                        break;
                    case 'FOREACH':
                        $var = substr($var,$p+1);
                        $var_ns = '$this->_env_vars[\'foreach\']';
                        break;
                    default:
                        $var_ns = '$this->_env_vars[\''.substr($var,5,$p-5).'\']';
                        $var = substr($var,$p+1);
                        break;
                    }
                }else{
                    $first = substr($first,1);
                    if(isset($this->bundle_vars[$first])){
                        $var_ns = '$this->bundle_vars[\''.$first.'\']';
                    }else{
                        $var_ns = '$this->_vars[\''.$first.'\']';
                    }
                    $var = substr($var,$p+1);
                }
                if($var_ns!=-1){
                    $a = preg_split('/(\.|\[[\\\'a-z0-9\_\"]+\])/',$var,-1,PREG_SPLIT_DELIM_CAPTURE);
                    if($a){
                        $var = '';
                        foreach($a as $k=>$l){
                            if($k%2==1){
                                if($l!='.'){
                                    $var.=$l;
                                }
                            }else{
                                if(isset($l[0])){
                                    if($l[0]!='$' && $l[0]!='"' && $l[0]!='\''){
                                        $l = "'".$l."'";
                                    }
                                    $var.='['.$l.']';
                                }
                            }
                        }
                    }
                }
            }

            if($var_ns!=-1){
                if($this->bundle_vars){
                    $var = $var_ns.preg_replace(array($this->bundle_vars_re,'!\$([a-z0-9\_]+)!iu'),array('$this->bundle_vars[\'\1\']','$this->_vars[\'\1\']'),$var);
                }else{
                    $var = $var_ns.preg_replace('!\$([a-z0-9\_]+)!iu','$this->_vars[\'\1\']',$var);
                }
            }

            $var = preg_replace_callback('!"_s([0-9]+)s_"!',array(&$this,'_rest'),$var);
            $this->lib[$i] = $var;
        }
        $res = preg_replace_callback('!"_s([0-9]+)s_"!',array(&$this,'_rest_root'),$res);
        $this->lib=array();
        return $res;
    }

    function _rest_root($match){
        return $this->lib[$match[1]];
    }

    function _rest($match){
        return $this->lib[$match[1]];
    }

    function _in_str($varstr){
        $varstr = $varstr[1];
        $varstr = preg_replace_callback('/\[(\$[a-z0-9\_\.]+)\]/iu',array(&$this,'_in_sub_str'),$varstr);
        $this->lib[$this->i] = $varstr;
        return '"_s'.($this->i++).'s_"';
    }

    function _in_sub_str($varstr){
        $varstr = $varstr[1];
        $varstr = preg_replace_callback('/\[(\$[a-z0-9\_\.]+)\]/iu',array(&$this,__METHOD__),$varstr);
        $this->lib[$this->i] = $varstr;
        return '."_s'.($this->i++).'s_"';
    }

    function strip_whitespace($tpl_source){
        $a = preg_split('/(<\s*(?:pre|script|textarea).*?>.*?<\s*\/\s*(?:pre|script|textarea)\s*>)/isu',$tpl_source,-1,PREG_SPLIT_DELIM_CAPTURE);
        $token = 'o-o-o-o';
        $r = '';
        $tpl_source = '';
        foreach($a as $k=>$v){
            if($k % 2 == 0){
                $r.=$v.$token;
                unset($a[$k]);
            }
        }
        $r = preg_replace('/\s+/s',' ',$r);
        foreach(explode($token,$r) as $i=>$txt){
            $tpl_source.=$txt;
            if(isset($a[2*$i+1])){
                $tpl_source.=$a[2*$i+1];
            }
        }
        return $tpl_source;
    }

    function _dequote($string){
        if (($string[0] == "'" || $string[0] == '"') && $string[strlen($string)-1] == $string[0]){
            return substr($string, 1, -1);
        }else{
            return $string;
        }
    }

    function _compile_ui_function($function, $arguments,&$_result){
        if(method_exists($this->controller->ui(),$function)){
            $_args = $this->_parse_arguments($arguments);
            foreach($_args as $key => $value)
            {
                if (is_bool($value)){
                    $value = $value ? 'true' : 'false';
                }elseif (is_null($value)){
                    $value = 'null';
                }
                $_args[$key] = "'$key' => $value";
            }
            $_result = '<?php echo ';
            $_result .= '$this->ui()->'.$function . '(array(' . implode(',', (array)$_args) . '));';
            $_result .= '?>';
            return true;
        }else{
            return false;
        }
    }

    function _compile_compiler_function($function, $arguments,$bundle_var_only, &$_result){
        $function = "compile_".$function;
        if(method_exists($this->controller,$function)){
            $_args = $this->_parse_arguments($arguments,$bundle_var_only);
            $_result = $this->controller->$function($_args,$this,$bundle_var_only);
            return true;
        }elseif (isset($this->compile_helper[$function])){
            $object = $this->compile_helper[$function];
            $_args = $this->_parse_arguments($arguments,$bundle_var_only);
            $_result = '<?php '.$object->$function($_args,$this,$bundle_var_only).' ?>';
            return true;
        }else{
            return false;
        }
    }

    function _compile_custom_function($function,$arguments, &$_result){

        if(method_exists($this->controller,'tpl_function_'.$function)){
            $function = '$this->tpl_function_'.$function;
        }else{
            $function = $this->getRuntimeFunc("function_".$function, false);
        }

        if ($function) {
            $_args = $this->_parse_arguments($arguments);
            foreach($_args as $key => $value) {
                if (is_bool($value)){
                    $value = $value ? 'true' : 'false';
                }elseif (is_null($value)){
                    $value = 'null';
                }
                $_args[$key] = "'$key' => $value";
            }
            $_result = '<?php echo ';
            $_result .= $function . '(array(' . implode(',', (array)$_args) . '), $this);';
            $_result .= '?>';
            return true;
        } else {
            return false;
        }
    }

    function _compile_custom_block($function, $arguments, &$_result){

        if ($function[0] == '/') {
            $start_tag = false;
            $function = substr($function, 1);
        } else {
            $start_tag = true;
        }
        if ($function_call = $this->getRuntimeFunc("block_".$function, false))
        {
            if ($start_tag)
            {
                $_args = $this->_parse_arguments($arguments);
                foreach($_args as $key => $value)
                {
                    if (is_bool($value))
                    {
                        $value = $value ? 'true' : 'false';
                    }elseif (is_null($value)){
                        $value = 'null';
                    }
                    $_args[$key] = "'$key' => $value";
                }
                $_result = "<?php \$this->_tag_stack[] = array('".str_replace("'","\\'",$function)."', array(".implode(',', (array)$_args).")); ";
                $_result .= $function_call . '(array(' . implode(',', (array)$_args) .'), null, $this); ';
                $_result .= 'ob_start(); ?>';
            }
            else
            {
                $_result .= '<?php $_block_content = ob_get_contents(); ob_end_clean(); ';
                $_result .= '$_block_content = ' . $function_call . '($this->_tag_stack[count($this->_tag_stack) - 1][1], $_block_content, $this); ';
                $_result .= 'echo $_block_content; array_pop($this->_tag_stack); $_block_content=\'\'; ?>';
            }
            return true;
        }
        else
        {
            return false;
        }
    }

}
