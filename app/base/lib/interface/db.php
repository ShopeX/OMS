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

interface base_interface_db
{
    public function exec($sql, $skipModifiedMark=false, $db_lnk=null);

    public function select($sql, $skipModifiedMark=false);

    public function selectrow($sql);

    public function selectlimit($sql, $limit=10, $offset=0);

    public function getRows($rs, $row=10);

    public function count($sql);

    public function quote($string);

    public function lastinsertid();

    public function affect_row();

    public function errorinfo();

    public function errorcode();

    public function beginTransaction();

    public function commit($status=true);

    public function rollBack();

}
