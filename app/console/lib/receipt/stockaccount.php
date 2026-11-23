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
 * 库存对账数据处理
 * 
 * 
 */
class console_receipt_stockaccount{

    public function checking($data){
        
        #$wms_message = kernel::service('wms.message');
              $stock_account_model = app::get('console')->model('stock_account');
              $stock_account_items_model = app::get('console')->model('stock_account_items');
              $filter_item = array();
              $batch = $data['batch'];
              $time = time();
              list($year,$month,$day) = array(date('Y',$time),date('m',$time),date('j',$time));
              foreach($data['items'] as $value){
              $bn = $value['product_bn'];
              #判断一下货品是否存在
              $materialObj = app::get('material')->model('basic_material');
              $material = $materialObj->dump(array('material_bn'=>$bn),'bm_id');
              if(!$material){
                continue;
              }
                     //系统当时库存
                     $original_goods_stock = $original_rejects_stock = 0;

           #良品#
           $sql    = "SELECT bp.store,bp.store_freeze 
                      FROM sdb_ome_branch_product as bp 
                      LEFT JOIN (sdb_material_basic_material AS a,sdb_ome_branch as b) ON (bp.product_id = a.bm_id AND bp.branch_id = b.branch_id ) 
                      WHERE b.type <> 'damaged' AND a.material_bn = '". $bn ."' AND b.wms_id = ".$data['wms_id']."";

           $now_stock = kernel::database()->select($sql);
           #print_r($now_stock);
           foreach($now_stock as $v){
            //$original_goods_stock += (int)($v['store']-$v['store_freeze']);
                        $original_goods_stock += (int)$v['store'];
           }
           #不良品#
           $sql    = "SELECT bp.store,bp.store_freeze 
                      FROM sdb_ome_branch_product as bp 
                      LEFT JOIN (sdb_material_basic_material AS a,sdb_ome_branch as b) ON (bp.product_id = a.bm_id AND bp.branch_id = b.branch_id) 
                      WHERE b.type = 'damaged' AND a.material_bn = '". $bn ."' AND b.wms_id = ".$data['wms_id']."";
           
           $now_stock = kernel::database()->select($sql);
           foreach($now_stock as $v){
            //$original_rejects_stock += (int)($v['store']-$v['store_freeze']);
                $original_rejects_stock += (int)$v['store'];
           }
                   
           //保存主记录数据
                    #良品
           $normal_data = array(
            'account_bn' => $bn,
            'account_ym' => $year.'-'.$month,
            'account_time' => $time,
            'account_type' => 1,
            'wms_id' => $data['wms_id'],
           );
            $normal_data['d'.$day] = $original_goods_stock.'|'.$value['normal_num'];
            
            //是否已存在当月记录
           $is_exist = $stock_account_model->getList('account_id',array('account_bn'=>$bn,'account_ym'=>$year.'-'.$month,'wms_id'=>$data['wms_id'],'account_type'=>1),0,1);
           if( !empty($is_exist[0]['account_id']) ){
                $normal_data['account_id'] = $is_exist[0]['account_id'];
           }
           
                     $stock_account_model->save($normal_data);

            #不良品
            $defective_data = array(
            'account_bn' => $bn,
            'account_ym' => $year.'-'.$month,
            'account_time' => $time,
            'account_type' => 0,
            'wms_id' => $data['wms_id'],
           );
            $defective_data['d'.$day] = $original_rejects_stock.'|'.$value['defective_num'];    
            //是否已存在当月记录
           $is_exist = $stock_account_model->getList('account_id',array('account_bn'=>$bn,'account_ym'=>$year.'-'.$month,'wms_id'=>$data['wms_id'],'account_type'=>0),0,1);
           if( !empty($is_exist[0]['account_id']) ){
                $defective_data['account_id'] = $is_exist[0]['account_id'];
           }
           $stock_account_model->save($defective_data);
           
            //保存差异记录数据
            $_data_items['batch'] = $batch;
            $_data_items['account_bn'] = $bn;
            $_data_items['account_time'] = $time;
            $_data_items['wms_id'] = $data['wms_id'];
            $_data_items['warehouse_code'] = $value['branch_bn'];
            $stock_items = $stock_account_items_model->getlist('*',array('batch'=>$batch,'account_bn'=>$bn),0,1);
            
            if(empty($stock_items[0]['items_id']) || $batch == ''){
                $_data_items['account_goods_stock'] = $original_goods_stock;
                $_data_items['original_goods_stock'] = $value['normal_num'];
                $_data_items['goods_diff_nums'] = ($original_goods_stock-$value['normal_num']);
                $_data_items['account_rejects_stock'] = $original_rejects_stock;
                $_data_items['original_rejects_stock'] = $value['defective_num'];
                $_data_items['rejects_diff_nums'] = ($original_rejects_stock-$value['defective_num']);
             
                $stock_account_items_model->insert($_data_items);
            }else{
                $_data_items['account_goods_stock'] = $original_goods_stock;
                $_data_items['original_goods_stock'] = ($stock_items[0]['original_goods_stock']+$value['normal_num']);
                $_data_items['goods_diff_nums'] = ($original_goods_stock-$_data_items['original_goods_stock']);
                $_data_items['account_rejects_stock'] = $original_rejects_stock;
                $_data_items['original_rejects_stock'] = ($stock_items[0]['original_rejects_stock']+$value['defective_num']);
                $_data_items['rejects_diff_nums'] = ($original_rejects_stock-$_data_items['original_rejects_stock']);
                
                $stock_account_items_model->update($_data_items,array('items_id'=>$stock_items[0]['items_id']));
            }
            $_data_items = null;
        }
        return true;
    }

}