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

use app\desktop\lib\annotation\RequirePermission;

final class mcpapi_ctl_v1_brand
{
    private ome_capability_brand $capability;

    public function __construct(?ome_capability_brand $capability = null)
    {
        $this->capability = $capability ?? new ome_capability_brand();
    }

    #[RequirePermission('goods_brand')]
    public function index(mcpapi_auth_context $context): mcpapi_http_response
    {
        return $this->list($context, []);
    }

    #[RequirePermission('goods_brand')]
    public function list(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        unset($context);
        try {
            return $this->jsonList($request);
        } catch (InvalidArgumentException $exception) {
            return mcpapi_http_response::fromJson(422, ['error' => 'validation_failed', 'message' => $exception->getMessage()]);
        }
    }

    #[RequirePermission('goods_brand')]
    public function show(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        unset($context);
        try {
            return $this->jsonDetail((int) ($request['brand_id'] ?? 0));
        } catch (InvalidArgumentException $exception) {
            return mcpapi_http_response::fromJson(422, ['error' => 'validation_failed', 'message' => $exception->getMessage()]);
        }
    }

    #[RequirePermission('goods_brand')]
    public function create(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        unset($context);
        try {
            return mcpapi_http_response::fromJson(200, ['info' => $this->capability->create($request)]);
        } catch (InvalidArgumentException $exception) {
            return mcpapi_http_response::fromJson(422, ['error' => 'validation_failed', 'message' => $exception->getMessage()]);
        }
    }

    #[RequirePermission('goods_brand')]
    public function update(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        unset($context);
        try {
            return mcpapi_http_response::fromJson(200, [
                'info' => $this->capability->update((int) ($request['brand_id'] ?? 0), $request),
            ]);
        } catch (InvalidArgumentException $exception) {
            return mcpapi_http_response::fromJson(422, ['error' => 'validation_failed', 'message' => $exception->getMessage()]);
        } catch (OutOfBoundsException $exception) {
            return mcpapi_http_response::fromJson(404, ['error' => 'not_found', 'message' => $exception->getMessage()]);
        }
    }

    #[RequirePermission('goods_brand')]
    public function delete(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        unset($context);
        try {
            return mcpapi_http_response::fromJson(200, [
                'success' => $this->capability->delete((int) ($request['brand_id'] ?? 0)),
            ]);
        } catch (InvalidArgumentException $exception) {
            return mcpapi_http_response::fromJson(422, ['error' => 'validation_failed', 'message' => $exception->getMessage()]);
        } catch (OutOfBoundsException $exception) {
            return mcpapi_http_response::fromJson(404, ['error' => 'not_found', 'message' => $exception->getMessage()]);
        }
    }

    private function jsonList(array $request): mcpapi_http_response
    {
        return mcpapi_http_response::fromJson(200, $this->capability->list($request));
    }

    private function jsonDetail(int $brandId): mcpapi_http_response
    {
        $info = $this->capability->detail($brandId);
        return $info === null
            ? mcpapi_http_response::fromJson(404, ['error' => 'not_found'])
            : mcpapi_http_response::fromJson(200, ['info' => $info]);
    }
}
