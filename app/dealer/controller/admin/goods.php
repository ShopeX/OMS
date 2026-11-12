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
 * ============================
 * @Author:   yaokangming
 * @Version:  1.0
 * @DateTime: 2020/9/7 14:09:36
 * @describe: 经销商商品
 * ============================
 */
class dealer_ctl_admin_goods extends desktop_controller {

    /**
     * index
     * @return mixed 返回值
     */

    public function index() {
        $actions = array(
            array('label'=>'成本调整','href'=>'index.php?app=dealer&ctl=admin_goods&act=create','target'=>'dialog::{width:600,height:300,title:\'成本调整\'}'),
            array(
                    'label' => '导出模板',
                    'href' => 'index.php?app=dealer&ctl=admin_goods&act=exportTemplate',
                    'target' => "_blank",
            ),
        );
        $params = array(
                'title'=>'经销商商品',
                'use_buildin_set_tag'=>false,
                'use_buildin_filter'=>false,
                'use_buildin_export'=>false,
                'use_buildin_import'=>true,
                'use_buildin_recycle'=>true,
                'actions'=>$actions,
        );
        
        $this->finder('dealer_mdl_goods', $params);
    }

    /*
     * 导出模板
    */

    public function exportTemplate()
    {
        header("Content-Type: text/csv");
        
        $filename = "经销商货品模板.csv";
        $encoded_filename = urlencode($filename);
        $encoded_filename = str_replace("+", "%20", $encoded_filename);
        
        $ua = $_SERVER["HTTP_USER_AGENT"];
        if (preg_match("/MSIE/", $ua)) {
            header('Content-Disposition: attachment; filename="' . $encoded_filename . '"');
        } else if (preg_match("/Firefox/", $ua)) {
            header('Content-Disposition: attachment; filename*="utf8\'\'' . $filename . '"');
        } else {
            header('Content-Disposition: attachment; filename="' . $filename . '"');
        }
        
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');
        
        //模板
        $dealerGoodsObj = app::get('dealer')->model('goods');
        $title          = $dealerGoodsObj->exportTemplate();
        
        echo '"'.implode('","',$title).'"';
    }

    /**
     * 创建
     * @return mixed 返回值
     */
    public function create() {
        $this->display('admin/goods.html');
    }

    /**
     * 保存
     * @return mixed 返回操作结果
     */
    public function save() {
        if(empty($_POST['bs_id'])) {
            $this->splash('error', $this->url, '经销商必填');
        }
        if(empty($_POST['bm_id'])) {
            $this->splash('error', $this->url, '基础物流必填');
        }
        if(!is_numeric($_POST['cost'])) {
            $this->splash('error', $this->url, '成本必须为数值');
        }
        $this->begin('index.php?app=dealer&ctl=admin_goods&act=index');
        $data = array(
            'bs_id' => $_POST['bs_id'],
            'bm_id' => $_POST['bm_id'],
            'cost' => $_POST['cost'],
        );
        $dealerGoodsObj = app::get('dealer')->model('goods');
        $oldRow = $dealerGoodsObj->db_dump(array('bs_id'=>$data['bs_id'], 'bm_id'=>$data['bm_id']), 'id');
        if($oldRow) {
            $dealerGoodsObj->update(array('cost'=>$data['cost'], 'modify_time'=>time()), array('id'=>$oldRow['id']));
        } else {
            $data['create_time'] = time();
            $data['modify_time'] = time();
            $dealerGoodsObj->insert($data);
        }
        $this->end(true, '操作成功');
    }
}
