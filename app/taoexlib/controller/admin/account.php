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

class taoexlib_ctl_admin_account extends desktop_controller {
    var $workground = 'rolescfg';
    public function __construct($app) {
    	if (!defined('APP_TOKEN') || !defined('APP_SOURCE')) {
    		echo "请在Config文件中定义常量APP_TOKEN和APP_SOURCE";
    		exit;
    	}
    	parent::__construct($app);
    }

    public function index() {
    	base_kvstore::instance('taoexlib')->fetch('account', $account);
    	if (unserialize($account)) {
//    		免登			
			$param = unserialize($account);
			$url = taoexlib_utils::get_book_url($param);
			$info = taoexlib_utils::get_user_info($param);	
			$this->pagedata['frameurl'] = $url;
			$this->pagedata['account'] = $param;
			$this->pagedata['info'] = (array)$info;
			$this->pagedata['warningnumber'] = $this->app->getConf('taoexlib.message.warningnumber');
			$this->page('admin/info.html');
    	}
        else {
			$this->pagedata['regEmail'] = taoexlib_utils::regEmail();
			$this->page('admin/bind.html');
        }
    }
    
    public function register() {
    	$region = $_POST['region'];
    	$region = explode(':', $_POST['region']);
		$regionArr = explode('/', $region[1]);
		
    	$this->begin('index.php?app=taoexlib&ctl=admin_account');
    	if (!trim(trim($_POST['email']))) {
    		$this->end(false, 'email不能为空！');
    	}
    	
    	if (!trim($_POST['password'])) {
    		$this->end(false, '密码不能为空！');
    	}
    	
        if (trim($_POST['confirm']) != trim($_POST['password'])) {
    		$this->end(false, '两次输入密码不一致！');
    	}
    	
//    	if (!trim($_POST['mobile'])) {
//    		$this->end(false, '手机号不能为空！');
//    	}
    	
    	if (!trim($_POST['owner'])) {
    		$this->end(false, '联系人不能为空！');
    	}
    	
    	if (!trim($_POST['wangwang']) && !trim($_POST['paipai'])) {
    		$this->end(false, '旺旺号和拍拍号必须输入一个！');
    	}
    	
    	if (!$_POST['tel']) {
    		$this->end(false, '联系电话不能为空！');
    	}
    	
    	if (!$regionArr[0] || !$regionArr[1]) {
    		$this->end(false, '请选择所属地区！');
    	}
    	
    	if (!trim($_POST['address'])) {
    		$this->end(false, '详细地址不能为空！');
    	}
    	
    	if (!trim($_POST['postcode'])) {
   			$this->end(false, '邮编不能为空！'); 		
    	}
    	
    	$param = array();
    	$param['email'] = trim($_POST['email']);
    	$param['entPwd'] = trim($_POST['password']);
    	$param['source'] = 'shopex_tcrm';
//    	$param['mobile'] = trim($_POST['mobile']);
    	$param['owner'] = trim($_POST['owner']);
    	$param['biz_user'] = trim($_POST['wangwang']);
    	$param['biz_paipai'] = trim($_POST['paipai']);
    	$param['tel'] = trim($_POST['tel']);
    	$param['province'] = taoexlib_utils::province_mapping($regionArr[0]);
    	$param['city'] = $regionArr[1];
    	$param['address'] = trim($_POST['address']);
    	$param['postalcode'] = trim($_POST['postcode']);
   	    
    	$result = taoexlib_utils::register($param);
    	if ('succ' == $result->res) {
    		$info = $result->info;
    		$data = array(
    			'entid' => $info->entid,
    			'password' => $param['entPwd'],
    			'email' => $info->email,
    			'status' => 1
    		);
			base_kvstore::instance('taoexlib')->store('account', serialize($data));
			base_kvstore::instance('taoexlib')->store('hasRegister', 1);
			base_kvstore::instance('taoexlib')->store('regEmail', $info->email);//增加默认email显示 

	    	base_kvstore::instance('taoexlib')->fetch('present', $present);				
			if ('yes' !== $present && defined('TAOEXLIB_PRESENT_ID')) {
			    base_kvstore::instance('taoexlib')->fetch('account', $account);
		    	if (!unserialize($account)) {
		    		return false;
		    	}
		    	
				$param = unserialize($account);
				$result = taoexlib_utils::buy($param, TAOEXLIB_PRESENT_ID, time());
	
				if ('succ' == $result->res) {
					base_kvstore::instance('taoexlib')->store('present', 'yes');
				}	
			}		
    		$this->end(true, '注册成功！');
    	}
    	else {   		
    		$msg = $result->msg;
    		$this->end(false, $msg ? $msg : '注册失败！');
    	}
	}
	
