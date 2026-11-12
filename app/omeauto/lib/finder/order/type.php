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

class omeauto_finder_order_type {

    static $oRlue = null;
    var $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
        // API ctl is api_order_type (not order_type/autobranchset); restore labels blanked in __construct.
        if ($vue) {
            $this->column_autoconfirm = '自动审单规则';
            $this->column_autodispatch = '自动分配规则';
            $this->column_autobranch = '发货仓库';
        }
    }
    function __construct() {
        if (self::$oRlue === null) {
            $rows = app::get('omeauto')->model('autoconfirm')->getList('oid,name,disabled');//, array('disabled' => 'false'));
            foreach((array)$rows as $t) {
                self::$oRlue['oc'][$t['oid']] = $t;
            }
            $rows = app::get('omeauto')->model('autodispatch')->getList('oid,name,disabled');//, array('disabled' => 'false'));
            foreach((array)$rows as $t) {
                self::$oRlue['od'][$t['oid']] = $t;
            }
            $rows = app::get('ome')->model('branch')->getList('branch_id,name,disabled', array('is_deliv_branch' => 'true'));
            foreach((array)$rows as $t) {
                self::$oRlue['bd'][$t['branch_id']] = $t;
            }
            
            // 添加参加O2O的门店特殊选项到仓库列表中
            self::$oRlue['bd'][-1] = array(
                'branch_id' => -1,
                'name' => '参加O2O的门店',
                'disabled' => 'false'
            );
        }
        // Legacy HTML pages share this finder; blank unrelated column titles by ctl.
        // Vue/API restores labels via setVueMode(true).
        if($_GET['ctl'] != 'order_type') {
            $this->column_autodispatch = '';
            $this->column_autoconfirm = '';
        }
        if($_GET['ctl'] != 'autobranchset') {
            $this->column_autobranch = '';
        }
    }
    
    var $addon_cols = "tid,oid,did,bid,config,memo,disabled,group_type";
    
    var $column_confirm = '操作';
    var $column_confirm_width = "90";
    function column_confirm($row) {
        $tid       = $row['tid'];
        $groupType = $row['_0_group_type'];
        $disabled  = ($row['_0_disabled'] == 'true');
        $finderId  = $_GET['_finder']['finder_id'] ?? '';
        $finder_vid = $_GET['_finder']['finder_vid'];
        
        // Define action configs once (same data for both Vue and HTML modes)
        $actions = [];
        if (in_array($groupType, ['order', 'branch', 'hold'])) {
            $actions[] = ['key' => 'toggle', 'label' => $disabled ? '启用' : '暂停', 'action' => 'setStatus',     'params' => [$tid, $disabled ? 'false' : 'true']];
            $actions[] = ['key' => 'edit',   'label' => '修改',           'action' => 'edit',          'params' => [$tid]];
            if ($groupType === 'branch') {
                $actions[] = ['key' => 'set', 'label' => '设置', 'action' => 'autobranchset', 'params' => [$tid]];
            } elseif ($groupType === 'hold') {
                $actions[] = ['key' => 'set', 'label' => '设置', 'action' => 'autohold',      'params' => [$tid]];
            }
        } elseif ($groupType === 'sms') {
            $actions[] = ['key' => 'toggle', 'label' => $disabled ? '启用' : '暂停', 'action' => 'sms_setStatus', 'params' => [$tid, $disabled ? 'false' : 'true']];
            $actions[] = ['key' => 'edit',   'label' => '修改',                    'action' => 'sms_edit',     'params' => [$tid]];
        }

        if ($this->vue_mode) {
            return $actions;
        }

        // Legacy HTML mode — render each action config to HTML
        $html = '';
        foreach ($actions as $a) {
            if ($a['key'] === 'toggle') {
                $act = ($a['action'] === 'sms_setStatus')
                    ? "index.php?app=taoexlib&ctl=admin_sms_rule&act=setStatus&finder_id=".$finderId."&finder_vid=".$finder_vid
                    : "index.php?app=omeauto&ctl=order_type&act=setStatus&finder_id=".$finderId."&finder_vid=".$finder_vid;
                $url = "{$act}&p[0]={$tid}&p[1]=" . ($disabled ? 'true' : 'false') . "&finder_id={$finderId}";
                $label = $a['label'];
                $confirm = $disabled
                    ? "你确定要启用指定规则的使用吗？？？\\n\\n注意：你还可以随时暂停指定规则。"
                    : "你确定要暂时停止指定规则的使用吗？？？\\n\\n注意：你还可以随时启用指定规则。";
                $html .= "<a href='javascript:void(0);' target='download' onclick='if(confirm(\"{$confirm}\")) {href=\"{$url}\";}'>{$label}</a>&nbsp;";
            } elseif ($a['key'] === 'edit') {
                $act = ($a['action'] === 'sms_edit')
                    ? "index.php?app=taoexlib&ctl=admin_sms_rule&act=edit_rule"
                    : "index.php?app=omeauto&ctl=order_type&act=edit";
                
                $title = ($a['action'] === 'sms_edit') ? '修改分组规则' : '修改分组规则';
                $width = 760; $height = 480;
                $sep = ($groupType === 'sms') ? '&nbsp;|&nbsp;' : '&nbsp;';
                $html .= "{$sep}<a href='javascript:void(0);' onclick=\"new Dialog('{$act}&p[0]={$tid}&finder_id={$finderId}',{width:{$width},height:{$height},title:'{$title}'}); \">修改</a>";
            } elseif ($a['key'] === 'set') {
                if ($groupType === 'branch') {
                    $dlgUrl = "index.php?app=omeauto&ctl=autobranchset&act=setBind&p[0]={$tid}&finder_id={$finderId}";
                    $html .= "&nbsp;<a href='javascript:void(0);' onclick=\"new Dialog('{$dlgUrl}',{width:760,height:500,title:'设置仓库'}); \">设置</a>";
                } elseif ($groupType === 'hold') {
                    $dlgUrl = "index.php?app=omeauto&ctl=autohold&act=set&p[0]={$tid}&finder_id={$finderId}";
                    $html .= "&nbsp;<a href='javascript:void(0);' onclick=\"new Dialog('{$dlgUrl}',{width:550,height:200,title:'设置hold单'}); \">设置</a>";
                }
            }
        }
        return $html;
    }
    function getsampleno($tid){
        $sr         = app::get('taoexlib')->model('sms_bind');
        $srinfo = $sr->select()->columns()->where('tid=?',$tid)->instance()->fetch_row();
        $sms_sample = app::get('taoexlib')->model('sms_sample');
        $srinfo = $sms_sample->select()->columns()->where('id=?',$srinfo['id'])->instance()->fetch_row();
        return $srinfo['id'];
    }
    var $column_autoconfirm = '自动审单规则';
    var $column_autoconfirm_width = "150";
    function column_autoconfirm($row) {
        $oid = intval($row['_0_oid']);
        $finderId = $_GET['_finder']['finder_id'] ?? '';

        if ($oid <= 0) {
            return $this->vue_mode ? null : '';
        }

        $rule = self::$oRlue['oc'][$oid] ?? null;
        if (!$rule) {
            return $this->vue_mode ? null : '';
        }

        $disabled = ($rule['disabled'] == 'true');
        $link = "index.php?app=omeauto&ctl=autoconfirm&act=edit&p[0]={$oid}&finder_id={$finderId}";

        if ($this->vue_mode) {
            return [
                'label' => $rule['name'],
                'link'  => $link,
                'disabled' => $disabled,
            ];
        }

        if ($disabled) {
            return "<span style='color:#DDDDDD;' title='该规则已经暂停使用'>(".$rule['name'].")</span>";
        }
        return "<a href='javascript:void(0);' onclick=\"new Dialog('{$link}',{width:750,height:480,title:'修改审单规则'}); \">".$rule['name']."</a>";
    }
    
    var $column_autodispatch = '自动分配规则';
    var $column_autodispatch_width = "150";
    function column_autodispatch($row) {
        $did = intval($row['_0_did']);
        $finderId = $_GET['_finder']['finder_id'] ?? '';

        if ($did <= 0) {
            return $this->vue_mode ? null : '';
        }

        $rule = self::$oRlue['od'][$did] ?? null;
        if (!$rule) {
            return $this->vue_mode ? null : '';
        }

        $disabled = ($rule['disabled'] == 'true');
        $link = "index.php?app=omeauto&ctl=autodispatch&act=edit&p[0]={$did}&finder_id={$finderId}";

        if ($this->vue_mode) {
            return [
                'label' => $rule['name'],
                'link'  => $link,
                'disabled' => $disabled,
            ];
        }

        if ($disabled) {
            return "<span style='color:#DDDDDD;' title='该规则已经暂停使用'>(".$rule['name'].")</span>";
        }
        return "<a href='javascript:void(0);' onclick=\"new Dialog('{$link}',{width:760,height:400,title:'修改自动分派规则'}); \">".$rule['name']."</a>";
    }
    
    var $column_autobranch = '发货仓库';
    var $column_autobranch_width = "150";
    function column_autobranch($row) {
        $bid = intval($row['_0_bid']);
        $finderId = $_GET['_finder']['finder_id'] ?? '';

        if ($bid == 0) {
            return $this->vue_mode ? null : '';
        }

        $branch = self::$oRlue['bd'][$bid] ?? null;
        if (!$branch) {
            return $this->vue_mode ? null : '';
        }

        $disabled = ($branch['disabled'] == 'true');

        if ($this->vue_mode) {
            return [
                'label' => $branch['name'],
                'disabled' => $disabled,
            ];
        }

        if ($disabled) {
            return "<span style='color:#DDDDDD;' title='该仓库已经暂停使用'>(".$branch['name'].")</span>";
        }
        return $branch['name'];
    }
    
    var $column_memo = '简单说明';
    var $column_memo_width = "250";
    function column_memo($row) {
        
        return $row['_0_memo'];
    }
    
    var $column_content = '类型内容';
    var $column_content_width = "250";
    function column_content($row) {
        $items = [];
        if (!empty($row['_0_config'])) {
            foreach ($row['_0_config'] as $cfg) {
                $role = json_decode($cfg, true);
                if (!empty($role['caption'])) {
                    $items[] = $role['caption'];
                }
            }
        }
        $text = implode(' ; ', $items);
        if ($this->vue_mode) {
            return $text;
        }
        return "<div onmouseover='bindFinderColTip(event)' rel='{$text}'>" . str_replace(';', '&nbsp;;&nbsp;', $text) . "<div>";
    }
    
    var $column_disabled = '是否启用';
    var $column_disabled_width = "80";
    function column_disabled($row) {
        $disabled = ($row['_0_disabled'] == 'true');

        // vue_mode: return {label, value} array so Vue slot receives a structured object
        if ($this->vue_mode) {
            return [
                'label' => $disabled ? '停用' : '启用',
                'value' => $disabled ? '1' : '0',
            ];
        }

        if ($disabled) {
            return "<span style='color:red;'>停用</span>";
        }
        return "<span style='color:green;'>启用</span>";
    }
}