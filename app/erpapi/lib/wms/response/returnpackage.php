<?php
class erpapi_wms_response_returnpackage extends erpapi_wms_response_abstract
{
    public function report($params)
    {
        $this->__apilog['title'] = '退货包裹状态通知接口';
        $this->__apilog['original_bn'] = '';
        return array('_stub' => 1);
    }
}
