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

final class mcpapi_http_response
{
    private int $status;
    private array $body;
    private string $json;
    private string $html;
    private array $headers;

    private function __construct(int $status, array $body, array $headers = [], string $html = '')
    {
        $this->status = $status;
        $this->body = $body;
        $this->json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $this->html = $html;
        $this->headers = array_merge(
            $html === '' ? ['Content-Type' => 'application/json; charset=utf-8'] : [],
            $headers
        );
    }

    public static function fromJson(int $status, array $body, array $headers = []): self
    {
        return new self($status, $body, $headers);
    }

    public static function fromHtml(int $status, string $html, array $headers = []): self
    {
        return new self($status, [], $headers, $html);
    }

    public static function redirect(string $location): self
    {
        return new self(302, [], ['Location' => $location], '');
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): array
    {
        return $this->body;
    }

    public function json(): string
    {
        return $this->json;
    }

    public function html(): string
    {
        return $this->html;
    }

    public function isHtml(): bool
    {
        return $this->html !== '';
    }

    public function headers(): array
    {
        return $this->headers;
    }
}
