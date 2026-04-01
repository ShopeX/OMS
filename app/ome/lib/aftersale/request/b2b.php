<?php
/**
 * [b2b]店铺售后业务请求类
 */
class ome_aftersale_request_b2b extends ome_aftersale_abstract
{
    function show_aftersale_html()
    {
        $html = '';
        
        return $html;
    }
    
    /**
     * 退款状态保存前扩展 , 当前平台用于传输CRC本地退款申请单
     * 
     * @param int $apply_id
     * @param array $data status=2 接收申请 ;  status=3 拒绝
     * @return array
     */
    function pre_save_refund($apply_id, $data)
    {
        set_time_limit(0);
        $oRefund_apply = app::get('ome')->model('refund_apply');
        $refunddata = $oRefund_apply->refund_apply_detail($apply_id);
       
        $rs = array('rsp' => 'succ', 'msg' => '', 'data' => '');
        $status = $data['status'];
        
        // 只有拒绝才发起请求
        $allowStatusList = ['3'];
        if (!in_array($status, $allowStatusList)) {
            return $rs;
        }
        
        // b2b 不同步本地拒绝退款申请
        if($refunddata['source'] != 'matrix'){
            return $rs;
        }

        $result = kernel::single('ome_service_refund_apply')->update_status($refunddata, $data['status'], 'sync');
        return $result;
    }
    
    /**
     * 售后保存前的扩展
     * 
     * @param array $data
     * @return array
     */
    function pre_save_return($data)
    {
        set_time_limit(0);
        $rs = array('rsp'=>'succ','msg'=>'','data'=>'');
        $return_id = $data['return_id'];
        $status = $data['status'];

        // 只有接收申请和拒绝才发起请求
        $allowStatusList = [ '5'];
        
        if(!in_array($status,$allowStatusList)){
            return $rs;
        }

        
        // 接受申请需选择转换类型,且类型为退货单
        if($status == '3' && (!isset($data['choose_type']) || $data['choose_type'] != '1')){
            return $rs;
        }
     
        $rsp = kernel::single('ome_service_aftersale')->update_status($return_id, $status, 'sync');
        if ($rsp && $rsp['rsp'] == 'fail') {
            $rs['rsp'] = 'fail';
            $rs['msg'] = $rsp['msg'];
        }
        
        return $rs;
    }

    /**
     * 获取允许的转换类型
     * @param
     * @return
     * @access  public
     * @author
     */
    function getServerType($serverTypeList, $returnproduct)
    {
        // 线上不允许换货
        if ($returnproduct['source'] == 'matrix') {
            unset($serverTypeList['2']);
        }
        return $serverTypeList;
    }
} 