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

class ome_mdl_misc_task extends dbeav_model
{
    private $objTypeProperty = array(
        'timing_confirm_order' => array(
            'text'   => '延时定时审单',
            'class'  => 'ome_order_timing',
            'method' => 'confirm',
        ),
        'delivery_send_again'  => array(
            'text'   => '发货单二次推送',
            'class'  => 'ome_delivery_notice',
            'method' => 'sendAgain',
        ),
        'delivery_lan_jie'  => array(
            'text'   => '发货单因售后仅退款拦截',
            'class'  => 'console_reship',
            'method' => 'batchOrderRefundToLJRK',
        ),
        'timing_bufa_order' => array(
            'text'   => '延迟创建补发赠品订单',
            'class'  => 'ome_order_bufa',
            'method' => 'createOrder',
        ),
        'timing_dealer_order' => array(
            'text'   => '延迟审核经销商订单',
            'class'  => 'dealer_platform_orders',
            'method' => 'confirmOrder',
        ),
        'timing_carrier_platform' => array(
            'text' => '重新获取平台承运商履约信息',
            'class' => 'ome_event_trigger_shop_logistics',
            'method' => 'getCarrierPlatform',
        ),
        'timing_cancel_order' => array(
            'text'   => '延迟取消订单',
            'class'  => 'ome_order_refund',
            'method' => 'reundCancelOrder',
        ),
        'urgent_logistics_notify' => array(
            'text'   => '淘宝加急发货状态回告',
            'class'  => 'ome_event_trigger_shop_order',
            'method' => 'urgent_logistics_report',
        ),
        'urgent_logistics_dly' => array(
            'text'   => '淘宝加急发货状态回告',
            'class'  => 'ome_event_trigger_shop_order',
            'method' => 'urgent_logistics_report',
        ),
        'urgent_logistics_csg' => array(
            'text'   => '淘宝加急发货状态回告',
            'class'  => 'ome_event_trigger_shop_order',
            'method' => 'urgent_logistics_report',
        ),
    );
    
    /**
     * 保存MiscTask
     * @param mixed $data 数据
     * @return mixed 返回操作结果
     */
    public function saveMiscTask($data)
    {
        if ($data['id']) {
            $id = $data['id'];
            unset($data['id']);
        } else {
            $row = $this->db_dump(array('obj_id' => $data['obj_id'], 'obj_type' => $data['obj_type']), 'id');
            $id  = $row['id'];
        }
        if ($id) {
            $this->update($data, array('id' => $id));
        } else {
            $data['create_time'] || $data['create_time'] = time();
            $this->insert($data);
            $id = $data['id'];
        }
        return $id;
    }

    /**
     * 处理
     * @return mixed 返回值
     */
    public function process()
    {
        // 多进程跑
        $page_no = 1;
        $limit   = 1000;

        if (cachecore::supportUUID()) {
            $page_no = cachecore::increment('misctaskexec');
        }

        if ($page_no < 1) {
            $page_no = 1;
        }

        $offset = ($page_no - 1) * $limit;
        $now    = time();
        $data   = $this->getList('id,obj_id,obj_type,extend_info', array('exec_time|lthan' => $now), $offset, $limit, 'exec_time asc');

        if (!$data) {
            cachecore::setcr('misctaskexec',0, 600);
            return true;
        }
        
        $arrMiscId = array();
        $arrMisc   = array();
        $taskList = array();
        foreach ($data as $val) {
            $arrMiscId[$val['id']]       = $val['id'];
            
            //任务类型
            $obj_type = $val['obj_type'];
            
            //按任务类型处理
            if(in_array($obj_type, array('timing_bufa_order'))){
                $taskList[] = $val;
            }else{
                $arrMisc[$obj_type][] = $val['obj_id'];
            }
        }
        
        //delete
        $this->delete(array('id' => $arrMiscId));
        
        //misc
        if($arrMisc){
            foreach ($arrMisc as $k => $val) {
                if ($this->objTypeProperty[$k]) {
                    kernel::single($this->objTypeProperty[$k]['class'])->{$this->objTypeProperty[$k]['method']}($val);
                }
            }
        }
        
        //补发任务
        //@todo：与上面调用不同，这里传入所有参数,上面只传obj_id字段值;
        if($taskList){
            foreach ($taskList as $taskKey => $taskVal)
            {
                //obj_type
                $obj_type = $taskVal['obj_type'];
                $propertyInfo = $this->objTypeProperty[$obj_type];
                
                //check
                if (empty($propertyInfo)) {
                    continue;
                }
                
                //exec
                $result = kernel::single($propertyInfo['class'])->{$propertyInfo['method']}($taskVal);
            }
        }
    }
}
