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

class ome_mdl_return_product extends dbeav_model
{
    var $defaultOrder = array('add_time DESC,return_id DESC');
    
    //是否支持自定义导出字段
    var $has_export_cnf = true;
    
    //这是导出文件的名称
    var $export_name = '售后申请单';
    
    public function _filter($filter,$tableAlias=null,$baseWhere=null)
    {
        $where = '';
        
        //多订单号查询
        $orderBns = array();
        if($filter['order_bn'] && is_string($filter['order_bn']) && strpos($filter['order_bn'], "\n") !== false){
            $orderBns = array_unique(array_map('trim', array_filter(explode("\n", $filter['order_bn']))));
            
            unset($filter['order_bn']);
        }elseif($filter['order_bn']){
            $orderBns = array($filter['order_bn']);
            
            unset($filter['order_bn']);
        }
        
        if($orderBns){
            $orderIds = array();
            
            //订单列表
            $orderObj = app::get('ome')->model('orders');
            $tempList = $orderObj->getList('order_id', array('order_bn'=>$orderBns), 0, 500);
            foreach((array)$tempList as $row){
                $orderIds[] = $row['order_id'];
            }
            
            //[兼容]归档订单
            if(empty($orderIds)){
                $ordersObj = app::get('archive')->model('orders');
                $tempList = $ordersObj->getList('order_id', array('order_bn'=>$orderBns), 0, 500);
                foreach((array)$tempList as $row) {
                    $orderIds[] = $row['order_id'];
                }
            }
            
            if(empty($orderIds)){
                $orderIds[] = 0;
            }
            
            $where .= ' AND order_id IN ('.implode(',', $orderIds).')';
        
            unset($orderIds, $tempList);
        }
        
        if(isset($filter['ship_name'])){
            $deliveryObj = $this->app->model("delivery");
            $rows = $deliveryObj->getList('delivery_id',array('ship_name'=>$filter['ship_name']));
            $deliveryId[] = 0;
            foreach($rows as $row){
                $deliveryId[] = $row['delivery_id'];
            }
            $where .= '  AND delivery_id IN ('.implode(',', $deliveryId).')';
            unset($filter['ship_name']);
        }
        if (isset($filter['member_uname'])){
            $memberObj = $this->app->model("members");
            $rows = $memberObj->getList('member_id',array('uname|has'=>$filter['member_uname']));
            $memberId[] = 0;
            foreach($rows as $row){
                $memberId[] = $row['member_id'];
            }
            $where .= '  AND member_id IN ('.implode(',', $memberId).')';
            unset($filter['member_uname']);
        }
        if (isset($filter['product_bn'])){
            $returnId = array();
            $returnId[] = 0;

            //多基础物料查询
            if($filter['product_bn'] && is_string($filter['product_bn']) && strpos($filter['product_bn'], "\n") !== false){
                $filter['product_bn'] = array_unique(array_map('trim', array_filter(explode("\n", $filter['product_bn']))));
            }

            //按基础物料查询相关售后单
            $rows = $this->getReturnIdByFilterbnEq($filter);
            if($rows){
                foreach($rows as $row){
                    $temp_return_id = $row['return_id'];
                    $returnId[$temp_return_id] = $temp_return_id;
                }
            }

            $where .= '  AND return_id IN ("'.implode('","', $returnId).'")';
            unset($filter['product_bn']);
        }
        return parent::_filter($filter,$tableAlias,$baseWhere).$where;
    }

    /**
     * 根据基础物料编码获取售后单ID（支持大数据量分批查询）
     * @param array $filter 筛选条件，包含 product_bn
     * @return array 返回 return_id 列表
     */
    public function getReturnIdByFilterbnEq($filter)
    {
        $product_bn = $filter['product_bn'];

        $where = 1;
        if (is_array($product_bn)) {
            $where = 'in (\'' . implode('\',\'', $product_bn) . '\')';
        } else {
            $where = '= \'' . $product_bn . '\'';
        }
        unset($filter['product_bn']);
        $return_filter = $this->_filter($filter);
        $return_filter = str_replace('`sdb_ome_return_product`', 'r', $return_filter);
        // 使用正则替换，排除已经带 r. 前缀的字段
        $return_filter = preg_replace('/(?<!r\.)return_id/', 'r.return_id', $return_filter);
        $return_filter = preg_replace('/(?<!r\.)delivery_mode/', 'r.delivery_mode', $return_filter);
        $sql = 'SELECT count(1) as _c FROM sdb_ome_return_product_items as i LEFT JOIN sdb_ome_return_product as r ON i.return_id=r.return_id WHERE i.bn ' . $where . ' AND ' . $return_filter;
        $count = $this->db->selectrow($sql);
        if ($count['_c'] >= 10000) {
            $offset = 0;
            $limit  = 9000;
            $list   = array();
            $sql    = 'SELECT i.return_id FROM sdb_ome_return_product_items as i LEFT JOIN sdb_ome_return_product as r ON i.return_id=r.return_id WHERE i.bn ' . $where . ' AND ' . $return_filter;
            $total  = floor($count['_c'] / $limit);
            for ($i = $total; $i >= 0; $i--) {
                $rows = $this->db->selectlimit($sql, $limit, $i * $limit);
                if ($rows) {
                    $list = array_merge_recursive($list, $rows);
                }
            }
            return $list;
        }

        $sql  = 'SELECT i.return_id FROM sdb_ome_return_product_items as i LEFT JOIN sdb_ome_return_product as r ON i.return_id=r.return_id WHERE i.bn ' . $where . ' AND ' . $return_filter;
        $rows = $this->db->select($sql);

        return $rows;
    }

    /* create_return_product 添加售后申请
     * @param sdf $sdf
     * @return sdf
     */
    function create_return_product(&$sdf){
        $shop_info = app::get('ome')->model('shop')->getShopInfo($sdf['shop_id']);
        // 经销店铺的单据，delivery_mode冗余到售后申请表
        if ($shop_info['delivery_mode'] == 'jingxiao') {
            $sdf['delivery_mode'] = $sdf['delivery_mode'];
        }
        $this->save($sdf);
    }

