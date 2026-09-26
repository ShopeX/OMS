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
 * 发票处理类
 */
class invoice_process
{
    //当前方法废弃使用新方法
    /**@used-by invoice_process::newCreate **/
    /**
     * 新增发票记录处理函数
     * 
     * @param array $params 传入参数
     */
    public function create($params, $type='order_create')
    {
        // 注册service，分析是否允许开蓝票
        foreach(kernel::servicelist('ome.service.invoice.analysis.prepose') as $object)
        {
            if(method_exists($object, 'check_blue_invoice')){
                // check
                $analysisResult = $object->check_blue_invoice($params);
                if($analysisResult['rsp'] == 'fail'){
                    $error_msg = ($analysisResult['error_msg'] ? $analysisResult['error_msg'] : '不允许开蓝发票!');
                    return [false, ['msg'=>$error_msg]];
                }
            }
        }
        
        //order_id是必须的
        $order_id = $params['order_id'];
        if(!$order_id){
            return false;
        }
        $check_create = kernel::single('invoice_check')->checkCreate($order_id);
        #详情那里的可以点编辑
        if(!$check_create && ($type !=='order_detail_basic')){
            return false;
        }
        //获取order表信息
        $mdlOmeOrders = app::get('ome')->model('orders');
        $rs_orders = $mdlOmeOrders->dump(array("order_id"=>$order_id));
        if (empty($rs_orders)){
            $mdlOmeOrders = app::get('archive')->model('orders');
            $rs_orders = $mdlOmeOrders->dump(array("order_id"=>$order_id));
            if (empty($rs_orders)) {
                return false;
            }
        }
        //获取前端店铺发票配置信息
        $shop_id = $rs_orders["shop_id"];
        $mode = 1;

        $rs = kernel::single('invoice_func')->get_order_setting($shop_id,$mode);
        $rs_setting = $rs[0];
        #电子发票,没有开票信息设置的，不创建
        if($mode == 1 && empty($rs_setting)){
           return true;
        }

        $mdlInOrder = app::get('invoice')->model('order');
        if($type == 'order_create'){
            //前端店铺下单 和 手动新建订单
            if($rs_orders['is_tax'] == 'false' && $params['source_from'] != 'xcxd'){
                //手动新建订单 不选择开票
                return false;
            }
            $invoice_mode   = intval($params['invoice_kind']); //默认0纸质发票 电子发票为1
            $order_bn       = $params['order_bn'];
            $tax_rate       = $rs_setting["tax_rate"] ? $rs_setting["tax_rate"] : 0;//税率
            $tax_title      = strip_tags(trim($params['tax_title'])); //发票抬头
            $content        = '商品明细'; //发票内容 默认为 商品明细
            $consignee_name = $params['consignee']['name']; //收货人名
            $consignee_area = $params['consignee']['area']; //收货人地区
            $consignee_addr = $params['consignee']['addr']; //收货人地址

            $telphone    = $params['consignee']['mobile'];
            $register_no = $params['register_no'];

            // 获取编辑表单其他的填写数据
            $other_insert = array(
                "ship_bank"         => $params["invoice_bank_name"],
                "ship_bank_no"      => $params["invoice_bank_account"],
                "ship_company_addr" => $params["invoice_address"],      // 注册地址
                "ship_company_tel"  => $params['invoice_phone'],        // 注册电话
                "ship_name"         => $params['invoice_receiver_name'],
                "tax_company"       => $params['invoice_receiver_name'],
            );

            if ($params['value_added_tax_invoice']) {
                $other_insert['type_id'] = '1';
            }else{
                $invoice_mode = '1';
            }

            $msg_part       = '新订单自动插入发票信息';
        }else if($type == 'order_detail_basic'){
            $tax_title = $params['tax_title']; //发票抬头

            //订单明细里“开票”按钮 手动生成 没有发票的订单发票信息
            if($rs_orders["is_tax"] == 'false'){
               $invoice_mode = intval($params['invoice_mode']); //默认0纸质发票 电子发票为1
               $order_bn = $rs_orders['order_bn']; //订单号
               $tax_rate = $rs_setting["tax_rate"] ? $rs_setting["tax_rate"] : 0;//税率
              
               $content = '商品明细'; //发票内容 默认为 商品明细 
               $consignee_name = $rs_orders['consignee']['name']; //收货人名
               $consignee_area = $rs_orders['consignee']['area']; //收货人地区
               $consignee_addr = $rs_orders['consignee']['addr']; //收货人地址
               $telphone       = $rs_orders['consignee']['mobile']; //用来发短信
               $msg_part = '手动插入发票信息';
                if ($invoice_mode == '0') {
                    $other_insert['type_id'] = '1';
                }
                if ($params['is_make_invoice']) {
                    $other_insert["is_make_invoice"] = $params['is_make_invoice'];
                }
            }else{
                #获取没有作废的那条开票记录,更新发票抬头
                $InOrder_info = $mdlInOrder->getList('id,is_status',array('order_id'=>$order_id,'is_status'=>'0'));
                if(empty($InOrder_info)){
                    return true;
                }
                $_data['title'] = $tax_title;
                $_data['id']    = $InOrder_info[0]['id'];
                $result = $mdlInOrder->save($_data);
                return true;
            }    
        }elseif($type == 'batch_invoice_order'){
           if($rs_orders['is_tax'] == 'false'){
              //手动新建订单 不选择开票
              return false;
           }
           $invoice_mode   = $params['invoice_kind']?intval($params['invoice_kind']):0; //默认0纸质发票 电子发票为1
           $order_bn       = $rs_orders['order_bn']; //订单号
           $tax_rate       = $rs_setting["tax_rate"] ? $rs_setting["tax_rate"] : 0;//税率
           $tax_title      = $rs_orders['tax_title']; //发票抬头
           $content        = '商品明细'; //发票内容 默认为 商品明细
           $consignee_name = $rs_orders['consignee']['name']; //收货人名
           $consignee_area = $rs_orders['consignee']['area']; //收货人地区
           $consignee_addr = $rs_orders['consignee']['addr']; //收货人地址
           $telphone       = $rs_orders['consignee']['mobile']; //用来发短信
           $msg_part = '批量创建订单自动插入发票信息';
        }else if($type == 'invoice_list_add_same'){
            //获取编辑表单其他的填写数据
            $other_insert = array(
                "remarks"           => $params["remarks"],
                "ship_bank"         => $params["ship_bank"],
                "ship_bank_no"      => $params["ship_bank_no"],
                "ship_tax"          => $params["ship_tax"],
                "ship_company_addr" => $params["ship_company_addr"],
                "ship_company_tel"  => $params["ship_company_tel"],
            );
            //发票列表 新建发票信息
            $invoice_mode = intval($params['mode']); //默认0纸质发票 电子发票为1
            //获取原发票记录
            $old_invoice_order = $mdlInOrder->dump(array("id"=>$params["id"]));
            $order_bn = $old_invoice_order["order_bn"]; //订单号
            $memoList = array();
            $oldmemo = @unserialize($old_invoice_order['memo']);
            if (end($oldmemo)['op_content'] != $params['memo']) {
                $op_name = kernel::single('desktop_user')->get_name();
                if ($oldmemo) {
                    foreach ($oldmemo as $k => $v) {
                        $memoList[] = $v;
                    }
                }
                $newmemo = htmlspecialchars($params['memo']);
                $newmemo = array('op_name'    => $op_name, 'op_time'    => date('Y-m-d H:i:s', time()), 'op_content' => $newmemo);
                $memoList[]  = $newmemo;
        
            }
            $params['memo'] = @serialize($memoList);
            //发票金额
            $params['invoice_amount'] = number_format($params["amount"],2,".",""); //纸质发票直接取填的开票金额为开票金额
            $other_insert["type_id"] = $params["type_id"]?:1;

            $tax_rate = 0;
            if(is_numeric($params["tax_rate"]) && intval($params["tax_rate"]) > 0 ){
                $tax_rate = $params["tax_rate"];
            }
            $tax_title = $params["title"];
            $content =  $old_invoice_order['content'];
            $params['invoice_receiver_name'] = $params["tax_company"];
            $consignee_area = $params["ship_area"];
            $consignee_addr = $params["ship_addr"];
            $telphone       = $params['ship_tel']; 

            //开票方信息取表单填写 重写rs_setting数组拿input框中的值
            $rs_setting = array(
                "payee_name"     => $params['payee_name'],
                "tax_no"         => $params['tax_no'],#开票方税号
                "address"        => $params['address'],
                "telphone"       => $params['telephone'],
                "payee_operator" => $params['payee_operator'],
                "bank"           => $params['bank'],
                "bank_no"        => $params['bank_no'],
                'payee_receiver' => $params['payee_receiver'],
                'payee_checker'  => $params['payee_checker']
            );
            //新建相似是否可以开票根据原票状态
            if ($old_invoice_order['is_make_invoice'] == '1') {
                $other_insert["is_make_invoice"] = '1';
            }
            $msg_part = "新建类似发票信息";
            if ($params['action_type'] && $params['action_type'] == 'doCheckChangeTicket') {
                $msg_part = "使用改票信息新建发票";
                //更新改票状态
                $mdlInOrder->update(['change_status'=>'2'],array("id"=>$params["id"]));
                //新发票设置为可操作状态
                $other_insert["is_make_invoice"] = '1';
                $rs = kernel::single('invoice_func')->get_order_setting($shop_id,$invoice_mode);
                $rs_setting = $rs[0];
            }
        }
        if ($params['source_status']) {
            $other_insert['source_status'] = $params['source_status'];
        }
        $total_amount = number_format($rs_orders['total_amount'],2,".",""); //（含税）订单总金额
        #京东的订单，优惠方案，是当支付金额来使用，相当于少付钱，因此开票金额需要从总额中减去优惠
        // if(!empty($params['invoice_pmt_amount']) && in_array($params['shop_type'] ,array('360buy'))){
        //     $total_amount = $total_amount -  $params['invoice_pmt_amount'];
        // }

        // 平台同步过来的开票金额
        if ($params['invoice_amount']) $total_amount = $params['invoice_amount'];


        #到达ERP的订单金额，实际都是已经含税了的，所以这里的税金，需要自行推算出来
        $cost_tax = kernel::single('invoice_func')->get_invoice_cost_tax($total_amount,$tax_rate);
        $tax_title = $tax_title ? $tax_title : '个人';//如抬头为空则用收货人名
        $operator = kernel::single('desktop_user')->get_id();//操作人ID
        $insert_arr = array(
                'is_print'       => '1',# 这个值对于openapi的用户有用
                'order_id'       => $order_id,
                'order_bn'       => $order_bn,
                'mode'           => 1,//这个key不存在就是0，1为电子发票
                'amount'         => $total_amount,#(含税)开票金额(在显示和调用的地方，允许客户按和甲方约定的开票金额，进行修改)
                'cost_tax'       => $cost_tax,
                'tax_rate'       => $tax_rate,
                'title'          => $tax_title,
                'content'        => $content,#新建发票时，发票内容默认是商品明细
                'operator'       => $operator,
                'create_time'    => time(),
                'tax_company'    => $params['invoice_receiver_name'],
                'ship_name'      => $params['invoice_receiver_name'],
                'ship_area'      => $consignee_area,
                'ship_addr'      => $consignee_addr,
                'ship_tel'       => $telphone,
                'shop_id'        => $shop_id,
                'shop_type'      => $rs_orders["shop_type"],
                'payee_name'     => $rs_setting["payee_name"] ? $rs_setting["payee_name"] : "",
                'tax_no'         => $rs_setting["tax_no"] ? $rs_setting["tax_no"] : "",
                'address'        => $rs_setting["address"] ? $rs_setting["address"] : "",
                'telephone'      => $rs_setting["telphone"] ? $rs_setting["telphone"] : "",
                'payee_operator' => $rs_setting["payee_operator"] ? $rs_setting["payee_operator"] : "",
                'bank'           => $rs_setting["bank"] ? $rs_setting["bank"] : "",
                'bank_no'        => $rs_setting["bank_no"] ? $rs_setting["bank_no"] : "",
                'payee_checker' => $rs_setting['payee_checker'] ? $rs_setting['payee_checker'] : '',
                'payee_receiver' => $rs_setting['payee_receiver'] ? $rs_setting['payee_receiver'] : '',
                'ship_email'     => $params['receiver_email'] ? $params['receiver_email'] : '',
                'invoice_apply_bn' => $this->getInvoiceApplyBn(),
                'is_auto' => ($params['is_auto'] == '2' ? '2' : '1'), // 是否自动开票(1:自动开票, 2:手工开票)
        );
        //对pos判断btq trade 不在列表里显示
        if($insert_arr['shop_type'] == 'pekon'){
            $stores = app::get('o2o')->model('store')->db_dump(array('shop_id'=>$shop_id),'store_sort');
            if(in_array($stores['store_sort'],array('BTQ','Trade'))){
                $insert_arr['disabled'] = 'true';
            }
            if($params['is_status']){
                $insert_arr['is_status'] = $params['is_status'];
            }
        }
       
        
        if($other_insert){
            $insert_arr = array_merge($insert_arr,$other_insert);
        }
        if($register_no) {
            $insert_arr['ship_tax'] = $register_no;
        }
        
        //发票备注
        if($params['memo']){
            $insert_arr['memo'] = $params['memo'];
        }
        //新增发票
        $result = $mdlInOrder->insert($insert_arr);
        
        //新增发票日志
        $opObj = app::get('ome')->model('operation_log');
        if($result){
            $msg = $msg_part."成功。";
            $opObj->write_log('invoice_create@invoice', $result, $msg);
            //专票新建类似发票成功更新明细
            if($type == 'invoice_list_add_same') {
                if ($insert_arr['mode'] == '0' && $insert_arr['type_id'] == '1') {
                    $insert_arr['id'] = $result;
                    kernel::single('invoice_sales_data')->generate($insert_arr);
                }
            }
            //生成的发票直接请求开票接口
            $autoinvoice = app::get('ome')->getConf('ome.order.autoinvoice');
            if ($params['action_type'] && in_array($params['action_type'],['doCheckChangeTicket','order_aftersale']) && $insert_arr['mode'] == '1' &&  $autoinvoice == 'on') {
                $this->billing(['id'=>$result,'order_id'=>$order_id]);
            }
            return true;
        }else{
            $msg = $msg_part."失败。";
            $opObj->write_log('invoice_create@invoice', $result, $msg);
            return false;
        }
    }
    
