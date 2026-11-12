<?php
/**
 * JWT Request Guard
 *
 * Extracts and validates the access token from the appropriate source
 * based on client type, enforcing token type = 'access' for business APIs.
 *
 * Does NOT handle login/refresh — those go through desktop_auth_jwt_client.
 */

class desktop_auth_jwt_guard
{
    /**
     * Authenticate an incoming request using the JWT access token.
     *
     * @param desktop_auth_jwt_client|null $client  defaults to building from current request
     * @return desktop_auth_jwt_result
     */
    public static function authenticateRequest(?desktop_auth_jwt_client $client = null): desktop_auth_jwt_result
    {
        $client = $client ?? desktop_auth_jwt_client::fromRequest();
        $token = self::extractTokenForClient($client);

        if ($token === null || $token === '') {
            return desktop_auth_jwt_result::unauthorized(
                '登录状态已过期',
                'session_expired',
                [],
                false
            );
        }

        // decodeWithValidation also checks: user existence, password_changed_at, token_version
        try {
            $claims = desktop_auth_jwt::decodeWithValidation($token, true);
        } catch (desktop_auth_exception $e) {
            return self::handleJwtException($e);
        }

        // Enforce access token type for business API requests
        $type = $claims['type'] ?? '';
        if ($type !== 'access') {
            // Refresh tokens cannot be used for business API calls
            if ($type === 'refresh') {
                return desktop_auth_jwt_result::unauthorized(
                    '登录状态已过期',
                    'session_expired',
                    [],
                    false
                );
            }
            return desktop_auth_jwt_result::unauthorized(
                '登录状态已过期',
                'session_expired',
                [],
                false
            );
        }

        // Verify user still exists and is active
        $userId = (int)($claims['user_id'] ?? 0);
        if ($userId <= 0) {
            return desktop_auth_jwt_result::unauthorized(
                '登录状态已过期',
                'session_expired',
                [],
                true
            );
        }

        $userModel = app::get('desktop')->model('users');
        $user = $userModel->dump(['user_id' => $userId], 'user_id,status');
        if (!$user || ($user['status'] ?? '0') !== '1') {
            return desktop_auth_jwt_result::unauthorized(
                '登录状态已过期',
                'session_expired',
                [],
                true
            );
        }

        return desktop_auth_jwt_result::authenticated(
            $userId,
            [
                'login_name' => $claims['login_name'] ?? '',
                'super'     => $claims['super'] ?? false,
                'roles'     => $claims['roles'] ?? [],
            ],
            self::transportModeForClient($client)
        );
    }

    /**
     * Map client token-source mode to result transportMode label.
     */
    private static function transportModeForClient(desktop_auth_jwt_client $client): string
    {
        $mode = $client->tokenSourceMode();
        if ($mode === desktop_auth_jwt_client::MODE_WAP_COOKIE) {
            return 'wap';
        }
        if ($mode === desktop_auth_jwt_client::MODE_HEADER_BODY) {
            return 'header';
        }
        return 'browser';
    }

    /**
     * Extract the raw token string based on client token-source mode.
     *
     * @param desktop_auth_jwt_client $client
     * @return string|null
     */
    private static function extractTokenForClient(desktop_auth_jwt_client $client): ?string
    {
        $mode = $client->tokenSourceMode();

        if ($mode === desktop_auth_jwt_client::MODE_HEADER_BODY) {
            return desktop_auth_jwt::extractHeaderAccessToken();
        }

        if ($mode === desktop_auth_jwt_client::MODE_WAP_COOKIE) {
            return desktop_auth_jwt::extractWapAccessToken();
        }

        return desktop_auth_jwt::extractBrowserAccessToken();
    }

    /**
     * Convert a JWT exception into an unauthorized result.
     *
     * @param desktop_auth_exception $e
     * @return desktop_auth_jwt_result
     */
    private static function handleJwtException(desktop_auth_exception $e): desktop_auth_jwt_result
    {
        $message = $e->getMessage();

        // Irrevocable failures: revoke / password change — clear cookies so the
        // next request won't present the same bad token.
        if (str_contains($message, 'revoked')
            || str_contains($message, 'Password has changed')) {
            return desktop_auth_jwt_result::unauthorized(
                $message,
                'jwt_revocable',
                [],
                true
            );
        }

        // Token has expired — do NOT clear refresh cookie; the frontend can
        // use it to obtain a new access token.
        if (str_contains($message, 'expired') || str_contains($message, 'expire')) {
            return desktop_auth_jwt_result::unauthorized(
                '登录状态已过期',
                'session_expired',
                [],
                false
            );
        }

        // Other decode failures (bad signature, malformed, etc.)
        return desktop_auth_jwt_result::unauthorized(
            '登录状态已过期',
            'session_expired',
            [],
            false
        );
    }
}
