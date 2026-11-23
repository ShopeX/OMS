<?php
class omeanalysts_mdl_ome_sales extends dbeav_model{

    var $has_export_cnf = true;

    var $export_name = '订单销售情况';

    var $mark_type = array('b0'=>'灰色','b1'=>'红色','b2'=>'橙色','b3'=>'黄色','b4'=>'蓝色','b5'=>'紫色','b6'=>'粉红色','b7'=>'绿色',''=>'-');

    /**
     * 须加密字段
     *
     * @var string
     **/
    private $__encrypt_cols = array(
        'ship_name'   => 'simple',
        'ship_tel'    => 'phone',
        'ship_mobile' => 'phone',
    );

    public function __construct($app){
        return parent::__construct(app::get('ome'));
    }

    public function table_name($real=false){
        if($real){
            $table_name = kernel::database()->prefix.'ome_sales';
        }else{
            $table_name = "ome_sales";
        }

        return $table_name;
    }

    public function get_sales($filter = null){

        $cols = 'count(S.order_id) as order_counts,sum(S.total_amount) as total_amounts,sum(S.cost_freight) as cost_freights,sum(S.sale_amount) as sale_amounts,sum(S.discount) as discounts,sum(S.delivery_cost_actual) as delivery_cost_actuals,sum(additional_costs) as additional_costs';

        $sql = 'SELECT '.$cols.' FROM sdb_ome_sales S WHERE '.$this->_filter($filter);

        $rows = $this->db->select($sql);

        $this->getSkuItems($rows[0],$filter);

        return $rows[0];
    }

    public function count($filter = null){

        // $sql = 'SELECT count(*) as _count FROM (SELECT S.sale_id FROM sdb_ome_sales S left join sdb_ome_delivery D on S.delivery_id = D.delivery_id WHERE '.$this->_filter($filter).') as omeanalysts_sales';
        $sql = "SELECT count(S.sale_id) as _count FROM sdb_ome_sales S WHERE ".$this->_filter($filter);

        $rows = $this->db->select($sql);

        return intval($rows[0]['_count']);
    }