    /**
     * 创建开票数据
     * @Author: xueding
     * @Vsersion: 2023/5/30 下午4:59
     * @param $params
     * @return array
     */
    public function newCreate($params,$type = '')
    {
        // 注册service，分析是否允许开蓝票
        foreach(kernel::servicelist('ome.service.invoice.analysis.prepose') as $object)
        {
            if(method_exists($object, 'check_blue_invoice')){
                // check
                $analysisResult = $object->check_blue_invoice($params);
                if($analysisResult['rsp'] == 'fail'){
                    $error_msg = ($analysisResult['error_msg'] ? $analysisResult['error_msg'] : '不允许开蓝发票。');
                    return [false, ['msg'=>$error_msg]];
                }
            }
        }
        
        $mdlInOrder          = app::get('invoice')->model('order');
        $invoiceOrderItemMdl = app::get('invoice')->model('order_items');
        $invoiceOrderLib     = kernel::single('invoice_order');
        if (!isset($params['items']) || empty($params['items'])) {
            return [false, '缺少开票明细'];
        }

        list($check_create,$msg) = kernel::single('invoice_check')->checkInvoiceCreate(array_column($params['items'],'of_id'),$type);
        
        //创建发票校验
        if(!$check_create){
            return [false, $msg];
        }
        
        $type_id = 0;
        if (intval($params['mode']) != '1') {
            $type_id = '1';
        }
        
        $shop_id    = $params["shop_id"];
        $shopInfo = app::get('ome')->model('shop')->getRow(['shop_id'=>$shop_id],'org_id');
        $org_id = $shopInfo['org_id'];
        
        //发票金额
        $total_amount = number_format($params["amount"], 2, ".", ""); //纸质发票直接取填的开票金额为开票金额
        $tax_rate     = 0;//税率
        
        #到达ERP的订单金额，实际都是已经含税了的，所以这里的税金，需要自行推算出来
        $cost_tax       = 0;
        $tax_title = '个人';
        if ($params['title']) {
            $tax_title      = strip_tags(trim($params['title']));
        }
        if ($params['tax_title']) {
            $tax_title      = strip_tags(trim($params['tax_title']));//如抬头为空则用收货人名
        }
        
        $operator       = kernel::single('desktop_user')->get_id();//操作人ID
        $orderBn = $params['order_bn'];
        if ($params['source_bn']) {
            $orderBn = $params['source_bn'];
        }
        if (is_array($orderBn)) {
            $orderBn = implode(',',$orderBn);
        }
        $invoiceApplyBn = $this->getInvoiceApplyBn();
        if (isset($params['invoice_apply_bn']) && trim((string) $params['invoice_apply_bn']) !== '') {
            $invoiceApplyBn = trim((string) $params['invoice_apply_bn']);
        }

        $invoiceSource = 'b2c';
        if (isset($params['source']) && trim((string) $params['source']) !== '') {
            $invoiceSource = trim((string) $params['source']);
        }
        
        // order_id
        $invoiceOrderId = 0;
        
        $insert_arr = array(
            'is_print'          => '1', //这个值对于openapi的用户有用
            'order_id'          => $invoiceOrderId,
            'order_bn'          => $orderBn,
            'mode'              => 1,//这个key不存在就是0，1为电子发票
            'type_id'           => $type_id,//0普通发票，1专用发票
            'amount'            => $total_amount,#(含税)开票金额(在显示和调用的地方，允许客户按和甲方约定的开票金额，进行修改)
            'cost_tax'          => $cost_tax,
            'tax_rate'          => $tax_rate,
            'title'             => $tax_title,
            'content'           => $params['content'] ? $params['content'] : '商品明细', //发票内容默认是商品明细
            'operator'          => $operator,
            'create_time'       => time(),
            'tax_company'       => $params['invoice_receiver_name'] ?: $params['ship_name'],
            'ship_name'         => $params['invoice_receiver_name'] ?: $params['ship_name'],
            'ship_area'         => $params["ship_area"],
            'ship_addr'         => $params["ship_addr"],
            'ship_tel'          => $params['ship_tel'],
            'shop_id'           => $shop_id,
            'shop_type'         => $params["shop_type"],
            'ship_email'        => $params['receiver_email'] ? $params['receiver_email'] : $params['ship_email'],
            'ship_tax'          => $params['ship_tax'] ? $params['ship_tax'] : '',
            'cost_freight'      => $params['cost_freight'] ? $params['cost_freight'] : '0',
            'invoice_type'      => $params['invoice_type'] ? $params['invoice_type'] : 'normal',
            "remarks"           => $params["remarks"],
            "ship_bank"         => $params["ship_bank"],
            "ship_bank_no"      => $params["ship_bank_no"],
            "ship_company_addr" => $params["ship_company_addr"],
            "ship_company_tel"  => $params["ship_company_tel"],
            "org_id"            => $org_id,
            'source'            => $invoiceSource,
            'invoice_apply_bn'  => $invoiceApplyBn,
            'is_auto' => ($params['is_auto'] == '2' ? '2' : '1'), // 是否自动开票(1:自动开票, 2:手工开票)
        );
        //对pos判断btq trade 不在列表里显示
        if ($insert_arr['shop_type'] == 'pekon') {
            $stores = app::get('o2o')->model('store')->db_dump(array('shop_id' => $shop_id), 'store_sort');
            if (in_array($stores['store_sort'], array('BTQ', 'Trade'))) {
                $insert_arr['disabled'] = 'true';
            }
        }
        if ($params['is_status']) {
            $insert_arr['is_status'] = $params['is_status'];
        }
        if ($params['source_status']) {
            $insert_arr['source_status'] = $params['source_status'];
        }
        //发票备注
        if ($params['memo']) {
            $insert_arr['memo'] = $params['memo'];
        }
        
        if (strpos($orderBn,',')) {
           $insert_arr['invoice_type'] = 'merge';
        }
        
        if (isset($params['is_make_invoice']) && in_array($params['is_make_invoice'],['0','1','2'])) {
            //新发票设置为可操作状态
            $insert_arr['is_make_invoice'] = $params['is_make_invoice'];
        }
        
        //改票创建的发票直接可以进行开票
        if ($type == 'change_ticket') {
            $insert_arr['is_make_invoice'] = '1';//改票新建发票直接设置为可开票
            //更新老发票改票状态
            $mdlInOrder->update(['change_status'=>'2'],array("id"=>$params["id"]));
        }
        
        kernel::database()->beginTransaction();
        
        //新增发票
        $result   = $mdlInOrder->insert($insert_arr);

        switch ($type){
            case "add_new_same":
                $msg_part = "新建类似发票信息";
                break;
            case "change_ticket":
                $msg_part = "使用改票信息新建发票";
                break;
            case "add_merge_invoice":
                $msg_part = "使用合并发票新建发票";
                break;
            default:
                $msg_part = '创建';
        }
        //新增发票日志
        $opObj = app::get('ome')->model('operation_log');
        if ($result) {
            //组织明细数据
            $items = $invoiceOrderLib->getAddItemsData($params,$result,$type);
            $sql           = ome_func::get_insert_sql($invoiceOrderItemMdl, $items);
            $insertItemsRs = $invoiceOrderItemMdl->db->exec($sql);
            if (!empty($type)) {
                $invoiceOrderLib->updateInvoiceItems(['id'=>$result,'amount'=>$total_amount]);
            }
            if (!$insertItemsRs['rs']) {
                kernel::database()->rollBack();
                $msg = $msg_part . "明细失败。";
                return [false, $msg];
            }
            //创建如果是完成订单设置为完成
            kernel::database()->commit();
            $msg = $msg_part . "成功！";
            $opObj->write_log('invoice_create@invoice', $result, $msg);
            //查询是否发票是否可以直接操作
            kernel::single('invoice_func')->getInvoiceMakeStatus($result);
            
            //更新税金
            $invoiceOrderLib->updateInvoice($result,['invoice_amount'=>$total_amount,'is_status'=>$params['is_status']]);
            //生成的发票直接请求开票接口
//            $autoinvoice = app::get('ome')->getConf('ome.order.autoinvoice');
//            if ($params['action_type'] && in_array($params['action_type'],['doCheckChangeTicket','order_aftersale']) && $insert_arr['mode'] == '1' &&  $autoinvoice == 'on') {
//                $this->billing(['id'=>$result,'order_id'=>$order_id]);
//            }
            return [true, $msg];
        } else {
            kernel::database()->rollBack();
            $msg = $msg_part . "失败。";
            return [false, $msg];
        }
    }
    