	public function bind() {
	    $this->begin('index.php?app=taoexlib&ctl=admin_account');
    	if (!trim(trim($_POST['bindemail']))) {
			$this->end(false, 'email不能为空！');
    	}
    	
    	if (!trim($_POST['bindpassword'])) {
    		$this->end(false, '密码不能为空！');
    	}
    	
    	$param = array();
    	$param['identifier'] = trim($_POST['bindemail']);
    	$param['password'] = trim($_POST['bindpassword']);
    	$result = taoexlib_utils::login($param);
    	if ('succ' == $result->res) {
    		$data = array(
    			'entid' => $result->info->entid,
    			'password' => trim($_POST['bindpassword']),
    			'email' => $result->info->email,
    			'status' => 1
    		);
    		base_kvstore::instance('taoexlib')->store('account', serialize($data));
    		$this->end(true, $result->res);
    	}
    	$this->end(false, '帐号或密码错误！');
	}
	
	public function unbind() {
		$this->begin('index.php?app=taoexlib&ctl=admin_account');
		base_kvstore::instance('taoexlib')->store('account', serialize(array()));
		$this->end(true, '操作成功！');
	}
		
	/*
	 * sendOne:发送短信
	 * @param $phone='13838385438'
	 * @param $content string;
	 * @param $echostr 是否开启输出功能 预览的时候开启返回短信状态信息 关闭将不再显示短信状态信息 用于发货 可以到短信日志查看日志状态信息
	 */
	
	public function sendOne($phone,$content,$logi_no,$delivery_bn,$echostr=false) {
		base_kvstore::instance('taoexlib')->fetch('account', $account);
    	if (!unserialize($account)) {
    		return false;
    	}
		$param = unserialize($account);
		$info = taoexlib_utils::get_user_info($param);
		if ('succ' == $info->res) {
			if ($info->info->month_residual) {
				$mscontent =array(
						'phones' => $phone,
						'content' => $content,
				);
				$smsresult=taoexlib_utils::send_notice($param, $mscontent);
				if($echostr&&$smsresult)
					return 'sendOk';
				else
					return 'sendFalse';
			}else{
				$this->writeSmslog($phone,$content,'当前没有可用的短信条数！',0);
				if($echostr)
					return 'month_residual_zero';
			}
		}else{
			$this->writeSmslog($phone,$content,$info->info,0);
			if($echostr)
				return '发送失败，原因：'.$info->info.'！';
		}	
	}
	
	/*
	 * wujian@shopex.cn
	 * 短信日志
	 * 2012年2月21日
	 * @param $phonearr 电话号码
	 * @param $delivery_bn 发货单号
	 * @param $logo 快递单号
	 * @param $content 发送内容
	 * @param $msg 短信状态信息
	 * @param $status 短信状态
	 */
	public function writeSmslog($phone,$content,$msg,$status){
			$messlog = app::get('taoexlib')->model("log");
					$messlogdata = array(
                		'mobile'=>$phone,
						'batchno'=>'',
                		'content'=>$content,
                		'sendtime'=>time(),
						'msg'=>$msg,
						'status' =>$status,
            		);
            $messlog->insert($messlogdata);		
	}
	
	/*
	 * 发货并且发短信提醒
	 */
	 public function deliverySendMessage($logi_no){
	 	$switch=app::get("taoexlib")->getConf('taoexlib.message.switch');
		if($switch == 'on'){
			$info = kernel::single('taoexlib_ctl_admin_setting')->getLogiNoInfo($logi_no);
			if($info){
                $phone = trim($info[1][12]);
				$delivery_bn = $info[1][11];
				$messcontent = $info[0];
				if(!empty($phone)){
					if(kernel::single('taoexlib_ctl_admin_setting')->checkBlackTel($phone)){
                        kernel::single('taoexlib_ctl_admin_account')->sendOne($phone,$messcontent,$logi_no,$delivery_bn);
					}else{
						kernel::single('taoexlib_ctl_admin_account')->writeSmslog($phone,$messcontent,'该手机号处于免打扰列表中',0);
					}
				}
			}
		}
	 }
/*	
	public function sendAll() {
	    base_kvstore::instance('taoexlib')->fetch('account', $account);
    	if (!unserialize($account)) {
    		return false;
    	}
    	
		$param = unserialize($account);
		$info = taoexlib_utils::get_user_info($param);
			
		if ('succ' == $info->res) {
			if ($info->info->month_residual) {
				$content = array(
					array(
						'phones' => array('13636348683', '13918635068'),
						'content' => '曾经有一份真诚的爱情放在我面前，我没有珍惜，等我失去的时候我才后悔莫及，人世间最痛苦的事莫过于此。你的剑在我的咽喉上割下去吧！不用再犹豫了！如果上天能够给我一个再来一次的机会，我会对那个女孩子说三个字：我爱你。',
					)
				);	
				taoexlib_utils::send_fanout($param, $content, true);
			}
		}
	}*/
	
	public function buy() {
		base_kvstore::instance('taoexlib')->fetch('present', $present);				
		if ('yes' !== $present) {
		    base_kvstore::instance('taoexlib')->fetch('account', $account);
	    	if (!unserialize($account)) {
	    		return false;
	    	}
	    	
			$param = unserialize($account);
			$result = taoexlib_utils::buy($param, taoexlib_PRESENT_ID, time());
			if ('succ' == $result->res) {
				base_kvstore::instance('taoexlib')->store('present', 'yes');
			}	
		}
		else {
			echo "已赠送!";
		}
	}
	
	public function blacklist() {
		$result = taoexlib_utils::update_blacklist();
	}
}