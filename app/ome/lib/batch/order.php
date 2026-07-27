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

class ome_batch_order{

    function getBranchStore($order_id,$branch_id)
    {
        $basicMStockFreezeLib  = kernel::single('material_basic_material_stock_freeze');
        
        $groupStore = array();
        $items = app::get('ome')->model('order_items')->getList('*', array('order_id' => $order_id, 'delete' => 'false'));
        foreach ($items as $item ) {

            if (in_array($item['product_id'], $groupStore['pids'])) {

                $groupStore['store'][$item['product_id']] += $item['nums'];
            } else {
    
                $groupStore['pids'][] = $item['product_id'];
                $groupStore['store'][$item['product_id']] = $item['nums'];
            }
        }
        $sql = "SELECT product_id, branch_id, store FROM sdb_ome_branch_product WHERE product_id in (".join(',', $groupStore['pids']).") AND branch_id IN (".$branch_id.")";
        $prows = kernel::database()->select($sql);
        
        //转换数据格式
        $store = array();
        if($prows)
        {
            $tempData    = array();
            foreach ((array) $prows as $row) {
                $product_id    = $row['product_id'];
                
                //根据仓库ID、基础物料ID获取该物料仓库级的预占
                $store_freeze    = $basicMStockFreezeLib->getBranchFreeze($row['product_id'], $row['branch_id']);
                $row['store']    = ($row['store'] < $store_freeze) ? 0 : ($row['store'] - $store_freeze);
                
                if($tempData[$product_id])
                {
                    $tempData[$product_id]['store'] += $row['store'];
                }
                else
                {
                    $tempData[$product_id] = $row;
                }
            }
            
            $store    = $tempData;
            unset($tempData);
        }
        
        //foreach ((array) $prows as $row) {
        //    $store[$row['product_id']] = $row;
        //}

        //检查订单组内的货品数量是否足够
        $allow = true;
        foreach ($groupStore['store'] as $pid => $nums) {
            if (($store[$pid]['store'] - $nums) <0) {

                $allow = false;
            } 
        }
        return $allow;
    }

    
    /**
     * 判断到不到
     * @
     * @
     * @access  public
     * @author sunjing@shopex.cn
     */
    function get_arrived($order,$corp,$branch)
    {
        $arrived_conf = app::get('ome')->getConf('ome.logi.arrived');
        $arrived_auto_conf = app::get('ome')->getConf('ome.logi.arrived.auto');
        $arrivedObj = kernel::single('omeauto_auto_plugin_arrived');
        $allow = true;
        if ($arrived_conf=='1' 
            && $arrived_auto_conf=='true'
            && in_array($order['shop_type'], $arrivedObj->getShopType())
            && $order['createway'] == 'matrix'
            && $branch['area']
        ) {
            $data = [
                'orders'=>[$order],
                'branch'=>$branch,
            ];
            $result = kernel::single('erpapi_router_request')->set('shop', $order['shop_id'])->logistics_addressReachable($data);

            if ($result['rsp']!='succ' || !$result['data'][$corp['type']]['is_deliverable']) {
                $allow = false;
            }
        }
        return $allow;
    }

