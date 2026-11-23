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

class console_ctl_admin_interface_iostocksearchs extends desktop_controller{
    var $name = "库存异动查询";
    var $workground = "interface_iostocksearchs_center";


    public function index()
    {
        $iostocksearchs = kernel::single('console_finder_interface_iostocksearchs');
       
        //$user_branch = kernel::single("ome_userbranch");
        //$branch_id= $user_branch->get_user_branch_id();
        if($branch_id)$base_filter['branch_id'] = $branch_id;
        $iostocksearchs->set_extra_view(array('eccommon' => 'analysis/extra_view.html'));
        //$_POST['store_name'] =  $branch_id;
        $iostocksearchs->set_params($_POST)->display();
    }
}
?>
