<?php
/**
 * 唯品会JITX订单物流相关接口
 */
class erpapi_shop_matrix_vop_request_logistics extends erpapi_shop_request_logistics
{
    /**
     * 获取请求平台获取运单号接口
     *
     * @return null
     */
    protected function get_waybill_number_apiname()
    {
        return STORE_WAYBILL_RESHIPPING_GET;
    }
    
    /**
     * 获取运单号参数
     * 平台文档：https://vop.vip.com/home#/api/method/detail/com.vip.vis.order.jit.service.JitXOrderInfo.JitXOrderInfoService-1.0.0/getRedeliverTransportNoVop
     *
     * @param $sdf
     * @return void
     */
    protected function get_waybill_number_params($sdf)
    {
        // param
        $param = parent::get_waybill_number_params($sdf);
        
        // 订单号
        $param['tid'] = $sdf['order_bn']; // 平台字段名：order_sn
        $param['order_bn'] = $sdf['order_bn']; // 平台字段名：order_sn
        
        // 仓库（发货仓码）
        $param['store_sn'] = $sdf['store_sn']; // 平台字段名：delivery_warehouse，例如：RSD0004584
        
        // 是否预制单，1：是，0：否
        $param['is_pre_transport'] = '0'; // 平台字段名：is_pre_transport
        
        return $param;
    }
}
