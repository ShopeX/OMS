<?php
/**
 * 订单预约Lib方法
 *
 * @author wangbiao@shopex.cn
 * @version 2025.06.20
 */
class ome_order_reservation extends ome_order_abstract
{
    /**
     * 创建订单预约信息
     *
     * @param array $orderInfo
     * @return array
     */
    public function createReservation($orderInfo)
    {
        $reservationMdl = app::get('ome')->model('order_reservation');
        $billLabelMdl = app::get('ome')->model('bill_label');
        $labelsMdl = app::get('omeauto')->model('order_labels');
        $operLogMdl = app::get('ome')->model('operation_log');
        
        //check
        if(empty($orderInfo) || empty($orderInfo['order_id'])){
            $error_msg = '无效的订单信息';
            return $this->error($error_msg);
        }
        
        // order_id
        $order_id = $orderInfo['order_id'];
        $order_bn = $orderInfo['order_bn'];
        
        // 预约订单标记
        $label_code = $this->reservation_order_label;
        $labelInfo = $labelsMdl->dump(['label_code'=>$label_code], 'label_id,label_code,label_name');
        if(empty($labelInfo)){
            $error_msg = '没有找到预约订单标记';
            return $this->error($error_msg);
        }
        
        // 查询预约订单
        $fitler = [
            'bill_type' => 'order',
            'bill_id' => $order_id,
            'label_id' => $labelInfo['label_id'],
        ];
        $orderLabelInfo = $billLabelMdl->dump($fitler, 'id,bill_id,label_id');
        if(empty($orderLabelInfo)){
            $error_msg = '订单没有预约标记';
            return $this->error($error_msg);
        }
        
        // check
        $reservationInfo = $reservationMdl->dump(['order_id'=>$order_id], 'id');
        if($reservationInfo){
            $error_msg = '订单已经有预约信息';
            return $this->error($error_msg);
        }
        
        // insert
        $sdf = [
            'order_id' => $order_id,
            'order_bn' => $order_bn,
            'custom_reserved_status' => '0',
            'order_status' => ($orderInfo['status'] ? $orderInfo['status'] : 'active'),
            'pay_status' => ($orderInfo['pay_status'] ? $orderInfo['pay_status'] : '1'),
            'ship_status' => ($orderInfo['ship_status'] ? $orderInfo['ship_status'] : '0'),
        ];
        $insert_id = $reservationMdl->insert($sdf);
        if(!$insert_id){
            $error_msg = '创建订单预约信息失败';
            return $this->error($error_msg);
        }
        
        // log
        $log_msg = '添加预约信息成功';
        $operLogMdl->write_log('order_reserved_create@ome', $insert_id, $log_msg);
        
        return $this->succ($log_msg);
    }
    
