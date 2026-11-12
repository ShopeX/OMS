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
 * 门店自动补货finder类
 *
 * @author wangbiao<wangbiao@shopex.cn>
 * @version $Id: Z
 */
class console_finder_replenish_task
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $addon_cols = 'task_id,store_ids,bm_ids';
    
    var $column_edit = '操作';
    var $column_edit_width = 180;
    var $column_edit_order = 1;
    /**
     * column_edit
     * @param mixed $row row
     * @return mixed 返回值
     */

    public function column_edit($row)
    {
        $finder_id = $_GET['_finder']['finder_id'];
        $task_id = ($row['task_id'] ? $row['task_id'] : $row[$this->col_prefix.'task_id']);
        
        //url
        $url_edit = '<a href="index.php?app=console&ctl=admin_replenish_task&act=edit&p[0]='. $task_id .'&finder_id='.$finder_id.' " target="dialog::{width:600,height:500,title:\'编辑补货任务\'}">编辑</a>';
        $url_comfirm = '<a href="index.php?app=console&ctl=admin_replenish_task&act=confirm&p[0]='. $task_id .'&finder_id='.$finder_id.' " target="dialog::{width:400,height:150,title:\'确认补货任务\'}">确认任务</a>';
        $url_dispose = '<a href="index.php?app=console&ctl=admin_replenish_task&act=dispose&p[0]='. $task_id .'&finder_id='.$finder_id.' " target="dialog::{width:400,height:150,title:\'确认生成补货单\'}">确认生成补货单</a>';
        $url_show = '<a href="index.php?app=console&ctl=admin_replenish_suggest&act=index&task_id='.$task_id.'&finder_id='.$finder_id.'">查看建议单</a>';
        
        //operaction 
        $urlList = array();
        switch ($row['task_status']){
            case '0':
                $urlList['url_edit'] = $url_edit;
                $urlList['url_comfirm'] = $url_comfirm;
            break;
            case '2':
                $urlList['url_show'] = $url_show;
            break;
            case '3':
                $urlList['url_dispose'] = $url_dispose;
                $urlList['url_show'] = $url_show;
            break;
            case '4':
            case '5':
            case '6':
                $urlList['url_show'] = $url_show;
            break;
        }
        
        return implode(' | ', $urlList);
    }
    
    var $detail_basic = '补货任务详情';
    /**
     * detail_basic
     * @param mixed $task_id ID
     * @return mixed 返回值
     */
    public function detail_basic($task_id)
    {
        $render = app::get('console')->render();
        
        $reTaskObj = app::get('console')->model('replenish_task');
        
        //补货任务详情
        $reTaskInfo = $reTaskObj->dump(array('task_id'=>$task_id), '*');
        $task_status = $reTaskInfo['task_status'];
        $store_type = $reTaskInfo['store_type'];
        $out_branch_id = $reTaskInfo['out_branch_id'];
        
        //dbschema
        $schema = $reTaskObj->get_schema();
        
        //单据状态
        $statusList = $schema['columns']['task_status']['type'];
        $reTaskInfo['task_status'] = $statusList[$task_status];
        
        //门店类型
        $storeTypeList = $schema['columns']['store_type']['type'];
        $reTaskInfo['store_type_name'] = $storeTypeList[$store_type];
        
        //调出仓库
        $sql = "SELECT branch_id,branch_bn,name FROM sdb_ome_branch WHERE branch_id=".$out_branch_id;
        $branchInfo = $reTaskObj->db->selectrow($sql);
        $reTaskInfo['branch_name'] = $branchInfo['name'];
        
        $render->pagedata['reTaskInfo'] = $reTaskInfo;
        
        return $render->fetch('admin/replenish/task_detail.html');
    }
    
    var $detail_item = '补货建议单明细';
    /**
     * detail_item
     * @param mixed $task_id ID
     * @return mixed 返回值
     */
    public function detail_item($task_id)
    {
        $render = app::get('console')->render();
        
        $reTaskObj = app::get('console')->model('replenish_task');
        $reTaskInfo = $reTaskObj->dump(array('task_id'=>$task_id), '*');
        
        $render->pagedata['reTaskInfo'] = $reTaskInfo;
        
        if ($this->vue_mode) { return []; }
        return $render->fetch('admin/replenish/suggest_item.html');
    }
    
    var $column_store = '店铺范围';
    var $column_store_width = 120;
    var $column_store_order = 30;
    /**
     * column_store
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_store($row)
    {
        $store_ids = $row[$this->col_prefix.'store_ids'];
        
        $text = '部分门店';
        if(empty($store_ids) || $store_ids=='_ALL_'){
            $text = '全部门店';
        }
        
        return $text;
    }
    
    var $column_product = '商品范围';
    var $column_product_width = 120;
    var $column_product_order = 31;
    /**
     * column_product
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_product($row)
    {
        $bm_ids = $row[$this->col_prefix.'bm_ids'];
        
        $text = '部分商品';
        if(empty($bm_ids) || $bm_ids=='_ALL_'){
            $text = '全部商品';
        }
        
        return $text;
    }
}
?>