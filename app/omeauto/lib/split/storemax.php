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
 * ============================
 * @Author:   yaokangming
 * @Version:  1.0
 * @DateTime: 2020/9/10 17:23:35
 * @describe: 按库存就全拆
 * ============================
 */
class omeauto_split_storemax extends omeauto_split_abstract
{

    #拆单规则配置获取数据
    /**
     * 获取Special
     * @return mixed 返回结果
     */

    public function getSpecial()
    {
        if($_POST['from'] == 'split') {
            return [];
        }
        return ['split_type'=>array(
                  'skucategory' => '按商品品类拆',
                  'skuweight' => '按商品重量拆',
                  'skuvolume' => '按商品体积拆',
              )];
    }

    #拆单规则保存前处理
    /**
     * preSaveSdf
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */
    public function preSaveSdf(&$sdf)
    {
        if($sdf['split_type'] != 'storemax') {
            return [true];
        }
        if($sdf['split_config']['split_type_2']) {
            list($rs, $msg) = kernel::single('omeauto_split_router', $sdf['split_config']['split_type_2'])->preSaveSdf($sdf);
            if(!$rs) {
                return [false, $msg];
            }
            if($sdf['split_config']['split_type_2'] == $sdf['split_config']['split_type_3']) {
                return [false, '第二层与第三层拆分不能一致'];
            }
        }
        if($sdf['split_config']['split_type_3']) {
            list($rs, $msg) = kernel::single('omeauto_split_router', $sdf['split_config']['split_type_3'])->preSaveSdf($sdf);
            if(!$rs) {
                return [false, $msg];
            }
        }
        return array(true, '保存成功');
    }

    #拆分订单
    /**
     * splitOrder
     * @param mixed $group group
     * @param mixed $splitConfig 配置
     * @return mixed 返回值
     */
    public function splitOrder(&$group, $splitConfig)
    {
        $startTime = microtime(true);
        $arrOrder   = $group->getOrders();
        $arrOrderId = array();
        $splitOrder = array();
        foreach ($arrOrder as $ok => $order) {
            $arrOrderId[] = $order['order_id'];
        }
        list($rs, $msg) = $this->_splitOrderByStore($arrOrder, $group, $splitConfig);
        if (!$rs) {
            return array(false, $msg);
        }
        if($splitConfig['split_type_2']) {
            list($rs, $msg) = kernel::single('omeauto_split_router', $splitConfig['split_type_2'])->splitOrderFromSplit($arrOrder, $group, $splitConfig);
            if(!$rs) {
                return [false, $msg];
            }
        }
        if($splitConfig['split_type_3']) {
            list($rs, $msg) = kernel::single('omeauto_split_router', $splitConfig['split_type_3'])->splitOrderFromSplit($arrOrder, $group, $splitConfig);
            if(!$rs) {
                return [false, $msg];
            }
        }
        $splitOrder = $arrOrder;
        if ($arrOrderId) {
            $group->setSplitOrderId($arrOrderId);
        }
        $group->updateOrderInfo($splitOrder);
        if (empty($splitOrder)) {
            return array(false, '无法拆单');
        }
        return array(true, '');
    }

    /**
     * splitOrderFromSplit
     * @param mixed $arrOrder arrOrder
     * @param mixed $group group
     * @param mixed $splitConfig 配置
     * @return mixed 返回值
     */
    public function splitOrderFromSplit(&$arrOrder, &$group, $splitConfig) {
        return $this->_splitOrderByStore($arrOrder, $group, $splitConfig);
    }

