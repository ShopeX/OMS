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


class invoice_order
{
    public function save_base_file($data)
    {
        ini_set('memory_limit', '512M');
        $file_id         = 0;
        $baseStoragerMdl = kernel::single('base_storager', 'invoice');
        if ($data['url'] && $data['url'] != 'no') {
            $url = $data['url'];// 远程图片URL
            if (preg_match('#^(https?|ftp)://#i', $url)) {
                // 本地保存路径（包括文件名）
                $extension   = pathinfo($url, PATHINFO_EXTENSION); // 获取扩展名
                $uniqid      = uniqid(); // 生成唯一ID
                $save_path   = sprintf('%s/%s-%s-%s.%s',DATA_DIR,$data['order_bn'],date('Ymd'),$uniqid,$extension);
                $src         = fopen($url, 'r');
                $destination = fopen($save_path, 'w');
                $bytesCopied = stream_copy_to_stream($src, $destination);
                if ($bytesCopied !== false) {
                    $data['url'] = $save_path;
                }
            }
            $file             = [
                'tmp_name' => $data['url'],
                'name'     => $data['name'],
            ];
            $msg = '';
            $addon['node_id'] = $data['order_bn'];
            $file_id          = $baseStoragerMdl->save_upload($file, 'file', $addon, $msg);
            if (isset($save_path)) {
                @unlink($save_path);
            }
        }
        return $file_id;
    }
    
    /**
     * 新建相似、改票参数组织
     * @Author: xueding
     * @Vsersion: 2023/6/1 上午11:40
     * @param $params
     * @return mixed
     */
    public function formatAddData($params)
    {
        $invoiceItemMdl = app::get('invoice')->model('order_items');
        $data           = $params;
        //默认0纸质发票 电子发票为1，  mode参数：'1' => '电子发票', '2' => '纸质发票', '3' => '专用发票',
        if (intval($params['mode']) == '1') {
            $mode = '1';
        } else {
            $mode = '2';
            if (intval($params['type_id']) == '1') {
                $mode = '3';
            }
        }
        $data['mode']                  = $mode;
        $data['tax_title']             = $params['title'];
        $data['invoice_receiver_name'] = $params['tax_company'];
        $data['register_no']           = $params['ship_tax'];
        
        $items         = $invoiceItemMdl->getList('*', ['id' => $params['id'],'is_delete'=>'false']);
        //编辑合并发票明细处理
        if ($params['invoice_type'] == 'merge') {
            $this->updateMergeInvoiceItems($params,$items);
        }
        if ($items) {
            foreach ($items as $key => $val) {
                $item_id                      = $val['item_id'];
                $items[$key]['specification'] = isset($params['specification'][$item_id]) ? $params['specification'][$item_id] : $val['specification'];
                $items[$key]['unit']          = isset($params['unit'][$item_id]) ? $params['unit'][$item_id] : $val['unit'];
                $items[$key]['item_name']     = isset($params['item_name'][$item_id]) ? $params['item_name'][$item_id] : $val['item_name'];
                $items[$key]['tax_code']      = isset($params['tax_code'][$item_id]) ? $params['tax_code'][$item_id] : $val['tax_code'];
            }
            $data['items'] = $items;
        }
        return $data;
    }
    
