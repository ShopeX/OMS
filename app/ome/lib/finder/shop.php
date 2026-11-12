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

class ome_finder_shop
{
    public $detail_shop            = "基本信息";
    public $detail_config          = "下载订单";
    public $detail_dly_corp        = "前端物流绑定";
    public $detail_shoporderlist   = "前端店铺检查";
    public $detail_jzorderdelivery = '家装订单发货';
    public $detail_branch          = '仓库配置';
    public $detail_jd_dly_corp     = '京东物流公司配置';
    public $detail_aligenius       = 'AliGenius配置';
    public $detail_cnatuto         = '菜鸟流转订单仓库设置';
    public $detail_basic_config    = '店铺基本配置';
    public $detail_aoxiang = '翱象配置';

    function __construct(){
        if($_GET['ctl'] == 'admin_shop'  && $_GET['act'] == 'index'){
            //nothing
        }else{
           unset($this->column_edit);
        }
    }
    
    private $_signedList = array(
        '0' => '未签约',
        '1' => '已签约',
        '2' => '取消签约',
    );
    
    public function detail_shop($shop_id)
    {
        $render = app::get('ome')->render();
        $oShop  = app::get('ome')->model("shop");
        $shop   = $oShop->dump($shop_id);

        // $addon = $shop['addon']; $config = (array)@unserialize($shop['config']);

        $shop_type      = ome_shop_type::get_shop_type();
        $reset_login    = "";
        $shoptype       = $shop['node_type'];
        $node_id        = $shop['node_id'];
        $finder_id      = $_GET['_finder']['finder_id'];
        $taobao_session = app::get('ome')->getConf('taobao_session_' . $node_id);
        $taobao_session = strval($taobao_session);

        $render->pagedata['reset_login'] = $reset_login;
        $render->pagedata['shop']        = $shop;
        $render->pagedata['finder_id']   = $finder_id;
        $render->pagedata['shop_type']   = $shop_type;
        $render->pagedata['is_show_sku']   = $shoptype=='taobao'?1:0;

        // $ui = $render->ui();

        // $config_html = '';

        // $default_config = $this->_config['default'];


        // $shop_config = array_merge($default_config,(array)$this->_config[$shoptype]);

        // foreach ($shop_config as $key => $value) {
        //     if($value['filter']) {
        //         foreach ($value['filter'] as $field => $v) {
        //             if($shop[$field] != $v) {
        //                 continue 2;
        //             }
        //         }
        //     }
        //     eval('$value["value"]=$'.str_replace(['[',']'],['["','"]'],$value['name']).';');

        //     $config_html .= $ui->form_input($value);
        // }

        // if ($config_html) $render->pagedata['config_html'] = $config_html.$ui->form_end();

        return $render->fetch("admin/system/terminal_detail.html");

    }

    public $detail_addon    = '店铺配置';
    /**
     * 店铺相关配置项
     * 
     * */
    public function detail_addon($shop_id)
    {
        $render = app::get('ome')->render();

        $shop   = app::get('ome')->model("shop")->dump($shop_id);
        $shop['config'] = (array)@unserialize($shop['config']);

        $render->pagedata['shop'] = $shop;


        return $render->fetch("admin/finder/shop/addon.html");
    }

    /**
     * 前端店铺同步
     * 
     * @param String $shop_id
     */
    public function detail_config($shop_id)
    {
        $render = app::get('ome')->render();
        $shop   = app::get('ome')->model('shop')->getList('node_type,node_id,business_type', array('shop_id' => $shop_id));

        if ($shop[0]['node_id']) {
            $config['is_config'] = ome_shop_type::get_shoporder_config($shop[0]['node_type']);
            if($shop[0]['node_type'] == 'taobao' && $shop[0]['business_type'] == 'maochao') {
                $config['is_config'] = 'off';
            }
            $config['error_msg'] = '本店铺暂不支持同步前端订单';
        } else {
            $config['is_config'] = 'off';
            $config['error_msg'] = '店铺未绑定';
        }

        if ($_POST) {
            $shop_config = array(
                'store_conf' => $_POST['store_conf'],

            );
            app::get('ome')->setConf('shop.weimobv.config', $shop_config);
        }

        $weimobvconf = app::get('ome')->getConf('shop.weimobv.config');

        $render->pagedata['weimobvconf'] = $weimobvconf;
        $render->pagedata['order_type']  = ($shop[0]['business_type'] == 'zx') ? 'direct' : 'agent';

        $render->pagedata['shop_id'] = $shop_id;

        $render->pagedata['config'] = $config;

        $render->pagedata['shop_detail'] = $shop[0];
        return $render->fetch("admin/system/shop_syncorder.html");
    }

