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
 * ============================
 * @Author:   yaokangming
 * @Version:  1.0
 * @DateTime: 2020/10/10 11:36:45
 * @describe: 类
 * ============================
 */
class erpapi_wms_matrix_yjdf_response_delivery extends erpapi_wms_response_delivery
{
    /**
     * status_update
     * @param mixed $params 参数
     * @return mixed 返回值
     */

    public function status_update($params)
    {
        $deliveryObj = app::get('ome')->model('delivery');
        $dlyItemObj = app::get('ome')->model('delivery_items');
        $packageObj = app::get('ome')->model('delivery_package');
        $foreignObj = app::get('console')->model('foreign_sku');
        $branchMdl  = app::get('ome')->model('branch');
        $orderItemMdl   = app::get('ome')->model('order_items');
        $orderObjectMdl = app::get('ome')->model('order_objects');
        $queueObj = app::get('base')->model('queue');
        
        $dly_logi_code = '';
        $dly_logi_bn = '';
        
        $data = parent::status_update($params);
        
        //发货单信息
        $fields = 'delivery_id,delivery_bn,status,shop_id,branch_id,is_wms_gift,original_delivery_bn,wms_channel_id';
        $delivery = $deliveryObj->db_dump(array('delivery_bn'=>$data['delivery_bn']), $fields);
        if(empty($delivery)) {
            $this->__apilog['result']['msg'] = '没找到对应的发货单';
            return false;
        }
        
        $data['delivery_id'] = $delivery['delivery_id'];

        if(empty($params['oid'])) {
            $this->__apilog['result']['msg'] = '缺少子包裹号';
            return false;
        }
        
        //物流包裹单数据
        $dlyBillData = array_merge($delivery, $data);
        $dlyBillData['package_bn'] = $params['oid'];
        
        $dlyBillData['items'] = array();
        if($dlyBillData['status']=='delivery'){
            //items
            if($params['item']){
                $dlyBillData['items'] = json_decode($params['item'], true);
            }
            
            //config
            $dlyBillData['crop_config'] = array(
                    'change_logi_off' => $this->__channelObj->wms['crop_config']['change_logi_off'], //转换物流公司开关
                    'change_channel_id' => $this->__channelObj->wms['crop_config']['change_channel_id'], //指定渠道ID
                    'change_logi_code' => $this->__channelObj->wms['crop_config']['change_logi_code'], //指定物流公司
                    'delivery_mode' => $this->__channelObj->wms['crop_config']['delivery_mode'], //发货回写方式
            );
        }
        
        //保存物流包裹单(放入queue队列中执行)
        $queueData = array(
                'queue_title' => '发货单号['. $delivery['delivery_bn'] .']保存物流包裹单',
                'start_time' => time(),
                'params' => array(
                        'sdfdata' => $dlyBillData,
                        'app' => 'console',
                        'mdl' => 'delivery',
                ),
                'worker' => 'console_delivery.saveDeliveryBill',
        );
        $queueObj->save($queueData);
        
        //发货单有赠品的标识
        $dly_is_wms_gift = $delivery['is_wms_gift'];

        // 用户已签收
        if($data['status'] == 'sign'){
            $data['oid'] = $params['oid'];
            
            //签收时间
            $data['sign_time'] = ($params['opeTime'] ? $params['opeTime'] : $params['date']); //年-月-日 时:分:秒
            
            //京东云交易发货已签收,自动审核对应的退货单
            //放入queue队列中执行
            $queueData = array(
                    'queue_title' => '发货单号：'. $data['delivery_bn'] .'已签收自动审核退货单',
                    'start_time' => time(),
                    'params' => array(
                            'sdfdata' => array('delivery_id'=>$delivery['delivery_id'], 'delivery_bn'=>$data['delivery_bn']),
                            'app' => 'oms',
                            'mdl' => 'reship',
                    ),
                    'worker' => 'ome_reship_kepler.autoDlyConfirmReship',
            );
            $queueObj->save($queueData);
            
            $this->__apilog['title'] = $this->__channelObj->wms['channel_name'] .'发货单['. $params['delivery_bn']. ']已签收';
            return $data;
        }elseif($data['status'] == 'payed'){
            //京东子订单已支付
            $this->__apilog['title'] = $this->__channelObj->wms['channel_name'] .'发货单['. $params['delivery_bn']. ']已支付';
            return $data;
        }

        // 追回包裹 BEGIN -----------8<
        if($data['status'] == 'cancel' && $delivery['status']=='succ'){
            $this->__apilog['title'] = $this->__channelObj->wms['channel_name'] .'发货单['. $params['delivery_bn']. ']拦截成功';
            
            $data['status']     = 'return_back';
            $data['reship_bn']  = $params['oid'];
            
            $package_items = $packageObj->getList('bn,number,logi_no,logi_bn', ['delivery_id' => $delivery['delivery_id'], 'package_bn' => $params['oid'], 'status' => 'delivery']);

            if (!$package_items) {
                $this->__apilog['result']['msg'] = '包裹子单未发货';
                return false;
            }

            // 更新状态
            $packageObj->update(['status' => 'return_back'], ['delivery_id' => $delivery['delivery_id'], 'package_bn' => $params['oid']]);

            if (!$packageObj->count(['delivery_id' => $delivery['delivery_id'], 'status' => 'delivery'])) {
                $deliveryObj->update(['status' => 'return_back'], ['delivery_id' => $delivery['delivery_id']]);
            }
            
            //[兼容]检查一个商品购买多件京东云交易拆分成多个子订单
            $product_bns = array_column($package_items, 'bn');
            $otherItems = $packageObj->getList('package_id,bn', array('delivery_id'=>$delivery['delivery_id'], 'bn'=>$product_bns, 'status'=>'delivery'));
            if($otherItems){
                $this->__apilog['result']['msg'] = '相同货号：'. $otherItems[0]['bn'] .'没有追回,不能AG自动退款';
                return false;
            }
            
            $order = $deliveryObj->getOrderBnbyDeliveryId($delivery['delivery_id']);

            if (app::get('o2o')->is_installed()) {
                $returnBranchLib = kernel::single('o2o_return_branch');
                $returnBranchCtx = $returnBranchLib->buildContext($delivery, array(
                    'shop_id'     => $delivery['shop_id'],
                    'order_id'    => $order['order_id'],
                    'return_type' => 'return',
                    'flag_type'   => ome_reship_const::__LANJIE_RUKU,
                    'source'      => 'delivery_back',
                ));
                $resolved = $returnBranchLib->resolve($returnBranchCtx);
                if ($resolved['branch_id'] <= 0) {
                    $this->__apilog['result']['msg'] = $resolved['branch_name'] ?: '未设置默认退货仓';
                    return false;
                }
                $branch_id = $resolved['branch_id'];
            } else {
                //取默认退货仓
                $branch_id     = app::get('ome')->getConf('return.auto_branch');
                $return_auto_shop_branch     = app::get('ome')->getConf('return.auto_shop_branch');
                if($return_auto_shop_branch[$delivery['shop_id']]) {
                    $branch_id = $return_auto_shop_branch[$delivery['shop_id']];
                }
                if (!$branch_id){
                    $this->__apilog['result']['msg'] = '未设置默认退货仓';
                    return false;
                }
            }
            $data['branch_id']  = $branch_id;
            $data['branch']     = $branchMdl->db_dump($branch_id,'branch_id,branch_bn');

            $items = [];
            foreach ($package_items as $key => $value) {
                $order_item   = $orderItemMdl->db_dump(['order_id' => $order['order_id'], 'bn' => $value['bn'], 'delete' => 'false'], 'obj_id');
                $order_object = $orderObjectMdl->db_dump(['obj_id' => intval($order_item['obj_id']) ], 'oid');

                $items[] = [
                    'order_id'             => $order['order_id'],
                    'order_bn'             => $order['order_bn'],
                    'oid'                  => $order_object['oid'],
                    'bn'                   => $value['bn'],
                    'num'                  => $value['number'],
                    'ccnum'                => 0,
                    'logistics_no'         => $value['logi_no'],
                    'company_code'         => $value['logi_bn'],
                ];
            }

            $data['items']    = $items;
            $data['order_id'] = $order['order_id'];

            return $data;
        }
        //追回包裹 END -----------8< 

        if(!in_array($delivery['status'], array('ready','progress'))) {
            //处理京东赠品
            //@todo场景：京东云交易主品已经全部发货，赠品最后才推送物流信息、父单与子单关系;
            $consoleDlyLib = kernel::single('console_delivery');
            
            $delivery['wms_id'] = $data['wms_id'];
            $consoleDlyLib->disposeKeplerGift($delivery, $params);
            
            //apilog
            $this->__apilog['result']['msg'] = '发货单已经处理完成';
            
            return false;
        }
        
        //发货单明细
        $deliveryId = $delivery['delivery_id'];
        $dlyItems = $dlyItemObj->getList('product_id,bn,number,is_wms_gift', array('delivery_id'=>$deliveryId));
        
        $gift_list = array();
        $skuIdNum = array();
        $dlySkuList = array();
        foreach ($dlyItems as $v)
        {
            $product_bn = $v['bn'];
            
            //过滤WMS赠送的赠品(这段判断可删除)
            if($v['is_wms_gift'] == 'true'){
                continue;
            }
            
            $skuIdNum[$v['product_id']] += $v['number'];
            
            $dlySkuList[$product_bn] = $v['product_id'];
        }
        
        //开启事务
        $transaction = kernel::database()->beginTransaction();
        
        //更新同步状态
        $sync_status = ($data['status'] == 'delivery' ? '12' : '3');
        
        //更新主表防止并发导致同一个包裹被记录两次
        if(empty($delivery['original_delivery_bn'])){
            $deliveryObj->update(array('last_modified'=>time(), 'sync_status'=>$sync_status, 'sync_msg'=>'', 'original_delivery_bn'=>$params['oid']), array('delivery_id'=>$deliveryId));
        }else{
            $deliveryObj->update(array('last_modified'=>time(), 'sync_status'=>$sync_status, 'sync_msg'=>''), array('delivery_id'=>$deliveryId));
        }
        
        $oid = $params['oid'];
        $old = $packageObj->getList('package_id', array('delivery_id'=>$deliveryId, 'package_bn'=>$oid));
        $items = $params['item'] ? json_decode($params['item'],true) : array();
        
        //包裹
        if($old){
            $packageIds = array_map('current', $old);
            
            if($data['status'] == 'return_back'){
                //拦截包裹发货的场景(没有item明细数据)
                $packageObj->update(array('status'=>$data['status']), array('package_id'=>$packageIds, 'package_bn'=>$oid));
            }elseif($data['status'] == 'cancel'){
                //[发货前]取消发货单
                $packageObj->update(array('status'=>$data['status']), array('package_id'=>$packageIds, 'package_bn'=>$oid));
            }else{
                //完成发货、取消发货的场景
                foreach ($items as $v)
                {
                    $outer_sku = $v['product_bn'];
                    
                    $v['logistics'] = trim($v['logistics']);
                    $v['logistics'] = str_replace(array('"', "'"), '', $v['logistics']);
                    
                    //京东云交易赠送的赠品
                    if($v['type'] == 'gift'){
                        $gift_list[] = $v;
                    }elseif(empty($v['type'])){
                        //[兼容]京东云交易没有传赠品类型
                        if(empty($dlySkuList[$outer_sku])){
                            $v['type'] = 'gift';
                            
                            $gift_list[] = $v; //不存在发货单明细上,则为赠品
                        }
                    }
                    
                    //[映射WMS仓储物流编码]兼容京东传的物流单号是ID
                    if($v['logistics']){
                        $v['logistics'] = $this->getLogiCode($v['logistics'], $data['wms_id']);
                        
                        //物流公司编码
                        $data['logi_id'] = $v['logistics'];
                    }
                    
                    //update
                    $filter = array(
                            'package_id' => $packageIds,
                            'package_bn' => $oid,
                            'outer_sku' => $v['product_bn'],
                    );
                    
                    $saveData = array(
                            'status' => $data['status'],
                            'logi_bn' => $v['logistics'],
                            'logi_no' => $v['logi_no'],
                    );
                    
                    if ($data['status'] == 'cancel_fail') {
                        $saveData['status'] = 'accept';
                    }elseif($data['status'] == 'delivery'){
                        //更新发货时间
                        $saveData['delivery_time'] = time();
                    }elseif($data['status'] == 'accept'){
                        //[兼容]已是DELIVERY发货状态,不允许更新为ACCEPT接收状态
                        //@todo：防止并发同分同秒推送DELIVERY、ACCEPT状态;
                        $filter['status|noequal'] = 'delivery';
                    }
                    
                    $packageObj->update($saveData, $filter);
                }
                
                //如果有子单取消父单
                if (isset($params['rootOrderId']) && !empty($params['rootOrderId']) && $params['rootOrderId'] != '0') {
                    $packageObj->update(['status' => 'cancel'],['package_bn'=>$params['rootOrderId']]);
                }
            }
        }else{
            //订单ID
            $orderSql = "SELECT order_id FROM sdb_ome_delivery_order WHERE delivery_id=".$deliveryId;
            $tempInfo = $deliveryObj->db->selectrow($orderSql);
            $order_id = intval($tempInfo['order_id']);
            
            //insert
            $inData = array();
            foreach ($items as $v)
            {
                $product_bn = $v['product_bn'];
                
                $v['logistics'] = trim($v['logistics']);
                $v['logistics'] = str_replace(array('"', "'"), '', $v['logistics']);
                
                //filter
                $filter = array(
                        'inner_product_id' => array_keys($skuIdNum),
                        'wms_id' => $data['wms_id'],
                        'outer_sku' => $v['product_bn'],
                );
                
                //临时兼容,京东云交易修正后,删除这段代码
                //[临时兼容]场景:主品和赠品全部都是gift赠品类型,使用price价格判断;
                if($v['type'] == 'gift' && $dlySkuList[$product_bn]){
                    if(isset($v['price']) && $v['price'] > 0){
                        $v['type'] == 'goods';
                    }
                }
                
                //[WMS京东云交易]赠送的赠品
                if($v['type'] == 'gift'){
                    $gift_list[] = $v;
                    
                    //删除发货单上商品ID
                    unset($filter['inner_product_id']);
                }
                
                $sku = $foreignObj->db_dump($filter, 'inner_product_id,inner_sku,outer_sku');
                
                //[兼容]没有映射关系,直接读取OMS系统基础物料信息
                if(empty($sku)) {
                    $sku = $this->getBasicMaterial($filter['outer_sku']);
                }
                
                //check
                if(empty($sku)) {
                    //[兼容]WMS赠品OMS系统没有,直接保存货号
                    if($v['type'] == 'gift'){
                        $sku = array(
                                'inner_product_id' => 0,
                                'inner_sku' => $v['product_bn'],
                                'outer_sku' => $v['product_bn'],
                        );
                    }else{
                        kernel::database()->rollBack();
                        $this->__apilog['result']['msg'] = '发货单没有此物料：'.$v['product_bn'];
                        return false;
                    }
                }
                
                //[映射WMS仓储物流编码]兼容京东传的物流单号是ID
                if($v['logistics']){
                    $v['logistics'] = $this->getLogiCode($v['logistics'], $data['wms_id']);
                    
                    //物流公司编码
                    $data['logi_id'] = $v['logistics'];
                }
                
                //发货时间
                $delivery_time = 0;
                if($data['status'] == 'delivery'){
                    //更新发货时间
                    $delivery_time = time();
                }
                
                $inData[] = array(
                    'delivery_id' => $deliveryId,
                    'order_id' => $order_id,
                    'package_bn' => $oid,
                    'logi_bn' => $v['logistics'],
                    'logi_no' => $v['logi_no'],
                    'product_id' => $sku['inner_product_id'],
                    'bn' => $sku['inner_sku'],
                    'outer_sku' => $sku['outer_sku'],
                    'status' => $data['status'],
                    'number' => $v['num'],
                    'create_time' => time(),
                    'delivery_time' => $delivery_time,
                    'is_wms_gift' => ($v['type']=='gift' ? 'true' : 'false'),
                    'main_sku_id' => $v['main_sku_id'], //关联主sku
                );
            }
            
            $sql = ome_func::get_insert_sql($packageObj, $inData);
            $packageObj->db->exec($sql);
            
            //如果有子单取消父单
            if (isset($params['rootOrderId']) && !empty($params['rootOrderId']) && $params['rootOrderId'] != '0') {
                $packageObj->update(['status' => 'cancel'],['package_bn'=>$params['rootOrderId']]);
                
                //[兼容]取消父单后,使用Queue队列进行DELIVERY发货
                //@todo：取消父单时,防止京东同分同秒推送DELIVERY、ACCEPT状态;
                $childData = array(
                        'delivery_id' => $deliveryId,
                        'delivery_bn' => $data['delivery_bn'],
                        'package_bn' => $oid,
                        'status' => $data['status'],
                );
                $this->_deliveryChildOrder($childData, $params);
            }
        }
        
        //事务提交
        kernel::database()->commit($transaction);
        
        //[WMS京东云交易]标识发货单存在赠品
        if($gift_list && $dly_is_wms_gift != 'true'){
            $deliveryObj->update(array('is_wms_gift'=>'true'), array('delivery_id'=>$deliveryId));
        }
        
        //check
        if(!in_array($data['status'], array('delivery','cancel','return_back'))){
            return $data;
        }
        
        //[发货前]京东订单取消判断
        if($data['status'] == 'cancel'){
            $pack_sql = "SELECT package_id FROM sdb_ome_delivery_package WHERE delivery_id=". $deliveryId ." AND status NOT IN('cancel', 'return_back')";
            $packInfo = $packageObj->db->selectrow($pack_sql);
            if($packInfo){
                $msg = '京东订单号未全部取消，等待全部取消';
                
                $this->__apilog['result']['msg'] = $msg;
                
                $deliveryObj->update(array('sync_msg'=>$msg), array('delivery_id'=>$deliveryId));
                
                return false;
            }
            
            return $data;
        }
        
        //京东订单包裹数量
        $packageRows = $packageObj->getList('product_id,number,is_wms_gift,status',array('delivery_id'=>$deliveryId,'status'=>$data['status']));
        $packagePN = array();
        foreach ($packageRows as $v)
        {
            //过滤WMS赠送的赠品
            if($v['is_wms_gift'] == 'true'){
                continue;
            }
            
            $packagePN[$v['product_id']] += $v['number'];
        }
        
        //临时兼容,京东云交易修正后,删除这段代码
        //[兼容]场景:顾客买A商品,京东云交易送的也是A赠品,推送给OMS时,主品和赠品全部都是gift赠品类型;
        if($packageRows && empty($packagePN)){
            return $data;
        }
        
        //update
        foreach ($skuIdNum as $pId => $num)
        {
            if($num > $packagePN[$pId]){
                if($data['status'] == 'cancel'){
                    $statusMsg = '取消';
                }elseif($data['status'] == 'return_back'){
                    $statusMsg = '追回';
                }else{
                    $statusMsg = '发货';
                }
                
                $msg = '发货单未全部'.$statusMsg.'，等待全部'.$statusMsg;
                $this->__apilog['result']['msg'] = $msg;
                
                $deliveryObj->update(array('sync_msg'=>$msg), array('delivery_id'=>$deliveryId));
                
                return false;
            }
        }
        
        //[兼容]京东云交易会传多个物流单号以,逗号分隔的场景
        if($data['status'] == 'delivery'){
            //OMS只需取一个物流单号
            $tempData = explode(',', $data['logi_no']);
            $data['logi_no'] = trim($tempData[0]);
        }
        
        //[特殊处理]指定转换物流公司
        if($data['status']=='delivery' && strtoupper($data['logi_id'])=='JD' && $delivery['wms_channel_id']){
            $change_logi = $this->__channelObj->wms['crop_config']['change_logi_off']; //转换物流公司开关
            $change_channel_id = trim($this->__channelObj->wms['crop_config']['change_channel_id']); //指定渠道ID
            $change_logi_code = $this->__channelObj->wms['crop_config']['change_logi_code']; //指定物流公司
            
            //转换物流开启&&指定渠道ID&&设置了转换物流公司
            if($change_logi=='1' && $change_channel_id==$delivery['wms_channel_id'] && $change_logi_code){
                $data['logi_id'] = $change_logi_code;
                
                //log
                $operLogObj = app::get('ome')->model('operation_log');
                $operLogObj->write_log('delivery_modify@ome', $delivery['delivery_id'], '自动转换物流公司为['. $change_logi_code .']');
                
            }
        }
        
        return $data;
    }
    
