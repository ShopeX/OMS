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
 * @author chenping<chenping@shopex.cn>
 */

class inventorydepth_shop {
    const DOWNLOAD_ALL_LIMIT = 50;
    
    function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * 店铺批量下载
     *
     * @return void
     * @author
     **/
    public function downloadList($shop_id,$filter,$page,&$errormsg=null)
    {
        $shop = $this->app->model('shop')->db_dump($shop_id);

        if (!$shop) {
            $errormsg = $this->app->_('店铺不存在！'); return false;
        }

        if (!$shop['node_id']) {
            $errormsg = $this->app->_('店铺未绑定！'); return false;
        }
        
        $shopfactory = inventorydepth_service_shop_factory::createFactory($shop['shop_type'],$shop['business_type']);
        if ($shopfactory === false) {
            $errormsg = $this->app->_('店铺类型有误！'); return false;
        }

        set_time_limit(0); ini_set('memory_limit','1024M');

        //可按店铺类型自定义每次查询的limit解决分销的问题
        $customLimit = $shopfactory->getCustomLimit();
        $used_limit = ($customLimit > 0 ? $customLimit : self::DOWNLOAD_ALL_LIMIT);

        $data = $shopfactory->downloadList($filter,$shop_id,$page,$used_limit,$errormsg);
        if($data === false) return false;

        if($data){
            $stores = [];
            $itemModel = $this->app->model('shop_items');
            $skuModel = $this->app->model('shop_skus');
            $skuModel->batchInsert($data,$shop,$stores);
            $itemModel->batchInsert($data,$shop,$stores);
        }

        return true;
    }

    /**
     * 批量下载商品 一次调用不超过20
     *
     * @return void
     * @author
     **/
    public function downloadByIIds($iids,$shop_id,&$errormsg)
    {
        $shop = $this->app->model('shop')->select()->columns('shop_id,shop_bn,name,node_id,shop_type,business_type')
                ->where('shop_id=?',$shop_id)
                ->instance()->fetch_row();

        if (!$shop) {
            $errormsg = $this->app->_('店铺不存在！'); return false;
        }

        if (!$shop['node_id']) {
            $errormsg = $this->app->_('店铺未绑定！'); return false;
        }

        $shopfactory = inventorydepth_service_shop_factory::createFactory($shop['shop_type'],$shop['business_type']);
        if ($shopfactory === false) {
            $errormsg = $this->app->_('店铺类型有误！'); return false;
        }

        $result = $shopfactory->downloadByIIds($iids,$shop_id,$errormsg);
        if ($result) {
            //保存数据
            $itemModel = $this->app->model('shop_items');
            foreach ($result as $item) {
                $itemModel->saveItem($item);
            }

            return true;
        }

        return false;
    }

    /**
     * 通过IID下载 单个
     *
     * @return void
     * @author
     **/
    public function downloadByIId($iid,$shop_id,&$errormsg)
    {
        $shop = $this->app->model('shop')->select()->columns('shop_id,shop_bn,name,node_id,shop_type,business_type')
                ->where('shop_id=?',$shop_id)
                ->instance()->fetch_row();

        if (!$shop) {
            $errormsg = $this->app->_('店铺不存在！'); return false;
        }

        if (!$shop['node_id']) {
            $errormsg = $this->app->_('店铺未绑定！'); return false;
        }

        $shopfactory = inventorydepth_service_shop_factory::createFactory($shop['shop_type'],$shop['business_type']);
        if ($shopfactory === false) {
            $errormsg = $this->app->_('店铺类型有误！'); return false;
        }
        
        $data = $shopfactory->downloadByIId($iid,$shop_id,$errormsg);

        if ($data) {
            $itemModel = $this->app->model('shop_items');
            $itemModel->saveItem($data,$shop);
        }

        return $data ? true : false;
    }

