<?php
class ome_return_product extends ome_abstract
{
    /**
     * $err_msg
     *
     * @param $status_type
     * @param $return_id
     * @return array
     */
    function batch_update($status_type,$return_id){
        set_time_limit(0);
        $need_return_id = array();
        foreach ($return_id as $return_id ) {
            $return_id = explode('||',$return_id);
            $need_return_id[] = $return_id[1];
        }
        $oReturn = app::get('ome')->model('return_product');
        $sql = 'SElECT shop_id,shop_type,source,return_id,return_bn,return_type FROM sdb_ome_return_product WHERE return_id in ('.implode(',',$need_return_id).') AND `status` in (\'1\',\'2\')';

        $return_list = $oReturn->db->select($sql);
        $fail = 0;
        $error_msg = array();
        if ($status_type == 'agree') {
            foreach ( $return_list as $return ) {
                $return_id = $return['return_id'];
                $rs = array();$api = FALSE;
                $adata = array(
                    'choose_type_flag'=>'1',
                    'status'=>'3',
                    'return_id'=>$return_id,
                );

                if ($return['source'] == 'matrix') {
                    $mod = 'sync';
                    if ($return['shop_type'] == 'tmall' || $return['shop_type']== 'meilishuo') {
                        $api = TRUE;
                        $adata['choose_type_flag'] = '0';
                    }
                    if ($return['return_type'] == 'change'){
                        $rs = kernel::single('ome_service_aftersale')->update_status($return_id,'6',$mod);
                    }else{
                        $rs = kernel::single('ome_service_aftersale')->update_status($return_id,'3',$mod);
                    }
                }
                
                if ($rs && $rs['rsp'] == 'fail') {
                    $fail++;
                    $error_msg[] = '单号:'.$return['return_bn'].",".$rs['msg'];
                }else{
                    $oReturn->tosave($adata,$api, $error_msg[]);

                    //更新退换货单上最后合计金额totalmoney
                    if($adata['status'] == 3 && $adata['choose_type_flag'])
                    {
                        kernel::single('ome_return_rchange')->update_totalmoney($return_id);
                    }
                }
            }
        }elseif($status_type == 'refuse') {
            $batchList = kernel::single('ome_refund_apply')->return_batch('refuse_return');
            $api = false;
            
            foreach ( $return_list as $return ) {
                $return_id = $return['return_id'];
                $rs = array();
                $adata = array(
                    'status'=>'5',
                    'return_id'=>$return_id,
                );
                if ($return['source'] == 'matrix') {
                    $return_batch = $batchList[$return['shop_id']];
                    $picurl = $return_batch['picurl'];
                    if ( $return['shop_type'] == 'tmall' ) {
                        $picurl = file_get_contents($picurl);
                        $picurl = base64_encode($picurl);
                    }
                    $memo = array(
                       'refuse_message'=>$return_batch['memo'],
                        'refuse_proof'=>$picurl,
                        'imgext'=>$return_batch['imgext'],
                    );
                    $rs = kernel::single('ome_service_aftersale')->update_status($return_id,'5','sync',$memo);
                }
                if ($rs && $rs['rsp'] == 'fail') {
                    $fail++;
                    $error_msg[] = '单号:'.$return['return_bn'].",".$rs['msg'];
                }else{
                    $oReturn->tosave($adata,$api, $error_msg[]);
                }
            }
        }

        $result = array('error_msg'=>$error_msg,'fail'=>$fail);

        return $result;
    }

    /**
    * 获取可操作列表
    */
    function return_list($return_id){
        set_time_limit(0);
        $oReturn = app::get('ome')->model('return_product');
        $sql = 'SElECT return_id FROM sdb_ome_return_product WHERE return_id in ("'.implode('","',$return_id).'") AND `status` in (\'1\',\'2\')';

        $return_list = $oReturn->db->select($sql);
        $need_return = array();
        foreach ( $return_list as $return ) {
            $need_return[] = $return['return_id'];
        }

        return $need_return;
    }
    
