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
 * 翱象订单Lib类
 *
 * @author wangbiao@shopex.cn
 * @version 2023.07.19
 */
class dchain_order extends dchain_abstract
{
    /**
     * 手工审核订单
     * @param $orderInfo
     * @param $branchInfo
     * @param $corpInfo
     * @return bool
     */

    public function combineOrder($orderInfo, $branchInfo, $corpInfo, $orderIds=array())
    {
        $orderExtendObj = app::get('ome')->model('order_extend');
        
        $order_id = $orderInfo['order_id'];
        $shop_id = $orderInfo['shop_id'];
        
        //order_extend
        $extendInfo = $orderExtendObj->dump(array('order_id'=>$order_id), '*');
        if(empty($extendInfo)){
            $error_msg = '订单没有扩展信息';
            return $this->error($error_msg);
        }
        
        //shop_id
        $extendInfo['shop_id'] = $shop_id;
        
        //[检查]建议配
        $checkResult = $this->_checkCombineOrderLogi($extendInfo, $corpInfo, $orderIds);
        if($checkResult['rsp'] != 'succ'){
            return $checkResult;
        }
        
        //[检查]建议仓
        $checkResult = $this->_checkCombineOrderBranch($extendInfo, $branchInfo, $orderIds);
        if($checkResult['rsp'] != 'succ'){
            return $checkResult;
        }
        
        return $this->succ();
    }
    
    /**
     * 检查审单时的建议配
     * 
     * @param $extendInfo
     * @param $corpInfo
     * @param $orderIds
     * @return array|void
     */
    public function _checkCombineOrderLogi($extendInfo, $corpInfo, $orderIds=null)
    {
        $shop_id = $extendInfo['shop_id'];
        
        //extend_field
        $extend_field = array();
        if($extendInfo['extend_field']){
            $extend_field = json_decode($extendInfo['extend_field'], true);
        }
        
        //check
        if(empty($extend_field['biz_delivery_type'])){
            //$error_msg = '没有建议配';
            return $this->succ();
        }
        
        //[合单]获取多个订单的扩展信息
        $logiData = array();
        if($orderIds && count($orderIds) > 1){
            $logiData = $this->getMultipleOrderLogi($orderIds);
            
            //只要有一个订单择配是：2类型,就赋值
            if($extend_field['biz_delivery_type'] != '2' && $logiData['biz_delivery_types'][2]){
                $extend_field['biz_delivery_type'] = '2';
            }
        }
        
        //建议物流公司
        if($extend_field['biz_delivery_type'] == '2'){
            //必须使用翱象建议的配送物流公司
            //合单时,获取多个订单的交集指定物流公司
            $multiBlackCodes = array();
            if($logiData){
                //[重置]建议配编码
                if($logiData['biz_delivery_code']){
                    $extendInfo['biz_delivery_code'] = $logiData['biz_delivery_code'];
                }
                
                //[重置]白名单
                if($logiData['white_delivery_cps']){
                    $extendInfo['white_delivery_cps'] = $logiData['white_delivery_cps'];
                }
                
                //[list]黑名单列表
                if($logiData['black_delivery_cps']){
                    $multiBlackCodes = json_decode($logiData['black_delivery_cps'], true);
                }
            }
            
            //check
            if(empty($extendInfo['biz_delivery_code']) && empty($extendInfo['white_delivery_cps'])){
                $error_msg = '翱象没有给指定的建议物流公司';
                return $this->error($error_msg);
            }
            
            $biz_delivery_codes = json_decode($extendInfo['biz_delivery_code'], true);
            $white_delivery_cps = json_decode($extendInfo['white_delivery_cps'], true);
            
            //[翱象]建议物流公司
            $axCorpIds = $this->getConfirmLogistics($biz_delivery_codes, $shop_id);
            
            //指定物流公司
            if($biz_delivery_codes && $axCorpIds){
                if(in_array($corpInfo['corp_id'], $axCorpIds)){
                    return $this->succ();
                }
            }
            
            //合单时,先检查黑名单列表
            if($multiBlackCodes && in_array($corpInfo['type'], $multiBlackCodes)){
                if($white_delivery_cps && in_array($corpInfo['type'], $white_delivery_cps)){
                    //物流公司是白名单中的
                }else{
                    $error_msg = '【合单发货】当前订单在猫淘平台对消费者进行了服务承诺，建议按照平台要求进行择配，否则存在赔付风险！';
                    return $this->error($error_msg);
                }
            }
            
            //白名单
            if($white_delivery_cps && in_array($corpInfo['type'], $white_delivery_cps)){
                return $this->succ();
            }

            if ($extendInfo['cpup_service'] == '218') {
                return $this->error('该订单为加价发顺丰平台集采订单，请修改为顺丰快递');
            }
            
            $error_msg = '当前订单在猫淘平台对消费者进行了服务承诺，建议按照平台要求进行择配，否则存在赔付风险。';
            return $this->error($error_msg);
        }elseif($extend_field['biz_delivery_type'] == '3'){
            //必须识别配送黑名单
            //合单时,获取多个订单的合集黑名单物流公司
            $multiWhiteCodes = array();
            if($orderIds && count($orderIds) > 1){
                //[重置]黑名单
                if($logiData['black_delivery_cps']){
                    $extendInfo['black_delivery_cps'] = $logiData['black_delivery_cps'];
                }
                
                //[list]白名单列表
                if($logiData['white_delivery_cps']){
                    $multiWhiteCodes = json_decode($logiData['white_delivery_cps'], true);
                }
            }
            
            //check
            if(empty($extendInfo['black_delivery_cps'])){
                $error_msg = '翱象没有给指定的配送黑名单';
                return $this->error($error_msg);
            }
            
            $black_delivery_cps = json_decode($extendInfo['black_delivery_cps'], true);
            $black_delivery_cps = ($black_delivery_cps ? $black_delivery_cps : array());
            if(in_array($corpInfo['type'], $black_delivery_cps)){
                if($multiWhiteCodes && in_array($corpInfo['type'], $multiWhiteCodes)){
                    //物流公司是白名单中的
                }else{
                    $error_msg = '平台建议当前线路不要使用您选择的配品牌，存在电子面单无法打印风险！';
                    return $this->error($error_msg);
                }
            }
        }
        
        return $this->succ();
    }
    
