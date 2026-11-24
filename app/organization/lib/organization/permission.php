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
 * 组织架构权限继承服务类
 * 实现权限继承、展开、合并等核心逻辑
 */
class organization_organization_permission {
    
    /**
     * 根据组织权限展开获取所有相关的门店branch_id
     * @param int $op_id 操作员ID
     * @param string $type 类型：online(仓库), offline(门店), 默认全部
     * @return array branch_id数组
     */

    public function expandUserBranchIds($op_id, $type = null) {
        if (empty($op_id)) {
            return [];
        }
        
        // 1. 获取用户的组织权限
        $orgOpsMdl = app::get('organization')->model('organization_ops');
        $userOrgs = $orgOpsMdl->getUserOrganizations($op_id);
        
        // 如果用户没有组织权限，返回所有门店仓的branch_id
        if (empty($userOrgs)) {
            return $this->getAllBranchIds($type);
        }
        
        $branchIds = [];
        $orgMdl = app::get('organization')->model('organization');
        
        foreach ($userOrgs as $orgId) {
            if (empty($orgId)) continue;
            
            // 获取组织信息
            $orgInfo = $orgMdl->dump(['org_id' => $orgId], 'org_type,org_no,parent_id');
            if (!$orgInfo) continue;
            
            if ($orgInfo['org_type'] == 3) { // 经销商
                // 获取经销商下的所有门店
                $dealerStoreIds = $this->getStoresByDealer($orgId, $type);
                $branchIds = array_merge($branchIds, $dealerStoreIds);
                
            } elseif ($orgInfo['org_type'] == 2) { // 门店
                // 直接获取门店对应的branch_id
                $branchId = $this->getBranchIdByStoreOrg($orgInfo['org_no'], $type);
                if ($branchId) {
                    $branchIds[] = $branchId;
                }
            }
        }
        
        return array_unique(array_filter($branchIds));
    }
    
    /**
     * 获取所有门店仓的branch_id
     * @param string $type 类型过滤：online(仓库), offline(门店), 默认offline
     * @return array branch_id数组
     */
    private function getAllBranchIds($type = 'offline') {
        $branchMdl = app::get('ome')->model('branch');
        $filter = ['check_permission' => 'false'];
        
        // 添加类型过滤
        if ($type === 'online') {
            $filter['b_type'] = '1';
        } elseif ($type === 'offline') {
            $filter['b_type'] = '2';
        }
        
        $branchList = $branchMdl->getList('branch_id', $filter, 0, -1);
        
        return $branchList ? array_column($branchList, 'branch_id') : [];
    }
    
    /**
     * 获取经销商下的所有门店branch_id
     * @param int $dealerOrgId 经销商组织ID
     * @param string $type 类型过滤
     * @return array branch_id数组
     */
    private function getStoresByDealer($dealerOrgId, $type = null) {
        $orgMdl = app::get('organization')->model('organization');
        $branchIds = [];
        
        // 查找经销商下的所有门店组织 (parent_id = dealerOrgId, org_type = 2)
        $storeOrgs = $orgMdl->getList('org_id,org_no', [
            'parent_id' => $dealerOrgId,
            'org_type' => 2
        ], 0, -1);
        
        if (!$storeOrgs) {
            return [];
        }
        
        foreach ($storeOrgs as $storeOrg) {
            $branchId = $this->getBranchIdByStoreOrg($storeOrg['org_no'], $type);
            if ($branchId) {
                $branchIds[] = $branchId;
            }
        }
        
        return $branchIds;
    }
    
    /**
     * 根据门店组织编码获取对应的branch_id
     * @param string $storeOrgNo 门店组织编码
     * @param string $type 类型过滤
     * @return int|null branch_id
     */
    private function getBranchIdByStoreOrg($storeOrgNo, $type = null) {
        if (!app::get('o2o')->is_installed() || empty($storeOrgNo)) {
            return null;
        }
        
        // 通过store_bn获取store_id
        $storeMdl = app::get('o2o')->model('store');
        $storeInfo = $storeMdl->dump(['store_bn' => $storeOrgNo], 'store_id');
        
        if (!$storeInfo) {
            return null;
        }
        
        // 通过store_id获取branch_id
        $branchMdl = app::get('ome')->model('branch');
        $filter = ['store_id' => $storeInfo['store_id'], 'check_permission' => 'false'];
        
        // 添加类型过滤
        if ($type === 'online') {
            $filter['b_type'] = '1';
        } elseif ($type === 'offline') {
            $filter['b_type'] = '2';
        }
        
        $branchInfo = $branchMdl->dump($filter, 'branch_id');
        
        return $branchInfo ? $branchInfo['branch_id'] : null;
    }
    
