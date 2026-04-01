<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
class sales_mdl_sales extends dbeav_model{

    //是否有导出配置
    var $has_export_cnf = true;

    //所用户信息
    static $__USERS = null;

    var $export_name = '销售单';

    public $filter_use_like = true;

    public $appendCols = 'order_id';

    function __construct($app){
        parent::__construct(app::get('ome'));
    }

    public function table_name($real=false){
        $table_name = "sales";
        if($real){
            return kernel::database()->prefix.$this->app->app_id.'_'.$table_name;
        }else{
            return $table_name;
        }
    }

    function searchOptions(){
        $parentOptions = parent::searchOptions();
        $childOptions = array(
            'order_bn'=>app::get('base')->_('订单号'),
            'bn'=>app::get('base')->_('基础物料编码'),
        );
        return $Options = array_merge($childOptions,$parentOptions);
     }

     function getList($cols='*', $filter=array(), $offset=0, $limit=-1, $orderby=null){

        return parent::getList($cols, $filter, $offset, $limit, $orderby);

     }

     function _filter($filter,$tableAlias=null,$baseWhere=null){
        @ini_set('memory_limit','512M');
        $where = '1';
         //订单号查询
        if (isset($filter['order_bn'])){
            $orderObj = $this->app->model("orders");
            $rows = $orderObj->getList('order_id',array('order_bn|head'=>$filter['order_bn']));
            $orderId[] = -1;
            foreach($rows as $row){
                $orderId[] = $row['order_id'];
            }
            $where .= '  AND order_id IN ('.implode(',', $orderId).')';
            unset($filter['order_bn']);

        }


        //基础物料编码查询
        if(isset($filter['bn'])){

            $sql = 'SELECT sale_id FROM sdb_ome_sales_items WHERE bn like \''.$filter['bn'].'\'';
            $rows = $this->db->select($sql);
            $saleId[] = 0;
            foreach($rows as $row){
                $saleId[] = $row['sale_id'];
            }

            $where .= ' AND sale_id IN ('. implode(',', $saleId).')';
            unset($filter['bn']);

        }

        //货品名称
        if(isset($filter['product_name'])){
            $sql = 'SELECT material_bn AS bn FROM sdb_material_basic_material WHERE material_name like \''.$filter['product_name'].'\'';
            $name = $this->db->select($sql);
            $sql2 = 'SELECT sale_id FROM sdb_ome_sales_items WHERE bn like \''.$name[0]['bn'].'\'';
            $rows = $this->db->select($sql2);
            $saleId[] = 0;
            foreach($rows as $row){
                $saleId[] = $row['sale_id'];
            }

            $where .= ' AND sale_id IN ('. implode(',', $saleId).')';
            unset($filter['product_name']);
        }

        if(isset($filter['ship_area'])){
            $sql = 'SELECT delivery_id FROM sdb_ome_delivery WHERE ship_area = "'.$filter['ship_area'].'"';
            $rows = $this->db->select($sql);
            $deliveryId[] = 0;
            foreach($rows as $row){
                $deliveryId[] = $row['delivery_id'];
            }

            $where .= ' AND delivery_id IN ('. implode(',', $deliveryId).')';
            unset($filter['ship_area']);
        }
        if (isset($filter['original_bn'])) {
            $sql = 'SELECT delivery_id FROM sdb_ome_delivery WHERE delivery_bn = "'.$filter['original_bn'].'"';
            $rows = $this->db->select($sql);
            $deliveryId[] = 0;
            foreach($rows as $row){
                $deliveryId[] = $row['delivery_id'];
            }

            $where .= ' AND delivery_id IN ('. implode(',', $deliveryId).')';
            unset($filter['original_bn']);
        }

        return parent::_filter($filter,$tableAlias,$baseWhere)." AND ".$where;
    }

    function exportTemplate($filter){
        foreach ($this->io_title($filter) as $v){
            $title[] = kernel::single('base_charset')->utf2local($v);
        }
        return $title;
    }