    /**
     * ajaxDoAutoOne
     * @param mixed $order_id ID
     * @param mixed $data 数据
     * @return mixed 返回值
     */
    public function ajaxDoAutoOne($order_id, $data) {
        $is_combine = $data['is_combine'];
        $is_split = $data['is_split'];
        $branch = $data['branch'];
        $corp = $data['corp'];
        $splitId = $data['split_id'];
        $retArr = array(
            'itotal'  => $is_combine ? 1 : count($order_id),
            'isucc'   => 0,
            'ifail'   => 0,
            'err_msg' => array(),
        );
        $opObj      = app::get('ome')->model('operation_log');
        $orderExMdl = app::get('ome')->model('order_extend');
        $orderMdl   = app::get('ome')->model('orders');
        $filter = array();
        $filter['order_id']       = $order_id;
        $filter['process_status'] = array('unconfirmed','confirmed','splitting');
        $filter['assigned']       = 'assigned';
        $filter['abnormal']       = 'false';
        $filter['is_fail']        = 'false';
        $filter['pause']          = 'false';
        $filter['is_auto']        = 'false';
        $filter['status']         = 'active';
        $filter['archive']        = '0';
        $filter['ship_status']    = array('0','2');
        $filter['pay_status']     = array('0','1');

        // 判断是否为自有仓，用ome_branch表的owner判断，全峰会有问题
        $isSelfwms = true;
        $channelAdapter = app::get('channel')->model('adapter')->db_dump(['channel_id'=>$branch['wms_id']]);
        if ($channelAdapter['adapter'] != 'selfwms') {
            $isSelfwms = false;
        }

        $orderMdl->filter_use_like = true;
        $orderList = $orderMdl->getList('*',$filter);

        foreach ($orderList as $k => $v) {
            if ($v['shop_type'] == 'dewu') {
                $oAddress       = app::get('ome')->model('return_address');
                $dewuBrandList  = $oAddress->getList('distinct branch_bn', ['shop_type'=>'dewu']);
                $dewuBrandList  = array_column($dewuBrandList, 'branch_bn');
                $dewu_corp_list = kernel::single('logisticsmanager_waybill_dewu')->logistics();
                unset($dewu_corp_list['VIRTUAL']); // 品牌直发不能用虚拟发货，虚拟发货是急速现货用的
                $dewu_channel  = app::get('logisticsmanager')->model('channel')->getList('channel_id', ['channel_type'=>'dewu', 'logistics_code|noequal'=>'VIRTUAL']);
                break;
            }
            if ($v['shop_type'] == 'kuaishou') {
                $kuaishou_channel  = app::get('logisticsmanager')->model('channel')->getList('channel_id', ['channel_type'=>$v['shop_type']]);
            }
    
            //自选物流发货
            list($is_specify, $corp_ids) = kernel::single('logistics_rule')->getSelfSelectedLogistics($v['order_id'], $v['shipping'],$v['shop_type']);
            if ($corp['corp_id'] != 'auto' && $is_specify && !in_array($corp['corp_id'],$corp_ids)) {
                unset($orderList[$k]);
                $retArr['err_msg'][] = '[' . $v['order_bn'] . ']订单未按指定物流发货';
                $is_combine || $retArr['ifail']++;
                continue;
            }
        }

        // 扩展信息 Begin
        $order_extend = array();
        foreach ($orderExMdl->getList('order_id,platform_logi_no,extend_field,white_delivery_cps',array('order_id'=>$order_id)) as $value) {
            if ($value['extend_field'] && is_string($value['extend_field'])) {
                $value['extend_field'] = json_decode($value['extend_field'], 1);
            }
            if ($value['white_delivery_cps'] && is_string($value['white_delivery_cps'])) {
                $value['white_delivery_cps'] = json_decode($value['white_delivery_cps'], 1);
            }
            $order_extend[$value['order_id']] = $value;
        }
        // 扩展信息 End
        $isMkDly = true;
        foreach ($orderList as $order) {
            // 检测京东订单是否有微信支付先用后付的单据
            $use_before_payed = false;
            if ($order['shop_type'] == '360buy') {
                $labelCode = kernel::single('ome_bill_label')->getLabelFromOrder($order['order_id']);
                $labelCode = array_column($labelCode, 'label_code');
                $use_before_payed = kernel::single('ome_order')->canDeliveryFromBillLabel($labelCode);
            }
            
            //定制订单类型,未推送给莫凡,不允许手工审核订单
            if($order['order_type'] == 'custom' && $order['is_delivery'] == 'N'){
                $retArr['err_msg'][] = '['.$order['order_bn'].']定制订单需要先推送莫凡，才允许审核订单!';
                $is_combine || $retArr['ifail']++;
                $isMkDly = false; continue;
            }
            
            // 如果是未支付，判断到付
            if ($order['pay_status'] == '0' && $order['is_cod'] != 'true' && !$use_before_payed) {
                $retArr['err_msg'][] = '['.$order['order_bn'].']订单未支付完全';
                $is_combine || $retArr['ifail']++;
                $isMkDly = false; continue;
            }

            // 如果是到付，而且是未支付，判断是否要拆单
            if (($order['is_cod'] == 'true') && $order['pay_status'] == '0' && $is_split == true) {
                $retArr['err_msg'][] = '['.$order['order_bn'].']到付订单未支付不能拆单';
                $is_combine || $retArr['ifail']++;
                $isMkDly = false; continue;
            }

            // 如果是先用后付，而且是未支付，判断是否要拆单
            if (($use_before_payed) && $order['pay_status'] == '0' && $is_split == true) {
                $retArr['err_msg'][] = '['.$order['order_bn'].']先用后付订单未支付不能拆单';
                $is_combine || $retArr['ifail']++;
                $isMkDly = false; continue;
            }

            // 与单笔和自动审单共用同一套拼多多消费者付费送货上门物流校验。
            // corp_id=auto 时在自动物流插件选出实际物流后再次校验。
            $homeDeliveryError = '';
            if (!kernel::single('ome_order_platform_pinduoduo_chargehomedelivery')->validate(
                $order,
                $branch,
                $corp,
                $homeDeliveryError
            )) {
                $retArr['err_msg'][] = '['.$order['order_bn'].']'.$homeDeliveryError;
                $is_combine || $retArr['ifail']++;
                $isMkDly = false; continue;
            }

            // 如果是得物品牌直发，判断是否合规
            if ($order['shop_type'] == 'dewu' && kernel::single('ome_order_bool_type')->isDWBrand($order['order_bool_type'])) {

                // 由于没有实际单据测试，所以审单的时候拦截，暂不支持多仓
                if ($order_extend[$order['order_id']]['extend_field']['performance_type'] == '3'){
                    $retArr['err_msg'][] = '品牌直发暂不支持多仓发货的履约模式';
                    $is_combine || $retArr['ifail']++;
                    $isMkDly = false; continue;
                }

                if ($order_extend[$order['order_id']]['extend_field']['performance_type'] == '3' && !in_array($branch['branch_bn'], $dewuBrandList)) {
                    $retArr['err_msg'][] = '得物品牌直发多仓发货订单的发货仓不能选'.$branch['branch_bn'];
                    $is_combine || $retArr['ifail']++;
                    $isMkDly = false; continue;
                }
                if (!in_array($corp['type'], array_keys($dewu_corp_list))) {
                    $dewu_corp_list_str = '';
                    foreach ($dewu_corp_list as $d_k => $d_v) {
                        $dewu_corp_list_str .= $d_v.'['.$d_k.'];';
                    }
                    $retArr['err_msg'][] = '得物品牌直发物流公司只能用'.$dewu_corp_list_str;
                    $is_combine || $retArr['ifail']++;
                    $isMkDly = false; continue;
                }
                if ($order_extend[$order['order_id']]['extend_field']['performance_type'] != '2' && !in_array($corp['channel_id'], array_column($dewu_channel, 'channel_id'))) {
                    $retArr['err_msg'][] = '品牌直发物流公司的单号来源只能用得物类型';
                    $is_combine || $retArr['ifail']++;
                    $isMkDly = false; continue;
                }
            }

            // 如果是快手中转订单，判断是否合规
            if ($order['shop_type'] == 'kuaishou') {
                $is_jy        = false;
                $billLabelMdl = kernel::single('ome_bill_label');
                $billLabel    = $billLabelMdl->getLabelFromOrder($order['order_id'], 'order');
                if ($billLabel) {
                    foreach ($billLabel as $b_k => $b_v) {
                        if ($b_v['label_code'] == 'XJJY') {
                            $is_jy = true;
                            break;
                        }
                    }
                }
                if ($is_jy && !in_array($corp['channel_id'], array_column($kuaishou_channel, 'channel_id'))) {
                    $retArr['err_msg'][] = '快手中转订单的物流公司单号来源只能用快手类型';
                    $is_combine || $retArr['ifail']++;
                    $isMkDly = false; continue;
                }
            }

            // 如果是京东集运，只能用京东无界电子面单发货，否则发货回写会失败
            if ($order['shop_type'] == '360buy' && $isSelfwms) {
                $jyInfo = kernel::single('ome_bill_label')->getBillLabelInfo($order['order_id'], 'order', 'SOMS_GNJY');
                if ($jyInfo) {
                    $jd_channel = app::get('logisticsmanager')->model('channel')->getList("channel_id",array('status'=>'true','channel_type'=>['360buy','jdalpha']));
                    if (!in_array($corp['channel_id'], array_column($jd_channel, 'channel_id'))) {
                        $retArr['err_msg'][] = '京东集运订单的物流公司单号来源只能用京东无界';
                        $is_combine || $retArr['ifail']++;
                        $isMkDly = false; continue;
                    }

                }
            }

            // 如果是抖音中转，只能用抖音电子面单发货
            if ($order['shop_type'] == 'luban' && $isSelfwms) {
                $jyInfo = kernel::single('ome_bill_label')->getBillLabelInfo($order['order_id'], 'order', 'XJJY');
                if ($jyInfo) {
                    $jd_channel = app::get('logisticsmanager')->model('channel')->getList("channel_id",array('status'=>'true','channel_type'=>['douyin']));
                    if (!in_array($corp['channel_id'], array_column($jd_channel, 'channel_id'))) {
                        $retArr['err_msg'][] = '抖音中转订单的物流单号来源只能用抖音';
                        $is_combine || $retArr['ifail']++;
                        $isMkDly = false; continue;
                    }

                }
            }

            //不是推荐物流不能发货
            list($rs, $msg) = kernel::single('ome_event_trigger_shop_logistics')->judgeRecommend($order, $corp['type']);
            if(!$rs) {
                $retArr['err_msg'][] = '['.$order['order_bn'].']物流失败，'.$msg;
                $is_combine || $retArr['ifail']++;
                $isMkDly = false; continue;
            }
    
            //发货快递白名单 指定快递发货
            $white_delivery_cps = $order_extend[$order['order_id']]['white_delivery_cps'];
            if($white_delivery_cps && !in_array($corp['type'],$white_delivery_cps)){
                $retArr['err_msg'][] = '['.$order['order_bn'].']未按指定承运商配送';
                $is_combine || $retArr['ifail']++;
                $isMkDly = false; continue;
            }

            // 收货信息
            $consignee = array(
                'branch_id'   => $branch['branch_id'],
                'name'        => $order['ship_name'],
                'mobile'      => $order['ship_mobile'],
                'area'        => $order['ship_area'],
                'telephone'   => $order['ship_tel'],
                'addr'        => $order['ship_addr'],
                'waybillCode' => '',
                'oper_source' => '手工批量',
            );

            // 如果是爱库存，指定运单号
            if (!$is_split && $order_extend[$order['order_id']]['platform_logi_no']) {
                $consignee['waybillCode'] = $order_extend[$order['order_id']]['platform_logi_no'];
            }

            $split_auto = array(
                'source'   => 'handbatch',
                'is_split' => $is_split,
            );
            if($is_split) {
                $split_auto['split_id'] = $splitId;
            }

            // 验证物流到不到
            if ($corp['corp_id'] == 'auto' || kernel::single('ome_batch_order')->get_arrived($order,$corp,$branch)) {
                if($is_combine) {
                    continue;
                }
                $rs =   kernel::single('omeauto_auto_combine')->mkDelivery($order['order_id'], $consignee, $corp['corp_id'], array(), $errmsg, $split_auto);
                if ($rs) {
                    $retArr['isucc']++;
                } else {
                    $retArr['ifail']++;

                    $retArr['err_msg'][] = '['.$order['order_bn'].']'.$errmsg;
                }
                
            } else {
                $auto_status = $order['auto_status'] | omeauto_auto_const::_LOGIST_ARRIVED;

                $orderMdl->update(array('auto_status'=>$auto_status),array('order_id'=>$order_id));
                $is_combine || $retArr['ifail']++;
                $isMkDly = false;
                $retArr['err_msg'][] = '['.$order['order_bn'].']物流不可达';
            }
        }
        if($is_combine) {
            if($isMkDly) {
                $rs =   kernel::single('omeauto_auto_combine')->mkDelivery($order_id, $consignee, $corp['corp_id'], array(), $errmsg, $split_auto);
                if ($rs) {
                    $retArr['isucc']++;
                } else {
                    $retArr['ifail']++;

                    $retArr['err_msg'][] = '['.$order['order_bn'].']'.$errmsg;
                }
            } else {
                $retArr['ifail']++;
            }
        }
        return $retArr;
    }


