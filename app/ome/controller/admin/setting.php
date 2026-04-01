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
class ome_ctl_admin_setting extends desktop_controller{
    var $name = "基本设置";
    var $workground = "setting_tools";
    var $view_source = 'normal';

    private $tabs = array(
        'order' => '订单配置',
        'purchase' => '仓储采购',
        'preprocess' => '预处理配置',
        'other' => '其他配置',
    );
    
    //列表分栏菜单
    public function _views()
    {
        $sub_menu = array();
        
        if ($this->view_source == 'problem') {
            $sub_menu = $this->_viewsProblem();
        }
        
        return $sub_menu;
    }
    
    public function _viewsProblem()
    {
        $problemObj = $this->app->model('return_product_problem');
        
        $sub_menu = array(
                0 => array('label'=>app::get('base')->_('全部'), 'filter'=>array(), 'optional' => false),
                // 1 => array('label'=>app::get('base')->_('店铺售后原因'), 'filter'=>array('problem_type'=>'shop'), 'optional'=>false),
                // 2 => array('label'=>app::get('base')->_('WMS售后原因'), 'filter'=>array('problem_type'=>'wms'), 'optional'=>false),
        );
        
        foreach ($sub_menu as $k => $v)
        {
            $sub_menu[$k]['filter'] = $v['filter'] ? $v['filter'] : null;
            $sub_menu[$k]['addon'] = $problemObj->count($v['filter']);
            $sub_menu[$k]['href'] = "index.php?app=ome&ctl=admin_setting&act=product_problem&view=". $k;
        }
        
        return $sub_menu;
    }
    
    private function _comp_setting($arr1,$arr2){
        if($arr1["order"] == $arr2["order"])return 0;return $arr1["order"] > $arr2["order"] ? 1 : -1;
    }
    