    /**
     * 重新预约订单
     *
     * @param array $orderInfo
     * @param string $again_type 重新预约类型
     * @return array
     */
    public function againReservation($order_id, $again_type='')
    {
        $orderMdl = app::get('ome')->model('orders');
        $reservationMdl = app::get('ome')->model('order_reservation');
        $operLogMdl = app::get('ome')->model('operation_log');
        
        // 预约信息
        $reservationInfo = $reservationMdl->dump(['order_id'=>$order_id], '*');
        if(empty($reservationInfo)){
            $error_msg = '订单没有预约信息';
            return $this->succ($error_msg);
        }
        $addressChangeReservationAgain = false;
        // check
        if($reservationInfo['custom_reserved_status'] == '0'){
            $error_msg = '订单还未进行预约操作，不用重新预约';
            return $this->succ($error_msg);
        }elseif($reservationInfo['custom_reserved_status'] == '4'){
            $error_msg = '预约已取消，不能重新预约';
            return $this->succ($error_msg);
        }elseif($reservationInfo['custom_reserved_status'] == '1'){
            //@todo：预约时间成功，是可以再次请求SAP进行预约的，而且不用先请求SAP取消预约时间；
            //$error_msg = '已预约成功，不能重新预约';
            //return $this->succ($error_msg);
            if($again_type == 'address_change'){
                $addressChangeReservationAgain = true;
            }
        } elseif($reservationInfo['custom_reserved_status'] == '2'){
            if($again_type == 'address_change'){
                $addressChangeReservationAgain = true;
            }
        }
        
        // 获取订单信息
        $orderInfo = $orderMdl->dump(['order_id'=>$order_id], '*');
        if(empty($orderInfo)){
            $error_msg = '订单不存在,请检查';
            return $this->error($error_msg);
        }
        
        // check
        if($orderInfo['process_status'] == 'cancel'){
            $error_msg = '订单已经取消,无需更新状态';
            return $this->error($error_msg);
        }
        
        if(in_array($orderInfo['ship_status'], ['1','3','4'])){
            $error_msg = '订单已经发货或退货,无需更新状态';
            return $this->error($error_msg);
        }
        
        //是否安装了miele应用
        if(app::get('miele')->is_installed()){
            $mieleOrderLib = kernel::single('miele_order');
            
            // 判断是否大家电
            $isLargeAppliance = $mieleOrderLib->isLargeAppliance($order_id);
            if(!$isLargeAppliance){
                $error_msg = '订单号：'. $orderInfo['order_bn'] .',不是大家电订单';
                return $this->error($error_msg);
            }
            
            // 标识
            $orderInfo['operation_type'] = 'cancel_delivery';
            
            // 更新大家电订单的标识：不允许审核订单
            $labelResult = $mieleOrderLib->largeApplianceOrder($orderInfo);
        }
        
        // update sdf
        $updateSdf = ['custom_reserved_status'=>'0'];
        
        // 订单地址修改，清空预约地址信息
        if($again_type == 'address_change'){
            $updateSdf['custom_reserved_name'] = '';
            $updateSdf['custom_reserved_mobile'] = '';
            $updateSdf['custom_reserved_area'] = '';
            $updateSdf['custom_reserved_address'] = '';
        }
        
        // 已经预约、预约失败，更新为：预约中
        $reservationMdl->update($updateSdf, ['id'=>$reservationInfo['id']]);
        
        // again_type
        $log_prefix = '';
        if($again_type == 'address_change'){
            $log_prefix = '[收货人地址变更]';
        }elseif($again_type == 'goods_change'){
            $log_prefix = '[SKU商品变更]';
        }elseif($again_type == 'sap_reservation_failed'){
            $log_prefix = '[SAP预约失败]';
        }
        
        // log
        $log_msg = $log_prefix . '更新为：待预约';
        $operLogMdl->write_log('order_reserved_modify@ome', $reservationInfo['id'], $log_msg);
        // 调用service扩展点，允许其他服务在重新预约时做自定义处理
        if($service = kernel::servicelist('ome.service.order.reservation.again')){
            foreach($service as $instance){
                if($addressChangeReservationAgain && method_exists($instance, 'after_address_change_reservation')){
                    $instance->after_address_change_reservation($reservationInfo['id']);
                }
            }
        }
        return $this->succ($log_msg);
    }
    
