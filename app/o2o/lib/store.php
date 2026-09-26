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
 * 门店数据处理Lib类
 *
 * @access public
 * @author wangbiao<wangbiao@shopex.cn>
 * @version $Id: import.php 2016-07-26 15:00
 */
class o2o_store
{
    /**
     * create_store 门店创建
     * 
     * @param $sdf
     * @return sdf
     */

    function create_store($sdf, &$errmsg)
    {
        $storeObj         = app::get('o2o')->model('store');
        $organizationObj  = app::get('organization')->model('organization');
        $serverObj        = app::get('o2o')->model('server');
        $regionObj        = app::get('o2o')->model('store_regions');
        
        $smsLib        = kernel::single('taoexlib_request_sms');
        $channelLib    = kernel::single('channel_interface_channel');
        $branchLib     = kernel::single('ome_interface_branch');
        $shopLib       = kernel::single('ome_interface_shop');
        $regionLib     = kernel::single('o2o_store_regions');
        
        //开启事务
        $storeObj->db->beginTransaction();
        
        // 如果状态为关闭(2)，检查门店是否还有库存
        if ($sdf['status'] == 2) {
            $hasStock = $this->checkStoreHasStock($sdf['org_no']);
            if ($hasStock) {
                $storeObj->db->rollBack();
                $errmsg .= '此门店下还有库存，关店失败，门店编码：' . $sdf['org_no'] . '。';
                return false;
            }
        }
        
        // 处理经销商层级逻辑
        $dealerOrgId = null;
        $finalParentId = $sdf['parent_id'];
        $finalOrgLevelNum = $sdf['org_level_num'];
        
        if (!empty($sdf['dealer_cos_id'])) {
            // 查询经销商信息
            $cosMdl = app::get('organization')->model('cos');
            $dealerCos = $cosMdl->dump(['cos_id' => $sdf['dealer_cos_id']], 'cos_code,cos_name');
            
            if ($dealerCos) {
                // 检查经销商是否已经在organization表中存在
                $dealerOrg = $organizationObj->dump(['org_no' => 'BS_' . $dealerCos['cos_code']]);
                
                if (!$dealerOrg) {
                    // 创建经销商组织记录
                    $dealerOrgData = array(
                        'org_name'              => $dealerCos['cos_name'],
                        'org_type'              => 3, // 经销商类型
                        'org_no'                => 'BS_' . $dealerCos['cos_code'], // 添加BS_前缀
                        'status'                => 1,
                        'area'                  => $sdf['area'],
                        'org_parents_structure' => $sdf['org_parents_structure'],
                        'parent_id'             => $finalParentId,
                        'org_level_num'         => $finalOrgLevelNum,
                    );
                    
                    if (!$organizationObj->save($dealerOrgData)) {
                        $storeObj->db->rollBack();
                        $errmsg .= '保存经销商组织信息失败，门店编码：' . $sdf['org_no'] . '。';
                        return false;
                    }
                    $dealerOrgId = $dealerOrgData['org_id'];
                } else {
                    $dealerOrgId = $dealerOrg['org_id'];
                }
                
                // 如果有经销商，门店的parent_id指向经销商，层级+1
                $finalParentId = $dealerOrgId;
                $finalOrgLevelNum = $finalOrgLevelNum + 1;
            }
        }
        
        //企业组织(注意：批量导入时必须填写所属组织)
        $org_sdf        = array(
                            'org_no' => $sdf['org_no'],
                            'org_name'=> $sdf['org_name'],
                            'org_type' => $sdf['org_type'],
                            'status' => $sdf['status'],
                            'area' => $sdf['area'],
                            'org_level_num' => $finalOrgLevelNum,
                            'org_parents_structure' => $sdf['org_parents_structure'],
                            'parent_id' => $finalParentId,
                         );
        $is_save    = $organizationObj->save($org_sdf);
        if(!$is_save)
        {
            #事务回滚
            $storeObj->db->rollBack();
            
            $errmsg    .= '保存企业组织出错,组织编码:'. $org_sdf['org_no'] .'。';
            
            return false;
        }
        
        //发送短信签名注册
        if (defined('APP_TOKEN') && defined('APP_SOURCE')) {
            base_kvstore::instance('taoexlib')->fetch('account', $account);
            if (unserialize($account)) {
                $sms_sign = '【'.$org_sdf['org_name'].'】';
                $smsLib->newoauth_request(array('sms_sign'=>$sms_sign));
            }
        }
        
        //如果设定了门店服务端类型获取对应的虚拟仓储类型
        $now_wms_id    = 0;
        if($sdf['server_id']){
            $serverInfo = $serverObj->dump(array('server_id'=>$sdf['server_id']),'type');
            if($serverInfo['type']){
                $wms_type = $channelLib->dump(array('channel_type'=>'wms','node_type'=>$serverInfo['type']),'channel_id');
                $now_wms_id = $wms_type['channel_id'];
            }
        }
        
        //门店
        $store_sdf    = array(
                            'store_bn' => $sdf['org_no'],
                            'name' => $sdf['org_name'],
                            'server_id' => $sdf['server_id'],
                            'addr' => $sdf['addr'],
                            /*
                            'longitude' => $sdf['longitude'],
                            'latitude' => $sdf['latitude'],
                            */
                            'area' => $sdf['area'],
                            'zip' => $sdf['zip'],
                            'contacter' => $sdf['contacter'],
                            'mobile' => $sdf['mobile'],
                            'tel' => $sdf['tel'],
                            'store_type' => $sdf['store_type'],
                            /*
                            'self_pick' => $sdf['self_pick'],
                            'distribution' => $sdf['distribution'],
                            'aftersales' => $sdf['aftersales'],
                            'confirm' => $sdf['confirm'],
                            */
                            'status' => $sdf['status'],
                        );
        $is_save    = $storeObj->save($store_sdf);
        if(!$is_save)
        {
            #事务回滚
            $storeObj->db->rollBack();
            
            $errmsg    .= '保存门店信息出错,门店编码:'. $store_sdf['store_bn'] .'。';
            
            return false;
        }
        
        //生成对应的虚拟仓
        $new_branch = array(
                'branch_bn' => $sdf['org_no'],
                'name' => $sdf['org_name'],
                'storage_code' => $sdf['org_no'],
                'b_type' => 2,
                'b_status' => $sdf['status'],
                'wms_id' => $now_wms_id,
                'area'         => $sdf['area'],
                'address'      => $sdf['addr'],
                'zip'          => $sdf['zip'],
                'phone'        => $sdf['tel'],
                'uname'        => $sdf['contacter'],
                'mobile'       => $sdf['mobile'],
                'store_id'     => $store_sdf['store_id'],
        );
        $save_branch    = $branchLib->save($new_branch);
        if(!$save_branch)
        {
            #事务回滚
            $storeObj->db->rollBack();
            
            $errmsg    .= '门店关联虚拟仓保存失败,门店编码:'. $store_sdf['store_bn'] .'。';
            
            return false;
        }
        
        //生成对应的虚拟店铺
        $new_shop = array(
                'shop_bn' => $sdf['org_no'],
                'name' => $sdf['org_name'],
                's_type' => 2,
                's_status' => $sdf['status'],
        );
        $save_shop    = $shopLib->save($new_shop);
        if(!$save_shop)
        {
            #事务回滚
            $storeObj->db->rollBack();
            
            $errmsg    .= '门店关联线下店铺保存失败,门店编码:'. $store_sdf['store_bn'] .'。';
            
            return false;
        }
        
        //更新相关的仓库和店铺
        $update_data    = array('branch_id'=>$new_branch['branch_id'], 'shop_id'=>$new_shop['shop_id']);
        $update_store   = $storeObj->update($update_data, array('store_id'=>$store_sdf['store_id']));
        if(!$update_store)
        {
            #事务回滚
            $storeObj->db->rollBack();
            
            $errmsg    .= '门店关联仓库、店铺信息更新失败,门店编码:'. $store_sdf['store_bn'] .'。';
            
            return false;
        }
        
        #新增门店关联地区数据信息
        list($temp_package, $temp_region_name, $region_id)    = explode(':', $sdf['area']);
        $region_data    = $regionLib->getRegionById($region_id);
        
        $save_data = array(
                        'store_id' => $store_sdf['store_id'],
                        'region_1' => intval($region_data[1]),
                        'region_2' => intval($region_data[2]),
                        'region_3' => intval($region_data[3]),
                        'region_4' => intval($region_data[4]),
                        'region_5' => intval($region_data[5]),
                    );
        $save_region    = $regionObj->save($save_data);
        if(!$save_region)
        {
            #事务回滚
            $storeObj->db->rollBack();
            
            $errmsg    .= '门店关联地区信息保存失败,门店编码:'. $store_sdf['store_bn'] .'。';
            
            return false;
        }
        
        //如果最新保存的信息有父节点，更新父节点为有下级仓库或门店节点信息
        if($finalParentId > 0)
        {
            $p_org_info    = $organizationObj->dump(array('org_id'=>$finalParentId), 'haschild');
            
            $child_arr     = $organizationObj->getList('org_id', array('parent_id'=>$finalParentId, 'org_type'=>2), 0, -1);
            
            if(count($child_arr) > 0){
                $org_save_parent_data['haschild'] = $p_org_info['haschild'] | 2;
            }else{
                $org_save_parent_data['haschild'] = $p_org_info['haschild'] ^ 2;
            }
            
            $organizationObj->update($org_save_parent_data, array('org_id'=>$finalParentId));
        }
        
        //事务确认
        $storeObj->db->commit();
        
        return true;
    }
    