    public function getList($cols='*', $filter=array(), $offset=0, $limit=-1, $orderType=null){

        if(isset($filter['_gross_sales_search'])&&($filter['_gross_sales_search'])){
            $tmp_gross_sales['_gross_sales_search'] = $filter['_gross_sales_search'];
            $tmp_gross_sales['gross_sales'] = $filter['gross_sales'];
            $tmp_gross_sales['gross_sales_from'] = $filter['gross_sales_from'];
            $tmp_gross_sales['gross_sales_to'] = $filter['gross_sales_to'];
            unset($filter['_gross_sales_search'],$filter['gross_sales'],$filter['gross_sales_from'],$filter['gross_sales_to']);
        }

        if(isset($filter['_gross_sales_rate_search'])&&($filter['_gross_sales_rate_search'])){
            $tmp_gross_sales_rate['_gross_sales_rate_search'] = $filter['_gross_sales_rate_search'];
            $tmp_gross_sales_rate['gross_sales_rate'] = $filter['gross_sales_rate'];
            $tmp_gross_sales_rate['gross_sales_rate_from'] = $filter['gross_sales_rate_from'];
            $tmp_gross_sales_rate['gross_sales_rate_to'] = $filter['gross_sales_rate_to'];
            unset($filter['_gross_sales_rate_search'],$filter['gross_sales_rate'],$filter['gross_sales_rate_from'],$filter['gross_sales_rate_to']);
        }

        $oItem = kernel::single("ome_mdl_sales_items");
        $cols = 'D.logi_no,D.ship_area,S.sale_id,S.order_id,S.sale_bn,S.discount,S.total_amount,S.cost_freight,S.sale_amount,S.branch_id,S.delivery_cost_actual,S.member_id,S.additional_costs,S.payment,S.delivery_id,S.order_create_time,S.paytime,S.ship_time,S.shop_id';
        $sql = 'SELECT '.$cols.' FROM sdb_ome_sales S left join sdb_ome_delivery D on S.delivery_id = D.delivery_id WHERE '.$this->_filter($filter);


        $_SESSION['filter'] = $filter;

        $rows = $this->db->selectLimit($sql,$limit,$offset);


        foreach($rows as $key=>$row){

            // 数据解密
            foreach ($this->__encrypt_cols as $field => $type) {
                if (isset($row[$field])) {
                    $rows[$key][$field] = (string) kernel::single('ome_security_factory')->decryptPublic($row[$field],$type);
                }
            }

            $ship_area = explode(':', $rows[$key]['ship_area']);
            $total_items = $oItem->getList('nums,cost,cost_amount,sales_amount',array('sale_id'=>$rows[$key]['sale_id']));

            $total_product_nums = $goods_sales_prices = $cost_amounts = 0;

            foreach ($total_items as $v) {
                $total_product_nums += $v['nums'];
                $goods_sales_prices += $v['sales_amount'];
                $cost_amounts += $v['cost_amount'];
            }

            $total_products_types = count($total_items);

            $rows[$key]['product_nums'] = $total_product_nums;
            $rows[$key]['products_type'] = $total_products_types;
            $rows[$key]['goods_sales_price'] = $goods_sales_prices;
            $rows[$key]['cost_amount'] = $cost_amounts;
            $rows[$key]['shop_type'] = kernel::single('omeanalysts_shop')->getShopDetail($row['shop_id']);
            $rows[$key]['ship_area'] = $ship_area[1];

            $cost_amount = $rows[$key]['cost_amount']?$rows[$key]['cost_amount']:0;
            $sale_amount = $rows[$key]['sale_amount']?$rows[$key]['sale_amount']:0;
            $delivery_cost_actual = $rows[$key]['delivery_cost_actual']?$rows[$key]['delivery_cost_actual']:0;
            //毛利 gross_sales
            $gross_sales = $sale_amount - $cost_amount - $delivery_cost_actual;//毛利
            $rows[$key]['gross_sales'] = round($gross_sales,3);

            //毛利率 gross_sales_rate
            $gross_sales_rate = ($sale_amount && $sale_amount!=0)?(round($gross_sales/$sale_amount,2)*100):0;
            $rows[$key]['gross_sales_rate'] = $gross_sales_rate."%";

            if(isset($tmp_gross_sales)){
                if(!$this->money_filter('gross_sales',$tmp_gross_sales,$rows[$key]['gross_sales'])){
                   unset($rows[$key]);
                }
            }

            if(isset($tmp_gross_sales_rate)){
                if(!$this->money_filter('gross_sales_rate',$tmp_gross_sales_rate,$gross_sales_rate)){
                   unset($rows[$key]);
                }
            }

            unset($total_product_nums,$goods_sales_prices,$cost_amounts);

        }

         //对数组排序
         if($orderType){
            $cost_amount = $gross_sales = $gross_sales_rate = [];
             foreach($rows as $k=>$data){
                $shop_id[$k] = $data['shop_id'];

                $order_id[$k] = $data['order_id'];
                $sale_bn[$k] = $data['sale_bn'];
                $products_type[$k] = $data['products_type'];
                $product_nums[$k] = $data['product_nums'];
                $total_amount[$k] = $data['total_amount'];
                $goods_sales_price[$k] = $data['goods_sales_price'];
                $discount[$k] = $data['discount'];
                $cost_freight[$k] = $data['cost_freight'];
                $sale_amount[$k] = $data['sale_amount'];
                $cost_amount[$k] = $data['cost_amount'];
                $delivery_cost_actual[$k] = $data['delivery_cost_actual'];
                $gross_sales[$k] = $data['gross_sales'];
                $gross_sales_rate[$k] = $data['gross_sales_rate'];
                $order_create_time[$k] = $data['order_create_time'];
                $paytime[$k] = $data['paytime'];
                $ship_time[$k] = $data['ship_time'];
                $branch_id[$k] = $data['branch_id'];
                $member_id[$k] = $data['member_id'];
                $payment[$k] = $data['payment'];
                $delivery_id[$k] = $data['delivery_id'];
                $ship_area[$k] = $data['ship_area'];
             }
            if(is_string($orderType)){
                $arr = explode(" ", $orderType);
                if(!in_array($arr[0],array('order_create_time','paytime','ship_time')) && ${$arr[0]}){
                    if(strtolower($arr[1]) == 'desc'){
                        array_multisort(${$arr[0]},SORT_DESC,$rows);
                    }
                    else {
                        array_multisort(${$arr[0]},SORT_ASC,$rows);
                    }
                }elseif($arr[0] == 'order_create_time'){
                    if(strtolower($arr[1]) == 'desc'){
                        array_multisort($order_create_time,SORT_DESC,$rows);
                    }
                    else{
                        array_multisort($order_create_time,SORT_ASC,$rows);
                    }
                }elseif($arr[0] == 'paytime'){
                    if(strtolower($arr[1]) == 'desc'){
                        array_multisort($paytime,SORT_DESC,$rows);
                    }
                    else{
                        array_multisort($paytime,SORT_ASC,$rows);
                    }
                }else{
                    if(strtolower($arr[1]) == 'desc'){
                        array_multisort($ship_time,SORT_DESC,$rows);
                    }
                    else{
                        array_multisort($ship_time,SORT_ASC,$rows);
                    }
                }
            }
         }

        return $rows;
    }