    /**
     * 格式化售后申请单数据
     *
     * @param $sdf
     * @param $error_msg string
     * @return array
     */
    public function _formatReturnProductData($sdf, &$error_msg=null)
    {
        $reshipMdl = app::get('ome')->model('reship');
        
        //check
        if(empty($sdf['return_bn']) || empty($sdf['shop_id']) || empty($sdf['shop_type'])){
            $error_msg = '无效的售后申请单数据';
            return array();
        }
        
        if(empty($sdf['order_id']) || empty($sdf['delivery_id'])){
            $error_msg = '售后申请单参数不正确';
            return array();
        }
        
        //items
        $itemList = ($sdf['refund_item_list'] ? $sdf['refund_item_list'] : $sdf['return_product_items']);
        if(empty($itemList)){
            $error_msg = '售后申请单明细不存在';
            return array();
        }
        
        //title
        if(empty($sdf['title'])){
            if(empty(empty($sdf['order_bn']))){
                $error_msg = '售后申请的订单号不能为空';
                return array();
            }
            
            $title = $sdf['order_bn'] . '售后申请单';
        }else{
            $title = $sdf['title'];
        }
        
        //add_time
        if($sdf['created']){
            $add_time = $sdf['created'];
        }elseif($sdf['add_time']){
            $add_time = $sdf['add_time'];
        }else{
            $add_time = time();
        }
        
        //outer_lastmodify
        if($sdf['modified']){
            $outer_lastmodify = $sdf['modified'];
        }elseif($sdf['outer_lastmodify']){
            $outer_lastmodify = $sdf['outer_lastmodify'];
        }else{
            $outer_lastmodify = time();
        }
        
        //branch_id
        $sdf['branch_id'] = ($sdf['branch_id'] ? $sdf['branch_id'] : 0);
        
        //master
        $data = array(
            'return_bn' => $sdf['return_bn'],
            'order_id' => $sdf['order_id'],
            'delivery_id' => $sdf['delivery_id'],
            'shop_id' => $sdf['shop_id'],
            'shop_type' => $sdf['shop_type'],
            'member_id' => $sdf['member_id'],
            'org_id' => $sdf['org_id'],
            'title' => $title,
            'status' => ($sdf['status'] ? $sdf['status'] : '1'),
            'op_id' => $sdf['op_id'],
            'refundmoney' => $sdf['refund_fee'],
            'money' => $sdf['refund_fee'],
            'shipping_type' => $sdf['shipping_type'],
            'source' => ($sdf['source'] ? $sdf['source'] : 'matrix'), //来源
            'flag_type' => $sdf['flag_type'],
            'platform_status' => $sdf['platform_status'], //平台售后单状态
            'apply_remark' => $sdf['apply_remark'], //售后申请描述
            'kinds' => 'reship', //售后类型
            'jsrefund_flag' => ($sdf['jsrefund_flag'] ? $sdf['jsrefund_flag'] : '0'), //极速退款标识
            'content' => ($sdf['content'] ? $sdf['content'] : $sdf['reason']),
            'comment' => ($sdf['comment'] ? $sdf['comment'] : $sdf['desc']),
            'memo' => $sdf['memo'],
            'add_time' => $add_time,
            'outer_lastmodify' => $outer_lastmodify,
        );
        
        //来源&&拦截入库
        if($sdf['refund_to_returnProduct']) {
            $data['source'] = 'refund';
            $data['flag_type'] = $data['flag_type'] | ome_reship_const::__LANJIE_RUKU;
        }elseif($sdf['source'] == 'delivery_back'){
            $data['flag_type'] = $data['flag_type'] | ome_reship_const::__LANJIE_RUKU;
        }
        
        //售后类型(默认是：return退货)
        if ($sdf['return_type']){
            $data['return_type'] = $sdf['return_type'];
        }
        
        //平台订单号
        if($sdf['platform_order_bn']){
            $data['platform_order_bn'] = $sdf['platform_order_bn'];
        }
        
        //来源店铺发货模式(self=>自发,jingxiao=>经销)
        if ($sdf['delivery_mode'] == 'jingxiao') {
            $data['delivery_mode'] = $sdf['delivery_mode'];
        }
        
        //items
        $isFail = 'false';
        $items = array();
        foreach($itemList as $itemInfo)
        {
            $product_id = intval($itemInfo['product_id']);
            
            //item
            $items[] = array(
                'product_id' => $product_id,
                'bn' => $itemInfo['bn'],
                'name' => $itemInfo['title'] ? $itemInfo['title']: $itemInfo['name'],
                'num' => $itemInfo['num'],
                'price' => $itemInfo['price'],
                'amount' => $itemInfo['amount'],
                'branch_id' => $sdf['branch_id'],
                'order_item_id' => $itemInfo['order_item_id'],
                'shop_goods_bn' => $itemInfo['shop_goods_bn'],
                'obj_type' => $itemInfo['obj_type'],
                'quantity' => $itemInfo['quantity'],
            );
            
            if(empty($product_id)) {
                $isFail = 'true';
            }
        }
        
        $data['is_fail'] = $isFail;
        $data['return_product_items'] = $items;
        
        //退货单增加赠品明细
        $return_gift_items = $reshipMdl->addReturnGiftItems($data['return_product_items'], $data['order_id'], $sdf['branch_id']);
        if($return_gift_items){
            $data['return_gift_items'] = $return_gift_items;
        }
        
        //售后问题类型
        if ($sdf['reason']) {
            $problemMdl = app::get('ome')->model('return_product_problem');
            $problem = $problemMdl->db_dump(['problem_name' => $sdf['reason']]);
            if (!$problem) {
                $problem = [
                    'problem_name' => $sdf['reason'],
                    'last_sync_time' => time(),
                    'createtime' => time(),
                ];
                
                $problemMdl->save($problem);
            }
            
            $data['problem_id'] = $problem['problem_id'];
        }
        
        return $data;
    }
    
