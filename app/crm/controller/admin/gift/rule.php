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

#增加规则的
class crm_ctl_admin_gift_rule extends desktop_controller{

    public function index($act = '')
    {        
        if($act == 'add'){
            $this->add();
            exit;
        }

        $base_filter = array('disable'=>'false');
        $this->finder('crm_mdl_gift_rule',array(
            'title'=>'赠品规则应用',
            'actions'=>array(
                array(
                    'label'=>'添加赠品规则应用',
                    'href'=>'index.php?app=crm&ctl=admin_gift_rule&act=index&p[0]=add',
                ),
                array('label'=>app::get('crm')->_('删除'),'icon' => 'del.gif', 'confirm' =>'确定删除选中项？','submit'=>'index.php?app=crm&ctl=admin_gift_rule&act=delRule',),
                array(
                    'label'  => '批量关闭',
                    'submit' => 'index.php?app=crm&ctl=admin_gift_rule&act=batchClose',
                    'target' => 'dialog::{width:600,height:300,title:\'批量关闭\'}"'
                ),
                array(
                    'label'  => '批量开启',
                    'submit' => 'index.php?app=crm&ctl=admin_gift_rule&act=batchopen',
                    'target' => 'dialog::{width:600,height:300,title:\'批量开启\'}"'
                ),
            ),
            'base_filter'=>$base_filter,
            'orderBy' => 'status DESC,priority DESC,id DESC',
            'use_buildin_recycle' => false,
            'use_buildin_filter'    => true,
        ));
    }
    
    function add(){
        $this->edit();
    }
   
