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

class finance_iostocksales
{
    public function do_iostock_sales_data($iostock_sales_data = array(), $delivery_time = null)
    {
        if (empty($iostock_sales_data)) {
            return;
        }

        $init_time = app::get('finance')->getConf('finance_setting_init_time');
        if (!$init_time) {
            return;
        }

        $sdf = $this->format_finance_data($iostock_sales_data['sales']);
        $finance_ar = kernel::single("finance_ar");
        $res        = $finance_ar->do_save($sdf);
    }

    //售后生成负销售单时调用,如果有退货有换货iostock_sales_data_ar为多张销售单
    public function do_sales_data($iostock_sales_data_arr = array())
    {
        foreach ((array) $iostock_sales_data_arr as $key => $val) {
            $val['sales']['iostock_type'] = $key;
            $this->do_iostock_sales_data($val);
        }
    }
    /*格式化账单方法接收参数*/
    public function format_finance_data($data = array())
    {
        if (empty($data)) {
            return array();
        }
    
        //format
        $data['sale_amount'] = (empty($data['sale_amount']) ? 0 : $data['sale_amount']);
        $data['delivery_cost'] = (empty($data['delivery_cost']) ? 0 : $data['delivery_cost']);
        $data['actually_amount'] = (empty($data['actually_amount']) ? 0 : $data['actually_amount']);
        $data['platform_amount'] = (empty($data['platform_amount']) ? 0 : $data['platform_amount']);
        
        //sdf
        $return_sdf                    = array();
        $return_sdf['trade_time']      = $data['sale_time']; //账单日期
        $return_sdf['delivery_time']   = $data['delivery_time']; //账单日期
        $return_sdf['member']          = $this->get_member_uname($data['member_id']); //会员/客户
        $return_sdf['type']            = $this->get_sales_type($data['iostock_type']); //业务类型 todo
        $return_sdf['order_bn']        = $data['order_bn']; //业务单据bn
        $return_sdf['relate_order_bn'] = $data['relate_order_bn']; //关联订单bn，专指售后换货
        $return_sdf['channel_id']      = $data['sales_items'][0]['shop_id']; //渠道ID shop_id todo
        $return_sdf['channel_name']    = $data['sales_items'][0]['shop_name']; //渠道名称 shop_name
        $return_sdf['sale_money']      = $data['sale_amount']; //商品成交金额
        $return_sdf['fee_money']       = $data['delivery_cost']; //运费
        $return_sdf['actually_money']  = $data['actually_amount']; //客户实付
        $return_sdf['platform_amount'] = $data['platform_amount']; //平台补贴
        $return_sdf['money']           = $return_sdf['sale_money'] + $return_sdf['fee_money']; //商品成交金额+运费
        $return_sdf['serial_number']   = $data['sale_bn']; //默认销售单据号，没有自定义规则  todo
        $return_sdf['charge_status']   = 1; //默认已记账
        $return_sdf['charge_time']     = time();
        $return_sdf['unique_id']       = $return_sdf['serial_number'];
        $return_sdf['memo']            = $data['sale_time'];
        $return_sdf['items']           = $this->_get_items($data['sales_items']);
        if($data['delivery_cost'] > 0) {
            // 明细重新分摊价格
            $return_sdf['items'] = kernel::single('ome_order')->calculate_part_porth($return_sdf['items'],array (
                'part_total'  => $return_sdf['actually_money'],
                'part_field'  => 'actually_money',
                'porth_field' => 'money',
            ));
        }
        return $return_sdf;
    }

    public function _get_items($sale_items = array())
    {
        $return_sale_itmes = array();
        foreach ((array) $sale_items as $k => $val) {
            $aTmp                = array();
            $aTmp['bn']          = $val['bn'];
            $aTmp['name']        = $val['name'];
            $aTmp['num']         = abs($val['nums']);
            $aTmp['money']       = $val['sales_amount'];
            $aTmp['actually_money']       = $val['actually_amount'];
            $aTmp['platform_amount']      = $val['platform_amount'];
            $return_sale_itmes[] = $aTmp;
        }
        return $return_sale_itmes;
    }
    /*会员用户名*/

    public function get_member_uname($member_id = null)
    {
        if (empty($member_id)) {
            return '';
        }

        $member_obj  = app::get('ome')->model("members");
        $member_data = $member_obj->getRow($member_id, 'uname');
        return $member_data['uname'];
    }

    /*订单的关联订单号*/

    public function get_relate_order_bn($order_id = null)
    {
        if (empty($order_id)) {
            return;
        }

        $orders_mdl = app::get("ome")->model("orders");
        $order_row  = $orders_mdl->db->selectrow("SELECT relate_order_bn FROM `sdb_ome_orders` where order_id=" . intval($order_id));
        return $order_row['relate_order_bn'];
    }

    /*获取销售单类型*/

    public function get_sales_type($type = null)
    {
        $sales_type_name = array(
            'SALE_STORAGE'      => '销售出库',
            'RETURN_STORAGE'    => '销售退货',
            'RE_STORAGE'        => '销售换货',
            'SALE_REFUND'       => '销售退款',
            'JIT_STOCKOUT'      => 'JIT出库',
            'JIT_STOCKIN'       => 'JIT退供',
            'JDZY_STOCKOUT'     => '京东自营销售出库',
            'JDZY_STOCKIN'      => '京东自营销售退货',
            'COMPENSATE'        => '直赔单',
        );
        if (empty($type)) {
            return $sales_type_name['SALE_STORAGE'];
        } else {
            return $sales_type_name[$type];
        }

    }
}
