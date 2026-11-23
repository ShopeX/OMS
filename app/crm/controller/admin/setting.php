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

class crm_ctl_admin_setting extends desktop_controller{

    var $workground = 'channel_center';
    #申请CRM绑定
    function apply_bindrelation($app_id='ome', $callback='', $api_url='') {
        $this->Certi = base_certificate::get('certificate_id');
        $this->Token = base_certificate::get('token');
        $this->Node_id = base_shopnode::node_id($app_id);
        $token = $this->Token;
        $sess_id = kernel::single('base_session')->sess_id();
        $apply['certi_id'] = $this->Certi;
        if ($this->Node_id)
            $apply['node_idnode_id'] = $this->Node_id;
        $apply['sess_id'] = $sess_id;
        $str = '';
        ksort($apply);
        foreach ($apply as $key => $value) {
            $str.=$value;
        }
        $apply['certi_ac'] = md5($str . $token);
        $Ofunc = kernel::single('ome_rpc_func');
        $app_xml = $Ofunc->app_xml();
        $api_v = $app_xml['api_ver'];
        $callback = kernel::base_url(true).kernel::url_prefix().'/openapi/crm.channel/crm_callback/';
        //$api_url = "http://192.168.132.19/index.php/api";
        $api_url = kernel::base_url(true).kernel::url_prefix()."/api";

        $params = array(
            'source'    => 'apply',
            'api_v'     => $api_v,
            'certi_id'  => $apply['certi_id'],
            'node_id'   => $apply['node_idnode_id'],
            'sess_id'   => $apply['sess_id'],
            'certi_ac'  => $apply['certi_ac'],
            'callback'  => $callback,
            'api_url'   => $api_url,
            'show_type' => 'shopex',
        );

        $this->pagedata['license_iframe'] = sprintf('<iframe width="100%%" frameborder="0" height="99%%" id="iframe" onload="this.height=document.documentElement.clientHeight-4" src="%s" ></iframe>',MATRIX_RELATION_URL . '?' . http_build_query($params));
        
        // $this->pagedata['license_iframe'] = '<iframe width="100%" frameborder="0" height="99%" id="iframe" onload="this.height=document.documentElement.clientHeight-4" src="' . MATRIX_RELATION_URL . '?source=apply&api_v='.$api_v.'&certi_id=' . $apply['certi_id'] . '&node_id=' . $apply['node_idnode_id'] . '&sess_id=' . $apply['sess_id'] . '&certi_ac=' . $apply['certi_ac'] . '&callback=' . $callback . '&api_url=' . $api_url.'" ></iframe>';
        $this->display('admin/system/apply_terminal.html');
    }    
    #crm绑定与配置页面
    public function basic(){
        $obj_channel = app::get('channel')->model('channel');
        $oLog = app::get('ome')->model('operation_log');
        $operation_data = $oLog->read_log(array('obj_type'=>'gift@crm'));
        if(!empty($operation_data)){
            krsort($operation_data);
            $this->pagedata['operation_data'] = $operation_data;
        }else{
            $this->pagedata['operation_data'] = null;
        }

        #验证crm有没有绑定
        $crmInfo = $obj_channel->valiCrmInfo();
        $data = null;
        if(empty($crmInfo['channel_id'])||empty($crmInfo['node_id'])){
            $this->pagedata['crmInfo'] = 0;
        }else{
            $this->pagedata['crmInfo'] = 1;
            #设置crm基本配置时,获取店铺类型
            $shop_info = $obj_channel->getShopType();
            
            $data = $this->app->getConf('crm.setting.cfg');
             
            if($data){
                $this->pagedata['data'] = $data;
            }
            #获取店铺订单类型
            $order_type = $this->getOrderType(); 
            $this->pagedata['shop_info'] =  $shop_info;

            $this->pagedata['order_type'] = $order_type;
            

            $this->pagedata['checked_order'] = $data['order_type'];
            #前一次操作的店铺
            $this->pagedata['checked_shop'] = $data['name'];
        }
        #如果是第一次配置crm时，前端只显示开关按钮，且默认是关闭
        if(empty($data)){
            $this->pagedata['basic'] = 0;
        }else{
            $this->pagedata['basic'] = 1;
        }
        #赠品的数量统计,如果赠品数量为0，红字相关提醒
        $gift_count = app::get('crm')->model('gift')->count();
        $this->pagedata['gift_count'] = $gift_count;
        $this->pagedata['server_name'] = $_SERVER['SERVER_NAME'];
        $this->pagedata['shopex_shop_type'] = $this->shopex_shop_type();
        $this->page('admin/setting.html');
    }