    /*
     * 申请售后服务详情
     */
    function product_detail($return_id)
    {
        $basicMaterialExtObj = app::get('material')->model('basic_material_ext');
        $orderItemMdl = app::get('ome')->model('order_items');
        
        $oProduct_items = $this->app->model('return_process_items');
        $oPro_items = $this->app->model('return_product_items');
        $oBranch = $this->app->model('branch');
        $oMembers = $this->app->model('members');
        $product_detail = $this->dump($return_id);//售后服务详情
        $product_detail['status_value']=$this->get_return_status($product_detail['status']);
        
        /*售后服务商品明细*/
        $product_detail['items']=$oPro_items->getList('*',array('return_id'=>$return_id));
        
        //obj_type
        $objTypeList = $orderItemMdl->_obj_alias;
        
        //获取订单obj层信息
        $orderItemList = array();
        $orderItemIds = array_column($product_detail['items'], 'order_item_id');
        if($orderItemIds){
            $orderLib = kernel::single('ome_order');
            $orderItemList = $orderLib->getOrderItemByItemIds($orderItemIds);
        }
        
        /*收货人信息*/
        foreach($product_detail['items'] as $k=>$v)
        {
            $obj_type = $v['obj_type'];
            $order_item_id = $v['order_item_id'];
            
            $spec_info    = $basicMaterialExtObj->dump(array('bm_id'=>$v['product_id']), 'specifications');

            //基础物料规格
            $product_detail['items'][$k]['spec_info']    = $spec_info['specifications'];

            $product_detail['items'][$k]['branch_name']=$oBranch->Get_name($v['branch_id']);
            $product_detail['items'][$k]['branch_id']=$v['branch_id'];
            $refund = $this->Get_delivery($v['branch_id'],$v['bn'],$product_detail['order_id']);

            $product_detail['items'][$k]['effective']=$refund['refund'];
            
            //销售物料类型名称
            $product_detail['items'][$k]['obj_type_name'] = ($obj_type ? $objTypeList[$obj_type] : '');
            
            //关联的订单object层信息
            if(isset($orderItemList[$order_item_id])){
                $orderItemInfo = $orderItemList[$order_item_id];
                
                //销售物料编码
                $product_detail['items'][$k]['sales_material_bn'] = $orderItemInfo['sales_material_bn'];
                
                //福袋组合编码
                $product_detail['items'][$k]['combine_bn'] = $orderItemInfo['combine_bn'];
            }
        }
        $oProduct_delivery = $this->app->model('delivery');
        $product_detail['delivery']=$oProduct_delivery->dump($product_detail['delivery_id'],'ship_area,ship_name,ship_addr,ship_zip,ship_tel,ship_email,ship_mobile');//收货人信息

        //仓库信息
        $process_data=$product_detail['process_data'] ? unserialize($product_detail['process_data']):[];
        $product_detail['process_data']=$process_data;
       
        //已处理的申请商品
        $check_data=$oProduct_items->getList('bn,order_id,name,branch_id,product_id,item_id,is_problem,problem_type,problem_belong,store_type,memo,status,need_money,other',array('return_id'=>$product_detail['return_id'],'is_check'=>'true'));
        $Oorder_items = $this->app->model('order_items');
        $Oproblem = $this->app->model('return_product_problem');

        foreach($check_data as $k=>$v)
        {
            $spec_info    = $basicMaterialExtObj->dump(array('bm_id'=>$v['product_id']), 'specifications');

            //基础物料规格
            $check_data[$k]['spec_info']    = $spec_info['specifications'];

            $get_filter = array('order_id'=>$v['order_id'],'bn'=>$v['bn']);
            $Oorder_detail = $Oorder_items->dump($get_filter,'price');
            $check_data[$k]['price']=$Oorder_detail['price'];
            $problem_belong='';
            $problem_type='';

            $check_data[$k]['problem_belong']=$problem_belong;

            $problem_type = $v['problem_type'];
            $check_data[$k]['problem_type']=$problem_type;
            $refund = $this->Get_check_delivery($v['bn'],$product_detail['order_id']);
            $check_data[$k]['effective'] = $refund['refund'];
            $check_data[$k]['StoreType']=$Oproblem->get_store_type($v['store_type']);
        }

        $product_detail['check_data']=$check_data;

        /*日志列表*/
        $oOperation_log = $this->app->model('operation_log');
        $log_filter = array('obj_type'=>'return_product@ome','obj_id'=>$return_id,'obj_name'=>$product_detail['return_bn']);
        $product_detail['log']=$oOperation_log->read_log($log_filter,0,20,'log_id');
        $product_detail['member'] = $oMembers->dump($product_detail['member_id'],'uname,tel,zip,email,mobile');/*会员信息*/
        return $product_detail;
    }

    /*
     * 保存退货和成功状态
     * deal 状态1退货 2 拒绝 3 换货
     *      1.退货 生成一张退货单
     *      2.换货：生成一张退货单。和一张未付款的新订单
     *      3.拒绝是原样打回，生成一张发货单
     */
    function saveinfo($data,$api=FALSE){

        $oProduct_items = $this->app->model('return_process_items');
        $oOperation_log = $this->app->model('operation_log');//写日志
        $memo = '';
        $oProduct_detail = $this->dump($data['return_id'],'delivery_id,order_id');
        $bn = $data['bn'];
        $return_id=$data['return_id'];
        $reship_num=0;//退货单数量
        $order_num=0;//退货单数量
        $delivery_num=0;//发货单数量
        foreach($bn as $k => $v){

            //售后服务明细表数据
            $tmpData['item_id'] = $data['item_id'][$k];
            $tmpData['need_money'] = $data['need'][$k];
            $tmpData['other'] = isset($data['other'][$k])?$data['other'][$k]:0;
            if ($api==FALSE)
                $tmpData['status'] = $_POST['deal'.$k];
            else{
                $tmpData['status'] = $data['deal'.$k];
                $_POST['deal'.$k] = $data['deal'.$k];
            }
            $oProduct_items->save($tmpData);
            //退货单信息
            $memo='';
             $Process_data=array('bn'=>$v,'branch_id'=>$data['branch_id'][$k],'product_id'=>$data['product_id'][$k],'return_id'=> $return_id,'product_name'=>$data['name'][$k],'number'=>1,'order_id'=>$oProduct_detail['order_id'],'delivery_id'=>$oProduct_detail['delivery_id'],'goods_id'=>$data['goods_id'][$k]);
             if($_POST['deal'.$k] == 1){
                    /*退货*/
                    $this->create_reship($Process_data);
                    $reship_num++;

                   //增加售后日志
            }elseif($_POST['deal'.$k] == 2){
                    $Order_data[] = array('bn'=>$v,'name'=>$data['name'][$k],'num'=>1);

                    $this->create_reship($Process_data);
                    //增加售后日志
                    $reship_num++;

            }elseif($_POST['deal'.$k] == 3){
                //danny_freeze_stock_log
                //define('FRST_OPER_ID','0');
                //define('FRST_OPER_NAME','');
                define('FRST_TRIGGER_OBJECT_TYPE','发货单：售后申请原样寄回生成发货单');
                define('FRST_TRIGGER_ACTION_TYPE','ome_mdl_return_product：saveinfo');
                 $new_delivery_bn = $this->create_delivery($Process_data);
                 $delivery_memo = '，发货单号为:'.$new_delivery_bn;
                //增加售后日志
                 $delivery_num++;
            }
       }
       
       /*当换货时生成一张订单,将接收过来同为换货的订单相同货号的数量累加*/
       if(!empty($Order_data)){
            //danny_freeze_stock_log
            //define('FRST_OPER_ID','0');
            //define('FRST_OPER_NAME','');
            define('FRST_TRIGGER_OBJECT_TYPE','订单：售后申请换货生成新订单');
            define('FRST_TRIGGER_ACTION_TYPE','ome_mdl_return_product：saveinfo');
           $new_order_id=$this->create_order($Order_data,$oProduct_detail['order_id'], $return_id);
           $order_memo = '，订单号为:'.$new_order_id;
           $order_num++;
        }
       $aData['return_id'] = $data['return_id'];
       if($data['status']==4){
            if($data['totalmoney']==''){
                $aData['money']=$data['money'];
             }else{
                $aData['money']=$data['totalmoney'];
                $aData['tmoney']=$data['tmoney'];
                $aData['bmoney']=$data['bmoney'];
            }
            $aData['memo'] = $data['dealmemo'];
            $memo.='售后服务完成';
       }else{

            $aData['memo'] = $data['dealmemo'];
            $aData['money'] = $data['totalmoney'];
            $aData['tmoney']=$data['tmoney'];
            $aData['bmoney']=$data['bmoney'];
              //补差价
           //增加售后日志
            $memo.= '售后服务：补差价(￥'.-(float)$data['totalmoney'].')';
        }
        $aData['status'] = $data['status'];
        $aData['last_modified'] = time();
        $this->save($aData);
        /*日志描述start*/
        if($reship_num!=0){
            $memo.='   生成了'.$reship_num.'张退货单,';
        }
        if($order_num!=0){
            $memo.='   生成了'.$order_num.'张订单'.$order_memo;
        }
        if($delivery_num!=0){
            $memo.='   生成了'.$delivery_num.'张发货单'.$delivery_memo;
        }
        if($data['memo']!=''){
          $memo.='(处理备注：'.$data['memo'].')';
        }
         /*日志描述end*/
        $oOperation_log->write_log('return@ome',$return_id,$memo);

        if ($api==FALSE){
            //售后申请状态API
            foreach(kernel::servicelist('service.aftersale') as $object=>$instance){
                if(method_exists($instance,'update_status')){
                    $instance->update_status($aData['return_id']);
                }
            }
        }

        return $Process_data;
    }

