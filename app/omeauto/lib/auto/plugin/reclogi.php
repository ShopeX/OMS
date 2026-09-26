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
 * ============================
 * @Author:   yaokangming
 * @Version:  1.0 
 * @DateTime: 2022/2/16 15:11:21
 * @describe: 推荐物流
 * ============================
 */

class omeauto_auto_plugin_reclogi extends omeauto_auto_plugin_abstract implements omeauto_auto_plugin_interface {

    /**
     * 是否支持批量审单
     */
    protected $__SUP_REP_ROLE = false;
    
    /**
     * 状态码
     */
    protected $__STATE_CODE = omeauto_auto_const::_RECLOGI_CODE;

    /**
     * 开始处理
     *
     * @param omeauto_auto_group_item $group 要处理的订单组
     * @return Array
     */
    public function process(&$group, &$confirmRoles=null) {
        $arrOrder = $group->getOrders();
        $order = current($arrOrder);
        $corp = $group->getDlyCorp();
        
        if (in_array(strtoupper($corp['type']), ['OTHER', '其他'])) {
            return;
        }
        
        list($rs, $msg) = kernel::single('ome_event_trigger_shop_logistics')->judgeRecommend($order, $corp['type']);
        if($rs) {
            return;
        }
        foreach ((array)$arrOrder as $order) {
            $group->setOrderStatus($order['order_id'], $this->getMsgFlag());
        }
        $group->setStatus(omeauto_auto_group_item::__OPT_ALERT, $this->_getPlugName());
    }

     /**
     * 获取该插件名称
     *
     * @param Void
     * @return String
     */
    public function getTitle() {

        return '物流停运';
    }

    /**
     * 获取提示信息
     *
     * @param Array $order 订单内容
     * @return Array
     */
    public function getAlertMsg(& $order) {

        return array('color' => 'RED', 'flag' => '停' ,'msg' => '常用快递停运，请使用建议快递');
    }
}