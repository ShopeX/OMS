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
 * 拆分翱象同步商品任务Lib类
 *
 * @author wangbiao@shopex.cn
 * @version 2023.03.15
 */
class dchain_autotask_aoxiangsync
{
    /**
     * 拆分翱象同步商品
     */
    public function process($params, &$error_msg='')
    {
        set_time_limit(0);
        ignore_user_abort(1);
        @ini_set('memory_limit','512M');

        $shopMdl = app::get('ome')->model('shop');
        $aoxiangLib = kernel::single('dchain_aoxiang');

        //签约店铺列表
        $shopList = $shopMdl->getList('shop_id,shop_bn,node_id', array('aoxiang_signed'=>'1'));
        if(empty($shopList)){
            return true;
        }

        //list splitSyncProductTask
        foreach ($shopList as $shopKey => $shopInfo)
        {
            //shop
            $shop_id = $shopInfo['shop_id'];
            
            //已解绑的店铺,更新为未签约状态
            if(empty($shopInfo['node_id'])){
                $update_sql = "UPDATE sdb_ome_shop SET aoxiang_signed='0' WHERE shop_id='". $shop_id ."'";
                $shopMdl->db->exec($update_sql);
                
                continue;
            }
            
            //删除product_id=0的商品
            $delete_sql = "DELETE FROM sdb_dchain_aoxiang_product WHERE shop_id='". $shop_id ."' AND product_id=0";
            $shopMdl->db->exec($delete_sql);
            
            //删除product_id=0的商品
            $delete_sql = "DELETE FROM sdb_dchain_aoxiang_skus WHERE shop_id='". $shop_id ."' AND product_id=0";
            $shopMdl->db->exec($delete_sql);
            
            //get config
            $aoxiangConfig = $aoxiangLib->getAoxiangSyncConfig($shop_id);
            if($aoxiangConfig['sync_product'] == 'false') {
                continue; //关闭回写商品
            }
            
            //拆分同步普通商品任务
            $result = $this->splitSyncNormalTask($shopInfo);

            //拆分同步组合商品任务
            $result = $this->splitSyncCombineTask($shopInfo);
            
            
            //[重试]同步今天之前running运行状态的普通商品任务
            $result = $this->runningSyncNormalTask($shopInfo);
            
            //[重试]同步今天之前running运行状态的组合同步商品任务
            $result = $this->runningSyncCombineTask($shopInfo);
            
            //延迟一秒
            sleep(1);
        }
        
        //unset
        unset($shopList);

        return true;
    }

    /**
     * 拆分普通同步商品任务
     * @param $shopInfo
     * @return void
     */
    public function splitSyncNormalTask($shopInfo)
    {
        $axProductMdl = app::get('dchain')->model('aoxiang_product');

        //shop
        $shop_id = $shopInfo['shop_id'];
        $shop_bn = $shopInfo['shop_bn'];
        $limit = 50;

        //filter(不要加入sync_status='running'每次都从0开始读取,会死循环)
        $filter = array('shop_id'=>$shop_id, 'sync_status'=>'none', 'product_type'=>'normal');

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
            $updateData = array('sync_status'=>'running', 'last_modified'=>time());
            $axProductMdl->update($updateData, array('product_bn'=>$product_bns, 'shop_id'=>$shop_id));


            //sdfdata
            $sdfdata = array(
                'uniqid' => sprintf('aoxiang_normal_sync_%s', $page_i),
                'shop_id' => $shop_id,
                'shop_bn' => $shop_bn,
                'task_page' => $page_i,
                'product_type' => 'normal',
                'product_bns' => json_encode($product_bns),
                'task_type' => 'syncaoxiang',
            );

            //MQ4服务器执行
            taskmgr_func::multiQueue($GLOBALS['_MQ_API_CONFIG'],'TG_API_EXCHANGE','TG_API_QUEUE','tg.sys.api.*', $sdfdata);
        }
        
        //unset
        unset($shopInfo, $filter, $axProductList);

