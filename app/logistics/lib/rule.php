<?php
class logistics_rule {
    /**
     * 快递配置信息
     * @var $array
     */
    static $corpList = array();

    /**
     * 电子面单来源类型
     * @var $array
     */
    static $channelType = array();

    /**
     * 快递公司地区配置
     * @var Array
     */
    static $corpArea = array();

    /**
     * 地区配置信息
     * @var Array
     */
    static $region = array();

    //static $logiRule = array();

    /**
    *规则分组
    *@param obj_id
    *return array
    */
    function getGroupAreaRule($obj_id){
        $dly_corpObj = app::get('ome')->model('dly_corp');
        $db = kernel::database();
        foreach($obj_id as $k=>$v){
            $sql = "SELECT min_weight,max_weight,corp_id FROM sdb_logistics_rule_items WHERE obj_id=$v ORDER BY min_weight,max_weight DESC";
            $rule_item =$db->select($sql);
            $rule_group_hash='';
            foreach($rule_item as $ik=>$iv){
                $rule_group_hash.=$iv['min_weight'].$iv['max_weight'].$iv['corp_id'];
            }
            $rule_group_hash=md5($rule_group_hash);

            $db->exec("UPDATE sdb_logistics_rule_obj SET rule_group_hash='$rule_group_hash' WHERE obj_id=$v");
        }
        
        $obj_ids = implode(',',$obj_id);
        $sql = "SELECT o.obj_id,o.rule_group_hash,o.set_type FROM sdb_logistics_rule_obj as o  where o.obj_id in($obj_ids) group by o.rule_group_hash";

        $rule_obj = $db->select($sql);

        $rule_list = array();
        foreach($rule_obj as $rk=>$rv){
            $rule_group_hash = $rv['rule_group_hash'];
            $obj_id = $rv['obj_id'];

            $region_list = $db->select("SELECT region_id,region_name,obj_id FROM sdb_logistics_rule_obj WHERE obj_id in ($obj_ids) AND rule_group_hash='$rule_group_hash'");

            $region_id=array();
            $region_name=array();
            $obj_id_list=array();
            foreach($region_list as $uk=>$uv){
                $region_id[]=$uv['region_id'];
                $region_name[]=$uv['region_name'];
                $obj_id_list[]=$uv['obj_id'];
            }

            $item_sql = "SELECT min_weight,max_weight,corp_id FROM sdb_logistics_rule_items WHERE obj_id=$obj_id ORDER BY min_weight,max_weight DESC";
            $rule_item =$db->select($item_sql);
            foreach($rule_item as $ik=>$iv){
                $dly_corp = $dly_corpObj->dump($iv['corp_id'],'name');
                $rule_item[$ik]['corp_name'] = $dly_corp['name'];
            }
            $rule_list[$rk]=array(
                'region_id'=>implode(',',$region_id),
                'region_name'=>implode(',',$region_name),
                'item_list'=>$rule_item,
                'set_type'=>$rv['set_type'],
                'obj_id'=>implode(',',$obj_id_list),
            );
        }

        return $rule_list;
    }



    /**
     * 根据收货地址匹配物流公司
     *
     * @param String $shipArea 送货地址
     * @return mixed
     */
    function autoMatchDlyCorp($shipArea, $branchId,$weight,$shop_type='',$shop_id='') {

        $this->initCropData($shop_type);
        $regionId = preg_replace('/.*:([0-9]+)$/is', '$1', $shipArea);
        // 按需加载 region_path，避免全量地区表占用内存
        $regionPath = $this->getRegionPath($regionId);

        $corpId = 0;
        if (!empty($regionPath)) {
            $regionIds = explode(',', $regionPath);
            foreach($regionIds as $key=>$val){
                if($regionIds[$key] == '' || empty($regionIds[$key])){
                    unset($regionIds[$key]);
                }
            }
            $corp = $this->getMapBranchRule($branchId,$regionIds,$weight,$shop_id);
            //增加判断如果取到指定物流公司，判断该物流公司是否支持该仓库发货
            //根据仓库获取指定的物流
            $branch_corp_lib = kernel::single("ome_branch_corp");
            $corp_ids = $branch_corp_lib->getCorpIdsByBranchId(array($branchId));
            if(self::$corpList[$corp['corp_id']] && in_array($corp['corp_id'],$corp_ids)) {
                $channel_id = self::$corpList[$corp['corp_id']]['channel_id'];
                if(self::$corpList[$corp['corp_id']]['tmpl_type']=='electron' && self::$channelType[$channel_id]=='wlb') {
                    if(self::$corpList[$corp['corp_id']]['shop_id'] && self::$corpList[$corp['corp_id']]['shop_id']==$shop_id) {
                        $corpId = $corp['corp_id'];
                    } elseif($corp['second_corp_id']) {
                        $corpId = $corp['second_corp_id'];
                    }
                } else {
                    $corpId = $corp['corp_id'];
                }
            }
        }
        return $corpId;
    }




