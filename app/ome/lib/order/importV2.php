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
class ome_order_importV2  implements omecsv_data_split_interface
{
    // public $column_num = 45;
    public $current_order_bn = null;
    
    /** @var string 导入单据类型，来自 queue type（order / platform） */
    public $order_type = '';
    
    const BUFA_ORDER_SOURCES = ['补发订单', '零元订单补发', '零元订单补发单'];
    const BUFA_ORDER_CUSTOM_SOURCES = ['零元订单补发', '零元订单补发单', '拦截入库补发', '拦截入库补发单', '退货入库补发', '退货入库补发单'];

    const IMPORT_TITLE = [
        ['label' => '*:订单号', 'col' => 'order_bn'],
        ['label' => '*:下单时间', 'col' => 'createtime', 'format' => 'yyyy-mm-dd h:mm:ss'],
        ['label' => '来源渠道', 'col' => 'order_source'],
        ['label' => '关联订单号', 'col' => 'relate_order_bn'],
        ['label' => '补发原因', 'col' => 'bufa_reason'],
        ['label' => '配送方式', 'col' => 'shipping'],
        ['label' => '*:来源店铺编号', 'col' => 'shop_bn'],
        ['label' => '*:配送费用', 'col' => 'cost_freight'],
        ['label' => '来源店铺', 'col' => 'shop_name'],
        ['label' => '商家备注', 'col' => 'mark_text'],
        ['label' => '客户备注', 'col' => 'custom_mark'],
        ['label' => '*:收货人姓名', 'col' => 'ship_name'],
        ['label' => '*:收货地址省份', 'col' => 'ship_province'],
        ['label' => '*:收货地址城市', 'col' => 'ship_city'],
        ['label' => '*:收货地址区/县', 'col' => 'ship_district'],
        ['label' => '*:收货详细地址', 'col' => 'ship_addr'],
        ['label' => '收货人固定电话', 'col' => 'ship_tel'],
        ['label' => '电子邮箱', 'col' => 'ship_email'],
        ['label' => '*:收货人移动电话', 'col' => 'ship_mobile'],
        ['label' => '邮编', 'col' => 'ship_zip'],
        ['label' => '货到付款', 'col' => 'is_cod'],
        ['label' => '是否开发票', 'col' => 'is_tax'],
        ['label' => '发票抬头', 'col' => 'tax_company'],
        ['label' => '发票金额', 'col' => 'invoice_amount'],
        ['label' => '优惠方案', 'col' => 'order_pmt'],
        ['label' => '*:订单优惠金额', 'col' => 'pmt_order'],
        ['label' => '*:商品优惠金额', 'col' => 'pmt_goods'],
        ['label' => '*:折扣', 'col' => 'discount'],
        ['label' => '返点积分', 'col' => 'score_g'],
        ['label' => '*:商品总额', 'col' => 'cost_item'],
        ['label' => '*:订单总额', 'col' => 'total_amount'],
        ['label' => '买家会员名', 'col' => 'member_uname'],
        ['label' => '商品重量', 'col' => 'weight'],
        ['label' => '发票号', 'col' => 'tax_no'],
        // ['label' => '周期购', 'col' => 'createway'],
    ];

    const IMPORT_ITEM_TITLE = [
        ['label' => '*:销售物料编码(明细)', 'col' => 'e_sm_bn'],
        ['label' => '销售物料名称(明细)', 'col' => 'e_sm_name'],
        // ['label' => '销售物料类型(明细)', 'col' => 'e_sm_type'],
        // ['label' => '基础物料编码(明细)', 'col' => 'e_item_bn'],
        // ['label' => '基础物料名称(明细)', 'col' => 'e_item_product_name'],
        // ['label' => '规格(明细)', 'col' => 'e_spec_info'],
        // ['label' => '单位(明细)', 'col' => 'e_unit'],
        ['label' => '*:原价(明细)', 'col' => 'e_price'],
        ['label' => '*:销售价(明细)', 'col' => 'e_sale_price'],
        // ['label' => '优惠额(明细)', 'col' => 'e_pmt_price'],
        // ['label' => '实付金额(明细)', 'col' => 'e_divide_order_fee'],
        // ['label' => '优惠分摊(明细)', 'col' => 'e_part_mjz_discount'],
        ['label' => '*:购买量(明细)', 'col' => 'e_nums'],
        // ['label' => '已发货量(明细)', 'col' => 'e_sendnum'],
        // ['label' => '已退货量(明细)', 'col' => 'e_return_num'],
        // ['label' => '已拆分量(明细)', 'col' => 'e_split_num'],
        // ['label' => 'hold单时限(明细)', 'col' => 'e_estimate_con_time'],
        // ['label' => '是否预售(明细)', 'col' => 'e_presale_status'],
        // ['label' => '预选仓(明细)', 'col' => 'e_store_code'],
        // ['label' => '子单号(明细)', 'col' => 'e_oid'],
        // ['label' => '关联子单号(明细)', 'col' => 'e_main_oid'],
        // ['label' => '平台商品ID(明细)', 'col' => 'e_shop_goods_id'],
        // ['label' => '平台SkuID(明细)', 'col' => 'e_shop_product_id'],
        // ['label' => '达人ID(明细)', 'col' => 'e_author_id'],
        // ['label' => '达人名称(明细)', 'col' => 'e_author_name'],
        // ['label' => '直播间ID(明细)', 'col' => 'e_room_id'],
    ];

