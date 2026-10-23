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


class wms_delivery_print_ship extends wms_delivery_print_abstract{

    /**
     * format
     * @param mixed $print_data 数据
     * @param mixed $sku sku
     * @param mixed $_err _err
     * @return mixed 返回值
     */
    public function format($print_data, $sku,&$_err){
        $delivery_cfg = app::get('wms')->getConf('wms.delivery.status.cfg');
        $deliveryObj = app::get('wms')->model('delivery');
        $dlyCorpObj = app::get('ome')->model('dly_corp');
        $basicMObj = app::get('material')->model('basic_material');
        
        $basicMaterialLib = kernel::single('material_basic_material');

        $markShowMethod = app::get('ome')->getConf('ome.order.mark'); //备注显示方式
        $express_company_no = '';
        $allItems = $rows = $dlyCorp = $hasPrint = $waybillRpc = array();
        if ($print_data['ids']) {
            unset($print_data['ids']);

            // 平台会员
            $memberList = app::get('ome')->model('members')->getList('member_id,uname',[
                'member_id' => array_unique(array_column($print_data['deliverys'], 'member_id')),
            ]);
            $memberList = array_column($memberList, null, 'member_id');

            // 主档
            $bmIds = [];
            foreach ($print_data['deliverys'] as $dly) {
                $bmIds = array_merge($bmIds, array_column($dly['delivery_items'], 'product_id'));
            }
            $bmIds = array_unique($bmIds);

            $bmExtList = app::get('material')->model('basic_material_ext')->getList('bm_id,weight,specifications',[
                'bm_id' => $bmIds,
            ]);
            $bmExtList = array_column($bmExtList, null, 'bm_id');

            $bmCodeList = app::get('material')->model('codebase')->getList('bm_id,code',[
                'bm_id' => $bmIds,
                'type' => kernel::single('material_codebase')->getBarcodeType()
            ]);
            $bmCodeList = array_column($bmCodeList, null, 'bm_id');

            $bmList = app::get('material')->model('basic_material')->getList('bm_id, material_bn, material_name', [
                'bm_id' => $bmIds,
            ]);
            $bmList = array_column($bmList, null, 'bm_id');

            // 店铺
            $shopList = app::get('ome')->model('shop')->getList('*',[
                'shop_id' => array_unique(array_column($print_data['deliverys'], 'shop_id')),
            ]);
            $shopList = array_column($shopList, null, 'shop_id');

            // 承运商
            $logiIds = array_unique(array_column($print_data['deliverys'], 'logi_id'));

            $corpList = app::get('ome')->model('dly_corp')->getList('*', [
                'corp_id' => $logiIds,
            ]);
            $corpList = array_column($corpList, null, 'corp_id');

            $shopCorpList = app::get('ome')->model('dly_corp_channel')->getList('*', [
                'corp_id' => $logiIds,
            ]);

            $channelIds = array_filter(array_column($corpList, 'channel_id'));
            foreach ($shopCorpList as $c) {
                $corpList[$c['corp_id']][$c['shop_type']] = $c['channel_id'];

                $channelIds[] = $c['channel_id'];
            }

            // 电子面单渠道
            $channelExtList = $channelList = [];
            if ($channelIds) {
                $channelExtList = app::get('logisticsmanager')->model('channel_extend')->getList('channel_id,seller_id,mobile',[
                    'channel_id' => $channelIds,
                ]);
                $channelExtList = array_column($channelExtList, null, 'channel_id');

                $channelList = app::get('logisticsmanager')->model('channel')->getList('channel_id,channel_type,logistics_code,shop_id', [
                    'channel_id' => $channelIds,
                ]);
                $channelList = array_column($channelList, null, 'channel_id');
            }



            // 打印信息
            $waybillList = [];
            $logiNos = array_filter(array_column($print_data['deliverys'], 'logi_no'));
            if ($logiNos) {
                $sql = "SELECT e.*, w.channel_id, w.waybill_number
                        FROM sdb_logisticsmanager_waybill w, sdb_logisticsmanager_waybill_extend e
                        WHERE w.id = e.waybill_id AND w.waybill_number IN('".implode("','", $logiNos)."')";
                foreach (kernel::database()->select($sql) as $waybill){
                    $waybillList[$waybill['channel_id']][$waybill['waybill_number']] = $waybill;
                }
            }

            foreach ($print_data['deliverys'] as $dly) {

                // 会员
                $dly['member_uname'] = $memberList[$dly['member_id']]['uname'];


                $data = $dly;

                $num = 0;
                $err = '';
                if ($data) {
                    //批次号
                    $allItems[$data['delivery_id']] = $data;

                    //统计已打印单据
                    if (($data['print_status']& 4) == 4) {
                        $hasPrint[] = $data['delivery_bn'];
                    }
                    
                    //获取明细中货品信息
                    foreach ($data['delivery_items'] as $k => $i) {
                        $num += $i['number'];

                        // 取后端商品名
                        $data['delivery_items'][$k]['product_name'] = $bmList[$i['product_id']]['material_name'];
                        // 取后端商品规格
                        $data['delivery_items'][$k]['addon'] = $bmExtList[$i['product_id']]['specifications'];
                        // 取后端商品重量
                        $data['delivery_items'][$k]['weight'] = $bmExtList[$i['product_id']]['weight'];

                        $data['delivery_items'][$k]['bn_dbvalue'] = $data['delivery_items'][$k]['bn'];
                    }

                    //获取订单相关信息
                    $o_bn = $mark_text = $custom_mark = $total_amount = array();
                    foreach ($data['orders'] as $odk => $order) {
                        if ($order['mark_text']) {
                            $mark = unserialize($order['mark_text']);
                            if (is_array($mark) || !empty($mark)){
                                if($markShowMethod == 'all'){
                                    foreach ($mark as $im) {
                                        $mark_text[] = $im['op_content'];
                                    }
                                }else{
                                    $mark = array_pop($mark);
                                    $mark_text[] = $mark['op_content'];
                                }
                            }
                        }

                        if ($order['custom_mark']) {
                            $custommark = unserialize($order['custom_mark']);
                            if (is_array($custommark) || !empty($custommark)){
                                if($markShowMethod == 'all'){
                                    foreach ($custommark as $im) {
                                        if($order['order_source'] == 'tbdx'){
                                            $im['op_content']= $this->fomate_tbfx_memo($im['op_content'],$markShowMethod);
                                            $custom_mark[] = $im['op_content'];
                                        }else{
                                            $custom_mark[] = $im['op_content'];
                                        }
                                    }
                                }else{
                                    if($order['order_source'] == 'tbdx'){
                                        $mark = array_pop($custommark);
                                        $memo['op_content']= $this->fomate_tbfx_memo($mark['op_content'],$markShowMethod);
                                        $custom_mark[] = $memo['op_content'];
                                    }else{
                                        $mark = array_pop($custommark);
                                        $custom_mark[] = $mark['op_content'];
                                    }
                                }
                            }
                        }
                        $o_bn[] = $order['order_bn'];
                        $total_amount[] = $order['total_amount'];
                    }

                    $shop = $shopList[$data['shop_id']];

                    #分销王订单新增代销人收货信息
                    if($shop['node_type'] == 'shopex_b2b'){
                        #开启分销王代销人发货信息
                        if($delivery_cfg['set']['wms_delivery_sellagent']){
                            #订单扩展表上的状态是
                            foreach($data['delivery_order'] as $val){
                                $oSellagent = app::get('ome')->model('order_selling_agent');
                                $sellagent_detail = $oSellagent->dump(array('order_id'=>$val['order_id']));
                                #订单扩展表上的状态是1  (只有代销人发货人与发货地址都存在，状态才会是1)
                                if($sellagent_detail['print_status'] == '1'){
                                    $shop['name'] = $sellagent_detail['website']['name'];
                                    $shop['default_sender'] = $sellagent_detail['seller']['seller_name'];
                                    $shop['mobile'] = $sellagent_detail['seller']['seller_mobile'];
                                    $shop['tel'] = $sellagent_detail['seller']['seller_phone'];
                                    $shop['zip'] = $sellagent_detail['seller']['seller_zip'];
                                    $shop['addr'] =  $sellagent_detail['seller']['seller_address'];
                                    $shop['area'] = $sellagent_detail['seller']['seller_area'];
                                }
                            }
                        }
                    }

                    //获取物流信息

                    $dlyCorp = $corpList[$data['logi_id']];

                    $data['prt_tmpl_id'] = $dlyCorp['prt_tmpl_id'];
                    $data['logi_type'] = $dlyCorp['type'];

                    // 电子面单渠道
                    $channel_id = $dlyCorp[$data['shop_type']] ?: $dlyCorp['channel_id'];
                    $data['channel_id'] = $channel_id;
                    $data['channel_type'] = $channelList[$channel_id]['channel_type'];
                    $data['logistics_code'] = $channelList[$channel_id]['logistics_code'];
                    $data['channel_shop_id'] = $channelList[$channel_id]['shop_id'];

                    $data['seller_id'] = $channelExtList[$channel_id]['seller_id'];
                    $data['channel_mobile'] = $channelExtList[$channel_id]['mobile'];
                    $data['mainoInfo'] = $waybillList[$channel_id][$data['logi_no']];

                    $data['shopinfo'] = $shop;
                    $data['order_memo'] = implode(',', $mark_text);
                    $data['order_custom'] = implode(',', $custom_mark);
                    $data['order_count'] = $num;
                    $data['order_bn'] = implode(',', $o_bn);
                    $data['order_total_amount'] = implode(',', $total_amount);

                    //去除多余的三级区域
                    $reg = preg_quote(trim($data['consignee']['province']));
                    if (!empty($data['consignee']['city'])) {
                        $reg .= '.*?' . preg_quote(trim($data['consignee']['city']));
                    }
                    if (!empty($data['consignee']['district'])) {
                        $reg .= '.*?' . preg_quote(trim($data['consignee']['district']));
                    }
                    $data['consignee']['addr'] = preg_replace('/' . $reg . '/is', '', $data['consignee']['addr']);

                    //快递公式
                    if (!$express_company_no) {
                        $express_company_no = strtoupper($dlyCorp['type']);
                        $logi_name = $data['logi_name'];
                    }
                    //京东电子面单补打
                    // $channel_info = $this->getWaybillType($data['delivery_id']);
                    if (in_array($data['channel_type'],array('360buy'))) {
                        $data['batch_logi_no'] = $data['logi_no'];
                         $logi_no = explode('-',$data['logi_no']);
                         
                         if (count($logi_no)<=1){
                            $data['batch_logi_no'] = $data['logi_no'].'-1-'.$data['logi_number'].'-';
                         }else{
                             $data['logi_no'] = $logi_no[0];
                         }
                    }
                    $rows['delivery'][] = $data;
                    $itm['delivery_id'] = $data['delivery_id'];
                    $itm['delivery_bn'] = $data['delivery_bn'];
                    $itm['consignee']['name'] = $data['consignee']['name'];
                    $idd[] = $itm;
                    $logid[$data['delivery_id']] = $data['logi_no'];
                    $ids[] = $data['delivery_id'];
                } else {
                    $_err = 'true';
                }
            }
            if ($ids)
                $name = implode(',', $ids);
        }

        $rows['dly_tmpl_id'] = $dlyCorp['prt_tmpl_id'];
        $rows['order_number'] = count($ids);
        $rows['name'] = $name;
        //物流公司标识
        $print_logi_id = $data['logi_id'];

        //商品名称和规格取前台,是合并发货单,取第一个订单的货品名称
        $deliCfgLib = kernel::single('wms_delivery_cfg');
        $is_print_front = (1 == $deliCfgLib->getValue('wms_delivery_is_printship')) ? true : false;
        if ($ids && $is_print_front) {
            // $arrPrintProductName = $deliveryObj->getPrintFrontProductName($ids);

            // if (!empty($arrPrintProductName)) {
                foreach ($rows['delivery'] as $k => $row) {
                    foreach ($row['delivery_items'] as $k2 => $v) {

                        // $bncode = md5($row['shop_id'].$v['bn']);

                        $row['delivery_items'][$k2]['product_name'] = $row['orders'][$v['order_id']]['order_objects'][$v['order_obj_id']]['name'];


                        $spec_info = ome_order_func::format_order_items_addon($row['orders'][$v['order_id']]['order_objects'][$v['order_obj_id']]['order_items'][$v['order_item_id']]['addon']);

                        $row['delivery_items'][$k2]['addon'] = $spec_info;
                        $row['delivery_items'][$k2]['spec_info'] = $spec_info;

                        $bpro_key = $row['branch_id'].$v['product_id'];

                        $row['delivery_items'][$k2]['store_position'] = &$bpro[$bpro_key];
                    }
                    $rows['delivery'][$k] = $row;
                }

            // }
        } elseif($ids) {
            // 货位的获取
            $tmp_product_ids = array();
            foreach ($rows['delivery'] as $k => $row) {
                foreach ($row['delivery_items'] as $k2 => $v) {
                    $tmp_product_ids[] = $v['product_id'];
                    $bpro_key = $row['branch_id'].$v['product_id'];
                    $rows['delivery'][$k]['delivery_items'][$k2]['store_position'] = &$bpro[$bpro_key];
                }
            }
            // 货品货位有关系
            $bppModel = app::get('ome')->model('branch_product_pos');
            $bppList = $bppModel->getList('product_id,pos_id,branch_id',array('product_id'=>$tmp_product_ids));

            // 如果货位存在
            if ($bppList) {
                // 货位信息
                $tmp_pos_ids = array();
                foreach ($bppList as $key=>$value) {
                    $tmp_pos_ids[] = $value['pos_id'];
                }

                $posModel = app::get('ome')->model('branch_pos');
                $posList = $posModel->getList('pos_id,branch_id,store_position',array('pos_id'=>$tmp_pos_ids));

                $newPosList = array();
                foreach ($posList as $key=>$value) {
                    $bpos_key = $value['branch_id'].$value['pos_id'];
                    
                    $bpos[$bpos_key] = $value['store_position'];
                }
                unset($posList);

                foreach ($bppList as $key=>$value) {
                    $bpro_key = $value['branch_id'].$value['product_id'];
                    $bpos_key = $value['branch_id'].$value['pos_id'];
                    $bpro[$bpro_key] = $bpos[$bpos_key];
                }
                unset($bppList);
            }
        }

        $hasPrintStr = implode(',',array_slice($hasPrint,0,4));
        $hasPrintStr .= (count($hasPrint)>4) ? '……' : '';

        return array(
            'allItems' => $allItems,
            'print_logi_id' => $print_logi_id,
            'delivery' => $rows['delivery'],
            'dly_tmpl_id' => $rows['dly_tmpl_id'],
            'order_number' => $rows['order_number'],
            'vid' => $rows['name'],
            'hasOnePrint' => json_encode(count($hasPrint)),
            'hasPrintStr' => json_encode($hasPrintStr),
            'ids' => $ids,
            'idd' => $idd,
            'logid' => $logid,
            'logi_name' => $logi_name,
            'count' => sizeof($ids),
            'express_company_no' => $express_company_no,
            'o_bn' => $o_bn,
        );
    }


