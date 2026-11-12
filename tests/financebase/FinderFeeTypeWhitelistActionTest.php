<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * 费用项设置（SCRUM-7828）行操作「设置白名单」入口契约测试。
 *
 * 背景（整页复测实测缺陷）：
 *   finder 原判据只用 `$row['shop_id']` 去 `shop_list`（按 shop_id 建索引）里查，
 *   但 Vue 契约下列表回包把 dbschema `table:shop@ome` 关联列**渲染成了展示名（店铺名）**，
 *   原始 32 位 id 已不在行里 ⇒ 匹配永远落空 ⇒ 「设置白名单」在 Vue 页**永不出现**
 *   （legacy 页同一数据有 10 个入口，Vue 页 0 个；实测取证于 2026-10-03）。
 *
 * 修复：`resolveShop()` 先按 shop_id 命中（legacy 行不受影响），未命中再按**店铺名**反查。
 * 本测试同时锁死两种行形态与负例，防止回归。
 */
class FinderFeeTypeWhitelistActionTest extends TestCase
{
    private const FINDER_FILE = '/app/financebase/lib/finder/bill/fee/type.php';

    /** @var object */
    private $finder;

    protected function setUp(): void
    {
        parent::setUp();
        require_once __DIR__ . '/../../' . ltrim(self::FINDER_FILE, '/');
        $this->finder = new financebase_finder_bill_fee_type();
        $this->finder->setVueMode(true);
        // 直接注入店铺缓存，避免依赖 financebase_func/getShopList（不引框架）
        $this->finder->shop_list = [
            'taobao-fx-id' => [
                'shop_id'       => 'taobao-fx-id',
                'name'          => '测试淘宝店铺7771',
                'shop_type'     => 'taobao',
                'business_type' => 'fx',
            ],
            'jd-zx-id' => [
                'shop_id'       => 'jd-zx-id',
                'name'          => '商派京东测试店铺',
                'shop_type'     => '360buy',
                'business_type' => 'zx',
            ],
        ];
    }

    /**
     * @return array<string,array{0:array<string,mixed>,1:array}>
     */
    public static function rowProvider(): array
    {
        $row = static fn (string $shopId): array => [
            'fee_type_id' => 7,
            'shop_id'     => $shopId,
            'rule_id'     => 1,
            'bill_type'   => 0,
        ];

        return [
            'legacy 行：原始 32 位 shop_id ⇒ 出「设置白名单」' => [$row('taobao-fx-id'), ['setWhitelist']],
            'Vue 行：关联列渲染成店铺名 ⇒ 同样出「设置白名单」（修复点）' => [$row('测试淘宝店铺7771'), ['setWhitelist']],
            '非 taobao 店铺（展示名）⇒ 空' => [$row('商派京东测试店铺'), []],
            '非 taobao 店铺（原始 id）⇒ 空' => [$row('jd-zx-id'), []],
            '未知店铺名 ⇒ 空（不得误命中）' => [$row('不存在的店铺'), []],
            'shop_id 缺失 ⇒ 空（不得告警/命中）' => [['fee_type_id' => 7], []],
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @param array<int,string>   $expectedActions
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('rowProvider')]
    public function testColumnEditWhitelistAction(array $row, array $expectedActions): void
    {
        $actions = $this->finder->column_edit($row);
        $this->assertSame(
            $expectedActions,
            array_map(static fn (array $a): string => (string) $a['action'], $actions),
            'column_edit 的 vue 结构化行操作不符合预期'
        );

        if ($expectedActions !== []) {
            $this->assertSame('设置白名单', $actions[0]['label']);
            $this->assertSame(7, $actions[0]['fee_type_id']);
        }
    }

    /**
     * 冻结：legacy（非 vue）分支仍按原逻辑出 HTML 片段，不因本次修复被改写。
     */
    public function testLegacyBranchStillEmitsHtml(): void
    {
        require_once __DIR__ . '/../../' . ltrim(self::FINDER_FILE, '/');
        $legacy = new financebase_finder_bill_fee_type();
        $legacy->setVueMode(false);
        $legacy->shop_list = $this->finder->shop_list;
        $_GET['_finder'] = ['finder_id' => 'unit-test'];

        $html = $legacy->column_edit(['fee_type_id' => 7, 'shop_id' => 'taobao-fx-id']);
        $this->assertStringContainsString('act=setWhiteList', $html, 'legacy 分支应仍输出原始 HTML 链接');
        $this->assertStringContainsString('设置白名单', $html);

        $none = $legacy->column_edit(['fee_type_id' => 7, 'shop_id' => 'jd-zx-id']);
        $this->assertSame('', $none, '非 taobao+fx 的 legacy 行应返回空串');
    }
}
