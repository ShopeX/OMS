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
 * @desc    换货对接
 * @author:sunjing@shopex.cn
 * @since:
 */
class erpapi_shop_response_exchange extends erpapi_shop_response_abstract {
/*买家已经申请退款，等待卖家同意                 WAIT_SELLER_AGREE
 卖家已经同意换货，等待买家退货                WAIT_BUYER_SEND_GOODS
 买家已经退货，等待卖家确认收货                WAIT_SELLER_CONFIRM_GOODS
 卖家拒绝确认收货                               SELLER_REFUSED_CONFIRM_GOODS
 换货关闭                                       EXCHANGE_CLOSE
 换货成功                                       EXCHANGE_SUCCESS
 换货结束                                       EXCHANGE_FINISH
 卖家确认收货,等待卖家发货                      WAIT_SELLER_SEND_GOODS
 换货关闭,转退货退款                             EXCHANGE_CLOSE_TO_SALES_RETURN*/
    protected $_change_return_type = false;
    static public  $return_status = array(
        'WAIT_SELLER_AGREE'         =>'1',//换货待处理
        'WAIT_BUYER_SEND_GOODS'     =>'3',//待买家退货
        'WAIT_SELLER_CONFIRM_GOODS'  =>'6',//  买家已退货，待收货
        'EXCHANGE_CLOSE'            =>'5',// 换货关闭
        'EXCHANGE_SUCCESS'          =>'4',// 换货成功
        'EXCHANGE_FINISH'           =>  '4',
        'WAIT_SELLER_SEND_GOODS'    =>'4',// 待发出换货商品  转换成  待收货状态？没这个状态的
        'SELLER_REFUSED_CONFIRM_GOODS'=>'9',// 卖家拒绝确认收货
        'EXCHANGE_CLOSE_TO_SALES_RETURN'=>'5',//  请退款
        'SELLER_REFUSE_BUYER' => '5', //卖家拒绝(抖音)
        'WAIT_BUYER_CONFIRM_GOODS'=>'3',
        'SELLER_REFUSED_SEND_NEW_GOODS' => '18', //【极速换货】商家拒绝发新货（非极速时不应走到此态；映射由二维逻辑覆盖）
    );
    
    protected function _formatAddParams($params) {
        self::trim($params);
        $sdf = array(
            'tid'                   =>  $params['tid'],
            'platform_order_bn'     =>  $params['tid'], //平台订单号
            'return_bn'             =>  $params['dispute_id'],
            'status'                =>  $params['status'],
            'platform_status'       =>  $params['source_status'], //平台退款状态
            'reason'                =>  $params['reason'],
            'comment'               =>  $params['desc'],
            'modified'              =>  $params['modified'] ? kernel::single('ome_func')->date2time($params['modified']) : '',
            'created'               =>  $params['createtime'] ? kernel::single('ome_func')->date2time($params['createtime']) : time(),
            'refund_phase'          =>  $params['refund_phase'],
            'advance_status'        =>  $params['advance_status'],
            'cs_status'             =>  $params['cs_status'],
            'good_status'           =>  $params['good_status'],
            'alipay_no'             =>  $params['alipay_no'],
            'buyer_nick'            =>  $params['buyer_nick'],
            'desc'                  =>  $params['desc'],
            'logistics_no'          =>  $params['buyer_logistic_no'] ? $params['buyer_logistic_no'] : '',
            'buyer_name'            =>  $params['buyer_name'] ? $params['buyer_name'] : '',
            'buyer_address'         =>  $params['buyer_address_detail'] ? $params['buyer_address_detail'] : '',
            'buyer_province'        =>  $params['buyer_state'] ? $params['buyer_state'] : '',
            'buyer_city'            =>  $params['buyer_city'] ? $params['buyer_city'] : '',
            'buyer_district'        =>  $params['buyer_district'] ? $params['buyer_district'] : '',
            'buyer_town'            =>  $params['buyer_town'] ? $params['buyer_town'] : '',
            'index_field'           =>  $params['index_field'] ? $params['index_field'] : '',
            'logistics_company'     =>  $params['buyer_logistic_name'] ? $params['buyer_logistic_name'] : '',
            'buyer_phone'           =>  $params['buyer_phone'] ? $params['buyer_phone'] : '',
            'seller_address'        =>  $params['address'] ? $params['address'] : '',
            'seller_logistic_no'    =>  $params['seller_logistic_no'] ? $params['seller_logistic_no'] : '',
            'seller_logistic_name'  =>  $params['seller_logistic_name'] ? $params['seller_logistic_name'] : '',
            'bought_bn'             =>  $params['bought_bn'],
            'title'                 =>  $params['title'],
            'num'                   =>  $params['num'],
            'price'                 =>  $params['price'],
            'exchange_bn'           =>  $params['exchange_bn'],
            'time_out'              =>  $params['time_out'],
            'operation_contraint'   =>  $params['operation_contraint'],
            'refund_version'        =>  $params['refund_version'],
            'speed_exchange'        =>  kernel::single('ome_order_platform_taobao_aftersale')->extractSpeedExchange($params), // 极速换货(1：代表是)，兼容 extend_field
            'shop_id'               =>  $this->__channelObj->channel['shop_id'],
            'attributes'             =>  $params['attributes'] ? @json_decode($params['attributes'],true) : [],
            'org_id'                => $this->__channelObj->channel['org_id'],
        );

        return $sdf;
    }
    
