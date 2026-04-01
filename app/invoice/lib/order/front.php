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

class invoice_order_front {

    //订单变更
    public function insertOrUpdateByOrder($params) {
        $source = 'b2c';
        $objFront = app::get('invoice')->model('order_front');
        $inData = array(
            'source_id'      => $params['order_id'],
            'source'         => $source,
            'mode'           => $params['invoice_kind'],
            'ship_area'      => $params['ship_area'] ? : $params['consignee']['area'],
            'ship_addr'      => $params['ship_addr'] ? : $params['consignee']['addr'],
            'ship_tel'       => $params['ship_mobile'] ? : $params['consignee']['mobile'],
            'ship_email'     => $params['receiver_email'] ? : '',
            'ship_name'      => $params['invoice_receiver_name'],
            'ship_tax'       => $params['register_no'],
            'ship_bank'      => $params["invoice_bank_name"],
            "ship_bank_no"      => $params["invoice_bank_account"],
            "ship_company_addr" => $params["invoice_address"],      // 注册地址
            "ship_company_tel"  => $params['invoice_phone'],        // 注册电话
            'amount' => $params['invoice_amount'],
            'status' => $params['status'],
            'title' => strip_tags(trim($params['tax_title'])),
            'is_status' => $params['is_status']
        );
        $oldRow = $objFront->db_dump(['source_id' => $params['order_id'],'source' => $source]);
        if($oldRow) {
            $diff = array_diff_assoc(array_filter($inData, function($v){return isset($v);}), $oldRow);
            if(empty($diff)) {
                return [true, ['msg'=>'没有差异项，无需更新']];
            }
            $objFront->update($diff, ['id'=>$oldRow['id']]);
            if($diff['amount']) {
                /* $inData = array_merge($oldRow, $diff);
                list($rs, $rsData) = $this->updateOrderItems($inData);
                if(!$rs) {
                    return [false, $rsData];
                } */
                unset($diff['amount']);
            }
            if($diff) {
                $this->eventUpdate($oldRow['id'], $diff);
            }
            return [true, ['msg'=>'更新完成']];
        }
        $inData['status'] = $inData['status'] ? : 'acitve';
        $inData['title'] = $inData['title'] ? : '个人';
        
        // 发票抬头最多支持150个字符，防止SQL错误
        if(strlen($inData['title']) > 150){
            $inData['title'] = kernel::single('ome_func')->substrStringSafe($inData['title'], 150);
        }
        
        $inData = kernel::single('invoice_order_front_router', $source)->getMain($inData);
        if(empty($inData)) {
            return [false, ['msg'=>'该来源暂不支持：'.$source]];
        }
        $inData = array_filter($inData);
        $rs = $objFront->insert($inData);
        if(!$rs) {
            return [false, ['msg'=>'主表写入失败:'.$objFront->db->errorinfo()]];
        }
        list($rs, $rsData) = $this->updateOrderItems($inData);
        if(!$rs) {
            return [false, $rsData];
        }
        list($rs, $rsData) = $this->eventCreate($inData['id']);
        return [true, ['msg'=>'处理完成']];
    }

    public function updateAmount($filter, $amount) {
        $objFront = app::get('invoice')->model('order_front');
        $main = $objFront->db_dump($filter);
        if(empty($main)) {
            return [false, ['msg'=>'缺少单据']];
        }
        $objFront->update(['amount'=>$amount], ['id'=>$main['id']]);
        $main['amount'] = $amount;
        return $this->updateOrderItems($main);
    }

    public function updateOrderItems($main) {
        $sourceItems = kernel::single('invoice_order_front_router', $main['source'])->getItems($main);
        if(empty($sourceItems)) {
            return [false, ['msg'=>'该来源暂不支持：'.$main['source']]];
        }
        if($main['cost_freight'] > 0) {
            $sourceItems[0] = [
                'of_id' => $main['id'],
                'source_item_id' => 0,
                'bm_id' => 0,
                'bn' => '邮费',
                'item_type' => 'ship',
                'name' => '邮费',
                'divide' => $main['cost_freight'],
                'amount' => $main['cost_freight'],
                'quantity' => 1,
                'reship_num' => 0,
                'is_delete' => 'false'
            ];
        }
        $objItems = app::get('invoice')->model('order_front_items');
        $ofiItems = $objItems->getList('*', ['of_id'=>$main['id']]);
        if($ofiItems) {
            foreach($ofiItems as $v) {
                if($sourceItems[$v['source_item_id']]) {
                    $sourceItems[$v['source_item_id']]['id'] = $v['id'];
                } else {
                    $v['is_delete'] = 'true';
                    $sourceItems[$v['source_item_id']] = $v;
                }
            }
        }
        $invoiceAmount = $main['amount'];
        foreach($sourceItems as $k => $v) {
            if($v['item_type'] == 'ship') {
                if($v['is_delete'] == 'false') {
                    if($invoiceAmount > $v['amount']) {
                        $invoiceAmount -= $v['amount'];
                    } else {
                        $v['is_delete'] = 'true';
                    }
                }
                $objItems->db_save($v);
                unset($sourceItems[$k]);
                continue;
            }
            if($v['is_delete'] == 'true') {
                $objItems->db_save($v);
                unset($sourceItems[$k]);
                continue;
            }
            $sourceItems[$k]['porth'] = (1 - $v['reship_num'] / $v['quantity']) * $v['divide'];
        }
        if(empty($sourceItems)) {
            return [true, ['msg'=>'处理完成']];
        }
        if ($invoiceAmount != $main['amount']) {
            $options     = array (
                'part_total'  => $invoiceAmount,
                'part_field'  => 'amount',
                'porth_field' => 'porth',
            );
            $sourceItems = kernel::single('ome_order')->calculate_part_porth($sourceItems, $options);
        }
        foreach($sourceItems as $v) {
            $objItems->db_save($v);
        }
        return [true, ['msg'=>'处理完成']];
    }

