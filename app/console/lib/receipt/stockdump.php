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
 * 库内转储
 */
class console_receipt_stockdump{



/**
    * 取消转储单
    */
class console_receipt_stockdump {

    public $error_info = array(
        'STOCKDUMP_BN_DOES_NOT_EXIST' => '转储单号不存在!',
        'STOCKDUMP_BN_IN_STATUS' => '转储单状态不能为 已入库/失败/取消',
        'SAVE_STOCKDUMP_RECORD_FAIL' => '保存转储单失败!',
        'SAVE_STOCKDUMP_ACTUAL_SHORTAGE' => '出库数量大于调出仓库实际库存,无法出库!',
        'STOCKDUMP_ACTUAL_SHORTAGE' => '出库数量大于调出仓库实际库存,无法出库,确认失败!',
        'UPDATA_CONFIRM_TYPE_FAIL' => '更新确认状态失败',
    );
    
    static $in_status = array(
        'FINISH'=>'1',
    );
    
    /**
     * 失败信息回传
     */
    public function fail($msg=''){
        $fail = array('rsp'=>'fail','msg'=>$msg);
        return $fail;
    }
    
    /**
     * 成功信息回传
     */
    public function success($msg=''){
        $success = array('rsp'=>'succ','msg'=>$msg);
        return $success;
    } 
    
    /**
     * 保存
     */
    public function do_save($appro_bn,$options){
        set_time_limit(0);
        $oAppro = app::get('console')->model('stockdump');
        $oAppro_items = app::get('console')->model('stockdump_items');
        
        //验证转储单是否存在
        $is_exist = $oAppro->dump(array('stockdump_bn'=>$appro_bn),'stockdump_id,stockdump_bn,in_status,self_status,memo');
        if(!$is_exist['stockdump_bn']){
            return $this->fail($this->error_info['STOCKDUMP_BN_DOES_NOT_EXIST']);
        }
        
        //判断转储单号不为已入库/失败/取消
        if($is_exist['in_status'] == 1 or $is_exist['in_status'] == 2 or $is_exist['self_status'] == 0){
            return $this->success($this->error_info['STOCKDUMP_BN_IN_STATUS']);
        }
        
        $appro_lists = $oAppro_items->getList(
            'item_id,stockdump_id,stockdump_bn,product_id,bn,num,appro_price',
            array('stockdump_bn'=>$appro_bn)
        );

        $item = array();
        foreach($options['items'] as $k=>$v){
            if($v['bn'] == ''){
                unset($options['items'][$k]);
            }
        }
        
        //格式化item
        if(count($options['items'])>0){
            foreach($options['items'] as $v){
                $item[trim($v['bn'])] = array(
                    'normal_num' => $v['num'],
                );
            }
        }
        
        $options['items'] = null;
        unset( $options['items'] );

        $in_status = $options['status'];
        kernel::database()->beginTransaction();
        
        foreach($appro_lists as $key=>$value){
            if( count($item) == 0){
                $in_nums = $value['num'];
            }else{
                $in_nums = $item[$value['bn']]['normal_num'];    
            }
            $appro_lists[$key]['in_nums'] = $in_nums;
            $oAppro_items->update(array('in_nums'=>$in_nums),array('item_id'=>$value['item_id']));
        }
        
        kernel::database()->commit();
        $appro_lists = $item = null;
        
        // unset
        unset($appro_lists,$item);
        
        $is_stock_diffnum_bool =  $this->is_stock_diffnum($appro_bn);

        $tran = kernel::database()->beginTransaction();
        
        //状态为FINISH已入库
        if($options['status'] == 'FINISH' && $is_stock_diffnum_bool == false){
            //如果无差异,更改确认状态为无需确认
            $affect_rows = $oAppro->update(array('confirm_type'=>0),array('stockdump_bn'=>$appro_bn,'confirm_type'=>1));
            if (is_bool($affect_rows)) {
                kernel::database()->rollBack();
                return $this->fail($this->error_info['UPDATA_CONFIRM_TYPE_FAIL']);
            }
            
            //执行出入库事务
            $appro_lists = $oAppro_items->getList('stockdump_id,stockdump_bn,item_id,bn,num,in_nums,defective_num,appro_price',array('stockdump_bn'=>$appro_bn, 'confirm_type'=>0));
            
            $ioResult = $this->save_iostock($appro_lists);
            
            if($ioResult['rsp'] == 'fail'){
                kernel::database()->rollBack();
                return $ioResult;
            } else {
                kernel::database()->commit($tran);
            }
        }else{
            //如果有差异,更改确认状态为未确认
            $oAppro->update(array('confirm_type'=>1),array('stockdump_bn'=>$appro_bn));
            kernel::database()->commit($tran);
        }
        
        $wsoMdl = app::get('console')->model('wms_transferorder');
        $wsoRow = $wsoMdl->db_dump(['order_code'=>$appro_bn, 'stockdump_status'=>'1'], 'id');
        if(!$wsoRow) {
            $wsoRow = $wsoMdl->db_dump(['erp_order_code'=>$appro_bn, 'stockdump_status'=>'1'], 'id');
        }
        
        if($wsoRow) {
            $wsoRs = $wsoMdl->update(['stockdump_id'=>$is_exist['stockdump_id'], 'stockdump_status'=>'2'], ['id'=>$wsoRow['id'], 'stockdump_status'=>'1']);
            if(!is_bool($wsoRs)) {
                app::get('ome')->model('operation_log')->write_log('wms_transferorder@console',$wsoRow['id'], '调拨完成');
            }
        }
        
        //更新转储单备注
        if($options['memo']){
            $memo = $is_exist['memo']." ";
            $memo.=$options['memo'];
            $oAppro->update(array('memo'=>$memo),array('stockdump_bn'=>$appro_bn));
        }
        
        //更新转储单状态
        $this->update_status($appro_bn,'1', $is_stock_diffnum_bool);
        
        return $this->success();
    }

