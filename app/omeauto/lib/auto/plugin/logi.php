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
 * 设置并检查物流号
 *
 * @author hzjsq@msn.com
 * @version 0.1b
 */

class omeauto_auto_plugin_logi extends omeauto_auto_plugin_abstract implements omeauto_auto_plugin_interface {

    /**
     * 是否支持批量审单
     */
    protected $__SUP_REP_ROLE = true;

    /**
     * 快递配置信息
     * @var $array
     */
    static $corpList = array();

    /**
     * 电子面单来源类型
     * @var $array
     */
    static $channelType = array();

    /**
     * 快递公司地区配置
     * @var Array
     */
    static $corpArea = array();

    /**
     * 地区配置信息
     * @var Array
     */
    static $region = array();

    /**
     * 状态码
     * @var integer
     */
    protected $__STATE_CODE = omeauto_auto_const::__LOGI_CODE;
    
    /**
     * 快递黑名单列表
     */
    static $_blackLogistics = array();
    
    /**
     * 开始处理
     *
     * @param omeauto_auto_group_item $group 要处理的订单组
     * @return Array
     */
    public function process(& $group, &$confirmRoles=null) {
        $this->choose($group, $confirmRoles);
        $orders = $group->getOrders();
        $corp = $group->getDlyCorp();
        if(empty($corp)) {
            return;
        }
        foreach($orders as $val) {
            // 强制指定、店铺绑定等路径不能静默替换物流，只在最终校验不合规时挂起。
            $homeDeliveryError = '';
            $homeDeliveryLib = kernel::single('ome_order_platform_pinduoduo_chargehomedelivery');
            if (!$homeDeliveryLib->validate(
                $val,
                $group->getBranchId(),
                $corp,
                $homeDeliveryError
            )) {
                $group->setDlyCorp([]);
                $group->setOrderStatus('*', $this->getMsgFlag());
                $group->setStatus(
                    omeauto_auto_group_item::__OPT_HOLD,
                    $this->_getPlugName(),
                    $val['order_bn'].' '.$homeDeliveryError
                );
                return;
            }

            if($val['shop_type'] == '360buy') {
                if(kernel::single('ome_bill_label_shsm')->isTinyPieces($val['order_id']) && $corp['channel_id']) {
                    $extendObj = app::get('logisticsmanager')->model('channel_extend');
                    $extend    = $extendObj->dump(array('channel_id' => $corp['channel_id']), 'addon');
                    $extend['addon'] = is_array($extend['addon']) ? $extend['addon'] : [];
                    if(is_array($extend['addon']['DELIVERY_TO_DOOR']) && $extend['addon']['DELIVERY_TO_DOOR']['value']) {
                        continue;
                    }
                    $group->setDlyCorp([]);
                    $group->setOrderStatus('*', $this->getMsgFlag());
                    $group->setStatus(omeauto_auto_group_item::__OPT_HOLD, $this->_getPlugName(), $val['order_bn'].' 是中小件送货上门，需要物流公司支持');
                    return;
                }
    
                // 京东集运必须使用京东无界电子面单发货，否则会回写失败
                $LabelLib = kernel::single('ome_bill_label');
                $labelList = $LabelLib->getLabelFromOrder($val['order_id']);
                if ($labelList && $corp['channel_id']) {
                    $labelList = array_column($labelList, 'label_code');
                    if (in_array('SOMS_GNJY', $labelList)) {
                        $mdlChannel = app::get('logisticsmanager')->model('channel');
                        $filter = array(
                            'channel_type'   =>  ['360buy','jdalpha'],
                            'status'         =>  'true',
                            'channel_id' => $corp['channel_id']
                        );
                        $channel = $mdlChannel->getList('channel_id', $filter);
                        if (!$channel) {
                            $group->setDlyCorp([]);
                            $group->setOrderStatus('*', $this->getMsgFlag());
                            $group->setStatus(omeauto_auto_group_item::__OPT_HOLD, $this->_getPlugName(), $val['order_bn'].' 是国内集运，需要使用京东电子面单');
                        }
                    }
                }
            }

            if(kernel::single('ome_order_bool_type')->isJDLVMI($val['order_bool_type'])){
                $corpData = kernel::single('logistics_rule')->getJDLVMICorp($val);
                if($corpData) {
                    $group->setDlyCorp($corpData);
                } else {
                    $group->setOrderStatus('*', $this->getMsgFlag());
                    $group->setStatus(omeauto_auto_group_item::__OPT_HOLD, $this->_getPlugName());
                }
    
                // 平台运单号
                $orderExtend = app::get('ome')->model('order_extend')->db_dump(array('order_id'=>$val['order_id']),'platform_logi_no');
    
                $group->setWaybillCode($orderExtend['platform_logi_no']);
    
                return null;
            }


        }
    }

