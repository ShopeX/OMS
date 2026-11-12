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
 * OAuth 同意记录列表（GET /mcp-api/oauth/consents，browser 会话 + CSRF）。
 *
 * 设计 §5「用户撤销入口挂个人设置」：desktop 个人设置通过本端点展示已授权应用，
 * 配合 POST /mcp-api/oauth/consents/revoke 撤销。
 */
final class mcpapi_ctl_oauth_consents
{
    /** @var callable(int $userId): array */
    private $consentLister;
    /** @var callable(string $clientId): ?array */
    private $clientFinder;

    public function __construct(?callable $consentLister = null, ?callable $clientFinder = null)
    {
        $this->consentLister = $consentLister ?? static function (int $userId): array {
            return app::get('mcpapi')->model('oauth_consent')
                ->getList('*', ['user_id' => $userId], 0, -1);
        };
        $this->clientFinder = $clientFinder ?? static function (string $clientId): ?array {
            $rows = app::get('mcpapi')->model('client')->getList('*', ['client_id' => $clientId], 0, 1);
            return is_array($rows[0] ?? null) ? $rows[0] : null;
        };
    }

    public function index(array $request = []): mcpapi_http_response
    {
        unset($request);
        $userId = (int) desktop_auth_context::userId();
        if ($userId < 1) {
            return mcpapi_http_response::fromJson(401, ['error' => 'unauthorized']);
        }

        $consents = [];
        foreach (($this->consentLister)($userId) as $row) {
            if (!is_array($row) || ((int) ($row['revoked_at'] ?? 0) > 0)) {
                continue;
            }
            $clientId = (string) ($row['client_id'] ?? '');
            if ($clientId === '') {
                continue;
            }
            $client = ($this->clientFinder)($clientId);
            $consents[] = [
                'client_id' => $clientId,
                'client_name' => is_array($client) ? (string) ($client['name'] ?? $clientId) : $clientId,
                'client_type' => is_array($client) ? (string) ($client['client_type'] ?? '') : '',
                'scopes' => is_array($row['scopes'] ?? null) ? array_values($row['scopes']) : [],
                'granted_at' => (int) ($row['granted_at'] ?? 0),
            ];
        }

        return mcpapi_http_response::fromJson(200, ['consents' => $consents]);
    }

    public function handle(array $request = []): mcpapi_http_response
    {
        return $this->index($request);
    }
}
