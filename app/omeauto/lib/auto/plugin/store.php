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
 * 检查备注和旗标
 *
 * @author hzjsq@msn.com
 * @version 0.1b
 */
class omeauto_auto_plugin_store extends omeauto_auto_plugin_abstract implements omeauto_auto_plugin_interface {

    /**
     * 是否支持批量审单
     */
    protected $__SUP_REP_ROLE = true;

    /**
     * 状态码
     */
    protected $__STATE_CODE = omeauto_auto_const::__STORE_CODE;

    /**
     * 开始处理
     *
     * @param omeauto_auto_group_item $group 要处理的订单组
     * @return Array
     */
    public function process(& $group, &$confirmRoles=null) {
        $allow = true;
        $groupStore = $this->getGroupStore($group);
        $bids = $group->getBranchId();
        $isStoreBranch = $group->isStoreBranch();
        #前边已通过库存确定仓库，故不需要再验证库存
        if($group->getConfirmBranch()) {
            if(is_array($bids)) {
                $group->setBranchId(array_shift($bids));
            }
            return null;
        }
        //门店库存逻辑处理
        if (app::get('o2o')->is_installed() && $bids && $isStoreBranch) {
            //检查门店仓库存情况
            $allow = $this->checkStoreBranchStore($bids, $group, $groupStore);
            
        }elseif($bids){
            //检查电商仓库存情况
            $allow = $this->checkElectricBranchStore($bids, $group, $groupStore);
        }else{
            $allow = false;
        }
        
        if (!$allow) {

            $group->setBranchId('');

            foreach($group->getOrders() as $order) {
                $group->setOrderStatus($order['order_id'], $this->getMsgFlag());
            }
            $group->setStatus(omeauto_auto_group_item::__OPT_HOLD, $this->_getPlugName());
        }
    }
    
    /**
     * 获取订单组中的所有货品数及货品编号
     * 
     * @param omeauto_auto_group_item $group
     * @return void
     */
    private function getGroupStore($group) {
        
        $result = array('store' => array(), 'pids' => array());
        foreach($group->getOrders() as $order) {
            foreach($order['objects'] as $objects){
                foreach ($objects['items'] as $item) {
                    
                    if (in_array($item['product_id'], $result['pids'])) {
                        //已经存在
                        $result['store'][$item['product_id']] += $item['nums'];
                    } else {
                        //没有新的
                        $result['pids'][] = $item['product_id'];
                        $result['store'][$item['product_id']] = $item['nums'];
                    }
                }
            }
        }
        
        return $result;
    }

    //根据门店仓检查库存情况
    private function checkStoreBranchStore($branchIds, $group, $groupStore){
        $storeManageLib    = kernel::single('ome_store_manage');
        $branchProductObj  = app::get('o2o')->model('branch_product');
        $basicMStockFreezeLib  = kernel::single('material_basic_material_stock_freeze');
        
        //根据获取到的仓库来判断库存
        foreach ((array) $branchIds as $bid )
        {
            $storeManageLib->loadBranch(array('branch_id'=>$bid));
            $isCtrlStore    = $storeManageLib->isCtrlBranchStore();
            
            if(!$isCtrlStore){
                $group->setBranchId($bid);
                return true;
            }else{
                //检查基础物料的供货关系是否存在
                $bm_ids    = array();
                $is_flag   = true;
                
                foreach ($groupStore['pids'] as $bm_id)
                {
                   
                    
                    
                    $bm_ids[]    = $bm_id;
                }
                
                
                //找到门店供货的基础物料中需要管控库存的基础物料
                $allow    = true;
                if($bm_ids)
                {
                    //实际库存减掉冻结数再做库存判断
                    $sql      = "SELECT product_id as bm_id, store FROM sdb_ome_branch_product WHERE product_id in (".join(',', $bm_ids).") AND branch_id = ".$bid;
                    $prows    = $branchProductObj->db->select($sql);
                    
                    //转换数据格式
                    $store = array();
                    foreach ((array) $prows as $row) {
                        
                        //根据门店仓库ID、基础物料ID获取该物料仓库级的预占
                        $store_freeze    = $basicMStockFreezeLib->getO2oBranchFreeze($row['bm_id'], $bid);
                        $row['store']    = ($row['store'] < $store_freeze) ? 0 : ($row['store'] - $store_freeze);
                        
                        $store[$row['bm_id']] = $row;
                    }
                    
                    //检查订单组内的货品数量是否足够
                    foreach ($groupStore['store'] as $pid => $nums) {
                        if(in_array($pid, $bm_ids)){
                            if (($store[$pid]['store'] - $nums) < 0) {
                                $allow = false;
                            }
                        }
                    }
                }
                
                //成功
                if ($allow) {
                    $group->setBranchId($bid);
                    return true;
                }
            }
        }
        
        if (!$allow || ($is_flag === false)) {
            return false;
        }
    }