    /**
     * detail_dly_corp
     * @param mixed $shop_id ID
     * @return mixed 返回值
     */
    public function detail_dly_corp($shop_id)
    {
        $shopCropObj = app::get('ome')->model('shop_dly_corp');
        $shopObj     = app::get('ome')->model("shop");
        $shopData    = $shopObj->dump($shop_id);
        if ($_POST) {
            if ($shopData['node_type'] == 'mia') {
                $shopCropObj->delete(array('shop_id' => $shop_id));
                foreach ($_POST['overseas'] as $corp_id => $overseas) {
                    #蜜芽宝贝,属于国外物流的物流公司
                    if ($overseas == '1') {
                        $data['shop_id'] = $_POST['shop_id'];
                        $data['corp_id'] = $corp_id;
                        $shopCropObj->save($data);
                    }
                    unset($data);
                }
            } else {
                $data['shop_id'] = $_POST['shop_id'];
                if ($_POST['config']['cropBind'] == 1) {
                    $shopCropObj->delete($data);
                    foreach ($_POST['crop_name'] as $key => $crop_name) {
                        if ($crop_name) {
                            $data['corp_id']   = $_POST['corp_id'][$key];
                            $data['crop_name'] = $crop_name;
                            $shopCropObj->save($data);
                        }
                    }
                }
                $shopObj->update(array('crop_config' => $_POST['config']), array('shop_id' => $_POST['shop_id']));
                $shopData['crop_config'] = $_POST['config'];
            }
        }

        $dlyCropObj = app::get('ome')->model('dly_corp');
        $branchObj  = app::get('ome')->model('branch');

        $shopCrop  = $shopCropObj->getList('*', array('shop_id' => $shop_id));
        $shopCrops = array();
        foreach ($shopCrop as $key => $val) {
            $shopCrops[$val['corp_id']] = $val['crop_name'];
            $all_corp_id[]              = $val['corp_id'];
        }

        //获取电商物流公司
        $dlyCrops = $dlyCropObj->getList('corp_id, name, type, is_cod, weight', array('disabled' => 'false', 'd_type' => 1), 0, -1, 'weight DESC');

//         $dlyGroups = array();
        foreach ($dlyCrops as $key => $val) {
//             $dlyGroups[$val['branch_id']]['dlyCrops'][] = $val;
            //             $branchIds[$val['branch_id']] = $val['branch_id'];
            if ($shopData['node_type'] == 'mia') {
                if (in_array($val['corp_id'], $all_corp_id)) {
                    $dlyCrops[$key]['oversea'] = '1';
                } else {
                    $dlyCrops[$key]['oversea'] = '0';
                }
            }
        }

        //获取电商仓库
        //         $branchs = $branchObj->getList('branch_id, branch_bn, name', array('branch_id' => $branchIds, 'b_type'=>1));
        //         foreach($branchs as $key=>$val){
        //             $dlyGroups[$val['branch_id']]['name'] = $val['name'];
        //         }

        $render = app::get('ome')->render();
//         $render->pagedata['dlyGroups'] = $dlyGroups;
        $render->pagedata['dlyCrops']  = $dlyCrops;
        $render->pagedata['shopData']  = $shopData;
        $render->pagedata['shopCrops'] = $shopCrops;
        #蜜芽宝贝，设置海外物流
        if ($shopData['node_type'] == 'mia') {
            /*  $render->pagedata['dlyCrops'] = $dlyCrops;
        $render->pagedata['all_corp_id'] = $all_corp_id;
        return $render->fetch("admin/system/overseas_dly_crop.html"); */
        }
        return $render->fetch("admin/system/terminal_dly_crop.html");
    }

