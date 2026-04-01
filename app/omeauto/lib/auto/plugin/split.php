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
 * @author ykm 2020/7/13 14:09:16
 * @describe 直接拆单
 */
class omeauto_auto_plugin_split extends omeauto_auto_plugin_abstract implements omeauto_auto_plugin_interface
{

    /**
     * 是否支持批量审单
     */
    protected $__SUP_REP_ROLE = false;
    /**
     * 状态码
     */
    protected $__STATE_CODE = omeauto_auto_const::_SPLIT_CODE;

    /**
     * 开始处理
     *
     * @param omeauto_auto_group_item $group 要处理的订单组
     * @param array $confirmRoles autoconfirm中的config
     * @return array 性能数据
     */
    public function process(&$group, &$confirmRoles=null)
    {
        $performanceData = array(
            'plugin_name' => $this->_getPlugName(),
            'start_time' => microtime(true),
            'end_time' => 0,
            'execution_time' => 0,
            'success' => true,
            'message' => '',
            'order_bns' => array(),
            'split_type' => '',
            'split_result' => array()
        );
        
        // 获取第一个订单号
        $orders = $group->getOrders();
        $firstOrder = current($orders);
        $performanceData['order_bns'] = $firstOrder['order_bn'] ?? '';
        
        $branchId = $group->getBranchId();

        // 指定仓发货
        if ($branchId[0] == 'sys_appoint') {
            $group->setProcessSplit();
            $performanceData['split_type'] = 'branchappoint';
            $startTime = microtime(true);
            list($rs, $msg, $code) = kernel::single('omeauto_split_router', 'branchappoint')->splitOrder($group, '');
            
            // 记录拆单结果
            $performanceData['split_result'] = array(
                'success' => $rs,
                'message' => $msg,
                'code' => $code ?? ''
            );
            
            if ($code == 'no branch') {
                foreach ($group->getOrders() as $order) {
                    $group->setOrderStatus($order['order_id'], $this->getMsgFlag());
                }

                $performanceData['success'] = false;
                $performanceData['message'] = $msg;
                $performanceData['end_time'] = microtime(true);
                $performanceData['execution_time'] = $performanceData['end_time'] - $performanceData['start_time'];

                $group->setStatus(omeauto_auto_group_item::__OPT_HOLD, $this->_getPlugName(), $msg);
                return $performanceData;
            }
        }
    
        //检测仓库是否管控库存
        //检测$branchId 是否只有一个仓库 是并且为库存管控
        $branchId = $group->getBranchId();
        if (is_array($branchId) && count($branchId) == 1) {
            $isCtrlStore = kernel::single('ome_branch')->getBranchCtrlStore(reset($branchId));
            if ($isCtrlStore === false) {
                $group->setConfirmBranch(true);
                $performanceData['message'] = '仓库不管控库存，跳过拆单';
                $performanceData['end_time'] = microtime(true);
                $performanceData['execution_time'] = $performanceData['end_time'] - $performanceData['start_time'];
                return $performanceData;
            }
        }
        
        // 门店仓不跑拆单
        if ($group->isStoreBranch()) {
            $performanceData['message'] = '门店仓不跑拆单';
            $performanceData['end_time'] = microtime(true);
            $performanceData['execution_time'] = $performanceData['end_time'] - $performanceData['start_time'];
            return $performanceData;
        }

        //是否启动拆单
        $orderSplitLib = kernel::single('ome_order_split');
        $split_seting  = $orderSplitLib->get_delivery_seting();
        $split_model   = $split_seting ? 2 : 0; //自由拆单方式split_model=2
        if (!$split_model) {
            $performanceData['message'] = '未启动拆单功能';
            $performanceData['end_time'] = microtime(true);
            $performanceData['execution_time'] = $performanceData['end_time'] - $performanceData['start_time'];
            return $performanceData;
        }

        // 货到付款和门店自提/猫超的不拆单
        foreach ($group->getOrders() as $order) {
            if ($order['is_cod'] == 'true') {
                $performanceData['message'] = '货到付款订单不拆单';
                $performanceData['end_time'] = microtime(true);
                $performanceData['execution_time'] = $performanceData['end_time'] - $performanceData['start_time'];
                return $performanceData;
            }
            if ($order['shipping'] == 'STORE_SELF_FETCH') {
                $performanceData['message'] = '门店自提订单不拆单';
                $performanceData['end_time'] = microtime(true);
                $performanceData['execution_time'] = $performanceData['end_time'] - $performanceData['start_time'];
                return $performanceData;
            }
            if ($order['shop_type'] == "taobao" && $order['order_source'] == 'maochao') {
                $performanceData['message'] = '猫超订单不拆单';
                $performanceData['end_time'] = microtime(true);
                $performanceData['execution_time'] = $performanceData['end_time'] - $performanceData['start_time'];
                return $performanceData;
            }
            if ($order['shop_type'] == "zkh") {
                $performanceData['message'] = 'ZKH订单不拆单';
                $performanceData['end_time'] = microtime(true);
                $performanceData['execution_time'] = $performanceData['end_time'] - $performanceData['start_time'];
                return $performanceData;
            }
        }

        $split = app::get('omeauto')->model('order_split')->dump(array('sid' => intval($confirmRoles['split_id'])));
        if ($split && $split['split_type']) {
            $splitType   = $split['split_type'];
            $splitConfig = $split['split_config'];
            $performanceData['split_type'] = $splitType;

            // 手工审单/自动审单标识
            $splitConfig['confirm_source'] = $confirmRoles['source'];

            $group->setProcessSplit();
            
            //开始拆单
            $startTime = microtime(true);
            list($rs, $msg, $logResult) = kernel::single('omeauto_split_router', $splitType)->splitOrder($group, $splitConfig);
            
            // 记录拆单结果
            $performanceData['split_result'] = array(
                'success' => $rs,
                'message' => $msg,
                'split_type' => $splitType,
                'log_result' => $logResult ?? null
            );
            
            if (!$rs) {
                foreach ($group->getOrders() as $order) {
                    $group->setOrderStatus($order['order_id'], $this->getMsgFlag());
                }
                $performanceData['success'] = false;
                $performanceData['message'] = $msg;
                $group->setStatus(omeauto_auto_group_item::__OPT_HOLD, $this->_getPlugName(), $msg);
            }
        }
        
        $performanceData['message'] = '拆单处理完成';
        $performanceData['end_time'] = microtime(true);
        $performanceData['execution_time'] = $performanceData['end_time'] - $performanceData['start_time'];
        return $performanceData;
    }


    /**
     * 获取该插件名称
     *
     * @param Void
     * @return String
     */
    public function getTitle()
    {
        return '拆单检查';
    }

    /**
     * 获取提示信息
     *
     * @param array $order 订单内容
     * @return array
     */
    public function getAlertMsg(&$order)
    {
        return array('color' => '#44607B', 'flag' => '拆', 'msg' => '无法拆单');
    }

}
