<?php
class sales_aftersale_type_change
{
    public function generate_aftersale($reship_id = null)
    {
        if (empty($reship_id)) {
            return false;
        }

        $Omembers        = app::get('ome')->model('members');
        $Oorder          = app::get('ome')->model('orders');
        $Oshop           = app::get('ome')->model('shop');
        $Oreship         = app::get('ome')->model('reship');
        $Orefunds        = app::get('ome')->model('refunds');
        $Orefund_apply   = app::get('ome')->model('refund_apply');
        $Oreturn_product = app::get('ome')->model('return_product');
        $Opam            = app::get('pam')->model('account');
        $Obranch         = app::get('ome')->model('branch');
        $Oprocess        = app::get('ome')->model('return_process_items');
        $Oreship_items = app::get('ome')->model('reship_items');
        $salesMdl        = app::get('ome')->model('sales');
        //退换货单
        $reshipData      = $Oreship->getList('*', array('reship_id' => $reship_id));
        $Oorder_items    = app::get('ome')->model('order_items');
        $shopData        = $Oshop->getList('name,shop_bn', array('shop_id' => $reshipData[0]['shop_id']));
        $orderData       = $Oorder->getList('member_id,order_bn,platform_order_bn,betc_id,cos_id', array('order_id' => $reshipData[0]['order_id']));
        
        //判断是否来源为归档
        $is_archive = kernel::single('archive_order')->is_archive($reshipData[0]['source']);
        if ($is_archive) {
            $archive_ordObj = kernel::single('archive_interface_orders');
            $orderData      = $archive_ordObj->getOrders($reshipData[0]['order_id'], 'member_id,order_bn');
            $orderData[0]   = $orderData;
            
            $sort = false;
            $orderitems = $archive_ordObj->getItemList($reshipData[0]['order_id'], $sort);
        } else {
            $orderitems = $Oorder_items->getList('pmt_price,price,bn,item_id,obj_id,shop_goods_id,shop_product_id', array('order_id' => $reshipData[0]['order_id']));
        }
        
        $shopData   = $Oshop->getList('name,shop_bn', array('shop_id' => $reshipData[0]['shop_id']));
        $memberData = $Omembers->getList('uname', array('member_id' => $orderData[0]['member_id']));
        $pamDatas   = $Opam->getList('login_name,account_id');

        foreach ($pamDatas as $v) {
            $pam_data[$v['account_id']] = $v['login_name'];
        }

        unset($pamDatas);

        $branch_datas = $Obranch->getList('name,branch_id');

        foreach ($branch_datas as $v) {
            $branch_data[$v['branch_id']] = $v['name'];
        }
        
        unset($branch_datas);

        if ($reshipData[0]['return_id']) {
            $returnData = $Oreturn_product->getList('return_bn,add_time,real_refund_amount', array('return_id' => $reshipData[0]['return_id']));
        }

        //售后退换货产生的退款申请（当退款金额大于0时）
        $reship_apply = $Orefund_apply->db->select("select * from sdb_ome_refund_apply where reship_id=" . $reship_id . " AND memo!='' AND refund_refer='1'");

        if ($reship_apply) {
            $apply_detail = $reship_apply;
        } else {
            $apply_detail = $Orefund_apply->db->select("select * from sdb_ome_refund_apply where memo like '%" . $reshipData[0]['reship_bn'] . "%' and refund_refer='1' group by order_id ");
        }
        //更新退货单
        if($apply_detail[0]['status'] == '4') {
            $Oreship->update(['refund_status'=>'finish'], ['reship_id'=>$reship_id]);
        } else if($apply_detail[0]['status'] == '3'){
            $Oreship->update(['refund_status'=>'refuse'], ['reship_id'=>$reship_id]);
        }
        $aftersales_set = app::get('ome')->getConf('ome.aftersales.auto_finish');

        if (!empty($apply_detail)) {
            $applymoney = 0;
            if ($reshipData[0]['return_type'] == 'return') {
                $applymoney = $reshipData[0]['totalmoney'];
            } elseif ($reshipData[0]['return_type'] == 'change') {
                $applymoney = $reshipData[0]['totalmoney'] + $reshipData[0]['cost_freight_money'] + $reshipData[0]['change_amount'];
            }
            
            if($aftersales_set == 'false' || empty($aftersales_set)){
                if (bcsub($apply_detail[0]['money'], $applymoney, 2) > 0) {
                    return false;
                }
                
                if (!in_array($apply_detail[0]['status'], array('3', '4'))) {
                    return false;
                }
            }
            
            if ($apply_detail[0]['payment']) {
                $payment_cfgObj = app::get('ome')->model('payment_cfg');
                $payment_cfg    = $payment_cfgObj->dump(array('id' => $apply_detail[0]['payment']), 'custom_name');
                $paymethod      = $payment_cfg['custom_name']; //支付方式  varchar
            } else {
                $refund_detail = $Orefunds->getList('paymethod', array('refund_bn' => $apply_detail[0]['refund_apply_bn']));
                $paymethod     = $refund_detail[0]['paymethod'];
            }
        }

        $problem_name = '';
        if ($reshipData[0]['problem_id']) {
            $problemObj   = app::get('ome')->model('return_product_problem');
            $problemdata  = $problemObj->dump(array('problem_id' => $reshipData[0]['problem_id']), 'problem_name');
            $problem_name = $problemdata['problem_name'];
        }

        $rprocessData               = $Oprocess->getList('op_id,acttime', array('reship_id' => $reship_id));
        $branch_id                  = intval($reshipData[0]['branch_id']);
        
        //平台订单号
        $platform_order_bn = '';
        if(empty($orderData[0]['platform_order_bn'])){
            $platform_order_bn = $orderData[0]['platform_order_bn'];
        }elseif($reshipData[0]['platform_order_bn']){
            $platform_order_bn = $reshipData[0]['platform_order_bn'];
        }
    
        //销售单
        $salesInfo = $salesMdl->db_dump(['order_id'=>$reshipData[0]['order_id']],'sale_id,order_id,ship_time');
        
        //data
        $data['shop_id']            = $reshipData[0]['shop_id'];
        $data['shop_bn']            = $shopData[0]['shop_bn'];
        $data['shop_name']          = $shopData[0]['name'];
        $data['order_id']           = $reshipData[0]['order_id'];
        $data['order_bn']           = $orderData[0]['order_bn'];
        $data['platform_order_bn']  = $platform_order_bn;
        $data['return_id']          = $reshipData[0]['return_id'];
        $data['return_bn']          = $returnData[0]['return_bn'];
        $data['reship_id']          = $reship_id;
        $data['reship_bn']          = $reshipData[0]['reship_bn'];
        $data['return_apply_id']    = $apply_detail[0]['apply_id'];
        $data['return_apply_bn']    = $apply_detail[0]['refund_apply_bn'];
        $data['return_type']        = $reshipData[0]['return_type'];
        $data['refundmoney']        = $apply_detail[0]['refunded'] + $reshipData[0]['had_refund'];
        if($apply_detail[0]['money']) {
            $data['refund_apply_money'] = $apply_detail[0]['money'] + $reshipData[0]['had_refund'];
        } else {
            $data['refund_apply_money'] = $reshipData[0]['totalmoney'] + $reshipData[0]['change_amount'] + $reshipData[0]['had_refund'];
        }
        $data['real_refund_amount'] = $returnData[0]['real_refund_amount'] ? : 
                                        ($apply_detail[0]['real_refund_amount'] ? : 
                                            ($data['refund_apply_money'] ? : $data['refundmoney']));
        $data['paymethod']          = $paymethod;
        $data['member_id']          = $orderData[0]['member_id'];
        $data['member_uname']       = $memberData[0]['uname'];
        $data['ship_mobile']        = $reshipData[0]['ship_mobile'];
        $data['add_time']           = $returnData[0]['add_time'];
        $data['check_time']         = $reshipData[0]['t_begin'];
        $data['acttime']            = $rprocessData[0]['acttime'];
        $data['refundtime']         = $apply_detail[0]['last_modified'];
        $data['org_id']             = $reshipData[0]['org_id'];
        $data['receiving_status']   = $reshipData[0]['is_check'] == '7' ? '1' : '0';
        
        $data['check_op_id']   = $reshipData[0]['op_id'];
        $data['check_op_name'] = $pam_data[$reshipData[0]['op_id']];

        $data['op_id']   = $rprocessData[0]['op_id'];
        $data['op_name'] = $pam_data[$rprocessData[0]['op_id']];

        $data['refund_op_id']   = $apply_detail[0]['verify_op_id'];
        $data['refund_op_name'] = $pam_data[$apply_detail[0]['verify_op_id']];

        $data['aftersale_time']    = time();
        $data['diff_order_bn']     = $reshipData[0]['diff_order_bn'];
        $data['change_order_bn']   = $reshipData[0]['change_order_bn'];
        $data['pay_type']          = $apply_detail[0]['pay_type'];
        $data['account']           = $apply_detail[0]['account'];
        $data['bank']              = $apply_detail[0]['bank'];
        $data['pay_account']       = $apply_detail[0]['pay_account'];
        $data['refund_apply_time'] = $apply_detail[0]['create_time'];
        $data['problem_name']      = $problem_name;
        $data['ship_time']         = $salesInfo['ship_time'];//发货时间
        
        if ($is_archive) {
            $data['archive'] = '1';
        }
        if($reshipData[0]['delivery_id']) {
            $data['delivery_id'] = $reshipData[0]['delivery_id'];
            $data['delivery_bn'] = app::get('sales')->model('delivery_order')->db_dump(['delivery_id'=>$data['delivery_id']], 'delivery_bn')['delivery_bn'];
        }
        
        //获取退货单明细
        $reshipitemData = $Oreship_items->db->select("SELECT item_id as obj_item_id, order_item_id,bn,product_name,product_id,num,price,branch_id,return_type,defective_num,normal_num,amount FROM sdb_ome_reship_items WHERE reship_id=" . $reship_id . " AND  ((defective_num>0 OR normal_num>0 OR num>0) OR return_type='change') AND `is_del`='false' ");
        
        //获取销售单明细金额
        $orderSales = $this->getSaleAmount($reshipData[0]['order_id']);

        $objs = $this->getOrderObj($reshipData[0]['order_id'],$is_archive);

        //取原实付价
        $itemsales = array();
        $itemsList = [];
        foreach ($orderitems as $v) {
            $obj_bn = $objs[$v['obj_id']];

            $itemsales[$v['item_id']] = $orderSales[$obj_bn][$v['bn']];

            $itemsdata[$v['bn']] = $v;
    
            $itemsList[$v['item_id']]['addon'] = json_encode(['shop_goods_id' => $v['shop_goods_id'], 'shop_product_id' => $v['shop_product_id']],JSON_UNESCAPED_UNICODE);
        }

        unset($orderitems);
        
        //获取退货残次仓ID
        $damagedInfo = kernel::single('console_iostockdata')->getDamagedbranch($branch_id);
        
        //return_process_items
        $returnProcessItem = $Oprocess->getList('product_id,branch_id', array('reship_id' => $reship_id));
        $returnProcessItem = array_column($returnProcessItem,null,'product_id');
        
        //计算实际 退款申请金额 = (退入商品和折旧费)分权平摊后的价格
        if ($reshipData[0]['bmoney'] > 0) {
            $_apply_money = array();

            $reshipitemsdata = $Oreship_items->db->select('select sum(price*num) as return_money,price,num,bn from sdb_ome_reship_items where reship_id = ' . $reship_id . ' and return_type = "return" AND `is_del`="false" group by item_id');

            $reshipcount = $Oreship_items->db->select('select sum(price*num) as total_return_money,count(*) as count from sdb_ome_reship_items where reship_id = ' . $reship_id . ' and return_type = "return" AND `is_del`="false"');

            $tmp_money = 0.00;
            $loop      = 1;
            foreach ($reshipitemsdata as $k => $v) {
                if ($reshipcount[0]['count'] == $loop) {
                    $_apply_money[$v['bn']]['apply_money'] = $reshipData[0]['bmoney'] - $tmp_money;
                    $tmp_money                             = 0;
                } else {
                    $_apply_money[$v['bn']]['apply_money'] = $reshipData[0]['bmoney'] * ($v['return_money'] / ($reshipcount[0]['total_return_money']));
                    $tmp_money += $_apply_money[$v['bn']]['apply_money'];
                }

                $loop++;
            }
            unset($reshipitemsdata);
        }
        
        $isAmountOrItem = 'item';
        $refundAmountSum = 0;
        foreach ($reshipitemData as $v) {
            $refundAmountSum = bcadd($refundAmountSum, $v['amount'], 2);
        }
        
        if($refundAmountSum != 0 && $refundAmountSum == $data['refund_apply_money']) {
            $isAmountOrItem = 'amount';
        }
        
        $aftersale_items = array();
        
        //查询出入库明细成本更新
        $iostockMdl = app::get('ome')->model('iostock');
        $iostock_arr = $iostockMdl->db->select('select bn,max( unit_cost ) AS unit_cost,sum(inventory_cost) as inventory_cost,original_id from sdb_ome_iostock where original_id = ' . $reship_id . ' and original_bn = "'.$reshipData[0]['reship_bn'] . '" GROUP BY bn');
        
        foreach ($iostock_arr as $k => $iostock){
            $iostockInfos[$iostock['bn']] = $iostock;
        }
        
        $propertyList = array();
        foreach ($reshipitemData as $reship)
        {
            $product_id = $reship['product_id'];
            $defective_num = $reship['defective_num'];
            $normal_num = $reship['normal_num'];
            $reship['cost'] = $iostockInfos[$reship['bn']]['unit_cost'];
            $reship['cost_amount'] = $iostockInfos[$reship['bn']]['inventory_cost'];
            if ($aftersales_set == 'refunded' && $reshipData[0]['is_check'] != '7' && $reship['normal_num'] == 0 && $reship['defective_num'] == 0 && $reship['return_type'] == 'return') {
                $reship['branch_id'] = $branch_id;
                $reship['apply_num'] = $reship['num'];
                $aftersale_items[]   = $reship;
            }
            //不良品需要使用：残次仓
            if ($reship['defective_num'] > 0 && $reship['return_type'] == 'return') {
                $damaged_branch_id = ($damagedInfo['branch_id'] ? $damagedInfo['branch_id'] : $returnProcessItem[$product_id]['branch_id']);
                $tmp = $reship;
                $tmp['branch_id'] = $damaged_branch_id;
                $tmp['num']       = $reship['defective_num'];
                $tmp['normal_num']       = 0;
                if($reship['normal_num'] > 0) {
                    $tmp['apply_num'] = $reship['num'] - $reship['normal_num'];
                } else {
                    $tmp['apply_num'] = $reship['num'];
                }
                $aftersale_items[]   = $tmp;
            }
            
            //良品
            if ($reship['normal_num'] > 0 && $reship['return_type'] == 'return') {
                $tmp = $reship;
                $tmp['num']       = $reship['normal_num'];
                $tmp['branch_id'] = $branch_id;
                $tmp['defective_num']       = 0;
                if($reship['defective_num'] > 0) {
                    $tmp['apply_num'] = $reship['normal_num'];
                } else {
                    $tmp['apply_num'] = $reship['num'];
                }
                $aftersale_items[]   = $tmp;
            }
            if ($reship['return_type'] == 'change') {

                $aftersale_items[] = $reship;
            }
            
            //良品商品
            if($normal_num){
                $propertyList[$product_id]['normal'] = $normal_num;
            }
            
            //不良品商品
            if($defective_num){
                $propertyList[$product_id]['defective'] = $defective_num;
            }
        }

        // 售后明细组装后扩展：客户 app 可补全 WMS 短收时被标品遗漏的退货明细行
        // service: sales.service.aftersale.change.items.after，方法 append_missing_items
        if ($services = kernel::servicelist('sales.service.aftersale.change.items.after')) {
            foreach ($services as $instance) {
                if (method_exists($instance, 'append_missing_items')) {
                    $aftersale_items = $instance->append_missing_items($aftersale_items, array(
                        'reshipitemData' => $reshipitemData,
                        'branch_id'      => $branch_id,
                        'iostockInfos'   => $iostockInfos,
                    ));
                }
            }
        }

        unset($reshipitemData);
        
        //获取退货关联的订单明细
        $aftersaleLib = kernel::single('sales_aftersale');
        $returnItems = $aftersaleLib->getReturnOrderItems($reshipData[0]['order_id'], $is_archive);
        $dlySaleItems = app::get('sales')->model('delivery_order_item')->getList('order_item_id,nums,platform_amount,settlement_amount,actually_amount,platform_pay_amount', ['order_id'=>$reshipData[0]['order_id']]);
        $dlySaleItems = array_column($dlySaleItems, null, 'order_item_id');
        $data['platform_amount'] = 0;
        $data['settlement_amount'] = 0;
        //format
        foreach ($aftersale_items as $k => $v)
        {
            $order_item_id = intval($v['order_item_id']);
            $product_id = $v['product_id'];
            
            //存在良品和不良品
            $normal_num = intval($propertyList[$product_id]['normal']); //良品退货数量
            $defective_num = intval($propertyList[$product_id]['defective']); //不良品退货数量
            $isFlag = false;
            if($normal_num && $defective_num){
                $isFlag = true;
            }
            
            //format
            $aftersale_items[$k]['pay_type']    = $data['pay_type'];
            $aftersale_items[$k]['account']     = $data['account'];
            $aftersale_items[$k]['bank']        = $data['bank'];
            $aftersale_items[$k]['pay_account'] = $data['pay_account'];

            $aftersale_items[$k]['payment']       = $apply_detail[0]['payment'];
            $aftersale_items[$k]['create_time']   = $data['refund_apply_time'];
            $aftersale_items[$k]['last_modified'] = $data['refundtime'];

            $aftersale_items[$k]['branch_name'] = $branch_data[$v['branch_id']];
            if ($v['return_type'] == 'change') {
                $aftersale_items[$k]['saleprice'] = $v['price'] * $v['num']; //销售价
                $aftersale_items[$k]['refunded']  = 0;
                $aftersale_items[$k]['money']     = 0;
            } else {
                //return
                $refundapply_money = $isAmountOrItem == 'amount' ? $v['amount'] : $v['price']*$v['num'];
                
                //birken勃肯客户申请退款金额 = price单价 * num退货数量
                if(strtolower($_SERVER['SERVER_NAME']) == 'birkenstock.erp.taoex.com'){
                    $refundapply_money = $v['price'] * $v['num'];
                }
                
                if ($reshipData[0]['bmoney'] > 0) {
                    $refundapply_money = $refundapply_money - $_apply_money[$v['bn']]['apply_money'];
                }
                
                $aftersale_items[$k]['saleprice'] = ($itemsales[$v['order_item_id']] * $v['num']);
                
                //退货入商品:良品和不良品都存在
                if($isFlag){
                    $refundapply_money = $v['price'] * $v['num'];
                }
                
                //已退款金额
                $aftersale_items[$k]['refunded']  = $refundapply_money;
                
                //申请退款金额
                $aftersale_items[$k]['money'] = $refundapply_money;
                if($dlySaleItems[$order_item_id]) {
                    $platform_amount = sprintf("%.2f", $dlySaleItems[$order_item_id]['platform_amount'] * $v['num'] / $dlySaleItems[$order_item_id]['nums']);
                    $settlement_amount = sprintf("%.2f", $dlySaleItems[$order_item_id]['settlement_amount'] * $v['num'] / $dlySaleItems[$order_item_id]['nums']);
                    $actually_amount = sprintf("%.2f", $dlySaleItems[$order_item_id]['actually_amount'] * $v['num'] / $dlySaleItems[$order_item_id]['nums']);
                    $platform_pay_amount = sprintf("%.2f", $dlySaleItems[$order_item_id]['platform_pay_amount'] * $v['num'] / $dlySaleItems[$order_item_id]['nums']);
                } else {
                    $platform_amount = 0;
                    $settlement_amount = 0;
                    $actually_amount = 0;
                    $settlement_amount = 0;
                }
                if($settlement_amount == 0){
                    $settlement_amount = $aftersale_items[$k]['saleprice'];
                }
                $data['platform_amount'] += $platform_amount;
                $data['settlement_amount'] += $settlement_amount;
                $data['actually_amount'] += $actually_amount;
                $data['platform_pay_amount'] += $platform_pay_amount;
                $aftersale_items[$k]['platform_amount']  = $platform_amount;
                $aftersale_items[$k]['settlement_amount']  = $settlement_amount;
                $aftersale_items[$k]['actually_amount']  = $actually_amount;
                $aftersale_items[$k]['platform_pay_amount']  = $platform_pay_amount;
            }
            
            //关联的销售物料
            $returnItemInfo = ($returnItems['items'][$order_item_id] ? $returnItems['items'][$order_item_id] : $returnItems['products'][$product_id]);
            $aftersale_items[$k]['item_type'] = $returnItemInfo['item_type']; //物料类型
            $aftersale_items[$k]['sales_material_bn'] = $returnItemInfo['goods_bn']; //销售物料编码
            $aftersale_items[$k]['addon'] = $itemsList[$order_item_id]['addon'];
        }
        
        $data['aftersale_items'] = $aftersale_items;
        
        $afterSaleInfo = $Oorder->db->selectrow("SELECT aftersale_id, aftersale_bn FROM sdb_sales_aftersale WHERE reship_id=". $reship_id);
        if($afterSaleInfo){
            $data['aftersale_id']    = $afterSaleInfo['aftersale_id'];
            $data['aftersale_bn']    = $afterSaleInfo['aftersale_bn'];
            $afterItemObj = app::get('sales')->model('aftersale_items');
            foreach($aftersale_items as $v) {
                if($v['return_type'] != 'return'){
                    continue;
                }
                $itemFilter = [
                    'aftersale_id'=>$afterSaleInfo['aftersale_id'],
                    'obj_item_id' => $v['obj_item_id'],
                    'return_type' => 'return'
                ];
                $afterItems = $afterItemObj->getList('item_id,branch_id', $itemFilter);
                if(count($afterItems) == 1){
                    $afterItems[0]['branch_id'] = $v['branch_id'];
                }
                foreach($afterItems as $key => $item){
                    if($item['branch_id'] == $v['branch_id']){
                        if($v['normal_num'] > 0){
                            $afterItemObj->update(['normal_num'=>$v['normal_num']], ['item_id'=>$item['item_id']]);
                        }
                        if($v['defective_num'] > 0){
                            $afterItemObj->update(['defective_num'=>$v['defective_num']], ['item_id'=>$item['item_id']]);
                        }
                    }
                }
            }
        }

        return $data;
    }

    public function getOrderObj($order_id,$is_archive){
        if ($is_archive) {

            $archive_ordObj = kernel::single('archive_interface_orders');
            
            $orderobjects = $archive_ordObj->getOrder_object(array('order_id'=>$order_id),'obj_id,bn');

        } else {
            
            $orderobjects = app::get('ome')->model('order_objects')->getList('obj_id,bn', array('order_id' => $order_id));
            
        }

        $objs = array();
        foreach($orderobjects as $v){
            $objs[$v['obj_id']] = $v['bn'];
        }

        return $objs;
    }

    public function getSaleAmount($order_id){
        $db = kernel::database();
        $items = $db->select("SELECT sales_material_bn,bn,sales_amount,nums FROM sdb_ome_sales_items AS I LEFT JOIN sdb_ome_sales as S ON I.sale_id=S.sale_id WHERE S.order_id=".$order_id." AND I.product_id>0");

        $data = array();
        foreach ( $items as $item ){
           
            $sales_price = sprintf('%.3f',$item['sales_amount']/$item['nums']);
            $data[$item['sales_material_bn']][$item['bn']] = $sales_price;
        }

        return $data;
    }
}
