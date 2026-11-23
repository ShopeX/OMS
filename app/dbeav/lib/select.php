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
 * 实现个简单的select对像
 * 目前只实现于core_db的select
 * 只支持单表 2010/4/23 edwin.lzh@gmail.com
 */

class dbeav_select 
{

    const DISTINCT      = 'distinct';
    const COLUMNS       = 'columns';
    const FROM          = 'from';
    const WHERE         = 'where';
    const GROUP         = 'group';
    const HAVING        = 'having';
    const ORDER         = 'order';
    const LIMIT_COUNT   = 'limit_count';
    const LIMIT_OFFSET  = 'limit_offset';

    const SQL_WILDCARD   = '*';
    const SQL_SELECT     = 'SELECT';
    const SQL_FROM       = 'FROM';
    const SQL_WHERE      = 'WHERE';
    const SQL_DISTINCT   = 'DISTINCT';
    const SQL_GROUP_BY   = 'GROUP BY';
    const SQL_ORDER_BY   = 'ORDER BY';
    const SQL_HAVING     = 'HAVING';
    const SQL_AND        = 'AND';
    const SQL_AS         = 'AS';
    const SQL_OR         = 'OR';
    const SQL_ON         = 'ON';
    const SQL_ASC        = 'ASC';
    const SQL_DESC       = 'DESC';

    protected static $_init_parts = array(
        self::DISTINCT     => false,
        self::COLUMNS      => array(),
        self::FROM         => null,
        self::WHERE        => array(),
        self::GROUP        => array(),
        self::HAVING       => array(),
        self::ORDER        => array(),
        self::LIMIT_COUNT  => null,
        self::LIMIT_OFFSET => null,
    );
    
    private $_parts = array();

    public function set_model(&$model) 
    {
        $this->_model = $model;
        return $this;
    }//End Function

    public function get_model() 
    {
        return $this->_model;
    }//End Function

    public function set_adapter($adapter) 
    {
        $this->_adapter = $adapter;
        return $this;
    }//End Function

    public function get_parts() 
    {
        return $this->_parts;
    }//End Function

    public function reset($part=null) 
    {
        if ($part == null) {
            $this->_parts = self::$_init_parts;
        } else if (array_key_exists($part, self::$_init_parts)) {
            $this->_parts[$part] = self::$_init_parts[$part];
        }
        return $this;
    }//End Function

    public function distinct($flag=true) 
    {
        $this->_parts[self::DISTINCT] = (bool) $flag;
        return $this;
    }//End Function

    public function from($db, $cols=self::SQL_WILDCARD) 
    {
        $this->_parts[self::FROM] = $db;
        $this->columns($cols);
        return $this;
    }//End Function

    public function columns($cols=self::SQL_WILDCARD) 
    {
        $this->reset('columns');
        if (!is_array($cols))   $cols = array($cols);
        foreach($cols AS $alias => $col){
            $this->_parts[self::COLUMNS][] = array($col, is_string($alias) ? $alias : null);
        }
        return $this;
    }//End Function

    public function where($str, $val='') 
    {
        $this->_parts[self::WHERE][] = $this->_where($str, $val, true);
        return $this;
    }//End Function

    public function or_where($str, $val='') 
    {
        $this->_parts[self::WHERE][] = $this->_where($str, $val, false);
        return $this;
    }//End Function

    private function _where($str, $val, $bool) 
    {
        $str = (isset($val)) ? $this->_adapter->quote_into($str, $val) : $str;
        return ($bool) ? self::SQL_AND . ' ' . $str : self::SQL_OR . ' ' . $str;
    }//End Function

    public function group($groups) 
    {
        if(!is_array($groups)) $groups = array($groups);
        foreach($groups AS $group){
            $this->_parts[self::GROUP][] = $group;
        }
        return $this;
    }//End Function

    public function having($str) 
    {
        if(func_num_args() > 1){
            $str = $this->_adapter->quote_into($str, func_get_arg(1));
        }
        if ($this->_parts[self::HAVING]){
            $this->_parts[self::HAVING][] = self::SQL_AND . " " . $str;
        }else{
            $this->_parts[self::HAVING][] = $str;
        }
        return $this;
    }//End Function

    public function or_having($str) 
    {
        if(func_num_args() > 1){
            $str = $this->_adapter->quote_into($str, func_get_arg(1));
        }
        if ($this->_parts[self::HAVING]){
            $this->_parts[self::HAVING][] = self::SQL_OR . " " . $str;
        }else{
            $this->_parts[self::HAVING][] = $str;
        }
        return $this;
    }//End Function

    public function order($orders) 
    {
        if(!is_array($orders))  $orders = array($orders);

        foreach($orders AS $order){
            $direction = self::SQL_ASC;
            if (preg_match('/(.*\W)(' . self::SQL_ASC . '|' . self::SQL_DESC . ')\b/si', $order, $matches)) {
                $val = trim($matches[1]);
                $direction = strtoupper($matches[2]);
            }
            $this->_parts[self::ORDER][] = array($val, $direction);
        }
        return $this;
    }//End Function

