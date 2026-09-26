<?php
/**
 * ============================
 * @Author:   yaokangming
 * @Version:  1.0 
 * @DateTime: 2020/11/27 16:41:57
 * @describe: model层
 * ============================
 */
class financebase_mdl_expenses_unsplit extends dbeav_model {
    public $has_export_cnf = true;
    public $export_name = '未拆单据';

    var $shop_name = array();

    public function modifier_shop_id($val)
    {
        if (empty($val)) {
            return '';
        }
        if (!isset($this->shop_name[$val])) {
            $row = app::get('ome')->model('shop')->getList('name', array('shop_id' => $val), 0, 1);
            if ($row) {
                $this->shop_name[$val] = $row[0]['name'];
            } else {
                return '';
            }
        }
        return $this->shop_name[$val];
    }

    public function table_name($real=false)
    {
        $table_name = 'bill';
        if($real){
            return DB_PREFIX.'financebase_'.$table_name;
        }else{
            return $table_name;
        }
    }

    /**
     * Finder 高级筛选按此名加载 model，避免 table_name 返回 bill 时误加载 financebase_mdl_bill
     */
    public function object_name()
    {
        return 'expenses_unsplit';
    }

    public function modifier_bill_category($col) {
        return $col ? : '未识别类型';
    }

    public function _filter($filter, $tableAlias = NULL, $baseWhere = NULL){
        $baseWhere[] = ($tableAlias ? $tableAlias . '.' : '') . 'split_status in("2","4")';
        if(isset($filter['time_from']) && $filter['time_from']){
            $baseWhere[] = ' `trade_time` >='.strtotime($filter['time_from']);
        }
        unset($filter['time_from']);

        if(isset($filter['time_to']) && $filter['time_to']){
            $baseWhere[] = ' `trade_time` <'.(strtotime($filter['time_to'])+86400);
        }
        unset($filter['time_to']);

        if(isset($filter['split_bn']) && trim($filter['split_bn']) !== '') {
            $splitBn = addslashes(trim($filter['split_bn']));
            $splitRows = app::get('financebase')->model('expenses_split')->getList('bill_id', array('split_bn'=>$splitBn));
            if($splitRows) {
                $billIds = array_column($splitRows, 'bill_id');
                if(isset($filter['id|in'])) {
                    $billIds = array_intersect((array)$filter['id|in'], $billIds);
                }
                $filter['id|in'] = $billIds ?: array(-1);
            } else {
                $baseWhere[] = " (`trade_no` like '%{$splitBn}%' or `financial_no` like '%{$splitBn}%' or `order_bn` like '%{$splitBn}%')";
            }
            unset($filter['split_bn']);
        }

        if(isset($filter['account_id_minus']) && $filter['account_id_minus'] !== '') {
            $rules = app::get('financebase')->model('expenses_rule')->getList(
                'bill_category',
                array('account_id_minus' => $filter['account_id_minus'])
            );
            if($rules) {
                $categories = array_column($rules, 'bill_category');
                if(isset($filter['bill_category']) && $filter['bill_category'] !== '') {
                    $categories = in_array($filter['bill_category'], $categories) ? array($filter['bill_category']) : array();
                }
                if($categories) {
                    $filter['bill_category|in'] = $categories;
                    unset($filter['bill_category']);
                } else {
                    $filter['id'] = -1;
                }
            } else {
                $filter['id'] = -1;
            }
            unset($filter['account_id_minus']);
        }

        if(isset($filter['split_time_from'])) { 
            if($filter['split_time_from']){
                $baseWhere[] = ' `split_time` >='.strtotime($filter['split_time_from']);
            }
            unset($filter['split_time_from']);
        }
        
        if(isset($filter['split_time_to'])) { 
            if($filter['split_time_to']){
                $baseWhere[] = ' `split_time` <'.(strtotime($filter['split_time_to'])+86400);
            }
            unset($filter['split_time_to']);
        }
        if(isset($filter['split_fail_msg'])) { 
            if($filter['split_fail_msg']){
                $fm = trim($filter['split_fail_msg']);
                $baseWhere[] = ' (`split_msg` like "%'.$fm.'%" or `confirm_fail_msg` like "%'.$fm.'%")';
            }
            unset($filter['split_fail_msg']);
        }
        if(isset($filter['bill_category']) && $filter['bill_category'] == '未识别类型') {
            $filter['bill_category'] = '';
        }
        return parent::_filter($filter, $tableAlias, $baseWhere);
    }

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
}