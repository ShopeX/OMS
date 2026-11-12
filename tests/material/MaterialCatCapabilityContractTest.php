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
        public static $material;

        public static function get($name)
        {
            return self::$material;
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

if (!class_exists('utils', false)) {
    class utils
    {
        public static function safe_input(string $value): string
        {
            return $value;
        }
    }
}

#[PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
final class MaterialCatCapabilityContractTest extends TestCase
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
        $this->assertFileExists($this->root . '/app/material/lib/capability/cat.php');
    }

    public function testCapabilityReturnsNormalizedTreeRows(): void
    {
        require_once $this->root . '/app/material/lib/capability/cat.php';

        $model = new class {
            public function getTree(): array
            {
                return [
                    ['id' => 1, 'pid' => 0, 'text' => 'Root', 'code' => 'R', 'p_order' => 1, 'cat_path' => ',', 'is_leaf' => false, 'child_count' => 2],
                    ['id' => 3, 'pid' => 1, 'text' => 'Leaf', 'code' => 'L', 'p_order' => 2, 'cat_path' => ',1,', 'is_leaf' => true, 'child_count' => 0],
                ];
            }
        };

        $capability = new material_capability_cat($model);
        $tree = $capability->tree();

        $this->assertSame(1, $tree[0]['cat_id']);
        $this->assertSame('Root', $tree[0]['cat_name']);
        $this->assertSame(2, $tree[1]['step']);
        $this->assertSame('Leaf', $tree[1]['cat_name']);
    }

    public function testCapabilityDetailReturnsNormalizedRow(): void
    {
        require_once $this->root . '/app/material/lib/capability/cat.php';

        $model = new class {
            public function dump(array $filter, string $fields = '*'): ?array
            {
                if ((int) ($filter['cat_id'] ?? 0) !== 3) {
                    return null;
                }

                return [
                    'cat_id' => 3,
                    'parent_id' => 1,
                    'cat_name' => 'Leaf',
                    'cat_code' => 'L',
                    'p_order' => 2,
                    'cat_path' => ',1,',
                    'child_count' => 0,
                ];
            }
        };

        $capability = new material_capability_cat($model);
        $detail = $capability->detail(3);

        $this->assertSame(3, $detail['cat_id']);
        $this->assertSame('Leaf', $detail['cat_name']);
    }

    public function testCapabilityCreateValidatesDuplicateNameAndPersistsData(): void
    {
        require_once $this->root . '/app/material/lib/capability/cat.php';

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

            public function dump($filter, string $fields = '*'): ?array
            {
                if (is_int($filter)) {
                    return null;
                }
                if (($filter['cat_name'] ?? null) === '重复分类') {
                    return ['cat_id' => 7];
                }
                return null;
            }

            public function save(array &$data): bool
            {
                $data['cat_id'] = $data['cat_id'] ?? 9;
                $this->saved = $data;
                return true;
            }
        };

        $capability = new material_capability_cat($model, $database);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('分类名称不能重复');
        $capability->create(['cat_name' => '重复分类', 'parent_id' => 0]);
    }

    public function testCapabilityCreateStartsAndCommitsTransaction(): void
    {
        require_once $this->root . '/app/material/lib/capability/cat.php';

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
            public function dump($filter, string $fields = '*'): ?array
            {
                return null;
            }

            public function save(array &$data): bool
            {
                $data['cat_id'] = 9;
                return true;
            }
        };

        $capability = new material_capability_cat($model, $database);
        $result = $capability->create(['cat_name' => '新分类', 'parent_id' => 0]);

        $this->assertSame(9, $result['cat_id']);
        $this->assertSame(1, $database->begins);
        $this->assertSame(1, $database->commits);
        $this->assertSame(0, $database->rollbacks);
    }

    public function testCapabilityDeleteDelegatesToRemoveContract(): void
    {
        require_once $this->root . '/app/material/lib/capability/cat.php';

        $message = '';
        $model = new class($message) {
            public array $removed = [];

            public function __construct(private string &$message)
            {
            }

            public function dump($filter, string $fields = '*'): ?array
            {
                return ['cat_id' => 3, 'cat_name' => 'Leaf'];
            }

            public function toRemove(int $catId, string &$msg): bool
            {
                $this->removed[] = $catId;
                $msg = '';
                return true;
            }
        };

        $capability = new material_capability_cat($model);
        $this->assertTrue($capability->delete(3));
        $this->assertSame([3], $model->removed);
    }

    public function testCapabilitySortUpdatesEveryProvidedOrder(): void
    {
        require_once $this->root . '/app/material/lib/capability/cat.php';

        $model = new class {
            public array $updates = [];

            public function update(array $data, array $filter): bool
            {
                $this->updates[] = [$data, $filter];
                return true;
            }

            public function cat2json(): bool
            {
                return true;
            }
        };

        $capability = new material_capability_cat($model);
        $count = $capability->sort(['3' => '1', '8' => '']);

        $this->assertSame(2, $count);
        $this->assertSame([
            [['p_order' => 1], ['cat_id' => 3]],
            [['p_order' => null], ['cat_id' => 8]],
        ], $model->updates);
    }
}
