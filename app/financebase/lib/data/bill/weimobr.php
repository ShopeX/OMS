<?php
/**
 * 微盟零售账单导入处理类
 * 支持正向（销售）和逆向（退款）两种模板
 * 
 * @author AI Assistant
 * @version 1.0
 */

class financebase_data_bill_weimobr extends financebase_abstract_bill
{
    public $order_bn_prefix = '';
    public $column_num = 0; // 动态字段数
    public $ioTitle = array();
    public $ioTitleKey = array();
    public $verified_data = array();
    public $shop_list_by_name = array(); // 店铺列表（按名称索引）
    
    // 标记当前处理的是正向还是逆向
    private $is_forward = null;
    
    // 正向表头（销售）
    private $title_forward = null;
    
    // 逆向表头（退款）
    private $title_reverse = null;

    /**
     * 获取正向表头（销售）
     * @return array
     */
    private function getTitleForward()
    {
        if ($this->title_forward === null) {
            $this->title_forward = array(
                'trade_time'              => '交易时间',
                'amount'                  => '交易金额',
                'settlement_amount'       => '应结订单金额',
                'fee_amount'              => '手续费金额',
                'income_amount'           => '入账金额',
                'wechat_coupon'          => '微信代金券金额',
                'unionpay_coupon'         => '云闪付优惠券金额',
                'alipay_coupon'          => '支付宝代金券商家优惠金额',
                'alipay_amount'           => '支付宝实收金额',
                'reconciliation_status'   => '对账状态',
                'bill_date'              => '账单日期',
                'trade_type'             => '交易类型',
                'split_status'           => '分账状态',
                'unfreeze_time'          => '解冻时间',
                'settlement_status'      => '结算状态',
                'settlement_time'        => '结算时间',
                'shop_name'              => '店铺名称',
                'org_id'                 => '组织ID',
                'parent_order_no'        => '父单号',
                'order_no'               => '订单编号',
                'trade_no'               => '交易单号',
                'channel_trade_no'       => '通道交互单号',
                'refund_amount'          => '退款金额',
                'fee_return'             => '手续费返还',
                'pay_mode'               => '支付模式',
                'merchant_no'            => '支付商户号',
                'currency'               => '币种',
                'business_type'          => '业务类型',
                'channel_type'           => '渠道类型',
                'remark1'                => '备注1',
                'payer_info'             => '付款人信息',
            );
        }
        return $this->title_forward;
    }

    /**
     * 获取逆向表头（退款）
     * @return array
     */
    private function getTitleReverse()
    {
        if ($this->title_reverse === null) {
            $this->title_reverse = array(
                'refund_time'            => '退款时间',
                'amount'                 => '退款金额',
                'actual_refund'          => '实退金额',
                'fee_return'             => '手续费返还',
                'wechat_coupon_refund'   => '微信代金券金额退款',
                'unionpay_coupon_refund' => '云闪付优惠券退款金额',
                'refund_status'          => '退款状态',
                'remark'                 => '备注',
                'reconciliation_status'  => '对账状态',
                'bill_date'              => '账单日期',
                'shop_name'              => '店铺名称',
                'org_id'                 => '组织ID',
                'dispute_no'             => '维权单号',
                'channel_refund_no'      => '通道退款单号',
                'channel_trade_no'       => '通道交互单号',
                'original_trade_time'    => '原交易时间',
                'original_trade_amount'  => '原交易金额',
                'original_unfreeze_time' => '原交易解冻时间',
                'original_parent_order'  => '原父单号',
                'order_no'               => '原订单编号', // 统一使用 order_no，Excel表头是"原订单编号"
                'original_trade_no'      => '原交易单号',
                'pay_mode'               => '支付模式',
                'merchant_no'            => '支付商户号',
                'currency'               => '币种',
                'business_type'          => '业务类型',
                'channel_type'           => '渠道类型',
            );
        }
        return $this->title_reverse;
    }

