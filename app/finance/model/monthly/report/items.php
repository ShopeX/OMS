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

class finance_mdl_monthly_report_items extends dbeav_model {
    var $has_export_cnf = true;
    var $export_name = '账期核销列表';

    /**
     * modifier_gap
     * @param mixed $col col
     * @param mixed $list list
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function modifier_gap($col,$list,$row){
        // 构建title内容
        $title = sprintf('sales:%s refund:%s', 
            isset($row['_0_sales_gap']) ? $row['_0_sales_gap'] : '0',
            isset($row['_0_refund_gap']) ? $row['_0_refund_gap'] : '0'
        );
        
        return sprintf('<a href="index.php?app=finance&ctl=monthend_verification&act=base_list&p[0]=%s&finder_id=%s" target="_blank" title="%s">%s</a>',
            $row['id'], $_GET['_finder']['finder_id'], 
            htmlspecialchars($title), 
            $col
        );
    }

    /**
     * modifier_order_bn
     * @param mixed $col col
     * @param mixed $list list
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function modifier_order_bn($col,$list,$row){
        if($this->is_export_data) {
            return $col;
        }
        return sprintf('<a href="index.php?app=finance&ctl=monthend_verification&act=sale_list&p[0]=%s&finder_id=%s" target="_blank">%s</a>',$row['id'],$_GET['_finder']['finder_id'], $col);
    }
}
