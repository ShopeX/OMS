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
 * 自助开发票(店小蜜)
 *
 * @author wangbiao<wangbiao@shopex.cn>
 * @version 0.1
 */
class erpapi_shop_response_invoice extends erpapi_shop_response_abstract
{
    /**
     * 接收参数
     */

    public $_sdf = array();

    /**
     * 自助开发票
     * 
     * @param array $params
     * @return array
     */
    public function add($params)
    {
        $this->__apilog['title'] = '自助开发票';

        //format params
        $params = $this->_returnParams($params);
        if (empty($params)) {
            $this->__apilog['result']['msg'] = '自助开发票: 该平台不支持自助开发票';
            return false;
        }

        $this->__apilog['original_bn']    = $params['order_bn'];
        $this->__apilog['result']['data'] = $params;

        if (empty($params['order_bn'])) {
            $this->__apilog['result']['msg'] = '自助开发票: 没有可开发票的订单!';
            return false;
        }

        //检查订单
        $orderObj   = app::get('ome')->model('orders');
        $this->_sdf = $orderObj->dump(array('order_bn' => $params['order_bn']), 'order_id, order_bn, is_tax, ship_name, ship_area, ship_addr, ship_zip, ship_tel, ship_email, ship_mobile');
        if (empty($this->_sdf)) {
            $this->__apilog['result']['msg'] = '自助开发票: 订单不存在!';
            return false;
        }

        if ($this->_sdf['status'] == 'dead') {
            $this->__apilog['result']['msg'] = '自助开发票: 订单已作废,不能开票!';
            return false;
        }

        $this->_sdf = array_merge($this->_sdf, $params);

        return $this->_sdf;
    }

    /**
     * 获取数据
     * 
     * @param array $params
     * @return array:
     */
    protected function _returnParams($params)
    {
        return array();
    }

    /**
     * 格式化参数
     * 
     * @param array $params
     * @return array:
     */
    protected function _formatParams($params)
    {

        //订单号
        $order_bn = $params['tid'];

        //发票属性(0:公司；1：个人)
        $invoice_attr = ($params['invoice_attr'] == '1' ? 1 : 0);

        //发票类型（1:普通发票；2：增值税专用发票）
        $invoice_type = ($params['invoice_type'] == '2' ? 2 : 1);

        //发票形态 （1:电子发票; 2：纸质发票)
        $invoice_kind = ($params['invoice_kind'] == '2' ? 2 : 1);

        //公司发票抬头
        $company_title = $params['company_title'];

        //公司发票税号
        $tax_no = $params['tax_no'];

        //增值税扩展参数(registered_address注册地址、registered_phone注册电话、bank开户行、bank_account账户)
        $extend_arg = ($params['extend_arg'] ? json_decode($params['extend_arg'], true) : '');

        //组织数据
        $sdf = array(
            'order_bn'      => $order_bn,
            'invoice_attr'  => $invoice_attr,
            'invoice_kind'  => $invoice_kind,
            'company_title' => $company_title,
            'tax_no'        => $tax_no,
            'invoice_type'  => $invoice_type,
            'extend_arg'    => $extend_arg,
        );

        return $sdf;
    }

    protected $electronKind = array(0, 4);
    protected $specialKind = array(2, 3, 5);

    protected function _formatMessagePush($params)
    {
        #淘宝 0=电子发票，1=纸质发票，2=专票，3=电子专用发票，4=全电普通发票，5=全电专用发票
        #ERP 1=电子发票，2=纸质发票，3=专票
        $invoice_kind = '2';
        if(isset($params['invoice_kind'])) {
            if(in_array($params['invoice_kind'], $this->electronKind)) {
                $invoice_kind = '1';
            }
            if(in_array($params['invoice_kind'], $this->specialKind)) {
                $invoice_kind = '3';
            }
        }
        $sdf = array(
            'tid'          => $params['platform_tid'],
            'tax_title'    => $params['payer_name'],
            'register_no'  => $params['payer_register_no'] == 'None' ? '' : $params['payer_register_no'],
            'invoice_kind' => $invoice_kind,
            'title_type'   => $params['business_type'], #抬头类型，0=个人，1=企业
        );
        return $sdf;
    }

    /**
     * message_push
     * @param mixed $params 参数
     * @return mixed 返回值
     */
    public function message_push($params)
    {
        $this->__apilog['title'] = '接受(' . $this->__channelObj->channel['name'] . ')发票信息';
        $sdf                     = $this->_formatMessagePush($params);
        if (empty($sdf)) {
            $this->__apilog['result']['msg'] = '不接受发票信息';
            return false;
        }
        $sdf['is_status'] = '0';
        $order_bn                      = $sdf['tid'];
        $shop_id                       = $this->__channelObj->channel['shop_id'];
        $this->__apilog['original_bn'] = $order_bn;

        $field   = 'order_id,order_bn,ship_name,ship_area,ship_addr,ship_mobile,ship_tel,shop_type,is_tax';
        $tgOrder = $this->getOrder($field, $shop_id, $order_bn);

        if ($tgOrder) {
            $oldInvoice = app::get('ome')->model('order_invoice')->db_dump(array('order_id' => $tgOrder['order_id']));
            if ($oldInvoice) {
//                if ($oldInvoice['register_no']) {
//                    $this->__apilog['result']['msg'] = '已经存在发票信息';
//                    return false;
//                } else {
                    $sdf['old_invoice'] = $oldInvoice;
//                }
            }
            $sdf['order_info'] = $tgOrder;
            return $sdf;
        } else {
            $this->__apilog['result']['msg'] = '缺少订单';
            return false;
        }
    }
}
