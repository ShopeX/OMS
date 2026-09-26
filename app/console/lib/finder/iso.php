<?php
class console_finder_iso{

   function __construct($app)
    {
        if($_GET['io']){
            $this->column_iso_status = '入库状态';
            $this->detail_basic = '入库单详情';
            $this->detail_item = '入库单明细';
        }else{
            $this->column_iso_status = '出库状态';
            $this->detail_basic = '出库单详情';
           $this->detail_item = '出库单明细';
        }
		if($_GET['act']=='allocate_iostocklist'){
            
            unset($this->column_edit);
            
        }
        
    }
    var $detail_basic = "出库单详情";
    var $detail_item = "出库单明细";

    var $addon_cols ='iso_status,defective_status,iso_id,check_status,receive_status,product_cost,iso_price,source';
    function column_iso_status($row){
        if($_GET['io']){
            $title = '入库';
        }else{
            $title = '出库';
        }
       
        $iso_status = $row[$this->col_prefix.'iso_status'];
        
        if($iso_status == '1'){
            $io_title = '未'.$title;
        }else if($iso_status == '2'){
            $io_title = '部分'.$title;
        }else if($iso_status == '3'){
            $io_title = '全部'.$title;
        }else if ($iso_status == '4'){
            $io_title = '取消'.$title;
        }
        
     	  return $io_title;
    }

    public $column_applynum = '申请数量';
    public $column_applynum_width = '100';
    public function column_applynum($row, $list){
        $total = $this->_getTotal($row, $list);
        return $total['applynum'];
    }
    public $column_applymoney = '申请金额';
    public $column_applymoney_width = '100';
    public function column_applymoney($row, $list){
        $total = $this->_getTotal($row, $list);
        if (!kernel::single('desktop_user')->has_permission('purchase_price')) {
            return '-';
        }
        return '￥' . round($total['applymoney'], 2);
    }
    public $column_actualnum = '实际数量';
    public $column_actualnum_width = '100';
    public function column_actualnum($row, $list){
        $total = $this->_getTotal($row, $list);
        return $total['actualnum'];
    }
    public $column_actualmoney = '实际金额';
    public $column_actualmoney_width = '100';
    public function column_actualmoney($row, $list){
        $total = $this->_getTotal($row, $list);
        if (!kernel::single('desktop_user')->has_permission('purchase_price')) {
            return '-';
        }
        return '￥' . round($total['actualmoney'], 2);
    }

