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
 * @author ykm 2015-11-20
 * @describe 特殊订单发货订单
 */

class brush_mdl_delivery extends dbeav_model{
    //是否有导出配置
    var $has_export_cnf = true;
    var $export_name = '特殊订单发货单';
    var $export_flag = false;

    public function modifier_bnsContent($col){
        $bnsContent = unserialize($col);
        $skuNum = $bnsContent['skuNum'];
        $itemNum = $bnsContent['itemNum'];
        $cnts = $bnsContent['bn'];
        $cnt = sprintf("共有 %d 种商品，总共数量为 %d 件， 具体 SKU 为： %s", $skuNum, $itemNum, @implode(', ', $cnts));
        @reset($cnts);
        $content = $cnts[@key($cnts)];
        if ($skuNum >1) {
            $content .= ' 等';
        }
        return sprintf("<span alt='%s' title='%s'><font color='red'>(%d / %d)</font> %s</span>",$cnt, $cnt, $skuNum, $itemNum, $content);
    }

    public function modifier_logi_id($col) {
        if(!$col) {
            return '';
        }
        $model = app::get('ome')->model('dly_corp');
        $row = $model->dump(array($model->idColumn => $col), $model->textColumn);
        return $row ? $row[$model->textColumn] : $col;
    }

    public function modifier_ship_name($ship_name,$list,$row)
    {
        if ($this->is_export_data) return $ship_name;

        $is_encrypt = kernel::single('ome_security_hash')->check_encrypt($ship_name);

        if (!$is_encrypt) return $ship_name;

        $base_url = kernel::base_url(1);$delivery_id = $row['delivery_id'];
        $encryptShipName = kernel::single('ome_view_helper2')->modifier_ciphertext($ship_name,'delivery','ship_name');
$return =<<<HTML
<a class="data-hide" href="javascript:void(0);" onclick="Ex_Loader('security',function(){new Security({url:'index.php?app=brush&ctl=admin_delivery&act=showSensitiveData&p[0]={$delivery_id}',clickElement:\$(event.target)}).desHtml(\$(event.target).getNext()); });"></a><span><span sensitive-field="ship_name">{$encryptShipName}</span></span>
HTML;
        return $ship_name?$return:$ship_name;
    }

    public function modifier_ship_tel($tel,$list,$row)
    {
        if ($this->is_export_data) return $tel;

        $is_encrypt = kernel::single('ome_security_hash')->check_encrypt($tel);

        if (!$is_encrypt) return $tel;

        $base_url = kernel::base_url(1);$delivery_id = $row['delivery_id'];
        $encryptTel = kernel::single('ome_view_helper2')->modifier_ciphertext($tel,'delivery','ship_tel');

$return =<<<HTML
<a class="data-hide" href="javascript:void(0);" onclick="Ex_Loader('security',function(){new Security({url:'index.php?app=brush&ctl=admin_delivery&act=showSensitiveData&p[0]={$delivery_id}',clickElement:\$(event.target)}).desHtml(\$(event.target).getNext()); });"></a><span><span sensitive-field="ship_tel">{$encryptTel}</span></span>
HTML;
        return $tel?$return:$tel;
    }

    public function modifier_ship_mobile($mobile,$list,$row)
    {
        if ($this->is_export_data) return $mobile;

        $is_encrypt = kernel::single('ome_security_hash')->check_encrypt($mobile);

        if (!$is_encrypt) return $mobile;

        $base_url = kernel::base_url(1);$delivery_id = $row['delivery_id'];
        $encryptMobile = kernel::single('ome_view_helper2')->modifier_ciphertext($mobile,'delivery','ship_mobile');

$return =<<<HTML
<a class="data-hide" href="javascript:void(0);" onclick="Ex_Loader('security',function(){new Security({url:'index.php?app=brush&ctl=admin_delivery&act=showSensitiveData&p[0]={$delivery_id}',clickElement:\$(event.target)}).desHtml(\$(event.target).getNext()); });"></a><span><span sensitive-field="ship_mobile">{$encryptMobile}</span></span>
HTML;
        return $mobile?$return:$mobile;
    }

    public function modifier_ship_addr($ship_addr,$list,$row)
    {
        if ($this->is_export_data) return $ship_addr;

        $is_encrypt = kernel::single('ome_security_hash')->check_encrypt($ship_addr);

        if (!$is_encrypt) return $ship_addr;

        $base_url = kernel::base_url(1);$delivery_id = $row['delivery_id'];
        $encryptAddr = kernel::single('ome_view_helper2')->modifier_ciphertext($ship_addr,'delivery','ship_addr');

$return =<<<HTML
<a class="data-hide" href="javascript:void(0);" onclick="Ex_Loader('security',function(){new Security({url:'index.php?app=brush&ctl=admin_delivery&act=showSensitiveData&p[0]={$delivery_id}',clickElement:\$(event.target)}).desHtml(\$(event.target).getNext()); });"></a><span><span sensitive-field="ship_addr">{$encryptAddr}</span></span>
HTML;
        return $ship_addr?$return:$ship_addr;
    }

    function getOrderIdByDeliveryId($dly_ids){

        $filter['delivery_id'] = $dly_ids;
        $data = app::get('brush')->model('delivery_order')->getList('order_id', $filter);
        foreach ($data as $item){
            $ids[] = $item['order_id'];
        }

        return $ids;
    }
}