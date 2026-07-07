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
class siso_receipt_sales_abstract
{
    /**
     * 销售单生成方法
     */
    function create($params,&$msg=array())
    {
        //[拆单]配置是否启动拆单
        $orderSplitLib    = kernel::single('ome_order_split');
        $split_seting     = $orderSplitLib->get_delivery_seting();
        
        $salesObj = app::get('ome')->model('sales');
        $salesObjectMdl = app::get('ome')->model('sales_objects');
        $itemsObj = app::get('ome')->model('sales_items');

        $this->_io_data = $params['iostock'];
        unset($params['iostock']);
        $this->_sales_data['sales'] = $this->get_sales_data($params);
        
        //格式化出入库信息内容($split_seting：是否启动拆单)
        $this->_sales_data = $this->convertSdf($this->_sales_data, $split_seting);
        
        if(is_array($this->_sales_data) && count($this->_sales_data)>0){
            
            //开启事务
            $trxStatus = $salesObj->db->beginTransaction();
            
            //list
            foreach($this->_sales_data['sales'] as $k => $sale)
            {
                //[拆单]过滤部分发货时,不生成销售单
                if(!empty($split_seting)){
                   $get_order_id       = intval($sale['order_id']);
                   $get_delivery_id    = intval($sale['delivery_id']);
                   
                   $allow_commit = $orderSplitLib->check_order_all_delivery($get_order_id, $get_delivery_id, true);
                   if($allow_commit){
                       continue; //部分拆分OR部分发货时,跳过生成销售单
                   }
                }

                // 事务内二次检查，防止并发重复生成销售单
                $order_id = intval($sale['order_id']);
                $salesObj->db->select(
                    "SELECT order_id FROM sdb_ome_orders WHERE order_id={$order_id} FOR UPDATE"
                );
                $exists = $salesObj->db->select(
                    "SELECT sale_id FROM sdb_ome_sales WHERE order_id={$order_id}"
                );
                if ($exists) {
                    continue;
                }
                
                if(!$this->check_required($sale,$msg)){
                    //事务回滚
                    $salesObj->db->rollBack();
                    
                    return false;
                }
                
                $main = array();
                $item = array();
                $salesObjects = array();
                $this->divide_data($sale,$main,$item, $salesObjects);
                
                //unset
                unset($main['sales_objects']);
                
                //save
                if($this->_mainvalue($main,$msg) && $this->_itemvalue($item,$msg)){
                    //获取销售编号
                    $sale_id = $this->gen_id();
                    
                    $main['sale_id'] = $sale_id;
                    
                    //objects层
                    foreach($salesObjects as $objKey => $objVal)
                    {
                        $objVal['sale_id'] = $sale_id;
                        
                        $isSave = $salesObjectMdl->insert($objVal);
                        if(!$isSave){
                            //事务回滚
                            $salesObj->db->rollBack();
                            
                            $msg[] = '保存销售对象objects明细失败[goods_bn:'. $objVal['goods_bn'] .']';
                            return false;
                        }
                        
                        $sale_obj_id = $objVal['obj_id'];
                        
                        //check
                        if(empty($objVal['sales_items'])){
                            continue;
                        }
                        
                        //items层
                        foreach($objVal['sales_items'] as $itemKey => $itemVal)
                        {
                            $itemVal['branch_id'] = $sale['branch_id'];
                            $itemVal['sale_id'] = $sale_id;
                            $itemVal['obj_id'] = $sale_obj_id;
                            
                            $isSave = $itemsObj->insert($itemVal);
                            if(!$isSave){
                                //事务回滚
                                $salesObj->db->rollBack();
                                
                                $msg[] = '保存销售对象items明细失败[product_bn:'. $itemVal['bn'] .']';
                                return false;
                            }
                        }
                    }
                    
                    //主数据
                    $isSave = $salesObj->save($main);
                    if(!$isSave){
                        //事务回滚
                        $salesObj->db->rollBack();
                        
                        $msg[] = '保存主数据失败[order_bn:'. $main['order_bn'] .']';
                        return false;
                    }
                    
                    //校验销售单
                    kernel::single('ome_sales_data')->proofSales($sale_id);
                }else{
                    //事务回滚
                    $salesObj->db->rollBack();
                    
                    return false;
                }
            }
            
            //事务确认
            $salesObj->db->commit($trxStatus);
            
            // 判断是否开启了成本管理，没有开启则不去更新
            $setting = kernel::single('tgstockcost_system_setting')->get_setting_value();
            if (!$setting || !$setting['tgstockcost.cost'] || $setting['tgstockcost.cost'] == '1') {
                $openCost = false;
            } else {
                $openCost = true;
            }

            //更新销售单上的成本单价和成本金额等字段
            if($this->_io_data && $openCost){
                kernel::single('tgstockcost_instance_router')->set_sales_iostock_cost(0,$this->_io_data);
            }
            
            return true;
        }else{
            return false;
        }
    }
    
