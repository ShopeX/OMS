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
 * 第三方应用中心（channel / 授权页）Vue JSON 契约
 *
 * legacy 页 = `channel_ctl_admin_channel::index()`（`app/channel/controller/admin/channel.php`）：
 *   `$this->finder('channel_mdl_channel', $params)`，params 逐字为
 *   title=`%s授权`(typemap[channel_type]) / use_buildin_new_dialog=false / use_buildin_set_tag=false /
 *   use_buildin_recycle=false / use_buildin_export=false / use_buildin_import=false /
 *   base_filter=`['channel_type'=>$channel_type]`；
 *   工具栏 actions = 「添加<类型>」(href act=add&p[]=<type>, dialog) + 非 sms 时「查看绑定关系」
 *   (href act=view_bindrelation&p[]=<type>, target=_blank)。
 * 行操作由 `app/channel/lib/finder/channel.php::column_opt()` 产出（**该 lib 已带 vue_mode 结构化分支**：
 *   edit / apply，node_id 或 channel_type=cloudprint 时去掉 apply；sms+shopex 追加 shopex_sms_account）。
 * 弹出页表单/写链路（SCRUM-7916）已 API 化：act=getFormData / getPlatformParams / save /
 * genPrivateKey / getSmsAccount / bindSmsAccount / unbindSmsAccount —— 语义逐字对齐 legacy
 * `channel_ctl_admin_channel` 的 add / edit / confightml / platformconfig / save / gen_private_key /
 * shopex_sms_account / shopex_sms_bind / shopex_sms_unbind（枚举取 `channel_auth_config`、
 * 接入方式取 `channel_rpc_response_bind::getBindKdMode`、sms 校验复用 `channel_sms_config`、
 * 通行证绑定复用 `taoexlib_utils`）；legacy 弹窗页入口保留不动。
 *
 * 读链路全部走框架 finderDispatch（action = list / columns / filters / cols / detail）；
 * 导出/导入/回收站不开放（与 legacy 开关一致）。
 *
 * ⚠️ 权限：本控制器 1 个 act 服务 4 个菜单（kuaidi / ticket / sms / cloudprint），
 *   `#[RequirePermission]` 的 `all` 是 **AND** 语义（`desktop_user::_getAnnotationPermissions()` +
 *   `chkground()` 逐个 `has_permission()`），套四个权限会把「只有单个渠道权限」的用户全部挡掉；
 *   而不加注解时 `permissionId()` 对 `ctl=api_channel` 取不到菜单行 ⇒ 返回 '0' ⇒ 框架对已登录会话**兜底放行**
 *   （越权）。故本控制器显式按 `channel_type` 逐菜单校验权限（放行面与 legacy 菜单表 permission 一致）。
 *
 * 路由：index.php?app=channel&ctl=api_channel&act=index&action=<list|columns|filters|cols|detail>
 *       index.php?app=channel&ctl=api_channel&act=getChannelTypes
 *       index.php?app=channel&ctl=api_channel&act=getBindRelationUrl&channel_type=<type>
 *       index.php?app=channel&ctl=api_channel&act=applyBindRelationUrl&channel_id=<id>
 */
class channel_ctl_api_channel extends desktop_ctl_api_base
{
    /** 菜单 permission 逐字沿用（app/channel/desktop.xml） */
    private const PERM_MAP = [
        'kuaidi'     => 'channel_index_kuaidi',
        'ticket'     => 'channel_index_ticket',
        'sms'        => 'channel_index_sms',
        'cloudprint' => 'channel_index_cloudprint',
    ];

