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
class console_mdl_delivery extends dbeav_model{
    var $defaultOrder = array('delivery_id',' DESC');
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
   public function table_name($real = false){
        if($real){
           $table_name = 'sdb_ome_delivery';
        }else{
           $table_name = 'delivery';
        }
        return $table_name;
	}

    public function get_schema(){
        return app::get('ome')->model('delivery')->get_schema();
    }

    function searchOptions(){
        $parentOptions = parent::searchOptions();
        $childOptions = array(
            'delivery_bn'=>app::get('base')->_('发货单号'),
            'order_bn'=>app::get('base')->_('订单号'),
            'member_uname'=>app::get('base')->_('用户名'),
            'ship_name'=>app::get('base')->_('收货人'),
            'ship_tel_mobile'=>app::get('base')->_('联系电话'),
            'product_bn'=>app::get('base')->_('货号'),
            'product_barcode'=>app::get('base')->_('条形码'),
            'delivery_ident'=>app::get('base')->_('打印批次号'),
        );
        return array_merge($childOptions,$parentOptions);
    }

    public function _filter($filter,$tableAlias=null,$baseWhere=null)
    {
        $baseWhere = (array) $baseWhere;
        $tPre = ($tableAlias?$tableAlias:'`'.$this->table_name(true).'`').'.';

        //setting
        $deliveryIds = array();
        $where = '';

        if(isset($filter['ship_tel_mobile'])){
            $encryptVal = kernel::single('ome_security_factory')->encryptPublic($filter['ship_tel_mobile'],'phone');
            $encryptVal  = utils::addslashes_array($encryptVal);
            $originalVal = utils::addslashes_array($filter['ship_tel_mobile']);

            $baseWhere[] = "({$tPre}ship_tel IN('".$originalVal."','".$encryptVal."')||{$tPre}ship_mobile IN('".$originalVal."','".$encryptVal."'))";

            unset($filter['ship_tel_mobile']);
        }

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

        if(isset($filter['outer_dlybn'])) {
            if(strpos($filter['outer_dlybn'], "\n") !== false){
                $filter['outer_dlybn'] = array_unique(array_map('trim', array_filter(explode("\n", $filter['outer_dlybn']))));
            }
            $rows = app::get('console')->model('delivery_extension')->getList('delivery_bn',['original_delivery_bn'=>$filter['outer_dlybn']]);
            unset($filter['outer_dlybn']);
            $rows = array_column($rows, 'delivery_bn');
            $rows[] = 0;
            $where .= '  AND delivery_bn IN ("'.implode('","', $rows).'")';
        }

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

            //delivery_id
            $tempDlyIds = array(0);
            foreach($rows as $row)
            {
                $temp_dly_id = $row['delivery_id'];

                $tempDlyIds[$temp_dly_id] = $temp_dly_id;
            }

            //intersection
            if(empty($deliveryIds)){
                $deliveryIds = $tempDlyIds;
            }else{
                $deliveryIds = array_intersect($deliveryIds, $tempDlyIds);
            }

            unset($filter['order_bn']);
        }

        //按基础物料搜索
        if (isset($filter['material_bn'])){
            //多货号查询
            if(strpos($filter['material_bn'], "\n") !== false){
                $material_bns = array_unique(array_map('trim', array_filter(explode("\n", $filter['material_bn']))));

                $filter['material_bn'] = $material_bns;
            }

            //search_delivery_items
            $itemsObj = app::get('ome')->model("delivery_items");
            $rows = $itemsObj->getDeliveryIdByPbn($filter);

            //delivery_id
            $tempDlyIds = array(0);
            foreach($rows as $row)
            {
                $temp_dly_id = $row['delivery_id'];

                $tempDlyIds[$temp_dly_id] = $temp_dly_id;
            }

            //intersection
            if(empty($deliveryIds)){
                $deliveryIds = $tempDlyIds;
            }else{
                $deliveryIds = array_intersect($deliveryIds, $tempDlyIds);
            }

            //unset
            unset($filter['material_bn']);
        }

        //按货号搜索
        if(isset($filter['product_bn'])){
            //search_delivery_items
            $itemsObj = app::get('ome')->model("delivery_items");
            $rows = $itemsObj->getDeliveryIdByFilter($filter);

            //delivery_id
            $tempDlyIds = array(0);
            foreach($rows as $row)
            {
                $temp_dly_id = $row['delivery_id'];

                $tempDlyIds[$temp_dly_id] = $temp_dly_id;
            }

            //intersection
            if(empty($deliveryIds)){
                $deliveryIds = $tempDlyIds;
            }else{
                $deliveryIds = array_intersect($deliveryIds, $tempDlyIds);
            }

            //unset
            unset($filter['product_bn']);
        }