    /*
    *  保存售后服务[申核中,通过申请]。
    */
    function tosave($adata, $api=false, &$error_msg=null)
    {
        $status = $adata['status'];
        
        $oPro_items = $this->app->model('return_product_items');
        $oOperation_log = $this->app->model('operation_log');
        $choose_type_flag = $adata['choose_type_flag'];

        $oldStatus = $this->db_dump(['return_id'=>$adata['return_id']], 'status');
        if($oldStatus['status'] == 4){
            $error_msg = '售后申请单已处理完成';
            return false;
        }
        if($status==3 && $choose_type_flag){
             //接收数据，并做相应的操作类型转换
             $status_type = $adata['choose_type']?$adata['choose_type']:'1';
             
             $error_msg = '';
             $addmemo = $this->transform_return_type($adata['return_id'], $status_type, $adata, $error_msg);
             if (!$addmemo){
                return false;
             }
        }

        $savedata = array('status'=>$adata['status'],'return_id'=>$adata['return_id'],'last_modified'=>time());
        if ($adata['outer_lastmodify']) $savedata['outer_lastmodify'] = $adata['outer_lastmodify'];
        
        //平台退货地址ID
        if($adata['address_id']){
            $savedata['address_id'] = $adata['address_id'];
        }
        
        //平台售后状态
        if($adata['platform_status']){
            $savedata['platform_status'] = $adata['platform_status'];
        }
        
        $result = $this->save($savedata);
        $memo = '售后服务:'.$this->get_return_status($status).$addmemo;

        if($adata['memo']!=''){
            $memo.='(处理备注：'.$adata['memo'].')';
        }
        
        $oOperation_log->write_log('return@ome',$adata['return_id'],$memo);
        
        //记录抖音退货地址信息
        if($adata['address_id']){
            $addressObj = app::get('ome')->model('return_address');
            $addressInfo = $addressObj->dump(array('contact_id'=>$adata['address_id']), '*');
            
            //log
            $log_msg = '选择平台退货地址ID：%s，%s-%s-%s %s';
            $log_msg = sprintf($log_msg, $addressInfo['contact_id'], $addressInfo['province'], $addressInfo['city'], $addressInfo['country'], $addressInfo['addr']);
            $oOperation_log->write_log('return@ome', $adata['return_id'], $log_msg);
        }
        
        if ($api === false){
            //售后申请状态API
            foreach(kernel::servicelist('service.aftersale') as $object=>$instance){
                if(method_exists($instance,'update_status')){
                    $instance->update_status($adata['return_id']);
                }
            }
        }
        
        //保存失败
        if($result === false){
            $error_msg = '保存售后申请单失败';
        }
        
        return $result;
     }
     
     /**
      * 接收数据，并做相应的操作类型转换
      *     status 1 退货单 2 换货单 3 退款申请单
      * @return void
      * @author
      **/
     function transform_return_type($return_id, $status, $post, &$error_msg=null)
     {
        switch ($status) {
            case '1':
            case '2':
                $error_msg = '';
                $result = $this->create_treship($return_id,$status, $error_msg);
                break;
            case '3':
                //创建退款申请单
                $post['source']          = 'local';
                $post['refund_refer']    = '1';
                $is_update_order         = true;//是否更新订单付款状态
                unset($post['status']);//释放外层售后申请状态为3的干扰值

                $return = kernel::single('ome_refund_apply')->createRefundApply($post, $is_update_order, $error_msg);
                if(!$return){
                    $result    = false;//创建失败提示信息
                }else{
                    $result    = $return['msg'];//创建成功提示信息
                }
                break;
        }

        return $result;
     }

