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
class console_mdl_material_package extends dbeav_model {

    private $templateColumn = array(
        '加工单名称' => 'mp_name',
        '仓库编码' => 'branch_bn',
        '单据备注' => 'memo',
        'MOVEMENT CODE' => 'movement_code',
        '礼盒物料编码' => 'bm_bn',
        '数量' => 'number',
    );

    public function getTemplateColumn() {
        return array_keys($this->templateColumn);
    }
    public function prepared_import_csv(){
        $this->import_data =[];
        $this->import_data_bm_bn =[];
        $this->ioObj->cacheTime = time();
    }

    public function prepared_import_csv_row($row,&$title,&$tmpl,&$mark,&$newObjFlag,&$msg)
    {
        if(empty($row) || empty(array_filter($row))) return false;
        if( $row[0] == '加工单名称' ){
            $this->nums = 1;
            $title = array_flip($row);
            foreach($this->templateColumn as $k => $val) {
                if(!isset($title[$k])) {
                    $msg['error'] = '请使用正确的模板';
                    return false;
                }
            }
            return false;
        }
        $this->nums++;
        if (empty($title)) {
            $msg['error'] = "请使用正确的模板格式！";
            return false;
        }
        $arrRequired = ['bm_bn','number'];
        if(!$this->import_data['main']) {
            $arrRequired = ['bm_bn','number','mp_name','branch_bn','movement_code'];
        }
        $arrData = array();
        foreach($this->templateColumn as $k => $val) {
            $arrData[$val] = trim($row[$title[$k]]);
            if(in_array($val, $arrRequired) && empty($arrData[$val])) {
                $msg['warning'][] = 'Line '.$this->nums.'：'.$k.'不能都为空！';
                return false;
            }
        }
        if($this->nums > 10000){
            $msg['error'] = "导入的数据量过大，请减少到10000条以下！";
            return false;
        }
        
        if(!$this->import_data['main']) {
            $main = [];
            $main['mp_name'] = $arrData['mp_name'];
            if($main['mp_name']) {
                if($this->db_dump(['mp_name'=>$main['mp_name']], 'id')) {
                    $msg['error'] = '加工单重复';
                    return false;
                }
            }
            $branch = app::get('ome')->model('branch')->db_dump(['branch_bn'=>$arrData['branch_bn']], 'branch_id');
            $main['branch_id'] = $branch['branch_id'];
            $main['memo'] = $arrData['memo'];
            $main['movement_code'] = $arrData['movement_code'];
            $this->import_data['main'] = $main;
        }
        if(in_array($arrData['bm_bn'], $this->import_data_bm_bn)) {
            $msg['error'] = 'Line '.$this->nums.'：'.$arrData['bm_bn'].' 基础物料重复';
            return false;
        }
        $bm = app::get('material')->model('basic_material')->db_dump(['material_bn'=>$arrData['bm_bn'], 'type'=>'4'], 'bm_id,material_name');
        if(empty($bm)) {
            $msg['error'] = 'Line '.$this->nums.'：'.$arrData['bm_bn'].' 礼盒物料不存在';
            return false;
        }
        $this->import_data_bm_bn[] = $arrData['bm_bn'];
        $item = [
            'bm_id' => $bm['bm_id'],
            'bm_bn' => $arrData['bm_bn'],
            'bm_name' => $bm['material_name'],
            'number' => $arrData['number'],
        ];
        $this->import_data['items'][] = $item;
        $mark = 'contents';
        return true;
    }

    function prepared_import_csv_obj($data,$mark,$tmpl,&$msg = ''){
        return null;
    }

    public function finish_import_csv(){
        if(empty($this->import_data)) {
            return null;
        }
        $data = $this->import_data['main'];
        $items = $this->import_data['items'];
        $this->insertDataItems($data, $items, '导入');
    }

