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

class openapi_data_original_transfer{

    /**
     * 出入库类型映射关系
     *
     * @var string
     **/
    private $_io_type = array(
        'E' => '70',
        'A' => '7',
        'G' => '200',
        'F' => '100',
        'K' => '400',
        'J' => '300',
        'B' => '5',
        'Y' => '800',
        'Z' => '700',
        'T' => '4',
        'R' => '40',
        'I' => '1',
        'H' => '10',
        'D' => '50',
    );

    /**
     * 出入库单类型
     *
     * @var string
     **/
    private $_io_status = array(
        'FINISH'     => '3',
        'PARTFINISH' => '2',
        'CANCEL'     => '4',
        'NEW'        => '1',
    );

    public function add($data){
        $result = array('rsp'=>'succ');
        
        $branch_product_mdl = app::get('ome')->model('branch_product');
        $basicMaterialObj = app::get('material')->model('basic_material');
        $basicMaterialExtObj = app::get('material')->model('basic_material_ext');
        $supplier_mdl = app::get('purchase')->model('supplier');
        $branch_mdl = app::get('ome')->model('branch');
        $isoMdl = app::get('taoguaniostockorder')->model('iso');
        $billType = $isoMdl::$bill_type;
        if (isset($data['bill_type']) && $data['bill_type'] && !isset($billType[$data['bill_type']])) {
            $result['rsp'] = 'fail';
            $result['msg'] = '业务类型不存在';
            return $result;
        }
        $sdf['bill_type'] = $data['bill_type'];//业务类型
    
        $_supplier= $supplier_mdl->dump(array('name'=>$data['vendor']), 'supplier_id');
        $_branch = $branch_mdl->getList('branch_id',array('branch_bn'=>$data['branch_bn']));
        if( !$_branch ){
            $result['rsp'] = 'fail';
            $result['msg'] = '仓库不存在';
            return $result;
        }
        if ($sdf['bill_type'] == 'asn') {
            $isoInfo = $isoMdl->db_dump(['iso_bn' => $data['io_bn']], 'iso_id');
            if ($isoInfo) {
                $result['rsp'] = 'fail';
                $result['msg'] = '出入库单号已存在！';
                return $result;
            }
        }
        $type = array('E'=>'70','A'=>'7','G'=>'200','F'=>'100','K'=>'400','J'=>'300','Z'=>'700','Y'=>'800');
        $inType = array('E','G','K','Y');
        $outType = array('A','F','J','Z');
        $sdf['type_id'] = $type[$data['type']];
        $sdf['iso_price'] = $data['delivery_cost'] ? $data['delivery_cost'] : 0;
        $sdf['supplier'] = $data['vendor'];
        $sdf['supplier_id'] = $_supplier['supplier_id'];
        $sdf['branch'] = $_branch[0]['branch_id'];
        $sdf['iostockorder_name'] = $data['name'];
        $sdf['operator'] = $data['operator'];
        $sdf['memo'] = $data['memo'];
        $sdf['confirm'] = $data['confirm'];
        $sdf['source']  = 'openapi';

        $sdf['io_bn'] = $data['io_bn'];
        $items = $data['items'];

        if(count($items)<=0 || !$items){
            $result['rsp'] = 'fail';
            $result['msg'] = '缺少出入库商品';
            return $result;
        }
        $items_detail = [];
        foreach($items as $v){
            $product = $basicMaterialObj->getlist('bm_id',array('material_bn'=>$v['bn']));
            if (!$product) {
                $result['rsp'] = 'fail';
                $result['msg'] = sprintf('货品[%s]不存在',$v['bn']);
                return $result;
            }

            $basicMExtInfo = $basicMaterialExtObj->getlist('unit',array('bm_id'=>$product[0]['bm_id']));
            $product[0]['unit'] = $basicMExtInfo[0]['unit'];

            if($v['nums'] == 0){
                $result['rsp'] = 'fail';
                $result['msg'] = '['.$v['bn'].']库存数量不能为0';
                return $result;
            }
            if(in_array($data['type'],$outType)){
                $aRow = $branch_product_mdl->dump(array('product_id'=>$product[0]['bm_id'],'branch_id'=>$sdf['branch']),'store');
                if($v['nums'] > $aRow['store']){
                    $result['rsp'] = 'fail';
                    $result['msg'] = '货号：'.$v['bn'].'出库数不可大于库存数'.$aRow['store'];
                    return $result;
                }
            }

            $products[$product[0]['bm_id']] = array(
                'bn'=>$v['bn'],
                'nums'=>$v['nums'],
                'unit'=>$product[0]['unit'],
                'name'=>$v['name'],
                'price'=>$v['price'],
            );
            
            if(isset($v['batchs']) && $v['batchs']){
                foreach ($v['batchs'] as $batch) {
                    $products[$product[0]['bm_id']]['items_detail'][] = [
                        'product_id'   => $product[0]['bm_id'],
                        'name'         => $v['name'],
                        'price'        => $v['price'],
                        'bn'           => $batch['bn'] ?? $v['bn'],
                        'nums'         => $batch['nums'],
                        'batch_code'   => $batch['batch_code'],
                        'product_date' => $batch['product_date'],
                        'expire_date'  => $batch['expire_date'],
                        'sn'           => '',
                    ];
                }
            }

        }
        $sdf['products'] = $products;
        
        //外部仓库业务
        if($data["extrabranch_bn"] || $data["extrabranch_name"]){ //外部仓库编码和名称有其一
            $mdl_ome_extrabranch = app::get('ome')->model('extrabranch');
            if($data["extrabranch_bn"]){
                $extra_from_branch_bn = $mdl_ome_extrabranch->dump(array("branch_bn"=>$data["extrabranch_bn"]));
            }
            if($data["extrabranch_name"]){
                $extra_from_branch_name = $mdl_ome_extrabranch->dump(array("name"=>$data["extrabranch_name"]));
            }
            $update_extra_arr = array(); //更新数据数组
            if($extra_from_branch_bn && $extra_from_branch_name){ //根据编码和名称都有数据的 不管是不是同一条数据 都拿编码的获取的branch_id
                $extrabranch_id = $extra_from_branch_bn["branch_id"];
            }elseif($extra_from_branch_bn){
                $extrabranch_id = $extra_from_branch_bn["branch_id"];
                if($data["extrabranch_name"]){
                    $update_extra_arr["name"] = $data["extrabranch_name"];
                }
            }elseif($extra_from_branch_name){
                $extrabranch_id = $extra_from_branch_name["branch_id"];
                if($data["extrabranch_bn"]){
                    $update_extra_arr["branch_bn"] = $data["extrabranch_bn"];
                }
            }else{ //新增外部仓库
                if($data["extrabranch_bn"] && $data["extrabranch_name"]){
                    $insert_extra_arr = array("branch_bn" => $data["extrabranch_bn"],"name"=>$data["extrabranch_name"]);
                }elseif($data["extrabranch_bn"]){
                    $insert_extra_arr = array("branch_bn" => $data["extrabranch_bn"],"name"=>$data["extrabranch_bn"]); //有外部仓库编码 无名称时
                }elseif($data["extrabranch_name"]){
                    $insert_extra_arr = array("name" => $data["extrabranch_name"]);
                }
            }
            $extra_info_arr = array();
            if($data["extrabranch_uname"]){
                $extra_info_arr["uname"] = $data["extrabranch_uname"];
            }
            if($data["extrabranch_email"]){
                $extra_info_arr["email"] = $data["extrabranch_email"];
            }
            if($data["extrabranch_phone"]){
                $extra_info_arr["phone"] = $data["extrabranch_phone"];
            }
            if($data["extrabranch_mobile"]){
                $extra_info_arr["mobile"] = $data["extrabranch_mobile"];
            }
            if($data["extrabranch_memo"]){
                $extra_info_arr["memo"] = $data["extrabranch_memo"];
            }
            if(!empty($extra_info_arr)){
                if($insert_extra_arr){
                    $insert_extra_arr = array_merge($insert_extra_arr,$extra_info_arr);
                }else{
                    $update_extra_arr = array_merge($update_extra_arr,$extra_info_arr);
                }
            }
            if($insert_extra_arr){ 
                $mdl_ome_extrabranch->insert($insert_extra_arr);
                $extrabranch_id = $insert_extra_arr['branch_id'];
            }else{
                if($update_extra_arr){
                    $filter_arr = array("branch_id"=>$extrabranch_id);
                    $mdl_ome_extrabranch->update($update_extra_arr,$filter_arr);
                }
            }
            $sdf['extrabranch_id'] = $extrabranch_id;
        }
        
        $msg = '';
        $rs = kernel::single('console_iostockorder')->save_iostockorder($sdf,$msg);
        if($rs){
            $result['data'] = kernel::single('console_iostockorder')->getIoStockOrderBn();
            $iostock_type = kernel::single('ome_iostock')->get_iostock_types();
            $log_msg = sprintf('openapi新建%s：%s', $iostock_type[$sdf['type_id']]['info'], '成功');
            app::get('ome')->model('operation_log')->write_log('create_iostock@taoguaniostockorder', $rs, $log_msg);
        }else{
            $result['rsp'] = 'fail';
            $result['msg'] = $msg;
        }
        
        
        return $result;
    }
    