    /**
     * 按需获取地区 path（带静态缓存，避免一次加载全部地区）
     *
     * @param mixed $regionId
     * @return string
     */
    private function getRegionPath($regionId)
    {
        if ($regionId === '' || $regionId === null) {
            return '';
        }
        if (!isset(self::$region[$regionId])) {
            $row = kernel::single('eccommon_regions')->getOneById(intval($regionId), 'region_path');
            self::$region[$regionId] = ($row && !empty($row['region_path'])) ? $row['region_path'] : '';
        }
        return self::$region[$regionId];
    }

    /**
     * 初始化快递公司配置
     *
     * @param void
     * @return void
     */
    private function initCropData($shop_type) {
        // 用快递公司缓存判断是否已初始化（不再依赖全量地区数据）
        if (!empty(self::$corpList)) {
            return;
        }
        /**/
        $corp_filter = array('disabled' => 'false');

        #说明:如果是店铺类型是亚马逊或当当的，则只能显示他自己的物流公司和通用的物流公司。并且非亚马逊或当当的店铺只能选择通用的物流公司。
        $shop_data = array('DANGDANG','AMAZON');
        $shop_type = strtoupper($shop_type);

        if (!in_array($shop_type,$shop_data)) {
            $corp_filter['type|notin']=$shop_data;
        }else{                                 
            $tmp_shop_type = array($shop_type);
            $shop_diff = array_diff($shop_data,$tmp_shop_type);
            $corp_filter['type|notin']=$shop_diff;
        }

        //获取快递公司配置信息
        $corp = app::get('ome')->model('dly_corp')->getList('corp_id, name, type, is_cod, weight, tmpl_type, channel_id, shop_id', $corp_filter, 0, -1, 'weight DESC');
         foreach($corp as $item) {
            self::$corpList[$item['corp_id']] = $item;
        }
        unset($corp);

        //电子面单来源类型
        $channelObj = app::get("logisticsmanager")->model('channel');
        $channel = $channelObj->getList("channel_id,channel_type",array('status'=>'true'));
        foreach($channel as $val) {
            self::$channelType[$val['channel_id']] = $val['channel_type'];
            unset($val);
        }
        unset($channel);
    }