    /**
     * 导入模板示例行（按 exportTemplateV2 表头顺序展开）
     * 含 3 个示例订单：全量单明细、全量多明细、仅必填
     *
     * @param array $titleLabels 表头 label 列表
     * @return array 二维行数据（不含表头）
     */
    public function getTemplateDemoRows(array $titleLabels)
    {
        $shopBn   = '示例店铺编号';
        $shopName = '示例店铺名称';
        $smBn1    = '示例销售物料编码A';
        $smBn2    = '示例销售物料编码B';
        $smName1  = '示例销售物料名称A';
        $smName2  = '示例销售物料名称B';
        $createtime = '2026-01-01 10:00:00';

        // 订单1：单明细，必填+常用选填
        // cost_item=100, freight=10, discount/pmt=0 → total=110
        $order1 = [
            '*:订单号'           => 'DEMO-ORDER-001',
            '*:下单时间'         => $createtime,
            '来源渠道'           => 'direct',
            '配送方式'           => '快递',
            '*:来源店铺编号'     => $shopBn,
            '*:配送费用'         => '10',
            '来源店铺'           => $shopName,
            '商家备注'           => '示例商家备注',
            '客户备注'           => '示例客户备注',
            '*:收货人姓名'       => '张三',
            '*:收货地址省份'     => '上海市',
            '*:收货地址城市'     => '上海市',
            '*:收货地址区/县'    => '浦东新区',
            '*:收货详细地址'     => '示例路100号',
            '收货人固定电话'     => '021-12345678',
            '电子邮箱'           => 'demo@example.com',
            '*:收货人移动电话'   => '13800138000',
            '邮编'               => '200000',
            '货到付款'           => '否',
            '是否开发票'         => '是',
            '发票抬头'           => '示例发票抬头',
            '发票金额'           => '110',
            '优惠方案'           => '',
            '*:订单优惠金额'     => '0',
            '*:商品优惠金额'     => '0',
            '*:折扣'             => '0',
            '返点积分'           => '0',
            '*:商品总额'         => '100',
            '*:订单总额'         => '110',
            '买家会员名'         => 'demo_buyer',
            '商品重量'           => '1',
            '发票号'             => 'DEMO-TAX-001',
            '*:销售物料编码(明细)' => $smBn1,
            '销售物料名称(明细)'   => $smName1,
            '*:原价(明细)'         => '100',
            '*:销售价(明细)'       => '100',
            '*:购买量(明细)'       => '1',
        ];

        // 订单2：两行明细（第二行订单号留空，展示主表继承）
        // 行1: 50×1, 行2: 50×1 → cost_item=100, freight=10 → total=110
        $order2Header = [
            '*:订单号'           => 'DEMO-ORDER-002',
            '*:下单时间'         => $createtime,
            '来源渠道'           => 'direct',
            '配送方式'           => '快递',
            '*:来源店铺编号'     => $shopBn,
            '*:配送费用'         => '10',
            '来源店铺'           => $shopName,
            '商家备注'           => '多明细示例',
            '客户备注'           => '',
            '*:收货人姓名'       => '李四',
            '*:收货地址省份'     => '浙江省',
            '*:收货地址城市'     => '杭州市',
            '*:收货地址区/县'    => '西湖区',
            '*:收货详细地址'     => '示例大道200号',
            '收货人固定电话'     => '',
            '电子邮箱'           => '',
            '*:收货人移动电话'   => '13900139000',
            '邮编'               => '310000',
            '货到付款'           => '否',
            '是否开发票'         => '否',
            '发票抬头'           => '',
            '发票金额'           => '0',
            '优惠方案'           => '',
            '*:订单优惠金额'     => '0',
            '*:商品优惠金额'     => '0',
            '*:折扣'             => '0',
            '返点积分'           => '0',
            '*:商品总额'         => '100',
            '*:订单总额'         => '110',
            '买家会员名'         => 'demo_buyer2',
            '商品重量'           => '2',
            '发票号'             => '',
            '*:销售物料编码(明细)' => $smBn1,
            '销售物料名称(明细)'   => $smName1,
            '*:原价(明细)'         => '50',
            '*:销售价(明细)'       => '50',
            '*:购买量(明细)'       => '1',
        ];
        $order2Item2 = [
            '*:销售物料编码(明细)' => $smBn2,
            '销售物料名称(明细)'   => $smName2,
            '*:原价(明细)'         => '50',
            '*:销售价(明细)'       => '50',
            '*:购买量(明细)'       => '1',
        ];

        // 订单3：仅必填列
        // cost_item=100, freight=10 → total=110
        $order3 = [
            '*:订单号'           => 'DEMO-ORDER-003',
            '*:下单时间'         => $createtime,
            '*:来源店铺编号'     => $shopBn,
            '*:配送费用'         => '10',
            '*:收货人姓名'       => '王五',
            '*:收货地址省份'     => '广东省',
            '*:收货地址城市'     => '广州市',
            '*:收货地址区/县'    => '天河区',
            '*:收货详细地址'     => '示例街300号',
            '*:收货人移动电话'   => '13700137000',
            '*:订单优惠金额'     => '0',
            '*:商品优惠金额'     => '0',
            '*:折扣'             => '0',
            '*:商品总额'         => '100',
            '*:订单总额'         => '110',
            '*:销售物料编码(明细)' => $smBn1,
            '*:原价(明细)'         => '100',
            '*:销售价(明细)'       => '100',
            '*:购买量(明细)'       => '1',
        ];

        return [
            $this->mapDemoRowByLabels($titleLabels, $order1),
            $this->mapDemoRowByLabels($titleLabels, $order2Header),
            $this->mapDemoRowByLabels($titleLabels, $order2Item2),
            $this->mapDemoRowByLabels($titleLabels, $order3),
        ];
    }

    /**
     * 按表头 label 顺序将关联数组展开为一行
     *
     * @param array $titleLabels
     * @param array $valuesByLabel label => value
     * @return array
     */
    private function mapDemoRowByLabels(array $titleLabels, array $valuesByLabel)
    {
        $row = [];
        foreach ($titleLabels as $label) {
            $row[] = array_key_exists($label, $valuesByLabel) ? (string)$valuesByLabel[$label] : '';
        }
        return $row;
    }

