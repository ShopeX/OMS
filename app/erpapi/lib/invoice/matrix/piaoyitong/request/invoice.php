<?php
/**
 * [票易通]处理电子发票业务Lib类
 *
 * @author wangbiao@shopex.cn
 * @version 2026.04.02
 */
class erpapi_invoice_matrix_piaoyitong_request_invoice extends erpapi_invoice_request_invoice
{
    /**
     * 获取开发票请求接口名
     *
     * @return string
     */
    protected function get_create_apiname()
    {
        return '';
    }
    
    /**
     * 开蓝票
     *
     * @param array $sdf
     * @return array
     */
    public function create($sdf)
    {
        // title
        $title = '[票易通]电子发票开蓝票-' . $sdf['invoice_apply_bn'];
        
        // result
        $result = $this->error('请求失败：未调用接口!', '');
        
//        // 重置erpapi_caller()类，请求平台开发票
//        $callerObj = new erpapi_caller();
//        $this->__caller = $callerObj->set_config($config)->set_channel($channel)->set_result($result);
        
        // 注册service，请求渠道商开蓝票
        foreach(kernel::servicelist('erpapi.service.invoice.request.caller') as $object)
        {
            if(method_exists($object, 'get_request_caller_object')){
                // analysis
                $serviceResult = $object->get_request_caller_object($sdf);
                if($serviceResult['rsp'] == 'succ' && $serviceResult['data']['objName']){
                    $requestObj = kernel::single($serviceResult['data']['objName']);
                    
                    // check method
                    if (method_exists($requestObj, 'make_blue_invoice')) {
                        $result = $requestObj->make_blue_invoice($sdf);
                    }
                }
            }
        }
        
        return $result;
    }
    
    /**
     * [冲红]发票取消接口
     *
     * @param array $sdf
     * @param array
     */
    public function cancel($sdf)
    {
        // title
        $title = '[票易通]电子发票冲红-' . $sdf['invoice_apply_bn'];
        
        // result
        $result = $this->error('请求失败：未调用接口!', '');
        
        // 注册service，请求渠道商开蓝票
        foreach(kernel::servicelist('erpapi.service.invoice.request.caller') as $object)
        {
            if(method_exists($object, 'get_request_caller_object')){
                // analysis
                $serviceResult = $object->get_request_caller_object($sdf);
                if($serviceResult['rsp'] == 'succ' && $serviceResult['data']['objName']){
                    $requestObj = kernel::single($serviceResult['data']['objName']);
                    
                    // check method
                    if (method_exists($requestObj, 'make_red_invoice')) {
                        $result = $requestObj->make_red_invoice($sdf);
                    }
                }
            }
        }
        
        return $result;
    }
}
