<?php

class goldentra_autotask_timer_stockprocess
{
    public function process($params = array(), &$error_msg = '')
    {
        @ini_set('memory_limit', '512M');
        set_time_limit(0);

        $batchLib = kernel::single('goldentra_stock_processor_batch');
        $limit = isset($params['limit']) ? (int) $params['limit'] : 1000;
        if ($limit <= 0) {
            $limit = 1000;
        }
        $mode = isset($params['mode']) ? trim($params['mode']) : '';

        if ($mode === 'increment') {
            $n = kernel::single('goldentra_stock_processor')->processPending($limit);
            $error_msg = sprintf('increment processed %d', $n);
            return true;
        }

        if (!empty($params['sync_batch_no'])) {
            $sync_batch_no = trim($params['sync_batch_no']);
            if ($batchLib->hasFullBatch($sync_batch_no)) {
                $result = $batchLib->processFullBatch($sync_batch_no);
                $error_msg = sprintf(
                    'full batch %s succ=%d fail=%d skip=%d',
                    $result['sync_batch_no'],
                    (int) $result['succ'],
                    (int) $result['fail'],
                    (int) $result['skip']
                );
                return true;
            }

            $n = kernel::single('goldentra_stock_processor')->processPending($limit, $sync_batch_no);
            $error_msg = sprintf('increment batch %s processed %d', $sync_batch_no, $n);
            return true;
        }

        $full_batch = $batchLib->findPendingFullBatch();
        if ($full_batch !== '') {
            $result = $batchLib->processFullBatch($full_batch);
            $error_msg = sprintf(
                'full batch %s succ=%d fail=%d skip=%d',
                $result['sync_batch_no'],
                (int) $result['succ'],
                (int) $result['fail'],
                (int) $result['skip']
            );
            return true;
        }

        $n = kernel::single('goldentra_stock_processor')->processPending($limit);
        $error_msg = sprintf('increment processed %d', $n);
        return true;
    }
}
