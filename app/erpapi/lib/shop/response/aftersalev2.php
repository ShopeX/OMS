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
 * @desc    售后接口数据转换
 * @author: jintao
 * @since: 2016/7/21
 */
class erpapi_shop_response_aftersalev2 extends erpapi_shop_response_abstract {

    protected $_change_return_type = false;
    protected $item_convert_field = [
        'sdf_field'     =>'outer_id',
        'order_field'   =>'bn',
        'default_field' =>'bn'
    ];

    static public $refund_status = array(
        'REFUND_WAIT_SELLER_AGREE'=>'0',
        'WAIT_SELLER_AGREE'=>'0',
        'WAIT_BUYER_RETURN_GOODS'=>'2',//卖家已经同意退款
        'SELLER_REFUSE_BUYER'=>'3',//卖家拒绝seller_refuse
        'CLOSED'=>'3',//退款关闭
        'SUCCESS'=>'4',//退款成功
        'WAIT_SELLER_CONFIRM_GOODS'=>'0',//买家已经退货 对应何流程？不处理
    );

    static public $return_status = array(
        'WAIT_SELLER_AGREE'=>'1',
        'WAIT_BUYER_RETURN_GOODS'=>'3',//卖家已经同意退款
        'SELLER_REFUSE_BUYER'=>'5',//卖家拒绝
        'CLOSED'=>'5',//退款关闭
        'SUCCESS'=>'4',//退款成功
        'WAIT_SELLER_CONFIRM_GOODS'=>'6',//买家已经退货
        'CONFIRM_FAILED'            =>  '5',
    );

    static public $reship_status = array(
        'confirm_failed'            =>  '5',
        'wait_buyer_return_goods'   =>  '0',
        'wait_seller_confirm_goods' =>  '1',
        'confirm_success'           =>  '7',
        'success'                   =>'0',
        '10009'                     =>  '1',
    );
    
    //平台退款的类型
    static public $tag_types = array(
        '价保退款' => '1', //天猫平台
        '返现退款' => '2', //天猫平台
        '赔付退款' => '3', //天猫平台
        '1' => '1', //抖音平台(价保退款单)
        '极速退款' => '5', //极速退款
        '售后仅退款' => '6',
        '发货前退款' => '7',
    );
    
    //平台订单状态
    protected $_sourceStatus = array();
    protected $refund_item_all = false;

    protected function _formatAddParams($params) {
        $sdf = array(
            'order_bn' => $params['tid'],
            'platform_order_bn' => $params['tid'], //平台订单号
            'refund_bn' => $params['refund_id'],
            'status' => $params['status'],
            'source_status' => $this->_sourceStatus[$params['source_status']] ? : $params['source_status'],
            'platform_status' => $params['source_status'], //平台退款状态
            'refund_fee' => $params['refund_fee'] ? sprintf('%.2f', $params['refund_fee']) : 0,
            'refund_type' => $params['refund_type'],
            'reason' => $params['reason'],
            'modified' => $params['modified'] ? kernel::single('ome_func')->date2time($params['modified']) : time(),
            'created' => $params['created'] ? kernel::single('ome_func')->date2time($params['created']) : time(),
            't_begin' => $params['t_begin'] ? kernel::single('ome_func')->date2time($params['t_begin']) : time(),
            'cur_money' => $params['cur_money'],
            'pay_type' => $params['pay_type'] ? $params['pay_type'] : 'online',
            'alipay_no' => $params['alipay_no'],
            'payment' => $params['payment'],
            'account' => $params['account'],
            'bank' => $params['bank'],
            'buyer_nick' => $params['buyer_nick'],
            'desc' => $params['desc'],
            'shipping_type' => $params['shipping_type'],
            'logistics_company' => $params['logistics_company'] ? $params['logistics_company'] : $params['company_name'],
            'logistics_no' => $params['logistics_no'] ? $params['logistics_no'] : $params['sid'],
            'pay_account' => $params['pay_account'],
            'has_good_return' => $params['has_good_return'] ? strtolower($params['has_good_return']) : '',
            'good_return_time' => $params['good_return_time'] ? strtotime($params['good_return_time']) : 0,
            'refund_item_list' => $params['refund_item_list'] ? json_decode($params['refund_item_list'],true) : [],
            'org_id' => $this->__channelObj->channel['org_id'],
            'refund_refer' => $params['refund_refer'], //退款来源(aftersale:售后仅退款,normal:普通)
            'extend_field' => $params['extend_field'] ? json_decode($params['extend_field'],true) : [],
            'from_platform' => $params['from_platform'],
        );
        
        if($params['platform_order_bn']){
            $sdf['platform_order_bn']    = $params['platform_order_bn'];
        }
        
        if($params['platform_aftersale_bn']){
            $sdf['platform_aftersale_bn']    = $params['platform_aftersale_bn'];
        }
        
        //组织架构ID
        if(isset($params['cos_id'])){
            $sdf['cos_id'] = $params['cos_id'];
        }
        
        //贸易公司ID
        if(isset($params['betc_id'])){
            $sdf['betc_id'] = $params['betc_id'];
        }
        
        $sdf['t_ready']    = $sdf['t_begin'];
        $sdf['t_sent']     = $sdf['modified'];
        
        //上一次已经作废掉的退货单号
        if($params['cancel_reship_bn']){
            $sdf['cancel_reship_bn'] = $params['cancel_reship_bn'];
        }
        //极速退款标识
        if($params['jsrefund_flag']){
            $sdf['jsrefund_flag'] = 1;
        }
        
        //客户实退
        if($sdf['extend_field'] && $sdf['extend_field']['real_refund_amount']) {
            $sdf['real_refund_amount'] = $sdf['extend_field']['real_refund_amount'];
        }
        self::trim($sdf);
        return $sdf;
    }

    /**
     * @param array $sdf
     * @param array $convert 例 array('sdf_field'=>'item_id','order_field'=>'shop_goods_id','default_field'=>'outer_id');
     * @return array 返回 以bn作主键的数组 捆绑商品使用捆绑商品的bn
     */

