<?php

class machinecenter_event_receive_sn extends machinecenter_event_response
{
    // 必填字段定义
    private $requiredFields = [
        'sn' => '序列号',
    ];

    /**
     * 检查参数是否完整
     * @param array $data     待检查的数据
     * @param string $err_msg 错误信息
     * @return bool
     */
    private function checkParams($data, &$err_msg = '')
    {
        $missingFields = [];

        // 检查必填字段
        foreach ($this->requiredFields as $field => $label) {
            if (!isset($data[$field]) || $data[$field] === '') {
                $missingFields[] = $label;
            }
        }

        if (!empty($missingFields)) {
            $err_msg = '缺少必填字段: ' . implode(', ', $missingFields);
            return false;
        }

//        // 检查字段类型
//        if (!is_numeric($data['bm_id'])) {
//            $err_msg = '基础物料ID必须为数字';
//            return false;
//        }
//
//        if (!is_numeric($data['branch_id'])) {
//            $err_msg = '仓库ID必须为数字';
//            return false;
//        }
//
//        if (!is_numeric($data['original_id'])) {
//            $err_msg = '来源ID必须为数字';
//            return false;
//        }

        return true;
    }

    /**
     * 添加或更新SN信息
     *
     * 该方法首先验证传入的数据，然后根据数据中是否包含有效的SN号来决定是插入新记录还是更新现有记录
     * 同时，它还记录了与SN相关的流程信息，以确保可以追踪SN的变更历史
     *
     * @param array $data 包含SN信息及其相关数据的数组
     * @return array 插入或更新操作的结果信息
     */
    public function add($data)
    {
        $checkRes = $this->checkParams($data, $err_msg);
        if (!$checkRes) {
            return $this->send_error($err_msg);
        }

        // 兼容 MySQL utf8(mb3)：姓名/昵称等字段可能含表情符等 4 字节 UTF-8
        $data = $this->_sanitizeMb3TextFields($data);

        $snMdl      = app::get('machinecenter')->model('material_sn');
        $snFlowMdl  = app::get('machinecenter')->model('material_sn_flow');
        // 注释掉解密方法，当前项目不需要
        // $data       = kernel::single('ome_security_hash')->getOriginTextList($data);
        $insertData = $this->_buildSnInsertData($data);
        if ($snInfo = $snMdl->db_dump(['sn' => $data['sn']], 'id')) {
            $insertData['id'] = $snInfo['id'];
        }
        $res = $snMdl->save($insertData);
        if ($res) {
            $this->_saveSnFlow($snFlowMdl, $data, $insertData['id']);
            return $this->send_succ('SN 保存成功');
        }
        return $this->send_error('SN 保存失败');
    }

    /**
     * 需兼容 utf8(mb3) 的文本字段（可能含表情符）
     * @var string[]
     */
    private $mb3TextFields = [
        'buyer_nickname',
        'buyer_name',
        'buyer_address',
        'ship_name',
        'ship_address',
        'ship_province',
        'ship_city',
        'ship_district',
        'ship_town',
        'ship_village',
        'user_name',
        'user_address',
        'user_province',
        'user_city',
        'user_district',
        'user_town',
        'user_village',
    ];

    /**
     * 过滤 MySQL utf8(mb3) 无法存储的 4 字节 UTF-8（如 emoji）
     * @param array $data
     * @return array
     */
    private function _sanitizeMb3TextFields($data)
    {
        if (!is_array($data)) {
            return $data;
        }
        foreach ($this->mb3TextFields as $field) {
            if (!isset($data[$field]) || !is_string($data[$field]) || $data[$field] === '') {
                continue;
            }
            $data[$field] = $this->_strip4ByteUtf8($data[$field]);
        }
        return $data;
    }