     /**
      * undocumented function
      *
      * @return void
      * @author
      **/
     function create_treship($return_id,$status, &$error_msg=null)
     {
        $Oreship = $this->app->model('reship');
        $Oproduct = $this->app->model('return_product');

        $Oorder = $this->app->model('orders');
        $Oorderitems = $this->app->model('order_items');
        $Odelivery = $this->app->model('delivery');
        $operLogMdl = $this->app->model('operation_log');
        
        $fudaiLib = kernel::single('material_fukubukuro_dispatch');
        
        $returninfo = $Oproduct->dump(array('return_id'=>$return_id));
        //判断来源
        $source = $returninfo['source'];
        $edit_reship_flag = false;
        //判断退货单重复
        if ($return_id){
            $flag_type = $returninfo['flag_type'];
            
            $reshipdata =$Oreship->dump(array('return_id'=>$return_id),'reship_id,is_check,shop_type');
            
            //[抖音平台]支持顾客重新编辑已拒绝的售后申请单
            if ($reshipdata['shop_type'] == 'luban' && $reshipdata['is_check'] == '5'){
                //OMS会重新生成退货单
            }else if($flag_type && ($flag_type& ome_reship_const::__EDIT_RETURN_CODE || $flag_type& ome_reship_const::__EDIT_CHANGE_CODE)){
                $edit_reship_flag = true;
                //换转退。退转换时
            }elseif ($reshipdata){
                $error_msg = '售后申请单已经存在退货单,不能重复创建';
                return false;
            }
        }
        
        $archiveLib = kernel::single('archive_order');
        if ($archiveLib->is_archive($source)) {
            $archive_ordObj = kernel::single('archive_interface_orders');
            $archive_delObj = kernel::single('archive_interface_delivery');
            $orderinfo = $archive_ordObj->getOrders(array('order_id'=>$returninfo['order_id']),'*');
            $order_items = $archive_ordObj->getItemList($returninfo['order_id']);
        }else{
             $order_items = $Oorderitems->getList('item_id,product_id,price,sale_price,nums,pmt_price,amount,divide_order_fee,part_mjz_discount',array('order_id'=>$returninfo['order_id']));
             $orderinfo = $Oorder->dump(array('order_id'=>$returninfo['order_id']));
        }

        $oDc = $this->app->model('dly_corp');
        $dc_data = $oDc->dump($orderinfo['logi_id']);
        $sdf = array(
            'order_id'=> $returninfo['order_id'],
            'delivery_id'=> $returninfo['delivery_id'],
            'member_id'=> $orderinfo['member_id'],
            'logi_name'=> $dc_data['name'],
            'logi_no'=> $orderinfo['logi_no'],
            'logi_id'=> $orderinfo['logi_id'],
            'ship_name'=> $orderinfo['consignee']['name'],
            'ship_area'=> $orderinfo['consignee']['area'],
            'delivery'=> $orderinfo['shipping']['shipping_name'],
            'ship_addr'=> $orderinfo['consignee']['addr'],
            'ship_zip'=> $orderinfo['consignee']['zip'],
            'ship_tel'=> $orderinfo['consignee']['telephone'],
            'ship_email'=> $orderinfo['consignee']['email'],
            'ship_mobile'=> $orderinfo['consignee']['mobile'],
            'is_protect'=> $orderinfo['shipping']['is_protect'],
            'memo'=> '',
            'return_id'=> $return_id,
            'source'=>$source,
            'shop_id'=>$orderinfo['shop_id'],
            'platform_status' => $returninfo['platform_status'],
        );
        if($edit_reship_flag){
            $sdf['reship_id'] = $reshipdata['reship_id'];
        }
       
        //平台订单号
        if($returninfo['platform_order_bn']){
            $sdf['platform_order_bn'] = $returninfo['platform_order_bn'];
        }
        if($returninfo['source'] == 'matrix') {
            $sdf['reship_bn'] = $returninfo['return_bn'];
        }
        
        if($returninfo['cos_id']) {
            $sdf['cos_id'] = $returninfo['cos_id'];
        }
        
        if($returninfo['betc_id']) {
            $sdf['betc_id'] = $returninfo['betc_id'];
        }
        
        if($returninfo['problem_id']) {
            $sdf['problem_id'] = $returninfo['problem_id'];
        }
        
        //标识
        if($returninfo['flag_type']){
            $sdf['flag_type'] = $returninfo['flag_type'];
        }
        
        if($returninfo['changebranch_id']) {
            $sdf['changebranch_id'] = $returninfo['changebranch_id'];
        }
        
        $orderItems = array();
         $orderItemProducts = array();
        $tmp_items = array();
        foreach ($order_items as $key => $value)
        {
            $order_item_id = $value['item_id'];
            $product_id = $value['product_id'];
            
            $sale_price = $value['sale_price'] > 0 ? $value['sale_price'] : bcsub($value['amount'], $value['pmt_price'],3);
            // 如果有均摊付费价格
            if ($value['divide_order_fee'] > 0 || $value['part_mjz_discount'] > 0) {
                $sale_price = $value['divide_order_fee'];
            }
            
            //items
            $value['actual_amount'] = $sale_price; //商品实际支付金额
            $orderItems[$product_id] = $value;
            $orderItemProducts[$order_item_id] = $value;
            
            //avg
            $sale_price = round($sale_price/$value['nums'],2);
            $tmp_items[$value['product_id']] = $sale_price;
            
            unset($value);
        }

        $tmoney = 0;
        $Oproduct_items = $this->app->model('return_product_items');
        $pro_items = $Oproduct_items->getList('*',array('return_id'=>$return_id,'disabled'=>'false'));
        foreach($pro_items as $k=>$v){
            $order_item_id = $v['order_item_id'];
            $product_id = $v['product_id'];
            
            //$sdf['return']['item_id'][$v['bn']] = $v['item_id'];
            $sdf['return']['goods_bn'][$k] = $v['order_item_id'];

            $sdf['return']['bn'][$v['order_item_id']] = $v['bn'];
            $sdf['return']['goods_name'][$v['order_item_id']] = $v['name'];
            $sdf['return']['price'][$v['order_item_id']] = $v['price'];
            $sdf['return']['num'][$v['order_item_id']] = $v['num'];
            $sdf['return']['branch_id'][$v['order_item_id']] = $v['branch_id'];
            $sdf['return']['product_id'][$v['order_item_id']] = $v['product_id'];
            $sdf['return']['order_item_id'][$v['order_item_id']] = $v['order_item_id'];
            $sdf['return']['oid'][$v['order_item_id']] = $v['oid'];

            $sdf['return']['shop_goods_bn'][$v['order_item_id']] = $v['shop_goods_bn'];
            $sdf['return']['obj_type'][$v['order_item_id']] = $v['obj_type'];
            $sdf['return']['quantity'][$v['order_item_id']] = $v['quantity'];
            
            //按订单明细item_id进行精准匹配
            //@todo：防止订单PKG组合商品与普通商品有相同的基础物料;
            if(isset($orderItemProducts[$order_item_id]) && $orderItemProducts[$order_item_id]){
                //退货数量与订单购买数量一致时,直接使用商品实付金额(防止多个数量,金额均摊不匀)
                if($orderItemProducts[$order_item_id]['nums'] == $v['num']){
                    $tmoney += $orderItemProducts[$order_item_id]['actual_amount'];
                }else{
                    $tmoney += ($v['num'] * $tmp_items[$v['product_id']]);
                }
            }else{
                //退货数量与订单购买数量一致时,直接使用商品实付金额(防止多个数量,金额均摊不匀)
                if(isset($orderItems[$product_id]) && $orderItems[$product_id]['nums'] == $v['num']){
                    $tmoney += $orderItems[$product_id]['actual_amount'];
                }else{
                    $tmoney += ($v['num'] * $tmp_items[$v['product_id']]);
                }
            }
            
        }
        
        $change = array();
        
        //判断是否是天猫换货如果是需要把已有参数组织 就算是选择了退也仍然是换
        $tmall_change_flag = false;

        if($returninfo['source'] == 'matrix'){
            $sdf['reship_bn'] = $returninfo['return_bn'];
        }elseif($returninfo['source'] == 'delivery_back'){
            //追回发货单自动创建的售后申请单,默认使用售后申请单号
            $checkReshipInfo = $Oreship->dump(array('reship_bn'=>$returninfo['return_bn']),'reship_id');
            if(empty($checkReshipInfo)){
                $sdf['reship_bn'] = $returninfo['return_bn'];
            }
        }
        
        if ($returninfo['source'] == 'matrix' )
        {
            if($returninfo['shop_type'] == 'tmall'){
                $tmall_detail = kernel::single('ome_service_aftersale')->get_return_type(array('return_id'=>$return_id));
            }else if(in_array($returninfo['shop_type'],['luban','pinduoduo','youzan'])){
                $tmall_detail = app::get('ome')->model('return_product_'.$returninfo['shop_type'])->dump(array('return_id'=>$returninfo['return_id'],'refund_type'=>'change'),'*');
            } else {
                $tmall_detail = app::get('ome')->model('return_apply_special')->db_dump(array('return_id'=>$returninfo['return_id']),'*');
                if($tmall_detail['special'] && is_array(json_decode($tmall_detail['special'], 1))) {
                    $tmall_detail = array_merge($tmall_detail, json_decode($tmall_detail['special'], 1));
                }
            }
            

            if ($tmall_detail['refund_type'] == 'change'){
                $sdf['source'] = 'matrix';
                //微盟换出订单号使用平台的
                if(in_array($returninfo['shop_type'],['weimobv'])){
                    $sdf['reship_bn'] = $tmall_detail['reship_bn'] ? $tmall_detail['reship_bn'] : $returninfo['return_bn'];
                }else{
                    $sdf['reship_bn'] = $returninfo['return_bn'];
                }
                $buyer_address = $tmall_detail['buyer_address'] ? explode('^^^',$tmall_detail['buyer_address']) : '';
                if ($buyer_address){

                    $sdf['ship_addr'] = array_pop($buyer_address);
                    $ship_area = implode('/',$buyer_address);
                    kernel::single('eccommon_regions')->region_validate($ship_area);
                    $sdf['ship_area'] = $ship_area;
                }
                $status = '2' ;
                $exchange_sku = $tmall_detail['exchange_sku'];
                $change_price = $tmoney/$tmall_detail['exchange_num'];
                
                //销售物料信息
                $salesMLib = kernel::single('material_sales_material');
                $salesMInfo = $salesMLib->getSalesMByBn($sdf['shop_id'],$tmall_detail['exchange_sku']);
                $objects = array();
                if($salesMInfo){
                    if($salesMInfo['sales_material_type'] == 5){
                        $basicMInfos = $salesMLib->get_order_pickone_bminfo($salesMInfo['sm_id'],$tmall_detail['exchange_num'],$sdf['shop_id']);
                        $obj_type = 'pko';
                    }elseif($salesMInfo['sales_material_type'] == 7) {
                        $basicMInfos = [];
                        
                        //福袋组合
                        $luckybagParams = [];
                        $luckybagParams['sm_id'] = $salesMInfo['sm_id'];
                        $luckybagParams['sale_material_nums'] = $tmall_detail['exchange_num']; //换出数量
                        $luckybagParams['shop_bn'] = $orderinfo['shop_bn'];
                        
                        $fdResult = $fudaiLib->process($luckybagParams);
                        if($fdResult['rsp'] == 'succ'){
                            $basicMInfos = $fdResult['data'];
                        }else{
                            //标记福袋分配错误信息
                            $luckybag_error = $fdResult['error_msg'];
                            
                            //logs
                            $operLogMdl->write_log('return@ome', $return_id, '换出福袋商品失败：'. $luckybag_error);
                        }
                        
                        $obj_type = 'lkb';
                    }else{
                        $basicMInfos = $salesMLib->getBasicMBySalesMId($salesMInfo['sm_id']);
                    }
                    
                    if($salesMInfo['sales_material_type'] == 2){
                        $salesMLib->calProSaleMPriceByRate($change_price, $basicMInfos);
                        $obj_type = 'pkg';
                    }

                    if($basicMInfos){
                        foreach($basicMInfos as $k => $basicMInfo){
                            //福袋组合ID
                            $luckybag_id = ($basicMInfo['combine_id'] ? $basicMInfo['combine_id'] : 0);
                            
                            //price
                            $item_price = $change_price;
                            if(in_array($obj_type, ['pkg', 'lkb'])){
                                $item_price = $basicMInfo['rate_price'];
                            }
                            
                            //number
                            $item_nums = $basicMInfo['number'] * $tmall_detail['exchange_num'];
                            if(in_array($obj_type, ['lkb'])){
                                $item_nums = $basicMInfo['number'];
                            }
                            
                            $items[] = array(
                                'bm_id'         => $basicMInfo['bm_id'],
                                'material_name' => $basicMInfo['material_name'],
                                'material_bn'   => $basicMInfo['material_bn'],
                                'type'          => $basicMInfo['type'],
                                'number'        => $item_nums,
                                'change_num'    => $item_nums,
                                'price'         => $item_price,
                                'luckybag_id'   => $luckybag_id, //福袋组合ID
                            );
                        }
                    }
                    
                    $objects[] = array(
                        'name'          =>  $salesMInfo['sales_material_name'],
                        'num'           =>  $tmall_detail['exchange_num'],
                        'price'         =>  $change_price,
                        'product_id'    =>  $salesMInfo['sm_id'],
                        'bn'            =>  $salesMInfo['sales_material_bn'],
                        'obj_type'      =>  $obj_type ? $obj_type : 'goods',
                        'item_type'     =>  $obj_type ? $obj_type : 'goods',
                        'items'         =>  $items,
                    );
                }

                if ($objects){
                    $sdf['change']['objects'] = $objects;
                }
                
                $sdf['ship_mobile'] =  $tmall_detail['buyer_phone'] ? $tmall_detail['buyer_phone'] : $sdf['ship_mobile'];
                
                $sdf['return_logi_name'] = $tmall_detail['buyer_logistic_name'];
                $sdf['return_logi_no'] = $tmall_detail['buyer_logistic_no'];
                $sdf['change_amount'] =  $tmoney;
                $tmall_change_flag = true;
                if (kernel::single('ome_reship_const')->isNewExchange($returninfo['flag_type'])) {
                    $sdf['flag_type'] = $returninfo['flag_type'] | ome_reship_const::__NEW_EXCHANGE_REPAIR;
                }
            }
        }
        
        $sdf['tmoney'] = $tmoney;
        
        // 退运费
        $sdf['refund_shipping_fee'] = $returninfo['refund_shipping_fee'];
        $addmemo = '并生成一张';
        if($status == '1'){
            $sdf['return_type'] = 'return';
            $addmemo .='退货单';
        }else{
            $sdf['return_type'] = 'change';
            $addmemo .='换货单';
        }
        
        //创建退换货单
        $msg = '';
        $reship_bn = $Oreship->create_treship($sdf,$msg);
        if($reship_bn === false){
            $error_msg = $msg;
            return false; //创建失败
        }
        
        $addmemo .='. 单号为: '.$reship_bn;
        return $addmemo;
     }
     
