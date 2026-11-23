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
 * 翱象系统Lib抽象类
 * 
 * @author wangbiao@shopex.cn
 * @version 2023.03.08
 */
class dchain_product extends dchain_abstract
{
    /**
     * 同步普通商品给到翱象系统
     * @todo：支持批量同步,每次最多同步50条;
     *
     * @param array $dataList
     * @param string $operation
     * @return array
     */
    public function syncNormalProduct($dataList, $operation='')
    {
        $axProductMdl = app::get('dchain')->model('aoxiang_product');
        
        //setting
        $product_type = 'normal'; //普通类型
        
        //get
        $shop_id = $dataList[0]['shop_id'];
        $shop_bn = $dataList[0]['shop_bn'];
        
        //mode
        $operation_name = '系统自动';
        if($operation == 'retry'){
            $operation_name = '重试';
        }elseif($operation == 'manual'){
            $operation_name = '手工';
        }
        
        //product_bns
        $product_bns = array_column($dataList, 'product_bn');

        //list
        $pids = array();
        $params = array();
        foreach ($dataList as $rowKey => $rowInfo)
        {
            $product_bn = $rowInfo['product_bn'];

            //check
            if ($rowInfo['product_type'] == 'combine') {
                $error_msg = '商品编码：' . $product_bn . '不是普通类型';
                continue;
            }

            //params
            $params[] = array(
                'shop_id' => $shop_id, //店铺ID
                'shop_bn' => $shop_bn, //店铺编码
                'sales_material_bn' => $product_bn, //货品编码
                'sales_material_type' => $rowInfo['product_type'], //商品类型
                'sales_material_name' => $rowInfo['product_name'], //商品名称
                'barcode' => $rowInfo['barcode'], //条形码
            );
            
            $pids[] = $rowInfo['pid'];
        }

        //check
        if(empty($params)){
            $error_msg = '没有可同步的商品';
            return $this->error($error_msg);
        }

        
        //update
        $updateData = array('sync_status'=>'running', 'last_modified'=>time());
        $axProductMdl->update($updateData, array('pid'=>$pids, 'sync_status'=>array('none','fail')));

        
        //request
        $result = kernel::single('erpapi_router_request')->set('shop', $shop_id)->product_createAoxiangMaterial($params);
        if(!in_array($result['rsp'], array('succ','running'))){
            $error_msg = ($result['msg'] ? $result['msg'] : $result['err_msg']);

            //update
            $updateData = array('sync_status'=>'fail', 'sync_msg'=>$error_msg, 'last_modified'=>time());
            $axProductMdl->update($updateData, array('pid'=>$pids, 'sync_status'=>array('none','fail','running')));

            return $this->error($error_msg);
        }
        
        return $this->succ();
    }

