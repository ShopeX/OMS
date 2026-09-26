<?php
/**
 * 微盟结算单导入（OMS 导入表，类支付宝账务明细格式）
 *
 * 列：账务流水号、业务流水号、商户订单号、商品名称、发生时间、对方账号、
 *     收入金额（+元）、支出金额（-元）、账户余额（元）、交易渠道、业务类型、备注、分类、平台
 */
class financebase_data_bill_weimobr_settlement extends financebase_abstract_bill
{
    public $column_num = 14;

    public function getTitle()
    {
        return array(
            'financial_no'   => '账务流水号',
            'trade_no'       => '业务流水号',
            'out_trade_no'   => '商户订单号',
            'goods_name'     => '商品名称',
            'trade_time'     => '发生时间',
            'member'         => '对方账号',
            'income_amount'  => '收入金额（+元）',
            'outcome_amount' => '支出金额（-元）',
            'balance_amount' => '账户余额（元）',
            'channel_name'   => '交易渠道',
            'trade_type'     => '业务类型',
            'remarks'        => '备注',
            'trade_desc'     => '分类',
            'platform_name'  => '平台',
        );
    }

    public function getSdf($row, $offset = 1, $title)
    {
        $row = array_map('trim', $row);

        if (!$this->ioTitle) {
            $this->ioTitle = $this->getTitle();
        }

        $res = array('status' => true, 'data' => array(), 'msg' => '');

        $first = isset($row[0]) ? str_replace("\u{FEFF}", '', $row[0]) : '';
        if ($first === '账务流水号') {
            return $res;
        }

        $tmpRow = array();
        foreach ($title as $k => $t) {
            $field = array_search($t, $this->getTitle(), true);
            if ($field !== false) {
                $tmpRow[$field] = isset($row[$k]) ? trim((string) $row[$k]) : '';
            }
        }

        if (empty($tmpRow) || empty($tmpRow['financial_no'])) {
            return $res;
        }

        foreach (array('financial_no', 'out_trade_no') as $field) {
            if (empty($tmpRow[$field])) {
                $res['status'] = false;
                $res['msg']    = sprintf('LINE %d : %s 不能为空！', $offset, $this->ioTitle[$field]);
                return $res;
            }
        }

        $income  = $this->_parseMoney($tmpRow['income_amount']);
        $outcome = $this->_parseMoney($tmpRow['outcome_amount']);
        if ($income == 0 && $outcome == 0) {
            $res['status'] = false;
            $res['msg']    = sprintf('LINE %d : 收入金额与支出金额不能同时为空！', $offset);
            return $res;
        }

        $tradeTime = $this->_resolveTradeTime($tmpRow);
        if (!$tradeTime) {
            $res['status'] = false;
            $res['msg']    = sprintf('LINE %d : %s 无法解析有效时间！', $offset, $this->ioTitle['trade_time']);
            return $res;
        }
        $tmpRow['trade_time'] = $tradeTime;

        if ($income > 0) {
            $priceCheck = finance_io_bill_verify::isPrice(sprintf('%.2f', $income));
            if ($priceCheck['status'] === 'fail') {
                $res['status'] = false;
                $res['msg']    = sprintf('LINE %d : %s 金额(%s)格式错误！', $offset, $this->ioTitle['income_amount'], $tmpRow['income_amount']);
                return $res;
            }
        }
        if ($outcome != 0) {
            $priceCheck = finance_io_bill_verify::isPrice(sprintf('%.2f', abs($outcome)));
            if ($priceCheck['status'] === 'fail') {
                $res['status'] = false;
                $res['msg']    = sprintf('LINE %d : %s 金额(%s)格式错误！', $offset, $this->ioTitle['outcome_amount'], $tmpRow['outcome_amount']);
                return $res;
            }
        }

        $tmpRow['income_amount']  = $income > 0 ? sprintf('%.2f', $income) : '';
        $tmpRow['outcome_amount'] = $outcome != 0 ? sprintf('%.2f', abs($outcome)) : '';
        $tmpRow['amount']         = $income > 0 ? $income : (float) (0 > $outcome ? $outcome : -abs($outcome));
        $tmpRow['order_bn']       = $this->_getOrderBn($tmpRow);

        $res['data'] = $tmpRow;
        return $res;
    }

    public function _filterData($data)
    {
        $new_data = array();

        $new_data['order_bn']     = $this->_getOrderBn($data);
        $new_data['trade_no']     = isset($data['trade_no']) ? $data['trade_no'] : '';
        $new_data['financial_no'] = $data['financial_no'];
        $new_data['out_trade_no'] = $data['out_trade_no'];
        $new_data['trade_time']   = strtotime($data['trade_time']);
        $new_data['trade_type']   = $data['trade_type'];
        $new_data['money']        = $data['amount'];
        $new_data['member']       = isset($data['member']) ? $data['member'] : '';
        $new_data['unique_id']    = md5($data['financial_no'] . '-' . $data['trade_type'] . '-' . $data['amount'] . '-' . $data['out_trade_no']);
        $new_data['platform_type'] = 'weimobr';
        $new_data['remarks']      = isset($data['remarks']) ? $data['remarks'] : '';

        return $new_data;
    }

