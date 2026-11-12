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
 * ShopEx licence
 *
 * @copyright  Copyright (c) 2005-2010 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license  http://ecos.shopex.cn/ ShopEx License
 */

use app\desktop\lib\annotation\RequirePermission;

class desktop_ctl_api_rpcnotify extends desktop_ctl_api_base
{
    protected static $objectName = 'base_mdl_rpcnotify';

    private const FINDER_ALIAS = 'desktop-rpcnotify-vue';

    private const FINDER_COLS = 'id,callback,rsp,notifytime,status,read_user,msg';

    #[RequirePermission('message_notify')]
    public function index(): void
    {
        $this->finderDispatch([
            'objectName' => static::$objectName,
            'finderAlias' => self::FINDER_ALIAS,
            'title' => app::get('desktop')->_('消息通知'),
            'finderCols' => self::FINDER_COLS,
            'use_buildin_filter' => true,
            'use_buildin_export' => false,
            'use_buildin_import' => false,
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag' => false,
            'use_buildin_recycle' => false,
            'orderBy' => 'id desc',

            'actions' => [
                [
                    'id' => 'read',
                    'label' => app::get('desktop')->_('标记已读'),
                    'submit' => 'ctl=rpcnotify&act=read',
                ],
                [
                    'id' => 'unread',
                    'label' => app::get('desktop')->_('标记未读'),
                    'submit' => 'ctl=rpcnotify&act=unread',
                ],
            ],
        ]);
    }
}