    /**
     * 同步商品关系给到翱象系统
     * @todo：支持批量同步,每次最多同步50条;
     *
     * @param array $dataList
     * @param string $operation
     * @return array
     */
    public function syncMappingProduct($dataList, $operation='')
    {
        $axProductMdl = app::get('dchain')->model('aoxiang_product');
        $axSkuMdl = app::get('dchain')->model('aoxiang_skus');

        //get
        $shop_id = $dataList[0]['shop_id'];
        $shop_bn = $dataList[0]['shop_bn'];
        $product_type = $dataList[0]['product_type'];

        //mode
        $operation_name = '系统自动';
        if($operation == 'retry'){
            $operation_name = '重试';
        }elseif($operation == 'manual'){
            $operation_name = '手工';
        }
        
        //data
        $pids = array_column($dataList, 'pid', 'pid');
        $axProductList = array_column($dataList, null, 'pid');

        //skuList
        $axSkuList = $axSkuMdl->getList('sid,pid,product_id,shop_iid,shop_sku_id', array('pid'=>$pids), 0, -1, 'pid ASC, sid ASC');
        if(empty($axSkuList)){
            $error_msg = '没有关联的sku明细';
            return $this->error($error_msg);
        }

        //list
        $paramList = array();
        foreach ($axSkuList as $rowKey => $rowInfo)
        {
            $pid = $rowInfo['pid'];

            //productInfo
            $axProductInfo = $axProductList[$pid];
            
            //params
            $paramList[] = array(
                'pid' => $pid,
                'sid' => $rowInfo['sid'],
                'shop_id' => $shop_id, //店铺ID
                'shop_bn' => $shop_bn, //店铺编码
                'sales_material_bn' => $axProductInfo['product_bn'], //商品编码
                'sales_material_type' => $axProductInfo['product_type'], //商品类型
                'sales_material_name' => $axProductInfo['product_name'], //商品名称
                'shop_iid' => $rowInfo['shop_iid'], //店铺商品ID
                'shop_sku_id' => $rowInfo['shop_sku_id'], //店铺货品ID
            );
            
            //筛选中无效的pid
            unset($pids[$pid]);
        }
        
        //更新无效的商品关系
        if($pids){
            //update
            $updateData = array('mapping_status'=>'invalid', 'last_modified'=>time());
            $axProductMdl->update($updateData, array('pid'=>$pids));
        }

        //check
        if(empty($paramList)){
            $error_msg = '没有需要同步关系的普通商品。';
            return $this->error($error_msg);
        }

        //分片
        $paramList = array_chunk($paramList, 50);
        foreach ($paramList as $paramKey => $requestParams)
        {
            $updatePids = array_column($requestParams, 'pid');

            //update
            $updateData = array('mapping_status'=>'running', 'sync_msg'=>'', 'last_modified'=>time());
            $axProductMdl->update($updateData, array('pid'=>$updatePids, 'mapping_status'=>array('none','fail')));

            //request
            $result = kernel::single('erpapi_router_request')->set('shop', $shop_id)->product_mappingAoxiangMaterial($requestParams);
            if(!in_array($result['rsp'], array('succ','running'))){
                $error_msg = ($result['msg'] ? $result['msg'] : $result['err_msg']);

                //update
                $updateData = array('mapping_status'=>'fail', 'sync_msg'=>$error_msg, 'last_modified'=>time());
                $axProductMdl->update($updateData, array('pid'=>$updatePids, 'mapping_status'=>array('none','fail')));
            }
        }

        return $this->succ();
    }

    /**
     * 同步组合商品给到翱象系统
     * @todo：支持批量同步,每次最多同步50条;
     *
     * @param array $dataList
     * @param string $operation
     * @return array
     */
    public function syncCombineProduct($axProductList, $operation='')
    {
        $axProductMdl = app::get('dchain')->model('aoxiang_product');
        
        $axProductLib = kernel::single('dchain_product');
        
        //setting
        $product_type = 'combine'; //普通类型

        //get
        $shop_id = $axProductList[0]['shop_id'];
        $shop_bn = $axProductList[0]['shop_bn'];

        //mode
        $operation_name = '系统自动';
        if($operation == 'retry'){
            $operation_name = '重试';
        }elseif($operation == 'manual'){
            $operation_name = '手工';
        }
        
        //goods_id
        $goods_ids = array_column($axProductList, 'product_id');
        
        //[组合]销售物料关联的子商品
        $tempList = $axProductLib->getPkgMaterialBmList($goods_ids);
        if(empty($tempList)){
            $pids = array_column($axProductList, 'pid');
            
            $error_msg = '组合商品没有子商品!';
            
            //update
            $updateData = array('sync_status'=>'lack', 'mapping_status'=>'invalid', 'sync_msg'=>$error_msg, 'last_modified'=>time());
            $axProductMdl->update($updateData, array('pid'=>$pids, 'sync_status'=>array('none','fail','running')));

            return $this->error($error_msg);
        }

        //format
        $pkgProductItems = array();
        foreach ($tempList as $itemKey => $itemVal)
        {
            $goods_id = $itemVal['sm_id'];
            $product_id = $itemVal['bm_id'];
            
            //items
            $pkgProductItems[$goods_id][] = array(
                'material_bn' => $itemVal['material_bn'],
                'number' => $itemVal['number'],
            );
        }

        //unset
        unset($tempList);

        //list
        $pids = array();
        $pkgParams = array();
        foreach ($axProductList as $rowKey => $rowInfo)
        {
            $pid = $rowInfo['pid'];
            $product_bn = $rowInfo['product_bn'];

            //组合商品product_id
            $goods_id = $rowInfo['product_id'];

            //check
            if ($rowInfo['product_type'] == 'normal') {
                $error_msg = '商品编码：' . $product_bn . '不是组合类型';
                continue;
            }

            //check
            if(empty($pkgProductItems[$goods_id])){
                $error_msg = '组合商品没有子商品!';

                //update
                $updateData = array('sync_status'=>'lack', 'mapping_status'=>'invalid', 'sync_msg'=>$error_msg, 'last_modified'=>time());
                $axProductMdl->update($updateData, array('pid'=>$pid, 'sync_status'=>array('none','fail','running')));

                continue;
            }

            //items
            $itemBnList = $pkgProductItems[$goods_id];

            //params
            $pkgParams[] = array(
                'shop_id' => $shop_id, //店铺ID
                'shop_bn' => $shop_bn, //店铺编码
                'sales_material_bn' => $rowInfo['product_bn'], //组合商品编码
                'sales_material_type' => $rowInfo['product_type'], //组合商品类型
                'sales_material_name' => $rowInfo['product_name'], //组合商品名称
                'barcode' => $rowInfo['barcode'], //条形码
                'itemList' => $itemBnList,
            );

            $pids[] = $rowInfo['pid'];
        }

        //check
        if(empty($pkgParams)){
            $error_msg = '没有可同步的组合商品';

            return $this->error($error_msg);
        }
        
        //update
        $updateData = array('sync_status'=>'running', 'last_modified'=>time());
        $axProductMdl->update($updateData, array('pid'=>$pids, 'sync_status'=>array('none','fail')));
        
        //组合商品
        $result = kernel::single('erpapi_router_request')->set('shop', $shop_id)->product_createAoxiangPkgMaterial($pkgParams);
        if(!in_array($result['rsp'], array('succ','running'))){
            $error_msg = ($result['msg'] ? $result['msg'] : $result['err_msg']);

            //update
            $updateData = array('sync_status'=>'fail', 'sync_msg'=>$error_msg, 'last_modified'=>time());
            $axProductMdl->update($updateData, array('pid'=>$pids, 'sync_status'=>array('none','fail','running')));

            return $this->error($error_msg);
        }
        
        return $this->succ();
    }
    
