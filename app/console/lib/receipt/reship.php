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
class console_receipt_reship{

    private static $is_check = array(
        'FINISH'=>'8',
    );

    //退换货单回传状态更新
    function updateStatus($data,&$msg)
    {
        $basicMaterialObj = app::get('material')->model('basic_material');
        $oReship = app::get('ome')->model('reship');
        $oReship_items = app::get('ome')->model('reship_items');
        $branchObj = app::get('ome')->model('branch');
        
        $reship_bn = $data['reship_bn'];
        $reship = $this->checkExist($reship_bn);
        
        $reship_id = $reship['reship_id'];
        if (!$reship) {
            $msg = '退货单号不存在!';
            return false;
        }
        $wrMdl = app::get('console')->model('wms_reship');
        $wrRow = $wrMdl->db_dump(['reship_bn'=>$reship_bn, 'reship_status'=>'1'], 'id');
        if($wrRow) {
            $wrRs = $wrMdl->update(['reship_id'=>$reship_id, 'reship_status'=>'2'], ['id'=>$wrRow['id'], 'reship_status'=>'1']);
            if(!is_bool($wrRs)) {
                app::get('ome')->model('operation_log')->write_log('wms_reship@console',$wrRow['id'], '匹配成功：'.$reship_bn);
            }
        }
        foreach ($data['items'] as $item) {
            if($item['defective_num'] > 0) {
                $damaged = kernel::single('console_iostockdata')->getDamagedbranch($reship['branch_id']);
                if(empty($damaged)) {
                    $msg = '仓库对应的残品仓不存在';
                    return false;
                }
                break;
            }
        }
        //WMS仓储类型标识
        $wms_type = $data['wms_type'];
        $type = $reship['return_type'];
        
        //保存验收信息
        $error_msg = '';
        if($wms_type == 'yjdf'){
            $operLogObj = app::get('ome')->model('operation_log');
            
            //[京东一件代发]保存京东服务单信息
            if(in_array($reship['is_check'], array('1','2','3','8','13'))){
                $result = kernel::single('ome_return_rchange')->accept_returned($reship_id, '3', $error_msg, $data);
                if(!$result){
                    $operLogObj->write_log('reship@ome', $reship_id, '更新京东服务单失败：'.$error_msg);
                }
            }else{
                //log
                $operLogObj->write_log('reship@ome', $reship_id, '未更新京东服务单状态[is_check:'. $reship['is_check'] .',service_bn:'. $data['service_bn'] .']');
            }
        }elseif($reship['is_check'] == '1'){
            kernel::single('ome_return_rchange')->accept_returned($reship_id,'3',$error_msg);
        }
        
        //status
        $status = $data['status'];
        //items
        $items = $data['items'];
        $arrItemUpData = [];
        if ($items) {
            $tran = kernel::database()->beginTransaction();
            
            // 提前获取所有reship_items，避免循环中重复查询导致数据丢失
            $all_reship_items = array();
            // 为每个item_id维护数量消耗计数器
            $consumed_quantities = array();
            $all_bns = array_unique(array_column($items, 'bn'));
            $tmpReshipItems = $oReship_items->getlist('num,normal_num,defective_num,item_id,branch_id,bn',array('reship_id'=>$reship_id,'bn'=>$all_bns,'defective_num'=>0,'normal_num'=>0,'return_type'=>array('return','refuse')),0,-1,'num desc');
            foreach($tmpReshipItems as $v) {
                $all_reship_items[$v['bn']][] = $v;
            }
                    
            foreach ($items as  $item) {
                $bn = $item['bn'];
                $check_num = (int)$item['normal_num'];
                $defective_num = (int)$item['defective_num'];
                $wms_sku_bn = $item['wms_sku_bn']; //WMS仓储sku货号
                $bnBmIdName = app::get('material')->model('basic_material')->db_dump(array('material_bn'=>$bn), 'bm_id,material_name');
                if($item['batch']) {
                    $useLogModel = app::get('console')->model('useful_life_log');
                    $useful = [];
                    foreach ($item['batch'] as $bv) {
                        $tmpUseful = [];
                        $tmpUseful['product_id'] = $bnBmIdName['bm_id'];
                        $tmpUseful['bn'] = $item['bn'];
                        $tmpUseful['original_bn'] = $reship_bn;
                        $tmpUseful['original_id'] = $reship_id;
                        $tmpUseful['sourcetb'] = 'reship';
                        $tmpUseful['create_time'] = time();
                        $tmpUseful['stock_status'] = '0';
                        $tmpUseful['num'] = $bv['num'];
                        $tmpUseful['normal_defective'] = $bv['normal_defective'];
                        $tmpUseful['product_time'] = (int)$bv['product_time'];
                        $tmpUseful['expire_time'] = (int)$bv['expire_time'];
                        $tmpUseful['purchase_code'] = $bv['purchase_code'];
                        $tmpUseful['produce_code'] = $bv['produce_code'];
                        if($item['branch_id']) {
                            $tmpUseful['branch_id'] = $item['branch_id'];
                            $tmpUseful['normal_defective'] = 'normal';
                        }
                        $useful[] = $tmpUseful;
                    }
                    $useLogModel->db->exec(ome_func::get_insert_sql($useLogModel, $useful));
                }
                if($item['sn_list']) {
                    $serialHistoryObj = app::get('ome')->model('product_serial_history');
                    $opInfo = kernel::single('ome_func')->getDesktopUser();
                    $historyData = [];
                    foreach($item['sn_list'] as $serial){
                        $tmpHistoryData = [
                            'branch_id' => $item['branch_id'] ? : $reship['branch_id'],
                            'bn' => $item['bn'],
                            'product_name' => $bnBmIdName['material_name'],
                            'act_type' => '2',
                            'act_time' =>time(),
                            'act_owner' => $opInfo['op_id'],
                            'bill_type' => '2',
                            'bill_id' => $reship_id,
                            'bill_no' => $reship_bn,
                            'serial_number' => $serial
                        ];
                        $historyData[] = $tmpHistoryData;
                    }
                    $serialHistoryObj->db->exec(ome_func::get_insert_sql($serialHistoryObj, $historyData));
                }
                // pda过来的支持部分收
                if ($data['source'] == 'pda') {
                    $item_add = array();
                    // 使用预先获取的reship_items数据，取第一条记录
                    $reship_items = isset($all_reship_items[$bn]) && !empty($all_reship_items[$bn]) ? $all_reship_items[$bn][0] : null;
                    if (!$reship_items) {
                        /* */
                    }else{
                        $item_add['item_id'] = $reship_items['item_id'];
                        $item_add['defective_num'] = $item['defective_num']+$reship_items['defective_num'];
                        $item_add['normal_num'] = $item['normal_num']+$reship_items['normal_num'];
                        // 退货仓库ID
                        if ( isset($item['return_branch_id']) && !empty($item['return_branch_id']) ) {
                            $item_add['return_branch_id'] = $item['return_branch_id'];
                        }
                        #更新收货数量
                        if($check_num > 0) {
                            $this->dealProcessItems($arrItemUpData, $bnBmIdName['bm_id'], $check_num, $reship['branch_id'], $reship_items['item_id']);
                        }
                        if($defective_num > 0) {
                            $this->dealProcessItems($arrItemUpData, $bnBmIdName['bm_id'], $defective_num, $damaged['branch_id'], $reship_items['item_id'], '1');
                        }
                    }
                    $oReship_items->save($item_add);
                } else {
                    // 根据传入的item中的branch_id来确定入库仓库
                    $target_branch_id = $reship['branch_id']; // 默认使用退货单的仓库
                    
                    // 如果item中有指定branch_id，则使用item的branch_id
                    if (!empty($item['branch_id'])) {
                        $target_branch_id = $item['branch_id'];
                    }
                    
                    // 使用预先获取的reship_items数据，考虑已消耗的数量
                    $reship_items = isset($all_reship_items[$bn]) ? $all_reship_items[$bn] : array();
                    if ($reship_items) {
                        $reship_item_loop = 1;
                        $reship_items_count = count($reship_items);
                        
                        foreach($reship_items as $reship_item){
                            $item_add = array();
                            $item_add['item_id'] = $reship_item['item_id'];
                            $need_return_num = $reship_item['num'] - $consumed_quantities[$reship_item['item_id']]['normal'] - $consumed_quantities[$reship_item['item_id']]['defective'];

                            //如果实际入库数为0，跳出该货品的退入数量处理逻辑
                            if($check_num + $defective_num <= 0){
                                break;
                            }
                            if($need_return_num < 1){
                                continue;
                            }
                            //申请数量大于实际退入良品，把良品都给这一行的sku，不足的后面再用不良品补
                            if($need_return_num >= $check_num){
                                $item_add['normal_num'] = $check_num;
                                $need_return_num = $need_return_num - $item_add['normal_num'];
                                $check_num = 0;
                            }elseif($need_return_num < $check_num){
                                //申请数量小于实际退入良品，直接按申请数量扣减良品数量，这一行sku退入完成
                                $item_add['normal_num'] = $need_return_num;
                                $need_return_num = 0;
                                $check_num = $check_num-$item_add['normal_num'];
                            }

                            //这一行sku良品不够用，还需不良品填充
                            if($need_return_num > 0 ){
                                //申请数量大于实际退入不良品，把不良品都给这一行的sku
                                if($need_return_num >= $defective_num){
                                    $item_add['defective_num'] = $defective_num;
                                    $need_return_num = $need_return_num - $item_add['defective_num'];
                                    $defective_num = 0;
                                }elseif($need_return_num < $defective_num){
                                    //申请数量小于实际退入不良品，直接按申请数量扣减不良品数量，这一行sku退入完成
                                    $item_add['defective_num'] = $need_return_num;
                                    $need_return_num = 0;
                                    $defective_num = $defective_num-$item_add['defective_num'];
                                }
                            }
                            

                            //更新收货数量 - 良品入库
                            if($item_add['normal_num'] > 0){
                                $this->dealProcessItems($arrItemUpData, $bnBmIdName['bm_id'], $item_add['normal_num'], $target_branch_id, $reship_item['item_id']);
                            }
                            
                            //不良品需要更新为：残次仓
                            if ($item_add['defective_num']>0) {
                                //获取不良品退货入库的残损仓ID
                                $defective_branch_id = $damaged['branch_id'] ? $damaged['branch_id'] : $target_branch_id;
                                $this->dealProcessItems($arrItemUpData, $bnBmIdName['bm_id'], $item_add['defective_num'], $defective_branch_id, $reship_item['item_id'], '1');
                            }
                            
                            //$oReship_items->save($item_add);

                            // 更新已消耗的数量
                            $consumed_quantities[$reship_item['item_id']]['normal'] += $item_add['normal_num'];
                            $consumed_quantities[$reship_item['item_id']]['defective'] += $item_add['defective_num'];

                            $reship_item_loop++;
                        }
                    }
                }
            }
            // 更新退货明细表中normal_num和defective_num
            foreach ($consumed_quantities as $item_id => $consumed_quantity) {
                $update_data = array();
                if (isset($consumed_quantity['normal'])) {
                    $update_data['normal_num'] = $consumed_quantity['normal'];
                }
                if (isset($consumed_quantity['defective'])) {
                    $update_data['defective_num'] = $consumed_quantity['defective'];
                }
                if (!empty($update_data)) {
                    $oReship_items->update($update_data, array('item_id' => $item_id));
                }
            }
            if($arrItemUpData) {
                $reshipProcess = app::get('ome')->model('return_process')->db_dump(['reship_id' => $reship_id]);
                kernel::single('ome_return_process')->qualityCheckItemsSave($arrItemUpData, $reshipProcess, false);
            }
            if(app::get('ome')->getConf('ome.reship.diff_refuse') == 'true') {
                $diffRow =  $oReship_items->db->select("select normal_num,defective_num,num,bn,order_item_id FROM sdb_ome_reship_items WHERE reship_id=".$reship_id." AND return_type='return' AND (normal_num+defective_num) <> num");
                if($diffRow) {
                    kernel::database()->rollBack();
                    $msg = '退货单入库数量有差异不能入库!';
                    return false;
                }
            }
            kernel::database()->commit($tran);
        }
        
        //[京东一件代发]判断退货数量是否完成
        //@todo：WMS京东是按一个一个数量回传服务单
        if($wms_type == 'yjdf'){
            //获取未完成的服务单
            //@todo：[兼容]京东不退货仅退款的情况
            $sql = "SELECT por_id FROM sdb_ome_return_process WHERE reship_id=". $reship_id ." AND service_status NOT IN('cancel', 'finish')";
            $processInfo = $oReship->db->selectrow($sql);
            if($processInfo){
                $status = 'SECTION'; //部分完成
            }
        }
        
        if ($status == 'FINISH') {
            $reship_update_data = array('is_check'=>'11');
        }else{
            if ( $data["source"] == 'pda' && $status == 'PARTIN' ) {
                // pda过来的 部分收货
                $reship_update_data = array('is_check' => '14');
            } else {
                $reship_update_data = array('is_check'=>'13');
            }
        }
        
        //检测收货数量和申请数量是否有差异
        $auto_flag = true;
        $item_list =  $oReship_items->db->select("select normal_num,defective_num,num,bn,order_item_id FROM sdb_ome_reship_items WHERE reship_id=".$reship_id." AND return_type='return'");
        foreach ($item_list as $reshipitem) {
            $branch_num = intval($reshipitem['normal_num']) + intval($reshipitem['defective_num']);
            if (intval($reshipitem['num']) != $branch_num) {
                $auto_flag = false;
                break;
            }
        }
        
        //是否差异入库
        $reship_update_data['is_gap'] = '0';
        if (!$auto_flag) {
            $reship_update_data['is_gap'] = '1';
            //暂时先不打标到原订单上
            //kernel::single('ome_bill_label')->markBillLabel($reship['order_id'], '', 'SAMS_RETURN_GAP', 'order', $err);
        }
        
        //update
        $oReship->update($reship_update_data,array('reship_id'=>$reship_id));
        $reship_autoConf = app::get('ome')->getConf('ome.reship.auto_finish');
        
        //[京东一件代发]部分完成时,不进行质检操作,等待全部完成；
        if($wms_type=='yjdf' && $status=='SECTION'){
            $reship_autoConf = 'false';
        }
        
        //拦截音发货仅退款
        if ($data['auto_confirm']) {
            $reship_autoConf = 'true';
        }
        
        //判断退货数量,无差异直接完成
        if (in_array($type,array('return','change')) && $reship_autoConf=='true'){
            
            //finish
            if ($auto_flag){
                if($oReship->finish_aftersale($reship_id)){
                    $res = kernel::single('console_reship')->siso_iostockReship($reship_id, $msg);
                    if (!$res) {
                        return false;
                    }
                }
            }
        }
        
        if ($type=='refuse') {//拒收时流程
            $this->update_returnreship($reship);
        }
        
        //[京东一件代发]更新发货单上京东订单号可退货数量
        if($wms_type=='yjdf'){
            $reshipLib = kernel::single('ome_reship');
            $error_msg = '';
            $reshipLib->complete_reship_package($data, $error_msg);
        }
        
        //判断是否拦截发货单生成的退货单
        if($reship['delivery_id'] && $reship['flag_type']){
            //拦截入库
            if($reship['flag_type'] & ome_reship_const::__LANJIE_RUKU){
                //更新发货单为追回成功(使用问题件时间字段记录追回时间)
                $oReship->db->exec("UPDATE sdb_ome_delivery SET status='return_back', problem_time=". time() ." WHERE delivery_id=". $reship['delivery_id']);
            }
        }
        
        return true;
    }

