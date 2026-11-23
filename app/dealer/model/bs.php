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
 * 经销商
 */

class dealer_mdl_bs extends dbeav_model
{
    private $import_data    = [];
    private $import_bs_code = [];

    private $templateColumn = array(
        '*:经销商编码' => 'bs_bn',
        '*:经销商名称' => 'name',
        '*:经销商客户编码' => 'customer_code',
        '*:销售办公室编码' => 'salesoffice_code',
        '*:产品组编码' => 'division_code',
        '*:销售组织' => 'salesgroup_code',
        '*:联系人姓名' => 'contact_name',
        '*:联系人手机' => 'contact_mobile',
        '*:联系人地址' => 'contact_address',
        '贸易公司编码' => 'betc_code',
    );

    public function table_name($real = false)
    {
        if ($real) {
            $table_name = 'sdb_dealer_business';
        } else {
            $table_name = 'business';
        }
        return $table_name;
    }

    public function object_name()
    {
        return 'bs';
    }

    // public function searchOptions()
    // {
    //     return array(
    //         'bs_code' => '经销商编码',
    //     );
    // }

    public function get_schema()
    {
        $schema = [
            'columns'         => array(
                'bs_id'           => array(
                    'type'     => 'mediumint(8)',
                    'required' => true,
                    'pkey'     => true,
                    'extra'    => 'auto_increment',
                    'label'    => '经销商ID',
                ),
                'bs_bn'           => array(
                    'type'            => 'varchar(32)',
                    'required'        => true,
                    'label'           => '经销商编号',
                    'in_list'         => true,
                    'default_in_list' => true,
                    'searchtype'      => 'nequal',
                    // 'filtertype'      => 'normal',
                    // 'filterdefault'   => false,
                    'order'           => 10,
                ),
                'name'            => array(
                    'type'            => 'varchar(255)',
                    'required'        => true,
                    'label'           => '经销商名称',
                    'editable'        => false,
                    'is_title'        => true,
                    'in_list'         => true,
                    'default_in_list' => true,
                    'order'           => 20,
                ),
                'status'          => array(
                    'type'            => "enum('active', 'close')",
                    'label'           => '状态',
                    'default'         => 'active',
                    'in_list'         => false,
                    'default_in_list' => false,
                    'editable'        => false,
                    'order'           => 40,
                ),
                'create_time'     => array(
                    'type'            => 'time',
                    'label'           => '创建时间',
                    'editable'        => false,
                    'in_list'         => false,
                    'default_in_list' => false,
                ),
                'modify_time'     => array(
                    'type'            => 'time',
                    'label'           => '修改时间',
                    'editable'        => false,
                    'in_list'         => false,
                    'default_in_list' => false,
                ),
                // 一件代发扩展字段
                'op_name'         => array(
                    'type'            => 'varchar(32)',
                    'label'           => '创建者',
                    'editable'        => false,
                    'is_title'        => false,
                    'in_list'         => false,
                    'default_in_list' => false,
                    'order'           => 70,
                ),
                'betc_id'         => array(
                    'type'            => 'varchar(255)',
                    'label'           => '贸易公司ID集合，示例：1,2,3',
                    'editable'        => false,
                    'is_title'        => false,
                    'in_list'         => false,
                    'default_in_list' => false,
                    'filtertype'      => 'normal',
                    'filterdefault'   => true,
                ),
                'cos_id'          => array(
                    'type'            => 'varchar(255)',
                    'label'           => '组织架构ID集合，示例：1,2,3',
                    'editable'        => false,
                    'is_title'        => false,
                    'in_list'         => false,
                    'default_in_list' => false,
                ),
                'contact_address' => array(
                    'type'            => 'varchar(255)',
                    'label'           => '联系人地址',
                    'editable'        => false,
                    'is_title'        => false,
                    'in_list'         => false,
                    'default_in_list' => false,
                ),
                'contact_mobile'  => array(
                    'type'            => 'varchar(30)',
                    'label'           => '联系人手机',
                    'editable'        => false,
                    'is_title'        => false,
                    'in_list'         => false,
                    'default_in_list' => false,
                ),
                'contact_name'    => array(
                    'type'            => 'varchar(30)',
                    'label'           => '联系人姓名',
                    'editable'        => false,
                    'is_title'        => false,
                    'in_list'         => false,
                    'default_in_list' => false,
                ),
                'customer_code'   => array(
                    'type'            => 'varchar(32)',
                    'label'           => '经销商客户编码',
                    'editable'        => false,
                    'is_title'        => false,
                    'in_list'         => false,
                    'default_in_list' => false,
                ),
                'salesoffice_code' => array(
                    'type'            => 'varchar(32)',
                    'label'           => '销售办公室编码',
                    'editable'        => false,
                    'is_title'        => false,
                    'in_list'         => false,
                    'default_in_list' => false,
                ),
                'division_code'   => array(
                    'type'            => 'varchar(32)',
                    'label'           => '产品组编码',
                    'editable'        => false,
                    'is_title'        => false,
                    'in_list'         => false,
                    'default_in_list' => false,
                ),
                'salesgroup_code' => array(
                    'type'            => 'varchar(32)',
                    'label'           => '销售组织',
                    'editable'        => false,
                    'is_title'        => false,
                    'in_list'         => false,
                    'default_in_list' => false,
                ),
                'at_time'         => array(
                    'type'            => 'TIMESTAMP',
                    'label'           => '创建时间',
                    'default'         => 'CURRENT_TIMESTAMP',
                    'width'           => 150,
                    'in_list'         => false,
                    'default_in_list' => false,
                    'order'           => 50,
                ),
                'up_time'         => array(
                    'type'            => 'TIMESTAMP',
                    'label'           => '更新时间',
                    'default'         => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
                    'width'           => 150,
                    'in_list'         => false,
                    'default_in_list' => false,
                    'order'           => 60,
                ),
            ),
            'idColumn'        => 'bs_id',
            'in_list'         => [
                'bs_bn',
                'name',
                'op_name',
                'at_time',
                'up_time',
                'customer_code',
                'salesoffice_code',
                'division_code',
                'salesgroup_code',
            ],
            'default_in_list' => [
                'bs_bn',
                'name',
                'op_name',
                'at_time',
                'up_time',
            ],
        ];
        return $schema;
    }