    var $column_edit = '操作';
    var $column_edit_width = '180';
    function column_edit($row){
        $Oiso_items = app::get('taoguaniostockorder')->model('iso_items');
        $string = array();
        $iso_status = $row[$this->col_prefix.'iso_status'];
        $defective_status = $row[$this->col_prefix.'defective_status'];
        $iso_id = $row[$this->col_prefix.'iso_id'];
        $check_status = $row[$this->col_prefix.'check_status'];
        $io = $_GET['io'];
        $finder_id = $_GET['_finder']['finder_id'];
        $finder_vid = $_GET['finder_vid'];
        $filter_data = array();
        $act = $_GET['act'];
        if($_GET['act'] == 'search_iostockorder'){
            $id = $row['iso_id'];
            $button = <<<EOF
        &nbsp;&nbsp;<a href="index.php?app=wms&ctl=admin_eo&act=printeo&p[0]=$id&t=$io&finder_vid=$finder_vid&finder_id=$finder_id" target="_bank" class="lnk">打印</a>
EOF;
            return $button;
        }
        if ($_isoST)
        foreach ($_isoST as $key=>$v){
            if (preg_match("/^_+/i",$key)) continue;
            $filter_data[$key] = $v;
        }
        $filter = urlencode(serialize($filter_data));
        
        $button = <<<EOF
            <a class="lnk" href="index.php?app=console&ctl=admin_iostockorder&act=doDefective&p[0]=$iso_id&p[1]=1&filter=$filter&_finder[finder_id]=$finder_id&finder_id=$finder_id" target="_blank">残损确认</a>
EOF;
        $button1 = <<<EOF
            <a class="lnk" href="index.php?app=console&ctl=admin_iostockorder&act=difference&p[0]=$iso_id&p[1]=1&filter=$filter&_finder[finder_id]=$finder_id&finder_id=$finder_id" target="_blank">差异查看</a>
EOF;
        $button2 = <<<EOF
            <a href="index.php?app=wms&ctl=admin_iostockorder&act=iostockorder_confirm&p[0]=$iso_id&p[1]=1&filter=$filter&find_id=$finder_id" target="_blank">入库确认</a>
            
EOF;
        $button3 = <<<EOF
            <a href="index.php?app=wms&ctl=admin_iostockorder&act=iostockorder_confirm&p[0]=$iso_id&p[1]=0&filter=$filter&find_id=$finder_id" target="_blank">出库确认</a>
           
EOF;
    $edit_button = <<<EOF
    <a href="index.php?app=console&ctl=admin_iostockorder&act=iostock_edit&p[0]=$iso_id&p[1]=$io&p[2]=$act&filter=$filter&finder_id=$finder_id" target="_blank">编辑</a>
EOF;
    $confirm_button = <<<EOF
    <a href="index.php?app=console&ctl=admin_iostockorder&act=check&p[0]=$iso_id&p[1]=$io&p[2]=$act&filter=$filter&finder_id=$finder_id" target="_blank">审核</a>
EOF;
    $cancel_button = <<<EOF
    <span class="lnk" onclick="new Dialog('index.php?app=console&ctl=admin_iostockorder&act=cancel&p[0]=$iso_id&p[1]=$io&p[2]=$act&filter=$filter&finder_id=$finder_id',{title:'取消出库',width:500,height:250})">取消</span>
EOF;
    $cancel_button = <<<EOF
    <a class="lnk" target="dialog::{width:1000,height:500,title:'取消出库'}" href="index.php?app=console&ctl=admin_iostockorder&act=cancel&p[0]=$iso_id&p[1]=$io&p[2]=$act&filter=$filter&finder_id=$finder_id">取消</a>
EOF;
        $button4 = <<<EOF
            <a href="index.php?app=console&ctl=admin_iostockorder&act=iostockConfirm&p[0]=$iso_id&p[1]=0&filter=$filter&find_id=$finder_id" target="_blank">出库确认</a>
           
EOF;
        $button5 = <<<EOF
            <a class="lnk" href="index.php?app=console&ctl=admin_iostockorder&act=addSame&p[0]=$iso_id&p[1]=$io&_finder[finder_id]=$finder_id&finder_id=$finder_id" target="_blank">新建相似</a>
EOF;
    //调拨入库 取消操作 生成入库单 打回原始仓库
    if($io == '1' && $act == 'allocate_iostock'){
        $cancel_button = <<<EOF
    <a target="_blank" href="index.php?app=console&ctl=admin_iostockorder&act=cancelIostockin&iso_id=$iso_id&finder_id=$finder_id">取消</a>
EOF;
    }

        if ($_GET['app'] == 'console'){
            if($iso_status == '3'){
                if($_GET['io'] && $defective_status=='1'){#残损确认
                    $string[]=$button;
                }
                #查看是否有差异
                $items = $Oiso_items->db->select("SELECT * from sdb_taoguaniostockorder_iso_items where iso_id=".intval($iso_id)." and (`normal_num`!=`nums` || defective_num>0)");
                if ($items){
                    $string[]=$button1;
                }
            }
            
            
            if ($check_status == '1' && $iso_status=='1'){
                //调拨入库隐藏 编辑  并且查看是否进行过调拨取消入库操作
                if($io == '1' && $act == 'allocate_iostock'){
                    //调拨入库页 获取是否已经进行过取消入库的操作
                    $taoguaniostockorder_iso_obj = app::get('taoguaniostockorder')->model('iso');
                    $iso_row = $taoguaniostockorder_iso_obj->dump(array("original_id"=>$iso_id,"type_id"=>11),"iso_bn");
                    $iso_bn_first_letter = substr($iso_row["iso_bn"],0,1);
                }else{
                    $string[]= $edit_button;
                }
                
                //在调拨入库处 已做调拨入库取消后不显示审核按钮
                if($iso_row["iso_bn"] && $iso_bn_first_letter == "X"){
                }else{
                    $string[]= $confirm_button;
                }
            }
            
            #取消
            if ($iso_status<='1'){
                $string[] = $cancel_button;
            }
            if (($row[$this->col_prefix.'receive_status']  & console_const::_FINISH_CODE) && $iso_status=='1'){
                $string[]=$button4;
            }
            if ($_GET['act'] == 'other_iostock' && $row[$this->col_prefix.'source'] == 'local' && !$row['original_bn']) {
                $string[] = $button5;
            }
        }
        
        
        if ($check_status == '2' && $_GET['app'] == 'wms' && ($iso_status < '3')){
            if($_GET['io']){
                $string[]=$button2;
            }else{
                $string[]=$button3;
            }
        }
        if($string)
        return '<span class="c-gray">'.implode('|',$string).'</span>';
    }

