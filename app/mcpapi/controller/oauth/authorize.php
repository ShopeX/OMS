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

declare(strict_types=1);

/**
 * OAuth 2.1 authorize 端点。
 *
 * GET  /mcp-api/oauth/authorize — 校验请求参数；未登录 → 302 独立 OAuth 登录页（登录后原地回跳）；
 *                                已登录未同意 → 返回同意页 HTML；已同意 → 直接签发授权码 302 回跳
 * POST /mcp-api/oauth/authorize — 同意（approve）→ 签发授权码 302 回跳；拒绝（deny）→ 302 error=access_denied
 *
 * 路由层把该控制器标记为 browser 认证（desktop_auth_context），CSRF 由
 * front controller 的 requiresBrowserCsrf 机制覆盖（POST 需 X-CSRF-Token）。
 */
final class mcpapi_ctl_oauth_authorize
{
    private $clientFinder;
    private $pendingClientFinder;
    private mcpapi_auth_oauth_scopes $scopes;
    private mcpapi_auth_oauth_consents $consents;
    private mcpapi_auth_oauth_codes $codes;
    private mcpapi_auth_owner $owner;

    public function __construct(
        ?callable $clientFinder = null,
        ?mcpapi_auth_oauth_scopes $scopes = null,
        ?mcpapi_auth_oauth_consents $consents = null,
        ?mcpapi_auth_oauth_codes $codes = null,
        ?mcpapi_auth_owner $owner = null,
        ?callable $pendingClientFinder = null
    ) {
        $this->clientFinder = $clientFinder ?? static function (string $clientId): ?array {
            $rows = app::get('mcpapi')->model('client')->getList('*', ['client_id' => $clientId], 0, 1);
            $row = $rows[0] ?? null;
            if (!is_array($row)) {
                return null;
            }
            $enabled = $row['enabled'] ?? false;
            $active = in_array($enabled, [true, 1, '1', 'true'], true)
                && ($row['revoked_at'] === null || (int) $row['revoked_at'] === 0)
                && ($row['expires_at'] === null || (int) $row['expires_at'] > time());
            return $active ? $row : null;
        };
        $this->scopes = $scopes ?? new mcpapi_auth_oauth_scopes();
        $this->consents = $consents ?? new mcpapi_auth_oauth_consents();
        $this->codes = $codes ?? new mcpapi_auth_oauth_codes();
        $this->owner = $owner ?? new mcpapi_auth_owner();
        $this->pendingClientFinder = $pendingClientFinder ?? self::defaultPendingFinder();
    }

    /**
     * 待审批 client 识别：enabled=false 且未吊销（区别于管理员吊销）。
     */
    private static function defaultPendingFinder(): callable
    {
        return static function (string $clientId): ?array {
            if ($clientId === '') {
                return null;
            }
            try {
                $rows = app::get('mcpapi')->model('client')->getList('*', ['client_id' => $clientId], 0, 1);
                $row = is_array($rows[0] ?? null) ? $rows[0] : null;
                if ($row === null) {
                    return null;
                }
                $enabled = in_array($row['enabled'] ?? null, [true, 1, '1', 'true'], true);
                $revoked = (int) ($row['revoked_at'] ?? 0) > 0;
                return (!$enabled && !$revoked) ? $row : null;
            } catch (Throwable $e) {
                return null;
            }
        };
    }

