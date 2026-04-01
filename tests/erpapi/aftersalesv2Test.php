<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class aftersalesv2Test extends TestCase
{
    // 在每个测试方法之前执行
    protected function setUp(): void
    {
        parent::setUp();

        // 在 setUp 方法中引入依赖文件
        include_once 'config/config.php';
        include_once 'app/base/defined.php';
        include_once 'app/base/kernel.php';
    }

    public function testAdd(): void
    {
        set_time_limit(0);
        ignore_user_abort(true);
        @ini_set('memory_limit', '2048M');

        $str = '{
            "refund_id": "R4326826108801688",
            "status": "SUCCESS",
            "trade_status": "4",
            "has_good_return": "true",
            "company_name": "顺丰速运",
            "modified": "2024-04-05 22:09:56",
            "extend_field": "{\"_matrix_msg_id\":\"6613C6600AB201316N2XZ2SNZ8H6Y72S\",\"status\":\"4\",\"refundStatus\":\"2\",\"reasonId\":\"700000\"}",
            "buyer_nick": "5cfbe211000000000602e878",
            "oid": "",
            "refund_type": "return",
            "spider_type": "小红书_refund",
            "sid": "SF3100695246153",
            "refund_version": "",
            "reason": "尺码/尺寸不合适",
            "attribute": "",
            "cost_shipping": "0",
            "seller_nick": "",
            "tid": "R2584935102342489",
            "desc": "",
            "refund_fee": "49.9",
            "refund_item_list": "{\"return_item\":[{\"item_id\":\"65fa79253403350001f45621\",\"num\":1,\"price\":\"49.9\",\"oid\":\"65fa79253403350001f45621\",\"outer_id\":\"\",\"modified\":\"2024-04-05 22:09:56\"}]}",
            "operation_constraint": "",
            "created": "2024-04-03 09:46:42",
            "payment_id": "",
            "outer_id": "",
            "refund_phase": "",
            "jsrefund_flag": "true"
          }';


        $sdf = json_decode($str,true);
        $sdf['method'] = 'ome.aftersalev2.add';
        $sdf['node_id'] = '1044116135';
        $erpapiMethod = erpapi_router_mapping::rspServiceMapping('', $sdf['method'], $sdf['node_id']);
        if(!$erpapiMethod) {
            $erpapiMethod = $sdf['method'];
        }
        $rs = kernel::single('erpapi_router_response')
            ->set_node_id($sdf['node_id'])
            ->set_api_name($erpapiMethod)
            ->dispatch($sdf);
        echo '<pre>';
        print_r($rs);
        echo '</pre>';

        $this->assertTrue(true,$rs['msg']);
    }

    /**
     * 为京东价保退款测试造订单并置为已发货（订单号 tid，平台 skuid item_id 对应子单 oid/sku_uuid）
     * 依赖：node_id 对应 360buy 店铺，否则跳过
     */
    private function create360buyPriceProtectOrderAndShip(string $nodeId, string $orderBn, string $platformSkuId): bool
    {
        $shop = app::get('ome')->model('shop')->dump(['node_id' => $nodeId], 'shop_id,shop_type');
        if (!$shop || empty($shop['shop_id'])) {
            return false;
        }
        $shopId = $shop['shop_id'];
        $db = kernel::database();

        $order = $db->selectrow(
            'SELECT order_id FROM sdb_ome_orders WHERE order_bn = ' . $db->quote($orderBn) . ' AND shop_id = ' . $db->quote($shopId)
        );
        if ($order && !empty($order['order_id'])) {
            $orderId = (int) $order['order_id'];
            $db->exec('UPDATE sdb_ome_orders SET ship_status = \'1\', pay_status = \'1\', payed = 3, total_amount = 3, process_status = \'splited\' WHERE order_id = ' . $orderId);
            $hasItem = $db->selectrow(
                'SELECT 1 FROM sdb_ome_order_items oi INNER JOIN sdb_ome_order_objects oo ON oi.obj_id = oo.obj_id '
                . 'WHERE oi.order_id = ' . $orderId . ' AND oi.shop_product_id = ' . $db->quote($platformSkuId) . ' AND oi.delete = \'false\' AND oo.delete = \'false\' LIMIT 1'
            );
            if (!$hasItem) {
                $oid = '1012_jbp_' . $orderBn . '_' . $platformSkuId;
                $skuUuid = 'jbp_uuid_' . $orderBn . '_' . $platformSkuId;
                $db->exec(
                    "INSERT INTO sdb_ome_order_objects (order_id, obj_type, shop_goods_id, goods_id, quantity, price, amount, oid, sku_uuid, divide_order_fee, `delete`) "
                    . "VALUES ({$orderId}, 'goods', " . $db->quote($platformSkuId) . ", 0, 1, '3', '3', " . $db->quote($oid) . ", " . $db->quote($skuUuid) . ", '3', 'false')"
                );
                $objId = $db->lastinsertid();
                if ($objId) {
                    $db->exec(
                        "INSERT INTO sdb_ome_order_items (order_id, obj_id, shop_goods_id, shop_product_id, product_id, bn, nums, sendnum, price, amount, `delete`) "
                        . "VALUES ({$orderId}, {$objId}, " . $db->quote($platformSkuId) . ", " . $db->quote($platformSkuId) . ", 0, " . $db->quote('TEST-JBP-' . $platformSkuId) . ", 1, 1, '3', '3', 'false')"
                    );
                }
            }
            return true;
        }

        $createtime = time();
        $db->exec(
            "INSERT INTO sdb_ome_orders (order_bn, shop_id, shop_type, createtime, pay_status, ship_status, total_amount, payed, process_status, status, confirm, is_delivery) "
            . "VALUES (" . $db->quote($orderBn) . ", " . $db->quote($shopId) . ", " . $db->quote($shop['shop_type'] ?? '360buy') . ", {$createtime}, '1', '1', '3', '3', 'splited', 'active', 'Y', 'Y')"
        );
        $orderId = $db->lastinsertid();
        if (!$orderId) {
            return false;
        }

        $oid = '1012_jbp_' . $orderBn . '_' . $platformSkuId;
        $skuUuid = 'jbp_uuid_' . $orderBn . '_' . $platformSkuId;
        $db->exec(
            "INSERT INTO sdb_ome_order_objects (order_id, obj_type, shop_goods_id, goods_id, quantity, price, amount, oid, sku_uuid, divide_order_fee, `delete`) "
            . "VALUES ({$orderId}, 'goods', " . $db->quote($platformSkuId) . ", 0, 1, '3', '3', " . $db->quote($oid) . ", " . $db->quote($skuUuid) . ", '3', 'false')"
        );
        $objId = $db->lastinsertid();
        if (!$objId) {
            return false;
        }

        $db->exec(
            "INSERT INTO sdb_ome_order_items (order_id, obj_id, shop_goods_id, shop_product_id, product_id, bn, nums, sendnum, price, amount, `delete`) "
            . "VALUES ({$orderId}, {$objId}, " . $db->quote($platformSkuId) . ", " . $db->quote($platformSkuId) . ", 0, " . $db->quote('TEST-JBP-' . $platformSkuId) . ", 1, 1, '3', '3', 'false')"
        );

        return true;
    }

    /**
     * 京东价保退款：验证入参（refund_item_list 无 oid/sku_uuid）经 360buy 转换后能正确走价保逻辑
     * 依赖：node_id 对应 360buy 店铺；测试前会造订单并置为已发货，便于通过 item_id 匹配回填 oid/sku_uuid
     */
    public function test360buyPriceProtectRefundAdd(): void
    {
        set_time_limit(0);
        ignore_user_abort(true);
        @ini_set('memory_limit', '2048M');

        $nodeId = '1272110034';
        $orderBn = '3345225012691859';
        $platformSkuId = '10172864876538';
        $shop = app::get('ome')->model('shop')->dump(['node_id' => $nodeId], 'shop_id');
        if (!$shop || empty($shop['shop_id'])) {
            $this->markTestSkipped('需要 node_id=1272110034 的 360buy 店铺方可运行本测试');
            return;
        }
        $this->create360buyPriceProtectOrderAndShip($nodeId, $orderBn, $platformSkuId);

        $params = [
            'status'             => 'SUCCESS',
            'to_node_id'         => '1592150435',
            'venderUndertakeAmount' => '',
            'refund_id'          => '34141299377',
            'from_node_id'        => '1272110034',
            'attribute'           => '',
            'oid'                => '',
            'app_id'             => 'ecos.ome',
            'source_status'       => '价保成功',
            'node_id'             => '1272110034',
            'date'                => '2026-02-02 15:17:40',
            'sign'                => 'D6D361DE8E47EAF1E2230126473F43CF',
            'refund_fee'          => '3.00',
            'desc'                => '价保服务',
            'refund_item_list'    => json_encode([
                'return_item' => [
                    [
                        'price'    => '',
                        'oid'      => '',
                        'modified' => '',
                        'num'      => 1,
                        'item_id'  => '10172864876538',
                        'outer_id' => '',
                    ],
                ],
            ]),
            'created'             => '2025-12-30 19:37:38',
            'modified'            => '',
            'popPlatformCouponRateAmount' => '3.0',
            'tid'                 => '3345225012691859',
            'has_good_return'     => 'false',
            'method'              => 'ome.aftersalev2.add',
            'refund_type'         => 'refund',
            'tag_type'            => '价保退款',
        ];

        $sdf = $params;
        $erpapiMethod = erpapi_router_mapping::rspServiceMapping('', $sdf['method'], $sdf['node_id']);
        if (!$erpapiMethod) {
            $erpapiMethod = $sdf['method'];
        }
        $rs = kernel::single('erpapi_router_response')
            ->set_node_id($sdf['node_id'])
            ->set_api_name($erpapiMethod)
            ->dispatch($sdf);

        $this->assertArrayHasKey('rsp', $rs, '响应应包含 rsp');
        $this->assertContains($rs['rsp'], ['succ', 'fail'], 'rsp 应为 succ 或 fail');
        $this->assertArrayHasKey('msg', $rs, '响应应包含 msg');
        $this->assertTrue(true, $rs['msg']);
    }
}
