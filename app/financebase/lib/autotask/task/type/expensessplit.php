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
 * @DateTime: 2020/11/24 15:58:54
 * @describe: 类
 * ============================
 */
class financebase_autotask_task_type_expensessplit extends financebase_autotask_task_init
{
    private $process_time = 300; //程序存活时间
    private $splitTime;

    /**
     * 处理
     * @param mixed $task_info task_info
     * @param mixed $error_msg error_msg
     * @return mixed 返回值
     */

    public function process($task_info,&$error_msg)
    {
        $startTime = time();
        do {
            $billModel = app::get('financebase')->model('bill');
            $row = $billModel->db_dump(array('split_status'=>'0'), '*');
            if(empty($row)) {
                break;
            }
            $rs = $billModel->update(array('split_status'=>'1','confirm_fail_msg'=>'','split_msg'=>''),array('id'=>$row['id'],'split_status'=>'0'));
            if(is_bool($rs)) {
                continue;
            }
            $row['split_status'] = '1';
            $this->dealSplit($row);
            if((time() - $startTime) > $this->process_time) {
                break;
            }
        } while (true);
        return true;
    }

    /**
     * dealSplit
     * @param mixed $bill bill
     * @return mixed 返回值
     */
    public function dealSplit($bill) {
        if($bill['split_status'] != '1') {
            return array(false, '不在处理中');
        }
        $this->splitTime = time();
        $this->dealBusinesstypeAndOld($bill['id']);
        if(empty($bill['bill_category'])) {
            $expensesRule = app::get('financebase')->getConf('expenses.rule.undefined');
        } else {
            $expensesRule = app::get('financebase')->model('expenses_rule')->db_dump(array('bill_category'=>$bill['bill_category']), 'business_type,bill_category,split_type,split_rule');
        }
        $billModel = app::get('financebase')->model('bill');
        if(empty($expensesRule['split_type']) || empty($expensesRule['split_rule'])) {
            $billModel->update(array('split_status'=>'5','split_time'=>$this->splitTime), array('id'=>$bill['id']));
            return array(true, '没有拆分规则');
        }
        if($expensesRule['split_rule'] == 'unsplit') {
            $billModel->update(array('split_status'=>'2','split_time'=>$this->splitTime), array('id'=>$bill['id']));
            return array(true, '不拆仅呈现');
        }
        list($skuList, $msg) = $this->getSkuList($bill, $expensesRule['business_type']);
        if(empty($skuList) || empty($skuList['sku'])) {
            $billModel->update(array('split_status'=>'4','split_time'=>$this->splitTime,'split_msg'=>$msg), array('id'=>$bill['id']));
            return array(false, '拆分失败:'.$msg);
        }
        $skuList['bill_category'] = $expensesRule['bill_category'];
        $splitClassName = 'financebase_expenses_'.$expensesRule['split_type'].'_'.$expensesRule['split_rule'];
        try {
            $splitClass = kernel::single($splitClassName);
            list($rs, $msg) = $splitClass->process($skuList, $this->splitTime);
        } catch (Exception $e) {
            list($rs, $msg) = array(false, '不存在拆分规则:'.$expensesRule['split_type'].'_'.$expensesRule['split_rule']);
        }
        if(!$rs) {
            $billModel->update(array('split_status'=>'4','split_time'=>$this->splitTime,'split_msg'=>$msg), array('id'=>$bill['id']));
            return array(false, '拆分失败:'.$msg);
        }
        $billModel->update(array('split_status'=>'3','split_time'=>$this->splitTime), array('id'=>$bill['id']));
        return array(true, '拆分成功');
    }

    /**
     * 获取拆分的明细
     * @param  array $bill 账单数据
     * @return array       array(
     *         'bill' => $bill,
     *         'parent_id' => '',
     *         'parent_type' => '', #bill/bill_import_summary
     *         'sku' => array(array(
     *             'bm_id' => '',
     *             'nums' => '',
     *             'divide_order_fee' => '',
     *         ))
     * )
     */
    public function getSkuList($bill, $expensesBusiness = '') {
        $skuList = array();
        $msg = '未找到拆分明细';
        switch ($expensesBusiness) {
            case 'cainiao':
                list($skuList, $msg) = kernel::single('financebase_data_bill_businesstype_cainiao')->getSkuList($bill);
                break;
            
            default:
                list($skuList, $msg) = $this->getSkuListFromOrderBn($bill);
                break;
        }
        return array($skuList, $msg);
    }

    private function getSkuListFromOrderBn($bill) {
        $orderBn = $bill['order_bn'];
        if(!$orderBn) {
            return array(array(), '订单号未找到');
        }
        $appName = 'ome';
        $order = app::get('ome')->model('orders')->db_dump(array('order_bn'=>$orderBn,'shop_id'=>$bill['shop_id']), 'order_id');
        if(empty($order)) {
            $appName = 'archive';
            $order = app::get('archive')->model('orders')->db_dump(array('order_bn'=>$orderBn,'shop_id'=>$bill['shop_id']), 'order_id');
        }
        if(!$order) {
            return array(array(), '对应的订单系统中不存在');
        }
        $skuList = array(
            'bill' => $bill,
            'parent_id' => $bill['id'],
            'parent_type' => 'bill',
            'sku' => array()
        );
        $items = app::get($appName)->model('order_items')->getList('product_id,nums,divide_order_fee',array('order_id'=>$order['order_id'], 'delete'=>'false'));
        foreach ($items as $v) {
            if($skuList['sku'][$v['product_id']]) {
                $skuList['sku'][$v['product_id']]['nums'] += $v['nums'];
                $skuList['sku'][$v['product_id']]['divide_order_fee'] += $v['divide_order_fee'];
            } else {
                $v['bm_id'] = $v['product_id'];
                $skuList['sku'][$v['product_id']] = $v;
            }
        }
        return array($skuList, '获取拆分的明细成功');
    }

    /**
     * dealBusinesstypeAndOld
     * @param mixed $billId ID
     * @return mixed 返回值
     */
    public function dealBusinesstypeAndOld($billId) {
        $expensesSplitModel = app::get('financebase')->model('expenses_split');
        $olds = $expensesSplitModel->getList('*',['bill_id'=>$billId,'split_status'=>'0']);
        if(!$olds) {
            return;
        }
        $expensesSplit = [];
        $oldIds = [];
        foreach ($olds as $v) {
            $oldIds[] = $v['id'];
            $v['split_bn'] = $expensesSplitModel->gen_id();
            $v['split_status'] = '2';
            $v['split_time'] = $this->splitTime;
            $v['money'] = -$v['money'];
            // 贷方和借方税金都需要红冲（取反）
            if (isset($v['tax_amount_plus'])) {
                $v['tax_amount_plus'] = -$v['tax_amount_plus'];
            }
            if (isset($v['tax_amount_minus'])) {
                $v['tax_amount_minus'] = -$v['tax_amount_minus'];
            }
            unset($v['id']);
            $expensesSplit[] = $v;
        }
        $expensesSplitModel->update(['split_status'=>'1'],['id'=>$oldIds]);
        $sql = ome_func::get_insert_sql($expensesSplitModel, $expensesSplit);
        $expensesSplitModel->db->exec($sql);
    }
}