    public function index()
    {
        $basicMaterialObj    = app::get('material')->model('basic_material');
        $basicMaterialBarcode    = kernel::single('material_basic_material_barcode');
        $opObj    = app::get('ome')->model('operation_log');
        
        //配置信息保存
        if($_POST['set']){
            $settins = $_POST['set'];
            
            if($settins['ome.cn.order.Auto'] != 'true'){
                $settins['ome.cn.order.Auto.bindshop'] = array();
            }
            $old_on_cnorder_shop = app::get('ome')->getConf('ome.cn.order.Auto.bindshop');
            $current_on_cnorder_shop = $settins['ome.cn.order.Auto.bindshop'];
            
            if($current_on_cnorder_shop){
                if($current_on_cnorder_shop){
                    #上次开启强制流转，现在又关闭的，需要告知
                    $current_off_cnorder_shop = array_diff($old_on_cnorder_shop,$current_on_cnorder_shop);
                }
            }else{
                #如果本次完全关闭了强制流转按钮，则上次已开启流转的所有店铺，都要通知已关闭规则
                $current_off_cnorder_shop = $old_on_cnorder_shop;
            }
            $this->begin();
            if($settins['ome.product.serial.merge']=='true' && !empty($settins['ome.product.serial.separate'])){
                $settins['ome.product.serial.separate'] = trim($settins['ome.product.serial.separate']);
                if(strlen($settins['ome.product.serial.separate'])>1){
                    $this->end(false,'分隔符只允许是一个字符');
                }
                if(preg_match("/([a-zA-Z]{1}|[0-9]{1})/i", $settins['ome.product.serial.separate'])){
                    $this->end(false,'分隔符不允许是字母或数字');
                }
                
                #查询关联的条形码bm_id
                $filter['code|has'] = $settins['ome.product.serial.separate'];
                $bm_ids    = $basicMaterialBarcode->getBmidListByFilter($filter, $code_list);
                
                #查询基础物料
                $filter    = array('bm_id'=>$bm_ids);
                $checkInfo    = $basicMaterialObj->getList('bm_id', $filter, 0, 1);
                $checkInfo    = $checkInfo[0];
                
                if($checkInfo['product_id']>0){
                    $this->end(false,'现有商品条形码中存在此分隔符');
                }
            }else{
                unset($settins['ome.product.serial.separate']);
            }

           if(!isset($settins['ome.combine.addressconf']['ship_address']) && !isset($settins['ome.combine.addressconf']['mobile'])) {
                $this->end(false,'相同地址判定中,收货地址和手机至少选择一个!');
            }
            
            //自动审单配置
            $old_is_auto_combine    = $this->app->getConf('ome.order.is_auto_combine');
            $now_is_auto_combine    = $settins['ome.order.is_auto_combine'];
            
            if($old_is_auto_combine != $now_is_auto_combine)
            {
                if($now_is_auto_combine == 'true')
                {
                    $log_msg   = '开启自动审单';
                }
                else
                {
                    $log_msg   = '关闭自动审单';
                }
                $opObj->write_log('order_split@ome', 0, $log_msg);
            }
            
            //选择指定时间判断处理
            if($settins["ome.order.auto_timer"] == 2){ //指定时间
                if(empty($settins["ome.order.auto_exec_timer"])){
                    $this->end(false,'缺失时间段的数据!');
                }
                $current_date = date('Y-m-d');
                $arr_timer_range = array();
                $new_auto_exec_timer = array();
                $new_key = 1;
                foreach ($settins["ome.order.auto_exec_timer"] as $var_aet){
                    $start_time_int = strtotime($current_date.' '.intval($var_aet["start_hour"]).':'.intval($var_aet["start_minute"]).':00');
                    $end_time_int = strtotime($current_date.' '.intval($var_aet["end_hour"]).':'.intval($var_aet["end_minute"]).':00');
                    if ($start_time_int >= $end_time_int){
                        $this->end(false,'结束时间必须大于起始时间!');
                    }
                    if (!empty($arr_timer_range)){
                        foreach ($arr_timer_range as $var_tr){
                            if ($start_time_int >= $var_tr["end_time_int"] || $end_time_int <= $var_tr["start_time_int"]){
                            }else{ //时间段存在交集
                                $this->end(false,'时间段存在交集，请修改!');
                            }
                            //压入新的时间范围
                            $arr_timer_range[] = array(
                                "start_time_int" => $start_time_int,
                                "end_time_int" => $end_time_int,
                            );
                        }
                    }else{ //首个时间区间
                        $arr_timer_range[] = array(
                            "start_time_int" => $start_time_int,
                            "end_time_int" => $end_time_int,
                        );
                    }
                    $new_auto_exec_timer[$new_key] = $var_aet;
                    $new_key++;
                }
                $settins["ome.order.auto_exec_timer"] = $new_auto_exec_timer;
            }else{ //所有时间 不做指定时间范围更新
                unset($settins["ome.order.auto_exec_timer"]);
            }
            
            //复审配置
            if( !isset($settins['ome.order.retrial']['product'])){
                $settins['ome.order.retrial']['product'] = 0;
            }
            if( !isset($settins['ome.order.retrial']['order'])){
                $settins['ome.order.retrial']['order'] = 0;
            }
            if( !isset($settins['ome.order.retrial']['delivery'])){
                $settins['ome.order.retrial']['delivery'] = 0;
            }
            
            if( !isset($settins['ome.order.cost_multiple']['flag'])){
                $settins['ome.order.cost_multiple']['flag'] = 0;
            }
            if( !isset($settins['ome.order.sales_multiple']['flag'])){
                $settins['ome.order.sales_multiple']['flag'] = 0;
            }
            
            // 报备外呼主叫号码组验证
            if(isset($settins['ome.call.mobile.group'])){
                $mobileGroup = trim($settins['ome.call.mobile.group']);
                if(!empty($mobileGroup)){
                    $lines = explode("\n", $mobileGroup);
                    $validMobiles = [];
                    $mobilePattern = '/^1[3-9]\d{9}$/'; // 手机号正则：11位，1开头，第二位3-9
                    
                    foreach($lines as $line){
                        $line = trim($line);
                        if(!empty($line)){
                            if(preg_match($mobilePattern, $line)){
                                $validMobiles[] = $line;
                            } else {
                                $this->end(false, '手机号格式错误：'. $line .'（请输入11位手机号，1开头，第二位3-9）');
                            }
                        }
                    }
                    
                    // 保存时去除重复并重新组合
                    $beforeCount = count($validMobiles);
                    $validMobiles = array_unique($validMobiles);
                    $afterCount = count($validMobiles);
                    
                    // 去重后重新检查数量限制
                    if($afterCount > 80){
                        $this->end(false, '报备外呼主叫号码组最多只能添加80个手机号，当前已输入'. $afterCount .'个（去重后）');
                    }
                    
                    // 如果有重复，记录日志提示
                    if($beforeCount > $afterCount){
                        $duplicateCount = $beforeCount - $afterCount;
                        // 可以记录日志，但不阻止保存
                    }
                    
                    $settins['ome.call.mobile.group'] = implode("\n", $validMobiles);
                } else {
                    $settins['ome.call.mobile.group'] = '';
                }
            }
            
            // 开票配置
            $settins['ome.invoice.amount.infreight'] = $settins['ome.invoice.amount.infreight']?:0;

            #订单拆单配置
            if($settins['ome.order.split'] == '1')
            {

                #配置日志
                $log_msg   = '开启拆单功能;';

                $log_msg   .= '-';
                $log_msg   .= ($settins['ome.order.split_type'] == '1' ? '回写第一张' : '回写最后一张');
                
                $opObj->write_log('order_split@ome', 0, $log_msg);
            }
            else
            {
                #关闭拆单功能_配置日志
                if($this->app->getConf('ome.order.split') == '1')
                {
                    $log_msg   = '关闭拆单功能';
                    $opObj->write_log('order_split@ome', 0, $log_msg);
                }
                
                #注销拆单配置
                $settins['ome.order.split']          = '';
                $settins['ome.order.split_type']    = '';

            }
            foreach($settins as $set=>$value){
                $curSet = $this->app->getConf($set);

                if($curSet!=$settins[$set]){
                    $curSet = $settins[$set];
                    $this->app->setConf($set,$settins[$set]);
                }
            }

            if(!isset($settins['ome.combine.addressconf']['ship_address'])){
                $settins['ome.combine.addressconf']['ship_address'] = 1;
            }

            if( !isset($settins['ome.combine.addressconf']['mobile'])){
                $settins['ome.combine.addressconf']['mobile'] = 1;
            }
            if($settins['ome.delivery.weight'] == 'on'){
               $this->app->setConf('ome.delivery.check_delivery','off');#称重开启后，关闭校验完即发货功能
             }
             //保持财务账期设置
            $init_time = $_POST['init_time'];
            if($init_time){
                $init_time['flag'] = 'true';
    
            }

            //如果提交的内容值有变化才更新
            // foreach($settins as $set=>$value){
            //     $curSet = app::get('ome')->getConf($set);
            //     if($curSet!=$settins[$set]){
            //         $curSet = $settins[$set];
            //         app::get('ome')->setConf($set,$settins[$set]);
            //     }
            // }

            //库存成本保存
            // if($settins['ome.delivery.weight'] == 'off'){
            //     $this->app->setConf('ome.delivery.logi','0');
            // }
            if($_POST['extends_set']){
                foreach(kernel::servicelist('system_setting') as $k=>$obj){
                    if(method_exists($obj,'save')){
                       if($obj->save($_POST['extends_set'],$msg) === false) $this->end(false,$msg);
                    }
                }
            }

            //扩展配置信息保存
            foreach(kernel::servicelist('system_setting') as $k=>$obj){
                if(method_exists($obj,'saveConf')){
                    $obj->saveConf($settins);
                }
            }
            if($current_on_cnorder_shop){
                foreach($current_on_cnorder_shop as $shop_id){
                    kernel::single('ome_event_trigger_shop_logistics')->syncOrderRule($shop_id,'true');
                }
            }
            #上次开启自动流转，本次又关闭自动流转的店铺，进行订单处理规则同步
            if($current_off_cnorder_shop){
                foreach($current_off_cnorder_shop as $shop_id){
                    kernel::single('ome_event_trigger_shop_logistics')->syncOrderRule($shop_id,'false');
                }
            }

            

            $this->end(true,'保存成功');
        }

        // 系统配置显示
        //$settingTabs = array(
        //    array('name' => '订单配置', 'file_name' => 'admin/system/setting/tab_order.html', 'app' => 'ome'),
        //    array('name' => '仓储采购', 'file_name' => 'admin/system/setting/tab_storage.html', 'app' => 'ome'),
        //    array('name' => '发货校验', 'file_name' => 'admin/system/setting/tab_delivery.html', 'app' => 'ome'),
        //    array('name' => '预处理配置', 'file_name' => 'admin/system/setting/tab_preprocess.html', 'app' => 'ome'),
        //    array('name' => '订单复审设置', 'file_name' => 'admin/system/setting/tab_retrial.html', 'app'=>'ome', 'order' => 30),
        //    array('name' => '其他配置', 'file_name' => 'admin/system/setting/tab_other.html', 'app'=>'ome'),
        //);
        $settingTabs = array();
        $setData = array();
        // $setView = array();

        // 读取所有可配置项
        $setting_info = array();

        //其他的配置暂时不动，直接赋值，后面细分到具体app
        // $show_tabs = $this->tabs;

        $servicelist = kernel::servicelist('system_setting');

        //配置信息的加载
        foreach($servicelist as $k=>$obj){

            //顶部tab页
            // if(isset($obj->tab_key) && isset($obj->tab_name)){
            //     $show_tabs = array_merge($show_tabs,array($obj->tab_key=>$obj->tab_name));
            // }

            //具体配置参数
            if(method_exists($obj,'all_settings')){
                $setting_info = array_merge($setting_info,$obj->all_settings());
            }

            if (method_exists($obj, 'get_setting_tab')) {
                $settingTabs = array_merge($settingTabs, $obj->get_setting_tab());
            }

            if (method_exists($obj,'get_pagedata')) {
                $obj->get_pagedata($this);
            }

            if (method_exists($obj,'get_setting_data')) {
                $setData = array_merge($setData,$obj->get_setting_data());
            }
        }

        uasort($settingTabs,array($this,'_comp_setting'));

        // 获取配置项值
        // foreach($setting_info as $set){
        //     $key = str_replace('.','_',$set);
        //     $setData[$key] = app::get('ome')->getConf($set);
        // }
        //因为老数据的问题，扩展的信息赋值放在全局赋值后面
        // foreach($servicelist as $k=>$obj){
        //     if(method_exists($obj, 'getView')){
        //         $setView[] = $obj->getView();
        //     }
        // }
        // if($_GET['pos']){
        //     $this->pagedata['display_pos'] = $_GET['pos'];
        // }
        #快递单与称重的顺序标示
        // if(!isset($setData['ome_delivery_logi'])){
        //     $setData['ome_delivery_logi'] = '0';
        // }
        
        // if($_GET['pos']){
        //     $this->pagedata['display_pos'] = $_GET['pos'];
        // }
        #快递单与称重的顺序标示
        // if(!isset($setData['ome_delivery_logi'])){
        //     $setData['ome_delivery_logi'] = '0';
        // }
        #逐单校验后即发货,默认是关闭的
        if(!isset($setData['ome_delivery_check_delivery'])){
            $setData['ome_delivery_check_delivery'] = 'off';
        }
        #称重开启，校验完即发货功能,默认是关闭的
        if($settins['ome.delivery.weight'] == 'on'){
            $setData['ome_delivery_check_delivery'] = 'off';
        }
        #华强宝默认是开启的
        if(!isset($setData['ome_delivery_hqepay'])){
            $setData['ome_delivery_hqepay'] = 'true';
        }

        $this->pagedata['settingTabs'] = $settingTabs;
        $this->pagedata['setData'] = $setData;
        $this->pagedata['branchCount'] = count(app::get('ome')->model('branch')->Get_branchlist());
        // $this->pagedata['setView']=$setView;
        $this->pagedata['show_tabs'] = $show_tabs;
        
        //tab门店配置页
        if(app::get('o2o')->is_installed()){
            //是否开启销单校验码：需要判断是否开通短信 在开通的情况下才能选择 “是”
            base_kvstore::instance('taoexlib')->fetch('account', $account);
            if(unserialize($account)){
                $this->pagedata["sms_active"] = true;
            }
        }
        //预售显示
        $presalehtml = 0;
        if (app::get('presale')->is_installed()) {
            $presalehtml = 1;
        }

        $this->pagedata['presalehtml'] = $presalehtml;
        
        //[拆单]未处理的订单[部分拆分、部分发货]
        if($setData['ome_order_split'] == '1')
        {
            $order_split_list    = $this->get_order_split_info();
            
            $this->pagedata['order_num']    = $order_split_list['order_num'];
            $this->pagedata['order_list']   = $order_split_list['order_list'];
        }
        //财务
    
        #账单起始年月日
        $tyear = date('Y');
        $tmonth = date('m',strtotime("-1 month"));
       if ($tmonth==12){
            $tyear -= 1;
        }
        for($d=1;$d<=28;$d++){
            $day[$d] = $d.'日';
        }
        $init_time = app::get('finance')->getConf('finance_setting_init_time');

        $finance_set = (!isset($init_time['day'])||$init_time['day']=='')?'off':'on';

        //判断是否安装财务模块
        if(app::get('finance')->is_installed()){
            $this->pagedata['finance_installed'] = 'yes';
        }
        #获取淘宝绑定店铺
        $this->pagedata['taobao_bind_shop'] = app::get('ome')->model('shop')->getList('shop_id,name,node_id',array('node_type'=>'taobao','node_id|noequal'=>''));
        $this->pagedata['tyear'] = $tyear;
        $this->pagedata['tmonth'] = ceil($tmonth);
        $this->pagedata['day'] = $day;
        $this->pagedata['init_time'] = $init_time;
        $this->pagedata['finance_set'] = $finance_set;
        
        //自动审单配置 显示需要
        $this->pagedata['c_hours'] = array_merge(array('00','01','02','03','04','05','06','07','08','09'),range(10,23));
        $this->pagedata['c_minutes'] = array_merge(array('00','01','02','03','04','05','06','07','08','09'),range(10,59));
        $this->pagedata['json_c_hours'] = json_encode($this->pagedata['c_hours']);
        $this->pagedata['json_c_minutes'] = json_encode($this->pagedata['c_minutes']);
        if(!empty($this->pagedata['setData']['ome_order_auto_exec_timer'])){
            $this->pagedata['count_auto_exec_timer'] = count($this->pagedata['setData']['ome_order_auto_exec_timer']);
        }
        
        $vopbill_set = app::get('ome')->getConf('ome.vopbill.set');
        $this->pagedata['vopbill_set'] = $vopbill_set;
        
       
        $this->page("admin/system/setting_index_all.html");
    }

