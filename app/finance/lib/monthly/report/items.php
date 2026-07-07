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

class finance_monthly_report_items {
    private $monthly_report;
    private $gap_list;

    /**
     * 获取ItemId
     * @param mixed $sdf sdf
     * @param mixed $insert insert
     * @return mixed 返回结果
     */
    public function getItemId($sdf, $insert = true) {
        if(!$sdf['order_bn']) {
            return 0;
        }
        $shop_id = $sdf['channel_id'];
        $time = $sdf['trade_time'];
        if(!isset($this->monthly_report[$shop_id]))
        {
            $mdlMonthlyReport = app::get('finance')->model('monthly_report');
            $mr = $mdlMonthlyReport->getList('monthly_id,begin_time,end_time',array('shop_id'=>$shop_id,'status|lthan'=>2));
            $this->monthly_report[$shop_id] = array_column($mr, null, 'monthly_id');
        }
        $mr = $this->monthly_report[$shop_id];
        $oMRI = app::get('finance')->model('monthly_report_items');
        $items = $oMRI->getList('id,monthly_id', ['order_bn'=>$sdf['order_bn']]);
        if($items) {
            foreach($items as $v) {
                if($mr[$v['monthly_id']])
                {
                    return $v['id'];
                }
            }
        }
        if(!$insert) {
            return 0;
        }
        foreach ($mr as $v) 
        {
            if($v['begin_time'] <= $time and $v['end_time'] >= $time )
            {
                $data = [
                    'order_bn' => $sdf['order_bn'],
                    'monthly_id' => $v['monthly_id'],
                ];
                $rs = $oMRI->insert($data);
                if($rs) {
                    return $data['id'];
                }
                return $oMRI->db_dump($data, 'id')['id'];
            }
        }
        return 0;
    }

    /**
     * doAutoVerificate
     * @param mixed $itemId ID
     * @param mixed $shop_id ID
     * @return mixed 返回值
     */
    public function doAutoVerificate($itemId, $shop_id) {
        if(empty($itemId)) {
            return [false, ['msg'=>'缺少单据']];
        }
        list($rs, $rsData) = $this->doGapRule($itemId, $shop_id);
        $oMRI = app::get('finance')->model('monthly_report_items');
        $billInfo = [];
        if($billInfo = app::get('finance')->model('bill')->db_dump(['status|noequal'=>'2', 'monthly_item_id'=>$itemId])
            || app::get('finance')->model('ar')->db_dump(['status|noequal'=>'2', 'monthly_item_id'=>$itemId], 'ar_id')
        ) {
            $upData = ['gap_type'=>'', 'memo'=>$rsData['msg'], 'verification_status'=>'1'];
            if($billInfo && $billInfo['fee_item'] == '国补金额' && $billInfo['money']>0){
                $upData['gov_shishou_total_upset_sql'] = '`gov_shishou_total`+'.$billInfo['money'];
            }
            $oMRI->update($upData, ['id'=>$itemId]);
        } else {
            $upData = ['verification_status'=>'2', 'memo'=>'', 'gap_type'=>''];
            if($rsData['msg']) {
                $upData['memo'] = $rsData['msg'];
            }
            if($rsData['gap_type']) {
                $upData['gap_type'] = $rsData['gap_type'];
            }
            $oMRI->update($upData, ['id'=>$itemId]);
            
            // 更新核对状态
            kernel::single('finance_verification')->updateArVerifyStatus($itemId);
        }

        $servicelist = kernel::servicelist('financebase.reportitem.doAutoVerificate.after');
        foreach ($servicelist as $instance) {
            if (method_exists($instance, 'after_verificate')){
                $instance->after_verificate($itemId);
            }
        }

        $itemRow = $oMRI->db_dump($itemId, 'monthly_id');
        if(!empty($itemRow['monthly_id'])) {
            finance_monthly_report::updateMonthlyAmount(array('monthly_id'=>$itemRow['monthly_id']));
        }

        return [$rs, $rsData];
    }

