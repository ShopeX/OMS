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
final class AftersalesLookupCapabilityContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testCapabilityFileExistsAtAutoloadCorrectPath(): void
    {
        $this->assertFileExists($this->root . '/app/ome/lib/capability/aftersales/lookup.php');
    }

    public function testCapabilityLooksUpBusinessNumbersAcrossAftersaleSourceWithOrganizationScope(): void
    {
        require_once $this->root . '/app/mcpapi/lib/auth/context.php';
        require_once $this->root . '/app/ome/lib/capability/aftersales/lookup.php';

        $calls = [];
        $aftersaleModel = new class($calls) {
            public array $calls;

            public function __construct(& $calls)
            {
                $this->calls = & $calls;
            }

            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1, string $orderBy = ''): array
            {
                $this->calls[] = $filter;
                if (($filter['order_bn'] ?? null) === 'SO-1') {
                    return [[
                        'aftersale_id' => 9,
                        'aftersale_bn' => 'AS-9',
                        'order_id' => 100,
                        'order_bn' => 'SO-1',
                        'shop_id' => 5,
                        'org_id' => 11,
                        'return_type' => 'refund',
                        'refund_apply_money' => '88.00',
                        'refundmoney' => '40.00',
                        'real_refund_amount' => '38.00',
                        'aftersale_time' => 1_700_000_000,
                    ]];
                }
                if (($filter['reship_bn'] ?? null) === 'RS-1') {
                    return [[
                        'aftersale_id' => 10,
                        'aftersale_bn' => 'AS-10',
                        'order_id' => 101,
                        'order_bn' => 'SO-2',
                        'shop_id' => 6,
                        'org_id' => 22,
                        'return_type' => 'change',
                        'refund_apply_money' => '0.00',
                        'refundmoney' => '0.00',
                        'real_refund_amount' => '0.00',
                        'aftersale_time' => 1_700_000_100,
                    ]];
                }
                return [];
            }
        };
        $operationOpsModel = new class {
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array
            {
                return [['org_id' => 11]];
            }
        };

        $capability = new ome_capability_aftersales_lookup($aftersaleModel, $operationOpsModel);
        $context = new mcpapi_auth_context('req', 'client', 'subject', 'tenant', 'https://oms.example.internal', 9, [1], ['aftersale_return_refund_apply'], ['aftersale_return_refund_apply'], time() + 3600, false);
        $result = $capability->lookup('SO-1', $context);

        $this->assertSame('SO-1', $calls[0]['order_bn']);
        $this->assertSame([11], $calls[0]['org_id']);
        $this->assertSame(1, $result['count']);
        $this->assertSame('refund', $result['matches'][0]['kind']);
        $this->assertSame(9, $result['matches'][0]['id']);
        $this->assertSame('AS-9', $result['matches'][0]['summary']['aftersale_bn']);
    }

    public function testCapabilityRejectsEmptyQuery(): void
    {
        require_once $this->root . '/app/ome/lib/capability/aftersales/lookup.php';

        $capability = new ome_capability_aftersales_lookup(new class {
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1, string $orderBy = ''): array
            {
                return [];
            }
        }, new class {
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array
            {
                return [];
            }
        });

        $this->expectException(InvalidArgumentException::class);
        $capability->lookup('');
    }

    public function testCapabilityReturnsEmptyMatchesWhenOrganizationScopeIsEmpty(): void
    {
        require_once $this->root . '/app/mcpapi/lib/auth/context.php';
        require_once $this->root . '/app/ome/lib/capability/aftersales/lookup.php';

        $capability = new ome_capability_aftersales_lookup(new class {
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1, string $orderBy = ''): array
            {
                return [['aftersale_id' => 9, 'order_bn' => 'SO-1']];
            }
        }, new class {
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array
            {
                return [];
            }
        });

        $context = new mcpapi_auth_context('req', 'client', 'subject', 'tenant', 'https://oms.example.internal', 9, [1], ['aftersale_return_refund_apply'], ['aftersale_return_refund_apply'], time() + 3600, false);
        $result = $capability->lookup('SO-1', $context);

        $this->assertSame(0, $result['count']);
        $this->assertSame([], $result['matches']);
    }

    public function testCapabilityPreservesRefuseTypeAndMarksPartialWhenUpperBoundIsHit(): void
    {
        require_once $this->root . '/app/ome/lib/capability/aftersales/lookup.php';

        $rows = [];
        for ($index = 1; $index <= 50; $index++) {
            $rows[] = [
                'aftersale_id' => $index,
                'aftersale_bn' => 'AS-' . $index,
                'order_id' => 100 + $index,
                'order_bn' => $index === 1 ? 'SO-REFUSE' : 'SO-' . $index,
                'shop_id' => 5,
                'org_id' => 11,
                'return_type' => $index === 1 ? 'refuse' : 'refund',
                'refund_apply_money' => '0.00',
                'refundmoney' => '0.00',
                'real_refund_amount' => '0.00',
                'aftersale_time' => 1_700_000_000 + $index,
            ];
        }

        $capability = new ome_capability_aftersales_lookup(new class($rows) {
            private array $rows;
            public function __construct(array $rows) { $this->rows = $rows; }
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1, string $orderBy = ''): array
            {
                return $this->rows;
            }
        }, new class {
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array
            {
                return [];
            }
        });

        $result = $capability->lookup('SO-REFUSE');

        $this->assertSame(1, $result['count']);
        $this->assertSame('refuse', $result['matches'][0]['kind']);
        $this->assertTrue($result['partial']);
        $this->assertSame(['lookup results are based on a bounded source window'], $result['warnings']);
    }

    public function testCapabilityFiltersPrefixFalsePositivesAndMapsRefundedToRefund(): void
    {
        require_once $this->root . '/app/ome/lib/capability/aftersales/lookup.php';

        $capability = new ome_capability_aftersales_lookup(new class {
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1, string $orderBy = ''): array
            {
                return [
                    [
                        'aftersale_id' => 12,
                        'aftersale_bn' => 'AS-12',
                        'order_id' => 112,
                        'order_bn' => 'SO-REFUNDED',
                        'shop_id' => 5,
                        'org_id' => 11,
                        'return_type' => 'refunded',
                        'aftersale_time' => 1_700_000_120,
                    ],
                    [
                        'aftersale_id' => 13,
                        'aftersale_bn' => 'AS-13',
                        'order_id' => 113,
                        'order_bn' => 'SO-REFUNDED-EXTRA',
                        'shop_id' => 5,
                        'org_id' => 11,
                        'return_type' => 'refund',
                        'aftersale_time' => 1_700_000_130,
                    ],
                ];
            }
        }, new class {
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array
            {
                return [];
            }
        });

        $result = $capability->lookup('SO-REFUNDED');

        $this->assertSame(1, $result['count']);
        $this->assertSame('refund', $result['matches'][0]['kind']);
        $this->assertSame('SO-REFUNDED', $result['matches'][0]['summary']['order_bn']);
    }
}
