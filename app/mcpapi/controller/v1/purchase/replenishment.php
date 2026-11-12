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

final class mcpapi_ctl_v1_purchase_replenishment
{
    private object $capability;

    public function __construct(?object $capability = null)
    {
        $this->capability = $capability ?? new console_capability_replenishment();
    }

    #[RequirePermission('console_purchase_po')]
    public function recommend(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        try {
            return mcpapi_http_response::fromJson(200, $this->capability->recommend($request));
        } catch (InvalidArgumentException $e) {
            return mcpapi_http_response::fromJson(422, [
                'error' => 'validation_failed',
                'message' => $e->getMessage(),
            ]);
        }
    }
}
