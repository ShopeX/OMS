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
 * sunjing@shopex.cn
 * 换货订单处理
 */
class erpapi_shop_response_process_exchange {

    /**
     * 添加
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */

    public function add($sdf) {
        $modelReturnProduct = app::get('ome')->model('return_product');
        if($sdf['return_product']) {
            $idBn = array(
                'return_id' => $sdf['return_product']['return_id'],
                'return_bn' => $sdf['return_product']['return_bn']
            );
            
            $appendMsg = '';
            
            //[抖音平台]换货转仅退款,并且平台已经退款完成
            $isExchangeRefund = $this->_checkExchangeRefund($sdf);
            if($isExchangeRefund){
                $sdf['status'] = '5';
                
                $appendMsg .= '(换货转仅退款,并且平台已经退款完成,OMS拒绝换货)';
            }
            
            // 换货地址
            $this->_dealExchangeReceiver($sdf['return_product']['return_id'], $sdf);
            //更新售后申请附加信息表
            $this->_dealTableAdditional($sdf['table_additional'], $idBn);
            
             //退转换处理
            kernel::single('ome_return')->processChange($sdf);

            //更新售后单
            $this->_returnProductUpdateStatus($sdf);

            $msg = '更新成功'. $appendMsg;
        } else {
            $insertData = $this->_returnProductSdfToData($sdf);

            $returnProductItems = $insertData['return_product_items'];
            unset($insertData['return_product_items']);
            $returnGiftItems = $insertData['return_gift_items'];
            unset($insertData['return_gift_items']);
            
            //抖音平台
            if(in_array($insertData['shop_type'],['luban'])){
                $lubanLib = kernel::single('ome_reship_luban');
                
                //换货转仅退款,并且平台已经退款完成
                $isExchangeRefund = $this->_checkExchangeRefund($sdf);
                if($isExchangeRefund){
                    return array('rsp'=>'fail', 'msg'=>'新建售后申请单失败:平台已经直接仅退款完成,不再创建换货申请单;');
                }
                
                //"仅退款"转换为"换货"申请时
                $result = $lubanLib->transformReturnProduct($insertData);
                if($result['rsp'] != 'succ'){
                    return array('rsp'=>'fail', 'msg'=>'新建售后申请单失败:'.$result['error_msg']);
                }
            }
            
            //insert
            $rs = $modelReturnProduct->insert($insertData);
            if(!$rs) {
                return array('rsp'=>'fail', 'msg'=>'售后申请单新建失败');
            }
            $this->_insertReturnProductItems($returnProductItems, $insertData['return_id']);
            //防止并发赠品多次携带到售后申请单明细，update阻塞查询判断。
//            $giftMsg = '';
//            if ($returnGiftItems) {
//                $giftOrderItemId = array_column($returnGiftItems, 'order_item_id');
//                $returnProductItemMdl = app::get('ome')->model('return_product_items');
//                $res = kernel::database()->exec("UPDATE sdb_ome_orders SET createtime=`createtime` WHERE order_id =".$insertData['order_id']);
//                if ($res['rs']) {
//                    if (!$returnProductItemMdl->getList('order_item_id',array('order_item_id'=>$giftOrderItemId))) {
//                        foreach($returnGiftItems as &$val) {
//                            $val['return_id'] = $insertData['return_id'];
//                        }
//                        $sql = ome_func::get_insert_sql($returnProductItemMdl, $returnGiftItems);
//                        $insertGiftRs = $returnProductItemMdl->db->exec($sql);
//                        $operateLog = app::get('ome')->model('operation_log');
//                        $giftMsg = '自动带出赠品成功';
//                        if (!$insertGiftRs['rs']) {
//                            $giftMsg = '自动带出赠品失败';
//                        }
//                    }
//                }
//            }
            
            app::get('ome')->model('operation_log')->write_log('return@ome',$insertData['return_id'],'创建售后申请单');
//            if ($giftMsg) {
//                $operateLog->write_log('return@ome', $insertData['return_id'], $giftMsg);
//            }
            $msg = '创建售后申请单成功';
            $this->_dealExchangeReceiver($insertData['return_id'], $sdf);
            $idBn = array(
                'return_id' => $insertData['return_id'],
                'return_bn' => $insertData['return_bn']
            );
            $this->_dealTableAdditional($sdf['table_additional'], $idBn);
            //提前冻结库存
            kernel::single('ome_return_product')->addExchangeFreeze($insertData['return_id'], $sdf['branch_id']);
            //自动审核售后申请单
            $is_auto_approve = app::get('ome')->getConf('aftersale.auto_approve');
            $is_gift_auto_approve = app::get('ome')->getConf('aftersale.gift_auto_approve');
            //有赠品根据开发判断是否字段审核
            $isHaveGift = array_column((array) $returnProductItems,'item_type');
            $isAutoGiftApprove = true;
            if ($isHaveGift && in_array('gift',$isHaveGift)) {
                if ($is_auto_approve == 'on'  && $is_gift_auto_approve != 'on' ) {
                    $isAutoGiftApprove = false;
                }
            }
            if($sdf['status']=='1' && $is_auto_approve == 'on' && $isAutoGiftApprove){
                $sdf['status'] = '3';

                // 同步平台状态
                $sdf['sync_platform'] = true;                

                //[抖音平台]推送同意换货状态给平台
                if($insertData['shop_type'] == 'luban'){
                    
                    //[京东一件代发]必须有京东寄件地址才能推送同意状态给抖音平台
                    $branchLib = kernel::single('ome_branch');
                    $wms_type = $branchLib->getNodetypBybranchId($sdf['branch_id']);
                    if(in_array($wms_type, array('yjdf'))){
                        $sdf['sync_platform'] = false;
                    }
                    
                }
            }
            
            //审核售后申请单
            if (in_array($sdf['status'],array('3','5','6'))) {
                $sdf['return_id'] = $insertData['return_id'];
                $sdf['status'] = in_array($sdf['status'],array('3','4','6')) ? '3' : $sdf['status'];
                $this->_returnProductUpdateStatus($sdf);
            } else {
                //[未开启]售后申请单自动审批,提前冻结库存
                kernel::single('ome_return_product')->addExchangeFreeze($insertData['return_id'], $sdf['branch_id']);
            }
            
        }
        
        //退换货单自动审核
        if($sdf['status'] == '3'){
            /***
             * todo: 换货接口不自动推送队列审核，手工审核比较好.
             ***/
            $is_auto_approve = app::get('ome')->getConf('return.auto_approve');
            if($is_auto_approve == 'on'){
                $Oreship = app::get('ome')->model('reship');
                $reshipLib = kernel::single('ome_reship');
                
                //获取reship_id
                $return_id = ($sdf['return_product'] ? $sdf['return_product']['return_id'] : $insertData['return_id']);
                $reshipdata = $Oreship->dump(array('return_id'=>$return_id, 'is_check'=>'0'), 'reship_id');
                
                //处理退换货单
                if($reshipdata['reship_id']){
                    $result = $reshipLib->batch_reship_queue($reshipdata['reship_id']);
                }
            }
        }
        
        return array('rsp'=>'succ', 'msg' => $msg);
    }

