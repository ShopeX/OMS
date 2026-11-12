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

class profileTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        chdir(__DIR__ . '/../../..');
        include_once 'config/config.php';
        include_once 'app/base/defined.php';
        include_once 'app/base/kernel.php';
    }

    public function testMaskDisplayName(): void
    {
        $this->assertSame('a*', desktop_auth_profile::maskDisplayName('ab'));
        $this->assertSame('ad***in', desktop_auth_profile::maskDisplayName('admin'));
        $this->assertSame('张*', desktop_auth_profile::maskDisplayName('张三'));
        $this->assertSame('管理***测试', desktop_auth_profile::maskDisplayName('管理员测试'));
    }

    public function testForBrowserClientStripsPii(): void
    {
        $public = desktop_auth_profile::forBrowserClient([
            'id' => 1,
            'login_name' => 'admin',
            'name' => '管理员',
            'is_super' => true,
            'roles' => [],
            'need_change_password' => null,
        ]);

        $this->assertSame(1, $public['id']);
        $this->assertSame('管*员', $public['display_name']);
        $this->assertTrue($public['is_super']);
        $this->assertArrayNotHasKey('login_name', $public);
        $this->assertArrayNotHasKey('name', $public);
    }
}
