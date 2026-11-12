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

class dealer_ctl_admin_bbu extends desktop_controller
{

    /**
     * index
     * @return mixed 返回值
     */
    public function index()
    {
        $actions = array(
            array(
                'label'  => '添加公司业务组织',
                'href'   => 'index.php?app=dealer&ctl=admin_bbu&act=create',
                'target' => "dialog::{width:500,height:200,title:'添加公司业务组织'}",
            ),
        );
        $params = array(
            'title'               => '公司业务组织',
            'use_buildin_set_tag' => false,
            'use_buildin_filter'  => false,
            'use_buildin_export'  => false,
            'use_buildin_import'  => false,
            'use_buildin_recycle' => false,
            'actions'             => $actions,
            // 'orderBy'             => 'status asc, up_time desc',
        );

        $this->finder('dealer_mdl_bbu', $params);
    }

    /**
     * 创建
     * @return mixed 返回值
     */
    public function create()
    {
        $this->display('admin/bbu.html');
    }

    /**
     * edit
     * @param mixed $bbuId ID
     * @return mixed 返回值
     */
    public function edit($bbuId)
    {
        $row = app::get('dealer')->model('bbu')->db_dump(array('bbu_id' => $bbuId));

        $this->pagedata['bbu_id']   = $row['bbu_id'];
        $this->pagedata['bbu_name'] = $row['bbu_name'];
        $this->pagedata['bbu_code'] = $row['bbu_code'];
        $this->display('admin/bbu.html');
    }

    /**
     * 保存
     * @return mixed 返回操作结果
     */
    public function save()
    {
        $this->begin('index.php?app=dealer&ctl=admin_bbu&act=index');
        try {
            // 写实现统一收敛到 capability（与 Vue JSON 入口 act=save 同一份代码）；
            // legacy 事务由 begin()/end() 托管，故 ownTransaction = false
            (new dealer_capability_bbu())->saveBbu($_POST, false);
        } catch (InvalidArgumentException $exception) {
            $this->end(false, $exception->getMessage());
            return;
        } catch (Throwable $exception) {
            $this->end(false, '保存失败，请重试');
            return;
        }
        $this->end(true, '操作成功');
    }

    /**
     * 查看快照
     */
    public function show_history($log_id)
    {
        $logSnapshootMdl    = app::get('ome')->model('operation_log_snapshoot');
        //日志
        $log = $logSnapshootMdl->db_dump(['log_id'=>$log_id]);
        $row = json_decode($log['snapshoot'],1);

        $this->pagedata['bbu_id']   = $row['bbu_id'];
        $this->pagedata['bbu_name'] = $row['bbu_name'];
        $this->pagedata['bbu_code'] = $row['bbu_code'];
        $this->pagedata['history']  = true;
        $this->singlepage('admin/bbu.html');
    }

}
