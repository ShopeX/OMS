<?php
/**
 * @Author: xueding@shopex.cn
 * @Vsersion: 2022/10/25
 * @Describe: 发票 相关请求接口类
 */

class erpapi_shop_matrix_jd_request_invoice extends erpapi_shop_request_invoice
{
    /**
     * 发票上传组织数据
     *
     * @return void
     * @author
     */
    protected function getUploadParams($sdf)
    {
        $params = parent::getUploadParams($sdf);
        
        // unset
        unset($params['invoice_code'], $params['blue_invoice_code']);
        
        $params['invoice_no'] = $params['invoice_no'] ?: 0;
        $params['blue_invoice_no'] = $params['blue_invoice_no'] ?: 0;
        
        $plat = array(
            '360buy' => '10001',
            'taobao' => '10002',
            'suning' => '10003',
            'amazon' => '10004',
            'other'  => '30001',
        );
        
        $order_type = $plat[$this->__channelObj->channel['node_type']];
        $params['order_type'] = $order_type ? $order_type : $plat['other'];
        
        // 版本号
        $params['invoice_dto_version'] = 'v2';
        
        return $params;
    }
}