    /**
     * 授权列表（Finder）
     * action=list / columns / filters / cols 由框架处理；detail 走框架（FE 传 finderview）。
     */
    public function index(): void
    {
        $channelType = $this->resolveChannelType();

        if (!$this->checkPermission($channelType)) {
            $this->fail('无权访问该授权页', 403);

            return;
        }

        $typemap = $this->typemap();
        $label   = $typemap[$channelType] ?? '';

        $this->finderDispatch([
            'objectName'             => 'channel_mdl_channel',
            'title'                  => $label === '' ? '' : sprintf('%s授权', $label),
            'use_buildin_filter'     => true,
            'use_buildin_selectrow'  => true,
            'use_buildin_recycle'    => false,
            'use_buildin_export'     => false,
            'use_buildin_import'     => false,
            'use_buildin_set_tag'    => false,
            'use_buildin_new_dialog' => false,
            'base_filter'            => $channelType === '' ? [] : ['channel_type' => $channelType],
            'actions'                => $this->toolbarActions($channelType, $label),
        ]);
    }

    /** 渠道类型枚举（添加/编辑弹窗用；与 dbschema `channel_type` 一致） */
    public function getChannelTypes(): void
    {
        if (!$this->checkPermission()) {
            $this->fail('无权访问该授权页', 403);

            return;
        }

        $this->succ(['types' => $this->typemap()]);
    }

    /**
     * 「查看绑定关系」矩阵页 URL（legacy `view_bindrelation($channel_type)`）
     * GET ?app=channel&ctl=api_channel&act=getBindRelationUrl&channel_type=<type>
     */
    public function getBindRelationUrl(): void
    {
        $channelType = $this->resolveChannelType();

        if (!$this->checkPermission($channelType)) {
            $this->fail('无权访问该授权页', 403);

            return;
        }

        $params           = kernel::single('channel_rpc_response_bind')->get_params(0, $channelType, '');
        $params['source'] = 'accept';

        $this->succ([
            'url'    => MATRIX_RELATION_URL . '?' . http_build_query($params),
            'params' => $params,
        ]);
    }

    /**
     * 「申请绑定」矩阵页 URL（legacy `apply_bindrelation($channel_id)`）
     * GET ?app=channel&ctl=api_channel&act=applyBindRelationUrl&channel_id=<id>
     */
    public function applyBindRelationUrl(): void
    {
        $channelId = (int) ($_GET['channel_id'] ?? 0);

        if (!$this->checkPermission((string) ($_GET['channel_type'] ?? ''))) {
            $this->fail('无权访问该授权页', 403);

            return;
        }

        $params           = kernel::single('channel_rpc_response_bind')->get_params($channelId);
        $params['source'] = 'apply';

        $this->succ([
            'url'    => MATRIX_RELATION_URL . '?' . http_build_query($params),
            'params' => $params,
        ]);
    }

    /**
     * channel_type 来源：GET 优先（菜单 params 展开到 query），POST JSON body 兜底
     * （FE `fetchChannelList()` 把 channel_type 放在 JSON body 里）。
     */
    private function resolveChannelType(): string
    {
        $type = $_GET['channel_type'] ?? $_POST['channel_type'] ?? '';

        if ((!is_string($type) || trim($type) === '')
            && strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST'
        ) {
            $body = json_decode((string) file_get_contents('php://input'), true);
            if (is_array($body) && isset($body['channel_type']) && is_string($body['channel_type'])) {
                $type = $body['channel_type'];
            }
        }

        return is_string($type) ? trim($type) : '';
    }

    /** 工具栏动作（legacy `index()` 的 `$params['actions']` 逐条对齐） */
    private function toolbarActions(string $channelType, string $label): array
    {
        if ($channelType === '') {
            return [];
        }

        $actions = [
            [
                'id'     => 'add',
                'label'  => sprintf('添加%s', $label),
                'action' => 'add',
                'href'   => sprintf('index.php?app=channel&ctl=admin_channel&act=add&p[]=%s', $channelType),
                'target' => ['type' => 'dialog', 'width' => '700px', 'height' => '400px'],
                'type'   => 'primary',
            ],
        ];

        if ($channelType !== 'sms') {
            $actions[] = [
                'id'     => 'view-bind-relation',
                'label'  => '查看绑定关系',
                'href'   => sprintf('index.php?app=channel&ctl=admin_channel&act=view_bindrelation&p[]=%s', $channelType),
                'target' => ['type' => 'blank'],
                'type'   => 'default',
            ];
        }

        return $actions;
    }

