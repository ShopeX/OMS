<?php
$db['return_batch']=array (
  'columns' => 
  array (
   'batch_id'=>array(
   'type' => 'number',
      'required' => true,
      'pkey' => true,
      'extra' => 'auto_increment',
      'editable' => false,
   ),
   'shop_id' =>
    array (
      'type' => 'table:shop@ome',
      'editable' => false,
      'label' => '店铺',
      'in_list' => true,
      'default_in_list' => true,
    ),

   
    'batch_type' => 
    array (
      'type' =>
      array (
        'refuse' => '拒绝退款',
        'accept_refund' => '同意退款',
        'accept_return' => '同意退货',
        'refuse_return' => '拒绝退货',
      ),
      'default' => 'refuse',
      'editable' => false,
      'label'=>'批量类型',
      'in_list' => true,
      'default_in_list' => true,
    ),
    
    'memo' =>
    array (
      'type' => 'varchar(200)',
      'editable' => false,
      'label' => '批量说明',
      'in_list' => true,
      'default_in_list' => true,
      'width' => 75,
    ),
    'picurl' =>
    array (
      'type' => 'varchar(255)',
      'editable' => false,
      'label' => '图片',
    ),
   'is_default' =>
    array (
      'type' => 'bool',
      'default' => 'false',
      'required' => true,
      'editable' => false,
      'in_list' => true,
      'label' => '是否默认',
      'width' => 80,
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
   'imgext' =>
    array (
      'type' => 'varchar(10)',
      'editable' => false,
      
    ),
   
    
),
 
  'engine' => 'innodb',
  'version' => '$Rev:  $',
  'comment'=>'批量操作表',
);
