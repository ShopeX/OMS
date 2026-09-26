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
 * 检查备注和旗标
 *
 * @author hzjsq@msn.com
 * @version 0.1b
 */
class omeauto_auto_plugin_flag extends omeauto_auto_plugin_abstract implements omeauto_auto_plugin_interface {

    /**
     * 是否支持批量审单
     */
    protected $__SUP_REP_ROLE = true;
    
    /** 
     * 状态码
     */
    protected $__STATE_CODE = omeauto_auto_const::__FLAG_CODE;

    /**
     * 开始处理
     *
     * @param omeauto_auto_group_item $group 要处理的订单组
     * @return Array
     */
    public function process(& $group , &$confirmRoles=null) {

        $allow = true;
        $fields = $this->_getCheckField($confirmRoles);
        $remarkRules = $this->_getRemarkRules($confirmRoles);
        
        $mark = kernel::single('omeauto_auto_group_mark');
        foreach ($group->getOrders() as $order) {

            //检查标记是否已经确认
            $markText = $this->getMark($order['mark_text']);
            $customText = $this->getMark($order['custom_mark']);
            
            if ($mark->isConfirm($markText, $customText)) {
                 
                //如需检查客户留言，订单可过，但打上有备注的标记
                if (in_array('custom_mark', $fields)) {
                    
                    if ($this->_matchRemark($customText, $remarkRules['custom_mark'])) {
                        $group->setOrderStatus($order['order_id'], $this->getMsgFlag());
                    }
                }
            } else {
                //备注
                foreach ($fields as $field) {
                    $remarkText = $field == 'custom_mark' ? $customText : $markText;
                    if ($this->_matchRemark($remarkText, $remarkRules[$field])) {
                        $allow = false;
                        $group->setOrderStatus($order['order_id'], $this->getMsgFlag());
                        break;
                    }
                }
            }
        }

        if (!$allow) { 

            $group->setStatus(omeauto_auto_group_item::__OPT_HOLD, $this->_getPlugName());
        }
    }

    /**
     * 获取配置信息
     *
     * @param void
     * @return array
     */
    private function _getCheckField($configRoles) {

        $result = array();
        if ($configRoles['memo'] == '1') {

            $result[] = 'custom_mark';
        }

        if ($configRoles['mark'] == '1') {

            $result[] = 'mark_text';
        }

        return $result;
    }

    /**
     * 获取备注关键字规则
     *
     * @param array $configRoles
     * @return array
     */
    private function _getRemarkRules($configRoles)
    {
        return array(
            'custom_mark' => $this->_parseKeywords(isset($configRoles['member_remark']) ? $configRoles['member_remark'] : ''),
            'mark_text' => $this->_parseKeywords(isset($configRoles['order_remark']) ? $configRoles['order_remark'] : ''),
        );
    }

    /**
     * 按竖线分隔关键字
     *
     * @param string $keywords
     * @return array
     */
    private function _parseKeywords($keywords)
    {
        $result = array();
        foreach (explode('|', (string) $keywords) as $keyword) {
            $keyword = trim($keyword);
            if ($keyword !== '') {
                $result[] = $keyword;
            }
        }

        return array_unique($result);
    }

    /**
     * 校验备注是否命中规则
     *
     * @param string $remarkText
     * @param array $keywords
     * @return bool
     */
    private function _matchRemark($remarkText, $keywords)
    {
        $remarkText = trim((string) $remarkText);
        if ($remarkText === '') {
            return false;
        }

        if (empty($keywords)) {
            return true;
        }

        foreach ((array) $keywords as $keyword) {
            if ($keyword !== '' && stripos($remarkText, $keyword) !== false) {
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

        return '有备注订单';
    }

    /**
     * 获取提示信息
     *
     * @param Array $order 订单内容
     * @return Array
     */
    public function getAlertMsg(& $order) {

        $html = '';
        if ($order['custom_mark']) {

            $html .= '<b>客户留言：</b><br />';
            $custom_mark = kernel::single('ome_func')->format_memo($order['custom_mark']);
            if (!empty($custom_mark)) {
                foreach ((array) $custom_mark as $k => $v) {
                    $html .= $v['op_content'] . "<br /><br/>";
                }
            } else {

                $html .= $order['custom_mark'] . "<br /><br/>";
            }
        }
        if ($order['mark_text']) {
            $html .= '<b>客服留言：</b><br />';
            $mark_text = kernel::single('ome_func')->format_memo($order['mark_text']);
            if (!empty($mark_text)) {
                foreach ((array) $mark_text as $k => $v) {
                    $html .= $v['op_content'] . "<br />";
                }
            } else {

                $html .= $order['mark_text'] . "<br />";
            }
        }
        
        $html = strip_tags(htmlspecialchars($html));
        
        return array('color' => '#666666', 'flag' => '备', 'msg' => $html);
    }
    
    /**
     * 获取用于快速审核的选项页，输出HTML代码
     * 
     * @param void
     * @return String
     */
    public function getInputUI() {
        
    }
}