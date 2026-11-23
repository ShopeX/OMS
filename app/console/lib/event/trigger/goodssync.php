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

class console_event_trigger_goodssync{

    
    /**
     *
     * 商品同步通知创建发起方法
     * @param string $wms_id 仓库类型ID
     * @param array $data 商品同步数据信息
     * @param string $sync 是否同步请求，true为同步，false异步，默认异步
     */
    public function create($wms_id, &$data, $sync = false){
        $db = kernel::database();
        $limit = 1000;
        $count = ceil(count($data)/$limit);
        for ($page=1;$page<=$count;$page++){
            $lim = ($page-1)*$limit;
            
            $params = array();
            $bns = array();
            for ($key=$lim;$key<$lim+$limit;$key++){
                
                if (!isset($data[$key])) break;
                $bns[] = '\''.$data[$key]['bn'].'\'';
                $params[] = $data[$key];
            }
            
            if ($params){
                //如果是自有仓储,直接更新成功
                $selfwms_list = kernel::single('console_goodssync')->get_wms_list('selfwms');
                
                if (in_array($wms_id,$selfwms_list)){
                    $new_tag = '1';
                    $sync_status = '3';
                    
                    $result = kernel::single('console_foreignsku')->batch_syncupdate($wms_id,$new_tag,$sync_status,$bns);
                }else{
                    $new_tag = '1';
                    $sync_status = '2';
                    $result = kernel::single('console_foreignsku')->batch_syncupdate($wms_id,$new_tag,$sync_status,$bns);
                }

                // $rs = kernel::single('middleware_wms_request', $wms_id)->goods_add($params,$sync);
                $rs = kernel::single('erpapi_router_request')->set('wms',$wms_id)->goods_add($params);

                unset($params);
               
            }
        }

        
    }

    /**
     *
     * 采购通知创建发起的响应接收方法
     * @param array $data
     */
    public function create_callback($res){

    }

    /**
     *
     * 商品同步通知更新发起方法
     * @param string $wms_id 仓库类型ID
     * @param array $data 商品同步数据信息
     * @param string $sync 是否同步请求，true为同步，false异步，默认异步
     */
    public function update($wms_id, &$data, $sync = false){
        $limit = 1000;
        $count = ceil(count($data)/$limit);
        ksort($data);
        for ($page=1;$page<=$count;$page++){
            $lim = ($page-1)*$limit;
            $params = array();
            $bns = array();
            for ($key=$lim;$key<$lim+$limit;$key++){
                if (!isset($data[$key])) break;
                $bns[] = '\''.$data[$key]['bn'].'\'';
                $params[] = $data[$key];
            }
            if ($params){
                
                

                //如果第三方仓储返回成功,则更新商品同步状态为已同步
                $selfwms_list = kernel::single('console_goodssync')->get_wms_list('selfwms');
               
                if (in_array($wms_id,$selfwms_list)){
                    $new_tag = '1';
                    $sync_status = '3';
                    kernel::single('console_foreignsku')->batch_syncupdate($wms_id,$new_tag,$sync_status,$bns);
                }else{
                    $new_tag = '1';
                    $sync_status = '2';
                    kernel::single('console_foreignsku')->batch_syncupdate($wms_id,$new_tag,$sync_status,$bns);
                }
                // $rs = kernel::single('middleware_wms_request', $wms_id)->goods_update($data,$sync);
                $rs = kernel::single('erpapi_router_request')->set('wms',$wms_id)->goods_update($data);

                unset($params);
            }
        }
        return true;
    }

    /**
     *
     * 商品通知更新发起的响应接收方法
     * @param array $data
     */
    public function update_callback($res){
        
    }

