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

class channel_finder_channel
{
    public $addon_cols = 'node_id,node_type,channel_type,shipper';

    public $column_opt = '操作';
    /**
     * column_opt
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_opt($row)
    {
        $finder_id = $_GET['_finder']['finder_id'];

        $buttons = [
            'edit' => sprintf(
                '<a href="index.php?app=channel&ctl=admin_channel&act=edit&p[0]=%s&finder_id=%s" target="dialog::{width:700,height:400,title:\'编辑\'}">编辑</a>',
                $row['channel_id'],
                $finder_id
            ),
            'apply' => sprintf(
                '<a href="index.php?app=channel&ctl=admin_channel&act=apply_bindrelation&p[0]=%s&finder_id=%s" target="dialog::{width:800,title:\'申请绑定\',onClose:function(){window.finderGroup[\'%s\'].refresh();}}">申请绑定</a>',
                $row['channel_id'],
                $finder_id,
                $finder_id
            ),
        ];

        if ($row[$this->col_prefix . 'node_id']) {
            unset($buttons['apply']);
        }
        if ($row[$this->col_prefix . 'channel_type'] == 'cloudprint') {
            unset($buttons['apply']);
        }
        if ($row[$this->col_prefix . 'channel_type'] == 'sms' && $row[$this->col_prefix . 'node_type'] == 'shopex') {
            unset($buttons['apply']);
            $adapter = app::get('channel')->model('adapter')->dump(array('channel_id' => $row['channel_id']), 'config');
            if ($adapter && $adapter['config']) {
                $adapter['config'] = unserialize($adapter['config']);
            }
            $label = $adapter && $adapter['config'] ? '查看账号' : '申请绑定';
            $buttons['shopex_sms_account'] = sprintf('<a href="index.php?app=channel&ctl=admin_channel&act=shopex_sms_account&p[0]=%s&finder_id=%s" target="dialog::{width:800,title:\'%s\'}">%s</a>', $row['channel_id'], $finder_id, $label, $label);
        }
        return implode(' | ', $buttons);
    }
}
