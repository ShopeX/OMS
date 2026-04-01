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
 * @author ykm 2016-04-05
 * @describe 店铺商品相关接口
 */
class erpapi_shop_request_product extends erpapi_shop_request_abstract
{
    //请求矩阵版本号
    protected $__version = '';
    
    protected function getUpdateStockApi()
    {
        return SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC;
    }

    /**
     * 回传库存
     * 
     * @param array $stocks
     * @param string $dorelease
     * @return array
     */

    public function updateStock($stocks, $dorelease = false)
    {
        $rs = array('rsp'=>'fail','msg'=>'','data'=>'');
        if (!$stocks) {
            $rs['msg'] = 'no stocks';
            return $rs;
        }
        
        $shop_id = $this->__channelObj->channel['shop_id'];
        $skuIds = array_keys($stocks);
        sort($stocks);
        $logData = ['list_quantity'=>'','original'=>$stocks,'name'=>$this->__channelObj->channel['name']];
        $stock_release_id = '';
        $bnLogData = ['step'=>'response','original'=>[], 'name'=>$this->__channelObj->channel['name']];
        foreach ($stocks as $key => $value) {
            if($value['stock_release_id']) {
                $stock_release_id = $value['stock_release_id'];
            }
            $bnLogData['original'][$value['bn']] = ['bn' =>$value['bn'], 'quantity'=>$value['quantity']];
            if($value['regulation']) {
                unset($stocks[$key]['regulation']);
            }
        }
        //格式化库存参数
        $stocks = $this->format_stocks($stocks);
        if(!$stocks){
            return $this->error('没有可回写的库存数据', '102');
        }
        
        //保存库存同步管理日志
        $oApiLogToStock = kernel::single('ome_api_log_to_stock');
        $stocks =$oApiLogToStock->save($stocks,$shop_id);
        $params = $this->_getUpdateStockParams($stocks);
        $logData = array_merge($logData, $params);
        
        //version版本号
        if($this->__version){
            $params['version'] = $this->__version;
        }
        
        //api_name
        $stockApi = $this->getUpdateStockApi($stocks);
        $callback = array(
            'class' => get_class($this),
            'method' => 'updateStockCallback',
            'params' => array(
                'shop_id' => $shop_id,
                'request_params' => $params,
                'log_data' => $bnLogData,
                'api_name' => $stockApi,
                'stock_release_id' => $stock_release_id
            )
        );
        $title = '批量更新店铺('.$this->__channelObj->channel['name'].')的库存(共'.count($stocks).'个)';
        $primaryBn = $this->__channelObj->channel['shop_bn'] . 'UpdateStock';
        $rs = $this->__caller->call($stockApi,$params,$callback,$title, 10, $primaryBn, false, '', $logData);
        $this->_writeLog($logData, $stockApi, $rs);
        if ($rs['rsp'] != 'fail'){
            if ($dorelease === true) {
                if ($skuIds && app::get('inventorydepth')->is_installed()) {
                    app::get('inventorydepth')->model('shop_adjustment')->update(array('release_status'=>'running'),array('id'=>$skuIds));
                }
            }
            app::get('ome')->model('shop')->update(array('last_store_sync_time'=>time()),array('shop_id'=>$shop_id));
        }
        return $rs;
    }

    protected function _writeLog($logData, $stockApi, $result = []) {
        $call_start_time = time();
        $apilogModel = app::get('ome')->model('api_log');
        $insertData = [];
        foreach($logData['original'] as $ld) {
            $log_id = $apilogModel->gen_id();
            $status = $result['rsp'] == 'succ' ? 'success' : ($result['rsp'] == 'running' ? 'success' : 'fail');
            if($result && is_array($result['data']) && count($result['data'])>1) {
                $transfer = [];
                $msg = '回写完成';
                $succ = is_string($result['data']['true_bn']) ? json_decode($result['data']['true_bn'], 1) : $result['data']['true_bn'];
                if($succ && in_array($ld['bn'], $succ)) {
                    $status = 'success';
                    $msg = '回写成功';
                }
                $error = is_string($result['data']['error_response']) ? json_decode($result['data']['error_response'], 1) : $result['data']['error_response'];
                if($error && in_array($ld['bn'], $error)) {
                    $status = 'fail';
                    $msg = '回写失败';
                }
                $error_msg_with_bn = is_string($result['data']['error_msg_with_bn']) ? json_decode($result['data']['error_msg_with_bn'], 1) : $result['data']['error_msg_with_bn'];
                if(is_array($error_msg_with_bn)) {
                    foreach($error_msg_with_bn as $k => $v) {
                        if(in_array($ld['bn'], $v)) {
                            $msg = $k;
                        }
                    }
                }
                if(is_array($result['data']['detail_response']) && $result['data']['detail_response']) {
                    foreach($result['data']['detail_response'] as $v) {
                        if($v['bn'] == $ld['bn']) {
                            if($v['rsp'] == 'fail') {
                                $status = 'fail';
                                $msg = $v['msg'];
                            }
                            $transfer[] = $v;
                        }
                    }
                }
            } else {
                $msg = $result['msg'] ? : '发起请求';
                $tmpTransfer = json_decode($logData['list_quantity'], 1);
                $transfer = [];
                foreach($tmpTransfer as $v) {
                    if($v['bn'] == $ld['bn']) {
                        $transfer[] = $v;
                    }
                }
                if(empty($transfer)) {
                    $transfer = $tmpTransfer;
                }
            }
            $logsdf = array(
                'log_id'        => $log_id,
                'task_name'     => $logData['name'].'-回写店铺库存:'.$ld['quantity'],
                'status'        => $status,
                'worker'        => $stockApi,
                'params'        => json_encode($ld),
                'transfer'      => json_encode($transfer),
                'response'      => json_encode($result),
                'msg'           => $msg,
                'log_type'      => '',
                'api_type'      => $logData['step'] ? : 'request',
                'memo'          => '',
                'original_bn'   => $ld['bn'],
                'createtime'    => $call_start_time,
                'last_modified' => $call_start_time,
                'msg_id'        => $result['msg_id'],
                'spendtime'     => microtime(true) - $call_start_time,
            );
            if ($ld['shop_sku_id']) {
                $logsdf['task_name']  .= ' shop_sku_id:'.$ld['shop_sku_id'];
            }
            $insertData[] = $logsdf;
        }
        $apilogModel->batchInsert($insertData);
    }