    protected function _returnProductAdditional($sdf) {
        return array();
    }

    /**
     * 添加
     * @param mixed $params 参数
     * @return mixed 返回值
     */

    public function add($params){
        $original_bn = $params['dispute_id'];

        if($this->__channelObj->channel['config']['exchange_receive'] === 'no'){
            $this->__apilog['result']['msg'] = '未开启换货收单配置';
            return false;
        }
        
        //替换表情符
        if(isset($params['buyer_nick']) && !empty($params['buyer_nick'])){
            $params['buyer_nick'] = kernel::single('ome_order_func')->filterEmoji($params['buyer_nick']);
        }
      
        //format
        $sdf = $this->_formatAddParams($params);
        
        if ($sdf){
            $original_bn = $sdf['order']['order_bn'];
        }
        $this->__apilog['title'] = '店铺(' . $this->__channelObj->channel['name'] . ')换货业务处理[换货单号：' . $params['dispute_id']. ']';
        $this->__apilog['original_bn'] = $params['tid'];
        $this->__apilog['result']['data'] = array('tid'=>$params['tid'],'aftersale_id'=>$params['dispute_id'],'retry'=>'false');

        if ($sdf['order']['tran_type'] == 'archive'){
            $sdf['archive'] = '1';
            $sdf['source'] = 'archive';
        }

        if(empty($sdf)) {
            $error_msg = '不接收售后单';
            
            //各个平台格式化数据时报错信息
            if($this->__apilog['result']['msg']){
                $error_msg .= '('. $this->__apilog['result']['msg'] .')';
            }
            
            $this->__apilog['result']['msg'] = $error_msg;
            return false;
        }
        
        //是否发货判断
        if (in_array($sdf['order']['ship_status'],array('0'))){
            $this->__apilog['result']['msg'] = '未发货订单不接收售后单';
            return false;
        }
        
        //判断状态是否处理
        $status = self::$return_status[strtoupper($sdf['status'])];
        if (!$status) {

            $this->__apilog['result']['msg'] = '此状态不处理!';
            return false;
        }
        $shopId = $sdf['shop_id'] = $this->__channelObj->channel['shop_id'];
        $sdf['shop']['delivery_mode'] = $this->__channelObj->channel['delivery_mode'];

        if(empty($sdf['return_items'])) {
            $this->__apilog['result']['msg'] = '退货明细不可为空!';
            return false;
        }
        
        // 兼容换了又换
        list($is_change, $change_msg, $change_order, $change_bought) = $changeRs = $this->getChangeReturnProduct($sdf);
        if($is_change === true){
            // OMS生成的新订单号
            $sdf['tid'] = $sdf['order_bn'] = $change_order['order_bn'];
            
            // OMS换货生成的新订单信息
            $sdf['order'] = $change_order;

            $sdf['bought_bn']   = $change_bought['bought_bn'];
            $sdf['oid']         = $change_bought['oid'];

            $sdf['is_change_more'] = true;

            // 重新定位明细
            $orders_detail = $this->getOrderByoid($this->__channelObj->channel['shop_id'], $sdf, $error_msg);
            $return_items = array();
            foreach ($orders_detail['item_list'] as $o_v) {
                //退换货数量
                $sdf['num'] = ($sdf['num'] ? $sdf['num'] : $o_v['quantity']);
                
                $price =  $o_v['nums'] ? bcdiv((float)$o_v['divide_order_fee'], (float)$o_v['nums'], 2) : 0;
                $radio = $o_v['quantity'] ? bcdiv((float)$sdf['num'], (float)$o_v['quantity'], 2) : 0;
                
                $return_num = $sdf['num'];
                if ($o_v['obj_type'] == 'pkg') {
                    $return_num = bcmul((float)$radio, (float)$o_v['nums']);
                }
                
                //items
                $return_items[] = array(
                    'bn'            => $o_v['item_bn'],
                    'name'          => $o_v['name'],
                    'product_id'    => $o_v['product_id'],
                    'num'           => $return_num,
                    'price'         => $price,
                    'sendNum'       => $o_v['sendnum'],
                    'order_item_id' => $o_v['item_id'],
                    'item_type'     => $o_v['item_type'],
                );
            }

            $sdf['return_items'] = $return_items;
        }
        
        if (is_array($sdf['change_items'])){
            $changeItemList = $this->_formatChangeItemlist($sdf['change_items']);
            if (empty($changeItemList)){
                $this->__apilog['result']['msg'] = '换货明细不可为空!';
                return false;
            }else{
                $sdf['change_items'] = $changeItemList;
            }
        }
        
        $rs = $this->_returnProductAddSdf($sdf);
        if(!$rs){
            $apilog = $this->__apilog['result'];
            if($apilog['msg_code'] == '6666' && in_array($sdf['order']['ship_status'],array('3','4'))){//二次换货数量不足时处理

                $change_flag = $this->_tranChange($sdf);
                if($change_flag){
                    $sdf['change_order_flag'] = true;
                    $sdf['change_order_id'] = $change_flag['change_order_id'];
                    $params['oid'] = '';
                    $sdf['memo'] = '换货订单转换生成,原订单号:'.$params['tid'];
                    $this->_tranChangeItems($sdf);
                   

                    $rs = $this->_returnProductAddSdf($sdf);

                    return $rs;
                }
            }
        }

        return $rs;
    }
    
