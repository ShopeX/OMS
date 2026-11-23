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
 
class base_view_compiler{

    function compile_modifier_default($attrs,$compiler,$bondle_var_only){
        list($string, $default ) = explode(',',$attrs);
        if($default===''){
            $default = '\'\'';
        }
        if($bondle_var_only){
            $compiler->_end_fix_quote($string);
            eval($s='$rst ='.str_replace('$this->bundle_vars','$compiler->bundle_vars',$string).';');
            if($rst){
                return var_export($rst,1);
            }else{
                return $default;
            }
        }else{
            return '((isset('.$string.') && \'\'!=='.$string.')?'.$string.':'.$default.')';
        }
    }
    
    function compile_ecos_logo(){
        return '?>Powered By <a href="http://www.shopex.cn" target="_blank">ECOS</a><?php';
    }

    function compile_math($attrs, &$compiler) {
        if(($attrs['equation'][0]=='\'' || $attrs['equation'][0]=='"') && $attrs['equation'][0]==$attrs['equation'][strlen($attrs['equation'])-1]){
            $equation = $attrs['equation'];
        }else{
            $equation = '"'.$attrs['equation'].'"';
        }
    
        $format = $attrs['format'];
        $assign = $attrs['assign'];
    
        unset($attrs['equation'],$attrs['format'],$attrs['assign']);
    
        foreach($attrs as $k=>$v){
            $re['/([^a-z])'.$k.'([^a-z])/i'] = '$1('.$v.')$2';
        }
        $equation = substr(preg_replace(array_keys($re),array_values($re),$equation),1,-1);
        if($format){
            $equation = 'sprintf('.$format.','.$equation.')';
        }
        if($assign){
            $equation = '$this->_vars['.$assign.']='.$equation;
        }
        return 'echo ('.$equation.');';
    }
}
