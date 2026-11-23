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
 * 翱象系统Lib类
 * 
 * @author wangbiao@shopex.cn
 * @version 2023.01.18
 */
class dchain_aoxiang extends dchain_abstract
{
    /**
     * 是否为签约的店铺
     *
     * @param $shop_id 店铺ID
     * @param $shop_type 店铺类型
     * @return bool
     */
    public function isSignedShop($shop_id, $shop_type='')
    {
        //支持的店铺类型
        if($shop_type && !in_array($shop_type, array('tmall', 'taobao'))){
            return false;
        }

        //是否安装了应用
        if(!app::get('dchain')->is_installed()){
            return false;
        }

        //店铺信息
        $shopInfo = app::get('ome')->model('shop')->dump(array('shop_id'=>$shop_id), 'shop_id,shop_bn,shop_type,aoxiang_signed');

        //店铺类型
        if(!in_array($shopInfo['shop_type'], array('tmall', 'taobao'))){
            return false;
        }

        //翱象签约店铺
        if($shopInfo['aoxiang_signed'] == '1'){
            return true;
        }

        return false;
    }

    /**
     * 获取已经签约的店铺
     */
    public function getSignedShops()
    {
        $shopMdl = app::get('ome')->model('shop');
        
        $shopList = $shopMdl->getList('shop_id,shop_bn,name,shop_type', array('aoxiang_signed'=>'1'));
        
        return array_column($shopList, null, 'shop_id');;
    }
    
    /**
     * 仓库新建、更新时触发自动推送给翱象系统
     *
     * @param $branch_id
     * @return void
     */
    public function triggerBranch($branch_id)
    {
        $branchMdl = app::get('ome')->model('branch');
        $shopMdl = app::get('ome')->model('shop');
        $aoBranchMdl = app::get('dchain')->model('aoxiang_branch');
        
        //info
        $branchInfo = $branchMdl->db_dump(array('branch_id'=>$branch_id), '*');
        if(empty($branchInfo)){
            return false;
        }
        
        if($branchInfo['type'] != 'main' || $branchInfo['is_deliv_branch'] != 'true'){
            return false;
        }
        
        //店铺信息
        $shopList = $shopMdl->getList('shop_id,shop_bn,shop_type,aoxiang_signed', array('shop_type'=>array('taobao','tmall'), 'aoxiang_signed'=>'1'));
        if(empty($shopList)){
            return false;
        }
        
        $shopList = array_column($shopList, null, 'shop_bn');
        
        //仓库关联的店铺
        $relation = app::get('ome')->getConf('shop.branch.relationship');
        if(empty($relation)){
            return false;
        }
        
        foreach ($relation as $shop_bn => $shopVal)
        {
            $shopInfo = $shopList[$shop_bn];
            if(empty($shopInfo)){
                continue;
            }
            
            $branch_bn = $shopVal[$branch_id];
            if(empty($branch_bn)){
                continue;
            }
    
            $branch_ids = array($branch_id);
            $shop_id = $shopInfo['shop_id'];
            
            //添加仓库映射
            $error_msg = '';
            $result = $this->addAoxiangBranch($branch_ids, $shop_id, $error_msg);
            if(!$result){
                continue;
            }
            
            //row
            $rowInfo = $aoBranchMdl->dump(array('branch_bn'=>$branch_bn, 'shop_id'=>$shop_id), '*');
            if(empty($rowInfo)){
                continue;
            }
            
            $syncList = array($rowInfo);
            
            //重置同步状态
            $updateData = array('sync_status'=>'none');
            $aoBranchMdl->update($updateData, array('bid'=>$rowInfo['bid']));
            
            //同步
            $operation = 'auto'; //自动同步标记
            $result = $this->syncBranch($syncList, $operation);
            if($result['rsp'] != 'succ'){
                //$error_msg = ($result['err_msg'] ? $result['err_msg'] : $result['msg']);
                continue;
            }
        }
        
        return true;
    }
    
    /**
     * 仓库新建、更新时触发自动推送给翱象系统
     *
     * @param $branch_id
     * @return void
     */
    public function triggerDeleteBranch($branch_id, $old_branch_bn)
    {
        $branchMdl = app::get('ome')->model('branch');
        $shopMdl = app::get('ome')->model('shop');
        $aoBranchMdl = app::get('dchain')->model('aoxiang_branch');
        
        //info
        $branchInfo = $branchMdl->db_dump(array('branch_id'=>$branch_id), '*');
        if(empty($branchInfo)){
            return false;
        }
        
        if($branchInfo['type'] != 'main' || $branchInfo['attr'] != 'true' || $branchInfo['is_deliv_branch'] != 'true'){
            return false;
        }
        
        //店铺信息
        $shopList = $shopMdl->getList('shop_id,shop_bn,shop_type,aoxiang_signed', array('shop_type'=>array('taobao','tmall'), 'aoxiang_signed'=>'1'));
        if(empty($shopList)){
            return false;
        }
        
        $shopList = array_column($shopList, null, 'shop_bn');
        
        //仓库关联的店铺
        $relation = app::get('ome')->getConf('shop.branch.relationship');
        if(empty($relation)){
            return false;
        }
        
        foreach ($relation as $shop_bn => $shopVal)
        {
            $shopInfo = $shopList[$shop_bn];
            if(empty($shopInfo)){
                continue;
            }
            
            $branch_bn = $shopVal[$branch_id];
            if(empty($branch_bn)){
                continue;
            }
            
            $branch_ids = array($branch_id);
            $shop_id = $shopInfo['shop_id'];
            
            //row
            $rowInfo = $aoBranchMdl->dump(array('branch_bn'=>$old_branch_bn, 'shop_id'=>$shop_id), '*');
            if(empty($rowInfo)){
                continue;
            }
    
            $deleteList = array($rowInfo);
            
            //删除仓库
            $operation = 'auto'; //自动同步标记
            $result = $this->deleteBranch($deleteList, $operation);
            if($result['rsp'] != 'succ'){
                //$error_msg = ($result['err_msg'] ? $result['err_msg'] : $result['msg']);
                
                continue;
            }
        }
        
        return true;
    }
    
