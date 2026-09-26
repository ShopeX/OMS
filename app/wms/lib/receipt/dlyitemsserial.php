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

class wms_receipt_dlyitemsserial{

    /**
     *
     * 唯一码发货单校验数据处理
     * @param Array $sdf 
     */
    public function generate($sdf, &$msg){
        //校验传入参数
        if(!$this->checkParams($sdf,$msg)){
            return false;
        }

        $prdSerialLib    = kernel::single('wms_product_serial');
        $rs = $prdSerialLib->freezeSerial($sdf['serial_id']);
        if(!$rs){
            return false;
        }

        $dlyItemsSerialObj    = app::get('wms')->model('delivery_items_serial');
        $rs = $dlyItemsSerialObj->insert($sdf);
        if($rs){
            //write log freeze serial
            $operationLogObj        = app::get('ome')->model('operation_log');
            $operationLogObj->write_log('product_serial_freeze@wms',$sdf['serial_id'],'唯一码校验预占，发货单号：'.$sdf['delivery_bn']);

            return true;
        }else{
            return false;
        }
    }

    private function checkParams(&$params,&$msg){
        //check required params

        //get serial_id
        $prdSerialObj = app::get('wms')->model('product_serial');
        $serial_info = $prdSerialObj->dump(array('serial_number' => $params['serial_number'], 'product_id' => $params['product_id'], 'branch_id' => $params['branch_id']),'serial_id');
        if($serial_info){
            $params['serial_id'] = $serial_info['serial_id'];
        }else{
            return false;
        }

        return true;
    }

    /**
     *
     * 唯一码
     * @param Array $sdf
     */
    public function consign($sdf, &$out_serial){
        if(empty($sdf['delivery_id']) || empty($sdf['delivery_bn'])){
            return false;
        }

        $prdSerialLib    = kernel::single('wms_product_serial');
        $prdSerialObj = app::get('wms')->model('product_serial');
        $dlyItemsSerialObj    = app::get('wms')->model('delivery_items_serial');
        $operationLogObj        = app::get('ome')->model('operation_log');

        $itemIds = array();
        $items = $dlyItemsSerialObj->getList('item_serial_id,serial_id,product_id,bn,product_name,serial_number', array('delivery_id'=>$sdf['delivery_id']), 0, -1);
        foreach ($items as &$item){
            $rs = $prdSerialLib->outStorage($item['serial_id']);
            if(!$rs){
                return false;
            }

            $rs = $dlyItemsSerialObj->update(array('status'=>1), array('item_serial_id'=>$item['item_serial_id']));
            if(!is_numeric($rs) || $rs <= 0){
                return false;
            }

            //get serial primary key to write log
            $serialIds[] = $item['serial_id'];

            //按更新状态组织数据抛出，后续
            unset($item['item_serial_id'], $item['serial_id']);
        }

        $operationLogObj->batch_write_log('product_serial_outstorage@wms',array('serial_id' => $serialIds),'唯一码发货出库，发货单号：'.$sdf['delivery_bn']);

        $out_serial = $items;
        return true;
    }

    /**
     *
     * 唯一码发货单前端叫回取消
     * @param Array $sdf 
     */
    public function cancel($sdf){
        if(empty($sdf['delivery_id']) || empty($sdf['delivery_bn'])){
            return false;
        }

        $prdSerialLib    = kernel::single('wms_product_serial');
        $prdSerialObj = app::get('wms')->model('product_serial');
        $dlyItemsSerialObj    = app::get('wms')->model('delivery_items_serial');
        $operationLogObj        = app::get('ome')->model('operation_log');

        $items = $dlyItemsSerialObj->getList('item_serial_id,serial_id,product_id,serial_number', array('delivery_id'=>$sdf['delivery_id']), 0, -1);
        foreach ($items as $item){
            $rs = $prdSerialLib->unfreezeSerial($item['serial_id']);
            if(!$rs){
                return false;
            }

            $rs = $dlyItemsSerialObj->update(array('status'=>2), array('item_serial_id'=>$item['item_serial_id']));
            if(!is_numeric($rs) || $rs <= 0){
                return false;
            }

            //get serial primary key to write log
            $serialIds[] = $item['serial_id'];
        }

        $operationLogObj->batch_write_log('product_serial_unfreeze@wms',array('serial_id' => $serialIds),'唯一码预占取消，发货单号：'.$sdf['delivery_bn']);

        return true;
    }