    /**
    * 获取仓库物流规则
    */
    function getMapBranchRule($branch_id,$regionIds,$weight,$shop_id){
        $branch_rule = kernel::database()->select('SELECT branch_id,type,parent_id FROM sdb_logistics_branch_rule WHERE branch_id='.$branch_id.' ORDER BY branch_id DESC');
        $branch_rule = $branch_rule[0];
        $branch_id= $branch_rule['branch_id'];
        if($branch_rule['type']=='other' && $branch_rule['parent_id']!=0){ //复用其他仓库规则
            $parent_id=0;
            app::get('logistics')->model('branch_rule')->getBranchRuleParentId($branch_id,$parent_id);
            $branch_id = $parent_id;
        }
        
        //新增set_type=shop按店铺的规则类型 按权限倒叙处理规则list 满足条件直接返回此规则所选的指定物流公司 by wangjianjun 20170804
        $mdl_logistics_rule = app::get('logistics')->model('rule');
        $rs_logistics_rule = $mdl_logistics_rule->getList("rule_id",array("branch_id"=>$branch_id),0,-1,"weight desc");
        if (empty($rs_logistics_rule)){//此仓库无规则
            return array();
        }
        //先过滤按店铺的规则 （倒叙规则list 只有当支持店铺也符合的情况下才能用“按店铺指定物流公司”的配置 ）
        $get_shop_rule_obj_id= false;
        $mdl_logistics_rule_obj = app::get('logistics')->model('rule_obj');
        $mdl_logistics_rule_shop = app::get('logistics')->model('rule_shop');
        foreach ($rs_logistics_rule as $var_l_r){
            $current_rule_obj = $mdl_logistics_rule_obj->dump(array("rule_id"=>$var_l_r["rule_id"]),"set_type,obj_id");
            if ($current_rule_obj["set_type"] == "shop"){
                $current_rule_shop = $mdl_logistics_rule_shop->dump(array("rule_id"=>$var_l_r["rule_id"],"shop_id"=>$shop_id));
                if (!empty($current_rule_shop)){ //此规则指定店铺包含此shop_id
                    $get_shop_rule_obj_id = $current_rule_obj["obj_id"];
                    break;
                }
            }else{//按任意重量、重量区间
                break;
            }
        }
        //处理规则
        if ($get_shop_rule_obj_id){ //按店铺选择 获取规则的指定物流公司
            $mdl_logistics_rule_items= app::get('logistics')->model('rule_items');
            $rs_rule_items = $mdl_logistics_rule_items->dump(array("obj_id"=>$get_shop_rule_obj_id),"corp_id");
            return array("corp_id"=>$rs_rule_items["corp_id"]);
        }else{ //按任意重量、重量区间 走原规则
            //获取需要排除的obj_id(按店铺的)
            $rs_rule_obj = $mdl_logistics_rule_obj->getList("obj_id",array("set_type"=>"shop","branch_id"=>$branch_id));
            $shop_obj_ids = array();
            if(!empty($rs_rule_obj)){
                foreach ($rs_rule_obj as $var_rule_obj){
                    $shop_obj_ids[] = $var_rule_obj["obj_id"];
                }
            }
            $sqlstr = ' WHERE o.branch_id='.$branch_id;
            if (!empty($shop_obj_ids)){
                $sqlstr .= ' and o.obj_id not in ('.implode(',',$shop_obj_ids).')';
            }
            $sql = 'SELECT distinct r.item_id,o.set_type,o.rule_type,r.region_id,i.* FROM sdb_logistics_rule_obj as o LEFT JOIN sdb_logistics_region_rule as r ON o.obj_id=r.obj_id left join sdb_logistics_rule_items as i on r.item_id=i.item_id  '.$sqlstr.' AND o.region_id in ('.implode(',',$regionIds).') AND o.rule_id>0 ORDER BY i.item_id DESC';
            $region_rule = kernel::database()->select($sql);
            $corp_rule = array();
            foreach ($region_rule as $rk=>$rule) {
                $corp_rule[$rule['rule_type']][$rule['region_id']][$rk]=array(
                        'corp_id' =>$rule['corp_id'],
                        'second_corp_id' =>$rule['second_corp_id'],
                        'min_weight'=>$rule['min_weight'],
                        'max_weight'=>$rule['max_weight'],
                        'set_type'=>$rule['set_type']
                );
            }
            $corp = array();
            if ($corp_rule['default']) {
                if($corp_rule['other']) {
                    $corp = $this->getCorpByArea($corp_rule['other'],$regionIds,$weight);//先匹配二级下属地区规则
                    if(!$corp || ($corp['corp_id']<0 && $corp['corp_id']!='-1')) {
                        $corp = $this->getCorpByArea($corp_rule['default'],$regionIds,$weight);//再匹配一级地区
                    }
                } else {
                    $corp = $this->getCorpByArea($corp_rule['default'],$regionIds,$weight);
                }
            }
            return $corp;
        }
    }

    /**
    * 获取区域对应物流公司
    *
    */
     function getCorpByArea($corp_rule,$regionIds,$weight) {
        $regionIds = array_reverse($regionIds);

        foreach($regionIds as $rId){

            if (isset($corp_rule[$rId])) {

                foreach ($corp_rule[$rId] as $rk=>$rv) {
                    
                    if ($rv['set_type'] =='noweight') {
                        $corp = $rv;
                        break 2;
                    } else {
                        if($weight>=$rv['min_weight'] && $weight<$rv['max_weight']){
                            $corp = $rv;
                            break 2;
                        }else if($weight>=$rv['min_weight'] && $rv['max_weight']=='-1'){
                            $corp = $rv;
                            break 2;
                        }
                    }

                }
            }

        }
        return $corp;

    }
    
