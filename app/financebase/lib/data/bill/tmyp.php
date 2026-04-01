<?php
/**
 * 天猫优品账单导入处理类
 * 处理第一个工作表
 * 
 * @author AI Assistant
 * @version 1.0
 */

class financebase_data_bill_tmyp extends financebase_abstract_bill
{
    public $order_bn_prefix = '';
    public $column_num = 33; // 33个字段（新增优品红包字段）
    public $ioTitle = array();
    public $ioTitleKey = array();
    public $verified_data = array();
    public $shop_list_by_name = array(); // 店铺列表（按名称索引）
    
    // 需要转换为行的金额列（列转行）
    public $column_to_row = [
        'amount',           // 含税金额
        'yp_red_packet',    // 优品红包
    ];
    
    // 指定要读取的工作表名称（已注释：现在只读取第一个工作表）
    // public $sheet_name = '账单明细-寄售';

    /**
     * 获取标题映射
     * @return array
     */
    public function getTitle()
    {
        $title = array(
            'bill_no'              => '对账单号',
            'settlement_company'   => '结算公司主体',
            'bill_create_time'     => '账单生成时间',
            'order_no'             => '交易主单',
            'trade_sub_order'      => '交易子单',
            'business_main_no'     => '业务主单据编码',
            'business_sub_no'      => '业务子单据编码',
            'pay_time'             => '支付时间',
            'business_time'        => '业务时间',
            'business_doc_type'    => '业务单据类型',
            'fee_type'             => '费用类型',
            'business_attr'        => '经营属性',
            'trade_type'           => '结算方式',
            'supplier_code'        => '供应商编码',
            'supplier_name'        => '供应商名称',
            'currency'             => '结算币种',
            'amount'               => '含税金额',
            'amount_without_tax'   => '未税金额',
            'tax_amount'           => '税额',
            'tax_rate'             => '税率',
            'goods_code'           => '后端商品编码',
            'goods_name'           => '后端商品名称',
            'unit'                 => '存储单位',
            'quantity'             => '商品数量',
            'price_with_tax'       => '含税单价',
            'price_without_tax'    => '未税单价',
            'is_recalculate'       => '是否重算',
            'reference'            => '参考',
            'financial_no'         => '唯一ID',
            'settlement_no'        => '结算流水编号',
            'diff_type'            => '差异判责类型',
            'bill_category'        => '账单分类',
            'yp_red_packet'        => '优品红包',
        );
        return $title;
    }

    /**
     * 批量转换数据（列转行）
     * 将含税金额和优品红包转换为两行数据
     * @param array $data 原始数据
     * @param array $title 标题行
     * @return array
     */
    public function batchTransferData($data, $title)
    {
        if (!$this->ioTitle) {
            $this->ioTitle = $this->getTitle();
        }

        $titleKey = array();
        foreach ($title as $k => $t) {
            $titleKey[$k] = array_search($t, $this->getTitle());
        }

        $reData = [];
        foreach($data as $row) {
            $row = array_map('trim', $row);
            
            // 跳过标题行
            if($row[0] == '对账单号') {
                continue;
            }

            // 过滤"票扣"数据：如果结算方式是"票扣"，则忽略此行数据
            $trade_type_index = array_search('trade_type', $titleKey);
            if($trade_type_index !== false) {
                $trade_type_value = isset($row[$trade_type_index]) ? trim($row[$trade_type_index]) : '';
                if($trade_type_value == '票扣') {
                    continue;
                }
            }

            $tmpRow = [];
            foreach($row as $k => $v) {
                if(isset($titleKey[$k]) && $titleKey[$k]) {
                    $tmpRow[$titleKey[$k]] = $v;
                }
            }

            // 将含税金额和优品红包转换为单独的行数据
            foreach($this->column_to_row as $col) {
                // 检查金额是否存在、不为空、不为0（包括 '0'、'0.00'、0、0.0 等）
                if(isset($tmpRow[$col]) && $tmpRow[$col] !== '' && floatval($tmpRow[$col]) != 0) {
                    $tmp = $tmpRow;
                    $tmp['amount'] = $tmpRow[$col];
                    
                    // 设置 trade_type
                    if($col == 'yp_red_packet') {
                        // 优品红包的 trade_type 就是"优品红包"
                        $tmp['trade_type'] = '优品红包';
                        // 优品红包需要重新生成 financial_no，在原值基础上加 "-1"
                        if(isset($tmpRow['financial_no']) && $tmpRow['financial_no']) {
                            $tmp['financial_no'] = $tmpRow['financial_no'] . '-1';
                        }
                    } else {
                        // 含税金额的 trade_type 保持原来的结算方式
                        $tmp['trade_type'] = isset($tmpRow['trade_type']) ? $tmpRow['trade_type'] : '';
                    }
                    
                    $reData[] = $tmp;
                }
            }
        }
        return $reData;
    }

