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
* 退换货处理类
*/
class ome_return_rchange
{
    /**
     * @var app
     */
    protected $app;
    function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * 计算差价
     *
     * @return []
     * @author
     **/
    public function calDiffAmount($post)
    {
        $mathLib = kernel::single('eccommon_math');

        # 换出数量
        $change_nums    = 0;
        if($post['change']['objects'])
        {
            foreach ($post['change']['objects'] as $key => $obj)
            {
                foreach ($obj['items'] as $key_i => $item)
                {
                    $change_nums    += $item['change_num'];
                }
            }
        }
        
        # 换出金额
        $change_amount    = 0;
        if($post['change']['objects'])
        {
            foreach ($post['change']['objects'] as $key => $obj)
            {
                $obj['price']    = floatval($obj['price']);
                
                $price    = $obj['price'] * $obj['num'];
                
                $change_amount    += $price;
            }
        }
        
        
        # 应退金额
        $tmoney = 0;
        $tnums = 0;
        if (isset($post['return']['goods_bn']) && is_array($post['return']['goods_bn'])) {
            foreach ($post['return']['goods_bn'] as $pbn) {
                $tmpAmount = floatval($post['return']['price'][$pbn]) * floatval($post['return']['num'][$pbn]);
                
                //check
                if(!isset($post['return']['amount'][$pbn])){
                    $post['return']['amount'][$pbn] = 0;
                }else{
                    $post['return']['amount'][$pbn] = floatval($post['return']['amount'][$pbn]);
                }
                
                $tmoney += $post['return']['amount'][$pbn] > 0 ? $post['return']['amount'][$pbn] : $tmpAmount;
                $tnums += $post['return']['num'][$pbn];
            }
        }
        $tmoney = $mathLib->getOperationNumber($tmoney);

        # 换出金额[暂时没有用到]
        if (isset($post['change']['goods_bn']) && is_array($post['change']['goods_bn'])) {
            foreach ($post['change']['goods_bn'] as $pbn ) {
                $change_amount += $post['change']['price'][$pbn] * $post['change']['num'][$pbn];
            }
        }

        $change_amount = $mathLib->getOperationNumber($change_amount);

        # 折旧费
        $bmoney = $mathLib->getOperationNumber($post['bmoney']);
        #补偿费用
        $bcmoney = $mathLib->getOperationNumber($post['bcmoney']);
        #已退费用
        $had_refund = $mathLib->getOperationNumber($post['had_refund']);

        # 补差价
        $diff_money = $post['diff_money'];
        if ($post['diff_order_bn'] && !$diff_money) {
            $orderModel = $this->app->model('orders');
            $diff_money = $orderModel->select()->columns('total_amount')
                            ->where('order_bn=?',$post['diff_order_bn'])
                            ->where('status=?','active')
                            ->where('pay_status=?','1')
                            ->where('ship_status=?','0')
                            ->instance()->fetch_one();
        }
        $diff_money = $mathLib->getOperationNumber($diff_money);

        # 邮费
        $cost_freight_money = $mathLib->getOperationNumber($post['cost_freight_money']);
         # 退邮费
        $refund_shipping_fee = $mathLib->getOperationNumber($post['refund_shipping_fee']);
        # 公式: 合计金额=应退金额+补偿费用＋补差价费用-折旧(其他费用)-已退费用-换出商品金额-买家承担的邮费
        $totalmoney = $tmoney+$bcmoney+$diff_money - $bmoney - $had_refund - $change_amount - $cost_freight_money+$refund_shipping_fee;
        $totalmoney = $mathLib->getOperationNumber($totalmoney);

        $result = array(
            'tmoney' => $tmoney,
            'change_amount' => $change_amount,
            'bmoney' => $bmoney,
            'had_refund' => $had_refund,
            'diff_money' => $diff_money,
            'totalmoney' => $totalmoney,
            'bcmoney'=>$bcmoney,
            'cost_freight_money' => $cost_freight_money,
            'tnums'=>$tnums,
            'change_nums'=>$change_nums,
        );

        return $result;
    }

