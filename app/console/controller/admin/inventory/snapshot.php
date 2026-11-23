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

class console_ctl_admin_inventory_snapshot extends desktop_controller
{

    public function index()
    {
        $params = [
            'title'                  => '库存快照',
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            'use_buildin_recycle'    => false,
            'use_buildin_export'     => false,
            'use_buildin_import'     => false,
            'use_buildin_filter'     => true,
            'use_view_tab'           => true,
            'orderBy'                => 'id DESC',
            'actions'                => [
                [
                    'label'  => '同步库存',
                    'submit' => $this->url . '&act=doSync&finder_vid='.$_GET['finder_vid'],
                ],
            ],
        ];

        $this->finder('console_mdl_inventory_snapshot', $params);
    }

    public function itemIndex($invs_id = 0)
    {
        $params = [
            'title'                  => '库存快照明细',
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            'use_buildin_recycle'    => false,
            'use_buildin_export'     => true,
            'use_buildin_import'     => false,
            'use_buildin_filter'     => true,
            'use_view_tab'           => false,
            'base_filter'            => ['invs_id' => $invs_id],

        ];

        $this->finder('console_mdl_inventory_snapshot_items', $params);
    }

    /**
     * undocumented function
     *
     * @return void
     * @author
     **/
    public function doSync()
    {
        $this->begin();

        $filter = $_POST;
        $filter['status'] = '3';

        $rows = app::get('console')->model('inventory_snapshot')->getList('id',$filter);

        foreach ($rows as $row) {
            kernel::single('taskmgr_interface_connecter')->push([
                'data' => [
                    'task_type'    => 'wms_sync_inv',
                    'invs_id'      => $row['id'],
                ],
                'url'  => kernel::openapi_url('openapi.autotask', 'service'),
            ]);
        }

        $this->end(true, '已放入后台执行');
    }
}
