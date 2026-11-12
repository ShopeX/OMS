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
final class ShipmentInterceptCapabilityContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testCapabilityFileExistsAtAutoloadCorrectPath(): void
    {
        $this->assertFileExists($this->root . '/app/ome/lib/capability/shipment/intercept.php');
    }

    public function testListRequiresTimeRangeOrOrderBn(): void
    {
        require_once $this->root . '/app/ome/lib/capability/shipment/intercept.php';

        $provider = $this->createStubProvider([]);
        $capability = new ome_capability_shipment_intercept($provider);
        $this->expectException(InvalidArgumentException::class);
        $capability->list([]);
    }

    public function testListReturnsEmptyWhenNoFailedLogs(): void
    {
        require_once $this->root . '/app/ome/lib/capability/shipment/intercept.php';

        $provider = $this->createStubProvider([
            'shipment_log' => [],
            'delivery_items_detail' => [],
            'delivery' => [],
            'order_objects' => [],
            'order_items' => [],
        ]);

        $capability = new ome_capability_shipment_intercept($provider);
        $result = $capability->list(['orderBn' => 'NO_LOGS']);

        $this->assertSame(['list' => [], 'total' => 0, 'page' => 1, 'pageSize' => 20], $result);
    }

    public function testListReturnsEmptyWhenSubOrdersNotFullRefund(): void
    {
        require_once $this->root . '/app/ome/lib/capability/shipment/intercept.php';

        $provider = $this->createStubProvider([
            'shipment_log' => [
                ['orderBn' => 'ORDER_001', 'status' => 'fail', 'receiveTime' => 1784600000, 'message' => 'fail'],
            ],
            'delivery_items_detail' => [
                ['delivery_id' => 1, 'order_obj_id' => 10, 'order_item_id' => 100, 'oid' => 'ORDER_001', 'bn' => 'BN_001', 'number' => 2],
            ],
            'delivery' => [
                ['delivery_id' => 1, 'delivery_bn' => 'DN001', 'logi_id' => 5, 'logi_no' => 'LOGI001'],
            ],
            'order_objects' => [
                ['obj_id' => 10, 'pay_status' => 1],
            ],
            'order_items' => [],
        ]);

        $capability = new ome_capability_shipment_intercept($provider);
        $result = $capability->list(['orderBn' => 'ORDER_001']);

        $this->assertSame(0, $result['total']);
        $this->assertSame([], $result['list']);
    }

    public function testListReturnsFullRefundSubOrdersWithItemDetails(): void
    {
        require_once $this->root . '/app/ome/lib/capability/shipment/intercept.php';

        $provider = $this->createStubProvider([
            'shipment_log' => [
                ['orderBn' => 'ORDER_001', 'status' => 'fail', 'receiveTime' => 1784600000, 'message' => 'platform error'],
            ],
            'delivery_items_detail' => [
                ['delivery_id' => 1, 'order_obj_id' => 10, 'order_item_id' => 100, 'oid' => 'ORDER_001', 'bn' => 'BASIC_MBN_001', 'number' => 2],
            ],
            'delivery' => [
                ['delivery_id' => 1, 'delivery_bn' => 'DN001', 'logi_id' => 5, 'logi_no' => 'LOGI001'],
            ],
            'order_objects' => [
                ['obj_id' => 10, 'pay_status' => 5],
            ],
            'order_items' => [
                ['item_id' => 100, 'bn' => 'BASIC_MBN_001', 'name' => '商品A', 'nums' => 2],
            ],
        ]);

        $capability = new ome_capability_shipment_intercept($provider);
        $result = $capability->list(['orderBn' => 'ORDER_001']);

        $this->assertSame(1, $result['total']);
        $this->assertCount(1, $result['list']);

        $row = $result['list'][0];
        $this->assertSame('ORDER_001', $row['order_bn']);
        $this->assertSame('DN001', $row['delivery_bn']);
        $this->assertSame(5, $row['logi_corp_code']);
        $this->assertSame('LOGI001', $row['delivery_code']);
        $this->assertSame('ORDER_001', $row['oid']);
        $this->assertSame('BASIC_MBN_001', $row['bn']);
        $this->assertSame('商品A', $row['name']);
        $this->assertSame(2.0, $row['quantity']);
        $this->assertSame(5, $row['status']);
        $this->assertSame(1784600000, $row['receive_time']);
        $this->assertSame('platform error', $row['fail_message']);
    }

    private function createStubProvider(array $fixturesByModel): object
    {
        return new class($fixturesByModel) {
            private array $fixturesByModel;

            public function __construct(array $fixturesByModel)
            {
                $this->fixturesByModel = $fixturesByModel;
            }

            public function model(string $name): object
            {
                $fixtures = $this->fixturesByModel[$name] ?? [];
                return new class($fixtures) {
                    private array $fixtures;

                    public function __construct(array $fixtures)
                    {
                        $this->fixtures = $fixtures;
                    }

                    public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1, string $orderBy = ''): array
                    {
                        return $this->fixtures;
                    }

                    public function count(array $filter = []): int
                    {
                        return count($this->fixtures);
                    }
                };
            }
        };
    }
}
