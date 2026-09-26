<?php
/**
 * 顺丰物流跟踪
 * 查询：STORE_LOGISTICS_TRACE_GET（store.logistics.trace.get）
 * 订阅：SHOP_LOGISTICS_SUBSCRIBE（store.trade.pub.appreciation）
 * 绑定走第三方快递 iframe（自有key）
 *
 * 调用：
 *   erpapi_router_request->set('hqepay', 'sf')->sf_query($sdf)
 *   erpapi_router_request->set('hqepay', 'sf')->sf_pub($sdf, true)
 */
class erpapi_hqepay_request_sf extends erpapi_hqepay_request_abstract
{
    public $node_type = 'sf';

    /**
     * 物流订阅（Matrix：store.trade.pub.appreciation）
     * 业务参数：
     *   logistic_code = 物流单号 logi_no（必填）
     *   tid           = 订单号 order_bn（可选）
     *
     * @param array $sdf
     * @param bool  $queue 是否入队
     * @return array
     */
    public function pub($sdf, $queue = false)
    {
        $args = func_get_args();
        array_pop($args);
        $_in_mq = $this->__caller->caller_into_mq(
            'sf_pub',
            'hqepay',
            $this->__channelObj->channel['hqepay_id'] ?? 'sf',
            $args,
            $queue
        );
        if ($_in_mq) {
            return $this->succ('成功放入队列');
        }

        $logisticCode = trim((string) ($sdf['logistic_code'] ?? $sdf['logi_no'] ?? $sdf['attributeNo'] ?? ''));
        if ($logisticCode === '') {
            return $this->error('运单号不能为空', 'SF_EMPTY_LOGI_NO');
        }

        $params = array(
            'logistic_code' => $logisticCode,
        );
        $tid = trim((string) ($sdf['tid'] ?? $sdf['order_bn'] ?? ''));
        if ($tid !== '') {
            $params['tid'] = $tid;
        }

        $title = sprintf('顺丰物流订阅[%s]', $logisticCode);
        $callback = array(
            'class'  => get_class($this),
            'method' => 'callback',
            'params' => array(
                'obj_bn' => $sdf['delivery_bn'] ?? $logisticCode,
            ),
        );

        return $this->__caller->call(
            SHOP_LOGISTICS_SUBSCRIBE,
            $params,
            $callback,
            $title,
            10,
            $sdf['delivery_bn'] ?? $logisticCode
        );
    }

    /**
     * 路由查询（Matrix：STORE_LOGISTICS_TRACE_GET）
     * to_node_id 取渠道表已绑定成功的 node_id（见 erpapi_channel_hqepay / erpapi_hqepay_config）
     * 业务参数仅 tracking_number；返回统一为快递鸟风格 Traces，供 ome_hqepay 展示
     *
     * @param array $sdf logi_no 运单号
     * @return array rsp/msg/data
     */
    public function query($sdf)
    {
        $trackingNumber = trim((string) ($sdf['logi_no'] ?? ''));
        if ($trackingNumber === '') {
            return $this->error('运单号不能为空', 'SF_EMPTY_LOGI_NO');
        }

        $params = array(
            'tracking_number' => $trackingNumber,
          
        );

        $title  = sprintf('查询物流信息[%s]', $trackingNumber);
        $result = $this->__caller->call(STORE_LOGISTICS_TRACE_GET, $params, array(), $title, 5, $trackingNumber);

        if (empty($result['rsp']) || $result['rsp'] != 'succ') {
            return $result;
        }

        $data = $result['data'];
        if (is_string($data)) {
            $data = json_decode($data, true);
        }
        if (!is_array($data)) {
            return $this->error('物流返回数据解析失败', 'SF_BAD_DATA');
        }

        // Matrix 可能再包一层 data
        if (isset($data['msgData']) === false && isset($data['data']) && is_array($data['data'])) {
            $data = $data['data'];
        }

        if (isset($data['success']) && !$data['success']) {
            $err = $data['errorMsg'] ?: ($data['errorCode'] ?: '查询失败');
            return $this->error('顺丰查询失败: ' . $err, (string) ($data['errorCode'] ?: 'SF_FAIL'));
        }

        $traces = $this->routesToTraces($data);
        if (!$traces) {
            return $this->error('暂无物流轨迹', 'SF_EMPTY_TRACE');
        }

        // 与快递鸟一致：data 含 Traces，ome_hqepay::get_dly_info / detail_delivery 可直接展示
        return $this->succ('ok', '', array('Traces' => $traces));
    }

    /**
     * 顺丰 routeResps.routes -> 快递鸟风格 Traces
     *
     * @param array $apiResultData
     * @return array
     */
    protected function routesToTraces(array $apiResultData)
    {
        $traces = array();
        $routeResps = $apiResultData['msgData']['routeResps']
            ?? $apiResultData['routeResps']
            ?? array();

        foreach ((array) $routeResps as $resp) {
            foreach ((array) ($resp['routes'] ?? array()) as $route) {
                $time   = $route['acceptTime'] ?? '';
                $addr   = $route['acceptAddress'] ?? '';
                $remark = $route['remark'] ?? '';
                $station = trim($addr . ($addr && $remark ? ' ' : '') . $remark);
                $traces[] = array(
                    'AcceptTime'    => $time,
                    'AcceptStation' => $station ?: $remark,
                    'Remark'        => $remark,
                    'OpCode'        => $route['opCode'] ?? '',
                );
            }
        }

        return $traces;
    }
}