    protected function _splitOrderByStore(&$arrOrder, &$group, $splitConfig)
    {
        $startTime = microtime(true);
        $group->setConfirmBranch(true);
        $bmIdNum = array();
        foreach ($arrOrder as $k => $order) {
            foreach ($order['objects'] as $ok => $object) {
                foreach ($object['items'] as $ik => $item) {
                    if($splitConfig['from'] == 'split') {
                        $bmIdNum[$item['product_id']] += $item['nums'];
                        continue;
                    }
                    if ($item['nums'] > $item['split_num']) {
                        $arrOrder[$k]['objects'][$ok]['items'][$ik]['original_num'] = $item['nums'];
                        $arrOrder[$k]['objects'][$ok]['items'][$ik]['nums']         = $nums         = $item['nums'] - $item['split_num'];
                        if ($bmIdNum[$item['product_id']]) {
                            $bmIdNum[$item['product_id']] += $nums;
                        } else {
                            $bmIdNum[$item['product_id']] = $nums;
                        }
                    } else {
                        unset($arrOrder[$k]['objects'][$ok]['items'][$ik]);
                    }
                }
                if (empty($arrOrder[$k]['objects'][$ok]['items'])) {
                    unset($arrOrder[$k]['objects'][$ok]);
                }
            }
            if (empty($arrOrder[$k]['objects'])) {
                unset($arrOrder[$k]);
            }
        }
        if (empty($arrOrder)) {
            return array(false, '可拆单明细为空，无需拆分');
        }
        $arrBranchId = $group->getBranchId();
        $bmIds       = array_keys($bmIdNum);

        if (!is_array($arrBranchId)) {
            //手工批量审单 固定仓库
            $bi = app::get('ome')->model('branch')->db_dump($arrBranchId, 'b_type,b_status');
            $arrBranchId = array($arrBranchId);
            if ($bi['b_type'] == '2') {
                return array(false, '批量审单不支持门店');
            } else {
                $storeRows   = $this->getBranchStoreRows($arrBranchId, $bmIds);
            }
        } else {
            $tidBranchId = array();
            foreach ($arrBranchId as $k => $v) {
                //选择第一个仓库规则
                if(strpos($k, '-')) {
                    list($tmpTid) = explode('-', $k);
                } else {
                    $tmpTid = 0;
                }
                if (!isset($tid)) {
                    $tid          = $tmpTid;
                    $branchConfig = $group->getAutoBranch();
                    $group->setAutoBranch((array) $branchConfig[$tid]);
                }
                if ($tid == $tmpTid) {
                    $tidBranchId[] = $v;
                }
            }
            $storeRows = $this->getStoreRows($tidBranchId, $bmIds, $splitConfig, $arrOrder);
        }

        if (empty($storeRows)) {
            $group->setOrderStatus('*', omeauto_auto_const::__STORE_CODE);
            return array(false, '有库存的仓库不存在');
        }
        //选取sku最全的仓库
        $maxBranch  = array();
        $branchInfo = array();
        foreach ($storeRows as $val) {
            $validNum = $val['store'] > $val['store_freeze'] ? $val['store'] - $val['store_freeze'] : 0;
            if ($validNum) {
                $maxBranch[$val['branch_id']] += 1;
                $branchInfo[$val['branch_id']]['branch_id']                 = $val['branch_id'];
                $branchInfo[$val['branch_id']]['store'][$val['product_id']] = $validNum;
            }
        }
        if (empty($maxBranch)) {
            $group->setOrderStatus('*', omeauto_auto_const::__STORE_CODE);
            return array(false, '有可用库存的仓库不存在');
        }
        $rs = $this->dealStoreOrder($arrOrder, $branchInfo, $splitConfig);
        if(empty($rs['branchInfo'])) {
            $group->setOrderStatus('*', omeauto_auto_const::__STORE_CODE);
            return [false, '库存不足无法拆单'];
        }
        $canBranchId = [];
        foreach ($rs['is_split'] as $branchId => $value) {
            if(!$value) {
                $canBranchId[] = $branchId;
            }
        }
        if(!$canBranchId) {
            arsort($rs['sku_num']);
            $max = current($rs['sku_num']);
            foreach ($rs['sku_num'] as $branchId => $num) {
                if($max == $num) {
                    $canBranchId[] = $branchId;
                }
            }
        }
        if(count($canBranchId) == 1) {
            $branchId = current($canBranchId);
        } else {
            $group->updateOrderInfo($arrOrder);
            $group->setBranchId($canBranchId);
            $branchId = kernel::single('omeauto_branch_choose')->getSelectBid($tid, $group, $rs['branchInfo']);
        }
        $resultBranch = app::get('ome')->model('branch')->db_dump($branchId, 'b_type,b_status');
        if ($resultBranch['b_type'] == '2') {
            $group->setStoreBranch();
            $group->setStoreDlyType('o2o_ship');
        }
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        
        if(isset($tid)) {#有仓库规则需要重置仓库
            $group->setBranchId(array($branchId));
        }
        $arrOrder = $rs['order'][$branchId];
        
        // 记录插件性能数据
        $optimizedRs = $this->optimizeSplitDetailsForLog($rs);
        
        $details = array(
            'success' => true,
            'message' => '拆单处理完成',
            'order_bns' => implode(',', array_column($arrOrder, 'order_bn')),
            'split_type' => 'storemax',
            'split_result' => array(
                'success' => true,
                'message' => '拆单成功',
                'split_type' => 'storemax',
                'initial_branch_info' => $branchInfo,
                'split_details' => $optimizedRs,
                'candidate_branches' => $canBranchId,
                'final_branch' => $branchId
            )
        );
        
        ome_api_log::add_message('plugin', 'split', $executionTime, $details);
        
        return [true, ''];
    }

