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

class dealer_ctl_admin_bs extends desktop_controller
{

    /**
     * index
     * @return mixed 返回值
     */
    public function index()
    {
        $actions = array(
            array(
                'label'  => '添加经销商',
                'href'   => 'index.php?app=dealer&ctl=admin_bs&act=create',
                'target' => "dialog::{width:780,height:635,title:'添加经销商'}",
            ),
            array(
                'label'  => '导出模板',
                'href'   => 'index.php?app=dealer&ctl=admin_bs&act=exportTemplate',
                'target' => "_blank",
            ),
        );
        $params = array(
            'title'               => '经销商',
            'use_buildin_set_tag' => false,
            'use_buildin_filter'  => true,
            'use_buildin_export'  => false,
            'use_buildin_import'  => true,
            'use_buildin_recycle' => false,
            'actions'             => $actions,
            // 'orderBy'             => 'status asc, up_time desc',
        );

        $this->finder('dealer_mdl_bs', $params);
    }

    /**
     * 创建
     * @return mixed 返回值
     */
    public function create()
    {
        $betcMdl  = app::get('dealer')->model('betc');
        $betcList = $betcMdl->getList('*', ['status' => 'active']);

        $this->pagedata['betc_list'] = $betcList;
        $this->display('admin/bs.html');
    }

    /**
     * edit
     * @param mixed $bsId ID
     * @return mixed 返回值
     */
    public function edit($bsId)
    {
        $row    = app::get('dealer')->model('bs')->db_dump(array('bs_id' => $bsId));
        $filter = [
            'status'     => 'active',
            'filter_sql' => '( OR betc_id in (' . $row['betc_id'] . '))',
        ];
        $sql      = "SELECT * FROM sdb_dealer_betc WHERE status='active' OR betc_id in ('" . $row['betc_id'] . "')";
        $betcList = kernel::database()->select($sql);

        // betcListLeft表示可选，betcListRight表示已选
        $betcListLeft = $betcListRight = [];
        $betcIdArr    = explode(',', $row['betc_id']);
        foreach ($betcList as $k => $v) {
            if (in_array($v['betc_id'], $betcIdArr)) {
                $betcListRight[] = $v;
            } else {
                $betcListLeft[] = $v;
            }
        }

        $this->pagedata['betc_list']     = $betcListLeft;
        $this->pagedata['betc_selected'] = $betcListRight;
        $this->pagedata['bs_info']       = $row;

        $this->display('admin/bs.html');
    }

