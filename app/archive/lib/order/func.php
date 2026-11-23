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

class archive_order_func{

    /**
     * 获取申请过退款或已退款的订单号
     * @access public
     * @param array or int $delivery_id 发货单号，如：array('1','2','3')或1
     * @param boolean $return_type 返回数据结果方式，true:列表,false:总数
     * @return 已申请退款或已退款的订单号及状态
     */
    public function get_refund_orders($delivery_id,$return_type=true){

        static $refund_orders = array();
        $deliveryObj = app::get('ome')->model('delivery');
        $refundsObj = app::get('ome')->model('refunds');
        $ordersObj = app::get('ome')->model('orders');
        $refund_applyObj = app::get('ome')->model('refund_apply');
        $deliveryorderObj = app::get('ome')->model('delivery_order');
        if (is_array($delivery_id)){
            foreach ($delivery_id as $delivery_id){
                self::get_refund_orders($delivery_id);
            }
        }else{
            $delivery_detail = $deliveryObj->dump($delivery_id, 'is_bind,delivery_id,parent_id,logi_no');
            if ($delivery_detail['parent_id'] > 0){
                $parent_delivery_detail = $deliveryObj->dump(array('delivery_id'=>$delivery_detail['parent_id']), 'logi_no');
                $delivery_detail['logi_no'] = $parent_delivery_detail['logi_no'];
            }
            if ($delivery_detail['is_bind'] == 'true'){
                $delivery_ids = $deliveryObj->getList('delivery_id', array('parent_id'=>$delivery_detail['delivery_id']), 0, -1);
                if ($delivery_ids){
                    foreach ($delivery_ids as $delivery){
                        self::get_refund_orders($delivery['delivery_id']);
                    }
                }
            }else{
                $delivery_orders = $deliveryorderObj->dump(array('delivery_id'=>$delivery_id), 'order_id');
                $order_id = $delivery_orders['order_id'];
                $refund_applys = $refund_applyObj->dump(array('order_id'=>$order_id,'status|noequal'=>'3','disabled'=>'false'), 'apply_id');
                if (!empty($refund_applys)){
                    $order_detail = $ordersObj->dump(array('order_id'=>$order_id), 'order_bn,pay_status');
                    $refund_orders[] = array('order_bn'=>$order_detail['order_bn'],'logi_no'=>$delivery_detail['logi_no'],'status'=>'申请退款或已退款','pay_status'=>$order_detail['pay_status']);
                }
            }
        }
        if (!empty($refund_orders)){
            if ($return_type == true){
                return $refund_orders;
            }else{
                return count($refund_orders);
            }
        }else{
            return null;
        }
    }


    /**
     * 更新订单优惠方案
     * @param Number $order_id 订单ID
     * @param String $shop_id 店铺ID
     * @param Array $pmt_detail 待更新的优惠方案
     * @param Array $addon 附加参数
     * @param Array $old_pmt 返回更新前的优惠方案
     * @return bool true or false
     */
    public function update_pmt($order_id,$shop_id,$pmt_detail=array(),$addon=array(),&$old_pmt){
        if (empty($pmt_detail) || !is_array($pmt_detail) || empty($order_id)) return false;

        $pmtObj = app::get('ome')->model('order_pmt');
        $old_pmt_detail = $pmtObj->getList('*',array('order_id'=>$order_id));
        $old_pmt = $old_pmt_detail;

        foreach ($pmt_detail as $k=>$v){
            if ((trim($v['pmt_amount']) == '' || trim($v['pmt_amount']) == 0) && trim($v['pmt_describe']) == ''){
                unset($pmt_detail[$k]);#将pmt_amount是0并且pmt_describe为空的去掉
            }
        }

        #比对
        $update_flag = false;
        if ($old_pmt_detail){
            if (count($old_pmt_detail) != count($pmt_detail)){
                $update_flag = true;
            }
        }else{
            if(count($pmt_detail) == 0){
                $update_flag = false;
            }else{
                $update_flag = true;
            }
        }

        if ($update_flag !== true){
            return false;
        }

        #删除以前的优惠金额
        $pmtObj->delete(array('order_id'=>$order_id));

        foreach ($pmt_detail as $k=>$v){

            //TODO:兼容拍拍优惠描述
            $pmt_describe = '';
            if (strstr($v['pmt_describe'],'@')){
                $pmt_describe = explode('@',$v['pmt_describe']);
                $pmt_describe = $pmt_describe[1];
            }else{
                $pmt_describe = $v['pmt_describe'];
            }

            $pmt_sdf = array(
                'order_id' => $order_id,
                'pmt_amount' => abs($v['pmt_amount']),
                'pmt_describe' => $pmt_describe,
                //'createtime' => $addon['createtime'],//增加优惠规则的创建时间（订单的创建时间）
                //'shop_id' => $shop_id,//增加店铺id
            );
            if($pmtObj->save($pmt_sdf)){
                $update_flag = true;
            }
        }
        return $update_flag;
    }

