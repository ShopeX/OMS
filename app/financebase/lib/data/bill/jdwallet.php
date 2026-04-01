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
 * 处理京东钱包导入
 * 支持多工作表Excel文件（结算表和资金表）
 *
 * @author AI Assistant
 * @version 1.0
 */

class financebase_data_bill_jdwallet extends financebase_abstract_bill
{
    public $order_bn_prefix = '';
    public $column_num = 22; // 结算表字段数量（比京东日账单少一个结算状态字段）
    public $ioTitle = array();
    public $ioTitleKey = array();
    public $verified_data = array();
    public $sheet_type = 'settlement'; // 工作表类型：settlement或fund


    /**
     * 检查文件是否有效
     * @param string $file_name 文件名
     * @param string $file_type 文件类型
     * @return array [是否有效, 错误信息, 标题]
     */
    public function checkFile($file_name, $file_type)
    {
        try {
            // 检查是否为多工作表Excel文件
            if ($file_type !== 'xlsx') {
                return array(false, '京东钱包导入只支持.xlsx格式的Excel文件', array());
            }

            // 使用PhpSpreadsheet读取多工作表
            $workbook = $this->readMultiSheetExcel($file_name);
            
            if (!$workbook) {
                return array(false, '无法读取Excel文件或文件格式不正确', array());
            }

            // 检查结算表
            $settlement_sheet = $workbook['settlement'];
            $settlement_headers = $this->getSheetHeaders($settlement_sheet);
            
            $required_fields = array('订单编号', '单据编号', '费用项', '金额');
            foreach ($required_fields as $field) {
                if (!in_array($field, $settlement_headers)) {
                    return array(false, '结算表缺少必填字段：' . $field, array());
                }
            }

            // 检查资金表
            $fund_sheet = $workbook['fund'];
            $fund_headers = $this->getSheetHeaders($fund_sheet);
            
            $required_fund_fields = array('创建时间', '商户订单号');
            foreach ($required_fund_fields as $field) {
                if (!in_array($field, $fund_headers)) {
                    return array(false, '资金表缺少必填字段：' . $field, array());
                }
            }

            return array(true, '文件验证通过', $settlement_sheet[0]);

        } catch (Exception $e) {
            return array(false, '文件检查异常：' . $e->getMessage(), array());
        }
    }


