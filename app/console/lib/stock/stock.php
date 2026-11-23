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
* 库存基类
* 
* 
*/
class console_stock_stock{

    function __construct()
    {
        $this->pObj = kernel::single('console_stock');
        $this->basicMaterialStock    = kernel::single('material_basic_material_stock');
        $this->_basicMaterialLib     = kernel::single('material_basic_material');
    }

   

    /**
    * 组合预占+
    * @access public
    * @param Int $product_id 普通货品ID
    * @param Int $nums 预占数量
    * @return bool
    */
    public function chg_combine_freeze($product_id,$nums){
        if (empty($product_id) || empty($nums)) return false;
        
        return $this->basicMaterialStock->freezeBatch([['bm_id'=>$product_id,'num'=>$nums], __CLASS__.'::'.__FUNCTION__]);
    }

    /**
    * 组合预占-
    * @access public
    * @param Int $product_id 基础货品ID
    * @param Int $nums 预占数量
    * @return bool
    */
    public function chg_combine_unfreeze($product_id,$nums)
    {
        if (empty($product_id) || empty($nums)) return false;
        
        $res = $this->basicMaterialStock->unfreezeBatch([['bm_id'=>$product_id, 'num'=>$nums]], __CLASS__.'::'.__FUNCTION__);
        return $res;
    }

    /**
    * 货品实际库存+
    * @access public
    * @param Int $product_id 货品ID
    * @param Int $nums 数量
    * @return bool
    */
    public function chg_storein($product_id,$nums='')
    {
        if (empty($product_id) || empty($nums)) return false;
        
        return false;
    }

    /**
    * 货品实际库存-
    * @access public
    * @param Int $product_id 货品ID
    * @param Int $nums 数量
    * @return bool
    */
    public function chg_storeout($product_id,$nums=''){
        if (empty($product_id) || empty($nums)) return false;
        
        return false;
    }

    /**
    * 仓库冻结+
    * @access public
    * @param Int $branch_id 仓库ID
    * @param Int $product_id 货品ID
    * @param Int $nums 数量
    * @return bool
    */
    public function chg_branch_freeze($branch_id,$product_id,$nums)
    {
        $libBranchProduct    = kernel::single('ome_branch_product');
        
        if (empty($branch_id) || empty($product_id) || empty($nums)) return false;
        
        if($libBranchProduct->freez($branch_id,$product_id,$nums)){
            return true;
        }else{
            return false;
        }
    }

    /**
    * 仓库冻结-
    * @access public
    * @param Int $branch_id 仓库ID
    * @param Int $product_id 货品ID
    * @param Int $nums 数量
    * @return bool
    */
    public function chg_branch_unfreeze($branch_id,$product_id,$nums)
    {
        $libBranchProduct    = kernel::single('ome_branch_product');
        
        if (empty($branch_id) || empty($product_id) || empty($nums)) return false;
        
        if($libBranchProduct->unfreez($branch_id, $product_id, $nums)){
            return true;
        }else{
            return false;
        }
    }

    /**
    * 仓库实际库存+
    * @access public
    * @param Int $branch_id 仓库ID
    * @param Int $product_id 货品ID
    * @param Int $nums 数量
    * @return bool
    */
    public function chg_branch_storein($branch_id,$product_id,$nums=''){
        if (empty($branch_id) || empty($product_id) || empty($nums)) return false;
        
        return false;
    }

    /**
    * 仓库实际库存-
    * @access public
    * @param Int $branch_id 仓库ID
    * @param Int $product_id 货品ID
    * @param Int $nums 数量
    * @return bool
    */
    public function chg_branch_storeout($branch_id,$product_id,$nums=''){
        if (empty($branch_id) || empty($product_id) || empty($nums)) return false;
        
        return false;
    }

    /**
    * 可售库存=线上仓库的库存-销售预占-组合预占-线上仓库的仓库冻结
    * @access public
    * @param Int $product_id 货品ID
    * @return bool
    */
    public function usable_sale_store($product_id){
        if (empty($product_id)) return 0;
        
        $branch_usable_store = $this->get_usable_store($product_id,'online');
        
        //基础物料库存信息
        $p    = $this->_basicMaterialLib->getBasicMaterialStock($product_id);
        
        $usable_store = $branch_usable_store - $p['store_freeze'] ;
        
        return $usable_store;
    }