    protected function _dealExchangeReceiver($return_id, $sdf) {
        if($sdf['buyer_province']) {
            $data = [
                'return_id'             => $return_id,
                'buyer_nick'            =>  $sdf['buyer_nick'],
                'buyer_name'            =>  $sdf['buyer_name'],
                'buyer_address'         =>  $sdf['buyer_address'],
                'buyer_province'        =>  $sdf['buyer_province'],
                'buyer_city'            =>  $sdf['buyer_city'],
                'buyer_district'        =>  $sdf['buyer_district'],
                'buyer_town'            =>  $sdf['buyer_town'],
                'buyer_phone'           =>  $sdf['buyer_phone'],
                'encrypt_source_data'   =>  $sdf['index_field'],
            ];
            app::get('ome')->model('return_exchange_receiver')->db_save($data);
        }
    }



    private function _dealTableAdditional($tableAdditional, $idBn) {

        if(empty($tableAdditional) || empty($idBn)) {
            return false;
        }
        $model = app::get('ome')->model($tableAdditional['model']);
        $data = array_merge($tableAdditional['data'], $idBn);

        $model->db_save($data);
    }


    private function _dealReturnProduct($sdf) {

    }

    private function _returnProductSdfToData($sdf) {

        $opInfo = kernel::single('ome_func')->get_system();
        $data = array(
            'return_bn'  => $sdf['return_bn'],
            'shop_id'    => $sdf['shop_id'],
            'member_id'  => $sdf['member_id'],
            'order_id'   => $sdf['order']['order_id'],
            'title'      => $sdf['order_bn'].'售后申请单',
            'content'    => $sdf['reason'],
            'comment'    => $sdf['desc'],
            'add_time'   => $sdf['created'],
            'status'     => '1',
            'platform_status' => $sdf['platform_status'], //平台售后单状态
            'op_id'      => $opInfo['op_id'],
            'refundmoney'=> '0',
            'money'      => '0',
            'shipping_type'=> $sdf['shipping_type'],
            'source'     => 'matrix',
            'shop_type'  => $sdf['shop_type'],
            'outer_lastmodify'=>$sdf['modified'],
            'delivery_id'=> $sdf['delivery_id'],
            'apply_remark'=> isset($sdf['apply_remark']) ? $sdf['apply_remark'] : '',
            'return_type'=>'change',
            'kinds' => 'change',
            'org_id' => $sdf['org_id'],
            'memo'      =>  $sdf['memo'],
        );
        
        //平台订单号
        if($sdf['platform_order_bn']){
            $data['platform_order_bn'] = $sdf['platform_order_bn'];
        }elseif(isset($sdf['order']['order_bn']) && $sdf['order']['order_bn']){
            $orderLib = kernel::single('ome_order');
            $rootOrderInfo = $orderLib->getRootOrderInfo($sdf['order']);
            if($rootOrderInfo){
                //根订单号
                $data['platform_order_bn'] = $rootOrderInfo['root_order_bn'];
            }
        }
        
        //新换货标识
        if($sdf['attributes'] && $sdf['attributes']['newExchangeRepair'] == '1'){
            $data['flag_type'] = ome_reship_const::__NEW_EXCHANGE_REPAIR;
        }

        // 经销店铺的单据，delivery_mode冗余到售后申请表
        $shop_info = app::get('ome')->model('shop')->getShopInfo($data['shop_id']);
        if ($shop_info['delivery_mode'] == 'jingxiao') {
            $data['delivery_mode'] = $shop_info['delivery_mode'];
        }

        $refund_fee = 0;
        foreach($sdf['return_items'] as $val) {
            $val['branch_id']               =       $sdf['branch_id'];
            $data['return_product_items'][] =       $val;
            $refund_fee+=$val['num']*$val['price'];
        }
        $data['return_gift_items'] = app::get('ome')->model('reship')->addReturnGiftItems($data['return_product_items'],$data['order_id'],$sdf['branch_id']);
        $data['refundmoney'] = $data['money'] = $refund_fee;
        
        if ($sdf['reason']) {
            $problemMdl = app::get('ome')->model('return_product_problem');
            $problem = $problemMdl->db_dump(['problem_name' => $sdf['reason']]);
            if (!$problem) {
                $problem = [
                    'problem_name' => $sdf['reason'],
                    'last_sync_time' => time(),
                    'createtime' => time(),
                ];
                $problemMdl->save($problem);
            }
            $data['problem_id'] = $problem['problem_id'];
        }

        // custom 修复未匹配归档字段的问题
        if (isset($sdf['archive'])) {
            $data['archive'] = $sdf['archive'];
        }
    
        return $data;
    }

