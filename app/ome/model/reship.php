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

class ome_mdl_reship extends dbeav_model{
    //是否有导出配置
    var $has_export_cnf = true;

    var $export_name = '退换货单';

    var $has_many = array(
       'reship_items' => 'reship_items'
    );
    //所用户信息
    static $__USERS = null;
    var $defaultOrder = array('t_begin DESC,reship_id DESC');

    var $is_check = array(
        0 => '未审核',
        1 => '审核成功',
        2 => '审核失败',
        3 => '收货成功',
        4 => '拒绝收货',
        5 => '拒绝',
        6 => '补差价',
        7 => '完成',
        8 => '质检通过',
        9 => '拒绝质检',
        10 => '质检异常',
      );
    private $expert_flag= false;
    #售后类型
    private $return_type = array (
            'return' => '退货',
            'change' => '换货',
            'refuse' => '拒收退货'
    );
    
    //import
    static $_import_order_bns = array();
    static public $_importBnPirces = array();
    
    /**
     * _filter
     * @param mixed $filter filter
     * @param mixed $tableAlias tableAlias
     * @param mixed $baseWhere baseWhere
     * @return mixed 返回值
     */
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
        
        if (isset($filter['bn'])) {
            $reshipItemModel = $this->app->model('reship_items');
            $rows = $reshipItemModel->getList('DISTINCT reship_id',array('bn|head'=>$filter['bn']));
            $reship_ids = array(0);
            foreach ($rows as $row) {
                $reship_ids[] = $row['reship_id'];
            }
            $where .=' AND reship_id IN('.implode(',',$reship_ids).')';
            unset($filter['bn']);
        }
        if (isset($filter['product_bn'])){
            $reshipId = array();
            $reshipId[] = 0;

            //多基础物料查询
            if($filter['product_bn'] && is_string($filter['product_bn']) && strpos($filter['product_bn'], "\n") !== false){
                $filter['product_bn'] = array_unique(array_map('trim', array_filter(explode("\n", $filter['product_bn']))));
            }

            //按基础物料查询相关退货单
            $reshipItemObj = $this->app->model('reship_items');
            $rows = $reshipItemObj->getReshipIdByFilterbnEq($filter);
            if($rows){
                foreach($rows as $row){
                    $temp_reship_id = $row['reship_id'];
                    $reshipId[$temp_reship_id] = $temp_reship_id;
                }
            }

            $where .= ' AND reship_id IN ("'.implode('","', $reshipId).'")';
            unset($filter['product_bn']);
        }
        if($filter['flag_type_text']) {
            if($filter['flag_type_text'] == 'ydt') {
                $where .= ' and (flag_type & '.ome_reship_const::__LANJIE_RUKU.')';
            } else {
                $where .= ' and (flag_type & '. ome_reship_const::__LANJIE_RUKU.')<>'.ome_reship_const::__LANJIE_RUKU;
            }
            unset($filter['flag_type_text']);
        }
        //服务单号和包裹号查询
        $processObj = app::get('ome')->model('return_process');//服务单号service_bn
        if (isset($filter['service_bn'])) {
            $row = $processObj->dump(array('service_bn'=>$filter['service_bn']),'reship_id');
            $reship_id = 0;
            if ($row) {
                $reship_id = $row['reship_id'];
            }
            $where .=' AND reship_id =' . $reship_id;
            unset($filter['service_bn']);
        }
        if (isset($filter['package_bn'])) {
            //包裹号package_bn
            $row = $processObj->dump(array('package_bn'=>$filter['package_bn']),'reship_id');
            $reship_id = 0;
            if ($row) {
                $reship_id = $row['reship_id'];
            }
            $where .=' AND reship_id =' . $reship_id;
            unset($filter['package_bn']);
        }
        if (isset($filter['return_type']) && $filter['return_type']=='tmallchange'){
            $where.=" AND source='matrix' && shop_type='tmall' AND return_type='change'";
            unset($filter['return_type']);
        }

        if (isset($filter['return_bn'])){
            $return_bn = str_replace(array("'", '"'), '', trim($filter['return_bn']));
            $return_detail = $this->db->selectrow("SELECT return_id FROM sdb_ome_return_product WHERE return_bn='".$return_bn."'");
            $where.=" AND return_id=".($return_detail['return_id'] ? : -1);
            unset($filter['return_bn']);
        }

        //退货单异常标识
        if (isset($filter['abnormal_status'])) {
            $filter['abnormal_status'] = kernel::single('ome_constants_reship_abnormal')->getBoolType(array('in'=>$filter['abnormal_status']));
        }
    
        if (isset($filter['flag_type_in']) && !isset($filter['flag_type'])) {
            $flagtype            = kernel::single('ome_reship_const')->getBoolType(array('in' => $filter['flag_type_in']));
            $filter['flag_type'] = $flagtype;
            unset($filter['flag_type_in']);
        }

