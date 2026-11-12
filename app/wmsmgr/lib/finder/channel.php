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

class wmsmgr_finder_channel
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $addon_cols = "channel_id";
    var $column_edit = "操作";
    var $column_edit_width = "180";
    var $column_edit_order = "1";
    
    function column_edit($row)
    {
        // Vue Finder 契约：结构化行操作（不再下发 legacy <a> HTML 片段，SPA 不解析 legacy 链接）
        if ($this->vue_mode) {
            $channel_id   = $row['_0_channel_id'] ?? $row['channel_id'] ?? '';
            $channel_name = $row['channel_name'] ?? '';

            $actions = array(
                array(
                    'key'    => 'init_material',
                    'label'  => '初始化',
                    'action' => 'index.php?app=wmsmgr&ctl=admin_wms&act=yjdf_sync_material&p[0]=' . $channel_id,
                    'target' => array('type' => 'dialog', 'width' => 600, 'height' => 350, 'title' => '初始化物料'),
                ),
            );

            // 渠道商品总数：无关联商品才允许删除（legacy 同款条件）
            $materialChannelMdl = app::get('material')->model('basic_material_channel');
            if (empty($materialChannelMdl->count(array('channel_id' => $channel_id)))) {
                $actions[] = array(
                    'key'     => 'delete',
                    'label'   => '删除',
                    'confirm' => '确定要删除【' . $channel_name . '】此渠道吗？',
                );
            }

            return $actions;
        }

        $materialChannelMdl = app::get('material')->model('basic_material_channel');

        $channel_id = $row[$this->col_prefix . 'channel_id'];
        $bind_btn = '';
        $finder_id = $_GET['_finder']['finder_id'];
        $url = "index.php?app=wmsmgr&ctl=admin_channel";
        
        $bind_btn .= sprintf('<a href="index.php?app=wmsmgr&ctl=admin_wms&act=yjdf_sync_material&p[0]=%s" target="dialog::{width:600,height:350,title:\'初始化物料\'}" style="">初始化</a>', $channel_id);
        
        //渠道商品总数
        $countNum = $materialChannelMdl->count(array('channel_id'=>$channel_id));
        if(empty($countNum)){
            $delMsg = '确定要删除【'. $row['channel_name'] .'】此渠道吗？';
            $bind_btn .= sprintf(' | <a href="javascript:if(confirm(\'%s\')){W.page(\'%s&act=delChannel&p[0]=%s&finder_id=%s\', $extend({method: \'get\'}, JSON.decode({})), this);}void(0);">删除</a>', $delMsg, $url, $channel_id, $finder_id);
        }
        
        return $bind_btn;
    }
}
