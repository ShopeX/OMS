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
 
define('XSD_PARSER_ATTR',1);
define('XSD_PARSER_ELE',2);
define('XSD_PARSER_MULTI_ELE',3);
class base_xsdparser{

    var $_depth = array();
    var $_last_entity = array();
    var $_xsd_ns = 'http://www.w3.org/2001/xmlschema';
    static $xsdlib = array();

    function &parse($xsdfile){
        if(!isset(self::$xsdlib[$xsdfile])){
            $this->_buff = array('elements'=>array(),'type'=>array());
            $this->_ns_len = strlen($this->_xsd_ns)+1;
            $this->_parse_xsd_file($xsdfile);
            self::$xsdlib[$xsdfile] = $this->_buff;
        }
        return self::$xsdlib[$xsdfile];
    }

    function _start_element($parser, $name, $attrs)
    {
        $is_entity = false;
        $last_entity = end($this->_last_entity);

        if($this->_ns_len)
            $name = substr($name,$this->_ns_len);

        if($name=='ELEMENT'){
            if(isset($attrs['NAME'])){
                $tagname = $attrs['NAME'];
                $this->_buff['elements'][$attrs['NAME']] = array('_tag'=>$tagname);
                $element = &$this->_buff['elements'][$attrs['NAME']];
                array_push($this->_last_entity,$attrs['NAME']);
                $is_entity = true;
            }elseif(isset($attrs['REF'])){
                $tagname = $attrs['REF'];
                $element = &$this->_buff['elements'][$attrs['REF']];
            }

            if($last_entity){
                $this->_buff['elements'][$last_entity][$tagname] = array(
                        (isset($attrs['MAXOCCURS']) && $attrs['MAXOCCURS']>'1')?XSD_PARSER_MULTI_ELE:XSD_PARSER_ELE
                    );
            }

        }elseif($name=='ATTRIBUTE'){
            $this->_buff['elements'][$last_entity]['_attrs']++;
            $this->_buff['elements'][$last_entity][$attrs['NAME']] = 
                array(XSD_PARSER_ATTR,
                isset($attrs['USE'])?$attrs['USE']:'optional');
        }
        array_push($this->_depth,$is_entity);
    }

    function _end_element($parser, $name)
    {
        if(array_pop($this->_depth)){
            array_pop($this->_last_entity);
        }
    }

    function _parse_xsd_file($xsdfile){
        $xml_parser = xml_parser_create_ns();
        xml_set_element_handler($xml_parser, array(&$this,'_start_element'), array(&$this,'_end_element'));

        $p = strpos($xsdfile,'_');
        if(defined('CUSTOM_CORE_DIR') && file_exists(CUSTOM_CORE_DIR.'/'.($app_id?$app_id:$this->app->app_id).'/view/'.$tmpl_file)){
             $xmldir = CUSTOM_CORE_DIR.'/'.substr($xsdfile,0,$p).'/xmlschema/'.substr($xsdfile,$p+1).'.xsd';
        }else{
             $xmldir = APP_DIR.'/'.substr($xsdfile,0,$p).'/xmlschema/'.substr($xsdfile,$p+1).'.xsd';
        }
        if (!($fp = fopen($xmldir, "r"))) {
            die("could not open XML input:".$xsdfile);
        }

        while ($data = fread($fp, 4096)) {
            if (!xml_parse($xml_parser, $data, feof($fp))) {
                die(sprintf("XML error: %s at line %d",
                    xml_error_string(xml_get_error_code($xml_parser)),
                    xml_get_current_line_number($xml_parser)));
            }
        }
        xml_parser_free($xml_parser);
    }

}
