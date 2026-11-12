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

class crm_finder_gift_rule_base{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    
    var $addon_cols = "disabled,gift_list,filter_arr";
    
    var $column_edit = "操作";
    var $column_edit_width = 100;
    var $column_edit_order = COLUMN_IN_HEAD;
    function column_edit($row)
    {
        $finder_id = $_GET['_finder']['finder_id'];
        $id = $row['id'];

        if ($this->vue_mode) {
            // Vue Finder 契约：结构化行操作（legacy HTML 链接不下发，避免 SPA 被 legacy 页整页跳走）；
            // 与 legacy 逐字对齐：编辑 → act=editRuleBase、复制 → act=copy_rulebase（均以弹窗承载）
            return [
                [
                    'key'    => 'edit',
                    'label'  => '编辑',
                    'action' => 'index.php?app=crm&ctl=admin_gift_rulebase&act=editRuleBase&id=' . $id,
                ],
                [
                    'key'    => 'copy',
                    'label'  => '复制',
                    'action' => 'index.php?app=crm&ctl=admin_gift_rulebase&act=copy_rulebase&id=' . $id,
                ],
            ];
        }

        $button = '';
      
        $button .= '<a href="index.php?app=crm&ctl=admin_gift_rulebase&act=editRuleBase&id='.$id.'&finder_id='.$finder_id . '">编辑</a>';
        $button .= ' | <a href="index.php?app=crm&ctl=admin_gift_rulebase&act=copy_rulebase&id='.$id.'&finder_id='.$finder_id . '">复制</a>';
        return $button;
    }

    var $column_limit = '是否限量';
    var $column_limit_width = 200;
    /**
     * column_limit
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_limit($row){
        $db = kernel::database();
        $filter_arr = $row[$this->col_prefix.'filter_arr'];
        $filter_arr = json_decode($filter_arr,true);
        $id = $row['id'];
        if($filter_arr['buy_goods']['limit_type'] == '1'){
            $sql = "select count(distinct order_bn) as total_orders from sdb_ome_gift_logs where rule_base_id=".$id."";
            $gift_logs = $db->selectRow($sql);
            $show_limit = '限量赠送:'.$filter_arr['buy_goods']['limit_orders'];
            $show_limit.='已送:'.$gift_logs['total_orders'].'';

            if ($this->vue_mode) {
                // Vue Finder 契约：结构化下发可见文案，不下发 legacy HTML 片段
                // （与 column_edit 的 vue_mode 分支同口径；SPA 不解析 legacy HTML）
                return $show_limit;
            }

            return sprintf("<div onmouseover='bindFinderColTip(event)' rel='%s' style='height:20px;background-color:%s;float:left;color:#ffffff;'>&nbsp;%s&nbsp;</div>", $show_limit, 'green', $show_limit);
        }else{
            return '-';
        }
    }



    var $detail_snapshot = '快照';
    /**
     * detail_snapshot
     * @param mixed $id ID
     * @return mixed 返回值
     */
    public function detail_snapshot($id){
        $render = app::get('crm')->render();
        $mdl_snapshot = app::get('crm')->model("snapshot");
        
        $logs = $mdl_snapshot->getList('*', array('task_id'=>$id, 'type'=>3),0,-1,'id desc');
        
        $render->pagedata['logs'] = $logs;
        return $render->fetch("admin/snapshot/gift_rule.html");
    }
    
}