    public function dealProcessItems(&$arrItemUpData, $bmId, $num, $branchId, $reshipItemId, $storeType ='0') {
        if(isset($arrItemUpData[$bmId])) {
            $arrItemUpData[$bmId]['check_num'] += $num;
            $arrItemUpData[$bmId]['items'][] = [
                'num' => $num,
                'store_type' => $storeType,
                'reship_item_id' => $reshipItemId,
                'branch_id' => $branchId,
            ];
        }else{
            $arrItemUpData[$bmId] = [
                'check_num' => $num,
                'items' => [[
                    'num' => $num,
                    'store_type' => $storeType,
                    'reship_item_id' => $reshipItemId,
                    'branch_id' => $branchId,
                ]]
            ];
        }
    }

    /**
     * 检查退货单是否存在判断
     * @param array $reship_bn 退货单编号
     */
    public function checkExist($reship_bn){
        $oReship = app::get('ome')->model('reship');
        $reship = $oReship->dump(array('reship_bn'=>$reship_bn),'*');
        return $reship;
    }

    public function checkValid($reship_bn,$status,&$msg){
        $reship = $this->checkExist($reship_bn);
        $is_check = $reship['is_check'];
        switch($status){
            case 'PARTIN':
            case 'FINISH':
                if ($is_check == '5' || $is_check == '7' || $is_check == '9' || $is_check == '10' || $is_check == '11') {
                    $msg = '所在状态不能入库';
                    return false;
                }else{
                    return true;
                }
                break;
            case 'CANCEL':
            case 'CLOSE':
                if ($is_check == '7' || $is_check == '8' || $is_check == '11' || $is_check == '13') {
                    $msg = '所在状态决定了不可以取消';
                    return false;
                }else{
                    return true;
                }
                break;
        }
        return true;
    }

