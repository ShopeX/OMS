<?php
/**
 * 物流跟踪请求（快递鸟订阅 / 顺丰路由注册 + 按配置查询）
 */
class ome_event_trigger_shop_hqepay
{
    public $node_id = '1227722633';
    
    public function check_bind_status(){
        $provider = $this->getTrackProvider();

        if ($provider == 'sf') {
            // Matrix 绑定：第三方快递 sf 且已回填 node_id
            $channel = app::get('channel')->model('channel')->dump([
                'channel_type' => 'kuaidi',
                'node_type'    => 'sf',
                'filter_sql'   => 'node_id IS NOT NULL AND node_id!=""',
            ]);
            return $channel ? true : false;
        }

        $channel = app::get('channel')->model('channel')->dump([
            'channel_type' => 'kuaidi',
            'node_type'    => 'kdn',
            'filter_sql'   => 'node_id IS NOT NULL AND node_id!=""',
        ]);

        if (!$channel) {
            return false;
        }
        return true;
    }

    /**
     * 物流跟踪来源：kdn（默认）| sf
     */
    public function getTrackProvider()
    {
        $provider = app::get('ome')->getConf('ome.logistics.track.provider');
        return $provider ?: 'kdn';
    }

    /**
     * 是否开启物流跟踪
     */
    public function isTrackEnabled()
    {
        return app::get('ome')->getConf('ome.delivery.hqepay') != 'false';
    }

    /**
     * 发货后订阅物流
     * - 跟踪关闭：跳过
     * - 来源 sf：顺丰订阅（store.trade.pub.appreciation）
     * - 来源 kdn：快递鸟增强订阅
     */
    public function hqepay_pub($delivery_id){
        if (!$this->isTrackEnabled()) {
            return true;
        }

        $rs = $this->check_bind_status();
        if(!$rs){return true;}

        if ($this->getTrackProvider() == 'sf') {
            return $this->sf_pub($delivery_id);
        }

        return $this->kdn_pub($delivery_id);
    }

    /**
     * 顺丰物流订阅（store.trade.pub.appreciation）
     * 必填：logistic_code=物流单号；tid=订单号可选
     */
    public function sf_pub($delivery_id)
    {
        $delivery = app::get('ome')->model('delivery')->getFinishDelivery($delivery_id);
        if (!$delivery || empty($delivery['logi_no'])) {
            return true;
        }

        $orderBn = '';
        if (!empty($delivery['orders'][0]['order_bn'])) {
            $orderBn = $delivery['orders'][0]['order_bn'];
        }

        $sdf = array(
            'logistic_code' => $delivery['logi_no'],
            'logi_no'       => $delivery['logi_no'],
            'tid'           => $orderBn,
            'order_bn'      => $orderBn,
            'delivery_bn'   => $delivery['delivery_bn'],
        );

        kernel::single('erpapi_router_request')->set('hqepay', 'sf')->sf_pub($sdf, true);
        return true;
    }