    /**
     * 选择物流
     *
     * @param omeauto_auto_group_item $group
     * @param array $confirmRoles
     * @return void
     */
    private function choose(&$group, &$confirmRoles=null) {
        $orderTypeLib = kernel::single('ome_order_bool_type');
        $axOrderLib = kernel::single('dchain_order');
        
        $branchId = $group->getBranchId();
        $isStoreBranch = $group->isStoreBranch();

        //由于库存判断不满足条件，没有聚焦到仓库，不判断物流公司的选择逻辑
        if(!$branchId){
            return true;
        }
        $branchIdCorpId = $group->getBranchIdCorpId();
        if($branchIdCorpId && $branchIdCorpId[$branchId]) {
            $corp = app::get('ome')->model('dly_corp')->getList('corp_id, name, type, is_cod, weight, channel_id, shop_id, tmpl_type', array('corp_id'=>$branchIdCorpId[$branchId]), 0, 1);
            if($corp){
                $group->setDlyCorp($corp[0]);
            }else{
                //不能匹配
                $group->setOrderStatus('*', $this->getMsgFlag());
                $group->setStatus(omeauto_auto_group_item::__OPT_HOLD, $this->_getPlugName(), '未查询到仓库对应的物流');
            }
            return true;
        }
        //如果是门店仓设置相应的物流公司
        if (app::get('o2o')->is_installed() && $isStoreBranch) {
                $dlyType = $group->getStoreDlyType();
                if($dlyType == 'o2o_ship') {
                    $cfilter =  array('corp_id'=>2);
                } else {
                    $cfilter =  array('type'=>$dlyType);
                }
                $corp = app::get('ome')->model('dly_corp')->getList('corp_id, name, type, is_cod, weight, channel_id, shop_id, tmpl_type',$cfilter, 0, 1);
                if($corp){
                    $group->setDlyCorp($corp[0]);
                    return true;
                }else{
                    //不能匹配
                    $group->setOrderStatus('*', $this->getMsgFlag());
                    $group->setStatus(omeauto_auto_group_item::__OPT_HOLD, $this->_getPlugName(), '门店对应的物流未找到');
                }
        }else{
            //电商仓物流优选逻辑处理
            /* 店铺物流绑定 start */
            $shopCropObj = app::get('ome')->model('shop_dly_corp');
            $shopObj = app::get('ome')->model("shop");
            $cropObj = app::get('ome')->model('dly_corp');

            $orders = $group->getOrders();
            
            //order_ids
            $orderIds = array_column($orders, 'order_id');
            
            //list
            foreach($orders as $val){
                $isAoxiang = false;
                
                //淘宝物流升级制定物流公司
                if ($val['shipping'] && kernel::single('ome_order_bool_type')->isCPUP($val['order_bool_type'])) {
                    $corpData = kernel::single('logistics_rule')->getCorpIdByCode($val['shipping'],
                        $group->getBranchId(), $val['order_id']);
                    if ($corpData) {
                        $group->setDlyCorp($corpData);
                    } else {
                        $group->setOrderStatus('*', $this->getMsgFlag());
                        $group->setStatus(omeauto_auto_group_item::__OPT_HOLD, $this->_getPlugName(), '物流升级对应的定制物流公司不存在');
                    }
                    return null;
                }
                if(kernel::single('ome_order_bool_type')->isJITX($val['order_bool_type'])) {
                    $corpData = kernel::single('logistics_rule')->getJITXCorp($val);
                    if($corpData) {
                        $group->setDlyCorp($corpData);
                    } else {
                        $group->setOrderStatus('*', $this->getMsgFlag());
                        $group->setStatus(omeauto_auto_group_item::__OPT_HOLD, $this->_getPlugName(), 'jitx对应的物流公司不存在');
                    }

                    // 平台运单号
                    $orderExtend = app::get('ome')->model('order_extend')->db_dump(array('order_id'=>$val['order_id']),'platform_logi_no');

                    $group->setWaybillCode($orderExtend['platform_logi_no']);

                    return null;
                }

                if(empty($is_cod)) {
                    $filter['shop_id'] = $val['shop_id'];
                    $filter['crop_name'] = $val['shipping'];
                    $is_cod = $val['is_cod'];
                }

                // 爱库存初始化运单号
                if($val['shop_type'] === 'aikucun' && $val['shipping']){
                    $cropData = $cropObj->dump(array('type'=>$val['shipping'],'channel_id|than'=>'0','disable'=>'false'));
                    if (!$cropData){
                        $group->setOrderStatus('*', $this->getMsgFlag());
                        $group->setStatus(omeauto_auto_group_item::__OPT_HOLD, $this->_getPlugName(), '爱库存指定的物流公司不存在');
                        return null;
                    }
                    $group->setDlyCorp($cropData);

                    // 平台运单号
                    $orderExtend = app::get('ome')->model('order_extend')->db_dump(array('order_id'=>$val['order_id']),'platform_logi_no');

                    $waybill_arr = explode(',', $orderExtend['platform_logi_no']);
                    $group->setWaybillCode($waybill_arr[0]);
                    $group->setSubWaybillCode(array_slice($waybill_arr, 1));

                    return;
                }elseif(in_array($val['shop_type'], array('taobao', 'tmall'))) {
                    //是否翱象订单标识
                    if($orderTypeLib->isAoxiang($val['order_bool_type'])) {
                        $isAoxiang = true;
                    }
                    
                    //翱象订单推送的快递
                    if($isAoxiang){
                        $logiResult = $axOrderLib->getOmeautoOrderLogi($val, $orderIds, $branchId);
                        if($logiResult['rsp'] == 'succ' && $logiResult['data']['corpInfo']){
                            //设置物流公司
                            $group->setDlyCorp($logiResult['data']['corpInfo']);
                            return null;
                        }elseif($logiResult['rsp'] == 'fail' && $logiResult['data']['flag'] == 'warning'){
                            //没有物流公司,提示不能自动审单
                            $group->setOrderStatus('*', $this->getMsgFlag());
                            $group->setStatus(omeauto_auto_group_item::__OPT_HOLD, $this->_getPlugName(), '翱象获取物流公司失败：'.$logiResult['msg']);
                            return null;
                        }elseif($logiResult['rsp'] == 'fail' && $logiResult['data']['black_delivery_cps']){
                            //快递黑名单
                            self::$_blackLogistics = array_merge(self::$_blackLogistics, $logiResult['data']['black_delivery_cps']);
                            self::$_blackLogistics = array_unique(self::$_blackLogistics);
                        }
                    }
                }
                
                if($val['order_type'] == 'vopczc') {
                    $corpData = kernel::single('logistics_rule')->getVopczcCorp();
                    $group->setDlyCorp($corpData);
                    return null;
                }

                if ($val['shop_type'] == 'luban') {
                    $LabelLib = kernel::single('ome_bill_label');
                    $labelList = $LabelLib->getLabelFromOrder($val['order_id']);
                    if ($labelList) {
                        $labelList = array_column($labelList, 'label_code');
                        $isSelfWms = (kernel::single('ome_branch')->getNodetypBybranchId($group->getBranchId()) == 'selfwms');
                        // 抖店如果有顺丰包邮标记，用顺丰快递
                        if (in_array('sf_free_shipping', $labelList)) {
                            if($isSelfWms){
                                $corpData = kernel::single('logistics_rule')->getLubanShunfengCorp($val);
                            } else {
                                $corpData = app::get('ome')->model('dly_corp')->db_dump(['type' => 'SF']);
                            }
                            if ($corpData) {
                                $group->setDlyCorp($corpData);
                            } else {
                                $group->setOrderStatus('*', $this->getMsgFlag());
                                $group->setStatus(omeauto_auto_group_item::__OPT_HOLD, $this->_getPlugName(), '抖店有顺丰包邮标记，用顺丰快递');
                            }
                            return null;
                        }

                        // 中转订单必须使用抖音电子面单
                        if ($isSelfWms && in_array('XJJY', $labelList)) {
                            $corpData = kernel::single('logistics_rule')->getLubanCorp($val);
                            if ($corpData) {
                                $group->setDlyCorp($corpData);
                            } else {
                                $group->setOrderStatus('*', $this->getMsgFlag());
                                $group->setStatus(omeauto_auto_group_item::__OPT_HOLD, $this->_getPlugName(), '抖音中转订单必须使用抖音电子面单');
                            }
                            return null;
                        }
    
                        //自选物流发货
                        if (in_array(kernel::single('ome_bill_label')->isExpressMust(), $labelList)) {
                            $shippingInfo = kernel::single('ome_hqepay_shipping')->getLogiNameType($val['shipping'], strtolower($val['shop_type']));
                            if ($shippingInfo) {
                                $corpData = app::get('ome')->model('dly_corp')->db_dump(['type' => $shippingInfo['logi_type']]);
                                if ($corpData) {
                                    $group->setDlyCorp($corpData);
                                } else {
                                    $group->setOrderStatus('*', $this->getMsgFlag());
                                    $group->setStatus(omeauto_auto_group_item::__OPT_HOLD, $this->_getPlugName(), '抖店有自选物流发货，用自选物流发货');
                                }
                                return null;
                            }
                        }
                    }
                }

                
    
                //工小达
                if (kernel::single('ome_bill_label')->getBillLabelInfo($val['order_id'], 'order', kernel::single('ome_bill_label')->isSomsGxd())) {
                    $corpData = kernel::single('logistics_rule')->getChannelCorpList('jdgxd');
                    if ($corpData) {
                        $extendInfo         = app::get('ome')->model('order_extend')->db_dump(array('order_id' => $val['order_id']), 'order_id,biz_delivery_code,white_delivery_cps');
                        $white_delivery_cps = json_decode($extendInfo['white_delivery_cps'], true);
                        foreach ($corpData as $kCorp => $vCorp) {
                            if (!in_array($vCorp['type'], (array)$white_delivery_cps)) {
                                unset($corpData[$kCorp]);
                                continue;
                            }
                        }
                        if (!$corpData) {
                            $group->setOrderStatus('*', $this->getMsgFlag());
                            $group->setStatus(omeauto_auto_group_item::__OPT_HOLD, $this->_getPlugName(), '京东工小达必须使用京东电子面单及推荐物流公司');
                        }
                        $group->setDlyCorp($corpData[0]);
                    } else {
                        $group->setOrderStatus('*', $this->getMsgFlag());
                        $group->setStatus(omeauto_auto_group_item::__OPT_HOLD, $this->_getPlugName(), '京东工小达必须使用京东电子面单及推荐物流公司');
                    }
                    return null;
                }

                //小时达订单且是平台运力时，强制使用指定的物流公司
                $xiaoshiInfo = kernel::single('ome_bill_label')->isXiaoshiDa($val['order_id']);
                if($xiaoshiInfo['is_xiaoshi_da'] && $val['shipping'] && $xiaoshiInfo['is_platform_delivery']){
                    // 根据订单shipping查找对应的物流公司
                    $corpObj = app::get('ome')->model('dly_corp');
                    $corpData = $corpObj->dump(['type' => $val['shipping'], 'disabled' => 'false'], 'corp_id, name, type, is_cod, weight, channel_id, shop_id, tmpl_type');
                    if($corpData){
                        $group->setDlyCorp($corpData);
                        return null;
                    } else {
                        $group->setOrderStatus('*', $this->getMsgFlag());
                        $group->setStatus(omeauto_auto_group_item::__OPT_HOLD, $this->_getPlugName(), '小时达订单指定的物流公司不存在：'.$val['shipping']);
                        return null;
                    }
                }
            }

            $shopData = $shopObj->dump($filter['shop_id']);
            $cropSet = false;
            if($shopData['crop_config']['cropBind']==1){
                $shopCrop = $shopCropObj->dump($filter);
                if($shopCrop['corp_id']>0){
                    $cropObj = app::get('ome')->model('dly_corp');
                    $cropData = $cropObj->dump($shopCrop['corp_id']);
                    if($cropData['corp_id']>0 && $cropData['disabled']=='false'){
                        $group->setDlyCorp($cropData);
                        $cropSet = true;
                    }
                }
                if($cropSet==false && $shopData['crop_config']['sysCrop']!=1){//未匹配物流且系统不自动选择
                    $group->setOrderStatus('*', $this->getMsgFlag());
                    $group->setStatus(omeauto_auto_group_item::__OPT_HOLD, $this->_getPlugName(), '未匹配店铺指定物流且配置为系统不自动选择');
                    $cropSet = true;

                }
            }
            /* 店铺物流绑定 end */
            //判断是否京东货到付款是否启用京东面单
            if ($shopData['shop_type'] == '360buy') {
                $jdcorpconf =  app::get('ome')->getConf('shop.jdcorp.config.'.$filter['shop_id']);
                if ($jdcorpconf['config'] == '1') {
                    $jdcorpList = $this->getjdcorp();
                    
                    if ($is_cod=='true') {//
                        
                        if ( $jdcorpList && $jdcorpList[$jdcorpconf['corp_id']]) {
                            
                            $group->setDlyCorp($jdcorpList[$jdcorpconf['corp_id']]);
                            $cropSet = true;
                        }
                        
                        if($cropSet==false){
                            $group->setOrderStatus('*', $this->getMsgFlag());
                            $group->setStatus(omeauto_auto_group_item::__OPT_HOLD, $this->_getPlugName(), '京东货到付款需使用京东物流');
                            $cropSet = true;
                        }
                    }
                    
                    if ($is_cod=='false' &&  $jdcorpconf['is_cod']=='1') {//货到付款也启用
                        
                        if ($jdcorpList[$jdcorpconf['corp_id']]) {
                               $group->setDlyCorp($jdcorpList[$jdcorpconf['corp_id']]);
                               $cropSet = true;
                        }
                    }
                }
                
            }

            $exrecommend_available = kernel::single('channel_func')->check_exrecommend_available();
           #如果开启智选物流功能，则获取快递鸟推荐的智选物流
            $channel_type = app::get('ome')->model('branch')->getChannelBybranchID($group->getBranchId());
           if($exrecommend_available && in_array($channel_type,array('selfwms'))){
               
                $exrecommend_corp_info =  kernel::single('ome_event_trigger_exrecommend_recommend')->exrecommend($group->getBranchId(),$group->get_order_data());
                $corpId = $exrecommend_corp_info['taobao']['exrecommend_corp_id'];
               if ($corpId > 0) {
                  //能匹配到物流公司
                   $group->setDlyCorp(self::$corpList[$corpId]);
                   $group->setWaybillCode($exrecommend_corp_info['taobao']['waybill_code']);
                   $cropSet = true;
               }
           }
           
           //[指定快递]针对淘宝和天猫订单
           if ($shopData['shop_type']=='taobao') {
               $order_ids = array();
               foreach($orders as $val){
                   $order_ids[] = $val['order_id'];
               }
           
               $cropData = kernel::single('ome_order_func')->get_assign_express($order_ids);
               if($cropData){
                   //能匹配到物流公司
                   $group->setDlyCorp($cropData);
                   $cropSet = true;
               }
           }
	        // 指定仓物流
	        $branch_corps    = $confirmRoles['special_corp'] ? @json_decode($confirmRoles['special_corp'], true) : array();
	        $specify_corp_id = is_array($branch_corps) && is_numeric($group->getBranchId()) && $branch_corps[$group->getBranchId()] ? $branch_corps[$group->getBranchId()] : '';
	
	        if ($cropSet == false && $confirmRoles['corpChoice'] == '2' && $specify_corp_id != 'auto') {
		        $filter = $cropData = array();
		
		        $filter['disabled'] = 'false';
		        $filter['corp_id']  = $specify_corp_id ? $specify_corp_id : $confirmRoles['corp_id'];
		
		        $filter['corp_id'] && $cropData = $cropObj->dump($filter);

		        if ($cropData) {
			        $group->setDlyCorp($cropData);
		        } else {
			        $group->setOrderStatus('*', $this->getMsgFlag());
			        $group->setStatus(omeauto_auto_group_item::__OPT_HOLD, $this->_getPlugName(), '指定仓物流不存在');
		        }
		
		        $cropSet = true;
	        }
	        
            if(!$cropSet || $cropSet==false){
                //自动匹配物流公司
                $this->initCropData();
                $corpId = $this->markSelectDlyCorp($group);

                if (!$corpId) {
                    $corpId = kernel::single('logistics_rule')->autoMatchDlyCorp($group->getShipArea(),$branchId,$group->getWeight(),$group->getShopType(),$filter['shop_id']);
                    if ($corpId > 0 && self::$corpList[$corpId]) {
                        //能匹配到物流公司

                        $group->setDlyCorp(self::$corpList[$corpId]);
                    } else {
                        //不能匹配
                        $group->setOrderStatus('*', $this->getMsgFlag());
                        $group->setStatus(omeauto_auto_group_item::__OPT_HOLD, $this->_getPlugName(), '自动物流规则匹配不到');
                    }
                } else {
                    $channel_id = self::$corpList[$corpId]['channel_id'];
                    if (!is_array($corpId) && (self::$corpList[$corpId]['tmpl_type']=='normal' || self::$channelType[$channel_id]=='ems' || (self::$channelType[$channel_id]=='wlb' && self::$corpList[$corpId]['shop_id']==$filter['shop_id']))) {
                        $mark = kernel::single('omeauto_auto_group_mark');
                        $corpId= $mark->fetchCorpId($corpId);
                        $group->setDlyCorp(self::$corpList[$corpId]);
                    } else {
                        //不能匹配
                        $group->setOrderStatus('*', $this->getMsgFlag());
                        $group->setStatus(omeauto_auto_group_item::__OPT_HOLD, $this->_getPlugName(), '备注匹配物流公司不到');
                    }
                }
            }
        }
    }