    //根据电商仓检查库存情况
    private function checkElectricBranchStore($bids, $group, $groupStore){
        //检查每个订单库存是否充足
        $branchObj = app::get('ome')->model("branch");
        $basicMStockFreezeLib  = kernel::single('material_basic_material_stock_freeze');
        $theSelectBranchId = array();
        
        // 获取指定不需要管控库存的基础物料
        $notCtrlStoreBmList = kernel::single('material_basic_material')->getNotCtrlStoreProducts($groupStore['pids']);
        
        //根据获取到的仓库来判断库存
        foreach ((array) $bids as $bkey => $bid ) {
            if(isset($tid)) {
                list($tmpTid,) = explode('-',$bkey);
                if($tid != $tmpTid) {
                    continue;
                }
            }
            $delivBranch = $branchObj->getDelivBranch($bid);
            $branchIds = array();
            $branchIds = $delivBranch[$bid]['bind_conf'];
            $branchIds[] = $bid;
            if ($bid > 0) {
                $sql = "SELECT product_id, branch_id, store FROM sdb_ome_branch_product WHERE product_id in ('".implode("','", $groupStore['pids'])."') AND branch_id IN ('".implode("','", $branchIds)."')";
                $prows = kernel::database()->select($sql);
                
                //转换数据格式
                $store = array();
                if($prows)
                {
                    $tempData    = array();
                    foreach ((array) $prows as $row) {
                        $product_id    = $row['product_id'];
                        
                        //根据仓库ID、基础物料ID获取该物料仓库级的预占
                        $store_freeze    = $basicMStockFreezeLib->getBranchFreeze($row['product_id'], $row['branch_id']);
                        $store_freeze    = abs($store_freeze);//因冻结存在负数情况，会出现a−(−b)=a+b的情况
                        $row['store']    = ($row['store'] < $store_freeze) ? 0 : ($row['store'] - $store_freeze);
                        
                        if($tempData[$product_id])
                        {
                            $tempData[$product_id]['store'] += $row['store'];
                        }
                        else 
                        {
                            $tempData[$product_id] = $row;
                        }
                    }
                    
                    $store    = $tempData;
                    unset($tempData);
                }

                //检查订单组内的货品数量是否足够
                $allow = true;
                foreach ($groupStore['store'] as $pid => $nums)
                {
                    // 检查不需要管控库存的基础物料
                    if(isset($notCtrlStoreBmList[$pid]) && $notCtrlStoreBmList[$pid]){
                        continue;
                    }
                    
                    // check store
                    if (($store[$pid]['store'] - $nums) < 0) {
                        $allow = false;
                    } 
                }
                
                if ($allow) {
                    //设置仓库
                    //$group->setBranchId($bid);
                    $theSelectBranchId[] = $bid;
                    if(!isset($tid)) {
                        // 设置仓库规则
                        $branchConfig = $group->getAutoBranch(); list($tid,) = explode('-',$bkey);
                        $group->setAutoBranch((array)$branchConfig[$tid]);
                    }
                }
            }
        }
        if($theSelectBranchId) {
            $group->setBranchId($theSelectBranchId);
            $bid = kernel::single('omeauto_branch_choose')->getSelectBid($tid,$group);
            $group->setBranchId($bid);
            return true;
        }

        return false;
    }

    /**
     * [此方法没有地方调用]获取订单中所有的产品IDS
     * 
     * @param Array $order
     * @return Array  
     */
    private function getAllProductIds($order) {

        $ids = array();
        
        //物料版需要先读取objects层数据
        foreach($order['objects'] as $objects)
        {
            foreach ($objects['items'] as $item) {
    
                $ids[] = $item['product_id'];
            }
        }

        return $ids;
    }

    /**
     * 获取该插件名称
     *
     * @param Void
     * @return String
     */
    public function getTitle() {

        return '库存不足';
    }

    /**
     * 获取提示信息
     *
     * @param Array $order 订单内容
     * @return Array
     */
    public function getAlertMsg(& $order) {

        return array('color' => '#3E3E3E', 'flag' => '库', 'msg' => '库存不足');
    }

    /**
     * 获取用于快速审核的选项页，输出HTML代码
     * 
     * @param void
     * @return String
     */
    public function getInputUI() {
        
    }
    
}