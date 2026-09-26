<?php
class sales_finder_sales{
    var $column_detail = 'detail';
    
    public $orderTypes = array(
        'normal' => '订单',
        'sale' => '销售单',
        'presale' => '预售订单',
        'vopczc' => '唯品会仓中仓订单',
        'platform' => '平台发货',
        'offline'  => '线下订单',
        'gift'  => '礼品卡订单',
        'integral'  => '积分订单',
        'exchange'  => '换货订单',
        'brush' => '特殊订单',
        'jitxsc' => '唯品会省仓',
        'zerobuy' => '0元购',
        'bufa' => '补发订单',
    );
    
    var $addon_cols = 'archive,order_id,order_type,order_source';
    
    function detail_edit($id){

        //[发货配置]是否启动拆单
        $orderSplitLib    = kernel::single('ome_order_split');
        $split_seting     = $orderSplitLib->get_delivery_seting();

        $render = app::get('sales')->render();
        $Oorders = app::get('ome')->model('orders');

        $oItem = kernel::single("ome_mdl_sales_items");
        $sales = app::get('sales')->model('sales')->getList('*',array('sale_id'=>$id));
        $dataitems = $oItem->getList('*',array('sale_id'=>$id));
        $total_nums = $oItem->getList('sum(nums) as total_nums',array('sale_id'=>$id));
        $archive = $sales[0]['archive'];

        $order_id = $sales[0]['order_id'];
        if ($archive == '1') {
            $archive_delObj = kernel::single('archive_interface_delivery');
            $archive_ordObj = kernel::single('archive_interface_orders');
            $sql2 = 'select ODB.logi_no from sdb_archive_delivery_bill as ODB left join sdb_archive_delivery_order as ODO on ODB.delivery_id = ODO.delivery_id left join sdb_ome_sales as OS on OS.order_id= ODO.order_id where OS.sale_id= '.$sales[0]['sale_id'];
            $delivery_bill = $archive_delObj->getDeliveryByorderId($order_id);

            $orders =$archive_ordObj->getOrders($order_id,'order_bn');
            $orders[0] = $orders;
            $delivery = $archive_delObj->getDelivery(array('delivery_id'=>$sales[0]['delivery_id']),"delivery_id,shop_type,delivery_bn,ship_name,ship_area,ship_province,ship_city,ship_district,ship_addr,ship_zip,ship_tel,ship_mobile,ship_email");
        }else{
            $sql2 = 'select ODB.logi_no from sdb_ome_delivery_bill as ODB left join sdb_ome_delivery_order as ODO on ODB.delivery_id = ODO.delivery_id left join sdb_ome_sales as OS on OS.order_id= ODO.order_id where OS.sale_id= '.$sales[0]['sale_id'];
            $delivery_bill = kernel::database()->select($sql2);
            $orders = $Oorders->getList('order_bn,is_service_order,service_price',array('order_id'=>$sales[0]['order_id']));
            $delivery = app::get('ome')->model('delivery')->getList('delivery_id,shop_type, delivery_bn,ship_name,ship_area,ship_province,ship_city,ship_district,ship_addr,ship_zip,ship_tel,ship_mobile,ship_email',array('delivery_id'=>$sales[0]['delivery_id']),0,1);
        }
        $sql3 = "select name from sdb_ome_shop where shop_id='".$sales[0]['shop_id']."'";
        $shopname = kernel::database()->select($sql3);

        $sql4 = "select uname from sdb_ome_members where member_id='".$sales[0]['member_id']."'";
        $uname = kernel::database()->select($sql4);

        //显示关联的销售物料编码,促销销售物料显示关联的基础物料信息
        if($dataitems)
        {
            $salesBasicMaterialObj    = app::get('material')->model('sales_basic_material');
            $orderItemObj             = app::get('ome')->model('order_items');
            $orderObjects             = app::get('ome')->model('order_objects');

            $material_sales_type      = array('product'=>'普通', 'pkg'=>'组合', 'gift'=>'赠品', 'lkb'=>'福袋', 'pko'=>'多选一','giftpackage'=>'礼盒');
            foreach ($dataitems as $key => $val)
            {
                if(empty($val['product_id']))
                {
                    #促销销售物料信息
                    $filter     = array('order_id'=>$sales[0]['order_id'], 'bn'=>$val['bn'], 'obj_type'=>'pkg');
                    $getItem    = $orderObjects->dump($filter, 'bn,obj_id, goods_id, obj_type');
                    $val['obj_type']    = $getItem['obj_type'];
                    $val['type_name']   = $material_sales_type[$val['obj_type']];
                    $val['sales_material_bn']   = $getItem['bn'];
                    #订单商品详细信息
                    $filter                = array('obj_id'=>$getItem['obj_id']);
                    $val['order_items']    = $orderItemObj->getList('item_id, product_id, bn, name, price, pmt_price, sale_price, amount, nums, `delete`', $filter);

                    #优惠金额平摊
                    $items_count    =  count($val['order_items']);
                    if($val['apportion_pmt'])
                    {
                        #关联基础物料贡献占比
                        $salesBasicMList = array();
                        $tempData        = $salesBasicMaterialObj->getList('bm_id, rate', array('sm_id'=>$getItem['goods_id']));
                        foreach ($tempData as $sKey => $sVal)
                        {
                            $salesBasicMList[$sVal['bm_id']]    = $sVal['rate'];
                        }

                        #平摊优惠
                        $bm_rate          = 0;
                        $apportion_pmt    = 0;
                        $pmt_price        = 0;
                        $item_i           = 0;

                        foreach ($val['order_items'] as $iKey => $iVal)
                        {
                            $item_i++;

                            if($item_i == $items_count)
                            {
                                $val['order_items'][$iKey]['apportion_pmt']    = $val['apportion_pmt'] - $apportion_pmt;
                                //$val['order_items'][$iKey]['pmt_price']        = $val['pmt_price'] - $pmt_price;

                                //PKG销售金额 = 销售金额 - 平摊优惠
                                $val['order_items'][$iKey]['amount']    = $val['order_items'][$iKey]['amount'] - $val['order_items'][$iKey]['apportion_pmt'];
                            }
                            else
                            {
                                $bm_rate    = $salesBasicMList[$iVal['product_id']] / 100;

                                $val['order_items'][$iKey]['apportion_pmt']    = round(($val['apportion_pmt'] * $bm_rate), 2);
                                //$val['order_items'][$iKey]['pmt_price']        = round(($val['pmt_price'] * $bm_rate), 2);

                                //PKG销售金额 = 销售金额 - 平摊优惠
                                $val['order_items'][$iKey]['amount']    = $val['order_items'][$iKey]['amount'] - $val['order_items'][$iKey]['apportion_pmt'];

                                $apportion_pmt  += $val['order_items'][$iKey]['apportion_pmt'];
                                //$pmt_price      += $val['order_items'][$iKey]['pmt_price'];
                            }
                        }
                    }
                }
                else
                {
                    $filter     = array('order_id'=>$sales[0]['order_id'], 'product_id'=>$val['product_id'], 'item_type'=>array('product', 'gift'));
                    $getItem    = $orderItemObj->dump($filter, 'item_id, item_type');
                    //$val['obj_type']    = $getItem['item_type'];
                    $val['type_name']   = $val['obj_type'] ? $material_sales_type[$val['obj_type']] : $getItem['item_type'];
                }
                $addon                  = $val['addon'] ? json_decode($val['addon'], true) : [];
                $val['shop_goods_id']   = isset($addon['shop_goods_id']) ? $addon['shop_goods_id'] : '';
                $val['shop_product_id'] = isset($addon['shop_product_id']) ? $addon['shop_product_id'] : '';
                $props = app::get('ome')->model('sales_items_props')->getList('props_col,props_value', ['item_detail_id'=>$val['item_id']]);
                $val['props'] = array_column($props, 'props_value', 'props_col');
                $dataitems[$key]    = $val;
            }
        }
        
        //[拆单]获取订单对应多个发货单
        if($split_seting){
            $sql    = "SELECT dord.delivery_id, d.delivery_bn, d.logi_no FROM sdb_ome_delivery_order AS dord
                        LEFT JOIN sdb_ome_delivery AS d ON(dord.delivery_id=d.delivery_id)
                        WHERE dord.order_id='".$sales[0]['order_id']."' AND (d.parent_id=0 OR d.is_bind='true') AND d.disabled='false'
                        AND d.status NOT IN('failed','cancel','back','return_back')";
            $delivery_list    = kernel::database()->select($sql);

            #获取订单对应所有发货单
            if($delivery_list && count($delivery_list) > 1)
            {
                $delivery[0]['delivery_bn']    = '';
                $sales[0]['logi_no']           = '';

                foreach($delivery_list as $key => $val)
                {
                    $delivery[0]['delivery_bn']    .= ' | '.$val['delivery_bn'];
                    $sales[0]['logi_no']           .= ' | '.$val['logi_no'];
                }

                $delivery[0]['delivery_bn']    = substr($delivery[0]['delivery_bn'], 2);
                $sales[0]['logi_no']           = substr($sales[0]['logi_no'], 2);
            }
        }

        if($delivery_bill){
            foreach ($delivery_bill as $value){
                $sales[0]['logi_no'] .=' | '.$value['logi_no'];
            }
        }
        
        $sales[0]['order_bn'] = $orders[0]['order_bn'];
        $sales[0]['sale_time'] = date("Y-m-d H:i:s",$sales[0]['sale_time']);
        $sales[0]['delivery_bn'] = $delivery[0]['delivery_bn'];
        $sales[0]['shopname']  = $shopname[0]['name'];
        $sales[0]['uname']   = $uname[0]['uname'];
        $sales[0]['nums']   = $total_nums[0]['total_nums'];
        $sales[0]['is_service_order']   = $orders[0]['is_service_order'];
        $sales[0]['service_price']   = $orders[0]['service_price'];
        // 代销人信息
        $selling_agent_info = app::get('ome')->model('order_selling_agent')->dump($sales[0]['selling_agent_id']);

        $render->pagedata['selling_agent_info'] = $selling_agent_info;
        $propsTitle = app::get('desktop')->model('customcols')->getList('*', ['tbl_name'=>'sdb_ome_sales_items']);
        $render->pagedata['propsTitle'] = $propsTitle;
                // 判断是否加密
        $delivery[0]['is_encrypt'] = kernel::single('ome_security_router',$delivery[0]['shop_type'])->show_encrypt($delivery[0], 'delivery');
        $render->pagedata['deliveryinfo'] = $delivery[0];
        $render->pagedata['dataitems'] = $dataitems;
        //销售价权限判断
        $showSalePrice = true;
        if (!kernel::single('desktop_user')->has_permission('sale_price')) {
            $showSalePrice = false;
        }
        $render->pagedata['show_sale_price'] = $showSalePrice;
        //原价权限判断
        $showOriginalPrice = true;
        if (!kernel::single('desktop_user')->has_permission('original_price')) {
            $showOriginalPrice = false;
        }
        $render->pagedata['show_original_price'] = $showOriginalPrice;
        $render->pagedata['sales'] = $sales[0];

        // 唯一码明细：机器中心发货流水 + goldentra 主档（无 goldentra 时跳过）
        $sum_price = 0;
        $sum_sale_price = 0;
        $sale_serial_number_items = array();
        
        $render->pagedata['sale_serial_number_items'] = $sale_serial_number_items;
        $render->pagedata['sum_price'] = $sum_price;
        $render->pagedata['sum_sale_price'] = $sum_sale_price;

        $render->display('detail.html');
    }
    
