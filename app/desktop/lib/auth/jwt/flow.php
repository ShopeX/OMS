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

class desktop_auth_jwt_flow
{
    /**
     * Login page meta payload for Vue.
     *
     * @return array<string, mixed>
     */
    public static function metaPayload(): array
    {
        $auth = desktop_auth_passport::makeAuth();
        $site = kernel::single('desktop_site')->getInfo();

        return [
            'show_vcode' => $auth->is_enable_vcode(),
            'idaas_captcha' => desktop_auth_idaas::isEnabled(),
            'mobile_check' => desktop_auth_passport::needMobileCheck(),
            'site_info' => $site,
            'legacy_login_url' => kernel::router()->gen_url(['ctl' => 'passport'], 1),
            'oidc_login' => desktop_auth_oidc::loginInfo(),
            'idle_timeout_minutes' => desktop_auth_jwt::configuredIdleTimeoutMinutes(),
            // SCRUM-7779：登录密文口径材料（前端按 legacy login_cipher 口径加密提交）
            'login_iv' => desktop_auth_login_cipher::iv(),
            'login_key_date' => desktop_auth_login_cipher::keyDate(),
        ];
    }

    /**
     * Browser username/password login orchestration.
     */
    public static function loginBrowser(array $payload): desktop_auth_jwt_result
    {
        // SCRUM-7779：密文登录（复用 legacy login_cipher 口径）+ 一次性 nonce 防重放。
        // 明文路径保留（其它客户端/老版本不受影响）。
        $cipher = (string)($payload['login_cipher'] ?? '');
        if ($cipher !== '') {
            $decoded = desktop_auth_login_cipher::decrypt($cipher);
            if ($decoded === null) {
                return desktop_auth_jwt_result::apiError('登录信息异常，请刷新页面后重试', 422, 'login_payload_invalid');
            }
            // 修法 2：nonce 由前端生成（meta 不再签发 ⇒ 无 KV 放大面）+ 密文时间戳新鲜度校验
            if (!desktop_auth_login_nonce::freshTimestamp((int)($decoded['ts'] ?? 0))
                || !desktop_auth_login_nonce::claim((string)($decoded['nonce'] ?? ''))) {
                return desktop_auth_jwt_result::apiError('登录信息已失效，请刷新页面后重试', 422, 'login_payload_expired');
            }
            $payload['username'] = (string)($decoded['uname'] ?? '');
            $payload['password'] = (string)($decoded['password'] ?? '');
        }

        $username = trim((string)($payload['username'] ?? ''));
        $password = (string)($payload['password'] ?? '');
        if ($username === '' || $password === '') {
            return desktop_auth_jwt_result::apiError('用户名或密码不能为空', 422, 'validation_error');
        }

        $verifyCode = (string)($payload['verifycode'] ?? '');
        $captchaId = (string)($payload['captcha_id'] ?? '');
        $idaasCaptchaCode = trim((string)($payload['idaas_captcha_code'] ?? ''));
        $idaasCaptchaValue = trim((string)($payload['idaas_captcha_value'] ?? ($payload['idaas_captcha'] ?? '')));
        $needMobile = (string)($payload['needmobileverifycode'] ?? '');

        $_POST['login_name'] = $username;
        $_POST['login_password'] = $password;

        $accountType = desktop_auth_passport::accountType();
        $auth = desktop_auth_passport::makeAuth();

        if (desktop_auth_passport::needVcode()) {
            if ($captchaId === '' || $verifyCode === '') {
                return desktop_auth_jwt_result::apiError('请输入图形验证码', 422, 'captcha_required');
            }
            if (!desktop_auth_passport::verifyCaptchaById($captchaId, $verifyCode)) {
                return desktop_auth_jwt_result::apiError('图形验证码不正确，请重新输入', 422, 'captcha_invalid');
            }
        }
        $auth->set_enable_vcode(false);

        $passport = kernel::single('pam_passport_basic');
        $authData = [];
        $userId = $passport->login($auth, $authData);
        desktop_auth_passport::logLoginAttempt($accountType, $authData);

        if (!$userId) {
            return desktop_auth_jwt_result::apiError(
                desktop_auth_passport::loginFailureMessage(),
                desktop_auth_passport::loginFailureStatus(),
                'credential_error',
                desktop_auth_passport::loginFailureExtra($needMobile)
            );
        }

        $authData['account_type'] = $accountType;
        $auth->account()->update('pam_passport_basic', $userId, $authData);

        // 服务账号（account_type=service）禁止建立桌面会话（防御纵深之一）
        $svcUser = app::get('desktop')->model('users')->dump($userId, 'account_type,status');
        if (is_array($svcUser) && ($svcUser['account_type'] ?? 'human') === 'service') {
            desktop_auth_passport::logLoginAttempt($accountType, array_merge($authData, ['login_result' => 'service_account_denied']));
            return desktop_auth_jwt_result::apiError('服务账号不能登录控制台', 403, 'service_account_denied');
        }

        $secondFactorChallenge = desktop_auth_jwt::finalizeSession(
            $userId,
            $password,
            $auth,
            $username,
            $accountType,
            $idaasCaptchaCode,
            $idaasCaptchaValue
        );
        if ($secondFactorChallenge !== null) {
            return desktop_auth_jwt_result::apiError(
                $secondFactorChallenge['message'],
                (int)($secondFactorChallenge['status'] ?? 403),
                (string)($secondFactorChallenge['data']['reason'] ?? 'idaas_challenge'),
                $secondFactorChallenge['data'] ?? []
            );
        }

        $profile = desktop_auth_profile::buildBrowser($userId, $username);
        $loginNameForToken = $profile['login_name'] ?? $username;

        return self::completeBrowserLogin($userId, $loginNameForToken, $profile, true, [
            'captcha_id' => $captchaId,
            'error_counter_app_id' => $auth->appid,
        ]);
    }