    public function insertDataItems($data, $items, $source) {
        if(empty($data) || empty($items)) {
            return [false, '数据不全'];
        }
        $this->db->beginTransaction();
        $data['mp_bn'] = $this->gen_id();
        $this->insert($data);
        if(!$data['id']) {
            $this->db->rollBack();
            return [false, ['msg'=>'写入失败']];
        }
        app::get('ome')->model('operation_log')->write_log('material_package@console',$data['id'],"订单".$source."新建");
        foreach ($items as $k => $value) {
            $items[$k]['mp_id'] = $data['id'];
        }
        $itemObj = app::get('console')->model('material_package_items');
        $sql = ome_func::get_insert_sql($itemObj, $items);
        $this->db->exec($sql);
        $items = $itemObj->getList('*', ['mp_id'=>$data['id']]);
        $bmIds = array_column($items, 'bm_id');
        $basicMaterialCombinationItemsObj = app::get('material')->model('basic_material_combination_items');
        $bmsRows = $basicMaterialCombinationItemsObj->getList('pbm_id,bm_id,material_bn,material_name,material_num', array('pbm_id' => $bmIds), 0, -1);
        $bms = [];
        foreach ($bmsRows as $v) {
            $bms[$v['pbm_id']][] = $v;
        }
        
        // 校验物料同步状态
        list($validateRs, $validateMsg) = $this->_validateMaterialSyncStatus($data['branch_id'], $items, $bmsRows);
        if (!$validateRs) {
            $this->db->rollBack();
            return [false, ['msg' => $validateMsg]];
        }
        
        $storeManage = kernel::single('ome_store_manage');
        $storeManage->loadBranch(array('branch_id'=>$data['branch_id']));
        $mpid = [];
        $storeLessMsg = '';
        foreach ($items as $v) {
            if(empty($bms[$v['bm_id']])) {
                $this->db->rollBack();
                return [false, ['msg'=>'缺少明细']];
            }
            if($data['service_type'] == '2') {
                $validNum  = $storeManage->processBranchStore(
                    array(
                        'node_type' =>'getAvailableStore',
                        'params'    =>array(
                            'branch_id' =>$data['branch_id'],
                            'product_id'=>$v['bm_id'],
                        ),
                    ), $err_msg);
                if($v['number'] > $validNum) {
                    $storeLessMsg .= $v['bm_bn'].'库存不足;';
                }
            }
            foreach ($bms[$v['bm_id']] as $vv) {
                $number = bcmul($v['number'], $vv['material_num']);
                $mpid[] = [
                    'mp_id' => $v['mp_id'],
                    'mpi_id' => $v['id'],
                    'bm_id' => $vv['bm_id'],
                    'bm_bn' => $vv['material_bn'],
                    'bm_name' => $vv['material_name'],
                    'number' => $number,
                ];
                if($data['service_type'] == '1') {
                    $validNum  = $storeManage->processBranchStore(
                        array(
                            'node_type' =>'getAvailableStore',
                            'params'    =>array(
                                'branch_id' =>$data['branch_id'],
                                'product_id'=>$vv['bm_id'],
                            ),
                        ), $err_msg);
                    if($number > $validNum) {
                        $storeLessMsg .= $vv['material_bn'].'库存不足;';
                    }
                }
            }
        }
        if($storeLessMsg) {
            $this->db->rollBack();
            return [false, ['msg'=>$storeLessMsg]];
        }
        $itemDetailObj = app::get('console')->model('material_package_items_detail');
        $sql = ome_func::get_insert_sql($itemDetailObj, $mpid);
        $this->db->exec($sql);
        $this->db->commit();
        return [true, ['msg'=>'操作成功']];
    }

    public function gen_id() {
        $prefix = 'MP'.date("ymd");
        $sign   = kernel::single('eccommon_guid')->incId('material_package', $prefix, 6);
        return $sign;
    }