    /**
     * 映射WMS仓储物流编码
     * @todo：兼容京东传的物流单号是ID
     * 
     * @param string $logi_code
     * @return string
     */
    public function getLogiCode($logi_code, $wms_id)
    {
        $relationObj = app::get('wmsmgr')->model('express_relation');
        
        $logiMapInfo = $relationObj->dump(array('wms_id'=>$wms_id, 'wms_express_bn'=>$logi_code), 'sys_express_bn');
        
        $logi_code = ($logiMapInfo['sys_express_bn'] ? $logiMapInfo['sys_express_bn'] : $logi_code);
        
        return $logi_code;
    }
    
    /**
     * [兼容]基础物料分配映射关系被删除,直接读取OMS系统基础物料信息
     * 
     * @param string $material_bn
     * @return array
     */
    public function getBasicMaterial($material_bn)
    {
        $basicMaterialObj = app::get('material')->model('basic_material');
        
        if(empty($material_bn)){
            return false;
        }
        
        $sku = $basicMaterialObj->dump(array('material_bn'=>$material_bn), 'bm_id,material_bn');
        if(empty($sku)){
            return false;
        }
        
        $result = array(
                'inner_product_id' => $sku['bm_id'],
                'inner_sku' => $sku['material_bn'],
                'outer_sku' => $sku['material_bn'],
        );
        
        return $result;
    }
    
    /**
     * 京东取消父单后,使用Queue队列进行子订单DELIVERY发货
     * @todo：防止京东同分同秒推送DELIVERY、ACCEPT状态;
     */
    public function _deliveryChildOrder($childData, $responeParams)
    {
        //check
        if(empty($childData['package_bn'])){
            return false;
        }
        
        if($childData['status'] != 'delivery'){
            return false;
        }
        
        $queueObj = app::get('base')->model('queue');
        
        //params
        unset($childData['status']);
        $responeParams = array_merge($childData, $responeParams);
        
        //保存物流包裹单(放入queue队列中执行)
        $queueData = array(
                'queue_title' => '京东订单号['. $childData['package_bn'] .']子单发货并取消父单',
                'start_time' => time(),
                'params' => array(
                        'sdfdata' => $responeParams,
                        'app' => 'console',
                        'mdl' => 'delivery',
                ),
                'worker' => 'console_delivery_kepler.responseChildJdOrder',
        );
        $queueObj->save($queueData);
        
        return true;
    }
}