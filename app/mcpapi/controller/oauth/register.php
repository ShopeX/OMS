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
 * RFC 7591 动态客户端注册端点（POST /mcp-api/oauth/register）。
 */
final class mcpapi_ctl_oauth_register
{
    private mcpapi_auth_oauth_registration $registration;

    public function __construct(?mcpapi_auth_oauth_registration $registration = null)
    {
        $this->registration = $registration ?? new mcpapi_auth_oauth_registration();
    }

    public function create(array $request = []): mcpapi_http_response
    {
        try {
            $body = $this->process($request);
        } catch (mcpapi_auth_oauth_error $exception) {
            return $exception->toResponse();
        }
        return mcpapi_http_response::fromJson(201, $body, ['Cache-Control' => 'no-store', 'Pragma' => 'no-cache']);
    }

    private function process(array $request): array
    {
        $request = $request === [] && class_exists('desktop_auth_request') ? desktop_auth_request::jsonBody() : $request;

        $redirectUris = $request['redirect_uris'] ?? null;
        if (!is_array($redirectUris)) {
            throw new mcpapi_auth_oauth_error('invalid_redirect_uri', 'redirect_uris array is required');
        }

        $clientName = '';
        if (isset($request['client_name']) && is_string($request['client_name'])) {
            $clientName = mb_substr(trim($request['client_name']), 0, 255);
        }

        $requestedScopes = null;
        if (isset($request['scope']) && is_string($request['scope']) && trim($request['scope']) !== '') {
            $requestedScopes = (new mcpapi_auth_oauth_scopes())->parse($request['scope']);
        }

        return $this->registration->register(
            $clientName,
            $redirectUris,
            $requestedScopes,
            (string) ($_SERVER['REMOTE_ADDR'] ?? '')
        );
    }
}
