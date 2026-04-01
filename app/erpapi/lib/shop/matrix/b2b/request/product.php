<?php
/**
 * @author
 * @describe 处理店铺商品相关类
 */

class erpapi_shop_matrix_b2b_request_product extends erpapi_shop_request_product {

    /**
     * 回传库存,对应第三方B2B接口文档, b2b.update_store.updateStore 批量库存回写 接口
     * @param array  $stocks
     * @param string $dorelease
     * @return array
     */
    public function updateStock($stocks, $dorelease = false)
    {
  
        $rs = array('rsp' => 'fail', 'msg' => '', 'data' => '');
        if (!$stocks) {
            $rs['msg'] = 'no stocks';
            return $rs;
        }

        $shop_id = $this->__channelObj->channel['shop_id'];
        $skuIds = array_keys($stocks);
        sort($stocks);
        $logData = ['list_quantity' => '', 'original' => json_encode($stocks, JSON_UNESCAPED_UNICODE)];
        foreach ($stocks as $key => $value) {
            if ($value['regulation']) {
                unset($stocks[$key]['regulation']);
            }
        }
        //格式化库存参数
        $stocks = $this->format_stocks($stocks);
        if (!$stocks) {
            return $this->error('没有可回写的库存数据', '102');
        }

        //保存库存同步管理日志
        $oApiLogToStock = kernel::single('ome_api_log_to_stock');
        $oApiLogToStock->save($stocks, $shop_id);
        $params = $this->_getUpdateStockParams($stocks);
     
        $logData = array_merge($logData, $params);

        //api_name
        $stockApi = SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC;
        // 直连请求暂不支持异步回调
        $callback = array();
        $callbackParams = array(
            'shop_id' => $shop_id,
            'request_params' => $params,
            'api_name' => $stockApi
        );

        $title = '批量更新店铺(' . $this->__channelObj->channel['name'] . ')的库存(共' . count($stocks) . '个)';
        $primaryBn = $this->__channelObj->channel['shop_bn'] . 'UpdateStock';
        $return = $this->__caller->call($stockApi, $params, [], $title, 10, $primaryBn, true, '', $logData);
      
        if ($return !== false) {
            if ($dorelease === true) {
                if ($skuIds && app::get('inventorydepth')->is_installed()) {
                    app::get('inventorydepth')->model('shop_adjustment')->update(array('release_status' => 'running'), array('id' => $skuIds));
                }
            }
            app::get('ome')->model('shop')->update(array('last_store_sync_time' => time()), array('shop_id' => $shop_id));

            if(isset($return['data']) && is_array($return['data']) && $return['data']){
                $return['data'] = json_encode($return['data']);
            }
            // 直连情况下,执行callback函数
            $this->updateStockCallback($return, $callbackParams);
        }
        
        $rs['rsp'] = 'success';

        return $rs;
    }

    /*
     * 整理参数
     */
    protected function _getUpdateStockParams($stocks)
    {
        return parent::_getUpdateStockParams($stocks);
    }
} 