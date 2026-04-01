<?php
/**
 * 处理京东E卡结算单导入
 *
 * @author 334395174@qq.com
 * @version 0.1
 */

class financebase_data_bill_jdecard extends financebase_abstract_bill
{
    public $order_bn_prefix = '';

    public $column_num = 11;

    public $ioTitle = array();
    public $ioTitleKey = array();
    public $verified_data = array();
    public $shop_list_by_name = array();

    // 处理数据
    public function getSdf($row, $offset=1, $title)
    {
        $row = array_map('trim', $row);

        if(!$this->ioTitle){
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

        $res = array('status'=>true, 'data'=>array(), 'msg'=>'');

        if($this->column_num <= count($row) and $row[0] != '订单编号')
        {
            $tmp = array_combine($titleKey, $row);
            
            // 判断必填参数不能为空
            foreach ($tmp as $k => $v) {
                // 必填字段验证：订单编号、费用项、金额
                if(in_array($k, array('order_no', 'trade_type', 'amount')))
                {
                    if(!$v)
                    {
                        $res['status'] = false;
                        $res['msg'] = sprintf("LINE %d : %s 不能为空！", $offset, $this->ioTitle[$k]);
                        return $res;
                    }
                }

                // 时间格式验证
                if(in_array($k, array('order_create_time', 'order_complete_time')))
                {
                    if($v) {
                        $result = finance_io_bill_verify::isDate($v);
                        if ($result['status'] == 'fail') 
                        {
                            $res['status'] = false;
                            $res['msg'] = sprintf("LINE %d : %s 时间(%s)格式错误！", $offset, $this->ioTitle[$k], $v);
                            return $res;
                        }
                    }
                }

                // 金额格式验证
                if(in_array($k, array('amount')))
                {
                    $result = finance_io_bill_verify::isPrice($v);
                    if ($result['status'] == 'fail') 
                    {
                        $res['status'] = false;
                        $res['msg'] = sprintf("LINE %d : %s 金额(%s)格式错误！", $offset, $this->ioTitle[$k], $v);
                        return $res;
                    }
                }

                // 清理特殊字符（订单编号、商品编号等）
                if(in_array($k, array('order_no', 'goods_bn', 'branch_id', 'store_bn'))){
                    $tmp[$k] = trim($v, '=\"');
                }
            }

            $res['data'] = $tmp;
        }

        return $res;
    }

    public function getTitle()
    {
        $title = array(
            'order_no'              => '订单编号',
            'goods_bn'              => '商品编号',
            'goods_number'          => 'SKU数量',
            'goods_name'            => '商品名称',
            'order_create_time'     => '下单时间',
            'order_complete_time'   => '完成时间',
            'trade_type'            => '费用项',
            'amount'                => '金额',
            'branch_id'             => '分公司id',
            'store_bn'              => '门店编号',
            'tax_rate'              => '税率',
        );

        return $title;
    }

    // 获取订单号
    public function _getOrderBn($params)
    {
        // 直接返回订单编号
        if (isset($params['order_no']) && $params['order_no']) {
            return $params['order_no'];
        }
        return '';
    }

    /**
     * 获取具体类别
     * @Author YangYiChao
     * @Date   2019-06-03
     * @param  [Array]     $params    参数
     * @return [String]                 具体类别
     */
    public function getBillCategory($params)
    {
        if(!$this->rules) {
            $this->getRules('360buy'); // 使用360buy的规则
        }

        $this->verified_data = $params;

        if($this->rules)
        {
            foreach ($this->rules as $item) 
            {
                foreach ($item['rule_content'] as $rule) {
                    if($this->checkRule($rule)) {
                        return $item['bill_category'];
                    }
                }   
            }
        }

        return '';
    }

    /**
     * 检查文件是否有效
     * @Author YangYiChao
     * @Date   2019-06-25
     * @param  String     $file_name 文件名
     * @param  String     $file_type 文件类型
     * @return Boolean
     */
    public function checkFile($file_name, $file_type)
    {
        if ($file_type !== 'xlsx') {
            return array(false, '京东E卡导入只支持.xlsx格式的Excel文件');
        }

        $ioType = kernel::single('financebase_io_' . $file_type);
        $row = $ioType->getData($file_name, 0, 1, 0); // 读取第一个工作表
        
        if (empty($row) || !isset($row[0])) {
            return array(false, '文件没有数据');
        }

        $title = array_values($this->getTitle());
        sort($title);

        $jdecardTitle = $row[0];
        sort($jdecardTitle);
        
        // 完全匹配
        if ($title == $jdecardTitle) {
            return array(true, '文件模板匹配', $row[0]);
        }
        
        // 包含所有必需列
        if (!array_diff($jdecardTitle, $title)) {
            return array(true, '文件模板匹配', $row[0]);
        }

        return array(false, '文件模板错误：' . var_export($row[0], true) . '，正确的为：' . var_export($title, 1));
    }

    /**
     * 同步到对账表
     * @Author YangYiChao
     * @Date   2019-06-25
     * @param  Array   原始数据    $data 
     * @param  String  具体类别    $bill_category
     * @return Boolean
     */
    public function syncToBill($data, $bill_category='')
    {
        $data['content'] = json_decode(stripslashes($data['content']), 1);
        if(!$data['content']) return false;

        $tmp = $data['content'];
        $shop_id = $data['shop_id'];

        $mdlBill = app::get('finance')->model('bill');
        $oMonthlyReport = kernel::single('finance_monthly_report');

        $tmp['fee_obj'] = '京东E卡';
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
            'trade_time'        => isset($tmp['order_complete_time']) ? strtotime($tmp['order_complete_time']) : 0,
            'fee_obj'           => $tmp['fee_obj'],
            'money'             => round($tmp['amount'], 2), // 保留正负
            'fee_item'          => $tmp['fee_item'],
            'fee_item_id'       => isset($this->fee_item_rules[$tmp['fee_item']]) ? $this->fee_item_rules[$tmp['fee_item']] : 0,
            'credential_number' => isset($tmp['financial_no']) ? $tmp['financial_no'] : '', // 使用生成的唯一编码
            'member'            => '',
            'memo'              => isset($tmp['goods_name']) ? $tmp['goods_name'] : '', // 备注保存商品名称
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

    // 更新订单号
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
            if('订单编号' == $v[0]) continue;

            // 假设最后3列是：店铺名称、账单号、订单号
            if(!isset($v[11]) || !isset($v[12]) || !isset($v[13])) continue;
            if(!$v[11] || !$v[12] || !$v[13]) continue;

            $shop_id = isset($this->shop_list_by_name[$v[11]]) ? $this->shop_list_by_name[$v[11]]['shop_id'] : 0;
            if(!$shop_id) continue;

            $filter = array('bill_bn' => $v[12], 'shop_id' => $shop_id);

            // 找到unique_id
            $bill_info = $mdlBill->getList('unique_id,bill_id', $filter, 0, 1);
            if(!$bill_info) continue;
            $bill_info = $bill_info[0];

            if($mdlBill->update(array('order_bn' => $v[13]), array('bill_id' => $bill_info['bill_id'])))
            {
                app::get('financebase')->model('bill')->update(array('order_bn' => $v[13]), array('unique_id' => $bill_info['unique_id'], 'shop_id' => $shop_id));
                $op_name = kernel::single('desktop_user')->get_name();
                $content = sprintf("订单号改成：%s", $v[13]);
                finance_func::addOpLog($v[12], $op_name, $content, '更新订单号');
            }
        }
    }

