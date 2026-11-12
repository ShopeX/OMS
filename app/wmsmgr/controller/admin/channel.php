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

class wmsmgr_ctl_admin_channel extends desktop_controller
{
    //渠道管理列表
    /**
     * index
     * @return mixed 返回值
     */
    public function index()
    {
        $actions = array();
        
        //是否允许添加新渠道
        $isAddChannel = kernel::single('wmsmgr_channel')->checkAddChannel();
        
        //添加新渠道
        if($isAddChannel){
            $actions['add'] = array(
                    'label' => '新增渠道',
                    'href' => 'index.php?app=wmsmgr&ctl=admin_channel&act=add',
                    'target' => "dialog::{width:600,height:300,title:'渠道添加  '}",
            );
        }
        
        //params
        $params = array(
            'title'                  => '渠道管理列表',
            'actions'                => $actions,
            'use_buildin_recycle'    => false,
            'use_buildin_selectrow'  => false,
            'orderBy' => 'id ASC',
        );
        $this->finder('wmsmgr_mdl_channel', $params);
    }
    
    function add() {
        $this->_edit();
    }
    
    function edit($id) {
        $this->_edit($id);
    }
    
    private function _edit($id = NULL) {
        if ($id) {
            $channelObj = $this->app->model('channel');
            $channel_detail = $channelObj->dump($id);
            $this->pagedata['channel'] = $channel_detail;
        }
        $this->pagedata['wms'] = app::get('wmsmgr')->model('wms')->getList('*',array('node_type'=>'yjdf','node_id|noequal'=>''));

        $this->display("add_channel.html");
    }
    
    function saveChannel()
    {
        $url = 'index.php?app=wmsmgr&ctl=admin_channel&act=index';
        $this->begin($url);

        // 写实现唯一落点 = wmsmgr_capability_channel（api 控制器 act=save 委托同一 capability）
        try {
            $result = (new wmsmgr_capability_channel())->saveChannel($_POST);
            $this->end(true, app::get('base')->_($result['message'] ?? '保存成功'));
        } catch (Throwable $e) {
            $this->end(false, $e->getMessage() !== '' ? $e->getMessage() : '保存失败');
        }
    }

    /**
     * 删除渠道
     * 
     * @param unknown $channel_id
     * @return string
     */
    public function delChannel($channel_id)
    {
        $url = 'javascript:parent.finderGroup["'.$_GET['finder_id'].'"].refresh();';

        // 写实现唯一落点 = wmsmgr_capability_channel（api 控制器 act=delChannel 委托同一 capability）
        try {
            $result = (new wmsmgr_capability_channel())->deleteChannel((string) $channel_id);
            $this->splash('success', $url, $result['message'] ?? '删除渠道成功');
        } catch (Throwable $e) {
            $this->splash('error', $url, $e->getMessage() !== '' ? $e->getMessage() : '删除渠道失败');
        }
    }
    
    /**
     * test
     * @return mixed 返回值
     */
    public function test()
    {
        $msg = '';
        kernel::single('material_autotask_timer_batchchannelmaterial')->process([],$msg);
        echo $msg;
    }
}