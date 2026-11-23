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
 * @DateTime: 2020/9/4 14:55:21
 * @describe: 经销商管理
 * ============================
 */
class dealer_ctl_admin_business extends desktop_controller {

    public function index() {
        $actions = array(
                array('label'=>'添加','href'=>'index.php?app=dealer&ctl=admin_business&act=create','target'=>'_blank'),
         );
        $params = array(
                'title'=>'经销商管理',
                'use_buildin_set_tag'=>false,
                'use_buildin_filter'=>false,
                'use_buildin_export'=>false,
                'use_buildin_import'=>false,
                'use_buildin_recycle'=>true,
                'actions'=>$actions,
        );

        $this->finder('dealer_mdl_business', $params);
    }

    public function create() {
        $this->singlepage('admin/business.html');
    }

    public function edit($bs_id) {
        $row = app::get('dealer')->model('business')->db_dump(array('bs_id'=>$bs_id));
        $branch_id = app::get('dealer')->model('business_branch')->getList('branch_id', array('bs_id'=>$bs_id));
        $this->pagedata['row'] = $row;
        $this->pagedata['branch_id'] = array_map('current', $branch_id);
        $this->singlepage('admin/business.html');
    }

    public function save() {
        $bs_id = (int) $_POST['bs_id'];
        $bs_bn = (string) $_POST['bs_bn'];
        $name = (string) $_POST['name'];
        $dealCost = (string) $_POST['deal_cost'];
        $dealCost = $dealCost ? $dealCost :'0';
        $branch_id = $_POST['branch_id'];
        $this->begin('index.php?app=dealer&ctl=admin_business&act=index');
        if(!$bs_bn || !$name || empty($branch_id)) {
            $this->end(false, '必填项未填');
        }
        $businessObj = app::get('dealer')->model('business');
        if($businessObj->db_dump(array('bs_bn'=>$bs_bn, 'bs_id|noequal'=>$bs_id), 'bs_id')) {
            $this->end(false, '经销商编码已经被使用');
        }
        if($dealCost) {
            if(utils::cal_bs_fee($dealCost,1000,1,1) <= 0) {
                $this->end(false, '订单处理费公式输入错误');
            }
        }
        $upData = array(
            'bs_bn' => $bs_bn,
            'name' => $name,
            'deal_cost' => $dealCost,
            'modify_time' => time()
        );
        if($bs_id) {
            $businessObj->update($upData, array('bs_id' => $bs_id));
        } else {
            $upData['create_time'] = time();
            $businessObj->insert($upData);
            $bs_id = $upData['bs_id'];
        }
        $bbObj = app::get('dealer')->model('business_branch');
        $bbObj->delete(array('bs_id'=>$bs_id));
        foreach ($branch_id as $v) {
            $saveData = array(
                'bs_id' => $bs_id,
                'branch_id' => $v
            );
            $bb = $bbObj->db_dump(array('branch_id'=>$v), 'bs_id');
            if($bb && $bb['bs_id'] != $bs_id) {
                $branch = app::get('ome')->model('branch')->db_dump(array('branch_id'=>$v), 'branch_bn');
                $bs = $businessObj->db_dump(array('bs_id'=>$bb['bs_id']), 'bs_bn');
                $this->end(false, '该仓库:'.$branch['branch_bn'].' 已经存在经销商:'.$bs['bs_bn'].' 了');
            } elseif (!$bb) {
                $bbObj->insert($saveData);
            }
        }
        $this->end(true, '操作成功');
    }

    public function checkExp() {
        $this->pagedata['expressions'] = $_GET['expvalue'];
        $this->display('admin/check_exp.html');
    }
}