    /**
    * 可用库存=货品实际库存 - 销售预占 - 组合预占 - 仓库冻结
    * @access public
    * @param Int $product_id 货品ID
    * @return bool
    */
    public function usable_store($product_id){
        if (empty($product_id)) return 0;
        
        return 0;
    }

    /**
    * 获取组合可售库存=组合库存-组合销售预占
    * @access public
    * @param Int $product_id 货品ID
    * @return bool
    */
    public function combine_usable_sale_store($product_id){
        if (empty($product_id)) return 0;
        
        //基础物料库存信息
        $p    = $this->_basicMaterialLib->getBasicMaterialStock($product_id);
        
        $usable_store = $p['store'] - $p['store_freeze'];

        return $usable_store;
    }

    /**
    * 获取仓库可用库存=仓库实际库存 - 仓库冻结库存
    * @access public
    * @param Int $branch_id 仓库ID
    * @param Int $product_id 货品ID
    * @return bool
    */
    public function branch_usable_store($branch_id,$product_id=''){
        if (empty($branch_id) || empty($product_id)) return 0;
        
        
        $usable_store = $this->get_available_store($branch_id,$product_id);
        
        return $usable_store;
    }

    /**
     *查看单仓库中的可用库存
     *
     **/
    function get_available_store($branch_id,$product_id){
        $bpModel = app::get('ome')->model('branch_product');
        $branch = $bpModel->getList('store,store_freeze',array('product_id'=>$product_id,'branch_id'=>$branch_id),0,1);
        
        //根据仓库ID、基础物料ID获取该物料仓库级的预占
        $basicMStockFreezeLib  = kernel::single('material_basic_material_stock_freeze');
        $branch[0]['store_freeze']    = $basicMStockFreezeLib->getBranchFreeze($product_id, $branch_id);
        
        return $branch[0]['store'] - $branch[0]['store_freeze'];
    }

    /**
     * 获取货品总可用库存：所有线上仓库
     * @param Int $product_id 货品ID
     * @param Bool $attr 仓库属性,online线下 offline线下 空为所有
     **/
    function get_usable_store($product_id,$attr=''){
        if (empty($product_id)) return NULL;
    
        return $this->_usable_store($product_id,$attr);
    }

    private function _usable_store($product_id='',$attr=''){
        
        $basicMStockFreezeLib  = kernel::single('material_basic_material_stock_freeze');
        
        $filter = array('product_id'=>$product_id);
        $attr_where = '';
        if ($attr){
            $attr = $attr == 'online' ? 'true' : 'false';
            $attr_where = ' AND b.attr=\''.$attr.'\' ';
        }
        $sql = sprintf('SELECT bp.store,bp.store_freeze, bp.product_id, b.branch_id FROM sdb_ome_branch AS b,sdb_ome_branch_product AS bp WHERE b.branch_id=bp.branch_id AND bp.product_id=\'%s\' %s ',$product_id,$attr_where);
        $branch = $this->db->select($sql);
        
        $usable_store = $store = $store_freeze = 0;
        if ($branch){
            foreach ($branch as $b){
                
                //根据仓库ID、基础物料ID获取该物料仓库级的预占
                $b['store_freeze']    = $basicMStockFreezeLib->getBranchFreeze($b['product_id'], $b['branch_id']);
                
                $store += $b['store'];
                $store_freeze += $b['store_freeze'];
            }
            $usable_store = $store - $store_freeze;
        }
        return $usable_store;
    }
    
    /**
     * 可删除，没有地方调用!!!
     * 
     * 获取货品在所有仓库中的冻结数
     *
     **/
//     function total_store_freeze($product_id){
//         if(empty($product_id)) return 0;
        
//         $sql = sprintf('SELECT sum(store_freeze) AS store_freeze FROM `sdb_ome_branch_product` WHERE product_id=\'%s\'',$product_id);
//         $bp = $this->db->selectrow($sql);
        
//         $store_freeze = isset($bp['store_freeze']) ? $bp['store_freeze'] : '0';
//         return $store_freeze;
//     }

}