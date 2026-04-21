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

class ome_order_import  implements omecsv_data_split_interface
{

    function run(&$cursor_id,$params,&$errmsg){
        //danny_freeze_stock_log
        define('FRST_OPER_NAME','system');
        define('FRST_TRIGGER_OBJECT_TYPE','订单导入冻结库存');
        define('FRST_TRIGGER_ACTION_TYPE','ome_order_import：run');
        
        $mdl = app::get('ome')->model('orders');
        $logObj = app::get('ome')->model('operation_log');
        
        $orderLib = kernel::single('ome_order');
        $luckyBagLib = kernel::single('ome_order_luckybag');
        
        foreach($params['sdfdata'] as $v)
        {
            $m = '';
            if(!$mdl->create_order($v, $m)){;
                kernel::log("errmsg = ".$m);
                if(!empty($m)){        
                    $errmsg.=$m.";";
                }
            }
            #订单保存后，如果是货到付款类型订单，增加应收金额
            if($v['shipping']['is_cod'] == 'true'){
                $oObj_orextend = app::get($params['app'])->model("order_extend");
                $code_data = array('order_id'=>$v['order_id'],'receivable'=>$v['total_amount'],'sellermemberid'=>$v['member_id']);
                $oObj_orextend->save($code_data);
            }
            
            //补发订单复制原订单收件人敏感数据
            if(in_array($v['order_type'], array('bufa')) && $v['order_id']){
                $error_msg = '';
                $bufaResult = $orderLib->createBufaOrderEncrypt($v, $error_msg);
                if(!$bufaResult && $error_msg){
                    //logs
                    $log_msg = '复制原平台订单收件人敏感数据失败：'. $error_msg;
                    $logObj->write_log('order_modify@ome', $v['order_id'], $log_msg);
                }
            }
            
            //福袋日志记录
            if($v['lucky_falg']){
                $luckyBagLib->saveLuckyBagUseLogs($v);
            }
            
            //[SAP创建]调用service进行SAP创建
            foreach(kernel::servicelist('ome.service.order.create.after') as $object)
            {
                if(method_exists($object, 'after_create')){
                    $object->after_create($v);
                }
            }
        }
        return false;
    }
    
