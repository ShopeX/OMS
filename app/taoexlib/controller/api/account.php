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
 * 短信开通（taoexlib 账号）Vue 侧接口
 *
 * 读：GET  ?app=taoexlib&ctl=api_account&act=index
 * 写：POST ?app=taoexlib&ctl=api_account&act=bind|unbind|register
 *
 * 与 legacy 控制器（taoexlib_ctl_admin_account）共用 taoexlib_account 逻辑：
 * 不再手搓 $_GET['action'] 分发，也不再让写操作散落在 legacy act（原先 legacy act
 * 在菜单表无对应行 ⇒ 权限判成 '0' ⇒ 兜底对 JWT 会话放行，属越权写）。
 */
class taoexlib_ctl_api_account extends desktop_ctl_api_base
{
    private function lib()
    {
        return kernel::single('taoexlib_account');
    }

    /** 统一取入参：JSON body 优先，兼容 form-urlencoded */
    private function input()
    {
        $data = $this->getJsonBody();
        if (empty($data) && !empty($_POST)) {
            $data = $_POST;
        }
        return is_array($data) ? $data : [];
    }

    #[RequirePermission('rolescfg')]
    public function index(): void
    {
        $view = $this->lib()->getView();
        $account = $view['account'];
        if (is_array($account)) {
            // 脱敏：绝不回传短信平台密码
            unset($account['password']);
        }

        $this->succ([
            'bound' => $view['bound'],
            'account' => $account,
            'frameurl' => $view['frameurl'],
            'info' => $view['info'],
            'warningnumber' => $view['warningnumber'],
            'regEmail' => $view['regEmail'],
        ]);
    }

    #[RequirePermission('rolescfg')]
    public function bind(): void
    {
        $input = $this->input();
        $result = $this->lib()->bind((string) ($input['bindemail'] ?? ''), (string) ($input['bindpassword'] ?? ''));

        $result['ok'] ? $this->succ(['message' => $result['message']]) : $this->fail($result['message']);
    }

    #[RequirePermission('rolescfg')]
    public function unbind(): void
    {
        $result = $this->lib()->unbind();

        $result['ok'] ? $this->succ(['message' => $result['message']]) : $this->fail($result['message']);
    }

    #[RequirePermission('rolescfg')]
    public function register(): void
    {
        $result = $this->lib()->register($this->input());

        $result['ok'] ? $this->succ(['message' => $result['message']]) : $this->fail($result['message']);
    }
}