    private function _insertReturnProductItems($returnProductItems, $returnId) {
        if(empty($returnId) || empty($returnProductItems)) {
            return false;
        }
        foreach($returnProductItems as &$val) {
            $val['return_id'] = $returnId;
        }
        $modelItem = app::get('ome')->model('return_product_items');
        $sql = ome_func::get_insert_sql($modelItem, $returnProductItems);
        $rs = $modelItem->db->exec($sql);
        return $rs['rs'];
    }
    
    /**
     * 更新售后申请单
     * 
     * @param $sdf
     * @return void
     */
    private function _returnProductUpdateStatus($sdf)
    {
        $operateLog = app::get('ome')->model('operation_log');
        $modelReturnProduct = app::get('ome')->model('return_product');
        $reshipObj = app::get('ome')->model('reship');
        
        $reshipLib = kernel::single('ome_reship');
        
        $returnProduct = $sdf['return_product'];
        switch($sdf['status']) {
            case '1':
                //[抖音平台]"退货"转换为"换货"申请时
                //@todo：顾客在平台上修改成换货申请,OMS自动拒绝原退货单,并且新创建换货单
                if(in_array($returnProduct['shop_type'],['luban'])){
                    if(in_array($returnProduct['return_type'], array('return'))){
                        $lubanLib = kernel::single('ome_reship_luban');
                        
                        $result = $lubanLib->transformExchange($sdf);
                        
                        //单据已被拒绝并修改单号后,自动创建新的售后申请单
                        if($result['action'] == 'refuse_return'){
                            
                            unset($sdf['return_product']);
                            
                            $this->add($sdf);
                        }elseif($result['rsp'] == 'fail'){
                            //请求WMS取消退货单失败,打标记
                        }
                    }
                }elseif($returnProduct && in_array($returnProduct['shop_type'], array('tmall', 'taobao'))){
                    //@todo：[兼容更新换货数量]天猫平台现在允许顾客修改售后单退货数量
                    if($sdf['version_change'] && $sdf['isModifyExchangeNum']){
                        $this->_updateReturnItems($sdf);
                    }
                    
                    //sdf
                    $updateSdf = array(
                        'platform_status' => $sdf['platform_status'],
                    );
                    
                    //修改换出商品
                    if($sdf['is_modify_exchange_bn']){
                        $error_msg = '';
                        $editResult = $reshipLib->updateReshipExchangeItems($sdf, $error_msg);
                        if($editResult){
                            $operateLog->write_log('return@ome', $returnProduct['return_id'], '修改换货商品：'. $sdf['change_items'][0]['bn']);
                        }else{
                            $operateLog->write_log('return@ome', $returnProduct['return_id'], '修改换货商品：'. $sdf['change_items'][0]['bn'] .'，修改失败：'. $error_msg);
                        }
                        
                        //更新时间
                        $updateSdf['outer_lastmodify'] = $sdf['modified'];
                    }
                    
                    //update
                    $modelReturnProduct->update($updateSdf, array('return_id'=>$returnProduct['return_id']));
                }else{
                    if($sdf['version_change'] && $returnProduct['status']=='5'){
                        $error_msg = '';
                        $data = array(
                            'status'    => $sdf['status'],
                            'return_id' => $returnProduct['return_id'] ? $returnProduct['return_id'] : $sdf['return_id'],
                            'outer_lastmodify' => $sdf['modified'],
                            'content'       =>$sdf['reason'],
                        );
                        $modelReturnProduct->tosave($data, true, $error_msg);
                    }
                }
                
                break;
            case '3':
                //修改换出商品
                if($sdf['is_modify_exchange_bn']){
                    $error_msg = '';
                    $editResult = $reshipLib->updateReshipExchangeItems($sdf, $error_msg);
                    if($editResult){
                        $operateLog->write_log('return@ome', $returnProduct['return_id'], '更新换货商品：'. $sdf['change_items'][0]['bn']);
                    }else{
                        $operateLog->write_log('return@ome', $returnProduct['return_id'], '更新换货商品：'. $sdf['change_items'][0]['bn'] .'，修改失败：'. $error_msg);
                    }
                }
                
                //data
                $data = array(
                    'status'            => $sdf['status'],
                    'platform_status' => $sdf['platform_status'], //平台售后单状态
                    'buyer_address'     => $sdf['buyer_address'],
                    'buyer_name'        => $sdf['buyer_name'],
                    'buyer_nick'        => $sdf['buyer_nick'],
                    'logistics_no'      => $sdf['logistics_no'],
                    'logistics_company' => $sdf['logistics_company'],
                    'buyer_phone'       => $sdf['buyer_phone'],
                    'desc'              => $sdf['desc'],
                    'reason'            => $sdf['reason'],
                    'return_id'         => $returnProduct['return_id'] ? $returnProduct['return_id'] : $sdf['return_id'],
                    'outer_lastmodify'  => $sdf['modified'],
                    'choose_type_flag'  => '1',
                );
                
                //是否推送平台：同意换货状态,默认:不用同步给平台同意状态;
                $is_sync_status = true;
                if($sdf['sync_platform'] === true){
                    $is_sync_status = false; //同步给平台同意状态
                }
                kernel::single('ome_return_product')->releaseChangeFreeze($data['return_id']);
                $modelReturnProduct->tosave($data, $is_sync_status, $error_msg);

                break;
            case '4':
            case '6':
                if ($sdf['reship']) {
                    $this->_updateReshipLogistics($sdf);
                }
                
                //修改换出商品
                if($sdf['is_modify_exchange_bn']){
                    $error_msg = '';
                    $editResult = $reshipLib->updateReshipExchangeItems($sdf, $error_msg);
                    if($editResult){
                        $operateLog->write_log('return@ome', $returnProduct['return_id'], '更新换货商品：'. $sdf['change_items'][0]['bn']);
                    }else{
                        $operateLog->write_log('return@ome', $returnProduct['return_id'], '更新换货商品：'. $sdf['change_items'][0]['bn'] .'，修改失败：'. $error_msg);
                    }
                }
                
                //如果没有退货单的情况下
                if (!$sdf['reship'] && $sdf['return_product']['status']<3){//售后申请单状态不为拒绝
                    $data = array(
                        'status'    => '3',
                        'platform_status' => $sdf['platform_status'], //平台售后单状态
                        'return_id' => $returnProduct['return_id'],
                        'outer_lastmodify' => $sdf['modified'],
                        'choose_type_flag' => '1',
                    );
                    kernel::single('ome_return_product')->releaseChangeFreeze($data['return_id']);
                    $modelReturnProduct->tosave($data, true, $error_msg);
                }
                $upData = array();//array('status'=>'4');
                
                //平台售后单状态
                if($sdf['platform_status']){
                    $upData['platform_status'] = $sdf['platform_status'];
                }
                if($sdf['real_refund_amount']) {
                    $upData['real_refund_amount'] = $sdf['real_refund_amount'];
                }
                
                $rs = $modelReturnProduct->update($upData, array('return_id'=>$returnProduct['return_id'], 'status|noequal'=>'4'));
                if(is_bool($rs)) {
                    break;
                }
                $operateLog->write_log('return@ome', $returnProduct['return_id'],'线上已完成,请进行收货/质检等操作');
                if($sdf['shop']['delivery_mode'] == 'jingxiao') {
                    $reship = $reshipObj->db_dump(['return_id'=>$returnProduct['return_id']], 'reship_id,is_check');
                    $reship_id = $reship['reship_id'];
                    if($reship['is_check'] == '0') {
                        $reshipObj->update(['is_check'=>'1'], ['reship_id'=>$reship_id]);
                        $sql = 'update sdb_ome_reship_items set normal_num = num where reship_id="'.$reship_id.'" and return_type="return"';
                        $reshipObj->db->exec($sql);
                    }
                    if ($reshipObj->finish_aftersale($reship_id)) {
                        kernel::single('console_reship')->siso_iostockReship($reship_id);
                    }
                }
                break;
            case '5':
                $error_msg = '';
                $data = array(
                    'status' => $sdf['status'],
                    'platform_status' => $sdf['platform_status'], //平台售后单状态
                    'return_id' => $returnProduct['return_id'] ? $returnProduct['return_id'] : $sdf['return_id'],
                    'outer_lastmodify' => $sdf['modified'],
                );
                $modelReturnProduct->tosave($data, true, $error_msg);
                kernel::single('ome_return_product')->releaseChangeFreeze($data['return_id']);
                // 同步拒绝退货单
                if ($sdf['reship']){
                    $reship = $sdf['reship'];

                    if($reship['change_order_id']){
                        kernel::single('ome_return')->pauseChangeOrder($reship['change_order_id']);
                        
                    }
                    $rs = console_reship::notice($reship);
                    console_reship::cancel($reship,'线上取消');
                    
                    //sdf
                    $updateSdf = array('platform_status'=>$sdf['platform_status']);
                    
                    //update
                    $reshipObj->update($updateSdf, array('reship_id'=>$sdf['reship']['reship_id']));
                }
                break;
            case '10':
                //卖家拒绝退款
                $updateSdf = array(
                    'platform_status' => $sdf['platform_status'],
                    'outer_lastmodify' => $sdf['modified'],
                    'last_modified' => time(),
                );
                $modelReturnProduct->update($updateSdf, array('return_id'=>$returnProduct['return_id']));
                
                //logs
                $operateLog->write_log('return@ome', $returnProduct['return_id'], '卖家在平台上拒绝退款');
                
                //更新OMS退货单平台状态
                if($sdf['reship']) {
                    //sdf
                    $updateSdf = array('platform_status'=>$sdf['platform_status']);
                    
                    //update
                    $reshipObj->update($updateSdf, array('reship_id'=>$sdf['reship']['reship_id']));
                    
                    //logs
                    $operateLog->write_log('reship@ome', $sdf['reship']['reship_id'], '卖家在平台上拒绝退款');
                }
                break;
            default:
                break;
        }
    }



