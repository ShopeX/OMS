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
    	// SCRUM-7781：实现已抽到 taoexlib_account（与 Vue api 共用），此处仅保留 legacy 页的 begin/end 语义
    	$this->begin('index.php?app=taoexlib&ctl=admin_account');
    	$result = kernel::single('taoexlib_account')->register($_POST);
    	$this->end($result['ok'], $result['message']);
    }
    public function bind() {
    	// SCRUM-7781：实现已抽到 taoexlib_account（与 Vue api 共用）
    	$this->begin('index.php?app=taoexlib&ctl=admin_account');
    	$result = kernel::single('taoexlib_account')->bind($_POST['bindemail'], $_POST['bindpassword']);
    	$this->end($result['ok'], $result['message']);
    }
    public function unbind() {
    	// SCRUM-7781：实现已抽到 taoexlib_account（与 Vue api 共用）
    	$this->begin('index.php?app=taoexlib&ctl=admin_account');
    	$result = kernel::single('taoexlib_account')->unbind();
    	$this->end($result['ok'], $result['message']);
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