    /**
     *
     * 唯一码发货单校验数据处理
     * @param Array $sdf 
     */
    public function returnProduct($sdf, &$msg, &$return_serial){
        //校验传入参数
        if(!$this->checkReturnParams($sdf,$msg)){
            return false;
        }

        //return to old branch
        if($sdf['action'] == 'return'){
            $prdSerialLib    = kernel::single('wms_product_serial');
            $rs = $prdSerialLib->returnStorage($sdf['serial_id']);
            if(!$rs){
                return false;
            }

        }elseif($sdf['action'] == 'add'){
            //return to new branch
            $prdSerialLib    = kernel::single('wms_product_serial');
            $rs = $prdSerialLib->returnStorageToNewBranch($sdf);
            if(!$rs){
                return false;
            }else{
                $sdf['serial_id'] = $rs;
            }
        }

        $reshipItemsSerialObj    = app::get('ome')->model('reship_items_serial');
        $rs = $reshipItemsSerialObj->insert($sdf);
        if($rs){
            //return quote 
            $return_serial = array('branch_id' => $sdf['branch_id'], 'bn' => $sdf['bn'], 'product_name' => $sdf['product_name'], 'reship_bn' => $sdf['reship_bn'], 'serial_number' => $sdf['serial_number']);
            //write log return serial
            $operationLogObj        = app::get('ome')->model('operation_log');
            $operationLogObj->write_log('product_serial_return@wms',$sdf['serial_id'],'唯一码退货退入，退货单号：'.$sdf['reship_bn']);

            return true;
        }else{
            return false;
        }
    }

    private function checkReturnParams(&$params,&$msg){
        //check required params

        //get serial_id
        $prdSerialObj = app::get('wms')->model('product_serial');
        $serial_info = $prdSerialObj->dump(array('serial_number' => $params['serial_number'], 'bn' => $params['bn'], 'branch_id' => $params['branch_id']),'serial_id,product_id');
        if($serial_info){
            $params['serial_id'] = $serial_info['serial_id'];
            $params['product_id'] = $serial_info['product_id'];

            $basicMaterialObj = app::get('material')->model('basic_material');
            $basicMaterialInfo = $basicMaterialObj->dump(array('material_bn' => $params['bn']),'material_name');
            if($basicMaterialInfo){
                $params['product_name'] = $basicMaterialInfo['material_name'];
            }

            $params['action'] = 'return';
        }else{
            $basicMaterialObj = app::get('material')->model('basic_material');
            $basicMaterialInfo = $basicMaterialObj->dump(array('material_bn' => $params['bn']),'bm_id,material_name');
            if($basicMaterialInfo){
                $params['product_name'] = $basicMaterialInfo['material_name'];
                $params['product_id'] = $basicMaterialInfo['bm_id'];
            }

            $params['action'] = 'add';
        }

        return true;
    }

    public function getConsignSerial($delivery_id){
        $dlyItemsSerialObj    = app::get('ome')->model('delivery_items_serial');
        $items = $dlyItemsSerialObj->getList('bn,serial_number', array('delivery_id'=>$delivery_id, 'status'=>1), 0, -1);
        if($items){
            return $items;
        }else{
            return false;
        }
    }

    public function getReturnSerial($reship_id){
        $reshipItemsSerialObj    = app::get('ome')->model('reship_items_serial');
        $items = $reshipItemsSerialObj->getList('bn,serial_number', array('reship_id'=>$reship_id), 0, -1);
        if($items){
            return $items;
        }else{
            return false;
        }
    }