    private function _updateReshipLogistics($sdf){
        $reship = $sdf['reship'];
        $logisticsCompany = $sdf['logistics_company'];
        $logisticsNo = $sdf['logistics_no'];
        if($reship){
            $is_update_logi = false;
            if ($reship && !$sdf['reship']['return_logi_name'] && (!$sdf['reship']['return_logi_no'] || $sdf['reship']['return_logi_no']=='None') &&  $logisticsNo) {
                $memo ='更新物流公司:'.$logisticsCompany.',物流单号:'.$logisticsNo;
                $upData = array(
                    'return_logi_name'=>$logisticsCompany,
                    'return_logi_no'=>$logisticsNo,
                    'outer_lastmodify'=>$sdf['modified'],
                );
                
                //flag
                $is_update_logi = true;
            }
            if ($sdf['version_change'] && $sdf['reship']['is_check']=='5' && $sdf['status']=='6'){//版本变化
                //未审核 接收申请
                $upData['is_check'] = '0';
                $upData['outer_lastmodify'] = $sdf['modified'];
                $memo.="版本变化,状态更新为未审核状态";
                $returnData= array('status'=>'3','outer_lastmodify'=>$sdf['modified']);
            }
            if ($upData){
                //更新平台售后状态
                $upData['platform_status'] = $sdf['platform_status'];
                
                //update
                $rs = app::get('ome')->model('reship')->update($upData,array('reship_id'=>$reship['reship_id']));
                $operateLog = app::get('ome')->model('operation_log');
                $operateLog->write_log('reship@ome',$reship['reship_id'],$memo);
                // 物流单号重复检测打标（flag_type 位）
                if ($reship['reship_id']) {
                    kernel::single('ome_reship')->markDuplicateReturnLogiNo($reship['reship_id'], $logisticsNo);
                }
                if ($returnData){
                    app::get('ome')->model('return_product')->update($returnData,array('return_id'=>$sdf['return_product']['return_id']));
                    $operateLog->write_log('return@ome', $sdf['return_product']['return_id'],'版本变化更新成接受申请状态');
                }
                
                //请求WMS更新最新物流单号
                if($is_update_logi){
                    //退换货自动审批(系统-->退换货自动审核设置-->是否启用退换货自动审批)
                    $is_auto_approve = app::get('ome')->getConf('return.auto_approve');
                    if($is_auto_approve == 'on'){
                        kernel::single('ome_reship')->batch_reship_queue($sdf['reship']['reship_id']);
                    }
                    foreach(kernel::servicelist('erpapi.service.aftersale.update.reship.logistics.after') as $object) {
                        if(method_exists($object, 'after_update_logistics')){
                            $object->after_update_logistics($reship['reship_id']);
                        }
                    }
                    $error_msg = '';
                    kernel::single('ome_reship')->request_wms_returnorder($reship['reship_id'], $error_msg);
                }
            }
            
            //[京东云交易]更新顾客退货物流单号
            if($reship['branch_id']){
                $branchLib = kernel::single('ome_branch');
                $wms_type = $branchLib->getNodetypBybranchId($reship['branch_id']);
                if($wms_type == 'yjdf'){
                    $queueObj = app::get('base')->model('queue');
                    
                    //放入queue队列中执行
                    $queueData = array(
                            'queue_title' => '退货单：'. $reship['reship_bn'] .'自动更新京东云交易退货物流信息',
                            'start_time' => time(),
                            'params' => array(
                                    'sdfdata' => array('reship_id'=>$reship['reship_id'], 'order_id'=>$reship['order']['order_id']),
                                    'app' => 'oms',
                                    'mdl' => 'reship',
                            ),
                            'worker' => 'ome_reship_kepler.syncLogisticInfo',
                    );
                    $queueObj->save($queueData);
                }
            }
            
        }

        return false;
    }
    
