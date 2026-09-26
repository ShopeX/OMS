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

class ome_event_trigger_delivery{

    /**
     *
     * 发货通知创建发起方法
     * @param string $channel_type 通路类型
     * @param string $channel_id 通路ID
     * @param array $data 发货通知数据信息
     * @param string $sync 是否同步请求，true为同步，false异步，默认异步
     */
    public function create($channel_type='wms',$channel_id,&$data, $sync = false){
        if ($data['order_bn']) {
            $order_bns = explode('|',$data['order_bn']);
            
            $channelObj = app::get('channel')->model('channel');

            $channel = $channelObj->db->selectrow("SELECT node_id,node_type FROM sdb_channel_channel WHERE channel_id=".$channel_id." AND node_id>0 AND node_type!='selfwms'");

            if($channel){
                $hchsafe = array(
                    'to_node_id' => $channel['node_id'],
                    'tradeIds'   => $order_bns,
                 );

                kernel::single('base_hchsafe')->order_push_log($hchsafe);
            }
            //
        }
        return kernel::single('erpapi_router_request')->set($channel_type,$channel_id)->delivery_create($data);
    }

    /**
     *
     * 发货通知创建发起的响应接收方法
     * @param array $data
     */
    public function create_callback($res){

    }

    /**
     *
     * 发货通知取消发起方法
     * @param string $channel_type 通路类型
     * @param string $channel_id 通路ID
     * @param array $data 发货通知状态数据信息
     * @param string $sync 是否同步请求，true为同步，false异步，默认异步
     */
    public function cancel($channel_type='wms',$channel_id, $data, $sync = false){
        return kernel::single('erpapi_router_request')->set($channel_type,$channel_id)->delivery_cancel($data);
    }

    /**
     *
     * 发货通知拦截发起方法
     * @param string $channel_type 通路类型
     * @param string $channel_id 通路ID
     * @param array $data 发货通知状态数据信息
     */
    public function cut($channel_type='wms',$channel_id, $data){
        return kernel::single('erpapi_router_request')->set($channel_type,$channel_id)->delivery_cut($data);
    }

    /**
     *
     * 发货通知取消发起方法
     * @param array $data
     */
    public function cancel_callback($res){

    }

    /**
     *
     * 发货通知暂停发起方法
     * @param string $channel_type 通路类型
     * @param string $channel_id 通路ID
     * @param array $data 发货通知状态数据信息
     * @param string $sync 是否同步请求，true为同步，false异步，默认异步
     */
    public function pause($channel_type='wms',$channel_id, $data, $sync = false){
        return kernel::single('erpapi_router_request')->set($channel_type, $channel_id)->delivery_pause($data);
    }

    /**
     *
     * 发货通知暂停发起方法
     * @param array $data
     */
    public function pause_callback($res){

    }

    /**
     *
     * 发货通知恢复发起方法
     * @param string $channel_type 通路类型
     * @param string $channel_id 通路ID
     * @param array $data 发货通知状态数据信息
     * @param string $sync 是否同步请求，true为同步，false异步，默认异步
     */
    public function renew($channel_type='wms',$channel_id, $data, $sync = false){
        return kernel::single('erpapi_router_request')->set($channel_type,$channel_id)->delivery_renew($data);
    }

    /**
     *
     * 发货通知恢复发起方法
     * @param array $data
     */
    public function renew_callback($res){

    }

    
    /**
     * 发货单查询
     * @param   
     * @return 
     * @access  public
     * @author cyyr24@sina.cn
     */
    function search($channel_type='wms',$channel_id,&$sdf, $sync = false)
    {
        return kernel::single('erpapi_router_request')->set($channel_type,$channel_id)->delivery_search($data);
    }

    
    /**
     * 查询回调
     * @param   
     * @return  
     * @access  public
     * @author sunjing@shopex.cn
     */
    function search_callback($res)
    {
        
    }

    public function notify($wms_id,$data){

        return kernel::single('erpapi_router_request')->set('wms',$wms_id)->delivery_notify($data);
    }

    /**
     * 发货单签收同步下游
     *
     * @param string $channel_type
     * @param string $channel_id
     * @param array $data
     * @param bool $sync
     * @return array
     */
    public function sign($channel_type = 'wms', $channel_id, $data, $sync = false)
    {
        return kernel::single('erpapi_router_request')->set($channel_type, $channel_id)->delivery_sign($data);
    }
}