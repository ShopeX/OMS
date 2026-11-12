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
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Vue Finder 时间筛选精度契约（到分，与 legacy 对齐）。
 *
 * 背景：Vue 端时间筛选下发 `{col}|{op}` + 'Y-m-d[ H:i[:s]]'（filterAdapter.resolveTimeFilterValue），
 * 但 legacy 侧 int 时间戳列（dbschema type=time / last_modify）的筛选解析只认 legacy 筛选包：
 *   {col}='Y-m-d' + _{col}_search={than|lthan|nequal|between|bthan|sthan} + _DTIME_[H|M][{col}]
 *   between 另走 {col}_from / {col}_to（app/dbeav/lib/filter.php L155-175：strtotime("$date $H:$M:00")）。
 *
 * 改前（RED，真机已复现）：`sale_time|bthan=2026-10-07 10:30` 走 filter.php 的 `|` 分支
 *   （_inner_getFilterType($type,$v,false) ⇒ 直接把字符串拼进 WHERE），与 int 列比较被 MySQL 强转 0
 *   ⇒ bthan 恒真（筛了等于没筛）、lthan 恒假（空列表）、nequal 退化成裸列 `WHERE sale_time`；
 *   同一条 SQL 里 `_DTIME_` 永远拼不上 ⇒ 分钟精度一并丢失。
 *
 * 改后（GREEN）：desktop_finder_vue_context::normalizeTimeFilterKeys() 在下发 SQL 前把 Vue 形态
 *   还原成 legacy 包，H/M 取用户所选时分、秒固定 00（legacy 时间筛选精度即到分），
 *   于是 SQL 与 legacy 页面同形 ⇒ 精度与行为同时对齐。
 *
 * 判据/范围：只归一 type=time / last_modify（int 时间戳分支）与 type=TIMESTAMP（严格大写，字符串分支）；
 *   两者 legacy 筛选包结构一致。datetime / 小写 timestamp / 其它列型 legacy 无时间分支，保持原路径
 *   （否则会把用户选的秒截成 00 或引入 legacy 没有的语义，造成回归）。
 */
#[RunTestsInSeparateProcesses]
class FinderVueTimeFilterContractTest extends TestCase
{
    private const CONTEXT_FILE = __DIR__ . '/../../app/desktop/lib/finder/vue/context.php';

    protected function setUp(): void
    {
        parent::setUp();
        if (!class_exists('desktop_finder_vue_context', false)) {
            require_once self::CONTEXT_FILE;
        }
    }

    /**
     * 真实列型的 dbschema 片段（billcenter/sales）：
     * sale_time = type:time（int 时间戳）⇒ 归一；ship_time = datetime 串 ⇒ 不归一。
     */
    private function salesColumns(): array
    {
        return [
            'bill_no' => ['type' => 'varchar(32)'],
            'sale_time' => ['type' => 'time'],
            'ship_time' => ['type' => 'datetime'],
            'cost_freight' => ['type' => 'money'],
            'last_modify' => ['type' => 'last_modify'],
            'is_del' => ['type' => 'bool'],
        ];
    }

    public static function timeColumnProvider(): array
    {
        return [
            'time ⇒ 归一' => [['c' => ['type' => 'time']], ['c' => true]],
            'last_modify ⇒ 归一' => [['c' => ['type' => 'last_modify']], ['c' => true]],
            'datetime 串 ⇒ 不归一（保秒）' => [['c' => ['type' => 'datetime']], []],
            'TIMESTAMP（严格大写）⇒ 归一（字符串分支）' => [['c' => ['type' => 'TIMESTAMP']], ['c' => true]],
            'timestamp（小写，legacy 无分支）⇒ 不归一' => [['c' => ['type' => 'timestamp']], []],
            'int ⇒ 不归一' => [['c' => ['type' => 'int']], []],
            'money ⇒ 不归一' => [['c' => ['type' => 'money']], []],
            '枚举数组 type ⇒ 不归一' => [['c' => ['type' => ['0' => '停售']]], []],
            '非数组列定义 ⇒ 跳过' => [['c' => 'varchar(32)'], []],
        ];
    }

    #[DataProvider('timeColumnProvider')]
    public function testTimeColumnKeysOnlyMatchesIntTimestampColumns(array $schema, array $expected): void
    {
        $this->assertSame($expected, desktop_finder_vue_context::timeColumnKeys($schema));
    }