    public function limit($offset=null, $count=null) 
    {
        $this->_parts[self::LIMIT_COUNT]  = (int) $count;
        $this->_parts[self::LIMIT_OFFSET] = (int) $offset;
        return $this;
    }//End Function

    public function page($page=null, $size=null) 
    {
        $page = ($page > 0) ? $page : 1;
        $size = ($size > 0) ? $size : 1;
        $this->_parts[self::LIMIT_COUNT]  = (int) $size;
        $this->_parts[self::LIMIT_OFFSET] = (int) $size * ($page - 1);
        return $this;
    }//End Function

    public function instance() 
    {
        $this->_adapter->set_obj($this);
        return $this->_adapter;
    }//End Function

    public function assemble() 
    {
        $sql = self::SQL_SELECT;
        foreach (array_keys(self::$_init_parts) as $part) {
            $part = strtolower($part);
            if($part == 'limit_count')  continue;   //todo：减少判断methon_exists提高性能
            $method = 'render_' . $part;
            //if (method_exists($this, $method)) {
                $sql = $this->$method($sql);
            //}
        }
        return $sql;
    }//End Function

    protected function render_distinct($sql) 
    {
        if ($this->_parts[self::DISTINCT]) {
            $sql .= ' ' . self::SQL_DISTINCT;
        }
        return $sql;
    }//End Function

    protected function render_columns($sql) 
    {
        if(!count($this->_parts[self::COLUMNS])){
            return null;
        }
        $columns = array();
        foreach ($this->_parts[self::COLUMNS] as $columnEntry) {
            list($column, $alias) = $columnEntry;
            $columns[] = $this->_adapter->quote_column_as($column, $alias);
        }
        return $sql .= ' ' . implode(', ', $columns);
    }//End Function

    public function get_columns() 
    {
        $columns = array();
        if(count($this->_parts[self::COLUMNS])){
            foreach ($this->_parts[self::COLUMNS] as $columnEntry) {
                list($column, $alias) = $columnEntry;
                $columns[] = $this->_adapter->quote_column_as($column, $alias);
            }
        }
        return $columns;
    }//End Function

    protected function render_from($sql) 
    {
        if(!empty($this->_parts[self::FROM])){
            $sql .= ' ' . self::SQL_FROM . ' ' . $this->_adapter->quote_identifier($this->_parts[self::FROM]);
        }
        return $sql;
    }//End Function

    protected function render_where($sql) 
    {
        if($this->_parts[self::FROM] && $this->_parts[self::WHERE]){
            $sql .= ' ' . self::SQL_WHERE . ' 1=1 ' .  implode(' ', $this->_parts[self::WHERE]);
        }
        return $sql;
    }//End Function

    protected function render_group($sql) 
    {
        if($this->_parts[self::FROM] && $this->_parts[self::GROUP]){
            $group = array();
            foreach($this->_parts[self::GROUP] as $term){
                $group[] = $this->_adapter->quote_identifier($term);
            }
            $sql .= ' ' . self::SQL_GROUP_BY . ' ' . implode(",\n\t", $group);
        }
        return $sql;
    }//End Function

    protected function render_having($sql) 
    {
        if($this->_parts[self::FROM] && $this->_parts[self::HAVING]){
            $sql .= ' ' . self::SQL_HAVING . ' ' . implode(' ', $this->_parts[self::HAVING]);
        }
        return $sql;
    }//End Function

    protected function render_order($sql) 
    {
        if($this->_parts[self::ORDER]){
            $order = array();
            foreach($this->_parts[self::ORDER] as $term){
                if (is_array($term)) {
                    $order[] = $this->_adapter->quote_identifier($term[0]) . ' ' . $term[1];
                } else {
                    $order[] = $this->_adapter->quote_identifier($term);
                }
            }
            $sql .= ' ' . self::SQL_ORDER_BY . ' ' . implode(', ', $order);
        }
        return $sql;
    }//End Function

    protected function render_limit_offset($sql) 
    {
        $count = 0;
        $offset = 0;
        if(!empty($this->_parts[self::LIMIT_OFFSET])){
            $offset = (int) $this->_parts[self::LIMIT_OFFSET];
            $count = intval(9223372036854775807);       //php最大值
        }
        if(!empty($this->_parts[self::LIMIT_COUNT])){
            $count = (int) $this->_parts[self::LIMIT_COUNT];
        }
        if($count > 0){
            $sql = trim($this->_adapter->limit($sql, $count, $offset));
        }
        return $sql;
    }//End Function

    public function to_sql() 
    {
        return $this->assemble();
    }//End Function

}//End Class