    /**
     * 门店数据有效性检查Lib类
     * @param unknown $params
     * @param unknown $err_msg
     * @return Array
     */
    public function checkAddParams(&$params, &$err_msg)
    {
        $organizationObj    = app::get('organization')->model('organization');
        
        //拆分组织经纬度信息
        if(isset($params['coordinate']))
        {
            $coordinate = explode(',',$params['coordinate']);
            $params['longitude'] = $coordinate[0];
            $params['latitude'] =  $coordinate[1];
            
            if(empty($params['longitude']) || empty($params['latitude']))
            {
                $err_msg    = '门店经纬度填写错误';
                return false;
            }
        }
        
        //表单验证
        if (strlen($params['zip']) <> '6') {
            $err_msg    = '请输入正确的邮编';
            return false;
        }
        
        //固定电话与手机必填一项
        $gd_tel = str_replace(" ", "", $params['tel']);
        $mobile = str_replace(" ", "", $params['mobile']);
        if (!$gd_tel && !$mobile) {
            $err_msg    = '固定电话与手机号码必需填写一项';
            return false;
        }
        
        $pattern = "/^400\d{7}$/";
        $pattern1 = "/^\d{1,4}-\d{7,8}(-\d{1,6})?$/i";
        if ($gd_tel) {
            $_rs = preg_match($pattern, $gd_tel);
            $_rs1 = preg_match($pattern1, $gd_tel);
            if ((!$_rs) && (!$_rs1)) {
                $err_msg    = '请填写正确的固定电话号码';
                return false;
            }
        }
        
        $pattern2 = "/^\d{8,15}$/i";
        if ($mobile) {
            if (!preg_match($pattern2, $mobile)) {
                $err_msg    = '请输入正确的手机号码';
                return false;
            }
            if ($mobile[0] == '0') {
                $err_msg    = '手机号码前请不要加0';
                return false;
            }
        }
        
        //识别是新增还是编辑
        $params['is_new_add']    = intval($params['org_id']) ? false : true;
        
        if($params['is_new_add']){
            $check_org_no_exist = $organizationObj->dump(array("org_no"=>$params["org_no"]),"org_id");
            if($check_org_no_exist){
                $err_msg    = '新增的编码已经存在';
                return false;
            }
            
            $check_org_name_exist = $organizationObj->dump(array("org_name"=>$params["org_name"]),"org_id");
            if($check_org_name_exist){
                $err_msg    = '新增的名称已经存在';
                return false;
            }
            
        }else{
            $check_org_no_exist = $organizationObj->dump(array("org_no"=>$params["org_no"]),"org_id");
            if($check_org_no_exist && $check_org_no_exist['org_id'] != $params['org_id']){
                $err_msg    = '编辑的编码已经存在';
                return false;
            }
            
            $check_org_name_exist = $organizationObj->dump(array("org_name"=>$params["org_name"]),"org_id");
            if($check_org_name_exist && $check_org_name_exist['org_id'] != $params['org_id']){
                $err_msg = '编辑的名称已经存在';
                return false;
            }
            
            //编辑的时候，取一下门店原有的信息
            $old_org_info = $organizationObj->dump(array("org_id"=>$params['org_id']),"org_id,parent_id");
            $params['old_org_info']    = $old_org_info;
        }
        
        return true;
    }

