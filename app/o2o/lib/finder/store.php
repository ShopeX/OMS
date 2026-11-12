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

class o2o_finder_store{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


    function __construct(){
        if(in_array($_REQUEST['action'], ['exportcnf', 'to_export', 'export'])){
            unset($this->column_edit);
        }
    }

    var $addon_cols = "store_id";
    var $column_edit = "操作";
    var $column_edit_width = 170;
    var $column_edit_order = 1;
    function column_edit($row){
        $finder_id = $_GET['_finder']['finder_id'];
        $store_id = $row['store_id'];
        $button = sprintf('<a href="index.php?app=o2o&ctl=admin_store&act=edit&p[0]=%s&finder_id=%s" target="dialog::{width:660,height:480,title:\'编辑门店\'}">编辑</a>', $store_id, $finder_id);
       
         
        $button .=' '.sprintf('<a href="index.php?app=o2o&ctl=admin_store&act=position&p[0]=%s&finder_id=%s" target="dialog::{width:660,height:480,title:\'新增仓位\'}">新增仓位</a>', $store_id, $finder_id);
        
        // 添加覆盖区域按钮
        $button .=' '.sprintf('<a href="index.php?app=o2o&ctl=admin_store&act=storeRegion&p[0]=%s&finder_id=%s" target="dialog::{width:800,height:600,title:\'门店覆盖区域\'}">覆盖区域</a>', $store_id, $finder_id);
        
        if (kernel::single('desktop_user')->has_permission('o2o_ctrl_store')) {
            $button .=' '.sprintf('<a href="index.php?app=o2o&ctl=admin_store&act=displayCtrlStore&p[0]=%s&finder_id=%s" target="dialog::{width:660,height:300,title:\'设置\'}">设置</a>', $store_id, $finder_id);
        }
        
        return $button;
    }

    public $detail_position = '库位信息';
    /**
     * detail_position
     * @param mixed $store_id ID
     * @return mixed 返回值
     */
    public function detail_position($store_id)
    {

        $branchMdl = app::get('ome')->model('branch');

        $branchs = $branchMdl->getlist('*', array('store_id' => $store_id, 'check_permission' => 'false'));

        $render = app::get('o2o')->render();

        $storeLib = kernel::single('o2o_store');

        $branch_types = $storeLib->getBranchType();

        foreach ($branchs as &$v) {
            $v['branch_type_text'] = $branch_types[$v['type']]['text'];
        }
        $render->pagedata['branchs'] = $branchs;
        
        return $render->fetch('admin/system/detail_position.html');
    }


    public $detail_log = '操作日志';
    /**
     * detail_log
     * @param mixed $store_id ID
     * @return mixed 返回值
     */
    public function detail_log($store_id)
    {
        $render = app::get('o2o')->render();

        $logObj  = app::get('ome')->model('operation_log');
        $logData = $logObj->read_log(array('obj_id' => $store_id, 'obj_type' => 'store@o2o'));
        $finder_id = $_GET['_finder']['finder_id'];

        foreach ($logData as $k => $v) {
            $logData[$k]['operate_time'] = date('Y-m-d H:i:s', $v['operate_time']);
            if ($v['memo'] == '编辑门店') {
                $logData[$k]['memo'] = "<a href='index.php?app=o2o&ctl=admin_store&act=show_history&p[0]={$v['log_id']}&p[1]={$store_id}&finder_id={$finder_id}' onclick=\"window.open(this.href, '_blank', 'width=700,height=800'); return false;\">查看快照</a>";
            }
        }

        $render->pagedata['datalist'] = $logData;

        return $render->fetch('admin/store/detail_log.html');
    }
    
    public $column_region_coverage = '门店覆盖范围';
    public $column_region_coverage_width = '150';
    public $column_region_coverage_order = 5;
    
