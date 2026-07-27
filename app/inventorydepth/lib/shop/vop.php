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
 * 唯品会平台库存Lib类
 *
 * @author wangbiao@shopex.cn
 * @version 2025.04.15
 */
class inventorydepth_shop_vop
{
    /**
     * 检查是否需要减掉唯品会店铺购物车的库存占用
     *
     * @param $shopInfo array 单个店铺信息
     * @return void
     */
    public function isSubtractVopCartStock($shopInfo, &$error_msg=null)
    {
        //shop_bn
        $shop_bn = $shopInfo['shop_bn'];
        
        //过滤唯品会店铺类型
        if(empty($shopInfo['node_type']) || in_array($shopInfo['node_type'], array('vop'))){
            $error_msg = 'vop唯品会店铺不执行';
            return false;
        }
        
        //指定执行的店铺类型
        //if(!in_array($shop['node_type'], array('taobao','luban','360buy','pinduoduo','xhs'))){
        //    $error_msg = '不支持店铺类型'. $shop['node_type'];
        //    return false;
        //}
        
        //config
        $is_vop_cart_stock_freeze = false;
        if($shopInfo['config']){
            $shops_config = @unserialize($shopInfo['config']);
            if($shops_config['vop_cart_stock_freeze'] == 'yes'){
                $is_vop_cart_stock_freeze = true;
            }
        }
        
        //check店铺未开启配置
        if(!$is_vop_cart_stock_freeze){
            $error_msg = '店铺已未配置[减掉唯品会购物车预占]';
            return false;
        }
        
        //仓库关联的店铺
        $branchRelationList = app::get('ome')->getConf('shop.branch.relationship');
        if(empty($branchRelationList) || empty($branchRelationList[$shop_bn])){
            $error_msg = '没有仓库关联的店铺';
            return false;
        }
        
        //shop
        $filter = array(
            'filter_sql' =>'{table}node_id is not null AND {table}node_id !="" AND node_type="vop" ',
        );
        $vopShopList = app::get('ome')->model('shop')->getList('shop_id,shop_bn,node_type', $filter);
        if(empty($vopShopList)){
            $error_msg = '没有唯品会店铺';
            return false;
        }
        
        //format
        $shopBranchs = array();
        foreach ($branchRelationList as $shop_bn_key => $branchs)
        {
            foreach ($branchs as $branch_id => $branch_bn)
            {
                $shopBranchs[$branch_bn][$shop_bn_key] = array(
                    'branch_id' => $branch_id,
                    'branch_bn' => $branch_bn,
                    'shop_bn' => $shop_bn_key,
                );
            }
        }
        
        //当前店铺关联的仓库列表
        $vopShops = array();
        $currentBranchs = $branchRelationList[$shop_bn];
        foreach ($currentBranchs as $branch_id => $branch_bn)
        {
            if(isset($shopBranchs[$branch_bn]) && $shopBranchs[$branch_bn]){
                $shopBns = array_keys($shopBranchs[$branch_bn]);
                
                //供货仓是否包含唯品会店铺
                foreach ($vopShopList as $vopKey => $vopShopInfo)
                {
                    $vop_shop_bn = $vopShopInfo['shop_bn'];
                    
                    //检查唯品会店铺是否开启回写
                    $request = kernel::single('inventorydepth_shop')->getStockConf($vopShopInfo['shop_id']);
                    if($request != 'true') {
                        continue;
                    }
                    
                    //check
                    if(in_array($vop_shop_bn, $shopBns)){
                        $vopShops[$vop_shop_bn] = $vopShopInfo;
                    }
                }
            }
        }
        
        return $vopShops;
    }
    
