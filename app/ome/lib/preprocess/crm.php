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

class ome_preprocess_crm{
    public $fenxiaowang = 'shopex_b2b';//分销王的店铺类型,需要检测订单类型
    private static  $__crm_gifts = array();
    
    #$type是重新强制请求CRM赠品的标示
    public function process($order_id,&$msg,$type=false){

        $res = $this->_process($order_id, $msg, $type);
        
        return $res;
    }

    public function _process($order_id,&$msg,$type=false){
        #获取crm基本配置
        $crm_cfg = app::get('crm')->getConf('crm.setting.cfg');
        #如果没有开启crm应用，程序返回
        //if(empty($crm_cfg)){
        //    return false;
        //}

        $operationLogObj = app::get('ome')->model('operation_log');
        $Obj_preprocess = app::get('ome')->model('order_preprocess');
        $obj_crm_rpc = kernel::single('crm_rpc_gift');
        
        $opinfo = kernel::single('ome_func')->getDesktopUser();
        if(!$order_id){
            return false;
        }

        #检测是否开启赠品
        //if($crm_cfg['gift'] != 'on'){
        //    $msg = '订单没有开启CRM赠品';
        //    $operationLogObj->write_log('order_preprocess@ome',$order_id, $msg,time(),$opinfo);
        //    return false;
        //}

        $_rs = $Obj_preprocess->getList('preprocess_order_id',array('preprocess_order_id'=>$order_id,'preprocess_status'=>'1','preprocess_type'=>'crm'));
        #不为空，说明已经获取过CRM赠品
        if(!empty($_rs)){
            return true;
        }

        $orderObj = app::get('ome')->model('orders');
        $obj_channel = app::get('channel')->model('channel');
        
        #根据订单号，找到当前订单的相关信息
        $order_info = $obj_channel->getOrderInfo($order_id);
        if( in_array($order_info['process_status'],array('splitting','splited'))){
            return true;
        }
        if(!empty($order_info['relate_order_bn'])){
            return true;
        }
        
        //补发订单
        if ($order_info['order_type'] == 'bufa') {
            $msg = '补发订单不需要执行CRM赠品';
            return true;
        }
        
        if( in_array($order_info['shop_type'],array('taobao'))
                && $order_info['createway'] == 'matrix'
                && $order_info['order_source'] == 'maochao'){
            $msg = '猫超国际不参与赠品规则';
            $operationLogObj->write_log('order_preprocess@ome',$order_id, $msg);
            return true;
        }
        #获取赠品规则的订单明细
        $order_item_info = $this->get_order_item_info($order_id);
        #获取赠品规则的订单对象
        $order_obj_info = $this->get_order_obj_info($order_id);
       
        #打标的状态
        $status = $order_info['abnormal_status'] | ome_preprocess_const::__HASCRMGIFT_CODE;
        
        $order_bn    = $order_info['order_bn'];#单号
        $shop_type    = $order_info['shop_type'];#店铺类型
        $shop_id       = $order_info['shop_id'];#店铺节点
        $receiver_name = $order_info['ship_name'];#收件人姓名
        $buyer_nick    = $order_info['uname'];#会员名
        $mobile        = $order_info['ship_mobile'] ? $order_info['ship_mobile'] : '';
        $tel           = $order_info['ship_tel'] ? $order_info['ship_tel'] : '';
        $ship_area     = $order_info['ship_area'];
        $ship_area_arr = '';
        if ($ship_area && is_string($ship_area)) {
            kernel::single('ome_func')->split_area($order_info['ship_area']);
            $ship_area_arr = $order_info['ship_area'];
        }
        $payed = $order_info['payed'] ?  $order_info['payed'] : 0;#付款金额
        $pay_time = $order_info['paytime'] ? $order_info['paytime'] : 0;#付款时间
        $isCod = $order_info['is_cod'] == 'true' ? 1 : 0;

        #检测当前订单的来源店铺否开启赠品
        //if(empty($crm_cfg['name'][$shop_id])){
        //   return false;
        //}

        $shop_shop_type = $this->shopex_shop_type();
        if($shop_shop_type[$shop_type]){
            $msg = 'shopex类型店铺，不支持CRM赠品处理';
            $operationLogObj->write_log('order_preprocess@ome',$order_id, $msg,time(),$opinfo);
            return false;
        }

        #检测完毕,调用赠品接口,获取赠品规则
        $params = array(
            'buyer_nick' => $buyer_nick,
            'receiver_name'=> $receiver_name,
            'mobile' => $mobile,
            'tel' => $tel,
            'shop_id' => $shop_id,
            'order_id' => $order_id,
            'order_bn' => $order_bn,
            'province' => $ship_area_arr[0],
            'city' => $ship_area_arr[1],
            'district' => $ship_area_arr[2],
            'total_amount' => $order_info['total_amount'],
            'payed' => $payed,
            'createtime' => $order_info['createtime'],
            'pay_time' => $pay_time,
            'is_cod' => $isCod,
            'pay_status' => $order_info['pay_status'],
            'order_type' => $order_info['order_type'],
            'items' => $order_item_info,
            'objects' => $order_obj_info,
            'mark_text' => $order_info['mark_text'], //商家备注
            'custom_mark' => $order_info['custom_mark'], //客户备注
        );

        #强制重新请求的标示
        if($type){
            $params['is_send_gift'] = 1;
        }else{
            $params['is_send_gift'] = 0;
        }
        
        #根据店铺节点、收件人、会员名、手机，获取赠品规则
        #如果绑定了crm，且crm赠品规则开启时 则调用crm赠品接口
        if(!empty($crm_cfg) && ($crm_cfg['gift'] == 'on')){
            $gift_rule = $obj_crm_rpc->getGiftRule($params);   // 请求crm接口
        } else {
            //两种情况：1.如果绑定crm，但是crm赠品规则未开启  2.如果没绑定crm，但是本地规则开启了 都直接调用本地的规则
            $gift_on_off = app::get('ome')->getConf('gift.on_off.cfg');
            if($gift_on_off == 'on'){
                $gift_rule = $obj_crm_rpc->getFormatRst($params);      // 调用本地方法
            } else {
                return true;
            }
        }
        
        //result
        if($gift_rule['result'] == 'succ'){
            #赠品数据为空
            if(empty($gift_rule['data'])){
                $msg = '订单CRM赠品数据为空';
                $operationLogObj->write_log('order_preprocess@ome',$order_id, $msg,time(),$opinfo);
                return false;
            }
            
            if(!isset($gift_rule['data']['order_bn']) && isset($gift_rule['msg'])){
                if($gift_rule['msg']){
                    $operationLogObj->write_log('order_preprocess@ome',$order_id, $gift_rule['msg'],time(),$opinfo);
                }
                
                return false;
            }
            
            #CRM返回订单与发送赠品请求的订单号不一致
            if($gift_rule['data']['order_bn'] != $order_bn){
                $msg = 'CRM返回订单号与请求单号不一致';
                $operationLogObj->write_log('order_preprocess@ome',$order_id, $msg,time(),$opinfo);
                return false;
            }
            
            #订单天生没有赠品，则程序返回
            if(empty($gift_rule['data']['gifts']) && empty($gift_rule['data']['bufaRules'])){
                $msg = '订单CRM赠品为空';
                $operationLogObj->write_log('order_preprocess@ome',$order_id, $msg,time(),$opinfo);
                return false;
            }
        }elseif($gift_rule['result'] == 'fail'){
            $msg = '订单CRM预处理:'.$gift_rule['msg'];
            if(($crm_cfg['gift'] != 'on') && !isset($crm_cfg['error'])){
                $dealways = app::get('ome')->getConf('gift.error.ways');
                #不添加赠品，继续审单发货
                if(empty($dealways) || ($dealways == 'off')){
                    $operationLogObj->write_log('order_preprocess@ome',$order_id, $msg,time(),$opinfo);
                    return false;
                }elseif($dealways == 'on'){
                    #打标提醒，人工处理
                    $orderObj->update(array('abnormal_status'=>$status, 'auto_status' => 32768),array('order_id'=>$order_id));
                    $operationLogObj->write_log('order_preprocess@ome',$order_id, $msg,time(),$opinfo);
                    #打标与记日志完成后，程序返回
                    return false;
                }
            } else {
                #不添加赠品，继续审单发货
                if($crm_cfg['error'] == 'off' ){
                    $operationLogObj->write_log('order_preprocess@ome',$order_id, $msg,time(),$opinfo);
                    return false;
                }elseif($crm_cfg['error'] == 'on'){
                    #打标提醒，人工处理
                    $orderObj->update(array('abnormal_status'=>$status),array('order_id'=>$order_id));
                    $operationLogObj->write_log('order_preprocess@ome',$order_id, $msg,time(),$opinfo);
                    #打标与记日志完成后，程序返回
                    return false;
                }
            }
        }else{
            $msg = '订单CRM预处理出错';
            #打标提醒，人工处理
            $orderObj->update(array('abnormal_status'=>$status),array('order_id'=>$order_id));
            $operationLogObj->write_log('order_preprocess@ome',$order_id, $msg,time(),$opinfo);
            #打标与记日志完成后，程序返回
            return false;
        }
        
        //[延迟补发]订单完成之后，再创建补发订单赠送赠品
        if($gift_rule['data']['bufaRules'] && $order_info['createway'] == 'matrix'){
            $applyIds = array();
            $bufaRules = $gift_rule['data']['bufaRules'];
            foreach ($bufaRules as $apply_id => $giftRules)
            {
                $applyIds[$apply_id] = $apply_id;
            }
            
            //记录符合条件的赠品应用规则
            $extend_info = json_encode(array('applyIds'=>$applyIds));
            
            //订单打标签,标记为：补发赠品
            $error_msg = '';
            $label_code = 'system_bufa';
            kernel::single('ome_bill_label')->markBillLabel($order_id, '', $label_code, 'order', $error_msg, 0, $extend_info);
            
            //[审单类型]没有符合的赠品,才插入赠送记录
            if(empty($gift_rule['data']['gifts'])){
                $preprocessData = array('preprocess_order_id'=>$order_id,'preprocess_type'=>'crm','preprocess_status'=>'1');
                $Obj_preprocess->insert($preprocessData);
            }
            
            //logs
            $msg = '订单完成后会延迟补发赠品';
            $operationLogObj->write_log('order_preprocess@ome', $order_id, $msg, time(), $opinfo);
        }
        
        //check审核订单时,没有可执行的赠送赠品
        if(empty($gift_rule['data']['gifts'])){
            return true;
        }
        
        #获取crm请求返回的所有gift_id
        $crm_gifts = $gift_rule['data']['gifts'];  #CRM这层结构：'gifts'=> array('bn1'=>1,'bn2'=>2,....)
        $crm_gift_bn = array_keys($crm_gifts);
        
        #根据赠品货号，找到赠品对应的货品信息
        $salesMObj = app::get('material')->model('sales_material');
        $salesM_info = $salesMObj->getList('sm_id,sales_material_name,sales_material_bn,sales_material_type',array('sales_material_bn'=>$crm_gift_bn));
        if(empty($salesM_info)){
            $msg = 'CRM赠品在ERP出错!';
            $operationLogObj->write_log('order_preprocess@ome',$order_id, $msg,time(),$opinfo);
            return false;
        }
        
        #当赠品数据存在,继续检测赠品设置
        if($salesM_info){
            $obj_crm_gift = app::get('crm')->model('gift');
            #根据crm请求返回的gift_id，到淘管赠品数据库中检测赠品货号是否对应
            $_gift_bn = $obj_crm_gift->getList('gift_bn',array('gift_bn'=>$crm_gift_bn,'status'=>1));
            #检测ERP正常赠品
            $erp_gift_bn = array_map('current',$_gift_bn);
            #获取无法在淘管中对应的赠品货号
            $diff_bn = array_diff($crm_gift_bn, $erp_gift_bn);
            #赠品不对应(如ERP已把赠品删除,而CRM没及时同步)
            if(!empty($diff_bn)){
                $is_fail = true;
            }
        }
       
        #库存不足的,放在审单的地方处理,这里不再验证库存   

        #以上验证处理完毕，开始处理相关订单流程
        $orderItemObj  = app::get('ome')->model("order_items");
        $orderObjectObj  = app::get('ome')->model("order_objects");
        $salesMLib = kernel::single('material_sales_material');
        $basicMStockFreezeLib = kernel::single('material_basic_material_stock_freeze');
        $is_update = true;


        // ===============================================================
        // ===============================================================
        // ===============================================================
        // ===============================================================
        // ===============================================================
        // ===============================================================
        // kernel::database()->beginTransaction();
        // try {

        //     $this->_try_catch();
        //     kernel::database()->commit();

        // } catch (BranchStoreFreezeException $e) {

        //     kernel::database()->rollBack();
        //     $redisRoback = $e->getAdditionalInfo();
        //     if ($redisRoback) {
                
        //     }
        // }

        kernel::database()->beginTransaction();

        // 订单已 hold 时，CRM 赠品继承同一时限，避免赠品单独先出单
        // 优先取同单明细 max(estimate_con_time)，再回退订单 timing_confirm（兼容京东预约发货等）
        $giftHoldTime = 0;
        $existObjects = $orderObjectObj->getList('estimate_con_time', array('order_id' => $order_id), 0, -1);
        foreach ($existObjects as $existObj) {
            $ect = intval($existObj['estimate_con_time']);
            if ($ect > $giftHoldTime) {
                $giftHoldTime = $ect;
            }
        }
        
        if ($giftHoldTime <= 0 && !empty($order_info['timing_confirm'])) {
            $giftHoldTime = intval($order_info['timing_confirm']);
        }

        $needFreezeItem = [];
        foreach($salesM_info as $info){

            $obj_type = 'gift';
            $obj_alias = '赠品';
            $item_type = 'gift';

            $tmp_obj = array(
                'order_id' => $order_id,
                'obj_type' => $obj_type,
                'obj_alias' => $obj_alias,
                'shop_goods_id' => '-1',#CRM赠品类型标示
                'goods_id' => $info['sm_id'],
                'bn' => $info['sales_material_bn'],
                'name' => $info['sales_material_name'],
                'price' => 0.00,
                'sale_price' => 0.00,
                'pmt_price' => 0.00,
                'amount' => 0.00,
                'quantity' => $crm_gifts[$info['sales_material_bn']]#CRM返回的赠品数量
            );
            //京东预约发货，赠品进行hold单
            if (kernel::single('ome_order_bool_type')->isBookingDelivery($order_info['order_bool_type']) && $order_info['timing_confirm']) {
                $tmp_obj['estimate_con_time'] = $order_info['timing_confirm'];
            }
            
            // 拼多多订单，赠品使用订单明细上最大的预售发货时间
            if(in_array($order_info['shop_type'], array('pinduoduo'))){
                if ($giftHoldTime > 0) {
                    $tmp_obj['estimate_con_time'] = $giftHoldTime;
                }
            }
            
            if($orderObjectObj->save($tmp_obj)){
                $basicMInfos = $salesMLib->getBasicMBySalesMId($info['sm_id']);
                foreach($basicMInfos as $k => $basicMInfo){
                    $tmp_items = array(
                        'order_id' => $order_id,
                        'obj_id' => $tmp_obj['obj_id'],
                        'shop_goods_id' => '-1',#CRM赠品类型标示
                        'product_id' => $basicMInfo['bm_id'],
                        'shop_product_id' => '-1',#CRM赠品类型标示
                        'bn' => $basicMInfo['material_bn'],
                        'name' => $basicMInfo['material_name'],
                        'cost' => 0.00,
                        'price' => 0.00,
                        'amount' => 0.00,
                        'sale_price'=> 0.00,
                        'pmt_price' => 0.00,
                        'quantity' =>  $crm_gifts[$info['sales_material_bn']]*$basicMInfo['number'],#CRM返回的赠品数量
                        'sendnum' => 0,
                        'item_type' => $item_type,
                    );

                    if($orderItemObj->save($tmp_items)){
                        $tmp_items['goods_id'] = $info['sm_id'];
                        $needFreezeItem[] = $tmp_items;
                    }else{
                        $is_update = false;
                        kernel::database()->rollBack();
                        break 2;
                    }
                }
            }else{
                $is_update = false;
                kernel::database()->rollBack();
                break;
            }
            $tmp_obj = array();
        }
        
        if($needFreezeItem && $is_update) {
            uasort($needFreezeItem, [kernel::single('console_iostockorder'), 'cmp_productid']);
            $branchBatchList = [];
            foreach($needFreezeItem as $v) {

                $freezeData = [];
                $freezeData['bm_id'] = $v['product_id'];
                $freezeData['sm_id'] = $v['goods_id'];
                $freezeData['obj_type'] = material_basic_material_stock_freeze::__ORDER;
                $freezeData['bill_type'] = 0;
                $freezeData['obj_id'] = $order_id;
                $freezeData['shop_id'] = $shop_id;
                $freezeData['branch_id'] = 0;
                $freezeData['bmsq_id'] = material_basic_material_stock_freeze::__SHARE_STORE;
                $freezeData['num'] = $v['quantity'];
                $freezeData['obj_bn'] = $order_bn;
                $branchBatchList[] = $freezeData;
            }

            $res = $basicMStockFreezeLib->freezeBatch($branchBatchList, __CLASS__.'::'.__FUNCTION__, $err);
            if (!$res) {
                $is_update = false;
                kernel::database()->rollBack();
            }

        }
        
        if($is_update){
            $data_preprocess = array('preprocess_order_id'=>$order_id,'preprocess_type'=>'crm','preprocess_status'=>'1');
            $rs = $Obj_preprocess->insert($data_preprocess);
            if(!$rs) {
                kernel::database()->rollBack();
                return false;
            }
            
            //赠品不对应的
            if($is_fail){
              $str_bn = implode(',', $diff_bn);
              $msg = 'CRM赠品'.$str_bn.'与ERP的赠品货号不对应,订单置为失败类型';
              $operationLogObj->write_log('order_preprocess@ome',$order_id, $msg,time(),$opinfo);
              #将订单状态改为失败订单
              $orderObj->update(array('is_fail'=>'true'),array('order_id'=>$order_id)); 
              kernel::database()->commit();
              return true ;
            }

            $msg = '订单预处理CRM赠品完成';
            #记录CRM预处理完成状态

            $operationLogObj->write_log('order_preprocess@ome',$order_id, $msg,time(),$opinfo);
            kernel::database()->commit();
            
            //更新开票明细
            $objFront = app::get('invoice')->model('order_front');
            $main = $objFront->db_dump(['source_id'=>$order_id]);
            
            //check检查只有发票记录存在才更新
            if($main){
                kernel::single('invoice_order_front')->updateOrderItems($main);
            }
            
            return true ;
        }else{
            kernel::database()->rollBack();

            $msg = '订单添加CRM赠品数据出错';
            $status = $order_info['abnormal_status'] | ome_preprocess_const::__HASCRMGIFT_CODE;
            #添加赠品出错的时候，继续审单发货
            if( $crm_cfg['error'] == 'off'){
                $operationLogObj->write_log('order_preprocess@ome',$order_id, $msg,time(),$opinfo);
                return false;
            }
            #添加赠品出错的时候，打标提醒，人工处理
            elseif( $crm_cfg['error'] == 'on'){
                $orderObj->update(array('abnormal_status'=>$status),array('order_id'=>$order_id));
                $operationLogObj->write_log('order_preprocess@ome',$order_id, $msg,time(),$opinfo);
                #打标与记日志完成后，程序返回
                return false;
            }
        }


    }