    /**
     * doGapRule
     * @param mixed $itemId ID
     * @param mixed $shop_id ID
     * @return mixed 返回值
     */
    public function doGapRule($itemId, $shop_id) {
        if(!isset($this->gap_list)) {
            $this->gap_list = array();
            $mdlBillVerificationError    = app::get('financebase')->model('bill_verification_error');
            foreach ($mdlBillVerificationError->getList('rule,is_verify,verify_mode,shop_id,name', array(), 0, -1, 'priority desc') as $key => $value) {
                $this->gap_list[$value['shop_id']][] = $value;
            }
        }
        # 三位小数
        $diff = $this->getDiffMoney($itemId);
        $gap_list = $this->gap_list[$shop_id] ? : [];
        $z = $p = $pz = true;
        foreach ($gap_list as $gap) {
            if($gap['verify_mode'] == '1') {
                ##收入
                if($z) {
                    $diff_money = $diff['yingshou_money'] - $diff['shishou_money'];
                    $diff_money = sprintf('%.3f', $diff_money);
                    list($zrs, $zrsData) = $this->gapCalc($gap, $diff_money, $itemId, 'z');
                    if($zrsData['msg'] != '未匹配到核销规则') {
                        $z = false;
                    }
                    $zrsData['msg'] = '收入:'.$zrsData['msg'];
                }
                #退款
                if($p) {
                    $diff_money = $diff['yingtui_money'] - $diff['shitui_money'];
                    $diff_money = sprintf('%.3f', $diff_money);
                    list($prs, $prsData) = $this->gapCalc($gap, $diff_money, $itemId, 'p');
                    if($prsData['msg'] != '未匹配到核销规则') {
                        $p = false;
                    }
                    $prsData['msg'] = '退款:'.$prsData['msg'];
                }
                #缺少实收实退
                if($diff['shishou_money'] == 0 && $diff['shitui_money'] == 0 && $pz) {
                    $diff_money = $diff['yingtui_money'] + $diff['yingshou_money'];
                    $diff_money = sprintf('%.3f', $diff_money);
                    list($pzrs, $pzrsData) = $this->gapCalc($gap, $diff_money, $itemId, 'ying');
                    if($pzrsData['msg'] != '未匹配到核销规则') {
                        $pz = false;
                    }
                    $pzrsData['msg'] = '应收应退:'.$pzrsData['msg'];
                }
                if(!$z && !$p && !$pz) {
                    $rsData = $zrsData;
                    $rsData['msg'] .= $prsData['msg'];
                    $rsData['msg'] .= $pzrsData['msg'];
                    return [($zrs && $prs && $pzrs), $rsData];
                }
            } else {
                $diff_money = $diff['gap'];
                $diff_money = sprintf('%.3f', $diff_money);
                list($rs, $rsData) = $this->gapCalc($gap, $diff_money, $itemId);
                if($rsData['msg'] != '未匹配到核销规则') {
                    return [$rs, $rsData];
                }
            }
        }
        return [false, ['msg'=>$gap_list ? '未匹配到核销规则' : '未配置核销规则']];
    }

    /**
     * gapCalc
     * @param mixed $gap gap
     * @param mixed $diff_money diff_money
     * @param mixed $itemId ID
     * @param mixed $compare compare
     * @return mixed 返回值
     */
    public function gapCalc($gap, $diff_money, $itemId, $compare = 'order') {
        $expression = array();
        foreach ($gap['rule'] as $rule) {
            $expression[] = str_replace('{field}', $diff_money, $this->get_comp($rule['operator'], $rule['operand']));
        }

        $expression = implode(' && ', array_filter($expression));
        $result = false;
        eval("\$result=($expression);");

        if ($result) {

            // 强制核销
            if ($gap['is_verify'] == 1) {
                $sdf = [
                    'gap_type' => $gap['name'],
                    'verification_status' => $diff_money == 0 ? 1 : 2,
                    'compare' => $compare
                ];
                list($verRs, $rsData) = $this->doVerificate($itemId, $sdf);
                $rsData['gap_type'] = $sdf['gap_type'];
                return [$verRs, $rsData];
            }

            return [false, ['msg'=>$gap['name'] . '未开启核销']];
        }
        return [false, ['msg' => '未匹配到核销规则']];
    }

