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

class ome_mdl_orders extends dbeav_model{

    //是否有导出配置
    var $has_export_cnf = true;
    //所有组用户信息
    static $__GROUPS = null;
    //所用户信息
    static $__USERS = null;

    var $has_many = array(
       //'delivery' => 'delivery', TODO:非标准写法，去掉后有报错需要修改代码
       'order_objects' => 'order_objects',
    );

    // var $defaultOrder = array('createtime DESC ,order_id DESC');
    var $defaultOrder = array('order_id DESC'); // 加上createtime排序，响应慢
    var $export_name = '订单';
    var $export_flag = false;

    static $order_source = array(
                    'local' => '分销王本地订单',
                    'fxjl' => '抓抓',
                    'taofenxiao' => '淘分销',
                    'tbjx' => '经销订单',
                    'tbdx' => '代销订单',
                    'secondbuy' => '分销王秒批订单',
                    'direct' => '直销订单',
                    'platformexchange' => '平台换货',
                    'maochao' => '猫超国际',
                    'wxshipin' => '微信视频号',
                    'miaozhu' => '喵住',
                    'goofish' => '闲鱼',
    );

    const EXPORT_ITEM_TITLE = [
        ['label' => '销售物料编码', 'col' => 'e_sm_bn'],
        ['label' => '销售物料名称', 'col' => 'e_sm_name'],
        ['label' => '销售物料类型', 'col' => 'e_sm_type'],
        ['label' => '基础物料编码', 'col' => 'e_item_bn'],
        ['label' => '基础物料名称', 'col' => 'e_item_product_name'],
        ['label' => '基础物料品牌', 'col' => 'e_brand_name'],
        ['label' => '规格', 'col' => 'e_spec_info'],
        ['label' => '单位', 'col' => 'e_unit'],
        ['label' => '原价', 'col' => 'e_price'],
        ['label' => '销售价', 'col' => 'e_sale_price'],
        ['label' => '优惠额', 'col' => 'e_pmt_price'],
        ['label' => '实付金额', 'col' => 'e_divide_order_fee'],
        ['label' => '优惠分摊', 'col' => 'e_part_mjz_discount'],
        ['label' => '购买量', 'col' => 'e_nums'],
        ['label' => '已发货量', 'col' => 'e_sendnum'],
        ['label' => '已退货量', 'col' => 'e_return_num'],
        ['label' => '已拆分量', 'col' => 'e_split_num'],
        ['label' => 'hold单时限', 'col' => 'e_estimate_con_time'],
        ['label' => '是否预售', 'col' => 'e_presale_status'],
        ['label' => '预选仓', 'col' => 'e_store_code'],
        ['label' => '子单号', 'col' => 'e_oid'],
        ['label' => '关联子单号', 'col' => 'e_main_oid'],
        ['label' => '平台商品ID', 'col' => 'e_shop_goods_id'],
        ['label' => '平台SkuID', 'col' => 'e_shop_product_id'],
        ['label' => '达人ID', 'col' => 'e_author_id'],
        ['label' => '达人名称', 'col' => 'e_author_name'],
        ['label' => '直播间ID', 'col' => 'e_room_id'],
    ];

    /**
     * 须加密字段
     * 
     * @var string
     * */
    private $__encrypt_cols = array(
        'ship_name'   => 'simple',
        'ship_tel'    => 'phone',
        'ship_mobile' => 'phone',
        'ship_addr'     => 'simple',
    );

    function _filter($filter,$tableAlias=null,$baseWhere=null)
    {
        $tPre      = ($tableAlias ? $tableAlias : '`' . $this->table_name(true) . '`') . '.';
        $tmpBaseWhere = kernel::single('ome_filter_encrypt')->encrypt($filter, $this->__encrypt_cols, $tPre, 'orders');
        $baseWhere = $baseWhere ? array_merge((array)$baseWhere, (array)$tmpBaseWhere) : (array)$tmpBaseWhere;
       
        if(isset($filter['archive'])){
            $where = ' archive='.$filter['archive'].' ';
            unset($filter['archive']);
        }else{
            $where = " 1 ";
        }
        
        if(isset($filter['tax_company'])){
            $where = ' tax_company like "'.$filter['tax_company'].'%" ';
            unset($filter['tax_company']);
        }

        if(isset($filter['order_confirm_filter'])){
            $where .= ' AND '.$filter['order_confirm_filter'];
            unset($filter['order_confirm_filter']);
        }
        
        if (isset($filter['assigned'])){
            if ($filter['assigned'] == 'notassigned'){
                $where .= ' AND (group_id=0 AND op_id=0)';
            }elseif($filter['assigned'] == 'buffer'){

            }else{
                // where (op_id is null OR op_id=0) AND (op_id > 0 OR group_id > 0) 会很影响查询效率
                $tmp_where = str_replace(' ', '', $where);
                if (stripos($tmp_where, 'op_idisnull') !== false || stripos($tmp_where, 'op_id=0') !== false) {
                    $where .= ' AND group_id > 0';
                } else {
                    $where .= '  AND (op_id > 0 OR group_id > 0)';
                }
            }

            $where  .= ' AND IF(process_status=\'is_retrial\', abnormal=\'true\', abnormal=\'false\')';
            unset ($filter['assigned'], $filter['abnormal']);
        }
        
        if (isset($filter['balance'])){
            if ($filter['balance'])
                $where .= " AND `old_amount` != 0 AND `total_amount` != `old_amount` ";
            else
                $where .= " AND `old_amount` = 0 ";
        }
        
        //自动取消订单过滤条件
        if (isset($filter['auto_cancel_order_filter'])){
            $where .= '  AND '.$filter['auto_cancel_order_filter'];
        }

        if (isset($filter['custom_process_status'])){
            if(is_array($filter['custom_process_status'])){
                $where .= '  AND process_status IN (\''.implode('\',\'', $filter['custom_process_status']).'\')';
            }elseif($filter['custom_process_status']){
                $where .= '  AND process_status ='.$filter['custom_process_status'];
            }
        }
        
        if(isset($filter['product_bn'])){
            $orderId = array();
            $orderId[] = 0;
            
            //多基础物料查询
            if($filter['product_bn'] && is_string($filter['product_bn']) && strpos($filter['product_bn'], "\n") !== false){
                $filter['product_bn'] = array_unique(array_map('trim', array_filter(explode("\n", $filter['product_bn']))));
            }
            
            //按基础物料查询相关订单
            $itemsObj = $this->app->model("order_items");
            $rows = $itemsObj->getOrderIdByFilterbnEq($filter);
            if($rows){
                foreach($rows as $row){
                    $temp_order_id = $row['order_id'];
                    $orderId[$temp_order_id] = $temp_order_id;
                }
            }
            
            if ($filter['has_bn'] == 'false') {
                $where .= '  AND order_id NOT IN ("'.implode('","', $orderId).'")';
            } else {
                $where .= '  AND order_id IN ("'.implode('","', $orderId).'")';
            }

            unset($filter['product_bn']);
        }elseif(isset($filter['sales_material_bn'])){
            $orderId = array();
            $orderId[] = 0;
            
            //赋值
            $filter['product_bn'] = $filter['sales_material_bn'];
            
            //多销售物料查询
            if($filter['product_bn'] && is_string($filter['product_bn']) && strpos($filter['product_bn'], "\n") !== false){
                $filter['product_bn'] = array_unique(array_map('trim', array_filter(explode("\n", $filter['product_bn']))));
            }
            
            //按销售物料查询相关订单
            $itemsObj = $this->app->model('order_items');
            $objectRows = $itemsObj->getOrderIdByPkgbnEq($filter);
            if($objectRows){
                foreach($objectRows as $objectItem){
                    $temp_order_id = $objectItem['order_id'];
                    
                    $orderId[$temp_order_id] = $temp_order_id;
                }
            }
            
            $where .= '  AND order_id IN ("'.implode('","', $orderId).'")';
            
            unset($filter['sales_material_bn'], $filter['product_bn']);
        }
        if (isset($filter['sales_material_name'])) {
            $orderId   = [0];
        
            //赋值
            $filter['product_name'] = $filter['sales_material_name'];
            //多销售物料查询
            if ($filter['sales_material_name'] && is_string($filter['sales_material_name']) && strpos($filter['sales_material_name'], "\n") !== false) {
                $filter['sales_material_name'] = array_unique(array_map('trim', array_filter(explode("\n", $filter['sales_material_name']))));
            }
        
            //按销售物料查询相关订单
            $itemsObj   = $this->app->model('order_items');
            $objectRows = $itemsObj->getOrderIdByFilterNameEq($filter);
            if ($objectRows) {
                foreach ($objectRows as $objectItem) {
                    $temp_order_id           = $objectItem['order_id'];
                    $orderId[$temp_order_id] = $temp_order_id;
                }
            }
        
            $where .= '  AND order_id IN ("' . implode('","', $orderId) . '")';
        
            unset($filter['sales_material_name']);
        }
        
        if ( $filter['ship_area'] ) {
            if($filter['ship_area'] && is_string($filter['ship_area']) && strpos($filter['ship_area'], "\n") !== false){
                $filter['ship_area'] = implode("|",array_unique(array_map('trim', array_filter(explode("\n", $filter['ship_area'])))));
            }
            $areaWhere = $where .' AND ship_area REGEXP \''.$filter['ship_area'].'\'';
            if ($filter['has_area'] == 'false') {
                $orderIds = $this->getArea($areaWhere);
                $where .= ' AND order_id not in ("'.implode('","',$orderIds).'")';
            }else{
                $where = $areaWhere;
            }
            unset($filter['ship_area']);
        }

        //支付失败
        if(isset($filter['payment_fail']) && $filter['payment_fail'] == true){
            $api_fail = $this->app->model("api_fail");
            $payment_fail_list = $api_fail->getList('order_id', array('type'=>'payment'), 0, -1);
            $payment_order_id = array();
            if ($payment_fail_list){
                foreach($payment_fail_list as $val){
                    $payment_order_id[] = $val['order_id'];
                }
            }
            $payment_order_id = implode('","', $payment_order_id);
            $payment_order_id =  $payment_order_id ? $payment_order_id : '';
            $where .= '  AND order_id IN ("'.$payment_order_id.'")';
            unset($filter['payment_fail']);
        }

        if(isset($filter['product_barcode'])){
            $itemsObj = $this->app->model("order_items");
            $rows = $itemsObj->getOrderIdByPbarcode($filter['product_barcode']);
            $orderId[] = 0;
            foreach($rows as $row){
                $orderId[] = $row['order_id'];
            }
            $where .= '  AND order_id IN ("'.implode('","', $orderId).'")';
            unset($filter['product_barcode']);
        }
        //判断是否录入发票号
        if(isset($filter['is_tax_no'])){
            if($filter['is_tax_no']==1){
                $where .= '  AND tax_no IS NOT NULL';

            }else{
                $where .= '  AND tax_no IS NULL';
            }
            unset($filter['is_tax_no']);
        }
        
        //付款确认
        if (isset($filter['pay_confirm'])){
            $where .= ' AND '.$filter['pay_confirm'];
            unset($filter['pay_confirm']);
        }
        //确认状态
        if (isset($filter['process_status_noequal'])){
            $value = '';
            foreach($filter['process_status_noequal'] as $k=>$v){
                $value .= "'".$v."',";
            }
            $len = strlen($value);
            $value_last = substr($value,0,($len-1));
            $where .= ' AND process_status not in ( '.$value_last.")";
            unset($filter['process_status_noequal']);
        }
        if (isset($filter['member_uname'])){
            $memberId = app::get('ome')->model('members')->getMemberIdByUname($filter['member_uname']);
            $where .= '  AND member_id IN ("'.implode('","', $memberId).'")';
            unset($filter['member_uname']);
        }
        if (isset($filter['pay_type'])){
            $cfgObj = app::get('ome')->model('payment_cfg');
            $rows = $cfgObj->getList('pay_bn',array('pay_type'=>$filter['pay_type']));
            $pay_bn[] = 0;
            foreach($rows as $row){
                $pay_bn[] = $row['pay_bn'];
            }
            $where .= '  AND pay_bn IN (\''.implode('\',\'', $pay_bn).'\')';
            unset($filter['pay_type']);
        }
        //部分支付 包含部分退款 部分支付
        if(isset($filter['pay_status_part'])){
            $where .= ' AND (pay_status = \'3\' or (pay_status = \'4\' and ship_status = \'0\'))';
            unset($filter['pay_status_part']);
        }
        //付款确认时，部分退款的只有未发货的才能继续支付
        if(isset($filter['pay_status_set'])){
            if($filter['pay_status_set'] == 2){
                $where .= ' AND (pay_status in (\'0\',\'3\') or (pay_status = \'4\' and ship_status = \'0\'))';
            }else{
                $where .= ' AND (pay_status in (\'0\',\'3\',\'8\') or (pay_status = \'4\' and ship_status = \'0\'))';
            }
            unset($filter['pay_status_set']);
        }
        
        #支付方式搜索
        if (isset($filter['pay_bn'])){
            $cfgObj = app::get('ome')->model('payment_cfg');
            $_rows = $cfgObj->getList('custom_name',array('pay_bn'=>$filter['pay_bn']));
            $where .= '  AND payment='."'{$_rows[0]['custom_name']}' ";
            unset($_rows);
            unset($filter['pay_bn']);
        }

        if( isset($filter['order_source']) && $filter['order_source'] ){

            $order_source = array_keys(self::$order_source);

            if(in_array($filter['order_source'], $order_source) && $filter['order_source']!='direct'){
                $where .=' AND order_source = \''.$filter['order_source'].'\'';
            }else{
                $where .=' AND order_source not in ( \''.implode('\',\'', $order_source).'\')';

            }

            unset($filter['order_source']);
        }
        if(isset($filter['logi_no'])&&$filter['logi_no']){
            #使用子表物流单号进行搜索
            if(is_string($filter['logi_no']) && strpos($filter['logi_no'], "\n") !== false){
                $filter['logi_no'] = array_unique(array_map('trim', array_filter(explode("\n", $filter['logi_no']))));
            }
            if (!is_array($filter['logi_no'])) {
                $filter['logi_no'] = array($filter['logi_no']);
            }
            $sql = 'select
                            orders.order_id
                        from sdb_ome_delivery_bill bill
                        join   sdb_ome_delivery  delivery
                        on bill.delivery_id=delivery.delivery_id and bill.logi_no in ("'.implode('","',$filter['logi_no']).'")
                        join  sdb_ome_delivery_order  orders on  delivery.delivery_id=orders.delivery_id';
            $_row = $this->db->select($sql);
            if(!empty($_row['order_id'])){
                unset($filter['logi_no']);
                $where .= ' AND order_id IN ("'.implode('","', array_column($_row,'order_id')).'")';
            }
        }
        // 商家备注是否存在
        if (isset($filter['is_mark_text'])) {
            if($filter['is_mark_text'] == 'true'){
                $where .= ' AND mark_text IS NOT NULL';
            }else {
                $where .= ' AND mark_text IS NULL';
            }
            unset($filter['is_mark_text']);
        }
        #商家备注
        if(isset($filter['mark_text']) && !isset($filter['is_mark_text'])){
            $mark_text = trim($filter['mark_text']);
            $sql = "SELECT order_id,mark_text FROM   sdb_ome_orders where  mark_text like "."'%{$mark_text}%'";
            $_rows = $this->db->select($sql);
            if(!empty($_rows)){
                foreach($_rows as $_orders){
                    $_order_id[] = $_orders['order_id'];
                };
                if (isset($filter['_mark_text_search']) && $filter['_mark_text_search'] == 'nohas') {
                    $where .= ' AND order_id NOT IN ("'.implode('","', $_order_id).'")';
                } else {
                    $where .= ' AND order_id IN ("'.implode('","', $_order_id).'")';
                }
                unset($filter['mark_text']);
            }
        }
        
        // 客户备注是否存在
        if (isset($filter['is_custom_mark'])) {
            if($filter['is_custom_mark']=='true'){
                $where .= '  AND custom_mark IS NOT NULL';
            }else {
                $where .= '  AND custom_mark IS NULL';
            }
            unset($filter['is_custom_mark']);
        }
        
        #客户备注
        if(isset($filter['custom_mark']) && !isset($filter['is_custom_mark'])){
            $custom_mark = trim($filter['custom_mark']);
            $sql = "SELECT order_id,custom_mark FROM   sdb_ome_orders where  custom_mark like "."'%{$custom_mark}%'";
            $_rows = $this->db->select($sql);
            if(!empty($_rows)){
                foreach($_rows as $_orders){
                    $_order_id[] = $_orders['order_id'];
                };
                if (isset($filter['_custom_mark_search']) && $filter['_custom_mark_search'] == 'nohas') {
                    $where .= ' AND order_id NOT IN ("'.implode('","', $_order_id).'")';
                } else {
                    $where .= ' AND order_id IN ("'.implode('","', $_order_id).'")';
                }
                unset($filter['custom_mark']);
            }
        }
        
        //是否签收
        if(isset($filter['logi_status'])){
            $sql = 'select
                        orders.order_id
                    from sdb_ome_delivery  delivery
                    left join  sdb_ome_delivery_order  orders on  delivery.delivery_id=orders.delivery_id
                    where delivery.logi_status='."'". addslashes($filter['logi_status'])."'";

            $where .= ' AND order_id IN ('.$sql.')';

            unset($filter['logi_status']);
        }

        if (isset($filter['is_relate_order_bn'])){
            if ($filter['is_relate_order_bn'] == 1){
                $where .= " AND relate_order_bn!=''";
            }
            if ($filter['is_relate_order_bn'] == 0){
                $where .= " AND (relate_order_bn='' or relate_order_bn is null)";
            }
            unset($filter['is_relate_order_bn']);
        }
        
        //模糊搜索订单号
        if($filter['head_order_bn']){
            $filter['head_order_bn'] = str_replace(array('"', "'"), '', $filter['head_order_bn']);
            
            $where .= " AND order_bn LIKE '". $filter['head_order_bn'] ."%'";
            
            unset($filter['head_order_bn'], $filter['order_bn']);
            
        }else{
            // 多订单号查询
            if($filter['order_bn'] && is_string($filter['order_bn']) && strpos($filter['order_bn'], "\n") !== false){
                $filter['order_bn'] = array_unique(array_map('trim', array_filter(explode("\n", $filter['order_bn']))));
            }
        }

        // 多关联订单号查询
        if($filter['relate_order_bn'] && is_string($filter['relate_order_bn']) && strpos($filter['relate_order_bn'], "\n") !== false){
            $filter['relate_order_bn'] = array_unique(array_map('trim', array_filter(explode("\n", $filter['relate_order_bn']))));
        }
        
        //订单标记
        if($filter['order_label']){
            $ordLabelObj = app::get('ome')->model('bill_label');
            $labelFilter = array(
                'label_id'=>$filter['order_label'],
                'bill_type'=>'order',
            );
            
            // unset
            // @todo：必须放到 $this->_filter($filter, 'o') 调用之前,否则会死循环;
            unset($filter['order_label']);
            
            //sql
            $sql = "select bill_id from sdb_ome_bill_label bl 
                left join sdb_ome_orders o on bl.bill_id=o.order_id
                where ".$ordLabelObj->_filter($labelFilter, 'bl')." and ".$this->_filter($filter, 'o');
            $where .= ' AND order_id IN ('. $sql .')';
        }

        // 最晚发货时间
        if($filter['latest_delivery_time']){

            $extendFilter = [
                'latest_delivery_time'  => $filter['latest_delivery_time'],
                '_latest_delivery_time_search' => $filter['_latest_delivery_time_search'],
                'latest_delivery_time_from' => $filter['latest_delivery_time_from'],
                'latest_delivery_time_to' => $filter['latest_delivery_time_to'],
                '_DTYPE_TIME' => $filter['_DTYPE_TIME'],
                '_DTIME_' => $filter['_DTIME_'],
            ];

            $orderExtendObj = app::get('ome')->model('order_extend');
            $orderExtendObj->filter_use_like = true;
            $sql = "select order_id from sdb_ome_order_extend 
            where ".$orderExtendObj->_filter($extendFilter);
            unset($filter['latest_delivery_time']);

            $where .= ' AND order_id IN ('. $sql .')';
        }
        
        // 销售人员编码
        if(isset($filter['seller_code']) || isset($filter['seller_name'])){
            $sellerFilter = [];
            if($filter['seller_code']){
                $sellerFilter['seller_code'] = htmlspecialchars($filter['seller_code']);
                $sellerFilter['seller_code'] = str_replace(["\r", "\n", '"', "'", '“', '”', '‘', '’', "\t", "*"], '', $sellerFilter['seller_code']);
            }
            
            if($filter['seller_name']){
                $sellerFilter['seller_name'] = htmlspecialchars($filter['seller_name']);
                $sellerFilter['seller_name'] = str_replace(["\r", "\n", '"', "'", '“', '”', '‘', '’', "\t", "*"], '', $sellerFilter['seller_name']);
            }
            
            // 订单扩展属性
            if($sellerFilter){
                $sellerMdl = app::get('material')->model('seller');
                
                // 销售人员列表
                $sellerList = $sellerMdl->getList('id,seller_code', $sellerFilter);
                if($sellerList){
                    $sellerCodes = array_column($sellerList, 'seller_code');
                    
                    $props_sql = "SELECT order_id FROM sdb_ome_order_props WHERE props_col='seller_code' AND props_value IN('". implode("','", $sellerCodes) ."')";
                    
                    $where .= ' AND order_id IN ('. $props_sql .')';
                }else{
                    $where .= ' AND order_id=-1';
                }
            }else{
                $where .= ' AND order_id=-1';
            }
            
            // unset
            unset($filter['seller_code'], $filter['seller_name']);
        }
        
        return $where ." AND ".parent::_filter($filter,$tableAlias,$baseWhere);
    }

    /**
     * 快速查询订单主表信息
     * @access public
     * @param mixed $filter 过滤条件,也可以直接是订单主键ID,如:$order_id
     * @param String $cols 字段名
     * @return Array 订单信息
     */
    function getRow($filter,$cols='*'){
        if (empty($filter)) return array();

        $wheresql = array();
        if (is_array($filter)){
            foreach ($filter as $col=>$value){
                $wheresql[] = '`'.$col.'`=\''.$value.'\'';
            }
            $wheresql = implode(' AND ', $wheresql);
        }else{
            $wheresql = '`order_id`='.$filter;
        }
        $sql = sprintf('SELECT %s FROM `sdb_ome_orders` WHERE %s',$cols,$wheresql);
        $row = $this->db->selectrow($sql);

        foreach ($this->__encrypt_cols as $field => $type) {
            if (isset($row[$field])) {
                $row[$field] = (string) kernel::single('ome_security_factory')->decryptPublic($row[$field],$type);
            }
        }

        return $row;
    }

    /**
     * 获取订单商品明细
     * @access public
     * @param Number $order_id 订单ID
     * @return Array 订单商品明细
     */
    function order_objects($order_id){
        if (empty($order_id)) return array();

        $order_objects = array();
        $wheresql = 'order_id='.$order_id;
        #objects
        $sql = sprintf('SELECT * FROM `sdb_ome_order_objects` WHERE %s',$wheresql);
        $order_objects = $this->db->select($sql);

        #items
        $sql = sprintf('SELECT * FROM `sdb_ome_order_items` WHERE %s',$wheresql);
        $items_list = $this->db->select($sql);
        if ($items_list){
            $tmp_items = array();
            foreach ($items_list as $i_key=>$i_val){
                $tmp_items[$i_val['obj_id']][] = $i_val;
            }
            $items_list = NULL;
        }

        if ($order_objects){
            foreach ($order_objects as $o_key=>&$o_val){
                $o_val['order_items'] = $tmp_items[$o_val['obj_id']];
            }
        }

        return $order_objects;
    }

    function modifier_mark_type($row){
        if($row){
            $tmp = ome_order_func::order_mark_type($row);
            if($tmp){
                $tmp = "<img src='".$tmp."' width='20' height='20'>";
                return $tmp;
            }
        }
    }

    function modifier_order_source($row){
        if($row){
            $tmp = ome_order_func::get_order_source($row);
            if($tmp){
                return $tmp;
            }
        }
    }

    function modifier_is_cod($row){
        if($row == 'true'){
            return "<div style='width:48px;padding:2px;height:16px;background-color:green;float:left;'><span style='color:#eeeeee;'>货到付款</span></div>";
        }else{
            return '款到发货';
        }
    }

    /**
     * modifier_shop_id
     * @param mixed $shop_id ID
     * @param mixed $list list
     * @param mixed $row row
     * @return mixed 返回值
     */
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

