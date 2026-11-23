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
 * 平台店铺商品处理Lib抽象类
 * 
 * @author wangbiao@shopex.cn
 * @version 2023.08.25
 */
class dchain_inventorydepth extends dchain_abstract
{
    /**
     * 通过商品编码获取销售物料列表(包含关联的基础物料条形码)
     *
     * @param $productBns
     * @return array
     */
    public function getSlateMaterialList($productBns)
    {
        $salesMaterialObj = app::get('material')->model('sales_material');
        $salesBasicMaterialObj = app::get('material')->model('sales_basic_material');
        $codebaseObj = app::get('material')->model('codebase');
        
        $codeBaseLib = kernel::single('material_codebase');
        
        //销售物料列表
        $salesMList = $salesMaterialObj->getList('sm_id,sales_material_bn,sales_material_name,sales_material_type', array('sales_material_bn'=>$productBns), 0 ,-1);
        if(empty($salesMList)){
            return array();
        }
        
        //sm_id
        $sm_ids = array_column($salesMList, 'sm_id');
        $salesMList = array_column($salesMList, null, 'sales_material_bn');
        
        //销售物料关联的基础物料
        $relations = $salesBasicMaterialObj->getList('sm_id,bm_id,number', array('sm_id'=>$sm_ids), 0, -1);
        $relations = array_column($relations, null, 'sm_id');
        $bm_ids = array_column($relations, 'bm_id');
        
        //基础物料关联的条形码
        $codType = $codeBaseLib->getBarcodeType();
        $barcodeList = $codebaseObj->getList('bm_id,code', array('bm_id'=>$bm_ids, 'type'=>$codType));
        $barcodeList = array_column($barcodeList, null, 'bm_id');
        
        //foramt
        foreach ($salesMList as $sales_material_bn => $val)
        {
            $sm_id = $val['sm_id'];
            
            //bm_id(组合商品只取其中一条记录)
            $bm_id = $relations[$sm_id]['bm_id'];
            
            //barcode
            $barcode = $barcodeList[$bm_id]['code'];
            
            $salesMList[$sales_material_bn]['barcode'] = $barcode;
        }
        
        //unset
        unset($sm_ids, $relations, $bm_ids, $barcodeList);
        
        return $salesMList;
    }
    
