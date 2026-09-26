<?php
/**
 * 快手资金表导入
 *
 * 字段映射：
 * 财务流水号/账务流水号 → 业务流水号
 * 关联业务单号         → 订单号（可为空）
 * 入账时间             → 账单时间
 * 财务方向/账务方向     → 收为正、支为负
 * 发生额（元）          → 金额（乘以方向）
 * 期末余额（元）        → 仅保留原始数据，不参与拆账
 * 业务类型             → 业务类型
 * 描述                 → 业务描述
 * 备注                 → 备注
 */
class financebase_data_bill_kuaishou_fund extends financebase_abstract_bill
{
    /** @var array 表头别名（兼容快手后台原生导出「账务*」命名） */
    protected function getTitleAliases()
    {
        return array(
            'financial_no'     => array('财务流水号', '账务流水号'),
            'related_order_no' => array('关联业务单号'),
            'trade_time'       => array('入账时间'),
            'direction'        => array('财务方向', '账务方向'),
            'amount_raw'       => array('发生额（元）', '发生额'),
            'balance_amount'   => array('期末余额（元）', '期末余额'),
            'trade_type'       => array('业务类型'),
            'trade_desc'       => array('描述'),
            'remarks'          => array('备注'),
        );
    }

    public function getTitle()
    {
        return array(
            'financial_no'     => '账务流水号',
            'related_order_no' => '关联业务单号',
            'trade_time'       => '入账时间',
            'direction'        => '账务方向',
            'amount_raw'       => '发生额（元）',
            'balance_amount'   => '期末余额（元）',
            'trade_type'       => '业务类型',
            'trade_desc'       => '描述',
            'remarks'          => '备注',
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
        if (in_array($first, array('财务流水号', '账务流水号'), true)) {
            return $res;
        }

        $tmpRow = $this->_mapRowToFields($row, $title);

        if (empty($tmpRow) || empty($tmpRow['financial_no'])) {
            return $res;
        }

        foreach (array('financial_no', 'trade_time', 'direction', 'trade_type') as $field) {
            if ($tmpRow[$field] === '') {
                $res['status'] = false;
                $res['msg']    = sprintf('LINE %d : %s 不能为空！', $offset, $this->ioTitle[$field]);
                return $res;
            }
        }

        $amount = abs($this->_parseMoney($tmpRow['amount_raw']));
        if ($amount == 0) {
            return $res;
        }

        $direction = $tmpRow['direction'];
        if (in_array($direction, array('支', '支出', '出'), true)) {
            $amount = -$amount;
        } elseif (!in_array($direction, array('收', '收入', '入'), true)) {
            $res['status'] = false;
            $res['msg']    = sprintf('LINE %d : %s 取值无效（%s），应为「收」或「支」！', $offset, $this->ioTitle['direction'], $direction);
            return $res;
        }

        $dateCheck = finance_io_bill_verify::isDate($tmpRow['trade_time']);
        if ($dateCheck['status'] === 'fail') {
            $res['status'] = false;
            $res['msg']    = sprintf('LINE %d : %s 时间(%s)格式错误！', $offset, $this->ioTitle['trade_time'], $tmpRow['trade_time']);
            return $res;
        }

        $priceCheck = finance_io_bill_verify::isPrice(sprintf('%.2f', abs($amount)));
        if ($priceCheck['status'] === 'fail') {
            $res['status'] = false;
            $res['msg']    = sprintf('LINE %d : %s 金额(%s)格式错误！', $offset, $this->ioTitle['amount_raw'], $tmpRow['amount_raw']);
            return $res;
        }

        $tmpRow['amount']           = $amount;
        $tmpRow['related_order_no'] = trim($tmpRow['related_order_no'], " \t\n\r\0\x0B");
        $tmpRow['order_bn']         = $this->_getOrderBn($tmpRow);

        $res['data'] = $tmpRow;
        return $res;
    }

    public function _filterData($data)
    {
        $new_data = array();

        $new_data['order_bn']      = $this->_getOrderBn($data);
        $new_data['trade_no']      = $data['financial_no'];
        $new_data['financial_no']  = $data['financial_no'];
        $new_data['out_trade_no']  = '';
        $new_data['trade_time']    = is_numeric($data['trade_time']) ? $data['trade_time'] : strtotime($data['trade_time']);
        $new_data['trade_type']    = $data['trade_type'];
        $new_data['money']         = $data['amount'];
        $new_data['member']        = '';
        $new_data['unique_id']     = md5(
            $data['financial_no'] . '-' . $data['trade_type'] . '-' . $data['amount'] . '-' . $new_data['trade_time']
        );
        $new_data['platform_type'] = 'kuaishou';
        $new_data['remarks']       = isset($data['remarks']) ? $data['remarks'] : '';

        return $new_data;
    }

    public function _getOrderBn($params)
    {
        if (!empty($params['related_order_no'])) {
            return trim($params['related_order_no'], " \t\n\r\0\x0B");
        }
        if (!empty($params['order_bn'])) {
            return $params['order_bn'];
        }

        return '';
    }

    public function getBillCategory($params)
    {
        if (!$this->rules) {
            $this->getRules('kuaishou');
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

        $plateTitle = array_map(function ($v) {
            return str_replace("\u{FEFF}", '', trim($v));
        }, $row[0]);

        $required = array('financial_no', 'trade_time', 'direction', 'amount_raw', 'trade_type');
        foreach ($required as $field) {
            if (!$this->_hasColumn($plateTitle, $field)) {
                $names = implode('/', $this->getTitleAliases()[$field]);
                return array(false, '文件模板错误：缺少必需列【' . $names . '】');
            }
        }

        return array(true, '文件模板匹配', $plateTitle);
    }

    public function getImportDateColunm($title = null)
    {
        $timeCol = array();
        foreach (array('入账时间') as $v) {
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

        $tmp['fee_obj']  = '快手';
        $tmp['fee_item'] = $bill_category;

        $res = $this->getBillType($tmp, $shop_id);
        if (!$res['status']) {
            return false;
        }

        if (!$data['shop_name']) {
            $data['shop_name'] = isset($this->shop_list[$data['shop_id']]) ? $this->shop_list[$data['shop_id']]['name'] : '';
        }

        $tradeTime = is_numeric($tmp['trade_time']) ? $tmp['trade_time'] : strtotime($tmp['trade_time']);

        $base_sdf = array(
            'order_bn'          => $this->_getOrderBn($tmp),
            'channel_id'        => $data['shop_id'],
            'channel_name'      => $data['shop_name'],
            'trade_time'        => $tradeTime,
            'fee_obj'           => $tmp['fee_obj'],
            'money'             => round($tmp['amount'], 2),
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
        );

        $base_sdf['crc32_order_bn']  = sprintf('%u', crc32($base_sdf['order_bn']));
        $base_sdf['bill_bn']         = $mdlBill->gen_bill_bn();
        $base_sdf['unconfirm_money'] = $base_sdf['money'];

        if ($mdlBill->insert($base_sdf)) {
            kernel::single('finance_monthly_report_items')->dealBillMatchReport($base_sdf['bill_id']);
            return true;
        }

        return false;
    }

    protected function _mapRowToFields($row, $title)
    {
        $aliases = $this->getTitleAliases();
        $tmpRow  = array_fill_keys(array_keys($aliases), '');

        foreach ($title as $k => $colName) {
            $colName = str_replace("\u{FEFF}", '', trim($colName));
            foreach ($aliases as $field => $names) {
                if (in_array($colName, $names, true)) {
                    $tmpRow[$field] = isset($row[$k]) ? trim((string) $row[$k], " \t\n\r\0\x0B'\"") : '';
                    break;
                }
            }
        }

        return $tmpRow;
    }

    protected function _hasColumn($plateTitle, $field)
    {
        foreach ($this->getTitleAliases()[$field] as $name) {
            if (array_search($name, $plateTitle, true) !== false) {
                return true;
            }
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
}
