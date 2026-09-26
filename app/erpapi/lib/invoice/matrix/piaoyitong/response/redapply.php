<?php
/**
 * [票易通]红字申请单接口响应类Lib类
 *
 * @author wangbiao@shopex.cn
 * @version 2026.04.02
 */
class erpapi_invoice_matrix_piaoyitong_response_redapply extends erpapi_invoice_response_redapply
{
    protected function _checkUpdateParams($params)
    {
        $params = [];
        
        // 直接返回异常
        if (empty($params)) {
            throw new exception('票易通回传数据结构不正确。');
        }
        
        // 直接返回false
        return false;
    }
    
    protected function _formatUpdateParams($params)
    {
        $data = [];
        
        // 可回传列表为空则直接返回
        if (empty($data)) {
            throw new exception('票易通发票回传结果为空。');
        }
        
        // 直接返回空
        return $data;
    }
}
