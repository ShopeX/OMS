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

class dealer_event_trigger_aftersale {

    

    public function push($plat_aftersale_id){

        $modelAftersale = app::get('dealer')->model('platform_aftersale');

        $aftersales = $modelAftersale->db_dump(array('plat_aftersale_id'=>$plat_aftersale_id),'*');

      
        //判断备注
        list($checkRs, $checkMsg) =$this->_checkUnpush($aftersales);
        if ($checkRs == false) {
           $modelAftersale->update(['sync_status' => '3', 'sync_msg'=>$checkMsg], ['plat_aftersale_id'=>$plat_aftersale_id]);

            return true;
        }

        list($checkRs, $checkMsg) =$this->_checkPush($aftersales);

        if ($checkRs == false) {
            $modelAftersale->update(['sync_status' => '2', 'sync_msg'=>$checkMsg], ['plat_aftersale_id'=>$plat_aftersale_id]);

            return true;
        }
        $return_type = $aftersales['return_type'];

        $status = $aftersales['status'];
        $itemsMdl = app::get('dealer')->model('platform_aftersale_items');

        if(in_array($return_type,array('return'))  && in_array($status,array('WAIT_BUYER_RETURN_GOODS','SUCCESS')) || in_array($return_type,array('refund','apply'))){


            $pushDatas = $this->formatPushParams($aftersales);

            
            if(empty($pushDatas)) return false;

            $count = count($pushDatas);
            $i=0;
            foreach($pushDatas as $k=>$data){

                if($i>1){
                    $data['refund_id'] = $data['refund_id'].'-'.$k;
                }

                $method = 'shop.aftersalev2.add';
                $data['method'] = $method;

                $data['from_platform'] = 'yjdf';
                
                $rs = kernel::single('erpapi_router_response')->set_node_id($data['node_id'])->set_api_name($method)->dispatch($data);
               
                $sync_status='1';
                if($rs['rsp']!='succ'){
                    $sync_status='2';
                }

                $updateData = array(

                    'sync_status'   =>  $sync_status,
                    'sync_msg'      =>  $rs['msg'],
                );

                $itemsMdl->update($updateData, ['plat_aftersale_id' => $plat_aftersale_id,'erp_order_id'=>$k]);
                $modelAftersale->update($updateData, ['plat_aftersale_id' => $plat_aftersale_id]);

                $i++;
            }
            
        }

    }



    private function _checkUnpush($aftersales)
    {
        
        if($aftersales['reason']){

            if (preg_match("/运费/", $aftersales['reason'])) {

                return [false,'退运费'];
                
            }
        }
               
        return [true];
    }
    /**
     * 校验推送
     *
     * @return void
     * @author
     **/
    private function _checkPush($aftersales)
    {
        

               
        return [true];
    }

    public function formatPushParams($aftersales){

        
        $plat_aftersale_id = $aftersales['plat_aftersale_id'];

        $extendMdl = app::get('dealer')->model('platform_aftersale_extend');

        $extends = $extendMdl->db_dump(array('plat_aftersale_id'=>$plat_aftersale_id),'json_data');

        $json_data = json_decode($extends['json_data'],true);

        $itemsMdl = app::get('dealer')->model('platform_aftersale_items');

        $items = $itemsMdl->getlist('*',array('plat_aftersale_id'=>$plat_aftersale_id));
        $return_item_list = array();

        $erp_order_ids = array();
        foreach($items as $v){
            if($v['erp_order_id']>0){
                $return_item_list[$v['erp_order_id']][] = $v;
                $erp_order_ids[$v['erp_order_id']] = $v['erp_order_id'];
            }
            
        }

        $ordobjMdl = app::get('ome')->model('order_objects');

        $ordobjs = $ordobjMdl->getlist('*',array('order_id'=>$erp_order_ids));

        $erp_ordobj = array();
        foreach($ordobjs as $v){

            $erp_ordobj[$v['order_id']][$v['line_no']] = $v;
        }


        $orditemMdl = app::get('ome')->model('order_items');

        $orditems = $orditemMdl->getlist('*',array('order_id'=>$erp_order_ids));

        $erp_orditems = array();

        foreach($orditems as $v){
            $erp_orditems[$v['order_id']][$v['obj_id']][$v['bn']] = $v;

        }

        $aftersale_data = array();
        foreach($return_item_list as $erp_order_id=>$rev){
            $return_item = array();
            $refund_fee = 0;
            foreach($rev as $rv){
                $erp_order_bn = $rv['erp_order_bn'];

                $erp_order_id = $rv['erp_order_id'];

                $erp_orditem = $erp_orditems[$erp_order_id][$rv['erp_obj_id']][$rv['bn']];
             
                $divide_order_fee = $erp_orditem['divide_order_fee'];

                $nums = $erp_orditem['nums'];

                $price = sprintf('%.3f',$divide_order_fee/$nums);
           
                $return_item[] = array(
                    'oid'           =>  $rv['oid'],
                    'num'           =>  $rv['num'],
                    'price'         =>  $price,
                    'bn'            =>  $rv['bn'],
                    'amount'        =>  $price*$rv['num'],
                    'sendNum'       =>  $erp_orditem['sendnum'],
                    'order_item_id' =>  $erp_orditem['item_id'],
                    'product_id'    =>  $erp_orditem['product_id'],
                    'name'          =>  $erp_orditem['name'],
                );
                
                $refund_fee+=$price*$rv['num'];
            }
            $tmp_aftersale_data = $json_data;

            $tmp_aftersale_data['platform_order_bn'] = $json_data['tid'];
            $tmp_aftersale_data['betc_id'] = $aftersales['betc_id'];
            $tmp_aftersale_data['cos_id'] = $aftersales['cos_id'];
            $tmp_aftersale_data['platform_aftersale_bn'] = $json_data['refund_id'];
            $tmp_aftersale_data['refund_fee']=$refund_fee;
            $refund_item_list = $tmp_aftersale_data['refund_item_list'];

            $refund_item_list = json_decode($refund_item_list,true);
            unset($tmp_aftersale_data['refund_item_list']);

            $tmp_aftersale_data['tid'] = $erp_order_bn;
            
            $tmp_aftersale_data['refund_item_list']['return_item'] = $return_item;


            $tmp_aftersale_data['refund_item_list'] = json_encode($tmp_aftersale_data['refund_item_list']);


            $aftersale_data[$erp_order_id] = $tmp_aftersale_data;

        }

        return $aftersale_data;

    }


    /**
     * 推送smart售后
     *
     * @return void
     * @author
     **/
    public function pushAftersaleSmart($plat_aftersale_id){

        $data = $this->formatAftersaleParams($plat_aftersale_id);
        $result = kernel::single('erpapi_router_request')->set('smart', $shop_id)->aftersale_add($data);
     
    }

    /**
     * 推送smart售后请求参数格式化
     *
     * @return void
     * @author
     **/
    public function formatAftersaleParams($plat_aftersale_id){

        return $data;
    }
}