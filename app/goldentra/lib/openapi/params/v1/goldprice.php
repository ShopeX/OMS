<?php

class goldentra_openapi_params_v1_goldprice extends openapi_api_params_abstract implements openapi_api_params_interface
{
    public function checkParams($method, $params, &$sub_msg)
    {
        if ($method === 'add') {
            if (empty($params['sync_batch_no'])) {
                $sub_msg = 'sync_batch_no 不能为空';
                return false;
            }
            $lib = kernel::single('goldentra_daily_goldprice');
            if ($lib->parsePricesPayload($params) !== false) {
                return true;
            }
            $sub_msg = '请提供 goldprices 批量 JSON';
            return false;
        }
        return parent::checkParams($method, $params, $sub_msg);
    }

    public function getAppParams($method)
    {
        $add = array(
            'sync_batch_no' => array(
                'type' => 'string',
                'name' => '同步批次号',
                'required' => true,
            ),
            'goldprices' => array(
                'type' => 'string',
                'name' => '金价JSON数组',
                'required' => true,
                'desc' => '多条金价，字段见 goldprices 明细结构（V1.3 小写 snake_case）',
            ),
        );

        foreach (goldentra_daily_goldprice_fields::$field_map as $api => $col) {
            $pos_req = in_array($api, goldentra_daily_goldprice_fields::$pos_required, true);
            $insert_req = in_array($api, goldentra_daily_goldprice_fields::$oms_insert_required, true);
            $desc = goldentra_daily_goldprice_fields::$descriptions[$api];
            if ($insert_req) {
                $desc .= '（新增记录 OMS 必填）';
            }
            $add[$api] = array(
                'type' => 'string',
                'name' => $api,
                'required' => $pos_req,
                'desc' => $desc,
            );
        }

        foreach (goldentra_daily_goldprice_fields::$ext_fields as $ext) {
            $add[$ext] = array(
                'type' => 'string',
                'name' => $ext,
                'desc' => '扩展备注',
            );
        }

        return array('add' => $add)[$method];
    }

    public function description($method)
    {
        $description = array(
            'add' => array(
                'name' => '每日金价同步（priceAdd）',
                'description' => '批量 JSON 字段 goldprices；唯一键 shop_bn+date+pricedimension_code+sales_area+pricedimension_name；缺必填整批失败；同价跳过；变价仅更新 gold_price',
            ),
        );
        return $description[$method];
    }
}