    public static function splitValueProvider(): array
    {
        return [
            '仅日期 ⇒ 00:00' => ['2026-10-07', ['date' => '2026-10-07', 'hour' => '00', 'minute' => '00']],
            '到分 ⇒ 原样' => ['2026-10-07 10:30', ['date' => '2026-10-07', 'hour' => '10', 'minute' => '30']],
            '到秒 ⇒ 取到分（秒丢）' => ['2026-10-07 10:30:45', ['date' => '2026-10-07', 'hour' => '10', 'minute' => '30']],
            'T 分隔（ISO）⇒ 接受' => ['2026-10-07T09:05', ['date' => '2026-10-07', 'hour' => '09', 'minute' => '05']],
            '带空格 ⇒ trim' => [' 2026-10-07 23:59 ', ['date' => '2026-10-07', 'hour' => '23', 'minute' => '59']],
            '空串 ⇒ null' => ['', null],
            '非法日期 ⇒ null' => ['2026/10/07', null],
            '纯数字时间戳 ⇒ null（不猜）' => ['1780000000', null],
            '非字符串 ⇒ null' => [1780000000, null],
        ];
    }

    #[DataProvider('splitValueProvider')]
    public function testSplitTimeFilterValue($value, ?array $expected): void
    {
        $this->assertSame($expected, desktop_finder_vue_context::splitTimeFilterValue($value));
    }

    /**
     * 单操作符 ⇒ legacy 单列形态：{col} + _{col}_search + _DTIME_[H|M][{col}]。
     */
    public function testSingleOperatorRestoresLegacyPacket(): void
    {
        $this->assertSame(
            [
                'sale_time' => '2026-10-07',
                '_sale_time_search' => 'bthan',
                '_DTIME_' => ['H' => ['sale_time' => '10'], 'M' => ['sale_time' => '30']],
            ],
            desktop_finder_vue_context::buildLegacyTimeFilterKeys(
                'sale_time',
                ['bthan' => ['date' => '2026-10-07', 'hour' => '10', 'minute' => '30']]
            )
        );
    }

    /**
     * 区间（bthan + sthan 配对）⇒ legacy between 形态：{col}_from / {col}_to（filter.php L160-170）。
     * 注意 legacy between 为 `>= from and < to`，与 legacy 页面同义。
     */
    public function testPairedOperatorsRestoreLegacyBetweenPacket(): void
    {
        $this->assertSame(
            [
                // 裸列键：filter.php 的 foreach 守卫是 isset($cols[$k])，缺它整列不进时间分支（实测返回全量）
                'sale_time' => '2026-10-07',
                'sale_time_from' => '2026-10-07',
                'sale_time_to' => '2026-10-08',
                '_sale_time_search' => 'between',
                '_DTIME_' => [
                    'H' => ['sale_time_from' => '10', 'sale_time_to' => '12'],
                    'M' => ['sale_time_from' => '30', 'sale_time_to' => '00'],
                ],
            ],
            desktop_finder_vue_context::buildLegacyTimeFilterKeys(
                'sale_time',
                [
                    'bthan' => ['date' => '2026-10-07', 'hour' => '10', 'minute' => '30'],
                    'sthan' => ['date' => '2026-10-08', 'hour' => '12', 'minute' => '00'],
                ]
            )
        );
    }

    /**
     * 改前红：Vue 形态的 `|` 键必须消失（`|` 分支拼不出 `_DTIME_`，是精度丢失的根因）。
     */
    public function testVuePipeKeyIsConvertedAway(): void
    {
        $normalized = desktop_finder_vue_context::normalizeTimeFilters(
            ['sale_time|bthan' => '2026-10-07 10:30'],
            $this->salesColumns()
        );

        $this->assertArrayNotHasKey('sale_time|bthan', $normalized);
        $this->assertSame('2026-10-07', $normalized['sale_time']);
        $this->assertSame('bthan', $normalized['_sale_time_search']);
        $this->assertSame('10', $normalized['_DTIME_']['H']['sale_time']);
        $this->assertSame('30', $normalized['_DTIME_']['M']['sale_time']);
    }

