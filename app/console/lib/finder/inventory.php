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

class console_finder_inventory{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    //var $detail_base = "基本信息";
    var $detail_item = "详情";

    /*function detail_base($appro_id){
        $render = app::get('console')->render();
        
    }*/
    
    function detail_item($inventory_id){
        $render = app::get('console')->render();
        $inv_iObj = app::get('console')->model('inventory_items');
        
        $count = $inv_iObj->count(array('inventory_id'=>$inventory_id));
        if ($count > 20){
            $render->pagedata['many'] = 'true';
            $rows = $inv_iObj->getList('*', array('inventory_id'=>$inventory_id), 0, 20);
        }else {
            $rows = $inv_iObj->getList('*', array('inventory_id'=>$inventory_id), 0, -1);
        }
        $render->pagedata['inventory_id'] = $inventory_id;
        $render->pagedata['rows'] = $rows;
        return $render->fetch("admin/inventory/item.html");
    }

}
?>