    protected function _getUpdateStockParams($stocks) {
        //去除数组下标
        $stocks = array_values($stocks);
        
        //待更新库存BN
        $params = array(
            'list_quantity' => json_encode($stocks),
        );
        return $params;
    }

    /**
     * 更新StockCallback
     * @param mixed $ret ret
     * @param mixed $callback_params 参数
     * @return mixed 返回值
     */
    public function updateStockCallback($ret, $callback_params) {
        $status          = $ret['rsp'];
        $res             = $ret['res'];
        $data            = $ret['data'] ? json_decode($ret['data'], true) : [];
        $data            = $data?:[];
        $ret['data'] = $data;
        $request_params  = $callback_params['request_params'];
        $msg_id          = $ret['msg_id'];
        $apiName         = $callback_params['api_name'];
        $stock_release_id = $callback_params['stock_release_id'];
        if($stock_release_id) {
            app::get('inventorydepth')->model('stock_release')->update([
                'sync_status' => ($ret['rsp'] == 'succ' ? 'succ' : 'fail'),
                'msg_id' => $msg_id,
                'sync_msg' => ($ret['msg'] ? mb_strcut($ret['msg'], 0, 200, 'utf8') : '')
            ], ['id'=>$stock_release_id]);
        }
        $this->_writeLog($callback_params['log_data'], $apiName, $ret);
        // 店铺信息
        if ($callback_params['shop_id']) {
            $shopModel = app::get('ome')->model('shop');
            $shop = $shopModel->dump(array('shop_id'=>$callback_params['shop_id']),'business_type');
        }
        // LOG PARAMS
        $log_params = array($apiName,$request_params,array(get_class($this),'updateStockCallback',$callback_params));
        if ($status != 'succ' && $status != 'fail' ){
            $res = $status . kernel::single('ome_api_func')->api_code2msg('re001', '', 'public');
        }

        $error_msg_with_bn = $data['error_msg_with_bn'];
        if ($error_msg_with_bn) {
            if (!is_array($error_msg_with_bn)) {
                $error_msg_with_bn = json_decode($error_msg_with_bn, 1);
            }
            // $notify_info = '';
            // foreach ($error_msg_with_bn as $errInfo => $errList) {
            //     $notify_info .= $errInfo . ': ' . implode(',', $errList) . "\n";
            // }
            // kernel::single('monitor_event_notify')->addNotify('stock_sync', [
            //     'stock_date'     => date('Y-m-d H:i:s',time()),
            //     'errmsg'         => $notify_info,
            // ]);
        }

        //更新失败的bn会返回，然后下次retry时，只执行失败的bn更新库存
        $err_item_bn = $data['error_response'];
        if (!is_array($err_item_bn)){
            $err_item_bn = json_decode($data['error_response'],true);
        }
        $itemsnum = json_decode($log_params[1]['list_quantity'],true);
        $new_itemsnum = $true_itemsnum = array();
        foreach($itemsnum as $k=>$v){
            if(in_array($v['bn'],(array)$err_item_bn) && !in_array($v['bn'],(array) $data['true_bn']) ){
                $new_itemsnum[] = $v;
            } else {
                $true_itemsnum[] = $v;
            }
        }

        //当返回失败且BN为空时不更新list_quantity
        if ($status == 'succ' || $new_itemsnum){
            $log_params[1]['list_quantity'] = json_encode($new_itemsnum);
        }else{
            $new_itemsnum = $itemsnum;
        }

        $log_detail = array(
            'msg_id' => $msg_id,
            'params' => serialize($log_params),
        );
        //更新库存同步管理的执行状态
        $oApiLogToStock = kernel::single('ome_api_log_to_stock');
        if ($true_itemsnum) {
            $oApiLogToStock->save_callback($true_itemsnum,'success',$callback_params['shop_id'],$res,$log_detail);
        }
        if ($new_itemsnum) {
            $oApiLogToStock->save_callback($new_itemsnum,'fail',$callback_params['shop_id'],$res,$log_detail);
            // 唯品会省仓库存回写，记录失败信息
            if ($error_msg_with_bn) {
                foreach ($error_msg_with_bn as $errInfo => $errList) {
                    $errr_bn_res = [];
                    foreach ($new_itemsnum as $nk => $nv) {
                        if (in_array($nv['bn'], $errList)) {
                            $errr_bn_res[] = $nv;
                        }
                    }
                    if ($errr_bn_res) {
                        $oApiLogToStock->save_callback($errr_bn_res,'fail',$callback_params['shop_id'],$errInfo,$log_detail);
                    }
                }
            }
        }
        return $this->callback($ret, $callback_params);
    }

