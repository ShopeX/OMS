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
 * IDAAS 登录与二次认证（Vue JWT 专用；erpapi account 仅透传 API）。
 */
class desktop_auth_idaas
{
    public static function isEnabled(): bool
    {
        $idaasEnable = app::get('ome')->getConf('pam.passport.idaas.enable');

        return defined('IDAAS_LOGIN')
            && true == constant('IDAAS_LOGIN')
            && $idaasEnable == 'true';
    }

    /**
     * @return array{ok:bool,message:string,code:string,captcha:string}
     */
    public static function fetchCaptcha(string $loginName = ''): array
    {
        if (defined('DEV_ENV') && DEV_ENV) {
            return [
                'ok' => true,
                'message' => '',
                'code' => 'dev-captcha-code',
                'captcha' => '',
            ];
        }

        $payload = [];
        if ($loginName !== '') {
            $payload['login_name'] = $loginName;
        }

        $rsp = kernel::single('erpapi_router_request')->set('idaas', 'aliyun')->account_captcha($payload);
        $data = self::normalizeCaptchaPayload($rsp);
        $code = trim((string)($data['code'] ?? ''));
        $captcha = trim((string)($data['captcha'] ?? ''));

        if ($code === '' || $captcha === '') {
            $message = trim((string)($rsp['msg'] ?? ($rsp['err_msg'] ?? 'IDAAS 验证码获取失败')));

            return [
                'ok' => false,
                'message' => $message !== '' ? $message : 'IDAAS 验证码获取失败',
                'code' => '',
                'captcha' => '',
            ];
        }

        return [
            'ok' => true,
            'message' => '',
            'code' => $code,
            'captcha' => self::normalizeCaptchaImage($captcha),
        ];
    }

    /**
     * 密码校验通过后：IDAAS login → 如需 2FA 则发 verifycode 并返回 Vue 挑战。
     *
     * @return array{message:string,data:array,status:int}|null null = IDAAS 已通过
     */
    public static function authenticate(
        int $userId,
        string $loginName,
        string $password,
        string $accountType,
        string $captchaCode = '',
        string $captchaValue = ''
    ): ?array {
        // 与 sync_account / hchsafe 一致：IDAAS 侧存 S# + MD5(明文)，不能传明文
        $idaas = kernel::single('erpapi_router_request')->set('idaas', 'aliyun');
        $rsp = $idaas->account_login([
            'login_name' => $loginName,
            'login_password' => pam_encrypt::get_encrypted_password($password, $accountType),
            'member_id' => $userId,
            'captcha_code' => $captchaCode,
            'captcha_value' => $captchaValue,
        ]);

        if (($rsp['rsp'] ?? '') === 'succ') {
            return null;
        }

        $factor = self::readSecondFactor($rsp);
        if ($factor === null) {
            $message = trim((string)($rsp['msg'] ?? ($rsp['err_msg'] ?? 'IDAAS 认证失败')));
            $errorCode = trim((string)($rsp['res'] ?? ''));

            if (self::isCaptchaRequiredError($errorCode, $message)) {
                return self::captchaChallenge($loginName, 'idaas_captcha_required', '请输入图形验证码');
            }

            if (self::isCaptchaInvalidError($errorCode, $message)) {
                return self::captchaChallenge(
                    $loginName,
                    'idaas_captcha_invalid',
                    $message !== '' ? $message : '图形验证码不正确'
                );
            }

            return self::challenge(
                $message !== '' ? $message : 'IDAAS 认证失败',
                self::idaasErrorExtra($errorCode),
                403
            );
        }

        $message = trim((string)($rsp['msg'] ?? ($rsp['err_msg'] ?? '登录未完成，需要进行二次认证')));
        if ($message === '') {
            $message = '登录未完成，需要进行二次认证';
        }

        return self::beginSecondFactor($factor, $userId, $loginName, $accountType, $message);
    }

    /**
     * @return array{ok:bool,message:string,status?:int,reason?:string}
     */
    public static function resendCode(string $idaasUsername, string $fid): array
    {
        desktop_auth_second_factor::assertSmsAllowed();

        if (!desktop_auth_second_factor::getPending($fid)) {
            return self::expiredSecondFactorResult();
        }

        $rsp = kernel::single('erpapi_router_request')->set('idaas', 'aliyun')->account_getMobileVerifyCode([
            'login_name' => $idaasUsername,
            'fid' => $fid,
        ]);

        if (($rsp['rsp'] ?? '') !== 'succ') {
            $message = trim((string)($rsp['msg'] ?? ($rsp['err_msg'] ?? '短信发送失败')));
            if ($message === '') {
                $message = '短信发送失败';
            }

            if (self::isExpiredSecondFactorMessage($message)) {
                desktop_auth_second_factor::clearPending($fid);

                return self::expiredSecondFactorResult();
            }

            return [
                'ok' => false,
                'message' => $message,
                'status' => 502,
                'reason' => 'idaas_sms_failed',
            ];
        }

        desktop_auth_second_factor::markSmsSent();

        return ['ok' => true, 'message' => ''];
    }