    /**
     * 退换货单回传取消退货单
     * 
     * @param array $data
     * @author sunjing@shopex.cn
     */
    function cancel($data, &$msg)
    {
        $oReship = app::get('ome')->model('reship');
        $reship = $this->checkExist($data['reship_bn']);
        $reship_id = $reship['reship_id'];
        if (!$reship) {
            $msg = '退货单号不存在!';
        }
        
        //[京东一件代发]取消京东售后服务单
        if($data['wms_type'] == 'yjdf'){
            $keplerLib = kernel::single('ome_reship_kepler');
            
            $data['action'] = 'cancelService';
            $result = $keplerLib->process($data);
            
            //自动拒绝平台售后申请单
            //todo：自动审核平台售后申请(系统-->退换货自动审核设置-->是否自动审核平台售后申请)
            $auto_confirm = app::get('ome')->getConf('return.auto_confirm');
            if($auto_confirm == 'on'){
                $data['action'] = 'disposeMQ';
                $data['afsResultType'] = 'CANCEL';
                $result = $keplerLib->process($data);
                
                return true;
            }
        }
        
        kernel::single('console_reship')->releaseChangeFreeze($reship_id);
        
        $reship_update_data = array('is_check'=>'5');
        $oReship->update($reship_update_data,array('reship_id'=>$reship_id));
        
        //判断是否拦截发货单生成的退货单
        if($reship['delivery_id'] && $reship['flag_type']){
            //拦截入库
            if($reship['flag_type'] & ome_reship_const::__LANJIE_RUKU){
                //更新发货单为追回失败(使用问题件时间字段记录追回失败时间)
                $oReship->db->exec("UPDATE sdb_ome_delivery SET status='succ', logi_status='8', problem_time=". time() ." WHERE delivery_id=". $reship['delivery_id']);
            }
        }
        // 退货单取消后的service扩展点
        foreach(kernel::servicelist('console.service.reship.cancel.after') as $object) {
            if(method_exists($object, 'cancel_reship_after')) {
                $object->cancel_reship_after($reship_id);
            }
        }
        return true;
    }