    /** dbschema `channel_type` 枚举（legacy 同源：`app::get('channel')->model('channel')->_columns()`） */
    private function typemap(): array
    {
        $schema = app::get('channel')->model('channel')->_columns();
        $types  = $schema['channel_type']['type'] ?? [];

        return is_array($types) ? $types : [];
    }

    /**
     * 按 channel_type 逐菜单校验权限；未带 channel_type 时要求「拥有任一渠道权限」。
     * 与 legacy 一致：菜单表 permission 决定放行面。
     */
    private function checkPermission(string $channelType = ''): bool
    {
        $user = kernel::single('desktop_user');

        if (method_exists($user, 'is_super') && $user->is_super()) {
            return true;
        }

        if (isset(self::PERM_MAP[$channelType])) {
            return (bool) $user->has_permission(self::PERM_MAP[$channelType]);
        }

        foreach (self::PERM_MAP as $permission) {
            if ($user->has_permission($permission)) {
                return true;
            }
        }

        return false;
    }

    /** 节点类型（平台）枚举（legacy `admin_channel::$_node_type` 逐字；other 兜底同 legacy add()） */
    private function nodeTypeMap(string $channelType): array
    {
        $map = [
            'kuaidi'     => ['kdn' => '快递鸟', 'sf' => '顺丰', 'other' => '其他'],
            'cloudprint' => ['yilianyun' => '易联云'],
            'ticket'     => ['feisuo' => '飞梭'],
            'sms'        => ['shopex' => '商派短信', 'soap' => 'WSDL短信'],
        ];

        return $map[$channelType] ?? ['other' => '其他'];
    }

    /** sms 走 adapter 档案表，其余类型 config 反序列化自 channel 主表（legacy __getChannelConfig 逐字） */
    private function channelConfig(array $channel): array
    {
        $raw = '';
        if ($channel['channel_type'] === 'sms') {
            $adapter = app::get('channel')->model('adapter')->dump(['channel_id' => $channel['channel_id']], 'config');
            $raw = $adapter['config'] ?? '';
        } else {
            $raw = (string) ($channel['config'] ?? '');
        }

        $config = @unserialize($raw);

        return is_array($config) ? $config : [];
    }

    /**
     * 添加/编辑弹窗表单数据（legacy add($channel_type) + edit($channel_id) 合并）：
     * channel_types / node_types / adapter_list + 编辑态 channel 回显（config 一并解出）。
     * GET ?act=getFormData&channel_type=<type>[&channel_id=<id>]
     */
    public function getFormData(): void
    {
        $channelType = (string) ($_GET['channel_type'] ?? '');

        if (!$this->checkPermission($channelType)) {
            $this->fail('无权访问该授权页', 403);

            return;
        }

        if ($channelType === '' || !isset(self::PERM_MAP[$channelType])) {
            $this->fail('channel_type 非法', 400);

            return;
        }

        $data = [
            'channel_id'   => 0,
            'channel_type' => $channelType,
            'channel_types'=> $this->typemap(),
            'node_types'   => $this->nodeTypeMap($channelType),
            'adapter_list' => kernel::single('channel_auth_config')->getAdapterList($channelType),
        ];

        $channelId = (int) ($_GET['channel_id'] ?? 0);
        if ($channelId > 0) {
            $channel = app::get('channel')->model('channel')->dump($channelId, '*');
            if (!$channel || ($channel['channel_type'] ?? '') !== $channelType) {
                $this->fail('授权记录不存在', 404);

                return;
            }

            $data['channel_id'] = (int) $channelId;
            $data['channel']    = [
                'channel_id'      => (int) $channel['channel_id'],
                'channel_bn'      => (string) ($channel['channel_bn'] ?? ''),
                'channel_name'    => (string) ($channel['channel_name'] ?? ''),
                'memo'            => (string) ($channel['memo'] ?? ''),
                'channel_adapter' => (string) ($channel['channel_adapter'] ?? ''),
                'node_type'       => (string) ($channel['node_type'] ?? ''),
                'node_id'         => (string) ($channel['node_id'] ?? ''),
                'disabled'        => (string) ($channel['disabled'] ?? 'false'),
                'config'          => $this->channelConfig((array) $channel),
            ];
        }

        $this->succ($data);
    }

