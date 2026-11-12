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
* 订单接收函数库
* @author dqiujing@gmail.com
* @copyright shopex.cn 2012.06.06
*/
class ome_rpc_response_func_order{

    public $shop_type2pay_type = array(
        'taobao' => 'alipaytrad',
        'paipai' => 'tenpaytrad',
    );

    /**
    * 订单保存
    * @access public
    * @param Array $sdf 订单sdf结构数据
    * @return Array array('rsp'=>'保存状态success/fail','msg'=>'错误消息','data'=>'数据')
    */
    public function save(&$sdf){

        //增加对南极人的处理
        if (strtolower($sdf['to_node_id']) == '1542862534' || strtolower($_SERVER['SERVER_NAME']) == 'yuewei.tg.taoex.com') {
            $countLWY =0;
            foreach ($sdf['order_objects'] as $item) {
                if (strpos($item['name'], '【11聚】') !== false) {
                    $countLWY++;
                }
            }

            //如果多有【11聚】字样则绝收
            if (($countLWY>0 && $countLWY == count($sdf['order_objects'])) || empty($sdf['order_objects'])) {
                $rs = array('rsp'=>'fail','msg'=>'良无印发货订单'.$sdf['order_bn'],'data'=>$sdf, 'logInfo'=>'良无印发货订单'.var_export($sdf,true),'logTitle'=>'良无印发货订单'.$sdf['order_bn']);
                return $rs;
            }
        }

        //对来自48体系的订单做处理
        if($sdf['shop_type'] == 'shopex_b2c'){
            foreach($sdf['order_objects'] as $key=>$obj_val){
                foreach($obj_val['order_items'] as $k=>$item){
                    if($item['item_type'] == 'goods'){
                        $sdf['order_objects'][$key]['order_items'][$k]['item_type'] = 'product';
                    }
                }
            }
        }

        /*Log info*/
        $log = app::get('ome')->model('api_log');
        $logTitle = '订单创建接口[订单：'. $sdf['order_bn'] .']';
        $logInfo = '订单创建接口：<BR>';
        $logInfo .= '接收参数 $sdf 信息：' . var_export($sdf, true) . '<BR>';
        /*Log info*/

        $order_bn = $sdf['order_bn'];
        $rs = array('rsp'=>'fail','msg'=>'','data'=>array('tid'=>$order_bn),'logTitle'=>$logTitle,'logInfo'=>$logInfo);


        if (empty($sdf)||empty($order_bn)) return $rs;

        $node_id = $sdf['node_id'];
        $lastmodify = ome_func::date2time($sdf['lastmodify']);
        $sdf_ship_status = trim($sdf['ship_status']);

        //订单来源过滤
        $filter = array('node_id'=>$node_id);
        $c2c_shop_list = ome_shop_type::shop_list();
        $jingdong_type = ome_shop_type::jingdong_type();
        $responseObj = kernel::single('ome_rpc_response');
        $shop_info = $responseObj->filter($filter);
        $shop_id = $shop_info['shop_id'];
        $shop_name = $shop_info['name'];
        $shop_type = $shop_info['node_type'];

        $logInfo .= '前端店铺信息：' . var_export($shop_info, true) . '<BR>';

        if (isset($shop_info['rsp']) && $shop_info['rsp'] == 'fail'){
            $rs['msg'] = $shop_info['msg'];
            $logInfo .= '返回值为：' . var_export($rs['msg'], true) . '<BR>';
            $rs['logInfo'] = $logInfo;
            $rs['logTitle'] = $logTitle;
            return $rs;
        }

        if(in_array($shop_type,$jingdong_type)){
            //如果接受到的京东订单状态为取消(status为dead)或商户类型不是SOP
            $jingdong_shop_type = $shop_info['addon'];
            if(in_array($sdf['status'],array('close','dead')) || $jingdong_shop_type['type']!='SOP'){
                $rs['rsp'] = 'success';
                return $rs;
            }
        }

        //将前端支付状态为处理中的初始为已支付
        if($sdf['pay_status'] == '2'){
            $sdf['pay_status'] = '1';
        }

        //订单数据过滤
        $filter_rs = $this->filter($sdf,$shop_info);

        if($filter_rs['rsp'] == 'fail' || $sdf['order_from'] == 'omeapiplugin'){
            //$rs['rsp'] = 'success';
            $rs['msg'] = $filter_rs['msg'];
            $rs['logTitle'] = $logTitle;
            $logInfo .= '返回值为：' . var_export($filter_rs['msg'], true) . '<BR>';
            $rs['logInfo'] = $logInfo;
            return $rs;
        }

        //订单数据
        $oOrder = app::get('ome')->model('orders');
        $order_filter = array('order_bn'=>$order_bn,'shop_id'=>$shop_id);
        $orders = $oOrder->getRow($order_filter);
        $outer_lastmodify = $orders['outer_lastmodify'];

        #淘宝sdf结构预处理
        if('taobao' == $sdf['shop_type']){
            $result = kernel::single('ome_service_c2c_taobao_order')->pre_tbfx_order($sdf,$shop_info['addon']);
            if($result['rsp'] == 'fail'){
                $rs['rsp'] = $result['rsp'];
                $rs['msg'] = $result['msg'];
                $rs['logTitle'] = $logTitle;
                $logInfo .= '返回值为：' . var_export($result['msg'], true) . '<BR>';
                $rs['logInfo'] = $logInfo;
                return $rs;
            }
        }

        if (empty($orders)){#订单添加
            //拒绝已发货订单
            if ($sdf_ship_status != '0'){
                $msg = '订单已发货';
                $rs['msg'] = $msg;
                $logInfo .= '返回值为：' . var_export($rs['msg'], true) . '<BR>';
                $rs['logInfo'] = $logInfo;
                return $rs;
            }

            $add_rs = $this->add($sdf,$logInfo);
            $rs['rsp'] = $add_rs['rsp'];
            $rs['msg'] = $add_rs['msg'];
            $rs['logTitle'] = $logTitle;
            $logInfo = $add_rs['logInfo'];
            $rs['logInfo'] = $logInfo;
            return $rs;
        }elseif($lastmodify > $outer_lastmodify || in_array($shop_type,$jingdong_type) || (($lastmodify == $outer_lastmodify) && $shop_type == 'paipai')){#订单更新

            $logTitle = '订单更新接口[订单：'. $sdf['order_bn'] .']';
            $logInfo = '订单更新接口：<BR>';
            $logInfo .= '接收参数 $sdf 信息：' . var_export($sdf, true) . '<BR>';
            $logInfo .= '前端店铺信息：' . var_export($shop_info, true) . '<BR>';

            $order_update_sdf = array();#待更新的订单字段
            $rs_refund_apply = false;//是否生成退款申请单 默认是:否
            $is_reback = false;//是否校回发货单 目前针对淘分销
            $order_id = $orders['order_id'];
            $filter = array('order_id'=>$order_id);
            $process_status = $orders['process_status'];
            $status = $orders['status'];
            $pay_status = $orders['pay_status'];
            $ship_status = $orders['ship_status'];
            $mark_text = $orders['mark_text'];
            $custom_mark = $orders['custom_mark'];
            $mark_type = $orders['mark_type'];
            $auto_status = $orders['auto_status'];
            $oLog = app::get('ome')->model('operation_log');
            $oFunc = kernel::single('ome_order_func');
            $oMemberFunc = kernel::single('ome_member_func');
            $omefunc = kernel::single('ome_func');
            $b2b_shop_list = ome_shop_type::b2b_shop_list();

            if(in_array($shop_type,$jingdong_type)){
                #更新订单备注
                if($mark=$oFunc->update_mark($order_id,$sdf['mark_text'],$shop_name,$mark_text,false)){
                    $order_update_sdf['mark_text'] = serialize($mark);
                    $oLog->write_log('order_edit@ome',$order_id,'修改订单备注');
                }

                if ($order_update_sdf){
                    $oOrder->update($order_update_sdf,$filter);
                }
            }elseif((in_array($shop_type,$c2c_shop_list) && $sdf['t_type']!='fenxiao') && !in_array($shop_type,$jingdong_type) || ($sdf['t_type']!='fenxiao' && $shop_type == 'taobao')){

                #更新订单备注
                if($mark=$oFunc->update_mark($order_id,$sdf['mark_text'],$shop_name,$mark_text,false)){
                    $order_update_sdf['mark_text'] = serialize($mark);
                    $oLog->write_log('order_edit@ome',$order_id,'修改订单备注');
                }
                #更新买家留言
                if($custom=$oFunc->update_message($order_id,$sdf['custom_mark'],$shop_name,$custom_mark,false)){
                    $order_update_sdf['custom_mark'] = serialize($custom);
                    $oLog->write_log('order_edit@ome',$order_id,'修改买家留言');
                }
                #更新订单旗标
                if (trim($sdf['mark_type']) != trim($mark_type)){
                    $order_update_sdf['mark_type'] = $sdf['mark_type'];
                    $oLog->write_log('order_edit@ome',$order_id,'修改订单旗标');
                }

                //更新淘宝订单超卖
                if($shop_type == 'taobao'){
                    $has_oversold = false;
                    $orderObjectObj = app::get('ome')->model('order_objects');
                    foreach($sdf['order_objects'] as $key=>$obj_val){
                        if($obj_val['is_oversold'] == true){
                            $orderObjectObj->update(array('is_oversold'=>1),array('shop_goods_id'=>$obj_val['shop_goods_id'],'order_id'=>$order_id));
                            $has_oversold = true;
                        }
                    }

                    if($has_oversold){
                        $order_update_sdf['auto_status'] = $auto_status | omeauto_auto_const::__OVERSOLD_CODE;
                    }
                }

                if ($order_update_sdf){
                    $oOrder->update($order_update_sdf,$filter);
                }

            }else{

                $pay_status_list = array('0','1','2','3','4','5','6','7','8');
                if($process_status == 'cancel' || $status == 'dead'){
                    #TG订单已取消或作废:更新订单支付状态和已支付金额
                    if (in_array($sdf['pay_status'],$pay_status_list) && $sdf['pay_status'] != $orders['pay_status']){
                        $order_update_sdf['pay_status'] = $sdf['pay_status'];
                    }
                    if ($sdf['payed'] != $orders['payed']){
                        $order_update_sdf['payed'] = $sdf['payed'];
                    }
                    if ($order_update_sdf){
                        $oOrder->update($order_update_sdf,$filter);
                        $oLog->write_log('order_edit@ome',$order_id,"前端店铺订单更新");
                    }
                    $rs['rsp'] = 'success';
                    $logInfo .= '返回值为：成功 <BR>';
                    $rs['logInfo'] = $logInfo;
                    return $rs;
                }elseif($sdf['status'] == 'dead'){
                    #前端订单作废,TG订单未支付且未发货:则取消订单
                    $dead_rs['rsp'] = 'fail';
                    if($pay_status == '0' && $ship_status == '0'){
                        $memo = '前端店铺:'.$shop_name.'订单作废';
                        $dead_rs = $oOrder->cancel($order_id,$memo,true,'async', false);
                    }
                    if ($dead_rs['rsp'] == 'fail'){
                        $rs['msg'] = '订单已发货无法取消或者取消失败';
                        $logInfo .= '返回值为：' . var_export($msg, true) . '<BR>';
                        $rs['logInfo'] = $logInfo;
                        return $rs;
                    }else{
                        $rs['rsp'] = 'success';
                        $logInfo .= '返回值为：成功 <BR>';
                        $rs['logInfo'] = $logInfo;
                        return $rs;
                    }
                }else{
                    $is_change_order = false;
                    #更新订单备注
                    if($mark=$oFunc->update_mark($order_id,$sdf['mark_text'],$shop_name,$mark_text,false)){
                        $order_update_sdf['mark_text'] = serialize($mark);
                        $oLog->write_log('order_edit@ome',$order_id,'修改订单备注');
                    }
                    #更新买家留言
                    if($custom=$oFunc->update_message($order_id,$sdf['custom_mark'],$shop_name,$custom_mark,false)){
                        $order_update_sdf['custom_mark'] = serialize($custom);
                        $oLog->write_log('order_edit@ome',$order_id,'修改买家留言');
                    }
                    #更新订单旗标
                    if (trim($sdf['mark_type']) != trim($mark_type)){
                        $order_update_sdf['mark_type'] = $sdf['mark_type'];
                        $oLog->write_log('order_edit@ome',$order_id,'修改订单旗标');
                    }

                    #更新订单优惠方案
                    $pmt_addon = $orders['createtime'];
                    $pmt_descript = $oFunc->update_pmt($order_id,$shop_id,$sdf['pmt_detail'],$pmt_addon,$old_pmt);
                    $orders['pmt'] = $old_pmt;

                    if ($pmt_descript){
                        $is_change_order = true;
                        $oLog->write_log('order_edit@ome',$order_id,'修改订单优惠方案');
                    }

                    #更新会员信息
                    $new_member_id = $oMemberFunc->save($sdf['member_info'],$shop_id,$orders['member_id'],$old_member);
                    $orders['mem_info'] = $old_member;
                    if ($new_member_id != $orders['member_id']){
                        $order_update_sdf['member_id'] = $new_member_id;
                        $orders['member_info'] = $old_member;
                        $is_change_order = true;
                        $oLog->write_log('order_edit@ome',$order_id,'修改订单会员信息');
                    }

                    #更新收货人信息
                    if($consignee_update=$oFunc->update_consignee($order_id,$sdf['consignee'],$orders,$is_update=false)){
                        $tmp = array('unconfirmed','confirmed','splitting','splited');
                        if (in_array($process_status,$tmp) && $ship_status == '0'){
                            $order_update_sdf = array_merge($order_update_sdf,$consignee_update);
                            $oLog->write_log('order_edit@ome',$order_id,'修改收货人信息');

                            #针对淘分销订单处理
                            $result = kernel::single('ome_service_shopex_b2b_order')->rebackdelivery($orders,$is_reback,$sdf['order_source']);

                            $order_update_sdf['confirm'] = $result['confirm'];
                            $order_update_sdf['process_status'] = $result['process_status'];

                        }
                        if($process_status == 'confirmed'){
                            $order_update_sdf['confirm'] = 'N';
                            $order_update_sdf['process_status'] = 'unconfirmed';
                        }

                        $is_change_order = true;
                    }

                    if (in_array($shop_type,$b2b_shop_list)){
                        #更新发货人信息
                        if($consigner_update=$oFunc->update_consigner($order_id,$sdf['consigner'],$orders,$is_update=false)){
                            $order_update_sdf = array_merge($order_update_sdf,$consigner_update);
                            $oLog->write_log('order_edit@ome',$order_id,'修改发货人信息');
                            $is_change_order = true;
                        }

                        #更新代销人信息
                        $sellagent_update=$oFunc->update_sellagent($order_id,$sdf['selling_agent']);
                        $orders['agent'] = $old_agent;
                        if($sellagent_update){
                            $oLog->write_log('order_edit@ome',$order_id,'修改代销人信息');
                            $is_change_order = true;
                        }
                    }

                    #更新订单失效时间
                    if (!empty($sdf['order_limit_time'])){
                        $oLimitTime = $omefunc->date2time($sdf['order_limit_time']);
                        if ($oLimitTime != $orders['order_limit_time'] && $pay_status == '0'){
                            $order_update_sdf['order_limit_time'] = $oLimitTime;
                            $oLog->write_log('order_edit@ome',$order_id,'修改订单失效时间');
                            $is_change_order = true;
                        }
                    }

                    #更新订单商品明细
                    if (in_array($process_status,array('unconfirmed','confirmed')) || ($ship_status == '0' && $sdf['order_source'] == 'taofenxiao') ){

                        #TG订单商品明细
                        $noexists_product = false;
                        #临时处理办法：将老订单上的商品明细bn是空的删了(清空预占库存).
                        if($shop_type == 'shopex_b2b'){
                            kernel::single('ome_service_shopex_b2b_order')->order_objects($order_id);
                        }
                        $orders['order_objects'] = $oOrder->order_objects($order_id);

                        $this->update_items($order_id,$sdf,$orders,$is_change_goods,$noexists_product);

                    }else{
                        $proc_status = $oOrder->_columns();
                        $logInfo .= '返回值为：订单确认状态处于'.$proc_status['process_status']['type'][$process_status].'状态,商品明细不需要更新. <BR>';
                    }

                    #商品不存在,更新订单状态为失败
                    if ($noexists_product == true){
                        $order_update_sdf['is_fail'] = 'true';
                        $order_update_sdf['edit_status'] = 'true';
                        $order_update_sdf['archive'] = 1;
                    }

                    #订单商品发生变化,更改订单确认状态为未确认
                    if ($is_change_goods == true){

                        #针对淘分销订单处理
                        if(!$is_reback && $sdf['order_source'] == 'taofenxiao'){
                            kernel::single('ome_service_shopex_b2b_order')->rebackdelivery($orders,$is_reback,'taofenxiao');
                        }

                        $order_update_sdf['confirm'] = 'N';
                        $order_update_sdf['process_status'] = 'unconfirmed';
                        $is_change_order = true;
                        $oLog->write_log('order_edit@ome',$order_id,'订单商品信息被修改');
                    }
                    #追加支付单
                    $add_payments = $this->add_payment($order_id,$sdf['payments'],$old_payments);
                    $orders['payments'] = $old_payments;
                    if ($add_payments){
                        $orders['payments'] = $old_payments;
                        $is_change_order = true;
                        $oLog->write_log('order_pay@ome',$order_id,'支付单添加');
                    }

                    #退款申请
                    $rs_refund_apply = $this->refund_apply($order_id,$sdf,$orders);
                    if ($rs_refund_apply){
                        $is_change_order = true;
                        $oLog->write_log('order_edit@ome',$order_id,'退款申请');
                    }

                    #---------------------更新订单主表的相关字段------------------------
                    #前端订单与TG订单的字段隐射关系,用于比对是否有变化
                    $sdf2orders_map = array(
                        'pay_status' => 'pay_status',
                        'discount' => 'discount',
                        'pmt_goods' => 'pmt_goods',
                        'pmt_order' => 'pmt_order',
                        'total_amount' => 'total_amount',
                        'cur_amount' => 'final_amount',
                        'payed' => 'payed',
                        'cost_item' => 'cost_item',
                        'is_tax' => 'is_tax',
                        'tax_no' => 'tax_no',
                        'cost_tax' => 'cost_tax',
                        'tax_title' => 'tax_company',
                        'shipping/shipping_name' => 'shipping',
                        'shipping/cost_shipping' => 'cost_freight',
                        'shipping/is_protect' => 'is_protect',
                        'shipping/cost_protect' => 'cost_protect',
                        'shipping/is_cod' => 'is_cod',
                        'payinfo/pay_name' => 'payment',
                        'payinfo/cost_payment' => 'cost_payment',
                        'weight' => 'weight',
                        'title' => 'tostr',
                        'score_u' => 'score_u',
                        'score_g' => 'score_g',
                    );
                    $sdf['lastmodify'] = $omefunc->date2time($sdf['lastmodify']);

                    foreach ($sdf2orders_map as $sdf_field=>$order_field){
                        $tmp = explode('/',$sdf_field);
                        if (count($tmp)>1){
                            $compre_value = trim($sdf[$tmp[0]][$tmp[1]]);
                            if (empty($compre_value)) continue;
                            if ($compre_value != $orders[$order_field]){
                                $order_update_sdf[$order_field] = $compre_value;
                                $is_change_order = true;
                            }
                        }else{
                            $compre_value = trim($sdf[$sdf_field]);
                            if (empty($compre_value)) continue;
                            if ($compre_value != $orders[$order_field]){
                                $order_update_sdf[$order_field] = $compre_value;
                                $is_change_order = true;
                            }
                        }
                    }

                    #更新订单最后修改时间
                    $order_update_sdf = array_merge($order_update_sdf,array('outer_lastmodify'=>$sdf['lastmodify']));

                    #更新订单支付状态为退款申请中
                    if ($rs_refund_apply == true){
                        $order_update_sdf['pay_status'] = '6';
                    }

                    #更新订单相关字段
                    if ($order_update_sdf){
                        if (isset($order_update_sdf['pay_bn'])){
                            $cfg = $this->get_payment($order_update_sdf['pay_bn'],$shop_type);
                            $order_update_sdf['pay_bn'] = $cfg['pay_bn'];
                            $order_update_sdf['payment'] = $cfg['custom_name'];
                        }

                        $order_update = false;
                        if($oOrder->update($order_update_sdf,$filter)){
                            $order_update = true;
                            // SCRUM-7468：更新为失败订单时写入进入快照
                            if (!empty($order_update_sdf['is_fail']) && $order_update_sdf['is_fail'] === 'true') {
                                try {
                                    kernel::single('monitor_event_orderfail')->recordEnterByOrderId($order_id);
                                } catch (Throwable $e) {
                                }
                            }
                        }
                    }

                    #生成快照
                    if ($is_change_order == true){
                        $log_id = $oLog->write_log('order_edit@ome',$order_id,"前端店铺订单更新");
                        $orders['shipping'] = array(
                            'shipping_name' => $orders['shipping'],
                            'cost_shipping' => $orders['cost_freight'],
                            'is_protect' => $orders['is_protect'],
                            'cost_protect' => $orders['cost_protect'],
                            'is_cod' => $orders['is_cod'],
                        );
                        $orders['payinfo'] = array(
                            'pay_name' => $orders['payment'],
                            'cost_payment' => $orders['cost_payment'],
                        );
                        $orders['consignee'] = array(
                            'name' => $orders['ship_name'],
                            'area' => $orders['ship_area'],
                            'addr' => $orders['ship_addr'],
                            'zip' => $orders['ship_zip'],
                            'telephone' => $orders['ship_tel'],
                            'email' => $orders['ship_email'],
                            'r_time' => $orders['ship_time'],
                            'mobile' => $orders['ship_mobile'],
                        );
                        $orders['consigner'] = array(
                            'name' => $orders['consigner_name'],
                            'area' => $orders['consigner_area'],
                            'addr' => $orders['consigner_addr'],
                            'zip' => $orders['consigner_zip'],
                            'tel' => $orders['consigner_tel'],
                            'email' => $orders['consigner_email'],
                            'mobile' => $orders['consigner_mobile'],
                        );
                        $orders['tax_title'] = $orders['tax_company'];
                        $orders['cur_amount'] = $orders['final_amount'];
                        $orders['title'] = $orders['tostr'];

                        $oOrder->write_log_detail($log_id,$orders);
                    }

                    #更新订单编辑同步状态:成功
                    $oOrder_sync = app::get('ome')->model('order_sync_status');
                    if ($oOrder_sync->count(array('order_id'=>$order_id))){
                        $oOrder_sync->update(array('sync_status'=>'2'),array('order_id'=>$order_id));
                    }
                }

                //更新订单支付状态
                $msg = $this->update_order_pay_status($order_id,$orders['pay_status'],$order_update);

                $logInfo .= '返回信息为：'.$msg.' <BR>';

            }
        }else{
            $logTitle = '订单更新接口[订单：'. $sdf['order_bn'] .']';
            $logInfo .= '返回值为：订单已存在不需要更新<BR>';
        }

        $rs['rsp'] = 'success';
        $rs['logTitle'] = $logTitle;
        $rs['logInfo'] = $logInfo;
        return $rs;
    }

