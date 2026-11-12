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

class dchain_ctl_admin_branch extends desktop_controller
{
    public $name = '优仓';
    public $workground = "channel_center";
    
    /**
     * index
     * @return mixed 返回值
     */
    public function index()
    {
        $params = array(
            'title'                  => '优仓管理',
            'actions'                => array(
                'add' => array(
                    'label'  => '添加优仓',
                    'href'   => 'index.php?app=dchain&ctl=admin_branch&act=add',
                    'target' => "dialog::{width:600,height:300,title:'优仓'}",
                ),
            ),
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            'use_buildin_recycle'    => false,
            'use_buildin_export'     => false,
            'use_buildin_import'     => false,
            'use_buildin_selectrow'  => false,
        );
        $this->finder('dchain_mdl_branch', $params);
    }
    
    function add()
    {
        $this->_edit();
    }
    
    function edit($dchainId)
    {
        $this->_edit($dchainId);
    }
    
    private function _edit($dchainId = null)
    {
        if ($dchainId) {
            $dchainBranchMdl               = $this->app->model('branch');
            $dchainData                    = $dchainBranchMdl->db_dump(array('channel_id' => $dchainId));
            $dchainData['config']          = @unserialize($dchainData['config']);
            $this->pagedata['branch_list'] = $dchainData;
        }
        
        $shopList = kernel::single('ome_shop')->shop_list([]);
        
        $this->pagedata['shop_list'] = $shopList;
        $this->display("add_dchain.html");
    }
    
    function saveDchainBranch()
    {
        $dchainBrnachMdl = $this->app->model("branch");
        
        $this->begin('index.php?app=dchain&ctl=admin_branch&act=index');
        
        $branch = array('channel_name' => trim($_POST['branch']['channel_name']), 'channel_type' => 'dchain');
        if ($_POST['branch']['channel_id']) {
            $branch['channel_id'] = $_POST['branch']['channel_id'];
        }
        if ($_POST['branch']['channel_bn']) {
            $branch['channel_bn'] = $_POST['branch']['channel_bn'];
        }
        
        // 验证编码
        if ($branch['channel_bn'] && $dchainBrnachMdl->count(array('channel_bn' => $branch['channel_bn']))) {
            $this->end(false, '优仓编码重复');
        }
        
        $shopList = kernel::single('ome_shop')->shop_list([]);
        $shopList = array_column($shopList, null, 'shop_id');
        $shopId = $_POST['config']['shop_id'];
        if (!isset($shopList[$shopId])) {
            $this->end(false, '请绑定店铺!');
        }
    
        $config = $_POST['config'];
        $warehouse_mapping = array();
        if ($_POST['oms_warehouse']) {
            foreach ($_POST['oms_warehouse'] as $key => $value) {
                if (empty($value) && empty($_POST['outer_warehouse'][$key])) {
                    continue;
                }
                if (!app::get('ome')->model('branch')->db_dump(array('branch_bn' => $value))) {
                    $this->end(false, $value . '：该编码系统仓库不存在!');
                }
                if (!$value) {
                    $this->end(false, '系统仓编码不能为空!');
                }
                if (!$_POST['outer_warehouse'][$key]) {
                    $this->end(false, '优仓编码不能为空!');
                }
                $warehouse_mapping[$value] = $_POST['outer_warehouse'][$key];
            }
        
            $config['warehouse_mapping'] = $warehouse_mapping;
        }
        //更新店铺映射
        $shopMdl        = app::get('ome')->model('shop');
        $shop           = $shopMdl->db_dump($shopId, 'config,shop_id,addon');
        $shop['config'] = @unserialize($shop['config']);
    
        $shop['config']['aging'] = empty($shop['config']['aging']) ? array_flip($warehouse_mapping) : ($shop['config']['aging'] + array_flip($warehouse_mapping));
        $rs                      = $shopMdl->save($shop);
        if (!$rs) {
            $this->end($rs, app::get('base')->_($rs ? '更新成功' : '更新失败'));
        }
        //更新优仓渠道映射
        $branch['node_id']   = $shopList[$shopId]['node_id'];
        $branch['node_type'] = $shopList[$shopId]['node_type'];
        $branch['config']    = serialize($config);
        $branch['addon']     = serialize($shopList[$shopId]);
        $branch['disabled']  = $_POST['disabled'];
        $rt                  = $dchainBrnachMdl->db_save($branch);
        $rt                  = $rt ? true : false;
        $this->end($rt, app::get('base')->_($rt ? '保存成功' : '保存失败'));
    }
}