    /**
     * 动态配置参数（legacy platformconfig($channel_type,$adapter,$node_type,$channel_id)）：
     * params=字段声明、channel_modes=接入方式枚举（kuaidi）、编辑态回显 config 现值。
     * GET ?act=getPlatformParams&channel_type=&adapter=&node_type=[&channel_id=]
     */
    public function getPlatformParams(): void
    {
        $channelType = (string) ($_GET['channel_type'] ?? '');
        $adapter     = (string) ($_GET['adapter'] ?? '');
        $nodeType    = (string) ($_GET['node_type'] ?? '');

        if (!$this->checkPermission($channelType)) {
            $this->fail('无权访问该授权页', 403);

            return;
        }

        if (!$channelType || !$adapter || !$nodeType) {
            $this->fail('channel_type/adapter/node_type 必填', 400);

            return;
        }

        /** @var channel_auth_config $authConfig */
        $authConfig = kernel::single('channel_auth_config');

        $data = [
            'params'         => $authConfig->getPlatformParam($channelType, $adapter, $nodeType),
            'platform_label' => '',
            'platform_list'  => $authConfig->getPlatformList($channelType, $adapter),
            'channel_modes'  => kernel::single('channel_rpc_response_bind')->getBindKdMode(null, $nodeType),
            'shipper'        => '',
            'node_id'        => '',
            'disabled'       => 'false',
            'config'         => [],
        ];

        // platform_label（legacy config 视图标题同源）
        $configAll = $authConfig->getConfig();
        $data['platform_label'] = (string) ($configAll[$channelType][$adapter]['platform'][$nodeType]['label'] ?? '');

        $channelId = (int) ($_GET['channel_id'] ?? 0);
        if ($channelId > 0) {
            $channel = app::get('channel')->model('channel')->db_dump($channelId, '*');
            if ($channel && $nodeType === ($channel['node_type'] ?? '')) {
                // 平台一致才回显（legacy platformconfig 判断逐字）
                $data['shipper']  = (string) ($channel['shipper'] ?? '');
                $data['node_id']  = (string) ($channel['node_id'] ?? '');
                $data['disabled'] = (string) ($channel['disabled'] ?? 'false');
                $data['config']   = $this->channelConfig((array) $channel);
            }
        }

        $this->succ($data);
    }

