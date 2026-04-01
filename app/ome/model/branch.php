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

class ome_mdl_branch extends dbeav_model {

    public static $branchList = null;
    public function _filter($filter, $tableAlias=null, $baseWhere=null) {
        $op_id = kernel::single('desktop_user')->get_id();
        if ($op_id) {//如果是系统同步，是没有当前管理员，默认拥有所有仓库权限
            $is_super = kernel::single('desktop_user')->is_super();
            if (!$is_super) {
                //加参数
                if ($filter['check_permission'] =='false'){
                    if ($filter['branch_id'] ){
                        $filter['branch_id'] = $filter['branch_id'];
                    }
                }else{
                    $branch_ids = $this->getBranchByUser(true);
                    if ($branch_ids) {
                        if ($filter['branch_id']) {
                            if (is_array($filter['branch_id'])) {
                                $realIds = array();
                                foreach ($filter['branch_id'] as $id) {
                                    if (in_array($id, $branch_ids)) {
                                        $realIds[] = $id;
                                    }
                                }

                                if ($realIds) {
                                    $filter['branch_id'] = $realIds;
                                } else {
                                    $filter['branch_id'] = 'false';
                                }

                            } elseif (!in_array($filter['branch_id'], $branch_ids)) {
                                $filter['branch_id'] = 'false';
                            }

                        } else {
                            $filter['branch_id'] = $branch_ids;
                        }
                    } else {
                        $filter['branch_id'] = 'false';
                    }
                }
            }
        }

        return parent::_filter($filter, $tableAlias, $baseWhere) . $where;
    }

    /*
     * 获取仓库对应的快递公司列表
     * @param int $branch_id
     * @return array
     */
    function get_corp($branch_id,$area='') {
        //根据仓库获取指定的物流
        $branch_corp_lib = kernel::single("ome_branch_corp");
        $corp_ids = $branch_corp_lib->getCorpIdsByBranchId(array($branch_id));
        if(empty($corp_ids)) {
            return [];
        }
        if (!$area || $area=='') {
            //代表该仓库找不到地区，默认为任意地方，所以任意物流公司都可送
            return $this->db->select("SELECT corp_id,name,type,weight,tmpl_type,channel_id FROM sdb_ome_dly_corp WHERE corp_id IN(" . implode(",", $corp_ids) . ") and disabled='false' AND d_type=1 ORDER BY weight DESC");
        } else {
            //获取没有设置地区的物流公司，代表这类物流公司哪都送
            $corp1 = $this->db->select("SELECT corp_id,name,type,weight,tmpl_type,channel_id FROM sdb_ome_dly_corp WHERE corp_id IN(" . implode(",", $corp_ids) . ") and disabled='false' AND d_type=1 AND corp_id NOT IN(SELECT DISTINCT(corp_id) FROM sdb_ome_dly_corp_area) ORDER BY weight DESC");
            //获取物流公司和仓库有地区交叉的物流公司
            $oRegion = kernel::single('eccommon_regions');
            $rows = $this->db->select("SELECT corp_id,region_id FROM sdb_ome_dly_corp_area");
            $region_ids = array();
            foreach ($rows as $v) {
                $region_ids[$v['corp_id']][] = $v['region_id'];
            }

            $corp_region = array();
            $branch_region = explode(":", $area);
            $corp_region[] = $branch_region[2];
            $regionShip = $oRegion->getOneById($branch_region[2], "local_name,region_path");
            $region_path = explode(",", $regionShip['region_path']);
            array_shift($region_path);
            array_pop($region_path);
            array_pop($region_path);
            if ($region_path) {
                foreach ($region_path as $id) {
                    if (!in_array($id, $corp_region)) {
                        $corp_region[] = $id;
                    }
                }
            }

            $corp_list = array();
            foreach ($region_ids as $k => $v) {
                if (array_intersect($v, $corp_region)) {
                    $corp_list[] = $k;
                }else{
                    $corp_remove[] = $k;
                }
            }

            if (empty($corp_list)) {
                $corp_sys = $corp1;
            } else {
                $corp2_corp_id_arr = array_intersect($corp_ids,$corp_list); //取交集
                if (!$corp2_corp_id_arr) {
                    return [];
                }
                $corp2 = $this->db->select("SELECT corp_id,name,type,weight,tmpl_type,channel_id FROM sdb_ome_dly_corp WHERE corp_id IN(" . implode(",", $corp2_corp_id_arr) . ") and disabled='false' ORDER BY weight DESC");
                $corp_sys = array_merge($corp2,$corp1);
                $corp_sys = $this->sysSortArray($corp_sys,'weight',"SORT_DESC","SORT_NUMERIC");
            }
            foreach($corp_sys as $key=>$val){
                $corp_sys[$key]['name'] = $val['name'].'*';
            }

            if (empty($corp_remove)) {
                return $corp_sys;
            } else {
                $corp3_corp_id_arr = array_intersect($corp_ids,$corp_remove); //取交集
                if (!$corp3_corp_id_arr) {
                    return [];
                }
                $corp3 = $this->db->select("SELECT corp_id,name,type,weight,tmpl_type,channel_id FROM sdb_ome_dly_corp WHERE corp_id IN(" . implode(",", $corp3_corp_id_arr) . ") and disabled='false' ORDER BY weight DESC");
                $corp_sys = array_merge($corp_sys,$corp3);
                $corp_sys = $this->sysSortArray($corp_sys,'weight',"SORT_DESC","SORT_NUMERIC");
                return $corp_sys;
            }
        }
    }

