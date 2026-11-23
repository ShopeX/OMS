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

class dealer_aftersale
{
    public function getPlaOrderObjectsItems($data) {
        $returnData = array();
        $filter['plat_order_id'] = $data['plat_order_id'];

        if($data['plat_oid']) $filter['plat_oid'] = $data['plat_oid'];
        $objectData = app::get('dealer')->model('platform_order_objects')->getList('plat_oid,plat_order_id,bn, quantity,plat_obj_id,is_shopyjdf_step', $filter);
        if(empty($objectData)) {
            return array();
        }
        $arrObjId = array();
        foreach($objectData as $oVal) {
            $oVal['item_nums'] = 0;
            $returnData[$oVal['plat_order_id']][$oVal['plat_obj_id']] = $oVal;
            $arrObjId[] = $oVal['plat_obj_id'];
        }
        $itemFilter['plat_obj_id'] = $arrObjId;
        $itemData = app::get('dealer')->model('platform_order_items')->getList('*', $itemFilter);
        if(empty($itemData)) {
            return array();
        }
        foreach($itemData as $iVal) {
            $iVal['quantity'] = $iVal['nums'];
            if($iVal['is_delete'] == 'false') {
                $returnData[$iVal['plat_order_id']][$iVal['plat_obj_id']]['item_nums'] += $iVal['nums'];
            }
            $returnData[$iVal['plat_order_id']][$iVal['plat_obj_id']]['platorder_items'][$iVal['plat_item_id']] = $iVal;
        }
        return $returnData;
    }
}
