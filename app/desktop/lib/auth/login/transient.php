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
 * Login transient KV cleanup — bridge data (captcha, 2FA, OIDC, trust grant, error counter).
 *
 * Called on successful login so temporary keys are deleted immediately instead of waiting for TTL.
 */
class desktop_auth_login_transient
{
    /**
     * @param array{
     *   captcha_id?: string,
     *   fid?: string,
     *   oidc_state?: string,
     *   error_counter_app_id?: string
     * } $context
     */
    public static function purge(array $context = []): void
    {
        $captchaId = trim((string)($context['captcha_id'] ?? ''));
        if ($captchaId !== '') {
            base_kvstore::instance('base_vcode')->store('captcha_' . $captchaId, '', 1);
        }

        $fid = trim((string)($context['fid'] ?? ''));
        if ($fid !== '') {
            desktop_auth_second_factor::clearPending($fid);
            desktop_auth_second_factor::clearVerifyFailures($fid);
        }

        $oidcState = trim((string)($context['oidc_state'] ?? ''));
        if ($oidcState !== '') {
            desktop_auth_oidc::discardChallenge($oidcState);
        }

        $appId = trim((string)($context['error_counter_app_id'] ?? ''));
        if ($appId !== '') {
            kernel::single('pam_login_error_counter')->reset($appId);
        }

        desktop_auth_second_factor::clearSmsThrottle();
        pam_trust_grant::revoke();
    }
}
