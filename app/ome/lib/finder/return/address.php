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

class ome_finder_return_address
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    function __construct($app)
    {
        $this->app = $app;
    }
    
    var $column_edit = "操作";
    var $column_edit_width = "200";
    function column_edit($row)
    {
        if (in_array($row['shop_type'],array('xhs'))) {
            return '<a target="dialog::{width:700,height:400,title:\'编辑\'}" href="index.php?app=ome&ctl=admin_return_address&act=edit&p[0]=' . $row['address_id'] . '&finder_id=' . $_GET['_finder']['finder_id'] . '">编辑</a>  ';
        }
    }
}

?>