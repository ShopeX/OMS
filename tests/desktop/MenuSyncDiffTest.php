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

use PHPUnit\Framework\TestCase;

/**
 * 差异同步核心逻辑测试：用假 menus model 记录 insert/save/delete，
 * 断言身份键命中复用 menu_id、下线删除、重复键去重、空集保护、重跑幂等。
 */
class MenuSyncDiffTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        chdir(dirname(__DIR__, 2));
        include_once 'config/config.php';
        include_once 'app/base/defined.php';
        include_once 'app/base/kernel.php';
        kernel::$console_output = true;

        // 父类 desktop_application_menu 依赖框架自动加载，必须在 setUp 之后才能声明子类。
        if (!class_exists('MenuSyncDiffTestMenu', false)) {
            eval('class MenuSyncDiffTestMenu extends desktop_application_menu {
                public $model;
                public $incoming = array();
                public $has_menugroup = true;
                public $content_typename = "";

                protected function menus_model()
                {
                    return $this->model;
                }

                protected function build_rows($app_id)
                {
                    return array($this->incoming, $this->has_menugroup);
                }

                public function detect($app, $current = null)
                {
                    return new ArrayIterator(array(array("id" => "workgroup")));
                }

                public function call_sync($model, array $existing_rows, array $incoming)
                {
                    return $this->sync_menu_rows($model, $existing_rows, $incoming);
                }
            }');
        }
    }

    public function testMatchingIdentityUpdatesInPlaceAndPreservesMenuIdAndExtraColumns(): void
    {
        $model = new MenuSyncDiffFakeModel();
        $model->seed($this->existingRow(10, array('icon' => 'star', 'menu_title' => '旧标题')));

        $incoming = $this->incomingRows(array($this->incomingRow(array('menu_title' => '新标题'))));

        $stats = $this->menu()->call_sync($model, $model->getList('*', array()), $incoming);

        $this->assertSame(0, $stats['insert']);
        $this->assertSame(1, $stats['update']);
        $this->assertSame(0, $stats['delete']);
        $this->assertSame(array('save'), array_column($model->calls, 0));
        $this->assertSame(10, $model->rows[10]['menu_id']);
        $this->assertSame('star', $model->rows[10]['icon'], '未在目标行里提供的列不得被清空');
        $this->assertSame('新标题', $model->rows[10]['menu_title']);
    }

    public function testNewIdentityIsInserted(): void
    {
        $model = new MenuSyncDiffFakeModel();

        $incoming = $this->incomingRows(array($this->incomingRow()));

        $stats = $this->menu()->call_sync($model, array(), $incoming);

        $this->assertSame(1, $stats['insert']);
        $this->assertSame(0, $stats['update']);
        $this->assertSame(0, $stats['delete']);
        $this->assertSame(array('insert'), array_column($model->calls, 0));
        $this->assertCount(1, $model->rows);
    }

    public function testDroppedIdentityIsDeletedOnlyAfterWrites(): void
    {
        $model = new MenuSyncDiffFakeModel();
        $model->seed($this->existingRow(42));

        $stats = $this->menu()->call_sync($model, $model->getList('*', array()), array());

        $this->assertSame(0, $stats['insert']);
        $this->assertSame(0, $stats['update']);
        $this->assertSame(1, $stats['delete']);
        $this->assertSame(array('delete'), array_column($model->calls, 0));
        $this->assertArrayNotHasKey(42, $model->rows);
    }

    public function testDuplicateExistingIdentitiesKeepSmallestMenuId(): void
    {
        $model = new MenuSyncDiffFakeModel();
        $model->seed($this->existingRow(10));
        $model->seed($this->existingRow(7));

        $incoming = $this->incomingRows(array($this->incomingRow()));

        $stats = $this->menu()->call_sync($model, $model->getList('*', array()), $incoming);

        $this->assertSame(1, $stats['update']);
        $this->assertSame(array('delete', 'save'), array_column($model->calls, 0));
        $this->assertSame(10, $model->calls[0][1], '应删除较大的 menu_id');
        $this->assertSame(7, $model->calls[1][1], '应保留最小的 menu_id');
        $this->assertArrayHasKey(7, $model->rows);
        $this->assertArrayNotHasKey(10, $model->rows);
    }

    public function testSecondRunIsIdempotent(): void
    {
        $model = new MenuSyncDiffFakeModel();
        $model->seed($this->existingRow(10));

        $incoming = $this->incomingRows(array($this->incomingRow()));

        $first = $this->menu()->call_sync($model, $model->getList('*', array()), $incoming);

        $this->assertSame(1, $first['update']);
        $this->assertSame(0, $first['insert']);
        $this->assertSame(0, $first['delete']);

        $existingCount = count($model->rows);
        $callsBeforeSecond = count($model->calls);
        $second = $this->menu()->call_sync($model, $model->getList('*', array()), $incoming);
        $secondCalls = array_slice($model->calls, $callsBeforeSecond);

        // 本任务「幂等」定义：同一份目标连跑两次，第二轮 0 insert / 0 delete，menu_id 不漂移。
        // 该定义不含 update —— 存量行仍会逐行 save()（原地 UPDATE），故第二轮 update 计数等于存量行数。
        $this->assertSame(0, $second['insert']);
        $this->assertSame(0, $second['delete']);
        $this->assertSame($existingCount, $second['update']);
        $this->assertSame($existingCount, count(array_filter($secondCalls, static function (array $call): bool {
            return $call[0] === 'save';
        })), '第二轮对每个存量行各 save 一次（原地 UPDATE，menu_id 不变）');
        $this->assertArrayHasKey(10, $model->rows);
    }

    public function testInsertFailureIsCountedAsFailedNotInsert(): void
    {
        $model = new MenuSyncDiffFakeModel();
        $model->failInsert = true;

        $incoming = $this->incomingRows(array($this->incomingRow()));

        $stats = $this->menu()->call_sync($model, array(), $incoming);

        $this->assertSame(0, $stats['insert'], '写入返回 false 时不得计入 insert 成功数');
        $this->assertSame(1, $stats['failed']);
        $this->assertSame(0, $stats['update']);
        $this->assertSame(0, $stats['delete']);
    }

    public function testSaveFailureIsCountedAsFailedNotUpdate(): void
    {
        $model = new MenuSyncDiffFakeModel();
        $model->seed($this->existingRow(10));
        $model->failSave = true;

        $incoming = $this->incomingRows(array($this->incomingRow()));

        $stats = $this->menu()->call_sync($model, $model->getList('*', array()), $incoming);

        $this->assertSame(0, $stats['update'], '写入返回 false 时不得计入 update 成功数');
        $this->assertSame(1, $stats['failed']);
        $this->assertSame(0, $stats['insert']);
        $this->assertSame(0, $stats['delete']);
    }

    public function testDeleteFailureIsCountedAsFailedNotDelete(): void
    {
        $model = new MenuSyncDiffFakeModel();
        $model->seed($this->existingRow(42));
        $model->failDelete = true;

        $stats = $this->menu()->call_sync($model, $model->getList('*', array()), array());

        $this->assertSame(0, $stats['delete'], '写入返回 false 时不得计入 delete 成功数');
        $this->assertSame(1, $stats['failed']);
        $this->assertSame(0, $stats['insert']);
        $this->assertSame(0, $stats['update']);
    }

    public function testUpdateOpensAndCommitsOwnTransaction(): void
    {
        $model = new MenuSyncDiffFakeModel();
        $model->seed($this->existingRow(10));
        $db = new MenuSyncDiffFakeDatabase();

        $menu = $this->menu();
        $menu->model = $model;
        $menu->incoming = array($this->incomingRow());
        // has_menugroup=false 跳过 menudefine version 写入，本用例只验证事务分支。
        $menu->has_menugroup = false;

        $property = new ReflectionProperty(kernel::class, '__db_instance');
        $property->setAccessible(true);
        $previous = $property->getValue();
        $property->setValue(null, $db);
        try {
            $this->assertTrue($menu->update('ome'));
        } finally {
            $property->setValue(null, $previous);
        }

        $this->assertSame(array('beginTransaction', 'commit'), $db->calls);
        $this->assertFalse($db->isInTransaction(), 'commit 后事务状态应复位');
    }

    public function testUpdateRollsBackWhenBeginTransactionFails(): void
    {
        $model = new MenuSyncDiffFakeModel();
        $model->seed($this->existingRow(10));
        $db = new MenuSyncDiffFakeDatabase();
        $db->failBegin = true;

        $menu = $this->menu();
        $menu->model = $model;
        $menu->incoming = array($this->incomingRow());
        // has_menugroup=false 跳过 menudefine version 写入，本用例只验证 begin 失败分支。
        $menu->has_menugroup = false;

        $property = new ReflectionProperty(kernel::class, '__db_instance');
        $property->setAccessible(true);
        $previous = $property->getValue();
        $property->setValue(null, $db);
        try {
            $this->assertFalse($menu->update('ome'));
        } finally {
            $property->setValue(null, $previous);
        }

        // begin 失败必须回滚复位事务状态，且绝不能走 commit
        $this->assertSame(array('beginTransaction', 'rollBack'), $db->calls);
        $this->assertFalse($db->isInTransaction(), 'begin 失败后事务状态必须复位');
        $this->assertSame(array(), $model->calls, 'begin 失败时不得写入/删除任何菜单行');
    }

    public function testEmptyIncomingGuardDoesNotDelete(): void
    {
        $model = new MenuSyncDiffFakeModel();
        $model->seed($this->existingRow(10));

        $menu = $this->menu();
        $menu->model = $model;
        $menu->incoming = array();

        $this->assertFalse($menu->update('ome'));
        $this->assertSame(array(), $model->calls, '解析到 0 条目标行时绝不能删除在用菜单');
        $this->assertCount(1, $model->rows);
    }

    private function menu(): MenuSyncDiffTestMenu
    {
        return new MenuSyncDiffTestMenu();
    }

    private function incomingRows(array $rows): array
    {
        $keyed = array();
        foreach ($rows as $row) {
            $key = desktop_application_menu::build_menu_key($row);
            $row['menu_key'] = $key;
            $keyed[$key] = $row;
        }
        return $keyed;
    }

    private function existingRow($menu_id, array $overrides = array()): array
    {
        return array_merge($this->identityColumns(), array(
            'menu_id' => $menu_id,
            'menu_title' => '菜单标题',
            'menu_order' => 1,
            'display' => 'true',
            'target' => '',
            'en' => 'order',
            'disabled' => 'false',
            'icon' => 'grid',
        ), $overrides);
    }

    private function incomingRow(array $overrides = array()): array
    {
        return array_merge($this->identityColumns(), array(
            'menu_title' => '菜单标题',
            'menu_order' => 1,
            'display' => 'true',
            'target' => '',
            'en' => 'order',
        ), $overrides);
    }

    private function identityColumns(): array
    {
        return array(
            'menu_type' => 'menu',
            'app_id' => 'ome',
            'workground' => 'order',
            'menu_group' => '订单管理',
            'menu_path' => 'app=ome&ctl=admin_order&act=index',
            'addon' => 'N;',
            'permission' => 'ome.action.order.view',
        );
    }
}