    /**
     * 出入库事务
     */
    public function save_iostock(&$appro_lists){
        $basicMStockFreezeLib   = kernel::single('material_basic_material_stock_freeze');
        
         $oAppro = app::get('console')->model('stockdump');
         $original_operator = $oAppro->dump(array('stockdump_id'=>$appro_lists[0]['stockdump_id']),'stockdump_id,stockdump_bn     ,operator_name,from_branch_id,to_branch_id,from_branch_name,to_branch_name,memo');
         $iostock_bn =  $original_operator['stockdump_bn'];
         $oBranchProduct = app::get('ome')->model("branch_product");

         $iostockData_in = array(
            'branch_id'=> $original_operator['to_branch_id'],
            'original_bn'=>$iostock_bn,
            'original_id'=>$original_operator['stockdump_id'],
            'operator'=>$original_operator['operator_name'],
            'memo'=>$original_operator['memo'],
         );
         $iostockData_out = array(
            'branch_id'=> $original_operator['from_branch_id'],
            'original_bn'=>$iostock_bn,
            'original_id'=>$original_operator['stockdump_id'],
            'operator'=>$original_operator['operator_name'],
            'memo'=>$original_operator['memo'],
         );
         
         foreach($appro_lists as $key=>$value){
             //入库数据
            $item = array(
                'bn' => $value['bn'],
                'price'=>$value['appro_price'],
                'iso_items_id'=>$value['item_id'],
                'nums'=>$value['in_nums']
            );
            $iostockData_in['items'][] = $item;
            $iostockData_out['items'][] = $item;
            
            //出库单 差异数量检测 判断实际库存与出库数量 防止出现负库存
            $is_branch_product_data = $oBranchProduct->getList(
                'store,store_freeze',
                array(
                    'branch_id' => $original_operator['from_branch_id'],
                    'product_id' => $value['product_id']
                )
            );
            
            //根据仓库ID、基础物料ID获取该物料仓库级的预占
            $is_branch_product_data[0]['store_freeze']    = $basicMStockFreezeLib->getBranchFreeze($value['product_id'], $original_operator['from_branch_id']);
            
            //实际可用库存
            $branch_product_available_nums = $is_branch_product_data[0]['store'] - $is_branch_product_data[0]['store_freeze'];
            
            //如果释放冻结库存后的实际库存仍然 小于 超出预计出库的数量 则确认失败
            if( $branch_product_available_nums < ($value['in_nums'] - $value['num']) ){
                return $this->fail($this->error_info['SAVE_STOCKDUMP_ACTUAL_SHORTAGE']);
            }
         }
         
        $iostock_result = $iostockoutresult = $res_clear_freeze = false;
        $iostockObj = kernel::single('siso_receipt_iostock_stockdumpin');
        
        //入库
        $iostockObj->_typeId = 600;
        $iostock_result = $iostockObj->create($iostockData_in, $data, $msg);
        if ($iostock_result){
            $iostockData_in = null;
            unset($iostockData_in);
            //出库，释放预占库存
            $res_clear_freeze = $this->clear_stockout_store_freeze($iostock_bn, $msg);
            if ($res_clear_freeze) {
                $iostockoutObj = kernel::single('siso_receipt_iostock_stockdumpout');
                $iostockoutObj->_typeId = 9;
                $iostockoutresult = $iostockoutObj->create($iostockData_out, $data, $msg);
                $iostockData_out = null;
                unset($iostockData_out);
            }
        }
        
        if ($iostock_result && $iostockoutresult && $res_clear_freeze){
            return $this->success();
        }else{
            return $this->fail('出入库失败：'.$msg);
        }
    }

