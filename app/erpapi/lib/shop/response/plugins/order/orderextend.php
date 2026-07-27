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
* @author chenping<chenping@shopex.cn>
* @version $Id: 2013-3-12 17:23Z
*/
class erpapi_shop_response_plugins_order_orderextend extends erpapi_shop_response_plugins_order_abstract
{
    public function convert(erpapi_shop_response_abstract $platform)
    {
        $extend = array();


        if ($platform->_ordersdf['sellermemberid']) {
            $extend['sellermemberid'] = $platform->_ordersdf['sellermemberid'];
        }

        // 保存报关号
        if ($platform->_ordersdf['payment_no']) {
          $extend['contents']['custom_info']['payment_no'] =  $platform->_ordersdf['payment_no'];
        }

        if ($platform->_ordersdf['gateway_name']) {
          $extend['contents']['custom_info']['gateway_name'] = $platform->_ordersdf['gateway_name'];
        }

        //菜鸟直送推送时间
        if ($platform->_ordersdf['cn_info']) {
            $extend['push_time'] = strtotime($platform->_ordersdf['cn_info']['push_time']);
        }

        //交易订单指定运单信息
        if ($platform->_ordersdf['shipping']['shipping_id']) {
            $extend['platform_logi_no'] = $platform->_ordersdf['shipping']['shipping_id'];
        }
        
        //json平台订单扩展信息
        if ($platform->_ordersdf['extend_field']['store_info']) {
            //转成json
            if(is_array($platform->_ordersdf['extend_field']['store_info'])){
                $platform->_ordersdf['extend_field']['store_info'] = json_encode($platform->_ordersdf['extend_field']['store_info']);
            }
            
            $extend['store_info'] = $platform->_ordersdf['extend_field']['store_info'];
        }
        
        if ($platform->_ordersdf['o2o_info']){
            $o2o_info = $platform->_ordersdf['o2o_info'];
            if($o2o_info['o2o_store_bn']){
                $extend['o2o_store_bn'] = $o2o_info['o2o_store_bn'];
            }
            if ($o2o_info['o2o_store_name']){
                $extend['o2o_store_name'] = $o2o_info['o2o_store_name'];
            }
            
        }
        
        // 身份证
        if($platform->_ordersdf['certId']){
            $extend['cert_id'] = $platform->_ordersdf['certId'];
        }
        //天猫物流升级信息
        if ($platform->_ordersdf['cpup_service']) {
            $extend['cpup_service'] = $platform->_ordersdf['cpup_service'];
        }
        if ($platform->_ordersdf['cn_service']) {
            $extend['cn_service'] = $platform->_ordersdf['cn_service'];
        }
        if ($platform->_ordersdf['es_time']) {
            $extend['es_time'] = $platform->_ordersdf['es_time'];
        }
        if ($platform->_ordersdf['promise_service']) {
            $extend['promise_service'] = $platform->_ordersdf['promise_service'];
        }
        if ($platform->_ordersdf['collect_time']) {
            $extend['collect_time'] = $platform->_ordersdf['collect_time'];
        }
        
        if ($platform->_ordersdf['latest_delivery_time']) {
            $extend['latest_delivery_time'] = $platform->_ordersdf['latest_delivery_time'];
        }
        
        if ($platform->_ordersdf['promised_collect_time']) {
            $extend['promised_collect_time'] = $platform->_ordersdf['promised_collect_time'];
        }
        
        if ($platform->_ordersdf['promised_sign_time']) {
            $extend['promised_sign_time'] = $platform->_ordersdf['promised_sign_time'];
        }
        
        if($platform->_ordersdf['biz_delivery_code']){
            $extend['biz_delivery_code'] = $platform->_ordersdf['biz_delivery_code'];
        }

        // 控制字段只在插件保存阶段使用，不写入 order_extend 表。
        // 由保存阶段结合旧白名单计算有效交集，防止直接覆盖其他物流服务的限制。
        if ($platform->_ordersdf['shop_type'] == 'pinduoduo'
            && isset($platform->_ordersdf['charge_home_delivery_door'])) {
            $extend['_charge_home_delivery_door'] = $platform->_ordersdf['charge_home_delivery_door'];
        }
        //保价订单SKU商品
        if ($platform->_ordersdf['extend_field']['special_refund_type_info'] && is_array($platform->_ordersdf['extend_field']['special_refund_type_info'])) {
            foreach ($platform->_ordersdf['extend_field']['special_refund_type_info'] as $skuOid => $skuVal)
            {
                $skuVal = trim(strtolower($skuVal));
                if($skuVal == 'priceprotect'){
                    $extend['price_protect'][$skuOid] = $skuOid;
                }
            }
        }

        if ($platform->_ordersdf['assign_express_code'] && is_string($platform->_ordersdf['assign_express_code'])) {
            $extend['assign_express_code'] = $platform->_ordersdf['assign_express_code'];
        }
  
        // 抖店顺丰包邮，指定快递编码
        if ($platform->_ordersdf['sf_free_shipping']) {
            $extend['assign_express_code'] = $platform->_ordersdf['shipping']['shipping_name'];
        }

        // 官方提货物流，指定快递编码
        if ($platform->_ordersdf['official_pickup']) {
            $extend['assign_express_code'] = 'pickup';
        }
        
        //asdp_biz_type
        $asdp_biz_type = $platform->_ordersdf['cn_info']['asdp_biz_type'];
        if(empty($asdp_biz_type) && $platform->_ordersdf['cn_info']){
            $asdp_biz_type = $platform->_ordersdf['cn_info']['logistics_agreement']['asdp_biz_type'];
        }
        
        // // 得物品牌直发履约类型
        // if ($platform->_ordersdf['performance_type']) {
        //     $extend['performance_type'] = $platform->_ordersdf['performance_type'];
        // }

        // vop唯品会 重点检查
        foreach ($platform->_ordersdf['order_objects'] as $k => $order_objects) {
            if (is_array($order_objects['extend_item_list']) && $order_objects['extend_item_list']['quality_check_type']) {
                $platform->_ordersdf['extend_field']['quality_check'][$order_objects['shop_goods_id']] = [
                    'shop_goods_id'            => $order_objects['shop_goods_id'],
                    'quality_check_type'       => $order_objects['extend_item_list']['quality_check_type'],
                    'quality_check_type_desc'  => $order_objects['extend_item_list']['quality_check_type_desc'],
                    // 'check_items'              => $order_objects['extend_item_list']['check_items'], // check_items保存在order_objects_check_items表里，保持数据保存唯一性
                ];
            }
        }
        
        //格式化翱象数据
        if($asdp_biz_type == 'aox'){
            $extendData = $this->_formatAoxiangData($platform->_ordersdf);
            
            //info
            $axExtendInfo = array(
                'es_time' => $extendData['es_time'], //物运时间(预计送达时间)
                'promise_service' => $extendData['promise_service'], //物流服务标签
                'promised_sign_time' => $extendData['promise_sign_time'], //承诺最晚送达时间
                'promised_collect_time' => $extendData['promise_collect_time'], //承诺最晚揽收时间
                'latest_delivery_time' => $extendData['latest_delivery_time'], //最晚出库时间
                'plan_sign_time' => $extendData['plan_sign_time'], //计划送达时间
                'push_time' => $extendData['push_time'], //ERP应推单时间(主单)
                'biz_delivery_code' => $extendData['biz_delivery_code'], //建议快递名单
                'white_delivery_cps' => $extendData['white_delivery_cps'], //快递白名单
                'black_delivery_cps' => $extendData['black_delivery_cps'], //快递黑名单
                'cpup_service' => $extendData['cpup_service'], //物流升级服务
            );
            $axExtendInfo = array_filter($axExtendInfo);
            
            //extend_field
            $extendFieldInfo = array(
                'biz_sd_type' => $extendData['biz_sd_type'], //建议仓类型
                'biz_store_code' => $extendData['biz_store_code'], //预选仓库编码
                'biz_delivery_type' => $extendData['biz_delivery_type'], //择配建议
                'send_country' => $extendData['send_country'], //国家--预估发货地址
                'send_state' => $extendData['send_state'], //省--预估发货地址
                'send_city' => $extendData['send_city'], //市
                'send_district' => $extendData['send_district'], //区
                'send_town' => $extendData['send_town'], //镇
                'send_division_code' => $extendData['send_division_code'], //预估发货地编码
            );
            $extendFieldInfo = array_filter($extendFieldInfo);
            
            //merge
            if($extendFieldInfo){
                $platform->_ordersdf['extend_field'] = array_merge($platform->_ordersdf['extend_field'], $extendFieldInfo);
            }
            
            //merge
            if($axExtendInfo){
                $extend = array_merge($extend, $axExtendInfo);
            }
        }
        //京东厂直 预约发货 预计发货时间
        if ($platform->_ordersdf['cn_info']['appointment_ship_time']) {
            $extend['latest_delivery_time'] = kernel::single('ome_func')->date2time($platform->_ordersdf['cn_info']['appointment_ship_time']);
        }
        //预计送达时间
        if ($platform->_ordersdf['cn_info']['es_date']) {
            $extend['promised_sign_time'] = kernel::single('ome_func')->date2time($platform->_ordersdf['cn_info']['es_date']);
        }
        if ($platform->_ordersdf['cn_info']['promise_delivery_time']) {
            $extend['promised_sign_time'] = kernel::single('ome_func')->date2time($platform->_ordersdf['cn_info']['promise_delivery_time']);
        }
        //json_encode
        if($platform->_ordersdf['extend_field']) {
            $extend['extend_field'] = json_encode($platform->_ordersdf['extend_field'], JSON_UNESCAPED_UNICODE);
        }
       
        return $extend;
    }

