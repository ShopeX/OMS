<?php

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