    /**
     * 检查文件是否有效
     * @param $file_name 文件名
     * @param $file_type 文件类型
     * @param $queue_data 请求参数
     * @return array
     * @date 2024-06-06 3:52 下午
     */
    public function checkFile($file_name, $file_type,$queue_data)
    {
        $orderMdl   = app::get('ome')->model('orders');
        $shopMdl    = app::get('ome')->model('shop');
        $arOMdl     = app::get('archive')->model('orders');
        $salesMLib = kernel::single('material_sales_material');

        $ioType = kernel::single('omecsv_io_split_' . $file_type);
        $rows   = $ioType->getData($file_name, 0, -1);

        $summaryTitle = array_merge(self::IMPORT_TITLE, self::IMPORT_ITEM_TITLE);
        // $cols    = array_column($summaryTitle, 'col');
        $oSchema = array_column($summaryTitle, 'label', 'col');

        // 获取系统必填标题
        $requiredTitle = [];  // 必填标题
        foreach ($summaryTitle as $k => $v) {
            if ('*:' == substr($v['label'], 0, 2)) {
                $requiredTitle[] = $v;
            }
        }
        $requiredLabel = array_column($requiredTitle, 'label');

        $shopList = $shopMdl->getList('shop_id,shop_bn,shop_type,name,s_type');
        $shopList = array_column($shopList, null, 'shop_bn');

        $previousRow = []; // 前一条数据
        $importTitle = [];  // 导入的标题
        $orderPrice  = []; // 订单价格相关数据
        $errors      = []; // 入队前全量预检错误汇总
        $checkedExistOrderBn = []; // 同单多明细只查一次订单号是否已存在
        $orderPriceInvalid = []; // 有行级错误的订单，跳过金额勾稽
        foreach ($rows as $key => $row) {
            if ($key == 0) {  
                $importTitle = $row;
                $_required_title = [];
                foreach ($row as $k => $v) {
                    if (in_array($v, $requiredLabel)) {
                        $_required_title[] = $v;
                    }
                }
                if (!$this->checkTitle($_required_title, $requiredLabel)) {
                    return array(false, '导入模板不正确', $row);
                }
                if ($row[0]!='*:订单号') {
                    return array(false, '导入模板第一列必须是"*:订单号"', $row);
                }

            } else {

                
                $titleKey = array();
                $_importTitle = $importTitle;
                foreach ($importTitle as $k => $t) {
                    $titleKey[$k] = array_search($t, $oSchema);
                    if ($titleKey[$k] === false) {
                        unset($titleKey[$k]);
                        unset($row[$k]);
                        unset($_importTitle[$k]);
                    }
                }
                $_importTitle = array_values($_importTitle);
                $row = array_values($row);
                
                // 如果当前行没有订单号,或者订单号与上一行相同，则主表数据以第一行的为准进行覆盖
                if ((!$row[0] || $row[0] == $previousRow['*:订单号']) && $previousRow) {
                    $_num = 0;
                    foreach ($previousRow as $_k => $_v) {
                        // 非明细字段
                        if ('(明细)' !== substr($_k, -8)) {
                            $row[$_num] = $_v;
                        }
                        $_num++;
                    }
                }

                // Excel/CSV 常会丢弃行尾空单元格，导致列数与标题不一致；对齐后再 combine
                $titleKeyCount = count($titleKey);
                $rowCount = count($row);
                if ($rowCount > $titleKeyCount) {
                    $row = array_slice($row, 0, $titleKeyCount);
                } elseif ($rowCount < $titleKeyCount) {
                    $row = array_pad($row, $titleKeyCount, '');
                }
                
                $buffer = array_combine($titleKey, $row);
                $buffer['order_bn'] = $this->normalizeImportOrderBn($buffer['order_bn'] ?? '');
                $orderBnLabel = $buffer['order_bn'];
                $orderBnPrefix = $orderBnLabel !== '' ? sprintf('订单[%s]', $orderBnLabel) : '';
                $rowPrefix = sprintf('第%d行%s', $key + 1, $orderBnPrefix);
                $rowErrors = [];

                // 数据验证（同行多错尽量全部收集）
                $isBufaOrder = in_array(trim($buffer['order_source']), self::BUFA_ORDER_SOURCES);
                foreach ($buffer as $k => $v) {
                    if ($isBufaOrder){
                        if ('*:' == substr($oSchema[$k], 0, 2) && $v === '' && !in_array($k, ['ship_name','ship_province','ship_city','ship_district','ship_addr','ship_mobile','ship_tel', 'createtime'])) {
                            $rowErrors[] = sprintf('%s%s此项必填', $rowPrefix, $oSchema[$k]);
                        }
                        
                        // 补发原因必填
                        if ($k == 'bufa_reason' && $v === '') {
                            $rowErrors[] = sprintf('%s%s此项必填', $rowPrefix, $oSchema[$k]);
                        }
                    } else {
                        if ('*:' == substr($oSchema[$k], 0, 2) && $v === '') {
                            if(!in_array($k, ['createtime'])){
                                $rowErrors[] = sprintf('%s%s必填', $rowPrefix, $oSchema[$k]);
                            }
                        }
                    }
                    
                    if ('时间' == substr($oSchema[$k], -1, 2) && $v) {
                        $check_time = kernel::single('ome_func')->parseDateTimeCell($v);
                        if(!$check_time){
                            $rowErrors[] = sprintf('%s%s需转文本格式', $rowPrefix, $oSchema[$k]);
                        }
                    }
                }

                // 金额/数量/手机号格式
                $formatMsgs = $this->validateImportRowFormat($buffer, $oSchema, $rowPrefix);
                if ($formatMsgs) {
                    $rowErrors = array_merge($rowErrors, $formatMsgs);
                }

                // 判断店铺是否存在
                $shop = null;
                if (isset($buffer['shop_bn'])) {
                    $buffer['shop_bn'] = trim((string)$buffer['shop_bn']);
                }
                if ($buffer['shop_bn'] !== '' && $buffer['shop_bn'] !== null) {
                    $shop = $shopList[$buffer['shop_bn']] ?? null;
                    if (!$shop) {
                        $rowErrors[] = sprintf('%s来源店铺编号[%s]不存在', $rowPrefix, $buffer['shop_bn']);
                    }
                }

                // 订单号查重：与 create_order 一致（同店同单号）；归档按 order_bn
                if ($buffer['order_bn'] !== '' && !isset($checkedExistOrderBn[$buffer['order_bn']])) {
                    $checkedExistOrderBn[$buffer['order_bn']] = true;
                    if ($shop && $orderMdl->dump(['order_bn' => $buffer['order_bn'], 'shop_id' => $shop['shop_id']], 'order_id')) {
                        $rowErrors[] = sprintf('%s订单号已经存在', $rowPrefix);
                    }

                    if ($arOMdl->dump(['order_bn' => $buffer['order_bn']], 'order_id')) {
                        $rowErrors[] = sprintf('%s订单号已经存在归档', $rowPrefix);
                    }
                }
                
                // 获取关联订单号的信息
                $relateOrderInfo = [];
                if($buffer['relate_order_bn']){
                    $relateOrderInfo = $orderMdl->dump(array('order_bn'=>$buffer['relate_order_bn']), 'order_id');
                    if(empty($relateOrderInfo)){
                        $rowErrors[] = sprintf('%s关联订单号[%s]不存在', $rowPrefix, $buffer['relate_order_bn']);
                    }
                }
                
                // 定制补发：无关联单时强制校验收货人
                $is_verify_consignee = in_array(trim($buffer['order_source']), self::BUFA_ORDER_CUSTOM_SOURCES);
                
                // 验证收货人信息
                if($is_verify_consignee && empty($relateOrderInfo)){
                    if(empty($buffer['ship_name'])){
                        $rowErrors[] = sprintf('%s收货人姓名不能为空', $rowPrefix);
                    }
                    
                    if(empty($buffer['ship_mobile']) && empty($buffer['ship_tel'])){
                        $rowErrors[] = sprintf('%s收货人移动电话、固定电话，至少填写一项', $rowPrefix);
                    }
                    
                    if(empty($buffer['ship_province']) || empty($buffer['ship_city']) || empty($buffer['ship_district'])){
                        $rowErrors[] = sprintf('%s收货人省、市、区，必须填写', $rowPrefix);
                    }
                    
                    if(empty($buffer['ship_addr'])){
                        $rowErrors[] = sprintf('%s收货人地址不能为空', $rowPrefix);
                    }
                }
                
                // 销售物料：一律预检（普通/平台/补发）；店铺无效时跳过以免连锁脏报错
                if ($shop && $buffer['e_sm_bn']) {
                    $salesMInfo = $salesMLib->getSalesMByBn($buffer['shop_bn'], $buffer['e_sm_bn']);
                    if (empty($salesMInfo)) {
                        $rowErrors[] = sprintf('%s销售物料[%s]在店铺[%s]下不存在', $rowPrefix, $buffer['e_sm_bn'], $buffer['shop_bn']);
                    }
                }

                if ($rowErrors) {
                    $errors = array_merge($errors, $rowErrors);
                    if ($buffer['order_bn'] !== '') {
                        $orderPriceInvalid[$buffer['order_bn']] = true;
                    }
                } else {
                    // 订单头金额仅取该单首行成功记录，避免后续明细行覆盖；明细逐行追加
                    if (!isset($orderPrice[$buffer['order_bn']])) {
                        $orderPrice[$buffer['order_bn']] = [
                            'cost_freight' => $buffer['cost_freight'],
                            'discount'     => $buffer['discount'],
                            'pmt_order'    => $buffer['pmt_order'],
                            'cost_item'    => $buffer['cost_item'],
                            'total_amount' => $buffer['total_amount'],
                            'order_source' => trim($buffer['order_source']),
                            'items'        => [],
                        ];
                    }
                    $orderPrice[$buffer['order_bn']]['items'][] = [
                        'e_price'       =>  $buffer['e_price'],
                        'e_sale_price'  =>  $buffer['e_sale_price'],
                        'e_nums'        =>  $buffer['e_nums'],
                    ];
                }

                $previousRow = array_combine($_importTitle, $row); // 在最后，保存前一条数据,给下个循环使用,因为同一张订单主表信息只有第一条有
            }
        }

        // 有行级错误的订单不参与金额勾稽，避免误报「总额与明细不一致」
        foreach ($orderPriceInvalid as $bn => $_) {
            unset($orderPrice[$bn]);
        }

        // 检查金额（汇总全部订单错误）
        foreach ($orderPrice as $_order_bn => $_order) {
            $item_amount = $item_pmt_price = 0;
            foreach ($_order['items'] as $_iv) {
                $e_price = $this->replace_import_price($_iv['e_price']);
                $e_sale_price = $this->replace_import_price($_iv['e_sale_price']);
                $e_nums = $this->replace_import_price($_iv['e_nums']);
                //行原价小计
                $item_amount_tmp = (float)$e_price * (float)$e_nums;
                $item_amount += $item_amount_tmp;
                //行商品优惠
                $item_pmt_price_tmp = $item_amount_tmp - ((float)$e_sale_price * (float)$e_nums);
                $item_pmt_price += $item_pmt_price_tmp;
            }

            // 检查金额
            $cost_item = $item_amount;
            $cost_freight = $this->replace_import_price($_order['cost_freight']);
            $cost_tax = 0;
            $discount = $this->replace_import_price($_order['discount']);
            $pmt_order = $this->replace_import_price($_order['pmt_order']);
            $pmt_goods = $item_pmt_price;
            $total_amount = (float)$cost_item + (float)$cost_freight + (float)$cost_tax - (float)$discount - (float)$pmt_order - (float)$pmt_goods;
            if (bccomp((float)$this->replace_import_price($_order['cost_item']), round($cost_item,2), 2) !== 0) {
                $errors[] = sprintf('订单[%s]订单商品总额[%s]明细与订单行商品行[%s]不一致', $_order_bn, $_order['cost_item'],  $cost_item);
            }
            
            // 零元订单补发单
            if(trim($_order['order_source']) == '零元订单补发'){
                $_order['order_source'] = '零元订单补发单';
            }
            
            if ($_order['order_source'] == '零元订单补发单' && bccomp((float)$total_amount, 0, 2) !== 0) {
                $errors[] = sprintf('订单[%s]来源渠道为[零元订单补发单]时，订单总额最终必须为0元，当前计算结果为[%s]', $_order_bn, round($total_amount, 2));
            }
            if (bccomp((float)$this->replace_import_price($_order['total_amount']), round($total_amount,2), 2) !== 0) {
                $errors[] = sprintf('订单[%s]订单总额[%s](商品总额[%s]+配送费用[%s]+税金[%s]-折扣[%s]-订单优惠[%s]-商品优惠[%s])不对',$_order_bn,$_order['total_amount'],$cost_item,$cost_freight,$cost_tax,$discount,$pmt_order,$pmt_goods);
            }
        }

        if ($errors) {
            return [false, implode('；', $errors), $rows[0] ?? []];
        }

        //导入文件内容验证
        return array(true, '文件模板匹配', $rows[0]);
    }

