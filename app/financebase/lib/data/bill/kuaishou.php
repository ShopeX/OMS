<?php
/**
 * 快手结算单导入（独立实现，支持商家后台原生导出格式）
 *
 * 一行 = 订单号 + 商品ID 行项目，按金额列拆成多条收支流水。
 */

class financebase_data_bill_kuaishou extends financebase_abstract_bill
{
    /** @var array 拆行金额列（不含合计收入/合计支出/实际结算金额） */
    public $column_to_row = array(
        'order_pay',
        'gov_subsidy',
        'pay_marketing_subsidy',
        'platform_subsidy',
        'merchant_subsidy',
        'kol_subsidy',
        'order_refund',
        'tech_service_fee',
        'presale_tech_fee',
        'kol_commission',
        'leader_commission',
        'kz_commission',
        'service_commission',
        'other_fee',
        'freight',
    );

    /** @var array 支出列（拆行后金额取负） */
    public $column_expense = array(
        'order_refund',
        'tech_service_fee',
        'presale_tech_fee',
        'kol_commission',
        'leader_commission',
        'kz_commission',
        'service_commission',
        'other_fee',
        'freight',
    );

    /** @var array checkFile 必需列 */
    public $required_columns = array(
        '商家ID',
        '订单号',
        '商品ID',
        '实际结算时间',
        '订单实付(元)',
        '订单退款(元)',
        '技术服务费(元)',
    );

    public function batchTransferData($data, $title)
    {
        if (!$this->ioTitle) {
            $this->ioTitle = $this->getTitle();
        }

        $titleKey = array();
        foreach ($title as $k => $t) {
            $t = str_replace("\u{FEFF}", '', trim($t));
            $titleKey[$k] = array_search($t, $this->ioTitle, true);
        }

        $reData = array();
        foreach ($data as $row) {
            $row = array_map('trim', $row);
            if (!$row || !isset($row[0])) {
                continue;
            }
            $first = str_replace("\u{FEFF}", '', $row[0]);
            if ($first === '商家ID' || $first === '订单号') {
                continue;
            }

            $tmpRow = array();
            foreach ($row as $k => $v) {
                if ($titleKey[$k] !== false) {
                    $tmpRow[$titleKey[$k]] = $v;
                }
            }

            if (empty($tmpRow['order_no']) || empty($tmpRow['goods_bn']) || empty($tmpRow['trade_time'])) {
                continue;
            }

            foreach ($this->column_to_row as $field) {
                if (!$this->isNonZeroAmount($tmpRow, $field)) {
                    continue;
                }
                $tmp = $tmpRow;
                $amount = round(floatval($tmpRow[$field]), 2);
                if (in_array($field, $this->column_expense, true)) {
                    $amount = -abs($amount);
                } else {
                    $amount = abs($amount);
                }
                $tmp['amount'] = $amount;
                $tmp['trade_type'] = $this->ioTitle[$field];
                $reData[] = $tmp;
            }
        }

        return $reData;
    }

    public function getSdf($row, $offset = 1, $title)
    {
        $res = array('status' => true, 'data' => array(), 'msg' => '');
        $tmp = array();

        foreach ($row as $k => $v) {
            $tmp[$k] = is_string($v) ? trim($v, "'\"") : $v;

            if (in_array($k, array('order_no', 'goods_bn', 'trade_type'), true) && $v === '') {
                $res['status'] = false;
                $res['msg'] = sprintf('LINE %d : %s 不能为空！', $offset, isset($this->ioTitle[$k]) ? $this->ioTitle[$k] : $k);
                return $res;
            }

            if ($k === 'trade_time') {
                $result = finance_io_bill_verify::isDate($v);
                if ($result['status'] == 'fail') {
                    $res['status'] = false;
                    $res['msg'] = sprintf('LINE %d : %s 时间(%s)格式错误！', $offset, $this->ioTitle[$k], $v);
                    return $res;
                }
            }

            if ($k === 'amount') {
                $result = finance_io_bill_verify::isPrice($v);
                if ($result['status'] == 'fail') {
                    $res['status'] = false;
                    $res['msg'] = sprintf('LINE %d : 订单 %s 金额(%s)格式错误！', $offset, $row['order_no'], $v);
                    return $res;
                }
            }
        }

        $res['data'] = $tmp;
        return $res;
    }