    function save_branch($data) {
        $oBranch_area = $this->app->model("branch_area");
        $areaGroupId = $data['areaGroupId'];
        $tmpGroupId = $oBranch_area->Getregion_id($areaGroupId);
        if ($data['branch_id'] != '') {
            $ret_region = $oBranch_area->Get_region($data['branch_id']);
            foreach ($ret_region as $k => $v) {
                if (in_array($v, $tmpGroupId) == false) {
                    $oBranch_area->Del_area($data['branch_id'], $v);
                }
            }
        }
        $this->save($data);
        foreach ($tmpGroupId as $k => $v) {
            $tmpdata = array(
                'branch_id' => $data['branch_id'],
                'region_id' => $v
            );
            $oBranch_area->save($tmpdata);
        }
    }

    function Get_name($branch_id) {
        $branch = $this->dump($branch_id, 'name');
        return $branch['name'];
    }

	function Getlist_name($branch_id) {
		if (!isset(self::$branchList[$branch_id])) {
			self::$branchList[$branch_id] = $this->Get_name($branch_id);
		}
		return self::$branchList[$branch_id];
	}

    /* 获取仓库列表 */

    function Get_branchlist() {
        $branch = $this->getList('branch_id,name,is_deliv_branch,online', [], 0, -1);

        return $branch;
    }

    /*
     * 获取仓库对应货号列表
     */

    function Get_poslist($branch_id) {

        $pos = $this->db->select('SELECT pos_id,store_position FROM sdb_ome_branch_pos WHERE branch_id=' . $branch_id);


        return $pos;
    }

    function fgetlist_csv(&$data, $filter, $offset) {
        $limit = 100;
        if ($filter['_gType']) {
            $title = array();
            if (!$data['title'])
                $data['title'] = array();
            $data['title']['' . $filter['_gType']] = '"' . implode('","', $this->io->data2local($this->io_title(array('type_id' => $filter['_gType'])))) . '"';
        }
        return false;
    }

    function io_title($filter, $ioType='csv') {
        $title = array();

        switch ($ioType) {
            case 'csv':
            default:

                $title = array(
                    $filter['type_id'],
                    'bn',
                    'name',
                    'store',
                    'sku_property',
                    'weight',
                    'store_position',
                );

                break;
        }
        $this->ioTitle['csv'][$filter['type_id']] = $title;
        return $title;
    }

    function export_csv($data) {
        $output = array();
        foreach ($data['title'] as $k => $val) {
            $output[] = $val . "\n" . implode("\n", (array) $data['content'][$k]);
        }
        echo implode("\n", $output);
    }

    /*
     * 获取操作员管辖仓库
     * getBranchByUser
     */

    function getBranchByUser($dataType=null,$type='online') {
        $opInfo = kernel::single('ome_func')->getDesktopUser();
        $op_id = $opInfo['op_id'];

        // 1. 获取直接的仓权限（原有逻辑）
        $directBranchIds = $this->getDirectBranchIds($op_id, $type);
        
        // 2. 获取继承的组织权限（仅适用于门店，固定为offline类型）
        $inheritedBranchIds = $this->getInheritedBranchIds($op_id, 'offline');
        
        // 3. 合并权限
        $allBranchIds = array_unique(array_merge($directBranchIds, $inheritedBranchIds));
        
        if ($dataType) {
            return array_values($allBranchIds);
        }
        
        if (empty($allBranchIds)) {
            return [];
        }
        
        $Obranch = $this->app->model('branch');
        $branch_list = $Obranch->getList('branch_id,name,uname,phone,mobile', array('branch_id' => $allBranchIds), 0, -1);
        
        if ($branch_list) {
            ksort($branch_list);
        }
        
        return $branch_list ? $branch_list : [];
    }

