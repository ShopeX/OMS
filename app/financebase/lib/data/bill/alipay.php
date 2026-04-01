<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
/**
 * 处理支付宝下载
 *
 * @author 334395174@qq.com
 * @version 0.1
 */
class financebase_data_bill_alipay extends financebase_abstract_bill
{
    public $order_bn_prefix = 'T200P';
    public $column_num      = 12;

    // 处理数据
    /**
     * 获取Sdf
     * @param mixed $row row
     * @param mixed $offset offset
     * @param mixed $title title
     * @return mixed 返回结果
     */

    public function getSdf($row, $offset = 1, $title)
    {
        $row = array_map('trim', $row);

        if (!$this->ioTitle) {
            $this->ioTitle = $this->getTitle();
        }

        $titleKey = array();
        foreach ($title as $k => $t) {
            $titleKey[$k] = array_search($t, $this->getTitle());

            if ($titleKey[$k] === false) {
                return array('status' => false, 'msg' => '未定义字段`' . $t . '`');
            }
        }

        $res = array('status' => true, 'data' => array(), 'msg' => '');

        $row_num = count($row);

        if ($this->column_num <= $row_num and $row[0] != '账务流水号') {

            // 兼容对账单
            // if ($row_num > 16) {
            //     $row = array_chunk($row, 16);
            //     $row = $row[0];
            // } elseif ($row_num < 16) {
            //     $diff_num = 16 - $row_num;
            //     $row      = array_pad($row, 16, '');
            // }

            $tmp = array_combine($titleKey, $row);
            //判断参数不能为空
            foreach ($tmp as $k => $v) {

                if (in_array($k, array('financial_no', 'trade_no', 'out_trade_no'))) {
                    if (!$v) {
                        $res['status'] = false;
                        $res['msg']    = sprintf("LINE %d : %s 不能为空！", $offset, $this->ioTitle[$k]);
                        return $res;
                    }
                }

                if (in_array($k, array('trade_time'))) {
                    $result = finance_io_bill_verify::isDate($v);
                    if ($result['status'] == 'fail' || strtotime($v) <= 0) {
                        $res['status'] = false;
                        $res['msg']    = sprintf("LINE %d : %s 时间格式错误！取值是：%s", $offset, $this->ioTitle[$k],$v);
                        return $res;
                    }
                }

                if (in_array($k, array('income_amount', 'outcome_amount', 'amount'))) {
                    $result = finance_io_bill_verify::isPrice($v);
                    if ($result['status'] == 'fail') {
                        $res['status'] = false;
                        $res['msg']    = sprintf("LINE %d : %s 金额格式错误！", $offset, $this->ioTitle[$k]);
                        return $res;
                    }
                }
            }

            $res['data'] = $tmp;

        }

        return $res;
    }

    /**
     * 获取Title
     * @return mixed 返回结果
     */
    public function getTitle()
    {
        $title = array(
            'financial_no'        => '账务流水号',
            'trade_no'            => '业务流水号',
            'out_trade_no'        => '商户订单号',
            'goods_name'          => '商品名称',
            'trade_time'          => '发生时间',
            'member'              => '对方账号',
            'income_amount'       => '收入金额（+元）',
            'outcome_amount'      => '支出金额（-元）',
            'amount'              => '账户余额（元）',
            'channel_name'        => '交易渠道',
            'trade_type'          => '业务类型',
            'remarks'             => '备注',
            'trade_desc'          => '业务描述',
            'bill_source'         => '业务账单来源',
            'order_bn'            => '业务基础订单号',
            'trade_base_order_bn' => '业务订单号',
        );

        return $title;
    }