      /**
       * !!!废弃,已不再使用!!!
       * 现使用：kernel::single('ome_refund_apply')->createRefundApply($sdf, $is_update_order, $error_msg);
       *
       *
       * 生成退款申请单
       *
       * @return void
       * @author
       **/
      function create_refund_apply($post)
      {

           $return = kernel::single('ome_refund_apply')->refund_apply_add($post,'1');
           if ($return['result'] == true){
                $result  = true;
           }else{
                $result = false;
           }

           return $return['msg'];
      }

     /*
      * 更新售后申请状态
      */
     function update_status($sdf){
           $this->save($sdf);
           //售后申请状态API
            foreach(kernel::servicelist('service.aftersale') as $object=>$instance){
                if(method_exists($instance,'update_status')){
                    $instance->update_status($sdf['return_id']);
                }
           }
     }


     /*
     * 获得售后服务状态
     * $param int $status
     */
     function get_return_status($status){
        $status_value=array (
            1 => '申请中',
            2 => '审核中',
            3 => '接受申请',
            4 => '完成',
            5 => '拒绝',
            6 => '已收货',
            7 => '已质检',
            8 => '补差价',
            9 => '已拒绝退款',
        );

         return $status_value[$status];
     }

  /*
   * 生成发货单
   * @param array $adata
   * return int
   */
   function create_delivery($adata)
   {

       $oDelivery = $this->app->model('delivery');
       $odelivery_order = $this->app->model('delivery_order');
       $odelivery_return = $this->app->model('delivery_return');
       $delivery_id = $adata['delivery_id'];
       $delivery = $oDelivery->dump($delivery_id);

       $delivery_sdf=array(
           'branch_id'=>$adata['branch_id'],
           'is_protect'=>$delivery['is_protect'],
           'delivery' => $delivery['delivery'],
           'logi_id'=>$delivery['logi_id'],
           'logi_name'=>$delivery['logi_name'],
           'op_id'=>kernel::single('desktop_user')->get_id(),
           'create_time'=>time(),
           'type'=>'reject',
           'delivery_items' =>array(
               array(
                'product_id'=>$adata['product_id'],
                'bn' =>$adata['bn'],
                'product_name' =>$adata['product_name'],
                'number' => 1,
                'goods_id'=>$adata['goods_id'],

                ),
           )
        );
        $ship_info=array(
           'name' => $delivery['consignee']['name'],
           'area' => $delivery['consignee']['area'],
           'addr' => $delivery['consignee']['addr'],
           'zip' => $delivery['consignee']['zip'],
           'telephone' =>$delivery['consignee']['telephone'],
           'mobile' =>$delivery['consignee']['mobile'],
           'email' => $delivery['consignee']['email']
          );

        $result=$oDelivery->addDelivery($adata['order_id'],$delivery_sdf,$ship_info);
        $delivery_bn = $oDelivery->dump(array('delivery_id'=>$result['data']),'delivery_bn');
        $delivery_bn = $delivery_bn['delivery_bn'];
        $deli_order_sdf = array(
            'order_id'=>$adata['order_id'],
            'delivery_id'=>$result
        );
        $odelivery_order->save($deli_order_sdf);
        $deli_return_sdf = array(
            'return_id'=>$adata['return_id'],
            'delivery_id'=>$result
        );
       $odelivery_return->save($deli_return_sdf);
       return $delivery_bn;
   }

  /*
   *生成退货单
   *并根据退货数量修改订单退货状态
   * @param array $adata
   * return int
  */
   function create_reship($adata){
       $oReship = $this->app->model('reship');
       //判断退货单重复
       if ($adata['return_id']) {
           $reshipdata = $oReship->dump(array('return_id' => $adata['return_id']), 'reship_id,is_check,shop_type');
           //小程序重复创建退货单拦截
           if ($reshipdata && $reshipdata['shop_type'] == 'ecos.ecshopx') {
               //售后申请单已经存在退货单,不能重复创建
               return true;
           }
       }
       
       $oDelivery = $this->app->model('delivery');
       $delivery = $oDelivery->dump($adata['delivery_id']);
       $reship_bn = $oReship->gen_id();
       $oReturn = $this->dump($adata['return_id'],'process_data');
       $process_data = unserialize($oReturn['process_data']);
       $process_data = $process_data[$adata['branch_id']];
       $process_data['shipmoney'] = $process_data['shipmoney'] ? $process_data['shipmoney'] : '0';
       $Reshipdata = array(
           'status' => 'succ',
           'reship_bn'=>$reship_bn,
           'bn'=>$adata['bn'],
           'branch_id'=>$adata['branch_id'],
           'is_protect'=>$delivery['is_protect'],
           'logi_id'=>$delivery['logi_id'],
           'logi_name'=>$process_data['shipcompany'],
           'logi_no'=>$process_data['shiplogino'],
           'money'=>$process_data['shipmoney'],
           't_begin'=>time(),
           't_end'=>time(),
           'weight'=>$delivery['weight'],
           'op_id'=>kernel::single('desktop_user')->get_id(),
           'return_id'=>$adata['return_id'],
           'order_id'=>$adata['order_id'],
           'shop_id'=>$delivery['shop_id'],
           'consignee'=>array(
               'name'=>$delivery['consignee']['name'],
               'addr'=>$delivery['consignee']['addr'],
               'zip'=>$delivery['consignee']['zip'],
               'telephone'=>$delivery['consignee']['telephone'],
               'mobile'=>$delivery['consignee']['mobile'],
               'email'=>$delivery['consignee']['email'],
               'area'=>$delivery['consignee']['area'],
            ),
           'reship_items'=>array(
               array(
                   'bn'=>$adata['bn'],
                   'product_name'=>$adata['product_name'],
                   'num'=>1
                   )
           )
        );
        $shop_info = app::get('ome')->model('shop')->getShopInfo($Reshipdata['shop_id']);
        // 经销店铺的单据，delivery_mode冗余到售后申请表
        if ($shop_info['delivery_mode'] == 'jingxiao') {
            $Reshipdata['delivery_mode'] = $shop_info['delivery_mode'];
        }

    if($oReship->save($Reshipdata)){
        //退货单创建 API

        foreach(kernel::servicelist('service.reship') as $object=>$instance){
            if(method_exists($instance,'reship')){
                $instance->reship($Reshipdata['reship_id']);
            }
        }

        //更新退货单状态
        foreach(kernel::servicelist('service.reship') as $object=>$instance){
            if(method_exists($instance,'update_status')){
                $instance->update_status($Reshipdata['reship_id']);
            }
        }

        $this->db->exec('UPDATE sdb_ome_order_items SET sendnum=sendnum-1 WHERE order_id='.$adata['order_id'].' AND bn=\''.$adata['bn'].'\'');
        $order_sum = $this->db->selectrow('SELECT sum(sendnum) as count FROM sdb_ome_order_items WHERE order_id='.$adata['order_id']);
        if($order_sum['count']==0){
            $ship_status=4;
        }else{
            $ship_status=3;
        }
       $this->db->exec('UPDATE sdb_ome_orders SET ship_status=\''.$ship_status.'\' WHERE order_id='.$adata['order_id']);

    }

   }

