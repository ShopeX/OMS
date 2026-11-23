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
 * 补货差异单任务
 * 
 * @author wangbiao<wangbiao@shopex.cn>
 * @version $Id: Z
 */
class console_ctl_admin_replenish_diff extends desktop_controller
{
    public function index()
    {
        $base_filter = array();
        
        //action
        $actions = array();
        
        //params
        $params = array(
                'use_buildin_new_dialog' => false,
                'use_buildin_set_tag' => false,
                'use_buildin_recycle' => false,
                'use_buildin_import' => false,
                'use_buildin_export' => false,
                'use_buildin_filter' => true,
                'use_view_tab' => true,
                'actions' => $actions,
                'title' => '补货差异单',
                'base_filter' => $base_filter,
                'orderBy' => 'diff_id DESC',
        );
        
        $this->finder('console_mdl_replenish_diff', $params);
    }
}
