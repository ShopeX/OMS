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
* 对外接口函数
* 
* @copyright shopex.cn 2013.4.10
* @author dongqiujin<123517746@qq.com>
*/
class channel_func{

    /**
    * 判断渠道是否已绑定
    *
    * @access public
    * @param String $channel_id 渠道ID
    * @return bool
    */
    public function isBind($channel_id=''){

        $channel_adapter = app::get('channel')->model('channel');
        $detail = $channel_adapter->getList('node_id',array('channel_id'=>$channel_id),0,1);
        return isset($detail[0]) && !empty($detail[0]['node_id']) ? true : false;
    }

    /**
    * 存储渠道与适配器的关系
    * @access public
    * @param String $channel_id 渠道ID
    * @param String $adapter 适配器
    * @return bool
    */
    public function saveChannelAdapter($channel_id,$adapter){
        $adapterMdl = app::get('channel')->model('adapter');
        $adapter_sdf = array(
            'channel_id' => $channel_id,
            'adapter' => $adapter
        );
        return $adapterMdl->save($adapter_sdf);
    }

    /**
    * 获取wms适配器列表
    * @access public
    * @return Array 适配器
    */
    public function getWmsAdapterList(){
        return middleware_adapter::getWmsList();
    }

    /**
    * 获取所有WMS类型的渠道
    * @access public
    * @return Array 适配器
    */
    public function getWmsChannelList(){
        #过滤o2o门店虚拟仓库
        $channelMdl = app::get('channel')->model('channel');
        $filter    = array('channel_type'=>'wms', 'node_type|notin'=>array('wap', 'webpos'));
        $channel_list = $channelMdl->getList('channel_id AS wms_id,channel_bn AS wms_bn,channel_name AS wms_name,node_id', $filter,0,-1);
        
        if($channel_list){
            foreach ($channel_list as &$val){
                $val['adapter'] = $this->getAdapterByChannelId($val['wms_id']);
            }
        }
        
        return $channel_list;
    }

    /**
    * 根据节点获取适配器
    *
    * @access public
    * @param String $node_id 节点号
    * @return Array 适配器
    */
    public function getAdapterByNodeId($node_id=''){

        $channelMdl = app::get('channel')->model('channel');
        $channel = $channelMdl->getList('channel_id',array('node_id'=>$node_id),0,1);

        $channel_adapter = app::get('channel')->model('adapter');
        $detail = $channel_adapter->getList('adapter',array('channel_id'=>$channel[0]['channel_id']),0,1);
        return isset($detail[0]) ? $detail[0]['adapter'] : '';
    }

    /**
    * 根据channel_id获取适配器
    *
    * @access public
    * @param String $channel_id 渠道ID
    * @return Array 适配器
    */
    public function getAdapterByChannelId($channel_id=''){

        $channel_adapter = app::get('channel')->model('adapter');
        $detail = $channel_adapter->getList('adapter',array('channel_id'=>$channel_id),0,1);
        return isset($detail[0]) ? $detail[0]['adapter'] : '';
    }

    /**
    * 根据wms_id获取wms_bn
    *
    * @access public
    * @param String $wms_id 渠道ID
    * @return String wms_bn
    */
    public function getWmsBnByWmsId($wms_id=''){

        $channel_adapter = app::get('channel')->model('channel');
        $detail = $channel_adapter->getList('channel_bn',array('channel_id'=>$wms_id),0,1);
        return isset($detail[0]) ? $detail[0]['channel_bn'] : '';
    }
    
    /**
    * 通过node_id获取渠道名称
    * @param String $node_id 节点号
    * @return String 
    */
    public function getChannelNameByNodeId($node_id=''){

        $channel_adapter = app::get('channel')->model('channel');
        $detail = $channel_adapter->getList('channel_name',array('node_id'=>$node_id),0,1);
        return isset($detail[0]) ? $detail[0]['channel_name'] : '';
    }

    
    /**
    * 通过wms_id获取渠道名称
    * @param String $wms_id wmsID
    * @return String 
    */
    public function getChannelNameById($wms_id=''){

        $channel_adapter = app::get('channel')->model('channel');
        $detail = $channel_adapter->getList('channel_name',array('channel_id'=>$wms_id),0,1);
        return isset($detail[0]) ? $detail[0]['channel_name'] : '';
    }

    /**
    * 根据channel_id获取node_id节点号
    * @param String $channel_id 渠道ID
    * @return String 
    */
    public function getNodeIdByChannelId($channel_id=''){

        $channel_adapter = app::get('channel')->model('channel');
        $detail = $channel_adapter->getList('node_id',array('channel_id'=>$channel_id),0,1);
        return isset($detail[0]) ? $detail[0]['node_id'] : '';
    }

