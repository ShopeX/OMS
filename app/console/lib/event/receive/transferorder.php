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
 *  库内转储
 */
class console_event_receive_transferorder extends console_event_response{

    /**
     * ioStorage
     * @param mixed $data 数据
     * @return mixed 返回值
     */

    public function ioStorage($data){
        $stockdumpObj = kernel::single('console_receipt_stockdump');
        $io_status = $data['status'];
        if ($data['io_source'] == 'selfwms'){#自有仓储不作处理
            return $this->send_succ();
        }
        
        $need_add = false;
        
        //验证转储单是否存在
        if(!$stockdumpObj->checkExist($data['stockdump_bn'])){
            if(!$stockdumpObj->checkExist($data['erp_stockdump_bn'])){
                $need_add = true;
            }
        }
       
        //验证转储单当前状态是否有效
        $msg = '';
        if(!$stockdumpObj->checkValid($data['stockdump_bn'],$io_status,$msg)){
           return $this->send_error($msg);
        }

        switch($io_status){
            case 'FINISH':
                // 新建转储单
                if ($need_add) {
                    if($data['erp_stockdump_bn']) {
                        $data['stockdump_bn'] = $data['erp_stockdump_bn'];
                    }
                    $rs = $this->createStockdump($data);
                    if($rs['rsp'] == 'fail') {
                        return $rs;
                    }
                }
                $stockdump_bn = $data['stockdump_bn'];
                
                return kernel::single('console_receipt_stockdump')->do_save($stockdump_bn,$data);
                break;
            case 'FAILED':
            case 'CANCEL':
            case 'CLOSE':
                $result = kernel::single('console_receipt_stockdump')->cancel($data['stockdump_bn']);
                break;
            default:
                return $this->send_succ('未定义的转储单操作');
                break;
        }
        
        if ($result){
            return $this->send_succ('转储单操作成功');
        }else{
            return $this->send_error('更新失败', '', $data);
        }
    }
    
    // 新建转储单
    function createStockdump($data){
        $libBranchProduct    = kernel::single('ome_branch_product');

        //选择商品数量判断
        $spmn = console_stock::SELECT_PRODUCT_MAX_NUM;
        if(count($data['items'])>$spmn){
            return $this->send_error('选择转储商品最大数量不能大于'.$spmn, '', $data);
        }

        // 临时兼容一下
        $data['from_branch_code'] = $data['from_branch_code']?:$data['from_warehouse_code'];
        $data['to_branch_code'] = $data['to_branch_code']?:$data['to_warehouse_code'];

        if (empty($data['from_branch_code']) || empty($data['to_branch_code'])) {
            return $this->send_error('from_branch_code or to_branch_code is not exist', '', $data);
        }
        $from_branch_id = $data['from_branch_id'] ? $data['from_branch_id'] :$this->getBranchByWmsBranchBn($data['from_branch_code']);
        if (!$from_branch_id) {
            return $this->send_error('出库仓库不存在', '', $data);
        }
        
        $to_branch_id = $data['to_branch_id'] ? $data['to_branch_id'] : $this->getBranchByWmsBranchBn($data['to_branch_code']);
        if (!$to_branch_id) {
            return $this->send_error('入库仓库不存在', '', $data);
        }

        $options = array(
            #'type' => 600,
            #'otype' => 2,
            'op_name' => '_system_',
            #'in_status' => 8,
            'from_branch_id' => $from_branch_id,
            'to_branch_id' => $to_branch_id,
            'memo' => $data['memo'],
            'use_third_party' => 1, //使用第三方的转储单号
            'from_physics_id'   => $data['from_physics_id'], 
            'to_physics_id'     => $data['to_physics_id'],
        );

        if($data['source_from']) $options['source_from'] = $data['source_from'];
        if($data['transfer_channel']) $options['transfer_channel'] = $data['transfer_channel'];
        if($data['stockdump_bn']) $options['stockdump_bn'] = $data['stockdump_bn'];
        $branchLib = kernel::single('ome_store_manage');
        $branchLib->loadBranch(array('branch_id' => $from_branch_id));
        $adata = array();
        foreach ($data['items'] as $key => $value) {
            $product_id = $this->getBasicMaterialByBn($value['bn']);

            if (!$product_id) {
                return $this->send_error('商品'.$value['bn'].'不存在', '', $data);
            }

            if (!$value['num'] || $value['num']<=0) {
                return $this->send_error($value['bn'].'调拨数量应大于0', '', $data);
            }

            if (!is_numeric($value['appro_price']) || intval($value['appro_price'])<0) {
                return $this->send_error($value['bn'].'行金额格式错误', '', $data);
            }
            
            $params = array(
                'node_type' => 'getAvailableStore',
                'params'    => array(
                    'branch_id'  => $from_branch_id,
                    'product_id' => $product_id,
                ),
            );
            
            $usable_store = $branchLib->processBranchStore($params, $err_msg);
            if($usable_store < $value['num']){
                return $this->send_error('仓库'.$value['from_branch_code'].'的'.$value['bn'].'可用库存不足', '', $data);
            }

            $adata[$key] = array(
               'product_id'=>$product_id,
               'num'=>$value['num'],
               'appro_price'=>$value['appro_price'],
               'bn'=>$value['bn'],
           );
        }

        $oStockdump = app::get('console')->model('stockdump');
        kernel::database()->beginTransaction();

        $appro_data = [];
        $approResult = $oStockdump->to_savestore($adata,$options, $appro_data);
        if(!$approResult) {
            kernel::database()->rollBack();
            
            return $this->send_error('转储失败：'.kernel::database()->errorinfo(), '', $data);
        }
        
        kernel::database()->commit();
        
        return $this->send_succ('新建成功');
    }
    
    function getBranchByWmsBranchBn($branch_bn){
        $branch_relationObj = app::get('wmsmgr')->model('branch_relation');

        $branch_relation    = $branch_relationObj->dump(array('wms_branch_bn'=>$branch_bn));
        $brBn = $branch_relation['sys_branch_bn'] ? : $branch_bn;
        $branchObj = kernel::single('console_iostockdata');
        $branch_info = $branchObj->getBranchBybn($brBn);

        return $branch_info['branch_id'];
    }
    
    function getBasicMaterialByBn($bn){
        $basicmaterialObj = app::get('material')->model('basic_material');
        $info = $basicmaterialObj->dump(array('material_bn'=>$bn));
        return $info['bm_id'];
    }
}
