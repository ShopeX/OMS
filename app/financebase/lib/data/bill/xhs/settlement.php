<?php
/**
 * 小红书资金结算单导入（货款明细）
 *
 * 模板工作表：货款明细
 * 列：创建时间、交易类型描述、收入（元）、支出（元）、账户余额（元）、业务单号、备注
 */
class financebase_data_bill_xhs_settlement extends financebase_abstract_bill
{
    public $sheet_name = '货款明细';

    public $column_num = 7;

    public function getTitle()
    {
        return array(
            'trade_time'     => '创建时间',
            'trade_type'     => '交易类型描述',
            'income_amount'  => '收入（元）',
            'outcome_amount' => '支出（元）',
            'balance_amount' => '账户余额（元）',
            'financial_no'   => '业务单号',
            'remarks'        => '备注',
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
        if ($first === '创建时间') {
            return $res;
        }

        $tmpRow = array();
        foreach ($title as $k => $t) {
            $field = array_search($t, $this->getTitle(), true);
            if ($field !== false) {
                $tmpRow[$field] = isset($row[$k]) ? trim($row[$k]) : '';
            }
        }

        if (empty($tmpRow)) {
            return $res;
        }

        foreach (array('financial_no', 'trade_time', 'trade_type') as $field) {
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
            $res['msg']    = sprintf('LINE %d : 收入（元）与支出（元）不能同时为空！', $offset);
            return $res;
        }

        $dateCheck = finance_io_bill_verify::isDate($tmpRow['trade_time']);
        if ($dateCheck['status'] === 'fail') {
            $res['status'] = false;
            $res['msg']    = sprintf('LINE %d : %s 时间(%s)格式错误！', $offset, $this->ioTitle['trade_time'], $tmpRow['trade_time']);
            return $res;
        }

        if ($income > 0) {
            $priceCheck = finance_io_bill_verify::isPrice((string) $income);
            if ($priceCheck['status'] === 'fail') {
                $res['status'] = false;
                $res['msg']    = sprintf('LINE %d : %s 金额(%s)格式错误！', $offset, $this->ioTitle['income_amount'], $tmpRow['income_amount']);
                return $res;
            }
        }
        if ($outcome > 0) {
            $priceCheck = finance_io_bill_verify::isPrice((string) $outcome);
            if ($priceCheck['status'] === 'fail') {
                $res['status'] = false;
                $res['msg']    = sprintf('LINE %d : %s 金额(%s)格式错误！', $offset, $this->ioTitle['outcome_amount'], $tmpRow['outcome_amount']);
                return $res;
            }
        }

        $tmpRow['income_amount']  = $income > 0 ? sprintf('%.2f', $income) : '';
        $tmpRow['outcome_amount'] = $outcome > 0 ? sprintf('%.2f', $outcome) : '';
        $tmpRow['amount']         = $income > 0 ? $income : -abs($outcome);
        $tmpRow['member']         = '';

        $res['data'] = $tmpRow;
        return $res;
    }

    public function _filterData($data)
    {
        $new_data = array();

        $new_data['order_bn']     = $this->_getOrderBn($data);
        $new_data['trade_no']     = $data['financial_no'];
        $new_data['financial_no'] = $data['financial_no'];
        $new_data['out_trade_no'] = '';
        $new_data['trade_time']   = strtotime($data['trade_time']);
        $new_data['trade_type']   = $data['trade_type'];
        $new_data['money']        = $data['amount'];
        $new_data['member']       = '';
        $new_data['unique_id']    = md5($data['financial_no'] . '-' . $data['amount'] . '-' . $data['trade_type'] . '-' . $data['trade_time']);
        $new_data['platform_type'] = 'xhs';
        $new_data['remarks']      = $data['remarks'];

        return $new_data;
    }

    public function _getOrderBn($params)
    {
        if (!empty($params['financial_no'])) {
            return $params['financial_no'];
        }
        if (!empty($params['remarks']) && preg_match('/订单号([A-Za-z0-9]+)/', $params['remarks'], $match)) {
            return $match[1];
        }

        return '';
    }

    public function getBillCategory($params)
    {
        if (!$this->rules) {
            $this->getRules('xhs');
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
        $ioType = kernel::single('financebase_io_' . $file_type);
        $sheet  = isset($this->sheet_name) ? $this->sheet_name : 0;
        $row    = $ioType->getData($file_name, $sheet, 5);

        $title = array_values($this->getTitle());
        if (empty($row[0])) {
            return array(false, '文件模板错误：未读取到表头');
        }

        $plateTitle = $row[0];
        $plateTitle[0] = str_replace("\u{FEFF}", '', $plateTitle[0]);
        foreach ($title as $v) {
            if (array_search($v, $plateTitle, true) === false) {
                return array(false, '文件模板错误：列【' . $v . '】未包含在' . implode('、', $plateTitle));
            }
        }

        return array(true, '文件模板匹配', $plateTitle);
    }

    public function getImportDateColunm($title = null)
    {
        $timeColumn = array('创建时间');
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

        $tmp['fee_obj']  = '小红书';
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
            'member'            => '',
            'memo'              => $tmp['remarks'],
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
        if ($value === '' || $value === null) {
            return 0;
        }
        $value = str_replace(',', '', trim($value));

        return is_numeric($value) ? (float) $value : 0;
    }
}
