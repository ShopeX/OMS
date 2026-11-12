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
 * WITHOUT WARRANTIES OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * 基础设置（openapi/admin_setting）Vue 写链路契约测试。
 *
 * 覆盖本单硬约束：读链路走 finderDispatch、写动作独立成 act、请求体统一 getRequestInput、
 * 权限判断只在控制器层（capability 不判权限）、写校验与落库落 openapi_capability_setting、
 * 成功/失败文案与被删基线一致、legacy 控制器冻结。
 */
class OpenapiSettingControllerTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    private function read(string $relative): string
    {
        $path = $this->root() . '/' . $relative;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /**
     * 取出某个 public 方法的实现片段（到下一个 public function 之前）。
     */
    private function methodBody(string $source, string $signature): string
    {
        $start = strpos($source, $signature);
        $this->assertNotFalse($start, '缺少方法：' . $signature);
        $next = strpos($source, 'public function', $start + strlen($signature));

        return $next === false ? substr($source, $start) : substr($source, $start, $next - $start);
    }

    /**
     * 读链路必须交给框架 finderDispatch，不得手搓 action 分发。
     */
    public function testReadChainGoesThroughFinderDispatch(): void
    {
        $source = $this->read('app/openapi/controller/api/setting.php');

        $this->assertStringContainsString('finderDispatch', $source);
        $this->assertStringNotContainsString('switch ($action)', $source);
        $this->assertStringNotContainsString("switch (\$this->action)", $source);
    }

    /**
     * 写动作必须是独立 public act（save / setStatus），不得复用 legacy act 名。
     */
    public function testWriteActionsAreIndependentPublicActs(): void
    {
        $source = $this->read('app/openapi/controller/api/setting.php');

        $this->assertStringContainsString('public function save(', $source);
        $this->assertStringContainsString('public function setStatus(', $source);
        $this->assertStringNotContainsString('public function addNew(', $source);
        $this->assertStringNotContainsString('public function edit(', $source);
    }

    /**
     * 写链路分层：控制器只做权限/入参/出参，校验与落库委托 capability，控制器不碰模型。
     */
    public function testWriteActsDelegateToCapability(): void
    {
        $source = $this->read('app/openapi/controller/api/setting.php');

        $this->assertStringContainsString('$this->capability()->save(', $source);
        $this->assertStringContainsString('$this->capability()->setStatus(', $source);

        foreach (['public function save(', 'public function setStatus('] as $signature) {
            $body = $this->methodBody($source, $signature);
            $this->assertStringNotContainsString("model('setting')", $body, $signature . ' 不应直接访问模型');
            $this->assertStringNotContainsString('->save($data)', $body, $signature . ' 不应直接落库');
            $this->assertStringNotContainsString('->dump(', $body, $signature . ' 不应直接查库');
        }
    }

    /**
     * 新增 lib 文件不在框架 lib 索引内，须显式引入（否则运行期报 Don't find lib file）。
     */
    public function testCapabilityIsRequiredExplicitly(): void
    {
        $source = $this->read('app/openapi/controller/api/setting.php');

        $this->assertStringContainsString("require_once dirname(__FILE__) . '/../../lib/capability/setting.php'", $source);
    }

    /**
     * 权限判断只在控制器层，权限点取自菜单 9017 的 permission；capability 内不得出现权限注解。
     */
    public function testPermissionStaysInControllerOnly(): void
    {
        $controller = $this->read('app/openapi/controller/api/setting.php');
        $capability = $this->read('app/openapi/lib/capability/setting.php');

        $this->assertStringContainsString("RequirePermission('setting_openapi_basic')", $controller);
        $this->assertStringNotContainsString('RequirePermission', $capability);
    }

    /**
     * 请求体统一走框架 getRequestInput()（JSON 与表单双兼容），不得直接读超全局。
     */
    public function testWriteActsUseFrameworkRequestInput(): void
    {
        $source = $this->read('app/openapi/controller/api/setting.php');

        $this->assertStringContainsString('getRequestInput()', $source);
        $this->assertStringNotContainsString('$_POST', $source);
        $this->assertStringNotContainsString('$_REQUEST', $source);
    }

    /**
     * capability 承担校验与落库，文案与被删基线一致（前端直接透出 message）。
     */
    public function testCapabilityOwnsValidationAndPersistence(): void
    {
        $source = $this->read('app/openapi/lib/capability/setting.php');

        foreach (['标识已存在', '配置信息不存在', '保存成功', '保存失败', '更新成功'] as $text) {
            $this->assertStringContainsString($text, $source);
        }
        $this->assertStringContainsString('InvalidArgumentException', $source);
        $this->assertStringContainsString('RuntimeException', $source);
        $this->assertStringContainsString('$model->save($data)', $source);
        // dbeav_model::save() 形参按引用传递，必须传变量
        $this->assertStringNotContainsString('->save([', $source);
    }

    /**
     * 控制器成功/失败出口契约保持不变。
     */
    public function testResponseContractMatchesDeletedBaseline(): void
    {
        $source = $this->read('app/openapi/controller/api/setting.php');

        $this->assertStringContainsString('$this->respond(', $source);
        $this->assertStringContainsString('$this->fail(', $source);
    }

    /**
     * 回收站不可实现（表无 is_del/disabled 列）；保持关闭，避免出现无落点的删除入口。
     */
    public function testRecycleStaysDisabled(): void
    {
        $source = $this->read('app/openapi/controller/api/setting.php');

        $this->assertStringContainsString("'use_buildin_recycle'    => false", $source);
    }

    /**
     * 菜单 target 落在 Vue 路由（站点以 target 反查菜单，缺失则整页不可达）。
     */
    public function testDesktopXmlCarriesVueTarget(): void
    {
        $source = $this->read('app/openapi/desktop.xml');

        $this->assertStringContainsString("target='/m-openapi/setting/index'", $source);
    }

    /**
     * legacy 控制器冻结：添加 / 编辑 / 状态切换的内联实现仍在。
     */
    public function testLegacyControllerStaysFrozen(): void
    {
        $source = $this->read('app/openapi/controller/admin/setting.php');

        $this->assertStringContainsString('function addNew(', $source);
        $this->assertStringContainsString('function edit(', $source);
        $this->assertStringContainsString('function setStatus(', $source);
    }
}
