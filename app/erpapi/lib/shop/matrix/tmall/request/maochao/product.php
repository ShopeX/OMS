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
 * @author ykm 2022/6/27 18:09:55
 * @describe 处理店铺商品相关类
 */

class erpapi_shop_matrix_tmall_request_maochao_product extends erpapi_shop_request_product {

    protected function getUpdateStockApi() {
        $api_name = SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC;
        return $api_name;
    }

    private function getStoreCode($branch_bn) {

        static $storeCode = [];
        if(isset($storeCode[$branch_bn])) {
            return $storeCode[$branch_bn];
        }

        $branch = app::get('ome')->model('branch')->dump(array ('branch_bn'=>$branch_bn,'check_permission'=>'false'));
        if(empty($branch)) {
            return $branch_bn;
        }

        $relation = app::get('ome')->model('branch_relation')->dump(array ('branch_id'=>$branch['branch_id'],'type' => '3pl'));
        if(empty($relation)) {
            return $branch_bn;
        }

        $storeCode[$branch_bn] = $relation['relation_branch_bn'];

        return $storeCode[$branch_bn];
    }


    public function _getUpdateStockParams($stockList) {
        $firstStock = current($stockList);
        $shop_id = $this->__channelObj->channel['shop_id'];

        if(empty($firstStock['branch_bn'])) {
            // $memo  = '未使用分仓独立回写，不能回写';
            // $optLogModel = app::get('inventorydepth')->model('operation_log');
            // $optLogModel->write_log('shop', $shop_id, 'stockup',$memo);
            return [];
        }

        $bns = array();
        foreach ($stockList as $key => $val)
        {
            $product_bn = trim($val['bn']);
            $bns[$product_bn] = $product_bn;
        }
        
        //按店铺+货号查询
        $skuObj = app::get('inventorydepth')->model('shop_skus');
        $tempList = $skuObj->getList('shop_sku_id,shop_product_bn,shop_iid', array('shop_id'=>$shop_id, 'shop_product_bn'=>$bns));
        if(empty($tempList)){
            return [];
        }
        $shopBnList = [];
        foreach ($tempList as $key => $value) {
            $shopBnList[$value['shop_product_bn']][] = $value;
        }
        $detail_operation_list = [];
        $list_quantity = [];
        foreach ($stockList as $key => $value) {
            $list_quantity[$value['bn']] = [
                'bn' => $value['bn'],
                'quantity' => $value['quantity'],
            ];
            foreach ($shopBnList[$value['bn']] as $k => $val) {
                $list_quantity[$value['bn']]['sc_item_id'] = $val['shop_iid'];
                $tmp = [
                    "item" => [
                        "outer_id" => $value['bn'],
                        "sc_item_id" => $val['shop_iid']
                    ],
                    "inventory_line_list" => [[
                        "inventory_line" => [
                            "quantity"=> $value['quantity']
                        ]
                    ]],
                    "additional_info" => [
                        "attribute" => [
                            "inv_operate_mode" => "FULLAMOUNT",
                            "supplier_id" => $this->__channelObj->channel['config']['supplier_id'],
                        ]
                    ],
                    "location"=> [
                        "store_code"=> $this->getStoreCode($value['branch_bn'])
                    ],
                    "detail_order"=> [
                        "operation_detail_order_id"=> time().rand(1000, 9999),
                    ]
                ];
                $detail_operation_list[] = $tmp;
            }
        }
        if(empty($list_quantity)) {
            return [];
        }
        $inventory_main_operation = [[
            'main_order'=>[
                'user_id' =>  $this->__channelObj->channel['addon']['tb_user_id'],
                'operation_order_id' => $this->uniqid()
            ],
            'detail_operation_list' => $detail_operation_list,
        ]];
        $return = [
            'tmall_type'=>'direct_marketing',
            'inventory_main_operation' =>json_encode($inventory_main_operation),
            'list_quantity' => json_encode(array_values($list_quantity))
        ];
        return $return;
    }

    #实时下载店铺商品
    public function skuAllGet($sdf)
    {
        $timeout = 20;
        $param = array(
            'page_no' => $sdf['page'],
            'page_size' => $sdf['page_size'],
            'begin_time' => $sdf['start_time'],
            'end_time' => $sdf['end_time'],
            'supplier_id' => $sdf['supplier_id'],
        );

        if ($sdf['scroll_id']) {
            $param['scroll_id'] = $sdf['scroll_id'];
        }

        $title = "获取店铺(" . $this->__channelObj->channel['name'] .')商品';

        $api_name = SHOP_GET_SUPPLIER_PRODUCTS;
        if (is_array($this->__channelObj->channel['config']) && in_array('MCZZ',(array) $this->__channelObj->channel['config']['sub_business_type'])) {
            $api_name = SHOP_SUPPLIER_PRODUCTS_FIND;
        }

        $result = $this->__caller->call($api_name,$param,array(),$title,$timeout, $param['supplier_id']);
        if ($result['res_ltype'] > 0) {
            for ($i=0;$i<3;$i++) {
                $result = $this->__caller->call($api_name,$param,array(),$title,$timeout, $param['supplier_id']);
                if ($result['res_ltype'] == 0) {
                    break;
                }
            }
        }

        $data =  $result['data'] ? json_decode($result['data'], true) : [];

        if ($api_name == SHOP_GET_SUPPLIER_PRODUCTS) {
            $result['data'] = $this->supplierProductsGetResponse($data);
        }

        if ($api_name == SHOP_SUPPLIER_PRODUCTS_FIND) {
            $result['scroll_id'] = $data['data']['scroll_id'];
            $result['total_count'] = $data['data']['total_count'];

            $result['data'] = $this->supplierProductsFindResponse($data);
        }
        
        return $result;
    }

    private function supplierProductsGetResponse($data) {

        $formatData = [];
        if(!$data || empty($data['data']['data']['page_data']['page_data'])) {
            return $formatData;
        }
        foreach ($data['data']['data']['page_data']['page_data'] as $value) {
            $formatData[] = [
                'iid' => $value['sc_item_id'],
                'title' => $value['sc_item_name'],
                'outer_id' => $value['outer_id'],
                'sku' => [
                    'outer_id' => $value['outer_id'],
                    'sku_id' => $value['supplier_id'],
                    'barcode' => $value['barcode'],
                ]
            ];
        }

        return $formatData;
    }

    private function supplierProductsFindResponse($data) {
        $formatData = [];

        if(!$data || empty($data['data']['data'])) {
            return $formatData;
        }

        foreach ($data['data']['data']['supply_product_info_response'] as $value) {
            $formatData[] = [
                'iid' => $value['supplier_product_id'],
                'title' => $value['supplier_product_name'],
                'outer_id' => $value['outer_id'],
                'sku' => [
                    'outer_id' => $value['outer_id'],
                    'sku_id' => $value['supplier_product_id'],
                    'barcode' => $value['barcode'],
                ]
            ];
        }

        return $formatData;
    }
}