    /**
     * 读取多工作表Excel文件
     * @param string $file_name 文件名
     * @return array|false
     */
    private function readMultiSheetExcel($file_name)
    {
        try {
            // 检查Vtiful\Kernel\Excel是否可用
            if (!class_exists('Vtiful\Kernel\Excel')) {
                throw new Exception('Vtiful\Kernel\Excel库未安装');
            }

            $path = pathinfo($file_name);
            $excel = new \Vtiful\Kernel\Excel(['path' => $path['dirname']]);
            
            // 读取结算表
            $settlement_data = $this->readSheetData($excel, $path['basename'], '结算表');
            if (!$settlement_data) {
                throw new Exception('无法读取结算表数据');
            }
            
            // 读取资金表
            $fund_data = $this->readSheetData($excel, $path['basename'], '资金表');
            if (!$fund_data) {
                throw new Exception('无法读取资金表数据');
            }
            
            return array(
                'settlement' => $settlement_data,
                'fund' => $fund_data
            );

        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 读取指定工作表数据
     * @param object $excel Excel对象
     * @param string $filename 文件名
     * @param string $sheet_name 工作表名
     * @return array|false
     */
    private function readSheetData($excel, $filename, $sheet_name)
    {
        try {
            // 打开文件并切换到指定工作表
            $excel->openFile($filename);
            
            // 获取所有工作表名称
            $sheet_names = $excel->sheetList();
            if (!in_array($sheet_name, $sheet_names)) {
                return false;
            }
            
            // 切换到指定工作表
            $excel->openSheet($sheet_name);
            
            // 读取标题行
            $title = $excel->nextRow();
            if (!$title) {
                return false;
            }
            
            // 设置日期列类型转换
            $type = array_pad([], count($title), \Vtiful\Kernel\Excel::TYPE_STRING);
            
            // 根据工作表类型设置不同的日期字段
            if ($sheet_name === '结算表') {
                $timeColumn = array('费用结算时间', '费用计费时间', '费用发生时间');
            } else if ($sheet_name === '资金表') {
                $timeColumn = array('创建时间');
            } else {
                $timeColumn = array();
            }
            
            foreach ($timeColumn as $v) {
                if ($k = array_search($v, $title)) {
                    $type[$k] = \Vtiful\Kernel\Excel::TYPE_TIMESTAMP;
                }
            }
            $excel->setType($type);
            
            // 读取所有数据
            $data = array();
            $data[] = $title; // 第一行是标题
            
            while (($row = $excel->nextRow()) !== null) {
                // 转换日期字段
                foreach ($timeColumn as $v) {
                    if ($k = array_search($v, $title)) {
                        if (isset($row[$k]) && is_numeric($row[$k]) && $row[$k] > 0) {
                            $timestamp = ($row[$k] - 25569) * 86400; // Excel基准日期是1900-01-01，Unix基准是1970-01-01
                            $row[$k] = date('Y-m-d H:i:s', $timestamp);
                        }
                    }
                }
                
                // 处理金额字段精度（解决浮点数精度问题）
                if ($sheet_name === '结算表') {
                    $amountColumn = array('金额');
                } else if ($sheet_name === '资金表') {
                    $amountColumn = array('账户余额(元)', '收入金额(元)', '支出金额(元)');
                } else {
                    $amountColumn = array();
                }
                
                foreach ($amountColumn as $v) {
                    if ($k = array_search($v, $title)) {
                        if (isset($row[$k]) && is_numeric($row[$k])) {
                            // 保留2位小数，避免浮点数精度问题
                            $row[$k] = number_format((float)$row[$k], 2, '.', '');
                        }
                    }
                }
                
                $data[] = $row;
            }
            
            return $data;
            
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 获取工作表标题行
     * @param array $sheet_data 工作表数据
     * @return array
     */
    private function getSheetHeaders($sheet_data)
    {
        if (empty($sheet_data) || !is_array($sheet_data)) {
            return array();
        }
        
        // 第一行就是标题行
        $headers = $sheet_data[0];
        return array_map('trim', $headers);
    }


    /**
     * 重写process方法，设置工作表类型后调用父类方法，并处理状态更新
     * @param int $cursor_id 游标ID
     * @param array $params 参数
     * @param array $errmsg 错误信息
     * @return bool
     */
    public function process($cursor_id, $params, &$errmsg)
    {
        // 设置工作表类型，供getSdf方法使用
        $this->sheet_type = $params['sheet_type'] ?? 'settlement';
        
        // 调用父类的process方法
        $result = parent::process($cursor_id, $params, $errmsg);
        
        // 京东钱包导入特有的状态更新逻辑
        if (isset($params['import_id'])) {
            $db = kernel::database();
            $import_id = intval($params['import_id']);
            
            if ($result) {
                // 处理成功，更新对应工作表的完成分片数
                $sheet_type = $params['sheet_type'] ?? 'settlement';
                
                if ($sheet_type === 'settlement') {
                    // 结算表分片完成，增加完成计数
                    $sql = "UPDATE sdb_financebase_bill_import_jdwallet 
                            SET settlement_chunks_completed = settlement_chunks_completed + 1 
                            WHERE id = {$import_id}";
                    $db->exec($sql);
                } else if ($sheet_type === 'fund') {
                    // 资金表分片完成，增加完成计数
                    $sql = "UPDATE sdb_financebase_bill_import_jdwallet 
                            SET fund_chunks_completed = fund_chunks_completed + 1 
                            WHERE id = {$import_id}";
                    $db->exec($sql);
                }
                
                // 检查是否所有分片都已完成，如果完成则更新状态
                $check_sql = "UPDATE sdb_financebase_bill_import_jdwallet 
                             SET status = 'completed' 
                             WHERE id = {$import_id} 
                             AND settlement_chunks_completed >= settlement_chunks_total 
                             AND fund_chunks_completed >= fund_chunks_total 
                             AND status != 'completed'";
                $db->exec($check_sql);
                
            } else {
                // 处理失败，更新为失败状态
                $error_msg = !empty($errmsg) ? implode('; ', $errmsg) : '数据处理失败';
                $error_msg = mysql_real_escape_string($error_msg);
                $sql = "UPDATE sdb_financebase_bill_import_jdwallet 
                        SET status = 'failed', 
                            error_msg = '{$error_msg}' 
                        WHERE id = {$import_id}";
                $db->exec($sql);
            }
        }
        
        return $result;
    }


    /**
     * 获取结算表标题定义（与京东日账单基本一致，但缺少结算状态字段）
     * @return array
     */
    public function getTitle()
    {
        $title = array(
            'order_no'              => '订单编号',
            'financial_no'          => '单据编号',
            'order_type'            => '单据类型',
            'goods_bn'              => '商品编号',
            'out_trade_no'          => '商户订单号',
            'goods_name'            => '商品名称',
            'trade_create_time'     => '费用发生时间',
            'trade_pay_time'        => '费用计费时间',
            'trade_settlement_time' => '费用结算时间',
            'trade_type'            => '费用项',
            'amount'                => '金额',
            'currency'              => '币种',
            'bill_type'             => '商家应收/应付',
            'settlement_remarks'    => '钱包结算备注',
            'shop_bn'               => '店铺号',
            'jd_store_bn'           => '京东门店编号',
            'brand_store_bn'        => '品牌门店编号',
            'store_name'            => '门店名称',
            'remarks'               => '备注',
            'fee_direction'         => '收支方向',
            'goods_number'          => '商品数量',
            'jd_trade_time'         => '账单日期', // 结算表使用"账单日期"，不是"对账日期"
        );

        return $title;
    }

    /**
     * 获取资金表标题定义
     * @return array
     */
    public function getFundTitle()
    {
        $title = array(
            'create_time'           => '创建时间',
            'account_code'          => '账户代码',
            'account_name'          => '账户名称',
            'currency'              => '币种',
            'income_amount'         => '收入金额(元)',
            'expense_amount'        => '支出金额(元)',
            'account_balance'       => '账户余额(元)',
            'trade_type'            => '交易类型',
            'out_trade_no'          => '商户订单号',
            'trade_order_no'        => '交易订单号',
            'original_merchant_order_no' => '原商户订单号',
            'remarks'                => '资金动账备注',
        );

        return $title;
    }

    /**
     * 重写getSdf方法，根据工作表类型选择处理方法
     * @param array $row 行数据
     * @param int $offset 行号
     * @param array $title 标题
     * @return array
     */
    public function getSdf($row, $offset = 1, $title)
    {
        // 检查是否有sheet_type参数，如果有则使用对应的处理方法
        $sheet_type = $this->sheet_type ?? 'settlement';
        
        if ($sheet_type === 'fund') {
            // 处理资金表数据
            return $this->getFundSdf($row, $offset, $title);
        } else {
            // 处理结算表数据（与京东日账单逻辑完全一致）
            return $this->getSettlementSdf($row, $offset, $title);
        }
    }

    /**
     * 处理资金表数据
     * @param array $row 行数据
     * @param int $offset 行号
     * @param array $title 标题
     * @return array
     */
    public function getFundSdf($row, $offset = 1, $title)
    {
        $row = array_map('trim', $row);

        if (!$this->ioTitle) {
            $this->ioTitle = $this->getFundTitle();
            $this->ioTitleKey = array_keys($this->ioTitle);
        }

        $titleKey = array();
        foreach ($title as $k => $t) {
            $titleKey[$k] = array_search($t, $this->getFundTitle());

            if ($titleKey[$k] === false) {
                return array('status' => false, 'msg' => '未定义字段`' . $t . '`');
            }
        }

        $res = array('status' => true, 'data' => array(), 'msg' => '');

        if (count($row) >= 12 && $row[0] != '创建时间') {
            $tmp = array_combine($titleKey, $row);
            
            // 转换Excel日期字段（Excel序列号转换为日期字符串）
            $timeColumn = array('create_time');
            foreach ($timeColumn as $k) {
                if (isset($tmp[$k]) && is_numeric($tmp[$k]) && $tmp[$k] > 0) {
                    $timestamp = ($tmp[$k] - 25569) * 86400; // Excel基准日期是1900-01-01，Unix基准是1970-01-01
                    $tmp[$k] = date('Y-m-d H:i:s', $timestamp);
                }
            }
            
            // 判断必填字段不能为空
            foreach ($tmp as $k => $v) {
                if (in_array($k, array('create_time', 'out_trade_no'))) {
                    if (!$v) {
                        $res['status'] = false;
                        $res['msg'] = sprintf("LINE %d : %s 不能为空！", $offset, $this->ioTitle[$k]);
                        return $res;
                    }
                }
            }

            // 计算money字段（收入金额 - 支出金额）
            $tmp['money'] = floatval($tmp['income_amount'] ?? 0) - floatval($tmp['expense_amount'] ?? 0);
            
            // 设置financial_no字段
            $tmp['financial_no'] = $tmp['trade_order_no'] ?? '';

            // 直接返回原始数据，父类process会调用_filterData
            $res['data'] = $tmp;
        }

        return $res;
    }

    /**
     * 处理结算表数据（与京东日账单逻辑完全一致）
     * @param array $row 行数据
     * @param int $offset 行号
     * @param array $title 标题
     * @return array
     */
    private function getSettlementSdf($row, $offset = 1, $title)
    {
        $row = array_map('trim', $row);

        if (!$this->ioTitle) {
            $this->ioTitle = $this->getTitle();
            $this->ioTitleKey = array_keys($this->ioTitle);
        }

        $titleKey = array();
        foreach ($title as $k => $t) {
            $titleKey[$k] = array_search($t, $this->getTitle());

            if ($titleKey[$k] === false) {
                return array('status' => false, 'msg' => '未定义字段`' . $t . '`');
            }
        }

        $res = array('status' => true, 'data' => array(), 'msg' => '');

        if ($this->column_num <= count($row) && $row[0] != '订单编号') {
            $tmp = array_combine($titleKey, $row);
            
            // 转换Excel日期字段（Excel序列号转换为日期字符串）
            $timeColumn = array('trade_create_time', 'trade_pay_time', 'trade_settlement_time');
            foreach ($timeColumn as $k) {
                if (isset($tmp[$k]) && is_numeric($tmp[$k]) && $tmp[$k] > 0) {
                    $timestamp = ($tmp[$k] - 25569) * 86400; // Excel基准日期是1900-01-01，Unix基准是1970-01-01
                    $tmp[$k] = date('Y-m-d H:i:s', $timestamp);
                }
            }
            
            // 判断必填字段不能为空
            foreach ($tmp as $k => $v) {
                if (in_array($k, array('order_no', 'financial_no', 'trade_settlement_time'))) {
                    if (!$v) {
                        $res['status'] = false;
                        $res['msg'] = sprintf("LINE %d : %s 不能为空！", $offset, $this->ioTitle[$k]);
                        return $res;
                    }
                }
            }

            // 时间格式验证
            foreach ($tmp as $k => $v) {
                if (in_array($k, array('trade_create_time', 'trade_pay_time', 'trade_settlement_time'))) {
                    $result = finance_io_bill_verify::isDate($v);
                    if ($result['status'] == 'fail') {
                        $res['status'] = false;
                        $res['msg'] = sprintf("LINE %d : %s 时间(%s)格式错误！", $offset, $this->ioTitle[$k], $v);
                        return $res;
                    }
                }
            }

            // 金额字段精度处理（解决浮点数精度问题）
            foreach ($tmp as $k => $v) {
                if (in_array($k, array('amount'))) {
                    if (is_numeric($v)) {
                        // 保留2位小数，避免浮点数精度问题
                        $tmp[$k] = number_format((float)$v, 2, '.', '');
                    }
                }
            }

            // 金额格式验证
            foreach ($tmp as $k => $v) {
                if (in_array($k, array('amount'))) {
                    $result = finance_io_bill_verify::isPrice($v);
                    if ($result['status'] == 'fail') {
                        $res['status'] = false;
                        $res['msg'] = sprintf("LINE %d : %s 金额(%s)格式错误！", $offset, $this->ioTitle[$k], $v);
                        return $res;
                    }
                }
            }

            // 字段清理
            foreach ($tmp as $k => $v) {
                if (in_array($k, array('order_no', 'financial_no', 'out_trade_no', 'goods_bn'))) {
                    $tmp[$k] = trim($v, '=\"');
                }
            }

            // 直接返回原始数据，父类process会调用_filterData
            $res['data'] = $tmp;
        }

        return $res;
    }


    /**
     * 数据过滤和转换（根据工作表类型进行不同处理）
     * @param array $data 原始数据
     * @return array
     */
    public function _filterData($data)
    {
        // 检查工作表类型，进行不同的数据处理
        $sheet_type = $this->sheet_type ?? 'settlement';
        
        if ($sheet_type === 'fund') {
            // 处理资金表数据
            return $this->_filterFundData($data);
        } else {
            // 处理结算表数据（与京东日账单保持一致）
            return $this->_filterSettlementData($data);
        }
    }


    /**
     * 处理结算表数据（与京东日账单保持一致）
     * @param array $data 原始数据
     * @return array
     */
    private function _filterSettlementData($data)
    {
        $new_data = array();
        
        $new_data['order_bn'] = $this->_getOrderBn($data);
        $new_data['trade_no'] = '';
        $new_data['financial_no'] = $data['financial_no'];// 京东把订单号存在财务流水号里，只作为生成唯一标识用
        $new_data['out_trade_no'] = $data['out_trade_no'];
        $new_data['trade_time'] = $data['trade_settlement_time'] ? strtotime($data['trade_settlement_time']) : 0;
        $new_data['trade_type'] = $data['trade_type'];
        $new_data['money'] = $data['amount'];
        $new_data['member'] = '';
        $new_data['unique_id'] = $data['financial_no'];

        $new_data['platform_type'] = 'jdwallet';
        $new_data['remarks'] = $data['remarks'];

        return $new_data;
    }


    /**
     * 获取订单号（与京东日账单保持一致）
     * @param array $params 参数
     * @return string
     */
    public function _getOrderBn($params)
    {
        return $params['order_no'];
    }


    /**
     * 资金表数据过滤和转换
     * @param array $data 原始数据
     * @return array
     */
    public function _filterFundData($data)
    {
        $new_data = array();

        // 从资金动账备注中提取订单号
        $order_bn = $this->_extractOrderBnFromRemark($data['remarks'] ?? '');

        // 资金表数据转换为标准账单格式（与结算表保持一致）
        $new_data['order_bn'] = $order_bn;
        $new_data['trade_no'] = '';
        $new_data['financial_no'] = $data['financial_no'] ?? ''; // 使用财务流水号
        $new_data['out_trade_no'] = $data['out_trade_no'] ?? '';
        $new_data['trade_time'] = $data['create_time'] ? strtotime($data['create_time']) : 0;
        $new_data['trade_type'] = !empty($data['trade_type']) ? $data['trade_type'] : '资金流水';
        $new_data['money'] = $data['money'] ?? 0;
        $new_data['member'] = '';
        // $new_data['unique_id'] = md5($data['account_code'] . '_' . $data['out_trade_no'] . '_' . $data['create_time']);
        $new_data['unique_id'] = $data['financial_no'] ?? '';

        $new_data['platform_type'] = 'jdwallet_fund';
        $new_data['remarks'] = $data['remarks'] ?? '';

        return $new_data;
    }

    /**
     * 从交易备注中提取订单号
     * @param string $remark 交易备注
     * @return string
     */
    private function _extractOrderBnFromRemark($remark)
    {
        if (empty($remark)) {
            return '';
        }

        // 匹配"订单号"后面的数字
        // 支持两种模式：
        // 1. "售后服务单号3185685896，对应订单号327569834090" -> 提取327569834090
        // 2. "价保对应订单号319578099931" -> 提取319578099931
        $pattern = '/订单号(\d+)/';
        if (preg_match($pattern, $remark, $matches)) {
            return $matches[1];
        }

        return '';
    }


    /**
     * 获取具体类别
     * @param array $params 参数
     * @return string
     */
    public function getBillCategory($params)
    {
        if (!$this->rules) {
            $this->getRules('360buy'); // 暂时使用360buy的规则
        }

        $this->verified_data = $params;

        if ($this->rules) {
            foreach ($this->rules as $item) {
                foreach ($item['rule_content'] as $rule) {
                    if ($this->checkRule($rule)) {
                        return $item['bill_category'];
                    }
                }
            }
        }

        return '';
    }

    /**
     * 同步到对账表
     * @param array $data 原始数据
     * @param string $bill_category 具体类别
     * @return bool
     */
    public function syncToBill($data, $bill_category = '')
    {
        $data['content'] = json_decode(stripslashes($data['content']), 1);
        if (!$data['content']) return false;

        $tmp = $data['content'];
        $shop_id = $data['shop_id'];

        $mdlBill = app::get('finance')->model('bill');
        $oMonthlyReport = kernel::single('finance_monthly_report');

        $tmp['fee_obj'] = '京东钱包';
        $tmp['fee_item'] = $bill_category;

        $res = $this->getBillType($tmp, $shop_id);
        if (!$res['status']) return false;

        if (!$data['shop_name']) {
            $data['shop_name'] = isset($this->shop_list[$data['shop_id']]) ? $this->shop_list[$data['shop_id']]['name'] : '';
        }

        // 根据工作表类型进行不同的处理
        $sheet_type = $this->sheet_type ?? 'settlement';
        
        if ($sheet_type === 'fund') {
            // 资金表处理
            return $this->syncFundToBill($data, $tmp, $res, $mdlBill);
        } else {
            // 结算表处理
            return $this->syncSettlementToBill($data, $tmp, $res, $mdlBill);
        }
    }

    /**
     * 同步结算表数据到对账表
     * @param array $data 原始数据
     * @param array $tmp 处理后的数据
     * @param array $res 账单类型结果
     * @param object $mdlBill 账单模型
     * @return bool
     */
    private function syncSettlementToBill($data, $tmp, $res, $mdlBill)
    {
        $base_sdf = array(
            'order_bn'          => $this->_getOrderBn($tmp),
            'channel_id'        => $data['shop_id'],
            'channel_name'      => $data['shop_name'],
            'trade_time'        => strtotime($tmp['trade_settlement_time']),
            'fee_obj'           => $tmp['fee_obj'],
            'money'             => round($tmp['amount'], 2),
            'fee_item'          => $tmp['fee_item'],
            'fee_item_id'       => isset($this->fee_item_rules[$tmp['fee_item']]) ? $this->fee_item_rules[$tmp['fee_item']] : 0,
            'credential_number' => $tmp['financial_no'],// 单据编号
            'member'            => '',
            'memo'              => $tmp['remarks'],
            'unique_id'         => $data['unique_id'],
            'create_time'       => time(),
            'fee_type'          => $tmp['trade_type'],
            'fee_type_id'       => $res['fee_type_id'],
            'bill_type'         => $res['bill_type'],
            'charge_status'     => 1,// 流水直接设置记账成功
            'charge_time'       => time(),
        );
        $base_sdf['monthly_id'] = 0;
        $base_sdf['monthly_item_id'] = 0;
        $base_sdf['monthly_status'] = 0;
        
        $base_sdf['crc32_order_bn'] = sprintf('%u', crc32($base_sdf['order_bn']));
        $base_sdf['bill_bn'] = $mdlBill->gen_bill_bn();
        $base_sdf['unconfirm_money'] = $base_sdf['money'];

        if ($mdlBill->insert($base_sdf)) {
            kernel::single('finance_monthly_report_items')->dealBillMatchReport($base_sdf['bill_id']);
            return true;
        }
        return false;
    }

    /**
     * 同步资金表数据到对账表
     * @param array $data 原始数据
     * @param array $tmp 处理后的数据
     * @param array $res 账单类型结果
     * @param object $mdlBill 账单模型
     * @return bool
     */
    private function syncFundToBill($data, $tmp, $res, $mdlBill)
    {
        $base_sdf = array(
            'order_bn'          => $this->_getOrderBn($tmp),
            'channel_id'        => $data['shop_id'],
            'channel_name'      => $data['shop_name'],
            'trade_time'        => strtotime($tmp['create_time']), // 资金表使用create_time字段
            'fee_obj'           => $tmp['fee_obj'],
            'money'             => round($tmp['money'], 2), // 资金表使用money字段（已计算好的净额）
            'fee_item'          => $tmp['fee_item'],
            'fee_item_id'       => isset($this->fee_item_rules[$tmp['fee_item']]) ? $this->fee_item_rules[$tmp['fee_item']] : 0,
            'credential_number' => $tmp['financial_no'],// 使用账户代码作为单据编号
            'member'            => '',
            'memo'              => $tmp['remarks'],
            'unique_id'         => $data['unique_id'],
            'create_time'       => time(),
            'fee_type'          => $tmp['trade_type'],
            'fee_type_id'       => $res['fee_type_id'],
            'bill_type'         => $res['bill_type'],
            'charge_status'     => 1,// 流水直接设置记账成功
            'charge_time'       => time(),
        );
        $base_sdf['monthly_id'] = 0;
        $base_sdf['monthly_item_id'] = 0;
        $base_sdf['monthly_status'] = 0;
        
        $base_sdf['crc32_order_bn'] = sprintf('%u', crc32($base_sdf['order_bn']));
        $base_sdf['bill_bn'] = $mdlBill->gen_bill_bn();
        $base_sdf['unconfirm_money'] = $base_sdf['money'];

        if ($mdlBill->insert($base_sdf)) {
            kernel::single('finance_monthly_report_items')->dealBillMatchReport($base_sdf['bill_id']);
            return true;
        }
        return false;
    }

    // 更新订单号
    public function updateOrderBn($data)
    {
        $this->_formatData($data);
        $mdlBill = app::get('finance')->model('bill');
        if(!$this->shop_list_by_name)
        {
            $this->shop_list_by_name = financebase_func::getShopList(financebase_func::getShopType());
            $this->shop_list_by_name = array_column($this->shop_list_by_name,null,'name');
        }

        foreach ($data as $v) 
        {
            if('订单编号' == $v[0]) continue;

            if(!$v[21] || !$v[22] || !$v[23]) continue;

            $shop_id = isset($this->shop_list_by_name[$v[21]]) ? $this->shop_list_by_name[$v[21]]['shop_id'] : 0;
            if(!$shop_id) continue;

            $filter = array('bill_bn'=>$v[22],'shop_id'=>$shop_id);


            // 找到unique_id
            $bill_info = $mdlBill->getList('unique_id,bill_id',$filter,0,1);
            if(!$bill_info) continue;
            $bill_info = $bill_info[0];
             

            if($mdlBill->update(array('order_bn'=>$v[23]),array('bill_id'=>$bill_info['bill_id'])))
            {
                app::get('financebase')->model('bill')->update(array('order_bn'=>$v[23]),array('unique_id'=>$bill_info['unique_id'],'shop_id'=>$shop_id));
                $op_name = kernel::single('desktop_user')->get_name();
                $content = sprintf("订单号改成：%s",$v[23]);
                finance_func::addOpLog($v[22],$op_name,$content,'更新订单号');

            }

        }
        
    }

    public function getImportDateColunm($title=null)
    {
        $timeColumn = ['费用结算时间','费用计费时间','费用发生时间'];
        $timeCol = [];
        foreach ($timeColumn as $v) {
             if($k = array_search($v, $title)) {
                $timeCol[] = $k+1;
             }
         } 
        $timezone = defined('DEFAULT_TIMEZONE') ? DEFAULT_TIMEZONE : 0;
        return array('column'=>$timeCol,'time_diff'=>$timezone * 3600 );
    }

}
