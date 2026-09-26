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

class invoice_func{
    public function channels($channel_type) {
        $channels = array (
            'taobao'   => array ('code' => 'taobao', 'name' => '阿里淘宝'),
            //            'bw'            => array ('code' => 'bw', 'name' => '百望股份'),
            'jinshui'  => array ('code' => 'jinshui', 'name' => '金税科技'),
            'chinaums' => array ('code' => 'chinaums', 'name' => '银联金四'),
            'baiwang'  => array ('code' => 'baiwang', 'name' => '百望金四'),
            'huifu'    => array ('code' => 'huifu', 'name' => '汇付发票'),
            'piaoyitong' => array ('code' => 'piaoyitong', 'name' => '票易通'),
        );

        if(!empty($channel_type)) {
            return $channels[$channel_type];
        }

        return $channels;
    }
    #设备类型
    public function invoice_eqpttype($channel_type='bw'){
        $data = array(
            'bw'=>array('0'=>'税控服务','1'=>'税控盘')
        );
        return $data[$channel_type];
    }
    public function  invoice_mode(){
       return array(
         0=>'纸质发票',
         1=>'电子发票',
       );
    } 
    #根据店铺获取相关配置
    public function get_order_setting($shop_id,$mode = 1){
       #电子发票配置
        $sql = "select * from sdb_invoice_order_setting  where  shopids like '%" . $shop_id . "%' and mode='".$mode."'";
        $rs = kernel::database()->select($sql);
        return $rs;
    }           
    #获取开票金额
    function get_invoice_amount(&$rs_invoice_order){
       #开票金额 = 合计金额 + 税金   （合计金额，为兼容老数据，就是订单总额；税金，根据税率，在生成发票单据时，已经生成）
       $invoice_amount = $rs_invoice_order['amount'] + $rs_invoice_order['cost_tax'];
       return $invoice_amount;
    }   
    /*
     * 进入ERP，要开发票订单的订单总额，实际就是已经含税了的开票金额。而税金，需要ERP自行推算，计算步骤如下：
     * 1、先计算不含税商品合计，计算方程:(含税)开票金额 = (不含税)商品合计 + (不含税)商品合计 * 税率 = (不含税)商品合计*( 1 + 税率)
     *   （不含税）商品合计公式 :(不含税)商品合计 = (含税)开票金额  / ( 1 + 税率)
     * 2、税金 = (含税)开票金额 - (不含税)商品合计
     * */
    function get_invoice_cost_tax($invoice_amount,$taxt_rate){
         $sum_price = $this->get_invoice_sum_price($invoice_amount, $taxt_rate);
         $cost_tax = $invoice_amount - $sum_price;
         return $cost_tax; 
    }
    #不含税合计金额（erp没有这个字段，需要自己计算）
    function get_invoice_sum_price($invoice_amount,$taxt_rate){
        $taxt_rate = $taxt_rate / 100;
        $sum_price = round($invoice_amount/(1+$taxt_rate),2);#保留2位
        return $sum_price;
    }
    #检查绑定
    // function check_bind($to_node_id,$to_node_type,$shop_name){
    //     $baiwang_bind_status = null;
    //     base_kvstore::instance('invoice/bind/baiwang')->fetch('invoice_bind_baiwang',$baiwang_bind_status);
    //     if(!$baiwang_bind_status){
    //         $rs =  kernel::single('base_thirdbind')->bind($to_node_id,$to_node_type,$shop_name);
    //         if($rs['rsp'] == 'succ'){
    //             base_kvstore::instance('invoice/bind/baiwang')->store('invoice_bind_baiwang',true);
    //             base_kvstore::instance('invoice/bind/baiwang')->fetch('invoice_bind_baiwang',$baiwang_bind_status);
    //         }
    //     }
    //     return $baiwang_bind_status?true:false;
    // }
     /*
     * 电子发票自动开蓝票处理
     * $order_id 订单id
     * $auto_bill_timer 自动开票时间点  1 商家发货后   2 客户签收后
     */
    public function do_einvoice_bill($order_id,$auto_bill_timer){
        $mdlOmeOrders = app::get('ome')->model('orders');
        $rs_orders = $mdlOmeOrders->dump($order_id,"shop_id,status,ship_status");
        //自动开票时间点 是 商家发货后 存在拆单 故必须判断订单是 已完成 已发货的
        if($auto_bill_timer == "1"){
            if($rs_orders["status"] != "finish" || $rs_orders["ship_status"] != "1"){
                return false;
            }
        }
        //shop_id必要参数
        if(!$rs_orders["shop_id"]){
            return false;
        }
        //获取shop_id对应的自动开票配置
        $mdlInSetShopIdRel = app::get('invoice')->model('setting_shopid_relation');
        $rs_rel = $mdlInSetShopIdRel->dump(array("shop_id"=>$rs_orders["shop_id"]));
        if (empty($rs_rel)){
            return false;
        }
        $mdlInOrderSet = app::get('invoice')->model('order_setting');
        //电子发票配置 启用状态 自动开票开启 开票时间点
        $rs_set = $mdlInOrderSet->dump(array("sid"=>$rs_rel["sid"],"mode"=>"1","auto_bill"=>"1","auto_bill_timer"=>$auto_bill_timer,"status"=>"true"));
        if (empty($rs_set)){
            return false;
        }
        $mdlInOrder = app::get('invoice')->model('order');
        //获取订单对应的电子开票信息数据的主键id
        $arr_filter = array(
            "is_status" => "0",
            "mode" => "1",
            "sync" => array("0","2"),
            "order_id" => $order_id,
        );
        $rs_invoice_order = $mdlInOrder->dump($arr_filter);
        if (empty($rs_invoice_order)){
            return false;
        }
        $arr_billing = array(
            "id" => $rs_invoice_order["id"],
            "order_id" => $order_id,
        );
        kernel::single('invoice_process')->billing($arr_billing);
    }
    