    /**
     * 保存收货服务中间信息
     * 
     * @param int $reship_id
     * @param string $status
     * @param string $msg
     * @param array $serviceData [京东一件代发]京东服务单信息
     * @return bool
     **/
    public function accept_returned($reship_id, $status, &$msg, $serviceData=null)
    {
        $oOperation_log = $this->app->model('operation_log');
        $Oreship        = $this->app->model('reship');
        $oProduct_pro   = $this->app->model('return_process');

        $oProduct_pro_detail = $oProduct_pro->product_detail($reship_id);
        $reship              = $Oreship->dump(array('reship_id'=>$reship_id),'is_check,return_id,reason');
        if($reship['is_check'] == '3'){
            $msg = '改单据已验收过!';
            return false;
        }

        //增加售后收货前的扩展
        $memo = '';
        foreach(kernel::servicelist('ome.aftersale') as $o){
            if(method_exists($o,'pre_sv_charge')){
                if(!$o->pre_sv_charge($_POST,$memo)){
                     $msg = $memo;
                    return false;
                }
            }
        }
        
        $data['branch_name'] = $oProduct_pro_detail['branch_name'];
        $data['memo'] = $_POST['info']['memo'];
        $data['shipcompany'] = $_POST['info']['shipcompany'];
        $data['shiplogino'] = $_POST['info']['shiplogino'];
        $data['shipmoney'] = $_POST['info']['shipmoney'];
        $data['shipdaofu'] = $_POST['info']['daofu'] == 1 ? 1 : 0;
        $data['shiptime'] = time();
        
        if($status == '4'){
            $addmemo = ',拒绝收货';
            $refuse_memo = unserialize($reship['reason']);
            //$refuse_memo .= '#收货原因#'.$_POST['info']['refuse_memo'];
            $refuse_memo['receive'] = $_POST['info']['refuse_memo'];
            $prodata = array('reship_id'=>$reship_id,'reason'=>serialize($refuse_memo));
            $oProduct_pro->cancel_process($prodata);
        }elseif($status == '3'){
            $prodata = array('reship_id'=>$reship_id,'process_data'=>serialize($data));
            $addmemo = ',收货成功';
            
            //[京东一件代发]京东服务单信息
            if($serviceData['wms_type'] == 'yjdf'){
                $prodata = array_merge($prodata, $serviceData);
                
                //save
                $oProduct_pro->save_return_process($prodata);
                
                //京东服务单部分退货,直接返回
                $sql = "SELECT por_id FROM sdb_ome_return_process WHERE reship_id=". $reship_id ." AND service_status NOT IN('cancel', 'finish')";
                $processInfo = $oProduct_pro->db->selectrow($sql);
                if($processInfo){
                    //log
                    $log_msg = '服务单号：'.$prodata['service_bn'].'部分发货成功';
                    $oOperation_log->write_log('reship@ome', $reship_id, $log_msg);
                    return true;
                }
            }else{
                //save
                $oProduct_pro->save_return_process($prodata);
            }
        }
        
        //update
        $filter = array(
            'is_check'=>$status,
            'outer_lastmodify'=>time(),//收货时间
        );
        $Oreship->update($filter,array('reship_id'=>$reship_id));
        
        if($reship['return_id'])
        {
            $Oproduct = $this->app->model('return_product');
            $recieved = 'false';
            if($status == '3'){
               $recieved = 'true';
            }
            
            $Oproduct->update(array('process_data'=>serialize($data),'recieved'=>$recieved),array('return_id'=>$reship['return_id']));
        }
        
        $Oreship_items = $this->app->model('reship_items');
        $oBranch = $this->app->model('branch');
        $reship_items = $Oreship_items->getList('branch_id',array('reship_id'=>$reship_id,'return_type'=>'return'));
        $branch_name = array();
        foreach($reship_items as $k=>$v){
            $branch_name[] = $oBranch->Get_name($v['branch_id']);
        }
        $add_name = array_unique($branch_name);
        $memo='仓库:'.implode(',', $add_name).$addmemo;
        $oOperation_log = $this->app->model('operation_log');
        if($reship['return_id']){
            $oOperation_log->write_log('return@ome',$reship['return_id'],$memo);
        }
        $oOperation_log->write_log('reship@ome',$reship_id,$memo);

        if($oProduct_pro_detail['return_id']){
           //售后申请状态更新
            foreach(kernel::servicelist('service.aftersale') as $object=>$instance){
                if(method_exists($instance,'update_status')){
                    $instance->update_status($oProduct_pro_detail['return_id']);
                }
            }
        }
        
        //增加售后收货前的扩展
        foreach(kernel::servicelist('ome.aftersale') as $o){
            if(method_exists($o,'after_sv_charge')){
                $o->after_sv_charge($_POST);
            }
        }
        
        return true;
    }