    protected function _getOrderDelivery($sdf) {
        // 获取订单关联的所有已发货的发货单delivery_id
        $sql = "SELECT dord.delivery_id, d.branch_id FROM sdb_ome_delivery_order AS dord
                  LEFT JOIN sdb_ome_delivery AS d ON(dord.delivery_id=d.delivery_id)
                  WHERE dord.order_id='".$sdf['order']['order_id']."'
                    AND (d.parent_id=0 OR d.is_bind='true')
                    AND d.disabled='false' AND d.status='succ'";

        $result = kernel::database()->select($sql);
        if(count($result) > 1 && !empty($sdf['return_items'])) {
            $arrDelivery = array();
            foreach ($result as $key => $val) {
                $arrDelivery[$val['delivery_id']] = $val;
            }
            
            $productId = '';
            foreach($sdf['return_items'] as $iVal) {
                if($iVal['product_id']) {
                    $productId = $iVal['product_id'];
                    break;
                }
            }
            
            $deliItemModel = app::get('ome')->model('delivery_items');
            $itemData = $deliItemModel->getList('delivery_id', array('delivery_id'=>array_keys($arrDelivery), 'product_id'=>$productId), 0, 1);
        }
        
        return $itemData ? $arrDelivery[$itemData[0]['delivery_id']] : $result[0];
    }
    
