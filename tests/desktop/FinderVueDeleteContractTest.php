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

if (!class_exists('desktop_finder_vue_context', false)) {
    class desktop_finder_vue_context
    {
        public function __construct(
            private object $model,
            private string $objectName,
            private array $builtFilters = []
        ) {
        }

        public function getModel(): object
        {
            return $this->model;
        }

        public function buildFilterConditions(array $filters): array
        {
            return $this->builtFilters ?: $filters;
        }

        public function getObjectName(): string
        {
            return $this->objectName;
        }
    }
}

if (!class_exists('kernel', false)) {
    class kernel
    {
        public static object $recycle;

        public static function single(string $name): object
        {
            if ($name !== 'desktop_system_recycle') {
                throw new RuntimeException('Unexpected service lookup: ' . $name);
            }

            return self::$recycle;
        }
    }
}

#[RunTestsInSeparateProcesses]
final class FinderVueDeleteContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
        $_POST = [];
    }

    protected function tearDown(): void
    {
        $_POST = [];
    }

    public function testDeleteHandlerAcceptsLegacyPrimaryKeyArraysFromPost(): void
    {
        require_once $this->root . '/app/desktop/lib/finder/vue/delete.php';

        $captured = [];
        kernel::$recycle = new class($captured) {
            public array $captured = [];

            public function __construct(array $captured)
            {
                $this->captured = $captured;
            }

            public function dorecycle(string $objectName, ?array $filter): bool
            {
                $this->captured[] = [$objectName, $filter];
                return true;
            }
        };

        $_POST['user_id'] = ['5', '7'];

        $handler = new desktop_finder_vue_delete(new desktop_finder_vue_context(
            (object) ['idColumn' => 'user_id'],
            'desktop_mdl_users'
        ));

        $result = $handler->handle([]);

        $this->assertSame(['success' => true, 'message' => '删除成功'], $result);
        $this->assertSame([
            ['desktop_mdl_users', ['user_id' => ['5', '7']]],
        ], kernel::$recycle->captured);
    }

    public function testDeleteHandlerAcceptsJsonIdsPayload(): void
    {
        require_once $this->root . '/app/desktop/lib/finder/vue/delete.php';

        kernel::$recycle = new class {
            public array $captured = [];

            public function dorecycle(string $objectName, ?array $filter): bool
            {
                $this->captured[] = [$objectName, $filter];
                return true;
            }
        };

        $handler = new desktop_finder_vue_delete(new desktop_finder_vue_context(
            (object) ['idColumn' => 'user_id'],
            'desktop_mdl_users'
        ));

        $result = $handler->handle(['ids' => [9, 11]]);

        $this->assertSame(['success' => true, 'message' => '删除成功'], $result);
        $this->assertSame([
            ['desktop_mdl_users', ['user_id' => [9, 11]]],
        ], kernel::$recycle->captured);
    }

    public function testDeleteHandlerUsesAllSelectionModeFilters(): void
    {
        require_once $this->root . '/app/desktop/lib/finder/vue/delete.php';

        kernel::$recycle = new class {
            public array $captured = [];

            public function dorecycle(string $objectName, ?array $filter): bool
            {
                $this->captured[] = [$objectName, $filter];
                return true;
            }
        };

        $handler = new desktop_finder_vue_delete(new desktop_finder_vue_context(
            (object) ['idColumn' => 'user_id'],
            'desktop_mdl_users',
            ['status' => '1']
        ));

        $result = $handler->handle([
            'selection_mode' => '_ALL_',
            'filters' => ['status' => '1'],
        ]);

        $this->assertSame(['success' => true, 'message' => '删除成功'], $result);
        $this->assertSame([
            ['desktop_mdl_users', ['status' => '1']],
        ], kernel::$recycle->captured);
    }

    public function testDeleteHandlerRejectsAllSelectionWithoutEffectiveFilters(): void
    {
        require_once $this->root . '/app/desktop/lib/finder/vue/delete.php';

        kernel::$recycle = new class {
            public array $captured = [];

            public function dorecycle(string $objectName, ?array $filter): bool
            {
                $this->captured[] = [$objectName, $filter];
                return true;
            }
        };

        $handler = new desktop_finder_vue_delete(new desktop_finder_vue_context(
            (object) ['idColumn' => 'user_id'],
            'desktop_mdl_users'
        ));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('请选择筛选条件后再执行全部删除');

        try {
            $handler->handle([
                'selection_mode' => '_ALL_',
                'filters' => [],
            ]);
        } finally {
            $this->assertSame([], kernel::$recycle->captured);
        }
    }

    public function testDeleteAllModeEnablesLikeMatchingOnModelDuringRecycle(): void
    {
        require_once $this->root . '/app/desktop/lib/finder/vue/delete.php';

        $model = new class {
            public string $idColumn = 'user_id';
            public bool $filter_use_like = false;
        };

        kernel::$recycle = new class($model) {
            public object $model;

            /** @var bool[] 调用 dorecycle 时模型上的 filter_use_like 取值 */
            public array $seenFlags = [];

            public function __construct(object $model)
            {
                $this->model = $model;
            }

            public function dorecycle(string $objectName, ?array $filter): bool
            {
                $this->seenFlags[] = $this->model->filter_use_like;
                return true;
            }
        };

        $handler = new desktop_finder_vue_delete(new desktop_finder_vue_context(
            $model,
            'desktop_mdl_users',
            ['batchno' => 'E2E7775']
        ));

        $result = $handler->handle([
            'selection_mode' => '_ALL_',
            'filters' => ['batchno' => 'E2E7775'],
        ]);

        $this->assertSame(['success' => true, 'message' => '删除成功'], $result);
        // 与列表(app/desktop/lib/finder/vue/list.php)同一匹配语义：删除期间必须开启 LIKE，
        // 否则同一批 filters 退化为等值匹配 -> 「提示删除成功但静默漏删」
        $this->assertSame([true], kernel::$recycle->seenFlags);
        // 用完复位，避免污染同一请求内的后续查询
        $this->assertFalse($model->filter_use_like);
    }

    public function testDeleteByIdsKeepsExactMatching(): void
    {
        require_once $this->root . '/app/desktop/lib/finder/vue/delete.php';

        $model = new class {
            public string $idColumn = 'user_id';
            public bool $filter_use_like = false;
        };

        kernel::$recycle = new class($model) {
            public object $model;

            /** @var bool[] 调用 dorecycle 时模型上的 filter_use_like 取值 */
            public array $seenFlags = [];

            public function __construct(object $model)
            {
                $this->model = $model;
            }

            public function dorecycle(string $objectName, ?array $filter): bool
            {
                $this->seenFlags[] = $this->model->filter_use_like;
                return true;
            }
        };

        $handler = new desktop_finder_vue_delete(new desktop_finder_vue_context(
            $model,
            'desktop_mdl_users'
        ));

        $result = $handler->handle(['ids' => [9, 11]]);

        $this->assertSame(['success' => true, 'message' => '删除成功'], $result);
        // 勾选删除走主键数组精确匹配，不得开启 LIKE
        $this->assertSame([false], kernel::$recycle->seenFlags);
        $this->assertFalse($model->filter_use_like);
    }

    /**
     * 分片删除一片：取「当前剩余集的前 L 条主键」→ 只删这一片 → 回 {chunkUpdated,nextOffset,done}
     * （与 app/material/controller/api/material/basic.php::batchVisible 同一约定，供前端进度条驱动）
     */
    public function testChunkedDeleteAllRemovesOneChunkAndReportsProgress(): void
    {
        require_once $this->root . '/app/desktop/lib/finder/vue/delete.php';

        $model = new class {
            public string $idColumn = 'user_id';
            public bool $filter_use_like = false;
            public array $getListCalls = [];

            public function getList($cols, $filter = null, $start = 0, $limit = 20, $order = null): array
            {
                $this->getListCalls[] = [$cols, $filter, $start, $limit, $order, $this->filter_use_like];
                return [['user_id' => 5], ['user_id' => 7]];
            }
        };

        kernel::$recycle = new class {
            public array $captured = [];

            public function dorecycle(string $objectName, ?array $filter): bool
            {
                $this->captured[] = [$objectName, $filter];
                return true;
            }
        };

        $handler = new desktop_finder_vue_delete(new desktop_finder_vue_context(
            $model,
            'desktop_mdl_users',
            ['status' => '1']
        ));

        $result = $handler->handle([
            'selection_mode' => '_ALL_',
            'filters' => ['status' => '1'],
            'chunk_offset' => 0,
            'chunk_size' => 200,
        ]);

        // 只取主键列、取剩余集前 L 条、按主键升序，且查询期间必须带 LIKE（与列表同语义）
        $this->assertSame([['user_id', ['status' => '1'], 0, 200, 'user_id asc', true]], $model->getListCalls);
        // 只删本片主键 —— 不能退化成「按 filters 整表删」
        $this->assertSame([['desktop_mdl_users', ['user_id' => [5, 7]]]], kernel::$recycle->captured);
        $this->assertSame([
            'success' => true,
            'message' => '删除成功',
            'chunkUpdated' => 2,
            'nextOffset' => 2,
            'done' => true,
        ], $result);
        $this->assertFalse($model->filter_use_like, '分片删除用完必须复位 filter_use_like');
    }

    public function testChunkedDeleteAllKeepsGoingWhenChunkIsFull(): void
    {
        require_once $this->root . '/app/desktop/lib/finder/vue/delete.php';

        $rows = [];
        for ($i = 1; $i <= 200; $i++) {
            $rows[] = ['user_id' => $i];
        }

        $model = new class($rows) {
            public string $idColumn = 'user_id';
            public bool $filter_use_like = false;

            public function __construct(private array $rows)
            {
            }

            public function getList($cols, $filter = null, $start = 0, $limit = 20, $order = null): array
            {
                return array_slice($this->rows, $start, $limit);
            }
        };

        $captured = new class {
            public int $calls = 0;
            public array $lastFilter = [];

            public function dorecycle(string $objectName, ?array $filter): bool
            {
                $this->calls++;
                $this->lastFilter = $filter;
                return true;
            }
        };
        kernel::$recycle = $captured;

        $handler = new desktop_finder_vue_delete(new desktop_finder_vue_context(
            $model,
            'desktop_mdl_users',
            ['status' => '1']
        ));

        $result = $handler->handle([
            'selection_mode' => '_ALL_',
            'filters' => ['status' => '1'],
            'chunk_offset' => 200,
            'chunk_size' => 200,
        ]);

        $this->assertSame(1, $captured->calls);
        $this->assertCount(200, $captured->lastFilter['user_id']);
        $this->assertSame(200, $result['chunkUpdated']);
        $this->assertSame(400, $result['nextOffset'], 'nextOffset 应累加游标（进度条口径）');
        $this->assertFalse($result['done'], '取满一片 ⇒ 还有剩余，前端应继续下一片');
    }

    public function testChunkedDeleteAllIsDoneWhenNothingMatches(): void
    {
        require_once $this->root . '/app/desktop/lib/finder/vue/delete.php';

        $model = new class {
            public string $idColumn = 'user_id';
            public bool $filter_use_like = false;

            public function getList($cols, $filter = null, $start = 0, $limit = 20, $order = null): array
            {
                return [];
            }
        };

        $captured = new class {
            public int $calls = 0;

            public function dorecycle(string $objectName, ?array $filter): bool
            {
                $this->calls++;
                return true;
            }
        };
        kernel::$recycle = $captured;

        $handler = new desktop_finder_vue_delete(new desktop_finder_vue_context($model, 'desktop_mdl_users'));

        $result = $handler->handle([
            'selection_mode' => '_ALL_',
            'filters' => ['status' => '1'],
            'chunk_offset' => 400,
            'chunk_size' => 200,
        ]);

        $this->assertSame(0, $captured->calls, '无命中行时不得调用删除');
        $this->assertSame([
            'success' => true,
            'message' => '删除成功',
            'chunkUpdated' => 0,
            'nextOffset' => 400,
            'done' => true,
        ], $result);
    }
}
