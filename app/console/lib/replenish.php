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
/**
 * 自动补货任务Lib类
 *
 * @author wangbiao<wangbiao@shopex.cn>
 * @version $Id: Z
 */
class console_replenish
{
    
    /**
     * 确认生成补货单
     * 
     * @param int $cursor_id
     * @param array $params
     * @param string $error_msg
     * @return boolean
     */

    public function disposeReplenish(&$cursor_id, $params, &$error_msg=null)
    {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');
        
        $reTaskObj = app::get('console')->model('replenish_task');
        $suggestObj = app::get('console')->model('replenish_suggest');
        $suggestitemMdl = app::get('console')->model('replenish_suggest_items');
        $branchProObj = app::get('ome')->model('branch_product');
       
        $cpfrObj = app::get('console')->model('cpfr');
        $cpfrItemObj = app::get('console')->model('cpfr_items');
        $logMdl = app::get('ome')->model('operation_log');
        
        //data
        $sdfdata = $params['sdfdata'];
        $sug_id = $sdfdata['sug_id'];
        $task_bn = $sdfdata['task_bn'];
        
        //操作人
        $operator = kernel::single('desktop_user')->get_name();
        $suggests = $suggestObj->dump(array('sug_id'=>$sug_id),'*');
        
        if( in_array($suggests['bill_type'],array('try_drink','demo')) ){//试饮不生成单据。生成订单

            return true;
        }
        //调出仓库
        $out_branch_id = $suggests['out_branch_id'];
        if(empty($out_branch_id)){
            $error_msg = '调出仓库不能为空';
            $logMdl->write_log('replenish_suggest@console',$sug_id,$error_msg);           
            return false;
        }
        
        $sql = "SELECT branch_id,branch_bn,name FROM sdb_ome_branch WHERE branch_id=".$out_branch_id;
        $outBranchInfo = $suggestObj->db->selectrow($sql);
        $out_branch_bn = $outBranchInfo['branch_bn'];
        
        //补货建议单列表
        $dataList = $suggestitemMdl->getList('*', array('sug_id'=>$sug_id));
        if(empty($dataList)){
            $error_msg = '没有补货建议单';
            
            return false;
        }
        $o2o_branch_id = $suggests['branch_id'];
        $product_ids = array();
 
        foreach ($dataList as $key => $val)
        {
            $bm_id = $val['bm_id'];
            
            
            $product_ids[$bm_id] = $bm_id;
            
        }
        
        //门店仓库列表
        $sql = "SELECT branch_id,branch_bn,name FROM sdb_ome_branch WHERE branch_id IN(". $o2o_branch_id .")";
        $tempList = $suggestObj->db->select($sql);
        
        $o2oBranchList = array_column($tempList, null, 'branch_id');
        
        //获取调出仓库库存
        $tempList = $branchProObj->getList('branch_id,product_id,store,store_freeze,is_locked,safe_store', array('branch_id'=>$out_branch_id, 'product_id'=>$product_ids));
        if(empty($tempList)){
            $error_msg = '调出仓库与商品没有映射库存关系';
           
            return false;
        }
        
        $branchStore = array();
        foreach ($tempList as $key => $val)
        {
            $branch_id = $val['branch_id'];
            $product_id = $val['product_id'];
            
            $branchStore[$branch_id][$product_id] = $val['store'] - $val['store_freeze'];
        }
        
        
        //获取门店仓库库存
        $tempList = $branchProObj->getList('branch_id,product_id as bm_id,store,store_freeze', array('branch_id'=>$o2o_branch_id, 'product_id'=>$product_ids));
        if(empty($tempList)){
            $error_msg = '门店仓库没有库存';
            $logMdl->write_log('replenish_suggest@console',$sug_id,$error_msg);      
                    
            //return false;
        }
        
        $o2oBranchStore = array();
        foreach ($tempList as $key => $val)
        {
            $branch_id = $val['branch_id'];
            $product_id = $val['bm_id'];
            
            $o2oBranchStore[$branch_id][$product_id] = $val['store'] - $val['store_freeze'];
        }
        
        //params
        $apply_total = 0; //申请补货总数
        $actual_total = 0; //实际补货总数
        $sku_total = 0; //调拨SKU总数
        $store_bn = $suggests['store_bn'];
        //组织数据
        $sdf = array();
        foreach ($dataList as $key => $val)
        {
            $o2o_branch_bn = $o2oBranchList[$o2o_branch_id]['branch_bn'];
            $bm_id = $val['bm_id'];
            $reple_nums = $val['reple_nums']; //申请补货数量
            
            //check
            if(empty($reple_nums)){
                continue;
            }
            
            //调出仓库库存
            $from_branch_store = $branchStore[$out_branch_id][$bm_id];
            $from_branch_store = intval($from_branch_store);
            
            //门店仓库库存
            $to_branch_store = $o2oBranchStore[$o2o_branch_id][$bm_id];
            $to_branch_store = intval($to_branch_store);
            
            //实际补货数量
            $in_nums = $reple_nums;
            if ($from_branch_store < $reple_nums) {
                $in_nums = $from_branch_store;
                
                $branchStore[$out_branch_id][$bm_id] = 0;
            } else {
                $branchStore[$out_branch_id][$bm_id] -= $in_nums;
            }
            
            //items
            $sdf[$store_bn][$bm_id] = array(
                    'bm_id' => $bm_id,
                    'material_bn' => $val['material_bn'],
                    'in_nums' => $in_nums, //实际补货数量
                    'reple_nums' => $val['reple_nums'], //建议补货数量
                    'apply_nums' => $val['apply_nums'], //申请补货数量
                    'from_branch_store' => $from_branch_store, //调出仓库存
                    'to_branch_store' => $to_branch_store, //调入门店仓库存
                    'to_branch_id' => $o2o_branch_id, //调入仓库ID
            );
            
            //count
            $sku_total++; //SKU总数
            $apply_total += intval($val['apply_nums']); //申请补货总数
            $actual_total += $in_nums; //实际补货总数
        }
        
        if(empty($sdf)){
            $error_msg = '没有可调拨入库的货品';
            
            return false;
        }
        
        //门店总数
        $store_total = count($sdf);
        
        $cpfr_detail = $cpfrObj->dump(array('origin_bn'=>$suggests['task_bn']),'cpfr_id');
        if($cpfr_detail){
            $error_msg = '补货任务已存在';
            $logMdl->write_log('replenish_suggest@console',$sug_id,$error_msg);      
            return false;
        }
        //开启事务
        $transaction = kernel::database()->beginTransaction();
        
        //插入到配货单表
        $masterSdf = array(
            'cpfr_bn' => uniqid(date('YmdHis')), //补货单编码
            'cpfr_name' => $suggests['task_bn'], //补货单名称
            'branch_id' => $out_branch_id, //调出仓库ID
            'branch_bn' => $out_branch_bn, //调出仓库编码
            'store_total' => $store_total, //门店总数
            'sku_total' => $sku_total, //SKU总数
            'num_total' => $actual_total, //申请补货总数
            'actual_total' => $apply_total, //实际补货总数
            'operator' => $operator, //操作人
            'adjust_type' => 'replenish', //调整库存类型
            'physics_id'    =>$suggests['physics_id'],
            'origin_bn' => $suggests['task_bn'], //补货任务号
            'create_time' => time(), //创建时间
            'last_modified' => time(), //最后更新时间
        );
        $cpfr_id = $cpfrObj->insert($masterSdf);
        if(!$cpfr_id){
            //回滚事务
            kernel::database()->rollBack();
            
            $error_msg = '创建配货单失败';
                        
            return false;
        }
        
        //items
        foreach ($sdf as $store_bn => $itemList)
        {
            if(empty($itemList)){
                continue;
            }
            
            foreach ($itemList as $bm_id => $itemVal)
            {
                $itemSdf = array(
                    'cpfr_id' => $cpfr_id,
                    'store_bn' => $store_bn, //门店编码
                    'to_branch_id' => $itemVal['to_branch_id'], //调入仓库ID
                    'product_id' => $itemVal['bm_id'],
                    'bn' => $itemVal['material_bn'],
                    'num' => $itemVal['in_nums'], //实际补货数量
                    'original_num' => $itemVal['apply_nums'], //申请补货数量
                    'reple_nums' => $itemVal['reple_nums'], //建议补货数量
                    'from_branch_store' => $itemVal['from_branch_store'], //调出仓库存
                    'to_branch_store' => $itemVal['to_branch_store'], //调入仓库存
                );
                $cpfr_item_id = $cpfrItemObj->insert($itemSdf);
                if(!$cpfr_item_id){
                    //回滚事务
                    kernel::database()->rollBack();
                    
                    $error_msg = '创建配货单明细失败';
                    
                    return false;
                }
            }
        }
        
        //提交事务
        kernel::database()->commit($transaction);
        
        //更新补货建议单状态
        $suggestObj->update(array('sug_status'=>'1'), array('sug_id'=>$sug_id));
        
        //创建调拨单
        $msg = '';
        $params = array(
            'cpfr_id' => $cpfr_id, //配货单ID
            'cpfr_bn' => $masterSdf['cpfr_bn'], //配货单编码
            'business_bn'=>$suggests['task_bn'],
            'physics_id'=>$suggests['physics_id'],
            'sug_id'    => $sug_id,
               

        );
        $result = $this->createAppropriation($params, $msg);
        if(!$result && $msg){
            $error_msg = $msg;
            
        }
        
        //更新配货单状态
        $cpfrObj->update(array('bill_status'=>'2'), array('cpfr_id'=>$cpfr_id));
        
        //销毁
        unset($tempList, $dataList, $sdf);
        
        return false;
    }
    
