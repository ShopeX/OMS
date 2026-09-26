<?php

class goldentra_openapi_params_v1_metadata extends openapi_api_params_abstract implements openapi_api_params_interface
{
    public function checkParams($method, $params, &$sub_msg)
    {
        if ($method !== 'add') {
            return parent::checkParams($method, $params, $sub_msg);
        }

        $rows = goldentra_metadata::parseApiPayload($params);
        if ($rows === false) {
            $sub_msg = '请提供 metadatas JSON 或单条 code/name/header/items';
            return false;
        }

        foreach ($rows as $row) {
            $err = goldentra_metadata::validateApiRow($row);
            if ($err !== true) {
                $sub_msg = $err;
                return false;
            }
        }

        return true;
    }

    public function getAppParams($method)
    {
        $ext = array();
        for ($i = 1; $i <= 20; $i++) {
            $ext['ext_' . $i] = array('type' => 'string', 'name' => 'ext_' . $i, 'desc' => '扩展字段');
        }

        return array(
            'sync_batch_no' => array(
                'type' => 'string',
                'required' => false,
                'name' => '同步批号',
                'desc' => 'SAP 同步批次号',
            ),
            'metadatas' => array(
                'type' => 'string',
                'required' => false,
                'name' => '字典JSON',
                'desc' => '单条对象或数组；含 code/name/header/items（兼容 item）',
            ),
            'code' => array('type' => 'string', 'required' => false, 'name' => '业务编码', 'desc' => '单条同步时使用'),
            'name' => array('type' => 'string', 'required' => false, 'name' => '业务名称', 'desc' => '单条同步时使用'),
            'header' => array(
                'type' => 'object',
                'required' => false,
                'name' => '字典头',
                'desc' => '含 unique_key 及 ext_1~ext_20',
            ),
            'items' => array(
                'type' => 'array',
                'required' => false,
                'name' => '字典值列表',
                'desc' => '每项含 unique_key 及 ext_1~ext_20；SAP 可传 item',
            ),
        ) + $ext;
    }

    public function description($method)
    {
        return array(
            'name' => '数据字典同步',
            'description' => 'V1.3：metadatas 批量 JSON 或单条 code/name/header/items；header.unique_key 为字典头唯一键；items/item 为字典值；扩展字段 ext_1~ext_20 写 ext_field JSON',
        );
    }
}