   /*
    *  售后服务生成订单
    *
    * @param array $adata ,int $order_id
    *
    * return $new_order_id
    */
   function create_order($adata,$order_id, $return_id='')
   {
        $oOrder = $this->app->model('orders');
        $oitem = $this->app->model('order_items');
        $oDelivery = $this->app->model('delivery');
        $delivery = $oDelivery->dump($adata['delivery_id']);
        $oGoods = $this->app->model('goods');
        $ret=array();
        foreach($adata as $k=>$v){
            if(isset($ret[$v['bn']])){
                $ret[$v['bn']]['num']++;
            }else{
                $ret[$v['bn']] = $v;
            }
        }
        $tostr='';
        $itemnum=0;
        foreach($ret as $k=>$v){
            $tostr.=$v['bn'].''.$v['name'].'('.$v['num'].')';
            $itemnum+=$v['num'];
        }
        $Order_detail = $oOrder->dump($order_id);
        $order_bn = $oOrder->gen_id();
        $order_sdf = array(
           'order_bn'=>$order_bn,
           'member_id'=>$Order_detail['member_id'],
            'currency'=>'CNY',
            'title'=>$tostr,
            'createtime'=>time(),
            'last_modified'=>time(),
            'confirm'=>'N',
            'status'=>'active',
            'pay_status'=>'0',
            'ship_status'=>'0',
            'is_delivery'=>'Y',
            'shop_id'=>$Order_detail['shop_id'],
            'itemnum'=>$itemnum,
            'shipping'=>array(
                'shipping_id'=>$Order_detail['shipping']['shipping_id'],
                'is_cod'=>'false',
                'shipping_name'=>$Order_detail['shipping']['shipping_name'],
                'cost_shipping'=>$Order_detail['shipping']['cost_shipping'],
                'is_protect'=>$Order_detail['shipping']['is_protect'],
                'cost_protect'=>$Order_detail['shipping']['cost_protect'],
            ),
           'consignee'=>array(
               'name'=>$Order_detail['consignee']['name'],
               'addr'=>$Order_detail['consignee']['addr'],
               'zip'=>$Order_detail['consignee']['zip'],
               'telephone'=>$Order_detail['consignee']['telephone'],
               'mobile'=>$Order_detail['consignee']['mobile'],
               'email'=>$Order_detail['consignee']['email'],
               'area'=>$Order_detail['consignee']['area'],
               'r_time'=>$Order_detail['consignee']['r_time'],
            ),
        );
            foreach($ret as $k1=>$v1){
                $goods = $oGoods->dump(array('bn'=>$v1['bn']),'goods_id');
                $item = $oitem->dump(array('order_id'=>$order_id,'bn'=>$v1['bn']),'*');
                $order_sdf['order_objects'][]=array(
                'obj_type'=> 'goods',  //goods,gift,taobao, api...
                'obj_alias'=> 'goods',  //goods,gift,taobao, api...
                'goods_id'=>$goods['goods_id']=='' ? 0:$goods['goods_id'],
                'bn'=>$v1['bn'],
                'name'=>$ret['name'],
                'price'=>$item['price'],
                'quantity'=>$ret['num'],
                'amount'=>$ret['num']*$item['price'],
                'weight'=>$item['weight']*$ret['num'],
                'order_items'=>array(
                        array(
                           'product_id'=>$item['product_id'],
                            'goods_id'=>$goods['goods_id']=='' ? 0:$goods['goods_id'],
                            'item_type'=>'product',
                            'bn'=>$v1['bn'],
                            'name'=>$v1['name'],
                            'quantity'=>$v1['num'],
                            'sendnum'=>0,
                            'amount'=>$v1['num']*$item['price'],
                            'price'=>$item['price'],
                            'weight'=>$item['weight']*$v1['num'],
                            'shop_product_id' => $item['shop_product_id'],
                            'shop_goods_id' => $item['shop_goods_id']
                          ),
                    ),
            );

             }
       $oOrder->create_order($order_sdf);
       
       //小红书平台：根据return_id查询换货收货地址表中的encrypt_source_data，提取openAddressId到extend_field，openAddressId用于获取电子面单取号
       if($order_sdf['order_id']){
           $extendObj = app::get('ome')->model('order_extend');
           
           if($return_id && $Order_detail['shop_type'] == 'xhs'){
               $returnExchangeReceiverObj = app::get('ome')->model('return_exchange_receiver');
               $exchangeReceiverInfo = $returnExchangeReceiverObj->dump(array('return_id'=>$return_id), 'encrypt_source_data');
               
               if($exchangeReceiverInfo && !empty($exchangeReceiverInfo['encrypt_source_data'])){
                   $encryptSourceData = unserialize($exchangeReceiverInfo['encrypt_source_data']);
                   
                   if($encryptSourceData && is_array($encryptSourceData) && isset($encryptSourceData['openAddressId']) && !empty($encryptSourceData['openAddressId'])){
                       //追加openAddressId到extend_field
                       $extend_data = array(
                           'order_id' => $order_sdf['order_id'],
                           'extend_field' => json_encode(array('openAddressId' => $encryptSourceData['openAddressId']), JSON_UNESCAPED_UNICODE)
                       );
                       $extendObj->save($extend_data);
                   }
               }
           }
       }

       return  $order_sdf['order_bn'];
   }

 /*
  * 获取已处理的申请商品 根据货号订单号获取发货单号以及对应收货相关信息
  * @param int $branch_id,int $order_id
  * return $array
  */
   function Get_check_delivery($bn,$order_id)
   {
        $sqlstr = "SELECT s.delivery_id,s.delivery_bn,s.ship_name,s.ship_area,s.ship_addr,sdi.bn,sum(sdi.number) as number FROM sdb_ome_delivery as s left join sdb_ome_delivery_items sdi on sdi.delivery_id = s.delivery_id left join sdb_ome_delivery_order as o on o.delivery_id=s.delivery_id WHERE o.order_id='$order_id' AND sdi.bn='$bn' AND s.type='normal' AND (s.parent_id=0 OR s.is_bind='true') AND s.status='succ' group by sdi.bn";

        $result=$this->db->selectrow($sqlstr);
        $result['refund'] = $result['number']-$this->Get_check_refund_num($bn,$order_id);

        return $result;
   }

 /*
    *获取已处理的申请商品 根据货号，订单号数量
    */
   function Get_check_refund_num($bn,$order_id)
   {
       $refund = $this->db->selectrow("SELECT sum(i.num) as count FROM sdb_ome_reship as r left join sdb_ome_reship_items as i on r.reship_id=i.reship_id WHERE r.order_id='".$order_id."' AND i.bn='".$bn."' group by i.bn");

       return $refund['count'];

   }

