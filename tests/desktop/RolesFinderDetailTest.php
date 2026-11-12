<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
final class RolesFinderDetailTest extends TestCase
{
    public function testDetailBuildsPermissionTreeWithFiveBulkMenuReads(): void
    {
        $rows = $this->menuFixtures();
        list($finder, $roles, $menus, $render) = $this->createFinder($rows);

        $result = $finder->detail_indo(7);

        $this->assertSame(1, $roles->dumpCount);
        $this->assertSame(5, $menus->getListCount);
        $this->assertSame(0, $menus->dumpCount);
        $this->assertSame(
            ['menu', 'permission', 'workground', 'widgets', 'adminpanel'],
            array_column($menus->queries, 'menu_type')
        );
        $this->assertSame(
            ['menu_type' => 'workground', 'disabled' => 'false', 'display' => 'true'],
            $menus->queries[2]
        );
        $this->assertSame(['menu_type' => 'widgets'], $menus->queries[3]);
        $this->assertSame(['menu_type' => 'adminpanel'], $menus->queries[4]);

        $this->assertSame(7, $result['items']['role_id']);
        $this->assertSame('仓库角色', $result['items']['role_name']);
        $this->assertSame(['p_selected', 'p_shared', 'widget_selected', 'admin_selected', 'other_selected'], $result['items']['workground']);
        $this->assertSame(['region' => ['east']], $result['items']['data_authority']);

        $this->assertSame(['wg_one', 'wg_two'], array_column($result['workgrounds'], 'workground'));
        $this->assertSame(1, $result['workgrounds'][0]['checked']);
        $this->assertArrayNotHasKey('checked', $result['workgrounds'][1]);
        $this->assertSame(['p_selected', 'p_shared'], array_column($result['workgrounds'][0]['permissions'], 'permission'));
        $this->assertSame(['已选菜单权限', '复用菜单权限'], array_column($result['workgrounds'][0]['permissions'], 'menu_title'));
        $this->assertSame([1, 1], array_column($result['workgrounds'][0]['permissions'], 'checked'));
        $this->assertSame(['wg_one', 'wg_one'], array_column($result['workgrounds'][0]['permissions'], 'role_workground'));
        $this->assertSame(['p_unselected'], array_column($result['workgrounds'][1]['permissions'], 'permission'));
        $this->assertSame(0, $result['workgrounds'][1]['permissions'][0]['checked']);
        $this->assertSame('wg_two', $result['workgrounds'][1]['permissions'][0]['role_workground']);

        $this->assertSame(['widget_selected'], array_column($result['widgets'], 'addon'));
        $this->assertSame(['admin_selected', 'admin_unselected'], array_column($result['adminpanels'], 'permission'));
        $this->assertSame([1, 0], array_column($result['adminpanels'], 'checked'));
        $this->assertSame(1, $result['flg']);
        $this->assertSame(['other_selected', 'other_unselected'], array_column($result['others'], 'permission'));
        $this->assertSame([1, 0], array_column($result['others'], 'checked'));
        $this->assertSame(1, $result['othersflg']);

        $finder->setVueMode(false);
        ob_start();
        $legacyResult = $finder->detail_indo(7);
        $legacyOutput = ob_get_clean();
        $this->assertNull($legacyResult);
        $this->assertSame('users/users_roles.html', $legacyOutput);
        $this->assertSame($render->pagedata['workgrounds'], $result['workgrounds']);
        $this->assertSame($render->pagedata['widgets'], $result['widgets']);
        $this->assertSame($render->pagedata['adminpanels'], $result['adminpanels']);
        $this->assertSame($render->pagedata['others'], $result['others']);
        $this->assertSame($render->pagedata['flg'], $result['flg']);
        $this->assertSame($render->pagedata['othersflg'], $result['othersflg']);
        $this->assertSame(2, $roles->dumpCount);
        $this->assertSame(10, $menus->getListCount);

        $finder->setVueMode(true);
        $menus->appendRows($this->largeFixture(100));
        $finder->detail_indo(7);
        $this->assertSame(3, $roles->dumpCount);
        $this->assertSame(15, $menus->getListCount, 'Menu query count must remain five per detail call.');
        $this->assertSame(0, $menus->dumpCount);
    }