    /**
     * column_region_coverage
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_region_coverage($row, $list)
    {
        if (empty($row['store_bn'])) {
            return '';
        }
        
        static $coverageCache;
        
        if (!isset($coverageCache)) {
            $coverageCache = array();
            
            // 从传入的list参数获取当前页面的所有store_bn
            $storeBns = array_unique(array_column($list, 'store_bn'));
            
            if (!empty($storeBns)) {
                // 批量查询门店的覆盖范围
                $warehouseMdl = app::get('logisticsmanager')->model('warehouse');
                $warehouses = $warehouseMdl->getList('branch_bn,region_names,region_ids', array(
                    'branch_bn' => $storeBns, 
                    'b_type' => 2
                ));
                
                // 汇总所有region_id，只查询一次regions表
                $allRegionIds = array();
                $warehouseRegionMap = array(); // 存储每个warehouse对应的region_ids
                
                foreach ($warehouses as $warehouse) {
                    $regionIds = $warehouse['region_ids'];
                    
                    // 如果没有region_ids，跳过
                    if (empty($regionIds)) {
                        continue;
                    }

                    // 如果region_names包含"中国"，直接标记
                    if (strpos($warehouse['region_names'], '中国') !== false) {
                        $coverageCache[$warehouse['branch_bn']] = '中国';
                        continue;
                    }

                    $warehouseRegionMap[$warehouse['branch_bn']] = $regionIds;

                    // 收集region_id用于批量查询
                    $regionGroups = explode(';', $regionIds);
                    foreach ($regionGroups as $group) {
                        if (empty(trim($group))) continue;
                        $ids = explode(',', $group);
                        $ids = array_filter(array_map('trim', $ids));
                        $allRegionIds = array_merge($allRegionIds, $ids);
                    }
                }
                
                // 批量查询所有region_id对应的地区名称
                $regionNameMap = array();
                if (!empty($allRegionIds)) {
                    $regionMdl = app::get('eccommon')->model('regions');
                    $regions = $regionMdl->getList('region_id,local_name', array('region_id' => array_unique($allRegionIds)));
                    $regionNameMap = array_column($regions, 'local_name', 'region_id');
                }
                
                // 处理每个warehouse的覆盖范围
                foreach ($warehouseRegionMap as $storeBn => $regionIds) {
                    $coverageText = $this->buildRegionCoverageText($regionIds, $regionNameMap);
                    if ($coverageText) {
                        $coverageCache[$storeBn] = $coverageText;
                    }
                }
            }
        }
        
        $store_bn = $row['store_bn'];
        if (isset($coverageCache[$store_bn])) {
            // 将分号分隔的组转换为独立的span元素
            $regionGroups = explode(';', $coverageCache[$store_bn]);
            $spanElements = array();
            
            foreach ($regionGroups as $group) {
                $group = trim($group);
                if (!empty($group)) {
                    $spanElements[] = '<span style="white-space: nowrap;margin: 2px;padding: 2px 4px;border-radius: 3px;background-color: #e8e8e8;display: inline-block;">' . htmlspecialchars($group) . '</span>';
                }
            }
            
            return '<div style="overflow: auto;word-break: break-word;white-space: normal;width: 100%;flex-wrap: wrap;display: flex;">' . implode('', $spanElements) . '</div>';
        } else {
            return '';
        }
    }

    /**
     * 构建地区覆盖范围文本
     * @param string $regionIds 地区ID字符串，格式：1,2,3;4,5,6
     * @param array $regionNameMap 地区ID到名称的映射
     * @return string 处理后的地区名称字符串
     */
    private function buildRegionCoverageText($regionIds, $regionNameMap)
    {
        // 按分号分割不同的地区组
        $regionGroups = explode(';', $regionIds);
        $resultGroups = array();
        
        foreach ($regionGroups as $group) {
            if (empty(trim($group))) {
                continue;
            }
            
            // 按逗号分割同一组内的地区ID
            $regionIdArray = explode(',', $group);
            $regionIdArray = array_filter(array_map('trim', $regionIdArray));
            
            if (empty($regionIdArray)) {
                continue;
            }
            
            // 从映射中获取地区名称
            $regionNames = array();
            foreach ($regionIdArray as $regionId) {
                if (isset($regionNameMap[$regionId])) {
                    $regionNames[] = $regionNameMap[$regionId];
                }
            }
            
            if (!empty($regionNames)) {
                $resultGroups[] = implode('-', $regionNames);
            }
        }
        
        return implode(';', $resultGroups);
    }

    
    public $column_dealer_bs = "所属经销商";
    public $column_dealer_bs_width = 150;
    public $column_dealer_bs_order = 15;
    /**
     * column_dealer_bs
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_dealer_bs($row, $list)
    {
        if (empty($row['store_bn'])) {
            return '';
        }
        
        static $dealerCache;
        
        if (!isset($dealerCache)) {
            $dealerCache = array();
            
            // 从传入的list参数获取当前页面的所有store_bn
            $storeBns = array_unique(array_column($list, 'store_bn'));
            
            if (!empty($storeBns)) {
                // 调用getDealerByStoreBn方法批量获取经销商信息
                $dealerBusiness = kernel::single('dealer_business');
                $dealerList = $dealerBusiness->getDealerByStoreBn($storeBns);
                
                // 构建缓存，以store_bn为key，经销商名称为value
                foreach ($dealerList as $storeBn => $dealerInfo) {
                    $dealerCache[$storeBn] = isset($dealerInfo['name']) ? '[' . $dealerInfo['bs_bn'] . ']' . $dealerInfo['name'] : '';
                }
            }
        }
        
        return isset($dealerCache[$row['store_bn']]) ? $dealerCache[$row['store_bn']] : '';
    }
}