    public $column_num = 45;
    public $current_order_bn = null;
    public $order_type = '';
    
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
        $this->order_type = isset($params['type']) ? $params['type'] : $params['queue_data']['type'];
        $sdf = [];
        $offset      = intval($data['offset']) + 1;//文件行数 行数默认从1开始
        $splitCount  = 0;//执行行数
        if($data){
            foreach($data as $row){
                $res = $this->getSdf($row, $offset, $params['title']);
                
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
        unset($cpfrItems);
        //创建订单
        if ($sdf) {
            list($result,$msgList) = $this->implodePlatformOrders($sdf);
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
     * 检查文件是否有效
     * @param $file_name 文件名
     * @param $file_type 文件类型
     * @param $queue_data 请求参数
     * @return array
     * @date 2024-06-06 3:52 下午
     */
    public function checkFile($file_name, $file_type,$queue_data)
    {
        $ioType = kernel::single('omecsv_io_split_' . $file_type);
        $row    = $ioType->getData($file_name, 0, 5);

        $title = $this->getTitle('order');
        
        sort($title);
        
        $aliTitle = $row[0];
        sort($aliTitle);
        
        if ($title == $aliTitle) {
            return array(true, '文件模板匹配', $row[0]);
        }
        
        //导入文件内容验证
        return array(true, '文件模板匹配', $row[0]);
    }
    
    /**
     * 导入文件表头定义
     * @date 2024-06-06 3:52 下午
     */
    
    public function getSdf($row, $offset = 1, $title)
    {
        $this->getTitle();
        $row = array_map('trim', $row);
        
        $oSchema = array_flip($this->oSchema['csv']['order']);
        
        $titleKey = array();
        foreach ($title as $k => $t) {
            $titleKey[$k] = array_search($t, $oSchema);
            if ($titleKey[$k] === false) {
                return array('status' => false, 'msg' => '未定义字段`' . $t . '`');
            }
        }
        
        $res = array('status' => true, 'data' => array(), 'msg' => '');
        
        $row_num = count($row);
        if ($this->column_num <= $row_num and $row[0] != '*:订单号') {
            
            $tmp = array_combine($titleKey, $row);
            //判断参数不能为空
            foreach ($tmp as $k => $v) {
                if (in_array($k, array('order_bn','bn','nums','shop_id'))) {
                    if (!$v) {
                        $res['status'] = false;
                        $res['msg']    = sprintf("LINE %d : %s 不能为空！", $offset, $oSchema[$k]);
                        return $res;
                    }
                }
            }
            $res['data'] = $tmp;
        }
        
        return $res;
    }
    
    /**
     * _formatData
     * @param mixed $data 数据
     * @return mixed 返回值
     */
    public function _formatData(&$data)
    {
        foreach ($data as $k => $str) {
            $data[$k] = str_replace(array("\r\n", "\r", "\n", "\t"), "", $str);
        }
    }
    
    /**
     * 添加OrderFormat
     * @param mixed $data 数据
     * @return mixed 返回值
     */
    public function addOrderFormat($data)
    {
        $orderTitle = array_flip( $this->getTitle('order' ) );
        $orderSchema = $this->oSchema['csv']['order'];
        $orderList = [];
        foreach($data as $row){
            $orderSdf  = kernel::single('desktop_io_type_csv')->csv2sdf( array_values($row) ,$orderTitle,$orderSchema  );
            //处理店铺信息
            $shop = app::get('ome')->model('shop')->db_dump(array('shop_bn'=>$orderSdf['shop_id']));
            if(!$shop) {
                $errMsg[] = sprintf('来源店铺不存在:'.$orderSdf['order_bn']);
                continue;
            }
            
            $salesMLib = kernel::single('material_sales_material');
            $lib_ome_order = kernel::single('ome_order');
            $tostr = [];
            
            $salesMInfo = $salesMLib->getSalesMByBn($shop['shop_id'],$orderSdf['bn']);
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
                    $obj_number = $orderSdf['nums'];
                    $product_price = $orderSdf['price']; //商品原价
                    $obj_sale_price = bcmul($orderSdf['retail_price'], $obj_number, 3); //商品总销售金额
                    $total_amount = bcmul($product_price, $obj_number, 3); //商品总金额
                    
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
                            $var_basic_info["price"] = $orderSdf['price'];
                            $var_basic_info["sale_price"] = $orderSdf['retail_price'];
                            
                            //商品优惠金额
                            $var_basic_info['pmt_price'] = $pmt_price;
                        }
                        unset($var_basic_info);
                        $return_arr_info = $lib_ome_order->format_order_items_data($item_type,$obj_number,$basicMInfos);
                    }else{ //普通、赠品
                        $obj_type = ($salesMInfo['sales_material_type'] == 1) ? 'goods' : ($salesMInfo['sales_material_type'] == 6 ? 'giftpackage' : 'gift');
                        $item_type = ($obj_type == 'goods') ? 'product' : $obj_type;
                        if($obj_type == 'gift'){
                            $orderSdf['price'] = 0.00;
                            $orderSdf['sale_price'] = 0.00;
                        }
                        foreach($basicMInfos as &$var_basic_info){
                            $var_basic_info["price"] = $orderSdf['price'];
                            $var_basic_info["sale_price"] = $orderSdf['sale_price'];
                            
                            //商品优惠金额
                            $var_basic_info['pmt_price'] = $pmt_price;
                        }
                        unset($var_basic_info);
                        
                        $return_arr_info = $lib_ome_order->format_order_items_data($item_type,$obj_number,$basicMInfos);
                    }
                    
                    $order_objects = array(
                        'obj_type' => $obj_type,
                        'obj_alias' => $obj_type,
                        'goods_id' => $salesMInfo['sm_id'],
                        'bn' => $salesMInfo['sales_material_bn'],
                        'name' => $orderSdf['name'],
                        'price' => $product_price,
                        'sale_price' => $obj_sale_price,
                        'amount' => $total_amount,
                        'quantity' => $obj_number,
                        'pmt_price' => $pmt_price,
                        'is_sh_ship' => true,
                        'order_items' => $return_arr_info["order_items"],
                    );
                    unset($order_items);
                    $toStrItem = [
                        'name' => $orderSdf['name'],
                        'num'  => $obj_number
                    ];
                    $tostr[]   = $toStrItem;
                }
            }
            
            $orderSdf["weight"] = $return_arr_info["weight"]; //商品重量
            $is_code = strtolower($orderSdf['shipping']['is_cod']);
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
            $createway = strtolower($orderSdf['createway']);
            #检测货到付款
            if( ($createway == '是') || ($createway == 'true')){
                $createway = 'matrix';
            }else{
                $createway = 'import';
            }
            
            $orderSdf['shop_id']            = $shop['shop_id'];
            $orderSdf['shop_type']          = $shop['shop_type'];
            //临时变量province city county
            $orderSdf_province = $this->import_area_char_filter($orderSdf['consignee']['area']['province']);
            $orderSdf_city = $this->import_area_char_filter($orderSdf['consignee']['area']['city']);
            $orderSdf_county = $this->import_area_char_filter($orderSdf['consignee']['area']['county']);
            
            //防止excel导入的时间格式不正确,年份大于1年后的时间
            $createtime = ($orderSdf['createtime'] ? strtotime($orderSdf['createtime']) : time());
            if($createtime > (time() + 31536000)){
                $createtime = time();
            }
            
            $paytime = ($orderSdf['paytime'] ? strtotime($orderSdf['paytime']) : time());
            if($paytime > (time() + 31536000)){
                $paytime = time();
            }
            
            //导入未填写下单时间,直接使用当时日期
            $orderSdf['createtime']         = $createtime;
            $orderSdf['paytime']            = $paytime;
            $orderSdf['consignee']['area']  = $orderSdf_province."/".$orderSdf_city."/".$orderSdf_county;
            $orderSdf['consignee']['mobile']  = trim($orderSdf['consignee']['mobile']);
            $orderSdf['shipping']['is_cod'] = $is_code;
            $orderSdf['shipping']['cost_shipping'] = $orderSdf['shipping']['cost_shipping'] ? $orderSdf['shipping']['cost_shipping'] : '0';
            $orderSdf['shipping']['cost_shipping'] = (float)$orderSdf['shipping']['cost_shipping'];
            
            $orderSdf['is_tax']             = $is_tax;
            $orderSdf['cost_tax']           = $orderSdf['cost_tax'] ? $orderSdf['cost_tax'] : '0';
            $orderSdf['discount']           = $orderSdf['discount'] ? $orderSdf['discount'] : '0';
            $orderSdf['score_g']            = $orderSdf['score_g'] ? $orderSdf['score_g'] : '0';
            $orderSdf['cost_item']          = $orderSdf['cost_item'] ? $orderSdf['cost_item'] : '0';
            $orderSdf['total_amount']       = $orderSdf['total_amount'] ? $orderSdf['total_amount'] : '0';
            $orderSdf['pmt_order']          = $orderSdf['pmt_order'] ? $orderSdf['pmt_order'] : '0';
            $orderSdf['pmt_goods']          = $orderSdf['pmt_goods'] ? $orderSdf['pmt_goods'] : '0';
            
            //过滤金额中的逗号(当csv金额大于1000时会自动加入,逗号)
            $orderSdf['cost_item'] = $this->replace_import_price($orderSdf['cost_item']);
            $orderSdf['total_amount'] = $this->replace_import_price($orderSdf['total_amount']);
            $orderSdf['pmt_order'] = $this->replace_import_price($orderSdf['pmt_order']);
            $orderSdf['payed']              = $orderSdf['total_amount'];
            //source
            $tmp_order_source               = ome_order_func::get_order_source();
            $tmp_order_source               = array_flip($tmp_order_source);
            $orderSdf['order_source']       = $tmp_order_source[$orderSdf['order_source']]?$tmp_order_source[$orderSdf['order_source']]:'direct';
            $orderSdf['custom_mark']        = kernel::single('ome_func')->append_memo($orderSdf['custom_mark']);
            $orderSdf['mark_text']          = kernel::single('ome_func')->append_memo($orderSdf['mark_text']);
            $orderSdf['createway']          = $createway;
            $orderSdf['source']             = 'local';
            $orderSdf['order_type']         = $this->order_type;
            //增加会员判断逻辑
            
            $memberObj = app::get('ome')->model('members');
            $tmp_member_name = trim($orderSdf['account']['uname']);
            $memberInfo = $memberObj->db_dump(array('uname'=>$tmp_member_name),'member_id');
            if($memberInfo){
                $orderSdf['member_id'] = $memberInfo['member_id'];
            }else{
                $members_data = array(
                    'uname'     =>  $tmp_member_name,
                    'name'      =>  $tmp_member_name,
                    'shop_type' =>  $shop['shop_type'],
                    'area_state'=>  $orderSdf_province,
                    'area_city' =>  $orderSdf_city,
                    'area_district'=> $orderSdf_county,
                    'shop_id'   =>  $shop['shop_id'],
                    'addr'      =>  $orderSdf['consignee']['addr'],
                    'tel'       =>  $orderSdf['consignee']['telephone'],
                    'mobile'    =>  $orderSdf['consignee']['mobile'],
                    'email'     =>  $orderSdf['consignee']['email'],
                    'zip'       =>  $orderSdf['consignee']['zip'],
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
    
    function implodePlatformOrders($contents)
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

            //平台自发订单处理
            if ($order['order_type'] == 'platform') {
                kernel::single('ome_order_platform')->deliveryConsign($order['order_id']);
            }
        }
        
        return [true, $errMsg];
    }
    
    function getTitle( $filter=null,$ioType='csv' ){
        switch( $ioType ){
            case 'csv':
            default:
                $this->oSchema['csv']['order'] = array(
                    '*:订单号' => 'order_bn',
                    '*:支付方式' => 'payinfo/pay_name',
                    '*:下单时间' => 'createtime',
                    '*:付款时间' => 'paytime',
                    
                    '*:商品货号' => 'bn',
                    '*:商品名称' => 'name',
                    '*:购买单位' => 'unit',
                    '*:商品规格' => 'spec_info',
                    '*:购买数量' => 'nums',
                    '*:商品原价' => 'price',
                    '*:销售价'   =>'retail_price',
                    '*:商品行优惠金额' => 'pmt_price',
                    '*:商品类型' => 'goods_type',
                    '*:商品品牌' => 'brand_name',
                    
                    '*:配送方式' => 'shipping/shipping_name',
                    '*:配送费用' => 'shipping/cost_shipping',
                    '*:来源店铺编号' => 'shop_id',
                    '*:订单附言' => 'custom_mark',
                    '*:收货人姓名' => 'consignee/name',
                    '*:收货地址省份' => 'consignee/area/province',
                    '*:收货地址城市' => 'consignee/area/city',
                    '*:收货地址区/县' => 'consignee/area/county',
                    '*:收货详细地址' => 'consignee/addr',
                    '*:收货人固定电话' => 'consignee/telephone',
                    '*:电子邮箱' => 'consignee/email',
                    '*:收货人移动电话' => 'consignee/mobile',
                    '*:邮编' => 'consignee/zip',
                    '*:货到付款' => 'shipping/is_cod',
                    '*:是否开发票' => 'is_tax',
                    '*:发票抬头' => 'tax_title',
                    '*:发票金额' => 'cost_tax',
                    '*:优惠方案' => 'order_pmt',
                    '*:订单优惠金额' => 'pmt_order',
                    '*:商品优惠金额' => 'pmt_goods',
                    '*:折扣' => 'discount',
                    '*:返点积分' => 'score_g',
                    '*:商品总额' => 'cost_item',
                    '*:订单总额' => 'total_amount',
                    '*:买家会员名' => 'account/uname',
                    '*:订单类型' => 'order_source',
                    '*:订单备注' => 'mark_text',
                    '*:商品重量' =>'weight',
                    '*:发票号'=>'tax_no',
                    '*:周期购'=>'createway',
                    '*:关联订单号'=>'relate_order_bn',
                );
                break;
        }
     
        $this->ioTitle[$ioType]['order'] = array_keys( $this->oSchema[$ioType]['order'] );
        return $this->ioTitle[$ioType][$filter];
    }
    
    
    //导入订单过滤格式化地区名称
    private function import_area_char_filter($str){
        return trim(str_replace(array("\t","\r","\n"),array("","",""),$str));
    }
    
    function replace_import_price($str)
    {
        return trim(str_replace(array(",", " "), array("", ""), $str));
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
        if ($row['0'] !== $this->current_order_bn) {
            if ($this->current_order_bn !== null) {
                $is_split = true;
            }
            $this->current_order_bn = $row['0'];//订单号
        }
        return $is_split;
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
            'page_size' => 500,
            'max_direct_count' => 500,
        );
        return $key ? $config[$key] : $config;
    }
}