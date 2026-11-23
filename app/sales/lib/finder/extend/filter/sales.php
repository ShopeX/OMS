<?php
class sales_finder_extend_filter_sales{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    function get_extend_colums(){
        $db['sales']=array (
            'columns' => array (
                'order_bn' => array (
                    'type' => 'varchar(30)',
                    'label' => '订单号',
                    'width' => 180,
                    'filtertype' => 'textarea',
                    'filterdefault' => true,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                ),              
                'shop_id' =>array (//店铺名称
                        'type' => 'table:shop@ome',
                        'filtertype' => 'normal',
                        'filterdefault' => true,
                        'default_in_list'=>true,
                        'in_list'=>true,
                        'label' => '店铺名称',
                        'comment' => '店铺名称',
                ),
                'original_bn' =>array (//原始单据号
                        'type' => 'varchar(32)',
                        'filtertype' => 'normal',
                        'filterdefault' => true,
                        'default_in_list'=>true,
                        'in_list'=>true,
                        'label' => '发货单号',
                        'comment' => '发货单号',
                ),
                'ship_area' =>
                array (
                  'type' => 'region',
                  'label' => '收货地区',
                  'comment' => '收货人地区',
                  'editable' => false,
                  'filtertype' => 'normal',
                  'filterdefault' => true,
                  'width' =>130,
                  'in_list' => true,
                  'default_in_list' => true,
                  'sdfpath' => 'consignee/area',
                ),
                'bn' =>array (
                        'type' => 'varchar(32)',
                        'filtertype' => 'normal',
                        'filterdefault' => true,
                        'default_in_list'=>true,
                        'in_list'=>true,
                        'label' => '基础物料编码',
                        'comment' => '基础物料编码',
                ),
                'product_name' =>array (
                        'type' => 'varchar(32)',
                        'filtertype' => 'normal',
                        'filterdefault' => true,
                        'default_in_list'=>true,
                        'in_list'=>true,
                        'label' => '基础物料名称',
                        'comment' => '基础物料名称',
                ),

                
            )
        );
        return $db;
    }
}