    /**
     * @description 验证补差价订单
     * @access public
     * @param void
     * @return void
     */
    public function diffOrderValidate($post,&$errormsg)
    {
        if (!$post['order_id']) {
            $errormsg = $this->app->_('请先选择补差价订单!');
            return false;
        }

        if (!$post['return_order_id']) {
            $errormsg = $this->app->_('请先选择退换货订单!');
            return false;
        }

        $orderModel = $this->app->model('orders');
        $order = $orderModel->getList('*',array('order_id'=>$post['order_id']),0,1);
        $order = $order[0];
        if (!$order) {
            $errormsg = $this->app->_("订单号【{$post['order_id']}】不存在!");
            return false;
        }

        $reshipModel = $this->app->model('reship');
        $reship = $reshipModel->getList('*',array('diff_order_bn'=>$order['order_bn'],'is_check|noequal'=>'5'),0,1);
        if ($reship) {
            $errormsg = $this->app->_("补差价订单已经被其他售后换货单据使用!");
            return false;
        }

        $orderItemModel = $this->app->model('order_items');
        $order['items'] = $orderItemModel->getList('*',array('order_id'=>$order['order_id']));

        $memberModel = $this->app->model('members');
        $member = $memberModel->getList('*',array('member_id'=>$order['member_id']));
        $order['member'] = $member[0];

        return $order;
    }
    