    /**
     *
     * according to the order_id, find the serial_number that how much quantities can return
     * @param Int $order_id 
     */
    public function getCanReturnSerial($params){
        if(!$params['order_id']){
            return false;
        }

        $order_id = $params['order_id'];
        $can_return_serial = array();

        //get history delivery
        $deliveryIds = kernel::single('ome_order_delivery')->getDlyIdsByOrdId($order_id, 'succ');
        if($deliveryIds){
            $serailInfo = $this->getConsignSerial($deliveryIds);
            if($serailInfo){
                foreach($serailInfo as $serial){
                    $can_return_serial[$serial['serial_number']] = $serial['bn'];
                }
            }
            foreach ($this->getMachineCenterDeliverySerials($deliveryIds) as $serial) {
                if ($serial['serial_number'] !== '' && $serial['serial_number'] !== null) {
                    $can_return_serial[$serial['serial_number']] = $serial['bn'];
                }
            }
        }

        if(!$can_return_serial) return false;

        //get history return
        $reshipIds = kernel::single('ome_order_reship')->getReshipIdsByOrdId($order_id, 'succ');
        if($reshipIds){
            $serailInfo = $this->getReturnSerial($reshipIds);
            if($serailInfo){
                foreach($serailInfo as $serial){
                    //if serial has return ,unset the array key
                    if(isset($can_return_serial[$serial['serial_number']])){
                        unset($can_return_serial[$serial['serial_number']]);
                    }
                }
            }
            foreach ($this->getMachineCenterReshipSerialNumbers($reshipIds) as $sn) {
                if (isset($can_return_serial[$sn])) {
                    unset($can_return_serial[$sn]);
                }
            }
        }

        return $can_return_serial;
    }

    /**
     * 机器中心发货出库流水中的唯一码（original_type=delivery）
     *
     * @param int[] $deliveryIds
     * @return array<int, array{bn:string, serial_number:string}>
     */
    private function getMachineCenterDeliverySerials(array $deliveryIds)
    {
        if (empty($deliveryIds) || !app::get('machinecenter')->is_installed()) {
            return array();
        }
        $snFlowMdl = app::get('machinecenter')->model('material_sn_flow');
        $filter = array('original_type' => 'delivery');
        if (count($deliveryIds) === 1) {
            $filter['original_id'] = reset($deliveryIds);
        } else {
            $filter['original_id|in'] = $deliveryIds;
        }
        $rows = $snFlowMdl->getList('sn_id,material_bn', $filter, 0, -1);
        if (empty($rows)) {
            return array();
        }
        $snIds = array_values(array_unique(array_filter(array_column($rows, 'sn_id'))));
        if (empty($snIds)) {
            return array();
        }
        $snMdl = app::get('machinecenter')->model('material_sn');
        $snFilter = count($snIds) === 1 ? array('id' => reset($snIds)) : array('id|in' => $snIds);
        $snRows = $snMdl->getList('id,sn', $snFilter, 0, -1);
        $idToSn = array();
        foreach ($snRows as $r) {
            $idToSn[$r['id']] = $r['sn'];
        }
        $out = array();
        foreach ($rows as $row) {
            $sn = isset($idToSn[$row['sn_id']]) ? $idToSn[$row['sn_id']] : '';
            if ($sn === '') {
                continue;
            }
            $out[] = array('bn' => $row['material_bn'], 'serial_number' => $sn);
        }
        return $out;
    }

    /**
     * 机器中心已退货入库流水中的 SN（original_type=reship，与 succ 退货单对齐）
     *
     * @param int[] $reshipIds
     * @return string[]
     */
    private function getMachineCenterReshipSerialNumbers(array $reshipIds)
    {
        if (empty($reshipIds) || !app::get('machinecenter')->is_installed()) {
            return array();
        }
        $snFlowMdl = app::get('machinecenter')->model('material_sn_flow');
        $filter = array('original_type' => 'reship');
        if (count($reshipIds) === 1) {
            $filter['original_id'] = reset($reshipIds);
        } else {
            $filter['original_id|in'] = $reshipIds;
        }
        $rows = $snFlowMdl->getList('sn_id', $filter, 0, -1);
        if (empty($rows)) {
            return array();
        }
        $snIds = array_values(array_unique(array_filter(array_column($rows, 'sn_id'))));
        if (empty($snIds)) {
            return array();
        }
        $snMdl = app::get('machinecenter')->model('material_sn');
        $snFilter = count($snIds) === 1 ? array('id' => reset($snIds)) : array('id|in' => $snIds);
        $snRows = $snMdl->getList('id,sn', $snFilter, 0, -1);
        $sns = array();
        foreach ($snRows as $r) {
            if ($r['sn'] !== '' && $r['sn'] !== null) {
                $sns[] = $r['sn'];
            }
        }
        return $sns;
    }
}