        return parent::_filter($filter,$tableAlias,$baseWhere).$where;
    }

    /*
     * 获取退货单明细列表
     *
     * @param int $order_id 订单id
     *
     * @return array
     */
    function getItemList($reship_id){
        $reship_items = array();
        $items = $this->dump($reship_id,"reship_id",array("reship_items"=>array("*")));
        if($items['reship_items']){
            $reship_items = $items['reship_items'];
        }

        return $reship_items;
    }

    /*
     * 生成退货单号
     *
     *
     * @return 退货单号
     */
    function gen_id($returnType = 'reship'){
        $i = rand(0,9999);
        do{
            if(9999==$i){
                $i=0;
            }
            $i++;
            $reship_bn = ($returnType == 'change' ? 'HUAN' : '').date("YmdH").'13'.str_pad($i,6,'0',STR_PAD_LEFT);
            $row = $this->db->selectrow('select reship_bn from sdb_ome_reship where reship_bn =\''.$reship_bn.'\'');
        }while($row);
        return $reship_bn;
    }

    function searchOptions(){
        $parentOptions = parent::searchOptions();
        $childOptions = array(
            'order_bn'=>app::get('base')->_('订单号'),
            'return_bn'=>'售后申请单号',
            'bn' => '货号',
            'service_bn' => '京东服务单号',
            'package_bn' => '京东订单号',
        );
        return $Options = array_merge($childOptions,$parentOptions);
    }

    //创建/编辑 退换单
    function create_treship($adata, &$msg=null)
    {
        $reshipObj = app::get('ome')->model('reship');
        $returnProductMdl = $this->app->model('return_product');
        $oOperation_log = $this->app->model('operation_log');

        $branchLib = kernel::single('ome_branch');
        $reshipLib = kernel::single('ome_reship');

        //新建标识
        $is_create = false;
        
        //是否货到付款订单
        $is_cod_order = ($adata['is_cod_order'] == 'true' ? true : false);
        
        //组织新增或者编辑数据
        if($adata['delivery_id']) {
            if ($adata['source'] == 'archive'){
                $archive_delObj = kernel::single('archive_interface_delivery');
                $delivery = $archive_delObj->getDelivery(array('delivery_id'=>$adata['delivery_id']),'*');
            }else{
                $oDelivery = $this->app->model('delivery');
                $delivery = $oDelivery->dump($adata['delivery_id']);
            }
        }
        
        if ($adata['branch_id']) {
            $branch_id = $adata['branch_id'];
        }else{
            if ($adata['return']['branch_id']) {
                $return_branch = $adata['return']['branch_id'];
                $branch_id = current($return_branch);
            }
        }
        
        //操作员信息
        $opInfo = kernel::single('ome_func')->getDesktopUser();
        $problem_id = 0;
        if ($adata['problem_id']) {
            $problem_id = $adata['problem_id'];
        }
        
        //return
        $returnProductInfo = array();
        if($adata['return_id']){
            $returnProductInfo = $returnProductMdl->dump(array('return_id'=>$adata['return_id']), '*');
        }
        
        //平台platform_order_bn
        if($returnProductInfo['platform_order_bn'] && empty($adata['platform_order_bn'])){
            $adata['platform_order_bn'] = $returnProductInfo['platform_order_bn'];
        }
        
        //sdf
        $sdf_data = array(
            'return_id'        => $adata['return_id'],
            'reship_id'        => $adata['reship_id'],
            'order_id'         => $adata['order_id'],
            'member_id'        => $adata['member_id'],
            'return_logi_name' => $adata['return_logi_name'],
            'return_type'      => $adata['return_type'],
            'return_logi_no'   => $adata['return_logi_no'],
            'logi_name'        => $adata['logi_name'],
            'logi_no'          => $adata['logi_no'],
            'logi_id'          => $adata['logi_id'],
            'delivery_id'      => (int)$adata['delivery_id'],
            'ship_name'        => $adata['ship_name'],
            'ship_area'        => $adata['ship_area'],
            'delivery'         => $adata['delivery'],
            'ship_addr'        => $adata['ship_addr'],
            'ship_zip'         => $adata['ship_zip'],
            'ship_tel'         => $adata['ship_tel'],
            'ship_email'       => $adata['ship_email'],
            'ship_mobile'      => $adata['ship_mobile'],
            'memo'             => $adata['memo'],
            'status'           => 'ready',
            'op_id'            => $opInfo['op_id'],
            'is_protect'       => ($adata['is_protect'] ? $adata['is_protect'] : $delivery['is_protect']),  //是否报价
            'return'           => $adata['return'],
            'change'           => $adata['change'],
            'reship_bn'        => ( $adata['reship_bn'] ? $adata['reship_bn'] : $this->gen_id($adata['return_type']) ),
            'shop_id'          => ( $adata['shop_id'] ? $adata['shop_id'] : $delivery['shop_id'] ),
            'problem_id'       => $problem_id,
            'branch_id'        => $branch_id,
            'tmoney'          =>  $adata['tmoney'],
            'change_amount'   =>  floatval($adata['change_amount']),
            'flag_type'       =>  (int)$adata['flag_type'],
            'platform_status' => $adata['platform_status'], //平台售后状态
            'platform_order_bn' => $adata['platform_order_bn'], //平台platform_order_bn
            'refund_shipping_fee'   => $adata['refund_shipping_fee'] ?  $adata['refund_shipping_fee'] :0,
        );
        
        if($adata['cos_id']){
            $sdf_data['cos_id'] = $adata['cos_id'];
        }
        
        if($adata['betc_id']){
            $sdf_data['betc_id'] = $adata['betc_id'];
        }
        
        $totalmoney = $adata['tmoney'] - $sdf_data['change_amount'];
        $sdf_data['totalmoney'] = $totalmoney;

        if(empty($sdf_data['shop_id'])) {
            $msg = '店铺信息为空!'; return false;
        }

        if ($adata['source']) $sdf_data['source'] = $adata['source'];
        
        if ($adata['return_type'] == 'change') {
            $sdf_data['changebranch_id'] = $adata['changebranch_id'] ? $adata['changebranch_id'] : $branch_id;
        } else {
            if($returnProductInfo['changebranch_id']) {
                $sdf_data['changebranch_id'] = $returnProductInfo['changebranch_id'];
            }
        }
        
        if ($adata['source'] == 'archive') {
            $sdf_data['archive'] = '1';
            $sdf_data['source'] = 'archive';
        }
        
        $oShop = $this->app->model('shop');
        $shop_info = $oShop->getShopInfo($sdf_data['shop_id']);
        $sdf_data['shop_type'] = $shop_info['shop_type'];

        // 经销店铺的单据，delivery_mode冗余到售后申请表
        if ($shop_info['delivery_mode'] == 'jingxiao') {
            $sdf_data['delivery_mode'] = $shop_info['delivery_mode'];
        }
        
        $needFreeze = false;
        if($sdf_data['reship_id']) {//编辑
            $res = kernel::single('console_reship')->releaseChangeFreeze($sdf_data['reship_id']);
            if ($res[0] == false) {
                $msg = '编辑退换单释放冻结失败：'.$res[1]['msg'];
                return false;

            } elseif ($res[1]['msg']!='没用预占明细') {
                $needFreeze = true;
            }
            $add_operation = '编辑';
        }else{//新建
            $sdf_data['t_begin'] = time();
            if ($adata['source'] == 'archive'){
                $orderObj = app::get('archive')->model('orders');
                $orderInfo = $orderObj->getList('order_id,order_bn,org_id',array('order_id'=>$adata['order_id']), 0, 1);
            }else{
                $orderObj = $this->app->model('orders');
                $orderInfo = $orderObj->getList('order_id,order_bn,org_id',array('order_id'=>$adata['order_id']), 0, 1);
            }
            
            $sdf_data['org_id'] = $orderInfo[0]['org_id'];
            $add_operation = '新建';

            $is_create = true;
            
            //通过order_bn获取根订单信息
            if(empty($sdf_data['platform_order_bn']) && $orderInfo[0]){
                $orderLib = kernel::single('ome_order');
                $rootOrderInfo = $orderLib->getRootOrderInfo($orderInfo[0]);
                if($rootOrderInfo){
                    //根订单号
                    $sdf_data['platform_order_bn'] = $rootOrderInfo['root_order_bn'];
                }
            }
        }

        $return = $sdf_data['return'];
        if ($branch_id) {
            $return['branch_id'] = $branch_id;
        }

        $change = $sdf_data['change'];
        unset($sdf_data['return'],$sdf_data['change']);
        if ($adata['reship_id'] && $adata['return_type'] == 'change') {
            $oldchange = kernel::single('ome_return_rchange')->getChangelist($adata['reship_id'],$adata['changebranch_id']);
        }

        //防止天猫平台同分同秒推送2次,生成2条退换货单
        if(!$adata['reship_id'] && $adata['return_id']){
            //创建前判断是否已生成
            $reship_tmp = $reshipObj->dump(array('return_id'=>$adata['return_id']), 'reship_id,shop_type,is_check');
            if($reship_tmp['shop_type']=='luban' && $reship_tmp['is_check']=='5'){
                //场景：抖音平台拒绝售后申请后,允许顾客编辑后重新发起售后申请

            }elseif ($reship_tmp){
                $msg = '售后申请退货单已存在,不能重复生成';
                //return false;
            }
        }
        
        //新建/编辑reship
        if($this->save($sdf_data)){
            # 保存退换货单明细
            $oReship_items = $this->app->model('reship_items');
            $result = $this->save_product_items($return,$sdf_data['reship_id'],$oReship_items,'return',$sdf_data['return_id']);
            if ($result['status'] != 'succ') {
              $msg = $result['msg']; return false;
            }
            
            //保存换出商品明细
            if ($sdf_data['return_type'] == 'change'){
                $change['shop_id'] = $sdf_data['shop_id'];
                $change['changebranch_id'] = $sdf_data['changebranch_id'];
                
                //save
                $result = $this->save_product_items($change,$sdf_data['reship_id'],$oReship_items,'change');
                if ($result['status'] != 'succ') {
                    $msg = $result['msg']; return false;
                }
                
                //判断是否有货品删除
                if ($oldchange) {
                    $this->_deletechange_item($sdf_data['reship_id'],$change,$oldchange);
                }
            }

            //操作日志
            $memo = $add_operation.'退换货单,单号为:'.$sdf_data['reship_bn'];
            $oOperation_log->write_log('reship@ome',$sdf_data['reship_id'],$memo);
            foreach(kernel::servicelist('ome.service.reship.create.after') as $object) {
                if(method_exists($object, 'after_create')){
                    $object->after_create($sdf_data['reship_id']);
                }
            }
            //存在相关的售后单 更新相关字段 为了售后问题类型的统计添加的字段(problem_id)，并且给该字段赋值
            if($sdf_data['return_id']){
                $oProduct = $this->app->model('return_product');
                $oProduct_problem_id = array(
                    'return_id'  => $sdf_data['return_id'],
                    'tmoney'     => $sdf_data['tmoney'],
                    'problem_id' => $adata['problem_type'][0],
                );
                
                //平台退货地址ID
                if($adata['address_id']){
                    $oProduct_problem_id['address_id'] = $adata['address_id'];
                }
                
                $oProduct->save($oProduct_problem_id);
            } else {
                kernel::single('console_reship')->reshipToReturn($sdf_data);
            }
            
            //[货到付款]订单打标
            if($is_create && $is_cod_order){
                $flag_type = ome_reship_const::__ISCOD_ORDER;
                $sql = "UPDATE sdb_ome_reship SET flag_type=flag_type | ". $flag_type ." WHERE reship_id=".$sdf_data['reship_id'];
                $reshipObj->db->exec($sql);
            }
            if ($needFreeze && ($sdf_data['return_type'] == 'change' || $sdf_data['changebranch_id'])) {
                $error_msg = '';
                $result = kernel::single('console_reship')->addChangeFreeze($sdf_data['reship_id'], $error_msg);
                if(!$result){
                    //log
                    $oOperation_log->write_log('reship@ome', $sdf_data['reship_id'], '换货预占库存失败:'. $error_msg);
                }
            }
            //[京东云交易]保存退货单与京东包裹关系明细
            $wms_type = $branchLib->getNodetypBybranchId($branch_id);
            if($wms_type == 'yjdf' && $is_create){
                $error_msg = '';
                $result = $reshipLib->create_reship_package($sdf_data, $error_msg);
                if(!$result){
                    $msg = $error_msg;

                    //log
                    $oOperation_log->write_log('reship@ome', $sdf_data['reship_id'], '创建退货包裹失败：'.$msg);

                    return false;
                }
            }

            // 物流单号重复检测打标（flag_type 位）
            if (!empty($sdf_data['reship_id']) && !empty($sdf_data['return_logi_no'])) {
                kernel::single('ome_reship')->markDuplicateReturnLogiNo($sdf_data['reship_id'], $sdf_data['return_logi_no']);
            }

            $msg = $add_operation.'退换货单成功，请等待审核!';
            return $sdf_data['reship_bn'];
        }else{
            $msg = $add_operation.'退换货单失败.';
            return false;
        }
  }

    /**
     * 保存退货明细
     * 将退入，换出商品分别存入reship_items表中
     * @param array $param ,$type
     * @return void
     * @author
     * */
  function save_product_items($param,$reship_id,$object,$type = 'return',$return_id='')
  {
      $shipObj = app::get('ome')->model('reship_objects');
      $itemsObj = app::get('ome')->model('reship_items');
      $oReturn_items = $this->app->model('return_product_items');
      
      $opInfo = kernel::single('ome_func')->getDesktopUser();
      
      $rs = array('status'=>'succ','msg'=>'保存成功！');
      
    # 保存退货及已有的换货明细
    if ($type == 'return' && $param['goods_bn'] && is_array($param['goods_bn']) ){
        //获取数据库中的退货数据
        $rs_return_data = $object->getList("bn,product_id,item_id",array("reship_id"=>$reship_id,"return_type"=>"return"));
        $current_return_bn_data = array();
        if(!empty($rs_return_data)){ //编辑过来的 有数据的
          foreach($rs_return_data as $var_rd){
              $current_return_bn_data[$var_rd["item_id"]] = $var_rd["item_id"];
           }
        }
        
      foreach ($param['goods_bn'] as $key => $bn) {
        $item = array(
          'reship_id'    => $reship_id,
          'product_name' => $param['goods_name'][$bn],
          'bn'           => $param['bn'][$bn],
          'num'          => $param['num'][$bn],
          'product_id'   => $param['product_id'][$bn],
          'price'        => $param['price'][$bn],
          'amount'       => (float)$param['amount'][$bn],
          'return_type'  => $type,
          //'branch_id'    => $param['branch_id'],
          'op_id'        => $opInfo['op_id'],
          'item_id'      => $param['item_id'][$bn],
          'order_item_id'=>$bn,
        );
        if ($type == 'return') {
            // branch_id
            if(is_array($param['branch_id'])){
                $item['branch_id'] = isset($param['branch_id'][$bn]) ? intval($param['branch_id'][$bn]) : 0;
            }else{
                $item['branch_id'] = intval($param['branch_id']);
            }
            
            if($param['shop_goods_bn'][$bn]){
                $item['shop_goods_bn'] = $param['shop_goods_bn'][$bn];
            }
            if($param['obj_type'][$bn]){
                $item['obj_type'] = $param['obj_type'][$bn];
            }
            if($param['quantity'][$bn]){
                $item['quantity'] = $param['quantity'][$bn];
            }
            
        }else{
            // branch_id
            if(is_array($param['branch_id'])){
                $item['branch_id'] = isset($param['branch_id'][$bn]) ? intval($param['branch_id'][$bn]) : 0;
            }else{
                $item['branch_id'] = intval($param['branch_id']);
            }
        }
        $result = $object->save($item);
        if (!$result) {
          return array('status'=>'fail','msg'=>'插入退货商品【'.$bn.'】时失败！');
        }
        if(!empty($current_return_bn_data) && isset($current_return_bn_data[$param['item_id'][$bn]])){
            unset($current_return_bn_data[$param['item_id'][$bn]]);
        }
        if ($type == 'return' && $return_id) {
          $updateData = array('num' => $item['num']);
          $updateFilter = array('return_id'=>$return_id,'product_id'=>$item['product_id']);
            // custom 兼容一笔订单存在多条同基础物料明细的情况, 更新条件补充order_item_id
            if ($item['order_item_id']) {
                $updateFilter['order_item_id'] = $item['order_item_id'];
            }
          $oReturn_items->update($updateData,$updateFilter);
        }
      }
      
      //未打钩的已保存的明细做删除处理
      if(!empty($current_return_bn_data)){
          foreach($current_return_bn_data as $key_bn => $value_product_id){
              $object->delete(array('item_id' => $value_product_id,"reship_id"=>$reship_id));

          }
      }
    }

    //[换货]保存新增的商品
    if ($type=='change' && $param['objects']){
        foreach ( $param['objects'] as $changeobj )
        {
            #销售物料层
            $obj    = array();
            $obj['reship_id'] = $reship_id;
            $obj['obj_type'] = $changeobj['obj_type'];
            $obj['product_id'] = $changeobj['product_id'];
            $obj['bn'] = $changeobj['bn'];
            $obj['product_name'] = $changeobj['name'];
            $obj['price'] = $changeobj['price'];
            $obj['num'] = $changeobj['num'];
            
            //obj_id
            if ($changeobj['item_id']){
                $obj['obj_id'] = $changeobj['item_id'];
            }
            
            $shipObj->save($obj);
            
            //新增或者编辑都会存在主键id 不存在说明obj表数据异常
            if(!$obj["obj_id"]){
                return array('status'=>'fail','msg'=>'插入换货销售物料号【'. $changeobj['bn'] .'】时失败！');
            }
            
            if ($changeobj['obj_type'] == 'pkg'){
                $salesMLib = kernel::single('material_sales_material');
                $salesMInfo = $salesMLib->getSalesMByBn($param['shop_id'],$changeobj['bn']);
                $basicMInfos = $salesMLib->getBasicMBySalesMId($salesMInfo['sm_id']);
                $salesMLib->calProSaleMPriceByRate($changeobj['price'], $basicMInfos);
                
                $arrBn = array();
                foreach($basicMInfos as $v){
                  $arrBn[$v['material_bn']] = $v['rate_price'];
                }
            }elseif($changeobj['obj_type'] == 'lkb'){
                //福袋组合
                $luckybagLib = kernel::single('material_luckybag');
                
                //获取基础物料价格
                $arrBn = [];
                $error_msg = '';
                $basicMInfos = $luckybagLib->getReshipMaterialPrices($changeobj, $error_msg);
                if($basicMInfos){
                    //获取均摊的单价price
                    foreach($basicMInfos as $basicVal)
                    {
                        $arrBn[$basicVal['material_bn']] = $basicVal['avg_price'];
                    }
                }
            }
            
            //items
            foreach ((array)$changeobj['items'] as $item)
            {
                $item['reship_id']      = $reship_id;
                $item['obj_type']       = $changeobj['obj_type'];
                $item['product_id']     = $item['bm_id'];
                $item['bn']             = $item['material_bn'];
                $item['product_name']   = $item['material_name'];
                $item['num']            = $item['change_num'];
                $item['return_type']    = 'change';
                
                //changebranch_id
                if (!$item['changebranch_id']){
                    $item['changebranch_id'] = $param['changebranch_id'];
                }
                
                //操作人
                $item['op_id']    = $opInfo['op_id'];

                //销售物料的价格
                if($changeobj['obj_type'] == 'pkg'){
                    $item['price'] = $arrBn[$item['material_bn']];
                }elseif($changeobj['obj_type'] == 'lkb'){
                    //福袋均摊价格
                    if(isset($arrBn[$item['material_bn']])){
                        $item['price'] = $arrBn[$item['material_bn']];
                    }else{
                        $item['price'] = 0;
                    }
                }else{
                    $item['price'] = $changeobj['price'];
                }
                
                //obj_id
                $item['obj_id']    = $obj['obj_id'];

                //判断插入还是更新
                $item_detail    = $itemsObj->dump(array('obj_id'=>$item['obj_id'], 'bn'=>$item['bn']), 'item_id');
                if ($item_detail['item_id']){
                    $item['item_id']    = $item_detail['item_id'];
                }
                
                $itemsObj->save($item);
            }
        }
    }

    return $rs;
  }
  
    /**
     * 获取售后明细
     * 
     * @return void
     * @author
     * */
    function getReshipItems($reship_id)
    {
      $Oreships = $this->app->model('reship_items');
      $oOrders = $this->app->model('orders');
      $Oreship_items = $Oreships->getList('*',array('reship_id'=>$reship_id),0,1);
      $reshipitems = $this->dump(array('reship_id'=>$reship_id),'*');
      $orders = $oOrders->dump($reshipitems['order_id'],'order_bn');
      if (!$orders['order_bn']) {

        $archive_ordObj = kernel::single('archive_interface_orders');
        $orders = $archive_ordObj->getOrders(array('order_id'=>$reshipitems['order_id']),'order_bn');
      }
      $reshipitems['order_bn'] =$orders['order_bn'];
      $reshipitems['items'][] = $Oreship_items[0];
      return $reshipitems;
    }

   /**
     * 获取审核发货单信息
     * 
     * @return void
     * @author
     * */
   function getCheckinfo($reship_id,$transform=true)
   {
       $basicMaterialLib    = kernel::single('material_basic_material');
       $libBranchProduct    = kernel::single('ome_branch_product');

        $oOrders = $this->app->model ('orders');
        $oMember = $this->app->model('members');
        $oDc = $this->app->model('dly_corp');
        $oReship_item = $this->app->model ( 'reship_items' );
        $Oreturn_products = $this->app->model('return_product');
        $orderItemMdl = app::get('ome')->model('order_items');
        
        $reship_data = $this->dump(array('reship_id'=>$reship_id));
        $reship_data['return_logi_id'] = $reship_data['return_logi_name'];
       // $dc_data = $oDc->dump($reship_data['return_logi_name']);
        $order_data = $oOrders->dump($reship_data['order_id']);

        if ($reship_data['change_order_id']) {
            $change_order_data = $oOrders->dump($reship_data['change_order_id']);
            if ($change_order_data) {
                $reship_data['memo'] .= ' 换货订单号:'.$change_order_data['order_bn'];
            }
        }

        $archive_ordObj = kernel::single('archive_interface_orders');
        if ($reship_data['archive']=='1' || ($reship_data['source'] && in_array($reship_data['source'],array('archive'))) || !$order_data) {
            $oReship_item = $oOrders = kernel::single('archive_interface_orders');
            $order_data = $archive_ordObj->getOrders(array('order_id'=>$reship_data['order_id']),'*');
            unset($order_data['source']);
        }
        unset($order_data['source'],$order_data['archive']);
        $member = $oMember->dump(array('member_id'=>$order_data['member_id']));
        $oBranch=$this->app->model('branch');
        if($transform){
           //$reship_data['return_logi_name'] = $dc_data['name'];
           $rd = explode(':', $reship_data['ship_area']);
           if($rd[1]){
             $reship_data['ship_area'] = str_replace('/', '-', $rd[1]);
           }
        }

        $reship_item = $this->getItemList($reship_id);
        $reship_data = array_merge($reship_data,$order_data);
        $rp = $Oreturn_products->dump(array('return_id'=>$reship_data['return_id']));
        $reship_data['title'] = $rp['title'];
        $reship_data['member_id'] = $member['account']['uname'];
        $reship_data['content'] = $rp['content'];
        $reship_data['return_memo'] = $rp['memo'];
        if ($reship_data['branch_id']) {
            $branchs = $oBranch->db->selectrow("SELECT name,branch_id FROM sdb_ome_branch WHERE branch_id=".$reship_data['branch_id']."");
            $reship_data['branch_name'] = $branchs['name'];
            unset($branchs);
        }
        
        //obj_type
        $objTypeList = $orderItemMdl->_obj_alias;
        
        //获取订单obj层信息
        $orderItemList = array();
        $orderItemIds = array_column($reship_item, 'order_item_id');
        if($orderItemIds){
            $orderLib = kernel::single('ome_order');
            $orderItemList = $orderLib->getOrderItemByItemIds($orderItemIds);
        }
        
        //items
        $lucky_flag = false;
        if($reship_item){
          $recover = array(); $tmoney = 0;
          foreach ($reship_item as $key => $value) {
            $branchs = $oBranch->db->selectrow("SELECT name,branch_id FROM sdb_ome_branch WHERE branch_id='".$value['branch_id']."'");
            $reship_item[$key]['branch_id'] = $branchs['branch_id'];
            $reship_item[$key]['branch_name'] = $branchs['name'];
            $reship_item[$key]['amount'] = $reship_item[$key]['amount'] > 0 ? $reship_item[$key]['amount'] : sprintf('%.2f',$reship_item[$key]['num'] * $reship_item[$key]['price']);

            $product    = $basicMaterialLib->getBasicMaterialExt($value['product_id']);

            $reship_item[$key]['spec_info'] = $product['specifications'];
            
            //销售物料类型名称
            $obj_type = $value['obj_type'];
            $reship_item[$key]['obj_type_name'] = ($obj_type ? $objTypeList[$obj_type] : '');
            
            //关联的订单object层信息
            $order_item_id = $value['order_item_id'];
            if(isset($orderItemList[$order_item_id])){
                $orderItemInfo = $orderItemList[$order_item_id];
                
                //销售物料编码
                $reship_item[$key]['sales_material_bn'] = $orderItemInfo['sales_material_bn'];
                
                //福袋组合编码
                $reship_item[$key]['combine_bn'] = $orderItemInfo['combine_bn'];
                
                //福袋组合编码
                if($orderItemInfo['combine_bn']){
                    $lucky_flag = true;
                }
            }
            
            //return_type
            if($value['return_type'] == 'return'){
                 $refund = $oReship_item->Get_refund_count( $reship_data['order_id'], $value['bn'] ,$reship_id,$value['order_item_id']);
                 $reship_item[$key]['effective'] = $refund;
                 $recover['return'][] = $reship_item[$key];
                 $recover['total_return_filter'][] = $product['bm_id'];

                 # 计算应退金额
                 if ($order_data["pay_status"] == "5" && !$reship_data['had_refund']){ //全额退款的不计算应退金额 直接拿默认的0
                 }else{
                     $tmoney += $value['price'] * $value['num'];
                 }
            }else{
                //作判断如果是待确认时,审核剩余数量不减冻结
                $refund=0;
                 $refund = $libBranchProduct->get_product_store( $value['branch_id'],$value['product_id'] );
                 if ($reship_data['is_check'] == '11' && $value['return_type'] == 'change') {
                    $refund+=$value['num'];
                }
                 $reship_item[$key]['effective'] = $refund;
                 $recover['change'][] = $reship_item[$key];
                 $recover['total_change_filter'][] = $product['bm_id'];
            }
          }

          $reship_data = array_merge($reship_data,$recover);
          //$reship_data['tmoney'] = ($reship_data['tmoney']!='0.000')?$reship_data['tmoney']:$reship_data['total_amount'];
          $reship_data['tmoney'] = kernel::single('eccommon_math')->getOperationNumber($tmoney);

          $reship_data['total_return_filter'] = is_array($reship_data['total_return_filter']) ? implode(',', $reship_data['total_return_filter']) : '';
          $reship_data['total_change_filter'] = is_array($reship_data['total_change_filter']) ? implode(',', $reship_data['total_change_filter']) : '';
        }
        
        //lucky_flag
        $reship_data['lucky_flag'] = $lucky_flag;
        
        return $reship_data;
   }
   
   //店铺类型
       /**
     * modifier_shop_type
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function modifier_shop_type($row)
   {
       $shopTypeList = ome_shop_type::get_shop_type();
       
       return $shopTypeList[$row];
   }
   
    /**
     * modifier_return_logi_name
     * @param mixed $val val
     * @return mixed 返回值
     */
    public function modifier_return_logi_name( $val ) {
        $oDc = $this->app->model('dly_corp');
        $dc_data = $oDc->dump($val);
        if($dc_data['name']){;
          return $dc_data['name'];
        }else{
          return $val;
        }
    }


   /**
    * 保存入库单信息
    * status 状态：
    *       5: 拒绝 生成一张发货单 商品明细为退入商品中的商品信息
    *       6：补差价，生成一张未付款的支付单
    *       8: 操作完成
    * @return void
    * @author
    * */
    public function saveinfo($reship_id,$data,$status,$api=false){

        $memo = '';
        $reship_num=0;//退货单数量
        $order_num=0;//退货单数量
        $oDc = $this->app->model('dly_corp');
        $Oreturn_products = $this->app->model('return_product');
        $reshipinfo = $this->dump(array('reship_id'=>$reship_id),'*');
        $oReship_items = $this->app->model('reship_items');
        $reship_items = $oReship_items->getList('*',array('reship_id'=>$reship_id));
        $reshipinfo['return_logi_id'] = $reshipinfo['return_logi_id'];
        $dc_data = $oDc->dump($reshipinfo['return_logi_name']);
        $reshipinfo['return_logi_name'] = $dc_data['name'];

        switch ($status){
          case '6':
            $aData['return_id'] = $data['return_id'];
            $aData['reship_id'] = $reship_id;
            $aData['memo'] = $data['dealmemo'];
            $aData['money'] = $data['totalmoney'];
            $aData['tmoney']=$data['tmoney'];
            $aData['bmoney']=$data['bmoney'];
            $aData['had_refund']=$data['had_refund'];
            $aData['is_check']='6';
            //补差价
            //增加售后日志
            $memo.= '售后服务：补差价(￥'.-(float)$data['totalmoney'].')';
            $this->update($aData,array('reship_id'=>$reship_id));
            if($reshipinfo['return_id']){
                unset($aData['reship_id'],$aData['is_check']);
                $aData['status']='8';
                $Oreturn_products->update($aData,array('return_id'=>$reshipinfo['return_id']));
            }

          break;
        }
        
        /*日志描述start*/
        if($reship_num!=0){
            $memo .= ' 生成了'.$reship_num.'张退货单,';
        }
        if($order_num!=0){
            $memo .= ' 生成了'.$order_num.'张订单';
        }
        $oOperation_log = $this->app->model('operation_log');//写日志
        if($data['return_id']){
           $oOperation_log->write_log('return@ome',$data['return_id'],$memo);
        }
        $oOperation_log->write_log('reship@ome',$reship_id,$memo);
        return true;
    }

  /*
    * 生成发货单
    * @param array $adata
    * return int
    */
   function create_delivery($adata)
   {
       $oDelivery = $this->app->model('delivery');

       $delivery_sdf=array(
           'branch_id'=>$adata['branch_id'],
           'is_protect'=>$adata['is_protect'],
           'delivery' => $adata['delivery'],
           'logi_id'=>$adata['logi_id'],
           'logi_name'=>$adata['logi_name'],
           'op_id'=>kernel::single('desktop_user')->get_id(),
           'create_time'=>time(),
           'delivery_cost_actual' => $adata['delivery_cost_actual'] ? $adata['delivery_cost_actual'] : 0,
           'type'=>'reject',
           'delivery_items' =>$adata['delivery_items'],
        );
       $adata['ship_area'] = str_replace('-', '/', $adata['ship_area']);
       kernel::single('eccommon_regions')->region_validate($adata['ship_area']);
        $ship_info=array(
           'name' => $adata['ship_name'],
           'area' => $adata['ship_area'],
           'addr' => $adata['ship_addr'],
           'zip' => $adata['ship_zip'],
           'telephone' =>$adata['ship_tel'],
           'mobile' =>$adata['ship_mobile'],
           'email' => $adata['ship_email']
          );

        $result=$oDelivery->addDelivery($adata['order_id'],$delivery_sdf,$ship_info);
        $delivery_bn = $oDelivery->dump(array('delivery_id'=>$result['data']),'delivery_bn');
        $delivery_bn = $delivery_bn['delivery_bn'];

        return $delivery_bn;
   }

   /*
    *  售后服务换货生成订单
    * @param $reshipinfo 退换货单信息,退换货单商品信息
    * return $new_order_id
    *
    *
    */
   function create_order($reshipinfo){
        $oOrder = $this->app->model('orders');
        $extendObj = app::get('ome')->model('order_extend');
        
        $rchangeObj = kernel::single('ome_return_rchange');
        $reshipLib = kernel::single('ome_reship');
        
        //setting
        $tostr = '';
        $itemnum = 0;
        
        //check
        $extend_detail = $extendObj->dump(array('orig_reship_id'=>$reshipinfo['reship_id']),'order_id');
        if ($extend_detail){
          return false;
        }
        
        //检测换出商品：销售物料关联的基础物料是否被删除;
        $error_msg = '';
        $checkResult = $reshipLib->formatReshipRchangeItems($reshipinfo, $error_msg);
        
        //获取换出商品明细
        $reship_items = $rchangeObj->getChangelist($reshipinfo['reship_id'],$reshipinfo['changebranch_id']);
        
        $order_bn = strpos($reshipinfo['reship_bn'], 'HUAN') !== false ? $reshipinfo['reship_bn'] : $oOrder->gen_id('change');
         if ($reshipinfo['source'] == 'archive') {
            $archive_ordObj = kernel::single('archive_interface_orders');
            $Order_detail = $archive_ordObj->getOrders(array('order_id'=>$reshipinfo['order_id']),'*');
        }else{
            $Order_detail = $oOrder->dump($reshipinfo['order_id']);
        }

        if($reshipinfo['ship_area']!=''){
           $reshipinfo['ship_area'] = str_replace('-', '/', $reshipinfo['ship_area']);
           kernel::single('eccommon_regions')->region_validate($reshipinfo['ship_area']);
           $ship_area = $reshipinfo['ship_area'];
        }else{
           $ship_area = $Order_detail['consignee']['area'];
        }

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
            'shop_id'=>$reshipinfo['shop_id'],
            'itemnum'=>$itemnum,
            'relate_order_bn'=>$Order_detail['order_bn'],
            'shipping'=>array(
                'shipping_id'=>$Order_detail['shipping']['shipping_id'],
                'is_cod'=>'false',
                'shipping_name'=>$Order_detail['shipping']['shipping_name'],
                'cost_shipping'=>$reshipinfo['cost_freight_money'],
                'is_protect'=>$Order_detail['shipping']['is_protect'],
                'cost_protect'=>0,
            ),
           'consignee'=>array(
               'name'=>$reshipinfo['ship_name']  ? $reshipinfo['ship_name'] :$Order_detail['consignee']['name'],
               'addr'=>($reshipinfo['ship_addr']!='')?$reshipinfo['ship_addr']:$Order_detail['consignee']['addr'],
               'zip'=>($reshipinfo['ship_zip']!='')?$reshipinfo['ship_zip']:$Order_detail['consignee']['zip'],
               'telephone'=>($reshipinfo['ship_tel']!='')?$reshipinfo['ship_tel']:$Order_detail['consignee']['telephone'],
               'mobile'=>($reshipinfo['ship_mobile']!='')?$reshipinfo['ship_mobile']:$Order_detail['consignee']['mobile'],
               'email'=>($reshipinfo['ship_email']!='')?$reshipinfo['ship_email']:$Order_detail['consignee']['email'],
               'area'=>$ship_area,
               'r_time'=>$Order_detail['consignee']['r_time'],
            ),
            'mark_type' => 'b1',
            'source' => 'local',
            'createway' => 'after',
            'is_tax' => $Order_detail["is_tax"],
            'tax_title' => $Order_detail["tax_title"],
            'shop_type' => $Order_detail["shop_type"],
        );
        
        //平台订单号
        if($reshipinfo['platform_order_bn']){
            $order_sdf['platform_order_bn'] = $reshipinfo['platform_order_bn'];
        }
        
        if(in_array($order_sdf['shop_type'],array('website','youzan'))  && $reshipinfo['source'] =='matrix'){
            $order_sdf['order_bn'] = $reshipinfo['reship_bn'];
        }
        //delivery_mode
        $shop = app::get('ome')->model('shop')->db_dump(['shop_id'=>$reshipinfo['shop_id']], 'delivery_mode');
        if($shop['delivery_mode'] == 'jingxiao') {
            $order_sdf['order_type'] = 'platform';
        }
        $needDecrypt = true;
        $exRs = kernel::single('ome_return_rchange')->dealExchangeEncrypt($reshipinfo['return_id']);
        if($exRs['rs']) {
            $order_sdf['order_bn'] = $reshipinfo['reship_bn'];
            $order_sdf['consignee'] = $exRs['data']['consignee'];
            $order_sdf['order_source'] = 'platformexchange';
            if($exRs['data']['encrypt_source_data']) {
                $order_receiver = ['encrypt_source_data'=>$exRs['data']['encrypt_source_data']];
                $needDecrypt = false;
            }
        }
        // 敏感数据解密
        if ($needDecrypt) {
            $decrypt_data = kernel::single('ome_security_router',$Order_detail['shop_type'])->decrypt(array (
              'ship_tel'    => $order_sdf['consignee']['telephone'],
              'ship_mobile' => $order_sdf['consignee']['mobile'],
              'ship_addr'   => $order_sdf['consignee']['addr'],
              'ship_name'   => $order_sdf['consignee']['name'],
              'shop_id'     => $order_sdf['shop_id'],
              'order_bn'    => $Order_detail['order_bn'],
            ), 'order');
            if ($decrypt_data['ship_tel']) $order_sdf['consignee']['telephone'] = $decrypt_data['ship_tel'];
            if ($decrypt_data['ship_mobile']) $order_sdf['consignee']['mobile'] = $decrypt_data['ship_mobile'];
            if ($decrypt_data['ship_addr'])   $order_sdf['consignee']['addr']   = $decrypt_data['ship_addr'];
            if ($decrypt_data['ship_name']) $order_sdf['consignee']['name']     = $decrypt_data['ship_name'];
        }
        $mark_text = array(
          array(
            'op_name' => 'system',
            'op_time' => time(),
            'op_content' => '售后换货，创建的换出订单。要求换货的订单('.$Order_detail['order_bn'].')',
          ),
        );
        if ($reshipinfo['memo']) {
          $user = app::get('desktop')->model('users')->getList('name',array('user_id' => $reshipinfo['op_id']),0,1);
          $mark_text[] = array(
            'op_name' => $user[0]['name'],
            'op_time' => time(),
            'op_content' => $reshipinfo['memo'],
          );
        }
        $order_sdf['mark_text'] = $mark_text;
        $tostr=array();
        list($actually_amount, $settlement_amount) = $this->getActuallyAmount($reshipinfo['reship_id']);
        // 按各行 sale_price（num*price）均摊实付与结算金额，与 ome_order::calculate_part_porth 一致
        $orderLib = kernel::single('ome_order');
        $divideRows = array();
        foreach ($reship_items as $objKey => $row) {
            $divideRows[$objKey] = array(
                'sale_price' => bcmul(strval($row['num']), strval($row['price']), 10),
            );
        }
        $porth_total = '0';
        foreach ($divideRows as $r) {
            if (floatval($r['sale_price']) > 0) {
                $porth_total = bcadd($porth_total, $r['sale_price'], 10);
            }
        }
        if (floatval($porth_total) > 0) {
            $divideRows = $orderLib->calculate_part_porth($divideRows, array(
                'part_total' => $actually_amount,
                'part_field' => 'actually_amount',
                'porth_field' => 'sale_price',
                'minuend_field' => null,
            ));
            $divideRows = $orderLib->calculate_part_porth($divideRows, array(
                'part_total' => $settlement_amount,
                'part_field' => 'settlement_amount',
                'porth_field' => 'sale_price',
                'minuend_field' => null,
            ));
        } else {
            foreach ($divideRows as $k => $_) {
                $divideRows[$k]['actually_amount'] = '0.00';
                $divideRows[$k]['settlement_amount'] = '0.00';
            }
        }
        //[销售物料层]格式化订单明细
        $item_cost = 0;
        foreach ( $reship_items as $objKey => &$items )
        {
            unset($items['reship_id'],$items['type'],$items['branch_id'],$items['item_id'],$items['obj_id']);

            if(($items['item_type'] != 'pkg') && ($items['item_type'] != 'lkb') && ($items['item_type'] != 'gift') && ($items['item_type'] != 'pko'))
            {
                $items['item_type']    = 'goods';
            }

            $sales_detail = app::get('material')->model('sales_material')->dump(array('sales_material_bn'=>$items['bn']),'sm_id');
            $items['obj_alias']   = $items['obj_type'] = $items['item_type'];
            $items['goods_id']    = $sales_detail['sm_id'];
            $items['name']        = $items['product_name'];
            $items['quantity']    = $items['num'];
            $items['amount']      = $items['sale_price'] = $items['num'] * $items['price'];
            $items['actually_amount'] = $divideRows[$objKey]['actually_amount'];
            $items['settlement_amount'] = $divideRows[$objKey]['settlement_amount'];
            if($order_sdf['order_type'] == 'platform') {
                $items['is_sh_ship'] = 'true';
            }
            $tostr[]=array("name"=>$items['product_name'],"num"=>$items['num']);
            
            //基础物料层
            $order_items    = array();
            foreach ($items['items'] as $key_i => $item_row)
            {
                #注销无用变量
                unset($item_row['item_id'], $item_row['obj_id'], $item_row['branch_id'], $item_row['number'], $item_row['quantity']);

                #注销基础物料变量
                unset($item_row['sm_id'], $item_row['bm_id'], $item_row['material_name'], $item_row['material_bn']);

                $item_row['goods_id']    = $items['goods_id'];

                $item_row['name']        = $item_row['product_name'];
                $item_row['quantity']    = $item_row['change_num'];//换货数量
                $item_row['amount']      = $item_row['sale_price'] = $item_row['change_num'] * $item_row['price'];
                
                $order_items[]    = $item_row;
            }
            
            //删除没有用的items层数据(否则福袋订单明细格式化金额会报错)
            unset($items['items']);
            
            $items['order_items']    = $order_items;
            
            //商品总金额
            $item_cost    += $items['amount'];
        }

        $order_sdf['order_objects']    = $reship_items;
        
       $order_sdf['total_amount'] = $item_cost+$order_sdf['shipping']['cost_shipping']+$order_sdf['shipping']['cost_protect'];
       $order_sdf['final_amount'] = $order_sdf['total_amount'];

       $order_sdf['cost_item']    = $item_cost;

       if($Order_detail["is_tax"] == "true"){ //原始订单如果是开票的
           $rs_invoice_info = kernel::single('invoice_common')->getInvoiceInfoByOrderId($reshipinfo['order_id']);
           $order_sdf["invoice_mode"] = $rs_invoice_info[0]["mode"]; //发票类型 0纸质 1电子
           $order_sdf["business_type"] = $rs_invoice_info[0]["business_type"]; //客户类型
           $order_sdf["ship_tax"] = $rs_invoice_info[0]["ship_tax"]; //客户税号
       }
       $order_sdf['title']=$tostr ? json_encode($tostr):'';
       
       //create order
       $result =  $this->app->model('orders')->create_order($order_sdf);
       
       if($order_sdf['order_id'] && $order_receiver) {
            $order_receiver['order_id'] = $order_sdf['order_id'];
            app::get('ome')->model('order_receiver')->db_save($order_receiver);
        }
       if ($result){
          //更新退货单上状态
          $reshipObj = app::get('ome')->model('reship');
          $reshipObj->update(array('change_order_id'=>$order_sdf['order_id'],'change_status'=>'1'),array('reship_id'=>$reshipinfo['reship_id']));
          kernel::single('ome_service_aftersale')->returngoods_agree($reshipinfo['return_id']);
          $extend_data = array('orig_reship_id'=>$reshipinfo['reship_id'],'order_id'=>$order_sdf['order_id']);
          
          //小红书平台：根据return_id查询换货收货地址表中的encrypt_source_data，提取openAddressId到extend_field，openAddressId用于获取电子面单取号
          if($reshipinfo['shop_type'] == 'xhs' && $reshipinfo['return_id']){
              $returnExchangeReceiverObj = app::get('ome')->model('return_exchange_receiver');
              $exchangeReceiverInfo = $returnExchangeReceiverObj->dump(array('return_id'=>$reshipinfo['return_id']), 'encrypt_source_data');
              
              if($exchangeReceiverInfo && !empty($exchangeReceiverInfo['encrypt_source_data'])){
                  $encryptSourceData = unserialize($exchangeReceiverInfo['encrypt_source_data']);
                  
                  if($encryptSourceData && is_array($encryptSourceData) && isset($encryptSourceData['openAddressId']) && !empty($encryptSourceData['openAddressId'])){
                      //追加openAddressId到extend_field
                      $extend_data['extend_field'] = json_encode(array('openAddressId' => $encryptSourceData['openAddressId']), JSON_UNESCAPED_UNICODE);
                  }
              }
          }

          $extendObj->save($extend_data);
          //原订单上新增换出订单备注
          if($Order_detail['mark_text']) $oldmemo= unserialize($Order_detail['mark_text']);
          $memo = array();
          $memo[] = array('op_name'=>'system', 'op_time'=>date('Y-m-d H:i:s',time()), 'op_content'=>'进行售后换货，创建的换出订单:'.$order_sdf['order_bn']);
          if ($oldmemo){
              foreach($oldmemo as $k=>$v){
                $memo[] = $v;
              }
          }


          if ($memo){
              $mark_text = serialize($memo);

              $oOrder->update(array('mark_text'=>$mark_text),array('order_id'=>$Order_detail['order_id']));
          }
       }
       if($order_sdf['order_type'] == 'platform' && $order_sdf['order_id']) {
            kernel::single('ome_order_platform')->deliveryConsign($order_sdf['order_id']);
       }
       
       // [SAP创建]调用service进行SAP创建
       foreach(kernel::servicelist('ome.service.order.create.after') as $object)
       {
           if(method_exists($object, 'after_create')){
               $object->after_create($order_sdf);
           }
       }
       
       return  $result ? $order_sdf : false;
   }

    public function getActuallyAmount($reship_id) {
        $reshipItems = app::get('ome')->model('reship_items')->getList('num,normal_num,defective_num,order_item_id', ['return_type'=>'return', 'reship_id'=>$reship_id]);
        if (!$reshipItems) {
            return [0, 0];
        }
        $orderItemNum = [];
        foreach($reshipItems as $v) {
            if(empty($v['order_item_id'])) {
                return [0, 0];
            }
            $orderItemNum[$v['order_item_id']] += $v['normal_num'] + $v['defective_num'];
        }
        $saleItems = app::get('ome')->model('sales_items')->getList('nums,order_item_id,actually_amount,settlement_amount', ['order_item_id'=>array_column($reshipItems, 'order_item_id')]);
        $actuallyAmount = 0;
        $settlementAmount = 0;
        foreach($saleItems as $v) {
            if($orderItemNum[$v['order_item_id']]) {
                $radio = $orderItemNum[$v['order_item_id']] / $v['nums'];
                $actuallyAmount += $radio * $v['actually_amount'];
                $settlementAmount += $radio * $v['settlement_amount'];
            }
        }
        return [$actuallyAmount, $settlementAmount];
    }

    /*
     * 数据验证
     * param $data 需校验的参数
     * param $v_msg 返回信息
     */
    function validate($data,&$v_msg)
    {
        $v_msg = '';
        $type_return = $data['return'];
        $type_change = $data['change'];

        $return_c = count((array)$type_return['goods_bn']);
        if( $return_c == 0 ){
          $v_msg = '请选择至少一个退入商品。';
          return false;
        }

        if($data['return_type'] == 'change' && $data['change_status']!='2' && (!$type_change['objects']) ){
            $v_msg = '请至少选择一个换出商品。';
          return false;
        }

        if ($data['return_type'] == 'change' && $data['change_status']!='2'){
            foreach($type_change['objects'] as $v_c){
                if ($v_c['product_id']<=0){
                    $v_msg = $v_c['bn'].'换货商品数据不完整!';
                    return false;
                }
            }
        }

        if($type_return['goods_bn']){
           foreach ($type_return['goods_bn'] as $key => $value)
           {
              // 获取基础物料号，如果不存在则使用order_item_id作为后备
              $material_bn = isset($type_return['bn'][$value]) ? $type_return['bn'][$value] : $value;
              
              if ($data['is_check'] == '11') {
                  $normal_num = intval($type_return['normal_num'][$value]);
                  $defective_num = intval($type_return['defective_num'][$value]);
                  $total_return_num = $normal_num + $defective_num;
                  if ($total_return_num > $type_return['effective'][$value]) {
                      $v_msg = '货号【'.$material_bn.'】的入库数量超出可退入数量，申请被人拒绝!';
                      return false;
                  }
                  
                  //if ($total_return_num < 1) {
                  //    $v_msg = '货号【'.$material_bn.'】的入库数量为0，需要删除!';
                  //    return false;
                  //}
              }else{
                  if($type_return['effective'][$value] < 1){
                      $v_msg = '退入商品中货号为:'.$material_bn.'商品申请数量小于0，申请被拒绝!';
                      return false;
                  }
                  
                  if ($type_return['num'][$value] > $type_return['effective'][$value]) {
                      $v_msg = '货号【'.$material_bn.'】的申请数量超出可退入数量，申请被人拒绝!';
                      return false;
                  }
                  
                  if ($type_return['num'][$value]<=0) {
                      $v_msg = '货号【'.$material_bn.'】的申请数量必须大于0!';
                      return false;
                  }
              }
           }
        }

        #数据验证([最终收货]提交时,不再检查库存,新建售后时已经检查过了)
        $libBranchProduct    = kernel::single('ome_branch_product');
        if($data['return_type'] == 'change' && $type_change['objects'] && $data['is_check'] != '11')
        {

            //换货的仓库
            $changebranch_id = $data['changebranch_id'];
            //判断是否是门店仓
            $store_id = kernel::single('ome_branch')->isStoreBranch($changebranch_id);

            #销售物料层
            foreach ($type_change['objects'] as $objects )
            {
                #基础物料层
                foreach ($objects['items'] as $item_key => $item)
                {
                    $bm_id         = $item['bm_id'];
                    $change_num    = $item['change_num'];#换货数量

                    if ($change_num < 1){
                        $v_msg = '换出商品中,基础物料为:['. $item['material_bn'] .']申请数量为0，申请被拒绝。';
                        return false;
                    }
                    if ($item['changebranch_id']){
                        $changebranch_id = $item['changebranch_id'];
                    }
                    //是否检查库存数 默认true(门店仓存在不管控库存的情况)
                    $check_stock = true;

                    #基础物料库存 [根据选择的换货仓库]获取基础物料库存
                    if($store_id){//门店仓
                        $arr_stock = kernel::single('o2o_return')->o2o_store_stock($changebranch_id,$bm_id);
                        $store_num = $arr_stock["store"]; //值可能会包括 "-" "x" 或 真实的库存数
                        if ($store_num == "x"){
                            $v_msg = '换出商品中,基础物料为:['. $item['material_bn'] .']与此门店仓无供货关系，申请被拒绝。';
                            return false;
                        }
                        if($store_num == "-"){//不管控库存。
                            $check_stock = false;
                        }
                    }else{//电商仓
                        $temp_store = $libBranchProduct->getAvailableStore($changebranch_id, array($bm_id));
                        $store_num = $temp_store[$bm_id];
                    }

                    if ($store_num < 1 && $check_stock){
                        $v_msg = '换出商品中,基础物料为:['. $item['material_bn'] .']实际的库存为0，申请被拒绝。';
                        return false;
                    }

                    if ($change_num > $store_num && $check_stock){
                        $v_msg = '换出商品中,基础物料为:['. $item['material_bn'] .']申请数量大于实际的库存。申请被拒绝。';
                        return false;
                    }
                }
            }
        }

        #  判断补差价 chenping
        if ($data['diff_order_bn']) {
          $order = $this->app->model('orders')->select()->columns('order_id')
                    ->where('order_bn=?',$data['diff_order_bn'])
                    ->where('pay_status=?','1')
                    ->where('ship_status=?','0')
                    ->where('status=?','active')
                    ->instance()->fetch_row();
          if (empty($order)) {
            $v_msg = '补差价订单有误!';
            return false;
          }
        }
        return true;
    }

    //质检成功后执行相应的操作
    function finish_aftersale($reship_id){
        $Oreturn_products = $this->app->model('return_product');
        $Oreship = $this->app->model('reship');
        $oOperation_log = $this->app->model('operation_log');
        $oRefund_apply = $this->app->model('refund_apply');
        $oReship_items = $this->app->model('reship_items');

        //避免并发加判断
        $reship_detail = $Oreship->dump($reship_id,'status');
        if ($reship_detail['status'] == 'succ') {
            return false;
        }

        //先更新状态为成功(并且设置is_modify编辑状态为false)
        $rs = $this->update(array('status'=>'succ', 'is_modify'=>'false'), array('status|noequal'=>'succ','reship_id'=>$reship_id));
        if(is_bool($rs)) {
            return false;
        }
        $wrMdl = app::get('console')->model('wms_reship');
        $wrRow = $wrMdl->db_dump(['reship_id'=>$reship_id, 'reship_status'=>'2'], 'id');
        if($wrRow) {
            $wrRs = $wrMdl->update(['reship_status'=>'3'], ['id'=>$wrRow['id'], 'reship_status'=>'2']);
            if(!is_bool($wrRs)) {
                app::get('ome')->model('operation_log')->write_log('wms_reship@console',$wrRow['id'], '退货单完成');
            }
        }

        //获取退换货单主表数据
        $reshipinfo = $this->dump(array('reship_id'=>$reship_id),'*');
        $shop_id = $reshipinfo['shop_id'];
        
        //AG自动退款配置
        $aliag_status = app::get('ome')->getConf('shop.aliag.config.'.$shop_id);
        
        //是否生成售后单
        $is_generate_aftersale = true;

        //满足条件 退换货单创建 API执行
        $this->request_reship_creat_api($reshipinfo['shop_id'],$reship_id);

        //是否归档
        $is_archive = kernel::single('archive_order')->is_archive($reshipinfo['archive']);

        //订单明细退货处理
        $orders = $this->do_order_items_return($reshipinfo,$is_archive);
        
        //生成退款申请单
        $totalmoney = (float)$reshipinfo['totalmoney']; //实际需要退款的金额

        //新建退款申请单时的申请退款金额
        $money = (float)$reshipinfo['tmoney']+(float)$reshipinfo['diff_money']+(float)$reshipinfo['bcmoney']-(float)$reshipinfo['bmoney']-(float)$reshipinfo['had_refund'];
        
        //[货到付款订单]无需生成退款申请单&&无需AG自动退款
        $is_cod_order = false;
        if($orders['is_cod'] == 'true' || $orders['shipping']['is_cod'] == 'true'){
            $is_cod_order = true;
            $aliag_status = false;
        }
        
        //申请退款金额大于0时新建退款申请(货到付款订单不需要创建退款申请单)
        if($money >= 0 && !($is_cod_order&&$money==0)){
            //[兼容]抖音平台退货完成,创建退款申请单号直接使用售后申请单号
            if($reshipinfo['source']=='matrix'){
                $returnInfo = $Oreturn_products->dump(array('return_id'=>$reshipinfo['return_id']), 'return_bn');

                $refund_apply_bn = ($returnInfo['return_bn'] ? $returnInfo['return_bn'] : $reshipinfo['reship_bn']);
            }else{
                $refund_apply_bn = $oRefund_apply->gen_id();
            }

            $refund_sdf = $this->create_refund_apply_record($refund_apply_bn,$reshipinfo,$money,$is_archive);
        }

        $reshipLib = kernel::single('ome_reship');
        if ($is_archive) {
            $reshipLib = kernel::single('archive_reship');
        }
        
        //判断是否要生成一张支付单
        if ($reshipinfo['diff_order_bn']) {//新增补差订单 发货状态改为已发货 并把状态回打给前端。
            kernel::single('ome_reship')->updatediffOrder($reshipinfo['diff_order_bn']);
        }
        
        //退货仓库类型
        $branchLib = kernel::single('ome_branch');
        $wms_type = $branchLib->getNodetypBybranchId($reshipinfo['branch_id']);
        
        //dispose
        $memo = '';
        if($reshipinfo['return_type'] =='change'){//换货
            
            //是否换货完成生成新订单
            $is_create_order = false;
            
            //check
            if($reshipinfo['change_order_id'] == 0 && $reshipinfo['change_status'] == '0'){
                $is_create_order = true;
            }
            
            //[京东一件代发]不用生成新订单,京东会生成新建推送给OMS
            if(in_array($reshipinfo['shop_type'], ['yunmall']) || $wms_type == 'yjdf'){
                $is_create_order = false;
            }
            
            //生成新订单
            if ($is_create_order){
                //这两个define是ome_freeze_stock_log表新增记录用
                define('FRST_TRIGGER_OBJECT_TYPE','订单：售后申请换货生成新订单');
                define('FRST_TRIGGER_ACTION_TYPE','ome_mdl_return_product：saveinfo');
                $change_order_sdf = $this->create_order($reshipinfo);
                if ($change_order_sdf) {
                    $memo .=' 生成了1张换货订单【'.$change_order_sdf['order_bn'].'】';
                    //库存管控 生成订单后释放库存
                    kernel::single('console_reship')->releaseChangeFreeze($reship_id);
                    //换出的订单金额
                    $change_total_amount = $change_order_sdf['total_amount'];
                    //换出的订单ID
                    $neworderid = $change_order_sdf['order_id'];
                }
            }else{
                $change_total_amount = $reshipinfo['change_amount'];
            }

            $pay_money = $money; //生成的换货订单支付金额
            $pay_status = '1'; //已支付
            if ($totalmoney == 0) {//如果实际退款金额为零,无需退款与支付
                if(!empty($refund_sdf)){ //退款申请完成，并产生退款单 走个已退款的流水
                    $reshipLib->createRefund($refund_sdf,$orders);
                }
            }elseif ($totalmoney<0) { //负数： 需客户再补钱的
                if(!empty($refund_sdf)){ //退款申请完成，并产生退款单 走个已退款的流水
                    $reshipLib->createRefund($refund_sdf,$orders);
                }
                $pay_status = '3'; //部分支付
            }elseif ($totalmoney>0) { //正数：需商家再补给客户
                //$is_generate_aftersale = false;
                //更新为实际退款金额 需要退款
                if(!empty($refund_sdf)){
                    $memo .= $refund_sdf['memo'].'总退款金额大于换货订单总额，进行多余费用退款!';
                    $oRefund_apply->update(array("memo"=>$memo),array('refund_apply_bn'=>$refund_apply_bn));
                }
                //生成退款申请单(换出的订单金额) 后产生退款单 状态更新为已退款
                if($change_total_amount>0){ //申请退款金额大于0时新建退款申请
                  //  $refund_apply_bn = $oRefund_apply->gen_id();
                  //  $refund_sdf = $this->create_refund_apply_record($refund_apply_bn,$reshipinfo,$change_total_amount,$is_archive);
                    $reshipLib->createRefund($refund_sdf,$orders); //退款申请完成，并产生退款单
                }
                $pay_money = $change_total_amount;
            }
            
            //新订单改为已或者部分支付状态
            if ($neworderid) {
                $order = array(
                        'order_id'        => $neworderid,
                        'shop_id'         => $reshipinfo['shop_id'],
                        'pay_status'      => $pay_status,
                        'pay_money'       => $pay_money,
                        'currency'        => 'CNY',
                        'reship_order_bn' => $orders['order_bn'],
                );
                if ($is_archive){
                    $order['archive'] = '1';
                }
                $reshipLib->payChangeOrder($order);
            }
        }elseif($reshipinfo['return_type'] == 'return'){ //退货
            $refundMoney  = (float)$reshipinfo['tmoney']; # 退款金额
            if($totalmoney == 0) { //前有节点已经拦掉生成退款申请单
                if(!$is_cod_order) {
                    $is_generate_aftersale = false;
                }
            }elseif($refundMoney>$totalmoney) {
              //多退 退换货生成的退款申请单，退换货单号为:201301251613000368。应退金额(12)扣除折旧费邮费后，实际应退金额为(2)
              $memo = $refund_sdf['memo'].'应退金额('.$refundMoney.')扣除(已退金额或折旧费邮费)后，实际应退金额为('.$totalmoney.')';
              $oRefund_apply->update(array("money"=>$totalmoney,"memo"=>$memo),array('refund_apply_bn'=>$refund_apply_bn));
              $is_generate_aftersale = false;
            }elseif($totalmoney>0 && $totalmoney>$refundMoney){
                //少退的
                $memo = $refund_sdf['memo'].'应退商品金额('.$refundMoney.'),';
                if ($reshipinfo['cost_freight_money'] < 0) {
                    $memo .= '加上相应的邮费,';
                }
                $memo .= '实际应退金额为('.$totalmoney.')';
                $oRefund_apply->update(array("money"=>$totalmoney,"memo"=>$memo),array('refund_apply_bn'=>$refund_apply_bn));
                $is_generate_aftersale = false;
            }
        }

        //更新为完成
        $t_end = time();
        $this->update(array('is_check'=>'7','t_end'=>$t_end,'refund_status'=>'ing'),array('reship_id'=>$reship_id));
        $memo .= '操作完成。';
        $refundAuto = false;
        if($reshipinfo['return_id']){
            $rpStatus = $Oreturn_products->db_dump(array('return_id'=>$reshipinfo['return_id']), 'status');
            if($rpStatus['status'] == '4') {
                $refundAuto = true;
            }
            $Oreturn_products->update(array('status'=>'4','money'=>$totalmoney),array('return_id'=>$reshipinfo['return_id']));
            $oOperation_log->write_log('return@ome',$reshipinfo['return_id'],$memo);
            //退货完成回写
            if ($change_order_sdf) {
                $newmemo =' 生成了1张换货订单【'.$change_order_sdf['order_bn'].'】';
            }
            kernel::single('ome_service_aftersale')->returngoods_confirm($reshipinfo['return_id']);
            //退货确认完成
            kernel::single('ome_service_aftersale')->update_status($reshipinfo['return_id'],'','async',$newmemo);
            
        }
        $oOperation_log->write_log('reship@ome',$reship_id,$memo);

        //生成售后单
        $aftersales_set = app::get('ome')->getConf('ome.aftersales.auto_finish');
        $trigger_event = '1';
        if($aftersales_set == 'true' || $aftersales_set == 'refunded'){
            $is_generate_aftersale=true;
            $trigger_event = '2';
        }
        if($is_generate_aftersale){
            kernel::single('sales_aftersale')->generate_aftersale($reship_id,$reshipinfo['return_type'], $trigger_event);
        }
        
        //[开启AG自动退款]售后处理完成的时候，推AG退货入库的标
        //order_source=platformexchange 兼容平台换了再退场景
        $is_request = false;
        if ($reshipinfo['return_id'] && $aliag_status && $reshipinfo['return_type'] == 'return' && $reshipinfo['source'] == 'matrix' && !($reshipinfo['flag_type'] & ome_reship_const::__LANJIE_RUKU)) {
            $is_request = true;
        } elseif ($reshipinfo['return_id'] && $aliag_status && $reshipinfo['return_type'] == 'return' && $orders['order_source'] == 'platformexchange') {
            $is_request = true;
        }
        if($reshipinfo['flag_type'] && ($reshipinfo['flag_type'] & ome_reship_const::__YUANDANTUI) ) {
            $is_request = true;
        }
        if($is_request){
            $agAutoReturn = false;
            if(in_array($reshipinfo['shop_type'], array('tmall','taobao'))){
                //天猫平台
                $refundOriginalObj = app::get('ome')->model('return_product_tmall');
                $refundOriginalInfo = $refundOriginalObj->getList('refund_fee,jsrefund_flag', array('return_id'=> $reshipinfo['return_id']) , 0 , 1);
                $original_refund_fee = $refundOriginalInfo[0]['refund_fee'];
                
                //识别是否开启AG并且是天猫退货退款已入库的，通知AG
                $compare_nums = $oReship_items->db->select("SELECT item_id FROM sdb_ome_reship_items WHERE reship_id=".$reship_id." AND return_type='return' AND num!=defective_num+normal_num");
                
                //check
                if(!$compare_nums){
                    $agAutoReturn = true;
                }
                if($reshipinfo['flag_type'] & ome_reship_const::__YUANDANTUI) {
                    $agAutoReturn = true;
                }
            }else{
                //c2c店铺平台
                $c2cShopType = ome_shop_type::shop_list();
                if(in_array($reshipinfo['shop_type'], $c2cShopType) || in_array($reshipinfo['shop_type'], ome_shop_type::shop_refund_list())){
                    //差收不发起AG
                    $compare_nums = $oReship_items->db->select("SELECT item_id FROM sdb_ome_reship_items WHERE reship_id=".$reship_id." AND return_type='return' AND num!=defective_num+normal_num");
                    if(!$compare_nums){
                        $agAutoReturn = true;
                    }
                }
                
                //[开普勒]校验京东云交易MQ消息通知的退款金额
                if($reshipinfo['shop_type'] == 'luban' && $wms_type == 'yjdf' && $agAutoReturn){
                    //$keplerLib = kernel::single('ome_reship_kepler');
                    //$agAutoReturn = $keplerLib->checkMqRefundAmount($reshipinfo, $refund_sdf['apply_id']);
                }
            }
            
            //AG自动退款
            if($agAutoReturn){
                $params = array(
                        'order_bn' => $orders['order_bn'],
                        'apply_id' => $refund_sdf['apply_id'],
                        'refund_bn' => $reshipinfo['reship_bn'],
                        'return_bn' => $reshipinfo['reship_bn'],
                        'is_aftersale_refund' => true,
                        'shop_id' => $shop_id,
                        'return_id'=>$reshipinfo['return_id'],
                );
               
                //[抖音平台]增加参数
                if ($reshipinfo['shop_type'] == 'luban') {
                    //查询物流公司编码
                    $dlyCorpMdl = app::get('ome')->model('dly_corp');
                    $company_code = $dlyCorpMdl->db_dump(array('name|has'=>mb_substr($reshipinfo['return_logi_name'],0,2)),'type');
                    $params['op_time'] = ($t_end ? $t_end : time());
                    $params['company_code'] = isset($company_code['type']) ? $company_code['type'] : '';
                    
                }
    
                // 补充ecos.ecshopx接口所需参数
                if ($reshipinfo['shop_type'] == 'ecos.ecshopx') {
                    if (!empty($refund_sdf)) {
                        $params = array_merge($refund_sdf,$params);
                    }
                }
                $params['logistics_no'] = trim($reshipinfo['return_logi_no']);
                //请求平台添加退款单
                kernel::single('ome_service_refund')->refund_request($params);
            }
        }
        
        //判断如果是极速退款自动完成退款单
        if ($totalmoney>0 && $reshipinfo['return_type'] == 'return'){
            if($reshipinfo['jsrefund_flag'] || $refundAuto) {
                $this->__jsRefundAuto($refund_sdf);
            }
        }
        //退货入库后更新退款未退货报表退货单据状态
        kernel::single('ome_refund_noreturn')->reshipRefundNoreturn($reshipinfo['order_id'],$reshipinfo['reship_id']);
    
        //短收差异入库订单打标
        if (isset($reshipinfo['flag_type']) && ($reshipinfo['flag_type'] & ome_reship_const::__RESHIP_DIFF)) {
            $err = '';
            kernel::single('ome_bill_label')->markBillLabel($reshipinfo['order_id'], '', 'SAMS_RETURN_GAP', 'order', $err);
        }
        foreach(kernel::servicelist('ome.service.reship.finish.after') as $object=>$instance){
            if(method_exists($instance,'afterReshipFinish')){
                $instance->afterReshipFinish($reshipinfo['reship_id']);
            }
        }
        return true;
    }

    //退换货单创建 API执行
    /**
     * request_reship_creat_api
     * @param mixed $shop_id ID
     * @param mixed $reship_id ID
     * @return mixed 返回值
     */
    public function request_reship_creat_api($shop_id,$reship_id){
        $oShop = $this->app->model('shop');
        $shop_type = $oShop->getRow(array('shop_id'=>$shop_id),'node_type');
        $c2c_shop_type = ome_shop_type::shop_list();
        if(!empty($shop_type['node_type']) && !in_array($shop_type['node_type'],$c2c_shop_type)){
            foreach(kernel::servicelist('service.reship') as $object=>$instance){
                if(method_exists($instance,'reship')){
                    $instance->reship($reship_id);
                }
            }
        }
    }

    //订单明细退货处理 并返回orders数组
    /**
     * do_order_items_return
     * @param mixed $reshipinfo reshipinfo
     * @param mixed $is_archive is_archive
     * @return mixed 返回值
     */
    public function do_order_items_return($reshipinfo,$is_archive){
        $oReship_items = $this->app->model('reship_items');
        $Reshipitem = $oReship_items->getList('bn,num,price,amount,normal_num,defective_num,order_item_id',array('reship_id'=>$reshipinfo["reship_id"],'return_type'=>'return'));
        app::get('sales')->model('delivery_order_item')->addReturnNum($Reshipitem);
        if ($is_archive) {
            $archive_ordobj = kernel::single('archive_interface_orders');
            $orders = $archive_ordobj->getOrders(array('order_id'=>$reshipinfo['order_id']),'*');
            kernel::single('archive_reship')->finish_aftersale($Reshipitem,$reshipinfo['order_id']);
        }else{
            $oItemModel = $this->app->model('order_items');
            $oOrder = $this->app->model('orders');
            foreach($Reshipitem as $k=>$v){
                $itemsql = "SELECT sendnum,bn,item_id, return_num FROM sdb_ome_order_items WHERE order_id='".$reshipinfo['order_id']."' AND bn='".$v['bn']."' AND item_id='".$v['order_item_id']."' AND sendnum != return_num AND `delete`='false'";
                $orderItems = $this->db->select($itemsql);
                $num = intval($v['normal_num']+$v['defective_num']);
                $residue_num    = 0;//剩余退货量
                foreach ($orderItems as $ivalue) {
                    if($num <= 0) break;
                    $residue_num    = intval($ivalue['sendnum'] -  $ivalue['return_num']);//剩余数量=已发货量-已退货量
                    if ($num > $residue_num) {
                        $num -= $residue_num;
                        //更新_已退货量 = 已发货量
                        $oItemModel->update(array('return_num' => $ivalue['sendnum']),array('item_id'=>$ivalue['item_id']));
                    } else {
                        //更新_已退货量 = 已退货量 + 本次退货量
                        $oItemModel->update(array('return_num' => ($ivalue['return_num'] + $num)),array('item_id'=>$ivalue['item_id']));
                    }
                }
            }
            //更新订单发货状态[return_num排除_已退完商品]
            $order_sum = $this->db->selectrow("SELECT sum(sendnum) as count FROM sdb_ome_order_items WHERE order_id='".$reshipinfo['order_id']."' AND sendnum != return_num AND `delete`='false'");
            
            //拆单_部分发货_部分退货时_判断是否有未发货的货品
            $orders = $oOrder->dump(array('order_id'=>$reshipinfo['order_id']));
            if(intval($order_sum['count']) == 0){
                if($orders['ship_status'] == '2' || $orders['ship_status'] == '3'){
                    $sql    = "SELECT sum(nums - sendnum) as count FROM sdb_ome_order_items WHERE order_id = '".$reshipinfo['order_id']."' AND nums != sendnum AND `delete` = 'false'";
                    $order_sum    = $this->db->selectrow($sql);
                }
            }
            
            $ship_status = (intval($order_sum['count']) == 0) ? '4' : '3';
            $oOrder->update(array('ship_status'=>$ship_status),array('order_id'=>$reshipinfo['order_id']));
            $orders['ship_status'] = $ship_status; //更新后的发货状态
        }
        return $orders;
    }

    /*
     * 生成退款申请单
     * $refund_apply_bn 生成的退款申请单号
     * $reshipinfo array 退换货单信息
     * $money 申请退款金额
     * $is_archive 是否归档
     */

    public function create_refund_apply_record($refund_apply_bn,$reshipinfo,$money,$is_archive){
        $oRefund_apply = $this->app->model('refund_apply');
        //退款明细序列化字段
        $addon    = array('reship_id'=>$reshipinfo["reship_id"],'return_id'=>$reshipinfo['return_id']);

        //组保存数据
        $refund_sdf = array(
            'refund_refer' => '1',
            'order_id' => $reshipinfo['order_id'],
            'refund_apply_bn' => $refund_apply_bn,
            'pay_type' => 'online',
            'money' => (float)$money,
            'refunded' => 0,
            'memo' => '退换货生成的退款申请单，退换货单号为:'.$reshipinfo['reship_bn'].'。',
            'status' => 0,
            'shop_id' => $reshipinfo['shop_id'],
            'addon' => serialize($addon),
            'return_id' => $reshipinfo['return_id'],
            'bcmoney' => (float)$reshipinfo['bcmoney'],
            'reship_id'=>$reshipinfo['reship_id'],
        );
        if($reshipinfo['flag_type'] & ome_reship_const::__ZERO_INTERCEPT) {
            $refund_sdf['bool_type'] = ome_refund_bool_type::__ZERO_INTERCEPT;
        }

        if ($is_archive) {
            $refund_sdf['archive'] = '1';
            $refund_sdf['source'] = 'archive';
        }
        elseif($reshipinfo['shop_type']=='luban' && $reshipinfo['source']=='matrix')
        {
            //[兼容]抖音平台退货完成,创建退款申请单号直接使用售后申请单号
            $refund_sdf['source'] = 'matrix';
        }
        else
        {
            $refund_sdf['source']    = 'local';
        }
        // 判断是否是淘宝退款转售后单据
        if ($reshipinfo['return_type'] == 'return' && $refundApplyTmall = $oRefund_apply->db_dump(array('order_id'=>$reshipinfo['order_id'],'refund_apply_bn'=>$reshipinfo['reship_bn'],'status|notin'=>array('3','4'),'source'=>'matrix'))) {
            $refund_sdf['status']     = $refundApplyTmall['status'];
            $refund_sdf['create_time']     = $refundApplyTmall['create_time'];
            $refund_sdf['memo']            .= $refundApplyTmall['memo'];
            $refund_sdf['refund_apply_bn'] = $refundApplyTmall['refund_apply_bn'];
            $refund_sdf['apply_id']        = $refundApplyTmall['apply_id'];
        }
        //创建退款申请单
        $is_update_order    = false;//是否更新订单付款状态
        $error_msg = '';
        kernel::single('ome_refund_apply')->createRefundApply($refund_sdf, $is_update_order, $error_msg);

        return $refund_sdf;
    }

    function io_title( $filter=null,$ioType='csv' )
    {
        switch($filter){
            case 'import_reship':
                $this->oSchema['csv'][$filter] = array(
                '*:订单号(必填)' => 'order_bn',
                '*:退换货单号(必填)' => 'reship_bn',
                '*:前端店铺名称(必填)' => 'shop_id',
                '*:退入仓名(不填默认发货仓)' => 'branch_id',
                '*:退回物流公司名' => 'return_logi_name',
                '*:退回物流单号' => 'return_logi_no',
                '*:备注' => 'memo',
                '*:补偿费用' => 'bcmoney',
                '*:折旧(其他费用)' => 'bmoney',
                '*:买家承担的邮费' => 'cost_freight_money',
                '*:补差价订单号' => 'diff_order_bn',
                '*:补差价订单金额' => 'diff_money',
                '*:退换货类型(不填默认退货)' => 'return_type',
                '*:换出仓名(换货时必填)' => 'changebranch_id',
                '*:售后类型' => 'flag_type_text',
                );
                break;
            case 'import_reship_return_item':
                $this->oSchema['csv'][$filter] = array(
                '*:订单号(必填)' => 'order_bn',
                '*:退换货单号(必填)' => 'reship_bn',
                '*:退入基础物料编码(必填)' => 'return_product_bn',
                '*:销售价' => 'return_price',
                '*:申请数量(必填)' => 'return_num',
                );
                break;
            case 'import_reship_change_item':
                $this->oSchema['csv'][$filter] = array(
                '*:订单号(必填)' => 'order_bn',
                '*:退换货单号(必填)' => 'reship_bn',
                '*:换出销售物料编码(必填)' => 'change_product_bn',
                '*:销售物料售价' => 'change_price',
                '*:申请数量(必填)' => 'change_num',
                );
                break;
            default:
                //原来的导出字段
                $this->oSchema['csv']['reship'] = array(
                    'col:退换货单号' => 'reship_bn',
                    'col:售后申请单号' => 'return_id',
                    'col:售后申请标题' => 'return_title',
                    'col:问题类型'=>'problem_id',
                    'col:订单号' => 'order_id',
                    'col:配送费用' => 'money',
                    'col:是否保价' => 'is_protect',
                    'col:配送方式' => 'delivery',
                    'col:物流公司名称' => 'logi_name',
                    'col:物流单号' => 'logi_no',
                    'col:退回物流公司名称' => 'return_logi_name',
                    'col:退回物流单号' => 'return_logi_no',
                    'col:收货人姓名' => 'ship_name',
                    'col:收货人地区' => 'ship_area',
                    'col:收货人地址' => 'ship_addr',
                    'col:收货人邮编' => 'ship_zip',
                    'col:收货人电话' => 'ship_tel',
                    'col:收货人手机' => 'ship_mobile',
                    'col:收货人Email' => 'ship_email',
                    'col:当前状态' => 'is_check',
                    'col:备注' => 'memo',
                    'col:退款的金额' => 'tmoney',
                    'col:补差的金额' => 'bmoney',
                    'col:补偿费用' => 'bcmoney',
                    'col:最后合计金额' => 'totalmoney',
                    'col:收货时间'=>'receive_time',
                    'col:单据结束时间'=>'t_end',
                );
                
                $this->oSchema['csv']['items'] = array(
                    'col:退货单号' => 'reship_bn',
                    'col:商品货号' => 'bn',
                    'col:仓库名称' => 'branch_name',
                    'col:类型' => 'return_type',
                    'col:商品名称' => 'product_name',
                    'col:申请数量' => 'num',
                    'col:良品' => 'normal_num',
                    'col:不良品' =>'defective_num',

                );
                    break;
        }
        if($this->expert_flag){
           $_title = array(
                    'col: 售后类型' => 'return_type',
                    'col:单据创建时间' => 't_begin',
                   );
           $this->oSchema[$ioType]['reship'] = array_merge($this->oSchema[$ioType]['reship'],$_title);
        }

        $this->ioTitle[$ioType][$filter] = array_keys( $this->oSchema[$ioType][$filter] );
        return $this->ioTitle[$ioType][$filter];
     }

     //csv导出
     function fgetlist_csv( &$data,$filter,$offset,$exportType = 1 ){
        $this->expert_flag =true;

        if( !$data['title']['reship'] ){
            $title = array();
            foreach( $this->io_title('reship') as $k => $v ){
                $title[] = $this->charset->utf2local($v);
            }
            $data['title']['reship'] = '"'.implode('","',$title).'"';
        }
        if( !$data['title']['items'] ){
            $title = array();
            foreach( $this->io_title('items') as $k => $v )
                $title[] = $this->charset->utf2local($v);
            $data['title']['items'] = '"'.implode('","',$title).'"';
        }
        $limit = 100;

        $itemsObj = $this->app->model('reship_items');
        if( !$list=$this->getList('reship_id',$filter,$offset*$limit,$limit) )return false;
        $oProduct_pro   = $this->app->model('return_process');
        foreach( $list as $aFilter ){
            $aReship = $this->dump($aFilter['reship_id'],'reship_bn,return_id,problem_id,order_id,money,is_protect,delivery,logi_name,logi_no,return_logi_name,return_logi_no,ship_name,ship_area,ship_addr,ship_zip,ship_tel,ship_mobile,ship_email,is_check,memo,tmoney,bmoney,totalmoney,return_type,bcmoney,t_end,t_begin');

            $reship_id = $aFilter['reship_id'];
            $return_process = $oProduct_pro->dump(array('reship_id'=>$reship_id),'process_data');

            $aReship['return_logi_no'] = "=\"\"".$aReship['return_logi_no']."\"\"";
            $aReship['logi_no'] = "=\"\"".$aReship['logi_no']."\"\"";
            $aReship['reship_bn'] =  "=\"\"".$aReship['reship_bn']."\"\"";//$aReship['reship_bn']."\t";
            //处理售后信息
            $rp = $this->app->model('return_product')->dump($aReship['return_id'],'return_bn,title');
            $aReship['return_id'] = "=\"\"".strval($rp['return_bn'])."\"\"";//strval($rp['return_bn'])."\t";
            $aReship['return_title'] = $rp['title'];

            #售后类型
            if($aReship['return_type']){
                $aReship['return_type'] = $this->return_type[$aReship['return_type']];
            }
            //售后问题
            $rpp = $this->app->model('return_product_problem')->dump($aReship['problem_id'],'problem_name');
            $aReship['problem_id'] = $rpp['problem_name'];
            if ($aReship['archive'] == '1') {
                //处理订单号
                $archive_ordObj = kernel::single('archive_interface_orders');

                $oOrder = $archive_ordObj->getOrders(array('order_id'=>$aReship['order_id']),'order_bn');
            }else{
                //处理订单号
                $oOrder = $this->app->model('orders')->dump($aReship['order_id']);
            }
            $aReship['order_id'] = "=\"\"".$oOrder['order_bn']."\"\"";//$oOrder['order_bn']."\t";

            //处理物流信息
            $dc = $this->app->model('dly_corp')->dump($aReship['return_logi_name'],'name');
            $aReship['return_logi_name'] = $dc['name'];
            //
            $process_data = $return_process['process_data'];
            $aReship['receive_time'] = '';
            $aReship['t_begin'] = $aReship['t_begin'] ? date('Y-m-d H:i:s',$aReship['t_begin']) : '';
            $aReship['t_end'] = $aReship['t_end'] ? date('Y-m-d H:i:s',$aReship['t_end']) : '';
            if ($process_data) {
                $process_data = unserialize($process_data);
                $aReship['receive_time'] = date('Y-m-d H:i:s',$process_data['shiptime']);
            }

            //处理收货地区
            $rd = explode(':', $aReship['ship_area']);
            if($rd[1]){
             $aReship['ship_area'] = str_replace('/', '-', $rd[1]);
            }

            //处理当前状态
            $aReship['is_check'] = $this->is_check[$aReship['is_check']];

            $aReship['is_protect'] = $aReship['is_protect']=='false'?'否':'是';


            $oreship = array_values($this->oSchema['csv']['reship']);
            //items
            $_items = $itemsObj->getlist('*',array('reship_id'=>$reship_id));

            foreach ( $_items as $_k=>$_v ) {
                $itemcsv =array_values($this->oSchema['csv']['items']);
                switch ($_v['return_type']) {
                    case 'return':
                         $return_type = '退货';
                        break;
                    case 'change':
                        $return_type = '换货';
                        break;
                        case 'refuse':
                            $return_type = '拒收退货';
                            break;

                }
                $branch = $itemsObj->db->selectrow("SELECT name FROM sdb_ome_branch WHERE branch_id=".$_v['branch_id']);
                $item = array(
                    'reship_bn'=>$aReship['reship_bn'],
                    'bn'=>$_v['bn'],
                    'product_name'=>$_v['product_name'],
                    'num'=>$_v['num'],
                    'normal_num'=>$_v['normal_num'],
                    'defective_num'=>$_v['defective_num'],
                    'return_type'=>$return_type,
                    'branch_name'=>$branch['name'],
                );

               foreach ($itemcsv as $ik=>$iv ) {
                   $itemRow[$ik] = $this->charset->utf2local($item[$iv]);
               }
               $data['content']['items'][] = '"'.implode('","',$itemRow).'"';
               unset($branch);
            }
            foreach( $oreship as $k=>$v ){
                $reshipRow[$v] = $this->charset->utf2local($aReship[$v]);
            }
            $data['content']['reship'][] = '"'.implode('","',$reshipRow).'"';
        }

        $data['name'] = '退换货单'.date("Ymd");

        return true;
    }

    /**
     * 获取exportdetail
     * @param mixed $fields fields
     * @param mixed $filter filter
     * @param mixed $offset offset
     * @param mixed $limit limit
     * @param mixed $has_title has_title
     * @return mixed 返回结果
     */
    public function getexportdetail($fields,$filter,$offset=0,$limit=1,$has_title=false)
    {
        $reship_arr = $this->getList('reship_bn,reship_id,order_id', array('reship_id' => $filter['reship_id']), 0, -1);
        foreach ($reship_arr as $reship) {
            $reship_bn[$reship['reship_id']] = $reship['reship_bn'];
        }
        
        $Obranch = app::get('ome')->model('branch');
        $branchs = $Obranch->getList('branch_id,name');
        foreach ($branchs as $v) {
            $branch[$v['branch_id']] = $v['name'];
        }
        unset($branchs);

        $reshipItemsObj = app::get('ome')->model('reship_items');

        //按升序导出(与列表中排序保持一致)
        $reship_items_arr = $reshipItemsObj->getList('*', array('reship_id'=>$filter['reship_id']), 0, -1, 'reship_id DESC');
        $return_type = [
            'return' => '退货',
            'change' => '换货',
            'refuse' => '拒收退货',
        ];
        $row_num = 1;
        if($reship_items_arr){
            foreach ($reship_items_arr as $key => $reship_item) {
                $reshipItemRow['bn']       = $reship_item['bn'];
                $reshipItemRow['branch_name']       = isset($branch[$reship_item['branch_id']]) ? $branch[$reship_item['branch_id']] : '-';
                $reshipItemRow['product_name']   = $reship_item['product_name'];
                $reshipItemRow['item_return_type'] = $return_type[$reship_item['return_type']] ?? '';
                $reshipItemRow['num']   = $reship_item['num'];
                $reshipItemRow['normal_num']   = $reship_item['normal_num'];
                $reshipItemRow['defective_num']   = $reship_item['defective_num'];
                $reshipItemRow['gap']   = $reship_item['return_type'] == 'return' ? $reship_item['num'] - $reship_item['normal_num'] - $reship_item['defective_num'] : 0;
                
                $data[$reship_item['reship_id']][] = $reshipItemRow;
                $row_num++;
            }
        }

        //明细标题处理
        if($data && $has_title){
            $title = array(
                '*:商品货号',
                '*:仓库名称',
                '*:商品名称',
                '*:明细退货类型',
                '*:申请数量',
                '*:良品',
                '*:不良品',
                '*:GAP',
            );

            foreach ((array)$title as $key => $value) {
                $title[$key] = mb_convert_encoding($value, 'GBK', 'UTF-8');
            }

            $data[0] = implode(',', $title);
        }

        ksort($data);
        return $data;
    }

    function export_csv($data,$exportType = 1 ){

        $output = array();
            foreach( $data['title'] as $k => $val ){
                $output[] = $val."\n".implode("\n",(array)$data['content'][$k]);
            }
        echo implode("\n",$output);
    }

    function getLogiInfo($logi_no,$branch_ids=array()){
        $sql = 'select reship_id from sdb_ome_reship where return_logi_no="'.$logi_no.'"';
        if ($branch_ids) {
            $sql.=" AND branch_id in (".implode(',',$branch_ids).")";
        }
      $loginfo = $this->db->selectrow($sql);
      if($loginfo['reship_id']){
          return $loginfo['reship_id'];
      }
        return false;
    }

    /**
     * modifier_totalmoney
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function modifier_totalmoney($row)
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
     * modifier_is_check
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function modifier_is_check($row) {
        if($row == '3') {
            return '审核成功';
        }
        return $this->schema['columns']['is_check']['type'][$row];
    }

    /**
     * modifier_op_id
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function modifier_op_id($row){
        switch ($row) {

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

            return '系统';
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
        $type = 'afterSale';
        if ($logParams['app'] == 'ome' && $logParams['ctl'] == 'admin_return_rchange') {
            if (isset($params['is_check'])) {
                //质检单据
                $type .= '_exchange_goods_qualityTesting';
            }
            else {
                //退换货
                $type .= '_exchange_goods_exchangeList';
            }
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
        $type = 'afterSale';
        $type .= '_import';
        return $type;
    }


    /**
     * 补偿费用显示
     * @param int
     * @return
     * @access  public
     * @author cyyr24@sina.cn
     */
    function modifier_bcmoney($row)
    {
        if ($row>0) {
            $bcmoney = sprintf("<div style='background-color:%s;float:left;'><span alt='%s' title='%s' style='color:#eeeeee;'>&nbsp;%s&nbsp;</span></div>", 'red', $row, $row, $row);
            return $bcmoney;
        }
    }

    /**
     * disabled_export_cols
     * @param mixed $cols cols
     * @return mixed 返回值
     */
    public function disabled_export_cols(&$cols){
        unset($cols['column_edit']);
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
        if($row == 'archive'){
           $row = sprintf("<div style='background-color:%s;float:left;'><span alt='%s' title='%s' style='color:#eeeeee;'>&nbsp;%s&nbsp;</span></div>", 'red', '归档', '归档', '归档');
        }elseif($row == 'matrix'){
            $row = '平台';
        }elseif($row == 'local'){
            $row = '本地';
        }elseif($row == 'import'){
            $row = '导入';
        }else{
            if(empty($row)){
                $row = '-';
            }
        }
        
        return $row;
    }


    /**
     * 删除换货明细.
     * @param
     * @return
     * @access  public
     * @author
     */
    function _deletechange_item($reship_id,$change,$oldchange)
    {
        $db = kernel::database();
        
        $newid =$this->_map_itemid($change['objects']);
        $oldid = $this->_map_itemid($oldchange);

        $diff = array();
        foreach ($oldid as $k=>$v ) {
            $diff[$k] = array_udiff_assoc((array)$v, (array)$newid[$k],array($this,'comp_array_value'));
        }

        #根据obj_id删除不需要的数据
        foreach ((array)$diff as $dk=>$delitem ) {
            if ($delitem) {
                foreach ($delitem as $dv )
                {
                    #先删除销售物料再删除对应的基础物料数据
                    $db->exec("DELETE FROM sdb_ome_reship_objects WHERE reship_id=".$reship_id." AND obj_id=".$dv);
                    $db->exec("DELETE FROM sdb_ome_reship_items WHERE reship_id=".$reship_id." AND obj_id=".$dv);
                }
            }
        }
        
        //新换出的商品明细
        $newItemList = [];
        foreach ((array)$change['objects'] as $objKey => $objVal)
        {
            //check
            if(!isset($objVal['item_id']) || empty($objVal['item_id'])){
                continue;
            }
            
            if(!isset($objVal['items']) || empty($objVal['items'])){
                continue;
            }
            
            //obj_id
            $obj_id = $objVal['item_id'];
            
            //items
            foreach ($objVal['items'] as $itemKey => $itemVal)
            {
                $bm_id = $itemVal['bm_id'];
                
                $newItemList[$obj_id][$bm_id] = $itemVal;
            }
        }
        
        //编辑前的换出商品明细
        $oldItemList = [];
        foreach ($oldchange as $objKey => $objVal)
        {
            //check
            if(!isset($objVal['item_id']) || empty($objVal['item_id'])){
                continue;
            }
            
            if(!isset($objVal['items']) || empty($objVal['items'])){
                continue;
            }
            
            //item_id
            $obj_id = $objVal['item_id'];
            
            //items
            foreach ($objVal['items'] as $itemKey => $itemVal)
            {
                $bm_id = $itemVal['bm_id'];
                
                $oldItemList[$obj_id][$bm_id] = $itemVal;
            }
        }
        
        //场景：换出销售物料关联的基础物料有变化,需要删除掉不存在的基础物料;
        //@todo：现在销售物料在任何情况下，都允许进行编辑关联的基础物料;
        if($oldItemList && $newItemList && $reship_id){
            $delMaterialBns = [];
            foreach ($oldItemList as $obj_id => $itemList)
            {
                if(!isset($newItemList[$obj_id])){
                    continue;
                }
                
                //check
                if(empty($itemList) || empty($newItemList[$obj_id])){
                    continue;
                }
                
                //items
                foreach ($itemList as $bm_id => $itemInfo)
                {
                    $material_bn = $itemInfo['material_bn'];
                    
                    //新换出商品明细存在,则跳过
                    if(isset($newItemList[$obj_id][$bm_id])){
                        continue;
                    }
                    
                    $delMaterialBns[$material_bn] = $material_bn;
                    
                    //删除编辑后,不存在的换出明细基础物料
                    $delete_sql = "DELETE FROM sdb_ome_reship_items WHERE reship_id=". $reship_id ." AND obj_id=". $obj_id ." AND product_id=". $bm_id;
                    $db->exec($delete_sql);
                }
            }
            
            //logs
            if($delMaterialBns){
                $operLogMdl = app::get('ome')->model('operation_log');
                
                $log_msg = '换出销售物料包含的基础物料被修改,删除基础物料：'. implode('、', $delMaterialBns);
                $operLogMdl->write_log('reship@ome', $reship_id, $log_msg);
            }
        }
        
        return $diff;
    }

    /**
     * _map_itemid
     * @param mixed $change change
     * @return mixed 返回值
     */
    public function _map_itemid($change){
        if (empty($change)){
            return array();
        }
        $ids = array();
        foreach ($change as $v ) {
            if ($v['item_id']) {
                $ids[$v['item_type']][] =$v['item_id'];
            }
        }
        return $ids;
    }

    /**
     * comp_array_value
     * @param mixed $a a
     * @param mixed $b b
     * @return mixed 返回值
     */
    public function comp_array_value($a,$b)
    {
        if ($a == $b) {
            return 0;
        }

        return $a > $b ? 1 : -1 ;
    }

    function getSalepriceByorderId($order_id){
        $items = $this->db->select("SELECT sales_material_bn,bn,sales_amount,nums FROM sdb_ome_sales_items AS I LEFT JOIN sdb_ome_sales as S ON I.sale_id=S.sale_id WHERE S.order_id=".$order_id." AND I.product_id>0");

        $data = array();
        foreach ( $items as $item ){
            $data[$item['sales_material_bn']][$item['bn']] = $item['sales_amount'];
        }

        return $data;

    }

    /**
     * __jsRefundAuto
     * @param mixed $refund_sdf refund_sdf
     * @return mixed 返回值
     */
    public function __jsRefundAuto($refund_sdf){

      $orderObj = app::get('ome')->model('orders');
      $order_detail = $orderObj->dump(array('order_id'=>$refund_sdf['order_id']),'payed');

      if ($order_detail['payed']>=round($refund_sdf['money'], 2)){
        $data = array(
            'refund_bn'     => $refund_sdf['refund_apply_bn'],
            'shop_id'       => $refund_sdf['shop_id'],
            'order_id'      => $refund_sdf['order_id'],
            'currency'      => 'CNY',
            'money'         => $refund_sdf['money'],
            'cur_money'     => $refund_sdf['money'],
            'pay_type'      => $refund_sdf['pay_type'],
            'download_time' => time(),
            'status'        => 'succ',
            'memo'          => $refund_sdf['memo'],
            'trade_no'      => $refund_sdf['alipay_no'],
            'modifiey'      => $refund_sdf['modified'],
            'payment'       => $refund_sdf['payment'],
            't_ready'       => time(),
            't_sent'        => time(),

        );
        $rs = app::get('ome')->model('refunds')->insert($data);
        if($rs){
            $applyObj = app::get('ome')->model('refund_apply');

            if ($refund_sdf['apply_id']){
                $updateData = array('status' => '4','refunded' => $data['money']);
                $filter = array('apply_id' => $refund_sdf['apply_id']);
                app::get('ome')->model('refund_apply')->update($updateData,$filter);

                app::get('ome')->model('operation_log')->write_log('refund_apply@ome', $refund_sdf['apply_id'], '极速:退款成功');
            }


            $sql ="update sdb_ome_orders set payed=IF((CAST(payed AS char)-IFNULL(0,cost_payment)-".$data['money'].")>=0,payed-IFNULL(0,cost_payment)-".$data['money'].",0)  where order_id=".$refund_sdf['order_id'];
            kernel::database()->exec($sql);
            kernel::single('ome_order_func')->update_order_pay_status($refund_sdf['order_id'], true, __CLASS__.'::'.__FUNCTION__);

            if ($refund_sdf['apply_id']){
                kernel::single('sales_aftersale')->generate_aftersale($refund_sdf['apply_id'],'refund');
            }

            $pReturnModel = $this->app->model('return_product');
            $pReturnModel->update(array('refundmoney'=>$refund_sdf['money']),array('return_id'=>$refund_sdf['return_id']));
        }

      }

    }

    //导出模板
    function exportTemplate($filter){
        foreach ($this->io_title($filter) as $v){
            $title[] = kernel::single('base_charset')->utf2local($v);
        }
        return $title;
    }

    //导入准备
    function prepared_import_csv(){
        $this->ioObj->cacheTime = time();
    }

    //导入检查
    function prepared_import_csv_row($row,$title,&$tmpl,&$mark,&$newObjFlag,&$msg)
    {
        set_time_limit(0);
        
        if(empty($row)){
            if($this->error_flag || $this->error_flag_return_items || $this->error_flag_change_items){
                unset($this->import_data);
                return false;
            }
            $error_msg = array();
            if(empty($this->import_data)){
                $msg['error'] = "无导入数据。";
                return false;
            }
            if(!empty($this->all_order_id)){
                unset($this->import_data);
                $msg['error'] = "以下订单号不不存在退货明细：".implode(",",$this->all_order_id);
                return false;
            }
            if(!empty($this->all_change_order_id)){
                unset($this->import_data);
                $msg['error'] = "以下订单号不不存在换货明细：".implode(",",$this->all_change_order_id);
                return false;
            }
            
            //全文件判断
            foreach($this->import_data as $temp_key_data => $reship_import_data)
            {
                foreach ($reship_import_data as $reship_bn_key => $temp_import_data)
                {
                    $temp_return_items = $this->get_import_return_items($temp_key_data,$temp_import_data["item"]["contents"]);
                    $temp_check_price_arr = array(
                        "bcmoney" => $temp_import_data["reship"]["contents"][0]['bcmoney'],//补偿费用
                        "bmoney" => $temp_import_data["reship"]["contents"][0]['bmoney'],//折旧(其他费用)
                        "cost_freight_money" => $temp_import_data["reship"]["contents"][0]['cost_freight_money'],//买家承担的邮费
                        "diff_order_bn" => $temp_import_data["reship"]["contents"][0]['diff_order_bn'],//补差价订单号
                        "diff_money" => $temp_import_data["reship"]["contents"][0]['diff_money'],//补差价订单金额
                        "return" => $temp_return_items,//退货明细
                    );
                    
                    $change_reship_items = $this->change_order_ids_info[$temp_key_data][$reship_bn_key];
                    if(!empty($change_reship_items)){
                        $temp_check_price_arr["change"] = $this->get_import_change_items($temp_key_data, $change_reship_items);
                        if($temp_check_price_arr["change"]["lkb_info"]){ //有福袋信息
                            foreach($temp_check_price_arr["change"]["lkb_info"] as $value_lkb_info){
                                foreach($value_lkb_info as $key_lkb => $value_lkb){
                                    $temp_check_price_arr[$key_lkb] = $value_lkb;
                                }
                            }
                            unset($temp_check_price_arr["change"]["lkb_info"]);
                        }
                        
                        $rchangeObj = kernel::single('ome_return_rchange');
                        $ome_orders = app::get('ome')->model('orders');
                        $rs_order_info = $ome_orders->dump(array("order_id"=>$temp_key_data),"shop_id");
                        $temp_check_price_arr["shop_id"] = $rs_order_info["shop_id"];
                        $temp_check_price_arr = $rchangeObj->format_rchange_data($temp_check_price_arr);
                    }
                    
                    $money = kernel::single('ome_return_rchange')->calDiffAmount($temp_check_price_arr);
                    
                    if(in_array($temp_key_data,$this->full_refund_order_ids)){ //全额退款订单
                        $money['totalmoney'] = 0;
                        if($temp_check_price_arr['bcmoney'] > $temp_import_data["reship"]["contents"][0]['payed']){
                            $error_msg[] = "订单号：".$temp_import_data["reship"]["contents"][0]['order_bn']."已经全额退款,补偿费用不能大于订单的已支付金额!";
                        }
                    }
                    
                    if ($money['totalmoney']-$temp_check_price_arr['bcmoney'] > $temp_import_data["reship"]["contents"][0]['payed']) {
                        $error_msg[] = "订单号：".$temp_import_data["reship"]["contents"][0]['order_bn']."的退款金额不能大于订单的已支付金额。";
                    }
                    
                    //退货业务, 检查退款金额
                    if($temp_import_data["reship"]["contents"][0]['return_type'] != 'change'){
                        if($money['totalmoney'] < 0){
                            $error_msg[] = "订单号：".$temp_import_data["reship"]["contents"][0]['order_bn']."的退款金额不能小于0";
                        }
                        
                        continue;
                    }
                    
                    //换货业务
                    if ($money['change_nums'] > $money['tnums']){
                        $error_msg[] = "订单号：".$temp_import_data["reship"]["contents"][0]['order_bn']."换货总数量不可以大于退货申请数量!";
                    }
                    
                    $this->validate_import_change_items($temp_check_price_arr["change"],$temp_import_data["reship"]["contents"][0]['changebranch_id'],$v_msg);
                    if($v_msg){
                        $error_msg[] = $v_msg;
                    }
                    
                    $this->import_data[$temp_key_data][$reship_bn_key]["reship"]["contents"][0]['change_items'] = $temp_check_price_arr["change"];
                }
            }
            
            if(!empty($error_msg)){
                unset($this->import_data);
                $msg['error'] = implode("     ",$error_msg);
                return false;
            }
        }
        
        $mark = false;
        $fileData = $this->import_data;
        if(!$fileData){
            $fileData = array();
        }
        
        if(substr($row[0],0,1) == '*'){
            $titleRs =  array_flip($row);
            $mark = 'title';
            
            return $titleRs;
        }else{
            if($row[0]){ //数据行首个单元格有值为有效数据(这里是order_bn值)csv内容读取数据顺序 主 退货明细 换货明细
                //先字段trim
                foreach($row as $row_key => &$row_var){
                    $row[$row_key] = trim($row_var);
                }
                unset($row_var);
                
                if(array_key_exists( '*:退入基础物料编码(必填)',$title)){ //参照页面新建：明细中bn只能是普通商品的，不能是捆绑商品。
                    if($this->error_flag || $this->error_flag_return_items){ //已有错误数据标记了 直接返回
                        return false;
                    }
                    
                    $oReship_item = $this->app->model('reship_items');
                    $ome_order_items = app::get('ome')->model('order_items');
                    
                    $order_bn = $row[0]; //订单号
                    $reship_bn = $row[1]; //退换货单号
                    $product_bn = $row[2]; //退入基础物料编码
                    $item_price = $row[3]; //销售物料售价
                    
                    //申请数量(退货明细申请数量)
                    $apply_num = intval($row[4]);
                    $row[4] = $apply_num;
                    
                    $order_id = $this->rl_order_bn_order_id[$order_bn];
                    if(empty($order_id)){
                        $msg['error'] = "订单号：". $order_bn ."主数据订单号或退入明细数据订单号异常。";
                        $this->error_flag_return_items = true;
                        return false;
                    }
                    
                    if(empty($product_bn)){
                        $msg['error'] = "订单号：". $order_bn ."请填写退入基础物料编码。";
                        $this->error_flag_return_items = true;
                        return false;
                    }
                    
                    //考虑到捆绑商品和普通商品同时存在的情况：一个product_id会有多个记录
                    $rs_items = $ome_order_items->getList("product_id,sendnum,return_num",array("order_id"=>$order_id,"bn"=>$product_bn,"delete"=>"false"));
                    if(empty($rs_items)){
                        $msg['error'] = "订单号：". $order_bn ."中不存在". $product_bn ."货品的数据有误";
                        $this->error_flag_return_items = true;
                        return false;
                    }
                    
                    //product_id
                    $product_id = $rs_items[0]["product_id"];
                    
                    //items
                    $sendnum = 0;
                    $return_num = 0;
                    foreach($rs_items as $var_return_item){
                        $sendnum += $var_return_item["sendnum"];
                        $return_num += $var_return_item["return_num"];
                    }
                    
                    if(isset($this->all_order_id_product_id[$order_id][$reship_bn][$product_id])){
                        $msg['error'] = "订单号：". $order_bn ."退入明细中基础物料编码数据重复";
                        $this->error_flag_return_items = true;
                        return false;
                    }
                    
                    if($apply_num <= 0){
                        $msg['error'] = "订单号：". $order_bn ."的". $product_bn ."退入明细中的申请数量不得为空或者小于等于0";
                        $this->error_flag_return_items = true;
                        return false;
                    }
                    
                    $can_return_num_order = $sendnum - $return_num;
                    $can_return_num_reship = $oReship_item->Get_refund_count($order_id, $product_bn);
                    
                    //订单总申请退货数量
                    $import_apply_nums = 0;
                    if(self::$_import_order_bns[$order_id][$product_id]){
                        $import_apply_nums = intval(self::$_import_order_bns[$order_id][$product_id]);
                    }
                    
                    $sum_apply_nums = $import_apply_nums + $apply_num;
                    
                    if($sum_apply_nums > $can_return_num_order || $sum_apply_nums > $can_return_num_reship){
                        $msg['error'] = "货号：". $product_bn ." 申请数量：". $sum_apply_nums ."个，大于可退数量：". min($can_return_num_order, $can_return_num_reship) ."个，错误的订单号：". $order_bn;
                        $this->error_flag_return_items = true;
                        return false;
                    }
                    
                    $this->all_order_id_product_id[$order_id][$reship_bn][$product_id] = $product_id; //用来判断订单下明细数据重复
                    
                    //同订单导入的货品总数
                    if(self::$_import_order_bns[$order_id][$product_id]){
                        self::$_import_order_bns[$order_id][$product_id] += $apply_num;
                    }else{
                        self::$_import_order_bns[$order_id][$product_id] = $apply_num;
                    }
                    
                    unset($this->all_order_id[$order_id]);
                    
                    //format
                    $rowInfo = array(
                        'order_bn' => $order_bn, //订单号
                        'reship_bn' => $reship_bn, //退换货单号
                        'product_bn' => $product_bn, //退入基础物料编码
                        'item_price' => $item_price, //销售物料售价
                        'apply_num' => $apply_num, //申请数量
                    );
                    
                    $fileData[$order_id][$reship_bn]['item']['contents'][] = $rowInfo;
                }elseif(array_key_exists( '*:换出销售物料编码(必填)',$title)){ //参照页面新建添加的是销售物料
                    //已有错误数据标记了 直接返回
                    if($this->error_flag || $this->error_flag_return_items || $this->error_flag_change_items){
                        return false;
                    }
                    
                    $order_bn = $row[0]; //订单号
                    $reship_bn = $row[1]; //退换货单号
                    $product_bn = $row[2]; //换出销售物料编码
                    $item_price = $row[3]; //销售物料售价
                    
                    //申请数量(换货明细申请数量)
                    $exchange_num = intval($row[4]);
                    $row[4] = $exchange_num;
                    
                    //订单
                    $order_id = $this->rl_order_bn_order_id[$order_bn];
                    if(empty($order_id)){
                        $msg['error'] = "订单号：". $order_bn ."主数据订单号或换出明细数据订单号异常。";
                        $this->error_flag_change_items = true;
                        return false;
                    }
                    
                    if(!isset($this->change_order_ids_info[$order_id])){
                        $msg['error'] = "订单号：". $order_bn ."不是换货类型，请删除换出明细内容。";
                        $this->error_flag_change_items = true;
                        return false;
                    }
                    
                    if(empty($product_bn)){
                        $msg['error'] = "订单号：". $order_bn ."请填写换出销售物料编码。";
                        $this->error_flag_change_items = true;
                        return false;
                    }
                    
                    if($exchange_num <= 0){
                        $msg['error'] = "订单号：". $order_bn ."的". $product_bn ."换出明细中的申请数量不得为空或者小于等于0";
                        $this->error_flag_change_items = true;
                        return false;
                    }
                    
                    //这里判当前的销售物料编码是否存在( 普通 促销 赠品 福袋 )
                    $mdl_ma_sa_ma = app::get('material')->model('sales_material');
                    $rs_sa_ma = $mdl_ma_sa_ma->dump(array("sales_material_bn"=>$product_bn));
                    if(empty($rs_sa_ma)){
                        $msg['error'] = "换出销售物料编码". $product_bn ."不存在。";
                        $this->error_flag_change_items = true;
                        return false;
                    }
                    
                    //sm_id
                    $sm_id = $rs_sa_ma['sm_id'];
                    
                    //material_type
                    $sales_material_type_name = "product";
                    if($rs_sa_ma["sales_material_type"] == 2){ //促销
                        $sales_material_type_name = "pkg";
                    }elseif($rs_sa_ma["sales_material_type"] == 3){ //赠品
                        $sales_material_type_name = "gift";
                    }elseif($rs_sa_ma["sales_material_type"] == 4){ //福袋
                        $sales_material_type_name = "lkb";
                    }elseif($rs_sa_ma["sales_material_type"] == 5){ //多选一
                        $sales_material_type_name = "pko";
                    }
                    
                    //check
                    if(isset($this->change_order_ids_info[$order_id][$reship_bn][$sales_material_type_name][$sm_id])){
                        $msg['error'] = "订单号：". $order_bn ."换出明细中销售物料编码数据重复";
                        $this->error_flag_change_items = true;
                        return false;
                    }
                    
                    $this->change_order_ids_info[$order_id][$reship_bn][$sales_material_type_name][$sm_id] = array(
                        "bn" => $product_bn,
                        "price" => $item_price,
                        "num" => $exchange_num,
                    );
                    
                    unset($this->all_change_order_id[$order_id]);
                    
                    //format
                    $rowInfo = array(
                        'order_bn' => $order_bn, //订单号
                        'reship_bn' => $reship_bn, //退换货单号
                        'product_bn' => $product_bn, //换出销售物料编码
                        'item_price' => $item_price, //销售物料售价
                        'exchange_num' => $exchange_num, //申请数量
                    );
                    
                    $fileData[$order_id][$reship_bn]['change_item']['contents'][] = $rowInfo;
                }else{
                    //已有错误数据标记了 直接返回
                    if($this->error_flag){
                        return false;
                    }
                    
                    $ome_shop = app::get('ome')->model('shop');
                    $ome_orders = app::get('ome')->model('orders');
                    $oDelivery = app::get('ome')->model('delivery');
                    $ome_branch = app::get('ome')->model('branch');
                    
                    //import_data
                    $order_bn = $row[0];
                    $reship_bn = $row[1];
                    $shop_name = $row[2];
                    $in_branch_name = $row[3];
                    $reship_type_name = $row[12];
                    $exchange_branch_name = $row[13];
                    
                    //check退换货单号
                    if(empty($reship_bn)){
                        $msg['error'] = "订单号：". $order_bn ."没有填写对应的退换货单号。";
                        $this->error_flag = true;
                        return false;
                    }
                    
                    //前端店铺名称
                    if(empty($shop_name)){
                        $msg['error'] = "订单号：". $order_bn ."的前端店铺名称不能为空。";$this->error_flag = true;return false;
                    }
                    
                    $rs_shop = $ome_shop->dump(array("name"=>$shop_name), "shop_id");
                    if(empty($rs_shop)){
                        $msg['error'] = "订单号：". $order_bn ."的前端店铺名称不正确。";$this->error_flag = true;return false;
                    }
                    $row[2] = $rs_shop["shop_id"];
                    
                    //订单号
                    $rs_order = $ome_orders->dump(array("order_bn"=>$order_bn, "shop_id"=>$rs_shop["shop_id"]));
                    if(empty($rs_order)){
                        $msg['error'] = "订单号：". $order_bn ."的数据不存在。";$this->error_flag = true;return false;
                    }
                    
                    $order_id = $rs_order['order_id'];
                    if(isset($fileData[$order_id][$reship_bn])){
                        $msg['error'] = "文件中订单号：". $order_bn .",退换货单号:". $reship_bn ."已在存在,不能重复。";$this->error_flag = true;return false;
                    }
                    
                    if($rs_order["disabled"] != "false" || $rs_order["is_fail"] != "false" || !in_array($rs_order["ship_status"],array("1","3")) || !in_array($rs_order["pay_status"],array("1","4","5"))){
                        $msg['error'] = "订单号：". $order_bn ."不符合售后要求。";$this->error_flag = true;return false;
                    }
                    
                    if($rs_order["pay_status"] == "5"){ //全额退款
                        $this->full_refund_order_ids[] = $order_id;
                    }
                    
                    //退换货单号
                    $reshipInfo = $this->dump(array('reship_bn'=>$reship_bn), 'reship_id');
                    if($reshipInfo){
                        $msg['error'] = '退换货单号：'. $reship_bn .'已经存在,不能重复导入';
                        $this->error_flag = true;
                        return false;
                    }
                    
                    //退入仓库
                    if($in_branch_name){ //有填写退入仓名的
                        $rs_branch = $ome_branch->dump(array("name"=>$in_branch_name));
                        if(empty($rs_branch)){
                            $msg['error'] = "仓库名：".$row[2]."不存在。";$this->error_flag = true;return false;
                        }
                        $row[3] = $rs_branch["branch_id"];
                    }
                    
                    //退换货类型
                    if($reship_type_name){
                        if($reship_type_name != "退货" && $reship_type_name != "换货"){
                            $msg['error'] = "退换货类型填写有误，请填写：退货或换货。";$this->error_flag = true;return false;
                        }
                        
                        if($reship_type_name == "换货"){ //换货的
                            if($rs_order["pay_status"] == "5"){ //全额退款
                                $msg['error'] = "全额退款订单：".$rs_order["order_bn"]."不能做换货。";$this->error_flag = true;return false;
                            }
                            
                            if(!$exchange_branch_name){
                                $msg['error'] = "换货请填写换出仓名。";$this->error_flag = true;return false;
                            }
                            
                            $rs_change_branch = $ome_branch->dump(array("name"=>$exchange_branch_name));
                            if(empty($rs_change_branch)){
                                $msg['error'] = "换出仓库名：". $exchange_branch_name ."不存在。";$this->error_flag = true;return false;
                            }
                            
                            $row[13] = $rs_change_branch["branch_id"];
                            
                            $this->change_order_ids_info[$order_id] = array(
                                "order_bn" => $order_bn,
                                "changebranch_id" => $rs_change_branch["branch_id"],
                            );
                            
                            //验证换货明细所需
                            $this->all_change_order_id[$order_id] = $order_bn; //验证订单换货必须要有换出货品明细
                        }
                    }
                    
                    //补差价订单
                    if($row[10]){
                        $order = $ome_orders->select()->columns('order_id')
                        ->where('order_bn=?', $row[10])
                        ->where('pay_status=?','1')
                        ->where('ship_status=?','0')
                        ->where('status=?','active')
                        ->instance()->fetch_row();
                        
                        if(empty($order)){
                            $msg['error'] = "订单号：". $order_bn ."补差价订单有误。";$this->error_flag = true;return false;
                        }
                    }
                    
                    //delivery
                    $deliveryList = $oDelivery->getDeliveryByOrder('*', $order_id);
                    if(empty($deliveryList)){
                        $msg['error'] = '退换货单号：'. $reship_bn .'没有对应发货单,请检查发货单状态!';
                        $this->error_flag = true;
                        return false;
                    }
                    
                    $this->rl_order_bn_order_id[$order_bn] = $rs_order["order_id"]; //明细中用 上面已判重复订单 这里可以用order_bn作为key的数据组数据
                    $this->all_order_id[$rs_order["order_id"]] = $order_bn; //验证订单退货必须要有退入货品明细
                    
                    //format
                    $rowInfo = array(
                        'order_bn' => $row[0], //订单号
                        'reship_bn' => $reship_bn, //退换货单号
                        'shop_id' => $row[2], //店铺shop_id
                        'in_branch_id' => $row[3], //退入仓库branch_id
                        'return_logi_name' => $row[4], //退回物流公司
                        'return_logi_no' => $row[5], //退回物流单号
                        'memo' => $row[6], //备注
                        'bcmoney' => $row[7], //补偿费用
                        'bmoney' => $row[8], //折旧(其他费用)
                        'cost_freight_money' => $row[9], //买家承担的邮费
                        'diff_order_bn' => $row[10], //补差价订单号
                        'diff_money' => $row[11], //补差价订单金额
                        'return_type' => ($row[12] == '换货' ? 'change' : 'return'), //退换货类型
                        'changebranch_id' => $row[13], //换出仓库branch_id
                        'flag_type' => ($row[14] == '原单退' || $row[14] == '原单') ? ome_reship_const::__LANJIE_RUKU : 0, //标记类型
                        'payed' => $rs_order['payed'], //订单支付金额(验证金额时用)
                    );
                    
                    $fileData[$order_id][$reship_bn]['reship']['contents'][] = $rowInfo;
                }
                
                $this->import_data = $fileData;
            }
        }
        
        return null;
    }

    //导入退换货单
    function finish_import_csv()
    {
        header("Content-type: text/html; charset=utf-8");
        
        $data = $this->import_data;
        unset($this->import_data);
        
        //格式化数据
        $oOrders = $this->app->model('orders');
        $oDelivery = $this->app->model('delivery');
        $oMember = $this->app->model('members');
        $count = 0;
        $limit = 50;
        $page = 0;
        $orderSdfs = array();
        
        //list
        foreach($data as $order_id => $reshipList)
        {
            foreach($reshipList as $reship_bn => $var_d)
            {
                $order_bn = $var_d['reship']['contents'][0]['order_bn'];
                $reship_bn = $var_d['reship']['contents'][0]['reship_bn'];
                $shop_id = $var_d['reship']['contents'][0]['shop_id'];
                $in_branch_id = $var_d['reship']['contents'][0]['in_branch_id'];
                $return_logi_name = $var_d['reship']['contents'][0]['return_logi_name'];
                $return_logi_no = $var_d['reship']['contents'][0]['return_logi_no'];
                $memo = $var_d['reship']['contents'][0]['memo'];
                $bcmoney = $var_d['reship']['contents'][0]['bcmoney'];
                $bmoney = $var_d['reship']['contents'][0]['bmoney'];
                $cost_freight_money = $var_d['reship']['contents'][0]['cost_freight_money'];
                $diff_order_bn = $var_d['reship']['contents'][0]['diff_order_bn'];
                $diff_money = $var_d['reship']['contents'][0]['diff_money'];
                $return_type = $var_d['reship']['contents'][0]['return_type'];
                $changebranch_id = $var_d['reship']['contents'][0]['changebranch_id'];
                $flag_type = $var_d['reship']['contents'][0]['flag_type'];
                $payed = $var_d['reship']['contents'][0]['payed'];
                
                //换出明细列表
                $change_items = $var_d['change_item']['contents'];
                
                //订单信息
                $order = $oOrders->dump(array('order_id'=>$order_id),'*');
                
                //会员信息
                $member = $oMember->dump(array('member_id'=>$order['member_id']));
                $delivery = $oDelivery->getDeliveryByOrder('*', $order_id);
                
                unset($delivery[0]['shop_id']);
                
                $order = array_merge($order,$delivery[0]);
                $order['member_id'] = $member['account']['uname'];
                
                //退货明细获取
                $return_items = $this->get_import_return_items($order_id, $var_d["item"]["contents"]);
                
                //退回物流公司名
                if($return_logi_name){
                    $mdl_dc = app::get('ome')->model('dly_corp');
                    $rs_dc = $mdl_dc->dump(array("name"=>$return_logi_name));
                    if(!empty($rs_dc)){
                        $return_logi_name = $rs_dc["corp_id"];
                    }
                }
                
                //sdf
                $orderSdf = array(
                    "reship_bn" => $reship_bn, //导入的退换货单号
                    "order_id" => $order_id,
                    'order_bn' => $order_bn,
                    "shop_id" => $shop_id,
                    "branch_id" => ($in_branch_id ? $in_branch_id : $order['branch_id']), //退入仓ID
                    "return_logi_name" => $return_logi_name, //退回物流公司名(参考原代码这里存logi_id)
                    "return_logi_no" => $return_logi_no, //退回物流单号
                    "memo" => $memo, //备注
                    "flag_type" => $flag_type, //备注
                    "logi_name" => $order["logi_name"], //订单物流公司
                    "logi_no" => $order["logi_no"], //订单物流单号
                    "logi_id" => $order["logi_id"], //订单物流ID
                    "ship_name" => $order["ship_name"], //收货人姓名
                    "ship_area" => $order["ship_area"], //收货人地区
                    "delivery" => $order["delivery"],
                    "ship_zip" => $order["ship_zip"], //收货人邮编
                    "ship_tel" => $order["ship_tel"], //收货人电话
                    "ship_email" => $order["ship_email"], //收货人邮箱
                    "ship_mobile" => $order["ship_mobile"], //收货人手机
                    "is_protect" => $order["is_protect"],
                    "source" => "import", //给空
                    "member_id" => $order["member_id"], //用户名
                    "return_type" => $return_type, //退换货类型(change:换货,return:退货)
                    "bcmoney" => ($bcmoney ? $bcmoney : 0),//补偿费用
                    "bmoney" => ($bmoney ? $bmoney : 0),//折旧(其他费用)
                    "cost_freight_money" => ($cost_freight_money ? $cost_freight_money : 0),//买家承担的邮费
                    "diff_order_bn" => $diff_order_bn,//补差价订单号
                    "diff_money" => $diff_money,//补差价订单金额
                    "return" => $return_items,//退货明细
                    "change" => array("objects"=>NULL),//退货明细
                );
                
                //换货
                if($return_type == 'change'){
                    $orderSdf['return_type'] = 'change';
                    $orderSdf['changebranch_id'] = $changebranch_id; //换出仓branch_id
                    
                    //check
                    $changeList = [];
                    if(empty($change_items)){
                        $orderSdf['import_error_msg'] = '换出商品明细不存在';
                    }else{
                        //格式化换货明细
                        $import_error_msg = '';
                        $changeList = $this->format_import_change_items($orderSdf, $change_items, $import_error_msg);
                        if(empty($changeList)){
                            $orderSdf['import_error_msg'] = '格式化换出商品为空('. $import_error_msg .')';
                        }
                    }
                    
                    //格式化后的换出明细
                    $orderSdf['change'] = $changeList;
                }
                
                //按每条执行一次任务
                $orderSdfs[$page][] = $orderSdf;
                
                $page++;
            }
        }
        
        $oQueue = app::get('base')->model('queue');
        foreach($orderSdfs as $v)
        {
            $original_bn = ($v[0]['reship_bn'] ? $v[0]['reship_bn'] : $v[0]['order_bn']);
            
            $queueData = array(
                'queue_title'=>'退换货单导入'.$original_bn,
                'start_time'=>time(),
                'params'=>array(
                        'sdfdata'=>$v,
                        'app' => 'ome',
                        'mdl' => 'reship'
                ),
                'worker'=>'ome_reship_import.run',
            );
            $oQueue->save($queueData);
        }
        
        return null;
    }

    private function get_import_return_items($order_id,$import_contents){
        $ome_orders_items = $this->app->model('order_items');
        $oReship_item = $this->app->model('reship_items');
        $rs_items = $ome_orders_items->getList("*",array("order_id"=>$order_id,"delete"=>"false"));
        $result_arr = array();
        
        $import_contents = array_column($import_contents, null, 'product_bn');
        
        $reshipObj = $this->app->model('reship');
        $tmpsale = $reshipObj->getSalepriceByorderId($order_id);
        
        foreach($rs_items as $var_ri)
        {
            $order_item_id = $var_ri['item_id'];
            $product_bn = $var_ri['bn'];
            $validNum = $var_ri['sendnum'] - $var_ri['return_num'];
            if($import_contents[$product_bn]['apply_num'] < 1 || $validNum < 1) {
                continue;
            }
            if($validNum < $import_contents[$product_bn]['apply_num']) {
                $num = $validNum;
            } else {
                $num = $import_contents[$product_bn]['apply_num'];
            }
            $import_contents[$product_bn]['apply_num'] -= $num;
            
            $result_arr["goods_bn"][] = $order_item_id;
            $result_arr["num"][$order_item_id] = $num;
            $result_arr["goods_name"][$order_item_id] = $var_ri["name"];
            $result_arr["goods_name"][$order_item_id] = $var_ri["name"];
            
            //退货货品
            $result_arr['product_id'][$order_item_id] = $var_ri['product_id'];
            $result_arr['bn'][$order_item_id] = $product_bn;
            
            $result_arr["effective"][$order_item_id] = $var_ri["sendnum"];
            
            $rs_branch = $oReship_item->getBranchCodeByBnAndOd($product_bn, $order_id);
            
            $result_arr["branch_id"][$order_item_id] = $rs_branch[0]["branch_id"];

            
            if($import_contents[$product_bn]['item_price'] == ''){
                //订单object层的销售金额
                $sales_amount = $tmpsale[$product_bn];
                if(is_array($sales_amount)){
                    $sales_amount = $sales_amount[$product_bn];
                }
                
                //基础物料销售金额
                if($var_ri['item_type'] == 'lkb'){
                    //取订单明细上的实付金额(福袋销售物料关联多个福袋会有多个相同的基础物料)
                    $sale_price = $var_ri['divide_order_fee'];
                }else{
                    //取销售单明细上的销售金额
                    $sale_price = $sales_amount > 0 ? $sales_amount : $var_ri['sale_price'];
                }
                
                $result_arr["price"][$order_item_id] = $sale_price / $var_ri["nums"];
            } else {
                $result_arr["price"][$order_item_id] = $import_contents[$product_bn]['item_price'];
            }
            
            //cache pirce
            self::$_importBnPirces[$order_id][$product_bn] = $result_arr["price"][$order_item_id];
        }
        
        return $result_arr;
    }

    private function get_import_change_items($order_id,$import_change_content){
        $mdl_sa_ma = app::get('material')->model('sales_material');
        $mdl_sa_ma_ext = app::get('material')->model('sales_material_ext');
        $result_arr = array();
        $key_material_type_name_arr = array("product","pkg","gift","lkb","pko");
        foreach($import_change_content as $key_material_type_name => $val_arr){
            if(!in_array($key_material_type_name,$key_material_type_name_arr)){
                continue;
            }
            foreach($val_arr as $key_a => $value_a){
                $rs_sa_ma = $mdl_sa_ma->dump(array("sm_id"=>$key_a));
                $rs_sa_ma_ext = $mdl_sa_ma_ext->dump(array("sm_id"=>$key_a));
                $result_arr[$key_material_type_name]["name"][$rs_sa_ma["sales_material_bn"]] = $rs_sa_ma["sales_material_name"];
                $result_arr[$key_material_type_name]["num"][$rs_sa_ma["sales_material_bn"]] = $value_a["num"];
                if($value_a["price"] > 0){
                    $result_arr[$key_material_type_name]["price"][$rs_sa_ma["sales_material_bn"]] = $value_a["price"];
                }elseif($rs_sa_ma_ext["retail_price"]){
                    $result_arr[$key_material_type_name]["price"][$rs_sa_ma["sales_material_bn"]] = $rs_sa_ma_ext["retail_price"];
                }else{
                    $result_arr[$key_material_type_name]["price"][$rs_sa_ma["sales_material_bn"]] = 0;
                }
                $result_arr[$key_material_type_name]["item_id"][$rs_sa_ma["sales_material_bn"]] = "";
                $result_arr[$key_material_type_name]["product_id"][$rs_sa_ma["sales_material_bn"]] = $key_a;
                $result_arr[$key_material_type_name]["bn"][] = $rs_sa_ma["sales_material_bn"];
                
            }
        }
        return $result_arr;
    }

    private function validate_import_change_items($format_import_change_content,$changebranch_id,&$v_msg){
        $libBranchProduct = kernel::single('ome_branch_product');
        foreach($format_import_change_content['objects'] as $objects){
            foreach($objects['items'] as $item){
                $bm_id = $item['bm_id'];
                $change_num = $item['change_num'];
                //根据选择的换货仓库 获取基础物料库存
                $temp_store = $libBranchProduct->getAvailableStore($changebranch_id, array($bm_id));
                $store_num = $temp_store[$bm_id];
                if ($change_num < 1){
                    $v_msg = '换出销售物料中,关联的基础物料为:['. $item['material_bn'] .']申请数量为0，申请被拒绝。';
                    return false;
                }
                if ($store_num < 1){
                    $v_msg = '换出销售物料中,关联的基础物料为:['. $item['material_bn'] .']实际的库存为0，申请被拒绝。';
                    return false;
                }
                if ($change_num > $store_num){
                    $v_msg = '换出销售物料中,关联的基础物料为:['. $item['material_bn'] .']申请数量大于实际的库存。申请被拒绝。';
                    return false;
                }
            }
        }
    }
    
    /**
     * 退货单增加赠品明细
     * @param $items
     * @param $order_id
     * @param int $branch_id
     * @param int $op_id
     * @return array
     */
    public function addReturnGiftItems($items, $order_id, $branch_id = 0, $op_id = 0)
    {
        //判断当前订单是否存在，不存在查询赠品
        $newData        = array();
        $orderItemLists = app::get('ome')->model('order_items')->getList('product_id,bn,name,nums,price,item_id as order_item_id,item_type,sendnum',
            array('order_id' => $order_id, 'item_type' => 'gift', 'delete' => 'false'));

        // 获取平台赠品明细
        foreach ($items as $item) {
            if ($item['order_item_id']) {
                $ite = app::get('ome')->model('order_items')->dump(['item_id' => $item['order_item_id'],'order_id' => $order_id], 'obj_id');

                if (!$ite) {
                    continue;
                }

                $obj = app::get('ome')->model('order_objects')->dump(['obj_id' => $ite['obj_id'],'order_id' => $order_id], 'oid');

                if (!$obj) {
                    continue;
                }

                $orderObjects = app::get('ome')->model('order_objects')->getList('obj_id,main_oid', [
                    'order_id' => $order_id,
                    'main_oid|findinset' => $obj['oid'],
                    'delete' => 'false',
                    'sale_price' => '0',
                ]);

                if ($orderObjects) {
                    $orderItems = app::get('ome')->model('order_items')->getList('product_id,bn,name,nums,price,item_id as order_item_id,item_type,sendnum',
                    [
                        'order_id' => $order_id,
                        'obj_id' => array_column($orderObjects, 'obj_id'),
                        'delete' => 'false',
                        'item_id|notin' => $orderItemLists ? array_column($orderItemLists, 'order_item_id') : ['0'],
                    ]);

                    if ($orderItems) {
                        $orderItemLists = array_merge((array)$orderItemLists, (array)$orderItems);
                    }
                }
            }
        }

        if ($orderItemLists) {
            $orderItemIds = array_column($orderItemLists, 'order_item_id');
            $itemsItems = array_column($items, 'order_item_id');
            $reshipItems  = app::get('ome')->model('reship_items')->getList('reship_id', array('order_item_id' => $orderItemIds));
            $isAdd        = true;
            if ($reshipItems) {
                $reship = app::get('ome')->model('reship')->db_dump(array('reship_id'    => current($reshipItems)['reship_id'], 'is_check|noequal' => 5 ));
                if ($reship) {
                    $isAdd = false;
                }
            }
            if ($isAdd) {
                foreach ($orderItemLists as $val) {
                    if (in_array($val['order_item_id'],$itemsItems)) {
                        continue;
                    }
                    $newData[] = array(
                        'product_id'    => $val['product_id'] ? $val['product_id'] : 0,
                        'bn'            => $val['bn'],
                        'name'          => $val['name'],
                        'product_name'  => $val['name'],
                        'num'           => $val['nums'],
                        'price'         => $val['price'],
                        'branch_id'     => $branch_id,
                        'order_item_id' => $val['order_item_id'],
                        'op_id'         => $op_id ? $op_id : '',
                        'item_type'     => $val['item_type'],
                        'sendNum'       => $val['sendnum'],
                    );
                }
            }
        }
        return $newData;
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
            return $ship_name;
        }
        
        $is_encrypt = kernel::single('ome_security_hash')->check_encrypt($ship_name);
        
        if (!$is_encrypt) return $ship_name;
        $base_url = kernel::base_url(1);$order_id = $row['_0_order_id'];
    
        $encryptShipName = kernel::single('ome_view_helper2')->modifier_ciphertext($ship_name,'order','ship_name');
        
        $return =<<<HTML