    /**
     * 检查审单时的建议仓
     * 
     * @param $extendInfo
     * @param $branchInfo
     * @param $orderIds
     * @return array|void
     */
    public function _checkCombineOrderBranch($extendInfo, $branchInfo, $orderIds=null)
    {
        //extend_field
        $extend_field = array();
        if($extendInfo['extend_field']){
            $extend_field = json_decode($extendInfo['extend_field'], true);
        }
        
        //check
        if(empty($extend_field['biz_sd_type'])){
            //$error_msg = '没有建议仓';
            return $this->succ();
        }
        
        //建议仓
        if($extend_field['biz_sd_type'] == '2'){
            //check
            if(empty($extend_field['biz_store_code'])){
                //平台确定,翱象没有给指定的仓库编码时,不要提醒报错;
                return $this->succ();
            }
            
            if($extend_field['biz_store_code'] != $branchInfo['branch_bn']){
                $error_msg = '当前订单在猫淘平台对消费者进行了服务承诺：当日发次日达，建议按照平台要求进行择仓，否则存在赔付风险;';
                return $this->error($error_msg);
            }
            
            //[合单时]检查多个订单,建议仓编码不同时,需要提醒
            if($orderIds && count($orderIds) > 1){
                $storeCodes = $this->getMultipleStoreCodes($orderIds);
                if($storeCodes && count($storeCodes) > 1){
                    $error_msg = '当前多个订单有不同的承诺服务，合单后无法满足所有服务要求;';
                    return $this->error($error_msg);
                }
            }
        }
        
        return $this->succ();
    }
    