    /***
    *  自动创建退款单处理
    *  sunjing
    */
    public function create_refund($order_id){

        $orderobj = app::get('ome')->model('orders');
        $order_detail = $orderobj->dump(array('order_id'=>$order_id),'payed,pay_status,order_id,shop_id,payment,ship_status');
        $split_oid = app::get('ome')->model('order_platformsplit')->getList('obj_id, split_oid', ['order_id'=>$order_id]);
        if($order_detail['payed']>0 && ($order_detail['pay_status'] == '4' || $split_oid) && in_array($order_detail['ship_status'],array('0'))){
            
            $refund_bn = app::get('ome')->model('refunds')->gen_id();
            $data = array(
                'refund_bn'     => $refund_bn,
                'shop_id'       => $order_detail['shop_id'],
                'order_id'      => $order_detail['order_id'],
                'currency'      => 'CNY',
                'money'         => $order_detail['payed'],
                'cur_money'     => $order_detail['payed'],
                'pay_type'      => '',
                'download_time' => time(),
                'status'        => 'succ',
                'memo'          => '强制退款',
                'trade_no'      => '',
                'modifiey'      => time(),
                'payment'       => $order_detail['payment'],
                't_ready'       => time(),
                't_sent'        => time(),

            );
            $rs = app::get('ome')->model('refunds')->insert($data);

            $sql ="update sdb_ome_orders set payed=IF((CAST(payed AS char)-IFNULL(0,cost_payment)-".$data['money'].")>=0,payed-IFNULL(0,cost_payment)-".$data['money'].",0)  where order_id=".$order_detail['order_id'];
            kernel::database()->exec($sql);
            kernel::single('ome_order_func')->update_order_pay_status($order_detail['order_id'], true, __CLASS__.'::'.__FUNCTION__);
        }
    }
}

?>
