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
 * 经销商公共Lib方法类
 *
 * @author wangbiao@shopex.cn
 * @version 2024.04.11
 */
class dealer_business
{
    /**
     * 获取操作人员的企业组织架构ID权限
     * @todo：获取失败或者没有权限，返回-1
     *
     * @return array
     */
    public function getOperationCosIds(&$error_msg=null)
    {
        //获取操作员拥有的COS列表
        $result = kernel::single('organization_cos')->getCosList();
        $cosIds = array();
        
        //获取失败
        if(!$result[0]){
            $error_msg = '获取企业组织架失败：'. $result[1];
            $cosIds = array(-1);
            
            return [false, $cosIds];
        }
        
        //check
        if($result[1] == '_ALL_'){
            //超级管理员
        }elseif($result[1] && is_array($result[1])){
            //拥有的cos_id
            $cosIds = array_column($result[1], 'cos_id');
        }else{
            //失败返回-1
            $cosIds = array('-1');
        }
        
        return [true, $cosIds];
    }
    
    /**
     * 获取经销商列表 or 指定经销商信息
     *
     * @param $bs_id 指定返回某个经销商信息
     * @return array
     */
    public function getBusiness($bs_id=0)
    {
        $businessMdl = app::get('dealer')->model('business');
        
        $filter = array();
        if($bs_id){
            $filter['bs_id'] = $bs_id;
        }
        
        //list
        $dataList = $businessMdl->getList('*', $filter);
        
        //指定经销商信息
        if($bs_id){
            return is_array($dataList[0]) ? $dataList[0] : array();
        }else{
            return $dataList;
        }
    }
    
    /**
     * 获取指定经销商列表(包含贸易公司信息)
     *
     * @param $bs_ids
     * @return array
     */
    public function getAssignBusiness($bs_ids)
    {
        $businessMdl = app::get('dealer')->model('business');
        $betcMdl = app::get('dealer')->model('betc');
        
        //去除空值
        $bs_ids = array_filter($bs_ids);
        
        //check
        if(empty($bs_ids)){
            return array();
        }
        
        //list
        $tempList = $businessMdl->getList('*', array('bs_id'=>$bs_ids));
        if(empty($tempList)){
            return array();
        }
        
        //betc_id
       $betcIds = array_column($tempList, 'betc_id');
       $betcIds = array_filter(explode(',', implode(',', $betcIds)));
       $betcList = $betcMdl->getList('betc_id,betc_code,betc_name', array('betc_id'=>$betcIds));
       $betcList = array_column($betcList, null, 'betc_id');
        
        //format
        // $dataList = array_column($dataList, null, 'bs_id');
        $dataList = array();
        foreach ($tempList as $key => $val)
        {
            $bs_id = $val['bs_id'];
            
            //贸易公司
            $betcs = array();
            if($val['betc_id']){
                $betcIds = explode(',', $val['betc_id']);
                foreach ($betcIds as $betcKey => $betc_id)
                {
                    if(empty($betcList[$betc_id])){
                        continue;
                    }
                    
                    $betcs[$betc_id] = $betcList[$betc_id];
                }
            }
            $val['betcs'] = $betcs;
            
            $dataList[$bs_id] = $val;
        }
        
        return $dataList;
    }
    
    /**
     * 获取店铺关联的供货仓
     *
     * @param $shopBns 店铺编码集合
     * @return array
     */
    public function getShopBranchs($shopBns)
    {
        $branchMdl = app::get('ome')->model('branch');
        
        //去除空值
        $shopBns = array_filter($shopBns);
        
        //check
        if(empty($shopBns)){
            return array();
        }
        
        //仓库关联的店铺
        $shopBranchs = app::get('ome')->getConf('shop.branch.relationship');
        if(empty($shopBranchs)){
            return array();
        }
        
        //获取所有主仓库
        $branchList = $branchMdl->db->select("SELECT branch_id,branch_bn,name FROM sdb_ome_branch WHERE type='main' ORDER BY weight DESC");
        if(empty($branchList)){
            return array();
        }
        
        $branchList = array_column($branchList, null, 'branch_bn');
        
        $dataList = array();
        foreach ($shopBranchs as $shop_bn => $items)
        {
            if(!in_array($shop_bn, $shopBns)){
                continue;
            }
            
            foreach ($items as $itemKey => $branch_bn)
            {
                $dataList[$shop_bn][$branch_bn] = $branchList[$branch_bn];
            }
        }
        
        return $dataList;
    }
    
    /**
     * 获取指定授权产品线
     *
     * @param $shopIds 店铺ID集合
     * @return array
     */
    public function getAssignSeries($shopIds)
    {
        $shopMdl = app::get('ome')->model('shop');
        $seriesMdl = app::get('dealer')->model('series');
        $seriesShopMdl = app::get('dealer')->model('series_endorse');
        
        //去除空值
        $shopIds = array_filter($shopIds);
        
        //check
        if(empty($shopIds)){
            return array();
        }
        
        //list
        $tempList = $seriesShopMdl->getList('*', array('shop_id'=>$shopIds));
        if(empty($tempList)){
            return array();
        }
        
        //shop_id
        $shopIds = array_column($tempList, 'shop_id');
        $shopIds = array_filter($shopIds);
        $shopList = $shopMdl->getList('shop_id,shop_bn,name', array('shop_id'=>$shopIds));
        $shopList = array_column($shopList, null, 'shop_id');
        
        //shop_id
        $seriesIds = array_column($tempList, 'series_id');
        $seriesIds = array_filter($seriesIds);
        $seriesList = $seriesMdl->getList('series_id,series_code,series_name', array('series_id'=>$seriesIds));
        $seriesList = array_column($seriesList, null, 'series_id');
        
        //format
        $dataList = array();
        foreach ($tempList as $key => $val)
        {
            $shop_id = $val['shop_id'];
            $series_id = $val['series_id'];
            
            //merge
            $dataList[$shop_id][$series_id] = array(
                'shop_id' => $shop_id,
                'shop_bn' => $shopList[$shop_id]['shop_bn'],
                'shop_name' => $shopList[$shop_id]['name'],
                'series_id' => $series_id,
                'series_code' => $seriesList[$series_id]['series_code'],
                'series_name' => $seriesList[$series_id]['series_name'],
            );
        }
        
        //unset
        unset($tempList, $shopList, $seriesList);
        
        return $dataList;
    }
    
