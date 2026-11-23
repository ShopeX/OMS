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

class console_receipt_inventory{

    public function do_inventory($data,&$msg)
    {
        $basicMaterialObj    = app::get('material')->model('basic_material');
        $oIostock       = app::get('ome')->model("iostock");
        $iostockObj     = kernel::service('ome.iostock');
        $oBranchProduct = app::get('ome')->model("branch_product");
        $oBranch        = app::get('ome')->model("branch");
        $oInventory     = app::get('console')->model("inventory_apply");
        $oInventoryItem = app::get('console')->model("inventory_apply_items");
        $operator       = kernel::single('desktop_user')->get_name();
        
        //盘点单详情
        $items = array();
        $tmp_items = array();
        foreach ($data['items'] as $item)
        {
            $product    = $basicMaterialObj->dump(array('material_bn'=>$item['bn']), 'bm_id, material_bn, material_name');
            
            if (!$product) {
                $msg = "无此货号：".$item['bn'];
                return false;
            }
            
            $item['num'] = isset($item['num']) ? $item['num'] : $item['normal_num'] + $item['defective_num'];
            $tmp_items[] = array(
                'bn'            => $item['bn'],
                'name'          => $product['material_name'],
                'quantity'      => $item['num'],
                'product_id'    => $product['bm_id'],
                'normal_num'    => $item['normal_num'],
                'defective_num' => $item['defective_num'],
                'total_qty'     => $item['total_qty'],
            );
        }
       
        if (count($tmp_items)<=0){
           
           $msg = '没有明细';
           return false;
        }
        foreach ($tmp_items as $t_i){
            $t_i['quantity'] += $items[$t_i['product_id']]['quantity'] ? $items[$t_i['product_id']]['quantity'] : 0;
            $items[$t_i['product_id']] = $t_i;
        }
        if ($data['operate_time'] && (strtotime($data['operate_time']) && strtotime($data['operate_time']) != -1))//-1兼容5.1
            $date = strtotime($data['operate_time']);
        else
            $date = time();

        $memo           = $data['memo'];
        $wms_id         = $data['wms_id'];
        $warehouse      = $data['branch_bn'];
        $inventory_bn   = $data['inventory_bn'];
        $_inventory = $oInventory->getlist('inventory_apply_id,inventory_apply_bn',array('inventory_apply_bn'=>$data['inventory_bn']),0,1);
        $_inventory = $_inventory[0];
        if (empty($_inventory['inventory_apply_bn'])){

            //创建申请 
            $inventory = array(
                'inventory_apply_bn'    => $data['inventory_bn'],
                'type'                  => 'once',
                'append'                => 'false',
                'out_id'                => $warehouse,
                'wms_id'                => $wms_id,
                'inventory_date'        => $date,
                'memo'                  => $data['memo'],
                'inventory_apply_items' => $items,
            );
            
            kernel::database()->beginTransaction();
            $rs = $oInventory->save($inventory);
            if (!$rs){
                kernel::database()->rollBack();
                $msg = '盘点单保存失败';
                return false;
            }
            kernel::database()->commit();
        }else {
            //增加Items 
            kernel::database()->beginTransaction();
            foreach ($items as $item){
                $invi = $oInventory->db->selectrow('SELECT item_id,normal_num,defective_num FROM sdb_console_inventory_apply_items WHERE inventory_apply_id='.$_inventory['inventory_apply_id'].' AND bn=\''.$item['bn'].'\'');
                if(empty($invi)){
                    $item['inventory_apply_id'] = $_inventory['inventory_apply_id'];
                    $item['normal_num'] = $item['normal_num'] + $invi['normal_num'];
                    $item['defective_num'] = $item['defective_num'] + $invi['defective_num'];
                    $item['quantity'] = $item['normal_num'] + $item['defective_num'];
                    $oInventoryItem->insert($item);
                }else{
                    
                    $item['normal_num'] = $item['normal_num'] + $invi['normal_num'];
                    $item['defective_num'] = $item['defective_num'] + $invi['defective_num'];
                    $item['quantity'] = $item['normal_num'] + $item['defective_num'];
                    $oInventoryItem->update($item,array('item_id'=>$invi['item_id']));
                }
            }
            kernel::database()->commit();
        }
        return true;
    }