    /**
     * 根据发货单ID获取售后服务单数据
     *
     * @param $deliveryInfo array
     * @param $error_msg string
     * @return array
     */
    public function getReturnProductByDelivery($delivery_id, &$error_msg=null)
    {
        $deliveryObj = app::get('ome')->model('delivery');
        $deliveryOrderObj = app::get('ome')->model('delivery_order');
        $deliveryDetailMdl = app::get('ome')->model('delivery_items_detail');
        $basicMaterialMdl = app::get('material')->model('basic_material');
        $orderObjMdl = app::get('ome')->model('order_objects');
        
        //操作人
        $opInfo = kernel::single('ome_func')->getDesktopUser();
        
        //获取发货单信息
        $filter = ['delivery_id'=>$delivery_id];
        $deliveryList = $deliveryObj->getList('*', $filter);
        if(empty($deliveryList)){
            $error_msg = '主发货单信息不存在';
            return false;
        }
        $deliveryRow = $deliveryList[0];
        
        //获取子发货单列表(合并发货单有多个子发货单)
        if($deliveryRow['is_bind'] == 'true'){
            $filter = ['parent_id'=>$delivery_id];
            $deliveryList = $deliveryObj->getList('*', $filter);
        }
        
        //delivery_id
        $deliveryIds = array_column($deliveryList, 'delivery_id');
        
        //order delivery
        $tempList = $deliveryOrderObj->getlist('*', ['delivery_id' => $deliveryIds], 0, -1);
        $deliveryOrderList = array_column($tempList, null, 'delivery_id');
        
        //delivery_items_detail
        $tempList = $deliveryDetailMdl->getlist('*', ['delivery_id'=>$deliveryIds], 0, -1);
        if(empty($tempList)){
            $error_msg = '发货单明细详情信息不存在';
            return false;
        }
        
        $detailList = [];
        $productIds = [];
        $orderObjIds = [];
        $orderItemIds = [];
        foreach ($tempList as $tempKey => $tempVal)
        {
            $delivery_id = $tempVal['delivery_id'];
            $product_id = $tempVal['product_id'];
            $order_obj_id = $tempVal['order_obj_id'];
            $order_item_id = $tempVal['order_item_id'];
            
            $detailList[$delivery_id][] = $tempVal;
            
            $productIds[$product_id] = $product_id;
            
            $orderObjIds[$order_obj_id] = $order_obj_id;
            
            $orderItemIds[$order_item_id] = $order_item_id;
        }
        
        //order objects
        $objectList = $orderObjMdl->getList('obj_id,order_id,obj_type,goods_id,shop_goods_id,bn', array('obj_id'=>$orderObjIds));
        $objectList = array_column($objectList, null, 'obj_id');
        
        //material
        $materialList = $basicMaterialMdl->getList('bm_id,material_bn,material_name', array('bm_id'=>$productIds));
        $materialList = array_column($materialList, null, 'bm_id');
        
        //return data
        $returnProductList = [];
        $line_i = 0;
        foreach ($deliveryList as $key => $deliveryInfo)
        {
            $delivery_id = $deliveryInfo['delivery_id'];
            
            //使用父发货单上的branch_id
            $branch_id = ($deliveryRow['branch_id'] ? $deliveryRow['branch_id'] : $deliveryInfo['branch_id']);
            
            $line_i++;
            
            //退货记录流水号
            if($deliveryRow['is_bind'] == 'true'){
                //合并发货单,使用父发货单号 + 行号
                $return_bn = 'LJ'. $deliveryRow['delivery_bn'] . $line_i;
            }else{
                //普通发货单
                $return_bn = 'LJ'. $deliveryInfo['delivery_bn'];
            }
            
            //order_id
            $order_id = $deliveryOrderList[$delivery_id]['order_id'];
            
            //master data
            $sdf = array(
                'return_bn' => $return_bn,
                'order_id' => $order_id, //退货记录流水号
                'platform_order_bn' => $deliveryInfo['platform_order_bn'], //平台订单号
                'delivery_id' => $deliveryInfo['delivery_id'], //发货单ID
                'branch_id' => $branch_id,
                'member_id' => $deliveryInfo['member_id'],
                'shop_id' => $deliveryInfo['shop_id'],
                'shop_type' => $deliveryInfo['shop_type'],
                'title' => $deliveryInfo['title'], //退货记录标题
                'content' => $deliveryInfo['content'], //申请售后原因
                'source' => 'delivery_back', //来源
                'sub_source' => 2, // 子来源(0、默认，1、订单，2、发货单，3、售后申请单)
                'op_id' => $opInfo['op_id'], //操作员ID
                'status' => '1',
                'flag_type' => ome_reship_const::__LANJIE_RUKU, //拦截入库标识
                'org_id' => $deliveryInfo['org_id'], //运营组织
                'add_time' => time(), //申请时间
                'outer_lastmodify' => time(), //最后更新时间
                //'refund_fee' => $deliveryInfo['money'], //申请退款金额
            );
            
            //detail
            $detailItems = $detailList[$delivery_id];
            if(empty($detailItems)){
                $error_msg = '发货单号：'. $deliveryInfo['delivery_bn'] .'明细详情不存在';
                return false;
            }
            
            //items data
            $sum_refund_fee = 0; //申请退款金额
            $items = [];
            foreach ($detailItems as $itemKey => $itemInfo)
            {
                $order_obj_id = $deliveryInfo['order_obj_id'];
                $product_id = $itemInfo['product_id'];
                
                //goods_bn
                $objectInfo = $objectList[$order_obj_id];
                $goods_bn = $objectInfo['bn'];
                
                //product_name
                $product_name = ($materialList[$product_id] ? $materialList[$product_id]['material_name'] : $itemInfo['bn']);
                
                //divide_order_fee货品实付金额
                if($itemInfo['divide_user_fee']){
                    $divide_order_fee = $itemInfo['divide_user_fee'];
                }elseif($itemInfo['divide_order_fee']){
                    $divide_order_fee = $itemInfo['divide_order_fee'];
                }else{
                    $divide_order_fee = $itemInfo['amount'];
                }
                
                //price
                $price = bcdiv($divide_order_fee, $itemInfo['number'], 3);
                
                //item
                $items[] = array(
                    'product_id' => $product_id,
                    'shop_goods_bn' =>$goods_bn,
                    'bn' => $itemInfo['bn'],
                    'name' => $product_name,
                    'num' => $itemInfo['number'],
                    'price' => $price,
                    'amount' => $divide_order_fee,
                    'branch_id' => $branch_id,
                    'order_item_id' => $itemInfo['order_item_id'],
                    'obj_type' => $itemInfo['item_type'],
                    'quantity' => $itemInfo['number'],
                );
                
                //refund_fee
                $sum_refund_fee += $divide_order_fee;
            }
            $sdf['return_product_items'] = $items;
            
            //申请退款金额
            $sdf['refund_fee'] = $sum_refund_fee;
            
            //格式化售后申请单数据
            $returnSdf = $this->_formatReturnProductData($sdf, $error_msg);
            if(empty($returnSdf)){
                $error_msg = '发货单号：'. $deliveryInfo['delivery_bn'] .'格式化售后申请单数据失败：'. $error_msg;
                return false;
            }
            
            $returnProductList[$order_id] = $returnSdf;
        }
        
        //unset
        unset($opInfo, $deliveryRow, $deliveryIds, $deliveryList, $tempList, $detailList, $objectList, $materialList);
        
        return $returnProductList;
    }
    
