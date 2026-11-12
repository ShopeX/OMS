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
 * 报价系统授权管理
 *
 * @author wangbiao@shopex.cn
 * @version 2024.08.22
 */
class wmsmgr_ctl_admin_smart extends desktop_controller
{
    var $workground = "wms_manager";
    
    protected $_channel_type = 'smart';
    
    function index()
    {
        $params = array(
            'title' => '报价系统授权',
            'actions' => array(
                'add' => array(
                    'label'  => '添加报价系统授权',
                    'href'   => 'index.php?app=wmsmgr&ctl=admin_smart&act=add',
                    'target' => "dialog::{width:600,height:450,title:'添加报价系统授权'}",
                ),
            ),
            'base_filter' => array('channel_type'=>$this->_channel_type),
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            'use_buildin_recycle'    => false,
            'use_buildin_export'     => false,
            'use_buildin_import'     => false,
        );
        
        $this->finder('wmsmgr_mdl_smart', $params);
    }
    
    function add() {
        $this->_edit();
    }

    function edit($wms_id) {
        $this->_edit($wms_id);
    }

    private function _edit($wms_id = NULL)
    {
        if ($wms_id) {
            $smartMdl = $this->app->model('smart');
            $wms_detail = $smartMdl->dump($wms_id);
            
            $wms_detail['config'] = unserialize($wms_detail['config']);
            $wms_detail['adapter'] = kernel::single('wmsmgr_func')->getAdapterByChannelId($wms_id);
            $wms_detail['api_url'] = app::get('wmsmgr')->getConf('api_url' . $wms_detail['node_id']);
            
            $this->pagedata['wms'] = $wms_detail;
        }
        
        $adapter_list = kernel::single('wmsmgr_smart_config')->getAdapterList();
        $this->pagedata['adapter_list'] = $adapter_list;
        
        $this->display("add_smart.html");
    }

    /**
     * confightml
     * @param mixed $wms_id ID
     * @param mixed $adapter adapter
     * @return mixed 返回值
     */

    public function confightml($wms_id, $adapter)
    {
        switch ($adapter) {
            case 'openapiwms':
                if ($wms_id) {
                    $smartMdl = $this->app->model('smart');
                    $wms_detail = $smartMdl->dump($wms_id, 'node_type,node_id');
                    
                    $this->pagedata['config']['node_type'] = $wms_detail['node_type'];
                    $this->pagedata['wms_id'] = $wms_id;
                }
                break;
            default:
                # code...
                break;
        }
        
        //渠道
        $platform_list = kernel::single('wmsmgr_smart_config')->getPlatformList($adapter);
        
        $this->pagedata['platform_list'] = $platform_list;
        
        $this->display("smart/". $adapter .".html");
    }
    
    /**
     * platformconfig
     * @param mixed $wms_id ID
     * @param mixed $platform platform
     * @return mixed 返回值
     */
    public function platformconfig($wms_id, $platform)
    {
        $config = array('private_key'=>'', 'api_url'=>'');
        if ($wms_id) {
            $adapter = app::get('channel')->model('adapter')->dump(array('channel_id' => $wms_id));
            $config = @unserialize($adapter['config']);
        }
        
        //渠道
        $platform_params = kernel::single('wmsmgr_smart_config')->getPlatformParam($platform);
        
        $this->pagedata['config'] = $config;
        $this->pagedata['platform_params'] = $platform_params;
        $this->pagedata['platform'] = $platform;
        
        $this->display("smart/platformconfig.html");
    }

    function savesmart()
    {
        // 写实现唯一一份在 wmsmgr_capability_smart（Vue / legacy 两入口共用）；
        // legacy 事务由 begin()/end() 托管 ⇒ ownTransaction=false
        $url = 'index.php?app=wmsmgr&ctl=admin_smart&act=index';
        $this->begin($url);

        try {
            (new wmsmgr_capability_smart())->saveSmart($_POST, false);
        } catch (Throwable $exception) {
            $this->end(false, $exception->getMessage() !== '' ? $exception->getMessage() : '保存失败');
        }

        $this->end(true, app::get('base')->_('保存成功'));
    }
    
