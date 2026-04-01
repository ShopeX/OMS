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
 * @Author:   Assistant
 * @Version:  1.0
 * @DateTime: 2024年
 * @describe: 经销商物料价格管理
 * ============================
 */
class dealer_finder_goods_price 
{
    public $addon_cols = "id";
    public $column_edit = "操作";
    public $column_edit_width = 80;
    public $column_edit_order = -1;
    
    // 添加经销商名称列
    public $column_bs_name = "经销商名称";
    public $column_bs_name_width = 150;
    public $column_bs_name_order = 15;
    
    // 添加基础物料名称列
    public $column_material_name = "基础物料名称";
    public $column_material_name_width = 150;
    public $column_material_name_order = 16;
    
    protected $app;
    protected $_render;

    function __construct($app)
    {
        $this->app = $app;
        $this->_render = app::get('dealer')->render();
        if(in_array($_REQUEST['action'], ['exportcnf', 'to_export', 'export'])){
            unset($this->column_edit);
        }
    }

    /**
     * column_bs_name
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */

    public function column_bs_name($row, $list)
    {
        if (empty($row['bs_id'])) {
            return '';
        }
        
        static $dealerCache;
        
        if (!isset($dealerCache)) {
            $dealerCache = array();
            
            // 从传入的list参数获取当前页面的所有bs_id
            if (!empty($list)) {
                $bsIds = array_unique(array_column($list, 'bs_id'));
                
                if (!empty($bsIds)) {
                    $dealerObj = app::get('dealer')->model('business');
                    $dealerList = $dealerObj->getList('bs_id,name', array('bs_id' => $bsIds));
                    
                    $dealerCache = array_column($dealerList, 'name', 'bs_id');
                }
            }
        }
        
        return isset($dealerCache[$row['bs_id']]) ? $dealerCache[$row['bs_id']] : '';
    }
    
    /**
     * column_material_name
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_material_name($row, $list)
    {
        if (empty($row['bm_id'])) {
            return '';
        }
        
        static $materialCache;
        
        if (!isset($materialCache)) {
            $materialCache = array();
            
            // 从传入的list参数获取当前页面的所有bm_id
            if (!empty($list)) {
                $bmIds = array_unique(array_column($list, 'bm_id'));
                
                if (!empty($bmIds)) {
                    $materialObj = app::get('material')->model('basic_material');
                    $materialList = $materialObj->getList('bm_id,material_name', array('bm_id' => $bmIds));
                    
                    $materialCache = array_column($materialList, 'material_name', 'bm_id');
                }
            }
        }
        
        return isset($materialCache[$row['bm_id']]) ? $materialCache[$row['bm_id']] : '';
    }
    

    
    /**
     * column_edit
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_edit($row)
    {
        $finder_id = $_GET['_finder']['finder_id'];
        $id = $row['id'];
        
        // 检查编辑权限
        $desktop_user = kernel::single('desktop_user');
        if (!$desktop_user->has_permission('dealer_goods_price_edit')) {
            return '';
        }
        
        // 检查记录是否已过期
        $current_time = time();
        $is_expired = false;
        
        // 如果有过期时间，检查是否已过期
        if ($row['end_time']) {
            if ($current_time >= $row['end_time']) {
                $is_expired = true;
            }
        }
        
        // 如果已过期，不显示编辑按钮
        if ($is_expired) {
            return '';
        }
        
        $button = '<a href="index.php?app=dealer&ctl=admin_goods_price&act=edit&p[0]=' . $id . '&finder_id=' . $finder_id . '" target="dialog::{width:800,height:600,title:\'编辑价格\'}">编辑</a>';
        return $button;
    }
    
    // 添加操作日志功能
    public $detail_show_log = '操作记录';
    /**
     * detail_show_log
     * @param mixed $price_id ID
     * @return mixed 返回值
     */
    public function detail_show_log($price_id)
    {
        // 使用ome模块的read_log方法，与经销商保持一致
        $omeLogMdl = app::get('ome')->model('operation_log');
        $logList = $omeLogMdl->read_log(array('obj_id' => $price_id, 'obj_type' => 'goods_price@dealer'), 0, -1);
        
        $finder_id = $_GET['_finder']['finder_id'];
        
        if ($logList) {
            foreach ($logList as $k => $v) {
                $logList[$k]['operate_time'] = date('Y-m-d H:i:s', $v['operate_time']);

                // 检查操作类型，为编辑操作添加快照链接
                if (strpos($v['operation'], '编辑') !== false) {
                    $logList[$k]['memo'] = "<a href='index.php?app=dealer&ctl=admin_goods_price&act=show_history&p[0]={$v['log_id']}&finder_id={$finder_id}' onclick=\"window.open(this.href, '_blank', 'width=801,height=570'); return false;\">查看快照</a>";
                } else {
                    // 其他操作（如新建）不显示快照链接，但保留原有的memo内容
                    $logList[$k]['memo'] = $v['memo'] ?: '';
                }
            }
        }
        
        $render = app::get('dealer')->render();
        $render->pagedata['logs'] = $logList ?: array();
        return $render->fetch('finder/goods/price/operation_log.html');
    }
} 