    #根据库存订单处理拆分
    protected function dealStoreOrder($arrOrder, $branchInfo, $splitConfig) {
        $return = ['branchInfo'=>[], 'order'=>[], 'sku_num'=>[], 'is_split'=>[]];
        if ($splitConfig['dimension'] == '1') {
            return $this->dealStoreOrderObject($arrOrder, $branchInfo);
        }
        foreach ($branchInfo as $branch_id => $value) {
            $tmpBI = ['branch_id'=>$branch_id, 'store'=>[]];
            $tmpSN = 0;
            $is_split = false;
            $tmpOrder = $arrOrder;
            foreach ($tmpOrder as $k => $order) {
                foreach ($order['objects'] as $ok => $object) {
                    foreach ($object['items'] as $ik => $item) {
                        if($value['store'][$item['product_id']] < $item['nums']) {
                            $num = $value['store'][$item['product_id']];
                            $is_split = true;
                        } else {
                            $num = $item['nums'];
                        }
                        if($num < 1) {
                            unset($tmpOrder[$k]['objects'][$ok]['items'][$ik]);
                            continue;
                        }
                        $tmpOrder[$k]['objects'][$ok]['items'][$ik]['nums'] = $num;
                        $value['store'][$item['product_id']] -= $num;
                        $tmpBI['store'][$item['product_id']] += $num;
                        $tmpSN += $num;
                    }
                    if(empty($tmpOrder[$k]['objects'][$ok]['items'])) {
                        unset($tmpOrder[$k]['objects'][$ok]);
                    }
                }
                if(empty($tmpOrder[$k]['objects'])) {
                    unset($tmpOrder[$k]);
                }
            }
            if($tmpOrder) {
                $return['branchInfo'][$branch_id] = $tmpBI;
                $return['order'][$branch_id] = $tmpOrder;
                $return['sku_num'][$branch_id] = $tmpSN;
                $return['is_split'][$branch_id] = $is_split;
            }
        }
        return $return;
    }

    #根据订单库存拆分销售物料
    protected function dealStoreOrderObject($arrOrder, $branchInfo) {
        $return = ['branchInfo'=>[], 'order'=>[], 'sku_num'=>[], 'is_split'=>[]];
        foreach ($arrOrder as $order) {
            foreach ($order['objects'] as $object) {
                $smIds[$object['goods_id']] = $object['goods_id'];
            }
        }
        $smBc = app::get('material')->model('sales_basic_material')->getList('sm_id, bm_id, number', array('sm_id'=>$smIds));
        $smBmNumber = array();
        foreach ($smBc as $v) {
            $smBmNumber[$v['sm_id']][$v['bm_id']] = $v['number'];
        }
        foreach ($branchInfo as $branch_id => $value) {
            $tmpBI = ['branch_id'=>$branch_id, 'store'=>[]];
            $tmpSN = 0;
            $is_split = false;
            $tmpOrder = $arrOrder;
            foreach ($tmpOrder as $k => $order) {
                foreach ($order['objects'] as $ok => $object) {
                    $objNumber = null;
                    foreach ($object['items'] as $ik => $item) {
                        $unitNumber = $smBmNumber[$object['goods_id']][$item['product_id']] ? : 1;
                        if($value['store'][$item['product_id']] < $item['nums']) {
                            $num = $value['store'][$item['product_id']];
                        } else {
                            $num = $item['nums'];
                        }
                        $objTmpNum = floor($num / $unitNumber);
                        if($objTmpNum < 1) {
                            $objNumber = 0;
                            break;
                        }
                        if(!isset($objNumber) || $objNumber > $objTmpNum) {
                            $objNumber = $objTmpNum;
                        }
                    }
                    if($objNumber) {
                        foreach ($object['items'] as $ik => $item) {
                            $unitNumber = $smBmNumber[$object['goods_id']][$item['product_id']] ? : 1;
                            $num = intval($objNumber * $unitNumber);
                            if($num < $item['nums']) {
                                $is_split = true;
                            }
                            $tmpOrder[$k]['objects'][$ok]['items'][$ik]['nums'] = $num;
                            $value['store'][$item['product_id']] -= $num;
                            $tmpBI['store'][$item['product_id']] += $num;
                            $tmpSN += $num;
                        }
                    } else {
                        unset($tmpOrder[$k]['objects'][$ok]);
                        $is_split = true;
                    }
                }
                if(empty($tmpOrder[$k]['objects'])) {
                    unset($tmpOrder[$k]);
                }
            }
            if($tmpOrder) {
                $return['branchInfo'][$branch_id] = $tmpBI;
                $return['order'][$branch_id] = $tmpOrder;
                $return['sku_num'][$branch_id] = $tmpSN;
                $return['is_split'][$branch_id] = $is_split;
            }
        }
        return $return;
    }