    #提交基本配置时，路由到此
    public function add(){
        $oLog = app::get('ome')->model('operation_log');
        $post = kernel::single('base_component_request')->get_post();
       
        $this->begin();
        if($post['crm']['gift'] == 'off'){
            #点击关闭按钮时，清除所有配置数据
            $this->app->setConf('crm.setting.cfg',null);
            $oLog->write_log('crm_off@ome',0,"关闭CRM赠品应用");
            $this->end(false,'配置数据已经清除完成！');
            exit;
        }
        $conf = $this->options();
        if ($post['crm']) {
            $crm = $post['crm'];
            $_gift= array_search($crm['gift'], $conf['gift']);
            $_nostock = array_search($crm['nostock'], $conf['nostock']);
            $_error = array_search($crm['error'], $conf['error']);
            if(empty($crm['name'])){
                $this->end(false,'请选择店铺！');
            }
            if($_gift === false){
                $this->end(false,'请选择开启或关闭！');
            }
            $shop = array_values($crm['name']);
            #店铺类型对应的订单类型
            $static_shop_type = self::getOrderType();
 
            #如果店铺有订单类型，则需要检查订单类型
            foreach( $shop as $_shop){
                $_shopinfo = explode('===', $_shop);
                $_shop_type = $_shopinfo[0];#店铺 类型
                $_order_type = $_shopinfo[1];#订单的类型
                #如果店铺有订单类型，则需要检查订单类型
                if(!empty($static_shop_type[$_shop_type])){
                    if(empty($crm['order_type'][$_order_type])){
                        $this->end(false,'请选择店铺订单类型！');
                    }
                }
            }
            if(!empty($crm['order_type'])){
                $order_type = array_keys($crm['order_type']);
                #如果有订单类型，一定要选择来源店铺
                foreach( $order_type as $_order_type){
                    if(empty($crm['name'][$_order_type])){
                        $this->end(false,'请选择店铺！');
                    }
                }
            }

            if($_error === false){
                $this->end(false,'请选择出错时的处理方案！');
            }
           
            $this->app->setConf('crm.setting.cfg',$crm);
        }else{
            $this->end(false,'请选择相关基本设置!');
        }
        $oLog->write_log('crm_on@ome',0,"开启CRM赠品应用");
        $this->end(true,'保存成功');
    }
    public function options(){
       return array(
                   'gift'=>array('off','on'),
                   'nostock'=>array('off','on'),
                   'error'=>array('off','on'),
               );
    }
    #获取店铺订单类型
    public static function getOrderType(){
        return  array(
                    //'taobao' => array('Z'=>'直销订单','F'=>'分销订单'),
                    'shopex_b2b' => array('b2c'=>'独立网站订单','fxjl'=>'抓抓订单','taofenxiao'=>'淘分销订单')
                );
    }
   #页面上关闭crm配置后,删除kv中的crm配置信息
    function removeSetting(){
        $oLog = app::get('ome')->model('operation_log');
        $oLog->write_log('crm_off@ome',0,"关闭CRM赠品应用");
        $this->app->setConf('crm.setting.cfg',null);
        return 1;
    } 
    #shopex前端店铺列表
    function shopex_shop_type(){
        $shop = array(
                    'shopex_b2b'=>'shopex_b2b',
                    'shopex_b2c'=>'shopex_b2c',
                    'ecos.b2c'=>'ecos.b2c',
                    'ecshop_b2c'=>'ecshop_b2c',
                    'ecos.dzg'=>'ecos.dzg'
                );
        return $shop;
    }   
    
    
}