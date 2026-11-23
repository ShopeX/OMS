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
 * 经销店铺管理
 *
 * @author wangbiao@shopex.cn
 * @version 2024.04.08
 */
class dealer_ctl_admin_shop extends desktop_controller
{
    var $title = '代发店铺管理';
    var $workground = 'dealer_center';
    var $_delivery_mode = 'shopyjdf';
    
    private $_mdl = null;
    private $_commonLib = null;
    private $_businessLib = null;
    
    private $_primary_id = null; //主键ID字段名
    private $_primary_bn = null; //单据编号字段名
    
    public function __construct($app)
    {
        parent::__construct($app);
        
        $this->_mdl = app::get('dealer')->model('shop');
        $this->_commonLib = kernel::single('dealer_common');
        $this->_businessLib = kernel::single('dealer_business');
        
        //primary_id
        $this->_primary_id = 'shop_id';
        
        //primary_bn
        $this->_primary_bn = 'shop_bn';
    }
    
    public function index()
    {
        $actions = array();
        $base_filter = $this->getFilters();
        
        //table
        $actions[] = array(
            'label' => '添加代发店铺',
            'href' => $this->url .'&act=addShop&view='. $_GET['view'] .'&finder_id='.$_GET['finder_id'],
            'target' => "dialog::{width:650,height:550,title:'添加代发店铺'}",
        );
        
        $actions[] = array(
            'label' => '增加产品线授权',
            'href' => $this->url .'&act=addProductLine&view='. $_GET['view'] .'&finder_id='.$_GET['finder_id'],
            'target' => "dialog::{width:800,height:600,title:'增加产品线授权'}",
        );
        
        //params
        $orderby = 'create_time DESC';
        $params = array(
            'title' => $this->title,
            'base_filter' => $base_filter,
            'actions'=> $actions,
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag' => false,
            'use_buildin_recycle' => false,
            'use_buildin_export' => true,
            'use_buildin_import' => false,
            'use_buildin_filter' => true,
            'orderBy' => $orderby,
        );
        
        $this->finder('dealer_mdl_shop', $params);
    }
    
    /**
     * 公共filter条件
     *
     * @return array
     */
    public function getFilters()
    {
        $base_filter = array('delivery_mode'=>$this->_delivery_mode);
        
//        //check shop permission
//        $organization_permissions = kernel::single('desktop_user')->get_organization_permission();
//        if($organization_permissions){
//            $base_filter['org_id'] = $organization_permissions;
//        }
        
        //获取操作人员的企业组织架构ID权限
        $cosData = $this->_businessLib->getOperationCosIds();
        if($cosData[1]){
            $base_filter['cos_id'] = $cosData[1];
        }
        
        return $base_filter;
    }
    
    /**
     * 添加店铺
     */
    public function addShop()
    {
        $operationOrgObj = app::get('ome')->model('operation_organization');
        
        //运营组织
        $orgs = $operationOrgObj->getList('*', array(), 0, -1);
        $this->pagedata['orgs'] = $orgs;
        
        //经销商列表
        $businessiList = $this->_businessLib->getBusiness();
        
        $this->pagedata['businessiList'] = $businessiList;
        $this->pagedata['title'] = '添加经销店铺';
        $this->display('admin/shop/add_shop.html');
    }
    
    /**
     * 编辑店铺
     */
    public function editShop($shop_id)
    {
        $operationOrgObj = app::get('ome')->model('operation_organization');
        
        //运营组织
        $orgs = $operationOrgObj->getList('*', array(), 0, -1);
        $this->pagedata['orgs'] = $orgs;
        
        //经销商列表
        $businessiList = $this->_businessLib->getBusiness();
        
        //店铺信息
        $shop = $this->_mdl->dump($shop_id);
        $shop_config = unserialize($shop['config']);
        
        //check
        if($shop['delivery_mode'] != $this->_delivery_mode){
            die('发货模式不是：一件代发，不能编辑!');
        }
        
        $this->pagedata['shop'] = $shop;
        
        //如果已绑定，并且未配置过渠道，默认为商派矩阵
        if(!isset($shop_config['adapter']) && $shop['node_id']){
            $shop_config['adapter'] = 'matrixonline';
        }
        
        $this->pagedata['businessiList'] = $businessiList;
        $this->pagedata['shop_config'] = $shop_config;
        $this->pagedata['title'] = '编辑经销店铺';
        $this->display('admin/shop/add_shop.html');
    }
    
