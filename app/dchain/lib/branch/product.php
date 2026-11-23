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
 * @Author: xueding@shopex.cn
 * @Datetime: 2022/4/20
 * @Describe: 外部优仓商品lib类
 */
class dchain_branch_product
{
    /**
     * @param array $sku_data sku原始数据
     * @param array $outer_data 请求优仓返回数据
     * @param array $dchainBranch 优仓信息
     */
    public function saveForeignSku($sku_data, $outer_data, $dchainBranch)
    {
        //result：0=全部失败，1=全部成功，2=部分成功
        $foreignSkuMdl  = app::get('dchain')->model('foreign_sku');
        $foreignSkuData = array();
        $outerItemDataList = $outer_data['detail']['detail_item'];
    
        $shopIidList = array_column($sku_data,'shop_iid');
        $foreignList = $foreignSkuMdl->getList('shop_sku_id,shop_product_id',array('shop_product_id'=>$shopIidList));
        $shopSkuId = array_column($foreignList,'shop_sku_id');
        $shopProductId = array_column($foreignList,'shop_product_id');
        if ($sku_data) {
            foreach ($sku_data as $key => $value) {
                //判断是捆绑还是普通商品
                if (!$value['inner_type']) {
                    $outerItemDataList = array_column($outerItemDataList, null, 'sc_item_code');
                } else {
                    $outerItemDataList = array_column($outerItemDataList, null, 'combine_sc_item_code');
                }
                $outerItemData = $outerItemDataList[$value['shop_product_bn']];
                $productBn     = isset($outerItemData['sc_item_code']) ? $outerItemData['sc_item_code'] : $outerItemData['combine_sc_item_code'];
                $productId     = isset($outerItemData['sc_item_id']) ? $outerItemData['sc_item_id'] : $outerItemData['combine_sc_item_id'];
            
                //本地与优仓映射数据
                $item                       = array();
                $item['dchain_id']          = $dchainBranch['channel_id'];
                $item['inner_sku']          = $value['shop_product_bn'];
                $item['inner_product_id']   = $value['product_id'];
                $updateItem['inner_type']   = $value['inner_type'];
                $updateItem['outer_sku']    = $productBn;
                $updateItem['outer_sku_id'] = $productId;
                //设置更新库存
                if ($value['product_id'] && !empty($productId)) {
                    $salesBasicMaterialMdl = app::get('material')->model('sales_basic_material');
                    $bmIds = $salesBasicMaterialMdl->getList('bm_id',array('sm_id'=>$value['product_id']));
                    if ($bmIds) {
                        app::get('material')->model('basic_material_stock')->update(['max_store_lastmodify_upset_sql'=>'UNIX_TIMESTAMP()'], ['bm_id'=>array_column($bmIds,'bm_id')]);
                    }
                }
                if ($outerItemData['sc_item_bar_code']) {
                    $updateItem['outer_bar_code'] = $outerItemData['sc_item_bar_code'];
                }
                $updateItem['sync_status'] = $outerItemData['success'] ? '3' : '1';
                $updateItem['addon']       = $outerItemData['success'] ? '' : $outerItemData['message'];
                $item['shop_sku_id']       = $value['shop_sku_id'];
                $item['shop_product_id']   = $value['shop_iid'];
                if (in_array($value['shop_iid'], $shopProductId)) {
                    $where['dchain_id']       = $dchainBranch['channel_id'];
                    $where['shop_product_id'] = $value['shop_iid'];
                    $where['inner_sku']       = $value['shop_product_bn'];
                    if ($value['shop_sku_id'] && in_array($value['shop_sku_id'], $shopSkuId)) {
                        $where['shop_sku_id'] = $value['shop_sku_id'];
                    }
                    $foreignSkuMdl->update($updateItem, $where);
                    continue;
                }
                //系统货号
                if (empty($value['shop_iid']) && empty($value['shop_sku_id'])) {
                    $foreignSkuMdl->update($updateItem, array('inner_sku' => $value['shop_product_bn']));
                    continue;
                }
                $item             = array_merge($item, $updateItem);
                $foreignSkuData[] = $item;
            }
        
            $sql = ome_func::get_insert_sql($foreignSkuMdl, $foreignSkuData);
            kernel::database()->exec($sql);
        }
    }
    
