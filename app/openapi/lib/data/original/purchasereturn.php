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

class openapi_data_original_purchasereturn{

        
    public function getList($filter,$offset=0,$limit=100){
        
    	$po_mdl = app::get('purchase')->model('returned_purchase');
    	$poItems_mdl = app::get('purchase')->model('returned_purchase_items');
    	$supplier_mod = app::get('purchase')->model('supplier');
    	$branch_mod = app::get('ome')->model('branch');
    	if(isset($filter['supplier'])){
	    	$supplierName = $filter['supplier'];
	    	$supplier_id = $supplier_mod->getList('supplier_id',array('name'=>$supplierName));
	    	$supplier_id = $supplier_id[0]['supplier_id'];
	    	unset($filter['supplier']);
	    	$filter['supplier_id'] = $supplier_id;
    	}
        if (isset($filter['branch'])) {
            $_branch = $branch_mod->getList('branch_id',array('name'=>$filter['branch']));
            $filter['branch_id'] = $_branch ? $_branch['branch_id'] : '';
            unset($filter['branch']);
        }
        if($filter['last_modify_start_time']){
            $filter['last_modify|bthan'] = strtotime($filter['last_modify_start_time']);
            unset($filter['last_modify_start_time']);
        }
        if($filter['last_modify_end_time']) {
            $filter['last_modify|sthan'] = strtotime($filter['last_modify_end_time']);
            unset($filter['last_modify_end_time']);
        }
    	foreach ($filter as $k=>$filt) {
            if(empty($filt)) unset($filter[$k]);
        }
    
        $count = $po_mdl->count($filter);
        if (!$count) {
            return ['lists' => [], 'count' => 0];
        }
        
    	$data = $po_mdl->getList('rp_id,name as po_name,rp_bn,supplier_id as supplier,returned_time as po_time,amount,operator,branch_id as branch,
    							return_status,delivery_cost as logistic_fee,
    							product_cost as item_cost,po_bn,memo',
    							$filter,$offset,$limit);
        $result = ['lists' => [], 'count' => $count];
        $formatFilter=kernel::single('openapi_format_abstract');
    	foreach ($data as $k=>$v){
    		$supplier_row = $supplier_mod->getList('bn,name',array('supplier_id'=>$v['supplier']));
    		$supplier_bn = $supplier_row[0]['bn'];
            $supplier_name = $supplier_row[0]['name'];
    		$branch = $branch_mod->getList('name,branch_bn',array('branch_id'=>$v['branch']));
    		$branch_name = $branch[0]['name'];
    		$branch_bn = $branch[0]['branch_bn'];
    		
    		$v['supplier'] = $formatFilter->charFilter($supplier_name);
            $v['supplier_bn'] = $formatFilter->charFilter($supplier_bn);
    		$v['branch'] = $formatFilter->charFilter($branch_name);
    		$v['branch_bn'] = $formatFilter->charFilter($branch_bn);
            $memo = unserialize($v['memo']);
            if ($memo && is_array($memo)) {
                $memo = implode('、', array_column($memo, 'op_content'));
            }
            $v['memo'] = str_replace(PHP_EOL, '', $memo);
    		$itemInfos = $poItems_mdl->getList('bn as product_bn,name as product_name, price,num,out_num', array('rp_id'=>$v['rp_id']));
    		unset($v['rp_id']);
    		if(!empty($itemInfos)){
	    		foreach ($itemInfos as $itemInfo){
                    $itemInfo['product_bn']= $formatFilter->charFilter($itemInfo['product_bn']);
                    $itemInfo['product_name']= $formatFilter->charFilter($itemInfo['product_name']);
                    $v['items'][] = $itemInfo;
	    		}
                $result['lists'][] = $v;
    		}
    	}
    	return $result;
    }
    //通过供应商编码获取供应商的id
    private function _getSupplierByBn($supplier_bn) {
        $supplierModel = app::get('purchase')->model('supplier');
        $supplier = $supplierModel->dump(array( 'bn' => $supplier_bn),'supplier_id');
        return $supplier;
    }
    //通过仓库编码获取仓库的id
    private function _getBranchByBn($branch_bn) {
        $branchModel = app::get('ome')->model('branch');
        $branch = $branchModel->dump(array( 'branch_bn' => $branch_bn),'branch_id');
        return $branch;
    }
    //通过货号和仓库id判断这个仓库里面是否存在这个货品和获取货品的id
    private function _getProductByBn($bn,$branch_id) {
        
        $basicMStockFreezeLib  = kernel::single('material_basic_material_stock_freeze');
        $basicMaterialObj = app::get('material')->model('basic_material');
        $branchModel = app::get('ome')->model('branch_product');
        
        $product = $basicMaterialObj->dump(array( 'material_bn' => $bn),'bm_id');
        $bproduct = $branchModel->dump(array('branch_id'=> $branch_id,'product_id'=>$product['bm_id']),'product_id,store,store_freeze');
        
        //根据仓库ID、基础物料ID获取该物料仓库级的预占
        $bproduct['store_freeze']    = $basicMStockFreezeLib->getBranchFreeze($product['bm_id'], $branch_id);
        
        return $bproduct;
    }
    public function add($data){
        
        $basicMaterialLib    = kernel::single('material_basic_material');
        
        $result = array('rsp'=>'succ');
        $name = $data['name'];
        $supplier_bn = $data['supplier_bn'];
        $branch_bn = $data['branch_bn'];
        $operator = $data['operator'];
        $items = json_decode($data['items'],true);
        $delivery_cost = $data['delivery_cost']?$data['delivery_cost']:0;
        $logi_no = $data['logi_no'];
        $emergency = $data['emergency']=='是'?true:false;
        $memo = $data['memo'];
        if ($name == '' || $branch_bn == '' || $supplier_bn == '' || $operator == '' || $items == '') {
            $result['rsp'] = 'fail';
            $result['msg'] = '必填字段不可为空!';
            return $result;
        }
        //判断仓库是否存在
        if(!$branch = $this->_getBranchByBn($branch_bn)){
            $result['rsp'] = 'fail';
            $result['msg'] = '仓库编码不存在!';
            return $result;
        }
        $branch_id = $branch['branch_id'];
        //判断供应商是否存在
        if (!$supplier = $this->_getSupplierByBn($supplier_bn)) {            
            $result['rsp'] = 'fail';
            $result['msg'] = '供应商编码不存在!';
            return $result;           
        }
        $supplier_id = $supplier['supplier_id'];
        if(!is_array($items)){
            $result['rsp'] = 'fail';
            $result['msg'] = 'items参数必须为数组!';
            return $result; 
        }
        $products = array();
        foreach ($items as $item) {
            if(!$item['bn'] || !$item['nums']){
                $result['rsp'] = 'fail';
                $result['msg'] = '必填字段不可为空!';
                return $result;
            }
            if(!$product = $this->_getProductByBn($item['bn'],$branch_id)){
                $result['rsp'] = 'fail';
                $result['msg'] = '货号'.$item['bn'].'在'.$branch['name'].'仓库不存在!';
                return $result;
            }
            $products[$item['bn']] = $product;
            if (!is_numeric($item['nums']) || $item['nums'] < 1){
                $result['rsp'] = 'fail';
                $result['msg'] = '货号'.$item['bn'].'的退货数量必须为数字且大于0!';
                return $result;
            }         
            if($item['nums'] > ($product['store']-$product['store_freeze'])){
                $result['rsp'] = 'fail';
                $result['msg'] = '货号'.$item['bn'].'的退货数量不可大于可用库存数量!';
                return $result;
            }

            $item['price'] = $item['price'] ?: 0;

            // if (!is_numeric($item['price']) || $item['price'] < 0){
            //     $result['rsp'] = 'fail';
            //     $result['msg'] = '货号'.$item['bn'].'的单价必须为数字且大于0!';
            //     return $result;
            // }
            $product_cost += $item['price']*$item['nums'];
            unset($item);
        }
        $oPurchase = app::get('purchase')->model('returned_purchase');
        $rp_bn = $oPurchase->gen_id();
        $sdf = array();
        $sdf['rp_bn'] = $data['rp_bn'] ?:  $rp_bn;
        $sdf['name'] = $name;
        $sdf['supplier_id'] = $supplier_id;
        $sdf['operator'] = $operator;
        $sdf['emergency'] = $emergency;
        $sdf['branch_id'] = $branch_id;
        $sdf['amount'] = $product_cost+$delivery_cost;
        $sdf['product_cost'] = $product_cost;
        $sdf['delivery_cost'] = $delivery_cost;
        $sdf['logi_no'] = $logi_no;
        $sdf['returned_time'] = time();
        $sdf['rp_type'] = 'eo';
        $sdf['po_type'] = 'cash';
        if ($memo){            
            $newmemo = array();
            $newmemo[] = array('op_time'=>date('Y-m-d H:i',time()), 'op_content'=>$memo);
        }
        $sdf['memo'] = serialize($newmemo);
        $rs = $oPurchase->save($sdf);
        if ($rs)
        {
            $rp_id = $sdf['rp_id'];
            $oPurchase_items = app::get('purchase')->model("returned_purchase_items");
            
            foreach ($items as $item){
                //插入采购退货单详情
                $product = $products[$item['bn']];
                $p    = $basicMaterialLib->getBasicMaterialExt($product['product_id']);
                
                $row = array();
                $row['rp_id'] = $rp_id;
                $row['product_id'] = $p['bm_id'];
                $row['num'] = $item['nums'];
                $row['price'] = sprintf('%.2f',$item['price']);
                $row['bn'] = $p['material_bn'];
                $row['barcode'] = $p['barcode'];
                $row['name'] = $p['material_name'];
                $row['spec_info'] = $p['specifications'];
                $oPurchase_items->save($row);
                unset($item,$p);                
            }                                           
        }
        return $result;        
    }

    /**
     * 取消采购退货单
     * @param array $data 包含rp_bn和memo的数据
     * @return array 返回结果
     */
    public function cancel($data) {
        $rp_bn = $data['rp_bn'];
        $memo = isset($data['memo']) ? $data['memo'] : '';
        
        if (empty($rp_bn)) {
            return array('rsp'=>'fail','msg'=>'退货单编号不能为空');
        }
        
        // 通过退货单编号查找退货单ID
        $po_mdl = app::get('purchase')->model('returned_purchase');
        $rp_info = $po_mdl->dump(array('rp_bn' => $rp_bn), 'rp_id');
        
        if (empty($rp_info['rp_id'])) {
            return array('rsp'=>'fail','msg'=>'退货单编号不存在');
        }
        
        $rp_id = $rp_info['rp_id'];
        
        // OpenAPI调用时也需要推送WMS
        $result = kernel::single('console_returned_purchase')->cancel($rp_id, $memo, true);
        
        if ($result['rsp'] == 'fail') {
            return array('rsp'=>'fail','msg'=>$result['error_msg']);
        }
        
        return array('rsp'=>'succ','msg'=>$result['msg']);
    }   
}