 /*
  * 根据仓库ID，货号订单号获取发货单号以及对应收货相关信息
  * @param int $branch_id,int $order_id
  * return $array
  */
   function Get_delivery($branch_id,$bn,$order_id)
   {
        //过滤合并发货单上总商品数量
        $sqlstr = "SELECT s.delivery_id,s.delivery_bn,s.ship_name,s.ship_area,s.ship_addr,sdi.bn,sum(sdi.number) as number FROM sdb_ome_delivery as s left join sdb_ome_delivery_items sdi on sdi.delivery_id = s.delivery_id left join sdb_ome_delivery_order as o on o.delivery_id=s.delivery_id WHERE o.order_id='$order_id' AND sdi.bn='$bn' AND s.branch_id='$branch_id' AND s.type='normal' AND s.is_bind='false' AND s.status='succ' group by sdi.bn";
                $result=$this->db->selectrow($sqlstr);

        $result['refund'] = $result['number']-$this->Get_refund_num($branch_id,$bn,$order_id);

        return $result;
   }
   /*
    *根据仓库，货号，订单号数量
    */
   function Get_refund_num($branch_id,$bn,$order_id)
   {
       $refund = $this->db->selectrow("SELECT sum(i.num) as count FROM sdb_ome_reship as r left join sdb_ome_reship_items as i on r.reship_id=i.reship_id WHERE i.return_type='return' AND r.is_check!='5' AND r.order_id='".$order_id."' AND i.bn='".$bn."' AND i.branch_id='".$branch_id."' group by i.bn");

       return $refund['count'];

   }
/*
 * 下载售后服务图片
 */
   function file_download($filename){
        $file = @ fopen($filename,"r");
        Header("Content-type: application/octet-stream");
        $file_name=substr($filename,strpos($filename,'upload'));
        Header("Content-Disposition: attachment; filename=" . $file_name);
        while (!feof ($file)) {
            echo fread($file,5000);
        }
        fclose ($file);
    }
    
    /*
    *根据订单和货号获取购买数量
    *@param int $order_id ,varchar $bn
    *return int
    */
    function get_order_count($order_id,$bn)
    {
        $sqlstr = "SELECT s.nums as count FROM sdb_ome_order_items as s WHERE s.order_id='$order_id' AND s.bn='$bn'";
        $result=$this->db->selectrow($sqlstr);
        return $result['count'];
    }
    
    /*外部记录流水号*/
    function gen_id(){
        $i = rand(0,9999);
        do{
            if(9999==$i){
                $i=0;
            }
            $i++;
            $return_bn = date('YmdH').'15'.str_pad($i,6,'0',STR_PAD_LEFT);
            $row = $this->db->selectrow('SELECT return_bn from sdb_ome_return_product where return_bn =\''.$return_bn.'\'');
        }while($row);
        return $return_bn;
    }

    /*
     * 根据订单号获取申请售后记录
     */
    function Get_aftersale_list($order_id){
        $market = $this->db->select('SELECT a.return_id,a.return_bn,a.status,l.op_name,l.operate_time from sdb_ome_return_product as a left join sdb_ome_operation_log as l on a.return_id=l.obj_id where a.order_id='.$order_id.' AND l.obj_type="return_product@ome" group by l.obj_id ORDER BY log_id DESC');
        foreach($market as $K=>$v){
           $market[$K]['status_value']=$this->get_return_status($v['status']);
        }
        return $market;
    }

    function searchOptions(){
        $parentOptions = parent::searchOptions();
        $childOptions = array(
            'order_bn'=>app::get('base')->_('订单号'),
            'return_bn'=>app::get('base')->_('退货记录流水号'),
            'ship_name'=>app::get('base')->_('收货人'),
            'member_uname'=>app::get('base')->_('用户名'),
            'product_bn'=>app::get('base')->_('货号'),
        );
        return array_merge($childOptions,$parentOptions);
    }
    
    //店铺类型
    public function modifier_shop_type($row)
    {
        $shopTypeList = ome_shop_type::get_shop_type();
        
        return $shopTypeList[$row];
    }
    
    /**
     * @description
     * @access public
     * @param void
     * @return void
     */
    public function modifier_money($row)
    {
        $cur = app::get('eccommon')->model('currency');
        if ($row<0) {
            $c = $cur->changer(-1*$row);
            $row = '还需用户补款:<span style="color:#3333ff;">'.$c.'</span>';
        }else{
            $c = $cur->changer($row);
            $row = '需退还用户:<span style="color:red;">'.$c.'</span>';
        }

        return $row;
    }
    /**
     * 单据来源.
     * @param   type    $varname    description
     * @return  type    description
     * @access  public
     * @author cyyr24@sina.cn
     */
    function modifier_source($row)
    {

        if ($row == 'local') {
            $row = '本地';
        }else if($row == 'matrix'){
           $row = '线上';
        }else if($row == 'archive'){
           $row = sprintf("<div style='background-color:%s;float:left;'><span alt='%s' title='%s' style='color:#eeeeee;'>&nbsp;%s&nbsp;</span></div>", 'red', '归档', '归档', '归档');
        }else{
            $row = '-';
        }
        return $row;
    }

    /**
     * 售后原因
     * @param
     * @return  string
     * @access  public
     * @author cyyr24@sina.cn
     */
    function modifier_content($row)
    {
        if ($row) {
            $reason = sprintf("<div style='background-color:%s;float:left;'><span alt='%s' title='%s' style='color:#eeeeee;'>&nbsp;%s&nbsp;</span></div>", 'green', $row, $row, $row);
            return $reason;
        }

    }

    function io_title( $filter=null,$ioType='csv' ){
        switch( $ioType ){
            case 'csv':
            default:
                $this->oSchema['csv']['return'] = array(
                    '*:退货记录流水号' => 'return_bn',
                    '*:订单号' => 'order_bn',
                    '*:退货记录标题' => 'title',
                    '*:最后合计金额' => 'money',
                    '*:最后更新时间' => 'last_modified',
                    '*:退款金额' => 'refundmoney',
                    '*:发货单号' => 'delivery_bn',

                    '*:申请时间' => 'add_time',
                    '*:状态' => 'status',
                    '*:售后类型'=>'problem_id',
                    '*:是否收货'=>'recieved',
                    '*:是否质检'=>'verify',
                );

                break;
        }
        $this->ioTitle[$ioType]['return'] = array_keys( $this->oSchema[$ioType]['return'] );

        return $this->ioTitle[$ioType][$filter];
     }

     function fgetlist_csv( &$data,$filter,$offset,$exportType = 1 ){
        set_time_limit(0); // 30分钟
        $max_offset = 1000; // 最多一次导出10w条记录
        if( !$data['title']['return'] ){
            $title = array();
            foreach( $this->io_title('return') as $k => $v ){
                $title[] = $this->charset->utf2local($v);
            }
            $data['title']['return'] = '"'.implode('","',$title).'"';
        }
        $limit = 100;
        if( !$list=$this->getList('*',$filter,0,-1) )return false;
        $oOrders = $this->app->model('orders');
        $oDelivery = $this->app->model('delivery');
        $return_problem = self::return_product_problem();
        $returnRow = array();
        foreach ($list as  &$list) {
            $order_id = $list['order_id'];
            $delivery_id = $list['delivery_id'];
            $orders = $oOrders->dump($order_id,'order_bn');
            $delivery = $oDelivery->dump($delivery_id,'delivery_bn');
            $list['return_bn'] = $list['return_bn']."\t";
            $list['order_bn'] = $orders['order_bn']."\t";
            $list['delivery_bn'] = $delivery['delivery_bn']."\t";
            $list['recieved'] = $list['recieved']=='false' ? '否': '是';
            $list['verify'] = $list['verify']=='false' ? '否': '是';
            $list['add_time'] = date('Y-m-d H:i:s',$list['add_time']);
            $list['last_modified'] = date('Y-m-d H:i:s',$list['last_modified']);

            $list['status'] = self::return_status($list['status']);
            $list['problem_id'] = $return_problem[$list['problem_id']];
            foreach( $this->oSchema['csv']['return'] as $k => $v ){
                $returnRow[$k] = $this->charset->utf2local(utils::apath( $list,explode('/',$v) ));
            }
            $data['content']['return'][] = '"'.implode('","',$returnRow).'"';
        }
        return false;
     }

     function export_csv($data,$exportType = 1 ){
        $output = array();
        //if( $exportType == 2 ){
            foreach( $data['title'] as $k => $val ){
                $output[] = $val."\n".implode("\n",(array)$data['content'][$k]);
            }
        //}
        echo implode("\n",$output);
    }


    /**
     * 售后状态值
     * @param   status    状态值
     * @return  type    状态值
     */
    static function return_status($status)
    {
        $return_status = array (
            1 => '申请中',
            2 => '审核中',
            3 => '接受申请',
            4 => '完成',
            5 => '拒绝',
            6 => '已收货',
            7 => '已质检',
            8 => '补差价',
            9 => '已拒绝退款',
        );
        return $return_status[$status];
    } // end func


