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

class channel_ctl_admin_logistics_exrecommend extends desktop_controller{
	
    public function index(){
    	$this->pagedata['exrecommend_service'] = false;
    	#获取智选物流订购的信息
    	
    	$taobao_exrecommend_info = kernel::single('channel_func')->get_exrecommend_service_info('taobao');
    	
        if($taobao_exrecommend_info){
            $this->pagedata['exrecommend_service'] = true;#快递鸟和菜鸟,只要有一个开启智选物流，则就可用
            $service_info['service_on']['taobao']['name'] = '菜鸟智选物流';
        }else{
            $service_info['service_off']['taobao']['name'] ='菜鸟智选物流';
            $service_info['service_off']['taobao']['url'] = 'https://fuwu.taobao.com/ser/detail.htm?service_code=A-CNZNFHYQ&smToken=9844be5821a34ac8878a51ab0f1da297&smSign=exDPGNTaMTHwPcCWqBN9kA%3D%3D';
        }
    	
    	$this->pagedata['service_info'] = $service_info;
    	#获取历史设置日志，这些日志,要在设置页面显示出来
    	$exrecommend_history_logs = app::get('channel')->model('logistics_logs')->getList('create_time,op_user,op_content,exrecommend_souce',array('op_type'=>2),0,-1,'create_time desc');
    	if($exrecommend_history_logs){
    		$all_exrecommend_type = array();#智选物流提供方的智选策略
    		foreach($exrecommend_history_logs as $k=>$val){
    			$op_content = unserialize($val['op_content']);
    			$exrecommend_type = $op_content['exrecommend_type'];
    			$exrecommend_souce = $val['exrecommend_souce'];
    			$exrecommend_history_logs[$k]['create_time'] = date('Y-m-d H:i:s',$val['create_time']);
    			if(!$all_exrecommend_type[$exrecommend_souce]){
    			    $all_exrecommend_type[$exrecommend_souce] = kernel::single('channel_func')->exrecommend_type($exrecommend_souce);
    			}
    			$exrecommend_history_logs[$k]['exrecommend_type_name'] =$all_exrecommend_type[$exrecommend_souce][$exrecommend_type];
    		}
    		$this->pagedata['exrecommend_history_logs'] = $exrecommend_history_logs;
    	}
    	#取最新一条智选策略设置日志
    	$last_exrecommend_set_logs = app::get('channel')->model('logistics_logs')->getList('op_content',array('op_type'=>2),0,1,'create_time desc');
    	if($last_exrecommend_set_logs){
    	    $op_content = unserialize($last_exrecommend_set_logs[0]['op_content']);
    	    $set_exrecommend_type = $op_content['exrecommend_type'];
    	    #最新一条设置的智选策略
    	    $this->pagedata['set_exrecommend_type'] = $set_exrecommend_type;
    	}
    	#检查智选物流开关是否开启
        $set_exrecommend_service = $this->app->getConf('set_exrecommend_service');
        $this->pagedata['set_exrecommend_service'] = $set_exrecommend_service?1:0;
        
        $set_exrecommend_souce = 'taobao';
        #如果原来开启了智选物流服务，但是来源为空，默认成快递鸟,菜鸟智选物流是后来才加的
        
        $this->pagedata['set_exrecommend_souce'] = $set_exrecommend_souce;
        $exrecommend_type = kernel::single('channel_func')->exrecommend_type($set_exrecommend_souce);
        $this->pagedata['all_exrecommend_types'] = $exrecommend_type;
        

        $this->page('admin/logistics/exrecommend.html');
    }
    public function set_exrecommend(){
    	$this->begin();
    	$data['exrecommend_souce'] = $_POST['set']['exrecommend_souce'];#智能发货提供方
    	if(empty($data['exrecommend_souce'])){
    	    $this->end(false,app::get('ome')->_('请选择智选物流提供方'));
    	}
    	$data['op_user'] = kernel::single('desktop_user')->get_name();
    	$data['op_type'] = 2;#操作日志类型是设置物流策略
    	$data['create_time'] = time();

    	$data['op_content'] = serialize(array('exrecommend_type'=>$_POST['set']['set_exrecommend_type']));
    	$obj_exrecommend_set_logs = app::get('channel')->model('logistics_logs');

    	#开启或关闭快递鸟智选物流服务
    	$obj_exrecommend_set_logs->save($data);
    	
    	$value = $_POST['set']['set_exrecommend_service'];

    	$this->app->setConf('set_exrecommend_service',$value);
    	$this->app->setConf('set_exrecommend_souce','taobao');
    	
    	
    	$this->end(true,app::get('ome')->_('设置完成'));
    }
    #发货策略上传
    function template_upload(){
    	$this->pagedata['exrecommend_souce'] = $_POST['exrecommend_souce']?$_POST['exrecommend_souce']:'taobao';#智选物流提供方
    	if($this->pagedata['exrecommend_souce'] == 'taobao'){
    	    #菜鸟以电子面单来源做发货维度
    	    $taobao_channels = app::get('logisticsmanager')->model('channel')->get_taobao_channel();#获取系统中有效的淘宝电子面单来源
    	    $all_channel_ids = array();
    	    foreach($taobao_channels as $channels){
    	        $all_channel_ids[$channels['channel_id']] = $channels['channel_id'];
    	    }
    	    $this->pagedata['all_warehouse_id'] =  json_encode($all_channel_ids);
    	    $channel_nums = count($all_channel_ids);
    	    $this->pagedata['total_warehouse_amount'] = $channel_nums;#如果一个淘宝电子面单来源有没有，需要提醒客户使用面单来源
    	    $this->pagedata['info_fullfill'] = $channel_nums>0?true:false;
    	    
    	}
    	$this->page('admin/logistics/template_upload.html');
    }
    
    function ajax_upload_log(){
    	$data['op_user'] = kernel::single('desktop_user')->get_name();
    	$data['op_type'] = '1';#操作日志类型是同步模板
    	$data['create_time'] = time();
    	$data['exrecommend_souce'] = $_POST['exrecommend_souce']?$_POST['exrecommend_souce']:'taobao';
    	
    	$obj_logistics_logs = app::get('channel')->model('logistics_logs');
    	$obj_logistics_logs->save($data);
    	 
    	$obj_logistics_logs->update(array('status'=>''),array('op_type'=>3));#上传完毕，把模板变动的状态都清除
    }
    #获取智选物流提供方的智选策略类型
    public function ajax_get_exrecommend_type(){
        $souce_type = $_POST['souce_type'];
        $exrecommend_type = kernel::single('channel_func')->exrecommend_type($souce_type);
        echo json_encode($exrecommend_type);
    }
}