<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

#[PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
final class AutodispatchApiContractTest extends TestCase
{
    private string $root;
    private string $controllerSource;
    private string $capabilitySource;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
        $this->controllerSource = (string) file_get_contents($this->root . '/app/mcpapi/controller/v1/autodispatch.php');
        $this->capabilitySource = (string) file_get_contents($this->root . '/app/omeauto/lib/capability/autodispatch.php');

        require_once $this->root . '/app/omeauto/lib/capability/autodispatch.php';
    }

    public function testMcpApiControllerDelegatesAutodispatchMutationsToCapability(): void
    {
        $this->assertStringContainsString('class omeauto_capability_autodispatch', $this->capabilitySource);
        $this->assertStringContainsString('private omeauto_capability_autodispatch $capability;', $this->controllerSource);
        $this->assertStringContainsString('$this->capability->create($request, $this->organizationPermissions($context))', $this->controllerSource);
        $this->assertStringContainsString('$this->capability->setStatus((int) ($request[\'oid\'] ?? 0), (string) ($request[\'status\'] ?? \'\'), $this->organizationPermissions($context))', $this->controllerSource);
        $this->assertStringNotContainsString('private function save(array $data, mcpapi_auth_context $context, string &$error)', $this->controllerSource);
        $this->assertStringNotContainsString('private function setStatus(int $oid, string $status, mcpapi_auth_context $context, string &$error)', $this->controllerSource);
    }

    public function testCapabilityCreateReplacesOrderTypeLinksAndReturnsAffectedIds(): void
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

        $dispatch = new class {
            public array $saved = [];

            public function save(array &$data): void
            {
                $this->saved = $data;
            }

            public function dump(array $conditions, string $fields = '*'): array
            {
                return ['oid' => (int) ($conditions['oid'] ?? 0), 'org_id' => 10, 'config' => []];
            }

            public function update(array $data, array $filter): void
            {
            }

            public function getList(string $fields): array
            {
                return [];
            }
        };

        $capability = new omeauto_capability_autodispatch($dispatch, new class {
            public function dump(array $conditions, string $fields = '*'): array
            {
                return [];
            }

            public function getList(string $fields): array
            {
                return [];
            }
        }, $database);

        $result = $capability->create([
            'oid' => 9,
            'name' => 'Updated',
            'org_id' => 10,
            'config' => ['autoOrders' => [3, 3, 8]],
        ], [10]);

        $this->assertSame([
            'oid' => 9,
            'affected_rule_oids' => [9],
            'affected_order_type_tids' => [3, 8],
        ], $result);
        $this->assertSame([
            'update sdb_omeauto_order_type set did=0 where did=9',
            'update sdb_omeauto_order_type set did=9 where tid=3',
            'update sdb_omeauto_order_type set did=9 where tid=8',
        ], $database->queries);
        $this->assertSame([3, 3, 8], $dispatch->saved['config']['autoOrders']);
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

        $dispatch = new class {
            public function save(array &$data): void
            {
                throw new RuntimeException('save failed');
            }

            public function dump(array $conditions, string $fields = '*'): array
            {
                return ['oid' => (int) ($conditions['oid'] ?? 0), 'org_id' => 10, 'config' => []];
            }

            public function update(array $data, array $filter): void
            {
            }

            public function getList(string $fields): array
            {
                return [];
            }
        };

        $capability = new omeauto_capability_autodispatch($dispatch, new class {
            public function dump(array $conditions, string $fields = '*'): array
            {
                return [];
            }

            public function getList(string $fields): array
            {
                return [];
            }
        }, $database);

        try {
            $capability->create([
                'oid' => 9,
                'name' => 'Updated',
                'org_id' => 10,
                'config' => ['autoOrders' => [3]],
            ], [10]);
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
        $dispatch = new class {
            public function dump(array $conditions, string $fields = '*'): array
            {
                return ['oid' => (int) ($conditions['oid'] ?? 0), 'org_id' => 10, 'config' => []];
            }

            public function update(array $data, array $filter): bool
            {
                return false;
            }

            public function getList(string $fields): array
            {
                return [];
            }
        };

        $capability = new omeauto_capability_autodispatch($dispatch, new class {
            public function getList(string $fields): array
            {
                return [];
            }

            public function dump(array $conditions, string $fields = '*'): array
            {
                return [];
            }
        }, $database);

        try {
            $capability->setDefault(9, [10]);
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $exception) {
            $this->assertSame('规则默认状态保存失败', $exception->getMessage());
        }

        $this->assertSame(1, $database->begins);
        $this->assertSame(0, $database->commits);
        $this->assertSame(1, $database->rollbacks);
    }

    public function testCapabilitySetStatusRejectsInvalidStatus(): void
    {
        $capability = new omeauto_capability_autodispatch(new class {
            public function dump(array $conditions, string $fields = '*'): array
            {
                return ['oid' => (int) ($conditions['oid'] ?? 0), 'org_id' => 10, 'config' => []];
            }

            public function getList(string $fields): array
            {
                return [];
            }
        }, new class {
            public function getList(string $fields): array
            {
                return [];
            }

            public function dump(array $conditions, string $fields = '*'): array
            {
                return [];
            }
        }, new class {
            public function query(string $sql): void
            {
            }
        });

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('状态无效');

        $capability->setStatus(9, 'invalid', [10]);
    }
}