    /**
     * 平台主动推送商品创建翱象商品
     * @param $items
     * @param $shopInfo
     * @return array
     */
    public function savePlatformSkus($items, $shopInfo)
    {
        $axProductMdl = app::get('dchain')->model('aoxiang_product');
        
        //params
        $shop_id = $shopInfo['shop_id'];
        $shop_bn = $shopInfo['shop_bn'];
        $skus = $items['skus'];
        $shop_iid = $items['iid']; //shop_iid
        $approve_status = $items['approve_status']; //上下架状态
        
        //check
        if(empty($skus)){
            $error_msg = '没有平台sku数据';
            return $this->error($error_msg);
        }
        
        if(empty($shop_id) || empty($shop_bn)){
            $error_msg = '没有店铺信息';
            return $this->error($error_msg);
        }
        
        //outer_id
        $shop_product_bns = array_column($skus['sku'], 'outer_id');
        
        //销售物料列表
        $salesMList = $this->getSlateMaterialList($shop_product_bns);
        if(empty($salesMList)){
            $error_msg = '没有对应的销售物料数据';
            return $this->error($error_msg);
        }
        
        //skus
        $pkgList = array();
        $insertProducts = array();
        $insertSkus = array();
        foreach($skus['sku'] as $skuKey => $skuVal)
        {
            $shop_product_bn = $skuVal['outer_id'];
            $shop_sku_id = $skuVal['sku_id'];
            $bind = 0;
            
            //过滤空商品编码
            if(empty($shop_product_bn)){
                continue;
            }
            
            //pid
            $pid = md5($shop_id . $shop_product_bn);
            
            //sid
            if($shop_sku_id){
                $sid = md5($shop_id . $shop_iid . $shop_sku_id);
            }else{
                $sid = md5($shop_id . $shop_iid);
            }
            
            //销售物料信息
            $salesMaterialInfo = $salesMList[$shop_product_bn];
            $sm_id = intval($salesMaterialInfo['sm_id']);
            $product_name = $salesMaterialInfo['sales_material_name'];
            
            //barcode
            $barcode = $salesMaterialInfo['barcode'];
            
            //material_type
            if($salesMaterialInfo['sales_material_type'] == 2){
                //PKG组合商品
                $product_type = 'combine';
                $bind = 1;
                
                //combine
                //$pkgList[$sm_id] = $salesMaterialInfo;
            }else{
                //普通商品
                $product_type = 'normal';
                $bind = 0;
            }
            
            //product_mapping
            $product_mapping = ($sm_id > 0 ? '1' : '0');
            
            //商品下架状态则跳过
            if(empty($approve_status)){
                $approve_status = 'onsale'; //默认为上架状态
            }
            
            //format
            $product_name = str_replace(array("'", '"'), '', $product_name);
            
            //insert product
            $sdf = array(
                'pid' => $pid,
                'shop_id' => $shop_id,
                'shop_bn' => $shop_bn,
                'product_id' => $sm_id,
                'product_bn' => $shop_product_bn,
                'barcode' => $barcode,
                'product_name' => $product_name,
                'product_type' => $product_type,
                'shop_iid' => $shop_iid,
                'product_mapping' => $product_mapping, //OMS商品映射
                'approve_status' => $approve_status, //商品在架状态
                'create_time' => time(),
                'last_modified' => time(),
            );
            $insertProducts[$pid] = "('". implode("','", $sdf) ."')";
            
            //insert sku
            $sdf = array(
                'sid' => $sid,
                'pid' => $pid,
                'shop_id' => $shop_id,
                'product_id' => $sm_id,
                'product_bn' => $shop_product_bn,
                'product_name' => $product_name,
                'shop_iid' => $shop_iid,
                'shop_sku_id' => $shop_sku_id,
                'mapping' => $product_mapping, //关联状态
                'bind' => $bind, //是否捆绑
                'create_time' => time(),
                'last_modified' => time(),
            );
            $insertSkus[$sid] = "('". implode("','", $sdf) ."')";
        }
        
        //save product
        if($insertProducts){
            $strFields = 'pid, shop_id, shop_bn, product_id, product_bn, barcode, product_name, product_type';
            $strFields .= ', shop_iid, product_mapping, approve_status, create_time, last_modified';
            
            $strValue = implode(',', $insertProducts);
            
            $insert_sql = "INSERT IGNORE INTO sdb_dchain_aoxiang_product(". $strFields .") VALUES ". $strValue;
            $axProductMdl->db->exec($insert_sql);
        }
        
        //save sku
        if($insertSkus){
            $strFields = 'sid, pid, shop_id, product_id, product_bn, product_name, shop_iid, shop_sku_id, mapping, bind, create_time, last_modified';
            
            $strValue = implode(',', $insertSkus);
            
            $insert_sql = "INSERT IGNORE INTO sdb_dchain_aoxiang_skus(". $strFields .") VALUES ". $strValue;
            $axProductMdl->db->exec($insert_sql);
        }
        
        //combine(组合商品--创建关联的子商品任务)--delete
//        if($pkgList){
//            $params = array('shop_id'=>$shop_id, 'shop_bn'=>$shop_bn, 'product_bns'=>array_column($pkgList, 'pkg_bn'), 'pkgList'=>$pkgList);
//            $this->createCombineSubProduct($params);
//        }
        
        //unset
        unset($productList, $goodsList, $insertProducts, $insertSkus, $pkgList);
        
        return $this->succ();
    }

