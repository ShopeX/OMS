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

class dealer_ctl_admin_shop_stocksku extends inventorydepth_ctl_shop_adjustment {

    public function index($source = '')
    {
        $base_filter = array();

        if($_POST['shop_id']) {
            $shop_id = $_POST['shop_id'];
        }elseif($_GET['shop_id']) {
            $shop_id = $_GET['shop_id'];
        } elseif($_GET['filter']['shop_id']) {
            $shop_id = $_GET['filter']['shop_id'];
        }
        list($rs, $cosId) = kernel::single('organization_cos')->getCosList();
        if(!$rs) {
            die('need cos id');
        }
        $base_filter['cos_id'] = $cosId;
        $shopdata = app::get('ome')->model('shop')->getList('shop_id,name', ['node_id|noequal'=>'','delivery_mode'=>'shopyjdf', 'cos_id'=>$cosId]);

        if($shop_id) {
            $base_filter['shop_id'] = $shop_id;
        }

        $title = "库存同步列表";
        
        $extra_view = array(
            'inventorydepth' => 'admin/show.html',
        );
        
        $this->pagedata['shopdata']= $shopdata;
        $this->pagedata['shop_id']= $shop_id;
        
        $params = array(
            'title' => $title,
            'actions' => array(
                0 => array('label'=>$this->app->_('批量开启回写库存'),'submit'=>'index.php?app=inventorydepth&ctl=shop_skus&act=set_request&p[0]=true','target'=>'refresh'),
                1 => array('label'=>$this->app->_('批量关闭回写库存'),'submit'=>'index.php?app=inventorydepth&ctl=shop_skus&act=set_request&p[0]=false','target'=>'refresh'),
                //2 => array('label'=>$this->app->_('发布库存'),'submit'=>'index.php?app=inventorydepth&ctl=shop_adjustment&act=releasePage','target'=>'dialog::{title:\'批量发布\'}'),
                //3 => array('label'=>$this->app->_('导出发布库存模板'),'submit'=>'index.php?app=inventorydepth&ctl=shop_adjustment&act=export_data&view='.$_GET["view"].'&p[0]=release_stock','target'=>'_blank'),
                //4 => array('label'=>$this->app->_('导入发布库存'),'href'=>'index.php?app=inventorydepth&ctl=shop_adjustment&act=index&action=import','target' =>  'dialog::{width:400,height:150,title:\'导入发布库存\'}'),
            ),
            'use_buildin_recycle' => false,
            'use_buildin_filter' => true,
            'use_buildin_export' => true,
            'base_filter' => $base_filter,
            'top_extra_view' => $extra_view,
            'finder_aliasname' => 'dealer_shop_stocksku',
            'orderBy' => 'request asc',
            'object_method' => array(
                'count'=>'count',
                'getlist'=>'getFinderList',
            ),
        );

        $this->pagedata['benchobj']    = kernel::single('inventorydepth_stock')->get_benchmark();
        $this->pagedata['calculation'] = kernel::single('inventorydepth_math')->get_calculation();
        $this->pagedata['res_full_url'] = $this->app->res_full_url;

        $this->finder('inventorydepth_mdl_shop_adjustment',$params);
    }
}