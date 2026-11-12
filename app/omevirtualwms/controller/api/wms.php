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
 * WITHOUT WARRANTIES OF ANY CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

use app\desktop\lib\annotation\RequirePermission;

/*
 * legacy 入口 `controller/admin/wms.php` 在文件顶部为 finder/model 定义了以下常量；
 * `controller/api/wms.php` 是新的进程入口，须在同一语义下补齐（guarded，避免与其他入口重复定义告警）：
 *   - WAIT_TIME：model/stockin.php::count()/getlist() 计算“队列失败重展”时间窗
 *   - TIP_INFO ：finder/{stockin,stockout,delivery,reship,stockdump}.php::column_msg() 备注文案
 */
if (!defined('WAIT_TIME')) {
    define('WAIT_TIME', 5); // finder 中的数据多长时间重复展示(队列失败的情况)，单位 分
}
if (!defined('TIP_INFO')) {
    define('TIP_INFO', '部分回传'); // 再次展示时的提示信息
}

/**
 * 虚拟仓 callback 配置（omevirtualwms / 模拟仓储 → 配置 → callback 配置）Vue JSON 契约
 *
 * legacy 页 `app/omevirtualwms/view/callback_config.html` 是**配置/设置表单页**
 * （非 finder，无表格/分页/工具栏）：CALLBACK模拟 radio + 异步/同步接口状态 select
 * + 单据状态 select + 错误消息文本。
 *
 * 读写链路与 legacy 共用 `omevirtualwms_capability_callback`（capability-backed，单一实现）。
 *
 * 路由：index.php?app=omevirtualwms&ctl=api_wms&act=<act>
 * （菜单 menu_path = app=omevirtualwms&ctl=admin_wms&act=callback_config
 *   ⇒ desktop_user 派生 vue_menu_path = ctl=api_wms&act=callback_config）
 */
class omevirtualwms_ctl_api_wms extends desktop_ctl_api_base
{
    private omevirtualwms_capability_callback $capability;

    private omevirtualwms_capability_simulate $simulate;

    public function __construct($app)
    {
        parent::__construct($app);
        $this->capability = kernel::single('omevirtualwms_capability_callback');
        $this->simulate   = kernel::single('omevirtualwms_capability_simulate');
    }

    /**
     * callback 配置 - 读取
     * GET ?app=omevirtualwms&ctl=api_wms&act=callback_config
     */
    #[RequirePermission('ocswms')]
    public function callback_config(): void
    {
        $this->succ($this->capability->load());
    }

    /**
     * callback 配置 - 保存（独立 public act，⛔ 不在 index() 里手搓 action 分发）
     * POST ?app=omevirtualwms&ctl=api_wms&act=save
     *      {"callback": {"status":"2","async":{"rsp":"running"},"sync":{"rsp":"succ"},"res":"","err_msg":""}}
     */
    #[RequirePermission('ocswms')]
    public function save(): void
    {
        $input    = $this->getJsonBody();
        $callback = $input['callback'] ?? [];

        if (!is_array($callback) || empty($callback)) {
            $this->fail('参数错误：缺少配置数据');

            return;
        }

        try {
            $saved = $this->capability->save($callback);
        } catch (InvalidArgumentException $exception) {
            $this->fail($exception->getMessage());

            return;
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage() !== '' ? $exception->getMessage() : '保存失败');

            return;
        }

