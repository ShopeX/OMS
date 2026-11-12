<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

if (!class_exists('app', false)) {
    class app
    {
        public static $ome;

        public static function get($name)
        {
            return self::$ome;
        }
    }
}

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

#[PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
final class OperationOrgCapabilityContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
        kernel::$database = new class {
            public function beginTransaction(): string
            {
                return 'tx';
            }

            public function commit($tx = null): void
            {
            }

            public function rollBack($tx = null): void
            {
            }
        };
    }

    public function testCapabilityFileExistsAtAutoloadCorrectPath(): void
    {
        $this->assertFileExists($this->root . '/app/ome/lib/capability/operationorg.php');
    }

    public function testCapabilityListsOrganizations(): void
    {
        require_once $this->root . '/app/ome/lib/capability/operationorg.php';

        $model = new class {
            public function getList(string $fields): array
            {
                return [
                    ['org_id' => 1, 'org_name' => '总部', 'parent_id' => 0],
                    ['org_id' => 3, 'org_name' => '华东组织', 'parent_id' => 1],
                ];
            }
        };

        $capability = new ome_capability_operationorg($model, new class {
            public function syncPermissionQueue(): void
            {
            }
        });
        $result = $capability->list();

        $this->assertSame(2, count($result['list']));
        $this->assertSame('总部', $result['list'][0]['org_name']);
        $this->assertSame(3, $result['list'][1]['org_id']);
    }

    public function testCapabilityReturnsOrganizationDetail(): void
    {
        require_once $this->root . '/app/ome/lib/capability/operationorg.php';

        $model = new class {
            public function dump(array $filter, string $fields = '*'): ?array
            {
                if ((int) ($filter['org_id'] ?? 0) !== 3) {
                    return null;
                }

                return ['org_id' => 3, 'org_name' => '华东组织', 'parent_id' => 1];
            }
        };

        $capability = new ome_capability_operationorg($model, new class {
            public function syncPermissionQueue(): void
            {
            }
        });
        $detail = $capability->detail(3);

        $this->assertSame(3, $detail['org_id']);
        $this->assertSame('华东组织', $detail['org_name']);
    }

    public function testCapabilityCreateRejectsDuplicateCode(): void
    {
        require_once $this->root . '/app/ome/lib/capability/operationorg.php';

        $model = new class {
            public function dump(array $filter, string $fields = '*'): ?array
            {
                return ($filter['code'] ?? '') === 'ORG_DUP' ? ['org_id' => 8, 'code' => 'ORG_DUP'] : null;
            }

            public function getList(string $fields): array
            {
                return [];
            }
        };

        $roles = new class {
            public function syncPermissionQueue(): void
            {
            }
        };

        $capability = new ome_capability_operationorg($model, $roles);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('编码已存在!不可以重复添加');
        $capability->create(['code' => 'ORG_DUP', 'name' => '重复组织']);
    }

    public function testCapabilityCreatePersistsAndSyncsPermissionQueue(): void
    {
        require_once $this->root . '/app/ome/lib/capability/operationorg.php';

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
        };

        $model = new class {
            public array $saved = [];

            public function dump(array $filter, string $fields = '*'): ?array
            {
                return null;
            }

            public function save(array $data): bool
            {
                $this->saved = $data;
                return true;
            }

            public function getList(string $fields): array
            {
                return [];
            }
        };

        $roles = new class {
            public int $syncs = 0;

            public function syncPermissionQueue(): void
            {
                $this->syncs++;
            }
        };

        $capability = new ome_capability_operationorg($model, $roles, $database);
        $result = $capability->create(['code' => 'ORG_NEW', 'name' => '新组织', 'parent_id' => 0]);

        $this->assertTrue($result['success']);
        $this->assertSame('ORG_NEW', $model->saved['code']);
        $this->assertSame(1, $roles->syncs);
        $this->assertSame(1, $database->begins);
        $this->assertSame(1, $database->commits);
        $this->assertSame(0, $database->rollbacks);
    }
}