    /**
     * 修改本地暂停状态
     *      根据前端支付状态和本地原始的支付状态 来决定暂停状态
     * @params order_id 订单ID
     * @params local_pay_status 本地原始的支付状态
     * @params order_update 本地订单是否更新成功
     * @return logInfo
     * @author
     **/
    private function update_order_pay_status( $order_id = NULL,$local_pay_status = NULL ,$order_update = false){

        if( empty($order_id) || empty($local_pay_status) || ($order_update == false) ) return '订单暂停状态不需要更新。';

        $orderObj = app::get('ome')->model('orders');
        $oLog = app::get('ome')->model('operation_log');
        $order_filter = array('order_id'=>$order_id);
        $order_detail = $orderObj->getList('order_bn,pay_status',$order_filter,0,1);
        $order_bn = $order_detail[0]['order_bn'];
        $pay_status = $order_detail[0]['pay_status'];
        $logInfo = '订单更新成功。';

        $is_order_update = false;


        if( $local_pay_status != $pay_status ){

            if($pay_status == '6'){
                $is_order_update = true;
                $data['pause'] = 'true';
                $logInfo .= '退款申请中 将订单置为暂停  其余的不暂停 信息：<BR>' . var_export($order_filter, true) . var_export($data, true) . '<BR>';
            }

            if($pay_status == '7'){
                $is_order_update = true;
                $oLog->write_log('order_edit@ome',$order_id,'退款中');
                $data['pause'] = 'true';
                $logInfo .= '退款中 将订单置为暂停  其余的不暂停 信息：<BR>' . var_export($order_filter, true) . var_export($data, true) . '<BR>';

            }

            if( in_array($local_pay_status, array('6','7')) && in_array($pay_status, array('1','3','4','5')) ){
                $is_order_update = true;
                $data['pause'] = 'false';
                if($pay_status == '4'){
                    //部分退款并且未发货的发货单打回
                    $deoObj = app::get('ome')->model('delivery_order');
                    $ids_tmp = $deoObj->getList('delivery_id',$order_filter);
                    $ids = array();
                    foreach ((array)($ids_tmp) as $k=>$v){
                        $ids[] = $v['delivery_id'];
                    }
                    if (!empty($ids)){
                        $dObj = app::get('ome')->model('delivery');
                        $dObj->rebackDelivery($ids,'',true, false);

                        $logInfo .= '部分退款并且未发货的发货单打回信息：<BR>' . var_export($ids, true) . '<BR>';
                    }
                }elseif($pay_status == '5'){
                    define('FRST_TRIGGER_OBJECT_TYPE','订单：未发货订单全额退款导致订单取消');
                    define('FRST_TRIGGER_ACTION_TYPE','ome_order_func：update_order_pay_status');
                    //全额退款并且未发货的订单取消
                    $refund_applyObj = app::get('ome')->model('refund_apply');
                    $refund_applyObj->check_iscancel($order_id, '', false);
                    $oLog->write_log('order_edit@ome',$order_id,'全额退款');
                    $logInfo .= '全额退款并且未发货的取消订单：'. $order_bn . '<BR>';
                }elseif($pay_status == '1'){
                    $is_order_update = true;
                    $data['pause'] = 'false';
                    $data['pay_status'] = '1';
                    $logInfo .= '将订单付款状态置为已支付';
                }
            }

        }


        if($is_order_update){
            if (!$orderObj->update($data, $order_filter)){
                return '订单暂停状态更新失败';
            }
        }


        return $logInfo;
    }