    /**
     * 获取标题映射（支持正向和逆向两种表头）
     * @return array
     */
    public function getTitle()
    {
        // 合并正向和逆向表头，支持两种模板
        return array_merge($this->getTitleForward(), $this->getTitleReverse());
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
        $row = array_map('trim', $row);

        // 判断是正向还是逆向（根据表头第一个字段）
        if ($this->is_forward === null && !empty($title)) {
            $first_title = trim($title[0]);
            if ($first_title == '退款时间') {
                $this->is_forward = false; // 逆向（退款）
            } else {
                $this->is_forward = true; // 正向（销售）
            }
        }

        // 根据正向/逆向获取对应的表头
        if ($this->is_forward) {
            $current_title = $this->getTitleForward();
        } else {
            $current_title = $this->getTitleReverse();
        }

        if(!$this->ioTitle){
            $this->ioTitle = $current_title;
            $this->ioTitleKey = array_keys($this->ioTitle);
        }

        $titleKey = array();
        foreach ($title as $k => $t) {
            $titleKey[$k] = array_search($t, $current_title);

            if ($titleKey[$k] === false) {
                return array('status' => false, 'msg' => '未定义字段`' . $t . '`');
            }
        }

        $res = array('status'=>true, 'data'=>array(), 'msg'=>'');

        // 正向：跳过表头行（交易时间）
        // 逆向：跳过表头行（退款时间）
        $first_field = $this->is_forward ? '交易时间' : '退款时间';
        if(count($row) > 0 && $row[0] != $first_field)
        {
            $tmp = array_combine($titleKey, $row);
            
            // 验证必填字段
            // 正向必填：交易时间、交易金额、订单编号
            // 逆向必填：退款时间、退款金额、订单编号（原订单编号）
            if($this->is_forward) {
                $required_fields = array('trade_time', 'amount', 'order_no');
            } else {
                $required_fields = array('refund_time', 'amount', 'order_no');
            }
            
            foreach ($tmp as $k => $v) {
                // 只验证必填字段
                if(in_array($k, $required_fields))
                {
                    if(!$v || $v == '--')
                    {
                        $res['status'] = false;
                        $res['msg'] = sprintf("LINE %d : %s 不能为空！", $offset, $this->ioTitle[$k]);
                        return $res;
                    }
                    
                    // 必填字段的格式验证
                    // 时间字段格式验证
                    if(in_array($k, array('trade_time', 'refund_time')))
                    {
                        $result = finance_io_bill_verify::isDate($v);
                        if ($result['status'] == 'fail') 
                        {
                            $res['status'] = false;
                            $res['msg'] = sprintf("LINE %d : %s 时间(%s)格式错误！", $offset, $this->ioTitle[$k], $v);
                            return $res;
                        }
                    }
                    
                    // 金额字段格式验证
                    if($k == 'amount')
                    {
                        // 自定义金额验证：支持多位小数的正负数
                        if (!preg_match('/^-?\d+(\.\d+)?$/', $v)) {
                            $res['status'] = false;
                            $res['msg'] = sprintf("LINE %d : %s 金额(%s)格式错误！", $offset, $this->ioTitle[$k], $v);
                            return $res;
                        }
                    }
                }

                // 特殊字段处理：去除Excel导出的 ="" 包裹
                if(in_array($k, array('order_no', 'trade_no', 'channel_trade_no', 'original_trade_no', 'parent_order_no', 'original_parent_order'))){
                    $tmp[$k] = trim($v, '=\"');
                }
            }

            // 标记数据类型（正向或逆向）
            $tmp['_is_forward'] = $this->is_forward;
            
            // 处理 trade_type：正向为"交易金额"，逆向为"退款金额"
            if ($this->is_forward) {
                $tmp['trade_type'] = '交易金额';
            } else {
                $tmp['trade_type'] = '退款金额';
            }
            
            // 处理 amount：如果是逆向，转为负数
            if (isset($tmp['amount']) && !$this->is_forward) {
                $tmp['amount'] = -abs(floatval($tmp['amount'])); // 逆向金额转为负数
            } elseif (isset($tmp['amount'])) {
                $tmp['amount'] = floatval($tmp['amount']); // 正向金额保持正数
            }
            
            $res['data'] = $tmp;
        }

        return $res;
    }