    /**
     * 自动创建售后服务单
     * @todo：主要用于发货单物流拦截成功后,自动创建售后服务单,并且自动审核生成售后退货单;
     *
     * @param $sdf
     * @param $error_msg
     * @return false|mixed
     */
    public function autoCreateReturnProduct($sdf, &$error_msg=null)
    {
        $returnProductMdl = app::get('ome')->model('return_product');
        $returnProductItemMdl = app::get('ome')->model('return_product_items');
        $reshipMdl = app::get('ome')->model('reship');
        $reshipItemMdl = app::get('ome')->model('reship_items');
        
        //check
        if(empty($sdf['return_bn']) || empty($sdf['order_id']) || empty($sdf['title']) || empty($sdf['shop_id'])){
            $error_msg = '无效的数据,无法保存售后申请单';
            return false;
        }
        
        if(empty($sdf['return_product_items'])){
            $error_msg = '没有明细数据,无法创建售后申请单';
            return false;
        }
        
        $return_bn = $sdf['return_bn'];
        
        //return_product_items
        $returnProductItems = $sdf['return_product_items'];
        unset($sdf['return_product_items']);
        
        //return_gift_items
        $returnGiftItems = array();
        if(isset($sdf['return_gift_items'])){
            $returnGiftItems = $sdf['return_gift_items'];
            unset($sdf['return_gift_items']);
        }
        
        //check
        $checkInfo = $returnProductMdl->dump(array('return_bn'=>$return_bn), 'return_id');
        if($checkInfo){
            $error_msg = '售后申请单号：'. $return_bn .'已经存在';
            return false;
        }
        
        //是否检测明细已经创建过售后申请单
        if(isset($sdf['is_check_items']) && $sdf['is_check_items'] === true){
            $returnItemList = array();
            $returnList = $returnProductMdl->getList('return_id,return_bn', array('order_id'=>$sdf['order_id'], 'status|noequal'=>'5'));
            if($returnList){
                $returnBnList = array_column($returnList, 'return_bn');
                $returnIds = array_column($returnList, 'return_id');
                $tempList = $returnProductItemMdl->getList('item_id,return_id,product_id,bn,num', array('return_id'=>$returnIds));
                $returnItemList = array_column($tempList, null, 'bn');
                
                //检测基础物料是否已经被创建过
                foreach ($returnProductItems as $itemKey => $itemVal)
                {
                    $material_bn = $itemVal['bn'];
                    
                    //check
                    if(isset($returnItemList[$material_bn]) && $returnItemList[$material_bn]){
                        $error_msg = '售后申请单号：'. implode(',', $returnBnList) .'中货号：'. $material_bn .'已经被申请过!';
                        return false;
                    }
                }
            }
            
            //读取退换货单上的退货明细
            $reshipList = $reshipMdl->getList('reship_id,reship_bn', array('order_id'=>$sdf['order_id'], 'status|notin'=>array('cancel','back')));
            if($reshipList){
                $reshipBnList = array_column($reshipList, 'reship_bn');
                $reshipIds = array_column($reshipList, 'reship_id');
                $tempList = $reshipItemMdl->getList('item_id,reship_id,bn', array('reship_id'=>$reshipIds));
                $reshipItemList = array_column($tempList, null, 'bn');
                
                //检测基础物料是否已经被创建过
                foreach ($returnProductItems as $itemKey => $itemVal)
                {
                    $material_bn = $itemVal['bn'];
                    
                    //check
                    if(isset($reshipItemList[$material_bn]) && $reshipItemList[$material_bn]){
                        $error_msg = '退换货单号：'. implode(',', $reshipBnList) .'中货号：'. $material_bn .'已经被申请过!';
                        return false;
                    }
                }
            }
        }
        
        //insert
        $insertRs = $returnProductMdl->insert($sdf);
        if(!$insertRs){
            $error_msg = '售后申请单号：'. $return_bn .'创建失败';
        }
        $return_id = $sdf['return_id'];
        
        //return_product_items
        foreach ($returnProductItems as $itemKey => $itemVal)
        {
            $itemVal['return_id'] = $return_id;
            
            $returnProductItemMdl->insert($itemVal);
        }
        
        //return_gift_items售后申请时,自动退货赠品
        $is_gift_auto_approve = app::get('ome')->getConf('aftersale.gift_auto_approve');
        if($returnGiftItems && $is_gift_auto_approve == 'on'){
            $giftOrderItemId = array_column($returnGiftItems, 'order_item_id');
            $returnGifts = $returnProductItemMdl->getList('order_item_id,return_id',array('order_item_id'=>$giftOrderItemId));
            if(empty($returnGifts)){
                foreach ($returnGiftItems as $itemKey => $itemVal)
                {
                    $itemVal['return_id'] = $return_id;
                    
                    $returnProductItemMdl->insert($itemVal);
                }
            }
        }
        
        return $return_id;
    }

