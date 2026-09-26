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
 * 一些订单属性的简单检查
 *
 * @author hzjsq@msn.com
 * @version 0.1b
 */

class omeauto_auto_plugin_abnormal extends omeauto_auto_plugin_abstract implements omeauto_auto_plugin_interface {

    /**
     * 是否支持批量审单
     */
    protected $__SUP_REP_ROLE = false;
    
    /**
     * 状态码
     */
    protected $__STATE_CODE = omeauto_auto_const::__ABNORMAL_CODE;

    protected $__PROVINCE = [
        '北京','上海','天津','重庆',
        '安徽','福建','甘肃','广东','贵州','海南','河北','河南','黑龙江','湖北','湖南','吉林','江苏','江西','辽宁','青海','山东','山西','陕西','四川','云南','浙江','台湾',
        '西藏','新疆','内蒙古','宁夏回族','广西壮族',
        '香港','澳门'
    ];

    /**
     * 开始处理
     *
     * @param omeauto_auto_group_item $group 要处理的订单组
     * @return Array
     */
    public function process(&$group, &$confirmRoles=null) {
        $allow = true;
        $msg = '';
        foreach ((array)$group->getOrders() as $order) {
            if(!$order['order_combine_hash'] || !$order['order_combine_idx'] || $order['pause']=='true' || $order['abnormal']=='true' || empty($order['ship_addr']) || $order['source_status'] == 'TRADE_CLOSED' || $order['is_delivery'] == 'N'){
                $allow = false;
                $msg = $order['order_bn'].' '.(!$order['order_combine_hash'] ? '数据不全，hash未生成' :
                        ($order['pause']=='true' ? '订单暂停' :
                            ($order['abnormal']=='true' ? '订单异常' :
                                (empty($order['ship_addr']) ? '缺少地址' :
                                    ($order['source_status'] == 'TRADE_CLOSED' ? '平台已经取消' : 
                                        ($order['is_delivery'] == 'N' ? '平台不允许发货' : '')
                                    )
                                )
                            )
                        ));
                $group->setOrderStatus($order['order_id'], $this->getMsgFlag());
            }

            $consignee = array (
                'ship_tel'    => $order['ship_tel'],
                'ship_mobile' => $order['ship_mobile'],
                'ship_addr'   => $order['ship_addr'],
                'ship_name'   => $order['ship_name'],
            );
            if ($order['createway'] != 'matrix' && $order['order_source'] != 'platformexchange' && kernel::single('ome_security_router',$order['shop_type'])->is_encrypt($consignee,'order')) 
            {
                // 补发订单类型
                if(in_array($order['order_type'], array('bufa'))){
                    //--不需要检查收货信息人加密
                }else{
                    $allow = false;
                    $msg = $order['order_bn'].' '.'订单含有密文';
                    $group->setOrderStatus($order['order_id'], $this->getMsgFlag());
                }
            }

            if($order['pay_status']=='3' && !kernel::single('ome_order_func')->checkPresaleOrder()){

                $allow = false;
                $msg = $order['order_bn'].' '.'部分支付';
                $group->setOrderStatus($order['order_id'], $this->getMsgFlag());
            }
            //双地址打异常
            if(!empty($order['ship_area'])) {
                $region    = explode(':', $order['ship_area']);
                $region    = explode('/', $region[1]);
            }
            $proviceList = array_flip($this->__PROVINCE);
            
            unset($proviceList[str_replace('自治区','',$region[0])]);
            
            $proviceListStr = implode('|',array_keys($proviceList));
            
            preg_match("/($proviceListStr)((?!\w{0,3}路)+(?!区))",$order['ship_addr'],$match);
            
            if ($match) {
                $allow = false;
                $msg = $order['order_bn'].' '.'订单双地址';
                $group->setOrderStatus($order['order_id'], $this->getMsgFlag());
            }
            if($order['shop_type'] == 'luban' 
               && $order['createway'] == 'matrix'
               ){
                if(in_array($order['source_status'], array('WAIT_SELLER_SEND_GOODS'))) {
                    $allow = false;
                    $msg = $order['order_bn'].' '.'平台订单未进入备货中';
                    $group->setOrderStatus($order['order_id'], $this->getMsgFlag());
                }
                if(is_array($order['extend_field'])) {
                    if(is_array($order['extend_field']['address_tag_ui'])) {
                        foreach($order['extend_field']['address_tag_ui'] as $atu) {
                            if(is_array($atu) && $atu["key"] == "double_address") {
                                $allow = false;
                                $msg = $order['order_bn'].' '.'订单双地址';
                                $group->setOrderStatus($order['order_id'], $this->getMsgFlag());
                            }
                        }
                    }
                }
            }

            // 判断行明细是否有退款
            foreach($order['objects'] as $object)
            {
                if ($object['pay_status'] == '5') {
                    $allow = false;
                    $msg = $object['bn'].' '.'已退款';
                    $group->setOrderStatus($order['order_id'], $this->getMsgFlag());
                }
            }
        }

        if(!$allow){
            $group->setStatus(omeauto_auto_group_item::__OPT_ALERT, $this->_getPlugName(), $msg);
        }
    }

     /**
     * 获取该插件名称
     *
     * @param Void
     * @return String
     */
    public function getTitle() {

        return '异常订单';
    }

    /**
     * 获取提示信息
     *
     * @param Array $order 订单内容
     * @return Array
     */
    public function getAlertMsg(& $order) {

        return array('color' => 'RED', 'flag' => '异' ,'msg' => '数据有异常的订单，如异常、暂停、平台取消');
    }
}
