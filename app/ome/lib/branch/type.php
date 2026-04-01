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
 * 仓库类型Lib
 * @author xiayuanjun
 * @version 1.0
 */
class ome_branch_type{

    static private $__instance = null;

    static private $__branches = array();

    function __construct(){
        if(!isset(self::$__instance)){
            self::$__instance = app::get('ome')->model('branch');
        }
    }

    /**
     * 
     * 获取自建类型的仓库id列表
     * @param null
     * @return array $__branches['ownIds'] 自建仓库id列表数据
     */

    public function getOwnBranchIds(){
        if(!isset(self::$__branches['ownIds'])){
            $tmpBranchList = self::$__instance->getList('branch_id',array('owner'=>'1'));

            $tmpBranchIds = array();
            foreach ($tmpBranchList as $key => $value) {
                $tmpBranchIds[] = $value['branch_id'];
            }
            self::$__branches['ownIds'] = $tmpBranchIds;
        }
        return self::$__branches['ownIds'];
	}

	/**
	 * 
	 * 获取自建类型的仓库列表
	 * @param null
	 * @return array $__branches['own'] 自建仓库列表数据
	 */
	public function getOwnBranchLists(){
        if(!isset(self::$__branches['own'])){
            self::$__branches['own'] = self::$__instance->getList('branch_id,branch_bn,name',array('owner'=>'1'));
        }else{
            return self::$__branches['own'];
        }
	}

    /**
     * 
     * 获取第三方类型的仓库列表
     * @param null
     * @return array $__branches['other'] 第三方仓库列表数据
     */
    public function getOtherBranchLists(){
        if(!isset(self::$__branches['other'])){
            self::$__branches['other'] = self::$__instance->getList('branch_id,branch_bn,name',array('owner'=>'2'));
        }else{
            return self::$__branches['other'];
        }
    }

    public function getAllWmsBranch() {
        $branch = kernel::database()->select('select * from sdb_ome_branch where wms_id > 0');
        $arrBranch = array();
        foreach($branch as $val) {
            $arrBranch[$val['branch_id']] = $val;
        }
        return $arrBranch;
    }

    /**
     * 获取指定第三方仓
     * 
     * @param Array $node_type
     * @return void
     * @author 
     * */
    public function getAssignWmsBranch($node_type)
    {
        $channel = app::get('channel')->model('channel')->getList('channel_id',array('node_type'=>$node_type));

        if (!$channel) return array();

        $channel_id = array();
        foreach ($channel as $value) {
            $channel_id[] = $value['channel_id'];
        }

        $rows = app::get('ome')->model('branch')->getList('*',array('wms_id'=>$channel_id));

        return $rows;
    }

    public function isWmsBranch($branchId) {
        $branchIsWms = array();
        $branch = kernel::database()->select('select branch_id, wms_id from sdb_ome_branch where branch_id in ("' . implode('","', (array)$branchId) . '")');
        foreach($branch as $val) {
            $branchIsWms[$val['branch_id']] = $val['wms_id'] > 0 ? true : false;
        }
        return is_array($branchId) ? $branchIsWms : $branchIsWms[$branchId];
    }
    
        /**
     * 获取DamagedBranch
     * @param mixed $branchId ID
     * @return mixed 返回结果
     */
    public function getDamagedBranch($branchId) {
        static $arrDamagedBranch = array();
        if(isset($arrDamagedBranch[$branchId])) {
            return $arrDamagedBranch[$branchId];
        }
        $modelBranch = app::get('ome')->model('branch');
        $damagedBranch = $modelBranch->db_dump(array('parent_id'=>$branchId, 'type'=>'damaged'));
        if(!$damagedBranch) {
            $branch = $modelBranch->db_dump(array('branch_id' => $branchId));
            if ($branch['type'] == 'main') {
                $damagedBranch = $branch;
                $damagedBranch['type'] = 'damaged';
                $damagedBranch['parent_id'] = $branchId;
                $damagedBranch['branch_bn'] = $branch['branch_bn'] . '_damaged';
                $damagedBranch['name'] = $branch['name'] . '-残损';
                $damagedBranch['attr'] = 'false';
                $damagedBranch['is_deliv_branch'] = 'false';
                if ($damagedBranch['shop_config']) unset($damagedBranch['shop_config']);
                $damagedBranch['is_declare'] = 'false';
                unset($damagedBranch['defaulted']);
                unset($damagedBranch['branch_id']);
                $modelBranch->insert($damagedBranch);
            } elseif ($branch['type'] == 'aftersale') {
                $damagedBranch = $modelBranch->db_dump(array('parent_id'=>$branch['parent_id'], 'type'=>'damaged'));
                if(!$damagedBranch) {
                    $damagedBranch = $branch;
                    $damagedBranch['type'] = 'damaged';
                    $damagedBranch['branch_bn'] = $branch['branch_bn'] . '_damaged';
                    $damagedBranch['name'] = $branch['name'] . '-残损';
                    unset($damagedBranch['branch_id']);
                    $modelBranch->insert($damagedBranch);
                }
            } elseif ($branch['type'] == 'damaged') {
                $damagedBranch = $branch;
            }
        }
        $arrDamagedBranch[$branchId] = $damagedBranch;
        return $arrDamagedBranch[$branchId];
    }

