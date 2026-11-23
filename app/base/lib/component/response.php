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
 

/*
 * @package component
 * @author  edwin.lzh@gmail.com 2010/4/12
 */

class base_component_response 
{
    
    /*
     * headers
     * @val array
     */
    protected $_headers = array();

    /*
     * raw headers
     * @val array
     */
    protected $_raw_headers = array();

    /*
     * response code
     * @val int
     */
    protected $_http_response_code = 200;

    /*
     * is redirect
     * @val boolean
     */
    protected $_is_redirect = false;

    /*
     * bodys
     * @val boolean
     */
    protected $_bodys = array();

    /*
     * normalize header by name
     * @param string $name
     * @return string
     */
    private function normalize_header($name) 
    {
        $filtered = str_replace(array('-', '_'), ' ', (string) $name);
        $filtered = ucwords(strtolower($filtered));
        $filtered = str_replace(' ', '-', $filtered);
        return $filtered;
    }//End Function

    /*
     * set header
     * @param string $name
     * @param mixed $value
     * @param boolean $replace
     * @return self
     */
    public function set_header($name, $value, $replace=false) 
    {
        $val = $this->normalize_header($value);

        if ($replace) {
            foreach ($this->_headers as $key => $header) {
                if ($name == $header['name']) {
                    unset($this->_headers[$key]);
                }
            }
        }

        $this->_headers[] = array(
            'name'    => $name,
            'value'   => $value,
            'replace' => $replace
        );
        return $this;
    }//End Function

    /*
     * get header
     * @param string $name
     * @param array &$header
     * @return boolean
     */
    public function get_header($name, &$header) 
    {
        foreach($this->_headers AS $row){
            if(strtolower($row['name']) == strtolower($name)){
                $header = $row;
                return true;
            }
        }
        return false;
    }//End Function

    /*
     * get all headers
     * @return array
     */
    public function get_headers() 
    {
        return $this->_headers;
    }//End Function

    /*
     * clean all headers
     * @return self
     */
    public function clean_headers() 
    {
        $this->_headers = array();
        return $this;
    }//End Function
    
    /*
     * set redirect
     * @param string $url
     * @param string $code
     * @return self
     */
    public function set_redirect($url, $code = 302) 
    {
        $this->set_header("Location", $url, true)
            ->set_http_response_code($code);
        return $this;
    }//End Function

    /*
     * set response code
     * @param string $code
     * @return self
     */
    public function set_http_response_code($code) 
    {
        $this->_is_redirect = ($code >=300 && $code <= 307) ? true : false;
        $this->_http_response_code = $code;
        return $this;
    }//End Function

    /*
     * get http response code
     * @return string
     */
    public function get_http_response_code() 
    {
        return $this->_http_response_code;
    }//End Function
    
    /*
     * is redirect
     * @return boolean
     */
    public function is_redirect() 
    {
        return $this->_is_redirect;
    }//End Function

    /*
     * set raw header
     * @param string $value
     * @return self
     */
    public function set_raw_header($value) 
    {
        if(substr($value, 0, 8) == 'Location'){
            $this->_is_redirect = true;
        }
        $this->_raw_headers[] = $value;
        return $this;
    }//End Function

    /*
     * get raw header
     * @return array
     */
    public function get_raw_headers() 
    {
        return $this->_raw_headers;
    }//End Function

    /*
     * clean raw header
     * @return self
     */
    public function clean_raw_headers() 
    {
        $this->_raw_headers = array();
        return $this;
    }//End Function

    /*
     * claen header & raw header
     * @return self
     */
    public function clean_all_headers() 
    {
        $this->clean_headers()->clean_raw_headers();
        return $this;
    }//End Function
    
    /*
     * send headers
     * @return self
     */
    public function send_headers() 
    {
        if(!count($this->_raw_headers) && !count($this->_headers) && (200 == $this->_http_response_code)){
            return $this;
        }

        $http_code_sent = false;

        foreach($this->_raw_headers AS $header){
            if($this->_http_response_code && !$http_code_sent){
                header($header, true, $this->_http_response_code);
                $http_code_sent = true;
          }else{
                header($header);
            }
        }

        foreach($this->_headers AS $header){
            if($this->_http_response_code && !$http_code_sent){
                header($header['name'] . ': ' . $header['value'], $header['replace'], $this->_http_response_code);
                $http_code_sent = true;
            }else{
                header($header['name'] . ': ' . $header['value'], $header['replace']);
            }
        }

        if (!$http_code_sent) {
            header('HTTP/1.1 ' . $this->_http_response_code);
            $http_code_sent = true;
        }

        return $this;
    }//End Function

    public function set_body($body) 
    {
        $this->_bodys[] = $body;
        return $this;
    }//End Function

    public function get_bodys() 
    {
        return $this->_bodys;
    }//End Function

    public function clean_bodys() 
    {
        $this->_bodys = array();
        return $this;
    }//End Function

    public function send_bodys() 
    {
        echo join('\n', $this->_bodys);
    }//End Function
}//End Class
