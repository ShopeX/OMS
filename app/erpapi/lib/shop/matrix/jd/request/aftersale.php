<?php
/**
 * 京东厂直 - 售后状态回传矩阵
 * 与 POP(360buy) 的 SHOP_AGREE_RETURN_GOOD / SHOP_REFUSE_RETURN_GOOD 区分，厂直走 store.refund.check（与 finance::_getAddRefundParams 入参结构一致）
 */
class erpapi_shop_matrix_jd_request_aftersale extends erpapi_shop_request_aftersale
{
    /**
     * 回传接口：同意退货(3)、拒绝(5)均走退款审核
     */
    protected function __afterSaleApi($status, $returnInfo = null)
    {
        if (in_array((string) $status, array('3', '5'), true)) {
            return SHOP_REFUND_CHECK;
        }

        return '';
    }

    /**
     * 与 erpapi_shop_matrix_jd_request_finance::_getAddRefundParams 中 params 及行尾备注一致（tid 见方法内注释）
     */
    protected function __formatAfterSaleParams($aftersale, $status)
    {
        $userName = kernel::single('desktop_user')->get_name();
        if ($userName === null || $userName === '') {
            $oper = kernel::single('ome_func')->getDesktopUser();
            $userName = $oper['op_name'] ? $oper['op_name'] : 'ERP';
        }

        $st = (string) $status;
        if ($st === '5') {
            $approval_state = 2;
            $suggestion     = $userName . '拒绝';
            if (!empty($aftersale['refuse_message'])) {
                $suggestion .= '：' . $aftersale['refuse_message'];
            } elseif (!empty($aftersale['memo']) && is_array($aftersale['memo']) && !empty($aftersale['memo']['refuse_message'])) {
                $suggestion .= '：' . $aftersale['memo']['refuse_message'];
            }
        } else {
            $approval_state = 1;
            $suggestion     = $userName . '同意';
        }

        // tid：订单编号 — 父类 erpapi_shop_request_aftersale::updateAfterSaleStatus 中赋值 $params['tid'] = $order['order_bn']
        return array(
            'approval_suggestion' => $suggestion,  # 审核意见
            'approval_state'      => $approval_state,  # 审核状态 1:审核通过 2:审核不通过
            'refund_id'           => $aftersale['return_bn'],  # 售前退款数据唯一标示（售后退货场景为售后申请单号 return_bn，对齐矩阵推送 refund_id）
            'operator_state'      => '5',  # 操作状态：5新订单;9正在出库;10 出库成功;15正在发货;16发货成功
        );
    }

    /**
     * 卖家确认收货（厂直 store.return.good.confirm：refund_id + tid）
     * tid：优先售后单 platform_order_bn；为空则按 order_id 查 orders.platform_order_bn；仍为空则 order_bn 托底（换货子单用 relate_order_bn）
     *
     * @param array $sdf return_bn、platform_order_bn、order_id（由 returngoods_confirm 传入）
     */
    public function returnGoodsConfirm($sdf)
    {
        $refundId        = isset($sdf['return_bn']) ? $sdf['return_bn'] : '';
        $orderId         = isset($sdf['order_id']) ? $sdf['order_id'] : null;
        $tidFromReturn   = isset($sdf['platform_order_bn']) ? trim((string) $sdf['platform_order_bn']) : '';

        $tid = $tidFromReturn;
        if ($tid === '' && $orderId) {
            $orderRow = app::get('ome')->model('orders')->db_dump(
                array('order_id' => $orderId),
                'platform_order_bn,order_bn,relate_order_bn,createway'
            );
            if ($orderRow) {
                $tid = isset($orderRow['platform_order_bn']) ? trim((string) $orderRow['platform_order_bn']) : '';
                if ($tid === '') {
                    $tid = ($orderRow['createway'] == 'after' && !empty($orderRow['relate_order_bn']))
                        ? $orderRow['relate_order_bn']
                        : $orderRow['order_bn'];
                }
            }
        }

        if ($refundId === '' || $tid === '') {
            return;
        }

        $title = '售后确认收货[' . $refundId . ']';
        $data  = array(
            'refund_id' => $refundId,
            'tid'       => $tid,
        );
        $this->__caller->call(SHOP_RETURN_GOOD_CONFIRM, $data, array(), $title, 10, $refundId);
    }
}
