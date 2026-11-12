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

use PHPUnit\Framework\TestCase;

class oidcTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        chdir(__DIR__ . '/../../..');
        include_once 'config/config.php';
        include_once 'app/base/defined.php';
        include_once 'app/base/kernel.php';
    }

    public function testBeginChallengeValidateStateAndNonce(): void
    {
        [$state, $nonce] = desktop_auth_oidc::beginChallenge();
        $this->assertNotSame('', $state);
        $this->assertNotSame('', $nonce);
        $this->assertTrue(desktop_auth_oidc::validateState($state));

        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'none'])), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode(['nonce' => $nonce])), '+/', '-_'), '=');
        $idToken = $header . '.' . $payload . '.sig';

        $this->assertTrue(desktop_auth_oidc::validateNonce($state, $idToken));
        $this->assertFalse(desktop_auth_oidc::validateState($state));
    }

    public function testStoreAndRecallIdToken(): void
    {
        desktop_auth_oidc::storeIdToken(42, 'test-id-token');
        $this->assertSame('test-id-token', desktop_auth_oidc::recallIdToken(42));
        $this->assertSame('', desktop_auth_oidc::recallIdToken(0));
    }
}