    function app_list(){
        $rows = kernel::database()->select('select app_id,app_name from sdb_base_apps where status = "active"');
        $app_list = array();
        foreach($rows as $v){
           $app_list[] = $v['app_id'];
        }
        return $app_list;
    }
     /*
     * 订单异常类型设置
     */
    function abnormal(){
        $this->finder('ome_mdl_abnormal_type',array(
            'title'=>'订单异常类型设置',
            'actions'=>array(
                            array(
                                'label'=>'添加',
                                'href'=>'index.php?app=ome&ctl=admin_setting&act=addabnormal',
                                 'target' => 'dialog::{width:450,height:150,title:\'新建异常类型\'}'
                            ),
                        ),
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'=>false,
            'use_buildin_recycle'=>false,
            'use_buildin_export'=>false,
            'use_buildin_import'=>false,
         ));
    }
    /*
    * 添加订单异常类型
    */
    function addabnormal(){
        $oAbnormal = $this->app->model("abnormal_type");
        if($_POST){
            $this->begin('index.php?app=ome&ctl=admin_setting&act=abnormal');
            $oAbnormal->save($_POST['type']);
            $this->end(true, app::get('base')->_('保存成功'));
        }
        $this->pagedata['title'] = '添加订单异常类型';
        $this->page("admin/system/abnormal.html");
    }
    /*
    * 编辑订单异常类型
    */
    function editabnormal($type_id){
        $oAbnormal = $this->app->model("abnormal_type");
        $this->pagedata['abnormal']=$oAbnormal->dump($type_id);
        $this->pagedata['title'] = '编辑订单异常类型';
        $this->page("admin/system/abnormal.html");
    }
     /*
     * 售后问题类型设置
     */
    function product_problem()
    {
        $this->view_source = 'problem';
        
        $actions = array();
        $actions[] = array(
                'label'=>'添加',
                'href'=>'index.php?app=ome&ctl=admin_setting&act=addproblem',
                'target' => 'dialog::{width:500,height:300,title:\'新建售后原因\'}',
        );
        $actions[] = array(
                'label'=>'初始化',
                'href'=>'index.php?app=ome&ctl=admin_setting&act=initProblem',
                // 'target' => 'dialog::{width:500,height:300,title:\'新建售后原因\'}',
        );
        // if(empty($_GET['view']) || $_GET['view']=='1'){
        //     $actions[] = array(
        //             'label' => '同步店铺售后原因',
        //             'href' => 'index.php?app=ome&ctl=admin_setting&act=sync_shop_reason&finder_id='. $_GET['finder_id'],
        //             'target' => "dialog::{width:350,height:200,title:'同步平台店铺售后原因'}",
        //     );
        // }
        
        // if(empty($_GET['view']) || $_GET['view']=='2'){
        //     $actions[] = array(
        //             'label'=>'同步WMS售后原因',
        //             'href' => 'index.php?app=ome&ctl=admin_setting&act=sync_reason&finder_id='. $_GET['finder_id'],
        //             'target' => "dialog::{width:350,height:200,title:'同步第三方仓储WMS售后原因'}",
        //     );
        // }
        
        //list
        $this->finder('ome_mdl_return_product_problem',array(
            'title'=>'售后问题类型设置',
            'actions' => $actions,
            'use_buildin_filter'=>true,
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'=>false,
            'use_buildin_recycle'=>true,
            'use_buildin_export'=>false,
            'use_buildin_import'=>false,
         ));
    }

