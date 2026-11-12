<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * PJ3270-85：立邦天猫订单，其中一个商品发货前价保后全额退款（删除商品），
 * 剩余商品继续发货完成生成的销售单客户实付被价保金额错误扣减。
 *
 * 线上样例 3316971145007095755：
 *   子单A（已删除）：refund_money=16.29（价保），主单 refund_money=16.29；
 *   子单B（存活）：actually_amount=2180.31（divide=2512.12）。
 *   销售单生成时 init() 移除子单A 后，差额分摊把 16.29 整体摊给子单B，
 *   客户实付 2180.31 被扣成 2164.02（发票对账 BLUE-1 合计与 16.29 精确吻合）。
 */
class TmallPriceProtectDeletedObjectSalesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        include_once 'config/config.php';
        include_once 'app/base/defined.php';
        include_once 'app/base/kernel.php';
    }

    /**
     * 造订单：已删除子单A（价保退款 16.29）+ 存活子单B（客户实付 2180.31）
     * 主单 refund_money=16.29（价保经 _updateProtectPrice 累计）
     */
    private function createOrder(string $orderBn, array $options = array()): int
    {
        $db = kernel::database();
        $shop = app::get('ome')->model('shop')->dump(['node_id' => '1033393833'], 'shop_id,shop_type');
        $this->assertNotEmpty($shop['shop_id'], '本地需要 node_id=1033393833 的店铺');

        $deletedRefund = isset($options['deleted_refund']) ? $options['deleted_refund'] : '16.29';
        $orderRefund = isset($options['order_refund']) ? $options['order_refund'] : '16.29';

        $createtime = time();
        $db->exec(
            "INSERT INTO sdb_ome_orders (order_bn, shop_id, shop_type, source, createtime, pay_status, ship_status, total_amount, payed, settlement_amount, process_status, status, confirm, is_delivery, refund_money)"
            . " VALUES (" . $db->quote($orderBn) . ", " . $db->quote($shop['shop_id']) . ", 'taobao', 'matrix', {$createtime}, '1', '0', '3391.930', '2512.120', '2512.120', 'splited', 'active', 'Y', 'Y', " . $db->quote($orderRefund) . ")"
        );
        $orderId = (int) $db->lastinsertid();
        $this->assertGreaterThan(0, $orderId, '订单创建失败');

        // 子单A：已删除（价保后售前全额退款），refund_money 记录价保
        $db->exec(
            "INSERT INTO sdb_ome_order_objects (order_id, obj_type, shop_goods_id, goods_id, quantity, price, amount, sale_price, oid, divide_order_fee, actually_amount, `delete`, refund_money)"
            . " VALUES ({$orderId}, 'goods', 643413555338, 7847, 2, '1099.000', '2198.000', '918.000', " . $db->quote($orderBn . '_OIDA') . ", '879.810', '763.600', 'true', " . $db->quote($deletedRefund) . ")"
        );
        $objAId = (int) $db->lastinsertid();
        $db->exec(
            "INSERT INTO sdb_ome_order_items (order_id, obj_id, shop_goods_id, shop_product_id, product_id, bn, nums, sendnum, split_num, price, amount, `delete`)"
            . " VALUES ({$orderId}, {$objAId}, 643413555338, 6222090502444, 2692, 'TEST-PJ3270-85-A', 2, 0, 0, '1099.000', '2198.000', 'true')"
        );

        // 子单B：存活，客户实付 2180.31（对齐线上 pkg 子单合计）
        $db->exec(
            "INSERT INTO sdb_ome_order_objects (order_id, obj_type, shop_goods_id, goods_id, quantity, price, amount, pmt_price, sale_price, oid, divide_order_fee, actually_amount, settlement_amount, `delete`, refund_money)"
            . " VALUES ({$orderId}, 'goods', 656929593704, 10124, 1, '5999.000', '5999.000', '3149.950', '2849.050', " . $db->quote($orderBn . '_OIDB') . ", '2512.120', '2180.310', '2512.120', 'false', 0)"
        );
        $objBId = (int) $db->lastinsertid();
        $this->assertGreaterThan(0, $objBId, '子单B创建失败');
        $db->exec(
            "INSERT INTO sdb_ome_order_items (order_id, obj_id, shop_goods_id, shop_product_id, product_id, bn, nums, sendnum, split_num, price, pmt_price, amount, sale_price, divide_order_fee, actually_amount, settlement_amount, platform_pay_amount, `delete`)"
            . " VALUES ({$orderId}, {$objBId}, 656929593704, 6111399582591, 2774, 'TEST-PJ3270-85-B', 2, 2, 2, '2549.580', '2677.460', '5099.150', '2421.700', '2512.120', '2180.310', '2512.120', '331.810', 'false')"
        );

        return $orderId;
    }

    /**
     * 造发货单 + 发货单订单关联 + 发货明细（子单B 已发货完成）
     */
    private function createDelivery(string $orderBn, int $orderId, int $objBId): int
    {
        $db = kernel::database();
        $deliveryBn = 'D' . $orderBn;
        $db->exec(
            "INSERT INTO sdb_ome_delivery (delivery_bn, branch_id, status, is_bind, disabled, pause, delivery_cost_actual, create_time)"
            . " VALUES (" . $db->quote($deliveryBn) . ", 1, 'succ', 'false', 'false', 'false', 0, " . time() . ")"
        );
        $deliveryId = (int) $db->lastinsertid();
        $this->assertGreaterThan(0, $deliveryId, '发货单创建失败');

        $db->exec(
            "INSERT INTO sdb_ome_delivery_order (order_id, delivery_id)"
            . " VALUES ({$orderId}, {$deliveryId})"
        );

        $itemRow = $db->selectrow("SELECT item_id FROM sdb_ome_order_items WHERE order_id={$orderId} AND bn='TEST-PJ3270-85-B'");
        $orderItemId = (int) $itemRow['item_id'];
        $db->exec(
            "INSERT INTO sdb_ome_delivery_items_detail (delivery_id, order_id, order_obj_id, order_item_id, product_id, bn, number, price, item_type)"
            . " VALUES ({$deliveryId}, {$orderId}, {$objBId}, {$orderItemId}, 2774, 'TEST-PJ3270-85-B', 2, '2549.580', 'product')"
        );

        return $deliveryId;
    }

    /**
     * 跑销售单数据生成链路（init + generate + price calculate）
     */
    private function generateSalesData(int $orderId, int $deliveryId): array
    {
        $original = kernel::single('ome_sales_original_data')->init($orderId);
        $this->assertNotFalse($original, '订单原始数据 init 失败');
        $salesData = kernel::single('ome_sales_data')->generate($original, $deliveryId);
        $this->assertNotFalse($salesData, '销售单数据 generate 失败');
        return $salesData;
    }

    private function itemAmount(array $salesData, string $bn): array
    {
        foreach ($salesData['sales_items'] as $item) {
            if ($item['bn'] === $bn) {
                return $item;
            }
        }
        $this->fail('销售明细未找到 bn=' . $bn);
    }

    /**
     * 场景一（线上样例复现）：删除子单带价保退款 → 存活子单客户实付不被扣减
     */
    public function testDeletedObjectRefundNotAllocatedToRemainingObject(): void
    {
        set_time_limit(0);
        @ini_set('memory_limit', '2048M');

        $orderBn = 'PJ327085A' . time();
        $orderId = $this->createOrder($orderBn);
        $objBId = (int) kernel::database()->selectrow(
            "SELECT obj_id FROM sdb_ome_order_objects WHERE order_id={$orderId} AND oid LIKE '%_OIDB'"
        )['obj_id'];
        $deliveryId = $this->createDelivery($orderBn, $orderId, $objBId);

        $salesData = $this->generateSalesData($orderId, $deliveryId);

        $item = $this->itemAmount($salesData, 'TEST-PJ3270-85-B');
        // 期望：存活子单客户实付保持 2180.31（价保 16.29 归属被删除子单，不再分摊）
        $this->assertEquals(2180.31, (float) $item['actually_amount'],
            '存活子单客户实付应为 2180.31，不应被价保 16.29 扣减');
    }

    /**
     * 场景二（回归保护）：无删除子单，主单退款大于存活子单退款合计 → 差额照常分摊
     */
    public function testGapAllocationStillWorksForNoDeletedObject(): void
    {
        set_time_limit(0);
        @ini_set('memory_limit', '2048M');

        $orderBn = 'PJ327085B' . time();
        // 已删除子单无退款；主单退款 50（模拟订单级未归属退款）
        $orderId = $this->createOrder($orderBn, ['deleted_refund' => '0.00', 'order_refund' => '50.00']);
        $objBId = (int) kernel::database()->selectrow(
            "SELECT obj_id FROM sdb_ome_order_objects WHERE order_id={$orderId} AND oid LIKE '%_OIDB'"
        )['obj_id'];
        $deliveryId = $this->createDelivery($orderBn, $orderId, $objBId);

        $salesData = $this->generateSalesData($orderId, $deliveryId);

        $item = $this->itemAmount($salesData, 'TEST-PJ3270-85-B');
        // 期望：差额 50 照常分摊给存活子单（2180.31 - 50 = 2130.31）
        $this->assertEquals(2130.31, (float) $item['actually_amount'],
            '无删除子单时订单级退款差额 50 应照常分摊给存活子单');
    }

    /**
     * 场景三（回归保护）：删除子单无退款、主单无退款 → 行为不变
     */
    public function testNoRefundNoChange(): void
    {
        set_time_limit(0);
        @ini_set('memory_limit', '2048M');

        $orderBn = 'PJ327085C' . time();
        $orderId = $this->createOrder($orderBn, ['deleted_refund' => '0.00', 'order_refund' => '0.00']);
        $objBId = (int) kernel::database()->selectrow(
            "SELECT obj_id FROM sdb_ome_order_objects WHERE order_id={$orderId} AND oid LIKE '%_OIDB'"
        )['obj_id'];
        $deliveryId = $this->createDelivery($orderBn, $orderId, $objBId);

        $salesData = $this->generateSalesData($orderId, $deliveryId);

        $item = $this->itemAmount($salesData, 'TEST-PJ3270-85-B');
        $this->assertEquals(2180.31, (float) $item['actually_amount'], '无退款场景客户实付应保持 2180.31');
    }
}
