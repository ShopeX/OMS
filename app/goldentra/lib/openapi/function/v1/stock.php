<?php

class goldentra_openapi_function_v1_stock extends goldentra_openapi_function_abstract implements openapi_api_function_interface
{
    public function getList($params, &$code, &$sub_msg)
    {
        $code = 'e000003';
        $sub_msg = '接口不支持查询';
        return false;
    }

    public function add($params, &$code, &$sub_msg)
    {
        $original_bn = isset($params['sync_batch_no']) ? $params['sync_batch_no'] : date('YmdHis');
        if (empty($original_bn) && isset($params['SYNC_BATCH_NO'])) {
            $original_bn = $params['SYNC_BATCH_NO'];
        }
        $this->_write_log('库存快照同步', $original_bn, 'running', $params);

        $lib = kernel::single('goldentra_stock');
        try {
            $stats = $lib->ingestBatchFromApi($params);
        } catch (Exception $e) {
            $code = 'e000008';
            $sub_msg = $e->getMessage();
            return false;
        }

        goldentra_autotask::push('goldentrastock', array(
            'sync_batch_no' => $stats['sync_batch_no'],
            'limit' => 1000,
        ));

        $detail = array(
            'message' => sprintf(
                '库存增量落库成功：%d 条，批次 %s（已加入异步比对队列）',
                $stats['inserted'],
                $stats['sync_batch_no']
            ),
            'stats' => $stats,
        );
        $result = goldentra_openapi_response::succ();
        $this->_write_log('库存快照同步', $original_bn, 'success', $params, $detail);
        return $result;
    }
}
