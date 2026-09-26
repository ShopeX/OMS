<?php

class goldentra_autotask_timer_materialsaveready
{
    public function process($params = array(), &$error_msg = '')
    {
        $limit = !empty($params['limit']) ? intval($params['limit']) : 20;
        $result = kernel::single('goldentra_material_ingest')->recoverStale($limit);
        $error_msg = sprintf(
            '主档落库补投：重置卡住 %d，补投 %d',
            isset($result['reset_stuck']) ? $result['reset_stuck'] : 0,
            isset($result['pushed']) ? $result['pushed'] : 0
        );
        return true;
    }
}