    /**
     * 添加仓库分配关系
     * 
     * @param array $branch_ids
     * @param string $shop_id
     * @return bool
     */
    public function addAoxiangBranch($branch_ids, $shop_id, &$error_msg=null)
    {
        $aoBranchMdl = app::get('dchain')->model('aoxiang_branch');
        $branchMdl = app::get('ome')->model('branch');
        $shopMdl = app::get('ome')->model('shop');
        
        //shop
        $shopInfo = $shopMdl->dump(array('shop_id'=>$shop_id), '*');
        if(empty($shopInfo)){
            $error_msg = '没有店铺信息(shop_id：'. $shop_id .')';
            return false;
        }
        
        //aoxiang
        $aoBranchList = $aoBranchMdl->getList('*', array('shop_id'=>$shop_id, 'branch_id'=>$branch_ids));
        $aoBranchList = array_column($aoBranchList, null, 'branch_id');
        
        //branch
        $sql = "SELECT branch_id,branch_bn,name FROM sdb_ome_branch WHERE branch_id IN(". implode(',', $branch_ids) .")";
        $branchList = $branchMdl->db->select($sql);
        if(empty($branchList)){
            $error_msg = '没有仓库数据';
            return false;
        }
        
        //list
        foreach ($branchList as $key => $val)
        {
            $branch_id = $val['branch_id'];
            
            //已经存在,则跳过
            if($aoBranchList[$branch_id]){
                continue;
            }
            
            //sdf
            $sdf = array(
                'shop_id' => $shop_id,
                'shop_type' => $shopInfo['shop_type'],
                'branch_id' => $branch_id,
                'branch_bn' => $val['branch_bn'],
                'create_time' => time(),
                'last_modified' => time(),
            );
            $aoBranchMdl->insert($sdf);
        }
        
        return true;
    }
    
    /**
     * 同步仓库给到翱象系统
     * @todo：支持批量同步,每次最多同步50条;
     * 
     * @param array $dataList
     * @param string $operation
     * @return array
     */
    public function syncBranch($dataList, $operation='')
    {
        $aoBranchMdl = app::get('dchain')->model('aoxiang_branch');
        $branchMdl = app::get('ome')->model('branch');
        $shopMdl = app::get('ome')->model('shop');
        $channelMdl = app::get('channel')->model('channel');
        
        $shop_id = $dataList[0]['shop_id'];
        $branch_ids = array_column($dataList, 'branch_id');
        
        //mode
        $operation_name = '系统自动';
        if($operation == 'retry'){
            $operation_name = '重试';
        }elseif($operation == 'manual'){
            $operation_name = '手工';
        }
        
        //shop
        $shopInfo = $shopMdl->dump(array('shop_id'=>$shop_id), '*');
        if(empty($shopInfo)){
            $error_msg = '没有店铺信息(shop_id：'. $shop_id .')';
            return $this->error($error_msg);
        }
        
        //仓库列表
        $sql = "SELECT * FROM sdb_ome_branch WHERE branch_id IN(". implode(',', $branch_ids) .")";
        $branchList = $branchMdl->db->select($sql);
        if(empty($branchList)){
            $error_msg = '没有仓库数据';
            return $this->error($error_msg);
        }
        
        $branchList = array_column($branchList, null, 'branch_id');
        $wms_ids = array_column($branchList, 'wms_id');
        
        //第三方仓储列表
        $channelList = array();
        if($wms_ids){
            $channelList = $channelMdl->getList('channel_id,channel_bn,channel_name,node_id,node_type', array('channel_id'=>$wms_ids, 'channel_type'=>'wms'));
            $channelList = array_column($channelList, null, 'channel_id');
        }
        
        //params
        $bids = array();
        $params = array();
        foreach ($dataList as $key => $rowInfo)
        {
            $branch_id = $rowInfo['branch_id'];
            
            //wms_id
            $wms_id = $branchList[$branch_id]['wms_id'];
            
            //branchInfo
            $branchInfo = $branchList[$branch_id];
            if(empty($branchInfo)){
                continue;
            }
            
            //channelInfo
            $channelInfo = $channelList[$wms_id];
            
            //area
            $areaList = explode(':', $branchInfo['area']);
            $areaList = explode('/', $areaList[1]);
            
            //params
            $params[] = array(
                'shop_bn' => $shopInfo['shop_bn'], //店铺编码
                'wms_channel_bn' => $channelInfo['channel_bn'], //WMS仓储编码
                'wms_channel_name' => $channelInfo['channel_name'], //WMS仓储名称
                'branch_bn' => $branchInfo['branch_bn'], //OMS仓库编码
                'branch_name' => $branchInfo['name'], //OMS仓库名称
                'storage_code' => $branchInfo['storage_code'], //OMS库内存放点编号
                'province' => $areaList[0], //省份
                'city' => $areaList[1], //城市
                'area' => $areaList[2], //地区
                'town' => $areaList[3], //乡镇
                'address' => $branchInfo['address'], //详细地址
                'zip' => $branchInfo['zip'],
                'uname' => $branchInfo['uname'], //联系人姓名
                'mobile' => $branchInfo['mobile'], //联系人手机
                'phone' => $branchInfo['phone'], //固定电话
                'disabled' => $branchInfo['disabled'], //是否启用
            );
            
            $bids[] = $rowInfo['bid'];
        }
        
        //check
        if(empty($params)){
            return $this->succ();
        }
        
        //request
        $result = kernel::single('erpapi_router_request')->set('shop', $shop_id)->branch_createAoxiangWarehouse($params);
        if($result['rsp'] != 'succ'){
            $error_msg = ($result['msg'] ? $result['msg'] : $result['err_msg']);
            
            //update
            $updateData = array('sync_status'=>'fail', 'sync_msg'=>$error_msg);
            $aoBranchMdl->update($updateData, array('bid'=>$bids));
            
            return $this->error($error_msg);
        }elseif(empty($result['datalist'])){
            $error_msg = '全部仓库都绑定失败';
            
            return $this->error($error_msg);
        }
        
        //结果
        $response = array();
        foreach ($result['datalist'] as $resStatus => $resItems)
        {
            foreach ($resItems as $itemKey => $itemVal)
            {
                $warehouse_code = $itemVal['warehouse_code'];
                
                if($resStatus == 'succ'){
                    $updateData = array('sync_status'=>'succ', 'seller_id'=>$itemVal['seller_id'], 'sync_msg'=>'');
                    
                    $response['succ'][] = $warehouse_code;
                }else{
                    $updateData = array('sync_status'=>'fail', 'sync_msg'=>$itemVal['message']);
                    
                    $response['fail'][] = $warehouse_code;
                }
                
                //update
                $aoBranchMdl->update($updateData, array('shop_id'=>$shop_id, 'branch_bn'=>$warehouse_code));
            }
        }
        
        //result
        if($response['succ'] && $response['fail']){
            $error_msg = '部分绑定成功：'. count($response['succ']) .'条记录，部分绑定失败：'. count($response['fail']) .'条记录;';
            
            return $this->error($error_msg);
        }elseif($response['fail']){
            $error_msg = '全部绑定失败：'. count($response['fail']) .'条记录;';
            
            return $this->error($error_msg);
        }else{
            return $this->succ();
        }
    }
    