    /**
     * modifier_org_id
     * @param mixed $org_id ID
     * @param mixed $list list
     * @param mixed $row row
     * @return mixed 返回值
     */
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

    /**
     * 订单暂停
     * 
     * @param int $order_id
     * @param bool $must_log 订单叫回失败,需额外记录失败日志
     * @param string $log_msg 操作日志前缀信息
     * @return array
     */
    public function pauseOrder($order_id, $must_log=false, $log_msg='')
    {
        $branchLib = kernel::single('ome_branch');
        $channelLib = kernel::single('channel_func');

        $dlyObj = app::get('ome')->model('delivery');
        $delivery_itemsObj = app::get('ome')->model('delivery_items');
        $oOperation_log = app::get('ome')->model('operation_log');

        $rs = array('rsp'=>'succ','msg'=>'');
        if(empty($order_id)){
            return $rs;
        }

        //订单信息
        $o = $this->dump($order_id,'pause,process_status,ship_status,group_id,op_id');

        //订单已经是暂停状态,直接返回
        if ($o['pause'] != 'false'){
            return $rs;
        }
        
        //优化撤消第三方仓发货单后,客服未回到未分派
        $order_group_id = $o['group_id'];
        $order_op_id = $o['op_id'];
        
        //订单对应的发货单
        $dly_ids    = $dlyObj->getDeliverIdByOrderId($order_id);

        //没有发货单的情况，直接暂停当前订单
        if(empty($dly_ids)){
            $order    = array();
            $order['order_id'] = $order_id;
            $order['pause'] = 'true';
            $this->save($order);
            $oOperation_log->write_log('order_modify@ome',$order_id, $log_msg .'订单暂停');

            //订单暂停状态同步
            if ($service_order = kernel::servicelist('service.order')){
                foreach($service_order as $object=>$instance){
                    if(method_exists($instance, 'update_order_pause_status')){
                        $instance->update_order_pause_status($order_id);
                    }
                }
            }

            return $rs;
        }

        $is_fail    = false;//失败标记
        $pause_dly  = array();//成功暂停的发货单
        $failDly    = array();

        foreach ($dly_ids as $key => $delivery_id)
        {
            //取仓库信息
            $deliveryInfo = $dlyObj->dump($delivery_id,'*',array('delivery_items'=>array('*'),'delivery_order'=>array('*')));
            if($deliveryInfo['status'] == 'succ'){
                continue;//已发货,跳过
            }
            
            //根据仓库识别是否门店仓还是电商仓
            $store_id = $branchLib->isStoreBranch($deliveryInfo['branch_id']);
            
            //根据仓库获取仓储类型
            $wms_id = $branchLib->getWmsIdById($deliveryInfo['branch_id']);
            if($wms_id || $store_id){
                    //第三方仓储
                    //发货通知单暂停推送仓库
                    $notice_params = array(
                            'delivery_id'=>$deliveryInfo['delivery_id'],
                            'delivery_bn'=>$deliveryInfo['delivery_bn'],
                            'branch_id'=>$deliveryInfo['branch_id'],
                            'wms_id' => $wms_id,
                    );

                    $res = ome_delivery_notice::cancel($notice_params, true);
                    if($res['rsp'] == 'success' || $res['rsp'] == 'succ')
                    {
                        $deliveryInfo['is_selfwms'] = false;
                        $pause_dly[]    = $deliveryInfo;

                        // $oOperation_log->write_log('delivery_back@ome',$deliveryInfo['delivery_id'], $log_msg .'发货单取消成功');
                    }else{
                        $is_fail   = true;

                        $failDly[$delivery_id]['rsp']  = 'fail';
                        $failDly[$delivery_id]['msg']  = $res['msg'];
                        $failDly[$delivery_id]['bn']   = $deliveryInfo['delivery_bn'];
                        $failDly[$delivery_id]['flag'] = 'wms';

                        $oOperation_log->write_log('delivery_back@ome',$deliveryInfo['delivery_id'], $log_msg .'发货单取消通知仓库:失败,原因:'.$res['msg']);

                        //订单收订触发叫回失败的，需额外记录失败日志并展示
                        if($must_log){
                            kernel::single('console_delivery')->update_sync_status($deliveryInfo['delivery_id'], 'cancel_fail', $res['msg']);
                        }
                    }
            }else{
                $is_fail   = true;

                $failDly[$delivery_id]['rsp']  = 'fail';
                $failDly[$delivery_id]['msg']  = '发货单的仓库所对应的仓储类型未定义';
                $failDly[$delivery_id]['bn']   = $deliveryInfo['delivery_bn'];
                $failDly[$delivery_id]['flag'] = 'none_wms';
            }
        }

        //发货单全部成功发货,没有需要暂停的发货单
        if(empty($pause_dly) && $is_fail==false)
        {
            $order_ids    = array();

            //处理订单对应多个发货单
            foreach ($dly_ids as $key => $delivery_id)
            {
                //合并发货单对应的订单id
                $deliveryInfo    = $dlyObj->dump($delivery_id,'delivery_id, is_bind');
                if($deliveryInfo['is_bind'] == 'true')
                {
                    //取关联订单号进行暂停
                    $temp_date = $dlyObj->getOrderIdByDeliveryId($deliveryInfo['delivery_id']);
                    if($temp_date){
                        foreach ($temp_date as $id){
                            $order_ids[]    = $id;
                        }
                    }
                }
            }
            
            $order_ids[]    = $order_id;
            $order_ids      = array_unique($order_ids);

            //订单暂停
            foreach ($order_ids as $id){
                $order    = array();
                $order['order_id'] = $id;
                $order['pause'] = 'true';
                
                $this->save($order);

                $oOperation_log->write_log('order_modify@ome',$id, $log_msg .'订单暂停');
            }

            //订单暂停状态同步
            if ($service_order = kernel::servicelist('service.order')){
                foreach($service_order as $object=>$instance)
                {
                    if(method_exists($instance, 'update_order_pause_status')){
                        foreach ($order_ids as $id)
                        {
                            $instance->update_order_pause_status($id);
                        }
                    }
                }
            }

            return $rs;
        }
        //暂停成功
        elseif($pause_dly && $is_fail==false)
        {
            $deliveryInfo   = array();
            foreach ($pause_dly as $key => $val)
            {
                $deliveryInfo   = $val;

                //自有仓储
                if($deliveryInfo['is_selfwms'] == true)
                {
                    //wms暂停发货单成功，暂停本地主发货单
                    $tmpdly = array(
                        'delivery_id' => $deliveryInfo['delivery_id'],
                        'pause' => 'true'
                    );
                    $dlyObj->save($tmpdly);
                    $oOperation_log->write_log('delivery_modify@ome',$deliveryInfo['delivery_id'], $log_msg .'发货单暂停');

                    //是否是合并发货单
                    if($deliveryInfo['is_bind'] == 'true'){
                        //取关联发货单号进行暂停
                        $delivery_ids = $dlyObj->getItemsByParentId($deliveryInfo['delivery_id'],'array');
                        if($delivery_ids){
                            foreach ($delivery_ids as $id){
                                $tmpdly = array(
                                    'delivery_id' => $id,
                                    'pause' => 'true'
                                );
                                $dlyObj->save($tmpdly);
                                $oOperation_log->write_log('delivery_modify@ome',$id, $log_msg .'发货单暂停');
                            }
                        }

                        //取关联订单号进行暂停
                        $order_ids = $dlyObj->getOrderIdByDeliveryId($deliveryInfo['delivery_id']);
                        if($order_ids){
                            foreach ($order_ids as $id){
                                $order    = array();
                                $order['order_id'] = $id;
                                $order['pause'] = 'true';
                                $this->save($order);

                                $oOperation_log->write_log('order_modify@ome',$id, $log_msg .'订单暂停');
                            }
                        }
                    }else{
                        //暂停当前订单
                        $order    = array();
                        $order['order_id'] = $order_id;
                        $order['pause'] = 'true';
                        $this->save($order);

                        $oOperation_log->write_log('order_modify@ome',$order_id, $log_msg .'订单暂停');
                    }

                    //订单暂停状态同步
                    if ($service_order = kernel::servicelist('service.order')){
                        foreach($service_order as $object=>$instance){
                            if(method_exists($instance, 'update_order_pause_status')){
                               if($order_ids){
                                   foreach ($order_ids as $id){
                                       $instance->update_order_pause_status($id);
                                   }
                               }else{
                                   $instance->update_order_pause_status($order_id);
                               }
                            }
                        }
                    }
                }
                //第三方仓储
                else
                {
                    //wms第三方仓储取消发货单成功，本地主发货单取消
                    $tmpdly = array(
                        'delivery_id' => $deliveryInfo['delivery_id'],
                        'status' => 'cancel',
                        'logi_id' => '0',
                        'logi_name' => '',
                        'logi_no' => NULL,
                    );
                    // $dlyObj->save($tmpdly);
                    $ret = $dlyObj->update($tmpdly, ['delivery_id'=> $deliveryInfo['delivery_id'], 'status|in' => ['stop', 'ready', 'progress', 'timeout']]);
                    if (is_bool($ret)) {
                        continue;
                    }
                    
                    $oOperation_log->write_log('delivery_modify@ome',$deliveryInfo['delivery_id'], $log_msg .'订单暂停后触发发货单撤销成功');
                    // 发货单取消后的service扩展点
                    foreach(kernel::servicelist('ome.service.delivery.cancel.after') as $object) {
                        if(method_exists($object, 'cancel_delivery_after')) {
                            $object->cancel_delivery_after($deliveryInfo['delivery_id']);
                        }
                    }
                    //是否是合并发货单
                    if($deliveryInfo['is_bind'] == 'true'){
                        //取关联发货单号进行暂停
                        $delivery_ids = $dlyObj->getItemsByParentId($deliveryInfo['delivery_id'],'array');
                        if($delivery_ids){
                            foreach ($delivery_ids as $id){
                                $tmpdly = array(
                                    'delivery_id' => $id,
                                    'status' => 'cancel',
                                    'logi_id' => '0',
                                    'logi_name' => '',
                                    'logi_no' => NULL,
                                );
                                $dlyObj->save($tmpdly);
                                $oOperation_log->write_log('delivery_modify@ome',$id, $log_msg .'订单暂停后触发发货单撤销成功');
                            }
                        }

                        //取关联订单号进行还原
                        $order_ids = $dlyObj->getOrderIdByDeliveryId($deliveryInfo['delivery_id']);
                        if($order_ids){
                            foreach ($order_ids as $id){
                                $order    = array();
                                $order['order_id'] = $id;
                                $order['confirm'] = 'N';
                                $order['process_status'] = 'unconfirmed';
                                $order['pause'] = 'true';
                                //订单回到未分派
                                if($order_group_id == '16777215'){
                                    $order['group_id'] = 0;
                                    $order['op_id'] = 0;
                                }
                                
                                //[拆单]获取订单对应有效的发货单
                                $temp_dlyid     = $dlyObj->getDeliverIdByOrderId($id);
                                if($temp_dlyid)
                                {
                                    $order['process_status'] = 'splitting';//部分拆分
                                }
                                $this->save($order);

                                //取对应组
                                if($order['process_status'] == 'unconfirmed'){
                                    $this->updateDispatchinfo($id);
                                }

                                $oOperation_log->write_log('order_modify@ome',$id, $log_msg .'发货单撤销,订单暂停成功,还需重新审核');
                            }
                        }
                    }else{
                        //还原当前订单
                        $order    = array();
                        $order['order_id'] = $order_id;
                        $order['confirm'] = 'N';
                        $order['process_status'] = 'unconfirmed';
                        $order['pause'] = 'true';
                        //订单回到未分派
                        if($order_group_id == '16777215'){
                            $order['group_id'] = 0;
                            $order['op_id'] = 0;
                        }
                        
                        //[拆单]获取订单对应有效的发货单
                        $temp_dlyid     = $dlyObj->getDeliverIdByOrderId($order_id);
                        if($temp_dlyid){
                            $order['process_status'] = 'splitting';//部分拆分
                        }
                        
                        $this->save($order);

                        //取对应组
                        if($order['process_status'] == 'unconfirmed'){
                            $this->updateDispatchinfo($order_id);
                        }

                        $oOperation_log->write_log('order_modify@ome',$order_id, $log_msg .'发货单撤销,订单暂停成功,还需重新审核');
                    }

                    //库存管控
                    $storeManageLib      = kernel::single('ome_store_manage');
                    $storeManageLib->loadBranch(array('branch_id'=>$deliveryInfo['branch_id']));
                    if($deliveryInfo['is_bind'] == 'true'){
                        foreach ($delivery_ids as $i){
                            $delivery = $dlyObj->dump($i,'delivery_id,branch_id,shop_id',array('delivery_items'=>array('*'),'delivery_order'=>array('*')));

                            $de = $delivery['delivery_order'];
                            $or = array_shift($de);
                            $ord_id = $or['order_id'];

                            //仓库库存处理
                            $params['params'] = array_merge($delivery,array('order_id'=>$ord_id));
                            $params['node_type'] ='pauseOrd';
                            $err_msg = '';
                            $processResult     = $storeManageLib->processBranchStore($params, $err_msg);
                            kernel::single('ome_order_object_splitnum')->backDeliverySplitNum($i);
                        }
                    }else{
                        $de = $deliveryInfo['delivery_order'];
                        $or = array_shift($de);
                        $ord_id = $or['order_id'];

                        //仓库库存处理
                        $params['params'] = array_merge($deliveryInfo,array('order_id'=>$ord_id));
                        $params['node_type'] ='pauseOrd';
                        $err_msg = '';
                        $processResult    = $storeManageLib->processBranchStore($params, $err_msg);
                        kernel::single('ome_order_object_splitnum')->backDeliverySplitNum($deliveryInfo['delivery_id']);
                    }
                }
            }
        }
        //暂停失败
        elseif($is_fail)
        {
            $temp_rs    = array('rsp'=>'fail', 'is_split'=>'true');
            foreach ($failDly as $key => $val)
            {
                $temp_rs['msg']     .= '发货单'.$val['bn'].' '.str_replace('数字校验失败', '撤销失败', $val['msg']).'<br>';
            }

            //已暂停或取消的发货单
            if($pause_dly)
            {
                $deliveryInfo   = array();
                $temp_msg   = array();
                foreach ($pause_dly as $key => $val)
                {
                    $deliveryInfo   = $val;
                    
                    if($val['is_selfwms'] == true)
                    {
                        $temp_msg['is_selfwms'][]   = $val['delivery_bn'];
                        
                        //wms暂停发货单成功，暂停本地主发货单
                        $tmpdly = array(
                                'delivery_id' => $deliveryInfo['delivery_id'],
                                'pause' => 'true'
                        );
                        $dlyObj->save($tmpdly);
                        $oOperation_log->write_log('delivery_modify@ome',$deliveryInfo['delivery_id'], $log_msg .'发货单暂停');
                        
                        //是否是合并发货单
                        if($deliveryInfo['is_bind'] == 'true'){
                            //取关联发货单号进行暂停
                            $delivery_ids = $dlyObj->getItemsByParentId($deliveryInfo['delivery_id'],'array');
                            if($delivery_ids){
                                foreach ($delivery_ids as $id){
                                    $tmpdly = array(
                                        'delivery_id' => $id,
                                        'pause' => 'true'
                                    );
                                    $dlyObj->save($tmpdly);
                                    $oOperation_log->write_log('delivery_modify@ome',$id, $log_msg .'发货单暂停');
                                }
                            }
                            
                            //取关联订单号进行暂停
                            $order_ids = $dlyObj->getOrderIdByDeliveryId($deliveryInfo['delivery_id']);
                            if($order_ids){
                                foreach ($order_ids as $id){
                                    $order    = array();
                                    $order['order_id'] = $id;
                                    $order['pause'] = 'true';
                                    $this->save($order);

                                    $oOperation_log->write_log('order_modify@ome',$id, $log_msg .'订单暂停');
                                }
                            }
                        }else{
                            //暂停当前订单
                            $order    = array();
                            $order['order_id'] = $order_id;
                            $order['pause'] = 'true';
                            $this->save($order);
                            
                            $oOperation_log->write_log('order_modify@ome',$order_id, $log_msg .'订单暂停');
                        }
                        
                        //订单暂停状态同步
                        if ($service_order = kernel::servicelist('service.order')){
                            foreach($service_order as $object=>$instance){
                                if(method_exists($instance, 'update_order_pause_status')){
                                   if($order_ids){
                                       foreach ($order_ids as $id){
                                           $instance->update_order_pause_status($id);
                                       }
                                   }else{
                                       $instance->update_order_pause_status($order_id);
                                   }
                                }
                            }
                        }
                    }else{
                        $temp_msg['other'][]   = $val['delivery_bn'];
                        
                        //wms第三方仓储取消发货单成功，本地主发货单取消
                        $tmpdly = array(
                                'delivery_id' => $deliveryInfo['delivery_id'],
                                'status' => 'cancel',
                                'logi_id' => '0',
                                'logi_name' => '',
                                'logi_no' => NULL,
                        );
                        // $dlyObj->save($tmpdly);
                        
                        $ret = $dlyObj->update($tmpdly, ['delivery_id'=> $deliveryInfo['delivery_id'], 'status|in' => ['stop', 'ready', 'progress', 'timeout']]);
                        if (is_bool($ret)) {
                            continue;
                        }
                        
                        $oOperation_log->write_log('delivery_modify@ome',$deliveryInfo['delivery_id'], $log_msg .'订单暂停后触发发货单撤销成功');
                        // 发货单取消后的service扩展点
                        foreach(kernel::servicelist('ome.service.delivery.cancel.after') as $object) {
                            if(method_exists($object, 'cancel_delivery_after')) {
                                $object->cancel_delivery_after($deliveryInfo['delivery_id']);
                            }
                        }
                        //是否是合并发货单
                        if($deliveryInfo['is_bind'] == 'true'){
                            //取关联发货单号进行暂停
                            $delivery_ids = $dlyObj->getItemsByParentId($deliveryInfo['delivery_id'],'array');
                            if($delivery_ids){
                                foreach ($delivery_ids as $id){
                                    $tmpdly = array(
                                        'delivery_id' => $id,
                                        'status' => 'cancel',
                                        'logi_id' => '0',
                                        'logi_name' => '',
                                        'logi_no' => NULL,
                                    );
                                    $dlyObj->save($tmpdly);
                                    $oOperation_log->write_log('delivery_modify@ome',$id, $log_msg .'发货单撤销成功');
                                }
                            }
                            
                            //取关联订单号进行还原
                            $order_ids = $dlyObj->getOrderIdByDeliveryId($deliveryInfo['delivery_id']);
                            if($order_ids){
                                foreach ($order_ids as $id){
                                    $order    = array();
                                    $order['order_id'] = $id;
                                    $order['confirm'] = 'N';
                                    $order['process_status'] = 'unconfirmed';
                                    $order['pause'] = 'true';
                                    //订单回到未分派
                                    if($order_group_id == '16777215'){
                                        $order['group_id'] = '0';
                                        $order['op_id'] = '0';
                                    }
                                    
                                    //[拆单]获取订单对应有效的发货单
                                    $temp_dlyid     = $dlyObj->getDeliverIdByOrderId($id);
                                    if($temp_dlyid)
                                    {
                                        $order['process_status'] = 'splitting';//部分拆分
                                    }
                                    $this->save($order);

                                    //取对应组
                                    if($order['process_status'] == 'unconfirmed'){
                                        $this->updateDispatchinfo($id);
                                    }

                                    $oOperation_log->write_log('order_modify@ome',$id, $log_msg .'发货单撤销,订单暂停成功,还原需重新审核');
                                }
                            }
                        }else{
                            //还原当前订单
                            $order    = array();
                            $order['order_id'] = $order_id;
                            $order['confirm'] = 'N';
                            $order['process_status'] = 'unconfirmed';
                            $order['pause'] = 'true';
                            //订单回到未分派
                            if($order_group_id == '16777215'){
                                $order['group_id'] = '0';
                                $order['op_id'] = '0';
                            }
                            
                            //[拆单]获取订单对应有效的发货单
                            $temp_dlyid     = $dlyObj->getDeliverIdByOrderId($order_id);
                            if($temp_dlyid)
                            {
                                $order['process_status'] = 'splitting';//部分拆分
                            }
                            $this->save($order);
                            
                            //取对应组
                            if($order['process_status'] == 'unconfirmed'){
                                $this->updateDispatchinfo($order_id);
                            }
                            
                            $oOperation_log->write_log('order_modify@ome',$order_id, $log_msg .'发货单撤销,订单暂停成功,还原需重新审核');
                        }
                        
                        //库存管控
                        $storeManageLib = kernel::single('ome_store_manage');
                        $storeManageLib->loadBranch(array('branch_id'=>$deliveryInfo['branch_id']));
                        if($deliveryInfo['is_bind'] == 'true'){
                            foreach ($delivery_ids as $i){
                                $delivery = $dlyObj->dump($i,'delivery_id,branch_id,shop_id',array('delivery_items'=>array('*'),'delivery_order'=>array('*')));
                                
                                $de = $delivery['delivery_order'];
                                $or = array_shift($de);
                                $ord_id = $or['order_id'];
                                
                                //仓库库存处理
                                $params['params']  = array_merge($delivery,array('order_id'=>$ord_id));
                                $params['node_type'] ='pauseOrd';
                                $err_msg = '';
                                $processResult       = $storeManageLib->processBranchStore($params, $err_msg);

                                kernel::single('ome_order_object_splitnum')->backDeliverySplitNum($i);
                            }
                        }else{
                            $de = $deliveryInfo['delivery_order'];
                            $or = array_shift($de);
                            $ord_id = $or['order_id'];
                            
                            //仓库库存处理
                            $params['params'] = array_merge($deliveryInfo,array('order_id'=>$ord_id));
                            $params['node_type'] ='pauseOrd';
                            $err_msg = '';
                            $processResult    = $storeManageLib->processBranchStore($params, $err_msg);
                            kernel::single('ome_order_object_splitnum')->backDeliverySplitNum($deliveryInfo['delivery_id']);
                        }
                    }
                }

                if($temp_msg['is_selfwms']){
                    $temp_rs['msg'] .= '<br><br>自有仓储,成功暂停的发货单：'.implode(',', $temp_msg['is_selfwms']);
                }elseif($temp_msg['other']){
                    $temp_rs['msg'] .= '<br><br>第三方仓储,成功取消的发货单：'.implode(',', $temp_msg['other']);
                }
            }

            $rs = $temp_rs;
            unset($temp_rs);
        }

        return $rs;
    }

