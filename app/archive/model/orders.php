<?php
class archive_mdl_orders extends dbeav_model{
     var $has_many = array(
        'order_objects' => 'order_objects',
    );
    //是否有导出配置
    var $has_export_cnf = true;

    var $defaultOrder = array('createtime DESC ,order_id DESC');
    
    var $export_name = '归档订单';

    /**
     * 须加密字段
     *
     * @var string
     **/
    private $__encrypt_cols = array(
        'ship_name'   => 'simple',
        'ship_mobile'    => 'phone',
        'ship_tel' => 'phone',
        'ship_addr'     => 'simple',
    );

     function _filter($filter,$tableAlias=null,$baseWhere=null){
        
         $where = " 1 ";

        if (isset($filter['flag']) && $filter['flag'] == '0') {
            return ;
        }

         if($filter['search_filter']){
            $field_map = array(
                'receive_name' => 'ship_name',
                'mobile'        => 'ship_mobile',
                'tel'           => 'ship_tel'
            );
             $field = $field_map[$filter['search_filter']];
             $encrypt_type = $this->__encrypt_cols[$field];
             if ($encrypt_type) {
                 $searchtype = 'nequal';

                 if ($searchtype!='nequal' && in_array($encrypt_type,array('search','nick','receiver_name'))) {
                     $encryptVal = kernel::single('ome_security_factory')->search($filter['search_filter_value'],$encrypt_type);
                 } else {
                     $encryptVal = kernel::single('ome_security_factory')->encryptPublic($filter['search_filter_value'],$encrypt_type);
                 }

                 $originalVal      = utils::addslashes_array($filter['search_filter_value']);
                 $encryptVal = utils::addslashes_array($encryptVal);
                 switch ($searchtype) {
                     case 'has':
                         $baseWhere[] = "({$field} LIKE '%".$originalVal."%' || {$field} LIKE '%".$encryptVal."%')";
                         break;
                     case 'head':
                         $baseWhere[] = "({$field} LIKE '".$originalVal."%' || {$field} LIKE '%".$encryptVal."%')";
                         break;
                     case 'foot':
                         $baseWhere[] = "({$field} LIKE '%".$originalVal."' || {$field} LIKE '%".$encryptVal."%')";
                         break;
                     default:
                         $baseWhere[] = "{$field} IN('".$originalVal."','".$encryptVal."')";
                         break;
                 }

                 unset($filter['search_filter']);
             }
         }

        if ($filter['search_filter'] && $filter['search_filter_value']) {
            switch ($filter['search_filter']) {
                case 'order_bn':
                    $where.=" AND order_bn='".$filter['search_filter_value']."'";
                    break;
                case 'logi_no':

                    $order_id = [0];
                    $delivery = app::get('archive')->model('delivery')->db_dump([
                        'logi_no' => $filter['search_filter_value'],
                    ],'delivery_id');
                    if ($delivery) {
                        $deliveryOrder = app::get('archive')->model('delivery_order')->getList('order_id',array(
                            'delivery_id' => $delivery['delivery_id'],
                        ));
                        if ($deliveryOrder) {
                            $order_id = array_column($deliveryOrder, 'order_id');
                        }
                    }

                    $where.=" AND order_id IN(".implode(',', $order_id).")";
                    
                    break;
                case 'member_name':
                    $member_id = '';
                    $memberObj = app::get('ome')->model("members");
                    $rows = $memberObj->getList('member_id',array('uname|head'=>$filter['search_filter_value']));

                    $memberId[] = 0;
                    foreach($rows as $row){
                        $memberId[] = $row['member_id'];
                    }
                    $where .= '  AND member_id IN ('.implode(',', $memberId).')';
                    
                    break;
                case 'receive_name':
                    $where.=" AND ship_name='".$filter['search_filter_value']."'";
                    break;
                case 'mobile':
                    $where.=" AND ship_mobile='".$filter['search_filter_value']."'";
                    break;
                case 'tel':
                    $where.=" AND ship_tel='".$filter['search_filter_value']."'";
                    break;
                case 'delivery_bn':
                    $order_ids = kernel::single('archive_interface_delivery')->getOrderBydeliverybn($filter['search_filter_value']);
                    if ($order_ids){
                        $where.=" AND order_id in (".implode(',',$order_ids).")";
                    }
                    break;
            }
        }
       
         if ($filter['time_from'] && $filter['time_to']) {
            $time_from = strtotime($filter['time_from']);
            $time_to = strtotime($filter['time_to']);

            //开始时间
            $start_time = strtotime(date("Y-m-1 00:00:00",$time_from));
            $where.=" AND createtime >='".$start_time."'";

            //获取选择时间范围内的最后一天
            if ( date('Ym',$time_to) >= date('Ym') ){
                $end_time = strtotime(date("Y-m-j 23:59:59",time()-24*60*60));
            }else{
                $end_time = strtotime(date('Y-m-t 23:59:59', $time_to));//1351612799
            }
            $where.=" AND createtime <='".$end_time."'";
         }
        if ($filter['shop_id']){
            $where.=" AND shop_id ='".$filter['shop_id']."'";
        }else{
            unset($filter['shop_id']);
        }
        return $where ." AND ".parent::_filter($filter,$tableAlias,$baseWhere);
       
    }

