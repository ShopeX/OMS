<?php

class goldentra_openapi_function_v1_material extends goldentra_openapi_function_abstract implements openapi_api_function_interface
{
    public function getList($params, &$code, &$sub_msg)
    {
        $code = 'e000003';
        $sub_msg = '接口不支持查询';
        return false;
    }

    public function add($params, &$code, &$sub_msg)
    {
        @ini_set('memory_limit', '512M');
        $original_bn = sprintf('syncMaterial%s', date('Ymd', time()));
        $this->_write_log('主档信息同步', $original_bn, 'running', $params);

        $materials = $this->_parseMaterials($params);
        if ($materials === false) {
            $code = 'e000006';
            $sub_msg = '参数结构体错误';
            return false;
        }

        $sync_batch_no = isset($params['sync_batch_no']) ? trim($params['sync_batch_no']) : '';
        if ($sync_batch_no === '') {
            $code = 'e000006';
            $sub_msg = 'sync_batch_no 不能为空';
            return false;
        }

        list($flag, $detail) = kernel::single('goldentra_material_ingest')->enqueueFromApi(
            $materials,
            $sync_batch_no
        );
        if (!$flag) {
            $code = 'e000006';
            $sub_msg = isset($detail['message']) ? $detail['message'] : '入队失败';
            $this->_write_log('主档信息同步', $original_bn, 'fail', $params, $detail);
            return false;
        }

        $result = goldentra_openapi_response::succ($detail['message']);
        $this->_write_log('主档信息同步', $original_bn, 'success', $params, $detail);
        return $result;
    }

    private function _parseMaterials($params)
    {
        if (!empty($params['materials'])) {
            return goldentra_api_normalize::decodeJsonBatch($params['materials'], 'unique_code');
        }

        if (isset($params['MATERIALS']['MATERIAL'])) {
            $materials = $params['MATERIALS']['MATERIAL'];
            if (goldentra_api_normalize::pick($materials, 'unique_code') !== null) {
                return array($materials);
            }
            return is_array($materials) ? $materials : false;
        }

        if (goldentra_api_normalize::pick($params, 'unique_code') !== null) {
            return array($params);
        }

        return false;
    }
}