    /**
     * [冲红电子发票]作废处理函数
     * @param array $params 传入参数
     */
    public function cancel($params,$type='order_cancel', &$result_msg=null)
    {
        // 注册service，分析是否允许冲红发票
        foreach(kernel::servicelist('ome.service.invoice.analysis.prepose') as $object)
        {
            if(method_exists($object, 'check_red_invoice')){
                // analysis
                $analysisResult = $object->check_red_invoice($params);
                if($analysisResult['rsp'] == 'fail'){
                    // 不允许创建发票，返回false
                    $result_msg = '不允许冲红发票';
                    return false;
                }
            }
        }
        
        // check
        $rs_invoice = kernel::single('invoice_check')->checkInvoiceCancel($params['id'], $error_msg);
        if(!$rs_invoice){
            $result_msg = $error_msg;
            return false;
        }
        //电子发票列表 操作项里的 作废link 必须是同一个id记录 否则返回false
        if($type == 'invoice_list' && intval($rs_invoice["id"]) != intval($params["id"])){
            $result_msg = '请选择正确的发票记录';
            return false;
        }
        $mdlInOrder = app::get('invoice')->model('order');
        
        $operator = kernel::single('ome_func')->getDesktopUser();
        $update_arr = array("is_status"=>2,"update_time"=>time(),"operator"=>$operator['op_id']);
        $filter_arr = array("id"=>$rs_invoice["id"]);
        $opObj = app::get('ome')->model('operation_log');
        

        if(intval($rs_invoice['mode']) == 1){
            //未开票 并且同步状态是 0(未同步) 和 2(开蓝失败)
            if(intval($rs_invoice['is_status']) == 0 && in_array(intval($rs_invoice['sync']), array(0,2))){
                //直接更新开票金额
                if ($type == 'order_aftersale') {
                    $orderInfo = app::get('ome')->model('orders')->db_dump(['order_id' => $params['order_id']], 'payed');
                    $amount    = $orderInfo['payed'];
                    list($rs, $rsData) = kernel::single('invoice_order')->getInvoiceMoney($rs_invoice);
                    if ($rs) {
                        $amount = $rsData['amount'];
                    }
                    $cost_tax = kernel::single('invoice_func')->get_invoice_cost_tax($amount, $rs_invoice["tax_rate"]);
                    if ($rs_invoice['amount'] != $amount && $amount > 0) {
                        $update_arr = array ("amount" => $amount, "update_time" => time(), "cost_tax" => $cost_tax);
                        $result     = $mdlInOrder->update($update_arr, $filter_arr);
                        $msg_part   = '部分退款更新发票金额，原发票金额：' . $rs_invoice['amount'] . '，修改后金额：' . $amount;
                    }
                }else{
                    $result = $mdlInOrder->update($update_arr,$filter_arr);
                    $msg_part = "作废未开票电子发票成功。";

                    // 作废ITEM数据
                    $eInvoidItemMdl = app::get('invoice')->model('order_electronic_items');
                    $eInvoidItemMdl->update(['invoice_status' => '2'],[
                        'id' => $rs_invoice["id"],
                        'invoice_status' => ['99','10']
                    ]);
                }
            }
            //已开票 并且同步状态是 3(开蓝成功) 4(开红票中) 和 5(冲红失败)
            if(intval($rs_invoice['is_status']) == 1 && in_array(intval($rs_invoice['sync']), array(3,4,5,8,9))){
                //电子发票冲红
                $checkEinvoiceCreate = kernel::single('invoice_check')->checkEinvoiceCreate($rs_invoice,"2", $error_msg);
                if(!$checkEinvoiceCreate){
                    $result_msg = $error_msg;
                    return false;
                }
                
                $rs_invoice['invoice_action_type'] = $params['invoice_action_type'];
                $rs_invoice = kernel::single('invoice_electronic')->getEinvoiceSerialNo($rs_invoice,"2");
                if(!$rs_invoice){
                    $result_msg = '没有可操作的明细记录';
                    return false;
                }
                //检查必要条件
                $check_do_einvoice = kernel::single('invoice_check')->checkDoEinvoice($rs_invoice);
                if(!empty($check_do_einvoice["arr_hint"])){
                    $opObj->write_log('invoice_cancel@invoice', $rs_invoice['id'], '冲红失败:'.(implode(',', $check_do_einvoice['arr_hint'])));
                    
                    $result_msg = '电子发票打接口的必要参数是否缺失';
                    return false;
                }
                
                kernel::single('invoice_electronic')->do_einvoice_create_limit($rs_invoice["id"],"red"); //做冲红点击动作缓存 防止连续点击
                
                // 电子发票冲红
                $rs = kernel::single('invoice_event_trigger_einvoice')->cancel($rs_invoice['shop_id'],$rs_invoice);
                if ($type == 'merge_order') {
                    $opObj->write_log('invoice_cancel@invoice', $rs_invoice['id'], '合并开票冲红原票');
                }
                $opObj->write_log('invoice_cancel@invoice', $rs_invoice['id'], $rs['msg']);
                
                // rsp
                if ($result['rsp'] == "fail") {
                    $result_msg = ($result['msg'] ? $result['msg'] : $result['error_msg']);
                }
                
                return $rs_invoice;
            }
        }else if(intval($rs_invoice['mode']) == 0){
            // 纸质售后不自动作废，需要发票追回的
            if ($type == 'order_aftersale') {
                $result_msg = '纸质发票不支持作废';
                return false;
            }

            if ($rs_invoice['is_status'] == '1') {
                $update_arr['is_make_invoice'] = '2';
                unset($update_arr['is_status']);
            }

            //纸质发票 直接标记作废
            $result = $mdlInOrder->update($update_arr,$filter_arr);
            $msg_part = "作废纸质发票成功。";
        }
        
        switch ($type){
            case "order_cancel":
                $msg = "订单取消处，".$msg_part;
                break;
             case "order_detail_basic":
                $msg = "订单详情处取消处，".$msg_part;
                break;
            case "invoice_list":
                $msg = "发票列表处，".$msg_part;
                break;
            case "order_detail":
                $msg = "发票详细处是否开票选择否，".$msg_part;
                break;
            case "batch_invoice_order":
               $msg = "批量设置不开票，".$msg_part;
               break;
            case "order_aftersale":
                $msg = "订单售后完成，" . $msg_part;
                break;
            case "merge_order":
                $msg = "合并开票，" . $msg_part;
                break;
            case "content_update":
                $msg = "开票内容更新，" . $msg_part;
                break;
        }
        
        //记录日志
        if($result){
            $result_msg = $msg;
            
            $opObj->write_log('invoice_cancel@invoice', $rs_invoice['id'], $msg);
            return $rs_invoice;
        }else{
            $result_msg  = '未处理冲红发票';
            return false;
        }
    }
    