    /**
     * [初始化]所有商品自动初始化任务
     * 入口：店铺签约后，自动执行初始化任务；
     *
     * @param $cursor_id
     * @param $params
     * @param $error_msg
     * @return false
     */
    public function autoTimerProduct(&$cursor_id, $params, &$error_msg=null)
    {
        set_time_limit(0);
        @ini_set('memory_limit','512M');
        
        $skuMdl = app::get('inventorydepth')->model('shop_skus');
        $shopMdl = app::get('ome')->model('shop');
        $queueMdl = app::get('base')->model('queue');
        
        //data
        $sdfdata = $params['sdfdata'];
        $shop_id = $sdfdata['shop_id'];

        //setting
        $limit = 500;

        //check
        if(empty($shop_id)){
            $error_msg = '没有店铺ID!';
            return false;
        }

        //shop
        $shopInfo = $shopMdl->dump(array('shop_id'=>$shop_id), 'shop_id,shop_bn,aoxiang_signed');
        $shop_bn = $shopInfo['shop_bn'];

        //check
        if(empty($shopInfo)){
            $error_msg = '没有店铺信息(shop_id：'. $shop_id .')!';
            return false;
        }

        if($shopInfo['aoxiang_signed'] != '1'){
            $error_msg = '店铺没有签约翱象(shop_id：'. $shop_id .')!';
            return false;
        }
        
        //check检查如果已经有数据则不能初始化
//        $axProductMdl = app::get('dchain')->model('aoxiang_product');
//        $checkCount = $axProductMdl->count(array('shop_id'=>$shop_id));
//        if($checkCount > 0){
//            $error_msg = '翱象商品表已经有数据,不能重复进行初始化!';
//            return false;
//        }
        
        //where
        $where = " WHERE shop_id='". $shop_id ."'";

        //count
        $countSql = "SELECT count(*) FROM sdb_inventorydepth_shop_skus ". $where;
        $count = $skuMdl->db->count($countSql);
        if($count <= 0){
            //$error_msg = '没有店铺平台普通商品!';

            return false;
        }

        //page
        $page_size = ceil($count / $limit);
        $normalList = array();
        $combineList = array();
        $normal_page = 0;
        $combine_page = 0;
        for ($page_i=1; $page_i<=$page_size; $page_i++)
        {
            $offset = ($page_i - 1) * $limit;

            //sku列表(不要使用ORDER BY outer_createtime ASC进行排序)
            $sql = "SELECT id,shop_product_bn,bind FROM sdb_inventorydepth_shop_skus ". $where ." LIMIT ". $offset .", ". $limit;
            $dataList = $skuMdl->db->select($sql);
            if(empty($dataList)){
                continue;
            }

            //list
            foreach ($dataList as $rowKey => $rowVal)
            {
                $shop_product_bn = $rowVal['shop_product_bn'];
                $is_bind = $rowVal['bind'];

                //check
                if(empty($shop_product_bn)){
                    continue;
                }

                //type
                if($is_bind == '1'){
                    $combineList[$shop_product_bn] = 1;
                }else{
                    $normalList[$shop_product_bn] = 1;
                }
            }

            //最后一页
            $is_normal_flag = false;
            $is_combine_flag = false;
            if($page_i == $page_size){
                if($normalList){
                    $is_normal_flag = true;
                }

                if($combineList){
                    $is_combine_flag = true;
                }
            }

            //queue normal
            //@todo：每次普通商品只有大于100条时才创建queue队列任务,节省资源;
            if(count($normalList) >= 100 || $is_normal_flag){
                $normal_page++;

                //sdfdata
                $sdfData = array(
                    'shop_id' => $shop_id,
                    'shop_bn' => $shop_bn,
                    'product_bns' => array_keys($normalList),
                    'task_page' => $normal_page,
                );

                //普通商品分配给翱象队列任务
                $queueData = array(
                    'queue_title' => '普通商品分配给翱象队列任务'. $normal_page,
                    'start_time' => time(),
                    'params' => array(
                        'sdfdata' => $sdfData,
                        'app' => 'dchain',
                        'mdl' => 'aoxiang_product',
                    ),
                    'worker'=> 'dchain_inventorydepth.autoDispatchNormalProduct',
                );
                $queueMdl->save($queueData);

                //reset重置并开始下一次任务
                $normalList = array();
            }

            //queue combine
            //@todo：每次组合商品只有大于100条时才创建queue队列任务,节省资源;
            if(count($combineList) >= 100 || $is_combine_flag){
                $combine_page++;

                //sdfdata
                $sdfData = array(
                    'shop_id' => $shop_id,
                    'shop_bn' => $shop_bn,
                    'product_bns' => array_keys($combineList),
                    'task_page' => $combine_page,
                );

                //组合商品分配给翱象队列任务
                $queueData = array(
                    'queue_title' => '组合商品分配给翱象队列任务'. $combine_page,
                    'start_time' => time(),
                    'params' => array(
                        'sdfdata' => $sdfData,
                        'app' => 'dchain',
                        'mdl' => 'aoxiang_product',
                    ),
                    'worker'=> 'dchain_inventorydepth.autoDispatchCombineProduct',
                );
                $queueMdl->save($queueData);

                //reset重置并开始下一次任务
                $combineList = array();
            }

            //unset
            unset($dataList);
        }

        //unset
        unset($shopInfo, $normalList, $combineList, $normal_page, $combine_page);

        return false;
    }