    /**
     * 获取指定经销商列表(包含贸易公司信息)
     *
     * @param $bs_ids
     * @return array
     */
    public function getAssignBetcs($betcIds=array())
    {
        $betcMdl = app::get('dealer')->model('betc');
        
        //filter
        $filter = array();
        if($betcIds){
            //去除空值
            $filter['betc_id'] = array_filter($betcIds);
        }
        
        //list
        $betcList = $betcMdl->getList('*', $filter);
        $betcList = array_column($betcList, null, 'betc_id');
        
        //format
        foreach ($betcList as $key => $val)
        {
            $betc_id = $val['betc_id'];
            
            //check
            if($betcIds && !in_array($betc_id, $betcIds)){
                unset($betcList[$betc_id]);
            }
        }
        
        return $betcList;
    }

    /**
     * 根据仓编码获取经销商信息
     * 
     * 逻辑流程：
     * 1. 根据branch_id去sdb_o2o_store表获取store_id和store_bn
     * 2. 根据store_bn去sdb_organization_organization表获取org_no=store_bn且org_type='2'的parent_id
     * 3. 用parent_id去sdb_organization_organization表查org_id=parent_id且org_type='3'的org_bn
     * 4. 用org_bn去sdb_dealer_business表匹配bs_bn获取经销商信息
     *
     * @param int|array $branch_id 仓编码或仓编码数组
     * @return array 经销商信息，单个查询返回经销商数组，批量查询返回以branch_id为key的数组
     */
    public function getDealerByBranchId($branch_id)
    {
        // 参数验证
        if (empty($branch_id)) {
            return array();
        }
        
        // 处理批量查询
        if (is_array($branch_id)) {
            $branch_ids = array_filter($branch_id);
            if (empty($branch_ids)) {
                return array();
            }
            $whereCondition = "os.branch_id IN (" . implode(',', $branch_ids) . ")";
        } else {
            $whereCondition = "os.branch_id = {$branch_id}";
        }

        $sql = "
            SELECT 
                os.branch_id,
                db.*
            FROM sdb_o2o_store os
            INNER JOIN sdb_organization_organization oo_store ON os.store_bn = oo_store.org_no AND oo_store.org_type = '2'
            INNER JOIN sdb_organization_organization oo_dealer ON oo_store.parent_id = oo_dealer.org_id AND oo_dealer.org_type = '3'
            INNER JOIN sdb_dealer_business db ON oo_dealer.org_no = CONCAT('BS_', db.bs_bn)
            WHERE {$whereCondition}
        ";

        $businessMdl = app::get('dealer')->model('business');
        $result = $businessMdl->db->select($sql);

        // 返回结果
        return is_array($branch_id) 
            ? array_column($result, null, 'branch_id') 
            : (!empty($result) ? $result[0] : array());

    }
    
    /**
     * 根据门店编码获取经销商信息
     * 
     * 逻辑流程：
     * 1. 根据store_bn去sdb_o2o_store表获取store_id
     * 2. 根据store_bn去sdb_organization_organization表获取org_no=store_bn且org_type='2'的parent_id
     * 3. 用parent_id去sdb_organization_organization表查org_id=parent_id且org_type='3'的org_no
     * 4. 用org_no去sdb_dealer_business表匹配bs_bn获取经销商信息
     *
     * @param string|array $store_bn 门店编码或门店编码数组
     * @return array 经销商信息，单个查询返回经销商数组，批量查询返回以store_bn为key的数组
     */
    public function getDealerByStoreBn($store_bn)
    {
        // 参数验证
        if (empty($store_bn)) {
            return array();
        }
        
        // 处理批量查询
        if (is_array($store_bn)) {
            $store_bns = array_filter($store_bn);
            if (empty($store_bns)) {
                return array();
            }
            $whereCondition = "os.store_bn IN ('" . implode("','", $store_bns) . "')";
        } else {
            $whereCondition = "os.store_bn = '{$store_bn}'";
        }

        try {
            $sql = "
                SELECT 
                    os.store_bn,
                    db.*
                FROM sdb_o2o_store os
                INNER JOIN sdb_organization_organization oo_store ON os.store_bn = oo_store.org_no AND oo_store.org_type = '2'
                INNER JOIN sdb_organization_organization oo_dealer ON oo_store.parent_id = oo_dealer.org_id AND oo_dealer.org_type = '3'
                INNER JOIN sdb_dealer_business db ON oo_dealer.org_no = CONCAT('BS_', db.bs_bn)
                WHERE {$whereCondition}
            ";

            $businessMdl = app::get('dealer')->model('business');
            $result = $businessMdl->db->select($sql);

            // 返回结果
            return is_array($store_bn) 
                ? array_column($result, null, 'store_bn') 
                : (!empty($result) ? $result[0] : array());

        } catch (Exception $e) {
            return array();
        }
    }
}
