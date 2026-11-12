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
 * Wap Module API Controller
 *
 * Provides API endpoints for the Wap (O2O Store Management) mobile interface
 */
class wap_ctl_api_index extends wap_controller
{
    /**
     * Get wap index data
     *
     * @return void
     */
    public function index(): void
    {
        $userLib = kernel::single('desktop_user');
        $op_id = $userLib->get_id();

        // Get user info
        $userInfo = kernel::single('ome_func')->getDesktopUser();

        // Get authorized store
        $is_super = kernel::single('desktop_user')->is_super();
        $branch_ids = [];
        $storeInfo = [];

        if (!$is_super) {
            $branchObj = kernel::single('o2o_store_branch');
            $branch_ids = $branchObj->getO2OBranchByUser(true);

            if (empty($branch_ids)) {
                $this->succ([
                    'userInfo' => $userInfo,
                    'count_data' => [],
                    'menu_list' => []
                ]);
                return;
            }

            $storeObj = app::get('o2o')->model('store');
            $storeList = $storeObj->getList('store_id, store_bn, name, addr, branch_id', ['branch_id' => $branch_ids], 0, 1, 'create_time desc');
            $storeInfo = $storeList[0] ?? [];
            $userInfo = array_merge((array)$userInfo, (array)$storeInfo);
        }

        // Get statistics
        $wapDeliveryLib = kernel::single('wap_delivery');
        $statistic = [];
        if ($storeInfo || $is_super) {
            $statistic = $wapDeliveryLib->getStoreStatistic($storeInfo);
        }

        // Build menu list based on permissions
        $menu_list_new = [
            'wap_order_index' => ['menuName' => '订单列表', 'linkUrl' => '', 'key' => 1],
            'wap_aftersale_returnproduct' => ['menuName' => '门店退货', 'linkUrl' => '', 'key' => 2],
            'wap_store_stock' => ['menuName' => '门店库存', 'linkUrl' => '', 'key' => 3],
        ];

        // Filter by user permissions
        $show_menu = [];
        foreach ($this->user_menu as $key => $val) {
            if (isset($menu_list_new[$val])) {
                $menu_list_new[$val]['count'] = isset($statistic[$val]) ? $statistic[$val] : 0;
                $key_id = $menu_list_new[$val]['key'];
                $show_menu[$key_id] = $menu_list_new[$val];
            }
        }

        // Sort by key
        ksort($show_menu);

        $this->succ([
            'userInfo' => $userInfo,
            'count_data' => $statistic,
            'menu_list' => $show_menu
        ]);
    }
}