    /**
     * 更新状态
     */
    public function update_status($appro_bn,$in_status, $is_diff = false){
        $oAppro = app::get('console')->model('stockdump');
        
        //修改调拨单的状态
        $data = array('in_status'=>$in_status);
        if ($in_status == 1) {
            $data['confirm_time'] = time();
            $data['confirm_type'] = $is_diff == true ? '1' : '0';
        }

        $oAppro->update($data,array('stockdump_bn'=>$appro_bn));
        return true;
    }
    
    /**
     * 判断全部出入库时数量是否有差异
     * false 无差异 true 有差异
     */
    public function is_stock_diffnum($appro_bn = ''){
        $result = false;//最终返回状态
        if($appro_bn == '') return $result;
        $sql = "SELECT COUNT(*) as _c FROM sdb_console_stockdump_items WHERE stockdump_bn = '".$appro_bn."' AND in_nums != num";
        $tmp = kernel::database()->select($sql);
        if($tmp[0]['_c']>0){
            $result = true;
        }else{
            $result = false;
        }
        
        return $result;
    }
    
    /**
     * 释放出库单预占库存量
     */
    public function clear_stockout_store_freeze($appro_bn, &$err_msg = '')
    {        
        $oAppro = app::get('console')->model('stockdump');
        $oAppro_items = app::get('console')->model('stockdump_items');
        $pStockObj = kernel::single('console_stock_products');
        
        $appro_lists = $oAppro_items->getList(
            'item_id,stockdump_id,stockdump_bn,product_id,bn,num,in_nums,appro_price',
            array('stockdump_bn'=>$appro_bn)
        );
        
        //库存出入库单信息
        $appro_data = $oAppro->dump(array('stockdump_bn'=>$appro_bn),'stockdump_id, from_branch_id, to_branch_id');
        $branch_id       = $appro_data['from_branch_id'];
        $stockdump_id    = $appro_data['stockdump_id'];
        
        //库存管控处理
        $storeManageLib    = kernel::single('ome_store_manage');
        $storeManageLib->loadBranch(array('branch_id'=>$branch_id));
        
        $params    = array();
        $params['node_type'] = 'finishStockdump';
        $params['params']    = array('stockdump_id'=>$stockdump_id, 'branch_id'=>$branch_id);
        $params['params']['items'] = $appro_lists;
        
        $res = $storeManageLib->processBranchStore($params, $err_msg);
        
        return $res;
    }