    /**
     * 处理数据（核心方法）
     * @param array $row 数据行（batchTransferData 返回的关联数组）
     * @param int $offset 行偏移量
     * @param array $title 标题行
     * @return array
     */
    public function getSdf($row, $offset=1, $title)
    {
        if(!$this->ioTitle){
            $this->ioTitle = $this->getTitle();
            $this->ioTitleKey = array_keys($this->ioTitle);
        }

        $res = array('status'=>true, 'data'=>array(), 'msg'=>'');
        
        // batchTransferData 返回的是关联数组，直接处理
        $tmp = array();
        foreach($row as $k => $v) {
            $tmp[$k] = trim($v, '\'');
        }
        
        // 过滤"票扣"数据：如果结算方式是"票扣"，则忽略此行数据
        if(isset($tmp['trade_type']) && $tmp['trade_type'] == '票扣') {
            // 返回成功状态但不处理数据（data为空）
            return array('status' => true, 'data' => array(), 'msg' => '');
        }
        
        // 验证必填字段
        foreach ($tmp as $k => $v) {
            // 必填字段：交易主单、结算方式、金额
            if(in_array($k, array('order_no', 'trade_type', 'amount')))
            {
                if(!$v)
                {
                    $res['status'] = false;
                    $res['msg'] = sprintf("LINE %d : %s 不能为空！", $offset, isset($this->ioTitle[$k]) ? $this->ioTitle[$k] : $k);
                    return $res;
                }
            }

            // 时间格式验证
            if(in_array($k, array('bill_create_time', 'pay_time', 'business_time')))
            {
                if($v && $v != '--') {
                    $result = finance_io_bill_verify::isDate($v);
                    if ($result['status'] == 'fail') 
                    {
                        $res['status'] = false;
                        $res['msg'] = sprintf("LINE %d : %s 时间(%s)格式错误！", $offset, isset($this->ioTitle[$k]) ? $this->ioTitle[$k] : $k, $v);
                        return $res;
                    }
                }
            }

            // 金额格式验证（天猫优品支持多位小数，如含税金额可能有6位小数）
            // 包含优品红包字段
            if(in_array($k, array('amount', 'amount_without_tax', 'tax_amount', 'price_with_tax', 'price_without_tax', 'yp_red_packet')))
            {
                if($v) {
                    // 自定义金额验证：支持多位小数的正负数
                    if (!preg_match('/^-?\d+(\.\d+)?$/', $v)) {
                        $res['status'] = false;
                        $res['msg'] = sprintf("LINE %d : %s 金额(%s)格式错误！", $offset, isset($this->ioTitle[$k]) ? $this->ioTitle[$k] : $k, $v);
                        return $res;
                    }
                }
            }

            // 特殊字段处理：去除Excel导出的 ="" 包裹
            if(in_array($k, array('order_no', 'business_main_no', 'business_sub_no', 'financial_no', 'settlement_no', 'goods_code'))){
                $tmp[$k] = trim($v, '=\"');
            }
        }

        $res['data'] = $tmp;
        return $res;
    }