    private function convertSdf($data, $split_seting=array())
    {
        //[拆单]获取多个发货单对应所有iostock出库单
        $orderSplitLib    = kernel::single('ome_order_split');
        if(!empty($split_seting))        {
            $order_delivery_iostock_data    = $orderSplitLib->get_delivery_iostock_data($this->_io_data, true);
        }
        
        foreach ($data['sales'] as $k => $sale)
        {
            //[拆单]多个发货单累加物流成本
            if(!empty($split_seting)){
                $get_order_id       = intval($sale['order_id']);
                $get_delivery_id    = intval($sale['delivery_id']);
                
                $delivery_cost_actual   = $orderSplitLib->count_delivery_cost_actual($get_order_id, true);
                if($delivery_cost_actual){
                    $data['sales'][$k]['delivery_cost_actual']  = $delivery_cost_actual;
                }
            }
            
            if ($data['sales'][$k]['sales_items']){
                foreach ($data['sales'][$k]['sales_items'] as $kk=>$vv)
                {
                    //[拆单]多个发货单时_iostock_id为NULL重新获取
                    if(!empty($this->_io_data[$vv['item_detail_id']]['iostock_id'])){
                        $vv['iostock_id'] = $this->_io_data[$vv['item_detail_id']]['iostock_id'];
                    }else{
                        $vv['iostock_id'] = isset($order_delivery_iostock_data[$vv['item_detail_id']]['iostock_id']) ? $order_delivery_iostock_data[$vv['item_detail_id']]['iostock_id'] : 0;
                    }
                    
                    $data['sales'][$k]['sales_items'][$kk] = $vv;

                    //[拆单]多个发货单时_iostock_bn为NULL重新获取
                    if(!empty($this->_io_data[$vv['item_detail_id']]['iostock_bn'])){
                        $iostock_bn = $this->_io_data[$vv['item_detail_id']]['iostock_bn'];
                    }else {
                        $iostock_bn = isset($order_delivery_iostock_data[$vv['item_detail_id']]['iostock_bn']) ? $order_delivery_iostock_data[$vv['item_detail_id']]['iostock_bn'] : 0;
                    }
                }
            }
            
            $data['sales'][$k]['iostock_bn'] = $iostock_bn;
            $sale_bn = $this->get_salse_bn();
            $data['sales'][$k]['sale_bn'] = $sale_bn;
        }
        return $data;
    }

    /**
     * 添加销售主表记录
     */
    function _add_Sales($data){
        $salesObj = app::get('ome')->model('sales');
        return $salesObj->save($data);
    }

    /**
     * 生成销售单的主键id号
     */
    function gen_id(){
        list($msec, $sec) = explode(" ",microtime());

        $sign = kernel::single('eccommon_guid')->incId('sales', $sec, 6);
        return $sign;
        /* $id = $sec.strval($msec*1000000);
          $conObj = app::get('ome')->model('concurrent');
        if($conObj->is_pass($id,'sales')){
            return $id;
        } else {
            return $this->gen_id();
        } */
    }

    /**
     * 检查所有必填字段
     */
    function check_required($data,&$msg){
        $msg = array();
        $arrMain = array('sale_amount','delivery_cost','operator','branch_id'); //主表必须字段
        $arrItems = array('bn','nums'); //明细表必须字段
        if(is_array($data) && count($data) > 0){
            $arrExist = array_keys($data);

            if(count(array_diff($arrMain,$arrExist))){
                $msg[] = '主表中必填字段不全';
                return false;
            }
            foreach($arrMain as $key){
                $tmp_value = trim($data[$key]);
                if(is_null($tmp_value) || $tmp_value === ''){
                    $msg[]=$key.'主表中必填字段值不能为空';
                }
            }
            if(in_array('sales_items',$arrExist) && is_array($data['sales_items'])){

                foreach($data['sales_items'] as $keys=>$contents){
                    $arrI = array_keys($contents);
                    if(count(array_diff($arrItems,$arrI))){
                        $msg[] ='明细表-' . $keys .'-' . '必填字段不全';
                    }else{
                        foreach($contents as $key=>$value){
                            if(in_array($key,$arrItems)){
                               empty($value) ? $msg[] = '明细表' . $keys . '-' .$key . '必填字段值不能为空' : '';
                            }
                        }
                    }
                }

                if(count($msg)){
                    return false;
                }
            }
            return true;
        }
        return false;
    }

    /**
     * 拆分出主表与子表数据
     */
    function divide_data($data,&$mainArr,&$itemArr, &$salesObjects){
        if($data){
            //sales_objects
            $salesObjects = array();
            foreach ((array)$data['sales_objects'] as $objKey => $objVal)
            {
                $order_obj_id = $objVal['order_obj_id'];
                
                $salesObjects[$order_obj_id] = $objVal;
            }
            
            //sales_items
            foreach ((array)$data['sales_items'] as $itemKey => $itemVal)
            {
                $order_obj_id = $itemVal['obj_id'];
                
                $salesObjects[$order_obj_id]['sales_items'][] = $itemVal;
            }
            
            //data
            foreach($data as $key=>$value)
            {
                if($key == 'sales_items'){
                    $itemArr = $data[$key];
                }else{
                    $mainArr[$key] = $data[$key];
                }
            }
            return true;
        }
        return false;
    }