    /**
     *
     * @return void
     * @author 
     **/
    public function postCreate($order_id,$extendinfo)
    {
        $orderExtendObj = app::get('ome')->model('order_extend'); 

        $extendinfo = $this->_prepareChargeHomeDeliveryDoorExtend($order_id, $extendinfo);
        
        if ($extendinfo['contents']) {      
          // 判断contents是否有值
          $row = $orderExtendObj->getList('contents',array('order_id'=>$order_id));
          if ($row && $row[0]['contents']) {
            $contents = @unserialize($row[0]['contents']);

            $newcontents = array_merge((array)$contents,$extendinfo['contents']);

            $extendinfo['contents'] = serialize($newcontents);
            if ($contents == $newcontents) {
               unset($extendinfo['contents']);
            }
            
          }
        }
        
        if ($extendinfo) {
          $extendinfo['order_id'] = $order_id;
          $orderExtendObj->save($extendinfo);
        }
        
        //价保订单打标识
        if($extendinfo['price_protect']){
            $labelLib = kernel::single('omeauto_order_label');
            $error_msg = '';
            $labelResult = $labelLib->labelPriceProtectOrder($order_id, $extendinfo['price_protect'], $error_msg);
        }
        
    }

  /**
   *
   * @param Array 
   * @return void
   * @author 
   **/
  public function postUpdate($order_id,$extendinfo)
  {
    $orderExtendObj = app::get('ome')->model('order_extend');

    $extendinfo = $this->_prepareChargeHomeDeliveryDoorExtend($order_id, $extendinfo);
    
    if ($extendinfo['contents']) {      
      // 判断contents是否有值
      $row = $orderExtendObj->getList('contents',array('order_id'=>$order_id));
      if ($row && $row[0]['contents']) {
        $contents = @unserialize($row[0]['contents']);

        $newcontents = array_merge((array)$contents,$extendinfo['contents']);

        $extendinfo['contents'] = serialize($newcontents);
        if ($contents == $newcontents) {
           unset($extendinfo['contents']);
        }
      }
    }

    if ($extendinfo) {
      $extendinfo['order_id'] = $order_id;
      $orderExtendObj->save($extendinfo);
    }  
  }

