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
 * 定义售后退货单常量
 *
 * @author wangbiao@shopex.cn
 * @version 0.1
 */
class ome_reship_const
{
    //追回发货单标识
    const __REBACK_DELIVERY = 0x00000001;
    
    //追回发货单成功
    const __REBACK_SUCC = 0x00000002;
    
    //追回发货单失败
    const __REBACK_FAIL = 0x00000004;
    
    //货到付款订单
    const __ISCOD_ORDER = 0x00000008;
    
    //零秒退快递拦截
    const __ZERO_INTERCEPT = 0x00000010;
    
    //新退换货单标识
    const __NEW_EXCHANGE_REPAIR = 0x00000020;
    
    //平台退货
    const __PLATFORM_RESHIP = 0x00000040;

    //拦截入库
    const __LANJIE_RUKU = 0x00000080;

    //退货差异入库
    const __RESHIP_DIFF = 0x00000100;

    const __EDIT_RETURN_CODE = 0x00000200;
    const __EDIT_CHANGE_CODE = 0x00000400;
    const __YUANDANTUI = 0x00000800;
    const __QZ_REFUND_CODE = 0x80000;
    
    // 退回物流单号重复
    const __RETURN_LOGI_DUP = 0x00001000;
  
    //是否是新退换货单
    /**
     * isNewExchange
     * @param mixed $flagType flagType
     * @return mixed 返回值
     */

    public function isNewExchange($flagType)
    {
        return $flagType & self::__NEW_EXCHANGE_REPAIR ? true : false;
    }

    /**
     * 获取Html
     * @param mixed $flag_type flag_type
     * @return mixed 返回结果
     */
    public function getHtml($flag_type) {
        $ret = '';
        if($flag_type){
            if($flag_type & ome_reship_const::__REBACK_DELIVERY){
                $style = array('color'=>'#3E3E3E', 'msg'=>'追回发货单', 'flag'=>'追回入库');
                $ret .= $this->getViewPanel($style['color'], $style['msg'], $style['flag']);
            }
            
            if($flag_type & ome_reship_const::__REBACK_FAIL){
                $style = array('color'=>'#ff0000', 'msg'=>'追回发货单失败', 'flag'=>'追回入库失败');
                $ret .= $this->getViewPanel($style['color'], $style['msg'], $style['flag']);
            }
            
            if($flag_type & ome_reship_const::__ISCOD_ORDER){
                $style = array('color'=>'green', 'msg'=>'货到付款订单', 'flag'=>'货到付款');
                $ret .= $this->getViewPanel($style['color'], $style['msg'], $style['flag']);
            }
    
            if($flag_type & ome_reship_const::__NEW_EXCHANGE_REPAIR){
                $style = array('color'=>'#F18A50', 'msg'=>'新换货单', 'flag'=>'新换货单');
                $ret .= $this->getViewPanel($style['color'], $style['msg'], $style['flag']);
            }
    
            if($flag_type & ome_reship_const::__ZERO_INTERCEPT){
                $style = array('color'=>'#F183A0', 'msg'=>'零秒退快递拦截', 'flag'=>'零秒退拦截');
                $ret .= $this->getViewPanel($style['color'], $style['msg'], $style['flag']);
            }
            if($flag_type & ome_reship_const::__LANJIE_RUKU){
                $style = array('color'=>'red', 'msg'=>'拦截入库', 'flag'=>'拦截入库');
                $ret .= $this->getViewPanel($style['color'], $style['msg'], $style['flag']);
            }
            if($flag_type & ome_reship_const::__QZ_REFUND_CODE){
                $style = array('color'=>'#3E3E3E', 'msg'=>'强制退款', 'flag'=>'强制退款');
                $ret .= $this->getViewPanel($style['color'], $style['msg'], $style['flag']);
            }
            if($flag_type & ome_reship_const::__RESHIP_DIFF){
                $style = array('color'=>'#BDB76B', 'msg'=>'退货差异入库', 'flag'=>'差异入库');
                $ret .= $this->getViewPanel($style['color'], $style['msg'], $style['flag']);
            }

            if($flag_type & ome_reship_const::__EDIT_RETURN_CODE){
                $style = array('color'=>'LimeGreen', 'msg'=>'换转退', 'flag'=>'换转退');
                $ret .= $this->getViewPanel($style['color'], $style['msg'], $style['flag']);
            }
            if($flag_type & ome_reship_const::__EDIT_CHANGE_CODE){
                $style = array('color'=>'#D2B48C', 'msg'=>'退转换', 'flag'=>'退转换');
                $ret .= $this->getViewPanel($style['color'], $style['msg'], $style['flag']);
            }
            if($flag_type & ome_reship_const::__YUANDANTUI){
                $style = array('color'=>'#D2B48C', 'msg'=>'原单退', 'flag'=>'原单退');
                $ret .= $this->getViewPanel($style['color'], $style['msg'], $style['flag']);
            }
            if($flag_type & ome_reship_const::__RETURN_LOGI_DUP){
                $style = array('color'=>'#9370DB', 'msg'=>'退回物流单号重复', 'flag'=>'物流重复');
                $ret .= $this->getViewPanel($style['color'], $style['msg'], $style['flag']);
            }
            $ret = '<div style="overflow: auto;word-break: break-word;white-space: normal;width: 100%;flex-wrap: wrap;display: flex;">'.$ret.'</div>';
        }
        return $ret;
    }

    /**
     * 获取ViewPanel
     * @param mixed $color color
     * @param mixed $msg msg
     * @param mixed $title title
     * @return mixed 返回结果
     */
    public function getViewPanel($color, $msg, $title)
    {
        $html =  sprintf("<span title='%s' style='filter: brightness(0.9) contrast(0.9);border:1px solid %s; color:%s;margin: 2px;padding: 0px 2px;border-radius: 5px;white-space: nowrap;'>%s</span>", $msg, $color, $color, $title);

        return $html;
    }
    
    /**
     * 获取BoolType
     * @param mixed $filter filter
     * @return mixed 返回结果
     */
    public function getBoolType($filter) {
        $where = array();
        if($filter['in']) {
            $in = 0;
            foreach((array)$filter['in'] as $val) {
                $in = $in | $val;
            }
            $where[] = 'flag_type & ' . $in . ' = ' . $in;
        }
        if($filter['out']) {
            $out = 0;
            foreach((array)$filter['out'] as $val) {
                $out = $out | $val;
            }
            $where[] = '!(flag_type & ' . $out . ')';
        }
        if(empty($where)) {
            return array();
        }
        $sql = 'select flag_type from sdb_ome_reship where ' . implode(' and ', $where) . ' group by flag_type';
        $flagData = kernel::database()->select($sql);
        $flagStatus = array('-1');
        foreach($flagData as $val) {
            $flagStatus[] = $val['flag_type'];
        }
        return $flagStatus;
    }
}