    public function getList($start_time,$end_time,$original_bn='',$supplier_bn='',$branch_bn='',$t_type='', $is_source=0, $offset=0,$limit=100){
        if(empty($start_time) || empty($end_time)){
            return false;
        }
        
        $iostockObj = app::get('ome')->model('iostock');
        $iostocktypeObj = app::get('ome')->model('iostock_type');
        $branchObj = app::get('ome')->model('branch');
        
        //外部仓库列表
        $extrabranchList = array();
        if($is_source){
            $oExtrabranch = app::get('ome')->model('extrabranch');
            $tempList = $oExtrabranch->getList('branch_id, name', array());
            if($tempList){
                foreach ($tempList as $key => $val){
                    $extrabranchList[$val['branch_id']] = $val['name'];
                }
            }
        }
        
        //获取基础物料信息
        $basicMaterialObj = app::get('material')->model('basic_material');
        $basicMaterialBarcode = kernel::single('material_basic_material_barcode');
        
        $countSql = "select count(iostock_id) as _count from sdb_ome_iostock where ";
        
        $where = " create_time >=".$start_time." and create_time <".$end_time;
        
        if($original_bn != ''){
            $where .= " AND original_bn = '".$original_bn."'";
        }
        if($branch_bn != ''){
            $_branch = $branchObj->getlist('branch_id',array('branch_bn'=>$branch_bn),0,1);
            $where .= " AND branch_id = '".$_branch[0]['branch_id']."'";
        }
        if($supplier_bn != ''){
            $supplierObj = app::get('purchase')->model('supplier');
            $_supplier = $supplierObj->getlist('supplier_id',array('bn'=>$supplier_bn),0,1);
            $where .= " AND supplier_id = '".$_supplier[0]['supplier_id']."'";
        }
        if($t_type != ''){
            $ioType = array('E'=>'70','A'=>'7','G'=>'200','F'=>'100','K'=>'400','J'=>'300','Z'=>'700','Y'=>'800','D'=>'50');
            $where .= " and type_id=".intval($ioType[$t_type]);
        }else{
            $where .= " and type_id in(70,7,200,100,400,300,700,800)";
        }
        
        $countList = $iostockObj->db->selectrow($countSql.$where);
        
        if(intval($countList['_count']) >0){
            
            $iostocktypeInfos = array();
            $iostocktype_arr = $iostocktypeObj->getList('type_id,type_name', array(), 0, -1);
            foreach ($iostocktype_arr as $k => $iostocktype){
                $iostocktypeInfos[$iostocktype['type_id']] = $iostocktype['type_name'];
            }
            
            $branchInfos = array();
            $branch_arr = $branchObj->getList('branch_id,branch_bn,name', array(), 0, -1);
            foreach ($branch_arr as $k => $branch){
                $branchInfos[$branch['branch_id']] = array('branch_bn'=>$branch['branch_bn'],'name'=>$branch['name']);
            }
            
            $listSql = "select * from sdb_ome_iostock where ";
            $lists = $iostockObj->db->select($listSql.$where." order by create_time asc limit ".$offset.",".$limit."");
            
            foreach($lists as &$v){
                //只获取有来源的出入库单(外部仓库)
                if($is_source){
                    $original_bn = $v['original_bn'];
                    $iso_sql = "SELECT extrabranch_id FROM sdb_taoguaniostockorder_iso WHERE iso_bn='". $original_bn ."'";
                    $iso_data = $iostockObj->db->selectrow($iso_sql);
                    if(empty($iso_data['extrabranch_id'])){
                        continue; //没有外部仓库,则跳过
                    }
    
                    $v['extrabranch_name'] = $extrabranchList[$iso_data['extrabranch_id']];
                }
                //出入库类型标识(in为入库、out为出库)
                $io = kernel::single('ome_iostock')->getIoByType($v['type_id']);
                $v['iso_type'] = $io == 1 ? 'in' : 'out';
                
                //获取基础物料信息
                $_product    = $basicMaterialObj->dump(array('material_bn'=>$v['bn']), 'bm_id, material_name');
                
                //基础物料条形码
                $_product['barcode'] = $basicMaterialBarcode->getBarcodeById($_product['bm_id']);
                
                $v['branch_bn'] = $branchInfos[$v['branch_id']]['branch_bn'];
                $v['branch_name'] = $branchInfos[$v['branch_id']]['name'];
                $v['barcode'] = $_product['barcode'];
                $v['name'] = $_product['material_name'];
                $v['type_name'] = $iostocktypeInfos[$v['type_id']];
            }
            
            return array(
                    'lists' => $lists,
                    'count' => $countList['_count'],
            );
            
        }else{
            return array(
                    'lists' => array(),
                    'count' => 0,
            );
        }
        
    }
    public function getIsoList($params, $offset, $limit)
    {
        $filter = array(
            'up_time|betweenstr' => array(
                $params['start_time'],
                $params['end_time'],
            ),
        );

        if ($params['type']) {
            $filter['type_id'] = array(0);
            foreach (explode(',', $params['type']) as $value) {
                $filter['type_id'][] = (int) $this->_io_type[$value];
            }
        }

        if ($params['branch_bn']) {
            $branch = app::get('ome')->model('branch')->dump(array(
                'branch_bn'       => $params['branch_bn'],
                'check_permission' => 'false'), 'branch_id');

            $filter['branch_id'] = $branch['branch_id'];
        }

        if ($params['iso_bn']) {
            $filter['iso_bn'] = $params['iso_bn'];
        }

        if ($params['bill_type']) {
            $filter['bill_type'] = $params['bill_type'];
        }

        if ($params['bill_type_not']) {
            $filter['bill_type|notin'] = explode(',', $params['bill_type_not']);
        }

        if ($params['status']) {
            $filter['iso_status'] = (int) $this->_io_status[$params['status']];
        }

        $isoMdl = app::get('taoguaniostockorder')->model('iso');
        $count  = $isoMdl->count($filter);

        if (!$count) {
            return array('lists' => array(), 'count' => 0);
        }

        $lists = array();

        $isoid_list = $branchid_list = array();

        $iso_list = $isoMdl->getList('*', $filter, $offset, $limit);
        $supplier_id = array_filter(array_column($iso_list, 'supplier_id'));
        if ($supplier_id) {
            $supplier_list = app::get('purchase')->model('supplier')->getList('supplier_id,bn,name',array(
                'supplier_id' => $supplier_id
            ));

            $supplier_list = array_column($supplier_list,null,'supplier_id');
        }
        $useLifeLog = app::get('console')->model('useful_life_log')->getList('original_id,product_id,num,bn,normal_defective,product_time,expire_time,purchase_code,produce_code', array('sourcetb'=>'iso', 'original_id'=>array_column($iso_list, 'iso_id')));
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
        foreach ($iso_list as $value) {
            $supplier = $supplier_list[$value['supplier_id']];
            $lists[] = array(
                // 'name'          => $value['name'],
                'iso_bn'        => $value['iso_bn'],
                'out_iso_bn'    => (string)$value['out_iso_bn'],
                'type'          => kernel::single('ome_iostock')->iostock_rules($value['type_id']),
                'original_bn'   => $value['original_bn'],
                'product_cost'  => $value['product_cost'],
                'iso_price'     => $value['iso_price'],
                'cost_tax'      => $value['cost_tax'],
                'oper'          => (string)$value['oper'],
                'logi_no'       => $value['logi_no'],
                'create_time'   => date('Y-m-d H:i:s', $value['create_time']),
                'complete_time' => $value['complete_time'] ? date('Y-m-d H:i:s', $value['complete_time']) : '',
                // 'operator'      => $value['operator'],
                'status'        => array_search($value['iso_status'], $this->_io_status),
                // 'check_status'  => $value['status'],
                'supplier_name' => (string)$value['supplier_name'],
                'supplier_bn'   => (string)$supplier['bn'],
                'branch_bn'     => &$branch_list[$value['branch_id']]['branch_bn'],
                'branch_type'     => &$branch_list[$value['branch_id']]['branch_type'],
                'extrabranch_bn' => $value['extrabranch_bn'],
                'bill_type'     => $value['bill_type'],
                'bill_bn'       => (string)$value['business_bn'],
                'cost_type'       => (string)$value['cost_type'],
                'cost_department' => (string)$value['cost_department'],
                'appropriation_no' => (string)$value['appropriation_no'],
                'items'         => &$items[$value['iso_id']],
            );

            $isoid_list[$value['iso_id']]       = $value['iso_id'];
            $branchid_list[$value['branch_id']] = $value['branch_id'];
        }

        $isoItemMdl = app::get('taoguaniostockorder')->model('iso_items');
        foreach ($isoItemMdl->getList('*', array('iso_id' => $isoid_list)) as $value) {
            $items[$value['iso_id']][] = array(
                'name'  => $value['product_name'],
                'bn'            => $value['bn'],
                'unit'          => $value['unit'],
                'price'         => $value['price'],
                'nums'          => $value['nums'],
                'normal_num'    => $value['normal_num'],
                'defective_num' => $value['defective_num'],
                'batchs'        => $this->_getBatchs($useLifeLog_arr, $value)
            );
        }

        $branchMdl = app::get('ome')->model('branch');
        foreach ($branchMdl->getList('branch_bn,branch_id,type', array('branch_id' => $branchid_list, 'skip_permission' => true)) as $value) {
            $branch_list[$value['branch_id']]['branch_bn'] = $value['branch_bn'];
            $branch_list[$value['branch_id']]['branch_type'] = $value['type'];
        }

        return array('lists' => $lists, 'count' => $count);
    }