    /**
     * 删除Channel
     * @return mixed 返回值
     */
    public function deleteChannel()
    {
        $this->begin('index.php?app=wmsmgr&ctl=admin_smart&act=index');
        
        $obj_channel = kernel::single('channel_channel');
        $obj_branch = kernel::single('ome_branch');
        
        if (is_array($_POST['channel_id']) && count($_POST['channel_id']) > 0) {
            $filter['channel_id'] = $_POST['channel_id'];
        } elseif (isset($_POST['isSelectedAll']) && $_POST['isSelectedAll'] == '_ALL_') {
            $filter = $_POST;
            unset($filter['app']);
            unset($filter['ctl']);
            unset($filter['act']);
            unset($filter['flt']);
            unset($filter['_finder']);
        }
        
        //获取所有已经绑定的应用channel_id
        $_bind_info = $obj_channel->getList($filter, '*');
        $_branch_bind_wms_info = $obj_branch->getBindWmsBranchList();
        $bind_channel_id = array(); #所有已经绑定的channel_id
        $bind_wms_id = array();
        $channel_name = array();
        
        if (!empty($_bind_info)) {
            foreach ($_bind_info as $v) {
                $channel_name[$v['channel_id']] = $v['channel_name'];
                $channel_id[] = $v['channel_id'];

                #把已经存在的绑定节点存起来
                if (strlen($v['node_id']) > 0 && !in_array($v['node_type'], array('selfwms', 'mobile', 'webpos'))) {
                    $bind_channel_id[] = $v['channel_id'];
                }

                if (in_array($v['node_type'], array('mobile', 'webpos'))) {
                    $this->end(false, $this->app->_('虚拟门店仓储类型不允许被删除!'));
                    exit;
                }
            }
        }
        
        if (!empty($_branch_bind_wms_info)) {
            foreach ($_branch_bind_wms_info as $v) {
                if (in_array($v['wms_id'], $channel_id)) {
                    $this->end(false, $this->app->_('仓储:' . $channel_name[$v['wms_id']] . '，已经绑定了仓库，请先解除绑定!'));
                    exit;
                }
            }
        }
        
        //验证即将被删除的这条channel_id是否已经解除绑定
        foreach ($channel_id as $id) {
            $result = array_search($id, $bind_channel_id);
            if ($result !== false) {
                $this->end(false, $this->app->_('删除前，请解除绑定!'));
                exit;
            }
        }
        
        //验证完毕，开始删除
        if ($obj_channel->delete(array('channel_id' => $channel_id))) {
            $this->end(true, $this->app->_('删除成功'));
        } else {
            $this->end(false, $this->app->_('删除失败'));
        }
    }

    /**
     * 申请绑定关系
     * @param string $app_id
     * @param string $callback 异步返回地址
     * @param string $api_url API通信地址
     */
    function apply_bindrelation($app_id = 'ome', $callback = '', $api_url = '')
    {
        $this->Certi = base_certificate::get('certificate_id');
        $this->Token = base_certificate::get('token');
        $this->Node_id = base_shopnode::node_id($app_id);
        $token = $this->Token;
        $sess_id = kernel::single('base_session')->sess_id();
        $apply['certi_id'] = $this->Certi;
        if ($this->Node_id)
            $apply['node_id'] = $this->Node_id;
        $apply['sess_id'] = $sess_id;
        $apply['certi_ac'] = base_certificate::getCertiAC($apply);

        $params = array(
            'source'    => 'apply',
            'certi_id'  => $apply['certi_id'],
            'node_id'   => $apply['node_id'],
            'sess_id'   => $apply['sess_id'],
            'certi_ac'  => $apply['certi_ac'],
            'callback'  => $callback,
            'api_url'   => $api_url,
            'show_type' => 'wms',
        );

        $this->pagedata['license_iframe'] = sprintf('<iframe width="100%%" frameborder="0" height="99%%" id="iframe" onload="this.height=document.documentElement.clientHeight-4" src="%s" ></iframe>', MATRIX_RELATION_URL . '?' . http_build_query($params));
        // $this->pagedata['license_iframe'] = '<iframe width="100%" frameborder="0" height="99%" id="iframe" onload="this.height=document.documentElement.clientHeight-4" src="' . MATRIX_RELATION_URL . '?source=apply&certi_id='.$apply['certi_id'].'&node_id=' . $apply['node_id'] . '&sess_id='.$apply['sess_id'].'&certi_ac='.$apply['certi_ac'].'&callback=' . $callback . '&api_url=' . $api_url .'" ></iframe>';

        $this->display('bindRelation.html');
    }