     /**
     * 获取该插件名称
     *
     * @param Void
     * @return String
     */
    public function getTitle() {

        return '无匹配物流';
    }

    /**
     * 根据收货地址自动匹配物流公司 （查看代码 已弃用  by wangjianjun 20170804）
     * @param String $shipArea 送货地址
     * @return mixed
     */
     function autoSelectDlyCorp($shipArea, $branchId, &$confirmRoles=null) {
        $regionId = preg_replace('/.*:([0-9]+)$/is', '$1', $shipArea);
        $this->initCropData();
        $regionPath = self::$region[$regionId];

        $regionIds = explode(',', $regionPath);
        foreach($regionIds as $key=>$val){
            if($regionIds[$key] == '' || empty($regionIds[$key])){
                unset($regionIds[$key]);
            }
        }
        if(count($regionIds)<3 && count($regionIds)>0){
            foreach(self::$region as $key=>$val){
                if(strpos($val,$regionPath)!==false && $regionPath != $val){
                    $childIds[] = $key;
                }
            }
            if(count($childIds)>0){
                $dlyAreaObj = app::get('ome')->model('dly_corp_area');
                $dlyCount = $dlyAreaObj->count(array('region_id'=>$childIds));
                if($dlyCount>0){
                    return 0;
                }
            }
        }

        //通过区域匹配可送达的物流公司
        $corpIds = $this->getCorpByArea($regionPath, $branchId);

        //在增加默认全部可送的快递公司
        if (empty($corpIds) && $confirmRoles['allDlyCrop'] != 1) {
            $corpIds = $this->getDefaultCorp($branchId);
        }

        //获取最佳物流
        $corpId = $this->getBestCorpId($corpIds);

        //根据设置返回物流公司ID
        return $corpId;
    }