     function io_title( $filter=null,$ioType='csv' ){
            switch( $ioType ){
            case 'csv':
            default:
                $this->oSchema['csv']['sales'] = array(
                    '*:店铺名称'       => '',
                    '*:仓库名称'       => '',
                    '*:销售单号'       => '',
                    '*:订单号'         => '',
                    '*:发货单号'       => '',
                    '*:用户名称'       => '',
                    '*:支付方式'       => '',
                    '*:销售金额'       => '',
                    '*:优惠金额'       => '',
                    '*:商品总额'       => '',
                    '*:物流单号'       => '',
                    '*:预收物流费'     => '',
                    '*:预估物流费'     => '',
                    '*:配送费用'       => '',
                    '*:附加费'         => '',
                    '*:预存款'         => '',
                    '*:是否开发票'     => '',
                    '*:订单审核人'     => '',
                    '*:销售时间'       => '',
                    '*:下单时间'       => '',
                    '*:付款时间'       => '',
                    '*:订单审核时间'   => '',
                    '*:发货时间'       => '',
                    '*:收货人姓名'     => '',
                    '*:收货人地区'     => '',
                    '*:收货人地址'     => '',
                    '*:收货人邮编'     => '',
                    '*:收货人固定电话' => '',
                    '*:收货人Email'    => '',
                    '*:收货人手机'     => '',
                );
                $this->oSchema['csv']['sales_items'] = array(
                    '*:销售单号'   => '',
                    '*:订单号'     => '',
                    '*:基础物料编码'       => '',
                    '*:商品名称'   => '',
                    '*:基础物料规格'   => '',
                    '*:吊牌价'   => '',
                    '*:数量'   => '',
                    '*:基础物料优惠'   => '',
                    '*:销售总价'   => '',
                    '*:平摊优惠'   => '',
                    '*:销售金额'   => '',
                );
                break;
        }
        $this->ioTitle[$ioType]['sales'] = array_keys( $this->oSchema[$ioType]['sales'] );
        $this->ioTitle[$ioType]['sales_items'] = array_keys( $this->oSchema[$ioType]['sales_items'] );
        return $this->ioTitle[$ioType][$filter];

     }
     //csv导出
     function fgetlist_csv( &$data,$filter,$offset,$exportType = 1 ){
       //print_r($data);//$filter是选中sales表中的记录id
       //$data是sales数据表
       //$offset 是偏移值
       //

         # [发货配置]是否启动拆单
         $orderSplitLib    = kernel::single('ome_order_split');
         $split_seting     = $orderSplitLib->get_delivery_seting();

         if( !$data['title']['sales'] ){
             $title = array();
             foreach( $this->io_title('sales') as $k => $v ){
                 $title[] = $this->charset->utf2local($v);
                //$title[] = $v;
             }
             $data['title']['sales'] = '"'.implode('","',$title).'"';
         }
         if( !$data['title']['sales_items'] ){
             $title = array();
              foreach( $this->io_title('sales_items') as $k => $v ){
                  $title[] = $this->charset->utf2local($v);
                  //$title[] = $v;
             }
             $data['title']['sales_items'] = '"'.implode('","',$title).'"';
         }

         if( !$data['title']['sales_items'] ){
             $title = array();
              foreach( $this->io_title('sales_items') as $k => $v ){
                  $title[] = $this->charset->utf2local($v);
                  //$title[] = $v;
             }
             $data['title']['sales_items'] = '"'.implode('","',$title).'"';
         }

         $limit = 100;
         //if( $filter[''] )获取的sales的id
         //$list=$this->getList('id',$filter,$offset*$limit,$limit);获取的sales的列表

        if(!$list = $this->getList('*',$filter,$offset*$limit,$limit)){
            return false;
        }

        //优化代码
        $oShop = app::get('ome')->model('shop');
        $oMembers = app::get('ome')->model('members');
        $oPam = app::get('pam')->model('account');
        $oBranch = app::get('ome')->model('branch');
        $oDelivery = app::get('ome')->model('delivery');
        $oOrder = app::get('ome')->model('orders');
        $archive_ordobj = kernel::single('archive_interface_orders');
        $archive_delobj = kernel::single('archive_interface_delivery');
        $oDeliveryOrder = app::get('ome')->model('delivery_order');
        $oSalesItems = app::get('ome')->model('sales_items');

        // 所有的店铺信息
        $rs = $oShop->getList('shop_id,shop_bn,name');
        foreach($rs as $v) {
            $shops[$v['shop_id']] = $v;
        }

        // 所有的仓库
        $rs = $oBranch->getList('branch_id,branch_bn,name');
        foreach($rs as $v) {
            $branchs[$v['branch_id']] = $v;
        }

        foreach($list as $v) {
            $order_ids[] = $v['order_id'];
            $member_ids[] = $v['member_id'];
            $iostock_bns[] = $v['iostock_bn'];
            $sale_ids[] = $v['sale_id'];
            $delivery_ids[] = $v['delivery_id'];
            $order_check_ids[] = $v['order_check_id'];
        }

        // 所有的会员
        $rs = $oMembers->getList('member_id,uname',array('member_id'=>$member_ids));
        foreach($rs as $v) {
            $members[$v['member_id']] = $v;
        }


        // 所有的发货单信息
        $rs = $oDelivery->getList('delivery_id,delivery_bn,logi_name,logi_no,ship_name,ship_addr,ship_zip,ship_tel,ship_email,ship_mobile,ship_area',array('delivery_id'=>$delivery_ids));
        foreach($rs as $v) {
            $deliverys[$v['delivery_id']] = $v;
        }
        unset($rs);
        $rs = $archive_delobj->getDelivery_list(array('delivery_id'=>$delivery_ids),'delivery_id,delivery_bn,logi_name,logi_no,ship_name,ship_addr,ship_zip,ship_tel,ship_email,ship_mobile,ship_area');

        foreach ($rs as $v ) {
            $deliverys[$v['delivery_id']] = $v;
        }
        unset($rs);
        // 所有的订单信息
        $rs = $oOrder->getList('order_id,order_bn',array('order_id'=>$order_ids));
        foreach($rs as $v) {
            $orders[$v['order_id']] = $v;
        }
        unset($rs);


        $rs = $archive_ordobj->getOrder_list(array('order_id'=>$order_ids),'order_id,order_bn');
        foreach ( $rs as $v ) {
            $orders[$v['order_id']] = $v;
        }
        unset($rs);
        // 所有的操作员信息
        $rs = $oPam->getList('login_name,account_id',array('account_id'=>$order_check_ids));
        foreach($rs as $v) {
            $check_names[$v['account_id']] = $v;
        }

        //所有的子销售数据
        $rs = $oSalesItems->getList('*',array('sale_id'=>$sale_ids));

        foreach($rs as $v) {
            $sales_items[$v['sale_id']][] = $v;
        }

         foreach( $list as $aFilter ){
                $aOrder = $aFilter;

                $shop_name = $shops[$aOrder['shop_id']]['name'];

                $branch_name = $branchs[$aOrder['branch_id']];

                $member_uname = $members[$aOrder['member_id']]['uname'];

                $delivery_bn = $deliverys[$aOrder['delivery_id']]['delivery_bn'];
                $ship_name = $deliverys[$aOrder['delivery_id']]['ship_name'];
                $ship_addr = $deliverys[$aOrder['delivery_id']]['ship_addr'];
                $ship_zip = $deliverys[$aOrder['delivery_id']]['ship_zip'];
                $ship_tel = $deliverys[$aOrder['delivery_id']]['ship_tel'];
                $ship_email = $deliverys[$aOrder['delivery_id']]['ship_email'];
                $ship_mobile = $deliverys[$aOrder['delivery_id']]['ship_mobile'];

                $rd = explode(':', $deliverys[$aOrder['delivery_id']]['ship_area']);
                if($rd[1]){
                  $ship_area = str_replace('/', '-', $rd[1]);
                }

                /*------------------------------------------------------ */
                //-- [拆单]获取订单对应多个发货单
                /*------------------------------------------------------ */
                if($split_seting)
                {
                    $dly_sql    = "SELECT dord.delivery_id, d.delivery_bn, d.logi_no FROM sdb_ome_delivery_order AS dord
                                LEFT JOIN sdb_ome_delivery AS d ON(dord.delivery_id=d.delivery_id)
                                WHERE dord.order_id='".$aOrder['order_id']."' AND (d.parent_id=0 OR d.is_bind='true') AND d.disabled='false'
                                AND d.status NOT IN('failed','cancel','back','return_back')";
                    $delivery_list    = kernel::database()->select($dly_sql);

                    #获取订单对应所有发货单
                    if($delivery_list && count($delivery_list) > 1)
                    {
                        $delivery_bn    = '';
                        $deliverys[$aOrder['delivery_id']]['logi_no']    = '';

                        foreach($delivery_list as $key_i => $dly_val)
                        {
                            $delivery_bn            .= ' | '.$dly_val['delivery_bn'];
                            $deliverys[$aOrder['delivery_id']]['logi_no']    .= ' | '.$dly_val['logi_no'];
                        }

                        $delivery_bn        = substr($delivery_bn, 2);
                        $deliverys[$aOrder['delivery_id']]['logi_no']    = substr($deliverys[$aOrder['delivery_id']]['logi_no'], 2);
                    }
                }

                $order_bn = $orders[$aOrder['order_id']]['order_bn'];

                $order_check_id = $check_names[$aOrder['order_check_id']]['login_name'];

                if($aOrder['sale_time']){
                    $sale_time = date("Y-m-d/H:i:s",$aOrder['sale_time']);
                }else{
                    $sale_time = '';
                }

                if($aOrder['order_check_time']){
                    $order_check_time = date("Y-m-d/H:i:s",$aOrder['order_check_time']);
                }else{
                    $order_check_time = '';
                }

                if($aOrder['order_create_time']){
                    $order_create_time = date("Y-m-d/H:i:s",$aOrder['order_create_time']);
                }else{
                    $order_create_time = '';
                }

                if($aOrder['paytime']){
                    $paytime = date("Y-m-d/H:i:s",$aOrder['paytime']);
                }else{
                    $paytime = '';
                }

                if($aOrder['ship_time']){
                    $ship_time = date("Y-m-d/H:i:s",$aOrder['ship_time']);
                }else{
                    $ship_time = '';
                }
                $aOrderRow = array();

                $aOrderRow['*:店铺名称']       = $shop_name;
                $aOrderRow['*:仓库名称']       = $branch_name['name'];
                $aOrderRow['*:销售单号']       = "=\"\"".$aOrder['sale_bn']."\"\"";
                $aOrderRow['*:订单号']         = "=\"\"".$order_bn."\"\"";
                $aOrderRow['*:发货单号']       = "=\"\"".$delivery_bn."\"\"";
                $aOrderRow['*:用户名称']       = $member_uname."\t";
                $aOrderRow['*:支付方式']       = $aOrder['payment'];
                $aOrderRow['*:销售金额']       = $aOrder['sale_amount'];
                $aOrderRow['*:优惠金额']       = $aOrder['discount'];
                $aOrderRow['*:商品总额']       = $aOrder['total_amount'];
                $aOrderRow['*:物流单号']       = $deliverys[$aOrder['delivery_id']]['logi_no']."\t";
                $aOrderRow['*:预收物流费']     = $aOrder['delivery_cost'];
                $aOrderRow['*:预估物流费']     = $aOrder['delivery_cost_actual'];
                $aOrderRow['*:配送费用']       = $aOrder['cost_freight'];
                $aOrderRow['*:附加费']         = $aOrder['additional_costs'];
                $aOrderRow['*:预存款']         = $aOrder['deposit'];
                $aOrderRow['*:是否开发票']     = $aOrder['is_tax'] == 'true'?'是':'否';
                $aOrderRow['*:订单审核人']     = $order_check_id;
                $aOrderRow['*:销售时间']       = $sale_time;
                $aOrderRow['*:下单时间']       = $order_create_time;
                $aOrderRow['*:付款时间']       = $paytime;
                $aOrderRow['*:订单审核时间']   = $order_check_time;
                $aOrderRow['*:发货时间']       = $ship_time;
                $aOrderRow['*:收货人姓名']     = $ship_name;
                $aOrderRow['*:收货人地区']     = $ship_area;
                $aOrderRow['*:收货人地址']     = $ship_addr;
                $aOrderRow['*:收货人邮编']     = $ship_zip;
                $aOrderRow['*:收货人固定电话'] = $ship_tel;
                $aOrderRow['*:收货人Email']    = $ship_email;
                $aOrderRow['*:收货人手机']     = $ship_mobile;

                $data['content']['sales'][]  = $this->charset->utf2local('"'.implode( '","', $aOrderRow ).'"');

                $objects = $sales_items[$aOrder['sale_id']];
                if ($objects){
                     foreach ($objects as $obj){
                        $orderObjRow = array();
                        $orderObjRow['*:销售单号']   ="=\"\"".$aOrder['sale_bn']."\"\"";
                        $orderObjRow['*:订单号']     = "=\"\"".$order_bn."\"\"";
                        $orderObjRow['*:基础物料编码']       = $obj['bn'];
                        $orderObjRow['*:商品名称']   = str_replace([',', "\n"], '', $obj['name']);
                        $orderObjRow['*:基础物料规格']   = str_replace([',', "\n"], '', $obj['spec_name']);
                        $orderObjRow['*:吊牌价']   = $obj['price'];
                        $orderObjRow['*:数量']   = $obj['nums'];
                        $orderObjRow['*:基础物料优惠']   = $obj['pmt_price'];
                        $orderObjRow['*:数量']   = $obj['nums'];
                        $orderObjRow['*:销售总价']   = $obj['sale_price'];
                        $orderObjRow['*:平摊优惠']   = $obj['apportion_pmt'];
                        $orderObjRow['*:销售金额']   = $obj['sales_amount'];
                        $data['content']['sales_items'][] = $this->charset->utf2local('"'.implode( '","', $orderObjRow ).'"');
                     }
                }
        }

        $data['name'] = 'sales'.date("YmdHis");
        return true;
    }