    //根据门店编码找到对应的仓库ID
    /**
     * 获取BranchIdByStoreBn
     * @param mixed $store_bn store_bn
     * @return mixed 返回结果
     */
    public function getBranchIdByStoreBn($store_bn){
        $storeObj = app::get('o2o')->model('store');
        $storeInfo = $storeObj->getList('branch_id',array('store_bn'=>$store_bn), 0, 1);
        if($storeInfo){
            return $storeInfo[0]['branch_id'];
        }
    }
    
    /**
     * 根据订单扩展表上的门店编码查找到门店信息
     * 
     * @param intval $order_id 订单号
     * @return Array
     */
    public function getOrderIdByStore($order_id)
    {
        $o2o_order    = array();
        $o2o_order['is_omnichannel']    = true;
        
        //指定门店
        $orderExtendObj    = app::get('ome')->model('order_extend');
        $ordExtRow         = $orderExtendObj->dump(array('order_id'=>$order_id), 'order_id, store_dly_type, store_bn');
        
        $store_bn          = $ordExtRow['store_bn'];
        $store_dly_type    = $ordExtRow['store_dly_type'];
        // 与 omeauto/orderextend 一致：1=门店自提(o2o_pickup)，2=门店配送(o2o_ship)
        $dly_corp_type     = ($store_dly_type == 1 ? 'o2o_pickup' : 'o2o_ship');
        
        if($store_dly_type && $store_bn)
        {
            //默认选择的门店物流公司
            $corpObj    = app::get('ome')->model('dly_corp');
            $corpRow    = $corpObj->dump(array('type'=>$dly_corp_type, 'd_type'=>2), 'corp_id');
            
            $o2o_order['select_corp_id']  = $corpRow['corp_id'];
            
            //根据订单扩展表上的门店编码查找到门店信息
            $o2oStoreObj = app::get('o2o')->model('store');
            $storeRow    = $o2oStoreObj->dump(array('store_bn'=>$store_bn), 'store_id, name, branch_id, area');
            
            $o2o_order['store_bn']    = $store_bn;
            $o2o_order['branch_id']   = $storeRow['branch_id'];
            $o2o_order['store_area']  = $storeRow['area'];
            
            $store_msg    = array(
                1 => '客户要求指定到【{store_name}】门店自提',
                2 => '客户要求指定【{store_name}】门店进行配送',
            );
            if (isset($store_msg[$store_dly_type])) {
                $o2o_order['store_recommend_msg']  = str_replace('{store_name}', $storeRow['name'], $store_msg[$store_dly_type]);
            }
        }
        
        return $o2o_order;
    }