    /*
     * 删除仓库：
     * 拒绝删除条件：关联货位
     */

    function pre_recycle($data=null) {
        $Obranch_product = $this->app->model('branch_product');
        $Obranch_pos = $this->app->model('branch_pos');
        $Obranch = $this->app->model('branch');
        if (is_array($_POST['branch_id'])) {
            foreach ($_POST['branch_id'] as $key => $val) {
                #仓库与货位关联
                $Obranch_detail = $Obranch->dump($val, 'name');
                $pos = $Obranch_pos->dump(array('branch_id' => $val), 'pos_id');
                if (!empty($pos)) {
                    $this->recycle_msg = '仓库：' . $Obranch_detail['name'] . '已与货位建立关系,无法删除!';
                    return false;
                }
                #仓库与商品关联
                $Obranch_product_detail = $Obranch_product->dump(array('branch_id' => $val), 'product_id');
                if (!empty($Obranch_product_detail)) {
                    $this->recycle_msg = '仓库：' . $Obranch_detail['name'] . '已与商品建立关系,无法删除!';
                    return false;
                }
                $deled .= $Obranch_detail['name'] . " - ";
            }
            return true;
        }
    }

    function isExistOfflineBranch() {
        $row = $this->db->selectRow('select count(*) as total from  sdb_ome_branch where attr="false"');
        if ($row['total'] > 0) {
            return true;
        } else {
            return false;
        }
    }

    function isExistOfflineBranchBywms($wms_id) {
        $wms_id = implode(',',$wms_id);
        $row = $this->db->selectRow('select count(*) as total from  sdb_ome_branch where attr="false" AND wms_id in ('.$wms_id.')');

        if ($row['total'] > 0) {
            return true;
        } else {
            return false;
        }
    }
    function isExistOnlineBranch() {
        $row = $this->db->selectRow('select count(*) as total from  sdb_ome_branch where attr="true"');
        if ($row['total'] > 0) {
            return true;
        } else {
            return false;
        }
    }
    function isExistOnlineBranchBywms($wms_id) {
        $wms_id = implode(',',$wms_id);
        $row = $this->db->selectRow('select count(*) as total from  sdb_ome_branch where attr="true" AND wms_id in ('.$wms_id.')');
        if ($row['total'] > 0) {
            return true;
        } else {
            return false;
        }
    }
    function getOnlineBranchs($field='*') {
        #过滤o2o门店虚拟仓库
        $rows = $this->db->select('select ' . $field . ' from sdb_ome_branch where attr="true" AND b_type=1 order by weight desc');

        return $rows;
    }
    function getOnlineBranchsBywms($field='*',$wms_id=array()) {
        $rows = $this->db->select('select ' . $field . ' from sdb_ome_branch where attr="true" AND wms_id in ('.implode(',',$wms_id).') order by weight desc');

        return $rows;
    }
    function getOfflineBranchs($field='*') {
        #过滤o2o门店虚拟仓库
        $rows = $this->db->select('select ' . $field . ' from sdb_ome_branch where attr="false" AND b_type=1 order by weight desc');

        return $rows;
    }
    function getOfflineBranchsBywms($field='*',$wms_id=array()) {
        $rows = $this->db->select('select ' . $field . ' from sdb_ome_branch where attr="false" AND wms_id in ('.implode(',',$wms_id).') order by weight desc');

        return $rows;
    }

    /**
     * 获取所有仓库(过滤o2o门店仓)
     *
     * @param string $field
     * @return Array
     */
    function getAllBranchs($field='*') {
        $rows = $this->db->select('select ' . $field . ' from sdb_ome_branch where b_type=1 order by weight desc');

        return $rows;
    }

    /**
    * 对二维数组进行排序
    *
    * sysSortArray($Array,"Key1","SORT_ASC","SORT_RETULAR","Key2"……)
    * @param array   $ArrayData  需要排序的数组.
    * @param string $KeyName1    排序字段.
    * @param string $SortOrder1  顺序("SORT_ASC"|"SORT_DESC")
    * @param string $SortType1   排序类型("SORT_REGULAR"|"SORT_NUMERIC"|"SORT_STRING")
    * @return array              排序后的数组.
    */
    function sysSortArray($ArrayData,$KeyName1,$SortOrder1 = "SORT_ASC",$SortType1 = "SORT_REGULAR")
    {
        if(!is_array($ArrayData))
        {
              return $ArrayData;
        }
        // Get args number.
        $ArgCount = func_num_args();
        // Get keys to sort by and put them to SortRule array.
        for($I = 1;$I < $ArgCount;$I ++)
        {
              $Arg = func_get_arg($I);
              if(!preg_match("/SORT/i",$Arg))
              {
                  $KeyNameList[] = $Arg;
                  $SortRule[]    = '$'.$Arg;
              }
              else
              {
                  $SortRule[]    = $Arg;
              }
        }
        // Get the values according to the keys and put them to array.
        foreach($ArrayData AS $Key => $Info)
        {
              foreach($KeyNameList AS $KeyName)
              {
                  ${$KeyName}[$Key] = $Info[$KeyName];
              }
        }
        // Create the eval string and eval it.
        $EvalString = 'array_multisort('.join(",",$SortRule).',$ArrayData);';
        eval ($EvalString);
        return $ArrayData;
    }

