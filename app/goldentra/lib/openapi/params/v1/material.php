<?php

class goldentra_openapi_params_v1_material extends openapi_api_params_abstract implements openapi_api_params_interface
{
    public function checkParams($method, $params, &$sub_msg)
    {
        if ($method === 'add') {
            if (empty($params['sync_batch_no'])) {
                $sub_msg = 'sync_batch_no 不能为空';
                return false;
            }
            if (!empty($params['materials'])
                || isset($params['MATERIALS']['MATERIAL'])
                || goldentra_api_normalize::pick($params, 'unique_code') !== null) {
                return true;
            }
            $sub_msg = '请提供 materials 批量 JSON 或单条 unique_code 等主档字段';
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
            'materials' => array(
                'type' => 'string',
                'name' => '主档JSON数组',
                'required' => true,
                'desc' => '多条主档，字段见 materials 明细结构（V1.3 小写 snake_case）',
            ),
        );

        foreach (goldentra_material_fields::$field_map as $api => $col) {
            $required = in_array($api, goldentra_material_fields::$oms_required, true);
            $desc = isset(goldentra_material_fields::$descriptions[$api])
                ? goldentra_material_fields::$descriptions[$api]
                : '';
            if (in_array($api, goldentra_material_fields::$diamond_required, true)) {
                $desc .= '（钻石件必填）';
            }
            $add[$api] = array(
                'type' => 'string',
                'name' => $api,
                'required' => $required,
                'desc' => $desc,
            );
        }

        foreach (goldentra_material_fields::$ext_fields as $ext) {
            $desc = '扩展备注';
            if ($ext === 'ext_1') {
                $desc = '备注1：为 X 时更新门店编码(store_bn)、金价定价维度(pricedimension_code)；非 X 时这两项保持原值';
            }
            $add[$ext] = array(
                'type' => 'string',
                'name' => $ext,
                'desc' => $desc,
            );
        }

        return array('add' => $add)[$method];
    }

    public function description($method)
    {
        $description = array(
            'add' => array(
                'name' => 'SAP主档同步（materialAdd）',
                'description' => '批量 JSON 字段 materials；按 unique_code upsert；必填仅 unique_code/mold_code/weight/price_method；可选 material_no(物料号)、material_desc(物料描述)；同唯一码多门店同步时，仅当 ext_1(备注1)=X 才更新 store_bn、pricedimension_code；扩展字段 ext_1~ext_20 写 ext_field',
            ),
        );

        return $description[$method];
    }
}
