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
 * CSRF guard for browser write requests (Double-Submit Cookie pattern).
 *
 * Skips:
 *   - GET / HEAD / OPTIONS
 *   - loginExempt actions
 *   - Controllers with checkCSRF = false
 *
 * Enforces:
 *   - X-CSRF-Token double-submit when header is present
 *   - Phase 1 gray zone: legacy shopex-ui AJAX + traditional forms without header
 *
 * Note: Origin / Referer same-site check is intentionally disabled (HTTPS
 * termination behind reverse proxy often makes scheme mismatch).
 */
class desktop_auth_csrf_guard
{
    /**
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
        if (!$enabled) {
            return;
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }

        $currentAction = $_GET['act'] ?? 'index';
        if (in_array($currentAction, $loginExempt, true)) {
            return;
        }

        self::verifyCsrfToken($app);
    }

    /**
     * @param mixed $app
     * @return void
     */
    private static function verifyCsrfToken($app): void
    {
        $headerToken = trim($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $requestedBy = $_SERVER['HTTP_X_REQUESTED_BY'] ?? '';
        $contentType = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
        $controller = (string) ($_GET['ctl'] ?? '');
        $isVueApi = str_starts_with($controller, 'api_');

        if ($headerToken !== '') {
            $csrf = kernel::single('desktop_csrf', $app);
            if (!$csrf->verify($headerToken)) {
                self::respondFailed('csrf_failed');
            }
            return;
        }

        $isLegacyAjax = ($requestedBy === 'shopex-ui');
        $isTraditionalForm = str_contains($contentType, 'application/x-www-form-urlencoded')
            || str_contains($contentType, 'multipart/form-data');

        if (!$isVueApi && ($isLegacyAjax || $isTraditionalForm)) {
            return;
        }

        self::respondFailed('csrf_failed');
    }

    /**
     * @param string $reason  'csrf_failed' | 'origin_rejected'
     * @return void
     */
    private static function respondFailed(string $reason): void
    {
        desktop_auth_jwt_transport::respondCsrfFailed($reason);
    }
}