    #处理淘宝分销类型订单备注
    private function fomate_tbfx_memo($memo = null,$markShowMethod ='last'){
        return '留言：'.preg_replace('/(买家|分销商|系统).*\(\d{4}-\d{1,2}-\d{1,2}\s{0,}\d{1,2}:\d{1,2}:\d{1,2}\)\s{0,}\(.*\)\s{0,}[:|：]/isU', '', $memo);
    }

    /**
     * arrayToJson
     * @param mixed $deliverys deliverys
     * @return mixed 返回值
     */
    public function arrayToJson($deliverys) {
        $jsondata = '';
        if ($deliverys) {
            $this->formatField($deliverys);
            $jsondata = json_encode($deliverys);
            $jsondata = str_replace(array('&quot;'), array('”'), $jsondata);
        }
        return $jsondata;
    }

    /**
     * formatField
     * @param mixed $oriRowData 数据
     * @return mixed 返回值
     */
    public function formatField(&$oriRowData) {
        foreach($oriRowData as $k => &$val) {
            if(is_array($val)) {
                foreach($val as &$data) {
                    // 保留 wxshipin_product_info 等嵌套商品数组结构
                    if (is_array($data)) {
                        continue;
                    }
                    $data = $this->printSingleFormat($data);
                }
            } else {
                $val = $this->printSingleFormat($val);
            }
        }
    }
    /**
     * printSingleFormat
     * @param mixed $single single
     * @return mixed 返回值
     */
    public function printSingleFormat($single) {
        if($single === null) {
            return '';
        } elseif (is_bool($single)) {
            return $single === false ? 'false' : 'true';
        }
        $str = strval($single);
        $str = trim($str);
        $str = str_replace(array('&#34;','"','&quot;','&quot'),array('“','“','“'), $str);
        $str = str_replace(array('&quot;','&quot'), array('”','”'), $str);

        return $str;
    }
    /**
     * 获取面单的类型 normal普通 direct电子直连 electron 普通电子
     * */
    public function getWaybillType($id) {
        $deliveryObj = app::get('wms')->model('delivery');
        $channelObj = app::get("logisticsmanager")->model("channel");
        $dlyCorpObj = app::get('ome')->model('dly_corp');
        $data = $deliveryObj->dump($id, 'logi_id');
        $dlyCorp = $dlyCorpObj->dump($data['logi_id'], 'tmpl_type,channel_id');
        $return = array('type' => 'normal');

        if ($dlyCorp['tmpl_type'] == 'electron') {
            $cFilter = array(
                'channel_id' => $dlyCorp['channel_id'],
                'status'=>'true',
            );
            $channel = $channelObj->dump($cFilter);
            $zlList = array('taobao', 'sf', 'yunda','360buy');
            if ($channel && in_array($channel['channel_type'], $zlList)) {
                $return = array('type' => 'direct', 'channel_type' => $channel['channel_type'], 'channel_id' => $channel['channel_id'], 'shop_id'=> $channel['shop_id']);
            }elseif($channel){
                $return = array('type' => 'electron', 'channel_type' => $channel['channel_type'], 'channel_id' => $channel['channel_id'], 'shop_id'=> $channel['shop_id']);
            }
        }elseif($dlyCorp['tmpl_type'] == 'cainiao'){
            $cFilter = array(
                'channel_id' => $dlyCorp['channel_id'],
                'status'=>'true',
            );
            $channel = $channelObj->dump($cFilter);
            $zlList = array('taobao');
            if ($channel && in_array($channel['channel_type'], $zlList)) {
                $return = array('type' => 'direct', 'channel_type' => $channel['channel_type'], 'channel_id' => $channel['channel_id'], 'shop_id'=> $channel['shop_id']);
            }
        }
        return $return;
    }

