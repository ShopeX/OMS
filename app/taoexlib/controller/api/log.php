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

use app\desktop\lib\annotation\RequirePermission;

class taoexlib_ctl_api_log extends desktop_ctl_api_base
{
    protected static $objectName = 'taoexlib_mdl_log';

    #[RequirePermission('rolescfg')]
    public function index(): void
    {
        // 全部动作（list / columns / filters / tabs / cols / detail）交由 base 标准分发：
        // - filters 由 base 依据 dbschema（filtertype / table:）构建
        // - tabs    由 _finderViews() 经 params.views 注入，count 由 tabs 处理器自动计算
        // - detail  由 desktop_finder_vue_detail 按 finderview 分发到 finder 对象的 detail_* 页
        //           （app/taoexlib/lib/finder/log.php::detail_edit，已按 vue_mode 返回结构化数据）
        $this->finderDispatch([
            'objectName' => 'taoexlib_mdl_log',
            'finderAlias' => 'taoexlib-log-vue',
            'title' => '短信日志',
            'use_buildin_filter' => true,
            'use_buildin_recycle' => true,
            'use_buildin_set_tag' => true,
            'views' => $this->_finderViews(),
            'actions' => [],
        ]);
    }

    /**
     * Vue Finder tabs（finderDispatch.views 与全选批量 view_id 解析共用）。
     * 与 legacy admin/log.php::_views() 一致：全部 / 发送成功 / 发送失败；count 由 tabs 处理器按 filter 自动算。
     *
     * @return list<array{id:string,label:string,filter:array}>
     */
    protected function _finderViews(): array
    {
        return [
            ['id' => 'all', 'label' => '全部', 'filter' => []],
            ['id' => 'success', 'label' => '发送成功', 'filter' => ['status' => 1]],
            ['id' => 'fail', 'label' => '发送失败', 'filter' => ['status' => 0]],
        ];
    }
}