    /**
     * 格式化换货数据
     * @param  
     * @return  
     * @access  public
     * @author sunjing@shopex.cn
     */
    function format_rchange_data($post)
    {
        $fudaiLib = kernel::single('material_fukubukuro_dispatch');
        
        //[格式化]获取销售物料关联的基础物料
        $salesBasicMaterialObj    = app::get('material')->model('sales_basic_material');
        $basicMaterialObj         = app::get('material')->model('basic_material');
        if(empty($post['ship_name']) && $post['order_id']) {
            $order = app::get('ome')->model('orders')->db_dump(['order_id'=>$post['order_id']], 'ship_name,ship_mobile,ship_tel,ship_area,ship_addr,ship_zip,ship_email,shop_id');
            if($order) {
                $post = array_merge($post, $order);
            }
        }
        
        //shop_id
        $shopInfo = [];
        if($post['shop_id']){
            $shopInfo = app::get('ome')->model('shop')->db_dump(array('shop_id'=>$post['shop_id']), 'shop_id,shop_bn,node_id,node_type');
        }
        
        //重组销售物料数据
        if($post['change']){
            $sales_material_list    = array();
            foreach ($post['change'] as $obj_type => $objects)
            {
                //objects
                foreach ($objects as $field_bn => $items)
                {
                    if(in_array($field_bn, array('name', 'sale_store', 'num', 'price', 'product_id', 'bn', 'item_id','changebranch_id'))){
                        foreach ($items as $sales_material_bn => $val)
                        {
                            //bn
                            if($field_bn == 'bn'){
                                $sales_material_bn    = $val;
                            }
                            
                            $sales_material_list[$sales_material_bn][$field_bn]    = $val;
                            
                            //obj_type
                            if($field_bn == 'bn'){
                                $sales_material_list[$sales_material_bn]['obj_type']    = $obj_type;

                                $sales_material_list[$sales_material_bn]['item_type']   = $obj_type;
                            }
                        }
                    }
                }
            }
            
            //组织换出明细
            $material_list    = array();
            if($sales_material_list){
                foreach ($sales_material_list as $key => $objects)
                {
                    $objects['num']    = intval($objects['num']);
                    
                    //changebranch_id
                    if (!$post['changebranch_id']){
                        $post['changebranch_id'] = $objects['changebranch_id'];
                    }
                    
                    if($objects['obj_type'] == 'lkb'){
                        $basicMInfos = [];
                        
                        //福袋组合
                        $luckybagParams = [];
                        $luckybagParams['sm_id'] = $objects['product_id'];
                        $luckybagParams['sale_material_nums'] = $objects['num']; //换出数量
                        $luckybagParams['shop_bn'] = $shopInfo['shop_bn'];
                        
                        $fdResult = $fudaiLib->process($luckybagParams);
                        if($fdResult['rsp'] == 'succ'){
                            $basicMInfos = $fdResult['data'];
                        }else{
                            //标记福袋分配错误信息
                            $luckybag_error = $fdResult['error_msg'];
                        }
                        
                        //items
                        foreach($basicMInfos as $var_bm)
                        {
                            //福袋组合ID
                            $luckybag_id = ($var_bm['combine_id'] ? $var_bm['combine_id'] : 0);
                            
                            $tmp_item = array(
                                'bm_id' => $var_bm['bm_id'],
                                'material_name' => $var_bm['material_name'],
                                'material_bn' => $var_bm['material_bn'],
                                'type' => 1, //物料属性:1(成品),2(半成品),3(普通),4(礼盒),5(虚拟)
                                'number' => $var_bm['number'],
                                'change_num' => $var_bm['number'],
                                'changebranch_id'=>$objects['changebranch_id'],
                                'luckybag_id'   => $luckybag_id, //福袋组合ID
                            );
                            
                            $objects['items'][] = $tmp_item;
                        }
                        
                    }elseif($objects["obj_type"] == "pko"){ //多选一
                        $salesMLib = kernel::single('material_sales_material');
                        $basicMInfos = $salesMLib->get_order_pickone_bminfo($objects['product_id'],$objects['num'],$post["shop_id"]);
                        foreach($basicMInfos as $var_bm){
                            $tmp_item = array(
                                "bm_id" => $var_bm["bm_id"],
                                "material_name" => $var_bm["material_name"],
                                "material_bn" => $var_bm["material_bn"],
                                "type" => $var_bm["type"],
                                "number" => $var_bm["number"],
                                "change_num" => $var_bm["number"],
                                'changebranch_id'=>$objects['changebranch_id'],
                            );
                            $objects['items'][] = $tmp_item;
                        }
                    }else{
                        $promoItems        = $salesBasicMaterialObj->getList('*',array('sm_id'=>$objects['product_id']),0,-1);
                        if($promoItems)
                        {
                            foreach($promoItems as $key_i => $promoItem)
                            {
                                $product_id    = $promoItem['bm_id'];
                                $tmp_item      = $basicMaterialObj->dump(array('bm_id'=>$product_id), 'bm_id, material_name, material_bn, type');
                                
                                //基础物料绑定数量
                                $tmp_item['number']    = $promoItem['number'];
                                
                                //[基础物料]需换货数量
                                $tmp_item['change_num']    = intval($promoItem['number'] * $objects['num']);#绑定基础物料的数量 * 输入的换货数量
                                $tmp_item['changebranch_id'] =  $objects['changebranch_id'];
                                $objects['items'][]    = $tmp_item;
                            }
                        }
                    }
                    $material_list[]    = $objects;
                }
            }
        }
        
        $post['change']    = array();
        $post['change']['objects']    = $material_list;
        
        return $post;
    }

    
    /**
     * 获取换货明细
     * @access  public
     * @author sunjing@shopex.cn
     */
    function getChangelist($reship_id,$branch_id)
    {
        $salesBasicMaterialObj   = app::get('material')->model('sales_basic_material');
        $libBranchProduct        = kernel::single('ome_branch_product');
        $salesMLib = kernel::single('material_sales_material');
        $db        = kernel::database();
        
        //branch_id
        $branch_id = intval($branch_id);
        
        // 退货明细
        $returnItems = $db->select("SELECT reship_id, bn, order_item_id FROM sdb_ome_reship_items  WHERE reship_id=".$reship_id." AND return_type='return'");
        foreach ($returnItems as $rik => $riv) {
            $orderItem = $db->selectrow("SELECT item_id, obj_id FROM sdb_ome_order_items WHERE item_id=".intval($riv['order_item_id']));
            if ($orderItem) {
                $orderObject = $db->selectrow("SELECT obj_id, `oid` FROM sdb_ome_order_objects WHERE obj_id=".$orderItem['obj_id']);

                $returnItems[$rik]['oid'] = $orderObject['oid'];
            }
        }
        
        // 二维数组中按货号排序
        uasort($returnItems, function($a, $b) {
            if ($a['bn'] == $b['bn']) {
                return 0;
            }

            return $a['bn'] > $b['bn'];
        });
        
        $changelist = array();
        
        #销售物料层
        $obj_list = $db->select("SELECT * FROM sdb_ome_reship_objects WHERE reship_id=".$reship_id."");

        // 二维数组中按货号排序
        uasort($obj_list, function($a, $b) {
            if ($a['bn'] == $b['bn']) {
                return 0;
            }

            return $a['bn'] > $b['bn'];
        });
        
        $branchlib = kernel::single('ome_branch');
        foreach ($obj_list as $kobj => &$obj )
        {
            $obj['type']         = 'change';
            $obj['item_type']    = $obj['obj_type'];
            $obj_id              = $obj['obj_id'];
            $obj['product_id']   = $obj['product_id'];
            $obj['name']         = $obj['product_name'];
            $obj['item_id']      = $obj_id;
            $obj['num']          = intval($obj['num']);
            $obj['oid']          = $returnItems[$kobj] ? $returnItems[$kobj]['oid'] : 0;
            
            //基础物料层
            $product_list = $db->select("SELECT *  FROM sdb_ome_reship_items WHERE reship_id=".$reship_id." AND obj_id=".$obj_id." AND return_type='change'");
            $items = array();
            $sale_store_list    = array();
            
            //判断是否是门店仓
            $store_id = kernel::single('ome_branch')->isStoreBranch($branch_id);
            
            foreach ($product_list as $products ){
                $product_id = $products['product_id'];
                
                //仓库库存
                if ($store_id){//门店仓
                    $arr_stock = kernel::single('o2o_return')->o2o_store_stock($branch_id,$product_id);
                    $products['sale_store'] = $arr_stock["store"]; //值可能会包括 "-" "x" 或 真实的库存数
                }else{//电商仓
                    $sale_store = $libBranchProduct->getAvailableStore($branch_id,array($product_id));
                    $products['sale_store'] = $sale_store[$product_id];
                }
                $products['store'] = $products['sale_store'];
               
                #格式化基础物料
                $products['sm_id']    = $obj['product_id'];#销售物料sm_id
                $products['bm_id']            = $products['product_id'];
                $products['material_name']    = $products['product_name'];
                $products['material_bn']      = $products['bn'];
                $products['type_name']        = '';
                $branch_detail = $branchlib->getBranchInfo($branch_id,'name');
                $obj['branch_name'] = $branch_detail['name'];
                $obj['branch_id'] = $branch_id;
                $products['branch_id']    = $branch_id;
                $products['branch_name']    = $branch_detail['name'];
                
                //基础物料绑定数量
                if($obj['obj_type'] == "lkb"){ //福袋
                    $products['number'] = intval($products['num']);
                }elseif($obj['obj_type']== "pko"){ //多选一
                    $products['number'] = intval($products['num']);
                }else{
                    $temp_item    = $salesBasicMaterialObj->dump(array('sm_id'=>$products['sm_id'], 'bm_id'=>$products['bm_id']), 'number');
                    
                    //[兼容]防止基础物料已经不存在
                    //@todo：销售物料编辑删除了个别基础物料;
                    if(empty($temp_item)){
                        $products['number'] = intval($products['num']);
                    }else{
                        $products['number'] = intval($temp_item['number']);
                    }
                }
                
                //物料类型
                $products['item_type']    = $obj['item_type'];
                if($obj['obj_type']== "pko"){
                    $products['change_num'] = $products['number'];#绑定基础物料的数量 * 输入的换货数量
                }elseif($obj['obj_type'] == "lkb"){
                    $products['change_num'] = $products['number'];
                    
                    //计算销售物料最小库存
                    $sale_store_list[] = $products['sale_store'];
                }else{
                    //[基础物料]需换货数量
                    $products['change_num'] = intval($products['number'] * $obj['num']);#绑定基础物料的数量 * 输入的换货数量
                    #计算销售物料最小库存
                    $sale_store_list[] = $products['sale_store'];
                }

                $items[] = $products;
            }
            
            $obj['items'] = $items;

            if($obj['obj_type']== "pko"){
                $obj['sale_store'] = $salesMLib->get_pickone_branch_store($obj['product_id'],$branch_id);
            }else{
                #最小库存数量
                $obj['sale_store'] = min($sale_store_list,0);
            }
            
            #销售物料类型
            switch($obj['obj_type']){
                case "pkg":
                    $obj['item_type_name'] = '组合'; break;
                case "gift":
                    $obj['item_type_name'] = '赠品'; break;
                case "lkb":
                    $obj['item_type_name'] = '福袋'; break;
                case "pko":
                    $obj['item_type_name'] = '多选一'; break;
                default:
                    $obj['item_type_name'] = '普通'; break;
            }
            $changelist[] = $obj;
        }
       
        return $changelist;
    }