    /**
     * 前端店铺订单同步
     * @param String $shop_id 店铺ID
     */
    public function detail_shoporderlist($shop_id)
    {
        $shopObj             = app::get('ome')->model("shop");
        $shopData            = $shopObj->dump($shop_id);
        $shop_type           = ome_shop_type::shopex_shop_type();
        $config['is_config'] = 'off';
        $config['error_msg'] = '店铺未绑定';
        //店铺显示过滤
        if ($shopData['node_id'] != '') {
            $config['error_msg'] = '本店铺暂不支持同步前端订单';
            if (in_array($shopData['node_type'], $shop_type) !== false) {
                $config['is_config'] = 'on';
            }
        }
        $ayncOrderBns = array();
        if ($config['is_config'] != 'off') {
            $config['error_msg'] = '';
            if (isset($_POST['starttime']) && isset($_POST['endtime'])) {
                $start        = explode('-', $_POST['starttime']);
                $end          = explode('-', $_POST['endtime']);
                $start_mktime = mktime(0, 0, 0, $start[1], $start[2], $start[0]);
                $end_mktime   = mktime(23, 59, 59, $end[1], $end[2], $end[0]);
                $starttime    = date('Y-m-d', $start_mktime);
                $endtime      = date('Y-m-d', $end_mktime);
            } else {
                $start_mktime = time() - 24 * 3600;
                $end_mktime   = time();
                $starttime    = date('Y-m-d', $start_mktime);
                $endtime      = date('Y-m-d', $end_mktime);
            }
            //获取差异数据
            if (isset($_POST['syncorderlist']) && $_POST['syncorderlist'] = '1') {

                $return   = kernel::single('erpapi_router_request')->set('shop', $shop_id)->order_getOrderList($start_mktime, $end_mktime);
                $orderBns = array();
                if ($return['rsp'] == 'success') {
                    foreach ($return['data'] as $data) {
                        $orderBns[] = $data['tid'];
                    }
                }
                $orderObj = app::get('ome')->model("orders");
                foreach ($orderBns as $order_bn) {
                    $filter = array('shop_id' => $shop_id, 'order_bn' => $order_bn);
                    $result = $orderObj->dump($filter);
                    if (empty($result)) {
                        $ayncOrderBns[] = $order_bn;
                    }
                }
                if (empty($ayncOrderBns)) {
                    $config['error_msg'] = '没有可同步的订单';
                }
            }
        }
        $render                      = app::get('ome')->render();
        $render->pagedata['shop_id'] = $shop_id;
        //订单类型
        $render->pagedata['order_type']   = ($shopData['business_type'] == 'zx') ? 'direct' : 'agent';
        $render->pagedata['config']       = $config;
        $render->pagedata['starttime']    = $starttime;
        $render->pagedata['endtime']      = $endtime;
        $render->pagedata['ayncOrderBns'] = $ayncOrderBns;
        return $render->fetch("admin/system/shop_syncorderlist.html");
    }