    /**
     * 指定普通商品同步翱象任务
     *
     * @param $cursor_id
     * @param $params
     * @param $error_msg
     * @return false
     */
    public function assignNormalProduct(&$cursor_id, $params, &$error_msg=null)
    {
        $axProductMdl = app::get('dchain')->model('aoxiang_product');
        $queueMdl = app::get('base')->model('queue');

        //data
        $sdfdata = $params['sdfdata'];
        $shop_id = $sdfdata['shop_id'];
        $product_bns = $sdfdata['product_bns']; //指定的商品编码
        $is_last = $sdfdata['is_last']; //最后一次任务标记

        //check
        if(empty($shop_id)){
            $error_msg = '没有指定商品的店铺ID!';
            return false;
        }

        //filter
        $filter = array('shop_id'=>$shop_id, 'product_type'=>'normal', 'product_bn'=>$product_bns);

        //普通商品
        $axProductList = $axProductMdl->getList('*', $filter, 0, -1, 'create_time ASC');
        if(empty($axProductList)){
            return false;
        }

        //list
        $pids = array();
        $params = array();
        foreach ($axProductList as $rowKey => $rowInfo)
        {
            $product_bn = $rowInfo['product_bn'];

            //check
            if ($rowInfo['product_type'] == 'combine') {
                $error_msg = '商品编码：' . $product_bn . '不是普通类型';
                continue;
            }

            //params
            $params[] = array(
                'shop_id' => $shop_id, //店铺ID
                'shop_bn' => $rowInfo['shop_bn'], //店铺编码
                'sales_material_bn' => $product_bn, //货品编码
                'sales_material_type' => $rowInfo['product_type'], //商品类型
                'sales_material_name' => $rowInfo['product_name'], //商品名称
                'barcode' => $rowInfo['barcode'], //条形码
            );

            $pids[] = $rowInfo['pid'];
        }

        //check
        if(empty($params)){
            return false;
        }

        //update running
        $updateData = array('sync_status'=>'running', 'last_modified'=>time());
        $axProductMdl->update($updateData, array('pid'=>$pids));

        //request
        $result = kernel::single('erpapi_router_request')->set('shop', $shop_id)->product_createAoxiangMaterial($params);
        if(!in_array($result['rsp'], array('succ','running'))){
            $error_msg = ($result['msg'] ? $result['msg'] : $result['err_msg']);

            //update
            $updateData = array('sync_status'=>'fail', 'sync_msg'=>$error_msg, 'last_modified'=>time());
            $axProductMdl->update($updateData, array('pid'=>$pids));
        }
        
        //延迟0.5秒
        //usleep(500000);
        
        //unset
        unset($filter, $axProductList, $params, $result);

        //拆分同步所有商品关系队列任务
        if($is_last == 'true'){
            $queueData = array(
                'queue_title' => '拆分同步所有商品关系队列任务',
                'start_time' => time(),
                'params' => array(
                    'sdfdata' => array('shop_id'=>$shop_id),
                    'app' => 'dchain',
                    'mdl' => 'aoxiang_product',
                ),
                'worker'=> 'dchain_product.splitMappingProductTask',
            );
            $queueMdl->save($queueData);
        }

        return false;
    }

