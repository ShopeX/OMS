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
 * JWKS 端点（GET /mcp-api/oauth/jwks）：发布 AS 验签公钥。
 */
final class mcpapi_ctl_oauth_jwks
{
    private mcpapi_auth_oauth_askeys $keys;

    public function __construct(?mcpapi_auth_oauth_askeys $keys = null)
    {
        $this->keys = $keys ?? new mcpapi_auth_oauth_askeys();
    }

    public function index(): mcpapi_http_response
    {
        return mcpapi_http_response::fromJson(200, $this->keys->jwks(), ['Cache-Control' => 'public, max-age=300']);
    }

    public function handle(): mcpapi_http_response
    {
        return $this->index();
    }
}
