<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Vue Finder 金额列币种展示契约（多币种前瞻）。
 *
 * 背景：列表通道经 resolveTypeModifiers() 走 dbschema type_modifier ⇒ 金额列已带币种符号
 * （'￥123.45'）；但 **detail 通道**（action=detail / finderview=items）直接吐模型原始行，
 * 金额列呈裸数值（'123.450'）⇒ 同一页列表与明细的金额展示不一致，多币种下更不可接受。
 *
 * 判据单一来源 = dbschema `type: 'money'`（用户口径：通过 dbschema 中 type=money 判断是否需要
 * 展示币种；与 legacy builder/view.php 的 type_modifier 同规），不猜列名。
 *
 * 落点：
 *   - desktop_finder_vue_context::applyMoneyModifiersToAllMoneyColumns() —— 列表通道（getAllColumns() 判据）
 *   - 同类 ::applyMoneyFormatter($rows, $columnSchema) —— 通用实现，detail 通道以 payload header 为判据复用
 *   - desktop_finder_vue_detail::formatMoneyColumns() —— detail 接线（body + lines.body）
 *
 * ⚠️ 幂等保护（本契约重点）：desktop_finder_vue_list 在 applyFuncColumns() **之后**才调用本流程
 *    （保证 column_xxx / modifier_xxx 仍拿原始数值），因此列表侧金额列可能已是展示串；而
 *    app/base/lib/static/modifiers.php::money() 内部走 floatval() ⇒ 二次格式化会把 '￥123.45'
 *    抹成 '￥0.00'。故 ::isFormattableMoney() 只放行 int/float/数字字符串。
 *
 * ⚠️ 真机行为级回归（RED/GREEN：detail 金额由 '123.450' → '￥123.45'、列表不变、无双币种符号）
 *    由手工探针覆盖；PHPUnit 侧不启动框架（currency/eccommon 依赖），仅覆盖纯判据与接线。
 */
class FinderVueMoneyCurrencyContractTest extends TestCase
{
    private const CONTEXT_FILE = __DIR__ . '/../../app/desktop/lib/finder/vue/context.php';
    private const LIST_FILE = __DIR__ . '/../../app/desktop/lib/finder/vue/list.php';
    private const DETAIL_FILE = __DIR__ . '/../../app/desktop/lib/finder/vue/detail.php';

    public static function setUpBeforeClass(): void
    {
        if (!class_exists('desktop_finder_vue_context', false)) {
            require_once self::CONTEXT_FILE;
        }
    }

    /**
     * 判据 = 列定义 type==='money'；不猜列名，忽略非 money 列与非数组列定义。
     */
    #[DataProvider('moneyColumnProvider')]
    public function testCollectsMoneyColumnsBySchemaType(array $schema, array $expected): void
    {
        $this->assertSame($expected, desktop_finder_vue_context::moneyColumnKeys($schema));
    }

    public static function moneyColumnProvider(): array
    {
        return [
            '按 dbschema type=money 命中，保持声明顺序' => [
                [
                    'order_no' => ['type' => 'text'],
                    'total_amount' => ['type' => 'money'],
                    'price' => ['type' => 'money'],
                    'at_time' => ['type' => 'time'],
                ],
                ['total_amount', 'price'],
            ],
            '列名含 amount 但 type 非 money ⇒ 不命中（只认 type，不认命名）' => [
                ['total_amount' => ['type' => 'text'], 'amount_text' => ['type' => 'varchar']],
                [],
            ],
            'type 缺失/非数组列定义 ⇒ 跳过，不报错' => [
                ['a' => [], 'b' => 'not-a-def', 'c' => ['type' => 'money']],
                ['c'],
            ],
            '空 schema ⇒ 空结果' => [[], []],
        ];
    }

    /**
     * 幂等保护：只有 int/float/数字字符串可被 money modifier 安全格式化。
     */
    #[DataProvider('formattableProvider')]
    public function testOnlyRawNumericValuesAreFormattable(mixed $value, bool $expected): void
    {
        $this->assertSame($expected, desktop_finder_vue_context::isFormattableMoney($value));
    }