    /**
     * 合并拼多多消费者付费送货上门物流白名单
     *
     * white_delivery_cps 可能已经承载其他平台物流服务限制。首次命中服务时取交集，
     * 并在 OMS 专用扩展节点保存合并前白名单；服务取消时再恢复，避免直接清空或覆盖
     * 其他业务写入的承运商限制。
     *
     * @param int $order_id
     * @param array $extendinfo
     * @return array
     */
    private function _prepareChargeHomeDeliveryDoorExtend($order_id, $extendinfo)
    {
        if (!array_key_exists('_charge_home_delivery_door', $extendinfo)) {
            return $extendinfo;
        }

        $serviceInfo = $extendinfo['_charge_home_delivery_door'];
        unset($extendinfo['_charge_home_delivery_door']);

        $orderExtendObj = app::get('ome')->model('order_extend');
        $oldExtend = $orderExtendObj->db_dump(['order_id' => $order_id], 'white_delivery_cps,extend_field');
        $oldExtendField = $oldExtend['extend_field'] ? json_decode($oldExtend['extend_field'], true) : [];
        $newExtendField = $extendinfo['extend_field'] ? json_decode($extendinfo['extend_field'], true) : [];
        $oldServiceInfo = $oldExtendField['charge_home_delivery_door_oms'] ?? [];

        if ($serviceInfo['active']) {
            if (array_key_exists('white_delivery_cps', $extendinfo)) {
                $originalWhiteDeliveryCps = json_decode($extendinfo['white_delivery_cps'], true);
            } elseif (array_key_exists('original_white_delivery_cps', $oldServiceInfo)) {
                $originalWhiteDeliveryCps = $oldServiceInfo['original_white_delivery_cps'];
            } else {
                $originalWhiteDeliveryCps = $oldExtend['white_delivery_cps'] ? json_decode($oldExtend['white_delivery_cps'], true) : [];
            }

            $originalWhiteDeliveryCps = array_values(array_unique(array_filter((array)$originalWhiteDeliveryCps)));
            $serviceWhiteDeliveryCps = array_values(array_unique((array)$serviceInfo['white_delivery_cps']));
            $effectiveWhiteDeliveryCps = $originalWhiteDeliveryCps
                ? array_values(array_intersect($originalWhiteDeliveryCps, $serviceWhiteDeliveryCps))
                : $serviceWhiteDeliveryCps;

            $serviceInfo['original_white_delivery_cps'] = $originalWhiteDeliveryCps;
            $serviceInfo['effective_white_delivery_cps'] = $effectiveWhiteDeliveryCps;
            $newExtendField['charge_home_delivery_door_oms'] = $serviceInfo;
            $extendinfo['white_delivery_cps'] = json_encode($effectiveWhiteDeliveryCps);
            $extendinfo['extend_field'] = json_encode($newExtendField, JSON_UNESCAPED_UNICODE);
        } elseif ($oldServiceInfo) {
            // 当前白名单仍是本功能上次写入的值时才恢复，避免覆盖期间由其他业务更新的白名单。
            $currentWhiteDeliveryCps = $oldExtend['white_delivery_cps'] ? json_decode($oldExtend['white_delivery_cps'], true) : [];
            $effectiveWhiteDeliveryCps = (array)$oldServiceInfo['effective_white_delivery_cps'];
            if ($currentWhiteDeliveryCps == $effectiveWhiteDeliveryCps) {
                $extendinfo['white_delivery_cps'] = json_encode((array)$oldServiceInfo['original_white_delivery_cps']);
            }
            unset($newExtendField['charge_home_delivery_door_oms']);
            $extendinfo['extend_field'] = json_encode($newExtendField, JSON_UNESCAPED_UNICODE);
        }

        return $extendinfo;
    }
    