    /**
     * 获取审核订单的指定快递
     * 
     * @param $orderInfo
     * @param $orderIds
     * @param $branch_id 审核订单选择的仓库ID
     * @return void
     */
    public function getOmeautoOrderLogi($orderInfo, $orderIds=null, $branch_id=0)
    {
        $orderExtendObj = app::get('ome')->model('order_extend');
        $cropObj = app::get('ome')->model('dly_corp');
        
        $order_id = $orderInfo['order_id'];
        $shop_id = $orderInfo['shop_id'];
        
        //order_extend
        $extendInfo = $orderExtendObj->dump(array('order_id'=>$order_id), '*');
        if(empty($extendInfo)){
            $error_msg = '订单没有扩展信息';
            return $this->error($error_msg);
        }
        
        //extend_field
        $extend_field = array();
        if($extendInfo['extend_field']){
            $extend_field = json_decode($extendInfo['extend_field'], true);
        }
        
        //check
        if(!in_array($extend_field['biz_delivery_type'], array('1', '2', '3'))){
            $error_msg = '没有建议快递';
            return $this->error($error_msg);
        }
        
        //switch
        switch ($extend_field['biz_delivery_type'])
        {
            case '1':
                //合单时,获取多个订单的交集指定物流公司
                if($orderIds && count($orderIds) > 1){
                    $logiData = $this->getMultipleOrderLogi($orderIds);
                    
                    //重置值
                    if($logiData['biz_delivery_code']){
                        $extendInfo['biz_delivery_code'] = $logiData['biz_delivery_code'];
                    }
                    
                    //重置值
                    if($logiData['white_delivery_cps']){
                        $extendInfo['white_delivery_cps'] = $logiData['white_delivery_cps'];
                    }
                }
                
                //json
                $biz_delivery_codes = json_decode($extendInfo['biz_delivery_code'], true);
                $white_delivery_cps = json_decode($extendInfo['white_delivery_cps'], true);
                
                //biz_delivery_type=1时,是弱建议,可以使用白名单
                if(empty($biz_delivery_codes)){
                    $biz_delivery_codes = $white_delivery_cps;
                }
                
                //check
                if(empty($biz_delivery_codes)){
                    $error_msg = '翱象没有建议物流公司';
                    return $this->error($error_msg);
                }
                
                $axCorpIds = $this->getConfirmLogistics($biz_delivery_codes, $shop_id);
                if(empty($axCorpIds)){
                    $error_msg = '未获取到翱象物流公司';
                    return $this->error($error_msg);
                }
                
                $corpList = $cropObj->getList('*', array('corp_id'=>$axCorpIds), 0, -1, 'weight DESC');
                if(empty($corpList)){
                    $error_msg = '没有可用的翱象物流公司';
                    return $this->error($error_msg);
                }
                $corpList = array_column($corpList, null, 'corp_id');
                
                //读取仓库关联的物流公司列表
                $branchCorpObj = app::get('ome')->model('branch_corp');
                $branchCorpList = $branchCorpObj->getList('*', array('branch_id'=>$branch_id), 0, -1);
                if(empty($branchCorpList)){
                    $error_msg = '没有仓库关联的物流公司列表';
                    return $this->error($error_msg, array('flag'=>'warning'));
                }
                
                //使用翱象推荐的物流公司
                foreach ($branchCorpList as $corpKey => $corpVal)
                {
                    $corp_id = $corpVal['corp_id'];
                    
                    if($corpList[$corp_id]){
                        return $this->succ('获取发货物流成功', array('corpInfo'=>$corpList[$corp_id]));
                    }
                }
                
                return $this->error('没有匹配翱象快递', array('flag'=>'warning'));
                
                break;
            case '2':
                //合单时,获取多个订单的交集指定物流公司
                if($orderIds && count($orderIds) > 1){
                    $logiData = $this->getMultipleOrderLogi($orderIds);
                    
                    //重置值
                    if($logiData['biz_delivery_code']){
                        $extendInfo['biz_delivery_code'] = $logiData['biz_delivery_code'];
                    }
                    
                    if($logiData['white_delivery_cps']){
                        $extendInfo['white_delivery_cps'] = $logiData['white_delivery_cps'];
                    }
                }
                
                //check
                if(empty($extendInfo['biz_delivery_code']) && empty($extendInfo['white_delivery_cps'])){
                    $error_msg = '翱象没有给指定的建议物流公司';
                    return $this->error($error_msg, array('flag'=>'warning'));
                }
                
                $biz_delivery_codes = json_decode($extendInfo['biz_delivery_code'], true);
                $white_delivery_cps = json_decode($extendInfo['white_delivery_cps'], true);
                
                //建议快递名单
                if($biz_delivery_codes){
                    $axCorpIds = $this->getConfirmLogistics($biz_delivery_codes, $shop_id);
                    if($axCorpIds){
                        $corpList = $cropObj->getList('*', array('corp_id'=>$axCorpIds), 0, 1, 'weight DESC');
                        if($corpList){
                            return $this->succ('获取发货物流成功', array('corpInfo'=>$corpList[0]));
                        }
                    }
                }
                
                //快递白名单
                if($white_delivery_cps){
                    $corpList = $cropObj->getList('*', array('type'=>$white_delivery_cps), 0, 1, 'weight DESC');
                    if($corpList){
                        return $this->succ('获取发货物流成功', array('corpInfo'=>$corpList[0]));
                    }
                }
                
                return $this->error('没有可指定的快递', array('flag'=>'warning'));
                
                break;
            case '3':
                //合单时,获取多个订单的合集黑名单物流公司
                if($orderIds && count($orderIds) > 1){
                    $logiData = $this->getMultipleOrderLogi($orderIds);
                    
                    //重置值
                    if($logiData['black_delivery_cps']){
                        $extendInfo['black_delivery_cps'] = $logiData['black_delivery_cps'];
                    }
                }
                
                //check
                if(empty($extendInfo['black_delivery_cps'])){
                    $error_msg = '翱象没有给指定的配送黑名单';
                    return $this->error($error_msg);
                }
                
                $black_delivery_cps = json_decode($extendInfo['black_delivery_cps'], true);
                
                $error_msg = '指定的黑名单快递';
                return $this->error($error_msg, array('black_delivery_cps'=>$black_delivery_cps));
                break;
        }
        
        return $this->error('没有可指定的快递!');
    }
    
