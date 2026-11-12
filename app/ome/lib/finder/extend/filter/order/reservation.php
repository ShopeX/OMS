<?php
class ome_finder_extend_filter_order_reservation
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    function get_extend_colums()
    {
        $db = [];
//        $db['order_reservation'] = array (
//            'columns' => array (
//                'order_bn' => array (
//                    'type' => 'varchar(50)',
//                    'label' => '订单号',
//                    'width' => 130,
//                    'filtertype' => 'textarea',
//                    'searchtype' => 'nequal',
//                    'filterdefault' => true,
//                    'editable' => false,
//                    'in_list' => true,
//                    'default_in_list' => true,
//                ),
//            )
//        );
//
        return $db;
    }
}
