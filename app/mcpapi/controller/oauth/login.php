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
 * OAuth 独立登录页（GET/POST /mcp-api/oauth/login，public 路由）。
 *
 * 设计：OAuth 授权流不进 OMS 控制台——未登录时渲染一个与同意页同风格的
 * 独立登录表单；登录成功（desktop 会话 cookie 原地种下）后 302 回 authorize，
 * 由 authorize 继续同意页/静默发码，WorkBuddy 在同一浏览器会话里闭环。
 *
 * next 参数只承载 authorize 的 query 参数（白名单键），authorize URL 由本控制器
 * 用 kernel::base_url 重建——不存在开放重定向。
 */
final class mcpapi_ctl_oauth_login
{
    private const AUTHORIZE_KEYS = ['response_type', 'client_id', 'redirect_uri', 'scope', 'state', 'code_challenge', 'code_challenge_method'];

    /** @var callable(string $clientId): ?array */
    private $clientFinder;

    public function __construct(?callable $clientFinder = null)
    {
        $this->clientFinder = $clientFinder ?? static function (string $clientId): ?array {
            $rows = app::get('mcpapi')->model('client')->getList('*', ['client_id' => $clientId], 0, 1);
            return is_array($rows[0] ?? null) ? $rows[0] : null;
        };
    }

    private function clientName(?array $next): string
    {
        $clientId = (string) ($next['client_id'] ?? '');
        if ($clientId === '') {
            return '';
        }
        $client = ($this->clientFinder)($clientId);
        if (!is_array($client)) {
            return $clientId;
        }
        $name = trim((string) ($client['name'] ?? ''));
        return $name !== '' ? $name : $clientId;
    }

    public function index(array $request = []): mcpapi_http_response
    {
        $next = $this->sanitizeNext($request['next'] ?? '');
        if ($next !== null && $this->isAuthenticated()) {
            return mcpapi_http_response::redirect($this->authorizeUrl($next));
        }
        return $this->loginPage($next, '', $this->clientName($next));
    }

    public function create(array $request = []): mcpapi_http_response
    {
        $next = $this->sanitizeNext($request['next'] ?? '');
        if ($next !== null && $this->isAuthenticated()) {
            return mcpapi_http_response::redirect($this->authorizeUrl($next));
        }

        $username = trim((string) ($request['username'] ?? ''));
        $password = (string) ($request['password'] ?? '');
        if ($username === '' || $password === '') {
            return $this->loginPage($next, '请输入用户名和密码', $this->clientName($next), 422);
        }

        $result = desktop_auth_jwt_flow::loginBrowser([
            'username' => $username,
            'password' => $password,
        ]);
        if (!$result->isAuthenticated()) {
            // 需要验证码/二次验证等场景：给出明确提示（本期独立页不支持图形验证码输入时提示走 OMS 登录）
            return $this->loginPage($next, (string) $result->message(), $this->clientName($next), 401);
        }

        // 登录成功：desktop 会话 cookie 已由 transport 通过 setcookie 种下，回授权流程
        return mcpapi_http_response::redirect($this->authorizeUrl($next));
    }

    private function isAuthenticated(): bool
    {
        return class_exists('desktop_auth_context') && desktop_auth_context::isAuthenticated();
    }

    /**
     * next 只允许 authorize 的 query 参数（白名单键 + 值长度约束），URL 由本类重建。
     *
     * @return array<string,string>|null
     */
    private function sanitizeNext($next): ?array
    {
        if (!is_string($next) || $next === '') {
            return null;
        }
        // 表单提交场景 PHP 已解码一次，值仍是整体 rawurlencode 形态（无顶层 = 和 &）；
        // GET 场景 PHP 已解码到含 = 的 query 形态。按形态自适应后再 parse_str。
        $decoded = str_contains($next, '=') ? $next : rawurldecode($next);
        parse_str($decoded, $params);
        if (!is_array($params) || $params === []) {
            return null;
        }
        $clean = [];
        foreach (self::AUTHORIZE_KEYS as $key) {
            if (isset($params[$key]) && is_string($params[$key]) && $params[$key] !== '' && mb_strlen($params[$key]) <= 1024) {
                $clean[$key] = $params[$key];
            }
        }
        if (($clean['client_id'] ?? '') === '' || ($clean['redirect_uri'] ?? '') === '') {
            return null;
        }
        return $clean;
    }

    /**
     * @param array<string,string>|null $params
     */
    private function authorizeUrl(?array $params): string
    {
        if ($params === null) {
            return rtrim((string) kernel::base_url(1), '/') . '/index.php/mcp-api/oauth/authorize';
        }
        return rtrim((string) kernel::base_url(1), '/') . '/index.php/mcp-api/oauth/authorize?' . http_build_query($params);
    }

    private function loginPage(?array $next, string $error, string $clientName = '', int $status = 200): mcpapi_http_response
    {
        $nextValue = $next === null ? '' : rawurlencode(http_build_query($next));
        $errorHtml = $error === '' ? '' : '<p class="error">' . htmlspecialchars($error, ENT_QUOTES) . '</p>';
        $appLine = $clientName === ''
            ? '登录后将继续授权流程，不会进入 OMS 控制台'
            : '应用 <b class="app">' . htmlspecialchars($clientName, ENT_QUOTES) . '</b> 请求访问你的 OMS 账号，登录后继续授权';
        $nextInput = $nextValue === '' ? '' : '<input type="hidden" name="next" value="' . htmlspecialchars($nextValue, ENT_QUOTES) . '">';
        $formTarget = '/index.php/mcp-api/oauth/login';
        $title = '登录 OMS';

        $html = <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$title}</title>
<style>
body{font-family:-apple-system,"PingFang SC","Microsoft YaHei",sans-serif;background:#f5f7fa;margin:0;display:flex;align-items:center;justify-content:center;min-height:100vh}
.card{background:#fff;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.08);max-width:420px;width:92%;padding:32px}
h1{font-size:18px;margin:0 0 6px;color:#1f2d3d}
.sub{font-size:13px;color:#8492a6;margin:0 0 18px}
.error{background:#fef0f0;color:#f56c6c;border:1px solid #fde2e2;border-radius:6px;padding:8px 10px;font-size:13px;margin:0 0 14px}
.app{color:#1f2d3d;font-size:14px}
label{display:block;font-size:13px;color:#5e6d82;margin:0 0 6px}
input[type=text],input[type=password]{width:100%;box-sizing:border-box;padding:10px;border:1px solid #dcdfe6;border-radius:6px;font-size:14px;margin:0 0 14px}
button{width:100%;padding:10px 0;border-radius:6px;border:1px solid #2d8cf0;font-size:14px;cursor:pointer;background:#2d8cf0;color:#fff}
button:hover{opacity:.88}
.note{font-size:12px;color:#909399;margin:16px 0 0}
</style>
</head>
<body>
<div class="card">
<h1>OMS MCP 授权登录</h1>
<p class="sub">{$appLine}</p>
{$errorHtml}
<form method="post" action="{$formTarget}">
{$nextInput}
<label for="username">用户名</label>
<input type="text" id="username" name="username" autocomplete="username" required>
<label for="password">密码</label>
<input type="password" id="password" name="password" autocomplete="current-password" required>
<button type="submit">登录并继续</button>
</form>
<p class="note">登录凭据仅用于建立本次授权会话。</p>
</div>
</body>
</html>
HTML;

        return mcpapi_http_response::fromHtml($status, $html, ['Cache-Control' => 'no-store']);
    }
}
