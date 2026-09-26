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
 * @author ykm 2017/7/5
 * @describe 发票处理
 */
class erpapi_shop_response_process_invoice {

    /**
     * message_push
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */

    public function message_push($sdf) {
        //custom 兼容前端传递开票扩展参数
        if(isset($sdf['extend_arg']) && $sdf['extend_arg']){
            $extendInvoiceInfo = $this->__formatExtendArg($sdf['extend_arg'], $sdf['order_info']['consignee']);
            
            if(!empty($extendInvoiceInfo)){
                $sdf = array_merge($sdf, $extendInvoiceInfo);
            }
        }
        $sdf = array_merge($sdf['order_info'], $sdf);
        if($sdf['order_info']['is_tax'] == 'false') {
            $sdf['order_info']['is_tax'] = 'true';
            app::get('ome')->model('orders')->update(array('is_tax' => 'true'), array('order_id' => $sdf['order_info']['order_id']));
        }
        
        // 来源标记
        $sdf['source_marking'] = 'message_push';
        
        // 是否自动开票(1:自动开票, 2:手工开票（默认）)
        $sdf['is_auto'] = '1';
        
        $rsp_msg = '';
        if($sdf['old_invoice']) {
            kernel::single('ome_order_invoice')->updateInvoice($sdf, '订单更新发票信息');
            list($rs, $rsData) = kernel::single('invoice_order_front')->insertOrUpdateByOrder($sdf);
            app::get('ome')->model('operation_log')->write_log('order_modify@ome', $sdf['order_id'], '更新发票信息:'.$rsData['msg']);
            
            $rsp_msg = ($rsData['msg'] ? '：' . $rsData['msg'] : '');
            
            return array('rsp'=>'succ', 'msg'=>'更新发票信息' . $rsp_msg);
        }else{
            $rs = kernel::single('ome_order_invoice')->insertInvoice($sdf, '订单保存发票信息', $rsp_msg);
            if($rs) {
                app::get('ome')->model('operation_log')->write_log('order_modify@ome',$sdf['order_info']['order_id'],'订单保存发票信息');
                return array('rsp'=>'succ', 'msg'=>'发票生成成功');
            }
            
            $rsp_msg = ($rsp_msg ? '：' . $rsp_msg : '');
        }
        
        return array('rsp'=>'fail', 'msg'=>'发票生成失败'. $rsp_msg);
    }

    /**
     * 添加
     * @param mixed $order order
     * @return mixed 返回值
     */
    public function add($order)
    {
        return array('rsp'=>'fail','msg'=>'该接口已经弃用!');
        $orderMdl     = app::get('ome')->model('orders');
        $orderInvoice = app::get('ome')->model('order_invoice');

        // 判断是否已经开票
        $invoiceCount = $orderInvoice->count(array('order_id'=>$order['order_id']));

        // 如果未开票，删除重来
        if ($invoiceCount && app::get('invoice')->is_installed()) {
            $invoiceMdl = app::get('invoice')->model('order');

            foreach ($invoiceMdl->getList('is_status,order_id,sync',array('order_id'=>$order['order_id'])) as $value) {
                if ($value['is_status'] == '1' || $value['sync'] != '0') {
                    return array('rsp'=>'fail','msg'=>'订单已经开票!');
                }
            }

            if ($order['order_id']) $invoiceMdl->delete(array('order_id'=>$order['order_id']));
        }

        if ($invoiceCount) $orderInvoice->delete(array('order_id'=>$order['order_id']));

        // 标记订单为开票
        $orderMdl->update(array('is_tax'=>'true'), array('order_id'=>$order['order_id']));

        $memo = '买家要求开票,发票抬头: '. $order['tax_title'];
        kernel::single('ome_order_invoice')->insertInvoice($order,$memo);

        return array('rsp'=>'succ','msg'=>'自助开发票成功!');

    }

    /**
     * 组装扩展信息至开票数据
     * @param Array|String $extendArg 开票扩展信息
     * @param Array $consignee 原订单收货信息
     * @return Array 发票扩展信息
     */
    private function __formatExtendArg($extendArg,$consignee)
    {
        
        $extendInvoiceInfo = [];
        
        if (is_string($extendArg)) {
            $extendArg = json_decode($extendArg, true);
        }

        $extendInvoiceInfo['consignee'] = $consignee;
        
        // 邮箱
        if(isset($extendArg['receiver_email'])){
            $extendInvoiceInfo['receiver_email'] = $extendArg['receiver_email'];
        }
       
        //invoice_bank_name
        if (isset($extendArg['bank'])) {
            $extendInvoiceInfo['invoice_bank_name'] = $extendArg['bank'];
        }
        
        //invoice_bank_account
        if (isset($extendArg['bank_account'])) {
            $extendInvoiceInfo['invoice_bank_account'] = $extendArg['bank_account'];
        }
        
        //invoice_address
        if (isset($extendArg['registered_address'])) {
            $extendInvoiceInfo['invoice_address'] = $extendArg['registered_address'];
        }
        // invoice_phone
        if (isset($extendArg['registered_phone'])) {
            $extendInvoiceInfo['invoice_phone'] = $extendArg['registered_phone'];
        }
        

        if (isset($extendArg['invoice_receiver_name'])) {
            $extendInvoiceInfo['invoice_receiver_name'] = $extendArg['invoice_receiver_name'];
        }

        // 收票地址
        if (isset($extendArg['receiver_addr'])) {
            //覆盖原有订单字段取值
            $extendInvoiceInfo['ship_addr'] = $extendArg['receiver_addr'];
        }
  
        // 收票电话
        if (isset($extendArg['receiver_mobile'])) {
            //覆盖原有订单字段取值
            $extendInvoiceInfo['ship_mobile'] = $extendArg['receiver_mobile'];
        }
        
        return $extendInvoiceInfo;

    }
}