    /**
     * 删除翱象系统里OMS同步的仓库
     * @todo：支持批量删除,每次最多同步50条;
     *
     * @param array $dataList
     * @param string $operation
     * @return array
     */
    public function deleteBranch($dataList, $operation='')
    {
        $aoBranchMdl = app::get('dchain')->model('aoxiang_branch');
        $branchMdl = app::get('ome')->model('branch');
        $shopMdl = app::get('ome')->model('shop');
        $channelMdl = app::get('channel')->model('channel');
        
        $shop_id = $dataList[0]['shop_id'];
        $branch_ids = array_column($dataList, 'branch_id');
        
        //mode
        $operation_name = '系统自动';
        if($operation == 'retry'){
            $operation_name = '重试';
        }elseif($operation == 'manual'){
            $operation_name = '手工';
        }
        
        //shop
        $shopInfo = $shopMdl->dump(array('shop_id'=>$shop_id), '*');
        if(empty($shopInfo)){
            $error_msg = '没有店铺信息(shop_id：'. $shop_id .')';
            return $this->error($error_msg);
        }
        
        //仓库列表
        $sql = "SELECT * FROM sdb_ome_branch WHERE branch_id IN(". implode(',', $branch_ids) .")";
        $branchList = $branchMdl->db->select($sql);
        if(empty($branchList)){
            $error_msg = '没有仓库数据';
            return $this->error($error_msg);
        }
        
        $branchList = array_column($branchList, null, 'branch_id');
        $wms_ids = array_column($branchList, 'wms_id');
        
        //第三方仓储列表
        $channelList = array();
        if($wms_ids){
            $channelList = $channelMdl->getList('channel_id,channel_bn,channel_name,node_id,node_type', array('channel_id'=>$wms_ids, 'channel_type'=>'wms'));
            $channelList = array_column($channelList, null, 'channel_id');
        }
        
        //params
        $bids = array();
        $params = array();
        foreach ($dataList as $key => $rowInfo)
        {
            $bid = $rowInfo['bid'];
            $branch_id = $rowInfo['branch_id'];
            $wms_id = $rowInfo['wms_id'];
            
            //未同步成功的单据可直接删除
            if($rowInfo['sync_status'] != 'succ'){
                $aoBranchMdl->delete(array('bid'=>$bid));
                
                continue;
            }
            
            //branchInfo
            $branchInfo = $branchList[$branch_id];
            if(empty($branchInfo)){
                continue;
            }
            
            //channelInfo
            $channelInfo = $channelList[$wms_id];
            
            //area
            $areaList = explode(':', $branchInfo['area']);
            $areaList = explode('/', $areaList[1]);
            
            //params
            $params[] = array(
                'shop_bn' => $shopInfo['shop_bn'], //店铺编码
                'wms_channel_bn' => $channelInfo['branch_bn'], //WMS仓储编码
                'wms_channel_name' => $channelInfo['channel_name'], //WMS仓储名称
                'branch_bn' => $branchInfo['branch_bn'], //OMS仓库编码
                'branch_name' => $branchInfo['name'], //OMS仓库名称
                'storage_code' => $branchInfo['storage_code'], //OMS库内存放点编号
                'province' => $areaList[0], //省份
                'city' => $areaList[1], //城市
                'area' => $areaList[2], //地区
                'town' => $areaList[3], //乡镇
                'address' => $branchInfo['address'], //详细地址
                'zip' => $branchInfo['zip'],
                'uname' => $branchInfo['uname'], //联系人姓名
                'mobile' => $branchInfo['mobile'], //联系人手机
                'phone' => $branchInfo['phone'], //固定电话
            );
            
            $bids[] = $bid;
        }
        
        //check
        if(empty($params)){
            return $this->succ();
        }
        
        //先申请解绑
        $result = kernel::single('erpapi_router_request')->set('shop', $shop_id)->branch_deleteAoxiangWarehouse($params);
        if($result['rsp'] != 'succ'){
            $error_msg = ($result['msg'] ? $result['msg'] : $result['err_msg']);
            
            //update
            $updateData = array('sync_msg'=>$error_msg);
            $aoBranchMdl->update($updateData, array('bid'=>$bids));
            
            return $this->error($error_msg);
        }elseif(empty($result['datalist'])){
            $error_msg = '全部仓库都删除失败';
            
            return $this->error($error_msg);
        }
        
        //结果
        $response = array();
        foreach ($result['datalist'] as $resStatus => $resItems)
        {
            foreach ($resItems as $itemKey => $itemVal)
            {
                $warehouse_code = $itemVal['warehouse_code'];
                
                //status
                if($resStatus == 'succ'){
                    //解绑成功,直接删除掉
                    $aoBranchMdl->delete(array('shop_id'=>$shop_id, 'branch_bn'=>$warehouse_code));
                    
                    $response['succ'][] = $warehouse_code;
                }else{
                    $updateData = array('sync_msg'=>$itemVal['message']);
                    $aoBranchMdl->update($updateData, array('shop_id'=>$shop_id, 'branch_bn'=>$warehouse_code));
                    
                    $response['fail'][] = $warehouse_code;
                }
            }
        }
        
        //result
        if($response['succ'] && $response['fail']){
            $error_msg = '部分解绑成功：'. count($response['succ']) .'条记录，部分解绑失败：'. count($response['fail']) .'条记录;';
            
            return $this->error($error_msg);
        }elseif($response['fail']){
            $error_msg = '全部解绑失败：'. count($response['fail']) .'条记录;';
            
            return $this->error($error_msg);
        }else{
            return $this->succ();
        }
    }
    