    function export_csv($data,$exportType = 1 ){
        $output = array();
         foreach( $data['title'] as $k => $val ){
                $output[] = $val."\n".implode("\n",(array)$data['content'][$k]);
            }
        echo implode("\n",$output);
    }

    public function modifier_order_check_id($row){

        switch($row){
            case 0:
                $ret = '无';
                break;
            case 16777215:
                $ret = '系统';
                break;
            default:
                $ret = $this->_getUserName($row);
                break;
        }

        return $ret;

    }

    private function _getUserName($uid) {
        if (self::$__USERS === null) {
            self::$__USERS = array();
            $rows = app::get('desktop')->model('users')->getList('*');
            foreach($rows as $row) {
                self::$__USERS[$row['user_id']] = $row['name'];
            }
        }

        if (isset(self::$__USERS[$uid])) {
            return self::$__USERS[$uid];
        } else {
            return '无';
        }
    }

    /**
     * 获得日志类型(non-PHPdoc)
     * @see dbeav_model::getLogType()
     */
    public function getLogType($logParams) {
        $type = $logParams['type'];
        $logType = 'none';
        if ($type == 'export') {
            $logType = $this->exportLogType($logParams);
        }
        elseif ($type == 'import') {
            $logType = $this->importLogType($logParams);
        }
        return $logType;
    }
    /**
     * 导出日志类型
     * @param Array $logParams 日志参数
     */
    public function exportLogType($logParams) {
        $params = $logParams['params'];
        $type = 'bill';
        if ($logParams['app'] == 'sales' && $logParams['ctl'] == 'admin_sales') {
            $type .= '_salesBill_sales';
        }
        $type .= '_export';
        return $type;
    }
    /**
     * 导入操作日志类型
     * @param Array $logParams 日志参数
     */
    public function importLogType($logParams) {
        $params = $logParams['params'];
        $type = 'bill';
        if ($logParams['app'] == 'sales' && $logParams['ctl'] == 'admin_sales') {
            $type .= '_salesBill_sales';
        }
        $type .= '_import';
        return $type;
    }
    public function getexportdetail($fields,$filter,$offset=0,$limit=1,$has_title=false)
    {
        $salesList    = array();

        $archive_ordobj = kernel::single('archive_interface_orders');
        $sales_arr = $this->getList('sale_bn,order_id,sale_id,selling_agent_id', array('sale_id' => $filter['sale_id']), 0, -1);
        foreach ($sales_arr as $sale) {
            $sales_bn[$sale['sale_id']] = $sale['sale_bn'];
            $sales_order_ids[$sale['order_id']] = $sale['sale_id'];
            $order_ids[] = $sale['order_id'];

            $salesList[$sale['sale_id']]    = $sale;
        }

        // 所有的订单信息
        $ordersObj = app::get('ome')->model('orders');
        $orders_arr = $ordersObj->getList('order_id,order_bn',array('order_id'=>$order_ids));
        foreach($orders_arr as $order) {
            if(isset($sales_order_ids[$order['order_id']])){
                $orders_bn[$sales_order_ids[$order['order_id']]] = $order['order_bn'];
            }
        }
        $orders_arr = $archive_ordobj->getOrder_list(array('order_id'=>$order_ids),'order_id,order_bn');

        foreach($orders_arr as $order) {
            if(isset($sales_order_ids[$order['order_id']])){
                $orders_bn[$sales_order_ids[$order['order_id']]] = $order['order_bn'];
            }
        }
        $saleItemsObj = app::get('ome')->model('sales_items');
        $sale_items_arr = $saleItemsObj->getList('*',array('sale_id'=>$filter['sale_id']));


        //格式化[防止促销销售物料没有显示关联的基础物料]
        if($sale_items_arr)
        {
            $salesBasicMaterialObj    = app::get('material')->model('sales_basic_material');
            $orderItemObj             = app::get('ome')->model('order_items');
            $orderObjects             = app::get('ome')->model('order_objects');

            $material_sales_type      = array('product'=>'普通', 'pkg'=>'促销', 'gift'=>'赠品','lkb'=>'福袋', 'pko'=>'多选一');

            $dataList          = $sale_items_arr;
            $sale_items_arr    = array();

            //代销人对象
            $agent_obj = app::get('ome')->model('order_selling_agent');

            foreach ($dataList as $key => $val)
            {
                $temp_order_id    = $salesList[$val['sale_id']]['order_id'];

                if(empty($val['product_id']))
                {
                    #促销销售物料
                    $filter     = array('order_id'=>$temp_order_id, 'bn'=>$val['bn'], 'obj_type'=>'pkg');
                    $getItem    = $orderObjects->dump($filter, 'obj_id, goods_id, bn, name');
                    if(empty($getItem))
                    {
                        continue;
                    }

                    #关联基础物料
                    $filter    = array('obj_id'=>$getItem['obj_id']);
                    $getList   = $orderItemObj->getList('item_id, product_id, bn, name, price, pmt_price, sale_price, amount, nums, `delete`, item_type', $filter);
                    if(empty($getList))
                    {
                        continue;
                    }

                    #优惠金额平摊
                    $items_count    =  count($getList);
                    if($val['apportion_pmt'] || $val['pmt_price'])
                    {
                        #关联基础物料贡献占比
                        $salesBasicMList = array();
                        $tempData        = $salesBasicMaterialObj->getList('bm_id, rate', array('sm_id'=>$getItem['goods_id']));
                        foreach ($tempData as $sKey => $sVal)
                        {
                            $salesBasicMList[$sVal['bm_id']]    = $sVal['rate'];
                        }
                    }

                    #平摊优惠金额
                    $bm_rate          = 0;
                    $apportion_pmt    = 0;
                    $pmt_price        = 0;
                    $item_i           = 0;

                    foreach ($getList as $itemKey => $ordItem)
                    {
                        $temp_item_data    = array();
                        $temp_item_data    = $val;

                        $temp_item_data['bn']           = $ordItem['bn'];
                        $temp_item_data['name']         = $ordItem['name'];
                        $temp_item_data['price']        = $ordItem['price'];
                        $temp_item_data['nums']         = $ordItem['nums'];
                        $temp_item_data['pmt_price']    = $ordItem['pmt_price'];//取item层基础物料优惠,默认都是0

                        $temp_item_data['sale_price']   = $ordItem['sale_price'];
                        $temp_item_data['sales_amount'] = $ordItem['amount'];

                        //$temp_item_data['obj_type']     = $ordItem['item_type'];
                        $temp_item_data['type_name']    = $val['obj_type'] ? $material_sales_type[$val['obj_type']] : $material_sales_type[$ordItem['item_type']];

                        $temp_item_data['sm_id']                  = $getItem['goods_id'];
                        $temp_item_data['sales_material_bn']      = $getItem['bn'];
                        $temp_item_data['sales_material_name']    = $getItem['name'];

                        #计算平摊优惠
                        if($val['apportion_pmt'])
                        {
                            $item_i++;

                            if($item_i == $items_count)
                            {
                                $temp_item_data['apportion_pmt']    = $val['apportion_pmt'] - $apportion_pmt;
                                //$temp_item_data['pmt_price']        = $val['pmt_price'] - $pmt_price;

                                //PKG销售金额 = 销售金额 - 平摊优惠
                                $temp_item_data['sales_amount']    = $temp_item_data['sales_amount'] - $temp_item_data['apportion_pmt'];
                            }
                            else
                            {
                                $bm_rate    = $salesBasicMList[$ordItem['product_id']] / 100;

                                $temp_item_data['apportion_pmt']    = round(($val['apportion_pmt'] * $bm_rate), 2);
                                //$temp_item_data['pmt_price']        = round(($val['pmt_price'] * $bm_rate), 2);

                                //PKG销售金额 = 销售金额 - 平摊优惠
                                $temp_item_data['sales_amount']    = $temp_item_data['sales_amount'] - $temp_item_data['apportion_pmt'];

                                $apportion_pmt  += $temp_item_data['apportion_pmt'];
                                //$pmt_price      += $temp_item_data['pmt_price'];
                            }
                        }

                        $agent_info = $agent_obj->dump(array('selling_agent_id' => $salesList[$val['sale_id']]['selling_agent_id']));
                        $temp_item_data['agent_uname'] = $agent_info['member_info']['uname'];

                        $sale_items_arr[]    = $temp_item_data;
                    }
                }
                else
                {
                    #基础物料信息
                    $filter     = array('order_id'=>$temp_order_id, 'product_id'=>$val['product_id'], 'item_type'=>array('product', 'gift'));
                    $getItem    = $orderItemObj->dump($filter, 'item_id, obj_id, item_type');
                    //$val['obj_type']    = $getItem['item_type'];
                    $val['type_name']   = $val['obj_type'] ? $material_sales_type[$val['obj_type']] : $material_sales_type[$getItem['item_type']];

                    #关联销售物料信息

                    $getItem    = $orderObjects->dump(array('obj_id'=>$getItem['obj_id']), 'obj_id, goods_id, bn, name');
                    $val['sm_id']                  = $getItem['goods_id'];
                    $val['sales_material_bn']      = $getItem['bn'];
                    $val['sales_material_name']    = $getItem['name'];

                    $agent_info = $agent_obj->dump(array('selling_agent_id' => $salesList[$val['sale_id']]['selling_agent_id']));
                    $val['agent_uname'] = $agent_info['member_info']['uname'];

                    $sale_items_arr[]    = $val;
                }
            }

            #销毁
            unset($dataList, $temp_item_data, $filter, $getItem, $getList, $material_sales_type);
        }


        $row_num = 1;
        if($sale_items_arr){
            foreach ($sale_items_arr as $key => $sale_item) {
                $sale_item['name'] = str_replace(array("\r\n", "\r", "\n"), "", $sale_item['name']);
                $saleItemRow['bn']    = mb_convert_encoding($sale_item['bn'], 'GBK', 'UTF-8');
                $saleItemRow['name']    = $sale_item['name'];
                $saleItemRow['type_name']       = $sale_item['type_name'];
                $saleItemRow['sales_material_bn']    = mb_convert_encoding($sale_item['sales_material_bn'], 'GBK', 'UTF-8');
                $saleItemRow['sales_material_name']    = str_replace([',', "\n"], '', $sale_item['sales_material_name']);
                $saleItemRow['spec_name']   = str_replace([',', "\n"], '', $sale_item['spec_name']);
                $saleItemRow['price']   = $sale_item['price'];
                $saleItemRow['pmt_price']   = $sale_item['pmt_price'];
                $saleItemRow['nums']   = $sale_item['nums'];
                $saleItemRow['sale_price']   = $sale_item['sale_price'];
                $saleItemRow['apportion_pmt']   = $sale_item['apportion_pmt'];
                $saleItemRow['sales_amount']   = $sale_item['sales_amount'];
                $saleItemRow['platform_amount']   = $sale_item['platform_amount'];
                $saleItemRow['settlement_amount']   = $sale_item['settlement_amount'];
                $saleItemRow['actually_amount']   = $sale_item['actually_amount'];
                $saleItemRow['platform_pay_amount']   = $sale_item['platform_pay_amount'];

                $saleItemRow['agent_uname']   = mb_convert_encoding($sale_item['agent_uname'], 'GBK', 'UTF-8');

                $data[$sale_item['sale_id']][] = $saleItemRow;
                $row_num++;
            }
        }

        //明细标题处理
        if($data && $has_title){
            $title = array(
                '*:销售单号',
                '*:订单号',
                '*:基础物料编码',
                '*:基础物料名称',

                '*:销售物料类型',
                '*:关联销售物料编码',
                '*:关联销售物料名称',

                '*:基础物料规格',
                '*:吊牌价',
                '*:基础物料优惠',
                '*:数量',
                '*:销售总价',
                '*:平摊优惠',
                '*:销售金额',

                '*:代销人用户名',
            );

            foreach ((array)$title as $key => $value) {
                $title[$key] = mb_convert_encoding($value, 'GBK', 'UTF-8');
            }

            $data[0] = implode(',', $title);
        }

        ksort($data);
        return $data;
    }