    /**
     * 组织开票明细
     * @Author: xueding
     * @Vsersion: 2023/5/30 下午4:59
     * @param $items
     * @param $id
     * @return array
     */
    public function getAddItemsData($params,$id,$type = '')
    {
        $items = $params['items'];
        $invoiceFunc = kernel::single('invoice_func');
        $all_basic_material_bns = $all_sales_material_bns = array();
        foreach($items as $v)
        {
            if($v['item_type'] == 'basic'){
                $all_basic_material_bns[] = $v['bn'];
            }elseif ($v['item_type'] == 'sales') {
                $all_sales_material_bns[] = $v['bn'];
            }
        }
        $materialList = [];
        if ($all_sales_material_bns) {
            $materialList = $invoiceFunc->getSalesMaterialInfo($all_sales_material_bns);
        }
        if ($all_basic_material_bns) {
            $materialList = $invoiceFunc->getBasicMaterialInfo($all_basic_material_bns);
        }
        $newData = [];
        foreach ($items as $key => $val) {
            $saleRow = $materialList[$val['bn']];
            $unit          = $saleRow['unit'] ?: $val['unit'];
            $tax_name      = $saleRow['tax_name'] ?: $val['item_name'];
            $specification = $val['specification'] ?: $val['bn'];
            $tax_code      = $saleRow['tax_code'] ?: $val['tax_code'];
            //运费开票分类编码处理
            if ($val['item_type'] == 'ship') {
                $tax_code = app::get('ome')->getConf('ome.invoice.infreight.category');
                $val['tax_rate']  = app::get('ome')->getConf('ome.invoice.infreight.rate')  * 100;
                $tax_name = app::get('ome')->getConf('ome.invoice.infreight.name');
                $unit = '';
            }
            if ($params['is_edit'] == 'true'|| in_array($type,['add_new_same','change_ticket','add_merge_invoice'])) {
                $unit          = $val['unit'];
                $tax_name      = $val['item_name'];
                $specification = $val['specification'];
                $tax_code      = $val['tax_code'];
            }
            $tax_rate                    = $saleRow['tax_rate'] > 0 ? $saleRow['tax_rate'] : $val['tax_rate'];
            $cost_tax                    = $invoiceFunc->get_invoice_cost_tax($val['amount'], $tax_rate);
            $tempData                    = [];
            $tempData['id']              = $id;
            $tempData['of_id']           = $val['of_id'];
            $tempData['of_item_id']      = $val['of_item_id'];
            $tempData['source_bn']       = $val['source_bn'];
            $tempData['bn']              = $val['bn'];
            $tempData['bm_id']           = $val['bm_id'];
            $tempData['item_type']       = $val['item_type'];
            $tempData['item_name']       = $tax_name ?? '';
            $tempData['specification']   = $specification ?? '';
            $tempData['unit']            = $unit ?? '';
            $tempData['amount']          = $val['amount'];
            $tempData['original_amount'] = $val['original_amount'] ? $val['original_amount'] : $val['amount'];
            $tempData['tax_rate']        = $tax_rate ?? 0;
            $tempData['tax_code']        = $tax_code ?? 0;
            $tempData['cost_tax']        = $cost_tax ?? 0;
            $tempData['quantity']        = $val['quantity'] ?: 1;
            if ($val['original_id']) {
                $tempData['original_id'] = $val['original_id'];
            }
            if ($val['original_item_id']) {
                $tempData['original_item_id'] = $val['original_item_id'];
            }
            if ($val['status'] == 'finish') {
                $tempData['item_is_make_invoice'] = '1';
            }
            if ($val['inoperable_reason']) {
                $tempData['inoperable_reason'] = $val['inoperable_reason'];
            }
            if (isset($val['item_is_make_invoice'])) {
                $tempData['item_is_make_invoice'] = $val['item_is_make_invoice'];
            }
            if (isset($val['is_delete'])) {
                $tempData['is_delete'] = $val['is_delete'];
            }
            $newData[]                   = $tempData;
        }
        return $newData;
    }
    
