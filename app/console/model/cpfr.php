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

class console_mdl_cpfr extends dbeav_model{
    
    /**
     * @param $cpfr_id
     * @param string $update_type inc 增量，all 全量
     */
   public function finishIostock($cpfr_id,$update_type = 'inc'){

        ini_set('memory_limit','1024M');
        $cpfrMdl = app::get('console')->model('cpfr');
        $cpfr = $cpfrMdl->dump(array('cpfr_id'=>$cpfr_id),'*');
        $branch_id = $cpfr['branch_id'];    
        $cpfrItemMdl = app::get('console')->model('cpfr_items');

        $cpfrItem = $cpfrItemMdl->getlist('*',array('cpfr_id'=>$cpfr_id));
        
        //手动调账备注
        $memo = ($_POST['memo'] ? str_replace(array("'",'"'), '', $_POST['memo']) : '');
        
        $iostocks = array();
        foreach($cpfrItem as $v){
            $stores = $this->getBranchStore($branch_id,$v['product_id']);
            $store = $stores['store'];
            if ($update_type == 'inc') {
                $diff_nums = $v['num'];
            }else{
                $diff_nums = $v['num']-$store;
            }
            if($diff_nums == 0){
                continue;
            }

            $type = $diff_nums < 0 ? "OUT" : "IN";
            $iostocks[$type][] = array(
                'product_id'    =>$v['product_id'],
                'bn'            =>$v['bn'],
                'branch_id'     =>$branch_id,
                'nums'          =>abs($diff_nums),
               
            );

        }

        foreach ($iostocks as $k=>$v ) {
            $iostockData = array(
                'original_bn'   =>  $cpfr['cpfr_bn'],
                'original_id'   =>  $cpfr['cpfr_id'],
                'branch_id'     =>  $branch_id,
            );  
            
            //备注信息
            if($memo){
                $iostockData['memo'] = $memo;
            }
            
            if ($k == 'IN') {
                $adjustLib = kernel::single('siso_receipt_iostock_stockin');
                $adjustLib->_typeId = 8;
            }else{
                $adjustLib = kernel::single('siso_receipt_iostock_stockout');
                $adjustLib->_typeId = 80;
            }
            $iostockData['items'] = $v;

            $adjustLib->create($iostockData, $createdata, $msg);
        }

        $updateData = array('bill_status'=>'2');

        $cpfrMdl->update($updateData,array('cpfr_id'=>$cpfr_id));
   }


    public function getBranchStore($branch_id,$product_id){
        $bpMdl = app::get('ome')->model('branch_product');
        $basicMStockFreezeLib   = kernel::single('material_basic_material_stock_freeze');
        $branchPro_info = $bpMdl->dump(array('branch_id'=>$branch_id,'product_id'=>$product_id),'store,store_freeze');

        if(empty($branchPro_info)){
            $store = 0;
            
        }
        else
        {
            //根据仓库ID、基础物料ID获取该物料仓库级的预占
            $branchPro_info['store_freeze'] = $basicMStockFreezeLib->getBranchFreeze($product_id, $branch_id);
            $store = $branchPro_info['store']-$branchPro_info['store_freeze'];
        }

        return array('store'=>$store);

    }
}