    /**
     * 根据客服备注获取物流公司
     *
     * @param Void
     * @return mixed
     */
    private function markSelectDlyCorp(& $group) {

        $mark = kernel::single('omeauto_auto_group_mark');
        if (! $mark->useMark()) {

            return null;
        }
        $ret = array();
        $wCode = trim($mark->getCodeByFix('markDelivery'));
        foreach ($group->getOrders() as $order) {

            $markText = $this->getMark($order['mark_text']);

            $wRet = $mark->getMark($wCode, $markText);
            if (!empty($wRet)) {

                //$ret = array_merge($ret, $wRet);
                foreach ($wRet as  $wItem) {

                    if (!in_array($wItem, $ret)) {

                        $ret[] = $wItem;
                    }
                }
            }
        }

        //检查快递是否唯一，否则置为不可用
        if (!empty($ret)) {

             if (count($ret) == 1) {

                 return $ret[0];
             }  else {

                 $group->setOrderStatus('*', $mark->getMsgFlag('w'));
                 $group->setStatus(omeauto_auto_group_item::__OPT_HOLD, $this->_getPlugName());
                 return $ret;
             }

        } else {

            return false;
        }
    }

    /**
     * 获取全局可用的物流  (查看代码 已弃用 by wangjianjun 20170804)
     * @return Array
     */
    private function getDefaultCorp($branchId) {
        $corpIds = array();
        $branch_corp_lib = kernel::single("ome_branch_corp");
        $corp_ids = $branch_corp_lib->getCorpIdsByBranchId(array($branchId));
        foreach (self::$corpList as $corpId => $info) {
            if (!isset(self::$corpArea[$corpId]) && in_array($corpId,$corp_ids)) {
                $corpIds[$corpId] = true;
            }
        }
        return $corpIds;
    }