    protected function _formatAddItemList($sdf, $convert = array()) {
        if(empty($convert)) {
            return array();
        }
        $itemList = $sdf['refund_item_list']['return_item'];
        $sdfField = $convert['sdf_field'];
        $orderField = $convert['order_field'];
        $defaultField = $convert['default_field'];
        $arrOrderField = array();
        foreach($itemList as $val) {
            if($val[$sdfField]) {
                $arrOrderField[] = $val[$sdfField];
            }
        }
        $filter = array(
            $orderField => $arrOrderField,
            'order_id' => $sdf['order']['order_id']
        );
        $object = app::get('ome')->model('order_objects')->getList($orderField . ', bn, obj_id, quantity,`delete`', $filter);
        $arrBn = array();
        $arrQuantity = array();
        $arrObjId = array();
        foreach($object as $oVal) {
            if($oVal['delete'] == 'true') {
                $this->_refundOidIsDeleted = true;
            }
            $arrBn[$oVal[$orderField]] = $oVal['bn'];
            $arrQuantity[$oVal[$orderField]] = $oVal['quantity'];
            $arrObjId[$oVal[$orderField]] = $oVal['obj_id'];
        }
        $arrItem = array();
        foreach ($itemList as $item) {
            $item['bn'] = $arrBn[(string)$item[$sdfField]] ? $arrBn[(string)$item[$sdfField]] : $item[$defaultField];
            $item['bn'] = (string) $item['bn'];
            if($item['nums'] && !$item['num']) {
                $item['num'] = $item['nums'];
                unset($item['nums']);
            }
            if($this->refund_item_all) {
                $item['num'] = $arrQuantity[(string)$item[$sdfField]];
            }
            if($arrItem[$item['bn']]) {
                $arrItem[$item['bn']]['num'] += $item['num'];
            } else {
                $arrItem[$item['bn']] = $item;
            }
            if($arrObjId[(string)$item[$sdfField]]) {
                $arrItem[$item['bn']]['obj_id'][] = $arrObjId[(string)$item[$sdfField]];
            }
        }

        return $arrItem;
    }
    
    protected function _calculateAddPriceFromRefundFee($items, $sdf) {
        // 再根据退款金额重新计算单价
        $refundFee = $sdf['refund_fee'] ? $sdf['refund_fee'] : $sdf['return_product']['money'];
        $options = array (
                'part_total'  => $refundFee,
                'part_field'  => 'amount',
                'porth_field' => 'porth_field',
            );
        $items = kernel::single('ome_order')->calculate_part_porth($items, $options);
        foreach ($items as $i => $item) {
            $items[$i]['price'] = $item['num'] > 0 ? bcdiv($item['amount'], $item['num'], 2) :  0;
        }

        return $items;
    }

    protected function _getAddType($sdf) {
        return '';
    }
    
    protected function _refundApplyAdditional($sdf) {
        return array();
    }

    //退款单 退款申请单 数据转换
    protected function _refundAddSdf($sdf)
    {
        //拼多多、抖音平台退款未包含平台优惠特殊处理
        $sdf = $this->_formatRefundFee($sdf);
        $sdf['status'] = self::$refund_status[strtoupper($sdf['status'])];
        $refundApplyBn = $sdf['refund_bn'];
        if($sdf['reason']) {
            if(preg_match('/#(\d+)#/', $sdf['reason'],$matches)) {
                $refundApplyBn = $matches[1];
            }
            $sdf['reason'] = preg_replace('/#(\d+)#/', '', $sdf['reason']);
        }
        $shopId = $this->__channelObj->channel['shop_id'];
        
        // 退款申请单
        $refundApplyModel = app::get('ome')->model('refund_apply');
        $refundApply = $refundApplyModel->getList('apply_id,return_id,refund_apply_bn,refund_refer,status,money,payment,memo,addon,outer_lastmodify,reship_id,bool_type', array('refund_apply_bn'=>$refundApplyBn,'shop_id'=>$shopId), 0, 1);
        $sdf['refund_version_change'] = false;
        if($refundApply) {
            $sdf['refund_apply'] = $refundApply[0];
            if ($sdf['modified'] > $sdf['refund_apply']['outer_lastmodify']) {
                $sdf['refund_version_change'] = true;
            }
            $refundApplyModel->update([
                'source_status'   => kernel::single('ome_refund_func')->get_source_status($sdf['source_status'])], 
                ['apply_id'=>$sdf['refund_apply']['apply_id']]);
            //退款申请单被标记为仅退款不发起拦截
            if($sdf['refund_to_returnProduct'] && ($sdf['refund_apply']['bool_type'] & ome_refund_bool_type::__ONLY_REFUND)) {
                $sdf['refund_to_returnProduct'] = false;
            }
        }
        
        // 退款单
        $refundModel = app::get('ome')->model('refunds');
        $refund = $refundModel->getList('refund_id', array('refund_bn'=>$sdf['refund_bn'],'shop_id'=>$shopId));
        if($refund) {
            $sdf['refund'] = $refund[0];
        }
        
        $payment_cfg = $this->get_payment($sdf['payment'],$sdf['shop_type']);
        if($payment_cfg) {
            $sdf['payment'] = $payment_cfg['id'];
        }
        
        if ($sdf['status'] == '4' || $sdf['refund_type'] == 'refund') {
            $this->__apilog['title'] = '店铺(' . $this->__channelObj->channel['name'] . ')退款单，单号：' . $sdf['refund_bn'];
            $sdf['response_bill_type'] = 'refund';
            
            //单独只创建退款申请单、退款单,不编辑订单明细、也不更新订单金额
            //@todo：平台订单全额退款、已取消，同分同秒推送过来，OMS只更新订单为全额退款或取消订单，未创建退款单;
            if($sdf['order']['pay_status'] == '5' && bccomp($sdf['order']['payed'], $sdf['refund_fee'], 3) < 0){
                $sdf['response_bill_type'] = 'refundonly';
            }
            
        } else {
            $this->__apilog['title'] = '店铺(' . $this->__channelObj->channel['name'] . ')退款申请单，单号：' . $sdf['refund_bn'];
            if ($sdf['status'] == '0' || empty($sdf['refund_apply'])) {
                $sdf['table_additional'] = $this->_refundApplyAdditional($sdf);
            }
            $sdf['response_bill_type'] = 'refund_apply';
        }
        
        // 商责免费退：统一走 refundonly（不改订单金额），由 _dealRefundOnly 按 postFIL 分支处理
        if (!empty($sdf['is_shangze_free_return']) && $sdf['is_shangze_free_return'] === 'true') {
            // 设置为：refundonly，只创建退款申请单、退款单,不编辑订单明细、也不更新订单金额
            $sdf['response_bill_type'] = 'refundonly';
            
            // status
            if (!empty($sdf['shangze_platform_refunded'])) {
                $sdf['status'] = '4';
                $this->__apilog['title'] = '店铺(' . $this->__channelObj->channel['name'] . ')商责退运费自动完成，单号：' . $sdf['refund_bn'];
            } else {
                $this->__apilog['title'] = '店铺(' . $this->__channelObj->channel['name'] . ')商责退运费申请，单号：' . $sdf['refund_bn'];
                if ($sdf['status'] == '0' || empty($sdf['refund_apply'])) {
                    $sdf['table_additional'] = $this->_refundApplyAdditional($sdf);
                }
            }
        }
        
        return $sdf;
    }
    
