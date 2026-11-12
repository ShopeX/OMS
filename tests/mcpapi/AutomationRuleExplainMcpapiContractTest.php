<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

#[PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
final class AutomationRuleExplainMcpapiContractTest extends TestCase
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

    public function testControllerAndRouterExposeExplainRoute(): void
    {
        $this->assertFileExists($this->root . '/app/mcpapi/controller/v1/automation/rule.php');
        $controllerSource = (string) file_get_contents($this->root . '/app/mcpapi/controller/v1/automation/rule.php');
        $routerSource = (string) file_get_contents($this->root . '/app/mcpapi/lib/router.php');

        $this->assertStringContainsString('final class mcpapi_ctl_v1_automation_rule', $controllerSource);
        $this->assertStringContainsString('public function explain(', $controllerSource);
        $this->assertStringContainsString("'v1_automation_rule' => ['explain']", $routerSource);
    }

    public function testControllerDelegatesExplainToCapability(): void
    {
        require_once $this->root . '/app/omeauto/lib/capability/rule/explain.php';
        require_once $this->root . '/app/mcpapi/controller/v1/automation/rule.php';

        $capability = new class extends omeauto_capability_rule_explain {
            public array $calls = [];

            public function __construct()
            {
            }

            public function explain(array $request, ?array $allowedOrganizationIds): array
            {
                $this->calls[] = [$request, $allowedOrganizationIds];
                return ['order' => ['order_id' => 42], 'reasons' => []];
            }
        };

        $controller = new mcpapi_ctl_v1_automation_rule($capability);
        $response = $controller->explain($this->context(true), ['order_bn' => 'O-42', 'expected_audit_rule_oid' => 1]);

        $this->assertSame(200, $response->status());
        $this->assertSame(42, $response->body()['order']['order_id']);
        $this->assertSame(['order_bn' => 'O-42', 'expected_audit_rule_oid' => 1], $capability->calls[0][0]);
        $this->assertNull($capability->calls[0][1]);
    }

    public function testControllerMapsValidationExceptionTo422(): void
    {
        require_once $this->root . '/app/omeauto/lib/capability/rule/explain.php';
        require_once $this->root . '/app/mcpapi/controller/v1/automation/rule.php';

        $capability = new class extends omeauto_capability_rule_explain {
            public function __construct()
            {
            }

            public function explain(array $request, ?array $allowedOrganizationIds): array
            {
                throw new InvalidArgumentException('order_id 或 order_bn 至少需要一个');
            }
        };

        $controller = new mcpapi_ctl_v1_automation_rule($capability);
        $response = $controller->explain($this->context(true), []);

        $this->assertSame(422, $response->status());
        $this->assertSame('validation_failed', $response->body()['error']);
    }

    public function testCapabilityParsesActualRuleFromOperationLog(): void
    {
        require_once $this->root . '/app/omeauto/lib/auto/group/item.php';
        require_once $this->root . '/app/omeauto/lib/capability/rule/explain.php';

        $capability = new omeauto_capability_rule_explain(
            $this->orderModel(),
            $this->emptyListModel(),
            $this->emptyListModel(),
            $this->emptyListModel(),
            $this->emptyDumpModel(),
            new class {
                public function getList(string $columns, array $filters, int $offset, int $limit, string $orderBy = ''): array
                {
                    return [
                        [
                            'log_id' => 8,
                            'obj_id' => '42',
                            'operation' => 'order_confirm@ome',
                            'operate_time' => 1700000000,
                            'memo' => '订单确认,操作员:系统,审单规则:B规则(7),仓库规则:-',
                        ],
                    ];
                }
            },
            $this->emptyModel(),
            $this->emptyModel(),
            $this->emptyModel(),
            $this->emptyListModel(),
            $this->emptyModel(),
            $this->emptySelectModel(),
            null
        );

        $result = $capability->explain(['order_id' => 42], null);

        $this->assertSame(7, $result['actual']['audit_rule']['oid']);
        $this->assertSame('B规则', $result['actual']['audit_rule']['name']);
        $this->assertSame(0, $result['actual']['warehouse_rule']['tid']);
    }

    public function testCapabilityReasonsAuditRuleDisabled(): void
    {
        require_once $this->root . '/app/omeauto/lib/auto/group/item.php';
        require_once $this->root . '/app/omeauto/lib/capability/rule/explain.php';

        $auditModel = new class {
            public function dump(array $conditions, string $fields = '*'): array
            {
                return ['oid' => 3, 'name' => 'A规则', 'disabled' => 'true', 'config' => []];
            }
        };

        $capability = new omeauto_capability_rule_explain(
            $this->orderModel(),
            $this->emptyListModel(),
            $this->emptyListModel(),
            $this->emptyListModel(),
            $this->emptyDumpModel(),
            $this->emptyListModel(),
            $this->emptyModel(),
            $auditModel,
            $this->emptyModel(),
            $this->emptyListModel(),
            $this->emptyModel(),
            $this->emptySelectModel(),
            null
        );

        $result = $capability->explain(['order_id' => 42, 'expected_audit_rule_oid' => 3], null);
        $codes = array_column($result['reasons'], 'code');

        $this->assertContains('AUDIT_RULE_DISABLED', $codes);
    }

    private function orderModel(): object
    {
        return new class {
            public function getList(string $columns, array $filters, int $offset, int $limit, string $orderBy = ''): array
            {
                return [['order_id' => 42, 'order_bn' => 'O-42', 'org_id' => 1, 'shop_id' => 1, 'order_type' => 'normal', 'total_amount' => '100.00', 'process_status' => 'confirmed']];
            }
        };
    }

    private function emptyModel(): object
    {
        return new class {
            public function dump(array $conditions, string $fields = '*'): ?array
            {
                return null;
            }

            public function getList(string $columns, array $filters = [], int $offset = 0, int $limit = -1, string $orderBy = ''): array
            {
                return [];
            }
        };
    }

    private function emptyListModel(): object
    {
        return new class {
            public function getList(string $columns, array $filters = [], int $offset = 0, int $limit = -1, string $orderBy = ''): array
            {
                return [];
            }
        };
    }

    private function emptyDumpModel(): object
    {
        return new class {
            public function dump(array $conditions, string $fields = '*'): ?array
            {
                return null;
            }
        };
    }

    private function emptySelectModel(): object
    {
        return new class {
            public function select(string $sql): array
            {
                return [];
            }
        };
    }

    private function context(bool $isSuper): mcpapi_auth_context
    {
        return new mcpapi_auth_context(
            'request-rule-explain',
            'client-rule-explain',
            'subject-rule-explain',
            'tenant-rule-explain',
            'https://oms.example.internal',
            1,
            [1],
            ['order_view'],
            ['order_view'],
            time() + 3600,
            $isSuper
        );
    }
}
