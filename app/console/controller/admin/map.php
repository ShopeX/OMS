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

class console_ctl_admin_map extends desktop_controller {
    public $name = '第三方仓储';
    public $workground = "console_center";

    public function index(){
        $params = array(
            'title' => '商品映射',
            'actions' => array(
                    'add' => array(
                        'label' => '映射',
                        'submit' => 'index.php?app=console&ctl=admin_map&act=mapUpdate&p[0]=add',
                        'target' => "dialog::{width:700,height:200,title:'添加映射'}",
                    ),
                    'del' => array(
                        'label' => '解除映射',
                        'submit' => 'index.php?app=console&ctl=admin_map&act=mapUpdate&p[0]=del',
                        'target' => "dialog::{width:700,height:200,title:'解除映射'}",
                    ),
            ),
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'=>false,
            'use_buildin_recycle'=>false,
            'use_buildin_export'=>false,
            'use_buildin_import'=>false,
        );
        $this->finder('console_mdl_map_goods', $params);
    }

    public function mapUpdate($operateType) {
        $filter = array();
        $filter['id'] = $_REQUEST['id'];
        $filter['mapping'] = '1';
        $list = app::get('console')->model('map_goods')->getList('id', $filter);
        if(empty($list))
        {
            die('没有可映射的货号，或者货号没有映射本地货品。');
        }
        
        $wmsData = app::get('channel')->model('channel')->getList('channel_id,channel_name', array('node_type' => 'qimen'));
        $this->pagedata['map_goods'] = $list;
        $this->pagedata['wms_data'] = $wmsData;
        $this->pagedata['operate_type'] = $operateType;
        $this->display('admin/material/sync_map.html');
    }

    public function doMapUpdate() {
        $wmsId = (int) $_POST['wms_id'];
        $id = $_POST['id'];
        $operateType = $_POST['operate_type'];
        
        $rs = kernel::single('console_event_trigger_goodssync')->syncMap($id, $wmsId, $operateType);
        echo ($rs['rsp'] == 'succ' ? '' : '同步失败：' . ($rs['err_msg']?$rs['err_msg']:$rs['msg'])) . '|ok.';
        exit();
    }
}