    /**
     * 合并订单时,检查快递是否允许合单
     * 
     * @param $comExtendInfo
     * @param $orderExtendInfo
     * @return bool
     */
    public function checkCombineLogistics($comExtendInfo, $orderExtendInfo)
    {
        //check
        if($comExtendInfo['order_id'] == $orderExtendInfo['order_id']){
            return true;
        }
        
        //check
        if(!in_array($orderExtendInfo['extend_field']['biz_delivery_type'], array('2', '3'))){
            //check
            if(in_array($comExtendInfo['extend_field']['biz_delivery_type'], array('2', '3'))){
                //过滤合并订单择配是2、3状态
                return false;
            }else{
                //择配不是2、3状态时,直接允许合单
                return true;
            }
        }
        
        //check建议配类型不一样,不能合单
        if($comExtendInfo['extend_field']['biz_delivery_type'] != $orderExtendInfo['extend_field']['biz_delivery_type']){
            return false;
        }
        
        //[原订单]建议快递名单
        $biz_delivery_code = array();
        if($orderExtendInfo['biz_delivery_code']){
            $biz_delivery_code = json_decode($orderExtendInfo['biz_delivery_code'], true);
        }
        
        //[原订单]快递白名单
        $white_delivery_cps = array();
        if($orderExtendInfo['white_delivery_cps']){
            $white_delivery_cps = json_decode($orderExtendInfo['white_delivery_cps'], true);
        }
        
        //[原订单]快递黑名单
        $black_delivery_cps = array();
        if($orderExtendInfo['black_delivery_cps']){
            $black_delivery_cps = json_decode($orderExtendInfo['black_delivery_cps'], true);
        }
        
        //[合单订单]建议的物流公司列表
        $comBizCodes = ($comExtendInfo['biz_delivery_code'] ? json_decode($comExtendInfo['biz_delivery_code'], true) : array());
        $comWhiteCodes = ($comExtendInfo['white_delivery_cps'] ? json_decode($comExtendInfo['white_delivery_cps'], true) : array());
        $comBlackCodes = ($comExtendInfo['black_delivery_cps'] ? json_decode($comExtendInfo['black_delivery_cps'], true) : array());
        
        //建议配类型
        switch($orderExtendInfo['extend_field']['biz_delivery_type'])
        {
            //建议配类型为2时（针对biz_delivery_type=2的订单取配交集,要求取同配）
            case '2':
                //原订单没有建议快递名单和快递白名单,直接允许合单
                if(empty($biz_delivery_code) && empty($white_delivery_cps)){
                    return true;
                }
                
                //没有建议快递名单和快递白名单,不能合单
                if(empty($comBizCodes) && empty($comWhiteCodes)){
                    return false;
                }
                
                $is_check = false;
                if(array_intersect($biz_delivery_code, $comBizCodes)){
                    $is_check = true;
                }elseif(array_intersect($white_delivery_cps, $comWhiteCodes)){
                    $is_check = true;
                }
                
                //check没有交集的快递,不能合单
                if(!$is_check){
                    return false;
                }
                break;
            //建议配类型为3时（针对biz_delivery_type=3的订单取并集,要求去掉并集黑名单）
            case '3':
                $logiList = array_merge($biz_delivery_code, $white_delivery_cps);
                if(empty($logiList) || empty($comBlackCodes)){
                    return true;
                }
                
                $tempBlackCodes = array_flip($comBlackCodes);
                foreach ($logiList as $logiKey => $logiVal)
                {
                    if($tempBlackCodes[$logiVal]){
                        unset($logiList[$logiKey]);
                    }
                }
                
                //没有建议的快递
                if(empty($logiList)){
                    return false;
                }
                break;
            default:
                return true;
        }
        
        return true;
    }
    
