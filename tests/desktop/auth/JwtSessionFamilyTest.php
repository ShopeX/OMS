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
 * SCRUM-7917 Plan B — session-family (fid) semantics.
 *
 * One browser login mints one family id; refresh rotation inherits it;
 * revoking the family kills only that browser's tokens. Other concurrent
 * sessions of the same account (different fid) stay valid.
 */
#[RunTestsInSeparateProcesses]
final class JwtSessionFamilyTest extends TestCase
{
    private static int $testUserId = 0;
    private static int $testAccountId = 0;
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
        putenv('JWT_SECRET=test-jwt-secret-for-family-tests');
        include_once 'config/config.php';
        include_once 'app/base/defined.php';
        include_once 'app/base/kernel.php';

        if (self::$testUserId <= 0) {
            self::$loginName = 'jwt_family_test_' . getmypid();
            $this->createFixtureUser(self::$loginName);
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testUserId <= 0) {
            return;
        }

        try {
            self::removeFixtureUser(self::$testUserId, self::$testAccountId);
        } catch (Throwable $e) {
            // cleanup must never mask the actual test result
        }
    }

    public function testFreshLoginTokensShareOneFamilyId(): void
    {
        $tokens = desktop_auth_jwt::issueTokens(self::$testUserId, 'family-test', ['login_time' => time()]);

        $accessPayload = desktop_auth_jwt::peekSignedPayload((string)$tokens['access']['token']);
        $refreshPayload = desktop_auth_jwt::peekSignedPayload((string)$tokens['refresh']['token']);

        $fid = (string)($accessPayload['fid'] ?? '');
        $this->assertNotSame('', $fid, 'access token must carry a session-family id');
        $this->assertSame($fid, (string)($refreshPayload['fid'] ?? ''), 'access and refresh must share the family id');
    }

    public function testFreshLoginsMintDistinctFamilyIds(): void
    {
        $a = desktop_auth_jwt::issueTokens(self::$testUserId, 'family-test', ['login_time' => time()]);
        $b = desktop_auth_jwt::issueTokens(self::$testUserId, 'family-test', ['login_time' => time()]);

        $fidA = (string)(desktop_auth_jwt::peekSignedPayload((string)$a['access']['token'])['fid'] ?? '');
        $fidB = (string)(desktop_auth_jwt::peekSignedPayload((string)$b['access']['token'])['fid'] ?? '');

        $this->assertNotSame('', $fidA);
        $this->assertNotSame('', $fidB);
        $this->assertNotSame($fidA, $fidB, 'each login must be its own session family');
    }

    public function testRotationInheritsFamilyId(): void
    {
        $tokens = desktop_auth_jwt::issueTokens(self::$testUserId, 'family-test', ['login_time' => time()]);
        $originalFid = (string)(desktop_auth_jwt::peekSignedPayload((string)$tokens['refresh']['token'])['fid'] ?? '');

        // Simulate a browser refresh rotation: consume old refresh, re-issue with
        // the family id carried through the profile (same path as flow::refresh).
        $claims = desktop_auth_jwt::consumeRefreshToken((string)$tokens['refresh']['token']);
        $profile = ['fid' => (string)($claims['fid'] ?? '')];
        $rotated = desktop_auth_jwt::issueTokens(self::$testUserId, 'family-test', $profile);

        $rotatedAccessFid = (string)(desktop_auth_jwt::peekSignedPayload((string)$rotated['access']['token'])['fid'] ?? '');
        $rotatedRefreshFid = (string)(desktop_auth_jwt::peekSignedPayload((string)$rotated['refresh']['token'])['fid'] ?? '');

        $this->assertSame($originalFid, $rotatedAccessFid, 'rotated access must stay in the same family');
        $this->assertSame($originalFid, $rotatedRefreshFid, 'rotated refresh must stay in the same family');
    }

    public function testFamilyRevocationKillsBothTokenTypes(): void
    {
        $tokens = desktop_auth_jwt::issueTokens(self::$testUserId, 'family-test', ['login_time' => time()]);
        $accessToken = (string)$tokens['access']['token'];
        $refreshToken = (string)$tokens['refresh']['token'];

        // both usable before revocation
        desktop_auth_jwt::decodeWithValidation($accessToken);
        desktop_auth_jwt::decodeRefresh($refreshToken);

        $fid = (string)(desktop_auth_jwt::peekSignedPayload($accessToken)['fid'] ?? '');
        desktop_auth_jwt::revokeFamily($fid);

        try {
            desktop_auth_jwt::decodeWithValidation($accessToken);
            $this->fail('revoked family must invalidate the access token');
        } catch (desktop_auth_exception $e) {
            $this->assertSame('Token has been revoked', $e->getMessage());
        }

        try {
            desktop_auth_jwt::decodeRefresh($refreshToken);
            $this->fail('revoked family must invalidate the refresh token');
        } catch (desktop_auth_exception $e) {
            $this->assertSame('Token has been revoked', $e->getMessage());
        }
    }

    public function testRevokingOneFamilySparesAnother(): void
    {
        $sessionA = desktop_auth_jwt::issueTokens(self::$testUserId, 'family-test', ['login_time' => time()]);
        $sessionB = desktop_auth_jwt::issueTokens(self::$testUserId, 'family-test', ['login_time' => time()]);

        $fidA = (string)(desktop_auth_jwt::peekSignedPayload((string)$sessionA['access']['token'])['fid'] ?? '');
        desktop_auth_jwt::revokeFamily($fidA);

        // A dies
        try {
            desktop_auth_jwt::decodeWithValidation((string)$sessionA['access']['token']);
            $this->fail('session A must be revoked');
        } catch (desktop_auth_exception $e) {
            $this->assertSame('Token has been revoked', $e->getMessage());
        }

        // B survives (the whole point of Plan B)
        $payloadB = desktop_auth_jwt::decodeWithValidation((string)$sessionB['access']['token']);
        $this->assertSame(self::$testUserId, (int)($payloadB['user_id'] ?? 0), 'session B must stay valid after A logs out');
    }

    public function testRevocationMarkerTtlIsPositive(): void
    {
        $this->assertGreaterThan(0, desktop_auth_family_revoker::markerTtl(null));
        $this->assertGreaterThan(0, desktop_auth_family_revoker::markerTtl(time() + 3600));
        // already-expired family: falls back to refresh TTL + slack
        $this->assertGreaterThan(0, desktop_auth_family_revoker::markerTtl(time() - 10));
    }

    public function testLegacyTokensWithoutFidSkipFamilyCheck(): void
    {
        // Pre-Plan-B tokens carry no fid: family check must be skipped, never revoked.
        $this->assertFalse(desktop_auth_jwt::isFamilyRevoked(null), 'no fid in token = skip family check');
        $this->assertFalse(desktop_auth_jwt::isFamilyRevoked(''), 'empty fid = skip family check');
        // A random unrevoked fid is also clean.
        $this->assertFalse(desktop_auth_jwt::isFamilyRevoked(desktop_auth_jwt::newFamilyId()));
    }

    private function createFixtureUser(string $loginName): void
    {
        $accountType = pam_account::get_account_type('desktop');
        $userModel = app::get('desktop')->model('users');

        $data = [
            'name'                 => 'JWT Family Test',
            'super'                => 0,
            'status'               => '1',
            'token_version'        => 1,
            'create_time'          => time(),
            'lastlogin'            => 0,
            'logincount'           => 0,
            'pam_account'          => [
                'login_name'     => $loginName,
                'login_password'  => pam_encrypt::get_encrypted_password('Family!Pass123', $accountType),
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

        // CRITICAL: resolve the fixture id by its unique login name via the pam
        // link, NOT by blind first-row reads — an unfiltered getList() once
        // returned the real admin row (user_id=1) and tearDown deleted it.
        $userRow = $userModel->getList('*', ['login_name' => self::$loginName]);
        self::$testUserId = (int)($userRow[0]['user_id'] ?? 0);
        $this->assertGreaterThan(0, self::$testUserId, 'desktop user was not created');
    }

    private static function removeFixtureUser(int $userId, int $accountId): void
    {
        $db = kernel::database();
        // Guard rails: never delete a row that is not our fixture.
        $db->exec("DELETE FROM sdb_desktop_users WHERE user_id=" . (int)$userId
            . " AND name='JWT Family Test'");
        $db->exec("DELETE FROM sdb_pam_account WHERE account_id=" . (int)$accountId
            . " AND login_name LIKE 'jwt_family_test_%'");
    }
}
