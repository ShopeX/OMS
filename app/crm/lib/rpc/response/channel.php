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

class crm_rpc_response_channel extends ome_rpc_response{
    public function crm_callback($result){
        $channel_type = 'crm';//定义写死为crm类型
        $nodes = $_POST;
        $status = $nodes['status'];
        $node_id = $nodes['node_id'];
        $node_type = $nodes['node_type'];
        $api_v = $nodes['api_v'];
        
        $filter = array('channel_type'=>$channel_type);
        
        $Obj_channel = kernel::single('channel_channel');
        #检查是否存在crm这条表记录
        $_rs = $Obj_channel->exists($channel_type);
        if ($status == 'bind'){
            $data = array('api_version'=>$api_v,'node_id'=>$node_id,'node_type'=>$node_type,'addon'=>$nodes);
            if($_rs){
                $Obj_channel->update($data, $filter);
            }else{
                $self_node_id = base_shopnode::node_id('ome');
                $data['channel_type'] = $channel_type;
                $data['channel_name'] = $channel_type.'_'.$self_node_id;
                $data['channel_bn'] = $channel_type.'_'.$self_node_id;
                $Obj_channel->insert($data);
            }
            die('1');
        }elseif ($status == 'unbind'){
            #解绑操作
            $Obj_channel->unbind($filter);
            die('1');
        }
        die('1');
    }
}
