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

class o2o_ctl_admin_refuse_reason extends desktop_controller
{
    function index()
    {
        $this->finder('o2o_mdl_refuse_reason',array(
                'title'=>'门店拒单原因',
                'actions'=>array(
                        array(
                                'label'=>'添加',
                                'href'=>'index.php?app=o2o&ctl=admin_refuse_reason&act=add',
                                'target' => 'dialog::{width:450,height:150,title:\'新增拒单原因\'}'
                        ),
                        array(
                                'label' => '删除',
                                'submit' => 'index.php?app=o2o&ctl=admin_refuse_reason&act=delReason&finder_id='.$_GET['finder_id'],
                                'confirm' => '是否确认删除?',
                                'target' => 'refresh'
                        ),
                ),
                'use_buildin_new_dialog' => false,
                'use_buildin_set_tag'=>false,
                'use_buildin_recycle'=>false,
                'use_buildin_export'=>false,
                'use_buildin_import'=>false,
        ));
    }
    
    /**
     * 添加类型
     */
    function add()
    {
        $refuseObj    = app::get('o2o')->model('refuse_reason');
        if($_POST)
        {
            $this->begin($this->url.'&act=index');
            $refuseObj->save($_POST['reason']);
            $this->end(true, app::get('base')->_('保存成功'));
        }
        
        $this->pagedata['title'] = '拒单原因';
        $this->page("admin/system/reason.html");
    }
    
    /**
     * 编辑类型
     */
    function edit($reason_id)
    {
        $refuseObj    = app::get('o2o')->model('refuse_reason');
        $this->pagedata['reason']    = $refuseObj->dump($reason_id);
        $this->pagedata['title']       = '拒单原因';
        $this->page("admin/system/reason.html");
    }

    /**
     * 拒单原因统计报表
     */
    function statistics(){
        if(empty($_POST)){
            $date_arr = $this->get_time_start();
            $_POST['time_from'] = $date_arr['now_zero_year'];
            $_POST['time_to'] = $date_arr['now_end_year'];
        }
        kernel::single('o2o_refuse_reason_statistics')->set_params($_POST)->display();
    }

    function get_time_start(){
        $t   =   time();   
        $nowday   =   date('Y-m-d',mktime(0,0,0,date("m",$t),date("d",$t),date("Y",$t)));            //当天零点的时间戳 
        $nowmonth   =   date('Y-m-d',mktime(0,0,0,date("m",$t),1,date("Y",$t)));                    //当月一号零点的时间戳
        $nextmonth =  date('Y-m-d',mktime(0,0,0,date("m",$t)+1,1,date("Y",$t)));                    //下月一号零点时间戳
        $nowyear    =   date('Y-m-d',mktime(0,0,0,1,1,date("Y",$t)));                                //当年一月一号零点时间戳
        $lastmonth =  date('Y-m-d',mktime(0,0,0,date("m",$t)-1,1,date("Y",$t)));                    //上月一号零点时间戳
        $nowendmonth = date('Y-m-d',mktime(0,0,0,date("m",$t)+1,1,date("Y",$t))-24*60*60);
        $nowendyear = date('Y-m-d',mktime(0,0,0,12,31,date("Y",$t)));
        return array('now_zero_day' => $nowday,
                     'now_zero_month'=>$nowmonth,
                     'now_zero_year'=>$nowyear,
                     'next_zero_month'=>$nextmonth,
                     'last_zero_month'=>$lastmonth,
                     'now_end_month'=>$nowendmonth,
                     'now_end_year'=>$nowendyear
        );
    }

    /**
     * chart_view
     * @return mixed 返回值
     */
    public function chart_view() {
        $filter = $_GET;
        if(isset($filter['report']) && $filter['report']=='month'){
            $time_from = strtotime($filter['time_from']);
            if($time_from == strtotime(date("Y-m-d"),time())){
                $time_from = ($time_from - 86400);
            }
            $last_time = date("Y-m-d",($time_from - 86400));
            $filter['time_from'] = date("Y-m-d",$time_from);
            $filter['time_to'] = date("Y-m-d",$filter['time_to']);
        }
        
        $filter['type_id']    = ($filter['type_id'] == '_NULL_' ? '' : $filter['type_id']);
        
        $filter = array(
            'time_from' => $filter['time_from'],
            'time_to' => $filter['time_to'],
            'type_id' => $filter['type_id'],
        );
        $fil = array(
                'time_from' => $last_time,
                'time_to' => date("Y-m-d",$time_from),
        );

        $refuseAnalysisObj = app::get('o2o')->model('store_refuse_analysis');
        $reasons = $refuseAnalysisObj->get_reason_analysis($filter);
            
        $reason_name = array();
        $reason_num = array();
        foreach($reasons as $key => $reason){
            $reason_name[] = '\''.$reason['name'].'\'';
            $reason_num[] = $reason['num'] ? $reason['num'] : 0;    
        }

        $categories = implode(',',$reason_name);
        $volume = implode(',',$reason_num);

        $this->pagedata['categories'] = '['.$categories.']';
        $this->pagedata['data']='{
                name: \'门店拒单原因分析\',
                data: ['.$volume.']
            }';
        $this->display("admin/analysis/ordersPrice/chart_type_column.html");
    }
    
    /**
     * 删除未使用过的拒单原因
     */
    function delReason()
    {
        $this->begin('');
        
        $ids    = array_map('intval', (array)$_POST['reason_id']);
        if($ids)
        {
            $refuseObj    = app::get('o2o')->model('refuse_reason');
            
            foreach($ids as $id)
            {
                //门店发货单拒单原因记录
                $deliveryInfo    = $refuseObj->db->selectrow("SELECT * FROM `sdb_o2o_store_refuse_analysis` WHERE reason_id=". $id);
                if($deliveryInfo)
                {
                    //拒单原因信息
                    $row    = $refuseObj->dump(array('reason_id'=>$id), 'reason_name');
                    
                    $this->end(false, '拒单原因：'. $row['reason_name'] .'，已经被使用不能删除。');
                    exit;
                }
                else 
                {
                    $refuseObj->db->exec("DELETE FROM sdb_o2o_refuse_reason WHERE reason_id=". $id);
                }
            }
        }
        
        $this->end(true);
    }
}