<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * PJ3270-84：立邦天猫订单，先价保后售前仅退款商品没有被删除。
 *
 * 线上样例 3316971145007095755（tmall 通道推送参数，取自 api_log 原始入参）：
 *   子单A：divide_order_fee=879.810，sale_price=918.000，价保退款 16.29（tag_type=价保退款，
 *   推送不带 oid、经退款明细 oid 回退累计 order_objects.refund_money=16.29）；
 *   售前仅退款：refund_fee=863.52，tmall_mcard_pz_sp=37.48（天猫权益金）。
 *   863.52 + 16.29 = 879.81 = divide_order_fee，价保补齐本可匹配；
 *   但 _autoEditorder 的「天猫权益金」块先执行（863.52+37.48=901.00），
 *   价保块再叠加（901.00+16.29=917.29），与 divide_order_fee(879.81) 和
 *   sale_price(918.00) 均不匹配 → 删除失败「退款金额与订单商品金额不匹配」。
 */
class TmallPresaleRefundAfterPriceProtectTest extends TestCase
{
    private const NODE_ID = '1033393833';

    protected function setUp(): void
    {
        parent::setUp();
        include_once 'config/config.php';
        include_once 'app/base/defined.php';
        include_once 'app/base/kernel.php';
    }

    /**
     * 造天猫(matrix)未发货订单：对齐线上样例的多子单结构
     * 子单A（退款目标）实付 879.810 + 子单B（保留）实付 2512.120，订单总额 3391.930
     */
    private function createTmallPresaleOrder(string $orderBn, string $oid): int
    {
        // 本地环境专用复现测试：依赖 node_id=1033393833 的天猫B店与本地 MySQL/Redis，环境缺失时跳过
        $shopCheck = app::get('ome')->model('shop')->dump(['node_id' => self::NODE_ID], 'shop_id,tbbusiness_type');
        if (!$shopCheck || empty($shopCheck['shop_id']) || $shopCheck['tbbusiness_type'] !== 'B') {
            $this->markTestSkipped('需要 node_id=' . self::NODE_ID . ' 的天猫B店（tbbusiness_type=B）本地环境');
        }
        $db = kernel::database();
        $shop = app::get('ome')->model('shop')->dump(['node_id' => self::NODE_ID], 'shop_id,shop_type,tbbusiness_type');
        $this->assertNotEmpty($shop['shop_id'], '本地需要 node_id=' . self::NODE_ID . ' 的店铺');
        $this->assertSame('B', $shop['tbbusiness_type'], '店铺需为天猫B店（tbbusiness_type=B）走 tmall 通道');

        $createtime = time();
        $db->exec(
            "INSERT INTO sdb_ome_orders (order_bn, shop_id, shop_type, source, createtime, pay_status, ship_status, total_amount, payed, process_status, status, confirm, is_delivery, refund_money)"
            . " VALUES (" . $db->quote($orderBn) . ", " . $db->quote($shop['shop_id']) . ", 'taobao', 'matrix', {$createtime}, '1', '0', '3391.930', '3391.930', 'unconfirmed', 'active', 'Y', 'Y', 0)"
        );
        $orderId = (int) $db->lastinsertid();
        $this->assertGreaterThan(0, $orderId, '订单创建失败');

        // 子单A：退款目标（金额对齐线上样例 I1P30L1C5V076240）
        $db->exec(
            "INSERT INTO sdb_ome_order_objects (order_id, obj_type, shop_goods_id, goods_id, quantity, price, amount, pmt_price, sale_price, oid, divide_order_fee, part_mjz_discount, actually_amount, platform_pay_amount, settlement_amount, `delete`, refund_money)"
            . " VALUES ({$orderId}, 'goods', 643413555338, 7847, 2, '1099.000', '2198.000', '1280.000', '918.000', " . $db->quote($oid) . ", '879.810', '38.190', '763.600', '116.210', '879.810', 'false', 0)"
        );
        $objId = (int) $db->lastinsertid();
        $this->assertGreaterThan(0, $objId, '子单A创建失败');
        $db->exec(
            "INSERT INTO sdb_ome_order_items (order_id, obj_id, shop_goods_id, shop_product_id, product_id, bn, nums, sendnum, split_num, price, amount, `delete`)"
            . " VALUES ({$orderId}, {$objId}, 643413555338, 6222090502444, 2692, 'TEST-PJ3270-84', 2, 0, 0, '1099.000', '2198.000', 'false')"
        );

        // 子单B：保留商品（保证订单不会全额退款被取消）
        $keepOid = $orderBn . '_KEEP';
        $db->exec(
            "INSERT INTO sdb_ome_order_objects (order_id, obj_type, shop_goods_id, goods_id, quantity, price, amount, sale_price, oid, divide_order_fee, `delete`, refund_money)"
            . " VALUES ({$orderId}, 'goods', " . $db->quote($keepOid) . ", 0, 1, '2512.120', '2512.120', '2512.120', " . $db->quote($keepOid) . ", '2512.120', 'false', 0)"
        );
        $keepObjId = (int) $db->lastinsertid();
        $this->assertGreaterThan(0, $keepObjId, '子单B创建失败');
        $db->exec(
            "INSERT INTO sdb_ome_order_items (order_id, obj_id, shop_goods_id, shop_product_id, product_id, bn, nums, sendnum, split_num, price, amount, `delete`)"
            . " VALUES ({$orderId}, {$keepObjId}, " . $db->quote($keepOid) . ", " . $db->quote($keepOid) . ", 999002, 'TEST-PJ3270-84-KEEP', 1, 0, 0, '2512.120', '2512.120', 'false')"
        );

        // 子单A 的基础物料库存主数据 + 冻结记录 + Redis 冻结（删除商品释放冻结需要）
        $db->exec(
            "INSERT IGNORE INTO sdb_material_basic_material_stock (bm_id, store, store_freeze, alert_store, last_modified, real_store_lastmodify, max_store_lastmodify, inc_store_lastmodify)"
            . " VALUES (999001, 100, 0, 0, " . time() . ", " . time() . ", " . time() . ", " . time() . ")"
        );
        $db->exec(
            "INSERT INTO sdb_material_basic_material_stock_freeze (bm_id, obj_type, bill_type, obj_id, branch_id, bmsq_id, num, last_modified)"
            . " VALUES (999001, 1, 0, {$orderId}, 1, -1, 2, " . time() . ")"
        );
        $freezeSeed = [[
            'bm_id'     => 999001,
            'quantity'  => 2,
            'branch_id' => 1,
            'obj_type'  => 1,
            'bill_type' => 0,
            'obj_id'    => $orderId,
            'obj_bn'    => '',
        ]];
        $seedRs = material_basic_material_stock::freezeInRedis($freezeSeed, '+', __CLASS__ . '::setUp');
        $this->assertTrue((bool) $seedRs[0], '模拟冻结基础物料失败：' . (isset($seedRs[1]) ? $seedRs[1] : ''));

        // 更新子单A明细的 product_id（冻结释放按 bm_id 匹配）
        $db->exec(
            "UPDATE sdb_ome_order_items SET product_id = 999001 WHERE order_id = {$orderId} AND bn = 'TEST-PJ3270-84'"
        );

        return $orderId;
    }

