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
 * 京东虎符WMS相关业务Lib类
 *
 * @author wangbiao@shopex.cn
 * @version 2026.10.30
 */
class erpapi_wms_matrix_hufu_response_stockdump extends erpapi_wms_response_stockdump
{
    public function status_update($params){
        $items = isset($params['item']) ? json_decode($params['item'], true) : array();
        if($items){
            $product_bns = array();
            foreach($items as $key=>$val){
                if ($val['product_bn']){
                    $product_bns[] = $val['product_bn'];
                }
            }
            
            $skuData = $this->getOmsProductBn($this->__channelObj->wms['channel_id'],$product_bns);
            
            foreach($items as $key=>$val){
                if (!$val['product_bn'])  continue;
                if($skuData[$val['product_bn']]){
                    $items[$key]['product_bn'] = $skuData[$val['product_bn']];
                }
            }
        }
        
        $params['item'] = json_encode($items);
        $params = parent::status_update($params);
        
        return $params;
    }
}
