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

final class MigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        chdir(__DIR__ . '/../..');
        if (!defined('ROOT_DIR')) {
            define('ROOT_DIR', getcwd());
        }
        if (!defined('APP_DIR')) {
            define('APP_DIR', ROOT_DIR . '/app');
        }
        include_once 'config/config.php';
        include_once 'app/base/defined.php';
        include_once 'app/base/kernel.php';
        kernel::register_autoload();
    }

    public function testLegacyClientsAreBackfilledOrDisabledSafely(): void
    {
        $migration = new mcpapi_migration_owner_backfill();
        $result = $migration->transform([
            ['client_id' => 'mapped', 'owner_user_id' => 0, 'oms_subject_id' => 42, 'enabled' => 1],
            ['client_id' => 'unmapped', 'owner_user_id' => 0, 'oms_subject_id' => 999, 'enabled' => 1],
        ], static fn(int $userId): bool => $userId === 42);

        $this->assertSame(42, $result[0]['owner_user_id']);
        $this->assertSame(1, $result[0]['enabled']);
        $this->assertSame(0, $result[1]['owner_user_id']);
        $this->assertSame(0, $result[1]['enabled']);
        $this->assertSame('owner_unmapped_migration', $result[1]['revoked_reason']);
    }

    public function testMigrationScriptDocumentsOwnerBackfillAndFailClosedBehavior(): void
    {
        $script = file_get_contents('app/mcpapi/script/update/1.0.1.php');

        $this->assertIsString($script);
        $this->assertStringContainsString('owner_user_id', $script);
        $this->assertStringContainsString('mcpapi_migration_owner_backfill', $script);
        $this->assertStringContainsString('transform', $script);
        $this->assertSame('owner_unmapped_migration', mcpapi_migration_owner_backfill::UNMAPPED_REASON);
    }
}
