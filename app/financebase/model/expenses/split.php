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
 * @DateTime: 2020/11/27 14:50:39
 * @describe: model层
 * ============================
 */
class financebase_mdl_expenses_split extends dbeav_model {
    public $has_export_cnf = true;
    public $export_name = '拆分明细';

    /**
     * _filter
     * @param mixed $filter filter
     * @param mixed $tableAlias tableAlias
     * @param mixed $baseWhere baseWhere
     * @return mixed 返回值
     */

    public function _filter($filter, $tableAlias = NULL, $baseWhere = NULL){
        $where = '';
        if(isset($filter['time_from']) && $filter['time_from']){
            $where .= ' AND `trade_time` >='.strtotime($filter['time_from']);
        }
        unset($filter['time_from']);
        
        if(isset($filter['time_to']) && $filter['time_to']){
            $where .= ' AND `trade_time` <'.(strtotime($filter['time_to'])+86400);
        }
        unset($filter['time_to']);
        if(isset($filter['split_time_from']) && $filter['split_time_from']){
            $where .= ' AND `split_time` >='.strtotime($filter['split_time_from']);
        }
        unset($filter['split_time_from']);
        
        if(isset($filter['split_time_to']) && $filter['split_time_to']){
            $where .= ' AND `split_time` <'.(strtotime($filter['split_time_to'])+86400);
        }
        unset($filter['split_time_to']);
        if($filter['material_bn']) {
            $bm = app::get('material')->model('basic_material')->db_dump(array('material_bn'=>$filter['material_bn']), 'bm_id');
            $filter['bm_id'] = $bm['bm_id'];
            unset($filter['material_bn']);
        }
        if($filter['trade_no']) {
            $bill = app::get('financebase')->model('bill')->getList('id',array('trade_no'=>$filter['trade_no']));
            $filter['bill_id'] = array_column($bill, 'id');
            unset($filter['trade_no']);
        }
        return parent::_filter($filter, $tableAlias, $baseWhere).$where;
    }

    /**
     * 获取BillCategoryCount
     * @param mixed $filter filter
     * @return mixed 返回结果
     */
    public function getBillCategoryCount($filter) {
        if(isset($filter['bill_category'])) {
            if(!$filter['bill_category']) {
                unset($filter['bill_category']);
            }
        }
        $sql = 'select bill_category,sum(money) total_money
                from sdb_financebase_expenses_split
                where '.$this->_filter($filter).'
                group by bill_category';
        $list = $this->db->select($sql);
        return $list;
    }

    /**
     * modifier_porth
     * @param mixed $col col
     * @return mixed 返回值
     */
    public function modifier_porth($col) {
        return $col;
    }

    /**
     * modifier_money
     * @param mixed $col col
     * @return mixed 返回值
     */
    public function modifier_money($col) {
        $oCur = app::get('eccommon')->model('currency');
        $oMath = kernel::single('eccommon_math');
        return $oCur->changer($oMath->getOperationNumber($col));
    }

    /**
     * modifier_split_type
     * @param mixed $col col
     * @return mixed 返回值
     */
    public function modifier_split_type($col) {
        $splitInfo = app::get('financebase')->model('expenses_rule')->getSplitInfo();
        return $col ? $splitInfo['split_type'][$col] : '';
    }

    /**
     * modifier_split_rule
     * @param mixed $col col
     * @return mixed 返回值
     */
    public function modifier_split_rule($col) {
        $splitInfo = app::get('financebase')->model('expenses_rule')->getSplitInfo();
        return $col ? $splitInfo['split_rule'][$col] : '';
    }

    /**
     * 获取PrimaryIdsByCustom
     * @param mixed $filter filter
     * @param mixed $opId ID
     * @return mixed 返回结果
     */
    public function getPrimaryIdsByCustom($filter, $opId) {
        if($filter['id']) {
            if($filter['time_from']) {
                unset($filter['time_from']);
            }
            if($filter['time_to']) {
                unset($filter['time_to']);
            }
        }
        $primary_ids = array();
        $primary_info = $this->getList('id', $filter, 0, -1);
        if($primary_info){
            foreach($primary_info as $info){
                $primary_ids[] = $info['id'];
            }
            $inLogData = array(
                'export_type' => 'items',
                'filter' => json_encode($filter, JSON_UNESCAPED_UNICODE),
                'export_time' => time(),
                'op_id' => $opId,
            );
            app::get('financebase')->model('expenses_export_log')->insert($inLogData);
        }
        return $primary_ids;
    }

    public function gen_id() {
        $prefix = 'SP'.date("ymd");
        $sign   = kernel::single('eccommon_guid')->incId('expenses_split', $prefix, 8);
        return $sign;
    }
}