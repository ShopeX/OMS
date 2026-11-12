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
final class ApiLogCapabilityContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testCapabilityFileExistsAtAutoloadCorrectPath(): void
    {
        $this->assertFileExists($this->root . '/app/ome/lib/capability/api/log.php');
    }

    public function testCapabilityListsApiLogs(): void
    {
        require_once $this->root . '/app/ome/lib/capability/api/log.php';

        $capturedOrder = null;
        $model = new class($capturedOrder) {
            public $capturedOrder;

            public function __construct(& $capturedOrder)
            {
                $this->capturedOrder = & $capturedOrder;
            }

            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1, string $orderBy = ''): array
            {
                $this->capturedOrder = $orderBy;
                return [
                    ['log_id' => 'L-1', 'status' => 'success', 'retry' => '2', 'spendtime' => '0.12'],
                ];
            }

            public function count(array $filter = []): int
            {
                return 1;
            }
        };

        $capability = new ome_capability_api_log($model, null);
        $result = $capability->list(['page' => 1, 'pageSize' => 20]);

        $this->assertSame('', $capturedOrder);
        $this->assertSame('L-1', $result['list'][0]['log_id']);
        $this->assertSame(2, $result['list'][0]['retry']);
        $this->assertSame(0.12, $result['list'][0]['spendtime']);
        $this->assertSame(1, $result['total']);
    }

    public function testCapabilityReturnsProcessedDetail(): void
    {
        require_once $this->root . '/app/ome/lib/capability/api/log.php';

        $model = new class {
            public function dump(string $logId): ?array
            {
                return $logId === 'L-9' ? ['log_id' => 'L-9', 'status' => 'fail', 'retry' => '1'] : null;
            }
        };
        $apiFunc = new class {
            public function processApiLogData(array $row): array
            {
                $row['status_text'] = '失败';
                return $row;
            }
        };

        $capability = new ome_capability_api_log($model, $apiFunc);
        $detail = $capability->detail('L-9');

        $this->assertSame('L-9', $detail['log_id']);
        $this->assertSame('失败', $detail['status_text']);
        $this->assertSame(1, $detail['retry']);
    }
}
