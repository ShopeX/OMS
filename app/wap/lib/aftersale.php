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
 * 门店售后退换货类
 * 20170526
 * @author: wangjianjun@shopex.cn
 */
class wap_aftersale{
    
    function __construct($app){
        $this->app = $app;
    }

    //获取并格式化退换货单的数据（包括主表和明细表）
    /**
     * 获取List
     * @param mixed $filter filter
     * @param mixed $offset offset
     * @param mixed $limit limit
     * @param mixed $orderby orderby
     * @return mixed 返回结果
     */

    public function getList($filter, $offset=0, $limit=1, $orderby=''){
        $mdl_wap_return = $this->app->model("return");
        $mdl_wap_return_items = $this->app->model("return_items");
        $materialLib = kernel::single('material_basic_material');
        $bmeMdl = app::get("material")->model("basic_material_ext");
        //主表数据
        $dataList = $mdl_wap_return->getList("*", $filter, $offset, $limit, $orderby);
        if (empty($dataList)){
            return array();
        }
        
        //统一获取售后申请单号aftersale_bn order_bn 以及物流信息、对应门店的地址
        $aftersale_ids = array();
        $order_ids= array();
        $use_branch_ids = array();
        $original_reship_bns = array();
        foreach ($dataList as $var_d){
            if($var_d["aftersale_id"] && !in_array($var_d["aftersale_id"],$aftersale_ids)){
                $aftersale_ids[] = $var_d["aftersale_id"];
            }
            if(!in_array($var_d["order_id"],$order_ids)){
                $order_ids[] = $var_d["order_id"];
            }
            if($var_d["original_reship_bn"] && !in_array($var_d["original_reship_bn"], $original_reship_bns)){
                $original_reship_bns[] = $var_d["original_reship_bn"];
            }
            //如果是换货按照changebrand_id为准
            $use_branch_id = $var_d["branch_id"];
            if($var_d["return_type"] == "change" && $var_d["changebranch_id"]){
                $use_branch_id = $var_d["changebranch_id"];
            }
            if ($use_branch_id && !in_array($use_branch_id,$use_branch_ids)){
                $use_branch_ids[] = $use_branch_id;
            }
        }
        if (!empty($aftersale_ids)){
            $mdl_ome_return_product = app::get('ome')->model("return_product");
            $rs_return_product = $mdl_ome_return_product->getList("return_id,return_bn",array("return_id|in"=>$aftersale_ids));
            $rl_return_id_bn = array();
            foreach ($rs_return_product as $var_r_p){
                $rl_return_id_bn[$var_r_p["return_id"]] = $var_r_p["return_bn"];
            }
        }
        if (!empty($order_ids)){
            $mdl_ome_orders = app::get('ome')->model("orders");
            $mdl_ome_dly_corp = app::get('ome')->model("dly_corp");
            $rs_order = $mdl_ome_orders->getList("order_id,order_bn,logi_id",array("order_id|in"=>$order_ids));
            $logi_ids = array();
            foreach ($rs_order as $var_o_i){
                if(!in_array($var_o_i["logi_id"],$logi_ids)){
                    $logi_ids[] = $var_o_i["logi_id"];
                }
            }
            $rs_dly_info = $mdl_ome_dly_corp->getList("corp_id,type,name",array("corp_id|in"=>$logi_ids));
            $rl_logi_id_info = array();
            foreach ($rs_dly_info as $var_dly_info){
                $rl_logi_id_info[$var_dly_info["corp_id"]] = $var_dly_info;
            }
            $rl_order_id_info = array();
            foreach ($rs_order as $var_o){
                $rl_order_id_info[$var_o["order_id"]]["order_bn"] = $var_o["order_bn"];
                $rl_order_id_info[$var_o["order_id"]]["dly_info"] = $rl_logi_id_info[$var_o["logi_id"]];
            }
        }
        if(!empty($use_branch_ids)){
            $mdl_o2o_store = app::get('o2o')->model("store");
            $rs_o2o_store = $mdl_o2o_store->getList("branch_id,addr",array("branch_id|in"=>$use_branch_ids));
            $rl_branch_addr = array();
            foreach ($rs_o2o_store as $var_o_s){
                $rl_branch_addr[$var_o_s["branch_id"]] = $var_o_s["addr"];
            }
        }
        $rl_reship_return_logi_no = array();
        if (!empty($original_reship_bns)) {
            $rs_ome_reship = app::get('ome')->model('reship')->getList(
                'reship_bn,return_logi_no',
                array('reship_bn|in' => $original_reship_bns)
            );
            foreach ($rs_ome_reship as $var_reship) {
                $rl_reship_return_logi_no[$var_reship['reship_bn']] = $var_reship['return_logi_no'];
            }
        }
        
        $status_arr = $this->status_arr();
        
        foreach ($dataList as &$var_data){
            $returns = $this->getReturns($var_data['aftersale_id']);


            $var_data['returns'] = $returns;

            $shops = $this->getshops($var_data['shop_id']);
            $var_data['shops'] = $shops;

            //获取明细表数据 （根据return_type分别处理） 退换的总商品数 及金额
            $current_return_items = $mdl_wap_return_items->getList("*",array("return_id"=>$var_data["return_id"]));
            $return_items_data = array();
            $change_items_data = array();
            foreach ($current_return_items as $k=>$var_r_i){

                $bmeInfo = $bmeMdl->dump(array("bm_id" => $var_r_i['product_id']), "unit");
                $current_return_items[$k]['unit'] =$bmeInfo['unit'];
                $mainImage = $materialLib->getBasicMaterialMainImage($var_r_i['product_id']);

                if ($mainImage) {
                    $current_return_items[$k]['goods_img_url'] = $mainImage['full_url'];
                }
                if($var_r_i["return_type"] == "return"){
                    $return_items_data[] = $var_r_i;
                }
                if($var_r_i["return_type"] == "change"){
                    $change_items_data[] = $var_r_i;
                }
            }
            $displayContext = array(
                'original_reship_bn' => $var_data['original_reship_bn'],
                'return_id'          => $var_data['return_id'],
                'order_id'           => $var_data['order_id'],
                'wap_return_status'  => $var_data['status'],
            );
            $var_data['return_product_items'] = kernel::single('wap_delivery')->enrichItemsByBnByService(
                $current_return_items,
                'bn',
                $displayContext
            );
            if(!empty($return_items_data)){//退
                $var_data["return_items_data"] = $return_items_data;
                $item_num_return = 0; 
                foreach ($var_data["return_items_data"] as $var_d_i){
                    if($var_d_i["return_type"] == "return"){
                        $item_num_return+= $var_d_i["num"];
                    }
                }
                $var_data["item_num_return"] = $item_num_return;
            }
            if(!empty($change_items_data)){//换
                $var_data["change_items_data"] = $change_items_data;
                $item_num_change = 0; 
                foreach ($var_data["change_items_data"] as $var_d_i){
                    if($var_d_i["return_type"] == "change"){
                        $item_num_change += $var_d_i["num"];
                    }
                }
                $var_data["item_num_change"] = $item_num_change;
            }
            //获取status_text
            $var_data["status_text"] = $status_arr[$var_data["status"]];
            //获取售后申请单bn
            $var_data["aftersale_bn"] = $rl_return_id_bn[$var_data["aftersale_id"]];
            //获取订单bn
            $var_data["order_bn"] = $rl_order_id_info[$var_data["order_id"]]["order_bn"];
            //获取物流信息
            $var_data["dly_info"] = $rl_order_id_info[$var_data["order_id"]]["dly_info"];
            //退回物流单号：wap.return.original_reship_bn → ome.reship.return_logi_no
            $var_data["return_logi_no"] = $rl_reship_return_logi_no[$var_data["original_reship_bn"]] ?? '';
            //对应门店仓地址addr 如果是换货按照changebrand_id为准
            $current_branch_id = $var_data["branch_id"];
            if($var_data["return_type"] == "change" && $var_data["changebranch_id"]){
                $current_branch_id= $var_data["changebranch_id"];
            }
            $var_data["current_store_addr"] = $rl_branch_addr[$current_branch_id];
            $var_data["reship_href"] = app::get('wap')->router()->gen_url(array('ctl'=>'aftersale_returnproduct','act'=>'addReship?return_id='.$var_data["return_id"].'&reship_id='.$reship_list[$var_data['return_id']]['reship_id']), true);
        }
        unset($var_data);
     
        return $dataList;
    }
    