    /* 获取发货仓绑定的备货仓信息 */
    function getDelivBranch($branch_id=0) {
        $filter = array('is_deliv_branch'=>'true','check_permission' => 'false');
        if($branch_id>0){
            $filter['branch_id'] = $branch_id;
        }
        $branchList = $this->getList('branch_id,name,is_deliv_branch,attr,bind_conf',$filter);
        foreach($branchList as $key=>$val){
            $val['bind_conf'] = unserialize($val['bind_conf']);
            $delivBranch[$val['branch_id']] = $val;
        }
        unset($branchList);

        return $delivBranch;
    }

    /***
    *
    */
    function get_corpbyarea($branch_id,$area='',$weight,$shop_type,$shop_id,$order_id,&$waybill_number) {
        $orderExtendObj = app::get('ome')->model('order_extend');
        $cropObj = app::get('ome')->model('dly_corp');
        
        $oneOrder = app::get('ome')->model('orders')->db_dump(array('order_id'=>$order_id), 'order_id, order_bn, shop_id, shop_type, logi_id, logi_no, shipping, order_bool_type, order_type, createway');
        $arrived_conf = app::get('ome')->getConf('ome.logi.arrived');
        $arrivedObj = kernel::single('omeauto_auto_plugin_arrived');
        $arriveResult = [];
        if ($arrived_conf=='1' 
            && in_array($oneOrder['shop_type'], $arrivedObj->getShopType())
            && $oneOrder['createway'] == 'matrix'
        ) {
            $branch = app::get('ome')->model('branch')->db_dump(['branch_id'=>$branch_id, 'check_permission'=>'false']);
            $data = [
                'orders'=>[$oneOrder],
                'branch'=>$branch,
            ];
            $arriveResult = kernel::single('erpapi_router_request')->set('shop', $oneOrder['shop_id'])->logistics_addressReachable($data);
        }
        // 指定快递：查询 assign_express_code，用于后续标记 flag_select
        $assignExpressCode = '';
        $assignExpressInfo = $orderExtendObj->db_dump(array('order_id'=>$order_id), 'assign_express_code');
        if (!empty($assignExpressInfo['assign_express_code'])) {
            $assignExpressCode = $assignExpressInfo['assign_express_code'];
        }

        // SOMS_PICKUP + 指定快递：只返回匹配的物流公司
        if ($assignExpressCode && kernel::single('ome_bill_label')->getBillLabelInfo($order_id, 'order', 'SOMS_PICKUP')) {
            $corpData = $cropObj->dump(array('type' => $assignExpressCode, 'disabled' => 'false'), '*');
            if ($corpData) {
                $corpData['flag_select'] = 1;
                return array($corpData);
            }
        }

        if(strtolower($shop_type) === 'aikucun' && $oneOrder['shipping']){
            $corps = $cropObj->getList('corp_id,name,type,weight,shop_id,tmpl_type,channel_id',array('type'=>$oneOrder['shipping'],'channel_id|than'=>'0','disabled'=>'false'));
            $orderExtend = app::get('ome')->model('order_extend')->dump(array('order_id'=>$order_id),'platform_logi_no');
            $waybill_number = $orderExtend['platform_logi_no'];

            foreach ($corps as $key => $value) {
                $corps[$key]['flag_select']  = 1;
            }

            return $corps;
        }

        if(kernel::single('ome_order_bool_type')->isJDLVMI($oneOrder['order_bool_type'])){
            $corpData = kernel::single('logistics_rule')->getJDLVMICorp($oneOrder);

            return $corpData ? array($corpData) : array();
            
        }
        if($oneOrder['order_type'] == 'vopczc') {
            $corpData = kernel::single('logistics_rule')->getVopczcCorp();
            return array($corpData);
        }
        #说明:如果是店铺类型是亚马逊或当当的，则只能显示他自己的物流公司和通用的物流公司。并且非亚马逊或当当的店铺只能选择通用的物流公司。
        $sqlstr = '';
        $shop_data = array('DANGDANG','AMAZON');
        $shop_type = strtoupper($shop_type);
        if (!in_array($shop_type,$shop_data)) {
            $shop_data = implode('\',\'',$shop_data );
            $sqlstr.=' AND `type` not in (\''.$shop_data.'\')';
        }else{
            $tmp_shop_type = array($shop_type);
            $shop_diff = array_diff($shop_data,$tmp_shop_type);
            $shop_diff = implode('\',\'',$shop_diff );
            $sqlstr.=' AND `type` not in (\''.$shop_diff.'\')';
        }
        //根据仓库获取指定的物流
        $branch_corp_lib = kernel::single("ome_branch_corp");
        $corp_ids = $branch_corp_lib->getCorpIdsByBranchId(array($branch_id));
        
        $install_o2o    = false;
        if(app::get('o2o')->is_installed()){
            $corpTypeLib    = kernel::single('o2o_corp_type');
            $install_o2o    = true;
            //带入门店自提、门店配送(门店物流方式不存在在branch_corp关系表中)
            $mdl_ome_dly_corp = $this->app->model('dly_corp');
            $rs_o2o_corps = $mdl_ome_dly_corp->getlist("corp_id",array("d_type"=>2));
            if(!empty($rs_o2o_corps)){
                foreach($rs_o2o_corps as $var_oc){
                    $corp_ids[] = $var_oc["corp_id"];
                }
            }
            // 门店仓且参与O2O：追加商家配送(seller)模式的物流公司
            $branchInfo = app::get('ome')->model('branch')->db_dump(array('branch_id'=>$branch_id,'check_permission'=>'false'), 'b_type');
            if ($branchInfo && $branchInfo['b_type']=='2') {
                $o2oStore = app::get('o2o')->model('store')->getList('store_id', array('branch_id'=>$branch_id,'is_o2o'=>'1'), 0, 1);
                if (!empty($o2oStore)) {
                    $sellerCorps = $mdl_ome_dly_corp->getList('corp_id', array('corp_model'=>'seller','disabled'=>'false'));
                    if (!empty($sellerCorps)) {
                        foreach ($sellerCorps as $sc) {
                            $corp_ids[] = $sc['corp_id'];
                        }
                    }
                }
            }
            // 去重，避免重复ID
            $corp_ids = array_values(array_unique($corp_ids));
        }
        if ($oneOrder['shipping'] && (kernel::single('ome_order_bool_type')->isCnService($oneOrder['order_bool_type']) || kernel::single('ome_order_bool_type')->isCPUP($oneOrder['order_bool_type']))
        ) {
            $corpData = kernel::single('logistics_rule')->getCorpIdByCode($oneOrder['shipping'], $branch_id, $order_id);
            if ($corpData) {
                $corpId = $corpData['corp_id'];
            }
        }
        
        $sql = "SELECT corp_id,name,type,weight,shop_id,tmpl_type,channel_id,d_type,corp_model FROM sdb_ome_dly_corp WHERE corp_id IN(" . implode(",", $corp_ids) . ") and disabled='false'".$sqlstr."  ORDER BY weight DESC";
        
        #获得哪都送的物流公司
        $corp1 = $this->db->select($sql);
        $copy_region = array();
        if (!$corpId) {
            $corpId = kernel::single('logistics_rule')->autoMatchDlyCorp($area, $branch_id,$weight,$shop_type,$shop_id);
        }
        if ($corpId) {
            $copy_region[] = $corpId;
        }
        
        //翱象建议物流
        if(in_array(strtolower($shop_type), array('taobao','tmall'))){
            $axOrderLib = kernel::single('dchain_order');
            
            //是否翱象订单标识
            $isAoxiang = false;
            if($oneOrder['order_bool_type']){
                $isAoxiang = kernel::single('ome_order_bool_type')->isAoxiang($oneOrder['order_bool_type']);
            }
            
            //extend_field
            $extend_field = array();
            if($isAoxiang){
                $extendInfo = $orderExtendObj->db_dump(array('order_id'=>$order_id), '*');
                if($extendInfo['extend_field']){
                    $extend_field = json_decode($extendInfo['extend_field'], true);
                }
            }
            
            //[翱象]建议物流公司
            $biz_delivery_codes = json_decode($extendInfo['biz_delivery_code'], true);
            $white_delivery_cps = json_decode($extendInfo['white_delivery_cps'], true);
            
            //设置默认物流公司
            foreach($corp1 as $corpKey => $corpVal)
            {
                //[翱象]建议物流公司(必须使用翱象建设配送物流公司)
                if($extend_field['biz_delivery_type'] == '2' && $biz_delivery_codes){
                    $axCorpIds = $axOrderLib->getConfirmLogistics($biz_delivery_codes, $shop_id);
                    
                    //check
                    if(empty($axCorpIds)){
                        continue;
                    }
                    
                    if(in_array($corpVal['corp_id'], $axCorpIds)){
                        //设置默认物流公司
                        $defaultCorpId = $corpVal['corp_id'];
                        
                        $copy_region = array($defaultCorpId);
                        
                        break;
                    }
                }elseif($white_delivery_cps && in_array($corpVal['type'], $white_delivery_cps)){
                    $defaultCorpId = $corpVal['corp_id'];
                    
                    $copy_region = array($defaultCorpId);
                    
                    break;
                }
            }
        }
        
        $corp_sys = $corp1;
        $branch_region = explode(":", $area);
        $corp_rule_list = array();
        foreach($corp_sys as $key=>$val){
            $corp_id = $val['corp_id'];
            $corp_rule_list[$corp_id] = $val;
            $corp_rule_list[$corp_id]['weight'] = $val['weight'];
            $flag='';
            $cost_freight = $this->app->model('delivery')->getDeliveryFreight($branch_region[2],$val['corp_id'],$weight);
            if($cost_freight==0){
                $cost_freight='运费未设置';
            }else{
                $cost_freight = '￥'.sprintf("%.2f",$cost_freight);
            }
            if(in_array($corp_id,$copy_region)){

                    $flag='(默认)';
                    $corp_rule_list[$val['corp_id']]['flag_select']=1;
            }
            $corp_str='';
            if($branch_rule['parent_id']!=0){
                if(in_array($corp_id,$copy_region)){
                    $corp_str='(复)';
                }
            }
            $electron='';
            if($val['tmpl_type']=='electron') {
                $electron='(电)';
            }
            //o2o门店物流公司_隐藏运费字样
            $name = $val['name'].'：'.$cost_freight.$flag.$corp_str.$electron;
            if ($arriveResult['rsp']=='succ' && $arriveResult['data'][$val['type']]) {
                if($arriveResult['data'][$val['type']]['is_deliverable']) {
                    $name .= '(可达)';
                } else {
                    $name .= '(不可达)';
                }
                if(!$arriveResult['data'][$val['type']]['is_shop_eBill']) {
                    $name .= '(未购买)';
                }
                if($arriveResult['data'][$val['type']]['is_recommended']) {
                    $name .= '(推荐)';
                }
                if($arriveResult['data'][$val['type']]['avg_cost_hours']) {
                    $name .= '(揽收时长'.$arriveResult['data'][$val['type']]['avg_cost_hours'].'h)';
                }
                if($arriveResult['data'][$val['type']]['level_percent']) {
                    $name .= '(预计超过'.$arriveResult['data'][$val['type']]['level_percent'].'%其他物流)';
                }
            }
            if($install_o2o)
            {
                $corp_type    = $corpTypeLib->get_corp_type($corp_id);
                if($corp_type['o2o_pickup'] || $corp_type['o2o_ship'])
                {
                    $name = $val['name'];
                }
            }

            $corp_rule_list[$val['corp_id']]['name'] = $name;
            $corp_rule_list[$val['corp_id']]['type'] = $val['type'];
            $corp_rule_list[$val['corp_id']]['shop_id'] = $val['shop_id'];
            $corp_rule_list[$val['corp_id']]['tmpl_type'] = $val['tmpl_type'];
            $corp_rule_list[$val['corp_id']]['d_type'] = $val['d_type'];
        }

        // 得物品牌直发，需要判断物流公司
        if (strtolower($shop_type) == 'dewu' && kernel::single('ome_order_bool_type')->isDWBrand($oneOrder['order_bool_type'])) {
            $dewu_corp_list = kernel::single('logisticsmanager_waybill_dewu')->logistics();
            unset($dewu_corp_list['VIRTUAL']); // 品牌直发不能用虚拟发货，虚拟发货是急速现货用的

            $dewu_channel   = app::get('logisticsmanager')->model('channel')->getList('channel_id', ['channel_type'=>'dewu', 'logistics_code|noequal'=>'VIRTUAL']);

            $orderExtend   = app::get('ome')->model('order_extend')->db_dump(['order_id'=>$order_id]);
            $orderExtend['extend_field'] && $orderExtend['extend_field'] = json_decode($orderExtend['extend_field'], 1);
            foreach ($corp_rule_list as $k => $v) {
                if (!in_array($v['type'], array_keys($dewu_corp_list))) {
                    unset($corp_rule_list[$k]);
                    continue;
                }

                // 商家指定物流不需要取号，所以可以选非得物的物流公司
                if (!in_array($v['channel_id'], array_column($dewu_channel, 'channel_id')) && $orderExtend['extend_field']['performance_type'] != '2') {
                    unset($corp_rule_list[$k]);
                }
            }
        }
    
        if (in_array(strtolower($shop_type), ['luban'])) {
            list($is_specify, $corp_ids) = kernel::single('logistics_rule')->getSelfSelectedLogistics($order_id, $oneOrder['shipping'],$shop_type);
            if ($is_specify) {
                if (!$corp_ids) {
                    return [];//指定物流匹配失败
                }
                foreach ($corp_rule_list as $k => $v) {
                    if (!in_array($k,$corp_ids)) {
                        unset($corp_rule_list[$k]);
                        continue;
                    }
                }
            }
        }
    
        //工小达
        if (kernel::single('ome_bill_label')->getBillLabelInfo($order_id, 'order', kernel::single('ome_bill_label')->isSomsGxd())) {
            $extendInfo         = $orderExtendObj->db_dump(array('order_id' => $order_id), 'order_id,biz_delivery_code,white_delivery_cps');
            $white_delivery_cps = json_decode($extendInfo['white_delivery_cps'], true);
        
            if (!$white_delivery_cps) {
                return [];//未取到建议物流
            }
        
            $corpData = kernel::single('logistics_rule')->getChannelCorpList('jdgxd');
            if (!$corpData) {
                return [];//物流匹配失败
            }
    
            foreach ($corpData as $key => $val) {
                if (!in_array($val['type'], (array)$white_delivery_cps)) {
                    unset($corpData[$key]);
                    continue;
                }
            }
    
            $corp_ids = array_column($corpData, 'corp_id');
            foreach ($corp_rule_list as $k => $v) {
                if (!in_array($k, $corp_ids)) {
                    unset($corp_rule_list[$k]);
                    continue;
                }
            }
        }
        
        if($corp_rule_list){
            sort($corp_rule_list);
    
            $corp_rule_list= $this->sysSortArray($corp_rule_list,'weight',"SORT_DESC","SORT_NUMERIC");
    
        }
        return $corp_rule_list;
    }