    /**
     * 指定组合商品分配队列任务
     *
     * @param $cursor_id
     * @param $params
     * @param $error_msg
     * @return false
     */
    public function assignCombineProduct(&$cursor_id, $params, &$error_msg=null)
    {
        $axProductMdl = app::get('dchain')->model('aoxiang_product');
        $queueMdl = app::get('base')->model('queue');
    
        $axProductLib = kernel::single('dchain_product');
        
        //data
        $sdfdata = $params['sdfdata'];
        $shop_id = $sdfdata['shop_id'];
        $product_bns = $sdfdata['product_bns']; //指定的商品编码
        $is_last = $sdfdata['is_last']; //最后一次任务标记

        //check
        if(empty($shop_id)){
            $error_msg = '没有指定商品的店铺ID。';
            return false;
        }

        //filter
        $filter = array('shop_id'=>$shop_id, 'product_type'=>'combine', 'product_bn'=>$product_bns);

        //组合商品
        $axProductList = $axProductMdl->getList('*', $filter, 0, -1, 'create_time ASC');
        if(empty($axProductList)){
            return false;
        }

        //goods_id
        $goods_ids = array_column($axProductList, 'product_id');
        
        //[组合]销售物料关联的子商品
        $tempList = $axProductLib->getPkgMaterialBmList($goods_ids);
        if(empty($tempList)){
            $pids = array_column($axProductList, 'pid');

            //update
            $updateData = array('sync_status'=>'lack', 'mapping_status'=>'invalid', 'sync_msg'=>'组合商品没有子商品!', 'last_modified'=>time());
            $axProductMdl->update($updateData, array('pid'=>$pids));

            return false;
        }

        //format
        $pkgProductItems = array();
        foreach ($tempList as $itemKey => $itemVal)
        {
            $goods_id = $itemVal['sm_id'];
            
            //items
            $pkgProductItems[$goods_id][] = array(
                'material_bn' => $itemVal['material_bn'],
                'number' => $itemVal['number'],
            );
        }

        //unset
        unset($tempList);

        //list
        $pids = array();
        $pkgParams = array();
        foreach ($axProductList as $rowKey => $rowInfo)
        {
            $pid = $rowInfo['pid'];
            $product_bn = $rowInfo['product_bn'];

            //组合商品product_id
            $goods_id = $rowInfo['product_id'];

            //check
            if ($rowInfo['product_type'] == 'normal') {
                $error_msg = '商品编码：' . $product_bn . '不是组合类型';
                continue;
            }

            //check
            if(empty($pkgProductItems[$goods_id])){
                //update
                $updateData = array('sync_status'=>'lack', 'mapping_status'=>'invalid', 'sync_msg'=>'组合商品没有子商品!', 'last_modified'=>time());
                $axProductMdl->update($updateData, array('pid'=>$pid));

                continue;
            }

            //items
            $itemBnList = $pkgProductItems[$goods_id];

            //params
            $pkgParams[] = array(
                'shop_id' => $shop_id, //店铺ID
                'shop_bn' => $rowInfo['shop_bn'], //店铺编码
                'sales_material_bn' => $rowInfo['product_bn'], //组合商品编码
                'sales_material_type' => $rowInfo['product_type'], //组合商品类型
                'sales_material_name' => $rowInfo['product_name'], //组合商品名称
                'barcode' => $rowInfo['barcode'], //条形码
                'itemList' => $itemBnList,
            );

            $pids[] = $rowInfo['pid'];
        }

        //check
        if(empty($pkgParams)){
            return false;
        }

        //update running
        $updateData = array('sync_status'=>'running', 'last_modified'=>time());
        $axProductMdl->update($updateData, array('pid'=>$pids));

        //组合商品
        $result = kernel::single('erpapi_router_request')->set('shop', $shop_id)->product_createAoxiangPkgMaterial($pkgParams);
        if(!in_array($result['rsp'], array('succ','running'))){
            $error_msg = ($result['msg'] ? $result['msg'] : $result['err_msg']);

            //update
            $updateData = array('sync_status'=>'fail', 'sync_msg'=>$error_msg, 'last_modified'=>time());
            $axProductMdl->update($updateData, array('pid'=>$pids));
        }

        //延迟0.5秒
        //usleep(500000);

        //unset
        unset($filter, $axProductList, $pkgProductItems, $pkgParams, $result);

        //拆分同步所有商品关系队列任务
        if($is_last == 'true'){
            $queueData = array(
                'queue_title' => '拆分同步所有商品关系队列任务',
                'start_time' => time(),
                'params' => array(
                    'sdfdata' => array('shop_id'=>$shop_id),
                    'app' => 'dchain',
                    'mdl' => 'aoxiang_product',
                ),
                'worker'=> 'dchain_product.splitMappingProductTask',
            );
            $queueMdl->save($queueData);
        }

        return false;
    }
    
