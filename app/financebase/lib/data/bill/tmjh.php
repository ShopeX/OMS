<?php
/**
 * 天猫聚合支付账单导入
 *
 * 字段映射（与原生导出对齐）：
 * 入帐日期 + 入帐时间 → 账单日期（优先入帐时间）
 * 支付流水号           → 流水号
 * 主订单id             → 订单号
 * 入帐类型             → 业务类型
 * 收入金额 / 支出金额  → 金额（收入为正、支出为负）
 * 业务描述             → 业务描述
 * 备注                 → 备注
 *
 * 表头兼容：账/帐、金额列是否带「(元)」
 */
class financebase_data_bill_tmjh extends financebase_abstract_bill
{
    public $column_num = 12;

    public function getTitle()
    {
        return array(
            'shop_nick'        => '商家昵称',
            'bill_date'        => '入帐日期',
            'trade_time'       => '入帐时间',
            'financial_no'     => '支付流水号',
            'main_order_id'    => '主订单id',
            'sub_order_id'     => '子订单id',
            'trade_type'       => '入帐类型',
            'income_amount'    => '收入金额(元)',
            'outcome_amount'   => '支出金额(元)',
            'trade_desc'       => '业务描述',
            'remarks'          => '备注',
            'channel'          => '收/付渠道',
            'data_create_time' => '数据创建时间',
            'data_modify_time' => '数据修改时间',
        );
    }

    /**
     * 表头别名（原生导出用「帐」、模板可能用「账」；金额列可能带/不带「(元)」）
     */
    protected function getTitleAliases()
    {
        return array(
            'shop_nick'        => array('商家昵称'),
            'bill_date'        => array('入帐日期', '入账日期'),
            'trade_time'       => array('入帐时间', '入账时间'),
            'financial_no'     => array('支付流水号'),
            'main_order_id'    => array('主订单id'),
            'sub_order_id'     => array('子订单id'),
            'trade_type'       => array('入帐类型', '入账类型'),
            'income_amount'    => array('收入金额(元)', '收入金额'),
            'outcome_amount'   => array('支出金额(元)', '支出金额'),
            'trade_desc'       => array('业务描述'),
            'remarks'          => array('备注'),
            'channel'          => array('收/付渠道'),
            'data_create_time' => array('数据创建时间'),
            'data_modify_time' => array('数据修改时间'),
        );
    }