    function countlist($filter=null){
        $filter['flag'] = '1';
        
        $sql ="SELECT COUNT(order_id) AS _count FROM sdb_archive_orders WHERE".$this->_filter($filter);

        $archive = $this->db->selectrow($sql);
        return $archive['_count'];
    }

    public function getexportdetail($fields,$filter,$offset=0,$limit=1,$has_title=false)
    {
        //获取订单号信息
        $orders = $this->db->select("SELECT order_id,order_bn FROM sdb_archive_orders WHERE order_id in(".implode(',', $filter['order_id']).")");
        $aOrder = array();
        if($orders){
            foreach($orders as $order){
                $aOrder[$order['order_id']] = $order['order_bn'];
            }
        }

        $pkgLib = kernel::single('archive_service_objtype_pkg');
        $lkbLib = kernel::single('archive_service_objtype_lkb');
        $pkoLib = kernel::single('archive_service_objtype_pko');
        $row_num = 1;
        foreach($filter['order_id'] as $oid){
            $objects = $this->db->select("SELECT * FROM sdb_archive_order_objects WHERE order_id =".$oid);
            if ($objects){
                foreach ($objects as $obj){
                    $obj_type = strtolower($obj['obj_type']);
                    if ($obj_type == 'pkg'){
                        $item_data = $pkgLib->process($obj);
                        if ($item_data){
                            foreach ($item_data as $itemv){
                                $orderObjRow = $this->get_order_obj_row_arr($obj_type,$aOrder[$obj['order_id']],$itemv);
                                $data[$row_num] = implode(',', $orderObjRow );
                                $row_num++;
                            }
                        }
                    }elseif($obj_type == 'lkb'){
                        $item_data = $lkbLib->process($obj);
                        if ($item_data){
                            foreach ($item_data as $itemv){
                                $orderObjRow = $this->get_order_obj_row_arr($obj_type,$aOrder[$obj['order_id']],$itemv);
                                $data[$row_num] = implode(',', $orderObjRow );
                                $row_num++;
                            }
                        }
                    }elseif($obj_type == 'pko'){
                        $item_data = $pkoLib->process($obj);
                        if($item_data){
                            foreach ($item_data as $itemv){
                                $orderObjRow = $this->get_order_obj_row_arr($obj_type,$aOrder[$obj['order_id']],$itemv);
                                $data[$row_num] = implode(',', $orderObjRow );
                                $row_num++;
                            }
                        }
                    }else {
                        $aOrder['order_items'] = $this->db->select("SELECT * FROM sdb_archive_order_items WHERE obj_id=".$obj['obj_id']." AND order_id =".$obj['order_id']);
                        $aOrder['order_items'] = ome_order_func::add_items_colum($aOrder['order_items']);
                        $orderRow = array();
                        $orderObjRow = array();
                        $k = 0;
                        if ($aOrder['order_items'])
                        foreach( $aOrder['order_items'] as $itemk => $itemv ){
                            $orderObjRow = $this->get_order_obj_row_arr($obj_type,$aOrder[$obj['order_id']],$itemv);
                            $data[$row_num] = implode(',', $orderObjRow );
                            $row_num++;
                        }
                    }
                }
            }
        }

        //明细标题处理
        if($data && $has_title){
            $title = array(
                '*:订单号',
                '*:商品货号',
                '*:商品名称',
                '*:购买单位',
                '*:商品规格',
                '*:购买数量',
                '*:商品原价',
                '*:销售价',
                '*:商品优惠金额',
                '*:商品类型',
                '*:商品品牌',
            );

            foreach ((array)$title as $key => $value) {
                $title[$key] = mb_convert_encoding($value, 'GBK', 'UTF-8');
            }

            $data[0] = implode(',', $title);
        }

        ksort($data);
        return $data;
    }
    