    /**
     * 换货单冻结预占
     */
     public function addExchangeFreeze($return_id, &$branch_id, $tmall_detail=[]) {
        $returninfo = app::get('ome')->model('return_product')->db_dump(array('return_id'=>$return_id));
        if(empty($tmall_detail)) {
            if($returninfo['shop_type'] == 'tmall'){
                $tmall_detail = kernel::single('ome_service_aftersale')->get_return_type(array('return_id'=>$return_id));
            }else if(in_array($returninfo['shop_type'],['luban','pinduoduo'])){
                $tmall_detail = app::get('ome')->model('return_product_'.$returninfo['shop_type'])->dump(array('return_id'=>$returninfo['return_id'],'refund_type'=>'change'),'*');
            } else {
                $tmall_detail = app::get('ome')->model('return_apply_special')->db_dump(array('return_id'=>$returninfo['return_id']),'*');
                if($tmall_detail['special'] && is_array(json_decode($tmall_detail['special'], 1))) {
                    $tmall_detail = array_merge($tmall_detail, json_decode($tmall_detail['special'], 1));
                }
            }
        }
        
        $err_msg = '';
        if ($tmall_detail['refund_type'] == 'change'){
            //销售物料信息
            $salesMLib = kernel::single('material_sales_material');
            $salesMInfo = $salesMLib->getSalesMByBn($returninfo['shop_id'],$tmall_detail['exchange_sku']);
            if($salesMInfo){
                if($salesMInfo['sales_material_type'] == 5){
                    $basicMInfos = $salesMLib->get_order_pickone_bminfo($salesMInfo['sm_id'],$tmall_detail['exchange_num'],$returninfo['shop_id']);
                }elseif($salesMInfo['sales_material_type'] == 7){
                    //福袋组合
                    //@todo：未创建换货单之前,福袋组合还不知道随机选哪几个基础物料,所以不能提前冻结库存;
                    $basicMInfos = array();
                    
                }else{
                    $basicMInfos = $salesMLib->getBasicMBySalesMId($salesMInfo['sm_id']);
                }
                $object = [
                    'goods_id' => $salesMInfo['sm_id'],
                    'bn' => $salesMInfo['sales_material_bn'],
                    'quantity' => $tmall_detail['exchange_num'],
                    'delete' => 'false'
                ];
                if($basicMInfos){
                    $items = [];
                    foreach($basicMInfos as $k => $basicMInfo){
                        $items[] = array(
                            'product_id'    => $basicMInfo['bm_id'],
                            'material_name' => $basicMInfo['material_name'],
                            'bn'            => $basicMInfo['material_bn'],
                            'num'           => $basicMInfo['number']*$tmall_detail['exchange_num'],
                            'goods_id'      => $salesMInfo['sm_id'],
                        );
                        $object['items'][] = [
                            'product_id' => $basicMInfo['bm_id'],
                            'bn' => $basicMInfo['material_bn'],
                            'nums' => $basicMInfo['number']*$tmall_detail['exchange_num'],
                            'delete' => 'false'
                        ];
                    }
                    $order = app::get('ome')->model('orders')->db_dump(['order_id'=>$returninfo['order_id']]);
                    $order['objects'][] = $object;
                    $item = new omeauto_auto_group_item([$order]);
                    $branchPlugObj = new omeauto_auto_plugin_branch();
                    $splitStoreObj = new omeauto_split_storemax();
                    $branchPlugObj->process($item);
                    $splitStoreObj->splitOrder($item,[]);
                    $sel_branch_id = is_array($item->getBranchId()) ? current($item->getBranchId()) : [];
                    $branch_id = $sel_branch_id ? : $branch_id;
                    app::get('ome')->model('return_product')->update(['changebranch_id'=>$branch_id], array('return_id'=>$return_id));
                    $params = array(
                        'items'       =>  $items,
                        'changebranch_id'   =>  $branch_id,
                        'return_id'   =>  $return_id,
                        'return_bn'   =>  $returninfo['return_bn'],
                        'shop_id'     =>  $returninfo['shop_id'],
                    );
                    $params_stock = array(
                        "params"    => $params,
                        "node_type" => 'createChangeReturn',
                    );
                    $storeManageLib = kernel::single('ome_store_manage');
                    $storeManageLib->loadBranch(array('branch_id'=>$branch_id));
                    $result = $storeManageLib->processBranchStore($params_stock, $err_msg);
                    
                    app::get('ome')->model('operation_log')->write_log('return@ome', $return_id, '冻结'.($result?'成功:':'失败:').$err_msg);;
                }
            }
        }
    }
    