    protected function _returnProductAdditional($sdf) {
        return array();
    }

    protected function _getOrderDelivery($sdf) {
        //获取订单关联的所有已发货的发货单(支持return_back追回状态)
        if ($sdf['order']['tran_type'] == 'archive'){
            $sql = "SELECT dord.delivery_id, d.branch_id, d.logi_no, d.logi_name FROM sdb_archive_delivery_order AS dord
                  LEFT JOIN sdb_archive_delivery AS d ON(dord.delivery_id=d.delivery_id)
                  WHERE dord.order_id='".$sdf['order']['order_id']."' AND d.status IN('succ','return_back')";
            $archive_delivery = kernel::database()->selectrow($sql);
            return $archive_delivery;
        }
        
        //获取订单关联的发货单(支持return_back追回状态)
        $sql = "SELECT dord.delivery_id, d.branch_id, d.logi_no, d.logi_name FROM sdb_ome_delivery_order AS dord
                  LEFT JOIN sdb_ome_delivery AS d ON(dord.delivery_id=d.delivery_id)
                  WHERE dord.order_id='".$sdf['order']['order_id']."'
                    AND (d.parent_id=0 OR d.is_bind='true')
                    AND d.disabled='false' AND d.status IN('succ','return_back')";
        $result = kernel::database()->select($sql);
        if(count($result) > 1 && !empty($sdf['refund_item_list'])) {
            $arrDelivery = array();
            foreach ($result as $key => $val) {
                $arrDelivery[$val['delivery_id']] = $val;
            }
            $productId = '';
            foreach($sdf['refund_item_list'] as $iVal) {
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

    protected function _returnProductReship($returnProductId, $orderId) {
        $rs = array();
        $oReship = app::get('ome')->model('reship');
        $field = 'reship_id,reship_bn,return_id,is_check,return_logi_name,return_logi_no,outer_lastmodify,branch_id,return_type,status,change_order_id,jsrefund_flag,flag_type';
        $reship = $oReship->getList($field, array('return_id' => $returnProductId), 0, 1);
        if($reship) {
            $rs['reship'] = $reship[0];
            $otherFilter = ' AND r.reship_id != ' . $rs['reship']['reship_id'];
        }
        $sql = "SELECT i.order_item_id,i.bn, i.num FROM sdb_ome_reship as r left join sdb_ome_reship_items as i on r.reship_id=i.reship_id WHERE i.return_type='return' AND r.is_check!='5' AND r.order_id='" . $orderId . "' " . $otherFilter;
        $otherReship = $oReship->db->select($sql);
        if($otherReship) {
            $rs['other_reship_items'] = $otherReship;
        }
        return $rs;
    }
    
    //检查售后申请单版本是否更新
    protected function _getReturnVersionChange($sdf)
    {
        $version_change = false;
        
        if ($sdf['modified'] > $sdf['return_product']['outer_lastmodify'] && ($sdf['return_product']['content']!=$sdf['reason']) || $sdf['return_product']['money']!=$sdf['refund_fee']) {
            $version_change = true;
        }
        
        return $version_change;
    }

    protected function _returnFreight($sdf) {
        return [];
    }
    
    //售后申请单数据转换
    protected function _returnProductAddSdf($sdf) {
        $this->__apilog['title'] = '店铺(' . $this->__channelObj->channel['name'] . ')售后申请单，单号：' . $sdf['refund_bn'];
        
        //场景：换货完成又进行退货
        if($sdf['change_order_id']){
            $this->__apilog['title'] = '换货完成后申请售后退货，申请单号：' . $sdf['refund_bn'];
        }
        
        $sdf['response_bill_type'] = 'return_product';
        $sdf['status'] = self::$return_status[strtoupper($sdf['status'])];
        $returnModel = app::get('ome')->model('return_product');
        $tgReturn = $returnModel->getList('*', array('shop_id'=>$sdf['shop_id'],'return_bn'=>$sdf['refund_bn']));
        $sdf['refund_version_change'] = false;
        $returnId = -1;
        if($tgReturn) {
            $sdf['return_product'] = $tgReturn[0];
            $returnId = $sdf['return_product']['return_id'];
            //版本是否更新
            $sdf['refund_version_change'] = $this->_getReturnVersionChange($sdf);


            if ($this->_change_return_type === true  && $sdf['return_product']['return_type'] =='change'){
                //$sdf['refund_version_change'] = true;
                //
                if(!in_array($sdf['status'],array('5','9'))){
                    $sdf['change_return_type'] = true;
                    //$sdf['status'] = '1';

                }
            }
        }
        $reshipData = $this->_returnProductReship($returnId, $sdf['order']['order_id']);
        if($reshipData['reship']) {
            $sdf['reship'] = $reshipData['reship'];
        }
        if($reshipData['other_reship_items']) {
            $sdf['other_reship_items'] = $reshipData['other_reship_items'];
        }
        
        $delivery = $this->_getOrderDelivery($sdf);
        if ($delivery) {
            $sdf['delivery_id'] = $delivery['delivery_id'];
            $sdf['branch_id'] = $delivery['branch_id'];
            $sdf['delivery_logistics_no'] = $delivery['logi_no'];
            if($sdf['refund_to_returnProduct']) {
                $sdf['logistics_company'] = $delivery['logi_name'];
                $sdf['logistics_no'] = $delivery['logi_no'];
            }
        }
        if($sdf['shop']['delivery_mode'] != 'jingxiao') {
            //取默认退货仓
            $return_auto_branch     = app::get('ome')->getConf('return.auto_branch');
            if ($return_auto_branch){
                $sdf['branch_id'] = $return_auto_branch;

            }
            $return_auto_shop_branch     = app::get('ome')->getConf('return.auto_shop_branch');
            if($return_auto_shop_branch[$sdf['shop_id']]) {
                $sdf['branch_id'] = $return_auto_shop_branch[$sdf['shop_id']];
            }
        }
        if ($sdf['status'] == '1' || !$sdf['return_product']) {
            // 如果前端传了会员名
            if ($sdf['buyer_nick']) {
                $shopMemberModel = app::get('ome')->model('shop_members');
                $member = $shopMemberModel->getList('member_id', array('shop_member_id' => $sdf['buyer_nick'], 'shop_id' => $sdf['shop_id']), 0, 1);
                $sdf['member_id'] = $member[0]['member_id'];
            } else {
                $sdf['member_id'] = $sdf['order']['member_id'];
            }
            $sdf['table_additional'] = $this->_returnProductAdditional($sdf);
            $sdf['table_return_freight'] = $this->_returnFreight($sdf);
        } else {
            $returnItemModel = app::get('ome')->model('return_product_items');
            $tgReturnItems = $returnItemModel->getList('*',array('return_id'=>$sdf['return_product']['return_id']));
            if (!$tgReturnItems) {
                $this->__apilog['result']['msg'] = '缺少明细';
                return false;
            }
            $sdf['refund_item_list'] = $tgReturnItems;
            $sdf['branch_id'] = $tgReturnItems[0]['branch_id'];
        }
        $sdf['choose_type_flag'] = 1;
        return $sdf;
    }

    protected function _reshipAddItemList($sdf) {
        $returnList = array();
        $itemsObj = app::get('ome')->model('return_product_items');
        $refundItemList = $itemsObj->getList('product_id,bn,name,num,price,order_item_id,amount',array('return_id'=>$sdf['return_product']['return_id']));
        if($refundItemList) {
            $items_ids = array();
            foreach($refundItemList as $var_ril){
                $items_ids[] = $var_ril["order_item_id"];
            }
            if(!empty($items_ids)){
                $mdl_order_items = app::get('ome')->model('order_items');
                $rs_items = $mdl_order_items->getList("item_id,sendnum",array("item_id"=>$items_ids));
                $rl_item_id_info = array();
                foreach($rs_items as $var_item){
                    $rl_item_id_info[$var_item["item_id"]] = $var_item["sendnum"];
                }
                foreach($refundItemList as $var_rfil){
                    $returnList[] = array(
                        "product_id" => $var_rfil["product_id"],
                        "bn" => $var_rfil["bn"],
                        "name" => $var_rfil["name"],
                        "num" => $var_rfil["num"],
                        "price" => $var_rfil["price"],
                        "amount" => $var_rfil["amount"],
                        "order_item_id" => $var_rfil["order_item_id"],
                        "sendNum" => $rl_item_id_info[$var_rfil["order_item_id"]],
                    );
                }
            }
        }
        return $returnList;
    }
    
    /**
     * 退后单数据转换
     * @param $sdf
     * @param $params 平台推送的原数据
     * @return array
     */
    protected function _reshipAddSdf($sdf, $params=null) {
        $this->__apilog['title'] = '店铺(' . $this->__channelObj->channel['name'] . ')退货单，单号：' . $sdf['refund_bn'];
        
        //场景：换货完成又进行退货
        if($sdf['change_order_id']){
            $this->__apilog['title'] = '换货完成后申请退货，申请单号：' . $sdf['refund_bn'];
        }
        
        $sdf['response_bill_type'] = 'reship';
        $sdf['status'] = self::$reship_status[strtolower($sdf['status'])];
        $sdf['shop_id'] = $this->__channelObj->channel['shop_id'];
        // 售后单
        $returnFilter = array('shop_id'=>$sdf['shop_id'],'return_bn'=>$sdf['refund_bn'],'source'=>'matrix');
        $returnModel = app::get('ome')->model('return_product');
        $tgReturn = $returnModel->getList('return_id,return_bn,delivery_id,status,money,is_fail,archive,return_type,outer_lastmodify,changebranch_id', $returnFilter, 0, 1);
        if (!$tgReturn) {
            //单拉售后单
            $returnRsp = kernel::single('erpapi_router_request')->set('shop', $sdf['shop_id'])->finance_getRefundDetail($sdf['refund_bn'],$sdf['refund_phase'], $sdf['order_bn']);
            if ($returnRsp['rsp'] == 'succ') {
                $msg = '';
                $rs = kernel::single('ome_return')->get_return_log($returnRsp['data'],$sdf['shop_id'],$msg);
                if ($rs) {
                    $tgReturn = $returnModel->getList('return_id,return_bn,delivery_id,status,money,is_fail,archive,return_type,outer_lastmodify,changebranch_id', $returnFilter, 0, 1);
                }
            }
        }
        if ($tgReturn[0]['archive'] == '1'){
            $sdf['archive'] = '1';
            $sdf['source'] = 'archive';

        }
        
        if (!$tgReturn) {
            //单拉售后单
            $this->__apilog['result']['msg'] = "售后申请单不存在,不可以创建退货单!";
            return false;
        }
        if($tgReturn[0]['is_fail'] == 'true') {
            $this->__apilog['result']['msg'] = "售后申请单处于失败状态,不可以创建退货单!";
            return false;
        }
        $sdf['return_product'] = $tgReturn[0];
        if(!$sdf['change_order_id']){
            $sdf['refund_item_list'] = $this->_reshipAddItemList($sdf);
        }

        $oDc = app::get('ome')->model('dly_corp');
        $dcData = $oDc->getList('name', array('corp_id'=>$sdf['order']['logi_id']), 0, 1);
        $sdf['logi_name'] = $dcData[0]['name'];
        $Odelivery = app::get('ome')->model('delivery');
        $deliveryinfo = $Odelivery->getList('branch_id', array('delivery_id'=>$sdf['return_product']['delivery_id']), 0, 1);
        if ($tgReturn[0]['archive'] == '1'){
            $deliveryinfo = app::get('archive')->model('delivery')->getList('branch_id', array('delivery_id'=>$sdf['return_product']['delivery_id']), 0, 1);
        }
        $sdf['branch_id'] = $deliveryinfo[0]['branch_id'];
        $reshipData = $this->_returnProductReship($sdf['return_product']['return_id'], $sdf['order']['order_id']);
        if($reshipData['reship']) {
            $sdf['reship'] = $reshipData['reship'];
        }
        if($reshipData['other_reship_items']) {
            $sdf['other_reship_items'] = $reshipData['other_reship_items'];
        }
    
        //@todo：客服拒绝退货后,平台介入或顾客上传凭证后,重新恢复退货;
        //场景：顾客申请退货，商家在天猫后台拒绝退货退款;顾客上传退货凭证后,平台自动同意退货申请,恢复原退货单；
        if($sdf['return_product']['status'] == '5' && $params){
            //判断是否允许恢复售后
            $isRecoverReturn = $this->_checkRecoverReturn($sdf);
            if($isRecoverReturn === true){
                //修改拒绝的售后申请单号和退换货单号
                $cancel_reship_bn = '';
                $isEditReturnBn = $this->_transformReturnBn($sdf, $cancel_reship_bn);
                if($isEditReturnBn === true){
                    //重新开始创建售后申请单
                    $params['refund_type'] = 'return';
                    $params['cancel_reship_bn'] = $cancel_reship_bn;
                
                    return $this->add($params);
                }
            }
        }
        
        return $sdf;
    }

    /**
     * 添加
     * @param mixed $params 参数
     * @return mixed 返回值
     */
    public function add($params){
        $this->__apilog['title'] = '店铺(' . $this->__channelObj->channel['name'] . ')售后业务处理[订单：' . $params['tid'] . ']';
        $this->__apilog['original_bn'] = $params['tid'];
        $this->__apilog['result']['data'] = array('tid'=>$params['tid'],'aftersale_id'=>$params['refund_id'],'retry'=>'false');
        
        //加判断yjdf非经销过来的不收
        if(in_array($this->__channelObj->channel['delivery_mode'], array('shopyjdf'))){
            if(!isset($params['from_platform']) || empty($params['from_platform']) ){
                $this->__apilog['result']['msg'] = '店铺为:一件代发,不接收非经销来源数据';
                return false;
            }
        }
        
        $sdf = $this->_formatAddParams($params);
        if(empty($sdf) || !is_array($sdf)) {
            if(!$this->__apilog['result']['msg']) {
                $this->__apilog['result']['msg'] = '没有数据,不接收售后单';
            }
            return false;
        }
        $shopId = $sdf['shop_id'] = $this->__channelObj->channel['shop_id'];
        $sdf['shop_type'] = $this->__channelObj->channel['shop_type'];
        $sdf['shop'] = $this->__channelObj->channel;
        $sdf['shop']['delivery_mode'] = $this->__channelObj->channel['delivery_mode'];
        $field = 'order_id,status,process_status,ship_status,pay_status,payed,cost_payment,pay_bn,member_id,logi_id,logi_no,ship_name,ship_area,ship_addr,ship_zip,ship_tel,ship_email,ship_mobile,shipping,is_protect,is_cod,source,order_type,createtime,abnormal,source_status,api_version,platform_order_bn,total_amount,sync,service_price';
        $tgOrder = $this->getOrder($field, $shopId, $sdf['order_bn'],'aftersale');

        if($tgOrder === 0) {
            $this->_dealRefundNoOrder($sdf);
            $this->__apilog['result']['msg'] = '没有订单' . $sdf['order_bn'];
            return false;
        }
        if($tgOrder['platform_order_bn']) {
            $sdf['platform_order_bn'] = $tgOrder['platform_order_bn'];
        }
        //删除退款日志
        if(!$tgOrder && in_array(strtoupper($sdf['status']), array('SELLER_REFUSE_BUYER', 'SUCCESS', 'CLOSED'))) {
            $filter = array(
                    'order_bn' => $sdf['order_bn'],
                    'shop_id' => $sdf['shop_id'],
                    'refund_bn' => $sdf['refund_bn']
            );
            app::get('ome')->model('refund_no_order')->delete($filter);
            $tgOrder = $this->getOrder($field, $shopId, $sdf['order_bn']);
        }
        
        //添加退款日志
        if (!$tgOrder) {
            if(!in_array(strtoupper($sdf['status']), array('SELLER_REFUSE_BUYER', 'SUCCESS', 'CLOSED'))) {
                $this->_dealRefundNoOrder($sdf);
            }
            $this->__apilog['result']['msg'] = '没有订单' . $sdf['order_bn'];
            return false;
        }
        
        $sdf['order'] = $tgOrder;

        // 兼容换了又退
        list($is_change, $change_msg, $change_order, $convert) = $changeRs = $this->getChangeReturnProduct($sdf);
        if($is_change === true){
            // OMS生成的新订单号
            $sdf['tid'] = $sdf['order_bn'] = $change_order['order_bn'];
            
            // OMS换货生成的新订单信息
            $sdf['order'] = $change_order;
        }

        // $this->__apilog['result']['data']['changeRs'] = $changeRs;


        $type = $this->_getAddType($sdf);
        if(empty($type)) {
            if(!$this->__apilog['result']['msg']) {
                $this->__apilog['result']['msg'] = '所属店铺类型,不接收售后单';
            }
            return false;
        }
        
        //未签收的售后仅退款转为售后退货
        if($type == 'refund' 
            && in_array($sdf['order']['ship_status'], ['1', '3'])
            && $sdf['order']['status'] == 'finish'
            && $sdf['order']['source_status'] != 'TRADE_FINISHED'
            && !(strpos($sdf['reason'], '运费') !== false || strpos($sdf['reason'], '买贵必赔') !== false || strpos($sdf['reason'], '保价') !== false || $sdf['tag_type'] == '1')
            && !(strpos($sdf['desc'], '换货转退款') !== false)
            && in_array(app::get('ome')->getConf('ome.reship.refund.only.reship'), ['true', 'refund'])
        ) {
            //[天猫定制订单]申请售后仅退款,不用转换拦截退货单
            if($sdf['order']['order_type'] == 'custom' && in_array($sdf['shop_type'], ['taobao', 'tmall'])){
                //不用转换为：returnProduct
            }else{
                $sdf['refund_to_returnProduct'] = true;
                if(app::get('ome')->getConf('ome.reship.refund.only.reship') != 'refund'){
                    $type = 'returnProduct';
                }
            }
        }
        
        //识别如果是已完成的售后，转成退款单更新的逻辑
        if(in_array($type, ['returnProduct','reship']) && (strtolower($sdf['status']) == 'success' || $sdf['jsrefund_flag']==1) ){
            $refundOriginalObj = app::get('ome')->model('return_product');
            $refundOriginalInfo = $refundOriginalObj->getList('return_id', array('return_bn'=>$sdf['refund_bn'],'status' =>'4') , 0 , 1);
            if($refundOriginalInfo){
                $refundApplyObj = app::get('ome')->model('refund_apply');
                $refundApplyInfo = $refundApplyObj->getList('refund_apply_bn', array('return_id'=>$refundOriginalInfo[0]['return_id'],'status' =>array('0','1','2','5','6')) , 0 , 1);
                if($refundApplyInfo){
                    $sdf['refund_bn'] = $refundApplyInfo[0]['refund_apply_bn'];
                    $sdf['tmall_has_finished_return_product'] = true;
                    $type = 'refund';
                    if($sdf['jsrefund_flag'] && strtolower($sdf['status']) != 'success'){
                        $sdf['status']='success';
                    }
                }
            }
        }
        $this->_refundOidIsDeleted = false;
        //获取订单上退货基础物料列表
        if(is_array($sdf['refund_item_list'])) {
            if(!$sdf['change_order_id']){
                $refundItemList = $this->_formatAddItemList($sdf);
                if(empty($refundItemList)) {
                    $sdf['refund_item_list'] = '';
                }else{
                    //根据申请退货商品关联退货订单明细
                    $sdf['refund_item_list'] = $this->_calculateAddPrice($refundItemList, $sdf);
                }
            } else {
                $rfItems = $sdf['refund_item_list'];
                foreach($rfItems as $k => $v) {
                    $rfItems[$k]['porth_field'] = $v['price'] * $v['num'];
                }
                $sdf['refund_item_list'] = $this->_calculateAddPriceFromRefundFee($rfItems, $sdf);
            }

        }
        //未签收的售后仅退款转为售后退货,若订单商品被删除，则重新生成退款单
        if($sdf['refund_to_returnProduct'] && $this->_refundOidIsDeleted) {
            $type = 'refund';
        }

        if($type == 'refund') {
            return $this->_refundAddSdf($sdf);
        } elseif( $type == 'returnProduct') {
            $returnSdf = $this->_returnProductAddSdf($sdf);
            
            //暂停换出订单
            if($returnSdf['reship']['change_order_id']){
                kernel::single('ome_return')->pauseChangeOrder($returnSdf['reship']['change_order_id']);                    
            }
            if(in_array($sdf['shop_type'], array('xhs','wxshipin')) && $returnSdf['return_product']['return_type'] == 'change'){
                $lubanLib = kernel::single('ome_reship_luban');
                $result = $lubanLib->transformExchange($returnSdf);
                if($result['rsp'] == 'succ'){
                    //原换货申请单已经拒绝,这里置为空;
                    $returnSdf['return_product'] = array();
            
                    //打标识,后面自动审核售后申请单,并且使用OMS本地生成的退货单号
                    $returnSdf['isTransformExchange'] = 'succeed';
                }else{
                    //作废换货单失败
                    $lubanLib->disposeExchangeBusiness($returnSdf);
            
                    //打失败标识
                    $returnSdf['isTransformExchange'] = 'fail';
                }
            }
    
            return $returnSdf;
        } elseif($type == 'reship') {
            return $this->_reshipAddSdf($sdf, $params);
        } else {
            if(!$this->__apilog['result']['msg']) {
                $this->__apilog['result']['msg'] = '不接收售后单';
            }
            return false;
        }
    }
    
    /**
     * 客服拒绝退货后,平台介入或顾客上传凭证后,重新恢复退货;
     * 场景：顾客申请退货，商家在天猫后台拒绝退货退款;顾客上传退货凭证后,平台自动同意退货申请,恢复原退货单；
     * 
     * @param $sdf
     * @return void
     */
    public function _checkRecoverReturn($sdf)
    {
        return false;
    }
    
    /**
     * 修改拒绝的售后申请单号和退换货单号
     * @param $sdf
     * @param $cancel_reship_bn 已经作废掉的退货单号
     * @return void
     */
    public function _transformReturnBn($sdf, &$cancel_reship_bn='')
    {
        $returnProductObj = app::get('ome')->model('return_product');
        $operateLog = app::get('ome')->model('operation_log');
        
        //params
        $refund_bn = $sdf['refund_bn'];
        
        //售后申请单信息
        if(empty($sdf['return_product'])){
            return false;
        }
        
        //售后申请单不是拒绝状态
        if($sdf['return_product']['status'] != '5'){
            return false;
        }
        
        //换货单信息
        if($sdf['reship']){
            //退换货单不是已取消状态
            if($sdf['reship']['is_check'] != '5'){
                return false;
            }
            
            $reship_id = $sdf['reship']['reship_id'];
            $reship_bn = $sdf['reship']['reship_bn'];
            
            //修改作废的退换货单号
            $cancel_reship_bn = $reship_bn .'-'. $reship_id;
            $update_sql = "UPDATE sdb_ome_reship SET reship_bn='". $cancel_reship_bn ."' WHERE reship_bn='". $reship_bn ."' AND is_check='5'";
            $returnProductObj->db->exec($update_sql);
            
            //log
            $operateLog->write_log('reship@ome', $reship_id, '平台恢复售后申请,修改拒绝的退换货单号为：'. $cancel_reship_bn);
        }
        
        //售后申请单信息
        $return_id = $sdf['return_product']['return_id'];
        $return_bn = $sdf['return_product']['return_bn'];
        
        //修改作废售后申请单号
        $cancel_return_bn = $return_bn .'-'. $return_id;
        $update_sql = "UPDATE sdb_ome_return_product SET return_bn='". $cancel_return_bn ."' WHERE return_bn='". $return_bn ."' AND status='5'";
        $returnProductObj->db->exec($update_sql);
        
        //log
        $operateLog->write_log('return@ome', $return_id, '平台恢复售后申请,修改售后申请单号为：'. $cancel_return_bn);
        
        //作废平台售后申请单号
        if(in_array($sdf['shop_type'], array('taobao', 'tmall')) && $sdf['shop_id']){
            $update_sql = "UPDATE sdb_ome_return_product_tmall SET return_bn='". $cancel_return_bn ."' WHERE shop_id='". $sdf['shop_id'] ."' AND return_bn='". $return_bn ."'";
            $returnProductObj->db->exec($update_sql);
        }
        
        return true;
    }
    
    protected function _formatLogisticsUpdate($params)
    {
        if (is_string($params['logistics_info'])) {
            $logistics_info = json_decode($params['logistics_info'], true);
            $process_data = array();
            $process_data['shipcompany'] = $logistics_info['logistics_company'];
            $process_data['logino'] = $logistics_info['logistics_no'];
        }
        $sdf = array(
            'order_bn'     => $params['tid'],
            'return_bn'    => $params['aftersale_id'],
            'process_data' => $process_data
        );
        return $sdf;
    }
    
    /**
     * logisticsUpdate
     * @param mixed $params 参数
     * @return mixed 返回值
     */
    public function logisticsUpdate($params) {
        $sdf = $this->_formatLogisticsUpdate($params);
        $this->__apilog['title'] = '前端店铺更新物流信息V1[售后单号：'.$sdf['return_bn'].' ]';
        $this->__apilog['original_bn'] = $sdf['order_bn'];
        $shopId = $this->__channelObj->channel['shop_id'];
        $sdf['node_type'] = $this->__channelObj->channel['node_type'];
        // 订单
        $orderModel = app::get('ome')->model('orders');
        $tgOrder = $orderModel->getList('order_id', array('order_bn'=>$sdf['order_bn'],'shop_id'=>$shopId));
        if (!$tgOrder) {
            $this->__apilog['result']['msg'] = '没有订单' . $sdf['order_bn'];
            return false;
        }
        $returnModel = app::get('ome')->model('return_product');
        $tgReturn = $returnModel->getList('return_id, process_data', array('return_bn'=>$sdf['return_bn'],'order_id'=>$tgOrder[0]['order_id']));
        if (!$tgReturn) {
            $this->__apilog['result']['msg'] = '没有售后申请单' . $sdf['return_bn'];
            return false;
        }
        $sdf['return_id'] = $tgReturn[0]['return_id'];
        $sdf['old_process_data'] = unserialize($tgReturn[0]['process_data']);
    
        return $sdf;
    }
    
    /**
     * [换货完成又退货]通过原平台订单找到换货生成的OMS新订单
     * 
     * @param $sdf
     * @return array
     */
    public function getChangeReturnProduct($sdf)
    {
        $orderObj = app::get('ome')->model('orders');
        $reshipMdl = app::get('ome')->model('reship');
        if($sdf['change_order_flag']) return false;
        //sdf
        // $oid        = $sdf['oid'];
        $shop_id    = $sdf['shop_id'];
        $order_bn   = $sdf['order_bn'];
        $refund_bn   = $sdf['refund_bn'];

        $orderFilter = [
            'shop_id' => $shop_id,
            'platform_order_bn' => $order_bn,
            'ship_status' => ['1','2','3','4'],
        ];

        // 判断是否数据已经存在
        $rp = app::get('ome')->model('return_product')->db_dump([
            'return_bn' => $refund_bn,
            'shop_id' => $shop_id,
        ], 'order_id');

        $field = 'order_id,order_bn,status,process_status,ship_status,pay_status,payed,cost_payment,pay_bn,member_id,logi_id,logi_no,ship_name,ship_area,ship_addr,ship_zip,ship_tel,ship_email,ship_mobile,shipping,is_protect,is_cod,source,order_type,createtime,abnormal,platform_order_bn,total_amount';
        if ($rp && $rp['order_id'] != $sdf['order']['order_id']){
            $order = $orderObj->db_dump(['order_id' => $rp['order_id']], $field);

            return [true, '匹配成功', $order];
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
            
            $returnDetails[$item['order_id']]['oid_list'][$oid] = $returnableNum;

            $returnDetails[$item['order_id']]['bn_list'][$bn] = $returnableNum;
        }

        foreach ($returnDetails as $order_id => $value){
            $oid_list = $value['oid_list'];
            $bn_list = $value['bn_list'];

            // 按OID查
            $match = true;
            foreach($sdf['refund_item_list']['return_item'] as $key => $return_item){
                if ($return_item['oid'] && $oid_list[$return_item['oid']] && intval($oid_list[$return_item['oid']]) >= intval($return_item['num'])) {
                    continue;
                }

                $match = false;
            }

            if ($match === true){
                return [true, '匹配成功', $orderList[$order_id]];
            }

            // 按货号查
            $match = true;
            foreach($sdf['refund_item_list']['return_item'] as $return_item){
                if ($return_item['outer_id'] && $bn_list[$return_item['outer_id']] && intval($bn_list[$return_item['outer_id']]) >= intval($return_item['num'])) {
                    continue;
                }

                $match = false;
            }

            if ($match === true){
                return [true, '匹配成功', $orderList[$order_id],$this->item_convert_field];
            }


        }
        
        return [false, '匹配失败'];
    }


    /**
     * 换货申请转退货申请
     * 
     * @param $sdf
     * @return array
     */
    public function getChangeApplyToReturnApply($sdf)
    {
        $returnMdl = app::get('ome')->model('return_product');
        $reshipMdl = app::get('ome')->model('reship');

        $refund_bn   = $sdf['refund_bn'];
        $shop_id    = $sdf['shop_id'];

        // 有进行中的换货申请
        $return = $returnMdl->db_dump([
            'return_bn' => $refund_bn,
            'return_type' => 'change',
            'status' => ['1','2','3','6','7','8'],
            'shop_id' => $shop_id,
        ]);
        if ($return) {

        }

        // 有进行中的退换货单
        $reship = $reshipMdl->db_dump([
            'reship_bn' => $refund_bn,
            'shop_id' => $shop_id,
            'return_type' => 'change',
            'is_check' => ['0', '1', '2'],
        ]);
        if ($reship) {

        }

        return [true, '请放心申请'];
    }
    
    /**
     * 获取订单退货商品及计算退货明细金额
     * 
     * @param $refundItems
     * @param $sdf
     * @return array
     */
    protected function _calculateAddPrice($refundItems, $sdf)
    {
        if(empty($refundItems)) {
            return array();
        }
        
        $orderMdl = app::get('ome')->model('orders');
        
        $orderLib = kernel::single('ome_order');
        
        //order
        $order = $sdf['order'];
        $order_id = $order['order_id'];
        
        //申请退货金额
        $refund_fee = ($sdf['refund_fee'] ? $sdf['refund_fee'] : $sdf['return_product']['money']);
        
        //订单详细信息
        $orderInfo = $orderMdl->dump($order_id, '*', array('order_objects'=>array('*',array('order_items'=>array('*')))));
        
        //[兼容微信小程序]一次申请多个不同SKU进行退货,分摊申请退货的金额
        if(count($refundItems) > 1){
            //获取订单销售物料价格
            $goodsList = array();
            $goodsDeleteList = array();
            if($orderInfo['order_objects']){
                foreach ($orderInfo['order_objects'] as $objKey => $objVal)
                {
                    $goods_bn = $objVal['bn'];
                    
                    //goods
                    if($objVal['delete'] == 'true'){
                        $goodsDeleteList[$goods_bn] = $objVal;
                    }else{
                        $goodsList[$goods_bn] = $objVal;
                    }
                }
            }
            
            //金额占比基数
            foreach ($refundItems as $itemKey => $itemVal)
            {
                if (in_array($orderInfo['shop_type'], ['website'])) {
                    $refundItems[$itemKey]['oid_refund_fee'] = $itemVal['amount'];
                } else {
                    //num
                    if(empty($itemVal['num'])){
                        $itemVal['num'] = 1;
                        $refundItems[$itemKey]['num'] = $itemVal['num'];
                    }
    
                    //price：京东平台推送价格字段为空值
                    if(empty($itemVal['price'])){
                        $goods_bn = isset($itemVal['bn']) ? $itemVal['bn'] : '';
        
                        //price
                        if(isset($goodsList[$goods_bn])){
                            $itemVal['price'] = $goodsList[$goods_bn]['price'];
                        }elseif(isset($goodsDeleteList[$goods_bn])){
                            $itemVal['price'] = $goodsDeleteList[$goods_bn]['price'];
                        }else{
                            $itemVal['price'] = 1;
                        }
        
                        $refundItems[$itemKey]['price'] = $itemVal['price'];
                    }
    
                    $refundItems[$itemKey]['porth_field'] = $itemVal['price'] * $itemVal['num'];
                }
                
            }
    
            if (!in_array($orderInfo['shop_type'], ['website'])) {
                //均摊退货的金额
                $options = array (
                    'part_total' => $refund_fee, //需要分摊的总额
                    'part_field' => 'oid_refund_fee', //需要分摊的字段
                    'porth_field' => 'porth_field', //作为基数的字段
                );
                $refundItems = kernel::single('ome_order')->calculate_part_porth($refundItems, $options);
            }
        }else{
            foreach ($refundItems as $itemKey => $itemVal)
            {
                $refundItems[$itemKey]['oid_refund_fee'] = $refund_fee;
            }
        }
        
        //order_objects
        $reutrnItems = array();
        $is_calculate = false;
        foreach ($orderInfo['order_objects'] as $objKey => $objVal)
        {
            $goods_bn = $objVal['bn'];
            $obj_quantity = $objVal['quantity'];
            
            //没有订单item明细
            if(empty($objVal['order_items'])){
                continue;
            }
            
            //check
            if($objVal['delete'] == 'true'){
                continue;
            }
            
            //退货销售物料不存在,直接跳过
            if(!isset($refundItems[$goods_bn])){
                continue;
            }
            $skuReturnInfo = $refundItems[$goods_bn];
            //如果和找到的obj行不一致，跳过
            if($skuReturnInfo['obj_id'] && !in_array($objVal['obj_id'],$skuReturnInfo['obj_id'])){
                continue;
            }
            //退货数量
            $return_num = intval($skuReturnInfo['num']);
            $return_num_radio = $return_num / $obj_quantity;
            
            //oid退货金额
            $oid_refund_fee = $skuReturnInfo['oid_refund_fee'];
            
            //使用订单明细上items层金额贡献比,这样更准确;
            //@todo：根据订单object层实付金额获取items层明细对应的金额占比;
            list($rateRs, $itemRates) = $orderLib->getItemRateByObject($objVal);
            
            //obj_type
            //if(in_array($objVal['obj_type'], array('pkg','pko','gift','lkb'))) {
            //    //是否重算无摊金额
            //    $is_calculate = true;
            //}
            
            //退货数量与购买数量一致&&退货金额与订单实付金额一致
            $temp_refund_fee = $oid_refund_fee;
            $item_line_i = 0;
            $item_count = count($objVal['order_items']);
            $can_unset_refund_item = true; // 控制是否可以删除refundItems的标记
            foreach($objVal['order_items'] as $itemKey => $itemVal)
            {
                $item_id = $itemVal['item_id'];
                $product_bn = $itemVal['bn'];
                
                $item_line_i++;
                
                //check
                if($itemVal['delete'] == 'true'){
                    continue;
                }
                
                //订单实付金额占比
                if($rateRs) {
                    $item_rate = $itemRates[$item_id];
                } else {
                    $item_rate = 1;
                }
                
                //检验申请退货数量(两种场景：1、与购买数量一致; 2、小于购买数量; )
                if($obj_quantity == $return_num){
                    //1、整退
                    $item_return_num = $itemVal['quantity'];
                    
                    //item_return_amount
                    if($item_line_i == $item_count){
                        $item_return_amount = $temp_refund_fee;
                    }else{
                        $item_return_amount = $oid_refund_fee * $item_rate;
                        $item_return_amount = sprintf('%.2f', $item_return_amount);
                        
                        $temp_refund_fee -= $item_return_amount;
                    }
                }else{
                    //2、部分退
                    $item_return_num = $itemVal['quantity'] * $return_num_radio;
                    $item_return_num = intval($item_return_num);
                    
                    //[兼容]福袋类型(防止：数量*贡献占比 = 0,导致代码报错)
                    //@todo：福袋购买多件销售物料时,分配到基础物料时,是可以分配给多个不同基础物料,每个1件;
                    if($item_return_num < 1){
                        $item_return_num = $itemVal['quantity'];
                    }
                    
                    //兼容小程序同一个商品，存在多行明细的情况
                    //因为_formatAddItemList里是按照bn sum的
                    if (in_array($orderInfo['shop_type'], ['website']) && $item_return_num > $itemVal['quantity']) {
                        $item_return_num               = $itemVal['quantity'];
                        $refundItems[$goods_bn]['num'] -= $itemVal['quantity'];
                        $can_unset_refund_item         = false; // 如果退货数量大于购买数量，不能删除refundItems
                    }
                    
                    //item_return_amount
                    if($item_line_i == $item_count){
                        $item_return_amount = $temp_refund_fee;
                    }else{
                        $item_return_amount = $oid_refund_fee * $item_rate;
                        $item_return_amount = sprintf('%.2f', $item_return_amount);
                        
                        $temp_refund_fee -= $item_return_amount;
                    }
                }
                
                //退货单价
                $item_price = sprintf('%.2f', $item_return_amount / $item_return_num);
                
                //data
                $tmpReturn = array(
                    'obj_type' => $objVal['obj_type'],
                    'item_type' => $itemVal['item_type'],
                    'product_id' => $itemVal['product_id'],
                    'bn' => $product_bn,
                    'name' => $itemVal['name'],
                    'price' => $item_price,
                    'amount' => $item_return_amount,
                    'porth_field' => $item_return_amount,
                    'num' => $item_return_num,
                    'sendNum' => $itemVal['sendnum'], //sendNum字母必须大写
                    'order_item_id' => $itemVal['item_id'],
                    'item_flag' => 'is_equal', //价格和数量相等标记
                );
                $tmpReturn = array_merge($skuReturnInfo, $tmpReturn);
                
                $reutrnItems[] = $tmpReturn;
            }
            
            //unset
            // 只有在所有item的退货数量都不大于购买数量时才删除refundItems
            if ($can_unset_refund_item) {
                unset($refundItems[$goods_bn]);
            }
        }
        
        //申请退货商品未找到订单item明细
        if($refundItems && is_array($refundItems)) {
            //product_bn
            $productBns = array_column($refundItems, 'bn');
            
            //material
            $materialList = kernel::single('material_basic_select')->getlist('bm_id,material_bn,material_name', array('material_bn'=>$productBns));
            if($materialList){
                $materialList = array_column($materialList, null, 'material_bn');
                
                //format
                foreach ($refundItems as $itemKey => $itemVal)
                {
                    $material_bn = $itemVal['bn'];
                    
                    //check
                    if(isset($materialList[$material_bn])){
                        $refundItems[$itemKey]['product_id'] = $materialList[$material_bn]['bm_id'];
                        $refundItems[$itemKey]['name'] = $materialList[$material_bn]['material_name'];
                    }
                }
            }
            
            //merge
            $reutrnItems = array_merge(array_values($refundItems), $reutrnItems);
        }
        
        //金额分摊
        if($is_calculate){
            $reutrnItems = $this->_calculateAddPriceFromRefundFee($reutrnItems, $sdf);
        }
        
        return $reutrnItems;
    }
    
    protected function _formatRefundFee($sdf) {
        return $sdf;
    }
}
