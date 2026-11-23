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
 

abstract class dbeav_select_adapter
{
    protected $_connect = null;
    
    abstract public function connect();

    abstract public function limit($sql, $count, $offset=0);

    abstract public function quote_column_as($column, $alias);

    abstract public function quote_identifier($name);

    abstract public function fetch($fetchMode=null);

    abstract public function fetch_all();

    abstract public function fetch_row();

    abstract public function fetch_one();

    abstract public function fetch_col();

    function __contruct() 
    {

    }//End Function

    public function query($selectObj){        
        $sql = $selectObj->assemble();
        $res = $this->_connect->query($sql);
        return $res;
    }

    public function quote($name) 
    {
        if (is_int($value)) {
            return $value;
        } elseif (is_float($value)) {
            return sprintf('%F', $value);
        }
        return "'" . addcslashes($value, "\000\n\r\\'\"\032") . "'";
    }//End Function

}//End Class