    var $column_order_id='订单号';
    var $column_order_id_width = '180';
    function column_order_id($row, $list)
    {
        return $this->_getOrder($row[$this->col_prefix . 'order_id'], $list)['order_bn'];
    }

    /**
     * 查询订单信息
     *
     * @param Type $var Description
     * @return type
     * @throws conditon
     **/
    private function _getOrder($order_id, $list)
    {
        static $orders;

        if (isset($orders)) {
            return $orders[$order_id] ? : [];
        }

        $filter = [
            'order_id' => array_column($list, $this->col_prefix . 'order_id'),
        ];

        $o1 = app::get('ome')->model('orders')->getList('order_id, order_bn', $filter);
        $o2 = app::get('archive')->model('orders')->getList('order_id, order_bn', $filter);

        $orders = array_merge((array)$o1, (array)$o2);
        $orders = array_column($orders,null, 'order_id');

        return $orders[$order_id] ? : [];
    }
    
    var $column_order_type ='订单类型';
    var $column_order_type_width = 110;
    function column_order_type($row){
        $order_type = $row[$this->col_prefix . 'order_type'];
        if(empty($order_type)){
            return '';
        }
        
        return $this->orderTypes[$order_type];
    }
    
    var $column_order_source = '来源渠道';
    var $column_order_source_width = 110;
    function column_order_source($row)
    {
        $order_source = $row[$this->col_prefix . 'order_source'];
        if (empty($order_source)) {
            return '';
        }
        $tmp_order_source   = ome_order_func::get_order_source();
        $order_source_title = isset($tmp_order_source[$order_source]) ? $tmp_order_source[$order_source] : ($order_source ?: $tmp_order_source['direct']);
        return $order_source_title;
    }

    var $column_customer_add='商家备注';
    var $column_customer_add_width = "100";
    function column_customer_add($row, $list){
        static $orderList;

        if(!isset($orderList)){
            $orderList = [];
            $orderIds = array_column($list, 'order_id');
            $orderMdl = app::get('ome')->model('orders');
            $orderList = $orderMdl->getList('order_id,mark_text', ['order_id' => $orderIds]);
            $orderList = array_column($orderList, 'mark_text', 'order_id');

            foreach($orderList as $order_id => $mark_text){
                $orderList[$order_id] = kernel::single('ome_func')->format_memo($mark_text);
            }
        }
        if(empty($orderList[$row['order_id']])){
            return '';
        }
        $html = '';
        $mark_text = $orderList[$row['order_id']];
        foreach ((array)$mark_text as $k=>$v){
            $html .= $v['op_content'].' '.$v['op_time'].' by '.$v['op_name']."<br />";
        }
        $html = strip_tags(htmlspecialchars($html));
        return "<div onmouseover='bindFinderColTip(event)' rel='{$html}'>".strip_tags(htmlspecialchars($mark_text[$k]['op_content']))."<div>";
    }
}