    function edit(){
        $shopObj =  app::get('ome')->model('shop');
        
        $id = intval($_GET['id']);
       $filter = array();
       $shops_name = $shopObj->getList('node_type,shop_id,name',$filter);

        
       foreach($shops_name as $v){
           $shops[$v['shop_id']] = $v['name'];
       }
        $rule = array(
            'start_time' => strtotime(date('Y-m-d')),
            'status' => 1,
            'time_type' => 'pay_time',
            'lv_id' => 0,
            'filter_arr' => array(
                'add_or_divide' => 'add',
            ),
        );
        
        
        //修改规则信息
        if($id > 0){
            $rule = $this->app->model('gift_rule')->dump($id,'*');
            $rule['filter_arr'] = json_decode($rule['filter_arr'], true);
            if($rule['shop_ids']){
                $rule['shop_ids'] = explode(',', $rule['shop_ids']);
            }
            //复制赠品规则应用
            if (isset($_GET['type']) && $_GET['type'] == 'copy') {
                unset($rule['id']);
            }
    
            if(!$rule['filter_arr']['add_or_divide'] && !$rule['id']) {
                die('非赠品规则应用，不能编辑<br/><br/><button onclick="history.back()">返回</button>');
            }
        }

        $big_region = array(
            'northern_china'    =>  '华北地区',
            'northeast_china'   =>  '东北地区',
            'eastern_china'     =>  '华东地区',
            'southcentral_china'=>  '中南地区',
            'southwest_china'   =>  '西南地区',
            'northwest_china'   =>  '西北地区',
            'hk_mc_tw'          =>  '港澳台地区',
        );
        // $big_region_list = array(
        //     'northern_china'    =>  array('北京','天津','河北','山西','内蒙古'), // 华北
        //     'northeast_china'   =>  array('辽宁','吉林','黑龙江'), // 东北
        //     'eastern_china'     =>  array('上海','江苏','浙江','江西','安徽','福建','山东'), // 华东
        //     'southcentral_china'=>  array('河南','湖北','湖南','广东','广西','海南'), // 中南
        //     'southwest_china'   =>  array('重庆','四川','贵州','云南','西藏'), // 西南
        //     'northwest_china'   =>  array('陕西','甘肃','青海','宁夏','新疆'), // 西北
        //     'hk_mc_tw'          =>  array('香港','澳门','台湾'), // 港澳台
        // );
        $province_affiliation = array(
            '北京'    =>  'northern_china',
            '天津'    =>  'northern_china',
            '河北'    =>  'northern_china',
            '山西'    =>  'northern_china',
            '内蒙古'   =>  'northern_china',

            '辽宁'    =>  'northeast_china',
            '吉林'    =>  'northeast_china',
            '黑龙江'   =>  'northeast_china',

            '上海'    =>  'eastern_china',
            '江苏'    =>  'eastern_china',
            '浙江'    =>  'eastern_china',
            '江西'    =>  'eastern_china',
            '安徽'    =>  'eastern_china',
            '福建'    =>  'eastern_china',
            '山东'    =>  'eastern_china',

            '河南'    =>  'southcentral_china',
            '湖北'    =>  'southcentral_china',
            '湖南'    =>  'southcentral_china',
            '广东'    =>  'southcentral_china',
            '广西'    =>  'southcentral_china',
            '海南'    =>  'southcentral_china',

            '重庆'    =>  'southwest_china',
            '四川'    =>  'southwest_china',
            '贵州'    =>  'southwest_china',
            '云南'    =>  'southwest_china',
            '西藏'    =>  'southwest_china',

            '陕西'    =>  'northwest_china',
            '甘肃'    =>  'northwest_china',
            '青海'    =>  'northwest_china',
            '宁夏'    =>  'northwest_china',
            '新疆'    =>  'northwest_china',

            '香港'    =>  'hk_mc_tw',
            '澳门'    =>  'hk_mc_tw',
            '台湾'    =>  'hk_mc_tw',
        );
        $provinces_new = array();
        
        $rs = app::get('eccommon')->model('regions')->getList('local_name',array('region_grade'=>1));
        foreach($rs as $v){
            $provinces[$v['local_name']] = $v['local_name'];

            foreach ($province_affiliation as $b_r_key => $b_r_value) {
                if (stripos($v['local_name'], $b_r_key) !== false) {
                    $provinces_new[$v['local_name']] = $b_r_value;
                    // $big_region_list[$b_r_value][$v['local_name']] = $v['local_name'];
                    break;
                }
            }
        }
        $this->pagedata['big_region'] = $big_region;
        $this->pagedata['provinces_new'] = $provinces_new;

        $rule['start_time_hour'] = 0;
        if($rule['start_time']){
            $rule['start_time_hour'] = (int)date('H', $rule['start_time']);
        }
        
         $rule['start_time_minitue'] = 0;
        if($rule['start_time']){
            $rule['start_time_minitue'] = (int)date('i', $rule['start_time']);
        } 
        
        $rule['start_time_second'] = 0;
        if($rule['start_time']){
            $rule['start_time_second'] = (int)date('s', $rule['start_time']);
        }
        $rule['end_time_hour'] = 0;
        if($rule['end_time']){
            $rule['end_time_hour'] = (int)date('H', $rule['end_time']);
        }
        
        $rule['end_time_minitue'] = 0;
        if($rule['end_time']){
            $rule['end_time_minitue'] = (int)date('i', $rule['end_time']);
        }
        $rule['end_time_second'] = 0;
        if($rule['end_time']){
            $rule['end_time_second'] = (int)date('s', $rule['end_time']);
        }
        $this->pagedata['conf_hours'] = array_merge(array('00','01','02','03','04','05','06','07','08','09'),range(10,23));
        $this->pagedata['conf_minitue'] = array_merge(array('00','01','02','03','04','05','06','07','08','09'),range(10,59));
        $this->pagedata['conf_second'] = array_merge(array('00','01','02','03','04','05','06','07','08','09'),range(10,59));

        $this->pagedata['provinces'] = $provinces;
        $this->pagedata['shops'] = $shops;
        $this->pagedata['rule'] = $rule;
        $this->pagedata['beigin_time'] = date("Y-m-d",time());
        $this->pagedata['end_time'] = date('Y-m-d',strtotime('+15 days'));
        $this->pagedata['bool_type'] = kernel::single('ome_order_bool_type')->getBoolTypeText();
        $this->pagedata['finder_vid'] = $_GET['finder_vid'];
        $this->page('admin/gift/rule_edit.html');
    }