    /**
     * 获取BranchIdByStoreCode
     * @param mixed $storeCode storeCode
     * @param mixed $type type
     * @param mixed $shop_id ID
     * @return mixed 返回结果
     */
    public function getBranchIdByStoreCode($storeCode, $type = '3pl', $shop_id = '') {
        $branchBn = (array) $storeCode;
        $arrBranch = array();
        $delivBranch = app::get('ome')->model("branch")->getDelivBranch();
        $branchR = app::get('ome')->model('branch_relation')->getList('branch_id,relation_branch_bn',
            array('relation_branch_bn'=>$branchBn, 'type'=>$type));
        foreach($branchR as $val) {
            if(!$arrBranch[$val['relation_branch_bn']] && $delivBranch[$val['branch_id']]) {
                $arrBranch[$val['relation_branch_bn']] = $val['branch_id'];
            }
        }
        $branch = app::get('ome')->model('branch')->getList('branch_id,branch_bn', array('branch_bn'=>$branchBn));
        foreach($branch as $val) {
            if(!$arrBranch[$val['branch_bn']] && $delivBranch[$val['branch_id']]) {
                $arrBranch[$val['branch_bn']] = $val['branch_id'];
            }
        }
        // $branchRelation = app::get('wmsmgr')->model('branch_relation')->getList('*', array('wms_branch_bn'=>$branchBn));
        // foreach($branchRelation as $val) {
        //     if(!$arrBranch[$val['wms_branch_bn']] && $delivBranch[$val['branch_id']]){
        //         $arrBranch[$val['wms_branch_bn']] = $val['branch_id'];
        //     }
        // }
        return $arrBranch;
    }
     
    /**
     * isAppointBranch
     * @param mixed $order order
     * @return mixed 返回值
     */
    public function isAppointBranch($order) {
        $boolType = $order['order_bool_type'];
        if($boolType & ome_order_bool_type::__4PL_CODE){
            return '4pl';
        }
        if($boolType & ome_order_bool_type::__3PL_CODE){
            return '3pl';
        }
        if($boolType & ome_order_bool_type::__SHI_CODE){
            return '3pl';
        }
        if(kernel::single('ome_order_bool_type')->isJITX($boolType)) {
            return 'vopjitx';
        }
        if(kernel::single('ome_order_bool_type')->isCPUP($boolType)) {
            return 'cpup';
        }
        if(kernel::single('ome_order_bool_type')->isJDLVMI($boolType)) {
            return 'jdlvmi';
        }
        return false;
    }

    /**
     * 根据单据类型获取仓库
     *
     * @return void
     * @author 
     **/
    public function getBranchByIOType($io_type_id = '')
    {
        $branchMdl = app::get('ome')->model('branch');

        $branchList = [];

        switch ($io_type_id) {
            case '1': // 采购入库
                $branch_id = kernel::single('ome_branch_flow_purchasein')->getBranchId();

                $branchList = $branchMdl->getList('branch_id,name,uname,phone,mobile',[
                    'b_type' => '1',
                    'type'  => 'main',
                    'branch_id' => $branch_id,
                ]);

                break;
            case '10': // 采购退货
                $branch_id = kernel::single('ome_branch_flow_damagedin')->getBranchId();
                $branchList = $branchMdl->getList('branch_id,name,uname,phone,mobile',[
                    'b_type' => '1',
                    'type'  => 'damaged',
                    'branch_id' => $branch_id,
                ]);

                break;
            
            default:
                // code...
                break;
        }

        return $branchList;
    }

    //通过仓库类型和仓储ID获取仓库ID
    /**
     * 通过仓库类型和仓储ID获取仓库
     *
     * @param string $type 仓库类型(对应branch_type.type)
     * @param int $wmsId 仓储ID(对应branch.wms_id)
     * @param string $warehouseCode 仓库编码(对应branch.warehouse_code)
     * @return array 仓库信息
     */
    public function getBranchByTypeWms($typeName, $wmsId, $warehouseCode = '') {
        if (empty($typeName) || empty($wmsId)) {
            return false;
        }

        $branchModel = app::get('ome')->model('branch');
        $filter = array(
            'storage_code' => trim($typeName),
            'wms_id' => intval($wmsId),
            'warehouse_code' => $warehouseCode
        );
        $branch = $branchModel->getList('branch_id', $filter, 0, 1);
        
        return $branch ? $branch[0] : [];
    }
}