    /**
     * 初始化计算退货单上最后合计金额totalmoney
     * 场景：售后单接受申请时自动创建退换货单;
     * 
     * @param $return_id 售后申请单id
     * @return bool
     */
    function update_totalmoney($return_id)
    {
        $reshipObj = app::get('ome')->model('reship');
        $reshipItemObj = app::get('ome')->model('reship_items');
        $reProductObj = app::get('ome')->model('return_product');
        $orderObj = app::get('ome')->model('orders');
        $mathLib = kernel::single('eccommon_math');
        
        //退换货单
        $reshipdata   = $reshipObj->dump(array('return_id'=>$return_id), 'reship_id,order_id');
        $reship_id    = $reshipdata['reship_id'];
        $order_id = $reshipdata['order_id'];
        if(empty($reshipdata))
        {
            return false;
        }
        
        //退货申请明细
        $items    = $reshipItemObj->getList('*', array('reship_id'=>$reship_id, 'return_type'=>'return'));
        if(empty($items))
        {
            return false;
        }
        
        $tnums    = 0;
        $tmoney   = 0;
        foreach ($items as $key => $val)
        {
            $tmoney    += ($val['price'] * $val['num']);//退款金额
            $tnums     += $val['num'];
        }
        $tmoney = $mathLib->getOperationNumber($tmoney);
        
        //换货明细
        $change_amount  = 0;
        $change_nums    = 0;
        $changeItems    = $reshipItemObj->getList('*', array('reship_id'=>$reship_id, 'return_type'=>'change'));
        if($changeItems)
        {
            foreach ($changeItems as $key => $val)
            {
                $change_amount    += ($val['price'] * $val['num']);//换出金额
                $change_nums    += $val['num'];
            }
        }
        $change_amount    = $mathLib->getOperationNumber($change_amount);
        
        $cost_freight_money = 0;//邮费
        $bcmoney    = 0;
        $diff_money = 0;
        $bmoney     = 0;//折旧(其他费用)
        
        //全额退款订单
        $orderInfo = $orderObj->dump(array('order_id'=>$order_id), 'pay_status');
        if($orderInfo['pay_status'] == '5'){
            $tmoney = ($tmoney > $orderInfo['payed'] ? $orderInfo['payed'] : $tmoney); //理论上全额退款订单退款金额为0
        }
        
        #最后合计金额(初始化计算退换金额时,不包含配送费用)
        $totalmoney = $tmoney-$change_amount;
        
        //更新退换货单信息
        $updateData = array(
                'tmoney' => $tmoney,
                'change_amount' => $change_amount,
                'bmoney' => $bmoney,
                'diff_money' => $diff_money,
                'totalmoney' => $totalmoney,
                'bcmoney'=>$bcmoney,
                'cost_freight_money' => $cost_freight_money,
        );
        
        $reshipObj->update($updateData, array('reship_id'=>$reship_id));
        
        //更新售后申请单上的金额
        $reProductObj->update(array('money'=>$totalmoney), array('return_id'=>$return_id));
        
        return true;
    }
    