    /**
     * 组合关系同步(PKG捆绑商品同步)
     * 
     * @param unknown $id
     * @param unknown $branchId
     * @return multitype:string
     */
    public function syncCombination($id, $branchId)
    {
        $salesBasicMaterialObj = app::get('material')->model('sales_basic_material');
        $basicMaterialObj = app::get('material')->model('basic_material');
        $branch = app::get('ome')->model('branch')->db_dump(array('branch_id'=>$branchId),'branch_id, branch_bn');
        $material = app::get('console')->model('foreign_sku')->db_dump(array('inner_product_id'=>$id,'inner_type'=>array('1','2')));

       
        if(empty($material)) {
            return array('rsp'=>'fail', 'msg' => $id . '：数据缺失，已经被删除');
        }

        if(empty($material['outer_sku'])) {
            return array('rsp'=>'fail', 'msg' => $material['inner_sku'] . '：还没有外部sku');
        }
        
        if($material['inner_type'] == '2'){
            $basicMaterialCombinationItemsObj = app::get('material')->model('basic_material_combination_items');
            $seMiBasicMInfos = $basicMaterialCombinationItemsObj->getList('pbm_id,bm_id,material_name,material_bn,material_num',array('pbm_id'=>$material['inner_product_id']), 0, -1);

            $products = array();
            $productBn = array();
            foreach($seMiBasicMInfos as $k => $v)
            {
                $bm_id = $v['bm_id'];
                
                $products[$bm_id] = array(

                    'product_id'   =>$bm_id,
                    'bn'           =>$v['material_bn'],
                    'pkgnum'       =>$v['material_num'],  

                );
                $productBn[] = $v['material_bn'];
            }
     

        }else{
            //获取PKG捆绑销售物料绑定的基础物料
            $salesBasicMInfos = $salesBasicMaterialObj->getList('bm_id,sm_id,number,rate',array('sm_id'=>$material['inner_product_id']), 0, -1);
            if(empty($salesBasicMInfos)){
                return array('rsp'=>'fail', 'msg'=>$material['inner_sku'] . '没有找到绑定基础物料关系');
            }
            
            $bmIds = array();
            $bmList = array();
            foreach($salesBasicMInfos as $k => $salesBasicMInfo)
            {
                $bm_id = $salesBasicMInfo['bm_id'];
                
                $bmIds[] = $bm_id;
                $bmList[$bm_id] = $salesBasicMInfo;
            }

            $basicMaterialInfos = $basicMaterialObj->getList('bm_id,material_name,material_bn',array('bm_id'=>$bmIds), 0, -1);
            if(empty($basicMaterialInfos)){
                return array('rsp'=>'fail', 'msg'=>$material['inner_sku'] . '捆绑商品明细不存在');
            }

            $products = array();
            $productBn = array();
            foreach($basicMaterialInfos as $key => $val)
            {
                $bm_id = $val['bm_id'];

                $val['product_id'] = $bm_id; //兼容货号ID字段
                $val['bn'] = $val['material_bn']; //兼容货号字段
                $val['pkgnum'] = $bmList[$bm_id]['number']; //兼容绑定数量字段

                $products[$val['product_id']] = $val;
                $productBn[] = $val['bn'];
            }
        }

        
        //检查PKG捆绑销售物料下的子基础物料是否已经有外部SKU
        $productMaterial = app::get('console')->model('foreign_sku')->getList('*', array('inner_sku'=>$productBn,'wms_id'=>$material['wms_id']));
        if(empty($productMaterial)) {
            $first = current($products);
            return array('rsp'=>'fail', 'msg' => $material['inner_sku'] . '：子商品' . $first['bn'] . '尚未获取外部sku');
        }
        $materialItems = array();
        foreach($productMaterial as $val)
        {
            $product_id = $val['inner_product_id'];
            
            if(empty($val['outer_sku'])) {
                return array('rsp'=>'fail', 'msg' => $material['inner_sku'] . '：子商品' . $val['inner_sku'] . '尚未获取外部sku');
            }
            
            $materialItems[] = array(
                'inner_sku' => $val['inner_sku'],
                'outer_sku' => $val['outer_sku'],
                'num' => $products[$product_id]['pkgnum']
            );
            
            if($products[$product_id]) {
                unset($products[$product_id]);
            } else {
                return array('rsp'=>'fail', 'msg' => $material['inner_sku'] . '：子商品' . $val['inner_sku'] . '需要重新分配');
            }
        }
        
        if($products) {
            $first = current($products);
            return array('rsp'=>'fail', 'msg' => $material['inner_sku'] . '：子商品' . $first['bn'] . '尚未获取外部sku');
        }
        
        
        $sdf = array(
            'material' => $material,
            'material_items' => $materialItems,
            'branch' => $branch
        );
        
        return kernel::single('erpapi_router_request')->set('wms',$material['wms_id'])->goods_addCombination($sdf);
    }

    public function syncMap($id, $wmsId, $operateType) {
        $mapGoods = app::get('console')->model('map_goods')->db_dump(array('id'=>$id));
        $material = app::get('console')->model('foreign_sku')->db_dump(array('inner_sku'=>$mapGoods['shop_product_bn'], 'wms_id'=>$wmsId));

        if(empty($material)) {
            return array('rsp'=>'fail', 'msg' => $mapGoods['shop_product_bn'] . '：尚未分配，请先分配');
        }
        if(empty($material['outer_sku'])) {
            return array('rsp'=>'fail', 'msg' => $mapGoods['shop_product_bn'] . '：尚未同步，请先同步');
        }
        $shop = app::get('ome')->model('shop')->db_dump(array('shop_id' => $mapGoods['shop_id']));
        $sdf = array(
            'map_goods' => $mapGoods,
            'material' => $material,
            'shop' => $shop,
            'operate_type' => $operateType
        );
        return kernel::single('erpapi_router_request')->set('wms',$material['wms_id'])->goods_syncMap($sdf);
    }