    /**
     * 根据仓库获取默认物流公司
     * @param $code
     * @param $branchId
     * @param array $orderId
     * @return array
     */
    public function getCorpIdByCode($code, $branchId, $orderId = array()) {
        $filter = array(
            'type' => explode(',', $code)
        );
        $corp = app::get('ome')->model('dly_corp')->getList('*',$filter, 0, -1, 'weight DESC');
        $tempId = app::get('logisticsmanager')->model('express_template')->getList('template_id',
            array('template_type'=>array('cainiao','cainiao_standard','cainiao_user')));
        $arrTempId = array();
        foreach($tempId as $val) {
            $arrTempId[] = $val['template_id'];
        }
        foreach($corp as $val) {
            if(($val['all_branch'] == 'true'
                    || ($val['all_branch'] == 'false' && in_array($branchId, explode(',', $val['branch_id']))))
                && in_array($val['prt_tmpl_id'], $arrTempId)
            ) {
                if ($orderId) {
                    $orderInfo = app::get('ome')->model('order_extend')->getList('cpup_service', array('order_id' => $orderId, 'cpup_service' => '202'));
                    if ($orderInfo) {
                        $logisticsmanager = app::get('logisticsmanager')->model('channel')->db_dump(array('channel_id' => $val['channel_id']), 'channel_type,exp_type');
                        $wlbObj           = kernel::single('logisticsmanager_waybill_taobao');
                        $logistics        = $wlbObj->get_ExpType('SF');
                        //天猫物流升级-如果是88vip，电子面单来源必须是淘宝，快递类型必须是顺丰电商标快
                        if ($val['tmpl_type'] == 'electron' && $logisticsmanager['channel_type'] == 'taobao' && $logistics[$logisticsmanager['exp_type']] == '电商标快') {
                            return $val;
                        }
                        continue;
                    }
                }
                return $val;
            }
        }
        return array();
    }

    public function getSelfFetchCorp(){
        $selfFetchType = 'SELF_FETCH';
        $selfFetchName = '到店自提';
        $model = app::get('ome')->model('dly_corp');
        $rowData = $model->db_dump(array('type'=>$selfFetchType));
        if(!$rowData) {
            $rowData = array(
                'type' => $selfFetchType,
                'name' => $selfFetchName
            );
            $model->insert($rowData);
        }
        return $rowData;
    }

    public function getVopczcCorp(){
        $vopczcType = 'HXPJBEST';
        $vopczcName = '品骏快递';
        $model = app::get('ome')->model('dly_corp');
        $rowData = $model->db_dump(array('type'=>$vopczcType));
        if(!$rowData) {
            $rowData = array(
                'type' => $vopczcType,
                'name' => $vopczcName
            );
            $model->insert($rowData);
        }
        return $rowData;
    }
    
    public function getJITXCorp($order){

        $mdlChannel = app::get('logisticsmanager')->model('channel');
        $channel = $mdlChannel->getList('channel_id', array('channel_type'=>'vopjitx','logistics_code'=>$order['shipping'],'status'=>'true'));

       if (!$channel) return array ();

        $channelIds = array_map('current', $channel);

        $model = app::get('ome')->model('dly_corp');
        $rowData = $model->db_dump(array('channel_id'=>$channelIds));

        return $rowData;
    }
    