    #实时下载店铺商品
    /**
     * itemsAllGet
     * @param mixed $filter filter
     * @param mixed $offset offset
     * @param mixed $limit limit
     * @return mixed 返回值
     */
    public function itemsAllGet($filter,$offset=0,$limit=100)
    {
        $timeout = 20;
        $param = array(
            'page_no'        => $offset,
            'page_size'      => $limit,
            'fields'         => 'iid,outer_id,bn,num,title,default_img_url,modified,detail_url,approve_status,skus,price,barcode ',
        );
        $param = array_merge((array)$param,(array)$filter);
        $title = "获取店铺(" . $this->__channelObj->channel['name'] .')商品';
        $shop_bn = $this->__channelObj->channel['shop_bn'];
        $primary_bn = $shop_bn.'_'.date('Ymd', time());
        
        $result = $this->__caller->call(SHOP_GET_ITEMS_ALL_RPC,$param,array(),$title,$timeout, $primary_bn);
        if ($result['res_ltype'] > 0) {
            for ($i=0;$i<3;$i++) {
                $primary_bn = $shop_bn.'-'.date('Ymd', time());
                $result = $this->__caller->call(SHOP_GET_ITEMS_ALL_RPC,$param,array(),$title,$timeout, $primary_bn);
                if ($result['res_ltype'] == 0) {
                    break;
                }
            }
        }
        if($result['data']) {
            $result['data'] = json_decode($result['data'], true);
        }
        return $result;
    }

    #根据$iids实时获取店铺商品
    /**
     * itemsListGet
     * @param mixed $iids ID
     * @return mixed 返回值
     */
    public function itemsListGet($iids)
    {
        if(!$iids) return false;
        if(is_array($iids)) $iids = implode(',', $iids);
        $timeout = 10;
        $param = array(
            'iids' => $iids,
        );
        $title = "根据IIDS获取店铺(" . $this->__channelObj->channel['name'] .')商品';
        $result = $this->__caller->call(SHOP_GET_ITEMS_LIST_RPC,$param,array(),$title,$timeout);
        if ($result->res_ltype > 0) {
            for ($i=0;$i<3;$i++) {
                $result = $this->__caller->call(SHOP_GET_ITEMS_LIST_RPC,$param,array(),$title,$timeout);
                if ($result->res_ltype == 0) {
                    break;
                }
            }
        }
        if($result['data']) {
            $result['data'] = json_decode($result['data'], true);
        }
        return $result;
    }

    #淘分销商品下载
    /**
     * fenxiaoProductsGet
     * @param mixed $filter filter
     * @param mixed $offset offset
     * @param mixed $limit limit
     * @return mixed 返回值
     */
    public function fenxiaoProductsGet($filter,$offset=0,$limit=20)
    {
        $timeout = 20;
        $param = array(
            'page_no'        => $offset,
            'page_size'      => $limit,
        );
        $param = array_merge((array)$param,(array)$filter);
        $title = '获取店铺(' . $this->__channelObj->channel['name'] . ')商品';
        $result = $this->__caller->call(SHOP_GET_FENXIAO_PRODUCTS,$param,array(),$title,$timeout);
        if ($result['res_ltype'] > 0) {
            for ($i=0;$i<3;$i++) {
                $result = $this->__caller->call(SHOP_GET_FENXIAO_PRODUCTS,$param,array(),$title,$timeout);
                if ($result['res_ltype'] == 0) {
                    break;
                }
            }
        }
        if($result['data']) {
            $result['data'] = json_decode($result['data'], true);
        }
        return $result;
    }

    #淘分销商品更新
    /**
     * fenxiaoProductUpdate
     * @param mixed $param param
     * @return mixed 返回值
     */
    public function fenxiaoProductUpdate($param) {
        $timeout = 20;
        if (!$param['pid']) {
            return false;
        }
        $title = "更新店铺(" . $this->__channelObj->channel['name'] .')商品'.$param['pid'].'上下架状态';
        $result = $this->__caller->call(SHOP_UPDATE_FENXIAO_PRODUCT,$param,array(),$title,$timeout, $param['pid']);
        return $result;
    }

    #根据IID获取单个商品
    /**
     * item_get
     * @param mixed $iid ID
     * @return mixed 返回值
     */
    public function item_get($iid) {
        $title = '单拉商品[' . $iid . ']';
        $params = array(
            'iid' => $iid,
        );
        for ($i=0; $i<3; $i++) {
            $result = $this->__caller->call(SHOP_ITEM_GET,$params,array(),$title, 20, $iid);
            if ($result['rsp'] == 'succ') break;
        }
        
        if ($result['rsp'] != 'succ' || !$result['data']) return array();

        if ($result['data']) $result['data'] = @json_decode($result['data'],true);

        return $result;
    }

