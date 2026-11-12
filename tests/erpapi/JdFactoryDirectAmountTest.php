<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * SCRUM-7981：京东厂直订单商家结算 / 客户实付按数量合计
 */
#[PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
final class JdFactoryDirectAmountTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testJdAnalysisMultipliesUnitAmountByQuantity(): void
    {
        $source = file_get_contents($this->root . '/app/erpapi/lib/shop/matrix/jd/response/order.php');
        $this->assertNotFalse($source);

        $pos = strpos($source, 'function _analysis(');
        $this->assertNotFalse($pos);
        $snippet = substr($source, $pos, 4000);

        $this->assertStringContainsString("coupon_actuallypay_field'] = \$field = 'extend_item_list/cost'", $snippet);
        $this->assertStringContainsString("floatval(\$unitActually) * \$qty", $snippet);
        $this->assertStringContainsString("\$purchasePrice * \$qty", $snippet);
        $this->assertStringContainsString("floatval(\$itemCost) * \$itemQty", $snippet);
        $this->assertStringContainsString("floatval(\$item['purchase_price'] ?? 0) * \$itemQty", $snippet);
    }

    /**
     * 样例单：cost=2100、purchase_price=2100、quantity=4 → 8400.00
     */
    public function testSamplePayloadYields8400(): void
    {
        $object = array(
            'purchase_price' => 2100,
            'quantity' => 4,
            'order_items' => array(
                array(
                    'purchase_price' => 2100,
                    'quantity' => 4,
                    'extend_item_list' => array('cost' => '2100.0'),
                ),
            ),
        );

        $mapped = $this->mapJdFactoryDirectAmounts($object);

        $this->assertSame('8400.00', $mapped['actually_amount']);
        $this->assertSame('8400.00', $mapped['settlement_amount']);
        $this->assertSame('8400.00', $mapped['order_items'][0]['actually_amount']);
        $this->assertSame('8400.00', $mapped['order_items'][0]['settlement_amount']);
    }

    public function testMissingQuantityKeepsUnitAmount(): void
    {
        $object = array(
            'purchase_price' => 2100,
            'order_items' => array(
                array(
                    'purchase_price' => 2100,
                    'extend_item_list' => array('cost' => '2100.0'),
                ),
            ),
        );

        $mapped = $this->mapJdFactoryDirectAmounts($object);

        $this->assertSame('2100.00', $mapped['actually_amount']);
        $this->assertSame('2100.00', $mapped['settlement_amount']);
    }

    /**
     * 与 erpapi_shop_matrix_jd_response_order::_analysis 同口径的纯函数映射
     *
     * @param array<string, mixed> $object
     * @return array<string, mixed>
     */
    private function mapJdFactoryDirectAmounts(array $object): array
    {
        $arrField = array('extend_item_list', 'cost');
        $qty = (int)($object['quantity'] ?? ($object['items_num'] ?? 0));
        if ($qty <= 0 && !empty($object['order_items'])) {
            $first = current($object['order_items']);
            $qty = (int)($first['quantity'] ?? ($first['num'] ?? 1));
        }
        if ($qty <= 0) {
            $qty = 1;
        }
        $unitActually = current($object['order_items'])[$arrField[0]][$arrField[1]];
        $object['actually_amount'] = sprintf('%.2f', floatval($unitActually) * $qty);
        $purchasePrice = floatval($object['purchase_price'] ?? 0);
        if ($purchasePrice <= 0 && !empty($object['order_items'])) {
            $firstItem = current($object['order_items']);
            $purchasePrice = floatval($firstItem['purchase_price'] ?? 0);
        }
        $object['settlement_amount'] = sprintf('%.2f', $purchasePrice * $qty);
        foreach ($object['order_items'] as $k => $item) {
            $itemQty = (int)($item['quantity'] ?? ($item['num'] ?? $qty));
            if ($itemQty <= 0) {
                $itemQty = 1;
            }
            $itemCost = $item[$arrField[0]][$arrField[1]] ?? $unitActually;
            $item['actually_amount'] = sprintf('%.2f', floatval($itemCost) * $itemQty);
            $item['settlement_amount'] = sprintf('%.2f', floatval($item['purchase_price'] ?? 0) * $itemQty);
            $object['order_items'][$k] = $item;
        }

        return $object;
    }
}
