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
class wms_mdl_delivery extends dbeav_model{
    public $filter_use_like = true;
    var $has_many = array(
        'delivery_items' => 'delivery_items',
    );
    var $defaultOrder = array('delivery_id',' ASC');
    //是否有导出配置
    var $has_export_cnf = true;
    public $export_name = 'wms发货单';

    /**
     * 须加密字段
     *
     * @var string
     **/
    private $__encrypt_cols = array(
        'ship_name'   => 'simple',
        'ship_tel'    => 'phone',
        'ship_mobile' => 'phone',
        'ship_addr'     => 'simple',
    );

    function __construct($app){
        if($_GET['status'] == '0' || $_GET['status'] == ''){
            $opInfo = kernel::single('ome_func')->getDesktopUser();
            if(app::get('ome')->getConf('delivery.bycreatetime'.$opInfo['op_id']) == 1){
                $this->defaultOrder = array('order_createtime',' ASC');
            }else{
                $this->defaultOrder = array('idx_split',' ASC');
            }
        }else{
            $this->defaultOrder = array('delivery_id',' DESC');
        }
        parent::__construct($app);
    }

    public function _filter($filter,$tableAlias=null,$baseWhere=null){
        $baseWhere = (array) $baseWhere;
        $tPre = ($tableAlias?$tableAlias:'`'.$this->table_name(true).'`').'.';
        $where = '';
        ///////////////////////////
        // 加密处理逻辑 2017/5/5 by cp //
        ///////////////////////////
        foreach ($filter as $key => $value) {
            $pos = strpos($key,'|');
            $field = false !== $pos ? substr($key,0,$pos): $key;

            $encrypt_type = $this->__encrypt_cols[$field];
            if ($encrypt_type) {
                $searchtype = false !== $pos ? substr($key,$pos+1): 'nequal';

                // 京东加密
                $encryptVal360 = kernel::single('erpapi_rpc_hufu')->encrypt($value).kernel::single('ome_security_hash')->get_code();

                // 拼多多加密
                $encryptValPdd = kernel::single('erpapi_rpc_fangzhou')->encrypt($value).kernel::single('ome_security_hash')->get_code();

                if ($searchtype!='nequal' && in_array($encrypt_type,array('search','nick','receiver_name'))) {
                    $encryptVal = kernel::single('ome_security_factory')->search($value,$encrypt_type);

                    $encryptVal360 = kernel::single('ome_security_factory')->search($encryptVal360,$encrypt_type);

                    $encryptValPdd = kernel::single('ome_security_factory')->search($encryptValPdd,$encrypt_type);
                } else {
                    $encryptVal = kernel::single('ome_security_factory')->encryptPublic($value,$encrypt_type);

                    $encryptVal360 = kernel::single('ome_security_factory')->encryptPublic($encryptVal360,$encrypt_type);

                    $encryptValPdd = kernel::single('ome_security_factory')->encryptPublic($encryptValPdd,$encrypt_type);
                }


                $originalVal      = utils::addslashes_array($value);
                $encryptVal = utils::addslashes_array($encryptVal);

                switch ($searchtype) {
                    case 'has':
                        $baseWhere[] = "({$tPre}{$field} LIKE '%".$originalVal."%' || {$tPre}{$field} LIKE '%".$encryptVal."%')";
                        break;
                    case 'head':
                        $baseWhere[] = "({$tPre}{$field} LIKE '".$originalVal."%' || {$tPre}{$field} LIKE '%".$encryptVal."%')";
                        break;
                    case 'foot':
                        $baseWhere[] = "({$tPre}{$field} LIKE '%".$originalVal."' || {$tPre}{$field} LIKE '%".$encryptVal."%')";
                        break;
                    default:
                        $baseWhere[] = "{$tPre}{$field} IN('".$originalVal."','".$encryptVal."','".$encryptVal360."','".$encryptValPdd."')";
                        break;
                }

                unset($filter[$key]);
            }
        }
        if(isset($filter['ship_tel_mobile'])){
            $encryptVal = kernel::single('ome_security_factory')->encryptPublic($filter['ship_tel_mobile'],'phone');
            $encryptVal  = utils::addslashes_array($encryptVal);
            $originalVal = utils::addslashes_array($filter['ship_tel_mobile']);

            $baseWhere[] = "({$tPre}ship_tel IN('".$originalVal."','".$encryptVal."')||{$tPre}ship_mobile IN('".$originalVal."','".$encryptVal."'))";

            unset($filter['ship_tel_mobile']);
        }

        $deliveryObj = app::get('ome')->model("delivery");
        if(isset($filter['extend_delivery_id'])){
            $where .= ' OR delivery_id IN ('.implode(',', $filter['extend_delivery_id']).')';
            unset($filter['extend_delivery_id']);
        }
        if (isset($filter['member_uname'])){
            $memberObj = app::get('ome')->model("members");
            $rows = $memberObj->getList('member_id',array('uname|has'=>$filter['member_uname']));
            $memberId[] = 0;
            foreach($rows as $row){
                $memberId[] = $row['member_id'];
            }
            $where .= '  AND member_id IN ('.implode(',', $memberId).')';
            unset($filter['member_uname']);
        }
        
        //按订单号搜索
        if (isset($filter['order_bn'])){
            // 多订单号查询
            if(strpos($filter['order_bn'], "\n") !== false){
                $filter['order_bn'] = array_unique(array_map('trim', array_filter(explode("\n", $filter['order_bn']))));
            }
            
            $orderObj = app::get('ome')->model("orders");
            $rows = $orderObj->getList('order_id',array('order_bn'=>$filter['order_bn']));
            $orderId[] = 0;
            foreach($rows as $row){
                $orderId[] = $row['order_id'];
            }

            $deliOrderObj = app::get('ome')->model("delivery_order");
            $rows = $deliOrderObj->getList('delivery_id',array('order_id'=>$orderId));
            $deliveryId[] = 0;
            foreach($rows as $row){
                $deliveryId[] = $row['delivery_id'];
            }
            //

            $_delivery_bn = $this->_getdelivery_bn($deliveryId);

            $where .= '  AND outer_delivery_bn IN (\''.implode('\',\'', $_delivery_bn).'\')';
            unset($filter['order_bn']);
        }
        
        if(isset($filter['no_logi_no']) && $filter['no_logi_no'] == true){
            $rows = $this->db->select("select delivery_id from sdb_wms_delivery_bill where logi_no = '' or logi_no is null");
            $deliveryId[] = 0;
            foreach($rows as $row){
                $deliveryId[] = $row['delivery_id'];
            }
            $where .= '  AND delivery_id IN ('.implode(',', $deliveryId).')';
            unset($filter['no_logi_no']);
        }

        if(isset($filter['product_bn']) && $filter['product_bn']){
            $where .= '  AND bnsContent like \'%'.utils::addslashes_array($filter['product_bn']).'%\'';
            unset($filter['product_bn']);
        }
        if(isset($filter['product_barcode'])){
            $itemsObj = $this->app->model("delivery_items");
            $rows = $itemsObj->getDeliveryIdByPbarcode($filter['product_barcode']);
            $deliveryId[] = 0;
            foreach($rows as $row){
                $deliveryId[] = $row['delivery_id'];
            }
            //$_delivery_bn = $this->_getdelivery_bn($deliveryId);
            $where .= '  AND delivery_id IN (\''.implode('\',\'', $deliveryId).'\')';

            unset($filter['product_barcode'],$_delivery_bn);
        }
        if(isset($filter['logi_no_ext'])){
            $logObj = $this->app->model("delivery_bill");
            $rows = $logObj->getlist('delivery_id',array('logi_no'=>$filter['logi_no_ext']));
            $deliveryId[] = 0;
            foreach($rows as $row){
                $deliveryId[] = $row['delivery_id'];
            }
            $where .= '  AND delivery_id IN ('.implode(',', $deliveryId).')';
            unset($filter['logi_no_ext']);
        }
        if(isset($filter['addonSQL'])){
            $where .= ' AND '.$filter['addonSQL'];
            unset($filter['addonSQL']);
        }
        if (!empty($filter['delivery_label_code'])) {
            $where .= ' AND EXISTS (
                SELECT 1 FROM sdb_ome_bill_label bl
                WHERE bl.bill_id = '.$tPre.'delivery_id
                AND bl.bill_type = "wms_delivery"
                AND bl.label_code = "'.addslashes($filter['delivery_label_code']).'"
            )';
            unset($filter['delivery_label_code']);
        }
        if (isset($filter['urgent_delivery'])) {
            if ($filter['urgent_delivery'] === 'true' || $filter['urgent_delivery'] === true || $filter['urgent_delivery'] == 1) {
                $where .= ' AND EXISTS (
                    SELECT 1 FROM sdb_ome_bill_label bl
                    WHERE bl.bill_id = '.$tPre.'delivery_id
                    AND bl.bill_type = "wms_delivery"
                    AND bl.label_code = "SOMS_URGENT_SHIP"
                )';
            } else {
                $where .= ' AND NOT EXISTS (
                    SELECT 1 FROM sdb_ome_bill_label bl
                    WHERE bl.bill_id = '.$tPre.'delivery_id
                    AND bl.bill_type = "wms_delivery"
                    AND bl.label_code = "SOMS_URGENT_SHIP"
                )';
            }
            unset($filter['urgent_delivery']);
        }
        if (isset($filter['shop_id_in'])) {
            $where .= ' AND ' . $tPre . 'shop_id in("'.implode('","', $filter['shop_id_in']).'")';
            unset($filter['shop_id_in']);
        }
        if(isset($filter['delivery_ident'])){
            $arr_delivery_ident = explode('_',$filter['delivery_ident']);
            
            $mdl_queue = app::get('ome')->model("print_queue");
            if(count($arr_delivery_ident) == 2){
                $ident_dly = array_pop($arr_delivery_ident);
                $ident = implode('-',$arr_delivery_ident);
                $queueItem = $mdl_queue->findQueueItem($ident,$ident_dly);
                if($queueItem){
                    $where .= '  AND delivery_id ='.$queueItem['delivery_id'].'';
                }else{
                    $where .= '  AND delivery_id IN (0)';
                }
            }else{
                $queue = $mdl_queue->findQueueById($filter['delivery_ident']);
                
                if($queue){
                    $where .= '  AND delivery_id IN ('.implode(',', array_map('current', $queue)).')';
                }else{
                    $where .= '  AND delivery_id IN (0)';
                }
            }

            unset($filter['delivery_ident']);
        }
        if($filter['todo']==1){
            $where .= " AND ((print_status & 1) !=1 or (print_status & 2) !=2 or (print_status & 4) !=4)";
            unset($filter['todo']);
        }
        if($filter['todo']==2){
            $where .= " AND ((print_status & 1) !=1 or (print_status & 4) !=4)";
            unset($filter['todo']);
        }
        if($filter['todo']==3){
            $where .= " AND ((print_status & 2) !=2 or (print_status & 4) !=4)";
            unset($filter['todo']);
        }
        if($filter['todo']==4){
            $where .= " AND (print_status & 4) !=4";
            unset($filter['todo']);
        }

        if (isset($filter['print_finish'])) {
            $where_or = array();
            foreach((array)$filter['print_finish'] as $key=> $value){
                $or = "(deli_cfg='".$key."'";
                switch($value) {
                    case '1_1':
                        $or .= " AND (print_status & 1) =1 and (print_status & 2) =2 ";
                        break;
                    case '1_0':
                        $or .= " AND (print_status & 1) =1 ";
                        break;
                    case '0_1':
                        $or .= " AND (print_status & 2) =2 ";
                        break;
                    case '0_0':
                        break;
                }
                $or .= ')';
                $where_or[] = $or;
            }
            if($where_or){
                $where .= ' AND ('.implode(' OR ',$where_or).')';
            }
            unset($filter['print_finish']);
        }
        if (isset($filter['ext_branch_id'])) {
            if (isset($filter['branch_id'])){
                $filter['branch_id'] = array_intersect((array)$filter['branch_id'],(array)$filter['ext_branch_id']);
                $filter['branch_id'] = $filter['branch_id'] ? $filter['branch_id'] : 'false';
            }else{
                $filter['branch_id'] = $filter['ext_branch_id'];
            }

            unset($filter['ext_branch_id']);
        }
        #客服备注
        if(isset($filter['mark_text']) && $filter['mark_text']){
            $mark_text = utils::addslashes_array($filter['mark_text']);

            $sql = "SELECT do.delivery_id FROM sdb_ome_delivery_order do JOIN sdb_ome_orders o ON do.order_id=o.order_id  and o.process_status='splited' and  o.mark_text like "."'%{$mark_text}%'";
            $_rows = $this->db->select($sql);
            if(!empty($_rows)){
                foreach($_rows as $_orders){
                    $_delivery[] = $_orders['delivery_id'];
                }
                $_delivery_bn = $this->_getdelivery_bn($_delivery);
                $where .= '  AND outer_delivery_bn IN (\''.implode('\',\'', $_delivery_bn).'\')';
                unset($filter['mark_text'],$_delivery,$_delivery_bn);
            }

        }
        #买家留言
        if(isset($filter['custom_mark'])){
            $custom_mark = utils::addslashes_array($filter['custom_mark']);
            $sql = "SELECT do.delivery_id FROM sdb_ome_delivery_order do JOIN sdb_ome_orders o ON do.order_id=o.order_id  and o.process_status='splited' and  o.custom_mark like "."'%{$custom_mark}%'";
            $_rows = $this->db->select($sql);
            if(!empty($_rows)){
                foreach($_rows as $_orders){
                    $_delivery[] = $_orders['delivery_id'];
                }
                $_delivery_bn = $this->_getdelivery_bn($_delivery);

                $where .= '  AND outer_delivery_bn IN (\''.implode('\',\'', $_delivery_bn).'\')';
                unset($filter['custom_mark'],$_delivery,$_delivery_bn);
            }

        }
        if (isset($filter['stock_status'])) {
            if ($filter['stock_status'] == 'true') {
                $where .= " AND (print_status & 1) =1";
            }else{
                $where .= " AND (print_status & 1) !=1";
            }
            unset($filter['stock_status']);
        }
        if (isset($filter['deliv_status'])) {
            if ($filter['deliv_status']=='true') {
                $where .= " AND (print_status & 2) =2";
            }else{
                $where .= " AND (print_status & 2) !=2";
            }
            unset($filter['deliv_status']);
        }
        if (isset($filter['expre_status'])) {
            if ($filter['expre_status']=='true') {
                $where .= " AND (print_status & 4) =4";
            }else{
                $where .= " AND (print_status & 4) !=4";
            }
            unset($filter['expre_status']);
        }

        //订单标记
        if($filter['order_label']){
            $ordLabelObj = app::get('ome')->model('bill_label');
            $tempData = $ordLabelObj->getList('bill_id', array('label_id'=>$filter['order_label'], 'bill_type'=>'wms_delivery'));
            if($tempData){
                $orderId = array();
                foreach ($tempData as $tempKey => $tempVal)
                {
                    $temp_order_id = $tempVal['bill_id'];
                    
                    $orderId[$temp_order_id] = $temp_order_id;
                }
                
                $where .= ' AND delivery_id IN ('. implode(',', $orderId) .')';
            }else{
                $where .= ' AND delivery_id = -1';
            }
            
            unset($filter['order_label'], $tempData);
        }

        return parent::_filter($filter,$tableAlias,$baseWhere).$where;
    }

    function searchOptions(){
        $parentOptions = parent::searchOptions();
        $childOptions = array(
            'order_bn'=>app::get('base')->_('订单号'),
            'delivery_bn'=>app::get('base')->_('发货单号'),
            'member_uname'=>app::get('base')->_('用户名'),
            'ship_name'=>app::get('base')->_('收货人'),
            'ship_tel_mobile'=>app::get('base')->_('联系电话'),
            'product_bn'=>app::get('base')->_('货号'),
            'product_barcode'=>app::get('base')->_('条形码'),
            'delivery_ident'=>app::get('base')->_('打印批次号'),
            'outer_delivery_bn'=>app::get('base')->_('外部发货单号'),
            'logi_no_ext'=>app::get('base')->_('物流单号'),
        );

        return array_merge($childOptions,$parentOptions);
    }

    public function count_logi_no($filter=null){
        if($filter['logi_no'] == 'NULL'){
            unset($filter['logi_no']);
            $row = $this->db->select('SELECT count(*) as _count FROM `'.$this->table_name(1).'` WHERE '.$this->_filter($filter).' AND `logi_no` IS NULL');
        }else{
            $row = $this->db->select('SELECT count(*) as _count FROM `'.$this->table_name(1).'` WHERE '.$this->_filter($filter));
        }
        return intval($row[0]['_count']);
    }

    function getlist_logi_no($cols='*', $filter=array(), $offset=0, $limit=-1, $orderType=null) {
        if (!$cols) {
            $cols = $this->defaultCols;
        }
        if (!empty($this->appendCols)) {
            $cols.=',' . $this->appendCols;
        }
        if ($this->use_meta) {
            $meta_info = $this->prepare_select($cols);
        }
        $orderType = $orderType ? $orderType : $this->defaultOrder;

        if ($filter['logi_no'] == 'NULL') {
            unset($filter['logi_no']);
            $where = $this->_filter($filter) . ' AND `logi_no` IS NULL';
        } else {
            $where = $this->_filter($filter);
        }

        //增加对 idx_split 的 order 排序的支持
        $orderType = (is_array($orderType) ? implode(' ', $orderType) : $orderType);
        $table = $this->table_name(true);

        if (strpos($orderType, 'idx_split') !== false) {

            $table .= " LEFT JOIN (SELECT COUNT(idx_split) AS iNum, idx_split AS idx FROM {$table} WHERE {$where} GROUP BY idx_split ) AS i ON i.idx=idx_split";
            $orderType = str_replace('`', '', $orderType);
            $orderType = str_replace('idx_split', 'skuNum, itemNum, iNum DESC, idx_split,delivery_id', $orderType);
        }

        $sql = 'SELECT ' . $cols . ' FROM ' . $table . ' WHERE ' . $where;

        if ($orderType)
            $sql.=' ORDER BY ' . $orderType;
        $data = $this->db->selectLimit($sql, $limit, $offset);
        // 数据解密
        foreach ((array) $data as $key => $value) {
            foreach ($this->__encrypt_cols as $field => $type) {
                if (isset($value[$field])) {
                    $data[$key][$field] = (string) kernel::single('ome_security_factory')->decryptPublic($value[$field],$type);
                }
            }
        }
        $this->tidy_data($data, $cols);
        if ($this->use_meta && count($meta_info['metacols']) && $data) {
            foreach ($meta_info['metacols'] as $col) {
                $obj_meta = new dbeav_meta($this->table_name(true), $col, $meta_info['has_pk']);
                $obj_meta->select($data);
            }
        }
        return $data;
    }

    public function update($data, $filter=array(), $mustUpdate = null) {

         foreach ($this->__encrypt_cols as $field => $type) {
             if (isset($data[$field])) {
                 $data[$field] = (string) kernel::single('ome_security_factory')->encryptPublic($data[$field],$type);
             }
         }

         //调用原有处理
         $result = parent::update($data, $filter, $mustUpdate);

         return $result;
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

    function modifier_is_cod($row){
        if($row == 'true'){
            return "<div style='width:48px;padding:2px;height:16px;background-color:green;float:left;'><span style='color:#eeeeee;'>货到付款</span></div>";
        }else{
            return '款到发货';
        }
    }

    /**
     * 判断是否已有此物流单号，检验前物流单号可以任意修改
     *
     * @param string $logi_no
     * @param int $dly_id
     * @return boolean
     */
    function existExpressNo($logi_no, $dly_id=0){
        //更新，conut走架构
        $filter['logi_no'] = $logi_no;
        $filter['delivery_id|noequal'] = $dly_id;//不等于，见dbeav：filter
        //$filter['verify'] = 'true';
        //$filter['status'] = array('progress','succ');

        $count = app::get('wms')->model('delivery_bill')->count($filter);
        //$billrow = $this->db->selectRow('select * from sdb_wms_delivery_bill where logi_no="'.$logi_no.'"');
        if ($count > 0) {
            return true;
        }else{
            return false;
        }
    }

    /**
     *
     * 根据主物流单号查找发货单ID
     */
    public function getDeliveryIdByLogiNo($logi_no){
        $row = app::get('wms')->model('delivery_bill')->getList('delivery_id',array('logi_no'=>$logi_no),0,1);
        return isset($row[0]) ? $row[0]['delivery_id'] : null;
    }

    /**
     * 通过发货单号获取发货单详情关联表的对应记录
     *
     * @param bigint $dly_id
     *
     * @return array()
     */
    function getItemsByDeliveryId($dly_id){
        $dly_itemObj = app::get('wms')->model('delivery_items');

        $rows = $dly_itemObj->getList('*', array('delivery_id' => $dly_id),0,-1);

        return $rows;
    }

    //根据发货单id调整打印排序
    function printOrderByByIds($ids) {
        if(!$ids)return false;
        $table = $this->table_name(true);
        $where = 'delivery_id in('.implode(',', $ids).')';
        $table .= " LEFT JOIN (SELECT COUNT(idx_split) AS iNum, idx_split AS idx FROM {$table} WHERE {$where} GROUP BY idx_split ) AS i ON i.idx=idx_split";
        $orderType =  'skuNum, itemNum, iNum DESC, idx_split,delivery_id';
        $sql = 'SELECT delivery_id FROM ' . $table . ' WHERE ' . $where.' ORDER BY ' . $orderType;
        $list = $this->db->select($sql);
        $delivery_ids = array();
        foreach($list as $row){
            $delivery_ids[] = $row['delivery_id'];
        }

        return $delivery_ids;
    }

    function existStockIsPlus($product_id, $store, $item_id, $branch_id, &$err_msg, $bn){
        $err_msg = '';
        $branch_pObj = app::get('ome')->model('branch_product');

        $bp = $branch_pObj->dump(array('branch_id'=>$branch_id,'product_id'=>$product_id),'store');
        if ($bp['store'] < $store){
            $err_msg = $bn."：此货号商品仓库商品数量不足";
            return false;
        }
        return true;
    }

    //根据wms发货单查询外键关键发货通知单，查询对应的订单号
    function getOrderIdByDeliveryId($dly_ids){
        $id_arr = $this->getOuterIdsByIds($dly_ids);
        //print_r($id_arr);
        $dly_orderObj = app::get('ome')->model('delivery_order');
        $filter['delivery_id'] = $id_arr;

        $data = $dly_orderObj->getList('order_id', $filter);
        foreach ($data as $item){
            $ids[] = $item['order_id'];
        }
        //print_r($ids);
        return $ids;
    }

    /**
     * 获取打印前端商品名
     *
     * @param Array $deliverys 发货单集合
     * @return Array
     * @author
     **/
    public function getPrintOrderName($deliverys)
    {
        $data = array();

        $order_ids = array();
        foreach ((array) $deliverys as $delivery) {
            foreach ($delivery['delivery_order'] as $delivery_order) {
                $order_ids[] = $delivery_order['order_id'];
            }
        }

        $orderItemModel = app::get('ome')->model('order_items');
        $orderItemList = $orderItemModel->getList('order_id,name,bn,addon',array('order_id' => $order_ids,'delete' => 'false'));

        $re_orderItemList = array();
        foreach ((array) $orderItemList as $order_item) {
            $order_item['addon'] = ome_order_func::format_order_items_addon($order_item['addon']);
            $re_orderItemList[$order_item['order_id']][$order_item['bn']] = $order_item;
        }
        unset($orderItemList);

        foreach ((array) $deliverys as $delivery) {
            $arr = array();
            foreach ($delivery['delivery_order'] as $delivery_order) {
                //$arr = array_merge((array) $arr,(array) $re_orderItemList[$delivery_order['order_id']]);
                $arr = $arr + (array) $re_orderItemList[$delivery_order['order_id']];
            }

            $data[$delivery['delivery_id']] = $arr;
        }
        unset($re_orderItemList);

        return $data;
    }

    /**
     * 获取打印基础物料位
     *
     * @param Array $deliverys 发货单集合
     * @return void
     * @author
     **/
    public function getPrintProductPos($deliverys)
    {
        $data = array();

        $product_ids = array();
        foreach ($deliverys as $delivery) {
            foreach ($delivery['delivery_items'] as $delivery_item) {
                $product_ids[] = $delivery_item['product_id'];

                $bpro_key = $delivery['branch_id'].$delivery_item['product_id'];
                $data[$delivery_item['product_id']] = &$bpro[$bpro_key];
            }
        }

        // 基础物料货位有关系
        $bppModel = app::get('ome')->model('branch_product_pos');
        $bppList = $bppModel->getList('product_id,pos_id,branch_id',array('product_id'=>$product_ids));

        // 如果货位存在
        if ($bppList) {
            // 货位信息
            $pos_ids = array();
            foreach ($bppList as $key=>$value) {
                $pos_ids[] = $value['pos_id'];
            }

            $posModel = app::get('ome')->model('branch_pos');
            $posList = $posModel->getList('pos_id,branch_id,store_position',array('pos_id'=>$pos_ids));

            foreach ($posList as $key=>$value) {
                $bpos_key = $value['branch_id'].$value['pos_id'];

                $bpos[$bpos_key] = $value['store_position'];
            }
            unset($posList);

            foreach ($bppList as $key=>$value) {
                $bpro_key = $value['branch_id'].$value['product_id'];
                $bpos_key = $value['branch_id'].$value['pos_id'];
                $bpro[$bpro_key] = $bpos[$bpos_key];
            }
            unset($bppList);
        }

        return $data;
    }

    function getAllTotalAmountByDelivery($delivery_order){
        $order_total_amount = 0;
        if(count($delivery_order)>1){//合并
            $is_vaild = true;
            foreach($delivery_order as $deli_order){
                $total_amount = $this->getTotalAmountByDelivery($deli_order['order_id'],$deli_order['ome_delivery_id']);
                if($total_amount){
                    $order_total_amount += $total_amount;
                }else{
                    $is_vaild = false;
                    break;
                }
            }

            if(!$is_vaild){
                $order_total_amount = 0;
            }
        }else{//单张
            $delivery_order = current($delivery_order);
            $order_total_amount = $this->getTotalAmountByDelivery($delivery_order['order_id'],$delivery_order['ome_delivery_id']);
        }

        return $order_total_amount;
    }

    function getTotalAmountByDelivery($order_id,$delivery_id){
        $order_total_amount = 0;
        $objOrders = app::get('ome')->model('orders');
        $order = $objOrders->order_detail($order_id);

        if($order['process_status'] == 'splited'){//已拆分
            $ids = app::get('ome')->model('delivery')->getDeliverIdByOrderId($order_id);
            if(count($ids) == 1){//发货单必须是一张
                $order_total_amount = $order['total_amount'];
            }
        }

        return $order_total_amount;
    }

    /**
     * 获取发货人信息
     *
     * @param int $dly_id
     *
     * @return array()
     */
    function getShopInfo($shop_id){
        static $shops;

        if ($shops[$shop_id]) return $shops[$shop_id];

        $shopObj = app::get('ome')->model("shop");
        $shops[$shop_id] = $shopObj->dump($shop_id);

        return $shops[$shop_id];
    }

    /**
     * 统计商品数量
     *
     * @param array() $dly_ids
     *
     * @return array()
     */
    function countProduct($dly_ids=null){
        if ($dly_ids){
            $sql = "SELECT bn,product_name,SUM(number) AS 'count' FROM sdb_wms_delivery_items
                                                              WHERE delivery_id IN ($dly_ids) AND number!=0 GROUP BY bn";

            $data = $this->db->select($sql);
        }
        return $data;
    }

    public function getPrintStockPrice($ids){
        $tmp_ids = $this->getOuterIdsByIds($ids);
        $data = array();
        $sql = 'SELECT did.bn,SUM(did.amount) AS _amount FROM sdb_ome_delivery_items_detail AS did WHERE did.delivery_id IN('.implode(',',$tmp_ids).') GROUP BY did.bn';
        $rows = $this->db->select($sql);
        foreach ($rows as $row) {
            $data[$row['bn']] = $row['_amount'];
        }
        return $data;
    }

    public function getOuterIdsByIds($ids){
        $filter['delivery_id'] = $ids;
        $outer_bns = $this->getList('outer_delivery_bn',$filter);
        foreach ($outer_bns as $outer_bn){
            $delivery_bns[] = $outer_bn['outer_delivery_bn'];
        }

        $deliveryObj = app::get('ome')->model('delivery');
        $delivery_ids = $deliveryObj->getList('delivery_id', array('delivery_bn'=>$delivery_bns));
        foreach ($delivery_ids as $delivery_id){
            $id_arr[] = $delivery_id['delivery_id'];
        }

        return $id_arr;
    }

    public function getOuterIdById($id){
        $row = $this->dump($id,'outer_delivery_bn');

        $deliveryObj = app::get('ome')->model('delivery');
        $deliveryInfo = $deliveryObj->dump(array('delivery_bn'=>$row['outer_delivery_bn']),'delivery_id');

        return $deliveryInfo['delivery_id'];

    }

    function getProductPosByDeliveryId($dly_id=null){
        $dly_id = $this->getOuterIdById($dly_id);
        if ($dly_id){
            // 发货单对应的仓库id
            $branch_id = app::get('ome')->model('delivery')->dump($dly_id,'branch_id');
            $sql = "SELECT di.bn,di.product_name,di.product_id, di.number,delivery_id, bp.store_position,
                    a.bm_id AS goods_id, a.material_name AS name, a.material_bn AS bn 
                    FROM sdb_ome_delivery_items di
                    JOIN sdb_material_basic_material a ON a.bm_id=di.product_id
                    LEFT JOIN (
                                SELECT bpp.*
                                FROM (
                                SELECT pos_id,product_id
                                FROM sdb_ome_branch_product_pos
                                        WHERE branch_id=".$branch_id['branch_id']."
                                )bpp
                                GROUP BY bpp.product_id
                    )bb
                    ON bb.product_id = di.product_id
                    LEFT JOIN sdb_ome_branch_pos bp ON bp.pos_id = bb.pos_id
                    WHERE di.delivery_id = $dly_id
                    AND di.number != 0";

            $rows = $this->db->select($sql);
        }

        $basicMaterialLib    = kernel::single('material_basic_material');
        
        foreach ($rows as $key => $val)
        {
            $get_product    = $basicMaterialLib->getBasicMaterialExt($val['goods_id']);
            
            $val['barcode']    = $get_product['barcode'];
            $val['weight']    = $get_product['weight'];
            $val['unit']     = $get_product['unit'];
            $val['price']    = $get_product['retail_price'];
            $val['spec_info']    = $get_product['specifications'];

            $rows[$key]    = $val;
        }

        return $rows;
    }

    //调整获取的数据内容去掉原来goods部分内容 by xiayuanjun
    function getProductPosInfo($dly_id='',$branch_id='')
    {
        $dly_id = $this->getOuterIdById($dly_id);
        if ($dly_id && $branch_id)
        {
            $sql = "SELECT di.item_id, di.bn,di.product_name,di.product_id,bmx.weight,bmx.unit,bmx.specifications,
                        di.number,delivery_id,bmx.retail_price as price,
                        p.material_name as name,p.bm_id, bp.store_position FROM sdb_ome_delivery_items di
                                JOIN sdb_material_basic_material p
                                    ON p.bm_id=di.product_id
                                LEFT JOIN sdb_material_basic_material_ext bmx ON bmx.bm_id = p.bm_id
                                LEFT JOIN (
                                SELECT bpp.*
                                FROM (
                                SELECT ss.pos_id,ss.product_id
                                FROM sdb_ome_branch_product_pos as ss LEFT JOIN sdb_ome_branch_pos bss on ss.pos_id=bss.pos_id
                                        WHERE ss.branch_id=".$branch_id." AND bss.pos_id!=''
                                )bpp
                                GROUP BY bpp.product_id
                                )bb
                                ON bb.product_id = di.product_id
                                LEFT JOIN sdb_ome_branch_pos bp ON bp.pos_id = bb.pos_id
                                WHERE di.delivery_id = $dly_id
                                    AND di.number != 0";
            $rows = $this->db->select($sql);
        }
        return $rows;
    }

    function getOrderMemoByDeliveryId($dly_ids=null){
        $dly_ids = $this->getOuterIdsByIds($dly_ids);
        if ($dly_ids){
            $sql = "SELECT o.custom_mark FROM sdb_ome_delivery_order do
                                    JOIN sdb_ome_orders o
                                        ON do.order_id=o.order_id
                                    WHERE do.delivery_id IN ($dly_ids)
                                        GROUP BY do.order_id ";
            $rows = $this->db->select($sql);
            $memo = array();
            if ($rows){
                foreach ($rows as $v)
                    $memo[] = unserialize($v['custom_mark']);
            }
            return serialize($memo);
        }
    }

    /**
     * 获取订单备注
     */
    function getOrderMarktextByDeliveryId($dly_ids=null){
        $dly_ids = $this->getOuterIdsByIds($dly_ids);
        if ($dly_ids){
            $sql = "SELECT o.mark_text FROM sdb_ome_delivery_order do
                                    JOIN sdb_ome_orders o
                                        ON do.order_id=o.order_id
                                    WHERE do.delivery_id IN ($dly_ids)
                                        GROUP BY do.order_id ";
            $rows = $this->db->select($sql);

            $memo = array();
            if ($rows){
                foreach ($rows as $v)

                    $memo[] = unserialize($v['mark_text']);
            }
            return serialize($memo);
        }
    }

    public function getPrintProductName($ids){
        $ids = $this->getOuterIdsByIds($ids);
        $printProductNames = array();
        $sql = 'SELECT distinct oi.order_id,oi.name,oi.bn,oi.addon,bp.store_position
                    FROM sdb_ome_delivery_order AS d2o
                LEFT JOIN sdb_ome_order_items AS oi
                    ON d2o.order_id = oi.order_id
                LEFT JOIN (
                    SELECT bpp.*
                        FROM (
                            SELECT pos_id,product_id
                            FROM sdb_ome_branch_product_pos
                            ORDER BY create_time DESC
                        )bpp
                    GROUP BY bpp.product_id
                 )bb
                    ON bb.product_id = oi.product_id
                 LEFT JOIN sdb_ome_branch_pos bp
                    ON bp.pos_id = bb.pos_id
                WHERE d2o.delivery_id IN('.implode(',',$ids).') ORDER BY d2o.order_id';
        $rows = $this->db->select($sql);
        foreach($rows as $row){
            $row['bn'] = trim($row['bn']);

            if (isset($printProductNames[$row['bn']]))
                continue;
            $row['addon'] = ome_order_func::format_order_items_addon($row['addon']);

            $printProductNames[$row['bn']] = $row;
        }

        return $printProductNames;
    }

    /**
     * 找印时获取前端名称
     *
     */
    public function getPrintFrontProductName($ids){
        $ids = $this->getOuterIdsByIds($ids);
        $ordersObj = app::get('ome')->model('orders');
        $printProductNames = array();
        $sql = 'SELECT distinct oi.order_id,oo.name,oi.bn,oi.addon,bp.store_position
                    FROM sdb_ome_delivery_order AS d2o
                LEFT JOIN sdb_ome_order_items AS oi
                    ON d2o.order_id = oi.order_id
                LEFT JOIN sdb_ome_order_objects AS oo
                    ON oi.obj_id = oo.obj_id
                LEFT JOIN (
                    SELECT bpp.*
                        FROM (
                            SELECT pos_id,product_id
                            FROM sdb_ome_branch_product_pos
                            ORDER BY create_time DESC
                        )bpp
                    GROUP BY bpp.product_id
                 )bb
                    ON bb.product_id = oi.product_id
                 LEFT JOIN sdb_ome_branch_pos bp
                    ON bp.pos_id = bb.pos_id
                WHERE d2o.delivery_id IN('.implode(',',$ids).') ORDER BY d2o.order_id';
        $rows = $this->db->select($sql);
        foreach($rows as $row){
            $orders = $ordersObj->dump($row['order_id'],'shop_id');

            $bncode = md5($orders['shop_id'].trim($row['bn']));
            $row['bn'] = $bncode;

            if (isset($printProductNames[$row['bn']]))
                continue;
            $row['addon'] = ome_order_func::format_order_items_addon($row['addon']);

            $printProductNames[$row['bn']] = $row;
        }

        return $printProductNames;
    }

    /**
     *
     * 统计已打印完成待校验的发货单总数
     */
    function countNoVerifyDelivery(){
        $filter = array(
            'status' => 0,
            'process_status' => 1,
            'disabled' => 'false',
        );

        $oBranch = app::get('ome')->model('branch');
        $is_super = kernel::single('desktop_user')->is_super();
        if (!$is_super) {
            $branch_ids = $oBranch->getBranchByUser(true);
            if ($branch_ids) {
                $filter['branch_id'] = $branch_ids;
            }
        }

        $num = $this->count_logi_no($filter);
        return $num;
    }

    /**
     * 统计已校验待发货的发货单总数
     */
    function countNoProcessDelivery(){
        $filter = array(
            'status' => 0,
            'process_status' => 3,
            'disabled' => 'false',
        );

        $oBranch = app::get('ome')->model('branch');
        $is_super = kernel::single('desktop_user')->is_super();
        if (!$is_super) {
            $branch_ids = $oBranch->getBranchByUser(true);
            if ($branch_ids) {
                $filter['branch_id'] = $branch_ids;
            }
        }

        $num = $this->count($filter);
        return $num;
    }

    /**
     * 统计子物流表待发货的发货单总数
     */
    function countNoProcessDeliveryBill(){
        $filter = array(
            'status' => 0,
            'process_status' => 3,
            'disabled' => 'false',
        );

        $oBranch = app::get('ome')->model('branch');
        $is_super = kernel::single('desktop_user')->is_super();
        if (!$is_super) {
            $branch_ids = $oBranch->getBranchByUser(true);
            if ($branch_ids) {
                $filter['branch_id'] = $branch_ids;
            }
        }

        $num = $this->count($filter);
        $dataDly = $this->getList('delivery_id', $filter, 0, -1);
        $billObj = app::get('wms')->model('delivery_bill');
        foreach($dataDly as $v){
            $billFilter = array(
                'status' => 0,
                'delivery_id'=> $v['delivery_id'],
                'type' => 2,
            );
            $num += $billObj->count($billFilter);
        }
        return $num;
    }

    function io_title( $filter, $ioType='csv' ){

        switch( $filter ){
            case 'delivery':
                $this->oSchema['csv'][$filter] = array(
                    '*:订单号' => 'order_bn',
                    '*:来源店铺' => 'shop_name',
                    '*:发票号' => 'tax_no',
                    '*:会员用户名' => 'member_id',
                    '*:物流公司' => 'logi_name',
                    '*:商品货号' => 'bn',
                    '*:商品名称' => 'product_name',
                    '*:购买数量' => 'number',
                    '*:商品单价'=>'price',
                    '*:配送方式'=>'delivery',
                    '*:配送费用'=>'freight',
                    '*:总价'=>'total',
                    '*:物流单号'=>'logi_no',
                    '*:收货地址'=>'ship_addr',
                    '*:收货地区'=>'ship_area',
                    '*:收货人'=>'ship_name',
                    '*:收货人电话'=>'ship_tel',
                    '*:收货人手机'=>'ship_mobile',
                    '*:收货邮编'=>'ship_zip',
                    '*:发货时间'=>'delivery_time',
                    '*:外部发货单号'=>'delivery_bn',
                    '*:发货单号'=>'outer_delivery_bn',
                    '*:发货单明细流水号'=>'item_id',
                    '*:货位'=>'store_position',
                    '*:规格'=>'spec_value',//
                );
                break;
        }
        $this->ioTitle[$ioType][$filter] = array_keys( $this->oSchema[$ioType][$filter] );
        return $this->ioTitle[$ioType][$filter];
    }

    function fgetlist_csv( &$data,$filter,$offset,$exportType = 1 )
    {
        $basicMaterialLib    = kernel::single('material_basic_material');

        $oShop = app::get('ome')->model('shop');
        $branchObj = app::get('ome')->model('branch');
        if( !$data['title']){
            $title = array();

            foreach($this->io_title('delivery') as $k => $v ){
                $title[] = $this->charset->utf2local($v);
            }
            $data['title']['delivery'] = '"'.implode('","',$title).'"';
        }
        //$limit =100;
        foreach ($filter as $fk=>$fv ) {
            if ($fv=='') {
                unset($filter[$fk]);
            }
        }
        if( !$list=$this->getlist_logi_no('*',$filter,0,-1) )return false;
        $outer_delivery = array();
        foreach ($list as $k => $row){
            $outer_delivery_bn[] = $row['outer_delivery_bn'];
            $outer_delivery[$row['outer_delivery_bn']] = $row['delivery_bn'];
        }
        $sql = 'SELECT DI.item_id, D.delivery_bn, D.delivery_id, D.member_id, D.logi_name, D.logi_no,
                D.ship_addr, D.ship_area, D.ship_name, D.ship_tel, D.ship_mobile, D.delivery, D.delivery_time, D.ship_zip, DI.bn, 
                a.material_name AS product_name, DI.number as dn 
            FROM sdb_ome_delivery_items AS DI
            LEFT JOIN sdb_ome_delivery AS D  ON D.delivery_id = DI.delivery_id 
            LEFT JOIN sdb_material_basic_material AS a on DI.product_id=a.bm_id 
            where D.delivery_bn in ("'.implode('","',$outer_delivery_bn).'") ORDER BY D.delivery_id DESC';

        $rows = $this->db->select($sql);
        $tmp_delivery_info = array(); $ids = array();
        foreach ($rows as $k => $row){
            $tmp_delivery_info[$row['delivery_id'].$row['bn']] = $row;
            $ids[] = $row['delivery_id'];
        }
        unset($rows);
        $sql = 'SELECT O.order_bn,O.shop_id,O.tax_no,O.cost_freight,OI.nums as number,ROUND((OI.sale_price/OI.nums),3) as price,OI.sale_price,DO.delivery_id,OI.bn,OI.product_id,D.branch_id
            FROM sdb_ome_order_items AS OI
            LEFT JOIN sdb_ome_orders AS O
                    ON O.order_id = OI.order_id
            LEFT JOIN sdb_ome_delivery_order AS DO
                    ON DO.order_id = O.order_id
            LEFT JOIN sdb_ome_delivery AS D
                    ON D.delivery_id = DO.delivery_id
            where D.delivery_id in ('.implode(',',$ids).') AND OI.delete=\'false\' ORDER BY D.delivery_id DESC, OI.bn ASC';
        $rows = $this->db->select($sql);
        $tmp_order = array();
        foreach ($rows as $k => $row){
            #同一订单运费只显示一次

            if(!isset($tmp_order[$row['order_bn']])){
                $tmp_order[$row['order_bn']] = $row['order_bn'];
                $cost_freight = round(($row['cost_freight']/$row['number'])*$tmp_delivery_info[$row['delivery_id'].$row['bn']]['dn'],3);
            }else{

                $cost_freight = 0;
            }
            if(isset($tmp_delivery_info[$row['delivery_id'].$row['bn']])){
                $rows[$k] = array_merge($row,$tmp_delivery_info[$row['delivery_id'].$row['bn']]);
                $rows[$k]['freight'] = $cost_freight;
                $rows[$k]['total'] = $cost_freight+ROUND($row['sale_price'],3);
            }
            #获取所有货位
            $_sql = 'select store_position from sdb_ome_branch_pos bpos left join sdb_ome_branch_product_pos  ppos on bpos. pos_id=ppos.pos_id where bpos.branch_id='.$row['branch_id'].' and product_id='.$row['product_id'];
            $_rows = $this->db->select($_sql);
            $_store_position = null;
            if(!empty($_rows[0])){
                #一个基础物料有多个货位时，中间要隔开
                foreach($_rows as $v){
                    $_store_position .= $v['store_position'].'|';
                }
            }
            #切掉尾部符号
            $_store_position  = substr_replace($_store_position,'',-1,1);
            $rows[$k]['store_position'] = $_store_position;

            $product_info    = $basicMaterialLib->getBasicMaterialExt($row['product_id']);
            
            #处理基础物料多规格值
            $spec_value = '';
            if(is_array($product_info['spec_desc']['spec_value']) && !empty($product_info['spec_desc']['spec_value'])){
                $spec_value = implode('|',$product_info['spec_desc']['spec_value']);
            }
            $rows[$k]['spec_value']  = $spec_value;
            unset($row,$_rows,$product_info);
        }
        foreach($rows as $key=>$row){
            $shop = $oShop->dump($row['shop_id'],'name');
            $rows[$key]['shop_name'] = $shop['name'];
            $rows[$key]['delivery_time'] = date('Y-m-d H:i:s',$row['delivery_time']);
            $ship_addr_arr = explode(':', $row['ship_area']);
            $rows[$key]['ship_area'] = $ship_addr_arr[1];
            $member = array();
            $memberObj = app::get('ome')->model('members');
            $member = $memberObj->getList('uname',array('member_id'=>$row['member_id']),0,1);
            $rows[$key]['member_id'] = $member[0]['uname'];
            $rows[$key]['order_bn'] .= "\t";
            $item_id = $row['item_id'];
            if(isset($item[$item_id])){
                $i++;
                $rows[$key]['item_id']= $item_id.'_'.$i;
            }else{
                $item[$item_id]=$item_id;
                $rows[$key]['item_id']= $item_id;
            }
            $rows[$key]['delivery_bn'] = $rows[$key]['delivery_bn']."\t";
            $rows[$key]['outer_delivery_bn'] = $outer_delivery[$row['delivery_bn']]."\t";
        }

        foreach( $rows as $aFilter ){


            foreach( $this->oSchema['csv']['delivery'] as $k => $v ){

                $pRow[$k] =  utils::apath( $aFilter,explode('/',$v) );
            }
            $data['content']['delivery'][] =$this->charset->utf2local('"'.implode( '","', $pRow ).'"');
        }


        return false;
    }

    function export_csv($data,$exportType = 1 ){
        $output = array();

        foreach( $data['title'] as $k => $val ){
            $output[] = $val."\n".implode("\n",(array)$data['content'][$k]);
        }

        echo implode("\n",$output);
    }


    /**
     * 根据delivery_id返回delivery_bn
     * @param   array    $delivery_id    description
     * @return  array delivery_bn
     * @access  public
     * @author cyyr24@sina.cn
     */
    function _getdelivery_bn($delivery_id)
    {
        $deliveryObj = app::get('ome')->model("delivery");
        $deliverys = $deliveryObj->getList('delivery_bn',array('delivery_id'=>$delivery_id));
        $deliverybn[] = 0;
        foreach ($deliverys as $delivery ) {
            $deliverybn[] = $delivery['delivery_bn'];
        }
        return $deliverybn;
    }


    /**
     * 获取打印状态
     * @param   $print_status
     * @return
     * @access  public
     * @author cyyr24@sina.cn
     */
    function get_printStatus($print_status)
    {
        $stock = 'false';
        $deliv = 'false';
        $expre = 'false';
        if(($print_status & 1) == 1){
            $stock = 'true';
        }
        if(($print_status & 2) == 2){
            $deliv = 'true';
        }
        if(($print_status & 4) == 4){
            $expre = 'true';
        }
        $print_status = array('stock'=>$stock,'deliv'=>$deliv ,'expre'=>$expre);
        return $print_status;
    }


    /**
     * 处理状态
     * @param   $process_status
     * @return
     * @access  public
     * @author cyyr24@sina.cn
     */
    function get_process_status($process_status)
    {
        switch($process_status){
            case 0:
                return '处理中';
                break;
            case 1:
                return '取消';
                break;
            case 2:
                return '暂停';
                break;
            case 3:
                return '已完成';
                break;
        }
    }


    /**
     * 根据订单返回发货单详情
     * @param   $order_id
     * @return  array
     * @access  public
     * @author cyyr24@sina.cn
     */
    function getDeliveryByOrder($order_id)
    {
        $deliOrderObj = app::get('ome')->model("delivery_order");
        $oBranch = app::get('ome')->model('branch');
        $dlyBillLib = kernel::single('wms_delivery_bill');

        $rows = $deliOrderObj->getList('delivery_id',array('order_id'=>$order_id), 0 , -1);
        foreach($rows as $key => $row){
            $delivery_id = $row['delivery_id'];
            $wms_delivery = $this->_getdelivery_bn($delivery_id);

            $delivery = $this->getlist('*', array('outer_delivery_bn'=>$wms_delivery[1],'status'=>array(0,3)));
            foreach($delivery as $k=>$v){
                $delivery_arr[$key] = $v;
                $delivery_arr[$key]['create_time'] = date('Y-m-d H:i:s',$v['create_time']);
                $logi_no = $dlyBillLib->getPrimaryLogiNoById($v['delivery_id']);
                $delivery_arr[$key]['logi_no'] = $logi_no;
                $delivery_arr[$key]['branch_name'] = $oBranch->Get_name($v['branch_id']);
                $print_status_show = $this->get_printStatus($v['print_status']);

                $stock = $print_status_show['stock'];
                $deliv = $print_status_show['deliv'];
                $expre = $print_status_show['expre'];
                if ($stock == 'true' && $deliv == 'true' && $expre == 'true') {
                    $delivery_arr[$key]['print_status'] = '已完成打印';

                }else if($stock == 'false' && $deliv == 'false' && $expre == 'false'){
                    $delivery_arr[$key]['print_status'] = '未打印';
                }else{
                    $print_status  = array();

                    if($stock == 'true'){
                        $print_status[] = '备货单';
                    }
                    if($deliv == 'true'){
                        $print_status[] = '清单';
                    }
                    if($expre == 'true'){
                        $print_status[] = '快递单';
                    }
                    $delivery_arr[$key]['print_status'] = implode("/",$print_status)."已打印";
                }
                #重量
                $delivery_weight = $dlyBillLib->getDeliveryByBill($v['delivery_id'],$logi_no);
                $delivery_arr[$key]['weight'] = $delivery_weight['weight'];
                #处理状态
                $delivery_arr[$key]['status_text'] = $this->get_process_status($v['status']);
            }
        }
        return $delivery_arr;
    }


    /**
     * 发货单导出日志类型
     * @param  $params
     * @return  type
     * @access  public
     * @author cyyr24@sina.cn
     */
    function getLogType($logParams)
    {
        $type = 'selfwms_delivery_export';
        return $type;
    }

    //逐单发货时，根据发货单id，获取基础物料编码、基础物料名称、数量、重量
    function getProcutInfo($delivery_id){
        $sql = 'select
                    items.bn,items.product_id,items.product_name,items.number,delivery.net_weight,delivery.delivery_id
                from sdb_wms_delivery as delivery
                left join sdb_wms_delivery_items items on items.delivery_id=delivery.delivery_id
                where delivery.delivery_id='.$delivery_id;
        $rows = $this->db->select($sql);
        return $rows;
    }

    function array2xml2($data,$root='root'){
        $xml='<'.$root.'>';
        $this->_array2xml($data,$xml);
        $xml.='</'.$root.'>';
        return $xml;
    }

    function _array2xml(&$data,&$xml){
        if(is_array($data)){
            foreach($data as $k=>$v){
                if(is_numeric($k)){
                    $xml.='<item>';
                    $xml.=$this->_array2xml($v,$xml);
                    $xml.='</item>';
                }else{
                    $xml.='<'.$k.'>';
                    $xml.=$this->_array2xml($v,$xml);
                    $xml.='</'.$k.'>';
                }
            }
        }elseif(is_numeric($data)){
            $xml.=$data;
        }elseif(is_string($data)){
            $xml.='<![CDATA['.$data.']]>';
        }
    }
    
    //导出扩展字段
    function export_extra_cols(){
        return array(
                'column_delivery_cost_expect' => array('label'=>'预计物流费用','width'=>'100','func_suffix'=>'delivery_cost_expect'),
        );
    }
    
    /**
     * 发货单列表项扩展字段
     */
    function extra_cols(){
        return array(
            'column_delivery_label' => array('label'=>'发货单标记','width'=>'220','func_suffix'=>'delivery_label'),
            'column_custom_mark' => array('label'=>'买家留言','width'=>'180','func_suffix'=>'custom_mark'),
            'column_mark_text' => array('label'=>'客服备注','width'=>'180','func_suffix'=>'mark_text'),
            'column_tax_no' => array('label'=>'发票号','width'=>'180','func_suffix'=>'tax_no'),
            'column_ident' => array('label'=>'批次号','width'=>'160','func_suffix'=>'ident','order_field'=>'idx_split'),
        );
    }

    /**
     * 买家备注扩展字段格式化
     */
    function extra_custom_mark($rows){
        return kernel::single('wms_extracolumn_delivery_custommark')->process($rows);
    }

    /**
     * 客服备注扩展字段格式化
     */
    function extra_mark_text($rows){
        return kernel::single('wms_extracolumn_delivery_marktext')->process($rows);
    }

    /**
     * 发票号扩展字段格式化
     */
    function extra_tax_no($rows){
        return kernel::single('wms_extracolumn_delivery_taxno')->process($rows);
    }

    /**
     * 批次号扩展字段格式化
     */
    function extra_ident($rows){
        return kernel::single('wms_extracolumn_delivery_ident')->process($rows);
    }

    function extra_delivery_label($rows){
        $deliveryIds = array_column((array)$rows, 'delivery_id');
        if (!$deliveryIds) {
            return array();
        }
        $labelRows = app::get('ome')->model('bill_label')->getBIllLabelList($deliveryIds, 'wms_delivery');
        $result = array();
        foreach ((array)$labelRows as $label) {
            $result[$label['bill_id']] .= sprintf(
                "<span class='tag-label' title='%s' style='background-color:%s;color:#000000;'>%s</span>",
                $label['label_name'],
                $label['label_color'],
                $label['label_name']
            );
        }
        return $result;
    }

    //定义导出明细内容的相关字段
    public function get_exp_detail_schema(){
        $schema = array (
            'columns' => array (
                'bn' => array(
                    'type' => 'varchar(30)',
                    'label' => '基础物料编码',
                    'width' => 85,
                    'editable' => false,
                ),
                'product_name' => array(
                    'type' => 'varchar(200)',
                    'required' => true,
                    'default' => '',
                    'label' => '商品名称',
                    'width' => 190,
                    'editable' => false,
                ),
                'number' => array(
                    'type' => 'number',
                    'required' => true,
                    'default' => 0,
                    'label' => '购买数量',
                    'editable' => false,
                ),
                'price' => array(
                    'type' => 'money',
                    'default' => '0',
                    'required' => true,
                    'label' => '商品单价',
                    'editable' => false,
                ),
                'avgprice' => array(
                    'type' => 'money',
                    'default' => '0',
                    'required' => true,
                    'label' => '商品均单价',
                    'editable' => false,
                ),
                'total' => array(
                    'type' => 'money',
                    'default' => '0',
                    'label' => '总价',
                    'width' => 70,
                    'editable' => false,
                ),
                'item_id' => array(
                    'type' => 'int unsigned',
                    'label' => '发货单明细流水号',
                    'comment' => '发货单明细流水号',
                    'editable' => false,
                ),
                'store_position' => array(
                    'type' => 'varchar(100)',
                    'label' => '货位',
                    'comment' => '货位',
                    'editable' => false,
                ),
                'spec_value' => array(
                    'type' => 'varchar(100)',
                    'label' => '规格',
                    'comment' => '规格',
                    'editable' => false,
                ),
            ),
        );
        return $schema;
    }

    //根据过滤条件获取导出发货单的主键数据数组
    public function getPrimaryIdsByCustom($filter, $op_id){
        //过滤掉空的查询条件
        foreach ($filter as $fk=>$fv ) {
            if ($fv=='') {
                unset($filter[$fk]);
            }
        }

        if( !$list=$this->getlist_logi_no('*',$filter,0,-1) )return false;

        $ids = array();
        foreach ($list as $k => $row){
            $ids[] = $row['delivery_id'];
        }
        return $ids;

    }

    //根据主键id获取导出数据
    public function getExportDataByCustom($fields, $filter, $has_detail, $curr_sheet, $start, $end, $op_id){
        $ids = $filter['delivery_id'];
        $outer_ids = $this->getOuterIdsByIds($ids);

        #基础物料
        $basicMaterialObj    = app::get('material')->model('basic_material');
        $basicMaterialLib    = kernel::single('material_basic_material');

        $obj_queue_items   = app::get('ome')->model('print_queue_items');
        $oBranch = app::get('ome')->model('branch');
        $dlyObj = app::get('ome')->model('delivery');
        $dlyorderObj = app::get('ome')->model('delivery_order');

        //获取所有仓库名称
        $all_branch_info = $oBranch->getList('branch_id,name',array());
        $all_branch_name = array();
        foreach($all_branch_info as $v){
            $all_branch_name[$v['branch_id']] = $v['name'];
        }
        unset($all_branch_info);

        $sql = 'SELECT delivery_bn,outer_delivery_bn,delivery_id,delivery_cost_expect FROM  sdb_wms_delivery where delivery_id in ("'.implode('","',$ids).'") ORDER BY delivery_id DESC';
        $list = $this->db->select($sql);
        $outer_delivery = array();
        $outer_deliveryIds = array();
        foreach ($list as $k => $row){
            $outer_delivery[$row['outer_delivery_bn']] = array(
                "delivery_bn" => $row['delivery_bn'],
                "delivery_cost_expect" => $row['delivery_cost_expect'],
            );
            $outer_deliveryIds[$row['outer_delivery_bn']] = $row['delivery_id'];
        }

        $sql = 'SELECT DI.product_id, DI.item_id, D.branch_id, D.delivery_bn, D.delivery_id, D.member_id, D.logi_name, D.logi_no, D.ship_addr, D.ship_area, D.ship_name, D.ship_tel, D.ship_mobile, D.delivery_time, D.ship_zip, DI.bn, DI.number as dn,D.create_time
            FROM sdb_ome_delivery_items AS DI
            LEFT JOIN sdb_ome_delivery AS D  ON D.delivery_id = DI.delivery_id 
            where D.delivery_id in ('.implode(',',$outer_ids).') ORDER BY D.delivery_id DESC';

        $rows = $this->db->select($sql);
        $tmp_delivery_info = array();
        foreach ($rows as $k => $row)
        {
            $product_info    = $basicMaterialObj->dump(array('bm_id'=>$row['product_id']), 'material_name');
            $row['product_name']    = $product_info['material_name'];

            $tmp_delivery_info[$row['delivery_id'].$row['bn']] = $row;
        }
        unset($rows);

        //[拆单]获取多个发货单对应订单信息
        $sql    = "SELECT DI.delivery_id, O.order_bn, O.custom_mark, O.mark_text, O.shop_id, O.tax_no, O.cost_freight,
                    DI.bn, DI.price, DI.amount, DI.number, DI.product_id 
                    FROM sdb_ome_delivery_items_detail AS DI 
                    LEFT JOIN sdb_ome_orders AS O 
                            ON O.order_id = DI.order_id 
                    WHERE DI.delivery_id in(".implode(',',$outer_ids).") ORDER BY DI.delivery_id DESC";

        $rows = $this->db->select($sql);
        //备注显示方式
        $markShowMethod = app::get('ome')->getConf('ome.order.mark');
        $tmp_order = array();
        foreach ($rows as $k => $row){
            //新增发货单创建时间
            $rows[$k]['create_time'] = date('Y-m-d H:i:s',$row['create_time']);
            //同一订单运费只显示一次
            if(!isset($tmp_order[$row['order_bn']])){
                $tmp_order[$row['order_bn']] = $row['order_bn'];
                $cost_freight = round(($row['cost_freight']/$row['number'])*$tmp_delivery_info[$row['delivery_id'].$row['bn']]['dn'],3);
            }else{
                $cost_freight = 0;
            }
            if(isset($tmp_delivery_info[$row['delivery_id'].$row['bn']])){
                $rows[$k] = array_merge($row,$tmp_delivery_info[$row['delivery_id'].$row['bn']]);
                $rows[$k]['freight'] = $cost_freight;
                $rows[$k]['total'] = $cost_freight +(ROUND($row['price'],3) * $row['number']);
            }
            
            //获取仓库branch_id
            $rows[$k]['branch_id']    = $tmp_delivery_info[$row['delivery_id'].$row['bn']]['branch_id'];
            $current_branch_id = $rows[$k]['branch_id']; //获取货位用
            $rows[$k]['branch_id'] = $all_branch_name[$rows[$k]['branch_id']]?$all_branch_name[$rows[$k]['branch_id']]:'-'; //发货仓库 要显示仓库名称
            #获取所有货位
            $_sql = 'select store_position from sdb_ome_branch_pos bpos left join sdb_ome_branch_product_pos  ppos on bpos. pos_id=ppos.pos_id where bpos.branch_id='.$current_branch_id.' and product_id='.$row['product_id'];
            $_rows = $this->db->select($_sql);
            $_store_position = null;
            if(!empty($_rows[0])){
                #一个基础物料有多个货位时，中间要隔开
                foreach($_rows as $v){
                    $_store_position .= $v['store_position'].'|';
                }
            }
            #切掉尾部符号
            $_store_position  = substr_replace($_store_position,'',-1,1);
            $rows[$k]['store_position'] = $_store_position;
            //处理商品均单价
            $dly_order = $dlyorderObj->getlist('*',array('delivery_id'=>$row['delivery_id']),0,-1);
            $sale_orders = $dlyObj->getsale_price($dly_order);
            $rows[$k]['avgprice'] = $sale_orders[$row['bn']];

            //处理货品多规格值
            $tmp_pdt_spec = array();
            if(!isset($tmp_pdt_spec[$row['product_id']]))
            {
                $product_info    = $basicMaterialLib->getBasicMaterialExt($row['product_id']);
                
                $spec_value = '';
                if(is_array($product_info['spec_desc']['spec_value']) && !empty($product_info['spec_desc']['spec_value'])){
                    $spec_value = implode('|',$product_info['spec_desc']['spec_value']);
                }
                $tmp_pdt_spec[$row['product_id']] = $spec_value;
            }else{
                $spec_value = $tmp_pdt_spec[$row['product_id']];
            }
            $rows[$k]['spec_value']  = $spec_value;

            $queue_items = $obj_queue_items->getlist('ident,ident_dly',array('delivery_id'=>$outer_deliveryIds[$rows[$k]['delivery_bn']]));
            if($queue_items[0]['ident'] && $queue_items[0]['ident_dly']){
                $rows[$k]['ident'] = $queue_items[0]['ident'].'_'.$queue_items[0]['ident_dly'];
            }else{
                $rows[$k]['ident'] = '-';
            }

            $str_custom_mark ='';
            if($row['custom_mark']) {
                $custom_mark = unserialize($row['custom_mark']);
                if (is_array($custom_mark) || !empty($custom_mark)){
                    if($markShowMethod == 'all'){
                        foreach ($custom_mark as $_custom_mark ) {
                            $str_custom_mark .= $_custom_mark['op_content'];
                        }
                    }else{
                        $_memo = array_pop($custom_mark);
                        $str_custom_mark = $_memo['op_content'];
                    }
                }
                $rows[$k]['custom_mark'] = $str_custom_mark;
            }else{
                $rows[$k]['custom_mark'] = '-';
            }

            $str_mark_text ='';
            if($row['mark_text']) {
                $mark_text = unserialize($row['mark_text']);
                if (is_array($mark_text) || !empty($mark_text)){
                    if($markShowMethod == 'all'){
                        foreach ($mark_text as $im) {
                            $str_mark_text .= $im['op_content'];
                        }
                    }else{
                        $_memo = array_pop($mark_text);
                        $str_mark_text = $_memo['op_content'];
                    }
                }
                $rows[$k]['mark_text'] = $str_mark_text;
            }else{
                $rows[$k]['mark_text'] = '-';
            }
            unset($row,$_rows,$product_info);
        }

        $item=array();
        $i=0;
        foreach($rows as $key=>$row){
            $ship_addr_arr = explode(':', $row['ship_area']);
            $rows[$key]['ship_area'] = $ship_addr_arr[1];
            $member = array();
            $memberObj = app::get('ome')->model('members');
            $member = $memberObj->getList('uname',array('member_id'=>$row['member_id']),0,1);
            $rows[$key]['member_id'] = $member[0]['uname'];
            $rows[$key]['order_bn'] .= "\t";
            $rows[$key]['logi_no'] .= "\t";
            $item_id = $row['item_id'];
            if(isset($item[$item_id])){
                $i++;
                $rows[$key]['item_id']= $item_id.'_'.$i;
            }else{
                $item[$item_id]=$item_id;
                $rows[$key]['item_id']= $item_id;
            }
            $rows[$key]['delivery_bn'] = $outer_delivery[$row['delivery_bn']]["delivery_bn"]."\t";
            //加上了扩展导出字段delivery_cost_expect预计物流费用
            $rows[$key]['delivery_cost_expect'] = $outer_delivery[$row['delivery_bn']]["delivery_cost_expect"];
        }

        //导出数据客户敏感信息处理
        $securityLib = kernel::single('ome_security_customer');
        $securityLib->check_sensitive_info($rows , 'omedlyexport_mdl_ome_delivery', $op_id);

        //error_log(var_export($rows,true)."\n\t",3,"/www/be.log");
        $crows = $this->convert($rows, $fields, $has_detail);
        //error_log(var_export($crows,true)."\n\t",3,"/www/af.log");

        //使用csv的方式格式化导出数据
        $new_rows = $this->formatCsvExport($crows);

        $export_arr['content']['main'] = array();
        //如果是第一分片那么加上标题
        if($curr_sheet == 1){

            $title = array();
            $main_schema = $this->get_schemas();
            $detail_schema = $this->get_exp_detail_schema();
            //error_log(var_export($new_rows,true)."\n\t",3,"/www/new_rows.log");

            foreach (explode(',', $fields) as $key => $col) {
                if(isset($main_schema['columns'][$col])){
                    $title[] = "*:".$main_schema['columns'][$col]['label'];
                }
            }

            if($has_detail == 1){
                foreach ($detail_schema['columns'] as $key => $col) {
                    $title[] = "*:".$col['label'];
                }
            }

            foreach ((array)$title as $key => $value) {
                $title[$key] = mb_convert_encoding($value, 'GBK', 'UTF-8');
            }

            $export_arr['content']['main'][0] = implode(',', $title);
            unset($main_schema, $detail_schema);
        }

        $new_line = 1;
        foreach($new_rows as $row => $content){
            $tmp_arr = array();
            foreach ($content as $value) {
                $tmp_arr[] = mb_convert_encoding($value, 'GBK', 'UTF-8');
            }
            $export_arr['content']['main'][$new_line] = implode(',', $tmp_arr);
            $new_line++;
        }
        return $export_arr;
    }

    //重写字段方法，导出格式化的时候会调用到，不在名单里的字段直接剔除
    public function _exportcolumns(){
        $main_schema = $this->get_schemas();
        $detail_schema = $this->get_exp_detail_schema();
        return array_merge($main_schema['columns'], $detail_schema['columns']);
    }

    //格式化输出的内容字段
    public function convert($rows, &$fields='', $has_detail=1){
        //反转扩展字段
        $fields = str_replace('column_custom_mark', 'custom_mark', $fields);
        $fields = str_replace('column_mark_text', 'mark_text', $fields);
        $fields = str_replace('column_tax_no', 'tax_no', $fields);
        $fields = str_replace('column_ident', 'ident', $fields);
        $fields = str_replace('column_delivery_cost_expect', 'delivery_cost_expect', $fields);

        $tmp_rows = array();
        $schema = $this->get_schemas();
        $detail_schema = $this->get_exp_detail_schema();
        //针对大而全的数据做格式化过滤，如果包含明细
        if($has_detail == 1){
            /*
            //找出不要的字段
            foreach($schema['in_list'] as $sk => $col){
                //将需要的字段从所有字段数组里去掉
                if(strpos($fields, $col) !== false){
                    unset($schema['in_list'][$sk]);
                }
            }

            foreach($rows as $key=>$row){
                foreach ($row as $column => $value) {
                    //不要的字段去掉
                    if(!in_array($column, $schema['in_list'])){
                        $tmp_rows[$key][$column] = $value;
                    }
                }
            }
            */

            //先处理主数据的排序
            foreach (explode(',', $fields) as $k => $col) {
                foreach ($rows as $key=>$row) {
                    foreach ($row as $cl => $value) {
                        //只保留配置的主字段
                        if($col == $cl){
                            $tmp_rows[$key][$col] = $row[$col] ? $row[$col] : '-';
                        }
                    }
                }
            }

            //继续处理明细数据的排序
            foreach ($detail_schema['columns'] as $col => $arr) {
                foreach ($rows as $key=>$row) {
                    foreach ($row as $cl => $value) {
                        //只保留配置的主字段
                        if($col == $cl){
                            $tmp_rows[$key][$col] = $row[$col] ? $row[$col] : '-';
                        }
                    }
                }
            }

        }else{
            //先将数组合并,去掉重复记录
            foreach($rows as $key=>$row){
                $tmp_deliverys_bn = array();
                if(!$tmp_deliverys_bn[$row['delivery_bn']]){
                    $tmp_deliverys_bn[$row['delivery_bn']] = $row['delivery_bn'];
                }else{
                    unset($row[$key]);
                }
            }

            foreach (explode(',', $fields) as $k => $col) {
                foreach ($rows as $key=>$row) {
                    foreach ($row as $cl => $value) {
                        //只保留配置的主字段
                        if($col == $cl){
                            $tmp_rows[$key][$col] = $row[$col];
                        }
                    }
                }
            }
        }

        return $tmp_rows;

    }

    public function get_schemas(){
        $schema = array (
            'columns' => array (
                'order_bn' => array(
                    'type' => 'varchar(32)',
                    'required' => true,
                    'label' => '订单号',
                    'width' => 125,
                    'editable' => false,
                ),
                'shop_id' => array(
                    'type' => 'table:shop@ome',
                    'label' => '来源店铺',
                    'width' => 75,
                    'editable' => false,
                ),
                'tax_no' => array(
                    'type' => 'varchar(50)',
                    'label' => '发票号',
                    'editable' => false,
                ),
                'member_id' => array(
                    'type' => 'varchar(50)',
                    'label' => '会员用户名',
                    'comment' => '订货会员ID',
                    'editable' => false,
                    'width' =>75,
                ),
                'logi_name' => array(
                    'type' => 'varchar(100)',
                    'label' => '物流公司',
                    'comment' => '物流公司名称',
                    'editable' => false,
                    'width' =>75,
                ),
                'freight' => array(
                    'type' => 'money',
                    'default' => '0',
                    'required' => true,
                    'label' => '配送费用',
                    'width' => 70,
                    'editable' => false,
                ),
                'logi_no' => array(
                    'type' => 'varchar(50)',
                    'default' => '0',
                    'label' => '物流单号',
                    'width' => 70,
                    'editable' => false,
                ),
                'ship_addr' => array(
                    'type' => 'varchar(100)',
                    'label' => '收货地址',
                    'comment' => '收货人地址',
                    'editable' => false,
                ),
                'ship_area' => array(
                    'type' => 'region',
                    'label' => '收货地区',
                    'comment' => '收货人地区',
                    'editable' => false,
                ),
                'ship_name' => array(
                    'type' => 'varchar(50)',
                    'label' => '收货人',
                    'comment' => '收货人姓名',
                    'editable' => false,
                ),
                'ship_tel' => array(
                    'type' => 'varchar(30)',
                    'label' => '收货人电话',
                    'comment' => '收货人电话',
                    'editable' => false,
                ),
                'ship_mobile' => array(
                    'type' => 'varchar(50)',
                    'label' => '收货人手机',
                    'comment' => '收货人手机',
                    'editable' => false,
                ),
                'ship_zip' => array(
                    'type' => 'varchar(20)',
                    'label' => '收货邮编',
                    'comment' => '收货人邮编',
                    'editable' => false,
                ),
                'delivery_time' => array(
                    'type' => 'time',
                    'label' => '发货时间',
                    'comment' => '发货时间',
                    'editable' => false,
                ),
                'delivery_bn' => array(
                    'type' => 'varchar(32)',
                    'label' => '发货单号',
                    'comment' => '配送流水号',
                    'editable' => false,
                ),
                'ident' => array(
                    'type' => 'varchar(64)',
                    'label' => '批次号',
                    'width' => 70,
                    'comment' => '本次打印的批次号',
                    'editable' => false,
                ),
                'custom_mark' => array(
                    'type' => 'longtext',
                    'label' => '买家留言',
                    'editable' => false,
                ),
                'mark_text' => array(
                    'type' => 'longtext',
                    'label' => '客服备注',
                    'editable' => false,
                ),
                'branch_id' => array(
                    'type' => 'number',
                    'editable' => false,
                    'label' => '发货仓库',
                    'width' => 110,
                ),
                'create_time'=>array (
                    'type' => 'time',
                    'label' => '创建时间',
                    'editable' => false,
                ),
                'delivery_cost_expect'=>array (
                    'type' => 'money',
                    'label' => '预计物流费用',
                    'editable' => false,
                ),
            ),
            'idColumn' => 'bn',
            'in_list' => array(
                0 => 'order_bn',
                1 => 'shop_id',
                2 => 'tax_no',
                3 => 'member_id',
                4 => 'logi_name',
                //5 => 'bn',
                //6 => 'product_name',
                //7 => 'number',
                //8 => 'price',
                9 => 'freight',
                //10 => 'total',
                11 => 'logi_no',
                12 => 'ship_addr',
                13 => 'ship_area',
                14 => 'ship_name',
                15 => 'ship_tel',
                16 => 'ship_mobile',
                17 => 'ship_zip',
                18 => 'delivery_time',
                19 => 'delivery_bn',
                //20 => 'item_id',
                //21=> 'store_position',
                //22=> 'spec_value',
                23=> 'ident',
                24=>'custom_mark',
                25=>'mark_text',
                26=> 'branch_id',
                27=>'create_time',
                28=>'delivery_cost_expect',
            ),
            'default_in_list' => array(
                0 => 'order_bn',
                1 => 'shop_id',
                2 => 'tax_no',
                3 => 'member_id',
                4 => 'logi_name',
                //5 => 'bn',
                //6 => 'product_name',
                //7 => 'number',
                //8 => 'price',
                9 => 'freight',
                //10 => 'total',
                11 => 'logi_no',
                12 => 'ship_addr',
                13 => 'ship_area',
                14 => 'ship_name',
                15 => 'ship_tel',
                16 => 'ship_mobile',
                17 => 'ship_zip',
                18 => 'delivery_time',
                19 => 'delivery_bn',
                //20 => 'item_id',
                //21=>  'store_position',
                //22=> 'spec_value',
                23=> 'ident',
                24=>'custom_mark',
                25=>'mark_text',
                26=>'branch_id',
                27=>'create_time',
                28=>'delivery_cost_expect',
            ),
        );
        return $schema;
    }

    public function disabled_export_cols(&$cols){
        $cols['order_bn'] = array(
            'type' => 'varchar(32)',
            'required' => true,
            'label' => '订单号',
            'width' => 125,
            'editable' => false,
        );
        $cols['logi_no'] = array(
            'type' => 'varchar(50)',
            'default' => '0',
            'label' => '物流单号',
            'width' => 70,
            'editable' => false,
        );
        unset($cols['is_protect'],  $cols['column_ident'],$cols['last_modified'],$cols['memo'],$cols['column_status'],$cols['column_process_status'],$cols['column_print_status'],$cols['column_create'],$cols['column_beartime'],$cols['column_deliveryNumInfo'],$cols['column_content'],$cols['delivery_group'],$cols['sms_group'],$cols['ship_email']);

    }


    public function get_Schema()
    {
        $data = parent::get_Schema();
        $data['columns']['stock_status']= array(
            'type' => 'bool',
            'filtertype' => 'normal',
            'filterdefault' => true,
            'default' => 'false',
            'comment' => '配货单是否打印',
            'label' => '备货单打印',
        );
        $data['columns']['deliv_status']= array(
            'type' => 'bool',
            'filtertype' => 'normal',
            'filterdefault' => true,
            'default' => 'false',
            'comment' => '商品清单是否打印',
            'label' => '发货单打印',
        );
        $data['columns']['expre_status']= array(
            'type' => 'bool',
            'filtertype' => 'normal',
            'filterdefault' => true,
            'default' => 'false',
            'comment' => '快递单是否打印',
            'label' => '快递单打印',
        );
        return $data;
    }

    public function modifier_ship_name($ship_name,$list,$row)
    {
        if ($this->is_export_data) {
            if ('false' != app::get('ome')->getConf('ome.sensitive.exportdata.encrypt')) {
                return kernel::single('ome_view_helper2')->modifier_ciphertext($ship_name,'delivery','ship_name');
            }
            return $ship_name;
        }

        $is_encrypt = kernel::single('ome_security_hash')->check_encrypt($ship_name);

        if (!$is_encrypt) return $ship_name;

        $base_url = kernel::base_url(1);$delivery_id = $row['delivery_id'];
        $encryptShipName = kernel::single('ome_view_helper2')->modifier_ciphertext($ship_name,'delivery','ship_name');
$return =<<<HTML
<a class="data-hide" href="javascript:void(0);" onclick="Ex_Loader('security',function(){new Security({url:'index.php?app=wms&ctl=admin_delivery&act=showSensitiveData&p[0]={$delivery_id}',clickElement:\$(event.target)}).desHtml(\$(event.target).getNext()); });"></a><span><span sensitive-field="ship_name">{$encryptShipName}</span></span>
HTML;
        return $ship_name?$return:$ship_name;
    }

    public function modifier_ship_tel($tel,$list,$row)
    {
        if ($this->is_export_data) {
            if ('false' != app::get('ome')->getConf('ome.sensitive.exportdata.encrypt')) {
                return kernel::single('ome_view_helper2')->modifier_ciphertext($tel,'delivery','ship_tel');
            }
            return $tel;
        }

        $is_encrypt = kernel::single('ome_security_hash')->check_encrypt($tel);

        if (!$is_encrypt) return $tel;

        $base_url = kernel::base_url(1);$delivery_id = $row['delivery_id'];
        $encryptTel = kernel::single('ome_view_helper2')->modifier_ciphertext($tel,'delivery','ship_tel');
$return =<<<HTML
<a class="data-hide" href="javascript:void(0);" onclick="Ex_Loader('security',function(){new Security({url:'index.php?app=wms&ctl=admin_delivery&act=showSensitiveData&p[0]={$delivery_id}',clickElement:\$(event.target)}).desHtml(\$(event.target).getNext()); });"></a><span><span sensitive-field="ship_tel">{$encryptTel}</span></span>
HTML;
        return $tel?$return:$tel;
    }

    public function modifier_ship_mobile($mobile,$list,$row)
    {
        if ($this->is_export_data) {
            if ('false' != app::get('ome')->getConf('ome.sensitive.exportdata.encrypt')) {
                return kernel::single('ome_view_helper2')->modifier_ciphertext($mobile,'delivery','ship_mobile');
            }
            return $mobile;
        }

        $is_encrypt = kernel::single('ome_security_hash')->check_encrypt($mobile);

        if (!$is_encrypt) return $mobile;

        $base_url = kernel::base_url(1);$delivery_id = $row['delivery_id'];
        $encryptMobile = kernel::single('ome_view_helper2')->modifier_ciphertext($mobile,'delivery','ship_mobile');
$return =<<<HTML
<a class="data-hide" href="javascript:void(0);" onclick="Ex_Loader('security',function(){new Security({url:'index.php?app=wms&ctl=admin_delivery&act=showSensitiveData&p[0]={$delivery_id}',clickElement:\$(event.target)}).desHtml(\$(event.target).getNext()); });"></a><span><span sensitive-field="ship_mobile">{$encryptMobile}</span></span>
HTML;
        return $mobile?$return:$mobile;
    }

    public function modifier_ship_addr($ship_addr,$list,$row)
    {
        if ($this->is_export_data) {
            if ('false' != app::get('ome')->getConf('ome.sensitive.exportdata.encrypt')) {
                return kernel::single('ome_view_helper2')->modifier_ciphertext($ship_addr,'delivery','ship_addr');
            }
            return $ship_addr;
        }

        $is_encrypt = kernel::single('ome_security_hash')->check_encrypt($ship_addr);

        if (!$is_encrypt) return $ship_addr;

        $base_url = kernel::base_url(1);$delivery_id = $row['delivery_id'];
        $encryptAddr = kernel::single('ome_view_helper2')->modifier_ciphertext($ship_addr,'delivery','ship_addr');
$return =<<<HTML
<a class="data-hide" href="javascript:void(0);" onclick="Ex_Loader('security',function(){new Security({url:'index.php?app=wms&ctl=admin_delivery&act=showSensitiveData&p[0]={$delivery_id}',clickElement:\$(event.target)}).desHtml(\$(event.target).getNext()); });"></a><span><span sensitive-field="ship_addr">{$encryptAddr}</span></span>
HTML;
        return $ship_addr?$return:$ship_addr;
    }

    public function modifier_member_id($member_id,$list,$row)
    {
        static $members;

        if (!$member_id) return '';

        if ($members) return $members[$row['delivery_id']];

        $members = array ();
        foreach ($list as $key => $value) {
            $members[$value['delivery_id']] = $value['member_id'];
        }

        $rows = array ();
        if ($mid = array_filter($members)) {
            $mdl  = app::get('ome')->model('members');
            foreach ($mdl->getList('member_id,uname',array('member_id'=>$mid)) as $value) {
                $rows[$value['member_id']] = $value['uname'];
            }
        }

        foreach ($members as $delivery_id => $value) {
            $uname = $rows[$value];
            $is_encrypt = kernel::single('ome_security_hash')->check_encrypt($uname);
            if ($is_encrypt) {
                $encrypt = kernel::single('ome_view_helper2')->modifier_ciphertext($uname,'delivery','uname');
                $uname = <<<HTML
<a class="data-hide" href="javascript:void(0);" onclick="Ex_Loader('security',function(){new Security({url:'index.php?app=wms&ctl=admin_delivery&act=showSensitiveData&p[0]={$delivery_id}',clickElement:\$(event.target)}).desHtml(\$(event.target).getNext()); });"></a><span><span sensitive-field="uname">{$encrypt}</span></span>
HTML;
            }

            $members[$delivery_id] = $uname;
        }

        return $members[$row['delivery_id']];
    }


}
?>
