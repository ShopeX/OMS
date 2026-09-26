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
 * 检查能否自动确定仓库
 *
 * @author hzjsq@msn.com
 * @version 0.1b
 */
class omeauto_auto_plugin_branch extends omeauto_auto_plugin_abstract implements omeauto_auto_plugin_interface
{

    /**
     * 状态码
     *
     * @var Integer
     */
    protected $__STATE_CODE = omeauto_auto_const::__BRANCH_CODE;

    /**
     * 涉及仓库选择的订单分组
     *
     * @var array
     */
    static $_orderGroups = null;

    /**
     * 已获取的各个规则类型下的地区area_id对应门店仓branch_id的关系数组
     * @var array[rule_type][area_id] = branch_id
     */
    static $_o2oStoreRegion = array();
    
    /**
     * 支持区域仓拆单的店铺类型
     * @todo：会根据[拆单规则设置]进行拆单,现只支持[抖音]平台
     * 
     * @var unknown
     */
    private $_regionWarehouseShopType = array('luban');

    #报错信息
    private $error_msg;
    
    /**
     * 开始处理
     *
     * @param omeauto_auto_group_item $group 要处理的订单组
     * @return Array
     */
    public function process(&$group, &$confirmRoles ='')
    {
        $this->error_msg = '';
        //@todo：返回的仓库$branchId是数组,后面store插件会转为单个仓库branch_id
        $branchId = $this->getBranchId($group, $confirmRoles);

        if ($branchId) {
            //设置使用的仓库编号
            $group->setBranchId($branchId);
        } else {
            //设置错误信息
            foreach ($group->getOrders() as $order) {
                $group->setOrderStatus($order['order_id'], $this->getMsgFlag());
            }
            $group->setStatus(omeauto_auto_group_item::__OPT_HOLD, $this->_getPlugName(), $this->error_msg);
        }
    }

    /**
     * 通过订单组，获取对应仓库
     *
     * @param omeauto_auto_group_item $group
     * @return void
     */
    private function getBranchId(&$group, &$confirmRoles)
    {
        $branchObj = app::get('ome')->model('branch');
        
        $orders = $group->getOrders();
        
        if(is_array($confirmRoles) && in_array($confirmRoles['inlet_class'], array('combine','ordertaking','split','combineagain'))) {
            list($rs, $rsData) = kernel::single('material_basic_material_stock_freeze')->deleteOrderBranchFreeze(array_column($orders, 'order_id'));
            if(!$rs) {
                $this->error_msg = $rsData['msg'];
                return [];
            }
        }
        // $appointBranch = false;

        // $store_code = '';
        foreach ($orders as $order) {
            foreach ($order['objects'] as $object)
            {
                if ($object['store_code']) {
                    // 如果指定仓，omeauto_split_branchappoint逻辑
                    return array('sys_appoint');
                }
            }
        }

        // 指定仓
        // if ($store_code) {
        //     $appointBranch = kernel::single('ome_branch_type')->isAppointBranch($order);
        //     $arrBranch     = kernel::single('ome_branch_type')->getBranchIdByStoreCode($store_code, $appointBranch);

        //     return (array) $arrBranch;
        // }

        // foreach ($orders as $val) {
        //     if ($abRs = kernel::single('ome_branch_type')->isAppointBranch($val)) {
        //         $appointBranch = $abRs;
        //     }
        // }

        // 返回指定仓
        // if ($appointBranch) {
        //     $rs = $this->getAppointBranchBranch($orders, $appointBranch);

        //     if($rs !== false) {
        //         return $rs;
        //     }
        // }

        foreach ($orders as $val) {
            $order_bool_type = $val['order_bool_type']; //判断是否流转订单
            $auto_branch_id  = app::get('ome')->getConf('shop.cnauto.set.' . $val['shop_id']);
            if ($val['shop_type'] == 'taobao' && ($order_bool_type & ome_order_bool_type::__CNAUTO_CODE)) {
                if (intval($auto_branch_id) > 0) {
                    return $this->filterRefusedBranchIds(array($auto_branch_id), $orders);
                } else {
                    return array();
                }
            }
        }

        //根据当前订单识别是否是全渠道订单
        $is_omnichannel = $group->isOmnichannel();
        if ($is_omnichannel) {
            //门店仓获取处理逻辑
            $branchIds = $this->getBranchByStore($group);
            if ($branchIds) {
                //设置当前处理订单为门店仓
                $group->setStoreBranch();

                return $branchIds;
            }
        }

        $branchs   = $branchObj->db->select("SELECT branch_id,name FROM sdb_ome_branch WHERE is_deliv_branch='true' AND disabled = 'false'");
        if (count($branchs) == 1) {
            return $this->filterRefusedBranchIds(array($branchs[0]['branch_id']), $orders);
        }

        $this->initFilters($group);
        $branch_info = array();
        $autobranch  = array();
        foreach (self::$_orderGroups as $branchId => $filter) {
            if ($filter->vaild($group)) {
                $info                   = explode('-', $branchId);
                $branch_info[$branchId] = $info[1];

                $config                     = $filter->getConfig();
                $autobranch[$config['tid']] = $config;
            }
        }

        // 仓库规则
        $group->setAutoBranch($autobranch);

        $branch_info = array_unique($branch_info);
        $filteredBranchInfo = $this->filterRefusedBranchIds($branch_info, $orders);
        if (empty($filteredBranchInfo) && !empty($branch_info)) {
            $this->error_msg = '本订单已拒单的门店不可再分配';
        }
        return $filteredBranchInfo;

    }

