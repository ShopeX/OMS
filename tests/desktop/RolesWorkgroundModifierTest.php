<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
final class RolesWorkgroundModifierTest extends TestCase
{
    public function testWidgetOnlyReturnsItsMenuTitle(): void
    {
        list($model) = $this->createRolesModel($this->menuFixtures());

        $this->assertSame(
            ['绿色通道'],
            $model->modifier_workground(['ome_desktop_widgets_greenchannel'])
        );
    }

    public function testMixedPermissionsAndWidgetKeepInputOrder(): void
    {
        list($model) = $this->createRolesModel($this->menuFixtures());

        $this->assertSame(
            ['用户管理', '绿色通道', '回收站'],
            $model->modifier_workground([
                'users',
                'ome_desktop_widgets_greenchannel',
                'recycle',
            ])
        );
    }

    public function testUnknownValuesAreOmitted(): void
    {
        list($model) = $this->createRolesModel($this->menuFixtures());

        $this->assertSame(
            ['用户管理', '回收站'],
            $model->modifier_workground(['users', 'missing-value', 'recycle'])
        );
    }

    public function testSerializedAndArrayValuesAreBothSupported(): void
    {
        list($model) = $this->createRolesModel($this->menuFixtures());
        $values = ['users', 'ome_desktop_widgets_greenchannel'];

        $this->assertSame(['用户管理', '绿色通道'], $model->modifier_workground($values));
        $this->assertSame(['用户管理', '绿色通道'], $model->modifier_workground(serialize($values)));
    }

    public function testQueriesWidgetAddonsOnlyForValuesUnresolvedAsPermissions(): void
    {
        list($model, $menusModel) = $this->createRolesModel($this->menuFixtures());

        $model->modifier_workground(['users', 'ome_desktop_widgets_greenchannel', 'missing-value']);

        $this->assertSame([
            [
                'permission,menu_title',
                [
                    'menu_type' => 'permission',
                    'permission' => ['users', 'ome_desktop_widgets_greenchannel', 'missing-value'],
                ],
            ],
            [
                'addon,menu_title',
                [
                    'menu_type' => 'widgets',
                    'addon' => [1 => 'ome_desktop_widgets_greenchannel', 2 => 'missing-value'],
                ],
            ],
        ], $menusModel->queries);

        $model->modifier_workground(['missing-value']);
        $this->assertCount(2, $menusModel->queries, 'Unknown values must be cached as processed.');
    }

    private function menuFixtures(): array
    {
        return [
            ['menu_type' => 'permission', 'permission' => 'users', 'menu_title' => '用户管理'],
            ['menu_type' => 'widgets', 'addon' => 'ome_desktop_widgets_greenchannel', 'menu_title' => '绿色通道'],
            ['menu_type' => 'permission', 'permission' => 'recycle', 'menu_title' => '回收站'],
        ];
    }

    private function createRolesModel(array $menus): array
    {
        if (!class_exists('dbeav_model', false)) {
            eval('class dbeav_model { public $app; }');
        }
        require_once dirname(__DIR__, 2) . '/app/desktop/model/roles.php';

        $menusModel = new class($menus) {
            private $menus;
            public $queries = [];

            public function __construct(array $menus)
            {
                $this->menus = $menus;
            }

            public function getList($columns, $filter)
            {
                $this->queries[] = [$columns, $filter];
                $rows = [];
                foreach ($this->menus as $menu) {
                    if ($menu['menu_type'] !== $filter['menu_type']) {
                        continue;
                    }
                    $field = $filter['menu_type'] === 'widgets' ? 'addon' : 'permission';
                    if (in_array($menu[$field], $filter[$field], true)) {
                        $rows[] = $menu;
                    }
                }
                return $rows;
            }
        };

        $model = new desktop_mdl_roles();
        $model->app = new class($menusModel) {
            private $menusModel;

            public function __construct($menusModel)
            {
                $this->menusModel = $menusModel;
            }

            public function model($name)
            {
                return $this->menusModel;
            }
        };

        return [$model, $menusModel];
    }
}
