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

class wms_receipt_delivery{

    //前端操作发货单的动作状态定义
    //取消
    const __CANCEL = 1;

    //暂停
    const __PAUSE = 2;

    //发货
    const __DELIVERY = 3;

    //恢复
    const __RENEW = 4;

    /**
     *
     * 发货通知单创建方法
     * @param array $data 发货通知单数据信息
     */
    public function create(&$sdf,&$msg = ''){
        //数据组织与格式化
        //$data = $sdf;

        //主表信息
        $data['delivery_bn'] = $this->gen_id();

        //外部发货通知单号，必要且唯一
        $data['outer_delivery_bn'] = $sdf['outer_delivery_bn'];

        $data['idx_split']  = $sdf['idx_split'];
        $data['skuNum']     = $sdf['skuNum'];
        $data['itemNum']    = $sdf['itemNum'];
        $data['bnsContent'] = $sdf['bnsContent'];

        $data['delivery_group'] = $sdf['delivery_group'];
        $data['sms_group'] = $sdf['sms_group'];

        $data['member_id'] = $sdf['member_id'];

        $data['is_protect'] = $sdf['is_protect'] ? $sdf['is_protect'] : 'false';
        $data['cost_protect'] = $sdf['cost_protect'] ? $sdf['cost_protect'] : '0';
        $data['is_cod'] = $sdf['is_cod'];
        
        $data['delivery_model'] = $sdf['delivery']; //配送方式

        $data['logi_id'] = $sdf['logi_id'];
        $data['logi_name'] = $sdf['logi_name'];
        //$data['logi_no'] = '';
        //$data['logi_number'] = 1;
        //$data['delivery_logi_number'] = 0;

        //收货人信息
        $data['consignee'] = $sdf['consignee'];

        $data['create_time'] = time();
        //$data['status'] = 'ready';
        $data['memo'] = $sdf['memo'];
        $data['branch_id'] = $sdf['branch_id'];

        $data['net_weight'] = $sdf['net_weight'] ? $sdf['net_weight'] : 0.000;
        //$data['status'] = '';

        $data['delivery_cost_expect'] = $sdf['delivery_cost_expect'];
        //$data['delivery_cost_actual']

        $data['bind_key'] = $sdf['bind_key'];

        //是普通发货单还是原样寄回发货单
        $data['type'] = $sdf['type'];
        $data['shop_id'] = $sdf['shop_id'];
        $data['order_createtime'] = $sdf['order_createtime'];
        $data['op_id'] = $sdf['op_id'];
        $data['op_name'] = $sdf['op_name'];
        $data['shop_type'] = $sdf['shop_type'];
        //小标和物流升级服务
        $data['bool_type'] = $sdf['bool_type'];
        $data['cpup_service'] = $sdf['cpup_service'];
        $data['promised_collect_time'] = $sdf['promised_collect_time'];
        $data['promised_sign_time'] = $sdf['promised_sign_time'];
        $data['cpup_addon'] = $sdf['cpup_addon'];

        //明细表信息，映射item_id的对应
        foreach($sdf['delivery_items'] as $kkk => $item){
            $sdf['delivery_items'][$kkk]['outer_item_id'] = $item['item_id'];
            unset($sdf['delivery_items'][$kkk]['item_id']);
        }

        $data['delivery_items'] = $sdf['delivery_items'];
        //如果是暂停状态
        if ($sdf['pause'] && $sdf['pause'] == 'true'){
            $data['status']= '2';
        }

        if ($sdf['sub_logi_nos']){
            $data['logi_number'] = count($sdf['sub_logi_nos'])+1;
        }

        $deliveryObj = app::get('wms')->model('delivery');
        $dlyItemObj = app::get('wms')->model('delivery_items');

        if($deliveryObj->save($data)){
            $sdf['delivery_id'] = $data['delivery_id'];

            $bill_info = array(
                'delivery_id' => $sdf['delivery_id'],
                'net_weight' => $data['net_weight'],
            );
            if ($sdf['logi_no']) $bill_info['logi_no'] = $sdf['logi_no'];
            $deliveryBillObj = app::get('wms')->model('delivery_bill');
            $deliveryBillObj->save($bill_info);

            //多包裹存储
            if($sdf['sub_logi_nos']){
                $waybillObj       = app::get('logisticsmanager')->model('waybill');
                $dlycorpObj         = app::get('ome')->model("dly_corp");
                $dly_corp = $dlycorpObj->dump($sdf['logi_id'],'channel_id');

                foreach($sdf['sub_logi_nos'] as $k => $sub_logi_no){
                    $sub_bill_info = array(
                        'delivery_id' => $sdf['delivery_id'],
                        'logi_no' => $sub_logi_no,
                        'type' => 2,
                    );
                    $deliveryBillObj->save($sub_bill_info);

                    if($dly_corp['channel_id'] && strtolower($sdf['shop_type']) == 'aikucun'){
                        $waybillObj->update(array('channel_id'=>$dly_corp['channel_id']),array('waybill_number'=>$sub_logi_no));
                    }
                    unset($sub_bill_info);
                }
            }

            //调用保质期分配Lib预占保质期信息，如果失败发货单回滚不创建
            $storageLifeReceiptLib = kernel::single('material_receipt_storagelife');
            $storageLifeLib = kernel::single('material_storagelife');
            $dlyItemsSLReceiptLib = kernel::single('wms_receipt_dlyitemsstoragelife');

            $storagelife_data['branch_id'] = $data['branch_id'];
            $storagelife_data['bill_id'] = $data['delivery_id'];
            $storagelife_data['bill_bn'] = $data['delivery_bn'];
            $storagelife_data['bill_type'] = 3;

            $has_use_expire = false;
            foreach($data['delivery_items'] as $k => $item){
                $is_use_expire = $storageLifeLib->checkStorageLifeById($item['product_id']);
                if($is_use_expire){
                    //如果是保质期物料的更新发货单明细item为1
                    $dlyItemObj->update(array('use_expire'=>1),array('item_id'=>$item['item_id']));

                    $storagelife_data['items'][] = array(
                        'item_id' => $item['item_id'],
                        'bm_id'=>$item['product_id'],
                        'bn'=>$item['bn'],
                        'product_name'=>$item['product_name'],
                        'num'=>$item['number'],
                    );

                    $has_use_expire = true;
                }
            }
            
            if($has_use_expire){
                //生成发货单保质期预占、流水
                if(!$storageLifeReceiptLib->freeze($storagelife_data ,$msg)){
                    return false;
                }else{
                    //生成发货单明细对应的批次明细
                    if(!$dlyItemsSLReceiptLib->generate($storagelife_data ,$msg)){
                        return false;
                    }
                }
            }
            $order_id = '';
            foreach ($sdf['order_objects'] as $s_k => $s_v) {
                $order_id = $s_v['order_id'];
                break;
            }
            //  jitx 检测订单是否有标签
            if (in_array(strtolower($sdf['shop_type']),['vop','luban'])) {
                kernel::single('ome_bill_label')->transferLabel('omeorders_to_wmsdelivery', [
                    'order_id'          => $order_id,
                    'wms_delivery_id'   => $data['delivery_id'],
                ]);
            }
            //标签写入发货单
            kernel::single('ome_bill_label')->orderToDeliveryLabel($order_id, $data['delivery_id'], 'wms_delivery');

            foreach (kernel::servicelist('wms.service.delivery.create.after') as $service) {
                if (!method_exists($service, 'afterCreate')) {
                    continue;
                }
                if ($service->afterCreate($data['delivery_id'], $sdf, $msg) === false) {
                    if (!$msg) {
                        $msg = '发货单创建后扩展处理失败';
                    }
                    return false;
                }
            }
            
            return true;
        }else{
            return false;
        }
    }