    /**
     * 拆分同步商品的任务
     *
     * @param $cursor_id
     * @param $params
     * @param $error_msg
     * @return false
     */
    public function splitMappingProductTask(&$cursor_id, $params, &$error_msg=null)
    {
        set_time_limit(0);
        @ini_set('memory_limit', '1024M');

        $queueMdl = app::get('base')->model('queue');
        $axProductMdl = app::get('dchain')->model('aoxiang_product');

        //seting
        $limit = 50;

        //data
        $sdfdata = $params['sdfdata'];
        $shop_id = $sdfdata['shop_id'];

        //check
        if (empty($shop_id)) {
            $error_msg = '没有可拆分商品的店铺ID!';
            return false;
        }

        //filter
        $filter = array('shop_id'=>$shop_id, 'sync_status'=>'succ', 'mapping_status'=>'none');

        //count
        $count = $axProductMdl->count($filter);
        if($count <= 0){
            return false;
        }

        //page
        $page_size = ceil($count / $limit);
        for ($page_i=1; $page_i<=$page_size; $page_i++)
        {
            $offset = ($page_i - 1) * $limit;

            //普通商品
            $axProductList = $axProductMdl->getList('pid,product_bn', $filter, $offset, $limit, 'create_time ASC');
            if(empty($axProductList)){
                continue;
            }

            //product_bn
            $product_bns = array_column($axProductList, 'product_bn');

            //queue data
            $queueSdf = array(
                'shop_id' => $shop_id,
                'product_bns' => $product_bns,
                'task_page' => $page_i,
            );

            //指定商品同步关系队列任务
            $queueData = array(
                'queue_title' => '指定商品同步关系队列任务-'.$page_i,
                'start_time' => time(),
                'params' => array(
                    'sdfdata' => $queueSdf,
                    'app' => 'dchain',
                    'mdl' => 'aoxiang_product',
                ),
                'worker'=> 'dchain_product.assignMappingProduct',
            );
            $queueMdl->save($queueData);
        }

        return false;
    }
    
