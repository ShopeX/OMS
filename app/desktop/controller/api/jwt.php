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

class desktop_ctl_api_jwt extends desktop_ctl_api_base
{
    public $workground = '';
    protected $loginExempt = [
        'login', 'refresh', 'logout', 'localLogout', 'meta', 'genCaptcha', 'genIdaasCaptcha',
        'sendSecondFactorCode', 'verifySecondFactor',
        'loginViaOidc',
    ];

    public function meta()
    {
        $this->succ(desktop_auth_jwt_flow::metaPayload());
    }

    public function genCaptcha()
    {
        $this->succ(desktop_auth_passport::generateCaptcha());
    }

    public function login()
    {
        try {
            $result = desktop_auth_jwt_flow::loginBrowser(desktop_auth_request::jsonBody());
            if (!$result->isAuthenticated()) {
                return $this->fail($result->message(), $result->statusCode(), $result->data());
            }

            $this->succ(desktop_auth_jwt_transport::browserLoginPayload($result));
        } catch (Throwable $e) {
            $resolved = desktop_auth_exception::resolveForApi($e, '登录失败，请稍后重试');
            $this->fail($resolved['message'], $resolved['status'], $resolved['extra']);
        }
    }

    public function genIdaasCaptcha()
    {
        if (!desktop_auth_idaas::isEnabled()) {
            return $this->fail('未开启 IDAAS 登录', 404);
        }

        try {
            $payload = desktop_auth_request::jsonBody();
            // SCRUM-7779：账号优先取密文（前端不再明文上网）；无密文时回退明文（老客户端 / 直连 GET）
            $loginName = desktop_auth_login_cipher::resolveUsername(
                $payload,
                (string)($_GET['username'] ?? '')
            );
            if ($loginName === null) {
                return $this->fail('登录信息异常，请刷新页面后重试', 422, [
                    'reason' => 'login_payload_invalid',
                ]);
            }

            $captcha = desktop_auth_idaas::fetchCaptcha($loginName);
            if (!$captcha['ok']) {
                return $this->fail($captcha['message'], 502, [
                    'reason' => 'idaas_captcha_fetch_failed',
                ]);
            }

            $this->succ([
                'code' => $captcha['code'],
                'captcha' => $captcha['captcha'],
            ]);
        } catch (Throwable $e) {
            $resolved = desktop_auth_exception::resolveForApi($e, 'IDAAS 验证码获取失败', 502);
            $this->fail($resolved['message'], $resolved['status'], $resolved['extra']);
        }
    }

    public function me()
    {
        $payload = desktop_auth_jwt_flow::mePayload(desktop_auth_context::userId());
        if ($payload === null) {
            $this->fail('登录状态已过期', 401, ['reason' => 'session_expired']);
            return;
        }

        $this->succ($payload);
    }

    public function refresh()
    {
        $client = desktop_auth_jwt_client::fromRequest();
        $result = desktop_auth_jwt_flow::refresh(desktop_auth_request::jsonBody(), $client);

        if (!$result->isAuthenticated()) {
            desktop_auth_jwt_transport::respondUnauthorizedFromResult($result, true);
            return;
        }

        $userId = (int)($result->userId() ?? 0);
        if ($userId <= 0) {
            $this->fail('刷新令牌无效', 401, ['reason' => 'jwt_revocable']);
            return;
        }

        $refreshData = $result->data();
        $loginName = (string)($refreshData['login_name'] ?? '');
        $profile = desktop_auth_profile::buildBrowser($userId, $loginName);
        $loginNameForToken = $profile['login_name'] ?? $loginName;
        if (!empty($refreshData['login_time'])) {
            $profile['login_time'] = (int)$refreshData['login_time'];
        }
        // SCRUM-7917 Plan B: keep the refresh rotation inside the same session family.
        if (!empty($refreshData['fid'])) {
            $profile['fid'] = (string)$refreshData['fid'];
        }

        $refreshExpiresAt = (int)($refreshData['refresh_expires_at'] ?? 0);
        $loginResult = desktop_auth_jwt_flow::completeBrowserLogin(
            $userId,
            $loginNameForToken,
            $profile,
            false,
            [],
            $refreshExpiresAt > 0 ? $refreshExpiresAt : null
        );
        $this->succ(desktop_auth_jwt_transport::browserLoginPayload($loginResult));
    }

