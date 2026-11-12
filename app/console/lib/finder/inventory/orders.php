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
 * 唯品会销售单订单finder
 *
 * @author wangbiao@shopex.cn
 * @version 2025.03.21
 */
class console_finder_inventory_orders
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    private $_appName = 'console';
    
//    public $addon_cols = 'id';
//    public $column_edit = '操作';
//    public $column_edit_width = 120;
//    public $column_edit_order = 1;
//    public function column_edit($row)
//    {
//        $finder_id = $_GET['_finder']['finder_id'];
//        $bill_inventory_id = $row[$this->col_prefix.'id'];
//
//        //$button = '<a href="index.php?app=". $this->_appName ."&ctl=admin_business&act=edit&p[0]='. $order_id .'&finder_id='. $finder_id .'" target="_blank">同步</a>';
//        $button = '';
//
//        return $button;
//    }
    
    var $detail_basic = '基本信息';
    /**
     * detail_basic
     * @param mixed $id ID
     * @return mixed 返回值
     */

    public function detail_basic($id)
    {
        $render = app::get($this->_appName)->render();
        $orderMdl = app::get($this->_appName)->model('inventory_orders');
        
        $dispose_status = array(
            'none' => '未处理',
            'running' => '处理中',
            'part' => '部分处理',
            'finish' => '已完成',
            'cancel' => '已取消',
            'needless' => '无需处理',
            'fail' => '处理失败',
        );
        
        //order
        $orderInfo = $orderMdl->dump(array('id'=>$id), '*');
        
        //dispose_status
        $orderInfo['dispose_status_value'] = $dispose_status[$orderInfo['dispose_status']];
        
        //hold_flag
        $hold_flags = $orderMdl->getHoldStatus();
        $orderInfo['hold_flag_value'] = $hold_flags[$orderInfo['hold_flag']];
        
        //format
        $orderInfo['warehouse_flag'] = (empty($orderInfo['warehouse_flag']) ? '' : $orderInfo['warehouse_flag']);
        $orderInfo['is_prebuy'] = (empty($orderInfo['is_prebuy']) ? '' : $orderInfo['is_prebuy']);
        $orderInfo['sales_source_indicator'] = (empty($orderInfo['sales_source_indicator']) ? '' : $orderInfo['sales_source_indicator']);
        
        $render->pagedata['orderInfo'] = $orderInfo;
        
        return $render->fetch('admin/vop/voporders_detail.html');
    }
    
    var $detail_items = '销售明细';
    /**
     * detail_items
     * @param mixed $id ID
     * @return mixed 返回值
     */
    public function detail_items($id)
    {
        $render = app::get('console')->render();
        $itemMdl = app::get($this->_appName)->model('inventory_order_items');
        $itemList = $itemMdl->getList('*', array('id'=>$id));
        
        $statusList = array(
            'none' => '未处理',
            'running' => '处理中',
            'succ' => '已完成',
            'fail' => '处理失败',
        );
        
        //format
        if($itemList){
            foreach ($itemList as $itemKey => $itemInfo)
            {
                $itemList[$itemKey]['status_value'] = $statusList[$itemInfo['status']];
                
                $itemList[$itemKey]['sales_source_indicator'] = (empty($itemInfo['sales_source_indicator']) ? '' : $itemInfo['sales_source_indicator']);
            }
        }
        
        $render->pagedata['itemList'] = $itemList;
        
        if ($this->vue_mode) { return []; }
        return $render->fetch('admin/vop/voporders_items.html');
    }
    
    var $detail_log = '操作日志';
    /**
     * detail_log
     * @param mixed $id ID
     * @return mixed 返回值
     */
    public function detail_log($id)
    {
        $render = app::get($this->_appName)->render();
        
        //log
        $operLogMdl = app::get('ome')->model('operation_log');
        $logList = $operLogMdl->read_log(array('obj_id'=>$id, 'obj_type'=>'inventory_orders@console'), 0, -1);
        
        $render->pagedata['logList'] = $logList;
        
        return $render->fetch('admin/vop/logs.html');
    }
}