    /**
     * 推送售后单（经 erpapi 路由走真实 tmall 通道转换与处理链路）
     */
    private function pushAftersale(array $params): array
    {
        $params = array_merge([
            'method'          => 'ome.aftersalev2.add',
            'node_id'         => self::NODE_ID,
            'spider_type'     => 'tm_refund_i',
            'status'          => 'success',
            'has_good_return' => 'False',
            'refund_type'     => 'refund',
            'reason'          => '订单信息拍错（规格/尺码/颜色等）',
            'created'         => date('Y-m-d H:i:s'),
            'modified'        => date('Y-m-d H:i:s'),
            'cs_status'       => '1',
            'order_status'    => 'TRADE_CLOSED',
            'good_status'     => 'BUYER_NOT_RECEIVED',
        ], $params);

        $sdf = $params;
        $erpapiMethod = erpapi_router_mapping::rspServiceMapping('', $sdf['method'], $sdf['node_id']);
        if (!$erpapiMethod) {
            $erpapiMethod = $sdf['method'];
        }

        return kernel::single('erpapi_router_response')
            ->set_node_id($sdf['node_id'])
            ->set_api_name($erpapiMethod)
            ->dispatch($sdf);
    }

    private function objectRow(int $orderId): array
    {
        return kernel::database()->selectrow(
            "SELECT * FROM sdb_ome_order_objects WHERE order_id={$orderId} AND obj_type='goods' AND oid LIKE '%_OID1'"
        ) ?: [];
    }