    /**
     * [普通商品]自动分配队列任务
     *
     * @param $cursor_id
     * @param $params
     * @param $error_msg
     * @return false
     */
    public function autoDispatchNormalProduct(&$cursor_id, $params, &$error_msg=null)
    {
        $shopItemMdl = app::get('inventorydepth')->model('shop_items');
        $skuMdl = app::get('inventorydepth')->model('shop_skus');
        $axProductMdl = app::get('dchain')->model('aoxiang_product');
        
        //data
        $sdfdata = $params['sdfdata'];

        $shop_id = $sdfdata['shop_id'];
        $shop_bn = $sdfdata['shop_bn'];
        $product_bns = $sdfdata['product_bns']; //指定的商品编码

        //setting
        $product_type = 'normal'; //普通类型
        $bind_type = 0; //是否组合商品

        //check
        if(empty($shop_id) || empty($shop_bn)){
            $error_msg = '没有店铺信息!';
            return false;
        }

        if(empty($product_bns)){
            $error_msg = '没有指定的普通商品!';
            return false;
        }
        
        //fitler
        $filter = array('shop_id'=>$shop_id, 'shop_product_bn'=>$product_bns);
        
        //sku列表
        $dataList = $skuMdl->getList('id,shop_iid,shop_sku_id,shop_product_bn,shop_title,mapping,bind', $filter);
        if(empty($dataList)){
            return false;
        }
        
        //shop_iid
        $shop_iids = array_column($dataList, 'shop_iid');
        
        //shop_items
        $skuItemList = $shopItemMdl->getList('id,iid,bn,approve_status', array('shop_id'=>$shop_id, 'iid'=>$shop_iids));
        if($skuItemList){
            $skuItemList = array_column($skuItemList, null, 'iid');
        }
        
        //销售物料列表
        $salesMList = $this->getSlateMaterialList($product_bns);
        if(empty($salesMList)){
            $error_msg = '没有对应的销售物料数据';
            return $this->error($error_msg);
        }
        
        //list
        $insertProducts = array();
        $insertSkus = array();
        foreach ($dataList as $key => $val)
        {
            $shop_product_bn = $val['shop_product_bn'];
            $shop_iid = $val['shop_iid'];
            $shop_sku_id = $val['shop_sku_id'];

            //过滤空商品编码
            if(empty($shop_product_bn)){
                continue;
            }

            //pid
            $pid = md5($shop_id . $shop_product_bn);

            //sid
            if($shop_sku_id){
                $sid = md5($shop_id . $shop_iid . $shop_sku_id);
            }else{
                $sid = md5($shop_id . $shop_iid);
            }
            
            //productInfo
            $productInfo = $salesMList[$shop_product_bn];
            $sm_id = intval($productInfo['sm_id']);
            
            //product_mapping
            $product_mapping = ($sm_id > 0 ? '1' : '0');
            
            //商品下架状态则跳过
            $approve_status = $skuItemList[$shop_iid]['approve_status'];
            if(empty($approve_status)){
                $approve_status = 'onsale'; //默认为上架状态
            }
            
            //product_name
            $product_name = ($val['shop_title'] ? $val['shop_title'] : $productInfo['sales_material_name']);
            
            //format
            $product_name = str_replace(array("'", '"'), '', $product_name);

            //add
            $sdf = array(
                'pid' => $pid,
                'shop_id' => $shop_id,
                'shop_bn' => $shop_bn,
                'product_id' => $sm_id,
                'product_bn' => $shop_product_bn,
                'barcode' => $productInfo['barcode'],
                'product_name' => $product_name,
                'product_type' => $product_type,
                'shop_iid' => $shop_iid,
                'product_mapping' => $product_mapping, //OMS商品映射
                'approve_status' => $approve_status, //商品在架状态
                'create_time' => time(),
                'last_modified' => time(),
            );
            $insertProducts[$pid] = "('". implode("','", $sdf) ."')";
            
            //insert sku
            $sdf = array(
                'sid' => $sid,
                'pid' => $pid,
                'shop_id' => $shop_id,
                'product_id' => $sm_id,
                'product_bn' => $shop_product_bn,
                'product_name' => $product_name,
                'shop_iid' => $shop_iid,
                'shop_sku_id' => $shop_sku_id,
                'mapping' => $product_mapping, //关联状态
                'bind' => $bind_type, //是否捆绑
                'create_time' => time(),
                'last_modified' => time(),
            );
            $insertSkus[$sid] = "('". implode("','", $sdf) ."')";
        }
        
        //save product
        if($insertProducts){
            $strFields = 'pid, shop_id, shop_bn, product_id, product_bn, barcode, product_name, product_type';
            $strFields .= ', shop_iid, product_mapping, approve_status, create_time, last_modified';
            
            $strValue = implode(',', $insertProducts);
            
            $insert_sql = "INSERT IGNORE INTO sdb_dchain_aoxiang_product(". $strFields .") VALUES ". $strValue;
            $axProductMdl->db->exec($insert_sql);
        }
        
        //save sku
        if($insertSkus){
            $strFields = 'sid, pid, shop_id, product_id, product_bn, product_name, shop_iid, shop_sku_id, mapping, bind, create_time, last_modified';
            
            $strValue = implode(',', $insertSkus);
            
            $insert_sql = "INSERT IGNORE INTO sdb_dchain_aoxiang_skus(". $strFields .") VALUES ". $strValue;
            $axProductMdl->db->exec($insert_sql);
        }
        
        //unset
        unset($dataList, $skuItemList, $productList, $shop_iids, $product_bns, $insertProducts, $insertSkus);
        
        return false;
    }