    /**
    * 根据node_id获取wms_id
    * @param String $node_id 节点号
    * @return String 
    */
    public function getWmsIdByNodeId($node_id=''){

        $channel_adapter = app::get('channel')->model('channel');
        $detail = $channel_adapter->getList('channel_id',array('node_id'=>$node_id),0,1);
        return isset($detail[0]) ? $detail[0]['channel_id'] : '';
    }

    /**
     * 获取渠道类型
     *
     * @param Int $channel_id
     * @return void
     * @author 
     **/
    public function getWmsNodeTypeById($channel_id)
    {
        $channel_adapter = app::get('channel')->model('channel');

        $channel = $channel_adapter->dump($channel_id,'node_type');

        return $channel['node_type'];
    }

    /**
    * 根据node_id获取adapter_type
    * @param String $node_id
    * @return String 
    */
    public function getAdapterTypeByNodeId($node_id=''){
        $channel_adapter = app::get('channel')->model('channel');
        $detail = $channel_adapter->getList('channel_type',array('node_id'=>$node_id),0,1);
        return isset($detail[0]) ? $detail[0]['channel_type'] : '';
    }

    /**
    * 是否自有仓储
    * @param String $wms_id
    * @return bool 
    */
    public function isSelfWms($wms_id=''){
        if(empty($wms_id)){
            return false;
        }

        $channel_adapter = app::get('channel')->model('adapter');
        $detail = $channel_adapter->getList('adapter',array('channel_id'=>$wms_id),0,1);
        return isset($detail[0]) && $detail[0]['adapter']=='selfwms' ? true : false;
    }

    /**
    * 是否移动端H5仓储
    * @param String $wms_id
    * @return bool 
    */
    public function isWapWms($wms_id=''){
        if(empty($wms_id)){
            return false;
        }

        $channel_adapter = app::get('channel')->model('adapter');
        $detail = $channel_adapter->getList('adapter',array('channel_id'=>$wms_id),0,1);
        return isset($detail[0]) && $detail[0]['adapter']=='wap' ? true : false;
    }

    /**
    * 获取适配器sign密钥
    * @param String $node_id
    * @return String 
    */
    public function getSignKey($node_id=''){
        $channel_adapter = app::get('channel')->model('channel');
        $detail = $channel_adapter->getList('secret_key',array('node_id'=>$node_id),0,1);
        return isset($detail[0]) ? $detail[0]['secret_key'] : '';
    }

    #快递鸟支持的物流编码
    public function support_logistics_code(){
        return array (
              0 => 'ANWL',
              1 => 'AXD',
              2 => 'BFDF',
              3 => 'CCES',
              4 => 'BJCS',
              5 => 'COE',
              6 => 'CSCY',
              7 => 'DBKD',
              8 => 'DHL',
              9 => 'DSWL',
              10 => 'DTW',
              11 => 'EMS',
              12 => 'FAST',
              13 => 'FEDEX',
              14 => 'FKD',
              15 => '019guangdongyouzheng',
              16 => 'GSD',
              17 => 'GTO',
              18 => 'GTSD',
              19 => 'HFWL',
              20 => 'TTKDEX',
              21 => 'HLWL',
              22 => 'HOAU',
              23 => 'SHQ',
              24 => 'BEST',
              25 => 'HXLWL',
              26 => 'HYLSD',
              27 => 'JDCOD',
              28 => 'JGSD',
              29 => 'CNEX',
              30 => 'JTKD',
              31 => 'JXD',
              32 => 'JYKD',
              33 => '028jiayunmei',
              34 => 'JIAYI',
              35 => 'LB',
              36 => 'LTS',
              37 => 'MHKD',
              38 => 'MLWL',
              39 => 'NEDA',
              40 => 'QCKD',
              41 => 'QFKD',
              42 => 'QRT',
              43 => 'SAWL',
              44 => 'SDWL',
              45 => 'SF',
              46 => 'SFWL',
              47 => 'SHWL',
              48 => 'STWL',
              49 => 'STO',
              50 => 'SURE',
              51 => 'TSSTO',
              52 => 'UAPEX',
              53 => 'UC',
              54 => 'WJWL',
              55 => 'WXWL',
              56 => 'XB',
              57 => 'XFWL',
              58 => 'XYT',
              59 => 'YADEX',
              60 => 'YCWL',
              61 => 'YUNDA',
              62 => 'YFEX',
              63 => 'YFHEX',
              64 => 'AIRFEX',
              65 => 'YTKD',
              66 => 'YTO',
              67 => 'POSTB',
              68 => 'ZENY',
              69 => 'ZHQKD',
              70 => 'ZJS',
              71 => 'ZTE',
              72 => 'CRE',
              73 => 'ZTO',
              74 => 'ZTKY',
              75 => 'ZYWL',
              76 => 'BQXHM',
        );
    }