    /**
     * 翱象数据格式化
     *
     * @param $ordersdf
     * @return void
     */
    public function _formatAoxiangData($ordersdf)
    {
        $extendSdf = array();
        
        //翱象物流协议
        $cnInfo = $ordersdf['cn_info'];
        
        //子单纬度的择仓、择配
        $logistics_infos = $ordersdf['logistics_infos'];
        
        //check
        if(empty($cnInfo) && empty($logistics_infos)){
            return array();
        }
        
        //预计送达时间
        if($cnInfo['es_date']){
            $extendSdf['es_time'] = strtotime($cnInfo['es_date'] .' 00:00:00');
        }
        
        //物流服务
        if($cnInfo['logistics_agreement']){
            //物流服务标签
            if($cnInfo['logistics_agreement']['logistics_service_msg']){
                $extendSdf['promise_service'] = $cnInfo['logistics_agreement']['logistics_service_msg'];
            }
            
            //计划送达时间
            if($cnInfo['logistics_agreement']['sign_time']){
                $extendSdf['plan_sign_time'] = strtotime($cnInfo['logistics_agreement']['sign_time']);
            }
            
            //承诺/最晚送达时间
            if($cnInfo['logistics_agreement']['promise_sign_time']){
                $extendSdf['promise_sign_time'] = strtotime($cnInfo['logistics_agreement']['promise_sign_time']);
            }
            
            //ERP应推单时间(主单)
            if($cnInfo['logistics_agreement']['push_time']){
                $extendSdf['push_time'] = strtotime($cnInfo['logistics_agreement']['push_time']);
            }
            
            //物流升级服务
            if($cnInfo['logistics_agreement']['asdp_ads']){
                $extendSdf['cpup_service'] = $cnInfo['logistics_agreement']['asdp_ads'];
            }
        }
        
        //子单物流发货信息
        if($logistics_infos && is_array($logistics_infos)) {
            $logisticsInfos = array();
            foreach($logistics_infos as $key => $val)
            {
                //format
                $val['promise_collect_time'] = ($val['promise_collect_time'] ? strtotime($val['promise_collect_time']) : 0);
                $val['promise_outbound_time'] = ($val['promise_outbound_time'] ? strtotime($val['promise_outbound_time']) : 0);
                
                //建议使用快递名单
                if($val['biz_delivery_code']){
                    $tempCps = explode(',', $val['biz_delivery_code']);
                    $tempCps = array_unique(array_filter($tempCps));
                    
                    //merge
                    if($logisticsInfos['biz_delivery_codes']){
                        $logisticsInfos['biz_delivery_codes'] = array_merge($logisticsInfos['biz_delivery_codes'], $tempCps);
                    }else{
                        $logisticsInfos['biz_delivery_codes'] = $tempCps;
                    }
                }
                
                //快递白名单
                if($val['white_delivery_cps']){
                    $tempCps = explode(',', $val['white_delivery_cps']);
                    $tempCps = array_unique(array_filter($tempCps));
                    
                    //merge
                    if($logisticsInfos['white_delivery_cps']){
                        $logisticsInfos['white_delivery_cps'] = array_merge($logisticsInfos['white_delivery_cps'], $tempCps);
                    }else{
                        $logisticsInfos['white_delivery_cps'] = $tempCps;
                    }
                }
                
                //快递黑名单
                if($val['black_delivery_cps']){
                    $tempCps = explode(',', $val['black_delivery_cps']);
                    $tempCps = array_unique(array_filter($tempCps));
                    
                    //merge
                    if($logisticsInfos['black_delivery_cps']){
                        $logisticsInfos['black_delivery_cps'] = array_merge($logisticsInfos['black_delivery_cps'], $tempCps);
                    }else{
                        $logisticsInfos['black_delivery_cps'] = $tempCps;
                    }
                }
                
                //子单的【最晚揽收时间】
                if($val['promise_collect_time']){
                    $logisticsInfos['promise_collect_time'][] = $val['promise_collect_time'];
                }
                
                //子单的【最晚出库时间】
                if($val['promise_outbound_time']){
                    $logisticsInfos['promise_outbound_time'][] = $val['promise_outbound_time'];
                }
                
                //预估发货地址
                if($val['send_state'] && $val['send_city']){
                    $extendSdf['send_country'] = $val['send_country']; //国家
                    $extendSdf['send_state'] = $val['send_state']; //省
                    $extendSdf['send_city'] = $val['send_city']; //市
                    $extendSdf['send_district'] = $val['send_district']; //区
                    $extendSdf['send_town'] = $val['send_town']; //镇
                    $extendSdf['send_division_code'] = $val['send_division_code']; //预估发货地编码
                }
                
                //建议仓类型
                if($val['biz_sd_type']){
                    $extendSdf['biz_sd_type'] = $val['biz_sd_type'];
                }
                
                //预选仓库编码
                if($val['biz_store_code'] && in_array($extendSdf['biz_sd_type'], array(1, 2))){
                    $extendSdf['biz_store_code'] = $val['biz_store_code'];
                }
                
                //择配建议
                if($val['biz_delivery_type']){
                    $extendSdf['biz_delivery_type'] = $val['biz_delivery_type'];
                }
            }
            
            //format物流公司列表唯一性
            if($logisticsInfos['biz_delivery_codes'] && is_array($logisticsInfos['biz_delivery_codes'])){
                $tempList = array();
                foreach ($logisticsInfos['biz_delivery_codes'] as $logi_key => $logi_code)
                {
                    $tempList[$logi_code] = $logi_code;
                }
                
                $logisticsInfos['biz_delivery_codes'] = $tempList;
            }
            
            if($logisticsInfos['white_delivery_cps'] && is_array($logisticsInfos['white_delivery_cps'])){
                $tempList = array();
                foreach ($logisticsInfos['white_delivery_cps'] as $logi_key => $logi_code)
                {
                    $tempList[$logi_code] = $logi_code;
                }
                
                $logisticsInfos['white_delivery_cps'] = $tempList;
            }
            
            if($logisticsInfos['black_delivery_cps'] && is_array($logisticsInfos['black_delivery_cps'])){
                $tempList = array();
                foreach ($logisticsInfos['black_delivery_cps'] as $logi_key => $logi_code)
                {
                    $tempList[$logi_code] = $logi_code;
                }
                
                $logisticsInfos['black_delivery_cps'] = $tempList;
            }
            
            //json
            $extendSdf['biz_delivery_code'] = ($logisticsInfos['biz_delivery_codes'] ? json_encode($logisticsInfos['biz_delivery_codes']) : '');
            $extendSdf['white_delivery_cps'] = ($logisticsInfos['white_delivery_cps'] ? json_encode($logisticsInfos['white_delivery_cps']) : '');
            $extendSdf['black_delivery_cps'] = ($logisticsInfos['black_delivery_cps'] ? json_encode($logisticsInfos['black_delivery_cps']) : '');
            
            //取子单上最早的【最晚揽收时间】
            if($logisticsInfos['promise_collect_time']){
                $extendSdf['promise_collect_time'] = min($logisticsInfos['promise_collect_time']);
            }
            
            //取子单上最早的【最晚出库时间】
            if($logisticsInfos['promise_outbound_time']){
                $extendSdf['latest_delivery_time'] = min($logisticsInfos['promise_outbound_time']);
            }
        }
        
        return $extendSdf;
    }
}