    protected function _getBatchs(&$useLifeLog_arr, $iso_item)
    {
        $product_id = intval($iso_item['product_id']);
        $iso_id = intval($iso_item['iso_id']);
        $batchs = array();
        if($iso_item['normal_num'] > 0) {
            if($useLifeLog_arr[$iso_id][$product_id]['normal']) {
                $num = $iso_item['normal_num'];
                foreach ($useLifeLog_arr[$iso_id][$product_id]['normal'] as $ulk => $useLife) {
                    if($num < 1) {
                        break;
                    }
                    if($useLife['num'] >= $num) {
                        $tmpNum = $num;
                    } else {
                        $tmpNum = $useLife['num'];
                    }
                    $num -= $tmpNum;
                    $useLifeLog_arr[$iso_id][$product_id]['normal'][$ulk]['num'] -= $tmpNum;
                    if($useLifeLog_arr[$iso_id][$product_id]['normal'][$ulk]['num'] < 1) {
                        unset($useLifeLog_arr[$iso_id][$product_id]['normal'][$ulk]);
                    }
                    $useLife['num'] = $tmpNum;
                    $batchs[] = array(
                        'bn' => $useLife['bn'],
                        'nums' => $useLife['num'],
                        'batch_code' => $useLife['purchase_code'],
                        'product_date' => $useLife['product_time'],
                        'expire_date' => $useLife['expire_time'],
                        'produce_code' => $useLife['produce_code'],
                        'inventory_type' => $useLife['normal_defective'] == 'normal' ? 'ZP' : 'CC',
                    );
                }
            }
        }
        if($iso_item['defective_num'] > 0) {
            if($useLifeLog_arr[$iso_id][$product_id]['defective']) {
                $num = $iso_item['defective_num'];
                foreach ($useLifeLog_arr[$iso_id][$product_id]['defective'] as $ulk => $useLife) {
                    if($num < 1) {
                        break;
                    }
                    if($useLife['num'] >= $num) {
                        $tmpNum = $num;
                    } else {
                        $tmpNum = $useLife['num'];
                    }
                    $num -= $tmpNum;
                    $useLifeLog_arr[$iso_id][$product_id]['defective'][$ulk]['num'] -= $tmpNum;
                    if($useLifeLog_arr[$iso_id][$product_id]['defective'][$ulk]['num'] < 1) {
                        unset($useLifeLog_arr[$iso_id][$product_id]['defective'][$ulk]);
                    }
                    $useLife['num'] = $tmpNum;
                    $batchs[] = array(
                        'bn' => $useLife['bn'],
                        'nums' => $useLife['num'],
                        'batch_code' => $useLife['purchase_code'],
                        'product_date' => $useLife['product_time'],
                        'expire_date' => $useLife['expire_time'],
                        'produce_code' => $useLife['produce_code'],
                        'inventory_type' => $useLife['normal_defective'] == 'normal' ? 'ZP' : 'CC',
                    );
                }
            }
        }
        return $batchs;
    }
}
