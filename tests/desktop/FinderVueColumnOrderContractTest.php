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
 * Vue Finder 列表页列顺序契约（框架统一修）。
 *
 * 单一权威（legacy）：
 *   - app/desktop/lib/finder/builder/prototype.php:218-241 all_columns()
 *     把「finder 虚拟列(column_xxx) + 扩展列 + dbschema in_list 列」合并后按 order 升序
 *     （未声明 order 视为 100，array_multisort 稳定 ⇒ 同 order 保持声明顺序）。
 *   - 同文件 getColumns() 第 3 分支（用户无保存列配置且 finder 未声明 _finder_cols）：
 *     用 all_columns() 的**键序**过滤出可见列 ⇒ legacy 表头顺序即 all_columns() 键序。
 *
 * 缺陷：desktop_finder_vue_context::buildColumnsMeta() 原先按「dbschema 声明顺序展开 +
 * finder 虚拟列一律追加到尾部」输出列元数据 ⇒ Vue 页表头顺序与 legacy 不一致
 * （典型：状态列/创建者列错位；如 dealer/bbu legacy=操作|编码|名称|状态|创建时间|更新时间|创建者）。
 *
 * ⚠️ 落位约定：该规则**单点使用**（唯一调用方 = desktop_finder_vue_context），故实现直接并入宿主类
 *    desktop_finder_vue_context（context.php），**不再拆出独立 lib 文件**（单点使用的类不入框架 lib 目录，
 *    避免多词平铺/目录式命名的额外约定与 require_once 陷阱）。下方 assertFileDoesNotExist 守卫该约定。
 *
 * 本测试锁两件事：
 *   1) 排序规则语义（desktop_finder_vue_context::sortByLegacyOrder）：只重排不增删列、
 *      按 all_columns() 键序稳定排序、未登记键排尾部、动作列剔除、重复键去重；
 *   2) context.php 确实消费该规则 + getAllColumns() 键序（防止回退成「尾部追加」写法），
 *      且不再残留 [DEBUG columns] 调试输出。
 *
 * ⚠️ 真机行为级回归（RED/GREEN 逐页列序对照）由手工探针覆盖，PHPUnit 侧不启动框架，
 *    仅覆盖纯排序规则与接线。
 */
class FinderVueColumnOrderContractTest extends TestCase
{
    private const CONTEXT_FILE = __DIR__ . '/../../app/desktop/lib/finder/vue/context.php';
    private const REPO_ROOT = __DIR__ . '/../../';

    public static function setUpBeforeClass(): void
    {
        if (!class_exists('desktop_finder_vue_context', false)) {
            require_once self::CONTEXT_FILE;
        }
    }

    /**
     * 排序规则：只改顺序、不改集合，按 legacy all_columns() 键序稳定排序。
     *
     * @param array $keys        待展示可见列键（原判定产出顺序）
     * @param array $allColumns  all_columns()/getAllColumns() 键序
     * @param array $expected    期望顺序
     */
    #[DataProvider('legacyOrderProvider')]
    public function testSortsByLegacyAllColumnsOrder(array $keys, array $allColumns, array $expected): void
    {
        $actual = desktop_finder_vue_context::sortByLegacyOrder($keys, $allColumns);

        $this->assertSame($expected, $actual);

        // 只重排、不增删（动作列除外）
        $this->assertSame(
            [],
            array_values(array_diff(
                array_values(array_diff($keys, desktop_finder_vue_context::ACTION_KEYS)),
                $actual
            )),
            '排序不得丢列'
        );
        $this->assertSame(
            [],
            array_values(array_diff($actual, $keys)),
            '排序不得新增列'
        );
    }