    /**
     * 发货通知单参数校验
     * @param array $params 发货通知参数信息
     * @param string $msg 发货通知单错误消失
     */
    public function checkCreateParams($params,&$msg){
        return true;
    }


    /**
     * 生成发货通知单的唯一标识
     */
    private function gen_id(){
        $prefix = 'W' . date("ymd");
        $sign = kernel::single('eccommon_guid')->incId('wms_delivery', $prefix, 7, true);

        return $sign;
        /*
        $cManage = app::get('ome')->model("concurrent");
        $prefix = date("ymd").'11';
        $sqlString = "SELECT MAX(delivery_bn) AS maxno FROM sdb_wms_delivery WHERE delivery_bn LIKE '".$prefix."%'";
        $aRet = app::get('wms')->model("delivery")->db->selectrow($sqlString);
        if(is_null($aRet['maxno'])){
            $aRet['maxno'] = 0;
            $maxno = 0;
        }else
            $maxno = substr($aRet['maxno'], -5);

        do{
            $maxno += 1;
            if ($maxno==100000){
                break;
            }
            $maxno = str_pad($maxno,5,'0',STR_PAD_LEFT);

            $sign = $prefix.$maxno;

            if($cManage->is_pass($sign,'wms_delivery')){
                break;
            }
        }while(true);

        return $sign;
        */
    }

