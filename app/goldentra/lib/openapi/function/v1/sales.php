<?php

class goldentra_openapi_function_v1_sales extends goldentra_openapi_function_abstract implements openapi_api_function_interface
{
    public function getList($params, &$code, &$sub_msg)
    {
        $code = 'e000003';
        $sub_msg = '接口不支持查询';
        return false;
    }

    public function add($params, &$code, &$sub_msg)
    {
        $code = 'e000003';
        $sub_msg = '请使用 goldentra.sales.callback';
        return false;
    }

    /**
     * SAP→OMS：销售/退货处理结果回调
     */
    public function callback($params, &$code, &$sub_msg)
    {
        $original_bn = 'salesCallback' . date('YmdHis');
        $this->_write_log('销售退货SAP回调', $original_bn, 'running', $params);

        try {
            $stats = kernel::single('goldentra_push_callback')->applyFromApi($params);
        } catch (Exception $e) {
            $code = 'e000006';
            $sub_msg = $e->getMessage();
            return false;
        }

        if (!empty($stats['fail']) && empty($stats['ok'])) {
            $code = 'e000008';
            $sub_msg = !empty($stats['fail_msgs'])
                ? implode('；', array_slice($stats['fail_msgs'], 0, 5))
                : '回调处理失败';
            return false;
        }

        $detail = array(
            'message' => sprintf('回调处理完成：成功 %d，失败 %d', $stats['ok'], $stats['fail']),
            'stats' => $stats,
        );
        $result = goldentra_openapi_response::succ();
        $result['data'] = $detail;
        $this->_write_log('销售退货SAP回调', $original_bn, 'success', $params, $detail);
        return $result;
    }
}