    public function _filter($filter, $tableAlias = null, $baseWhere = null)
    {
        $where = ' 1 ';
        if ($filter['betc_id']) {
            $where .= " AND FIND_IN_SET(" . $filter['betc_id'] . ", betc_id)";
            unset($filter['betc_id']);
        }
        return parent::_filter($filter, $tableAlias, $baseWhere) . " AND " . $where;
    }

    /*
     * 导出模板
     */
    public function exportTemplate()
    {
        $title = [];
        foreach ($this->io_title() as $v) {
            $title[] = kernel::single('base_charset')->utf2local($v);
        }

        return $title;
    }

    public function io_title($ioType = 'csv')
    {
        $this->oSchema['csv'] = $this->templateColumn;

        $this->ioTitle[$ioType] = array_keys($this->oSchema[$ioType]);

        return $this->ioTitle[$ioType];
    }

    /**
     * CSV导入
     */
    public function prepared_import_csv()
    {
        $this->ioObj->cacheTime = time();
    }

    public function prepared_import_csv_obj($data, $mark, $tmpl, &$msg = '')
    {
        return null;
    }

    //CSV导入业务处理
    public function prepared_import_csv_row($row, $title, &$tmpl, &$mark, &$newObjFlag, &$msg)
    {
        if (empty($row)) {
            return true;
        }
        $mark = false;
        if (substr($row[0], 0, 1) == '*') {
            $title = array_flip($row);
            $mark  = 'title';
            foreach ($this->templateColumn as $k => $val) {
                if (!isset($title[$k])) {
                    $msg['error'] = '请使用正确的模板';
                    return false;
                }
            }
            return $title;
        }
        if (empty($title)) {
            $msg['error'] = "请使用正确的模板格式！";
            return false;
        }
        $arrData = array();
        foreach ($this->templateColumn as $k => $val) {
            $arrData[$val] = trim($row[$title[$k]]);
        }
        if (count($this->import_data) > 10000) {
            $msg['error'] = "导入的数据量过大，请减少到10000条以下！";
            return false;
        }
        if (!$arrData['bs_bn']) {
            $msg['error'] = "经销商编码必须填写";
            return false;
        }
        if (!$arrData['name']) {
            $msg['error'] = "经销商名称必须填写";
            return false;
        }
        if (!$arrData['customer_code']) {
            $msg['error'] = "经销商客户编码必须填写";
            return false;
        }
        if (!$arrData['salesoffice_code']) {
            $msg['error'] = "销售办公室编码必须填写";
            return false;
        }
        if (!$arrData['division_code']) {
            $msg['error'] = "产品组编码必须填写";
            return false;
        }
        if (!$arrData['salesgroup_code']) {
            $msg['error'] = "销售组织必须填写";
            return false;
        }
        /*
        if (!$arrData['contact_name']) {
            $msg['error'] = "联系人姓名必须填写";
            return false;
        }
        if (!$arrData['contact_mobile']) {
            $msg['error'] = "联系人手机必须填写";
            return false;
        }
        if (!$arrData['contact_address']) {
            $msg['error'] = "联系人地址必须填写";
            return false;
        }
        */

        // 验证编码格式：只允许英文字母、数字、下划线、中划线
        $pattern = '/^[a-zA-Z0-9\-_]*$/';
        if ($arrData['bs_bn'] && !preg_match($pattern, $arrData['bs_bn'])) {
            $msg['error'] = '经销商编码格式不正确，只允许英文字母、数字、下划线、中划线';
            return false;
        }
        if ($arrData['customer_code'] && !preg_match($pattern, $arrData['customer_code'])) {
            $msg['error'] = '经销商客户编码格式不正确，只允许英文字母、数字、下划线、中划线';
            return false;
        }
        if ($arrData['salesoffice_code'] && !preg_match($pattern, $arrData['salesoffice_code'])) {
            $msg['error'] = '销售办公室编码格式不正确，只允许英文字母、数字、下划线、中划线';
            return false;
        }
        if ($arrData['division_code'] && !preg_match($pattern, $arrData['division_code'])) {
            $msg['error'] = '产品组编码格式不正确，只允许英文字母、数字、下划线、中划线';
            return false;
        }
        if ($arrData['salesgroup_code'] && !preg_match($pattern, $arrData['salesgroup_code'])) {
            $msg['error'] = '销售组织格式不正确，只允许英文字母、数字、下划线、中划线';
            return false;
        }

        $bsMdl = app::get('dealer')->model('bs');
        $betcMdl = app::get('dealer')->model('betc');
        $cosMdl = app::get('organization')->model('cos');

        // 检测贸易公司（如果提供）
        $betcIdArr = [];
        if (!empty($arrData['betc_code'])) {
            $betcInfo = $betcMdl->db_dump(['betc_code' => $arrData['betc_code'], 'status' => 'active']);
            if (!$betcInfo) {
                $msg['error'] = '贸易公司编码' . $arrData['betc_code'] . '不存在或无效!';
                return false;
            }
            $betcIdArr[] = $betcInfo['betc_id'];
        }

        if (isset($this->import_bs_code[$arrData['bs_bn']])) {
            $msg['error'] = '经销商编码' . $arrData['bs_bn'] . '重复!';
            return false;
        }
        $this->import_bs_code[$arrData['bs_bn']] = $arrData['bs_bn'];

        // 检查经销商编码是否已存在，但不阻止导入（支持更新）
        $bsInfo = $bsMdl->db_dump(['bs_bn' => $arrData['bs_bn']]);
        if ($bsInfo) {
            // 标记为更新操作
            $arrData['is_update'] = true;
            $arrData['existing_bs_id'] = $bsInfo['bs_id'];
        } else {
            $arrData['is_update'] = false;
        }

        $sdfRow = [
            'bs_bn'           => $arrData['bs_bn'],
            'name'            => $arrData['name'],
            'status'          => 'active',
            'betc_id'         => implode(',', $betcIdArr),
            'contact_address' => $arrData['contact_address']?trim($arrData['contact_address']): '',
            'contact_mobile'  => $arrData['contact_mobile']?trim($arrData['contact_mobile']): '',
            'contact_name'    => $arrData['contact_name']?trim($arrData['contact_name']): '',
            'customer_code'   => $arrData['customer_code'],
            'salesoffice_code' => $arrData['salesoffice_code'],
            'division_code'   => $arrData['division_code'],
            'salesgroup_code' => $arrData['salesgroup_code'],
            'cos_id'          => '',
            'deal_cost'       => '', // 老表字段（订单处理费公式），必填，所以给默认值
            'is_update'       => $arrData['is_update'],
            'existing_bs_id'  => $arrData['existing_bs_id'] ?? null,
        ];
        //组织数据
        $this->import_data[] = $sdfRow;

        return null;
    }

