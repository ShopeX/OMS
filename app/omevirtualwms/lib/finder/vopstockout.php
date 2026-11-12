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
 * 唯品会出库单模拟回传
 */
class omevirtualwms_finder_vopstockout{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $column_control = '操作';
	var $column_control_width = 100;

    /** vue_mode 下发 _0_<col> 原始值别名（结构化行操作取主键用） */
    public $addon_cols = "stockout_no";
    function column_control($row){
        $flag = "vopstockout";
        if ($this->vue_mode) {
            $stockoutNo = $row['_0_stockout_no'] ?? $row['stockout_no'] ?? '';
            return array(
                array(
                    'key'    => 'start_mock',
                    'label'  => '开始模拟',
                    'action' => 'index.php?app=omevirtualwms&ctl=admin_wms&act=getinfo&p[0]=' . $stockoutNo . '&p[1]=' . $flag,
                ),
            );
        }
        return '<a  href="index.php?app=omevirtualwms&ctl=admin_wms&act=getinfo&p[0]='.$row['stockout_no'].'&p[1]='.$flag.'">开始模拟</a>';
    }
    
    function row_style($row)
    {
        if (in_array($row['stockout_no'],app::get('omevirtualwms')->model('vopstockout')->queue)) {
            return "\" style=\"background-color:#ffe3e7;\"";
        }
    }
}
