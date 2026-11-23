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
 * 抖音平台库存回写
 */
class erpapi_shop_matrix_luban_request_product extends erpapi_shop_request_product
{
    /**
     * 获取店铺商品列表
     * @param $params
     * @return bool
     */

    public function itemsListGet($params)
    {
        $timeout = 15;
        $param   = array(
            'page' => isset($params['page']) ? $params['page'] : 1,
            'size' => 50,
        );
        
        //指定状态返回商品列表：0上架 1下架
        if (isset($params['goods_type']) && $params['goods_type'] !== '') {
            $param['status'] = $params['goods_type'];
        }
        
        //商品创建开始时间(没有传开始时间,默认拉取半年内的商品)
        if (empty($params['start_time'])) {
            $param['start_time'] = strtotime('-6 month');
        }else{
            $param['start_time'] = $params['start_time'];
        }
        
        //商品创建开始结束时间
        $param['end_time'] = time();
        
        //request
        $title  = "获取店铺(" . $this->__channelObj->channel['name'] . ')商品[page'. $param['page'] .']';
        $result = $this->__caller->call(SHOP_GET_ITEMS_LIST_RPC, $param, array(), $title, $timeout,$this->__channelObj->channel['shop_bn']);
        if ($result['rsp'] == 'succ') {
            $data    = json_decode($result['data'], true);
            $tmpData = array();
            foreach ($data['data'] as $key => $value) {
                $tmpData[] = array(
                    'iid'              => $value['product_id'],
                    'outer_product_id' => $value['outer_product_id'],
                    'title'            => $value['name'],
                    'sku_status'       => $value['status'],
                    'price'            => bcdiv($value['discount_price'], 100),
                );
            }
            $data['data'] = $tmpData;
        }
        $result['data'] = $data;
        unset($result['response']);
        return $result;
    }
    
    /**
     * 获取商品详情
     * @param $prodcut_id
     * @return mixed
     */
    public function itemsGet($prodcut_id)
    {
        $timeout = 10;
        $param   = array(
            'product_id' => $prodcut_id,
        );
        $title   = "获取店铺(" . $this->__channelObj->channel['name'] . ')商品ID：['. $prodcut_id .']商品详情';
        $result  = $this->__caller->call(SHOP_ITEM_GET, $param, array(), $title, $timeout,$this->__channelObj->channel['shop_bn']);
        if ($result['rsp'] == 'succ') {
            $data           = json_decode($result['data'], true);
            $result['data'] = $data['spec_prices'];
        }
        return $result;
    }
    
    /**
     * 获取订单明细
     * @param array $order_id  子单号
     * @param array $order_bn  订单号
     * @return mixed
     */
    public function itemsOrderGet($order_id,$order_bn)
    {
        $timeout = 10;
        $param   = array(
            'order_ids' => json_encode(array_values($order_id)),
        );
        $title   = "获取平台订单(" . $this->__channelObj->channel['name'] . ')明细';
        $result  = $this->__caller->call(SHOP_ORDER_SETTLE_GET, $param, array(), $title, $timeout,$order_bn);
        if ($result['rsp'] == 'succ' && !empty($result['data'])) {
            $result['data'] = json_decode($result['data'], true);
        }
        return $result;
    }
    
