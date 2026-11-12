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

class console_finder_vopurchase{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    
    var $addon_cols    ='warehouse,sell_et_time';
    
    var $column_warehouse = '入库仓库';
    var $column_warehouse_width = '100';
    var $column_warehouse_order = 14;
    function column_warehouse($row)
    {
        if($row[$this->col_prefix .'warehouse'])
        {
            $purchaseLib    = kernel::single('purchase_purchase_order');
            $branchInfo     = $purchaseLib->getWarehouse($row[$this->col_prefix .'warehouse']);
            
            $row[$this->col_prefix .'warehouse']    = $branchInfo['branch_name'];
        }
        
        return $row[$this->col_prefix .'warehouse'];
    }
    
    var $column_edit  = '操作';
    var $column_edit_order = 2;
    var $column_edit_width = '160';
    function column_edit($row)
    {
        $finder_id   = $_GET['_finder']['finder_id'];
        $po_id       = $row['po_id'];

        $button = [];
        if ($row[$this->col_prefix .'sell_et_time'] > time()) {
        if ($row['unpick_num']) {
            $button[] = <<<EOF
            <a href="index.php?app=console&ctl=admin_vopurchase&act=confirm&p[0]=$po_id&finder_id=$finder_id" target="dialog::{width:350,height:150,title:'创建拣货单'}">创建拣货单</a>
EOF;
        }

        $button[] = <<<EOF
            <a href="index.php?app=console&ctl=admin_vopurchase&act=getPicks&p[0]=$po_id&finder_id=$finder_id">下载拣货单</a>
EOF;
        }
        return '<span class="c-gray">'. implode(' ', $button) .'</span>';
    }
    
    var $detail_logs = '操作日志';
    /**
     * detail_logs
     * @param mixed $po_id ID
     * @return mixed 返回值
     */
    public function detail_logs($po_id)
    {
        $render = app::get('console')->render();
        $logObj = app::get('ome')->model('operation_log');
        
        $logs    = $logObj->read_log(array('obj_id'=>$po_id,'obj_type'=>'order@purchase'), 0, -1);
        foreach($logs as $k=>$v)
        {
            $logs[$k]['operate_time']    = date('Y-m-d H:i:s', $v['operate_time']);
        }
        
        $render->pagedata['logs']    = $logs;
        return $render->fetch('admin/vop/logs.html');
    }
}