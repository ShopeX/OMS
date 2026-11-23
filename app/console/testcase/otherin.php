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

class otherin extends PHPUnit_Framework_TestCase
{
    function setUp() {

    }

    public function testOtherin(){
        $_POST = array (
          'at' => 
          array (
            1 => '6',
            2 => '6',
          ),
          'pr' => 
          array (
            1 => '1',
            2 => '1',
          ),
          'io' => '1',
          'iostockorder_name' => '20130517入库单',
          'supplier' => 's001',
          'supplier_id' => '1',
          'branch' => '1',
          'type_id' => '70',
          'iso_price' => 0,
          'barcode' => '',
          'bn' => 
          array (
            1 => 'test001',
            2 => 'test002',
          ),
          'name' => '',
          'product_name' => 
          array (
            1 => 'test001',
            2 => 'test002',
          ),
          'unit' => 
          array (
            1 => '',
            2 => '',
          ),
          'memo' => '',
          'operator' => 'admin',
          'products' => 
          array (
            1 => 
            array (
              'bn' => 'test001',
              'nums' => '6',
              'unit' => '',
              'name' => 'test001',
              'price' => '1',
            ),
            2 => 
            array (
              'bn' => 'test002',
              'nums' => '6',
              'unit' => '',
              'name' => 'test002',
              'price' => '1',
            ),
          ),
        );
        $_POST['iso_price'] = $_POST['iso_price'] ? $_POST['iso_price'] : 0;
        $oBranchProduct = app::get('ome')->model('branch_product');
        $branch_id = $_POST['branch'];
        $products = array();
        foreach($_POST['bn'] as $product_id=>$bn){
            
            $products[$product_id] = array('bn'=>$bn,
                'nums'=>$_POST['at'][$product_id],
                'unit'=>$_POST['unit'][$product_id],
                'name'=>$_POST['product_name'][$product_id],
                'price'=>$_POST['pr'][$product_id],
            );
        }
        $_POST['products'] = $products;
        $iso_id = kernel::single('console_iostockorder')->save_iostockorder($_POST,$msg);
        $wms_id = kernel::single('ome_branch')->getWmsIdById(1);
        
        if ($iso_id){
            //事件解发向WMS发起通知单
            #$result = kernel::single('console_iostockdata')->notify_otherstock($_POST['io'],$iso_id,'create');
            
            $result = kernel::single('console_event_trigger_otherstockin')->create(array('iso_id'=>$iso_id),false);
           print_r($result);
        }
        $iostockorderObj = app::get('taoguaniostockorder')->model('iso');
        $iostockorderObj->update(array('check_status'=>'2'),array('iso_id'=>$iso_id));
    }
}