    /**
     * 订单结构数据扩展与修改
     * @access public
     * @param array $order_sdf 订单sdf数据结构
     * @return 扩展或修改后的订单sdf数据结构
     */
    static function order_sdf_extend(&$order_sdf){

       if ($order_sdf){
            foreach ($order_sdf as $obj_type=>$objects){
                if (is_array($objects)){
                    foreach ($objects as $obj_id=>$items){
                        if ($items['order_items']){
                            foreach ($items['order_items'] as $item_id=>$item_val){
                                //显示items下的addon属性值
                                $addon = array();
                                $product_attr = unserialize($item_val['addon']);
                                if (!empty($product_attr['product_attr'])){
	                                foreach ($product_attr['product_attr'] as $attr){
                                        if($attr['original_str']) {
                                            $addon[] = $attr['original_str'];
                                            break;
                                        }else{
                                            $addon[] = $attr['label'].":".$attr['value'];
                                        }
	                                }
                                }
                                if ($addon){
                                    $addon = implode(';',$addon);
                                }
                                $order_sdf[$obj_type][$obj_id]['order_items'][$item_id]['addon'] = $addon ? $addon : '-';
                            }
                        }
                    }
                }
            }
        }
    }



    /**
    * 更新订单收货人信息
    *$new_consignee = $old_consignee = array(
    *    'ship_name' => '收货人姓名',
    *    'ship_area' => '收货人所在地区,ecos标准地区格式',
    *    'ship_province' => '收货人所在省',
    *    'ship_city' => '收货人所在市',
    *    'ship_district' => '收货人所在县(区)',
    *    'ship_addr' => '收货人所在详细地址',
    *    'ship_zip' => '收货人所在地区邮编',
    *    'ship_tel' => '收货人固定电话',
    *    'ship_mobile' => '收货人手机号码',
    *    'ship_email' => '收货人邮箱地址',
    *    'ship_time' => '收货人要求到货时间'
    *);
    * @access public
    * @param Number $order_id 订单ID
    * @param Array $new_consignee 新的收货人信息
    * @param Array $old_consignee 原始的收货人信息(订单表的收货人结构)
    * @param bool $is_update 默认true更新,false不更新
    * @return bool 不更新的时候,返回需要更新的收货人信息数组
    */
    public function update_consignee($order_id,$new_consignee,&$old_consignee=array(),$is_update=true){
        if (empty($new_consignee)) return false;

        $oFunc = kernel::single('ome_func');
        #收货人信息结构兼容
        if (!isset($new_consignee['ship_name'])){
            $new_consignee = array(
                'ship_name' => $new_consignee['name'],
                'ship_province' => $new_consignee['area_state'],
                'ship_city' => $new_consignee['area_city'],
                'ship_district' => $new_consignee['area_district'],
                'ship_addr' => $new_consignee['addr'],
                'ship_zip' => $new_consignee['zip'],
                'ship_tel' => $new_consignee['telephone'],
                'ship_mobile' => $new_consignee['mobile'],
                'ship_email' => $new_consignee['email'],
                'ship_time' => $new_consignee['r_time']
            );
            $area = $new_consignee['ship_province'].'/'.$new_consignee['ship_city'].'/'.$new_consignee['ship_district'];
            $oFunc->region_validate($area);
            $new_consignee['ship_area'] = $area;
        }

        #比较的字段
        $compre_field = array('ship_name','ship_area','ship_addr','ship_zip','ship_tel','ship_email','ship_time','ship_mobile');

        if (empty($old_consignee)){
            $oOrder = app::get('ome')->model('orders');
            $filter = array('order_id'=>$order_id);
            $orders = $oOrder->getRow($filter, implode(',',$compre_field));
            if ($orders){
                foreach ($orders as $field=>$val){
                    $old_consignee[$field] = $val;
                }
            }
        }

        #判断原始收货人地区是否为本地标准的地区格式
        if (isset($old_consignee['ship_area']) && !$oFunc->is_correct_region($old_consignee['ship_area'])){
            $area = $old_consignee['ship_area'];
            $oFunc->region_validate($area);
            $old_consignee['ship_area'] = $area;
        }

        $update_sdf = array();
        foreach($compre_field as $field){
            $compre_value = trim($new_consignee[$field]);
            if (empty($compre_value)) continue;
            if ($new_consignee[$field] != $old_consignee[$field]){
                $update_sdf[$field] = $new_consignee[$field];
            }
        }

        if ($update_sdf){
            if ($is_update == true){
                $oOrder = app::get('ome')->model('orders');
                return $oOrder->update($update_sdf,array('order_id'=>$order_id));
            }else{
                return $update_sdf;
            }
        }
        return false;
    }