    /**
     * 换货冻结释放
     *
     * @param $return_id
     * @param $bm_id
     * @return array
     */
    public function releaseChangeFreeze($return_id, $bm_id = ''){
        $returnProductMdl = app::get('ome')->model('return_product');
        $info = $returnProductMdl->db_dump(array('return_id'=>$return_id), 'changebranch_id');

        $items = [];
        $stockFre = app::get('material')->model('basic_material_stock_freeze');
        // 换货单审核换出商品库存不足会冻结到商品上（不冻仓）, 所以obj_type也有可能是1
        $filter = array(
            // 'obj_type'=>material_basic_material_stock_freeze::__BRANCH, 
            'obj_id'=>$return_id, 
            'bill_type'=>material_basic_material_stock_freeze::__RETURN
        );
        if($bm_id) {
            $filter['bm_id'] = $bm_id;
        }
        $freeze = $stockFre->getList('*', $filter);
        $changebranch_id = $info['changebranch_id'];
        foreach($freeze as $v) {
            $bn = app::get('material')->model('basic_material')->db_dump(['bm_id'=>$v['bm_id']], 'material_bn')['material_bn'];
            #bn,product_name,num,product_id,changebranch_id
            $items[] = [
                'changebranch_id'     =>  $v['branch_id'],
                'product_id'    =>  $v['bm_id'],
                'num'           =>  $v['num'],
                'bn'            =>  $bn,
                'goods_id'      =>  $v['goods_id'],
                'obj_type'      =>  $v['obj_type'],
            ];
        }
        
        if(empty($freeze)) {
            return [true, ['msg'=>'没用预占明细']];
        }
        
        $storeManageLib = kernel::single('ome_store_manage');
        $storeManageLib->loadBranch(array('branch_id'=>$changebranch_id));
        
        $err_msg = '';
        $params = array(
            'items'       =>  $items,
            'branch_id'   =>  $changebranch_id,
            'return_id'         =>  $return_id,
        );
        $params_stock = array(
                'params'    => $params,
                'node_type' => 'deleteChangeReturn',
        );
        $rs = $storeManageLib->processBranchStore($params_stock, $err_msg);
        
        return [$rs, ['msg'=>$err_msg]];
    }
    