    /**
     * 指定商品同步关系队列任务
     *
     * @param $cursor_id
     * @param $params
     * @param $error_msg
     * @return false
     */
    public function assignMappingProduct(&$cursor_id, $params, &$error_msg=null)
    {
        $axProductMdl = app::get('dchain')->model('aoxiang_product');
        $axSkuMdl = app::get('dchain')->model('aoxiang_skus');

        //data
        $sdfdata = $params['sdfdata'];
        $shop_id = $sdfdata['shop_id'];
        $product_bns = $sdfdata['product_bns'];
        
        //check
        if(empty($shop_id)){
            //$error_msg = '没有店铺ID字段。';

            return false;
        }

        //check
        if(empty($product_bns)){
            //$error_msg = '没有指定商品。';

            return false;
        }

        //filter
        $filter = array('shop_id'=>$shop_id, 'product_bn'=>$product_bns);

        //商品列表(包含普通或组合)
        $axProductList = $axProductMdl->getList('pid,product_bn,product_type,product_name,shop_id,shop_bn', $filter, 0, -1, 'create_time ASC');
        if(empty($axProductList)){
            return false;
        }

        //format
        $pids = array_column($axProductList, 'pid');
        $axProductList = array_column($axProductList, null, 'pid');

        //skuList
        $axSkuList = $axSkuMdl->getList('sid,pid,product_id,shop_iid,shop_sku_id', array('pid'=>$pids), 0, -1, 'pid ASC, sid ASC');
        if(empty($axSkuList)){
            return false;
        }

        //list
        $paramList = array();
        foreach ($axSkuList as $rowKey => $rowInfo)
        {
            $pid = $rowInfo['pid'];

            //productInfo
            $axProductInfo = $axProductList[$pid];
            $shop_bn = $axProductInfo['shop_bn'];

            //params
            $paramList[] = array(
                'pid' => $pid,
                'sid' => $rowInfo['sid'],
                'shop_id' => $shop_id, //店铺ID
                'shop_bn' => $shop_bn, //店铺编码
                'sales_material_bn' => $axProductInfo['product_bn'], //商品编码
                'sales_material_type' => $axProductInfo['product_type'], //商品类型
                'sales_material_name' => $axProductInfo['product_name'], //商品名称
                'shop_iid' => $rowInfo['shop_iid'], //店铺商品ID
                'shop_sku_id' => $rowInfo['shop_sku_id'], //店铺货品ID
            );
        }

        //check
        if(empty($paramList)){
            //$error_msg = '没有需要同步关系的商品。';

            return false;
        }

        //分片(每页50条)
        $paramList = array_chunk($paramList, 50);
        foreach ($paramList as $paramKey => $requestParams)
        {
            $updatePids = array_column($requestParams, 'pid');

            //update running
            $updateData = array('mapping_status'=>'running', 'sync_msg'=>'', 'last_modified'=>time());
            $axProductMdl->update($updateData, array('pid'=>$updatePids));

            //request
            $result = kernel::single('erpapi_router_request')->set('shop', $shop_id)->product_mappingAoxiangMaterial($requestParams);
            if(!in_array($result['rsp'], array('succ','running'))){
                $error_msg = ($result['msg'] ? $result['msg'] : $result['err_msg']);

                //update
                $updateData = array('mapping_status'=>'fail', 'sync_msg'=>$error_msg, 'last_modified'=>time());
                $axProductMdl->update($updateData, array('pid'=>$updatePids));
            }
        }

        return false;
    }
    
    
    
    /**
     * 删除翱象系统里OMS同步的商品
     * @todo：支持批量删除,每次最多同步50条;
     *
     * @param array $dataList
     * @param string $operation
     * @return array
     */
    public function deleteProduct($dataList, $operation='')
    {
        $shopMdl = app::get('ome')->model('shop');
        $codeObj = app::get('material')->model('barcode');
        
        $axProductMdl = app::get('dchain')->model('aoxiang_product');
        $axProductLib = kernel::single('dchain_product');
        
        $shop_id = $dataList[0]['shop_id'];
        $product_ids = array_column($dataList, 'product_id');
        
        //商品类型
        $product_type = $dataList[0]['product_type'];
        
        //mode
        $operation_name = '系统自动';
        if($operation == 'retry'){
            $operation_name = '重试';
        }elseif($operation == 'manual'){
            $operation_name = '手工';
        }
        
        //获取条码的类型值
        $barcodeType = material_codebase::getBarcodeType();
        
        //shop
        $shopInfo = $shopMdl->dump(array('shop_id'=>$shop_id), '*');
        if(empty($shopInfo)){
            $error_msg = '没有店铺信息(shop_id：'. $shop_id .')';
            return $this->error($error_msg);
        }
        
        //params
        $pidList = array();
        $params = array();
        foreach ($dataList as $key => $rowInfo)
        {
            $pid = $rowInfo['pid'];
            $product_id = $rowInfo['product_id'];
            
            //未同步成功的单据可直接删除
            if($rowInfo['sync_status'] != 'succ' && $rowInfo['mapping_status'] != 'succ'){
                $axProductMdl->delete(array('pid'=>$pid));
                
                continue;
            }
            
            //未删除商品关系的则报错
            if($rowInfo['mapping_status'] == 'succ'){
                $error_msg = '商品编码：'. $rowInfo['product_bn'] .'存在商品关系,无法删除商品';
                return $this->error($error_msg);
            }
            
            //params
            $params[] = array(
                'shop_bn' => $shopInfo['shop_bn'], //店铺编码
                'shop_id' => $shop_id, //店铺ID
                'sm_id' => $product_id, //商品ID
                'sales_material_bn' => $rowInfo['product_bn'], //商品编码
                'sales_material_name' => $rowInfo['product_name'], //商品名称
                'sales_material_type' => $rowInfo['product_type'], //商品类型
                'barcode' => $rowInfo['barcode'], //条形码
            );
            
            $pidList[] = $pid;
        }
        
        //check
        if(empty($params)){
            return $this->succ();
        }
        
        //request
        $result = kernel::single('erpapi_router_request')->set('shop', $shop_id)->product_deleteAoxiangMaterial($params);
        if(!in_array($result['rsp'], array('succ','running'))){
            $error_msg = ($result['msg'] ? $result['msg'] : $result['err_msg']);
            
            //update
            $updateData = array('delete_status'=>'fail', 'sync_msg'=>$error_msg, 'last_modified'=>time());
            $axProductMdl->update($updateData, array('pid'=>$pidList));
            
            return $this->error($error_msg);
        }
        
        //update
        $updateData = array('delete_status'=>'running', 'sync_msg'=>'', 'last_modified'=>time());
        $axProductMdl->update($updateData, array('pid'=>$pidList));
        
        return $this->succ();
    }
    
