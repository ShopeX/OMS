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

class dealer_ctl_admin_shop_stocksync extends inventorydepth_ctl_shop {

    public function index()
    {
        $base_filter = array('filter_sql' => '{table}node_id is not null and {table}node_id !=""', 's_type' => 1, 'delivery_mode'=>'shopyjdf');
        list($rs, $cosId) = kernel::single('organization_cos')->getCosList();
        if(!$rs) {
            die('need cos id');
        }
        $base_filter['cos_id'] = $cosId;
        $params = array(
            'title'               => '代发库存同步管理',
            'actions'             => array(),
            //'finder_cols' => 'shop_bn,name,last_store_sync_time',
            'use_buildin_recycle' => false,
            'base_filter'         => $base_filter,
        );

        $this->finder('dealer_mdl_shop_stocksync', $params);
    }
}