    protected function _returnProductAddSdf($sdf)
    {
        $returnModel = app::get('ome')->model('return_product');
        $topspeedLib = kernel::single('ome_order_platform_taobao_aftersale');
        
        //平台售后状态
        $platform_status = strtoupper($sdf['status']);
        $sdf['platform_status_raw'] = $platform_status;
        if (empty($sdf['platform_status'])) {
            $sdf['platform_status'] = $platform_status;
        }
        
        //售后申请单信息（先取库，便于漏传 speed_exchange 时仍按已落库极速标解释）
        $tgReturn = $returnModel->getList('*', array('shop_id'=>$sdf['shop_id'],'return_bn'=>$sdf['return_bn']));
        $version_change = false;
        if($tgReturn) {
            $sdf['return_product'] = $tgReturn[0];
            
            if(in_array($sdf['shop_type'],['xhs'])){
                if(in_array($tgReturn['kinds'], array('reship'))){
                    //$lubanLib = kernel::single('ome_reship_luban');
                    //$lubanLib->transformExchange($sdf);
                }
            }
            
            $reshipData = $this->_returnProductReship($sdf['return_product']['return_id'], $sdf['order']['order_id']);
            if($reshipData['reship']) {
                $sdf['reship'] = $reshipData['reship'];
            }
            if($reshipData['other_reship_items']) {
                $sdf['other_reship_items'] = $reshipData['other_reship_items'];
            }

            if ($sdf['modified'] > $sdf['return_product']['outer_lastmodify']){
                $version_change = true;
            }

            if($sdf['modified'] <= $sdf['return_product']['outer_lastmodify']) {
                $this->__apilog['result']['msg'] = '更新时间未变化不更新';
                return false;
            }
            if ($this->_change_return_type === true  && $sdf['return_product']['return_type'] =='return'){
                //
                if(!in_array($sdf['status'],array('5','9'))){
                    $sdf['change_return_type'] = true;
                }
            }
        }

        // 极速身份（报文或已落库）
        $isTopspeed = $topspeedLib->resolveIsTopspeedExchange($sdf);
        $sdf['is_topspeed_exchange'] = $isTopspeed ? true : false;

        //状态（二维映射：极速 WAIT_SELLER_SEND_GOODS→6，SELLER_REFUSED_SEND_NEW_GOODS→18）
        $mapped = $topspeedLib->resolvePlatformStatusMap($platform_status, $isTopspeed, self::$return_status);
        if ($mapped === null || $mapped === '') {
            $sdf['status'] = self::$return_status[strtoupper($platform_status)];
        } else {
            $sdf['status'] = $mapped;
        }
        
        //商家拒绝退款
        if(in_array($sdf['shop_type'], array('taobao', 'tmall')) && $platform_status=='SELLER_REFUSE_BUYER'){
            $sdf['status'] = '10';
        }
        
        //不存在状态为已完成和已拒绝不处理
        if($sdf['shop']['delivery_mode'] == 'jingxiao') {
            
        } else {
            if (!$sdf['return_product'] && in_array($sdf['status'],array('4','5'))){
                $this->__apilog['result']['msg'] = '已完成单据不处理!';
                return false;
            }
        }
    
        //版本变化标识
        $sdf['version_change'] = $version_change;
        
        //判断是否已拒绝或完成状态
        if ($sdf['return_product']['status'] == '5' && !$version_change){
            $this->__apilog['result']['msg'] = '已拒绝版本未变化不处理';
            return false;
        }
        if ($sdf['return_product']['status'] == '4'){
            // 极速换货：若曾误入完成态，允许 17/18 继续更新
            $allowTopspeedContinue = $isTopspeed && in_array($platform_status, array(
                'WAIT_SELLER_SEND_GOODS',
                'SELLER_REFUSED_SEND_NEW_GOODS',
            ), true);
            if (!$allowTopspeedContinue) {
                $appendMsg = '已完成售后单,';
                
                $isCancelOrder = false;
                if($sdf['shop_type'] == 'luban' && $sdf['exchange_status']['after_sale_status'] == 12 && $sdf['exchange_status']['refund_status'] == 3){
                    //换货转仅退款,并且平台已经退款完成
                    $isCancelOrder = true;
                    $appendMsg .= '换货转仅退款';
                }elseif($sdf['shop_type'] == 'luban' && $sdf['status'] == '5'){
                    //售后关闭(商家拒绝确认收货)
                    $isCancelOrder = true;
                    $appendMsg .= '商家拒绝确认收货';
                }
                
                //取消生成的新订单
                if($isCancelOrder){
                    $lubanLib = kernel::single('ome_reship_luban');
                    $result = $lubanLib->cancelExchangeOrder($sdf);
                    if($result['rsp'] == 'succ'){
                        $this->__apilog['result']['msg'] = $appendMsg .',OMS自动取消生成的新订单成功;';
                        return false;
                    }else{
                        $this->__apilog['result']['msg'] = $appendMsg .',取消新订单失败:'. $result['error_msg'];
                        return false;
                    }
                }
                
                $this->__apilog['result']['msg'] = '已完成售后单不处理';

                if($sdf['reship'] && $sdf['status'] == '5' && $sdf['reship']['change_order_id']){//加判断如果是close加暂停

                    kernel::single('ome_return')->pauseChangeOrder($sdf['reship']['change_order_id']);    
                    
                    $this->__apilog['result']['msg'].='换出订单暂停';
                }
                return false;
            }
        }
    
        //发货单
        $delivery = $this->_getOrderDelivery($sdf);
        if ($delivery) {
            $sdf['delivery_id'] = $delivery['delivery_id'];
            $sdf['branch_id'] = $delivery['branch_id'];
        }
    
        //待处理状态
        if ($sdf['status'] == '1' || !$sdf['return_product']) {
            $itemObj = app::get('ome')->model('reship_items');
            $archiveOrderLib = kernel::single('archive_interface_orders');
            
            // 如果前端传了会员名
            if ($sdf['buyer_nick']) {
                $shopMemberModel = app::get('ome')->model('shop_members');
                $member = $shopMemberModel->getList('member_id', array('shop_member_id' => $sdf['buyer_nick'], 'shop_id' => $sdf['shop_id']), 0, 1);
                $sdf['member_id'] = $member[0]['member_id'];
            } else {
                $sdf['member_id'] = $sdf['order']['member_id'];
            }
            
            //退货明细列表
            $return_items = $sdf['return_items'];
    
            //默认判断可退货数量
            $is_check_return_nums = true;
            
            //退货转换货Or换货申请修改换出商品
            //场景：1、顾客在抖音平台修改退货为换货类型,判断数量则无法生成换货单; 2、天猫顾客修改换货申请单上,换出A商品修改为B商品;
            if(in_array($sdf['shop_type'], array('luban', 'taobao', 'tmall'))){
                //退货单与换货单号相同,并且退货申请单还没有完成入库
                if($sdf['return_product']['return_bn'] == $sdf['return_bn'] && in_array($sdf['return_product']['status'], array('1','2','3'))){
                    $is_check_return_nums = false;
                }
                
                //判断是否修改换货单上换出商品
                $sdf['is_modify_exchange_bn'] = $this->_isModify_exchange_bn($sdf);
            }
            
            //检查可退货数量
            if($is_check_return_nums){
                foreach($return_items as $item){
                    $effective = $itemObj->Get_refund_count($sdf['order']['order_id'], $item['bn']);
                    
                    //当小于等于0时,读取归档数据
                    if ($effective <= 0)  {
                        $effective = $archiveOrderLib->Get_refund_count($sdf['order']['order_id'], $item['bn']);
                    }
                    
                    if ($effective <= 0)  {
                        $this->__apilog['result']['msg'] = "返回值：货号[{$item['bn']}]申请数超出订单可退数";
                        $this->__apilog['result']['msg_code'] = "6666";
                        return false;
                    }
                }
            }
            
            $sdf['table_additional'] = $this->_returnProductAdditional($sdf);
        } else {
            $returnItemModel = app::get('ome')->model('return_product_items');
            $tgReturnItems = $returnItemModel->getList('item_id',array('return_id'=>$sdf['return_product']['return_id']));
            if (!$tgReturnItems && $sdf['status']!='5') {
                $this->__apilog['result']['msg'] = '缺少明细';
                return false;
            }
            
            //是否更新售后申请附加信息表
            if ($sdf['status']<=3 || $sdf['status'] == 6){
                $sdf['table_additional'] = $this->_returnProductAdditional($sdf);
            }
            
            //判断是否修改换货单上换出商品
            $sdf['is_modify_exchange_bn'] = $this->_isModify_exchange_bn($sdf);
        }
        
        //选择类型标志
        $sdf['choose_type_flag'] = 1;
        
        //[兼容更新换货数量]顾客在平台上修改换货数量
        $sdf['isModifyExchangeNum'] = false;
        if($sdf['version_change'] && in_array($sdf['shop_type'], array('tmall'))){
            $sdf['isModifyExchangeNum'] = $this->_checkModifyExchangeNum($sdf);
        }
        
        return $sdf;
    }