    public function testNormalizeKeepsNonTimeColumnsAndLegacyKeysUntouched(): void
    {
        $input = [
            'bill_no' => 'A1',
            'bill_no|like' => 'B2',
            'ship_time|bthan' => '2026-10-07 10:30:45',
            'is_del' => '0',
            '_DTIME_' => ['H' => ['sale_time' => '08'], 'M' => ['sale_time' => '05']],
            '_sale_time_search' => 'than',
        ];
        $this->assertSame($input, desktop_finder_vue_context::normalizeTimeFilters($input, $this->salesColumns()));
    }

    /**
     * legacy 原生包（含裸 {col}）必须原样透传：真机实测漏守卫时 `>=` 会被覆写成 `=`（结果少 1 行）。
     */
    public function testLegacyPacketWithBareColumnKeyIsPassedThrough(): void
    {
        $input = [
            'sale_time' => '2026-10-06',
            '_sale_time_search' => 'bthan',
            '_DTIME_' => ['H' => ['sale_time' => '23'], 'M' => ['sale_time' => '31']],
        ];

        $this->assertSame($input, desktop_finder_vue_context::normalizeTimeFilters($input, $this->salesColumns()));
    }

    /**
     * 幂等：归一后的包再进一次（同一请求被复用/重放）结果不变。
     */
    public function testNormalizeIsIdempotent(): void
    {
        $once = desktop_finder_vue_context::normalizeTimeFilters(
            ['sale_time|bthan' => '2026-10-06 00:00', 'sale_time|sthan' => '2026-10-06 23:31'],
            $this->salesColumns()
        );

        $this->assertSame($once, desktop_finder_vue_context::normalizeTimeFilters($once, $this->salesColumns()));
        $this->assertSame('between', $once['_sale_time_search']);
    }

    /**
     * 无 time 列的 finder ⇒ 完全不动（零副作用）。
     */
    public function testNormalizeIsNoopWithoutTimeColumns(): void
    {
        $input = ['bill_no|bthan' => '2026-10-07 10:30', 'money_col' => '100'];
        $this->assertSame(
            $input,
            desktop_finder_vue_context::normalizeTimeFilters($input, ['bill_no' => ['type' => 'varchar(32)']])
        );
    }

    /**
     * 非法时间串不改写：交回 legacy 原路径（不猜、不静默丢条件）。
     */
    public function testInvalidValueFallsBackToLegacyPath(): void
    {
        $input = ['sale_time|bthan' => 'not-a-date'];
        $this->assertSame($input, desktop_finder_vue_context::normalizeTimeFilters($input, $this->salesColumns()));
    }

    /**
     * last_modify 同规（列表默认按最后修改时间筛）。
     */
    public function testLastModifyColumnIsNormalizedToo(): void
    {
        $normalized = desktop_finder_vue_context::normalizeTimeFilters(
            ['last_modify|sthan' => '2026-10-07 18:20'],
            $this->salesColumns()
        );
        $this->assertSame(
            [
                'last_modify' => '2026-10-07',
                '_last_modify_search' => 'sthan',
                '_DTIME_' => ['H' => ['last_modify' => '18'], 'M' => ['last_modify' => '20']],
            ],
            $normalized
        );
    }

    /**
     * TIMESTAMP 列同规，但走 filter.php 的字符串分支：value 保持 'Y-m-d' + _DTIME_ 的 H/M，
     * 由 filter.php 自己补 ':00' 并加引号（故秒不会被提前截断成 00 以外的值）。
     */
    public function testTimestampColumnIsNormalizedWithStringValue(): void
    {
        $normalized = desktop_finder_vue_context::normalizeTimeFilters(
            ['create_time|bthan' => '2026-10-07 09:05'],
            ['create_time' => ['type' => 'TIMESTAMP']]
        );
        $this->assertSame(
            [
                'create_time' => '2026-10-07',
                '_create_time_search' => 'bthan',
                '_DTIME_' => ['H' => ['create_time' => '09'], 'M' => ['create_time' => '05']],
            ],
            $normalized
        );
    }