    public function getSkuItems(&$data,$filter){
        // $sql = 'select sum(SI.nums) as nums,sum(SI.cost_amount) as goods_cost from sdb_ome_sales_items SI left join sdb_ome_sales S on SI.sale_id = S.sale_id where '.$this->_filter($filter);
        $sql = 'SELECT sum(SI.nums) as nums,sum(SI.cost_amount) as goods_cost FROM sdb_ome_sales_items SI WHERE sale_id in (SELECT sale_id FROM sdb_ome_sales S WHERE '. $this->_filter($filter) .')';

        $s_data = $this->db->selectrow($sql);
        $data['product_nums'] = $s_data['nums'];
        $data['cost_amounts'] = $s_data['goods_cost'];
/*
        set_time_limit(0);

        $offset = 0;

        while ($this->countSkuitems($offset,$s_data,$filter)) {

            $data['product_nums'] = $s_data['nums'];
            $data['cost_amounts'] = $s_data['goods_cost'];
            $offset++;
        } */

    }

    public function countSkuitems($offset,&$data,$filter){

        $limit = 1000;

        $sql = 'select SI.nums,SI.cost_amount as goods_cost from sdb_ome_sales_items SI left join sdb_ome_sales S on SI.sale_id = S.sale_id where '.$this->_filter($filter).' limit '.$offset*$limit.','.$limit;

        $rows = $this->db->select($sql);

        if(!$rows) return false;

        foreach ($rows as $k => $v) {
            $data['nums'] += $v['nums'];
            $data['goods_cost'] += $v['goods_cost'];
        }

        unset($rows);

        return true;
    }

    public function _filter($filter,$tableAlias=null,$baseWhere=null){

        $where = '';

        if(isset($filter['ship_area'])&&($filter['ship_area'])){
            $deliveryObj = $this->app->model("delivery");
            $rows = $deliveryObj->getList('delivery_id',array('ship_area'=>$filter['ship_area']));
            $deliveryId[] = -1;
            foreach($rows as $row){
                $deliveryId[] = $row['delivery_id'];
            }
            $where .= '  AND S.delivery_id IN ('.implode(',', $deliveryId).')';

            unset($filter['ship_area']);
        }


        if(isset($filter['_order_create_time_search'])||isset($filter['_paytime_search'])||isset($filter['_ship_time_search'])){
            unset($filter['time_from'],$filter['time_to']);
        }


        if( isset($filter['order_id']) && $filter['order_id'] ){
            $orderObj = $this->app->model("orders");
            $rows = $orderObj->getList('order_id',array('order_bn|head'=>$filter['order_id']));
            $orderId[] = -1;
            foreach($rows as $row){
                $orderId[] = $row['order_id'];
            }
            $where .= '  AND S.order_id IN ('.implode(',', $orderId).')';

            unset($filter['order_id']);
        }

        if(isset($filter['own_branches']) && $filter['own_branches']){
            $where .= '  AND S.branch_id in ('.implode(',',$filter['own_branches']).')';
        }
        unset($filter['own_branches']);

        if(isset($filter['branch_id']) && $filter['branch_id']){
            $where .= '  AND S.branch_id = \''.addslashes($filter['branch_id']).'\'';
        }
        unset($filter['branch_id']);

        if(isset($filter['shop_id']) && $filter['shop_id']){
            if (!is_array($filter['shop_id'])) {
                $where .= '  AND S.shop_id = \''.addslashes($filter['shop_id']).'\'';
            } else {
                if (count($filter['shop_id']) == 1) {
                    $where .= '  AND S.shop_id = \''.addslashes($filter['shop_id'][0]).'\'';
                } else {
                    $where .= '  AND S.shop_id IN (\''.implode("','", $filter['shop_id']).'\')';
                }
            }
        }
        unset($filter['shop_id']);

        if (isset($filter['shop_type']) && $filter['shop_type']){
            $shopList = kernel::single('omeanalysts_shop')->getShopList();
            $shop_ids = $shopList[$filter['shop_type']];

            if ($shop_ids){
                $where .= " AND S.shop_id in ('".implode('\',\'',$shop_ids)."')";
            }

        }
        unset($filter['shop_type']);
        
        if(isset($filter['org_id']) && $filter['org_id']){
            $where .= " AND S.org_id in ('".implode('\',\'',$filter['org_id'])."')";
        }
        unset($filter['org_id']);
        
        $config = app::get('eccommon')->getConf('analysis_config');

        if(isset($config['filter']['order_status']) && $config['filter']['order_status']){
            switch($config['filter']['order_status']){
                case 'createorder':
                    $time_filter = 'order_create_time';
                break;
                case 'confirmed':
                    $time_filter = 'order_check_time';
                break;
                case 'pay':
                    $time_filter = 'paytime';
                break;
                case 'ship':
                    $time_filter = 'ship_time';
                break;
            }

        }else{
            $time_filter = 'sale_time';
        }

        if(isset($filter['time_from']) && $filter['time_from']){
            $where .= ' AND S.'.$time_filter.' >='.strtotime($filter['time_from']);
            unset($filter['time_from']);
        }

        if(isset($filter['time_to']) && $filter['time_to']){
            $where .= ' AND S.'.$time_filter.' <='.strtotime($filter['time_to'].' 23:59:59');
            unset($filter['time_to']);
        }

        unset($config);

        $out_filter = parent::_filter($filter,$tableAlias,$baseWhere).$where;

        $out_filter = str_replace('`sdb_ome_sales`','S',$out_filter);

        return $out_filter;
    }

