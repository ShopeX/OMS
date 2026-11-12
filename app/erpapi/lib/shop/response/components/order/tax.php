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
* 发票
*
* @author chenping<chenping@shopex.cn>
* @version $Id: tax.php 2013-3-12 17:23Z
*/
class erpapi_shop_response_components_order_tax extends erpapi_shop_response_components_order_abstract
{
    const _APP_NAME = 'ome';

    /**
     * convert
     * @return mixed 返回值
     */

    public function convert()
    {
        $this->_platform->_newOrder['is_tax']           = ($this->_platform->_ordersdf['is_tax'] === 'true' || $this->_platform->_ordersdf['is_tax'] === true || $this->_platform->_ordersdf['is_tax'] == '1') ? 'true' : 'false';


        $this->_platform->_newOrder['cost_tax']         = (float)$this->_platform->_ordersdf['cost_tax'];
        // $this->_platform->_newOrder['tax_no']           = $this->_platform->_ordersdf['tax_no'];
        // $this->_platform->_newOrder['tax_title']        = $this->_platform->_ordersdf['tax_title'];
        // $this->_platform->_newOrder['ship_tax']        = $this->_platform->_ordersdf['payer_register_no'];
        // $this->_platform->_newOrder['business_type'] = $this->_platform->_ordersdf['payer_register_no'] ? 1 : 0;
        // $shop_id = $this->_platform->__channelObj->channel['shop_id'];

        //这里判断开票方式是电子or纸质 获取开票信息
        // $invoice_kind = intval($this->_platform->_ordersdf["invoice_kind"]);
        // $mode = "0";
        // if($invoice_kind == 1){
        //     $mode = "1";
        // }
        // $rs_invoice_setting = kernel::single('invoice_common')->getInOrderSetByShopId($shop_id,$mode);

        //前端店铺下tab发票配置页 前端店铺下单发票设置
        // if($this->_platform->_newOrder['is_tax'] == 'false' && $rs_invoice_setting['force_tax_switch'] == '1'){
        //     $this->_platform->_newOrder['is_tax'] = 'true';
        //     $this->_platform->_newOrder['tax_title'] = $rs_invoice_setting['force_tax_title'];
        // }
        
        //需要开票的并且是要电子发票的
        // if($this->_platform->_newOrder['is_tax'] == "true" && $invoice_kind == 1){
        //     $this->_platform->_newOrder["invoice_mode"] = "1";
        // }
    }
    
    /**
     * 更新
     * @return mixed 返回值
     */
    public function update()
    {
        // if ($this->_platform->_ordersdf['tax_title'] && $this->_platform->_ordersdf['tax_title'] != $this->_platform->_tgOrder['tax_title']) {
        //     $this->_platform->_newOrder['tax_title'] = $this->_platform->_ordersdf['tax_title'];
        // }
        
        $this->_platform->_ordersdf['is_tax'] = ($this->_platform->_ordersdf['is_tax'] === 'true' || $this->_platform->_ordersdf['is_tax'] === true) ? 'true' : 'false';

        if ($this->_platform->_ordersdf['is_tax'] != $this->_platform->_tgOrder['is_tax']) {
            $this->_platform->_newOrder['is_tax'] = $this->_platform->_ordersdf['is_tax'];
        }

        if(floatval($this->_platform->_ordersdf['cost_tax']) != $this->_platform->_tgOrder['cost_tax']) {
            $this->_platform->_newOrder['cost_tax'] = $this->_platform->_ordersdf['cost_tax'];
        }

        // if ($this->_platform->_ordersdf['tax_no'] && $this->_platform->_ordersdf['tax_no'] != $this->_platform->_tgOrder['tax_no']) {
        //     $this->_platform->_newOrder['tax_no'] = $this->_platform->_ordersdf['tax_no'];
        // }

        // if ($this->_platform->_ordersdf['payer_register_no'] && $this->_platform->_ordersdf['payer_register_no'] != $this->_platform->_tgOrder['ship_tax']) {
        //     $this->_platform->_newOrder['ship_tax'] = $this->_platform->_ordersdf['payer_register_no'];
        //     $this->_platform->_newOrder['business_type'] = $this->_platform->_ordersdf['payer_register_no'] ? 1 : 0;
        // }
    }
}