    /**
     * 格式化库存数据
     * @todo：抖音平台有按仓库级回传的场景,需要替换为抖音库存编码;
     * 
     * @param array $stockList
     * @return array
     */
    public function format_stocks($stockList)
    {
        static $warehouseList;
        
        if(empty($warehouseList)){
            //获取已绑定抖音平台的区域仓
            $branchLib = kernel::single('ome_branch');
            $shop_type = 'luban';
            $warehouseList = $branchLib->getLogisticWarehouse($shop_type);
        }
        
        //没有区域仓则直接回写
        if(empty($warehouseList)){
            return $stockList;
        }
        
        //list
        foreach ($stockList as $key => $val)
        {
            $branch_bn = $val['branch_bn'];
            if(empty($branch_bn)){
                continue;
            }
            
            //删除没有绑定抖音平台的区域仓
            $warehouseInfo = $warehouseList[$branch_bn];
            if(empty($warehouseInfo)){
                unset($stockList[$key]);
            }
            
            //转换成[抖音平台]外部仓库ID
            $val['warehouse_id'] = $branch_bn;
            
            //查询抖音区域仓冻结库存
            if ($this->__channelObj->channel['config']['store_type'] == 'yes') {
                $sku_id = isset($val['sku_id']) ? $val['sku_id'] : 0;
                if ($sku_id > 0) {
                    if(isset($val['stock_only'])) {
                        $val['quantity'] = $val['stock_only'];
                    }
                    $primaryBn = $this->__channelObj->channel['shop_bn'] . 'UpdateStock';
                    $sku_detail = $this->itemsSkuGet($sku_id,$primaryBn);
                    if ($sku_detail['rsp'] == 'succ' && isset($sku_detail['data']) && isset($sku_detail['data']['prehold_stock_map'])) {
                        if ($sku_detail['data']['sku_type'] == 1) {
                            if (!empty($sku_detail['data']['prehold_stock_map'])) {
                                if (isset($sku_detail['data']['prehold_stock_map'][$branch_bn])) {
                                    $quantity = $val['quantity'] - $sku_detail['data']['prehold_stock_map'][$branch_bn];
                                    $val['quantity'] = $quantity < 0 ? 0 : $quantity ;
                                }
                            }
                        }
                    }
                }
            }
            
            //向下取整(分多仓：PKG捆绑商品有除不尽的场景)
            $val['quantity'] = intval($val['quantity']);
            
            //删除不需要的字段
            unset($val['memo'], $val['branch_bn']);
            
            $stockList[$key] = $val;
        }
        
        return $stockList;
    }
    
    
    /**
     * 获取sku_id详情
     * @param $order_id
     * @param $order_bn
     * @return mixed
     */
     
    public function itemsSkuGet($sku_id,$primaryBn)
    {
        $timeout = 10;
        $param   = array(
            'sku_id' => $sku_id,
        );
        $title   = "获取抖音平台sku_id：[" . $sku_id . ']详情';
        $result  = $this->__caller->call(SHOP_ITEM_SKU_GET, $param, array(), $title, $timeout,$primaryBn);
        
        if ($result['rsp'] == 'succ' && !empty($result['data'])) {
            $result['data'] = json_decode($result['data'], true);
        }
        return $result;
    }
    
    /**
     * 设置SKU区域库存-发货时效
     * 
     * @param array $param 单个SKU请求参数
     * @return array
     */
    public function setSkuShipTime($param)
    {
        $timeout = 10;
        $primary_bn = $param['shop_product_bn'];
        $title = '店铺('. $this->__channelObj->channel['name'] .')商品['. $primary_bn .']设置区域库存发货时效';
        $callback = array();
        
        //request
        $result = $this->__caller->call(SHOP_SKU_SET_SELL_TYPE, $param, $callback, $title, $timeout, $primary_bn);
        if ($result['rsp'] == 'succ') {
            
            //$data = json_decode($result['data'], true);
        }
        
        return $result;
    }
    
    /**
     * 获取查询诊断商品接口
     *
     * @return null
     */
    protected function get_product_suggestion_apiname($sdf=[])
    {
        return SHOP_GET_PRODUCT_SUGGESTION_LIST;
    }
    
    /**
     * 获取查询诊断商品请求参数
     *
     * @param $sdf
     * @return array
     */
    protected function get_product_suggestion_params($sdf=[])
    {
        // param
        $param = parent::get_product_suggestion_params($sdf);
        
        // OMS抖音店铺编码（此字段不用传给平台API接口）
        $param['shop_bn'] = $this->__channelObj->channel['shop_bn'];
        
        // 页码
        $param['page_no'] = 1;
        
        // 每页查询数量（仅取 total，不需要拉全量列表）
        $param['page_size'] = 10;
        
        // 商品处理建议：force_offline强制下架, suggest_offline建议下架, optimize牵引优化
        if($sdf['suggestion']){
            $param['suggestion'] = $sdf['suggestion'];
        }else{
            $param['suggestion'] = 'force_offline';
        }
        
        // 批量查询商品处理建议：force_offline强制下架, suggest_offline建议下架, optimize牵引优化
        //$param['suggestion_list'] = array('force_offline', 'suggest_offline', 'optimize');
        
        return $param;
    }
}