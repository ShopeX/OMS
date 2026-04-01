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
 * @DateTime: 2020/11/25 11:09:47
 * @describe: 类
 * ============================
 */
abstract class financebase_expenses_sku_abstract {

    protected $type; //拆分维度

    protected $rule; //拆分规则

    protected $failMsg = '未获取到价值项'; //失败原因

    protected $originalSkuId; //源skuID

    protected $originalSkuCombinationItems; //礼盒详情

    protected function _dealCombinationItems(&$skuList) {
        $this->originalSkuId = array();
        $this->originalSkuCombinationItems = array();
        foreach ($skuList['sku'] as $v) {
            $this->originalSkuId[] = $v['bm_id'];
        }
        $rows = app::get('material')->model('basic_material_combination_items')
                    ->getList('pbm_id,bm_id,material_num',array('pbm_id'=>$this->originalSkuId));
        if(empty($rows)) {
            return;
        }
        $ciBmids = array();
        foreach ($rows as $v) {
            $ciBmids[$v['bm_id']] = $v['bm_id'];
            $item = array('bm_id'=>$v['bm_id'], 'nums'=>$v['material_num']);
            if($this->originalSkuCombinationItems[$v['pbm_id']]) {
                $this->originalSkuCombinationItems[$v['pbm_id']]['items'][] = $item;
            } else {
                $this->originalSkuCombinationItems[$v['pbm_id']] = array(
                    'items' => [$item],
                    'porth' => 'nums'
                );
            }
        }
        $bmRows = app::get('material')->model('basic_material_ext')
                    ->getList('bm_id, cost', array('bm_id'=>$ciBmids));
        $retailPrice = array();
        foreach ($bmRows as $v) {
            $retailPrice[$v['bm_id']] = $v['cost'];
        }
        #将礼盒sku转换成基础sku
        $scItems = array();
        foreach ($skuList['sku'] as $k => $v) {
            if($this->originalSkuCombinationItems[$v['bm_id']]) {
                unset($skuList['sku'][$k]);
                foreach ($this->originalSkuCombinationItems[$v['bm_id']]['items'] as $ik => $iv) {
                    $this->originalSkuCombinationItems[$v['bm_id']]['part_total'] = $v['divide_order_fee'];
                    if($retailPrice[$iv['bm_id']] > 0) {
                        $this->originalSkuCombinationItems[$v['bm_id']]['porth'] = 'retail_amount';
                        $this->originalSkuCombinationItems[$v['bm_id']]['items'][$ik]['nums'] = $iv['nums'] * $v['nums'];
                        $this->originalSkuCombinationItems[$v['bm_id']]['items'][$ik]['retail_amount'] = $iv['nums'] * $retailPrice[$iv['bm_id']] * $v['nums'];
                    }
                }
            }
        }
        foreach ($this->originalSkuCombinationItems as $v) {
            $options = array (
                'part_total'  => $v['part_total'],
                'part_field'  => 'money',
                'porth_field' => $v['porth'],
            );
            $items = kernel::single('ome_order')->calculate_part_porth($v['items'], $options);
            foreach ($items as $iv) {
                $skuList['sku'][$iv['bm_id']]['bm_id'] = $iv['bm_id'];
                $skuList['sku'][$iv['bm_id']]['nums'] += $iv['nums'];
                $skuList['sku'][$iv['bm_id']]['divide_order_fee'] += $iv['money'];
            }
        }
    }

    abstract protected function _getPorthValue($skuList);

    /**
     * [process description]
     * @param  array $skuList array(
     *         'bill' => $bill,
     *         'ids' => '',
     *         'parent_id' => '',
     *         'parent_type' => '', #bill/bill_import_order
     *         'sku' => array(array(
     *             'bm_id' => '',
     *             'nums' => '',
     *             'divide_order_fee' => '',
     *         ))
     * @return array          [description]
     */

