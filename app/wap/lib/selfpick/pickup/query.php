<?php
/**
 * 自提列表 - 前端订单查询（扫码/输入 → 解析为 OMS 订单号）
 *
 * 同事在 callFrontOrderQuery() 中接入平台 HTTP；
 * 返回 data['order_bn'] 供跳转确认核销页。
 */
class wap_selfpick_pickup_query
{
    /**
     * @param string $content 扫码或手动输入内容
     * @param string $err_msg
     * @param array  $result  成功时含 order_bn
     * @return bool
     */
    public function query($content, &$err_msg = '', array &$result = array())
    {
        $result = array('order_bn' => '');

        $content = trim($content);
        if ($content === '') {
            $err_msg = '请扫描或输入订单号/提货码';
            return false;
        }

        $platformData = array();
        if (!$this->callFrontOrderQuery($content, $err_msg, $platformData)) {
            return false;
        }

        $orderBn = trim($platformData['order_bn'] ?? '');
        if ($orderBn === '') {
            $err_msg = '未返回订单号';
            return false;
        }

        $result['order_bn'] = $orderBn;
        return true;
    }

    /**
     * 调用前端/平台订单查询接口
     *
     * 成功时 $data 需包含 order_bn
     * TODO: 同事在此实现 HTTP 请求
     *
     * @param string $content
     * @param string $err_msg
     * @param array  $data
     * @return bool
     */
    protected function callFrontOrderQuery($content, &$err_msg, array &$data = array())
    {
        $data = array();

        // TODO: 同事接入前端订单查询 HTTP，示例：
        // $response = kernel::single('xxx')->request(...);
        // $parsed = $this->parseFrontOrderResponse($response, $err_msg);
        // if ($parsed) { $data['order_bn'] = $parsed['order_bn']; return true; }
        // return false;

        // 占位：未接入 HTTP 前，直接将扫码内容作为订单号返回，便于联调
        $data['order_bn'] = $content;
        return true;
    }

    /**
     * 解析平台响应（同事接入后可复用）
     *
     * @param mixed  $response
     * @param string $err_msg
     * @return array|false 含 order_bn
     */
    protected function parseFrontOrderResponse($response, &$err_msg = '')
    {
        if (is_string($response)) {
            $response = json_decode($response, true);
        }
        if (!is_array($response)) {
            $err_msg = '平台响应格式错误';
            return false;
        }

        if (!empty($response['success']) || (isset($response['code']) && (int) $response['code'] === 0)) {
            $orderBn = trim($response['data']['order_bn'] ?? $response['order_bn'] ?? '');
            if ($orderBn === '') {
                $err_msg = '未返回订单号';
                return false;
            }
            return array('order_bn' => $orderBn);
        }

        $err_msg = $response['msg'] ?? $response['message'] ?? '订单查询失败';
        return false;
    }
}
