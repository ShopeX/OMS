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
 * 短信开通（taoexlib 账号）共享逻辑
 *
 * legacy 控制器 taoexlib_ctl_admin_account 与 api 控制器 taoexlib_ctl_api_account 共同调用，
 * 保证「legacy 菜单页」与「Vue 页」走同一实现 —— 写链路不再散落在各自的 act 里。
 * 状态仍存 kvstore taoexlib/account（serialize 存法沿用 legacy，保证两页互认）。
 */
class taoexlib_account
{
    /** 读取当前绑定账号；未绑定返回 null */
    public function getAccount()
    {
        $raw = null;
        base_kvstore::instance('taoexlib')->fetch('account', $raw);
        $data = $raw ? unserialize($raw) : [];
        return (is_array($data) && !empty($data['entid'])) ? $data : null;
    }

    private function storeAccount(array $data)
    {
        base_kvstore::instance('taoexlib')->store('account', serialize($data));
    }

    /** 注册页默认邮箱（legacy bind.html 与 Vue 页共用） */
    public function regEmail()
    {
        return (string) taoexlib_utils::regEmail();
    }

    /** 页面展示数据：未绑定 ⇒（bind 态）regEmail；已绑定 ⇒ frameurl/info/warningnumber */
    public function getView()
    {
        $warningnumber = app::get('taoexlib')->getConf('taoexlib.message.warningnumber');
        $account = $this->getAccount();
        if (!$account) {
            return [
                'bound' => false,
                'account' => null,
                'frameurl' => '',
                'info' => [],
                'warningnumber' => $warningnumber,
                'regEmail' => $this->regEmail(),
            ];
        }
        return [
            'bound' => true,
            'account' => $account,
            'frameurl' => taoexlib_utils::get_book_url($account),
            'info' => (array) taoexlib_utils::get_user_info($account),
            'warningnumber' => $warningnumber,
            'regEmail' => '',
        ];
    }

    /** 绑定：校验 + 登录短信平台 + 落 kvstore */
    public function bind($email, $password)
    {
        if ('' === trim((string) $email)) {
            return ['ok' => false, 'message' => 'email不能为空！'];
        }
        if ('' === trim((string) $password)) {
            return ['ok' => false, 'message' => '密码不能为空！'];
        }

        $result = taoexlib_utils::login(['identifier' => trim($email), 'password' => trim($password)]);
        if (isset($result->res) && 'succ' === $result->res) {
            $this->storeAccount([
                'entid' => $result->info->entid,
                'password' => trim($password),
                'email' => $result->info->email,
                'status' => 1,
            ]);
            return ['ok' => true, 'message' => '绑定成功！'];
        }

        return ['ok' => false, 'message' => '帐号或密码错误！'];
    }

    /** 解绑 */
    public function unbind()
    {
        $this->storeAccount([]);
        return ['ok' => true, 'message' => '操作成功！'];
    }

    /** 注册：按 legacy 的 8 项校验 + 调用注册 + 落 kvstore + 赠品补做 */
    public function register(array $post)
    {
        $email = trim((string) ($post['email'] ?? ''));
        $password = trim((string) ($post['password'] ?? ''));
        $confirm = trim((string) ($post['confirm'] ?? ''));
        $owner = trim((string) ($post['owner'] ?? ''));
        $wangwang = trim((string) ($post['wangwang'] ?? ''));
        $paipai = trim((string) ($post['paipai'] ?? ''));
        $tel = trim((string) ($post['tel'] ?? ''));
        $address = trim((string) ($post['address'] ?? ''));
        $postcode = trim((string) ($post['postcode'] ?? ''));
        $region = explode(':', (string) ($post['region'] ?? ''));
        $regionArr = explode('/', (string) ($region[1] ?? ''));

        if ('' === $email) {
            return ['ok' => false, 'message' => 'email不能为空！'];
        }
        if ('' === $password) {
            return ['ok' => false, 'message' => '密码不能为空！'];
        }
        if ($confirm !== $password) {
            return ['ok' => false, 'message' => '两次输入密码不一致！'];
        }
        if ('' === $owner) {
            return ['ok' => false, 'message' => '联系人不能为空！'];
        }
        if ('' === $wangwang && '' === $paipai) {
            return ['ok' => false, 'message' => '旺旺号和拍拍号必须输入一个！'];
        }
        if ('' === $tel) {
            return ['ok' => false, 'message' => '联系电话不能为空！'];
        }
        if (empty($regionArr[0]) || empty($regionArr[1])) {
            return ['ok' => false, 'message' => '请选择所属地区！'];
        }
        if ('' === $address) {
            return ['ok' => false, 'message' => '详细地址不能为空！'];
        }
        if ('' === $postcode) {
            return ['ok' => false, 'message' => '邮编不能为空！'];
        }

        $param = [
            'email' => $email,
            'entPwd' => $password,
            'source' => 'shopex_tcrm',
            'owner' => $owner,
            'biz_user' => $wangwang,
            'biz_paipai' => $paipai,
            'tel' => $tel,
            'province' => taoexlib_utils::province_mapping($regionArr[0]),
            'city' => $regionArr[1],
            'address' => $address,
            'postalcode' => $postcode,
        ];

        $result = taoexlib_utils::register($param);
        if (isset($result->res) && 'succ' === $result->res) {
            $info = $result->info;
            $this->storeAccount([
                'entid' => $info->entid,
                'password' => $password,
                'email' => $info->email,
                'status' => 1,
            ]);
            base_kvstore::instance('taoexlib')->store('hasRegister', 1);
            base_kvstore::instance('taoexlib')->store('regEmail', $info->email);
            $this->buyPresentIfNeeded();
            return ['ok' => true, 'message' => '注册成功！'];
        }

        $msg = isset($result->msg) ? $result->msg : '';
        return ['ok' => false, 'message' => $msg ? $msg : '注册失败！'];
    }

    /** 注册成功后的「赠品购买」补做（原 legacy register() 内联逻辑原样搬入） */
    private function buyPresentIfNeeded()
    {
        $present = null;
        base_kvstore::instance('taoexlib')->fetch('present', $present);
        if ('yes' === $present || !defined('TAOEXLIB_PRESENT_ID')) {
            return;
        }
        $account = $this->getAccount();
        if (!$account) {
            return;
        }
        $result = taoexlib_utils::buy($account, TAOEXLIB_PRESENT_ID, time());
        if (isset($result->res) && 'succ' === $result->res) {
            base_kvstore::instance('taoexlib')->store('present', 'yes');
        }
    }
}
