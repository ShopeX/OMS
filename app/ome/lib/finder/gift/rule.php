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
 * User: jintao
 * Date: 2016/3/18
 */
class ome_finder_gift_rule
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


    var $addon_cols = 'filter_arr,start_time,end_time';
    var $detail_history = '赠品操作记录';
    var $column_edit = '操作';
    var $column_width = 120;
    /**
     * column_edit
     * @param mixed $row row
     * @return mixed 返回值
     */

    public function column_edit($row){
        $finder_id = $_GET['_finder']['finder_id'];
        $id = $row['id'];
        $shop_id = $row['shop_id'];
        $lv_id = $row['lv_id'];
        $gift_bn = $row['gift_bn'];
        $filter_arr = $row[$this->col_prefix.'filter_arr'];
        $filter_arr = json_decode($filter_arr,true);
        
        $button = '';
        //$button .= '<a href="index.php?app=ecorder&ctl=admin_gift_rule&act=edit_rule&p[0]='.$id.'&p[1]='.$_GET['view'].'&finder_id='.$finder_id . '" target="dialog::{title:\''.app::get('ecorder')->_('编辑促销规则').'\', width:700, height:380}">编辑</a>';
        $button .= '<a href="index.php?app=ome&ctl=admin_crm_gift&act=addAndEdit&p[0]=add&id='.$id.'&finder_id='.$finder_id . '">编辑</a>';

        $button .= ' | <a href="index.php?app=ome&ctl=admin_crm_gift&act=priority&p[0]='.$id.'&p[1]='.$_GET['view'].'&finder_id='.$finder_id . '" target="dialog::{title:\''.app::get('ecorder')->_('设置优先级').'\', width:550, height:250}">优先级</a>';

        
        if ($filter_arr['buy_goods']['type'] == '1'){

            $button.= ' | <a href="index.php?app=ome&ctl=admin_crm_gift&act=import_goods&p[0]='.$id.'&p[1]='.$_GET['view'].'&finder_id='.$finder_id . '" target="dialog::{title:\''.app::get('ecorder')->_('导入').'\', width:550, height:250}">导入</a>';
        }
        
        $button .= " | <a href='javascript:void(0);' target='download' onclick='if(confirm(\"你确定要复制此赠品规则吗？\")) {href=\"index.php?app=ome&ctl=admin_crm_gift&act=copy_rule&id={$id}&finder_id={$finder_id}\";}'>复制</a>";
        
        return $button;
    }


    function detail_history($id){
        $render = app::get('ome')->render();
        $logObj = app::get('ome')->model('operation_log');
        $history = $logObj->read_log(array('obj_id'=>$id,'obj_type'=>'gift_rule@ome'),0,-1);
        foreach($history as $k=>$v){

            $history[$k]['operate_time'] = date('Y-m-d H:i:s',$v['operate_time']);
        }
        $render->pagedata['history'] = $history;
        return $render->fetch('admin/gift/detail_history.html');
    }

    var $column_limit = '是否限量';
    var $column_limit_width = 120;
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
            $sql = "select count(distinct order_bn) as total_orders from sdb_ome_gift_logs where gift_rule_id=".$id." ";
            $gift_logs = $db->selectRow($sql);
            $show_limit = '限量赠送:'.$filter_arr['buy_goods']['limit_orders'];
            $show_limit.='已送:'.$gift_logs['total_orders'].'';

            return sprintf("<div onmouseover='bindFinderColTip(event)' rel='%s' style='height:20px;background-color:%s;float:left;color:#ffffff;'>&nbsp;%s&nbsp;</div>", $show_limit, 'green', $show_limit);
        }else{
            return '-';
        }
    }

    var $column_agingstatus = '规则时效';
    var $column_agingstatus_width = 120;
    /**
     * column_agingstatus
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_agingstatus($row){
        $nowTime = time();
        $start_time = $row[$this->col_prefix.'start_time'];
        $end_time = $row[$this->col_prefix.'end_time'];

        if($nowTime >= $start_time && $nowTime <= $end_time){
            return "<span style='color:green;'>有效</span>";
        }elseif($nowTime < $start_time){
            return "<span style=''>未开始</span>";
        }else{
            return "<span style='color:red;'>已过期</span>";
        }
    }
}