    /*
     * 添加售后问题
     */
    function addproblem()
    {
        $oProblem = $this->app->model("return_product_problem");
        if($_POST){
            $this->begin('index.php?app=ome&ctl=admin_setting&act=product_problem');
            
            //单据类型
            $problem_type = $_POST['problem_type'];
            
            $reason_id = trim($_POST['reason_id']);
            $reason_id = str_replace(array('"',"'"), '', $reason_id);
            
            $problem_name = trim($_POST['problem_name']);
            $problem_name = str_replace(array('"',"'"), '', $problem_name);
            
            //wms关联店铺原因
            $wms_reason_id = trim($_POST['wms_reason_id']);
            $wms_reason_id = str_replace(array('"',"'"), '', $wms_reason_id);
            $wms_reason_name = '';
            if($wms_reason_id){
                $wmsReasonInfo = $oProblem->dump(array('reason_id'=>$wms_reason_id), 'problem_id,reason_id,problem_name');
                if($wmsReasonInfo){
                    $wms_reason_id = $wmsReasonInfo['reason_id'];
                    $wms_reason_name = $wmsReasonInfo['problem_name'];
                }
            }
            
            //save
            $saveData = array(
                    'problem_type' => $problem_type,
                    'reason_id' => $reason_id,
                    'problem_name' => $problem_name,
                    'platform_type' => trim($_POST['platform_type']),
                    'disabled' => $_POST['disabled'],
                    'wms_reason_id' => $wms_reason_id,
                    'wms_reason_name' => $wms_reason_name,
                    'last_sync_time' => time(),
            );
            
            if($_POST['problem_id']){
                $saveData['problem_id'] = intval($_POST['problem_id']);
            }else{
                $saveData['createtime'] = time();
            }
            $oProblem->save($saveData);
            
            $this->end(true, app::get('base')->_('添加成功'));
        }
        
        /***
        //关联售后原因
        $shopReasonList = array();
        $tempList =$oProblem->getList('problem_id,reason_id,problem_name', array('problem_type'=>'shop'), 0, 100);
        if($tempList){
            foreach ($tempList as $key => $val)
            {
                $reason_id = $val['reason_id'];
                
                $shopReasonList[$reason_id] = $val['problem_name'];
            }
        }
        $this->pagedata['shopReasonList'] = $shopReasonList;
        ***/
        
        $this->pagedata['disabled_type'] = array('true'=>'是','false'=>'否');
        $this->pagedata['problem']['disabled'] = 'false';
        $this->page("admin/system/product_problem.html");
    }
    