    public function finish_import_csv()
    {
        $oQueue = app::get('base')->model('queue');

        $aP   = $this->import_data;
        $pSdf = array();

        $count     = 0;
        $limit     = 50;
        $page      = 0;
        $orderSdfs = array();

        foreach ($aP as $k => $aPi) {
            if ($count < $limit) {
                $count++;
            } else {
                $count = 0;
                $page++;
            }
            $pSdf[$page][] = $aPi;
        }

        foreach ($pSdf as $v) {
            // $queueData = array(
            //     'queue_title' => '经销商导入',
            //     'start_time'  => time(),
            //     'params'      => array(
            //         'sdfdata' => $v,
            //     ),
            //     'worker'      => 'dealer_mdl_bs.import_run',
            // );
            // $oQueue->save($queueData);
            $this->import_run($cursor_id, ['sdfdata' => $v]);
        }
        $oQueue->flush();

        return null;
    }

    public function import_run(&$cursor_id, $params)
    {
        $opInfo  = kernel::single('ome_func')->getDesktopUser();
        $sdfdata = $params['sdfdata'];
        $omeLogMdl = app::get('ome')->model('operation_log');
        
        foreach ($sdfdata as $data) {
            try {
                $data['op_name'] = $opInfo['op_name'];
                $data['modify_time'] = time();
                
                // 共同字段数据（更新和新增都需要的字段）
                $commonFields = [
                    'name' => $data['name'],
                    'betc_id' => $data['betc_id'],
                    'contact_address' => $data['contact_address'],
                    'contact_mobile' => $data['contact_mobile'],
                    'contact_name' => $data['contact_name'],
                    'customer_code' => $data['customer_code'],
                    'salesoffice_code' => $data['salesoffice_code'],
                    'division_code' => $data['division_code'],
                    'salesgroup_code' => $data['salesgroup_code'],
                    'op_name' => $data['op_name'],
                    'modify_time' => $data['modify_time'],
                ];
                
                if ($data['is_update']) {
                    // 获取更新前的数据作为快照
                    $existingData = $this->db_dump(['bs_id' => $data['existing_bs_id']]);
                    
                    // 更新现有经销商记录
                    $result = $this->update($commonFields, ['bs_id' => $data['existing_bs_id']]);
                    
                    $bsId = $data['existing_bs_id'];
                    $operation = 'dealer_bs_edit@dealer';
                    $logInfo = '通过导入更新经销商';
                    $snapshoot = $existingData; // 保存更新前的数据
                    
                    if ($result) {
                        // 更新企业组织表记录
                        $existingBs = $this->db_dump(['bs_id' => $bsId], 'cos_id');
                        if ($existingBs && $existingBs['cos_id']) {
                            $cosMdl = app::get('organization')->model('cos');
                            $cosMdl->update([
                                'cos_name' => $data['name'],
                                'op_name' => $data['op_name'],
                            ], ['cos_id' => $existingBs['cos_id']]);
                            
                            // 同步更新organization表中的经销商名称
                            $organizationMdl = app::get('organization')->model('organization');
                            $dealerOrg = $organizationMdl->dump(['org_no' => 'BS_' . $data['bs_bn'], 'org_type' => 3]);
                            if ($dealerOrg) {
                                $organizationMdl->update([
                                    'org_name' => $data['name']
                                ], ['org_id' => $dealerOrg['org_id']]);
                            }
                        }
                    }
                } else {
                    // 新增经销商记录
                    $insertFields = array_merge($commonFields, [
                        'bs_bn' => $data['bs_bn'],
                        'status' => 'active',
                        'create_time' => time(),
                        'deal_cost' => '', // 老表字段（订单处理费公式），必填，所以给默认值
                    ]);
                    
                    $bsId = $this->insert($insertFields);
                    
                    $operation = 'dealer_bs_add@dealer';
                    $logInfo = '通过导入添加经销商';
                    $snapshoot = null; // 新增时不需要快照
                    
                    if ($bsId) {
                        // 创建企业组织表记录
                        $cosData = [
                            'cos_type' => 'bs',
                            'cos_code' => $data['bs_bn'],
                            'cos_name' => $data['name'],
                            'op_name' => $data['op_name'],
                            'parent_id' => 2,
                            'is_leaf' => '1',
                        ];
                        
                        $cosId = kernel::single('organization_cos')->saveCos($cosData);
                        if ($cosId) {
                            $this->update(['cos_id' => $cosId], ['bs_id' => $bsId]);
                            
                            // 同步更新organization表中的经销商名称
                            $organizationMdl = app::get('organization')->model('organization');
                            $dealerOrg = $organizationMdl->dump(['org_no' => 'BS_' . $data['bs_bn'], 'org_type' => 3]);
                            if ($dealerOrg) {
                                $organizationMdl->update([
                                    'org_name' => $data['name']
                                ], ['org_id' => $dealerOrg['org_id']]);
                            }
                            
                            // 如果有贸易公司，更新贸易公司的out_bind_id
                            if (!empty($data['betc_id'])) {
                                $betcList = ['cosIdArr' => [$cosId]];
                                kernel::single('organization_cos')->upBetcOutBindId($cosId, $betcList);
                            }
                        }
                    }
                }
                
                // 记录操作日志和快照
                if ($bsId) {
                    $log_id = $omeLogMdl->write_log($operation, $bsId, $logInfo);
                    if ($log_id && $snapshoot) { // 只有更新时才记录快照
                        $shootMdl = app::get('ome')->model('operation_log_snapshoot');
                        $snapshoot = json_encode($snapshoot, JSON_UNESCAPED_UNICODE);
                        $tmp = ['log_id' => $log_id, 'snapshoot' => $snapshoot];
                        $shootMdl->insert($tmp);
                    }
                }
                
            } catch (Exception $e) {
                error_log("经销商导入失败: " . $e->getMessage());
            }
        }
        return false;
    }
}