    /**
     * 检查外部发货通知单号是否存在
     * @param string $outer_delivery_bn 外部发货通知单号
     */
    public function checkOuterExist($outer_delivery_bn){
        $deliveryObj = app::get('wms')->model("delivery");
        $aRet = $deliveryObj->dump(array('outer_delivery_bn'=>trim($outer_delivery_bn)),'delivery_bn');
        if(isset($aRet['delivery_bn']) && !empty($aRet['delivery_bn'])){
            return true;
        }else{
            return false;
        }
    }

    /**
     *
     * 根据外部通知单号获取发货单信息
     * @param string $outer_delivery_bn
     */
    public function getOneByOuterDlyBn($outer_delivery_bn){
        $deliveryObj = app::get('wms')->model("delivery");
        $deliveryInfo = $deliveryObj->dump(array('outer_delivery_bn'=>trim($outer_delivery_bn)),'delivery_bn');
        return $deliveryInfo ? $deliveryInfo : null;
    }

    /**
     *
     * 根据当前状态判断前端更新状态是否可操作
     * @param string $outer_delivery_bn
     * @param int $remote_status
     * @param string $msg
     */
    public function checkDlyStatusByOuterDlyBn($outer_delivery_bn, $remote_status, &$msg){
        $deliveryObj = app::get('wms')->model("delivery");
        $deliveryInfo = $deliveryObj->dump(array('outer_delivery_bn'=>trim($outer_delivery_bn)),'status,disabled');

        if($deliveryInfo['disabled'] == 'true'){
            $msg = '发货单已删除';
            return false;
        }

        switch ($remote_status){
            case 1:
                //取消动作时
                if($deliveryInfo['status'] == 1){
                    $msg = '发货单已取消';
                    return false;
                }elseif ($deliveryInfo['status'] == 3){
                    $msg = '发货单已完成发货';
                    return false;
                }
                break;
            case 2:
                //暂停动作时
                if($deliveryInfo['status'] == 1){
                    $msg = '发货单已取消';
                    return false;
                }elseif($deliveryInfo['status'] == 2){
                    $msg = '发货单已暂停';
                    return false;
                }elseif ($deliveryInfo['status'] == 3){
                    $msg = '发货单已完成发货';
                    return false;
                }
                break;
            case 3:
                //发货动作时
                if($deliveryInfo['status'] == 1){
                    $msg = '发货单已取消';
                    return false;
                }elseif($deliveryInfo['status'] == 2){
                    $msg = '发货单已暂停';
                    return false;
                }elseif ($deliveryInfo['status'] == 3){
                    $msg = '发货单已完成发货';
                    return false;
                }
                break;
            case 4:
                //恢复动作时
                if($deliveryInfo['status'] == 1){
                    $msg = '发货单已取消';
                    return false;
                }elseif($deliveryInfo['status'] == 0){
                    $msg = '发货单未暂停';
                    return false;
                }elseif ($deliveryInfo['status'] == 3){
                    $msg = '发货单已完成发货';
                    return false;
                }
                break;
        }
        return true;
    }

