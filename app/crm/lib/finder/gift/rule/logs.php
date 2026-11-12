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
 * 赠品规则记录
 *
 * @author wangbiao@shopex.cn
 * @version v0.1
 */
class crm_finder_gift_rule_logs
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $addon_cols = 'rule_id,gift_id';
    
    public $_giftObj = null;
    
    public $_ruleObj = null;
    
    /**
     * __construct
     * @return mixed 返回值
     */

    public function __construct()
    {
        $this->_giftObj = app::get('crm')->model('gift');
        $this->_ruleObj = app::get('crm')->model('gift_rule_base');
    }
    
    var $column_edit = '操作';
    var $column_edit_width = 100;
    var $column_edit_order = 1;
    function column_edit($row)
    {
        $finder_id = $_GET['_finder']['finder_id'] ?? '';
        $sid = $row['sid'];
        
        if ($this->vue_mode) {
            return [
                ['key' => 'edit', 'label' => '预警设置', 'action' => 'edit', 'sid' => $sid]
            ];
        }
        
        $url = sprintf('index.php?app=crm&ctl=admin_gift_rule_logs&act=edit&sid=%d&finder_id=%s', $sid, $finder_id);
        
        $button = '<a href="'. $url .'" target="dialog::{width:600,height:400,title:\'预警设置\'}">预警设置</a>';
        return $button;
    }
    
    var $column_rule_name = '规则名称';
    var $column_rule_name_width = 130;
    var $column_rule_name_order = 19;
    function column_rule_name($row)
    {
        $ruleInfo = $this->_ruleObj->dump(array('id'=>$row[$this->col_prefix.'rule_id']), 'title');
        $title = $ruleInfo['title'] ?? '';
        
        if ($this->vue_mode) {
            return ['text' => $title];
        }
        
        return $title;
    }
    
    var $column_rule_status = '规则状态';
    var $column_rule_status_width = 90;
    var $column_rule_status_order = 20;
    function column_rule_status($row)
    {
        $ruleInfo = $this->_ruleObj->dump(array('id'=>$row[$this->col_prefix.'rule_id']), 'id');
        $status = $ruleInfo ? '使用中' : '已删除';
        $type = $ruleInfo ? 'success' : 'info';
        
        if ($this->vue_mode) {
            return ['text' => $status, 'type' => $type];
        }
        
        return $status;
    }
    
    var $column_gift_name = '赠品名称';
    var $column_gift_name_width = 130;
    var $column_gift_name_order = 23;
    function column_gift_name($row)
    {
        $productInfo = $this->_giftObj->dump(array('gift_id'=>$row[$this->col_prefix.'gift_id']), 'gift_name');
        $name = $productInfo['gift_name'] ?? '';
        
        if ($this->vue_mode) {
            return ['text' => $name];
        }
        
        return $name;
    }
    
    var $column_gift_status = '赠品状态';
    var $column_gift_status_width = 90;
    var $column_gift_status_order = 24;
    function column_gift_status($row)
    {
        $productInfo = $this->_giftObj->dump(array('gift_id'=>$row[$this->col_prefix.'gift_id']), 'gift_id,is_del');
        $status = $productInfo['is_del'] == '1' ? '禁用' : '启用';
        $type = $productInfo['is_del'] == '1' ? 'danger' : 'success';
        
        if ($this->vue_mode) {
            return ['text' => $status, 'type' => $type];
        }
        
        return $status;
    }
}