    /**
     * [组合商品]自动分配队列任务
     *
     * @param $cursor_id
     * @param $params
     * @param $error_msg
     * @return false
     */
    public function autoDispatchCombineProduct(&$cursor_id, $params, &$error_msg=null)
    {
        $shopItemMdl = app::get('inventorydepth')->model('shop_items');
        $skuMdl = app::get('inventorydepth')->model('shop_skus');
        $axProductMdl = app::get('dchain')->model('aoxiang_product');

        //data
        $sdfdata = $params['sdfdata'];

        $shop_id = $sdfdata['shop_id'];
        $shop_bn = $sdfdata['shop_bn'];
        $product_bns = $sdfdata['product_bns']; //指定的商品编码

        //setting
        $product_type = 'combine'; //组合类型
        $bind_type = 1; //是否组合商品

        //check
        if(empty($shop_id) || empty($shop_bn)){
            $error_msg = '没有店铺信息;';
            return false;
        }

        if(empty($product_bns)){
            $error_msg = '没有指定的组合商品;';
            return false;
        }

        //fitler
        $filter = array('shop_id'=>$shop_id, 'shop_product_bn'=>$product_bns);

        //sku列表
        $dataList = $skuMdl->getList('id,shop_iid,shop_sku_id,shop_product_bn,shop_title,mapping,bind', $filter);
        if(empty($dataList)){
            return false;
        }

        //shop_iid
        $shop_iids = array_column($dataList, 'shop_iid');

        //shop_items
        $skuItemList = $shopItemMdl->getList('id,iid,bn,approve_status', array('shop_id'=>$shop_id, 'iid'=>$shop_iids));
        if($skuItemList){
            $skuItemList = array_column($skuItemList, null, 'iid');
        }
        
        //销售物料列表
        $salesMList = $this->getSlateMaterialList($product_bns);
        if(empty($salesMList)){
            $error_msg = '没有对应的销售物料数据';
            return $this->error($error_msg);
        }
        
        //list
        $insertProducts = array();
        $insertSkus = array();
        foreach ($dataList as $key => $val)
        {
            $shop_product_bn = $val['shop_product_bn'];
            $shop_iid = $val['shop_iid'];
            $shop_sku_id = $val['shop_sku_id'];

            //过滤空商品编码
            if(empty($shop_product_bn)){
                continue;
            }

            //pid
            $pid = md5($shop_id . $shop_product_bn);

            //sid
            if($shop_sku_id){
                $sid = md5($shop_id . $shop_iid . $shop_sku_id);
            }else{
                $sid = md5($shop_id . $shop_iid);
            }

            //goodsInfo
            $goodsInfo = $salesMList[$shop_product_bn];
            $sm_id = intval($goodsInfo['sm_id']);
            
            //product_mapping
            $product_mapping = ($sm_id > 0 ? '1' : '0');
            
            //商品下架状态则跳过
            $approve_status = $skuItemList[$shop_iid]['approve_status'];
            if(empty($approve_status)){
                $approve_status = 'onsale'; //默认为上架状态
            }
            
            //product_name
            $product_name = ($val['shop_title'] ? $val['shop_title'] : $goodsInfo['sales_material_name']);
            
            //format
            $product_name = str_replace(array("'", '"'), '', $product_name);
            
            //add
            $sdf = array(
                'pid' => $pid,
                'shop_id' => $shop_id,
                'shop_bn' => $shop_bn,
                'product_id' => $sm_id,
                'product_bn' => $shop_product_bn,
                'barcode' => $goodsInfo['barcode'], //默认为空(组合商品没有barcode)
                'product_name' => $product_name,
                'product_type' => $product_type,
                'shop_iid' => $shop_iid,
                'product_mapping' => $product_mapping, //OMS商品映射
                'approve_status' => $approve_status, //商品在架状态
                'create_time' => time(),
                'last_modified' => time(),
            );
            $insertProducts[$pid] = "('". implode("','", $sdf) ."')";

            //insert sku
            $sdf = array(
                'sid' => $sid,
                'pid' => $pid,
                'shop_id' => $shop_id,
                'product_id' => $sm_id,
                'product_bn' => $shop_product_bn,
                'product_name' => $product_name,
                'shop_iid' => $shop_iid,
                'shop_sku_id' => $shop_sku_id,
                'mapping' => $product_mapping, //关联状态
                'bind' => $bind_type, //是否捆绑
                'create_time' => time(),
                'last_modified' => time(),
            );
            $insertSkus[$sid] = "('". implode("','", $sdf) ."')";
        }

        //save product
        if($insertProducts){
            $strFields = 'pid, shop_id, shop_bn, product_id, product_bn, barcode, product_name, product_type';
            $strFields .= ', shop_iid, product_mapping, approve_status, create_time, last_modified';

            $strValue = implode(',', $insertProducts);

            $insert_sql = "INSERT IGNORE INTO sdb_dchain_aoxiang_product(". $strFields .") VALUES ". $strValue;
            $axProductMdl->db->exec($insert_sql);
        }

        //save sku
        if($insertSkus){
            $strFields = 'sid, pid, shop_id, product_id, product_bn, product_name, shop_iid, shop_sku_id, mapping, bind, create_time, last_modified';

            $strValue = implode(',', $insertSkus);

            $insert_sql = "INSERT IGNORE INTO sdb_dchain_aoxiang_skus(". $strFields .") VALUES ". $strValue;
            $axProductMdl->db->exec($insert_sql);
        }

        //组合商品创建关联的子商品任务--delete
//        $params = array(
//            'shop_id' => $shop_id,
//            'shop_bn' => $shop_bn,
//            'product_bns' => $product_bns,
//            'pkgList' => $goodsList,
//        );
//        $result = $this->createCombineSubProduct($params);

        //unset
        unset($dataList, $skuItemList, $goodsList, $shop_iids, $product_bns, $insertProducts, $insertSkus);

        return false;
    }