    /**
     * 删除翱象系统里OMS同步的商品
     * @todo：支持批量删除,每次最多同步50条;
     *
     * @param array $dataList
     * @param string $operation
     * @return array
     */
    public function deleteProductMapping($dataList, $operation='')
    {
        $shopMdl = app::get('ome')->model('shop');
        $axSkuMdl = app::get('dchain')->model('aoxiang_skus');
        
        $shop_id = $dataList[0]['shop_id'];
        
        //商品类型
        $product_type = $dataList[0]['product_type'];
        
        //mode
        $operation_name = '系统自动';
        if($operation == 'retry'){
            $operation_name = '重试';
        }elseif($operation == 'manual'){
            $operation_name = '手工';
        }
        
        //shop
        $shopInfo = $shopMdl->dump(array('shop_id'=>$shop_id), '*');
        if(empty($shopInfo)){
            $error_msg = '没有店铺信息(shop_id：'. $shop_id .')';
            return $this->error($error_msg);
        }
        
        //pids
        $pids = array_column($dataList, 'pid');
        
        //sku
        $skuList = $axSkuMdl->getList('*', array('pid'=>$pids, 'mapping_status'=>'succ'));
        if(empty($skuList)){
            $error_msg = '没有关联的SKU列表数据';
            return $this->error($error_msg);
        }
        
        //params
        $params = array();
        foreach ($skuList as $key => $rowInfo)
        {
            $pid = $rowInfo['pid'];
            $product_id = $rowInfo['product_id'];
            
            //未同步关系成功的则跳过
            if($rowInfo['mapping_status'] != 'succ'){
                continue;
            }
            
            //params
            $params[] = array(
                'shop_bn' => $shopInfo['shop_bn'], //店铺编码
                'shop_id' => $shop_id, //店铺ID
                'sm_id' => $product_id, //商品ID
                'sales_material_bn' => $rowInfo['product_bn'], //商品编码
                'sales_material_type' => $product_type, //商品类型
                'shop_iid' => $rowInfo['shop_iid'], //店铺商品ID
                'shop_sku_id' => $rowInfo['shop_sku_id'], //店铺货品ID
            );
        }
        
        //check
        if(empty($params)){
            return $this->succ();
        }
        
        //request
        $result = kernel::single('erpapi_router_request')->set('shop', $shop_id)->product_deleteMaterialMapping($params);
        if(!in_array($result['rsp'], array('succ','running'))){
            $error_msg = ($result['msg'] ? $result['msg'] : $result['err_msg']);
            
            return $this->error($error_msg);
        }
        
        return $this->succ();
    }
    
    /**
     * 获取组合销售物料对应的所有基础物料列表
     *
     * @param $sm_ids
     * @return void
     */
    public function getPkgMaterialBmList($sm_ids)
    {
        $saleMaterialMdl = app::get('material')->model('sales_material');
        
        //check
        if(empty($sm_ids)){
            return array();
        }
        
        //[组合]销售物料关联的子商品
        $sql = "SELECT a.*, b.material_bn FROM sdb_material_sales_basic_material AS a LEFT JOIN sdb_material_basic_material AS b ON a.bm_id=b.bm_id ";
        $sql .= "WHERE a.sm_id IN(". implode(',', $sm_ids) .")";
        $pkgProductList = $saleMaterialMdl->db->select($sql);
        
        return $pkgProductList;
    }
}