    /**
     * 获取最佳物流公司
     *
     * @param Array $corpIds 可用物流
     * @return Integer
     */
    private function getBestCorpId($corpIds) {

        //返回权重最高的
        $weight = -1;
        $id = 0;
        foreach ($corpIds as $corpId => $v) {

            if (self::$corpList[$corpId]['weight'] > $weight) {

                $weight = self::$corpList[$corpId]['weight'];
                $id = $corpId;
            }
        }

        return $id;
    }

    /**
     * 通过发货地区的地区路径，获取可匹配的快递公司 （查看代码 已弃用 by wangjianjun 20170804）
     * @param String $regionPath 发货地区的地区路径
     * @return Array;
     */
    private function getCorpByArea($regionPath, $branchId) {
        $corpIds = array();
        //先查找有区域配置的快递公司
        if (!empty($regionPath)) {
            $regionIds = explode(',', $regionPath);
            array_shift($regionIds);
            array_pop($regionIds);
            $branch_corp_lib = kernel::single("ome_branch_corp");
            $corp_ids = $branch_corp_lib->getCorpIdsByBranchId(array($branchId));
            foreach ($regionIds as $rId) {
                foreach(self::$corpArea as $corpId => $cRegion) {
                    if (in_array($rId, $cRegion) && in_array($corpId,$corp_ids)) {
                        $corpIds[$corpId] = true;
                    }
                }
            }
        }
        return $corpIds;
    }