    /**
     * [task任务]指定普通商品同步任务
     *
     * @param $params
     * @return array
     */
    public function taskNormalProduct($params)
    {
        $axProductMdl = app::get('dchain')->model('aoxiang_product');
        
        //data
        $shop_id = $params['shop_id'];
        $product_bns = $params['product_bns']; //指定的商品编码
        
        //check
        if(empty($shop_id)){
            $error_msg = '没有指定商品的店铺ID!';
            return $this->error($error_msg);
        }
        
        //filter
        $filter = array('shop_id'=>$shop_id, 'product_bn'=>$product_bns);
        
        //普通商品
        $axProductList = $axProductMdl->getList('*', $filter);
        if(empty($axProductList)){
            $error_msg = '没有可执行的普通商品';
            return $this->error($error_msg);
        }

        //list
        $pids = array();
        $params = array();
        foreach ($axProductList as $rowKey => $rowInfo)
        {
            $product_bn = $rowInfo['product_bn'];

            //check
            if ($rowInfo['product_type'] == 'combine') {
                //$error_msg = '商品编码：' . $product_bn . '不是普通类型';
                continue;
            }
            
            //params
            $params[] = array(
                'shop_id' => $shop_id, //店铺ID
                'shop_bn' => $rowInfo['shop_bn'], //店铺编码
                'sales_material_bn' => $product_bn, //货品编码
                'sales_material_type' => $rowInfo['product_type'], //销售物料类型
                'sales_material_name' => ($rowInfo['product_name'] ? $rowInfo['product_name'] : $product_bn), //销售物料名称
                'barcode' => $rowInfo['barcode'], //条形码
            );
            
            $pids[] = $rowInfo['pid'];
        }

        //check
        if(empty($params)){
            $error_msg = '没有可同步的销售物料';
            return $this->error($error_msg);
        }

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

        return $this->succ();
    }

