<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

final class desktop_capability_user_provisioning
{
    private object $usersModel;
    private $runUserAddHooks;
    private $saveOrganizationPermission;
    private $saveGround;
    private $saveDealerShopPermission;
    private $sendEmail;
    private $logUserAdd;
    private $syncAccount;

    public function __construct(
        ?object $usersModel = null,
        ?callable $runUserAddHooks = null,
        ?callable $saveOrganizationPermission = null,
        ?callable $saveGround = null,
        ?callable $saveDealerShopPermission = null,
        ?callable $sendEmail = null,
        ?callable $logUserAdd = null,
        ?callable $syncAccount = null
    ) {
        $this->usersModel = $usersModel ?? app::get('desktop')->model('users');
        $this->runUserAddHooks = $runUserAddHooks ?? static function (array $payload): void {
            foreach (kernel::servicelist('desktop_useradd') as $service) {
                if ($service instanceof desktop_interface_useradd) {
                    $service->useradd($payload);
                }
            }
        };
        $this->saveOrganizationPermission = $saveOrganizationPermission ?? static function (int $userId, array $orgIds): bool {
            return kernel::single('desktop_ctl_users')->saveOrganizationPermission($userId, $orgIds);
        };
        $this->saveGround = $saveGround ?? static function (array $payload): void {
            kernel::single('desktop_ctl_users')->save_ground($payload);
        };
        $this->saveDealerShopPermission = $saveDealerShopPermission ?? static function (array $payload): bool {
            return (bool) kernel::single('organization_cos')->save_operation_permission($payload);
        };
        $this->sendEmail = $sendEmail ?? static function (string $to, string $subject, string $body): void {
            kernel::single('console_email')->send($to, $subject, $body);
        };
        $this->logUserAdd = $logUserAdd ?? static function (array $payload): void {
            app::get('desktop')->model('user_logs')->addUser($payload);
        };
        $this->syncAccount = $syncAccount ?? static function (array $payload, string $mode): array {
            return kernel::single('desktop_user_auth')->sync_account($payload, $mode);
        };
    }

    public function create(array $request): array
    {
        $request['super'] = (int) ($request['super'] ?? 0);
        if ($request['super'] === 0 && empty($request['role'])) {
            throw new InvalidArgumentException('请至少选择一个工作组');
        }

        $request['mcp_enabled'] = (string) ($request['mcp_enabled'] ?? '1');
        // 账号类型：human（默认，人工操作员）/ service（服务账号，Agent 专用）
        $request['account_type'] = ($request['account_type'] ?? '') === 'service' ? 'service' : 'human';
        // 服务账号：禁登录控制台（密码随机），无有效凭据
        $isService = $request['account_type'] === 'service';
        $request['pam_account']['login_name'] = trim((string) ($request['pam_account']['login_name'] ?? ''));
        $rawPassword = $isService
            ? substr(str_shuffle('abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 8) . '!aA1' . bin2hex(random_bytes(8)) // 随机密码含大小写数字（满足密码策略；服务账号禁登录，无有效凭据）
            : (string) ($request['pam_account']['login_password'] ?? '');
        if (!$isService && $rawPassword === '') {
            throw new InvalidArgumentException('人工账号必须设置密码');
        }
        // validate 用明文（usersModel::validate 内含密码强度校验，bcrypt 串会被误判）；
        // re_password 一致性检查对齐（legacy 表单字段）；validate 通过后再加密落库
        $request['pam_account']['login_password'] = $rawPassword;
        if (!isset($_POST['re_password'])) {
            $_POST['re_password'] = $rawPassword;
        }
        $request['pam_account']['account_type'] = pam_account::get_account_type('desktop');
        $request['op_no'] = strtoupper(trim((string) ($request['op_no'] ?? '')));
        $request['create_time'] = time();
        $request['last_modify'] = $request['create_time'];
        foreach ((array) ($request['role'] ?? []) as $roleId) {
            $request['roles'][] = ['role_id' => $roleId];
        }

        $msg = '';
        if (!$this->usersModel->validate($request, $msg)) {
            throw new InvalidArgumentException($msg !== '' ? $msg : '操作失败');
        }
        // 校验通过后加密落库（dbeav has_parent 由 save 处理）
        $request['pam_account']['login_password'] = pam_encrypt::get_encrypted_password($rawPassword, pam_account::get_account_type('desktop'));
        if (!$this->usersModel->save($request)) {
            throw new RuntimeException('保存失败');
        }

        ($this->runUserAddHooks)($request);

        if (!empty($request['store_org_conf']['orgIds'][0])) {
            $orgIds = array_values(array_filter(explode(',', (string) $request['store_org_conf']['orgIds'][0])));
            ($this->saveOrganizationPermission)((int) $request['user_id'], $orgIds);
        }
        if ($request['super'] === 0) {
            ($this->saveGround)($request);
        }
        if (!empty($request['dealer_shop_conf'])) {
            ($this->saveDealerShopPermission)($request);
        }
        if (!empty($request['email'])) {
            ($this->sendEmail)(trim((string) $request['email']) . '#' . $request['name'], '账号开通', '    您的账号是 ' . $request['pam_account']['login_name'] . "\n密码是 " . $rawPassword . "\n请尽快登录，并修改密码");
        }
        ($this->logUserAdd)($request);

        [$result, $message] = ($this->syncAccount)([
            'account_id' => $request['pam_account']['account_id'],
            'login_name' => $request['pam_account']['login_name'],
            'login_password' => $request['pam_account']['login_password'],
        ], 'add');
        if ($result === false) {
            throw new RuntimeException($message ?: '同步失败');
        }

        return ['success' => true, 'user_id' => (int) ($request['user_id'] ?? 0)];
    }
}