    /**
     * 获取订单编号
     * @param array $params
     * @return string
     */
    public function _getOrderBn($params)
    {
        // 直接返回交易主单（order_no）
        if (isset($params['order_no']) && $params['order_no']) {
            return $params['order_no'];
        }
        
        return '';
    }

    /**
     * 过滤数据
     * @param array $data
     * @return array
     */
    public function _filterData($data)
    {
        $new_data = array();
        $new_data['order_bn'] = $this->_getOrderBn($data);
        $new_data['trade_no'] = isset($data['order_no']) ? $data['order_no'] : ''; // 交易主单
        $new_data['financial_no'] = isset($data['financial_no']) ? $data['financial_no'] : ''; // 唯一ID
        $new_data['out_trade_no'] = isset($data['business_main_no']) ? $data['business_main_no'] : ''; // 业务主单据编码
        $new_data['trade_time'] = isset($data['business_time']) ? strtotime($data['business_time']) : 0;
        $new_data['trade_type'] = isset($data['trade_type']) ? $data['trade_type'] : ''; // 结算方式
        $new_data['money'] = isset($data['amount']) ? floatval($data['amount']) : 0; // 含税金额（保留正负）
        $new_data['member'] = ''; // 会员信息为空
        
        // 使用唯一ID作为唯一标识
        $new_data['unique_id'] = isset($data['financial_no']) ? $data['financial_no'] : '';
        $new_data['platform_type'] = 'tmyp';
        $new_data['remarks'] = isset($data['business_doc_type']) ? $data['business_doc_type'] : ''; // 业务单据类型
        return $new_data;
    }