    /**
    * 添加支付单
    * @access public
    * @param Number $order_id 订单ID
    * @param Array $payments 前端订单支付单数据
    * @return
    */
    public function add_payment($order_id,$payments,&$pay_list=array()){
        if (empty($order_id) || empty($payments)) return false;

        $oPayment = app::get('ome')->model('payments');
        $oOrders = app::get('ome')->model('orders');
        $omefunc = kernel::single('ome_func');
        $order_row = $oOrders->dump(array('order_id'=>$order_id),'shop_id');
        $shop_id = $order_row['shop_id'];

        $pay_list = $oPayment->getList('payment_bn',array('order_id'=>$order_id));
        $pay_bn_arr = array();
        if ($pay_list){
            foreach ($pay_list as $pay){
                $pay_bn_arr[] = $pay['payment_bn'];
            }
        }

        $is_change_payment = false;
        foreach ($payments as $pay){

            $payment_bn = $pay['trade_no'];
            if (!in_array($payment_bn,$pay_bn_arr)){
                $pay_bn = $pay['pay_bn'];
                if ($pay_bn){
                    $payment_cfgObj = app::get('ome')->model('payment_cfg');
                    $payment_cfg = $payment_cfgObj->getList('id,pay_type',array('pay_bn'=>$pay_bn),0,1);
                    $pay['payment'] = $payment_cfg[0]['id'];
                    $pay['pay_type'] = $payment_cfg[0]['pay_type'];
                }

                $pay['pay_time'] = $omefunc->date2time($pay['pay_time']);
                $payment_sdf = array(
                    'payment_bn' => $payment_bn,
                    'shop_id' => $shop_id,
                    'order_id' => $order_id,
                    'account' => $pay['account'],
                    'bank' => $pay['bank'],
                    'pay_account' => $pay['pay_account'],
                    'money' => $pay['money']?$pay['money']:'0',
                    'paycost' => $pay['paycost'],
                    'payment' => $pay['payment'],
                    'pay_bn' => $pay_bn,
                    'pay_type' => $pay['pay_type'],
                    'paymethod' => $pay['paymethod'],
                    't_begin' => $pay['pay_time'] ? $pay['pay_time'] : time(),
                    'download_time' => time(),
                    't_end' => $pay['pay_time'] ? $pay['pay_time'] : time(),
                    'status' => 'succ',
                    'memo' => $pay['memo'],
                    'trade_no' => $pay['trade_no'],
                    'op_id' => $pay['op_id'] ? $pay['op_id'] : '',
                    'op_name' => $pay['op_name'] ? $pay['op_name'] : $order_row['shop_type'],
                );
                $oPayment->insert($payment_sdf);
                $is_change_payment = true;
                $oOrders->update(array('paytime'=>$payment_sdf['t_begin']),array('order_id'=>$order_id));
            }
        }
        return $is_change_payment;
    }

    /*退款申请
    * @access public
    * @param Number $order_id 订单ID
    * @param Array $sdf 前端订单信息
    * @param Array $orders TG订单信息
    * @return
    */
    public function refund_apply($order_id,&$sdf,&$orders){
        if (empty($order_id) || empty($sdf) || empty($orders) || $sdf['order_source'] =='taofenxiao') return false;

        $refund_money = 0;
        if ($sdf['payed'] > $sdf['total_amount']){
            $refund_money = $sdf['payed'] - $sdf['total_amount'];
        }
        if ($refund_money > 0){
            $refund_applyObj = app::get('ome')->model('refund_apply');
            $create_time = ome_func::date2time($sdf['lastmodify']);
            $refund_apply_sdf = array(
                'order_id' => $order_id,
                'refund_apply_bn' => $refund_applyObj->gen_id(),
                'pay_type' => 'online',
                'money' => $refund_money,
                'refunded' => '0',
                'memo' => '订单编辑产生的退款申请',
                'create_time' => $create_time,
                'status' => '2',
                'shop_id' => $orders['shop_id'],
            );
            $refund_applyObj->create_refund_apply($refund_apply_sdf);

            return true;
        }
        return false;
    }