    /**
     * 物流公司新建、更新时触发自动推送给翱象系统
     *
     * @param $branch_id
     * @return void
     */
    public function triggerLogistics($corp_id)
    {
        $aoLogiMdl = app::get('dchain')->model('aoxiang_logistics');
        $corpMdl = app::get('ome')->model('dly_corp');
        $shopMdl = app::get('ome')->model('shop');
        
        //info
        $corpInfo = $corpMdl->db_dump(array('corp_id'=>$corp_id), '*');
        if(empty($corpInfo)){
            return false;
        }
        
        if(in_array($corpInfo['type'], array('o2o_pickup', 'o2o_ship'))){
            return false;
        }
        
        //签约店铺信息
        $shopList = $shopMdl->getList('shop_id,shop_bn,shop_type,aoxiang_signed', array('shop_type'=>array('taobao','tmall'), 'aoxiang_signed'=>'1'));
        if(empty($shopList)){
            return false;
        }
        
        foreach ($shopList as $shopKey => $shopVal)
        {
            $shop_id = $shopVal['shop_id'];
            $shop_bn = $shopVal['shop_bn'];
            
            $corpIds = array($corp_id);
            
            //添加物流公司映射
            if($corpInfo['disabled'] == 'false'){
                $error_msg = '';
                $result = $this->addAoxiangLogistics($corpIds, $shop_id, $error_msg);
                if(!$result){
                    continue;
                }
            }
            
            //row
            $rowInfo = $aoLogiMdl->dump(array('logi_code'=>$corpInfo['type'], 'shop_id'=>$shop_id), '*');
            if(empty($rowInfo)){
                continue;
            }
            
            $syncList = array($rowInfo);
            
            //重置同步状态
            $updateData = array('sync_status'=>'none');
            $aoLogiMdl->update($updateData, array('lid'=>$rowInfo['lid']));
            
            //同步
            $operation = 'auto'; //自动同步标记
            $result = $this->syncLogistics($syncList, $operation);
            if($result['rsp'] != 'succ'){
                //$error_msg = ($result['err_msg'] ? $result['err_msg'] : $result['msg']);
                
                continue;
            }
        }
        
        return true;
    }
    
    /**
     * 添加物流公司分配关系
     *
     * @param array $corpIds
     * @param string $shop_id
     * @return bool
     */
    public function addAoxiangLogistics($corpIds, $shop_id, &$error_msg=null)
    {
        $aoLogiMdl = app::get('dchain')->model('aoxiang_logistics');
        $corpMdl = app::get('ome')->model('dly_corp');
        $shopMdl = app::get('ome')->model('shop');
        
        //shop
        $shopInfo = $shopMdl->dump(array('shop_id'=>$shop_id), '*');
        if(empty($shopInfo)){
            $error_msg = '没有店铺信息(shop_id：'. $shop_id .')';
            return false;
        }
        
        //aoxiang
        $aoBranchList = $aoLogiMdl->getList('*', array('shop_id'=>$shop_id, 'corp_id'=>$corpIds));
        $aoBranchList = array_column($aoBranchList, null, 'corp_id');
        
        //物流公司列表
        $corpList = $corpMdl->getList('corp_id,type,name', array('corp_id'=>$corpIds));
        if(empty($corpList)){
            $error_msg = '没有物流公司数据';
            return false;
        }
        
        //list
        foreach ($corpList as $key => $val)
        {
            $corp_id = $val['corp_id'];
            
            //唯一物流编码(物流公司编码+物流公司ID)
            $erp_code = $val['type'] . sprintf("%03d", $corp_id);
            
            //已经存在,则跳过
            if($aoBranchList[$corp_id]){
                continue;
            }
            
            //sdf
            $sdf = array(
                'erp_code' => $erp_code,
                'shop_id' => $shop_id,
                'shop_type' => $shopInfo['shop_type'],
                'corp_id' => $corp_id,
                'logi_code' => $val['type'],
                'create_time' => time(),
                'last_modified' => time(),
            );
            $aoLogiMdl->insert($sdf);
        }
        
        return true;
    }
    