    public $addon_cols        = "shop_id,shop_type,node_id,name,node_type,alipay_authorize,aoxiang_signed,config";
    public $column_edit       = "操作";
    public $column_edit_width = "280";
    /**
     * column_edit
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_edit($row)
    {

        $finder_id      = $_GET['_finder']['finder_id'];
        $shop_type      = $row[$this->col_prefix . 'shop_type'];
        $node_type      = $row[$this->col_prefix . 'node_type'];
        $node_id        = $row[$this->col_prefix . 'node_id'];
        $shop_id        = $row[$this->col_prefix . 'shop_id'];
        $shop_name      = $row[$this->col_prefix . 'name'];
        $configStr      = $row[$this->col_prefix . 'config'];
        $config =       @unserialize($configStr);
        if (!is_array($config)) {
            $config = array();
        }
        $adapter        = isset($config['adapter']) ? $config['adapter'] : '';
        $button1        = sprintf('<a href="index.php?app=ome&ctl=admin_shop&act=editterminal&p[0]=%s&finder_id=%s" target="dialog::{width:900,height:800,title:\'编辑店铺\',onClose:function(){if(window.finderGroup && window.finderGroup[\'%s\']){window.finderGroup[\'%s\'].refresh();}}}">编辑</a>', $row[$this->col_prefix . 'shop_id'], $finder_id, $finder_id, $finder_id);
        $taobao_session = app::get('ome')->getConf('taobao_session_' . $node_id);
        $taobao_session = $taobao_session ? $taobao_session : 'false';
        $certi_id       = base_certificate::get('certificate_id');
        $api_url        = kernel::base_url(true) . kernel::url_prefix() . '/api';
        $button2 = '';
        $sess_id = kernel::single('base_session')->sess_id();
        $callback_url = urlencode(kernel::openapi_url('openapi.ome.shop', 'shop_callback', array('shop_id' => $shop_id, 'sess_id' => $sess_id)));
        $app_id       = "ome";
        $api_url      = urlencode($api_url);
        
        // 申请绑定按钮或已绑定状态
        $is_binded = false;
        if ($node_id) {
            // 商家自研（openapi）：只要有节点即视为已绑定，不依赖奇门
            if ($adapter === 'openapi') {
                $is_binded = true;
            } elseif ($node_type == 'taobao') {
                // 淘宝店铺需要同时检查奇门授权状态
                $channelObj = kernel::single('channel_channel');
                $qimenChannel = $channelObj->getQimenJushitaErp();
                // 判断是否已授权：检查 app_key 和 secret_key 是否都有值
                if (!empty($qimenChannel['app_key']) && !empty($qimenChannel['secret_key'])) {
                    $is_binded = true;
                }
            } else {
                // 非淘宝店铺，只要有 node_id 就算已绑定
                $is_binded = true;
            }
        }
        
        if ($is_binded) {
            // 已绑定：显示已绑定（可点击，高亮显示）
            $button3 = sprintf(' | <a href="index.php?app=ome&ctl=admin_shop&act=bind_guide&shop_id=%s&finder_id=%s" target="dialog::{width:930,height:800,title:\'店铺绑定\',onClose:function(){if(window.finderGroup && window.finderGroup[\'%s\']){window.finderGroup[\'%s\'].refresh();}}}" style="color: #52c41a; font-weight: bold;">已绑定</a>', $shop_id, $finder_id, $finder_id, $finder_id);
        } else {
            // 未绑定：显示申请店铺绑定按钮
            $button3 = sprintf(' | <a href="index.php?app=ome&ctl=admin_shop&act=bind_guide&shop_id=%s&finder_id=%s" target="dialog::{width:930,height:800,title:\'申请店铺绑定\',onClose:function(){if(window.finderGroup && window.finderGroup[\'%s\']){window.finderGroup[\'%s\'].refresh();}}}">申请店铺绑定</a>', $shop_id, $finder_id, $finder_id, $finder_id);
        }
        
        // 支付宝绑定授权
        if ($node_type == 'taobao' && $adapter !== 'openapi') {
            $button2 .= $row[$this->col_prefix . 'alipay_authorize'] == 'true' ? ' | 支付宝已授权' : sprintf(' | <a href="index.php?app=ome&ctl=admin_shop&act=apply_bindrelation&p[0]=%s&p[1]=%s&p[2]=%s&p[3]=pay&finder_id=%s" target="dialog::{width:930,title:\'申请店铺绑定\',onClose:function(){window.finderGroup[\'%s\'].refresh();}}">申请支付宝授权</a>', $app_id, $callback_url, $api_url, $finder_id,$finder_id);
        }elseif($node_type == 'luban' && $node_id){
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
        if ($extend_button_service = kernel::servicelist('ome.shop.finder')) {
            foreach ($extend_button_service as $object => $instance) {
                if (method_exists($instance, 'operator_button')) {
                    $extend_button .= ' | ' . $instance->operator_button($shop_id, $shop_name, $node_id, $node_type);
                }
            }
        }
        if (strlen($extend_button) < '6') {
            $extend_button = '';
        }
        if ($node_type == '360buy' && $adapter !== 'openapi'){


            $noshopbutton =   sprintf(" | <a href='index.php?app=ome&ctl=admin_shop&act=noshopapply_bindrelation&p[0]=%s&p[1]=%s&p[2]=%s' target='dialog::{width:800,title:\"京东授权\",onClose:function(){window.finderGroup[\"%s\"].refresh();}}'>京东授权</a>", $api_url,$callback_url,$node_type,$finder_id);  
         }
        return $button1 . $button3 . $button2 . $extend_button.$noshopbutton;
    }

    /**
     * detail_jzorderdelivery
     * @param mixed $shop_id ID
     * @return mixed 返回值
     */
    public function detail_jzorderdelivery($shop_id)
    {
        $render = app::get('ome')->render();
        $oShop  = app::get('ome')->model("shop");
        $shop   = $oShop->dump($shop_id, 'shop_type');
        if (in_array($shop['shop_type'], array('taobao'))) {
            $render->pagedata['shop_id']     = $shop_id;
            $render->pagedata['jzorderconf'] = app::get('ome')->getConf('shop.jzorder.config.' . $shop_id);

            return $render->fetch("admin/system/jzorderdelivery.html");
        }

    }

