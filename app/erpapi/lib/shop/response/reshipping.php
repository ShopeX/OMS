<?php
/**
 * 补寄申请接口处理
 *
 * @category
 * @package
 * @author
 * @version $Id: Z
 */
class erpapi_shop_response_reshipping extends erpapi_shop_response_abstract
{

    /**
     * 数据转换：将矩阵推送的原始参数转换为标准格式
     *
     * @param array $params 矩阵推送的原始参数
     * @return array|false 转换后的标准格式数据
     */
    public function add($params)
    {
        $this->__apilog['original_bn'] = $params['dispute_id'];
        $this->__apilog['title']       = '接收补寄申请[' . $params['alipay_no'] . ']';

        // 数据转换：将原始参数转换为标准格式SDF
        $sdf = $this->_formatAddParams($params);

        // 添加店铺信息
        $sdf['shop_id'] = $this->__channelObj->channel['shop_id'];
        $sdf['shop_type'] = $this->__channelObj->channel['shop_type'];

        self::trim($sdf);
        return $sdf;
    }

    /**
     * 格式化参数：将矩阵推送的原始参数转换为标准格式
     *
     * @param array $params 矩阵推送的原始参数
     * @return array 标准格式数据
     */
    protected function _formatAddParams($params)
    {
        $sdf = array(
            'dispute_id' => $params['dispute_id'],
            'alipay_no' => isset($params['alipay_no']) ? $params['alipay_no'] : '',
            'biz_order_id' => isset($params['biz_order_id']) ? $params['biz_order_id'] : '',
            'status' => $params['status'],
            'reason' => isset($params['reason']) ? $params['reason'] : '',
            'desc' => isset($params['desc']) ? $params['desc'] : '',
            'refund_phase' => isset($params['refund_phase']) ? $params['refund_phase'] : '',
            'created' => isset($params['created']) ? $params['created'] : '',
            'modified' => isset($params['modified']) ? $params['modified'] : '',
            'time_out' => isset($params['time_out']) ? $params['time_out'] : '',
            'buyer_nick' => isset($params['buyer_nick']) ? $params['buyer_nick'] : '',
            'buyer_open_uid' => isset($params['buyer_open_uid']) ? $params['buyer_open_uid'] : '',
            'buyer_name' => isset($params['buyer_name']) ? $params['buyer_name'] : '',
            'buyer_phone' => isset($params['buyer_phone']) ? $params['buyer_phone'] : '',
            'buyer_address' => isset($params['buyer_address']) ? $params['buyer_address'] : '',
            'buyer_province' => isset($params['buyer_province']) ? $params['buyer_province'] : '',
            'buyer_city' => isset($params['buyer_city']) ? $params['buyer_city'] : '',
            'buyer_district' => isset($params['buyer_district']) ? $params['buyer_district'] : '',
            'buyer_town' => isset($params['buyer_town']) ? $params['buyer_town'] : '',
            'seller_nick' => isset($params['seller_nick']) ? $params['seller_nick'] : '',
            'title' => isset($params['title']) ? $params['title'] : '',
            'bought_sku' => isset($params['bought_sku']) ? $params['bought_sku'] : '',
            'new_bought_sku' => isset($params['new_bought_sku']) ? $params['new_bought_sku'] : '',
            'num' => isset($params['num']) ? $params['num'] : 1,
            'price' => isset($params['price']) ? $params['price'] : 0,
            'good_status' => isset($params['good_status']) ? $params['good_status'] : '',
            'cs_status' => isset($params['cs_status']) ? $params['cs_status'] : '',
            'advance_status' => isset($params['advance_status']) ? $params['advance_status'] : '',
            'operation_contraint' => isset($params['operation_contraint']) ? $params['operation_contraint'] : '',
            'logi_name' => isset($params['logi_name']) ? $params['logi_name'] : '',
            'logi_no' => isset($params['logi_no']) ? $params['logi_no'] : '',
            'oaid' => isset($params['oaid']) ? $params['oaid'] : '',
            'real_receiver_open_id' => isset($params['real_receiver_open_id']) ? $params['real_receiver_open_id'] : '',
            'real_receiver_display_nick' => isset($params['real_receiver_display_nick']) ? $params['real_receiver_display_nick'] : '',
            'refuse_reason' => isset($params['refuse_reason']) ? $params['refuse_reason'] : '',
            'refuse_reason_id' => isset($params['refuse_reason_id']) ? $params['refuse_reason_id'] : '',
            'extend_field' => isset($params['extend_field']) ? $params['extend_field'] : '',
        );

        return $sdf;
    }

}