    /**
     * Issue browser cookies + CSRF and return login success result.
     *
     * @param array{captcha_id?:string,fid?:string,oidc_state?:string,error_counter_app_id?:string} $transientCleanup
     */
    public static function completeBrowserLogin(
        int $userId,
        string $loginNameForToken,
        array $profile,
        bool $incrementVersion = true,
        array $transientCleanup = [],
        ?int $preserveRefreshExpiresAt = null
    ): desktop_auth_jwt_result {
        desktop_auth_login_transient::purge($transientCleanup);

        $tokens = self::issueTokensForUser(
            $userId,
            $loginNameForToken,
            $profile,
            $incrementVersion,
            $preserveRefreshExpiresAt
        );
        desktop_auth_jwt_transport::setBrowserAuthCookies($tokens);

        $refreshExp = (int)($tokens['refresh']['expires_at'] ?? 0);
        $csrfToken = kernel::single('desktop_csrf')->set_token($refreshExp > 0 ? $refreshExp : null);

        return self::browserSuccessResult(
            $tokens,
            $profile,
            desktop_auth_jwt::configuredIdleTimeoutMinutes(),
            $csrfToken
        );
    }

    /**
     * IDAAS second-factor verification → browser login.
     */
    public static function verifySecondFactorBrowser(array $payload): desktop_auth_jwt_result
    {
        $fid = trim((string)($payload['fid'] ?? ''));
        // SCRUM-7779：账号与登录同口径，有密文只认密文 uname，不透传顶层明文
        $username = desktop_auth_login_cipher::resolveUsername($payload);
        if ($username === null) {
            return desktop_auth_jwt_result::apiError('登录信息异常，请刷新页面后重试', 422, 'login_payload_invalid');
        }
        $code = trim((string)($payload['mobileverifycode'] ?? ''));
        if ($fid === '' || $username === '' || $code === '') {
            return desktop_auth_jwt_result::apiError('缺少 fid、用户名或验证码', 422, 'validation_error');
        }

        $result = desktop_auth_idaas::completeVerify($fid, $username, $code);
        if (!$result['ok']) {
            $reason = (string)($result['reason'] ?? 'idaas_second_factor_failed');
            $extra = ['reason' => $reason];
            if (!empty($result['idaas_error_code'])) {
                $extra['idaas_error_code'] = $result['idaas_error_code'];
            }

            return desktop_auth_jwt_result::apiError(
                $result['message'],
                (int)$result['status'],
                $reason,
                $extra
            );
        }

        $userId = (int)$result['user_id'];
        $loginName = (string)$result['login_name'];
        $profile = desktop_auth_profile::buildBrowser($userId, $loginName);
        $loginNameForToken = $profile['login_name'] ?? $loginName;

        return self::completeBrowserLogin($userId, $loginNameForToken, $profile, true, [
            'fid' => $fid,
            'error_counter_app_id' => desktop_auth_passport::makeAuth()->appid,
        ]);
    }