    /**
     * 订单导出列表扩展字段
     */
    function export_extra_cols(){
        return array(
            'column_ship_name' => array('label'=>'收货人姓名','width'=>'100','func_suffix'=>'ship_name'),
            'column_ship_area' => array('label'=>'收货人地区','width'=>'100','func_suffix'=>'ship_area'),
            'column_ship_addr' => array('label'=>'收货人地址','width'=>'150','func_suffix'=>'ship_addr'),
            'column_ship_zip' => array('label'=>'收货人邮编','width'=>'100','func_suffix'=>'ship_zip'),
            'column_ship_tel' => array('label'=>'收货人固定电话','width'=>'100','func_suffix'=>'ship_tel'),
            'column_ship_email' => array('label'=>'收货人Email','width'=>'100','func_suffix'=>'ship_email'),
            'column_ship_mobile' => array('label'=>'收货人手机','width'=>'100','func_suffix'=>'ship_mobile'),
            'column_delivery_bn' => array('label'=>'发货单号','width'=>'100','func_suffix'=>'delivery_bn'),
        );
    }

    /**
     * 收货人姓名扩展导出字段
     */
    function export_extra_ship_name($rows){
        return kernel::single('sales_exportextracolumn_sales_shipname')->process($rows);
    }

    /**
     * 收货人地区扩展导出字段
     */
    function export_extra_ship_area($rows){
        return kernel::single('sales_exportextracolumn_sales_shiparea')->process($rows);
    }

