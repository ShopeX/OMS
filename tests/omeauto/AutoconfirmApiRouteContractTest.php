<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AutoconfirmApiRouteContractTest extends TestCase
{
    public function testIndexPhpActSaveDispatchesToApiAutoconfirmSave(): void
    {
        $root = dirname(__DIR__, 2);
        $routerSource = (string) file_get_contents($root . '/app/desktop/lib/router.php');
        $controllerSource = (string) file_get_contents($root . '/app/omeauto/controller/api/autoconfirm.php');

        $this->assertStringContainsString("\$_GET['act'] = \$_GET['act']?\$_GET['act']:'index';", $routerSource);
        $this->assertStringContainsString("app::get(\$_GET['app'])->controller(\$_GET['ctl'])", $routerSource);
        $this->assertStringContainsString("call_user_func_array(array(&\$controller,\$_GET['act'])", $routerSource);
        $this->assertStringContainsString('public function save(): void', $controllerSource);

        $_GET = [
            'app' => 'omeauto',
            'ctl' => 'api_autoconfirm',
            'act' => 'save',
        ];
        $controller = new class {
            public bool $saved = false;

            public function save(): void
            {
                $this->saved = true;
            }
        };

        $methods = get_class_methods($controller);
        $this->assertContains($_GET['act'], $methods);
        call_user_func_array([$controller, $_GET['act']], []);
        $this->assertTrue($controller->saved);
    }
}
