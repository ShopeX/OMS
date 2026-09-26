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

class desktop_site
{
    /**
     * 获取Info
     * @return mixed 返回结果
     */
    public function getInfo()
    {
        $info = app::get('desktop')->getConf('siteInfo');

        $logoUrl = '';
        if ($info['logoUrl']) {
            list($logoUrl) = explode('|', $info['logoUrl']);
            // storager 存的是相对路径，展示时拼成可访问 URL
            if ($logoUrl && !preg_match('#^https?://#i', $logoUrl)) {
                $base = defined('IMG_URL') ? IMG_URL : kernel::base_url(1);
                $logoUrl = rtrim($base, '/') . '/' . ltrim($logoUrl, '/');
            }
        }
        
        $info['logoUrl'] = $logoUrl;
        
        base_kvstore::instance('menudefine')->fetch('version', $menuVer);
        $info['menuVer'] = $menuVer?:'20240722000000';

        return $info;
    }
}