    /**
     * 收货人地址扩展导出字段
     */
    function export_extra_ship_addr($rows){
        return kernel::single('sales_exportextracolumn_sales_shipaddr')->process($rows);
    }

    /**
     * 收货人邮编扩展导出字段
     */
    function export_extra_ship_zip($rows){
        return kernel::single('sales_exportextracolumn_sales_shipzip')->process($rows);
    }

    /**
     * 收货人固定电话扩展导出字段
     */
    function export_extra_ship_tel($rows){
        return kernel::single('sales_exportextracolumn_sales_shiptel')->process($rows);
    }

    /**
     * 收货人Email扩展导出字段
     */
    function export_extra_ship_email($rows){
        return kernel::single('sales_exportextracolumn_sales_shipemail')->process($rows);
    }

    /**
     * 收货人手机扩展导出字段
     */
    function export_extra_ship_mobile($rows){
        return kernel::single('sales_exportextracolumn_sales_shipmobile')->process($rows);
    }

    /**
     * 发货单号扩展导出字段
     */
    function export_extra_delivery_bn($rows){
        return kernel::single('sales_exportextracolumn_sales_deliverybn')->process($rows);
    }

    /**
     * 单据来源.
     * @param   type    $varname    description
     * @return  type    description
     * @access  public
     * @author cyyr24@sina.cn
     */
    function modifier_archive($row)
    {

        if($row == '1'){
           $row = sprintf("<div style='background-color:%s;float:left;'><span alt='%s' title='%s' style='color:#eeeeee;'>&nbsp;%s&nbsp;</span></div>", 'red', '归档', '归档', '归档');
        }else{
            $row = '-';
        }
        return $row;
    }