    /**
     * detail_jd_dly_corp
     * @param mixed $shop_id ID
     * @return mixed 返回值
     */
    public function detail_jd_dly_corp($shop_id)
    {
        $render = app::get('ome')->render();
        if ($_POST) {
            $jdcorp_config = array(
                'config'  => $_POST['config'],
                'is_cod'  => $_POST['is_cod'],
                'corp_id' => $_POST['corp_id'],
            );
            app::get('ome')->setConf('shop.jdcorp.config.' . $shop_id, $jdcorp_config);
        }
        $db                           = kernel::database();
        $dlycorps                     = $db->select("SELECT d.corp_id, d.name, d.type FROM sdb_ome_dly_corp as d LEFT JOIN sdb_logisticsmanager_channel as c on d.channel_id=c.channel_id WHERE c.channel_type='360buy' AND d.disabled='false'");
        $render->pagedata['shop_id']  = $shop_id;
        $render->pagedata['dlycorps'] = $dlycorps;

        $dlycorpconf                     = app::get('ome')->getConf('shop.jdcorp.config.' . $shop_id);
        $render->pagedata['iscod']       = array('true');
        $render->pagedata['noiscod']     = array('false');
        $render->pagedata['dlycorpconf'] = $dlycorpconf;

        $shopObj                      = app::get('ome')->model("shop");
        $shopData                     = $shopObj->dump($shop_id);
        $render->pagedata['shopData'] = $shopData;
        return $render->fetch("admin/system/jd_dly_crop.html");
    }

    /**
     * detail_aligenius
     * @param mixed $shop_id ID
     * @return mixed 返回值
     */
    public function detail_aligenius($shop_id)
    {
        $render = app::get('ome')->render();
        $oShop  = app::get('ome')->model("shop");
        $shop   = $oShop->dump($shop_id, 'shop_type');
        if (in_array($shop['shop_type'], array('taobao','360buy','luban','xhs','pinduoduo','ecos.ecshopx','website'))) {
            $render->pagedata['shop_id']        = $shop_id;
            $render->pagedata['aligenius_conf'] = app::get('ome')->getConf('shop.aliag.config.' . $shop_id);
            $render->pagedata['shop_type'] = $shop['shop_type'];
            $render->pagedata['refund_aligenius_conf'] = app::get('ome')->getConf('shop.refund.aliag.config.' . $shop_id);
        }

        return $render->fetch("admin/system/aligenius.html");
    }

    /**
     * detail_cnatuto
     * @param mixed $shop_id ID
     * @return mixed 返回值
     */
    public function detail_cnatuto($shop_id)
    {
        $render = app::get('ome')->render();
        if (intval($_POST['branch_id']) > 0) {
            app::get('ome')->setConf('shop.cnauto.set.' . $shop_id, $_POST['branch_id']);
        }
        $auto_branch_id                     = app::get('ome')->getConf('shop.cnauto.set.' . $shop_id);
        $render->pagedata['auto_branch_id'] = $auto_branch_id;
        $branchObj                          = app::get('ome')->model('branch');
        $branch_list                        = $branchObj->getList('branch_id, branch_bn, name', array('is_deliv_branch' => 'true'));
        $render->pagedata['branch_list']    = $branch_list;
        unset($branch_list);
        $oShop                        = app::get('ome')->model("shop");
        $shopData                     = $oShop->dump($shop_id, 'shop_type');
        $render->pagedata['shopData'] = $shopData;
        return $render->fetch("admin/system/cnauto.html");
    }