    protected function _returnProductReship($returnProductId, $orderId) {
        $rs = array();
        $oReship = app::get('ome')->model('reship');
        $field = 'reship_id,reship_bn,return_id,is_check,return_logi_name,return_logi_no,outer_lastmodify,change_status,return_type,changebranch_id,branch_id,change_order_id';
        $reship = $oReship->getList($field, array('return_id' => $returnProductId), 0, 1);
        if($reship) {
            $rs['reship'] = $reship[0];
            $otherFilter = ' AND r.reship_id != ' . $rs['reship']['reship_id'];
        }
        
        $sql = "SELECT i.bn, i.num FROM sdb_ome_reship as r left join sdb_ome_reship_items as i on r.reship_id=i.reship_id WHERE i.return_type='return' AND r.is_check!='5' AND r.order_id='" . $orderId . "' " . $otherFilter;
        $otherReship = $oReship->db->select($sql);
        if($otherReship) {
            $rs['other_reship_items'] = $otherReship;
        }
        
        return $rs;
    }
    
    protected function _formatChangeItemlist($change_items){
        $salesMLib = kernel::single('material_sales_material');
        foreach($change_items as &$items){
            $product_detail = $salesMLib->getSalesMByBn($this->__channelObj->channel['shop_id'],$items['bn']);
            if($product_detail){
                $items['product_id'] = $product_detail['sm_id'];
                $items['name'] = $product_detail['sales_material_name'];
            }else{
                return array();
            }
        }
        
        return $change_items;
    }
    