    /**
    * 更新订单发货人信息
    *$new_consigner = $old_consigner = array(
    *    'consigner_name' => '发货人姓名',
    *    'consigner_area' => '发货人所在地区,ecos标准地区格式',
    *    'consigner_province' => '发货人所在省',
    *    'consigner_city' => '发货人所在市',
    *    'consigner_district' => '发货人所在县(区)',
    *    'consigner_addr' => '发货人所在详细地址',
    *    'consigner_zip' => '发货人所在地区邮编',
    *    'consigner_tel' => '发货人固定电话',
    *    'consigner_mobile' => '发货人手机号码',
    *    'consigner_email' => '发货人邮箱地址',
    *);
    * @access public
    * @param Number $order_id 订单ID
    * @param Array $new_consigner 新的发货人信息
    * @param Array $old_consigner 原始的发货人信息(订单表的发货人结构)
    * @param bool $is_update 默认true更新,false不更新
    * @return bool 不更新的时候,返回需要更新的发货人信息数组
    */
    public function update_consigner($order_id,$new_consigner,&$old_consigner=array(),$is_update=true){

        if (empty($new_consigner)) return false;

        $oFunc = kernel::single('ome_func');
        #发货人信息结构兼容
        if (!isset($new_consigner['consigner_name'])){
            $new_consigner = array(
                'consigner_name' => $new_consigner['name'],
                'consigner_province' => $new_consigner['area_state'],
                'consigner_city' => $new_consigner['area_city'],
                'consigner_district' => $new_consigner['area_district'],
                'consigner_addr' => $new_consigner['addr'],
                'consigner_zip' => $new_consigner['zip'],
                'consigner_tel' => $new_consigner['telephone'],
                'consigner_mobile' => $new_consigner['mobile'],
                'consigner_email' => $new_consigner['email'],
            );
            $area = $new_consigner['consigner_province'].'/'.$new_consigner['consigner_city'].'/'.$new_consigner['consigner_district'];
            $oFunc->region_validate($area);
            $new_consigner['consigner_area'] = $area;
        }

        #判断原始收货人地区是否为本地标准的地区格式
        if (isset($old_consigner['consigner_area']) && !$oFunc->is_correct_region($old_consigner['consigner_area'])){
            $area = $old_consigner['consigner_area'];
            $oFunc->region_validate($area);
            $old_consigner['consigner_area'] = $area;
        }

        #比较的字段
        $compre_field = array('consigner_name','consigner_area','consigner_addr','consigner_zip','consigner_tel','consigner_email','consigner_mobile');

        if (empty($old_consigner)){
            $oOrder = app::get('ome')->model('orders');
            $filter = array('order_id'=>$order_id);
            $orders = $oOrder->getRow($filter, implode(',',$compre_field));
            if ($orders){
                foreach ($orders as $field=>$val){
                    $old_consigner[$field] = $val;
                }
            }
        }

        $update_sdf = array();
        foreach($compre_field as $field){
            $compre_value = trim($new_consigner[$field]);
            if (empty($compre_value)) continue;
            if ($new_consigner[$field] != $old_consigner[$field]){
                $update_sdf[$field] = $new_consigner[$field];
            }
        }

        if ($update_sdf){
            if ($is_update == true){
                $oOrder = app::get('ome')->model('orders');
                return $oOrder->update($update_sdf,array('order_id'=>$order_id));
            }else{
                return $update_sdf;
            }
        }
        return false;
    }

