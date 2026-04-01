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
 * ============================
 * @Author:   yaokangming
 * @Version:  1.0
 * @DateTime: 2022/11/29 11:26:14
 * @describe: 加工单
 * ============================
 */
class erpapi_wms_response_process_storeprocess {

    /**
     * status_update
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */

    public function status_update($sdf){
        $result = $this->_dealStatus($sdf);
        // 报警
        if($result['rsp'] == 'fail') {
            kernel::single('monitor_event_notify')->addNotify('wms_stockprocess_confirm', [
                'mp_bn' => $sdf['mp_bn'],
                'errmsg'      => $result['msg'],
            ]);
        }
        return $result;
    }

    private function _dealStatus($sdf){

        $tmp = $this->tmp_dealStatus($sdf);
        return $tmp;
    }

    private function tmp_dealStatus($sdf)
    {
        $mpObj = app::get('console')->model('material_package');
        $mpRow = $mpObj->db_dump(['mp_bn'=>$sdf['mp_bn']], '*');
        if(empty($mpRow)) {
            return ['rsp'=>'fail', 'msg'=>'缺少加工单'];
        }
        if($mpRow['service_type'] == '2') {
            $sdf['in_items'] = $sdf['product_items'];
            $sdf['out_items'] = $sdf['material_items'];
        } else {
            $sdf['in_items'] = $sdf['material_items'];
            $sdf['out_items'] = $sdf['product_items'];
        }
        if($mpRow['status'] != '2') {
            if($mpRow['status'] == '4' || $mpRow['status'] == '5') {
                kernel::database()->beginTransaction();

                list($rs, $rsData) = $this->_dealInItems($mpRow, $sdf['in_items']);
                if(!$rs) {  
                    kernel::database()->rollBack();
                    return ['rsp'=>'fail', 'msg'=>$rsData['msg']];
                }
                kernel::database()->commit(); 

                kernel::database()->beginTransaction();

                list($rs, $rsData) = $this->_dealOutItems($mpRow, $sdf['out_items']);
                if(!$rs) {
                    kernel::database()->rollBack();
                    return ['rsp'=>'fail', 'msg'=>$rsData['msg']];
                }
                kernel::database()->commit();

                // 所有处理完成，更新状态为'4'（已完成）
                kernel::database()->beginTransaction();
                $finalUpData = [
                    'status' => '4',
                    'complete_time' => $sdf['complete_time'],
                ];
                $finalRs = $mpObj->update($finalUpData, ['id'=>$mpRow['id'], 'status|in'=>['4','5']]);
                if(is_bool($finalRs)) {
                    kernel::database()->rollBack();
                    return ['rsp'=>'fail', 'msg'=>'加工单最终状态更新失败'];
                }
                kernel::database()->commit();
                return ['rsp'=>'succ', 'msg'=>'操作完成'];
            }
            return ['rsp'=>'fail', 'msg'=>'加工单无需处理'];
        }
        kernel::database()->beginTransaction();

        // 更新成"处理中"状态
        $upData = [
            'status' => '5',
        ];
        $rs = $mpObj->update($upData, ['id'=>$mpRow['id'], 'status'=>'2']);
        if(is_bool($rs)) {
            kernel::database()->rollBack();
            return ['rsp'=>'fail', 'msg'=>'加工单状态更新失败'];
        }
        $wspMdl = app::get('console')->model('wms_storeprocess');
        $wspRow = $wspMdl->db_dump(['mp_bn'=>$mpRow['mp_bn'], 'mp_status'=>'1'], 'id');
        if($wspRow) {
            $wspRs = $wspMdl->update(['mp_id'=>$mpRow['id'], 'mp_status'=>'2'], ['id'=>$wspRow['id'], 'mp_status'=>'1']);
            if(!is_bool($wspRs)) {
                app::get('ome')->model('operation_log')->write_log('wms_storeprocess@console',$wspRow['id'], '加工单完成');
            }
        }
        app::get('ome')->model('operation_log')->write_log('material_package@console',$mpRow['id'],"加工单完成");
        kernel::database()->commit();
        if($mpRow['service_type'] == '2') {
            $itemsDetail    = app::get('console')->model('material_package_items')->getList('*', ['mp_id' => $mpRow['id']]);
        } else {
            $itemsDetail    = app::get('console')->model('material_package_items_detail')->getList('*', ['mp_id' => $mpRow['id']]);
        }
        $storeManageLib = kernel::single('ome_store_manage');
        $storeManageLib->loadBranch(array('branch_id' => $mpRow['branch_id']));

        $params = array();
        $params['main']    = $mpRow;
        $params['items']    = $itemsDetail;
        $params = ['params'=>$params];
        $params['node_type'] = 'finishMaterialPackage';
        $err_msg = '';
        $storeManageLib->processBranchStore($params, $err_msg);

        kernel::database()->beginTransaction();

        list($rs, $rsData) = $this->_dealInItems($mpRow, $sdf['in_items']);
        if(!$rs) {
            kernel::database()->rollBack();
            return ['rsp'=>'fail', 'msg'=>$rsData['msg']];
        }
        kernel::database()->commit();

        kernel::database()->beginTransaction();

        list($rs, $rsData) = $this->_dealOutItems($mpRow, $sdf['out_items']);
        if(!$rs) {
            kernel::database()->rollBack();
            return ['rsp'=>'fail', 'msg'=>$rsData['msg']];
        }
        kernel::database()->commit();
        
        // 所有处理完成，更新状态为'4'（已完成）
        kernel::database()->beginTransaction();
        $finalUpData = [
            'status' => '4',
            'complete_time' => $sdf['complete_time'],
        ];
        $finalRs = $mpObj->update($finalUpData, ['id' => $mpRow['id'], 'status' => '5']);
        if(is_bool($finalRs)) {
            kernel::database()->rollBack();
            return ['rsp'=>'fail', 'msg'=>'加工单最终状态更新失败'];
        }
        kernel::database()->commit();
        
        return ['rsp'=>'succ', 'msg'=>'操作完成'];
    }