    /**
     * 开票处理
     *
     * @param array $params 传入参数
     * @param string $event 事件触发,['man'=>'手动','consign' => '发货']
     * @param string $error_msg
     * @return array
     */
    function billing($params, $event='man', &$error_msg=null)
    {
        $invOrderMdl = app::get('invoice')->model('order');
        $opObj = app::get('ome')->model('operation_log');
        
        $result = array('rsp'=>'fail', 'error_msg'=>'', 'mode'=>1);
        
        // 获取发票信息
        $invoice_id = intval($params['id']);
        $invoiceInfo = $invOrderMdl->db_dump(['id'=>$invoice_id]);
        
        // check
        if(empty($invoiceInfo)){
            $result['error_msg'] = '发票信息不存在';
            return $result;
        }
        
        // source
        if(!isset($params['source']) && $invoiceInfo['source']){
            $params['source'] = $invoiceInfo['source'];
        }
        
        // 注册service，分析是否允许开蓝票
        foreach(kernel::servicelist('ome.service.invoice.analysis.prepose') as $object)
        {
            if(method_exists($object, 'check_blue_invoice')){
                // check
                $analysisResult = $object->check_blue_invoice($params);
                if($analysisResult['rsp'] == 'fail'){
                    $error_msg = ($analysisResult['error_msg'] ? $analysisResult['error_msg'] : '不允许直接开蓝发票；');
                    
                    $result['error_msg'] = $error_msg;
                    return $result;
                }
            }
        }
        
        try {
            if ($invoiceInfo) {
                if ($invoiceInfo['sync'] == '0') {
                    $invOrderMdl->update(['sync'=>'1'],['id'=>$params['id']]);
                }
            }
            
            $checkLib = kernel::single('invoice_check');
            $electLib = kernel::single('invoice_electronic');
            
            //开票触发点 在发票列表操作区域
            $rs_invoice = $checkLib->checkMakeInvoice($params, $error_msg);
            if(!$rs_invoice){
                throw new Exception($error_msg);
            }
            
            $rs_invoice['mode'] = intval($rs_invoice['mode']);
            
            // mode
            $result['mode'] = $rs_invoice['mode'];
            
            if($rs_invoice['mode'] == 1){
    
                if ($event != 'man') {
                    if ($rs_invoice['einvoice_operating_conditions'] == '3' && $event != 'sign') {
                        $error_msg = '只允许签收触发开票';
                        throw new Exception($error_msg);
                    }
                }
    
                //电子发票开蓝
                $checkEinvoiceCreate = $checkLib->checkEinvoiceCreate($rs_invoice, $error_msg);
                if(!$checkEinvoiceCreate){
                    throw new Exception($error_msg);
                }
                
                $isCheck = $electLib->getEinvoiceSerialNo($rs_invoice);
                if(!$isCheck || !$rs_invoice['serial_no']){
                    $error_msg = '没有开票流水号';
                    throw new Exception($error_msg);
                }
                
                //操作日志
                $msg = !isset($params['msg']) ? '点击开票' : $params['msg'];
                $opObj->write_log('invoice_billing@invoice', $rs_invoice['id'], $msg);
                
                //开票点击动作缓存,防止连续点击
                $electLib->do_einvoice_create_limit($rs_invoice["id"]);
                
                //request请求开蓝票
                $rs = kernel::single('invoice_event_trigger_einvoice')->create($rs_invoice['shop_id'], $rs_invoice, $error_msg);
                if($rs['rsp'] == 'fail' || !$rs){
                    $error_msg = '开蓝失败：'. ($rs['msg'] ? $rs['msg'] : $error_msg) .'('.$rs['msg_id'].')';
                    throw new Exception($error_msg);
                }
                
                //log
                $log_msg = '开蓝中（'.$rs['msg_id'].')';
                $opObj->write_log('invoice_billing@invoice', $rs_invoice['id'], $log_msg);
                
                //返回成功
                $result['rsp'] = 'succ';
                
                return $result;
            } elseif ($event == 'man') {
                //纸质发票开票
                $mdlInOrder = app::get('invoice')->model('order');
                
                $cur_time = time();
                $operator = kernel::single('desktop_user')->get_id();
                
                $update_arr = array("is_status"=>1,"update_time"=>$cur_time,"dateline"=>$cur_time,"operator"=>$operator);
                $filter_arr = array("id"=>$rs_invoice["id"]);
                $rs = $mdlInOrder->update($update_arr, $filter_arr);
                
                //log
                $log_msg = '纸质发票开票成功。';
                $opObj->write_log('invoice_billing@invoice', $rs_invoice['id'], $log_msg);
                
                $result['rsp'] = 'succ';
                return $result;
            }
        } catch (Exception $e){
            $msg = $e->getMessage();
            if ($oldInvoice = $invOrderMdl->db_dump(['id'=>$params['id']])) {
                if ($oldInvoice['sync'] == '1') {
                    $updateData['sync'] = '2';
                    if ($msg == '请求开票失败:没有发票明细') {
                        $updateData['is_status'] = '2';
                    }
                    $invOrderMdl->update($updateData,['id'=>$params['id']]);
                }
            }
            $opObj->write_log('invoice_billing@invoice', $params['id'], $msg);
            
            $result['error_msg'] = $msg;
            
            return $result;
        }
    }