    /**
     * item_sku_get
     * @param mixed $sku sku
     * @return mixed 返回值
     */
    public function item_sku_get($sku) {
        $title = '单拉商品SKU[' . ($sku['sku_id'] ? $sku['sku_id'] : $sku['iid']) . ']';
        $params = array(
            'sku_id' => $sku['sku_id'],
            'iid' => $sku['iid'],
            'num_iid' => $sku['iid'],
        );
        if ($sku['seller_uname']) $params['seller_uname'] = $sku['seller_uname'];
        for ($i=0; $i<3; $i++) {
            $result = $this->__caller->call(SHOP_ITEM_SKU_GET,$params,array(),$title, 20, ($sku['sku_id'] ? $sku['sku_id'] : $sku['iid']));
            if ($result['rsp'] == 'succ') break;
        }
        if ($result['rsp'] != 'succ' || !$result['data']) return array();
        
        if ($result['data']) $result['data'] = @json_decode($result['data'],true);

        return $result;
    }

    # 单个更新上下架
    /**
     * approveStatusUpdate
     * @param mixed $approve approve
     * @return mixed 返回值
     */
    public function approveStatusUpdate($approve) {
        if(!$approve) return false;
        $title = '更新店铺('.$this->__channelObj->channel['name'].')的('.($approve['title']?$approve['title']:$approve['iid']).')商品上下架状态';
        $params['iid'] = $approve['iid'];
        $params['approve_status'] = $approve['approve_status'];
        if($approve['approve_status'] == 'onsale') $params['num'] = $approve['num'];
        if($approve['outer_id']) $params['outer_id'] = $approve['outer_id'];
        $operinfo = kernel::single('ome_func')->getDesktopUser();
        $params = array_merge((array) $params,(array) $operinfo);
        $result = $this->__caller->call(SHOP_UPDATE_ITEM_APPROVE_STATUS_RPC,$params,array(),$title, 10, $params['iid']);
        return $result;
    }

    #批量更新商品上下架
    /**
     * approveStatusListUpdate
     * @param mixed $approve_status approve_status
     * @return mixed 返回值
     */
    public function approveStatusListUpdate($approve_status) {
        if(!$approve_status) return false;
        $approve_status_msg = '';
        switch ($approve_status[0]['approve_status']) {
            case 'onsale':
                $approve_status_msg = '上架';
                break;
            case 'instock':
                $approve_status_msg = '下架';
                break;
            case 'is_pre_delete':
                $approve_status_msg = '预删除';
                break;
        }
        $title = '批量'.$approve_status_msg.'店铺('.$this->__channelObj->channel['name'].')的商品(共'.count($approve_status).'个)';
        $params = array(
            'list_quantity' => json_encode($approve_status),
        );
        $callback = array(
            'class' => get_class($this),
            'method' => 'approveStatusUpdateCallback',
            'params' => array(
                'list_quantity' => $params['list_quantity']
            )
        );

        $rs = $this->__caller->call(SHOP_UPDATE_ITEM_APPROVE_STATUS_LIST_RPC,$params,$callback,$title,10,'batchUpdateApprove');
        return $rs;
    }

    #批量上下架回调方法
    /**
     * approveStatusUpdateCallback
     * @param mixed $response response
     * @param mixed $callback_params 参数
     * @return mixed 返回值
     */
    public function approveStatusUpdateCallback($response, $callback_params) {
        $list_quantity = json_decode($callback_params['list_quantity'],true);
        $approve_status = $list_quantity[0]['approve_status'];
        $true_bn = $response['data']['true_bn'];
        # 更新状态
        if ($true_bn) {
            $itemFilter = array(
                'bn' => $true_bn,
                'shop_id' => $callback_params['shop_id'],
            );
            if (app::get('inventorydepth')->is_installed()) {
                app::get('inventorydepth')->model('shop_items')->update(array('approve_status'=>$approve_status),$itemFilter);
            }
        }
        return $response;
    }