    public function modifier_member_id($member_id,$list,$row)
    {
        static $get_from_db,$order_list;

        if ($get_from_db === true) return  $order_list[$row['_0_order_id']]['uname'];

        $member_list = array ();
        foreach ($list as $value) {
            $order_list[$value['_0_order_id']]['member_id'] = $value['member_id'];

            $member_list[$value['member_id']] = array ();
        }


        if ($mid = array_keys($member_list)) {
            $m1Mdl = app::get('ome')->model('members');
            foreach ($m1Mdl->getList('uname,member_id',array('member_id'=>$mid)) as $value) {
                $member_list[$value['member_id']]['uname'] = $value['uname'];
            }
        }

        foreach ($order_list as $order_id => $value) {
            $value['uname'] = $member_list[$value['member_id']]['uname'];

            if ($this->is_export_data) {
                if ('false' != app::get('ome')->getConf('ome.sensitive.exportdata.encrypt')) {
                    $value['uname'] = kernel::single('ome_view_helper2')->modifier_ciphertext($value['uname'],'sales','uname');
                }
                $order_list[$order_id] = $value; continue;
            }

            $is_encrypt = kernel::single('ome_security_hash')->check_encrypt($value['uname']);

            if ($value['uname'] && $is_encrypt) {
                $encrypt = kernel::single('ome_view_helper2')->modifier_ciphertext($value['uname'],'sales','uname');

                $value['uname'] = <<<HTML
<a class="data-hide" href="javascript:void(0);" onclick="Ex_Loader('security',function(){new Security({url:'index.php?app=ome&ctl=admin_member&act=showSensitiveData&p[0]={$order_id}',clickElement:\$(event.target)}).desHtml(\$(event.target).getNext()); });"></a><span><span sensitive-field="uname">{$encrypt}</span></span>
HTML;
            }

            $order_list[$order_id] = $value;
        }

        $get_from_db = true;

        return $order_list[$row['_0_order_id']]['uname'];
    }
    
