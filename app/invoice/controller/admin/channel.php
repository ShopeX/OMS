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

class invoice_ctl_admin_channel extends desktop_controller
{
    public function __construct($app)
    {
        parent::__construct($app);
    }

    public function index()
    {
        $this->title    = '电子发票渠道列表';
        $params  = array(
           'title'=>$this->title,
           'use_buildin_import'=>false,
           'use_buildin_recycle'=>false,
           'actions' =>array(
                array(
                  'label'=>'添加',
                  'href'=>'index.php?app=invoice&ctl=admin_channel&act=add',
                  'target' => 'dialog::{width:620,height:460,title:\'添加来源\'}',
                ),
           )
        );
        
        $this->finder('invoice_mdl_channel', $params);
    }

    public function add(){
       $this->_edit('add','');
    }

    public function edit($channel_id){
       $this->_edit('edit',$channel_id);
    }

    public function _edit($action='add',$channel_id)
    {
        //来源类型信息
        $funcObj = kernel::single('invoice_func');
        $this->pagedata['channels'] = $funcObj->channels('');
        
        if ($channel_id) {
            $channel = app::get('invoice')->model('channel')->db_dump($channel_id);
            $channel['extend_data'] = @json_decode($channel['extend_data'], true);
         $this->pagedata['channel'] = $channel;
        }
        
        $this->display("admin/channel.html");
    }

    /**
     * 加载对应平台配置
     * 
     * @param string $channel_type 类型
     * @param int $channel_id 类型ID
     * @return void
     * @author 
     */
    public function getAuthConfigs($channel_type, $channel_id = 0)
    {
        $authObj = kernel::single('invoice_auth_' . $channel_type);
        
        $configs = $authObj->getAuthConfigs();
        
        if ($channel_id) {
          $channel = app::get('invoice')->model('channel')->db_dump($channel_id,'extend_data,shop_id');
        
          $extend_data = @json_decode($channel['extend_data'], true);
          $extend_data['shop_id'] = $channel['shop_id'];
        
          if ($extend_data) {
            foreach ((array) $configs as $key => $value) {
                if (isset($extend_data[$key])) $configs[$key]['value'] = $extend_data[$key];
            }
          }
        }
        
        $ui = new base_component_ui($this);
        foreach ((array) $configs as $key => $value) {
            $configs[$key]['inputer'] = $ui->input($value);
        }
        
        $this->pagedata['configs'] = $configs;
        
        $this->display('admin/auth/config.html');
    }
    
    public function delChannel()
    {
        $this->begin('index.php?app=invoice&ctl=admin_channel&act=index');  
        $obj_order_setting     = kernel::single('invoice_mdl_order_setting');
        $obj_channel    = kernel::single('invoice_mdl_channel');
        
        $isSelectedAll = $_POST['isSelectedAll'];
        $channel_ids = $_POST['channel_id'];
        
        if($isSelectedAll != '_ALL_' && $channel_ids){
          $basefilter = array('channel_id'=>$channel_ids);
        }else{
          $basefilter = array();
        }
        $all_channel_ids = $obj_channel->getList('channel_id',$basefilter);
        $channel_ids = array_map('current', $all_channel_ids);
        
        foreach($channel_ids as  $channel_id){
          $rs = $obj_order_setting->getList('*',array('channel_id'=>$channel_id));
          if(!empty($rs)){
             $this->end(false,'渠道已被使用,不能关闭！');
          }
        }
        
        foreach($channel_ids as $id){
          $obj_channel->update(array('status'=>'false'),array('channel_id'=>$id));
        }
        
        $this->end(true, $this->app->_('关闭成功'));
    }
    
