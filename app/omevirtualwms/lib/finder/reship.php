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

class omevirtualwms_finder_reship{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


    
    var $column_control = '操作';
    var $column_control_width = 100;

    /** vue_mode 下发 _0_<col> 原始值别名（结构化行操作取主键用） */
    public $addon_cols = "reship_bn";
    function column_control($row){
    	$flag = "reship";
        if ($this->vue_mode) {
            $reshipBn = $row['_0_reship_bn'] ?? $row['reship_bn'] ?? '';
            return array(
                array(
                    'key'    => 'start_mock',
                    'label'  => '开始模拟',
                    'action' => 'index.php?app=omevirtualwms&ctl=admin_wms&act=getinfo&p[0]=' . $reshipBn . '&p[1]=' . $flag,
                ),
            );
        }
        return '<a href="index.php?app=omevirtualwms&ctl=admin_wms&act=getinfo&p[0]='.$row['reship_bn'].'&p[1]='.$flag.'">开始模拟</a>';
    }

    function row_style($row)
    {
        if (in_array($row['reship_bn'],app::get('omevirtualwms')->model('reship')->queue)) {
            return "\" style=\"background-color:#ffe3e7;\"";
        }
    }

    var $column_msg = '备注';
    var $column_msg_width = 400;
    var $column_msg_order = 200;
    function column_msg($row)
    {
        if (in_array($row['reship_bn'],app::get('omevirtualwms')->model('reship')->queue)) {
            return app::get('omevirtualwms')->_(TIP_INFO);
        }
    }

}
