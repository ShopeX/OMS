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
final class OrderCapabilityContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testCapabilityFileExistsAtAutoloadCorrectPath(): void
    {
        $this->assertFileExists($this->root . '/app/ome/lib/capability/order.php');
    }

    public function testCapabilityListsOrdersWithOrganizationScope(): void
    {
        require_once $this->root . '/app/mcpapi/lib/auth/context.php';
        require_once $this->root . '/app/ome/lib/capability/order.php';

        $capturedFilter = null;
        $ordersModel = new class($capturedFilter) {
            public $capturedFilter;

            public function __construct(& $capturedFilter)
            {
                $this->capturedFilter = & $capturedFilter;
            }

            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1, string $orderBy = ''): array
            {
                $this->capturedFilter = $filter;
                return [
                    ['order_id' => 1, 'order_bn' => 'O-1', 'org_id' => 11],
                    ['order_id' => 2, 'order_bn' => 'O-2', 'org_id' => 22],
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

        $capability = new ome_capability_order($ordersModel, $operationOpsModel, new class {
            public function read_log(array $filter, int $offset = 0, int $limit = -1): array
            {
                return [];
            }
        }, new class {
            public function get_normal_order_type(): array
            {
                return ['normal'];
            }
        });

        $context = new mcpapi_auth_context('req', 'client', 'subject', 'tenant', 'https://oms.example.internal', 9, [1], ['order_view'], ['order_view'], time() + 3600, false);
        $result = $capability->list(['page' => 1, 'pageSize' => 20], $context);

        $this->assertSame([11, 22], $capturedFilter['org_id']);
        $this->assertArrayNotHasKey('disabled', $capturedFilter);
        $this->assertArrayNotHasKey('is_fail', $capturedFilter);
        $this->assertArrayNotHasKey('archive', $capturedFilter);
        $this->assertArrayNotHasKey('process_status|noequal', $capturedFilter);
        $this->assertArrayNotHasKey('order_type|in', $capturedFilter);
        $this->assertSame(2, count($result['list']));
        $this->assertSame('O-1', $result['list'][0]['order_bn']);
        $this->assertSame(2, $result['total']);
    }

    public function testCapabilityMyPendingSceneInjectsOperatorScope(): void
    {
        require_once $this->root . '/app/mcpapi/lib/auth/context.php';
        require_once $this->root . '/app/ome/lib/capability/order.php';

        $capturedFilter = null;
        $ordersModel = new class($capturedFilter) {
            public $capturedFilter;

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
        $operationOpsModel = new class {
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array
            {
                return [['org_id' => 11]];
            }
        };

        $capability = new ome_capability_order($ordersModel, $operationOpsModel, new class {
            public function read_log(array $filter, int $offset = 0, int $limit = -1): array
            {
                return [];
            }
        });

        $context = new mcpapi_auth_context('req', 'client', 'subject', 'tenant', 'https://oms.example.internal', 9, [1], ['order_view'], ['order_view'], time() + 3600, false);
        $capability->list(['filters' => ['scene' => 'my_pending', 'op_id' => 999, 'shop_id' => 12]], $context);

        $this->assertSame(9, $capturedFilter['op_id']);
        $this->assertSame('assigned', $capturedFilter['assigned']);
        $this->assertSame(['unconfirmed', 'confirmed', 'splitting'], $capturedFilter['process_status']);
        $this->assertSame('false', $capturedFilter['abnormal']);
        $this->assertSame('false', $capturedFilter['is_fail']);
        $this->assertSame('active', $capturedFilter['status']);
        $this->assertSame(12, $capturedFilter['shop_id']);
        $this->assertSame([11], $capturedFilter['org_id']);
        $this->assertArrayNotHasKey('scene', $capturedFilter);
        $this->assertArrayNotHasKey('order_confirm_filter', $capturedFilter);
    }

    public function testCapabilityPendingSceneUsesGroupFilterWithoutOpId(): void
    {
        require_once $this->root . '/app/mcpapi/lib/auth/context.php';
        require_once $this->root . '/app/ome/lib/capability/order.php';

        $capturedFilter = null;
        $ordersModel = new class($capturedFilter) {
            public $capturedFilter;

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
        $operationOpsModel = new class {
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array
            {
                return [['org_id' => 11]];
            }
        };

        $capability = new ome_capability_order($ordersModel, $operationOpsModel, new class {
            public function read_log(array $filter, int $offset = 0, int $limit = -1): array
            {
                return [];
            }
        });

        $context = new mcpapi_auth_context('req', 'client', 'subject', 'tenant', 'https://oms.example.internal', 9, [1], ['order_view'], ['order_view'], time() + 3600, false);
        $capability->list(['filters' => ['scene' => 'pending', 'op_id' => 9]], $context);

        $this->assertArrayNotHasKey('op_id', $capturedFilter);
        $this->assertArrayNotHasKey('assigned', $capturedFilter);
        $this->assertArrayNotHasKey('scene', $capturedFilter);
        $this->assertSame('group_id > 0', $capturedFilter['order_confirm_filter']);
        $this->assertSame(['unconfirmed', 'confirmed', 'splitting'], $capturedFilter['process_status']);
        $this->assertSame('false', $capturedFilter['abnormal']);
        $this->assertSame('active', $capturedFilter['status']);
        $this->assertSame([11], $capturedFilter['org_id']);
    }

    public function testCapabilityMyPendingSceneSkipsOpIdForSuperUser(): void
    {
        require_once $this->root . '/app/mcpapi/lib/auth/context.php';
        require_once $this->root . '/app/ome/lib/capability/order.php';

        $capturedFilter = null;
        $ordersModel = new class($capturedFilter) {
            public $capturedFilter;

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

        $capability = new ome_capability_order($ordersModel, new class {
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array
            {
                return [];
            }
        }, new class {
            public function read_log(array $filter, int $offset = 0, int $limit = -1): array
            {
                return [];
            }
        });

        $context = new mcpapi_auth_context('req', 'client', 'subject', 'tenant', 'https://oms.example.internal', 9, [1], ['order_view'], ['order_view'], time() + 3600, true);
        $capability->list(['filters' => ['scene' => 'my_pending']], $context);

        $this->assertArrayNotHasKey('op_id', $capturedFilter);
        $this->assertSame('assigned', $capturedFilter['assigned']);
        $this->assertSame(['unconfirmed', 'confirmed', 'splitting'], $capturedFilter['process_status']);
    }

    public function testCapabilityRejectsUnknownScene(): void
    {
        require_once $this->root . '/app/mcpapi/lib/auth/context.php';
        require_once $this->root . '/app/ome/lib/capability/order.php';

        $capability = new ome_capability_order(new class {
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1, string $orderBy = ''): array
            {
                return [];
            }

            public function count(array $filter = []): int
            {
                return 0;
            }
        }, new class {
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array
            {
                return [];
            }
        }, new class {
            public function read_log(array $filter, int $offset = 0, int $limit = -1): array
            {
                return [];
            }
        });

        $context = new mcpapi_auth_context('req', 'client', 'subject', 'tenant', 'https://oms.example.internal', 9, [1], ['order_view'], ['order_view'], time() + 3600, false);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('filters.scene 仅支持 my_pending、pending');
        $capability->list(['filters' => ['scene' => 'unknown']], $context);
    }

    public function testCapabilityReturnsOrderDetailWithObjectsAndLogs(): void
    {
        require_once $this->root . '/app/mcpapi/lib/auth/context.php';
        require_once $this->root . '/app/ome/lib/capability/order.php';

        $ordersModel = new class {
            public function dump($orderId, string $fields = '*', array $subs = []): ?array
            {
                return $orderId === 9 ? [
                    'order_id' => 9,
                    'order_bn' => 'O-9',
                    'org_id' => 11,
                    'shop_id' => 2,
                    'shop_type' => 'taobao',
                    'member_id' => 3,
                    'pay_status' => '1',
                    'order_bool_type' => 32,
                    'mark_type' => 'b1',
                    'abnormal' => 'true',
                    'abnormal_status' => 1,
                    'archive' => 1,
                    'disabled' => 'true',
                    'is_fail' => 'true',
                    'process_status' => 'cancel',
                    'order_objects' => [
                        ['obj_id' => 1, 'order_items' => [['item_id' => 6, 'product_id' => 8, 'name' => 'SKU-A']]],
                    ],
                ] : null;
            }
        };
        $operationOpsModel = new class {
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array
            {
                return [['org_id' => 11]];
            }
        };
        $capturedLogFilter = null;
        $logModel = new class($capturedLogFilter) {
            public $capturedLogFilter;

            public function __construct(& $capturedLogFilter)
            {
                $this->capturedLogFilter = & $capturedLogFilter;
            }

            public function read_log(array $filter, int $offset = 0, int $limit = -1): array
            {
                $this->capturedLogFilter = $filter;
                return [['log_id' => 3, 'operate_time' => 1700000000, 'operation' => 'edit', 'op_name' => 'admin', 'memo' => 'memo', 'ip' => '127.0.0.1']];
            }
        };
        $orderExtendModel = new class {
            public function dump(array $filter): array
            {
                return ['order_id' => 9, 'store_bn' => 'STORE-9', 'platform_logi_no' => 'PL-9'];
            }
        };
        $paymentsModel = new class {
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array
            {
                return [['payment_id' => 1, 'payment_bn' => 'P-1', 'money' => '23.00', 'paymethod' => '支付宝', 't_begin' => 1700000000]];
            }
        };
        $refundsModel = new class {
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array
            {
                return [['refund_bn' => 'R-1', 'money' => '5.00', 'paymethod' => '原路退回', 'payment' => 0, 't_ready' => 1700000200]];
            }
        };
        $refundApplyModel = new class {
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array
            {
                return [['refund_apply_bn' => 'RA-1', 'status' => '2', 'money' => '8.00', 'refunded' => '0.00', 'bool_type' => 0, 'create_time' => 1700000300]];
            }
        };
        $orderPmtModel = new class {
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array
            {
                return [['pmt_describe' => '满减', 'pmt_amount' => '10.00']];
            }
        };
        $orderCouponModel = new class {
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array
            {
                return [['type' => '平台券', 'type_name' => '平台券', 'pmt_describe' => '平台券', 'pmt_amount' => '3.00', 'total_amount' => '3.00', 'oid' => 'OID-1', 'material_bn' => 'MBN-1', 'amount' => '3.00']];
            }
        };
        $billLabelModel = new class {
            public function getBIllLabelList(array $billIds, string $billType = 'order'): array
            {
                return [['bill_id' => '9', 'bill_type' => $billType, 'label_code' => 'SOMS_GNJY', 'label_name' => '国内寄样']];
            }
        };
        $refundBoolTypeLib = new class {
            public function isPriceProtectRefund(int $boolType): bool
            {
                return false;
            }
        };
        $orderBoolTypeLib = new class {
            public function getBoolTypeDescribe(int $boolType, string $shopType = 'taobao'): string
            {
                return $boolType === 32 ? 'TM物流升级' : '';
            }

            public function isCPUP(int $boolType): bool
            {
                return $boolType === 32;
            }

            public function isJITX(int $boolType): bool
            {
                return false;
            }

            public function isAoxiang(int $boolType): bool
            {
                return false;
            }

            public function isJDLVMI(int $boolType): bool
            {
                return false;
            }

            public function isDWBrand(int $boolType): bool
            {
                return false;
            }

            public function isDWJISU(int $boolType): bool
            {
                return false;
            }

            public function isO2opick(int $boolType): bool
            {
                return false;
            }

            public function isBookingDelivery(int $boolType): bool
            {
                return false;
            }
        };
        $billLabelShsmLib = new class {
            public function isTinyPieces(int $orderId): bool
            {
                return $orderId === 9;
            }
        };
        $abnormalModel = new class {
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1, string $orderBy = ''): array
            {
                return [['abnormal_id' => 10, 'abnormal_type_id' => 2, 'abnormal_type_name' => '赠品异常', 'abnormal_memo' => serialize([['op_name' => 'admin', 'op_content' => 'memo']]), 'is_done' => 'false']];
            }
        };
        $preprocessConstLib = new class {
            public function getBoolTypeIdentifier(int $boolType, string $shopType = ''): string
            {
                return $boolType === 1 ? '<span>赠</span>' : '';
            }
        };

        $capability = new ome_capability_order(
            $ordersModel,
            $operationOpsModel,
            $logModel,
            new class {
                public function get_normal_order_type(): array
                {
                    return ['normal'];
                }
            },
            $orderExtendModel,
            $paymentsModel,
            $refundsModel,
            $refundApplyModel,
            $orderPmtModel,
            $orderCouponModel,
            $billLabelModel,
            $refundBoolTypeLib,
            $orderBoolTypeLib,
            $billLabelShsmLib,
            $abnormalModel,
            $preprocessConstLib
        );
        $context = new mcpapi_auth_context('req', 'client', 'subject', 'tenant', 'https://oms.example.internal', 9, [1], ['order_view'], ['order_view'], time() + 3600, false);
        $detail = $capability->detail(9, $context);

        $this->assertSame(9, $detail['basic']['order_id']);
        $this->assertSame('O-9', $detail['basic']['order_bn']);
        $this->assertSame('SKU-A', $detail['order_objects'][0]['order_items'][0]['name']);
        $this->assertSame('edit', $detail['oplog'][0]['operation']);
        $this->assertSame(['obj_id' => 9, 'obj_type' => 'orders@ome'], $capturedLogFilter);
        $this->assertSame('STORE-9', $detail['order_extend']['store_bn']);
        $this->assertSame('P-1', $detail['payments'][0]['payment_bn']);
        $this->assertSame('R-1', $detail['refunds'][0]['refund_bn']);
        $this->assertSame('RA-1', $detail['refund_apply'][0]['refund_apply_bn']);
        $this->assertSame('满减', $detail['pmts'][0]['pmt_describe']);
        $this->assertSame('OID-1', $detail['coupon']['pmts'][0]['oid']);
        $this->assertSame('SOMS_GNJY', $detail['bill_labels'][0]['label_code']);
        $this->assertSame(32, $detail['order_identifiers']['order_bool_type']);
        $this->assertSame('TM物流升级', $detail['order_identifiers']['order_bool_type_text']);
        $this->assertSame('b1', $detail['order_identifiers']['mark_type']);
        $this->assertSame(['SOMS_GNJY'], $detail['order_identifiers']['bill_label_codes']);
        $this->assertTrue($detail['order_identifiers']['is_cpup']);
        $this->assertTrue($detail['order_identifiers']['is_shsm']);
        $this->assertSame('true', $detail['abnormal_identifiers']['abnormal']);
        $this->assertSame(1, $detail['abnormal_identifiers']['abnormal_status']);
        $this->assertSame('<span>赠</span>', $detail['abnormal_identifiers']['abnormal_status_display']);
        $this->assertSame('赠品异常', $detail['abnormal_identifiers']['abnormal_latest']['abnormal_type_name']);
    }

    public function testCapabilityRejectsOrderDetailOutsideOrganizationScope(): void
    {
        require_once $this->root . '/app/mcpapi/lib/auth/context.php';
        require_once $this->root . '/app/ome/lib/capability/order.php';

        $ordersModel = new class {
            public function dump($orderId, string $fields = '*', array $subs = []): ?array
            {
                return $orderId === 9 ? ['order_id' => 9, 'order_bn' => 'O-9', 'org_id' => 11, 'order_objects' => []] : null;
            }
        };
        $operationOpsModel = new class {
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array
            {
                return [['org_id' => 22]];
            }
        };

        $capability = new ome_capability_order(
            $ordersModel,
            $operationOpsModel,
            new class {
                public function read_log(array $filter, int $offset = 0, int $limit = -1): array
                {
                    return [];
                }
            },
            new class {
                public function get_normal_order_type(): array
                {
                    return ['normal'];
                }
            },
            new class {
                public function dump(array $filter): array
                {
                    return [];
                }
            },
            new class {
                public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array
                {
                    return [];
                }
            },
            new class {
                public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array
                {
                    return [];
                }
            },
            new class {
                public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array
                {
                    return [];
                }
            },
            new class {
                public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array
                {
                    return [];
                }
            },
            new class {
                public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array
                {
                    return [];
                }
            },
            new class {
                public function getBIllLabelList(array $billIds, string $billType = 'order'): array
                {
                    return [];
                }
            },
            new class {
                public function isPriceProtectRefund(int $boolType): bool
                {
                    return false;
                }
            },
            new class {
                public function getBoolTypeDescribe(int $boolType, string $shopType = 'taobao'): string
                {
                    return '';
                }

                public function isCPUP(int $boolType): bool
                {
                    return false;
                }

                public function isJITX(int $boolType): bool
                {
                    return false;
                }

                public function isAoxiang(int $boolType): bool
                {
                    return false;
                }

                public function isJDLVMI(int $boolType): bool
                {
                    return false;
                }

                public function isDWBrand(int $boolType): bool
                {
                    return false;
                }

                public function isDWJISU(int $boolType): bool
                {
                    return false;
                }

                public function isO2opick(int $boolType): bool
                {
                    return false;
                }

                public function isBookingDelivery(int $boolType): bool
                {
                    return false;
                }
            },
            new class {
                public function isTinyPieces(int $orderId): bool
                {
                    return false;
                }
            },
            new class {
                public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1, string $orderBy = ''): array
                {
                    return [];
                }
            },
            new class {
                public function getBoolTypeIdentifier(int $boolType, string $shopType = ''): string
                {
                    return '';
                }
            }
        );

        $context = new mcpapi_auth_context('req', 'client', 'subject', 'tenant', 'https://oms.example.internal', 9, [1], ['order_view'], ['order_view'], time() + 3600, false);

        $this->assertNull($capability->detail(9, $context));
    }
}
