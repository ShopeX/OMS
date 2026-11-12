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
final class ReturnProductCapabilityContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testCapabilityFileExistsAtAutoloadCorrectPath(): void
    {
        $this->assertFileExists($this->root . '/app/ome/lib/capability/return/product.php');
    }

    public function testCapabilityListsReturnProductsWithOrganizationScope(): void
    {
        require_once $this->root . '/app/mcpapi/lib/auth/context.php';
        require_once $this->root . '/app/ome/lib/capability/return/product.php';

        $capturedFilter = null;
        $returnModel = new class($capturedFilter) {
            public $capturedFilter;

            public function __construct(& $capturedFilter)
            {
                $this->capturedFilter = & $capturedFilter;
            }

            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1, string $orderBy = ''): array
            {
                $this->capturedFilter = $filter;
                return [
                    ['return_id' => 1, 'return_bn' => 'R-1', 'org_id' => 11],
                    ['return_id' => 2, 'return_bn' => 'R-2', 'org_id' => 22],
                ];
            }

            public function count(array $filter = []): int
            {
                return 2;
            }
        };
        $operationOpsModel = new class {
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array
            {
                return [['org_id' => 11], ['org_id' => 22]];
            }
        };

        $capability = new ome_capability_return_product($returnModel, $operationOpsModel);
        $context = new mcpapi_auth_context('req', 'client', 'subject', 'tenant', 'https://oms.example.internal', 9, [1], ['aftersale_return_apply'], ['aftersale_return_apply'], time() + 3600, false);
        $result = $capability->list(['page' => 1, 'pageSize' => 20], $context);

        $this->assertSame([11, 22], $capturedFilter['org_id']);
        $this->assertSame(2, count($result['list']));
        $this->assertSame('R-1', $result['list'][0]['return_bn']);
    }

    public function testCapabilityReturnsReturnProductDetail(): void
    {
        require_once $this->root . '/app/mcpapi/lib/auth/context.php';
        require_once $this->root . '/app/ome/lib/capability/return/product.php';

        $returnModel = new class {
            public function product_detail(int $id): ?array
            {
                return $id === 9 ? ['return_id' => 9, 'return_bn' => 'R-9', 'org_id' => 11, 'items' => [['bn' => 'SKU-1']], 'log' => [['operation' => 'create']]] : null;
            }
        };
        $operationOpsModel = new class {
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array
            {
                return [['org_id' => 11]];
            }
        };

        $capability = new ome_capability_return_product($returnModel, $operationOpsModel);
        $context = new mcpapi_auth_context('req', 'client', 'subject', 'tenant', 'https://oms.example.internal', 9, [1], ['aftersale_return_apply'], ['aftersale_return_apply'], time() + 3600, false);
        $detail = $capability->detail(9, $context);

        $this->assertSame(9, $detail['basic']['return_id']);
        $this->assertSame('R-9', $detail['basic']['return_bn']);
        $this->assertSame('SKU-1', $detail['basic']['items'][0]['bn']);
        $this->assertSame('create', $detail['basic']['log'][0]['operation']);
    }
}