    /**
     * 保存（新增/编辑）—— legacy `admin_channel::save()` 逐字语义：
     * kuaidi channel_mode→shipper；config 走 `channel_sms_config::formatConfig` 同源校验
     * （__formatConfig 即按 channel_type 找 channel_<type>_config）；sms 落 adapter 档案表。
     * POST ?act=save  body: {"channel":{...},"config":{...}}
     */
    public function save(): void
    {
        $body    = $this->getJsonBody();
        $channel = is_array($body['channel'] ?? null) ? $body['channel'] : [];
        $config  = is_array($body['config'] ?? null) ? $body['config'] : [];

        $channelType = (string) ($channel['channel_type'] ?? ($_GET['channel_type'] ?? ''));
        if (!$this->checkPermission($channelType)) {
            $this->fail('无权访问该授权页', 403);

            return;
        }

        if (!$channelType || trim((string) ($channel['channel_bn'] ?? '')) === ''
            || trim((string) ($channel['channel_name'] ?? '')) === '') {
            $this->fail('应用编号/应用名称必填', 400);

            return;
        }

        $channelMdl = app::get('channel')->model('channel');

        // [第三方快递] 使用 shipper 字段保存对接方式类型（legacy save() 逐字）
        if (!empty($channel['channel_mode']) && $channelType === 'kuaidi') {
            $channel['shipper'] = $channel['channel_mode'];
        }

        // 如果有额外参数,则检查（legacy __formatConfig：按 channel_type 找 channel_<type>_config::formatConfig）
        // 注意：kernel autoload 对不存在的 lib 会抛 "Don't find lib file" 致命错（cloudprint/kuaidi/ticket
        // 无 <type>_config 类），class_exists 会触发 autoload，故先判 lib 文件存在性。
        if (!empty($config)) {
            $channelConfigClassName = sprintf('channel_%s_config', $channelType);
            $channelConfigLibFile = app::get('channel')->app_dir . '/lib/' . $channelType . '/config.php';
            if (file_exists($channelConfigLibFile) && class_exists($channelConfigClassName)) {
                try {
                    $channel = kernel::single($channelConfigClassName)->formatConfig($config, $channel);
                } catch (Exception $e) {
                    // legacy 同款吞异常
                }
            } else {
                // 无 <type>_config 类的类型（kuaidi/cloudprint 等）：动态参数仍需持久化，
                // 序列化进 channel.config 列（编辑回显 getFormData -> channel.config -> FE 回填）。
                // legacy 此场景实际丢失 config（__formatConfig 无类时原样返回），属 legacy 不完整行为，这里修正为落库。
                if (!empty($channel['channel_adapter'])) {
                    $channel['config'] = serialize($config);
                }
            }
        }

        $rs = (bool) $channelMdl->save($channel);
        if ($rs && $channelType === 'sms') {
            if (!empty($config)) {
                $rs = (bool) kernel::single('channel_sms_config')->saveAdapterConfig($config, $channel);
            } elseif (($channel['node_type'] ?? '') === 'shopex') {
                base_kvstore::instance('taoexlib')->fetch('account', $account);
                $account = @unserialize((string) $account);
                if ($account) {
                    $rs = (bool) kernel::single('channel_sms_config')->saveAdapterConfig($account, $channel);
                }
            }
        }

        if (!$rs) {
            $this->fail('保存失败', 400);

            return;
        }

        // model save 按引用回填自增 channel_id（insert() 内 $data[$this->idColumn] = $insert_id）
        $this->succ(['channel_id' => (int) ($channel['channel_id'] ?? 0), 'message' => '保存成功']);
    }

    /** 生成快递私钥（legacy gen_private_key 逐字：32 位，含大小写/数字/符号） */
    public function genPrivateKey(): void
    {
        if (!$this->checkPermission()) {
            $this->fail('无权访问该授权页', 403);

            return;
        }

        $lowercaseLetters = 'abcdefghijklmnopqrstuvwxyz';
        $uppercaseLetters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $numbers          = '0123456789';
        $symbols          = '!@#$%&';

        $randomString  = '';
        $randomString .= $lowercaseLetters[random_int(0, strlen($lowercaseLetters) - 1)];
        $randomString .= $uppercaseLetters[random_int(0, strlen($uppercaseLetters) - 1)];
        $randomString .= $numbers[random_int(0, strlen($numbers) - 1)];
        $randomString .= $symbols[random_int(0, strlen($symbols) - 1)];

        $allCharacters = $lowercaseLetters . $uppercaseLetters . $numbers . $symbols;
        for ($i = 0; $i < 32 - 4; $i++) {
            $randomString .= $allCharacters[random_int(0, strlen($allCharacters) - 1)];
        }

        $this->succ(['key' => str_shuffle($randomString)]);
    }

