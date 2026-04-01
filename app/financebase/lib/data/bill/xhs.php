<?php
/**
 * 处理小红书账单导入
 *
 * @author AI Assistant
 * @version 1.0
 */

class financebase_data_bill_xhs extends financebase_abstract_bill
{
    public $column_num = 5; // 5个字段：订单号、国补金额、SKU、数量、账单日期
    public $ioTitle = array();
    public $ioTitleKey = array();
    private static $shop_type_cache = array(); // 静态变量缓存店铺类型数据

    public function getTitle()
    {
        $title = array(
            'order_bn' => '订单号',
            'amount' => '国补金额',
            'sku' => 'SKU',
            'quantity' => '数量',
            'trade_time' => '账单日期'
        );
        return $title;
    }

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

        if ($this->column_num <= count($row) && $row[0] != '订单号') {
            $tmp = array_combine($titleKey, $row);
            
            // 必填字段验证
            $required_fields = array('order_bn', 'amount', 'sku', 'quantity', 'trade_time');
            foreach ($required_fields as $field) {
                if (empty($tmp[$field])) {
                    $res['status'] = false;
                    $res['msg'] = sprintf("LINE %d : %s 不能为空！", $offset, $this->ioTitle[$field]);
                    return $res;
                }
            }

            // 金额格式验证
            if (!is_numeric($tmp['amount'])) {
                $res['status'] = false;
                $res['msg'] = sprintf("LINE %d : %s 金额(%s)格式错误！", $offset, $this->ioTitle['amount'], $tmp['amount']);
                return $res;
            }

            // 数量格式验证
            if (!is_numeric($tmp['quantity']) || $tmp['quantity'] <= 0) {
                $res['status'] = false;
                $res['msg'] = sprintf("LINE %d : %s 数量(%s)必须为正数！", $offset, $this->ioTitle['quantity'], $tmp['quantity']);
                return $res;
            }

            // 日期格式验证
            if (!strtotime($tmp['trade_time'])) {
                $res['status'] = false;
                $res['msg'] = sprintf("LINE %d : %s 日期(%s)格式错误！", $offset, $this->ioTitle['trade_time'], $tmp['trade_time']);
                return $res;
            }

            $res['data'] = $tmp;
        }

        return $res;
    }

    public function _filterData($data)
    {
        $new_data = array();
        
        $new_data['order_bn'] = $this->_getOrderBn($data);
        $new_data['trade_no'] = '';
        $new_data['financial_no'] = $data['order_bn'];
        $new_data['out_trade_no'] = '';
        $new_data['trade_time'] = strtotime($data['trade_time']);
        $new_data['trade_type'] = '小红书账单';
        $new_data['money'] = $data['amount'];
        $new_data['member'] = '';
        
        // unique_id 生成规则：shop_id + 订单号 + SKU + 国补金额的MD5
        $shop_id = $data['shop_id'] ?: $this->getCurrentShopId();
        $new_data['unique_id'] = md5($shop_id . '-' . $data['order_bn'] . '-' . $data['sku'] . '-' . $data['amount']);
        
        $shop_info = $this->getShopInfoByShopId($shop_id);
        $new_data['platform_type'] = 'xhs'; // 小红书固定平台类型
        $new_data['remarks'] = '小红书账单导入-SKU:' . $data['sku'] . ' 数量:' . $data['quantity'];
        
        return $new_data;
    }

    public function _getOrderBn($params)
    {
        return $params['order_bn'];
    }

    public function getBillCategory($params)
    {
        // 小红书账单固定类别
        return '小红书账单';
    }

    /**
     * 检查文件是否有效
     * @param string $file_name 文件名
     * @param string $file_type 文件类型
     * @return array [是否有效, 错误信息, 标题]
     */
    public function checkFile($file_name, $file_type)
    {
        $ioType = kernel::single('financebase_io_' . $file_type);
        $row = $ioType->getData($file_name, 0, 1);
        $title = array_values($this->getTitle());
        sort($title);

        $xhsTitle = $row[0];
        sort($xhsTitle);
        if ($title == $xhsTitle) {
            return array(true, '文件模板匹配', $row[0]);
        }
        if (!array_diff($xhsTitle, $title)) {
            return array(true, '文件模板匹配', $row[0]);
        }

        return array(false, '文件模板错误：' . var_export($row[0], true) . '，正确的为：' . var_export($title, 1));
    }

    /**
     * 获取导入日期列配置
     * @param array $title 标题数组
     * @return array
     */
    public function getImportDateColunm($title = null)
    {
        // 小红书导入的日期字段配置
        $timeColumn = ['账单日期'];
        $timeCol = array();
        foreach ($timeColumn as $v) {
            if($k = array_search($v, $title)) {
                $timeCol[] = $k+1;
            }
        }
        $timezone = defined('DEFAULT_TIMEZONE') ? DEFAULT_TIMEZONE : 0;
        return array('column' => $timeCol, 'time_diff' => $timezone * 3600);
    }

    public function syncToBill($data, $bill_category = '')
    {
        $data['content'] = json_decode(stripslashes($data['content']), 1);
        if (!$data['content']) return false;

        $tmp = $data['content'];
        $shop_id = $data['shop_id'];

        $mdlBill = app::get('finance')->model('bill');
        $oMonthlyReport = kernel::single('finance_monthly_report');

        $tmp['fee_obj'] = '小红书';
        $tmp['fee_item'] = '小红书账单';

        $res = $this->getBillType($tmp, $shop_id);
        if (!$res['status']) return false;

        if (!$data['shop_name']) {
            $data['shop_name'] = isset($this->shop_list[$data['shop_id']]) ? $this->shop_list[$data['shop_id']]['name'] : '';
        }

        $base_sdf = array(
            'order_bn'          => $tmp['order_bn'],
            'channel_id'        => $data['shop_id'],
            'channel_name'      => $data['shop_name'],
            'trade_time'        => strtotime($tmp['trade_time']),
            'fee_obj'           => $tmp['fee_obj'],
            'money'             => round($tmp['money'], 2),
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
            'crc32_order_bn'    => sprintf('%u', crc32($tmp['order_bn'])),
            'bill_bn'           => $mdlBill->gen_bill_bn(),
            'unconfirm_money'   => round($tmp['money'], 2),
        );

        if ($mdlBill->insert($base_sdf)) {
            kernel::single('finance_monthly_report_items')->dealBillMatchReport($base_sdf['bill_id']);
            return true;
        }
        return false;
    }

    private function getShopInfoByShopId($shop_id)
    {
        if (!$shop_id) {
            return null;
        }
        
        // 如果缓存为空，一次性查询所有店铺数据
        if (empty(self::$shop_type_cache)) {
            $mdlShop = app::get('ome')->model('shop');
            $shop_list = $mdlShop->getList('shop_id,shop_type');
            
            // 将数据存入静态缓存，key为shop_id，value为shop数组
            foreach ($shop_list as $shop) {
                self::$shop_type_cache[$shop['shop_id']] = $shop;
            }
        }
        
        // 从缓存中获取shop信息
        if (isset(self::$shop_type_cache[$shop_id])) {
            return self::$shop_type_cache[$shop_id];
        }
        
        return null;
    }

    private function getCurrentShopId()
    {
        // 从当前请求中获取shop_id
        return $_POST['shop_id'] ?? '';
    }
}