    /**
     * [task任务]指定组合商品同步任务
     *
     * @param $params
     * @return array
     */
    public function taskCombineProduct($params)
    {
        $axProductMdl = app::get('dchain')->model('aoxiang_product');
        
        $axProductLib = kernel::single('dchain_product');
        
        //data
        $shop_id = $params['shop_id'];
        $product_bns = $params['product_bns']; //指定的商品编码
        
        //check
        if(empty($shop_id)){
            $error_msg = '没有指定商品的店铺ID。';
            return $this->error($error_msg);
        }

        //filter
        $filter = array('shop_id'=>$shop_id, 'product_bn'=>$product_bns);

        //组合商品
        $axProductList = $axProductMdl->getList('*', $filter);
        if(empty($axProductList)){
            $error_msg = '没有可执行的组合商品';
            return $this->error($error_msg);
        }
        
        //goods_id
        $goods_ids = array_column($axProductList, 'product_id');
        
        //[组合]销售物料关联的基础物料
        $tempList = $axProductLib->getPkgMaterialBmList($goods_ids);
        if(empty($tempList)){
            $pids = array_column($axProductList, 'pid');
            
            //update
            $updateData = array('sync_status'=>'lack', 'mapping_status'=>'invalid', 'sync_msg'=>'组合商品没有子商品!', 'last_modified'=>time());
            $axProductMdl->update($updateData, array('pid'=>$pids));

            $error_msg = '组合商品没有子商品';
            return $this->error($error_msg);
        }

        //format
        $pkgProductItems = array();
        foreach ($tempList as $itemKey => $itemVal)
        {
            $sm_id = $itemVal['sm_id'];
            
            //items
            $pkgProductItems[$sm_id][] = array(
                'material_bn' => $itemVal['material_bn'],
                'number' => $itemVal['number'],
            );
        }
        
        //unset
        unset($tempList);

        //list
        $pids = array();
        $pkgParams = array();
        $lackPids = array();
        foreach ($axProductList as $rowKey => $rowInfo)
        {
            $pid = $rowInfo['pid'];
            
            //组合商品product_id
            $goods_id = $rowInfo['product_id'];
            
            //check
            if ($rowInfo['product_type'] == 'normal') {
                //$error_msg = '商品编码：' . $product_bn . '不是组合类型';
                continue;
            }
            
            //check
            if(empty($pkgProductItems[$goods_id])){
                $lackPids[] = $pid;
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
                'sales_material_name' => ($rowInfo['product_name'] ? $rowInfo['product_name'] : $rowInfo['product_bn']), //组合商品名称
                'barcode' => $rowInfo['barcode'], //条形码
                'itemList' => $itemBnList,
            );

            $pids[] = $pid;
        }
        
        //更新为组合无子货品
        if($lackPids){
            //update
            $updateData = array('sync_status'=>'lack', 'mapping_status'=>'invalid', 'sync_msg'=>'组合商品没有子商品!', 'last_modified'=>time());
            $axProductMdl->update($updateData, array('pid'=>$lackPids));
        }
        
        //check
        if(empty($pkgParams)){
            $error_msg = '没有可同步的组合销售物料';
            return $this->error($error_msg);
        }

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
        unset($filter, $axProductList, $pkgProductItems, $pkgParams, $result, $lackPids);

        return $this->succ();
    }

