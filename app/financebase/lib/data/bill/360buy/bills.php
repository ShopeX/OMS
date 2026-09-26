<?php
/**
 * 京东 listBills / financebase_rpc_request_bill::bill_360buy_bills 生成的 CSV 导入
 * biz_type = 360buy_bills 时使用本类，与钱包模板 financebase_data_bill_360buy 分离
 *
 * 接口拉数时 direction×bal 在 bill_360buy_bills 写 CSV 阶段处理；手动导入 CSV 金额原样入库。
 *
 * @see financebase_rpc_request_bill::bill_360buy_bills 表头需与本类 getTitle() 一致
 */
class financebase_data_bill_360buy_bills  extends financebase_abstract_bill
{
    public $column_num = 20;

    public function getTitle()
    {
        return array(
            'order_no'              => '订单编号',
            'financial_no'          => '单据编号',
            'order_type'            => '单据类型',
            'goods_bn'              => '商品编号',
            'out_trade_no'          => '商户订单号',
            'goods_name'            => '商品名称',
            'bill_status'           => '账单状态',
            'trade_create_time'     => '费用发生时间',
            'delivered_time'        => '订单妥投时间',
            'trade_settlement_time' => '费用结算时间',
            'trade_type'            => '费用项',
            'amount'                => '金额',
            'currency'              => '币种',
            'bill_type'             => '商家应收/应付',
            'settlement_remarks'    => '钱包结算备注',
            'shop_bn'               => '店铺号',
            'remarks'               => '备注',
            'fee_direction'         => '收支方向',
            'goods_number'          => '商品数量',
            'jd_trade_time'         => '对账日期',
        );
    }

    /**
     * 订单号（与 financebase_data_bill_360buy 一致，供 abstract bill::process / syncToBill 使用）
     */
    public function _getOrderBn($params)
    {
        return isset($params['order_no']) ? $params['order_no'] : '';
    }

    /**
     * 写入 sdb_financebase_bill 的字段映射（与 wallet 版 _filterData 口径一致，避免 bills 未继承 360buy 时报错）
     */
    public function _filterData($data)
    {
        $new_data = array();

        $new_data['order_bn']     = $this->_getOrderBn($data);
        $new_data['trade_no']     = '';
        $new_data['financial_no'] = isset($data['financial_no']) ? $data['financial_no'] : '';
        $new_data['out_trade_no'] = isset($data['out_trade_no']) ? $data['out_trade_no'] : '';
        $new_data['trade_time']   = !empty($data['trade_settlement_time'])
            ? strtotime($data['trade_settlement_time'])
            : 0;
        $new_data['trade_type'] = isset($data['trade_type']) ? $data['trade_type'] : '';
        $new_data['money']      = isset($data['amount']) ? $data['amount'] : '';
        $new_data['member']     = '';
        $new_data['unique_id']  = $new_data['financial_no'];

        $new_data['platform_type'] = '360buy';
        $new_data['remarks']       = isset($data['remarks']) ? $data['remarks'] : '';

        return $new_data;
    }

