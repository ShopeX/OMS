<?php

class goldentra_openapi_params_v1_sales extends openapi_api_params_abstract implements openapi_api_params_interface
{
    public function checkParams($method, $params, &$sub_msg)
    {
        if ($method === 'callback') {
            $lib = kernel::single('goldentra_push_callback');
            if ($lib->parseBody($params) !== false) {
                return true;
            }
            $sub_msg = '请提供 BODY/body JSON 数组，或扁平字段 ZPTDHYZ';
            return false;
        }
        return parent::checkParams($method, $params, $sub_msg);
    }

    public function getAppParams($method)
    {
        $callback = array(
            'BODY' => array(
                'type' => 'string',
                'name' => '回调BODY JSON数组',
                'required' => true,
                'desc' => '销售/退货处理结果，字段见接口文档（ZPTDHYZ必填，STATUS/MSGTXT/VBELN*等）',
            ),
            'ZPTDHYZ' => array(
                'type' => 'string',
                'name' => 'OMS发货单号',
                'required' => false,
                'desc' => '单条扁平调用时必填',
            ),
            'STATUS' => array(
                'type' => 'string',
                'name' => '消息状态',
                'required' => false,
                'desc' => 'S成功 / E失败',
            ),
            'MSGTXT' => array(
                'type' => 'string',
                'name' => '消息文本',
                'required' => false,
            ),
            'ZSALESTYPE' => array(
                'type' => 'string',
                'name' => '销售类型',
                'required' => false,
                'desc' => '01销售 / 02退货，用于精确定位',
            ),
        );
        return array('callback' => $callback)[$method];
    }

    public function description($method)
    {
        $description = array(
            'callback' => array(
                'name' => '销售/退货处理结果回调',
                'description' => 'SAP 异步回写 SD003 处理结果；按 ZPTDHYZ 更新 sap_push_sales',
            ),
        );
        return $description[$method];
    }
}