    private function createFinder(array $rows): array
    {
        if (!class_exists('kernel', false)) {
            eval('class kernel { public static $service; public static function single($name) { return self::$service; } }');
        }

        kernel::$service = new class {
            public function get_permission_per() { throw new RuntimeException('slow helper called: get_permission_per'); }
            public function get_adminpanel() { throw new RuntimeException('slow helper called: get_adminpanel'); }
            public function get_others() { throw new RuntimeException('slow helper called: get_others'); }
        };

        require_once dirname(__DIR__, 2) . '/app/desktop/lib/finder/roles.php';

        $roles = new class {
            public $dumpCount = 0;
            public function dump($roleId)
            {
                ++$this->dumpCount;
                return [
                    'role_name' => '仓库角色',
                    'workground' => serialize(['p_selected', 'p_shared', 'widget_selected', 'admin_selected', 'other_selected']),
                    'data_authority' => serialize(['region' => ['east']]),
                ];
            }
        };
        $menus = new class($rows) {
            private $rows;
            public $queries = [];
            public $getListCount = 0;
            public $dumpCount = 0;
            public function __construct(array $rows) { $this->rows = $rows; }
            public function appendRows(array $rows) { $this->rows = array_merge($this->rows, $rows); }
            public function dump($filter) { ++$this->dumpCount; throw new RuntimeException('menus dump called'); }
            public function getList($columns, $filter)
            {
                ++$this->getListCount;
                $this->queries[] = $filter;
                $result = [];
                foreach ($this->rows as $row) {
                    foreach ($filter as $field => $value) {
                        if (!array_key_exists($field, $row) || $row[$field] != $value) {
                            continue 2;
                        }
                    }
                    $result[] = $row;
                }
                return $result;
            }
        };
        $render = new class {
            public $pagedata = [];
            public function fetch($template) { return $template; }
        };
        $app = new class($roles, $menus, $render) {
            private $roles;
            private $menus;
            private $render;
            public function __construct($roles, $menus, $render) { $this->roles = $roles; $this->menus = $menus; $this->render = $render; }
            public function model($name) { return $name === 'roles' ? $this->roles : $this->menus; }
            public function render() { return $this->render; }
        };

        $finder = new desktop_finder_roles($app);
        $finder->setVueMode(true);
        return [$finder, $roles, $menus, $render];
    }

    private function menuFixtures(): array
    {
        return [
            ['menu_type' => 'menu', 'menu_id' => 'm1', 'permission' => 'p_selected', 'workground' => 'wg_one'],
            ['menu_type' => 'menu', 'menu_id' => 'm2', 'permission' => 'p_shared', 'workground' => 'wg_one'],
            ['menu_type' => 'menu', 'menu_id' => 'm3', 'permission' => 'p_shared', 'workground' => 'wg_one'],
            ['menu_type' => 'menu', 'menu_id' => 'm4', 'permission' => 'p_unselected', 'workground' => 'wg_two'],
            ['menu_type' => 'permission', 'permission' => 'p_selected', 'menu_title' => '已选菜单权限', 'disabled' => 'false', 'display' => 'true'],
            ['menu_type' => 'permission', 'permission' => 'p_shared', 'menu_title' => '复用菜单权限', 'disabled' => 'false', 'display' => 'true'],
            ['menu_type' => 'permission', 'permission' => 'p_unselected', 'menu_title' => '未选菜单权限', 'disabled' => 'false', 'display' => 'true'],
            ['menu_type' => 'permission', 'permission' => 'admin_selected', 'menu_title' => '已选控制面板', 'disabled' => 'false', 'display' => 'true'],
            ['menu_type' => 'permission', 'permission' => 'admin_unselected', 'menu_title' => '未选控制面板', 'disabled' => 'false', 'display' => 'true'],
            ['menu_type' => 'permission', 'permission' => 'admin_disabled', 'menu_title' => '禁用控制面板', 'disabled' => 'true', 'display' => 'true'],
            ['menu_type' => 'permission', 'permission' => 'other_selected', 'menu_title' => '已选其他', 'disabled' => 'false', 'display' => 'true'],
            ['menu_type' => 'permission', 'permission' => 'other_unselected', 'menu_title' => '未选其他', 'disabled' => 'false', 'display' => 'true'],
            ['menu_type' => 'permission', 'permission' => 'other_hidden', 'menu_title' => '隐藏其他', 'disabled' => 'false', 'display' => 'false'],
            ['menu_type' => 'workground', 'menu_id' => 'wg_menu_1', 'workground' => 'wg_one', 'menu_title' => '工作台一', 'disabled' => 'false', 'display' => 'true'],
            ['menu_type' => 'workground', 'menu_id' => 'wg_menu_2', 'workground' => 'wg_two', 'menu_title' => '工作台二', 'disabled' => 'false', 'display' => 'true'],
            ['menu_type' => 'workground', 'menu_id' => 'wg_disabled', 'workground' => 'wg_disabled', 'disabled' => 'true', 'display' => 'true'],
            ['menu_type' => 'widgets', 'addon' => 'widget_selected', 'menu_title' => '已选挂件'],
            ['menu_type' => 'widgets', 'addon' => 'widget_unselected', 'menu_title' => '未选挂件'],
            ['menu_type' => 'adminpanel', 'permission' => 'admin_selected'],
            ['menu_type' => 'adminpanel', 'permission' => 'admin_unselected'],
            ['menu_type' => 'adminpanel', 'permission' => 'admin_disabled'],
        ];
    }

    private function largeFixture(int $count): array
    {
        $rows = [];
        for ($i = 0; $i < $count; ++$i) {
            $permission = 'bulk_' . $i;
            $rows[] = ['menu_type' => 'menu', 'menu_id' => 'bulk_menu_' . $i, 'permission' => $permission, 'workground' => 'wg_one'];
            $rows[] = ['menu_type' => 'permission', 'permission' => $permission, 'menu_title' => $permission, 'disabled' => 'false', 'display' => 'true'];
        }
        return $rows;
    }
}
