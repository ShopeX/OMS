<?php

class goldentra_openapi_function_v1_goldprice extends goldentra_openapi_function_abstract implements openapi_api_function_interface
{
    public function getList($params, &$code, &$sub_msg)
    {
        $code = 'e000003';
        $sub_msg = '接口不支持查询';
        return false;
    }

    public function add($params, &$code, &$sub_msg)
    {
        $original_bn = date('YmdH', time());
        $this->_write_log('每日金价信息同步', $original_bn, 'running', $params);

        $lib = kernel::single('goldentra_daily_goldprice');
        $prices = $lib->parsePricesPayload($params);
        if ($prices === false) {
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

        try {
            $stats = $lib->upsertBatchFromApi($prices, 'sap', $sync_batch_no);
        } catch (Exception $e) {
            $code = 'e000008';
            $sub_msg = $e->getMessage();
            return false;
        }

        $detail = array(
            'message' => sprintf(
                '每日金价处理成功：新增 %d，更新 %d，跳过 %d',
                $stats['inserted'],
                $stats['updated'],
                $stats['skipped']
            ),
            'stats' => $stats,
        );
        $result = goldentra_openapi_response::succ();
        $this->_write_log('每日金价信息同步', $original_bn, 'success', $params, $detail);
        return $result;
    }
}