    /**
     * 通过order_id批量更新预约订单信息
     *
     * @param $orderIds
     * @param $error_msg
     * @return void
     */
    public function updateReservationByOrderIds($orderIds)
    {
        $orderMdl = app::get('ome')->model('orders');
        $reservationMdl = app::get('ome')->model('order_reservation');
        
        // check
        if(empty($orderIds)){
            $error_msg = '无效的更新操作';
            return $this->error($error_msg);
        }
        
        // 预约订单列表
        $reOrderList = $reservationMdl->getList('id,order_id,order_bn,order_status,pay_status,ship_status', ['order_id'=>$orderIds]);
        if(empty($reOrderList)){
            $error_msg = '没有预约订单信息';
            return $this->error($error_msg);
        }
        
        // ERP订单
        $orderList = $orderMdl->getList('order_id,order_bn,status,pay_status,process_status,ship_status', array('order_id'=>$orderIds), 0, -1);
        if(empty($orderList)){
            $error_msg = '没有查询到ERP订单';
            return $this->error($error_msg);
        }
        
        //按ERP订单号进行更新明细发货状态
        foreach ($orderList as $key => $orderInfo)
        {
            $order_bn = $orderInfo['order_bn'];
            
            // sdf
            $updateData = [
                'order_status' => $orderInfo['status'],
                'pay_status' => $orderInfo['pay_status'],
                'process_status' => $orderInfo['process_status'],
                'ship_status' => $orderInfo['ship_status'],
            ];
            
            // update
            $reservationMdl->update($updateData, ['order_bn'=>$order_bn]);
        }
        
        return $this->succ('更新成功');
    }
    
    /**
     * [更新]预约订单的相关状态
     *
     * @param $orderInfo
     * @param $error_msg
     * @return bool
     */
    public function operateReservationOrder($orderInfo, &$error_msg=null)
    {
        $orderMdl = app::get('ome')->model('orders');
        $reservationMdl = app::get('ome')->model('order_reservation');
        
        //check
        if(empty($orderInfo['order_id']) && empty($orderInfo['order_bn'])){
            $error_msg = '没有订单ID、订单号,请检查!';
            return false;
        }
        
        //order
        $order_id = $orderInfo['order_id'];
        $order_bn = $orderInfo['order_bn'];
        
        //filter
        $filter = [];
        if($order_id){
            $filter['order_id'] = $order_id;
        }else{
            $filter['order_bn'] = $order_bn;
        }
        
        // orderInfo
        $reOrderInfo = $reservationMdl->db_dump($filter, '*');
        if(empty($reOrderInfo)){
            return true;
        }
        
        // 获取订单信息
        //@todo：如果平台推送订单是已发货状态，需要重新读取OMS订单实时信息，防止PKG组合部分发货的情况；
        if($orderInfo['ship_status'] == '1'){
            $orderInfo = $orderMdl->dump(['order_id'=>$order_id], '*');
            if(empty($orderInfo)){
                return true;
            }
        }
        
        // check status
        if($reOrderInfo['order_status'] == $orderInfo['status'] && $reOrderInfo['pay_status'] == $orderInfo['pay_status']){
            if($reOrderInfo['process_status'] == $orderInfo['process_status'] && $reOrderInfo['ship_status'] == $orderInfo['ship_status']){
                return true;
            }
        }
        
        // sdf
        $updateData = [];
        if($orderInfo['status']){
            $updateData['order_status'] = $orderInfo['status'];
        }
        
        if($orderInfo['pay_status']){
            $updateData['pay_status'] = $orderInfo['pay_status'];
        }
        
        if($orderInfo['process_status']){
            $updateData['process_status'] = $orderInfo['process_status'];
        }
        
        if($orderInfo['ship_status']){
            $updateData['ship_status'] = $orderInfo['ship_status'];
        }
        
        // update
        if($updateData){
            $reservationMdl->update($updateData, ['id'=>$reOrderInfo['id']]);
        }
        
        return true;
    }
    
    /**
     * 获取预约订单信息
     *
     * @param $order_id
     * @return void
     */
    public function getReservationOrderInfo($order_id, &$error_msg=null)
    {
        $reservationMdl = app::get('ome')->model('order_reservation');
        
        // 预约信息
        $reservationInfo = $reservationMdl->dump(['order_id'=>$order_id], '*');
        if(empty($reservationInfo)){
            $error_msg = '订单没有预约信息';
            return false;
        }
        
        // 商品预约信息
        if($reservationInfo['reservation_items']){
            // json
            $reservationItems = json_decode($reservationInfo['reservation_items'], true);
            
            // format
            $reserItemList = [];
            foreach ($reservationItems as $reKey => $reVal)
            {
                $order_item_id = $reVal['order_item_id'];
                
                $reserItemList[$order_item_id] = $reVal;
            }
            
            $reservationInfo['reservation_items'] = $reserItemList;
        }
        
        return $reservationInfo;
    }
    
