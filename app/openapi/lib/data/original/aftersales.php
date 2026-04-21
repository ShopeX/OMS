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


class openapi_data_original_aftersales
{
    public function getList($filter,$start_time,$end_time,$offset=0,$limit=100)
    {
        $aftersaleMdl = app::get('sales')->model('aftersale');
        $count = $aftersaleMdl->count($filter);
        if(intval($count) >0)
        {
            $shopObj = app::get('ome')->model('shop');
            $branchObj = app::get('ome')->model('branch');
            $orderObj = app::get('ome')->model('orders');
            $memberObj = app::get('ome')->model('members');
            $returnProductObj = app::get('ome')->model('return_product');
            $reshipObj = app::get('ome')->model('reship');
            $refundApplyObj = app::get('ome')->model('refund_apply');
            $opObj = app::get('desktop')->model('users');
            


            $branchInfos = array();
            $branch_arr = $branchObj->getList('branch_id,name,branch_bn', array(), 0, -1);
            foreach ($branch_arr as $k => $branch){
                $branchInfos[$branch['branch_id']]['name'] = $branch['name'];
                $branchInfos[$branch['branch_id']]['branch_bn'] = $branch['branch_bn'];
            }
            $aftersaleLists = $aftersaleMdl->getList('*', $filter, $offset, $limit,'aftersale_id ASC');
            if(empty($aftersaleLists)){
                return array(
                    'lists' => array(),
                    'count' => $count,
                );
            }

            $shopInfos = $shopObj->getList('shop_id,shop_bn,name,delivery_mode', [
                'shop_id' => array_column($aftersaleLists, 'shop_id'),
            ]);
            $shopInfos = array_column($shopInfos, null, 'shop_id');


            $aftersaleIds = array(-1);
            $orderIds = array(-1);
            $memberIds = array(-1);
            $returnIds = array(-1);
            $reshipIds = array(-1);
            $refundApplyIds = array(-1);
            $opIds = array(-1);
            foreach ($aftersaleLists as $k => $aftersale){
                $aftersaleIds[] = $aftersale['aftersale_id'];

                if(intval($aftersale['order_id'])>0 && !in_array($aftersale['order_id'],$orderIds)){
                    $orderIds[] =  $aftersale['order_id'];
                }

                if(intval($aftersale['member_id'])>0 && !in_array($aftersale['member_id'],$memberIds)){
                    $memberIds[] = $aftersale['member_id'];
                }

                if(intval($aftersale['return_id'])>0 && !in_array($aftersale['return_id'],$returnIds)){
                    $returnIds[] = $aftersale['return_id'];
                }

                if(intval($aftersale['reship_id'])>0 && !in_array($aftersale['reship_id'],$reshipIds)){
                    $reshipIds[] = $aftersale['reship_id'];
                }

                if(intval($aftersale['return_apply_id'])>0 && !in_array($aftersale['return_apply_id'],$refundApplyIds)){
                    $refundApplyIds[] = $aftersale['return_apply_id'];
                }

                if(intval($aftersale['check_op_id'])>0 && !in_array($aftersale['check_op_id'],$opIds)){
                    $opIds[] = $aftersale['check_op_id'];
                }

                if(intval($aftersale['op_id'])>0 && !in_array($aftersale['op_id'],$opIds)){
                    $opIds[] = $aftersale['op_id'];
                }

                if(intval($aftersale['refund_op_id'])>0 && !in_array($aftersale['refund_op_id'],$opIds)){
                    $opIds[] = $aftersale['refund_op_id'];
                }
            }
            
            $order_arr = $orderObj->getList('order_id,order_bn,paytime,ship_area,ship_addr,relate_order_bn,order_type',array('order_id'=>$orderIds),0,-1);
            foreach ($order_arr as $k => $order){
                $orderInfos[$order['order_id']] = $order;
            }
            $orderObjs = app::get('ome')->model('order_objects')->getList('obj_id,bn', ['order_id'=>$orderIds]);
            $orderObjs = array_column($orderObjs, 'bn', 'obj_id');
            $orderItems = app::get('ome')->model('order_items')->getList('item_id,obj_id,bn', ['order_id'=>$orderIds]);
            $itemIdBn = array();
            foreach ($orderItems as $k => $item){
                $itemIdBn[$item['item_id']] = $orderObjs[$item['obj_id']];
            }

            $sales_arr = app::get('ome')->model('sales')->getList('sale_id,order_id,sale_bn,ship_time',array('order_id'=>$orderIds),0,-1);
            $sales_arr = array_column($sales_arr, null, 'order_id');

            $member_arr = $memberObj->getList('member_id,name',array('member_id'=>$memberIds),0,-1);
            foreach ($member_arr as $k => $member){
                $memberInfos[$member['member_id']] = $member['name'];
            }
            
            $return_arr = $returnProductObj->getList('return_id,return_bn',array('return_id'=>$returnIds),0,-1);
            foreach ($return_arr as $k => $return){
                $returnInfos[$return['return_id']] = $return['return_bn'];
            }

            $reship_arr = $reshipObj->getList('reship_id,reship_bn,return_logi_name,return_logi_no,flag_type,ship_area,ship_addr,ship_zip,change_order_id',array('reship_id'=>$reshipIds),0,-1);
            $changeOrder = $orderObj->getList('order_id,order_bn', ['order_id'=>array_column($reship_arr, 'change_order_id')]);
            $changeOrder = array_column($changeOrder, 'order_bn', 'order_id');
            foreach ($reship_arr as $k => $reship){
                $return_category = 'customer';//客退
                if ($reship['flag_type'] & ome_reship_const::__LANJIE_RUKU) {
                    $return_category = 'Intercept';//拦截退
                }
                $reship['return_category'] = $return_category;
                $reship['change_order_bn'] = $changeOrder[$reship['change_order_id']];
                $reshipInfos[$reship['reship_id']] = $reship;
            }
            $refundApply_arr = $refundApplyObj->getList('apply_id,refund_apply_bn',array('apply_id'=>$refundApplyIds),0,-1);
            foreach ($refundApply_arr as $k => $refundApply){
                $refundApplyInfos[$refundApply['apply_id']] = $refundApply['refund_apply_bn'];
            }

            $op_arr = $opObj->getList('user_id,name',array('user_id'=>$opIds),0,-1);
            foreach ($op_arr as $k => $op){
                $opInfos[$op['user_id']] = $op['name'];
            }
            
            $useLifeLog = app::get('console')->model('useful_life_log')->getList('original_id,product_id,num,bn,normal_defective,product_time,expire_time,purchase_code,produce_code', array('sourcetb'=>'reship', 'original_id'=>$reshipIds));
            $useLifeLog_arr = array();
            foreach ($useLifeLog as $k => $useLife){
                if($useLifeLog_arr[$useLife['original_id']][$useLife['product_id']][$useLife['normal_defective']][$useLife['purchase_code']]) {
                    $useLifeLog_arr[$useLife['original_id']][$useLife['product_id']][$useLife['normal_defective']][$useLife['purchase_code']]['num'] += $useLife['num'];
                    continue;
                }
                $useLife['product_time'] = $useLife['product_time'] ? date('Y-m-d H:i:s',$useLife['product_time']) : '';
                $useLife['expire_time'] = $useLife['expire_time'] ? date('Y-m-d H:i:s',$useLife['expire_time']) : '';
                $useLifeLog_arr[$useLife['original_id']][$useLife['product_id']][$useLife['normal_defective']][$useLife['purchase_code']] = $useLife;
            }
            $aftersaleInfos = array();
            foreach ($aftersaleLists as $k => $aftersale){
                $aftersaleInfos[$aftersale['aftersale_id']] = $aftersale;
                $aftersaleInfos[$aftersale['aftersale_id']]['order_bn'] = $orderInfos[$aftersale['order_id']]['order_bn'];
                $aftersaleInfos[$aftersale['aftersale_id']]['relate_order_bn'] = $orderInfos[$aftersale['order_id']]['relate_order_bn'];
                $aftersaleInfos[$aftersale['aftersale_id']]['order_type'] = $orderObj->schema['columns']['order_type']['type'][$orderInfos[$aftersale['order_id']]['order_type']];
                $aftersaleInfos[$aftersale['aftersale_id']]['order_pay_time'] = $orderInfos[$aftersale['order_id']]['paytime'];
                $aftersaleInfos[$aftersale['aftersale_id']]['sale_bn'] = $sales_arr[$aftersale['order_id']]['sale_bn'];
                $aftersaleInfos[$aftersale['aftersale_id']]['ship_time'] = $sales_arr[$aftersale['order_id']]['ship_time'];
                $aftersaleInfos[$aftersale['aftersale_id']]['shop_bn'] = $shopInfos[$aftersale['shop_id']]['shop_bn'];
                $aftersaleInfos[$aftersale['aftersale_id']]['shop_name'] = $shopInfos[$aftersale['shop_id']]['name'];
                $aftersaleInfos[$aftersale['aftersale_id']]['delivery_mode'] = $shopInfos[$aftersale['shop_id']]['delivery_mode'];

                $aftersaleInfos[$aftersale['aftersale_id']]['member_name'] = $memberInfos[$aftersale['member_id']];
                $aftersaleInfos[$aftersale['aftersale_id']]['return_bn'] = $returnInfos[$aftersale['return_id']];
                $aftersaleInfos[$aftersale['aftersale_id']]['reship_bn'] = $reshipInfos[$aftersale['reship_id']]['reship_bn'];
                $aftersaleInfos[$aftersale['aftersale_id']]['change_order_bn'] = $reshipInfos[$aftersale['reship_id']]['change_order_bn'];
                $aftersaleInfos[$aftersale['aftersale_id']]['return_logi_no'] = $reshipInfos[$aftersale['reship_id']]['return_logi_no'];
                $aftersaleInfos[$aftersale['aftersale_id']]['return_logi_name'] = $reshipInfos[$aftersale['reship_id']]['return_logi_name'];
                $area = $reshipInfos[$aftersale['reship_id']]['ship_area'] ? : $orderInfos[$aftersale['order_id']]['ship_area'];
                kernel::single('ome_func')->split_area($area);
                $aftersaleInfos[$aftersale['aftersale_id']]['ship_province'] = $area[0];
                $aftersaleInfos[$aftersale['aftersale_id']]['ship_city'] = $area[1];
                $aftersaleInfos[$aftersale['aftersale_id']]['ship_district'] = $area[2];
                $aftersaleInfos[$aftersale['aftersale_id']]['ship_addr'] = $reshipInfos[$aftersale['reship_id']]['ship_addr'] ? : $orderInfos[$aftersale['order_id']]['ship_addr'];
                $aftersaleInfos[$aftersale['aftersale_id']]['ship_zip'] = $reshipInfos[$aftersale['reship_id']]['ship_zip'];
                $aftersaleInfos[$aftersale['aftersale_id']]['refund_apply_bn'] = $aftersale['return_apply_bn'];
                $aftersaleInfos[$aftersale['aftersale_id']]['aftersale_type'] = $this->getType($aftersale['return_type']);
                $aftersaleInfos[$aftersale['aftersale_id']]['check_op_name'] = $opInfos[$aftersale['check_op_id']];
                $aftersaleInfos[$aftersale['aftersale_id']]['op_name'] = $opInfos[$aftersale['op_id']];
                $aftersaleInfos[$aftersale['aftersale_id']]['refund_op_name'] = $opInfos[$aftersale['refund_op_id']];
                $aftersaleInfos[$aftersale['aftersale_id']]['return_category'] = $reshipInfos[$aftersale['reship_id']]['return_category'];
                
                $aftersaleInfos[$aftersale['aftersale_id']]['aftersale_items'] = array();
            }
            $aftersale_items = app::get('sales')->model('aftersale_items')->getList('*', [
                'aftersale_id' => array_column($aftersaleLists, 'aftersale_id'),
            ]);
            
            //基础物料信息
            $productIds = array_unique(array_column($aftersale_items, 'product_id'));
            $basicMaterialList = array();
            if($productIds){
                $basicMaterialList = $this->_getBasicMaterial($productIds);
            }
            $propsRows = app::get('sales')->model('aftersale_items_props')->getList('*', ['item_detail_id'=>array_column($aftersale_items, 'item_id')]);
            $propsItems = [];
            foreach ($propsRows as $k => $propsRow){
                $propsItems[$propsRow['item_detail_id']][] = $propsRow;
            }
            //items
            foreach ($aftersale_items as $k =>$aftersale_item)
            {
                $product_id = intval($aftersale_item['product_id']);
                $aftersale_item['reship_id'] = $aftersaleInfos[$aftersale_item['aftersale_id']]['reship_id'];
                //check
                if($aftersale_item['return_type'] == 'change') continue;
                $aftersale_item['batchs'] = $this->_getBatchs($useLifeLog_arr, $aftersale_item);
                $aftersale_item['props'] = $this->_getProps($propsItems[$aftersale_item['item_id']]);
                //基础物料信息
                $materialInfo = $basicMaterialList[$product_id];
                
                //开票税率
                $aftersale_item['cost_tax'] = 0;
                if($materialInfo['tax_rate'] > 0){
                    $aftersale_item['cost_tax'] = $materialInfo['tax_rate'] / 100;
                }
                
                //基础物料信息
                $aftersale_item['brand_code'] = $materialInfo['brand_code']; //物料品牌
                $aftersale_item['cat_name'] = $materialInfo['cat_name']; //物料分类
                $aftersale_item['goods_type'] = $materialInfo['type']; //物料属性
                
                $aftersale_item['barcode'] = $materialInfo['barcode']; //条形码
                $aftersale_item['retail_price'] = $materialInfo['retail_price']; //物料销售价
    
                $addon                             = $aftersale_item['addon'] ? json_decode($aftersale_item['addon'], true) : [];
                $aftersale_item['shop_goods_id']   = isset($addon['shop_goods_id']) ? $addon['shop_goods_id'] : '';
                $aftersale_item['shop_product_id'] = isset($addon['shop_product_id']) ? $addon['shop_product_id'] : '';
                $aftersale_item['sales_material_bn'] = $itemIdBn[$aftersale_item['order_item_id']] ? : '';
                //merge
                if(isset($aftersaleInfos[$aftersale_item['aftersale_id']])){
                    $aftersale_item['branch_name'] = $branchInfos[$aftersale_item['branch_id']]['name'];
                    $aftersale_item['branch_bn'] = $branchInfos[$aftersale_item['branch_id']]['branch_bn'];
                    $aftersaleInfos[$aftersale_item['aftersale_id']]['aftersale_items'] = array_merge($aftersaleInfos[$aftersale_item['aftersale_id']]['aftersale_items'],array($aftersale_item));
                }
            }

            return array(
                'lists' => $aftersaleInfos,
                'count' => $count,
            );
        }else{
            return array(
                'lists' => array(),
                'count' => 0,
            );
        }
    }

