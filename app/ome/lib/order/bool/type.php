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
 * @author ykm 2017/1/13
 * @describe 订单种类二进制常量类
 */
class ome_order_bool_type
{
    #是否是服务订单
    const __SERVICE_CODE = 0x0001;
    #菜鸟自动流转订单
    const __CNAUTO_CODE = 0x0002;

    // 代销标识
    const __DAIXIAO_CODE = 0x0004;

    #跨境订单
    const __INTERNAL_CODE = 0x0008;
    
    #挽单优先发货
    const __COME_BACK= 0x00010;
    
    //天猫物流升级
    const __CPUP_CODE = 0x00020;
    
    //翱象订单
    const __AOXIANG_CODE = 0x00040;
    
    //唯品会JITX
    const __JITX_CODE = 0x0080;
    
    #催发货
    const __URGENT_DELIVERY = 0x0200;
    
    #天猫直送3pl
    const __3PL_CODE = 0x0400;
    
    //双十一预约退款
    const __BOOKING_REFUND = 0x0800;
    
    //分销订单
    const __DISTRIBUTION_CODE = 0x01000;

    //风控订单
    const __RISK_CODE = 0x2000;

    //天猫直送4pl
    const __4PL_CODE = 0x4000;

    //天猫时效
    const __SHI_CODE = 0x8000;

    //[得物平台]特供订单
    const __TEGONG_CODE = 0x10000;

    //[淘宝]更换商品信息
    const __UPDATEITEM_CODE = 0x20000;
    
    const __JDLVMI_CODE = 0x040000;
    
    const __O2OPICK_CODE = 0x80000;
    
    //顺手买一件活动
    const __ACTIVITY_PURCHASE = 0x010000;
    
    #DEWU急速现货
    const __DEWU_JISU_CODE = 0x0100000;

    #DEWU品牌直发
    const __DEWU_BRAND_CODE = 0x40000;

    const __BOOKING_DELIVERY = 0x0100;
    
    // 失败订单标签
    const __ORDER_FAIL_FLAG = 0x800000;
    
    private $boolStatus = array(
        self::__SERVICE_CODE    => array('identifier' => '服', 'text' => '服务订单', 'color' => 'green'),
        self::__CNAUTO_CODE     => array('identifier' => '菜', 'text' => '菜鸟自动流转订单', 'color' => 'yellow'),
        self::__INTERNAL_CODE   => array('identifier' => '跨', 'text' => '跨境订单', 'color' => 'red'),
        
        self::__BOOKING_REFUND  => array('identifier' => '退', 'text' => '预约退款订单', 'color' => '#0a5fe7', 'search' => 'true'),

        self::__URGENT_DELIVERY => array('identifier' => '催', 'text' => '催发货订单', 'color' => 'red', 'search' => 'true'),
        self::__3PL_CODE        => array('identifier' => '时', 'text' => '天猫3PL订单', 'color' => '#F18A50'),
        self::__4PL_CODE        => array('identifier' => '直', 'text' => '菜鸟直送订单', 'color' => '#938F5C'),
        self::__SHI_CODE        => array('identifier' => '时', 'text' => '天猫时效订单', 'color' => '#118BA8'),
        self::__RISK_CODE       => array('identifier' => '风', 'text' => '风控订单', 'color' => '#E438A0'),
        self::__JITX_CODE       => array('identifier' => 'JITX', 'text' => 'JITX订单', 'color' => '#0F82A1'),

        self::__TEGONG_CODE     => array('identifier' => '供', 'text' => '特供订单', 'color' => 'red', 'search' => 'false'),
        self::__DAIXIAO_CODE    => array('identifier' => '代', 'text' => '代销订单', 'color' => '#66ccff', 'search' => 'true'),
        self::__UPDATEITEM_CODE    => array('identifier' => '换', 'text' => '此订单自助更换过sku', 'color' => '#66ccff'),
        self::__DISTRIBUTION_CODE    => array('identifier' => '销', 'text' => '分销订单', 'color' => '#6655ff'),
        self::__CPUP_CODE        => array('identifier' => 'TM物', 'text' => 'TM物流升级', 'color' => '#fecc66', 'search' => 'false'),
        
        self::__AOXIANG_CODE => array('identifier'=>'翱', 'text'=>'翱象订单', 'color'=>'red', 'search'=>'true'),
        self::__COME_BACK       => array('identifier' => '优先发货', 'text' => '优先发货', 'color' => '#DEB887', 'search' => 'false'),
        self::__O2OPICK_CODE=> array('identifier' => '现货', 'text' => '门店现货', 'color' => 'LimeGreen', 'search' => 'true'),
        self::__JDLVMI_CODE => array('identifier'=>'JDLVMI', 'text'=>'京东云仓', 'color'=>'LimeGreen', 'search'=>'false'),
        self::__ACTIVITY_PURCHASE=> array('identifier' => '顺', 'text' => '顺手买一件活动', 'color' => '#E9967A', 'search' => 'true'),
        self::__DEWU_JISU_CODE   => array('identifier' => '急速现货', 'text' => '急速现货', 'color' => '#C481A5', 'search' => 'false'),
        self::__DEWU_BRAND_CODE   => array('identifier' => '品牌直发', 'text' => '品牌直发', 'color' => '#00CCCC', 'search' => 'false'),
        self::__BOOKING_DELIVERY   => array('identifier' => '预约发货', 'text' => '预约发货', 'color' => '#0C7ED9FF', 'search' => 'false'),
        
        self::__ORDER_FAIL_FLAG => array('identifier'=>'失败标签', 'text'=>'符合设置的失败标签', 'color'=>'#FF4040', 'search'=>'false'),
    );