    #shopex前端店铺列表
    function shopex_shop_type(){
        $shop = array(
            'shopex_b2b'=>'shopex_b2b',
            'shopex_b2c'=>'shopex_b2c',
            'ecos.b2c'=>'ecos.b2c',
            'ecshop_b2c'=>'ecshop_b2c',
            'ecos.dzg'=>'ecos.dzg'
        );
        return $shop;
    }
    
    //获取赠品规则的订单明细
    private function get_order_item_info($order_id){
        $mdl_order_items = app::get('ome')->model('order_items');
        $sql = "SELECT bn,name, nums,sale_price as price,divide_order_fee FROM sdb_ome_order_items WHERE `delete` = 'false' and order_id = {$order_id}";
        return $mdl_order_items->db->select($sql);
    }
    
    //获取赠品规则的订单对象
    private function get_order_obj_info($order_id){
        $mdl_order_objects = app::get('ome')->model('order_objects');
        $sql = "SELECT bn, name, quantity as nums, sale_price as price,divide_order_fee, author_id, author_name, addon FROM sdb_ome_order_objects WHERE `delete` = 'false' and order_id= {$order_id}";
        $list = $mdl_order_objects->db->select($sql);
        foreach ($list as $k => $v) {
            if ($v['addon']) {
                $list[$k]['addon'] = @json_decode($v['addon'], 1);
            } else {
                $list[$k]['addon'] = [];
            }
        }
        return $list;
    }
    