    /**
     * 物流服务标签列表
     * 
     * @return void
     */
    public function getPromiseServiceList()
    {
        $promise_services = array(
            '送货上门' => '送货上门',
            '次日达' => '次日达',
            '24小时发' => '24小时发',
            '今日发' => '今日发',
            '官方物流' => '官方物流',
            '新疆集运' => '新疆集运',
            '按需配送' => '按需配送',
        );
        
        return $promise_services;
    }
    
    /**
     * 获取审核订单使用的物流公司
     * 
     * @param $biz_delivery_codes
     * @param $shop_id
     * @return void
     */
    public function getConfirmLogistics($biz_delivery_codes, $shop_id)
    {
        $aoLogiMdl = app::get('dchain')->model('aoxiang_logistics');
        
        //check
        if(empty($biz_delivery_codes) || empty($shop_id)){
            return array();
        }
        
        //list
        $aoBranchList = $aoLogiMdl->getList('*', array('erp_code'=>$biz_delivery_codes, 'shop_id'=>$shop_id));
        if(empty($aoBranchList)){
            return array();
        }
        
        return array_column($aoBranchList, 'corp_id', 'erp_code');
    }
    
    /**
     * 获取多个订单推送快递物流的交集
     * 
     * @param $orderIds
     * @return array
     */
    public function getMultipleOrderLogi($orderIds)
    {
        $orderExtendObj = app::get('ome')->model('order_extend');
        
        //list
        $extendList = $orderExtendObj->getList('*', array('order_id'=>$orderIds));
        if(empty($extendList)){
            return array();
        }
        
        $logiData = array();
        $biz_delivery_types = array();
        
        //get
        $bizDeliveryCodes = array();
        $whiteDeliveryCps = array();
        $blackDeliveryCps = array();
        foreach($extendList as $itemKey => $extendInfo)
        {
            $order_id = $extendInfo['order_id'];
            
            //check
            if(empty($extendInfo['biz_delivery_code']) && empty($extendInfo['white_delivery_cps'])){
                continue;
            }
            
            //extend_field
            $extend_field = array();
            if($extendInfo['extend_field']){
                $extend_field = json_decode($extendInfo['extend_field'], true);
            }
            
            //所有订单的择配类型
            $biz_delivery_type = intval($extend_field['biz_delivery_type']);
            $biz_delivery_types[$biz_delivery_type][$order_id] = $order_id;
            
            //logi
            $biz_delivery_codes = json_decode($extendInfo['biz_delivery_code'], true);
            $white_delivery_cps = json_decode($extendInfo['white_delivery_cps'], true);
            $black_delivery_cps = json_decode($extendInfo['black_delivery_cps'], true);
            
            //建议快递名单
            if($biz_delivery_codes){
                //取交集
                if($bizDeliveryCodes){
                    $bizDeliveryCodes = array_intersect($bizDeliveryCodes, $biz_delivery_codes);
                }else{
                    $bizDeliveryCodes = $biz_delivery_codes;
                }
            }
            
            //快递白名单
            if($white_delivery_cps){
                //取交集
                if($whiteDeliveryCps){
                    $whiteDeliveryCps = array_intersect($whiteDeliveryCps, $white_delivery_cps);
                }else{
                    $whiteDeliveryCps = $white_delivery_cps;
                }
            }
            
            //黑名单取合集
            if($black_delivery_cps){
                $blackDeliveryCps = array_merge($blackDeliveryCps, $black_delivery_cps);
                $blackDeliveryCps = array_filter($blackDeliveryCps);
            }
        }
        
        //data
        $logiData['biz_delivery_code'] = ($bizDeliveryCodes ? json_encode($bizDeliveryCodes) : '');
        $logiData['white_delivery_cps'] = ($whiteDeliveryCps ? json_encode($whiteDeliveryCps) : '');
        $logiData['black_delivery_cps'] = ($blackDeliveryCps ? json_encode($blackDeliveryCps) : '');
        $logiData['biz_delivery_types'] = $biz_delivery_types;
        
        return $logiData;
    }
    