    #获取商品
    /**
     * items_custom_get
     * @param mixed $productCode productCode
     * @return mixed 返回值
     */
    public function items_custom_get($productCode) {
        if (!$productCode) return false;
        $title = '获取单个商品[' . $productCode . ']';
        $params = array(
            'iid' => $productCode,
            //'bn'=>$productCode,
        );
        for ($i=0;$i<3;$i++) {
            $result = $this->__caller->call(SHOP_GET_ITEMS_CUSTOM,$params,array(),$title, 20, $productCode);
            if ($result['rsp'] == 'succ') {
                break;
            }
        }
        if($result['data']) {
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
        return $stockList;
    }
    
    //[搬云起]根据IID获取单个商品
    public function item_get_new($iid)
    {
        $title  = '新单拉商品[' . $iid . ']';
        
        $params = array(
                'iid' => $iid,
        );
        
        for ($i=0; $i<3; $i++)
        {
            $result = $this->__caller->call(SHOP_ITEM_I_GET,$params,array(),$title, 20, $iid);
            
            if ($result['rsp'] == 'succ') break;
        }
        
        if ($result['rsp'] != 'succ' || !$result['data']) return array();
        
        if ($result['data']) $result['data'] = @json_decode($result['data'],true);
        
        return $result;
    }

    /**
     * skuAllGet
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */
    public function skuAllGet($sdf){}
    
    /**
     * 同步OMS商品给到翱象系统
     * 
     * @param array $params
     * @return array
     */
    public function createAoxiangMaterial($params)
    {
        $title = '同步OMS销售物料给到翱象系统';
        
        $shop_id = $params[0]['shop_id'];
        $shop_bn = $params[0]['shop_bn'];
        $original_bn = ($shop_bn ? $shop_bn : date('Ymd', time()));
        
        //warehouse
        $goodsList = array();
        $product_bns = array();
        foreach ($params as $key => $val)
        {
            $goodsInfo = array (
                'sc_item_id' => $val['sales_material_bn'], //ERP货品ID
                'sc_item_code' => $val['sales_material_bn'], //货品商家编码
                'sc_item_name' => ($val['sales_material_name'] ? $val['sales_material_name'] : $val['sales_material_bn']), //货品名称
                'bar_code' => $val['barcode'], //货品条码
            );
            
            $goodsList[] = $goodsInfo;
            
            //product_bns
            $product_bns[] = $val['sales_material_bn'];
        }
        
        //params
        $requestParams = array(
            'sc_items' => json_encode($goodsList), //商品数组,最多50条
        );
        
        //callback
        $callback = array(
            'class' => get_class($this),
            'method' => 'updateAoxiangMaterialCallback',
            'params' => array(
                'shop_id' => $shop_id,
                'product_bns' => $product_bns,
            )
        );
        
        //request
        $result = $this->__caller->call(SHOP_AOXIANG_GOODS_CREATE_ASYNC, $requestParams, $callback, $title, 10, $original_bn);
        
        return $result;
    }
    
    /**
     * 异步接收货品同步结果
     * 
     * @param array $response
     * @param array $callback_params
     * @return Array
     */
    public function updateAoxiangMaterialCallback($response, $callback_params)
    {
        $axProductMdl = app::get('dchain')->model('aoxiang_product');
        
        //shop
        $shop_id = $callback_params['shop_id'];
        $product_bns = $callback_params['product_bns'];
        
        //check
        if($response['rsp'] != 'fail'){
            return $this->callback($response, $callback_params);
        }
        
        //result
        $dataList = $response['data'];
        
        //string
        if(is_string($response['data'])){
            $dataList = json_decode($response['data'], true);
        }
        
        //check
        if(empty($dataList)){
            return $this->callback($response, $callback_params);
        }
        
        //list
        $fail_bns = array();
        $dataList = $dataList['data']['data_item'];
        if($dataList){
            $resData = array();
            foreach ($dataList as $key => $val)
            {
                $sc_item_id = $val['sc_item_id'];
                
                $fail_bns[$sc_item_id] = $sc_item_id;
            }
        }
        
        //fail
        $err_msg = $response['err_msg'];
        $err_msg = json_decode($err_msg, true);
        $err_msg = $err_msg['msg'];
        
        //update
        $updateData = array('sync_status'=>'fail', 'sync_msg'=>$err_msg);
        
        //bns
        if($fail_bns){
            $filter = array('shop_id'=>$shop_id, 'product_bn'=>$fail_bns);
            $axProductMdl->update($updateData, $filter);
        }elseif($product_bns){
            $filter = array('shop_id'=>$shop_id, 'product_bn'=>$product_bns);
            $axProductMdl->update($updateData, $filter);
        }
        
        return $this->callback($response, $callback_params);
    }
    
    /**
     * 删除翱象系统里OMS同步的销售物料
     * 
     * @param array $params
     * @return array
     */
    public function deleteAoxiangMaterial($params)
    {
        $title = '删除翱象系统里OMS同步的销售物料';
        
        $shop_id = $params[0]['shop_id'];
        $shop_bn = $params[0]['shop_bn'];
        $original_bn = ($shop_bn ? $shop_bn : date('Ymd', time()));
        
        //warehouse
        $goodsList = array();
        foreach ($params as $key => $val)
        {
            $goodsInfo = array (
                'sc_item_id' => $val['sales_material_bn'], //ERP货品ID
                'sc_item_code' => $val['sales_material_bn'], //货品商家编码
                'sc_item_name' => ($val['sales_material_name'] ? $val['sales_material_name'] : $val['sales_material_bn']), //货品名称
                'bar_code' => $val['barcode'], //货品条码
            );
            
            $goodsList[] = $goodsInfo;
        }
        
        //check
        if(empty($goodsList)){
            $error_msg = '没有可请求删除的商品';
            
            $this->error($error_msg);
        }
        
        //params
        $requestParams = array(
            'sc_items' => json_encode($goodsList), //商品数组,最多50条
        );
        
        //callback
        $callback = array(
            'class' => get_class($this),
            'method' => 'deleteAoxiangMaterialCallback',
            'params' => array(
                'shop_id' => $shop_id,
                'shop_bn' => $shop_bn,
            )
        );
        
        //request
        $result = $this->__caller->call(SHOP_AOXIANG_GOODS_DELETE_ASYNC, $requestParams, $callback, $title, 10, $original_bn);
        
        return $result;
    }
    
    /**
     * 异步删除货品
     * 
     * @param array $response
     * @param array $callback_params
     * @return Array
     */
    public function deleteAoxiangMaterialCallback($response, $callback_params)
    {
        $axProductMdl = app::get('dchain')->model('aoxiang_product');
        
        //shop
        $shop_id = $callback_params['shop_id'];
        
        return $this->callback($response, $callback_params);
    }
    
    /**
     * 同步商品关系给到翱象系统
     * 
     * @param array $params
     * @return array
     */
    public function mappingAoxiangMaterial($params)
    {
        $title = '同步商品关系给到翱象系统';
        
        $shop_id = $params[0]['shop_id'];
        $shop_bn = $params[0]['shop_bn'];
        $original_bn = ($shop_bn ? $shop_bn : date('Ymd', time()));
        
        //warehouse
        $goodsList = array();
        $product_bns = array();
        foreach ($params as $key => $val)
        {
            $goodsInfo = array (
                'item_id' => $val['shop_iid'], //ERP货品ID
                'sku_id' => $val['shop_sku_id'], //货品商家编码
                'sc_item_id' => $val['sales_material_bn'], //ERP货品编码
            );
            
            $goodsList[] = $goodsInfo;
            
            //product_bns
            $product_bns[] = $val['sales_material_bn'];
        }
        
        //params
        $requestParams = array(
            'item_mappings' => json_encode($goodsList), //商品数组,最多50条
        );
        
        //callback
        $callback = array(
            'class' => get_class($this),
            'method' => 'mappingAoxiangMaterialCallback',
            'params' => array(
                'shop_id' => $shop_id,
                'shop_bn' => $shop_bn,
                'product_bns' => $product_bns,
            )
        );
        
        //request
        $result = $this->__caller->call(SHOP_AOXIANG_GOODS_MAPPING_ASYNC, $requestParams, $callback, $title, 10, $original_bn);
        
        return $result;
    }
    
    /**
     * 异步接收货品同步结果
     * 
     * @param array $response
     * @param array $callback_params
     * @return Array
     */
    public function mappingAoxiangMaterialCallback($response, $callback_params)
    {
        $axProductMdl = app::get('dchain')->model('aoxiang_product');
        
        //shop
        $shop_id = $callback_params['shop_id'];
        $shop_bn = $callback_params['shop_bn'];
        $product_bns = $callback_params['product_bns'];
        
        //check
        if($response['rsp'] != 'fail'){
            return $this->callback($response, $callback_params);
        }
        
        //result
        $dataList = $response['data'];
        
        //string
        if(is_string($response['data'])){
            $dataList = json_decode($response['data'], true);
        }
        
        $dataList = $dataList['data']['data_item'];
        
        //check
        if(empty($dataList)){
            return $this->callback($response, $callback_params);
        }
        
        //fail
        $err_msg = $response['err_msg'];
        $err_msg = json_decode($err_msg, true);
        $err_msg = $err_msg['msg'];
        
        //list
        $fail_bns = array();
        foreach ($dataList as $key => $val)
        {
            $sc_item_id = $val['sc_item_id'];
            
            $fail_bns[$sc_item_id] = $sc_item_id;
        }
        
        //update
        $updateData = array('sync_status'=>'fail', 'sync_msg'=>$err_msg);
        if($fail_bns){
            $axProductMdl->update($updateData, array('shop_id'=>$shop_id, 'product_bn'=>$fail_bns));
        }elseif($product_bns){
            $axProductMdl->update($updateData, array('shop_id'=>$shop_id, 'product_bn'=>$fail_bns));
        }
        
        return $this->callback($response, $callback_params);
    }
    
    /**
     * 翱象库存回传
     * 
     * @param array $stocks
     * @param string $dorelease
     * @return array
     */
    public function stockAoxiangUpdate($stocks)
    {
        $shop_id = $this->__channelObj->channel['shop_id'];
        $shop_bn = $this->__channelObj->channel['shop_bn'];
        $shop_name = $this->__channelObj->channel['name'];
        
        $title = '翱象店铺('. $shop_name .')的库存(共'. count($stocks) .'个)';
        $primaryBn = $shop_bn . 'AoxiangStock';
        
        //fail
        $rs = array('rsp'=>'fail','msg'=>'','data'=>'');
        if (!$stocks) {
            $rs['msg'] = 'no stocks';
            return $rs;
        }
        
        //format
        $physics_inventory = array();
        foreach ($stocks as $key => $val)
        {
            $physics_inventory[] = array(
                'erp_warehouse_code' => $val['branch_bn'], //ERP仓库编码
                'sc_item_id' => $val['bn'], //ERP货品id
                'total_quantity' => $val['total_quantity'], //仓实际正品库存总数
                'avaliable_quantity' => $val['quantity'], //仓可用正品库存数量
            );
        }
        
        //params
        $params = array(
            'physics_inventory' => json_encode($physics_inventory),
        );
        
        //callback
        $callback = array();
        
        //request
        $result = $this->__caller->call(SHOP_AOXIANG_INVENTORY_SYNC, $params, $callback, $title, 10, $primaryBn);
        
        return $result;
    }
    
    /**
     * 同步OMS组合销售物料给到翱象系统
     * 
     * @param array $params
     * @return array
     */
    public function createAoxiangPkgMaterial($params)
    {
        $title = '同步OMS组合销售物料给到翱象系统';
        
        $shop_id = $params[0]['shop_id'];
        $shop_bn = $params[0]['shop_bn'];
        $original_bn = ($shop_bn ? $shop_bn : date('Ymd', time()));
        
        //warehouse
        $goodsList = array();
        $product_bns = array();
        foreach ($params as $key => $val)
        {
            //子商品(关联的基础物料)
            $subList = array();
            foreach ($val['itemList'] as $itemKey => $itemVal)
            {
                $subList[] = array(
                    'sc_item_id' => $itemVal['material_bn'],
                    'quantity' => $itemVal['number'],
                );
            }
            
            //goods
            $goodsInfo = array (
                'combine_sc_item_id' => $val['sales_material_bn'], //ERP货品ID
                'combine_sc_item_code' => $val['sales_material_bn'], //货品商家编码
                'combine_sc_item_name' => ($val['sales_material_name'] ? $val['sales_material_name'] : $val['sales_material_bn']), //货品名称
                'sub_sc_items' => $subList, //子商品列表
            );
            
            $goodsList[] = $goodsInfo;
            
            //product_bns
            $product_bns[] = $val['sales_material_bn'];
        }
        
        //params
        $requestParams = array(
            'combine_sc_items' => json_encode($goodsList), //商品数组,最多50条
        );
        
        //callback
        $callback = array(
            'class' => get_class($this),
            'method' => 'updateAoxiangPkgMaterialCallback',
            'params' => array(
                'shop_id' => $shop_id,
                'product_bns' => $product_bns,
            )
        );
        
        //request
        $result = $this->__caller->call(SHOP_AOXIANG_GOODS_COMBINE_CREATE_ASYNC, $requestParams, $callback, $title, 10, $original_bn);
        
        return $result;
    }
    
    /**
     * 异步接收组合货品同步结果
     * 
     * @param array $response
     * @param array $callback_params
     * @return Array
     */
    public function updateAoxiangPkgMaterialCallback($response, $callback_params)
    {
        $axProductMdl = app::get('dchain')->model('aoxiang_product');
        
        //check
        if($response['rsp'] != 'fail'){
            return $this->callback($response, $callback_params);
        }
        
        //result
        $dataList = $response['data'];
        
        //shop
        $shop_id = $callback_params['shop_id'];
        $product_bns = $callback_params['product_bns'];
        
        //string
        if(is_string($response['data'])){
            $dataList = json_decode($response['data'], true);
        }
        
        $dataList = $dataList['data']['data_item'];
        
        //check
        if(empty($dataList)){
            return $this->callback($response, $callback_params);
        }
        
        //list
        $fail_bns = array();
        foreach ($dataList as $key => $val)
        {
            $sc_item_id = $val['sc_item_id'];
            
            $fail_bns[$sc_item_id] = $sc_item_id;
        }
        
        //fail
        $err_msg = $response['err_msg'];
        $err_msg = json_decode($err_msg, true);
        $err_msg = $err_msg['msg'];
        
        //update
        $updateData = array('sync_status'=>'fail', 'sync_msg'=>$err_msg);
        
        //bns
        if($fail_bns){
            $filter = array('shop_id'=>$shop_id, 'product_bn'=>$fail_bns);
            $axProductMdl->update($updateData, $filter);
        }elseif($product_bns){
            $filter = array('shop_id'=>$shop_id, 'product_bn'=>$product_bns);
            $axProductMdl->update($updateData, $filter);
        }
        
        return $this->callback($response, $callback_params);
    }
    
    /**
     * 删除翱象系统里OMS同步商品关系
     * 
     * @param array $params
     * @return array
     */
    public function deleteMaterialMapping($params)
    {
        $axProductMdl = app::get('dchain')->model('aoxiang_product');
        $axSkuMdl = app::get('dchain')->model('aoxiang_skus');
        
        $title = '删除翱象系统里OMS同步商品关系';
        
        $shop_id = $params[0]['shop_id'];
        $shop_bn = $params[0]['shop_bn'];
        $original_bn = ($shop_bn ? $shop_bn : date('Ymd', time()));
        
        //warehouse
        $goodsList = array();
        $goodsBns = array();
        foreach ($params as $key => $val)
        {
            $goodsInfo = array (
                'item_id' => $val['shop_iid'], //ERP货品ID
                'sku_id' => $val['shop_sku_id'], //货品商家编码
                'sc_item_id' => $val['sales_material_bn'], //ERP货品编码
            );
            
            $goodsBns[] = $val['sales_material_bn'];
            
            $goodsList[] = $goodsInfo;
        }
        
        //params
        $requestParams = array(
            'item_mappings' => json_encode($goodsList), //商品数组,最多50条
        );
        
        //callback
        $callback = array();
        
        //request
        $result = $this->__caller->call(SHOP_AOXIANG_GOODS_DELETE_MAPPING, $requestParams, $callback, $title, 10, $original_bn);
        
        //format
        if($result['rsp'] == 'succ'){
            $dataList = $result['data'];
            
            //unset
            unset($result['data']);
            
            //string
            if(is_string($dataList)){
                $dataList = json_decode($dataList, true);
            }
            
            if($dataList['data']){
                $detailItems = $dataList['data']['data_item'];
                
                $product_bns = array();
                $shop_sku_ids = array();
                foreach ((array)$detailItems as $key => $val)
                {
                    $seller_id = $val['seller_id'];
                    $item_id = $val['item_id'];
                    $sku_id = $val['sku_id'];
                    $sc_item_id = $val['sc_item_id'];
                    
                    //check
                    if($val['success'] == 1 || $val['success'] == 'true'){
                        $product_bns[$sc_item_id] = $sc_item_id;
                        
                        $shop_sku_ids[$sku_id] = $sku_id;
                    }else{
                        //失败暂时不处理
                    }
                }
                
                //update
                $updateData = array('mapping_status'=>'none', 'last_modified'=>time());
                $axProductMdl->update($updateData, array('shop_id'=>$shop_id, 'product_bn'=>$product_bns));
                
                //update sku
                $updateData = array('mapping_status'=>'none', 'last_modified'=>time());
                $axSkuMdl->update($updateData, array('shop_id'=>$shop_id, 'shop_sku_id'=>$shop_sku_ids));
            }else{
                //所有都是成功的平台会返回空
                //update
                $updateData = array('mapping_status'=>'none', 'last_modified'=>time());
                $axProductMdl->update($updateData, array('shop_id'=>$shop_id, 'product_bn'=>$goodsBns));
                
                //update sku
                $updateData = array('mapping_status'=>'none', 'last_modified'=>time());
                $axSkuMdl->update($updateData, array('shop_id'=>$shop_id, 'product_bn'=>$goodsBns));
            }
        }
        
        return $result;
    }

    /**
     * 查询渠道平台库存数
     * 
     * @param array  $sdf ,示例：['sku_id'=>'test', stock_model=>'PARTITION']
     * @return array
     * */
    public function queryInventory($sdf = [])
    {
        $primary_bn = $sdf['sku_id'];

        $params = array(
            'sc_item_ids' => $sdf['sku_id'],
        );

    
        $title = '查询渠道库存';
        $result = $this->__caller->call(SHOP_INVENTORY_QUERY,$params,[],$title,5,$primary_bn);

        if($result['rsp'] == 'succ') {
            $data = @json_decode($result['data'], 1);

            $stockModelEnum = [
                'POP_PARTITION' => 'PARTITION', // 分区库存
                'POP_SOP' => 'SOP', // 全国库存
            ];
            $f_data = [
                'sku_id' => $data['data']['obj']['skuId'],
                'stock_model' => $stockModelEnum[$data['data']['obj']['stockModel']],
            ];

            foreach ($data['data']['obj']['skuStockInfos'] as $item) {
                $skuStockInfos = [
                    'sku_id' => $item['skuId'],
                    'orderTransferNum' => $item['orderTransferNum'], // 订单转移至生产库存 POP
                    'orderBookingNum' => $item['orderBookingNum'], // 订单预占库存
                    'appBookingNum' => $item['appBookingNum'], // 申请单预定库存 POP
                    'storeId' => $item['storeId'], // 分区仓有，全国仓为0
                    'stockNum' => $item['stockNum'], // 总库存
                ];

                $f_data['skuStockInfos'][] = $skuStockInfos;
            }

            $result['data'] = $f_data ?: [];
        }

        return $result;
    }


    /**
     * 查询缓存中的商品信息
     * @Author: XueDing
     * @Date: 2024/11/22 11:01 AM
     * 
     * 本函数通过调用渠道服务的查询接口来获取缓存中的商品信息，可按条件筛选和分页查询
     * 主要用于提高商品信息的查询效率，避免直接对数据库的频繁访问
     * 
     * @param array $sdf 查询条件数组，包含分页和时间筛选等参数，默认为空数组
     * @return array 查询结果数组，包含商品信息和查询状态
     */
    public function queryCacheProduct($sdf = [])
    {
        $shopLib = kernel::single('ome_shop');
        
        // 获取渠道名称，用于后续的日志或错误信息中
        $shop_name = $this->__channelObj->channel['name'];
        $shop_id = $this->__channelObj->channel['shop_id'];

        // 初始化查询参数数组，包括分页、时间筛选和排序方式等
        // 允许通过$sdf参数自定义部分查询条件，如页码和页面大小等
        $params = array (
            'page'       => $sdf['page'] ?: 1,
            'page_size'  => $sdf['page_size'] ?: 10,
            'start_time' => $sdf['start_time'],
            'end_time'   => $sdf['end_time'],
            'sort_order' => $sdf['sort_order'] ?: 'desc',
        );
        
        //唯品会店铺配置：常态合作编号
        $cooperation_no = $shopLib->getShopVopCooperationNo($shop_id);
        if($cooperation_no){
            //指定常态合作编码：cooperation_no进行下载商品
            $params['num_iid'] = $cooperation_no;
        }
        
        // 定义查询操作的日志标题，用于记录查询缓存商品的操作
        $title = '查询' . $shop_name . '店铺缓存商品';
        
        //primary_bn
        $primary_bn = 'queryCacheProduct'. date('md');
        
        // 调用查询接口，传递查询参数和日志信息等，以获取缓存中的商品数据
        $result = $this->__caller->call(SHOP_INVENTORY_CACHE_QUERY, $params, [], $title, 10, $this->__channelObj->channel['shop_bn']);

        // 如果查询结果成功且有数据，则尝试将数据解析为JSON格式
        if ($result['rsp'] == 'succ') {
            if ($result['data']) {
                $result['data'] = @json_decode($result['data'], true);
            }
        }

        // 返回查询结果，包括商品数据和查询状态等信息
        return $result;
    }
}