    /**
     * 校验物料同步状态
     * @param int $branch_id 仓库ID
     * @param array $mainItems 主商品数组，包含 bm_id 和 bm_bn
     * @param array $detailItems 子商品数组，包含 bm_id 和 bm_bn（或 material_bn）
     * @return array [是否成功, 错误信息]
     */
    public function _validateMaterialSyncStatus($branch_id, $mainItems, $detailItems = array()) {
        // 获取 wms_id，如果为空则跳过校验（门店仓）
        $wms_id = kernel::single('ome_branch')->getWmsIdById($branch_id);
        if (!$wms_id) {
            return [true, ''];
        }

        // 收集所有商品的 bm_id 和 bm_bn
        $bm_ids = array();
        $bm_bn_map = array();
        
        // 收集主商品
        foreach ($mainItems as $item) {
            if (!empty($item['bm_id'])) {
                $bm_ids[] = $item['bm_id'];
                $bm_bn_map[$item['bm_id']] = isset($item['bm_bn']) ? $item['bm_bn'] : '';
            }
        }
        
        // 收集子商品
        foreach ($detailItems as $item) {
            if (!empty($item['bm_id'])) {
                $bm_ids[] = $item['bm_id'];
                // 子商品可能使用 material_bn 或 bm_bn
                $bm_bn_map[$item['bm_id']] = isset($item['material_bn']) ? $item['material_bn'] : (isset($item['bm_bn']) ? $item['bm_bn'] : '');
            }
        }
        
        $bm_ids = array_unique($bm_ids);
        
        if (empty($bm_ids)) {
            return [true, ''];
        }

        // 查询 foreign_sku 表，获取所有商品的同步状态
        $foreignSkuModel = app::get('console')->model('foreign_sku');
        $foreignSkuList = $foreignSkuModel->getList('inner_product_id, sync_status, inner_sku', array(
            'inner_product_id|in' => $bm_ids,
            'wms_id' => $wms_id
        ));

        // 构建已查询到的商品映射（bm_id => sync_status）
        $syncStatusMap = array();
        $skuMap = array();
        foreach ($foreignSkuList as $foreignSku) {
            $syncStatusMap[$foreignSku['inner_product_id']] = $foreignSku['sync_status'];
            $skuMap[$foreignSku['inner_product_id']] = $foreignSku['inner_sku'];
        }

        // 校验同步状态，收集未同步的商品信息
        $unsyncItems = array();
        $syncStatusText = array(
            0 => '未同步',
            1 => '同步失败',
            2 => '同步中',
            3 => '同步成功',
            4 => '同步后编辑',
        );
        foreach ($bm_ids as $bm_id) {
            if (!isset($syncStatusMap[$bm_id])) {
                // 商品在 foreign_sku 表中不存在，视为未分配
                $material_bn = isset($bm_bn_map[$bm_id]) && !empty($bm_bn_map[$bm_id]) ? $bm_bn_map[$bm_id] : $bm_id;
                $unsyncItems[] = array(
                    'material_bn' => $material_bn,
                    'status_text' => '未分配'
                );
            } elseif ($syncStatusMap[$bm_id] != '3') {
                // sync_status 不等于 3，视为未同步成功
                // inner_sku 就是 material_bn
                $material_bn = isset($skuMap[$bm_id]) ? $skuMap[$bm_id] : (isset($bm_bn_map[$bm_id]) && !empty($bm_bn_map[$bm_id]) ? $bm_bn_map[$bm_id] : $bm_id);
                $unsyncItems[] = array(
                    'material_bn' => $material_bn,
                    'status_text' => isset($syncStatusText[$syncStatusMap[$bm_id]]) ? $syncStatusText[$syncStatusMap[$bm_id]] : '未知状态'
                );
            }
        }
        // 如果有未同步的商品，生成错误提示信息
        if (!empty($unsyncItems)) {
            $errorMsg = '以下商品未同步到仓库，请先到"基础物料分配"页面同步物料：';
            $itemMsgs = array();
            foreach ($unsyncItems as $item) {
                $itemMsgs[] = '商品编码：' . $item['material_bn'] . '，同步状态：' . $item['status_text'];
            }
            $errorMsg .= implode('；', $itemMsgs);
            return [false, $errorMsg];
        }

        return [true, ''];
    }