    /**
     * undocumented function
     * 
     * @return void
     * @author
     * */
    public function create($post)
    {
        // 前后去空格
        foreach ($post as $key => $value) {
            if (is_string($value)) {
                $post[$key] = trim($value);
            }
        }

        // 手机号验证
        $mobile = str_replace(" ", "", $post['mobile']);
        if (!empty($mobile)) {
            if (!kernel::single('ome_func')->isMobile($mobile)) {
                return [false, '请输入正确的手机号码'];
            }
        }

        $store_id = (int) $post['store_id'];

        // 如果状态为关闭(2)，检查门店是否还有库存
        if ($post['status'] == 2) {
            $hasStock = $this->checkStoreHasStock($post['store_bn']);
            if ($hasStock) {
                return [false, '此门店下还有库存，关店失败'];
            }
        }

        // 更新企业结构
        $orgMdl   = app::get('organization')->model('organization');
        $storeMdl = app::get('o2o')->model('store');

        $org = $orgMdl->dump(['org_no' => $post['store_bn']]);

        if (!$store_id && $org) {
            $msg = $org['org_type'] == '2' ? '门店编码已经存在' : '此编码被企业组织占用';

            return [false, $msg];
        }

       if($post['store_type'] == 'join' && empty($post['dealer_cos_id'])) {
            return [false, '经营模式为加盟时,经销商信息不能为空'];
       }

        // 处理经销商层级逻辑
        $dealerOrgId = null;
        if (!empty($post['dealer_cos_id'])) {
            // 查询经销商信息
            $cosMdl = app::get('organization')->model('cos');
            $dealerCos = $cosMdl->dump(['cos_id' => $post['dealer_cos_id']], 'cos_code,cos_name');
            
            if ($dealerCos) {
                // 检查经销商是否已经在organization表中存在
                $dealerOrg = $orgMdl->dump(['org_no' => 'BS_' . $dealerCos['cos_code']]);
                
                if (!$dealerOrg) {
                    // 创建经销商组织记录
                    list(, , $originalParentId) = explode(':', $post['org_parents_structure']);
                    $pOrg = $orgMdl->dump($originalParentId, 'org_level_num');
                    
                    $dealerOrgData = array(
                        'org_name'              => $dealerCos['cos_name'],
                        'org_type'              => 3, // 经销商类型
                        'org_no'                => 'BS_' . $dealerCos['cos_code'], // 添加BS_前缀
                        'status'                => 1,
                        'area'                  => $post['area'],
                        'org_parents_structure' => $post['org_parents_structure'],
                        'parent_id'             => $originalParentId,
                        'org_level_num'         => $pOrg['org_level_num'] + 1,
                    );
                    
                    if (!$orgMdl->save($dealerOrgData)) {
                        return [false, '保存经销商组织信息失败：' . $orgMdl->db->errorinfo()];
                    }
                    $dealerOrgId = $dealerOrgData['org_id'];
                } else {
                    $dealerOrgId = $dealerOrg['org_id'];
                }
            }
        }

        $upOrgData = array(
            'org_id'                => (int) $org['org_id'],
            'org_name'              => $post["name"],
            'org_type'              => 2,
            'org_no'                => $post["store_bn"],
            'status'                => $post['status'],
            'area'                  => $post['area'],
            'org_parents_structure' => $post['org_parents_structure'],
        );

        // 如果有经销商，门店的parent_id指向经销商，否则指向原来的所属组织
        if ($dealerOrgId) {
            $upOrgData['parent_id'] = $dealerOrgId;
            $pOrg = $orgMdl->dump($dealerOrgId, 'org_level_num');
            $upOrgData['org_level_num'] = $pOrg['org_level_num'] + 1;
        } else {
            list(, , $upOrgData['parent_id']) = explode(':', $post['org_parents_structure']);
            $pOrg = $orgMdl->dump($upOrgData['parent_id'], 'org_level_num');
            $upOrgData['org_level_num'] = $pOrg['org_level_num'] + 1;
        }

        if (!$orgMdl->save($upOrgData)) {
            return [false, $orgMdl->db->errorinfo()];
        }

        // 保存门店
        $store = $storeMdl->dump(['store_bn' => $post['store_bn']]);
        if (!$store_id && $store) {
            return [false, '门店编码已经存在'];
        }
        $snapshoot = $store ?: [];

        $post['store_id'] = $store_id;
        if (!$store_id) {
            $post['create_time'] = time();
        }
        $post['sync_status'] = '0';
        if (!$storeMdl->save($post)) {
            return [false, $storeMdl->db->errorinfo()];
        }

        $server = app::get('o2o')->model('server')->dump($post['server_id']);

        // 保存店铺
        $upShopData = array(
            'shop_bn'   => $post['store_bn'],
            'name'      => $post['name'],
            's_type'    => 2,
            's_status'  => $post['status'],
            'node_type' => $server['node_type'],
            'shop_type' => $server['node_type'],
            'node_id'   => $post['store_bn'],
           
        );

      

        $shop = app::get('ome')->model('shop')->dump([
            'shop_bn' => $post['store_bn'],
            's_type'  => 2,
        ]);
        $upShopData['shop_id'] = $shop['shop_id'];

        $rs = kernel::single('ome_interface_shop')->save($upShopData);
        if (!$rs) {
            return [false, '门店关联线下店铺保存失败，编码可能被占用'];
        }

        // 验证仓库编号格式
        if (!empty($post['store_bn'])) {
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $post['store_bn'])) {
                return [false, '仓库编号只允许输入英文字母、数字、下划线和横线'];
            }
        }
        
        // 验证库内存放点编号格式
        if (!empty($post['storage_codes']['main'])) {
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $post['storage_codes']['main'])) {
                return [false, '库内存放点编号只允许输入英文字母、数字、下划线和横线'];
            }
        }
        
        // 主仓
        $upBranchData = array(
            'branch_bn'    => $post['store_bn'],
            'name'         => $post['name'],
            'storage_code' => $post['storage_codes']['main'],
            'b_type'       => 2,
            'b_status'     => $post['status'],
            'weight'       => $post['priority'],
            'area'         => $post['area'],
            'address'      => $post['addr'],
            'zip'          => $post['zip'],
            'phone'        => $post['tel'],
            'uname'        => $post['contacter'],
            'mobile'       => $post['mobile'],
            'store_id'     => $post['store_id'],
            // 'is_ctrl_store' => $post['is_ctrl_store'],
        );
       

        $branch = app::get('ome')->model('branch')->dump([
            'branch_bn'        => $post['store_bn'],
            'b_type'           => 2,
            'check_permission' => 'false',
        ]);
        $upBranchData['branch_id'] = (int) $branch['branch_id'];

        if ($upBranchData['branch_id']) {
            // 更新
            $rs = kernel::single('ome_interface_branch')->update($upBranchData,[
                'branch_id' => $upBranchData['branch_id'],
            ]);

        } else {
            // 插入
            $rs = kernel::single('ome_interface_branch')->save($upBranchData);
        }
        if (!$rs) {
            return [false, sprintf('门店关联虚拟仓保存失败：%s', kernel::database()->errorinfo())];
        }

        kernel::single('console_map_branch')->getLocation($upBranchData['branch_id']);

        kernel::single('ome_shop_onoffline')->doSave($upShopData['shop_id'], $post['online_id']);

      
        //更新相关的仓库和店铺
        $rs = $storeMdl->update([
            'branch_id' => $upBranchData['branch_id'],
            'shop_id'   => $upShopData['shop_id'],
        ], ['store_id' => $post['store_id']]);
        if (!$rs) {
            return [false, '门店关联仓库、店铺信息更新失败'];
        }

        // 自动权限继承：当新门店添加到经销商时，自动继承权限
        if (!$store_id && $dealerOrgId) { // 只在新建门店且有经销商的情况下执行
            $this->autoInheritStorePermission($dealerOrgId, $upOrgData['org_id']);
        }
        
        // 记录操作日志
        $log_id = app::get('ome')->model('operation_log')->write_log('store_upsert@o2o', $post['store_id'], ($store_id ? '编辑' : '创建') . '门店');
        if ($log_id && $store_id && $snapshoot) {
            $shootMdl  = app::get('ome')->model('operation_log_snapshoot');
            $snapshoot = json_encode($snapshoot, JSON_UNESCAPED_UNICODE);
            $tmp       = ['log_id' => $log_id, 'snapshoot' => $snapshoot];
            $shootMdl->insert($tmp);
        }
        return [true, '保存成功'];
    }


        /**
     * 获取BranchType
     * @return mixed 返回结果
     */
    public function getBranchType()
    {

        $typeMdl = app::get('ome')->model('branch_type');

        $types = $typeMdl->getlist('*',array('source'=>'create'));

        $branch_types = [];

        foreach($types as $v){
            $branch_types[$v['type_code']] = [

                'text'      => $v['type_name'],
                'type_code' => $v['type_code'], 
            ];
        }
    
        return $branch_types;
    }

    /**
     * 保存BranchType
     * @param mixed $store store
     * @param mixed $branch_type branch_type
     * @return mixed 返回操作结果
     */
    public function saveBranchType($store, $branch_type)
    {
        
        $branch_types = $this->getBranchType();
        if (!$branch_types[$branch_type]) {
            return [false, '库存类型不支持'];
        }

        if (!$store['store_id']) {
            return [false, '门店ID为空'];
        }

        if (!$store['store_bn']) {
            return [false, '门店编码为空'];
        }
        $branch_bn = $store['store_bn'];

        $prefix = strtolower($branch_types[$branch_type]['type_code']);

        if ($prefix) {
            $branch_bn = $store['store_bn'] . '_' . $prefix;
        }

        $branchList = kernel::single('ome_interface_branch')->getList('*', [
            'store_id'         => $store['store_id'],
            'type'             => $branch_type,
            'branch_bn'        => $branch_bn,
            'b_type'           => '2',
            'check_permission' => 'false',
        ], 0, 1);
        $branch = current($branchList);

        // 验证仓库编号格式
        if (!empty($branch_bn)) {
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $branch_bn)) {
                return [false, '仓库编号只允许输入英文字母、数字、下划线和横线'];
            }
        }
        
        // 验证库内存放点编号格式
        if (!empty($store['storage_code'])) {
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $store['storage_code'])) {
                return [false, '库内存放点编号只允许输入英文字母、数字、下划线和横线'];
            }
        }
        
        $data = array(
            'branch_bn'    => $branch_bn,
            'name'         => sprintf('[%s]%s', $branch_types[$branch_type]['text'], $store['name']),
            'storage_code' => $store['storage_code'] ?: $store['store_bn'],
            'type'         => $branch_type,
            'b_type'       => 2,
            'b_status'     => $store['status'],
            'weight'       => $store['priority'],
            'area'         => $store['area'],
            'address'      => $store['addr'],
            'zip'          => $store['zip'],
            'phone'        => $store['tel'],
            'uname'        => $store['contacter'],
            'mobile'       => $store['mobile'],
            'store_id'     => $store['store_id'],
            'branch_id'    => (int) $branch['branch_id'],
        );

        // 仓库名拼接上库位
        if ($data['storage_code']) {
            $data['name'] .= '-'.$data['storage_code'];
        }
       
        if ($branch['branch_id']) {
            // 更新
            $rs = kernel::single('ome_interface_branch')->update($data,[
                'branch_id' => $branch['branch_id']
            ]);
        } else {
            // 插入
            $rs = kernel::single('ome_interface_branch')->save($data);
        }

        if (!$rs) {
            return [false, kernel::database()->errorinfo()];
        }

        return [true];
    }
    
    /**
     * 自动权限继承：当新门店添加到经销商时，自动继承权限
     * @param int $dealerOrgId 经销商组织ID
     * @param int $newStoreOrgId 新门店组织ID
     * @return bool 操作结果
     */
    private function autoInheritStorePermission($dealerOrgId, $newStoreOrgId) {
        if (empty($dealerOrgId) || empty($newStoreOrgId)) {
            return false;
        }
        
        // 检查 organization 应用是否安装
        if (!app::get('organization')->is_installed()) {
            return true;
        }
        
        try {
            // 使用权限继承服务类
            $permissionService = kernel::single('organization_organization_permission');
            $result = $permissionService->autoInheritPermissionForNewStore($dealerOrgId, $newStoreOrgId);
            
                    return $result;
        
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 根据门店仓 branch_id 获取接单量上限，0 表示不限制
     *
     * @param int $branch_id
     * @return int
     */
    public function getAcceptOrderLimitByBranchId($branch_id)
    {
        $branch_id = (int) $branch_id;
        if ($branch_id <= 0) {
            return 0;
        }

        $storeObj = app::get('o2o')->model('store');
        $store    = $storeObj->db_dump(array('branch_id' => $branch_id), 'accept_order_limit');
        if (empty($store)) {
            return 0;
        }

        return max(0, (int) $store['accept_order_limit']);
    }

    /**
     * 统计门店仓当日派单量（KV 净派单：下发 +1，撤销/拒单 -1）
     *
     * @param int $branch_id
     * @return int
     */
    public function getTodayAcceptedOrderCount($branch_id)
    {
        $branch_id = (int) $branch_id;
        if ($branch_id <= 0) {
            return 0;
        }

        if (!app::get('wap')->is_installed()) {
            return 0;
        }

        return kernel::single('wap_branch_daily_counter')->getDispatchCount($branch_id);
    }

    /**
     * 在候选门店仓中按当日派单量最低优先、相同随机选取
     *
     * @param array $branchIds
     * @return int
     */
    public function pickBranchByLowestDispatch(array $branchIds)
    {
        if (!app::get('wap')->is_installed()) {
            $branchIds = array_values(array_filter(array_map('intval', $branchIds)));
            return !empty($branchIds) ? $branchIds[0] : 0;
        }

        return kernel::single('wap_branch_daily_counter')->pickBranchByLowestDispatch($branchIds);
    }

    /**
     * 门店仓当日接单量是否已达上限
     *
     * @param int $branch_id
     * @return bool true=已超限需跳过该门店
     */
    public function isAcceptOrderLimitExceeded($branch_id)
    {
        $limit = $this->getAcceptOrderLimitByBranchId($branch_id);
        if ($limit <= 0) {
            return false;
        }

        return $this->getTodayAcceptedOrderCount($branch_id) >= $limit;
    }

    /**
     * 过滤已达接单量上限的门店仓
     *
     * @param array $branchIds
     * @return array
     */
    public function filterBranchIdsByAcceptOrderLimit($branchIds)
    {
        if (empty($branchIds)) {
            return array();
        }

        $available = array();
        foreach ((array) $branchIds as $branchId) {
            if (!$this->isAcceptOrderLimitExceeded($branchId)) {
                $available[] = $branchId;
            }
        }

        return $available;
    }

    /**
     * 获取订单已拒单的门店编码（store_bn，与 branch_bn 一致）
     *
     * @param array|int $orderIds
     * @return array
     */
    public function getRefusedStoreBnsByOrderIds($orderIds)
    {
        if (empty($orderIds)) {
            return array();
        }
        $orderIds = array_values(array_unique(array_filter((array) $orderIds)));
        if (empty($orderIds)) {
            return array();
        }

        return (array) app::get('o2o')->model('store_refuse_analysis')->get_refuse_stores($orderIds);
    }

    /**
     * 门店仓是否已被当前订单拒单
     *
     * @param int       $branch_id
     * @param array|int $orderIds
     * @return bool
     */
    public function isBranchRefusedByOrder($branch_id, $orderIds)
    {
        $refusedStoreBns = $this->getRefusedStoreBnsByOrderIds($orderIds);
        if (empty($refusedStoreBns)) {
            return false;
        }

        $branch_id = (int) $branch_id;
        if ($branch_id <= 0) {
            return false;
        }

        $branchRow = app::get('ome')->model('branch')->db_dump($branch_id, 'branch_bn');
        if (empty($branchRow['branch_bn'])) {
            return false;
        }

        return in_array($branchRow['branch_bn'], $refusedStoreBns, true);
    }

    /**
     * 过滤本订单已拒单的门店仓
     *
     * @param array     $branchIds
     * @param array|int $orderIds
     * @return array
     */
    public function filterBranchIdsByOrderRefuse($branchIds, $orderIds)
    {
        if (empty($branchIds)) {
            return array();
        }

        $refusedStoreBns = $this->getRefusedStoreBnsByOrderIds($orderIds);
        if (empty($refusedStoreBns)) {
            return (array) $branchIds;
        }

        $branchRows = kernel::single('ome_interface_branch')->getList(
            'branch_id,branch_bn',
            array('branch_id' => $branchIds),
            0,
            -1
        );
        $allowedMap = array();
        foreach ((array) $branchRows as $row) {
            if (!in_array($row['branch_bn'], $refusedStoreBns, true)) {
                $allowedMap[$row['branch_id']] = true;
            }
        }

        $available = array();
        foreach ((array) $branchIds as $branchId) {
            if (!empty($allowedMap[$branchId])) {
                $available[] = $branchId;
            }
        }

        return $available;
    }

    /**
     * 门店审单候选仓过滤：接单量 + 本订单已拒单门店
     *
     * @param array                        $branchIds
     * @param array|int                    $orderIds
     * @return array
     */
    public function filterBranchIdsForStoreConfirm($branchIds, $orderIds)
    {
        $branchIds = $this->filterBranchIdsByAcceptOrderLimit((array) $branchIds);
        return $this->filterBranchIdsByOrderRefuse($branchIds, $orderIds);
    }

    /**
     * 获取所有参与O2O的门店branch_id
     * 用于仓库分配规则中bid=-1的特殊情况
     * 
     * @return array 返回满足条件的门店branch_id数组
     */
    public function getAllO2OStoreBranchIds()
    {
        // 从O2O_STORE表查询is_o2o=1的branch_id
        $o2oStoreObj = app::get('o2o')->model('store');
        $o2oStores = $o2oStoreObj->getList('branch_id', array('is_o2o' => '1'));
        
        if (empty($o2oStores)) {
            return array();
        }
        
        $o2oBranchIds = array_column($o2oStores, 'branch_id');
        if (empty($o2oBranchIds)) {
            return array();
        }
        
        // 根据branch_id查询ome_branch表，获取满足条件的门店
        $branchObj = app::get('ome')->model('branch');
        $storeBranches = $branchObj->getList('branch_id', array(
            'branch_id' => $o2oBranchIds,
            'b_type' => '2',
            'disabled' => 'false',
            'is_deliv_branch' => 'true',
            'check_permission' => 'false',
            'b_status' => '1', // 门店仓状态为启用
        ));
        
        if (empty($storeBranches)) {
            return array();
        }
        
        return array_column($storeBranches, 'branch_id');
    }
    
    /**
     * 更新经销商状态，同时更新其下所有门店状态
     * 
     * @param string $status 状态 (active/close)
     * @param string $bsBn 经销商编码
     * @param array &$errorMsg 错误信息（引用传递）
     * @return bool 操作结果
     */
    public function updateBsStoreStatus($status, $bsBn, &$errorMsg = '')
    {
        try {
            // 开启事务
            $db = kernel::database();
            $tran = $db->beginTransaction();

            // 状态映射：active=1(激活), close=2(关闭)
            $statusValue = ($status === 'active') ? 1 : 2;
            
            // 更新经销商下所有门店状态
            $organizationObj = app::get('organization')->model('organization');
            $orgNo = 'BS_' . $bsBn;
            $dealerOrg = $organizationObj->dump(['org_no' => $orgNo, 'org_type' => 3], 'org_id');

            if ($dealerOrg) {
                // 查找经销商下的所有门店组织
                $storeOrgs = $organizationObj->getList('org_id,org_no', [
                    'parent_id' => $dealerOrg['org_id'],
                    'org_type' => 2 // 门店类型
                ]);
                
                if (!empty($storeOrgs)) {
                    $storeBns = array_column($storeOrgs, 'org_no');
                    $storeOrgIds = array_column($storeOrgs, 'org_id');
                    
                    // 如果要关闭门店，先检查每个门店是否还有库存
                    if ($statusValue == 2) {
                        $storesWithStock = array();
                        foreach ($storeBns as $storeBn) {
                            if ($this->checkStoreHasStock($storeBn)) {
                                $storesWithStock[] = $storeBn;
                            }
                        }
                        
                        if (!empty($storesWithStock)) {
                            $db->rollBack();
                            $errorMsg = '以下门店还有库存，无法关闭：' . implode('、', $storesWithStock);
                            return false;
                        }
                    }
                    
                    // 更新门店组织状态
                    $organizationObj->update(
                        ['status' => $statusValue],
                        ['org_id|in' => $storeOrgIds]
                    );
                    
                    // 更新O2O门店状态
                    $storeObj = app::get('o2o')->model('store');
                    $stores = $storeObj->getList('store_id,branch_id,shop_id,store_bn,status', ['store_bn|in' => $storeBns]);
                    
                    if (!empty($stores)) {
                        // 为每个门店记录操作日志和快照
                        foreach ($stores as $store) {
                            // 获取门店更新前的快照
                            $snapshoot = $storeObj->dump(['store_bn' => $store['store_bn']]);
                            
                            // 记录操作日志
                            $log_id = app::get('ome')->model('operation_log')->write_log('store_upsert@o2o', $store['store_id'], '经销商关闭，关闭下属门店');
                            
                            // 记录快照
                            if ($log_id && $snapshoot) {
                                $shootMdl = app::get('ome')->model('operation_log_snapshoot');
                                $snapshoot = json_encode($snapshoot, JSON_UNESCAPED_UNICODE);
                                $tmp = ['log_id' => $log_id, 'snapshoot' => $snapshoot];
                                $shootMdl->insert($tmp);
                            }
                        }
                        
                        // 更新门店状态
                        $storeObj->update(
                            ['status' => $statusValue],
                            ['store_bn|in' => $storeBns]
                        );
                        
                        // 更新关联的仓库状态
                        $branchIds = array_column($stores, 'branch_id');
                        if (!empty($branchIds)) {
                            $branchObj = app::get('ome')->model('branch');
                            $branchObj->update(
                                ['b_status' => $statusValue],
                                ['branch_id|in' => $branchIds]
                            );
                        }
                        
                        // 更新关联的店铺状态
                        $shopIds = array_column($stores, 'shop_id');
                        if (!empty($shopIds)) {
                            $shopObj = app::get('ome')->model('shop');
                            $shopObj->update(
                                ['s_status' => $statusValue],
                                ['shop_id|in' => $shopIds]
                            );
                        }
                    }
                }
            }
            
            // 提交事务
            $db->commit($tran);
            
            return true;
            
        } catch (Exception $e) {
            // 回滚事务
            $db->rollBack();
            return false;
        }
    }
    
    /**
     * 检查门店是否还有库存
     * 
     * @param string $store_bn 门店编码
     * @return bool true表示有库存，false表示无库存
     */
    public function checkStoreHasStock($store_bn)
    {
        if (empty($store_bn)) {
            return false;
        }
        
        // 根据门店编码获取branch_id
        $branchId = $this->getBranchIdByStoreBn($store_bn);
        if (!$branchId) {
            return false;
        }
        
        // 查询库存表，检查是否有store > 0的记录
        $branchProductMdl = app::get('ome')->model('branch_product');
        $stockRecord = $branchProductMdl->dump([
            'branch_id' => $branchId,
            'store|noequal' => 0
        ], 'id');
        
        return !empty($stockRecord);
    }
}