    /**
     * 初始化快递公司配置
     *
     * @param void
     * @return void
     */
    private function initCropData() {
        if (!empty(self::$region)) {
            return;
        }

        //获取地区配置信息
        $regions = kernel::single('eccommon_regions')->getList('region_id,region_path');
        foreach ($regions as $row) {
            self::$region[$row['region_id']] = $row['region_path'];
        }
        unset($regions);

        //获取快递公司配置信息
        $corp = app::get('ome')->model('dly_corp')->getList('corp_id, name, type, is_cod, weight, channel_id, shop_id, tmpl_type', array('disabled' => 'false'), 0, -1, 'weight DESC');
        foreach($corp as $item) {
            //过滤黑名单快递
            if(in_array($item['type'], self::$_blackLogistics)){
                continue;
            }
            
            self::$corpList[$item['corp_id']] = $item;
        }
        unset($corp);

        //快递公司配送区域配置信息s
        $corpArea = app::get('ome')->model('dly_corp_area')->getList('*');
        foreach ($corpArea as $item) {

            self::$corpArea[$item['corp_id']][] = $item['region_id'];
        }
        unset($corpArea);

        //电子面单来源类型
        $channelObj = app::get("logisticsmanager")->model('channel');
        $channel = $channelObj->getList("channel_id,channel_type",array('status'=>'true'));
        foreach($channel as $val) {
            self::$channelType[$val['channel_id']] = $val['channel_type'];
            unset($val);
        }
        unset($channel);
    }