    /**
     * 保存
     * @return mixed 返回操作结果
     */
    public function save()
    {
        $bsId           = $_POST['bs_id'];
        $bsBn           = trim($_POST['bs_bn']);
        $name           = trim($_POST['name']);
        $betcIdArr      = explode(',', $_POST['betc_id']);
        $contactName    = $_POST['contact_name'] ? trim($_POST['contact_name']) : '';
        $contactMobile  = $_POST['contact_mobile'] ? trim($_POST['contact_mobile']) : '';
        $contactAddress = $_POST['contact_address'] ? trim($_POST['contact_address']) : '';
        $customerCode   = $_POST['customer_code'] ? trim($_POST['customer_code']) : '';
        $salesofficeCode = $_POST['salesoffice_code'] ? trim($_POST['salesoffice_code']) : '';
        $divisionCode   = $_POST['division_code'] ? trim($_POST['division_code']) : '';
        $salesgroupCode = $_POST['salesgroup_code'] ? trim($_POST['salesgroup_code']) : '';
        $status         = $_POST['status'] ? $_POST['status'] : 'active';

        $this->begin('index.php?app=dealer&ctl=admin_bs&act=index');
        if (!$betcIdArr || !$_POST['betc_id']) {
            // $this->end(false, '请选择所属贸易公司');
            $betcIdArr = [];
        }
        if (!$name) {
            $this->end(false, '经销商名称必填');
        }
        if (!$bsBn) {
            $this->end(false, '经销商编码必填');
        }
        if (!$customerCode) {
            $this->end(false, '经销商客户编码必填');
        }
        
        // 验证必填字段
        /*
        if (!$contactName) {
            $this->end(false, '联系人姓名必填');
        }
        if (!$contactMobile) {
            $this->end(false, '联系人电话必填');
        }
        if (!$contactAddress) {
            $this->end(false, '联系人地址必填');
        }
        */
        if (!$salesofficeCode) {
            $this->end(false, '销售办公室编码必填');
        }
        if (!$divisionCode) {
            $this->end(false, '产品组编码必填');
        }
        if (!$salesgroupCode) {
            $this->end(false, '销售组织必填');
        }
        
        // 验证编码格式：只允许英文字母、数字、下划线、中划线
        $pattern = '/^[a-zA-Z0-9\-_]*$/';
        if ($customerCode && !preg_match($pattern, $customerCode)) {
            $this->end(false, '经销商客户编码格式不正确，只允许英文字母、数字、下划线、中划线');
        }
        if ($salesofficeCode && !preg_match($pattern, $salesofficeCode)) {
            $this->end(false, '销售办公室编码格式不正确，只允许英文字母、数字、下划线、中划线');
        }
        if ($divisionCode && !preg_match($pattern, $divisionCode)) {
            $this->end(false, '产品组编码格式不正确，只允许英文字母、数字、下划线、中划线');
        }
        if ($salesgroupCode && !preg_match($pattern, $salesgroupCode)) {
            $this->end(false, '销售组织格式不正确，只允许英文字母、数字、下划线、中划线');
        }
        // if (!kernel::single('ome_func')->isMobile($contactMobile) && !kernel::single('ome_func')->isTel($contactMobile)) {
        //     $this->end(false, '联系人电话无效');
        // }

        $opInfo  = kernel::single('ome_func')->getDesktopUser();
        $cosMdl  = app::get('organization')->model('cos');
        $betcMdl = app::get('dealer')->model('betc');
        $bsMdl   = app::get('dealer')->model('bs');

        $betcCosInfo = [];
        if ($betcIdArr) {
            $betcInfo = $betcMdl->getList('*', ['betc_id|in' => $betcIdArr, 'status' => 'active']);
            if (!$betcInfo || count($betcInfo) != count($betcIdArr)) {
                $this->end(false, '贸易公司不存在或无效');
            }

            $betcCodeArr = array_column($betcInfo, 'betc_code');
            $betcCosInfo = $cosMdl->getList('*', ['cos_code|in' => $betcCodeArr, 'cos_type' => 'betc']);
            if (!$betcCosInfo || count($betcCodeArr) != count($betcCosInfo)) {
                $this->end(false, '贸易公司组织架构异常');
            }
        }

        $filter = [
            'bs_bn' => $bsBn,
            // 'bs_id|noequal' => $bsId,
        ];
        $bsList = $bsMdl->getList('*', $filter);
        if ($bsList) {
            if (count($bsList) > 1 || $bsList[0]['bs_id'] != $bsId) {
                $this->end(false, '经销商编码已被使用');
            }
        }

        $data = [
            'bs_bn'           => $bsBn,
            'name'            => $name,
            'status'          => $status,
            'op_name'         => $opInfo['op_name'],
            'betc_id'         => implode(',', $betcIdArr),
            'contact_address' => $contactAddress,
            'contact_mobile'  => $contactMobile,
            'contact_name'    => $contactName,
            'customer_code'   => $customerCode,
            'salesoffice_code' => $salesofficeCode,
            'division_code'   => $divisionCode,
            'salesgroup_code' => $salesgroupCode,
            // 'cos_id'          => '',
            'deal_cost'       => '', // 老表字段（订单处理费公式），必填，所以给默认值
        ];

        $snapshoot = [];
        if ($bsId) {
            unset($data['bs_bn']); // 编码不允许编辑
            
            // 检查状态变更，只有关闭经销商时才调用状态更新方法
            $oldStatus = $bsList[0]['status'];
            if ($oldStatus != $status && $status === 'close') {
                // 调用状态更新方法
                $storeLib = kernel::single('o2o_store');
                $errorMsg = '';
                $updateResult = $storeLib->updateBsStoreStatus($status, $bsList[0]['bs_bn'], $errorMsg);
                if (!$updateResult) {
                    $this->end(false, $errorMsg ?: '更新经销商状态失败');
                }
            }
            
            $bsMdl->update($data, ['bs_id' => $bsId]);
            $data['bs_id'] = $bsId;
            $data['bs_bn'] = $bsList[0]['bs_bn'];
            $logInfo       = '更新经销商。';
            $operation     = 'dealer_bs_edit@dealer';
            $snapshoot     = $bsList[0];
        } else {
            $data['create_time'] = time();
            $data['modify_time'] = time();
            $bsMdl->insert($data);
            $logInfo   = '添加经销商。';
            $operation = 'dealer_bs_add@dealer';
        }

        // 创建/更新 企业组织表,得到返回再去更新经销商表
        $cosData = [
            'cos_type'  => 'bs',
            'cos_code'  => $data['bs_bn'],
            'cos_name'  => $data['name'],
            'op_name'   => $data['op_name'],
            'parent_id' => 2,
            'is_leaf'   => '1',
        ];
        $cosId = kernel::single('organization_cos')->saveCos($cosData);
        if ($cosId) {
            $bsMdl->update(['cos_id' => $cosId], ['bs_id' => $data['bs_id']]);

            // 同步更新organization表中的经销商名称（如果存在的话）
            $organizationMdl = app::get('organization')->model('organization');
            $dealerOrg = $organizationMdl->dump(['org_no' => 'BS_' . $data['bs_bn'], 'org_type' => 3]);
            if ($dealerOrg) {
                $organizationMdl->update([
                    'org_name' => $data['name']
                ], ['org_id' => $dealerOrg['org_id']]);
            }

            // 更新贸易公司的out_bind_id
            $betcList = ['cosIdArr' => array_column($betcCosInfo, 'cos_id')];
            kernel::single('organization_cos')->upBetcOutBindId($cosId, $betcList);
        }

        $omeLogMdl = app::get('ome')->model('operation_log');
        $log_id    = $omeLogMdl->write_log($operation, $data['bs_id'], $logInfo);
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

        $filename         = "经销商模板.csv";
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
        $bsMdl = app::get('dealer')->model('bs');
        $title = $bsMdl->exportTemplate();

        echo '"' . implode('","', $title) . '"';
    }