    /**
     * 过滤本订单已拒单的门店仓（与 store / storemax 审单逻辑对齐）
     *
     * @param array $branchIds
     * @param array $orders
     * @return array
     */
    private function filterRefusedBranchIds($branchIds, $orders)
    {
        if (empty($branchIds) || !app::get('o2o')->is_installed()) {
            return (array) $branchIds;
        }
        $orderIds = array_column((array) $orders, 'order_id');
        if (empty($orderIds)) {
            return array_values((array) $branchIds);
        }
        $numericBranchIds = array();
        foreach ((array) $branchIds as $branchId) {
            if ($branchId === 'sys_appoint' || $branchId === '' || $branchId === null) {
                continue;
            }
            $numericBranchIds[] = $branchId;
        }
        if (empty($numericBranchIds)) {
            return (array) $branchIds;
        }
        return kernel::single('o2o_store')->filterBranchIdsByOrderRefuse($numericBranchIds, $orderIds);
    }

    /**
     * 检查涉及仓库选择的订单分组对像是否已经存在
     *
     * @param void
     * @return void
     */
    private function initFilters($group = null)
    {

        if (self::$_orderGroups === null) {

            $filters = kernel::single('omeauto_auto_type')->getAutoBranchTypes();

            self::$_orderGroups = array();
            if ($filters) {

                foreach ($filters as $config) {
                    // 特殊处理bid=-1的情况，代表所有参与O2O的门店
                    if ($config['bid'] == -1) {
                        $o2oStoreLib = kernel::single('o2o_store');
                        $o2oBranchIds = $o2oStoreLib->getAllO2OStoreBranchIds();
                        
                        if (!empty($o2oBranchIds)) {
                            // 获取当前订单组的收货地址，用于覆盖范围检查
                            $ship_area = null;
                            if ($group) {
                                $ship_area = $group->getShipArea();
                            }
                            
                            // 只有有收货地址时才处理门店仓规则
                            if ($ship_area) {
                                // 使用门店仓覆盖区域检测工具类，过滤出可用的门店仓
                                $availableO2OBranchIds = kernel::single('ome_store_branch_coverage')->getAvailableBranches($o2oBranchIds, $ship_area);
                                $availableO2OBranchIds = $o2oStoreLib->filterBranchIdsByAcceptOrderLimit($availableO2OBranchIds);
                                if ($group) {
                                    $orderIds = array_column($group->getOrders(), 'order_id');
                                    $availableO2OBranchIds = $o2oStoreLib->filterBranchIdsByOrderRefuse($availableO2OBranchIds, $orderIds);
                                }

                                if (!empty($availableO2OBranchIds)) {
                                    // 只为有覆盖范围的门店仓创建规则
                                    foreach ($availableO2OBranchIds as $branchId) {
                                        $o2oConfig = $config;
                                        $o2oConfig['bid'] = $branchId;
                                        
                                        $filter = new omeauto_auto_group();
                                        $filter->setConfig($o2oConfig);
                                        self::$_orderGroups[$config['tid'] . '-' . $branchId] = $filter;
                                    }
                                }
                            }
                            // 没有收货地址时，不处理门店仓规则
                        }
                    } else {
                        $filter = new omeauto_auto_group();
                        $filter->setConfig($config);
                        self::$_orderGroups[$config['tid'] . '-' . $config['bid']] = $filter;
                    }
                }
            }

            //增加缺省订单分组,也就是默认仓库处理
            // 屏蔽默认仓
            // $defaultBranch = app::get('ome')->model('branch')->dump(array('defaulted' => 'true', 'disabled' => 'false'));
            // if (!empty($defaultBranch)) {
            //     $filter = new omeauto_auto_group();
            //     $filter->setDefault();
            //     self::$_orderGroups['a-' . $defaultBranch['branch_id']] = $filter;
            // }

        }
    }

