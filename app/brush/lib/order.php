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
 * @author ykm 2015-11-16
 * @describe 订单刷单公用类
 */
class brush_order
{
    /**
     * 通过刷单规则,判断订单是否为刷单
     * 
     * @param array $sdf
     * @return bool
     */
    public function brush_confirm(&$sdf)
    {
        $farm_model = app::get('brush')->model('farm');
        $farm_list = $farm_model->getList("*",array('status'=>'1'));
        if(empty($farm_list)){
            return false;
        }
        
        foreach($farm_list as $val)
        {
            $farm_id = $this->_condition($val, $sdf);
            if($farm_id) {
                $sdf['order_type'] = 'brush';
                $sdf['brush']['farm_id'] = $farm_id;
                
                break;
            }
        }
        
        return true;
    }
    
    /**
     * 刷单过滤规则
     * 
     * @param unknown $farm
     * @param unknown $sdf
     * @return boolean|Ambigous <boolean, unknown>
     */
    private function _condition($farm, $sdf)
    {
        $return = false;
        
        //商店是否满足要求
        if($farm['shop_ids']) {
            $arrShop = explode(',', $farm['shop_ids']);
            
            $this->trim($arrShop);
            
            $return = in_array($sdf['shop']['shop_id'], $arrShop) ? $farm['farm_id'] : false;
            if(!$return) {
                return false;
            }
        }
        
        //用户账号
        if($farm['user_name']) {
            $users = explode(',', $farm['user_name']);
            
            $this->trim($users);
            
            $return = (in_array($sdf['member_info']['uname'], $users) 
                        || ($sdf['member_info']['buyer_open_uid'] && in_array($sdf['member_info']['buyer_open_uid'], $users))
                    ) ? $farm['farm_id'] : false;
            if(!$return) {
                return false;
            }
        }
        
        //收货人手机
        if($farm['ship_mobile']) {
            $ship_mobile = explode(',', $farm['ship_mobile']);
            
            $this->trim($ship_mobile);
            
            $return = in_array($sdf['consignee']['mobile'], $ship_mobile) ? $farm['farm_id'] : false;
            if(!$return) {
                return false;
            }
        }
        
        //商品货号
        if($farm['product_bn']) {
            $retCheckBn = $this->_matchProductBn($farm['product_bn'], $farm['product_bn_match'], $sdf);
            if(!$retCheckBn) {
                return false;
            }
            
            $return = $farm['farm_id'];
        }
        
        //订单总额 total_amount
        if($farm['condition']) {
            if($farm['condition'] == 'gt') {
                if(!($sdf['total_amount'] > $farm['money'])){
                    return false;
                }
            } elseif($farm['condition'] == 'lt'){
                if(!($sdf['total_amount'] < $farm['money'])){
                    return false;
                }
            } elseif($farm['condition'] == 'eq'){
                if ($sdf['total_amount'] != $farm['money']){
                    return false;
                }
            }
            
            $return = $farm['farm_id'];
        }
        
        //客户备注 custom_mark
        if($farm['custom_mark']) {
            if($sdf['custom_mark'] == trim($farm['custom_mark'])) {
                $return = $farm['farm_id'];
            } else {
                return false;
            }
        }
        
        //商家备注 mark_text
        if($farm['mark_text']) {
            if($sdf['mark_text'] == trim($farm['mark_text'])) {
                $return = $farm['farm_id'];
            } else {
                return false;
            }
        }
        
        //淘宝旗标
        if($farm['mark_type']) {
            if($sdf['mark_type'] == trim($farm['mark_type'])) {
                $return = $farm['farm_id'];
            } else {
                return false;
            }
        }
        
        //详细地址 consignee/addr
        if($farm['ship_addr']) {
            if($sdf['consignee']['addr'] == trim($farm['ship_addr'])) {
                $return = $farm['farm_id'];
            } else {
                return false;
            }
        }
        
        return $return;
    }

    /**
     * 商品货号匹配
     * 
     * @param array $productBn
     * @param int $match
     * @param array $sdf
     * @return boolean
     */
    private function _matchProductBn($productBn, $match, $sdf)
    {
        $arrBn = explode(',', $productBn);
        
        $this->trim($arrBn);
        
        if($match == 0) {
            foreach($sdf['order_objects'] as $objects)
            {
                if (!in_array($objects['bn'], $arrBn)) {
                    return false;
                }
            }
            
            return true;
        } elseif($match == 1) {
            foreach($sdf['order_objects'] as $objects)
            {
                if (in_array($objects['bn'], $arrBn)) {
                    return true;
                }
            }
            
            return false;
        } elseif($match == 2) {
            foreach($sdf['order_objects'] as $objects)
            {
                if (in_array($objects['bn'], $arrBn)) {
                    return false;
                }
            }
            
            return true;
        }
        
        return false;
    }

    /**
     * 去首尾空格
     */
    private function trim(&$arr)
    {
        foreach ($arr as $key => &$value)
        {
            if (is_array($value)) {
                $this->trim($value);
            } elseif (is_string($value)) {
                $value = trim($value);
            }
        }
    }
}