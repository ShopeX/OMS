<?php
/**
 * 自提确认提货 - 前端/平台核销
 *
 * 同事在 callFrontPickupVerify() 中接入 HTTP；
 * 返回 rsp=succ 后再执行本地提货（do_verify）。
 */
class wap_selfpick_pickup_verify
{
    /**
     * 确认提货前调用前端核销接口
     *
     * @param array  $deliveryInfo 发货单信息
     * @param string $pickup_code  本地提货码（启用时）
     * @param array  $extra        扩展参数，如 serial_items
     * @param string $err_msg      失败原因（对应接口 msg）
     * @return bool
     */
    public function verify(array $deliveryInfo, $pickup_code = '', array $extra = array(), &$err_msg = '')
    {
        return $this->callFrontPickupVerify($deliveryInfo, $pickup_code, $extra, $err_msg);
    }

    /**
     * 调用前端/平台核销 HTTP
     *
     * 成功时接口返回 rsp=succ；失败 rsp=fail，msg 为原因
     * TODO: 同事在此实现 HTTP 请求
     *
     * @param array  $deliveryInfo
     * @param string $pickup_code
     * @param array  $extra
     * @param string $err_msg
     * @return bool
     */
    protected function callFrontPickupVerify(array $deliveryInfo, $pickup_code, array $extra, &$err_msg = '')
    {
        // TODO: 同事接入前端核销 HTTP，示例：
        // $response = kernel::single('xxx')->request(...);
        // return $this->parseFrontVerifyResponse($response, $err_msg);

        // 占位：未接入 HTTP 前视为成功，便于联调本地提货流程
        return true;
    }

    /**
     * 解析前端核销响应
     *
     * @param mixed  $response
     * @param string $err_msg
     * @return bool
     */
    protected function parseFrontVerifyResponse($response, &$err_msg = '')
    {
        if (is_string($response)) {
            $response = json_decode($response, true);
        }
        if (!is_array($response)) {
            $err_msg = '平台响应格式错误';
            return false;
        }

        if (($response['rsp'] ?? '') === 'succ') {
            return true;
        }

        $err_msg = trim($response['msg'] ?? '');
        if ($err_msg === '') {
            $err_msg = '前端核销失败';
        }
        return false;
    }
}