        // 前后端分离契约：直接返回 JSON（勿用 legacy begin()/end()，其 redirect 会让 SPA 跳去 legacy 页）
        $this->succ(['message' => '保存成功', 'callback' => $saved]);
    }

    /**
     * 回传单列表 - 7 类单据统一入口（legacy `admin_wms::show2($flag)` 的 Vue JSON 契约）
     *
     * legacy 源：app/omevirtualwms/controller/admin/wms.php::show2()
     *  - 标题表 / base_filter['io_type'] = $flag / use_buildin_* 全 false / finder('omevirtualwms_mdl_'.$flag, $params)
     *  - 本轮仅迁读链路；写链路 legacy act=doSubmit 不迁，行操作仍回 legacy getinfo 表单页
     *
     * 路由：index.php?app=omevirtualwms&ctl=api_wms&act=show2&flag=<flag>
     * （菜单 menu_path = app=omevirtualwms&ctl=admin_wms&act=show2 + addon.url_params.flag
     *   ⇒ desktop_user 派生 vue_menu_path = app=omevirtualwms&ctl=api_wms&act=show2&flag=<flag>&finder_vid=...）
     */
    private const SHOW2_TITLES = [
        'stockin'      => '入库单回传',
        'stockout'     => '出库单回传',
        'delivery'     => '发货单回传',
        'reship'       => '退货单回传',
        'stockdump'    => '转储单回传',
        'vopstockout'  => '唯品会出库单回传',
        'storeprocess' => '加工单回传',
    ];

    /**
     * 回传单列表 - 读取（flag 白名单分派 7 个 finder）
     * GET/POST ?app=omevirtualwms&ctl=api_wms&act=show2&flag=stockin&action=list|columns|filters|tabs
     */
    #[RequirePermission('ocswms')]
    public function show2(): void
    {
        $flag = $this->resolveShow2Flag();

        if ($flag === '') {
            $this->fail('参数错误：flag 缺失或非法（允许值：stockin、stockout、delivery、reship、stockdump、vopstockout、storeprocess）');

            return;
        }

        $this->finderDispatch([
            'objectName'            => 'omevirtualwms_mdl_' . $flag,
            'title'                 => app::get('omevirtualwms')->_(self::SHOW2_TITLES[$flag]),
            'base_filter'           => ['io_type' => $flag],
            'use_buildin_recycle'   => false,
            'use_buildin_filter'    => false,
            'use_buildin_selectrow' => false,
            'use_buildin_export'    => false,
        ]);
    }

    /**
     * 与 show2() 同一实现；菜单派生的 vue_menu_path 使用 act=show2，此处提供 act=index 别名便于直连调试。
     */
    #[RequirePermission('ocswms')]
    public function index(): void
    {
        $this->show2();
    }

    /**
     * 「开始模拟」表单 - 读取（legacy `admin_wms::getinfo` 的 Vue JSON 契约）
     *
     * legacy 源：controller/admin/wms.php::getinfo():316-392 → _getinfo():394-612
     *   GET bn=<单号>；`$type` 由单号首字符/参数路由（capability::resolveType()）
     *
     * 路由：index.php?app=omevirtualwms&ctl=api_wms&act=simulate&flag=stockin&bn=...
     * 该页由列表页行操作（开始模拟）以抽屉打开，非独立菜单项。
     */
    #[RequirePermission('ocswms')]
    public function simulate(): void
    {
        $flag = $this->resolveShow2Flag();

        if ($flag === '') {
            $this->fail('参数错误：flag 缺失或非法（允许值：stockin、stockout、delivery、reship、stockdump、vopstockout、storeprocess）');

            return;
        }

        // 单号路由参数与 legacy 行操作 URL 逐字对齐：
        //   stockin  → p[2]=type_id（=1 走采购入库，否则按单号首字符路由）
        //   stockout → i_type=purchase_return（走采购退货出库）
        $params = [
            'bn'      => isset($_GET['bn']) && is_scalar($_GET['bn']) ? (string) $_GET['bn'] : '',
            'type'    => isset($_GET['type']) && is_scalar($_GET['type']) ? (string) $_GET['type'] : '',
            'type_id' => isset($_GET['type_id']) && is_scalar($_GET['type_id']) ? (string) $_GET['type_id'] : '',
            'i_type'  => isset($_GET['i_type']) && is_scalar($_GET['i_type']) ? (string) $_GET['i_type'] : '',
            'status'  => isset($_GET['status']) && is_scalar($_GET['status']) ? (string) $_GET['status'] : '',
        ];

        $result = $this->simulate->info($flag, $params);

        if (empty($result['ok'])) {
            $this->fail(isset($result['msg']) ? $result['msg'] : '读取失败');

            return;
        }

        $this->succ($result['data']);
    }

    /**
     * 「开始模拟」表单 - 提交（legacy `admin_wms::doSubmit` 的 Vue JSON 契约）
     *
     * legacy 源：controller/admin/wms.php::doSubmit():234-313
     *   → _return_params():632-915 → _return_method():947-982 → _assemble()/_gen_sign():984-1008
     *
     * POST ?app=omevirtualwms&ctl=api_wms&act=submit
     *   {"flag":"stockin","params":{"stockin_bn":"...","warehouse":"...","node_id":"...","rows":[...]}}
     *
     * 有意偏离 legacy 一处：明细为空时 legacy `err()` 直接输出 HTML 片段并 exit（wms.php:917-928），
     * 破坏前后端分离的 JSON 协议；此处改为结构化 fail('请添加商品！')，前端提示语义一致。
     */
    #[RequirePermission('ocswms')]
    public function submit(): void
    {
        $input  = $this->getJsonBody();
        $flag   = isset($input['flag']) && is_scalar($input['flag']) ? (string) $input['flag'] : '';
        $params = isset($input['params']) && is_array($input['params']) ? $input['params'] : [];

        if (!array_key_exists($flag, self::SHOW2_TITLES)) {
            $this->fail('参数错误：flag 缺失或非法（允许值：stockin、stockout、delivery、reship、stockdump、vopstockout、storeprocess）');

            return;
        }

        $result = $this->simulate->submit($flag, $params);

        if (empty($result['ok'])) {
            $this->fail(isset($result['msg']) ? $result['msg'] : '提交失败');

            return;
        }

        $this->succ([
            'message' => isset($result['msg']) ? $result['msg'] : '提交成功',
            'rsp'     => isset($result['data']) ? $result['data'] : [],
        ]);
    }

    /**
     * flag 解析 + 白名单校验（非法/缺失一律返回空串，由调用方 fail 成结构化 JSON，避免未知 objectName 触发 500）
     */
    private function resolveShow2Flag(): string
    {
        $flag = '';

        if (isset($_GET['flag']) && is_scalar($_GET['flag'])) {
            $flag = (string) $_GET['flag'];
        }

        if ($flag === '' && isset($_GET['p'][0]) && is_scalar($_GET['p'][0])) {
            $flag = (string) $_GET['p'][0];
        }

        $flag = trim($flag);

        return array_key_exists($flag, self::SHOW2_TITLES) ? $flag : '';
    }
}
