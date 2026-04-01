<?php
/**
 * 处理京东国补结算单导入
 * 多个金额列转换为多行数据（类似抖音处理逻辑）
 *
 * @author 334395174@qq.com
 * @version 0.1
 */

class financebase_data_bill_jdguobu extends financebase_abstract_bill
{
    public $order_bn_prefix = '';
    public $column_num = 19; // 19个字段
    public $ioTitle = array();
    public $ioTitleKey = array();
    public $verified_data = array();
    public $shop_list_by_name = array();

    // 需要转换为行的金额列（金额不为0时才处理）
    public $column_to_row = [
        'commission',           // 佣金
        'sop_amount',          // SOP采购货款
        'service_fee',         // 交易服务费
        'guobu_amount',        // 国补采购款
        'large_store_amount',  // 大店业务采购货款
        'promotion_service_fee', // 促销服务费
        'ad_commission',       // 广告联合活动降扣佣金
        'link_service_fee',    // 超链3.0服务费
        'final_amount',        // 应结货款
    ];

    /**
     * 获取标题映射
     * @return array
     */
    public function getTitle()
    {
        $title = array(
            'billing_time'         => '计费时间',
            'complete_time'        => '完成时间',
            'order_no'             => '订单编号',
            'order_total_amount'   => '订单总金额',
            'merchant_promotion'   => '商家促销金额',
            'full_reduction'       => '满减优惠金额',
            'shop_jingquan'        => '店铺京券金额',
            'shop_dongquan'        => '店铺东券金额',
            'payment'              => '货款',
            'commission'           => '佣金',
            'sop_amount'           => 'SOP采购货款',
            'service_fee'          => '交易服务费',
            'guobu_amount'         => '国补采购款',
            'large_store_amount'   => '大店业务采购货款',
            'promotion_service_fee' => '促销服务费',
            'ad_commission'        => '广告联合活动降扣佣金',
            'link_service_fee'     => '超链3.0服务费',
            'final_amount'         => '应结货款',
            'remarks'              => '备注',
        );
        return $title;
    }