    public function money_filter($key,$filter,$target){
        switch($filter['_'.$key.'_search']){
            case 'than':
                if(isset($filter[$key]) && ($filter[$key])){
                    $_where = ($target > $filter[$key])?true:false;
                }
            break;
            case 'lthan':
                if(isset($filter[$key]) && ($filter[$key])){
                    $_where = ($target < $filter[$key])?true:false;
                }
            break;
            case 'nequal':
                if(isset($filter[$key]) && ($filter[$key])){
                    $_where = ($target == $filter[$key])?true:false;
                }
            break;
            case 'sthan':
                if(isset($filter[$key]) && ($filter[$key])){
                   $_where = ($target <= $filter[$key])?true:false;
                }
            break;
            case 'bthan':
                if(isset($filter[$key]) && ($filter[$key])){
                   $_where = ($target >= $filter[$key])?true:false;
                }
            break;
            case 'between':
                if(isset($filter[$key.'_from']) && ($filter[$key.'_from']) && isset($filter[$key.'_to']) && ($filter[$key.'_to'])){
                    $_where = ( ($target >= $filter[$key]) && ($target < $filter[$key]) )?true:false;
                }
            break;
        }

        return $_where;
    }

    public function exportName(&$data){
        $data['name'] = $_POST['time_from'].'到'.$_POST['time_to'].'订单销售情况';
    }

