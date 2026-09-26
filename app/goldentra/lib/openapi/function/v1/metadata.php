<?php

class goldentra_openapi_function_v1_metadata extends goldentra_openapi_function_abstract implements openapi_api_function_interface
{
    public function getList($params, &$code, &$sub_msg)
    {
        $code = 'e000003';
        $sub_msg = '接口不支持查询';
        return false;
    }

    public function add($params, &$code, &$sub_msg)
    {
        $rows = goldentra_metadata::parseApiPayload($params);
        if ($rows === false) {
            $code = 'e000006';
            $sub_msg = '请提供 metadatas JSON 或单条 code/name/header/items';
            return false;
        }

        $sync_batch_no = isset($params['sync_batch_no']) ? trim($params['sync_batch_no']) : '';
        $ok = 0;
        $fail = 0;
        $fail_msgs = array();
        $metadata_ids = array();

        foreach ($rows as $row) {
            $rowKey = array(
                'code' => goldentra_api_normalize::pickString($row, 'code'),
            );
            $original_bn = $rowKey['code'] !== '' ? $rowKey['code'] : date('YmdHis');
            $this->_write_log('数据字典同步', $original_bn, 'running', array_merge($params, $row));
            try {
                $result = kernel::single('goldentra_metadata')->upsertFromApi($row);
                $ok++;
                $metadata_ids[] = $result['metadata_id'];
                $this->_write_log('数据字典同步', $original_bn, 'success', array_merge($params, $row), $result);
            } catch (Exception $e) {
                $fail++;
                $fail_msgs[] = goldentra_api_normalize::formatRowError($e->getMessage(), $rowKey);
                $this->_write_log('数据字典同步', $original_bn, 'fail', array_merge($params, $row), array('msg' => $e->getMessage()));
            }
        }

        if ($fail > 0) {
            $code = 'e000006';
            $sub_msg = goldentra_openapi_response::failSubMsg(
                sprintf('同步失败 %d 条', $fail),
                $fail_msgs
            );
            return false;
        }

        return goldentra_openapi_response::succ(sprintf('同步成功 %d 条', $ok));
    }
}