    protected function getStoreRows($arrBranchId, $bmIds, $splitConfig, $arrOrder)
    {
        switch ($splitConfig['s_type']) {
            case '1': #先电商仓再门店仓
                $storeRows = $this->getBranchStoreRows($arrBranchId, $bmIds);
                if (empty($storeRows)) {
                    $storeRows = $this->getO2oStoreRows($bmIds, $arrOrder);
                }
                break;
            case '2': #先门店仓再电商仓
                $storeRows = $this->getO2oStoreRows($bmIds, $arrOrder);
                if (empty($storeRows)) {
                    $storeRows = $this->getBranchStoreRows($arrBranchId, $bmIds);
                }
                break;
            case '3': #同时门店仓电商仓,优先门店仓
                $branchStoreRows = $this->getBranchStoreRows($arrBranchId, $bmIds);
                $o2oStoreRows    = $this->getO2oStoreRows($bmIds, $arrOrder);
                $storeRows       = $o2oStoreRows;
                foreach ($branchStoreRows as $v) {
                    $storeRows[] = $v;
                }
                break;
            case '4': #同时电商仓门店仓,优先电商仓
                $branchStoreRows = $this->getBranchStoreRows($arrBranchId, $bmIds);
                $o2oStoreRows    = $this->getO2oStoreRows($bmIds, $arrOrder);
                $storeRows       = $branchStoreRows;
                foreach ($o2oStoreRows as $v) {
                    $storeRows[] = $v;
                }
                break;
            case '5': #仅门店仓
                $storeRows = $this->getO2oStoreRows($bmIds, $arrOrder);
                break;
            default: #仅电商仓
                $storeRows = $this->getBranchStoreRows($arrBranchId, $bmIds);
                break;
        }
        return $storeRows;
    }

    protected function getBranchStoreRows($arrBranchId, $bmIds)
    {
        /* $modelBp  = app::get('ome')->model('branch_product');
        $bpFilter = array(
            'product_id' => $bmIds,
            'branch_id'  => $arrBranchId,
            'filter_sql' => 'store>store_freeze',
        );
        $storeRows = $modelBp->getList('branch_id, product_id, store, store_freeze', $bpFilter); */
        $arrBranchId = $this->filterBranchesByStoreControl($arrBranchId);
        $storeRows = $this->batchStoreFromRedis($bmIds, $arrBranchId);
        return $this->sortBranchById($storeRows, $arrBranchId);
    }

    protected function batchStoreFromRedis($bmIds, $arrBranchId)
    {
        $storeRows = [];
        foreach ($bmIds as $product_id) {
            foreach ($arrBranchId as $branch_id) {
                $rs = ome_branch_product::storeFromRedis(['branch_id' => $branch_id, 'product_id' => $product_id]);
                if(!$rs[0]) {
                    continue;
                }
                if($rs[2]['store'] <= $rs[2]['store_freeze']) {
                    continue;
                }
                $storeRows[] = [
                    'branch_id' => $branch_id,
                    'product_id' => $product_id,
                    'store' => $rs[2]['store'],
                    'store_freeze' => $rs[2]['store_freeze'],
                ];
            }
        }
        return $storeRows;
    }

    #storeRows 根据 arrBranchId 排序
    protected function sortBranchById($storeRows, $arrBranchId)
    {
        if (empty($storeRows)) {
            return array();
        }
        $tmpStore = array();
        foreach ($storeRows as $v) {
            $index = array_search($v['branch_id'], $arrBranchId);
            if ($index !== false) {
                $tmpStore[$index][] = $v;
            }
        }
        ksort($tmpStore);
        $ttmpStore = array();
        foreach ($tmpStore as $v) {
            foreach ($v as $vv) {
                $ttmpStore[] = $vv;
            }
        }
        return $ttmpStore;
    }

