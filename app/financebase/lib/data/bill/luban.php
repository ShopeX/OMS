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
 * 处理抖音下载
 *
 * @author 334395174@qq.com
 * @version 0.1
 */
class financebase_data_bill_luban extends financebase_abstract_bill
{
    /** 横向拆行列：订单实付应结 → 其他分成（不含免佣金额） */
    public $column_to_row = [
        'yi_jie',
        'ping_tai_bu_tie',
        'di_kou',
        'zheng_fu_bu_tie',
        'da_ren_bu_tie',
        'zhi_fu_bu_tie',
        'yin_xiao_bu_tie',
        'yin_hang_bu_tie',
        'tui_kuan',
        'yu_fei_shi_fu',
        'yu_fei_bu_tie',
        'ping_tai_fu_wu_fei',
        'yong_jin',
        'fu_wu_shang_yong_jin',
        'qu_dao_fen_cheng',
        'zhao_shang_fwf',
        'zhan_wai_tui_fei',
        'qi_ta_feng_cheng',
    ];

    /**
     * batchTransferData
     * @param mixed $data 数据
     * @param mixed $title title
     * @return mixed 返回值
     */

    public function batchTransferData($data, $title) {
        // 1. API 自动拉取：将 shop_id 格式转换为与 Excel 导入一致的表头/行数据
        if(isset($title[0]) && ($title[0] == "\u{FEFF}shop_id" || $title[0] == "shop_id")) {
            list($res, $content) = $this->convertApiFile($data,$title);
            if (!$res) {
                return array(false, implode('；', $content));
            }
            $data = $content;
            $title = $content[0];
        }
    
        if (!$this->ioTitle) {
            $this->ioTitle = $this->getTitle();
        }

        // 2. 建立 Excel 列索引 → 内部字段名 的映射
        $titleKey = array();
        foreach ($title as $k => $t) {
            $titleKey[$k] = array_search($t, $this->getTitle());
        }
        $reData = [];
        foreach($data as $row) {
            $row = array_map('trim', $row);
            if($row[0] == '动账时间' || $row[0] == "\u{FEFF}动账时间") {
                continue;
            }
            $tmpRow = [];
            foreach($row as $k => $v) {
                if($titleKey[$k]) {
                    $tmpRow[$titleKey[$k]] = $v;
                }
            }
            // 3. 列转行：每个非 0 横向金额列拆成一条流水，业务类型取列名
            foreach($this->column_to_row as $crv) {
                if($tmpRow[$crv] != 0) {
                    $tmp = $tmpRow;
                    $tmp['amount'] = $tmpRow[$crv];
                    $tmp['trade_type'] = $this->ioTitle[$crv];
                    $reData[] = $tmp;
                }
            }
            // 4. 兜底：横向各列均为 0 且动账金额非 0 时，用动账场景/动账金额/动账方向生成一条流水
            //    （正负相抵如 yi_jie=100、tui_kuan=-100 不触发，因各列并非全 0）
            if ($this->isAllHorizontalAmountZero($tmpRow)
                && round(floatval($tmpRow['jin_e'] ?? 0), 2) != 0) {
                $tmp = $tmpRow;
                $tmp['amount'] = $this->resolveLubanAmountByDirection(
                    $tmpRow['jin_e'],
                    isset($tmpRow['fang_xian']) ? $tmpRow['fang_xian'] : ''
                );
                $changJin = trim((string) (isset($tmpRow['chang_jin']) ? $tmpRow['chang_jin'] : ''));
                $tmp['trade_type'] = $changJin !== '' ? $changJin : '动账';
                $reData[] = $tmp;
            }
        }
        return $reData;
    }

    /** 横向拆行列是否全部为 0（按列判断，非合计） */
    private function isAllHorizontalAmountZero(array $row)
    {
        foreach ($this->column_to_row as $field) {
            if (round(floatval(isset($row[$field]) ? $row[$field] : 0), 2) != 0) {
                return false;
            }
        }
        return true;
    }