    /**
     *
     * 取消发货单
     * @param string $outer_delivery_bn
     */
    function cancelDlyByOuterDlyBn($outer_delivery_bn){

        $deliveryObj = app::get('wms')->model("delivery");
        $deliveryBillObj = app::get('wms')->model("delivery_bill");
        $dlyCorpObj = app::get('ome')->model('dly_corp');
        $deliveryInfo = $deliveryObj->dump(array('outer_delivery_bn'=>$outer_delivery_bn),'delivery_id,delivery_bn,logi_id,branch_id');
        if(empty($deliveryInfo)) {
            return true;
        }
        $filter = array('outer_delivery_bn' => $outer_delivery_bn);
        $data = array('status' => 1);
        $deliveryObj->update($data, $filter);

        $filter = array('delivery_id' => $deliveryInfo['delivery_id']);
        $data = array('status' => 2,'logi_no'=>'');
        $deliveryBill = $deliveryBillObj->getList('logi_no',$filter);
        //wms发货单取消日志
        $dlyCorp = $dlyCorpObj->dump($deliveryInfo['logi_id'], 'tmpl_type,channel_id');
        if (count($deliveryBill)>0 && $dlyCorp['tmpl_type'] == 'electron'){
            foreach ($deliveryBill as $bill){
                $waybillObj = kernel::single('logisticsmanager_service_waybill');
                $waybillObj->recycle_waybill($bill['logi_no'],$dlyCorp['channel_id'],$deliveryInfo['delivery_id'],$deliveryInfo['delivery_bn']);
            }
            
        }
        $deliveryBillObj->update($data, $filter);

        //OME发起发货单撤销，保质期条码预占释放
        $storageLifeReceiptLib = kernel::single('material_receipt_storagelife');
        $storagelife_data['branch_id'] = $deliveryInfo['branch_id'];
        $storagelife_data['bill_id'] = $deliveryInfo['delivery_id'];
        $storagelife_data['bill_type'] = 3;
        $rs = $storageLifeReceiptLib->unfreeze($storagelife_data ,$msg);
        if(!$rs){
            return false;
        }

        //发货单预占唯一码释放
        $dlyItemsSerialLib    = kernel::single('wms_receipt_dlyitemsserial');
        $serialItem = array('delivery_id' => $deliveryInfo['delivery_id'], 'delivery_bn' => $deliveryInfo['delivery_bn']);
        $rs = $dlyItemsSerialLib->cancel($serialItem);
        if(!$rs){
            return false;
        }

        return true;
    }

    /**
     *
     * 暂停发货单
     * @param string $outer_delivery_bn
     */
    function pauseDlyByOuterDlyBn($outer_delivery_bn){

        $deliveryObj = app::get('wms')->model("delivery");

        $filter = array('outer_delivery_bn' => $outer_delivery_bn);
        $data = array('status' => 2);

        $deliveryObj->update($data, $filter);
        return true;
    }

    /**
     *
     * 恢复发货单
     * @param string $outer_delivery_bn
     */
    function renewDlyByOuterDlyBn($outer_delivery_bn){

        $deliveryObj = app::get('wms')->model("delivery");

        $filter = array('outer_delivery_bn' => $outer_delivery_bn);
        $data = array('status' => 0);

        $deliveryObj->update($data, $filter);
        return true;
    }

}