    /**
     * 编辑处理函数
     * @param array $params 传入参数
     */
    public function edit($params){

        if(!$params["id"]){
            return [false,'缺少必要参数'];
        }
        $mdlInOrder = app::get('invoice')->model('order');
        $invoiceItemMdl = app::get('invoice')->model('order_items');
        $invoiceOrderLib     = kernel::single('invoice_order');
    
        //条件
        $filter_arr = array("id"=>$params["id"],"is_status"=>"0");
        $rs_invoice_order = $mdlInOrder->dump($filter_arr,"*");
        $invoice_order_items = $invoiceItemMdl->getList('*',array('id'=>$params["id"],'is_delete'=>'false'));
        //编辑合并发票明细处理
        if ($rs_invoice_order['invoice_type'] == 'merge') {
            $invoiceOrderLib->updateMergeInvoiceItems($params,$invoice_order_items);
        }
        //更新内容
        $operator = kernel::single('desktop_user')->get_id();
        $cur_time = time();
        $update_arr = array (
            "title"             => $params["title"],
            "content"           => $params["content"],
            "remarks"           => $params["remarks"],
            "operator"          => $operator,
            "update_time"       => $cur_time,
            "bank"              => $params["bank"],
            "bank_no"           => $params["bank_no"],
            "tax_company"       => $params["tax_company"],
            "ship_name"         => $params["tax_company"],
            "ship_tel"          => $params["ship_tel"],
            "ship_tax"          => $params["ship_tax"],
            "ship_area"         => $params["ship_area"],
            "ship_addr"         => $params["ship_addr"],
            'ship_company_addr' => $params['ship_company_addr'],
            'hsbz'              => $params['hsbz'],#含税标志
            'lslbs'             => $params['lslbs'],#零税率标示
            'yhzcbs'            => $params['yhzcbs'],#是有优惠
            'zzstsgl'           => $params['zzstsgl'],#增值税特殊管理
            'payee_receiver'    => $params['payee_receiver'],
            'payee_checker'     => $params['payee_checker'],
            'ship_company_tel'  => $params['ship_company_tel'],
            'is_edit'           => 'true',
            "ship_email"        => $params["ship_email"],
            "amount"            => $params["amount"],
            "ship_bank"         =>$params["ship_bank"],
            "ship_bank_no"      =>$params["ship_bank_no"],
        );
        
        if (isset($params['type_id'])) {
            $update_arr['type_id'] = strval(intval($params['type_id']));
        }
        if (isset($params['mode']) && in_array($params['mode'], ['0', '1'])) {
            $update_arr['mode'] = $params['mode'];
        }
        
        // 验证手机号、电话
        if(!empty($update_arr['ship_tel'])){
            $is_mobile = kernel::single('ome_func')->isMobile($update_arr['ship_tel']);
            $is_tel = kernel::single('ome_func')->isTel($update_arr['ship_tel']);
            
            if(!$is_mobile && !$is_tel){
                $error_msg = '收票人手机号、电话不正确，请正确填写后再提交！';
                return [false, $error_msg];
            }
        }
        
        // 收票人Email
        if(!empty($update_arr['ship_email'])){
            if (!preg_match("/^([0-9A-Za-z\\-_\\.]+)@([0-9a-z]+\\.[a-z]{2,3}(\\.[a-z]{2})?)$/i", $update_arr['ship_email'])) {
                $error_msg = '收票人Email不正确，请正确填写后再提交！';
                return [false, $error_msg];
            }
        }
        
        //备注
        $oldmemo = @unserialize($rs_invoice_order['memo']);
        if ($oldmemo) {
            if ($oldmemo && end($oldmemo)['op_content'] != $params['memo']) {
                $op_name = kernel::single('desktop_user')->get_name();
                if ($oldmemo) {
                    foreach ($oldmemo as $k => $v) {
                        $memoList[] = $v;
                    }
                }
                $newmemo = htmlspecialchars($params['memo']);
                $newmemo = array('op_name'    => $op_name, 'op_time'    => date('Y-m-d H:i:s', time()), 'op_content' => $newmemo);
                $memoList[]  = $newmemo;
                $update_arr['memo'] = @serialize($memoList);
            }
        }

        kernel::database()->beginTransaction();
        
        $result = $mdlInOrder->update($update_arr,$filter_arr);

        $msg_part = "编辑发票";

        $opObj = app::get('ome')->model('operation_log');
        if($result){
            if ($params['item_id']) {
                //更新发票明细
                list($updateItemRes,$msg_part) = $invoiceOrderLib->updateInvoiceItems($params,$invoice_order_items);
                if (!$updateItemRes) {
                    kernel::database()->rollBack();
                    $msg = $msg_part;
                    $opObj->write_log('invoice_edit@invoice', $params["id"], $msg);
                    return [false,$msg];
                }
                $invoiceOrderLib->updateInvoice($params["id"],['invoice_amount'=>$params["amount"]]);
            }
            kernel::database()->commit();
            //编辑快照
            $log_memo        = serialize(['invoice'=>$rs_invoice_order,'invoice_order_items'=>$invoice_order_items]);
            $opObj->write_log('invoice_edit@invoice', $params["id"], $log_memo);
            return [true];
        }else{
            kernel::database()->rollBack();
            $msg = $msg_part.'失败。';
            $opObj->write_log('invoice_edit@invoice', $params["id"], $msg);
            return [false,$msg];
        }
        
    }
    