    /**
     * 校验短信并完成二次认证（Vue verifySecondFactor）。
     *
     * @return array{ok:bool,status:int,message:string,user_id?:int,login_name?:string}
     */
    public static function completeVerify(string $fid, string $idaasUsername, string $code): array
    {
        desktop_auth_second_factor::assertVerifyAllowed($fid);

        $pending = desktop_auth_second_factor::getPending($fid);
        if (!$pending) {
            return self::expiredSecondFactorResult();
        }

        $userId = (int)$pending['user_id'];
        $mobile = '';
        $user = app::get('desktop')->model('users')->db_dump(['user_id' => $userId], 'mobile');
        if ($user) {
            $mobile = (string)($user['mobile'] ?? '');
        }

        $rsp = kernel::single('erpapi_router_request')->set('idaas', 'aliyun')->account_checkMobileVerifyCode([
            'login_name' => $idaasUsername,
            'mobileverifycode' => $code,
            'fid' => $fid,
            'mobile' => $mobile,
            'merchantName' => self::merchantName(),
        ]);

        if (($rsp['rsp'] ?? '') !== 'succ') {
            desktop_auth_second_factor::recordVerifyFailure($fid);
            $message = trim((string)($rsp['msg'] ?? ($rsp['err_msg'] ?? '验证码验证失败')));
            if ($message === '') {
                $message = '验证码验证失败';
            }

            if (self::isExpiredSecondFactorMessage($message)) {
                desktop_auth_second_factor::clearPending($fid);

                return self::expiredSecondFactorResult();
            }

            return ['ok' => false, 'status' => 401, 'message' => $message];
        }

        desktop_auth_second_factor::clearVerifyFailures($fid);

        $loginName = trim((string)($pending['login_name'] ?? ''));
        if ($loginName === '') {
            $loginName = $idaasUsername;
        }

        return [
            'ok' => true,
            'status' => 200,
            'message' => '',
            'user_id' => $userId,
            'login_name' => $loginName,
        ];
    }

    /**
     * @return array{message:string,data:array,status:int}
     */
    protected static function beginSecondFactor(
        array $factor,
        int $userId,
        string $loginName,
        string $accountType,
        string $message
    ): array {
        $loginFid = self::extractFid($factor);
        $idaasUsername = self::extractIdaasUsername($factor, $loginName);
        $phoneMask = trim((string)($factor['phoneNumber'] ?? ($factor['mobile_mask'] ?? '')));

        // 与旧 account.php 一致：先调 verifycode 发短息，fid 常由短信接口返回而非 login 响应
        desktop_auth_second_factor::assertSmsAllowed();

        $smsRsp = kernel::single('erpapi_router_request')->set('idaas', 'aliyun')->account_getMobileVerifyCode([
            'login_name' => $idaasUsername,
            'fid' => $loginFid,
        ]);

        $fid = self::extractFid(is_array($smsRsp['data'] ?? null) ? $smsRsp['data'] : []);
        if ($fid === '') {
            $fid = $loginFid;
        }

        if (($smsRsp['rsp'] ?? '') !== 'succ') {
            $failMsg = trim((string)($smsRsp['msg'] ?? ($smsRsp['err_msg'] ?? '短信发送失败')));
            if ($fid === '') {
                return self::challenge(
                    $failMsg !== '' ? $failMsg : '短信发送失败',
                    [],
                    502
                );
            }

            return self::secondFactorPayload(
                $failMsg !== '' ? $failMsg : '短信发送失败',
                $fid,
                $idaasUsername,
                $phoneMask,
                false,
                502
            );
        }

        if ($fid === '') {
            return self::challenge('二次验证凭证缺失，请重新登录', [], 502);
        }

        desktop_auth_second_factor::markSmsSent();

        if (!desktop_auth_second_factor::getPending($fid)) {
            desktop_auth_second_factor::savePending($fid, $userId, $accountType, $loginName, $idaasUsername);
        }

        if ($message === '') {
            $message = '登录未完成，需要进行二次认证';
        }

        return self::secondFactorPayload($message, $fid, $idaasUsername, $phoneMask, true, 403);
    }

    /**
     * @return array{message:string,data:array,status:int}
     */
    protected static function secondFactorPayload(
        string $message,
        string $fid,
        string $idaasUsername,
        string $phoneMask,
        bool $smsSent,
        int $status
    ): array {
        return self::challenge($message, [
            'need_mobile_code' => true,
            'needSecondFactor' => true,
            'fid' => $fid,
            'username' => $idaasUsername,
            'mobile_mask' => $phoneMask,
            'phoneNumber' => $phoneMask,
            'sms_sent' => $smsSent,
        ], $status);
    }

