<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

final class desktop_capability_user_profile
{
    private object $usersModel;

    public function __construct(?object $usersModel = null)
    {
        $this->usersModel = $usersModel ?? app::get('desktop')->model('users');
    }

    public function update(int $userId, array $request): array
    {
        if ($userId < 1) {
            throw new InvalidArgumentException('用户ID不能为空');
        }

        $name = trim((string) ($request['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('请输入姓名');
        }

        $mobile = trim((string) ($request['mobile'] ?? ''));
        if ($mobile !== '' && (strlen($mobile) !== 11 || !ctype_digit($mobile))) {
            throw new InvalidArgumentException('手机号码格式不正确！');
        }

        $current = method_exists($this->usersModel, 'dump')
            ? $this->usersModel->dump($userId, '*', [':account@pam' => ['*']])
            : [];
        $currentLoginName = '';
        if (is_array($current) && isset($current['account']) && is_array($current['account'])) {
            $currentLoginName = (string) ($current['account']['login_name'] ?? '');
        }

        $payload = [
            'account_id' => $userId,
            'user_id' => $userId,
            'op_no' => strtoupper(trim((string) ($request['op_no'] ?? ''))),
            'name' => $name,
            'mobile' => $mobile,
            'email' => trim((string) ($request['email'] ?? '')),
            'memo' => (string) ($request['memo'] ?? ''),
            'status' => (string) ($request['status'] ?? '1'),
            'mcp_enabled' => (string) ($request['mcp_enabled'] ?? '1'),
            'pam_account' => [
                'account_id' => $userId,
                'login_name' => (string) (($currentLoginName !== '' ? $currentLoginName : ($request['login_name'] ?? '')) ?: '')
            ]
        ];

        if (!$this->usersModel->editUser($payload)) {
            throw new RuntimeException('编辑失败');
        }

        return ['success' => true];
    }
}