    public function view_rule(){

        $shopObj = app::get('ome')->model('shop');
        $shop_id = $_GET['shop_id'];
        // 引入店铺model
        $shops_name = $shopObj->getList('shop_id,name');

        foreach($shops_name as $v){
            $shops[$v['shop_id']] = $v['name'];
        }
    
        $rule = array(
            'start_time' => date('Y-m-d'),
            'status'     => 1,
            'shop_id'    => $shop_id,
            'time_type'  => 'pay_time',
            'lv_id'      => 0,
            'filter_arr' => array(
                'order_amount' => array(
                    'type'=>0
                ),
                'buy_goods' => array(
                    'type'=>0
                ),
            ),
        );
    
        if(isset($_GET['snapshot_id'])){
            $snapshot_id        = floatval($_GET['snapshot_id']);
            $snapshot           = app::get('crm')->model('snapshot')->dump($snapshot_id);
            $rule               = json_decode($snapshot['content'], true);
            $rule['filter_arr'] = json_decode($rule['filter_arr'], true);
        }

        if($rule['shop_ids']){
            $shop_ids = explode(',', $rule['shop_ids']);
        }else{
            $shop_ids = array($rule['shop_id']);
        }

        if($shop_ids){
            $rule['shop_ids'] = array();

            foreach($shop_ids as $v){
                $rule['shop_ids'][] = $shops[$v];
            }
            $rule['shop_ids'] = implode('；', $rule['shop_ids']);
        }
        if($rule['filter_arr']['province'][0] == '_ALL_'){
            $rule['filter_arr']['province'] = false;
        }
        if($rule['filter_arr']['add_or_divide']) {
            $this->pagedata['rule']  = $rule;
            $this->display('admin/gift/rule_apply_view.html');exit();
        }
        // 去除空的货号
        foreach($rule['filter_arr']['buy_goods']['goods_bn'] as $k=>$v){
            if( ! $v) unset($rule['filter_arr']['buy_goods']['goods_bn'][$k]);
        }
        // 已经设定的赠品组合
        $gifts = array();

        if($rule['gift_ids']){

            $gift_num = explode(',', $rule['gift_num']);

            foreach ($rule['goodsInfo'] as $key=>$val){
                $gifts[$key]['num']        = $gift_num[$key];
                $gifts[$key]['gift_bn']    = $val['gift_goods_bn'];
                $gifts[$key]['gift_name']  = mb_substr($val['gift_goods_name'],0,22,'utf-8');
                $gifts[$key]['gift_price'] = $val['gift_goods_price'];
            }
        }
        $this->pagedata['gifts'] = $gifts;
        $this->pagedata['rule']  = $rule;
        $this->pagedata['bool_type'] = kernel::single('ome_order_bool_type')->getBoolTypeText();
        $this->display('admin/gift/rule_view.html');
    }

    public function priority($id=0){
        if($_POST){
            $this->begin("index.php?app=crm&ctl=admin_gift_rule&act=index");
            $shopGiftObj = app::get('crm')->model('gift_rule');
            $data = $_POST;
            $data['priority'] = intval($_POST['priority']);
            $data['modified_time'] = time();
            if($shopGiftObj->save($data)){
                $this->end(true,'添加成功');
            }else{
                $this->end(false,'添加失败');
            }
        }
    
        //修改规则信息
        if($id>0){
            $rule = $this->app->model('gift_rule')->dump($id);
            
            $rule['start_time'] = date("Y-m-d", $rule['start_time']);
            $rule['end_time'] = date("Y-m-d", $rule['end_time']);
        }
        
        $this->pagedata['rule'] = $rule;
        $this->pagedata['view'] = $_GET['view'];
        $this->display('admin/gift/priority.html');
    }


