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

class ome_finder_payment_cfg{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $detail_basic = "支付方式详情";
    
    function detail_basic($id){
        $render = app::get('ome')->render();
        $cfgObj = app::get('ome')->model('payment_cfg');
        $cfgShopObj = app::get('ome')->model('payment_shop');

        $cfg = $cfgObj->dump($id);
        $cfgShop = $cfgShopObj->getShopByPayBn($cfg['pay_bn']);
        $cfg['pay_type'] = ome_payment_type::pay_type_name($cfg['pay_type']);

        $render->pagedata['cfg'] = $cfg;
        $render->pagedata['cfgShop'] = $cfgShop;
        return $render->fetch("admin/system/payment_cfg_detail.html");
    }

    var $addon_cols = "id,pay_bn";
    var $column_relation_shop = "关联店铺";
    var $column_relation_shop_width = "320";
    function column_relation_shop($row){
        $cfgShopObj = app::get('ome')->model('payment_shop');

        $pay_bn = $row[$this->col_prefix.'pay_bn'];
        $cfgShop = $cfgShopObj->getShopByPayBn($pay_bn);
        $arr_relation_shop = array();
        foreach($cfgShop as $shop){
            $arr_relation_shop[]= $shop['name'];
        }
        $relation_shop = implode(' | ', $arr_relation_shop);
        $value = '<span title="'.$relation_shop.'">'.$relation_shop.'</span>';

        return $value;
    }
}
?>