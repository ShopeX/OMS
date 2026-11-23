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
class channel_ctl_admin_logistics_examin extends desktop_controller{
    public function index(){
    	#检测是否需要上传模板
    	$need_upload_temple = kernel::single('channel_func')->check_need_upload_temple();

        #检查发货仓库信息的完整性
        $branch_info_fullfill =  kernel::single('ome_func')->check_branch_info_fullfill();

        
        $this->pagedata['info_fullfill'] = $branch_info_fullfill;
        
        
        $obj_delivery = app::get('ome')->model('delivery');
        #先检查已签收或揽收发货单，数量是不是已经大于500，只有大于500条的，才可以体检
        $this->pagedata['history_order_count'] = $obj_delivery->count(array('is_received'=>array('1','2')));
        
        
        #计算发货仓库数量（模板上传，以仓库为单位进行上传）
        $_all_branch  = app::get('ome')->model('branch')->getList('branch_id',array('is_deliv_branch'=>'true'));
        $all_branch = array();
        foreach( $_all_branch as $key=>$val){
        	$all_branch_id[$val['branch_id']] = $val['branch_id'];
        }
        
        $this->pagedata['need_upload_temple'] =  $need_upload_temple;
        $this->pagedata['all_warehouse_id'] =  json_encode($all_branch_id);
        $this->pagedata['total_warehouse_amount'] = count($all_branch_id);
        $this->page('admin/logistics/setting.html');
    }
    public function ajax_get_used_times(){
    	$examin_service_info = kernel::single('ome_addedservice')->get_service('examin');
    	$used_times = $examin_service_info['used_times']?$examin_service_info['used_times']:'0';#总使用次数
    	$filter['examin_status'] = array('running','succ');
    	#获取本地已使用体检次数（运行中和已成功）
    	$local_used_times = $this->app->model('logistics_examinreport')->count($filter);
    	if($local_used_times >= $used_times){
    		$used_times = $local_used_times ;
    	}
    	$available_times = $examin_service_info['available_times']?$examin_service_info['available_times']:'0';#可授权次数
    	echo json_encode(array('used_times'=>$used_times,'available_times'=>$available_times));
    }
    #以发货网店为单位，进行模板上传
    public function ajax_warehouse_template_upload(){
        $warehouse_id = $_POST['warehouse_id'];
        $exrecommend_souce = $_POST['exrecommend_souce']?$exrecommend_souce:'hqepay';
        
        $status = kernel::single('ome_event_trigger_exrecommend_recommend')->strategyUpdate($warehouse_id);#同步与仓库相关的所有模板策略
        
        if($status['rsp'] == 'succ'){
           echo json_encode(array('status'=>'succ'));die;
        }else{
           echo json_encode(array('status'=>'fail'));die;
        }
    }
    #添加体检
    public function ajax_start_examin(){
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
        echo 'succ';
    }
    function ajax_upload_log(){
    	$data['op_user'] = kernel::single('desktop_user')->get_name();
    	$data['op_type'] = '1';#已同步模板
    	$data['create_time'] = time();
        $data['exrecommend_souce'] = $_POST['exrecommend_souce']?$_POST['exrecommend_souce']:'hqepay';
    	 
    	$obj_logistics_logs = app::get('channel')->model('logistics_logs');
    	$obj_logistics_logs->save($data);
    	
    	$obj_logistics_logs->update(array('status'=>''),array('op_type'=>3));#上传完毕，把模板变动的状态都清除
    }
}