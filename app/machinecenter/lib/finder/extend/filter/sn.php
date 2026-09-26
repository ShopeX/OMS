<?php

class machinecenter_finder_extend_filter_sn
{
    function get_extend_colums()
    {
        $db['material_sn'] = array (
            'columns' => array (
                'order_bn'       => array (
                    'type'            => 'varchar(32)',
                    'label'           => '订单号',
                    'default_in_list' => true,
                    'searchtype'      => 'nequal',
                    'in_list'         => true,
                    'filtertype'      => 'textarea',
                    'filterdefault'   => true,
                    'order'           => 10,
                    'width'           => 200,
                ),
                'bill_bn'       => array (
                    'type'            => 'varchar(32)',
                    'label'           => '业务单号',
                    'default_in_list' => true,
                    'searchtype'      => 'nequal',
                    'in_list'         => true,
                    'filtertype'      => 'textarea',
                    'filterdefault'   => true,
                    'order'           => 10,
                    'width'           => 200,
                ),
                'original_bn'    =>
                    array (
                        'type'            => 'varchar(32)',
                        'label'           => '原始单据号',
                        'default_in_list' => true,
                        'searchtype'      => 'nequal',
                        'in_list'         => true,
                        'filtertype'      => 'textarea',
                        'filterdefault'   => true,
                        'order'           => 10,
                        'width'           => 200,
                    ),
                'sn_label' => array(
                    'type' => kernel::single('ome_bill_label')->getBillLabelFilterOptions('material_sn'),
                    'label' => 'SN标记',
                    'width' => 120,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'editable' => false,
                ),
            )
        );
        return $db;
    }
}

