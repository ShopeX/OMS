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

class console_receipt_allocate
{

    /**
     * 保存调拔单出库数据
     * 并冻结对应商品库存
     *
     */
    public function to_savestore($adata, $appropriation_type, $memo, $op_name, &$msg)
    {
        $oAppropriation       = app::get('taoguanallocate')->model("appropriation");
        $oAppropriation_items = app::get('taoguanallocate')->model("appropriation_items");
        $pStockObj            = kernel::single('console_stock_products');

        $basicMaterialObj = app::get('material')->model('basic_material');

        $oBranch    = app::get('ome')->model("branch_product");
        $op_name    = $op_name == '' ? '未知' : $op_name;
        $appro_data = array(
            'type'           => $appropriation['type'],
            'create_time'    => time(),
            'operator_name'  => $op_name,
            'memo'           => $memo,
            'corp_id'        => $adata[0]['corp_id'] ? $adata[0]['corp_id'] : 0,
            'from_branch_id' => $adata[0]['from_branch_id'],
            'to_branch_id'   => $adata[0]['to_branch_id'],
        );
        
        $extrabranch = [];
        if ($appro_data['to_branch_id']) {
            $extrabranch = app::get('ome')->model('branch')->db_dump([
                'branch_id' => $appro_data['to_branch_id'],
                'check_permission' => false,
            ], 'branch_bn');

            $appro_data['extrabranch_bn'] = $extrabranch['branch_bn'];
        }

        //新增原单据号
        if($adata[0]['original_bn']){
            $appro_data['original_bn'] = $adata[0]['original_bn'];
        }
        
        if($adata[0]['bill_type']){
            $appro_data['bill_type'] = $adata[0]['bill_type'];
        }
        if($adata[0]['business_bn']){
            $appro_data['business_bn'] = $adata[0]['business_bn'];
        }
        if($adata[0]['to_physics_id']){
            $appro_data['to_physics_id'] = $adata[0]['to_physics_id'];
        }
        $appro_data['appropriation_no'] = $this->gen_appropriation_no();
        $oAppropriation->save($appro_data);

        $appropriation_id = $appro_data['appropriation_id'];
       
        $is_flag = false;
        foreach ($adata as $k => $v) {
            
            //过滤数量是0的记录
            if(empty($v['num'])){
                continue;
            }
            
            //基础物料信息
            $product = $basicMaterialObj->dump(array('bm_id' => $v['product_id']), 'bm_id, material_bn, material_name');

            $from_branch_id = $v['from_branch_id'];
            $to_branch_id   = $v['to_branch_id'];
            $add_store_data = array(
                'pos_id' => $to_pos_id, 'product_id' => $v['product_id'], 'num' => $v['num'], 'branch_id' => $to_branch_id,
            );

            $lower_store_data = array(
                'pos_id' => $from_pos_id, 'product_id' => $v['product_id'], 'num' => $v['num'], 'branch_id' => $from_branch_id);
            
            $items_data = array(
                'appropriation_id' => $appropriation_id,
                'bn'               => $product['material_bn'],
                'product_name'     => $product['material_name'],
                'product_id'       => $v['product_id'],
                'from_branch_id'   => $from_branch_id == '' ? 0 : $from_branch_id,
                'from_pos_id'      => $from_pos_id == '' ? 0 : $from_pos_id,
                'to_branch_id'     => $to_branch_id == '' ? 0 : $to_branch_id,
                'to_pos_id'        => $to_pos_id == '' ? 0 : $to_pos_id,
                'num'              => $v['num'],
                'to_branch_num'    => $v['to_branch_num'],
                'from_branch_num'  => $v['from_branch_num'],
            );
            $oAppropriation_items->save($items_data);
            
            //标识
            $is_flag = true;
        }
        
        //没有调拔单,直接返回
        if($is_flag === false){
            return false;
        }
        
        if ($appropriation_type == 1) {
            //直接调拨
            $result = $this->do_iostock($appropriation_id, $msg);
            return $result;
        } elseif ($appropriation_type == 2) {
            //出入库调拨
            $result = $this->do_out_iostockorder($appropriation_id, $msg);
            return $result;
        } else {
            return false;
        }
    }

