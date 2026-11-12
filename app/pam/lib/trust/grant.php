<?php
/**
 * 一次性可信身份凭证（Trust Grant）
 *
 * 用途：跨请求安全传递已认证的 userId
 * 场景：OIDC callback → JWT 签发、未来 SAML/企业微信等
 *
 * 安全特性：
 *   - HMAC-SHA256 签名防篡改
 *   - KVStore 服务端存储防伪造
 *   - 一次性消费（redeem 后立即销毁）
 *   - 短 TTL（120s）
 *   - httpOnly + Secure + SameSite=Lax
 *   - 绑定 source 防跨流程滥用
 *
 * @copyright  Copyright (c) 2005-2026 ShopEx Technologies Inc. (http://www.shopex.cn)
 */
class pam_trust_grant
{
    const TTL_SECONDS = 120;
    const COOKIE_NAME = 'trust_grant';
    const KVSTORE_NS  = 'trust_grant';

    /**
     * 签发一次性凭证
     *
     * @param int    $userId  已验证的用户 ID
     * @param string $source  来源标识 ('oidc', 'idaas', 'wechat' ...)
     * @return string token（已自动设 cookie）
     */
    public static function issue(int $userId, string $source): string
    {
        $nonce   = bin2hex(random_bytes(16));
        $expires = time() + self::TTL_SECONDS;

        $payload = [
            'user_id' => $userId,
            'source'  => $source,
            'nonce'   => $nonce,
            'expires' => $expires,
        ];

        $token = self::sign($payload);

        // 服务端存储（防伪造 cookie）
        base_kvstore::instance(self::KVSTORE_NS)
            ->store($nonce, json_encode($payload), self::TTL_SECONDS);

        // httpOnly cookie，JS 不可读
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie(self::COOKIE_NAME, $token, [
            'expires'  => $expires,
            'path'     => '/',
            'secure'   => $secure,
            'httponly'  => true,
            'samesite' => 'Lax',
        ]);

        return $token;
    }

    /**
     * 消费凭证，返回 userId
     *
     * @param string $expectedSource 期望来源（必须匹配）
     * @return int userId (0 = 无效)
     */
    public static function redeem(string $expectedSource): int
    {
        $token = $_COOKIE[self::COOKIE_NAME] ?? '';
        if (!$token) {
            return 0;
        }

        // 验签
        $payload = self::verify($token);
        if (!$payload) {
            self::revoke();
            return 0;
        }

        // 过期检查
        if (($payload['expires'] ?? 0) < time()) {
            self::revoke();
            return 0;
        }

        // 来源检查（防跨流程）
        if (($payload['source'] ?? '') !== $expectedSource) {
            self::revoke();
            return 0;
        }

        // 服务端二次验证
        $nonce  = $payload['nonce'] ?? '';
        $stored = null;
        base_kvstore::instance(self::KVSTORE_NS)->fetch($nonce, $stored);
        if (!$stored) {
            self::revoke();
            return 0;
        }

        $serverPayload = json_decode($stored, true);
        if (!$serverPayload
            || (int)$serverPayload['user_id'] !== (int)$payload['user_id']
            || $serverPayload['source'] !== $expectedSource
        ) {
            self::revoke();
            return 0;
        }

        // 一次性：消费后销毁
        self::revoke($nonce);

        return (int)$payload['user_id'];
    }

    /**
     * 撤销凭证
     *
     * @param string $nonce 可选，指定要清除的 KVStore key
     */
    public static function revoke(string $nonce = ''): void
    {
        if ($nonce) {
            base_kvstore::instance(self::KVSTORE_NS)->store($nonce, '', 1);
        }
        setcookie(self::COOKIE_NAME, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'httponly'  => true,
            'samesite' => 'Lax',
        ]);
    }

    // ── 内部方法 ──

    private static function sign(array $payload): string
    {
        $data = base64_encode(json_encode($payload));
        $sig  = hash_hmac('sha256', $data, self::getSecret());
        return $data . '.' . $sig;
    }

    private static function verify(string $token): ?array
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return null;
        }
        [$data, $sig] = $parts;
        $expected = hash_hmac('sha256', $data, self::getSecret());
        if (!hash_equals($expected, $sig)) {
            return null;
        }
        return json_decode(base64_decode($data), true);
    }

    private static function getSecret(): string
    {
        try {
            return desktop_auth_jwt::resolveSecret();
        } catch (desktop_auth_exception $e) {
            throw new RuntimeException('[TRUST-01] ' . $e->getMessage(), 0, $e);
        }
    }
}
