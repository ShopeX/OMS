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

class desktop_ctl_api_system extends desktop_ctl_api_base
{
    /**
     * GET - Get site info
     * ?app=desktop&ctl=api_system&act=siteInfo
     */
    #[RequirePermission('shopsetting')]
    public function siteInfo(): void
    {
        $siteInfo = kernel::single('desktop_site')->getInfo();

        $this->succ([
            'siteInfo' => $siteInfo,
        ]);
    }

    /**
     * POST - Save site info
     * ?app=desktop&ctl=api_system&act=siteSetSave
     */
    #[RequirePermission('shopsetting')]
    public function siteSetSave(): void
    {
        $this->begin();

        $file = $_FILES;

        $siteInfo = app::get('desktop')->getConf('siteInfo');
        $siteInfo = array_merge((array)$siteInfo, (array)$_POST['siteInfo']);

        // 如果使用系统默认
        if ($siteInfo['logoSet'] == '0') {
            unset($siteInfo['logoUrl']);
            app::get('desktop')->setConf('siteInfo', $siteInfo);
            unset($file);
        }

        if ($file['logo'] && $file['logo']['tmp_name'] && !$file['logo']['error']) {
            $logoFilename = '/tmp/logo.png';
            $msg = '';

            move_uploaded_file($file['logo']['tmp_name'], $logoFilename);
            $id = kernel::single('base_storager')->save_upload($logoFilename, 'image', '', $msg);

            if (!$id) {
                $this->end(false, $msg);
            }

            $siteInfo['logoUrl'] = $id;

            app::get('desktop')->setConf('siteInfo', $siteInfo);
        }

        $this->end(true, app::get('desktop')->_('保存成功'));
    }

    /**
     * GET - Check if current user has a permission
     * ?app=desktop&ctl=api_system&act=checkPermission&permission=user_roles_add
     */
    #[RequirePermission('shopsetting')]
    public function checkPermission(): void
    {
        $input = $this->getRequestInput();
        $permission = trim((string) ($input['permission'] ?? ''));
        if ($permission === '') {
            $this->fail('permission is required');
            return;
        }

        $allowed = kernel::single('desktop_user')->has_permission($permission);
        $this->succ([
            'permission' => $permission,
            'allowed' => (bool) $allowed,
        ]);
    }
}