    /**
     * 获取DiffMoney
     * @param mixed $itemId ID
     * @return mixed 返回结果
     */
    public function getDiffMoney($itemId) {
        $arlist = app::get('finance')->model('ar')->getList('money,type,trade_time,actually_money,platform_amount', ['monthly_item_id' => $itemId]);
        $upData = ['yingshou_money'=>0, 'yingtui_money'=>0, 'refund_only_money'=>0,'shishou_money'=>0,'shitui_money'=>0,'actually_amount'=>0,'platform_amount'=>0,'refund_actually_amount'=>0,'refund_platform_amount'=>0];
        foreach ($arlist as $arRow) {
            if($arRow['money'] > 0) {
                $upData['ship_time'] = $arRow['trade_time'];
                $upData['yingshou_money'] += $arRow['money'];
                // 累计客户实付和平台承担金额（只计算收入）
                $upData['actually_amount'] += $arRow['actually_money'];
                $upData['platform_amount'] += $arRow['platform_amount'];
            } else {
                $upData['reship_time'] = $arRow['trade_time'];
                $upData['yingtui_money'] += $arRow['money'];
                // 累计退款客户实付和退款平台承担金额（只计算退款）
                $upData['refund_actually_amount'] += $arRow['actually_money'];
                $upData['refund_platform_amount'] += $arRow['platform_amount'];
                if($arRow['type'] == kernel::single('finance_ar')->get_type_by_name('售后仅退款')) {
                    $upData['refund_only_money'] += $arRow['money'];
                }
            }
        }
        $upData['xiaotui_total'] = $upData['yingshou_money'] + $upData['yingtui_money'];
        $billList = app::get('finance')->model('bill')->getList('money,trade_time', ['monthly_item_id' => $itemId]);
        foreach ($billList as $billRow) {
            if($billRow['money'] > 0) {
                $upData['shishou_trade_time'] = $billRow['trade_time'];
                $upData['shishou_money'] += $billRow['money'];
            } else {
                $upData['shitui_trade_time'] = $billRow['trade_time'];
                $upData['shitui_money'] += $billRow['money'];
            }
        }
        $upData['shouzhi_total'] = $upData['shishou_money'] + $upData['shitui_money'];
        $upData['gap'] = $upData['xiaotui_total'] - $upData['shouzhi_total'];
        $upData['sales_gap'] = $upData['yingshou_money'] - $upData['shishou_money'];
        $upData['refund_gap'] = $upData['yingtui_money'] - $upData['shitui_money'];
        app::get('finance')->model('monthly_report_items')->update($upData, ['id'=>$itemId]);
        return $upData;
    }
    
    /**
     * 获取_comp
     * @param mixed $type type
     * @param mixed $var var
     * @return mixed 返回结果
     */
    public function get_comp($type, $var)
    {
        $comp = array(
            'nequal'  => '{field}==' . $var,
            'than'    => '{field}> ' . $var,
            'lthan'   => '{field}< ' . $var,
            'bthan'   => '{field}>=' . $var,
            'sthan'   => '{field}<=' . $var,
            'between' => '{field}>=' . $var[0] . ' && ' . ' {field}<=' . $var[1],
        );

        return $comp[$type];
    }

