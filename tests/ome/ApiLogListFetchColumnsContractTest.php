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
 * API 同步日志列表取数列裁剪契约（SCRUM-7920）。
 *
 * 背景：desktop_finder_vue_list::handle() 原先固定 $model->getlist('*', ...)，
 * 把 sdb_ome_api_log 的 params/response/transfer/original_params 4 个 longtext 全量带出，
 * 500/页列表响应 ~1.1MB；legacy builder/view.php:490-512 + 607-609 一直是按展示列拼 SQL。
 *
 * 本测试锁两件事：
 *   1) 框架纯逻辑 desktop_finder_vue_list::buildFetchColumns()
 *      —— finderCols 空 ⇒ '*'；非空 ⇒ getColumns() ∩ dbschema columns、滤掉 func/extra
 *      虚拟列、idColumn 兜底插首、带 sql 键列用表达式（不启动框架/DB，纯反射调用）；
 *   2) 接线契约 —— ome_ctl_api_api_log::index() 声明的 finderCols 恰好是
 *      dbschema in_list 展示列，且绝不包含 4 个 longtext。
 *
 * ⚠️ 真机行为级回归（响应体积/列表渲染/详情/retry）由 dev 站手工验收覆盖。
 */
class ApiLogListFetchColumnsContractTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';
    private const LIST_FILE = self::ROOT . '/app/desktop/lib/finder/vue/list.php';
    private const CONTROLLER_FILE = self::ROOT . '/app/ome/controller/api/api/log.php';
    private const DBSCHEMA_FILE = self::ROOT . '/app/ome/dbschema/api_log.php';

    /** 4 个必须被剔除的大字段（longtext）。 */
    private const LONGTEXT_FIELDS = ['params', 'response', 'transfer', 'original_params'];

    private static object $driver;
    private static ReflectionMethod $buildFetchColumns;

    public static function setUpBeforeClass(): void
    {
        if (!class_exists('desktop_finder_vue_list', false)) {
            require_once self::LIST_FILE;
        }
        $ref = new ReflectionClass('desktop_finder_vue_list');
        // buildFetchColumns() 不触碰 $this->context，故可跳过构造（避免搭真实 context/model）。
        self::$driver = $ref->newInstanceWithoutConstructor();
        self::$buildFetchColumns = $ref->getMethod('buildFetchColumns');
        self::$buildFetchColumns->setAccessible(true);
    }

    private function build(string $finderCols, array $columns, array $schema): string
    {
        return (string) self::$buildFetchColumns->invoke(self::$driver, $finderCols, $columns, $schema);
    }

    /**
     * API 日志真实 schema（含 model get_schema() 注入的 idColumn=log_id）。
     */
    private function apiLogSchema(): array
    {
        $db = [];
        require self::DBSCHEMA_FILE; // dbschema 文件填充 $db['api_log']
        $schema = $db['api_log'];
        $schema['idColumn'] = 'log_id'; // 镜像 ome_mdl_api_log::get_schema()

        return $schema;
    }

    private function declaredFinderCols(): array
    {
        $src = (string) file_get_contents(self::CONTROLLER_FILE);
        $this->assertSame(
            1,
            preg_match("/'finderCols'\s*=>\s*'([^']*)'/", $src, $m),
            'ome_ctl_api_api_log::index() 未声明 finderCols，列表取数仍是 *'
        );

        return array_map('trim', explode(',', $m[1]));
    }

    // ===== 1. 框架纯逻辑 =====

    public function testEmptyFinderColsKeepsWildcard(): void
    {
        $schema = [
            'idColumn' => 'log_id',
            'columns'  => [
                'log_id' => ['type' => 'varchar(32)'],
                'params' => ['type' => 'longtext'],
            ],
        ];

        // 未声明 finderCols：即使 getColumns() 有内容也必须回退 '*'（其他 finder 页零回归）
        $this->assertSame('*', $this->build('', ['log_id', 'params'], $schema));
        $this->assertSame('*', $this->build('   ', ['log_id', 'params'], $schema));
    }

    public function testSubsetPrependsIdColumnWhenMissing(): void
    {
        $schema = $this->apiLogSchema();
        $display = ['createtime', 'status', 'worker', 'msg'];

        // 模拟用户保存的列配置：getColumns() 只回这些展示列，log_id 不在其中
        $sql = $this->build(implode(',', $display), $display, $schema);

        $this->assertSame(
            '`log_id`,`createtime`,`status`,`worker`,`msg`',
            $sql,
            'idColumn 不在展示列时必须兜底插首（legacy view.php:607-609）'
        );
        foreach (self::LONGTEXT_FIELDS as $field) {
            $this->assertStringNotContainsString($field, $sql, '大字段不得进入取数列：' . $field);
        }
    }

    public function testIdColumnNotDuplicatedWhenAlreadyPresent(): void
    {
        $schema = $this->apiLogSchema();

        $this->assertSame(
            '`log_id`,`createtime`',
            $this->build('createtime', ['log_id', 'createtime'], $schema)
        );
    }

    public function testFuncAndExtraVirtualColumnsAreFiltered(): void
    {
        $schema = $this->apiLogSchema();

        // getColumns() 可能带 func 列（column_edit，不在 schema.columns）与 extra 虚拟列
        $sql = $this->build('createtime,column_edit,ghost_extra', ['createtime', 'column_edit', 'ghost_extra'], $schema);

        $this->assertSame('`log_id`,`createtime`', $sql, 'func/extra 虚拟列不在表里，必须被交集滤掉');
    }

    public function testSqlKeyedColumnUsesExpression(): void
    {
        $schema = [
            'idColumn' => 'id',
            'columns'  => [
                'id'     => ['type' => 'int'],
                'status' => ['type' => 'varchar(16)', 'sql' => "IF(status = 'success', 1, 0)"],
            ],
        ];

        $this->assertSame(
            '`id`,IF(status = \'success\', 1, 0) as status',
            $this->build('status', ['id', 'status'], $schema),
            "带 sql 键的列按 legacy 用其表达式（view.php:505-506）"
        );
    }

    public function testDuplicateColumnsAreDeduped(): void
    {
        $schema = $this->apiLogSchema();

        // log_id 已在展示列中 ⇒ 保持在原位，不重复插入（legacy 仅「不在列表中」才插首）
        $this->assertSame(
            '`createtime`,`log_id`',
            $this->build('createtime', ['createtime', 'createtime', 'log_id', 'createtime'], $schema)
        );
    }

    public function testIdColumnMayBeArrayShape(): void
    {
        $schema = [
            'idColumn' => ['log_id' => 'log_id'], // dbtable.php 结构
            'columns'  => [
                'log_id'     => ['type' => 'varchar(32)'],
                'createtime' => ['type' => 'time'],
            ],
        ];

        $this->assertSame('`log_id`,`createtime`', $this->build('createtime', ['createtime'], $schema));
    }

    /**
     * finderCols 与 schema 完全无交集且无 idColumn ⇒ 兜底 '*'，绝不产出空取数列。
     *
     * @param array $schema
     */
    #[DataProvider('degenerateSchemaProvider')]
    public function testFallsBackToWildcardWhenNothingSurvives(array $schema): void
    {
        $this->assertSame('*', $this->build('ghost', ['ghost'], $schema));
    }

    public static function degenerateSchemaProvider(): array
    {
        return [
            'columns 为空'            => [['columns' => []]],
            'idColumn 为空'           => [['idColumn' => '', 'columns' => ['real' => ['type' => 'int']]]],
            'idColumn 数组但为空'     => [['idColumn' => [], 'columns' => ['real' => ['type' => 'int']]]],
        ];
    }

    // ===== 2. 控制器接线契约 =====

    public function testControllerDeclaresExactlyDbschemaInListColumns(): void
    {
        $schema = $this->apiLogSchema();

        $expected = [];
        foreach ($schema['columns'] as $key => $def) {
            if (!empty($def['in_list'])) {
                $expected[] = $key;
            }
        }

        $this->assertSame(
            $expected,
            $this->declaredFinderCols(),
            'finderCols 必须与 dbschema in_list 展示列一致（新增展示列时同步更新控制器）'
        );
    }

    public function testDeclaredFinderColsExcludesLongtextFields(): void
    {
        $declared = $this->declaredFinderCols();

        foreach (self::LONGTEXT_FIELDS as $field) {
            $this->assertNotContains($field, $declared, 'finderCols 不得包含大字段：' . $field);
        }
    }

    public function testRealPageFetchColumnsSlimmedWithIdColumnFirst(): void
    {
        $schema = $this->apiLogSchema();
        $declared = $this->declaredFinderCols();

        // 模拟默认路径：无用户保存配置时 getColumns() = finderCols；
        // 用户保存配置时 getColumns() 是展示列子集（可能含 func 列），交集后结果一致/更窄。
        $expectedSql = '`log_id`,' . implode(',', array_map(static fn ($c) => '`' . $c . '`', $declared));

        $this->assertSame($expectedSql, $this->build(implode(',', $declared), $declared, $schema));
        $this->assertSame(
            $expectedSql,
            $this->build(implode(',', $declared), array_merge($declared, ['column_edit']), $schema),
            'func 列 column_edit 由 _actions/回调承载，不参与取数'
        );
    }

    /**
     * 接线契约：list.php 必须把 buildFetchColumns() 结果交给 getlist，而不是硬编码 '*'。
     */
    public function testListHandlerWiresFetchColumnsIntoGetlist(): void
    {
        $src = (string) file_get_contents(self::LIST_FILE);

        $this->assertStringContainsString('$this->buildFetchColumns(', $src);
        $this->assertStringContainsString('getFinderCols()', $src);
        $this->assertStringContainsString('getColumns()', $src);
        $this->assertStringContainsString('getSchema()', $src);
        $this->assertStringContainsString(
            '$model->$getlistMethod($fetchColumns,',
            $src,
            'getlist 必须以计算出的取数列调用'
        );
        $this->assertStringNotContainsString(
            "\$model->\$getlistMethod('*',",
            $src,
            'list.php 不得再硬编码 getlist(\'*\')'
        );
    }
}
