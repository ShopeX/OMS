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

class archive_ctl_api_log extends desktop_ctl_api_base
{
    protected static $objectName = 'archive_mdl_operation_log';
    protected static $sensitiveFields = [];

    #[RequirePermission('archive_log')]
    public function index(): void
    {
        $this->finderDispatch([
            'objectName'    => static::$objectName,
            'finderAlias'   => 'default',
            'orderBy'       => 'operate_time desc',
            'use_buildin_new_dialog' => false,
            'use_buildin_recycle'   => false,
            'use_buildin_export'    => false,
            'use_buildin_import'    => false,
        
            'actions' => [],
        ]);
    }
}
