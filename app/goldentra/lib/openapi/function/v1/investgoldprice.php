<?php

class goldentra_openapi_function_v1_investgoldprice extends goldentra_openapi_function_abstract implements openapi_api_function_interface
{
    public function getList($params, &$code, &$sub_msg)
    {
        $code = 'e000003';
        $sub_msg = '接口不支持查询';
        return false;
    }

    public function add($params, &$code, &$sub_msg)
    {
        $original_bn = 'investGold' . date('YmdHis');
        $this->_write_log('投资金价下发', $original_bn, 'running', $params);

        $lib = kernel::single('goldentra_invest_goldprice');
        $rows = $lib->parsePayload($params);
        if ($rows === false) {
            $code = 'e000006';
            $sub_msg = '参数结构体错误：请提供 items/BODY JSON，或扁平 request_id/ZIDNUM';
            return false;
        }

        $sync_batch_no = '';
        if (!empty($params['sync_batch_no'])) {
            $sync_batch_no = trim($params['sync_batch_no']);
        } elseif (!empty($params['PACKMSGID'])) {
            $sync_batch_no = trim($params['PACKMSGID']);
        } elseif (!empty($params['packmsgid'])) {
            $sync_batch_no = trim($params['packmsgid']);
        }

        $stats = $lib->upsertBatchFromApi($rows, 'sap', $sync_batch_no);
        if (!empty($stats['fail']) && empty($stats['ok'])) {
            $code = 'e000008';
            $sub_msg = !empty($stats['fail_msgs'])
                ? implode('；', array_slice($stats['fail_msgs'], 0, 8))
                : '投资金价处理失败';
            $this->_write_log('投资金价下发', $original_bn, 'fail', $params, $stats);
            return false;
        }

        $detail = array(
            'message' => sprintf(
                '投资金价处理完成：新增 %d，更新 %d，跳过 %d，失败 %d',
                $stats['inserted'],
                $stats['updated'],
                $stats['skipped'],
                $stats['fail']
            ),
            'stats' => $stats,
        );
        $result = goldentra_openapi_response::succ($detail['message']);
        $result['data'] = $detail;
        // 便于 PI 映射 SAP 通用响应
        $result['STATUS'] = empty($stats['fail']) ? 'S' : 'E';
        $result['MSGTXT'] = $detail['message'];
        $this->_write_log('投资金价下发', $original_bn, 'success', $params, $detail);
        return $result;
    }
}
