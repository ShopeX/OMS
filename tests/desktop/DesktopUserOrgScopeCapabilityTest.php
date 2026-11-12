<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
final class DesktopUserOrgScopeCapabilityTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testCapabilityFileExistsAtAutoloadPath(): void
    {
        $this->assertFileExists($this->root . '/app/desktop/lib/capability/user/orgscope.php');
    }

    public function testUpdatePersistsOrgAndDealerScope(): void
    {
        require_once $this->root . '/app/desktop/lib/capability/user/orgscope.php';

        $savedOrgs = [];
        $savedDealer = [];
        $savedOperation = [];

        $capability = new desktop_capability_user_orgscope(
            static function (int $userId, array $orgIds) use (&$savedOrgs): bool {
                $savedOrgs[] = [$userId, $orgIds];
                return true;
            },
            static function (array $payload) use (&$savedDealer): bool {
                $savedDealer[] = $payload;
                return true;
            },
            static function (array $payload) use (&$savedOperation): bool {
                $savedOperation[] = $payload;
                return true;
            }
        );

        $result = $capability->update(9, [
            'org_id' => [1, 2],
            'store_org_conf' => ['orgIds' => ['5,6']],
            'dealer_shop_conf' => ['cosIds' => ['9,10']]
        ]);

        $this->assertSame(['success' => true], $result);
        $this->assertSame([[9, ['5', '6']]], $savedOrgs);
        $this->assertSame([[ 'user_id' => 9, 'dealer_shop_conf' => ['cosIds' => ['9,10']] ]], $savedDealer);
        $this->assertSame([[ 'user_id' => 9, 'org_id' => [1, 2] ]], $savedOperation);
    }

    public function testUpdateClearsOrgAndDealerScopeWhenExplicitlyEmpty(): void
    {
        require_once $this->root . '/app/desktop/lib/capability/user/orgscope.php';

        $savedOrgs = [];
        $savedDealer = [];

        $capability = new desktop_capability_user_orgscope(
            static function (int $userId, array $orgIds) use (&$savedOrgs): bool {
                $savedOrgs[] = [$userId, $orgIds];
                return true;
            },
            static function (array $payload) use (&$savedDealer): bool {
                $savedDealer[] = $payload;
                return true;
            },
            static function (array $payload): bool {
                return true;
            }
        );

        $result = $capability->update(9, [
            'store_org_conf' => ['orgIds' => []],
            'dealer_shop_conf' => ['cosIds' => []],
            'org_id' => []
        ]);

        $this->assertSame(['success' => true], $result);
        $this->assertSame([[9, []]], $savedOrgs);
        $this->assertSame([[ 'user_id' => 9, 'dealer_shop_conf' => ['cosIds' => []] ]], $savedDealer);
    }
}
