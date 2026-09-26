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
 * @desc
 * @author: jintao
 * @since: 2016/7/21
 */
class erpapi_shop_response_process_aftersale
{

    /**
     * 添加
     * @param mixed $params 参数
     * @return mixed 返回值
     */

    public function add($params)
    {
        if($params['action']=='update') {
            return $this->update_aftersale($params);
        }   
        $items = $params['return_product_items'];
        unset($params['return_product_items']);
        $opInfo = kernel::single('ome_func')->get_system();
        $params['order_id'] = $params['order']['order_id'];
        $params['op_id'] = $opInfo['op_id'];
        $params['source'] = 'matrix';
        $returnProductMdl = app::get('ome')->model('return_product');
        $returnProductMdl->create_return_product($params);
        if(empty($params['return_id'])) {
            $dbErr = $returnProductMdl->db->errorinfo();
            return array('rsp'=>'fail', 'msg'=>'售后申请单新建失败'.($dbErr ? ':'.$dbErr : ''));
        }
        
        //log
        $oOperation_log = app::get('ome')->model('operation_log');
        $oOperation_log->write_log('return@ome',$params['return_id'],'创建售后申请单');
        
        $returnItemModel = app::get('ome')->model('return_product_items');
        foreach($items as $item) {
            $item['return_id'] = $params['return_id'];
            $itemRs = $returnItemModel->insert($item);
            if(!$itemRs) {
                $dbErr = $returnItemModel->db->errorinfo();
                $failMsg = '售后申请单明细写入失败'.($dbErr ? ':'.$dbErr : '');
                $oOperation_log->write_log('return@ome', $params['return_id'], $failMsg);
                return array('rsp'=>'fail', 'msg'=>$failMsg);
            }
        }
        $is_return_auto_receive =  app::get('ome')->getConf('ome.return.auto.receive');
        
        // 检测是否开启售后自动审核 兼容ecos.ecshopx
        if($is_return_auto_receive == 'true' || $params['node_type'] == 'ecos.ecshopx'){
            // 检测是不是分销王(分销王没有换货，只有退货)
            if(in_array($params['node_type'],['shopex_b2b','ecos.ecshopx'])){
                // 检测状态是小于3,才可以自动接受申请操作
                if( $params['status'] < 3){
                    $error_msg = '';
                    $adata = array(
                        'choose_type_flag'=>'1',#退货单
                        'status'=>'3',#直接更新为已接受
                        'return_id'=>$params['return_id'],
                        'memo'=>'自动退货申请'
                    );
                    app::get('ome')->model('return_product')->tosave($adata,false, $error_msg);
                }
            }
        }

        if($params['table_additional']){

           $this->_dealTableAdditional($params['table_additional']);
        }
        return array('rsp'=>'succ', 'msg'=>'售后单接收成功');
    }

    /**
     * statusUpdate
     * @param mixed $params 参数
     * @return mixed 返回值
     */
    public function statusUpdate($params) {
        $status = $params['status'];
        $tgReturnItems = $params['return_items'];
        $data = array(
            'status'    => $status,
            'return_id' => $params['return_id'],
        );
        $returnModel = app::get('ome')->model('return_product');
        if (in_array($status, array('2','3'))) {
            foreach ($tgReturnItems as $key => $item) {
                $data['item_id'][$key]          = $item['item_id'];
                $data['effective'][$item['bn']] =  $item['num'];
                $data['bn'][$item['bn']]        = $item['num'];
            }
            $data['choose_type_flag'] = 1;
            $error_msg = '';
            $returnModel->tosave($data, false, $error_msg);
        } elseif ($status == '4') {
            $totalmoney = 0;
            foreach ($tgReturnItems as $key => $item) {
                $data['branch_id'][$key]  = $item['branch_id'];
                $data['product_id'][$key] = $item['product_id'];
                $data['goods_id'][$key]   = 0;
                $data['item_id'][$key]    = $item['item_id'];
                $data['effective'][$key]  = $item['num'];
                $data['name'][$key]       = $item['name'];
                $data['bn'][$key]         = $item['bn'];
                $data['deal'.$key]        = 1;
            }
            $data['totalmoney'] = $totalmoney;
            $data['tmoney']     = $totalmoney;
            $data['bmoney']     = 0;
            $data['memo']       = '';
            
            // 统计此次请求对应货号退货数量累加
            $can_refund = array();
            foreach($data['bn'] as $k=>$v){
                if(isset($can_refund[$v])){
                    $can_refund[$v]['num']++;
                }else{
                    $can_refund[$v]['num']=1;
                    $can_refund[$v]['effective'] = $data['effective'][$k];
                }
                if($can_refund[$v]['effective'] == 0){
                    return array('rsp'=>'fail', 'msg' => '货号为['.$v.']没有可申请量，请选择拒绝操作,订单号:'.$params['order_bn'].',售后申请单号:'.$params['return_bn']);
                }else if($can_refund[$v]['num'] > $can_refund[$v]['effective']){
                    return array('rsp'=>'fail', 'msg' => '货号为['.$v.']大于可申请量，请选择拒绝操作,订单号:'.$params['order_bn'].',售后申请单号:'.$params['return_bn']);
                }
            }
            $returnModel->saveinfo($data, true);
        } else {
            $returnModel->update(array('status'=>$status),array('return_id'=>$params['return_id']));
        }
        return array('rsp'=>'succ', 'msg'=>'售后申请单状态更新成功');
    }

