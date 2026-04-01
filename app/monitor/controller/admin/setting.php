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

class monitor_ctl_admin_setting extends desktop_controller
{

    public $workground = 'setting_tools';
    public function index()
    {
        $this->pagedata['email']  = app::get('monitor')->getConf('email.config');
        $this->pagedata['workwx'] = app::get('monitor')->getConf('workwx.config');
        
        $this->pagedata['dingding'] = app::get('monitor')->getConf('dingding.config');
        $this->page('admin/setting/index.html');
    }

    public function save()
    {
        $this->begin();

        $emailConfig = (array) app::get('monitor')->getConf('email.config');
        $emailPost = isset($_POST['email']) && is_array($_POST['email']) ? $_POST['email'] : array();

        // 密码未提交或提交为空字符串时保留历史值，避免误清空（如字符串"0"被empty误判）
        if ($emailPost && (!array_key_exists('smtppasswd', $emailPost) || $emailPost['smtppasswd'] === '') && !empty($emailConfig['smtppasswd'])) {
            $emailPost['smtppasswd'] = $emailConfig['smtppasswd'];
        }

        app::get('monitor')->setConf('email.config', $emailPost);

        app::get('monitor')->setConf('workwx.config', $_POST['workwx']);

        app::get('monitor')->setConf('dingding.config', $_POST['dingding']);

        $this->end(true, app::get('desktop')->_('保存成功'));
    }
}