    /**
    * 更新订单商品明细
    * @access public
    * @param Number $order_id 订单ID
    * @param Array $sdf 前端订单数据
    * @param Array $orders TG订单数据
    * @param bool $is_change_goods 商品是否有变化
    * @param bool $noexists_product 商品是否存在
    * @return
    */
    public function update_items($order_id,$sdf=array(),&$orders,&$is_change_goods,&$noexists_product){
        if (empty($sdf)) return false;
        
        $oOrder = app::get('ome')->model('orders');
        $shop_id = $orders['shop_id'];

        if ($sdf['order_objects']){
            $before_objects = array();
            foreach($sdf['order_objects'] as $key=>$obj_val){
                $obj_bn = trim($obj_val['bn']);
                $obj_key = $obj_bn.'-'.$obj_val['obj_type'];
                if ($obj_val['order_items']){
                    $tmp_items_bn = array();
                    foreach ($obj_val['order_items'] as $item_key =>$item_val){
                        $item_bn = trim($item_val['bn']);
                        $tmp_price = $item_val['sale_price'] / $item_val['quantity'];//销售单价
                        $unqiue_key = $item_bn.$item_val['item_type'].$tmp_price;
                        $tmp_items_bn[] = $unqiue_key;

                        if(isset($obj_val['order_items'][$unqiue_key])){//price bn item_type相同的情况 将item上的数量累加
                            $obj_val['order_items'][$unqiue_key]['quantity'] += $item_val['quantity'];
                            $obj_val['order_items'][$unqiue_key]['pmt_price'] += $item_val['pmt_price'];
                            $obj_val['order_items'][$unqiue_key]['price'] += $item_val['price'];
                            $obj_val['order_items'][$unqiue_key]['amount'] += $item_val['amount'];
                            $obj_val['order_items'][$unqiue_key]['sale_price'] += $item_val['sale_price'];
                        }else{
                            if(isset($item_val['item_id'])){
                                unset($item_val['item_id']);
                            }

                            $obj_val['order_items'][$unqiue_key] = $item_val;
                        }
                        unset($obj_val['order_items'][$item_key]);
                    }
                }
                $sdf['order_objects'][$obj_key] = $obj_val;
                unset($sdf['order_objects'][$key]);
                $before_objects[$obj_key] = array_unique($tmp_items_bn);
            }
        }

        if ($orders['order_objects']){
            $old_objects = array();
            foreach($orders['order_objects'] as $obj_val){
                $obj_bn = trim($obj_val['bn']);
                $obj_key = $obj_bn.'-'.$obj_val['obj_type'];

                #如果前端没有传递删除的子订单,则将TG本地的子订单增加到前端的订单中
                $delete_obj = false;
                if (!isset($before_objects[$obj_key])){
                    $sdf['order_objects'][$obj_key] = $obj_val;
                    $delete_obj = true;
                }

                if ($obj_val['order_items']){
                    $tmp_order_items = array();
                    foreach ($obj_val['order_items'] as $ikey=>$item_val){
                        $item_bn = trim($item_val['bn']);
                        $tmp_price = $item_val['sale_price'] / $item_val['nums'];//销售单价
                        $item_key = $item_bn.$item_val['item_type'].$tmp_price;
                        $tmp_order_items[$item_key] = $item_val;

                        //前端没传的删除商品，规格直接读上次保存的信息
                        $item_val['addon'] = unserialize($item_val['addon']);

                        #前端没有传递删除的商品,则将TG本地的商品增加到前端的订单商品明细中,标记为删除

                        if (($delete_obj == false && !in_array($item_key,$before_objects[$obj_key])) || $delete_obj == true){
                            $item_val['status'] = 'close';
                            $sdf['order_objects'][$obj_key]['order_items'][$item_key] = $item_val;
                            if ($delete_obj == true){
                                unset($sdf['order_objects'][$obj_key]['order_items'][$ikey]);
                            }
                        }
                    }
                }
                $obj_val['order_items'] = $tmp_order_items;
                $old_objects[$obj_key] = $obj_val;
            }
        }

        $basicMaterialObj      = app::get('material')->model('basic_material');
        $basicMaterialStock    = kernel::single('material_basic_material_stock');
        $salesMaterialObj = app::get('material')->model('sales_material');

        $oItems = app::get('ome')->model('order_items');
        $oObjects = app::get('ome')->model('order_objects');
        
        $add_objects = array();#新增加的obj
        $add_items = array();#新增加的items
        $update_objects = array();#更新的obj
        $update_items = array();#更新的items
        $is_change_goods = false;
        $must_add_tbfxsdf = array();
        $must_update_tbfxsdf = array();

        #比对是否有变化的字段列表
        $compre_obj = array('oid','obj_type','obj_alias','name','price','amount','quantity','pmt_price','sale_price','weight','score');
        $compre_items = array('name','cost','price','amount','pmt_price','sale_price','weight','nums','item_type','delete','addon');

        $batchList = [];
        foreach ($sdf['order_objects'] as $obj_val){
            $obj_val['bn'] = trim($obj_val['bn']);
            $obj_key = $obj_val['bn'].'-'.$obj_val['obj_type'];
            if (isset($old_objects[$obj_key])){#obj存在
                $tg_obj_val = $old_objects[$obj_key];
                foreach ($obj_val['order_items'] as $items_val){
                    $items_val['bn'] = trim($items_val['bn']);
                    $tmp_quantity = $items_val['quantity'] ? $items_val['quantity'] : $items_val['nums'];
                    $tmp_price = $items_val['sale_price'] / $tmp_quantity;
                    $items_key = $items_val['bn'].$items_val['item_type'].$tmp_price;

                    #前端订单商品不在TG订单商品明细中
                    if (!isset($old_objects[$obj_key]['order_items'][$items_key])){
                        #前端订单商品存在于TG商品列表中
                        
                        $p    = $basicMaterialObj->dump(array('material_bn'=>$items_val['bn']), 'bm_id');#基础物料
                        
                        if ($p){
                            $product_id = $p['bm_id'];
                            $batchList['+'][] = [
                                'bm_id' =>  $product_id,
                                'num'   =>  intval($items_val['quantity']),
                            ];
                            $items_val['product_id'] = $product_id;
                        }else{
                            $noexists_product = true;
                        }
                        #将该商品增加到TG订单中
                        $items_val['order_id'] = $order_id;
                        $items_val['obj_id'] = $tg_obj_val['obj_id'];
                        $items_val['obj_price'] = $obj_val['price'];
                        $items_val['obj_pmt_price'] = $obj_val['pmt_price'];
                        $items_val['obj_amount'] = $obj_val['amount'];
                        $items_val['obj_sale_price'] = $obj_val['sale_price'];
                        $items_val['obj_quantity'] = $obj_val['quantity'];
                        #淘宝分销字段
                        #$items_val['fx_oid'] = $obj_val['fx_oid'];
                        $items_val['buyer_payment'] = $obj_val['buyer_payment'];
                        $items_val['cost_tax'] = $obj_val['cost_tax'];

                        $add_items[$tg_obj_val['obj_id']][]= $items_val;
                        $is_change_goods = true;
                    }else{
                        #TG订单商品明细
                        $tg_items = $old_objects[$obj_key]['order_items'][$items_key];
                        $tg_nums = $tg_items['nums'];
                        $tg_status = $tg_items['delete'];
                        $tg_product_id = $tg_items['product_id'];
                        $tg_item_id = $tg_items['item_id'];
                        if (isset($items_val['status']) && $items_val['status'] == 'close'){
                            $items_val['status'] = 'close';
                        }else{
                            $items_val['status'] = 'active';
                        }

                        #商品数量差异量 = 前端订单商品数量-TG订单商品数量
                        $diff_nums = $items_val['quantity']-$tg_nums;

                        if ($items_val['status'] == 'close' && $tg_status == 'false'){
                            #前端订单商品状态是删除且TG订单商品状态非删除
                            if ($orders['is_fail'] == 'true'){
                                if ($tg_product_id > 0){
                                    $batchList['-'][] = [
                                        'bm_id' =>  $tg_product_id,
                                        'num'   =>  $tg_nums,
                                    ];
                                }
                            }else{
                                $batchList['-'][] = [
                                    'bm_id' =>  $tg_product_id,
                                    'num'   =>  $tg_nums,
                                ];
                            }
                        }elseif ($items_val['status'] == 'active' && $tg_status == 'true'){
                            #前端订单商品状态是正常且TG订单商品状态删除
                            if ($orders['is_fail'] == 'true'){
                                if ($tg_product_id > 0){
                                    $batchList['+'][] = [
                                        'bm_id' =>  $tg_product_id,
                                        'num'   =>  $items_val['quantity'],
                                    ];
                                }
                            }else{
                                $batchList['+'][] = [
                                    'bm_id' =>  $tg_product_id,
                                    'num'   =>  $items_val['quantity'],
                                ];
                            }
                        }elseif ($items_val['status'] == 'active' && $tg_status == 'false'){
                            #前端订单商品状态正常且TG订单商品状态正常
                            if ($orders['is_fail'] == 'true'){
                                if ($tg_product_id > 0 && $diff_nums != 0){
                                    if ($diff_nums > 0){
                                        $batchList['+'][] = [
                                            'bm_id' =>  $tg_product_id,
                                            'num'   =>  $diff_nums,
                                        ];
                                    }else{
                                        $batchList['-'][] = [
                                            'bm_id' =>  $tg_product_id,
                                            'num'   =>  abs($diff_nums),
                                        ];
                                    }
                                }
                            }else{
                                if ($diff_nums != 0){
                                    if ($diff_nums > 0){
                                        $batchList['+'][] = [
                                            'bm_id' =>  $tg_product_id,
                                            'num'   =>  $diff_nums,
                                        ];
                                    }else{
                                        $batchList['-'][] = [
                                            'bm_id' =>  $tg_product_id,
                                            'num'   =>  abs($diff_nums),
                                        ];
                                    }
                                }
                            }
                        }

                        #判断items是否有变化
                        $items_val['nums'] = $items_val['quantity'];

                        if ($items_val['status'] == 'close'){
                            $items_val['delete'] = 'true';
                        }else{
                            $items_val['delete'] = 'false';
                        }

                        $items_val['addon'] = $items_val['addon'] ? serialize($items_val['addon']) : $oOrder->_format_productattr($items_val['product_attr'],$tg_product_id,$items_val['original_str']);

                        $tmp_items_update_sdf = array();
                        foreach ($compre_items as $compre_field){
                            $compre_value = isset($items_val[$compre_field]) && !is_array($items_val[$compre_field]) ? trim($items_val[$compre_field]) : $items_val[$compre_field];
                            if (empty($compre_value)) continue;
                            if ($items_val[$compre_field] != $tg_items[$compre_field]){
                                $tmp_items_update_sdf[$compre_field] = $items_val[$compre_field];
                                $tmp_items_update_sdf['item_id'] = $tg_item_id;
                                if (in_array($compre_field,array('nums','price','pmt_price'))){
                                    $tmp_items_update_sdf['obj_price'] = $obj_val['price'];
                                    $tmp_items_update_sdf['obj_pmt_price'] = $obj_val['pmt_price'];
                                    $tmp_items_update_sdf['obj_amount'] = $obj_val['amount'];
                                    $tmp_items_update_sdf['obj_sale_price'] = $obj_val['sale_price'];
                                    $tmp_items_update_sdf['obj_quantity'] = $obj_val['quantity'];
                                    $tmp_items_update_sdf['price'] = $items_val['price'];
                                    $tmp_items_update_sdf['sale_price'] = $items_val['sale_price'];
                                    $tmp_items_update_sdf['nums'] = $items_val['nums'];
                                    $tmp_items_update_sdf['pmt_price'] = $items_val['pmt_price'];
                                }
                            }
                        }
                        if ($tmp_items_update_sdf){

                            $tmp_items_update_sdf['fx_oid'] = $items_val['fx_oid'];
                            $tmp_items_update_sdf['buyer_payment'] = $items_val['buyer_payment'];
                            $tmp_items_update_sdf['cost_tax'] = $items_val['cost_tax'];

                            $update_items[$tg_obj_val['obj_id']][] = $tmp_items_update_sdf;
                            $is_change_goods = true;
                        }
                    }
                }

                #判断obj是否有变化
                $tmp_obj_update_sdf = array();
                foreach ($compre_obj as $compre_field){
                    $compre_value = trim($obj_val[$compre_field]);
                    $orderobj_columns = $oObjects->_columns();
                    if (empty($compre_value) || !in_array($compre_field,array_keys($orderobj_columns)) ) continue;
                    if ($obj_val[$compre_field] != $old_objects[$obj_key][$compre_field]){
                        $tmp_obj_update_sdf[$compre_field] = $obj_val[$compre_field];
                    }
                }
                if ($tmp_obj_update_sdf){

                    $tmp_obj_update_sdf['fx_oid'] = $obj_val['fx_oid'];
                    $tmp_obj_update_sdf['buyer_payment'] = $obj_val['buyer_payment'];
                    $tmp_obj_update_sdf['cost_tax'] = $obj_val['cost_tax'];

                    $update_objects[$tg_obj_val['obj_id']] = $tmp_obj_update_sdf;
                    $is_change_goods = true;
                }
            }else{#obj不存在
                #新增加的obj商品明细
                $T_pmtPrice = $tmp_obj_price = 0;
                $item_status = 'false';
                foreach ($obj_val['order_items'] as &$add_items_val){
                    $add_items_val['order_id'] = $order_id;
                    $bn = trim($add_items_val['bn']);
                    
                    $p    = $basicMaterialObj->dump(array('material_bn'=>$bn), 'bm_id');#基础物料
                    
                    if ($p){
                        #前端订单商品存在于TG商品列表中
                        $product_id = $p['bm_id'];
                        $batchList['+'][] = [
                            'bm_id' =>  $product_id,
                            'num'   =>  $add_items_val['quantity'],
                        ];
                        $add_items_val['product_id'] = $product_id;
                    }else{
                        $add_items_val['product_id'] = 0;
                        $noexists_product = true;
                    }
                    $product_attr['product_attr'] = $add_items_val['product_attr'];
                    $add_items_val['addon'] = serialize($product_attr);
                    if ($add_items_val['status'] == 'close' ){
                        $item_status = 'true';
                    }
                    $add_items_val['delete'] = $item_status;
                    $add_items_val['item_type'] = $add_items_val['item_type']?$add_items_val['item_type']:'product';

                    //items金额相关字段
                    $add_items_val['price'] = $add_items_val['price'] ? $add_items_val['price'] : 0.00;
                    $add_items_val['pmt_price'] = $add_items_val['pmt_price'] ? $add_items_val['pmt_price'] : 0.00;
                    $add_items_val['amount'] = $add_items_val['amount'] ? $add_items_val['amount'] : $add_items_val['quantity']*$add_items_val['price'];
                    $add_items_val['sale_price'] = trim($add_items_val['sale_price']);

                    if(empty($add_items_val['sale_price'])){
                        $add_items_val['sale_price'] = $add_items_val['quantity'] * $add_items_val['price'] - $add_items_val['pmt_price'];
                    }


                    $T_pmtPrice += $add_items_val['pmt_price'];

                }
                $obj_val['order_id'] = $order_id;

                if($obj_val['bn'])
                {
                    $goods_info    = $salesMaterialObj->dump(array('sales_material_bn'=>$obj_val['bn']), 'sm_id');
                    if($goods_info){
                        $obj_val['goods_id'] = $goods_info['sm_id'];
                    }
                }
                $obj_val['oid'] = sprintf('%.0f',$obj_val['oid']);
                $obj_val['obj_type'] = $obj_val['obj_type']?$obj_val['obj_type']:'goods';

                //objects金额相关字段

                $obj_val['pmt_price'] = $obj_val['pmt_price'] ? $obj_val['pmt_price'] : 0.00;
                $obj_val['amount'] = $obj_val['amount'] ? $obj_val['amount'] : $obj_val['quantity'] * $obj_val['price'];

                $obj_val['price'] = $obj_val['amount'] / $obj_val['quantity'];   //针对B2B obj price传0情况调整
                $obj_val['price'] = $obj_val['price'] ? $obj_val['price'] : 0.00;

                $obj_val['sale_price'] = trim($obj_val['sale_price']);
                if(empty($obj_val['sale_price'])){
                    $obj_val['sale_price'] = $obj_val['amount'] - $obj_val['pmt_price'] - $T_pmtPrice;
                }

                $obj_val['cost_tax'] = $obj_val['cost_tax']?$obj_val['cost_tax']:0.00;
                $obj_val['buyer_payment'] = $obj_val['buyer_payment']?$obj_val['buyer_payment']:0.00;
                $obj_val['fx_oid'] = $obj_val['fx_oid']?$obj_val['fx_oid']:'0';

                $add_objects[] = $obj_val;
                $is_change_goods = true;
            }
        }

        $basicMaterialStock->chg_product_store_freeze_batch($batchList['+'], '+', __CLASS__.'::'.__FUNCTION__);
        $basicMaterialStock->chg_product_store_freeze_batch($batchList['-'], '-', __CLASS__.'::'.__FUNCTION__);

        #保存新增加的obj
        if ($add_objects){

            foreach ($add_objects as $insert){
                $oObjects->save($insert);

                $tbobj['order_id'] = $order_id;
                $tbobj['obj_id'] = $insert['obj_id'];
                $tbobj['fx_oid'] = $insert['fx_oid'];
                $tbobj['buyer_payment'] = $insert['buyer_payment'];
                $tbobj['cost_tax'] = $insert['cost_tax'];

                $must_add_tbfxsdf['order_objects'][] = $tbobj;
            }
        }

        #保存有更新的obj
        if ($update_objects){
            foreach ($update_objects as $obj_id=>$update_obj_val){
                if (empty($update_obj_val)) continue;
                $oObjects->update($update_obj_val,array('obj_id'=>$obj_id));

                $tbobj['order_id'] = $order_id;
                $tbobj['obj_id'] = $obj_id;
                $tbobj['fx_oid'] = $update_obj_val['fx_oid'];
                $tbobj['buyer_payment'] = $update_obj_val['buyer_payment'];
                $tbobj['cost_tax'] = $update_obj_val['cost_tax'];

                $must_update_tbfxsdf['order_objects'][] = $tbobj;
            }
        }

        #保存新增加的items
        if ($add_items){
            foreach ($add_items as $obj_id=>$obj_items){
                $T_pmtPrice = $tmp_obj_price = 0;
                $tmp_obj_sdf = array();
                if (empty($obj_items)) continue;
                foreach($obj_items as $items){
                    unset($items['item_id']);
                    $items['nums'] = $items['quantity'];
                    if ($items['status'] == 'close'){
                        $items['delete'] = 'true';
                    }else{
                        $items['delete'] = 'false';
                    }
                    $product_attr['product_attr'] = $items['product_attr'];
                    $items['addon'] = $oOrder->_format_productattr($product_attr['product_attr'],$items['product_id'],$items['original_str']);
                    $items['item_type'] = $items['item_type']?$items['item_type']:'product';

                    //items金额相关字段
                    $items['price'] = $items['price'] ? $items['price'] : 0.00;
                    $items['pmt_price'] = $items['pmt_price'] ? $items['pmt_price'] : 0.00;
                    $items['amount'] = $items['amount'] ? $items['amount'] : $items['quantity']*$items['price'];

                    $items['sale_price'] = trim($items['sale_price']);

                    if(empty($items['sale_price'])){
                        $items['sale_price'] = $items['quantity'] * $items['price'] - $items['pmt_price'];
                    }


                    $T_pmtPrice += $items['pmt_price'];
                    $tmp_obj_price = $items['obj_price'];
                    $tmp_obj_pmt_price = $items['obj_pmt_price'];
                    $tmp_obj_amount = $items['obj_amount'];
                    $tmp_obj_sale_price = trim($items['obj_sale_price']);
                    $tmp_obj_quantity = $items['obj_quantity'];

                    $oItems->insert($items);

                    $tbitem['order_id'] = $order_id;
                    $tbitem['obj_id'] = $obj_id;
                    $tbitem['item_id'] = $items['item_id'];
                    $tbitem['buyer_payment'] = $items['buyer_payment'];
                    $tbitem['cost_tax'] = $items['cost_tax'];

                    $must_add_tbfxsdf['order_items'][] = $tbitem;
                }
                //更新obj上的销售金额
                if(empty($tmp_obj_sale_price)){
                     $tmp_obj_sdf['sale_price'] = $tmp_obj_quantity * $tmp_obj_price- $tmp_obj_pmt_price - $T_pmtPrice;
                }else{
                     $tmp_obj_sdf['sale_price'] = $tmp_obj_sale_price;
                }

                if ($tmp_obj_sdf['sale_price'] != $tmp_obj_sale_price){
                    $oObjects->update($tmp_obj_sdf,array('obj_id'=>$obj_id));
                }
            }
        }

        #保存有更新的items
        if ($update_items){
            foreach ($update_items as $obj_id=>$obj_items){
                $T_pmtPrice = 0;
                $tmp_obj_sdf = array();
                $is_modify_obj = false;
                if (empty($obj_items)) continue;
                foreach($obj_items as $items){
                    if (isset($items['obj_amount'])){
                        //商品明细销售金额计算
                        $items['sale_price'] = $items['sale_price'] ? $items['sale_price'] : ($items['quantity'] * $items['price'] - $items['pmt_price']);
                        $T_pmtPrice += $items['pmt_price'];
                        $tmp_obj_price = $items['obj_price'];
                        $tmp_obj_pmt_price = $items['obj_pmt_price'];
                        $tmp_obj_amount = $items['obj_amount'];
                        $tmp_obj_sale_price = trim($items['obj_sale_price']);
                        $tmp_obj_quantity = $items['obj_quantity'];
                        $is_modify_obj = true;
                    }

                    $oItems->update($items,array('item_id'=>$items['item_id']));


                    $tbitem['order_id'] = $order_id;
                    $tbitem['obj_id'] = $obj_id;
                    $tbitem['item_id'] = $items['item_id'];
                    $tbitem['buyer_payment'] = $items['buyer_payment'];
                    $tbitem['cost_tax'] = $items['cost_tax'];

                    $must_update_tbfxsdf['order_items'][] = $tbitem;

                }
                if ($is_modify_obj){
                    //更新obj上的销售金额
                    if(empty($tmp_obj_sale_price)){
                        $tmp_obj_sdf['sale_price'] = $tmp_obj_quantity * $tmp_obj_price- $tmp_obj_pmt_price - $T_pmtPrice;
                    }else{
                        $tmp_obj_sdf['sale_price'] = $tmp_obj_sale_price;
                    }
                    $oObjects->update($tmp_obj_sdf,array('obj_id'=>$obj_id));
                }
            }
        }

        if( $is_change_goods && (!empty($must_update_tbfxsdf) || !empty($must_add_tbfxsdf)) ){
            //处理淘宝分销订单
            kernel::single('ome_service_c2c_taobao_order')->update_tbfx_order($sdf,$must_add_tbfxsdf,$must_update_tbfxsdf);

        }

        return true;
    }