    /**
     * 根据查询条件获取导出数据
     * @Author: xueding
     * @Vsersion: 2022/5/25 上午10:35
     * @param $fields
     * @param $filter
     * @param $has_detail
     * @param $curr_sheet
     * @param $start
     * @param $end
     * @param $op_id
     * @return bool
     */
    public function getExportDataByCustom($fields, $filter, $has_detail, $curr_sheet, $start, $end, $op_id)
    {
        $params = [
            'fields'     => $fields,
            'filter'     => $filter,
            'has_detail' => $has_detail,
            'curr_sheet' => $curr_sheet,
            'op_id'      => $op_id,
        ];

        $salesListData = kernel::single('ome_func')->exportDataMain(__CLASS__,$params);
        if (!$salesListData) {
            return false;
        }
    
        //根据选择的字段定义导出的第一行标题
        if ($curr_sheet == 1) {
            $data['content']['main'][] = $this->getCustomExportTitle($salesListData['title']);
        }

        $sales_items_columns      = array_values($this->salesItemsExportTitle());

        $sale_ids = array_column($salesListData['content'],'sale_id');
        $main_columns = array_values($salesListData['title']);
        $saleList = $salesListData['content'];
        //所有的子销售数据
        $sales_items = $this->getexportdetail('',array('sale_id'=>$sale_ids));
        foreach( $saleList as $saleRow ){
            $objects = $sales_items[$saleRow['sale_id']];
            $items_fields = implode(',', $sales_items_columns);
            $all_fields   = implode(',', $main_columns) . ',' . $items_fields;
            if ($objects){
                foreach ($objects as $obj){
                    $salesDataRow = array_merge($saleRow, $obj);
                    $exptmp_data = [];
                    foreach (explode(',', $all_fields) as $key => $col) {
                        if (isset($salesDataRow[$col])) {
                            $salesDataRow[$col] = mb_convert_encoding(str_replace(array(",","\r\n","\r","\n","\"")," ",$salesDataRow[$col]), 'GBK', 'UTF-8');
                            $exptmp_data[]      = $salesDataRow[$col];
                        } else {
                            $exptmp_data[] = '';
                        }
                    }
                    $data['content']['main'][] = implode(',', $exptmp_data);
                }
            }
        }
        return $data;
    }
    