    /**
     * 导入行格式校验：金额/数量/手机号（返回全部格式错误文案）
     *
     * @param array $buffer col => value
     * @param array $oSchema col => label
     * @param string $rowPrefix 如 第3行订单[xxx]
     * @return string[]
     */
    private function validateImportRowFormat(array $buffer, array $oSchema, string $rowPrefix)
    {
        $msgs = [];
        $amountCols = ['cost_freight', 'pmt_order', 'pmt_goods', 'discount', 'cost_item', 'total_amount', 'e_price', 'e_sale_price'];
        foreach ($amountCols as $col) {
            if (!array_key_exists($col, $buffer) || $buffer[$col] === '' || $buffer[$col] === null) {
                continue;
            }
            $num = $this->replace_import_price($buffer[$col]);
            if (!is_numeric($num) || (float)$num < 0) {
                $label = $oSchema[$col] ?? $col;
                $msgs[] = sprintf('%s%s格式不正确', $rowPrefix, $label);
            }
        }

        if (array_key_exists('e_nums', $buffer) && $buffer['e_nums'] !== '' && $buffer['e_nums'] !== null) {
            $nums = $this->replace_import_price($buffer['e_nums']);
            if (!is_numeric($nums) || (float)$nums <= 0) {
                $label = $oSchema['e_nums'] ?? '*:购买量(明细)';
                $msgs[] = sprintf('%s%s格式不正确', $rowPrefix, $label);
            }
        }

        $mobile = isset($buffer['ship_mobile']) ? preg_replace('/\s+/', '', (string)$buffer['ship_mobile']) : '';
        if ($mobile !== '' && !preg_match('/^1\d{10}$/', $mobile)) {
            $label = $oSchema['ship_mobile'] ?? '*:收货人移动电话';
            $msgs[] = sprintf('%s%s格式不正确', $rowPrefix, $label);
        }

        return $msgs;
    }

