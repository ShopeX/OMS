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
 * @DateTime: 2022/12/5 10:17:17
 * @describe: 加工单
 * ============================
 */
class console_ctl_admin_wms_storeprocess extends desktop_controller {

    public function index() {
        $actions = array();
        $params = array(
            'title'=>'加工单列表',
            'use_buildin_set_tag'=>false,
            'use_buildin_filter'=>false,
            'use_buildin_export'=>false,
            'use_buildin_import'=>false,
            'use_buildin_recycle'=>false,
            'actions'=>$actions,
            'orderBy'=>'id desc',
        );
        
        $this->finder('console_mdl_wms_storeprocess', $params);
    }
}