    /**
     * 仓库配置
     * 
     * @return void
     * @author 
     * */
    public function detail_branch($shop_id)
    {
        $render = app::get('ome')->render();

        $shop = app::get('ome')->model("shop")->dump($shop_id,'shop_bn');

        $branches = [];

        $rel = app::get('ome')->getConf('shop.branch.relationship');

        if ($rel[$shop['shop_bn']]) {
            $branches = app::get('ome')->model('branch')->getList('branch_id,branch_bn,cutoff_time,latest_delivery_time,name',[
                'branch_bn' => $rel[$shop['shop_bn']],
                'skip_permission' => false,
            ]);

            foreach ($branches as &$value) {
                $value['cutoff_time'] = substr($value['cutoff_time'], 0, 2).':'.substr($value['cutoff_time'], 2, 2);
                $value['latest_delivery_time'] = substr($value['latest_delivery_time'], 0, 2).':'.substr($value['latest_delivery_time'], 2, 2);
            }
        }

        $render->pagedata['branches'] = $branches;
        $render->pagedata['shop_id'] = $shop_id;

        return $render->fetch("admin/system/shop_branch.html");
    }
    
    /**
     * 店铺基本配置
     * @param $shop_id
     * @return mixed
     */
    public function detail_basic_config($shop_id)
    {
        $render = app::get('ome')->render();
        $shop = app::get('ome')->model("shop")->dump($shop_id,'shop_id,config,node_type');
        $shop['config'] = @unserialize($shop['config']);
    
        $agingConf = app::get('ome')->getConf('ome.aging.long.positions');
        if($agingConf == 'true') {
            $agingShopConf = app::get('ome')->getConf('ome.aging.long.positions.shop');
            if($agingShopConf && in_array($shop_id, $agingShopConf)) {
                $render->pagedata['aging'] = 'true';
            }
        }

        $render->pagedata['shop'] = $shop;
    
        return $render->fetch("admin/system/shop_config.html");
    }
    
    var $column_aoxiang_signed = '翱象签约状态';
    var $column_aoxiang_signed_width = 130;
    function column_aoxiang_signed($row)
    {
        $aoxiang_signed = '';
        if(in_array($row['shop_type'], array('taobao', 'tmall'))){
            $aoxiang_signed = $row[$this->col_prefix . 'aoxiang_signed'];
            $aoxiang_signed = $this->_signedList[$aoxiang_signed];
        }
        
        return $aoxiang_signed;
    }
    
    /**
     * 翱象配置
     * 
     * @param $shop_id
     * @return false|string|null
     */
    public function detail_aoxiang($shop_id)
    {
        $render = app::get('ome')->render();
        
        //shop
        $shopInfo = app::get('ome')->model("shop")->dump($shop_id, '*');
        
        //[翱象系统]同步仓库作业信息
        $aoxiangLib = kernel::single('dchain_aoxiang');
        $isAoxiang = $aoxiangLib->isSignedShop($shop_id, $shopInfo['shop_type']);
        
        //value
        $render->pagedata['isAoxiang'] = $isAoxiang;
        
        //check
        if(!$isAoxiang){
            return $render->fetch("admin/system/shop_aoxiang_config.html");
        }
        
        //get config
        $aoxiangConfig = app::get('ome')->getConf('shop.aoxiang.config.'. $shop_id);
        $aoxiangConfig = json_decode($aoxiangConfig, true);
        
        $render->pagedata['aoxiangConfig'] = $aoxiangConfig;
        $render->pagedata['shop_id'] = $shop_id;
        $render->pagedata['shop'] = $shopInfo;
        
        return $render->fetch("admin/system/shop_aoxiang_config.html");
    }
}