    /**
     * 获取订单编号
     * @param array $params
     * @return string
     */
    public function _getOrderBn($params)
    {
        // 正向和逆向都使用 order_no
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
        $order_bn = $this->_getOrderBn($data);
        
        $is_forward = isset($data['_is_forward']) ? $data['_is_forward'] : true;
        
        // 正逆向共用的字段
        $new_data = array(
            'order_bn' => $order_bn,
            'trade_no' => $order_bn, // trade_no 都使用订单号
            'financial_no' => isset($data['channel_trade_no']) ? $data['channel_trade_no'] : '', // financial_no 都使用 channel_trade_no
            'member' => '', // member 都为空
            'remarks' => '', // remarks 都为空
            'trade_type' => isset($data['trade_type']) ? $data['trade_type'] : '', // trade_type 已经在 getSdf 中处理
            'money' => isset($data['amount']) ? floatval($data['amount']) : 0, // amount 已经在 getSdf 中处理（逆向已转为负数）
        );
        
        // 正逆向不同的字段
        if ($is_forward) {
            // 正向（销售）
            $new_data['out_trade_no'] = isset($data['trade_no']) ? $data['trade_no'] : '';
            $new_data['trade_time'] = isset($data['trade_time']) ? strtotime($data['trade_time']) : 0;
        } else {
            // 逆向（退款）
            $new_data['out_trade_no'] = isset($data['original_trade_no']) ? $data['original_trade_no'] : '';
            $new_data['trade_time'] = isset($data['refund_time']) ? strtotime($data['refund_time']) : 0;
        }
        
        // 生成唯一ID：使用 trade_no + financial_no + trade_time + money 的 MD5
        $unique_str = sprintf(
            '%s_%s_%s_%s',
            $new_data['trade_no'],
            $new_data['financial_no'],
            $new_data['trade_time'],
            $new_data['money']
        );
        $new_data['unique_id'] = md5($unique_str);
        $new_data['platform_type'] = 'weimobr';
        
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
            $this->getRules('weimobr'); // 使用微信支付的规则
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

        // 判断是正向还是逆向
        $is_forward = isset($tmp['_is_forward']) ? $tmp['_is_forward'] : true;

        $tmp['fee_obj'] = '微盟零售';
        $tmp['fee_item'] = $bill_category;

        $res = $this->getBillType($tmp, $shop_id);
        if(!$res['status']) return false;

        if(!$data['shop_name']){
            $data['shop_name'] = isset($this->shop_list[$data['shop_id']]) ? $this->shop_list[$data['shop_id']]['name'] : '';  
        }

        // 根据正向/逆向获取时间（字符串转时间戳）
        if ($is_forward) {
            $trade_time = isset($tmp['trade_time']) ? strtotime($tmp['trade_time']) : 0;
        } else {
            $trade_time = isset($tmp['refund_time']) ? strtotime($tmp['refund_time']) : 0;
        }
        
        // amount 已经在 getSdf 中处理（逆向已转为负数）
        $money = isset($tmp['amount']) ? floatval($tmp['amount']) : 0;
        $credential_number = isset($tmp['channel_trade_no']) ? $tmp['channel_trade_no'] : '';

        $base_sdf = array(
            'order_bn'          => $this->_getOrderBn($tmp),
            'channel_id'        => $data['shop_id'],
            'channel_name'      => $data['shop_name'],
            'trade_time'        => $trade_time,
            'fee_obj'           => $tmp['fee_obj'],
            'money'             => round($money, 2), // 保留正负
            'fee_item'          => $tmp['fee_item'],
            'fee_item_id'       => isset($this->fee_item_rules[$tmp['fee_item']]) ? $this->fee_item_rules[$tmp['fee_item']] : 0,
            'credential_number' => $credential_number,
            'member'            => '', // member 都为空
            'memo'              => '', // remarks 都为空
            'unique_id'         => $data['unique_id'],
            'create_time'       => time(),
            'fee_type'          => $is_forward ? '交易金额' : '退款金额',
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
        // 正向使用"交易时间"，逆向使用"退款时间"
        $timeColumn = array();
        if ($this->is_forward === false) {
            $timeColumn = array('退款时间');
        } else {
            $timeColumn = array('交易时间');
        }
        
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
     * 更新订单编号
     * @param array $data
     * @return bool
     */
    public function updateOrderBn($data)
    {
        $mdlBill = app::get('financebase')->model('bill');
        
        foreach($data as $row) {
            $order_bn = $this->_getOrderBn($row);
            if($order_bn) {
                $is_forward = isset($row['_is_forward']) ? $row['_is_forward'] : true;
                $filter = array(
                    'unique_id' => md5(sprintf(
                        '%s_%s',
                        isset($row['order_no']) ? $row['order_no'] : '',
                        $is_forward ? (isset($row['channel_trade_no']) ? $row['channel_trade_no'] : '') : (isset($row['channel_refund_no']) ? $row['channel_refund_no'] : '')
                    ))
                );
                $mdlBill->update(array('order_bn' => $order_bn), $filter);
            }
        }
        
        return true;
    }

    /**
     * 检查文件是否有效
     * @param string $file_name 文件名
     * @param string $file_type 文件类型
     * @return array
     */
    public function checkFile($file_name, $file_type)
    {
        $ioType = kernel::single('financebase_io_' . $file_type);
        
        // 读取第一个工作表的第一行（表头）
        $row = $ioType->getData($file_name, 0, 1, 0);

        if (empty($row) || !isset($row[0])) {
            return array(false, '文件没有数据');
        }

        // 判断是正向还是逆向（根据表头第一个字段）
        $first_title = trim($row[0][0]);
        $is_forward = ($first_title != '退款时间');
        
        // 根据正向/逆向获取对应的表头进行验证
        if ($is_forward) {
            $title = array_values($this->getTitleForward());
        } else {
            $title = array_values($this->getTitleReverse());
        }
        sort($title);

        $fileTitle = $row[0];
        sort($fileTitle);

        // 完全匹配
        if ($title == $fileTitle) {
            return array(true, '文件模板匹配', $row[0]);
        }
        
        // 包含关系匹配
        if (!array_diff($fileTitle, $title)) {
            return array(true, '文件模板匹配', $row[0]);
        }

        return array(false, '文件模板错误：' . var_export($row[0], true) . '，正确的为：' . var_export($title, 1));
    }
}