    /**
     * 通过SKU_ID下载,单个
     *
     * @param Array $sku SKU信息
     * @param String $shop_id 店铺ID
     * @param String $errormsg 错误信息
     * @return void
     * @author
     **/
    public function dowloadBySkuId($sku,$shop_id,&$errormsg)
    {
        $shop = $this->app->model('shop')->select()->columns('shop_id,shop_bn,name,node_id,shop_type')
                ->where('shop_id=?',$shop_id)
                ->instance()->fetch_row();

        if (!$shop) {
            $errormsg = $this->app->_('店铺不存在！'); return false;
        }

        if (!$shop['node_id']) {
            $errormsg = $this->app->_('店铺未绑定！'); return false;
        }

        $id = $sku['id'];

        $shopfactory = inventorydepth_service_shop_factory::createFactory($shop['shop_type']);
        if ($shopfactory === false) {
            $errormsg = $this->app->_('店铺类型有误！'); return false;
        }

        $data = $shopfactory->dowloadBySkuId($sku,$shop_id,$errormsg);
        if($data){
            # 更新货品
            $this->app->model('shop_skus')->updateSku($data,$id);
        }

        return $data ? true : false;
    }

    /**
     * 获取店铺对应的仓库
     *
     * @return []|bool
     * @author
     **/
    public function getBranchByshop($shop_bn='')
    {
        if (!$this->branches) {
            $this->branches = app::get('ome')->getConf('shop.branch.relationship');
        }
        
        if(!$this->branches) return false;

        return $shop_bn ? $this->branches[$shop_bn] : $this->branches;
    }

    /**
     * 执行发布
     *
     * @param Array 商品记录ID
     * @param String $shop_id 店铺ID
     * @return void
     * @author
     **/
    public function doRelease($ids,$shop_id,$dorelease = false)
    {
        $skus = $this->app->model('shop_adjustment')->getList('shop_product_bn,shop_stock,addon',array('shop_id'=>$shop_id,'id'=>$ids));
        if (!$skus) return false;

        if ($dorelease) {
            $update_columns['operator'] = kernel::single('desktop_user')->get_id();
            $update_columns['operator_ip'] = kernel::single('base_component_request')->get_remote_ip();
            $this->app->model('shop_adjustment')->update($update_columns,array('id'=>$ids));
        }

        foreach ($skus as $key => $sku) {
            $s = array(
                'bn'         => $sku['shop_product_bn'],
                'quantity'   => $sku['shop_stock'],
                'lastmodify' => time(),
            );

            if ($dorelease == false) {
                $s['memo'] = $sku['addon']['stock'];
            }

            $stocks[] = $s;
        }

        # 回写
        kernel::single('inventorydepth_service_shop_stock')->items_quantity_list_update($stocks,$shop_id,$dorelease);
    }

    /**
     * 往前端回写库存
     *
     * @return void
     * @author
     **/
    public function doStockRequest($stocks,$shop_id,$doRelease=false)
    {
        # 如果是手动发布，记录发布操作人
        if ($doRelease == true) {
            $data['operator']    = kernel::single('desktop_user')->get_id();
            $data['operator_ip'] = kernel::single('base_component_request')->get_remote_ip();

            $ids = array_keys($stocks);

            $adjustmentModel = $this->app->model('shop_adjustment');
            $adjustmentModel->update($data,array('id'=>$ids));

            //$adjustmentModel->update_shop_stock($ids);
        }

        # 回写开始
        return kernel::single('inventorydepth_service_shop_stock')->items_quantity_list_update($stocks,$shop_id,$doRelease);
    }






    /**
     * 获取自动回写值
     *
     * @return void
     * @author
     **/
    public function getStockConf($shop_id)
    {
        $request = app::get('ome')->getConf('request_auto_stock_' . $shop_id);
        if ($request == 'false') {
            return 'false';
        }

        $request = $this->app->getConf('request_auto_stock_'.$shop_id);

        return ($request === 'true') ? 'true' : 'false';
    }

    /**
     * 保存自动回写值
     *
     * @return void
     * @author
     **/
    public function setStockConf($shop_id,$value)
    {
        $this->app->setConf('request_auto_stock_'.$shop_id,$value);

        app::get('ome')->setConf('request_auto_stock_' . $shop_id, $value);
    }

