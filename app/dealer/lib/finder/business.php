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
 * ============================
 * @Author:   yaokangming
 * @Version:  1.0
 * @DateTime: 2020/9/4 16:22:23
 * @describe: 经销商
 * ============================
 */
class dealer_finder_business {
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


    public $addon_cols = "bs_id";
    public $column_edit = "操作";
    public $column_edit_width = 120;
    public $column_edit_order = 1;
    /**
     * column_edit
     * @param mixed $row row
     * @return mixed 返回值
     */

    public function column_edit($row){
        $finder_id = $_GET['_finder']['finder_id'];
        $bs_id = $row[$this->col_prefix.'bs_id'];
        $button = '<a href="index.php?app=dealer&ctl=admin_business&act=edit&p[0]='.$bs_id.'&finder_id='.$finder_id.'" target="_blank">编辑</a>';
        return $button;
    }
}