    /**
     * 增强版快递鸟物流订阅
     */
    public function kdn_pub($delivery_id)
    {
        $delivery = app::get('ome')->model('delivery')->getFinishDelivery($delivery_id);
        if (!$delivery){return true;} 
        
        // 物流公司
        $corp = app::get('ome')->model('dly_corp')->db_dump($delivery['logi_id'],'type,channel_id,tmpl_type,corp_id');
        $logi_type = $corp['type'];

        $pay_type = 1; //其他面单的，直接设置为1
        // 电子面单来源
        if ($corp['tmpl_type'] == 'electron' && $corp['channel_id']) {
            $channel = app::get('logisticsmanager')->model('channel')->dump($corp['channel_id']);
            if ($channel['channel_type'] == 'hqepay') {
              list(,,$pay_type) = explode('|||', $channel['shop_id']);
            }
        }

        // 对应仓库
        $branch = app::get('ome')->model('branch')->db_dump($delivery['branch_id']);

        $area = $branch['area'];
        kernel::single('ome_func')->split_area( $area);

        $sender = array();
        $sender['uname']    = $branch['uname'];
        $sender['province'] = $area[0];
        $sender['city']     = $area[1];
        $sender['area']     = $area[2];
        $sender['address']  = $branch['address'];
        $sender['tel']      = $branch['phone'];
        $sender['mobile']   = $branch['mobile'];
        $sender['zip']      = $branch['zip'];

        if (!$sender['uname'] || !$sender['address'] || !$area || (!$sender['tel']&&!$sender['mobile'])) {
          $shop = app::get('ome')->model('shop')->db_dump($delivery['shop_id']);

          $area = $shop['area'];
          kernel::single('ome_func')->split_area( $area);
          $sender['uname']    = $shop['default_sender'];
          $sender['province'] = $area[0];
          $sender['city']     = $area[1];
          $sender['area']     = $area[2];
          $sender['address']  = $shop['addr'];
          $sender['tel']      = $shop['tel'];
          $sender['mobile']   = $shop['mobile'];
          $sender['zip']      = $shop['zip'];
        }

        // 判断是否为第三方
        if ($branch['owner'] == '2' && $branch['wms_id'] && $delivery['type'] == 'wms' && app::get('wms')->is_installed()) {
            $express = app::get('wms')->model('express_relation')->db_dump(array('wms_id'=>$branch['wms_id'], 'corp_id'=>$corp['corp_id']));

            if ($express['wms_express_bn']) $logi_type = $express['wms_express_bn'];
        }

        $item_list = app::get('ome')->model('delivery_items')->getList('bn as bn  ,product_name as name ',array('delivery_id'=>$delivery_id));
        foreach($item_list as $k=>$t){
            $item_list[$k]['bn']   = '物品001';//$this->charFilter($t['bn']);
            $item_list[$k]['name'] = '物品001';//$this->charFilter($t['name']);
        }
        
        $to_address   = $delivery['ship_addr'] ? $delivery['ship_province'] . $delivery['ship_city'] . $delivery['ship_district'] . $delivery['ship_addr'] : '_SYSTEM';
        $from_address =  $sender['address'] ?  $sender['province'] . $sender['city'] .  $sender['area'] .  $sender['address'] : '_SYSTEM';

        // 开启揽件
        $receivedtime = 'true'==app::get('ome')->getConf('ome.logistics.received') ? app::get('ome')->getConf('ome.logistics.receivedtime'):null;

        $sdf = array(
                'warehouse_id'      => $delivery['branch_id'],
                'warehouse_address' => $from_address,
                'member_id'         => base_shopnode::node_id('ome'), #会员标识(体检必备字段)
                'company_code'      => $logi_type,
                'delivery_bn'       => $delivery['delivery_bn'],
                'logistic_code'     => $delivery['logi_no'],
                'tid'               => $delivery['orders'][0]['order_bn'],#取第一个发货单即可
                'order_type'        => ($corp['tmpl_type'] == 'electron' )?1:2,  # 运单类型:1-电子运单,2-纸质运 单
                'pay_type'          => $pay_type,  # 邮费支付方式:1-现付,2-到付
                'is_need_pay'       => '2',  # 是否代收货款:1-是,2-否
                'payment'           => 0,  # 代收货款金额
                'to_company'        => '收货人A', //$delivery['ship_name'],
                'to_name'           => '收货人A',//$delivery['ship_name'],
                'to_zip'            => '000000',//$delivery['ship_zip'],
                'to_province'       => '中国',//$delivery['ship_province'],
                'to_city'           => '中国',//$delivery['ship_city'],
                'to_area'           => '中国',//$delivery['ship_district'],
                'to_address'        => '中国',//$this->charFilter($to_address),
                'to_tel'            => '00000000',//(string)$delivery['tel'],
                'to_mobile'         => '00000000000',//(string)$delivery['ship_mobile'],
                
                'from_company'      => $sender['uname'],
                'from_name'         => $sender['uname'],
                'from_zip'          => (string)$sender['zip'],
                'from_province'     => (string)$sender['province'],
                'from_city'         => (string)$sender['city'],
                'from_area'         => (string)$sender['area']?:'',
                'from_address'      =>  $this->charFilter($from_address),
                'from_tel'          => (string)$sender['tel'],
                'from_mobile'       => (string)$sender['mobile'],
                'cost'              => '',
                'other_cost'        => '',
                'start_date'        => $receivedtime?date('Y-m-d').' '.$receivedtime:'',#上门取件的时间
                'end_date'          => '',
                'weight'            => '',
                'volume'            => '',
                'remark'            => '',
                'service_list'      => '',#增值服务，这个可不传
                'goods_list'        => json_encode($item_list) 
        );

        kernel::single('erpapi_router_request')->set('hqepay', $this->node_id)->hqepay_pub($sdf,true);
        return true;
    }
   
    #过滤特殊字符
    public function charFilter($str){
        return str_replace(array("#","<",">","&","'",'"',''),'',$str);
    }
}