    /**
     * 获取自动上下架设置
     *
     * @return void
     * @author
     **/
    public function getFrameConf($shop_id)
    {
        $request = $this->app->getConf('request_auto_frame_'.$shop_id);

        return ($request === 'true') ? 'true' : 'false';
    }

    /**
     * 保存自动上下架设置
     *
     * @return void
     * @author
     **/
    public function setFrameConf($shop_id,$value)
    {
        $this->app->setConf('request_auto_frame_'.$shop_id,$value);
    }

    /**
     * 下载店铺商品标识
     * @param string $shop_id 店铺ID
     * @param string $status   状态
     * @param int $time 下载时间
     */
    public function setShopSync($shop_id,$time = null)
    {
        $value = $this->getShopSync($shop_id);
        $value['op_id'] = kernel::single('desktop_user')->get_id();

        if ($time) $value['lastmodify'] = $time;

        $this->app->setConf(sprintf('shop_sync_%s',$shop_id),$value);
    }

    /**
     * 获取同步状态
     *
     * @return void
     * @author
     **/
    public function getShopSync($shop_id)
    {
        $value = $this->app->getConf(sprintf('shop_sync_%s',$shop_id));

        return $value;
    }

    public static function array_addslashes($temp_arr) {
        foreach ($temp_arr as $key => $value) {
            if (is_array($value)) {
                $value = self::array_addslashes($value);
                $array_temp[$key] = $value;
            } else {
                $array_temp[$key]=addslashes($value);
            }
        }
        return $array_temp;
    }
    
    /**
     * Ajax批量下载店铺商品
     * 
     * @param unknown $shop_id
     * @param unknown $filter
     * @param unknown $page
     * @param unknown $errormsg
     */
    public function ajaxDownloadList($shop_id, $filter, $page=0)
    {
        set_time_limit(0);
        ini_set('memory_limit','1024M');
        
        $shopMdl = app::get('ome')->model('shop');
        
        //shopInfo
        $shop = $shopMdl->db_dump($shop_id);
        $result = array('rsp'=>'fail', 'error_msg'=>'', 'msg_code'=>'', 'total'=>0);
        
        //check
        if (!$shop) {
            $result['error_msg'] = '店铺信息不存在！';
            return $result;
        }
        
        if (!$shop['node_id']) {
            $result['error_msg'] = "【{$shop['name']}】店铺未绑定！";
            return $result;
        }
        
        //检查操作的用户
        $sync = $this->getShopSync($shop['shop_id']);
        if ($sync['op_id'] != kernel::single('desktop_user')->get_id()) {
            $operator = kernel::single('desktop_user')->get_name();
            
            $result['error_msg'] = "由于[{$operator}]用户的操作，系统终止了您的请求!";
            return $result;
        }
        
        //平台是否支持拉取商品
        if (!inventorydepth_shop_api_support::items_all_get_support($shop['shop_type'])) {
            $result['error_msg'] = "暂不支持对店铺【{$shop['name']}】商品的同步!";
            return $result;
        }
        
        $shopfactory = inventorydepth_service_shop_factory::createFactory($shop['shop_type'], $shop['business_type']);
        if ($shopfactory === false) {
            $result['error_msg'] = "【{$shop['name']}】店铺类型有误！";
            return $result;
        }
        
        //每次拉取个数
        $customLimit = $shopfactory->getCustomLimit();
        $limit = ($customLimit > 0 ? $customLimit : self::DOWNLOAD_ALL_LIMIT);
        
        //download
        $data = $shopfactory->downloadList($filter, $shop_id, $page, $limit, $error_msg);

        //需要拉取商品的总数
        $totalResults = $shopfactory->getTotalResults();

        // data为负直接return，对于dewu那种第一页merchant_sku_code都是空的，那就没法再发起请求了
        if($data === false && (!$totalResults || !is_numeric($totalResults) || $totalResults<=0)){
            $result['error_msg'] = '没有获取到数据';
            return $result;
        }
        
        //保存数据
        if($data){
            $stores = [];
            $itemModel = $this->app->model('shop_items');
            $skuModel = $this->app->model('shop_skus');
            $skuModel->batchInsert($data,$shop,$stores);
            $itemModel->batchInsert($data,$shop,$stores);
        }
        
        //result
        $result['rsp'] = 'succ';
        $result['total'] = intval($totalResults);
        $result['succNums'] = $limit; //count($data);
        $result['failNums'] = 0;
        
        //页码信息
        $result['limit'] = $limit;
        $result['current_page'] = $page; //当前页码
        $result['all_pages'] = ceil($totalResults / $limit); //总页码
        $result['next_page'] = $page + 1; //下一页
        
        //下一页
        if($result['current_page'] >= $result['all_pages']){
            $result['next_page'] = 0;
        }
        
        return $result;
    }

