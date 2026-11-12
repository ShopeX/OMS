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
 * @DateTime: 2022/11/21 15:44:49
 * @describe: 类
 * ============================
 */
class console_finder_difference {
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    public $addon_cols = 'status,diff_bn,operate_type,out_status,in_status';

    public $detail_item = "详情";
    /**
     * detail_item
     * @param mixed $id ID
     * @return mixed 返回值
     */

    public function detail_item($id){
        $render = app::get('console')->render();
        $itemsObj = app::get('console')->model('difference_items');
        $rows = $itemsObj->getList('*', array('diff_id'=>$id), 0, -1);

        $differenceMdl = app::get('console')->model('difference');

        $diff = $differenceMdl->db_dump($id,'operate_type');

        $render->pagedata['diff'] = $diff;
        $render->pagedata['rows'] = $rows;
        if($diff['operate_type'] == 'store'){
            if ($this->vue_mode) { return []; }
        return $render->fetch("admin/difference/item.html");
        }else{
            return $render->fetch("admin/difference/item.html");

        }
        
    }

    public $detail_freeze = "冻结详情";
    /**
     * detail_freeze
     * @param mixed $id ID
     * @return mixed 返回值
     */
    public function detail_freeze($id){
        $render = app::get('console')->render();
        $itemsObj = app::get('console')->model('difference_items_freeze');
        $rows = $itemsObj->getList('*', array('diff_id'=>$id), 0, -1);
        foreach ($rows as $k => $v) {
            $rows[$k]['branch_bn'] = kernel::single('ome_branch')->getBranchBnById($v['branch_id']);
        }
        $render->pagedata['rows'] = $rows;
        return $render->fetch("admin/difference/freeze_item.html");
    }

    public $detail_oplog = "操作记录";
    /**
     * detail_oplog
     * @param mixed $id ID
     * @return mixed 返回值
     */
    public function detail_oplog($id){
        $render = app::get('console')->render();
        $opObj  = app::get('ome')->model('operation_log');
        $logdata = $opObj->read_log(array('obj_id'=>$id,'obj_type'=>'difference@console'), 0, -1);
        foreach($logdata as $k=>$v){
            $logdata[$k]['operate_time'] = date('Y-m-d H:i:s',$v['operate_time']);
        }
        $render->pagedata['log'] = $logdata;
        return $render->fetch('admin/oplog.html');
    }

    var $column_operation = '操作';
    var $column_operation_width = 120;
    var $column_operation_order = 1;
    /**
     * column_operation
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_operation($row){
        $diff_bn = $row[$this->col_prefix.'diff_bn'];
        $btn = [];
        if(in_array($row[$this->col_prefix.'status'], ['2'])) {
            $btn[] = '<a class="lnk" href="javascript:if(confirm(\'确认单据'.$diff_bn.'?\')) {W.page(\'index.php?app=console&ctl=admin_difference&act=singleConfirm&p[0]='.$row['id'].'&finder_id='.$_GET['_finder']['finder_id'].'&finder_vid='.$_GET['finder_vid'].'\');};">
                            确认</a>';
            $operate_type = $row[$this->col_prefix.'operate_type'];

        
            if(in_array($operate_type, ['branch'])) {
                $btn[] = '<a class="lnk" href="index.php?app=console&ctl=admin_difference&act=doEdit&p[0]='.$row['id'].'&finder_id='.$_GET['_finder']['finder_id'].'" target="_blank">编辑</a>';

            }
            $btn[] = '<a class="lnk" 
                    href="javascript:if(confirm(\'取消单据'.$diff_bn.'?\')) {W.page(\'index.php?app=console&ctl=admin_difference&act=cancel&p[0]='.$row['id'].'&finder_id='.$_GET['_finder']['finder_id'].'&finder_vid='.$_GET['finder_vid'].'\');};">
                    取消</a>';
        }
        if(in_array($row[$this->col_prefix.'status'], ['1'])) {
            if(in_array($row[$this->col_prefix.'out_status'], ['1']) || in_array($row[$this->col_prefix.'in_status'], ['1'])) {
                $btn[] = '<a class="lnk" 
                        href="javascript:if(confirm(\''.$diff_bn.'生成出或入库单失败，重试?\')) {W.page(\'index.php?app=console&ctl=admin_difference&act=retry&p[0]='.$row['id'].'&finder_id='.$_GET['_finder']['finder_id'].'&finder_vid='.$_GET['finder_vid'].'\');};">
                重试</a>';
            }
        }
        return implode(' | ', $btn);
    }

    public $detail_useful = "有效期列表";
    /**
     * detail_useful
     * @param mixed $id ID
     * @return mixed 返回值
     */
    public function detail_useful($id){
        $render = app::get('console')->render();
        $itemsObj = app::get('console')->model('difference_items');
        $rows = $itemsObj->getList('batch,material_bn', array('diff_id'=>$id), 0, -1);

      
        foreach($rows as &$v){

            $v['batch'] = json_decode($v['batch'],true);
            
        }


        $render->pagedata['batchs'] = $rows;

        return $render->fetch("admin/useful/item.html");

    }
}
