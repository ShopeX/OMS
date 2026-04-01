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

class ome_entity_branch_product
{
    
    /**
     * 获取虚拟仓累计计算出来的平均成本
     * @param $branchId
     * @param $productId
     * @return array
     */
    public function getBranchCountCostPrice($branchId, $productId)
    {
        $branchMdl              = app::get('ome')->model('branch');
        $entityBranchProductMdl = app::get('ome')->model('entity_branch_product');
        if (!$branchId || !$productId) {
            return array();
        }
        
        //判断是否是主仓
        $branchInfo = $branchMdl->db_dump($branchId, 'branch_id,type,parent_id');
        if ($branchInfo['type'] != 'main' && $branchInfo['parent_id'] != 0) {
            $branchInfo = $branchMdl->db_dump(array('branch_id' => $branchInfo['parent_id']), 'branch_id');
        }
        
        $data = $entityBranchProductMdl->getList('*', array('entity_branch_id' => $branchInfo['branch_id'], 'product_id' => $productId));
        
        $branch_product = array();
        if ($data) {
            foreach ($data as $key => $value) {
                $branch_product[$branchId][$value['product_id']] = $value;
            }
        }
        return $branch_product;
    }
    
    /**
     * 计算总仓库存成本
     * @param $params
     */
    public function setBranchCountCostPrice($params,$entity_branch_product,$operator)
    {
        $entityBranchProductMdl = app::get('ome')->model('entity_branch_product');
        $branchId               = intval($params['branch_id']);
        $productId              = intval($params['product_id']);
        $branchInfo             = $this->getBranchIdS($branchId, $productId);
        if (empty($branchInfo) || empty($operator)) {
            return false;
        }
        $entity        = $entity_branch_product[$params['branch_id']][$params['product_id']];
        if ($params['type_id'] == 10) {//采购退货
            $inventoryCost = $params['iostock_price'] * $params['nums']; //出入库成本
        }else{
            $inventoryCost = $entity['unit_cost'] * $params['nums']; //出入库成本
        }
        
        if (empty($entity_branch_product)) {
            if(in_array($params['bill_type'],['workorder'])){
                $entityUnitCost = $params['unit_cost'];
            } else {
                $entityUnitCost = $params['iostock_price'];
            }
            $store = $params['nums'];
            $inventoryCost = $entityInventoryCost = $entityUnitCost * $params['nums']; //出入库成本
            $entity_branch_product_sql = "INSERT INTO `sdb_ome_entity_branch_product`
                    (`entity_branch_id`, `product_id`, `store`, `last_modified`, `unit_cost`, `inventory_cost`) VALUES
                    (".$branchInfo['branch_id'].", ".$productId.", ".$params['nums'].", ".time().", ".$entityUnitCost.", ".$inventoryCost.")";
        }else{
            switch ($operator) {
                case "+": //入库
                    $store               = $params['nums'];
                    $inventoryCost = $params['iostock_price'] * $params['nums']; //出入库成本
                    if ($params['type_id'] == 30 || $params['type_id']  == 31  || $params['type_id'] == 32 || $params['type_id'] == 60 || $params['type_id']  == 8) {//退货入库/换货入库/拒收退货入库/盘盈/调账
                        $inventoryCost = $entity['unit_cost'] * $params['nums']; //出入库成本
                    }
                    //调账成本
                    if(in_array($params['bill_type'],array('branchadjust','storeadjust','branchadjust_init','storeadjust_init'))){
                        $inventoryCost = $entity['unit_cost'] * $params['nums']; //出入库成本
                    }
                    if(in_array($params['bill_type'],['workorder'])){
                        $inventoryCost = $params['unit_cost'] * $params['nums'];
                    }
                    $entity_branch_product_sql = " UPDATE sdb_ome_entity_branch_product set
                            inventory_cost = IF( (inventory_cost+$inventoryCost)>0 , inventory_cost+$inventoryCost ,0 ),
                            unit_cost = IF( ROUND(inventory_cost/(store+$store),3)>0,ROUND(inventory_cost/(store+$store),3),0 ),
                            store = IF( (store+$store)>0 , store+$store ,0 )
                        where entity_branch_id=".$branchInfo['branch_id']." and product_id=".$productId;
                    
                    $entityInventoryCost = ($entity['inventory_cost'] + $inventoryCost) > 0 ? $entity['inventory_cost'] + $inventoryCost : 0;
                    $now_store = $entity['store'] + $store;
                    break;
                case "-"://出库
                    $store               = $params['nums'];
                    $now_store = $entity['store'] - $store;
                    if ($now_store == 0) {
                        $inventoryCost = $entity['inventory_cost'];
                    }
                    $entity_branch_product_sql = " UPDATE sdb_ome_entity_branch_product set
                            inventory_cost = IF( (inventory_cost-$inventoryCost)>0 , inventory_cost-$inventoryCost ,0 ),
                            unit_cost = IF( (store-$store)>0 , IF( ROUND(inventory_cost/(store-$store),3)>0,ROUND(inventory_cost/(store-$store),3),0 ), 0),
                            store = IF( (store-$store)>0 , store-$store ,0 )
                        where entity_branch_id=".$branchInfo['branch_id']." and product_id=".$productId;
                    
                    $entityInventoryCost = ($entity['inventory_cost'] - $inventoryCost) > 0 ? $entity['inventory_cost'] - $inventoryCost : 0;
            }
            $entityUnitCost = $now_store > 0 ? (round($entityInventoryCost / $now_store, 3) > 0 ? round($entityInventoryCost / $now_store, 3) : 0) : 0;
        }
        
        $entityBranchProductMdl->db->exec($entity_branch_product_sql);
        
        if ($operator == '-' && $now_store == 0) {
            return ['store' => $now_store, 'unit_cost' => $entity['unit_cost'], 'inventory_cost' => $entity['inventory_cost']];
        }else{
            return ['store' => $store, 'unit_cost' => $entityUnitCost, 'inventory_cost' => $entityInventoryCost];
        }

    }
    
