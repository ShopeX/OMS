<?php
/**
 * 京东商家账单 statement.get（360buy_bills）
 *
 * 路由：listbills_query → erpapi_ipay_360buy_request_listbills
 */
class erpapi_ipay_360buy_request_listbills extends erpapi_ipay_request_listbills
{
    public function query($sdf)
    {
        $rs = $this->__caller->call(
            SHOP_JD_TRADE_STATEMENT_GET,
            $sdf,
            array(),
            '京东商家账单statement.get',
            10,
            $this->__channelObj->channel['channel_bn']
        );
        if (is_array($rs)) {
            unset($rs['response'], $rs['responseHeader']);
        }

        return $rs;
    }
}
