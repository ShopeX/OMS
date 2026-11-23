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
 * @author ykm 2015-11-17
 * @describe 特殊订单条件
 */

class brush_mdl_farm extends dbeav_model{

    public function modifier_shop_ids($col){
        $arrShopName = app::get('ome')->model('shop')->getList('name', array('shop_id|in' => explode(',', $col)));
        $strShopName = '';
        foreach($arrShopName as $val){
            $strShopName .= $val['name'] . ',';
        }
        return trim($strShopName, ',');
    }

    public function modifier_mark_type($col){
        $arrMarkType = kernel::single('ome_order_func')->order_mark_type();
        return $col ? "<img src='{$arrMarkType[$col]}' width='20'height='20'>" : '';
    }
}
?>