    /**
     * 保存店铺数据
     */
    public function saveShop()
    {
        $this->begin($this->url .'&act=index');
        $error_msg = '';
        
        //post
        $saveData = $_POST['shop'];
        
        //format
        $saveData['shop_id'] = trim($saveData['shop_id']);
        $saveData['shop_bn'] = trim($saveData['shop_bn']);
        $saveData['shop_bn'] = $this->_commonLib->replaceChar($saveData['shop_bn']);
        
        //店铺信息
        $shopInfo = array();
        if($saveData['shop_id']){
            $shopInfo = $this->_mdl->dump(array('shop_id'=>$saveData['shop_id']), '*');
        }
        
        //验证店铺编码
        if(empty($shopInfo)){
            $checkShop = $this->_mdl->dump(array('shop_bn'=>$saveData['shop_bn']), 'shop_id');
            if($checkShop){
                $error_msg = '店铺编码：'. $saveData['shop_bn'] .'已经被使用,请检查!';
                $this->end(false, $error_msg);
            }
        }
        
        //验证手机号
        $isMobile = $this->_commonLib->replaceChar($saveData['mobile'], $error_msg);
        if(!$isMobile){
            $this->end(false, $error_msg);
        }
        
        //验证经销商
        if(empty($saveData['bs_id'])){
            $error_msg = '请选择所属经销商!';
            $this->end(false, $error_msg);
        }
        
        //获取经销商信息
        $businessInfo = $this->_businessLib->getBusiness($saveData['bs_id']);
        if(empty($businessInfo)){
            $error_msg = '经销商不存在,请检查!';
            $this->end(false, $error_msg);
        }
        
        //经销商ID、组织架构ID
        $saveData['bs_id'] = (isset($businessInfo['bs_id']) ? $businessInfo['bs_id'] : 0);
        // $saveData['cos_id'] = $businessInfo['cos_id']; //组织架构ID集合，示例：1,2,3
        
        //保存 or 更新
        unset($saveData['shop_id']);
        if($shopInfo){
            unset($saveData['shop_bn']);
            
            //shop_id
            $saveData['shop_id'] = $shopInfo['shop_id'];
        }else{
            $saveData['create_time'] = time();
        }
        
        //发货模式
        $saveData['delivery_mode'] = $this->_delivery_mode;
        
        //opinfo
        $opInfo = kernel::single('ome_func')->getDesktopUser();
        
        //保存COS企业组织信息
        $cosData = array(
            'cos_type' => 'shop',
            'cos_code' => ($saveData['shop_bn'] ? $saveData['shop_bn'] : $shopInfo['shop_bn']),
            'cos_name' => ($saveData['name'] ? $saveData['name'] : $shopInfo['name']),
            'op_name' => $opInfo['op_name'],
            'parent_id' => $businessInfo['cos_id'], //组织架构ID
            'is_leaf' => '1', //叶子节点(默认填写1)
        );
        $shop_cos_id = kernel::single('organization_cos')->saveCos($cosData);
        if (!$shop_cos_id) {
            $this->end(false, app::get('base')->_('保存企业组织失败'));
        }
        
        $saveData['cos_id'] = $shop_cos_id;
        //save
        $isSave = $this->_mdl->save($saveData);
        if(!$isSave){
            $this->end(false, app::get('base')->_('保存店铺失败'));
        }
        
        $this->end(true, app::get('base')->_('保存成功'));
    }
    
    public function showProduct()
    {
        //get
        $shop_id = $_REQUEST['shop_id'];
        if(empty($shop_id)){
            die('无效的操作!');
        }
        
        //filter
        $base_filter = array('shop_id'=>$shop_id);
        
        //params
        $orderby = 'sm_id DESC';
        $params = array(
            'title' => '分销商品列表',
            'base_filter' => $base_filter,
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag' => false,
            'use_buildin_recycle' => false,
            'use_buildin_export' => false,
            'use_buildin_import' => false,
            'use_buildin_filter' => true,
            'orderBy' => $orderby,
        );
        
        $this->finder('dealer_mdl_sales_material', $params);
    }
    
    /**
     * 增加产品线授权
     */
    public function addProductLine()
    {
        $seriesMdl = app::get('dealer')->model('series');
        
        //产品授权信息
        $dataList = $seriesMdl->getList('*', array('status'=>'active'));
        if(empty($dataList)){
            die('没有产品线数据...');
        }
        
        $selectedList = array();
        
        $this->pagedata['history'] = false;
        $this->pagedata['dataList'] = $dataList;
        $this->pagedata['selectedList'] = $selectedList;
        
        $this->pagedata['title'] = '增加产品线授权';
        $this->display('admin/shop/add_product_line.html');
    }
    
    /**
     * 保存店铺数据
     */
    public function saveProductLine()
    {
        die('暂不支持保存数据。。。');
        
        $this->begin($this->url .'&act=index');
        $error_msg = '';
        
        $this->end(true, app::get('base')->_('保存成功'));
    }
}