    /**
     * 同步物流公司给到翱象系统
     * @todo：支持批量同步,每次最多同步50条;
     *
     * @param array $dataList
     * @param string $operation
     * @return array
     */
    public function syncLogistics($dataList, $operation='')
    {
        $aoLogiMdl = app::get('dchain')->model('aoxiang_logistics');
        $corpMdl = app::get('ome')->model('dly_corp');
        $shopMdl = app::get('ome')->model('shop');
        
        $shop_id = $dataList[0]['shop_id'];
        $corpIds = array_column($dataList, 'corp_id');
        
        //mode
        $operation_name = '系统自动';
        if($operation == 'retry'){
            $operation_name = '重试';
        }elseif($operation == 'manual'){
            $operation_name = '手工';
        }
        
        //shop
        $shopInfo = $shopMdl->dump(array('shop_id'=>$shop_id), '*');
        if(empty($shopInfo)){
            $error_msg = '没有店铺信息(shop_id：'. $shop_id .')';
            return $this->error($error_msg);
        }
        
        //物流公司列表
        $corpList = $corpMdl->getList('corp_id,type,name,disabled', array('corp_id'=>$corpIds));
        if(empty($corpList)){
            $error_msg = '没有物流公司数据';
            return $this->error($error_msg);
        }
        
        $corpList = array_column($corpList, null, 'corp_id');
        
        //params
        $lids = array();
        $params = array();
        foreach ($dataList as $key => $rowInfo)
        {
            $corp_id = $rowInfo['corp_id'];
            
            //info
            $corpInfo = $corpList[$corp_id];
            if(empty($corpInfo)){
                continue;
            }
            
            //params
            $params[] = array(
                'shop_bn' => $shopInfo['shop_bn'], //店铺编码
                'erp_code' => $rowInfo['erp_code'], //erp配资源唯一编码,卖家唯一
                'logi_code' => $corpInfo['type'], //物流公司编码
                'logi_name' => $corpInfo['name'], //物流公司名称
                'disabled' => $corpInfo['disabled'], //是否启用
            );
            
            $lids[] = $rowInfo['lid'];
        }
        
        //check
        if(empty($params)){
            return $this->succ();
        }
        
        //request
        $result = kernel::single('erpapi_router_request')->set('shop', $shop_id)->logistics_createAoxiangLogistics($params);
        if($result['rsp'] != 'succ'){
            $error_msg = ($result['msg'] ? $result['msg'] : $result['err_msg']);
            
            //update
            $updateData = array('sync_status'=>'fail', 'sync_msg'=>$error_msg);
            $aoLogiMdl->update($updateData, array('lid'=>$lids));
            
            return $this->error($error_msg);
        }elseif(empty($result['datalist'])){
            $error_msg = '全部物流公司都绑定失败';
            
            return $this->error($error_msg);
        }
        
        //结果
        $response = array();
        foreach ($result['datalist'] as $resStatus => $resItems)
        {
            foreach ($resItems as $itemKey => $itemVal)
            {
                $erp_code = $itemVal['erp_code'];
                
                if($resStatus == 'succ'){
                    $updateData = array('sync_status'=>'succ', 'sync_msg'=>'');
                    
                    $response['succ'][] = $erp_code;
                }else{
                    $updateData = array('sync_status'=>'fail', 'sync_msg'=>$itemVal['message']);
                    
                    $response['fail'][] = $erp_code;
                }
                
                //update
                $aoLogiMdl->update($updateData, array('shop_id'=>$shop_id, 'erp_code'=>$erp_code));
            }
        }
        
        //result
        if($response['succ'] && $response['fail']){
            $error_msg = '部分绑定成功：'. count($response['succ']) .'条记录，部分绑定失败：'. count($response['fail']) .'条记录;';
            
            return $this->error($error_msg);
        }elseif($response['fail']){
            $error_msg = '全部绑定失败：'. count($response['fail']) .'条记录;';
            
            return $this->error($error_msg);
        }else{
            return $this->succ();
        }
    }
    
    /**
     * 删除翱象系统里OMS同步的物流公司
     * @todo：支持批量删除,每次最多同步50条;
     *
     * @param array $dataList
     * @param string $operation
     * @return array
     */
    public function deleteLogistics($dataList, $operation='')
    {
        $aoLogiMdl = app::get('dchain')->model('aoxiang_logistics');
        $corpMdl = app::get('ome')->model('dly_corp');
        $shopMdl = app::get('ome')->model('shop');
        
        $shop_id = $dataList[0]['shop_id'];
        $corpIds = array_column($dataList, 'corp_id');
        
        //mode
        $operation_name = '系统自动';
        if($operation == 'retry'){
            $operation_name = '重试';
        }elseif($operation == 'manual'){
            $operation_name = '手工';
        }
        
        //shop
        $shopInfo = $shopMdl->dump(array('shop_id'=>$shop_id), '*');
        if(empty($shopInfo)){
            $error_msg = '没有店铺信息(shop_id：'. $shop_id .')';
            return $this->error($error_msg);
        }
        
        //物流公司列表
        $corpList = $corpMdl->getList('corp_id,type,name', array('corp_id'=>$corpIds));
        if(empty($corpList)){
            $error_msg = '没有物流公司数据';
            return $this->error($error_msg);
        }
        
        $corpList = array_column($corpList, null, 'corp_id');
        
        //params
        $lids = array();
        $params = array();
        foreach ($dataList as $key => $rowInfo)
        {
            $lid = $rowInfo['lid'];
            $corp_id = $rowInfo['corp_id'];
            
            //未同步成功的单据可直接删除
            if($rowInfo['sync_status'] != 'succ'){
                $aoLogiMdl->delete(array('lid'=>$lid));
                
                continue;
            }
            
            //info
            $corpInfo = $corpList[$corp_id];
            if(empty($corpInfo)){
                continue;
            }
            
            //params
            $params[] = array(
                'shop_bn' => $shopInfo['shop_bn'], //店铺编码
                'erp_code' => $rowInfo['erp_code'], //erp配资源唯一编码,卖家唯一
                'logi_code' => $corpInfo['type'], //物流公司编码
                'logi_name' => $corpInfo['name'], //物流公司名称
            );
            
            $lids[] = $lid;
        }
        
        //check
        if(empty($params)){
            return $this->succ();
        }
        
        //request
        $result = kernel::single('erpapi_router_request')->set('shop', $shop_id)->logistics_deleteAoxiangLogistics($params);
        if($result['rsp'] != 'succ'){
            $error_msg = ($result['msg'] ? $result['msg'] : $result['err_msg']);
            
            //update
            $updateData = array('sync_msg'=>$error_msg);
            $aoLogiMdl->update($updateData, array('lid'=>$lids));
            
            return $this->error($error_msg);
        }elseif(empty($result['datalist'])){
            $error_msg = '全部物流公司都解绑失败';
            
            return $this->error($error_msg);
        }
        
        //结果
        $response = array();
        foreach ($result['datalist'] as $resStatus => $resItems)
        {
            foreach ($resItems as $itemKey => $itemVal)
            {
                $erp_code = $itemVal['erp_code'];
                
                if($resStatus == 'succ'){
                    //解绑成功,直接删除掉
                    $aoLogiMdl->delete(array('shop_id'=>$shop_id, 'erp_code'=>$erp_code));
                    
                    $response['succ'][] = $erp_code;
                }else{
                    $updateData = array('sync_msg'=>$itemVal['message']);
                    $aoLogiMdl->update($updateData, array('shop_id'=>$shop_id, 'erp_code'=>$erp_code));
                    
                    $response['fail'][] = $erp_code;
                }
            }
        }
        
        //result
        if($response['succ'] && $response['fail']){
            $error_msg = '部分解绑成功：'. count($response['succ']) .'条记录，部分解绑失败：'. count($response['fail']) .'条记录;';
            
            return $this->error($error_msg);
        }elseif($response['fail']){
            $error_msg = '全部解绑失败：'. count($response['fail']) .'条记录;';
            
            return $this->error($error_msg);
        }else{
            return $this->succ();
        }
    }