    public function fgetlist_csv( &$data,$filter,$offset,$exportType = 1 ){

        if( isset($_SESSION['filter']) && $_SESSION['filter'] ){
           $filter = array_merge($filter,$_SESSION['filter']);
        }

        $limit = 100;

        $productssale = $this->getList('*',$filter,$offset*$limit,$limit);

        if(!$productssale) return false;


         @ini_set('memory_limit','1024M');
        if( !$data['title'] ){
            $title = array();
            foreach( $this->io_title() as $k => $v ){
                $title[] = $v;
            }

            $data['title']['omeanalysts_sales'] = mb_convert_encoding('"'.implode('","',$title).'"', 'GBK', 'UTF-8');
        }


        $obj = app::get('ome');
        $Obranch = $obj->model('branch');
        $oShop = $obj->model('shop');
        $oMembers = $obj->model('members');
        $oDelivery = $obj->model('delivery');
        $oOrder = $obj->model('orders');

        foreach($productssale as $v) {
            $order_ids[] = $v['order_id'];
            $member_ids[] = $v['member_id'];
            $delivery_ids[] = $v['delivery_id'];
        }

        //获取所有仓库
        $branchs = $Obranch->getList('name,branch_id');
        foreach ($branchs as $v) {
            $branch[$v['branch_id']] = $v['name'];
        }

        // 所有的店铺信息
        $rs = $oShop->getList('shop_id,shop_bn,name');
        foreach($rs as $v) {
            $shops[$v['shop_id']] = $v['name'];
        }

        // 所有的会员
        $rs = $oMembers->getList('member_id,uname',array('member_id'=>$member_ids));
        foreach($rs as $v) {
            $members[$v['member_id']] = $v['uname'];
        }

        // 所有的订单信息
        $rs = $oOrder->getList('order_id,order_bn,mark_type',array('order_id'=>$order_ids));
        foreach($rs as $v) {
            $orders[$v['order_id']] = $v;
        }

        // 所有的发货单信息
        $rs = $oDelivery->getList('delivery_id,delivery_bn',array('delivery_id'=>$delivery_ids));
        foreach($rs as $v) {
            $deliverys[$v['delivery_id']] = $v['delivery_bn'];
        }
//商品销售额  商品成本
        foreach ($productssale as $k => $aFilter) {

            $productRow['*:店铺名称'] = $shops[$aFilter['shop_id']];
            $productRow['*:订单号'] = "=\"\"".$orders[$aFilter['order_id']]['order_bn']."\"\"";
            $productRow['*:销售号'] = $aFilter['sale_bn'];
            $productRow['*:货品种数'] = $aFilter['products_type'];
            $productRow['*:货品数量'] = $aFilter['product_nums'];
            $productRow['*:商品总额'] = $aFilter['total_amount'];
            $productRow['*:商品销售额'] = $aFilter['goods_sales_price']?$aFilter['goods_sales_price']:0;
            $productRow['*:优惠额'] = $aFilter['discount'];
            $productRow['*:物流金额'] = $aFilter['cost_freight'];
            $productRow['*:销售价'] = $aFilter['sale_amount'];
            $productRow['*:商品成本'] = $aFilter['cost_amount']?$aFilter['cost_amount']:0;
            $productRow['*:物流成本'] = $aFilter['delivery_cost_actual']?$aFilter['delivery_cost_actual']:0;
            $productRow['*:毛利'] = $aFilter['gross_sales'];
            $productRow['*:毛利率'] = $aFilter['gross_sales_rate'];
            $productRow['*:订单创建时间'] = date('Y-m-d H:i:s',$aFilter['order_create_time']);
            $productRow['*:订单支付时间'] = $aFilter['paytime']?date('Y-m-d H:i:s',$aFilter['paytime']):'';
            $productRow['*:订单发货时间'] = date('Y-m-d H:i:s',$aFilter['ship_time']);
            $productRow['*:仓库名称'] = $branch[$aFilter['branch_id']];
            $productRow['*:用户名称'] = $members[$aFilter['member_id']];
            $productRow['*:附加费'] = $aFilter['additional_costs'];
            $productRow['*:支付方式'] = $aFilter['payment'];
            $productRow['*:发货单号'] = $deliverys[$aFilter['delivery_id']]."\t";
            $productRow['*:收货人地区'] = $aFilter['ship_area'];
            $productRow['*:订单备注图标'] = $this->mark_type[$orders[$aFilter['order_id']]['mark_type']];
            $productRow['*:物流单号'] = "\t".$aFilter['logi_no'];

            $data['content']['omeanalysts_sales'][] = mb_convert_encoding('"'.implode('","',$productRow).'"', 'GBK', 'UTF-8');
        }

        return true;

    }

    public function export_csv($data,$exportType = 1 ){

        $output = array();

        $output[] = $data['title']['omeanalysts_sales']."\n".implode("\n",(array)$data['content']['omeanalysts_sales']);

        echo implode("\n",$output);
    }

    public function io_title( $filter=null,$ioType='csv'){
        switch( $ioType ){
            case 'csv':
            default:
                $this->oSchema['csv']['main'] = array(
                    '*:店铺名称'=>'shop_id',
                    '*:订单号'=>'order_id',
                    '*:销售号'=>'sale_bn',
                    '*:货品种数'=>'products_type',
                    '*:货品数量'=>'product_nums',
                    '*:商品总额'=>'total_amount',
                    '*:商品销售额'=>'goods_sales_price',
                    '*:优惠额'=>'discount',
                    '*:物流金额'=>'cost_freight',
                    '*:销售价'=>'sale_amount',
                    '*:商品成本'=>'cost_amount',
                    '*:物流成本'=>'delivery_cost_actual',
                    '*:毛利'=>'gross_sales',
                    '*:毛利率'=>'gross_sales_rate',
                    '*:订单创建时间'=>'order_create_time',
                    '*:订单支付时间'=>'paytime',
                    '*:订单发货时间'=>'ship_time',
                    '*:仓库名称'=>'branch_id',
                    '*:用户名称'=>'member_id',
                    '*:附加费'=>'additional_costs',
                    '*:支付方式'=>'payment',
                    '*:发货单号'=>'delivery_id',
                    '*:收货人地区'=>'ship_area',
                    '*:订单备注图标'=>'mark_type',
                    '*:物流单号'=>'logi_no'
                );
        }
        $this->ioTitle[$ioType][$filter] = array_keys( $this->oSchema[$ioType]['main'] );
        return $this->ioTitle[$ioType][$filter];
    }

