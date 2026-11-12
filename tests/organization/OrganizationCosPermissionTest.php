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

if (!class_exists('dbeav_model', false)) {
    class dbeav_model
    {
        public $app;
    }
}

if (!class_exists('app', false)) {
    class app
    {
        public static $organization;

        public static function get($name)
        {
            return self::$organization;
        }
    }
}

#[RunTestsInSeparateProcesses]
final class OrganizationCosPermissionTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testEmptyDealerShopSelectionClearsExistingBindingsAndReturnsSuccess(): void
    {
        require_once $this->root . '/app/organization/lib/cos.php';

        $cosOpsModel = new class {
            public array $deleted = [];
            public array $inserted = [];

            public function delete(array $filter): bool
            {
                $this->deleted[] = $filter;
                return true;
            }

            public function insert(array $data): bool
            {
                $this->inserted[] = $data;
                return true;
            }
        };

        app::$organization = new class($cosOpsModel) {
            public function __construct(private object $cosOpsModel)
            {
            }

            public function model($name)
            {
                return $this->cosOpsModel;
            }
        };

        $service = new organization_cos();
        $result = $service->save_operation_permission([
            'user_id' => 9,
            'dealer_shop_conf' => ['cosIds' => []],
        ]);

        $this->assertTrue($result);
        $this->assertSame([['op_id' => 9]], $cosOpsModel->deleted);
        $this->assertSame([], $cosOpsModel->inserted);
    }
}