    /**
     * 移除 4 字节 UTF-8 字符（U+10000 及以上）
     * @param string $str
     * @return string
     */
    private function _strip4ByteUtf8($str)
    {
        $filtered = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $str);
        return $filtered === null ? $str : $filtered;
    }

    private function _buildSnInsertData($data)
    {
        $insertData = [
            'sn'                => $data['sn'],
            'qa_expire_item'    => !empty($data['qa_expire_item']) ? $data['qa_expire_item'] : 0,
            'platform_order_bn' => $data['platform_order_bn'],
            'order_bn'          => $data['order_bn'] ?? '',
            'original_id'       => $data['original_id'] ?: 0,
            'original_bn'       => $data['original_bn'] ?? '',
            'original_type'     => $data['original_type'],
            'shop_id'           => $data['shop_id'],
            'shop_bn'           => $data['shop_bn'],
            'source'            => $data['source'] ?? 'local',
            'bill_bn'           => $data['bill_bn'] ?? '',
            // 购买人信息
            'buyer_open_id'     => $data['buyer_open_id'],
            'buyer_nickname'    => $data['buyer_nickname'],
            'buyer_name'        => $data['buyer_name'],
            'buyer_mobile'      => $data['buyer_mobile'],
            'buyer_email'       => $data['buyer_email'],
            'buyer_address'     => $data['buyer_address'],
            // 收货人信息
            'ship_oaid'         => $data['ship_oaid'],
            'ship_name'         => $data['ship_name'],
            'ship_mobile'       => $data['ship_mobile'],
            'ship_email'        => $data['ship_email'],
            'ship_address'      => $data['ship_address'],
            'ship_province'     => $data['ship_province'],
            'ship_city'         => $data['ship_city'],
            'ship_district'     => $data['ship_district'],
            'ship_town'         => $data['ship_town'],
            'ship_village'      => $data['ship_village'],
            // 使用人信息
            'user_name'         => $data['user_name'],
            'user_mobile'       => $data['user_mobile'],
            'user_email'        => $data['user_email'],
            'user_address'      => $data['user_address'],
            'user_province'     => $data['user_province'],
            'user_city'         => $data['user_city'],
            'user_district'     => $data['user_district'],
            'user_town'         => $data['user_town'],
            'user_village'      => $data['user_village'],
            'status'            => $data['status'] ?: 0,
            'branch_id'         => $data['branch_id'],
            'branch_bn'         => $data['branch_bn'],
        ];
        if ($data['bm_id']) {
            $insertData['bm_id']       = $data['bm_id'];
            $insertData['material_bn'] = $data['material_bn'];
        }
        if ($data['barcode']) {
            $insertData['barcode'] = $data['barcode'];
        }
        if ($data['imei_number']) {
            $insertData['imei_number'] = $data['imei_number'];
        }
        if (!empty($data['version_time'])) {
            $insertData['version_time'] = (int) $data['version_time'];
        }

        return $insertData;
    }

    private function _saveSnFlow($snFlowMdl, $data, $snId)
    {
        $flowData = [
            'source'            => $data['source'] ?? 'local',
            'shop_id'           => $data['shop_id'] ?? '',
            'sn_id'             => $snId,
            'order_bn'          => $data['order_bn'],
            'branch_id'         => $data['branch_id'],
            'branch_bn'         => $data['branch_bn'],
            'original_id'       => $data['original_id'] ?: 0,
            'original_bn'       => $data['original_bn'],
            'platform_order_bn' => $data['platform_order_bn'],
            'bill_bn'           => $data['bill_bn'],
            'original_type'     => $data['original_type'],
            'io_status'         => $data['io_status'] ?: 0,
            'status'            => $data['status'] ?: 0,
            'io_time'           => $data['io_time'],
            'buyer_open_id'     => $data['buyer_open_id'],
            'buyer_nickname'    => $data['buyer_nickname'],
            'buyer_name'        => $data['buyer_name'],
            'buyer_mobile'      => $data['buyer_mobile'],
            'buyer_email'       => $data['buyer_email'],
            'buyer_address'     => $data['buyer_address'],
            'ship_oaid'         => $data['ship_oaid'],
            'ship_name'         => $data['ship_name'],
            'ship_mobile'       => $data['ship_mobile'],
            'ship_email'        => $data['ship_email'],
            'ship_address'      => $data['ship_address'],
            'ship_province'     => $data['ship_province'],
            'ship_city'         => $data['ship_city'],
            'ship_district'     => $data['ship_district'],
            'ship_town'         => $data['ship_town'],
            'ship_village'      => $data['ship_village'],
            'user_name'         => $data['user_name'],
            'user_mobile'       => $data['user_mobile'],
            'user_email'        => $data['user_email'],
            'user_address'      => $data['user_address'],
            'user_province'     => $data['user_province'],
            'user_city'         => $data['user_city'],
            'user_district'     => $data['user_district'],
            'user_town'         => $data['user_town'],
            'user_village'      => $data['user_village'],
        ];
        if ($data['bm_id']) {
            $flowData['bm_id']       = $data['bm_id'];
            $flowData['material_bn'] = $data['material_bn'];
        }
        if ($data['barcode']) {
            $flowData['barcode'] = $data['barcode'];
        }
        if ($data['imei_number']) {
            $flowData['imei_number'] = $data['imei_number'];
        }
        if ($snFlowInfo = $snFlowMdl->db_dump([
            'sn_id'         => $flowData['sn_id'],
            'order_bn'      => $flowData['order_bn'],
            'material_bn'   => $flowData['material_bn'],
            'original_bn'   => $flowData['original_bn'],
            'original_type' => $flowData['original_type'],
        ], 'id')) {
            $flowData['id'] = $snFlowInfo['id'];
        }
        $snFlowMdl->save($flowData);
    }

    /**
     * 更新SN使用信息
     * @param $params
     * @return array
     */
    public function updateUserInfo($params)
    {
        $opMdl              = app::get('ome')->model('operation_log');
        $machineCenterSnMdl = app::get('machinecenter')->model('material_sn');

        $params = $this->_sanitizeMb3TextFields($params);

        $params['qa_expire_item']    = $updateData['qa_expire_item'] = kernel::single('ome_func')->date2time($params['qa_expire_item']);
        $updateData['user_name']     = $params['user_name'];
        $updateData['user_mobile']   = $params['user_mobile'];
        $updateData['user_email']    = $params['user_email'];
        $updateData['user_address']  = $params['user_address'];
        $updateData['user_province'] = $params['user_province'];
        $updateData['user_city']     = $params['user_city'];
        $updateData['user_district'] = $params['user_district'];
        $updateData['user_town']     = $params['user_town'];
        $updateData['user_village']  = $params['user_village'];
        $msg                         = '质保过期时间:' . date('Y-m-d', $updateData['qa_expire_item']);
        if ($params['user_name']) {
            $msg .= ' 使用人姓名：' . $params['user_name'];
        }
        if ($params['user_mobile']) {
            $msg .= ' 使用人手机：' . $params['user_mobile'];
        }
        if ($params['user_email']) {
            $msg .= ' 使用人邮箱：' . $params['user_email'];
        }
        $msg           .= ' 使用人地址：' . $params['user_province'] . $params['user_city'] . $params['user_district'] . $params['user_town'] . $params['user_village'] . $params['user_address'];
        $operator_type = '人工编辑';
        if ($params['operator_type']) {
            $operator_type = $params['operator_type'];
        }
        if ($params['id']) {
            $updateData = array_filter($updateData);
            if ($machineCenterSnMdl->update($updateData, ['id' => $params['id']])) {
                $opMdl->write_log('update@machinecenter', $params['id'], $msg);
                return [true, $operator_type . '操作更新成功'];
            }
        } else {
            $res = kernel::single('machinecenter_event_receive_sn')->add($params);
            if ($res['rsp'] == 'succ') {
                return [true, $operator_type . '操作新增成功'];
            }
            return [false, $operator_type . '操作新增失败' . $res['msg']];
        }
        return [false, $operator_type . '操作更新失败'];
    }

    private $updateFields = [
        'shop_id',
        'qa_expire_item',
        'material_bn',
        'barcode',
        'imei_number',
        'bm_id',
        'branch_id',
        'branch_bn',
        'platform_order_bn',
        'order_bn',
        'original_id',
        'original_bn',
        'original_type',
        'bill_bn',
        'status',
        'buyer_nickname',
        'buyer_name',
        'buyer_mobile',
        'buyer_email',
        'buyer_address',
        'ship_oaid',
        'ship_name',
        'ship_mobile',
        'ship_email',
        'ship_address',
        'ship_province',
        'ship_city',
        'ship_district',
        'ship_town',
        'ship_village',
        'user_name',
        'user_mobile',
        'user_email',
        'user_address',
        'user_province',
        'user_city',
        'user_district',
        'user_town',
        'user_village',
    ];

    /**
     * openapi更新SN信息
     * 需求
     * -新SN系统存在记录
     * 1、旧SN去掉当前单据明细，更新主结构状态和信息，取最新的一条SN记录
     * 2、新SN主表存在时，新增一条明细，更新主结构状态和信息
     *
     * -新SN系统不存在
     * 1、旧SN去掉当前单据明细，更新主结构状态和信息，取最新的一条SN记录
     * 2、新SN主表不存在时，新增一条SN记录，关联明细，更新主结构状态和信息
     *
     * 变更都需要记录日志
     * 老SN不存在接口报错
     *
     * @param $params
     * @return array
     */
    public function updateSnInfo($params)
    {
        $opMdl     = app::get('ome')->model('operation_log');
        $snMdl     = app::get('machinecenter')->model('material_sn');
        $snFlowMdl = app::get('machinecenter')->model('material_sn_flow');

        $bill_bn   = $params["bill_bn"];
        $bill_type = $params["bill_type"];
        $items     = $params["items"];

        foreach ($items as $key => $val) {
            $oldSn      = $val['old_sn'];
            $newSn      = $val['new_sn'];
            $materialBn = $val['bn'];
            $newInfo    = $snMdl->db_dump(['sn' => $newSn]);
            $oldInfo    = $snMdl->db_dump(['sn' => $oldSn]);
            $oldFlowRow = $snFlowMdl->db_dump(['sn_id' => $oldInfo['id'], 'material_bn' => $materialBn, 'original_bn' => $bill_bn, 'original_type' => $bill_type]);
            //新SN有记录的情况处理
            if ($newInfo) {
                $snRowId = $newInfo['id'];
                //流水发生变更，更新主结构信息
                $updateSnId = [$newInfo['id'], $oldInfo['id']];
            } else {
                //新SN无记录insert记录
                $snUpdateData = [];
                foreach ($this->updateFields as $field) {
                    $snUpdateData[$field] = $oldFlowRow[$field];
                }
                $snUpdateData['sn']             = $newSn;
                $snUpdateData['qa_expire_item'] = $oldInfo['qa_expire_item'];
                $snMdl->insert($snUpdateData);
                $snRowId = $snUpdateData['id'];
                //流水发生变更，更新主结构信息
                $updateSnId = [$snRowId, $oldInfo['id']];
            }
            //流水挂靠主数据变化，记录日志
            if ($oldFlowRow) {
                $snFlowMdl->update(['sn_id' => $snRowId], ['id' => $oldFlowRow['id']]);
                $opMdl->write_log('update@machinecenter', $oldInfo['id'], '发生SN更新,新SN：' . $newSn);
            }
            //更新历史业务数据
            $snFilter     = ['bill_no' => $bill_bn, 'serial_number' => $oldSn];
            $snHistoryMdl = app::get('ome')->model('product_serial_history');
            if ($bill_type == 'delivery') {
                $snFilter['bill_type'] = '1';
            } elseif ($bill_type == 'reship') {
                $snFilter['bill_type'] = '2';
            } else {
                $snFilter['bill_type'] = '-1';
            }
            $snHistoryInfo = $snHistoryMdl->db_dump($snFilter, 'history_id');
            if ($snHistoryInfo) {
                $snHistoryMdl->update(['serial_number' => $newSn], ['history_id' => $snHistoryInfo['history_id']]);
            }
            foreach ($updateSnId as $snId) {
                $flowList     = $snFlowMdl->getList('*', ['sn_id' => $snId], 0, 1, 'io_time desc');
                $flowRow      = current($flowList);
                $snUpdateData = [];
                foreach ($this->updateFields as $field) {
                    if ($snId == $snRowId && in_array($field, ['qa_expire_item',])) {
                        continue;
                    }
                    $snUpdateData[$field] = $flowRow[$field];
                }
                if ($snId == $oldInfo['id']) {
                    $snUpdateData['bill_bn']     = '';
                    $snUpdateData['original_id'] = 0;
                    $snUpdateData['original_bn'] = '';
                    $snUpdateData['order_bn']    = '';
                }
                //为空的记录流水状态为未使用
                if (empty($flowRow)) {
                    $snUpdateData['status'] = '3';
                }
                $snMdl->update($snUpdateData, ['id' => $snId]);
                $opMdl->write_log('update@machinecenter', $snId, 'SN流水发生更新');
                foreach(kernel::servicelist('ome.service.machinecenter.sn.edit') as $service) {
                    if(method_exists($service,'after_sn_edit')) {
                        
                        $service->after_sn_edit($params);
                    }
                }
            }
        }
        

        return [true, '更新SN成功'];
    }
}