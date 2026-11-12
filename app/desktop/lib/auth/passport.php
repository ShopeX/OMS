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

class desktop_auth_passport
{
    public static function accountType(): string
    {
        return pam_account::get_account_type('desktop');
    }

    public static function makeAuth(): pam_auth
    {
        $auth = pam_auth::instance(self::accountType());
        $auth->set_appid('desktop');

        return $auth;
    }

    public static function needVcode(): bool
    {
        $auth = pam_auth::instance(self::accountType());
        $auth->set_appid('desktop');

        return $auth->is_enable_vcode();
    }

    public static function needMobileCheck(): bool
    {
        $setting = app::get('ome')->getConf('desktop.account.mobile.verify');
        if ($setting === 'false') {
            return false;
        }
        try {
            $smsSample = app::get('taoexlib')->model('sms_sample');
            $row = $smsSample->db_dump(['send_type' => 'login', 'status' => '1']);

            return (bool)$row;
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function verifyCaptchaById(string $captchaId, string $code): bool
    {
        if ($captchaId === '' || $code === '') {
            return false;
        }
        $kvstore = base_kvstore::instance('base_vcode');
        $stored = null;
        $kvstore->fetch('captcha_' . $captchaId, $stored);
        if (!$stored) {
            return false;
        }
        $kvstore->store('captcha_' . $captchaId, '', 1);

        return (string)$stored === (string)$code;
    }

    public static function generateCaptcha(): array
    {
        $captchaId = bin2hex(random_bytes(16));

        $vcode = kernel::single('base_vcode');
        $vcode->length(4);

        $code = $vcode->obj->get_code();
        $kvstore = base_kvstore::instance('base_vcode');
        $kvstore->store('captcha_' . $captchaId, $code, 300);

        $imageData = $vcode->obj->render();

        return [
            'captcha_id' => $captchaId,
            'image' => 'data:image/gif;base64,' . base64_encode($imageData),
        ];
    }

    public static function logLoginAttempt(string $type, array $authData): void
    {
        $log = [
            'event_time' => time(),
            'event_type' => $type,
            'event_data' => base_request::get_remote_addr() . ':' . ($authData['log_data'] ?? ''),
        ];
        app::get('pam')->model('log')->insert($log);
    }

    public static function loginFailureMessage(): string
    {
        $error = (string)($_SESSION['error'] ?? '');
        if ($error !== '') {
            if (str_contains($error, '登录IP受限') || str_contains($error, 'IP受限')) {
                return $error;
            }
            if (str_contains($error, '验证码')) {
                return $error;
            }
            if (str_contains($error, '登录信息异常')) {
                return $error;
            }
        }

        return '登录失败，请检查用户名或密码';
    }

    public static function loginFailureStatus(): int
    {
        $error = (string)($_SESSION['error'] ?? '');
        if ($error !== '' && (str_contains($error, '登录IP受限') || str_contains($error, 'IP受限'))) {
            return 403;
        }

        return 401;
    }

    /**
     * @return array<string, mixed>
     */
    public static function loginFailureExtra(string $needMobile): array
    {
        $extra = ['need_mobile_code' => $needMobile === '2'];
        $error = (string)($_SESSION['error'] ?? '');
        if ($error !== '' && (str_contains($error, '登录IP受限') || str_contains($error, 'IP受限'))) {
            $extra['reason'] = 'ip_restricted';
        }

        return $extra;
    }
}