    /**
    * 订单添加
    * @access public
    * @param Array $sdf 订单sdf结构数据
    * @return Number 订单号
    */
    public function add(&$sdf,$logInfo){

        if (empty($sdf)) return NULL;
        $data = array('tid'=>$sdf['order_bn']);
        $return = array('rsp'=>'fail','msg'=>'','data'=>$data);

        if($service = kernel::servicelist('service.order')){
            foreach ($service as $instance){
                if (method_exists($instance, 'pre_add_order')){
                    $instance->pre_add_order($sdf);
                }
            }
        }

        $order_sdf = $sdf;
        $orderObj = app::get('ome')->model('orders');
        $oResponse = kernel::single('ome_rpc_response');
        $omefunc = kernel::single('ome_func');
        $paymentObj = app::get('ome')->model('payments');

        //--------------------------参数数据初始化赋值------------------------
        $order_bn = $order_sdf['order_bn'];
        $node_id = $order_sdf['node_id'];
        //前端店铺信息
        $addon['bn'] = $order_bn;
        $status = $order_sdf['status'];
        $shop_info = $oResponse->get_shop(array('node_id'=>$node_id),'shop_id,name,node_type');
        $shop_id = $shop_info['shop_id'];
        $shop_name = $shop_info['name'];
        $shop_type = $shop_info['node_type'];
        $c2c_shop_list = ome_shop_type::shop_list();
        $jingdong_type = ome_shop_type::jingdong_type();

        //$logInfo .= '订单信息：' . var_export($order_sdf, true) . '<BR>';

        //本地标准SDF格式数据转换
        $order_sdf['shop_id'] = $shop_id;
        $order_sdf['shop_name'] = $shop_name;
        $order_sdf['shop_type'] = $shop_type;
        $this->order_sdf_convert($order_sdf);

        if($order_sdf['shipping']['is_cod']){
            $is_cod = $order_sdf['shipping']['is_cod'];
        }else{
            $is_cod = 'false';
        }

        $logInfo .= '订单状态业务规则 $is_cod 为：' . var_export($is_cod, true) . '<BR>';

        //订单货品明细业务规则过滤
        $order_objects = $order_sdf['order_objects'];

        $product_status = $this->order_objects_filter($order_bn,$shop_id,$shop_name,$order_objects);//todo
        $order_sdf['order_objects'] = $order_objects;

        $logInfo .= '本地转换后的商品结构信息：' . var_export($order_sdf['order_objects'], true) . '<BR>';

        // 仅针对C2C类型店铺
        if (in_array($shop_type, $c2c_shop_list)){
            //将订单的已支付金额与支付状态初始化，即支付金额为0，支付状态为0（未支付）
            //TODO：此步操作是为了先将所有的订单为未支付，然后会在生成支付单的时候根据支付单数据改变订单的已支付金额与支付状态
            $tmp_payed = $order_sdf['payed'];
            $tmp_pay_status = $order_sdf['pay_status'];
            $order_sdf['payed'] = '0';
            $order_sdf['pay_status'] = '0';
        }

        //保存前端店铺会员信息
        $oMemberFunc = kernel::single('ome_member_func');
        $member_id = $oMemberFunc->save($order_sdf['member_info'],$shop_id);
        if ($member_id){
            $order_sdf['member_id'] = $member_id;
            $logInfo .= '更新前端店铺会员信息，会员ID：' . var_export($member_id, true) . '<BR>';
        }else{
            unset($order_sdf['member_id']);
        }

        //订单创建
        if ($product_status==false){
            $order_sdf['is_fail'] = 'true';
            $order_sdf['edit_status'] = 'true';
            $order_sdf['archive'] = 1;
        }

        if ($orderObj->create_order($order_sdf)){

            $order_id = $order_sdf['order_id'];

            // 恢复支付单
            if($instance = kernel::service('ome_rpc_data_restore.payment')){
                if(method_exists($instance, 'add')){
                    $instance->add($order_bn);
                }
            }

            //更新店铺下载订单时间
            $shopObj = app::get('ome')->model('shop');
            $shopdata = array('last_download_time'=>time());
            $shopObj->update($shopdata, array('shop_id'=>$shop_id));

            //生成支付单`
            if (isset($order_sdf['payments'])){
                $payment_list = $order_sdf['payments'];
            }else{
                $payment_list = array($order_sdf['payment_detail']);
            }

            $pay_time = $payment_list[0]['pay_time']?$payment_list[0]['pay_time']:time();

            if(!in_array($shop_type, $c2c_shop_list) && (bccomp('0.000', $order_sdf['total_amount'],3) == 0)){
                $add_payment = false;
            }else{
                $add_payment = true;
            }
            if (!empty($payment_list) && $pay_time && ($order_sdf['pay_status'] == '1' || $tmp_pay_status == '1') && $add_payment){
                $tmp_pay_money = 0;
                foreach ($payment_list as $pay){
                    $t_begin = $t_end = $omefunc->date2time($pay['pay_time']);

                    $pay_bn = $order_sdf['payment_cfg']['pay_bn'];
                    $payment = $order_sdf['payment_cfg']['id'];
                    $pay_type = $order_sdf['payment_cfg']['pay_type'];

                    $payment_money = $tmp_payed ? $tmp_payed : $pay['money'];
                    $payment_bn = $pay['trade_no'] ? $pay['trade_no'] : $paymentObj->gen_id();
                    $payment_sdf = array(
                        'payment_bn' => $payment_bn,
                        'shop_id' => $shop_id,
                        'order_id' => $order_id,
                        'account' => $pay['account'],
                        'bank' => $pay['bank'],
                        'pay_account' => $pay['pay_account'],
                        'currency' => 'CNY',
                        'money' => $payment_money,
                        'paycost' => $pay['paycost'],
                        'cur_money' => $payment_money?$payment_money:'0',
                        'pay_type' => $pay_type ? $pay_type : 'online',
                        'payment' => $payment,
                        'pay_bn' => $pay_bn,
                        'paymethod' => $pay['paymethod'],
                        't_begin' => $t_begin ? $t_begin : time(),
                        'download_time' => time(),
                        't_end' => $t_end ? $t_end : time(),
                        'status' => 'succ',
                        'trade_no' => $pay['trade_no'],
                        'memo' => $pay['memo'],
                        'op_id' => $pay['op_id'] ? $pay['op_id'] : '',
                    	'op_name' => $pay['op_name'] ? $pay['op_name'] : $order_sdf['shop_type'],
                    );
                    $tmp_pay_money += $payment_money;
                    //添加支付单
                    $paymentObj->insert($payment_sdf);
                    $orderObj->update(array('paytime'=>$payment_sdf['t_begin']),array('order_id'=>$order_id));
                }

                //更新订单支付金额
                $update_fileds = "`payed`=IF(`payed`+{$tmp_pay_money}>`total_amount`, `payed`, `payed`+{$tmp_pay_money})";
                $sql ="UPDATE `sdb_ome_orders` SET {$update_fileds} WHERE `order_id`='".$order_id."'";
                if ($tmp_pay_money == '0' || ($paymentObj->db->exec($sql,true) && $paymentObj->db->affect_row())){
                    //更新订单支付状态
                    if($tmp_pay_money >= $order_sdf['total_amount']){
                        $pay_status = '1';//已支付
                    }elseif ($tmp_pay_money == '0'){
                        $pay_status = '0';//未支付
                    }elseif($tmp_pay_money < $order_sdf['total_amount']){
                        $pay_status = '3';//部分支付
                    }
                    $sql ="UPDATE `sdb_ome_orders` SET pay_status='".$pay_status."' WHERE `order_id`='".$order_id."'";
                    $orderObj->db->exec($sql);
                }

                $logInfo .= '前端店铺增加支付单，信息：' . var_export($payment_sdf, true) . '<BR>';
            }
            $oFunc = kernel::single('ome_order_func');

            //优惠方案
            $pmt_detail = $order_sdf['pmt_detail'];

            $logInfo .= '优惠方案信息：' . var_export($pmt_detail, true) . '<BR>';

            if (!empty($pmt_detail)){
                $pmt_addon = $order_sdf['createtime'];
                $oFunc->update_pmt($order_sdf['order_id'],$shop_id,$pmt_detail,$pmt_addon,$old_pmt);
            }
            //处理淘宝订单优惠上的赠品
            if($shop_type == 'taobao' && app::get('ome')->getConf('ome.preprocess.tbgift') =='true'){
                kernel::single('ome_preprocess_tbgift')->save($order_id,json_decode($order_sdf['other_list'],true));
            }

            //处理淘宝分销订单
            kernel::single('ome_service_c2c_taobao_order')->save_tbfx_order($order_sdf);

            //更新代销人信息
            $selling_agent = $order_sdf['selling_agent'];

            $logInfo .= '代销人信息：' . var_export($selling_agent, true) . '<BR>';

            if (!empty($selling_agent['member_info']['uname'])){
                $oFunc->update_sellagent($order_id,$selling_agent);
            }

            if($service = kernel::servicelist('service.order')){
                foreach ($service as $instance){
                    if (method_exists($instance, 'after_add_order')){
                        if ($tmp_pay_status){
                            $order_sdf['pay_status'] = $tmp_pay_status;
                            $order_sdf['payed'] = $tmp_payed;
                        }
                        $instance->after_add_order($order_sdf);
                    }
                }
            }

            $logInfo .= '返回值为：' . var_export($data, true) . '<BR>';

            $return['rsp'] = 'success';
            $data['order_id'] = $order_sdf['order_id'];
            $return['logInfo'] = $logInfo;
            $return['data'] = $data;
            return $return;
        }else{
            $msg = '订单结构相关字段类型不匹配或者存在相同订单号!';
            $return['rsp'] = 'fail';
            $return['msg'] = $msg;
            $return['logInfo'] = '异常消息：' . $msg . '<BR>';
            return $return;
        }
    }

