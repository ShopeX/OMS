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
 *
 *
 * @author sunjing<sunjing@shopex.cn>
 * @version $Id: tbpresale.php 2016-10-13 17:23Z
 */
class erpapi_shop_response_components_order_tbpresale extends erpapi_shop_response_components_order_abstract
{
    const _APP_NAME = 'ome';

    /**
     * convert
     * @return mixed 返回值
     */

    public function convert()
    {
        if($this->_platform->_ordersdf['order_type'] == 'presale'){
            $this->_platform->_newOrder['order_type'] = 'presale';
            if($this->_platform->_ordersdf['step_trade_status']=='FRONT_PAID_FINAL_NOPAID'){//订单已付尾款未付
                 
                $this->_platform->_newOrder['payed'] = $this->_platform->_ordersdf['step_paid_fee'];
                if($this->_platform->_ordersdf['step_paid_fee']>0){
                    $this->_platform->_newOrder['pay_status'] =3;
                }else{
                    $this->_platform->_newOrder['pay_status'] =$this->_platform->_ordersdf['pay_status'];
                }


            }
            // 天猫物流升级
            if ($this->_platform->_ordersdf['cn_info']['asdp_biz_type'] == 'logistics_upgrade') {
                $this->_platform->_ordersdf['latest_delivery_time'] = strtotime($this->_platform->_ordersdf['cn_info']['delivery_time']);
                $this->_platform->_ordersdf['cpup_service']         = $this->_platform->_ordersdf['cn_info']['asdp_ads'];
            }
        }
    }

    /**
     * 更新
     * @return mixed 返回值
     */
    public function update()
    {

        if($this->_platform->_tgOrder['order_type'] == 'presale') {
            $order_id = $this->_platform->_tgOrder['order_id'];
            if($this->_platform->_ordersdf['step_trade_status'] == 'FRONT_PAID_FINAL_PAID'){

                $this->_platform->_newOrder['step_trade_status'] = $this->_platform->_ordersdf['step_trade_status'];
                //查看扩展表里状态是否为1如果为1 需要更新状态
                $extendObj = app::get('ome')->model('order_extend');
                $extend = $extendObj->dump(array('order_id'=>$order_id));
                if ($extend['presale_auto_paid']>0 && $extend['presale_pay_status'] == '1'){
                    $extendObj->update(array('presale_auto_paid'=>0,'presale_pay_status'=>'2'),array('order_id'=>$order_id));

                    // //往前端发起回写状态
                    //通知wms发货

                }
                // 天猫物流升级
                if ($this->_platform->_ordersdf['cn_info']['asdp_biz_type'] == 'logistics_upgrade') {
                    $this->_platform->_ordersdf['latest_delivery_time'] = strtotime($this->_platform->_ordersdf['cn_info']['delivery_time']);
                    $this->_platform->_ordersdf['cpup_service']         = $this->_platform->_ordersdf['cn_info']['asdp_ads'];
                    $this->_platform->_ordersdf['collect_time'] = (int)strtotime($this->_platform->_ordersdf['cn_info']['collect_time']);
                    $this->_platform->_ordersdf['promised_collect_time'] = strtotime($this->_platform->_ordersdf['cn_info']['promised_collect_time']);
                    $this->_platform->_ordersdf['promised_sign_time'] = strtotime($this->_platform->_ordersdf['cn_info']['promised_sign_time']);
                }
            } elseif ($this->_platform->_ordersdf['payed'] == '0' && $this->_platform->_ordersdf['step_trade_status']=='FRONT_PAID_FINAL_NOPAID'){
                //订单已付尾款未付
                $this->_platform->_newOrder['payed'] = $this->_platform->_ordersdf['step_paid_fee'];
                if($this->_platform->_ordersdf['step_paid_fee']>0){
                    $this->_platform->_newOrder['pay_status'] = 3;
                }else{
                    $this->_platform->_newOrder['pay_status'] =$this->_platform->_ordersdf['pay_status'];
                }
            }

            //补付款单
            //补优惠方案
            if($this->_platform->_tgOrder['order_type'] == 'presale' && $this->_platform->_ordersdf['pay_status']=='1'){

                $pmt_detail_list = $this->_platform->_ordersdf['pmt_detail'];
                $pmtsdf = array();
                foreach ((array) $pmt_detail_list as $key => $value) {
                    if (!is_array($value) || trim($value['pmt_amount']) == '' || trim($value['pmt_amount']) == 0) {
                        continue;
                    }

                    //TODO:兼容拍拍优惠描述
                    $pmt_describe = '';
                    if ($partpmtdesc = strstr($value['pmt_describe'],'@')){
                        $pmt_describe = ltrim($partpmtdesc,"@");
                    }else{
                        $pmt_describe = $value['pmt_describe'];
                    }

                    $pmtsdf[] = array(
                        'order_id'     => $order_id,
                        'pmt_amount'   =>  number_format(abs($value['pmt_amount']), 3, '.', ''),
                        'pmt_describe' => $pmt_describe,
                    );
                }

                if ($pmtsdf){
                    $pmtObj = app::get('ome')->model('order_pmt');
                    $oldpmts = $pmtObj->getList('order_id,pmt_amount,pmt_describe',array('order_id'=>$order_id));

                    if (!$oldpmts){
                        kernel::single('erpapi_shop_response_plugins_order_promotion')->postCreate($order_id,$pmtsdf);
                    }


                }
   
                 //尾款通知wms发货
                ome_delivery_notice::notify_presale($order_id);
            }
        }
        

    }
}