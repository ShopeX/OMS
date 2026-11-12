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
final class LegacyRolesControllerAdapterTest extends TestCase
{
    public function testSaveDelegatesToCapabilityInsteadOfDirectModelPersistence(): void
    {
        if (!class_exists('desktop_controller', false)) {
            eval('class desktop_controller { public $app; public array $beginCalls = []; public array $endCalls = []; public function __construct($app) { $this->app = $app; } public function begin($url = null) { $this->beginCalls[] = $url; } public function end($success = false, $message = null) { $this->endCalls[] = [$success, $message]; } }');
        }
        if (!class_exists('app', false)) {
            eval('class app { public static $desktop; public static function get($name) { return self::$desktop; } }');
        }
        if (!class_exists('kernel', false)) {
            eval('class kernel { public static function single($name) { return new class { public function get_adminpanel() { return []; } public function get_others() { return []; } public function get_permission_per() { return []; } }; } public static function database() { return new stdClass(); } }');
        }

        $app = new class {
            public int $rolesModelCalls = 0;

            public function model($name)
            {
                if ($name === 'roles') {
                    $this->rolesModelCalls++;
                    if ($this->rolesModelCalls > 1) {
                        throw new RuntimeException('legacy roles controller should not access roles model directly in save()');
                    }
                    return new class {
                        public function check_gname(string $name)
                        {
                            return false;
                        }

                        public function save(array $data): bool
                        {
                            return true;
                        }
                    };
                }
                return new stdClass();
            }

            public function _($message)
            {
                return $message;
            }
        };

        app::$desktop = $app;

        require_once dirname(__DIR__, 2) . '/app/desktop/lib/capability/roles.php';
        require_once dirname(__DIR__, 2) . '/app/desktop/controller/roles.php';

        $controller = new desktop_ctl_roles($app);
        $model = new class {
            public array $saved = [];

            public function check_gname(string $name)
            {
                return false;
            }

            public function save(array $data): bool
            {
                $this->saved[] = $data;
                return true;
            }
        };

        $database = new class {
            public function beginTransaction(): string
            {
                return 'tx';
            }

            public function commit($tx = null): void
            {
            }

            public function rollBack($tx = null): void
            {
            }
        };

        $capability = new desktop_capability_roles($model, $database);

        $reflection = new ReflectionObject($controller);
        $property = $reflection->getProperty('capability');
        $property->setAccessible(true);
        $property->setValue($controller, $capability);

        $_POST = ['role_name' => '仓库管理员', 'workground' => ['desktop.users']];
        $controller->save();

        $this->assertCount(1, $model->saved);
        $this->assertSame([true, '保存成功'], $controller->endCalls[0]);
    }
}