    function do_save()
    {
        $this->begin('index.php?app=invoice&ctl=admin_channel&act=index');
        $channelMdl = app::get('invoice')->model('channel');
        
        // 获取已经存在的渠道信息
        $channelInfo = [];
        if($_POST['channel_id']){
            $channelInfo = $channelMdl->db_dump($_POST['channel_id']);
        }
        
        $data = array();
        $data['channel_id']   = $_POST['channel_id'];
        $data['name']         = $_POST['name'];
        if ($_POST['channel_type']) $data['golden_tax_version'] = $_POST['golden_tax_version'];
        $data['extend_data']  = json_encode($_POST['extend_data']);
        
        if (!$data['channel_id']) $data['create_time'] = time();
        if ($_POST['channel_type']) $data['channel_type'] = $_POST['channel_type'];
        if ($_POST['shop_id']) $data['shop_id'] = $_POST['shop_id'];
        
        if ($data['shop_id'] && $channelMdl->dump(array('shop_id'=>$data['shop_id'],'channel_id|noequal'=>$data['channel_id']))) {
           $this->end(false,'店铺已经存在');
        }

        if ($_POST['shop_id']) {
            $shopInfo          = app::get('ome')->model('shop')->db_dump(['shop_id' => $_POST['shop_id']]);
            if ($shopInfo['shop_type'] == 'taobao') {
                $data['node_id']   = $shopInfo['node_id'];
                $data['node_type'] = $shopInfo['node_type'];
            }
        }
        
        // 票易通
        if ($data['channel_type'] == 'piaoyitong' || $channelInfo['channel_type'] == 'piaoyitong') {
            $data['golden_tax_version'] = 1;
            $data['node_type'] = ($data['channel_type'] ? $data['channel_type'] : $channelInfo['channel_type']);
            $data['node_id'] = date('Ymd');
        }
        
        // save
        if (!$channelMdl->save($data)) {
           $this->end(false,'保存失败');
        }
        
        if (!defined('DEV_ENV') || (defined('DEV_ENV') && 'sandbox' == constant("DEV_ENV")) ) {}
        $channel = $channelMdl->db_dump($data['channel_id']);
        // 缺失节点,进行绑定
        if(!$channel['node_id']){
            $force = true;
            $needResponse = true;
            $result = kernel::single('erpapi_router_request')->set('bind', $channel['channel_type'])->bind_bind($_POST['extend_data'], $force, $needResponse);

            if (!$result) {
                $this->end(false, '绑定失败');
            }

            // 如果实际发起请求,则处理回写
            if (is_array($result)) {
                list($rs, $errmsg) = $channelMdl->bindChannelCallback($data, $result);

                if (!$rs) {
                    $this->end(false, '绑定失败:' . $errmsg);
                }
            }
        } else {
            if ($channel['channel_type'] == 'piaoyitong' && $channelMdl->dump(array(
                'channel_type' => 'piaoyitong',
                'channel_id|noequal' => $channel['channel_id'],
                'node_id|noequal' => '',
            ))) {
                $this->end(false, '票易通渠道已存在，不能重复添加');
            }
        }
        
        $this->end(true, '新建成功');
    }

    //开启或关闭状态切换
    public function status($status,$channel_id = null)
    {
        $this->begin('index.php?app=invoice&ctl=admin_channel&act=index');
        $obj_channel = app::get('invoice')->model('channel');
        $obj_order_setting     = kernel::single('invoice_mdl_order_setting');
        if(empty($channel_id)){
            $this->end(false,$this->app->_('设置失败'));
        }
        $filter = array('channel_id'=>$channel_id);
        $all_channel_ids = $obj_channel->getList('channel_id',$filter);
        $channel_ids = array_map('current', $all_channel_ids);
        $rs = $obj_order_setting->getList('*',array('channel_id'=>$channel_ids));
        if(!empty($rs)){
            $this->end(false,'该渠道已被使用，不能关闭！');
        }
        
        $data['status'] = $status;
        $obj_channel->update($data,$filter);
        
        $this->end(true,$this->app->_('设置成功'));
    }

    public function unbind($channel_id)
    {
        $channelMdl = app::get('invoice')->model('channel');
        $channel = $channelMdl->db_dump($channel_id);
        if (!$channel) {
            $this->splash('error', $this->url, '渠道不存在');
        }

        if (!$channel['node_id']) {
            $this->splash('error', $this->url, '渠道未绑定');
        }
        if ($channel['channel_type'] == 'piaoyitong') {
            $result = true;
        //不是淘宝开票渠道的才需要请求解绑
        } elseif ($channel['channel_type'] != 'taobao') {
            $result = kernel::single('erpapi_router_request')
                ->set('bind', $channel['channel_type'])
                ->bind_unbind([
                    'to_node' => $channel['node_id'],
                    'node_type' => $channel['node_type'],
                    'title' => $channel['name'],
                ]);
        }else{
            $result = true;
        }

        if ($result){
            $channelMdl->update(['node_id'=>'', 'status'=>'false'], ['channel_id'=>$channel['channel_id']]);
            $this->splash('success', $this->url, '解绑成功');
        } else {
            $this->splash('error', $this->url, '解绑失败');
        }
    }
}