    public function splitIostock($iostockBn = '',&$iostockData = array(),$type = '',$msg = '',$iotype = ''){
        $iostockObj = kernel::service('ome.iostock');
        $max = 200;
        $i = 1;
        $data = array();
        
        foreach($iostockData as $k=>$v){
           $data[$k] = $v;
           if($i == $max){
               $stock = $iostockObj->set($iostockBn,$data,$type,$msg,$iotype);
               $i = 1;
               $data = array();
           }else{
               $i++;
           }
        }
        
        if($i>1 && $i<=$max){
            $stock = $iostockObj->set($iostockBn,$data,$type,$msg,$iotype);
        }
        
        $data = array();
    }
    
    /***
    * 查询编号是否存在
    * @stockdump_bn 转储单编号
    */
    public function checkExist($stockdump_bn){
        $oStockdump = app::get('console')->model('stockdump');
        $stockdump = $oStockdump->dump(array('stockdump_bn'=>$stockdump_bn),'*');
       
        return $stockdump;
    }

    /**
    * 查询对应状态是否可操作
    */
    public function checkValid($stockdump_bn,$status,&$msg){
        $stockdump = $this->checkExist($stockdump_bn);
        $in_status = $stockdump['in_status'];
        $self_status = $stockdump['self_status'];
        switch($status){
            case 'PARTIN':
            case 'FINISH':
                if ($self_status=='0' || $self_status=='2'){
                    $msg = '转储单状态已取消或关闭,不可以入库';
                    return false;
                }
                break;
            case 'FAILED':
            case 'CANCEL':
            case 'CLOSE':
                if (($in_status=='1')  ){

                    $msg = '转储单状态为已入库不可以取消';
                    return false;
                }
                if ($self_status == '0'){
                    $msg = '转储单状态已取消不可以取消';
                    return false;
                }
                break;
        }
        return true;
    }
    
    /**
     * 转储单确认事务
     */
    public function confirm_stock($stockdump_bn){
        set_time_limit(0);
        ini_set('memory_limit','256M');
        $oStockdump = app::get('console')->model('stockdump');
        $ostock_items = app::get('console')->model('stockdump_items');

        //更新出入库单确认状态 增加确认人、确认时间
        $confirm_data = array(
            'confirm_type' => 2,
            'confirm_name' => kernel::single('desktop_user')->get_name(),
            'confirm_time' => time(),
        );
        
        $affect_rows = $oStockdump->update($confirm_data,array('stockdump_bn'=>$stockdump_bn));
        if (is_bool($affect_rows)) {
            return $this->fail($this->error_info['UPDATA_CONFIRM_TYPE_FAIL']);
        }

        $appro_lists = $ostock_items->getList(
            'item_id,stockdump_id,stockdump_bn,product_id,bn,num,in_nums,defective_num,appro_price',
            array('stockdump_bn'=>$stockdump_bn,'confirm_type'=>2)
        );
       
        $ioResult = $this->save_iostock($appro_lists);
       
        if ($ioResult['rsp'] == 'succ'){
            return $this->success();
        }else{
            return $ioResult;
        }
    }

    /**
     * 取消转储单
     */
    function cancel($stockdump_bn){
        $approObj = app::get('console')->model('stockdump');
        $type = $approObj->update(array('self_status'=>'0','response_time'=>time()),array('stockdump_bn'=>$stockdump_bn));
        if ($type){
            $this->clear_stockout_store_freeze($stockdump_bn);
        }
        
        return $type;
    }
}
