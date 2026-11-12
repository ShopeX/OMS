<?php
class ome_finder_extend_filter_product_serial{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    function get_extend_colums(){
        $db['product_serial']=array (
            'columns' =>array(
            'order_bn' => array (
                    'type' => 'varchar(30)',
                    'label' => '订单号',
                    'width' => 130,
                    'in_list' => true,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'default_in_list'=>true
                ),
            'bill_no' =>
                array (
                  'type' => 'varchar(32)',
                 'label' => '单据号',
                'editable' => false,
                'in_list' => true,
                'filtertype' => 'normal',
                'filterdefault' => true,
                'default_in_list'=>true

                ),
              'product_name' => 
                array (
                  'type' => 'varchar(200)',
                'label' => '货品名称',
               'editable' => false,
                'in_list' => true,
                'default_in_list'=>true
                ),
               'type_id' =>
                array (
                  'type' => 'table:goods_type@ome',
                    'label' => '类型',
                  'width' => 100,
                  'editable' => false,
                  'in_list' => true,
                  'default_in_list' => true,
                ),
             'brand_id' =>
            array (
              'type' => 'table:brand@ome',
              'label' => '品牌',
              'width' => 75,
              'editable' => false,
              'in_list' => true,
              'default_in_list' => true,
            ),
                    ),
         );
        return $db;
    }
}

?>