    /**
     *
     * 调拔单出库
     * @param  appropriation_id
     * @param  $msg
     */
    public function do_out_iostockorder($appropriation_id, &$msg)
    {
        $basicMaterialObj    = app::get('material')->model('basic_material');
        $basicMaterialExtObj = app::get('material')->model('basic_material_ext');

        #判断是否开启固定成本，如果开启，price等于商品成本价
        $cost = false;
        if (app::get('tgstockcost')->is_installed()) {
            $tgstockcost = app::get("ome")->getConf("tgstockcost.cost");
            if ($tgstockcost == 2) {
                $cost = true;
            }
        }
        $iostock_instance = kernel::service('ome.iostock');
        $appitemObj       = app::get('taoguanallocate')->model('appropriation_items');

        $products         = array();
        $db               = kernel::database();
        $sql              = 'SELECT * FROM `sdb_taoguanallocate_appropriation` WHERE `appropriation_id`=\'' . $appropriation_id . '\'';
        $app_detail       = $db->selectrow($sql);
        $app_items_detail = $appitemObj->getList('*', array('appropriation_id' => $appropriation_id), 0, -1);
        $branch_id        = 0;
        $to_branch_id     = 0;
        if ($app_items_detail) {
            $items_detail = array();
            foreach ($app_items_detail as $k => $v) {
                if (!$branch_id) {
                    $branch_id = $v['from_branch_id'];
                }
                //虚拟仓累计成本
                $costSetting = kernel::single('tgstockcost_system_setting')->getCostSetting();
                $branchCost = false;
                if ($costSetting['branch_cost']['value'] == '2') {
                    $branchCost = true;
                    $entityBranchProduct = $res = kernel::single('ome_entity_branch_product')->getBranchCountCostPrice($branch_id,$v['product_id'] );
                }
                if (!$to_branch_id) {
                    $to_branch_id = $v['to_branch_id'];
                }

                if ($cost) {
                    #如果已经开启固定成本，则获取商品的成本价
                    $product = $basicMaterialExtObj->dump(array('bm_id' => $v['product_id']), 'bm_id, retail_price, cost, unit');
                } else {
                    #如果没有开启，则不需要获取成本价
                    $product = $basicMaterialExtObj->dump(array('bm_id' => $v['product_id']), 'bm_id, retail_price, unit');

                    #调拨出库时，获取对应的单位成本
                    $unit_cost       = $db->selectRow('select unit_cost from  sdb_ome_branch_product where branch_id=' . $branch_id . ' and product_id=' . $v['product_id']);
                    $product['cost'] = $unit_cost['unit_cost'];
    
                    //使用虚拟仓累计成本
                    if ($branchCost) {
                        $entityUnitCost    = $entityBranchProduct[$branch_id][$v['product_id']]['unit_cost'];
                        $product['unit']     = isset($entityUnitCost) ? $entityUnitCost : $product['cost'];
                        $product['cost'] = isset($entityUnitCost) ? $entityUnitCost : $product['cost'];
                    }
                }

                $products[$v['product_id']] = array(
                    'unit'  => $product['unit'],
                    'name'  => $v['product_name'],
                    'bn'    => $v['bn'],
                    'nums'  => $v['num'],
                    'price' => $product['cost'] ? $product['cost'] : 0,

                );

                if($v['package_code']){

                    $extendpro = array('package_code'=>$v['package_code']);
                    $extendpro = serialize($extendpro);
                    $products[$v['product_id']]['items_detail'][] = array(
                        'product_id'=>$v['product_id'],
                        'unit'  => $product['unit'],
                        'name'  => $v['product_name'],
                        'bn'    => $v['bn'],
                        'nums'  => $v['num'],
                        'price' => $product['cost'] ? $product['cost'] : 0,
                        'extendpro'=>$extendpro,
                        'original_id' => $v['item_id'],
                    );
                }
                
            }
        }

        $extrabranch = [];
        if ($to_branch_id) {
            $extrabranch = app::get('ome')->model('branch')->db_dump([
                'branch_id' => $to_branch_id,
                'check_permission' => false,
            ], 'branch_bn');
        }

        $data = array(
            'iostockorder_name' => date('Ymd') . '出库单',
            'supplier'          => '',
            'supplier_id'       => 0,
            'branch'            => $branch_id,
            'extrabranch_id'    => $to_branch_id,
            'extrabranch_bn'     => $extrabranch['branch_bn'],
            'type_id'           => 40,
            'iso_price'         => 0,
            'memo'              => $app_detail['memo'],
            'operator'          => kernel::single('desktop_user')->get_name(),
            'original_bn'       => '',
            'original_id'       => $appropriation_id,
            'products'          => $products,
            'appropriation_no'  => $app_detail['appropriation_no'],
            'business_bn'       => $app_detail['business_bn'] ? $app_detail['business_bn'] : $app_detail['appropriation_no'],
            'physics_id'        => $app_detail['from_physics_id'],
            'logi_no'           => $app_detail['logi_no'],
        );

        if($app_detail['extra_ship_area']){
            $data['extra_ship_area'] = $app_detail['extra_ship_area'];
        }
        if($app_detail['extra_ship_addr']){
            $data['extra_ship_addr'] = $app_detail['extra_ship_addr'];
        }
        if($app_detail['extra_ship_mobile']){
            $data['extra_ship_mobile'] = $app_detail['extra_ship_mobile'];
        }
        if($app_detail['extra_ship_name']){
            $data['extra_ship_name'] = $app_detail['extra_ship_name'];
        }

        //加判断 是否已存在
        if($app_detail['appropriation_no']){
            $isoMdl = app::get('taoguaniostockorder')->model('iso');
            $isos = $isoMdl->db_dump(array('type_id'=>$data['type_id'],'appropriation_no'=>$data['appropriation_no']),'iso_id');
            if($isos){
                return false;
            }
        }
        if($app_detail['source_from'] == 'pos' && $app_detail['bill_type'] == 'returnnormal'){
            //门店退仓自动完成
            $data['confirm'] = 'Y';
        }
        if($app_detail['bill_type'] == 'transfer') {
            $data['check'] = 'Y';
        }
        if($app_detail['bill_type']){
            $data['bill_type'] = $app_detail['bill_type'];
        }
        if ($app_detail['corp_id']) {
            $data['corp_id'] = $app_detail['corp_id'];
        }

        $iostockorder_instance = kernel::single('console_iostockorder');
        return $iostockorder_instance->save_iostockorder($data, $msg);
    }

