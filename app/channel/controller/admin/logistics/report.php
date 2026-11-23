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

#第三方应用中心
class channel_ctl_admin_logistics_report extends desktop_controller{
    public function index(){
         #检测是不是第一次
         $first_examin_record = $this->app->model('logistics_examinreport')->getList('*',array('is_first_examin'=>'true'));
         $params = array( 
          'title'=>'物流体检报告',
          'actions' => array(
              'add_examin'=>array('label'=>app::get('ome')->_('新体检'),'href'=>"index.php?app=channel&ctl=admin_logistics_report&act=add_examin",'target'=>'dialog::{width:690,height:300,title:\'新体检\'}"'),
          ),
          'use_buildin_recycle'=>false,
           'orderBy' => 'examin_time desc',
        ); 
        $this->finder('channel_mdl_logistics_examinreport',$params);
    }

    function add_examin(){
       $examin_service_info = kernel::single('ome_addedservice')->get_service('examin');
       $used_times = $examin_service_info['used_times']?$examin_service_info['used_times']:0;#总使用次数
       $available_times = $examin_service_info['available_times']?$examin_service_info['available_times']:0;#可授权次数
       $filter['examin_status'] = array('running','succ');
       #获取本地已使用体检次数（运行中和已成功）
       $local_used_times = $this->app->model('logistics_examinreport')->count($filter);
       if($local_used_times >= $used_times){
           $used_times = $local_used_times ;
       }
       
       $this->pagedata['used_times'] = $used_times;
       $this->pagedata['available_times'] = $available_times;
       
       $obj_delivery = app::get('ome')->model('delivery');
       #先检查已签收或揽收发货单，数量是不是已经大于500，只有大于500条的，才可以体检
       $this->pagedata['history_order_count'] = $obj_delivery->count(array('is_received'=>array('1','2')));
       $this->page('admin/logistics/reexamin.html');
    }  
    #添加体检
    public function add_examin_task(){
    	$obj_examin_report = app::get('channel')->model('logistics_examinreport');
    	$rs =  $obj_examin_report->getList('*',array('is_first_examin'=>'true'));
    	#查看是否已经第一次体检
    	if(!empty($rs)){
    		$is_first_examin = false;
    	}else{
    		$is_first_examin = true;
    	}
    	$examin_data['examin_time'] = time();
    	$examin_data['is_first_examin'] = $is_first_examin?'true':'false';
    	$examin_data['examin_op_user']  = kernel::single('desktop_user')->get_name();
    	$obj_examin_report = app::get('channel')->model('logistics_examinreport');
    	#预先生成报告日志
    	$obj_examin_report->save($examin_data);
    	 
    	$params = array(
    			'is_first_examin' => $is_first_examin,
    			'examin_id'=>$examin_data['examin_id'],
    	);
    	#开始体检
    	kernel::single('ome_event_trigger_shop_hqepay')->logistics_examin_send($params);
    	$re = array('status'=>'finish');
    	echo json_encode($re);exit;
    }    
    
    
    
}