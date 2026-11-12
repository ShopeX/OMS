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

declare(strict_types=1);

use app\desktop\lib\annotation\RequirePermission;

/**
 * 唯品会JIT售后单（前后端分离 api）
 *
 * 对齐 legacy：app/billcenter/controller/admin/aftersales.php
 *   $this->finder('billcenter_mdl_aftersales', [
 *       'title' => '售后单',
 *       'use_buildin_recycle' => false,   // 无删除入口
 *       'use_buildin_filter'  => true,
 *       'orderBy'             => 'id desc',
 *   ]);
 * legacy 无「新建」入口、无导出/导入 ⇒ 本控制器不提供写动作。
 *
 * 读动作（list/columns/filters/cols/detail/...）全部走基类 finderDispatch。
 * legacy finder 的函数列 detail_items（'明细信息'）已按框架口径在 finder 内补 vue_mode 结构化返回，
 * FE 通过 action=detail&finderview=items 行内展开消费 ⇒ 本控制器不再提供页内自定义动作。
 */
class billcenter_ctl_api_aftersales extends desktop_ctl_api_base
{
    protected static $objectName = 'billcenter_mdl_aftersales';

    /**
     * Finder 读动作入口
     * ?app=billcenter&ctl=api_aftersales&act=index&action=list|columns|filters|cols|detail|favstar
     */
    #[RequirePermission('billcenter_aftersales')]
    public function index(): void
    {
        $action = $_GET['action'] ?? 'list';

        if (in_array($action, static::$finderActions, true)) {
            $this->finderDispatch([
                'objectName'                => 'billcenter_mdl_aftersales',
                // 与 legacy admin/aftersales.php 逐字对齐
                'use_buildin_recycle'       => false,
                'use_buildin_filter'        => true,
                'orderBy'                   => 'id desc',
                // legacy index() 未下发任何工具栏按钮
                'actions'                   => [],
            ]);
            return;
        }

        $this->fail('Invalid finder action: ' . $action);
    }

}