    /**
     * 创建调拨单
     * 
     * @param array $params 配货单信息
     * @param string $error_msg
     * @return bool
     */
    public function createAppropriation($params, $error_msg=null)
    {
        $cpfrObj = app::get('console')->model('cpfr');
        $cpfrItemObj = app::get('console')->model('cpfr_items');
        
        $cpfr_id = intval($params['cpfr_id']);
        $cpfr_bn = $params['cpfr_bn'];
        $sug_id = $params['sug_id'];
        $logMdl = app::get('ome')->model('operation_log');
        //check
        if(empty($cpfr_id) || empty($cpfr_bn)){
            $error_msg = '创建调拨单失败,没有配货单信息';
                        
            return false;
        }
        
        //filter
        $filter = array();
        if($cpfr_id){
            $filter['cpfr_id'] = $cpfr_id;
        }else{
            $filter['cpfr_bn'] = $cpfr_bn;
        }
        
        //配货单信息
        $cpfrInfo = $cpfrObj->dump($filter, '*');
        if(empty($cpfrInfo)){
            $error_msg = '创建调拨单失败,没有获取到配货单信息';
            return false;
        }
        
        if($cpfrInfo['bill_status'] == '2'){
            $error_msg = '创建调拨单失败,配货单状态不允许操作';
            return false;
        }
        
        $cpfr_id = $cpfrInfo['cpfr_id'];
        $out_branch_id = intval($cpfrInfo['branch_id']);
        $operator = $cpfrInfo['operator'];
        
        //check
        if(empty($out_branch_id)){
            $error_msg = '创建调拨单失败,没有调出仓库';
            return false;
        }
        
        //随机调拨出库仓对应的物流公司
        $sql = "SELECT corp_id,type,name FROM sdb_ome_dly_corp WHERE branch_id=". $out_branch_id ." AND disabled='false'";
        $corpInfo = $cpfrObj->db->selectrow($sql);
        if(empty($corpInfo)){
            //随机获取一个物流公司
            $sql = "SELECT corp_id,type,name FROM sdb_ome_dly_corp WHERE disabled='false' AND d_type=1";
            $corpInfo = $cpfrObj->db->selectrow($sql);
        }
        $corp_id = $corpInfo['corp_id'];
        
        //不同门店列表
        $sql = "SELECT DISTINCT to_branch_id,store_bn FROM sdb_console_cpfr_items WHERE cpfr_id=". $cpfr_id ."";
        $storeList = $cpfrObj->db->select($sql);
        if(empty($storeList)){
            $error_msg = '创建调拨单失败,没有入库仓信息';
            
            return false;
        }
        
        //检查实际补货数量
        $sql = "SELECT item_id FROM sdb_console_cpfr_items WHERE cpfr_id=". $cpfr_id ." AND num>0";
        $checkItems = $cpfrObj->db->selectrow($sql);
        if(empty($checkItems)){
            $error_msg = '没有实际补货数量,调出仓库存不足'; 
            return false;
        }
        
        $iso_ids = array();
        $errorMsgs = array();
        $appropriation_type = 2; //出入库调拨类型
        

        //按门店进行创建调拨单
        foreach ($storeList as $key => $val)
        {
            $store_bn = $val['store_bn'];
            $to_branch_id = $val['to_branch_id']; //调入仓库
            
            //配货单明细
            $itemList = $cpfrItemObj->getList('*', array('cpfr_id'=>$cpfr_id, 'store_bn'=>$store_bn,'to_branch_id'=>$to_branch_id));
            if(empty($itemList)){
                continue;
            }
            
            //防止重复
            $appropriation_detail = $cpfrObj->db->selectrow("SELECT appropriation_id FROM sdb_taoguanallocate_appropriation WHERE original_bn='".$cpfr_bn."' AND to_branch_id=".$to_branch_id."");

            if($appropriation_detail){
                continue;
            }
            //开启事务
            $transaction = kernel::database()->beginTransaction();
            
            //format
            $itemList[0]['corp_id'] = $corp_id; //物流公司
            $itemList[0]['from_branch_id'] = $out_branch_id; //调出仓库
            $itemList[0]['to_branch_id'] = $to_branch_id; //调入仓库
            $itemList[0]['original_bn'] = $cpfr_bn; //配货单号
            $itemList[0]['bill_type'] = 'replenishment'; 
            $itemList[0]['business_bn'] = $params['business_bn']; //业务单号
            $itemList[0]['to_physics_id'] = $params['physics_id']; //业务单号
            
            // 备注只显示：业务单号
            //$memo = sprintf('配货单[%s]门店[%s]创建调拨单', $cpfr_bn, $store_bn);
            $memo = $params['business_bn'];
            
            //iso
            $msg = '';
            $iso_id = kernel::single('console_receipt_allocate')->to_savestore($itemList, $appropriation_type, $memo, $operator, $msg);
            if(!$iso_id){
                //回滚事务

                kernel::database()->rollBack();
                
                //error_msg
                $errorMsgs[] = sprintf('配货单[%s]创建调拨单失败：%s', $cpfr_bn, $msg);

                continue;
            }
            
            //提交事务
            kernel::database()->commit($transaction);
            
            $iso_ids[] = $iso_id;
        }
        //更新配货单状态
        //更新配货单状态
        $cpfrObj->update(array('bill_status'=>'2'), array('cpfr_id'=>$cpfr_id));
       
        //error
        if($errorMsgs){
            $error_msg = implode(';', $errorMsgs);
            $error_msg = substr($error_msg, 0, 180);
               
            return false;
        }
        
        
        //自动审核调拨单
        $errorMsgs = array();
        foreach ($iso_ids as $key => $iso_id)
        {
            $result = kernel::single('console_iostockorder')->autoCheck($iso_id);
            if($result[0] === false){
                $errorMsgs[] = 'iso_id:'.$iso_id.'审核失败:'.$result[1];
            }
        }
       
        if($errorMsgs){
            $error_msg = implode(';', $errorMsgs);
            $error_msg = substr($error_msg, 0, 180);
                    
            return false;
        }
        
        return true;
    }
    