    /**
     * 更新发票明细
     * @Author: xueding
     * @Vsersion: 2023/5/31 下午5:42
     * @param $params
     * @param array $invoiceItems
     * @return array
     */
    public function updateInvoiceItems($params,$invoiceItems = [])
    {
        $invoiceFunc = kernel::single('invoice_func');
        if (!$params['id']) {
            return [false,'更新明细缺少参数'];
        }
        $invoiceMdl = app::get('invoice')->model('order');
        $oldData = $invoiceMdl->db_dump($params['id'],'amount,cost_freight');
        $invoiceItemMdl = app::get('invoice')->model('order_items');
        $opObj = app::get('ome')->model('operation_log');
        
        if (empty($invoiceItems)) {
            $invoiceItems = $invoiceItemMdl->getList('*',['id'=>$params['id']]);
        }
        if ($params['amount'] > 0) {
            $newItems = $invoiceItems;
            foreach ($newItems as $key => $val) {
                if ($val['item_type'] == 'ship') {
                    unset($newItems[$key]);
                }
            }
            if ($params['amount'] < $oldData['cost_freight']) {
                return [false,'开票金额不能小于运费'];
            }
            $options = array (
                'part_total'  => $params['amount'] - $oldData['cost_freight'],
                'part_field'  => 'amount',
                'porth_field' => 'original_amount',
            );
            $newItems = kernel::single('ome_order')->calculate_part_porth($newItems, $options);
            $newItems = array_column($newItems,null,'item_id');
        }
        
        foreach ($invoiceItems as $key => $val) {
            $updateData = [];
            $item_id = $val['item_id'];
            if (isset($params['item_name'][$item_id])) {
                $updateData['item_name'] = $params['item_name'][$item_id];
            }
            if (isset($params['specification'][$item_id])) {
                $updateData['specification'] = $params['specification'][$item_id];
            }
            if (isset($params['unit'][$item_id])) {
                $updateData['unit'] = $params['unit'][$item_id];
            }
            if (isset($params['tax_code'][$item_id])) {
                $updateData['tax_code'] = $params['tax_code'][$item_id];
//                $invoiceTypeList = material_sales_material::$sale_invoice_bn;
//                $invoiceTypeList = array_column($invoiceTypeList,null,'code');
//                if ($invoiceTypeList[$updateData['tax_code']]) {
//                    $updateData['tax_rate'] = $val['tax_rate'] = $invoiceTypeList[$updateData['tax_code']]['rate'];
//                }else{
//                    if ($val['amount'] > 0) {
//                        return [false,'更新明细失败'.$val['bn'].'，税收编码不正确'];
//                    }
//                }
            }
            if (isset($newItems[$item_id])) {
                $updateData['amount'] = $newItems[$item_id]['amount'];
                $cost_tax = $invoiceFunc->get_invoice_cost_tax($updateData['amount'], ($val['tax_rate'] ? $val['tax_rate'] : '13'));
                $updateData['cost_tax'] = $cost_tax;
            }
            if ($val['status'] == 'finish') {
                $updateData['item_is_make_invoice'] = '1';
                $updateData['inoperable_reason']    = '';
            }
            if ($val['is_delete']) {
                $updateData['is_delete'] = $val['is_delete'];
            }
            if (isset($val['quantity'])) {
                $updateData['quantity'] = $val['quantity'];
            }
            if ($updateData) {
                $updateItemRes = $invoiceItemMdl->update($updateData,['item_id'=>$item_id,'id'=>$params['id']]);
                if (!$updateItemRes) {
                    $msg = '失败。';
                    $opObj->write_log('invoice_edit@invoice', $params["id"], $msg);
                    return [false,'更新明细失败'.$val['bn']];
                }
            }
        }
        kernel::single('invoice_func')->getInvoiceMakeStatus($params['id']);
    
        return [true,'更新明细成功'];
    }
    
    /**
     * 合并发票同货号合并展示
     * @Author: xueding
     * @Vsersion: 2023/6/2 下午4:18
     * @param $invoice_order_items
     * @return array
     */
    public function showMergeInvoiceItems($invoice_order_items)
    {
        $itemData = [];
        foreach ($invoice_order_items as $key => $val) {
            if ($val['is_delete'] == 'true') {
                continue;
            }
            if (!$itemData[$val['bn']]) {
                $itemData[$val['bn']] = $val;
            }else{
                $itemData[$val['bn']]['amount'] += $val['amount'];
                $itemData[$val['bn']]['quantity'] += $val['quantity'];
                if (isset($val['sales_amount'])) {
                    $itemData[$val['bn']]['sales_amount'] += $val['sales_amount'];
                }
                if (isset($val['nums'])) {
                    $itemData[$val['bn']]['nums'] += $val['nums'];
                }
            }
        }
        return $itemData;
    }
    
    /**
     * 合并冲红原发票
     * @Author: xueding
     * @Vsersion: 2023/6/2 下午6:12
     * @param array $ids
     */
    public function cancelOldInvoiceOrder(array $ids)
    {
        foreach ($ids as $id) {
            kernel::single('invoice_process')->cancel(['id'=>$id],'merge_order');
        }
    }
    
    /**
     * 合并发票明细数组重组
     * @Author: xueding
     * @Vsersion: 2023/6/2 下午4:15
     * @param $params
     * @param $invoice_order_items
     */
    public function updateMergeInvoiceItems(&$params, $invoice_order_items)
    {
        foreach ($params['bn'] as $item_id => $bn) {
            $bns[$bn]['spec']      = $params['specification'][$item_id];
            $bns[$bn]['unit']      = $params['unit'][$item_id];
            $bns[$bn]['item_name'] = $params['item_name'][$item_id];
            $bns[$bn]['unit']      = $params['unit'][$item_id];
        }
        foreach ($invoice_order_items as $key => $val) {
            if (isset($bns[$val['bn']])) {
                $params['item_name'][$val['item_id']]     = $bns[$val['bn']]['item_name'];
                $params['specification'][$val['item_id']] = $bns[$val['bn']]['spec'];
                $params['unit'][$val['item_id']]          = $bns[$val['bn']]['unit'];
                $params['tax_code'][$val['item_id']]      = $bns[$val['bn']]['tax_code'];
            }
        }
    }
    
