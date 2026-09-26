<?php

class goldentra_openapi_params_v1_stock extends openapi_api_params_abstract implements openapi_api_params_interface
{
    public function checkParams($method, $params, &$sub_msg)
    {
        if ($method === 'add') {
            $lib = kernel::single('goldentra_stock');
            $lines = $lib->parseStocksPayload($params);
            if ($lines === false) {
                $sub_msg = '请提供 stocks 批量 JSON';
                return false;
            }
            try {
                $lib->validateApiBatchMeta($params);
            } catch (Exception $e) {
                $sub_msg = $e->getMessage();
                return false;
            }
            return true;
        }
        return parent::checkParams($method, $params, $sub_msg);
    }

    public function getAppParams($method)
    {
        $add = array(
            'sync_batch_no' => array(
                'type' => 'string',
                'name' => '推送批次号',
                'required' => true,
                'desc' => goldentra_stock_fields::$descriptions['sync_batch_no'],
            ),
            'stocks' => array(
                'type' => 'string',
                'name' => '库存JSON数组',
                'required' => true,
                'desc' => '多条明细，字段见 stocks 明细结构（V1.3 小写 snake_case）',
            ),
        );

        foreach (goldentra_stock_fields::$line_required as $api) {
            $add[$api] = array(
                'type' => 'string',
                'name' => $api,
                'required' => true,
                'desc' => goldentra_stock_fields::$descriptions[$api],
            );
        }

        $add['unit'] = array(
            'type' => 'string',
            'name' => 'unit',
            'desc' => goldentra_stock_fields::$descriptions['unit'],
        );

        foreach (goldentra_stock_fields::$ext_fields as $ext) {
            $add[$ext] = array(
                'type' => 'string',
                'name' => $ext,
                'desc' => '扩展备注',
            );
        }

        $add['type']['desc'] .= '（OpenAPI 固定 increment）';

        return array('add' => $add)[$method];
    }

    public function description($method)
    {
        $description = array(
            'add' => array(
                'name' => '库存增量同步（stockAdd）',
                'description' => 'V1.3 批量 JSON 字段 stocks；plant=工厂/仓库编码，branch_bn=库位（均落库）；匹配 OMS 仓库优先 plant，找不到再用 plant_库位；明细含 plant/branch_bn/unique_code/qty/type/snapshot_time/unit；type=increment；全量走 FTP CSV',
            ),
        );
        return $description[$method];
    }
}
