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

use app\desktop\lib\annotation\RequirePermission;

class desktop_ctl_api_ipsetting extends desktop_ctl_api_base
{
    /**
     * 获取IP白名单设置
     */
    #[RequirePermission('users')]
    public function index()
    {
        // 检查超级管理员权限
        if ((kernel::single('desktop_user')->is_super()) != '1') {
            $this->fail('对不起，您无权限操作当前页面！');
            return;
        }

        $configIPdata = app::get('desktop')->getConf('ip_setting_white_list');
        
        $this->succ([
            'ip_addr' => $configIPdata['ip_addr'] ?? '',
            'user_name' => $configIPdata['user_name'] ?? '',
            'lasttime' => $configIPdata['lasttime'] ?? '',
            'lasttime_format' => $configIPdata['lasttime'] ? date('Y-m-d H:i:s', $configIPdata['lasttime']) : '',
        ]);
    }

}