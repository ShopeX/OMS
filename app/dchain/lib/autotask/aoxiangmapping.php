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
 * 翱象同步商品Lib类
 *
 * @author wangbiao@shopex.cn
 * @version 2023.03.15
 */
class dchain_autotask_aoxiangmapping
{
    //当前的执行时间
    public static $now;

    function __construct()
    {
        self::$now = time();
    }

    /**
     * 翱象同步商品
     */
    public function process($params, &$error_msg='')
    {
        set_time_limit(0);
        ignore_user_abort(1);
        @ini_set('memory_limit','512M');
        
        $shopMdl = app::get('ome')->model('shop');
        $aoxiangLib = kernel::single('dchain_aoxiang');
        
        //签约店铺列表
        $shopList = $shopMdl->getList('shop_id,shop_bn', array('aoxiang_signed'=>'1'));
        if(empty($shopList)){
            return true;
        }
        
        //list
        foreach ($shopList as $shopKey => $shopInfo)
        {
            //shop
            $shop_id = $shopInfo['shop_id'];
            
            //get config
            $aoxiangConfig = $aoxiangLib->getAoxiangSyncConfig($shop_id);
            
            //拆分所有商品同步关系任务
            if($aoxiangConfig['sync_product'] != 'false') {
                $result = $this->splitMappingTask($shopInfo);
            }
            
            //回写商品库存任务
            if($aoxiangConfig['sync_stock'] != 'false') {
                $result = $this->syncProductStockTask($shopInfo);
            }
            
            //延迟一秒
            sleep(1);
        }
        
        //unset
        unset($shopList);
        
        return true;
    }
    
    /**
     * 拆分所有商品同步关系任务
     * @param $shopInfo
     * @return void
     */
    public function splitMappingTask($shopInfo)
    {
        $axProductMdl = app::get('dchain')->model('aoxiang_product');
        
        //shop
        $shop_id = $shopInfo['shop_id'];
        $shop_bn = $shopInfo['shop_bn'];
        $limit = 50;
        
        //filter
        $filter = array('shop_id'=>$shop_id, 'sync_status'=>'succ', 'mapping_status'=>'none');
        
        //count
        $count = $axProductMdl->count($filter);
        if($count <= 0){
            return true;
        }
        
        //page
        $page_size = ceil($count / $limit);
        for ($page_i=1; $page_i<=$page_size; $page_i++)
        {
            $offset = ($page_i - 1) * $limit;
            
            //普通商品(每次都从0开始)
            $axProductList = $axProductMdl->getList('pid,product_bn', $filter, 0, $limit, 'create_time ASC');
            if(empty($axProductList)){
                continue;
            }
            
            //product_bn
            $product_bns = array_column($axProductList, 'product_bn');
            
            
            //更新状态为：running执行中
            $updateData = array('mapping_status'=>'running');
            $axProductMdl->update($updateData, array('product_bn'=>$product_bns, 'shop_id'=>$shop_id));
            
            
            //sdfdata
            $sdfdata = array(
                    'uniqid' => sprintf('aoxiang_mapping_sync_%s', $page_i),
                    'shop_id' => $shop_id,
                    'shop_bn' => $shop_bn,
                    'task_page' => $page_i,
                    'product_type' => 'normal',
                    'product_bns' => json_encode($product_bns),
                    'task_type' => 'mappingaoxiang',
            );
            
            //MQ4服务器执行
            taskmgr_func::multiQueue($GLOBALS['_MQ_API_CONFIG'],'TG_API_EXCHANGE','TG_API_QUEUE','tg.sys.api.*', $sdfdata); 
        }
        
        //unset
        unset($shopInfo, $filter, $axProductList);
        
        return true;
    }
    
    /**
     * [初始化]回写商品第一次库存任务
     * @param $shopInfo
     * @return void
     */
    public function syncProductStockTask($shopInfo)
    {
        $axProductMdl = app::get('dchain')->model('aoxiang_product');
        $queueMdl = app::get('base')->model('queue');
        
        //shop
        $shop_id = $shopInfo['shop_id'];
        $limit = 50;
        
        //filter
        $filter = array('shop_id'=>$shop_id, 'sync_stock_time'=>0);
        
        //count
        $count = $axProductMdl->count($filter);
        if($count <= 0){
            return true;
        }
        
        //page
        $page_size = ceil($count / $limit);
        for ($page_i=1; $page_i<=$page_size; $page_i++)
        {
            $offset = ($page_i - 1) * $limit;
            
            //普通商品(每次都从0开始)
            $axProductList = $axProductMdl->getList('pid,product_bn,product_type,sync_status', $filter, 0, $limit, 'create_time ASC');
            if(empty($axProductList)){
                continue;
            }
            
            //product_bn
            $product_bns = array_column($axProductList, 'product_bn');
            
            
            //更新回写库存时间
            $updateData = array('sync_stock_time'=>time());
            $axProductMdl->update($updateData, array('product_bn'=>$product_bns, 'shop_id'=>$shop_id));
            
            
            //使用queue队列回写初始化库存
            $queueData = array(
                'queue_title' => '初始化回写商品库存任务page'. $page_i,
                'start_time' => time(),
                'params' => array(
                    'sdfdata' => array('shopInfo'=>$shopInfo, 'axProductList'=>$axProductList),
                    'app' => 'dchain',
                    'mdl' => 'aoxiang_product',
                ),
                'worker' => 'dchain_aoxiang.autoSyncProductStock',
            );
            $queueMdl->save($queueData);
        }
        
        //unset
        unset($shopInfo, $filter, $axProductList);
    
        return true;
    }
}