    /*
     * 编辑售后问题
     */
    function editproblem($problem_id)
    {
        $oProblem = $this->app->model('return_product_problem');
        
        $problem = $oProblem->dump($problem_id);
        if(empty($problem)){
            die('售后原因信息不存在');
        }
        
        //关联售后原因
        $shopReasonList = array();
        $filter = array();
        if($problem['problem_type'] == 'wms'){
            $filter = array('problem_type'=>'shop');
        }else{
            $filter = array('problem_type'=>'wms');
        }
        
        $tempList =$oProblem->getList('problem_id,reason_id,problem_name', $filter, 0, 100);
        if($tempList){
            foreach ($tempList as $key => $val)
            {
                $reason_id = $val['reason_id'];
                
                $shopReasonList[$reason_id] = $val['problem_name'];
            }
        }
        
        $this->pagedata['shopReasonList'] = $shopReasonList;
        $this->pagedata['problem'] = $problem;
        $this->pagedata['disabled_type'] = array('true'=>'是','false'=>'否');
        $this->page("admin/system/product_problem.html");
    }

    /**
     * 收款账号管理
     */
    function set_collection_account()
    {
        $this->finder('ome_mdl_bank_account', array(
            'title' => '收款账号管理',
            'actions'=>array(
                array(
                    'label'=>'添加',
                    'href'=>'index.php?app=ome&ctl=admin_setting&act=add_bank_account',
                    'target' => 'dialog::{width:550,height:350,resizeable:false,title:\'新建银行账户\'}',
                ),
            ),
            'use_buildin_set_tag' => false,
            'use_buildin_filter' => true,
            'use_buildin_new_dialog' => false,
            'use_buildin_tagedit' => true,
            'use_buildin_export' => false,
            'use_buildin_import' => false,
            'use_buildin_recycle'=> true,
        ));
    }

