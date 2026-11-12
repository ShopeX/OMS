<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

final class desktop_capability_user_password
{
    private object $usersModel;
    private object $userLib;
    private object $pamAccountModel;
    private $persistPassword;
    private $revokeTokenVersion;
    private $logPasswordChange;
    private $syncAccount;
    private string $accountType;

    public function __construct(
        ?object $usersModel = null,
        ?object $userLib = null,
        ?object $pamAccountModel = null,
        ?callable $persistPassword = null,
        ?callable $revokeTokenVersion = null,
        ?callable $logPasswordChange = null,
        ?callable $syncAccount = null,
        ?string $accountType = null
    ) {
        $this->usersModel = $usersModel ?? app::get('desktop')->model('users');
        $this->userLib = $userLib ?? kernel::single('desktop_user');
        $this->pamAccountModel = $pamAccountModel ?? app::get('pam')->model('account');
        $this->persistPassword = $persistPassword ?? function (array $saveData): bool {
            return $this->usersModel->save($saveData);
        };
        $this->revokeTokenVersion = $revokeTokenVersion ?? static function (int $userId): void {
            desktop_auth_jwt::incrementTokenVersion($userId);
        };
        $this->logPasswordChange = $logPasswordChange ?? function (int $userId): void {
            app::get('desktop')->model('user_logs')->changePwd($userId);
        };
        $this->syncAccount = $syncAccount ?? static function (array $payload, string $mode): array {
            return kernel::single('desktop_user_auth')->sync_account($payload, $mode);
        };
        $this->accountType = $accountType ?? pam_account::get_account_type('desktop');
    }

    public function change(array $request, int $currentUserId): array
    {
        $oldLoginPassword = trim((string) ($request['old_login_password'] ?? ''));
        $newLoginPassword = trim((string) ($request['new_login_password'] ?? ''));
        $confirmPassword = trim((string) ($request['confirm_password'] ?? ''));
        $requestedUserId = (int) ($request['user_id'] ?? 0);
        $errorMsg = '';

        if ($oldLoginPassword === '' || $newLoginPassword === '' || $confirmPassword === '') {
            throw new InvalidArgumentException('参数不完整');
        }
        if ($newLoginPassword !== $confirmPassword) {
            throw new InvalidArgumentException('两次密码不一致');
        }

        $targetUserId = $requestedUserId > 0 && $requestedUserId !== $currentUserId ? $requestedUserId : $currentUserId;

        if ($targetUserId === $currentUserId && $newLoginPassword === $oldLoginPassword) {
            throw new InvalidArgumentException('新密码和旧密码需要不一致');
        }

        $encryptedOldPassword = pam_encrypt::get_encrypted_password($oldLoginPassword, $this->accountType);
        if ($targetUserId !== $currentUserId) {
            $superRow = $this->usersModel->getList('user_id', ['super' => '1']);
            $superAccountId = (int) ($superRow[0]['user_id'] ?? 0);
            $passRow = $this->pamAccountModel->getList('account_id', [
                'account_id' => $superAccountId,
                'account_type' => $this->accountType,
                'login_password' => $encryptedOldPassword,
            ]);
            if (!$passRow) {
                throw new InvalidArgumentException('超级管理员密码不正确');
            }
        } else {
            $passRow = $this->pamAccountModel->getList('account_id', [
                'account_id' => $targetUserId,
                'account_type' => $this->accountType,
                'login_password' => $encryptedOldPassword,
            ]);
            if (!$passRow) {
                throw new InvalidArgumentException('原始密码不正确');
            }
        }

        $loginPassword = pam_encrypt::get_encrypted_password($newLoginPassword, $this->accountType);
        if (!$this->userLib->checkRepeatPassWord($targetUserId, $loginPassword, $errorMsg)) {
            throw new InvalidArgumentException($errorMsg);
        }

        $sdf = $this->usersModel->dump($targetUserId, '*', [':account@pam' => ['*']]);
        if (!$this->userLib->checkUserPassWordLength($targetUserId, $newLoginPassword, $errorMsg)) {
            throw new InvalidArgumentException($errorMsg);
        }
        if (!$this->userLib->validPassWord($newLoginPassword, $errorMsg, $sdf['name'] ?? '')) {
            throw new InvalidArgumentException($errorMsg);
        }
        if (!$this->userLib->checkPassword($newLoginPassword, $errorMsg)) {
            throw new InvalidArgumentException($errorMsg);
        }

        $saveData = [
            'user_id' => $targetUserId,
            'pam_account' => [
                'account_id' => $targetUserId,
                'login_password' => $loginPassword,
            ],
        ];

        $completed = desktop_auth_password_security::complete(
            $targetUserId,
            $saveData,
            $this->persistPassword,
            $this->revokeTokenVersion
        );
        if (!$completed) {
            throw new RuntimeException('密码保存失败，请稍后重试');
        }

        $this->userLib->checkUpdatePwd($targetUserId, true);
        ($this->logPasswordChange)($targetUserId);
        [$result, $message] = ($this->syncAccount)([
            'account_id' => $targetUserId,
            'login_name' => $sdf['account']['login_name'] ?? '',
            'login_password' => $loginPassword,
        ], 'password');
        if ($result === false) {
            throw new RuntimeException($message ?: '密码同步失败');
        }

        return ['message' => '密码修改成功'];
    }
}