    /**
     * doVerificate
     * @param mixed $itemId ID
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */
    public function doVerificate($itemId, $sdf) {
        $verification_time = time();
        $filter = ['status|noequal'=>'2', 'monthly_item_id'=>$itemId];
        if($sdf['compare'] == 'z') {
            $filter['money|than'] = 0;
        } elseif($sdf['compare'] == 'p') {
            $filter['money|lthan'] = 0;
        }
        $mdlBill = app::get('finance')->model('bill');
        if($sdf['compare'] == 'ying') {
            $bill_data = [];
        } else {
            $bill_data = $mdlBill->getList('bill_id,bill_bn,money,memo', $filter);
        }
        foreach ($bill_data as $key => $value) {
            // 更新核销流水表
           $data = array();
           $data['verification_time']   = $verification_time;
           $data['status']              = 2;
           $data['confirm_money']       = $value['money'];
           $data['unconfirm_money']     = 0;
           $data['verification_status'] = $sdf['verification_status'];
           $data['gap_type'] = $sdf['gap_type'];
           $data['memo'] = ($sdf['compare'] == 'z' ? '按收入核销' : ($sdf['compare'] == 'p' ? '按退款核销' : '按订单核销')) . ' ' . $value['memo'];
           $data['auto_flag']           = 1;

           $mdlBill->update($data,array('bill_id'=>$value['bill_id'],'status|noequal'=>'2'));
       }
        $mdlAr = app::get('finance')->model('ar');
        $ar_data = $mdlAr->getList('ar_id, money', $filter);
        foreach ($ar_data as $key => $value) {
            $data = array();
            if(in_array($sdf['compare'], ['order','ying']) && empty($bill_data)) {
                $data['verification_flag'] = 1;
            }
            $data['verification_time']   = $verification_time;
            $data['verification_status'] = $sdf['verification_status'];
            $data['gap_type'] = $sdf['gap_type'];
            $data['status']              = 2;
            $data['confirm_money']       = $value['money'];
            $data['unconfirm_money']     = 0;
            $data['memo']                = ($sdf['compare'] == 'z' ? '按收入核销' : ($sdf['compare'] == 'p' ? '按退款核销' : '按订单核销')) . ' ' . '实收实退单据号:'. implode('|', array_column($bill_data,'bill_bn')) ;
            $data['auto_flag']           = 1;

            $mdlAr->update($data,array('ar_id'=>$value['ar_id'],'status|noequal'=>'2'));
        }
        return [true, ['msg'=>'核销成功']];
    }