    /**
     *
     * 生成调拨单出入库明细
     * @param  $appropriation_id
     * @param
     * @param  $msg
     */
    public function do_iostock($appropriation_id, &$msg)
    {
        $allow_commit = false;
        kernel::database()->beginTransaction();

        //存储出入库记录
        $iostock_data = $this->get_iostock_data($appropriation_id);
        $out          = array(); //调出
        $in           = array(); //调入
        $out          = $in          = $iostock_data;
        foreach ($iostock_data['items'] as $item_id => $iostock) {
            $iostock['nums']  = abs($iostock['nums']);
            $out['branch_id'] = $iostock['from_branch_id'];
            $in['branch_id']  = $iostock['to_branch_id'];

        }
        if (count($out['items']) > 0) {
            $stockoutLib          = kernel::single('siso_receipt_iostock_stockout');
            $stockoutLib->_typeId = 40;
            if ($stockoutLib->create($out, $data, $msg)) {
                $allow_commit = true;
            }

        }
        if (count($in['items']) > 0 && $allow_commit) {

            $stockinLib          = kernel::single('siso_receipt_iostock_stockin');
            $stockinLib->_typeId = 4;
            if ($stockinLib->create($in, $data, $msg)) {
                $allow_commit = true;
            }

        }

        if ($allow_commit == true) {
            kernel::database()->commit();
            return true;
        } else {
            
            kernel::database()->rollBack();

            $msg['out_msg'] = $out_msg;
            $msg['in_msg']  = $in_msg;
            return false;
        }

    }

    /**
     * 组织出库数据
     * @access public
     * @param String $iso_id 出入库ID
     * @return sdf 出库数据
     */
    public function get_iostock_data($appropriation_id)
    {

        $appitemObj = app::get('taoguanallocate')->model('appropriation_items');

        $db           = kernel::database();
        $sql          = 'SELECT * FROM `sdb_taoguanallocate_appropriation` WHERE `appropriation_id`=\'' . $appropriation_id . '\'';
        $app_detail   = $db->selectrow($sql);
        $iostock_data = array(
            'original_id' => $appropriation_id,
            'original_bn' => $app_detail['appropriation_no'],
        );
        $app_items_detail = $appitemObj->getList('*', array('appropriation_id' => $appropriation_id), 0, -1);
        if ($app_items_detail) {
            foreach ($app_items_detail as $k => $v) {

                $bp_data = $db->selectrow('select unit_cost from sdb_ome_branch_product where branch_id = ' . $v['from_branch_id'] . ' and product_id = ' . $v['product_id']);

                $iostock_data['items'][$v['item_id']] = array(
                    'from_branch_id'   => $v['from_branch_id'],
                    'to_branch_id'     => $v['to_branch_id'],
                    'original_bn'      => '',
                    'original_id'      => $appropriation_id,
                    'original_item_id' => $v['item_id'],
                    'supplier_id'      => 0,
                    'bn'               => $v['bn'],
                    'iostock_price'    => $bp_data['unit_cost'] ? $bp_data['unit_cost'] : 0,
                    'nums'             => abs($v['num']),
                    'oper'             => $app_detail['operator_name'],
                    'create_time'      => $app_detail['create_time'],
                    'operator'         => kernel::single('desktop_user')->get_name(),
                    'memo'             => $app_detail['memo'],
                );
            }
        }
        return $iostock_data;
    }

