<?php
/**
 * SAP主档高级筛选：主档标记（参照基础物料 material_label）
 */
class goldentra_finder_extend_filter_material
{
    public function get_extend_colums()
    {
        return array(
            'material' => array(
                'columns' => array(
                    'material_label' => array(
                        'type' => kernel::single('ome_bill_label')->getBillLabelFilterOptions('goldentra_material'),
                        'label' => '主档标记',
                        'width' => 120,
                        'filtertype' => 'normal',
                        'filterdefault' => true,
                        'editable' => false,
                    ),
                ),
            ),
        );
    }
}
