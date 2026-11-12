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


/* TODO: Add code here */
class desktop_finder_users{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $column_control = '操作';
    function __construct($app){
        $this->app=$app;
    }
    var $addon_cols = 'is_lock,super';
    function column_control($row){
        $list['edit'] = '<a href="index.php?app=desktop&ctl=users&act=edit&_finder[finder_id]='.$_GET['_finder']['finder_id'].'&p[0]='.$row['user_id'].'" target="dialog::{title:\''.app::get('desktop')->_('编辑操作员').'\', width:750, height:650}">'.app::get('desktop')->_('编辑').'</a>';
        if($row[$this->col_prefix.'is_lock'] == '1') {
            $list['unLock'] = '<a href="index.php?app=desktop&ctl=users&act=unLock&_finder[finder_id]='.$_GET['_finder']['finder_id'].'&p[0]='.$row['user_id'].'">'.app::get('desktop')->_('解锁').'</a>';
        }
        $is_operator_edit = kernel::single('desktop_user')->has_permission('operator_edit');
        if(!$is_operator_edit){
            unset($list['edit']);
        } else {
            // 超级管理员不可复制
            if (!$this->_isSuperUser($row)) {
                $list['clone'] = '<a href="index.php?app=desktop&ctl=users&act=edit&clone=true&_finder[finder_id]='.$_GET['_finder']['finder_id'].'&p[0]='.$row['user_id'].'" target="dialog::{title:\''.app::get('desktop')->_('复制操作员').'\', width:750, height:650}">'.app::get('desktop')->_('复制').'</a>';
            }
        }
        // Vue mode: return structured array for dynamic row actions
        if ($this->vue_mode) {
            $actions = [];
            if (isset($list['edit'])) {
                $actions[] = ['key' => 'edit', 'label' => '编辑', 'action' => 'edit'];
            }
            if (isset($list['unLock'])) {
                $actions[] = ['key' => 'unLock', 'label' => '解锁', 'action' => 'unLock'];
            }
            if (isset($list['clone'])) {
                $actions[] = ['key' => 'clone', 'label' => '复制', 'action' => 'clone'];
            }
            return $actions;
        }

        return implode(' | ', $list);
    }

    /**
     * 是否超级管理员（行内 super 字段或 addon 前缀字段）
     * 注意：列表 pipeline 中 intbool 可能已将 super 转为「是/否」文案
     */
    private function _isSuperUser(array $row): bool
    {
        $candidates = [
            $row['super'] ?? null,
            $row[$this->col_prefix . 'super'] ?? null,
            $row['_0_super'] ?? null,
        ];
        foreach ($candidates as $super) {
            if ($super === '1' || $super === 1 || $super === true) {
                return true;
            }
            if ($super === app::get('base')->_('是')) {
                return true;
            }
        }
        return false;
    }

    public $column_roles = '角色';
    public $column_roles_width = 260;
    public $column_roles_order = 300;
    private $_roles = null;
    /**
     * column_roles
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_roles($row, $list)
    {
        if (!isset($this->_roles)) {
            $userIds = [];
            foreach ((array) $list as $listRow) {
                $pk = $this->resolveUserPk((array) $listRow);
                if ($pk !== null) {
                    $userIds[] = $pk;
                }
            }
            $userIds = array_values(array_unique($userIds));

            if (!$userIds) {
                $this->_roles = [];
                return '';
            }

            $hasRoles = app::get('desktop')->model('hasrole')->getList('*', ['user_id' => $userIds]);

            if (!$hasRoles) {
                $this->_roles = [];
                return '';
            }

            $roles = app::get('desktop')->model('roles')->getList('role_id,role_name', ['role_id' => array_column($hasRoles, 'role_id')]);
            $roles = array_column($roles, 'role_name', 'role_id');

            $this->_roles = [];
            foreach ($hasRoles as $hasRole) {
                $roleName = $roles[$hasRole['role_id']] ?? '';
                if ($roleName === '' || $roleName === null) {
                    continue;
                }
                $this->_roles[$hasRole['user_id']][] = $roleName;
            }
        }

        $pk = $this->resolveUserPk((array) $row);
        $text = implode('、', (array)($this->_roles[$pk] ?? []));

        if ($this->vue_mode) {
            return $text;
        }

        $html_s = '<div style="overflow: auto;word-break: break-word;white-space: normal;width: 100%;flex-wrap: wrap;display: flex;">';
        $html_e = '</div>';
        return $html_s . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . $html_e;
    }
    
    /**
     * 解析列表行上的用户主键。
     * Vue 路径下 modifier_user_id 可能已把 user_id 换成登录名/脱敏数组，此时用 _pkvalue_raw；
     * 旧列表/导出没有 _pkvalue_raw，仍用标量 user_id。
     */
    private function resolveUserPk(array $row)
    {
        foreach (['_pkvalue_raw', 'user_id'] as $key) {
            if (!array_key_exists($key, $row)) {
                continue;
            }
            
            $value = $row[$key];
            if (is_scalar($value) && $value !== '' && $value !== null) {
                return $value;
            }
        }
        
        return null;
    }
}