    /**
     * 收款账号新增
     */
    function add_bank_account()
    {
        $bank_account = '';
        if(isset($_GET['ba_id'])){
            $ba_id = $_GET['ba_id'];
            $bank_account_obj = kernel::single('ome_mdl_bank_account');
            $bank_account = $bank_account_obj->getList('*', array('ba_id'=>$ba_id), 0, 1);
        }
        $this->pagedata['item'] = $bank_account[0] ? : [];
        $this->page('admin/system/bank_account.html');
    }

    public function do_add_bank_account()
    {
        $bank_acount = $this->app->model('bank_account');
        if($_POST){
            if($_POST['item']['ba_id'] != ''){
                // 修改
                $has_exists = $bank_acount->dump(array('ba_id' => $_POST['item']['ba_id']), '*');
                $this->begin('index.php?app=ome&ctl=admin_setting&act=set_collection_account');

                if($has_exists['account'] == $_POST['item']['account']){
                    $bank_acount->update($_POST['item'], array('ba_id' => $_POST['item']['ba_id']));
                    $this->end(true, app::get('base')->_('编辑成功'));
                } else {
                    $banks = $bank_acount->dump(array('account' => $_POST['item']['account']), '*');
                    if(!isset($banks['ba_id'])){
                        $bank_acount->update($_POST['item'], array('ba_id' => $_POST['item']['ba_id']));
                        $this->end(true, app::get('base')->_('编辑成功'));
                    } else {
                        $this->end(false, app::get('base')->_('账号重复'));
                    }
                }

            } else {
                // 添加
                $has_exists = $bank_acount->dump(array('account' => $_POST['item']['account']), '*');

                $this->begin('index.php?app=ome&ctl=admin_setting&act=set_collection_account');
                if($has_exists){
                    $this->end(false, app::get('base')->_('该账号已经存在，请勿重复添加'), 3);
                } else {
                    $bank_acount->save($_POST['item']);
                    $this->end(true, app::get('base')->_('添加成功'));
                }

            }
        }
    }

