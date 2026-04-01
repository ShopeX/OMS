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
 * 退换货单通知封装类
 *
 * @author wangjianjun
 * @Time: 20170511
 */
class ome_return_notice
{
    /**
     * 通知WMS创建退换货单
     * 
     * @param int $reship_id 退换货ID
     * @param string $error_msg 退换货单同步数据信息
     * @return bool
     */


    static public function create($reship_id, &$error_msg=null)
    {
        $reshipObj = app::get('ome')->model('reship');
        $operationLogObj = app::get('ome')->model('operation_log');
        
        //获取退换货数据
        $data = kernel::single('ome_receipt_reship')->reship_create(array('reship_id'=>$reship_id));
        if (empty($data)){
            return true;
        }

        $shop = app::get('ome')->model('shop')->db_dump(['shop_id'=>$data['shop_id']], 'delivery_mode');
        if($shop['delivery_mode'] == 'jingxiao') {
            $error_msg = '经销店铺退货单不能推送第三方';
            return false;
        }
        //根据仓库识别是否门店仓还是电商仓
        $store_id = kernel::single('ome_branch')->isStoreBranch($data['branch_id']);
        if($store_id){
            $channel_type = 'store';
            $channel_id = $store_id;
        }else{
            $wms_id = kernel::single('ome_branch')->getWmsIdById($data['branch_id']);
            $channel_type = 'wms';
            $channel_id = $wms_id;
        }
        
        //request
        $result = kernel::single('erpapi_router_request')->set($channel_type,$channel_id)->reship_create($data);
        
        //[兼容]返回成功状态
        if($result['rsp'] == 'success'){
            $result['rsp'] = 'succ';
        }
        
        //rsp
        if ($result['rsp'] == 'succ'){
            $saveData = array(
                    'sync_status' => '3',
                    'sync_code' => '',
                    'sync_msg' => '',
            );
            
            //更新退货单外部编号
            if($result['data']['wms_order_code'] && $channel_type == 'wms'){
                $saveData['out_iso_bn'] = $result['data']['wms_order_code'];
            }
            
            $reshipObj->update($saveData, array('reship_id'=>$reship_id));
        }else{
            //错误信息
            $error_msg = ($result['err_msg'] ? $result['err_msg'] : $result['msg']);
            
            //错误码
            $error_code = ($result['error_code'] ? $result['error_code'] : $result['msg_code']);
            
            $saveData = array('sync_status'=>'2', 'sync_code'=>$error_code, 'sync_msg'=>$error_msg);
            
            //[京东云交易]一件代发
            $wms_type = kernel::single('ome_branch')->getNodetypBybranchId($data['branch_id']);
            if($wms_type == 'yjdf'){
                $saveData['is_check'] = '2';
            }
            
            $reshipObj->update($saveData, array('reship_id'=>$reship_id));
        }
        
        //记录日志
        // $rsp_result = json_encode($result);
        $memo = '发送至仓库（'.$result['msg_id'].'）';
        $operationLogObj->write_log('reship@ome',$reship_id,$memo);
        
        //[兼容]返回状态,如果失败则返回失败信息
        if($result['rsp'] == 'fail'){
            $error_msg = $result['msg'];
            return false;
        }
        
        return true;
    }
    
    /**
     * query
     * @param mixed $reship_id ID
     * @param mixed $error_msg error_msg
     * @return mixed 返回值
     */
    static public function query($reship_id, &$error_msg=null)
    {
        $reshipObj = app::get('ome')->model('reship');
        $branchLib = kernel::single('ome_branch');
        
        //退换货单信息
        $reshipInfo = $reshipObj->dump(array('reship_id'=>$reship_id), '*');
        
        //branch_bn
        $reshipInfo['branch_bn'] = $branchLib->getBranchBnById($reshipInfo['branch_id']);
        
        //wms_id
        $channel_id = $branchLib->getWmsIdById($reshipInfo['branch_id']);
        
        //request
        $result = kernel::single('erpapi_router_request')->set('wms', $channel_id)->reship_query($reshipInfo);
        if($result['rsp'] == 'fail'){
            $error_msg = $result['msg'];
            
            //添加异常：云交易商品不可售后
            $abnormal_status = ome_constants_reship_abnormal::__CONFIRM_CODE;
            $sql = "UPDATE sdb_ome_reship SET is_check='2',abnormal_status=abnormal_status | ". $abnormal_status .",sync_msg='". $error_msg ."' WHERE reship_id=".$reship_id;
            $reshipObj->db->exec($sql);
            
            return false;
        }else{
            //清除异常:云交易商品不可售后
            $abnormal_status = ome_constants_reship_abnormal::__CONFIRM_CODE;
            if(($reshipInfo['abnormal_status'] & $abnormal_status) ==  $abnormal_status){
                $sql = "UPDATE sdb_ome_reship SET abnormal_status=abnormal_status ^ ". $abnormal_status .",sync_msg='' WHERE reship_id=".$reship_id;
                $reshipObj->db->exec($sql);
            }
        }
        
        return true;
    }
    