    /**
     * 获取翱象系统绑定的仓库库存
     *
     * @param array $stocks
     * @param array $shopInfo
     * @return array
     */
    public function getBranchStocks($stocks, $shopInfo, &$error_msg=null)
    {
        $aoBranchMdl = app::get('dchain')->model('aoxiang_branch');
        
        //params
        $shop_id = $shopInfo['shop_id'];
        $shop_bn = $shopInfo['shop_bn'];

        //check
        if(empty($stocks) || empty($shop_id) || empty($shop_bn)){
            $error_msg = '库存数据不能为空';
            return false;
        }
        
        //获取同步成功的仓库列表
        $aoBranchList = $aoBranchMdl->getList('*', array('shop_id'=>$shop_id, 'sync_status'=>'succ'));
        if(empty($aoBranchList)){
            $error_msg = '没有可用的仓库';
            return false;
        }

        $aoBranchList = array_column($aoBranchList, null, 'branch_bn');

        //stock
        $branchStockList = array();
        foreach($stocks as $stoKey => $stoVal)
        {
            $product_bn = $stoVal['bn'];
            
            foreach($aoBranchList as $branchKey => $branchVal)
            {
                $branch_bn = $branchVal['branch_bn'];
                
                $resultStock = $this->getProductBranchStock($product_bn, $shop_bn, $shop_id, $branch_bn);
                
                $totalStock = intval($resultStock['branch_store']);
                $actualStock = intval($resultStock['actual_stock']);
                
                //list
                $branchStockList[$branch_bn][$product_bn] = array(
                    'bn' => $product_bn,
                    'branch_bn' => $branch_bn,
                    'total_quantity' => $totalStock,
                    'quantity' => $actualStock,
                );
            }
        }
        
        return $branchStockList;
    }
    
