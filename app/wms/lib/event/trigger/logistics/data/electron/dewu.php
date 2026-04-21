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
 * 得物
 * Class wms_event_trigger_logistics_data_electron_dewu
 */
class wms_event_trigger_logistics_data_electron_dewu extends wms_event_trigger_logistics_data_electron_common
{
    /**
     * 获取DirectSdf
     * @param mixed $arrDelivery arrDelivery
     * @param mixed $arrBill arrBill
     * @param mixed $shop shop
     * @return mixed 返回结果
     */

    public function getDirectSdf($arrDelivery, $arrBill, $shop)
    {
        $delivery  = $arrDelivery[0];
        $primaryBn = $delivery['delivery_bn'];
        if (empty($arrBill)) {
            $this->needRequestId[] = $delivery['delivery_id'];
        } else {
            $this->needRequestId[]   = $arrBill[0]['b_id'];
            $delivery['delivery_bn'] = $this->setChildRqOrdNo($delivery['delivery_bn'], $arrBill[0]['b_id']);
        }
        
        $sdf                   = parent::getDirectSdf($arrDelivery, $arrBill, $shop);
        $sdf['primary_bn']     = $primaryBn;
        $sdf['delivery']       = $delivery;
        $sdf['logistics_code'] = $this->channel['logistics_code'];
        return $sdf;
    }
}