    /**
     * 获取部分拆分的订单和部分发货的订单
     */
    function get_order_split_info()
    {
        $fields     = "order_id, order_bn, shop_id, shop_type, process_status, ship_status, total_amount, last_modified";
        $where      = " WHERE (process_status='splitting' || ship_status='2') AND `status`='active' ";
        
        $order_num  = kernel::database()->select("SELECT count(*) as num FROM ".DB_PREFIX."ome_orders ".$where);
        $order_list = kernel::database()->select("SELECT ".$fields." FROM ".DB_PREFIX."ome_orders ".$where." ORDER BY order_id DESC LIMIT 5");
        
        #关联发货单_数量
        if($order_list)
        {
            //确认状态、发货状态
            $ship_array     = array (0 => '未发货', 1 => '已发货', 2 => '部分发货', 3 => '部分退货', 4 => '已退货');
            $process_array  = array('unconfirmed' => '未确认','confirmed' => '已确认','splitting' => '部分拆分',
                                    'splited' => '已拆分完', 'cancel' => '取消', 'remain_cancel' =>'余单撤销');
            
            //店铺
            $shop_list  = array();
            $oShop      = app::get('ome')->model('shop');
            $data_shop  = $oShop->getList('shop_id, name', null, 0, -1);
            foreach ($data_shop as $key => $val)
            {
                $sel_shop_id    = $val['shop_id'];
                $shop_list[$sel_shop_id]    = $val['name'];
            }
            
            $data_dly   = array();
            foreach ($order_list as $key => $val)
            {
                $sel_order_id   = $val['order_id'];
                $sql    = "SELECT dord.delivery_id, d.status FROM ".DB_PREFIX."ome_delivery_order AS dord
                            LEFT JOIN ".DB_PREFIX."ome_delivery AS d ON (dord.delivery_id=d.delivery_id)
                            WHERE dord.order_id=".$sel_order_id." AND (d.parent_id=0 OR d.is_bind='true') AND d.disabled='false'
                            AND d.status NOT IN('failed','cancel','back','return_back')";
                $data_dly   = kernel::database()->select($sql);
                
                $order_list[$key]['dly_count']  = count($data_dly);
                $order_list[$key]['delivery']   = $data_dly;
                $order_list[$key]['dly_succ']   = 0;
                
                foreach ($data_dly as $key_j => $val_j)
                {
                    if($val_j['status'] == 'succ')
                    {
                        $order_list[$key]['dly_succ']++;//已发货数量
                    }
                }
                
                $sel_shop_id    = $val['shop_id'];
                $ship_status    = $val['ship_status'];
                $process_status = $val['process_status'];
                $order_list[$key]['shop_name']       = $shop_list[$sel_shop_id];
                $order_list[$key]['ship_status']     = $ship_array[$ship_status];
                $order_list[$key]['process_status']  = $process_array[$process_status];
            }
        }
        
        $result    = array('order_num'=>$order_num[0]['num'], 'order_list'=>$order_list);
        
        return $result;
    }
    
    /**
     * 同步WMS仓储的售后原因
     */
    public function sync_reason()
    {
        $problemObj = app::get('ome')->model('return_product_problem');
        
        //路由列表
        $storage = array();
        $sql = "SELECT channel_id,node_id,channel_bn,channel_name AS node_name,node_type FROM sdb_channel_channel WHERE channel_type='wms' AND node_id !=''";
        $tempList = $problemObj->db->select($sql);
        foreach ($tempList as $key => $val)
        {
            if($val['node_id'] == 'selfwms'){
                continue;
            }
            
            //现只支持yjdf京东一件代发仓储
            if($val['node_type'] != 'yjdf'){
                continue;
            }
            
            $storage[] = $val;
        }
        
        //check
        if(empty($storage)){
            die('现只支持yjdf京东一件代发仓储同步售后原因!');
        }
        
        $this->pagedata['storage'] = $storage;
        $this->pagedata['finder_id'] = $_GET['finder_id'];
        $this->display('admin/system/sync_reason.html');
    }
    
    /**
     * 同步WMS仓储的售后原因
     */
    public function doSyncResaon()
    {
        $this->begin('index.php?app=ome&ctl=admin_setting&act=product_problem');
        
        $node_id = $_POST['node_id'];
        if(empty($node_id)){
            $this->end(false, '没有获取到WMS仓储路由');
        }
        
        //[京东一件代发]查询订单是否可申请售后
        $channelObj = app::get('channel')->model('channel');
        $channelInfo = $channelObj->dump(array('node_id'=>$node_id),'channel_id,node_id,node_type,channel_bn');
        if($channelInfo['node_type'] != 'yjdf')
        {
            $this->end(false, '现只支持yjdf京东一件代发仓储同步售后原因');
        }
        $channel_id = $channelInfo['channel_id'];
        
        //request
        $result = kernel::single('erpapi_router_request')->set('wms', $channel_id)->reship_resaon($channelInfo);
        if($result['rsp'] != 'succ')
        {
            $error_msg = $result['msg'];
            
            $this->end(false, $error_msg);
        }
        
        $this->end(true, app::get('base')->_('同步成功'), 3);
    }
    
