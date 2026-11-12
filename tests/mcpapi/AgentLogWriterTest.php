<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Agent 审计日志 writer：action_type 推导 / biz_keys 提取 / 脱敏 / 截断 / 字段落库
 */
final class AgentLogWriterTest extends TestCase
    {
        private string $cwdBeforeTest;

        protected function setUp(): void
        {
            parent::setUp();
            $this->cwdBeforeTest = getcwd();
            chdir(__DIR__ . '/../..');
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

    private object $model;
    private array $inserted = [];

    private function writer(int $now = 1800000000): mcpapi_agent_log_writer
    {
        $this->inserted = [];
        $this->model = new class {
            public array $rows = [];
            public function insert(array $row): void { $this->rows[] = $row; }
            public function getList(...$args): array { return []; }
        };
        return new mcpapi_agent_log_writer($this->model, null, $now);
    }

    private function lastRow(): array
    {
        return $this->model->rows[0] ?? [];
    }

    public function testActionTypeDerivation(): void
    {
        $cases = [
            'material_category_list' => 'query',
            'material_category_get' => 'query',
            'order_get' => 'query',
            'material_category_create' => 'create',
            'user_create_prepare' => 'create',
            'purchase_order_import' => 'create',
            'material_category_update' => 'update',
            'autodispatch_setDefault' => 'update',
            'material_category_delete' => 'delete',
            'shipment_intercept_cancel' => 'delete',
            'unknown_tool_xyz' => 'query',
        ];
        foreach ($cases as $tool => $expected) {
            $writer = $this->writer();
            $writer->write(['tool_name' => $tool, 'path' => '/x', 'method' => 'POST']);
            $this->assertSame($expected, $this->lastRow()['action_type'], "tool: $tool");
        }
    }

    public function testWriteFlagFollowsActionTypeNotHttpMethod(): void
    {
        // POST 的查询工具：write_flag=false
        $writer = $this->writer();
        $writer->write(['tool_name' => 'order_list', 'path' => '/x', 'method' => 'POST']);
        $this->assertSame('false', $this->lastRow()['write_flag']);

        // POST 的删除工具：write_flag=true
        $writer = $this->writer();
        $writer->write(['tool_name' => 'material_category_delete', 'path' => '/x', 'method' => 'POST']);
        $this->assertSame('true', $this->lastRow()['write_flag']);
    }

    public function testBizKeysExtraction(): void
    {
        $writer = $this->writer();
        $writer->write([
            'tool_name' => 'order_update',
            'path' => '/x',
            'method' => 'POST',
            'params' => [
                'order_id' => 123,
                'nested' => ['po_bn' => 'PO2026'],
                'irrelevant' => 'x',
                'secret_pw' => 'value-not-collected',
            ],
        ]);
        $row = $this->lastRow();
        $this->assertSame('order_id=123,po_bn=PO2026', $row['biz_keys'] ?? '');
    }

    public function testParamsAreRedactedAndTruncated(): void
    {
        $writer = $this->writer();
        $big = str_repeat('a', 70000);
        $writer->write([
            'tool_name' => 'user_create',
            'path' => '/x',
            'method' => 'POST',
            'params' => ['username' => 'admin', 'password' => 'super-secret', 'blob' => $big],
        ]);
        $row = $this->lastRow();
        $this->assertStringNotContainsString('super-secret', (string) $row['params']);
        $this->assertStringContainsString('[REDACTED]', (string) $row['params']);
        $this->assertLessThanOrEqual(65536 + 20, strlen((string) $row['params']));
        $this->assertStringEndsWith('...[truncated]', (string) $row['params']);
    }

    public function testTraceAndConfirmAndMode(): void
    {
        $writer = $this->writer();
        $writer->write([
            'tool_name' => 'order_get',
            'path' => '/x',
            'method' => 'GET',
            'trace_id' => 'conv-abc',
            'confirm_used' => true,
            'session_id' => 'sess-1',
            'client_mode' => 'bearer',
            'agent_name' => 'workbuddy',
            'agent_version' => '1.0',
            'model' => 'qwen-max',
            'user_id' => 7,
            'client_id' => 'mcp_x',
        ]);
        $row = $this->lastRow();
        $this->assertSame('conv-abc', $row['trace_id']);
        $this->assertSame('true', $row['confirm_used']);
        $this->assertSame('sess-1', $row['session_id']);
        $this->assertSame('bearer', $row['client_mode']);
        $this->assertSame('workbuddy', $row['agent_name']);
        $this->assertSame('qwen-max', $row['model']);
        $this->assertSame(7, $row['user_id']);
    }

    /**
     * router writeApiLog 集成：从请求头收集 agent/tool 字段并写入
     */
    public function testRouterWriteApiLogFeedsAgentLog(): void
    {
        $inserted = [];
        $fakeModel = new class {
            public array $rows = [];
            public function insert(array $row): void { $this->rows[] = $row; }
            public function getList(...$args): array { return []; }
        };
        $writer = new mcpapi_agent_log_writer($fakeModel, null, 1800000000);

        $router = new mcpapi_router(new mcpapi_auth_guard(static function (string $token): array {
            return [];
        }));
        // fake OME writer（避免真实写 sdb_ome_api_log）
        $omeWriter = new class implements mcpapi_api_log_writer_contract {
            public function write(array $context): string { return 'fake'; }
        };
        $omeProp = new ReflectionProperty($router, 'apiLogWriter');
        $omeProp->setValue($router, $omeWriter);
        $prop = new ReflectionProperty($router, 'agentLogWriter');
        $prop->setValue($router, $writer);

        $context = new mcpapi_auth_context(
            'req-1', 'mcp_x', '1', 'default', 'https://oms.example', 1, [], ['order_view'], ['oms:read'], 9999999999, false
        );
        $response = mcpapi_http_response::fromJson(200, ['ok' => true]);

        $method = new ReflectionMethod($router, 'writeApiLog');
        $method->setAccessible(true);
        $method->invoke($router, [
            'auth' => 'mcp', 'controllerKey' => 'v1_order', 'action' => 'index',
        ], 'GET', '/mcp-api/v1/order', 'req-1', [], $context, $response, microtime(true), [
            'HTTP_X_MCP_TOOL_NAME' => 'order_get',
            'HTTP_X_MCP_AGENT_NAME' => 'workbuddy',
            'HTTP_X_MCP_AGENT_VERSION' => '1.0',
            'HTTP_X_MCP_MODEL' => 'qwen-max',
            'HTTP_MCP_SESSION_ID' => 'sess-9',
            'HTTP_X_SHOPEX_OMS_CLIENT_MODE' => 'bearer',
        ]);

        $row = $fakeModel->rows[0] ?? [];
        $this->assertSame('order_get', $row['tool_name'] ?? '');
        $this->assertSame('workbuddy', $row['agent_name'] ?? '');
        $this->assertSame('qwen-max', $row['model'] ?? '');
        $this->assertSame('bearer', $row['client_mode'] ?? '');
        $this->assertSame('mcp_x', $row['client_id'] ?? '');
        $this->assertSame(1, $row['user_id'] ?? 0);
        $this->assertSame('query', $row['action_type'] ?? '');
    }
}