    /**
     * sms+shopex 账号信息/绑定态（legacy shopex_sms_account）：
     * 绑定态返回通行证信息 + 余量 + 充值页 frameurl；未绑定返回 reg_email 预填。
     * GET ?act=getSmsAccount&channel_id=<id>
     */
    public function getSmsAccount(): void
    {
        $channelId = (int) ($_GET['channel_id'] ?? 0);

        if (!$this->checkPermission('sms')) {
            $this->fail('无权访问该授权页', 403);

            return;
        }

        if (!$channelId) {
            $this->fail('channel_id 必填', 400);

            return;
        }

        $adapter = app::get('channel')->model('adapter')->dump(['channel_id' => $channelId], 'config');
        $account = [];
        if ($adapter && $adapter['config']) {
            $account = @unserialize((string) $adapter['config']);
        }

        if (!is_array($account) || !$account) {
            $this->succ(['bound' => false, 'reg_email' => (string) taoexlib_utils::regEmail()]);

            return;
        }

        $info = (array) taoexlib_utils::get_user_info($account);
        $this->succ([
            'bound'         => true,
            'entid'         => (string) ($account['entid'] ?? ''),
            'email'         => (string) ($account['email'] ?? ''),
            'status_text'   => (($info['res'] ?? '') === 'fail')
                ? (string) ($info['info'] ?? '未知')
                : ((($info['info']->account_info->active ?? 0) == 1) ? '正常' : '未激活'),
            'month_residual'=> isset($info['info']->month_residual) ? (int) $info['info']->month_residual : null,
            'all_residual'  => isset($info['info']->all_residual) ? (int) $info['info']->all_residual : null,
            'warningnumber' => (int) app::get('taoexlib')->getConf('taoexlib.message.warningnumber'),
            'frameurl'      => taoexlib_utils::get_book_url($account),
        ]);
    }

    /**
     * sms 通行证绑定（legacy shopex_sms_bind 逐字语义：taoexlib_utils::login 成功后
     * kvstore 存 account + channel_sms_config::saveAdapterConfig 落 adapter 档案表）。
     * POST ?act=bindSmsAccount  body: {"channel_id":N,"bindemail":"...","bindpassword":"..."}
     */
    public function bindSmsAccount(): void
    {
        $body = $this->getJsonBody();

        if (!$this->checkPermission('sms')) {
            $this->fail('无权访问该授权页', 403);

            return;
        }

        $channelId    = (int) ($body['channel_id'] ?? 0);
        $bindemail    = trim((string) ($body['bindemail'] ?? ''));
        $bindpassword = trim((string) ($body['bindpassword'] ?? ''));

        if (!$channelId) {
            $this->fail('channel_id 必填', 400);

            return;
        }
        if ($bindemail === '') {
            $this->fail('email不能为空！', 400);

            return;
        }
        if ($bindpassword === '') {
            $this->fail('密码不能为空！', 400);

            return;
        }

        $result = taoexlib_utils::login([
            'identifier' => $bindemail,
            'password'   => $bindpassword,
        ]);

        if ((($result->res ?? '') === 'succ')) {
            $data = [
                'entid'    => $result->info->entid,
                'password' => $bindpassword,
                'email'    => $result->info->email,
                'status'   => 1,
            ];
            base_kvstore::instance('taoexlib')->store('account', serialize($data));

            $channel = app::get('channel')->model('channel')->dump($channelId, '*');
            kernel::single('channel_sms_config')->saveAdapterConfig($data, $channel);
            $this->succ(['message' => 'succ']);

            return;
        }

        $this->fail('帐号或密码错误！', 400);
    }

    /**
     * sms 通行证解绑（legacy shopex_sms_unbind 逐字语义：删 adapter 档案 + 清 kvstore account）。
     * POST ?act=unbindSmsAccount  body: {"channel_id":N}
     */
    public function unbindSmsAccount(): void
    {
        $body = $this->getJsonBody();

        if (!$this->checkPermission('sms')) {
            $this->fail('无权访问该授权页', 403);

            return;
        }

        $channelId = (int) ($body['channel_id'] ?? 0);
        if (!$channelId) {
            $this->fail('channel_id 必填', 400);

            return;
        }

        app::get('channel')->model('adapter')->delete(['channel_id' => $channelId]);
        base_kvstore::instance('taoexlib')->store('account', serialize([]));
        $this->succ(['message' => '操作成功！']);
    }
}