    private function _dealInItems($main, $in_items) {
        $mpiObj = app::get('console')->model('material_package_items');
        if($main['service_type'] == '2') {
            $mpiObj = app::get('console')->model('material_package_items_detail');
        }
        $items = $mpiObj->getList('*', ['mp_id'=>$main['id'], 'in_number'=>0]);
        if (empty($items)) {
            return [true, ['msg'=>'不存在入库数量为0的明细']];
        }
        $bmidItems = [];
        $products = [];
        foreach ($items as $v) {
            $bmidItems[$v['bm_bn']][] = $v;
        }
        $bm_bns = array_column($items,null,'bm_bn');
        foreach ($in_items as $v) {
            if(!$bmidItems[$v['bm_bn']]) {
                return [false, ['msg'=>'物料不在明细中:'.$v['bm_bn']]];
            }
            $in_number = $v['number'];
            foreach ($bmidItems[$v['bm_bn']] as $vv) {
                if($products[$vv['bm_id']]) {
                    if($in_number > $vv['number']) {
                        $upInNumber = $vv['number'];
                    } else {
                        $upInNumber = $in_number;
                    }
                    $in_number -= $upInNumber;
                    $mpiObj->update(['in_number'=>$upInNumber], ['id'=>$vv['id']]);
                    continue;
                }
                if($in_number > $vv['number']) {
                    $upInNumber = $vv['number'];
                } else {
                    $upInNumber = $in_number;
                }
                $in_number -= $upInNumber;
                $mpiObj->update(['in_number'=>$upInNumber], ['id'=>$vv['id']]);
                $products[$vv['bm_id']] = [
                    'bn' => $vv['bm_bn'],
                    'name' => $vv['bm_name'],
                    'nums' => $v['number'],
                    'unit' => '',
                    'price' => 0,
                ];

                $batchs = array();
                if($v['batch']){
                    foreach($v['batch'] as $bv){
                        $bm_id = $bm_bns[$v['bm_bn']]['bm_id'];
                        $bv['product_id'] = $bm_id;

                        $batchs[] =$bv;
                    }
                }
                if($batchs) $products[$vv['bm_id']]['batch'] = $batchs;
            }

            if($in_number > 0) {
                return [false, ['msg'=>'物料超过数量:'.$v['bm_bn']]];
            }
        }
        $op = kernel::single('ome_func')->getDesktopUser();
        $data = array(
            'iostockorder_name' => date('Ymd') . '入库单',
            'supplier'          => '',
            'supplier_id'       => 0,
            'branch'            => $main['branch_id'],
            'extrabranch_id'    => 0,
            'type_id'           => ome_iostock::DIRECT_STORAGE,
            'iso_price'         => 0,
            'memo'              => $main['memo'],
            'operator'          => $op['op_name'],
            'original_bn'       => $main['mp_bn'],
            'original_id'       => $main['id'],
            'products'          => $products,
            'appropriation_no'  => '',
            'bill_type'         => 'workorder',
            'confirm'           => 'Y',
            'business_bn'       => $main['mp_bn'], 
        );


        
        $iostockorder_instance = kernel::single('console_iostockorder');
        $msg = '';
        $rs = $iostockorder_instance->save_iostockorder($data, $msg);
        return [$rs, ['msg'=>$msg]];
    }