	/*检查仓库名是否存在*/
	function namecheck($name)
	{
		$row = $this->getList('branch_id',array('name'=>$name));
		if($row) return $row[0]['branch_id'];
		else return false;
	}

    #获取快递鸟物流推荐
    function get_exrecommend($branch_id,$area='',$weight,$shop_type,$shop_id,&$order_data='',&$waybill_number) {
        $sqlstr = '';
        $shop_data = array('DANGDANG','AMAZON');
        $shop_type = strtoupper($shop_type);
        #非亚马逊或非当当的店铺，只能选择通用的物流公司
        if (!in_array($shop_type,$shop_data)) {
            $shop_data = implode('\',\'',$shop_data );
            $sqlstr.=' AND `type` not in (\''.$shop_data.'\')';
        }else{
            #亚马逊或当当的店铺，则只能显示他自己的物流公司和通用的物流公司
            $tmp_shop_type = array($shop_type);
            $shop_diff = array_diff($shop_data,$tmp_shop_type);
            $shop_diff = implode('\',\'',$shop_diff );
            $sqlstr.=' AND `type` not in (\''.$shop_diff.'\')';
        }

        $channel_type = 'taobao';


        $exrecommend_corps =  kernel::single('ome_event_trigger_exrecommend_recommend')->exrecommend($branch_id,$order_data);

        if(empty($exrecommend_corps))return false;
        #使用了淘宝智选物流，会返回电子面单号
        $waybill_number = $exrecommend_corps[$channel_type]['waybill_code']?$exrecommend_corps[$channel_type]['waybill_code']:NULL;

        //根据仓库获取指定的物流
        $branch_corp_lib = kernel::single("ome_branch_corp");
        $corp_ids = $branch_corp_lib->getCorpIdsByBranchId(array($branch_id));
        $sql = "SELECT corp_id,name,type,shop_id,tmpl_type,channel_id,corp_model FROM sdb_ome_dly_corp
                WHERE corp_id IN(" . implode(",", $corp_ids) . ") and disabled='false'".$sqlstr;
        #重新获取相关ERP物流
        $erp_corp = $this->db->select($sql);
        $jdCorpConf =  app::get('ome')->getConf('shop.jdcorp.config.'.$shop_id);
        #判断是否是京东货到付款是否启用京东面单
        if ($shop_type == '360BUY'){
            $jdCorpConf =  app::get('ome')->getConf('shop.jdcorp.config.'.$shop_id);
            #开启了京配
            if ($jdCorpConf['config'] == '1') {
                $arrCorpId = array();
                foreach($erp_corp as $val) {
                    $arrCorpId[] = $val['corp_id'];
                }
                if(in_array($jdCorpConf['corp_id'], $arrCorpId)) {
                    #京东货到付款订单，默认使用京配物流
                    if ($is_cod == 'true') {
                        $defaultCorpId = $jdCorpConf['corp_id'];
                    } else {
                        #京东款到货发也启用京配物流
                        if ($jdCorpConf['is_cod'] == '1') {
                            $defaultCorpId = $jdCorpConf['corp_id'];
                        }
                    }
                }
            }
        }
        $branch_region = explode(":", $area);
        $corp_rule_list = array();

        foreach($erp_corp as $key=>$val){

            $corp_id = $val['corp_id'];

            $corp_rule_list[$corp_id] = $val;
            $flag='';
            if($exrecommend_corps['taobao']){
                #淘宝的智选物流,需要erp自行计算运费
                $cost_freight = $this->app->model('delivery')->getDeliveryFreight($branch_region[2],$val['corp_id'],$weight);
            }
            if($val['tmpl_type']=='electron') {
                $electron='( 电 )';
            }
            #京东的如果设置了京配，优先京东
            if($corp_id == $defaultCorpId){
                $flag='(默认)';
                $corp_rule_list[$val['corp_id']]['flag_select']=1;
            }
            if((!$defaultCorpId) ){
               #智选物流,推荐的物流
                if($exrecommend_corps[$channel_type]['corp_id_list'][$corp_id]['selected'] == 'true'){
                    #快递鸟根据用户所选择的策略算出来的
                    $flag = '<font color="#dd1d1d">(智)</font>';
                    $corp_rule_list[$val['corp_id']]['flag_select']=1;
                }
            }
            if($cost_freight == 0){
                $cost_freight='运费未设置';
            }else{
                $cost_freight = '￥'.sprintf("%.2f",$cost_freight);
            }

            $name = $val['name'].$electron.'：'.$cost_freight.$flag;
            $corp_rule_list[$corp_id]['name'] = $name;
            $corp_rule_list[$corp_id]['type'] = $val['type'];
            $corp_rule_list[$corp_id]['shop_id'] = $val['shop_id'];
            $corp_rule_list[$corp_id]['tmpl_type'] = $val['tmpl_type'];
        }

        return $corp_rule_list;
    }