    /**
     * 检查换货数量是否变化
     * 
     * @param array $sdf
     * @return bool
     */
    protected function _checkModifyExchangeNum($sdf)
    {
        $returnItemModel = app::get('ome')->model('return_product_items');
        
        //check
        if(empty($sdf['return_items']) || empty($sdf['return_product'])){
            return false;
        }
        
        //申请退货数量
        $apply_nums = 0;
        foreach($sdf['return_items'] as $item)
        {
            $apply_nums += intval($item['num']);
        }
        
        //上次申请退货的数量
        $returnItems = $returnItemModel->getList('item_id,num',array('return_id'=>$sdf['return_product']['return_id']));
        if(empty($returnItems)){
            return false;
        }
        
        $return_nums = 0;
        foreach ($returnItems as $key => $val)
        {
            $return_nums += intval($val['num']);
        }
        
        //换货数量是否被修改
        if($apply_nums != $return_nums){
            return true;
        }
        
        return false;
    }

    protected function _tranChange($sdf){

    }

    protected function _tranChangeItems(&$sdf){
        $order_id = $sdf['change_order_id'];

        $orderObj = app::get('ome')->model('orders');
        if ($order_id>0){
            $order_detail = $orderObj->dump($order_id,"order_id,order_bn,status,process_status,ship_status,pay_status",array("order_objects"=>array("*",array("order_items"=>array('*')))));
        
            if($order_detail){

                $sdf['order'] = array(
                    'order_id'       => $order_detail['order_id'],
                    'status'         => $order_detail['status'],
                    'process_status' => $order_detail['process_status'],
                    'ship_status'    => $order_detail['ship_status'],
                    'pay_status'     => $order_detail['pay_status'],
                    'order_bn'       => $order_detail['order_bn'],
                );
                $sdf['tid']  =$sdf['order_bn']   =   $order_detail['order_bn'];
                $order_object = $order_detail['order_objects'];
                $return_item = $sdf['refund_item_list']['return_item'];
                $return_item    =    is_array($return_item) ? current($return_item) : [];
                $item_list = array();

                foreach($order_object as $oo){
                    foreach($oo['order_items'] as $ov){
                        if($ov['delete'] == 'false'){
                            $item_list[] = array(
                                'product_id' => $ov['product_id'],
                                'bn'         => $ov['bn'],
                                'name'       => $ov['name'],
                                'num'        => $sdf['num'],
                                'price'      => $ov['price'],
                                'sendNum'   =>  $ov['sendnum'],
                                'op_id'     => '888888',
                                'order_item_id' => $ov['item_id'],
                            );
                        }
                    }
                }
                
                $sdf['return_items'] = $item_list;

            }
        }
    }
    