    /**
     * 获取该插件名称
     *
     * @param Void
     * @return String
     */
    public function getTitle()
    {

        return '无匹配仓库';
    }

    /**
     * 获取提示信息
     *
     * @param Array $order 订单内容
     * @return Array
     */
    public function getAlertMsg(&$order)
    {

        return array('color' => '#48EAED', 'flag' => '仓', 'msg' => '无法对应仓库');
    }

    /**
     * 获取用于快速审核的选项页，输出HTML代码
     *
     * @param void
     * @return String
     */
    public function getInputUI()
    {

    }

    /**
     * 门店审单候选仓过滤（接单量 + 本订单已拒单门店）
     *
     * @param array|int                 $branchIds
     * @param omeauto_auto_group_item   $group
     * @return array
     */
    private function filterStoreBranchIdsForConfirm($branchIds, $group)
    {
        if (empty($branchIds) || !app::get('o2o')->is_installed()) {
            return (array) $branchIds;
        }

        $orderIds = array_column($group->getOrders(), 'order_id');

        return kernel::single('o2o_store')->filterBranchIdsForStoreConfirm((array) $branchIds, $orderIds);
    }

    /**
     * 根据订单信息获取是否匹配到门店仓
     *
     * @param omeauto_auto_group_item $group
     * @return int branch_id
     */
    private function getBranchByStore($group)
    {
        //判断O2O APP 是否启用
        if (app::get('o2o')->is_installed()) {
            $storeLib = kernel::single('o2o_store');
            $orderIds = array_column($group->getOrders(), 'order_id');

            //如果订单有指定门店信息，则根据指定门店设置仓库
            $storeInfo = $group->getStoreInfo();
            if ($storeInfo && $storeInfo['store_bn']) {
                $branchId = $storeLib->getBranchIdByStoreBn($storeInfo['store_bn']);

                //如果指定了履约方式则赋值
                if ($storeInfo['store_dly_type'] > 0) {
                    $dlyType = $storeInfo['store_dly_type'] == 1 ? 'o2o_pickup' : 'o2o_ship';
                    $group->setStoreDlyType($dlyType);
                }

                // 指定门店未超限且未被本订单拒单则直接返回，否则继续按区域匹配其他门店
                if ($branchId
                    && !$storeLib->isAcceptOrderLimitExceeded($branchId)
                    && !$storeLib->isBranchRefusedByOrder($branchId, $orderIds)) {
                    return array($branchId);
                }
            }

            //根据收货地区匹配覆盖门店进行配送
            $ship_area         = $group->getShipArea();
            $arrArea           = explode(':', $ship_area);
            $filter['area_id'] = isset($arrArea[2]) ? $arrArea[2] : '';

            if (empty($filter['area_id'])) {
                return "";
            }

            $rule_type = app::get('o2o')->getConf('o2o.autostore.type');

            // 缓存：门店优选规则(autostore) / 门店列表覆盖区域(store_region)
            foreach (array($rule_type, 'store_region') as $cacheRuleType) {
                if (isset(self::$_o2oStoreRegion[$cacheRuleType][$filter['area_id']])) {
                    $group->setStoreDlyType('o2o_ship');

                    return $this->filterStoreBranchIdsForConfirm(
                        self::$_o2oStoreRegion[$cacheRuleType][$filter['area_id']],
                        $group
                    );
                }
            }

            $filter['mode'] = $rule_type;
            // 优先：门店优选规则(o2o_autostore_rule)
            $error_msg = '';
            $branchIds = kernel::single('o2o_autostore')->matchStoreBranch($filter, $error_msg);
            $cacheRuleType = $rule_type;

            // 备选：门店列表 -> 覆盖区域(logisticsmanager_warehouse.region_ids)
            if (empty($branchIds)) {
                $branchIds = $this->_matchStoreBranchByStoreRegion($ship_area);
                $cacheRuleType = 'store_region';
            }

            if (empty($branchIds)) {
                return "";
            }

            $branchLib = kernel::single('ome_interface_branch');

            //判断是否安装了阿里全渠道app
            if (app::get('tbo2o')->is_installed()) {
                //获取订单对应的前端店铺shop_id
                $shop_id = $group->getShopId();
                //获取阿里全渠道主店铺shop_id
                $tbo2o_shop = kernel::single('tbo2o_common')->getTbo2oShopInfo();
                if ($shop_id && $tbo2o_shop["shop_id"] && $shop_id == $tbo2o_shop["shop_id"]) {
                    //是阿里全渠道店铺的订单
                } else {
                    //不是阿里全渠道店铺的订单
                    $arr_o2o_server   = kernel::single('tbo2o_common')->getTbo2oServerInfo();
                    $mdlO2oStore      = app::get("o2o")->model("store");
                    $o2o_store_filter = array("branch_id|in" => $branchIds);
                    //有设置过阿里全渠道线下服务端的 过滤掉阿里全渠道的门店仓
                    if ($arr_o2o_server["server_id"]) {
                        $o2o_store_filter["server_id|noequal"] = $arr_o2o_server["server_id"];
                    }
                    $rs_o2o_store = $mdlO2oStore->getList("branch_id", $o2o_store_filter);
                    if (empty($rs_o2o_store)) {
                        return "";
                    }
                    //重新获取$branchIds
                    $branchIds = array();
                    foreach ($rs_o2o_store as $k_o_s) {
                        $branchIds[] = $k_o_s["branch_id"];
                    }
                }
            }

            $branch_arr = $branchLib->getList('branch_id', array('branch_id' => $branchIds, 'b_status' => 1), 0, -1);

            //返回匹配到的所有门店仓库
            $branchIds = array();
            if ($branch_arr) {
                foreach ($branch_arr as $key => $val) {
                    $branchIds[] = $val['branch_id'];
                }
            }

            if ($branchIds) {
                // 缓存区域匹配结果；拒单/接单量按订单在读用时过滤
                self::$_o2oStoreRegion[$cacheRuleType][$filter['area_id']] = $branchIds;
                //匹配到门店仓，定义当前物流公司为门店配送
                $group->setStoreDlyType('o2o_ship');
            }

            return $this->filterStoreBranchIdsForConfirm($branchIds, $group);
        }
    }

