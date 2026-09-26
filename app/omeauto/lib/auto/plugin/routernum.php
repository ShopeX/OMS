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
 * @author ykm 2018/7/4
 * @describe
 */
class omeauto_auto_plugin_routernum extends omeauto_auto_plugin_abstract implements omeauto_auto_plugin_interface {

    /**
     * 是否支持批量审单
     */
    protected $__SUP_REP_ROLE = false;
    /**
     * 状态码
     */
    protected $__STATE_CODE = omeauto_auto_const::__ROUTER_CODE;

    /**
     * 开始处理
     *
     * @param omeauto_auto_group_item $group 要处理的订单组
     * @return array
     */
    public function process(& $group, &$confirmRoles=null) {
        $allow = true;
        #添加默认值，避免缺少路由次数陷入死循环
        $routerNumLimit = $this->_getRouterNumLimit($group);
        $confirmRoles['router_num'] = $confirmRoles['router_num'] > 0 ? (int)$confirmRoles['router_num'] : $routerNumLimit;
        foreach ($group->getOrders() as  $order) {
            $extendModel = app::get('ome')->model('order_extend');
            $extendOrder = $extendModel->db_dump(array('order_id'=>$order['order_id']), 'router_num');
            if($extendOrder['router_num'] >= $confirmRoles['router_num']){
                $allow = false;
                $group->setOrderStatus($order['order_id'], $this->getMsgFlag());
                break;
            }
        }

        if(!$allow){
            $group->setStatus(omeauto_auto_group_item::__OPT_HOLD, $this->_getPlugName());
        }

    }

    /**
     * 获取路由次数上限：门店仓读系统配置，其他仓默认9次
     *
     * @param omeauto_auto_group_item $group
     * @return int
     */
    private function _getRouterNumLimit($group)
    {
        if (!$this->_isStoreBranchGroup($group)) {
            return 9;
        }
        $routerNumLimit = (int) app::get('o2o')->getConf('o2o.order.router_num');
        return $routerNumLimit > 0 ? $routerNumLimit : 3;
    }

    /**
     * 是否门店仓审单（branch 插件在 routernum 之前已选仓）
     *
     * @param omeauto_auto_group_item $group
     * @return bool
     */
    private function _isStoreBranchGroup($group)
    {
        if ($group->isStoreBranch()) {
            return true;
        }
        $branchIds = $group->getBranchId();
        if (empty($branchIds)) {
            return false;
        }
        $ids = is_array($branchIds) ? $branchIds : array($branchIds);
        if (in_array('sys_appoint', $ids, true)) {
            return true;
        }
        $branchLib = kernel::single('ome_branch');
        foreach ($ids as $branchId) {
            if ($branchLib->isStoreBranch($branchId)) {
                return true;
            }
        }
        return false;
    }


    /**
     * 获取该插件名称
     *
     * @param Void
     * @return String
     */
    public function getTitle() {
        return '路由次数检查';
    }

    /**
     * 获取提示信息
     *
     * @param array $order 订单内容
     * @return array
     */
    public function getAlertMsg(& $order) {
        return array('color' => '#BD794E', 'flag' => '路', 'msg' => '路由次数超过限制');
    }

}
