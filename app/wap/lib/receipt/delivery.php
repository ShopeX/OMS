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

class wap_receipt_delivery{

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
        //校验传入参数
        if(!$this->checkParams($sdf,$error_msg)){
            $msg = '发货通知单参数检验失败,具体原因:'.$error_msg;
            return false;
        }

        //检查发货通知单是否已经存在
        if($this->checkOuterExist($sdf['outer_delivery_bn'])){
            $msg = '发货通知单外部编号'.$sdf['outer_delivery_bn']."已存在";
            return false;
        }

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
        //$data['delivery'] = $sdf['delivery'];

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
        
        //新增order_bn(为必有字段) total_amount字段 
        $data['order_bn']= $sdf['order_bn'];
        $data['total_amount']= $sdf['total_amount'];
        
        $deliveryObj = app::get('wap')->model('delivery');
        $deliveryObj->db->beginTransaction();

        if($deliveryObj->save($data)){
            $sdf['delivery_id'] = $data['delivery_id'];

            $bill_info = array(
                'delivery_id' => $sdf['delivery_id'],
                'net_weight' => $data['net_weight'],
            );

            $deliveryBillObj = app::get('wap')->model('delivery_bill');
            $deliveryBillObj->save($bill_info);

            $deliveryObj->db->commit();
            return true;
        }else{
            $deliveryObj->db->rollBack();
            return false;
        }
    }

    /**
     * 发货通知单参数校验
     * @param array $params 发货通知参数信息
     * @param string $msg 发货通知单错误消失
     */
    private function checkParams($params,&$msg){
        return true;
    }


    /**
     * 生成发货通知单的唯一标识
     */
    private function gen_id(){
        $prefix = 'O'.date("ymd");
        $sign = kernel::single('eccommon_guid')->incId('wap_delivery', $prefix, 7, true);

        return $sign;
    }

    /**
     * 检查外部发货通知单号是否存在
     * @param string $outer_delivery_bn 外部发货通知单号
     */
    public function checkOuterExist($outer_delivery_bn){
        $deliveryObj = app::get('wap')->model("delivery");
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
        $deliveryObj = app::get('wap')->model("delivery");
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
    function checkDlyStatusByOuterDlyBn($outer_delivery_bn, $remote_status, &$msg){
        $deliveryObj = app::get('wap')->model("delivery");
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

        $deliveryObj = app::get('wap')->model("delivery");
        $deliveryBillObj = app::get('wap')->model("delivery_bill");
        $dlyCorpObj = app::get('ome')->model('dly_corp');
        $deliveryInfo = $deliveryObj->dump(array('outer_delivery_bn'=>$outer_delivery_bn),'delivery_id,logi_id');

        $filter = array('outer_delivery_bn' => $outer_delivery_bn);
        $data = array('status' => 1);
        $deliveryObj->update($data, $filter);

        $filter = array('delivery_id' => $deliveryInfo['delivery_id']);
        $data = array('status' => 2,'logi_no'=>'');
        $deliveryBillObj->update($data, $filter);

        if (!empty($deliveryInfo['delivery_id'])) {
            kernel::single('wap_delivery_serial')->releasePrepareSerialByDelivery(
                intval($deliveryInfo['delivery_id'])
            );
        }
    }

    /**
     *
     * 暂停发货单
     * @param string $outer_delivery_bn
     */
    function pauseDlyByOuterDlyBn($outer_delivery_bn){

        $deliveryObj = app::get('wap')->model("delivery");

        $filter = array('outer_delivery_bn' => $outer_delivery_bn);
        $data = array('status' => 2);

        $deliveryObj->update($data, $filter);
    }

    /**
     *
     * 恢复发货单
     * @param string $outer_delivery_bn
     */
    function renewDlyByOuterDlyBn($outer_delivery_bn){

        $deliveryObj = app::get('wap')->model("delivery");

        $filter = array('outer_delivery_bn' => $outer_delivery_bn);
        $data = array('status' => 0);

        $deliveryObj->update($data, $filter);
    }

    /**
     * 仅更新 WAP 本地签收状态，不回写 OME
     *
     * @param string $outer_delivery_bn
     * @param string $err_msg
     * @return bool
     */
    public function signDlyLocalByOuterDlyBn($outer_delivery_bn, &$err_msg = '')
    {
        $outer_delivery_bn = trim($outer_delivery_bn);
        if ($outer_delivery_bn === '') {
            $err_msg = '发货单号不能为空';
            return false;
        }

        $deliveryObj  = app::get('wap')->model('delivery');
        $deliveryInfo = $deliveryObj->dump(
            array('outer_delivery_bn' => $outer_delivery_bn),
            'delivery_id,delivery_bn,is_received'
        );
        if (empty($deliveryInfo['delivery_id'])) {
            $err_msg = '发货单不存在';
            return false;
        }
        if (intval($deliveryInfo['is_received']) === 2) {
            return true;
        }

        $result = $deliveryObj->save(array(
            'delivery_id'   => $deliveryInfo['delivery_id'],
            'is_received'   => 2,
            'last_modified' => time(),
        ));
        if (!$result) {
            $err_msg = '更新发货单签收状态失败';
            return false;
        }

        if (!empty($deliveryInfo['delivery_bn'])) {
            app::get('wap')->model('delivery_code')->update(
                array('status' => 1),
                array('delivery_bn' => $deliveryInfo['delivery_bn'])
            );
        }

        return true;
    }

}