    /**
     * 更新补货任务为失败状态
     */
    public function updateTaskailStatus($task_id, $task_msg)
    {
        $reTaskObj = app::get('console')->model('replenish_task');
        
        //更新为失败状态
        $reTaskObj->update(array('task_status'=>'7', 'task_msg'=>$task_msg), array('task_id'=>$task_id));
        
        return true;
    }

    /**
     * 补货建议单创建
     * @return
     */
    public function create($data, $msg)
    {

        $suggestMdl         = app::get('console')->model('replenish_suggest');
        $branchMdl        = app::get('ome')->model('branch');
        $sugitemsMdl     = app::get('console')->model('replenish_suggest_items');
        $basicMaterialObj = app::get('material')->model('basic_material');

        $suggest_data = array(
            'create_time'       => time(),
            'apply_name'        => $data['apply_name'],
            'memo'              => $data['memo'],
            'task_bn'           => $data['task_bn'],
            'store_bn'          => $data['store_bn'],
            'branch_id'         => $data['branch_id'],
            'source'            => $data['source'],
            'physics_id'        => $data['physics_id'],
            'product_amount'    => $data['product_amount'],
            'out_branch_bn'     => $data['out_branch_bn'], 
            'out_branch_id'     => $data['out_branch_id'],

        );

        if($data['bill_type']) $suggest_data['bill_type'] = $data['bill_type'];
   
        $suggestMdl->save($suggest_data);
        $sug_id = $suggest_data['sug_id'];
        foreach ((array) $data['items'] as $k => $v) {
            $product = $basicMaterialObj->dump(array('material_bn' => $v['material_bn']), 'bm_id, material_bn, material_name');
            $items_data = array(
                'sug_id'           => $sug_id,
                'material_bn'      => $v['material_bn'],
                'bm_id'            => $product['bm_id'],
                'reple_nums'       => $v['nums'],
                'apply_nums'       => $v['nums'],
                'price'            => $v['price'],   
                  
            );
            
            $sugitemsMdl->save($items_data);

        }
        
        $is_auto_check = true;
        if (isset($data['check_auto']) && $data['check_auto'] === '0' ){
            $is_auto_check = false;
        }
        if($suggest_data['out_branch_id']>0 && $is_auto_check==true){
            $this->confirmReplenish($suggest_data);
            
        }
            
        return true;

    }

    