    /**
    * 更新订单代销人信息
    * @access public
    * @param Number $order_id 订单ID;$type,新增或编辑模式
    * @param Array $new_sellagent 新的代销人信息
    * @return bool true有变化,false无变化
    */
    public function update_sellagent($order_id,$new_sellagent=array(),$type=false){
        if (empty($new_sellagent) || empty($order_id)) return false;

        $oSellAgent = app::get('ome')->model('order_selling_agent');
        $oFunc = kernel::single('ome_func');

        $new_sellagent['member_info']['tel'] = $new_sellagent['member_info']['telephone'];
        $area = $new_sellagent['member_info']['area_state'].'/'.$new_sellagent['member_info']['area_city'].'/'.$new_sellagent['member_info']['area_district'];
        $oFunc->region_validate($area);
        $new_sellagent['member_info']['area'] = $area;
        $compre_field = array(
            'member_info' => array('uname','level','name','birthday','sex','area','addr','zip','tel','mobile','email'),
            'website' => array('name','domain','logo')
        );

        $old_sellagent = $oSellAgent->dump(array('order_id'=>$order_id));
        #只处理新增的数据。原来已存在的老订单不管
        if($type == 'create'){
            #新增分销王订单前端发货人地区
            if($new_sellagent['seller']){
                $seller_area = $new_sellagent['seller']['seller_state'].'-'.$new_sellagent['seller']['seller_city'].'-'.$new_sellagent['seller']['seller_district'];
                $new_sellagent['seller']['seller_area'] =  $seller_area;
                $compre_field['seller'] = array('seller_name','seller_mobile','seller_phone','seller_zip','seller_area','seller_address');
            }
        }

        $update_sdf = array();

        foreach($compre_field as $path=>$field){
            if (is_array($field)){
                foreach($field as $fname){
                    $compre_value = trim($new_sellagent[$path][$fname]);
                    if (empty($compre_value)) continue;
                    if ($new_sellagent[$path][$fname] != $old_sellagent[$path][$fname]){
                        $update_sdf[$path][$fname] = $new_sellagent[$path][$fname];
                    }
                }
            }else{
                $compre_value = trim($new_sellagent[$field]);
                if (empty($compre_value)) continue;
                if ($new_sellagent[$field] != $old_sellagent[$field]){
                    $update_sdf[$field] = $new_sellagent[$field];
                }
            }
        }
        if ($update_sdf){
            $update_sdf['selling_agent_id'] = $old_sellagent['selling_agent_id'];
            $update_sdf['order_id'] = $order_id;
            #只处理新增的订单,原来已存在的老订单不管
            if($type == 'create'){
                #分销王订单前端发货人与发货地址存在
                if($update_sdf['seller']['seller_address'] && $update_sdf['seller']['seller_name']){
                    $update_sdf['print_status'] = '1';#打印前端代销人发货信息
                }
            }
            return $oSellAgent->save($update_sdf);
        }

        return false;
    }



    /**
     * 订单旗标
     * @param string $mark_value 获取相应旗标的图标，为空时获取所有旗标
     * @return 返回旗标
     */
    static function order_mark_type($mark_value=''){
        $arr = array('b0' => '灰色', 'b1' => '红色', 'b2' => '橙色', 'b3' => '黄色', 'b4' => '蓝色', 'b5' => '紫色', 'b6' => '浅粉', 'b7' => '绿色', 'b8' => '浅蓝', 'b9' => '深绿', 'b10' => '桃红');
        $mark_type = array();
        $url = app::get('ome')->res_url;
        foreach ($arr as $k=>$v){
            $mark_type[$k] = $url.'/remark_icons/'.$k.'.png';
        }
        if ($mark_value){
            return $mark_type[$mark_value];
        }else{
            return $mark_type;
        }
    }

    /**
     * 获取商品类型
     * @return 返回现有商品类型
     */

    public function get_obj_type(){
        $obj_type = array();
        if ($service = kernel::servicelist('ome.service.order.confirm'))
        foreach ($service as $object => $instance){
            if (method_exists($instance, 'view_list')){
                $list = $instance->view_list();
                if (is_array($list) && count($list) > 0){
                    foreach ($list as $type =>$config){
                        $obj_type[$type] = $type;
                    }
                }
            }
        }
        return $obj_type;
    }

    /**
     * 增加order_items的unit字段
     * @param array $data 获得要处理的数组
     * @return array
     */
    static function add_getItemList_colum($data){
    	$basicMaterialLib    = kernel::single('material_basic_material');
    	
    	if($data){
	        foreach($data as $obj_type=>$obj){
	        	foreach($obj as $obj_id=>$oObj){
	        		foreach($oObj['order_items'] as $item_id=>$iObj)
	        		{
	        		    $product    = $basicMaterialLib->getBasicMaterialExt($iObj['product_id']);
	        		    
	        			$data[$obj_type][$obj_id]['order_items'][$item_id]['unit'] = $product['unit'];
	        			$data[$obj_type][$obj_id]['order_items'][$item_id]['specifications'] = $product['specifications'];
	        		}
	        	}
	        }
	        return $data;
    	}
    }

