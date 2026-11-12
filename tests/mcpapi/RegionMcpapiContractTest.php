<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class RegionMcpapiContractTest extends TestCase
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

    public function testControllerDelegatesSearchToCapability(): void
    {
        require_once $this->root . '/app/eccommon/lib/capability/region.php';
        require_once $this->root . '/app/mcpapi/controller/v1/region.php';

        $capability = new class extends eccommon_capability_region {
            public array $calls = [];
            public function __construct() {}
            public function search(array $request): array
            {
                $this->calls[] = $request;
                return ['list' => [['region_id' => '1', 'name' => '北京', 'region_grade' => 1]], 'total' => 1];
            }
        };

        $controller = new mcpapi_ctl_v1_region($capability);
        $response = $controller->list($this->context(), ['query' => '北京', 'region_grade' => 1]);

        $this->assertSame(200, $response->status());
        $this->assertSame(['query' => '北京', 'region_grade' => 1], $capability->calls[0]);
        $this->assertSame('1', $response->body()['list'][0]['region_id']);
    }

    public function testControllerMapsEmptyQueryTo422(): void
    {
        require_once $this->root . '/app/eccommon/lib/capability/region.php';
        require_once $this->root . '/app/mcpapi/controller/v1/region.php';

        $capability = new class extends eccommon_capability_region {
            public function __construct() {}
            public function search(array $request): array
            {
                throw new InvalidArgumentException('请填写要查询的地区名称');
            }
        };

        $controller = new mcpapi_ctl_v1_region($capability);
        $response = $controller->list($this->context(), ['query' => '']);

        $this->assertSame(422, $response->status());
        $this->assertSame('validation_failed', $response->body()['error']);
        $this->assertSame('请填写要查询的地区名称', $response->body()['message']);
    }

    private function context(): mcpapi_auth_context
    {
        return new mcpapi_auth_context(
            'request-region',
            'client-region',
            'subject-region',
            'tenant-region',
            'https://oms.example.internal',
            1,
            [1],
            ['regions'],
            ['regions'],
            time() + 3600,
            true
        );
    }
}