    // 获取订单号
    /**
     * _getOrderBn
     * @param mixed $params 参数
     * @return mixed 返回值
     */
    public function _getOrderBn($params)
    {
        if (isset($params['order_bn']) and $params['order_bn']) {
            return $params['order_bn'];
        }

        $out_trade_no = $params['out_trade_no'];
        $remarks      = $params['remarks'];
        $trade_type   = $params['trade_type'];

        switch ($trade_type) {
            case '交易退款':
                $tmp = explode('-', $remarks);
                $needChange = false;
                foreach ($tmp as $v) {
                    if($v == '售后退款' || $v == '保证金退款') {
                        $needChange = true;
                    }
                    if($needChange && 'T' == substr($v, 0, 1)) {
                        $out_trade_no = $v;
                    }
                }

                if ('T' == substr($out_trade_no, 0, 1)) {
                    preg_match('/T(\d+)P/', $out_trade_no, $match);
                    if ($match) {
                        return str_replace($match[0], '', $out_trade_no);
                    }
                    return $out_trade_no;
                }

                if ('A' == substr($remarks, 0, 1)) {
                    $tmp = explode('A', $remarks);
                    return $tmp[3];
                }

                preg_match('/(\d+)/i', $remarks, $match);
                if ($match) {
                    return $match[1];
                }

                break;
            case '其它':
                if ($params['member'] == '*金账号()') {
                    return '';
                }

                $tmp = explode('-', $remarks);
                $needChange = false;
                foreach ($tmp as $v) {
                    if($v == '售后支付' || $v == '保证金退款') {
                        $needChange = true;
                    }
                    if($needChange && 'T' == substr($v, 0, 1)) {
                        preg_match('/T(\d+)P/', $v, $match);
                        if ($match) {
                            return str_replace($match[0], '', $v);
                        }
                    }
                }

                if ($tmp[0] == '聚划算保险保证金') {
                    return '';
                }

                if (preg_match('/[0-9]+/', $remarks, $match)) {
                    return $match[0];
                }
                break;
            case '转账':
                if ('CAE' == substr($out_trade_no, 0, 3)) {
                    preg_match('/(\d+)/i', $remarks, $match);
                    if ($match) {
                        return $match[1];
                    }
                    $tmp = explode('_', $out_trade_no);
                    return $tmp[2];
                } elseif ('HJCAE' == substr($out_trade_no, 0, 5)) {
                    $tmp = explode('==', $out_trade_no);
                    if(empty($tmp[3])) {
                        if(strpos($remarks, 'KY_ITEM')) {
                            preg_match('/KY_ITEM\)\((\d+)\)/i', $remarks, $match);
                            if ($match) {
                                return $match[1];
                            }
                        }
                    }
                    return $tmp[3];
                } elseif (strstr($remarks, '淘宝客佣金')) {
                    preg_match('/淘宝客佣金(代扣|退)款\[(\d+)\]/i', $remarks, $match);
                    if ($match) {
                        return $match[2];
                    }
                } else {
                    preg_match('/{(\d+)}/i', $remarks, $match);
                    if ($match) {
                        return $match[1];
                    }
                }
                break;
            case '交易分账':
                preg_match('/(\d+)/i', $remarks, $match);
                if ($match) {
                    return $match[1];
                }
                return "";
                break;
            case '提现':
                return "";
                break;
            default:

                if ('HJCOM' == substr($out_trade_no, 0, 5)) {
                    $tmp = explode('==', $out_trade_no);
                    return $tmp[3];
                } elseif ('CAE_POINT' == substr($out_trade_no, 0, 9)) {
                    $tmp = explode('_', $out_trade_no);

                    $last_index = count($tmp) - 1;

                    if ($tmp[$last_index] == 'RETURNGIVE"') {
                        if (preg_match('/[0-9]+/', $remarks, $match)) {
                            return $match[0];
                        }
                    } else {
                        return $tmp[2];
                    }
                } elseif ('GXFZ' == substr($out_trade_no, 0, 4)) {
                    $tmp = explode('_', $out_trade_no);
                    return $tmp[2];
                } elseif ('T' == substr($out_trade_no, 0, 1)) {
                    preg_match('/T(\d+)P/', $out_trade_no, $match);
                    if ($match) {
                        return str_replace($match[0], '', $out_trade_no);
                    }
                    return $out_trade_no;
                } else {
                    return '';
                }
                break;
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
        if (!$this->rules) {
            $this->getRules('alipay');
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
     * @Author YangYiChao
     * @Date   2019-06-25
     * @param  String     $file_name 文件名
     * @param  String     $file_type 文件类型
     * @return Boolean
     */
    public function checkFile($file_name, $file_type)
    {
        $ioType = kernel::single('financebase_io_' . $file_type);
        $row    = $ioType->getData($file_name, 0, 5);
        if ('#支付宝账务明细查询' != $row[0][0]) {
            return array(false, '文件模板错误：' . var_export($row, true));
        }

        $title = array_values($this->getTitle());
        sort($title);

        /* $aliTitle = $row[1];
        sort($aliTitle);
        if ($title == $aliTitle) {
            return array(true, '文件模板匹配', $row[1]);
        } */

        $aliTitle = $row[4];
        sort($aliTitle);

        if (!array_diff($aliTitle, $title)) {
            return array(true, '文件模板匹配', $row[4]);
        }

        return array(false, '文件模板错误：' . var_export($row, true));
    }

    /**
     * 同步到对账表
     * @Author YangYiChao
     * @Date   2019-06-25
     * @param  Array   原始数据    $data
     * @param  String  具体类别    $bill_category
     * @return Boolean
     */
    public function syncToBill($data, $bill_category = '')
    {

        $data['content'] = json_decode(stripslashes($data['content']), 1);
        if (!$data['content']) {
            return false;
        }

        $tmp     = $data['content'];
        $shop_id = $data['shop_id'];

        $mdlBill        = app::get('finance')->model('bill');
        $oMonthlyReport = kernel::single('finance_monthly_report');

        $tmp['fee_obj']  = '淘宝';
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
            'trade_time'        => strtotime($tmp['trade_time']),
            'fee_obj'           => $tmp['fee_obj'],
            'money'             => $res['bill_type'] ? round($tmp['outcome_amount'], 2) : round($tmp['income_amount'], 2),
            'fee_item'          => $tmp['fee_item'],
            'fee_item_id'       => isset($this->fee_item_rules[$tmp['fee_item']]) ? $this->fee_item_rules[$tmp['fee_item']] : 0,
            'credential_number' => $tmp['financial_no'], // 流水号
            'member'            => $tmp['member'],
            'memo'              => $tmp['remarks'],
            'unique_id'         => $data['unique_id'],
            'create_time'       => time(),
            'fee_type'          => $tmp['trade_type'],
            'fee_type_id'       => $res['fee_type_id'],
            'bill_type'         => $res['bill_type'],
            'charge_status'     => 1, // 流水直接设置记账成功
            'charge_time'       => time(),
        );
        $base_sdf['monthly_id'] = 0;
        $base_sdf['monthly_item_id']     = 0;
        $base_sdf['monthly_status'] = 0;
        
        $base_sdf['crc32_order_bn']  = sprintf('%u', crc32($base_sdf['order_bn']));
        $base_sdf['bill_bn']         = $mdlBill->gen_bill_bn();
        $base_sdf['unconfirm_money'] = $base_sdf['money'];

        if ($mdlBill->insert($base_sdf)) {
            kernel::single('finance_monthly_report_items')->dealBillMatchReport($base_sdf['bill_id']);
            return true;
        }
        return false;
    }

    // 更新订单号
    /**
     * 更新OrderBn
     * @param mixed $data 数据
     * @return mixed 返回值
     */
    public function updateOrderBn($data)
    {
        $this->_formatData($data);
        $mdlBill = app::get('finance')->model('bill');
        if (!$this->shop_list_by_name) {
            $this->shop_list_by_name = financebase_func::getShopList(financebase_func::getShopType());
            $this->shop_list_by_name = array_column($this->shop_list_by_name, null, 'name');
        }

        foreach ($data as $v) {
            if ('账务流水号' == $v[0]) {
                continue;
            }

            if (!$v[13] || !$v[14] || !$v[12]) {
                continue;
            }

            $shop_id = isset($this->shop_list_by_name[$v[12]]) ? $this->shop_list_by_name[$v[12]]['shop_id'] : 0;
            if (!$shop_id) {
                continue;
            }

            $filter = array('bill_bn' => $v[13], 'shop_id' => $shop_id);

            // 找到unique_id
            $bill_info = $mdlBill->getList('unique_id,bill_id', $filter, 0, 1);
            if (!$bill_info) {
                continue;
            }

            $bill_info = $bill_info[0];

            if ($mdlBill->update(array('order_bn' => $v[14]), array('bill_id' => $bill_info['bill_id']))) {
                app::get('financebase')->model('bill')->update(array('order_bn' => $v[14]), array('unique_id' => $bill_info['unique_id'], 'shop_id' => $shop_id));
                $op_name = kernel::single('desktop_user')->get_name();
                $content = sprintf("订单号改成：%s", $v[14]);
                finance_func::addOpLog($v[13], $op_name, $content, '更新订单号');

            }

        }

    }

    /**
     * _filterData
     * @param mixed $data 数据
     * @return mixed 返回值
     */
    public function _filterData($data)
    {
        $new_data = array();

        $new_data['order_bn']     = $data['order_bn'] ? $data['order_bn'] : $this->_getOrderBn($data);
        $new_data['financial_no'] = $data['financial_no'];
        $new_data['out_trade_no'] = $data['out_trade_no'];
        $new_data['trade_time']   = strtotime($data['trade_time']);
        $new_data['member']       = $data['member'];
        $new_data['trade_type']   = $data['trade_type'];
        $new_data['trade_no']     = $data['trade_no'];
        if (0 < $data['income_amount']) {
            $new_data['money'] = $data['income_amount'];
        } else {
            $new_data['money'] = $data['outcome_amount'];
        }
        $new_data['unique_id'] = md5($data['trade_no'] . $data['out_trade_no'] . $data['financial_no']);

        $new_data['platform_type'] = 'alipay';
        $new_data['remarks']       = $data['remarks'];

        return $new_data;
    }

    /**
     * 获取ImportDateColunm
     * @param mixed $title title
     * @return mixed 返回结果
     */
    public function getImportDateColunm($title=null)
    {
        $timezone = defined('DEFAULT_TIMEZONE') ? DEFAULT_TIMEZONE : 0;
        return array('column' => array(5), 'time_diff' => $timezone * 3600);
    }

}