    public static function legacyOrderProvider(): array
    {
        // dealer/bbu：dbschema order = code=10, name=20, status=30, at_time=40, up_time=50, op_name=60
        // 修复前输出 = dbschema 声明顺序展开 + 虚拟列追加尾部 ⇒ 状态列被挤到最尾。
        $bbuDeclOrder = ['bbu_code', 'bbu_name', 'op_name', 'at_time', 'up_time', 'column_status'];
        $bbuLegacyOrder = ['bbu_code', 'bbu_name', 'column_status', 'at_time', 'up_time', 'op_name'];

        return [
            'bbu：状态列(order=30) 回到 名称(order=20) 之后、创建时间(order=40) 之前' => [
                $bbuDeclOrder,
                $bbuLegacyOrder,
                $bbuLegacyOrder,
            ],
            '已是 legacy 顺序 ⇒ 原样返回（幂等）' => [
                $bbuLegacyOrder,
                $bbuLegacyOrder,
                $bbuLegacyOrder,
            ],
            '未登记在 all_columns() 的键排尾部，且保持入参相对顺序' => [
                ['b', 'ghost_a', 'a', 'ghost_b'],
                ['a', 'b'],
                ['a', 'b', 'ghost_a', 'ghost_b'],
            ],
            '同 order（都未登记 ⇒ 尾部）保持入参相对顺序（稳定排序）' => [
                ['z2', 'z1', 'a'],
                ['a'],
                ['a', 'z2', 'z1'],
            ],
            '动作列一律剔除（由 _actions 列承载）' => [
                ['bbu_code', 'column_edit', 'bbu_name', 'column_op'],
                ['bbu_code', 'column_edit', 'bbu_name', 'column_op'],
                ['bbu_code', 'bbu_name'],
            ],
            '重复键去重，首次出现的位置参与排序' => [
                ['bbu_name', 'bbu_code', 'bbu_name'],
                ['bbu_code', 'bbu_name'],
                ['bbu_code', 'bbu_name'],
            ],
            '空入参 ⇒ 空结果' => [
                [],
                ['a', 'b'],
                [],
            ],
            'all_columns() 为空（无 dbschema 的 FINDER_COLS 页）⇒ 保持入参顺序' => [
                ['f1', 'f2', 'f3'],
                [],
                ['f1', 'f2', 'f3'],
            ],
        ];
    }

    /**
     * 接线契约：context.php 必须用本类 sortByLegacyOrder() 排序，并消费 getAllColumns() 键序。
     */
    public function testContextConsumesLegacyOrderRule(): void
    {
        $src = (string) file_get_contents(self::CONTEXT_FILE);

        $this->assertStringContainsString(
            'self::sortByLegacyOrder(',
            $src,
            'buildColumnsMeta() 未按 legacy all_columns() 键序排序'
        );
        $this->assertStringContainsString(
            'array_keys($this->getAllColumns())',
            $src,
            '排序未以 getAllColumns()（= legacy all_columns() 同源实现）的键序为权威'
        );
        $this->assertStringContainsString(
            'self::ACTION_KEYS',
            $src,
            '动作列清单未复用本类 ACTION_KEYS 常量'
        );
        $this->assertStringNotContainsString(
            '[DEBUG columns]',
            $src,
            '框架内不得残留调试输出'
        );
    }

    /**
     * 落位契约：列顺序规则单点使用，实现必须内联在宿主类中，禁止再拆出独立 lib 文件。
     * （历史：曾拆为 app/desktop/lib/finder/vue/column/order.php，为单点使用的额外文件 + 额外 require_once，已回收）
     */
    public function testOrderRuleIsNotExtractedIntoItsOwnLibFile(): void
    {
        foreach ([
            '/app/desktop/lib/finder/vue/column/order.php',
            '/app/desktop/lib/finder/vue/filter/control/type.php',
        ] as $strayLib) {
            $this->assertFileDoesNotExist(
                self::REPO_ROOT . ltrim($strayLib, '/'),
                '单点使用的契约实现应内联在宿主类，勿新增 lib 文件：' . $strayLib
            );
        }

        $src = (string) file_get_contents(self::CONTEXT_FILE);
        $this->assertStringNotContainsString(
            "require_once __DIR__ . '/column/",
            $src,
            'context.php 不应再 require 列顺序 lib 文件（实现已内联）'
        );
    }
}
