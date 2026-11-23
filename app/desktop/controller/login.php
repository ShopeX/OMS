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
 * ============================
 * @Author:   yaokangming
 * @Version:  1.0 
 * @DateTime: 2021/1/20 14:31:31
 * @describe: 控制器
 * ============================
 */
class desktop_ctl_login extends desktop_controller {

    public function index() {
        $actions = array();
        $params = array(
                'title'=>'登录日志',
                'use_buildin_set_tag'=>false,
                'use_buildin_filter'=>false,
                'use_buildin_export'=>false,
                'use_buildin_import'=>false,
                'use_buildin_recycle'=>false,
                'actions'=>$actions,
                'orderBy'=>'event_id desc',
        );
        
        $this->finder('desktop_mdl_login', $params);
    }

    /**
     * 获取账号信息
     *
     * @return void
     **/
    public function getUser()
    {
        $data = [
            'login_name' => kernel::single('base_view_helper')->modifier_cut($this->user->get_login_name(),'-1',strlen($this->user->get_login_name()) > 11 ?'****':'**',false,true),
            'user_mobile' => $this->user->get_mobile(),
            'user_name' => kernel::single('base_view_helper')->modifier_cut($this->user->get_name(),'-1',strlen($this->user->get_name()) > 11 ?'****':'**',false,true),
            'user_status' => $this->user->get_status(),
        ];

        $this->returnJson($data);
    }
}