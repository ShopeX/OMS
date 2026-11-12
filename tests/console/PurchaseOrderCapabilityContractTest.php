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
final class PurchaseOrderCapabilityContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testCapabilityFileExistsAtAutoloadCorrectPath(): void
    {
        $this->assertFileExists($this->root . '/app/console/lib/capability/purchase.php');
    }

    public function testCapabilityListsPurchaseOrders(): void
    {
        require_once $this->root . '/app/console/lib/capability/purchase.php';

        $poModel = new class {
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1, string $orderBy = ''): array
            {
                return [
                    ['po_id' => 1, 'po_bn' => 'PO-1', 'name' => '采购单一'],
                    ['po_id' => 2, 'po_bn' => 'PO-2', 'name' => '采购单二'],
                ];
            }

            public function count(array $filter = []): int
            {
                return 2;
            }
        };

        $capability = new console_capability_purchase(
            $poModel,
            new class { public function dump($id, string $fields = '*'): array { return []; } },
            new class { public function dump($id, string $fields = '*'): array { return []; } },
            new class { public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array { return []; } },
            new class { public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array { return []; } },
            new class { public function read_log(array $filter, int $offset = 0, int $limit = -1): array { return []; } }
        );

        $result = $capability->list(['page' => 1, 'pageSize' => 20]);

        $this->assertSame(2, count($result['list']));
        $this->assertSame('采购单一', $result['list'][0]['name']);
    }

    public function testCapabilityReturnsPurchaseOrderDetailAndTemplate(): void
    {
        require_once $this->root . '/app/console/lib/capability/purchase.php';

        $poModel = new class {
            public function dump($poId, string $fields = '*', array $subs = []): ?array
            {
                return $poId === 9
                    ? ['po_id' => 9, 'po_bn' => 'PO-9', 'name' => '详情采购单', 'supplier_id' => 3, 'branch_id' => 5, 'memo' => serialize([['op_content' => '备注']]), 'po_items' => [['po_item_id' => 10, 'bn' => 'BM-1', 'num' => 2, 'price' => '10.00']]]
                    : null;
            }

            public function io_title($filter): array
            {
                return $filter === 'purchase'
                    ? ['*:CSCID' => '', '*:供应商' => '']
                    : ['*:货号' => 'bn', '*:数量' => 'num'];
            }

            public function exportTemplate($filter): array
            {
                return $filter === 'purchase' ? ['po_bn', 'supplier'] : ['bn', 'num'];
            }
        };

        $capability = new console_capability_purchase(
            $poModel,
            new class { public function dump($id, string $fields = '*'): array { return ['name' => '供应商A', 'bn' => 'SUP-A']; } },
            new class { public function dump($id, string $fields = '*'): array { return ['name' => '仓库A', 'branch_bn' => 'BR-A']; } },
            new class { public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array { return []; } },
            new class { public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array { return [['eo_id' => 7, 'eo_bn' => 'EO-7']]; } },
            new class { public function read_log(array $filter, int $offset = 0, int $limit = -1): array { return [['log_id' => 8, 'operate_time' => 1700000000, 'op_name' => 'tester', 'operation' => 'edit', 'memo' => 'memo', 'ip' => '127.0.0.1']]; } }
        );

        $detail = $capability->detail(9);
        $template = $capability->importTemplate();

        $this->assertSame(9, $detail['basic']['po_id']);
        $this->assertSame('供应商A', $detail['basic']['supplier_name']);
        $this->assertSame('BM-1', $detail['items'][0]['bn']);
        $this->assertSame('*:CSCID,*:供应商', $template['csv_header_sections'][0]);
        $this->assertSame('*:货号,*:数量', $template['csv_header_sections'][1]);
    }

    public function testCapabilityAppliesBranchScopeForNonSuperUser(): void
    {
        require_once $this->root . '/app/console/lib/capability/purchase.php';
        require_once $this->root . '/app/mcpapi/lib/auth/context.php';

        $capturedFilter = null;
        $poModel = new class($capturedFilter) {
            public ?array $capturedFilter = null;
            public function __construct(& $capturedFilter)
            {
                $this->capturedFilter = & $capturedFilter;
            }
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1, string $orderBy = ''): array
            {
                $this->capturedFilter = $filter;
                return [];
            }
            public function count(array $filter = []): int
            {
                return 0;
            }
        };

        $capability = new console_capability_purchase(
            $poModel,
            new class { public function dump($id, string $fields = '*'): array { return []; } },
            new class {
                public function dump($id, string $fields = '*'): array { return []; }
                public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array { return [['branch_id' => 12], ['branch_id' => 18]]; }
            },
            new class { public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array { return [['branch_id' => 12], ['branch_id' => 18]]; } },
            new class { public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array { return [['branch_id' => 12], ['branch_id' => 18]]; } },
            new class { public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array { return []; } },
            new class { public function read_log(array $filter, int $offset = 0, int $limit = -1): array { return []; } }
        );

        $context = new mcpapi_auth_context('req', 'client', 'subj', 'tenant', 'https://oms.example.internal', 99, [1], ['console_purchase_po'], ['console_purchase_po'], time() + 3600, false);
        $capability->list([], $context);

        $this->assertSame([12, 18], $capturedFilter['branch_id']);
    }
}