    /**
     * 保存erp赠品规则
     */
    public function save_rule()
    {
        // 启动事务
        $this->begin("index.php?app=crm&ctl=admin_gift_rule&act=index");
        
        $shopGiftObj = $this->app->model('gift_rule');
        
        // 检测参数
        if(empty($_POST['shop_ids'])){
            $this->end(false,'最选择店铺');
        }
        if(empty($_POST['filter_arr']['province'])){
            $_POST['filter_arr']['province'][] = '_ALL_';#不选区域，表示指定所有店铺
        }
        
        //触发节点--补发赠品设置
        if($_POST['trigger_type'] == 'order_complete'){
            $_POST['defer_day'] = $_POST['defer_day'] ? intval($_POST['defer_day']) : 0;
            if(empty($_POST['defer_day'])){
                $this->end(false, '请填写延迟天数，系统自动补发赠品');
            }elseif($_POST['defer_day'] < 1){
                $this->end(false, '请正确填写延迟天数，系统自动补发赠品');
            }
            
            if($_POST['is_exclude'] != '1'){
                $this->end(false, '订单延迟补发赠品，请选择[排他]模式');
            }
        }else{
            $_POST['defer_day'] = 0;
        }
        
        // 组合数据
        $data = $_POST;
        $data['start_time']    = strtotime($data['start_time'].' '.$data['start_time_hour'].':'.$data['start_time_minitue'].':'.$data['start_time_second']);
        $data['end_time']      = strtotime($data['end_time'].' '.$data['end_time_hour'].':'.$data['end_time_minitue'].':'.$data['end_time_second']);
        $data['modified_time'] = time();
        
        if(is_array($data['shop_ids']) && count($data['shop_ids'])>10){
            $this->end(false,'最多只能选择十个店铺');
        }

        if(empty($data['filter_arr']['add_or_divide']) || empty($data['filter_arr']['id'])) {
            $this->end(false,'赠品规则未选择');
        }

        $data['shop_ids'] = implode(',',  $data['shop_ids']);

        $data['filter_arr'] = json_encode($data['filter_arr']);
        
        if(!$data['id']) $data['create_time'] = time();

        if($shopGiftObj->db_save($data)){
            // 数据快照
            if($data['id']){
                // 获取规则信息
                $sdf = $shopGiftObj->dump($data['id'], '*');

                $sdf = array(
                    'title'   => '赠品规则发生变动',
                    'content' => json_encode($sdf),
                    'task_id' => $data['id'],
                );
                $this->gift_rule_change($sdf);
            }
            $this->end(true,'添加成功');
        }else{
            $this->end(false,'添加失败');
        }
    }

    public function logs(){
        $actions     = array();
        $base_filter = array();
        $this->finder('crm_mdl_gift_logs',array(
            'title'                 => '赠品发送列表' . $this->helpLink('/hc/sections/61790/'),
            'actions'               => $actions,
            'base_filter'           => $base_filter,
            'orderBy'               => 'id DESC',
            'use_buildin_recycle'   => false,
            'use_buildin_filter'    => true,
            'use_buildin_export'    => true,
            'use_view_tab'          => true,
        ));
    }

    public function showSensitiveData($order_bn)
    {
        $order = app::get('ome')->model('orders')->db_dump(array('order_bn'=>$order_bn,'order_type'=>'_ALL_'), 'order_bn,member_id,shop_type,shop_id,order_id');
        
        if (!$order) {
            $order = app::get('archive')->model('orders')->db_dump(array('order_bn'=>$order_bn),'order_bn,member_id,shop_type,shop_id,order_id');
        }

        if ($order['member_id']) {
            $member = app::get('ome')->model('members')->db_dump($order['member_id'],'uname');

            $order['uname'] = $member['uname'];
        }

        // 页面加密处理
        $order['encrypt_body'] = kernel::single('ome_security_router',$order['shop_type'])->get_encrypt_body($order, 'order');

        // 推送日志
        kernel::single('base_hchsafe')->order_log(array('operation'=>'赠品发放查看订单客户账号信息','tradeIds'=>array($order['order_bn'])));
        

        $this->splash('success',null,null,'redirect',$order);
    } 