    /*
     * 查看绑定关系
     */
    function view_bindrelation()
    {
        $this->Certi = base_certificate::get('certificate_id');
        $this->Token = base_certificate::get('token');
        $this->Node_id = base_shopnode::node_id('ome');
        $token = $this->Token;
        $sess_id = kernel::single('base_session')->sess_id();
        $apply['certi_id'] = $this->Certi;
        $apply['node_id'] = $this->Node_id;
        $apply['sess_id'] = $sess_id;
        $apply['certi_ac'] = base_certificate::getCertiAC($apply);
        $callback = urlencode(kernel::openapi_url('openapi.channel', 'bindCallback'));
        $api_url = kernel::base_url(true) . kernel::url_prefix() . '/api';
        $api_url = urlencode($api_url);
        $op_id = kernel::single('desktop_user')->get_login_name();
        $op_user = kernel::single('desktop_user')->get_name();
        // $params = '&op_id='.$op_id.'&op_user='.$op_user;

        $params = array(
            'op_id'     => $op_id,
            'op_user'   => $op_user,
            'source'    => 'accept',
            'certi_id'  => $apply['certi_id'],
            'node_id'   => $this->Node_id,
            'sess_id'   => $apply['sess_id'],
            'certi_ac'  => $apply['certi_ac'],
            'callback'  => $callback,
            'api_url'   => $api_url,
            'show_type' => 'wms',
        );
        
        echo sprintf('<title>查看绑定关系</title><iframe width="100%%" height="95%%" frameborder="0" src="%s" ></iframe>', MATRIX_RELATION_URL . '?' . http_build_query($params));
    }
    
    /**
     * undocumented function
     * 
     * @return void
     * @author
     * */
    public function gen_private_key()
    {
        echo md5(uniqid());
        exit;
    }
    
    /**
     * 绑定店铺
     * 
     * @return void
     * @author 
     */
    public function bind_page($channel_id)
    {
        $this->pagedata['channel_id'] = $channel_id;

        $filter = array('filter_sql'=>' node_id != "" AND node_id IS NOT NULL');

        $wms = $this->app->model("wms")->db_dump($channel_id,'config');
        $wms['config'] = @unserialize($wms['config']);

        if (in_array($wms['config']['node_type'],array('bms','bim'))) {
            $filter['node_type'] = 'taobao';
        }

        if (in_array($wms['config']['node_type'],array('yph'))){
            $filter['node_type'] = '360buy';
        }
        $this->pagedata['filter'] = $filter;

        $this->display('bind/shop.html');
    }

    /**
     * 仓储绑定店铺
     * 
     * @return void
     * @author 
     */
    public function bind_shop()
    {
        $channel_id = $_POST['channel_id']; $shop_id = $_POST['shop_id'];

        $wmsModel = $this->app->model('smart');
        $wms = $wmsModel->db_dump(array('channel_id'=>$channel_id,'channel_type'=>$this->_channel_type));
        $wms['config'] = @unserialize($wms['config']);

        if (!$wms) parent::splash('error',null,'仓储不存在');

        if ($wms['node_id']) parent::splash('error',null,'仓储已经绑定');

        if (!$wms['config']['node_type'] || $wms['config']['node_type'] == 'other') parent::splash('error',null,'仓储不支持绑定店铺');

        $shop = app::get('ome')->model('shop')->dump($shop_id,'node_id,node_type');
        if (!$shop['node_id']) parent::splash('error',null,'店铺未绑定');

        $addon = array('node_id'=>$shop['node_id']);
        $node_id = sprintf('o%u', crc32(utils::array_md5($wms['config']) . kernel::base_url()));;
        $wmsModel->update(array('node_id'=>$node_id,'node_type'=>$wms['config']['node_type'],'addon'=>$addon),array('channel_id'=>$channel_id));

        parent::splash('success',null,'绑定成功');
    }