    /**
     * 客户申请预约时间与SAP预约时间是否一致
     *
     * @param $order_id
     * @param $orderItemReservations
     * @param $itemReservationSdf 按货号纬度记录SAP预约时间
     * @return array
     */
    public function diffReservationSame($order_id, $orderItemReservations, $itemReservationSdf=[])
    {
        $reservationMdl = app::get('ome')->model('order_reservation');
        $operLogMdl = app::get('ome')->model('operation_log');
        
        // Setting
        $error_msg = '';
        
        // check
        if(empty($order_id)){
            $error_msg = '无效的校验参数';
            return $this->error($error_msg);
        }
        
        // 预约信息
        $reservationInfo = $this->getReservationOrderInfo($order_id, $error_msg);
        if(empty($reservationInfo)){
            return $this->error($error_msg);
        }
        
        // SAP未回传预约时间
        if(empty($orderItemReservations)){
            // update is_reserved_same false
            //$reservationMdl->update(['is_reserved_same'=>'false'], ['order_id'=>$order_id]);
            
            $error_msg = 'SAP未回传预约时间!';
            
            // log
            $log_msg = $error_msg;
            $operLogMdl->write_log('order_reserved_modify@ome', $reservationInfo['id'], $log_msg);
            
            return $this->error($error_msg);
        }
        
        // 对比预约时间标记
        $is_reserved_same = 'true';
        
        // 通过保存的订单明细预约时间，检测预约订单的预约时间是否一致
        $detectionResult = $this->detectionOrderReservedTime($order_id);
        if($detectionResult['rsp'] == 'succ' && $detectionResult['data']['is_reserved_same'] == 'false'){
            $is_reserved_same = 'false';
        }
        
        // update is_reserved_same
        $reservationMdl->update(['is_reserved_same'=>$is_reserved_same], ['order_id'=>$order_id]);
        
        // log
        if($itemReservationSdf){
            $itemTimeString = [];
            foreach ($itemReservationSdf as $product_bn => $sap_reserved_times)
            {
                if(empty($sap_reserved_times)){
                    continue;
                }
                
                $sapTimeStrings = [];
                foreach ($sap_reserved_times as $itemKey => $timeVal)
                {
                    $sapTimeStrings[] = is_numeric($timeVal) ? date('Y-m-d', $timeVal) : '';
                }
                
                $sap_time_string = implode('、', $sapTimeStrings);
                
                $itemTimeString[] = $product_bn . '：' . $sap_time_string;
            }
            
            // log
            $log_msg = 'SAP预约时间：'. ($is_reserved_same == 'true' ? '一致' : '不一致') . '；'. implode('，', $itemTimeString);
            $operLogMdl->write_log('order_reserved_modify@ome', $reservationInfo['id'], $log_msg);
        }
        
        return $this->succ();
    }
    
    /**
     * 获取预约订单的SAP预约时间
     *
     * @param $order_id
     * @return void
     */
    public function getOrderSapReservedTimes($order_id)
    {
        $itemPropMdl = app::get('ome')->model('order_items_props');
        
        // 预约时间
        $props_col = 'sap_reserved_times';
        $itemPropList = $itemPropMdl->getList('pro_id,order_id,item_id,props_col,props_value', ['order_id'=>$order_id, 'props_col'=>$props_col]);
        if(empty($itemPropList)){
            return false;
        }
        
        $itemPropList = array_column($itemPropList, null, 'item_id');
        
        // format
        foreach ($itemPropList as $item_id => $itemProp)
        {
            $item_id = $itemProp['item_id'];
            
            if($itemProp['props_value']){
                $propsValueList = json_decode($itemProp['props_value'], true);
                
                $reservedTimes = [];
                foreach ($propsValueList as $propKey => $propVal)
                {
                    $reservedTimes[] = is_numeric($propVal) ? date('Y-m-d', $propVal) : '';
                }
                
                $itemPropList[$item_id]['sap_reserved_times'] = implode('、', $reservedTimes);
            }else{
                $itemPropList[$item_id]['sap_reserved_times'] = '';
            }
        }
        
        return $itemPropList;
    }
    