        //按条形码搜索
        if(isset($filter['product_barcode'])){
            //search_delivery_items
            $itemsObj = app::get('ome')->model("delivery_items");

            //获取条形码关联的货号
            $codeSql = "SELECT a.code, b.bm_id,b.material_bn FROM sdb_material_codebase AS a LEFT JOIN sdb_material_basic_material AS b ON a.bm_id=b.bm_id ";
            $codeSql .= " WHERE a.code='". addslashes($filter['product_barcode']) ."'";
            $materialInfo = $itemsObj->db->selectrow($codeSql);

            //用货号进行搜索
            $filter['product_bn'] = $materialInfo['material_bn'];

            //unset(一定要注销掉,防止重复调用)
            unset($filter['product_barcode']);

            //list
            $rows = $itemsObj->getDeliveryIdByFilter($filter);

            //delivery_id
            $tempDlyIds = array(0);
            foreach($rows as $row)
            {
                $temp_dly_id = $row['delivery_id'];

                $tempDlyIds[$temp_dly_id] = $temp_dly_id;
            }

            //intersection
            if(empty($deliveryIds)){
                $deliveryIds = $tempDlyIds;
            }else{
                $deliveryIds = array_intersect($deliveryIds, $tempDlyIds);
            }

            //unset
            unset($filter['product_bn']);
        }

        if(isset($filter['logi_no_ext'])){
            $logObj = app::get('ome')->model("delivery_log");
            $rows = $logObj->getDeliveryIdByLogiNO($filter['logi_no_ext']);

            //delivery_id
            $tempDlyIds = array(0);
            foreach($rows as $row)
            {
                $temp_dly_id = $row['delivery_id'];

                $tempDlyIds[$temp_dly_id] = $temp_dly_id;
            }

            //intersection
            if(empty($deliveryIds)){
                $deliveryIds = $tempDlyIds;
            }else{
                $deliveryIds = array_intersect($deliveryIds, $tempDlyIds);
            }

            unset($filter['logi_no_ext']);
        }

        if(isset($filter['addonSQL'])){
            $where .= ' AND '.$filter['addonSQL'];
            unset($filter['addonSQL']);
        }

        if(isset($filter['delivery_ident'])){
            //delivery_id
            $tempDlyIds = array(0);

            $arr_delivery_ident = explode('_',$filter['delivery_ident']);
            $mdl_queue = app::get('ome')->model("print_queue");
            if(count($arr_delivery_ident) == 2){
                $ident_dly = array_pop($arr_delivery_ident);
                $ident = implode('-',$arr_delivery_ident);
                $queueItem = $mdl_queue->findQueueItem($ident,$ident_dly);
                if($queueItem){
                    $temp_dly_id = $queueItem['delivery_id'];
                    $tempDlyIds[$temp_dly_id] = $temp_dly_id;
                }
            }else{
                if (1 == substr_count($filter['delivery_ident'], '-')) {
                    $queues = $mdl_queue->getList('dly_bns',array('ident|head'=>$filter['delivery_ident']));
                    if ($queues){
                        //$queue['dly_bns'] = implode(',', array_map('current', $queues));
                        $tempDlyIds = array_map('current', $queues);
                    }
                } else {
                    //获取实际的打印批次号
                    $delivery_id = $mdl_queue->findQueueDeliveryId($filter['delivery_ident'],'delivery_id');
                    if($delivery_id){
                        //$queue['dly_bns'] = $delivery_id;
                        $tempDlyIds = explode(',', $delivery_id);
                    }
                }
            }

            //intersection
            if(empty($deliveryIds)){
                $deliveryIds = $tempDlyIds;
            }else{
                $deliveryIds = array_intersect($deliveryIds, $tempDlyIds);
            }

            unset($filter['delivery_ident']);
        }
        if($filter['todo']==1){
            $where .= " AND (stock_status='false' or expre_status='false' or deliv_status='false')";
            unset($filter['todo']);
        }
        if($filter['todo']==2){
            $where .= " AND (stock_status='false' or expre_status='false')";
            unset($filter['todo']);
        }
        if($filter['todo']==3){
            $where .= " AND (expre_status='false' or deliv_status='false')";
            unset($filter['todo']);
        }
        if($filter['todo']==4){
            $where .= " AND expre_status='false'";
            unset($filter['todo']);
        }

