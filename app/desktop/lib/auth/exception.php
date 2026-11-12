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

class desktop_auth_exception extends Exception
{
    /**
     * @return array{message:string,status:int,extra:array<string,mixed>}
     */
    public static function resolveForApi(Throwable $e, string $fallback, int $defaultStatus = 500): array
    {
        $message = trim($e->getMessage());
        $status = $defaultStatus;
        $extra = [];

        if ($message === '') {
            return [
                'message' => $fallback,
                'status' => $defaultStatus,
                'extra' => $extra,
            ];
        }

        if ($e instanceof self) {
            $extra['reason'] = 'auth_internal';

            return [
                'message' => $message,
                'status' => 500,
                'extra' => $extra,
            ];
        }

        if (str_contains($message, '登录IP受限') || str_contains($message, 'IP受限')) {
            $status = 403;
            $extra['reason'] = 'ip_restricted';
        } elseif (str_contains($message, '图形验证码') || str_contains($message, '验证码不能为空')) {
            $status = 422;
            $extra['reason'] = 'idaas_captcha_required';
        } elseif (str_contains($message, '验证码不正确') || str_contains($message, '验证码验证失败')) {
            $status = 422;
            $extra['reason'] = 'idaas_captcha_invalid';
        } elseif (str_contains($message, '短信')) {
            $status = 502;
            $extra['reason'] = 'idaas_sms_failed';
        } elseif (str_contains($message, '二次认证') || str_contains($message, '二次验证')) {
            $status = 410;
            $extra['reason'] = 'idaas_second_factor_failed';
        }

        return [
            'message' => $message,
            'status' => $status,
            'extra' => $extra,
        ];
    }
}
