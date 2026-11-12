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
 * MCP 凭证管理 legacy 入口（desktop 框架菜单：MCP管理 / MCP凭证）。
 *
 * 功能：注册机密客户端（操作员型 / Agent 型）· 动态注册模式切换 ·
 *       待审批列表审批/拒绝 · 全量客户端列表 · 吊销。
 * 密钥仅注册响应里一次性明文显示（secret.html），库中只存 bcrypt 哈希。
 */
class mcpapi_ctl_admin_client extends desktop_controller
{
    var $workground = 'adminpanel';

    private function manager(): mcpapi_auth_client_manager
    {
        static $manager = null;
        if ($manager === null) {
            $manager = new mcpapi_auth_client_manager();
        }
        return $manager;
    }

    private function requirePermission(string $perm): void
    {
        if (!$this->has_permission($perm)) {
            $this->splash('fail', 'index.php?app=mcpapi&ctl=admin_client&act=index', '没有操作权限：' . $perm);
        }
    }

    private function operatorName(): string
    {
        return (string) (desktop_auth_context::loginName() ?: ('user#' . desktop_auth_context::userId()));
    }

    public function index()
    {
        $this->requirePermission('mcpapi.client.list');

        $clients = $this->manager()->listAllClients();
        $pending = $this->manager()->listPendingClients();
        $pendingIds = [];
        foreach ((array) $pending as $p) {
            $pendingIds[(string) ($p['client_id'] ?? '')] = true;
        }

        // 行展示修饰：状态 / 类型 / 时间
        $rows = [];
        foreach ((array) $clients as $row) {
            if (!is_array($row)) {
                continue;
            }
            $cid = (string) ($row['client_id'] ?? '');
            $enabled = in_array($row['enabled'] ?? null, [true, 1, '1', 'true'], true);
            $revoked = (int) ($row['revoked_at'] ?? 0) > 0;
            $row['kind_label'] = (($row['client_kind'] ?? 'operator') === 'agent') ? 'Agent型' : '操作员型';
            $row['origin_label'] = (($row['registration_origin'] ?? 'static') === 'dynamic') ? '动态注册' : '管理端注册';
            $row['status_label'] = $revoked ? '已吊销' : ($enabled ? '已启用' : (isset($pendingIds[$cid]) ? '待审批' : '已停用'));
            $row['status_class'] = $revoked ? 'mcp-st-revoked' : ($enabled ? 'mcp-st-on' : 'mcp-st-pending');
            $row['created_fmt'] = (int) ($row['created_at'] ?? 0) > 0 ? date('Y-m-d H:i', (int) $row['created_at']) : '—';
            $row['expires_fmt'] = (int) ($row['expires_at'] ?? 0) > 0 ? date('Y-m-d', (int) $row['expires_at']) : '永久';
            $row['scopes_label'] = implode('、', (array) ($row['scopes'] ?? []));
            $rows[] = $row;
        }

        // 服务账号下拉（account_type=service 且未禁用）
        $serviceAccounts = [];
        try {
            $svcRows = app::get('desktop')->model('users')->getList('user_id,name,disabled', ['account_type' => 'service'], 0, -1);
            foreach ((array) $svcRows as $u) {
                if (!is_array($u) || in_array($u['disabled'] ?? null, [true, 1, '1', 'true'], true)) {
                    continue;
                }
                $serviceAccounts[] = ['user_id' => (int) $u['user_id'], 'name' => (string) $u['name']];
            }
        } catch (Throwable $e) {
            $serviceAccounts = [];
        }

        // 角色列表（表单已不使用；保留变量仅为模板兼容，后续可移除）
        $roles = [];
        try {
            $roleRows = app::get('desktop')->model('roles')->getList('role_id,role_name', [], 0, -1);
            foreach ((array) $roleRows as $r) {
                if (!is_array($r)) {
                    continue;
                }
                $roles[] = ['role_id' => (int) $r['role_id'], 'role_name' => (string) $r['role_name']];
            }
        } catch (Throwable $e) {
            $roles = [];
        }

        $this->pagedata['clients'] = $rows;
        $this->pagedata['pending'] = $pending;
        $this->pagedata['pending_count'] = count((array) $pending);
        $this->pagedata['mode'] = mcpapi_auth_oauth_registration::currentMode();
        $this->pagedata['scopes'] = mcpapi_auth_oauth_scopes::SUPPORTED;
        $this->pagedata['service_accounts'] = $serviceAccounts;
        $this->pagedata['roles'] = $roles;
        $this->page('admin/client/index.html');
    }

