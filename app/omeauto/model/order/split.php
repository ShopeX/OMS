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

class omeauto_mdl_order_split extends dbeav_model{
    protected $split_type = array(
        'storemax' => '按库存就全拆',
        // 'goodstype' => '商品类型数量拆',
        'sku' => '单商品拆',
        'skuweight' => '按商品重量拆',
        'skucategory' => '按商品品类拆',
        'skuvolume' => '按商品体积拆',
        'branchgroup' => '按仓库分组拆',
        'virtualsku' => '按虚拟商品拆,虚拟商品自动发货',
        //'skuchannel' => '按京东开普勒商品渠道ID拆',
        'oid' => '按京东子订单拆',
        'orderhost' => '按达人信息拆',
        'customerclassify' => '按客户分类拆',
    );

    protected $batchConfirmSplitType = array('storemax', 'sku', 'skuweight','skucategory', 'skuvolume', 'oid');

    public function getBatchConfirmSplitType() {
        return $this->batchConfirmSplitType;
    }

    public function getSplitType() {
        return $this->split_type;
    }

    public function modifier_split_type($col) {
        return $this->split_type[$col] ? $this->split_type[$col] : $col;
    }
}