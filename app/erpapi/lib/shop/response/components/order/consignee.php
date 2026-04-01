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
* 收货人信息
*
* @author chenping<chenping@shopex.cn>
* @version $Id: consignee.php 2013-3-12 17:23Z
*/
class erpapi_shop_response_components_order_consignee extends erpapi_shop_response_components_order_abstract
{
    const _APP_NAME = 'ome';
    /**
     * 数据转换
     *
     * @return void
     * @author  
     **/
    public function convert()
    {
        if ($this->_platform->_ordersdf['consignee']) {
            $area_state = $this->_platform->_ordersdf['consignee']['area_state']; //省
            $area_city = $this->_platform->_ordersdf['consignee']['area_city']; //市
            $area_district = $this->_platform->_ordersdf['consignee']['area_district']; //区
            $area_street = $this->_platform->_ordersdf['consignee']['area_street']; //镇、街道
            if(empty($area_district) && $area_street){
                $area_district = $area_street;
                $area_street = '';
            }
            
            $this->_platform->_newOrder['consignee']['name']      = $this->_platform->_ordersdf['consignee']['name'];
            $this->_platform->_newOrder['consignee']['area']      = $area_state .'/'. $area_city .'/'. $area_district; //地区
            $this->_platform->_newOrder['consignee']['addr']      = $this->_platform->_ordersdf['consignee']['addr'];
            $this->_platform->_newOrder['consignee']['zip']       = $this->_platform->_ordersdf['consignee']['zip'];
            $this->_platform->_newOrder['consignee']['telephone'] = $this->_platform->_ordersdf['consignee']['telephone'];
            $this->_platform->_newOrder['consignee']['email']     = $this->_platform->_ordersdf['consignee']['email'];
            $this->_platform->_newOrder['consignee']['r_time']    = $this->_platform->_ordersdf['consignee']['r_time'];
            $this->_platform->_newOrder['consignee']['mobile']    = $this->_platform->_ordersdf['consignee']['mobile'];
            
            //添加四级地区(镇、街道)
            if($area_street){
                $this->_platform->_newOrder['consignee']['area'] .= '/'. $area_street;
            }
        }
    }

    /**
     * 修改收货人
     *
     * @return void
     * @author 
     **/
    public function update()
    {
        $is_update = true;
        $order_id  = $this->_platform->_tgOrder['order_id'] ?? 0;
        $orRe      = app::get('ome')->model('order_receiver')->db_dump(['order_id' => $order_id], 'encrypt_source_data');
        $ensd      = $orRe ? @json_decode($orRe['encrypt_source_data'], 1) : [];
        $oaid      = $this->_platform->_ordersdf['index_field']['oaid'] ?? '';
        if ($oaid && isset($ensd['oaid']) && $ensd['oaid'] == $oaid) {
            $is_update = false;
        }
        $process_status = array('unconfirmed','confirmed','splitting','splited','is_retrial');
        if ($is_update && $this->_platform->_ordersdf['consignee'] && in_array($this->_platform->_tgOrder['process_status'], $process_status) && $this->_platform->_tgOrder['ship_status'] == '0') {
            $area_state = $this->_platform->_ordersdf['consignee']['area_state']; //省
            $area_city = $this->_platform->_ordersdf['consignee']['area_city']; //市
            $area_district = $this->_platform->_ordersdf['consignee']['area_district']; //区
            $area_street = $this->_platform->_ordersdf['consignee']['area_street']; //镇、街道
            if(empty($area_district) && $area_street){
                $area_district = $area_street;
                $area_street = '';
            }
            
            //地区
            $area = $area_state .'/'. $area_city .'/'. $area_district;
            
            //添加四级地区(镇、街道)
            if($area_street){
                $area .= '/' . $area_street;
            }
            
            kernel::single('ome_func')->region_validate($area);
            
            $consignee = array();
            $consignee['name']      = $this->_platform->_ordersdf['consignee']['name'];
            $consignee['area']      = $area;
            $consignee['addr']      = $this->_platform->_ordersdf['consignee']['addr'];
            $consignee['zip']       = $this->_platform->_ordersdf['consignee']['zip'];
            $consignee['telephone'] = $this->_platform->_ordersdf['consignee']['telephone'];
            $consignee['email']     = $this->_platform->_ordersdf['consignee']['email'];
            $consignee['r_time']    = $this->_platform->_ordersdf['consignee']['r_time'];
            $consignee['mobile']    = $this->_platform->_ordersdf['consignee']['mobile'];

            $diff_consignee = array_udiff_assoc($consignee, $this->_platform->_tgOrder['consignee'],array($this,'comp_array_value'));

            if ($diff_consignee) {
                $this->_platform->_newOrder['consignee'] = $diff_consignee;
            }
        }
    }
}