    /**
     * 订单恢复
     * 
     * @param int $order_id
     * @return boolean
     */
    public function renewOrder($order_id, $memo = '')
    {
        $branchLib = kernel::single('ome_branch');
        $channelLib = kernel::single('channel_func');

        $dlyObj      = app::get('ome')->model('delivery');
        $oOperation_log    = app::get('ome')->model('operation_log');

        $is_fail    = false;//失败标记
        $pause_dly  = array();//需要恢复的发货单

        if(empty($order_id)){
            return false;
        }

        //订单信息
        $o = $this->dump($order_id,'pause');
        if($o['pause'] != 'true'){
            return false;
        }

        //订单对应的发货单
        $dly_ids = $dlyObj->getDeliverIdByOrderId($order_id);

        //没有发货单的情况，直接恢复当前订单
        if(empty($dly_ids)){
//            $order    = array();
//            $order['order_id'] = $order_id;
//            $order['pause'] = 'false';
//            $this->save($order);
            
            //update
            $affect_rows = $this->update(array('pause'=>'false'), array('order_id'=>$order_id, 'status|noequal'=>'dead'));
            if(!is_bool($affect_rows)){
                $oOperation_log->write_log('order_modify@ome',$order_id,'订单恢复:' . $memo);
            }
            
            //订单恢复状态同步
            if ($service_order = kernel::servicelist('service.order')){
                foreach($service_order as $object=>$instance){
                    if(method_exists($instance, 'update_order_pause_status')){
                        $instance->update_order_pause_status($order_id, 'false');
                    }
                }
            }
            
            return true;
        }

        //恢复发货单
        foreach ($dly_ids as $key => $delivery_id)
        {
             //取仓库信息
             $deliveryInfo = $dlyObj->dump($delivery_id,'*');

             //已发货的发货单,跳过
             if($deliveryInfo['status'] == 'succ'){
                 continue;//已发货,跳过
             }

             $pause_dly[]    = $deliveryInfo;

             $wms_id = $branchLib->getWmsIdById($deliveryInfo['branch_id']);
             if($wms_id){
                 $is_selfWms = $channelLib->isSelfWms($wms_id);
                 if($is_selfWms){
                     //发货通知单暂停推送仓库
                     $notice_params = array(
                         'delivery_id'=>$deliveryInfo['delivery_id'],
                         'delivery_bn'=>$deliveryInfo['delivery_bn'],
                         'branch_id'=>$deliveryInfo['branch_id'],
                         'wms_id' => $wms_id,
                     );

                     $res = ome_delivery_notice::renew($notice_params, true);
                     if($res['rsp'] == 'success' || $res['rsp'] == 'succ'){
                         //wms恢复发货单成功，恢复本地主发货单
                         $tmpdly = array(
                             'delivery_id' => $deliveryInfo['delivery_id'],
                             'pause' => 'false'
                         );
                         $dlyObj->save($tmpdly);
                         $oOperation_log->write_log('delivery_modify@ome',$deliveryInfo['delivery_id'],'发货单恢复');

                         //是否是合并发货单
                         if($deliveryInfo['is_bind'] == 'true'){
                             //取关联发货单号进行暂停
                             $delivery_ids = $dlyObj->getItemsByParentId($deliveryInfo['delivery_id'],'array');

                             if($delivery_ids){
                                 foreach ($delivery_ids as $id){
                                     $tmpdly = array(
                                             'delivery_id' => $id,
                                             'pause' => 'false'
                                     );
                                     $dlyObj->save($tmpdly);
                                     $oOperation_log->write_log('delivery_modify@ome',$id,'发货单恢复');
                                 }
                             }

                             //取关联订单号进行暂停
                             $order_ids = $dlyObj->getOrderIdByDeliveryId($deliveryInfo['delivery_id']);
                             if($order_ids){
                                 foreach ($order_ids as $id){
                                     $order    = array();
                                     $order['order_id'] = $id;
                                     $order['pause'] = 'false';
                                     $this->save($order);

                                     $oOperation_log->write_log('order_modify@ome',$id,'订单恢复');
                                 }
                             }
                         }else{
                             //恢复当前订单
                             $order    = array();
                             $order['order_id'] = $order_id;
                             $order['pause'] = 'false';
                             $this->save($order);

                             $oOperation_log->write_log('order_modify@ome',$order_id,'订单恢复');
                         }

                         //订单暂停状态同步
                         if ($service_order = kernel::servicelist('service.order')){
                             foreach($service_order as $object=>$instance){
                                 if(method_exists($instance, 'update_order_pause_status')){
                                     if($order_ids){
                                         foreach ($order_ids as $id){
                                             $instance->update_order_pause_status($id, 'false');
                                         }
                                     }else{
                                         $instance->update_order_pause_status($order_id, 'false');
                                     }
                                 }
                             }
                         }
                     }else{
                         $is_fail   = true;
                     }
                 }
             }else{
                 $is_fail   = true;
             }
        }

        //恢复已发货的发货单对应的订单状态
        if(empty($pause_dly) && $is_fail==false){
            $order_ids    = array();

            //处理订单对应多个发货单
            foreach ($dly_ids as $key => $delivery_id)
            {
                //取仓库信息
                $deliveryInfo = $dlyObj->dump($delivery_id,'delivery_id, is_bind');

                //是否是合并发货单
                if($deliveryInfo['is_bind'] == 'true')
                {
                    //取关联订单号进行暂停
                    $temp_date = $dlyObj->getOrderIdByDeliveryId($deliveryInfo['delivery_id']);
                    if($temp_date){
                        foreach ($temp_date as $id){
                            $order_ids[]    = $id;
                        }
                    }
                }
            }
            
            $order_ids[]    = $order_id;
            $order_ids      = array_unique($order_ids);

            foreach ($order_ids as $id){
//                $order    = array();
//                $order['order_id'] = $id;
//                $order['pause'] = 'false';
//                $this->save($order);
                
                //update
                $affect_rows = $this->update(array('pause'=>'false'), array('order_id'=>$id, 'status|noequal'=>'dead'));
                if(!is_bool($affect_rows)){
                    $oOperation_log->write_log('order_modify@ome',$id,'订单恢复');
                }
            }

            //订单恢复状态同步
            if ($service_order = kernel::servicelist('service.order')){
                foreach($service_order as $object=>$instance)
                {
                    if(method_exists($instance, 'update_order_pause_status')){
                        foreach ($order_ids as $id)
                        {
                            $instance->update_order_pause_status($id);
                        }
                    }
                }
            }
        }

        if($is_fail){
            return false;
        }

        return true;
    }

    //分派时间
    function modifier_dispatch_time($row){
       if ($row){
           $tmp = date('Y年m月d日 H点i分',$row);
           return $tmp;
       }
    }

    //平台状态
    function modifier_source_status($row){
       return kernel::single('ome_order_func')->get_source_status($row, 'txt');
    }

     //平台状态
    function modifier_step_trade_status($row){
       return kernel::single('ome_order_func')->get_step_trade_status($row, 'txt');
    }

    /**
     * 确认组
     * 
     * @param Integer $row 组ID
     * @return void
     */
    function modifier_group_id($row) {

        switch ($row) {
            case 0:
                $ret = '无';
                break;
            case 16777215:
                $ret = '系统';
                break;
            default:
                $ret = $this->_getGroupName($row);
                break;
        }

        return $ret;
    }

    /**
     * 获取用户名
     * 
     * @param Integer $gid
     * @return String;
     */
    private function _getUserName($uid) {
        if (self::$__USERS === null) {

            self::$__USERS = array();
            $rows = app::get('desktop')->model('users')->getList('*');
            foreach((array) $rows as $row) {
                self::$__USERS[$row['user_id']] = $row['name'];
            }
        }

        if (isset(self::$__USERS[$uid])) {

            return self::$__USERS[$uid];
        } else {

            return '未知';
        }
    }

    /**
     * 获取组名
     * 
     * @param Integer $gid
     * @return String;
     */
    private function _getGroupName($gid) {

        if (self::$__GROUPS === null) {

            self::$__GROUPS = array();
            $rows = app::get('ome')->model('groups')->getList('*');
            foreach((array) $rows as $row) {
                self::$__GROUPS[$row['group_id']] = $row['name'];
            }
        }

        if (isset(self::$__GROUPS[$gid])) {

            return self::$__GROUPS[$gid];
        } else {

            return '未知';
        }
    }