    /**
     * 通过保存的订单明细预约时间，检测预约订单的预约时间是否一致
     *
     * @param $order_id
     * @return array
     */
    public function detectionOrderReservedTime($order_id)
    {
        $itemPropMdl = app::get('ome')->model('order_items_props');
        $basicMaterialObj = app::get('material')->model('basic_material');
        $catMdl = app::get('material')->model('basic_material_cat');
        
        // setting
        $error_msg = '';
        $is_reserved_same = 'true';
        
        // check
        if(empty($order_id)){
            $error_msg = '无效的校验参数';
            return $this->error($error_msg);
        }
        
        // 预约信息
        $reservationInfo = $this->getReservationOrderInfo($order_id, $error_msg);
        if(empty($reservationInfo)){
            $error_msg = '获取预约信息失败：'. $error_msg;
            return $this->error($error_msg);
        }
        
        // check
        if(empty($reservationInfo['reservation_items'])){
            $error_msg = '没有商品明细预约时间';
            return $this->error($error_msg);
        }
        
        // order_item_id
        $orderItemIds = array_column($reservationInfo['reservation_items'], 'order_item_id');
        
        // sap_reserved_times
        $props_col = 'sap_reserved_times';
        $itemPropList = $itemPropMdl->getList('pro_id,item_id,props_col,props_value', ['item_id'=>$orderItemIds, 'props_col'=>$props_col]);
        $itemPropList = array_column($itemPropList, null, 'item_id');
        if(empty($itemPropList)){
            $is_reserved_same = 'false';
            $result = ['is_reserved_same'=>$is_reserved_same];
            
            return $this->succ('检测结果成功：没有订单明细预约时间', $result);
        }
        
        // 大家电订单分类
        $catIds = [];
        $catList = $catMdl->getList('cat_id,cat_code,cat_name', ['cat_code'=>'LARGE_APPLIANCES']);
        if($catList){
            $catIds = array_column($catList, 'cat_id');
        }
        
        // diff
        $diffList = [];
        foreach ($reservationInfo['reservation_items'] as $reItemKey => $reItemVal)
        {
            $order_item_id = $reItemVal['order_item_id'];
            $product_bn = $reItemVal['product_bn'];
            
            // 过滤延保商品(虚拟类型)
            $basicMaterialInfo = $basicMaterialObj->dump(array('material_bn'=>$product_bn), 'bm_id,material_bn,type,cat_id');
            if($basicMaterialInfo['type'] == 5){
                continue;
            }
            
            // 验证指定的基础物料分类
            if($catIds && !in_array($basicMaterialInfo['cat_id'], $catIds)){
                continue;
            }
            
            // check
            if(empty($reItemVal['item_reserved_time'])){
                $is_reserved_same = 'false';
                
                $diffList[] = ['product_bn'=>$product_bn, 'error_msg'=>'item_reserved_time empty!'];
                
                continue;
            }
            
            if(empty($itemPropList[$order_item_id]['props_value'])){
                $is_reserved_same = 'false';
                
                $diffList[] = ['product_bn'=>$product_bn, 'error_msg'=>'props_value empty!'];
                
                continue;
            }
            
            // props_value
            $propsValueList = json_decode($itemPropList[$order_item_id]['props_value'], true);
            
            // diff
            foreach ($propsValueList as $propKey => $propVal)
            {
                if(empty($propVal) || !is_numeric($propVal)){
                    $is_reserved_same = 'false';
                    
                    $diffList[] = ['product_bn'=>$product_bn, 'error_msg'=>'props_value Not Numeric!'];
                    
                    continue;
                }
                
                // check time
                if($propVal != $reItemVal['item_reserved_time']){
                    $is_reserved_same = 'false';
                    
                    $diffList[] = ['product_bn'=>$product_bn, 'error_msg'=>'props_value item_reserved_time inequality!'];
                    
                    continue;
                }
            }
        }
        
        $result = ['is_reserved_same'=>$is_reserved_same, 'diffInfo'=>$diffList];
        
        return $this->succ('检测结果成功', $result);
    }
    