    /**
     * 多个时间列互不干扰，且 `_DTIME_` 合并（不是覆盖）。
     */
    public function testMultipleTimeColumnsMergeDtime(): void
    {
        $normalized = desktop_finder_vue_context::normalizeTimeFilters(
            ['sale_time|bthan' => '2026-10-07 10:30', 'last_modify|sthan' => '2026-10-08 09:15'],
            $this->salesColumns()
        );

        $this->assertSame('bthan', $normalized['_sale_time_search']);
        $this->assertSame('sthan', $normalized['_last_modify_search']);
        $this->assertSame('10', $normalized['_DTIME_']['H']['sale_time']);
        $this->assertSame('09', $normalized['_DTIME_']['H']['last_modify']);
        $this->assertSame('15', $normalized['_DTIME_']['M']['last_modify']);
    }

    /**
     * 无操作符（等于/是）⇒ legacy nequal 形态。
     */
    public function testPlainKeyMapsToNeqUal(): void
    {
        $normalized = desktop_finder_vue_context::normalizeTimeFilters(
            ['sale_time' => '2026-10-07 10:30'],
            $this->salesColumns()
        );
        $this->assertSame('nequal', $normalized['_sale_time_search']);
        $this->assertSame('2026-10-07', $normalized['sale_time']);
    }

    /**
     * 真机主路径：Vue 表头筛选弹层（FinderHeaderFilterPopover::toPatch）下发的是符号操作符
     * `{col}|>=` / `{col}|<=`（不是 bthan/sthan），值形态 'Y-m-d H:i:s'。
     * 改前：`>=` 不在 legacy 词表里 ⇒ 条件构建为空 ⇒ 整列筛选被静默丢弃（返回全量，比 400 更难发现）。
     */
    public function testSymbolOperatorTokensAreNormalized(): void
    {
        $ge = desktop_finder_vue_context::normalizeTimeFilters(
            ['sale_time|>=' => '2026-10-06 23:31:00'],
            $this->salesColumns()
        );
        $this->assertSame(
            [
                'sale_time' => '2026-10-06',
                '_sale_time_search' => 'bthan',
                '_DTIME_' => ['H' => ['sale_time' => '23'], 'M' => ['sale_time' => '31']],
            ],
            $ge
        );

        $le = desktop_finder_vue_context::normalizeTimeFilters(
            ['sale_time|<=' => '2026-10-06 23:32:00'],
            $this->salesColumns()
        );
        $this->assertSame('sthan', $le['_sale_time_search']);
        $this->assertSame(['H' => ['sale_time' => '23'], 'M' => ['sale_time' => '32']], $le['_DTIME_']);
    }

    /**
     * 表头弹层「介于」= 同一列同时下发 `|>=` 与 `|<=` ⇒ 拼成 legacy between 包（含裸列键）。
     */
    public function testSymbolPairBecomesBetweenPacket(): void
    {
        $normalized = desktop_finder_vue_context::normalizeTimeFilters(
            ['sale_time|>=' => '2026-10-06 00:00:00', 'sale_time|<=' => '2026-10-06 23:31:59'],
            $this->salesColumns()
        );

        $this->assertSame('between', $normalized['_sale_time_search']);
        $this->assertSame('2026-10-06', $normalized['sale_time']);
        $this->assertSame('2026-10-06', $normalized['sale_time_from']);
        $this->assertSame('2026-10-06', $normalized['sale_time_to']);
        $this->assertSame(
            [
                'H' => ['sale_time_from' => '00', 'sale_time_to' => '23'],
                'M' => ['sale_time_from' => '00', 'sale_time_to' => '31'],
            ],
            $normalized['_DTIME_']
        );
    }

    /**
     * 认不出的操作符绝不静默丢条件：原样透传回 legacy 路径（宁可 legacy 报错，也不要「筛了等于没筛」）。
     */
    public function testUnknownOperatorIsPassedThrough(): void
    {
        $input = ['sale_time|has' => '2026-10-06 23:31'];
        $this->assertSame($input, desktop_finder_vue_context::normalizeTimeFilters($input, $this->salesColumns()));
        $this->assertNull(desktop_finder_vue_context::normalizeTimeOperatorToken('has'));
        $this->assertSame('bthan', desktop_finder_vue_context::normalizeTimeOperatorToken('>='));
        $this->assertSame('sthan', desktop_finder_vue_context::normalizeTimeOperatorToken('<'));
        $this->assertSame('', desktop_finder_vue_context::normalizeTimeOperatorToken(null));
    }
}
