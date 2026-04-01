<?php
/**
 * 微盟2.0Lib方法类
 *
 * @author wangbiao@shopex.cn
 * @version 2025.07.01
 */
class inventorydepth_service_shop_weimobr extends inventorydepth_service_shop_common
{
    // 微盟商品接口：weimob_shop/goods/getList，一次最多支持拉取20条记录;
    public $customLimit = 20;
    
    /**
     * 下载全部商品(包括普通现货、专供现货)
     * 官方接口名：weimob_shop/goods/getList
     * 官方接口URL：https://doc.weimobcloud.com/detail?menuId=19&childMenuId=1&tag=2396&id=2776&isold=2
     *
     * @param array $filter
     * @param string $shop_id
     * @param int $offset
     * @param int $limit
     * @param string $limit
     * @return array
     */
    public function downloadList($filter, $shop_id, $offset=0, $limit=30, &$error_msg=null)
    {
        $shopService = kernel::single('inventorydepth_rpc_request_shop_items');
        
        // 开始执行
        $result = $shopService->items_all_get($filter, $shop_id, $offset, $limit);
        if($result === false){
            $error_msg = $shopService->get_err_msg();
            return false;
        }
        
        // check
        if(!isset($result['totalCount']) || !isset($result['pageList'])){
            $error_msg = '响应数据无效：没有总数和列表数据';
            return false;
        }
        
        // 响应数据为空
        if(empty($result['pageList'])){
            $this->totalResults = 0;
            return array();
        }
        
        // 平台商品总数
        $this->totalResults = $result['totalCount'];
        
        //格式化
        $data = array();
        foreach ($result['pageList'] as $skuKey => $goodsInfo)
        {
            // 店铺库存
            $shop_store = isset($goodsInfo['goodsStock']['goodsStockNum']) ? $goodsInfo['goodsStock']['goodsStockNum'] : 0;
            
            // 商品最小销售价
            $minSalePrice = 0;
            if(isset($goodsInfo['goodsPrice']['minSalePrice']) && $goodsInfo['goodsPrice']['minSalePrice']){
                $minSalePrice = $goodsInfo['goodsPrice']['minSalePrice'];
            }
            
            // 商品最大销售价
            $maxSalePrice = 0;
            if(isset($goodsInfo['goodsPrice']['maxSalePrice']) && $goodsInfo['goodsPrice']['maxSalePrice']){
                $maxSalePrice = $goodsInfo['goodsPrice']['maxSalePrice'];
            }
            
            // 商品是否上架
            $approve_status = 'onsale';
            if(isset($goodsInfo['isOnline']) && !$goodsInfo['isOnline']){
                $approve_status = 'instock';
            }
            
            //spu
            $data[] = array(
                'iid' => $goodsInfo['goodsId'], //店铺商品ID
                'outer_id' => ($goodsInfo['outerGoodsCode'] ? $goodsInfo['outerGoodsCode'] : $goodsInfo['goodsId']), //外部商品编码，是商户自有 ERP 系统的商品编码.
                'title' => $goodsInfo['title'], //商品名称
                'approve_status' => $approve_status, //商品是否上架
                'price' => $minSalePrice,
                'num' => $shop_store, //店铺库存
                'simple' => 'false',
                //'skus' => $skuList, // 微盟平台没有SKU列表
            );
        }
        
        // 单独按iid拉取商品详细信息及SKU列表
        //@todo：微盟平台商品API接口没有SKU列表数据,需要单独请求SKU接口拉取数据;
        if($data){
            foreach ($data as $key => $itemInfo)
            {
                $itemDetailInfo = $this->downloadByIId($itemInfo['iid'], $shop_id, $error_msg);
                if(empty($itemDetailInfo)){
                    $data[$key]['skus'] = [];
                    continue;
                }
                
                $data[$key]['skus'] = $itemDetailInfo['skus'];
            }
        }
        
        return $data;
    }
    
    /**
     * 通过IID下载 单个SKU
     * 官方接口名：weimob_shop/goods/sku/search
     * 官方接口URL：https://doc.weimobcloud.com/detail?menuId=19&childMenuId=1&tag=2396&id=4153&isold=2
     *
     * @param $iid
     * @param $shop_id
     * @param $error_msg
     * @return array|false|void
     */
    public function downloadByIId($iid, $shop_id, &$error_msg=null)
    {
        $shopService = kernel::single('inventorydepth_rpc_request_shop_items');
        
        // store.item.get
        $goodsInfo = $shopService->item_get($iid, $shop_id);
        if ($goodsInfo === false) {
            $error_msg = $shopService->get_err_msg();
            return false;
        }
        
        // check
        if(empty($goodsInfo['skuList'])){
            $error_msg = $iid . '商品详情不存在！';
            return false;
        }
        
        // 商品是否上架
        $approve_status = 'onsale';
        if(isset($goodsInfo['isOnline']) && !$goodsInfo['isOnline']){
            $approve_status = 'instock';
        }
        
        // SKU列表
        $skus = [];
        foreach ($goodsInfo['skuList'] as $skuKey => $skuInfo)
        {
            $skus[] = array(
                'sku_id' => $skuInfo['skuId'], //SKU ID
                'outer_id' => $skuInfo['outerSkuCode'], //店铺货号
                'barcode' => $skuInfo['skuBarCode'], //条形码
                'price' => $skuInfo['salePrice'], //销售价，精确到 2 位小数
                'quantity' => $skuInfo['skuStockNum'], //SKU店铺库存数量
                //'costPrice' => $skuInfo['costPrice'], //成本价，精确到 2 位小数
                //'marketPrice' => $skuInfo['marketPrice'], //市场价，精确到 2 位小数
                //'properties' => $skuInfo['goodsPropertyList'], //商品属性
            );
        }
        
        // 商品详细信息
        $itemInfo = array(
            'iid' => $goodsInfo['goodsId'], //店铺商品ID
            'outer_id' => ($goodsInfo['outerGoodsCode'] ? $goodsInfo['outerGoodsCode'] : $goodsInfo['goodsId']), //外部商品编码，是商户自有 ERP 系统的商品编码.
            'title' => $goodsInfo['title'], //商品名称
            'approve_status' => $approve_status, //商品是否上架
            'simple' => 'false',
            //'price' => 0,
            //'num' => 0,
            'skus' => ['sku'=>$skus],
        );
        
        return $itemInfo;
    }
}
