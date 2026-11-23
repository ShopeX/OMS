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

class console_capability_inventory
{
    private object $stockSource;
    private object $branchProductSource;

    public function __construct(?object $stockSource = null, ?object $branchProductSource = null)
    {
        $this->stockSource = $stockSource ?? kernel::single('console_ctl_api_stock');
        $this->branchProductSource = $branchProductSource ?? kernel::single('console_ctl_api_branch_product');
    }

    public function list(array $request): array
    {
        return $this->stockSource->list($request);
    }

    public function getByMaterialWarehouse(int $branchId, int $bmId): ?array
    {
        if ($branchId < 1 || $bmId < 1) {
            throw new InvalidArgumentException('branch_id and bm_id must be positive integers');
        }

        return $this->branchProductSource->getRow($branchId, $bmId);
    }

    public function diffCheck(array $pairs): array
    {
        if ($pairs === []) {
            throw new InvalidArgumentException('pairs is required');
        }

        return $this->branchProductSource->diff($pairs);
    }
}