    /**
     * 将API订单数据转换成本地标准的SDF订单结构数据
     * @access public
     * @param array $order_sdf API订单结构数据
     * @return array 本地标准的sdf结构
     */
    public function order_sdf_convert(&$order_sdf){

        $shop_id = $order_sdf['shop_id'];
        $shop_type = $order_sdf['shop_type'];
        $shop_name = $order_sdf['shop_name'];
        $c2c_shop_list = ome_shop_type::shop_list();
        if (in_array($shop_type, $c2c_shop_list)){
            //淘宝订单优惠、折扣和商品优惠金额转为正数
            $order_sdf['pmt_goods'] = abs($order_sdf['pmt_goods']);
            $order_sdf['pmt_order'] = abs($order_sdf['pmt_order']);

        }
        $payments = $this->get_payment($order_sdf['pay_bn'],$shop_type);
        $order_sdf['pay_bn'] = $payments['pay_bn'];
        $order_sdf['payment_cfg'] = $payments;

        //买家留言
        $custom_memo = $order_sdf['custom_mark'];

        if(in_array($shop_type,array('taobao','paipai')) && app::get('ome')->getConf('ome.checkems') =='true'){
            $tmp['shipping'] = $order_sdf['shipping'];
            if (strtolower(trim($tmp['shipping']['shipping_name'])) == 'ems') {
                $custom_memo = empty($custom_memo) ? "系统：用户选择了 EMS 的配送方式" : "{$custom_memo}\n系统：用户选择了 EMS 的配送方式";
            }
        }

        if ($custom_memo){
            $custommemo[] = array('op_name'=>$shop_name, 'op_time'=>date("Y-m-d H:i:s",time()), 'op_content'=>htmlspecialchars($custom_memo));
            $order_sdf['custom_mark'] = serialize($custommemo);
        }
        //订单备注
        $mark_memo = $order_sdf['mark_text'];
        if ($mark_memo){
            $markmemo[] = array('op_name'=>$shop_name, 'op_time'=>date("Y-m-d H:i:s",time()), 'op_content'=>htmlspecialchars($mark_memo));
            $order_sdf['mark_text'] = serialize($markmemo);
        }
        //配送信息
        $order_sdf['shipping'] = $order_sdf['shipping'];
        if (!empty($order_sdf['shipping'])){
            $order_sdf['shipping']['cost_shipping'] =  $order_sdf['shipping']['cost_shipping']?$order_sdf['shipping']['cost_shipping']:0.00;
            $order_sdf['shipping']['is_protect'] =  $order_sdf['shipping']['is_protect']?$order_sdf['shipping']['is_protect']:'false';
            $order_sdf['shipping']['cost_protect'] =  $order_sdf['shipping']['cost_protect']?$order_sdf['shipping']['cost_protect']:0.00;
            $order_sdf['shipping']['is_cod'] =  $order_sdf['shipping']['is_cod'] == 'true' ? 'true' :'false';
        }
        $omefunc = kernel::single('ome_func');
        $order_sdf['shop_id'] = $shop_id;
        $order_sdf['shop_type'] = $shop_type;
        $order_sdf['is_delivery'] = $order_sdf['is_delivery'] ? $order_sdf['is_delivery'] : 'Y';
        $order_sdf['cost_item'] = $order_sdf['cost_item']?$order_sdf['cost_item']:0.00;
        $order_sdf['is_tax'] = $order_sdf['is_tax']?$order_sdf['is_tax']:'false';
        $order_sdf['cost_tax'] = $order_sdf['cost_tax']?$order_sdf['cost_tax']:0.00;
        $order_sdf['discount'] = $order_sdf['discount']?$order_sdf['discount']:0.00;
        $order_sdf['total_amount'] = $order_sdf['total_amount']?$order_sdf['total_amount']:0.00;
        $order_sdf['pmt_goods'] = $order_sdf['pmt_goods'] ? $order_sdf['pmt_goods'] : 0.00;
        $order_sdf['pmt_order'] = $order_sdf['pmt_order'] ? $order_sdf['pmt_order'] : 0.00;
        $order_sdf['payed'] = $order_sdf['payed']?$order_sdf['payed']:0.00;
        $order_sdf['cur_amount'] = !empty($order_sdf['cur_amount'])?$order_sdf['cur_amount']:0.00;
        $order_sdf['score_u'] = !empty($order_sdf['score_u'])?$order_sdf['score_u']:0.00;
        $order_sdf['score_g'] = !empty($order_sdf['score_g'])?$order_sdf['score_g']:0.00;
        $order_sdf['currency'] = !empty($order_sdf['currency'])?$order_sdf['currency']:'CNY';

        $order_sdf['source'] = 'matrix';
        $order_sdf['createtime'] = $omefunc->date2time($order_sdf['createtime']);
        $order_sdf['download_time'] = time();
        $order_sdf['lastmodify'] = !empty($order_sdf['lastmodify'])?$order_sdf['lastmodify']:time();
        $order_sdf['outer_lastmodify'] = $omefunc->date2time($order_sdf['lastmodify']);
        unset($order_sdf['lastmodify']);
        //收货人信息
        $order_sdf['consignee'] = $order_sdf['consignee'];
        if (!empty($order_sdf['consignee'])){
            $order_sdf['consignee']['area'] = $order_sdf['consignee']['area_state'].'/'.$order_sdf['consignee']['area_city'].'/'.$order_sdf['consignee']['area_district'];
        }
        //发货人信息
        $order_sdf['consigner'] = $order_sdf['consigner'];
        if (!empty($order_sdf['consigner'])){
            $order_sdf['consigner']['area'] = $order_sdf['consigner']['area_state'].'/'.$order_sdf['consigner']['area_city'].'/'.$order_sdf['consigner']['area_district'];
        }
        $order_sdf['consigner']['tel'] = $order_sdf['consigner']['telephone'];
        if (isset($order_sdf['cost_payment'])){
            unset($order_sdf['cost_payment']);
        }
        //$order_sdf['payinfo'] = $order_sdf['payinfo'];
        //设置订单失败时间
        if (empty($order_sdf['order_limit_time'])){
            $order_sdf['order_limit_time'] = time() + 60*(app::get('ome')->getConf('ome.order.failtime'));
        }else{
            $order_sdf['order_limit_time'] = kernel::single('ome_func')->date2time($order_sdf['order_limit_time']);
        }
    }