    /***
    * 完成盘点单
    *
    */
    function finish_inventory($inventory_bn,$branch_bn,$inventory_type,$items){
        
        $invObj = app::get('console')->model('inventory');
        $inv_itemObj = app::get('console')->model('inventory_items');
        $inv_aObj = app::get('console')->model('inventory_apply');
        $sdf = $inv_aObj->getlist('*',array('inventory_apply_bn'=>$inventory_bn),0,1 );
        $sdf = $sdf[0];
        $inv = $invObj->getlist('inventory_bn,inventory_id,branch_bn',array('inventory_apply_id'=>$sdf['inventory_apply_id'],'branch_bn'=>$branch_bn),0,1);
        $inv = $inv[0];
        if (!$inv){
            $inventory = array(
                'type'                  => $sdf['type'],
                'memo'                  => $sdf['memo'],
                'out_id'                => $sdf['out_id'],
                'branch_bn'             => $branch_bn,
                'create_date'           => time(),
                'inventory_bn'          => $invObj->gen_id(),
                'inventory_date'        => $sdf['inventory_date'],
                'inventory_apply_id'    => $sdf['inventory_apply_id'],
             );
            $rs = $invObj->save($inventory);
            $inventory_id = $inventory['inventory_id'];
        }else{
            $inventory_id = $inv['inventory_id'];
        }
        $overage = array();
        $shortage = array();
        $default_store = array();
        $invitem_sql = array();
        foreach ($items as $item){
            if ($inventory_type == '4'){#期初
                $type = '3';
            }else{
                $type = $item['normal_num'] > 0 ? '1' : '2';
            }
            $bn         = $item['bn'];
            $name       = $item['name'];
            $quantity   = $item['normal_num'];
            $product_id = $item['product_id'] ? $item['product_id'] : '0';
            $total_qty  = $item['total_qty'];
            $invitem_sql[] = "('$inventory_id','$bn','$name','$quantity','$product_id','$total_qty')";
            
            if ($inventory_type == '4'){
                $default_store['items'][] = array(
                    'bn'            => $bn,
                    'normal_num'    => $item['normal_num'],
                    'is_use_expire' => $item['is_use_expire'], //增加保质期判断
                    'inventory_item_id' => $item['inventory_item_id'],
                 );
            }else{
                if ($type == '1'){
                    $overage['items'][] = array(
                    'bn'            => $bn,
                    'normal_num'    => $item['normal_num'],
                    'is_use_expire' => $item['is_use_expire'], //增加保质期判断
                    'inventory_item_id' => $item['inventory_item_id'],
                    );
                }else{
                     $shortage['items'][] = array(
                        'bn'            => $bn,
                        'normal_num'    => abs($item['normal_num']),
                        'is_use_expire' => $item['is_use_expire'], //增加保质期判断
                        'inventory_item_id' => $item['inventory_item_id'],
                     );
                }
            }
            
        }
        if (count($invitem_sql)>0){
            $insert_invitemsql = 'INSERT INTO sdb_console_inventory_items (inventory_id,bn,name,quantity,product_id,total_qty) VALUES '.implode(',',$invitem_sql);
            $inv_itemObj->db->exec($insert_invitemsql);
        }

        //盘点表数据
        $inv = $invObj->getlist('inventory_bn,inventory_id,branch_bn',array('inventory_id'=>$inventory_id),0,1);
        $inv = $inv[0];
        
        #出入库
        $overage_result = true;
        $shortage_result = true;
        $default_store_result = true;
        //#为盘点单出入库
        if (count($overage['items'])>0){
            $overage = array_merge($inv,$overage);
            $overageLib = kernel::single('siso_receipt_iostock_overage');
            $overageLib->create($overage, $data, $msg);
            
        }
        if (count($shortage['items'])>0){
            $shortage = array_merge($inv,$shortage);
            $shortageLib = kernel::single('siso_receipt_iostock_shortage');
            $shortageLib->create($shortage, $data, $msg);
        }
        #盘盈
        if (count($default_store['items'])>0){
            $default_store = array_merge($inv,$default_store);
            $defaultstoreLib = kernel::single('siso_receipt_iostock_defaultstore');
            $defaultstoreLib->create($default_store, $data, $msg);
        }
       
        if ($overage_result && $shortage_result && $default_store_result){
            $rs = $inv_aObj->update(array('status'=>'confirmed','process_date'=>time()), array('inventory_apply_id'=>$sdf['inventory_apply_id']));
        }
            
        return true;
    }

    
}