    public function get_schema(){
        $schema = array (
            'columns' => array (
                'shop_id' =>
                array (
                  'type' => 'table:shop@ome',
                  'label' => '店铺名称',
                  'orderby' =>true,
                  'default_in_list'=>true,
                  'in_list'=>true,
                  'order' =>'1',
                  //'filtertype' => 'normal',
                  //'filterdefault' => true,
                ),
                'shop_type'=>array(
                    'type' => 'varchar(32)',
                    'label' => '店铺类型',
                    'in_list' => true,
                    'default_in_list' => true,
                    'width' => '70',
                    'order' =>'2',
                ),
                'order_id' => array (
                    'type' => 'table:orders@ome',
                    'label' => '订单号',
                    'order' =>'2',
                    'editable' => false,
                    'orderby' =>true,
                    'searchtype' => 'has',
                ),
                'sale_bn' => array (
                    'type' => 'varchar(32)',
                    'label' => '销售号',
                    'order' =>'3',
                    'editable' => false,
                    'orderby' =>true,
                    'searchtype' => 'has',
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                ),
                'products_type' => array (
                    'type' => 'varchar(50)',
                    'label' => '货品种数',
                    'comment' => '货品种数',
                    'orderby' =>true,
                    'editable' => false,
                    'order' =>'4',
                    'width' =>85,
                ),
                'product_nums' => array (
                    'type' => 'varchar(100)',
                    'label' => '货品数量',
                    'comment' => '货品数量',
                    'orderby' =>true,
                    'order' =>'5',
                    'editable' => false,
                    #'filtertype' => 'yes',
                    #'filterdefault' => true,
                    'width' =>75,
                ),
                'total_amount' => array (
                    'type' => 'money',
                    'required' => true,
                    'label' => '商品总额',
                    'orderby' =>true,
                    'comment' => '商品总额',
                    'editable' => false,
                    'order' =>'6',
                    'width' =>100,
                ),
                'goods_sales_price' => array (
                    'type' => 'money',
                    'label' => '商品销售额',
                    'comment' => '商品销售额',
                    'width' => 110,
                    'order' =>'7',
                    'orderby' =>true,
                    'in_list' => true,
                    'default_in_list' => true,
                ),
                'discount' => array (
                    'type' => 'number',
                    'editable' => false,
                    'label' => '优惠额',
                    'comment' => '优惠额',
                    'orderby' =>true,
                    'order' =>'8',
                    'width' =>85,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                ),
                'cost_freight' => array (
                    'type' => 'number',
                    'editable' => false,
                    'label' => '物流金额',
                    'orderby' =>true,
                    'order' =>'9',
                    'width' =>85,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                ),
                'sale_amount' => array (
                    'type' => 'number',
                    'editable' => false,
                    'label' => '销售价',
                    'orderby' =>true,
                    'order' =>'10',
                    'width' =>85,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                ),
                'cost_amount' => array (
                    'type' => 'money',
                    'editable' => false,
                    'label' => '商品成本',
                    'orderby' =>true,
                    'order' =>'11',
                    'width' =>85,
                ),
                'delivery_cost_actual' => array (
                    'type' => 'money',
                    'editable' => false,
                    'label' => '物流成本',
                    'orderby' =>true,
                    'order' =>'12',
                    'width' =>85,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                ),
                'gross_sales' => array (
                    'type' => 'money',
                    'editable' => false,
                    'label' => '毛利',
                    'orderby' =>true,
                    'order' =>'13',
                    'width' =>85,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                ),
                'gross_sales_rate' => array (
                    'type' => 'number',
                    'editable' => false,
                    'label' => '毛利率',
                    'orderby' =>false,
                    'order' =>'14',
                    'width' =>85,
                    'filtertype' => 'number',
                    'filterdefault' => true,
                ),
                'order_create_time' => array (
                    'type' => 'time',
                    'label' => '订单创建时间',
                    'order' =>'15',
                    'orderby' =>true,
                    'filtertype' => 'time',
                    'filterdefault' => true,
                    'width' =>130,
                ),
                'paytime' => array (
                    'type' => 'time',
                    'label' => '订单支付时间',
                    'comment' => '',
                    'order' =>'16',
                    'orderby' =>true,
                    'editable' => false,
                    'filtertype' => 'time',
                    'filterdefault' => true,
                    'width' =>130,
                ),
                'ship_time' => array (
                    'type' => 'time',
                    'label' => '订单发货时间',
                    'order' =>'17',
                    'orderby' =>true,
                    'editable' => false,
                    'filtertype' => 'time',
                    'filterdefault' => true,
                    'width' =>130,
                ),
                'branch_id' => array (
                    'type' => 'table:branch@ome',
                    'editable' => false,
                    'label' => '仓库名称',
                    'orderby' =>true,
                    'order' =>'18',
                    'width' =>85,
                    //'filtertype' => 'normal',
                    //'filterdefault' => true,
                ),
                'member_id' =>
                array (
                  'type' => 'table:members@ome',
                  'label' => '用户名称',
                  'filtertype' => 'normal',
                  'filterdefault' => true,
                  'orderby' =>true,
                ),
                'additional_costs' =>
                array (
                  'type' => 'money',
                  'label' => '附加费',
                  'orderby' =>true,
                ),                
                'payment' =>
                array (
                  'type' => 'varchar(255)',
                  'label' => '支付方式',
                  'width' => 65,
                  'orderby' =>true,
                ),
                'delivery_id' =>
                array (
                  'type' => 'table:delivery@ome',
                  'comment' => '发货单号',
                  'orderby' =>true,
                ),
                'ship_area' =>
                array (
                  'type' => 'region',
                  'label' => '收货人地区',
                  'comment' => '收货人地区',
                  'filtertype' => 'normal',
                  'filterdefault' => true,
                  'width' =>130,
                  'orderby' =>true,
                  'sdfpath' => 'consignee/area',
                ),
                'logi_no' =>
                array (
                        'type' => 'varchar(50)',
                        'label' => '物流单号',
                        'comment' => '物流单号',
                        'editable' => false,
                        'width' =>110,
                        'in_list' => true,
                        'default_in_list' => true,
                ),
            ),
            'idColumn' => 'sale_bn',
            'in_list' => array (
                0 => 'shop_id',
                1 => 'order_id',
                2 => 'sale_bn',
                3 => 'products_type',
                4 => 'product_nums',
                5 => 'total_amount',
                6 => 'goods_sales_price',
                7 => 'discount',
                8 => 'cost_freight',
                9 => 'sale_amount',
                10 => 'cost_amount',
                11 => 'delivery_cost_actual',
                12 => 'gross_sales',
                13 => 'gross_sales_rate',
                14 => 'order_create_time',
                15 => 'paytime',
                16 => 'ship_time',
                17 => 'branch_id',
                18 => 'member_id',
                19 => 'additional_costs',
                20 => 'payment',
                21 => 'ship_area',
                22 => 'logi_no',
                23=>'shop_type',
            ),
            'default_in_list' => array (
                0 => 'shop_id',
                1 => 'order_id',
                2 => 'sale_bn',
                3 => 'products_type',
                4 => 'product_nums',
                5 => 'total_amount',
                6 => 'goods_sales_price',
                7 => 'discount',
                8 => 'cost_freight',
                9 => 'sale_amount',
                10 => 'cost_amount',
                11 => 'delivery_cost_actual',
                12 => 'gross_sales',
                13 => 'gross_sales_rate',
                14 => 'order_create_time',
                15 => 'paytime',
                16 => 'ship_time',
                17 => 'branch_id',
                18 => 'additional_costs',
                19 => 'logi_no',
                20 =>'shop_type',
            ),
        );

        return $schema;
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
        $type = 'report';
        if ($logParams['app'] == 'omeanalysts' && $logParams['ctl'] == 'ome_analysis') {
            $type .= '_salesReport_orderSales';
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
        $type = 'report';
        if ($logParams['app'] == 'omeanalysts' && $logParams['ctl'] == 'ome_analysis') {
            $type .= '_salesReport_orderSales';
        }
        $type .= '_import';
        return $type;
    }

    //根据查询条件获取导出数据
    public function getExportDataByCustom($fields, $filter, $has_detail, $curr_sheet, $start, $end, $op_id){
        
        //根据选择的字段定义导出的第一行标题
        if($curr_sheet == 1){
            $data['content']['main'][] = $this->getExportTitle($fields);
        }

        $productssale = $this->getList('*',$filter,$start,$end);
        if(!$productssale) return false;

        $Obranch = app::get('ome')->model('branch');
        $oShop = app::get('ome')->model('shop');
        $oMembers = app::get('ome')->model('members');
        $oDelivery = app::get('ome')->model('delivery');
        $oOrder = app::get('ome')->model('orders');

        foreach($productssale as $v) {
            $order_ids[] = $v['order_id'];
            $member_ids[] = $v['member_id'];
            $delivery_ids[] = $v['delivery_id'];
        }

        //获取所有仓库
        $branchs = $Obranch->getList('name,branch_id');
        foreach ($branchs as $v) {
            $branch[$v['branch_id']] = $v['name'];
        }

        // 所有的店铺信息
        $rs = $oShop->getList('shop_id,shop_bn,name');
        foreach($rs as $v) {
            $shops[$v['shop_id']] = $v['name'];
        }

        // 所有的会员
        $rs = $oMembers->getList('member_id,uname',array('member_id'=>$member_ids));
        foreach($rs as $v) {
            $members[$v['member_id']] = $v['uname'];
        }

        // 所有的订单信息
        $rs = $oOrder->getList('order_id,order_bn,mark_type',array('order_id'=>$order_ids));
        foreach($rs as $v) {
            $orders[$v['order_id']] = $v;
        }

        // 所有的发货单信息
        $rs = $oDelivery->getList('delivery_id,delivery_bn',array('delivery_id'=>$delivery_ids));
        foreach($rs as $v) {
            $deliverys[$v['delivery_id']] = $v['delivery_bn'];
        }

        //商品销售额  商品成本
        foreach ($productssale as $k => $aFilter) {
            $productRow['shop_id'] = $shops[$aFilter['shop_id']];
            $productRow['order_id'] = $orders[$aFilter['order_id']]['order_bn'];
            $productRow['sale_bn'] = $aFilter['sale_bn'];
            $productRow['products_type'] = $aFilter['products_type'];
            $productRow['product_nums'] = $aFilter['product_nums'];
            $productRow['total_amount'] = $aFilter['total_amount'];
            $productRow['goods_sales_price'] = $aFilter['goods_sales_price']?$aFilter['goods_sales_price']:0;
            $productRow['discount'] = $aFilter['discount'];
            $productRow['cost_freight'] = $aFilter['cost_freight'];
            $productRow['sale_amount'] = $aFilter['sale_amount'];
            $productRow['cost_amount'] = $aFilter['cost_amount']?$aFilter['cost_amount']:0;
            $productRow['delivery_cost_actual'] = $aFilter['delivery_cost_actual']?$aFilter['delivery_cost_actual']:0;
            $productRow['gross_sales'] = $aFilter['gross_sales'];
            $productRow['gross_sales_rate'] = $aFilter['gross_sales_rate'];
            $productRow['order_create_time'] = date('Y-m-d H:i:s',$aFilter['order_create_time']);
            $productRow['paytime'] = $aFilter['paytime']?date('Y-m-d H:i:s',$aFilter['paytime']):'';
            $productRow['ship_time'] = date('Y-m-d H:i:s',$aFilter['ship_time']);
            $productRow['branch_id'] = $branch[$aFilter['branch_id']];
            $productRow['member_id'] = $members[$aFilter['member_id']];
            $productRow['additional_costs'] = $aFilter['additional_costs'];
            $productRow['payment'] = $aFilter['payment'];
            $productRow['delivery_id'] = $deliverys[$aFilter['delivery_id']];
            $productRow['ship_area'] = $aFilter['ship_area'];
            $productRow['mark_type'] = $this->mark_type[$orders[$aFilter['order_id']]['mark_type']];
            $productRow['logi_no'] = $aFilter['logi_no'];

            $exptmp_data = array();
            foreach (explode(',', $fields) as $key => $col) {
                if(isset($productRow[$col])){
                    $productRow[$col] = mb_convert_encoding($productRow[$col], 'GBK', 'UTF-8');
                    $exptmp_data[] = $productRow[$col];
                }
                else
                {
                    $exptmp_data[]    = '';
                }
            }

            $data['content']['main'][] = implode(',', $exptmp_data);
        }

        return $data;
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
}