    public function getSdf($row, $offset = 1, $title)
    {
        $row = array_map('trim', $row);

        if (!$this->ioTitle) {
            $this->ioTitle    = $this->getTitle();
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

        $need = count($titleKey);
        if ($need < 1) {
            return array('status' => false, 'msg' => '表头映射为空');
        }

        // 与列数对齐：导出 CSV 常省略尾部空列，导致 count($row) < 表头列数，原先用固定 column_num 会直接跳过且不报错
        if (count($row) < $need) {
            $row = array_pad($row, $need, '');
        } elseif (count($row) > $need) {
            $row = array_slice($row, 0, $need);
        }

        // 跳过表头行
        if ($row[0] != '订单编号') {

            $combineKeys = array_values($titleKey);
            $tmp           = array_combine($combineKeys, $row);
            if ($tmp === false) {
                return array('status' => false, 'msg' => sprintf('LINE %d : 列数与表头不一致', $offset));
            }

            foreach ($tmp as $k => $v) {

                if (in_array($k, array('order_no', 'financial_no', 'trade_settlement_time'))) {
                    if (!$v) {
                        $res['status'] = false;
                        $res['msg']    = sprintf("LINE %d : %s 不能为空！", $offset, $this->ioTitle[$k]);

                        return $res;
                    }
                }

                if (in_array($k, array('trade_create_time', 'delivered_time', 'trade_settlement_time'))) {
                    $result = finance_io_bill_verify::isDate($v);
                    if ($result['status'] == 'fail') {
                        $res['status'] = false;
                        $res['msg']    = sprintf("LINE %d : %s 时间(%s)格式错误！", $offset, $this->ioTitle[$k], $v);

                        return $res;
                    }
                }

                if (in_array($k, array('amount'))) {
                    $result = finance_io_bill_verify::isPrice($v);
                    if ($result['status'] == 'fail') {
                        $res['status'] = false;
                        $res['msg']    = sprintf("LINE %d : %s 金额(%s)格式错误！", $offset, $this->ioTitle[$k], $v);

                        return $res;
                    }
                }

                if (in_array($k, array('order_no', 'financial_no', 'out_trade_no', 'goods_bn'))) {
                    $tmp[$k] = trim($v, '=\"');
                }
            }

            $res['data'] = $tmp;
        }
       
        return $res;
    }

    public function getImportDateColunm($title = null)
    {
        $timeColumn = array('费用结算时间', '订单妥投时间', '费用发生时间');
        $timeCol    = array();
        foreach ($timeColumn as $v) {
            if (($k = array_search($v, $title)) !== false) {
                $timeCol[] = $k + 1;
            }
        }
        $timezone = defined('DEFAULT_TIMEZONE') ? DEFAULT_TIMEZONE : 0;

        return array('column' => $timeCol, 'time_diff' => $timezone * 3600);
    }

    /**
     * listBills 列与钱包版「批量改单号」列位不一致，不执行父类逻辑
     */
    public function updateOrderBn($data)
    {
        return;
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
        if(!$this->rules) $this->getRules('360buy');

        $this->verified_data = $params;

        if($this->rules)
        {
            foreach ($this->rules as $item) 
            {
                foreach ($item['rule_content'] as $rule) {
                    if($this->checkRule($rule)) return $item['bill_category'];
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
    public function checkFile($file_name,$file_type){
        $ioType = kernel::single('financebase_io_'.$file_type);
        $row = $ioType->getData($file_name,0,1);
        $title = array_values($this->getTitle());
        sort($title);

        $jdTitle = $row[0];
        sort($jdTitle);
        if ($title == $jdTitle) {
            return array(true, '文件模板匹配', $row[0]);
        }
        if (!array_diff($jdTitle, $title)) {
            return array(true, '文件模板匹配', $row[0]);
        }

        return array(false, '文件模板错误：' . var_export($row[0], true).'，正确的为：' . var_export($title, 1));
    }


    /**
     * 同步到对账表
     * @Author YangYiChao
     * @Date   2019-06-25
     * @param  Array   原始数据    $data 
     * @param  String  具体类别    $bill_category
     * @return Boolean
     */
    public function syncToBill($data,$bill_category='')
    {

        $data['content'] = json_decode(stripslashes($data['content']),1);
        if(!$data['content']) return false;

        $tmp = $data['content'];
        $shop_id = $data['shop_id'];

        $mdlBill = app::get('finance')->model('bill');
        $oMonthlyReport = kernel::single('finance_monthly_report');

        $tmp['fee_obj'] = '京东';
        $tmp['fee_item'] = $bill_category;

        $res = $this->getBillType($tmp,$shop_id);
        if(!$res['status']) return false;

        if(!$data['shop_name']){
            $data['shop_name'] = isset($this->shop_list[$data['shop_id']]) ? $this->shop_list[$data['shop_id']]['name'] : '';  
        }

        $base_sdf = array(
            'order_bn'          => $this->_getOrderBn($tmp),
            'channel_id'        => $data['shop_id'],
            'channel_name'      => $data['shop_name'],
            'trade_time'        => strtotime($tmp['trade_settlement_time']),
            'fee_obj'           => $tmp['fee_obj'],
            'money'             => round($tmp['amount'],2),
            'fee_item'          => $tmp['fee_item'],
            'fee_item_id'       => isset($this->fee_item_rules[$tmp['fee_item']]) ? $this->fee_item_rules[$tmp['fee_item']] : 0,
            'credential_number' => $tmp['financial_no'],// 单据编号
            'member'            => '',
            'memo'              => $tmp['remarks'],
            'unique_id'         => $data['unique_id'],
            'create_time'       => time(),
            'fee_type'          => $tmp['trade_type'],
            'fee_type_id'       => $res['fee_type_id'],
            'bill_type'         => $res['bill_type'],
            'charge_status'     => 1,// 流水直接设置记账成功
            'charge_time'       => time(),

        );
        $base_sdf['monthly_id'] = 0;
        $base_sdf['monthly_item_id']     = 0;
        $base_sdf['monthly_status'] = 0;
        
        
        $base_sdf['crc32_order_bn'] = sprintf('%u',crc32($base_sdf['order_bn']));
        $base_sdf['bill_bn'] = $mdlBill->gen_bill_bn();
        $base_sdf['unconfirm_money'] = $base_sdf['money'];

        if($mdlBill->insert($base_sdf)){
            kernel::single('finance_monthly_report_items')->dealBillMatchReport($base_sdf['bill_id']);
            return true;
        }
        return false;
    }

}
