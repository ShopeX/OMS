<?php
class ome_finder_extend_filter_delivery_bill
{
    function get_extend_colums()
    {
        // 线上店铺列表（按是否常用店铺进行排序），与订单高级筛选一致
        $shopFilter = array(
            's_type' => '1',
        );
        $shopNames = array_column(
            app::get('ome')->model('shop')->getList('name,shop_id', $shopFilter, 0, -1, 'is_common_shop ASC, s_status DESC'),
            'name',
            'shop_id'
        );

        $db['delivery_bill'] = array(
            'columns' => array(
                'shop_id' => array(
                    'type'          => $shopNames,
                    'label'         => '来源店铺',
                    'width'         => 100,
                    'editable'      => false,
                    'in_list'       => true,
                    'filtertype'    => 'fuzzy_search_multiple',
                    'filterdefault' => true,
                ),
            ),
        );

        return $db;
    }
}
