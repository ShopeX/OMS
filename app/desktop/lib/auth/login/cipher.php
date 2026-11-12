<?php

/**
 * SCRUM-7779 登录密文口径（复用 legacy app/pam/lib/passport/basic.php:89-108）
 *
 * 口径：AES-128-CBC + ZeroPadding + base64
 *    key = 服务端日期(YYYY-MM-DD) + "eDu189"   （16 字节）
 *    iv  = OPENSSL_CPIHER_IV 前 16 字节
 * 明文：JSON {uname, password, terminal, nonce}
 *
 * 注意：ZeroPadding（不是 PKCS7）—— 前端以 CryptoJS.pad.ZeroPadding 对齐；
 *       WebCrypto 只能 PKCS7，无法与本口径互通，故前端固定用 crypto-js。
 */
class desktop_auth_login_cipher
{
    /** key 后缀，与 legacy 前端 encrypt_login() 保持一致 */
    private const KEY_SUFFIX = 'eDu189';

    /** legacy 兼容：key 后缀 + 日期 */
    public static function keyFor(string $date): string
    {
        return $date . self::KEY_SUFFIX;
    }

    /** 下发给前端的 iv（与 legacy basic-login.html 的 login_iv 同源） */
    public static function iv(): string
    {
        if (!defined('OPENSSL_CPIHER_IV')) {
            return '';
        }

        return substr((string)constant('OPENSSL_CPIHER_IV'), 0, 16);
    }

    /** 下发给前端的 key 日期（用服务端日期，规避客户端时区/跨零点差异） */
    public static function keyDate(): string
    {
        return date('Y-m-d');
    }

    /**
     * 解密登录密文；解不开或明文不是合法 JSON 时返回 null。
     * 允许 ±1 天的 key（覆盖「meta 取件 → 提交」跨零点）——重放由 nonce 一次性兜住。
     *
     * @return array<string,mixed>|null
     */
    public static function decrypt(string $cipher): ?array
    {
        $iv = self::iv();
        if ($cipher === '' || $iv === '') {
            return null;
        }

        foreach ([self::keyDate(), date('Y-m-d', time() - 86400), date('Y-m-d', time() + 86400)] as $date) {
            $raw = openssl_decrypt($cipher, 'aes-128-cbc', self::keyFor($date), OPENSSL_ZERO_PADDING, $iv);
            if ($raw === false) {
                continue;
            }

            $decoded = json_decode(self::stripZeroPadding($raw), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * 从请求载荷取登录账号（SCRUM-7779 统一口径）。
     *
     * - 带 login_cipher：只认密文里的 uname，**不再回退明文 username**（账号不落明文请求体）；
     *   密文损坏/解不开时返回 null，调用方按「登录信息异常」拒绝。
     * - 不带 login_cipher：回退顶层 username（老客户端 / 未启用密文的站点），
     *   再回退 $plainFallback（如 legacy GET 直连的 query 参数）。
     *
     * @param array<string,mixed> $payload
     */
    public static function resolveUsername(array $payload, string $plainFallback = ''): ?string
    {
        $cipher = (string)($payload['login_cipher'] ?? '');
        if ($cipher === '') {
            return trim((string)($payload['username'] ?? $plainFallback));
        }

        $decoded = self::decrypt($cipher);
        if (!is_array($decoded)) {
            return null;
        }

        return trim((string)($decoded['uname'] ?? ''));
    }

    /** 去掉 ZeroPadding 尾部填充字节（等价 legacy filterNonPrintableChar，但只削尾部） */
    private static function stripZeroPadding(string $raw): string
    {
        return rtrim($raw, "\x00..\x1f");
    }
}