    /**
     * 批量查询商品库存
     *
     * @param $shopInfo
     * @param $barcodeList
     * @return void
     */
    public function getSkuCartFreezeStocks($shopInfo, $barcodeList)
    {
        $skusObj = app::get('inventorydepth')->model('shop_skus');
        $shopLib = kernel::single('ome_shop');
        
        //shop_id
        $shop_id = $shopInfo['shop_id'];
        $result = array('rsp'=>'fail', 'error_msg'=>'', 'data'=>array());
        
        //check
        if(empty($shop_id) || empty($barcodeList)){
            $error_msg = '无效的请求数据';
            $result['error_msg'] = $error_msg;
            
            return $result;
        }
        
        //唯品会店铺配置：常态合作编号
        $cooperation_no = $shopLib->getShopVopCooperationNo($shop_id);
        
        //shop_skus
        $shopSkuList = $skusObj->getList('id,shop_product_bn,shop_sku_id', array('shop_id'=>$shop_id, 'shop_sku_id'=>$barcodeList));
        if($shopSkuList){
            $shopSkuList = array_column($shopSkuList, null, 'shop_sku_id');
        }
        
        //sku
        $skuList = array();
        foreach ($barcodeList as $key => $barcode)
        {
            //check
            if(empty($barcode)){
                continue;
            }
            
            //check条形码不在店铺商品列表里
            if(!isset($shopSkuList[$barcode])){
                continue;
            }
            
            $skuInfo = array('barcode'=>$barcode);
            
            //cooperation_no
            if($cooperation_no){
                $skuInfo['cooperation_no'] = $cooperation_no;
            }
            
            $skuList[] = $skuInfo;
        }
        
        //check
        if(empty($skuList)){
            $error_msg = '没有可请求的SKU或者条形码不在店铺商品SKU列表里';
            $result['error_msg'] = $error_msg;
            
            return $result;
        }
        
        //params
        $params = array();
        $params['batchGetSkuStockRequest'] = array(
            'sku_list' => $skuList,
        );
        
        //request
        $rspResult = kernel::single('erpapi_router_request')->set('shop', $shop_id)->product_batchGetSkuStock($params);
        if($rspResult['rsp'] != 'succ'){
            $error_msg = '请求查询失败:'. $rspResult['error_msg'];
            $result['error_msg'] = $error_msg;
            
            return $result;
        }
        
        if(empty($rspResult['dataList'])){
            $error_msg = '查询返回为空';
            $result['error_msg'] = $error_msg;
            
            return $result;
        }
        
        //dataList
        $skuStocks = array();
        foreach ($rspResult['dataList'] as $key => $val)
        {
            $barcode = $val['barcode'];
            
            //过滤库存占用为0的记录
            if(!isset($val['current_hold']) && !isset($val['circuit_break_value'])){
                continue;
            }
            
            //fromat
            $val['leaving_stock'] = intval($val['leaving_stock']);
            $val['current_hold'] = intval($val['current_hold']);
            $val['circuit_break_value'] = intval($val['circuit_break_value']);
            $val['unpaid_hold'] = intval($val['unpaid_hold']);
            
            //set
            if(!isset($skuStocks[$barcode])){
                $skuStocks[$barcode] = array(
                    'barcode' => $barcode,
                    'leaving_stock' => $val['leaving_stock'],
                    'current_hold' => $val['current_hold'],
                    'circuit_break_value' => $val['circuit_break_value'],
                    'unpaid_hold' => $val['unpaid_hold'],
                    'warehouse_flag' => intval($val['warehouse_flag']), //分区仓库代码ID
                    'area_warehouse_id' => intval($val['area_warehouse_id']), //仓库编码标识
                );
            }else{
                $skuStocks[$barcode]['leaving_stock'] += $val['leaving_stock']; //剩余库存
                $skuStocks[$barcode]['current_hold'] += $val['current_hold']; //库存占用
                //$skuStocks[$barcode]['circuit_break_value'] += $val['circuit_break_value']; //熔断值
                $skuStocks[$barcode]['unpaid_hold'] += $val['unpaid_hold']; //未支付占用数
                
                // 熔断值（现在多个条形码时，不进行累加熔断值）
                $skuStocks[$barcode]['circuit_break_value'] = $val['circuit_break_value']; //熔断值
            }
        }
        
        //data
        $result['rsp'] = 'succ';
        $result['data'] = $skuStocks;
        
        return $result;
    }
}
