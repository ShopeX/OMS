<?php

/**
 * 发货单处理
 *
 * @category
 * @package
 * @author  chenping<chenping@shopex.cn>
 * @version $Id: Z
 */
class erpapi_shop_matrix_b2b_request_delivery extends erpapi_shop_request_delivery
{
    protected function get_delivery_apiname($sdf)
    {
        return SHOP_LOGISTICS_OFFLINE_SEND;
    }

    /**
     * 发货请求参数
     *
     * @return void
     * @author
     **/
    protected function get_confirm_params($sdf)
    {
        $itemList = array();
        foreach ($sdf['order_objects'] as $obj) {
            // 拆单要过滤掉赠品
            if ($obj['shop_goods_id'] == '-1') {
                continue;
            }
            $tmp        = array(
                'product_bn'   => $obj['bn'],
                'product_name' => $obj['name'],
                'number'       => $obj['quantity'],
                'oid'          => $obj['oid'],
                'sku_uuid'     => $obj['sku_uuid'], 

            );
            $itemList[] = $tmp;
        }

        $param = array(
            't_confirm' => $sdf['delivery_time'],
            'order_bn' => $sdf['orderinfo']['order_bn'],
            'date' => time(),
            'logi_no' => $sdf['logi_no'],
            'logi_code' => $sdf['logi_type'],
            'logi_name' => $sdf['logi_name'],
        );
        $param['items'] = json_encode($itemList);
      
        return $param;
    }

    /**
     * 数据处理
     *
     * @return void
     * @author
     **/
    protected function format_confirm_sdf(&$sdf)
    {
        parent::format_confirm_sdf($sdf);

    }

    /**
     * 对应第三方B2B接口文档, b2b.delivery.update 更新物流信息 接口
     * 对应D1M文档, order/status/update 推送订单状态 接口
     * @param array $sdf
     * @param false $queue
     * @return array|void
     */
    public function confirm($sdf, $queue = false)
    {

        // 只处理已发货与部分发货状态
        if ($sdf['status'] != 'succ' && !in_array($sdf['orderinfo']['ship_status'], array('1', '2'))) return $this->succ('发货单未发货');

        $this->format_confirm_sdf($sdf);

        // 发货记录
        $opInfo = kernel::single('ome_func')->getDesktopUser();
        $log_id = uniqid($_SERVER['HOSTNAME']);
        $log = array(
            'shopId' => $this->__channelObj->channel['shop_id'],
            'ownerId' => $opInfo['op_id'],
            'orderBn' => $sdf['orderinfo']['order_bn'],
            'deliveryCode' => $sdf['logi_no'],
            'deliveryCropCode' => $sdf['logi_type'],
            'deliveryCropName' => $sdf['logi_name'],
            'receiveTime' => time(),
            'status' => 'send',
            'updateTime' => '0',
            'message' => '',
            'log_id' => $log_id,
        );

        $shipmentLogModel = app::get('ome')->model('shipment_log');
        $shipmentLogModel->insert($log);

        // 更新订单状态
        $orderModel = app::get('ome')->model('orders');
        $orderModel->update(array('sync' => 'run'), array('order_id' => $sdf['orderinfo']['order_id']));

        // 整理参数格式
        $title = sprintf('发货状态回写[%s]-%s', $sdf['delivery_bn'], $this->__channelObj->channel['node_type']);

        $params = $this->get_confirm_params($sdf);

        // 直连请求暂不支持异步回调
        $callback = array();
        $callbackParams = array(
            'shipment_log_id' => $log_id,
            'order_id' => $sdf['orderinfo']['order_id'],
            'logi_no' => $sdf['logi_no'],
            'obj_bn' => $sdf['orderinfo']['order_bn'],
            'company_code' => $sdf['logi_type'],
        );


        $result = $this->__caller->call($this->get_delivery_apiname($sdf), $params, $callback, $title, 10, $sdf['orderinfo']['order_bn']);

        // 直连情况下,执行callback函数
        $this->confirm_callback($result, $callbackParams);
        return $result;
    }

    /**
     * 发货回调
     *
     * @return void
     * @author
     **/
    public function confirm_callback($response, $callback_params)
    {
        $rs = parent::confirm_callback($response, $callback_params);
        return $rs;
    }
} 