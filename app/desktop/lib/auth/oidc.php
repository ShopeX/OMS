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

class desktop_auth_oidc
{
    private const CHALLENGE_NS = 'oidc_challenge';
    private const CHALLENGE_TTL = 300;
    private const ID_TOKEN_NS = 'oidc_id_token';
    private const ID_TOKEN_TTL = 86400;

    /**
     * Vue SPA login page URL (JWT browser entry).
     */
    public static function vueLoginUrl(array $query = []): string
    {
        $url = kernel::get_host_url() . '/login';
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        return $url;
    }

    /**
     * OIDC login button metadata for Vue login page.
     *
     * @return array<string, mixed>
     */
    public static function loginInfo(): array
    {
        $enabled = app::get('ome')->getConf('pam.passport.oidc.enable');
        if ($enabled !== 'true') {
            return ['enabled' => false];
        }

        $info = app::get('ome')->getConf('pam.passport.oidc.info');
        $labelBase = isset($info['oidctype']) && $info['oidctype'] ? strtoupper($info['oidctype']) : '第三方';
        $label = $labelBase . '信任登录';

        $forceRedirect = (($info['to_out'] ?? '') === 'true');

        try {
            $router = kernel::single('erpapi_router_request')->set('account', 'account');
            $result = $router->user_loginUrl([]);
            if (is_array($result) && ($result['rsp'] ?? '') === 'succ' && !empty($result['data'])) {
                return [
                    'enabled' => true,
                    'label' => $label,
                    'url' => $result['data'],
                    'force_redirect' => $forceRedirect,
                ];
            }
        } catch (Throwable $e) {
        }

        return ['enabled' => false];
    }

    /**
     * Create OIDC state/nonce pair and persist server-side (no PHP session).
     *
     * @return array{0: string, 1: string}
     */
    public static function beginChallenge(): array
    {
        $state = self::generateToken();
        $nonce = self::generateToken();
        $payload = json_encode([
            'nonce' => $nonce,
            'expires' => time() + self::CHALLENGE_TTL,
        ]);
        base_kvstore::instance(self::CHALLENGE_NS)->store($state, $payload, self::CHALLENGE_TTL);

        return [$state, $nonce];
    }

    /**
     * Validate OIDC state before exchanging authorization code.
     */
    public static function validateState(string $state): bool
    {
        if ($state === '') {
            return false;
        }

        $stored = null;
        if (!base_kvstore::instance(self::CHALLENGE_NS)->fetch($state, $stored) || !$stored) {
            return false;
        }

        $data = json_decode($stored, true);
        if (!is_array($data) || (int)($data['expires'] ?? 0) < time()) {
            self::consumeChallenge($state);

            return false;
        }

        return true;
    }

    /**
     * Validate id_token nonce and consume the challenge (one-time).
     */
    public static function validateNonce(string $state, string $idToken): bool
    {
        if ($state === '' || $idToken === '') {
            return false;
        }

        $stored = null;
        if (!base_kvstore::instance(self::CHALLENGE_NS)->fetch($state, $stored) || !$stored) {
            return false;
        }

        $data = json_decode($stored, true);
        if (!is_array($data) || (int)($data['expires'] ?? 0) < time()) {
            self::consumeChallenge($state);

            return false;
        }

        $expected = (string)($data['nonce'] ?? '');
        $payload = self::decodeJwtPayload($idToken);
        $nonce = (string)($payload['nonce'] ?? '');
        $valid = $expected !== '' && $nonce !== '' && hash_equals($expected, $nonce);
        self::consumeChallenge($state);

        return $valid;
    }

    /**
     * Store id_token for federated logout (CIAM/Okta end_session).
     */
    public static function storeIdToken(int $userId, string $idToken): void
    {
        if ($userId <= 0 || $idToken === '') {
            return;
        }

        base_kvstore::instance(self::ID_TOKEN_NS)->store((string)$userId, $idToken, self::ID_TOKEN_TTL);
    }

    /**
     * Recall stored id_token for federated logout.
     */
    public static function recallIdToken(int $userId): string
    {
        if ($userId <= 0) {
            return '';
        }

        $token = null;
        base_kvstore::instance(self::ID_TOKEN_NS)->fetch((string)$userId, $token);

        return is_string($token) ? $token : '';
    }

    /**
     * Discard stored id_token after federated logout URL is built.
     */
    public static function clearIdToken(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        base_kvstore::instance(self::ID_TOKEN_NS)->store((string)$userId, '', 1);
    }

    private static function consumeChallenge(string $state): void
    {
        base_kvstore::instance(self::CHALLENGE_NS)->store($state, '', 1);
    }

    /**
     * Discard OIDC challenge immediately (success, failure, or post-login cleanup).
     */
    public static function discardChallenge(string $state): void
    {
        if ($state === '') {
            return;
        }

        self::consumeChallenge($state);
    }

    private static function generateToken(): string
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (Throwable $e) {
            return bin2hex(openssl_random_pseudo_bytes(16));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function decodeJwtPayload(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) < 2) {
            return [];
        }

        $payload = json_decode(self::base64UrlDecode($parts[1]), true);

        return is_array($payload) ? $payload : [];
    }

    private static function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($data, '-_', '+/')) ?: '';
    }
}