<a class="data-hide" href="javascript:void(0);" onclick="Ex_Loader('security',function(){new Security({url:'index.php?app=ome&ctl=admin_order&act=showSensitiveData&p[0]={$order_id}',clickElement:\$(event.target)}).desHtml(\$(event.target).getNext()); });"></a><span><span sensitive-field="ship_name">{$encryptShipName}</span></span>
HTML;
        return $ship_name?$return:$ship_name;
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
            return $ship_addr;
        }
        
        $is_encrypt = kernel::single('ome_security_hash')->check_encrypt($ship_addr);
        
        if (!$is_encrypt) return $ship_addr;
        
        $base_url = kernel::base_url(1);$order_id = $row['_0_order_id'];
        $encryptAddr = kernel::single('ome_view_helper2')->modifier_ciphertext($ship_addr,'order','ship_addr');
        $return =<<<HTML
<a class="data-hide" href="javascript:void(0);" onclick="Ex_Loader('security',function(){new Security({url:'index.php?app=ome&ctl=admin_order&act=showSensitiveData&p[0]={$order_id}',clickElement:\$(event.target)}).desHtml(\$(event.target).getNext()); });"></a><span><span sensitive-field="ship_addr">{$encryptAddr}</span></span>
HTML;
        return $ship_addr?$return:$ship_addr;
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
            return $mobile;
        }
        
        $is_encrypt = kernel::single('ome_security_hash')->check_encrypt($mobile);
        
        if (!$is_encrypt) return $mobile;
        
        $base_url = kernel::base_url(1);$order_id = $row['_0_order_id'];
        $encryptMobile = kernel::single('ome_view_helper2')->modifier_ciphertext($mobile,'order','ship_mobile');
        $return =<<<HTML