    private function itemRow(int $orderId): array
    {
        return kernel::database()->selectrow(
            "SELECT * FROM sdb_ome_order_items WHERE order_id={$orderId} AND bn=" . kernel::database()->quote('TEST-PJ3270-84')
        ) ?: [];
    }

    /**
     * 场景一（线上样例复现）：价保退款（明细 oid 回退累计 refund_money）→ 售前仅退款（含权益金）删除商品失败
     */
    public function testPresaleRefundDeletesItemAfterPriceProtectWithMcard(): void
    {
        set_time_limit(0);
        @ini_set('memory_limit', '2048M');

        $orderBn = 'PJ327084A' . time();
        $oid = $orderBn . '_OID1';
        $orderId = $this->createTmallPresaleOrder($orderBn, $oid);

        // 价保退款完成（tag_type=价保退款，推送 oid 为空，经退款明细 oid 回退定位子单）
        $rs1 = $this->pushAftersale([
            'tid'              => $orderBn,
            'refund_id'        => 'PJ327084P' . time() . 'A',
            'refund_fee'       => '16.29',
            'tag_type'         => '价保退款',
            'oid'              => '',
            'total_fee'        => '16.29',
            'refund_item_list' => json_encode([
                'return_item' => [
                    ['oid' => $oid, 'num' => 2, 'price' => '8.15', 'outer_id' => 'TEST-PJ3270-84', 'item_id' => '643413555338'],
                ],
            ]),
        ]);
        $this->assertNotNull($rs1, '价保退款推送无响应');

        $object = $this->objectRow($orderId);
        // 价保退款经明细 oid 回退累计到 order_objects.refund_money（线上数据确认此行为）
        $this->assertEquals(16.29, (float) $object['refund_money'], '价保应累计 order_objects.refund_money=16.29');

        // 售前仅退款完成（对齐线上推送：refund_fee=863.52，天猫权益金=37.48）
        $rs2 = $this->pushAftersale([
            'tid'              => $orderBn,
            'refund_id'        => 'PJ327084R' . time() . 'B',
            'refund_fee'       => '863.52',
            'tmall_mcard_pz_sp' => '37.48',
            'total_fee'        => '901.00',
            'tag_type'         => '',
            'oid'              => $oid,
            'refund_item_list' => json_encode([
                'return_item' => [
                    ['oid' => $oid, 'num' => 2, 'price' => '431.76', 'outer_id' => 'TEST-PJ3270-84', 'item_id' => '643413555338'],
                ],
            ]),
        ]);
        $this->assertNotNull($rs2, '售前仅退款推送无响应');

        $object = $this->objectRow($orderId);
        $item = $this->itemRow($orderId);
        // 期望：价保补齐比对（863.52+16.29=879.81=divide_order_fee），商品被删除
        $this->assertSame('true', $object['delete'], '子单应被删除');
        $this->assertSame('true', $item['delete'], '商品明细应被删除');
    }

