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

class wmsmgr_ctl_admin_wms extends desktop_controller {

    var $workground = "wms_manager";

    function index() {
        $params = array(
            'title'                  => 'WMS管理',
            'actions'                => array(
                'add' => array(
                    'label'  => '添加WMS',
                    'href'   => 'index.php?app=wmsmgr&ctl=admin_wms&act=add',
                    'target' => "dialog::{width:670,height:380,title:'第三方仓储'}",
                ),
                array('label' => '查看绑定关系', 'href' => 'index.php?app=wmsmgr&ctl=admin_wms&act=view_bindrelation', 'target' => '_blank'),
                array('label' => app::get('channel')->_('删除'), 'confirm' => '确定删除选中项？', 'submit' => 'index.php?app=wmsmgr&ctl=admin_wms&act=deleteChannel',),
            ),
            'base_filter'            => array('channel_type' => 'wms'),
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            'use_buildin_recycle'    => false,
            'use_buildin_export'     => false,
            'use_buildin_import'     => false,
        );
        $this->finder('wmsmgr_mdl_wms', $params);
    }


    function add() {
        $this->_edit();
    }

    function edit($wms_id) {
        $this->_edit($wms_id);
    }

    private function _edit($wms_id = NULL) {
        if ($wms_id) {
            $oWms = $this->app->model('wms');
            $wms_detail = $oWms->dump($wms_id);
            $wms_detail['config'] = unserialize($wms_detail['config']);
            $wms_detail['adapter'] = kernel::single('wmsmgr_func')->getAdapterByChannelId($wms_id);
            $wms_detail['api_url'] = app::get('wmsmgr')->getConf('api_url' . $wms_detail['node_id']);
            app::get('wms')->setConf('wms_storage_division_code_' . $wms['channel_id'], $_POST['divisionCode']);
            $this->pagedata['wms'] = $wms_detail;
        }

        #适配器列表
        // $adapter_list = kernel::single('wmsmgr_func')->getWmsAdapterList();

        // $this->pagedata['adapter_list'] = $adapter_list;
        // $this->pagedata['adapter_list_json'] = json_encode($adapter_list);

        $adapter_list = kernel::single('wmsmgr_auth_config')->getAdapterList();
        $this->pagedata['adapter_list'] = $adapter_list;

        $this->display("add_wms.html");
    }

    /**
     * confightml
     * @param mixed $wms_id ID
     * @param mixed $adapter adapter
     * @return mixed 返回值
     */
    public function confightml($wms_id, $adapter) {
        switch ($adapter) {
            case 'openapiwms':
                if ($wms_id) {
                    $oWms = $this->app->model('wms');
                    $wms_detail = $oWms->dump($wms_id, 'node_type,node_id');
                    $this->pagedata['config']['node_type'] = $wms_detail['node_type'];
                    $this->pagedata['wms_id'] = $wms_id;
                }
                break;
            case 'matrixwms':
                if ($wms_id){
                    $oWms = $this->app->model('wms');
                    $wms_detail = $oWms->db_dump($wms_id,'node_type,node_id,config');
                    $this->pagedata['config'] = @unserialize($wms_detail['config']);
                    $this->pagedata['wms_id'] = $wms_id;
                }
                break;
            case 'ilcwms':
                if ($wms_id) {
                    $oWms = $this->app->model('wms');
                    $wms_detail = $oWms->dump($wms_id, 'node_type,node_id');
                    // $this->pagedata['config']['node_id'] = $wms_detail['node_id'];
                    // $this->pagedata['wms_id'] = $wms_id;
                    $channel_adapter = app::get('channel')->model('adapter')->dump(array('channel_id' => $wms_id));
                    $config = @unserialize($channel_adapter['config']);

                    if (!$config['node_id'])
                        $config['node_id'] = $wms_detail['node_id'];
                    if (!$config['url'])
                        $config['url'] = app::get('wmsmgr')->getConf('api_url' . $wms_detail['node_id']);

                    $this->pagedata['config'] = $config;
                }
                break;
            case 'mixturewms':
                if ($wms_id) {
                    $oWms = $this->app->model('wms');
                    $wms_detail = $oWms->dump($wms_id, 'node_type,node_id,shipper');

                    $channel_adapter = app::get('channel')->model('adapter')->dump(array('channel_id' => $wms_id));
                    $config = @unserialize($channel_adapter['config']);


                    if (!$config['url'])
                        $config['url'] = app::get('wmsmgr')->getConf('api_url' . $wms_detail['node_id']);
                    $this->pagedata['shipper'] = $wms_detail['shipper'];
                    $this->pagedata['config'] = $config;
                }
                break;
            default:
                # code...
                break;
        }


        $platform_list = kernel::single('wmsmgr_auth_config')->getPlatformList($adapter);

        $this->pagedata['platform_list'] = $platform_list;
        $this->display('auth/' . $adapter . '.html');
    }


