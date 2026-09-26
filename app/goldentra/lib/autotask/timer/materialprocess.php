<?php

class goldentra_autotask_timer_materialprocess
{
    public function process($params = array(), &$error_msg = '')
    {
        $processor = kernel::single('goldentra_material_processor');

        if (!empty($params['unique_codes'])) {
            $codes = is_array($params['unique_codes'])
                ? $params['unique_codes']
                : explode(',', $params['unique_codes']);
            $result = $processor->processByUniqueCodes($codes);
        } elseif (!empty($params['material_ids'])) {
            $ids = is_array($params['material_ids'])
                ? $params['material_ids']
                : explode(',', $params['material_ids']);
            $result = $processor->processByIds($ids);
        } else {
            $error_msg = '缺少 unique_codes 或 material_ids，拒绝扫描 pending';
            return false;
        }

        $error_msg = sprintf('主档转换：成功 %d，失败 %d', $result['ok'], $result['fail']);
        if (!empty($result['fail_msgs'])) {
            $error_msg .= '；' . implode('；', array_slice($result['fail_msgs'], 0, 5));
        }
        return $result['fail'] === 0 || $result['ok'] > 0;
    }
}
