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
class console_finder_adjust {
    function __construct(){
        if(in_array($_REQUEST['action'], ['exportcnf', 'to_export', 'export'])){
            unset($this->column_edit);
        }
    }

    public $addon_cols = 'bill_status';

    public $column_edit = "操作";
    public $column_edit_width = "80";
    public $column_edit_order = "-1";
    public function column_edit($row) {
        $confirmBtn = '<a class="lnk" target="dialog::{width:550,height:250,title:\'单据确认\'}" 
                href="index.php?app=console&ctl=admin_adjust&act=singleConfirm&p[0]='.$row['id'].'&finder_id='.$_GET['_finder']['finder_id'].'">
                确认</a>';
        $cancelBtn = '<a class="lnk" 
                href="javascript:if(confirm(\'确定取消该单据?\')) {W.page(\'index.php?app=console&ctl=admin_adjust&act=cancel&p[0]='.$row['id'].'&finder_id='.$_GET['_finder']['finder_id'].'\');};">
                取消</a>';
        if(in_array($row[$this->col_prefix.'bill_status'], ['1','2'])) {
            return $confirmBtn . " | " . $cancelBtn;
        }
        return '';
    }

    public $detail_item = "基础物料详情";
    public function detail_item($id){
        $render = app::get('console')->render();
        $items = app::get('console')->model('adjust_items')->getList('*', ['adjust_id'=>$id]);
        $render->pagedata['items'] = $items;
        return $render->fetch('admin/adjust/items.html');
    }

    public $detail_oplog = "操作记录";
    public function detail_oplog($id){
        $render = app::get('console')->render();
        $opObj  = app::get('ome')->model('operation_log');
        $logdata = $opObj->read_log(array('obj_id'=>$id,'obj_type'=>'adjust@console'), 0, -1);
        foreach($logdata as $k=>$v){
            $logdata[$k]['operate_time'] = date('Y-m-d H:i:s',$v['operate_time']);
        }
        $render->pagedata['log'] = $logdata;
        return $render->fetch('admin/oplog.html');
    }

    public $detail_useful = "有效期列表";
    public function detail_useful($id){
        $render = app::get('console')->render();
        $itemsObj = app::get('console')->model('adjust_items');
        $rows = $itemsObj->getList('batch,bm_bn as material_bn', array('adjust_id'=>$id), 0, -1);

      
        foreach($rows as &$v){

            $v['batch'] = json_decode($v['batch'],true);
            
        }


        $render->pagedata['batchs'] = $rows;

        return $render->fetch("admin/useful/item.html");

    }

    // 添加仓库编码列
    public $column_branch_bn = "仓库编码";
    public $column_branch_bn_width = 100;
    public $column_branch_bn_order = 15;
    public function column_branch_bn($row, $list)
    {
        if (empty($row['branch_id'])) {
            return '';
        }
        
        static $branchCache;
        
        if (!isset($branchCache)) {
            $branchCache = array();
            
            // 从传入的list参数获取当前页面的所有bs_id
            if (!empty($list)) {
                $branchIds = array_unique(array_column($list, 'branch_id'));
                
                if (!empty($branchIds)) {
                    $branchObj = app::get('ome')->model('branch');
                    $branchList = $branchObj->getList('branch_id,branch_bn', array('branch_id' => $branchIds,'check_permission'=>'false'));
                    
                    $branchCache = array_column($branchList, 'branch_bn', 'branch_id');
                }
            }
        }
        
        return isset($branchCache[$row['branch_id']]) ? $branchCache[$row['branch_id']] : '';
    }

}