    /**
     * 生成入库单
     */
    public function createStockin($sug_id){

        $suggestMdl= app::get('console')->model('replenish_suggest');
       
        $suggest = $suggestMdl->dump(array('sug_id'=>$sug_id),'*');
        //判断是否已生成
        $isoMdl = app::get('taoguaniostockorder')->model('iso');

        $iso = $isoMdl->db_dump(array('bill_type'=>array('try_drink','demo') ,'original_bn'=>$suggest['task_bn']),'iso_id');
        if($iso){
            return [false, ['msg'=>'入库单已存在']];
        }
        $sugitemsMdl     = app::get('console')->model('replenish_suggest_items');

        $items = $sugitemsMdl->getlist('*',array('sug_id'=>$sug_id));

        if(empty($items)) {
            return [true, ['msg'=>'缺少明细']];
        }
        kernel::database()->beginTransaction();
        $products = [];
        foreach ($items as $v) {
            $products[$v['bm_id']] = [
                'bn'    => $v['material_bn'],
                'name'  => '',
                'nums'  => $v['reple_nums'],
                'unit'  => '',
                'price' => 0,
            ];
        }
        $op = kernel::single('ome_func')->getDesktopUser();

        $bill_type = $suggest['bill_type'];
       
        $data = array(
            'iostockorder_name' => date('Ymd') .'('.$suggest['bill_type'].')补货入库单',
            'supplier'          => '',
            'supplier_id'       => 0,
            'branch'            => $suggest['branch_id'],
            'extrabranch_id'    => $suggest['out_branch_id'],
            'type_id'           => ome_iostock::DIRECT_STORAGE,
            'iso_price'         => 0,
            'memo'              => '', //变量不存在：(string)$addData['memo'],
            'operator'          => $op['op_name'],
            'original_bn'       => $suggest['task_bn'],
            'original_id'       => $suggest['task_id'],
            'physics_id'        => $suggest['physics_id'],
            'products'          => $products,
            'appropriation_no'  => '',
            'bill_type'         => $suggest['bill_type'],
            'business_bn'       => $suggest['task_bn'],
           
        );


        $data['check'] = 'Y';

        $iostockorder_instance = kernel::single('console_iostockorder');
        $rs = $iostockorder_instance->save_iostockorder($data, $msg);
        $msg = '生成试饮入库单';
        if($rs) {
            $msg.="成功";
            kernel::database()->commit();
        } else {

            kernel::database()->rollBack();

            $msg.="失败";
        }
        $logMdl = app::get('ome')->model('operation_log');
        $logMdl->write_log('replenish_suggest@console',$sug_id,$msg);  
        return [$rs, ['msg'=>$msg]];
    }