    public function getCustomExportTitle($main_title)
    {
        $main_title = array_keys($main_title);
        $saleItems_title = array_keys($this->salesItemsExportTitle());
        $title           = array_merge($main_title, $saleItems_title);
        return mb_convert_encoding(implode(',', $title), 'GBK', 'UTF-8');
    }
    
    
    public function salesItemsExportTitle()
    {
        $items_title = array(
            '详情基础物料编码'   => 'bn',
            '详情基础物料名称'   => 'name',
            '详情销售物料类型'   => 'type_name',
            '详情关联销售物料编码' => 'sales_material_bn',
            '详情关联销售物料名称' => 'sales_material_name',
            '详情基础物料规格'     => 'spec_name',
            '详情吊牌价'      => 'price',
            '详情基础物料优惠'     => 'pmt_price',
            '详情数量'       => 'nums',
            '详情销售总价'     => 'sale_price',
            '详情平摊优惠'     => 'apportion_pmt',
            '详情销售金额'     => 'sales_amount',
            '详情平台承担金额'     => 'platform_amount',
            '详情结算金额'     => 'settlement_amount',
            '详情客户实付'     => 'actually_amount',
            '详情支付优惠金额'     => 'platform_pay_amount',
            '代销人用户名'     => 'agent_uname',
        );
        return $items_title;
    }
    
    function modifier_discount($row){
        if($row){
            if (!kernel::single('desktop_user')->has_permission('sale_price')) {
                return '-';
            }else{
                return '￥' . $row;
            }
        }
    }
    
    function modifier_total_amount($row){
        if($row){
            if (!kernel::single('desktop_user')->has_permission('sale_price')) {
                return '-';
            }else{
                return '￥' . $row;
            }
        }
    }
    
    function modifier_sale_amount($row){
        if($row){
            if (!kernel::single('desktop_user')->has_permission('sale_price')) {
                return '-';
            }else{
                return '￥' . $row;
            }
        }
    }

    public function modifier_shop_id($shop_id,$list,$row){
        static $shopList;

        if (isset($shopList)) {
            return $shopList[$shop_id];
        }

        $shopIds  = array_unique(array_column($list, 'shop_id'));
        $shopList = app::get('ome')->model('shop')->getList('shop_id,name', ['shop_id'=>$shopIds]);
        $shopList = array_column($shopList, 'name', 'shop_id');

        return $shopList[$shop_id];
    }

    public function modifier_org_id($org_id,$list,$row){
        static $orgList;

        if (isset($orgList)) {
            return $orgList[$org_id];
        }

        $orgIds  = array_unique(array_column($list, 'org_id'));
        $orgList = app::get('ome')->model('operation_organization')->getList('org_id,name', ['org_id'=>$orgIds]);
        $orgList = array_column($orgList, 'name', 'org_id');

        return $orgList[$org_id];
    }

}