    /**
     * 订单商品明细规则处理
     * @param string $order_bn 订单号
     * @param string $shop_id 店铺ID
     * @param string $shop_name 店铺名称
     * @param array $order_objects 订单明细数据
     * @return 货号过滤状态
     */
    public function order_objects_filter($order_bn='',$shop_id='',$shop_name='',&$order_objects){
        if (empty($order_objects)) return false;

        $salesMaterialObj = app::get('material')->model('sales_material');
        $bn_exists = array();


        if ($order_objects){
            $i = 0;
            foreach($order_objects as $key=>$obj_val){
                $obj_bn = trim($obj_val['bn']);
                $obj_key = $obj_bn.'-'.$obj_val['obj_type'];
                if ($obj_val['order_items']){
                    $tmp_items_bn = array();
                    foreach ($obj_val['order_items'] as $item_key =>$item_val){
                        $item_bn = trim($item_val['bn']);

                        $item_val['sale_price'] = $item_val['sale_price'] ? $item_val['sale_price'] : 0;

                        $tmp_price = $item_val['sale_price'] / $item_val['quantity'];//销售单价
                        $unqiue_key = $item_bn.$item_val['item_type'].$tmp_price;
                        $tmp_items_bn[] = $unqiue_key;

                        if(isset($obj_val['order_items'][$unqiue_key])){//price bn item_type相同的情况 将item上的数量累加
                            $obj_val['order_items'][$unqiue_key]['quantity'] += $item_val['quantity'];
                            $obj_val['order_items'][$unqiue_key]['pmt_price'] += $item_val['pmt_price'];
                            $obj_val['order_items'][$unqiue_key]['price'] += $item_val['price'];
                            $obj_val['order_items'][$unqiue_key]['amount'] += $item_val['amount'];
                            $obj_val['order_items'][$unqiue_key]['sale_price'] += $item_val['sale_price'];
                        }else{
                            if(isset($item_val['item_id'])){
                                unset($item_val['item_id']);
                            }

                            $obj_val['order_items'][$unqiue_key] = $item_val;
                        }
                        unset($obj_val['order_items'][$item_key]);
                    }
                }
                $order_objects[$key] = $obj_val;
                $i++;
            }
        }

        $new_order_objects = $order_objects;
        foreach($new_order_objects as $key=>$object){
            //子订单
            if($object['bn'])
            {
                $goods_info    = $salesMaterialObj->dump(array('sales_material_bn'=>$object['bn']), 'sm_id');
                if($goods_info)
                {
                    $order_objects[$key]['goods_id'] = $goods_info['sm_id'];
                }
            }
            $order_objects[$key]['obj_type'] = $object['obj_type']?$object['obj_type']:'goods';
            $order_objects[$key]['shop_goods_id'] = $object['shop_goods_id']?$object['shop_goods_id']:0;
            $order_objects[$key]['weight'] = $object['weight']?$object['weight']:0.00;
            $order_objects[$key]['quantity'] = $object['quantity'];
            $order_objects[$key]['bn'] = $object['bn']?$object['bn']:null;
            $order_objects[$key]['oid'] = sprintf('%.0f',$object['oid']);
            $order_objects[$key]['is_oversold'] = ($object['is_oversold'] == true) ?  1 : 0;

            //商品明细
            $items = $object['order_items'];
            $T_pmtPrice = $tmp_obj_price = $ik = 0;
            foreach($items as $k=>$item){
                //判断货号是否存在
                $sql = "SELECT bm_id as product_id, material_bn as bn FROM sdb_material_basic_material WHERE material_bn='".$item['bn']."' ";
                $product_info = kernel::database()->selectrow($sql);
                $product_status = false;
                if(empty($item['bn']) || $product_info['bn'] != $item['bn']){
                    foreach(kernel::servicelist('ome.product') as $name=>$instance){
                        if(method_exists($instance, 'getProductByBn')){
                            $product_info = $instance->getProductByBn($item['bn']);
                            if(!empty($product_info)){
                                $product_status = true;
                                break;
                            }
                        }
                    }
                }else{
                    $product_status = true;
                }
                $bn_exists[] = array('bn'=>$item['bn'],'status'=>$product_status);
                //商品状态
                $item_status = 'false';
                if ($item['status'] == 'close' ){
                    $item_status = 'true';
                }
                //货号规格属性
                if ($item['product_attr']){
                    $product_attr['product_attr'] = $item['product_attr'];
                    $order_objects[$key]['order_items'][$ik]['addon'] = serialize($product_attr);
                }
                $order_objects[$key]['order_items'][$ik] = $item;
                $order_objects[$key]['order_items'][$ik]['delete'] = $item_status;
                $order_objects[$key]['order_items'][$ik]['product_id'] = $product_info['product_id']?$product_info['product_id']:0;
                $order_objects[$key]['order_items'][$ik]['shop_goods_id'] = $item['shop_goods_id']?$item['shop_goods_id']:0;
                $order_objects[$key]['order_items'][$ik]['shop_product_id'] = $item['shop_product_id']?$item['shop_product_id']:0;
                $order_objects[$key]['order_items'][$ik]['quantity'] = $item['quantity']?$item['quantity']:1;
                $order_objects[$key]['order_items'][$ik]['sendnum'] = 0;
                $order_objects[$key]['order_items'][$ik]['item_type'] = trim($item['item_type'])?$item['item_type']:'product';

                //items金额相关字段
                $order_objects[$key]['order_items'][$ik]['price'] = $item['price']?$item['price']:0.00;
                $order_objects[$key]['order_items'][$ik]['pmt_price'] = $item['pmt_price']?$item['pmt_price']:0.00;
                $order_objects[$key]['order_items'][$ik]['amount'] = $item['amount'] ? $item['amount'] : $item['quantity'] * $item['price'];
                $order_objects[$key]['order_items'][$ik]['sale_price'] = $item['sale_price'] ? $item['sale_price'] : ($item['quantity'] * $item['price'] - $item['pmt_price']);

                $T_pmtPrice += $item['pmt_price'];

                unset($order_objects[$key]['order_items'][$k]);

                $ik++;
            }

            //objects金额相关字段
            $order_objects[$key]['pmt_price'] = $object['pmt_price']?$object['pmt_price']:0.00;
            $order_objects[$key]['amount'] = $object['amount'] ? $object['amount'] : $object['quantity'] * $object['price'];

            $object['price'] = $order_objects[$key]['amount'] / $object['quantity'];   //针对B2B obj price传0情况调整

            $order_objects[$key]['price'] = $object['price']?$object['price']:0.00;


            if( !isset($object['sale_price']) ){

                $order_objects[$key]['sale_price'] = $order_objects[$key]['amount'] - $object['pmt_price'] - $T_pmtPrice;

            }else{

                $object['sale_price'] = trim($object['sale_price']);

                if( empty($object['sale_price']) ){

                    $order_objects[$key]['sale_price'] = $order_objects[$key]['amount'] - $object['pmt_price'] - $T_pmtPrice;

                }else{

                    $order_objects[$key]['sale_price'] = $object['sale_price'];

                }
            }

            //$order_objects[$key]['sale_price'] = $object['sale_price'] ? $object['sale_price'] : ($order_objects[$key]['amount'] - $object['pmt_price'] - $T_pmtPrice);
        }
        //获取bn是否存在状态
        if ($bn_exists){
            foreach ($bn_exists as $bn_status){
                if (!$bn_status['status']){
                    $product_status = false;
                    break;
                }
            }
        }
        return $product_status;
    }

