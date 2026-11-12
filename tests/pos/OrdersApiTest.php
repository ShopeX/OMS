<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

if (!defined('OMS_TEST_ROOT')) {
    define('OMS_TEST_ROOT', realpath(dirname(__DIR__, 2)));
}

if (!defined('APP_DIR')) {
    define('APP_DIR', OMS_TEST_ROOT . '/app');
}

include_once OMS_TEST_ROOT . '/config/config.php';
include_once OMS_TEST_ROOT . '/app/base/defined.php';
include_once OMS_TEST_ROOT . '/app/base/kernel.php';

class OrdersApiTest extends TestCase
{
    public function testOrdersListApiRequiresAuth(): void
    {
        $authEnforced = $this->checkAuthIsEnforced();

        $this->assertTrue(
            $authEnforced,
            'POS Orders API should enforce authentication. API is accessible without auth - this is a security issue.'
        );
    }

    private function checkAuthIsEnforced(): bool
    {
        $_GET = [
            'app' => 'pos',
            'ctl' => 'api_orders',
            'act' => 'index',
        ];
        $_SESSION = [];
        $_COOKIE = [];
        $originalEnv = [
            'HTTP_X_AUTH_TOKEN' => $_SERVER['HTTP_X_AUTH_TOKEN'] ?? null,
            'HTTP_AUTHORIZATION' => $_SERVER['HTTP_AUTHORIZATION'] ?? null,
        ];
        unset($_SERVER['HTTP_X_AUTH_TOKEN']);
        unset($_SERVER['HTTP_AUTHORIZATION']);

        try {
            ob_start();
            kernel::single('desktop_router_dispatch')->dispatch($_GET);
            $output = ob_get_clean();
        } catch (Exception $e) {
            $_SERVER = array_merge($_SERVER, $originalEnv);
            return strpos($e->getMessage(), 'login') !== false 
                || strpos($e->getMessage(), 'auth') !== false
                || strpos($e->getMessage(), '登录') !== false;
        }

        $_SERVER = array_merge($_SERVER, $originalEnv);
        $response = json_decode($output, true);
        if (!$response) {
            return false;
        }

        return isset($response['rsp']) && $response['rsp'] === 'fail'
            && (isset($response['message']) && (
                stripos($response['message'], 'login') !== false 
                || stripos($response['message'], 'auth') !== false
                || stripos($response['message'], '登录') !== false
            ));
    }
}