    public function set_logs() {
        if(isset($_POST['set_gift_erp'])){
           $set_type = $_POST['set_type'];
           $set_gift_taobao = $set_gift_erp = 'on';
           
           #淘宝赠品启用状态
            if(empty($_POST['set_gift_taobao']) || $_POST['set_gift_taobao'] == 'off' ){
                $set_gift_taobao = 'off';
                app::get('ome')->setConf('ome.preprocess.tbgift','false');
            }else{
                app::get('ome')->setConf('ome.preprocess.tbgift','true');
            }
            #ERP赠品启用状态
            if(empty($_POST['set_gift_erp']) || $_POST['set_gift_erp'] == 'off' ){
                $set_gift_erp = 'off';
                $setting['radio'] = 'off';
            }else{
                $setting['radio'] = 'on';
            }
            #出错处理
            if(empty($_POST['erp_gift_error_setting']) || $_POST['erp_gift_error_setting'] == 'off'){
                $setting['error'] = 'off';#关闭出错,审单发货
            }else{
                $setting['error'] = 'on';
            }
            base_kvstore::instance('crm/set/gift_erp_setting')->store('crm_gift_erp_setting',  $setting);
            if($set_gift_erp == 'off'){
                $set_type = 'other';
            }
            
            $arr = array(
                'set_gift_taobao'=>$set_gift_taobao,
                'set_gift_erp'=>$set_gift_erp,
                'set_type' => $set_type,
                'op_user' => kernel::single('desktop_user')->get_name(),
                'create_time' => time(),
            );
            
            $url = 'index.php?app=crm&ctl=admin_gift_rule&act=set_logs';
            $this->begin($url);

            $this->app->model('gift_set_logs')->save($arr);
            
            $this->end(true,'保存成功');
        }
        $gift_erp_setting = array();
        base_kvstore::instance('crm/set/gift_erp_setting')->fetch('crm_gift_erp_setting',  $gift_erp_setting);
        if(empty( $gift_erp_setting)){
            $this->pagedata['erp_gift_error_setting'] = 'off';
        }else{
            $this->pagedata['erp_gift_error_setting'] = $gift_erp_setting['error'];
        }
        
        #默认为叠加 exclude
        $set_type = 'exclude';
        
        #以最后一次设定的模式为准
        $rs = $this->app->model('gift_set_logs')->getList('*', '', 0, 1, 'id DESC');

        $taobao_gift_setting = app::get('ome')->getConf('ome.preprocess.tbgift'); #是否启用淘宝赠品(兼容很早以前的)
        if($taobao_gift_setting == 'true'){
            $set_gift_taobao = 'on';
        }else{
            $set_gift_taobao = 'off';
        }
        if($rs){
            if($rs[0]['set_type'] != 'other'){
               $set_type = $rs[0]['set_type'];
            }
        }

        $this->pagedata['set_type'] = $set_type;
        $this->pagedata['set_gift_erp'] = $rs[0]['set_gift_erp'];
        $this->pagedata['set_gift_taobao'] = $set_gift_taobao;
        
        $extra_view = array('crm'=>'admin/gift/set.html');
    
        $actions = array();
        $base_filter = array();
        $this->finder('crm_mdl_gift_set_logs',array(
            'title'=>'赠品设置' . $this->helpLink('/hc/articles/81514/'),
            'actions'=>$actions,
            'base_filter'=>$base_filter,
            'orderBy' => 'id DESC',
            'use_buildin_recycle' => false,
            'use_buildin_filter' => false,
            'use_view_tab' => false,
            'top_extra_view' => $extra_view,
            'use_buildin_setcol' => false,
            'use_buildin_refresh' => false,
        ));
    }

    #恢复已删除赠品规则
    public function recover($status = false,$id = false){
        $finder_id = $_GET['finder_id'];
        $this->begin('index.php?app=crm&ctl=admin_gift_rule&act=index&$finder_id='.$finder_id);
        $obj_rule = $this->app->model('gift_rule');
        if(empty($id)){
            $this->end(false,$this->app->_('设置失败'));
        }
        if($status == 'true'){
            $data['disable'] = 'false';#恢复
        }
        $data['modified_time'] = time();
        $filter = array('id'=>$id);
        $obj_rule->update($data,$filter);
        $this->end(true,$this->app->_('设置成功'));
    }
    public function delRule(){
        $this->begin('index.php?app=crm&ctl=admin_gift_rule&act=index');
        $obj_rule = $this->app->model('gift_rule');
        $isSelectedAll = $_POST['isSelectedAll']; 
        $ids = $_POST['id'];
    
        if($isSelectedAll != '_ALL_' && $ids){
            $basefilter = array('id'=>$ids);
        }else{
            $basefilter = array();
        }
        $ids = $obj_rule->getList('id',$basefilter);
        $rule_ids = array_map('current',$ids);
        $obj_rule->update(array('disable'=>'true'),array('id|in'=>$rule_ids));
        $this->end(true, $this->app->_('删除成功'));
    }

