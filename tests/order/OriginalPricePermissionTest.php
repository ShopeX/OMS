<?php

use PHPUnit\Framework\TestCase;

/**
 * 原价查看权限(original_price)单元测试
 *
 * 验证内容:
 * - desktop.xml 中 original_price 权限已注册
 * - ome_finder_orders::detail_basic / detail_goods 设置 show_original_price pagedata
 * - sales_finder_sales::detail_edit 设置 show_original_price pagedata
 * - ome_mdl_orders::orderItemsExportTitle 无权限时隐藏"详情商品原价"列
 * - ome_mdl_orders::getExportDetailV2 无权限时 e_price 置为 "-"
 * - 视图模板中 price 字段已加 {if $show_original_price} 判断
 *
 * 依据规范: 仅引入 config/config.php + app/base/defined.php + app/base/kernel.php
 */
class OriginalPricePermissionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        include_once 'config/config.php';
        include_once 'app/base/defined.php';
        include_once 'app/base/kernel.php';
    }

    /**
     * desktop.xml 中应注册 original_price 权限
     */
    public function testDesktopXmlRegistersOriginalPricePermission(): void
    {
        $xml = file_get_contents(dirname(__FILE__) . '/../../app/ome/desktop.xml');
        $this->assertStringContainsString(
            '<permission id="original_price">原价查看</permission>',
            $xml,
            'desktop.xml 必须注册 original_price 权限'
        );
    }

    /**
     * ome_finder_orders 文件中 detail_basic 应设置 show_original_price
     */
    public function testFinderOrdersDetailBasicSetsShowOriginalPrice(): void
    {
        $code = file_get_contents(dirname(__FILE__) . '/../../app/ome/lib/finder/orders.php');

        // detail_basic 内应包含 original_price 权限判断
        $detailBasicStart = strpos($code, 'function detail_basic(');
        $this->assertNotFalse($detailBasicStart, 'detail_basic 方法必须存在');

        $detailGoodsStart = strpos($code, 'function detail_goods(');
        $section = substr($code, $detailBasicStart, $detailGoodsStart - $detailBasicStart);

        $this->assertStringContainsString(
            "has_permission('original_price')",
            $section,
            'detail_basic 必须调用 original_price 权限判断'
        );
        $this->assertStringContainsString(
            "pagedata['show_original_price'] = \$showOriginalPrice",
            $section,
            'detail_basic 必须把 show_original_price 设置到 pagedata'
        );
    }

    /**
     * ome_finder_orders 文件中 detail_goods 应设置 show_original_price
     */
    public function testFinderOrdersDetailGoodsSetsShowOriginalPrice(): void
    {
        $code = file_get_contents(dirname(__FILE__) . '/../../app/ome/lib/finder/orders.php');

        $detailGoodsStart = strpos($code, 'function detail_goods(');
        $this->assertNotFalse($detailGoodsStart, 'detail_goods 方法必须存在');

        // 取 detail_goods 方法体到下一个 function 之前
        $nextFn = strpos($code, "\n    function ", $detailGoodsStart + 10);
        $section = $nextFn !== false
            ? substr($code, $detailGoodsStart, $nextFn - $detailGoodsStart)
            : substr($code, $detailGoodsStart);

        $this->assertStringContainsString(
            "has_permission('original_price')",
            $section,
            'detail_goods 必须调用 original_price 权限判断'
        );
        $this->assertStringContainsString(
            "pagedata['show_original_price'] = \$showOriginalPrice",
            $section,
            'detail_goods 必须把 show_original_price 设置到 pagedata'
        );
    }

    /**
     * sales_finder_sales::detail_edit 应设置 show_original_price
     */
    public function testFinderSalesDetailEditSetsShowOriginalPrice(): void
    {
        $code = file_get_contents(dirname(__FILE__) . '/../../app/sales/lib/finder/sales.php');

        $detailEditStart = strpos($code, 'function detail_edit(');
        $this->assertNotFalse($detailEditStart, 'detail_edit 方法必须存在');

        $nextFn = strpos($code, "\n    function ", $detailEditStart + 10);
        $section = $nextFn !== false
            ? substr($code, $detailEditStart, $nextFn - $detailEditStart)
            : substr($code, $detailEditStart);

        $this->assertStringContainsString(
            "has_permission('original_price')",
            $section,
            'detail_edit 必须调用 original_price 权限判断'
        );
        $this->assertStringContainsString(
            "pagedata['show_original_price'] = \$showOriginalPrice",
            $section,
            'detail_edit 必须把 show_original_price 设置到 pagedata'
        );
    }

    /**
     * ome_mdl_orders::orderItemsExportTitle 应在无权限时隐藏"详情商品原价"列
     */
    public function testOrderItemsExportTitleHidesOriginalPriceColumn(): void
    {
        $code = file_get_contents(dirname(__FILE__) . '/../../app/ome/model/orders.php');

        $methodStart = strpos($code, 'public function orderItemsExportTitle()');
        $this->assertNotFalse($methodStart, 'orderItemsExportTitle 方法必须存在');

        $methodEnd = strpos($code, "\n    }\n", $methodStart);
        $section = substr($code, $methodStart, $methodEnd - $methodStart);

        $this->assertStringContainsString(
            "'详情商品原价'",
            $section,
            'orderItemsExportTitle 必须包含"详情商品原价"列定义'
        );
        $this->assertStringContainsString(
            "has_permission('original_price')",
            $section,
            'orderItemsExportTitle 必须对 original_price 权限做判断'
        );
        $this->assertStringContainsString(
            "unset(\$items_title['详情商品原价'])",
            $section,
            '无 original_price 权限时必须 unset "详情商品原价" 列'
        );
    }

    /**
     * ome_mdl_orders::getExportDetailV2 应在无权限时把 e_price 置为 "-"
     */
    public function testGetExportDetailV2MasksOriginalPriceWhenNoPermission(): void
    {
        $code = file_get_contents(dirname(__FILE__) . '/../../app/ome/model/orders.php');

        $methodStart = strpos($code, 'public function getExportDetailV2(');
        $this->assertNotFalse($methodStart, 'getExportDetailV2 方法必须存在');

        $methodEnd = strpos($code, "\n    }\n", $methodStart);
        // getExportDetailV2 是类的最后一个方法, 适配结尾
        if ($methodEnd === false) {
            $methodEnd = strpos($code, "\n}", $methodStart);
        }
        $section = substr($code, $methodStart, $methodEnd - $methodStart);

        $this->assertStringContainsString(
            "\$showOriginalPrice = kernel::single('desktop_user')->has_permission('original_price')",
            $section,
            'getExportDetailV2 必须读取 original_price 权限状态到 \$showOriginalPrice'
        );
        $this->assertStringContainsString(
            "\$showOriginalPrice ? \$iv['price'] : '-'",
            $section,
            'getExportDetailV2 必须在无权限时把 e_price 置为 "-"'
        );
    }

    /**
     * goods_view.html 中原价字段必须受 show_original_price 控制
     */
    public function testGoodsViewHtmlWrapsOriginalPrice(): void
    {
        $html = file_get_contents(dirname(__FILE__) . '/../../app/ome/view/admin/order/products/goods_view.html');
        $this->assertStringContainsString(
            '<{if $show_original_price}><{$item.price|cur}><{else}>-<{/if}>',
            $html,
            'goods_view.html 中原价字段必须受 show_original_price 控制'
        );
    }

    /**
     * gift_view.html 中两处原价字段必须受 show_original_price 控制
     */
    public function testGiftViewHtmlWrapsOriginalPrice(): void
    {
        $html = file_get_contents(dirname(__FILE__) . '/../../app/ome/view/admin/order/products/gift_view.html');

        // 赠品 object 行的原价
        $this->assertStringContainsString(
            '<{if $show_original_price}><{$obj.price}><{else}>-<{/if}>',
            $html,
            'gift_view.html 中 obj.price 字段必须受 show_original_price 控制'
        );

        // 赠品 item 行的原价
        $this->assertStringContainsString(
            '<{if $show_original_price}><{$item.price}><{else}>-<{/if}>',
            $html,
            'gift_view.html 中 item.price 字段必须受 show_original_price 控制'
        );
    }

    /**
     * sales/view/detail.html 中两处原价字段必须受 show_original_price 控制
     */
    public function testSalesDetailHtmlWrapsOriginalPrice(): void
    {
        $html = file_get_contents(dirname(__FILE__) . '/../../app/sales/view/detail.html');

        // 销售明细 dataitem 行的原价
        $this->assertStringContainsString(
            '<{if $show_original_price}><{$dataitem.price|cur}><{else}>-<{/if}>',
            $html,
            'sales/view/detail.html 中 dataitem.price 字段必须受 show_original_price 控制'
        );

        // 子项 item 行的原价
        $this->assertStringContainsString(
            '<{if $show_original_price}><{$item.price}><{else}>-<{/if}>',
            $html,
            'sales/view/detail.html 中 item.price 字段必须受 show_original_price 控制'
        );
    }

    /**
     * 原价总额 modifier_cost_item 仍受 sale_price 控制(原价总额=成本, 归属 sale_price 合理)
     * 设计报告第 8 条: 保持现状, 不改用 original_price
     */
    public function testModifierCostItemKeepsUsingSalePricePermission(): void
    {
        $code = file_get_contents(dirname(__FILE__) . '/../../app/ome/model/orders.php');

        $methodStart = strpos($code, 'function modifier_cost_item(');
        $this->assertNotFalse($methodStart, 'modifier_cost_item 方法必须存在');

        $methodEnd = strpos($code, "\n    }\n", $methodStart);
        $section = substr($code, $methodStart, $methodEnd - $methodStart);

        // 仍使用 sale_price 控制(按设计报告保持现状)
        $this->assertStringContainsString(
            "has_permission('sale_price')",
            $section,
            'modifier_cost_item 应保持使用 sale_price 权限(原价总额=成本, 归属 sale_price)'
        );
        // 不应改用 original_price
        $this->assertStringNotContainsString(
            "has_permission('original_price')",
            $section,
            'modifier_cost_item 不应改用 original_price 权限'
        );
    }
}
