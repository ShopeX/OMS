<?php
/**
 * 商家账单 listbills 默认请求类（矩阵 statement.get 等由平台子类实现）
 *
 * @see erpapi_ipay_360buy_request_listbills 京东商家账单
 */
class erpapi_ipay_request_listbills extends erpapi_ipay_request_abstract
{
    public function query($sdf)
    {
        return $this->error('当前渠道未实现 listbills 查询', 'listbills_not_implemented');
    }
}