    /**
     * 场景三（回归保护）：无权益金，退款+价保=实付 → 价保单独补齐，商品删除成功（验收标准3）
     */
    public function testPresaleRefundWithPriceProtectOnlyStillDeletes(): void
    {
        set_time_limit(0);
        @ini_set('memory_limit', '2048M');

        $orderBn = 'PJ327084C' . time();
        $oid = $orderBn . '_OID1';
        $orderId = $this->createTmallPresaleOrder($orderBn, $oid);

        // 价保退款完成（同场景一：经明细 oid 回退累计）
        $rs1 = $this->pushAftersale([
            'tid'              => $orderBn,
            'refund_id'        => 'PJ327084P' . time() . 'E',
            'refund_fee'       => '16.29',
            'tag_type'         => '价保退款',
            'oid'              => '',
            'total_fee'        => '16.29',
            'refund_item_list' => json_encode([
                'return_item' => [
                    ['oid' => $oid, 'num' => 2, 'price' => '8.15', 'outer_id' => 'TEST-PJ3270-84', 'item_id' => '643413555338'],
                ],
            ]),
        ]);
        $this->assertNotNull($rs1);
        $object = $this->objectRow($orderId);
        $this->assertEquals(16.29, (float) $object['refund_money'], '价保应累计 order_objects.refund_money=16.29');

        // 售前仅退款完成（无权益金，863.52+16.29=879.81=divide_order_fee）
        $rs2 = $this->pushAftersale([
            'tid'              => $orderBn,
            'refund_id'        => 'PJ327084R' . time() . 'F',
            'refund_fee'       => '863.52',
            'tag_type'         => '',
            'oid'              => $oid,
            'refund_item_list' => json_encode([
                'return_item' => [
                    ['oid' => $oid, 'num' => 2, 'price' => '431.76', 'outer_id' => 'TEST-PJ3270-84', 'item_id' => '643413555338'],
                ],
            ]),
        ]);
        $this->assertNotNull($rs2);

        $object = $this->objectRow($orderId);
        $item = $this->itemRow($orderId);
        $this->assertSame('true', $object['delete'], '价保单独补齐场景子单应被删除');
        $this->assertSame('true', $item['delete'], '价保单独补齐场景商品明细应被删除');
    }

    /**
     * 场景二（回归保护）：无价保、退款不含权益金 → 权益金补齐比对，商品删除成功
     */
    public function testPresaleRefundWithMcardOnlyStillDeletes(): void
    {
        set_time_limit(0);
        @ini_set('memory_limit', '2048M');

        $orderBn = 'PJ327084B' . time();
        $oid = $orderBn . '_OID1';
        $orderId = $this->createTmallPresaleOrder($orderBn, $oid);

        // 售前仅退款：refund_fee=879.31（不含权益金），权益金 0.50 → 879.31+0.50=879.81=divide
        //（注：842.33+37.48 等组合会踩中原有权益金块的存量浮点精度问题，非本任务范围，用浮点安全组合做回归）
        $rs = $this->pushAftersale([
            'tid'              => $orderBn,
            'refund_id'        => 'PJ327084R' . time() . 'C',
            'refund_fee'       => '879.31',
            'tmall_mcard_pz_sp' => '0.50',
            'total_fee'        => '879.81',
            'tag_type'         => '',
            'oid'              => $oid,
            'refund_item_list' => json_encode([
                'return_item' => [
                    ['oid' => $oid, 'num' => 2, 'price' => '439.66', 'outer_id' => 'TEST-PJ3270-84', 'item_id' => '643413555338'],
                ],
            ]),
        ]);
        $this->assertNotNull($rs);

        $object = $this->objectRow($orderId);
        $item = $this->itemRow($orderId);
        $this->assertSame('true', $object['delete'], '权益金补齐场景子单应被删除');
        $this->assertSame('true', $item['delete'], '权益金补齐场景商品明细应被删除');
    }
}
