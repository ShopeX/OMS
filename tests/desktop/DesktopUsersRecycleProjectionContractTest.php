<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class DesktopUsersRecycleProjectionContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = dirname(__DIR__, 2);
    }

    public function testUsersDeleteSnapshotKeepsPamAndRolesButExcludesScopeProjectionTables(): void
    {
        $source = file_get_contents($this->root . '/app/desktop/model/users.php');
        $deleteBlockStart = strpos($source, "'delete'  => array(");
        $deleteBlockEnd = strpos($source, '    );', $deleteBlockStart);
        $deleteBlock = substr($source, $deleteBlockStart ?: 0, ($deleteBlockEnd ?: 0) - ($deleteBlockStart ?: 0));

        $this->assertIsString($source);
        $this->assertNotFalse($deleteBlockStart);
        $this->assertNotFalse($deleteBlockEnd);
        $this->assertIsString($deleteBlock);
        $this->assertStringContainsString("'pam_account:account@pam' => array('*')", $deleteBlock);
        $this->assertStringContainsString("'roles'                   => array('*')", $deleteBlock);
        $this->assertStringNotContainsString('organization_ops', $deleteBlock);
        $this->assertStringNotContainsString('cos_ops', $deleteBlock);
        $this->assertStringNotContainsString('operation_ops', $deleteBlock);
        $this->assertStringNotContainsString('branch_ops', $deleteBlock);
        $this->assertStringNotContainsString('group_ops', $deleteBlock);
    }

    public function testUsersModelHasDeleteCleanupButNoProjectionRestoreHook(): void
    {
        $source = file_get_contents($this->root . '/app/desktop/model/users.php');

        $this->assertIsString($source);
        $this->assertStringContainsString('public function suf_delete(' . '$userid' . ')', $source);
        $this->assertStringContainsString("delete(array('user_id' => " . '$userid' . '))', $source);
        $this->assertStringNotContainsString('function suf_restore', $source);
        $this->assertStringNotContainsString('function post_restore', $source);
    }

    public function testOrgScopeCapabilityOwnsProjectionReplacementOutsideUsersRestore(): void
    {
        $source = file_get_contents($this->root . '/app/desktop/lib/capability/user/orgscope.php');

        $this->assertIsString($source);
        $this->assertStringContainsString("model('organization_ops')->saveUserOrganizations", $source);
        $this->assertStringContainsString("kernel::single('organization_cos')->save_operation_permission", $source);
        $this->assertStringContainsString("model('operation_ops')", $source);
        $this->assertStringContainsString("delete(['op_id' => " . '$userId' . '])', $source);
    }
}