/**
 * 假 menus model：内存表 + 调用流水，用于断言 insert/save/delete 行为。
 */
class MenuSyncDiffFakeModel
{
    public $rows = array();
    public $calls = array();
    public $failInsert = false;
    public $failSave = false;
    public $failDelete = false;
    private $seq = 0;

    public function seed(array $row): void
    {
        $this->rows[$row['menu_id']] = $row;
    }

    public function getList($cols = '*', $filter = array(), $offset = 0, $limit = -1, $orderby = null)
    {
        return array_values($this->rows);
    }

    public function insert(&$data)
    {
        if ($this->failInsert) {
            $this->calls[] = array('insert', null);
            return false;
        }
        $id = ++$this->seq;
        $data['menu_id'] = $id;
        $this->rows[$id] = $data;
        $this->calls[] = array('insert', $id);
        return $id;
    }

    public function save(&$data)
    {
        if ($this->failSave) {
            $this->calls[] = array('save', $data['menu_id']);
            return false;
        }
        $id = $data['menu_id'];
        $this->rows[$id] = isset($this->rows[$id]) ? array_merge($this->rows[$id], $data) : $data;
        $this->calls[] = array('save', $id);
        return true;
    }

    public function delete($filter)
    {
        if ($this->failDelete) {
            $this->calls[] = array('delete', $filter['menu_id']);
            return false;
        }
        $id = $filter['menu_id'];
        unset($this->rows[$id]);
        $this->calls[] = array('delete', $id);
        return true;
    }
}

/**
 * 假数据库连接：只记录事务调用并维护事务状态，不触碰真实 DB。
 */
class MenuSyncDiffFakeDatabase
{
    public $calls = array();
    public $failBegin = false;
    private $inTransaction = false;

    public function isInTransaction(): bool
    {
        return $this->inTransaction;
    }

    public function beginTransaction()
    {
        $this->calls[] = 'beginTransaction';
        // 与 connections::beginTransaction() 同形：先置位、再执行 start transaction，失败时标志位残留 true
        $this->inTransaction = true;
        if ($this->failBegin) {
            return false;
        }
        return true;
    }

    public function commit($status = true)
    {
        $this->calls[] = 'commit';
        $this->inTransaction = false;
        return true;
    }

    public function rollBack()
    {
        $this->calls[] = 'rollBack';
        $this->inTransaction = false;
    }
}
