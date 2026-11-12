<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
final class P0SecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        chdir(__DIR__ . '/../../..');
        $_SERVER = [];
        $_GET = [];
    }

    protected function tearDown(): void
    {
        $_SERVER = [];
        $_GET = [];
        parent::tearDown();
    }

    public function testDesktopJwtClientDoesNotContainLegacyMcpTrust(): void
    {
        $source = file_get_contents('app/desktop/lib/auth/jwt/client.php');

        $this->assertStringNotContainsString('TYPE_MCP', $source);
        $this->assertStringNotContainsString('assertTrustedMcp', $source);
        $this->assertStringNotContainsString('MCP_CLIENT_SECRET', $source);
        $this->assertStringNotContainsString('MCP_ALLOWED_SOURCES', $source);
    }

    public function testLegacyMcpHeadersRemainBrowserRequests(): void
    {
        $this->loadJwtClient();
        $_SERVER = [
            'HTTP_X_OMS_CLIENT_TYPE' => 'mcp',
            'HTTP_X_OMS_MCP_SECRET' => 'obsolete-secret',
            'HTTP_X_REQUESTED_BY' => 'shopex-oms-mcp',
        ];

        $client = desktop_auth_jwt_client::fromRequest();

        $this->assertTrue($client->isBrowser());
        $this->assertSame(desktop_auth_jwt_client::MODE_BROWSER_COOKIE, $client->tokenSourceMode());
    }

    public function testCsrfGuardHasNoLegacyMcpExemption(): void
    {
        $source = file_get_contents('app/desktop/lib/auth/csrf/guard.php');

        $this->assertStringNotContainsString('isMcpChannel', $source);
        $this->assertStringNotContainsString("transportMode() !== 'mcp'", $source);
    }

    public function testApiBaseEnablesCsrfProtectionByDefault(): void
    {
        if (!class_exists('desktop_controller', false)) {
            eval('class desktop_controller {}');
        }
        require_once 'app/desktop/controller/api/base.php';

        $property = new ReflectionProperty(desktop_ctl_api_base::class, 'checkCSRF');
        $controller = (new ReflectionClass(desktop_ctl_api_base::class))->newInstanceWithoutConstructor();

        $this->assertTrue($property->getValue($controller));
    }

    public function testPasswordSecurityStampOnlyAddsChangedTime(): void
    {
        require_once 'app/desktop/lib/auth/password/security.php';
        $saveData = ['pam_account' => ['account_id' => 42, 'login_password' => 'test-hash']];

        $stamped = desktop_auth_password_security::stampPasswordChangedAt($saveData, 1234567890);

        $this->assertSame(1234567890, $stamped['pam_account']['password_changed_at']);
        $this->assertArrayNotHasKey('password_changed_at', $saveData['pam_account']);
    }

    public function testPasswordSecurityRevokeInvokesCallbackExactlyOnce(): void
    {
        require_once 'app/desktop/lib/auth/password/security.php';
        $revoked = [];

        desktop_auth_password_security::revokeAfterSuccessfulChange(
            42,
            static function (int $userId) use (&$revoked): void {
                $revoked[] = $userId;
            }
        );

        $this->assertSame([42], $revoked);
    }

    public function testPasswordSecurityCompletionPersistsStampBeforeSingleRevoke(): void
    {
        require_once 'app/desktop/lib/auth/password/security.php';
        $events = [];

        $success = desktop_auth_password_security::complete(
            42,
            ['pam_account' => ['account_id' => 42, 'login_password' => 'test-hash']],
            static function (array $saveData) use (&$events): bool {
                $events[] = ['persist', $saveData['pam_account']['password_changed_at']];
                return true;
            },
            static function (int $userId) use (&$events): void {
                $events[] = ['revoke', $userId];
            },
            1234567890
        );

        $this->assertTrue($success);
        $this->assertSame([['persist', 1234567890], ['revoke', 42]], $events);
    }

    public function testPasswordSecurityCompletionDoesNotRevokeFailedPersistence(): void
    {
        require_once 'app/desktop/lib/auth/password/security.php';
        $revoked = [];

        $success = desktop_auth_password_security::complete(
            42,
            ['pam_account' => ['account_id' => 42, 'login_password' => 'test-hash']],
            static fn(array $saveData): bool => false,
            static function (int $userId) use (&$revoked): void {
                $revoked[] = $userId;
            },
            1234567890
        );

        $this->assertFalse($success);
        $this->assertSame([], $revoked);
    }

    public function testLegacyPasswordChangeRevokesOnlyInIdaasSuccessBranchBeforeCommit(): void
    {
        $source = file_get_contents('app/desktop/controller/default.php');
        $start = strpos($source, 'public function changePassword()');
        $end = strpos($source, 'public function getJDToken()', $start);
        $method = substr($source, $start, $end - $start);

        $stamp = strpos($method, 'stampPasswordChangedAt(');
        $save = strpos($method, '$saved = $users->save($_POST);');
        $sync = strpos($method, "sync_account(array (");
        $successBranch = strpos($method, 'if ($rs === false) {} else {');
        $revoke = strpos($method, 'revokeAfterSuccessfulChange(');
        $status = strpos($method, '$status = true;', $successBranch);
        $commit = strpos($method, '$db->commit()');

        foreach (compact('stamp', 'save', 'sync', 'successBranch', 'revoke', 'status', 'commit') as $name => $position) {
            $this->assertNotFalse($position, sprintf('Missing %s in legacy password-change flow', $name));
        }
        $this->assertSame(1, substr_count($method, 'revokeAfterSuccessfulChange('));
        $this->assertStringNotContainsString('desktop_auth_password_security::complete(', $method);
        $this->assertTrue($stamp < $save && $save < $sync && $sync < $successBranch);
        $this->assertTrue($successBranch < $revoke && $revoke < $status && $status < $commit);
    }

    public function testVuePasswordChangeStopsWhenPersistenceReturnsFalse(): void
    {
        // 该流程已重构为 desktop_capability_user_password::change()（控制器只做转发 + 异常→响应映射），
        // 断言点随之迁移到 capability；语义不变：持久化返回 false ⇒ 立即中止，不得继续后续副作用。
        $method = $this->controllerMethodSource(
            'app/desktop/lib/capability/user/password.php',
            'public function change(array $request, int $currentUserId): array',
            "\n    }\n}"
        );

        $complete = strpos($method, '$completed = desktop_auth_password_security::complete(');
        $failure = strpos($method, 'if (!$completed) {', $complete ?: 0);
        $abort = strpos($method, "throw new RuntimeException('密码保存失败，请稍后重试');", $failure ?: 0);
        $afterSuccess = strpos($method, '$this->userLib->checkUpdatePwd(', $abort ?: 0);

        foreach (compact('complete', 'failure', 'abort', 'afterSuccess') as $name => $position) {
            $this->assertNotFalse($position, sprintf('Missing %s in Vue password-change persistence failure flow', $name));
        }
        $this->assertTrue($complete < $failure && $failure < $abort && $abort < $afterSuccess);

        // 控制器侧语义仍须把该失败映射为 500 失败响应（不得被吞成成功响应）
        $controller = $this->controllerMethodSource(
            'app/desktop/controller/api/users.php',
            'public function changePassword(): void',
            "/**\n     * 解锁用户"
        );
        $catchRuntime = strpos($controller, 'catch (RuntimeException $exception) {');
        $fail500 = strpos($controller, '$this->fail($exception->getMessage(), 500);', $catchRuntime ?: 0);

        $this->assertNotFalse($catchRuntime, 'Missing RuntimeException catch in Vue password-change controller');
        $this->assertNotFalse($fail500, 'Missing 500 failure response in Vue password-change controller');
        $this->assertTrue($catchRuntime < $fail500);
    }

    public function testLegacyPasswordChangeSaveFailureSkipsAllSuccessSideEffectsAndRollsBack(): void
    {
        $method = $this->controllerMethodSource(
            'app/desktop/controller/default.php',
            'public function changePassword()',
            'public function getJDToken()'
        );

        $save = strpos($method, '$saved = $users->save($_POST);');
        $failure = strpos($method, 'if ($saved === false) {', $save ?: 0);
        $message = strpos($method, '$msg = \'密码保存失败，请稍后重试\';', $failure ?: 0);
        $successElse = strpos($method, '} else {', $message ?: 0);
        $rollback = strpos($method, '$db->rollBack()');

        foreach (compact('save', 'failure', 'message', 'successElse', 'rollback') as $name => $position) {
            $this->assertNotFalse($position, sprintf('Missing %s in legacy save-failure flow', $name));
        }
        $failureBranch = substr($method, $failure, $successElse - $failure);
        $this->assertStringNotContainsString('sync_account', $failureBranch);
        $this->assertStringNotContainsString('revokeAfterSuccessfulChange', $failureBranch);
        $this->assertStringNotContainsString('$db->commit()', $failureBranch);
        $this->assertTrue($save < $failure && $failure < $message && $message < $successElse && $successElse < $rollback);
    }

    public function testApiFormWithoutCsrfHeaderIsRejected(): void
    {
        $this->loadCsrfGuardWithFailureTransport();
        $_GET['ctl'] = 'api_users';
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('csrf_failed');
        $this->invokeVerifyCsrfToken(null);
    }

    public function testApiJsonWithoutCsrfHeaderIsRejected(): void
    {
        $this->loadCsrfGuardWithFailureTransport();
        $_GET['ctl'] = 'api_users';
        $_SERVER['CONTENT_TYPE'] = 'application/json';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('csrf_failed');
        $this->invokeVerifyCsrfToken(null);
    }

    public function testApiForgedShopexUiHeaderDoesNotBypassCsrf(): void
    {
        $this->loadCsrfGuardWithFailureTransport();
        $_GET['ctl'] = 'api_users';
        $_SERVER['HTTP_X_REQUESTED_BY'] = 'shopex-ui';
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('csrf_failed');
        $this->invokeVerifyCsrfToken(null);
    }

    public function testLegacyFormWithoutCsrfHeaderKeepsGrayCompatibility(): void
    {
        $this->loadCsrfGuardWithFailureTransport();
        $_GET['ctl'] = 'default';
        $_SERVER['CONTENT_TYPE'] = 'multipart/form-data; boundary=test';

        $this->invokeVerifyCsrfToken(null);
        $this->addToAssertionCount(1);
    }

    public function testApiValidCsrfHeaderUsesExistingVerifier(): void
    {
        if (!class_exists('kernel', false)) {
            eval('class kernel { public static $csrf; public static function single($name, $app = null) { return self::$csrf; } }');
        }
        if (!class_exists('P0CsrfVerifier', false)) {
            eval('class P0CsrfVerifier { public $tokens = []; public function verify($token) { $this->tokens[] = $token; return true; } }');
        }
        $this->loadCsrfGuardWithFailureTransport();
        $_GET['ctl'] = 'api_users';
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid-double-submit-token';
        kernel::$csrf = new P0CsrfVerifier();

        $this->invokeVerifyCsrfToken(null);

        $this->assertSame(['valid-double-submit-token'], kernel::$csrf->tokens);
    }

    private function loadJwtClient(): void
    {
        if (!class_exists('desktop_auth_exception', false)) {
            eval('class desktop_auth_exception extends Exception {}');
        }
        require_once 'app/desktop/lib/auth/jwt/client.php';
    }

    private function loadCsrfGuard(): void
    {
        $this->loadJwtClient();
        require_once 'app/desktop/lib/auth/jwt/result.php';
        require_once 'app/desktop/lib/auth/csrf/guard.php';
    }

    private function loadCsrfGuardWithFailureTransport(): void
    {
        if (!class_exists('desktop_auth_jwt_transport', false)) {
            eval('class desktop_auth_jwt_transport { public static function respondCsrfFailed($reason) { throw new RuntimeException($reason); } }');
        }
        $this->loadCsrfGuard();
    }

    private function invokeVerifyCsrfToken($app): void
    {
        $method = new ReflectionMethod(desktop_auth_csrf_guard::class, 'verifyCsrfToken');
        $method->invoke(null, $app);
    }

    private function controllerMethodSource(string $path, string $startMarker, string $endMarker): string
    {
        $source = file_get_contents($path);
        $start = strpos($source, $startMarker);
        $end = strpos($source, $endMarker, $start === false ? 0 : $start);
        $this->assertNotFalse($start, 'Controller method start marker missing');
        $this->assertNotFalse($end, 'Controller method end marker missing');
        return substr($source, $start, $end - $start);
    }

}