    protected function getO2oStoreRows($bmIds, $arrOrder)
    {
        //如果全局不管控供货关系即门店不管控库存
        $supply_relation = app::get('o2o')->getConf('o2o.ctrl.supply.relation');
        if ($supply_relation != 'true') {
            //return array();
        }
        #启用的门店
        $branchLib  = kernel::single('ome_interface_branch');
        $branchRows = $branchLib->getList('branch_id,branch_bn', array('b_type' => 2, 'b_status' => 1, 'is_ctrl_store' => '1'), 0, -1);
        if (empty($branchRows)) {
            return array();
        }

        $refuse_stores = app::get('o2o')->model('store_refuse_analysis')->get_refuse_stores(array_column($arrOrder, 'order_id'));

        $branchIds = array();
        foreach ($branchRows as $v) {
            // 排除掉拒绝的门店
            if (in_array($v['branch_bn'], $refuse_stores)) {
                continue;
            }

            $branchIds[] = $v['branch_id'];
        }

       
        #商品库存
        $productStoreObj = app::get('ome')->model('branch_product');
        $psFilter        = array(
            'product_id'      => $bmIds,
            'branch_id'  => $branchIds,
            'filter_sql' => 'store>store_freeze',
        );
        // $psRows = $productStoreObj->getList('branch_id,product_id,store,store_freeze', $psFilter);
        $storeRows = $this->batchStoreFromRedis($bmIds, $branchIds);
        if (empty($storeRows)) {
            return array();
        }
        #门店权重排序
        $o2oStoreId     = app::get('o2o')->model('store')->getList('branch_id', array('branch_id' => $psFilter['branch_id']), 0, -1, 'priority desc');
        $arrO2oBranchId = array_map('current', $o2oStoreId);
        return $this->sortBranchById($storeRows, $arrO2oBranchId);
    }


    
    /**
     * 过滤出管控库存的仓库
     * @param array $branchIds 仓库ID数组
     * @return array 管控库存的仓库ID数组
     */
    protected function filterBranchesByStoreControl($branchIds)
    {
        if (empty($branchIds)) {
            return [];
        }
        $branchLib  = kernel::single('ome_interface_branch');
        $branchList = $branchLib->getList('branch_id', ['branch_id' => $branchIds, 'is_ctrl_store' => '1'], 0, -1);
        
        if (empty($branchList)) {
            return [];
        }
        
        // 获取满足条件的分支ID集合
        $branchList = array_column($branchList, null,'branch_id');
        
        // 循环遍历原始数组，删除不满足条件的分支ID
        foreach ($branchIds as $bkey => $branch_id) {
            if (!isset($branchList[$branch_id])) {
                unset($branchIds[$bkey]);
            }
        }
        
        return $branchIds;
    }
    
    /**
     * 优化拆单详情数据用于日志记录
     * 只记录代码中实际使用的字段，减少冗余数据
     * 
     * @param array $rs 原始拆单结果数据
     * @return array 优化后的拆单结果数据
     */
    private function optimizeSplitDetailsForLog($rs) {
        $optimizedRs = array();
        
        // 优化 order 信息，只记录实际使用的字段
        if (isset($rs['order'])) {
            $optimizedOrder = array();
            foreach ($rs['order'] as $branchId => $orders) {
                $optimizedOrder[$branchId] = array();
                foreach ($orders as $order) {
                    $optimizedOrderData = array(
                        'order_id' => $order['order_id'],
                        'order_bn' => $order['order_bn']
                    );
                    
                    // 只记录代码中实际使用的 item 字段
                    if (isset($order['objects'])) {
                        $optimizedOrderData['items'] = array();
                        foreach ($order['objects'] as $object) {
                            if (isset($object['items'])) {
                                foreach ($object['items'] as $item) {
                                    $optimizedOrderData['items'][] = array(
                                        'product_id' => $item['product_id'], // 用于库存计算
                                        'nums' => $item['nums'] ?? 0,        // 用于数量计算
                                        'split_num' => $item['split_num'] ?? 0, // 用于拆单逻辑
                                        'original_num' => $item['original_num'] ?? 0 // 用于记录原始数量
                                    );
                                }
                            }
                        }
                    }
                    
                    $optimizedOrder[$branchId][] = $optimizedOrderData;
                }
            }
            $optimizedRs['order'] = $optimizedOrder;
        }
        
        // 优化 branchInfo 信息，只保留实际使用的字段
        if (isset($rs['branchInfo'])) {
            $optimizedBranchInfo = array();
            foreach ($rs['branchInfo'] as $branchId => $branchData) {
                $optimizedBranchInfo[$branchId] = array(
                    'branch_id' => $branchData['branch_id'],
                    'store' => $branchData['store'] // 用于库存计算，保留完整数据
                );
            }
            $optimizedRs['branchInfo'] = $optimizedBranchInfo;
        }
        
        // 保留 sku_num 和 is_split 信息（代码中直接使用）
        if (isset($rs['sku_num'])) {
            $optimizedRs['sku_num'] = $rs['sku_num'];
        }
        
        if (isset($rs['is_split'])) {
            $optimizedRs['is_split'] = $rs['is_split'];
        }
        
        return $optimizedRs;
    }
}