    /**
     * 指定商品同步关系
     *
     * @param $params
     * @param $error_msg
     * @return false
     */
    public function reqeustMappingProduct($params)
    {
        $axProductMdl = app::get('dchain')->model('aoxiang_product');
        $axSkuMdl = app::get('dchain')->model('aoxiang_skus');

        //data
        $shop_id = $params['shop_id'];
        $product_bns = $params['product_bns'];

        //check
        if(empty($shop_id)){
            $error_msg = '没有店铺ID字段。';
            return $this->error($error_msg);
        }

        //check
        if(empty($product_bns)){
            $error_msg = '没有指定商品。';
            return $this->error($error_msg);
        }

        //filter
        $filter = array('shop_id'=>$shop_id, 'product_bn'=>$product_bns);

        //商品列表(包含普通或组合)
        $axProductList = $axProductMdl->getList('pid,product_bn,product_type,product_name,shop_id,shop_bn', $filter);
        if(empty($axProductList)){
            $error_msg = '没有需要同步关系的商品';
            return $this->error($error_msg);
        }

        //format
        $pids = array_column($axProductList, 'pid', 'pid');
        $axProductList = array_column($axProductList, null, 'pid');

        //skuList
        $axSkuList = $axSkuMdl->getList('sid,pid,product_id,shop_iid,shop_sku_id', array('pid'=>$pids), 0, -1, 'create_time ASC');
        if(empty($axSkuList)){
            $error_msg = '没有需要同步关系的SKU';
            return $this->error($error_msg);
        }

        //list
        $paramList = array();
        foreach ($axSkuList as $rowKey => $rowInfo)
        {
            $pid = $rowInfo['pid'];

            //productInfo
            $axProductInfo = $axProductList[$pid];

            //shop_bn
            $shop_bn = $axProductInfo['shop_bn'];

            //params
            $paramList[] = array(
                'pid' => $pid,
                'sid' => $rowInfo['sid'],
                'shop_id' => $shop_id, //店铺ID
                'shop_bn' => $shop_bn, //店铺编码
                'sales_material_bn' => $axProductInfo['product_bn'], //销售物料编码
                'sales_material_type' => $axProductInfo['product_type'], //销售物料类型
                'sales_material_name' => $axProductInfo['product_name'], //销售物料名称
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
            $error_msg = '没有需要同步关系的商品。';
            return $this->error($error_msg);
        }
        
        //分片(每页50条)
        $paramList = array_chunk($paramList, 50);
        foreach ($paramList as $paramKey => $requestParams)
        {
            $updatePids = array_column($requestParams, 'pid');
            
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
}
