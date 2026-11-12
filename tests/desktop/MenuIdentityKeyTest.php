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
 * build_menu_key() 纯函数契约：7 个身份列参与哈希，其余列不参与；
 * NULL / 未设置 / 'N;' / '' 归一到同一个空值。
 */
class MenuIdentityKeyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        chdir(dirname(__DIR__, 2));
        include_once 'config/config.php';
        include_once 'app/base/defined.php';
        include_once 'app/base/kernel.php';
    }

    public function testSameIdentityColumnsProduceSameKey(): void
    {
        $a = desktop_application_menu::build_menu_key($this->baseRow());
        $b = desktop_application_menu::build_menu_key($this->baseRow());

        $this->assertSame($a, $b);
        $this->assertSame(40, strlen($a));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $a);
    }

    public function testNullEmptyAndSerializedNullNormalizeToSameKey(): void
    {
        $serialized = $this->baseRow();
        $serialized['addon'] = 'N;';
        $null = $this->baseRow();
        $null['addon'] = null;
        $empty = $this->baseRow();
        $empty['addon'] = '';

        $expected = desktop_application_menu::build_menu_key($serialized);
        $this->assertSame($expected, desktop_application_menu::build_menu_key($null));
        $this->assertSame($expected, desktop_application_menu::build_menu_key($empty));
    }

    public function testMissingColumnsAreTreatedAsEmptyStrings(): void
    {
        $full = $this->baseRow();
        $full['permission'] = '';
        $full['menu_group'] = '';

        $missing = $this->baseRow();
        unset($missing['permission'], $missing['menu_group']);

        $this->assertSame(
            desktop_application_menu::build_menu_key($full),
            desktop_application_menu::build_menu_key($missing)
        );
    }

    public function testEachIdentityColumnChangesTheKey(): void
    {
        $base = desktop_application_menu::build_menu_key($this->baseRow());

        foreach (array('menu_type', 'app_id', 'workground', 'menu_group', 'menu_path', 'addon', 'permission') as $col) {
            $row = $this->baseRow();
            $row[$col] = 'changed-' . $col;
            $this->assertNotSame($base, desktop_application_menu::build_menu_key($row), $col . ' must be part of identity');
        }
    }

    public function testNonIdentityColumnsDoNotChangeTheKey(): void
    {
        $base = desktop_application_menu::build_menu_key($this->baseRow());

        foreach (array('menu_id', 'menu_title', 'menu_order', 'target', 'display', 'en', 'icon', 'disabled') as $col) {
            $row = $this->baseRow();
            $row[$col] = 'changed';
            $this->assertSame($base, desktop_application_menu::build_menu_key($row), $col . ' must not be part of identity');
        }
    }

    private function baseRow(): array
    {
        return array(
            'menu_type' => 'menu',
            'app_id' => 'ome',
            'workground' => 'order',
            'menu_group' => '订单管理',
            'menu_path' => 'app=ome&ctl=admin_order&act=index',
            'addon' => serialize(array('url_params' => array('p[0]' => '1'))),
            'permission' => 'ome.action.order.view',
        );
    }
}