    /**
     * 增加order_items的unit字段
     * @param array $data 获得要处理的数组
     * @return array
     */
    static function add_items_colum($data){
        $basicMaterialLib    = kernel::single('material_basic_material');
        
        if($data){
            foreach($data as $obj_id=>$obj)
            {
                $product    = $basicMaterialLib->getBasicMaterialExt($obj['product_id']);
                
                $data[$obj_id]['unit'] = $product['unit'];
                $data[$obj_id]['specifications'] = $product['specifications'];
            }
            return $data;
        }
    }

    /**
     * 计算订单未发货商品的总金额
     * @param string $order_id 订单号
     * @return 未发货商品总金额
     */
    public function order_items_diff_money($order_id){
        $order = app::get('ome')->model('orders')->dump($order_id,"order_id",array("order_objects"=>array("*",array("order_items"=>array("*")))));
        $amount = 0;
        if ($order['order_objects']){
            foreach ($order['order_objects'] as $obj){
                if ($service = kernel::service("ome.service.order.remain.".trim($obj['obj_type']))){
                    if (method_exists($service, 'diff_money')){
                        $tmp_amount = $service->diff_money($obj);
                        $amount += $tmp_amount;
                    }
                }else {
                    if ($service = kernel::service("ome.service.order.remain.goods")){
                        if (method_exists($service, 'diff_money')){
                            $tmp_amount = $service->diff_money($obj);
                            $amount += $tmp_amount;
                        }
                    }
                }
            }
        }
        return $amount;
    }

   

    /**
     * 更新订单支付状态
     * @access public
     * @param string $order_id 订单主键ID
     * @return void
     */
    public function update_order_pay_status($order_id=NULL){
        $log = app::get('ome')->model('api_log');
        $logTitle = '更新订单状态操作[订单ID：'. $order_id .']';
        $logInfo = '更新订单 '. $order_id . ' 操作<BR>';

        if (empty($order_id)) {
            $log->write_log($log->gen_id(), $logTitle, __CLASS__, __METHOD__, '', '', 'response', 'fail', $logInfo,'','api.store.trade');

            return false;
        }

        $orderObj = app::get('archive')->model('orders');
        $refundObj = app::get('ome')->model('refunds');
        $refund_applyObj = app::get('ome')->model('refund_apply');
        $dObj = app::get('ome')->model('delivery');
        $order_filter = array("order_id"=>$order_id);

        //订单详情
        $order_detail = $orderObj->dump($order_filter, 'order_bn,payed,total_amount,shop_type');
        $payed = strval($order_detail['payed']);
        $total_amount = strval($order_detail['total_amount']);

        $logInfo .= '订单信息：<BR>' . var_export($order_detail, true) . '<BR>';
        $logInfo .= '当前支付状态：'. $payed . '<BR>';
        $logInfo .= '当前总计金额：'. $total_amount . '<BR>';

        //获取退款单及退款申请记录
        $refund_filter = array_merge($order_filter,array('status'=>'succ'));
        $refund_detail = $refundObj->dump($refund_filter, 'refund_id');
        $refund_apply_filter = array_merge($order_filter,array('status'=>array('0','1','2','6')));
        $refund_apply_detail = $refund_applyObj->dump($refund_apply_filter, 'apply_id');

        $logInfo .= '退款单信息：<BR>' . var_export($refund_detail, true) . '<BR>';
        $logInfo .= '退款申请信息：<BR>' . var_export($refund_apply_detail, true) . '<BR>';

        $pay_status = '';

        //支付状态
        if ($payed == '0' && $total_amount > '0' && !$refund_detail){
            $pay_status = '0';//未支付
        }elseif($payed < $total_amount && !$refund_detail){
            $pay_status = '3';//部分支付
        }elseif($payed >= $total_amount){
            $pay_status = '1';//已支付
        }

        //退款状态
        if($refund_apply_detail){
            $pay_status = '6';//退款申请中
        }elseif($payed == '0' && $refund_detail){
            //danny_freeze_stock_log
            define('FRST_TRIGGER_OBJECT_TYPE','订单：未发货订单全额退款导致订单取消');
            define('FRST_TRIGGER_ACTION_TYPE','ome_order_func：update_order_pay_status');
            $pay_status = '5';//全额退款
            //全额退款并且未发货的订单取消
            
        }elseif($payed < $total_amount && $refund_detail){
            $pay_status = '4';//部分退款

            
            
        }

        if ($pay_status !== ''){
        	//退款申请中 将订单置为暂停  其余的不暂停
            if($pay_status == 6){
                $data['pause'] = 'true';
        	    $logInfo .= '退款申请中 将订单置为暂停  其余的不暂停 信息：<BR>' . var_export($order_filter, true) . var_export($data, true) . '<BR>';
        	}else{
        	    $data['pause'] = 'false';
        	    $logInfo .= '将订单暂停状态恢复 信息：<BR>' . var_export($order_filter, true) . var_export($data, true) . '<BR>';
        	}
            $data['pay_status'] = $pay_status;
            if (!$orderObj->update($data, $order_filter)){
                $log->write_log($log->gen_id(), $logTitle, __CLASS__, __METHOD__, '', '', 'response', 'fail', $logInfo,'','api.store.trade',$order_detail['order_bn']);

                return false;
            }


        }

        $log->write_log($log->gen_id(), $logTitle, __CLASS__, __METHOD__, '', '', 'response', 'success', $logInfo,'','api.store.trade',$order_detail['order_bn']);

        return true;
    }