    private function _dealOutItems($main, $out_items) {
        $mpidObj = app::get('console')->model('material_package_items_detail');
        if($main['service_type'] == '2') {
            $mpidObj = app::get('console')->model('material_package_items');
        }
        $items = $mpidObj->getList('*', ['mp_id'=>$main['id'], 'out_number'=>0]);
        if (empty($items)) {
            return [true, ['msg'=>'不存在出库数量为0的明细']];
        }
        $bmidItems = [];
        $products = [];
        foreach ($items as $v) {
            $bmidItems[$v['bm_bn']][] = $v;
        }
        foreach ($out_items as $v) {
            if(!$bmidItems[$v['bm_bn']]) {
                return [false, ['msg'=>'物料不在明细中:'.$v['bm_bn']]];
            }
            $out_number = $v['number'];
            foreach ($bmidItems[$v['bm_bn']] as $vv) {
                if($products[$vv['bm_id']]) {
                    if($out_number > $vv['number']) {
                        $upInNumber = $vv['number'];
                    } else {
                        $upInNumber = $out_number;
                    }
                    $out_number -= $upInNumber;
                    $mpidObj->update(['out_number'=>$upInNumber], ['id'=>$vv['id']]);
                    continue;
                }
                if($out_number > $vv['number']) {
                    $upInNumber = $vv['number'];
                } else {
                    $upInNumber = $out_number;
                }
                $out_number -= $upInNumber;
                $mpidObj->update(['out_number'=>$upInNumber], ['id'=>$vv['id']]);
                $products[$vv['bm_id']] = [
                    'bn' => $vv['bm_bn'],
                    'name' => $vv['bm_name'],
                    'nums' => $v['number'],
                    'unit' => '',
                    'price' => 0,
                ];

                $batchs = array();
                if($v['batch']){
                    foreach($v['batch'] as $bv){
                        $bv['product_id'] = $vv['bm_id'];

                        $batchs[] =$bv;
                    }
                }
                if($batchs) $products[$vv['bm_id']]['batch'] = $batchs;
            }
            if($out_number > 0) {
                return [false, ['msg'=>'物料超过数量:'.$v['bm_bn']]];
            }
        }
        $op = kernel::single('ome_func')->getDesktopUser();
        $data = array(
            'iostockorder_name' => date('Ymd') . '出库单',
            'supplier'          => '',
            'supplier_id'       => 0,
            'branch'            => $main['branch_id'],
            'extrabranch_id'    => 0,
            'type_id'           => ome_iostock::DIRECT_LIBRARAY,
            'iso_price'         => 0,
            'memo'              => $main['memo'],
            'operator'          => $op['op_name'],
            'original_bn'       => $main['mp_bn'],
            'original_id'       => $main['id'],
            'products'          => $products,
            'appropriation_no'  => '',
            'bill_type'         => 'workorder',
            'confirm'           => 'Y',
            'business_bn'       => $main['mp_bn'], 
        );

        $iostockorder_instance = kernel::single('console_iostockorder');
        $msg = '';
        $rs = $iostockorder_instance->save_iostockorder($data, $msg);

        return [$rs, ['msg'=>$msg]];
    }
}
