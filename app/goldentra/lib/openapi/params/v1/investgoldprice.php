<?php

class goldentra_openapi_params_v1_investgoldprice extends openapi_api_params_abstract implements openapi_api_params_interface
{
    public function checkParams($method, $params, &$sub_msg)
    {
        if ($method === 'add') {
            $lib = kernel::single('goldentra_invest_goldprice');
            if ($lib->parsePayload($params) !== false) {
                return true;
            }
            $sub_msg = '请提供 items/BODY JSON 数组，或扁平字段 request_id(ZIDNUM)';
            return false;
        }
        return parent::checkParams($method, $params, $sub_msg);
    }

    public function getAppParams($method)
    {
        $add = array(
            'items' => array(
                'type' => 'string',
                'name' => '投资金价JSON数组',
                'required' => true,
                'desc' => '多条；也可用 BODY。字段为小写下划线（兼容 camelCase/SAP 大写），见接口文档',
            ),
            'BODY' => array(
                'type' => 'string',
                'name' => 'BODY JSON数组',
                'required' => false,
                'desc' => '与 items 二选一',
            ),
            'sync_batch_no' => array(
                'type' => 'string',
                'name' => '同步批次号',
                'required' => false,
            ),
            'request_id' => array(
                'type' => 'string',
                'name' => '请求ID(ZIDNUM)',
                'required' => false,
                'desc' => '单条扁平调用时必填',
            ),
            'sync_type' => array(
                'type' => 'string',
                'name' => '变更类型(ZJGBG)',
                'required' => false,
                'desc' => '1类目基准价 / 2商品加价 / 3两者',
            ),
            'sales_organize' => array(
                'type' => 'string',
                'name' => '销售组织(VKORG)',
                'required' => false,
            ),
            'brand_id' => array(
                'type' => 'string',
                'name' => '品牌(ZPP)',
                'required' => false,
            ),
            'category_code' => array(
                'type' => 'string',
                'name' => '类目(MATKL)',
                'required' => false,
            ),
            'price' => array(
                'type' => 'string',
                'name' => '基准价(ZJG_ZTJJ)',
                'required' => false,
            ),
            'item_code' => array(
                'type' => 'string',
                'name' => '商品编码(MATNR)',
                'required' => false,
            ),
            'add_price' => array(
                'type' => 'string',
                'name' => '加价(ZJG_ZTJ1)',
                'required' => false,
            ),
        );
        foreach (goldentra_invest_goldprice_fields::$ext_fields as $ext) {
            $add[$ext] = array(
                'type' => 'string',
                'name' => $ext,
                'required' => false,
                'desc' => isset(goldentra_invest_goldprice_fields::$descriptions[$ext])
                    ? goldentra_invest_goldprice_fields::$descriptions[$ext]
                    : '扩展备注',
            );
        }
        return array('add' => $add)[$method];
    }

    public function description($method)
    {
        $description = array(
            'add' => array(
                'name' => '投资金价下发',
                'description' => 'SAP投资金条基准价/加价下发；唯一键 sales_organize+brand_id+category_code+item_code；非每日金价接口',
            ),
        );
        return $description[$method];
    }
}