    /**
     * 规范化导入订单号：去空白，避免 Excel 浮点/科学计数与库中单号对不上
     *
     * @param mixed $bn
     * @return string
     */
    private function normalizeImportOrderBn($bn)
    {
        $bn = trim((string)$bn);
        $bn = preg_replace('/^[\x{00A0}\x{200B}\s]+|[\x{00A0}\x{200B}\s]+$/u', '', $bn);
        if ($bn === '') {
            return '';
        }
        // Excel 常把长数字读成 12345.0
        if (preg_match('/^\d+\.0+$/', $bn)) {
            return (string)intval($bn);
        }
        // 科学计数法（如 1.23456789012345E+14）
        if (preg_match('/^\d+(\.\d+)?[eE][+\-]?\d+$/', $bn)) {
            $asFloat = (float)$bn;
            if (is_finite($asFloat) && $asFloat == floor($asFloat) && $asFloat < 1e15) {
                return sprintf('%.0f', $asFloat);
            }
        }
        return $bn;
    }

    /**
     * 检查两个数组中的标题是否相同
     * 此函数通过将数组中的每个项目转换为JSON字符串，然后对这些字符串进行排序，最后比较两个结果数组来实现
     * 这种方法可以忽略数组中相同标题的不同顺序
     *
     * @param array $array1 第一个数组，包含标题信息
     * @param array $array2 第二个数组，包含标题信息
     * @return bool 如果两个数组中的标题完全匹配，则返回true；否则返回false
     */
    function checkTitle($array1, $array2) {
        // 如果数组长度不同，直接返回false
        if (count($array1) !== count($array2)) {
            return false;
        }
        
        // 将数组转换为字符串形式以便比较
        $array1Strings = array_map(function($item) {
            return json_encode($item, JSON_UNESCAPED_UNICODE);
        }, $array1);
        
        $array2Strings = array_map(function($item) {
            return json_encode($item, JSON_UNESCAPED_UNICODE);
        }, $array2);
        
        // 对字符串数组进行排序
        sort($array1Strings);
        sort($array2Strings);

        // 比较排序后的数组
        return $array1Strings === $array2Strings;
    }

    /**
     * 每页切分数量
     * @param $key
     * @return int|int[]
     * @date 2024-09-05 6:03 下午
     */
    public function getConfig($key = '')
    {
        $config = array(
            'page_size' => 200,
        );
        return $key ? $config[$key] : $config;
    }

    /**
     * 是否是同一个订单明细行检测
     * @param $row
     * @return bool
     * @date 2024-09-05 4:59 下午
     */
    public function is_split($row)
    {
        $is_split = false;
        if ($row['0']) {
            if ($row['0'] !== $this->current_order_bn) {
                if ($this->current_order_bn !== null) {
                    $is_split = true;
                }
                $this->current_order_bn = $row['0'];//订单号
            }
        }
        return $is_split;
    }

    /**
     * 订单切片导入逻辑处理
     * @param $cursor_id
     * @param $params
     * @param $errmsg
     * @return bool[]
     * @date 2024-09-05 9:58 上午
     */
    public function process($cursor_id, $params, &$errmsg)
    {
        @ini_set('memory_limit', '128M');
        $oFunc = kernel::single('omecsv_func');
        $queueMdl     = app::get('omecsv')->model('queue');
    
        $oFunc->writelog('处理任务-开始', 'settlement', $params);
        
        //业务逻辑处理
        $data = $params['data'];
        
        // order_type
        $this->order_type = isset($params['type']) ? $params['type'] : ($params['queue_data']['type'] ?? '');
        
        // whitelist type=order 对应普通/补发导入，落库 order_type 由业务字段决定
        if ($this->order_type === 'order') {
            $this->order_type = '';
        }
        
        $sdf = [];
        $offset      = intval($data['offset']) + 1;//文件行数 行数默认从1开始
        $splitCount  = 0;//执行行数
        if($data){
            $previousRow = []; // 前一条数据
            
            foreach($data as $row){
                $res = $this->getSdf($row, $offset, $params['title'], $previousRow);
                
                if ($res['status'] and $res['data']) {
                    $tmp = $res['data'];
                    $this->_formatData($tmp);
                    $sdf[] = $tmp;
                } elseif (!$res['status']) {
                    array_push($errmsg, $res['msg']);
                }
                
                //包含表头
                if ($res['status']) {
                    $splitCount++;
                }
                $offset++;
            }
        }
        unset($data);
        //创建订单
        if ($sdf) {
            list($result,$msgList) = $this->implodeOrders($sdf);
            if($msgList){
                $errmsg = array_merge($errmsg, $msgList);
            }
            $queueMdl->update(['original_bn' => 'order', 'split_count' => $splitCount], ['queue_id' => $cursor_id]);
        }
        
        //任务数据统计更新等
        $oFunc->writelog('处理任务-完成', 'settlement', 'Done');
        return [true];
    }

    /**
     * 导入文件表头定义
     * @date 2024-06-06 3:52 下午
     */
    
     public function getSdf($row, $offset = 1, $title, &$previousRow)
     {
        $res = array('status' => true, 'data' => array(), 'msg' => '');

        $row = array_map('trim', $row);
         
        $summaryTitle = array_merge(self::IMPORT_TITLE, self::IMPORT_ITEM_TITLE);
        $oSchema = array_column($summaryTitle, 'label', 'col');
         
        $titleKey = array();
        $_title = $title;
        foreach ($title as $k => $t) {
            $titleKey[$k] = array_search($t, $oSchema);
            if ($titleKey[$k] === false) {
                unset($titleKey[$k]);
                unset($row[$k]);
                unset($_title[$k]);
            }
        }
        $_title = array_values($_title);
         $row = array_values($row);

        // $row_num = count($row);
        // if ($this->column_num <= $row_num && $row[0] != '*:订单号') {
        if ($row[0] != '*:订单号') {

            // 如果当前行没有订单号,或者订单号与上一行相同，则主表数据以第一行的为准进行覆盖
            if ((!$row[0] || $row[0] == $previousRow['*:订单号']) && $previousRow) {
                $_num = 0;
                foreach ($previousRow as $_k => $_v) {
                    // 非明细字段
                    if ('(明细)' !== substr($_k, -8)) {
                        $row[$_num] = $_v;
                    }
                    $_num++;
                }
            }
        }

        // Excel/CSV 常会丢弃行尾空单元格，导致列数与标题不一致；对齐后再 combine
        $titleKeyCount = count($titleKey);
        $rowCount = count($row);
        if ($rowCount > $titleKeyCount) {
            $row = array_slice($row, 0, $titleKeyCount);
        } elseif ($rowCount < $titleKeyCount) {
            $row = array_pad($row, $titleKeyCount, '');
        }

        if ($row[0] != '*:订单号') {
            $res['data'] = array_combine($titleKey, $row);
        }
        
        $previousRow = array_combine($_title, $row); // 在最后，保存前一条数据,给下个循环使用,因为同一张订单主表信息只有第一条有
        return $res;
     }

     public function _formatData(&$data)
     {
        foreach ($data as $k => $str) {
            $data[$k] = str_replace(array("\r\n", "\r", "\n", "\t"), "", $str);
        }
     }
     