    protected function _getProps($propsItems)
    {
        $returnProps = [];
        if($propsItems) {
            foreach ($propsItems as $propsItem) {
                $returnProps[$propsItem['props_col']] = $propsItem['props_value'];
            }
        }
        return $returnProps;
    }

    private function getType($key){
        $types = array(
            'return' =>'退货',
            'change' => '换货',
            'refuse' => '拒绝收货',
            'refund' => '退款',
        );
        return $types[$key];
    }
    
    protected function _getBatchs(&$useLifeLog_arr, $aftersale_item)
    {
        $product_id = intval($aftersale_item['product_id']);
        $original_id = intval($aftersale_item['reship_id']);
        $batchs = array();
        if($aftersale_item['normal_num'] > 0) {
            if($useLifeLog_arr[$original_id][$product_id]['normal']) {
                $num = $aftersale_item['normal_num'];
                foreach ($useLifeLog_arr[$original_id][$product_id]['normal'] as $ulk => $useLife) {
                    if($num < 1) {
                        break;
                    }
                    if($useLife['num'] >= $num) {
                        $tmpNum = $num;
                    } else {
                        $tmpNum = $useLife['num'];
                    }
                    $num -= $tmpNum;
                    $useLifeLog_arr[$original_id][$product_id]['normal'][$ulk]['num'] -= $tmpNum;
                    if($useLifeLog_arr[$original_id][$product_id]['normal'][$ulk]['num'] < 1) {
                        unset($useLifeLog_arr[$original_id][$product_id]['normal'][$ulk]);
                    }
                    $useLife['num'] = $tmpNum;
                    $batchs[] = $useLife;
                }
            }
        }
        if($aftersale_item['defective_num'] > 0) {
            if($useLifeLog_arr[$original_id][$product_id]['defective']) {
                $num = $aftersale_item['defective_num'];
                foreach ($useLifeLog_arr[$original_id][$product_id]['defective'] as $ulk => $useLife) {
                    if($num < 1) {
                        break;
                    }
                    if($useLife['num'] >= $num) {
                        $tmpNum = $num;
                    } else {
                        $tmpNum = $useLife['num'];
                    }
                    $num -= $tmpNum;
                    $useLifeLog_arr[$original_id][$product_id]['defective'][$ulk]['num'] -= $tmpNum;
                    if($useLifeLog_arr[$original_id][$product_id]['defective'][$ulk]['num'] < 1) {
                        unset($useLifeLog_arr[$original_id][$product_id]['defective'][$ulk]);
                    }
                    $useLife['num'] = $tmpNum;
                    $batchs[] = $useLife;
                }
            }
        }
        return $batchs;
    }
    /**
     * 获取基础物料信息
     *
     * @param array $productIds
     * @return array
     */
    public function _getBasicMaterial($productIds)
    {
        $basicMaterialObj = app::get('material')->model('basic_material');
        $basicMaterialExtObj = app::get('material')->model('basic_material_ext');
        $codebaseMdl = app::get('material')->model('codebase');
        
        $codeBaseLib = kernel::single('material_codebase');
        
        //主信息
        $mainList = $basicMaterialObj->getList('bm_id,type,cat_id,cat_path,tax_rate', array('bm_id'=>$productIds));
        $mainList = array_column($mainList, null, 'bm_id');
        
        //扩展信息
        $extList = $basicMaterialExtObj->getList('bm_id,brand_id,retail_price,specifications', array('bm_id'=>$productIds));
        $extList = array_column($extList, null, 'bm_id');
        
        //条形码
        $codType = $codeBaseLib->getBarcodeType();
        $barcodeList = $codebaseMdl->getList('*', array('bm_id'=>$productIds, 'type'=>$codType));
        $barcodeList = array_column($barcodeList, null, 'bm_id');
        
        //品牌
        $brandList = array();
        $brandIds = array_unique(array_column($extList, 'brand_id'));
        if($brandIds){
            $brandMdl = app::get('ome')->model('brand');
            $brandList = $brandMdl->getList('brand_id,brand_code,brand_name', array('brand_id'=>$brandIds));
            $brandList = array_column($brandList, null, 'brand_id');
        }
        
        //商品分类
        $catList = array();
        $catIds = array_unique(array_column($mainList, 'cat_id'));
        if($catIds){
            $catList = app::get('material')->model('basic_material_cat')->getList('cat_id,cat_path,cat_name,cat_code', array('cat_id'=>$catIds));
            $catList = array_column($catList, null, 'cat_id');
        }
        
        //list
        $basicMaterialList = array();
        foreach((array)$mainList as $key => $val)
        {
            $bm_id = $val['bm_id'];
            $extInfo = $extList[$bm_id];
            
            //merge
            $val = array_merge($val, $extInfo);
            
            $brand_id = $val['brand_id'];
            $cat_id = $val['cat_id'];
            
            //other
            $val['barcode'] = $barcodeList[$bm_id]['code'];
            $val['brand_code'] = $brandList[$brand_id]['brand_code'];
            $val['cat_name'] = $catList[$cat_id]['cat_name'];
            
            $basicMaterialList[$bm_id] = $val;
        }
        
        return $basicMaterialList;
    }
    