    /**
     * OIDC trust grant → browser login.
     */
    public static function loginViaOidcBrowser(): desktop_auth_jwt_result
    {
        $userId = pam_trust_grant::redeem('oidc');
        if ($userId <= 0) {
            return desktop_auth_jwt_result::apiError('OIDC 会话无效或已过期，请重新登录', 401, 'oidc_expired');
        }

        $userModel = app::get('desktop')->model('users');
        $user = $userModel->dump(
            ['user_id' => $userId],
            'user_id,name,super,status',
            [':account@pam' => ['login_name']]
        );
        if (!$user || empty($user['account']['login_name'])) {
            return desktop_auth_jwt_result::apiError('用户信息异常，请联系管理员', 500, 'user_invalid');
        }
        if (($user['status'] ?? '0') !== '1') {
            return desktop_auth_jwt_result::apiError('账号已被禁用', 403, 'forbidden');
        }

        $loginName = $user['account']['login_name'];
        $profile = desktop_auth_profile::buildBrowser($userId, $loginName);

        return self::completeBrowserLogin($userId, $loginName, $profile, true, [
            'error_counter_app_id' => desktop_auth_passport::makeAuth()->appid,
        ]);
    }

    /**
     * Current session user payload for /me.
     *
     * @return array<string, mixed>|null null when session invalid
     */
    public static function mePayload(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $userModel = app::get('desktop')->model('users');
        $user = $userModel->dump(
            ['user_id' => $userId],
            'user_id,name,super,status',
            [':account@pam' => ['login_name']]
        );
        if (!$user || ($user['status'] ?? '0') !== '1') {
            return null;
        }

        $loginName = (string)($user['account']['login_name'] ?? '');
        $profile = desktop_auth_profile::buildBrowser($userId, $loginName);

        $claims = desktop_auth_jwt::claims(true);
        $expiresIn = desktop_auth_jwt::DEFAULT_TTL;
        if (!empty($claims['exp'])) {
            $expiresIn = max(0, (int)$claims['exp'] - time());
        }

        $payload = [
            'expires_in' => $expiresIn,
            'idle_timeout_minutes' => desktop_auth_jwt::configuredIdleTimeoutMinutes(),
            'user' => desktop_auth_profile::forBrowserClient($profile),
        ];
        $aiAssistantAuth = self::aiAssistantAuthPayload($userId);
        if ($aiAssistantAuth !== null) {
            $payload['ai_assistant_auth'] = $aiAssistantAuth;
        }

        return $payload;
    }

    /**
     * Issue tokens and produce a result object.
     *
     * Does NOT set cookies — the caller (controller or flow) must
     * call transport::setBrowserAuthCookies() or transport::setWapAuthCookies()
     * after this method returns.
     *
     * @param int    $userId
     * @param string $loginNameForToken
     * @param array  $profile   user profile fields
     * @return array tokens array from desktop_auth_jwt::issueTokens()
     */
    public static function issueTokensForUser(
        int $userId,
        string $loginNameForToken,
        array $profile,
        bool $incrementVersion = true,
        ?int $preserveRefreshExpiresAt = null
    ): array
    {
        // Login-like flows increment token_version when single-session is enforced.
        // Refresh flows must not increment, otherwise freshly rotated tokens revoke
        // other active tokens for the same user.
        if ($incrementVersion && desktop_auth_jwt::isSingleSessionEnforced()) {
            desktop_auth_jwt::incrementTokenVersion($userId);
        }

        return desktop_auth_jwt::issueTokens(
            $userId,
            $loginNameForToken,
            $profile,
            $preserveRefreshExpiresAt
        );
    }