    /**
     * 发票更新
     * @Author: xueding
     * @Vsersion: 2023/6/6 下午6:15
     * @param $of_id
     * @param $params
     * @return array|string[]
     */
    public function updateOrderInvoiceProcess($of_id,$params)
    {
        //check
        if(empty($of_id)){
            return [false,'更新失败,无效的of_id'];
        }
        
        $addon = json_encode($params);
        $opObj = app::get('ome')->model('operation_log');
        $sql = "SELECT oi.*,o.is_status,o.is_make_invoice,o.source_status,o.mode,o.type_id,o.amount as invoice_amount FROM sdb_invoice_order AS o LEFT JOIN sdb_invoice_order_items AS oi on oi.id=o.id WHERE oi.of_id =" . $of_id . " AND o.is_status != '2' ";
        $invoiceItemList = kernel::database()->select($sql);
        if (!$invoiceItemList) {
            return [false,'更新发票信息不存在'];
        }
        $invoiceInfo = current($invoiceItemList);
        $id = $invoiceInfo['id'];
        //防止并发
        kernel::database()->exec("UPDATE sdb_invoice_order SET create_time=`create_time` WHERE id =".$id);
    
        if ($params['status'] == 'close') {
//            kernel::single('invoice_process')->cancel(['id'=>$id],"content_update");
//            return [true,'更新成功'];
        }
        
        //有items参数更新明细数据
        if ($params['items']) {
            $items = array_column($params['items'],null,'id');
        }
        $invoiceAmount = $invoiceInfo['invoice_amount'];
        $item_amount = [];

        foreach ($invoiceItemList as $key => $val) {
            if ($val['item_is_make_invoice'] == '0' && $params['status'] == 'finish') {
                $invoiceItemList[$key]['status'] = 'finish';
            }
            if (isset($items[$val['of_item_id']])) {
                $ofItemRow  = $items[$val['of_item_id']];
                if (isset($ofItemRow['is_delete'])) {
                    $invoiceItemList[$key]['is_delete'] = $ofItemRow['is_delete'];
                    $invoiceAmount = $invoiceAmount - $invoiceItemList[$key]['amount'];
                }
                if (isset($ofItemRow['reship_num'])) {
                    $quantity = $ofItemRow['quantity'] - $ofItemRow['reship_num'];
                    if ($quantity) {
                        $amount = $invoiceItemList[$key]['amount'] / $invoiceItemList[$key]['quantity'];
                        $invoiceItemList[$key]['quantity'] = $quantity;
                        //开票金额减去退掉的数量乘单价
                        $invoiceAmount = $invoiceAmount - ($amount * $ofItemRow['reship_num']);
                        $item_amount[$val['item_id']]      = ($amount * $quantity);
                    }else{
                        $invoiceItemList[$key]['is_delete'] = 'true';
                        $invoiceAmount = $invoiceAmount - $invoiceItemList[$key]['amount'];
                    }
                }
            }
            if ($params['status'] == 'close') {
                $invoiceItemList[$key]['is_delete'] = 'true';
            }
        }
        $params['invoice_amount'] = $invoiceAmount;

        $this->updateInvoiceItems(['id'=>$invoiceInfo['id']],$invoiceItemList);
        if ($params['status'] == 'finish') {
            $opObj->write_log('invoice_edit@invoice', $id, $invoiceInfo['source_bn'].',订单签收完成更新可操作状态');
        }
    
        $params['is_status'] = $invoiceInfo['is_status'];
        $params['mode'] = $invoiceInfo['mode'];
        $params['type_id'] = $invoiceInfo['type_id'];
        $this->updateInvoice($id,$params);
        
        $opObj->write_log('invoice_edit@invoice', $id, '发票信息发生更新，更新内容：'. $addon);
        return [true,'更新成功'];
    }
    