    /**
     * 平台订单已完成,执行补发赠品任务--延迟**天创建补发订单
     *
     * @param $ordersdf
     * @param $error_msg
     * @return bool
     */
    public function bufaOrderGifts($order_id, &$error_msg=null)
    {
        $orderMdl = app::get('ome')->model('orders');
        $preprocessMdl = app::get('ome')->model('order_preprocess');
        $salesMdl = app::get('material')->model('sales_material');
        $crmGiftMdl = app::get('crm')->model('gift');
        $logMdl = app::get('ome')->model('operation_log');
        
        $labelLib = kernel::single('ome_bill_label');
        
        //订单信息
        $ordersdf = $orderMdl->db_dump(array('order_id'=>$order_id), '*');
        if(empty($ordersdf)){
            $error_msg = '订单信息不存在';
            return false;
        }
        
        //setting
        $bill_type = 'order';
        $label_code = 'system_bufa';
        $order_id = $ordersdf['order_id'];
        $order_bn = $ordersdf['order_bn'];
        
        //获取订单标记信息
        $labelInfo = $labelLib->getBillLabelInfo($order_id, $bill_type, $label_code, $error_msg);
        if(empty($labelInfo)){
            $error_msg = '订单未标记补发赠品';
            return false;
        }
        
        //检查是否已经创建过补发订单
        $bufaOrderInfo = $orderMdl->db_dump(array('relate_order_bn'=>$order_bn, 'order_type'=>'bufa'), 'order_id');
        if($bufaOrderInfo){
            $error_msg = '订单已经创建过补发订单';
            return false;
        }
        
        //opinfo
        $opinfo = kernel::single('ome_func')->getDesktopUser();
        
        //订单objects明细
        $orderObjes = $this->getPlatformOrderObjects($order_id);
        $ordersdf['objects'] = $orderObjes;
        
        //订单items明细
        $orderItems = $this->getPlatformOrderItems($order_id);
        $ordersdf['items'] = $orderItems;
        
        //check
        if(empty($ordersdf['objects']) || empty($ordersdf['items'])){
            $error_msg = '订单没有商品明细';
            return false;
        }
        
        //会员名、收货人姓名
        $memberInfo = app::get('ome')->model('members')->db_dump($ordersdf['member_id'], 'uname');
        $ordersdf['buyer_nick'] = $memberInfo['uname']; //会员名
        $ordersdf['receiver_name'] = $ordersdf['ship_name']; //收件人姓名
        
        //指定订单审核时符合的赠品应用规则
        $applyGiftInfo = json_decode($labelInfo['extend_info'], true);
        
        //获取补发赠品规则
        $result = kernel::single('crm_gift')->getBufaGiftRules($ordersdf, $applyGiftInfo);
        if($result['rsp'] != 'succ'){
            //清除补发标签
            //$labelLib->delLabelFromBillId($order_id, $labelInfo['label_id'], $bill_type, $error_msg);
            
            //log
            $error_msg = '延迟赠送赠品失败：'. $result['error_msg'];
            $logMdl->write_log('order_preprocess@ome', $order_id, $error_msg, time(), $opinfo);
            
            return false;
        }elseif(empty($result['gifts'])){
            //log
            $error_msg = '延迟赠送赠品失败：赠送商品为空';
            $logMdl->write_log('order_preprocess@ome', $order_id, $error_msg, time(), $opinfo);
            
            return false;
        }elseif(empty($result['applyInfo']['defer_day'])){
            //log
            $error_msg = '延迟赠送天数不正确';
            $logMdl->write_log('order_preprocess@ome', $order_id, $error_msg, time(), $opinfo);
            
            return false;
        }
        
        //赠品信息
        $defer_day = intval($result['applyInfo']['defer_day']);
        $giftProducts = $result['gifts'];
        $giftBns = array_keys($giftProducts);
        
        //根据赠品货号,找到对应的销售物料
        $saleMaterList = $salesMdl->getList('sm_id,sales_material_name,sales_material_bn,sales_material_type',array('sales_material_bn'=>$giftBns));
        if(empty($saleMaterList)){
            $msg = 'CRM赠品在没有对应的销售物料('. implode(',', $giftBns) .')!';
            $logMdl->write_log('order_preprocess@ome', $order_id, $msg, time(), $opinfo);
            return false;
        }
        
        //继续检测启用的赠品
        $_gift_bn = $crmGiftMdl->getList('gift_bn', array('gift_bn'=>$giftBns, 'status'=>1));
        $erp_gift_bn = array_map('current', $_gift_bn);
        
        //获取差异的赠品货号(例如ERP已把赠品删除,而CRM没及时同步)
        $is_fail = false;
        $diff_bn = array_diff($giftBns, $erp_gift_bn);
        if(!empty($diff_bn)){
            //打标记
            $is_fail = true;
        }
        
        //赠送销售物料列表、数量
        $giftSalesMaterial = array();
        foreach($saleMaterList as $saleInfo)
        {
            //销售物料编码
            $sales_material_bn = $saleInfo['sales_material_bn'];
            
            //CRM赠品赠送的数量
            $quantity = intval($giftProducts[$sales_material_bn]);
            
            //check
            if(empty($quantity)){
                continue;
            }
            
            //data
            $giftSalesMaterial[] = array(
                'sales_material_bn' => $sales_material_bn,
                'quantity' => $quantity,
            );
        }
        
        //check
        if(empty($giftSalesMaterial)){
            $error_msg = '没有可赠送的销售物料';
            $logMdl->write_log('order_preprocess@ome', $order_id, $error_msg, time(), $opinfo);
            return false;
        }
        
        //扩展信息
        $extendInfo = array();
        $extendInfo['giftSalesMaterial'] = $giftSalesMaterial;
        //$extendInfo['orderInfo'] = array('order_id'=>$order_id, 'order_bn'=>$order_bn);
        
        //延迟天数
        $timing_time = strtotime($defer_day .' day');
        
        //延时任务
        $task = array(
            'obj_id' => $order_id,
            'obj_type' => 'timing_bufa_order',
            'exec_time' => $timing_time,
            'extend_info' => json_encode($extendInfo),
        );
        app::get('ome')->model('misc_task')->saveMiscTask($task);
        
        //logs
        $msg = '延迟创建补发赠品订单：'. date('Y-m-d H:i:s', $timing_time);
        $logMdl->write_log('order_preprocess@ome', $order_id, $msg, time(), $opinfo);
        
        //插入赠送记录日志
        $data_preprocess = array('preprocess_order_id'=>$order_id, 'preprocess_type'=>'crm', 'preprocess_status'=>'1');
        $preprocessMdl->insert($data_preprocess);
        
        //赠品有差异的
        if($is_fail){
            $str_bn = implode(',', $diff_bn);
            $msg = 'CRM补发赠品完成，本次差异赠品：'. $str_bn;
            $logMdl->write_log('order_preprocess@ome', $order_id, $msg, time(), $opinfo);
        }else{
            //CRM预处理完成
            //$msg = 'CRM补发赠品完成';
            //$logMdl->write_log('order_preprocess@ome',$order_id, $msg, time(), $opinfo);
        }
        
        return true;
    }
    
