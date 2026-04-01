<?php
/**
 * 处理喵速达账单导入
 *
 * @author AI Assistant
 * @version 1.0
 */
class financebase_data_bill_miaosuda extends financebase_abstract_bill
{
    public $order_bn_prefix = '';
    public $column_num = 34; // 喵速达账单有34列
    public $ioTitle = array();
    public $ioTitleKey = array();
    public $verified_data = array();

    /**
     * 处理数据
     */
    public function getSdf($row, $offset = 1, $title)
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

        if ($this->column_num <= count($row) && $row[0] != '对账单编码') {
            
            $tmp = array_combine($titleKey, $row);
            
            // 验证必填字段
            foreach ($tmp as $k => $v) {
                // 必填字段验证（核心字段）
                if (in_array($k, array('bill_code', 'main_order_no', 'business_time', 'amount'))) {
                    if (!$v && $v !== '0' && $v !== 0) {
                        $res['status'] = false;
                        $res['msg'] = sprintf("LINE %d : %s 不能为空！", $offset, $this->ioTitle[$k]);
                        return $res;
                    }
                }

                // 时间格式验证
                if (in_array($k, array('bill_create_time', 'business_time', 'trade_pay_time'))) {
                    if ($v && trim($v) !== '') {
                        $result = finance_io_bill_verify::isDate($v);
                        if ($result['status'] == 'fail' || strtotime($v) <= 0) {
                            $res['status'] = false;
                            $res['msg'] = sprintf("LINE %d : %s 时间格式错误！取值是：%s", $offset, $this->ioTitle[$k], $v);
                            return $res;
                        }
                    }
                }

                // 金额格式验证（喵速达支持多位小数，如未税金额可能有6位小数）
                if (in_array($k, array('amount', 'amount_without_tax', 'tax_amount', 'unit_price_with_tax'))) {
                    if ($v && trim($v) !== '') {
                        // 自定义金额验证：支持多位小数的正负数
                        if (!preg_match('/^-?\d+(\.\d+)?$/', $v)) {
                            $res['status'] = false;
                            $res['msg'] = sprintf("LINE %d : %s 金额格式错误！取值是：%s", $offset, $this->ioTitle[$k], $v);
                            return $res;
                        }
                    }
                }
                
                // 处理订单号（去除Excel的="xxx"格式）
                if (in_array($k, array('bill_code', 'main_order_no', 'sub_order_no', 'purchase_order_no', 'financial_no', 'order_no'))) {
                    $tmp[$k] = trim($v, '=\"');
                }
            }

            $res['data'] = $tmp;
        }

