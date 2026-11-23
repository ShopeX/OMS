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
 * 补货建议单列表
 * 
 * @author wangbiao<wangbiao@shopex.cn>
 * @version $Id: Z
 */
class console_ctl_admin_replenish_suggest extends desktop_controller
{
    public function index()
    {
        
        $base_filter = array();
        
        $actions = array();
        
        $params = array(
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag' => false,
            'use_buildin_recycle' => false,
           // 'use_buildin_import'=>true,
            //'use_buildin_importxls'=>true,
            'use_buildin_export' => false,
            'use_buildin_filter' => true,
            'use_view_tab' => true,
            'actions' => $actions,
            'title' => '补货申请列表',
            'base_filter' => $base_filter,
            'orderBy' => 'sug_id DESC',
        );
        
        $this->finder('console_mdl_replenish_suggest', $params);
    }
    
    
    
    /**
     * 获取补货建议单明细
     */
    public function getItems($sug_id)
    {
        if(empty($sug_id)){
            return '';
        }
        
        $itemsMdl = app::get('console')->model('replenish_suggest_items');
        
        //补货建议单明细
        $dataList = $itemsMdl->getList('*', array('sug_id'=>$sug_id));
    
        if(empty($dataList)){
            return '';
        }
        
        echo(json_encode($dataList));
    }

    /**
     * 补货建议明细展示
     * @param $task_id
     * @param $page
     */
    public function detail()
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');
        //明细表头数据
        $sug_id = intval($_GET['sug_id']);
        $page = intval($_GET['page']);

        $suggestMdl = app::get('console')->model('replenish_suggest');
        
        //补货任务详情
        $suggests = $suggestMdl->dump(array('sug_id'=>$sug_id), '*');

        $suggests['create_time'] = date('Y-m-d H:i:s',$suggests['create_time']);
        $suggests['last_modified'] = $suggests['last_modified'] ? date('Y-m-d H:i:s',$suggests['last_modified']) : '';

        $sug_status = array(
            '0' => '未确认',
            '1' => '已确认',
            '2' => '已完成',
            '3' => '已作废',
        );

        $suggests['sug_status_value'] = $sug_status[$suggests['sug_status']];
        $items = app::get('console')->model('replenish_suggest_items')->getList('*', [
            'sug_id' => $sug_id,
        ],0,-1,'item_id DESC');

        
        // 门店信息
        $branchs = app::get('ome')->model('branch')->dump([
            'branch_id'        => $suggests['branch_id'],
            'check_permission' => 'false',
        ],'branch_id, branch_bn,name');
      
       $this->pagedata['branchs'] = $branchs;
        
        $this->pagedata['suggests'] = $suggests;
        $this->pagedata['page'] = $page;
        $this->pagedata['items'] = $items;
        $this->page('admin/replenish/new_task_detail.html');
    }

    /**
     * 补货建议明细修改
     */
    public function saveApplyNums()
    {
        $item_id = $_POST['item_id'];
        $reple_nums = $_POST['reple_nums'];
        
        if(empty($item_id)){
            $this->splash('error',null, '无效的操作','');
        }
        
        if(!$reple_nums){
            $this->splash('error', null, '数量不能为空', '');
        }
    
        if($reple_nums <= 0){
            $this->splash('error',null, '实际补货数量必须大于0','');
        }
        
        $replenishSuggestMdl = app::get('console')->model('replenish_suggest_items');
        $suggest = $replenishSuggestMdl->db_dump(array('sug_id'=>$sug_id),'*');
    
        
        $res = $replenishSuggestMdl->update(array('reple_nums'=>$reple_nums),array('item_id'=>$item_id));
        if(!$res){
            $this->splash('error', null, '修改失败', '',['info'=>$suggest]);
        }
        $this->splash('success', null, '修改成功', '',['info'=>$suggest]);
    }

    public function exportTemplate($sug_id)
    {

        $suggestMdl = app::get('console')->model('replenish_suggest');
        $suggests = $suggestMdl->dump(array('sug_id'=>$sug_id),'task_bn');
        $row = $suggestMdl->getTemplateColumn();

        $list = $suggestMdl->getItems($sug_id);
        $data = array();
        foreach($list as $v){
            $data[] = array($suggests['task_bn'],$v['material_bn'],$v['reple_nums']);
        }

        $lib = kernel::single('omecsv_phpexcel');
        $lib->newExportExcel($data, '补货单导入模板', 'xls', $row);
    }
}