    public function getChannelBybranchID($branch_id){
        if(!is_numeric($branch_id)){
            return '';
        }
        $sql = "SELECT c.node_type FROM sdb_ome_branch as b LEFT JOIN sdb_channel_channel as c ON b.wms_id=c.channel_id WHERE b.branch_id=".$branch_id;
        $branch_detail = $this->db->selectrow($sql);
        return $branch_detail['node_type'];
    }


    function modifier_type($row){
        if($row){
            $types = $this->getBranchtype($row);

            return $types;
        }
    }

    public function getBranchtype($type_code){

        $typeMdl = app::get('ome')->model('branch_type');

        $types = $typeMdl->db_dump(array('type_code'=>$type_code),'type_name,type_code');

        return $types['type_name'];
    }

    /**
     * 获取用户的直接仓权限（原有逻辑）
     * @param int $op_id 操作员ID
     * @param string $type 类型
     * @return array branch_id数组
     */
    private function getDirectBranchIds($op_id, $type = 'online') {
        if (empty($op_id)) {
            return [];
        }
        
        $oBops = $this->app->model('branch_ops');
        
        if($type=='online'){
            $filter = array('op_id' => $op_id,'b_type'=>1);
        }elseif($type=='offline'){
            $filter = array('op_id' => $op_id,'b_type'=>2);
        }else{
            $filter = array('op_id' => $op_id);
        }

        $bops_list = $oBops->getList('branch_id', $filter, 0, -1);
        $bps = [];
        if ($bops_list) {
            foreach ($bops_list as $k => $v) {
                $bps[] = $v['branch_id'];
            }
        }
        
        return $bps;
    }
    
    /**
     * 获取用户继承的组织权限（仅适用于门店）
     * @param int $op_id 操作员ID
     * @param string $type 类型（固定为offline，因为组织权限继承只适用于门店）
     * @return array branch_id数组
     */
    private function getInheritedBranchIds($op_id, $type = 'offline') {
        if (empty($op_id)) {
            return [];
        }
        
        // 检查 organization 应用是否安装
        if (!app::get('organization')->is_installed()) {
            return [];
        }
        
        try {
            // 使用权限继承服务类
            $permissionService = kernel::single('organization_organization_permission');
            return $permissionService->expandUserBranchIds($op_id, $type);
            
        } catch (Exception $e) {
            return [];
        }
    }

}

?>