        return true;
    }

    /**
     * 拆分组合同步商品任务
     * @param $shopInfo
     * @return void
     */
    public function splitSyncCombineTask($shopInfo)
    {
        $axProductMdl = app::get('dchain')->model('aoxiang_product');

        //shop
        $shop_id = $shopInfo['shop_id'];
        $shop_bn = $shopInfo['shop_bn'];
        $limit = 50;

        //filter(不要加入sync_status='running'每次都从0开始读取,会死循环)
        $filter = array('shop_id'=>$shop_id, 'sync_status'=>'none', 'product_type'=>'combine');

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

            //组合商品(每次都从0开始)
            $axProductList = $axProductMdl->getList('pid,product_bn', $filter, 0, $limit, 'create_time ASC');
            if(empty($axProductList)){
                continue;
            }

            //product_bn
            $product_bns = array_column($axProductList, 'product_bn');

            //update running(更新状态为：执行中)
            $updateData = array('sync_status'=>'running', 'last_modified'=>time());
            $axProductMdl->update($updateData, array('product_bn'=>$product_bns, 'shop_id'=>$shop_id));


            //sdfdata
            $sdfdata = array(
                'uniqid' => sprintf('aoxiang_combine_sync_%s', $page_i),
                'shop_id' => $shop_id,
                'shop_bn' => $shop_bn,
                'task_page' => $page_i,
                'product_type' => 'combine',
                'product_bns' => json_encode($product_bns),
                'task_type' => 'syncaoxiang',
            );

            //MQ4服务器执行
            taskmgr_func::multiQueue($GLOBALS['_MQ_API_CONFIG'],'TG_API_EXCHANGE','TG_API_QUEUE','tg.sys.api.*', $sdfdata);
        }
        
        //unset
        unset($shopInfo, $filter, $axProductList);

        return true;
    }
    
    /**
     * [重试]同步今天之前running运行状态的普通商品任务
     * @param $shopInfo
     * @return void
     */
    public function runningSyncNormalTask($shopInfo)
    {
        $axProductMdl = app::get('dchain')->model('aoxiang_product');
        
        //shop
        $shop_id = $shopInfo['shop_id'];
        $shop_bn = $shopInfo['shop_bn'];
        $limit = 50;
        
        //一个小时之前的时间戳
        $start_time = time() - 3600;
        
        //filter
        $filter = array('shop_id'=>$shop_id, 'sync_status'=>'running', 'product_type'=>'normal', 'last_modified|lthan'=>$start_time);
        
        //count
        $count = $axProductMdl->count($filter);
        if($count <= 0){
            return true;
        }
        
        //last_product_id
        $last_product_id = 0;
        
        //page
        $page_size = ceil($count / $limit);
        for ($page_i=1; $page_i<=$page_size; $page_i++)
        {
            $offset = ($page_i - 1) * $limit;
            
            //add filter
            $filter['product_id|than'] = $last_product_id;
            
            //普通商品(每次都从0开始)
            $axProductList = $axProductMdl->getList('pid,product_bn,product_id', $filter, 0, $limit, 'product_id ASC');
            if(empty($axProductList)){
                continue;
            }
            
            //product_bn
            $product_bns = array_column($axProductList, 'product_bn');
            
            //最后一个商品
            $lastProductInfo = array_pop($axProductList);
            $last_product_id = $lastProductInfo['product_id'];
            
            //更新状态为：running执行中
            $updateData = array('last_modified'=>time());
            $axProductMdl->update($updateData, array('product_bn'=>$product_bns, 'shop_id'=>$shop_id));
            
            //sdfdata
            $sdfdata = array(
                'uniqid' => sprintf('aoxiang_running_normal_%s', $page_i),
                'shop_id' => $shop_id,
                'shop_bn' => $shop_bn,
                'task_page' => $page_i,
                'product_type' => 'normal',
                'product_bns' => json_encode($product_bns),
                'task_type' => 'syncaoxiang',
            );
            
            //MQ4服务器执行
            taskmgr_func::multiQueue($GLOBALS['_MQ_API_CONFIG'],'TG_API_EXCHANGE','TG_API_QUEUE','tg.sys.api.*', $sdfdata);
        }
        
        //unset
        unset($shopInfo, $filter, $axProductList);
        
        return true;
    }
    
    /**
     * [重试]同步今天之前running运行状态的组合同步商品任务
     * @param $shopInfo
     * @return void
     */
    public function runningSyncCombineTask($shopInfo)
    {
        $axProductMdl = app::get('dchain')->model('aoxiang_product');
        
        //shop
        $shop_id = $shopInfo['shop_id'];
        $shop_bn = $shopInfo['shop_bn'];
        $limit = 50;
        
        //一个小时之前的时间戳
        $start_time = time() - 3600;
        
        //filter
        $filter = array('shop_id'=>$shop_id, 'sync_status'=>'running', 'product_type'=>'combine', 'last_modified|lthan'=>$start_time);
        
        //count
        $count = $axProductMdl->count($filter);
        if($count <= 0){
            return true;
        }
        
        //last_product_id
        $last_product_id = 0;
        
        //page
        $page_size = ceil($count / $limit);
        for ($page_i=1; $page_i<=$page_size; $page_i++)
        {
            $offset = ($page_i - 1) * $limit;
            
            //add filter
            $filter['product_id|than'] = $last_product_id;
            
            //组合商品(每次都从0开始)
            $axProductList = $axProductMdl->getList('pid,product_bn,product_id', $filter, 0, $limit, 'create_time ASC');
            if(empty($axProductList)){
                continue;
            }
            
            //product_bn
            $product_bns = array_column($axProductList, 'product_bn');
            
            //最后一个商品
            $lastProductInfo = array_pop($axProductList);
            $last_product_id = $lastProductInfo['product_id'];
            
            //update running(更新状态为：执行中)
            $updateData = array('last_modified'=>time());
            $axProductMdl->update($updateData, array('product_bn'=>$product_bns, 'shop_id'=>$shop_id));
            
            //sdfdata
            $sdfdata = array(
                'uniqid' => sprintf('aoxiang_running_combine_%s', $page_i),
                'shop_id' => $shop_id,
                'shop_bn' => $shop_bn,
                'task_page' => $page_i,
                'product_type' => 'combine',
                'product_bns' => json_encode($product_bns),
                'task_type' => 'syncaoxiang',
            );
            
            //MQ4服务器执行
            taskmgr_func::multiQueue($GLOBALS['_MQ_API_CONFIG'],'TG_API_EXCHANGE','TG_API_QUEUE','tg.sys.api.*', $sdfdata);
        }
        
        //unset
        unset($shopInfo, $filter, $axProductList);
        
        return true;
    }
}