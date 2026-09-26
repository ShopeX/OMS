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

class wap_ctl_user extends wap_controller
{
    var $delivery_link    = array();
    
    function __construct($app)
    {
        parent::__construct($app);
        
        $this->delivery_link['index']      = app::get('wap')->router()->gen_url(array('ctl'=>'store','act'=>'index'), true);
        $this->delivery_link['logout']     = app::get('wap')->router()->gen_url(array('ctl'=>'passport','act'=>'logout'), true);
        
        $this->delivery_link['mine']       = app::get('wap')->router()->gen_url(array('ctl'=>'user','act'=>'mine'), true);
        $this->delivery_link['info']       = app::get('wap')->router()->gen_url(array('ctl'=>'user','act'=>'info'), true);
        $this->delivery_link['passwd']     = app::get('wap')->router()->gen_url(array('ctl'=>'user','act'=>'passwd'), true);

        $this->delivery_link['statistics']     = app::get('wap')->router()->gen_url(array('ctl'=>'store','act'=>'statistics'), true);
        
        $this->pagedata['delivery_link']   = $this->delivery_link;
    }
    
    function mine()
    {
        #管理员信息
        $userInfo    = kernel::single('ome_func')->getDesktopUser();
        
        #授权门店
        $is_super = kernel::single('desktop_user')->is_super();
        if(!$is_super){
            $branchObj  = kernel::single('o2o_store_branch');
            $branch_ids = $branchObj->getO2OBranchByUser(true);
            $storeLib   = kernel::single('wap_store_user');
            $storeInfo  = $storeLib->getCurrentStore($branch_ids);
            $userInfo   = $storeLib->mergeUserStoreInfo($userInfo, $storeInfo);
        }
        
        $this->pagedata['userInfo']    = $userInfo;
        $this->pagedata['action']      = __FUNCTION__;
        $this->pagedata['logout']      = ($_COOKIE['relogin'] == '1' ? false : true);
        
        $this->display('store/user_mine.html');
    }
    
    function info()
    {
        $userObj   = app::get('desktop')->model('users');
        
        $opInfo    = kernel::single('ome_func')->getDesktopUser();
        
        //根据当前管理员获取负责管理的门店信息
        $branchObj     = kernel::single('o2o_store_branch');
        $branch_ids    = $branchObj->getO2OBranchByUser(true);
        if(empty($branch_ids))
        {
            $this->pagedata['link_url']     = $this->delivery_link['index'];
            $this->pagedata['error_msg']    = '当前店员没有门店的管理权限';
            echo $this->fetch('auth_error.html');
            exit;
        }
        
        //门店信息
        $storeObj      = app::get('o2o')->model('store');
        $storeInfo     = $storeObj->dump(array('branch_id'=>$branch_ids), '*');
        
        if($storeInfo['area'])
        {
            $temp_area    = explode(':', $storeInfo['area']);
            $storeInfo['district']    = str_replace('/', '-', $temp_area[1]);
        }
        $this->pagedata['storeInfo']    = $storeInfo;
        
        //保存
        if($_POST)
        {
            $contacter    = trim($_POST['contacter']);
            $mobile       = trim($_POST['mobile']);
            $addr         = trim($_POST['addr']);
            
            if(empty($contacter))
            {
                echo json_encode(array('error'=>true, 'message'=>'请填写联系人', 'redirect'=>null));
                exit;
            }
            if(empty($mobile) || strlen($mobile) != 11)
            {
                echo json_encode(array('error'=>true, 'message'=>'手机号码格式错误', 'redirect'=>null));
                exit;
            }
            
            $pattern    = "/^\d{8,15}$/i";
            if (!preg_match($pattern, $mobile)) {
                echo json_encode(array('error'=>true, 'message'=>'请输入正确的手机号码', 'redirect'=>null));
                exit;
            }
            if ($mobile[0] == '0') {
                echo json_encode(array('error'=>true, 'message'=>'手机号码前请不要加0', 'redirect'=>null));
                exit;
            }
            if(empty($addr))
            {
                echo json_encode(array('error'=>true, 'message'=>'请填写门店地址', 'redirect'=>null));
                exit;
            }
            
            $update_data   = array('contacter'=>$contacter, 'mobile'=>$mobile, 'addr'=>$addr);
            $store_save    = $storeObj->update($update_data, array('store_id'=>$storeInfo['store_id']));
            if(!$store_save)
            {
                echo json_encode(array('error'=>true, 'message'=>'门店信息更新失败', 'redirect'=>null));
                exit;
            }
            
            if(empty($_POST['name']))
            {
                echo json_encode(array('error'=>true, 'message'=>'请填写您要修改的昵称', 'redirect'=>null));
                exit;
            }
            
            $result    = $userObj->update(array('name'=>htmlspecialchars(trim($_POST['name']))), array('user_id'=>$opInfo['op_id']));
            if($result)
            {
                $auth_type  = pam_account::get_account_type('desktop');
                $user_id    = $_SESSION['account'][$auth_type];
                $_inner_key = sprintf("account_user_%s", $user_id);
                //cachecore::store($_inner_key, '',1);//注销缓存
                
                //重新生成缓存
                $user_data    = $userObj->dump($opInfo['op_id'],'*',array( ':account@pam'=>array('*') ));
                cachecore::store($_inner_key, $user_data, 60*15);//缓存15分钟
                
                echo json_encode(array('success'=>true, 'message'=>'保存成功', 'redirect'=>$this->delivery_link['mine']));
                exit;
            }
            else 
            {
                echo json_encode(array('error'=>true, 'message'=>'保存失败', 'redirect'=>null));
                exit;
            }
        }
        
        //管理员
        $userInfo   = $userObj->dump(array('user_id'=>$opInfo['op_id']), 'user_id, name, op_no');
        
        $pamObj    = app::get('pam')->model('account');
        $pamInfo   = $pamObj->dump(array('account_id'=>$userInfo['user_id']), 'login_name');
        
        $userInfo    = array_merge($userInfo, $pamInfo);
        
        $this->pagedata['userInfo']    = $userInfo;
        $this->pagedata['action']      = __FUNCTION__;
        
        $this->display('store/user_info.html');
    }
    