    public function _getOrderBn($params)
    {
        if (!empty($params['out_trade_no'])) {
            return $params['out_trade_no'];
        }
        if (!empty($params['order_bn'])) {
            return $params['order_bn'];
        }

        return '';
    }

    public function getBillCategory($params)
    {
        if (!$this->rules) {
            $this->getRules('weimobr');
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

    public function checkFile($file_name, $file_type)
    {
        if ($file_type === 'et') {
            $file_type = 'xls';
        }

        $ioType = kernel::single('financebase_io_' . $file_type);
        $row    = $ioType->getData($file_name, 0, 3);

        if (empty($row[0])) {
            return array(false, '文件模板错误：未读取到表头');
        }

        $plateTitle = $row[0];
        $plateTitle[0] = str_replace("\u{FEFF}", '', $plateTitle[0]);
        $title = array_values($this->getTitle());
        foreach ($title as $v) {
            if (array_search($v, $plateTitle, true) === false) {
                return array(false, '文件模板错误：列【' . $v . '】未包含在' . implode('、', $plateTitle));
            }
        }

        return array(true, '文件模板匹配', $plateTitle);
    }

    public function getImportDateColunm($title = null)
    {
        $timeColumn = array('发生时间');
        $timeCol    = array();
        foreach ($timeColumn as $v) {
            if (($k = array_search($v, $title, true)) !== false) {
                $timeCol[] = $k + 1;
            }
        }
        $timezone = defined('DEFAULT_TIMEZONE') ? DEFAULT_TIMEZONE : 0;

        return array('column' => $timeCol, 'time_diff' => $timezone * 3600);
    }

    public function syncToBill($data, $bill_category = '')
    {
        $data['content'] = json_decode(stripslashes($data['content']), 1);
        if (!$data['content']) {
            return false;
        }

        $tmp     = $data['content'];
        $shop_id = $data['shop_id'];

        $mdlBill = app::get('finance')->model('bill');

        $tmp['fee_obj']  = '微盟';
        $tmp['fee_item'] = $bill_category;

        $res = $this->getBillType($tmp, $shop_id);
        if (!$res['status']) {
            return false;
        }

        if (!$data['shop_name']) {
            $data['shop_name'] = isset($this->shop_list[$data['shop_id']]) ? $this->shop_list[$data['shop_id']]['name'] : '';
        }

        $base_sdf = array(
            'order_bn'          => $this->_getOrderBn($tmp),
            'channel_id'        => $data['shop_id'],
            'channel_name'      => $data['shop_name'],
            'trade_time'        => strtotime($tmp['trade_time']),
            'fee_obj'           => $tmp['fee_obj'],
            'money'             => round(abs($tmp['amount']), 2),
            'fee_item'          => $tmp['fee_item'],
            'fee_item_id'       => isset($this->fee_item_rules[$tmp['fee_item']]) ? $this->fee_item_rules[$tmp['fee_item']] : 0,
            'credential_number' => $tmp['financial_no'],
            'member'            => isset($tmp['member']) ? $tmp['member'] : '',
            'memo'              => isset($tmp['remarks']) ? $tmp['remarks'] : '',
            'unique_id'         => $data['unique_id'],
            'create_time'       => time(),
            'fee_type'          => $tmp['trade_type'],
            'fee_type_id'       => $res['fee_type_id'],
            'bill_type'         => $res['bill_type'],
            'charge_status'     => 1,
            'charge_time'       => time(),
            'monthly_id'        => 0,
            'monthly_item_id'   => 0,
            'monthly_status'    => 0,
            'crc32_order_bn'    => sprintf('%u', crc32($this->_getOrderBn($tmp))),
            'bill_bn'           => $mdlBill->gen_bill_bn(),
            'unconfirm_money'   => round(abs($tmp['amount']), 2),
        );

        if ($mdlBill->insert($base_sdf)) {
            kernel::single('finance_monthly_report_items')->dealBillMatchReport($base_sdf['bill_id']);
            return true;
        }

        return false;
    }

    protected function _parseMoney($value)
    {
        if ($value === '' || $value === null || $value === '--') {
            return 0;
        }
        $value = str_replace(',', '', trim((string) $value));

        return is_numeric($value) ? (float) $value : 0;
    }

    /**
     * 发生时间为 -- 时，从流水号中解析 YYYYMMDD
     */
    protected function _resolveTradeTime(array $row)
    {
        $time = isset($row['trade_time']) ? trim($row['trade_time']) : '';
        if ($time && $time !== '--' && strtotime($time)) {
            return date('Y-m-d H:i:s', strtotime($time));
        }

        foreach (array('financial_no', 'trade_no', 'out_trade_no') as $field) {
            if (empty($row[$field])) {
                continue;
            }
            if (preg_match('/(20\d{6})/', $row[$field], $match)) {
                $d = $match[1];
                return substr($d, 0, 4) . '-' . substr($d, 4, 2) . '-' . substr($d, 6, 2) . ' 00:00:00';
            }
        }

        return '';
    }
}