    /**
     * 查询黑白名单快递
     *
     * @param $sdf
     * @param $error_msg
     * @return void
     */
    public function triggerOrderLogi($orderInfo, &$error_msg=null)
    {
        $orderObjMdl = app::get('ome')->model('order_objects');
        $orderExtendObj = app::get('ome')->model('order_extend');
        
        $order_id = $orderInfo['order_id'];
        $order_bn = $orderInfo['order_bn'];
        $shop_id = $orderInfo['shop_id'];
        $shop_type = $orderInfo['shop_type'];
        
        //check
        $isCheck = $this->isSignedShop($shop_id, $shop_type);
        if(!$isCheck){
            $error_msg = '不是翱象签约的店铺';
            return false;
        }
        
        //订单明细
        $orderObjList = $orderObjMdl->getList('obj_id,order_id,bn,oid', array('order_id'=>$order_id));
        if(empty($orderObjList)){
            $error_msg = '订单没有明细';
            return false;
        }
        
        //order_extend
        $extendInfo = $orderExtendObj->dump(array('order_id'=>$order_id), 'order_id,extend_field');
        if(empty($extendInfo)){
            $error_msg = '订单没有扩展信息';
            return false;
        }
        
        //extend_field
        $extend_field = array();
        if($extendInfo['extend_field']){
            $extend_field = json_decode($extendInfo['extend_field'], true);
        }
        
        //发货地区信息(翱象推送过来的预估发货地信息)
        $sendInfo = array(
            'send_division_code' => $extend_field['send_division_code'],
            'send_province' => $extend_field['send_state'],
            'send_city' => $extend_field['send_city'],
            'send_district' => $extend_field['send_district'],
            'send_town' => $extend_field['send_town'],
        );
        
        //收货人地区
        $shipInfo = ($orderInfo['consignee']['area'] ? $orderInfo['consignee']['area'] : $orderInfo['ship_area']);
        $shipInfo = explode(':', $shipInfo);
        $shipInfo = explode('/', $shipInfo[1]);
        
        //收货人信息
        $receiveInfo = array(
            'receive_province' => $shipInfo[0],
            'receive_city' => $shipInfo[1],
            'receive_district' => $shipInfo[2],
            'receive_town' => $shipInfo[3],
        );
        
        //list
        $itemList = array();
        foreach ($orderObjList as $objKey => $objVal)
        {
            if(empty($objVal['oid'])){
                continue;
            }
            
            //item
            $itemInfo = array(
                'order_code' => $order_bn, //ERP发货单号
                'trade_id' => $order_bn, //主交易单号
                'sub_trade_id' => $objVal['oid'], //子交易单号
                
            );
            
            //发货地区信息
            $itemInfo = array_merge($itemInfo, $sendInfo);
            
            //收货人地区
            $itemInfo = array_merge($itemInfo, $receiveInfo);
            
            //merge
            $itemList[] = $itemInfo;
        }
        
        //request
        $result = kernel::single('erpapi_router_request')->set('shop', $shop_id)->order_selectOrderLogi($itemList);
        if($result['rsp'] != 'succ'){
            $error_msg = ($result['msg'] ? $result['msg'] : $result['err_msg']);
            
            return false;
        }
        
        //string
        if(is_string($result['data'])){
            $result['data'] = json_decode($result['data'], true);
        }
        
        //data_detail
        $detailList = $result['data']['data']['data_detail'];
        if(empty($detailList)){
            $error_msg = '翱象返回的data_detail字段为空';
            
            return false;
        }
        
        //list
        $logisticsInfos = array();
        $extendFieldSdf = array();
        foreach ($detailList as $itemKey => $val)
        {
            $order_code = $val['order_code'];
            $trade_id = $val['trade_id'];
            $sub_trade_id = $val['sub_trade_id'];
            
            $updateSdf = array();
            
            //建议仓类型
            if($val['biz_sd_type']){
                $updateSdf['biz_sd_type'] = $val['biz_sd_type'];
            }
            
            //预选仓库编码
            if($val['biz_store_code']){
                //仓建议为0、1时,不用使用翱象仓审单
                if(!in_array($val['biz_sd_type'], '0','1')){
                    $updateSdf['store_code'] = $val['biz_store_code'];
                }
            }
            
            //择配建议
            if($val['biz_delivery_type']){
                $updateSdf['biz_delivery_type'] = $val['biz_delivery_type'];
            }
            
            //建议使用快递名单
            if($val['biz_delivery_code']){
                $logisticsInfos['biz_delivery_codes'][] = $val['biz_delivery_code'];
            }
            
            //快递白名单
            if($val['white_delivery_cps']){
                $logisticsInfos['white_delivery_cps'][] = $val['white_delivery_cps'];
            }
            
            //快递黑名单
            if($val['black_delivery_cps']){
                $logisticsInfos['black_delivery_cps'][] = $val['black_delivery_cps'];
            }
            
            //预估发货地址
            if($val['send_state'] && $val['send_city']){
                $extendFieldSdf['send_country'] = $val['send_country']; //国家
                $extendFieldSdf['send_state'] = $val['send_state']; //省
                $extendFieldSdf['send_city'] = $val['send_city']; //市
                $extendFieldSdf['send_district'] = $val['send_district']; //区
                $extendFieldSdf['send_town'] = $val['send_town']; //镇
                $extendFieldSdf['send_division_code'] = $val['send_division_code']; //预估发货地编码
            }
            
            //update
            if($updateSdf){
                $orderObjMdl->update($updateSdf, array('order_id'=>$order_id, 'oid'=>$sub_trade_id));
            }
        }
        
        $extendData = array();
        
        //建议使用快递名单
        if($logisticsInfos['biz_delivery_codes']){
            $extendData['biz_delivery_code'] = ($logisticsInfos['biz_delivery_codes'] ? json_encode($logisticsInfos['biz_delivery_codes']) : '');
        }
        
        //快递白名单
        if($logisticsInfos['white_delivery_cps']){
            $extendData['white_delivery_cps'] = ($logisticsInfos['white_delivery_cps'] ? json_encode($logisticsInfos['white_delivery_cps']) : '');
        }
        
        //快递黑名单
        if($logisticsInfos['black_delivery_cps']){
            $extendData['black_delivery_cps'] = ($logisticsInfos['black_delivery_cps'] ? json_encode($logisticsInfos['black_delivery_cps']) : '');
        }
        
        //merge extend_field
        if($extendFieldSdf){
            $extend_field = array_merge($extend_field, $extendFieldSdf);
            $extendData['extend_field'] = json_encode($extend_field);
        }
        
        //update
        if($extendData){
            $extendData['order_id'] = $order_id;
            
            $orderExtendObj->save($extendData);
        }
        
        return true;
    }

    /**
     * 获取回写给翱象系统的库存数据
     *
     * @param array $stocks
     * @param array $shopInfo
     * @return array
     */
    public function getStocks($stocks, $shopInfo, &$error_msg=null)
    {
        $axProductMdl = app::get('dchain')->model('aoxiang_product');

        //params
        $shop_id = $shopInfo['shop_id'];
        $shop_bn = $shopInfo['shop_bn'];

        //check
        if(empty($stocks) || empty($shop_id) || empty($shop_bn)){
            $error_msg = '无效的获取库存';
            return false;
        }
        $bns = array_column($stocks, 'bn');

        $productList = $axProductMdl->getList('pid,product_id,product_bn,sync_status', array('shop_id'=>$shop_id, 'product_bn'=>$bns));
        if(empty($productList)){
            $error_msg = '没有同步的商品';
            return false;
        }
        $productList = array_column($productList, null, 'product_bn');

        //stock
        foreach ($stocks as $key => $val)
        {
            $bn = $val['bn'];

            //check
            if(empty($productList[$bn]) || $productList[$bn]['sync_status'] != 'succ'){
                //删除未同步的商品
                unset($stocks[$key]);

                continue;
            }
        }

        //check
        if(empty($stocks)){
            $error_msg = '没有需要同步库存的商品';
            return false;
        }

        //获取绑定的仓库库存
        $stocks = $this->getBranchStocks($stocks, $shopInfo, $error_msg);
        if($stocks === false){
            return false;
        }

        return $stocks;
    }