    /**
     * 合并直接权限和继承权限
     * @param int $op_id 操作员ID
     * @param string $type 类型
     * @return array 合并后的branch_id数组
     */
    public function getMergedBranchIds($op_id, $type = null) {
        if (empty($op_id)) {
            return [];
        }
        
        // 1. 获取直接的门店权限（从branch_ops）
        $directBranchIds = $this->getDirectBranchIds($op_id, $type);
        
        // 2. 获取继承的组织权限
        $inheritedBranchIds = $this->expandUserBranchIds($op_id, $type);
        
        // 3. 合并权限
        $allBranchIds = array_unique(array_merge($directBranchIds, $inheritedBranchIds));
        
        return array_values(array_filter($allBranchIds));
    }
    
    /**
     * 获取用户的直接门店权限（原有逻辑）
     * @param int $op_id 操作员ID
     * @param string $type 类型
     * @return array branch_id数组
     */
    private function getDirectBranchIds($op_id, $type = null) {
        $oBops = app::get('ome')->model('branch_ops');
        
        $filter = ['op_id' => $op_id];
        
        if ($type === 'online') {
            $filter['b_type'] = 1;
        } elseif ($type === 'offline') {
            $filter['b_type'] = 2;
        }
        
        $bops_list = $oBops->getList('branch_id', $filter, 0, -1);
        
        $branchIds = [];
        if ($bops_list) {
            foreach ($bops_list as $v) {
                $branchIds[] = $v['branch_id'];
            }
        }
        
        return $branchIds;
    }
    
    /**
     * 当新门店添加到经销商时，自动继承权限
     * @param int $dealerOrgId 经销商组织ID
     * @param int $newStoreOrgId 新门店组织ID
     * @return bool 操作结果
     */
    public function autoInheritPermissionForNewStore($dealerOrgId, $newStoreOrgId) {
        if (empty($dealerOrgId) || empty($newStoreOrgId)) {
            return false;
        }
        
        $orgOpsMdl = app::get('organization')->model('organization_ops');
        
        // 获取拥有经销商权限的所有操作员
        $adminList = $orgOpsMdl->getOperatorsByOrganization($dealerOrgId);
        
        $updateCount = 0;
        foreach ($adminList as $admin) {
            // 为每个拥有经销商权限的管理员添加新门店权限
            if ($orgOpsMdl->addOrganizationToUser($admin['op_id'], $newStoreOrgId)) {
                $updateCount++;
                
                // 同时更新branch_ops表以保持一致性
                $this->syncBranchOpsFromOrganization($admin['op_id']);
            }
        }
        
        return $updateCount > 0;
    }
    
    /**
     * 同步组织权限到branch_ops表
     * @param int $op_id 操作员ID
     * @return bool 操作结果
     */
    public function syncBranchOpsFromOrganization($op_id) {
        if (empty($op_id)) {
            return false;
        }
        
        // 获取所有继承的branch_id
        $inheritedBranchIds = $this->expandUserBranchIds($op_id);
        
        if (empty($inheritedBranchIds)) {
            return true;
        }
        
        $branchOpsMdl = app::get('ome')->model('branch_ops');
        
        // 为每个继承的branch_id添加到branch_ops（如果不存在）
        foreach ($inheritedBranchIds as $branchId) {
            $exists = $branchOpsMdl->dump([
                'op_id' => $op_id,
                'branch_id' => $branchId
            ], 'op_id');
            
            if (!$exists) {
                $branchOpsMdl->save([
                    'op_id' => $op_id,
                    'branch_id' => $branchId
                ]);
            }
        }
        
        return true;
    }
    
