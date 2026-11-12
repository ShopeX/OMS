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

final class mcpapi_ctl_v1_internal_exchange
{
    private mcpapi_auth_exchange $exchange;

    public function __construct(?mcpapi_auth_exchange $exchange = null)
    {
        $this->exchange = $exchange ?? new mcpapi_auth_exchange(
            new mcpapi_auth_client_database(),
            new mcpapi_auth_token_issuer()
        );
    }

    public function handle(mcpapi_gateway_identity $identity, array $request = []): mcpapi_http_response
    {
        if ($request !== []) {
            throw new mcpapi_auth_exception();
        }
        return mcpapi_http_response::fromJson(200, [
            'access_token' => $this->exchange->issue($identity),
            'token_type' => 'Bearer',
        ], ['Cache-Control' => 'no-store']);
    }
}