    public function index(array $request = []): mcpapi_http_response
    {
        $request = $this->mergedRequest($request);
        $validated = $this->validateAuthorizeRequest($request);
        if ($validated instanceof mcpapi_http_response) {
            return $validated;
        }
        [$client, $clientId, $redirectUri, $state, $scopes, $codeChallenge, $challengeMethod] = $validated;

        // 浏览器会话：desktop_auth_context 由路由层 browser 认证填充
        if (!class_exists('desktop_auth_context') || !desktop_auth_context::isAuthenticated()) {
            return $this->redirectToLogin($request);
        }
        $userId = desktop_auth_context::userId();

        [$ownerAuth, $notEligible] = $this->resolveOwnerContext($userId);
        if ($notEligible !== null) {
            return $notEligible;
        }
        // 服务账号不能作为 OAuth 授权人（给人点同意）
        if (($ownerAuth['account_type'] ?? 'human') === 'service') {
            return mcpapi_http_response::fromJson(403, [
                'error' => 'access_denied',
                'error_description' => 'Service accounts cannot perform OAuth authorization',
            ]);
        }
        [$scopes, $scopeError] = $ownerAuth['is_super']
            ? [array_keys(mcpapi_auth_oauth_scopes::SUPPORTED), null]
            : $this->resolveGrantedScopes($userId, $client, $scopes);
        if ($scopeError !== null) {
            return $scopeError;
        }

        if (!($this->clientFinder)($clientId)) {
            // 待审批：记录申请人（登录后即知身份），渲染待审批提示页
            if (is_array(($this->pendingClientFinder)($clientId))) {
                $this->recordApplicant($clientId, $userId);
                return $this->pendingPage();
            }
            return mcpapi_http_response::fromJson(400, ['error' => 'invalid_client', 'error_description' => 'Unknown or inactive client']);
        }
        if ($this->consents->hasValidConsent($userId, $clientId, $scopes)) {
            return $this->issueCodeRedirect($clientId, $userId, $redirectUri, $state, $scopes, $codeChallenge, $challengeMethod);
        }

        return $this->consentPage($client, $redirectUri, $state, $scopes, $codeChallenge, $challengeMethod);
    }

    public function create(array $request = []): mcpapi_http_response
    {
        $request = $this->mergedRequest($request);
        $action = (string) ($request['action'] ?? '');
        $validated = $this->validateAuthorizeRequest($request);
        if ($validated instanceof mcpapi_http_response) {
            return $validated;
        }
        [$client, $clientId, $redirectUri, $state, $scopes, $codeChallenge, $challengeMethod] = $validated;

        if (!class_exists('desktop_auth_context') || !desktop_auth_context::isAuthenticated()) {
            return $this->redirectToLogin($request);
        }
        $userId = desktop_auth_context::userId();

        [$ownerAuth, $notEligible] = $this->resolveOwnerContext($userId);
        if ($notEligible !== null) {
            return $notEligible;
        }
        // 服务账号不能作为 OAuth 授权人（给人点同意）
        if (($ownerAuth['account_type'] ?? 'human') === 'service') {
            return mcpapi_http_response::fromJson(403, [
                'error' => 'access_denied',
                'error_description' => 'Service accounts cannot perform OAuth authorization',
            ]);
        }
        [$scopes, $scopeError] = $ownerAuth['is_super']
            ? [array_keys(mcpapi_auth_oauth_scopes::SUPPORTED), null]
            : $this->resolveGrantedScopes($userId, $client, $scopes);
        if ($scopeError !== null) {
            return $scopeError;
        }

        if (!($this->clientFinder)($clientId)) {
            if (is_array(($this->pendingClientFinder)($clientId))) {
                $this->recordApplicant($clientId, $userId);
                return $this->pendingPage();
            }
            return mcpapi_http_response::fromJson(400, ['error' => 'invalid_client', 'error_description' => 'Unknown or inactive client']);
        }
        if ($action === 'deny') {
            return $this->errorRedirect($redirectUri, 'access_denied', 'The user denied the authorization request', $state);
        }

        $this->consents->grant($userId, $clientId, $scopes);
        return $this->issueCodeRedirect($clientId, $userId, $redirectUri, $state, $scopes, $codeChallenge, $challengeMethod);
    }