    /**
     * 判断是否大订单的界限
     * @access static public
     * @return int
     */
    static public function get_max_orderitems(){
        return 1000;
    }

    /**
     * 更新订单备注
     * @access public
     * @param Number $order_id 订单ID
     * @param String $add_mark 待更新的订单备注内容
     * @param String $op_name 操作人
     * @param String $old_mark_memo 订单现有的订单备注内容
     * @param bool $is_update 是否更新,默认更新true,false不更新
     * @return $is_update=true,返回成功或失败;$is_update=false返回待更新的订单备注内容
     */
    public function update_mark($order_id,$add_mark='',$op_name='',$old_mark='',$is_update=true){
        $add_mark = trim($add_mark);
        if (empty($add_mark)) return false;

        return $this->_update_mark_message($order_id,$add_mark,$op_name,$old_mark,'mark_text',$is_update);
    }

    private function _update_mark_message($order_id,$add_content,$op_name,$old_content,$field_name,$is_update){
        $oOrder = app::get('ome')->model('orders');
        if (empty($old_content)){
            $filter = array('order_id'=>$order_id);
            $orders = $oOrder->getRow($filter, 'mark_text,custom_mark');
            $old_content = $orders[$field_name];
        }

        $update_flag = true;#更新标识
        $oFunc = kernel::single('ome_func');
        $old_content = $oFunc->format_memo($old_content);
        $add_content = trim($add_content);
        if ($old_content){
            foreach ($old_content as $val){
                if ($val['op_content'] == $add_content){
                    $update_flag = false;
                    break;
                }
            }
        }
        if ($update_flag){
            #原始内容
            $new_content = is_array($old_content) ? $old_content : array();

            #追加内容
            $add_content = array('op_name'=>$op_name,'op_content'=>$add_content,'op_time'=>time());
            $new_content[] = $add_content;

            if ($is_update == true){
                return $oOrder->update(array($field_name=>serialize($new_content)), $filter);
            }else{
                return $new_content;
            }
        }
        return false;
    }

    /**
     * 更新买家留言
     * @access public
     * @param Number $order_id 订单ID
     * @param String $add_message 待更新的买家留言内容
     * @param String $op_name 操作人
     * @param String $old_mark_memo 订单现有的买家留言内容
     * @param bool $is_update 是否更新,默认更新true,false不更新
     * @return  $is_update=true,返回成功或失败;$is_update=false返回待更新的买家留言内容
     */
    public function update_message($order_id,$add_message='',$op_name='',$old_msg='',$is_update=true){
        $add_message = trim($add_message);
        if (empty($add_message)) return false;

        return $this->_update_mark_message($order_id,$add_message,$op_name,$old_msg,'custom_mark',$is_update);
    }

    /**
     * 定义基本的订单类型
     *
     * @return array
     * @author yangminsheng
     **/
    public function get_order_source($type = null)
    {

        $source = array(
            'local' => '分销王本地订单',
            'fxjl' => '抓抓',
            'taofenxiao' => '淘分销',
            'tbjx' => '淘宝经销',
            'tbdx' => '淘宝代销',
            'secondbuy' => '分销王秒批订单',
            'direct' => '直销订单',
            );
        
        if($type){
            return $source[$type];
        }

        return $source;
    }
    
}