    #生成16位的调拨单号
    private function gen_appropriation_no()
    {
//        $i                = rand(0, 9);
//        $appropriation_no = 'S' . date('YmdHis') . $i;
        
        // 生成16位调拨单号,并且保证唯一性
        $type = 'APPROPORATION';
        $prefix = 'S'.date('Ymd'); //S + 年月日
        $appropriation_no = kernel::single('eccommon_guid')->incId($type, $prefix, 6, true);
        
        return $appropriation_no;
    }

    /**
     * 调拔单创建
     * @return
     */
    public function create($data, $msg)
    {

        $approMdl         = app::get('taoguanallocate')->model("appropriation");
        $branchMdl        = app::get('ome')->model('branch');
        $approItemMdl     = app::get('taoguanallocate')->model('appropriation_items');
        $basicMaterialObj = app::get('material')->model('basic_material');

        $appro_data = array(
            'create_time'    => time(),
            'operator_name'  => $data['op_name'],
            'memo'           => $data['memo'],
            'corp_id'        => 0,
            'from_branch_id' => $data['from_branch_id'],
            'to_branch_id'   => $data['to_branch_id'],
            'bill_type'      => $data['bill_type'],
            'from_physics_id'=> $data['from_physics_id'],
            'to_physics_id'  => $data['to_physics_id'],
            'movement_code'  => $data['movement_code'],

        );
        if($data['source_from']){
            $appro_data['source_from'] = $data['source_from'];
        }

        if($data['logi_code']){
            $appro_data['logi_code'] = $data['logi_code'];
        }

        if($data['logi_no']){
            $appro_data['logi_no'] = $data['logi_no'];
        }
        if (!$appro_data['bill_type']) {
            //电商-》门店 补货
            $from_branch_id = $appro_data['from_branch_id'];
            $to_branch_id   = $appro_data['to_branch_id'];

            $from_branch = $branchMdl->db_dump(array('branch_id' => $from_branch_id, 'check_permission' => 'false'), 'b_type');
            $to_branch   = $branchMdl->db_dump(array('branch_id' => $to_branch_id, 'check_permission' => 'false'), 'b_type');
            if ($from_branch['b_type'] == 1 && $from_branch['b_type'] == 2) {
                $appro_data['bill_type'] = 'replenishment';
            }
        }

        $appro_data['appropriation_no'] = $data['appropriation_no'] ? $data['appropriation_no'] : $this->gen_appropriation_no();
        $appro_data['process_status'] = isset($data['process_status']) ? $data['process_status'] : 0;
        $appro_data['original_bn']    = isset($data['original_bn']) ? $data['original_bn'] : null;
        $appro_data['original_id']    = isset($data['original_id']) ? $data['original_id'] : null;
        $appro_data['extrabranch_bn'] = isset($data['extrabranch_bn']) ? $data['extrabranch_bn'] : '';
        $appro_data['is_quickly']     = isset($data['is_quickly']) ? $data['is_quickly'] : 'false';
        
        if($data['extra_ship_area']){
            $appro_data['extra_ship_area'] = $data['extra_ship_area'];
        }
        if($data['extra_ship_addr']){
            $appro_data['extra_ship_addr'] = $data['extra_ship_addr'];
        }
        if($data['extra_ship_mobile']){
            $appro_data['extra_ship_mobile'] = $data['extra_ship_mobile'];
        }
        if($data['extra_ship_name']){
            $appro_data['extra_ship_name'] = $data['extra_ship_name'];
        }
        $approMdl->save($appro_data);
        $appropriation_id = $appro_data['appropriation_id'];
        foreach ((array) $data['items'] as $k => $v) {
            $items = $approItemMdl->dump(array('appropriation_id' => $appropriation_id, 'product_id' => $v['product_id']), 'item_id');

            $items_data = array(
                'appropriation_id' => $appropriation_id,
                'bn'               => $v['material_bn'],
                'product_name'     => $v['material_name'],
                'product_id'       => $v['product_id'],
                'from_branch_id'   => $v['from_branch_id'],
                'from_pos_id'      => 0,
                'to_branch_id'     => $v['to_branch_id'],
                'to_pos_id'        => 0,
                'num'              => $v['num'],
                'to_branch_num'    => $v['to_branch_num'],
                'from_branch_num'  => $v['from_branch_num'],
            );
            if($v['package_code']){
                $items_data['package_code'] = $v['package_code'];
            }
            if ($items) {
                $items_data['item_id'] = $items['item_id'];
            }
            $approItemMdl->save($items_data);

        }

        return $appro_data['appropriation_no'];

    }
}