    //获取导出明细中的行内容
    private function get_order_obj_row_arr($obj_type,$order_bn,$itemv){
        $orderObjRow = array(
            '*:订单号' => mb_convert_encoding($order_bn, 'GBK', 'UTF-8'),
            '*:商品货号' => mb_convert_encoding("\t".$itemv['bn'], 'GBK', 'UTF-8'),
            '*:商品名称' => mb_convert_encoding("\t".str_replace("\n"," ",$itemv['name']), 'GBK', 'UTF-8'),
            '*:购买单位' => mb_convert_encoding($itemv['unit'], 'GBK', 'UTF-8'),
        );
        if($obj_type == "pkg" || $obj_type == "lkb" || $obj_type == "pko"){
            $orderObjRow['*:商品规格'] = $itemv['spec_info'] ? mb_convert_encoding(str_replace("\n"," ",$itemv['spec_info']), 'GBK', 'UTF-8'):"-";
        }else{ //普通、赠品
            $addon = unserialize($itemv['addon']);
            $spec_info = null;
            if(!empty($addon)){
                foreach($addon as $val){
                    foreach ($val as $v){
                        $spec_info[] = $v['value'];
                    }
                }
            }
            $orderObjRow['*:商品规格'] = $spec_info ? mb_convert_encoding(implode('||', $spec_info), 'GBK', 'UTF-8'):'-';
        }
        $orderObjRow['*:购买数量'] = $itemv['nums'];
        $orderObjRow['*:商品原价'] = $itemv['price'];
        $orderObjRow['*:销售价'] = $itemv['sale_price'] / $itemv['nums'];
        $orderObjRow['*:商品优惠金额'] = $itemv['pmt_price'];
        if($obj_type == "pkg" || $obj_type == "lkb" || $obj_type == "pko"){
            $orderObjRow['*:商品规格'] = $itemv['spec_info'] ? mb_convert_encoding(str_replace("\n"," ",$itemv['spec_info']), 'GBK', 'UTF-8'):"-";
        }else{ //普通、赠品
            $_typeName = app::get('ome')->model('orders')->getTypeName($itemv['product_id']);
            $orderObjRow['*:商品类型'] = mb_convert_encoding($_typeName['type_name'], 'GBK', 'UTF-8');
            $orderObjRow['*:商品品牌'] = mb_convert_encoding($_typeName['brand_name'], 'GBK', 'UTF-8');
        }
        return $orderObjRow;
    }

    /**
     * 订单暂停
     */
    function pauseOrder($order_id, $must_update = 'false'){

       
    }

     function renewOrder($order_id){
     }

    function save(&$data,$mustUpdate = null){
         //外键 先执行save
        $this->_save_parent($data,$mustUpdate);
        $plainData = $this->sdf_to_plain($data);
        if(!$this->db_save($plainData,$mustUpdate )) return false;

        $order_id = $plainData['order_id'];
        if(isset($data['order_objects'])){
            foreach($data['order_objects'] as $k=>$v){
                if(isset($v['order_items'])){
                    foreach($v['order_items'] as $k2=>$item){
                        $data['order_objects'][$k]['order_items'][$k2]['order_id'] = $order_id;
                    }
                }else{
                    break;
                }
            }
        }

        if( !is_array($this->idColumn) ){
            $data[$this->idColumn] = $plainData[$this->idColumn];
            $this->_save_depends($data,$mustUpdate );
        }
        $plainData = null; //内存用完就放
        return true;
     }

    /**
     * 归档订单导出列表扩展字段
     */
    function export_extra_cols(){
        return array(
            'column_logi_name' => array('label'=>'物流公司','width'=>'100','func_suffix'=>'logi_name'),
            'column_logi_no' => array('label'=>'物流单号','width'=>'100','func_suffix'=>'logi_no'),
        );
    }

    /**
     * 扩展字段格式化
     */
    function export_extra_logi_name($rows){
        return kernel::single('ome_exportextracolumn_archive_loginame')->process($rows);
    }
    function export_extra_logi_no($rows){
        return kernel::single('ome_exportextracolumn_archive_logino')->process($rows);
    }

    public function insert(&$data)
    {
        foreach ($this->__encrypt_cols as $field => $type) {
            if (isset($data[$field])) {
                $data[$field] = (string) kernel::single('ome_security_factory')->encryptPublic($data[$field],$type);
            }
        }

        return parent::insert($data);
    }

    public function update($data,$filter=array(),$mustUpdate = null)
    {
        foreach ($this->__encrypt_cols as $field => $type) {
            if (isset($data[$field])) {
                $data[$field] = (string) kernel::single('ome_security_factory')->encryptPublic($data[$field],$type);
            }
        }

        return parent::update($data,$filter,$mustUpdate);
    }

    public function getList($cols='*', $filter=array(), $offset=0, $limit=-1, $orderType=null)
    {
        $data = parent::getList($cols,$filter,$offset,$limit,$orderType);

        foreach ((array) $data as $key => $value) {
            foreach ($this->__encrypt_cols as $field => $type) {
                if (isset($value[$field])) {
                    $data[$key][$field] = (string) kernel::single('ome_security_factory')->decryptPublic($value[$field],$type);
                }
            }
        }

        return $data;
    }

