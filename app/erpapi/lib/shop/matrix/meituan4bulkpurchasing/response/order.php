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
 * 美团订单
 */
class erpapi_shop_matrix_meituan4bulkpurchasing_response_order extends erpapi_shop_response_order
{
    protected function get_update_components()
    {
        $components = array('markmemo', 'custommemo', 'marktype');
        
        $rs = app::get('ome')->model('order_extend')->getList('extend_status',array('order_id'=>$this->_tgOrder['order_id']));
        // 如果ERP收货人信息未发生变动时，则更新淘宝收货人信息
        if ($rs[0]['extend_status'] != 'consignee_modified') {
            $components[] = 'consignee';
        }
        return $components;
    }
    
    protected function _analysis()
    {
        parent::_analysis();
        $hashCode = kernel::single('ome_security_hash')->get_code();
        if ($this->_ordersdf['extend_field']['oaid']) {
            foreach ($this->_ordersdf['consignee'] as $key => $value) {
                // 邮编和邮箱不是 OMS 隐私索引字段，不能拼接 oaid@hash。
                if (in_array($key, ['email', 'zip', 'ship_zip'])) {
                    continue;
                }

                if(strpos($value, '*') !== false) {
                    $this->_ordersdf['consignee'][$key] .= '>>' . $this->_ordersdf['extend_field']['oaid'] . $hashCode;
                }
            }
        }
    }
}