    /**
     * [合单时]检查多个订单,建议仓编码不同时,需要提醒
     * 
     * @param $orderIds
     * @return array
     */
    public function getMultipleStoreCodes($orderIds)
    {
        $orderExtendObj = app::get('ome')->model('order_extend');
    
        //list
        $extendList = $orderExtendObj->getList('*', array('order_id'=>$orderIds));
        if(empty($extendList)){
            return array();
        }
        
        //get
        $storeCodes = array();
        foreach($extendList as $itemKey => $extendInfo)
        {
            $order_id = $extendInfo['order_id'];
            
            //check
            if(empty($extendInfo['extend_field'])){
                continue;
            }
            
            //extend_field
            $extend_field = array();
            if($extendInfo['extend_field']){
                $extend_field = json_decode($extendInfo['extend_field'], true);
            }
            
            //建议仓不是：2类型则跳过
            if($extend_field['biz_sd_type'] != '2'){
                continue;
            }
            
            $biz_store_code = $extend_field['biz_store_code'];
            
            //check
            if(empty($biz_store_code)){
                continue;
            }
            
            $storeCodes[$biz_store_code] = $biz_store_code;
        }
        
        return $storeCodes;
    }
    
    /**
     * 获取平台推荐与黑名单物流列表
     * 
     * @param $order_id
     * @param $error_msg
     * @return array|null
     */
    public function getRecommendLogis($order_id, &$error_msg=null)
    {
        $orderExtendObj = app::get('ome')->model('order_extend');
        
        //order_extend
        $extendInfo = $orderExtendObj->dump(array('order_id'=>$order_id), '*');
        if(empty($extendInfo)){
            $error_msg = '订单没有扩展信息';
            return array();
        }
        
        //extend_field
        $extend_field = array();
        if($extendInfo['extend_field']){
            $extend_field = json_decode($extendInfo['extend_field'], true);
        }
        
        //建议快递名单
        $biz_delivery_codes = array();
        if($extendInfo['biz_delivery_code']){
            $tempList = json_decode($extendInfo['biz_delivery_code'], true);
            if($tempList){
                foreach ($tempList as $key => $code)
                {
                    $code = trim($code);
                    $biz_delivery_codes[$code] = $code;
                }
            }
        }
        
        //快递白单
        $white_delivery_cps = array();
        if($extendInfo['white_delivery_cps']){
            $tempList = json_decode($extendInfo['white_delivery_cps'], true);
            if($tempList){
                foreach ($tempList as $key => $code)
                {
                    $code = trim($code);
                    $white_delivery_cps[$code] = $code;
                }
            }
        }
        
        //快递黑名单
        $black_delivery_cps = array();
        if($extendInfo['black_delivery_cps']){
            $tempList = json_decode($extendInfo['black_delivery_cps'], true);
            if($tempList){
                foreach ($tempList as $key => $code)
                {
                    $code = trim($code);
                    $black_delivery_cps[$code] = $code;
                }
            }
        }
        
        //建议物流公司
        if($extend_field['biz_delivery_type'] == '1' && empty($biz_delivery_codes)){
            //biz_delivery_type=1时,是弱建议,可以使用白名单
            $biz_delivery_codes = $white_delivery_cps;
        }elseif($extend_field['biz_delivery_type'] == '2'){
            //必须使用翱象建议的物流公司
        }elseif($extend_field['biz_delivery_type'] == '3'){
            //翱象给出的物流公司黑名单
        }
        
        return array('biz_delivery_codes'=>$biz_delivery_codes, 'black_delivery_cps'=>$black_delivery_cps);
    }
}
