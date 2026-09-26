<?php
/**
* 公共函数库
* 
* @copyright shopex.cn 2013.4.10
* @author dongqiujin<123517746@qq.com>
*/
class wmsmgr_func{

    /**
    * 根据wmsmgr_id获取适配器
    *
    * @access public
    * @param String $channel_id 渠道ID
    * @return Array 适配器
    */
    public function getAdapterByChannelId($channel_id=''){
        return kernel::single('channel_func')->getAdapterByChannelId($channel_id);
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
    * 存储渠道与适配器的关系
    * @access public
    * @return bool
    */
    public function saveChannelAdapter($channel_id,$adapter=''){
        return kernel::single('channel_func')->saveChannelAdapter($channel_id,$adapter);
    }

    /**
    * 根据wms_id、系统物流公司编号获取wms物流公司编号
    * @access public
    * @return string
    */
    public function getWmslogiCode($channel_id,$sys_express_corp_bn=''){
        $express_relation_mdl = app::get('wmsmgr')->model('express_relation');
        $data = $express_relation_mdl->getlist('*',array('wms_id'=>$channel_id,'sys_express_bn'=>$sys_express_corp_bn));
        return isset($data[0]['wms_express_bn']) ? $data[0]['wms_express_bn'] : '';
    }

    /**
    * 根据wms_id、wms物流公司编号获取系统物流公司编号
    * @access public
    * @return string
    */
    public function getlogiCode($channel_id,$wms_express_corp_bn=''){
        $express_relation_mdl = app::get('wmsmgr')->model('express_relation');
        $data = $express_relation_mdl->getlist('*',array('wms_id'=>$channel_id,'wms_express_bn'=>$wms_express_corp_bn));
        return isset($data[0]['sys_express_bn']) ? $data[0]['sys_express_bn'] : '';
    }

    /**
    * 根据wms_id、系统店铺编号获取wms售达方编号
    * @access public
    * @return string
    */
    public function getWmsShopCode($wms_id,$shop_bn=''){
        $shop_config = app::get('finance')->getConf('shop_config_'.$wms_id);
        return $shop_config[$shop_bn];
    }

    public function getBranchIdByStoreCode($storeCode)
    {
        $branch_relationObj = app::get('wmsmgr')->model('branch_relation');
        $branch_relation = $branch_relationObj->db->selectrow("SELECT b.branch_id,b.name FROM sdb_ome_branch as b LEFT JOIN sdb_wmsmgr_branch_relation as r ON b.branch_bn=r.sys_branch_bn WHERE r.wms_branch_bn='".$storeCode."'");

        return $branch_relation ? $branch_relation : array();
    }
    
    /**
     * [开普勒]获取京东云交易物流公司列表
     */
    public function getKeplerLogi()
    {
        $logiList = array (
            0 => array (
              'logi_id' => 'JD',
              'logi_code' => 'JD',
              'logi_name' => '京东快递',
            ),
            1 => array (
              'logi_id' => '680414',
              'logi_code' => 'ZTO56',
              'logi_name' => '中通快运',
            ),
            2 => array (
              'logi_id' => '845686',
              'logi_code' => 'XXWL',
              'logi_name' => '星星物流',
            ),
            3 => array (
              'logi_id' => '852412',
              'logi_code' => 'OTP',
              'logi_name' => '承诺达',
            ),
            4 => array (
              'logi_id' => '731302',
              'logi_code' => 'YDKY',
              'logi_name' => '韵达快运',
            ),
            5 => array (
              'logi_id' => '599866',
              'logi_code' => 'KYE',
              'logi_name' => '跨越速运',
            ),
            6 => array (
              'logi_id' => '764546',
              'logi_code' => 'YPYD',
              'logi_name' => '韵达',
            ),
            7 => array (
              'logi_id' => '839046',
              'logi_code' => 'YPZT',
              'logi_name' => '中通',
            ),
            8 => array (
              'logi_id' => '839104',
              'logi_code' => 'YPST',
              'logi_name' => '申通',
            ),
            9 => array (
              'logi_id' => '840864',
              'logi_code' => 'YPYZXB',
              'logi_name' => '邮政',
            ),
            10 => array (
              'logi_id' => '313214',
              'logi_code' => 'RFD',
              'logi_name' => '北京如风达',
            ),
            11 => array (
              'logi_id' => '2105',
              'logi_code' => 'SE',
              'logi_name' => '速尔快递',
            ),
            12 => array (
              'logi_id' => '692584',
              'logi_code' => 'PJ',
              'logi_name' => '品骏快递',
            ),
            13 => array (
              'logi_id' => '1748',
              'logi_code' => 'BESTJD',
              'logi_name' => '百世快递',
            ),
            14 => array (
              'logi_id' => '2100',
              'logi_code' => 'QY',
              'logi_name' => '全一快递',
            ),
            15 => array (
              'logi_id' => '323141',
              'logi_code' => 'AF',
              'logi_name' => '亚风快运',
            ),
            16 => array (
              'logi_id' => '1327',
              'logi_code' => 'YUNDA',
              'logi_name' => '韵达快递',
            ),
            17 => array (
              'logi_id' => '1409',
              'logi_code' => 'ZJS',
              'logi_name' => '宅急送',
            ),
            18 => array (
              'logi_id' => '465',
              'logi_code' => 'EMS',
              'logi_name' => '邮政',
            ),
            19 => array (
              'logi_id' => '1499',
              'logi_code' => 'ZTO',
              'logi_name' => '中通速递',
            ),
            20 => array (
              'logi_id' => '2016',
              'logi_code' => 'QFKD',
              'logi_name' => '全峰快递',
            ),
            21 => array (
              'logi_id' => '2465',
              'logi_code' => 'GTO',
              'logi_name' => '国通快递',
            ),
            22 => array (
              'logi_id' => '470',
              'logi_code' => 'STO',
              'logi_name' => '申通快递',
            ),
            23 => array (
              'logi_id' => '596494',
              'logi_code' => 'ANXB',
              'logi_name' => '安能快递',
            ),
            24 => array (
              'logi_id' => '833190',
              'logi_code' => 'JDYC',
              'logi_name' => '京东云仓',
            ),
            25 => array (
              'logi_id' => '751988',
              'logi_code' => 'MT',
              'logi_name' => '同城速配',
            ),
            26 => array (
              'logi_id' => '710024',
              'logi_code' => 'DADA',
              'logi_name' => '同城速配',
            ),
            27 => array (
              'logi_id' => '762584',
              'logi_code' => 'JDYP',
              'logi_name' => '宜昌远安韵',
            ),
            28 => array (
              'logi_id' => '1747',
              'logi_code' => 'UC',
              'logi_name' => '优速物流',
            ),
            29 => array (
              'logi_id' => '2094',
              'logi_code' => 'KJKD',
              'logi_name' => '快捷快递',
            ),
            30 => array (
              'logi_id' => '463',
              'logi_code' => 'YTO',
              'logi_name' => '圆通快递',
            ),
            31 => array (
              'logi_id' => '3046',
              'logi_code' => 'DBKD',
              'logi_name' => '德邦快递',
            ),
            32 => array (
              'logi_id' => '2087',
              'logi_code' => 'BDB',
              'logi_name' => '京东快递',
            ),
            33 => array (
              'logi_id' => '2170',
              'logi_code' => 'EMS',
              'logi_name' => '邮政',
            ),
            34 => array (
              'logi_id' => '4832',
              'logi_code' => 'ANE',
              'logi_name' => '安能物流',
            ),
            35 => array (
              'logi_id' => '597579',
              'logi_code' => 'QFKDBJ',
              'logi_name' => '全峰快递',
            ),
            36 => array (
              'logi_id' => '467',
              'logi_code' => 'SF',
              'logi_name' => '顺丰快递',
            ),
            37 => array (
              'logi_id' => '3668',
              'logi_code' => 'EMSBZ',
              'logi_name' => 'EMS',
            ),
            38 => array (
              'logi_id' => '2171',
              'logi_code' => 'CHINAPOST',
              'logi_name' => '邮政',
            ),
            39 => array (
              'logi_id' => '1549',
              'logi_code' => 'ZJB',
              'logi_name' => '宅急便',
            ),
            40 => array (
              'logi_id' => '500043',
              'logi_code' => 'KXTX',
              'logi_name' => '卡行天下',
            ),
            41 => array (
              'logi_id' => '568096',
              'logi_code' => 'WJK',
              'logi_name' => '万家康',
            ),
            42 => array (
              'logi_id' => '222693',
              'logi_code' => 'BYL',
              'logi_name' => '贝业新兄弟',
            ),
            43 => array (
              'logi_id' => '171686',
              'logi_code' => 'YZP',
              'logi_name' => '易宅配',
            ),
            44 => array (
              'logi_id' => '171683',
              'logi_code' => '1ZITON',
              'logi_name' => '一智通',
            ),
            45 => array (
              'logi_id' => '5419',
              'logi_code' => 'ZTWL',
              'logi_name' => '中铁物流',
            ),
            46 => array (
              'logi_id' => '2101',
              'logi_code' => 'KERRY',
              'logi_name' => '嘉里大通',
            ),
            47 => array (
              'logi_id' => '2462',
              'logi_code' => 'TDHY',
              'logi_name' => '天地华宇',
            ),
            48 => array (
              'logi_id' => '2460',
              'logi_code' => 'CNEX',
              'logi_name' => '佳吉快运',
            ),
            49 => array (
              'logi_id' => '2755',
              'logi_code' => 'WOMAI',
              'logi_name' => '中粮我买',
            ),
            50 => array (
              'logi_id' => '2461',
              'logi_code' => 'XBWL',
              'logi_name' => '新邦物流',
            ),
            51 => array (
              'logi_id' => '2130',
              'logi_code' => 'DBKD',
              'logi_name' => '德邦快递',
            ),
            52 => array (
              'logi_id' => '2009',
              'logi_code' => 'TTKD',
              'logi_name' => '天天快递',
            ),
            53 => array (
              'logi_id' => '605050',
              'logi_code' => 'CRE',
              'logi_name' => 'CRE',
            ),
            54 => array (
              'logi_id' => '687888',
              'logi_code' => 'rrs',
              'logi_name' => '日日顺物流',
            ),
            55 => array (
              'logi_id' => '1255654',
              'logi_code' => 'jtexpress',
              'logi_name' => '极兔速递',
            ),
        );
        
        return $logiList;
    }
}