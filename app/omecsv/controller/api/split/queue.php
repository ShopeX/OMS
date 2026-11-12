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

class omecsv_ctl_api_split_queue extends desktop_ctl_api_base
{
    protected static $objectName = 'omecsv_mdl_queue';

    protected static $supportedActions = ['filters', 'columns', 'list'];

    public function index(): void
    {
        $this->finderDispatch([
            'objectName'    => 'omecsv_mdl_queue',
            'finderAlias'  => 'vue',
            'title'        => '任务列表',
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            'use_buildin_recycle'    => false,
            'use_buildin_export'     => false,
            'use_buildin_import'     => false,
            'use_buildin_filter'    => true,
            'orderBy'       => 'queue_id',
            'orderType'     => 'desc',
        
            'actions' => [],
        ]);
    }
}