    /**
     * dealArMatchReport
     * @param mixed $ar_id ID
     * @return mixed 返回值
     */
    public function dealArMatchReport($ar_id) {
        $oMonthlyReportItems = kernel::single('finance_monthly_report_items');
        $oAr = app::get('finance')->model('ar');
        $arRow = $oAr->db_dump($ar_id, 'ar_id,ar_bn,order_bn,channel_id,trade_time,money,monthly_item_id,channel_id,ar_type');
        if(empty($arRow)) {
            return [false, ['msg'=>'缺少单据']];
        }
        if($arRow['monthly_item_id']) {
            return [false, ['msg'=>$arRow['ar_bn'].'已经匹配账期']];
        }
        $init_time = app::get('finance')->getConf('finance_setting_init_time');
        if($init_time['according'] == 'shi_shou') {
            $itemId = $oMonthlyReportItems->getItemId($arRow, false);
            if(empty($itemId) && $arRow['ar_type'] == 1) {
                $order = app::get('ome')->model('orders')->db_dump(['order_bn'=>$arRow['order_bn']], 'source_status,end_time,shop_type');
                // ar_type=1:应退；source_status=TRADE_CLOSED:平台关闭；shop_type=luban:抖音；end_time为空:未确认收货。
                if($order && ($order['source_status'] == 'TRADE_CLOSED' || ($order['shop_type'] == 'luban' && empty($order['end_time'])))) {
                    $itemId = $oMonthlyReportItems->getItemId($arRow);
                    $arRows = $oAr->getList('ar_id',['order_bn'=>$arRow['order_bn'], 'monthly_item_id'=>0, 'ar_id|noequal'=>$arRow['ar_id']]);
                    if($arRows) {
                        foreach($arRows as $v) {
                            $this->dealArMatchReport($v['ar_id']);
                        }
                    }
                }
            }
        } else {
            $itemId = $oMonthlyReportItems->getItemId($arRow);
            $oBill = app::get('finance')->model('bill');
            $billRows = $oBill->getList('bill_id',['order_bn'=>$arRow['order_bn'], 'monthly_item_id'=>0]);
            if($billRows) {
                foreach($billRows as $v) {
                    $this->dealBillMatchReport($v['bill_id']);
                }
            }
        }
        if($itemId) {
            kernel::database()->beginTransaction();
            $sql = 'select id, monthly_id, yingshou_money, yingtui_money, xiaotui_total, shouzhi_total
                    from sdb_finance_monthly_report_items
                    where id="'.$itemId.'" limit 1 for update';
            $itemMRI = kernel::database()->select($sql);
            $itemMRI = $itemMRI[0];
            $main = [];
            $main['monthly_id']     = $itemMRI['monthly_id'];
            $main['monthly_item_id']     = $itemId;
            $oAr->update($main, ['ar_id'=>$arRow['ar_id']]);
            kernel::single('finance_monthly_report_items')->doAutoVerificate($itemId, $arRow['channel_id']);
            kernel::database()->commit();
            return [true, ['msg' => $arRow['ar_bn'].'匹配成功', 'monthly_id'=>$itemMRI['monthly_id']]];
        }
        return [false, ['msg' => $arRow['ar_bn'].'匹配失败']];
    }

    /**
     * dealBillMatchReport
     * @param mixed $bill_id ID
     * @return mixed 返回值
     */
    public function dealBillMatchReport($bill_id) {
        $oMonthlyReportItems = kernel::single('finance_monthly_report_items');
        $oBill = app::get('finance')->model('bill');
        $billRow = $oBill->db_dump($bill_id, 'bill_id,bill_bn,order_bn,channel_id,trade_time,money,monthly_item_id,channel_id');
        if(empty($billRow)) {
            return [false, ['msg'=>'缺少单据']];
        }
        if($billRow['monthly_item_id']) {
            return [false, ['msg'=>$billRow['bill_bn'].'已经匹配账期']];
        }
        $init_time = app::get('finance')->getConf('finance_setting_init_time');
        if($init_time['according'] == 'shi_shou') {
            $itemId = $oMonthlyReportItems->getItemId($billRow);
            $oAr = app::get('finance')->model('ar');
            $arRows = $oAr->getList('ar_id',['order_bn'=>$billRow['order_bn'], 'monthly_item_id'=>0]);
            if($arRows) {
                foreach($arRows as $v) {
                    $this->dealArMatchReport($v['ar_id']);
                }
            }
        } else {
            $itemId = $oMonthlyReportItems->getItemId($billRow, false);
        }
        if($itemId) {
            kernel::database()->beginTransaction();
            $sql = 'select id, monthly_id, xiaotui_total, shishou_money, shitui_money, shouzhi_total
                    from sdb_finance_monthly_report_items
                    where id="'.$itemId.'" limit 1 for update';
            $itemMRI = kernel::database()->select($sql);
            $itemMRI = $itemMRI[0];
            $main = [];
            $main['monthly_id']     = $itemMRI['monthly_id'];
            $main['monthly_item_id']     = $itemId;
            $oBill->update($main, ['bill_id'=>$billRow['bill_id']]);
            kernel::single('finance_monthly_report_items')->doAutoVerificate($itemId, $billRow['channel_id']);
            kernel::database()->commit();
            return [true, ['msg' => $billRow['bill_bn'].'匹配成功', 'monthly_id'=>$itemMRI['monthly_id']]];
        }
        return [false, ['msg' => $billRow['bill_bn'].'匹配失败']];
    }
}