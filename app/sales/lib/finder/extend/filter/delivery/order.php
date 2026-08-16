<?php
class sales_finder_extend_filter_delivery_order
{
    public function get_extend_colums()
    {
        $db['delivery_order'] = array(
            'columns' => array(
                // 订单号保存在发货销售明细中，主列表通过模型过滤转换为 delivery_id。
                'order_bn' => array(
                    'type'          => 'varchar(32)',
                    'label'         => '订单号',
                    'editable'      => false,
                    'filtertype'    => 'textarea',
                    'filterdefault' => true,
                ),
            ),
        );

        return $db;
    }
}
