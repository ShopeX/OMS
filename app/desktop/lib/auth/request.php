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

/**
 * Unified per-request auth bootstrap for desktop controllers.
 */
class desktop_auth_request
{
    /**
     * Authenticate JWT, populate auth context, clear cookies when revoked.
     *
     * @return desktop_auth_jwt_result
     */
    public static function bootstrap(): desktop_auth_jwt_result
    {
        $result = desktop_auth_jwt_guard::authenticateRequest();

        if ($result->isAuthenticated()) {
            $userId = (int)($result->userId() ?? 0);
            desktop_auth_context::setUserId($userId);
            $data = $result->data();
            $loginName = (string)($data['login_name'] ?? '');
            if ($loginName !== '') {
                desktop_auth_context::setLoginName($loginName);
            }
            desktop_auth_context::setProfile([
                'login_name' => $loginName,
                'is_super' => (bool)($data['super'] ?? false),
                'roles' => is_array($data['roles'] ?? null) ? $data['roles'] : [],
            ]);
        } elseif ($result->shouldClearCookies()) {
            $client = desktop_auth_jwt_client::fromRequest();
            if ($client->tokenSourceMode() === desktop_auth_jwt_client::MODE_WAP_COOKIE) {
                desktop_auth_jwt_transport::clearWapAuthCookies();
            } else {
                desktop_auth_jwt_transport::clearDesktopAuthCookies();
            }
        }

        return $result;
    }

    /**
     * Parse JSON request body with form-urlencoded fallback.
     *
     * @return array<string, mixed>
     */
    public static function jsonBody(): array
    {
        $content = file_get_contents('php://input');
        if (!$content) {
            return $_POST ?: [];
        }
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $_POST ?: [];
        }

        return is_array($data) ? $data : [];
    }

    /**
     * Enforce CSRF + Origin for browser write requests when enabled.
     *
     * @param bool                        $enabled
     * @param array                       $loginExempt
     * @param mixed                       $app
     * @return void
     */
    public static function enforceWriteProtection(
        bool $enabled,
        array $loginExempt,
        $app
    ): void {
        desktop_auth_csrf_guard::enforceWriteProtection($enabled, $loginExempt, $app);
    }
}
