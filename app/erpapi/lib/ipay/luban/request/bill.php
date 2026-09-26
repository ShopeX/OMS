<?php
/**
 * 鲁班渠道账单请求（矩阵）
 * 继承默认 {@see erpapi_ipay_request_bill}，固定鲁班门店账单 API，避免在通用 bill 里依赖 node_type 分支。
 *
 * 路由：channel platform=luban → erpapi_ipay_luban_request_bill（见 erpapi_router_request::_get_object_class）
 */
class erpapi_ipay_luban_request_bill extends erpapi_ipay_request_bill
{
    protected function __getQueryApi($sdf)
    {
        return STORE_DOWNLOAD_SHOP_ACCOUNT_ITEM;
    }

    protected function __getDownloadurlApi($sdf)
    {
        return STORE_DOWNLOAD_SHOP_ACCOUNT_ITEM_FILE;
    }
}