    /** 兜底行金额：出账为负，入账为正 */
    private function resolveLubanAmountByDirection($amount, $direction)
    {
        $amount = round(abs(floatval($amount)), 2);
        $direction = trim((string) $direction);
        if ($direction === '' || strpos($direction, '出账') !== false) {
            return -$amount;
        }
        return $amount;
    }

    // 处理数据
    /**
     * 获取Sdf
     * @param mixed $row row
     * @param mixed $offset offset
     * @param mixed $title title
     * @return mixed 返回结果
     */
    public function getSdf($row,$offset=1, $title)
    {
        $res = array('status'=>true,'data'=>array(),'msg'=>'');
        $tmp = [];
        //判断参数不能为空（独立动账如权益保险可无订单号，动账流水号仍必填）
        foreach ($row as $k => $v) {
            $tmp[$k] = trim($v, '\'');

            if(in_array($k, array('trade_no','trade_type')))
            {
                if(!$v)
                {
                    $res['status'] = false;
                    $res['msg'] =  sprintf("%s : %s 不能为空！", $row['trade_no'], $this->ioTitle[$k]);
                    return $res;
                }
            }

            if(in_array($k, array('trade_time')))
            {
                $result = finance_io_bill_verify::isDate($v);
                if ($result['status'] == 'fail') 
                {
                    $res['status'] = false;
                    $res['msg'] =  sprintf("%s : %s 时间(%s)格式错误！", $row['trade_no'], $this->ioTitle[$k], $v);
                    return $res;
                }
            }

            if(in_array($k, array('amount')))
            {
                $result = finance_io_bill_verify::isPrice($v);
                if ($result['status'] == 'fail') 
                {
                    $res['status'] = false;
                    $res['msg'] =  sprintf("%s : %s 金额(%s)格式错误！", $row['trade_no'], $this->ioTitle[$k], $v);
                    return $res;
                }
            }
        }


        $res['data'] = $tmp;


        return $res;
    }

