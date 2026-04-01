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
class ome_ctl_admin_branchset extends desktop_controller{
    var $name = "仓库设置";
    var $workground = "setting_tools";

    function index(){
       $branchtype = app::get('wms')->getConf('wms.branchset.type');
       $this->pagedata['branchtype'] =  $branchtype;
       //售后质检商品入库类型
       $goodsBranchType = app::get('ome')->getConf('ome.aftersale.goods.branch.type');
       $this->pagedata['goods_branch_type'] =  $goodsBranchType;
       $setData                                = array();
       $setData['return_auto_branch']          = app::get('ome')->getConf('return.auto_branch');
       $setData['return_auto_shop_branch']     = app::get('ome')->getConf('return.auto_shop_branch');
       // 获取WMS商品类型仓库配置
       $setData['wms_goods_type_branch'] = app::get('ome')->getConf('wms.goods.type.branch');
       $this->pagedata['setData'] = $setData;
       $shopMdl = app::get('ome')->model('shop');
       $shopList = $shopMdl->getlist('shop_id,name',array('s_type'=>'1','s_status'=>'2'));

       $this->pagedata['shopList'] = $shopList;
       $branchMdl = app::get('ome')->model('branch');
       $branchList = $branchMdl->getlist('branch_id,name',array('check_permission'=>'false','type'=>array('main','aftersale','damaged')));

       $this->pagedata['branchList'] = $branchList;
       $this->page('admin/branch/branchset.html');
    }

    function save()
    {
        $this->begin();
        $settype = $_POST['set']['branchtype'];
        $goodsBranchType = $_POST['set']['goods_branch_type'];
        $_POST['set']['return.auto_shop_branch'] = [];
        if($_POST['auto_shop_branch']) {
            foreach ($_POST['auto_shop_branch']['shop_id'] as $key => $shop_id) {
                $_POST['set']['return.auto_shop_branch'][$shop_id] = $_POST['auto_shop_branch']['branch_id'][$key];
            }
        }
        // 保存WMS商品类型仓库配置
        if ($_POST['set']['wms_goods_type_branch']) {
            app::get('ome')->setConf('wms.goods.type.branch', $_POST['set']['wms_goods_type_branch']);
        }
        app::get('wms')->setConf('wms.branchset.type',$settype);
        app::get('ome')->setConf('ome.aftersale.goods.branch.type',$goodsBranchType);
        app::get('ome')->setConf('return.auto_branch', $_POST['set']['return.auto_branch']);
        app::get('ome')->setConf('return.auto_shop_branch', $_POST['set']['return.auto_shop_branch']);
        $this->end(true,'保存成功');
    }
     
}
?>