    #发货后的操作，自动开发票
    # 20230206要签收后开票 发货后开票暂时屏蔽
    public function after_consign_autoinvoice($delivery_id){
        // 发货后自动开票
        return true;
        $autoinvoice = app::get('ome')->getConf('ome.order.autoinvoice');
        if ($autoinvoice != 'on') {
            return true;
        }

        $deliveryOrderModel = app::get('ome')->model('delivery_order');
        $mdlInOrder = app::get('invoice')->model('order');
        $deliveryOrderList = $deliveryOrderModel->getList('order_id',array('delivery_id'=>$delivery_id));
        $orderIds = array_map('current', $deliveryOrderList);
        if(!$orderIds)return true;
        foreach($orderIds as $order_id){
            #获取订单未开票的发票记录
            $arr_filter = array('order_id' => $order_id,'is_status' => '0');
            $rs_invoice = $mdlInOrder->getList('*', $arr_filter, 0, 1, 'id DESC');
            if(!$rs_invoice)continue;
            $arr_billing = array(
                "id" => $rs_invoice[0]['id'],
                "order_id" => $order_id,
            );
            #调开票的公用方法
            $this->billing($arr_billing, 'consign');
        }
    }
    
    /**
     * 保存改票信息内容
     * @Author: xueding
     * @Vsersion: 2022/10/25 下午4:51
     * @param $data
     * @return array
     */
    public function addChangeTicketData($data)
    {
        $mdlInOrder = app::get('invoice')->model('order');
        if ($data) {
            if ($invoiceInfo = $mdlInOrder->db_dump($data['id'])) {
                $memoList = array();
                $oldmemo = @unserialize($invoiceInfo['memo']);
                if (is_array($oldmemo) && end($oldmemo)['op_content'] != $data['memo']) {
                    $op_name = kernel::single('desktop_user')->get_name();
                    if ($oldmemo) {
                        foreach ($oldmemo as $k => $v) {
                            $memoList[] = $v;
                        }
                    }
                    $newmemo = htmlspecialchars($data['memo']);
                    $newmemo = array('op_name'    => $op_name, 'op_time'    => date('Y-m-d H:i:s', time()), 'op_content' => $newmemo);
                    $memoList[]  = $newmemo;
        
                }
                $data['memo'] = @serialize($memoList);
                $updateData = ['changesdf' => json_encode($data), 'change_status' => '1'];
                //电票改票自动冲红
                if ($invoiceInfo['mode'] == '1') {
                    $result = $mdlInOrder->update($updateData, ['id' => $data['id']]);
                } else {
                    //纸票改票改为待冲红
                    $updateData['is_make_invoice'] = '2';
                    $result = $mdlInOrder->update($updateData, ['id' => $data['id']]);
                }
            }
        
            $opObj = app::get('ome')->model('operation_log');
            if ($result) {
                $msg = '专票保存改票信息成功。';
                $result_msg = '';
                if ($invoiceInfo['mode'] == '1') {
                    $msg = '电票保存改票信息成功。';
                    $param     = array(
                        'id'                  => $data['id'],
                        'order_id'            => $data['order_id'],
                        'invoice_action_type' => $_POST['invoice_action_type'],
                        'source'              => $invoiceInfo['source'], // 发票来源,默认值:b2c(默认值)
                    );
                    $cancelRes = $this->cancel($param, 'invoice_list', $result_msg);
                    if (!$cancelRes) {
                        return [false, '原票冲红失败：'. $result_msg];
                    }
                }
                $opObj->write_log('invoice_edit@invoice', $data["id"], $msg);
    
                return [true, $msg];
            } else {
                $msg = '保存改票信息失败。';
                $opObj->write_log('invoice_edit@invoice', $data["id"], $msg);
                return [false, $msg];
            }
        }
    }
    
