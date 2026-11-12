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

class purchase_finder_pick_order_items
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    private $_appName = 'purchase';
    
    public $addon_cols = 'item_id,stat';
    
    var $detail_basic = '基本信息';
    /**
     * detail_basic
     * @param mixed $id ID
     * @return mixed 返回值
     */
    public function detail_basic($id)
    {
        $render = app::get($this->_appName)->render();
        $orderMdl = app::get($this->_appName)->model('pick_order_items');
        
        $statusList = array(
            'none' => '未处理',
            'running' => '处理中',
            'succ' => '已完成',
            'fail' => '处理失败',
        );
        
        //order
        $itemInfo = $orderMdl->dump(array('item_id'=>$id), '*');
        
        $itemInfo['status_value'] = $statusList[$itemInfo['status']];
        
        $render->pagedata['itemInfo'] = $itemInfo;
        
        return $render->fetch('admin/pick/order_item_detail.html');
    }
    
    var $column_stat = '订单状态';
    public $column_stat_width = 90;
    public $column_stat_order = 32;
    /**
     * column_stat
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_stat($row)
    {
        $orderMdl = app::get($this->_appName)->model('pick_order_items');
        
        //list
        $statList = $orderMdl::$order_stat;
        
        //stat
        $stat = $row[$this->col_prefix.'stat'];
        
        return $statList[$stat]['name'];
    }
}
?>