    /**
     * 获取Title
     * @return mixed 返回结果
     */
    public function getTitle()
    {

        $title = array(
            'trade_time'            => '动账时间',
            'order_no'              => '订单号',
            'trade_no'              => '动帐流水号',
            'financial_no'          => '子订单号',
            'order_type'            => '订单类型',
            'goods_bn'              => '商品ID',
            'member'                => '动账账户',
            'remarks'               => '备注',
            'yi_jie'                => '订单实付应结',
            'ping_tai_bu_tie'       => '实际平台补贴',
            'di_kou'                => '以旧换新抵扣',
            'zheng_fu_bu_tie'       => '政府补贴平台垫资',
            'da_ren_bu_tie'         => '实际达人补贴',
            'zhi_fu_bu_tie'         => '实际抖音支付补贴',
            'yin_xiao_bu_tie'       => '实际抖音月付营销补贴',
            'yin_hang_bu_tie'       => '银行补贴',
            'tui_kuan'              => '订单退款',
            'fang_xian'             => '动账方向',
            'jin_e'                 => '动账金额',
            'chang_jin'             => '动账场景',
            'ji_fei'                => '计费类型',
            'shou_hou_bian_hao'     => '售后编号',
            'xia_dan_shi_jian'      => '下单时间',
            'yu_fei_shi_fu'         => '运费实付',
            'yu_fei_bu_tie'         => '实际平台补贴_运费',
            'ping_tai_fu_wu_fei'    => '平台服务费',
            'yong_jin'              => '佣金',
            'fu_wu_shang_yong_jin'  => '服务商佣金',
            'qu_dao_fen_cheng'      => '渠道分成',
            'zhao_shang_fwf'        => '招商服务费',
            'zhan_wai_tui_fei'      => '站外推广费',
            'qi_ta_feng_cheng'      => '其他分成',
            'shi_fou_mian_yong'     => '是否免佣',
            'mian_yong_jin_e'       => '免佣金额',
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
        
        return $params['order_no'];
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
        if(!$this->rules) $this->getRules('luban');

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
        $ioType = kernel::single('financebase_io_' . $file_type);
        $row    = $ioType->getData($file_name, 0, 5);
        $row[0][0] = str_replace("\u{FEFF}", "", $row[0][0]);
        //通过api接口获取到数据格式转换
        $_title = $row[0];//原始表头
    
        if (isset($row[0][0]) && $row[0][0] == 'shop_id') {
            list($res, $data) = $this->convertApiFile($row,$_title);
            if (!$res) {
                return array(false, implode('；', $data));
            }
            $row = $data;
        }
        
        $title = array_values($this->getTitle());

        $plateTitle = $row[0];
        foreach($title as $v) {
            if(array_search($v, $plateTitle) === false) {
                return array(false, '文件模板错误：列【'.$v.'】未包含在'.implode('、', $plateTitle) );
            }
        }
        return array(true, '文件模板匹配', $_title);
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

        $tmp['fee_obj'] = '抖音';
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
            'trade_time'        => strtotime($tmp['trade_time']),
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
        if(!$this->shop_list_by_name)
        {
            $this->shop_list_by_name = financebase_func::getShopList(financebase_func::getShopType());
            $this->shop_list_by_name = array_column($this->shop_list_by_name,null,'name');
        }

        foreach ($data as $v) 
        {
            if('订单编号' == $v[0]) continue;

            if(!$v[21] || !$v[22] || !$v[23]) continue;

            $shop_id = isset($this->shop_list_by_name[$v[21]]) ? $this->shop_list_by_name[$v[21]]['shop_id'] : 0;
            if(!$shop_id) continue;

            $filter = array('bill_bn'=>$v[22],'shop_id'=>$shop_id);


            // 找到unique_id
            $bill_info = $mdlBill->getList('unique_id,bill_id',$filter,0,1);
            if(!$bill_info) continue;
            $bill_info = $bill_info[0];
             

            if($mdlBill->update(array('order_bn'=>$v[23]),array('bill_id'=>$bill_info['bill_id'])))
            {
                app::get('financebase')->model('bill')->update(array('order_bn'=>$v[23]),array('unique_id'=>$bill_info['unique_id'],'shop_id'=>$shop_id));
                $op_name = kernel::single('desktop_user')->get_name();
                $content = sprintf("订单号改成：%s",$v[23]);
                finance_func::addOpLog($v[22],$op_name,$content,'更新订单号');

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
        
        $new_data['order_bn'] = $this->_getOrderBn($data);
        $new_data['trade_no'] = $data['trade_no'];
        $new_data['financial_no'] = $data['financial_no'];
        $new_data['out_trade_no'] = $data['out_trade_no'];
        $new_data['trade_time'] = strtotime($data['trade_time']);
        $new_data['trade_type'] = $data['trade_type'];
        $new_data['money'] = $data['amount'];
        $new_data['member'] = $data['member'];
        $new_data['unique_id'] = md5($data['trade_no'] . '-' . $data['trade_type']);

        $new_data['platform_type'] = 'luban';
        $new_data['remarks'] = $data['remarks'];

        return $new_data;
    }

    /**
     * 获取ImportDateColunm
     * @param mixed $title title
     * @return mixed 返回结果
     */
    public function getImportDateColunm($title=null)
    {
        $timeColumn = ['动账时间','下单时间','bill_time','business_order_create_time'];
        $timeCol = [];
        foreach ($timeColumn as $v) {
            $k = array_search($v, $title);
             if($k !== false) {
                $timeCol[] = $k+1;
             }
         } 
        $timezone = defined('DEFAULT_TIMEZONE') ? DEFAULT_TIMEZONE : 0;
        return array('column'=>$timeCol,'time_diff'=>$timezone * 3600 );
    }
    
    /**
     * 接口获取数据格式转换
     * @param $rows 原数据
     * @param $title 原表头
     * @return array
     * @date 2024-12-24 4:44 下午
     */
    public function convertApiFile($rows,$title)
    {
        // 获取$b的头部字段
        $headers = array_values($this->getTitle());
        // 创建新的数组
        $newData[] = $headers;
        
        $mapping = $this->getApiTitle();//映射关系
        $msg     = [];

        // 弹出表头，最好在入口就去掉
        $title = array_map(function ($v) {
            return trim($v, "\u{FEFF}");
        }, $title);
        $line1 = array_map(function ($v) {
            return trim($v, "\u{FEFF}");
        }, $rows[0]);

        if ($title == $line1) {
            array_shift($rows);
        }

        // 遍历$a的数据行
        foreach ($rows as $row) {
//            if ($row === $rows[0]) continue; // 跳过头部
            $newRows = [];
            foreach ($headers as $header) {
                $mappedKey = array_search($mapping[$header], $title);
                if ($mappedKey !== false) {
                    $newRows[] = $row[$mappedKey];
                } else {
                    // 如果没有映射到的字段，抛出异常
                    $msg[] = sprintf('接口数据中缺少字段：%s/%s', $header, $mapping[$header]);
                }
            }
            $newData[] = $newRows;
        }
        
        if ($msg) {
            return [false, $msg];
        }
        return [true, $newData];
    }
    
    /**
     * 接口获取数据字段对应关系
     * @return string[]
     * @date 2024-10-24 5:00 下午
     */
    public function getApiTitle()
    {
        $title = array (
            '店铺id' => 'shop_id',
            '动账时间' => 'bill_time',
            '动帐流水号' => 'account_trade_no',
            '动账方向(类型)' => 'fund_flow',
            '动账方向' => 'fund_flow_desc',//动账方向描述
            '动账金额' => 'account_amount',
            '动账账户(类型)' => 'account_type',
            '动账账户' => 'account_type_desc',//动账账户描述
            '动账场景' => 'trans_scene',//动账场景描述
            '动账场景(类型)' => 'trans_scene_tag',
            '计费类型(类型)' => 'biz_type',
            '计费类型' => 'biz_type_desc',//计费类型描述
            '子订单号' => 'shop_order_id',
            '订单号' => 'shop_order_id',//店铺单号
            '售后编号' => 'after_sale_service_no',
            '下单时间' => 'business_order_create_time',
            '商品ID' => 'product_id',
            '订单类型(类型)' => 'order_type',
            '订单类型' => 'order_type_desc',//订单类型描述
            '订单实付应结' => 'pay_amount',
            '运费实付' => 'post_amount',//运费
            '实际平台补贴_运费' => 'post_promotion_amount',//平台补贴运费
            '实际平台补贴' => 'promotion_amount',
            '以旧换新抵扣' => 'recycler_amount',
            '实际达人补贴' => 'author_coupon_subsidy',
            '实际抖音支付补贴' => 'actual_zt_pay_promotion',
            '实际抖音月付营销补贴' => 'actual_zr_pay_promotion',//实际DOU分期营销补贴
            '银行补贴' => 'bank_pay_promotion_amount',
            '订单退款' => 'refund_amount',
            '平台服务费' => 'platform_service_fee',
            '佣金' => 'commission',
            '服务商佣金' => 'partner_commission_amount',
            '渠道分成' => 'channel_fee',
            '招商服务费' => 'colonel_service_fee',
            '站外推广费' => 'channel_promotion_fee',//直播间站外推广
            '其他分成' => 'other_sharing_amount',//其他分成金额
            '是否免佣' => 'free_commission_flag',
            '免佣金额' => 'real_free_commission_amount',
            '备注' => 'remark',
            
            '动账摘要描述' => 'account_bill_desc',
            '动账摘要' => 'account_bill_desc_tag',
            '店铺单号' => 'shop_order_id',
            '打包费' => 'packing_amount',
            '政府补贴平台垫资' => 'gov_promotion_amount',
            '商品名称' => 'product_name',
            '达人id' => 'author_id',
            '达人名称' => 'author_name',
        );
        return $title;
    }

}