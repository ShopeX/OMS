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

class openapi_data_original_po{

    public function add($data){
        $result = array('rsp'=>'succ');

        $supplier_mdl = app::get('purchase')->model('supplier');
        $branch_mdl = app::get('ome')->model('branch');
        $po_mdl = app::get('purchase')->model('po');
        $formatFilter=kernel::single('openapi_format_abstract');
        $po_type = array('1'=>'cash','2'=>'credit');

        // 供应商编号
        if ($data['vendor_bn']) {
            $supplier= $supplier_mdl->dump(array('bn'=>$data['vendor_bn']), 'supplier_id');
        }

        if (!$supplier && $data['vendor']) {
            $supplier= $supplier_mdl->dump(array('name'=>$data['vendor']), 'supplier_id');
        }

        $_branch = $branch_mdl->getList('branch_id',array('branch_bn'=>$data['branch_bn']));

        $sdf['supplier_id'] = $supplier['supplier_id'];
        $sdf['operator'] = 'system';
        $sdf['po_type'] = $po_type[$data['type']];
        $sdf['name'] = $formatFilter->charFilter($data['name']);
        $sdf['branch_id'] = $_branch[0]['branch_id'];
        $sdf['arrive_time'] = $data['arrive_time'] ?: 0;
        $sdf['deposit'] = $data['deposit_balance'];
        $sdf['deposit_balance'] = $data['deposit_balance'];
        $sdf['delivery_cost'] = $data['delivery_cost'] ?: 0;
        $sdf['operator'] = $data['operator'];
        $sdf['memo'] = $formatFilter->charFilter($data['memo']);
        $sdf['po_bn'] = $data['po_bn'];
        $sdf['items'] = $data['items'];

        $rs = $po_mdl->savePo($sdf);
        if($rs['status'] == 'success'){
            $result['data'] = $rs['data'];

            // 自动审核
            if ($data['confirm'] == 'Y' && $sdf['po_id']) {
                kernel::single('console_po')->do_check($sdf['po_id']);
            }

            // 写入属性表
            if (!empty($data['props']) && $sdf['po_id']) {
                // 解析props（JSON字符串转数组）
                $props = json_decode($data['props'], true);
                if ($props && is_array($props)) {
                    $poPropsModel = app::get('purchase')->model('po_props');
                    foreach ($props as $propsCol => $propsValue) {
                        if (!empty($propsValue)) {
                            $inData = array(
                                'po_id' => $sdf['po_id'],
                                'props_col' => $propsCol,
                                'props_value' => $propsValue,
                            );
                            $poPropsModel->insert($inData);
                        }
                    }
                }
            }
            
            // 写入明细属性表
            if (!empty($data['items']) && is_array($data['items']) && $sdf['po_id']) {
                $poItemsModel = app::get('purchase')->model('po_items');
                $poItemsPropsModel = app::get('purchase')->model('po_items_props');
                
                foreach ($data['items'] as $item) {
                    // 如果item中有props字段，保存到po_items_props表
                    if (isset($item['props']) && !empty($item['props'])) {
                        // 通过po_id和bn查询item_id
                        $itemInfo = $poItemsModel->db_dump(array(
                            'po_id' => $sdf['po_id'],
                            'bn' => $item['bn']
                        ), 'item_id');
                        
                        if ($itemInfo && $itemInfo['item_id']) {
                            // props可能是JSON字符串或数组
                            $itemProps = is_string($item['props']) ? json_decode($item['props'], true) : $item['props'];
                            
                            if ($itemProps && is_array($itemProps)) {
                                foreach ($itemProps as $propsCol => $propsValue) {
                                    if (!empty($propsValue)) {
                                        $inData = array(
                                            'item_id' => $itemInfo['item_id'],
                                            'props_col' => $propsCol,
                                            'props_value' => $propsValue,
                                        );
                                        $poItemsPropsModel->insert($inData);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }else{
            $result['rsp'] = 'fail';
            $result['msg'] = $rs['msg'];
        }

        return $result;
    }
    
    public function getList($filter,$offset=0,$limit=100){
    	$po_mdl = app::get('purchase')->model('po');
    	$poItems_mdl = app::get('purchase')->model('po_items');
        $taoguanIsoMdl = app::get('taoguaniostockorder')->model('iso');
        $taoguanIsoItemsMdl = app::get('taoguaniostockorder')->model('iso_items');
    	$supplier_mod = app::get('purchase')->model('supplier');
    	$branch_mod = app::get('ome')->model('branch');
    	$isostockMdl = app::get('ome')->model('iostock');
        $formatFilter=kernel::single('openapi_format_abstract');
    	if(isset($filter['supplier'])){
            $supplierName = $filter['supplier'];
            $supplier_id = $supplier_mod->getList('supplier_id',array('name'=>$supplierName));
            $supplier_id = $supplier_id[0]['supplier_id'];
            unset($filter['supplier']);
            $filter['supplier_id'] = $supplier_id;
    	}

        $count = $po_mdl->count($filter);
        
        if (!$count) {
            return ['lists' => [], 'count' => 0];
        }

    	$data = $po_mdl->getList('po_id,name as po_name,po_bn,supplier_id as supplier,purchase_time as po_time,amount,operator,branch_id as branch,
    							po_status,statement as statement_status,check_status,eo_status,delivery_cost as logistic_fee,
    							product_cost as item_cost,deposit,deposit_balance,po_species,accos_po_bn',
    							$filter,($offset-1)*$limit,$limit);

        $result = ['lists' => [], 'count' => $count];

        // 批量查询属性表数据
        $poIds = array_column($data, 'po_id');
        $poPropsData = [];
        if (!empty($poIds)) {
            $poPropsModel = app::get('purchase')->model('po_props');
            $poPropsList = $poPropsModel->getList('*', array(
                'po_id' => $poIds
            ));
            
            // 按po_id分组
            foreach ($poPropsList as $prop) {
                if (!isset($poPropsData[$prop['po_id']])) {
                    $poPropsData[$prop['po_id']] = array();
                }
                $poPropsData[$prop['po_id']][$prop['props_col']] = $prop['props_value'];
            }
        }

        foreach ($data as $k => $v){
            $v['po_time'] = date('Y-m-d H:i:s',$v['po_time']);
            $supplier_row = $supplier_mod->getList('bn,name',array('supplier_id'=>$v['supplier']));
            $supplier_bn = $supplier_row[0]['bn'];
            $supplier_name = $supplier_row[0]['name'];
            $branch = $branch_mod->getList('name,branch_bn',array('branch_id'=>$v['branch']));
            $branch_name = $branch[0]['name'];
            $branch_bn = $branch[0]['branch_bn'];

            $v['supplier'] = $supplier_name;
            $v['supplier_bn'] = $supplier_bn;
            $v['branch'] = $branch_name;
            $v['branch_bn'] = $branch_bn;
            
            //eo list
            $isoList = $taoguanIsoMdl->getList('iso_id,name,iso_bn,memo,branch_id,product_cost as cost,arrival_no,create_time', array('original_id' => $v['po_id'],'original_bn' => $v['po_bn']));
            if (!empty($isoList)) {
                foreach ($isoList as $key => $value) {
                    if ($isoList[$key]['memo']) {
                        
                        $memo = @unserialize($isoList[$key]['memo']);
                        if ($memo){
                            $isoList[$key]['memo'] = str_replace(PHP_EOL, '', implode('、', array_column($memo, 'op_content')));
                        }
                        
                    }
                    $isoItemsList = $taoguanIsoItemsMdl->getList('product_id,product_name,bn as product_bn,price,nums,normal_num,defective_num',
                        array('iso_id' => $value['iso_id']));
                    
                    $branchLib = kernel::single('ome_branch');
                    $isoList[$key]['branch_bn'] = $branchLib->getBranchBnById($isoList[$key]['branch_id']);
                    
                    unset($isoList[$key]['branch_id'],$isoList[$key]['iso_id']);
                    $isoList[$key]['items'] = $isoItemsList;
                    $isoList[$key]['arrival_no'] = empty($isoList[$key]['arrival_no']) ? '' : $isoList[$key]['arrival_no'];
                    $isoList[$key]['create_time'] = date('Y-m-d H:i:s', $value['create_time']);
                }
            }
            $v['eo_list'] = $isoList;
            $itemInfos = $poItems_mdl->getList('item_id,bn as product_bn,name as product_name, price,num, in_num, defective_num as bad_num,status', array('po_id'=>$v['po_id']));
            $v['po_bn']= $formatFilter->charFilter($v['po_bn']);
            if(!empty($itemInfos)){
                // 批量查询明细属性表数据
                $itemIds = array_column($itemInfos, 'item_id');
                $poItemsPropsData = [];
                if (!empty($itemIds)) {
                    $poItemsPropsModel = app::get('purchase')->model('po_items_props');
                    $poItemsPropsList = $poItemsPropsModel->getList('*', array(
                        'item_id' => $itemIds
                    ));
                    
                    // 按item_id分组
                    foreach ($poItemsPropsList as $prop) {
                        if (!isset($poItemsPropsData[$prop['item_id']])) {
                            $poItemsPropsData[$prop['item_id']] = array();
                        }
                        $poItemsPropsData[$prop['item_id']][$prop['props_col']] = $prop['props_value'];
                    }
                }
                
                foreach ($itemInfos as $key => $itemInfo){
                    $itemInfo['product_bn']= $formatFilter->charFilter($itemInfo['product_bn']);
                    $itemInfo['product_name']= $formatFilter->charFilter($itemInfo['product_name']);
                    // 添加明细扩展属性
                    $itemInfo['props'] = isset($poItemsPropsData[$itemInfo['item_id']]) ? $poItemsPropsData[$itemInfo['item_id']] : array();
                    // 移除item_id，不输出
                    unset($itemInfo['item_id']);
                    $itemInfos[$key] = $itemInfo;
                }
                $v['items']= $itemInfos;
                $v['props'] = isset($poPropsData[$v['po_id']]) ? $poPropsData[$v['po_id']] : array();
                $result['lists'][] =$v;
            }
    	}
    	return $result;
    }

    public function cancel($data)
    {
        $result = array('rsp' => 'succ');
        
        $po_mdl = app::get('purchase')->model('po');
        $formatFilter = kernel::single('openapi_format_abstract');
        
        // 获取采购单编号
        $po_bn = trim($data['po_bn']);
        if (empty($po_bn)) {
            $result['rsp'] = 'fail';
            $result['msg'] = '采购单编号不能为空';
            return $result;
        }
        
        // 查询采购单
        $po_info = $po_mdl->dump(array('po_bn' => $po_bn), 'po_id');
        if (!$po_info) {
            $result['rsp'] = 'fail';
            $result['msg'] = '采购单不存在';
            return $result;
        }
        
        // 操作员
        $operator = $data['operator'] ? $formatFilter->charFilter($data['operator']) : 'system';
        
        // 备注
        $memo = $data['memo'] ? $formatFilter->charFilter($data['memo']) : '';
        
        // 调用采购取消方法
        list($rs, $msg) = kernel::single('console_po')->do_cancel($po_info['po_id'], $operator, $memo);
        
        if (!$rs) {
            $result['rsp'] = 'fail';
            $result['msg'] = $msg;
        } else {
            $result['data'] = array(
                'po_bn' => $po_bn,
                'po_id' => $po_info['po_id']
            );
            $result['msg'] = $msg;
        }
        
        return $result;
    }
    
}