    /**
    * 订单过滤
    * 对于前端打过来的订单，过滤掉不符合条件的:拒绝C2C前端店铺的未支付、已发货和已关闭订单
    * @access public
    * @param Array $sdf 订单sdf结构数据
    * @param Array $shop_info 店铺信息
    * @return array('rsp'=>'保存状态success/fail','msg'=>'错误消息')
    */
    public function filter(&$sdf,$shop_info=array()){
        $rs = array('rsp'=>'fail','msg'=>'');
        $shipping = $sdf['shipping'];
        $is_cod = isset($shipping['is_cod']) && $shipping['is_cod'] == 'true' ? 'true' : 'false';
        $order_bn = $sdf['order_bn'];
        $node_id = $sdf['node_id'];
        $shop_type = $shop_info['node_type'];
        $status = $sdf['status'];
        $pay_status = $sdf['pay_status'];
        $ship_status = $sdf['ship_status'];
        $order_objects = $sdf['order_objects'];
        $shop_name = $shop_info['name'];
        $shop_id = $shop_info['shop_id'];
        $order_type = $sdf['order_type'];
        $order_source = $sdf['order_source'];
        $t_type = $sdf['t_type'];
        $consignee = $sdf['consignee'];

        ///实例化数据模型
        $oApi_log = app::get('ome')->model('api_log');
        $log_id = $oApi_log->gen_id();
        $log_title = "接收店铺({$shop_name})的订单:".$order_bn;
        $request_class = 'ome_rpc_response_order';
        $request_method = 'add';

        //前端C2C店铺类型
        $c2c_shop_list = ome_shop_type::shop_list();
        $jingdong_type = ome_shop_type::jingdong_type();

        //订单order_bn为空
        if (empty($order_bn)){
            $msg = '订单号不能为空';
            //$log_id = $oApi_log->gen_id();
            //$oApi_log->write_log($log_id,$log_title,$request_class,$request_method,'','','response','fail',$msg,'','api.store.trade');
            $rs['msg'] = $msg;
            return $rs;
        }

        //如果订单明细不存在拒收
        if(empty($order_objects)){
            $msg = '订单明细不能为空';
            //$log_id = $oApi_log->gen_id();
            //$oApi_log->write_log($log_id,$log_title,$request_class,$request_method,'','','response','fail',$msg,'','api.store.trade');
            $rs['msg'] = $msg;
            return $rs;
        }

        //拒绝c2c：未支付并且是款到发货的订单
        if (in_array($shop_type, $c2c_shop_list)){

            //淘分销订单只接受已支付订单
            if( 'taobao' == $shop_type && $t_type == 'fenxiao' && $pay_status == '0'){
                $msg = '淘宝分销订单未支付';
                $rs['msg'] = $msg;
                return $rs;
            }elseif ( $is_cod == 'false' && $pay_status == '0'){
                $msg = '订单未支付';
                //$rs['rsp'] = 'success';
                $rs['msg'] = $msg;
                return $rs;
            }
        }
        if($shop_type == 'amazon'){

            if( 'AFN' == $trade_type ){
                $msg = '不接受配送方式为亚马逊配送的订单';
                //$log_id = $oApi_log->gen_id();
                //$oApi_log->write_log($log_id,$log_title,$request_class,$request_method,'','','response','fail',$msg,'','api.store.trade',$order_bn);
                $rs['msg'] = $msg;
                return $rs;
            }

            if( empty($consignee['addr']) && empty($consignee['name']) ){
                $msg = '收货人信息不完整';
                //$log_id = $oApi_log->gen_id();
                //$oApi_log->write_log($log_id,$log_title,$request_class,$request_method,'','','response','fail',$msg,'','api.store.trade',$order_bn);
                $rs['msg'] = $msg;
                return $rs;
            }

            if( trim($shipping['shipping_name']) == '卖家自行配送' ){
                $sdf['self_delivery'] = 'true';
            }else{
                $sdf['self_delivery'] = 'false';
            }

        }
        //判断是否接收物流宝发货的订单
        //if('false' == app::get('ome')->getConf('ome.delivery.wuliubao') && $order_type == 'agentsale'){
          if('false' == app::get('ome')->getConf('ome.delivery.wuliubao') && strtolower(trim($sdf['is_force_wlb'])) == 'true'){
              //临时解决办法
            $msg = '不接收物流宝发货订单';
            //$log_id = $oApi_log->gen_id();
            //$oApi_log->write_log($log_id,$log_title,$request_class,$request_method,'','','response','fail',$msg,'','api.store.trade',$order_bn);
            $rs['msg'] = $msg;
            return $rs;
        }

        //拒绝关闭订单
        if ($status != 'active'){

            if($status=='dead' && $order_source == 'taofenxiao'){
                $rs['rsp'] = 'success';
                return $rs;
            }

            if ($status == 'close'){
                $msg = 'order:'.$order_bn.' has been closed';
                $log_title = '店铺('.$shop_name.')订单'.$order_bn . '已关闭';
            }else{
                $msg = 'order:'.$order_bn.' has been finished';
                $log_title = '店铺('.$shop_name.')订单'.$order_bn . '已完成';
            }
            //日志记录
            /*
            $api_filter = array('marking_value'=>$order_bn,'marking_type'=>'order_close');
            $api_detail = $oApi_log->dump($api_filter, 'log_id');
            if (empty($api_detail['log_id'])){
                $addon = $api_filter;
                $log_id = $oApi_log->gen_id();
                $oApi_log->write_log($log_id,$log_title,$request_class,$request_method,'','','response','fail',$msg,$addon,'api.store.trade',$order_bn);
            }
            */

            $rs['msg'] = $msg;

            return $rs;
        }

        $rs['rsp'] = 'success';
        return $rs;
    }

    /**
    * 支付方式获取
    * @param String $pay_bn 支付方式编号
    * @param String $shop_type 店铺类型
    * @return Array 支付方式信息
    */
    public function get_payment($pay_bn,$shop_type=''){
        $pay_bn = $pay_bn ? $pay_bn :$this->shop_type2pay_type[$shop_type];
        $pay_bn = $pay_bn ? $pay_bn : 'online';
        $payment_cfg = kernel::database()->select("SELECT id,custom_name,pay_bn,pay_type FROM `sdb_ome_payment_cfg` WHERE `pay_bn`='{$pay_bn}' OR `pay_bn`='online'");
        if ($payment_cfg){
           $online = array();
           foreach ($payment_cfg as $cfg){
               if ($cfg['pay_bn'] == $pay_bn){
                   return $cfg;
               }else{
                   $online = $cfg;
               }
           }
           return $online;
        }else{
            $cfgObj = app::get('ome')->model('payment_cfg');
            $cfgSdf = array(
                'custom_name' => '线上支付',
                'pay_bn' => 'online',
                'pay_type' => 'online',
            );
            $cfgObj->save($cfgSdf);
            return $cfgSdf;
        }
    }

}
