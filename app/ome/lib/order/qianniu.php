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
 * 千牛修改订单相关业务
 *
 * @author wangbiao@shopex.cn
 * @version 2026.10.13
 */
class ome_order_qianniu extends ome_order_abstract
{
    /**
     * 淘宝自助改sku需要在1S之内返回是否允许修改SKU
     *
     * @param array $params
     * @return array
     */
    public function checkOrderModifySku($params)
    {
        $orderMdl = app::get('ome')->model('orders');
        
        // check
        if (empty($params['order_bn'])) {
            return $this->error('请求订单号为空，请检查！', array('msg_code' => '2001'));
        }
        
        if (empty($params['old_sale_bn'])) {
            return $this->error('原SKU为空，请检查！', array('msg_code' => '2001'));
        }
        
        if (empty($params['sale_bn'])) {
            return $this->error('新SKU为空，请检查！', array('msg_code' => '2001'));
        }
        
        if (empty($params['shop_id'])) {
            return $this->error('店铺信息为空，请检查！', array('msg_code' => '2001'));
        }
        
        $order_bn = $params['order_bn'];
        $shop_id = $params['shop_id'];
        $old_sales_material_bn = $params['old_sale_bn']; // 原SKU
        $new_sales_material_bn = $params['sale_bn']; // 新SKU
        
        // 本地查单，禁止同步拉单
        $filter = array(
            'order_bn' => $order_bn,
            'shop_id' => $shop_id,
        );
        $orderInfo = $orderMdl->dump($filter, 'order_id,order_bn,shop_id,ship_status,pay_status,is_modify,pause');
        if (!$orderInfo) {
            // 本地无单：直接许可平台改 SKU，不打标、不投 task
            return $this->succ('订单未进入OMS，允许平台修改SKU');
        }
        
        // 订单表字段集合
        $orderColumns = $orderMdl->schema;
        
        // 退款中不允许
        $pay_status = $orderInfo['pay_status'];
        if (in_array($pay_status, array('4','5','6','7'))) {
            // 付款状态
            $payStatusList = $orderColumns['columns']['pay_status']['type'];
            
            $pay_status_str = $payStatusList[$pay_status];
            
            return $this->error('订单付款状态['. $pay_status_str .']无法修改sku', array('msg_code' => '1015'));
        }
        
        // 已发货/已退货不允许；未发货（含未生成发货单、已生成未发货）允许
        $ship_status = (string) $orderInfo['ship_status'];
        if ($ship_status !== '0') {
            // 发货状态
            $shipStatusList = $orderColumns['columns']['ship_status']['type'];
            
            $ship_status_text = $shipStatusList[$ship_status];
            
            return $this->error('订单发货状态['. $ship_status_text .']无法修改sku', array('msg_code' => '1015'));
        }
        
        // 原 SKU 明细：有效行（未删除）
        $orderObjectsMdl = app::get('ome')->model('order_objects');
        $objFilter = array(
            'order_id' => $orderInfo['order_id'],
            'bn' => $old_sales_material_bn,
            'delete' => 'false',
            'shop_goods_id|noequal' => '-1',
        );
        $validObject = $orderObjectsMdl->dump($objFilter, '*');
        
        // is_modify
        if ($orderInfo['is_modify'] == 'true') {
            // 已修改过：原 SKU 明细已删除则不允许再改
            if (!$validObject) {
                return $this->error('订单编辑过商品，原SKU：'. $old_sales_material_bn .' 已删除', array('msg_code' => '3003'));
            }
        } elseif (!$validObject) {
            // 未标记修改：原 SKU 仍须在有效明细上
            return $this->error('订单原SKU：'. $old_sales_material_bn .' 不存在', array('msg_code' => '2001'));
        }
        
        // 打标：待换 SKU
        $orderExtendModel = app::get('ome')->model('order_extend');
        $orderExtendInfo = $orderExtendModel->dump(array('order_id'=>$orderInfo['order_id']), 'bool_extendstatus');
        $bool_extendstatus = $orderExtendInfo ? intval($orderExtendInfo['bool_extendstatus']) : 0;
        
        // save
        $extendSdf = array(
            'order_id' => $orderInfo['order_id'],
            'bool_extendstatus' => $bool_extendstatus | ome_order_bool_extendstatus::__UPDATESKU_ORDER,
        );
        $orderExtendModel->save($extendSdf);
        
        // 允许修改SKU，放入队列执行后续逻辑
        $opinfo = kernel::single('ome_func')->get_system();
        $blObj = app::get('ome')->model('batch_log');
        
        $logPayload = array(
            'order_id' => $orderInfo['order_id'],
            'order_bn' => $orderInfo['order_bn'],
            'shop_id' => $orderInfo['shop_id'],
            'old_sales_material_bn' => $old_sales_material_bn, // 原SKU
            'new_sales_material_bn' => $new_sales_material_bn, // 新SKU
        );
        $bldata = array(
            'op_id' => $opinfo['op_id'],
            'op_name' => $opinfo['op_name'],
            'createtime' => time(),
            'batch_number' => 1, // 批量发货总数
            'log_type' => 'modifysku',
            'log_text' => json_encode($logPayload),
        );
        $blObj->save($bldata);

        // 任务队列
        if (defined('SAAS_COMBINE_MQ') && SAAS_COMBINE_MQ == 'true') {
            $data = array();
            $data['spider_data']['url'] = kernel::openapi_url('openapi.autotask', 'service');
            
            $push_params = array(
                'log_text' => $bldata['log_text'],
                'log_id' => $bldata['log_id'],
                'task_type' => 'modifysku',
            );
            $push_params['taskmgr_sign'] = taskmgr_rpc_sign::gen_sign($push_params);
            $postAttr = array();
            foreach ($push_params as $key => $val) {
                $postAttr[] = $key . '=' . urlencode($val);
            }
            
            $data['spider_data']['params'] = empty($postAttr) ? '' : join('&', $postAttr);
            $data['relation']['to_node_id'] = base_shopnode::node_id('ome');
            $data['relation']['from_node_id'] = '0';
            $data['relation']['tid'] = $bldata['log_id'];
            $data['relation']['to_url'] = $data['spider_data']['url'];
            $data['relation']['time'] = time();
            
            $routerKey = 'tg.order.inventory.' . $data['relation']['from_node_id'];
            
            $message = json_encode($data);
            $mq = kernel::single('base_queue_mq');
            $mq->connect($GLOBALS['_MQ_COMBINE_CONFIG'], 'TG_COMBINE_EXCHANGE', 'TG_COMBINE_QUEUE');
            $mq->publish($message, $routerKey);
            $mq->disConnect();
        } else {
            $push_params = array(
                'data' => array(
                    'log_text' => $bldata['log_text'],
                    'log_id' => $bldata['log_id'],
                    'task_type' => 'modifysku',
                ),
                'url' => kernel::openapi_url('openapi.autotask', 'service'),
            );
            kernel::single('taskmgr_interface_connecter')->push($push_params);
        }
        
//        // 订单日志
//        app::get('ome')->model('operation_log')->write_log(
//            'order_modify@ome',
//            $orderInfo['order_id'],
//            '淘宝自助改SKU许可通过，原SKU:' . $old_sales_material_bn . ' 新SKU:' . $new_sales_material_bn
//        );
        
        return $this->succ('允许修改SKU');
    }
}