    public static function formattableProvider(): array
    {
        return [
            'DB 原始金额串（三位小数）⇒ 可格式化' => ['123.450', true],
            '整数串 ⇒ 可格式化' => ['100', true],
            'int ⇒ 可格式化' => [120, true],
            'float ⇒ 可格式化' => [48.5, true],
            '负数金额串 ⇒ 可格式化（退款/冲销）' => ['-12.300', true],
            '已达展示态（带币种符号）⇒ 必须跳过，否则被 floatval 抹成 ￥0.00' => ['￥123.45', false],
            '带千分位与符号的展示串 ⇒ 跳过' => ['￥1,234.56', false],
            '空串 ⇒ 跳过' => ['', false],
            '占位符 ⇒ 跳过' => ['--', false],
            'null ⇒ 跳过' => [null, false],
            '数组 ⇒ 跳过（不递进格式化）' => [[1, 2], false],
        ];
    }

    /**
     * 列表接线：金额流程必须在 applyFuncColumns() 之后（业务列仍拿原始值）、
     * annotateSensitiveFields() 之前，且以 getAllColumns() 为判据来源。
     */
    public function testListPipelineWiresMoneyAfterFuncColumns(): void
    {
        $src = (string) file_get_contents(self::LIST_FILE);

        $funcPos = strpos($src, 'applyFuncColumns($list)');
        $moneyPos = strpos($src, 'applyMoneyModifiersToAllMoneyColumns($list)');
        $sensitivePos = strpos($src, 'annotateSensitiveFields($list)');

        $this->assertNotFalse($funcPos, 'list.php 应调用 applyFuncColumns');
        $this->assertNotFalse($moneyPos, 'list.php 应调用 applyMoneyModifiersToAllMoneyColumns（金额列币种展示）');
        $this->assertNotFalse($sensitivePos, 'list.php 应调用 annotateSensitiveFields');
        $this->assertGreaterThan($funcPos, $moneyPos, '金额格式化必须在 applyFuncColumns 之后：避免展示串破坏 column_*/modifier_* 计算');
        $this->assertLessThan($sensitivePos, $moneyPos, '金额格式化应在响应收尾（sensitive 标注）之前');

        $ctx = (string) file_get_contents(self::CONTEXT_FILE);
        $this->assertStringContainsString(
            'return $this->applyMoneyFormatter($rows, $this->getAllColumns());',
            $ctx,
            '列表通道判据来源须为 getAllColumns()（与 applyTimeModifiersToAllTimeColumns 同规）'
        );
        $this->assertStringContainsString(
            'method_exists($model, \'modifier_\' . $col)',
            $ctx,
            'model 自带 modifier_<col> 的列须跳过（业务自渲染）'
        );
    }

    /**
     * detail 接线：body 与 lines.body 都要按各自 header 的 type=money 格式化。
     */
    public function testDetailPipelineFormatsBodyAndLines(): void
    {
        $src = (string) file_get_contents(self::DETAIL_FILE);

        $this->assertStringContainsString(
            'private function formatMoneyColumns(array $data): array',
            $src,
            'detail.php 应定义 formatMoneyColumns()'
        );
        $this->assertStringContainsString(
            "\$data['body'] = \$this->context->applyMoneyFormatter(\$data['body'], \$data['header']);",
            $src,
            'detail 主单 body 须按 header（含 dbschema type）格式化'
        );
        $this->assertStringContainsString(
            "\$data['lines']['body'] = \$this->context->applyMoneyFormatter(\$data['lines']['body'], \$data['lines']['header']);",
            $src,
            'detail 明细 lines.body 须按 lines.header 格式化'
        );

        $normalizePos = strpos($src, 'normalizeResult($result)');
        $moneyPos = strpos($src, '$data = $this->formatMoneyColumns($data);');
        $this->assertNotFalse($normalizePos, 'detail.php 应先 normalizeResult');
        $this->assertNotFalse($moneyPos, 'detail.php 应调用 formatMoneyColumns');
        $this->assertGreaterThan($normalizePos, $moneyPos, '格式化须在 normalizeResult 之后（对最终 payload 生效）');
    }

    /**
     * ⚠️ 落位约定：金额规则单点使用（唯一调用方 = desktop_finder_vue_context / vue_detail），
     * 故实现并入宿主类，不新增独立 lib 文件（避免多词平铺/目录式命名的额外约定与 require_once 陷阱）。
     */
    public function testNoStandaloneMoneyLibFile(): void
    {
        $this->assertFileDoesNotExist(__DIR__ . '/../../app/desktop/lib/finder/vue/money.php');
        $this->assertFileDoesNotExist(__DIR__ . '/../../app/desktop/lib/finder/vue/currency.php');
    }
}
