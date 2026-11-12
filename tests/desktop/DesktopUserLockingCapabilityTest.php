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
final class DesktopUserLockingCapabilityTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testCapabilityFileExistsAtAutoloadPath(): void
    {
        $this->assertFileExists($this->root . '/app/desktop/lib/capability/user/locking.php');
    }

    public function testUnlockBeginsAndCommitsTransaction(): void
    {
        require_once $this->root . '/app/desktop/lib/capability/user/locking.php';

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
            public array $updates = [];

            public function update(array $data, array $filter): bool
            {
                $this->updates[] = [$data, $filter];
                return true;
            }
        };

        $capability = new desktop_capability_user_locking($model, $database);
        $result = $capability->unlock(9);

        $this->assertSame(['success' => true], $result);
        $this->assertSame(1, $database->begins);
        $this->assertSame(1, $database->commits);
        $this->assertSame(0, $database->rollbacks);
        $this->assertSame([['is_lock' => '0', 'lock_reason' => ''], ['user_id' => 9]], $model->updates[0]);
    }

    public function testUnlockRejectsEmptyUserId(): void
    {
        require_once $this->root . '/app/desktop/lib/capability/user/locking.php';

        $capability = new desktop_capability_user_locking(new class {
            public function update(array $data, array $filter): bool
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
        $this->expectExceptionMessage('用户ID不能为空');
        $capability->unlock(0);
    }

    public function testUnlockRollsBackWhenUpdateFails(): void
    {
        require_once $this->root . '/app/desktop/lib/capability/user/locking.php';

        $database = new class {
            public int $rollbacks = 0;

            public function beginTransaction(): string
            {
                return 'tx';
            }

            public function commit($tx = null): void
            {
            }

            public function rollBack($tx = null): void
            {
                $this->rollbacks++;
            }
        };

        $model = new class {
            public function update(array $data, array $filter): bool
            {
                return false;
            }
        };

        $capability = new desktop_capability_user_locking($model, $database);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('解锁失败');
        try {
            $capability->unlock(9);
        } finally {
            TestCase::assertSame(1, $database->rollbacks);
        }
    }
}