    /**
     * 更新发票主信息
     * @Author: xueding
     * @Vsersion: 2023/6/6 下午6:14
     * @param $id
     * @param $params
     */
    public function updateInvoice($id,$params)
    {
        $invoiceMdl = app::get('invoice')->model('order');
        $invoiceItemMdl = app::get('invoice')->model('order_items');
        $opObj = app::get('ome')->model('operation_log');
        $invoice_order = $invoiceMdl->dump(['id'=>$id],"*");
        $invoice_order_items = $invoiceItemMdl->getList('*',array('id'=>$id,'is_delete'=>'false'));
        
        $updateInvoiceData = [];
        if ($params["ship_bank"]) {
            $updateInvoiceData['ship_bank'] = $params["ship_bank"];
        }
        if ($params["ship_bank_no"]) {
            $updateInvoiceData['ship_bank_no'] = $params["ship_bank_no"];
        }
        if ($params["ship_company_addr"]) {
            $updateInvoiceData['ship_company_addr'] = $params["ship_company_addr"];
        }
        if ($params["ship_company_tel"]) {
            $updateInvoiceData['ship_company_tel'] = $params["ship_company_tel"];
        }
        if ($params["ship_tax"]) {
            $updateInvoiceData['ship_tax'] = $params["ship_tax"];
        }
        // 更新发票抬头
        if (isset($params['title'])) {
            $updateInvoiceData['title'] = trim($params['title']);
        }
        if ($params['invoice_amount']) {
            $updateInvoiceData['amount'] = $params['invoice_amount'];
            $updateInvoiceData['cost_tax'] = array_sum(array_column($invoice_order_items,'cost_tax'));
        }
        if ($params['is_status'] == '1') {
            if ($params['mode'] == '0' && $params['type_id'] == '1') {
                $updateInvoiceData['is_make_invoice'] = '2';
            }else{
                $type = 'content_update';
                kernel::single('invoice_process')->cancel(['id'=>$id],$type);
                $updateInvoiceData['action_type'] = $type;
            }
        }
        if ($updateInvoiceData) {
            $invoiceMdl->update($updateInvoiceData,['id'=>$id]);
            $log_memo        = serialize(['invoice'=>$invoice_order,'invoice_order_items'=>$invoice_order_items]);
            $opObj->write_log('invoice_edit@invoice', $id, $log_memo);
        }
    }
    
    /**
     * 冲红成功设置新票为可操作
     * @Author: xueding
     * @Vsersion: 2023/6/9 上午11:24
     * @param $original_id
     */
    public function cancelSuccessRes($original_id)
    {
        $invoiceItemMdl = app::get('invoice')->model('order_items');
        $sql = "SELECT oi.item_id,oi.id,oi.source_bn FROM sdb_invoice_order AS o LEFT JOIN sdb_invoice_order_items AS oi on oi.id=o.id WHERE oi.original_id =" . $original_id . " AND o.is_status = '0' AND o.invoice_type = 'merge'  ";
        $invoiceItemList = kernel::database()->select($sql);
        if ($invoiceItemList) {
            $row = current($invoiceItemList);
            $id = $row['id'];
            $itemsId = array_column($invoiceItemList,'item_id');
            $invoiceItemMdl->update(['item_is_make_invoice'=>'1'],['item_id'=>$itemsId,'original_id'=>$original_id,'id'=>$id]);
            kernel::single('invoice_func')->getInvoiceMakeStatus($id);
            app::get('ome')->model('operation_log')->write_log('invoice_billing@invoice', $id, $row['source_bn'].',冲红成功更新可操作状态');
        }
    }

    /**
     * 获取平台最新开票金额
     * @Author: XueDing
     * @Date: 2023/11/22 2:13 PM
     * @param $orderInfo
     * @return array
     */
    public function getInvoiceMoney($orderInfo) {
        if (in_array($orderInfo['shop_type'], ['taobao', 'tmall', 'luban'])) {
            $tmInvoiceInfo = kernel::single('erpapi_router_request')->set('shop', $orderInfo['shop_id'])->invoice_getApplyInfo(['order_bn'=>$orderInfo['order_bn']]);
            if ($tmInvoiceInfo['rsp'] == 'succ') {
                foreach ($tmInvoiceInfo['data'] as $invKey => $invVal) {
                    if ($invVal['invoice_type'] == 'blue') {
                        return [true, ['amount'=>$invVal['invoice_amount']]];
                    }
                }
                if ($orderInfo['shop_type'] == 'luban' && $invoiceList = $tmInvoiceInfo['data']['invoice_list']) {
                    foreach ($invoiceList as $val) {
                        $val['invoice_amount'] = sprintf("%.2f", $val['invoice_amount'] / 100);
                        if ($val['invoice_amount'] > 0) {
                            return [true, ['amount' => $val['invoice_amount']]];
                        }
                    }
                }
            }
        }
        //京东根据订单优惠明细重新计算开票实付金额
        if ($orderInfo['shop_type'] == '360buy') {
            $couponList = kernel::single('ome_order_coupon')->getOrderItemCouponDetail($orderInfo['order_id']);
            $amount  = 0;
            foreach ($couponList as $key => $val) {
                if (($val['sendnum'] - $val['return_num']) > 0) {
                    $amount += ($val['sendnum'] - $val['return_num']) * $val['calcActuallyPay'];
                }
            }
            return [true, ['amount'=>$amount]];
        }
        return [true, ['amount' => $orderInfo['payed']]];
    }
}