        return $res;
    }

    /**
     * 获取字段定义
     * 
     * 根据喵速达Excel模板的实际列名定义（共34列）
     */
    public function getTitle()
    {
        $title = array(
            'bill_code'                => '对账单编码',           // S2025102032246311035
            'bill_create_time'         => '账单创建时间',         // 2025-10-20 14:00:17
            'main_order_no'            => '业务主单据编码',       // PO036125100821303611874066166
            'sub_order_no'             => '业务子单据编码',       // IO25100821303611874066166252507
            'business_time'            => '业务时间',            // 2025-10-08 21:30:36
            'source_doc_type'          => '来源单据类型',         // 采购入库
            'fee_code'                 => '费用项编码',          // HM_PAYMENT_GOODS
            'trade_type'               => '结算方式',            // 货款
            'supplier_code'            => '供应商编码',          // 488675267
            'supplier_name'            => '供应商名称',          // 美诺电器有限公司冰洗厨热商家仓配
            'currency'                 => '结算币种',            // CNY
            'amount'                   => '含税金额',            // 11770.56 (主要金额字段)
            'amount_without_tax'       => '未税金额',            // 10416.424779
            'tax_amount'               => '税额',               // 1354.135221
            'tax_rate'                 => '税率',               // 0.13
            'goods_code'               => '货品编码',            // 898392380713
            'goods_name'               => '货品名称',            // TCH797WP C 莲花白
            'billing_quantity'         => '计费数量',            // 1.000000
            'unit_price_with_tax'      => '含税单价',            // 11770.560000
            'is_recalculate'           => '是否重算',            // 计算数据
            'financial_no'             => '唯一编码',            // KLZYS202510086465904920660
            'purchase_order_no'        => '采购单编码',          // PO036125100821303611874066166
            'settlement_mode'          => '结算模式',            // PURCHASE
            'industry_name'            => '行业名称',            // 数码家电
            'order_no'                 => '二段LP单号',          // LP00738139484471
            'trade_sub_order_no'       => '交易子单编码',        // 2591505374260069772
            'trade_main_order_no'      => '交易主单编码',        // 2591505374260069772
            'refund_quantity'          => '原子货品退款数量',     // 空
            'refund_amount'            => '原子货品消费者退款金额', // 空
            'goods_unit'               => '货品单位',            // 空
            'trade_pay_time'           => '交易支付时间',         // 空
            'refund_order_no'          => '退款单号',            // 空
            'sales_quantity'           => '销售数量（退款数量）',  // 1.000000
            'billing_formula'          => '计费公式',            // 长文本
        );

        return $title;
    }

    /**
     * 获取订单号
     */
    public function _getOrderBn($params)
    {
        // 直接使用二段LP单号
        if (isset($params['order_no']) && $params['order_no']) {
            return $params['order_no'];
        }
        
        return '';
    }

    /**
     * 获取具体类别
     */
    public function getBillCategory($params)
    {
        if (!$this->rules) {
            $this->getRules('miaosuda');
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
     * 检查文件是否有效
     */
    public function checkFile($file_name, $file_type)
    {
        $ioType = kernel::single('financebase_io_' . $file_type);
        $row = $ioType->getData($file_name, 0, 3);
        
        // 检查第一行是否包含"对账单编码"
        if (!isset($row[0][0]) || $row[0][0] != '对账单编码') {
            return array(false, '文件模板错误：第一列应该是"对账单编码"');
        }

        $title = array_values($this->getTitle());
        sort($title);

        $fileTitle = $row[0];
        sort($fileTitle);

        if (!array_diff($fileTitle, $title)) {
            return array(true, '文件模板匹配', $row[0]);
        }

        return array(false, '文件模板错误：列名不匹配');
    }

    /**
     * 同步到对账表
     */
    public function syncToBill($data, $bill_category = '')
    {
        $data['content'] = json_decode(stripslashes($data['content']), 1);
        if (!$data['content']) {
            return false;
        }

        $tmp = $data['content'];
        $shop_id = $data['shop_id'];

        $mdlBill = app::get('finance')->model('bill');

        $tmp['fee_obj'] = '喵速达';
        $tmp['fee_item'] = $bill_category;

        $res = $this->getBillType($tmp, $shop_id);
        if (!$res['status']) {
            return false;
        }

        if (!$data['shop_name']) {
            $data['shop_name'] = isset($this->shop_list[$data['shop_id']]) ? $this->shop_list[$data['shop_id']]['name'] : '';
        }

        // 使用含税金额作为主要金额（保留正负，负数表示支出/退款）
        $amount = isset($tmp['amount']) ? floatval($tmp['amount']) : 0;

        $base_sdf = array(
            'order_bn'          => $this->_getOrderBn($tmp),
            'channel_id'        => $data['shop_id'],
            'channel_name'      => $data['shop_name'],
            'trade_time'        => strtotime($tmp['business_time']), // 使用业务时间
            'fee_obj'           => $tmp['fee_obj'],
            'money'             => round($amount, 2), // 保留正负
            'fee_item'          => $tmp['fee_item'],
            'fee_item_id'       => isset($this->fee_item_rules[$tmp['fee_item']]) ? $this->fee_item_rules[$tmp['fee_item']] : 0,
            'credential_number' => isset($tmp['financial_no']) ? $tmp['financial_no'] : '', // 使用唯一编码（财务流水号）
            'member'            => isset($tmp['supplier_name']) ? $tmp['supplier_name'] : '',
            'memo'              => isset($tmp['source_doc_type']) ? $tmp['source_doc_type'] : '', // 来源单据类型
            'unique_id'         => $data['unique_id'],
            'create_time'       => time(),
            'fee_type'          => isset($tmp['trade_type']) ? $tmp['trade_type'] : '', // 结算方式
            'fee_type_id'       => $res['fee_type_id'],
            'bill_type'         => $res['bill_type'],
            'charge_status'     => 1,
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
     * 提取关键字段
     */
    public function _filterData($data)
    {
        $new_data = array();

        $new_data['order_bn'] = $this->_getOrderBn($data);
        $new_data['trade_no'] = isset($data['main_order_no']) ? $data['main_order_no'] : ''; // 业务主单据编码
        $new_data['financial_no'] = isset($data['financial_no']) ? $data['financial_no'] : ''; // 唯一编码
        $new_data['out_trade_no'] = isset($data['order_no']) ? $data['order_no'] : ''; // 二段LP单号
        $new_data['trade_time'] = isset($data['business_time']) ? strtotime($data['business_time']) : 0;
        $new_data['trade_type'] = isset($data['trade_type']) ? $data['trade_type'] : ''; // 结算方式
        $new_data['money'] = isset($data['amount']) ? floatval($data['amount']) : 0; // 含税金额（保留正负）
        $new_data['member'] = isset($data['supplier_name']) ? $data['supplier_name'] : '';
        
        // 直接使用唯一编码作为唯一标识
        $new_data['unique_id'] = isset($data['financial_no']) ? $data['financial_no'] : '';

        $new_data['platform_type'] = 'miaosuda';
        $new_data['remarks'] = isset($data['source_doc_type']) ? $data['source_doc_type'] : ''; // 来源单据类型

        return $new_data;
    }

    /**
     * 获取导入日期列
     */
    public function getImportDateColunm($title = null)
    {
        // 找到时间列的位置
        $timeColumn = array('账单创建时间', '业务时间', '交易支付时间');
        $timeCol = array();
        
        if ($title) {
            foreach ($timeColumn as $v) {
                $k = array_search($v, $title);
                if ($k !== false) {
                    $timeCol[] = $k + 1;
                }
            }
        }
        
        $timezone = defined('DEFAULT_TIMEZONE') ? DEFAULT_TIMEZONE : 0;
        return array('column' => $timeCol, 'time_diff' => $timezone * 3600);
    }
    
    /**
     * 批量转换数据（可选，用于特殊数据处理）
     */
    public function batchTransferData($data, $title) {
        // 如果需要对整批数据进行预处理，可以在这里实现
        // 例如：处理金额格式、清理特殊字符等
        return $data;
    }
}
