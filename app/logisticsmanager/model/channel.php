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

class logisticsmanager_mdl_channel extends dbeav_model {

    public $getWaybillAccountFromApi = ['wxshipin', 'xhs', 'meituan4bulkpurchasing', 'aikucun'];
    
    function modifier_status($row){
            if ($row == 'false') {
                $ret = '<span style="color:red;">已停用</span>';
            } else {
                $ret = '<span style="color:green;">已启用</span>';
            }
        return $ret;
    }
    #获取系统中有效的菜鸟电子面单来源
    public function get_taobao_channel($group_by = true,$channel_filter=array()){
        $sql = "select
                    CRC32(CONCAT(extend.province,'-',extend.city,'-',extend.area,'-',extend.address_detail))  send_area_id,
                  channel.channel_id,logistics_code, channel.channel_type,channel.shop_id,province,city, area,address_detail,mobile,tel,default_sender,extend.seller_id tb_seller_id
                 from sdb_logisticsmanager_channel  channel
                 join  sdb_logisticsmanager_channel_extend extend  on channel.channel_id=extend.channel_id      
                 WHERE  channel.channel_type='taobao'   and   channel.status='true'  and   ( extend.province!=''  and extend.city!='' and  extend.area!='' )";  
        #如果需要分组，则相同来源，相同类型，相同地址的，分在一组
        if($group_by){
            $sql = $sql." GROUP BY channel.shop_id,channel.channel_type,send_area_id";
            if($channel_filter['send_area_id']){
                $sql = $sql.' HAVING send_area_id='.$channel_filter['send_area_id'];
            }
            if($channel_filter['channel_id']){
                $sql = $sql.' HAVING channel.channel_id='.$channel_filter['channel_id'];
            }
        }

        $rs = $this->db->select($sql);
        return $rs;
    }
    #根据channel_id,获取所有与传入channel_id,具有相同店铺，相同类型，相同地址的电子面单来源
    /**
     * 获取_same_send_area_channels
     * @param mixed $channel_id ID
     * @return mixed 返回结果
     */
    public function get_same_send_area_channels($channel_id){
        $channel_filter['channel_id'] = $channel_id;
        $rs = $this->get_taobao_channel(false);
        if(!$rs)return false;
        $same_channels = array();
        $main_id = '';
        foreach($rs as $v){
            #同店铺，相同类型，相同地址面单来源，放在一起,当做同一个网点
            $same_channels[$v['send_area_id']][] = $v['channel_id'];
            if($v['channel_id'] == $channel_id){
                $main_send_area_id = $v['send_area_id'];
            }
        }
        return $same_channels[$main_send_area_id];
    }
    #获取菜鸟智选物流的时候，根据菜鸟返回的物流编码和发货地址，找到channel_id
    /**
     * 获取_channel_id
     * @param mixed $logistics_code logistics_code
     * @param mixed $address address
     * @return mixed 返回结果
     */
    public function get_channel_id($logistics_code,$address){
        $sql = "select  channel.channel_id
            from sdb_logisticsmanager_channel  channel
            join  sdb_logisticsmanager_channel_extend extend  on channel.channel_id=extend.channel_id   
            where   channel.channel_type='taobao'   and extend.seller_id!='' and   channel.status='true'  
            and channel.logistics_code='".$logistics_code."' and extend.province='".$address['province']."' and extend.city='".$address['city']."' and extend.area='".$address['district']."' and extend.address_detail='".$address['detail']."'";  
        $rs = $this->db->select($sql);
        if(!$rs)return false;
        return $rs[0]['channel_id'];
    }
}
?>