    /**
     * 获取销售物料开票信息
     * @Author: xueding
     * @Vsersion: 2023/5/30 下午4:01
     * @param $sales_bns
     * @return array
     */
    public static function getSalesMaterialInfo($sales_bns)
    {
        $smMdl = app::get('material')->model('sales_material');
        $smExtMdl = app::get('material')->model('sales_material_ext');
        $sales_list = $smMdl->getList('sm_id,sales_material_bn,tax_rate,tax_code,tax_name,sales_material_type',['sales_material_bn'=>$sales_bns]);
        $data = array();
        if ($sales_list) {
            $data = array_column($sales_list,null,'sales_material_bn');
            $smIds = array_column($sales_list,'sm_id');
            $smExtList = $smExtMdl->getList('*',['sm_id'=>$smIds]);
            $smExtList = array_column($smExtList,null,'sm_id');
            foreach ($data as $key => $val) {
                if (isset($smExtList[$val['sm_id']])) {
                    $data[$key] = array_merge($val,$smExtList[$val['sm_id']]);
                    $data[$key]['material_basic_cost_total'] = 0;
                    if ($data[$key]['sales_material_type'] == '3') {
                        $basic_list = kernel::single('material_sales_material')->getBasicMBySalesMId($val['sm_id']);
                        if ($basic_list) {
                            $data[$key]['material_basic_cost_total'] = array_sum(array_column($basic_list,'cost'));
                        }
                    }
                }
            }
        }
        
        return $data;
    }

    /**
     * 获取销售物料开票信息
     * @Author: xueding
     * @Vsersion: 2023/5/30 下午4:01
     * @param $sales_bns
     * @return array
     */
    public static function getBasicMaterialInfo($basic_bns)
    {
        $bsMdl = app::get('material')->model('basic_material');
        $bsExtMdl = app::get('material')->model('basic_material_ext');
        $basic_list = $bsMdl->getList('bm_id,material_bn,tax_rate,tax_code,tax_name',['material_bn'=>$basic_bns]);
        $data = array();
        if ($basic_list) {
            $data = array_column($basic_list,null,'material_bn');
            $bmIds = array_column($basic_list,'bm_id');
            $bmExtList = $bsExtMdl->getList('unit,bm_id',['bm_id'=>$bmIds]);
            $bmExtList = array_column($bmExtList,null,'bm_id');
            foreach ($data as $key => $val) {
                if (isset($bmExtList[$val['bm_id']])) {
                    $data[$key] = array_merge($val,$bmExtList[$val['bm_id']]);
                }
            }
        }

        return $data;
    }
    
    /**
     * 判断当前订单是否可操作
     * @Author: xueding
     * @Vsersion: 2023/6/6 上午10:38
     * @param $id
     * @return bool
     */
    public function getInvoiceMakeStatus($id)
    {
        $invoiceMdl = app::get('invoice')->model('order');
        $invoiceItemMdl = app::get('invoice')->model('order_items');
        $is_make = $invoiceItemMdl->count(['id'=>$id,'item_is_make_invoice'=>'0']);
        if (!$is_make) {
            $invoiceMdl->update(['is_make_invoice'=>'1'],['id'=>$id]);
        }
    }
}