    /**
     * 判断是否修改换货单上换出商品
     * 
     * @param $sdf
     * @return void
     */
    public function _isModify_exchange_bn($sdf)
    {
        $changeItemsInfo = $sdf['change_items'][0];
        
        //check
        if(!in_array($sdf['shop_type'], array('luban', 'taobao', 'tmall'))){
            return false;
        }
        
        //退货单与换货单号不相同,则跳过
        if($sdf['return_product']['return_bn'] != $sdf['return_bn']){
            return false;
        }
        
        //退货申请单不是处理中状态,则跳过
        if(!in_array($sdf['return_product']['status'], array('1','2','3'))){
            return false;
        }
        
        //没有换货商品信息
        if(empty($changeItemsInfo)){
            return false;
        }
        
        //还没有生成售后退换货单
        if(empty($sdf['reship'])){
            return false;
        }
        
        //获取售后退货单明细中的objects层换货商品列表
        $reship_change_items = $this->_getReshipExchangeItems($sdf['reship']);
        if(empty($reship_change_items)){
            return false;
        }
        
        //上次换货商品与本次相同
        if($changeItemsInfo['bn'] == $reship_change_items[0]['bn']){
            return false;
        }
        
        return true;
    }
    
    /**
     * 获取售后换货单object明细列表
     * 
     * @param $reshipInfo
     * @return array
     */
    public function _getReshipExchangeItems($reshipInfo)
    {
        $reshipObjMdl = app::get('ome')->model('reship_objects');
        
        //check
        if(empty($reshipInfo['reship_id'])){
            return array();
        }
        
        //objects取消最近的一条记录
        $tempList = $reshipObjMdl->getList('*', array('reship_id'=>$reshipInfo['reship_id']), 0, 1, 'obj_id DESC');
        if(empty($tempList)){
            return array();
        }
        
        //换货商品
        $change_items = array();
        foreach ($tempList as $key => $val)
        {
            $change_items[] = $val;
        }
        
        return $change_items;
    }

