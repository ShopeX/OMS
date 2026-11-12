<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ShipmentInterceptMcpapiContractTest extends TestCase
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

    public function testControllerFileExistsAtExpectedPath(): void
    {
        $this->assertFileExists($this->root . '/app/mcpapi/controller/v1/shipment/intercept.php');
    }

    public function testIndexForwardsToListWithEmptyRequest(): void
    {
        require_once $this->root . '/app/ome/lib/capability/shipment/intercept.php';
        require_once $this->root . '/app/mcpapi/controller/v1/shipment/intercept.php';

        $capability = new class extends ome_capability_shipment_intercept {
            public function __construct() {}
            public function list(array $request): array
            {
                return [
                    'list' => [['order_bn' => 'O-1', 'status' => 5]],
                    'total' => 1,
                    'page' => 1,
                    'pageSize' => 20,
                ];
            }
        };

        $controller = new mcpapi_ctl_v1_shipment_intercept($capability);
        $response = $controller->index($this->context());

        $this->assertSame(200, $response->status());
        $this->assertSame(1, $response->body()['total']);
        $this->assertSame('O-1', $response->body()['list'][0]['order_bn']);
    }

    public function testListReturnsEnvelopeAt200(): void
    {
        require_once $this->root . '/app/ome/lib/capability/shipment/intercept.php';
        require_once $this->root . '/app/mcpapi/controller/v1/shipment/intercept.php';

        $capability = new class extends ome_capability_shipment_intercept {
            public function __construct() {}
            public function list(array $request): array
            {
                return [
                    'list' => [['order_bn' => 'O-1'], ['order_bn' => 'O-2']],
                    'total' => 2,
                    'page' => 1,
                    'pageSize' => 20,
                ];
            }
        };

        $controller = new mcpapi_ctl_v1_shipment_intercept($capability);
        $response = $controller->list($this->context(), ['page' => 1, 'pageSize' => 20]);

        $this->assertSame(200, $response->status());
        $this->assertSame(2, $response->body()['total']);
        $this->assertCount(2, $response->body()['list']);
    }

    public function testListReturns422WhenMissingTimeRangeAndOrderBn(): void
    {
        require_once $this->root . '/app/ome/lib/capability/shipment/intercept.php';
        require_once $this->root . '/app/mcpapi/controller/v1/shipment/intercept.php';

        $capability = new class extends ome_capability_shipment_intercept {
            public function __construct() {}
            public function list(array $request): array
            {
                if (!isset($request['startTime']) && !isset($request['endTime']) && empty($request['orderBn'])) {
                    throw new InvalidArgumentException('startTime/endTime 或 orderBn 至少传一个');
                }
                return ['list' => [], 'total' => 0, 'page' => 1, 'pageSize' => 20];
            }
        };

        $controller = new mcpapi_ctl_v1_shipment_intercept($capability);
        $response = $controller->list($this->context(), []);

        $this->assertSame(422, $response->status());
        $this->assertSame('validation_failed', $response->body()['error']);
    }

    public function testControllerExposesReadOnlyMethodsOnly(): void
    {
        $source = (string) file_get_contents($this->root . '/app/mcpapi/controller/v1/shipment/intercept.php');

        $this->assertStringContainsString('public function list(', $source);
        $this->assertStringNotContainsString('public function show(', $source);
        $this->assertStringNotContainsString('public function create(', $source);
        $this->assertStringNotContainsString('public function update(', $source);
        $this->assertStringNotContainsString('public function delete(', $source);
    }

    private function context(): mcpapi_auth_context
    {
        return new mcpapi_auth_context('req', 'client', 'subject', 'tenant', 'https://oms.example.internal', 1, [1], ['setting_api_log'], ['setting_api_log'], time() + 3600, true);
    }
}
