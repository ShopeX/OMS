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

class ome_finder_groups{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $detail_basic = "管理员组详情";
    
    function detail_basic($group_id){
        $render = app::get('ome')->render();
        $oOperators = app::get('desktop')->model("users");
        $oGroups = app::get('ome')->model("groups");
        $oGroup_ops = app::get('ome')->model("group_ops");
        $Operators = $oOperators->getList('user_id,name');
        $admin_name='';
        foreach($Operators as $k=>$v){
           $O_exist = array('group_id'=>$group_id,'op_id'=>$v['user_id']);
            if($oGroup_ops->dump($O_exist)){
                $admin_name.=$v['name'].',';
            }
        }
       
        $render->pagedata['admin_name'] =  $admin_name;
        $render->pagedata['Oper_detail'] =  $oGroups->dump($group_id);
        return $render->fetch('admin/system/groups_detail.html');
    }

    var $addon_cols = "group_id";
    var $column_groups = "管理员组编辑";
    var $column_groups_width = "100";
    function column_groups($row){
        $finder_id = $_GET['_finder']['finder_id'];
        return '<a href="index.php?app=ome&ctl=admin_group&act=editgroups&p[0]='.$row[$this->col_prefix.'group_id'].'&finder_id='.$finder_id.'" target="dialog::{width:500,height:300,title:\'添加管理员组\'}">编辑</a>';
    }
}
?>