    #智能优选物流策略
    function exrecommend_type($souce_type='taobao'){
        $exrecommend_type = array(
                
                'taobao'=>array( 
                    '1'=>'时效服务优先'
               )
        );
        return $exrecommend_type[$souce_type];
    }
    #获取客户设置的智选策略
    function get_exrecommend_type($souce_type='hqepay'){
        $obj_exrecommend_set_logs = app::get('channel')->model('logistics_logs');
        #获取最新一条设置智选策略日志
        $exrecommend_set_logs = $obj_exrecommend_set_logs->getList('op_content',array('op_type'=>2,'exrecommend_souce'=>$souce_type),0,1,'create_time desc');
        
        #如果客户没有设置智选策略，默认是综合推荐
        if(empty($exrecommend_set_logs)){
            return '0';
        }
  
        $op_content = unserialize($exrecommend_set_logs[0]['op_content']);
        return $op_content['exrecommend_type'];
    }
    #获取模板上传记录
    function get_upload_records($exrecommend_souce){
        $obj_logistics_logs = app::get('channel')->model('logistics_logs');
        #检查是否有模板上传记录
        $logistics_logs = $obj_logistics_logs->getList('id',array('op_type'=>1,'exrecommend_souce'=>$exrecommend_souce),0,1,'create_time desc');
        if(empty($logistics_logs)){
            return false;
        }
        return true;
    }
    #检查是否有仓库、物流变动
    public function check_temple_change(){
        $obj_logistics_logs = app::get('channel')->model('logistics_logs');
        $logistics_logs = $obj_logistics_logs->getList('id',array('op_type'=>3,'status'=>'true'),0,1);
        if(empty($logistics_logs)){
            return false;
        }
        return true;
    }
    #检查智选物流的功能是否可用
    function check_exrecommend_available(){
        #检查智选物流的开关配置
        $set_exrecommend_service = app::get('channel')->getConf('set_exrecommend_service');
        #如果没设置,默认是关闭的,必须要用户手工开启才可用
        if(empty($set_exrecommend_service)){
            return false;
        }
        #如果已经开启了智选物流开关，而没有来源，则默认成快递鸟，因为这个来源是后来加的
        $channel_type = 'taobao';
        
        #获取模板上传记录，没有模板记录的，不能用
        $upload_log_records = $this->get_upload_records($channel_type);
        if(!$upload_log_records)return false;
        #菜鸟的,只要检测到有模板上传，就可以用了，不用再检查策略
        if($channel_type == 'taobao'){
            return true;
        }
        #快递鸟的，还需要获取客户设置的智选策略
        $exrecommend_type = $this->get_exrecommend_type($channel_type);
        if(!$exrecommend_type){
            return false;
        }
        return true;
    }
    #检查是否需要重复上传模板
    public function check_need_upload_temple(){
        $upload_records = $this->get_upload_records();
        #如果没有上传模板记录,则需要上传模板
        if(empty($upload_records)){
            return true;
        }
        $temple_change = $this->check_temple_change();
        #如果仓库或物流发送了变动，则需要我上传模板
        if($temple_change == 'true'){
            return true;
        }
        return false;
    }
    #检查是否订购了第三方平台的智选服务
    public function check_issubscribe($chanel_type){
        #非菜鸟的不需要检查
        if(!in_array($chanel_type,array('taobao')))return true;
        $cacheKey = 'exrecommend_'.$chanel_type.'_issubscribe';
        $is_subscribe = cachecore::fetch($cacheKey);
        if($is_subscribe)return true;
        $rs = kernel::single('ome_event_trigger_exrecommend_recommend')->issubscribe($chanel_type);

        if(!$rs || ($rs['rsp'] == 'fail'))return false;
        cachecore::store($cacheKey,true,86400);
        return true;
    }
    #获取智选物流的服务信息
    public function get_exrecommend_service_info($chanel_type){
        if($chanel_type == 'taobao'){
            $exrecommend_info = $this->check_issubscribe($chanel_type);
        }
        return $exrecommend_info;
    }
}