    /**
     * 获取平台订单商品明细（过滤掉OMS系统赠送的赠品）
     *
     * @param $order_id
     * @return array
     */
    private function getPlatformOrderObjects($order_id){
        $orderObjectMdl = app::get('ome')->model('order_objects');
        
        $sql = "SELECT obj_id,obj_type,shop_goods_id,bn,name,quantity AS nums,sale_price AS price,divide_order_fee FROM sdb_ome_order_objects";
        $sql .= " WHERE `delete`='false' AND order_id=". $order_id;
        $itemList = $orderObjectMdl->db->select($sql);
        if(empty($itemList)){
            return array();
        }
        
        //过滤掉OMS赠送的赠品
        foreach ($itemList as $key => $val){
            if($val['obj_type'] == 'gift' && $val['shop_goods_id'] == '-1'){
                unset($itemList[$key]);
            }
        }
        
        return $itemList;
    }
    
    /**
     * 获取平台订单货品明细（过滤掉OMS系统赠送的赠品）
     *
     * @param $order_id
     * @return array
     */
    public function getPlatformOrderItems($order_id){
        $orderItemMdl = app::get('ome')->model('order_items');
        
        $sql = "SELECT item_id,bn,name,nums,sale_price AS price,divide_order_fee,item_type,shop_product_id FROM sdb_ome_order_items WHERE `delete`='false' AND order_id=". $order_id;
        $itemList = $orderItemMdl->db->select($sql);
        if(empty($itemList)){
            return array();
        }
        
        //过滤掉OMS赠送的赠品
        foreach ($itemList as $key => $val){
            if($val['item_type'] == 'gift' && $val['shop_product_id'] == '-1'){
                unset($itemList[$key]);
            }
        }
        
        return $itemList;
    }
}