    function passwd()
    {
        if($_POST)
        {
            $users    = app::get('desktop')->model('users');
            $userLib  = kernel::single('desktop_user');
            
            $err_data    = array('error'=>true, 'message'=>'', 'redirect'=>null);
            
            $old_password    = trim($_POST['old_password']);
            $new_password    = trim($_POST['new_password']);
            $new_password2   = trim($_POST['new_password2']);
            
            if(empty($old_password) || empty($new_password) || empty($new_password2))
            {
                $err_data['message']    = '密码都必须填写';
                echo json_encode($err_data);
                exit;
            }
            
            if($new_password != $new_password2)
            {
                $err_data['message']    = '两次新密码输入不一致';
                echo json_encode($err_data);
                exit;
            }
            
            //检查新密码
            $error_msg  = '';
            $chkPass    = $userLib->validPassWord($new_password, $error_msg);
            if(!$chkPass)
            {
                $err_data['message']    = $error_msg;
                echo json_encode($err_data);
                exit;
            }
            
            //管理员信息
            $opInfo    = kernel::single('ome_func')->getDesktopUser();
            
            $account_type    = pam_account::get_account_type('desktop');
            
            $filter    = array();
            $filter['account_id'] = $opInfo['op_id'];
            $filter['account_type'] = $account_type;
            $filter['login_password'] = pam_encrypt::get_encrypted_password($old_password, $account_type);
            
            $pass_row    = app::get('pam')->model('account')->getList('account_id', $filter);
            
            if(empty($pass_row))
            {
                $err_data['message']    = '原始密码输入错误';
                echo json_encode($err_data);
                exit;
            }
            
            //md5
            $new_password    = pam_encrypt::get_encrypted_password($new_password, $account_type);
            
            //保存
            $save_data    = array('user_id'=>$opInfo['op_id']);
            $save_data['pam_account']['account_id']        = $opInfo['op_id'];
            $save_data['pam_account']['login_password']    = $new_password;
            
            $users->save($save_data);
            $userLib->checkUpdatePwd($opInfo['op_id'], true);
            
            echo json_encode(array('success'=>true, 'message'=>'重置密码成功', 'redirect'=>$this->delivery_link['logout']));
            exit;
        }
        
        $this->pagedata['action']      = __FUNCTION__;
        
        $this->display('store/user_passwd.html');
    }
}
