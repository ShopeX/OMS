<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Vue Finder 快捷筛选「控件类型」口径契约测试（SCRUM-7858）。
 *
 * 权威：legacy app/desktop/lib/finder/builder/filter/render.php:40-120
 *   —— dbschema 的 filtertype 只是「可筛选」标记，控件类型由**列 type** 决定，
 *      仅 3 处特判（decimal+number / textarea / custom*）+ last_modify|TIMESTAMP→time。
 * 第二层（接线）：context.php 必须调用归一函数，不得再把 filtertype 当控件类型下发。
 *
 * ⚠️ 落位约定：该归一函数**单点使用**（唯一调用方 = desktop_finder_vue_context），故实现内联在
 *    宿主类 desktop_finder_vue_context::normalizeFilterControlType()（context.php），
 *    **不再拆出独立 lib 文件**（与列顺序契约同一约定）。
 */
#[RunTestsInSeparateProcesses]
class FinderVueFilterControlTypeTest extends TestCase
{
    /** context.php 绝对路径（宿主类所在文件） */
    private const CONTEXT_FILE = __DIR__ . '/../../app/desktop/lib/finder/vue/context.php';

    protected function setUp(): void
    {
        parent::setUp();
        if (!class_exists('desktop_finder_vue_context', false)) {
            require_once self::CONTEXT_FILE;
        }
    }

    /**
     * 期望值 = **前端契约**（src/api/finder/filterAdapter.ts normalizeFilterType 的 typeMap）。
     *
     * ⚠️ 折叠发生在 BE：非 FE token 的列类型（varchar(200)/longtext/money/int/region/intbool/timestamp…）
     *    与 legacy custom* 结果（view/custom）一律下发 'normal'，等价于前端 `?? "normal"` 兜底；
     *    这样不经 normalizeFilterDefinitions 的页面（如 crm/gift 自持 filterDefinitions）也有分支可匹配。
     *    ⚠️ 期望值必须随 FE typeMap 变更同步（FE 支持 'view'/'custom' 才可放开）。
     */
    public static function legacyControlTypeProvider(): array
    {
        return [
            '7800 货号 varchar + yes ⇒ normal（文本框）' => ['varchar(200)', 'yes', 'normal'],
            '基础物料 longtext + true ⇒ normal（文本框）' => ['longtext', true, 'normal'],
            'reship is_protect bool + yes ⇒ bool（→是/否下拉）' => ['bool', 'yes', 'bool'],
            'bool + normal ⇒ bool（→是/否下拉）' => ['bool', 'normal', 'bool'],
            'bool + true ⇒ bool' => ['bool', true, 'bool'],
            'bool + false ⇒ bool' => ['bool', false, 'bool'],
            'decimal + number ⇒ number' => ['decimal(20,3)', 'number', 'number'],
            'decimal + normal ⇒ normal（文本框）' => ['decimal(20,3)', 'normal', 'normal'],
            'filtertype=textarea ⇒ textarea' => ['varchar(255)', 'textarea', 'textarea'],
            'filtertype=custom_view ⇒ view 非 FE token ⇒ normal' => ['varchar(255)', 'custom_view', 'normal'],
            'filtertype=custom ⇒ custom 非 FE token ⇒ normal' => ['varchar(255)', 'custom', 'normal'],
            'last_modify ⇒ time' => ['last_modify', 'normal', 'time'],
            'TIMESTAMP（严格大写）⇒ time' => ['TIMESTAMP', 'normal', 'time'],
            'timestamp（小写，legacy 不识别）⇒ normal' => ['timestamp', 'normal', 'normal'],
            'time ⇒ time' => ['time', 'normal', 'time'],
            '枚举数组 ⇒ select' => [['0' => '停售', '1' => '在售'], 'yes', 'select'],
            'table: 跨表列 ⇒ 原样透传' => ['table:shop@ome', 'normal', 'table:shop@ome'],
            'money ⇒ normal（文本框）' => ['money', 'normal', 'normal'],
            'int ⇒ normal（文本框）' => ['int unsigned', 'yes', 'normal'],
            'region ⇒ normal（前端降级文本框）' => ['region', 'normal', 'normal'],
            'intbool ⇒ normal（非 FE token）' => ['intbool', 'normal', 'normal'],
        ];
    }

    #[DataProvider('legacyControlTypeProvider')]
    public function testNormalizeMatchesLegacyControlType($type, $filtertype, string $expected): void
    {
        $this->assertSame(
            $expected,
            desktop_finder_vue_context::normalizeFilterControlType($type, $filtertype)
        );
    }

    /**
     * 接线契约：契约层必须走归一函数（改前红 / 改后绿）。
     */
    public function testContextWiringUsesNormalizer(): void
    {
        $src = (string) file_get_contents(self::CONTEXT_FILE);

        $this->assertStringContainsString(
            'self::normalizeFilterControlType(',
            $src,
            'context.php 未接入快捷筛选控件类型归一函数（SCRUM-7858）'
        );
        $this->assertStringNotContainsString(
            '$filterType = $filtertype;',
            $src,
            'context.php 仍在把 filtertype 直接当控件类型下发（SCRUM-7858）'
        );
    }
}