    function detail_basic($iso_id){
        $render = app::get('console')->render();
        $isoObj = app::get('taoguaniostockorder')->model('iso');
        //备注追加
        if($_POST){
            $iso_id = $_POST['iso_id'];
            $memo = htmlspecialchars($_POST['memo']);
            //取出原备注信息
            $oldmemo = $isoObj->dump(array('iso_id'=>$iso_id), 'memo');
            $oldmemo= $oldmemo['memo'];
            if ($oldmemo) $memo = $oldmemo.$memo.'；';
            $iso['memo'] = $memo;
            $iso['iso_id'] = $iso_id;
            $isoObj->save($iso);
        }
        
        $suObj = app::get('purchase')->model('supplier');
        $brObj = app::get('ome')->model('branch');
        $iso = $isoObj->dump($iso_id,'*',array('iso_items'=>array('*')));
        $oExtrabranch = app::get('ome')->model('extrabranch');
        if($iso['type_id'] == 4 || $iso['type_id'] == 40){
            $extrabranch_id = $iso['extrabranch_id'];
            
            //$extrabranch = $oExtrabranch->dump($extrabranch_id,'name');
        }else{
            $extrabranch_id = $iso['extrabranch_id'];
            
            $extrabranch = $oExtrabranch->dump($extrabranch_id,'name');
        }

        $total_num=0;
        if ($iso['iso_items'])
        foreach($iso['iso_items'] as $k=>$v){
            $total_num+=$v['nums'];
        }
        $su = $suObj->dump($iso['supplier_id'],'name');
        $br = $brObj->dump($iso['branch_id'], 'name');
        $iso['iso_id']   = $iso_id;
        $iso['branch_name']   = $br['name'];
        $iso['supplier_name'] = $su['name'];
        $iso['create_time'] = date("Y-m-d", $iso['create_time']);
        $iso['total_num']     = $total_num;
        $iso['memo'] = $iso['memo'];
        $iso['extrabranch_name'] = $extrabranch['name'];
        $amount = $iso['product_cost'] + $iso['iso_price'];
        $showPurchasePrice = true;
        if (!kernel::single('desktop_user')->has_permission('purchase_price')) {
            $showPurchasePrice = false;
        }
        $render->pagedata['show_purchase_price'] = $showPurchasePrice;
        $render->pagedata['iso'] = $iso;
        $render->pagedata['amount'] = $amount;

        if($_GET['io']){
            return $render->fetch("admin/iostock/instock_detail.html");
        }else{
            return $render->fetch("admin/iostock/outstock_detail.html");
        }
    }

    function detail_item($iso_id)
    {
        $basicMaterialLib    = kernel::single('material_basic_material');
        
        $render = app::get('console')->render();
        $isoObj  = app::get('taoguaniostockorder')->model('iso');
        $isoItemsDetailObj  = app::get('taoguaniostockorder')->model('iso_items_detail');
        
        $iso = $isoObj->dump($iso_id,'iso_id',array('iso_items'=>array('*')));
        $detailList = $isoItemsDetailObj->getList('product_id,nums,batch_code,product_date,expire_date,extendpro,box_no',['iso_id'=>$iso_id]);
        $newDetailList = [];
        $batch = false;//是否有批次
        if($detailList){
            foreach ($detailList as $k => $item) {
                if($item['extendpro']){
                    $item['extendpro'] = unserialize($item['extendpro']);
                }
                $newDetailList[$item['product_id']][] = $item;
            }
            $batch = true;
        }

        foreach($iso['iso_items'] as $k=>$order_item)
        {
            $product    = $basicMaterialLib->getBasicMaterialExt($order_item['product_id']);
            
            $order_item['spec_info'] = $product['specifications'];
            $order_item['barcode'] = $product['barcode'];
            $order_item['product_name'] = $product['material_name'];
            $order_item['unit'] = isset($product['unit']) ? $product['unit'] : '';
            $order_item['detail'] = isset($newDetailList[$order_item['product_id']]) ? $newDetailList[$order_item['product_id']] : [];
            if (empty($order_item['sn']) && !empty($order_item['sn_list'])) {
                $snList = json_decode($order_item['sn_list'], true);
                if (is_array($snList)) {
                    $order_item['sn'] = implode(',', $snList);
                }
            }
            $iso['iso_items'][$k] = $order_item;
        }
       
        $render->pagedata['batch'] = $batch;
        $render->pagedata['iso_items'] = $iso['iso_items'];
        if($_GET['io']){
            return $render->fetch("admin/iostock/instock_item.html");
        }else{
            return $render->fetch("admin/iostock/outstock_item.html");
        }
    }

