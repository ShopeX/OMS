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

class ediws_finder_refundinfo
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    
    
    public $detail_items = '明细列表';
    /**
     * detail_items
     * @param mixed $id ID
     * @return mixed 返回值
     */
    public function detail_items($id)
    {

        $render = app::get('ediws')->render();
       
        
        $itemsMdl = app::get('ediws')->model('refundinfo_items');

        $items = $itemsMdl->getlist('*',array('refundinfo_id'=>$id));

        $render->pagedata['items'] = $items;
        unset($items);
        return $render->fetch('refundinfo_items.html');
        
    }

   
    
}
