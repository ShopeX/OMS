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


class desktop_task{
    
    function install_options(){
        return array(
                'admin_uname'=>array('type'=>'text','vtype'=>'required','required'=>true,'title'=>'用户名','default'=>'admin'),
                'admin_password'=>array('type'=>'password','vtype'=>'required','required'=>true,'title'=>'密码'),
                'admin_password_re'=>array('type'=>'password','vtype'=>'required','vtype'=>'samePas','required'=>true,'title'=>'重复密码'),  
            );
    }
    
    function checkenv($options){
        if($options['admin_password']!=$options['admin_password_re']){
            echo "Error: 两次密码不一致\n";
            return false;
        }

        if(empty($options['admin_password'])){
            echo "Error: 密码不能为空\n";
            return false;
        }

        if(strlen($options['admin_password']) < 8){
            echo "Error: 密码长度不能小于8位\n";
            return false;
        }

        if(strlen($options['admin_password']) > 20){
            echo "Error: 密码长度不能大于20位\n";
            return false;
        }

        if(!preg_match('/[a-z]+/', $options['admin_password']) || !preg_match('/[A-Z]+/', $options['admin_password']) || !preg_match('/[0-9]/', $options['admin_password'])){
            echo "Error: 密码必须包含英文数字大小写\n";
            return false;
        }
        return true;
    }

    function post_install($options)
    {
        kernel::log('Create admin account');
        //设置用户体系，前后台互不相干
        pam_account::register_account_type('desktop','shopadmin','后台管理系统');
        
        
        //todo: 封装成更简单的函数
        $account = array(
            'pam_account'=>array(
                'login_name'=>$options['admin_uname'],
                'login_password' => hash('sha256', md5($options['admin_password'])),
                'account_type'=>'shopadmin',
                ),
            'name'=>$options['admin_uname'],
            'super'=>1,
            'status'=>1
            );

        app::get('desktop')->model('users')->save($account);

        //初始化导出模板
        kernel::single('desktop_init')->addDefaultExportStandardTemplate();
    }

    function post_uninstall(){
        pam_account::unregister_account_type('desktop');
    }

    function post_update(){
        $deploy_info = base_setup_config::deploy_info();
        $logo = substr($deploy_info['product_name'],strlen('商派ONex  '));
        app::get('desktop')->setConf('logo',$logo);

        if (!pam_account::get_account_type('desktop')){
            pam_account::register_account_type('desktop','shopadmin','后台管理系统');
        }
    }
}
