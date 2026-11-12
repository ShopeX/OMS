<?php
/**
 * WMS 发货单
 *
 * @category 
 * @package 
 * @author yaokangming<yaokangming@shopex.cn>
 * @version $Id: Z
 */
class erpapi_wms_openapi_sku360_response_delivery  extends erpapi_wms_response_delivery
{
    public function status_update($params){
        $wmsFunc = kernel::single('wmsmgr_func');
        
        // shop_type
        $shop_type = $wmsFunc->getShopTypeByDeliveryBn(isset($params['delivery_bn']) ? $params['delivery_bn'] : '');
        
        // 根据wms_id、wms物流公司编号获取系统物流公司编号
        $sys_logistics = $wmsFunc->getlogiCode($this->__channelObj->wms['channel_id'], $params['logistics'], $shop_type);
        $params['logistics'] = $sys_logistics ? $sys_logistics : $params['logistics'];

        return parent::status_update($params);
    }
}