    /**
     * 更新Logistics
     * @param mixed $reship_id ID
     * @param mixed $error_msg error_msg
     * @return mixed 返回值
     */
    static public function updateLogistics($reship_id, &$error_msg=null)
    {
        $reshipObj = app::get('ome')->model('reship');
        $corpObj = app::get('ome')->model('dly_corp');
        
        $branchLib = kernel::single('ome_branch');
        
        //退换货单信息
        $reshipInfo = $reshipObj->dump(array('reship_id'=>$reship_id), 'reship_id,reship_bn,order_id,source,branch_id,shop_type,return_logi_no,return_logi_name,t_begin');
        $order_id = $reshipInfo['order_id'];
        
        //wms_id
        $channel_id = $branchLib->getWmsIdById($reshipInfo['branch_id']);
        
        //branch_bn
        $branch_bn = $branchLib->getBranchBnById($reshipInfo['branch_id']);
        $reshipInfo['branch_bn'] = $branch_bn;
        
        //物流公司编码
        $corpInfo = $corpObj->dump(array('name'=>$reshipInfo['return_logi_name']), 'corp_id,type');
        $reshipInfo['return_logi_code'] = $corpInfo['type'];
        
        //request
        $result = kernel::single('erpapi_router_request')->set('wms', $channel_id)->reship_logistics($reshipInfo);
        if($result['rsp'] == 'fail'){
            $error_msg = $result['msg'];
            return false;
        }
        
        return true;
    }
    
    /**
     * selectAddress
     * @param mixed $reship_id ID
     * @param mixed $error_msg error_msg
     * @return mixed 返回值
     */
    static public function selectAddress($reship_id, &$error_msg=null)
    {
        $reshipObj = app::get('ome')->model('reship');
        $corpObj = app::get('ome')->model('dly_corp');
        
        $branchLib = kernel::single('ome_branch');
        
        //[防并发]判断OMS退货单对应京东多个售后服务单号,重复请求
        $cacheKeyName = sprintf("selectAddress_reship_%s", $reship_id);
        $cacheData = cachecore::fetch($cacheKeyName);
        if($cacheData !== false) {
            return true;
        }
        
        //退换货单信息
        $reshipInfo = $reshipObj->dump(array('reship_id'=>$reship_id), '*');
        $order_id = $reshipInfo['order_id'];
        $reship_bn = $reshipInfo['reship_bn'];
        
        //wms_id
        $channel_id = $branchLib->getWmsIdById($reshipInfo['branch_id']);
        
        //branch_bn
        $branch_bn = $branchLib->getBranchBnById($reshipInfo['branch_id']);
        $reshipInfo['branch_bn'] = $branch_bn;
        
        //request
        $result = kernel::single('erpapi_router_request')->set('wms', $channel_id)->reship_address($reshipInfo);
        if($result['rsp'] == 'fail'){
            $error_msg = $result['msg'];
            return false;
        }
        
        //只有抖音平台需要回传平台退货状态
        if(!in_array($reshipInfo['shop_type'], array('luban'))){
            return true;
        }
        
        //[防并发]判断重复请求(15秒之内不能重复)
        cachecore::store($cacheKeyName, date('YmdHis', time()), 15);
        
        //[抖音平台]请求同意退货(放入queue队列中执行)
        //todo：没有京东云交易寄件地址之前,推送抖音同意退货都失败了(返回,系统错误:无订单权限)
        $queueObj = app::get('base')->model('queue');
        $queueData = array(
                'queue_title' => '退货单号：'. $reship_bn .'回传平台同意退货状态',
                'start_time' => time(),
                'params' => array(
                        'sdfdata' => array('reship_id'=>$reship_id, 'order_id'=>$order_id),
                        'app' => 'oms',
                        'mdl' => 'reship',
                ),
                'worker' => 'ome_reship_luban.syncAfterSaleStatus',
        );
        $queueObj->save($queueData);
        
        return true;
    }
    
    /**
     * 获取AddressAreaId
     * @param mixed $reship_id ID
     * @param mixed $error_msg error_msg
     * @return mixed 返回结果
     */
    static public function getAddressAreaId($reship_id, &$error_msg=null)
    {
        $reshipObj = app::get('ome')->model('reship');
        $channelObj = app::get('channel')->model('channel');
        
        $branchLib = kernel::single('ome_branch');
        
        //退换货单信息
        $reshipInfo = $reshipObj->dump(array('reship_id'=>$reship_id), 'reship_id,branch_id,ship_area,ship_addr');
        
        //wms_id
        $channel_id = $branchLib->getWmsIdById($reshipInfo['branch_id']);
        
        //WMS配置信息
        $channelInfo = $channelObj->dump(array('channel_id'=>$channel_id), 'channel_id,crop_config');
        
        //没有设置"京标四级地址"直接返回
        if($channelInfo['crop_config']['address_type'] != 'j'){
            return true;
        }
        
        //收货地址
        $tempArea = explode(':', $reshipInfo['ship_area']);
        $tempArea = explode('/', $tempArea[1]);
        
        //params
        $params = array(
                'ship_province' => $tempArea[0], //省
                'ship_city' => $tempArea[1], //市
                'ship_district' => $tempArea[2], //区
                'ship_town' => $tempArea[3], //镇
                'ship_addr' => $reshipInfo['ship_addr'], //详细地址
        );
        
        //request
        $result = kernel::single('erpapi_router_request')->set('wms', $channel_id)->branch_getAreaId($params);
        if($result['rsp'] == 'fail'){
            $error_msg = $result['msg'];
            return false;
        }
        
        //返回int类型值：provinceid、cityid、streetid、townid
        return $result['data'];
    }
}
