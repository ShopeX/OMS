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
 

class dbeav_select_mysql extends dbeav_select_adapter 
{
    function __contruct($params=null) 
    {
        parent::__contruct();
    }//End Function

    public function connect() 
    {
        if($this->_connect == null){
            $this->_connect = kernel::database();
        }
    }//End Function

    public function set_obj($selectObj) 
    {
        $this->selectObj = $selectObj;
    }//End Function

    public function exec() 
    {
        $this->connect();
        $sql = $this->selectObj->assemble();
        $res = $this->_connect->exec($sql);
        return $res['rs'];
    }//End Function

    public function select() 
    {
        $this->connect();
        $sql = $this->selectObj->assemble();
        $data = $this->_connect->select($sql);
        $cols = $this->selectObj->get_columns();
        $this->selectObj->get_model()->tidy_data($data, (count($cols))?join(',', $cols):'*');
        return $data;
    }//End Function
    
    public function limit($sql, $count, $offset=0) 
    {
        return sprintf('%s LIMIT %s %s', $sql, ($offset>0) ? $offset . ',' : '', intval($count));
    }//End Function

    public function quote_column_as($column, $alias)
    {
        if($column == '*')  return $column;
        if($alias != null)
            return sprintf('%s AS %s', $column, $this->quote_identifier($alias));
        else
            return  $column;
    }//End Function
    
    public function quote_identifier($name) 
    {
        return sprintf('`%s`', $name);
    }//End Function

    public function quote_into($str, $val) 
    {
        return str_replace('?', $this->quote($val), $str);
    }//End Function

    public function quote($val) 
    {
        if (is_int($val)) {
            return $val;
        } elseif (is_float($val)) {
            return sprintf('%F', $val);
        }
        return "'" . addcslashes($val, "\000\n\r\\'\"\032") . "'";
    }//End Function

    public function fetch($fetchMode=null) 
    {
        if($fetchMode == null) $fetchMode = 'all';
        $method = 'fetch_' . strtolower($fetchMode);
        if(method_exists($this, $method)){
            if(func_num_args() > 1){
                $args = func_get_args();
                unset($args[0]);
                return call_user_func_array(array($this, $method), $args);
            }else{
                return $this->$method();
            }
        }else{
            return false;
        }
    }//End Function

    public function fetch_all() 
    {
        return $this->select();
    }//End Function

    public function fetch_row() 
    {
        $data = $this->fetch_all();
        return $data[0];
    }//End Function

    public function fetch_one() 
    {
        $data = $this->fetch_row();
        if(is_array($data)){
            foreach($data AS $d)
                return $d;
        }
        return false;
    }//End Function
    
    public function fetch_col() 
    {
        $res = $this->select();
        $cols = func_get_args();
        foreach($res AS $row){
            if(func_num_args() > 0){
                foreach($row AS $key=>$val){
                    if(in_array($key, $cols)){
                        $data[$key][] = $val;
                    }
                }
            }else{
                foreach($row AS $key=>$val){
                    $data[$key][]= $val;
                }
            }
        }
        return (array) $data;
    }//End Function

}//End Class
