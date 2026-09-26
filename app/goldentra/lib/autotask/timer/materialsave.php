<?php

class goldentra_autotask_timer_materialsave
{
    public function process($params = array(), &$error_msg = '')
    {
        @ini_set('memory_limit', '512M');
        set_time_limit(0);

        $queue_id = isset($params['queue_id']) ? intval($params['queue_id']) : 0;
        if ($queue_id <= 0) {
            $error_msg = '缺少 queue_id';
            return false;
        }

        $result = kernel::single('goldentra_material_ingest')->processQueueId($queue_id);
        $error_msg = sprintf(
            '主档落库：成功 %d，失败 %d',
            isset($result['ok']) ? $result['ok'] : 0,
            isset($result['fail']) ? $result['fail'] : 0
        );
        if (!empty($result['fail_msgs'])) {
            $error_msg .= '；' . implode('；', array_slice($result['fail_msgs'], 0, 5));
        }
        return $result['fail'] === 0 || $result['ok'] > 0;
    }
}
