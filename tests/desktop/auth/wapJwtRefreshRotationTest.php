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

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * WAP refresh must match desktop rotation: consume jti, reuse → token_version bump,
 * absolute refresh expiry preserved via issueTokensForUser.
 */
#[RunTestsInSeparateProcesses]
final class wapJwtRefreshRotationTest extends TestCase
{
    private static int $testUserId = 0;
    private static int $testAccountId = 0;
    private static int $originalTokenVersion = 0;
    private static string $loginName = '';

    protected function setUp(): void
    {
        parent::setUp();

        if (!defined('ROOT_DIR')) {
            define('ROOT_DIR', dirname(__DIR__, 3));
        }
        if (!defined('APP_DIR')) {
            define('APP_DIR', ROOT_DIR . '/app');
        }

        chdir(ROOT_DIR);
        putenv('JWT_SECRET=test-jwt-secret-for-wap-refresh-rotation');
        include_once 'config/config.php';
        include_once 'app/base/defined.php';
        include_once 'app/base/kernel.php';

        if (self::$testUserId <= 0) {
            self::$loginName = 'wap_jwt_rotation_' . getmypid();
            self::$testUserId = $this->createFixtureUser(self::$loginName);
            self::$originalTokenVersion = $this->readTokenVersion(self::$testUserId);
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testUserId <= 0) {
            return;
        }

        try {
            app::get('desktop')->model('users')->update(
                ['token_version' => self::$originalTokenVersion],
                ['user_id' => self::$testUserId]
            );
        } catch (Throwable $e) {
        }

        try {
            self::removeFixtureUser(self::$testUserId, self::$testAccountId);
        } catch (Throwable $e) {
        }
    }

    public function testWapRefreshPathConsumesAndPreservesAbsoluteExpiry(): void
    {
        $loginTime = time();
        $tokens = desktop_auth_jwt_flow::issueTokensForUser(
            self::$testUserId,
            self::$loginName,
            ['is_super' => false, 'login_time' => $loginTime],
            true
        );
        $refreshToken = (string)($tokens['refresh']['token'] ?? '');
        $originalRefreshExp = (int)($tokens['refresh']['expires_at'] ?? 0);
        $this->assertNotSame('', $refreshToken);
        $this->assertGreaterThan(time(), $originalRefreshExp);

        // Same contract as wap_controller::refreshWapJwtSession
        $payload = desktop_auth_jwt::consumeRefreshToken($refreshToken);
        $rotated = desktop_auth_jwt_flow::issueTokensForUser(
            self::$testUserId,
            self::$loginName,
            [
                'is_super' => false,
                'login_time' => desktop_auth_jwt::preservedLoginTimeFromClaims($payload),
            ],
            false,
            $originalRefreshExp
        );

        $this->assertSame($originalRefreshExp, (int)($rotated['refresh']['expires_at'] ?? 0));

        $this->expectException(desktop_auth_exception::class);
        $this->expectExceptionMessage('revoked');
        desktop_auth_jwt::decodeRefresh($refreshToken);
    }

    public function testWapRefreshReuseIncrementsTokenVersion(): void
    {
        $before = $this->readTokenVersion(self::$testUserId);
        $tokens = desktop_auth_jwt_flow::issueTokensForUser(
            self::$testUserId,
            self::$loginName,
            ['login_time' => time()],
            false
        );
        $refreshToken = (string)($tokens['refresh']['token'] ?? '');
        $consumed = desktop_auth_jwt::consumeRefreshToken($refreshToken);

        // 宽限期幂等上线后，窗口内重放不再触发盗用吊销；此处删除血缘标记
        // 模拟"宽限期外的重放"（真盗用场景），必须仍然 bump token_version。
        if (is_array($consumed) && ($consumed['jti'] ?? '') !== '') {
            base_kvstore::instance('pam_token_grace')
                ->real_delete('grace_' . md5((string)$consumed['jti']));
        }

        try {
            desktop_auth_jwt::decodeRefresh($refreshToken);
            $this->fail('Expected revoked refresh token');
        } catch (desktop_auth_exception $e) {
            desktop_auth_jwt::revokeSessionOnRefreshReuse($refreshToken, $e);
        }

        $after = $this->readTokenVersion(self::$testUserId);
        $this->assertGreaterThan($before, $after);
    }

    private function createFixtureUser(string $loginName): int
    {
        $accountType = pam_account::get_account_type('desktop');
        $userModel = app::get('desktop')->model('users');

        $data = [
            'name'          => 'WAP JWT Rotation Test',
            'super'         => 0,
            'status'        => '1',
            'token_version' => 1,
            'create_time'   => time(),
            'lastlogin'     => 0,
            'logincount'    => 0,
            'pam_account'   => [
                'login_name'          => $loginName,
                'login_password'      => pam_encrypt::get_encrypted_password('Rotation!Pass123', $accountType),
                'account_type'        => $accountType,
                'createtime'          => time(),
                'password_changed_at' => time(),
            ],
        ];

        $saved = $userModel->save($data);
        $this->assertTrue((bool)$saved, 'failed to insert fixture user');

        $row = app::get('pam')->model('account')->getList(
            'account_id',
            ['login_name' => $loginName, 'account_type' => $accountType]
        );
        self::$testAccountId = (int)($row[0]['account_id'] ?? 0);
        $this->assertGreaterThan(0, self::$testAccountId);

        return (int)$data['user_id'];
    }

    private static function removeFixtureUser(int $userId, int $accountId): void
    {
        app::get('desktop')->model('users')->delete(['user_id' => $userId]);
        if ($accountId > 0) {
            app::get('pam')->model('account')->delete(['account_id' => $accountId]);
        }
    }

    private function readTokenVersion(int $userId): int
    {
        $row = app::get('desktop')->model('users')->getList('token_version', ['user_id' => $userId]);
        return (int)($row[0]['token_version'] ?? 0);
    }
}
