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
 * @author ykm 2017/7/6
 * @describe 订单发票
 */
class erpapi_shop_response_plugins_order_invoice extends erpapi_shop_response_plugins_order_abstract
{

    /**
     * convert
     * @param erpapi_shop_response_abstract $platform platform
     * @return mixed 返回值
     */

    public function convert(erpapi_shop_response_abstract $platform)
    {
        $invoice = array();
        $invoice['tax_no']                  = $platform->_ordersdf['tax_no'];
        $invoice['tax_title']               = $platform->_ordersdf['tax_title'];
        $invoice_kind                       = $platform->_ordersdf['invoice_kind']?$platform->_ordersdf['invoice_kind']:"2";
        $invoice['invoice_bank_name']       = $platform->_ordersdf['invoice_bank_name'];
        $invoice['invoice_phone']           = $platform->_ordersdf['invoice_phone'];
        $invoice['invoice_address']         = $platform->_ordersdf['invoice_address'];
        $invoice['invoice_bank_account']    = $platform->_ordersdf['invoice_bank_account'];
        $invoice['invoice_receiver_name']   = $platform->_ordersdf['invoice_receiver_name'];
        $value_added_tax_invoice = $platform->_ordersdf['value_added_tax_invoice'];
        $invoiceKindMap = [
            '1' => 1,
            '2' => 2,
            '3' => 3,
        ];
        if($platform->_ordersdf['invoice_status']){
            $invoice_status = $platform->_ordersdf['invoice_status'];
            if($invoice_status =='2'){
                $invoice['is_status'] = '1';
            }else if($invoice_status =='3'){
                $invoice['is_status'] = '2';
            }


        }
        $invoice['invoice_kind'] = $invoiceKindMap[$invoice_kind] ? $invoiceKindMap[$invoice_kind] : $invoice_kind;
        // 专票兼容
        if($platform->_ordersdf['invoice_kind'] == '3'){
            $value_added_tax_invoice = true;
        }
        $invoice['value_added_tax_invoice'] = $value_added_tax_invoice;
        $invoice['invoice_amount']          = $platform->_ordersdf['invoice_amount'] ? : $platform->_ordersdf['total_amount'];          // 开票金额
        // todo 文档内缺失invoice_receiver_addr
        // 补充收票邮箱/收票手机号/收票地址字段
        if(isset($platform->_ordersdf['invoice_receiver_addr'])){
            $invoice['receiver_addr'] = $platform->_ordersdf['invoice_receiver_addr'];
        }

        if (isset($platform->_ordersdf['invoice_receiver_email'])) {
            $invoice['receiver_email'] = $platform->_ordersdf['invoice_receiver_email'];
        }

        if (isset($platform->_ordersdf['invoice_receiver_mobile'])) {
            $invoice['receiver_mobile'] = $platform->_ordersdf['invoice_receiver_mobile'];
        }
    
        if (isset($platform->_ordersdf['invoice_receiver_name'])) {
            $invoice['receiver_name'] = $platform->_ordersdf['invoice_receiver_name'];
        }
        
        
        
        if (!$platform->_tgOrder 
            && in_array($platform->__channelObj->channel['shop_type'], array('360buy'))){
            // $invoice_pmt_amount = 0;

            // 京东的优惠券，算支付金额。所以，ERP计算开票金额的时候，需要从支付总额中扣除优惠券。增加发票优惠字段invoice_pmt_amount
            // if ($platform->_ordersdf['pmt_detail']) {
            //     foreach($platform->_ordersdf['pmt_detail'] as $pmt_detail){
            //         list($pmt_prefix) = explode('-', $pmt_detail['pmt_describe']);
            //         if(!empty($pmt_detail['pmt_amount']) && in_array($pmt_prefix, array('41','52','34','39'))){
            //             $invoice_pmt_amount += $pmt_detail['pmt_amount'];
            //         }
            //     }
            // }

            //if (!$invoice['invoice_amount']) $invoice['invoice_amount'] = $platform->_ordersdf['total_amount'];

            // 扣掉京东收取的退货无忧费
            if (1 === bccomp((float)$platform->_ordersdf['return_insurance_fee'], 0, 3)) {
                // $invoice_pmt_amount += $platform->_ordersdf['return_insurance_fee'];
                $invoice['invoice_amount'] -= $platform->_ordersdf['return_insurance_fee'];
            }

            // $invoice['invoice_pmt_amount'] = $invoice_pmt_amount;#发票优惠金额
        }


        $invoice['register_no'] = $platform->_ordersdf['payer_register_no'];
        $invoice['title_type'] = $invoice['register_no'] ? '1' : '0';
        
        $return = array();
        if($invoice['tax_no'] || $invoice['tax_title']) {
            if ($platform->_tgOrder) {
                $oldInvoice = app::get('ome')->model('order_invoice')->db_dump(array('order_id'=>$platform->_tgOrder['order_id']));
                
                // 读取归档订单发票记录
                if(empty($oldInvoice)){
                    $oldInvoice = app::get('archive')->model('order_invoice')->db_dump(array('order_id'=>$platform->_tgOrder['order_id']));
                }
                
                if($oldInvoice) {
                    foreach ($invoice as $k => $val) {
                        if (isset($oldInvoice[$k]) && $val != $oldInvoice[$k]) {
                            $return[$k] = $val;
                        }
                    }
                } else {
                    $return = $invoice;
                }
            } else {
                $return = $invoice;
            }
        }
        return $return;
    }

    /**
     * postCreate
     * @param mixed $order_id ID
     * @param mixed $invoice invoice
     * @return mixed 返回值
     */
    public function postCreate($order_id, $invoice)
    {
        $orderField   = 'order_id,order_bn,ship_name,ship_area,ship_addr,ship_mobile,ship_tel,shop_type,source_status';
        $order = app::get('ome')->model('orders')->db_dump(['order_id'=>$order_id], $orderField);
        $source_status = $order['source_status'];
        unset($order['source_status']);
        $sdf = array_merge($order, $invoice);
        if($source_status == 'TRADE_FINISHED') {
            $sdf['status'] = 'finish';
        } elseif ($source_status == 'TRADE_CLOSED') {
            $sdf['status'] = 'close';
        }
        // 收票地址 ,覆盖原有字段取值
        if(isset($invoice['receiver_addr']) && $invoice['receiver_addr']){
            $sdf['ship_addr'] = $invoice['receiver_addr'];
        }

        // 收票电话 ,覆盖原有字段取值
        if (isset($invoice['receiver_mobile']) && $invoice['receiver_mobile']) {
            $sdf['ship_mobile'] = $invoice['receiver_mobile'];
        }
        
        if (isset($invoice['receiver_name']) && $invoice['receiver_name']) {
            $sdf['invoice_receiver_name'] = $invoice['receiver_name'];
        }
        
        kernel::single('ome_order_invoice')->insertInvoice($sdf);
    }

    // todo 待确认更新逻辑
    /**
     * postUpdate
     * @param mixed $order_id ID
     * @param mixed $invoice invoice
     * @return mixed 返回值
     */
    public function postUpdate($order_id, $invoice) {
        app::get('ome')->model('order_invoice')->update($invoice, array('order_id'=>$order_id));
    }
}