    /**
     * 商货品关联状态更新
     * @param $params
     * @param $dchainBranch
     */
    public function updateMappingStatus($params, $dchainBranch)
    {
        $foreignSkuMdl = app::get('dchain')->model('foreign_sku');
        foreach ($params['detail']['detail_item'] as $key => $value) {
            $item['mapping_status'] = $value['success'] ? '2' : '1';
            $item['mapping_addon']  = $value['success'] ? '' : $value['message'];
            $where                  = array(
                'dchain_id'       => $dchainBranch['channel_id'],
                'shop_product_id' => $value['item_id'],
                'outer_sku'       => $value['sc_item_code'],
            );
            if ($value['sku_id']) {
                $where['shop_sku_id'] = $value['sku_id'];
            }
            $foreignSkuMdl->update($item, $where);
        }
    }
    
    /**
     * [回写库存成功]更新商货品关联信息
     *
     * @param int $node_id
     * @param array $bnList
     * @param string $error_msg
     * @return bool
     */
    public function requestMappingProduct($node_id, $bnList, &$error_msg = null)
    {
        $shopMdl    = app::get('ome')->model('shop');
        $skuMdl     = app::get('dchain')->model('foreign_sku');
        $requestLib = kernel::single('erpapi_router_request');
        
        if (empty($node_id) || empty($bnList)) {
            $error_msg = '更新商货品关联信息，无效的请求.';
            return false;
        }
        
        $dchainBranch = app::get('channel')->model('channel')->db_dump(array('node_id' => $node_id, 'channel_type' => 'dchain'), 'channel_id,channel_bn,channel_name,channel_type');
        if (empty($dchainBranch)) {
            $error_msg = '没有优仓绑定关系，无效的请求.';
            return false;
        }
        $channel_id = $dchainBranch['channel_id'];
        
        //shop
        $shopInfo = $shopMdl->db_dump(array('node_id' => $node_id), 'shop_id,shop_bn,name');
        if (empty($shopInfo)) {
            $error_msg = 'node_id：' . $node_id . '，关联店铺不存在.';
            return false;
        }
        
        //sku
        $filter   = array('dchain_id' => $channel_id, 'inner_sku' => $bnList);
        $tempList = $skuMdl->getList('inner_sku,shop_sku_id,shop_product_id,outer_bar_code,mapping_status', $filter);
        if (empty($tempList)) {
            $error_msg = '没有可更新的商品.';
            return false;
        }
        
        //list
        $skuList = array();
        foreach ($tempList as $key => $val) {
            //过滤已经关联成功的商品
            if ($val['mapping_status'] == '2') {
                continue;
            }
            
            //$val['shop_product_bn'] = $val['inner_sku']; //内部sku编码
            //$val['shop_iid'] = $val['shop_product_id']; //店铺商品ID
            //$val['shop_barcode'] = $val['outer_bar_code']; //外部商家条码
            
            //skus
            $skuList[] = array(
                'item_id'                       => $val['shop_product_id'], //店铺商品ID
                'sku_id'                        => $val['shop_sku_id'],
                'sc_item_code'                  => $val['inner_sku'], //内部sku编码
                'need_sync_sc_item_inv_to_item' => 1,
            );
        }
        
        if (empty($skuList)) {
            $error_msg = '没有需要更新的商品.';
            return false;
        }
        
        //每次执行50个
        $limit    = 30;
        $dataList = array_chunk($skuList, $limit);
        
        //request
        foreach ($dataList as $key => $items) {
            $param          = array();
            $param['items'] = $items;
            
            $result = $requestLib->set('dchain', $shopInfo['shop_id'])->product_item_mapping($param);
            if ($result['rsp'] == 'succ' && !empty($result['data'])) {
                //更新商货品关联信息
                $this->updateMappingStatus($result['data'], $dchainBranch);
            }
        }
        
        return true;
    }
}