    /**
     * 创建合并发票
     * @Author: xueding
     * @Vsersion: 2023/6/2 下午3:52
     * @param $params
     * @return array
     */
    public function addMergeInvoice($params)
    {
        $id = explode(',',$params['item']['id']);
        $invoiceOrderLib = kernel::single('invoice_order');
        $invoiceMdl = app::get('invoice')->model('order');
        $invoiceList = $invoiceMdl->getList('*',['id'=>$id]);
        $invoiceList = array_column($invoiceList,null,'id');
        //作废或者冲红原发票
        $invoiceOrderLib->cancelOldInvoiceOrder($id);
        
        $invoiceItemMdl = app::get('invoice')->model('order_items');
        $data = $invoiceOrderLib->formatAddData($params['item']);
        
        $items         = $invoiceItemMdl->getList('*', ['id' => $id,'is_delete'=>'false']);
        $bns = [];
        foreach ($params['item']['bn'] as $item_id => $bn) {
            $bns[$bn]['spec']      = $params['item']['specification'][$item_id];
            $bns[$bn]['unit']      = $params['item']['unit'][$item_id];
            $bns[$bn]['item_name'] = $params['item']['item_name'][$item_id];
            $bns[$bn]['tax_code']  = $params['item']['tax_code'][$item_id];
        }
        foreach ($items as $key => $val) {
            if (isset($bns[$val['bn']])) {
                $items[$key]['specification'] = $bns[$val['bn']]['spec'];
                $items[$key]['unit']          = $bns[$val['bn']]['unit'];
                $items[$key]['item_name']     = $bns[$val['bn']]['item_name'];
                $items[$key]['tax_code']      = $bns[$val['bn']]['tax_code'];
            }
            $items[$key]['original_id']          = $val['id'];
            $items[$key]['original_item_id']     = $val['item_id'];
           
            if ($invoiceList[$val['id']]['is_status'] == '1') {
                $items[$key]['item_is_make_invoice'] = '0';
                $items[$key]['inoperable_reason'] = '发票冲红中，暂不可操作';
            }else{
                $items[$key]['item_is_make_invoice'] = $val['item_is_make_invoice'];
            }
        }
        $data['is_edit'] = 'true';
        $data['org_id'] = 'true';
        $data['items'] = $items;
        if ($data['mode'] != '1') {
            $data['mode'] = '3';
        }
        return $this->newCreate($data,'add_merge_invoice');
    }

    /**
     * 生成开票申请单号
     */
    public function getInvoiceApplyBn($source = 'b2c')
    {
        $type = 'INVOICE';
        $prefix = 'INV' . date('Ymd');
        $sign = kernel::single('eccommon_guid')->incId($type, $prefix, 6, true);
        return $sign;
    }
}
