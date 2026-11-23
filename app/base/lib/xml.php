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
 
class base_xml{

    var $_xsd_parser = null;
    var $_path = null;

    function array2xml(&$value,$name,$xsd=null){
        if($this->init_xsd($xsd)){
            return $this->_array2xml_with_xsd($value,$name);
        }else{
            trigger_error('No xsd',E_ERROR);
        }
    }

    function _array2xml_with_xsd(&$value,$name){
        $body = '';
        $attrs = array();
        if(is_array($value)){
            foreach($value as $k=>$v){
                if(isset($this->_schema['elements'][$name][$k])){
                    switch($this->_schema['elements'][$name][$k][0]){
                    case XSD_PARSER_ATTR:
                        $attrs[$k] = ' '.$k.'="'.htmlspecialchars($v).'"';
                        break;
                    case XSD_PARSER_MULTI_ELE:
                        if(is_numeric(key($v))){ //兼容数组的传输方式
                            foreach($v as $j){
                                $body.=$this->_array2xml_with_xsd($j,$k);
                            }
                            break; //如果不是以序列数组形式存放，则认为是其中一个条目
                        }
                    case XSD_PARSER_ELE:
                        $body.=$this->_array2xml_with_xsd($v,$k);
                        break;
                    }
                }
            }
        }else{
            $body = htmlspecialchars($value);
        }
        return '<'.$name.implode('',$attrs).'>'.$body.'</'.$name.'>';
    }

    function xml2array(&$xmldata,$xsd=null){
        $this->init_xsd($xsd);

        $parser = xml_parser_create();

        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
        xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
        xml_parse_into_struct($parser, $xmldata, $tags);
        xml_parser_free($parser);

        $elements = array();
        $stack = array();
        foreach ($tags as $i=>$tag) {
            if ($tag['type'] == "complete" || $tag['type'] == "open") {
                $elements[$i] = array('tag'=>$tag['tag'],'element'=>isset($tag['attributes'])?
                    $tag['attributes']:null);
                if(isset($tag['value'])){
                    if($this->_schema['elements'][$tag['tag']]['_attrs']){ //有属性
                        $elements[$i]['element']['value'] = $tag['value'];
                    }else{
                        $elements[$i]['element'] = $tag['value'];
                    }
                }

                if($stack){
                    $last = count($stack)-1;
                    $childdef = $this->_schema['elements'][$elements[$stack[$last]]['tag']][$tag['tag']];
                    if($childdef[0]==XSD_PARSER_ELE){
                        $elements[$stack[$last]]['element'][$tag['tag']] =
                            &$elements[$i]['element'];
                    }elseif($childdef[0]==XSD_PARSER_MULTI_ELE){
                        $elements[$stack[$last]]['element'][$tag['tag']][] =
                            &$elements[$i]['element'];
                    }
                }

                if($tag['type']=='open'){
                    $stack[] = $i;
                }
            }
            if ($tag['type'] == "close") {
                array_pop($stack);
            }
        }

        return $elements[0]['element']; 
    }

    function init_xsd($xsdfiles){
        if($xsdfiles){
            if(!$this->_xsd_parser){
                $this->_xsd_parser = new base_xsdparser;
            }
            return $this->_schema = $this->_xsd_parser->parse($xsdfiles);
        }else{
            return $this->_schema = null;
        }
    }

}
