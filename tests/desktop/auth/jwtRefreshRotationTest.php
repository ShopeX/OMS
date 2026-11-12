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

#[RunTestsInSeparateProcesses]
final class jwtRefreshRotationTest extends TestCase
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
        putenv('JWT_SECRET=test-jwt-secret-for-refresh-rotation-tests');
        include_once 'config/config.php';
        include_once 'app/base/defined.php';
        include_once 'app/base/kernel.php';

        if (self::$testUserId <= 0) {
            self::$loginName = 'jwt_rotation_test_' . getmypid();
            self::$testUserId = $this->createFixtureUser(self::$loginName);
            self::$originalTokenVersion = self::readTokenVersion(self::$testUserId);
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
            // framework may be unavailable during shutdown
        }

        try {
            self::removeFixtureUser(self::$testUserId, self::$testAccountId);
        } catch (Throwable $e) {
            // cleanup must never mask the actual test result
        }
    }

    public function testConsumeRefreshTokenBlacklistsOldRefresh(): void
    {
        $tokens = desktop_auth_jwt::issueTokens(self::$testUserId, 'rotation-test', ['login_time' => time()]);
        $refreshToken = (string)($tokens['refresh']['token'] ?? '');
        $this->assertNotSame('', $refreshToken);

        desktop_auth_jwt::consumeRefreshToken($refreshToken);

        $this->expectException(desktop_auth_exception::class);
        $this->expectExceptionMessage('revoked');
        desktop_auth_jwt::decodeRefresh($refreshToken);
    }

    public function testRefreshReuseIncrementsTokenVersion(): void
    {
        $userModel = app::get('desktop')->model('users');
        $before = $this->readTokenVersion(self::$testUserId);

        $tokens = desktop_auth_jwt::issueTokens(self::$testUserId, 'reuse-test', ['login_time' => time()]);
        $refreshToken = (string)($tokens['refresh']['token'] ?? '');
        $consumed = desktop_auth_jwt::consumeRefreshToken($refreshToken);

        // 宽限期幂等上线后，窗口内重放不再触发盗用吊销；此处删除血缘标记
        // 模拟"宽限期外的重放"（真盗用场景），必须仍然 bump token_version。
        $this->dropGraceMarker((string)($consumed['jti'] ?? ''));

        try {
            desktop_auth_jwt::decodeRefresh($refreshToken);
            $this->fail('Expected revoked refresh token');
        } catch (desktop_auth_exception $e) {
            desktop_auth_jwt::revokeSessionOnRefreshReuse($refreshToken, $e);
        }

        $after = $this->readTokenVersion(self::$testUserId);
        $this->assertGreaterThan($before, $after);
    }

    public function testRefreshRotationPreservesAbsoluteExpiry(): void
    {
        $loginTime = time();
        $tokens = desktop_auth_jwt::issueTokens(self::$testUserId, 'expiry-test', ['login_time' => $loginTime]);
        $originalRefreshExp = (int)($tokens['refresh']['expires_at'] ?? 0);
        $this->assertGreaterThan(time(), $originalRefreshExp);

        $rotated = desktop_auth_jwt::issueTokens(
            self::$testUserId,
            'expiry-test',
            ['login_time' => $loginTime],
            $originalRefreshExp
        );
        $newRefreshExp = (int)($rotated['refresh']['expires_at'] ?? 0);

        $this->assertSame($originalRefreshExp, $newRefreshExp);
    }

    public function testGraceWindowReplayDoesNotRevoke(): void
    {
        $userModel = app::get('desktop')->model('users');
        $before = $this->readTokenVersion(self::$testUserId);

        $tokens = desktop_auth_jwt::issueTokens(self::$testUserId, 'grace-test', ['login_time' => time()]);
        $refreshToken = (string)($tokens['refresh']['token'] ?? '');
        $firstClaims = desktop_auth_jwt::consumeRefreshToken($refreshToken);
        $this->assertNotEmpty($firstClaims['jti']);

        // Race loser replays the same (now blacklisted) token within the grace
        // window: same logical rotation -> idempotent pass, no version bump.
        $replayClaims = desktop_auth_jwt::consumeRefreshToken($refreshToken);
        $this->assertSame((int)$firstClaims['sub'], (int)$replayClaims['sub']);
        $this->assertSame((string)$firstClaims['jti'], (string)$replayClaims['jti']);

        $this->assertSame($before, $this->readTokenVersion(self::$testUserId));
    }

    public function testGraceReplayStillBlockedAfterFamilyRevoke(): void
    {
        $tokens = desktop_auth_jwt::issueTokens(self::$testUserId, 'grace-family-test', ['login_time' => time()]);
        $refreshToken = (string)($tokens['refresh']['token'] ?? '');
        $claims = desktop_auth_jwt::consumeRefreshToken($refreshToken);
        $fid = (string)($claims[desktop_auth_jwt::CLAIM_FID] ?? '');
        $this->assertNotSame('', $fid);

        kernel::single('desktop_auth_family_revoker')->revoke($fid, 60);

        try {
            desktop_auth_jwt::consumeRefreshToken($refreshToken);
            $this->fail('Expected family-revoked refresh token to stay revoked despite grace record');
        } catch (desktop_auth_exception $e) {
            $this->assertStringContainsString('revoked', $e->getMessage());
        }
    }

    public function testGraceWindowExpiryStillRevokes(): void
    {
        $userModel = app::get('desktop')->model('users');
        $before = $this->readTokenVersion(self::$testUserId);

        $tokens = desktop_auth_jwt::issueTokens(self::$testUserId, 'grace-exp-test', ['login_time' => time()]);
        $refreshToken = (string)($tokens['refresh']['token'] ?? '');
        $claims = desktop_auth_jwt::consumeRefreshToken($refreshToken);

        // Simulate grace window expiry by deleting the recorded marker.
        $this->dropGraceMarker((string)($claims['jti'] ?? ''));
        $grace = kernel::single('desktop_auth_refresh_grace');
        $this->assertNull($grace->claimsFor((string)($claims['jti'] ?? '')));

        try {
            desktop_auth_jwt::decodeRefresh($refreshToken);
            $this->fail('Expected revoked refresh token');
        } catch (desktop_auth_exception $e) {
            desktop_auth_jwt::revokeSessionOnRefreshReuse($refreshToken, $e);
        }

        $this->assertGreaterThan($before, $this->readTokenVersion(self::$testUserId));
    }

    private function createFixtureUser(string $loginName): int
    {
        $accountType = pam_account::get_account_type('desktop');
        $userModel = app::get('desktop')->model('users');

        $data = [
            'name'                 => 'JWT Rotation Test',
            'super'                => 0,
            'status'               => '1',
            'token_version'        => 1,
            'create_time'          => time(),
            'lastlogin'            => 0,
            'logincount'           => 0,
            'pam_account'          => [
                'login_name'     => $loginName,
                'login_password'  => pam_encrypt::get_encrypted_password('Rotation!Pass123', $accountType),
                'account_type'    => $accountType,
                'createtime'      => time(),
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
        $this->assertGreaterThan(0, self::$testAccountId, 'pam account was not created');

        return (int)$data['user_id'];
    }

    private static function removeFixtureUser(int $userId, int $accountId): void
    {
        $userModel = app::get('desktop')->model('users');
        $userModel->delete(['user_id' => $userId]);

        if ($accountId > 0) {
            app::get('pam')->model('account')->delete(['account_id' => $accountId]);
        }
    }

    private function readTokenVersion(int $userId): int
    {
        $row = app::get('desktop')->model('users')->getList('token_version', ['user_id' => $userId]);
        return (int)($row[0]['token_version'] ?? 0);
    }

    /**
     * base_kvstore::delete() 是软删（ttl=1 续写，等 cron 清理），1 秒内仍可读到；
     * 测试要立即失效必须 real_delete。
     */
    private function dropGraceMarker(string $jti): void
    {
        if ($jti === '') {
            return;
        }
        base_kvstore::instance('pam_token_grace')->real_delete('grace_' . md5($jti));
    }
}