    /**
     * 根据类型获取对应的物流公司
     * @param $order
     * @param bool $is_option 是否指定物流
     * @return array|mixed|null
     * @date 2024-06-13 10:07 上午
     */
    public function getLubanShunfengCorp($order,$is_option = false)
    {
        $mdlChannel = app::get('logisticsmanager')->model('channel');
        $shipping = ['shunfeng','shunfengkuaiyun'];
        $filter = array(
            'channel_type'   =>  'douyin',
            'logistics_code' =>  $shipping,
            'status'         =>  'true',
        );
        if($is_option){
            $shipping = array_merge($shipping,(array)$order['shipping']);
        }
        if (in_array($order['shipping'], $shipping)) {
            $filter['logistics_code'] = $order['shipping'];
        }
        
        $channel = $mdlChannel->getList('channel_id', $filter);

       if (!$channel) return array ();

        $channelIds = array_map('current', $channel);

        $model = app::get('ome')->model('dly_corp');
        $rowData = $model->db_dump(array('channel_id'=>$channelIds));

        return $rowData;
    }

    public function getLubanCorp($order)
    {
        $mdlChannel = app::get('logisticsmanager')->model('channel');

        $filter = array(
            'channel_type'   =>  'douyin',
            'status'         =>  'true',
        );
        $channel = $mdlChannel->getList('channel_id', $filter);

       if (!$channel) return array ();

        $channelIds = array_map('current', $channel);

        $model = app::get('ome')->model('dly_corp');
        $rowData = $model->db_dump(array('channel_id'=>$channelIds));

        return $rowData;
    }

    public function getJDLVMICorp($order){

        $model = app::get('ome')->model('dly_corp');
        $rowData = $model->db_dump(array('type'=>$order['shipping']));

        return $rowData;
    }
    
    /**
     * 指定物流检测
     * @param $order_id
     * @param $shipping
     * @param $shop_type
     * @param string $bill_type
     * @return array
     * @author db
     * @date 2024-06-20 10:39 上午
     */
    public function getSelfSelectedLogistics($order_id, $shipping, $shop_type, $bill_type = 'order')
    {
        $is_specify = false;
        $corpIds    = [];
        $labelCode  = kernel::single('ome_bill_label')->getLabelFromOrder($order_id, $bill_type);
        $labelCode  = array_column($labelCode, 'label_code');
        if ($labelCode && in_array(kernel::single('ome_bill_label')->isExpressMust(), (array)$labelCode)) {
            $is_specify = true;//有指定物流
            
            $shippingInfo = kernel::single('ome_hqepay_shipping')->getLogiNameType($shipping, strtolower($shop_type));
            if ($shippingInfo) {
                $rowData = app::get('ome')->model('dly_corp')->getList('corp_id,type,name', ['type' => $shippingInfo['logi_type']]);
                if ($rowData) {
                    $corpIds = array_column($rowData, 'corp_id');
                }
            }
        }
        return [$is_specify, $corpIds];
    }

    /**
     * 根据类型获取对应的物流公司
     * @param $order
     * @param bool $is_option 是否指定物流
     * @return array|mixed|null
     * @date 2024-06-13 10:07 上午
     */
    public function getJdCorp($order,$is_option = false)
    {
        $mdlChannel = app::get('logisticsmanager')->model('channel');
        $shipping = [];
        $filter = array(
            'channel_type'   =>  ['360buy','jdalpha'],
            'status'         =>  'true',
        );
        if($is_option){
            $shipping = array_merge($shipping,(array)$order['shipping']);
        }
        if (in_array($order['shipping'], $shipping)) {
            $filter['logistics_code'] = $order['shipping'];
        }
        
        $channel = $mdlChannel->getList('channel_id', $filter);

       if (!$channel) return array ();

        $channelIds = array_map('current', $channel);

        $model = app::get('ome')->model('dly_corp');
        $rowData = $model->db_dump(array('channel_id'=>$channelIds));

        return $rowData;
    }
    
    /**
     * @param $order
     * @param $channel_type ['jdgxd'] or 'jdgxd'
     * @return array
     * @date 2025-03-17 4:38 下午
     */
    public function getChannelCorpList($channel_type)
    {
        $mdlChannel = app::get('logisticsmanager')->model('channel');
        $filter = array(
            'channel_type'   =>  $channel_type,
            'status'         =>  'true',
        );
        $channel = $mdlChannel->getList('channel_id', $filter);
        if (!$channel) return array ();
        
        $channelIds = array_column($channel, 'channel_id');
        $model = app::get('ome')->model('dly_corp');
        $rows = $model->getList('*',array('channel_id'=>$channelIds));
        
        return $rows;
    }
}
