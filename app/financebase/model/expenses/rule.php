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
 * @DateTime: 2020/11/23 17:21:05
 * @describe: model层
 * ============================
 */
class financebase_mdl_expenses_rule extends dbeav_model {
    private $billCategory;
    private $cainiaoBillCategory;
    private $splitType = array("sku"=>"sku");
    private $splitRule = array(""=>"","unsplit"=>"不拆仅呈现","price"=>"按价值权重","weight"=>"按重量权重","num"=>"按件数权重","volume"=>"按体积权重");
    // 导出配置
    var $has_export_cnf = true;
    var $export_name = '费用拆分规则';
    public function table_name($real=false)
    {
        $table_name = 'bill_category_rules';
        if($real){
            return DB_PREFIX.'financebase_'.$table_name;
        }else{
            return $table_name;
        }
    }

    public function getBillCategory() {
        if(!$this->billCategory) {
            $rows = $this->getList('bill_category,split_type,split_rule');
            $undefined = app::get('financebase')->getConf('expenses.rule.undefined');
            $this->billCategory = array($undefined);
            $this->billCategory = array_merge($this->billCategory, $rows);
        }
        return $this->billCategory;
    }

    public function getCainiaoBillCategory() {
        if(!$this->cainiaoBillCategory) {
            $this->cainiaoBillCategory = $this->getList('bill_category,split_type,split_rule', array('business_type'=>'cainiao'));
        }
        return $this->cainiaoBillCategory;
    }

    public function getSplitInfo() {
        return array(
            'split_type' => $this->splitType,
            'split_rule' => $this->splitRule,
        );
    }

    public function getExpensesList($cols='*', $filter=array(), $offset=0, $limit=-1, $orderType=null) {
        $rows = parent::getList($cols, $filter, $offset, $limit, $orderType);
        if($offset != 0) {
            return $rows;
        }
        $first = app::get('financebase')->getConf('expenses.rule.undefined');
        if(!$first) {
            $first = array(
                'rule_id' => 'undefined',
                'bill_category' => '未识别类型',
            );
            app::get('financebase')->setConf('expenses.rule.undefined', $first);
        }
        array_unshift($rows, $first);
        return $rows;
    }

    public function modifier_split_type($col) {
        return $col ? $this->splitType[$col] : '';
    }

    public function modifier_split_rule($col) {
        return $col ? $this->splitRule[$col] : '';
    }
}
