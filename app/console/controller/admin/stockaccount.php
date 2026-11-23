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

class console_ctl_admin_stockaccount extends desktop_controller{
    var $name = "库存对账查询";
    var $workground = "console_center";
    public function index(){
        $account = kernel::single('console_finder_stockaccount');
        $account->set_extra_view(array('console' => 'admin/analysis/account_items_time_header.html'));
        if(empty($_POST['time_from'])){
            $_POST['time_from'] = $_POST['time_to'] = date('Y-m-d');
        }

        $account->set_params($_POST)->display();
    }
}
?>