    public function updateItemsByOrder($source_id, $source, $deleteShip = false) {
        $mainObj = app::get('invoice')->model('order_front');
        $mainId = $mainObj->db_dump(['source_id' => $source_id,'source' => $source], 'id')['id'];
        if(!$mainId) {
            return [false, ['msg'=>'缺少单据']];
        }
        $trans = kernel::database()->beginTransaction();
        #并发限制
        $mainObj->update(['source_id'=>$source_id], ['id'=>$mainId]);
        $main = $mainObj->db_dump(['id'=>$mainId]);
        $sourceItems = kernel::single('invoice_order_front_router', $main['source'])->getItems($main);
        if(empty($sourceItems)) {
            kernel::database()->rollBack();
            return [false, ['msg'=>'该来源暂不支持：'.$main['source']]];
        }
        $objItems = app::get('invoice')->model('order_front_items');
        $ofiItems = $objItems->getList('*', ['of_id'=>$main['id']]);
        $updateItems = [];
        if($ofiItems) {
            foreach($ofiItems as $v) {
                if($v['item_type'] == 'ship') {
                    if($v['is_delete'] =='false' && $deleteShip) {
                        $updateItems[] = ['id'=>$v['id'], 'is_delete'=>'true'];
                        $objItems->update(['is_delete'=>'true'], ['id'=>$v['id']]);
                        $mainObj->update(['cost_freight'=>0], ['id'=>$mainId]);
                    }
                    continue;
                }
                $upItem = [];
                if($sourceItems[$v['source_item_id']]) {
                    if($v['is_delete'] != $sourceItems[$v['source_item_id']]['is_delete']) {
                        $upItem['is_delete'] = $sourceItems[$v['source_item_id']]['is_delete'];
                    }
                    if($v['reship_num'] != $sourceItems[$v['source_item_id']]['reship_num']) {
                        $upItem['reship_num'] = $sourceItems[$v['source_item_id']]['reship_num'];
                        $upItem['quantity'] = $v['quantity'];
                    }
                } else {
                    $upItem['is_delete'] = 'true';
                }
                if($upItem) {
                    $upItem['id'] = $v['id'];
                    $updateItems[] = $upItem;
                    $objItems->update($upItem, ['id'=>$v['id']]);
                }
            }
        }
        $mainStatus = $this->dealMainStatus($mainId);
        kernel::database()->commit($trans);
        $this->eventUpdate($main['id'], ['status'=>$mainStatus, 'items'=>$updateItems]);
        return [true, ['msg'=>'操作成功']];
    }

    public function dealMainStatus($mainId) {
        $mainObj = app::get('invoice')->model('order_front');
        $status = $mainObj->db_dump(['id' => $mainId], 'status')['status'];
        if($status == 'close') {
            return $status;
        }
        $objItems = app::get('invoice')->model('order_front_items');
        $ofiItems = $objItems->getList('quantity,reship_num,is_delete', ['of_id'=>$mainId]);
        foreach($ofiItems as $v) {
            if($v['quantity'] == $v['reship_num'] || $v['is_delete'] == 'true') {
                //
            } else {
                return $status;
            }
        }
        $mainObj->update(['status'=>'close'], ['id'=>$mainId]);
        return 'close';
    }

    public function eventCreate($id) {
        $objFront = app::get('invoice')->model('order_front');
        $main = $objFront->db_dump(['id'=>$id]);
        if($main['is_status'] != '0' || $main['disabled'] == 'true') {
            $objFront->update(['is_write'=>'0', 'write_msg'=>'不需要写入'], ['id'=>$main['id']]);
            return [false, ['msg'=>'不需要写入']];
        }
        $objItems = app::get('invoice')->model('order_front_items');
        $ofiItems = $objItems->getList('*', ['of_id'=>$main['id']]);
        foreach($ofiItems as $k => $val) {
            $ofiItems[$k]['of_item_id'] = $val['id'];
            $ofiItems[$k]['source_bn'] = $main['source_bn'];
            $ofiItems[$k]['status'] = $main['status'];
            $ofiItems[$k]['item_name'] = $val['name'];
        }
        $params = $main;
        $params['items'] = $ofiItems;
        list($rs, $msg) = kernel::single('invoice_process')->newCreate($params);
        $objFront->update(['is_write'=>($rs ? '1' : '0'), 'write_msg'=>mb_strcut($msg, 0, 255, 'UTF-8')], ['id'=>$main['id']]);
        return [$rs, ['msg'=>$msg]];
    }

    public function eventUpdate($id, $upData){
        list($rs, $msg) = kernel::single('invoice_order')->updateOrderInvoiceProcess($id,$upData);
        if(!$rs && $msg=='更新发票信息不存在') {
            return $this->eventCreate($id);
        }
        return [$rs, ['msg'=>$msg]];
    }
}