    /**
     * pda退货异常
     * @param   array  $data
     * @param   string $msg
     * @author pangxianpeng@shopex.cn
     */
    function abnormal($data, &$msg){
        $oReship = app::get('ome')->model('reship');
        if ( !$data['reship_id'] ) {
            $msg = '退货单号不存在!';
            return;
        }
        return $oReship->update(array('is_check' => '12'),array('reship_id' => $data['reship_id']));
    } // end func


    /**
     * 退货追回入库
     * 
     * @param
     * @return
     * @access  public
     * @author sunjing@shopex.cn
     */
    function update_returnreship($reship)
    {
        $order_id = $reship['order_id'];
        $logi_no = $reship['logi_no'];
        $reship_id = $reship['reship_id'];
        $items_detailObj = app::get('ome')->model('delivery_items_detail');
        $operationLogObj = app::get('ome')->model('operation_log');
        $deliveryObj = app::get('ome')->model('delivery');
        $orderObj = app::get('ome')->model('orders');

        $oReship_item = app::get('ome')->model('reship_items');
        $deliveryInfo = $deliveryObj->dump(array('logi_no'=>$logi_no),'*');
        $delivery_id = $deliveryInfo['delivery_id'];
        $orderItems = $items_detailObj->getlist('*',array('order_id'=>$order_id,'delivery_id'=>$delivery_id));
        $orderdata = $orderObj->dump($order_id);
        //发货单关联订单sendnum扣减
        foreach($orderItems as $orderitem){

            $orderObj->db->exec('UPDATE sdb_ome_order_items SET return_num=return_num+'.$orderitem['number'].' WHERE order_id='.$order_id.' AND bn=\''.$orderitem['bn'].'\' AND obj_id='.$orderitem['order_obj_id']);

        }
        //订单相关状态变更

        kernel::single('ome_delivery_refuse')->update_orderStatus($order_id);
        //增加拒收退货入库明细
        kernel::single('console_reship')->siso_iostockReship($reship_id);
        //负销售单
        if ($orderdata['status'] == 'finish') {
            kernel::single('sales_aftersale')->generate_aftersale($reship_id,'refuse');
        }
        
        //退货追回成功后更新status状态
        $deliveryObj->db->exec("UPDATE sdb_ome_reship SET status='succ', is_check='7',t_end=".time()." WHERE reship_id=".$reship_id."");
        
        //订单添加相应的操作日志
        $operationLogObj->write_log('order_refuse@ome', $order_id, "发货后退回，订单做退货处理");
    }
    