    /**
     * [换货完成又换]通过原平台订单找到换货生成的OMS新订单
     * 
     * @param $sdf
     * @return array
     */
    public function getChangeReturnProduct($sdf)
    {
        $orderObj   = app::get('ome')->model('orders');
        
        $shop_id        = $sdf['shop_id'];
        $order_bn       = $sdf['tid'];
        $return_bn      = $sdf['return_bn'];

        $orderFilter = [
            'shop_id'               => $shop_id,
            'platform_order_bn'     => $order_bn,
            'ship_status'           => ['1','2','3','4'],
        ];

        // 判断是否数据已经存在
        $rp = app::get('ome')->model('return_product')->db_dump([
            'return_bn'         => $return_bn,
            'shop_id'           => $shop_id,
        ], 'order_id');
        
        $field = 'order_id,order_bn,status,process_status,ship_status,pay_status,payed,cost_payment,pay_bn,member_id,logi_id,logi_no,ship_name,ship_area,ship_addr,ship_zip,ship_tel,ship_email,ship_mobile,shipping,is_protect,is_cod,source,order_type,createtime,abnormal';
        if ($rp && $rp['order_id'] != $sdf['order']['order_id']){
            $order = $orderObj->db_dump(['order_id' => $rp['order_id']], $field);

            $object = [];
            if (!$object && $sdf['oid']) {
                $object = app::get('ome')->model('order_objects')->dump([
                    'order_id'=> $order['order_id'],
                    'oid'     => $sdf['oid'],
                    'delete' => 'false',
                ], 'order_id,obj_id,oid,quantity,bn,obj_type');
            } 

            if (!$object && $sdf['bought_bn']) {
                $object = app::get('ome')->model('order_objects')->dump([
                    'order_id'  => $order['order_id'],
                    'bn'        => $sdf['bought_bn'],
                    'delete'    => 'false',
                ], 'order_id,obj_id,oid,quantity,bn,obj_type');
            }

            return [true, '匹配成功', $order, ['bought_bn' => $object['bn'], 'oid' => $object['oid']]];
        }

        // 定位退货订单
        $orderList = $orderObj->getList($field, $orderFilter);

        if (!$orderList) {
            return [false, '未找到平台订单号：'.$order_bn];
        }

        if (1 == count($orderList)  && $orderList[0]['source'] == 'matrix'){
            return [false, '未存在换货订单'];
        }

        $orderList = array_column($orderList, null, 'order_id');
        $objects = app::get('ome')->model('order_objects')->getList('order_id,obj_id,oid,quantity,bn', [
            'order_id'=> array_keys($orderList),
            'delete' => 'false',
        ]);
        $objects = array_column($objects, null, 'obj_id');

        $items = app::get('ome')->model('order_items')->getList('item_id,obj_id,order_id,bn,nums,sendnum,return_num,item_type', [
            'order_id' => array_keys($orderList),
            'filter_sql' => ' sendnum > return_num ',
            'delete' => 'false',
        ]);

        $returnDetails = [];
        foreach ($items as $item) {
            $oid        = $objects[$item['obj_id']]['oid'];
            $quantity   = $objects[$item['obj_id']]['quantity'];

            $bn = $item['bn'];
            $returnableNum = $item['sendnum'] - $item['return_num'];
            
            //item_type
            if (in_array($item['item_type'], array('pkg', 'lkb'))){
                $returnableNum = floor($returnableNum / $item['nums'] * $quantity);
            }
            
            $returnDetails[$item['order_id']]['oid_list'][$oid] = [
                'returnableNum' => $returnableNum,
                'bought_bn'     => $objects[$item['obj_id']]['bn'],
                'oid' => $oid,
            ];

            $returnDetails[$item['order_id']]['bn_list'][$bn] = [
                'returnableNum' => $returnableNum,
                'bought_bn'     => $objects[$item['obj_id']]['bn'],
                'oid'           => $oid,
            ];
        }

        foreach ($returnDetails as $order_id => $value){
            $oid_list = $value['oid_list'];
            $bn_list = $value['bn_list'];

            // 按OID查
            if ($sdf['oid'] && $oid_list[$sdf['oid']] && intval($oid_list[$sdf['oid']]['returnableNum']) >= intval($sdf['num'])) {
                return [true, '匹配成功', $orderList[$order_id], $oid_list[$sdf['oid']]];
            }

            // 按货号查
            if ($sdf['bought_bn'] && $bn_list[$sdf['bought_bn']] && intval($bn_list[$sdf['bought_bn']]['returnableNum']) >= intval($sdf['num'])) {
                return [true, '匹配成功', $orderList[$order_id], $bn_list[$sdf['bought_bn']]];
            }
        }
        
        return [false, '匹配失败'];
    }
    
    /**
     * [淘宝极速换货]换货拒绝发出新品
     *
     * @param $sdf
     * @return array
     */
    public function consigngoods_refuse($params)
    {
        //format
        $sdf = $this->_formatConsigngoodsRefuseParams($params);
        
        // 平台只传 dispute_id，日志原单号用换货单号
        $original_bn = !empty($sdf['return_bn']) ? $sdf['return_bn'] : '';
        
        // apilog
        $this->__apilog['title'] = '店铺(' . $this->__channelObj->channel['name'] . ')换货拒绝发出新品[换货单号：' . $original_bn . ']';
        $this->__apilog['original_bn'] = $original_bn;
        $this->__apilog['result']['data'] = $sdf;
        
        // check
        if(empty($sdf) || empty($original_bn)) {
            $error_msg = '无效的换货拒绝参数';
            
            $this->__apilog['result']['msg'] = $error_msg;
            
            return false;
        }
        
        return $sdf;
    }
    
    /**
     * [淘宝极速换货]格式化换货拒绝传参
     *
     * @param $sdf
     * @return array
     */
    protected function _formatConsigngoodsRefuseParams($params)
    {
        self::trim($params);
        
        $sdf = array(
            'return_bn' => $params['dispute_id'], // 换货单号
            'seller_refuse_reason_id' =>  $params['seller_refuse_reason_id'], // 拒绝原因 ID
            'leave_message' =>  $params['leave_message'], // 商家补充说明
            'shop_id' => $this->__channelObj->channel['shop_id'],
        );
        
        return $sdf;
    }
}