    /**
     * @return array{0:array,1:string,2:string,3:string,4:string[],5:?string,6:?string}|mcpapi_http_response
     */
    private function validateAuthorizeRequest(array $request)
    {
        $responseType = (string) ($request['response_type'] ?? '');
        if ($responseType !== 'code') {
            return mcpapi_http_response::fromJson(400, ['error' => 'unsupported_response_type']);
        }

        $clientId = (string) ($request['client_id'] ?? '');
        $redirectUri = (string) ($request['redirect_uri'] ?? '');
        if ($clientId === '' || $redirectUri === '') {
            return mcpapi_http_response::fromJson(400, ['error' => 'invalid_request']);
        }

        // client 查找：活跃（clientFinder）或待审批（pendingClientFinder）均通过参数校验；
        // 待审批的最终拦截在登录之后（便于记录申请人并给出友好页面）
        $client = ($this->clientFinder)($clientId) ?? ($this->pendingClientFinder)($clientId);
        if (!is_array($client)) {
            // client 未知：不得重定向（RFC 6749 §4.1.2.1）
            return mcpapi_http_response::fromJson(400, ['error' => 'invalid_client', 'error_description' => 'Unknown or inactive client']);
        }

        $registered = [];
        if (is_array($client['redirect_uris'] ?? null)) {
            $registered = $client['redirect_uris'];
        } elseif (is_string($client['redirect_uris'] ?? null) && $client['redirect_uris'] !== '') {
            $decoded = @unserialize($client['redirect_uris'], ['allowed_classes' => false]);
            $registered = is_array($decoded) ? $decoded : [];
        }
        // redirect_uri 归一化比对：query 参数经 PHP 解码后中文/特殊字符为明文形态，
        // 而注册时 JSON body 保留 percent-encoded 形态，需双向归一后精确匹配（RFC 3986 §6.2.2）
        $registeredMatch = [];
        foreach ($registered as $uri) {
            $registeredMatch[] = (string) $uri;
            $decoded = rawurldecode((string) $uri);
            if ($decoded !== (string) $uri) {
                $registeredMatch[] = $decoded;
            }
        }
        $redirectUriVariants = array_unique([$redirectUri, rawurldecode($redirectUri)]);
        if (!array_intersect($redirectUriVariants, $registeredMatch)) {
            return mcpapi_http_response::fromJson(400, ['error' => 'invalid_request', 'error_description' => 'redirect_uri is not registered']);
        }

        // OAuth 2.1：公共客户端强制 PKCE
        $clientType = (string) ($client['client_type'] ?? 'confidential');
        $codeChallenge = isset($request['code_challenge']) ? (string) $request['code_challenge'] : null;
        $challengeMethod = isset($request['code_challenge_method']) ? (string) $request['code_challenge_method'] : null;
        if ($clientType === 'public' && ($codeChallenge === null || $codeChallenge === '')) {
            return mcpapi_http_response::fromJson(400, ['error' => 'invalid_request', 'error_description' => 'PKCE code_challenge is required for public clients']);
        }
        if ($codeChallenge !== null && $codeChallenge !== '' && !in_array($challengeMethod ?? '', ['S256'], true)) {
            return mcpapi_http_response::fromJson(400, ['error' => 'invalid_request', 'error_description' => 'Only S256 code_challenge_method is supported']);
        }

        $scopes = $this->scopes->sanitize(
            $this->scopes->parse(isset($request['scope']) ? (string) $request['scope'] : null)
        );
        if ($scopes === []) {
            $scopes = $this->scopes->defaults();
        }

        $state = (string) ($request['state'] ?? '');
        return [$client, $clientId, $redirectUri, $state, $scopes, $codeChallenge, $challengeMethod];
    }

    /**
     * @param string[] $scopes
     */
    private function issueCodeRedirect(
        string $clientId,
        int $userId,
        string $redirectUri,
        string $state,
        array $scopes,
        ?string $codeChallenge,
        ?string $challengeMethod
    ): mcpapi_http_response {
        $issued = $this->codes->issue($clientId, $userId, $redirectUri, $scopes, $codeChallenge, $challengeMethod);
        return $this->codeRedirect($redirectUri, $issued['code'], $state);
    }

    private function codeRedirect(string $redirectUri, string $code, string $state): mcpapi_http_response
    {
        $separator = str_contains($redirectUri, '?') ? '&' : '?';
        $target = $redirectUri . $separator . 'code=' . rawurlencode($code);
        if ($state !== '') {
            $target .= '&state=' . rawurlencode($state);
        }
        return $this->schemeAwareRedirect($target, '授权成功', '已将授权码返回应用，请切换到应用继续使用。');
    }

    private function errorRedirect(string $redirectUri, string $error, string $description, string $state): mcpapi_http_response
    {
        $separator = str_contains($redirectUri, '?') ? '&' : '?';
        $target = $redirectUri . $separator . 'error=' . rawurlencode($error)
            . '&error_description=' . rawurlencode($description);
        if ($state !== '') {
            $target .= '&state=' . rawurlencode($state);
        }
        return $this->schemeAwareRedirect($target, '已拒绝授权', '你已拒绝本次授权请求，可关闭此页面。');
    }

    /**
     * scheme 感知回跳：http(s) 直接 302（页面自然跳转）；
     * 私有 scheme（workbuddy:// 等）302 无法改变当前页面，渲染结果页并由 JS 触发 scheme。
     */
    private function schemeAwareRedirect(string $target, string $title, string $message): mcpapi_http_response
    {
        if (preg_match('#^https?://#i', $target) === 1) {
            return mcpapi_http_response::redirect($target);
        }
        return $this->callbackResultPage($target, $title, $message);
    }