    /**
     * 售后问题类型
     * @param
     * @return
     * @access  public
     * @
     */
     function return_product_problem()
    {
        $oProblem = $this->app->model('return_product_problem');
        $problem = $oProblem->getCatList();
        $problem_list = array();
        foreach ($problem as  $problem) {
            $problem_list[$problem['problem_id']] = $problem['problem_name'];
        }
        return $problem_list;
    } // end func

    public function batch_update_product_data($return_ids,$data){
        $need_return_id = array();
        foreach ($return_ids as $return_id ) {
            $return_id = explode('||',$return_id);
            $need_return_id[] = $return_id[1];
        }
        
        $oReturn = app::get('ome')->model('return_product');
        $sql = 'SElECT return_id FROM sdb_ome_return_product WHERE return_id in ('.implode(',',$need_return_id).') AND `status` in (\'1\',\'2\')';
        $return_list = $oReturn->db->select($sql);
        $return_ids = array_map('current',  $return_list);
        if($return_ids){
            $up_data = array('post_fee_bear_role'=>$data['post_fee_bear_role']);
            
            //平台售后退货地址ID
            if($data['address_id']){
                $up_data['address_id'] = $data['address_id'];
            }
            
            $filter['return_id'] = $return_ids;
            $oReturn->update($up_data,$filter);
            
            //记录抖音退货地址信息
            if($up_data['address_id'] && is_array($return_ids)){
                $addressObj = app::get('ome')->model('return_address');
                $addressInfo = $addressObj->dump(array('contact_id'=>$up_data['address_id']), '*');
                
                $log_msg = '选择平台退货地址ID：%s，%s-%s-%s %s';
                $log_msg = sprintf($log_msg, $addressInfo['contact_id'], $addressInfo['province'], $addressInfo['city'], $addressInfo['country'], $addressInfo['addr']);
                
                //log
                if($addressInfo){
                    $oOperation_log = $this->app->model('operation_log');
                    foreach ($return_ids as $key => $return_id)
                    {
                        $oOperation_log->write_log('return@ome', $return_id, $log_msg);
                    }
                }
            }
        }
        
        return true;
    }
    
    /**
     * CSV字段转义函数
     * 直接替换掉可能导致CSV错行的特殊字符
     *
     * @param string $field 需要转义的字段值
     * @return string 转义后的字段值
     */
    private function escapeCsvField($field)
    {
        // 将字段转换为字符串
        $field = (string)$field;
        
        // 直接替换掉可能导致CSV错行的特殊字符
        $field = str_replace(array(',', '"', "\n", "\r", "\t"), '', $field);
        
        return $field;
    }

    /**
     * 导出售后申请单明细数据
     * 返回按 return_id 分组的数据结构，供 getExportDataByCustom 使用
     *
     * @param $fields
     * @param $filter
     * @param $offset
     * @param $limit
     * @param $has_title
     * @return array
     */
    public function getexportdetail($fields, $filter, $offset=0, $limit=1, $has_title=false)
    {
        $returnItemObj = app::get('ome')->model('return_product_items');
        $branchObj = app::get('ome')->model('branch');
        
        $data = array();
        
        // 获取售后申请单ID列表
        if (isset($filter['return_id'])) {
            $return_ids = is_array($filter['return_id']) ? $filter['return_id'] : array($filter['return_id']);
        } else {
            $return_ids = array();
        }
        
        // 如果 return_id 为空，直接返回空数组
        if (empty($return_ids)) {
            return $data;
        }
        
        // 售后明细列表
        $itemList = $returnItemObj->getList('*', array('return_id|in'=>$return_ids), 0, -1, 'return_id DESC');
        if(empty($itemList)){
            return $data;
        }
        
        // 仓库列表
        $branchList = array();
        $tempList = $branchObj->getList('branch_id,name', array());
        foreach($tempList as $val)
        {
            $branch_id = $val['branch_id'];
            $branchList[$branch_id] = $val['name'];
        }
        unset($tempList);
        
        // 按 return_id 分组返回数据
        foreach($itemList as $key => $item)
        {
            $return_id = $item['return_id'];
            $branch_id = $item['branch_id'];
            
            $itemRow = array(
                'bn' => $item['bn'],
                'name' => $item['name'],
                'num' => $item['num'],
                'price' => $item['price'],
                'branch_name' => isset($branchList[$branch_id]) ? $branchList[$branch_id] : '-',
            );
            
            $data[$return_id][] = $itemRow;
        }
        
        return $data;
    }
    
    /**
     * 售后申请单明细字段标题定义
     * 只包含主表没有的字段，避免重复
     *
     * @return array 明细字段标题映射（中文标题 => 字段名）
     */
    public function returnProductItemsExportTitle()
    {
        $items_title = array(
            '商品货号' => 'bn',
            '商品名称' => 'name',
            '申请数量' => 'num',
            '价格' => 'price',
            '仓库' => 'branch_name',
        );
        return $items_title;
    }
    
    /**
     * 获取自定义导出标题（主表+明细）
     *
     * @param array $main_title 主表标题数组
     * @return string GBK编码的标题行
     */
    public function getCustomExportTitle($main_title)
    {
        $main_title = array_keys($main_title);
        $items_title = array_keys($this->returnProductItemsExportTitle());
        $title = array_merge($main_title, $items_title);
        return mb_convert_encoding(implode(',', $title), 'GBK', 'UTF-8');
    }
    
    /**
     * 根据查询条件获取导出数据（主表+明细合并）
     * 参考 reship.php 的实现方式
     *
     * @param array $fields 导出字段
     * @param array $filter 筛选条件
     * @param bool $has_detail 是否包含明细
     * @param int $curr_sheet 当前sheet页
     * @param int $start 起始位置
     * @param int $end 结束位置
     * @param int $op_id 操作员ID
     * @return array|false 导出数据
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
        
        $returnListData = kernel::single('ome_func')->exportDataMain(__CLASS__, $params);
        if (!$returnListData || !isset($returnListData['content']) || !isset($returnListData['title'])) {
            return false;
        }
        
        $data = array();
        $data['content'] = array();
        $data['content']['main'] = array();
        
        // 根据选择的字段定义导出的第一行标题
        if ($curr_sheet == 1) {
            $data['content']['main'][] = $this->getCustomExportTitle($returnListData['title']);
        }
        
        $items_columns = array_values($this->returnProductItemsExportTitle());
        
        $returnList = isset($returnListData['content']) ? $returnListData['content'] : array();
        $return_ids = !empty($returnList) ? array_column($returnList, 'return_id') : array();
        $main_columns = array_values($returnListData['title']);
        
        // 如果没有主表数据，只返回标题行（如果是第一页）
        if (empty($returnList)) {
            return $data;
        }
        
        // 获取所有的明细数据
        $return_items = array();
        if (!empty($return_ids)) {
            $return_items = $this->getexportdetail('*', array('return_id' => $return_ids));
        }
        
        foreach ($returnList as $returnRow) {
            $objects = isset($return_items[$returnRow['return_id']]) ? $return_items[$returnRow['return_id']] : array();
            $items_fields = implode(',', $items_columns);
            $all_fields = implode(',', $main_columns) . ',' . $items_fields;
            
            // 如果没有明细数据，直接输出主表信息
            if (empty($objects)) {
                $returnDataRow = $returnRow;
                $exptmp_data = array();
                foreach (explode(',', $all_fields) as $key => $col) {
                    if (isset($returnDataRow[$col])) {
                        $returnDataRow[$col] = mb_convert_encoding($returnDataRow[$col], 'GBK', 'UTF-8');
                        $exptmp_data[] = $this->escapeCsvField($returnDataRow[$col]);
                    } else {
                        $exptmp_data[] = '';
                    }
                }
                $data['content']['main'][] = implode(',', $exptmp_data);
                continue;
            }
            
            // 合并主表和明细数据
            if ($objects) {
                foreach ($objects as $obj) {
                    $returnDataRow = array_merge($returnRow, $obj);
                    $exptmp_data = [];
                    foreach (explode(',', $all_fields) as $key => $col) {
                        if (isset($returnDataRow[$col])) {
                            $returnDataRow[$col] = mb_convert_encoding($returnDataRow[$col], 'GBK', 'UTF-8');
                            $exptmp_data[] = $this->escapeCsvField($returnDataRow[$col]);
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
}
?>
