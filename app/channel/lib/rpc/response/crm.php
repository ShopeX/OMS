<?php
class channel_rpc_response_crm extends ome_rpc_response{
    public function crm_callback($result){
        // 验证签名
        $data = $_POST;
        if (empty($data['certi_ac'])) {
            die('0'); // certi_ac 不存在
        }
        
        $certi_ac = $data['certi_ac'];
        unset($data['certi_ac']);
        $sign = base_certificate::getCertiAC($data);
        
        if ($certi_ac != $sign) {
            die('0'); // 签名错误
        }
        
        $channel_id = $result['channel_id'];
        $nodes = $_POST;
        $status = $nodes['status'];
        $node_id = $nodes['node_id'];
        $node_type = $nodes['node_type'];
        $api_v = $nodes['api_v'];
        $filter = array('channel_id'=>$channel_id);
        
        $Obj_channel = app::get('ome')->model('channel');
        $shopdetail = $Obj_channel->dump(array('node_id'=>$node_id), 'node_id');
        if ($status=='bind' and !$shopdetail['node_id']){
            if ($node_id){
                $data = array('api_version'=>$api_v,'node_id'=>$node_id,'node_type'=>$node_type,'addon'=>$nodes);

                $Obj_channel->update($data, $filter);

                die('1');
            }
        }elseif ($status=='unbind'){
            $data = array('node_id'=>'');
            $Obj_channel->update($data, $filter);
            die('1');
        }
        die('0');
    }
}