    /**
     * 获取BranchRecordSerialize
     * @param mixed $branchId ID
     * @param mixed $productId ID
     * @return mixed 返回结果
     */
    public function getBranchRecordSerialize($branchId, $productId)
    {
        $branchProductMdl = app::get('ome')->model('branch_product');
        
        $branchInfo = $this->getBranchIdS($branchId, $productId);
        if (!$branchInfo) {
            return;
        }
        
        $data = $branchProductMdl->getList('branch_id,store,unit_cost,inventory_cost', array('branch_id' => $branchInfo['branch_ids'], 'product_id' => $productId));
    
        $costSetting = kernel::single('tgstockcost_system_setting')->getCostSetting();
        return Serialize(['branch_list'=>$data,'cost_config'=>$costSetting]);
    }
    
    /**
     * 获取关联仓库ID
     * @param $branchId
     * @param $productId
     * @return array
     */
    public function getBranchIdS($branchId, $productId)
    {
        $branchMdl = app::get('ome')->model('branch');
        
        if (!$branchId|| is_array($branchId) || !$productId || !is_int($productId)) {
            return array();
        }
        //判断是否是主仓
        $branchInfo = $branchMdl->db_dump(['branch_id' => $branchId, 'check_permission' => 'false'], 'branch_id,parent_id,type');
        if ($branchInfo['type'] != 'main' && $branchInfo['parent_id'] != 0) {
            $branchInfo      = $branchMdl->db_dump($branchInfo['parent_id'], 'branch_id,parent_id,type');
            $branchChildInfo = $branchMdl->getList('branch_id', array('parent_id' => $branchInfo['branch_id']));
        } else {
            $branchChildInfo = $branchMdl->getList('branch_id', array('parent_id' => $branchInfo['branch_id']));
        }
        $branchIds = array($branchInfo['branch_id']);
        if ($branchChildInfo) {
            $branchIds = array_merge($branchIds, array_column($branchChildInfo, 'branch_id'));
        }
        return ['branch_id' => $branchInfo['branch_id'], 'branch_ids' => $branchIds];
    }
}