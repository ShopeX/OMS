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

class ome_ctl_admin_api_log extends desktop_controller{
    var $workground = "setting_tools";

    function running(){
        $this->index('running');
    }

    function success(){
        $this->index('success');
    }

    function sending(){
        $this->index('sending');
    }

    function requestfail(){
        $this->index('fail','request');
    }

    function responsefail(){
        $this->index('fail','response');
    }

    function index($status='all', $api_type='request')
    {
        $base_filter = array();
        $orderby = ' createtime desc ';
        switch($status){
            case 'all':
                $this->title = '所有同步日志';
                break;
            case 'running':
                $this->title = '同步运行中日志';
                $base_filter = array('status'=>'running');
                break;
            case 'success':
                $this->title = '同步成功日志';
                $base_filter = array('status'=>'success');
                break;
            case 'fail':
                $this->title = '同步失败日志';
                $base_filter = array('status'=>'fail', 'api_type'=>$api_type);
                break;
            case 'sending':
                $this->title = '发起中日志';
                $base_filter = array('status'=>'sending');
                break;
        }

        if ($status=='fail' && $api_type=='request'){
            $actions =
              array(
                 array(
                   'label' => '批量重试',
                   'submit' => 'index.php?app=ome&ctl=admin_api_log&act=batch_retry&finder_id='.$_GET['finder_id'],
                   'target' => "dialog::{width:550,height:300,title:'批量重试'}",
                 ),
              );
        }
        $params = array(
            'title'=>$this->title.'，<font class="c-red">默认显示7天内日志</font>',
            'actions'=> $actions,
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'=>false,
            'use_buildin_recycle'=>false,
            'use_buildin_export'=>false,
            'use_buildin_import'=>false,
            'use_buildin_filter'=>true,
            'orderBy' => $orderby,
        );

        // $base_filter['original_bn'] = $_POST['original_bn'] ?: 'None';
        
        //加入同步日志时间范围
        if(!$_POST['createtime'])
        {
            // $time_from = strtotime($_POST['createtime_from']);
            // $filter = array('createtime|than' => strtotime('-7 days'));
            
            $base_filter['createtime|than'] = strtotime('-7 days');
        }
        
        //默认POST查询7天内的日志
        // $this->pagedata['startTime'] = strtotime('-7 days');
        
        if($base_filter){
            $params['base_filter'] = $base_filter;
        }
        
        # 在列表上方添加搜索
        // if(!isset($_GET['action'])) {
        //     $panel = new desktop_panel($this);
        //     $panel->setId('api_log_finder_top');
        //     $panel->setTmpl('admin/finder/finder_panel_filter.html');
        //     $panel->show('ome_mdl_api_log', $params);
        // }
        
        $this->finder('ome_mdl_api_log',$params);
    }

    /*
     * 重试同步日志
     * @param int or array $log_id 待重试的日志ID
     * @param string $retry_type 重试方式，默认为单个重试，batch:为批量重试
     */
    function retry($log_id='', $retry_type='single'){
        if ($retry_type == 'single'){
            $this->pagedata['log_id'] = $log_id;
        }else{
            if (is_array($log_id['log_id'])){
                $this->pagedata['log_id'] = implode("|", $log_id['log_id']);
            }
        }
        $this->pagedata['isSelectedAll'] = $log_id['isSelectedAll'];
        $this->pagedata['retry_type'] = $retry_type;
        $this->display("admin/api/retry.html");
    }

    function retry_do(){
        $log_id = urldecode($_GET['log_id']);
        $retry_type = $_GET['retry_type'];
        $isSelectedAll = $_GET['isSelectedAll'];
        $cursor = $_GET['cursor'];
        // CNVD api_log retry: harden log_id before model layer (segments alnum/_/- only).
        if ($isSelectedAll !== '_ALL_') {
            $log_id = (string) $log_id;
            $segments = ($retry_type === 'batch' && strpos($log_id, '|') !== false)
                ? explode('|', $log_id)
                : array($log_id);
            foreach ($segments as $segment) {
                if ($segment === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $segment)) {
                    echo json_encode(array('task_name' => 'invalid log_id', 'status' => 'fail'));
                    exit;
                }
            }
        }
        $return = $this->app->model('api_log')->retry($log_id, $retry_type, $isSelectedAll, $cursor);
        echo json_encode($return);
        exit;
    }

    function batch_retry(){
        $this->retry($_POST, 'batch');
    }

    function show($log_id){
        if(empty($log_id)){
            die('无效操作！');
        }
        
        $apilog = app::get('ome')->model("api_log")->dump($log_id);
        
        // 使用封装的API日志数据处理方法
        $apilog = kernel::single('ome_api_func')->processApiLogData($apilog);

        // 生成唯一的DOM ID
        $dom_id = 'apilog_' . $log_id . '_' . time() . '_' . mt_rand(1000, 9999);
        $this->pagedata['apilog'] = $apilog;
        $this->pagedata['dom_id'] = $dom_id;

        $this->display("admin/api/detail.html");
    }
}
?>