    public function updateDataItems($data, $items, $source) {
        if(empty($data) || empty($items)) {
            return [false, '数据不全'];
        }
        $this->db->beginTransaction();
        $upData = [
            'mp_name' => $data['mp_name'],
            'branch_id' => $data['branch_id'],
            'movement_code' => $data['movement_code'],
            'memo' => $data['memo'],
        ];
        $rs = $this->update($upData, ['id'=>$data['id']]);
        app::get('ome')->model('operation_log')->write_log('material_package@console',$data['id'],"订单".$source."更新");
        $itemObj = app::get('console')->model('material_package_items');
        $oldItems = $itemObj->getList('*', ['mp_id'=>$data['id']]);
        $bmIds = array_column($items, 'bm_id');
        $oldBmIds = array_column($oldItems, 'bm_id');
        $bmIds = array_merge($bmIds, $oldBmIds);
        $basicMaterialCombinationItemsObj = app::get('material')->model('basic_material_combination_items');
        $bmsRows = $basicMaterialCombinationItemsObj->getList('pbm_id,bm_id,material_bn,material_name,material_num', array('pbm_id' => $bmIds), 0, -1);
        $bms = [];
        foreach ($bmsRows as $v) {
            $bms[$v['pbm_id']][$v['bm_id']] = $v;
        }
        
        // 收集需要校验的主商品（只包括保留和新增的，不包括删除的）
        // $items 中包含了所有要保留和新增的主商品
        $allMainItems = $items;
        
        // 获取要保留和新增的主商品的 bm_id 列表
        $keepBmIds = array_column($items, 'bm_id');
        
        // 收集需要校验的子商品
        // 从 bmsRows 过滤，只保留那些 pbm_id 在 $items 中的子商品（排除要删除的主商品的子商品）
        $allDetailItems = array();
        foreach ($bmsRows as $bmsRow) {
            if (in_array($bmsRow['pbm_id'], $keepBmIds)) {
                $allDetailItems[] = $bmsRow;
            }
        }
        
        // 2. 收集保留的旧主商品对应的子商品（从 itemDetail 获取）
        // 只收集那些在 $items 中存在的旧主商品对应的子商品
        $itemDetailObj = app::get('console')->model('material_package_items_detail');
        foreach ($oldItems as $oldItem) {
            // 如果这个旧主商品在 $items 中存在，说明是保留的，需要校验其子商品
            if (isset($items[$oldItem['bm_id']])) {
                $oldDetailItems = $itemDetailObj->getList('bm_id, bm_bn', ['mpi_id' => $oldItem['id']]);
                $allDetailItems = array_merge($allDetailItems, $oldDetailItems);
            }
            // 如果不在 $items 中，说明是要删除的，不需要校验
        }
        
        // 校验物料同步状态
        list($validateRs, $validateMsg) = $this->_validateMaterialSyncStatus($data['branch_id'], $allMainItems, $allDetailItems);
        if (!$validateRs) {
            $this->db->rollBack();
            return [false, ['msg' => $validateMsg]];
        }
        
        $storeManage = kernel::single('ome_store_manage');
        $storeManage->loadBranch(array('branch_id'=>$data['branch_id']));
        $storeLessMsg = '';
        foreach ($oldItems as $value) {
            if($items[$value['bm_id']]) {
                $newItem = $items[$value['bm_id']];
                unset($items[$value['bm_id']]);
                if($newItem['number'] != $value['number']) {
                    $itemObj->update(['number'=>$newItem['number']], ['id'=>$value['id']]);
                    if($data['service_type'] == '2') {
                        $validNum  = $storeManage->processBranchStore(
                            array(
                                'node_type' =>'getAvailableStore',
                                'params'    =>array(
                                    'branch_id' =>$data['branch_id'],
                                    'product_id'=>$newItem['bm_id'],
                                ),
                            ), $err_msg);
                        if($newItem['number'] > $validNum) {
                            $storeLessMsg .= $newItem['bm_bn'].'库存不足;';
                        }
                    }
                    $itemDetail = $itemDetailObj->getList('id, bm_id, bm_bn', ['mpi_id'=>$value['id']]);
                    foreach ($itemDetail as $vv) {
                        $number = bcmul($newItem['number'], $bms[$value['bm_id']][$vv['bm_id']]['material_num']);
                        $itemDetailObj->update(['number' => $number], ['id'=>$vv['id']]);
                        if($data['service_type'] == '1') {  
                            $validNum  = $storeManage->processBranchStore(
                                array(
                                    'node_type' =>'getAvailableStore',
                                    'params'    =>array(
                                        'branch_id' =>$data['branch_id'],
                                        'product_id'=>$vv['bm_id'],
                                    ),
                                ), $err_msg);
                            if($number > $validNum) {
                                $storeLessMsg .= $vv['bm_bn'].'库存不足;';
                            }
                        }
                    }
                }
            } else {
                $itemObj->delete(['id'=>$value['id']]);
                $itemDetailObj->delete(['mpi_id'=>$value['id']]);
            }
        }
        if($storeLessMsg) {
            $this->db->rollBack();
            return [false, ['msg'=>$storeLessMsg]];
        }
        if(empty($items)) {
            $this->db->commit();
            return [true, ['msg'=>'操作成功']];
        }
        $mpid = [];
        foreach ($items as $v) {
            if(empty($bms[$v['bm_id']])) {
                $this->db->rollBack();
                return [false, ['msg'=>'缺少明细']];
            }
            $v['mp_id'] = $data['id'];
            $itemObj->insert($v);
            if($data['service_type'] == '2') {
                $validNum  = $storeManage->processBranchStore(
                    array(
                        'node_type' =>'getAvailableStore',
                        'params'    =>array(
                            'branch_id' =>$data['branch_id'],
                            'product_id'=>$v['bm_id'],
                        ),
                    ), $err_msg);
                if($v['number'] > $validNum) {
                    $storeLessMsg .= $v['bm_bn'].'库存不足;';
                }
            }
            foreach ($bms[$v['bm_id']] as $vv) {
                $number = bcmul($v['number'], $vv['material_num']);
                $mpid[] = [
                    'mp_id' => $v['mp_id'],
                    'mpi_id' => $v['id'],
                    'bm_id' => $vv['bm_id'],
                    'bm_bn' => $vv['material_bn'],
                    'bm_name' => $vv['material_name'],
                    'number' => $number,
                ];
                if($data['service_type'] == '1') {
                    $validNum  = $storeManage->processBranchStore(
                        array(
                            'node_type' =>'getAvailableStore',
                            'params'    =>array(
                                'branch_id' =>$data['branch_id'],
                                'product_id'=>$vv['bm_id'],
                            ),
                        ), $err_msg);
                    if($number > $validNum) {
                        $storeLessMsg .= $vv['material_bn'].'库存不足;';
                    }
                }
            }
        }
        if($storeLessMsg) {
            $this->db->rollBack();
            return [false, ['msg'=>$storeLessMsg]];
        }
        $itemDetailObj = app::get('console')->model('material_package_items_detail');
        $sql = ome_func::get_insert_sql($itemDetailObj, $mpid);
        $this->db->exec($sql);
        $this->db->commit();
        return [true, ['msg'=>'操作成功']];
    }
}
