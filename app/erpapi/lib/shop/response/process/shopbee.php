<?php
/**
 * Shopbee 供销订单异步消息（标签变更等）
 */
class erpapi_shop_response_process_shopbee
{
    public function tagchange($params)
    {
        if (empty($params['order_id']) || empty($params['tag_key'])) {
            return array('rsp' => 'fail', 'msg' => '参数不完整');
        }


        switch ($params['tag_key']) {
            case 'sku_logistics_extra_info':
                return $this->_handleShowSFFreeShippingTag($params['order_id'], $params['tag_info']);
            case 'shop_priority_delivery':
                return $this->_handleShopPriorityDelivery($params['order_id'], $params['tag_info']);
        }



        return array('rsp' => 'succ', 'msg' => '顺丰包邮标与白名单已更新');
    }

    /**
     * 处理顺丰包邮标签与白名单
     * @param int $orderId
     * @return array
     */
    private function _handleShowSFFreeShippingTag($orderId, $tagInfo)
    {
        $ModifyInfo = $tagInfo['ModifyInfo'] ?? '';
        if (is_string($ModifyInfo)) {
            $ModifyInfo = json_decode($ModifyInfo, true);
        }

        $HighQualityExpressInfo = $tagInfo['HighQualityExpressInfo'] ?? '';
        if (is_string($HighQualityExpressInfo)) {
            $HighQualityExpressInfo = json_decode($HighQualityExpressInfo, true);
        }

        // 打顺丰包邮标签
        if ($tagInfo['ShowHighQualityExpressTag'] == 1){
            $err = null;
            kernel::single('ome_bill_label')->markBillLabel($orderId, '', 'sf_free_shipping', 'order', $err, 0, $tagInfo);

            // 更新order_extend表white_delivery_cps
            $orderExtendMdl = app::get('ome')->model('order_extend');

            $upData = [
                'assign_express_code' => 'SF',
            ];

            if (is_array($ModifyInfo) && $ModifyInfo['support_all_delivery'] == 1){
                $upData['assign_express_code'] = '';
            }

            $orderExtendMdl->update(
                $upData,
                array('order_id' => $orderId)
            );
        }

        // 打优质快递标签
        if ($HighQualityExpressInfo && $HighQualityExpressInfo['enable'] == 1){
            $err = null;
            kernel::single('ome_bill_label')->markBillLabel($orderId, '', 'SOMS_HIGH_EXPRESS', 'order', $err, 0, $tagInfo);

            if ($HighQualityExpressInfo['express_company_code']){
                $biz_delivery_code = is_string($HighQualityExpressInfo['express_company_code']) ? [$HighQualityExpressInfo['express_company_code']] : $HighQualityExpressInfo['express_company_code'];
                $orderExtendMdl->update(
                    [
                        'biz_delivery_code' => json_encode($biz_delivery_code),
                    ],
                    ['order_id' => $orderId]
                );
            }
        }
    }

    /**
     * 处理优先发货标签
     * @param int $orderId
     * @return array
     */
    private function _handleShopPriorityDelivery($orderId, $tagInfo)
    {
        if ($tagInfo['isShow'] != 1) {
            return array('rsp' => 'succ', 'msg' => '无需处理');
        }

        // 打顺丰包邮标签
        $err = null;
        kernel::single('ome_bill_label')->markBillLabel($orderId, '', 'priority_delivery', 'order', $err, 0, $tagInfo);


        if ($tagInfo['suggestDeliveryUnix']) {
            // 更新order_extend表latest_delivery_time
            $orderExtendMdl = app::get('ome')->model('order_extend');

            $upData = [
                'latest_delivery_time' => $tagInfo['suggestDeliveryUnix'],
            ];

            $orderExtendMdl->update($upData, ['order_id' => $orderId]);
        }

        return array('rsp' => 'succ', 'msg' => '优先发货标已打');
    }
}