    public function logout()
    {
        $token = desktop_auth_jwt::extractBrowserAccessToken();
        try {
            $result = desktop_auth_jwt_flow::logout($token);
        } catch (desktop_auth_exception $e) {
            $this->fail('退出失败，请稍后重试', 500);
            return;
        }

        $userId = (int)($result->userId() ?? 0);
        $oidcLogoutUrl = null;
        if ($userId > 0) {
            // 复用已有 loginOutUrl（拼 endsession 地址）；浏览器打开后才能清 IdP 会话
            try {
                $rs = kernel::single('erpapi_router_request')->set('account', 'account')->user_loginOutUrl([
                    'user_id' => $userId,
                ]);
                if (($rs['rsp'] ?? '') === 'succ' && !empty($rs['data'])) {
                    $oidcLogoutUrl = (string)$rs['data'];
                }
            } catch (Throwable $e) {
            }
            desktop_auth_oidc::clearIdToken($userId);
        }

        desktop_auth_context::clear();
        desktop_auth_jwt_transport::clearDesktopAuthCookies();
        kernel::single('pam_passport_basic')->loginout(desktop_auth_passport::makeAuth(), 'index.php');

        $payload = ['message' => '退出成功'];
        if ($oidcLogoutUrl) {
            $payload['oidc_logout_url'] = $oidcLogoutUrl;
        }
        $this->succ($payload);
    }

    /**
     * Local logout: clear THIS browser's auth cookies WITHOUT the global
     * token_version bump (SCRUM-7917).
     *
     * Used by passive sign-outs (front-end idle timeout etc.). Shared-account
     * peers must not be kicked offline by this browser going idle; manual
     * logout keeps using logout() which revokes the whole token family.
     */
    public function localLogout()
    {
        $token = desktop_auth_jwt::extractBrowserAccessToken();
        try {
            $result = desktop_auth_jwt_flow::localLogout($token);
        } catch (desktop_auth_exception $e) {
            $this->fail('退出失败，请稍后重试', 500);
            return;
        }

        $userId = (int)($result->userId() ?? 0);
        if ($userId > 0) {
            // 只清本浏览器持有的 id_token 缓存；不动 IdP 会话（无 oidc_logout_url），
            // 也不清共享的 desktop_auth_oidc 登记以外的东西
            desktop_auth_oidc::clearIdToken($userId);
        }

        desktop_auth_context::clear();
        desktop_auth_jwt_transport::clearDesktopAuthCookies();
        // 注意：刻意不调 pam_passport_basic::loginout() —— 那会走 legacy session
        // 注销（含 session_id 清理语义），这里只回收 JWT cookie 即可。

        $this->succ(['message' => '退出成功']);
    }

    public function sendSecondFactorCode()
    {
        $payload = desktop_auth_request::jsonBody();
        $fid = trim((string)($payload['fid'] ?? ''));
        // SCRUM-7779：账号与登录同口径，有密文只认密文 uname，不透传顶层明文
        $username = desktop_auth_login_cipher::resolveUsername($payload);
        if ($username === null) {
            return $this->fail('登录信息异常，请刷新页面后重试', 422, ['reason' => 'login_payload_invalid']);
        }
        if ($fid === '' || $username === '') {
            return $this->fail('缺少 fid 或用户名', 422);
        }

        try {
            $result = desktop_auth_idaas::resendCode($username, $fid);
        } catch (Throwable $e) {
            $resolved = desktop_auth_exception::resolveForApi($e, '短信发送失败', 502);
            $this->fail($resolved['message'], $resolved['status'], $resolved['extra']);
            return;
        }

        if (!$result['ok']) {
            $status = (int)($result['status'] ?? 502);
            $reason = (string)($result['reason'] ?? 'idaas_sms_failed');

            return $this->fail($result['message'], $status, ['reason' => $reason]);
        }

        $this->succ(['message' => '短信验证码已发送']);
    }

    public function verifySecondFactor()
    {
        try {
            $result = desktop_auth_jwt_flow::verifySecondFactorBrowser(desktop_auth_request::jsonBody());
            if (!$result->isAuthenticated()) {
                return $this->fail($result->message(), $result->statusCode(), $result->data());
            }

            $this->succ(desktop_auth_jwt_transport::browserLoginPayload($result));
        } catch (Throwable $e) {
            $resolved = desktop_auth_exception::resolveForApi($e, '二次认证失败，请稍后重试');
            $this->fail($resolved['message'], $resolved['status'], $resolved['extra']);
        }
    }

    public function loginViaOidc()
    {
        $result = desktop_auth_jwt_flow::loginViaOidcBrowser();
        if (!$result->isAuthenticated()) {
            return $this->fail($result->message(), $result->statusCode(), $result->data());
        }

        $this->succ(desktop_auth_jwt_transport::browserLoginPayload($result));
    }
}