    protected function getRequiredFields()
    {
        return array(
            'shop_nick',
            'bill_date',
            'trade_time',
            'financial_no',
            'main_order_id',
            'sub_order_id',
            'trade_type',
            'income_amount',
            'outcome_amount',
            'trade_desc',
            'remarks',
            'channel',
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
        if ($first === '商家昵称') {
            return $res;
        }

        $tmpRow = $this->_mapRowToFields($row, $title);
        if (empty($tmpRow) || empty($tmpRow['financial_no'])) {
            return $res;
        }

        foreach (array('financial_no', 'trade_type') as $field) {
            if ($tmpRow[$field] === '') {
                $res['status'] = false;
                $res['msg']    = sprintf('LINE %d : %s 不能为空！', $offset, $this->ioTitle[$field]);
                return $res;
            }
        }

        $tradeTime = $this->_resolveTradeTime($tmpRow);
        if ($tradeTime === false) {
            $res['status'] = false;
            $res['msg']    = sprintf(
                'LINE %d : 入帐时间/入帐日期格式错误！取值是：%s / %s',
                $offset,
                isset($tmpRow['trade_time']) ? $tmpRow['trade_time'] : '',
                isset($tmpRow['bill_date']) ? $tmpRow['bill_date'] : ''
            );
            return $res;
        }
        $tmpRow['trade_time'] = $tradeTime;

        $income  = $this->_parseMoney(isset($tmpRow['income_amount']) ? $tmpRow['income_amount'] : '');
        $outcome = $this->_parseMoney(isset($tmpRow['outcome_amount']) ? $tmpRow['outcome_amount'] : '');
        if ($income == 0 && $outcome == 0) {
            $res['status'] = false;
            $res['msg']    = sprintf('LINE %d : 收入金额与支出金额不能同时为空！', $offset);
            return $res;
        }

        if ($income != 0) {
            $priceCheck = finance_io_bill_verify::isPrice((string) abs($income));
            if ($priceCheck['status'] === 'fail') {
                $res['status'] = false;
                $res['msg']    = sprintf('LINE %d : %s 金额(%s)格式错误！', $offset, $this->ioTitle['income_amount'], $tmpRow['income_amount']);
                return $res;
            }
        }
        if ($outcome != 0) {
            $priceCheck = finance_io_bill_verify::isPrice((string) abs($outcome));
            if ($priceCheck['status'] === 'fail') {
                $res['status'] = false;
                $res['msg']    = sprintf('LINE %d : %s 金额(%s)格式错误！', $offset, $this->ioTitle['outcome_amount'], $tmpRow['outcome_amount']);
                return $res;
            }
        }

        $tmpRow['income_amount']  = $income != 0 ? sprintf('%.2f', abs($income)) : '';
        $tmpRow['outcome_amount'] = $outcome != 0 ? sprintf('%.2f', abs($outcome)) : '';
        $tmpRow['amount']         = $income != 0 ? abs($income) : -abs($outcome);
        $tmpRow['main_order_id']  = $this->_cleanId(isset($tmpRow['main_order_id']) ? $tmpRow['main_order_id'] : '');
        $tmpRow['sub_order_id']   = $this->_cleanId(isset($tmpRow['sub_order_id']) ? $tmpRow['sub_order_id'] : '');
        $tmpRow['financial_no']   = $this->_cleanId($tmpRow['financial_no']);
        $tmpRow['order_bn']       = $this->_getOrderBn($tmpRow);
        $tmpRow['member']         = '';

        $res['data'] = $tmpRow;
        return $res;
    }

    public function _filterData($data)
    {
        $new_data = array();

        $new_data['order_bn']      = $this->_getOrderBn($data);
        $new_data['trade_no']      = $data['financial_no'];
        $new_data['financial_no']  = $data['financial_no'];
        $new_data['out_trade_no']  = !empty($data['sub_order_id']) ? $data['sub_order_id'] : '';
        $new_data['trade_time']    = is_numeric($data['trade_time']) ? $data['trade_time'] : strtotime($data['trade_time']);
        $new_data['trade_type']    = $data['trade_type'];
        $new_data['money']         = $data['amount'];
        $new_data['member']        = '';
        $new_data['unique_id']     = md5(
            $data['financial_no'] . '-' .
            $data['trade_type'] . '-' .
            $data['amount'] . '-' .
            $new_data['trade_time'] . '-' .
            (isset($data['main_order_id']) ? $data['main_order_id'] : '') . '-' .
            (isset($data['sub_order_id']) ? $data['sub_order_id'] : '')
        );
        $new_data['platform_type'] = 'tmjh';
        $new_data['remarks']       = isset($data['remarks']) ? $data['remarks'] : '';

        return $new_data;
    }

    public function _getOrderBn($params)
    {
        return !empty($params['main_order_id']) ? $params['main_order_id'] : '';
    }

    public function getBillCategory($params)
    {
        if (!$this->rules) {
            $this->getRules('tmjh');
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

        $plateTitle = array();
        foreach ($row[0] as $v) {
            $plateTitle[] = str_replace("\u{FEFF}", '', trim((string) $v));
        }

        foreach ($this->getRequiredFields() as $field) {
            if (!$this->_hasColumn($plateTitle, $field)) {
                $names = implode('/', $this->getTitleAliases()[$field]);
                return array(false, '文件模板错误：缺少必需列【' . $names . '】，当前表头：' . implode('、', $plateTitle));
            }
        }

        return array(true, '文件模板匹配', $plateTitle);
    }

    public function getImportDateColunm($title = null)
    {
        $timeColumn = array('入帐时间', '入账时间', '入帐日期', '入账日期');
        $timeCol    = array();
        foreach ($timeColumn as $v) {
            if (($k = array_search($v, (array) $title, true)) !== false) {
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

        $tmp['fee_obj']  = '天猫聚合支付';
        $tmp['fee_item'] = $bill_category;

        $res = $this->getBillType($tmp, $shop_id);
        if (!$res['status']) {
            return false;
        }

        if (!$data['shop_name']) {
            $data['shop_name'] = isset($this->shop_list[$data['shop_id']]) ? $this->shop_list[$data['shop_id']]['name'] : '';
        }

        $amount = isset($tmp['amount']) ? $tmp['amount'] : 0;
        $base_sdf = array(
            'order_bn'          => $this->_getOrderBn($tmp),
            'channel_id'        => $data['shop_id'],
            'channel_name'      => $data['shop_name'],
            'trade_time'        => is_numeric($tmp['trade_time']) ? $tmp['trade_time'] : strtotime($tmp['trade_time']),
            'fee_obj'           => $tmp['fee_obj'],
            'money'             => round(abs($amount), 2),
            'fee_item'          => $tmp['fee_item'],
            'fee_item_id'       => isset($this->fee_item_rules[$tmp['fee_item']]) ? $this->fee_item_rules[$tmp['fee_item']] : 0,
            'credential_number' => $tmp['financial_no'],
            'member'            => '',
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
            'unconfirm_money'   => round(abs($amount), 2),
        );

        if ($mdlBill->insert($base_sdf)) {
            kernel::single('finance_monthly_report_items')->dealBillMatchReport($base_sdf['bill_id']);
            return true;
        }

        return false;
    }

    protected function _mapRowToFields($row, $title)
    {
        $tmpRow = array();
        foreach ($title as $k => $t) {
            $t = str_replace("\u{FEFF}", '', trim((string) $t));
            $field = $this->_resolveFieldByTitle($t);
            if ($field !== false) {
                $tmpRow[$field] = isset($row[$k]) ? trim($row[$k]) : '';
            }
        }

        return $tmpRow;
    }

    protected function _resolveFieldByTitle($title)
    {
        foreach ($this->getTitleAliases() as $field => $aliases) {
            if (in_array($title, $aliases, true)) {
                return $field;
            }
        }

        return false;
    }

    protected function _hasColumn($plateTitle, $field)
    {
        $aliases = isset($this->getTitleAliases()[$field]) ? $this->getTitleAliases()[$field] : array();
        foreach ($aliases as $name) {
            if (array_search($name, $plateTitle, true) !== false) {
                return true;
            }
        }

        return false;
    }

    protected function _resolveTradeTime($tmpRow)
    {
        if (!empty($tmpRow['trade_time'])) {
            $v = trim($tmpRow['trade_time']);
            if (is_numeric($v) && strlen($v) >= 10) {
                // Excel 序列日期或时间戳
                $ts = strlen($v) > 11 ? intval($v / 1000) : intval($v);
                if ($ts > 0) {
                    return date('Y-m-d H:i:s', $ts);
                }
            }
            $dateCheck = finance_io_bill_verify::isDate($v);
            if ($dateCheck['status'] !== 'fail' && strtotime($v) > 0) {
                return date('Y-m-d H:i:s', strtotime($v));
            }
        }

        if (!empty($tmpRow['bill_date'])) {
            $d = preg_replace('/\D/', '', (string) $tmpRow['bill_date']);
            if (strlen($d) === 8) {
                $formatted = substr($d, 0, 4) . '-' . substr($d, 4, 2) . '-' . substr($d, 6, 2) . ' 00:00:00';
                if (strtotime($formatted) > 0) {
                    return $formatted;
                }
            }
            $dateCheck = finance_io_bill_verify::isDate($tmpRow['bill_date']);
            if ($dateCheck['status'] !== 'fail' && strtotime($tmpRow['bill_date']) > 0) {
                return date('Y-m-d H:i:s', strtotime($tmpRow['bill_date']));
            }
        }

        return false;
    }

    protected function _parseMoney($value)
    {
        if ($value === '' || $value === null) {
            return 0;
        }
        $value = str_replace(array(',', ' '), '', trim($value));

        return is_numeric($value) ? (float) $value : 0;
    }

    protected function _cleanId($value)
    {
        $value = trim((string) $value);
        $value = trim($value, "=\"'");
        // 去掉科学计数法展示残留
        if (preg_match('/^\d+\.0+$/', $value)) {
            $value = substr($value, 0, strpos($value, '.'));
        }

        return $value;
    }
}
