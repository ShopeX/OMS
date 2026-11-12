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

class desktop_ctl_api_tags extends desktop_ctl_api_base
{
    protected static $objectName = 'desktop_mdl_tag';

    protected static $supportedActions = ['filters', 'columns', 'list', 'detail'];

    public function index(): void
    {
        $tagType = $_GET['type'] ?? 'item';
        $appId = $_GET['app_id'] ?? '';

        $baseFilter = ['tag_type' => $tagType];
        if ($appId) {
            $baseFilter['app_id'] = $appId;
        }

        $this->finderDispatch([
            'objectName'    => 'desktop_mdl_tag',
            'finderAlias'  => 'vue',
            'title'        => '标签管理',
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            'use_buildin_recycle'    => false,
            'use_buildin_export'     => false,
            'use_buildin_import'     => false,
            'base_filter'   => $baseFilter,
            'orderBy'      => 'tag_id',
            'orderType'    => 'desc',
        
            'actions' => [],
        ]);
    }

    public function detail(): void
    {
        $id = intval($_GET['id'] ?? 0);
        if (!$id) {
            $this->fail('标签ID不能为空');
            return;
        }

        $model = app::get('desktop')->model('tag');
        $detail = $model->dump($id);
        if (!$detail) {
            $this->fail('标签不存在');
            return;
        }

        $this->succ(['data' => $detail]);
    }
}