    function implodeOrders($contents)
    {
        $orderMdl = app::get('ome')->model('orders');
        $oFunc    = kernel::single('omecsv_func');
        
        //格式化订单数据结构
        list($orderList, $errMsg) = $this->addOrderFormat($contents);
        //使用队列创建订单
        foreach($orderList as $order){
            if (!isset($order['order_objects']) || !$order['order_objects']) {
                $errMsg[] = sprintf('订单【%s】商品货号不存在', $order['order_bn']);
                $oFunc->writelog(sprintf('商品货号不存在：【%s】', $order['order_bn']), 'settlement', '商品货号在销售物料中不存在');
                continue;
            }
            $m = '';
            if (!$orderMdl->create_order($order, $m)) {
                if (!empty($m)) {
                    $errMsg[] = sprintf('订单【%s】创建失败:%s', $order['order_bn'], $m);
                    $oFunc->writelog(sprintf('订单创建失败：【%s】', $order['order_bn']), 'settlement', $m);
                }
            }
            
            // OMS创建订单之后，调用service处理相关业务
            foreach(kernel::servicelist('ome.service.order.create.after') as $object)
            {
                if(method_exists($object, 'after_create')){
                    $object->after_create($order);
                }
            }
            
            // 平台自发订单：创建成功后自动发货
            if ($order['order_type'] == 'platform' && !empty($order['order_id'])) {
                kernel::single('ome_order_platform')->deliveryConsign($order['order_id']);
            }
        }
        
        return [true, $errMsg];
    }

    public function getTitle($filter=null,$ioType='csv'){
        $summaryTitle = array_merge(self::IMPORT_TITLE, self::IMPORT_ITEM_TITLE);
        return array_column($summaryTitle, 'label');
    }