        if (isset($filter['print_finish'])) {
            $where_or = array();
            foreach((array)$filter['print_finish'] as $key=> $value){
                $or = "(deli_cfg='".$key."'";
                switch($value) {
                    case '1_1':
                        $or .= " AND stock_status='true' AND deliv_status='true' ";
                        break;
                    case '1_0':
                        $or .= " AND stock_status='true' ";
                        break;
                    case '0_1':
                        $or .= " AND deliv_status='true' ";
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
        if(isset($filter['logi_no'])){
            $obj_delivery_bill = $deliOrderObj = app::get('ome')->model("delivery_bill");
            #获取子表物流单号
            $delivery_id = $obj_delivery_bill->dump(array('logi_no'=>$filter['logi_no']),'delivery_id');
            if(!empty($delivery_id['delivery_id'])){
                $temp_dly_id = $delivery_id['delivery_id'];

                $tempDlyIds = array(0);
                $tempDlyIds[$temp_dly_id] = $temp_dly_id;

                //intersection
                if(empty($deliveryIds)){
                    $deliveryIds = $tempDlyIds;
                }else{
                    $deliveryIds = array_intersect($deliveryIds, $tempDlyIds);
                }

                unset($filter['logi_no']);
            }
        }

        //客服备注
        if(isset($filter['mark_text'])){
            $mark_text = $filter['mark_text'];
            $sql = "SELECT do.delivery_id FROM sdb_ome_delivery_order do JOIN sdb_ome_orders o ON do.order_id=o.order_id  and o.process_status='splited' and  o.mark_text like "."'%{$mark_text}%'";
            $_rows = $this->db->select($sql);
            if(!empty($_rows)){
                $tempDlyIds = array(0);
                foreach($_rows as $_orders)
                {
                    $temp_dly_id = $_orders['delivery_id'];

                    $tempDlyIds[$temp_dly_id] = $temp_dly_id;
                }

                //intersection
                if(empty($deliveryIds)){
                    $deliveryIds = $tempDlyIds;
                }else{
                    $deliveryIds = array_intersect($deliveryIds, $tempDlyIds);
                }

                unset($filter['mark_text']);
            }

        }

        //买家留言
        if(isset($filter['custom_mark'])){
            $custom_mark = $filter['custom_mark'];
            $sql = "SELECT do.delivery_id FROM sdb_ome_delivery_order do JOIN sdb_ome_orders o ON do.order_id=o.order_id  and o.process_status='splited' and  o.custom_mark like "."'%{$custom_mark}%'";
            $_rows = $this->db->select($sql);
            if(!empty($_rows)){
                $tempDlyIds = array(0);
                foreach($_rows as $_orders)
                {
                    $temp_dly_id = $_orders['delivery_id'];

                    $tempDlyIds[$temp_dly_id] = $temp_dly_id;
                }

                //intersection
                if(empty($deliveryIds)){
                    $deliveryIds = $tempDlyIds;
                }else{
                    $deliveryIds = array_intersect($deliveryIds, $tempDlyIds);
                }

                unset($filter['custom_mark']);
            }
        }

        //订单标记
        if($filter['order_label']){
            // 优化：使用EXISTS子查询提高性能，避免SQL注入
            $where .= ' AND EXISTS (
                SELECT 1 FROM sdb_ome_bill_label bl 
                LEFT JOIN sdb_ome_delivery o ON bl.bill_id = o.delivery_id
                WHERE bl.bill_id = sdb_ome_delivery.delivery_id 
                AND bl.label_id = "'.addslashes($filter['order_label']).'"
                AND bl.bill_type = "ome_delivery"
            )';
            unset($filter['order_label']);
        }
        if (!empty($filter['order_label_code'])) {
            $where .= ' AND EXISTS (
                SELECT 1 FROM sdb_ome_bill_label bl
                WHERE bl.bill_id = sdb_ome_delivery.delivery_id 
                AND bl.label_code = "'.addslashes($filter['order_label_code']).'"
                AND bl.bill_type = "ome_delivery"
            )';
            unset($filter['order_label_code']);
        }
        #同步状态筛查
        if (isset($filter['sync_filter'])) {
            $boolSync            = kernel::single('console_delivery_bool_sync')->getBoolSync($filter['sync_filter']);
            $where .= ' AND sync IN ("'.implode('","', $boolSync).'")';
            unset($filter['sync_filter']);
        }

        //delivery_ids
        if($deliveryIds){
            $where .= " AND delivery_id IN ('". implode("','", $deliveryIds) ."')";
        }

        //订单标记
        if($filter['order_label']){
            $ordLabelObj = app::get('ome')->model('bill_label');
            $tempData = $ordLabelObj->getList('bill_id', array('label_id'=>$filter['order_label'], 'bill_type'=>'ome_delivery'));
            if($tempData){
                $orderId = array();
                foreach ($tempData as $tempKey => $tempVal)
                {
                    $temp_order_id = $tempVal['bill_id'];

                    $orderId[$temp_order_id] = $temp_order_id;
                }

                $where .= ' AND delivery_id IN ("'. implode('","', $orderId) .'")';
            }else{
                $where .= ' AND delivery_id = "-1"';
            }

            unset($filter['order_label'], $tempData);
        }
        if (isset($filter['delivery_bn'])){
            // 多发货号查询
            if(strpos($filter['delivery_bn'], "\n") !== false){
                $filter['delivery_bn'] = array_unique(array_map('trim', array_filter(explode("\n", $filter['delivery_bn']))));
            }
        }
        return parent::_filter($filter,$tableAlias,$baseWhere).$where;
    }

