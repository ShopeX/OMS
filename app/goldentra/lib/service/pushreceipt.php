<?php

/**
 * @deprecated 确认收货已并入 sap_push_sales，并由定时任务 goldentrapushconfirm 扫描
 * 保留空壳避免历史 service 配置报错
 */
class goldentra_service_pushreceipt
{
    public function after_sign($delivery_id, array $payload = array())
    {
        return array(true, '已改由定时任务扫描签收后确认收货，签收钩子不再推送');
    }
}
