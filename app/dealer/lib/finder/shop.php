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
 * 经销店铺finder
 *
 * @author wangbiao@shopex.cn
 * @version 2024.04.08
 */
class dealer_finder_shop extends ome_finder_shop
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    static $_businessList = array();
    static $_supplyBranchs = array();
    static $_seriesList = array();
    
    //model
    private $_appName = 'dealer';
    private $_modelName = 'shop';
    private $_primary_id = 'shop_id';
    
    public $addon_cols = "shop_id,shop_type,node_id,name,node_type,alipay_authorize,aoxiang_signed,config,bs_id,cos_id";
    
    public $column_edit = "操作";
    public $column_edit_width = 280;
    public $column_edit_order = 1;
    /**
     * column_edit
     * @param mixed $row row
     * @return mixed 返回值
     */

    public function column_edit($row)
    {
        $finder_id = $_GET['_finder']['finder_id'];
        $api_url = kernel::base_url(true) . kernel::url_prefix() . '/api';
        $app_id = $this->_appName;
        
        $node_type = $row[$this->col_prefix . 'node_type'];
        $node_id = $row[$this->col_prefix . 'node_id'];
        $shop_id = $row[$this->col_prefix . 'shop_id'];
        $configStr = $row[$this->col_prefix . 'config'];
        $config = unserialize($configStr);
        
        //button
        $button1 = sprintf('<a href="index.php?app=%s&ctl=admin_%s&act=editShop&p[0]=%s&finder_id=%s" target="dialog::{width:650,height:550,title:\'编辑经销店铺\'}">编辑</a>', $this->_appName, $this->_modelName, $row[$this->_primary_id], $finder_id);
        
        $sess_id = kernel::single('base_session')->sess_id();
        $callback_url = urlencode(kernel::openapi_url('openapi.ome.shop', 'shop_callback', array('shop_id' => $shop_id, 'sess_id' => $sess_id)));
        $api_url = urlencode($api_url);
        
        //button3
        if(!isset($config['adapter']) || (isset($config['adapter']) && $config['adapter'] == 'matrixonline')){
            $button3 = $node_id ? ' | 已绑定' :  sprintf(' | <a href="index.php?app=ome&ctl=admin_shop&act=apply_bindrelation&p[0]=%s&p[1]=%s&p[2]=%s&finder_id=%s" target="dialog::{width:800,title:\'申请店铺绑定\',onClose:function(){window.finderGroup[\'%s\'].refresh();}}">申请绑定</a>',$app_id,$callback_url,$api_url,$finder_id,$finder_id);
        }else{
            $button3 = $node_id ? sprintf(' | <a href="index.php?app=ome&ctl=admin_shop&act=bindNodeId&p[0]=%s&p[1]=%s&finder_id=%s" target="dialog::{width:600,height:400,title:\'取消绑定\'}" style="color:#990000;">取消绑定</a>',$shop_id,'unbind',$finder_id) : sprintf(' | <a href="index.php?app=ome&ctl=admin_shop&act=bindNodeId&p[0]=%s&finder_id=%s" target="dialog::{width:600,height:400,title:\'对接第三方渠道\'}">申请绑定</a>',$shop_id,$finder_id);
        }
        
        //支付宝绑定授权
        $button2 = '';
        if($node_type == 'luban' && $node_id){
            //抖音平台退货地址库
            $addressObj = app::get('ome')->model('return_address');
            $countNums = $addressObj->count(array('shop_id'=>$shop_id, 'contact_id|than'=>0));
            if($countNums <= 0){
                $button2 .= sprintf(' | <a href="index.php?app=ome&ctl=admin_return_address&act=index&shop_id=%s">同步退货地址库</a>', $shop_id);
            }
            
            //抖音平台地区库
            $regionObj = app::get('eccommon')->model('platform_regions');
            $countNums = $regionObj->count(array('shop_type'=>'luban'));
            if($countNums <= 0){
                $button2 .= sprintf(' | <a href="index.php?app=eccommon&ctl=platform_regions&act=index&shop_id=%s">同步平台地区库</a>', $shop_id);
            }
        }
        
        //扩展操作功能按钮
        $showMateiraLUrl = 'index.php?app=desktop&act=alertpages&goto='.urlencode('index.php?app=dealer&ctl=admin_shop&act=showProduct&shop_id='.$shop_id.'&finder_id='.$finder_id);
        $extend_button = ' | <a href="'. $showMateiraLUrl .'" target="_blank">查看分销商品</a>';
        
        return $button1 . $button3 . $button2 . $extend_button;
    }
    
    //所属经销商
    var $column_business_name = '所属经销商';
    var $column_business_name_width = 130;
    var $column_business_name_order = 50;
    /**
     * column_business_name
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_business_name($row, $list)
    {
        $this->_getBusiness($list);
        
        //check
        $bs_id = $row[$this->col_prefix .'bs_id'];
        if(empty($bs_id)){
            return '';
        }
        
        $businessInfo = (isset(self::$_businessList[$bs_id]) ? self::$_businessList[$bs_id] : array());
        
        return $businessInfo['name'];
    }
    
    //所属贸易公司
    var $column_betc_name = '所属贸易公司';
    var $column_betc_name_width = 130;
    var $column_betc_name_order = 52;
    /**
     * column_betc_name
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_betc_name($row, $list)
    {
        $this->_getBusiness($list);

        //check
        $bs_id = $row[$this->col_prefix .'bs_id'];
        if(empty($bs_id)){
            return '';
        }
        
        $businessInfo = (isset(self::$_businessList[$bs_id]) ? self::$_businessList[$bs_id] : array());
        
        $betcNames = array();
        if($businessInfo['betcs']){
            $betcNames = array_column($businessInfo['betcs'], 'betc_name');
        }
        
        return ($betcNames ? implode(',', $betcNames) : '');
    }
    
    //供货仓
    var $column_supply_branch = '供货仓';
    var $column_supply_branch_width = 230;
    var $column_supply_branch_order = 54;
    /**
     * column_supply_branch
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_supply_branch($row, $list)
    {
        $this->_getSupplyBranchs($list);
        
        $shop_bn = $row['shop_bn'];
        
        //branchs
        $branchNames = array();
        $branchs = (isset(self::$_supplyBranchs[$shop_bn]) ? self::$_supplyBranchs[$shop_bn] : array());
        if($branchs){
            $branchNames = array_column($branchs, 'name');
        }
        
        return ($branchNames ? implode(',', $branchNames) : '');
    }
    
    //授权产品线
    var $column_series_name = '授权产品线';
    var $column_series_name_width = 230;
    var $column_series_name_order = 56;
    /**
     * column_series_name
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_series_name($row, $list)
    {
        $this->_getSeriesList($list);
        
        $shop_id = $row[$this->col_prefix .'shop_id'];
        
        $seriesNames = array();
        $seriesList = (isset(self::$_seriesList[$shop_id]) ? self::$_seriesList[$shop_id] : array());
        if($seriesList){
            $seriesNames = array_column($seriesList, 'series_name');
        }
        
        return ($seriesNames ? implode(',', $seriesNames) : '');;
    }
    
    /**
     * 批量获取指定经销商列表(包含贸易公司信息)
     * 
     * @param $list
     * @return boolean
     */
    private function _getBusiness($list)
    {
        //check
        if(self::$_businessList) {
            return true;
        }
        
        $bsIds = array_column($list, $this->col_prefix .'bs_id');
        if(empty($bsIds)){
            return true;
        }
        
        self::$_businessList = kernel::single('dealer_business')->getAssignBusiness($bsIds);
        
        return true;
    }
    
    /**
     * 批量获取供货仓
     * 
     * @param $list
     * @return boolean
     */
    private function _getSupplyBranchs($list)
    {
        //check
        if(self::$_supplyBranchs) {
            return true;
        }
        
        $shopBns = array_column($list, 'shop_bn');
        if(empty($shopBns)){
            return true;
        }
        
        self::$_supplyBranchs = kernel::single('dealer_business')->getShopBranchs($shopBns);
        
        return true;
    }
    
    /**
     * 批量获取授权产品线
     * 
     * @param $list
     * @return boolean
     */
    private function _getSeriesList($list)
    {
        //check
        if(self::$_seriesList) {
            return true;
        }
        
        $shopIds = array_column($list, 'shop_id');
        if(empty($shopIds)){
            return true;
        }
        
        self::$_seriesList = kernel::single('dealer_business')->getAssignSeries($shopIds);
        
        return true;
    }
}
