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

/**
 * 端口配置 Vue API 控制器（SCRUM-7646）
 *
 * 继承 desktop_ctl_api_base，为前端 Vue 表单页提供邮件/企微/钉钉三组配置读写接口。
 * 配置存于 app::get('monitor') 的 kvstore，旧 HTML 控制器
 * app/monitor/controller/admin/setting.php 保留作回退。本页非 finder 页，不使用 finderDispatch。
 */
class monitor_ctl_api_setting extends desktop_ctl_api_base
{
    /**
     * 读取三组配置（密码脱敏，仅返回是否已配置）
     * ?app=monitor&ctl=api_setting&act=getConfig
     *
     * @return void
     */
    #[RequirePermission('performance')]
    public function getConfig(): void
    {
        $email    = app::get('monitor')->getConf('email.config');
        $workwx   = app::get('monitor')->getConf('workwx.config');
        $dingding = app::get('monitor')->getConf('dingding.config');
        $email    = is_array($email) ? $email : [];

        $this->succ([
            'email' => [
                'smtpserver' => $email['smtpserver'] ?? '',
                'smtpport'   => $email['smtpport'] ?? '',
                'smtpssl'    => $email['smtpssl'] ?? '2', // 默认 '2'=否，与 legacy 视图/前端初值一致
                'usermail'   => $email['usermail'] ?? '',
                'fromname'   => $email['fromname'] ?? '',
                'smtppasswd' => '', // 不回传明文（legacy 视图从不回填密码）
                'has_passwd' => !empty($email['smtppasswd']),
            ],
            'workwx'   => ['webhook' => is_array($workwx) ? ($workwx['webhook'] ?? '') : ''],
            'dingding' => ['webhook' => is_array($dingding) ? ($dingding['webhook'] ?? '') : ''],
        ]);
    }

    /**
     * 保存三组配置（密码空值保留历史值 + setConf 返回值检查）
     * ?app=monitor&ctl=api_setting&act=save
     *
     * @return void
     */
    #[RequirePermission('performance')]
    public function save(): void
    {
        $input = $this->getRequestInput();

        $emailPost    = isset($input['email']) && is_array($input['email']) ? $input['email'] : [];
        $workwxPost   = isset($input['workwx']) && is_array($input['workwx']) ? $input['workwx'] : [];
        $dingdingPost = isset($input['dingding']) && is_array($input['dingding']) ? $input['dingding'] : [];

        // ① 密码未提交或提交为空字符串时保留历史值，避免误清空（如字符串"0"被empty误判）
        $emailConfig = (array) app::get('monitor')->getConf('email.config');
        if ($emailPost && (!array_key_exists('smtppasswd', $emailPost) || $emailPost['smtppasswd'] === '') && !empty($emailConfig['smtppasswd'])) {
            $emailPost['smtppasswd'] = $emailConfig['smtppasswd'];
        }

        // ② 只落库已知键，避免前端多传字段污染配置；未提交的 webhook 保留历史
        $emailPost    = array_intersect_key($emailPost, array_flip(['smtpserver', 'smtpport', 'smtpssl', 'usermail', 'fromname', 'smtppasswd']));
        $workwxPost   = $workwxPost ?: (array) app::get('monitor')->getConf('workwx.config');
        $dingdingPost = $dingdingPost ?: (array) app::get('monitor')->getConf('dingding.config');

        // ③ kvstore 无事务，逐个检查返回值，失败即返回（已知限制：非原子）
        if (!app::get('monitor')->setConf('email.config', $emailPost)
            || !app::get('monitor')->setConf('workwx.config', $workwxPost)
            || !app::get('monitor')->setConf('dingding.config', $dingdingPost)) {
            $this->fail('保存失败', 500);
            return;
        }

        $this->succ(['message' => '保存成功']);
    }

    /**
     * 菜单派生端点兼容入口（本页非列表页，等价于 getConfig）
     * ?app=monitor&ctl=api_setting&act=index
     *
     * @return void
     */
    #[RequirePermission('performance')]
    public function index(): void
    {
        $this->getConfig();
    }
}
