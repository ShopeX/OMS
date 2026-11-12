<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class McpapiApiLogWriterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
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

    public function testWriteCreatesFinalApiLogWithRouteMetadata(): void
    {
        $model = new class {
            public array $calls = [];

            public function gen_id(): string
            {
                return 'generated-log-id';
            }

            public function insert(&$data): bool
            {
                $this->calls[] = $data;
                return true;
            }
        };

        $writer = new mcpapi_api_log_writer($model);
        $logId = $writer->write([
            'request_id' => 'req-1',
            'path' => '/mcp-api/v1/brand/list',
            'method' => 'POST',
            'controller' => 'v1_brand',
            'action' => 'list',
            'client_id' => 'client-a',
            'subject_id' => 'subject-a',
            'request' => ['page' => 1],
            'response_status' => 200,
            'response_body' => ['list' => []],
            'duration' => 0.25,
            'message' => 'done',
            'status' => 'success',
        ]);

        $this->assertSame('generated-log-id', $logId);
        $this->assertCount(1, $model->calls);
        $this->assertSame('success', $model->calls[0]['status']);
        $this->assertSame('mcpapi_router:dispatch', $model->calls[0]['worker']);
        $this->assertSame('response', $model->calls[0]['api_type']);
        $this->assertSame('/mcp-api/v1/brand/list', $model->calls[0]['url']);
        $this->assertSame('req-1', $model->calls[0]['msg_id']);
        $this->assertSame(0.25, $model->calls[0]['spendtime']);
        $this->assertStringContainsString('client-a', $model->calls[0]['params']);
        $this->assertStringContainsString('POST', $model->calls[0]['params']);
        $this->assertSame(json_encode(['list' => []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $model->calls[0]['response']);
    }
}
