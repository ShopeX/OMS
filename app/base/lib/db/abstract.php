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
 
class base_db_abstract 
{
    public $prefix = 'sdb_';
    public static $mysql_query_executions = 0;

    function __construct(){
        $this->prefix = DB_PREFIX;
    }

    public function query($sql , $skipModifiedMark = false,$db_lnk=null){
        $rs = $this->exec($sql,$skipModifiedMark,$db_lnk);
        return $rs;
    }

    public function selectPager($queryString,$pageStart=null,$pageLimit=null) {
        $_data['total'] = $this->count($queryString);
        $_data['page'] = ceil($_data['total']/$pageLimit);
        if($pageLimit==null) {
            $_data = $this->select($queryString);
        } else {
            $_data['data'] = $this->selectLimit($queryString, $pageLimit, $pageStart*$pageLimit);
        }
        return $_data;
    }
}//End Class