    /**
     * 检查所有待打的快递单都有运单号了
     */
    public function checkAllHasLogiNo($deliveryIds,$afterprint = true,$cids='') {
        if($afterprint){
            $deliveryBillObj = app::get('wms')->model('delivery_bill');
            $deliveryIdStr = implode(',', $deliveryIds);
            $sql = "SELECT count(logi_no) as _count FROM `sdb_wms_delivery_bill` where delivery_id IN (". $deliveryIdStr . ") and type =1";
            $result = $deliveryBillObj->db->select($sql);
            $status = false;
            if ($result) {
                $count = $result[0]['_count'];
                if (count($deliveryIds) == $count) {
                    $status = true;
                }
            }
        }else{
            $deliveryBillObj = app::get('wms')->model('delivery_bill');
            $deliveryBIdStr = implode(',', $cids);
            $sql = "SELECT count(logi_no) as _count FROM `sdb_wms_delivery_bill` where delivery_id=". $deliveryIds . " and b_id IN (". $deliveryBIdStr . ") and type =2";
            $result = $deliveryBillObj->db->select($sql);
            $status = false;
            if ($result) {
                $count = $result[0]['_count'];
                if (count($cids) == $count) {
                    $status = true;
                }
            }
        }
        return $status;
    }

    /**
     * afterPrintDirectType
     * @return mixed 返回值
     */
    static public function afterPrintDirectType(){
        $channel_type = array('sf', 'yunda');
        return $channel_type;
    }
}