    /**
     * 获取账单分类
     * @param array $params
     * @return string
     */
    public function getBillCategory($params)
    {
        if (!$this->rules) {
            $this->getRules('alipay'); // 使用alipay的规则
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
     * 同步到对账表（finance.bill）
     * @param array $data 原始数据
     * @param string $bill_category 具体类别
     * @return bool
     */
    public function syncToBill($data, $bill_category='')
    {
        $data['content'] = json_decode(stripslashes($data['content']), 1);
        if(!$data['content']) return false;

        $tmp = $data['content'];
        $shop_id = $data['shop_id'];

        $mdlBill = app::get('finance')->model('bill');
        $oMonthlyReport = kernel::single('finance_monthly_report');

        $tmp['fee_obj'] = '天猫优品';
        $tmp['fee_item'] = $bill_category;

        $res = $this->getBillType($tmp, $shop_id);
        if(!$res['status']) return false;

        if(!$data['shop_name']){
            $data['shop_name'] = isset($this->shop_list[$data['shop_id']]) ? $this->shop_list[$data['shop_id']]['name'] : '';  
        }

        $base_sdf = array(
            'order_bn'          => $this->_getOrderBn($tmp),
            'channel_id'        => $data['shop_id'],
            'channel_name'      => $data['shop_name'],
            'trade_time'        => isset($tmp['business_time']) ? strtotime($tmp['business_time']) : 0,
            'fee_obj'           => $tmp['fee_obj'],
            'money'             => round($tmp['amount'], 2), // 保留正负
            'fee_item'          => $tmp['fee_item'],
            'fee_item_id'       => isset($this->fee_item_rules[$tmp['fee_item']]) ? $this->fee_item_rules[$tmp['fee_item']] : 0,
            'credential_number' => isset($tmp['financial_no']) ? $tmp['financial_no'] : '', // 唯一ID
            'member'            => '', // 会员信息为空
            'memo'              => isset($tmp['remarks']) ? $tmp['remarks'] : '',
            'unique_id'         => $data['unique_id'],
            'create_time'       => time(),
            'fee_type'          => isset($tmp['trade_type']) ? $tmp['trade_type'] : '',
            'fee_type_id'       => $res['fee_type_id'],
            'bill_type'         => $res['bill_type'],
            'charge_status'     => 1, // 流水直接设置记账成功
            'charge_time'       => time(),
        );
        
        $base_sdf['monthly_id'] = 0;
        $base_sdf['monthly_item_id'] = 0;
        $base_sdf['monthly_status'] = 0;
        
        $base_sdf['crc32_order_bn'] = sprintf('%u', crc32($base_sdf['order_bn']));
        $base_sdf['bill_bn'] = $mdlBill->gen_bill_bn();
        $base_sdf['unconfirm_money'] = $base_sdf['money'];

        if($mdlBill->insert($base_sdf)){
            kernel::single('finance_monthly_report_items')->dealBillMatchReport($base_sdf['bill_id']);
            return true;
        }
        return false;
    }

    /**
     * 获取导入日期列
     * @param array|null $title
     * @return array
     */
    public function getImportDateColunm($title = null)
    {
        // 找到时间列的位置
        $timeColumn = array('账单生成时间', '支付时间', '业务时间');
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
     * 更新订单号
     * @param array $data 数据
     * @return void
     */
    public function updateOrderBn($data)
    {
        $this->_formatData($data);
        $mdlBill = app::get('finance')->model('bill');
        
        if(!$this->shop_list_by_name)
        {
            $this->shop_list_by_name = financebase_func::getShopList(financebase_func::getShopType());
            $this->shop_list_by_name = array_column($this->shop_list_by_name, null, 'name');
        }

        foreach ($data as $v) 
        {
            // 跳过标题行
            if('对账单号' == $v[0]) continue;

            // 需要有店铺名称、账单号、订单号才能更新
            // 假设最后3列是：店铺名称、账单号、订单号（需要根据实际情况调整）
            // 注意：由于新增了优品红包字段，列索引需要相应调整
            if(!isset($v[33]) || !isset($v[34]) || !isset($v[35])) continue;
            if(!$v[33] || !$v[34] || !$v[35]) continue;

            $shop_id = isset($this->shop_list_by_name[$v[33]]) ? $this->shop_list_by_name[$v[33]]['shop_id'] : 0;
            if(!$shop_id) continue;

            $filter = array('bill_bn' => $v[34], 'shop_id' => $shop_id);

            // 找到unique_id
            $bill_info = $mdlBill->getList('unique_id,bill_id', $filter, 0, 1);
            if(!$bill_info) continue;
            $bill_info = $bill_info[0];

            if($mdlBill->update(array('order_bn' => $v[35]), array('bill_id' => $bill_info['bill_id'])))
            {
                app::get('financebase')->model('bill')->update(array('order_bn' => $v[35]), array('unique_id' => $bill_info['unique_id'], 'shop_id' => $shop_id));
                $op_name = kernel::single('desktop_user')->get_name();
                $content = sprintf("订单号改成：%s", $v[35]);
                finance_func::addOpLog($v[34], $op_name, $content, '更新订单号');
            }
        }
    }

    /**
     * 检查文件是否有效
     * @param string $file_name 文件名
     * @param string $file_type 文件类型
     * @return array
     */
    public function checkFile($file_name, $file_type)
    {
        if ($file_type !== 'xlsx') {
            return array(false, '天猫优品导入只支持.xlsx格式的Excel文件');
        }

        $ioType = kernel::single('financebase_io_' . $file_type);
        
        // 读取第一个工作表（不再限制特定工作表名称）
        $row = $ioType->getData($file_name, 0, 1, 0);

        if (empty($row) || !isset($row[0])) {
            return array(false, '文件没有数据');
        }

        // 完整验证所有列名
        $title = array_values($this->getTitle());
        sort($title);

        $tmypTitle = $row[0];
        sort($tmypTitle);

        // 完全匹配
        if ($title == $tmypTitle) {
            return array(true, '文件模板匹配', $row[0]);
        }
        
        // 包含关系匹配
        if (!array_diff($tmypTitle, $title)) {
            return array(true, '文件模板匹配', $row[0]);
        }

        return array(false, '文件模板错误：' . var_export($row[0], true) . '，正确的为：' . var_export($title, 1));
    }

}