    /**
     * Build the browser-success result for login / refresh / 2FA / OIDC.
     *
     * Returns a result with transportMode = 'browser'; the caller must call
     * transport::setBrowserAuthCookies($tokens) before responding.
     *
     * The response body is token-free; caller uses
     * transport::browserLoginPayload($result) to build the JSON body.
     *
     * @param array $tokens   from issueTokensForUser()
     * @param array $profile  user profile
     * @param int   $idleTimeoutMinutes
     * @param string $csrfToken  raw CSRF token from desktop_csrf::set_token()
     * @return desktop_auth_jwt_result
     */
    public static function browserSuccessResult(
        array $tokens,
        array $profile,
        int $idleTimeoutMinutes = 60,
        string $csrfToken = ''
    ): desktop_auth_jwt_result {
        $data = [
            'expires_in' => $tokens['access']['expires_in'] ?? 300,
            'idle_timeout_minutes' => $idleTimeoutMinutes,
            'user' => $profile,
        ];
        if ($csrfToken !== '') {
            $data['csrf_token'] = $csrfToken;
        }
        $userId = (int)($profile['id'] ?? 0);
        $aiAssistantAuth = self::aiAssistantAuthPayload($userId);
        if ($aiAssistantAuth !== null) {
            $data['ai_assistant_auth'] = $aiAssistantAuth;
        }
        return desktop_auth_jwt_result::loginSucceeded(
            $data,
            [],                                    // cookies written by caller via transport
            'browser'
        );
    }

