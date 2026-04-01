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
        $memberMdl  = app::get('ome')->model('members');

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

                // 如果当前行的数据长于标题，截取标题长度的数据
                if (count($row) > count($titleKey)) {
                    $row = array_splice($row, 0, count($titleKey));
                }
                
                $buffer = array_combine($titleKey, $row);

                // 数据验证
                foreach ($buffer as $k => $v) {
                    if ($buffer['order_source'] == '补发订单'){
                        if ('*:' == substr($oSchema[$k], 0, 2) && $v === '' && !in_array($k, ['ship_name','ship_province','ship_city','ship_district','ship_addr','ship_mobile','ship_tel'])) {
                            return [false, sprintf('%s必填', $oSchema[$k])];
                        }
                        // 补发原因必填
                        if ($k == 'bufa_reason' && $v === '') {
                            return [false, sprintf('%s必填', $oSchema[$k])];
                        }
                    } else {
                        if ('*:' == substr($oSchema[$k], 0, 2) && $v === '') {
                            return [false, sprintf('%s必填', $oSchema[$k])];
                        }
                    }
        
                    if ('时间' == substr($oSchema[$k], -1, 2) && $v && !strtotime($v)) {
                        return [false, sprintf('%s需转文本格式', $oSchema[$k])];
                    }
                }

                // 判断店铺是否存在
                $shop = $shopList[$buffer['shop_bn']];
                if (!$shop) {
                    return [false, sprintf('[%s]来源店铺编号不存在', $buffer['shop_bn']), $buffer];
                }

                if ($orderMdl->dump(['order_bn' => $buffer['order_bn']], 'order_id')) {
                    return [false, sprintf('[%s]订单号已经存在', $buffer['order_bn']), $buffer];
                }

                if ($arOMdl->dump(['order_bn' => $buffer['order_bn']], 'order_id')) {
                    return [false, sprintf('[%s]订单号已经存在归档', $buffer['order_bn']), $buffer];
                }

                if ($buffer['member_uname']) {
                    $member = $memberMdl->dump([
                        'uname' => $buffer['member_uname'],
                    ], 'member_id');

                    if ($member) {
                        $member_id = $member['member_id'];
                    } else {
                        // 会员
                        $member_id = kernel::single('ome_member_func')->save([
                            'name'  => $buffer['member_uname'],
                            'uname' => $buffer['member_uname'],
                        ], $shop['shop_id']);
                        if (!$member_id) {
                            return [false, '保存会员失败：' . kernel::database()->errorinfo(), $buffer];
                        }
                    }
                }

                $orderPrice[$buffer['order_bn']]['cost_freight']    =   $buffer['cost_freight'];
                $orderPrice[$buffer['order_bn']]['discount']        =   $buffer['discount'];
                $orderPrice[$buffer['order_bn']]['pmt_order']       =   $buffer['pmt_order'];
                $orderPrice[$buffer['order_bn']]['cost_item']       =   $buffer['cost_item'];
                $orderPrice[$buffer['order_bn']]['total_amount']    =   $buffer['total_amount'];
                $orderPrice[$buffer['order_bn']]['items'][] = [
                    'e_price'       =>  $buffer['e_price'],
                    'e_sale_price'  =>  $buffer['e_sale_price'],
                    'e_nums'        =>  $buffer['e_nums'],
                ];

                $previousRow = array_combine($_importTitle, $row); // 在最后，保存前一条数据,给下个循环使用,因为同一张订单主表信息只有第一条有
            }
        }

        // 检查金额
        foreach ($orderPrice as $_order_bn => $_order) {
            $item_amount = $item_pmt_price = 0;
            foreach ($_order['items'] as $_iv) {
                //行原价小计
                $item_amount_tmp = $_iv['e_price'] * $_iv['e_nums'];
                $item_amount += $item_amount_tmp;
                //行商品优惠
                $item_pmt_price_tmp = $item_amount_tmp - ((float)$_iv['e_sale_price'] * (float)$_iv['e_nums']);
                $item_pmt_price += $item_pmt_price_tmp;
            }

            // 检查金额
            $cost_item = $item_amount;
            $cost_freight = $_order['cost_freight'];
            $cost_tax = 0;
            $discount = $_order['discount'];
            $pmt_order = $_order['pmt_order'];
            $pmt_goods = $item_pmt_price;
            $total_amount = (float)$cost_item + (float)$cost_freight + (float)$cost_tax - (float)$discount - (float)$pmt_order - (float)$pmt_goods;
            if (bccomp((float)$_order['cost_item'], round($cost_item,2), 2) !== 0) {
                return [false, sprintf('[%s]订单商品总额[%s]明细与订单行商品行[%s]不一致', $_order_bn, $_order['cost_item'],  $cost_item), $buffer];
            }
            if (bccomp((float)$_order['total_amount'], round($total_amount,2), 2) !== 0) {
                return [false, sprintf('[%s]订单总额[%s](商品总额[%s]+配送费用[%s]+税金[%s]-折扣[%s]-订单优惠[%s]-商品优惠[%s])不对',$_order_bn,$_order['total_amount'],$cost_item,$cost_freight,$cost_tax,$discount,$pmt_order,$pmt_goods), $_order];
            }
        }

        //导入文件内容验证
        return array(true, '文件模板匹配', $rows[0]);
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

            // 如果当前行的数据长于标题，截取标题长度的数据
            if (count($row) > count($titleKey)) {
                $row = array_splice($row, 0, count($titleKey));
            }

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
            
            //[SAP创建]调用service进行SAP创建
            foreach(kernel::servicelist('ome.service.order.create.after') as $object)
            {
                if(method_exists($object, 'after_create')){
                    $object->after_create($order);
                }
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

            //临时变量province city county
            $orderSdf_province = $this->import_area_char_filter($orderSdf['ship_province']);
            $orderSdf_city = $this->import_area_char_filter($orderSdf['ship_city']);
            $orderSdf_county = $this->import_area_char_filter($orderSdf['ship_district']);

            //补发订单
            if($orderSdf['order_source'] == '补发订单'){
                $orderSdf['order_type'] = 'bufa';
                
                //获取原订单信息
                $relateOrderInfo = array();
                if($orderSdf['relate_order_bn']){
                    $relateOrderInfo = app::get('ome')->model('orders')->dump(array('order_bn'=>$orderSdf['relate_order_bn']), '*');
                    
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
                
                unset($orderSdf['order_source']);
            } else {
                $orderSdf['consignee']['area']['province']  = $orderSdf_province;
                $orderSdf['consignee']['area']['city']      = $orderSdf_city;
                $orderSdf['consignee']['area']['county']    = $orderSdf_county;

                $orderSdf['consignee']['mobile']    =   $orderSdf['ship_mobile'];
                $orderSdf['consignee']['telephone'] =   $orderSdf['ship_tel'];
                $orderSdf['consignee']['email']     =   $orderSdf['ship_email'];
                $orderSdf['consignee']['zip']       =   $orderSdf['ship_zip'];
                $orderSdf['consignee']['addr']      =   $orderSdf['ship_addr'];
                $orderSdf['consignee']['name']      =   $orderSdf['ship_name'];
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
            $createtime = ($orderSdf['createtime'] ? strtotime($orderSdf['createtime']) : time());
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
            $orderSdf['order_type']                     = $orderSdf['order_type'] ? $orderSdf['order_type'] : 'normal';
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