    /**
     * 获取提示信息
     *
     * @param Array $order 订单内容
     * @return Array
     */
    public function getAlertMsg(& $order) {

        return array('color' => 'BLUE', 'flag'=>'物' , 'msg' => '无法自动匹配物流公司');
    }

    /**
     * 获取用于快速审核的选项页，输出HTML代码
     *
     * @param void
     * @return String
     */
    public function getInputUI() {

        //获取快递公司配置信息
        $corpList = array('-1' => '自动匹配物流');
        $corp = app::get('ome')->model('dly_corp')->getList('corp_id, name, type, is_cod, weight', array('disabled' => 'false'), 0, -1, 'weight DESC');
        foreach($corp as $item) {
            $corpList[$item['corp_id']] = $item['name'];
        }
        unset($corp);

        if (empty($corpList)) {

            $result = "<span>您还没有设置可用的物流公司。</span>";
        } else {
            $result = "<span class='customTitle'>请选择指的物流公司：</sapn>\n<select name='customAuto[logi][customLogiId]'>\n";
            foreach ($corpList as $logiId => $cropName) {

                $result .= "<option value='{$logiId}'>{$cropName}</option>\n";
            }
            $result .= "</select>\n";
        }

        return $result;
    }

    public function getjdcorp(){
        $db = kernel::database();
        $dlycorps = $db->select("SELECT d.* FROM sdb_ome_dly_corp as d LEFT JOIN sdb_logisticsmanager_channel as c on d.channel_id=c.channel_id WHERE c.channel_type='360buy' AND d.disabled='false'");
        $jdcorpList = array();
        foreach ($dlycorps as $corp ) {
            $jdcorpList[$corp['corp_id']] = $corp;
        }
        return $jdcorpList;
    }
}