    /**
     * 查看快照
     */
    public function show_history($log_id)
    {
        $logSnapshootMdl = app::get('ome')->model('operation_log_snapshoot');
        $log             = $logSnapshootMdl->db_dump(['log_id' => $log_id]);
        $row             = json_decode($log['snapshoot'], 1);

        $filter = [
            'status'     => 'active',
            'filter_sql' => '( OR betc_id in (' . $row['betc_id'] . '))',
        ];
        $sql      = "SELECT * FROM sdb_dealer_betc WHERE status='active' OR betc_id in ('" . $row['betc_id'] . "')";
        $betcList = kernel::database()->select($sql);

        // betcListLeft表示可选，betcListRight表示已选
        $betcListLeft = $betcListRight = [];
        $betcIdArr    = explode(',', $row['betc_id']);
        foreach ($betcList as $k => $v) {
            if (in_array($v['betc_id'], $betcIdArr)) {
                $betcListRight[] = $v;
            } else {
                $betcListLeft[] = $v;
            }
        }

        $this->pagedata['betc_list']     = $betcListLeft;
        $this->pagedata['betc_selected'] = $betcListRight;
        $this->pagedata['bs_info']       = $row;
        $this->pagedata['history']       = true;

        $this->singlepage('admin/bs.html');
    }

}
