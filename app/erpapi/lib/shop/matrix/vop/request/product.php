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
 * vop平台库存回写
 */
class erpapi_shop_matrix_vop_request_product extends erpapi_shop_request_product
{
    /**
     * format_stocks
     * @param mixed $stocks stocks
     * @return mixed 返回值
     */

    public function format_stocks($stocks)
    {
        $skuObj = app::get('inventorydepth')->model('shop_skus');
        $shopLib = kernel::single('ome_shop');
        
        $shop_id = $this->__channelObj->channel['shop_id'];
        
        //唯品会店铺配置：常态合作编号
        $cooperation_no = $shopLib->getShopVopCooperationNo($shop_id);
        
        //bns
        $bns = array();
        foreach ($stocks as $key => $val)
        {
            $barcode = trim($val['barcode']); // 用条码barcode去匹配
            
            //指定常态合作编码：cooperation_no回写库存
            if($cooperation_no){
                $val['num_iid'] = $cooperation_no;
            }
            
            //sku_id
            $val['sku_id'] = $barcode;
            
            $bns[$barcode] = $val;
        }
        
        //按店铺+货号查询
        $tempList = $skuObj->getList('id,shop_iid,shop_product_bn,shop_sku_id', array('shop_id'=>$shop_id, 'shop_sku_id'=>array_keys($bns)));
        if (empty($tempList)) {
            return false;
        }
        
        $itemStocks = [];
        foreach ($tempList as $k => $v)
        {
            $barcode = $v['shop_sku_id'];
            
            if ($bns[$barcode]) {
                $itemStocks[] = $bns[$barcode];
            }
        }
        unset($tempList, $stocks);
        
        return $itemStocks;
    }

    #实时下载店铺商品
    /**
     * itemsAllGet
     * @param mixed $filter filter
     * @param mixed $offset offset
     * @param mixed $limit limit
     * @return mixed 返回值
     */
    public function itemsAllGet($filter, $offset = 0, $limit = 100)
    {
        $shopLib = kernel::single('ome_shop');
        
        $shop_id = $this->__channelObj->channel['shop_id'];
        
        $timeout = 20;
        $param   = array(
            'page_no'   => $offset,
            'page_size' => $limit,
        );
        
        //唯品会店铺配置：常态合作编号
        $cooperation_no = $shopLib->getShopVopCooperationNo($shop_id);
        if($cooperation_no){
            //指定常态合作编码：cooperation_no进行下载商品
            $param['iid'] = $cooperation_no;
        }
        
        $param = array_merge((array) $param, (array) $filter);
        $title = "获取店铺(" . $this->__channelObj->channel['name'] . ')商品';
        
        //primary_bn
        $primary_bn = 'downloadSku'. date('md');
        
        //request
        $rsp   = $this->__caller->call(SHOP_ITEM_SKU_LIST, $param, array(), $title, $timeout, $primary_bn);
        
        //check
        if ($rsp['rsp'] =='succ' && $rsp['data']) {
            $data = $rsp['data'];
            if (!is_array($rsp['data'])) {
                $data = json_decode($rsp['data'], 1);
            }
            
            //msg
            if (isset($data['msg'])) {
                $data['msg'] = json_decode($data['msg'], 1);
                if($data['msg']){
                    $rsp['data'] = $data['msg']['result'];
                }
            }
        }
        
        return $rsp;
    }
    
    /**
     * 批量查询商品库存
     * API文档：https://vop.vip.com/home#/api/method/detail/vipapis.inventory.InventoryService-1.0.0/batchGetSkuStock
     * 
     * @param $param
     * @return array
     */
    public function batchGetSkuStock($param)
    {
        $title = '批量查询商品库存('. $this->__channelObj->channel['name'] .')';
        $primary_bn = 'BatchGetSkuStock'. date('md');;
        
        //data
        $requestData = $param;
        
        //params
        $requestParams = array(
            'vop_type' => 'Inventory',
            'vop_method' => 'batchGetSkuStock', //方法名
            'is_multiple_params' => 0, //是否多个参数：默认1,必须填写0
            'data' => json_encode($requestData),
        );
        
        //request
        $result = $this->__caller->call(SHOP_COMMONS_VOP_JIT, $requestParams, array(), $title, 10, $primary_bn);
        if($result['rsp'] == 'succ'){
            $result = $this->batchGetSkuStock_callback($result);
        }
        
        return $result;
    }
    
    /**
     * [格式化]商品库存数据
     * 
     * @param $response
     * @param $callback_params
     * @return array
     */
    public function batchGetSkuStock_callback($response, $callback_params=NULL)
    {
        $total = 0;
        
        //check
        if($response['rsp'] != 'succ'){
            return $response;
        }
        
        //json
        if(is_array($response['data'])){
            $data = $response['data'];
        }else{
            $data = json_decode($response['data'], true);
        }
        
        //content
        if(is_array($data['msg'])){
            $data = $data['msg'];
        }else{
            $data = json_decode($data['msg'], true);
        }
        
        //format
        $skuList = array();
        if(isset($data['result'])){
            $skuList = $data['result'];
            $total = count($skuList);
        }
        
        //result
        $result = array(
            'rsp' => $response['rsp'],
            'res' => $response['res'],
            'msg_id' => $response['msg_id'],
            'total' => $total,
            'dataList' => $skuList,
        );
        
        return $result;
    }
}