    /**
     * 补货审核
     * @return 
     */
    public function confirmReplenish($data){
        $sug_id = $data['sug_id'];

        $suggestObj = app::get('console')->model('replenish_suggest');
        $suggest = $suggestObj->dump(array('sug_id'=>$sug_id),'*');

        if(!in_array($suggest['sug_status'],array('0'))){
            return [false, ['msg'=>'非为审核状态']];
        }
        //判断库存不足
        $sugitemsMdl     = app::get('console')->model('replenish_suggest_items');
        $items = $sugitemsMdl->getlist('*',array('sug_id'=>$sug_id));

        $bm_id_list = array_column($items, 'bm_id');
        $bpModel       = app::get('ome')->model('branch_product');
        $out_branch_id =  $data['out_branch_id'];
        $product_store = array();
        foreach($bpModel->getList('product_id,branch_id,store,store_freeze', array('product_id' => $bm_id_list, 'branch_id' => $out_branch_id)) as $value){

            $product_store[$value['branch_id']][$value['product_id']] = $value['store'] - $value['store_freeze'];
        }

        foreach ((array)$items as $key => $value) {
            $bm_id = $value['bm_id'];
           
            if ($value['nums'] > $product_store[$out_branch_id][$bm_id]) {
                $msg = $value['material_bn'].'库存不足';
                return [false, ['msg'=>$msg]];
            }
        }
      
        $suggestObj->update(array('sug_status'=>'1'), array('sug_id'=>$sug_id));
 
        $sdf = array('sug_id'=>$data['sug_id'], 'task_bn'=>$data['task_bn']);

        $rs = $this->disposeReplenish($cursor_id, array('sdfdata'=>$sdf), $error_msg);
        $logMdl = app::get('ome')->model('operation_log');
        if($rs){
            //更新补货建议单状态
            $suggestObj->update(array('sug_status'=>'2'), array('sug_id'=>$sug_id));
        }else{
            $logMdl->write_log('replenish_suggest@console',$sug_id,$error_msg);  

        }
        return [true, ['msg'=>'成功']];
    }
}