    public function problemSetDefaulted($problem_id)
    {
        $finder_id = $_REQUEST['finder_id'];
        
        if(empty($problem_id)){
            echo "<script>alert('无效的操作！');top.finderGroup['". $finder_id ."'].refresh();</script>";
            exit;
        }
        
        //info
        $problemObj = $this->app->model('return_product_problem');
        $problemInfo = $problemObj->dump(array('problem_id'=>$problem_id), '*');
        if(empty($problemInfo)){
            echo "<script>alert('无效的操作！');top.finderGroup['". $finder_id ."'].refresh();</script>";
            exit;
        }
        
        //全部取消默认
        kernel::database()->query("UPDATE sdb_ome_return_product_problem SET defaulted='false' WHERE problem_type='". $problemInfo['problem_type'] ."'");
        
        //设置为默认
        $problemObj->update(array('defaulted'=>'true'), array('problem_id'=>$problem_id));
        
        echo "<script>alert('设置默认成功！！');top.finderGroup['". $finder_id ."'].refresh();</script>";
        exit;
    }

    public function problemWipeDefaulted($problem_id)
    {
        $finder_id = $_REQUEST['finder_id'];
        
        if(empty($problem_id)){
            echo "<script>alert('无效的操作！');top.finderGroup['". $finder_id ."'].refresh();</script>";
            exit;
        }
        
        //info
        $problemObj = $this->app->model('return_product_problem');
        $problemInfo = $problemObj->dump(array('problem_id'=>$problem_id), '*');
        if(empty($problemInfo)){
            echo "<script>alert('无效的操作！');top.finderGroup['". $finder_id ."'].refresh();</script>";
            exit;
        }
        
        //取消默认
        $problemObj->update(array('defaulted'=>'false'), array('problem_id'=>$problem_id));
        
        echo "<script>alert('取消默认成功！！');top.finderGroup['". $finder_id ."'].refresh();</script>";
        exit;
    }
    
    /**
     * 同步平台店铺售后原因
     */
    public function sync_shop_reason()
    {
        $shopObj = app::get('ome')->model('shop');
        
        //list
        $tempList = $shopObj->getList('shop_id,shop_bn,name,shop_type,node_id', array());
        if(empty($tempList)){
            die('没有店铺');
        }
        
        $shopList = array();
        foreach ($tempList as $key => $val)
        {
            if(empty($val['node_id'])){
                continue;
            }
            
            //只支持抖音平台
            if($val['shop_type'] != 'luban'){
                continue;
            }
            
            $shopList[] = $val;
        }
        
        //check
        if(empty($shopList)){
            die('没有可选择的店铺');
        }
        
        $this->pagedata['shopList'] = $shopList;
        $this->pagedata['finder_id'] = $_GET['finder_id'];
        $this->display('admin/system/sync_shop_reason.html');
    }
    
    /**
     * 同步平台店铺的售后原因
     */
    public function doSyncShopResaon()
    {
        $this->begin('index.php?app=ome&ctl=admin_setting&act=product_problem');
        
        $shopObj = app::get('ome')->model('shop');
        
        $shop_id = $_POST['shop_id'];
        if(empty($shop_id)){
            $this->end(false, '没有获取到店铺信息');
        }
        
        //店铺信息
        $shopInfo = $shopObj->dump(array('shop_id'=>$shop_id), 'shop_id,shop_bn,name,shop_type,node_id');
        if(empty($shopInfo['node_id'])){
            $this->end(false, '店铺信息不存在或未绑定');
        }
        
        //request
        $result = kernel::single('erpapi_router_request')->set('shop', $shopInfo['shop_id'])->aftersale_getReturnResaon($shopInfo);
        if($result['rsp'] != 'succ'){
            $error_msg = $result['msg'];
            
            $this->end(false, $error_msg);
        }
        
        $this->end(true, app::get('base')->_('同步成功'), 3);
    }

    /**
     * 初始化售后类型
     *
     * @return void
     * @author 
     **/
    public function initProblem()
    {
        $this->begin($this->url.'&act=product_problem');

        $problem_list = [
           '收到商品少件 / 错件 / 空包裹' ,
            '少件／漏发' ,
            '功能故障' ,
            '商家发错货',
            '不喜欢 / 效果不好',
            '做工粗糙 / 有瑕疵 / 有污渍' ,
            '商品材质 / 品牌 / 外观等描述不符',
            '生产日期 / 保质期 / 规格等描述不符',
            '大小／尺寸／重量与商品描述不符',
            '品种／规格／成分等描述不符',
            '品种／产品／规格／成分等描述不符',
            '规格等描述不符',
            '其他',
        ];

        $problemMdl = app::get('ome')->model('return_product_problem');
        foreach ($problem_list as $name) {

            $problem = $problemMdl->db_dump(['problem_name' => $name]);
            if (!$problem) {
                $problem = [
                    'problem_name' => $name,
                    'last_sync_time' => time(),
                    'createtime' => time(),
                ];

                $problemMdl->save($problem);
            }
        }

        $this->end(true);
    }
}
