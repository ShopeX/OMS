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
 * 门店仓覆盖区域检测工具类
 * 
 * 用于检测门店仓是否开启了O2O、是否设置了覆盖区域、
 * 以及收货地址是否在覆盖区域内的工具方法
 * 
 * @author AI Assistant
 * @version 1.0
 */
class ome_store_branch_coverage
{
    /**
     * 检测门店仓是否开启了O2O
     * 
     * @param int $branch_id 仓库ID
     * @return bool 是否开启O2O
     */
    public static function isO2OEnabled($branch_id)
    {
        if (!app::get('o2o')->is_installed()) {
            return false;
        }
        
        $o2oStoreObj = app::get('o2o')->model('store');
        $o2oStores = $o2oStoreObj->getList('branch_id', array('is_o2o' => '1'));
        
        if (empty($o2oStores)) {
            return false;
        }
        
        $o2oBranchIds = array_column($o2oStores, 'branch_id');
        return in_array($branch_id, $o2oBranchIds);
    }
    
    /**
     * 检测门店仓是否设置了覆盖区域
     * 
     * @param int $branch_id 仓库ID
     * @return bool 是否设置了覆盖区域
     */
    public static function hasCoverageArea($branch_id)
    {
        $warehouseObj = app::get('logisticsmanager')->model('warehouse');
        $warehouse = $warehouseObj->dump(array('branch_id' => $branch_id, 'b_type' => 2), 'region_ids');
        
        if (empty($warehouse) || empty($warehouse['region_ids'])) {
            return false;
        }
        
        return true;
    }
    
    /**
     * 检测收货地址是否在门店仓的覆盖区域内
     * 
     * @param int $branch_id 仓库ID
     * @param string $ship_area 收货地址区域（格式：mainland:省/市/区/街道:末级ID）
     * @return bool 是否在覆盖区域内
     */
    public static function isAddressCovered($branch_id, $ship_area)
    {
        // 解析收货地址区域
        // 格式：mainland:浙江/湖州市/德清县/武康街道:3267
        $areaParts = explode(':', $ship_area);
        if (count($areaParts) < 3) {
            return false;
        }

        $shipRegionId = intval($areaParts[2]);
        
        // 第二个部分包含省市区街道信息，用/分割
        $regionNames = explode('/', $areaParts[1]);
        if (count($regionNames) < 3) {
            return false;
        }
        
        // 提取省市区名称（前三个）
        $provinceName = trim($regionNames[0]);
        $cityName = trim($regionNames[1]);
        $districtName = trim($regionNames[2]);
        
        // 获取门店仓的覆盖区域
        $warehouseObj = app::get('logisticsmanager')->model('warehouse');
        $warehouse = $warehouseObj->dump(array('branch_id' => $branch_id, 'b_type' => 2), 'region_ids');
        
        if (empty($warehouse) || empty($warehouse['region_ids'])) {
            return false;
        }
        
        // 解析覆盖区域格式：多组以分号分隔，每组为逗号分隔的层级路径
        $coverageGroups = array_filter(array_map('trim', explode(';', $warehouse['region_ids'])));
        
        foreach ($coverageGroups as $group) {
            $pathIds = array_filter(array_map('intval', explode(',', $group)));
            if (empty($pathIds)) {
                continue;
            }
            
            // 取每组路径的最后一个ID（末级区域ID）
            $lastRegionId = end($pathIds);

            // 收货地址末级 region_id 落在覆盖路径中（如 2589,2590,2595 覆盖成华区 2595）
            if ($shipRegionId > 0 && in_array($shipRegionId, $pathIds, true)) {
                return true;
            }
            
            // 查询该区域的等级和名称
            $regionModel = app::get('eccommon')->model('regions');
            $region = $regionModel->dump(array('region_id' => $lastRegionId), 'region_grade,local_name');
            
            if (empty($region)) {
                continue;
            }
            
            $regionGrade = intval($region['region_grade']);
            $regionName = trim($region['local_name']);
            
            // 根据区域等级匹配对应的收货地址区域名称
            $addressRegionName = null;
            switch ($regionGrade) {
                case 1: // 省级
                    $addressRegionName = $provinceName;
                    break;
                case 2: // 市级
                    $addressRegionName = $cityName;
                    break;
                case 3: // 区级
                    $addressRegionName = $districtName;
                    break;
                default:
                    continue 2; // 跳过不支持的等级
            }
            
            // 检查区域名称是否匹配
            if ($addressRegionName === $regionName) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 综合检测门店仓是否可用于指定收货地址
     * 
     * @param int $branch_id 仓库ID
     * @param string $ship_area 收货地址区域（格式：mainland:省/市/区/街道:末级ID）
     * @return array 检测结果
     */
    public static function checkBranchAvailability($branch_id, $ship_area)
    {
        $result = array(
            'branch_id' => $branch_id,
            'is_o2o_enabled' => false,
            'has_coverage_area' => false,
            'is_address_covered' => false,
            'is_available' => false,
            'message' => ''
        );
        
        // 检测是否开启O2O
        $result['is_o2o_enabled'] = self::isO2OEnabled($branch_id);
        if (!$result['is_o2o_enabled']) {
            $result['message'] = '门店仓未开启O2O功能';
            return $result;
        }
        
        // 检测是否设置覆盖区域
        $result['has_coverage_area'] = self::hasCoverageArea($branch_id);
        if (!$result['has_coverage_area']) {
            $result['message'] = '门店仓未设置覆盖区域';
            return $result;
        }
        
        // 检测收货地址是否在覆盖区域内
        $result['is_address_covered'] = self::isAddressCovered($branch_id, $ship_area);
        if (!$result['is_address_covered']) {
            $result['message'] = '收货地址不在门店仓覆盖区域内';
            return $result;
        }
        
        // 所有条件都满足
        $result['is_available'] = true;
        $result['message'] = '门店仓可用';
        
        return $result;
    }
    
    /**
     * 批量检测门店仓可用性
     * 
     * @param array $branch_ids 仓库ID数组
     * @param string $ship_area 收货地址区域（格式：mainland:省/市/区/街道:末级ID）
     * @return array 检测结果数组
     */
    public static function checkBranchesAvailability($branch_ids, $ship_area)
    {
        $results = array();
        
        foreach ($branch_ids as $branch_id) {
            $results[$branch_id] = self::checkBranchAvailability($branch_id, $ship_area);
        }
        
        return $results;
    }
    
    /**
     * 过滤出可用的门店仓
     * 
     * @param array $branch_ids 仓库ID数组
     * @param string $ship_area 收货地址区域（格式：mainland:省/市/区/街道:末级ID）
     * @return array 可用的仓库ID数组
     */
    public static function getAvailableBranches($branch_ids, $ship_area)
    {
        $available_branches = array();
        
        foreach ($branch_ids as $branch_id) {
            $result = self::checkBranchAvailability($branch_id, $ship_area);
            if ($result['is_available']) {
                $available_branches[] = $branch_id;
            }
        }
        
        return $available_branches;
    }
}