    /**
     * 更新退货数量
     * 
     * @param array $sdf
     * @return bool
     */
    protected function _updateReturnItems($sdf)
    {
        $returnItemModel = app::get('ome')->model('return_product_items');
        
        //check
        if(empty($sdf['return_items']) || empty($sdf['return_product'])){
            return false;
        }
        
        //上次申请退货的数量
        $return_id = $sdf['return_product']['return_id'];
        $returnItems = $returnItemModel->getList('item_id,bn,num',array('return_id'=>$return_id));
        if(empty($returnItems)){
            return false;
        }
        
        $returnItems = array_column($returnItems, null, 'bn');
        
        //update
        $editBns = array();
        foreach($sdf['return_items'] as $val)
        {
            $product_bn = $val['bn'];
            $apply_nums = intval($val['num']);
            
            //check
            if(empty($apply_nums)){
                continue;
            }
            
            if(empty($returnItems[$product_bn])){
                continue;
            }
            
            if($returnItems[$product_bn]['num'] == $apply_nums){
                continue;
            }
            
            $editBns[] = '货号：'.$product_bn.',数量：'. $returnItems[$product_bn]['num'] .'->'. $apply_nums;
            
            //更新退货数量
            $returnItemModel->update(array('num'=>$apply_nums), array('item_id'=>$returnItems[$product_bn]['item_id']));
        }
        
        //log
        if($editBns){
            $operateLog = app::get('ome')->model('operation_log');
            $operateLog->write_log('return@ome', $return_id, '更新退货数量【'. implode(';', $editBns) .'】');
        }
        
        return true;
    }

    /**
     * [抖音平台]换货转仅退款,并且平台已经退款完成
     * @todo: 场景：商家未及时处理换货单,导致抖音平台直接给顾客退款;
     * 
     * @param array $sdf
     * @return bool
     */
    public function _checkExchangeRefund($sdf)
    {
        //shop_type
        if(!in_array($sdf['shop_type'], array('luban'))){
            return false;
        }
        
        if($sdf['exchange_status']['after_sale_status'] == 12 && $sdf['exchange_status']['refund_status'] == 3){
            return true;
        }
        
        return false;
    }

}
