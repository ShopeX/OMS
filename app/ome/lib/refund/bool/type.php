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

class ome_refund_bool_type
{
    //是否是价保退款单
    const __PROTECTED_CODE = 0x0001;
    //零秒退快递拦截
    const __ZERO_INTERCEPT = 0x0002;
    //手工确认为仅退款
    const __ONLY_REFUND = 0x0004;
    
    private $boolStatus = array(
        self::__PROTECTED_CODE => array('identifier'=>'保', 'text'=>'价保退款单', 'color'=>'RED', 'search'=>'true'),
        self::__ZERO_INTERCEPT => array('identifier'=>'拦', 'text'=>'零秒退快递拦截', 'color'=>'#F183A0', 'search'=>'true'),
        self::__ONLY_REFUND => array('identifier'=>'仅', 'text'=>'手工确认为仅退款', 'color'=>'blue', 'search'=>'true'),
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
     * 获取BoolTypeIdentifier
     * @param mixed $boolType boolType
     * @return mixed 返回结果
     */
    public function getBoolTypeIdentifier($boolType)
    {
        $str = '';
        foreach ($this->boolStatus as $k => $val) {
            if ($boolType & $k) {
                $str .= sprintf("<span class='tag-label' title='%s' style='background-color:%s;color:#ffffff;'>%s</span>", $val['text'], $val['color'], $val['identifier']);
            }
        }
        return $str;
    }
    
    /**
     * 支持列表搜索项
     * @return array
     */
    public function getSearchOptions()
    {
        $options = array();
        
        foreach ($this->boolStatus as $k => $v)
        {
            if ($v['search'] == 'true') {
                $options[$k] = $v['text'];
            }
        }
        
        return $options;
    }
    
    //是否价保退款单
    public function isPriceProtectRefund($boolType)
    {
        return $boolType & self::__PROTECTED_CODE ? true : false;
    }
}