    /*
     * 根据订单id获取是否撤销失败发货单
     * 
     *
     * @param bigint $order_id
     *
     * @return array $ids
     */

    function getDeliveryByOrderId($order_id){
        $delivery_ids = $this->db->select("SELECT dord.delivery_id FROM sdb_ome_delivery_order AS dord
                                            LEFT JOIN sdb_ome_delivery AS d ON(dord.delivery_id=d.delivery_id)
                                            WHERE dord.order_id={$order_id} AND (d.parent_id=0 OR d.is_bind='true') AND d.disabled='false' AND d.status NOT IN('failed','cancel','back','return_back','succ') AND sync='fail'");
        $ids = array();
        if($delivery_ids){
            foreach($delivery_ids as $v){
                $ids[] = $v['delivery_id'];
            }
        }

        return $ids;
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

     public function finder_getList($cols='*', $filter=array(), $offset=0, $limit=-1, $orderType=null)
     {
         $data = parent::finder_getList($cols,$filter,$offset,$limit,$orderType);

         foreach ((array) $data as $key => $value) {
             foreach ($this->__encrypt_cols as $field => $type) {
                 if (isset($value[$field])) {
                     $data[$key][$field] = (string) kernel::single('ome_security_factory')->decryptPublic($value[$field],$type);
                 }
             }
         }

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
<a class="data-hide" href="javascript:void(0);" onclick="Ex_Loader('security',function(){new Security({url:'index.php?app=console&ctl=admin_delivery&act=showSensitiveData&p[0]={$delivery_id}',clickElement:\$(event.target)}).desHtml(\$(event.target).getNext()); });"></a><span><span sensitive-field="ship_name">{$encryptShipName}</span></span>
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
<a class="data-hide" href="javascript:void(0);" onclick="Ex_Loader('security',function(){new Security({url:'index.php?app=console&ctl=admin_delivery&act=showSensitiveData&p[0]={$delivery_id}',clickElement:\$(event.target)}).desHtml(\$(event.target).getNext()); });"></a><span><span sensitive-field="ship_tel">{$encryptTel}</span></span>
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
<a class="data-hide" href="javascript:void(0);" onclick="Ex_Loader('security',function(){new Security({url:'index.php?app=console&ctl=admin_delivery&act=showSensitiveData&p[0]={$delivery_id}',clickElement:\$(event.target)}).desHtml(\$(event.target).getNext()); });"></a><span><span sensitive-field="ship_mobile">{$encryptMobile}</span></span>
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
<a class="data-hide" href="javascript:void(0);" onclick="Ex_Loader('security',function(){new Security({url:'index.php?app=console&ctl=admin_delivery&act=showSensitiveData&p[0]={$delivery_id}',clickElement:\$(event.target)}).desHtml(\$(event.target).getNext()); });"></a><span><span sensitive-field="ship_addr">{$encryptAddr}</span></span>
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
<a class="data-hide" href="javascript:void(0);" onclick="Ex_Loader('security',function(){new Security({url:'index.php?app=console&ctl=admin_delivery&act=showSensitiveData&p[0]={$delivery_id}',clickElement:\$(event.target)}).desHtml(\$(event.target).getNext()); });"></a><span><span sensitive-field="uname">{$encrypt}</span></span>
HTML;
            }

            $members[$delivery_id] = $uname;
        }

        return $members[$row['delivery_id']];
    }

    function getOrderIdByDeliveryId($dly_ids){
        $dly_orderObj = app::get('ome')->model('delivery_order');
        $filter['delivery_id'] = $dly_ids;

        $data = $dly_orderObj->getList('order_id', $filter);
        foreach ($data as $item){
            $ids[] = $item['order_id'];
        }
        return $ids;
    }
}