    public $detail_serial = "唯一码列表";
    public function detail_serial($iso_id)
    {
        $render = app::get('console')->render();
        $page = max(1, intval($_GET['serial_page'] ?? 1));
        $render->pagedata = array_merge(
            $render->pagedata,
            console_adjust_serial::preparePagedIsoSerialListViewData($iso_id, $page, 50)
        );

        return $render->fetch('admin/iostock/serial_list.html');
    }

    private $totalNumMoney;
    private function _getTotal($row, $list){
        if(!$this->totalNumMoney) {
            $isoId = [];
            foreach ($list as $v) {
                $isoId[] = $v['iso_id'];
            }
            $sql = 'select iso_id, 
                        sum(nums) applynum, 
                        sum(nums * price) applymoney, 
                        sum(normal_num+defective_num) actualnum,
                        sum((normal_num+defective_num) * price) actualmoney
                        from sdb_taoguaniostockorder_iso_items
                        where iso_id in ("'.implode('","', $isoId).'")
                        group by iso_id';
            $rows = kernel::database()->select($sql);
            $this->totalNumMoney = array_column($rows, null, 'iso_id');
        }
        return $this->totalNumMoney[$row['iso_id']];
    }
    
    var $column_product_cost = '商品总额';
    var $column_product_cost_width = "75";
    function column_product_cost($row)
    {
        $product_cost         = '￥' . $row[$this->col_prefix . 'product_cost'];
        if (!kernel::single('desktop_user')->has_permission('purchase_price')) {
            $product_cost = '-';
        }
        return $product_cost;
    }
    
    var $column_iso_price = '出入库费用';
    var $column_iso_price_width = "75";
    function column_iso_price($row)
    {
        $iso_price         = '￥' . $row[$this->col_prefix . 'iso_price'];
        if (!kernel::single('desktop_user')->has_permission('purchase_price')) {
            $iso_price = '-';
        }
        return $iso_price;
    }
    
    public $detail_oplog = "操作记录";
    public function detail_oplog($id){
        $render = app::get('console')->render();
        $opObj  = app::get('ome')->model('operation_log');
        $logdata = $opObj->read_log(array('obj_id'=>$id,'obj_type'=>'iso@taoguaniostockorder'), 0, -1);
        $find_id = $_GET['_finder']['finder_id'];
        foreach($logdata as $k=>$v){
            $logdata[$k]['operate_time'] = date('Y-m-d H:i:s',$v['operate_time']);
            $is_json = json_decode($v['memo']);
            $log_id = $v['log_id'];
            if($is_json === null){
                $memo = $v['memo'];
            }else{
                $memoData = json_decode($v['memo'],true);
    
                $button = <<<EOF
            <a class="lnk" href="index.php?app=console&ctl=admin_iostockorder&act=detailHistory&p[0]=$log_id&_finder[finder_id]=$find_id&finder_id=$find_id" target="_blank">点击查看</a>
EOF;
                $memo = $memoData['title'].$button;
            }
    
            $logdata[$k]['memo'] = $memo;
        }
        $render->pagedata['log'] = $logdata;
        return $render->fetch('admin/oplog.html');
    }

    public $detail_useful = "有效期记录";
    public function detail_useful($iso_id){
       
        $isoMdl  = app::get('taoguaniostockorder')->model('iso');
        $isos = $isoMdl->dump(array('iso_id'=>$iso_id,'iso_bn'));
        $render = app::get('console')->render();
        $usefulMdl = app::get('console')->model('useful_life_log');

        $usefuls = $usefulMdl->getlist('*',array('original_bn'=>$isos['iso_bn']));

        $render->pagedata['usefuls'] = $usefuls;

        return $render->fetch("admin/useful/iso.html");
    }
}