    /**
     * platformconfig
     * @param mixed $wms_id ID
     * @param mixed $platform platform
     * @return mixed 返回值
     */
    public function platformconfig($wms_id, $platform) {
        if ($wms_id) {
            $adapter = app::get('channel')->model('adapter')->dump(array('channel_id' => $wms_id));
            $config = @unserialize($adapter['config']);
            if ($platform == $config['node_type'])
                $this->pagedata['config'] = $config;
        }

        $platform_params = kernel::single('wmsmgr_auth_config')->getPlatformParam($platform);

        $this->pagedata['platform_params'] = $platform_params;
        $this->pagedata['platform'] = $platform;

        $this->display('auth/platformconfig.html');
    }

    function saveWms() {
        $url = 'index.php?app=wmsmgr&ctl=admin_wms&act=index';
        $this->begin($url);

        // legacy 事务由 begin()/end() 托管 ⇒ capability ownTransaction=false
        $input = array(
            'wms'          => isset($_POST['wms']) ? $_POST['wms'] : array(),
            'config'       => isset($_POST['config']) ? $_POST['config'] : array(),
            'adapter'      => isset($_POST['adapter']) ? $_POST['adapter'] : '',
            'shipper'      => isset($_POST['shipper']) ? $_POST['shipper'] : '',
            'divisionCode' => isset($_POST['divisionCode']) ? $_POST['divisionCode'] : '',
        );

        try {
            kernel::single('wmsmgr_capability_wms')->saveWms($input, false);
        } catch (Throwable $exception) {
            $this->end(false, $exception->getMessage() !== '' ? $exception->getMessage() : '保存失败');
            return;
        }

        $this->end(true, app::get('base')->_('保存成功'));
    }

    /**
     * 删除Channel
     * @return mixed 返回值
     */
    public function deleteChannel() {
        $this->begin('index.php?app=wmsmgr&ctl=admin_wms&act=index');

        // legacy 事务由 begin()/end() 托管 ⇒ capability ownTransaction=false
        $input = array(
            'channel_id'     => isset($_POST['channel_id']) ? $_POST['channel_id'] : null,
            'selection_mode' => (isset($_POST['isSelectedAll']) && $_POST['isSelectedAll'] == '_ALL_') ? '_ALL_' : '',
            'filters'        => $_POST,
        );

        try {
            kernel::single('wmsmgr_capability_wms')->deleteChannel($input, false);
        } catch (Throwable $exception) {
            $this->end(false, $exception->getMessage() !== '' ? $exception->getMessage() : '删除失败');
            return;
        }

        $this->end(true, $this->app->_('删除成功'));
    }