    public function process($skuList, $time = 0) {
        $className = get_class($this);
        $className = explode('_', $className);
        $this->type = $className[2];
        $this->rule = $className[3];
        $expensesSplitModel = app::get('financebase')->model('expenses_split');
        $this->_dealCombinationItems($skuList);
        $porth = $this->_getPorthValue($skuList);
        if(empty($porth)) {
            return array(false, $this->failMsg);
        }
        
        // 获取会计科目和税率信息
        $accountInfo = array(
            'account_id_plus' => null,
            'account_id_minus' => null,
            'tax_rate_plus' => 0,
            'tax_rate_minus' => 0,
            'postingkey_plus' => '',
            'postingkey_minus' => '',
            'costcenter_plus' => '',
            'costcenter_minus' => '',
            'customer_plus' => '',
            'customer_minus' => '',
        );
        if (!empty($skuList['bill']['bill_category'])) {
            $ruleModel = app::get('financebase')->model('expenses_rule');
            $ruleData = $ruleModel->db_dump(
                array('bill_category' => $skuList['bill']['bill_category']),
                'account_id_plus,account_id_minus'
            );
            if ($ruleData) {
                $accountInfo['account_id_plus'] = $ruleData['account_id_plus'];
                $accountInfo['account_id_minus'] = $ruleData['account_id_minus'];
                
                $accountChartModel = app::get('financebase')->model('account_chart');
                
                // 如果有贷会计科目，获取税率、记账码、成本中心、客户信息
                if ($ruleData['account_id_plus']) {
                    $accountData = $accountChartModel->db_dump(
                        array('id' => $ruleData['account_id_plus']),
                        'tax_rate,postingkey,costcenter,customer'
                    );
                    if ($accountData) {
                        if ($accountData['tax_rate']) {
                            $accountInfo['tax_rate_plus'] = $accountData['tax_rate'];
                        }
                        $accountInfo['postingkey_plus'] = $accountData['postingkey'] ?? '';
                        $accountInfo['costcenter_plus'] = $accountData['costcenter'] ?? '';
                        $accountInfo['customer_plus'] = $accountData['customer'] ?? '';
                    }
                }
                
                // 如果有借会计科目，获取税率、记账码、成本中心、客户信息
                if ($ruleData['account_id_minus']) {
                    $accountData = $accountChartModel->db_dump(
                        array('id' => $ruleData['account_id_minus']),
                        'tax_rate,postingkey,costcenter,customer'
                    );
                    if ($accountData) {
                        if ($accountData['tax_rate']) {
                            $accountInfo['tax_rate_minus'] = $accountData['tax_rate'];
                        }
                        $accountInfo['postingkey_minus'] = $accountData['postingkey'] ?? '';
                        $accountInfo['costcenter_minus'] = $accountData['costcenter'] ?? '';
                        $accountInfo['customer_minus'] = $accountData['customer'] ?? '';
                    }
                }
            }
        }
        
        $time = $time ? : time();
        $expensesSplit = array();
        $hasPorth = false;
        foreach ($skuList['sku'] as $v) {
            if($porth[$v['bm_id']] > 0) {
                $hasPorth = true;
            }
            $expensesSplit[] = array(
                'split_bn' => $expensesSplitModel->gen_id(),
                'bm_id' => $v['bm_id'],
                'bill_id' => $skuList['bill']['id'],
                'parent_id' => $skuList['parent_id'],
                'parent_type' => $skuList['parent_type'],
                'trade_time' => $skuList['bill']['trade_time'],
                'split_time' => $time,
                'money' => 0,
                'bill_category' => $skuList['bill']['bill_category'],
                'split_type' => $this->type,
                'split_rule' => $this->rule,
                'porth' => $porth[$v['bm_id']],
                'shop_id' => $skuList['bill']['shop_id'],
                'account_id_plus' => $accountInfo['account_id_plus'],
                'account_id_minus' => $accountInfo['account_id_minus'],
                'tax_rate_plus' => $accountInfo['tax_rate_plus'],
                'tax_rate_minus' => $accountInfo['tax_rate_minus'],
                'tax_amount_plus' => 0, // 将在计算money后更新
                'tax_amount_minus' => 0, // 将在计算money后更新
                'order_bn' => $skuList['bill']['order_bn'] ?? '',
                'financial_no' => $skuList['bill']['financial_no'] ?? '',
                'postingkey_plus' => $accountInfo['postingkey_plus'],
                'postingkey_minus' => $accountInfo['postingkey_minus'],
                'costcenter_plus' => $accountInfo['costcenter_plus'],
                'costcenter_minus' => $accountInfo['costcenter_minus'],
                'customer_plus' => $accountInfo['customer_plus'],
                'customer_minus' => $accountInfo['customer_minus'],
            );
        }
        if(!$hasPorth) {
            return array(false, $this->failMsg);
        }
        if($skuList['ids']) {
            $olds = $expensesSplitModel->db_dump(['bill_id'=>$skuList['bill']['id']]);
            try{
                $splitStatus = $olds ? '2' : '1';
                app::get('financebase')->model($skuList['parent_type'])->update(['split_status'=>$splitStatus],['id'=>$skuList['ids']]);
            } catch (Exception $e){}
        }
        $options = array (
            'part_total'  => $skuList['bill']['money'],
            'part_field'  => 'money',
            'porth_field' => 'porth',
        );
        $expensesSplit = kernel::single('ome_order')->calculate_part_porth($expensesSplit, $options, 5);
        
        // 计算税金
        foreach ($expensesSplit as &$item) {
            // 计算贷方税金
            if ($accountInfo['tax_rate_plus'] > 0) {
                $item['tax_amount_plus'] = round($item['money'] * $accountInfo['tax_rate_plus'], 5);
            }
            // 计算借方税金
            if ($accountInfo['tax_rate_minus'] > 0) {
                $item['tax_amount_minus'] = round($item['money'] * $accountInfo['tax_rate_minus'], 5);
            }
        }
        unset($item);
        
        $sql = ome_func::get_insert_sql($expensesSplitModel, $expensesSplit);
        $expensesSplitModel->db->exec($sql);
        return array(true, '拆分完成');
    }

}