    protected static function extractFid(array $data): string
    {
        foreach (['fid', 'factorId', 'factor_id', 'loginFid'] as $key) {
            $value = trim((string)($data[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    protected static function extractIdaasUsername(array $data, string $loginName): string
    {
        $idaasUsername = trim((string)($data['username'] ?? ($data['login_name'] ?? '')));
        if ($idaasUsername === '') {
            $idaasUsername = base_shopnode::node_id('ome') . '.' . $loginName;
        }

        return $idaasUsername;
    }

    /**
     * @return array<string,mixed>|null
     */
    protected static function readSecondFactor(array $rsp): ?array
    {
        if (!in_array(($rsp['rsp'] ?? ''), ['fail', 'failure'], true)) {
            return null;
        }

        $candidates = [];
        if (isset($rsp['data']) && is_array($rsp['data'])) {
            $candidates[] = $rsp['data'];
        }
        if (isset($rsp['response']['data']) && is_array($rsp['response']['data'])) {
            $candidates[] = $rsp['response']['data'];
        }

        foreach ($candidates as $data) {
            $needsSecondFactor = filter_var($data['needSecondFactor'] ?? false, FILTER_VALIDATE_BOOLEAN)
                || filter_var($data['needSecondverifycode'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if ($needsSecondFactor || self::extractFid($data) !== '') {
                return $data;
            }
        }

        return null;
    }

    /**
     * @return array{message:string,data:array,status:int}
     */
    protected static function challenge(string $message, array $data, int $status): array
    {
        return ['message' => $message, 'data' => $data, 'status' => $status];
    }

    protected static function merchantName(): string
    {
        $shopMdl = app::get('ome')->model('shop');
        $names = [];
        foreach ($shopMdl->getList('addon', ['node_type' => 'taobao', 'filter_sql' => 'node_id is not null and node_id !=""']) as $row) {
            if ($row['addon']['nickname']) {
                $names[] = (string)$row['addon']['nickname'];
            }
        }

        return implode(',', $names);
    }

    /**
     * @return array<string,mixed>
     */
    protected static function normalizeCaptchaPayload(array $rsp): array
    {
        if (isset($rsp['data']) && is_array($rsp['data'])) {
            return $rsp['data'];
        }

        if (isset($rsp['data']) && is_string($rsp['data']) && $rsp['data'] !== '') {
            $decoded = json_decode($rsp['data'], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        if (isset($rsp['response']) && is_string($rsp['response'])) {
            $decoded = json_decode($rsp['response'], true);
            if (is_array($decoded['data'] ?? null)) {
                return $decoded['data'];
            }
        }

        return [];
    }

    protected static function normalizeCaptchaImage(string $captcha): string
    {
        if ($captcha === '') {
            return '';
        }
        if (str_starts_with($captcha, 'data:') || str_starts_with($captcha, 'http://') || str_starts_with($captcha, 'https://')) {
            return $captcha;
        }

        return 'data:image/png;base64,' . $captcha;
    }

    protected static function isCaptchaRequiredError(string $errorCode, string $message): bool
    {
        return $errorCode === 'InvalidParameter.Captcha.IsNull';
    }

    protected static function isCaptchaInvalidError(string $errorCode, string $message): bool
    {
        if (self::isCaptchaRequiredError($errorCode, $message)) {
            return false;
        }

        return self::isCaptchaError($message, $errorCode);
    }

    /**
     * @return array{message:string,data:array,status:int}
     */
    protected static function captchaChallenge(string $loginName, string $reason, string $defaultMessage): array
    {
        $extra = self::buildCaptchaChallengeData($loginName, $reason);
        $message = $defaultMessage;
        if (!empty($extra['captcha_fetch_error'])) {
            $message = (string)$extra['captcha_fetch_error'];
        }

        return self::challenge($message, $extra, 422);
    }

    /**
     * @return array<string,mixed>
     */
    protected static function idaasErrorExtra(string $errorCode): array
    {
        $extra = [];
        if ($errorCode !== '') {
            $extra['idaas_error_code'] = $errorCode;
        }

        return $extra;
    }

    /**
     * @return array<string,mixed>
     */
    protected static function buildCaptchaChallengeData(string $loginName, string $reason): array
    {
        $extra = [
            'need_idaas_captcha' => true,
            'reason' => $reason,
        ];

        $captcha = self::fetchCaptcha($loginName);
        if ($captcha['ok']) {
            $extra['captcha_code'] = $captcha['code'];
            $extra['captcha'] = $captcha['captcha'];
        } else {
            $extra['captcha_fetch_error'] = $captcha['message'];
        }

        return $extra;
    }

    protected static function isCaptchaError(string $message, string $errorCode): bool
    {
        if ($errorCode !== '' && stripos($errorCode, 'Captcha') !== false) {
            return true;
        }

        return str_contains($message, '验证码');
    }

    protected static function isExpiredSecondFactorMessage(string $message): bool
    {
        if ($message === '') {
            return false;
        }

        if (str_contains($message, '重新进行账号密码认证') || str_contains($message, '重新登录')) {
            return true;
        }

        return str_contains($message, 'fid') && (str_contains($message, '失效') || str_contains($message, '过期'));
    }

    /**
     * @return array{ok:bool,status:int,message:string,reason:string}
     */
    protected static function expiredSecondFactorResult(): array
    {
        return [
            'ok' => false,
            'status' => 410,
            'message' => '二次认证会话已失效，请重新登录',
            'reason' => 'second_factor_expired',
        ];
    }
}
