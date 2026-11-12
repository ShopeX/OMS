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
 * RFC 8414 AS 元数据端点（GET /mcp-api/.well-known/oauth-authorization-server）。
 *
 * 路径含点分段，由 router 特判映射到本控制器（键：well_known_oauth_authorization_server）。
 */
final class mcpapi_ctl_oauth_authorization_server
{
    private mcpapi_auth_oauth_tokens $tokens;
    private mcpapi_auth_oauth_scopes $scopes;

    public function __construct(?mcpapi_auth_oauth_tokens $tokens = null, ?mcpapi_auth_oauth_scopes $scopes = null)
    {
        $this->tokens = $tokens ?? new mcpapi_auth_oauth_tokens();
        $this->scopes = $scopes ?? new mcpapi_auth_oauth_scopes();
    }

    public function index(): mcpapi_http_response
    {
        $base = rtrim((string) kernel::base_url(1), '/') . '/index.php/mcp-api/oauth';

        return mcpapi_http_response::fromJson(200, [
            'issuer' => $this->tokens->issuer(),
            'authorization_endpoint' => $base . '/authorize',
            'token_endpoint' => $base . '/token',
            'registration_endpoint' => rtrim((string) kernel::base_url(1), '/') . '/index.php/mcp-api/oauth/register',
            'jwks_uri' => $base . '/jwks',
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['client_secret_basic', 'client_secret_post', 'none'],
            'scopes_supported' => array_keys(mcpapi_auth_oauth_scopes::SUPPORTED),
        ], ['Cache-Control' => 'public, max-age=300']);
    }

    public function handle(): mcpapi_http_response
    {
        return $this->index();
    }
}