    /**
     * 退货接单结果处理
     * 
     * @param array $data
     * @param string $error_msg
     * @return bool
     */
    public function reship_accept($data, &$error_msg=null)
    {
        $processObj = app::get('ome')->model('return_process');
        $Oreturn_product = app::get('ome')->model('return_product');
        $reshipItemObj = app::get('ome')->model('reship_items');
        $operLogObj = app::get('ome')->model('operation_log');
        
        $toolsLib = kernel::single('base_db_tools');
        
        $reship_bn = $data['reship_bn'];
        $reshipInfo = $this->checkExist($reship_bn);
        
        //check
        if(empty($reshipInfo)){
            $error_msg = '退货单号不存在,或者京东先推送了APPLY状态(系统会用队列重试推送)';
            return false;
        }
        
        if(!in_array($reshipInfo['is_check'], array('1', '2', '13'))){
            $error_msg = '退货单状态不支持接收结果[is_check:'. $reshipInfo['is_check'] .', reship_bn:'. $reship_bn .']';
            return false;
        }
        
        //判断是否存在
        $processInfo = $processObj->dump(array('reship_id'=>$reshipInfo['reship_id'], 'service_bn'=>$data['service_bn']), 'por_id,afsResultType,step_type');
        if($processInfo){
            if(empty($data['afsResultType']) && empty($data['stepType'])){
                $error_msg = '服务单号：'. $data['service_bn'] .' 已经存在,并且没有售后状态。';
                return false;
            }
            
            if($processInfo['afsResultType'] == $data['afsResultType'] && $processInfo['step_type'] == $data['stepType']){
                $error_msg = '服务单号：'. $data['service_bn'] .' 已经存在,并且售后状态未发生变化。';
                return false;
            }
            
            //update
            $updateData = array();
            if($data['afsResultType']){
                $updateData['afsResultType'] = $data['afsResultType'];
            }
            
            if($data['stepType']){
                $updateData['step_type'] = $data['stepType'];
            }
            
            $processObj->update($updateData, array('por_id'=>$processInfo['por_id']));
            
            $error_msg = '服务单号：'. $data['service_bn'] .' 更新售后状态成功。';
            return true;
        }
        
        //防止并发插入重复记录
        $_inner_key = sprintf("reship_%s", md5($reshipInfo['reship_id'] .'_'. $data['service_bn']));
        $checkData = cachecore::fetch($_inner_key);
        if($checkData === false) {
            cachecore::store($_inner_key, 1, 5);
        }else{
            $error_msg = '服务单号：'. $data['service_bn'] .' 已经存在,不能重复请求';
            return false;
        }
        
        //保存服务单
        $sdf = array(
                'reship_id'=>$reshipInfo['reship_id'],
                'order_id'=>$reshipInfo['order_id'],
                'return_id'=>$reshipInfo['return_id'],
                'member_id'=>$reshipInfo['member_id'],
                'title'=>'京东服务单',
                'content'=>$data['content'],
                'add_time'=>$reshipInfo['t_begin'],
                'shop_id'=>$reshipInfo['shop_id'],
                'last_modified'=> time(),
                'memo'=>$data['memo'],
                'branch_id'=>$reshipInfo['branch_id'],
                'attachment'=>$data['attachment'],
                'comment'=>$data['comment'],
                'process_data'=>$data['process_data'],
                'recieved'=>'true',
                'verify'=>'false',
                'wms_type' => $data['wms_type'], //WMS仓储类型[yjdf京东一件代发]
                'service_bn' => $data['service_bn'], //服务单号
                'service_type' => $data['service_type'], //售后服务类型
                'service_status' => 'accept', //服务单状态
                'package_bn' => $data['package_bn'], //包裹号
                'wms_order_code' => $data['wms_order_code'], //售后申请单号
                'logi_code' => $data['logi_code'], //物流公司编码
                'logi_no' => $data['logi_no'], //物流单号
                'afsResultType' => $data['afsResultType'], //平台处理结果
                'step_type' => $data['stepType'], //平台处理环节
        );
        
        if($reshipInfo['return_id']){
            $product_info = $Oreturn_product->dump(array('return_id'=>$reshipInfo['return_id']), 'title,content,add_time,shop_id,memo,attachment,comment');
            
            $sdf['title'] = $product_info['title'];
            $sdf['content'] = $product_info['content'];
            $sdf['add_time'] = $product_info['add_time'];
            $sdf['shop_id'] = $product_info['shop_id'];
            $sdf['memo'] = $product_info['memo'];
            $sdf['attachment'] = $product_info['attachment'];
            $sdf['comment'] = $product_info['comment'];
        }
        
        //save
        $result = $processObj->save($sdf);
        if(!$result){
            $error_msg = '保存收货单失败!';
            return false;
        }
        
        //insert_id
        $por_id = $sdf['por_id'];
        
        //退货单列表
        $reship_items = $reshipItemObj->getList('*', array('reship_id'=>$reshipInfo['reship_id'], 'return_type'=>'return'));
        if(empty($reship_items)){
            $error_msg = '没有退货明细记录!';
            return false;
        }
        
        //items
        foreach ($reship_items as $k => $v)
        {
            $product_bn = $v['bn'];
            
            //服务单sku
            $wmsItemInfo = $data['items'][$product_bn];
            $wms_sku_bn = $wmsItemInfo['wms_sku_bn'];
            $v['num'] = ($wmsItemInfo['normal_num'] ? $wmsItemInfo['normal_num'] : $wmsItemInfo['defective_num']);
            $v['num'] = ($v['num'] ? $v['num'] : 1);
            
            //只处理服务单上的sku货号
            if(empty($wmsItemInfo)){
                continue;
            }
            
            $process_items = array();
            for($i=0; $i<$v['num']; $i++)
            {
                $process_items['por_id'] = $por_id;
                
                $process_items['reship_id'] = $v['reship_id'];
                $process_items['order_id'] = $sdf['order_id'];
                $process_items['return_id'] = $sdf['return_id'];
                $process_items['product_id'] = $v['product_id'];
                $process_items['bn'] = $v['bn'];
                $process_items['name'] = $v['product_name'];
                $process_items['branch_id'] = $v['branch_id'];
                $process_items['op_id'] = $v['op_id'];
                $process_items['acttime'] = time();
                
                $process_items['num'] = 1;
                $process_items['wms_sku_bn'] = $wms_sku_bn;
                
                $rs = $processObj->db->exec('select * from sdb_ome_return_process_items where 0=1');
                
                $sql = $toolsLib->getinsertsql($rs, $process_items);
                $processObj->db->exec($sql);
            }
        }
        
        //logs
        $operLogObj->write_log('reship@ome', $reshipInfo['reship_id'], '服务单号：'. $sdf['service_bn'] .' 接收成功');
        
        return true;
    }
}
