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
class  financebase_mdl_base extends dbeav_model{


    var $has_export_cnf = true;
    var $defaultOrder = array('id DESC');
    public $filter_use_like = true;
    public $export_name = '店铺收支明细';




    public function table_name($real=false){
        $tableName = 'bill';
        return $real ? kernel::database()->prefix.'financebase_'.$tableName : $tableName;

    }

    public function searchOptions(){
        return array();
    }

    public function modifier_shop_id($val)
    {
        if(!isset($this->shop_name[$val])){
            $row = app::get('ome')->model('shop')->getList('name',array('shop_id'=>$val),0,1);
            if($row){
                $this->shop_name[$val] = $row[0]['name'];
            }else{
                return '';
            }
            
        }
        return $this->shop_name[$val];
    }

    public function modifier_bill_category($col) {
        return $col ? : '未识别类型';
    }

    public function modifier_ar_verify_status($val) {
        $status_map = array(
            '1' => '已核对AR',
            '2' => '需核对AR',
            '3' => '无需核对AR'
        );
        return isset($status_map[$val]) ? $status_map[$val] : '未知状态';
    }

    public function modifier_disabled($val) {
        return $val === 'true' ? '失败' : '成功';
    }

    public function _filter($filter, $tableAlias = NULL, $baseWhere = NULL){
        if(isset($filter['time_from']) && $filter['time_from']){
            $where .= ' AND `trade_time` >='.strtotime($filter['time_from']);
        }
        unset($filter['time_from']);
        
        if(isset($filter['time_to']) && $filter['time_to']){
            $where .= ' AND `trade_time` <'.(strtotime($filter['time_to'])+86400);
        }
        unset($filter['time_to']);

        if(isset($filter['shop_id']) && $filter['shop_id'] = array_filter((array)$filter['shop_id'])){
            $where .= ' AND `shop_id` in (\''.implode("','", $filter['shop_id'])."') ";
        }
        unset($filter['shop_id']);

        if(isset($filter['bill_status']))
        {
            $filter['bill_status'] == 'succ' and $filter['disabled'] = 'false';
            $filter['bill_status'] == 'fail' and $filter['disabled'] = 'true';
            unset($filter['bill_status']);
        }
        if(isset($filter['bill_category'])) {
            if($filter['bill_category'] == 'all') {
                unset($filter['bill_category']);
            }
            $undefined = app::get('financebase')->getConf('expenses.rule.undefined');
            if ($filter['bill_category'] == $undefined['bill_category']) {
                $filter['bill_category'] = "";
            }
        }

        if(isset($filter['search_value']) and $filter['search_value'])
        {
            $filter[$filter['search_key']] = $filter['search_value'];
            unset($filter['search_key'],$filter['search_value']);
        }

        if($filter['money_type']) {
            if($filter['money_type'] == 'positive') {
                $filter['money|than'] = 0;
            }
            if($filter['money_type'] == 'negative') {
                $filter['money|lthan'] = 0;
            }
            unset($filter['money_type']);
        }
    
        if(isset($filter['trade_type']) and $filter['trade_type'])
        {
            $where .= " AND `trade_type` = '".$filter['trade_type']."'";
        }
        unset($filter['trade_type']);
    
        return parent::_filter($filter, $tableAlias, $baseWhere).$where;
    }

    public function getTotal($filter) {
        $sql = 'select sum(case when money>0 then money end) total_positive,
                    sum(case when money<0 then money end) total_negative
                from sdb_financebase_bill
                where '.$this->_filter($filter);
        return $this->db->selectrow($sql);
    }

}