    /**
     * 获取JIT售后单
     * @param $filter
     * @param int $offset
     * @param int $limit
     * @return array
     * @date 2024-11-14 10:17 上午
     */
    public function getGxList($filter, $offset = 0, $limit = 100)
    {
        $jitAftersaleMdl = app::get('billcenter')->model('aftersales');
        $count = $jitAftersaleMdl->count($filter);
        
        $jitAftersaleList = $jitAftersaleMdl->getList('*', $filter, $offset, $limit);
        if (!$jitAftersaleList){
            return [
                'lists' => [],
                'count' => $count,
            ];
        }
    
        $jitAftersaleList = array_column($jitAftersaleList, null, 'id');
        $logiCode = array_unique(array_column($jitAftersaleList, 'logi_code'));
        $carrier = app::get('console')->model('carrier')->getList('carrier_code,carrier_name', ['carrier_code'=>$logiCode]);
        $carrier = array_column($carrier, 'carrier_name', 'carrier_code');
        foreach ($jitAftersaleList as $k => $v) {
            $jitAftersaleList[$k]['logi_name'] = $carrier[$v['logi_code']] ?? '';
        }
        $items = app::get('billcenter')->model('aftersales_items')->getList('*', [
            'aftersale_id' => array_column($jitAftersaleList, 'id'),
        ]);
        $basicMaterialExtObj = app::get('material')->model('basic_material_ext');
        $bcExt = $basicMaterialExtObj->getList('bm_id,retail_price', ['bm_id'=>array_unique(array_column($items, 'bm_id'))]);
        $bcExt = array_column($bcExt, null, 'bm_id');
        //items
        foreach ($items as $k => $item) {
            $item['retail_price'] = $bcExt[$item['bm_id']] ? $bcExt[$item['bm_id']]['retail_price'] : 0;
            $jitAftersaleList[$item['aftersale_id']]['items'][] = $item;
        }
        
        return [
            'lists' => array_values($jitAftersaleList),
            'count' => $count,
        ];
    }
}