    /**
     * 获取商品指定仓库的库存
     *
     * @param $product_bn
     * @param $shop_bn
     * @param $shop_id
     * @param $branch_bn
     * @return void
     */
    public function getProductBranchStock($product_bn, $shop_bn, $shop_id, $branch_bn)
    {
        $stockObject = kernel::single('inventorydepth_stock_calculation');
        $stockProductsLib = kernel::single('inventorydepth_stock_products');
        
        //获取商品的店铺级预占
        $sha1Str = $shop_id .'-'. strtolower($product_bn);
        $sha1 = sha1($sha1Str);
        $globals_freeze = $stockObject->get_shop_freeze($product_bn, $shop_bn, $shop_id);
        $globals_freeze = intval($globals_freeze);
        
        //计算可用库存
        $branch_product = $stockProductsLib->fetch_branch_products($branch_bn, $product_bn);
        $result = array();
        if($branch_product) {
            //配额库存
            $authorize_store = 0;
            
            //仓库总库存
            $branch_store = $branch_product['store'];
            
            //仓库冻结库存
            $branch_store_freeze = $branch_product['store_freeze'];
            
            //仓库可用库存 = 仓库总库存 - 仓库冻结库存 - 店铺预占冻结 - 配额库存
            $actual_stock = $branch_store - $branch_store_freeze - $globals_freeze - $authorize_store;
            $actual_stock = $actual_stock > 0 ? $actual_stock : 0;
            
            $result = array(
                'branch_store' => $branch_store,
                'branch_store_freeze' => $branch_store_freeze,
                'globals_freeze' => $globals_freeze,
                'authorize_store' => $authorize_store,
                'actual_stock' => $actual_stock,
            );
        }
        
        return $result;
    }
    
    /**
     * 仓库自动分配队列任务
     *
     * @param $cursor_id
     * @param $params
     * @param $error_msg
     * @return false
     */
    public function autoTaskAddBranch(&$cursor_id, $params, &$error_msg=null)
    {
        $branchMdl = app::get('ome')->model('branch');
        
        //data
        $sdfdata = $params['sdfdata'];
        $shop_id = $sdfdata['shop_id'];
        
        //check
        if(empty($shop_id)){
            $error_msg = '没有店铺shop_id';
            return false;
        }
        
        //list
        $branchList = $branchMdl->getList('branch_id,branch_bn', array('type'=>'main', 'disabled'=>'false', 'is_deliv_branch'=>'true'));
        if(empty($branchList)){
            $error_msg = '没有仓库列表';
            return false;
        }
        
        //auto
        foreach ($branchList as $key => $val)
        {
            $branch_id = $val['branch_id'];
            
            $result = $this->triggerBranch($branch_id);
        }
        
        return false;
    }
    
    /**
     * 物流公司自动分配队列任务
     *
     * @param $cursor_id
     * @param $params
     * @param $error_msg
     * @return false
     */
    public function autoTaskAddLogistics(&$cursor_id, $params, &$error_msg=null)
    {
        $corpMdl = app::get('ome')->model('dly_corp');
        
        //data
        $sdfdata = $params['sdfdata'];
        $shop_id = $sdfdata['shop_id'];
        
        //check
        if(empty($shop_id)){
            $error_msg = '没有店铺shop_id';
            return false;
        }
        
        //list
        $logiList = $corpMdl->getList('corp_id,type,name', array('disabled'=>'false'));
        if(empty($logiList)){
            $error_msg = '没有物流公司列表';
            return false;
        }
        
        //auto
        foreach ($logiList as $key => $val)
        {
            $corp_id = $val['corp_id'];
            
            $result = $this->triggerLogistics($corp_id);
        }
        
        return false;
    }

    /**
     * 获取翱象同步数据的配置信息
     * @param $shop_id 店铺ID
     * @param business 业务名称(sync_branch、sync_logistics、sync_product、sync_stock、sync_delivery)
     * @return void
     */
    public function getAoxiangSyncConfig($shop_id, $business='')
    {
        //check
        if(empty($shop_id)){
            return array();
        }

        //get config
        $aoxiangConfig = app::get('ome')->getConf('shop.aoxiang.config.'. $shop_id);
        $aoxiangConfig = json_decode($aoxiangConfig, true);

        //return
        if($business){
            return $aoxiangConfig[$business];
        }

        return $aoxiangConfig;
    }
    
    /**
     * 库存回写队列任务
     *
     * @param $cursor_id
     * @param $params
     * @param $error_msg
     * @return false
     */
    public function autoSyncProductStock(&$cursor_id, $params, &$error_msg=null)
    {
        //data
        $sdfdata = $params['sdfdata'];
        
        $shopInfo = $sdfdata['shopInfo'];
        $shop_id = $shopInfo['shop_id'];
        $axProductList = $sdfdata['axProductList'];
        
        //check
        if(empty($shopInfo)){
            $error_msg = '没有店铺信息';
            return false;
        }
        
        //check
        if(empty($axProductList)){
            $error_msg = '没有翱象商品信息';
            return false;
        }
        
        //format
        $stocks = array();
        foreach($axProductList as $product)
        {
            //check
            if($product['sync_status'] != 'succ'){
                continue;
            }
            
            //默认库存为0(后面会按仓库级获取库存)
            $quantity = 0;
            
            //商品类型
            $product_type = ($product['product_type'] == 'combine' ? 'pkg' : 'normal');
            
            //stocks
            $stocks[] = array(
                'bn' => $product['product_bn'],
                'quantity' => $quantity,
                'product_type' => $product_type,
            );
        }
        
        //check
        if(empty($stocks)){
            //$error_msg = '没有可回写的商品';
            return false;
        }
        
        //page size
        $page_size = 50;
        
        //page
        $newStocks = array_chunk($stocks, $page_size);
        $aoxiangResult = array();
        foreach ($newStocks as $stockItem)
        {
            //推送仓库级库存
            $stockList = $this->getStocks($stockItem, $shopInfo, $error_msg);
            if(!$stockList){
                continue;
            }
            
            //request
            foreach ($stockList as $branch_bn => $branchStock)
            {
                $aoxiangResult = kernel::single('erpapi_router_request')->set('shop', $shop_id)->product_stockAoxiangUpdate($branchStock);
            }
        }
        
        return false;
    }
}
