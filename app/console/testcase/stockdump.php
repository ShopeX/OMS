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

class stockdump extends PHPUnit_Framework_TestCase
{
    function setUp() {

    }

    public function testStockdump(){
        $_POST = array (
          'is_products_id' => 
          array (
            0 => '1',
          ),
          'ckid' => 
          array (
            0 => '1',
          ),
          'product_id' => 
          array (
            1 => '1',
          ),
          'to_branch_id' => '2',
          'from_branch_id' => '1',
          'to_stock_price' => 
          array (
            1 => '2.00',
          ),
          'num' => 
          array (
            1 => '3',
          ),
          'memo' => '',
        );
        $oStockdump = app::get('console')->model('stockdump');
        $from_branch_id = $_POST['from_branch_id'];
        $to_branch_id = $_POST['to_branch_id'];
        $memo = $_POST['memo'];
        $num = $_POST['num'];
        $ckid = $_POST['ckid'];
        $product_id = $_POST['product_id'];
        $appro_price = $_POST['to_stock_price'];
        
        $op_name = kernel::single('desktop_user')->get_login_name();
        $op_name = $op_name== '' ? 'system' : $op_name;
        $options = array(
            'type' => 600,
            'otype' => 2,
            'op_name' => $op_name,
            #'in_status' => 8,
            'from_branch_id' => $from_branch_id,
            'to_branch_id' => $to_branch_id,
            'memo' => $memo,
        );
        $pStockObj = kernel::single('console_stock_products');

        foreach($ckid as $k=>$v){
           

           //判断选择商品库存是否充足
           $usable_store = $pStockObj->get_branch_usable_store($from_branch_id,$product_id[$v]);
           if($usable_store < $num[$v]){
                echo '行仓库可用库存不足';
           }
           
           $adata[$k] = array(
               'product_id'=>$product_id[$v],
               'num'=>$num[$v],
               'appro_price'=>$appro_price[$v],
           );
        }

        #$approResult = $oStockdump->to_savestore($adata,$options);
        if($approResult){
            #$result = kernel::single('console_iostockdata')->notify_stockdump($approResult['stockdump_id'],'create');
            //print_r($resulr);
            
        }
        $wms_id = kernel::single('ome_branch')->getWmsIdById(5);
        $data = array(
            'stockdump_bn'=>'20130604000001',     
        );
        kernel::single('console_event_trigger_stockdump')->updateStatus($wms_id, $data, true);
        #ocs发货单请求日志参数
//        $contents = @file_get_contents('http://ocs2.test.vmod.cn/ocs1.3.0encode/data/store.wms.transferorder.create');
//        $ocs_params = @unserialize($contents);
//
//        #讨管发货单请求日志参数
//        $tg_contents = @file_get_contents('http://localhost/oms/data/store.wms.transferorder.create');
//        $tg_params = @unserialize($tg_contents);
//
//        $diff_arr_item = middleware_func::compare_params($ocs_params,$tg_params);
//        print_r($diff_arr_item);

    }
}