    /**
     * 批量转换数据（列转行）
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
            if($row[0] == '计费时间') {
                continue;
            }

            // 过滤"合计"行：订单编号为空 或 包含"合计"字样
            $order_no_index = array_search('order_no', $titleKey);
            if($order_no_index !== false) {
                $order_no_value = isset($row[$order_no_index]) ? trim($row[$order_no_index]) : '';
                
                // 如果订单号为空，或者整行数据中包含"合计"，则跳过
                if(empty($order_no_value) || $this->containsTotal($row)) {
                    continue;
                }
            }

            $tmpRow = [];
            foreach($row as $k => $v) {
                if(isset($titleKey[$k]) && $titleKey[$k]) {
                    $tmpRow[$titleKey[$k]] = $v;
                }
            }

            // 将每个金额列转换为单独的一行数据（忽略金额为0的数据）
            foreach($this->column_to_row as $col) {
                // 检查金额是否存在、不为空、不为0（包括 '0'、'0.00'、0、0.0 等）
                if(isset($tmpRow[$col]) && $tmpRow[$col] !== '' && floatval($tmpRow[$col]) != 0) {
                    $tmp = $tmpRow;
                    $tmp['amount'] = $tmpRow[$col];
                    $tmp['trade_type'] = $this->ioTitle[$col];
                    $reData[] = $tmp;
                }
            }
        }
        return $reData;
    }

    /**
     * 检查行数据是否包含"合计"
     * @param array $row
     * @return bool
     */
    private function containsTotal($row)
    {
        foreach($row as $cell) {
            if(strpos($cell, '合计') !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * 处理数据（核心方法）
     * @param array $row 数据行
     * @param int $offset 行偏移量
     * @param array $title 标题行
     * @return array
     */
    public function getSdf($row, $offset=1, $title)
    {
        $res = array('status'=>true, 'data'=>array(), 'msg'=>'');
        $tmp = [];
        
        // 判断参数不能为空
        foreach ($row as $k => $v) {
            $tmp[$k] = trim($v, '\'');

            // 必填字段验证：订单编号、费用类型
            if(in_array($k, array('order_no', 'trade_type')))
            {
                if(!$v)
                {
                    $res['status'] = false;
                    $res['msg'] = sprintf("%s : %s 不能为空！", isset($row['order_no']) ? $row['order_no'] : 'LINE '.$offset, $this->ioTitle[$k]);
                    return $res;
                }
            }

            // 时间格式验证
            if(in_array($k, array('billing_time', 'complete_time')))
            {
                if($v) {
                    $result = finance_io_bill_verify::isDate($v);
                    if ($result['status'] == 'fail') 
                    {
                        $res['status'] = false;
                        $res['msg'] = sprintf("%s : %s 时间(%s)格式错误！", isset($row['order_no']) ? $row['order_no'] : 'LINE '.$offset, $this->ioTitle[$k], $v);
                        return $res;
                    }
                }
            }

            // 金额格式验证
            if(in_array($k, array('amount')))
            {
                if($v) {
                    $result = finance_io_bill_verify::isPrice($v);
                    if ($result['status'] == 'fail') 
                    {
                        $res['status'] = false;
                        $res['msg'] = sprintf("%s : %s 金额(%s)格式错误！", isset($row['order_no']) ? $row['order_no'] : 'LINE '.$offset, $this->ioTitle[$k], $v);
                        return $res;
                    }
                }
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
        $new_data['trade_no'] = isset($data['order_no']) ? $data['order_no'] : '';
        $new_data['financial_no'] = isset($data['order_no']) ? $data['order_no'] : '';
        $new_data['out_trade_no'] = '';
        $new_data['trade_time'] = isset($data['billing_time']) ? strtotime($data['billing_time']) : 0;
        $new_data['trade_type'] = isset($data['trade_type']) ? $data['trade_type'] : '';
        $new_data['money'] = isset($data['amount']) ? floatval($data['amount']) : 0; // 保留正负
        $new_data['member'] = '';
        
        // 使用订单号+费用类型组合作为唯一标识
        $unique_str = sprintf('%s-%s', 
            isset($data['order_no']) ? $data['order_no'] : '',
            isset($data['trade_type']) ? $data['trade_type'] : ''
        );
        $new_data['unique_id'] = md5($unique_str);
        
        $new_data['platform_type'] = 'jdguobu';
        $new_data['remarks'] = isset($data['remarks']) ? $data['remarks'] : '';

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
            $this->getRules('360buy'); // 使用360buy的规则
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
     * @param string $file_name 文件名
     * @param string $file_type 文件类型
     * @return array
     */
    public function checkFile($file_name, $file_type)
    {
        if ($file_type !== 'xlsx') {
            return array(false, '京东国补导入只支持.xlsx格式的Excel文件');
        }

        $ioType = kernel::single('financebase_io_' . $file_type);
        $row = $ioType->getData($file_name, 0, 5, 0);

        if (empty($row) || !isset($row[0])) {
            return array(false, '文件没有数据');
        }

        $title = array_values($this->getTitle());
        $plateTitle = $row[0];

        // 检查是否包含所有必需的列
        foreach($title as $v) {
            if(array_search($v, $plateTitle) === false) {
                return array(false, '文件模板错误：列【'.$v.'】未包含在导入文件中');
            }
        }

        return array(true, '文件模板匹配', $row[0]);
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

        $tmp['fee_obj'] = '京东国补';
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
            'trade_time'        => isset($tmp['billing_time']) ? strtotime($tmp['billing_time']) : 0,
            'fee_obj'           => $tmp['fee_obj'],
            'money'             => round($tmp['amount'], 2), // 保留正负
            'fee_item'          => $tmp['fee_item'],
            'fee_item_id'       => isset($this->fee_item_rules[$tmp['fee_item']]) ? $this->fee_item_rules[$tmp['fee_item']] : 0,
            'credential_number' => isset($tmp['financial_no']) ? $tmp['financial_no'] : '',
            'member'            => '',
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
            if('计费时间' == $v[0]) continue;

            // 需要有店铺名称、账单号、订单号才能更新
            if(!isset($v[19]) || !isset($v[20]) || !isset($v[21])) continue;
            if(!$v[19] || !$v[20] || !$v[21]) continue;

            $shop_id = isset($this->shop_list_by_name[$v[19]]) ? $this->shop_list_by_name[$v[19]]['shop_id'] : 0;
            if(!$shop_id) continue;

            $filter = array('bill_bn' => $v[20], 'shop_id' => $shop_id);

            // 找到unique_id
            $bill_info = $mdlBill->getList('unique_id,bill_id', $filter, 0, 1);
            if(!$bill_info) continue;
            $bill_info = $bill_info[0];

            if($mdlBill->update(array('order_bn' => $v[21]), array('bill_id' => $bill_info['bill_id'])))
            {
                app::get('financebase')->model('bill')->update(array('order_bn' => $v[21]), array('unique_id' => $bill_info['unique_id'], 'shop_id' => $shop_id));
                $op_name = kernel::single('desktop_user')->get_name();
                $content = sprintf("订单号改成：%s", $v[21]);
                finance_func::addOpLog($v[20], $op_name, $content, '更新订单号');
            }
        }
    }

    /**
     * 获取导入日期列
     * @param array|null $title
     * @return array
     */
    public function getImportDateColunm($title=null)
    {
        $timeColumn = array('计费时间', '完成时间');
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
}