    /**
     * 按操作员门店组织权限展开可访问的 store_id（fail-closed）
     * 支持上级组织 / 经销商 / 门店节点；未配置组织权限时返回空数组，不返回全部门店。
     *
     * @param int $op_id 操作员ID
     * @return array store_id 列表
     */
    public function getStoreIdsByOperator($op_id)
    {
        if (empty($op_id)) {
            return [];
        }

        if (!app::get('organization')->is_installed() || !app::get('o2o')->is_installed()) {
            return [];
        }

        $userOrgs = app::get('organization')->model('organization_ops')->getUserOrganizations($op_id);
        $userOrgs = array_values(array_filter(array_map('intval', (array) $userOrgs)));
        if (empty($userOrgs)) {
            return [];
        }

        $orgMdl = app::get('organization')->model('organization');
        $allOrgs = $orgMdl->getList('org_id,parent_id,org_type,org_no', ['del_mark' => 0], 0, -1);
        if (empty($allOrgs)) {
            return [];
        }

        $orgMap = [];
        $childrenMap = [];
        foreach ($allOrgs as $org) {
            $orgId = (int) $org['org_id'];
            $orgMap[$orgId] = $org;
            $parentId = (int) $org['parent_id'];
            if (!isset($childrenMap[$parentId])) {
                $childrenMap[$parentId] = [];
            }
            $childrenMap[$parentId][] = $orgId;
        }

        $storeBnList = [];
        $visited = [];
        $queue = $userOrgs;
        while (!empty($queue)) {
            $orgId = (int) array_shift($queue);
            if ($orgId < 1 || isset($visited[$orgId])) {
                continue;
            }
            $visited[$orgId] = true;

            if (!isset($orgMap[$orgId])) {
                continue;
            }

            $org = $orgMap[$orgId];
            if ((int) $org['org_type'] === 2 && $org['org_no'] !== '' && $org['org_no'] !== null) {
                $storeBnList[] = $org['org_no'];
            }

            if (!empty($childrenMap[$orgId])) {
                foreach ($childrenMap[$orgId] as $childId) {
                    if (!isset($visited[$childId])) {
                        $queue[] = $childId;
                    }
                }
            }
        }

        $storeBnList = array_values(array_unique(array_filter($storeBnList)));
        if (empty($storeBnList)) {
            return [];
        }

        $stores = app::get('o2o')->model('store')->getList('store_id', ['store_bn' => $storeBnList], 0, -1);
        if (empty($stores)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', array_column($stores, 'store_id')))));
    }

    /**
     * 组织树可见 org_id（fail-closed）
     * 已选组织 + 全部下级 + 祖先路径；未配置组织权限时返回空数组。
     *
     * @param int $op_id 操作员ID
     * @return array org_id 列表
     */
    public function getVisibleOrgIdsByOperator($op_id)
    {
        if (empty($op_id)) {
            return [];
        }

        if (!app::get('organization')->is_installed()) {
            return [];
        }

        $userOrgs = app::get('organization')->model('organization_ops')->getUserOrganizations($op_id);
        $userOrgs = array_values(array_filter(array_map('intval', (array) $userOrgs)));
        if (empty($userOrgs)) {
            return [];
        }

        $orgMdl = app::get('organization')->model('organization');
        $allOrgs = $orgMdl->getList('org_id,parent_id', ['del_mark' => 0], 0, -1);
        if (empty($allOrgs)) {
            return [];
        }

        $orgMap = [];
        $childrenMap = [];
        foreach ($allOrgs as $org) {
            $orgId = (int) $org['org_id'];
            $orgMap[$orgId] = $org;
            $parentId = (int) $org['parent_id'];
            if (!isset($childrenMap[$parentId])) {
                $childrenMap[$parentId] = [];
            }
            $childrenMap[$parentId][] = $orgId;
        }

        $visible = [];
        $visited = [];
        $queue = $userOrgs;
        while (!empty($queue)) {
            $orgId = (int) array_shift($queue);
            if ($orgId < 1 || isset($visited[$orgId])) {
                continue;
            }
            $visited[$orgId] = true;

            if (!isset($orgMap[$orgId])) {
                continue;
            }

            $visible[$orgId] = true;

            if (!empty($childrenMap[$orgId])) {
                foreach ($childrenMap[$orgId] as $childId) {
                    if (!isset($visited[$childId])) {
                        $queue[] = $childId;
                    }
                }
            }
        }

        // 祖先路径：保证深层授权时顶级树仍可展开到有权节点
        foreach ($userOrgs as $selectedId) {
            $currentId = (int) $selectedId;
            $guard = 0;
            while ($currentId > 0 && isset($orgMap[$currentId]) && $guard < 100) {
                $visible[$currentId] = true;
                $currentId = (int) $orgMap[$currentId]['parent_id'];
                $guard++;
            }
        }

        return array_map('intval', array_keys($visible));
    }

    /**
     * 获取管理员有权限查看的所有门店完整信息
     * 支持经销商权限继承
     * @param int $op_id 管理员操作员ID
     * @return array 门店信息数组，包含store_id、store_bn、branch_id、shop_id等字段
     */
    public function getAuthorizedStoresByOperator($op_id) {
        if (empty($op_id)) return [];
        
        // 获取用户的组织权限
        $userOrgs = app::get('organization')->model('organization_ops')->getUserOrganizations($op_id);
        if (empty($userOrgs)) return [];
        
        $orgMdl = app::get('organization')->model('organization');
        
        // 批量查询所有相关组织信息
        $allOrgInfos = $orgMdl->getList('org_id,org_type,org_no,parent_id', ['org_id' => $userOrgs], 0, -1);
        if (empty($allOrgInfos)) return [];
        
        // 按org_id建立索引，方便快速查找
        $orgMap = array_column($allOrgInfos, null, 'org_id');
        
        // 收集所有需要查询的门店编码
        $storeBnList = [];
        
        foreach ($userOrgs as $orgId) {
            if (!isset($orgMap[$orgId])) continue;
            
            $orgInfo = $orgMap[$orgId];
            
            if ($orgInfo['org_type'] == 3) { // 经销商 - 收集下属门店编码
                foreach ($allOrgInfos as $childOrg) {
                    if ($childOrg['parent_id'] == $orgId && $childOrg['org_type'] == 2) {
                        $storeBnList[] = $childOrg['org_no'];
                    }
                }
            } elseif ($orgInfo['org_type'] == 2) { // 门店 - 直接收集编码
                $storeBnList[] = $orgInfo['org_no'];
            }
        }
        
        if (empty($storeBnList)) return [];
        
        // 批量查询门店信息
        $storeInfos = app::get('o2o')->model('store')->getList('store_id,store_bn,shop_id', ['store_bn' => $storeBnList], 0, -1);
        if (empty($storeInfos)) return [];
        
        // 提取所有store_id，批量查询branch信息
        $storeIds = array_column($storeInfos, 'store_id');
        $branchInfos = app::get('ome')->model('branch')->getList('store_id,branch_id', [
            'store_id' => $storeIds,
            'b_type' => '2'
        ], 0, -1);
        
        // 按store_id建立branch索引
        $branchMap = array_column($branchInfos, 'branch_id', 'store_id');
        
        // 组装最终结果
        $result = [];
        foreach ($storeInfos as $store) {
            if (isset($branchMap[$store['store_id']])) {
                $result[] = [
                    'store_id' => $store['store_id'],
                    'store_bn' => $store['store_bn'],
                    'branch_id' => $branchMap[$store['store_id']],
                    'shop_id' => $store['shop_id']
                ];
            }
        }
        
        return $result;
    }
    
    /**
     * 根据操作员ID获取有权限的经销商ID列表
     * @param int $op_id 操作员ID
     * @return array 经销商ID数组
     */
    public function getDealerIdsByOperator($op_id) {
        if (empty($op_id)) {
            return [];
        }
        
        // 获取用户的组织权限
        $orgOpsMdl = app::get('organization')->model('organization_ops');
        $userOrgs = $orgOpsMdl->getUserOrganizations($op_id);
        
        if (empty($userOrgs)) {
            return [];
        }
        
        // 一次性查询所有组织信息，按org_type分类处理
        $orgMdl = app::get('organization')->model('organization');
        $allOrgs = $orgMdl->getList('org_id,org_type,org_no,parent_id', [
            'org_id' => $userOrgs
        ], 0, -1);
        
        if (empty($allOrgs)) {
            return [];
        }
        
        // 收集所有需要的 parent_id 和 org_id
        $needQueryIds = [];
        $dealerOrgNos = [];
        
        foreach ($allOrgs as $org) {
            switch ($org['org_type']) {
                case 1: // 组织层
                    // 收集需要查询的 org_id
                    $needQueryIds[] = $org['org_id'];
                    break;
                    
                case 3: // 经销商层
                    // 直接获取 org_no
                    $dealerOrgNos[] = $org['org_no'];
                    break;
                    
                case 2: // 门店层
                    // 收集需要查询的 parent_id
                    if (!empty($org['parent_id'])) {
                        $needQueryIds[] = $org['parent_id'];
                    }
                    break;
            }
        }
        
        // 批量查询所有需要的组织信息
        if (!empty($needQueryIds)) {
            $needQueryIds = array_unique($needQueryIds);
            
            // 批量查询：1. 组织层下的经销商 2. 门店的父级经销商
            $relatedOrgs = $orgMdl->getList('org_no', [
                'org_id' => $needQueryIds,
                'org_type' => 3
            ], 0, -1);
            
            if (!empty($relatedOrgs)) {
                foreach (array_column($relatedOrgs, 'org_no') as $orgNo) {
                    $dealerOrgNos[] = $orgNo;
                }
            }
        }
        
        // 去重
        $dealerOrgNos = array_unique(array_filter($dealerOrgNos));
        
        if (empty($dealerOrgNos)) {
            return [];
        }
        
        // 根据组织编码匹配经销商业务表，获取真正的经销商ID
        // 需要去掉BS_前缀来匹配bs_bn字段
        $dealerBusinessMdl = app::get('dealer')->model('business');
        
        // 去掉BS_前缀，获取真正的经销商编码
        $dealerBnList = [];
        foreach ($dealerOrgNos as $orgNo) {
            if (strpos($orgNo, 'BS_') === 0) {
                $dealerBnList[] = substr($orgNo, 3); // 去掉BS_前缀
            } else {
                $dealerBnList[] = $orgNo; // 兼容没有前缀的情况
            }
        }
        
        $dealerBusinessList = $dealerBusinessMdl->getList('bs_id', [
            'bs_bn' => $dealerBnList
        ], 0, -1);
        
        return array_column($dealerBusinessList, 'bs_id');
    }
    
} 
