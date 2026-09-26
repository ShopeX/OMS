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

class financebase_finder_base{

	var $detail_basic = "原始数据";
    
    function detail_basic($id){

        $render = app::get('financebase')->render();
        $mdlBill = app::get('financebase')->model("bill");
        $mdlBillBase = app::get('financebase')->model("bill_base");

        $bill_row = $mdlBill->getList('unique_id,shop_id,platform_type',array('id'=>$id));
        $base_bill_row = $mdlBillBase->getList('content',array('shop_id'=>$bill_row[0]['shop_id'],'unique_id'=>$bill_row[0]['unique_id']));

        $class_name = 'financebase_data_bill_'.$bill_row[0]['platform_type'];
        if (!ome_func::class_exists($class_name)){
        	die($bill_row[0]['platform_type'].'无此类型的方法');
        }

        $instance = kernel::single($class_name);

        $array_title = $instance->getTitle();

        $array_content = json_decode($base_bill_row[0]['content'],1);

        $info = array();

        foreach ($array_title as $key => $value) {
        	$info[$key] = array('title'=>$value,'content'=>$array_content[$key]);
        }

 		$render->pagedata['info'] = $info;

        return $render->fetch("admin/bill/detail.html");
    }

    public $detail_expenses = '费用拆分';
    /**
     * detail_expenses
     * @param mixed $id ID
     * @return mixed 返回值
     */
    public function detail_expenses($id) {
        $render = app::get('financebase')->render();
        $rows = app::get('financebase')->model('expenses_split')->getList('*', array('bill_id'=>$id));
        foreach ($rows as $k => $v) {
            $bm = app::get('material')->model('basic_material')->db_dump(array('bm_id'=>$v['bm_id']), 'material_bn,material_name');
            $rows[$k]['material_bn'] = $bm['material_bn'];
            $rows[$k]['material_name'] = $bm['material_name'];
            $rows[$k]['split_status'] = $v['split_status'] == '2' ? '红冲项' : ($v['split_status'] == '1' ? "调整项" : '拆分项');
        }
        $render->pagedata['expenses'] = $rows;
        return $render->fetch("admin/bill/expenses_split.html");
    }

    var $column_edit = "操作";
    var $column_edit_width = "80";
    function column_edit($row) {
        $finder_id = $_GET['_finder']['finder_id'];

        $ret = '<a href="index.php?app=financebase&ctl=admin_shop_settlement_bill&act=edit&p[0]='.$row['id'].'&finder_id=' . $finder_id . '" target="dialog::{width:690,height:200,title:\'编辑\'}">编辑</a>';

        return $ret;
    }

    public $column_goods_name = '商品名称';
    public $column_goods_name_width = '150';
    public $column_goods_name_order = 36;
    public function column_goods_name($row, $list)
    {
        $content = $this->_getBillBaseContent($row, $list);
        return isset($content['goods_name']) ? $content['goods_name'] : '';
    }

    public $column_trade_desc = '业务描述';
    public $column_trade_desc_width = '150';
    public $column_trade_desc_order = 37;
    public function column_trade_desc($row, $list)
    {
        $content = $this->_getBillBaseContent($row, $list);
        return $this->_resolveTradeDesc($content);
    }

    /**
     * 批量加载 bill_base 原始 JSON，避免列表逐行查询
     */
    protected function _getBillBaseContent($row, $list)
    {
        static $cache = null;
        if ($cache === null) {
            $cache = array();
            $billIds = array();
            foreach ($list as $listRow) {
                if (!empty($listRow['id'])) {
                    $billIds[] = $listRow['id'];
                }
            }
            if ($billIds) {
                $mdlBill = app::get('financebase')->model('bill');
                $mdlBillBase = app::get('financebase')->model('bill_base');
                $bills = $mdlBill->getList(
                    'id,shop_id,unique_id',
                    array('id|in' => array_unique($billIds))
                );
                $shopUniqueMap = array();
                $idKeyMap = array();
                foreach ($bills as $billRow) {
                    if (empty($billRow['shop_id']) || empty($billRow['unique_id'])) {
                        continue;
                    }
                    $shopUniqueMap[$billRow['shop_id']][] = $billRow['unique_id'];
                    $idKeyMap[$billRow['id']] = $billRow['shop_id'] . ':' . $billRow['unique_id'];
                }
                foreach ($shopUniqueMap as $shopId => $uniqueIds) {
                    $rows = $mdlBillBase->getList(
                        'shop_id,unique_id,content',
                        array('shop_id' => $shopId, 'unique_id|in' => array_unique($uniqueIds))
                    );
                    foreach ($rows as $baseRow) {
                        $cacheKey = $baseRow['shop_id'] . ':' . $baseRow['unique_id'];
                        $cache[$cacheKey] = json_decode($baseRow['content'], true) ?: array();
                    }
                }
                foreach ($idKeyMap as $billId => $cacheKey) {
                    $cache['id:' . $billId] = isset($cache[$cacheKey]) ? $cache[$cacheKey] : array();
                }
            }
        }

        $billId = isset($row['id']) ? $row['id'] : '';
        return ($billId && isset($cache['id:' . $billId])) ? $cache['id:' . $billId] : array();
    }

    /**
     * 业务描述字段兼容各平台命名差异
     */
    protected function _resolveTradeDesc($content)
    {
        if (empty($content)) {
            return '';
        }
        foreach (array('trade_desc', 'trade_describe') as $field) {
            if (!empty($content[$field]) && $content[$field] !== '-') {
                return $content[$field];
            }
        }
        return '';
    }

}

