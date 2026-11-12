<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

if (!class_exists('kernel', false)) {
    class kernel
    {
        public static $database;

        public static function database()
        {
            return self::$database;
        }
    }
}

if (!class_exists('app', false)) {
    class app
    {
        public static $omeauto;

        public static function get($name)
        {
            return self::$omeauto;
        }
    }
}

#[PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
final class AutoconfirmSaveContractTest extends TestCase
{
    private string $legacySource;
    private string $controllerSource;
    private string $capabilitySource;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 2);
        $this->legacySource = (string) file_get_contents($root . '/app/omeauto/controller/autoconfirm.php');
        $this->controllerSource = (string) file_get_contents($root . '/app/mcpapi/controller/v1/autoconfirm.php');
        $this->capabilitySource = (string) file_get_contents($root . '/app/omeauto/lib/capability/autoconfirm.php');

        require_once $root . '/app/omeauto/lib/capability/autoconfirm.php';
    }

    public function testMcpApiControllerDelegatesToCapabilityInsteadOfOwningSaveContract(): void
    {
        $this->assertStringContainsString("app::get('omeauto')->model('autoconfirm')->save", $this->legacySource);
        $this->assertStringContainsString('class omeauto_capability_autoconfirm', $this->capabilitySource);
        $this->assertStringContainsString('private omeauto_capability_autoconfirm $capability;', $this->controllerSource);
        $this->assertStringContainsString('$this->capability->create($request, $this->organizationPermissions($context))', $this->controllerSource);
        $this->assertStringContainsString('$this->capability->setStatus((int) ($request[\'oid\'] ?? 0), (string) ($request[\'status\'] ?? \'\'), $this->organizationPermissions($context))', $this->controllerSource);
        $this->assertStringNotContainsString('private function save(array &$data, string &$error)', $this->controllerSource);
        $this->assertStringNotContainsString('private function formatConfirmData(array &$data, string &$error): bool', $this->controllerSource);
    }

    public function testCapabilityRejectsAnEffectiveTimeRangeShorterThanOneHour(): void
    {
        $capability = new omeauto_capability_autoconfirm(new class {
            public function save(array &$data): void
            {
            }
        }, new class {
            public function getList(string $fields): array
            {
                return [];
            }
        }, new class {
            public function query(string $sql): void
            {
            }
        });

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('(生效时间范围)时间范围不能小于1个小时');

        $capability->create([
            'confirmStartTime' => '2026-07-17',
            'confirmEndTime' => '2026-07-17',
            '_DTIME_' => [
                'H' => ['confirmStartTime' => '09', 'confirmEndTime' => '09'],
                'M' => ['confirmStartTime' => '00', 'confirmEndTime' => '30'],
            ],
            'config' => ['autoOrders' => ['3']],
        ], null);
    }

    public function testCapabilityCreateReplacesOrderTypeLinksAndReturnsTheirTids(): void
    {
        $database = new class {
            public array $queries = [];
            public int $begins = 0;
            public int $commits = 0;
            public int $rollbacks = 0;

            public function beginTransaction(): string
            {
                $this->begins++;
                return 'tx';
            }

            public function commit($tx = null): void
            {
                $this->commits++;
            }

            public function rollBack($tx = null): void
            {
                $this->rollbacks++;
            }

            public function query(string $sql): void
            {
                $this->queries[] = $sql;
            }
        };
        $model = new class {
            public array $saved = [];

            public function save(array &$data): void
            {
                if (empty($data['oid'])) {
                    $data['oid'] = 42;
                }
                $this->saved = $data;
            }

            public function dump(array $conditions, string $fields = '*'): ?array
            {
                return ['oid' => (int) ($conditions['oid'] ?? 0), 'config' => []];
            }

            public function update(array $data, array $filter): void
            {
            }
        };
        $capability = new omeauto_capability_autoconfirm($model, new class {
            public function getList(string $fields): array
            {
                return [];
            }
        }, $database);

        $result = $capability->create([
            'oid' => 9,
            'confirmStartTime' => '2026-07-17',
            'confirmEndTime' => '2026-07-17',
            '_DTIME_' => [
                'H' => ['confirmStartTime' => '09', 'confirmEndTime' => '11'],
                'M' => ['confirmStartTime' => '00', 'confirmEndTime' => '00'],
            ],
            'config' => ['autoOrders' => ['3', '3', '8']],
        ], null);

        $this->assertSame(['oid' => 9, 'linked_order_type_tids' => [3, 8]], $result);
        $this->assertSame([
            'update sdb_omeauto_order_type set oid=0 where oid=9',
            'update sdb_omeauto_order_type set oid=9 where tid=3',
            'update sdb_omeauto_order_type set oid=9 where tid=8',
        ], $database->queries);
        $this->assertSame(9, $model->saved['oid']);
        $this->assertSame(['3', '3', '8'], $model->saved['config']['autoOrders']);
        $this->assertSame(1, $database->begins);
        $this->assertSame(1, $database->commits);
        $this->assertSame(0, $database->rollbacks);
    }

    public function testCapabilityCreateRollsBackWhenSaveFailsAfterResettingLinks(): void
    {
        $database = new class {
            public int $begins = 0;
            public int $commits = 0;
            public int $rollbacks = 0;

            public function beginTransaction(): string
            {
                $this->begins++;
                return 'tx';
            }

            public function commit($tx = null): void
            {
                $this->commits++;
            }

            public function rollBack($tx = null): void
            {
                $this->rollbacks++;
            }

            public function query(string $sql): void
            {
            }
        };
        $model = new class {
            public function save(array &$data): void
            {
                throw new RuntimeException('save failed');
            }

            public function dump(array $conditions, string $fields = '*'): ?array
            {
                return ['oid' => (int) ($conditions['oid'] ?? 0), 'config' => []];
            }

            public function update(array $data, array $filter): void
            {
            }
        };

        $capability = new omeauto_capability_autoconfirm($model, new class {
            public function getList(string $fields): array
            {
                return [];
            }
        }, $database);

        try {
            $capability->create([
                'oid' => 9,
                'confirmStartTime' => '2026-07-17',
                'confirmEndTime' => '2026-07-17',
                '_DTIME_' => [
                    'H' => ['confirmStartTime' => '09', 'confirmEndTime' => '11'],
                    'M' => ['confirmStartTime' => '00', 'confirmEndTime' => '00'],
                ],
                'config' => ['autoOrders' => ['3']],
            ], null);
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $exception) {
            $this->assertSame('save failed', $exception->getMessage());
        }

        $this->assertSame(1, $database->begins);
        $this->assertSame(0, $database->commits);
        $this->assertSame(1, $database->rollbacks);
    }

    public function testCapabilitySetDefaultRollsBackWhenRuleUpdateReturnsFalse(): void
    {
        $database = new class {
            public int $begins = 0;
            public int $commits = 0;
            public int $rollbacks = 0;

            public function beginTransaction(): string
            {
                $this->begins++;
                return 'tx';
            }

            public function commit($tx = null): void
            {
                $this->commits++;
            }

            public function rollBack($tx = null): void
            {
                $this->rollbacks++;
            }

            public function query(string $sql): bool
            {
                return true;
            }
        };
        $model = new class {
            public function dump(array $conditions, string $fields = '*'): ?array
            {
                return ['oid' => (int) ($conditions['oid'] ?? 0), 'config' => []];
            }

            public function update(array $data, array $filter): bool
            {
                return false;
            }
        };

        $capability = new omeauto_capability_autoconfirm($model, new class {
            public function getList(string $fields): array
            {
                return [];
            }
        }, $database);

        try {
            $capability->setDefault(9, null);
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $exception) {
            $this->assertSame('规则默认状态保存失败', $exception->getMessage());
        }

        $this->assertSame(1, $database->begins);
        $this->assertSame(0, $database->commits);
        $this->assertSame(1, $database->rollbacks);
    }
}