    /**
     * 注册机密客户端（POST）。响应直接渲染一次性密钥页（不经重定向，密钥不进 URL/session）。
     */
    public function save_register()
    {
        $this->requirePermission('mcpapi.client.add');

        $kind = ($_POST['kind'] ?? '') === 'agent' ? 'agent' : 'operator';
        $name = trim((string) ($_POST['name'] ?? ''));
        // scope 不再表单勾选：默认授予全部（权限面实际由角色实时收敛，scope 仅作额外裁剪）
        $scopes = is_array($_POST['scopes'] ?? null) ? array_values(array_filter(array_map('strval', $_POST['scopes']))) : [];
        if ($scopes === []) {
            $scopes = array_keys(mcpapi_auth_oauth_scopes::SUPPORTED);
        }

        $redirectUris = [];
        if ($kind === 'operator') {
            $redirectUris = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) ($_POST['redirect_uris'] ?? '')))));
        }

        $serviceUserId = (int) ($_POST['service_user_id'] ?? 0) ?: null;
        // 服务账号统一在用户管理创建，注册表单仅绑定（不再顺带新建）
        $newSvc = null;
        if ($kind === 'agent' && $serviceUserId === null) {
            throw new InvalidArgumentException('Agent 客户端必须绑定服务账号（请先在操作员管理创建）', 422);
        }

        try {
            $result = $this->manager()->registerConfidential(
                $kind,
                $name,
                $redirectUris,
                $scopes,
                $this->operatorName(),
                $serviceUserId,
                $newSvc
            );
        } catch (InvalidArgumentException $e) {
            $this->splash('fail', 'index.php?app=mcpapi&ctl=admin_client&act=index', '注册失败：' . $e->getMessage());
            return;
        } catch (Throwable $e) {
            $this->splash('fail', 'index.php?app=mcpapi&ctl=admin_client&act=index', '注册失败：' . $e->getMessage());
            return;
        }

        // 一次性密钥交接页
        $client = is_array($result['client'] ?? null) ? $result['client'] : [];
        $svc = is_array($result['service_account'] ?? null) ? $result['service_account'] : [];
        $this->pagedata['reg_kind'] = $kind === 'agent' ? 'Agent型（client_credentials）' : '操作员型（authorization_code + PKCE）';
        $this->pagedata['reg_client_id'] = (string) ($client['client_id'] ?? '');
        $this->pagedata['reg_client_secret'] = (string) ($result['client_secret'] ?? '');
        $this->pagedata['reg_scopes'] = implode('、', (array) ($client['scopes'] ?? $scopes));
        $this->pagedata['reg_redirect_uris'] = implode(' | ', (array) ($client['redirect_uris'] ?? []));
        $this->pagedata['reg_service'] = $kind === 'agent'
            ? (($svc['login_name'] ?? '') !== '' ? (string) $svc['login_name'] : ('user#' . ($client['owner_user_id'] ?? '')))
            : '';
        $this->page('admin/client/secret.html');
    }

    public function save_approve()
    {
        $this->requirePermission('mcpapi.client.approve');
        $clientId = trim((string) ($_POST['client_id'] ?? ''));
        try {
            $this->manager()->approvePendingClient($clientId, $this->operatorName());
            $this->splash('success', 'index.php?app=mcpapi&ctl=admin_client&act=index', '已批准：' . $clientId);
        } catch (Throwable $e) {
            $this->splash('fail', 'index.php?app=mcpapi&ctl=admin_client&act=index', '批准失败：' . $e->getMessage());
        }
    }

    public function save_reject()
    {
        $this->requirePermission('mcpapi.client.approve');
        $clientId = trim((string) ($_POST['client_id'] ?? ''));
        try {
            $this->manager()->rejectPendingClient($clientId, $this->operatorName(), trim((string) ($_POST['reason'] ?? '')));
            $this->splash('success', 'index.php?app=mcpapi&ctl=admin_client&act=index', '已拒绝：' . $clientId);
        } catch (Throwable $e) {
            $this->splash('fail', 'index.php?app=mcpapi&ctl=admin_client&act=index', '拒绝失败：' . $e->getMessage());
        }
    }

    public function save_revoke()
    {
        $this->requirePermission('mcpapi.client.revoke');
        $clientId = trim((string) ($_POST['client_id'] ?? ''));
        try {
            $this->manager()->revokeById($clientId, $this->operatorName(), trim((string) ($_POST['reason'] ?? '')));
            $this->splash('success', 'index.php?app=mcpapi&ctl=admin_client&act=index', '已吊销：' . $clientId);
        } catch (Throwable $e) {
            $this->splash('fail', 'index.php?app=mcpapi&ctl=admin_client&act=index', '吊销失败：' . $e->getMessage());
        }
    }

    /**
     * 动态注册模式切换（open / approval / closed）。
     */
    public function save_regmode()
    {
        $this->requirePermission('mcpapi.client.approve');
        $mode = (string) ($_POST['mode'] ?? '');
        if (!in_array($mode, mcpapi_auth_oauth_registration::MODES, true)) {
            $this->splash('fail', 'index.php?app=mcpapi&ctl=admin_client&act=index', '非法注册模式：' . $mode);
            return;
        }
        mcpapi_auth_oauth_registration::setMode($mode);
        $this->splash('success', 'index.php?app=mcpapi&ctl=admin_client&act=index', '注册模式已切换为：' . $mode);
    }
}