    public function _filterData($data)
    {
        $new_data = array();
        
        $new_data['order_bn'] = $this->_getOrderBn($data);
        $new_data['trade_no'] = isset($data['order_no']) ? $data['order_no'] : ''; // 使用订单编号
        
        // 生成唯一编码：order_no + goods_bn + goods_number + trade_type + amount 的 MD5
        // 必须加入金额，因为同一订单同一商品可能既有正金额（货款）又有负金额（价保扣款）
        $unique_str = sprintf(
            '%s_%s_%s_%s_%s',
            isset($data['order_no']) ? $data['order_no'] : '',
            isset($data['goods_bn']) ? $data['goods_bn'] : '',
            isset($data['goods_number']) ? $data['goods_number'] : '',
            isset($data['trade_type']) ? $data['trade_type'] : '',
            isset($data['amount']) ? $data['amount'] : ''
        );
        $unique_code = md5($unique_str);
        
        $new_data['financial_no'] = $unique_code; // 使用生成的唯一编码
        $new_data['out_trade_no'] = isset($data['goods_bn']) ? $data['goods_bn'] : ''; // 使用商品编号
        $new_data['trade_time'] = isset($data['order_complete_time']) ? strtotime($data['order_complete_time']) : 0;
        $new_data['trade_type'] = isset($data['trade_type']) ? $data['trade_type'] : '';
        $new_data['money'] = isset($data['amount']) ? floatval($data['amount']) : 0; // 保留正负
        $new_data['member'] = '';
        
        // 使用生成的唯一编码作为唯一标识
        $new_data['unique_id'] = $unique_code;
        $new_data['platform_type'] = 'jdecard';
        $new_data['remarks'] = ''; // 备注为空

        return $new_data;
    }

    public function getImportDateColunm($title=null)
    {
        $timeColumn = array('下单时间', '完成时间');
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

