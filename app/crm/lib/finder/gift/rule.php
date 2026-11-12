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

class crm_finder_gift_rule{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    
    var $addon_cols = "start_time,end_time,status,disable,filter_arr";
    
    var $column_edit = "操作";
    var $column_edit_width = 120;
    var $column_edit_order = COLUMN_IN_HEAD;
    function column_edit($row)
    {
        $finder_id = $_GET['_finder']['finder_id'];
        $id = $row['id'];
        $filterArr = json_decode($row[$this->col_prefix . 'filter_arr'], true);
        $button = '';
        if($filterArr['add_or_divide']) {
            $button .= '<a href="index.php?app=crm&ctl=admin_gift_rule&act=index&p[0]=add&id=' . $id . '&finder_id=' . $finder_id . '">编辑</a> | ';
        }
        $button .= '<a href="index.php?app=crm&ctl=admin_gift_rule&act=priority&p[0]='.$id.'&p[1]='.$_GET['view'].'&finder_id='.$finder_id . '" target="dialog::{title:\''.app::get('crm')->_('设置优先级').'\', width:550, height:250}">优先级</a>';
        $button .= ' | <a href="index.php?app=crm&ctl=admin_gift_rule&act=copy_rule&id=' . $id . '&finder_id=' . $finder_id . '">复制</a>';
        return $button;
    }
        
    var $column_validtime = "有效期";
    var $column_validtime_width = 180;
    var $column_validtime_order = 80;
    function column_validtime($row)
    {
        $start_time = $row[$this->col_prefix.'start_time'];
        $end_time = $row[$this->col_prefix.'end_time'];
        
        $button = date('Y-m-d', $start_time).' ~ '.date('Y-m-d', $end_time);        
        return $button;
    }
    
    var $column_status = "状态";
    var $column_status_width = 80;
    var $column_status_order = 90;
    function column_status($row)
    {
        $start_time = $row[$this->col_prefix.'start_time'];
        $end_time = $row[$this->col_prefix.'end_time'];
        $status = $row[$this->col_prefix.'status'];

        $button = '';
        if($status=='0') {
            $button .= ' <font color="#999">已关闭</font>';        
        }elseif($start_time > time()){
            $button .= ' <font color="#999">未开始</font>';        
        }elseif($end_time < time()){
            $button .= ' <font color="#999">已过期</font>';
            //自动将过期的设置为关闭
            /*app::get('crm')->model('gift_rule')->update(
                array('status'=>'0'), 
                array('id'=>$row['id'])
            );*/
        }
        elseif($row[$this->col_prefix.'disable'] =='true'){
            $button .= ' <font color="#999">已作废</font>';
        }
        else{
            $button .= ' <font color=green>活动中</font>';
        }        
        return $button;
    }
    
    /**
     * row_style
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function row_style($row)
    {
        $start_time = $row[$this->col_prefix.'start_time'];
        $end_time = $row[$this->col_prefix.'end_time'];
        $status = $row[$this->col_prefix.'status'];
        
        if($status=='0' or $start_time > time() or $end_time < time()){
            return 'list-close';
        }else{
            return '';
        }
    }

    public $detail_detail = '详情';
    public $detail_detail_order = '10';
    /**
     * detail_detail
     * @param mixed $id ID
     * @return mixed 返回值
     */
    public function detail_detail($id){
        $render = app::get('crm')->render();
        $mdlRule = app::get('crm')->model("gift_rule");
        $rule = $mdlRule->db_dump(array('id'=>$id));
        if($rule['shop_ids'] == '_ALL_') {
            $rule['shop_ids'] = '';
        } else {
            $shop = app::get('ome')->model('shop')->getList('name', array('shop_id' => explode(',', $rule['shop_ids'])));
            $rule['shop_ids'] = implode(',', array_map('current', $shop));
        }
        $mdlGoods = app::get('crm')->model('gift');
        $ruleBaseId = array();
        $rule['filter_arr'] = json_decode($rule['filter_arr'], true);
        if($rule['filter_arr']['add_or_divide']) {
            foreach ($rule['filter_arr']['id'] as $val) {
                $ruleBaseId[$val] = $val;
            }
            $ruleBase = app::get('crm')->model("gift_rule_base")->getList('*', array('id'=>$ruleBaseId));
            foreach ($ruleBase as $k => $val) {
                $gift_list = unserialize($val['gift_list']);
                $keySort = array_keys($gift_list);;
                $giftRows = $mdlGoods->getList('*',array('gift_id'=>array_keys($gift_list)));
                $gifts = array ();
                foreach ($giftRows as $key => $value) {
                    $value['num']        = $gift_list[$value['gift_id']];
                    $value['gift_bn']    = $value['gift_bn'];
                    $value['gift_name']  = mb_substr($value['gift_name'],0,22,'utf-8');
                    $value['gift_price'] = $value['gift_price'];
                    $index = array_search($value['gift_id'], $keySort);
                    $gifts[$index] = $value;
                }
                ksort($gifts);
                $ruleBase[$k]['gifts'] = $gifts;
                $ruleBase[$k]['filter_arr'] = json_decode($val['filter_arr'], true);
            }
        } else {
            if($rule['gift_ids']){
                $gifts = $mdlGoods->getList('*',array('gift_id'=>explode(',', $rule['gift_ids'])));
                $gift_num = explode(',', $rule['gift_num']);
                foreach ($gifts as $key=>$val){
                    $gifts[$key]['num']        = $gift_num[$key];
                    $gifts[$key]['gift_bn']    = $val['gift_bn'];
                    $gifts[$key]['gift_name']  = mb_substr($val['gift_name'],0,22,'utf-8');
                    $gifts[$key]['gift_price'] = $val['gift_price'];
                }
                $rule['gifts'] = $gifts;
            }
            $ruleBase = array($rule);
        }
        $render->pagedata['rule'] = $rule;
        $render->pagedata['rule_base'] = $ruleBase;
        return $render->fetch("admin/gift_detail.html");
    }

    public $detail_snapshot = '快照';
    public $detail_snapshot_order = '20';
    /**
     * detail_snapshot
     * @param mixed $id ID
     * @return mixed 返回值
     */
    public function detail_snapshot($id){
        $render = app::get('crm')->render();
        $mdl_snapshot = app::get('crm')->model("snapshot");
        
        $logs = $mdl_snapshot->getList('*', array('task_id'=>$id, 'type'=>1),0,-1,'id desc');
        
        $render->pagedata['logs'] = $logs;
        return $render->fetch("admin/snapshot/gift_rule.html");
    }
}
