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

use PHPUnit\Framework\TestCase;

/**
 * 失败重试（erpapi/admin_api_fail）Vue 化契约测试
 *
 * 覆盖本单硬约束：读链路走 finderDispatch、写动作独立成 act、权限判断只在控制器层、
 * 能力层保留 legacy 的 500 上限与文案、真实外呼仍走 legacy 重试任务、legacy 控制器冻结。
 */
class ErpapiApiFailControllerTest extends TestCase
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
     * 读链路必须交给框架 finderDispatch（$finderActions 白名单），不得手搓 action 分发。
     */
    public function testReadChainGoesThroughFinderDispatch(): void
    {
        $source = $this->read('app/erpapi/controller/api/api/fail.php');

        $this->assertStringContainsString('finderDispatch', $source);
        $this->assertStringNotContainsString('switch ($action)', $source);
        $this->assertStringNotContainsString("switch (\$this->action)", $source);
    }

    /**
     * 写动作必须是独立 public act（retryCheck / retryDo），不得把 legacy 的 act 名直接暴露。
     */
    public function testWriteActionsAreIndependentPublicActs(): void
    {
        $source = $this->read('app/erpapi/controller/api/api/fail.php');

        $this->assertStringContainsString('function retryCheck(', $source);
        $this->assertStringContainsString('function retryDo(', $source);
        $this->assertStringNotContainsString('function retry_view(', $source);
        $this->assertStringNotContainsString('function retry_do(', $source);
    }

    /**
     * 权限判断只在控制器层：注解式 RequirePermission('setting_api_log')（菜单权限标识）。
     */
    public function testControllerCarriesSettingApiLogPermissionAnnotation(): void
    {
        $source = $this->read('app/erpapi/controller/api/api/fail.php');

        $this->assertStringContainsString("RequirePermission('setting_api_log')", $source);
    }

    /**
     * 能力层禁止再做权限判断（权限一律在控制器层）。
     */
    public function testCapabilityMustNotJudgePermission(): void
    {
        $source = $this->read('app/erpapi/lib/capability/api/fail.php');

        $this->assertStringNotContainsString('RequirePermission', $source);
        $this->assertStringNotContainsString('hasPermission', $source);
        $this->assertStringNotContainsString('checkPermission', $source);
    }

    /**
     * 能力层保留 legacy retry_view 的拒绝契约：空选 / 超过 500 条。
     */
    public function testCapabilityKeepsLegacyEmptyAndCap500Rejections(): void
    {
        $source = $this->read('app/erpapi/lib/capability/api/fail.php');

        $this->assertStringContainsString('没有选择执行的记录!', $source);
        $this->assertStringContainsString('每次最多可执行500条记录!', $source);
    }

    /**
     * 真实重试仍复用 legacy 重试任务，不得绕过网关自搓外呼。
     */
    public function testCapabilityDelegatesRetryToLegacyTask(): void
    {
        $source = $this->read('app/erpapi/lib/capability/api/fail.php');

        $this->assertStringContainsString("kernel::single('erpapi_autotask_task_retryapi')", $source);
        $this->assertStringNotContainsString('curl_', $source);
    }

    /**
     * 工具栏动作由后端下发（与 legacy 的「批量重试」一致）。
     */
    public function testToolbarActionBatchRetryIsDeclaredInController(): void
    {
        $source = $this->read('app/erpapi/controller/api/api/fail.php');

        $this->assertStringContainsString("'batch-retry'", $source);
        $this->assertStringContainsString('批量重试', $source);
    }

    /**
     * legacy 控制器冻结：仍保有 retry_view / retry_do 内联实现与权限门，未被动过。
     */
    public function testLegacyControllerStaysFrozen(): void
    {
        $source = $this->read('app/erpapi/controller/admin/api/fail.php');

        $this->assertStringContainsString('retry_view', $source);
        $this->assertStringContainsString('retry_do', $source);
    }
}