    /**
     * 确认收货时间：库内多为时间戳（char），列表展示转为可读时间
     */
    public function modifier_take_delivery_time($col, $list = null, $row = null)
    {
        return ($col && is_numeric($col)) ? date('Y-m-d H:i:s', $col) : '-';
    }

    public function modifier_ship_name($ship_name,$list,$row)
    {
        if ($this->is_export_data) {
            if ('false' != app::get('ome')->getConf('ome.sensitive.exportdata.encrypt')) {
                return kernel::single('ome_view_helper2')->modifier_ciphertext($ship_name,'order','ship_name');
            }
            return $ship_name;
        }

        $is_encrypt = kernel::single('ome_security_hash')->check_encrypt($ship_name);

        if (!$is_encrypt) return $ship_name;

        $base_url = kernel::base_url(1);$order_id = $row['order_id'];
        $encryptShipName = kernel::single('ome_view_helper2')->modifier_ciphertext($ship_name,'order','ship_name');
$return =<<<HTML
<a class="data-hide" href="javascript:void(0);" onclick="Ex_Loader('security',function(){new Security({url:'index.php?app=ome&ctl=admin_order&act=showSensitiveData&p[0]={$order_id}',clickElement:\$(event.target)}).desHtml(\$(event.target).getNext()); });"></a><span><span sensitive-field="ship_name">{$encryptShipName}</span></span>
HTML;
        return $ship_name?$return:$ship_name;
    }

    public function modifier_ship_tel($tel,$list,$row)
    {
        if ($this->is_export_data) {
            if ('false' != app::get('ome')->getConf('ome.sensitive.exportdata.encrypt')) {
                return kernel::single('ome_view_helper2')->modifier_ciphertext($tel,'order','ship_tel');
            }
            return $tel;
        }

        $is_encrypt = kernel::single('ome_security_hash')->check_encrypt($tel);

        if (!$is_encrypt) return $tel;

        $base_url = kernel::base_url(1);$order_id = $row['order_id'];
        $encryptTel = kernel::single('ome_view_helper2')->modifier_ciphertext($tel,'order','ship_tel');
$return =<<<HTML
<a class="data-hide" href="javascript:void(0);" onclick="Ex_Loader('security',function(){new Security({url:'index.php?app=ome&ctl=admin_order&act=showSensitiveData&p[0]={$order_id}',clickElement:\$(event.target)}).desHtml(\$(event.target).getNext()); });"></a><span><span sensitive-field="ship_tel">{$encryptTel}</span></span>
HTML;
        return $tel?$return:$tel;
    }

    public function modifier_ship_mobile($mobile,$list,$row)
    {
        if ($this->is_export_data) {
            if ('false' != app::get('ome')->getConf('ome.sensitive.exportdata.encrypt')) {
                return kernel::single('ome_view_helper2')->modifier_ciphertext($mobile,'order','ship_mobile');
            }
            return $mobile;
        }

        $is_encrypt = kernel::single('ome_security_hash')->check_encrypt($mobile);

        if (!$is_encrypt) return $mobile;

        $base_url = kernel::base_url(1);$order_id = $row['order_id'];
        $encryptMobile = kernel::single('ome_view_helper2')->modifier_ciphertext($mobile,'order','ship_mobile');
$return =<<<HTML
<a class="data-hide" href="javascript:void(0);" onclick="Ex_Loader('security',function(){new Security({url:'index.php?app=ome&ctl=admin_order&act=showSensitiveData&p[0]={$order_id}',clickElement:\$(event.target)}).desHtml(\$(event.target).getNext()); });"></a><span><span sensitive-field="ship_mobile">{$encryptMobile}</span></span>
HTML;
        return $mobile?$return:$mobile;
    }


    public function modifier_ship_addr($ship_addr,$list,$row)
    {
        if ($this->is_export_data) {
            if ('false' != app::get('ome')->getConf('ome.sensitive.exportdata.encrypt')) {
                return kernel::single('ome_view_helper2')->modifier_ciphertext($ship_addr,'order','ship_addr');
            }
            return $ship_addr;
        }

        $is_encrypt = kernel::single('ome_security_hash')->check_encrypt($ship_addr);

        if (!$is_encrypt) return $ship_addr;
        
        $base_url = kernel::base_url(1);$order_id = $row['order_id'];
        $encryptAddr = kernel::single('ome_view_helper2')->modifier_ciphertext($ship_addr,'order','ship_addr');
$return =<<<HTML
<a class="data-hide" href="javascript:void(0);" onclick="Ex_Loader('security',function(){new Security({url:'index.php?app=ome&ctl=admin_order&act=showSensitiveData&p[0]={$order_id}',clickElement:\$(event.target)}).desHtml(\$(event.target).getNext()); });"></a><span><span sensitive-field="ship_addr">{$encryptAddr}</span></span>
HTML;
        return $ship_addr?$return:$ship_addr;
    }
}

?>