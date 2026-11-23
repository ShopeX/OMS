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

class desktop_ctl_api_navigate extends desktop_ctl_api_base
{
    public $workground = '';
    protected $loginExempt = [];

    public function getMenus()
    {
        $userId = $this->getCurrentUserId();
        if (!$userId) {
            return $this->fail('未登录或登录已过期', 401);
        }

        $desktop_user = kernel::single('desktop_user');
        $desktop_user->setVirtualLogin($userId);
        $oldMenus = $desktop_user->get_work_menu();

        $menus = $oldMenus['workground'];
        foreach ($menus as $key => $val) {
            unset($menus[$key]['app_id'], $menus[$key]['menu_path'], $menus[$key]['workground'], $menus[$key]['menu_group'], $menus[$key]['target']);
            $tmpSubMenu = $oldMenus['menu'][$key] ?? [];
            $secondLevelMenus = [];
            foreach ($tmpSubMenu as $tmpKey => $tmpVal) {
                $secondLevelMenus[] = [
                    'menu_id'    => current($tmpVal)['en'],
                    'menu_title' => $tmpKey,
                    'menu_type'  => '',
                    'submenus'   => $tmpVal,  // 包含 permission/vue_route_path/vue_menu_path
                ];
            }
            if (empty($secondLevelMenus)) {
                unset($menus[$key]);
                continue;
            }
            $menus[$key]['menu_id']  = $key;
            $menus[$key]['submenus'] = $secondLevelMenus;
        }
        $menus = array_values($menus);

        $this->succ(['menus' => $menus]);
    }
}
