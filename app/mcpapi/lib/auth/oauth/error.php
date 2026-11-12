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
 * OAuth 协议错误：携带 RFC 6749 §5.2 error code，token 端点统一转 JSON 响应。
 */
final class mcpapi_auth_oauth_error extends RuntimeException
{
    public function __construct(
        private readonly string $errorCode,
        string $message = '',
        private readonly int $status = 400
    ) {
        parent::__construct($message === '' ? $errorCode : $message, $status);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function toResponse(): mcpapi_http_response
    {
        return mcpapi_http_response::fromJson($this->status, [
            'error' => $this->errorCode,
            'error_description' => $this->getMessage(),
        ], ['Cache-Control' => 'no-store', 'Pragma' => 'no-cache']);
    }
}