    public function syncGet($data) {
        $branch = app::get('ome')->model('branch')->dump($data['branch_id']);
        if(empty($branch)) {
            return array('rsp'=>'fail', 'msg' => '仓库不存在');
        }
        $sdf = array(
            'scroll_id' => $data['scroll_id'],
            'branch' => $branch,
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
        );
        $object = kernel::single('erpapi_router_request')->set('wms',$branch['wms_id']);
        $rs = $object->goods_syncGet($sdf);
        if($rs['rsp'] != 'succ') {
            return $rs;
        }
        $rs['succ'] = $rs['fail'] = 0;
        $materialObj = app::get('material')->model('basic_material');
        $foreignObj = app::get('console')->model('foreign_sku');
        foreach ($rs['data']['items'] as $v) {
            if(empty($v['inner_sku'])) {
                $rs['fail'] ++;
                continue;
            }
            $materialRow = $materialObj->db_dump(array('material_bn'=>$v['inner_sku']), 'bm_id');
            if(!$materialRow) {
                $rs['fail'] ++;
                continue;
            }
            $upData = array(
                'inner_sku' => $v['inner_sku'],
                'inner_product_id' => $materialRow['bm_id'],
                'wms_id' => $branch['wms_id'],
                'outer_sku' => $v['outer_sku']
            );
            $oldRow = $foreignObj->db_dump(array('inner_sku'=>$upData['inner_sku'], 'wms_id'=>$upData['wms_id']), 'fsid');
            if($oldRow) {
                $foreignObj->update(array('outer_sku'=>$upData['outer_sku']), array('fsid'=>$oldRow['fsid']));
            } else {
                $foreignObj->insert($upData);
            }
            $rs['succ'] ++;
        }
        return $rs;
    }

    public function syncPrice($data, $branchId) {
        $branch = app::get('ome')->model('branch')->dump($branchId);
        if(empty($branch)) {
            return array('rsp'=>'fail', 'msg' => '仓库不存在');
        }
        $sdf = array(
            'data' => $data,
            'branch' => $branch,
        );
        $object = kernel::single('erpapi_router_request')->set('wms',$branch['wms_id']);
        $rs = $object->goods_syncPrice($sdf);
        if($rs['rsp'] != 'succ') {
            return $rs;
        }
        $rs['succ'] = 0;
        $rs['error_msg'] = array();
        $skuId = array();
        foreach ($data as $v) {
            $skuId[$v['outer_sku']][] = $v['fsid'];
        }
        
        $foreignObj = app::get('console')->model('foreign_sku');
        foreach ($rs['data']['items'] as $v) {
            if($v['rsp'] != 'succ') {
                $rs['error_msg'][] = $v['outer_sku'] . '失败:' . $v['errorMessage'];
                continue;
            }
            
            //更新WMS仓储采购价格
            $foreignObj->update(array('price'=>$v['price'], 'sync_status'=>'3'), array('fsid'=>$skuId[$v['outer_sku']]));
            
            $rs['succ'] += count($skuId[$v['outer_sku']]);
        }
        return $rs;
    }

    public function checkSynCombine($id,$branchs){
       
        $wms_id = $branchs['wms_id'];
        $basicMaterialCombinationItemsObj = app::get('material')->model('basic_material_combination_items');
        $seMiBasicMInfos = $basicMaterialCombinationItemsObj->getList('pbm_id,bm_id,material_name,material_bn,material_num',array('pbm_id'=>$id), 0, -1);
        
        if(empty($seMiBasicMInfos)) return true;
        $productBn = array();
        foreach($seMiBasicMInfos as $k => $v){
            
            $productBn[] = $v['material_bn'];
        }

        $productMaterial = app::get('console')->model('foreign_sku')->getList('*', array('inner_sku'=>$productBn,'wms_id'=>$wms_id,'sync_status'=>array('0','1','2')));
        
        $product_ids = array_column($productMaterial, 'inner_product_id');
        if($product_ids){

            $product_sdf = kernel::single('console_goodssync')->getProductSdf($product_ids);

            kernel::single('console_goodssync')->syncProduct_notifydata($wms_id,$product_sdf,$branchs['branch_bn']);
        }


    }
}