    public function getTitle()
    {
        return array(
            'merchant_id'           => '商家ID',
            'order_no'              => '订单号',
            'goods_bn'              => '商品ID',
            'goods_name'            => '商品名称',
            'goods_num'             => '商品数量',
            'order_create_time'     => '订单创建时间',
            'order_pay'             => '订单实付(元)',
            'gov_subsidy'           => '政府补贴',
            'pay_marketing_subsidy' => '支付营销补贴',
            'platform_subsidy'      => '平台补贴',
            'merchant_subsidy'      => '商家补贴(元)',
            'kol_subsidy'           => '达人补贴',
            'total_income'          => '合计收入(元)',
            'order_refund'          => '订单退款(元)',
            'tech_service_fee'      => '技术服务费(元)',
            'presale_tech_fee'      => '预售增收技术服务费（元）',
            'kol_commission'        => '达人佣金(元)',
            'leader_commission'     => '团长佣金(元)',
            'kz_commission'         => '快赚客佣金(元)',
            'service_commission'    => '服务商佣金(元)',
            'other_fee'             => '其他收费',
            'total_expense'         => '合计支出(元)',
            'settlement_amount'     => '实际结算金额(元)',
            'trade_time'            => '实际结算时间',
            'remarks'               => '备注',
            'freight'               => '运费',
        );
    }

    public function _getOrderBn($params)
    {
        return $params['order_no'];
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
        $ioType = kernel::single('financebase_io_' . $file_type);
        $row = $ioType->getData($file_name, 0, 1);
        if (empty($row[0])) {
            return array(false, '文件为空或无法读取表头');
        }

        $_title = $row[0];
        $plateTitle = array_map(function ($v) {
            return str_replace("\u{FEFF}", '', trim($v));
        }, $_title);

        foreach ($this->required_columns as $col) {
            if (array_search($col, $plateTitle, true) === false) {
                return array(false, '文件模板错误：缺少必需列【' . $col . '】');
            }
        }

        return array(true, '文件模板匹配', $_title);
    }

    public function syncToBill($data, $bill_category = '')
    {
        $data['content'] = json_decode(stripslashes($data['content']), 1);
        if (!$data['content']) {
            return false;
        }

        $tmp = $data['content'];
        $shop_id = $data['shop_id'];

        $mdlBill = app::get('finance')->model('bill');

        $tmp['fee_obj'] = '快手';
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
            'trade_time'        => is_numeric($tmp['trade_time']) ? $tmp['trade_time'] : strtotime($tmp['trade_time']),
            'fee_obj'           => $tmp['fee_obj'],
            'money'             => round($tmp['amount'], 2),
            'fee_item'          => $tmp['fee_item'],
            'fee_item_id'       => isset($this->fee_item_rules[$tmp['fee_item']]) ? $this->fee_item_rules[$tmp['fee_item']] : 0,
            'credential_number' => $tmp['goods_bn'],
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

        $base_sdf['crc32_order_bn'] = sprintf('%u', crc32($base_sdf['order_bn']));
        $base_sdf['bill_bn'] = $mdlBill->gen_bill_bn();
        $base_sdf['unconfirm_money'] = $base_sdf['money'];

        if ($mdlBill->insert($base_sdf)) {
            kernel::single('finance_monthly_report_items')->dealBillMatchReport($base_sdf['bill_id']);
            return true;
        }

        return false;
    }

    public function _filterData($data)
    {
        $new_data = array();

        $new_data['order_bn'] = $this->_getOrderBn($data);
        $new_data['trade_no'] = $data['order_no'] . '-' . $data['goods_bn'];
        $new_data['financial_no'] = $data['goods_bn'];
        $new_data['out_trade_no'] = '';
        $new_data['trade_time'] = strtotime($data['trade_time']);
        $new_data['trade_type'] = $data['trade_type'];
        $new_data['money'] = $data['amount'];
        $new_data['member'] = '';
        $new_data['unique_id'] = md5(
            $data['order_no'] . '-' . $data['goods_bn'] . '-' . $data['trade_type'] . '-' . $data['trade_time'] . '-' . $data['amount']
        );
        $new_data['platform_type'] = 'kuaishou';
        $new_data['remarks'] = $this->buildRemarks($data);

        return $new_data;
    }

    public function getImportDateColunm($title = null)
    {
        $timeColumn = array('实际结算时间', '订单创建时间');
        $timeCol = array();
        foreach ($timeColumn as $v) {
            $k = array_search($v, $title, true);
            if ($k !== false) {
                $timeCol[] = $k + 1;
            }
        }
        $timezone = defined('DEFAULT_TIMEZONE') ? DEFAULT_TIMEZONE : 0;
        return array('column' => $timeCol, 'time_diff' => $timezone * 3600);
    }

    protected function isNonZeroAmount($row, $field)
    {
        if (!isset($row[$field]) || $row[$field] === '') {
            return false;
        }
        return abs(round(floatval($row[$field]), 2)) > 0;
    }

    protected function buildRemarks($data)
    {
        $parts = array();
        if (!empty($data['goods_name'])) {
            $parts[] = $data['goods_name'];
        }
        if (!empty($data['goods_num'])) {
            $parts[] = '数量:' . $data['goods_num'];
        }
        if (!empty($data['remarks'])) {
            $parts[] = $data['remarks'];
        }
        return implode(' ', $parts);
    }
}