    /**
     * bindNodeId
     * @param mixed $channelId ID
     * @return mixed 返回值
     */
    public function bindNodeId($channelId) {
        $this->pagedata['channel_id'] = (int) $channelId;
        $this->display('bind/node_id.html');
    }

    /**
     * 设置NodeId
     * @return mixed 返回操作结果
     */
    public function setNodeId() {
        $channel_id = trim($_POST['channel_id']);
        $node_id = trim($_POST['node_id']);
        
        $wmsModel = $this->app->model('smart');
        $wms = $wmsModel->db_dump(array('channel_id'=>$channel_id,'channel_type'=>$this->_channel_type));
        $wms['config'] = @unserialize($wms['config']);

        if (!$wms) parent::splash('error',null,'仓储不存在');

        if ($wms['node_id']) parent::splash('error',null,'仓储已经绑定');

        if (!$wms['config']['node_type'] || $wms['config']['node_type'] == 'other') parent::splash('error',null,'仓储不支持填写node_id');

        $wmsModel->update(array('node_id'=>$node_id,'node_type'=>$wms['config']['node_type']),array('channel_id'=>$channel_id));

        parent::splash('success',null,'绑定成功');
    }
    
    /**
     * 仓储解绑店铺
     * 
     * @param Int $channel_id 仓库ID
     * @return void
     * @author 
     */
    public function unbind_shop($channel_id)
    {
        $affect_row = $this->app->model('smart')->update(array('node_id'=>null,'node_type'=>null), array('channel_id'=>$channel_id,'channel_type'=>$this->_channel_type));
        $affect_row && is_numeric($affect_row) ? parent::splash('success','index.php?app=wmsmgr&ctl=admin_smart&act=index','解绑成功') : parent::splash('error',null,'解绑失败');
    }
    
    /**
     * 检查是否允许解除绑定关系
     */
    public function unbind_verify($channel_id)
    {
        if (empty($channel_id)) {
            die("单据号传递错误！");
        }
        
        $wmsObj = $this->app->model('smart');
        $wmsInfo = $wmsObj->db_dump(array('channel_id'=>$channel_id, 'channel_type'=>$this->_channel_type), '*');
        if(empty($wmsInfo)){
            die("WMS仓储信息不存在！");
        }
        
        $this->pagedata['channel_id'] = $channel_id;
        $this->pagedata['wmsInfo'] = $wmsInfo;
        $this->display('bind/unbind_verify.html');
    }
    
    /**
     * 最终解除绑定关系
     */
    public function finish_unbind($channel_id)
    {
        $result = array('rsp'=>'fail', 'error_msg'=>'');
        
        //info
        $wmsObj = $this->app->model('smart');
        $wmsInfo = $wmsObj->db_dump(array('channel_id'=>$channel_id, 'channel_type'=>$this->_channel_type), '*');
        if(empty($wmsInfo)){
            $result['error_msg'] = 'WMS仓储信息不存在';
            echo json_encode($result);
            exit;
        }
        
        //解除绑定
        $affect_row = $wmsObj->update(array('node_id'=>null, 'node_type'=>null) ,array('channel_id'=>$channel_id,'channel_type'=>$this->_channel_type));
        if($affect_row && is_numeric($affect_row)){
            $result['rsp'] = 'succ';
            echo json_encode($result);
            exit;
        }else{
            $result['error_msg'] = '解除绑定失败';
            echo json_encode($result);
            exit;
        }
    }
}