    /**
     * AI Assistant embed 下游鉴权（需已登录；无 MCP 凭证时返回 null）。
     * 返回 Base-Url + Client-Id + Client-Secret(JWT) + Client-Mode=jwt；绝不回传真实 MCP Secret。
     *
     * @return array{mode:string,X-Shopex-OMS-Base-Url:string,X-Shopex-OMS-Client-Id:string,X-Shopex-OMS-Client-Secret:string,X-Shopex-OMS-Client-Mode:string}|null
     */
    public static function aiAssistantAuthPayload(int $userId): ?array
    {
        if ($userId < 1
            || !app::get('mcpapi')->is_installed()
            || !class_exists('mcpapi_auth_client_manager')) {
            return null;
        }

        try {
            $manager = new mcpapi_auth_client_manager();
            $downstream = $manager->assistantDownstreamAuthForOwner($userId);
            if ($downstream === null) {
                return null;
            }

            $loginName = '';
            $account = app::get('pam')->model('account')->getList('login_name', ['account_id' => $userId], 0, 1);
            if (is_array($account[0] ?? null)) {
                $loginName = (string) ($account[0]['login_name'] ?? '');
            }
            if ($loginName === '') {
                $loginName = (string) $userId;
            }

            $issued = desktop_auth_jwt::issueAiAssistantToken($userId, $loginName);
            $token = (string) ($issued['token'] ?? '');
            if ($token === '') {
                return null;
            }

            return [
                'mode' => 'jwt',
                'X-Shopex-OMS-Base-Url' => $downstream['X-Shopex-OMS-Base-Url'],
                'X-Shopex-OMS-Client-Id' => $downstream['X-Shopex-OMS-Client-Id'],
                'X-Shopex-OMS-Client-Secret' => $token,
                'X-Shopex-OMS-Client-Mode' => 'jwt',
            ];
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Build the browser-success result for logout.
     *
     * @return desktop_auth_jwt_result
     */
    public static function logoutResult(?int $userId = null): desktop_auth_jwt_result
    {
        return new desktop_auth_jwt_result(
            true,
            $userId,
            200,
            'logout_succeeded',
            '退出成功',
            [],
            [],
            false,
            'browser'
        );
    }

    /**
     * Refresh tokens for a browser client.
     *
     * Reads refresh_token from the HttpOnly cookie when not supplied in payload.
     *
     * @param array                       $payload  JSON body (may contain refresh_token key)
     * @param desktop_auth_jwt_client|null $client   defaults to current request client
     * @return desktop_auth_jwt_result
     */
    public static function refresh(array $payload, ?desktop_auth_jwt_client $client = null): desktop_auth_jwt_result
    {
        $client = $client ?? desktop_auth_jwt_client::fromRequest();

        // Browser reads refresh token from cookie when not in payload
        $refreshToken = desktop_auth_jwt::extractBrowserRefreshToken();
        if ($refreshToken === null || $refreshToken === '') {
            return desktop_auth_jwt_result::unauthorized(
                '缺少 refresh_token',
                'missing_refresh_token',
                [],
                false
            );
        }

        // Decode, blacklist jti (rotation), detect reuse of old refresh tokens.
        try {
            $claims = self::consumeRefreshClaims($refreshToken);
        } catch (desktop_auth_exception $e) {
            return desktop_auth_jwt_result::unauthorized(
                $e->getMessage(),
                'jwt_revocable',
                [],
                false
            );
        }

        // Validate user still exists and is active
        $userId = (int)($claims['sub'] ?? 0);
        if ($userId <= 0) {
            return desktop_auth_jwt_result::unauthorized(
                '刷新令牌无效',
                'jwt_revocable',
                [],
                true
            );
        }

        $userModel = app::get('desktop')->model('users');
        $user = $userModel->dump(
            ['user_id' => $userId],
            'user_id,name,super,status',
            [':account@pam' => ['login_name']]
        );
        if (!$user) {
            return desktop_auth_jwt_result::unauthorized(
                '账号不存在或已被删除',
                'jwt_revocable',
                [],
                true
            );
        }
        if (($user['status'] ?? '0') !== '1') {
            return desktop_auth_jwt_result::unauthorized(
                '账号已被禁用',
                'forbidden',
                [],
                true
            );
        }

        $loginName = $user['account']['login_name'];
        // Delegate profile construction to controller helper — flow does not
        // re-implement user-profile building to avoid duplicating business logic
        $profileData = [
            'login_name' => $loginName,
            'user_id' => $userId,
            'is_super' => (bool)($user['super'] ?? false),
            'login_time' => desktop_auth_jwt::preservedLoginTimeFromClaims($claims),
            // Absolute refresh exp from login — refresh must not slide this forward.
            'refresh_expires_at' => (int)($claims['exp'] ?? 0),
            // SCRUM-7917 Plan B: rotate within the SAME session family.
            'fid' => (string)($claims[desktop_auth_jwt::CLAIM_FID] ?? ''),
        ];

        return desktop_auth_jwt_result::refreshSucceeded(
            $profileData,
            [],
            'browser',
            $userId
        );
    }

    /**
     * Logout: revoke token_version and produce a result with cookie-clearing flag.
     *
     * Strategy ( Browser access expired + refresh valid场景 ):
     *   1. Try access token decode  → sub as userId
     *   2. If access decode fails: try browser refresh cookie → sub as userId
     *   3. Final fallback: desktop_auth_context::userId()
     *
     * DB revoke (incrementTokenVersion) MUST succeed before returning logout_succeeded.
     * On DB failure: throws desktop_auth_exception → caller must fail-closed (500).
     *
     * @param string|null                  $token    raw access token from request (optional)
     * @return desktop_auth_jwt_result
     * @throws desktop_auth_exception on DB revoke failure (caller MUST catch and fail-closed)
     */
    public static function logout(?string $token): desktop_auth_jwt_result
    {
        [$userId, $familyId, $familyExpiresAt] = self::resolveLogoutUser($token);

        if ($userId <= 0) {
            // Cannot determine user — treat as already unauthenticated, still clear cookies.
            return self::logoutResult(null);
        }

        // SCRUM-7917 Plan B：手动登出只吊销本浏览器的会话族（fid），
        // 不再 bump token_version —— 多人共用一账号时其他人不受影响。
        // 全账号吊销语义保留在：改密、refresh 重用检测、互踢（restrict=true 登录 bump）。
        // 族黑名单 TTL 覆盖该族 refresh 最大剩余寿命，access 自然过期。
        if ($familyId !== '') {
            desktop_auth_jwt::revokeFamily($familyId, $familyExpiresAt);
        }

        return self::logoutResult($userId);
    }

    /**
     * Local logout: clear THIS browser's auth cookies WITHOUT bumping token_version.
     *
     * Used by passive sign-outs (front-end idle timeout / session-expiry cleanup).
     * Multiple people sharing one account is a supported scenario; a global
     * token_version bump here would kick every other active session of the same
     * account offline (SCRUM-7917). Only this browser's cookies are invalidated;
     * the shared token_version, other sessions and even this session family stay
     * untouched (the browser simply drops its own cookies).
     *
     * @param string|null $token raw access token from request (optional)
     * @return desktop_auth_jwt_result
     */
    public static function localLogout(?string $token): desktop_auth_jwt_result
    {
        [$userId] = self::resolveLogoutUser($token);

        // Deliberately NO incrementTokenVersion() — see docblock above.
        return self::logoutResult($userId > 0 ? $userId : null);
    }

    /**
     * Resolve the acting user for a logout-style request.
     *
     * Same three-strategy resolution as logout(): access token → refresh cookie
     * → authenticated context. Returns [userId, familyId, familyExpiresAt] with
     * userId = 0 and familyId = '' when the client cannot be identified.
     * familyExpiresAt = refresh exp (when resolvable) so the revocation marker
     * TTL can be scoped to the family's real remaining lifetime.
     *
     * @param string|null $token raw access token from request (optional)
     * @return array{0:int,1:string,2:int|null}
     */
    private static function resolveLogoutUser(?string $token): array
    {
        $userId = 0;
        $familyId = '';
        $familyExpiresAt = null;

        // Strategy 1: access token decode (tolerates expiry)
        if ($token !== null && $token !== '') {
            try {
                $accessPayload = desktop_auth_jwt::decode($token, false);
                $userId = (int)($accessPayload['sub'] ?? 0);
                $familyId = (string)($accessPayload[desktop_auth_jwt::CLAIM_FID] ?? '');
            } catch (desktop_auth_exception $e) {
                // fall through to strategy 2
            }
        }

        // Strategy 2: access expired or absent — try the browser refresh cookie.
        // This ensures logout still revokes the family even when access is expired.
        if ($userId <= 0) {
            $refreshToken = desktop_auth_jwt::extractBrowserRefreshToken();
            if ($refreshToken !== null && $refreshToken !== '') {
                try {
                    $refreshPayload = desktop_auth_jwt::decodeRefresh($refreshToken);
                    $userId = (int)($refreshPayload['sub'] ?? 0);
                    $familyId = (string)($refreshPayload[desktop_auth_jwt::CLAIM_FID] ?? '');
                    $exp = (int)($refreshPayload['exp'] ?? 0);
                    if ($exp > 0) {
                        $familyExpiresAt = $exp;
                    }
                } catch (desktop_auth_exception $e) {
                    // refresh also invalid/unavailable — fall through to context
                }
            }
        }

        // Strategy 3: last resort — authenticated context (may be set by guard on valid session)
        if ($userId <= 0) {
            $userId = desktop_auth_context::userId();
        }

        return [$userId, $familyId, $familyExpiresAt];
    }

    /**
     * Validate refresh token, blacklist jti (rotation), detect reuse.
     *
     * @return array<string, mixed>
     * @throws desktop_auth_exception
     */
    private static function consumeRefreshClaims(string $refreshToken): array
    {
        try {
            return desktop_auth_jwt::consumeRefreshToken($refreshToken);
        } catch (desktop_auth_exception $e) {
            desktop_auth_jwt::revokeSessionOnRefreshReuse($refreshToken, $e);
            throw $e;
        }
    }

    /**
     * Build a browser-login success result from tokens + profile.
     *
     * This is a convenience wrapper around browserSuccessResult() for the
     * controller's login / verifySecondFactor / loginViaOidc flows.
     *
     * @param array $tokens
     * @param array $profile
     * @return desktop_auth_jwt_result
     */
    public static function loginBrowserResult(array $tokens, array $profile): desktop_auth_jwt_result
    {
        return self::browserSuccessResult(
            $tokens,
            $profile,
            desktop_auth_jwt::configuredIdleTimeoutMinutes()
        );
    }
}
