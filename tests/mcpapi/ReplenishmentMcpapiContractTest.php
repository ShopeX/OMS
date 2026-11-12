<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ReplenishmentMcpapiContractTest extends TestCase
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

    public function testRecommendReturns200WithValidInput(): void
    {
        require_once $this->root . '/app/console/lib/capability/replenishment.php';

        $capability = $this->capability();
        $capability->recommendResult = [
            'recommendation_id' => 'REPL-aaaaaaaaaaaaaaaa',
            'generated_at' => '2026-08-12T00:00:00+00:00',
            'branch_id' => 1,
            'branch_name' => 'Test',
            'time_from' => '2026-07-28',
            'time_to' => '2026-08-10',
            'total_low_stock' => 2,
            'lines' => [
                ['product_id' => 100, 'material_bn' => 'BM-001', 'store' => 10, 'store_freeze' => 5, 'safe_store' => 50, 'available' => 5, 'daily_sales' => 5.0, 'days_remaining' => 1.0],
                ['product_id' => 200, 'material_bn' => 'BM-002', 'store' => 5, 'store_freeze' => 0, 'safe_store' => 20, 'available' => 5, 'daily_sales' => 2.0, 'days_remaining' => 2.5],
            ],
        ];

        $controller = new mcpapi_ctl_v1_purchase_replenishment($capability);
        $response = $controller->recommend($this->context(), ['branch_id' => 1]);

        $this->assertSame(200, $response->status());
        $this->assertSame('REPL-aaaaaaaaaaaaaaaa', $response->body()['recommendation_id']);
        $this->assertSame(2, $response->body()['total_low_stock']);
    }

    public function testRecommendReturns422OnMissingBranchId(): void
    {
        require_once $this->root . '/app/console/lib/capability/replenishment.php';

        $capability = $this->capability();
        $controller = new mcpapi_ctl_v1_purchase_replenishment($capability);
        $response = $controller->recommend($this->context(), []);

        $this->assertSame(422, $response->status());
        $this->assertSame('validation_failed', $response->body()['error']);
    }

    public function testRecommendReturns422OnInvalidDateFormat(): void
    {
        require_once $this->root . '/app/console/lib/capability/replenishment.php';

        $capability = $this->capability();
        $controller = new mcpapi_ctl_v1_purchase_replenishment($capability);
        $response = $controller->recommend($this->context(), ['branch_id' => 1, 'time_from' => 'invalid']);

        $this->assertSame(422, $response->status());
        $this->assertSame('validation_failed', $response->body()['error']);
    }

    public function testRecommendReturns422OnDateFromAfterTo(): void
    {
        require_once $this->root . '/app/console/lib/capability/replenishment.php';

        $capability = $this->capability();
        $controller = new mcpapi_ctl_v1_purchase_replenishment($capability);
        $response = $controller->recommend($this->context(), ['branch_id' => 1, 'time_from' => '2026-08-20', 'time_to' => '2026-08-01']);

        $this->assertSame(422, $response->status());
        $this->assertSame('validation_failed', $response->body()['error']);
    }

    public function testRecommendReturns422OnTooLargeDateRange(): void
    {
        require_once $this->root . '/app/console/lib/capability/replenishment.php';

        $capability = $this->capability();
        $controller = new mcpapi_ctl_v1_purchase_replenishment($capability);
        $response = $controller->recommend($this->context(), ['branch_id' => 1, 'time_from' => '2026-01-01', 'time_to' => '2026-03-31']);

        $this->assertSame(422, $response->status());
        $this->assertSame('validation_failed', $response->body()['error']);
    }

    private function capability(): object
    {
        return new class {
            public $recommendResult = null;

            public function recommend(array $request, ?mcpapi_auth_context $context = null): array
            {
                if ($this->recommendResult !== null) {
                    return $this->recommendResult;
                }
                return (new console_capability_replenishment(
                    new class {
                        public $db;
                        public function __construct()
                        {
                            $this->db = new class {
                                public function select($s): array { return []; }
                            };
                        }
                    },
                    new class {
                        public function dump($id, string $f = '*'): array { return ['branch_id' => $id, 'name' => 'Test']; }
                        public function getList(...$args): array { return []; }
                    },
                    new class { public function getList(...$args): array { return []; } }
                ))->recommend($request, $context);
            }
        };
    }

    private function context(): mcpapi_auth_context
    {
        return new mcpapi_auth_context(
            'request-replenishment',
            'client-replenishment',
            'subject-replenishment',
            'tenant-replenishment',
            'https://oms.example.internal',
            1,
            [1],
            ['console_purchase_po'],
            ['console_purchase_po'],
            time() + 3600,
            true
        );
    }
}
