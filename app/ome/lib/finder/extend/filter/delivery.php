<?php
class ome_finder_extend_filter_delivery{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    function get_extend_colums(){
        $orderTypeObj = app::get('omeauto')->model('order_type');
        $types = $orderTypeObj->getList('tid,name',array('disabled'=>'false','delivery_group'=>'true'));
        $delivery_group = array();
        foreach($types as $type){
            $delivery_group[$type['tid']] = $type['name'];
        }

        $branchObj = app::get('ome')->model('branch');
        $bCount = $branchObj->count();
        $branchPanel = ($bCount>1) ? 'delivery_finder_top' : '';

        $db['delivery']=array (
            'columns' => array (
                'delivery_time' => array (
                    'type' => 'time',
                    'label' => '发货时间',
                    'comment' => '发货时间',
                    'editable' => false,
                    'filtertype' => 'time',
                    'filterdefault' => true,
                    'in_list' => true,
                ),
                'ship_tel_mobile' => array (
                    'type' => 'varchar(30)',
                    'label' => '收货人联系电话',
                    'comment' => '收货人联系电话',
                    'editable' => false,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'in_list' => true,
                ),
                'delivery_ident' => array (
                    'type' => 'varchar(30)',
                    'label' => '打印批次号',
                    'width' => 130,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                ),
                'order_bn' => array (
                    'type' => 'varchar(30)',
                    'label' => '订单号',
                    'width' => 130,
                    'filtertype' => 'textarea',
                    'filterdefault' => true,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                ),
                'logi_no_ext' => array (
                    'type' => 'varchar(50)',
                    'label' => '物流单号',
                    'comment' => '物流单号',
                    'editable' => false,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'width' =>110,
                ),
		        'logi_no' => array (
                    'type' => 'varchar(50)',
                    'label' => '物流单号',
                    'comment' => '物流单号',
                    'editable' => false,
                    'width' =>110,
                    'in_list' => true,
                    'default_in_list' => true,
                ),
                'member_uname' => array (
                  'type' => 'varchar(50)',
                  'label' => '会员用户名',
                  'width' => 75,
                  'editable' => false,
                  'filtertype' => 'normal',
                  'filterdefault' => 'true',
                  'in_list' => true,
                  'default_in_list' => true,
                ),
                'product_bn' => array (
                    'type' => 'varchar(30)',
                    'label' => '货号',
                    'width' => 85,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                    'panel_id' => 'delivery_finder_top',
                ),
                'product_barcode' => array (
                    'type' => 'varchar(32)',
                    'label' => '条形码',
                    'width' => 110,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                ),
                'skuNum' =>
                array(
                    'type' => 'skunum',
                    'filtertype' => 'normal',
                    'required' => true,
                    'label' => '商品种类',
                    'comment' => '商品种类数',
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                    'default' => 0,
                    'filterdefault' => true,
                    'panel_id' => 'delivery_finder_top',
                ),
                'itemNum' =>
                array(
                    'type' => 'skunum',
                    'filtertype' => 'normal',
                    'required' => true,
                    'label' => '商品总数量',
                    'comment' => '商品种类数',
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                    'default' => 0,
                    'filterdefault' => true,
                    'panel_id' => 'delivery_finder_top',
                ),
                'delivery_group' =>
                array (
                    'type' => $delivery_group,
                    'label' => '发货单分组',
                    'comment' => '发货单分组',
                    'editable' => false,
                    'width' =>75,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'in_list' => true,
                    'panel_id' => 'delivery_finder_top',
                ),
                'branch_id' =>
                    array (
                    'type' => 'table:branch@ome',
                    'editable' => false,
                    'label' => '仓库',
                    'width' => 110,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'in_list' => true,
                    'panel_id' => $branchPanel,
                ),
                'shop_id' =>
                array (
                  'type' => 'table:shop@ome',
                  'label' => '来源店铺',
                  'width' => 75,
                  'editable' => false,
                  'in_list' => true,
                  'filtertype' => 'normal',
                  'filterdefault' => true,
                  'panel_id' => 'delivery_finder_top',
                ),
                'custom_mark' =>
                    array (
                            'type' => 'custom_mark',
                            'label' => '买家留言',
                            'width' => 500,
                            'editable' => false,
                            'in_list' => true,
                            'filtertype' => 'normal',
                            'filterdefault' => true,
                            'default_in_list'=>true
                    ),  
                'mark_text' =>
                    array (
                            'type' => 'mark_text',
                            'label' => '客服备注',
                            'width' => 500,
                            'editable' => false,
                            'in_list' => true,
                            'filtertype' => 'normal',
                            'filterdefault' => true,
                            'default_in_list'=>true
                    ),
                    'net_weight' =>
                    array(
                            'type' => 'money',
                            'label' => '商品净重',
                            'comment' => '商品净重',
                            'width' => 50,
                            'editable' => false,
                            'in_list' => true,
                            'filtertype' => 'normal',
                            'filterdefault' => true,
                            'default_in_list' => true
                    ),
            )
        );
        if(($_GET['ctl'] == 'admin_receipts_outer')||($_GET['ctl'] == 'admin_receipts_print' && $_GET['status'] == '') || ($_GET['ctl'] == 'admin_delivery' && $_GET['act'] == 'index')){
            $db['delivery']['columns']['status'] = array(
                    'type' =>
                      array(
                        'succ' => '已发货',
                        'failed' => '发货失败',
                        'cancel' => '已取消',
                        'progress' => '等待配货',
                        'timeout' => '超时',
                        'ready' => '等待配货',
                        'stop' => '暂停',
                        'back' => '打回',
                    ),
                    'label' => '发货状态',
                    'comment' => '发货状态',
                    'editable' => false,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'in_list' => true,
                );
        }
        $sku = kernel::single('base_component_request')->get_get('sku');
        if (in_array($sku,array('single'))) {
            # 去除商品种类、商品总数量、发货单分组的panel_id
            unset($db['delivery']['columns']['skuNum']['panel_id']);
        }
        return $db;
    }
}