    public function queryCacheProduct($shop_id,$params)
    {
        if(!$shop_id) return false;

        $result = kernel::single('erpapi_router_request')->set('shop', $shop_id)->product_queryCacheProduct($params);

        return $result;
    }

    public function downloadCacheProductList($shop_id,$filter,$page,&$errormsg)
    {
        $shop = $this->app->model('shop')->db_dump($shop_id);

        if (!$shop) {
            $errormsg = $this->app->_('店铺不存在！'); return false;
        }

        if (!$shop['node_id']) {
            $errormsg = $this->app->_('店铺未绑定！'); return false;
        }

        $shopfactory = inventorydepth_service_shop_factory::createFactory($shop['shop_type'],$shop['business_type']);
        if ($shopfactory === false) {
            $errormsg = $this->app->_('店铺类型有误！'); return false;
        }

        set_time_limit(0); ini_set('memory_limit','1024M');

        //可按店铺类型自定义每次查询的limit解决分销的问题
        $customLimit = $shopfactory->getCustomLimit();
        $used_limit = ($customLimit > 0 ? $customLimit : self::DOWNLOAD_ALL_LIMIT);
        $filter['page'] = $page;
        $filter['page_size'] = $used_limit;
        $result = $this->queryCacheProduct($shop_id,$filter);
        if ($result['rsp'] == 'fail') {
            $errormsg = $result['err_msg'];
            return false;
        }
        if($result['data']['info_list']){
            $data = [];
            $tmpData = $result['data']['info_list'];

            if ($shop['shop_type'] == 'vop') {
                // 把唯品会barcode去当做oms的条码去查询物料类型为普通的销售物料，如果查得到，获取销售物料编码复制给skus的outer_id，这样下载商品就可以正常关联到oms商品 ---barcode to sm_bn start
                $barcodeList = array_column($tmpData, 'sku_id');
                $barcodeVsSmbn = kernel::single('inventorydepth_service_shop_vop')->barcodeToSmbn($barcodeList);
                if ($barcodeVsSmbn) {
                    foreach ($tmpData as $k => $v) {
                        if ($barcodeVsSmbn[$v['sku_id']]) {
                            $tmpData[$k]['oms_sm_bn'] = $barcodeVsSmbn[$v['sku_id']];
                        }
                    }
                }
                // ---barcode to sm_bn end
            }
            foreach ($tmpData as $key => $val) {
                if ($shop['shop_type'] == 'vop') {
                    $goods_key = $val['sku_bn'];
                } else {
                    $goods_key = $val['num_iid'];
                }
                if (!isset($data[$goods_key])) {
                    $data[$goods_key] = [
                        'appove_status' => $val['appove_status'],
                        'sku_id'        => $val['sku_id'],
                        'outer_id'      => '',
                        'num_iid'       => $goods_key,
                    ];
                }

                $data[$goods_key]['skus']['sku'][] = [
                    'appove_status' => $val['appove_status'],
                    'sku_id'        => $val['sku_id'],
                    'outer_id'      => $val['oms_sm_bn']?$val['oms_sm_bn']:$val['sku_bn'],
                    'num_iid'       => $goods_key,
                ];
            }
            
            $stores = [];
            $skuModel = app::get('inventorydepth')->model('shop_skus');
            $itemModel = app::get('inventorydepth')->model('shop_items');
            $skuModel->batchInsert($data,$shop,$stores);
            $itemModel->batchInsert($data,$shop,$stores);
        }

        return true;
    }
}