    /**
     * 确认人
     * 
     * @param Integer $row 确认人ID
     * @return void
     */
    function modifier_op_id($row) {

        switch ($row) {
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

    /**
     * 打回订单的发货单
     * @param int $order_id 订单号
     * @param boolean $reback_status 打回状态，默认为false:打回所有发货单;true：只打回未发货的发货单
     */
    function rebackDeliveryByOrderId($order_id,$dly_status=false,$memo=''){

        $dlyObj      = app::get('ome')->model('delivery');
        $dly_oObj    = app::get('ome')->model('delivery_order');
        $opObj       = app::get('ome')->model('operation_log');

        $is_succ    = true;//成功标识

        $bind = array();
        $dlyos = array();
        $mergedly = array();

        //订单关联的发货单
        $data = $dly_oObj->getList('*',array('order_id'=>$order_id),0,-1);
        if ($data)
        {
            foreach ($data as $v){
                $dly = $dlyObj->dump($v['delivery_id'],'process,status,parent_id,is_bind');

                //只打回未发货的发货单
                //if ($dly_status == true){
                    if ($dly['process'] == 'true' || in_array($dly['status'],array('failed', 'cancel', 'back', 'succ','return_back'))){
                        continue;
                    }
                //}

                if ($dly['parent_id'] == 0 && $dly['is_bind'] == 'true'){
                    $bind[$v['delivery_id']]['delivery_id'] = $v['delivery_id'];
                }elseif ($dly['parent_id'] == 0){
                    $dlyos[$v['delivery_id']][] = $v['delivery_id'];
                }else{
                    $mergedly[$v['delivery_id']] = $v['delivery_id'];
                    $bind[$dly['parent_id']]['items'][] = $v['delivery_id'];
                }
            }
        }

        //如果是合并发货单的话
        if ($bind)
        {
            foreach ($bind as $k => $i){
                //$items = $dlyObj->getItemsByParentId($i['delivery_id'], 'array', 'delivery_id');

                #拆分发货单
                $result = $dlyObj->splitDelivery($i['delivery_id'], $i['items'], false);

                if ($result){
                    $is_succ   = $dlyObj->rebackDelivery($i['items'], $memo, $dly_status, false);

                    #打回发货单失败
                    if($is_succ == false)
                    {
                        return false;
                    }

                    foreach ($i['items'] as $i){
                        $dlyObj->updateOrderPrintFinish($i, 1);
                    }
                }
            }
        }

        //单个发货单
        if ($dlyos){
            foreach ($dlyos as $v){
                $is_succ   = $dlyObj->rebackDelivery($v, $memo, $dly_status, false);

                #打回发货单失败
                if($is_succ == false)
                {
                    return false;
                }

                $dlyObj->updateOrderPrintFinish($v, 1);
            }
        }

        return true;
    }

    /**
     * 删除全额退款的订单对象并扣除金额
     * 用于发货单撤销等场景，当order_objects为全额退款(pay_status=5)时，需要删除对象并扣除订单金额
     * 
     * @param int $obj_id order_objects的obj_id
     * @param string $log_msg 日志消息
     * @return bool 是否成功
     */
    public function deleteRefundObject($obj_id, $log_msg = '发货单取消')
    {
        if (empty($obj_id)) {
            return false;
        }
        
        $orderObjectsObj = app::get('ome')->model('order_objects');
        
        // 获取order_objects信息（必须是pay_status=5且delete=false）
        $obj = $orderObjectsObj->dump($obj_id, '*');
        
        if (empty($obj) || $obj['pay_status'] != '5' || $obj['delete'] == 'true') {
            return false;
        }
        
        $order_id = $obj['order_id'];
        
        // 检查该obj_id下的order_items是否有split_num>0的记录，如果有则不允许删除
        $orderItemsObj = app::get('ome')->model('order_items');
        $checkItems = $orderItemsObj->getList('item_id', array(
            'obj_id' => $obj_id, 
            'delete' => 'false',
            'split_num|than' => 0
        ));
        
        if (!empty($checkItems)) {
            return false;
        }
        
        // 更新order_objects的delete状态
        $affectedRows = $orderObjectsObj->update(
            array('delete' => 'true'), 
            array('obj_id' => $obj_id)
        );
        
        // 必须严格等于1才继续
        if ($affectedRows !== 1) {
            return false;
        }
        
        // 批量标记关联的order_items为已删除（前面已经验证过没有split_num>0的记录）
        $orderItemsObj->update(array('delete' => 'true'), array('obj_id' => $obj_id, 'delete' => 'false'));
        
        // 扣除订单金额
        $sql = "UPDATE sdb_ome_orders SET
                pmt_goods = pmt_goods - " . floatval($obj['pmt_price'] ?: 0) . ",
                pmt_order = pmt_order - " . floatval($obj['part_mjz_discount'] ?: 0) . ",
                cost_item = cost_item - " . floatval($obj['amount'] ?: 0) . ",
                total_amount = total_amount - " . floatval($obj['divide_order_fee'] ?: 0) . ",
                final_amount = final_amount - " . floatval($obj['divide_order_fee'] ?: 0) . "
                WHERE order_id = " . $order_id;
        kernel::database()->exec($sql);
        
        // 记录日志
        $opObj = app::get('ome')->model('operation_log');
        $opObj->write_log('order_modify@ome', $order_id, 
            $log_msg . ',扣除金额' . floatval($obj['divide_order_fee'] ?: 0));
        
        return true;
    }

    /**
     * 获得总数量
     * 
     * @param string $where
     * 
     * @return array()
     */
    function get_all($where){
        $minute = $this->app->getConf('ome.order.unconfirmtime');
        $time = time() - $minute*60;

        $sql = "SELECT COUNT(o.order_id) AS 'TOTAL' FROM sdb_ome_orders o
                                        WHERE 1 $where ";
        $re4 = $this->db->selectrow($sql);
        $sql = "SELECT COUNT(o.order_id) AS 'TOTAL' FROM sdb_ome_orders o
                                        WHERE (is_cod='true' OR pay_status='1') $where
                                            AND (`op_id` is null and `group_id` is null)";
        $re1 = $this->db->selectrow($sql);
        $sql = "SELECT COUNT(o.order_id) AS 'TOTAL' FROM sdb_ome_orders o
                                        WHERE (`op_id` is not null or `group_id` is not null) $where
                                            AND o.confirm='N'";
        $re2 = $this->db->selectrow($sql);
        $sql = "SELECT COUNT(o.order_id) AS 'TOTAL' FROM sdb_ome_orders o
                                        WHERE (`op_id` is not null or `group_id` is not null) $where
                                            AND o.confirm='N'
                                            AND o.dt_begin < $time ";
        $re3 = $this->db->selectrow($sql);

        $re['all'] = $re4['TOTAL'];
        $re['a'] = $re1['TOTAL'];
        $re['b'] = $re2['TOTAL'];
        $re['c'] = $re3['TOTAL'];
        return $re;
    }


    /**
     * 获得确认组订单数量
     * 
     * @param string $where
     * 
     * @return array
     */
    function get_group($where){
        $sql = "SELECT o.group_id,g.name FROM sdb_ome_orders o
                                JOIN sdb_ome_groups g
                                    ON o.group_id=g.group_id
                                WHERE g.g_type='confirm' $where GROUP BY o.group_id ";
        $data = $this->db->select($sql);
        $result = array();
        if ($data){
            $minute = $this->app->getConf('ome.order.unconfirmtime');
            $time = time() - $minute*60;
            foreach ($data as $v){
                $group_id = $v['group_id'];
                $result[$group_id]['name'] = $v['name'];

                $sql = "SELECT COUNT(order_id) AS 'TOTAL' FROM sdb_ome_orders  as o
                                        WHERE group_id=$group_id
                                            AND (`op_id` is not null or `group_id` is not null)
                                            AND confirm='N' $where";
                $re = $this->db->selectrow($sql);
                $result[$group_id]['b'] = $re['TOTAL'];

                $sql = "SELECT COUNT(order_id) AS 'TOTAL' FROM sdb_ome_orders
                                        WHERE group_id=$group_id
                                            AND (`op_id` is not null or `group_id` is not null)
                                            AND confirm='N'
                                            AND dt_begin < $time ";
                $re = $this->db->selectrow($sql);
                $result[$group_id]['c'] = $re['TOTAL'];
            }
        }

        return $result;
    }


    /**
     * 获得已分派但未确认时间超过设定时间的订单数量
     * 
     * @param string $where
     * @param string $type
     * 
     * @return number
     */
    function get_operator($where){
        $sql = "SELECT o.group_id,g.name as 'g_name',o.op_id,u.name as 'u_name' FROM sdb_ome_orders o
                                JOIN sdb_ome_groups g
                                    ON o.group_id=g.group_id
                                JOIN sdb_desktop_users u
                                    ON u.user_id=o.op_id
                                WHERE g.g_type='confirm' $where GROUP BY o.op_id ";
        $data = $this->db->select($sql);
        $result = array();
        if ($data){
            $minute = $this->app->getConf('ome.order.unconfirmtime');
            $time = time() - $minute*60;
            foreach ($data as $v){
                $op_id = $v['op_id'];
                $result[$op_id]['g_name'] = $v['g_name'];
                $result[$op_id]['u_name'] = $v['u_name'];

                $sql = "SELECT COUNT(order_id) AS 'TOTAL' FROM sdb_ome_orders as o
                                        WHERE op_id=$op_id
                                            AND (`op_id` is not null or `group_id` is not null)
                                            AND confirm='N' $where";
                $re = $this->db->selectrow($sql);
                $result[$op_id]['b'] = $re['TOTAL'];

                $sql = "SELECT COUNT(order_id) AS 'TOTAL' FROM sdb_ome_orders
                                        WHERE op_id=$op_id
                                            AND (`op_id` is not null or `group_id` is not null)
                                            AND confirm='N'
                                            AND dt_begin < $time ";
                $re = $this->db->selectrow($sql);
                $result[$op_id]['c'] = $re['TOTAL'];
            }
        }

        return $result;
    }

    function get_confirm_ops(){
        $sql = "SELECT go.op_id,u.name FROM sdb_ome_group_ops go
                            JOIN sdb_ome_groups g
                                ON g.group_id = go.group_id
                            JOIN sdb_desktop_users u
                                ON go.op_id = u.user_id
                            WHERE g.g_type = 'confirm' GROUP BY go.op_id ";
        $re = $this->db->select($sql);
        return $re;
    }
    /*
     * 根据订单来恢复预占的冻结库存
     * 比如在订单被取消时，就需要恢复冻结库存
     *
     * @param int $order_id
     * @param int $is_ctrl_store 是否管控库存 false为不管控，不需要扣减已生成发货单的商品数量
     */
    function unfreez($order_id,$is_ctrl_store = true){
        $oDelivery = $this->app->model("delivery");
        //unfreeze剩余未生成发货单的货品
        $basicMStockFreezeLib = kernel::single('material_basic_material_stock_freeze');

        kernel::single('material_basic_material_stock_freeze')->deleteOrderBranchFreeze([$order_id]);
        $items = $this->db->select("SELECT product_id,nums,obj_id FROM sdb_ome_order_items WHERE order_id=".$order_id ." AND `delete`='false'");
        uasort($items, [kernel::single('console_iostockorder'), 'cmp_productid']);

        $objects = app::get('ome')->model('order_objects')->getList('obj_id,goods_id', ['obj_id'=>array_column($items, 'obj_id')]);
        $objects = array_column($objects, null, 'obj_id');

        $branchBatchList = [];
        foreach($items as $v){
            $dly_num = $oDelivery->getDeliveryFreez($order_id,$v['product_id']);
            $dly_num = $dly_num ? $dly_num : 0;
            $num = $v['nums'] - $dly_num;
            if (!$is_ctrl_store) {
                $num = $v['nums'];
            }
            if($num > 0){

                $branchBatchList[] = [
                    'bm_id'     =>  $v['product_id'],
                    'sm_id'     =>  $objects[$v['obj_id']]['goods_id'],
                    'obj_type'  =>  material_basic_material_stock_freeze::__ORDER,
                    'bill_type' =>  0,
                    'obj_id'    =>  $order_id,
                    'branch_id' =>  '',
                    'bmsq_id'   =>  material_basic_material_stock_freeze::__SHARE_STORE,
                    'num'       =>  $num,
                ];
            }
        }
        
        $err = '';
        $basicMStockFreezeLib->unfreezeBatch($branchBatchList, __CLASS__.'::'.__FUNCTION__, $err);

        //订单取消后，清除订单级预占流水
        // unfreezeBatch已经清除
        // $basicMStockFreezeLib->delOrderFreeze($order_id);

        return true;
    }

    /**
     * 更新订单状态
     * 
     * @param bigint $order_id
     * @param string $status
     * 
     * @return boolean
     */
    function set_status($order_id, $status){
        $data['order_id'] = $order_id;
        $data['process_status'] = $status['order_status'];
        if(isset($status['pause'])){
            $data['pause'] = $status['pause'];
        }


        return $this->save($data);
    }

    /*
     * 取消发货单
     *
     * @param int $order_id
     *
     * @return bool
     */
    function cancel_delivery($order_id,$must_log = false)
    {
        $cancel_orders = array();
        
        //找到订单关联的发货单，取消发货单，释放仓库冻结，增加店铺销售物料冻结
        //如果状态是失败，但是有成功取消发货单数量，做后续逻辑判断用到
        $rs = array('rsp'=>'succ','msg'=>'','succ_num'=>0);

        //bugfix 拆单一个订单可能对应多个发货单 xiayuanjun
        $deliveryList = $this->db->select("SELECT dord.delivery_id,d.is_bind,d.status,d.delivery_bn,d.branch_id,d.shop_id FROM sdb_ome_delivery_order AS dord
                                            LEFT JOIN sdb_ome_delivery AS d ON(dord.delivery_id=d.delivery_id)
                                            WHERE dord.order_id={$order_id} AND d.disabled='false' AND d.parent_id=0 AND d.status not in('cancel','back','succ','return_back')");
        if($deliveryList){
            $oDelivery = $this->app->model("delivery");
            $delivery_itemsObj = $this->app->model('delivery_items');
            $itemsObj = $this->app->model("order_items");
            $oOperation_log = app::get('ome')->model('operation_log');
            $dlyObj = app::get('ome')->model("delivery");
            //库存管控处理
            $storeManageLib    = kernel::single('ome_store_manage');

            foreach($deliveryList as $delivery){
                $is_bind = $delivery['is_bind'];
                $branch_id = $delivery['branch_id'];
                $delivery_bn = $delivery['delivery_bn'];
                $delivery_id = $delivery['delivery_id'];
                $shop_id = $delivery['shop_id'];
                $storeManageLib->loadBranch(array('branch_id'=>$delivery['branch_id']));

                if ($is_bind == 'false') {
                    //发货通知单暂停推送仓库
                    $notice_params = array(
                        'delivery_id'=>$delivery_id,
                        'delivery_bn'=>$delivery_bn,
                        'branch_id'=>$branch_id,
                    );

                    $res = ome_delivery_notice::cancel($notice_params, true);
                    if ( $res['rsp'] == 'success' || $res['rsp'] == 'succ') {
                        $tmpdly = array(
                            'delivery_id' => $delivery_id,
                            'status' => 'cancel',
                            'logi_id' => '0',
                            'logi_name' => '',
                            'logi_no' => NULL,
                        );
                        
                        // 防并发
                        $ret = $dlyObj->update($tmpdly, ['delivery_id'=>$delivery_id, 'status|in' => ['stop', 'ready', 'progress', 'timeout']]);
                        if (is_bool($ret)) {
                           $rs['msg'] = '发货单已被取消';
                           return $rs;
                        }
                        
                        $oOperation_log->write_log('delivery_modify@ome',$delivery_id,'发货单撤销成功');
                        //更新发货单状态 API
                         kernel::single('ome_event_trigger_shop_delivery')->delivery_process_update($delivery_id);

                        $deliveryInfo = $oDelivery->dump($delivery_id,'delivery_id,branch_id,shop_id',array('delivery_items'=>array('*'),'delivery_order'=>array('*')));
                        $de = $deliveryInfo['delivery_order'];
                        $or = array_shift($de);
                        $ord_id = $or['order_id'];

                        //仓库库存处理
                        $params['params'] = array_merge($deliveryInfo,array('order_id'=>$ord_id));
                        $params['node_type'] ='cancelDly';
                        $err_msg = '';
                        $processResult    = $storeManageLib->processBranchStore($params, $err_msg);
                        kernel::single('ome_order_object_splitnum')->backDeliverySplitNum($delivery_id);

                        $rs['succ_num']++;
                        
                        //撤消成功的订单
                        $cancel_orders[$order_id] = $order_id;
                        
                        // 发货单取消后的service扩展点
                        foreach(kernel::servicelist('ome.service.delivery.cancel.after') as $object) {
                            if(method_exists($object, 'cancel_delivery_after')) {
                                $object->cancel_delivery_after($delivery_id);
                            }
                        }
                    }else{
                        $rs['rsp'] = 'fail';
                        $rs['msg']    .= $res['msg'] . ';';
                        $oOperation_log->write_log('delivery_back@ome',$delivery_id,'发货单取消通知仓库:失败,原因'.$rs['msg']);

                        //订单收订触发叫回失败的，需额外记录失败日志并展示
                        if($must_log){
                            kernel::single('console_delivery')->update_sync_status($delivery_id, 'cancel_fail', $rs['msg']);
                        }
                    }
                }else{
                    

                    $split_result = $oDelivery->splitDelivery($delivery_id,array(),$must_log);
                    if (!$split_result) {
                        $rs['rsp'] = 'fail';
                        $rs['msg'] .= '可能WMS订单不存在;';
                    }else{
                        $rs['succ_num']++;
                    }
                }
            }
        }
        
        return $rs;
    }

    function order_detail($order_id){
        $order_detail = $this->dump($order_id);
        return $order_detail;
    }
    /*
     * 设置订单异常，并保存异常类型和备注
     *
     * @param array $data abnormal对象的sdf结构数据
     * @param string $log_memo 日志备注
     *
     */
    function set_abnormal($data){
        //组织 分派的数组 $data_dispatch 跟filter(跟dispatch 的参数形式保持一致)

        $data_dispatch = array(
            'group_id' =>$data['group_id'],
            'op_id' =>$data['op_id'],
            'dt_begin' =>time(),
            'dispatch_time' =>time(),
        );
        //组织 set_abnormal的数组
        $data = array(
            'abnormal_id'=>$data['abnormal_id'],
            'order_id'=>$data['order_id'],
            'is_done'=>$data['is_done'],
            'abnormal_memo'=>$data['abnormal_memo'],
            'abnormal_type_id' => $data['abnormal_type_id']
        );
        
        $abnormal_msg = $data['abnormal_memo'];
	    $memo = array();
        $oAbnormal = $this->app->model('abnormal');
        #订单异常名称
        //echo $data['abnormal_type_id'];exit;
        $type_name = $this->app->model('abnormal_type')->dump(array('type_id'=>$data['abnormal_type_id']),'type_name');
        $data['abnormal_type_name'] = $type_name['type_name'];

        //备注信息
        $oldmemo = $oAbnormal->dump(array('abnormal_id'=>$data['abnormal_id']), 'abnormal_memo');
        $oldmemo= unserialize($oldmemo['abnormal_memo']);
        if ($oldmemo)
        foreach($oldmemo as $k=>$v){
            $memo[] = $v;
        }
        $op_name = kernel::single('desktop_user')->get_name();
        $newmemo =  htmlspecialchars($data['abnormal_memo']);
        $memo[] = array('op_name'=>$op_name, 'op_time'=>date('Y-m-d H:i',time()), 'op_content'=>$newmemo);
        $data['abnormal_memo'] = serialize($memo);
        $oAbnormal->save($data);

        switch ($data['is_done']){
            case 'false':
                $order_data = array('order_id'=>$data['order_id'],'abnormal'=>'true');
                $this->save($order_data);
                $memo = "设置订单异常(". $abnormal_msg .")";
                break;
            case 'true' :
                $order = $this->dump($data['order_id']);
                if ($order['ship_status'] == 2){
                    $filter = array(
                        'order_id'=>$data['order_id'],
                        'abnormal'=>'false',
                        'confirm'=>'Y',
                        // 'process_status'=>'splitting',
                        //'dispatch_time'=>0, #部分发货_保留分派时间
                        'print_finish'=>'false'
                    );
                    $is_splited   = app::get('ome')->model('order_items')->is_splited($data['order_id']);
                    $filter['process_status'] = $is_splited ? 'splited' : 'splitting';

                }elseif ($order['process_status'] == 'cancel'){
                    $filter = array(
                        'order_id'=>$data['order_id'],
                        'abnormal'=>'false',
                        'confirm'=>'N',
                        'group_id'=>NULL,
                        'op_id'=>NULL,
                        'dispatch_time'=>0,
                        'print_finish'=>'false'
                    );
                }
                #余单撤消或已发货的订单
                elseif($order['process_status']=='remain_cancel' || $order['ship_status'] == '1')
                {
                    $filter = array(
                            'order_id'=>$data['order_id'],
                            'abnormal'=>'false',
                            'confirm'=>'Y',
                    );
                }
                //[拆单]部分拆分部分退货订单异常处理
                elseif($order['process_status'] == 'splitting')
                {
                    $filter = array(
                        'order_id'=>$data['order_id'],
                        'abnormal'=>'false',
                        'confirm'=>'N',
                        'process_status'=>'unconfirmed',
                        'group_id'=>NULL,
                        'op_id'=>NULL,
                        'dispatch_time'=>0,
                        'print_finish'=>'false',
                    );
                    
                    //根据已拆分的发货单货品数量,判断订单的拆分状态
                    $orderLib = kernel::single('ome_order');
                    $process_status = $orderLib->get_order_process_status($data['order_id']);
                    if($process_status){
                        $filter['process_status'] = $process_status;
                    }
                    
                    //部分拆分或已拆分时,不用更新(确认组、确认人、分派时间)
                    if(in_array($filter['process_status'], array('splitting', 'splited'))){
                        unset($filter['group_id'], $filter['op_id'], $filter['dispatch_time']);
                    }
                }
                //已拆分完,部分退货订单异常处理
                elseif($order['process_status'] == 'splited')
                {
                    $filter = array(
                            'order_id'=>$data['order_id'],
                            'abnormal'=>'false',
                            'confirm'=>'N',
                            'process_status'=>'unconfirmed',
                            'group_id'=>NULL,
                            'op_id'=>NULL,
                            'dispatch_time'=>0,
                            'print_finish'=>'false',
                    );
                    
                    //根据已拆分的发货单货品数量,判断订单的拆分状态
                    $orderLib = kernel::single('ome_order');
                    $process_status = $orderLib->get_order_process_status($data['order_id']);
                    if($process_status){
                        $filter['process_status'] = $process_status;
                    }
                    
                    //部分拆分或已拆分时,不用更新(确认组、确认人、分派时间)
                    if(in_array($filter['process_status'], array('splitting', 'splited'))){
                        unset($filter['group_id'], $filter['op_id'], $filter['dispatch_time']);
                    }
                }
                else {
                    $filter = array(
                        'order_id'=>$data['order_id'],
                        'abnormal'=>'false',
                        'confirm'=>'N',
                        'process_status'=>'unconfirmed',
                        'group_id'=>NULL,
                        'op_id'=>NULL,
                        'dispatch_time'=>0,
                        'print_finish'=>'false'
                    );
                }
                $order_data = $filter;
                $this->save($order_data);

                $memo = "修改订单异常备注(". $abnormal_msg .")";
                break;
        }

        //写操作日志
        $oOperation_log = $this->app->model('operation_log');
        $oOperation_log->write_log('order_modify@ome',$data['order_id'],$memo);
    }

    /*
     * 获取订单明细列表
     *
     * @param int $order_id 订单id
     * @param bool $sort 是否要排序，默认不要。排序后的结果会按照普通商品、捆绑商品、赠品、配件等排列
     *
     * @return array
     */
    function getItemList($order_id,$sort=false){
        $order_items = array();

        if($sort){
            $items = $this->dump($order_id,"order_id",array("order_objects"=>array("*")));
            foreach($items['order_objects'] as $k=>$v){
                
                //价保订单SKU
                if($v['object_bool_type'] > 0){
                    if(kernel::single('ome_order_bool_objecttype')->isPriceProtect($v['object_bool_type'])) {
                        $v['is_price_protect'] = 'true';
                    }
                }
                
                // 直播间ID
                $v['addon'] = @json_decode($v['addon'], 1);
                if ($v['addon'] && isset($v['addon']['room_id'])) {
                    $v['room_id'] = $v['addon']['room_id'];
                }

                //object
                $order_items[$v['obj_type']][$k] = $v;
                
                //普通商品类型
                foreach($this->db->select("SELECT *,nums AS quantity FROM sdb_ome_order_items WHERE obj_id=".$v['obj_id']." AND item_type='product' ORDER BY item_type") as $it){
                    $order_items[$v['obj_type']][$k]['order_items'][$it['item_id']] = $it;
                }
                
                //其它商品类型
                foreach($this->db->select("SELECT *,nums AS quantity FROM sdb_ome_order_items WHERE obj_id=".$v['obj_id']." AND item_type<>'product' ORDER BY item_type") as $it){
                    $order_items[$v['obj_type']][$k]['order_items'][$it['item_id']] = $it;
                }
            }

        }else{
            $items = $this->dump($order_id,"order_id",array("order_objects"=>array("*",array("order_items"=>array("*")))));
            foreach($items['order_objects'] as $oneobj)
            {
                foreach ($oneobj['order_items'] as $objitems)
                $order_items[] = $objitems;
            }
        }
        return $order_items;
    }

    /*
     * 获取订单明细以及明细商品在各个仓库中的库存
     *
     * @param int $order_id
     * @param int $branch_id
     *
     * @return array
     */
    function getItemBranchStore($order_id, $branch_id=0)
    {
        $libBranchProduct    = kernel::single('ome_branch_product');

        $order_items = $this->getItemList($order_id,true);

        $tmp = array();
        if($order_items){
            $oDelivery = $this->app->model("delivery");
            $branchObj = $this->app->model("branch");
            $delivBranch = $branchObj->getDelivBranch($branch_id);
            $orderFreeze = app::get('material')->model('basic_material_stock_freeze')->getList('bm_id, branch_id, num', 
                        array('obj_type'=>1, 'obj_id'=>$order_id, 'bill_type'=>material_basic_material_stock_freeze::__ORDER_YOU));
            $orderFreeze = array_column($orderFreeze, null, 'bm_id');
            foreach($order_items as $obj_type=>$object_type){
                foreach($object_type as $obj_id=>$obj){

                    $i = 1;
                    foreach($obj['order_items'] as $k=>$item)
                    {
                        $branch_store = $libBranchProduct->get_branch_store($item['product_id']);

                        /* 货品库存 = 发货仓库存+绑定的备货仓库存 */
                        if($delivBranch){
                            foreach($delivBranch as $branch_id=>$branch){
                                if (array_key_exists($branch_id, $branch_store)) {
                                    foreach((array)$branch['bind_conf'] as $bindBid){
                                        $branch_store[$branch_id] += $branch_store[$bindBid];
                                    }
                                }
                            }
                        }
                        if($orderFreeze[$item['product_id']]) {
                            $ofv = $orderFreeze[$item['product_id']];
                            $branch_store[$ofv['branch_id']] += $ofv['num'];
                        }
                        $order_items[$obj_type][$obj_id]['order_items'][$k]['branch_store'] = $branch_store;

                        $sql = "SELECT SUM(number) AS 'num' FROM `sdb_ome_delivery_items_detail` did
                                                    JOIN `sdb_ome_delivery` d
                                                        ON d.delivery_id=did.delivery_id
                                                    WHERE order_item_id='".$item['item_id']."'
                                                        AND product_id='".$item['product_id']."'
                                                        AND d.status != 'back'
                                                        AND d.status != 'cancel' AND d.status!='return_back'
                                                        AND d.is_bind = 'false'";
                        $oi = $this->db->selectrow($sql);

                        $tmpNum = $item['quantity']-intval($oi['num']);
                        
                        //菜鸟直送
                        if ($obj['store_code']){
                            $wmsBranch = kernel::single('wmsmgr_func')->getBranchIdByStoreCode($obj['store_code']);
                            $cnServiceBranch = '';
                            if($wmsBranch && $wmsBranch['branch_id']){
                                $cnServiceBranch = $delivBranch[$wmsBranch['branch_id']]['name'];
                            }
                        }
                        
                        $order_items[$obj_type][$obj_id]['cnServiceBranch'] = $cnServiceBranch ? $cnServiceBranch :
                                                        ($obj['store_code'] ? $obj['store_code'] : '-');
                        $order_items[$obj_type][$obj_id]['left_nums'] = $tmpNum;
                        $order_items[$obj_type][$obj_id]['order_items'][$k]['left_nums'] = $tmpNum;
                        if ($obj_type == 'pkg' || $obj_type == 'giftpackage' || $obj_type == 'lkb'){
                            $order_items[$obj_type][$obj_id]['left_nums'] = intval($obj['quantity'] / $item['quantity'] * $tmpNum);
                            $order_items[$obj_type][$obj_id]['sendnum'] = intval($obj['quantity'] / $item['quantity'] * $item['sendnum']);

                            foreach ((array) $branch_store as $bk => $bv) {

                                $bstore = intval($obj['quantity'] / $item['quantity'] * $bv);

                                if ($i==1) {
                                   $order_items[$obj_type][$obj_id]['branch_store'][$bk] = $bstore;
                                } else {
                                    $order_items[$obj_type][$obj_id]['branch_store'][$bk] = min(intval($order_items[$obj_type][$obj_id]['branch_store'][$bk]),$bstore);
                                }
                            }
                        }
                         $i++;
                    }
                }
            }

            // [拆单]重新计算捆绑商品仓库库存数量
            if(!empty($order_items['pkg']))
            {
                foreach($order_items['pkg'] as $obj_id => $obj_li)
                {
                    if(!empty($obj_li['branch_store']))
                    {
                        foreach ($obj_li['branch_store'] as $brand_id => $branch_num)
                        {
                            foreach($obj_li['order_items'] as $item_id => $item)
                            {
                                $order_items['pkg'][$obj_id]['order_items'][$item_id]['branch_store'][$brand_id]    = intval($item['branch_store'][$brand_id]);
                            }
                        }
                    }
                }

                foreach($order_items['pkg'] as $obj_id => $obj_li)
                {
                    if(!empty($obj_li['branch_store']))
                    {
                        foreach ($obj_li['branch_store'] as $brand_id => $branch_num)
                        {
                            foreach($obj_li['order_items'] as $item_id => $item)
                            {
                                $get_branch_num     = intval($order_items['pkg'][$obj_id]['branch_store'][$brand_id]);
                                $branch_store_num   = intval($item['branch_store'][$brand_id]);

                                $order_items['pkg'][$obj_id]['branch_store'][$brand_id]     = min($get_branch_num, $branch_store_num);
                            }
                        }
                    }
                }
            }
        }

        return $order_items;
    }

    function getItemsNum($order_id, $product_id){
        $sql = "SELECT SUM(nums) AS '_count' FROM sdb_ome_order_items WHERE order_id='".$order_id."' AND product_id='".$product_id."'";
        $row = $this->db->selectrow($sql);
        return $row['_count'];
    }

    /*
     * 获取本订单的order_object的对象别名
     *
     * @param bigint $order_id
     *
     * @return array
     */
    function getOrderObjectAlias($order_id){
        $ret = array();
        $order_object = $this->db->select("SELECT DISTINCT(obj_type),obj_alias FROM sdb_ome_order_objects WHERE order_id={$order_id} ORDER BY obj_type");
        foreach($order_object as $v){
            $ret[$v['obj_type']] = $v['obj_alias'];
        }

        return $ret;
    }

    /*
     * 获取订单商品可能会使用到的仓库[只获取线上仓库]
     *
     * @param int $order_id
     *
     * @return array
     */
    function getBranchByOrder($order_id){
        $branch = $this->db->select("SELECT distinct(b.branch_id),b.name,b.uname,b.phone,b.mobile,b.stock_threshold,b.weight,b.branch_bn FROM sdb_ome_branch AS b
                                           WHERE b.disabled='false' AND b.is_deliv_branch='true' AND b.b_type=1 ORDER BY b.branch_id");


        return $branch;
    }

     /*
     * 生成订单号
     */
    function gen_id($flag='local') {
        $i = rand(0,9999);
        do{
            if(9999==$i){
                $i=0;
            }
            $i++;

            $prefix = '';
            if ($flag == 'local'){
                $prefix = 'L';
            }elseif ($flag == 'change'){
                $prefix = 'C';
            }elseif ($flag == 'bufa'){
                $prefix = 'B';
            }elseif($flag){
                $prefix = $flag;
            }
            
            $order_bn = $prefix.date('YmdH').'10'.str_pad($i,6,'0',STR_PAD_LEFT);
            
            $row = $this->db->selectrow("SELECT order_id from sdb_ome_orders where order_bn='". $order_bn ."'");
        }while($row);
        
        return $order_bn;
    }

    /*
     * 订单确认操作
     *
     * @param bigint $order_id 订单id
     * @param array $ship_info 收货人信息
     *
     * @return bool
     */
    function confirm($order_id,$is_auto=false){
        if($order=$this->dump($order_id,"*")){
            if($order['confirm']=='Y' || $order['process_status'] == 'cancel'){
                return false;
            }
        }

        $data['order_id'] = $order_id;
        $data['process_status'] = 'confirmed';
        $data['confirm'] = 'Y';

        $this->save($data);
        $oOperation_log = $this->app->model('operation_log');
        $opinfo = NULL;
        if ($is_auto) $opinfo = kernel::single('ome_func')->get_system();
        $oOperation_log->write_log('order_confirm@ome',$data['order_id'],"订单确认",NULL,$opinfo);

        return true;
    }

    /*
     * 拆分订单，生成发货单
     *
     * @param bigint $order_id
     */
    function mkDelivery($order_id,$delivery_info){
        $oDelivery = $this->app->model("delivery");
        $dly_orderObj =  $this->app->model("delivery_order");
        $delivery_itemObj = $this->app->model("delivery_items");
        $order_itemObj = $this->app->model("order_items");
        if(is_array($delivery_info) && count($delivery_info)>0){
            $ids = array();
            foreach($delivery_info as $delivery){
                $tmp_items = $delivery['order_items'];
                unset($delivery['order_items']);
                $ids[] = $oDelivery->addDelivery($order_id,$delivery,array(),$tmp_items);
            }
            //根据orderid找到delivery
            $dly_orderdata = $dly_orderObj->getList("*",array("order_id"=>$order_id));
            $dlyitemcount = 0;
            foreach ($dly_orderdata as $dly_order){
                $sql = "SELECT SUM(di.number) AS 'total' FROM sdb_ome_delivery_items di
                                        JOIN sdb_ome_delivery d
                                            ON di.delivery_id=d.delivery_id
                                        WHERE d.delivery_id ='".$dly_order['delivery_id']."'
                                        AND d.status != 'back'
                                        AND d.status != 'cancel'
                                        AND d.disabled = 'false'
                                        AND d.is_bind = 'false' AND d.status!='return_back'";

                $row = $this->db->selectrow($sql);
                $dlyitemcount += empty($row)?0:$row['total'];
            }
            $orderitemcount = 0;
            $orderitems = $order_itemObj->getList("*",array("order_id"=>$order_id));
            foreach ($orderitems as $oneitem){
                if ($oneitem['delete'] == 'false') $orderitemcount += $oneitem['nums'];
            }

            $data = [
                'splited_num_upset_sql' => 'IF(`splited_num` IS NULL, 1, `splited_num` + 1)',
            ];
            //如果delivery_item数量与order_item数量相等，则拆分完，否则部分拆分
            if ($orderitemcount <= $dlyitemcount)
            {
                $data['order_id'] = $order_id;
                $data['process_status'] = 'splited';
                $this->save($data);
            }
            else
            {
                $data['order_id'] = $order_id;
                $data['process_status'] = 'splitting';
                $this->save($data);
            }
            $oOperation_log = $this->app->model('operation_log');
            $oOperation_log->write_log('order_split@ome',$data['order_id'],"订单拆分");
            return $ids;
        }
    }

   /*
     * 快速查找订单信息
     */

    public function getOrders($name=null)
    {
        $sql = " SELECT order_id,order_bn FROM `sdb_ome_orders`
        WHERE ship_status = '1' and order_bn regexp '".$name."'";
        $data = $this->db->select($sql);
        $result = array();
        if ($data)
        foreach ($data as $v){
            $result[] = $v;
        }
        return $result;
    }


     /*根据过滤条件查询数据*/
     function getOrderId($finderResult){
        $where = $finderResult? $this->_filter($finderResult):'order_id in ('.implode(',',$finderResult['order_id']).')';
        $sql = 'select order_id  from  sdb_ome_orders   where '.$where;
        return $this->db->select($sql);
     }

     /*
      * 订单详情查询
      * @param order_bn string
      * @return array
      */
     function getOrderBybn($filter=null, $cols='*', $lim=0, $limit=1){
       $sql = 'select '.$cols.' FROM `sdb_ome_orders` ';
       $whereSql = '';
       $limitSql = '';
       if ($filter) $whereSql .= " WHERE ".$filter;
       $limitSql .= " limit $lim,$limit ";
       $rows =  $this->db->select($sql . $whereSql . $limitSql);

       $selectField = " SELECT count(*) as counts FROM (".$sql.$whereSql.") c";
       $count = $this->db->select($selectField);
       $rows['count'] = $count[0]['counts'];

       return $rows;
     }

    /*
     * 获取订单上下条
     * getOrderUpNext
     */
    function getOrderUpNext($id=null,$filter=null, $type='>'){
        if (!$id) return;
        $sql = "SELECT order_id,order_bn FROM `sdb_ome_orders` WHERE order_id $type '$id' ";
        $sql .= $filter;
        if ($type=='<') $desc = "desc";
        $sql .= " ORDER BY order_id $desc ";
        $tmp = $this->db->selectRow($sql);
        return $tmp;
    }

    /* create_order 订单创建
     * @param sdf $sdf
     * @return sdf
     */
    function create_order(&$sdf, &$msg = '')
    {
        //订单创建基础处理Lib
        $res = kernel::single('ome_order')->create_order($sdf, $msg);
        if(!$res){
            return false;
        }
        
        //brush特殊订单、补发订单,不需要后续功能
        if(in_array($sdf['shop_type'], array('brsh', 'bufa'))){
            return true;
        }
        
        $objRetrial = kernel::single('ome_order_retrial');
        list($rs, $msg) = $objRetrial->checkMonitorAbnormal($sdf);
        if($rs) {
            $objRetrial->monitorAbnormal($sdf['order_id'], $msg);
        }
        
        //保存订单发票信息
        if(app::get('invoice')->is_installed()){
            kernel::single('ome_order_invoice')->insertInvoice($sdf);
        }
        
        // 订单打标识
        $error_msg = '';
        kernel::single('ome_preprocess_label')->process($sdf['order_id'], $error_msg);
        
        //hold
        kernel::single('omeauto_auto_hold')->process($sdf['order_id']);
        
        // 订单预约信息
        kernel::single('ome_order_reservation')->createReservation($sdf);
        
        return true;
    }

    /**
     * 将前端店铺过来的货品规格属性值序列化
     * @access public
     * @param array $productattr 货品属性值
     * @return serialize 货品属性值
     */
    public function _format_productattr($productattr='',$product_id='',$original_str=''){
        if (!is_array($productattr) || empty($productattr)){
            $oSpecvalue = $this->app->model('spec_values');
            $oSpec = $this->app->model('specification');
            /*
            $oProducts = $this->app->model('products');//已不会调用该表和这段代码pdts，废弃 xiayuanjun
            $productattr = array();
            $product_info = $oProducts->dump(array('product_id'=>$product_id),"spec_desc");
            $spec_desc = $product_info['spec_desc'];
            if ($spec_desc['spec_value_id'])
            foreach ($spec_desc['spec_value_id'] as $sk=>$sv){
                $tmp = array();
                $specval = $oSpecvalue->dump($sv,"spec_value,spec_id");
                //$tmp['value'] = $specval['spec_value'];
                $tmp['value'] = $spec_desc['spec_value'][$sk];
                $spec = $oSpec->dump($specval['spec_id'],"spec_name");
                $tmp['label'] = $spec['spec_name'];
                $productattr[] = $tmp;
            }
            */
        }else{
            $productattr[0]['original_str'] = $original_str;//原始商品属性值
        }
        $addon['product_attr'] = $productattr;
        return serialize($addon);
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
                    continue;
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
      * 取消订单
      * @access public
      * @param Number $order_id 订单ID
      * @param String $memo 取消备注
      * @param Bool $is_request 是否询问请求
      * @param string $mode 请求类型:sync同步  async异步
      * @param Bool $must_log 订单收订叫回失败记录日志
      * @return Array
      */
     function cancel($order_id,$memo,$is_request=true,$mode='sync', $must_log = false){
         $operLogMdl = $this->app->model('operation_log');
         
         $rs = array('rsp'=>'fail','msg'=>'');
         
        //取消订单的时候先取消发货单
        $result = $this->cancel_delivery($order_id,$must_log);
        if ($result['rsp'] == 'succ') {
            //订单取消 API
            $instance = kernel::service('service.order');
            if($is_request == true && $instance && method_exists($instance, 'update_order_status')){
                $rs = $instance->update_order_status($order_id, 'dead', $memo, $mode);
            }
            
            //异步默认状态先置为成功
            if($mode == 'async'){
                $rs['rsp'] = 'succ';
            }
            $rs['rsp'] = ($rs['rsp'] == 'succ')?'success':'fail';
            
            //dispose
            if ($mode == 'async' || $rs['rsp'] == 'success'){
                $savedata = array();
                $savedata['process_status'] = 'cancel';
                $savedata['status'] = 'dead';
                $savedata['archive'] = 1;//订单归档
                $savedata['splited_num'] = 0;
                
                //update
                $affect_rows = $this->update($savedata, array('order_id'=>$order_id, 'status|noequal'=>'dead'));
                
                //没有更新影响行数,不需要释放库存
                //@todo：现在矩阵同分同秒推送退款完成单和更新订单时，会导致并发重复释放库存冻结;
                if(is_bool($affect_rows)){
                    //logs
                    $memo = '订单已经是取消状态!';
                    $operLogMdl->write_log('order_modify@ome', $order_id, $memo);
                    
                    return $rs;
                }else{
                    $memo .= '影响行数：'. $affect_rows;
                }
                
                //订单取消释放基础物料上的冻结，以及销售物料店铺冻结
                $this->unfreez($order_id);
                
                $order = $this->db_dump(['order_id'=>$order_id], '*');
                if($order['createtime'] > (time() - 600)) {
                    //如果10分钟内取消，则订单需要发起库存回写
                    kernel::single('inventorydepth_stock')->storeNeedUpdateSku($order_id, $order['shop_id']);
                }
                
                //invoice
                $arr_create_invoice = array(
                    'order_id'=>$order_id,
                    'source_status' => 'TRADE_CLOSED'
                );
                kernel::single('invoice_order_front_router', 'b2c')->operateTax($arr_create_invoice);
                
                // [更新]预约订单的取消状态
                $error_msg = '';
                kernel::single('ome_order_reservation')->operateReservationOrder($order, $error_msg);
                
                //logs
                $operLogMdl->write_log('order_modify@ome',$order_id,$memo);

                // 订单取消成功，触发订单取消事件
                $instance = kernel::service('ome.service.order.cancel.after');
                if($instance && method_exists($instance, 'cancel_order_after')){
                    $instance->cancel_order_after($order_id);
                }
            }
        }else{
            //取消失败，但有取消成功的情况下重置订单状态为部分拆分
            if($result['succ_num'] > 0){
                $tmp_order = array('order_id'=>$order_id,'confirm'=>'N','process_status'=>'splitting');
                $this->save($tmp_order);
            }
            
            kernel::single('ome_order')->resumeOrdStatus(array('order_id'=>$order_id));
            $rs['rsp'] = 'fail';
            $rs['msg']=$result['msg'] ? $result['msg'] : '发货单取消失败';
        }

        return $rs;
     }

    function searchOptions(){
        $parentOptions = parent::searchOptions();
        $childOptions = array(
            'product_bn'=>app::get('ome')->_('基础物料编码'),
            'product_barcode'=>app::get('ome')->_('条形码'),
            'sales_material_bn'=>app::get('ome')->_('销售物料编码'),
            'member_uname'=>app::get('ome')->_('用户名'),
            'ship_mobile'     =>app::get('ome')->_('联系手机'),
            'ship_tel'        =>app::get('ome')->_('联系电话'),
            'logi_no'=>app::get('ome')->_('物流单号')
        );
        
        //插入模糊搜订单号
        if($parentOptions){
            $array_1 = array_splice($parentOptions, 0, 1);
            $array_2 = array('head_order_bn'=>'模糊搜订单');
            
            $parentOptions = array_merge($array_1, $array_2, $parentOptions);
            
            unset($array_1, $array_2);
        }
        
        return $Options = array_merge($parentOptions,$childOptions);
    }

    /*
     * $order_ids id数组
     */
    function dispatch($data,$filter,$order_ids,$is_auto=false){
        $data['is_auto'] = 'false';//手动分派，改变自动处理标示
        if(empty($data['op_id'])){
            $data['op_id'] = 0;
        }
        $this->update($data,$filter);
        //写日志
        $oOperation_log = $this->app->model('operation_log');
        $oGroup = $this->app->model('groups');
        $oOperator = app::get('desktop')->model('users');
        $memo = "";

        if($data['group_id']){
            $group = $oGroup->dump(intval($data['group_id']));
            $memo = '订单分派给'.$group['name'];

            if($data['op_id']){
                $operator = $oOperator->dump(intval($data['op_id']));
                $memo .= '的'.$operator['name'];
            }
        }else{
            $memo = "订单撤销分派";
        }

        if($order_ids[0] == '_ALL_'){
            $opinfo = NULL;
            if ($is_auto) $opinfo = kernel::single('ome_func')->get_system();
            unset($filter['filter_sql']);
            $oOperation_log->batch_write_log('order_dispatch@ome',$filter,$memo,time(),$opinfo);
        }else{
            foreach($order_ids as $order_id){
                $opinfo = NULL;
                if ($is_auto){
                    $opinfo = kernel::single('ome_func')->get_system();
                }
                $oOperation_log->write_log('order_dispatch@ome',$order_id,$memo,NULL,$opinfo);
            }
        }


         //创建订单后执行的操作
        if($data['group_id'] && $oServiceOrder = kernel::servicelist('ome_dispatch_after')){
            if($order_ids[0] == '_ALL_'){
                $order_ids = array();
                $rows = $this->getList("order_id",$filter,0,-1);
                foreach($rows as $v){
                    $order_ids[] = $v['order_id'];
                }
            }
            foreach($order_ids as $v){
                 foreach($oServiceOrder as $object){
                    if(method_exists($object,'dispatch_after')){
                        $object->dispatch_after($v);
                     }
                 }
            }
        }
        return true;
    }

    /*
     * 订单退回
     * $order_ids id数组
     *
     */
    function goback($data,$filter,$remark,$act){
        $this->update($data,$filter);
        //写日志
        $oOperation_log = $this->app->model('operation_log');
        $memo = "";
        $op_name = kernel::single('desktop_user')->get_name();
        $memo = $op_name.$act.'，原因：'.$remark;
        $oOperation_log->write_log('order_dispatch@ome',$filter['order_id'],$memo,NULL,NULL);
        return true;
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
                $this->oSchema['csv']['order'] = array(
                    '*:订单号' => 'order_bn',
                    '*:支付方式' => 'payinfo/pay_name',
                    '*:下单时间' => 'createtime',
                    '*:付款时间' => 'paytime',
                    '*:配送方式' => 'shipping/shipping_name',
                    '*:配送费用' => 'shipping/cost_shipping',
                    '*:来源店铺编号' => 'shop_id',
                    '*:来源店铺' => 'shop_name',
                    '*:订单附言' => 'custom_mark',
                    '*:收货人姓名' => 'consignee/name',
                    '*:收货地址省份' => 'consignee/area/province',
                    '*:收货地址城市' => 'consignee/area/city',
                    '*:收货地址区/县' => 'consignee/area/county',
                    '*:收货详细地址' => 'consignee/addr',
                    '*:收货人固定电话' => 'consignee/telephone',
                    '*:电子邮箱' => 'consignee/email',
                    '*:收货人移动电话' => 'consignee/mobile',
                    '*:邮编' => 'consignee/zip',
                    '*:货到付款' => 'shipping/is_cod',
                    '*:是否开发票' => 'is_tax',
                    '*:发票抬头' => 'tax_title',
                    '*:发票金额' => 'cost_tax',
                    '*:优惠方案' => 'order_pmt',
                    '*:订单优惠金额' => 'pmt_order',
                    '*:商品优惠金额' => 'pmt_goods',
                    '*:折扣' => 'discount',
                    '*:返点积分' => 'score_g',
                    '*:商品总额' => 'cost_item',
                    '*:订单总额' => 'total_amount',
                    '*:买家会员名' => 'account/uname',
                    '*:来源渠道' => 'order_source', //order_type
                    '*:订单备注' => 'mark_text',
                    '*:商品重量' =>'weight',
                    '*:发票号'=>'tax_no',
                    '*:周期购'=>'createway',
                    '*:关联订单号'=>'relate_order_bn',
                    '*:补发原因' => 'bufa_reason',
                );
                $this->oSchema['csv']['obj'] = array(
                    '*:订单号' => '',
                    '*:商品货号' => '',
                    '*:商品名称' => '',
                    '*:购买单位' => '',
                    '*:商品规格' => '',
                    '*:购买数量' => '',
                    '*:商品原价' => '',
                    '*:销售价' =>'',
                    '*:商品优惠金额' => '',
                    '*:商品类型' => '',
                    '*:商品品牌' => '',
                );
                break;
        }
        #新增导出字段
        if($this->export_flag){
            $title = array(
                        '*:发货状态'=>'ship_status',
                        '*:付款状态'=>'pay_status'
                    );
            $this->oSchema['csv']['order'] = array_merge($this->oSchema['csv']['order'],$title);
        }
        #导出模板时，将不需要的字段从这里清除
        if(!$this->export_flag){
            unset($this->oSchema['csv']['order']['*:来源店铺']);
        }
        $this->ioTitle[$ioType]['order'] = array_keys( $this->oSchema[$ioType]['order'] );
        $this->ioTitle[$ioType]['obj'] = array_keys( $this->oSchema[$ioType]['obj'] );
        return $this->ioTitle[$ioType][$filter];
     }

    /**
     * 统计导出数据
     * 
     * @param Array $filter 过滤条件
     * @return void
     * @author
     * */
    public function fcount_csv($filter)
    {
        $count = $this->count($filter);
        if ($count < 500 && $count > 0) {
            $orderidList = array();

            $orderList = $this->getList('order_id',$filter);
            foreach ($orderList as $order) {
                $orderidList[] = $order['order_id'];
            }

            if ($orderidList) {
                $orderItemModel = app::get('ome')->model('order_items');
                $itemCount = $orderItemModel->count(array('order_id'=>$orderidList));

                if ($itemCount > 2500) {
                    $count = 600;
                }
            }
        }

        return $count;
    }

     //csv导出
     function fgetlist_csv( &$data,$filter,$offset,$exportType = 1 ){
        @ini_set('memory_limit','1024M'); set_time_limit(0);

        $this->export_flag = true;
        $max_offset = 1000; // 最多一次导出10w条记录
        if ($offset>$max_offset) return false;// 限制导出的最大页码数

        if( !$data['title']['order'] ){
            $title = array();
            foreach( $this->io_title('order') as $k => $v ){
                $title[] = $this->charset->utf2local($v);
            }
            $data['title']['order'] = '"'.implode('","',$title).'"';
        }
        if( !$data['title']['obj'] ){
            $title = array();
            foreach( $this->io_title('obj') as $k => $v )
                $title[] = $this->charset->utf2local($v);
            $data['title']['obj'] = '"'.implode('","',$title).'"';
        }
        $limit = 100;

        if( !$list=$this->getList('order_id',$filter,$offset*$limit,$limit) )return false;
        foreach( $list as $aFilter ){
            $aOrder = $this->dump($aFilter['order_id']);
            if($aOrder['order_bn']){
                $aOrder['order_bn'] = "=\"\"".$aOrder['order_bn']."\"\"";//"\t".$aOrder['order_bn'];#解决订单号科学计数法的问题
            }
            if( !$aOrder )continue;
            $objects = $this->db->select("SELECT * FROM sdb_ome_order_objects WHERE order_id=".$aFilter['order_id']);

            if ($objects){
                foreach ($objects as $obj){
                    if ($service = kernel::service("ome.service.order.objtype.".strtolower($obj['obj_type']))){
                        $item_data = $service->process($obj);
                        if ($item_data)
                        foreach ($item_data as $itemv){
                            $orderObjRow = array();
                            $orderObjRow['*:订单号']   = $aOrder['order_bn'];
                            $orderObjRow['*:商品货号'] = "\t".$itemv['bn'];
                            $orderObjRow['*:商品名称'] = "\t".str_replace("\n"," ",$itemv['name']);
                            $orderObjRow['*:购买单位'] = $itemv['unit'];
                            $orderObjRow['*:商品规格'] = $itemv['spec_info'] ? str_replace("\n"," ",$itemv['spec_info']):"-";
                            $orderObjRow['*:购买数量'] = $itemv['nums'];
                            $orderObjRow['*:商品原价'] = $itemv['price'];
                            $orderObjRow['*:销售价'] = $itemv['sale_price'] / $itemv['nums'];
                            $orderObjRow['*:商品优惠金额'] = $itemv['pmt_price'];

                            $data['content']['obj'][] = $this->charset->utf2local('"'.implode( '","', $orderObjRow ).'"');
                        }
                    }else {
                        $aOrder['order_items'] = $this->db->select("SELECT * FROM sdb_ome_order_items WHERE obj_id=".$obj['obj_id']." AND `delete`='false' AND order_id=".$aFilter['order_id']);
                        $aOrder['order_items'] = ome_order_func::add_items_colum($aOrder['order_items']);
                        $orderRow = array();
                        $orderObjRow = array();
                        $k = 0;
                        if ($aOrder['order_items'])
                        foreach( $aOrder['order_items'] as $itemk => $itemv ){
                            $addon = unserialize($itemv['addon']);
                            $spec_info = null;
                            if(!empty($addon)){
                                foreach($addon as $val){
                                    foreach ($val as $v){
                                        $spec_info[] = $v['value'];
                                    }
                                }
                            }
                            $_typeName = $this->getTypeName($itemv['product_id']);
                            $orderObjRow = array();
                            $orderObjRow['*:订单号']   = $aOrder['order_bn'];
                            $orderObjRow['*:商品货号'] = "\t".$itemv['bn'];
                            $orderObjRow['*:商品名称'] = "\t".str_replace("\n"," ",$itemv['name']);
                            $orderObjRow['*:购买单位'] = $itemv['unit'];
                            $orderObjRow['*:商品规格'] = $spec_info?implode('||', $spec_info):'-';//$itemv['spec_info'] ? str_replace("\n"," ",$itemv['spec_info']):"-";
                            $orderObjRow['*:购买数量'] = $itemv['nums'];
                            $orderObjRow['*:商品原价'] = $itemv['price'];
                            $orderObjRow['*:销售价'] = $itemv['sale_price'] / $itemv['nums'];
                            $orderObjRow['*:商品优惠金额'] = $itemv['pmt_price'];
                            $orderObjRow['*:商品类型'] = $_typeName['type_name'];
                            $orderObjRow['*:商品品牌'] = $_typeName['brand_name'];


                            $data['content']['obj'][] = $this->charset->utf2local('"'.implode( '","', $orderObjRow ).'"');
                        }
                    }
                }
            }

            //处理地区数据
            $area = explode('/',$aOrder['consignee']['area'] );
            if(strpos($area[0],":")){
                $tmp_province = explode(":",$area[0]);
                $province = $tmp_province[1];
            }else{
                $province = $area[0];
            }
            #付款状态
            switch($aOrder['pay_status']){
                case 0:
                    $aOrder['pay_status'] = '未支付';
                    break;
                case 1:
                    $aOrder['pay_status'] = '已支付';
                    break;
                case 2:
                    $aOrder['pay_status'] = '处理中';
                    break;
                case 3:
                    $aOrder['pay_status'] = '部分付款';
                    break;
                case 4:
                    $aOrder['pay_status'] = '部分退款';
                    break;
                case 5:
                    $aOrder['pay_status'] = '全额退款';
                    break;
                case 6:
                    $aOrder['pay_status'] = '退款申请中';
                    break;
                case 7:
                    $aOrder['pay_status'] = '退款中';
                    break;
                case 8:
                    $aOrder['pay_status'] = '支付中';
                    break;
            }
            #发货状态
            switch($aOrder['ship_status']){
                case 0:
                    $aOrder['ship_status'] = '未发货';
                    break;
                case 1:
                    $aOrder['ship_status'] = '已发货';
                    break;
                case 2:
                    $aOrder['ship_status'] = '部分发货';
                    break;
                case 3:
                    $aOrder['ship_status'] = '部分退货';
                    break;
                case 4:
                    $aOrder['ship_status'] = '已退货';
                    break;
            }
            $city = $area[1];
            if(strpos($area[2],":")){
                $tmp_county = explode(":",$area[2]);
                $county = $tmp_county[0];
            }else{
                $county = $area[2];
            }
            $aOrder['consignee']['area'] = array(
                'province' => $province,
                'city' => $city,
                'county' => $county,
            );

            $tmp_remark = kernel::single('ome_func')->format_memo($aOrder['custom_mark']);
            $tmp = '';
            if ($tmp_remark)
            foreach ($tmp_remark as $v){
                $tmp .= $v['op_content'].'-'.$v['op_time'].'-by-'.$v['op_name'].';';
            }
            $aOrder['custom_mark'] = str_replace("\n"," ",$tmp);
            //订单备注
            $tmp_mark_text = kernel::single('ome_func')->format_memo($aOrder['mark_text']);
            $tmp_mark = '';
            if ($tmp_mark_text) {
                foreach ($tmp_mark_text as $tv) {
                    $tmp_mark.=$tv['op_content'].'-'.$tv['op_time'].'-by-'.$tv['op_name'].';';
                }
            }
            $aOrder['mark_text'] = str_replace("\n"," ",$tmp_mark);
            $aOrder['consignee']['addr'] = str_replace("\n"," ",$aOrder['consignee']['addr']);
            //处理店铺信息
            $shop = $this->app->model('shop')->dump($aOrder['shop_id']);
            $aOrder['shop_id'] = $shop['shop_bn'];
            $aOrder['shop_name'] = $shop['name'];
            $aOrder['createtime'] = date('Y-m-d H:i:s',$aOrder['createtime']);
            $aOrder['paytime'] = $aOrder['paytime'] ? date('Y-m-d H:i:s',$aOrder['paytime']) : '尚未付款';

            $member = $this->app->model('members')->dump($aOrder['member_id']);

            #订单类型
            $aOrder['order_source'] = ome_order_func::get_order_source($aOrder['order_source']);

            $aOrder['account']['uname'] = $member['account']['uname'];
            $aOrder['shipping']['is_cod'] = $aOrder['shipping']['is_cod'] == 'true' ? '是':'否';
            $aOrder['is_tax'] = $aOrder['is_tax'] == 'true' ? '是':'否';            #会员邮箱
            $aOrder['consignee']['email'] = $member['contact']['email'];
            //处理订单优惠方案
            $order_pmtObj = $this->app->model('order_pmt');
            $pmt = $order_pmtObj->getList('pmt_describe',array('order_id'=>$aOrder['order_id']));
            foreach($pmt as $k=>$v){
                $pmt_tmp .= $v['pmt_describe'].";";
            }
            $aOrder['order_pmt']  = $pmt_tmp;
            $aOrder['createway']  = '';
             $aOrder['relate_order_bn'] = "=\"\"".$aOrder['relate_order_bn']."\"\"";
            unset($pmt_tmp);
            foreach( $this->oSchema['csv']['order'] as $k => $v ){
                $orderRow[$k] = $this->charset->utf2local(utils::apath( $aOrder,explode('/',$v) ));
            }
            $data['content']['order'][] = '"'.implode('","',$orderRow).'"';
        }
        return true;
    }

    function export_csv($data,$exportType = 1 ){
        $output = array();
      //  if( $exportType == 2 ){
            foreach( $data['title'] as $k => $val ){
                $output[] = $val."\n".implode("\n",(array)$data['content'][$k]);
            }
      //  }
        echo implode("\n",$output);
    }

    function prepared_import_csv(){
        $this->ioObj->cacheTime = time();
    }
    
    /**
     * [第三步]最终,导入数据保存
     * 
     * @return void
     */
    function finish_import_csv(){
        header("Content-type: text/html; charset=utf-8");
        
        $data = $this->import_data;
        unset($this->import_data);
        
        $orderTitle = array_flip( $this->io_title('order') );
        $objTitle = array_flip( $this->io_title('obj') );
        $orderSchema = $this->oSchema['csv']['order'];
        $objSchema =$this->oSchema['csv']['obj'];
        $oQueue = app::get('base')->model('queue');
        $salesMLib = kernel::single('material_sales_material');
        $lib_ome_order = kernel::single('ome_order');
        $fudaiLib = kernel::single('material_fukubukuro_dispatch');
        
        $count = 0;
        $limit = 50;
        $page = 0;
        $orderSdfs = array();
        foreach( $data as $ordre_id => $aOrder ){
            $orderSdf = array();
            $orderSdf = $this->ioObj->csv2sdf( $aOrder['order']['contents'][0] ,$orderTitle,$orderSchema  );
            $lucky_falg = false;
            
            $orderSdf['platform_order_bn'] = $orderSdf['order_bn'];

            //补发订单
            if($orderSdf['order_source'] == '补发订单'){
                $orderSdf['order_type'] = 'bufa';
                
                //获取原订单信息
                $relateOrderInfo = array();
                if($orderSdf['relate_order_bn']){
                    $relateOrderInfo = $this->app->model('orders')->dump(array('order_bn'=>$orderSdf['relate_order_bn']), '*');
                    
                    if ($relateOrderInfo['platform_order_bn']) {
                        $orderSdf['platform_order_bn'] = $relateOrderInfo['platform_order_bn'];
                    }
                    
                    //原订单的省、市、区、镇
                    list(, $areaTemp, ) = explode(':', $relateOrderInfo['consignee']['area']);
                    $areaTemp = explode('/', $areaTemp);
                    
                    //复制收货人信息
                    $orderSdf['consignee']['area']['province'] = $areaTemp[0];
                    $orderSdf['consignee']['area']['city'] = $areaTemp[1];
                    $orderSdf['consignee']['area']['county'] = $areaTemp[2];
                    $orderSdf['consignee']['area']['town'] = $areaTemp[3];
                    
                    $orderSdf['consignee']['mobile'] = $relateOrderInfo['consignee']['mobile'];
                    $orderSdf['consignee']['telephone'] = $relateOrderInfo['consignee']['telephone'];
                    $orderSdf['consignee']['email'] = $relateOrderInfo['consignee']['email'];
                    $orderSdf['consignee']['zip'] = $relateOrderInfo['consignee']['zip'];
                    $orderSdf['consignee']['addr'] = $relateOrderInfo['consignee']['addr'];
                    $orderSdf['consignee']['name'] = $relateOrderInfo['consignee']['name'];
                }
                
                unset($orderSdf['order_source']);
            }
            
            //处理店铺信息
            $shop = $this->app->model('shop')->dump(array('shop_bn'=>$orderSdf['shop_id']));
            if(!$shop) continue;

            $orderObjectItem = 0;
            $salesMLib = kernel::single('material_sales_material');
            $lib_ome_order = kernel::single('ome_order');
            $tostr = [];
            foreach( $aOrder['obj']['contents'] as $k => $v ){
                $salesMInfo = $salesMLib->getSalesMByBn($shop['shop_id'],$v[$objTitle['*:商品货号']]);
                if($salesMInfo){
                    if($salesMInfo['sales_material_type'] == 7){
                        //福袋组合
                        $luckybagParams = $salesMInfo;
                        $luckybagParams['sale_material_nums'] = $v[$objTitle['*:购买数量']];
                        $luckybagParams['shop_bn'] = $shop['shop_bn'];
                        
                        $fdResult = $fudaiLib->process($luckybagParams);
                        if($fdResult['rsp'] == 'succ'){
                            $basicMInfos = $fdResult['data'];
                        }else{
                            //标记福袋分配错误信息
                            //$error_msg = '销售物料编码：'. $salesMInfo['sales_material_bn'] .'获取福袋组合失败：'. $fdResult['error_msg'] .'!';
                        }
                        
                        $lucky_falg = true;
                        
                        //unset
                        unset($luckybagParams, $fdResult);
                    }elseif($salesMInfo['sales_material_type'] == 5){ //多选一
                        $basicMInfos = $salesMLib->get_order_pickone_bminfo($salesMInfo['sm_id'],$v[$objTitle['*:购买数量']],$shop['shop_id']);
                    }else{
                        //获取绑定的基础物料
                        $basicMInfos = $salesMLib->getBasicMBySalesMId($salesMInfo['sm_id']);
                    }
                    
                    //关联基础物料列表
                    if($basicMInfos){
                        $obj_number = $v[$objTitle['*:购买数量']];
                        $product_price = $v[$objTitle['*:商品原价']]; //商品原价
                        $obj_sale_price = bcmul($v[$objTitle['*:销售价']], $obj_number, 3); //商品总销售金额
                        $total_amount = bcmul($product_price, $obj_number, 3); //商品总金额
                        
                        //商品优惠金额
                        $pmt_price = bcsub($total_amount, $obj_sale_price, 3);
                        
                        //如果是促销类销售物料
                        if($salesMInfo['sales_material_type'] == 2){ //促销
                            $obj_type = $item_type = 'pkg';
                            
                            //item层关联基础物料平摊销售价
                            $salesMLib->calProSaleMPriceByRate($obj_sale_price, $basicMInfos);
                            
                            //平摊优惠金额
                            $salesMLib->calProPmtPriceByRate($pmt_price, $basicMInfos);
                            
                            //组织订单item明细
                            $return_arr_info = $lib_ome_order->format_order_items_data($item_type,$obj_number,$basicMInfos);
                        }elseif($salesMInfo['sales_material_type'] == 7){
                            //福袋组合
                            $obj_type = 'lkb';
                            $item_type = 'lkb';
                            
                            //格式化order_items
                            $return_arr_info = $lib_ome_order->format_order_items_data($item_type, $obj_number, $basicMInfos);
                            
                        }elseif($salesMInfo['sales_material_type'] == 5){ //多选一
                            $obj_type = $item_type = 'pko';
                            foreach($basicMInfos as &$var_basic_info){
                                $var_basic_info["price"] = $v[$objTitle['*:商品原价']];
                                $var_basic_info["sale_price"] = $v[$objTitle['*:销售价']];
                                
                                //商品优惠金额
                                $var_basic_info['pmt_price'] = $pmt_price;
                            }
                            unset($var_basic_info);
                            $return_arr_info = $lib_ome_order->format_order_items_data($item_type,$obj_number,$basicMInfos);
                        }else{ //普通、赠品
                            $obj_type = ($salesMInfo['sales_material_type'] == 1) ? 'goods' : ($salesMInfo['sales_material_type'] == 6 ? 'giftpackage' : 'gift');
                            $item_type = ($obj_type == 'goods') ? 'product' : $obj_type;
                            if($obj_type == 'gift'){
                                $v[$objTitle['*:商品原价']] = 0.00;
                                $v[$objTitle['*:销售价']] = 0.00;
                            }
                            foreach($basicMInfos as &$var_basic_info){
                                $var_basic_info["price"] = $v[$objTitle['*:商品原价']];
                                $var_basic_info["sale_price"] = $v[$objTitle['*:销售价']];
                                
                                //商品优惠金额
                                $var_basic_info['pmt_price'] = $pmt_price;
                            }
                            unset($var_basic_info);
                            
                            $return_arr_info = $lib_ome_order->format_order_items_data($item_type,$obj_number,$basicMInfos);
                        }
                        
                        $orderSdf['order_objects'][] = array(
                            'obj_type' => $obj_type,
                            'obj_alias' => $obj_type,
                            'goods_id' => $salesMInfo['sm_id'],
                            'bn' => $salesMInfo['sales_material_bn'],
                            'name' => $v[$objTitle['*:商品名称']],
                            'price' => $product_price,
                            'sale_price' => $obj_sale_price,
                            'amount' => $total_amount,
                            'quantity' => $obj_number,
                            'pmt_price' => $pmt_price,
                            'order_items' => $return_arr_info["order_items"],
                        );
                        unset($order_items);
                        $toStrItem = [
                            'name' => $v[$objTitle['*:商品名称']],
                            'num'  => $obj_number
                        ];
                        $tostr[]   = $toStrItem;
                    }
                }
            }
            
            $orderSdf["weight"] = $return_arr_info["weight"]; //商品重量
            $is_code = strtolower($orderSdf['shipping']['is_cod']);
            #检测货到付款
            if( ($is_code == '是') || ($is_code == 'true')){
                $is_code = 'true';
            }else{
                $is_code = 'false';
            }
            $is_tax = strtolower($orderSdf['is_tax']);
            #检测货到付款
            if( ($is_tax == '是') || ($is_tax == 'true')){
                $is_tax = 'true';
            }else{
                $is_tax = 'false';
            }
            $createway = strtolower($orderSdf['createway']);
            #检测货到付款
            if( ($createway == '是') || ($createway == 'true')){
                $createway = 'matrix';
            }else{
                $createway = 'import';
            }

            $orderSdf['shop_id']            = $shop['shop_id'];
            $orderSdf['shop_type']          = $shop['shop_type'];
            //临时变量province city county
            $orderSdf_province = $this->import_area_char_filter($orderSdf['consignee']['area']['province']);
            $orderSdf_city = $this->import_area_char_filter($orderSdf['consignee']['area']['city']);
            $orderSdf_county = $this->import_area_char_filter($orderSdf['consignee']['area']['county']);
            
            //防止excel导入的时间格式不正确,年份大于1年后的时间
            $createtime = ($orderSdf['createtime'] ? strtotime($orderSdf['createtime']) : time());
            if($createtime > (time() + 31536000)){
                $createtime = time();
            }
            
            $paytime = ($orderSdf['paytime'] ? strtotime($orderSdf['paytime']) : time());
            if($paytime > (time() + 31536000)){
                $paytime = time();
            }
            
            //导入未填写下单时间,直接使用当时日期
            $orderSdf['createtime']         = $createtime;
            $orderSdf['paytime']            = $paytime;
            $orderSdf['consignee']['area']  = $orderSdf_province."/".$orderSdf_city."/".$orderSdf_county;
            $orderSdf['consignee']['mobile']  = trim($orderSdf['consignee']['mobile']);
            $orderSdf['shipping']['is_cod'] = $is_code; //$orderSdf['shipping']['is_cod']?strtolower($orderSdf['shipping']['is_cod']):'false';
            $orderSdf['shipping']['cost_shipping'] = $orderSdf['shipping']['cost_shipping'] ? $orderSdf['shipping']['cost_shipping'] : '0';
            $orderSdf['is_tax']             = $is_tax;
            $orderSdf['cost_tax']           = $orderSdf['cost_tax'] ? $orderSdf['cost_tax'] : '0';
            $orderSdf['discount']           = $orderSdf['discount'] ? $orderSdf['discount'] : '0';
            $orderSdf['score_g']            = $orderSdf['score_g'] ? $orderSdf['score_g'] : '0';
            $orderSdf['cost_item']          = $orderSdf['cost_item'] ? $orderSdf['cost_item'] : '0';
            $orderSdf['total_amount']       = $orderSdf['total_amount'] ? $orderSdf['total_amount'] : '0';
            $orderSdf['pmt_order']          = $orderSdf['pmt_order'] ? $orderSdf['pmt_order'] : '0';
            $orderSdf['pmt_goods']          = $orderSdf['pmt_goods'] ? $orderSdf['pmt_goods'] : '0';
            
            //过滤金额中的逗号(当csv金额大于1000时会自动加入,逗号)
            $orderSdf['cost_item'] = $this->replace_import_price($orderSdf['cost_item']);
            $orderSdf['total_amount'] = $this->replace_import_price($orderSdf['total_amount']);
            $orderSdf['pmt_order'] = $this->replace_import_price($orderSdf['pmt_order']);
            
            //source
            $tmp_order_source               = ome_order_func::get_order_source();
            $tmp_order_source               = array_flip($tmp_order_source);
            $ome_order_source               = $tmp_order_source[$orderSdf['order_source']] ?: $orderSdf['order_source'];
            $orderSdf['order_source']       = $ome_order_source ?: 'direct';
            $orderSdf['custom_mark']        = kernel::single('ome_func')->append_memo($orderSdf['custom_mark']);
            $orderSdf['mark_text']          = kernel::single('ome_func')->append_memo($orderSdf['mark_text']);
            $orderSdf['createway']          = $createway;
            $orderSdf['source']             = 'local';
            //增加会员判断逻辑

            $memberObj = app::get('ome')->model('members');
            $tmp_member_name = trim($orderSdf['account']['uname']);
            $memberInfo = $memberObj->dump(array('uname'=>$tmp_member_name),'member_id');
            if($memberInfo){
                $orderSdf['member_id'] = $memberInfo['member_id'];
            }else{
                $members_data = array(
                    'uname'     =>  $tmp_member_name,
                    'name'      =>  $tmp_member_name,
                    'shop_type' =>  $shop['shop_type'],
                    'area_state'=>  $orderSdf_province,
                    'area_city' =>  $orderSdf_city,
                    'area_district'=> $orderSdf_county,
                    'shop_id'   =>  $shop['shop_id'],
                    'addr'      =>  $orderSdf['consignee']['addr'],
                    'tel'       =>  $orderSdf['consignee']['telephone'],
                    'mobile'    =>  $orderSdf['consignee']['mobile'],
                    'email'     =>  $orderSdf['consignee']['email'],
                    'zip'       =>  $orderSdf['consignee']['zip'],
                );
                $orderSdf['member_id'] = kernel::single('ome_member_func')->save($members_data,$shop['shop_id']);
            }
            $orderSdf['title'] = json_encode($tostr, JSON_UNESCAPED_UNICODE);
            
            //福袋标记
            if($lucky_falg){
                $orderSdf['lucky_falg'] = true;
            }
            
            if($count < $limit){
                $count ++;
            }else{
                $count = 0;
                $page ++;
            }

            $orderSdfs[$page][] = $orderSdf;
        }
        
        //使用队列创建订单
        foreach($orderSdfs as $v){
            $queueData = array(
                'queue_title'=>'订单导入',
                'start_time'=>time(),
                'params'=>array(
                    'sdfdata'=>$v,
                    'app' => 'ome',
                    'mdl' => 'orders'
                ),
                'worker'=>'ome_order_import.run',
            );
            $oQueue->save($queueData);
        }
        app::get('base')->model('queue')->flush();
    }
    
    /**
     * [第一步]整理导入的数据
     * 
     * @param $row
     * @param $title
     * @param $tmpl
     * @param $mark
     * @param $newObjFlag
     * @param $msg
     * @return bool|int[]|string[]
     */
    function prepared_import_csv_row($row,$title,&$tmpl,&$mark,&$newObjFlag,&$msg){
        $this->has_products = 0;
        //定义一个商品货号状态，为的是区别商品明细是否有值(2011_12_21_luolongjie)
        $shopModel = app::get('ome')->model('shop');

        $mark = 'contents';
        $fileData = $this->import_data;

        if( !$fileData )
            $fileData = array();
        
        //去除标题行BOM头(例如：'<feff>*:订单号')
        $row[0] = trim($row[0], "\xEF\xBB\xBF");
        
        if( substr($row[0],0,1) == '*' ){
            $titleRs =  array_flip($row);

            $mark = 'title';

            return $titleRs;
        }else{
            if( $row[0] ){
                $row[0] = trim($row[0]);
                if(is_array($title) && array_key_exists( '*:商品货号',$title )  ) {
                    $product_status = true;
                    $salesMLib = kernel::single('material_sales_material');
                    $shop = $shopModel->getList('shop_id',array('shop_bn'=>$this->import_data[$row[0]]['order']['contents'][0][6]),0,1);
                    $salesMInfo = $salesMLib->getSalesMByBn($shop[0]['shop_id'],$row[1]);
                    if($salesMInfo){
                        if($salesMInfo['sales_material_type'] == 4){ //福袋
                            $basicMInfos = $salesMLib->get_order_luckybag_bminfo($salesMInfo['sm_id']);
                        }else if($salesMInfo['sales_material_type'] == 5){ //多选一
                            $basicMInfos = $salesMLib->get_order_pickone_bminfo($salesMInfo['sm_id'],"1",$shop[0]['shop_id']);
                        }else{
                            //获取绑定的基础物料
                            $basicMInfos = $salesMLib->getBasicMBySalesMId($salesMInfo['sm_id']);
                        }
                        if(!$basicMInfos){
                            $product_status = false;
                        }
                    }else{
                        $product_status = false;
                    }

                    if ($product_status==false) $this->not_exist_product_bn = isset($this->not_exist_product_bn)?array_merge($this->not_exist_product_bn,array($row[1])):array($row[1]);


                    if(!is_numeric($row[5]) || $row[5] < 1){

                        $this->not_num_zero = isset($this->not_num_zero)?array_merge($this->not_num_zero,array($row[1])):array($row[1]);

                    }

                    //说明商品明细有过值，并非为空(2011_12_21_luolongjie)
                    $this->has_products = 1;
                    $fileData[$row[0]]['obj']['contents'][] = $row;
                }else{
                    //计数判断，是否超过10000条记录，超过就提示数据过多
                    if(isset($this->order_nums)){
                        kernel::log($this->order_nums);
                        $this->order_nums ++;
                        if($this->order_nums > 5000){
                            unset($this->import_data);
                            $msg['error'] = "导入的数据量过大，请减少到5000单以下！";
                            return false;
                        }
                    }else{
                        $this->order_nums = 0;
                    }

                    if(isset($fileData[$row[0]])){
                        $this->duplicate_order_bn_in_file = isset($this->duplicate_order_bn_in_file)?array_merge($this->duplicate_order_bn_in_file,array($row[0])):array($row[0]);
                    }
                    if($this->dump(array('order_bn'=>$row[0]))){
                        $this->duplicate_order_bn_in_db = isset($this->duplicate_order_bn_in_db)?array_merge($this->duplicate_order_bn_in_db,array($row[0])):array($row[0]);
                    }

                    if(empty($row[6])){
                        unset($this->import_data);
                        $msg['error'] = "来源店铺编号不能为空";
                        return false;
                    }
                    
                    //补发订单,不用判断收货人信息
                    //@todo：保存前会自动获取原订单的收货人信息;
                    if($row[29] != '补发订单'){
                        //check
                        if(empty($row[13]) && empty($row[15])){
                            unset($this->import_data);
                            $msg['error'] = '收货人移动电话/收货人固定电话不能同时为空';
                            return false;
                        }
                        
                        //$preg_phone='/^[\d]+$/i';
                        $preg_phone = '/^[\d-]{8,16}$/i'; //支持虚拟号
                        if($row[15] && !preg_match($preg_phone, $row[15])){
                            unset($this->import_data);
                            $msg['error'] = '收货人移动电话格式不正确('. $row[15] .')';
                            return false;
                        }
                        
                        if(empty($row[9]) && empty($row[10]) && empty($row[11])){
                            $msg['error'] = '收货地址省、市、区/县不能为空';
                            return false;
                        }
                        
                    } else {
                        //关联订单号
                        if(empty($row[34])){
                            unset($this->import_data);
                            $msg['error'] = '补发订单关联单号不能为空';
                            return false;
                        }
                        //原平台订单
                        $originalInfo = app::get('ome')->model('orders')->dump(array('order_bn'=>$row[34]), 'order_id,order_bn,createway,relate_order_bn');
                        if(empty($originalInfo)){
                            unset($this->import_data);
                            $msg['error'] = '关联订单不存在';
                            return false;
                        }
                        
                        // 补发原因
                        if(empty($row[35])){
                            unset($this->import_data);
                            $msg['error'] = '补发原因不能为空';
                            return false;
                        }
                    }
                    
                    //shop
                    $shop = $shopModel->getList('shop_bn',array('shop_bn'=>$row[6]),0,1);
                    if (!$shop) {
                            unset($this->import_data);
                            $msg['error'] = "来源店铺【".$row[6]."】不存在";
                            return false;
                    }
    
                    $area = $row[9] . '/' . $row[10] . '/' . $row[11];
                    list($res, $err_msg) = kernel::single('eccommon_regions')->checkRegion($area);
                    if (!$res) {
                        $msg['error'] = sprintf("收货地址【%s】不在地址库！", $err_msg);
                        return false;
                    }
                    
                    $fileData[$row[0]]['order']['contents'][] = $row;
                }

                $this->import_data = $fileData;
            }
        }
        
        return true;
    }
    
    /**
     * [第二步]检查导入的订单商品明细
     * 
     * @param $data
     * @param $mark
     * @param $tmpl
     * @param $msg
     * @return bool
     */
    function prepared_import_csv_obj($data,$mark,$tmpl,&$msg = ''){
        $error_msg = array();
        //当商品没有货号时候，停止导入（有其他商品明细，却没货号，或者货号不对）
        if(isset($this->not_exist_product_bn)){
            if(count($this->not_exist_product_bn) > 10){
                for($i=0;$i<10;$i++){
                    $not_exist_product_bn[] = current($this->not_exist_product_bn);
                    next($this->not_exist_product_bn);
                }
                $more = "...";
            }else{
                $not_exist_product_bn = $this->not_exist_product_bn;
                $more = "";
            }
            $error_msg[] = "不存在的销售物料或没绑定：".implode(",",$not_exist_product_bn).$more;
        }elseif($this->has_products == 0){ //没有任何商品明细的时候
            $error_msg[] = "缺少商品明细";
        }elseif($this->not_num_zero){
            if(count($this->not_num_zero)>10){
                for($i=0;$i<10;$i++){
                    $not_num_zero[] = current($this->not_num_zero);
                    next($this->not_num_zero);
                }
                $more = "...";
            }else{
                $not_num_zero = $this->not_num_zero;
                $more = '';
            }
            $error_msg[] = "购买数量小于0：".implode(",",$not_num_zero).$more;
        }
    
        if(isset($this->duplicate_order_bn_in_file)){
            if(count($this->duplicate_order_bn_in_file) > 10){
                for($i=0;$i<10;$i++){
                    $duplicate_order_bn_in_file[] = current($this->duplicate_order_bn_in_file);
                    next($this->duplicate_order_bn_in_file);
                }
                $more = "...";
            }else{
                $more = "";
            }
            $error_msg[] = "文件中以下订单号重复：".implode(",",$this->duplicate_order_bn_in_file).$more;
        }
        if(isset($this->duplicate_order_bn_in_db)){
            if(count($this->duplicate_order_bn_in_db) > 10){
                for($i=0;$i<10;$i++){
                    $duplicate_order_bn_in_db[] = current($this->duplicate_order_bn_in_db);
                    next($this->duplicate_order_bn_in_db);
                }
                $more = "...";
            }else{
                $more = "";
            }
            $error_msg[] = "以下订单号在系统中已经存在：".implode(",",$this->duplicate_order_bn_in_db).$more;
        }
        
        foreach ($this->import_data as $key =>$row) {
            $item_amount = $item_pmt_price = $item_devide_order_fee = 0;
            if(empty($row['order']['contents'])) {
                $error_msg[] = sprintf('[%s]没有主信息',$key);
                continue;
            }
            $order_row = current($row['order']['contents']);
            $obj_row = $row['obj']['contents'];
            foreach ($obj_row as $obj_key => $obj_val) {
                //行原价小计
                $item_amount_tmp = (float)$obj_val[6] * (float)$obj_val[5];
                $item_amount += $item_amount_tmp;
                //行商品优惠
                $item_pmt_price_tmp = $item_amount_tmp - ((float)$obj_val[7] * (float)$obj_val[5]);
                $item_pmt_price += $item_pmt_price_tmp;
            }
            // 检查金额
            $cost_item = $item_amount;
            $cost_freight = $order_row[5];
            $cost_tax = 0;
            $discount = $order_row[24];
            $pmt_order = $order_row[22];
            $pmt_goods = $item_pmt_price;
            $total_amount = (float)$cost_item + (float)$cost_freight + (float)$cost_tax - (float)$discount - (float)$pmt_order - (float)$pmt_goods;
            if (bccomp((float)$order_row[26], round($cost_item,2), 2) !== 0) {
                $error_msg[] = sprintf('商品总额[%s]明细与订单行商品行[%s]不一致',$cost_item,$order_row[26]);
            }
            if (bccomp((float)$order_row[27], round($total_amount,2), 2) !== 0) {
                $error_msg[] = sprintf('订单总额[%s](商品总额[%s]+配送费用[%s]+税金[%s]-折扣[%s]-订单优惠[%s]-商品优惠[%s])不对',$order_row[27],$cost_item,$cost_freight,$cost_tax,$discount,$pmt_order,$pmt_goods);
            }
        }
        if(!empty($error_msg)){
            unset($this->import_data);
            $msg['error'] = implode("     ",$error_msg);
            return false;
        }
        return true;
    }

    function counter_dispatch($filter=null){
        $table_name = app::get('ome')->model('orders')->table_name(1);
        $strWhere = '';

        $sql = 'SELECT count(*) as _count FROM `'.$this->table_name(1).'` WHERE '.$this->_filter($filter) . $strWhere;
        $row = $this->db->select($sql);

        return intval($row[0]['_count']);
    }

    function countAbnormal($filter=null){
        $abnormal_table_name = app::get('ome')->model('abnormal')->table_name(1);
        $strWhere = '';
        if(isset($filter['abnormal_type_id'])){
            $strWhere = ' AND '.$abnormal_table_name.'.abnormal_type_id ='.$filter['abnormal_type_id'];
        }

        $row = $this->db->select('SELECT count(*) as _count FROM `'.$this->table_name(1).'` LEFT JOIN  '.$abnormal_table_name.'  ON '.$this->table_name(1).'.order_id = '.$abnormal_table_name.'.order_id WHERE '.$this->_abnormalFilter($filter,$this->table_name(1)) . $strWhere);

        return intval($row[0]['_count']);
    }

    function getlistAbnormal($cols='*', $filter=array(), $offset=0, $limit=-1, $orderType=null){
        if(!$cols){
            $cols = $this->defaultCols;
        }
        if(!empty($this->appendCols)){
            $cols.=','.$this->appendCols;
        }
        if($this->use_meta){
             $meta_info = $this->prepare_select($cols);
        }

        $abnormal_table_name = app::get('ome')->model('abnormal')->table_name(1);
        $strWhere = '';
        if(isset($filter['abnormal_type_id'])){
            $strWhere = ' AND '.$abnormal_table_name.'.abnormal_type_id ='.$filter['abnormal_type_id'];
        }

        $this->defaultOrder[0] = $this->table_name(true).'.createtime';
        $tmpCols = array();
        foreach(explode(',',$cols) as $col){
            if(strpos($col, 'as column')){
                $tmpCols[] = $col;
            }else{
                $tmpCols[] = $this->table_name(true).'.'.$col;
            }
        }
        $cols = implode(',',$tmpCols);
        unset($tmpCols);

        $orderType = $orderType?$orderType:$this->defaultOrder;
        $sql = 'SELECT '.$cols.' FROM `'.$this->table_name(true).'` LEFT JOIN  '.$abnormal_table_name.'  ON '.$this->table_name(1).'.order_id = '.$abnormal_table_name.'.order_id WHERE '.$this->_abnormalFilter($filter,$this->table_name(1)) . $strWhere;

        if($orderType)$sql.=' ORDER BY '.(is_array($orderType)?implode(' ', $orderType):$orderType);

        $data = $this->db->selectLimit($sql,$limit,$offset);

        foreach ((array) $data as $key => $value) {
            foreach ($this->__encrypt_cols as $field => $type) {
                if (isset($value[$field])) {
                    $data[$key][$field] = (string) kernel::single('ome_security_factory')->decryptPublic($value[$field],$type);
                }
            }
        }

        $this->tidy_data($data, $cols);
        if($this->use_meta && count($meta_info['metacols']) && $data){
            foreach($meta_info['metacols'] as $col){
                $obj_meta = new dbeav_meta($this->table_name(true),$col,$meta_info['has_pk']);
                $obj_meta->select($data);
            }
        }
        return $data;
    }

    function getColumns(){
        $columns = array();
        foreach( $this->_columns() as $k=>$v ){
            $columns[] = $k;
        }

        return $columns;
    }


    /**如果是订单编辑，保存订单的原始数据
     * @param int $log_id
     */
    function write_log_detail($log_id,$detail){
        $ooObj = $this->app->model('operations_order');
        $data = array(
           'log_id'=>$log_id,
           'order_id' => $detail['order_id'],
           'order_detail' =>$detail,
        );

        $ooObj->save($data);
    }

    /**
     * 读取订单编辑前的详情
     */
    function read_log_detail($order_id,$log_id){
        $ooObj = $this->app->model('operations_order');
        $sObj = $this->app->model('shop');
        $oodetail = $ooObj->dump(array('order_id'=>$order_id,'log_id'=>$log_id),'*');

        $detail = unserialize($oodetail['order_detail']);

        $oodetail['order_detail'] = $detail;
        foreach($detail['order_objects'] as $key=>$value){
            foreach($value['order_items'] as $k=>$v){
                $addon[$key][$k] = unserialize($v['addon']);
                $add[$key][$k] = '';
                // 检查 $addon[$key][$k] 是否为数组且包含 'product_attr' 键
                if (is_array($addon[$key][$k]) && isset($addon[$key][$k]['product_attr'])) {
                    foreach((array)$addon[$key][$k]['product_attr'] as $vl){
                        if (!is_array($vl)) {
                            continue;
                        }
                        $add[$key][$k] .= $vl['label'].":".$vl['value'].";";
                    }
                }
                $detail['order_objects'][$key]['order_items'][$k]['addon'] = $add[$key][$k];
                $detail['order_objects'][$key]['order_items'][$k]['quantity'] = $v['quantity'] ? $v['quantity'] : $v['nums'];

            }
        }

        //发货人信息
        if(empty($detail['consigner']['name'])){
            $shop_info = $sObj->getList('*',array('shop_id'=>$detail['shop_id']));
            $shop_info = $shop_info[0];
            $detail['consigner']['name'] = $shop_info['default_sender'];
            $detail['consigner']['area'] = $shop_info['area'];
            $detail['consigner']['addr'] = $shop_info['addr'];
            $detail['consigner']['zip'] = $shop_info['zip'];
            $detail['consigner']['email'] = $shop_info['email'];
            $detail['consigner']['tel'] = $shop_info['tel'];
        }
        if($detail['shop_type'] == 'shopex_b2b'){
            //代销人信息
            $osaObj = app::get('ome')->model('order_selling_agent');
            $agent = $osaObj->dump(array('order_id'=>$detail['order_id']),'*');
            $detail['agent'] = $agent;
        }
        //买家留言
        $custom_mark = unserialize($detail['custom_mark']);
        if ($custom_mark){
            foreach ($custom_mark as $k=>$v){
                $custom_mark[$k] = $v;
                if (!strstr($v['op_time'], "-")){
                    $v['op_time'] = date('Y-m-d H:i:s',$v['op_time']);
                    $custom_mark[$k]['op_time'] = $v['op_time'];
                }
            }
        }

        //订单备注
        $mark_text = unserialize($detail['mark_text']);
        if ($mark_text)
        foreach ($mark_text as $k=>$v){
            $mark_text[$k] = $v;
            if (!strstr($v['op_time'], "-")){
                $v['op_time'] = date('Y-m-d H:i:s',$v['op_time']);
                $mark_text[$k]['op_time'] = $v['op_time'];
            }
        }
        $detail['mark_type_arr'] = ome_order_func::order_mark_type();//订单备注旗标

        $detail['custom_mark'] = $custom_mark;
        $detail['mark_text'] = $mark_text;
        $oodetail['order_detail'] = $detail;
        return $oodetail;
    }


    //不能进行订单编辑的状态判断
    /**
     * not_allow_edit
     * @param mixed $order_id ID
     * @return mixed 返回值
     */
    public function not_allow_edit($order_id){
        $order = $this->dump($order_id);
        //已取消的订单不允许编辑
        if($order['process_status'] == 'cancel'){
            $data['msg'] = '该订单已取消，不能进行编辑';
            $data['res'] = 'false';
            return $data;
        }
        //退款申请中的订单不允许编辑
        if($order['pay_status'] == '6'){
            $data['msg'] = '退款申请中的订单不允许编辑';
            $data['res'] = 'false';
            return $data;
        }
        //退款中的订单不允许编辑
        if($order['pay_status'] == '7'){
            $data['msg'] = '退款中的订单不允许编辑';
            $data['res'] = 'false';
            return $data;
        }
        //支付中的订单不允许编辑
        if($order['pay_status'] == '8'){
            $data['msg'] = '支付中的订单不允许编辑';
            $data['res'] = 'false';
            return $data;
        }
        //已发货订单不允许编辑
        if($order['ship_status'] == '1'){
            $data['msg'] = '已发货订单不允许编辑';
            $data['res'] = 'false';
            return $data;
        }
        //部分发货订单不允许编辑
        if($order['ship_status'] == '2'){
            $data['msg'] = '部分发货订单不允许编辑';
            $data['res'] = 'false';
            return $data;
        }
        //部分退货订单不允许编辑
        if($order['ship_status'] == '3'){
            $data['msg'] = '部分退货订单不允许编辑';
            $data['res'] = 'false';
            return $data;
        }
        //已退货订单不允许编辑
        if($order['ship_status'] == '4'){
            $data['msg'] = '已退货订单不允许编辑';
            $data['res'] = 'false';
            return $data;
        }
        //余单撤销订单不允许编辑
        if($order['process_status'] == 'remain_cancel'){
            $data['msg'] = '余单撤销订单不允许编辑';
            $data['res'] = 'false';
            return $data;
        }
        return true;
   }

   /*根据发货单号获取订单运费总额*/
   function get_costfreight($delivery_id = array()){
        $Odelivery_order = $this->app->model('delivery_order');
        $getOrders = $Odelivery_order->getList('order_id',array('delivery_id|in'=>$delivery_id));
        $costfreight = 0;
        if($getOrders){
            foreach ($getOrders as $k => $v) {
                $orderid[$k] = $v['order_id'];
            }

            $costfreight = $this->getList('sum(cost_freight) as cost_freight',array('order_id|in'=>$orderid));
            $costfreight = $costfreight[0]['cost_freight'];
        }

        return $costfreight;
   }


    /**
     * 统计订单商品重量
     * @param  order_id
     * @return void
     */
    function getOrderWeight($order_id,$type='',$additional=''){
        $orderObj = $this->app->model('orders');
        $basicMaterialExtObj = app::get('material')->model('basic_material_ext');
        $salesMaterialExtObj = app::get('material')->model('sales_material_ext');

        $weight = 0;
        $order = $orderObj->dump($order_id,"order_id",array("order_objects"=>array("*",array("order_items"=>array("*")))));
        foreach ($order['order_objects'] as $k=>$v) {
            if($v['obj_type']=='pkg'){
                $bn = $v['bn'];
                $pkg = $salesMaterialExtObj->dump(array('sm_id'=>$v['goods_id']),'weight');
                if ($pkg['weight']>0){
                    //捆绑是一个删全删除的，所以取一个看状态是否是删除
                    $order_items_flag = array_pop($v['order_items']);
                    if ($order_items_flag['delete']=='false') {
                        $weight+=$pkg['weight']*$v['quantity'];
                    }
                }else {
                    foreach($v['order_items'] as $k1=>$v1){
                        if ($v1['delete'] == 'false') {
                            $products = $basicMaterialExtObj->dump(array('bm_id'=>$v1['product_id']),'weight');
                            if($products['weight']>0){
                                $weight+=$products['weight']*$v1['quantity'];
                            }else{
                                $weight=0;
                                break 2;
                            }
                        }
                    }
                }
            }else{
                foreach($v['order_items'] as $k1=>$v1){
                    if ($v1['delete'] == 'false') {
                        $products = $basicMaterialExtObj->dump(array('bm_id'=>$v1['product_id']),'weight');
                        if($products['weight']>0){
                            $weight+=$products['weight']*$v1['quantity'];
                        }else{
                            $weight=0;
                            break 2;
                        }
                    }
                }
            }
        }

        $weight = round($weight,3);
        return $weight;
    }

    function getOrdersBnById($original_id = null){
       $sql="
            select
               do.delivery_id,o.order_bn
            from sdb_ome_delivery_order as do
            join sdb_ome_orders as o on do.order_id=o.order_id and do.delivery_id in ($original_id)";
        $_value = $this->db->select($sql);
        return $_value?$_value:null;
    }

    /**
     * 异常订单过滤条件
     * 
     */

    function _abnormalFilter($filter,$tableAlias=null,$baseWhere=null){
        $table_name = $this->table_name(true);
        if(isset($filter['archive'])){
            $where = ' '.$table_name.'.archive='.$filter['archive'].' ';
            unset($filter['archive']);
        }else{
            $where = "1";
        }
        ///////////////////////////
        // 加密处理逻辑 2017/5/5 by cp //
        ///////////////////////////
        foreach ($filter as $key => $value) {
            $pos = strpos($key,'|');
            $field = false !== $pos ? substr($key,0,$pos): $key;

            $encrypt_type = $this->__encrypt_cols[$field];
            if ($encrypt_type) {
                $searchtype = false !== $pos ? substr($key,$pos+1): 'nequal';

                if ($searchtype!='nequal' && in_array($encrypt_type,array('search','nick','receiver_name'))) {
                    $encryptVal = kernel::single('ome_security_factory')->search($value,$encrypt_type);
                } else {
                    $encryptVal = kernel::single('ome_security_factory')->encryptPublic($value,$encrypt_type);
                }


                $originalVal      = utils::addslashes_array($value);
                $encryptVal = utils::addslashes_array($encryptVal);

                switch ($searchtype) {
                    case 'has':
                        $baseWhere[] = "({$table_name}.{$field} LIKE '%".$originalVal."%' || {$table_name}.{$field} LIKE '%".$encryptVal."%')";
                        break;
                    case 'head':
                        $baseWhere[] = "({$table_name}.{$field} LIKE '".$originalVal."%' || {$table_name}.{$field} LIKE '%".$encryptVal."%')";
                        break;
                    case 'foot':
                        $baseWhere[] = "({$table_name}.{$field} LIKE '%".$originalVal."' || {$table_name}.{$field} LIKE '%".$encryptVal."%')";
                        break;
                    default:
                        $baseWhere[] = "{$table_name}.{$field} IN('".$originalVal."','".$encryptVal."')";
                        break;
                }

                unset($filter[$key]);
            }
        }

        if(isset($filter['ship_tel_mobile'])){
            $encryptVal = kernel::single('ome_security_factory')->encryptPublic($filter['ship_tel_mobile'],'phone');
            $encryptVal  = utils::addslashes_array($encryptVal);
            $originalVal = utils::addslashes_array($filter['ship_tel_mobile']);

            $baseWhere[] = "({$table_name}.ship_tel IN('".$originalVal."','".$encryptVal."')||{$table_name}.ship_mobile IN('".$originalVal."','".$encryptVal."'))";

            unset($filter['ship_tel_mobile']);
        }
        
        //brush特殊订单
        $where .= ' AND '. $table_name .'.order_type<>"brush" ';
        
        //order_confirm_filter
        if(isset($filter['order_confirm_filter'])){
            $where .= ' AND '.$table_name.'.'.$filter['order_confirm_filter'];
            unset($filter['order_confirm_filter']);
        }
        
        if (isset($filter['assigned'])){
            if ($filter['assigned'] == 'notassigned'){
                $where .= ' AND ('.$table_name.'.group_id=0 AND '.$table_name.'.op_id=0)';
            }else{
                $where .= '  AND ('.$table_name.'.op_id > 0 OR '.$table_name.'.group_id > 0)';
            }
            unset ($filter['assigned']);
        }
        
        if (isset($filter['balance'])){
            if ($filter['balance'])
                $where .= " AND ".$table_name.".`old_amount` != 0 AND ".$table_name.".`total_amount` != `old_amount` ";
            else
                $where .= " AND ".$table_name.".`old_amount` = 0 ";
        }
        
        //自动取消订单过滤条件
        if (isset($filter['auto_cancel_order_filter'])){
            $where .= '  AND '.$table_name.'.'.$filter['auto_cancel_order_filter'];
        }

        if(isset($filter['product_bn'])){
            
            //多基础物料查询
            if($filter['product_bn'] && is_string($filter['product_bn']) && strpos($filter['product_bn'], "\n") !== false){
                $filter['product_bn'] = array_unique(array_map('trim', array_filter(explode("\n", $filter['product_bn']))));
            }
            
            //按基础物料查询相关订单
            $itemsObj = $this->app->model("order_items");
            $rows = $itemsObj->getOrderIdByPbn($filter['product_bn']);
            $orderId[] = 0;
            foreach($rows as $row){
                $orderId[] = $row['order_id'];
            }
            
            $where .= '  AND '.$table_name.'.order_id IN ('.implode(',', $orderId).')';
            unset($filter['product_bn']);
        }elseif(isset($filter['sales_material_bn'])){
            $orderId = array();
            $orderId[] = 0;
            
            //赋值
            $filter['product_bn'] = $filter['sales_material_bn'];
            
            //多销售物料查询
            if($filter['product_bn'] && is_string($filter['product_bn']) && strpos($filter['product_bn'], "\n") !== false){
                $filter['product_bn'] = array_unique(array_map('trim', array_filter(explode("\n", $filter['product_bn']))));
            }
            
            //按销售物料查询相关订单
            $itemsObj = $this->app->model('order_items');
            $objectRows = $itemsObj->getOrderIdByPkgbnEq($filter);
            if($objectRows){
                foreach($objectRows as $objectItem){
                    $temp_order_id = $objectItem['order_id'];
                    
                    $orderId[$temp_order_id] = $temp_order_id;
                }
            }
            
            $where .= '  AND '.$table_name.'.order_id IN ('.implode(',', $orderId).')';
            
            unset($filter['sales_material_bn'], $filter['product_bn']);
        }

        //支付失败
        if(isset($filter['payment_fail']) && $filter['payment_fail'] == true){
            $api_fail = $this->app->model("api_fail");
            $payment_fail_list = $api_fail->getList('order_id', array('type'=>'payment'), 0, -1);
            $payment_order_id = array();
            if ($payment_fail_list){
                foreach($payment_fail_list as $val){
                    $payment_order_id[] = $val['order_id'];
                }
            }
            $payment_order_id = implode(',', $payment_order_id);
            $payment_order_id =  $payment_order_id ? $payment_order_id : '\'\'';
            $where .= '  AND '.$table_name.'.order_id IN ('.$payment_order_id.')';
            unset($filter['payment_fail']);
        }

        if(isset($filter['product_barcode'])){
            $itemsObj = $this->app->model("order_items");
            $rows = $itemsObj->getOrderIdByPbarcode($filter['product_barcode']);
            $orderId[] = 0;
            foreach($rows as $row){
                $orderId[] = $row['order_id'];
            }
            $where .= '  AND '.$table_name.'.order_id IN ('.implode(',', $orderId).')';
            unset($filter['product_barcode']);
        }
        //判断是否录入发票号
        if(isset($filter['is_tax_no'])){
            if($filter['is_tax_no']==1){
                $where .= '  AND '.$table_name.'.tax_no IS NOT NULL';

            }else{
                $where .= '  AND '.$table_name.'.tax_no IS NULL';
            }
            unset($filter['is_tax_no']);
        }
        //付款确认
        if (isset($filter['pay_confirm'])){
            $where .= ' AND '.$table_name.'.'.$filter['pay_confirm'];
            unset($filter['pay_confirm']);
        }
        //确认状态
        if (isset($filter['process_status_noequal'])){
            $value = '';
            foreach($filter['process_status_noequal'] as $k=>$v){
                $value .= "'".$v."',";
            }
            $len = strlen($value);
            $value_last = substr($value,0,($len-1));
            $where .= ' AND '.$table_name.'.process_status not in ( '.$value_last.")";
            unset($filter['process_status_noequal']);
        }
        if (isset($filter['member_uname'])){
            $memberObj = $this->app->model("members");
            $rows = $memberObj->getList('member_id',array('uname|head'=>$filter['member_uname']));
            $memberId[] = 0;
            foreach($rows as $row){
                $memberId[] = $row['member_id'];
            }
            $where .= '  AND '.$table_name.'.member_id IN ('.implode(',', $memberId).')';
            unset($filter['member_uname']);
        }
        if (isset($filter['pay_type'])){
            $cfgObj = app::get('ome')->model('payment_cfg');
            $rows = $cfgObj->getList('pay_bn',array('pay_type'=>$filter['pay_type']));
            $pay_bn[] = 0;
            foreach($rows as $row){
                $pay_bn[] = $row['pay_bn'];
            }
            $where .= '  AND '.$table_name.'.pay_bn IN (\''.implode('\',\'', $pay_bn).'\')';
            unset($filter['pay_type']);
        }
        //部分支付 包含部分退款 部分支付
        if(isset($filter['pay_status_part'])){
            $where .= ' AND ('.$table_name.'.pay_status = \'3\' or ('.$table_name.'.pay_status = \'4\' and '.$table_name.'.ship_status = \'0\'))';
            unset($filter['pay_status_part']);
        }
        //付款确认时，部分退款的只有未发货的才能继续支付
        if(isset($filter['pay_status_set'])){
            if($filter['pay_status_set'] == 2){
                $where .= ' AND ('.$table_name.'.pay_status in (\'0\',\'3\') or ('.$table_name.'.pay_status = \'4\' and '.$table_name.'.ship_status = \'0\'))';
            }else{
                $where .= ' AND ('.$table_name.'.pay_status in (\'0\',\'3\',\'8\') or ('.$table_name.'.pay_status = \'4\' and '.$table_name.'.ship_status = \'0\'))';
            }
            unset($filter['pay_status_set']);
        }

        return $where ." AND ".parent::_filter($filter,$tableAlias,$baseWhere);
    }
    
    #获取发货单上捆绑商品item_id
    function getPkgItemId($delivery_id = null){
        $sql = "select delivery_item_id  from sdb_ome_delivery_items_detail where item_type='pkg' and delivery_id=".$delivery_id;
        $_value = $this->db->select($sql);
        if(!empty($_value)){
            foreach( $_value as $id){
                $item_id[] = $id['delivery_item_id'];
            }
            return $item_id;
        }
        return false;


    }
    #根据product_id，获取商品类型、品牌类型
    function getTypeName($product_id =null){

        $basicMaterialExtObj    = app::get('material')->model('basic_material_ext');
        $_name                  = $basicMaterialExtObj->dump(array('bm_id'=>$product_id), 'bm_id, brand_id, cat_id');
        if(empty($_name))
        {
            return false;
        }

        #格式化品牌
        $ome_brand_obj          = app::get('ome')->model('brand');
        $temp                   = $ome_brand_obj->dump(array('brand_id'=>$_name['brand_id']), 'brand_name');
        $_name['brand_name']    = $temp['brand_name'];

        #格式化品牌、商品类型
        $goods_type_obj         = app::get('ome')->model('goods_type');
        $temp                   = $goods_type_obj->dump(array('type_id'=>$_name['cat_id']), 'name');
        $_name['type_name']     = $temp['name'];

        return $_name;
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
        $type = 'order';
        if ($params['disabled'] == 'false' && $params['is_fail'] == 'false' || $params['archive'] && $params['filter_sql']['process_status'] != 'cancel') {
            //当前订单
            $type .= '_current';
        }
        elseif ($params['disabled'] == 'false' && $params['order_confirm_filter'] == '(is_fail=\'false\' OR (is_fail=\'true\' AND status!=\'active\'))') {
            //历史订单
            $type .= '_history';
        }
        elseif ($params['is_fail'] == 'true' && $params['status'] == 'active') {
            //失败订单
            $type .= '_fail';
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
        $type = 'order';
        $type .= '_import';
        return $type;
    }

    /**
     * 发货单列表项扩展字段
     */
    function extra_cols(){
        return array(
            'column_abnormal_type_name' => array('label'=>'异常类型','width'=>'80','func_suffix'=>'abnormal_type_name'),
        );
    }

    /**
     * 买家备注扩展字段格式化
     */
    function extra_abnormal_type_name($rows){
        return kernel::single('ome_extracolumn_order_abnormaltypename')->process($rows);
    }

    /**
     * 订单导出列表扩展字段
     */
    function export_extra_cols(){
        return array(
            'column_discount_plan' => array('label'=>'优惠方案','width'=>'100','func_suffix'=>'discount_plan'),
            'column_mark_type_colour' => array('label'=>'订单备注图标颜色','width'=>'100','func_suffix'=>'mark_type_colour'),
        );
    }

    /**
     * 买家备注扩展字段格式化
     */
    function export_extra_discount_plan($rows){
        return kernel::single('ome_exportextracolumn_order_discountplan')->process($rows);
    }
    /**
     * 订单备注图标颜色扩展字段格式化
     */
    function export_extra_mark_type_colour($rows){
        return kernel::single('ome_exportextracolumn_order_marktypecolour')->process($rows);
    }

    /**
     * 获取京东子单号
     * @param $orderIds
     * @return array
     */
    public function getPackageBn($orderIds)
    {
        $oDeliveryOrder = app::get('ome')->model('delivery_order');
        $sql = "SELECT do.order_id,do.delivery_id,dp.package_bn,dp.bn,dp.status FROM sdb_ome_delivery_order do
                LEFT JOIN sdb_ome_delivery_package AS dp ON do.delivery_id = dp.delivery_id
                WHERE status <> 'cancel' AND do.order_id IN(". implode(',', $orderIds) .")";
        $deliveryPackageList = $oDeliveryOrder->db->select($sql);

        $_deliveryPackageList = array();
        if ($deliveryPackageList) {
            foreach($deliveryPackageList as $val){
                $key = $val['order_id'].'_'.$val['bn'];
                $_deliveryPackageList[$key] = $val;
            }
        }
        return $_deliveryPackageList;
    }

    public function getexportdetail_改用getlist加getExportDetailV2($fields,$filter,$offset=0,$limit=1,$has_title=false)
    {
        //获取订单号信息
        $orders = $this->db->select("SELECT order_id,order_bn FROM sdb_ome_orders WHERE order_id in(".implode(',', $filter['order_id']).")");
        $aOrder = array();
        if($orders){
            foreach($orders as $order){
                $aOrder[$order['order_id']] = $order['order_bn'];
            }
        }
        $deliveryPackageList = $this->getPackageBn($filter['order_id']);

        $row_num = 1;
        foreach($filter['order_id'] as $oid){
            $objects = $this->db->select("SELECT * FROM sdb_ome_order_objects WHERE order_id =".$oid);
            if ($objects){
                foreach ($objects as $obj){
                    if ($service = kernel::service("ome.service.order.objtype.".strtolower($obj['obj_type']))){
                        $item_data = $service->process($obj);
                        if ($item_data){
                            foreach ($item_data as $itemv){
                                $package_bn = '';
                                $orderObjRow = array();
                                $orderObjRow['*:订单号']   = mb_convert_encoding($aOrder[$obj['order_id']], 'GBK', 'UTF-8');
                                $orderObjRow['*:商品货号'] = mb_convert_encoding($itemv['bn'], 'GBK', 'UTF-8');
                                $orderObjRow['*:商品名称'] = mb_convert_encoding(str_replace("\n"," ",$itemv['name']), 'GBK', 'UTF-8');
                                $orderObjRow['*:购买单位'] = mb_convert_encoding($itemv['unit'], 'GBK', 'UTF-8');
                                $orderObjRow['*:商品规格'] = $itemv['spec_info'] ? mb_convert_encoding(str_replace("\n"," ",$itemv['spec_info']), 'GBK', 'UTF-8'):"-";
                                $orderObjRow['*:购买数量'] = $itemv['nums'];
                                $orderObjRow['*:商品原价'] = $itemv['price'];
                                $orderObjRow['*:销售价'] = $itemv['sale_price'] / $itemv['nums'];
                                $orderObjRow['*:商品优惠金额'] = $itemv['pmt_price'];
                                $orderObjRow['*:子单号']   = mb_convert_encoding("\t".$obj['oid'], 'GBK', 'UTF-8');
                                if($obj['is_wms_gift'] == 'false') $package_bn = $deliveryPackageList[$obj['order_id'].'_'.$itemv['bn']]['package_bn'];
                                $orderObjRow['*:第三方单号']   = mb_convert_encoding("\t".$package_bn, 'GBK', 'UTF-8');
                                $orderObjRow['*:平台商品ID']   = mb_convert_encoding("\t".$obj['shop_goods_id'], 'GBK', 'UTF-8');

                                $data[$row_num] = implode(',', $orderObjRow );
                                $row_num++;
                            }
                        }
                    }else {
                        $aOrder['order_items'] = $this->db->select("SELECT * FROM sdb_ome_order_items WHERE obj_id=".$obj['obj_id']." AND `delete`='false' AND order_id =".$obj['order_id']);
                        $aOrder['order_items'] = ome_order_func::add_items_colum($aOrder['order_items']);
                        $package_bn = '';
                        $orderRow = array();
                        $orderObjRow = array();
                        $k = 0;
                        if ($aOrder['order_items'])
                        foreach( $aOrder['order_items'] as $itemk => $itemv ){
                            $addon = unserialize($itemv['addon']);
                            $spec_info = null;
                            if(!empty($addon)){
                                foreach($addon as $val){
                                    foreach ($val as $v){
                                        $spec_info[] = $v['value'];
                                    }
                                }
                            }
                            $_typeName = $this->getTypeName($itemv['product_id']);
                            $orderObjRow = array();
                            $orderObjRow['*:订单号']   = mb_convert_encoding($aOrder[$obj['order_id']], 'GBK', 'UTF-8');
                            $orderObjRow['*:商品货号'] = mb_convert_encoding("\t".$itemv['bn'], 'GBK', 'UTF-8');
                            $orderObjRow['*:商品名称'] = mb_convert_encoding("\t".str_replace("\n"," ",$itemv['name']), 'GBK', 'UTF-8');
                            $orderObjRow['*:购买单位'] = mb_convert_encoding($itemv['unit'], 'GBK', 'UTF-8');
                            $orderObjRow['*:商品规格'] = $spec_info ? mb_convert_encoding(implode('||', $spec_info), 'GBK', 'UTF-8'):'-';
                            $orderObjRow['*:购买数量'] = $itemv['nums'];
                            $orderObjRow['*:商品原价'] = $itemv['price'];
                            $orderObjRow['*:销售价'] = $itemv['sale_price'] / $itemv['nums'];
                            $orderObjRow['*:商品优惠金额'] = $itemv['pmt_price'];
                            $orderObjRow['*:商品类型'] = mb_convert_encoding($_typeName['type_name'], 'GBK', 'UTF-8');
                            $orderObjRow['*:商品品牌'] = mb_convert_encoding($_typeName['brand_name'], 'GBK', 'UTF-8');
                            $orderObjRow['*:子单号']   = mb_convert_encoding("\t".$obj['oid'], 'GBK', 'UTF-8');
                            if($obj['is_wms_gift'] == 'false') $package_bn = $deliveryPackageList[$obj['order_id'].'_'.$itemv['bn']]['package_bn'];
                            $orderObjRow['*:第三方单号']   = mb_convert_encoding("\t".$package_bn, 'GBK', 'UTF-8');
                            $orderObjRow['*:平台商品ID']   = mb_convert_encoding("\t".$itemv['shop_goods_id'], 'GBK', 'UTF-8');
                            $orderObjRow['*:平台SkuID']   = mb_convert_encoding("\t".$itemv['shop_product_id'], 'GBK', 'UTF-8');


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
                '*:子单号',
                '*:第三方单号',
                '*:平台商品ID',
                '*:平台SkuID',

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
     * 获取分派信息
     * @param
     * @return
     * @access  public
     * @author sunjing@shopex.cn
     */
    function updateDispatchinfo($order_id)
    {
        $combineObj = new omeauto_auto_combine();

        $dispatchObj = app::get('omeauto')->model('autodispatch');
        $params = array();
        $params[] = array(
            'orders' => array (
                0 => $order_id,
            ),
        );
        $result = $combineObj->dispatch($params);

        if ($result['did'] && $result['did']>0) {
            $opData = $dispatchObj->dump($result['did'],'group_id,op_id');
            if($opData) {
                $this->update($opData,array('order_id'=>$order_id));
                $usersObj        = app::get('desktop')->model('users');
                $groupsObj       = app::get('ome')->model('groups');
                $confirm_opname  = $usersObj->dump($opData['op_id'], 'name');
                $confirm_opgroup = $groupsObj->dump($opData['group_id'], 'name');
                $logMsg = '<span style="display:none">' . var_export($result, 1) . '</span>'."订单重新分派给确认组:" . $confirm_opgroup['name'] . ",确认人:" . ($confirm_opname ? $confirm_opname['name'] : '-');
                $omeLogMdl = app::get('ome')->model('operation_log');
                $omeLogMdl->write_log('order_dispatch@ome', $order_id, $logMsg);
            }
            
            return true;
        }
        
        return false;
    }

    /**
     * 计算订单优惠均摊
     */
    function getPmtorder($order_id){
        $order_detail = $this->dump($order_id,"order_id,pmt_order");
        $order_objects = $this->db->select("SELECT o.* FROM sdb_ome_order_items i LEFT JOIN sdb_ome_order_objects AS o ON i.obj_id=o.obj_id WHERE i.order_id=".$order_id." AND i.`delete`='false' GROUP BY order_id,obj_id");

        $all_discount = $order_detail['pmt_order'];
        $tmp_goods = array();
        $all_goods_sale_price = 0.00;//所有商品销售价格:去商品object上优惠后的所有商品销售价合计
        foreach ($order_objects as $key => $object){

            $tmp_goods[$key]['product'][$object['bn']] = array(
                'product_pmt_price' => $object['pmt_price'],
                'sale_price' => $object['sale_price'],
                'obj_id' => $object['obj_id'],
            );

            $tmp_goods[$key]['obj_id'] = $object['obj_id'];
            $tmp_goods[$key]['bn'] = $object['bn'];
            $tmp_goods[$key]['sale_price'] = $object['sale_price'];
            $tmp_goods[$key]['goods_pmt_price'] = 0.00;

            $tmp_goods[$key]['price_worth'] = bcsub($object['sale_price'],$tmp_goods[$key]['goods_pmt_price'],2);

            $all_goods_sale_price = bcadd($all_goods_sale_price,$tmp_goods[$key]['price_worth'],2);
        }
        $loop = 1;
        $goods_count = count($tmp_goods);
        // PHP8 下避免 BCMath ValueError：比例改用 bc 计算，并保护分母为 0 的场景。
        $has_apportion_pmt = '0.00';
        foreach($tmp_goods as $key => $goods){
            if($tmp_goods[$key]['price_worth'] > 0){
                if($goods_count == $loop){
                    $tmp_goods[$key]['apportion_pmt'] = bcsub($all_discount,$has_apportion_pmt,2);
                }else{
                    if (bccomp((string)$all_goods_sale_price, '0', 6) <= 0) {
                        $tmp_goods[$key]['apportion_pmt'] = '0.00';
                    } else {
                        $ratio = bcdiv((string)$all_discount, (string)$all_goods_sale_price, 8);
                        $tmp_goods[$key]['apportion_pmt'] = bcmul($ratio, (string)$tmp_goods[$key]['price_worth'], 2);
                    }
                    $has_apportion_pmt = bcadd($has_apportion_pmt,$tmp_goods[$key]['apportion_pmt'],2);
                }
            }else{
                $tmp_goods[$key]['apportion_pmt'] = 0.00;
            }

            $tmp_products = $goods['product'];

            $sale_product[$goods['obj_id']][$tmp_goods[$key]['bn']]['apportion_pmt'] = $tmp_goods[$key]['apportion_pmt'];
            $sale_product[$goods['obj_id']][$tmp_goods[$key]['bn']]['sales_amount'] = bcsub($tmp_goods[$key]['sale_price'],$tmp_goods[$key]['apportion_pmt'],2);

            $loop++;
        }

        return $sale_product;
    }
    
    //导入订单过滤格式化地区名称
    private function import_area_char_filter($str){
        return trim(str_replace(array("\t","\r","\n"),array("","",""),$str));
    }

    /**
     * modifier_ship_name
     * @param mixed $ship_name ship_name
     * @param mixed $list list
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function modifier_ship_name($ship_name,$list,$row)
    {
        if ($this->is_export_data) {
            if ('false' != app::get('ome')->getConf('ome.sensitive.exportdata.encrypt')) {
                return kernel::single('ome_view_helper2')->modifier_ciphertext($ship_name,'order','ship_name');
            }
            return kernel::single('ome_security_export')->decryptField([
                        'origin' => $ship_name,
                        'field_type' => 'ship_name',
                        'shop_id' => $row['shop_id'],
                        'origin_bn' => $row['order_bn'],
                        'type' => 'order'
                    ]);
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

    /**
     * modifier_ship_tel
     * @param mixed $tel tel
     * @param mixed $list list
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function modifier_ship_tel($tel,$list,$row)
    {
        if ($this->is_export_data) {
            if ('false' != app::get('ome')->getConf('ome.sensitive.exportdata.encrypt')) {
                return kernel::single('ome_view_helper2')->modifier_ciphertext($tel,'order','ship_tel');
            }
            return kernel::single('ome_security_export')->decryptField([
                        'origin' => $tel,
                        'field_type' => 'ship_tel',
                        'shop_id' => $row['shop_id'],
                        'origin_bn' => $row['order_bn'],
                        'type' => 'order'
                    ]);
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

    /**
     * modifier_ship_mobile
     * @param mixed $mobile mobile
     * @param mixed $list list
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function modifier_ship_mobile($mobile,$list,$row)
    {
        if ($this->is_export_data) {
            if ('false' != app::get('ome')->getConf('ome.sensitive.exportdata.encrypt')) {
                return kernel::single('ome_view_helper2')->modifier_ciphertext($mobile,'order','ship_mobile');
            }
            return kernel::single('ome_security_export')->decryptField([
                        'origin' => $mobile,
                        'field_type' => 'ship_mobile',
                        'shop_id' => $row['shop_id'],
                        'origin_bn' => $row['order_bn'],
                        'type' => 'order'
                    ]);
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

    /**
     * modifier_ship_addr
     * @param mixed $ship_addr ship_addr
     * @param mixed $list list
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function modifier_ship_addr($ship_addr,$list,$row)
    {
        if ($this->is_export_data) {
            if ('false' != app::get('ome')->getConf('ome.sensitive.exportdata.encrypt')) {
                return kernel::single('ome_view_helper2')->modifier_ciphertext($ship_addr,'order','ship_addr');
            }
            return kernel::single('ome_security_export')->decryptField([
                        'origin' => $ship_addr,
                        'field_type' => 'ship_addr',
                        'shop_id' => $row['shop_id'],
                        'origin_bn' => $row['order_bn'],
                        'type' => 'order'
                    ]);
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
    
    /**
     * 增加旺旺联系方式
     * 
     * @param integre $row 用户ID
     * @return String
     */
    function modifier_member_id($member_id,$list,$row) {
        static $get_from_db,$order_list;

        if ($get_from_db === true) return  $order_list[$row['order_id']]['uname'];

        $member_list = array ();
        foreach ($list as $value) {
            $order_list[$value['order_id']]['shop_type'] = $value['_0_shop_type'];
            $order_list[$value['order_id']]['member_id'] = $value['member_id'];

            $member_list[$value['member_id']] = array ();
        }

        if ($mid = array_keys($member_list)) {
            $m1Mdl = app::get('ome')->model('members');
            $shop_list = array ();
            foreach ($m1Mdl->getList('uname,member_id,shop_id,buyer_open_uid',array('member_id'=>$mid)) as $value) {
                $member_list[$value['member_id']]['uname']     = $value['uname'];
                $member_list[$value['member_id']]['buyer_open_uid']     = $value['buyer_open_uid'];
                $member_list[$value['member_id']]['shop_type'] = &$shop_list[$value['shop_id']];
            }

            if ($shop_id = array_keys($shop_list)) {
                $shopMdl = app::get('ome')->model('shop');
                foreach ($shopMdl->getList('shop_id,shop_type', array ('shop_id' => $shop_id)) as $value) {
                    $shop_list[$value['shop_id']] = $value['shop_type'];
                }
            }
        }

        foreach ($order_list as $order_id => $value) {
            $value['uname']          = $member_list[$value['member_id']]['uname'];

            if ($this->is_export_data) {
                if ('false' != app::get('ome')->getConf('ome.sensitive.exportdata.encrypt')) {
                    $value['uname'] = kernel::single('ome_view_helper2')->modifier_ciphertext($value['uname'],'order','uname');
                }
                $order_list[$order_id] = $value; continue;
            }

            switch ($value['shop_type']) {
                case 'taobao':
                    if ($value['uname']) {
                        $value['uname'] = kernel::single('ome_order_func')->getWangWangHtml(['nick'=>$value['uname'], 'encryptuid'=>$member_list[$value['member_id']]['buyer_open_uid']]);
                    }
                    break;
                default :
                    // $is_encrypt = kernel::single('ome_security_hash')->check_encrypt($value['uname']);

                    if ($value['uname']) {
                        $encrypt = kernel::single('ome_view_helper2')->modifier_ciphertext($value['uname'],'order','uname');

                        $value['uname'] = <<<HTML
<a class="data-hide" href="javascript:void(0);" onclick="Ex_Loader('security',function(){new Security({url:'index.php?app=ome&ctl=admin_member&act=showSensitiveData&p[0]={$order_id}',clickElement:\$(event.target)}).desHtml(\$(event.target).getNext()); });"></a><span><span sensitive-field="uname">{$encrypt}</span></span>
HTML;
                    }

                    break;
            }

            $order_list[$order_id] = $value;
        }

        $get_from_db = true;

        return $order_list[$row['order_id']]['uname'];
    }
    
    /**
     * insert
     * @param mixed $data 数据
     * @return mixed 返回值
     */
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

    public function finder_getList($cols='*', $filter=array(), $offset=0, $limit=-1, $orderType=null){

        $data = parent::finder_getList($cols, $filter, $offset, $limit, $orderType);

        foreach ((array) $data as $key => $value) {
            foreach ($this->__encrypt_cols as $field => $type) {
                if (isset($value[$field])) {
                    $data[$key][$field] = (string) kernel::single('ome_security_factory')->decryptPublic($value[$field],$type);
                }
            }
        }

        return $data;
    }

    protected function _debcrypt(&$data) {
        foreach ((array) $data as $key => $value) {
            foreach ($this->__encrypt_cols as $field => $type) {
                if (isset($value[$field])) {
                    $data[$key][$field] = (string) kernel::single('ome_security_factory')->decryptPublic($value[$field],$type);
                }
            }
        }
    }
    
    /**
     * 获取Area
     * @param mixed $where where
     * @return mixed 返回结果
     */
    public function getArea($where) {
        $sql = 'SELECT order_id from sdb_ome_orders WHERE '.$where;
        return array_column($this->db->select($sql),'order_id');
    }
    
    /**
     * 过滤金额中的逗号和空格
     * 
     * @param string $str
     * @return string
     */
    function replace_import_price($str)
    {
        return trim(str_replace(array(",", " "), array("", ""), $str));
    }
    /**
     * 根据查询条件获取导出数据
     * @Author: xueding
     * @Vsersion: 2022/5/24 下午2:16
     * @param $fields
     * @param $filter
     * @param $has_detail
     * @param $curr_sheet
     * @param $start
     * @param $end
     * @param $op_id
     * @return bool
     */
    public function getExportDataByCustom_改用getlist加getExportDetailV2($fields, $filter, $has_detail, $curr_sheet, $start, $end, $op_id)
    {
        $params = [
            'fields'     => $fields,
            'filter'     => $filter,
            'has_detail' => $has_detail,
            'curr_sheet' => $curr_sheet,
            'op_id'      => $op_id,
        ];
        $orderListData = kernel::single('ome_func')->exportDataMain(__CLASS__,$params);
        if (!$orderListData) {
            return false;
        }
        //根据选择的字段定义导出的第一行标题
        if ($curr_sheet == 1) {
            if($has_detail == 1) {
                $data['content']['main'][] = $this->getCustomExportTitle($orderListData['title']);
            } else {
                $data['content']['main'][] = mb_convert_encoding(implode(',', array_keys($orderListData['title'])), 'GBK', 'UTF-8');
            }
        }
    
        $orderItemsMdl = app::get('ome')->model('order_items');
        $orderObjectsMdl = app::get('ome')->model('order_objects');
        
        $order_items_columns      = array_values($this->orderItemsExportTitle());
        $items_fields = implode(',', $order_items_columns);
    
        $deliveryPackageList = $this->getPackageBn($filter['order_id']);
    
        $main_columns = array_values($orderListData['title']);
        $orderList = $orderListData['content'];
        foreach ($orderList as $order_data) {
            $order_data['order_bn'] = $order_data['order_bn']."\t";
            if($has_detail != 1) {
                $exptmp_data = [];
                foreach ($main_columns as $key => $col) {
                    if (isset($order_data[$col])) {
                        $order_data[$col] = mb_convert_encoding($order_data[$col], 'GBK', 'UTF-8');
                        $exptmp_data[]      = $order_data[$col];
                    } else {
                        $exptmp_data[] = '';
                    }
                }
                $data['content']['main'][] = implode(',', $exptmp_data);
                continue;
            }
            $order_items  = $orderItemsMdl->getList('*', ['order_id' => $order_data['order_id'],'delete'=>'false']);
            $order_objects  = $orderObjectsMdl->getList('*', ['order_id' => $order_data['order_id']]);
            $order_items  = ome_order_func::add_items_colum($order_items);
            $order_objects = array_column($order_objects,null,'obj_id');
            if ($order_items) {
                foreach ($order_items as $itemk => $itemv) {
                    $addon     = unserialize($itemv['addon']);
                    $spec_info = null;
                    if (!empty($addon)) {
                        foreach ($addon as $val) {
                            foreach ($val as $v) {
                                $spec_info[] = $v['value'];
                            }
                        }
                    }
                    //item 数据获取
                    $_typeName                     = $this->getTypeName($itemv['product_id']);
                    $orderItemObjRow               = array();
                    $orderItemObjRow['bn']         = $itemv['bn'];
                    $orderItemObjRow['name']       = str_replace("\n", " ", $itemv['name']);
                    $orderItemObjRow['sm_bn']      = $order_objects[$itemv['obj_id']]['bn'];
                    $orderItemObjRow['sm_name']    = str_replace("\n", " ", $order_objects[$itemv['obj_id']]['name']);
                    $orderItemObjRow['unit']       = $itemv['unit'];
                    $orderItemObjRow['spec_info']  = $spec_info ? implode('||', $spec_info) : '-';
                    $orderItemObjRow['nums']       = $itemv['nums'];
                    $orderItemObjRow['price']      = $itemv['price'];
                    $orderItemObjRow['sale_price'] = $itemv['nums'] ? $itemv['sale_price'] / $itemv['nums'] : '';
                    $orderItemObjRow['pmt_price']  = $itemv['pmt_price'];
                    $orderItemObjRow['type_name']  = $_typeName['type_name'];
                    $orderItemObjRow['brand_name'] = $_typeName['brand_name'];
                    $orderItemObjRow['divide_order_fee'] = ($itemv['divide_order_fee'] ? $itemv['divide_order_fee'] : 0);
                    $orderItemObjRow['oid']   = mb_convert_encoding("\t".$order_objects[$itemv['obj_id']]['oid'], 'GBK', 'UTF-8');
                    if($order_objects[$itemv['obj_id']]['is_wms_gift'] == 'false') $package_bn = $deliveryPackageList[$itemv['order_id'].'_'.$itemv['bn']]['package_bn'];
                    $orderItemObjRow['package_bn']   = mb_convert_encoding("\t".$package_bn, 'GBK', 'UTF-8');
                    $orderItemObjRow['shop_goods_id']   = mb_convert_encoding("\t".$itemv['shop_goods_id'], 'GBK', 'UTF-8');
                    $orderItemObjRow['shop_product_id']   = mb_convert_encoding("\t".$itemv['shop_product_id'], 'GBK', 'UTF-8');

                    $orderItemObjRow = array_map(function($_v) {
                        $_v = str_replace('&nbsp;', '', $_v);
                        $_v = str_replace(array("\r\n", "\r", "\n"), '', $_v);
                        $_v = str_replace(',', '，', $_v);
                        $_v = strip_tags(html_entity_decode($_v, ENT_COMPAT | ENT_QUOTES, 'UTF-8'));
                        $_v = trim($_v);
                        return $_v;
                    }, $orderItemObjRow);
    
                    $orderdataRow = array_merge($order_data, $orderItemObjRow);
                    $all_fields   = implode(',', $main_columns) . ',' . $items_fields;
                    
                    $exptmp_data = [];
                    foreach (explode(',', $all_fields) as $key => $col) {
                        if (isset($orderdataRow[$col])) {
                            $orderdataRow[$col] = mb_convert_encoding($orderdataRow[$col], 'GBK', 'UTF-8');
                            $exptmp_data[]      = $orderdataRow[$col];
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
    
    /**
     * 获取CustomExportTitle
     * @param mixed $main_title main_title
     * @return mixed 返回结果
     */
    public function getCustomExportTitle($main_title)
    {
        $main_title = array_keys($main_title);
        $order_items_title = array_keys($this->orderItemsExportTitle());
        $title             = array_merge($main_title, $order_items_title);
        return mb_convert_encoding(implode(',', $title), 'GBK', 'UTF-8');    }
    
    
    /**
     * orderItemsExportTitle
     * @return mixed 返回值
     */
    public function orderItemsExportTitle()
    {
        $items_title = array(
            '详情基础物料编码'   => 'bn',
            '详情基础物料名称'   => 'name',
            '关联销售物料编码'   => 'sm_bn',
            '关联销售物料名称'   => 'sm_name',
            '详情购买单位'   => 'unit',
            '详情基础物料规格'   => 'spec_info',
            '详情购买数量'   => 'nums',
            '详情商品原价'   => 'price',
            '详情销售价'    => 'sale_price',
            '详情商品优惠金额' => 'pmt_price',
            '详情商品实际支付金额' => 'divide_order_fee',
            '详情商品类型'   => 'type_name',
            '详情商品品牌'   => 'brand_name',
            '详情子单号'   => 'oid',
            '详情第三方单号'   => 'package_bn',
            '平台商品ID'   => 'shop_goods_id',
            '平台SkuID'   => 'shop_product_id',
        );
        return $items_title;
    }
    
    function modifier_pmt_goods($row){
        if($row){
            if (!kernel::single('desktop_user')->has_permission('sale_price')) {
                return '-';
            }else{
                return '￥' . $row;
            }
        }
    }
    
    function modifier_pmt_order($row){
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
    
    function modifier_payed($row){
        if($row){
            if (!kernel::single('desktop_user')->has_permission('sale_price')) {
                return '-';
            }else{
                return '￥' . $row;
            }
        }
    }
    
    function modifier_cost_item($row){
        if($row){
            if (!kernel::single('desktop_user')->has_permission('sale_price')) {
                return '-';
            }else{
                return '￥' . $row;
            }
        }
    }

    /**
     * 导出明细
     *
     * @param array $list
     * @param array $colArray
     * @return array
     **/
    public function getExportDetailV2($list, $colArray)
    {
        $order_id_arr = array_unique(array_column($list, 'order_id'));
        if (!$order_id_arr) {
            return [$list, $colArray];
        }

        foreach (self::EXPORT_ITEM_TITLE as $_v) {
            $colArray[$_v['col']] = ['label' => $_v['label']];
        }

        $list = array_column($list, null, 'order_id');

        $orderItemsMdl = app::get('ome')->model('order_items');
        $orderObjMdl = app::get('ome')->model('order_objects');

        $objects = $items = [];
        foreach ($orderObjMdl->getList('*', ['order_id|in' => $order_id_arr]) as $ov) {
            // 直播间ID
            $ov['addon'] = @json_decode($ov['addon'], 1);
            if ($ov['addon'] && isset($ov['addon']['room_id'])) {
                $ov['room_id'] = $ov['addon']['room_id'];
            }

            $objects[$ov['order_id']][$ov['obj_id']] = $ov;
        }

        $listV2 = $tmp_order_id = [];
        $items = $orderItemsMdl->getList('*', ['order_id|in' => $order_id_arr, 'delete'=>'false']);

        // 获取基础物料品牌名称
        $bmIds = array_column($items, 'product_id');
        $brandList = kernel::single('material_extracolumn_basicmaterial_brand')->associatedData($bmIds);

        foreach ($items as $iv) {
            // if (in_array($iv['order_id'], $tmp_order_id)) {
            //     $main = array_fill_keys(array_keys((array)$list[$iv['order_id']]), '_ISNULL_');
            // } else {
            //     $main = (array)$list[$iv['order_id']];
            //     $tmp_order_id[$iv['order_id']] = $iv['order_id'];
            // }
            $main = (array)$list[$iv['order_id']];
            $addon = unserialize($iv['addon']);
            $spec_info = null;
            if(!empty($addon)){
                foreach($addon as $val){
                    foreach ($val as $v){
                        $spec_info[] = $v['value'];
                    }
                }
            }
            $l = array_merge($main, [
                'e_sm_bn'               =>  $objects[$iv['order_id']][$iv['obj_id']]['bn'],
                'e_sm_name'             =>  $objects[$iv['order_id']][$iv['obj_id']]['name'],
                'e_sm_type'             =>  $objects[$iv['order_id']][$iv['obj_id']]['obj_type'],
                'e_item_bn'             =>  $iv['bn'],
                'e_item_product_name'   =>  $iv['name'],
                'e_brand_name'          =>  $brandList[$iv['product_id']],
                'e_spec_info'           =>  $spec_info?implode('||', $spec_info):'-',
                'e_unit'                =>  $iv['unit'],
                'e_price'               =>  $iv['price'],
                'e_sale_price'          =>  $iv['sale_price'] / $iv['nums'],
                'e_pmt_price'           =>  $iv['pmt_price'],
                'e_divide_order_fee'    =>  $iv['divide_order_fee']? $iv['divide_order_fee'] : 0,
                'e_part_mjz_discount'   =>  $iv['part_mjz_discount'],
                'e_nums'                =>  $iv['nums'],
                'e_sendnum'             =>  $iv['sendnum'],
                'e_return_num'          =>  $iv['return_num'],
                'e_split_num'           =>  $iv['split_num'],
                'e_estimate_con_time'   =>  $objects[$iv['order_id']][$iv['obj_id']]['estimate_con_time'] ? date('Y-m-d H:i:s', $objects[$iv['order_id']][$iv['obj_id']]['estimate_con_time']) : '-',
                'e_presale_status'      =>  $objects[$iv['order_id']][$iv['obj_id']]['is_oversold'] == 1 ? '是' : '否',
                'e_store_code'          =>  $objects[$iv['order_id']][$iv['obj_id']]['store_code'],
                'e_oid'                 =>  mb_convert_encoding("\t".$objects[$iv['order_id']][$iv['obj_id']]['oid'], 'GBK', 'UTF-8'),
                'e_main_oid'            =>  str_replace(',', '', $objects[$iv['order_id']][$iv['obj_id']]['main_oid']),
                'e_shop_goods_id'       =>  mb_convert_encoding("\t".$objects[$iv['order_id']][$iv['obj_id']]['shop_goods_id'], 'GBK', 'UTF-8'),
                'e_shop_product_id'     =>  mb_convert_encoding("\t".$objects[$iv['order_id']][$iv['obj_id']]['shop_product_id'], 'GBK', 'UTF-8'),
                'e_author_id'           =>  $objects[$iv['order_id']][$iv['obj_id']]['author_id'],
                'e_author_name'         =>  $objects[$iv['order_id']][$iv['obj_id']]['author_name'],
                'e_room_id'             =>  $objects[$iv['order_id']][$iv['obj_id']]['room_id'],
            ]);

            // 兼容导出数据，过滤掉特殊符号
            // $l = array_map(function($v) {
            //     $v = str_replace([',',"\r\n", "\r", "\n"],['，',' ',' ',' '],$v );
            // }, $l);

            $listV2[] = $l;
        }
        return [$listV2, $colArray];
    }

}