    //更新补差价
    public function update_diff_amount($arr_cal_diff_amount,$reship_bn){
        $Oreship = $this->app->model('reship');
        $money = kernel::single('ome_return_rchange')->calDiffAmount($arr_cal_diff_amount);
        $update_arr = array(
            "totalmoney" => $money['totalmoney'],
            "tmoney" => $money['tmoney'],
            "bmoney" => $money['bmoney'],
            "had_refund" => $money['had_refund'],
            "bcmoney" => $money['bcmoney'],
            "diff_money" => $money['diff_money'],
            "change_amount" => $money['change_amount'],
            "diff_order_bn" => $arr_cal_diff_amount['diff_order_bn'] ? $arr_cal_diff_amount['diff_order_bn'] : '',
            "cost_freight_money" => $money['cost_freight_money'],
        );
        $Oreship->update($update_arr,array('reship_bn'=>$reship_bn));
    }
    
    public function dealExchangeEncrypt($return_id) {
        if(empty($return_id)) {
            return ['rs'=>false];
        }
        $exchange = app::get('ome')->model('return_exchange_receiver')->db_dump(['return_id'=>$return_id], '*');
        if(empty($exchange)) {
            return ['rs'=>false];
        }
        if(!$exchange['buyer_province']) {
            return ['rs'=>false];
        }
        $subfix = '';
        $encrypt_source_data_raw = $exchange['encrypt_source_data'] ? $exchange['encrypt_source_data'] : '';

        $returnMdl = app::get('ome')->model('return_product');
        $return = $returnMdl->db_dump(array('return_id'=>$return_id),'shop_type,order_id');

        $encrypt_source_data = array();
        if ($encrypt_source_data_raw) {
            $encrypt_source_data = json_decode($encrypt_source_data_raw, 1);
            if (!is_array($encrypt_source_data)) {
                $encrypt_source_data = array();
            }
        }

        if ($return['shop_type'] == 'wxshipin') {
            // 换货未带密文时继承原销售单（与补寄读 order_receiver 一致），避免 md5 兜底导致推 WMS 手机号超长
            if (empty($encrypt_source_data['ewaybill_order_code'])
                && empty($encrypt_source_data['oaid'])
                && empty($encrypt_source_data['receiver_mobile_index_origin'])
                && empty($encrypt_source_data['receiver_name_index_origin'])
                && empty($encrypt_source_data['receiver_address_index_origin'])
                && $return['order_id']
            ) {
                $original = app::get('ome')->model('order_receiver')->db_dump(array('order_id' => $return['order_id']), 'encrypt_source_data');
                if ($original['encrypt_source_data']) {
                    $encrypt_source_data_raw = $original['encrypt_source_data'];
                    $encrypt_source_data = json_decode($encrypt_source_data_raw, 1);
                }
            }
            $oaid = $encrypt_source_data['ewaybill_order_code'] ? $encrypt_source_data['ewaybill_order_code'] : $encrypt_source_data['oaid'];
            if ($oaid) {
                if ($encrypt_source_data['ewaybill_order_code'] && empty($encrypt_source_data['oaid'])) {
                    $encrypt_source_data['oaid'] = $encrypt_source_data['ewaybill_order_code'];
                    $encrypt_source_data_raw = json_encode($encrypt_source_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
                $subfix = '>>'.$oaid.kernel::single('ome_security_hash')->get_code();
            }
        } elseif ($encrypt_source_data_raw) {
            if($encrypt_source_data['oaid']) {
                $subfix = '>>'.$encrypt_source_data['oaid'].kernel::single('ome_security_hash')->get_code();
            } else {
                if($return['shop_type'] =='kuaishou'){

                    $subfix = kernel::single('ome_security_hash')->get_code();

                    if ($receiver_name_index = $encrypt_source_data['receiver_name_index']) {
                        $exchange['buyer_name'] .= '>>' . $receiver_name_index;
                  
                    }
                    if ($receiver_name_index = $encrypt_source_data['receiver_mobile_index']) {
                        $exchange['buyer_phone'] .= '>>' . $receiver_name_index;
                  
                    }
                    if ($receiver_name_index = $encrypt_source_data['receiver_address_index']) {
                        $exchange['buyer_address'] .= '>>' . $receiver_name_index;
                  
                    }

                }else{
                    $subfix = '>>'.md5($encrypt_source_data_raw).kernel::single('ome_security_hash')->get_code();
                }
            }
        }
        $ship_area = $exchange['buyer_province'].'/'.$exchange['buyer_city'].'/'.$exchange['buyer_district'].'/'.$exchange['buyer_town'];
        kernel::single('ome_func')->region_validate($ship_area);
        $consignee = [
            'name'      => strpos($exchange['buyer_name'], '*') !== false ? $exchange['buyer_name'].$subfix : $exchange['buyer_name'],
            'addr'      => strpos($exchange['buyer_address'], '*') !== false ? $exchange['buyer_address'].$subfix : $exchange['buyer_address'],
            'zip'       => '',
            'telephone' => '',
            'mobile'    => strpos($exchange['buyer_phone'], '*') !== false ? $exchange['buyer_phone'].$subfix : $exchange['buyer_phone'],
            'email'     => '',
            'area'      => $ship_area,
        ];
        if($return['shop_type'] =='wxshipin'){
            $consignee['name'] = $exchange['buyer_name'];
        }

        return ['rs'=>true, 'data'=>['consignee'=>$consignee, 'encrypt_source_data'=>$encrypt_source_data_raw]];
    }
}
