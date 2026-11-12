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
 * 操作日志 / 登录日志 Vue 控制器（SCRUM-7682）
 *
 * ctl 落点：菜单 controller='admin_operation_log' → desktop_user 对含 admin_ 的
 * menu_path 做 str_replace('admin_','api_')（app/desktop/lib/user.php:397-405），
 * 故运行时 ctl = api_operation_log，类名 ome_ctl_api_operation_log 经 `_`→`/`
 * 反解到 app/ome/controller/api/operation/log.php。
 * 两个入口方法名 = 菜单 act（desktop.xml:154 action='index' / :155 action='login_list'）。
 * 平台标准两层分派：act 选入口方法、action 选 finder 处理器（base.php:29-45 白名单），
 * 控制器内不做任何 action 路由。
 * 前端 src/api/ome/operation-log.ts:94 硬编码 ctl=api_operation_log（与派生值等价，可留）；
 * 登录日志 src/api/ome/login-list.ts:46 硬编码 ctl=api_loginlist —— 须删除、改走菜单派生（D1 定案 B）。
 *
 * 旧 HTML 控制器 app/ome/controller/admin/operation/log.php 保留作回退，不改。
 */

use app\desktop\lib\annotation\RequirePermission;

class ome_ctl_api_operation_log extends desktop_ctl_api_base
{
    /** 默认对象（操作日志入口）；登录日志入口在方法内切换为 pam_mdl_log */
    protected static $objectName = 'ome_mdl_operation_log';

    /**
     * 操作日志入口（菜单 admin_operation_log/act=index）：
     * 列表/列/筛选/cols 分发，operate_time desc，保留老页恒真过滤 log_id>0。
     */
    #[RequirePermission('operation_log')]
    public function index(): void
    {
        $this->finderDispatch([
            'objectName'             => 'ome_mdl_operation_log',
            'finderAlias'            => 'vue',
            'title'                  => '操作日志',              // 逐字对齐 admin/operation/log.php:8
            'orderBy'                => 'operate_time',          // 老页 ' operate_time desc '（:7）
            'orderType'              => 'desc',
            'base_filter'            => ['log_id|bthan' => 1],   // 老页恒真条件，必须保留（老控制器:9 注释：count 时 mysql 会用 op_id 当索引）
            'actions'                => [],
            'use_buildin_new_dialog' => false,                   // 对齐老页 :14-19
            'use_buildin_set_tag'    => false,
            'use_buildin_recycle'    => false,
            'use_buildin_export'     => false,
            'use_buildin_import'     => false,
            'use_buildin_filter'     => true,                    // 必须 true，否则筛选定义直接返回 []（context.php:515-517）
        ]);
    }

    /**
     * 登录日志入口（菜单 admin_operation_log/act=login_list）：
     * 固定只看后台管理员登录记录（老控制器 :48-69 的 base_filter event_type='shopadmin'）。
     */
    #[RequirePermission('login_log')]
    public function login_list(): void
    {
        static::$objectName = 'pam_mdl_log';                     // getModel()/getSchema() 走 pam 模型（base.php:159-179）

        $this->finderDispatch([
            'objectName'             => 'pam_mdl_log',
            'finderAlias'            => 'vue',
            'title'                  => '登录日志',              // 逐字对齐 :51
            'orderBy'                => 'event_time',            // 老页 ' event_time desc '（:50）
            'orderType'              => 'desc',
            'base_filter'            => ['event_type' => 'shopadmin'], // 固定口径，不可省（:52）
            'actions'                => [],
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            'use_buildin_recycle'    => false,
            'use_buildin_export'     => false,
            'use_buildin_import'     => false,
            'use_buildin_filter'     => true,
        ]);
    }

    /**
     * 操作日志详情：前端硬编码 act=detail&log_id=（operation-log.ts:88-99，%注意该请求不带 action 参数%）。
     * 入口名必须为 detail（否则路由不到）。
     */
    #[RequirePermission('operation_log')]
    public function detail(): void
    {
        $input = $this->getRequestInput();
        $logId = $input['log_id'] ?? ($_GET['p'][0] ?? '');
        if ($logId === '' || $logId === null) {
            $this->fail('无效操作！', 422);
            return;
        }
        $row = $this->getModel()->dump($logId);                  // 模型未重写 dump ⇒ dbeav 原生
        if (empty($row) || !is_array($row)) {
            $this->fail('日志不存在', 404);
            return;
        }
        $this->succ($row);                                       // 平铺返回，同 7662 api/log.php:134
    }
}