    private function callbackResultPage(string $target, string $title, string $message): mcpapi_http_response
    {
        $safeTarget = htmlspecialchars($target, ENT_QUOTES);
        $jsTarget = addslashes($target);
        $safeTitle = htmlspecialchars($title, ENT_QUOTES);
        $safeMessage = htmlspecialchars($message, ENT_QUOTES);

        $html = <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$safeTitle}</title>
<style>
body{font-family:-apple-system,"PingFang SC","Microsoft YaHei",sans-serif;background:#f5f7fa;margin:0;display:flex;align-items:center;justify-content:center;min-height:100vh}
.card{background:#fff;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.08);max-width:460px;width:92%;padding:32px;text-align:center}
h1{font-size:18px;margin:0 0 10px;color:#1f2d3d}
.ok{width:52px;height:52px;border-radius:50%;background:#f0f9eb;color:#67c23a;font-size:28px;line-height:52px;margin:0 auto 14px}
p{font-size:14px;color:#5e6d82;margin:0 0 8px}
a.btn{display:inline-block;margin-top:14px;padding:10px 28px;border-radius:6px;background:#2d8cf0;color:#fff;text-decoration:none;font-size:14px}
.hint{font-size:12px;color:#909399;margin-top:14px}
</style>
</head>
<body>
<div class="card">
<div class="ok">✓</div>
<h1>{$safeTitle}</h1>
<p>{$safeMessage}</p>
<a class="btn" href="{$safeTarget}">打开应用</a>
<p class="hint">如浏览器未自动跳转，请点击上方按钮；完成后即可关闭此页面。</p>
</div>
<script>setTimeout(function(){ location.replace("{$jsTarget}"); }, 300);</script>
</body>
</html>
HTML;

        return mcpapi_http_response::fromHtml(200, $html, ['Cache-Control' => 'no-store']);
    }

    private function redirectToLogin(array $request): mcpapi_http_response
    {
        // SPA 授权登录页（iframeui /oauth-login，复用登录组件全能力：密码/验证码/Okta SSO）。
        // next 编码与 SPA decodeNext 约定一致：btoa(encodeURIComponent(url))。
        // 服务端渲染的 /mcp-api/oauth/login 保留为无 JS 降级通道。
        $current = $this->currentAuthorizeUrl($request);
        $next = base64_encode(rawurlencode($current));
        $loginUrl = '/oauth-login?next=' . rawurlencode($next);
        return mcpapi_http_response::redirect($loginUrl);
    }

    private function currentAuthorizeUrl(array $request): string
    {
        $query = http_build_query($this->authorizeParams($request));
        $base = rtrim((string) kernel::base_url(1), '/');
        return $base . '/index.php/mcp-api/oauth/authorize' . ($query !== '' ? '?' . $query : '');
    }

    private function authorizeParams(array $request): array
    {
        $params = [];
        foreach (['response_type', 'client_id', 'redirect_uri', 'scope', 'state', 'code_challenge', 'code_challenge_method'] as $key) {
            if (isset($request[$key]) && is_string($request[$key]) && $request[$key] !== '') {
                $params[$key] = $request[$key];
            }
        }
        return $params;
    }

    /**
     * 设计 EARS：用户禁用或 mcp_enabled 关闭时，拒绝为其签发任何新 token。
     */
    /**
     * 实时解析用户角色上下文；禁用/mcp_enabled 关闭返回错误响应。
     *
     * @return array{0:array|null,1:mcpapi_http_response|null} [ownerAuth, errorResponse]
     */
    private function resolveOwnerContext(int $userId): array
    {
        try {
            return [$this->owner->resolve($userId), null];
        } catch (mcpapi_auth_exception $e) {
            return [null, mcpapi_http_response::fromJson(403, [
                'error' => 'access_denied',
                'error_description' => 'Account is disabled or MCP access is turned off for this user',
            ])];
        }
    }

    /**
     * 记录待审批应用的申请人（登录用户），供管理员审批时参考
     */
    private function recordApplicant(string $clientId, int $userId): void
    {
        $loginName = class_exists('desktop_auth_context') ? (string) desktop_auth_context::loginName() : '';
        $applicant = $loginName !== '' ? $loginName : ('user#' . $userId);
        try {
            app::get('mcpapi')->model('client')->update(
                ['applied_by' => $applicant, 'updated_at' => time()],
                ['client_id' => $clientId]
            );
        } catch (Throwable $e) {
            // 记录失败不影响主流程
        }
    }

    /**
     * 待审批提示页（浏览器友好，替代裸 JSON 403）
     */
    private function pendingPage(): mcpapi_http_response
    {
        $html = <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>应用等待审批</title>
<style>
body{font-family:-apple-system,"PingFang SC","Microsoft YaHei",sans-serif;background:#f5f7fa;margin:0;display:flex;align-items:center;justify-content:center;min-height:100vh}
.card{background:#fff;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.08);max-width:460px;width:92%;padding:32px;text-align:center}
h1{font-size:18px;margin:0 0 10px;color:#1f2d3d}
p{font-size:14px;color:#5e6d82;margin:0 0 8px}
.hint{font-size:12px;color:#909399;margin-top:14px}
</style>
</head>
<body>
<div class="card">
<h1>应用等待审批</h1>
<p>该应用正在等待管理员审批。</p>
<p>审批通过后，请回到应用重新发起连接即可继续授权。</p>
<p class="hint">如有疑问，请联系系统管理员。</p>
</div>
</body>
</html>
HTML;

        return mcpapi_http_response::fromHtml(403, $html, ['Cache-Control' => 'no-store']);
    }

    /**
     * 换票关 scope 收敛（角色感知）：
     * - 超管：权限即全部 → 授予全部 SUPPORTED scope（同意页展示完整清单）
     * - 非超管：scope ≤ 应用能力上限（超出 → invalid_scope）；
     *   实际权限面在每次 API 调用时由 guard 按「角色权限 ∩ scope 展开权限点」实时收敛（跟着角色走）
     *
     * @param string[] $scopes
     * @param array $client
     * @return array{0:string[],1:mcpapi_http_response|null} [scopes, errorResponse]
     */
    private function resolveGrantedScopes(int $userId, array $client, array $scopes): array
    {
        [$ownerAuth, $errorResponse] = $this->resolveOwnerContext($userId);
        if ($errorResponse !== null) {
            return [[], $errorResponse];
        }
        if ($ownerAuth['is_super']) {
            return [array_keys(mcpapi_auth_oauth_scopes::SUPPORTED), null];
        }
        $clientScopes = is_array($client['scopes'] ?? null)
            ? array_values(array_filter($client['scopes'], 'is_string'))
            : [];
        if ($clientScopes !== []) {
            $allowed = array_fill_keys($clientScopes, true);
            foreach ($scopes as $scope) {
                if (!isset($allowed[$scope])) {
                    return [[], mcpapi_http_response::fromJson(400, [
                        'error' => 'invalid_scope',
                        'error_description' => 'Requested scope exceeds the client registered capability',
                    ])];
                }
            }
        }
        return [$scopes, null];
    }

    /**
     * @param string[] $scopes
     */
    /**
     * @param array $client client 表行（含 client_id / name）
     */
    private function consentPage(
        array $client,
        string $redirectUri,
        string $state,
        array $scopes,
        ?string $codeChallenge,
        ?string $challengeMethod
    ): mcpapi_http_response {
        $hidden = [
            'response_type' => 'code',
            'client_id' => (string) ($client['client_id'] ?? ''),
            'redirect_uri' => $redirectUri,
            'scope' => implode(' ', $scopes),
        ];
        if ($state !== '') {
            $hidden['state'] = $state;
        }
        if ($codeChallenge !== null && $codeChallenge !== '') {
            $hidden['code_challenge'] = $codeChallenge;
            $hidden['code_challenge_method'] = $challengeMethod ?? 'S256';
        }
        // 同意页为服务端渲染表单，无法携带 X-CSRF-Token header；
        // 嵌入当前 CSRF cookie 值，由 front controller 映射为 header 走 desktop_csrf 双提交校验。
        // cookie 缺失（过期/未发，如非 api_jwt 登录路径）时现场补种，避免同意提交必 403
        $csrfToken = (string) ($_COOKIE['oms-csrf-token'] ?? '');
        if ($csrfToken === '') {
            try {
                $csrfToken = kernel::single('desktop_csrf', app::get('desktop'))->set_token();
            } catch (Throwable $e) {
                $csrfToken = '';
            }
        }
        if ($csrfToken !== '') {
            $hidden['_csrf'] = $csrfToken;
        }
        $formTarget = '/index.php/mcp-api/oauth/authorize';

        $rows = '';
        foreach ($this->scopes->describe($scopes) as $entry) {
            $rows .= '<tr><td class="scope">' . htmlspecialchars($entry['scope'], ENT_QUOTES) . '</td>'
                . '<td>' . htmlspecialchars($entry['description'], ENT_QUOTES) . '</td></tr>';
        }
        $hiddenInputs = '';
        foreach ($hidden as $name => $value) {
            $hiddenInputs .= '<input type="hidden" name="' . htmlspecialchars((string) $name, ENT_QUOTES)
                . '" value="' . htmlspecialchars((string) $value, ENT_QUOTES) . '">';
        }

        $loginName = class_exists('desktop_auth_context') ? desktop_auth_context::loginName() : '';
        $clientName = trim((string) ($client['name'] ?? ''));
        if ($clientName === '') {
            $clientName = (string) ($client['client_id'] ?? '');
        }
        $appName = htmlspecialchars($clientName, ENT_QUOTES);
        $clientIdHtml = htmlspecialchars((string) ($client['client_id'] ?? ''), ENT_QUOTES);
        $title = '授权确认';

        $html = <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$title}</title>
<style>
body{font-family:-apple-system,"PingFang SC","Microsoft YaHei",sans-serif;background:#f5f7fa;margin:0;display:flex;align-items:center;justify-content:center;min-height:100vh}
.card{background:#fff;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.08);max-width:520px;width:92%;padding:32px}
h1{font-size:18px;margin:0 0 6px;color:#1f2d3d}
.sub{font-size:13px;color:#8492a6;margin:0 0 18px}
.who{font-size:14px;color:#1f2d3d;margin:0 0 16px}
.who b{color:#2d8cf0}
.app{color:#1f2d3d;font-size:15px}
code{font-family:ui-monospace,monospace;color:#8492a6;font-size:11px;word-break:break-all}
table{width:100%;border-collapse:collapse;margin:0 0 22px;font-size:13px}
td{padding:8px 10px;border-bottom:1px solid #ebeef5;color:#5e6d82}
td.scope{font-family:ui-monospace,monospace;color:#2d8cf0;white-space:nowrap}
.actions{display:flex;gap:12px}
button{flex:1;padding:10px 0;border-radius:6px;border:1px solid #dcdfe6;font-size:14px;cursor:pointer;background:#fff;color:#606266}
button.primary{background:#2d8cf0;border-color:#2d8cf0;color:#fff}
button:hover{opacity:.85}
.note{font-size:12px;color:#909399;margin:16px 0 0}
</style>
</head>
<body>
<div class="card">
<h1>OMS MCP 授权请求</h1>
<p class="sub">应用 <b class="app">{$appName}</b> 请求以你的身份访问 OMS，请确认授权范围</p>
<p class="who">当前用户：<b>{$loginName}</b></p>
<table>{$rows}</table>
<form method="post" action="{$formTarget}">
{$hiddenInputs}
<div class="actions">
<button type="submit" name="action" value="approve" class="primary">同意授权</button>
<button type="submit" name="action" value="deny">拒绝</button>
</div>
</form>
<p class="note">授权后，该应用将以你的身份访问上述 OMS 业务能力；权限随你的账号角色实时收敛，可随时在系统内撤销。<br>应用标识：<code>{$clientIdHtml}</code></p>
</div>
</body>
</html>
HTML;

        return mcpapi_http_response::fromHtml(200, $html, ['Cache-Control' => 'no-store']);
    }

    /**
     * front controller 解析 JSON body；authorize 也接受 form 表单/GET query。
     */
    private function mergedRequest(array $request): array
    {
        if ($request === [] && class_exists('desktop_auth_request')) {
            $request = desktop_auth_request::jsonBody();
        }
        if (is_array($_GET ?? null) && $_GET !== []) {
            foreach ($_GET as $key => $value) {
                if (is_string($value) && !array_key_exists($key, $request)) {
                    $request[$key] = $value;
                }
            }
        }
        if (is_array($_POST ?? null) && $_POST !== []) {
            foreach ($_POST as $key => $value) {
                if (is_string($value)) {
                    $request[$key] = $value;
                }
            }
        }
        return $request;
    }
}
