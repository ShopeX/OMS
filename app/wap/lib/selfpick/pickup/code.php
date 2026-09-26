<?php
/**
 * 自提提货码平台核销
 *
 * 平台核销只能成功一次；本地需记录 platform_pickup_verified，
 * 断线后重扫提货码时跳过平台接口，仅继续本地发货/签收。
 */
class wap_selfpick_pickup_code
{
    const RESULT_OK      = 'ok';
    const RESULT_ALREADY = 'already';
    const RESULT_FAIL    = 'fail';

    /**
     * 校验/核销平台提货码
     *
     * @param string $code    扫码或手动输入的内容
     * @param string $err_msg 失败原因
     * @param array  $result  成功时返回 delivery_id、resumed 等
     * @return bool
     */
    public function verify($code, &$err_msg = '', array &$result = array())
    {
        $result = array(
            'delivery_id'       => 0,
            'platform_verified' => false,
            'resumed'           => false,
        );

        $delivery = $this->resolveDeliveryByCode($code, $err_msg);
        if (empty($delivery)) {
            return false;
        }

        $result['delivery_id'] = (int) $delivery['delivery_id'];

        if ($this->isPlatformVerified($delivery)) {
            $result['platform_verified'] = true;
            $result['resumed']           = true;
            return true;
        }

        $platformData = array();
        $platformRes  = $this->callPlatformPickupVerify($code, $delivery, $err_msg, $platformData);
        if ($platformRes === self::RESULT_OK) {
            $this->markPlatformVerified($delivery['delivery_id'], $platformData);
            $result['platform_verified'] = true;
            return true;
        }

        if ($platformRes === self::RESULT_ALREADY) {
            $this->markPlatformVerified($delivery['delivery_id'], $platformData);
            $result['platform_verified'] = true;
            $result['resumed']           = true;
            return true;
        }

        return false;
    }

    /**
     * 是否已在平台侧核销（本地已落库）
     */
    public function isPlatformVerified(array $delivery)
    {
        return (int) ($delivery['platform_pickup_verified'] ?? 0) === 1;
    }

    /**
     * 标记平台核销成功
     */
    public function markPlatformVerified($delivery_id, array $platformData = array())
    {
        $delivery_id = intval($delivery_id);
        if ($delivery_id <= 0) {
            return false;
        }

        $update = array(
            'delivery_id'                   => $delivery_id,
            'platform_pickup_verified'      => 1,
            'platform_pickup_verified_time' => time(),
            'last_modified'                 => time(),
        );
        if (!empty($platformData['traceId'])) {
            $update['platform_pickup_trace_id'] = substr((string) $platformData['traceId'], 0, 64);
        }

        return app::get('wap')->model('delivery')->save($update);
    }

    /**
     * 根据提货码/扫码内容解析自提发货单
     */
    public function resolveDeliveryByCode($code, &$err_msg = '')
    {
        $code = trim($code);
        if ($code === '') {
            $err_msg = '请输入提货码';
            return null;
        }

        $wapDeliveryLib = kernel::single('wap_delivery');
        $baseFilter     = $this->getSelfpickVerifyBaseFilter();
        $pickup_code    = '';
        $order_bn       = $code;

        if (strpos($code, '_') !== false) {
            $parts       = explode('_', $code, 2);
            $pickup_code = trim($parts[0]);
            $order_bn    = trim($parts[1]);
            if ($order_bn === '') {
                $err_msg = '扫码内容无效';
                return null;
            }
        } elseif (preg_match('/^\d{6}$/', $code)) {
            $codeInfo = app::get('wap')->model('delivery_code')->dump(
                array('code' => $code, 'status' => 2),
                'delivery_bn'
            );
            if (!empty($codeInfo['delivery_bn'])) {
                $filter = array_merge($baseFilter, array('delivery_bn' => $codeInfo['delivery_bn']));
                $rows   = $wapDeliveryLib->getList($filter, 0, 1, 'order_createtime desc', 'pending');
                if (!empty($rows[0])) {
                    if (!$rows[0]['can_pickup_verify']) {
                        $err_msg = '该订单当前不可提货';
                        return null;
                    }
                    return $rows[0];
                }
            }
        }

        $filter = array_merge($baseFilter, array('order_bn|foot' => $order_bn));
        $rows   = $wapDeliveryLib->getList($filter, 0, 1, 'order_createtime desc', 'pending');
        if (empty($rows[0])) {
            $err_msg = '未找到自提订单，请核对提货码';
            return null;
        }
        if (!$rows[0]['can_pickup_verify']) {
            $err_msg = '该订单当前不可提货';
            return null;
        }

        return $rows[0];
    }

    /**
     * 请求平台核销提货码
     *
     * 同事在此方法内补充 HTTP 调用，$responseBody 为平台 JSON 响应体。
     * 成功示例：{"code":0,"msg":"操作成功","success":true,"data":"680","traceId":"..."}
     * 已核销：{"code":501,"msg":"订单已核销，请勿重复核销","success":false,...}
     *
     * @return string self::RESULT_OK|self::RESULT_ALREADY|self::RESULT_FAIL
     */
    public function callPlatformPickupVerify($code, array $delivery, &$err_msg = '', array &$platformData = array())
    {
        $platformData = array();

        // TODO: 同事实现平台 HTTP 请求，赋值 $responseBody
        $responseBody = null;
        if ($responseBody === null || $responseBody === '') {
            // 占位：未接入 HTTP 前视为成功，便于联调本地提货流程
            return self::RESULT_OK;
        }

        return $this->parsePlatformResponse($responseBody, $err_msg, $platformData);
    }

    /**
     * 解析平台核销响应
     */
    public function parsePlatformResponse($responseBody, &$err_msg = '', array &$platformData = array())
    {
        $platformData = array();
        $response     = is_array($responseBody) ? $responseBody : json_decode($responseBody, true);
        if (!is_array($response)) {
            $err_msg = '平台响应解析失败';
            return self::RESULT_FAIL;
        }

        $code    = isset($response['code']) ? (int) $response['code'] : -1;
        $success = !empty($response['success']);
        $msg     = trim($response['msg'] ?? '');

        if ($success && $code === 0) {
            $platformData = array(
                'data'    => $response['data'] ?? '',
                'traceId' => $response['traceId'] ?? '',
            );
            return self::RESULT_OK;
        }

        if ($code === 501 || strpos($msg, '已核销') !== false) {
            $platformData = array(
                'traceId' => $response['traceId'] ?? '',
            );
            $err_msg = $msg ?: '订单已核销';
            return self::RESULT_ALREADY;
        }

        $err_msg = $msg ?: '平台核销失败';
        return self::RESULT_FAIL;
    }

    /**
     * 自提核销基础筛选（门店权限 + 自提物流）
     */
    protected function getSelfpickVerifyBaseFilter()
    {
        $filter = array_merge(
            kernel::single('wap_delivery')->getPickupOnlyFilter(),
            array('status|notin' => array(1))
        );
        $is_super = kernel::single('desktop_user')->is_super();
        if (!$is_super) {
            $branch_ids = kernel::single('o2o_store_branch')->getO2OBranchByUser(true);
            if (!empty($branch_ids)) {
                $filter['branch_id'] = $branch_ids;
            }
        }
        return $filter;
    }
}
