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
 * ShopEx licence
 *
 * @IP地址白名单
 * @2016-11-16
 * @liwei@shopex.cn
 *
 */

use app\desktop\lib\annotation\RequirePermission;

class desktop_ctl_ipsetting extends desktop_controller
{

    var $workground = 'desktop_ctl_system';

    #[RequirePermission('users')]
    function index(){
        if((kernel::single('desktop_user')->is_super())!='1'){
            die("<h3>对不起，您无权限操作当前页面！</h3>");
        }
        $configIPdata = app::get('desktop')->getConf('ip_setting_white_list');
        $this->pagedata['ip_addr'] = $configIPdata['ip_addr'];
        $this->pagedata['user_name'] = $configIPdata['user_name'];
        $this->pagedata['lasttime'] = $configIPdata['lasttime']?(date("Y-m-d H:i:s",$configIPdata['lasttime'])):'';
        $this->page('ipsetting.html');
    }
    
    //@保存新增IP地址
    #[RequirePermission('users')]
    public function save(){
        $this->begin('index.php?app=desktop&ctl=ipsetting&act=index');
        $op_name = kernel::single('desktop_user')->get_login_name();

        $remote_ip = kernel::single('desktop_ip')->getIp();
        if($_POST['ip_addr'] && kernel::single('desktop_ip')->limit($_POST)){
            $this->end(false,app::get('desktop')->_('保存失败，当前IP['.$remote_ip.']必须包含！'));
        }

        $saveData = array(
            'ip_addr'   =>$_POST['ip_addr'],
            'user_name' =>$op_name,
            'lasttime'  =>time(),
        );

        $result = app::get('desktop')->setConf('ip_setting_white_list',$saveData);
        $this->end(true,app::get('desktop')->_('保存成功'));
    }

}
