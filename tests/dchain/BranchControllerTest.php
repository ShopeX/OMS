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

use PHPUnit\Framework\TestCase;

class DchainBranchControllerTest extends TestCase
{
    public function testWarehouseMappingIsInitializedBeforeOptionalPostData(): void
    {
        $source = file_get_contents(__DIR__ . '/../../app/dchain/controller/admin/branch.php');

        $initializationPosition = strpos($source, '$warehouse_mapping = array();');
        $conditionalPosition = strpos($source, "if ($" . "_POST['oms_warehouse'])");

        $this->assertIsInt($initializationPosition, 'warehouse mapping should be initialized');
        $this->assertIsInt($conditionalPosition, 'oms_warehouse conditional should exist');
        $this->assertLessThan(
            $conditionalPosition,
            $initializationPosition,
            'warehouse mapping must be initialized before optional oms_warehouse data is checked'
        );
    }
}
