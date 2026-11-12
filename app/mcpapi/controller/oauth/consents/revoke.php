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
 * OAuth 同意撤销（POST /mcp-api/oauth/consents/revoke，browser 会话 + CSRF）。
 *
 * 撤销同意 → 该应用换票需重新同意；存量 refresh token 一并全部吊销；
 * 已发 access 在 TTL 内自然失效（guard 实时权限解析兜底）。
 */
final class mcpapi_ctl_oauth_consents_revoke
{
    private mcpapi_auth_oauth_consents $consents;
    private mcpapi_auth_oauth_refreshtokens $refreshTokens;

    public function __construct(
        ?mcpapi_auth_oauth_consents $consents = null,
        ?mcpapi_auth_oauth_refreshtokens $refreshTokens = null
    ) {
        $this->consents = $consents ?? new mcpapi_auth_oauth_consents();
        $this->refreshTokens = $refreshTokens ?? new mcpapi_auth_oauth_refreshtokens();
    }

    public function create(array $request = []): mcpapi_http_response
    {
        $userId = (int) desktop_auth_context::userId();
        if ($userId < 1) {
            return mcpapi_http_response::fromJson(401, ['error' => 'unauthorized']);
        }

        $clientId = (string) ($request['client_id'] ?? '');
        if ($clientId === '') {
            return mcpapi_http_response::fromJson(400, [
                'error' => 'invalid_request',
                'error_description' => 'client_id is required',
            ]);
        }

        $this->consents->revoke($userId, $clientId);
        $this->refreshTokens->revokeAll($userId, $clientId);

        return mcpapi_http_response::fromJson(200, [
            'ok' => true,
            'client_id' => $clientId,
        ]);
    }
}
