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


class pam_account{

    function __construct($type){
        $this->type = $type;
        $this->session = kernel::single('base_session')->start();
    }

    function is_valid(){
        return $_SESSION['account'][$this->type];
    }
    
    function is_exists($login_name){
        if(app::get('pam')->model('account')->getList('account_id',array('account_type'=>$this->type,'login_name'=>$login_name)))
            return true;
        else
            return false;
    }

    function update($module,$module_uid, $auth_data){
        if($module!='pam_passport_basic' && $module!='pam_passport_wap'){
            $auth_model = app::get('pam')->model('auth');
            if($row = $auth_model->getlist('*',array(
                    'module_uid'=>$module_uid,
                    'module'=>$module,
                ),0,1)){
                $auth_model->update(array('data'=>$auth_data),array(
                    'module_uid'=>$module_uid,
                    'module'=>$module,
                ));
                $account_id = $row[0]['account_id'];
            }else{
                $account = app::get('pam')->model('account');
                $login_name = microtime();
                while($row = $account->getList('account_id',array('login_name' => $login_name,'account_type' => $this->type)))
                {
                	$login_name = microtime();
                }
                $data = array(
                            'login_name' => $login_name,
                            'login_password' => md5(time()),
                            'account_type'=>$this->type,
                            'createtime'=>time(),
                    );
                $account_id = $account->insert($data);
				if(!$account_id) return false;
                $data = array(
                    'account_id'=>$account_id,
                    'module_uid'=>$auth_data['login_name'],
                    'module'=>$module,
                    'data'=>$auth_data,
                );
                $auth_model->insert($data);
            }
        }else{
            $account_id = $module_uid;
        } 

        $_SESSION['account'][$this->type] = $account_id;
        return true;
    }

    static function register_account_type($app_id,$type,$name){
        $account_types = app::get('pam')->getConf('account_type');
        $account_types[$app_id] = array('name' => $name, 'type' => $type);
        app::get('pam')->setConf('account_type',$account_types);
    }

    static function unregister_account_type($app_id){
        $account_types = app::get('pam')->getConf('account_type');
        unset($account_types[$app_id]);
        app::get('pam')->setConf('account_type',$account_types);
    }

    static function get_account_type($app_id = 'b2c') 
    {
        $aType = app::get('pam')->getConf('account_type');
        //todo
        return is_array($aType[$app_id]) ? $aType[$app_id]['type'] : false;
    }//End Function
    
    /**
     * isFreezeAccount
     * @param mixed $loginName loginName
     * @param mixed $account account
     * @return mixed 返回值
     */
    public function isFreezeAccount($loginName, $account) {
        if('false' == app::get('ome')->getConf('desktop.account.error.freeze')) {
            return false;
        }
        $maxTimes = 5;
        if($account) {
            if($account['times'] >= $maxTimes && $account['login_time'] > (time() - 600)) {
                return true;
            }
            $upData = array(
                'times' => 0,
                'login_time' => time()
            );
            app::get('pam')->model('account')->update($upData, array('account_id'=>$account['account_id']));
            return false;
        }
        $pamAccountModel = app::get('pam')->model('account');
        $pamAccount = $pamAccountModel->dump(array('login_name'=>$loginName), 'account_id, times');
        if($pamAccount) {
            $upData = array();
            $upData['login_time'] = time();
            if($pamAccount['times'] < $maxTimes) {
                $upData['times'] = $pamAccount['times'] + 1;
            }
            $pamAccountModel->update($upData, array('account_id'=>$pamAccount['account_id']));
            if($pamAccount['times'] > ($maxTimes - 2)) {
                return true;
            }
        }
        return false;
    }
}
