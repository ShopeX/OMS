<?php
/**
 * 发货单业务Lib方法类
 *
 * @author wangbiao@shopex.cn
 * @version 2025.09.25
 */
class ome_delivery
{
    public function autoVirtualDelivery(&$cursor_id, $params, &$error_msg=null)
    {
        //data
        $sdfdata = $params['sdfdata'];
        $delivery_id = $sdfdata['delivery_id'];
        $delivery_bn = $sdfdata['delivery_bn'];
        $logi_id = $sdfdata['logi_id'];
        if(empty($delivery_id)){
            $error_msg = '没有发货单信息';
            return false;
        }
        
        // params
        $virtualParams = array(
            'delivery_bn' => $delivery_bn, // 发货单号
            'status' => 'delivery',
            'logi_id' => $logi_id, // 物流公司ID：直接使用发货单上的物流公司ID
            'logi_no' => $delivery_bn, // 物流单号：直接使用发货单号作为虚拟物流单号
        );
        $result = kernel::single('ome_event_receive_delivery')->update($virtualParams);
        if($result['rsp'] != 'succ'){
            $error_msg = '虚拟发货单更新失败：'.$result['msg'];
            return false;
        }
        
        return false;
    }
}