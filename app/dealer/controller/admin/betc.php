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

class dealer_ctl_admin_betc extends desktop_controller
{

    public function index()
    {
        $actions = array(
            array(
                'label'  => '添加贸易公司',
                'href'   => 'index.php?app=dealer&ctl=admin_betc&act=create',
                'target' => "dialog::{width:500,height:360,title:'添加贸易公司'}",
            ),
            array(
                'label'  => '导出模板',
                'href'   => 'index.php?app=dealer&ctl=admin_betc&act=exportTemplate',
                'target' => "_blank",
            ),
        );
        $params = array(
            'title'               => '业务贸易公司',
            'use_buildin_set_tag' => false,
            'use_buildin_filter'  => true,
            'use_buildin_export'  => false,
            'use_buildin_import'  => true,
            'use_buildin_recycle' => false,
            'actions'             => $actions,
            // 'orderBy'             => 'status asc, up_time desc',
        );

        $this->finder('dealer_mdl_betc', $params);
    }

    public function create()
    {
        $bbuMdl                     = app::get('dealer')->model('bbu');
        $bbuList                    = $bbuMdl->getList('*', ['status' => 'active']);
        $this->pagedata['bbu_list'] = $bbuList;
        $this->display('admin/betc.html');
    }

    public function edit($betcId)
    {
        $row     = app::get('dealer')->model('betc')->db_dump(array('betc_id' => $betcId));
        $bbuMdl  = app::get('dealer')->model('bbu');
        $bbuList = $bbuMdl->getList('*', ['status' => 'active']);

        $this->pagedata['bbu_list']  = $bbuList;
        $this->pagedata['betc_info'] = $row;
        $this->display('admin/betc.html');
    }

    public function save()
    {
        $betcId         = $_POST['betc_id'];
        $bbuId          = $_POST['bbu_id'];
        $betcCode       = trim($_POST['betc_code']);
        $betcName       = trim($_POST['betc_name']);
        $contactName    = $_POST['contact_name'] ? trim($_POST['contact_name']) : '';
        $contactMobile  = $_POST['contact_mobile'] ? trim($_POST['contact_mobile']) : '';
        $contactAddress = $_POST['contact_address'] ? trim($_POST['contact_address']) : '';

        $this->begin('index.php?app=dealer&ctl=admin_betc&act=index');
        if (!$bbuId) {
            $this->end(false, '请选择销售团队');
        }
        if (!$betcName) {
            $this->end(false, '贸易公司名称必填');
        }
        if (!$betcCode) {
            $this->end(false, '贸易公司编码必填');
        }
        // if (!kernel::single('ome_func')->isMobile($contactMobile) && !kernel::single('ome_func')->isTel($contactMobile)) {
        //     $this->end(false, '联系人电话无效');
        // }

        $opInfo  = kernel::single('ome_func')->getDesktopUser();
        $cosMdl  = app::get('organization')->model('cos');
        $bbuMdl  = app::get('dealer')->model('bbu');
        $betcMdl = app::get('dealer')->model('betc');

        $bbuInfo = $bbuMdl->db_dump(['bbu_id' => $bbuId]);
        if (!$bbuInfo || $bbuInfo['status'] == 'close') {
            $this->end(false, '销售团队不存在或无效');
        }

        $bbuCosInfo = $cosMdl->db_dump(['cos_code' => $bbuInfo['bbu_code'], 'cos_type' => 'bbu']);
        if (!$bbuCosInfo) {
            $this->end(false, '销售团队组织架构异常');
        }

        $filter = [
            'betc_code' => $betcCode,
            // 'betc_id|noequal' => $betcId,
        ];
        $betcList = $betcMdl->getList('*', $filter);
        if ($betcList) {
            if (count($betcList) > 1 || $betcList[0]['betc_id'] != $betcId) {
                $this->end(false, '贸易公司编码已被使用');
            }
        }

        $data = [
            'betc_name'       => $betcName,
            'betc_code'       => $betcCode,
            'status'          => 'active',
            'op_name'         => $opInfo['op_name'],
            'bbu_id'          => $bbuId,
            'contact_address' => $contactAddress,
            'contact_mobile'  => $contactMobile,
            'contact_name'    => $contactName,
            // 'cos_id'          => '',
        ];

        $snapshoot = [];
        if ($betcId) {
            unset($data['betc_code']); // 编码不允许编辑
            $betcMdl->update($data, ['betc_id' => $betcId]);
            $data['betc_id']   = $betcId;
            $data['betc_code'] = $betcList[0]['betc_code'];
            $logInfo           = '编辑贸易公司。';
            $operation         = 'dealer_betc_edit@dealer';
            $snapshoot         = $betcList[0];
        } else {
            $betcMdl->insert($data);
            $logInfo   = '添加贸易公司。';
            $operation = 'dealer_betc_add@dealer';
        }

        // 创建/更新 企业组织表,得到返回再去更新贸易公司表
        $cosData = [
            'cos_type'  => 'betc',
            'cos_code'  => $data['betc_code'],
            'cos_name'  => $data['betc_name'],
            'op_name'   => $data['op_name'],
            'parent_id' => $bbuCosInfo['cos_id'],
            'is_leaf'   => '1',
        ];
        $cosId = kernel::single('organization_cos')->saveCos($cosData);
        $cosId && $betcMdl->update(['cos_id' => $cosId], ['betc_id' => $data['betc_id']]);

        $omeLogMdl = app::get('ome')->model('operation_log');
        $log_id    = $omeLogMdl->write_log($operation, $data['betc_id'], $logInfo);
        if ($log_id && $snapshoot) {
            $shootMdl  = app::get('ome')->model('operation_log_snapshoot');
            $snapshoot = json_encode($snapshoot, JSON_UNESCAPED_UNICODE);
            $tmp       = ['log_id' => $log_id, 'snapshoot' => $snapshoot];
            $shootMdl->insert($tmp);
        }
        $this->end(true, '操作成功');
    }

    /*
     * 导出模板
     */
    public function exportTemplate()
    {
        header("Content-Type: text/csv");

        $filename         = "贸易公司模板.csv";
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
        $betcMdl = app::get('dealer')->model('betc');
        $title   = $betcMdl->exportTemplate();

        echo '"' . implode('","', $title) . '"';
    }

    /**
     * 查看快照
     */
    public function show_history($log_id)
    {
        $bbuMdl  = app::get('dealer')->model('bbu');
        $bbuList = $bbuMdl->getList('*', ['status' => 'active']);
        //日志
        $logSnapshootMdl = app::get('ome')->model('operation_log_snapshoot');
        $log             = $logSnapshootMdl->db_dump(['log_id' => $log_id]);
        $row             = json_decode($log['snapshoot'], 1);

        $this->pagedata['bbu_list']  = $bbuList;
        $this->pagedata['betc_info'] = $row;
        $this->pagedata['history']   = true;
        $this->singlepage('admin/betc.html');
    }

}
