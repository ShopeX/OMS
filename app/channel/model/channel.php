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
class channel_mdl_channel extends dbeav_model{
    
/* 	public function table_name($real = false){
        if($real){
           $table_name = 'sdb_ome_channel';
        }else{
           $table_name = 'channel';
        }
        return $table_name;
	} */

/*     public function modifier_node_id($cols){
       if(strlen($cols)<=0){
           return '未绑定';
       }else{
           return '已绑定';
       }
    } */
/*     public function get_schema(){
        return app::get('ome')->model('channel')->get_schema();
    } */
    #设置crm基本配置时,获取店铺类型,店铺名称
    public function getShopType(){
        $sql = 'select shop_type,shop_id,node_type,name from sdb_ome_shop where node_id is not null and node_id!=""';
        $node_type = $this->db->select($sql);
        $new = array();
       foreach($node_type as $k=>$v){
           $new[$v['shop_id']] = $v;
       }
        return $new;
    }

    public function getChannelInfo($cols = '*',$filter = array()){
        return $this->getList($cols,$filter);
    }
    #验证crm应用有没有添加,或者验证crm应用有没有绑定
    function valiCrmInfo(){
	    $sql = "select channel_id,node_id from sdb_channel_channel where channel_type='crm'";
        return $this->db->selectRow($sql);
    
    }
     #获取所有已经绑定的应用channel_id
/* 	function valiBindInfo(){
        $sql = 'select channel_id,node_id from sdb_ome_channel where node_id is not null';
        return $this->db->select($sql);
    
	} */

	#根据order_id,获取该条订单的相关信息
	function getOrderInfo($order_id = null){
       $order_info = app::get('ome')->model('orders')->db_dump($order_id);
       $member = app::get('ome')->model('members')->db_dump($order_info['member_id'], '*');
       
       // uname
       $order_info['uname'] = $member['uname'];
       
       // buyer_open_uid
       $order_info['buyer_open_uid'] = isset($member['buyer_open_uid']) ? $member['buyer_open_uid'] : '';
       
       return $order_info;
	}
	#获得店铺信息
    public function getShopInfo($shop_id) {
        $sql = "SELECT
                  node_id,name 
                FROM sdb_ome_shop 
                WHERE shop_id = '{$shop_id}'";
        $shop_info = $this->db->selectRow($sql);
        return $shop_info;
    }
    #获得订单明细
    public function getOrderItemInfo($order_id, $item_type = '') {
        $sql = "SELECT
                  bn,name, nums,sale_price as price
                FROM sdb_ome_order_items
                WHERE order_id = {$order_id}";
        if ($item_type) {
            $sql .= " AND item_type = '{$item_type}'";
        }
        $orderItem = $this->db->select($sql);
        return $orderItem;
    }

    /**
     * 获得订单对象详情
     * @param $order_id
     * @return mixed
     */
    public function getOrderObjects($order_id)
    {
        $sql = "SELECT bn, name, quantity as nums, sale_price as price FROM sdb_ome_order_objects WHERE order_id= {$order_id}";
        $order_obj = $this->db->select($sql);
        return $order_obj;
    }

	#检测是否有淘宝类型店铺已经绑定
	public function getTaobaoNodeInfo($shope_type = 'taobao'){
	    $sql = 'select node_id from sdb_ome_shop where node_type='."'$shope_type'";
	    $nodeInfo =  $this->db->select($sql);
	    $result = null;
	    foreach($nodeInfo as $node_id){
	        $result .= $node_id['node_id'];
	    }
	    if(strlen($result)>0){
	        #至少有一个淘宝类型店铺已经绑定,返回真
	        return true;
	    }else{
	        #没有一个绑定的淘宝类型店铺，返回为假
	        return false;
	    }
	}
	#绑定旺旺精灵时，获取已经绑定的店铺
	function get_bind_shop(){
	   #获取所有已经绑定的淘宝、天猫的来源店铺
	   $sql = 'select shop_id,name,addon from sdb_ome_shop where node_type=\'taobao\' and node_id is not null and node_id!=""';
	   return $this->db->select($sql);
	}
	#检查是否存在crm这条表记录，备注：只有一条crm类型表记录
	function checkedCrmInfo(){
	    $sql = "select count(*) count from sdb_channel_channel where channel_type='crm'";
	    $count = $this->db->selectRow($sql);
	    if($count['count'] == 0){
	        return false;
	    }
	    return true;
	}

    
    /**
     * 根据标识返回对应wms
     * @param  
     * @return 
     * @access  public
     * @author sunjing@shopex.cn
     */
    function get_wmd_idBynodetype($node_type=array())
    {
        
        $channel = $this->getlist('channel_id',array('node_type'=>$node_type));
        $channel_id = array();
        if ($channel) {
            foreach ($channel as $cha ) {
                $channel_id[] = $cha['channel_id'];
            }
        }
        
        return $channel_id;
    }

  
    /**
     * 根据wms_id返回node_id
     * @param   
     * @return  
     * @access  public
     * @author sunjing@shopex.cn
     */
    function get_node_idBywms($wms_id)
    {
        $channel = $this->dump(array('channel_id'=>$wms_id),'node_id');
        return $channel['node_id'];
    }
}
