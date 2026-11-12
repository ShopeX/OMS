<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

if (!class_exists('app', false)) {
    class app
    {
        public static $desktop;

        public static function get($name)
        {
            return self::$desktop;
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

#[RunTestsInSeparateProcesses]
final class DesktopRolesCapabilityContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testCapabilityFileExistsAtAutoloadPath(): void
    {
        $this->assertFileExists($this->root . '/app/desktop/lib/capability/roles.php');
    }

    public function testCreateBeginsAndCommitsTransaction(): void
    {
        require_once $this->root . '/app/desktop/lib/capability/roles.php';

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

            public function check_gname(string $name)
            {
                return false;
            }

            public function save(array $data): bool
            {
                $this->saved = $data;
                return true;
            }
        };

        $capability = new desktop_capability_roles($model, $database);
        $result = $capability->create([
            'role_name' => '仓库管理员',
            'workground' => ['desktop.users'],
        ]);

        $this->assertSame(['success' => true], $result);
        $this->assertSame(1, $database->begins);
        $this->assertSame(1, $database->commits);
        $this->assertSame(0, $database->rollbacks);
        $this->assertSame([], $model->saved['data_authority']);
    }

    public function testCreateRejectsEmptyRoleName(): void
    {
        require_once $this->root . '/app/desktop/lib/capability/roles.php';

        $capability = new desktop_capability_roles(new class {
            public function check_gname(string $name)
            {
                return false;
            }

            public function save(array $data): bool
            {
                return true;
            }
        }, new class {
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
        });

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('工作组名称不能为空');
        $capability->create(['role_name' => '', 'workground' => ['desktop.users']]);
    }

    public function testUpdateExcludesCurrentRoleFromDuplicateCheck(): void
    {
        require_once $this->root . '/app/desktop/lib/capability/roles.php';

        $model = new class {
            public array $saved = [];

            public function check_gname(string $name)
            {
                return 9;
            }

            public function save(array $data): bool
            {
                $this->saved = $data;
                return true;
            }
        };

        $capability = new desktop_capability_roles($model, new class {
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
        });

        $result = $capability->update(9, [
            'role_id' => 9,
            'role_name' => '仓库管理员',
            'workground' => ['desktop.users'],
        ]);

        $this->assertSame(['success' => true], $result);
        $this->assertSame(9, $model->saved['role_id']);
    }
}
