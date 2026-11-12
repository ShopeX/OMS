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

class ome_finder_return_process{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $detail_basic = "收货/质检详情";
    
    function detail_basic($por_id){
        $render = app::get('ome')->render();
        $oProduct_pro = app::get('ome')->model('return_process');
        $oOrder = app::get('ome')->model('orders');
        $oProduct_pro_detail = $oProduct_pro->product_detail($por_id);
        $render->pagedata['pro_detail'] = $oProduct_pro_detail;
        if (!is_numeric($oProduct_pro_detail['attachment'])){
           $render->pagedata['attachment_type'] = 'remote';
        }
        $render->pagedata['order'] = $oOrder->dump($oProduct_pro_detail['order_id']);
        return $render->fetch('admin/sv_charge/detail.html');
    }

}
?>