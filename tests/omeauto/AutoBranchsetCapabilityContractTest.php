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
final class AutoBranchsetCapabilityContractTest extends TestCase
{
    private string $capabilitySource;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 2);
        $this->capabilitySource = (string) file_get_contents($root . '/app/omeauto/lib/capability/autobranchset.php');

        require_once $root . '/app/omeauto/lib/capability/order/type.php';
        require_once $root . '/app/omeauto/lib/capability/autobranchset.php';
    }

    public function testCapabilityUsesSharedConditionEncodingAndSupportsBranchRules(): void
    {
        $this->assertStringContainsString('normalizeGroupConditions', $this->capabilitySource);
        $this->assertStringContainsString("'branch_rules'", $this->capabilitySource);
        $this->assertStringNotContainsString("in_array(\$role, ['address'], true)", $this->capabilitySource);
    }

    public function testCapabilitySavePersistsCombinedConditionsAndBranchRules(): void
    {
        $database = new class {
            public array $execs = [];
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

            public function exec(string $sql): void
            {
                $this->execs[] = $sql;
            }

            public function select(string $sql): array
            {
                return [];
            }
        };

        $orderType = new class {
            public ?array $saved = null;

            public function save(array &$data): bool
            {
                if (!isset($data['tid']) || $data['tid'] === null) {
                    $data['tid'] = 77;
                }
                $this->saved = $data;
                return true;
            }

            public function dump(array $conditions, string $fields = '*'): array
            {
                return ['tid' => (int) ($conditions['tid'] ?? 77), 'name' => 'warehouse-rule', 'org_id' => 1, 'group_type' => 'branch'];
            }

            public function update(array $data, array $filter): bool
            {
                return true;
            }

            public function getList(string $fields): array
            {
                return [];
            }
        };

        $autobranch = new class {
            public array $saved = [];

            public function save(array &$data): bool
            {
                $this->saved[] = $data;
                return true;
            }
        };

        $autobranchget = new class {
            public array $saved = [];

            public function insert(array &$data): bool
            {
                $this->saved[] = $data;
                return true;
            }
        };

        $encoder = new class {
            public function normalizeGroupConditions(mixed $conditions, int $orgId = 0): array
            {
                $encoded = [];
                foreach ((array) $conditions as $condition) {
                    $encoded[] = json_encode([
                        'role' => (string) ($condition['role'] ?? ''),
                        'caption' => 'ok',
                        'content' => $condition['content'] ?? null,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
                return $encoded;
            }
        };

        $capability = new omeauto_capability_autobranchset(
            $orderType,
            new class {
                public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1, string $order = ''): array
                {
                    return [['branch_id' => '2', 'is_ctrl_store' => 'false']];
                }
            },
            $database,
            $autobranch,
            new class {},
            $autobranchget,
            $encoder
        );

        $result = $capability->save([
            'name' => '17-condition warehouse rule',
            'org_id' => 1,
            'conditions' => [
                ['role' => 'shop', 'content' => ['shop-1']],
                ['role' => 'money', 'content' => ['min' => 100, 'max' => 500, 'type' => 3]],
                ['role' => 'address', 'content' => ['1']],
            ],
            'branch_bindings' => [
                ['branch_id' => 2, 'weight' => 5, 'is_default' => true],
            ],
            'branch_rules' => [
                ['classify' => 'router', 'weight' => 10],
                ['classify' => 'cost', 'weight' => 5],
            ],
        ]);

        $this->assertSame(77, $result['tid']);
        $this->assertCount(3, $orderType->saved['config']);
        $this->assertSame(1, $database->begins);
        $this->assertSame(1, $database->commits);
        $this->assertSame(0, $database->rollbacks);
        $this->assertCount(1, $autobranch->saved);
        $this->assertSame(2, $autobranch->saved[0]['bid']);
        $this->assertCount(2, $autobranchget->saved);
        $this->assertSame('router', $autobranchget->saved[0]['classify']);
        $this->assertSame(10, $autobranchget->saved[0]['weight']);
        $this->assertSame('cost', $autobranchget->saved[1]['classify']);
        $this->assertSame(5, $autobranchget->saved[1]['weight']);
        $this->assertCount(2, $database->execs);
        $this->assertStringContainsString('DELETE FROM sdb_omeauto_autobranchget WHERE tid=77', $database->execs[1]);
    }

    public function testCapabilityRejectsUnknownBranchRuleClassify(): void
    {
        $capability = new omeauto_capability_autobranchset(
            new class {
                public function save(array &$data): bool { return true; }
                public function dump(array $conditions, string $fields = '*'): array { return []; }
                public function update(array $data, array $filter): bool { return true; }
            },
            new class {},
            new class {
                public function beginTransaction(): string { return 'tx'; }
                public function commit($tx = null): void {}
                public function rollBack($tx = null): void {}
                public function exec(string $sql): void {}
                public function select(string $sql): array { return []; }
            },
            new class {
                public function save(array &$data): bool { return true; }
            },
            new class {},
            new class {
                public function insert(array &$data): bool { return true; }
            },
            new class {
                public function normalizeGroupConditions(mixed $conditions, int $orgId = 0): array { return []; }
            }
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('暂不支持该仓库选择策略');

        $capability->save([
            'name' => 'invalid branch rule',
            'branch_rules' => [['classify' => 'unknown', 'weight' => 1]],
        ]);
    }
}