    /**
     * 检查明细表值是否符合
     */
    function _itemvalue($data,&$msg){
        $rea = '字段类型不符(子表)';
        if(is_array($data)){
            foreach($data as $key=>$val){
                foreach($val as $field=>$content){
                    if($content != ''){
                        switch ($field){
                                //bigint(20) unsigned
                            case 'sale_id':
                            case 'iostock_id':
                                if(is_numeric($content) && strlen($content)<=20 && $content>=0){
                                    continue 2;
                                } else{
                                    $msg[] = $key .'-'. $field.'-'.$rea;
                                }
                                break;
                                //int(10) unsigned
                            case 'item_id':
                                if(is_numeric($content) && strlen($content)<=10 && $content>0){
                                    continue 2;
                                } else{
                                    $msg[] = $key .'-'. $field.'-'.$rea;
                                }
                                break;
                                //varchar(32)
                            case 'bn':
                                if(is_string($content) && strlen($content)<=32){
                                    continue 2;
                                } else{
                                    $msg[] = $key .'-'. $field.'-'.$rea;
                                }
                                break;
                                // mediumint(8) unsigned
                            case 'nums':
                            case 'branch_id':
                                if(is_numeric($content) && strlen($content)<=8 && $content>0){
                                    continue 2;
                                } else{
                                    $msg[] = $key .'-'. $field.'-'.$rea;
                                }
                                break;
                                //decimal(20,3)
                            case 'price':
                            case 'cost':
                            case 'cost_tax':
                                if(is_numeric($content) && strlen($content)<=20){
                                    continue 2;
                                } else{
                                    $msg[] = $key .'-'. $field.'-'.$rea;
                                }
                                break;
                        }
                    }
                }
            }
            return true;
        }
        return false;
    }

    /**
     * 检查主表字段值是否符合
     */
    function _mainvalue($data,&$msg){
        $rea = '字段类型不符(主表)';
        foreach($data as $key=>$content){
            if($content != ''){
                switch ($key){
                        //bigint(20) unsigned
                    case 'sale_id':
                        if(is_numeric($content) && strlen($content)<=20 && $content>0){
                            continue 2;
                        } else{
                            $msg[] = $key .'-'.$rea;
                        }
                        break;
                        //varchar(32)
                    case 'sale_bn':
                    case 'shop_id':
                        if(is_string($content) && strlen($content)<=32){
                            continue 2;
                        } else{
                            $msg[] = $key .'-'.$rea;
                        }
                        break;
                        //int(10) unsigned
                    case 'sale_time':
                    case 'member_id':
                        if (!empty($content)){
                            if(is_numeric($content) && strlen($content)<=10 && $content>0){
                                continue 2;
                            } else{
                                $msg[] = $key .'-'.$rea;
                            }
                        }
                        break;
                        //decimal(20,3)
                    case 'sale_amount':
                    case 'cost':
                    case 'delivery_cost':
                    case 'additional_costs':
                    case 'deposit':
                    case 'discount':
                        if(is_numeric($content) && strlen($content)<=20){
                            continue 2;
                        } else{
                            $msg[] = $key .'-'.$rea;
                        }
                        break;
                        //varchar(30)
                    case 'operator':
                        if(is_string($content) && strlen($content)<=30){
                            continue 2;
                        } else{
                            $msg[] = $key .'-'.$rea;
                        }
                        break;
                        //mediumint(8) unsigned
                    case 'branch_id':
                        if(is_numeric($content) && strlen($content)<=8 && $content>0){
                            continue 2;
                        } else{
                            $msg[] = $key .'-'.$rea;
                        }
                        break;
                        //enum('0','1')
                    case 'pay_status':
                        if(is_numeric($content) && strlen($content)<=2){
                            continue 2;
                        } else{
                            $msg[] = $key .'-'.$rea;
                        }
                        break;
                    case 'selling_agent_id':
                }
            }
        }
        return true;
    }

    /**
     * 生成销售单单号
     */
    function get_salse_bn($num = 0){
        $type = 'SALSE';
        $prefix = 'S'.date('Ymd');
        $sign = kernel::single('eccommon_guid')->incId($type, $prefix, 6, true);
        return $sign;
        
        /*
        $type = 'SALSE';

        if($num >= 1){
            $num++;
        }else{
            $sql = "SELECT id FROM sdb_ome_concurrent WHERE `type`='$type' and `current_time`>'".strtotime(date('Y-m-d'))."' and `current_time`<=".time()." order by id desc limit 0,1";
            $arr = kernel::database()->select($sql);
            if($id = $arr[0]['id']){
                $num = substr($id,-6);
                $num = intval($num)+1;
            }else{
                $num = 1;
            }
        }

        $po_num = str_pad($num,6,'0',STR_PAD_LEFT);
        $salse_bn = 'S'.date(Ymd).$po_num;

        $conObj = app::get('ome')->model('concurrent');
        if($conObj->is_pass($salse_bn,$type)){
            return $salse_bn;
        } else {
            if($num > 999999){
                return false;
            }else{
                return $this->get_salse_bn($num);
            }
        }
        */
    }
    
}