    /**
     * 获取BoolTypeText
     * @param mixed $num num
     * @return mixed 返回结果
     */

    public function getBoolTypeText($num = null)
    {
        if ($num) {
            return (array) $this->boolStatus[$num];
        }
        return $this->boolStatus;
    }

    /**
     * 获取订单标识
     * 
     * @param $boolType
     * @param $shop_type
     * @param $add_to_bill_label 是否追加订单标记的显示
     * @param $order_id 订单id
     * @return string
     */
    public function getBoolTypeIdentifier($boolType,$shop_type,$add_to_bill_label = false, $order_id = '')
    {
        $str = '';
        $labelStr = [];
        foreach ($this->boolStatus as $k => $val) {

            if ($boolType & $k) {
                if($shop_type == 'luban' && $boolType == self::__CPUP_CODE){
                    $val['identifier'] = 'DY物';
                    $val['text'] = 'DY物流升级';
                    $val['color'] = '#FF8800 ';
                }

                // $str .= sprintf("<span class='tag-label' title='%s' style='background-color:%s;color:#FFFFFF;'>%s</span>", $val['text'], $val['color'], $val['identifier']);
                $labelStr[] = sprintf("<span title='%s' style='filter: brightness(0.9) contrast(0.9);border:1px solid %s; color:%s;margin: 2px;padding: 0px 2px;border-radius: 5px;white-space: nowrap;'>%s</span>", $val['text'], $val['color'], $val['color'], $val['identifier']);
            }
            
        }
        if($add_to_bill_label && $order_id){
            $billObj = kernel::single('ome_bill_label');
            $labelList = $billObj->getLabelFromOrder($order_id);
            foreach($labelList as $val){
                $labelStr[] =
                sprintf("<span title='%s' style='filter: brightness(0.9) contrast(0.9);border:1px solid %s; color:%s;margin: 2px;padding: 0px 2px;border-radius: 5px;white-space: nowrap;'>%s</span>", $val['label_name'], $val['label_color'], $val['label_color'], $val['label_name']);
            }
        }
        $str = '<div style="overflow: auto;word-break: break-word;white-space: normal;width: 100%;flex-wrap: wrap;display: flex;">'.implode("", $labelStr).'</div>';
        return $str;
    }

