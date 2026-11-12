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


class invoice_finder_order_setting
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

	public $addon_cols = 'title,tax_rate,shop_id';//调用字段
	
	/*------------------------------------------------------ */
    //-- 编辑
    /*------------------------------------------------------ */
	var $column_edit  = '编辑';
    var $column_edit_order = 5;
    var $column_edit_width = '60';
    function column_edit($row)
    {
        if ($this->vue_mode) {
            return [
                ['key' => 'edit', 'label' => '编辑', 'action' => 'edit'],
            ];
        }

        $finder_id = $_GET['_finder']['finder_id'];
        $sid = $row['sid'];

        $button = "<a href='index.php?app=invoice&ctl=admin_order_setting&act=editor&p[0]={$sid}&finder_id={$finder_id}' target='dialog::{width:620,height:585,title:\"开票信息配置编辑\"}'>编辑</a>";

        return $button;
    }
}