    /**
     * 申请绑定关系
     * @param string $app_id
     * @param string $callback 异步返回地址
     * @param string $api_url API通信地址
     */
    function apply_bindrelation($app_id = 'ome', $callback = '', $api_url = '') {
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
    function view_bindrelation() {

        $this->Certi = base_certificate::get('certificate_id');
        $this->Token = base_certificate::get('token');
        //$this->Token = base_shopnode::get('token','ome');
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

        // echo '<title>查看绑定关系</title><iframe width="100%" height="95%" frameborder="0" src='.MATRIX_RELATION_URL.'?source=accept&certi_id='.$apply['certi_id'].'&node_id=' . $this->Node_id . '&sess_id='.$apply['sess_id'].'&certi_ac='.$apply['certi_ac'].'&callback='.$callback.'&api_url='.$api_url.$params.' ></iframe>';

        echo sprintf('<title>查看绑定关系</title><iframe width="100%%" height="95%%" frameborder="0" src="%s" ></iframe>', MATRIX_RELATION_URL . '?' . http_build_query($params));
    }

    /**
     * 配置物流公司
     * @param string $wms_id
     */
    public function exitExpress($wms_id = '') {
        $express_relation_mdl = $this->app->model('express_relation');
        if ($_POST) {
            $this->begin();
            $wms_id = $_POST['wms_id'];
            $wms_express_bn = $_POST['wms_express_bn'];
            foreach ($wms_express_bn as $k => $v) {
                $sdata = array('wms_id' => $wms_id, 'sys_express_bn' => $k, 'wms_express_bn' => $v);
                $rs = $express_relation_mdl->save($sdata);
            }
            $this->end(true, app::get('base')->_($rs ? '保存成功' : '保存失败'));
        } else {
            $dly_corp_mdl = app::get('ome')->model('dly_corp');
            $sys_express_corp = $dly_corp_mdl->getlist('*');
            $wms = $express_relation_mdl->getlist('*', array('wms_id' => $wms_id));
            foreach ($wms as $v) {
                $wmsBn[$v['sys_express_bn']] = $v['wms_express_bn'];
            }
            $this->pagedata['sys_express_corp'] = $sys_express_corp;
            $this->pagedata['wms_id'] = $wms_id;
            $this->pagedata['wmsBn'] = $wmsBn;
            $this->display("exitExpress.html");
        }
    }

    /**
     * undocumented function
     * 
     * @return void
     * @author
     * */
    public function gen_private_key() {
        echo md5(uniqid());
        exit;
    }

    public function syncBase($wmsId) {
        $baseData = array();
        $shop = app::get('ome')->model('shop')->getList('shop_id,name', array());
        foreach($shop as $k => $val) {
            $baseData[] = array(
                'id' => $val['shop_id'],
                'type' => 'shop',
                'name' => '店铺-' . $val['name']
            );
        }
        $baseData[] = array(
            'id' => '-1',
            'type' => 'shop',
            'name' => '没有店铺-noshopcode'
        );
        $corp = app::get('ome')->model('dly_corp')->getList('corp_id,name', array());
        foreach($corp as $val) {
            $baseData[] = array(
                'id' => $val['corp_id'],
                'type' => 'corp',
                'name' => '物流-'. $val['name']
            );
        }
        $baseData[] = array(
            'id' => '-1',
            'type' => 'corp',
            'name' => '未选物流-other'
        );
        $supplier = app::get('purchase')->model('supplier')->getList('supplier_id,name', array());
        foreach($supplier as $val) {
            $baseData[] = array(
                'id' => $val['supplier_id'],
                'type' => 'supplier',
                'name' => '供应商-' . $val['name']
            );
        }
        $baseData[] = array(
            'id' => '-1',
            'type' => 'supplier',
            'name' => '没有供应商-nosuppliercode'
        );
        $this->pagedata['wms_id'] = $wmsId;
        $this->pagedata['base_data'] = $baseData;
        $this->page('base_sync.html');
    }

        /**
     * doSyncBase
     * @return mixed 返回值
     */
    public function doSyncBase() {
        $wmsId = (int) $_POST['wms_id'];
        $type = $_POST['type'];
        $id = $_POST['id'];
        if($type == 'shop') {

            $rs = kernel::single('ome_event_trigger_shop')->syncShop($id, $wmsId);
        } elseif($type == 'corp') {

            $rs = kernel::single('ome_event_trigger_logistics')->add($id, $wmsId);
        } elseif($type == 'supplier') {

            $rs = kernel::single('ome_event_trigger_supplier')->syncSupplier($id, $wmsId);
        } else {
            $rs = array('rsp' => 'fail', 'msg' => '没有该类型');
        }
        echo $rs['rsp'] == 'succ' ? 'ok.' : 'Error: '.($rs['err_msg']?$rs['err_msg']:$rs['msg']);exit;
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

        $wmsModel = $this->app->model('wms');
        $wms = $wmsModel->db_dump(array('channel_id'=>$channel_id,'channel_type'=>'wms'));
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
        
        $wmsModel = $this->app->model('wms');
        $wms = $wmsModel->db_dump(array('channel_id'=>$channel_id,'channel_type'=>'wms'));
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
        $affect_row = $this->app->model('wms')->update(array('node_id'=>null,'node_type'=>null),array('channel_id'=>$channel_id,'channel_type'=>'wms'));
        $affect_row && is_numeric($affect_row) ? parent::splash('success','index.php?app=wmsmgr&ctl=admin_wms&act=index','解绑成功') : parent::splash('error',null,'解绑失败');
    }
    
    /**
     * yjdf初始化物料
     * @param $channel_id
     */
    public function yjdf_sync_material($channel_id){
        
        $this->pagedata['channel_id'] = $channel_id;
        $this->pagedata['pre_time'] = strtotime('-1 month');
        $this->pagedata['now_time'] = time();
        $this->display('sync_material.html');
    }
    /**
     * 初始化商品处理逻辑
     * 
     * @return void
     * @author
     * */
    public function ajaxSyncMaterial()
    {
        $retArr = array(
            'itotal'            => 0,
            'isucc'             => 0,
            'ifail'             => 0,
            'add_foreign_succ'  => 0,
            'add_foreign_error' => 0,
            'total'             => 0,
            'err_msg'           => array(),
        );

        $rs = kernel::single('wmsmgr_sync_material')->syncMaterial($_POST);
        
        if ($rs['rsp'] == 'succ') {
            $retArr['itotal']            += ($rs['succ'] + $rs['fail']);
            $retArr['ifail']             += $rs['material_add_error'];
            $retArr['add_foreign_succ']  += $rs['add_foreign_succ'];
            $retArr['add_foreign_error'] += $rs['add_foreign_error'];
            if (!$rs['scrollId']) {
                $retArr['total']             = $rs['data']['total'];
            }else{
                $retArr['total']             = $rs['total'];
            }
            $retArr['scrollId']          = $rs['scrollId'];
        } else {
            $retArr['err_msg'] = [$rs['err_msg']];
        }

        echo json_encode($retArr);exit;
    }
    
    /**
     * 检查是否允许解除绑定关系
     */
    public function unbind_verify($channel_id)
    {
        if (empty($channel_id)) {
            die("单据号传递错误！");
        }
        
        $wmsObj = $this->app->model('wms');
        $wmsInfo = $wmsObj->db_dump(array('channel_id'=>$channel_id, 'channel_type'=>'wms'), '*');
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
        $wmsObj = $this->app->model('wms');
        $wmsInfo = $wmsObj->db_dump(array('channel_id'=>$channel_id, 'channel_type'=>'wms'), '*');
        if(empty($wmsInfo)){
            $result['error_msg'] = 'WMS仓储信息不存在';
            echo json_encode($result);
            exit;
        }
        
        //解除绑定
        $affect_row = $wmsObj->update(array('node_id'=>null, 'node_type'=>null) ,array('channel_id'=>$channel_id,'channel_type'=>'wms'));
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
    
    /**
     * 初始化京东物流公司映射关系
     * @param int $wms_id
     */
    public function initializeLogi($wms_id)
    {
        $wmsmgrObj = app::get('wmsmgr')->model('wms');
        $wmsInfo = $wmsmgrObj->dump($wms_id);
        if(empty($wmsInfo)){
            die('empty wms');
        }
        
        $wmsInfo['config'] = unserialize($wmsInfo['config']);
        $wmsInfo['adapter'] = kernel::single('wmsmgr_func')->getAdapterByChannelId($wms_id);
        $wmsInfo['api_url'] = app::get('wmsmgr')->getConf('api_url' . $wmsInfo['node_id']);
        
        $this->pagedata['wms'] = $wmsInfo;
        
        $this->display('initialize_logi.html');
    }
    
    /**
     * 初始化京东物流公司映射关系
     */
    public function doInitializeLogi()
    {
        $this->begin('index.php?app=wmsmgr&ctl=admin_wms&act=index');

        // legacy 事务由 begin()/end() 托管 ⇒ capability ownTransaction=false
        try {
            kernel::single('wmsmgr_capability_wms')->doInitializeLogi(array(
                'channel_id' => isset($_POST['channel_id']) ? $_POST['channel_id'] : '',
            ), false);
        } catch (Throwable $exception) {
            $this->end(false, $exception->getMessage() !== '' ? $exception->getMessage() : '初始化失败');
            return;
        }

        $this->end(true, app::get('base')->_('同步WMS异常错误码成功'), 3);
    }
}