    public function gift_rule_change($sdf){
        $mdl_snapshot = app::get('crm')->model('snapshot');
        $data = array(
            'task_id' => $sdf['task_id'],
            'type'    => 1,
            'title'   => $sdf['title'],
            'content' => $sdf['content'],
            'op_user' => kernel::single('desktop_user')->get_name(),
            'create_time' => date('Y-m-d H:i:s'),
        );
        $mdl_snapshot->save($data);
        return true;
    }
    
    /**
     * 批量关闭赠品规则应用
     * @date 2024-09-10 2:16 下午
     */
    public function batchClose()
    {
        
        $this->pagedata['request_url'] = 'index.php?app=crm&ctl=admin_gift_rule&act=ajaxClose';
        $this->pagedata['autotime']    = '500';
        
        $_POST['status'] = array('1');
        $_POST           = array_merge($_POST, $_GET);
        parent::dialog_batch('crm_mdl_gift_rule', true, 100, 'inc');
    }
    
    public function ajaxClose()
    {
        parse_str($_POST['primary_id'], $primary_id);
        
        if (!$primary_id) {
            echo 'Error: 请先选择数据';
            exit;
        }
        
        $retArr = array(
            'itotal'  => 0,
            'isucc'   => 0,
            'ifail'   => 0,
            'err_msg' => array(),
        );
        
        $ruleMdl = app::get('crm')->model('gift_rule');
        $rules   = $ruleMdl->getList('id', $primary_id['f'], $primary_id['f']['offset'], $primary_id['f']['limit']);
        
        $retArr['itotal'] = count($rules);
        
        foreach ($rules as $v) {
            
            $updata = array('status' => '0');
            $rs     = $ruleMdl->update($updata, array('id' => $v['id']));
            if ($rs) {
                $retArr['isucc']++;
            } else {
                $retArr['ifail']++;
            }
        }
        
        echo json_encode($retArr), 'ok.';
        exit;
    }
    
    /**
     * 复制赠品规则应用
     * @date 2024-10-08 2:28 下午
     */
    public function copy_rule()
    {
        $_GET['type'] = 'copy';
        $this->edit();
    }

    public function batchopen()
    {
        

        if($_POST['isSelectedAll'] == '_ALL_'){
            die('不能使用全选功能,每次最多选择20条!');
        }

        
        $this->pagedata['request_url'] = 'index.php?app=crm&ctl=admin_gift_rule&act=ajaxopen';
        $this->pagedata['autotime']    = '500';
        
        $_POST['status'] = array('0');
        $_POST           = array_merge($_POST, $_GET);
        parent::dialog_batch('crm_mdl_gift_rule', true, 100, 'inc');
    }

    public function ajaxopen()
    {
        
        parse_str($_POST['primary_id'], $primary_id);
        
        if (!$primary_id) {
            echo 'Error: 请先选择数据';
            exit;
        }
        
        $retArr = array(
            'itotal'  => 0,
            'isucc'   => 0,
            'ifail'   => 0,
            'err_msg' => array(),
        );
        
        $ruleMdl = app::get('crm')->model('gift_rule');
        $rules   = $ruleMdl->getList('*', $primary_id['f'], $primary_id['f']['offset'], $primary_id['f']['limit']);
        
        $retArr['itotal'] = count($rules);
        
        foreach ($rules as $v) {
            
            $sdf = array(
                'title'   => '赠品规则活动开启',
                'content' => json_encode($v),
                'task_id' => $v['id'],
            );
            $this->gift_rule_change($sdf);
            $updata = array('status' => '1');
            $rs     = $ruleMdl->update($updata, array('id' => $v['id']));
            if ($rs) {
                $retArr['isucc']++;
            } else {
                $retArr['ifail']++;
            }
        }
        
        echo json_encode($retArr), 'ok.';
        exit;
    }

}