    /**
     * 按门店列表「覆盖区域」(logisticsmanager_warehouse) 匹配 O2O 门店仓
     *
     * @param string $ship_area
     * @return array
     */
    private function _matchStoreBranchByStoreRegion($ship_area)
    {
        if (empty($ship_area) || !app::get('logisticsmanager')->is_installed()) {
            return array();
        }

        $storeLib = kernel::single('o2o_store');
        $o2oBranchIds = $storeLib->getAllO2OStoreBranchIds();
        if (empty($o2oBranchIds)) {
            return array();
        }

        return kernel::single('ome_store_branch_coverage')->getAvailableBranches($o2oBranchIds, $ship_area);
    }

    //平台指定仓库进行发货
    //@todo：已经没有地方调用此方法；
    private function getAppointBranchBranch($orders, $appointBranch)
    {
        $orderTypeLib = kernel::single('ome_order_bool_type');
        
        $storeCode = '';
        $shop_id = '';
        foreach ($orders as $order) {
            //是否翱象订单或者店铺已签约翱象
            $isAoxiang = false;
            if($orderTypeLib->isAoxiang($order['order_bool_type'])) {
                $isAoxiang = true;
            }
            
            //objects
            foreach ($order['objects'] as $objKey => $object)
            {
                //[翱象]建议仓业务
                if($object['store_code'] && $isAoxiang){
                    if($object['biz_sd_type'] == 2){
                        //建议仓类型为：2,多个商品有多个指定仓,只需要用第一个指定仓;
                        $storeCode = $object['store_code'];
                        break;
                    }elseif($object['biz_sd_type'] == 1){
                        //建议仓类型为：1,按照OMS自己的逻辑选仓;
                        unset($order['objects'][$objKey]['store_code']);
                        continue;
                    }
                }
                
                //预选仓库编码
                if ($storeCode) {
                    if ($storeCode != $object['store_code'] && $object['store_code']) {
                        return array();
                    }
                } else {
                    $storeCode = $object['store_code'];
                    $shop_id = $order['shop_id'];
                }
            }
        }

        if (empty($storeCode)) {
            return false;
        }

        $arrBranch = kernel::single('ome_branch_type')->getBranchIdByStoreCode($storeCode, $appointBranch, $shop_id);

        return $arrBranch[$storeCode] ? array($arrBranch[$storeCode]) : array();
    }

}