    /**
     * 获取订单的报备外呼主叫号码组
     *
     * @param $orderInfo
     * @return array
     */
    public function getOrderSecretMobiles($orderInfo)
    {
        $order_id = intval($orderInfo['order_id']);
        
        // 获取订单收货人信息
        $oaid = '';
        $original = app::get('ome')->model('order_receiver')->db_dump(['order_id'=>$order_id], 'encrypt_source_data');
        if($original['encrypt_source_data']){
            $encrypt_source_data = json_decode($original['encrypt_source_data'], 1);
            if($encrypt_source_data){
                $oaid = $encrypt_source_data['oaid'];
            }
        }
        
        // check
        if(empty($oaid)){
            $error_msg = '订单收货人信息不存在';
            return $this->error($error_msg);
        }
        
        // 获取系统里配置的报备外呼主叫号码组
        $mobileGroup = app::get('ome')->getConf('ome.call.mobile.group');
        $mobileList = [];
        if(!empty($mobileGroup)){
            // 按换行符分割，过滤空行
            $lines = explode("\n", $mobileGroup);
            foreach($lines as $line){
                $line = trim($line);
                if(!empty($line)){
                    $mobileList[] = $line;
                }
            }
        }
        
        // check
        if(empty($mobileList)){
            $error_msg = '请在系统里配置：报备外呼主叫号码组';
            return $this->error($error_msg);
        }
        
        // data
        $data = [
            'order_id' => $order_id,
            'order_bn' => $orderInfo['order_bn'],
            'oaid' => $oaid,
            'mobile_list' => $mobileList,
        ];
        
        return $this->succ('获取成功', $data);
    }
    
    /**
     * 更新预约商品明细预约时间
     * @todo：批量编码替换大家电订单赠品时，删除订单商品明细的预约明细记录；
     *
     * @param $order_id
     * @param $error_msg
     * @return void
     */
    public function updateReservationItems($order_id, &$error_msg = null)
    {
        $orderItemMdl = app::get('ome')->model('order_items');
        $reservationMdl = app::get('ome')->model('order_reservation');
        
        // items
        $itemList = $orderItemMdl->getList('item_id,obj_id,bn', ['order_id' => $order_id, 'delete' => 'true']);
        $itemList = array_column($itemList, null, 'item_id');
        if (empty($itemList)) {
            $error_msg = 'empty order_items';
            return false;
        }
        
        // order_reservation
        $reservationInfo = $reservationMdl->dump(['order_id' => $order_id], '*');
        if (empty($reservationInfo) || empty($reservationInfo['reservation_items'])) {
            $error_msg = 'empty order_reservation';
            return false;
        }
    
        $reservation_items = json_decode($reservationInfo['reservation_items'], true);
        if (empty($reservation_items)) {
            $error_msg = 'empty reservation_items';
            return false;
        }
    
        // exec
        foreach ($reservation_items as $itemKey => $itemVal) {
            $order_item_id = $itemVal['order_item_id'];
        
            // unset
            if ($itemList[$order_item_id]) {
                unset($reservation_items[$itemKey]);
            }
        }
        
        // json
        $reservation_items_string = json_encode($reservation_items);
        
        // update is_reserved_same
        $reservationMdl->update(['reservation_items'=>$reservation_items_string], ['order_id'=>$order_id]);
        
        return true;
    }
}