    /**
     * logisticsUpdate
     * @param mixed $params 参数
     * @return mixed 返回值
     */
    public function logisticsUpdate($params) {
        $processData = array_merge((array)$params['old_process_data'], (array)$params['process_data']);
        app::get('ome')->model('return_product')->update(array('process_data'=>serialize($processData)),array('return_id'=>$params['return_id']));
        #分销王的退货单，同步物流信息
        // if( in_array ($params['node_type'],ome_shop_type::shopex_shop_type()) ){
            $obj_reship = app::get('ome')->model('reship');
            $rs  = $obj_reship->count(array('return_id'=>$params['return_id']));
            if($rs > 0){
                $_data['return_logi_name'] = $params['process_data']['shipcompany'];
                $_data['return_logi_no'] = $params['process_data']['logino'];
                $obj_reship->update($_data,array('return_id'=>$params['return_id']));
    
                $reshipInfo = $obj_reship->db_dump(['return_id'=>$params['return_id']],'reship_id,shop_type');
                //退换货自动审批
                if($_data['return_logi_no'] && $reshipInfo && $reshipInfo['shop_type'] == 'ecos.ecshopx'){
                    kernel::single('ome_reship')->batch_reship_queue($reshipInfo['reship_id']);
                }
            }
        // }
        return array('rsp'=>'succ', 'msg'=>'物流信息更新成功');
    }

    private function _dealTableAdditional($tableAdditional) {
        if(empty($tableAdditional)) {
            return false;
        }

        $model = app::get('ome')->model($tableAdditional['model']);
        unset($tableAdditional['model']);

        $model->db_save($tableAdditional);
    }
    
    /**
     * 更新_aftersale
     * @param mixed $params 参数
     * @return mixed 返回值
     */
    public function update_aftersale($params){
        //全民分销可以编辑，拒绝后可以重新开始
        if($params['status'] == '1'||$params['status'] == '5') {
            $oOperation_log = app::get('ome')->model('operation_log');//写日志
            unset($params['return_product_items']);
            unset($params['action']);
            $opInfo = kernel::single('ome_func')->get_system();
            $params['order_id'] = $params['order']['order_id'];
            $params['op_id'] = $opInfo['op_id'];
            $params['source'] = 'matrix';
            $rs = app::get('ome')->model('return_product')->update($params, array('return_id'=>$params['return_id']));
            if (is_bool($rs)) {
                return array('rsp' => 'fail', 'msg' => "更新售后申请单[{$params['return_bn']}]状态失败：可能是金额不一致");
            } else {
                $memo = '(退款金额、原因或版本变化)售后申请单更新为未审核';
                $oOperation_log->write_log('return@ome', $params['return_id'], $memo);
                return array('rsp' => 'succ', 'msg' => "更新售后申请单[{$params['return_bn']}]状态成功：{$params['status']},影响行数：" . $rs);
            }
        }
    }
}