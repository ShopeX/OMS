<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MaterialCatMcpapiContractTest extends TestCase
{
    private string $cwdBeforeTest;
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cwdBeforeTest = getcwd();
        $this->root = dirname(__DIR__, 2);
        chdir($this->root);
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

    protected function tearDown(): void
    {
        chdir($this->cwdBeforeTest);
        parent::tearDown();
    }

    public function testControllerFileExistsAtAutoloadCorrectPath(): void
    {
        $this->assertFileExists($this->root . '/app/mcpapi/controller/v1/material/cat.php');
    }

    public function testControllerDelegatesTreeReadToCapability(): void
    {
        require_once $this->root . '/app/material/lib/capability/cat.php';
        require_once $this->root . '/app/mcpapi/controller/v1/material/cat.php';

        $capability = new class extends material_capability_cat {
            public function __construct() {}
            public function tree(): array
            {
                return [['cat_id' => 1, 'cat_name' => 'Root']];
            }
        };

        $controller = new mcpapi_ctl_v1_material_cat($capability);
        $response = $controller->list($this->context(), []);

        $this->assertSame(200, $response->status());
        $this->assertSame('Root', $response->body()['rows'][0]['cat_name']);
    }

    public function testControllerDoesNotDependOnRequestBodyActionMultiplexing(): void
    {
        $source = (string) file_get_contents($this->root . '/app/mcpapi/controller/v1/material/cat.php');

        $this->assertStringNotContainsString("['action']", $source);
        $this->assertStringContainsString('public function list(', $source);
        $this->assertStringContainsString('public function show(', $source);
    }

    public function testControllerDelegatesCreateDeleteAndSortToCapability(): void
    {
        require_once $this->root . '/app/material/lib/capability/cat.php';
        require_once $this->root . '/app/mcpapi/controller/v1/material/cat.php';

        $capability = new class extends material_capability_cat {
            public array $calls = [];
            public function __construct() {}
            public function create(array $request): array
            {
                $this->calls[] = ['create', $request];
                return ['cat_id' => 9];
            }
            public function delete(int $catId): bool
            {
                $this->calls[] = ['delete', $catId];
                return true;
            }
            public function sort(array $orders): int
            {
                $this->calls[] = ['sort', $orders];
                return 2;
            }
            public function tree(): array { return []; }
            public function detail(int $catId): ?array { return null; }
        };

        $controller = new mcpapi_ctl_v1_material_cat($capability);
        $create = $controller->create($this->context(), ['cat_name' => '新分类']);
        $delete = $controller->delete($this->context(), ['cat_id' => 9]);
        $sort = $controller->sort($this->context(), ['p_order' => ['3' => '1', '8' => '']]);

        $this->assertSame(200, $create->status());
        $this->assertSame(200, $delete->status());
        $this->assertSame(200, $sort->status());
        $this->assertSame(['create', ['cat_name' => '新分类']], $capability->calls[0]);
        $this->assertSame(['delete', 9], $capability->calls[1]);
        $this->assertSame(['sort', ['3' => '1', '8' => '']], $capability->calls[2]);
    }

    private function context(): mcpapi_auth_context
    {
        return new mcpapi_auth_context(
            'request-material-cat',
            'client-material-cat',
            'subject-material-cat',
            'tenant-material-cat',
            'https://oms.example.internal',
            1,
            [1],
            ['material_cat'],
            ['material_cat'],
            time() + 3600,
            true
        );
    }
}