<a class="data-hide" href="javascript:void(0);" onclick="Ex_Loader('security',function(){new Security({url:'index.php?app=ome&ctl=admin_order&act=showSensitiveData&p[0]={$order_id}',clickElement:\$(event.target)}).desHtml(\$(event.target).getNext()); });"></a><span><span sensitive-field="ship_mobile">{$encryptMobile}</span></span>
HTML;
        return $mobile?$return:$mobile;
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
        
        $reshipListData = kernel::single('ome_func')->exportDataMain(__CLASS__, $params);
        if (!$reshipListData) {
            return false;
        }
        
        //根据选择的字段定义导出的第一行标题
        if ($curr_sheet == 1) {
            $data['content']['main'][] = $this->getCustomExportTitle($reshipListData['title']);
        }
        
        $reship_items_columns = array_values($this->reshipItemsExportTitle());
        
        $reship_ids   = array_column($reshipListData['content'], 'reship_id');
        $main_columns = array_values($reshipListData['title']);
        $reshipList   = $reshipListData['content'];
        //所有的子销售数据
        $reship_items = $this->getexportdetail('*', array('reship_id' => $reship_ids));
        foreach ($reshipList as $reshipRow) {
            $objects      = $reship_items[$reshipRow['reship_id']];
            $items_fields = implode(',', $reship_items_columns);
            $all_fields   = implode(',', $main_columns) . ',' . $items_fields;
            if ($objects) {
                foreach ($objects as $obj) {
                    $reshipDataRow = array_merge($reshipRow, $obj);
                    $exptmp_data   = [];
                    foreach (explode(',', $all_fields) as $key => $col) {
                        if (isset($reshipDataRow[$col])) {
                            $reshipDataRow[$col] = mb_convert_encoding($reshipDataRow[$col], 'GBK', 'UTF-8');
                            $exptmp_data[]       = $reshipDataRow[$col];
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
        $main_title        = array_keys($main_title);
        $order_items_title = array_keys($this->reshipItemsExportTitle());
        $title             = array_merge($main_title, $order_items_title);
        return mb_convert_encoding(implode(',', $title), 'GBK', 'UTF-8');
    }
    
    
    /**
     * reshipItemsExportTitle
     * @return mixed 返回值
     */
    public function reshipItemsExportTitle()
    {
        $items_title = array(
            '商品货号' => 'bn',
            '仓库名称' => 'branch_name',
            '商品名称' => 'product_name',
            '明细退货类型' => 'item_return_type',
            '申请数量' => 'num',
            '良品'   => 'normal_num',
            '不良品'  => 'defective_num',
            'GAP'  => 'gap',
        );
        return $items_title;
    }
    
    /**
     * [格式化]导入换出商品明细
     * 
     * @param $order_id
     * @param $import_contents
     * @return void
     */
    public function format_import_change_items($orderInfo, $changeItems, &$error_msg=null)
    {
        $salesMaterialExtObj = app::get('material')->model('sales_material_ext');
        
        $salesMLib = kernel::single('material_sales_material');
        $rchangeObj = kernel::single('ome_return_rchange');
        
        //params
        $order_id = $orderInfo['order_id'];
        $shop_id = $orderInfo['shop_id'];
        
        //check
        if(empty($order_id) || empty($shop_id) || empty($orderInfo['changebranch_id'])){
            $error_msg = '订单、店铺、换出仓库不能为空';
            return false;
        }
        
        //获取销售物料类型
        $salesMaterialTypeList = $salesMLib->getSalesMaterialTypeList();
        
        //foramt
        $changeList = [];
        foreach ($changeItems as $itemKey => $itemVal)
        {
            $sales_material_bn = $itemVal['product_bn'];
            
            //换出销售物料信息
            $salesMInfo = $salesMLib->getSalesMByBn($shop_id, $sales_material_bn);
            if(empty($salesMInfo)){
                $error_msg = '换出商品：'. $sales_material_bn .'不存在';
                return false;
            }
            
            //type
            $sales_material_type = $salesMInfo['sales_material_type'];
            $obj_type = $salesMaterialTypeList[$sales_material_type]['type'];
            if($obj_type == 'goods'){
                $obj_type = 'product';
            }
            
            //pirce：导入换出商品,没有填写价格;
            if(empty($itemVal['item_price']) && $itemVal['item_price'] !== 0 && $itemVal['item_price'] !== 0.00){
                if(isset(self::$_importBnPirces[$order_id][$sales_material_bn]) && self::$_importBnPirces[$order_id][$sales_material_bn] > 0){
                    $item_price = self::$_importBnPirces[$order_id][$sales_material_bn];
                }else{
                    $salesExtInfo = $salesMaterialExtObj->dump(array('sm_id'=>$salesMInfo['sm_id']), 'sm_id,cost,retail_price');
                    $item_price = floatval($salesExtInfo['retail_price']);
                }
            }else{
                $item_price = floatval($itemVal['item_price']);
            }
            
            //拼接POST提交换货数据
            $changeList[$obj_type]['name'][$sales_material_bn] = $salesMInfo['sales_material_name'];
            $changeList[$obj_type]['num'][$sales_material_bn] = intval($itemVal['exchange_num']);
            $changeList[$obj_type]['price'][$sales_material_bn] = $item_price;
            $changeList[$obj_type]['product_id'][$sales_material_bn] = $salesMInfo['sm_id'];
            $changeList[$obj_type]['bn'][$sales_material_bn] = $salesMInfo['sales_material_bn'];
            //$changeList[$obj_type]['sale_store'][$sales_material_bn] = $salesMInfo['sale_store'];
            //$changeList[$obj_type]['item_id'][$sales_material_bn] = '';
        }
        
        //Post
        $orderInfo['change'] = $changeList;
        
        //格式化换货数据
        $post = $rchangeObj->format_rchange_data($orderInfo);
        if(empty($post['change'])){
            $error_msg = '换出商品格式化为空';
            return false;
        }
        
        return $post['change'];
    }
}
?>
