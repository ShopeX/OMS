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

class console_stock_occupy
{
    public function add($data) {
        
        $items = $data['items'];
        $order_bn = $data['order_bn'];
        if(empty($items)) {
            return array(false, '没有商品明细');
        }

        $itemcodes = array_column($items,'itemcode');

        $shop_id = $data['shop_id'];
        $skuMdl = app::get('inventorydepth')->model('shop_skus');
        
        $shop_bnList = $skuMdl->getlist('shop_product_bn,shop_sku_id',array('shop_id'=>$shop_id,'shop_sku_id'=>$itemcodes));

        $shop_bns = array_column($shop_bnList,null,'shop_sku_id');
 
        $bns = array_column($shop_bnList,'shop_product_bn');

        $salesMaterialObj = app::get('material')->model('sales_material');
        $salesMLib = kernel::single('material_sales_material');

        $sm_info    = $salesMaterialObj->getlist('sm_id,sales_material_bn',array('sales_material_bn'=>$bns));

        $products = array_column($sm_info,null,'sales_material_bn');
        if($sm_info){
            foreach($sm_info as $sk=>$sv){
                $basicMInfos = $salesMLib->getBasicMBySalesMId($sv['sm_id']);

            }
            
        }
       
        $logs = $this->getOrderFreeze($order_bn);

        if($logs){
            return array(true, '已存在冻结记录');
        }
        $oper = kernel::single('ome_func')->getDesktopUser();
        $freezeMdl  = app::get('material')->model('basic_material_stock_artificial_freeze');
        $order_bn = $data['order_bn'];
        $oper = kernel::single('ome_func')->getDesktopUser();

        $productMdl = app::get('ome')->model('products');
        $bpMdl      = app::get('ome')->model('branch_product');
        $storeManageLib = kernel::single('ome_store_manage');
        $params['node_type'] = "artificialFreeze";
        
        foreach($items as $v){
            $itemcode = $v['itemcode'];
            
            $shop_product_bn = $shop_bns[$itemcode]['shop_product_bn'];
           
            $sm_id = $products[$shop_product_bn];
            $basicMInfos = $salesMLib->getBasicMBySalesMId($sm_id);
           
            foreach ($basicMInfos as $k => $basicMInfo){
                $freeze = array(
                    'shop_id'       => $v['shop_id'],
                
                    'bm_id'    => $basicMInfo['bm_id'],
                    'freeze_num'    => $v['num'],
                    'freeze_reason' => sprintf('[%s]订单预占', $order_bn),
                    'freeze_time'   => time(),
                    'op_id'         => $oper['op_id'],
                    'original_bn'   => $order_bn,
                    'bn'            => $basicMInfo['material_bn'],
                    'shop_product_bn'=>$itemcode,
                    'obj_type'      => 'product',
                    'original_type' => 'jdlvmiorder',
                    'branch_id'     =>  $v['branch_id'],
    
                );
    
                $rs = $freezeMdl->insert($freeze);

                if($freeze['branch_id']>0 && $freeze['bm_id']>0){
                    $freeze['obj_id']= $freeze['bmsaf_id'];
                    $storeManageLib->loadBranch(array('branch_id'=>$freeze['branch_id']));
                    $params['params'][] = $freeze;
                }

            }
                
        }
      
        $storeManageLib->processBranchStore($params,$err_msg);
        return array (true, '成功');
    }


    

    public function deleteOrderOccupy($order_bn) {
      
        $freezeMdl  = app::get('material')->model('basic_material_stock_artificial_freeze');
      
        $freeze_items = $freezeMdl->getList('*', array (
            'original_bn'   => $order_bn,
            'original_type' => 'jdlvmiorder',
            'status'        => '1',
        ));

       
        if (!$freeze_items) return array(false, '没有冻结');

        $storeManageLib = kernel::single('ome_store_manage');
        $params['node_type'] = "artificialUnfreeze";
        
        foreach ($freeze_items as $item) {
            $affect_rows =$freezeMdl->delete(array('bmsaf_id ' => $item['bmsaf_id']));
            $storeManageLib->loadBranch(array('branch_id'=>$item['branch_id']));
            $item['obj_id']= $item['bmsaf_id'];
            if ($affect_rows) {
                $params['params'][] = $item;
                
            }else{
                return array (false, '释放预占失败');
            }           
        }
        

        $storeManageLib->processBranchStore($params,$err_msg);
        return array (true, '释放预占成功');
    }

   

    public function getBranchId($warehouseno) {
        $branchs = app::get('ome')->model('branch_relation')->dump(array ('relation_branch_bn'=>$warehouseno,'type' => 'jdlvmi'));
        return $branchs;

    }

    public function getOrderFreeze($order_bn){
        $freezeObj  = app::get('material')->model('basic_material_stock_artificial_freeze');
        $freeze_list = $freezeObj->db_dump(array('original_bn'=>$order_bn,'original_type'=>'jdlvmiorder','status'=>'1'),'bmsaf_id');

        if ($freeze_list){
            return true;
        }
        return false;
    }

   
    
}