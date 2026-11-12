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

if (!class_exists('desktop_finder_builder_prototype', false)) {
    class desktop_finder_builder_prototype
    {
        public object $controller;
        public object $app;
        public object $object;
        public array $dbschema = [];
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

if (!class_exists('app', false)) {
    class app
    {
        public static function get(string $name): object
        {
            return new class {
                public function _(string $message): string
                {
                    return $message;
                }
            };
        }
    }
}

#[RunTestsInSeparateProcesses]
final class FinderBuilderDoRecycleContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
        $_POST = [];
        $_GET = [];
    }

    protected function tearDown(): void
    {
        $_POST = [];
        $_GET = [];
    }

    public function testLegacyDoRecycleRejectsSelectAllWithoutViewFilter(): void
    {
        require_once $this->root . '/app/desktop/lib/finder/builder/dorecycle.php';

        kernel::$recycle = new class {
            public array $captured = [];

            public function dorecycle(string $objectName, ?array $filter): bool
            {
                $this->captured[] = [$objectName, $filter];
                return true;
            }
        };

        $controller = new class {
            public array $endCalls = [];

            public function begin(): void
            {
            }

            public function end(bool $success, string $message, ?string $redirect = null): void
            {
                $this->endCalls[] = [$success, $message, $redirect];
            }
        };

        $builder = new desktop_finder_builder_dorecycle();
        $builder->controller = $controller;
        $builder->app = new class {
            public function model(string $name): object
            {
                return new class {
                    public bool $filter_use_like = false;
                    public ?string $recycle_msg = null;
                };
            }
        };
        $builder->object = new class {
            public function table_name(): string
            {
                return 'users';
            }

            public function get_schema(): array
            {
                return ['idColumn' => 'user_id'];
            }
        };

        $_POST['isSelectedAll'] = '_ALL_';
        $_POST['user_id'] = [];
        $_GET['view'] = '';

        $builder->main();

        $this->assertSame([
            [false, '请选择筛选条件后再执行全部删除', null],
        ], $controller->endCalls);
        $this->assertSame([], kernel::$recycle->captured);
    }
}