    public function addOrderFormat($data)
    {
        $summaryTitle = array_merge(self::IMPORT_TITLE, self::IMPORT_ITEM_TITLE);
        // $orderTitle   = array_flip(array_column($summaryTitle, 'label'));
        $orderSchema  = array_column($summaryTitle, 'col', 'label');
        $_colAll = array_column($summaryTitle, 'label', 'col');
        $orderList    = [];

        $orderTitle   = []; // 需要和$row的顺序一致
        foreach($data as $row){
            if (!$orderTitle) {
                $_num = 0;
                foreach($row as $_k => $_v){
                    $orderTitle[$_colAll[$_k]] = $_num;
                    $_num++;
                }
            }
            $orderSdf  = kernel::single('desktop_io_type_csv')->csv2sdf( array_values($row), $orderTitle, $orderSchema);
            $orderSdf['order_bn'] = $this->normalizeImportOrderBn($orderSdf['order_bn'] ?? '');

            //临时变量province city county
            $orderSdf_province = $this->import_area_char_filter($orderSdf['ship_province']);
            $orderSdf_city = $this->import_area_char_filter($orderSdf['ship_city']);
            $orderSdf_county = $this->import_area_char_filter($orderSdf['ship_district']);

            // 收货人信息
            $orderSdf['consignee']['area']['province']  = $orderSdf_province;
            $orderSdf['consignee']['area']['city']      = $orderSdf_city;
            $orderSdf['consignee']['area']['county']    = $orderSdf_county;
            
            $orderSdf['consignee']['mobile']    =   $orderSdf['ship_mobile'];
            $orderSdf['consignee']['telephone'] =   $orderSdf['ship_tel'];
            $orderSdf['consignee']['email']     =   $orderSdf['ship_email'];
            $orderSdf['consignee']['zip']       =   $orderSdf['ship_zip'];
            $orderSdf['consignee']['addr']      =   $orderSdf['ship_addr'];
            $orderSdf['consignee']['name']      =   $orderSdf['ship_name'];
            
            //补发订单
            $is_custom_bufa = false;
            if(in_array(trim($orderSdf['order_source']), self::BUFA_ORDER_SOURCES)){
                $orderSdf['order_type'] = 'bufa';
                
                // 是否项目定制的补发单类型
                if(in_array(trim($orderSdf['order_source']), self::BUFA_ORDER_CUSTOM_SOURCES)){
                    $is_custom_bufa = true;
                }
                
                // 补发原因
                if(trim($orderSdf['order_source']) == '零元订单补发单' || trim($orderSdf['order_source']) == '零元订单补发'){
                    if($orderSdf['bufa_reason']){
                        $orderSdf['bufa_reason'] = '零元订单补发：'. $orderSdf['bufa_reason'];
                    }else{
                        $orderSdf['bufa_reason'] = '零元订单补发';
                    }
                }
                
                // 获取关联订单号的信息
                $relateOrderInfo = [];
                if($orderSdf['relate_order_bn']){
                    $relateOrderInfo = app::get('ome')->model('orders')->dump(array('order_bn'=>$orderSdf['relate_order_bn']), '*');
                }
                
                // 定制补发类型的补发单
                //@todo：以导入文件中手工填写的收货人信息为准，如果没有填写，才使用关联订单号的收货人信息；
                if($is_custom_bufa){
                    if($relateOrderInfo){
                        if(empty($orderSdf_province) || empty($orderSdf_city)){
                            // 关联订单的省、市、区、镇
                            list(, $areaTemp, ) = explode(':', $relateOrderInfo['consignee']['area']);
                            $areaTemp = explode('/', $areaTemp);
                            
                            if($areaTemp[0]){
                                $orderSdf_province = $areaTemp[0];
                            }
                            
                            if($areaTemp[1]){
                                $orderSdf_city = $areaTemp[1];
                            }
                            
                            if($areaTemp[2]){
                                $orderSdf_county = $areaTemp[2];
                            }
                        }
                        
                        // 订单收货人地址为空
                        if(empty($orderSdf['consignee']['addr'])){
                            $orderSdf['consignee']['addr'] = $relateOrderInfo['consignee']['addr'];
                        }
                        
                        // 订单收货人姓名为空
                        if(empty($orderSdf['consignee']['name'])){
                            $orderSdf['consignee']['name'] = $relateOrderInfo['consignee']['name'];
                        }
                        
                        // 订单收货人手机号为空
                        if(empty($orderSdf['consignee']['mobile'])){
                            $orderSdf['consignee']['mobile'] = $relateOrderInfo['consignee']['mobile'];
                        }
                        
                        if(empty($orderSdf['ship_mobile'])){
                            $orderSdf['ship_mobile'] = $relateOrderInfo['consignee']['mobile'];
                        }
                        
                        if(empty($orderSdf['consignee']['telephone']) && empty($orderSdf['ship_mobile'])){
                            $orderSdf['consignee']['telephone'] = $relateOrderInfo['consignee']['telephone'];
                        }
                        
                        // email
                        if(empty($orderSdf['consignee']['email'])){
                            $orderSdf['consignee']['email'] = $relateOrderInfo['consignee']['email'];
                        }
                        
                        // zip
                        if(empty($orderSdf['consignee']['zip'])){
                            $orderSdf['consignee']['zip'] = $relateOrderInfo['consignee']['zip'];
                        }
                    }
                }else{
                    //获取原订单信息
                    if($relateOrderInfo){
                        //原订单的省、市、区、镇
                        list(, $areaTemp, ) = explode(':', $relateOrderInfo['consignee']['area']);
                        $areaTemp = explode('/', $areaTemp);
                        
                        //复制收货人信息
                        $orderSdf['consignee']['area']['province'] = $areaTemp[0];
                        $orderSdf['consignee']['area']['city'] = $areaTemp[1];
                        $orderSdf['consignee']['area']['county'] = $areaTemp[2];
                        $orderSdf['consignee']['area']['town'] = $areaTemp[3];
                        
                        $orderSdf['consignee']['mobile'] = $relateOrderInfo['consignee']['mobile'];
                        $orderSdf['consignee']['telephone'] = $relateOrderInfo['consignee']['telephone'];
                        $orderSdf['consignee']['email'] = $relateOrderInfo['consignee']['email'];
                        $orderSdf['consignee']['zip'] = $relateOrderInfo['consignee']['zip'];
                        $orderSdf['consignee']['addr'] = $relateOrderInfo['consignee']['addr'];
                        $orderSdf['consignee']['name'] = $relateOrderInfo['consignee']['name'];
                    }
                }
                
                // unset
                unset($orderSdf['order_source']);
            }
            
            //处理店铺信息
            $shop = app::get('ome')->model('shop')->db_dump(array('shop_bn'=>$orderSdf['shop_bn']));
            if(!$shop) {
                $errMsg[] = sprintf('来源店铺不存在:'.$orderSdf['order_bn']);
                continue;
            }
            
            $salesMLib = kernel::single('material_sales_material');
            $lib_ome_order = kernel::single('ome_order');
            $tostr = [];
            
            $salesMInfo = $salesMLib->getSalesMByBn($shop['shop_bn'],$orderSdf['e_sm_bn']);
            $order_objects = [];
            if($salesMInfo){
                if($salesMInfo['sales_material_type'] == 4){ //福袋
                    $basicMInfos = $salesMLib->get_order_luckybag_bminfo($salesMInfo['sm_id']);
                }elseif($salesMInfo['sales_material_type'] == 5){ //多选一
                    $basicMInfos = $salesMLib->get_order_pickone_bminfo($salesMInfo['sm_id'],$orderSdf['nums'],$shop['shop_id']);
                }else{
                    //获取绑定的基础物料
                    $basicMInfos = $salesMLib->getBasicMBySalesMId($salesMInfo['sm_id']);
                }
                if($basicMInfos){
                    $obj_number     = $orderSdf['e_nums'];
                    $product_price  = $orderSdf['e_price']; //商品原价
                    $obj_sale_price = bcmul($orderSdf['e_sale_price'], $obj_number, 3); //商品总销售金额
                    $total_amount   = bcmul($product_price, $obj_number, 3); //商品总金额
                    
                    //商品优惠金额
                    $pmt_price = bcsub($total_amount, $obj_sale_price, 3);
                    
                    //如果是促销类销售物料
                    if($salesMInfo['sales_material_type'] == 2){ //促销
                        $obj_type = $item_type = 'pkg';
                        //item层关联基础物料平摊销售价
                        $salesMLib->calProSaleMPriceByRate($obj_sale_price, $basicMInfos);
                        //平摊优惠金额
                        $salesMLib->calProPmtPriceByRate($pmt_price, $basicMInfos);
                        //组织订单item明细
                        $return_arr_info = $lib_ome_order->format_order_items_data($item_type,$obj_number,$basicMInfos);
                    }elseif($salesMInfo['sales_material_type'] == 4){ //福袋
                        $obj_type = $item_type = 'lkb';
                        $return_arr_info = $lib_ome_order->format_order_items_data($item_type,$obj_number,$basicMInfos);
                    }elseif($salesMInfo['sales_material_type'] == 5){ //多选一
                        $obj_type = $item_type = 'pko';
                        foreach($basicMInfos as &$var_basic_info){
                            $var_basic_info["price"] = $orderSdf['e_price'];
                            $var_basic_info["sale_price"] = $orderSdf['e_sale_price'];
                            
                            //商品优惠金额
                            $var_basic_info['pmt_price'] = $pmt_price;
                        }
                        unset($var_basic_info);
                        $return_arr_info = $lib_ome_order->format_order_items_data($item_type,$obj_number,$basicMInfos);
                    }else{ //普通、赠品
                        $obj_type  = ($salesMInfo['sales_material_type'] == 1) ? 'goods' : ($salesMInfo['sales_material_type'] == 6 ? 'giftpackage' : 'gift');
                        $item_type = ($obj_type == 'goods') ? 'product' : $obj_type;
                        if($obj_type == 'gift'){
                            $orderSdf['e_price'] = 0.00;
                            $orderSdf['e_sale_price'] = 0.00;
                        }
                        foreach($basicMInfos as &$var_basic_info){
                            $var_basic_info["price"]        = $orderSdf['e_price'];
                            $var_basic_info["sale_price"]   = $orderSdf['e_sale_price'];
                            
                            //商品优惠金额
                            $var_basic_info['pmt_price'] = $pmt_price;
                        }
                        unset($var_basic_info);
                        
                        $return_arr_info = $lib_ome_order->format_order_items_data($item_type,$obj_number,$basicMInfos);
                    }
                    
                    $order_objects = array(
                        'obj_type'      => $obj_type,
                        'obj_alias'     => $obj_type,
                        'goods_id'      => $salesMInfo['sm_id'],
                        'bn'            => $salesMInfo['sales_material_bn'],
                        'name'          => $salesMInfo['sales_material_name'],
                        'price'         => $product_price,
                        'sale_price'    => $obj_sale_price,
                        'amount'        => $total_amount,
                        'quantity'      => $obj_number,
                        'pmt_price'     => $pmt_price,
                        'order_items'   => $return_arr_info["order_items"],
                    );
                    
                    // 平台自发：明细标记商家发货
                    if ($this->order_type === 'platform') {
                        $order_objects['is_sh_ship'] = true;
                    }
                    
                    // unset
                    unset($order_items);
                    $toStrItem = [
                        'name' => $salesMInfo['sales_material_name'],
                        'num'  => $obj_number
                    ];
                    $tostr[]   = $toStrItem;
                }
            }
            
            $orderSdf["weight"] = $return_arr_info["weight"]; //商品重量
            $is_code = strtolower($orderSdf['is_cod']);
            #检测货到付款
            if( ($is_code == '是') || ($is_code == 'true')){
                $is_code = 'true';
            }else{
                $is_code = 'false';
            }
            $is_tax = strtolower($orderSdf['is_tax']);
            #检测货到付款
            if( ($is_tax == '是') || ($is_tax == 'true')){
                $is_tax = 'true';
            }else{
                $is_tax = 'false';
            }
            
            $orderSdf['shop_id']            = $shop['shop_id'];
            $orderSdf['shop_type']          = $shop['shop_type'];
            
            //防止excel导入的时间格式不正确,年份大于1年后的时间
            $createtime = time();
            
            // 格式化日期时间，支持：2015-01-01 00:00:00 和 2015/1/1 00:00:00
            if($orderSdf['createtime']){
                $createtime = kernel::single('ome_func')->parseDateTimeCell($orderSdf['createtime']);
            }
            
            if(!$createtime){
                $createtime = time();
            }
            if($createtime > (time() + 31536000)){
                $createtime = time();
            }
            
            // $paytime = ($orderSdf['paytime'] ? strtotime($orderSdf['paytime']) : '0');
            // if($paytime && $paytime > (time() + 31536000)){
            //     $paytime = time();
            // }
            
            //导入未填写下单时间,直接使用当时日期
            $orderSdf['createtime']                     = $createtime;
            $orderSdf['consignee']['area']['name']      = $orderSdf['ship_name'];
            $orderSdf['consignee']['area']              = $orderSdf_province."/".$orderSdf_city."/".$orderSdf_county;
            $orderSdf['consignee']['mobile']            = trim($orderSdf['ship_mobile']);
            $orderSdf['shipping']                       = [];
            $orderSdf['shipping']['cost_shipping']      = $orderSdf['cost_freight'] ? $orderSdf['cost_freight'] : '0';
            $orderSdf['shipping']['cost_shipping']      = (float)$orderSdf['shipping']['cost_shipping'];
            
            $orderSdf['shipping']['is_cod']             = $is_code;
            $orderSdf['is_tax']                         = $is_tax;
            $orderSdf['cost_tax']                       = $orderSdf['invoice_amount'] ? $orderSdf['invoice_amount'] : '0';
            $orderSdf['discount']                       = $orderSdf['discount'] ? $orderSdf['discount'] : '0';
            $orderSdf['score_g']                        = $orderSdf['score_g'] ? $orderSdf['score_g'] : '0';
            $orderSdf['cost_item']                      = $orderSdf['cost_item'] ? $orderSdf['cost_item'] : '0';
            $orderSdf['total_amount']                   = $orderSdf['total_amount'] ? $orderSdf['total_amount'] : '0';
            $orderSdf['pmt_order']                      = $orderSdf['pmt_order'] ? $orderSdf['pmt_order'] : '0';
            $orderSdf['pmt_goods']                      = $orderSdf['pmt_goods'] ? $orderSdf['pmt_goods'] : '0';
            
            //过滤金额中的逗号(当csv金额大于1000时会自动加入,逗号)
            $orderSdf['cost_item']                      = $this->replace_import_price($orderSdf['cost_item']);
            $orderSdf['total_amount']                   = $this->replace_import_price($orderSdf['total_amount']);
            $orderSdf['pmt_order']                      = $this->replace_import_price($orderSdf['pmt_order']);
            // $orderSdf['payed']                          = $orderSdf['payed'] ? $orderSdf['payed'] : '0';
            $orderSdf['order_source']                   = trim($orderSdf['order_source']) ? trim($orderSdf['order_source']) : 'direct';
            $orderSdf['custom_mark']                    = kernel::single('ome_func')->append_memo($orderSdf['custom_mark']);
            $orderSdf['mark_text']                      = kernel::single('ome_func')->append_memo($orderSdf['mark_text']);
            $orderSdf['createway']                      = 'import';
            $orderSdf['source']                         = 'local';
            
            // 平台自发导入：强制 order_type=platform，并标记已支付（与列表 pay_status 一致）
            if ($this->order_type === 'platform') {
                $orderSdf['order_type'] = 'platform';
                $orderSdf['payed']      = $orderSdf['total_amount'];
                $orderSdf['pay_status'] = '1';
                $orderSdf['paytime']    = $orderSdf['createtime'] ?: time();
            } else {
                $orderSdf['order_type'] = $orderSdf['order_type'] ? $orderSdf['order_type'] : 'normal';
            }
            
            // 添加补发原因
            $orderSdf['bufa_reason']                    = $orderSdf['bufa_reason'] ? $orderSdf['bufa_reason'] : '';
            
            //增加会员判断逻辑
            $memberObj          = app::get('ome')->model('members');
            $tmp_member_name    = trim($orderSdf['member_uname']);
            $memberInfo         = $memberObj->db_dump(array('uname'=>$tmp_member_name),'member_id');
            if($memberInfo){
                $orderSdf['member_id'] = $memberInfo['member_id'];
            }else{
                $members_data = array(
                    'uname'         =>  $tmp_member_name,
                    'name'          =>  $tmp_member_name,
                    'shop_type'     =>  $shop['shop_type'],
                    'area_state'    =>  $orderSdf_province,
                    'area_city'     =>  $orderSdf_city,
                    'area_district' =>  $orderSdf_county,
                    'shop_id'       =>  $shop['shop_id'],
                    'addr'          =>  $orderSdf['ship_addr'],
                    'tel'           =>  $orderSdf['ship_tel'],
                    'mobile'        =>  $orderSdf['ship_mobile'],
                    'email'         =>  $orderSdf['ship_email'],
                    'zip'           =>  $orderSdf['ship_zip'],
                );
                $orderSdf['member_id'] = kernel::single('ome_member_func')->save($members_data,$shop['shop_id']);
            }
            $orderSdf['title'] = json_encode($tostr, JSON_UNESCAPED_UNICODE);
            
            if(!$orderList[$orderSdf['order_bn']]){
                unset($orderSdf['bn'],$orderSdf['name'],$orderSdf['unit'],$orderSdf['spec_info'],$orderSdf['nums'],$orderSdf['price'],$orderSdf['retail_price'],$orderSdf['pmt_price'],$orderSdf['goods_type'],$orderSdf['brand_name']);
                $orderList[$orderSdf['order_bn']] = $orderSdf;
            }
            if($order_objects){
                $orderList[$orderSdf['order_bn']]['order_objects'][]  = $order_objects;
            }
        }
        
        return [$orderList,$errMsg];
    }
    //导入订单过滤格式化地区名称
    private function import_area_char_filter($str){
        return trim(str_replace(array("\t","\r","\n"),array("","",""),$str));
    }

    function replace_import_price($str)
    {
        return trim(str_replace(array(",", " "), array("", ""), $str));
    }
}
