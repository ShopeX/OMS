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
 * @DateTime: 2020/11/24 10:05:23
 * @describe: 费用均摊规则
 * ============================
 */
class financebase_finder_expenses_rule {
    public $addon_cols = 'rule_content';

    public $column_edit = "操作";
    public $column_edit_width = "80";
    /**
     * column_edit
     * @param mixed $row row
     * @return mixed 返回值
     */

    public function column_edit($row) {
        $finder_id = $_GET['_finder']['finder_id'];

        $ret = '<a href="index.php?app=financebase&ctl=admin_expenses_rule&act=setRule&p[0]='.$row['rule_id'].'&finder_id=' . $finder_id . '" target="dialog::{width:550,height:400,resizeable:false,title:\'设置\'}">设置</a>';

        return $ret;
    }

    var $column_platform = '平台设置';
    var $column_platform_width = "500";
    var $column_platform_order = 20;
    function column_platform($row) {

        $oFunc = kernel::single('financebase_func');

        $platform = $oFunc->getShopPlatform();

        $ret = [];

        $row['rule_content'] = $row[$this->col_prefix.'rule_content'];

        $rule_content = $row['rule_content'] ? json_decode($row['rule_content'],1) : array();
        foreach ($platform as $key => $value) {
            if(isset($rule_content[$key]) && $rule_content[$key]) {
                $ret[] = $value;
            }
        }

        return implode(',', $ret);  
    }
}