    /**
     * 重置售后申请单创建的数据
     * @todo：平台退货金额大于OMS订单已支付金额,通过销售单明细重置平台退货商品金额;
     *
     * @param $data
     * @return array
     */
    public function resetReturnProductSdf($data)
    {
        $salesMdl = app::get('ome')->model('sales');
        $salesItemMdl = app::get('ome')->model('sales_items');
        
        $order_id = $data['order_id'];
        $return_product_items = $data['return_product_items'];
        
        //check
        if(empty($order_id) || empty($return_product_items)){
            $error_msg = '无效的转换参数';
            return $this->error($error_msg);
        }
        
        //oid
        $oids = array_column($return_product_items, 'oid');
        $oids = array_filter($oids);
        
        //check
        if(empty($oids)){
            $error_msg = '没有可用的oid子订单号';
            return $this->error($error_msg);
        }
        
        //sales
        $saleInfo = $salesMdl->dump(array('order_id'=>$order_id), 'sale_id,sale_bn,order_bn');
        if(empty($saleInfo)){
            $error_msg = '没有找到销售单主数据';
            return $this->error($error_msg);
        }
        
        //sales_items
        $tempItems = $salesItemMdl->getList('item_id,product_id,bn,oid,actually_amount,nums', array('sale_id'=>$saleInfo['sale_id']));
        if(empty($tempItems)){
            $error_msg = '没有找到销售单明细数据';
            return $this->error($error_msg);
        }
        
        $saleItemList = [];
        foreach ($tempItems as $itemKey => $itemVal)
        {
            $oid = $itemVal['oid'];
            $product_bn = $itemVal['bn'];
            
            //check
            if(empty($oid)){
                continue;
            }
            
            $saleItemList[$oid][$product_bn] = $itemVal;
        }
        
        //按销售单货品明细获取退货金额
        $is_reset = true;
        $refund_fee = 0;
        foreach ($return_product_items as $itemKey => $itemVal)
        {
            $oid = $itemVal['oid'];
            $product_bn = $itemVal['bn'];
            $return_nums = $itemVal['num'];
            
            //check
            if(empty($oid) || empty($product_bn)){
                //sum
                $refund_fee += $itemVal['amount'];
                
                //flag
                $data['return_product_items'][$itemKey]['flag_str'] = 'no_oid';
                $is_reset = false;
                
                continue;
            }
            
            if(!isset($saleItemList[$oid][$product_bn]) || empty($saleItemList[$oid][$product_bn]['nums'])){
                //sum
                $refund_fee += $itemVal['amount'];
                
                //flag
                $data['return_product_items'][$itemKey]['flag_str'] = 'no_sales';
                $is_reset = false;
                
                continue;
            }
            
            //amount
            if($saleItemList[$oid][$product_bn]['nums'] == $return_nums){
                //客户实付
                $actually_amount = $saleItemList[$oid][$product_bn]['actually_amount'];
            }else{
                $actually_amount = $saleItemList[$oid][$product_bn]['actually_amount'] / $saleItemList[$oid][$product_bn]['nums'] * $return_nums;
                $actually_amount = bcmul($actually_amount, 1, 2); //保留两位小数
            }
            
            $data['return_product_items'][$itemKey]['amount'] = $actually_amount;
            $data['return_product_items'][$itemKey]['flag_str'] = 'reset_amount';
            
            //sum
            $refund_fee += $actually_amount;
        }
        
        //refund_fee
        $data['refundmoney'] = $refund_fee;
        $data['money'] = $refund_fee;
        
        //fail
        if(!$is_reset){
            $error_msg = '退货商品明细转换失败';
            return $this->error($error_msg, $data);
        }
        
        return $this->succ('重置售后申请单数据成功', $data);
    }
}