    //退换货单状态映射数组
    /**
     * status_arr
     * @return mixed 返回值
     */
    public function status_arr(){
        return array("1"=>"待处理","2"=>"已拒绝","3"=>"已处理");
    }

    //换货订单生成、付款、生成发货单、发货流程
    /**
     * 创建_order_pay_store_delivery
     * @param mixed $rs_ome_reship rs_ome_reship
     * @return mixed 返回值
     */
    public function create_order_pay_store_delivery($rs_ome_reship)
    {
        $res = $this->_create_order_pay_store_delivery($rs_ome_reship);

        return $res;
    }
    
    private function _create_order_pay_store_delivery($rs_ome_reship){
        //开启事务
        kernel::database()->beginTransaction();
        
        //生成订单
        $mdl_ome_reship = app::get('ome')->model('reship');
        $change_order_sdf = $mdl_ome_reship->create_order($rs_ome_reship);
        if (!$change_order_sdf){//生成订单失败
            kernel::database()->rollBack();
            return false;
        }
        
        //支付操作
        $mdl_ome_orders = app::get('ome')->model('orders');
        $rs_orders = $mdl_ome_orders->dump(array("order_id"=>$rs_ome_reship["order_id"]),"order_bn,logi_id");
        $order_pay = array(
                        'order_id' => $change_order_sdf['order_id'],
                        'shop_id' => $rs_ome_reship['shop_id'],
                        'pay_status' => '1',
                        'pay_money' => $change_order_sdf['total_amount'],
                        'currency' => 'CNY',
                        'reship_order_bn' => $rs_orders['order_bn'],
        );
        $is_archive = kernel::single('archive_order')->is_archive($rs_ome_reship['source']);
        if ($is_archive){
            $order['archive'] = '1';
        }
        kernel::single('ome_reship')->payChangeOrder($order_pay);
        
        //生成发货单
        $orders = array($change_order_sdf['order_id']);
        //收货人信息
        $consignee = array (
                "name" => $change_order_sdf["consignee"]["name"],
                "area" => $change_order_sdf["consignee"]["area"],
                'addr' => $change_order_sdf["consignee"]["addr"],
                'telephone' => $change_order_sdf["consignee"]["telephone"],
                'mobile' => $change_order_sdf["consignee"]["mobile"],
                'r_time' => $change_order_sdf["consignee"]["r_time"],
                'zip' => $change_order_sdf["consignee"]["zip"],
                'email' => $change_order_sdf["consignee"]["email"],
                'memo' => '',
        );
        $consignee["branch_id"] = $rs_ome_reship["changebranch_id"]; //相关换货门店仓id
        $logiId = $rs_orders["logi_id"]; //已原始订单完成发货的logi_id为依据(dly_corp:o2o_ship或者o2o_pickup)
        $splitting_product= ''; //无拆单
        $combineObj = kernel::single('omeauto_auto_combine');
        $result = $combineObj->mkDelivery($orders, $consignee, $logiId, $splitting_product, $errmsg, []);
        if (!$result){
            kernel::database()->rollBack();
            return false;
        }
        #全链路 已财审（ome_ctl_admin_order finish_combine审单确认生成发货单有这步）
        kernel::single('ome_event_trigger_shop_order')->order_message_produce($orders,'to_wms');
        
        //发货(接单)
        $mdl_wap_delivery = app::get('wap')->model('delivery');
        $delivery_info = $mdl_wap_delivery->dump(array('order_bn'=>$change_order_sdf["order_bn"]), '*');
        if(empty($delivery_info)){
            kernel::database()->rollBack();
            return false;
        }
        $delivery_params = array_merge(array('delivery_id'=>$delivery_info["delivery_id"]), $delivery_info);
        $dly_process_lib = kernel::single('wap_delivery_process');
        $wap_delivery_lib = kernel::single('wap_delivery');
        if($dly_process_lib->accept($delivery_params)){ //接单成功
            //task任务更新统计数据
            $wap_delivery_lib->taskmgr_statistic('confirm');
        }else{ //接单失败
            kernel::database()->rollBack();
            return false;
        }
        
        //发货
        if($dly_process_lib->consign($delivery_info)){ //发货成功
            $wap_delivery_lib->taskmgr_statistic('consign');
        }else{ //发货失败
            kernel::database()->rollBack();
            return false;
        }
        
        //最终完成
        kernel::database()->commit();
        return $change_order_sdf;
    }
   
    
    /**
     * 获取Returns
     * @param mixed $aftersale_id ID
     * @return mixed 返回结果
     */
    public function getReturns($aftersale_id){
        $returnMdl = app::get('ome')->model('return_product');
        $returns = $returnMdl->dump(array('return_id'=>$aftersale_id),'return_bn,add_time');
        $returns['add_time'] = date('Y-m-d H:i:s',$returns['add_time']);

        return $returns;
    }

    /**
     * 获取shops
     * @param mixed $shop_id ID
     * @return mixed 返回结果
     */
    public function getshops($shop_id){
        $shopMdl = app::get("ome")->model("shop");
        $shops = $shopMdl->dump($shop_id, "name,shop_type");
        $shops['shop_name'] = $shops['name'];
        $shops['shop_type_name'] = ome_shop_type::shop_name($shops['shop_type']);

        return $shops;

    }



}