    /**
     * isCnService
     * @param mixed $boolType boolType
     * @return mixed 返回值
     */
    public function isCnService($boolType)
    {
        // 判断是否为NULL或空值
        if (is_null($boolType) || $boolType === '') {
            return false;
        }
        
        if (($boolType & ome_order_bool_type::__4PL_CODE)
            || ($boolType & ome_order_bool_type::__3PL_CODE)) {
            return true;
        }
        return false;
    }

    /**
     * 获取Order_type_list
     * @return mixed 返回结果
     */
    public function getOrder_type_list()
    {
        $order_type_list = array();
        $boolstatus      = $this->boolStatus;
        foreach ($boolstatus as $k => $v) {
            if ($v['search'] == 'true') {
                $order_type_list[$k] = $v['text'];
            }
        }
        return $order_type_list;
    }

    /**
     * 获取订单种类文字描述
     * 
     * @param $boolType
     * @param string $shop_type
     * @return string
     */
    public function getBoolTypeDescribe($boolType,$shop_type = 'taobao')
    {
        $data = array();
        foreach ($this->boolStatus as $k => $val) {
            if ($boolType & $k) {
                if($shop_type == 'luban'){
                    $val['text'] = 'DY物流升级';
                }
                $data[] = $val['text'];
            }
        }

        return implode('、', $data);
    }
    
    //是否jitx订单
    public function isJITX($boolType)
    {
        // 判断是否为NULL或空值
        if (is_null($boolType) || $boolType === '') {
            return false;
        }
        
        return $boolType & self::__JITX_CODE ? true : false;
    }
    
    //是否是天猫物流升级订单
    /**
     * isCPUP
     * @param mixed $boolType boolType
     * @return mixed 返回值
     */
    public function isCPUP($boolType)
    {
        // 判断是否为NULL或空值
        if (is_null($boolType) || $boolType === '') {
            return false;
        }
        
        return $boolType & self::__CPUP_CODE ? true : false;
    }
    
    //是否翱象订单
    /**
     * isAoxiang
     * @param mixed $boolType boolType
     * @return mixed 返回值
     */
    public function isAoxiang($boolType)
    {
        // 判断是否为NULL或空值
        if (is_null($boolType) || $boolType === '') {
            return false;
        }
        
        return $boolType & self::__AOXIANG_CODE ? true : false;
    }

    /**
     * isJDLVMI
     * @param mixed $boolType boolType
     * @return mixed 返回值
     */
    public function isJDLVMI($boolType)
    {
        // 判断是否为NULL或空值
        if (is_null($boolType) || $boolType === '') {
            return false;
        }
        
        return $boolType & self::__JDLVMI_CODE ? true : false;
    }

    /**
     * isDWBrand
     * @param mixed $boolType boolType
     * @return mixed 返回值
     */
    public function isDWBrand($boolType)
    {
        // 判断是否为NULL或空值
        if (is_null($boolType) || $boolType === '') {
            return false;
        }
        
        return $boolType & self::__DEWU_BRAND_CODE ? true : false;
    }

    /**
     * isDWJISU
     * @param mixed $boolType boolType
     * @return mixed 返回值
     */
    public function isDWJISU($boolType)
    {
        // 判断是否为NULL或空值
        if (is_null($boolType) || $boolType === '') {
            return false;
        }
        
        return $boolType & self::__DEWU_JISU_CODE ? true : false;
    }

    /**
     * isO2opick
     * @param mixed $boolType boolType
     * @return mixed 返回值
     */
    public function isO2opick($boolType){
        // 判断是否为NULL或空值
        if (is_null($boolType) || $boolType === '') {
            return false;
        }
        
        return $boolType & self::__O2OPICK_CODE ? true : false;
    }
    
    /**
     * isBookingDelivery
     * @param mixed $boolType boolType
     * @return mixed 返回值
     */
    public function isBookingDelivery($boolType){
        // 判断是否为NULL或空值
        if (is_null($boolType) || $boolType === '') {
            return false;
        }
        
        return $boolType & self::__BOOKING_DELIVERY ? true : false;
    }
}
