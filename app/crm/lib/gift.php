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

class crm_gift{
    private $crmGiftPlugin = array('paystatus','buyernick','memo','ordertype','orderamount','buygoods','sendgoods');

    function getGiftRule($sdf){ 
        #全局API日志
        $log_mdl = app::get('ome')->model('operation_log');
        $opinfo = kernel::single('ome_func')->getDesktopUser();

        $pay_time = floatval($sdf['pay_time']);
        $createtime = floatval($sdf['createtime']);
    
        $shop_name = $sdf['shop_name'];
        $shop_id = $sdf['shop_id'];
        $buyer_nick = $sdf['buyer_nick'];
        
        // 优先落 buyer_open_uid（脱敏昵称如 w** 会撞名），为空时再回退 buyer_nick
        $buyer_account = !empty($sdf['buyer_open_uid']) ? $sdf['buyer_open_uid'] : $buyer_nick;

        $order_id = $sdf['order_id'];
        $order_bn = $sdf['order_bn'];
        $payed = floatval($sdf['payed']);
        $province = $sdf['province'];
        $shopObj = app::get('ome')->model('shop');
        
        #查询是否存在有效规则
        $sql = "select * from sdb_ome_gift_rule where status = '1' and disable='false' and 
            ((time_type = 'createtime' AND ({$createtime} BETWEEN start_time AND end_time)) OR 
            (time_type = 'pay_time' AND ({$pay_time} BETWEEN start_time AND end_time))) 
            order by priority DESC,id DESC";
        $data = $shopObj->db->select($sql);
        if(empty($data)){
            return array('msg'=>"没有有效的赠品规则");
        }
        
        #获取基础赠品规则
        $ruleBaseId = array();
        foreach($data as $mykey=>$rule) {
            #赠品判断条件
            $rule['filter_arr'] = json_decode($rule['filter_arr'], true);
            $data[$mykey] = $rule;
            if($rule['filter_arr']['add_or_divide']) {
                foreach ($rule['filter_arr']['id'] as $val) {
                    $ruleBaseId[$val] = $val;
                }
            }
        }
        
        $ruleBase = array();
        if($ruleBaseId) {
            $ruleBaseRows = app::get('crm')->model('gift_rule_base')->getList('*', array('id'=>$ruleBaseId, 'disabled' => array('true', 'false')));
            foreach ($ruleBaseRows as $val) {
                $val['gift_list'] = unserialize($val['gift_list']);
                $val['filter_arr'] = json_decode($val['filter_arr'], true);
                $ruleBase[$val['id']] = $val;
            }
        }
        
        $reason = array();
        $giftList = array();
        $gift_send_log = array();//记录赠品发送日志
        $last_exclude_flag = 0;
        $bufaRules = array(); //补发应用规则
        $return = array();
        
        #检测是否符合赠送条件
        foreach($data as $mykey=>$rule){
            $apply_id = $rule['id'];
            
            //check
            if($rule['trigger_type'] != 'order_complete'){
                if($giftList){
                    if($last_exclude_flag == 1) { //上个满足的规则是排他的 忽略后续任何规则
                        break;
                    }
                    if($rule['is_exclude']==1) { //上个满足的规则不排他 当前规则排他的话直接跳过
                        continue;
                    }
                }
                $last_exclude_flag = $rule['is_exclude'];
            }
            
            //非全选,需要检测下店铺
            if($rule['shop_ids'] != '_ALL_'){
                if($rule['shop_ids']){
                    $rule['shop_ids'] = explode(',', $rule['shop_ids']);
                }
                if($rule['shop_ids'] && !in_array($shop_id, $rule['shop_ids'])){
                    $reason[] = 'ERP赠品:' . $rule['title'] . '(' . $apply_id . ')-不符合指定店铺';
                    continue;
                }
            }
            
            #非全选,需要检测送货地区
            if($rule['filter_arr']['province'][0] != '_ALL_'){
                if( ! $province or ! in_array($province, $rule['filter_arr']['province'])){
                    $reason[] = 'ERP赠品:' . $rule['title'] . '(' . $apply_id . ')-不符合指定收货区域';
                    continue;
                }
            }
            
            //[补发方式]订单完成后，再触发
            if($rule['trigger_type'] == 'order_complete' && empty($rule['defer_day'])){
                $reason[] = '应用规则:'. $rule['title'] .'('. $apply_id .')-延迟天数填写错误';
                continue;
            }
            
            //check
            if(empty($rule['filter_arr']['add_or_divide'])) {
                $reason[] = 'ERP赠品:' . $rule['title'] . '(' . $apply_id . ')-请使用新的规则';
                continue;
            }
            
            //赠品规则
            $reason[] = 'ERP赠品:' . $rule['title'] . '(' . $apply_id . ')';
            foreach ($rule['filter_arr']['id'] as $baseId) {
                if($giftList && $rule['filter_arr']['add_or_divide'] != 'add') {
                    break;
                }
                
                //check已被删除
                if($ruleBase[$baseId] && $ruleBase[$baseId]['disabled'] != 'false') {
                    $reason[] = '&nbsp;&nbsp;&nbsp;&nbsp;' . $ruleBase[$baseId]['rule_bn'] . '(' . $baseId . ')-该规则不存在';
                    continue;
                }
                
                //check赠品规则里没有赠品列表
                $msg = '&nbsp;&nbsp;&nbsp;&nbsp;' . $ruleBase[$baseId]['rule_bn'] . '(' . $baseId . ')-';
                if(empty($ruleBase[$baseId]['gift_list']) || !is_array($ruleBase[$baseId]['gift_list'])) {
                    $reason[] = $msg . '没有设定赠品';
                    continue;
                }
                
                $ruleBase[$baseId]['rule_id'] = $apply_id;
                $suite = 1; # 仅buygoods中引用
                
                //验证插件列表
                foreach ($this->crmGiftPlugin as $plugin)
                {
                    //补发赠品规则,跳过验证库存
                    if($rule['trigger_type'] == 'order_complete' && $plugin == 'sendgoods'){
                        continue;
                    }
                    
                    //exec
                    $rs = kernel::single('crm_gift_'.$plugin)->process($ruleBase[$baseId], $sdf, $suite);
                    if(!$rs[0]) {
                        if(strpos($rs[1], '库存不足') !== false) {
                            $return = array('err'=>1, 'msg'=>$msg . $rs[1]);
                        }
                        $reason[] = $msg . $rs[1];
                        continue 2;
                    } else {
                        if($rs[1]) {
                            $msg .= $rs[1] . '-';
                        }
                    }
                }
                
                //赠送方式
                if($rule['trigger_type'] == 'order_complete'){
                    $reason[] = $msg . '通过（延迟补发）';
                }else{
                    $reason[] = $msg . '通过';
                }
                
                //符合条件的赠品规则
                $suite = $suite > 1 ? $suite : 1;
                foreach ($ruleBase[$baseId]['gift_list'] as $gift_id => $num)
                {
                    //赠送数量
                    $gift_nums = $num * $suite;
                    
                    //赠送方式
                    if($rule['trigger_type'] == 'order_complete'){
                        //[延迟补发]订单完成之后，再创建补发订单赠送赠品
                        $bufaRules[$apply_id][$baseId][$gift_id] = $gift_id;
                    }else{
                        //订单审核时
                        $gift_send_log[$gift_id][$apply_id .'-'. $baseId] = $gift_nums;
                        $giftList[$gift_id] += $gift_nums;
                    }
                }
            }
        }
        
        #如果符合条件，添加赠送日志
        $return || $return = array('msg'=>'不符合赠品规则');
        $succReturn = array();
        if($giftList){
            $rule_gift_ids = array();
            
            #库存这里先观察下，是否需要设置
            $gifts = array();
            $create_time = time();
            $m_gift_logs = app::get('ome')->model('gift_logs');
            
            $rs = app::get('crm')->model('gift')->getList('gift_id as id,gift_bn,gift_name,gift_num,giftset,product_id',array('gift_id'=>array_keys($giftList)));
            foreach($rs as $v){
                $gift_num = (int) $giftList[$v['id']];
                $sqlstr   = '';
                if ($v['giftset'] == '0') {
                    $sqlstr = ',gift_num=gift_num-' . $gift_num;
                }
                $sql = "update sdb_crm_gift set send_num=send_num+" . $gift_num . "{$sqlstr} where  gift_id=" . $v['id'];
                $shopObj->db->exec($sql);
                $gifts[$v['gift_bn']] += $gift_num;
                
                #记录赠品发送日志
                foreach ($gift_send_log[$v['id']] as $rbid => $num) {
                    list($ruleId, $baseId) = explode('-', $rbid);
                    $md5_key = md5($order_bn.$ruleId.$v['gift_bn'].$create_time.$baseId);
                    $log_arr = array(
                        'order_source'=>$shop_name,
                        'order_bn'=>$order_bn,
                        'buyer_account'=>$buyer_account,
                        'member_id'=>isset($sdf['member_id']) ? intval($sdf['member_id']) : 0,
                        'shop_id'=>$shop_id,
                        'paid_amount'=>$payed,
                        'gift_num'=>$num,
                        'gift_rule_id'=>$ruleId,
                        'rule_base_id'=>$baseId,
                        'gift_bn'=>$v['gift_bn'],
                        'gift_name'=>$v['gift_name'],
                        'create_time'=>$create_time,
                        'md5_key'=>$md5_key,
                        'status'=>0,
                    );
                    $m_gift_logs->save($log_arr);
                    
                    //赠送记录关系
                    $rule_gift_ids[$baseId][$v['id']] = $gift_num;
                }
            }
            
            #返回erp的发货数据
            $succReturn = array(
                'order_bn'=>$order_bn,
                'gifts'=>$gifts,
            );
            
            //更新[赠品规则记录 ]已赠送数量
            foreach ($rule_gift_ids as $rule_base_id => $item)
            {
                foreach ($item as $gift_id => $gift_num){
                    $update_sql = "UPDATE sdb_crm_gift_rule_logs SET send_num=send_num+". $gift_num .",send_time=". time();
                    $update_sql .= " WHERE rule_id=". $rule_base_id ." AND gift_id=". $gift_id;
                    $shopObj->db->exec($update_sql);
                }
            }
        }
        
        //[延迟补发]订单完成之后，再创建补发订单赠送赠品
        if($bufaRules){
            $succReturn['order_bn'] = $order_bn;
            $succReturn['bufaRules'] = $bufaRules;
        }
        
        //log
        if($reason){
            $logInfo = implode('<br/>', $reason);
            $log_mdl->write_log('order_preprocess@ome',$order_id, $logInfo,time(),$opinfo);
        }
        
        //返回结果
        if($succReturn){
            //succ
            return $succReturn;
        }else{
            //fail
            return $return;
        }
    }
    
    /**
     * 保存赠品规则记录
     * 
     * @param array $params 赠品规则数据
     * @param string $error_msg
     * @return bool
     */
    public function save_gift_rule_logs($params, &$error_msg=null)
    {
        $ruleObj = app::get('crm')->model('gift_rule_base');
        $ruleLogObj = app::get('crm')->model('gift_rule_logs');
        $giftObj = app::get('crm')->model('gift');
        
        $rule_id = $params['id'];
        $gift_list = unserialize($params['gift_list']);
        
        //check
        if(empty($params) || empty($params['id'])){
            $error_msg = '无效的数据';
            return false;
        }
        
        if(empty($gift_list)){
            $error_msg = '没有赠送商品';
            return false;
        }
        
        $data = array(
                'rule_id' => $rule_id,
                'rule_bn' => $params['rule_bn'],
        );
        
        //赠品规则
        $ruleInfo = $ruleObj->dump(array('id'=>$rule_id), 'rule_bn');
        if($ruleInfo){
            $data['rule_bn'] = $ruleInfo['rule_bn'];
        }
        
        //赠品信息
        $tempList = $giftObj->getList('gift_id,product_id,gift_bn,gift_name,spec_info', array('gift_id'=>array_keys($gift_list)));
        if(empty($tempList)){
            $error_msg = '没有找到赠品数据';
            return false;
        }
        
        $giftList = array();
        foreach ($tempList as $key => $val){
            $gift_id = $val['gift_id'];
            
            $giftList[$gift_id] = $val;
        }
        
        foreach ($giftList as $gift_id => $val)
        {
            $saveData = array(
                'rule_id' => $data['rule_id'],
                'rule_bn' => $data['rule_bn'],
                'gift_id' => $gift_id,
                'product_id' => $val['product_id'],
                'gift_bn' => $val['gift_bn'],
                'gift_name' => $val['gift_name'],
                'spec_info' => $val['spec_info'],
            );
            
            //是否已经存在
            $ruleLogInfo = $ruleLogObj->dump(array('rule_id'=>$rule_id, 'gift_id'=>$gift_id), 'sid');
            if($ruleLogInfo){
                $saveData['sid'] = $ruleLogInfo['sid'];
            }else{
                $saveData['create_time'] = time();
                $saveData['update_time'] = time();
            }
            
            $result = $ruleLogObj->save($saveData);
        }
        
        return true;
    }
    
    /**
     * 获取补发赠品规则
     * 
     * @param $orderInfo 订单信息（包含objects、items商品明细）
     * @param $applyGiftInfo 订单审核时符合条件的赠品应用规则
     * @return array
     */
    public function getBufaGiftRules($orderInfo, $applyGiftInfo=null)
    {
        $giftLogMdl = app::get('ome')->model('gift_logs');
        $crmGiftMdl = app::get('crm')->model('gift');
        $ruleBaseMdl = app::get('crm')->model('gift_rule_base');
        $logMdl = app::get('ome')->model('operation_log');
        
        //opinfo
        $opinfo = kernel::single('ome_func')->getDesktopUser();
        
        //指定的赠品应用规则
        $applyIds = $applyGiftInfo['applyIds'];
        
        //setting
        $order_id = $orderInfo['order_id'];
        $order_bn = $orderInfo['order_bn'];
        $shop_id = $orderInfo['shop_id'];
        $shop_name = $orderInfo['shop_name'];
        $buyer_nick = $orderInfo['buyer_nick'];
        
        // 优先落 buyer_open_uid（脱敏昵称如 w** 会撞名），为空时再回退 buyer_nick
        $buyer_account = !empty($orderInfo['buyer_open_uid']) ? $orderInfo['buyer_open_uid'] : $buyer_nick;
        
        $payed = floatval($orderInfo['payed']);
        $pay_time = floatval($orderInfo['paytime']);
        $createtime = floatval($orderInfo['createtime']);
        
        $result = array('rsp'=>'fail', 'error_msg'=>'');
        $reason = array();
        
        //收货地区
        list($mainland, $areaInfo, $areaId) = explode(':', $orderInfo['ship_area']);
        list($province, $city, $district) = explode('/', $areaInfo);
        
        //[补发赠品规则]获取赠品应用规则
        if($applyIds){
            //指定赠品应用规则
            $sql = "SELECT * FROM sdb_ome_gift_rule WHERE id IN(". implode(',', $applyIds) .") ORDER BY priority DESC, id DESC";
        }else{
            $sql = "SELECT * FROM sdb_ome_gift_rule WHERE status='1' AND disable='false' AND trigger_type='order_complete'
            AND ((time_type='createtime' AND ({$createtime} BETWEEN start_time AND end_time)) OR (time_type='pay_time' AND ({$pay_time} BETWEEN start_time AND end_time)))
            ORDER BY priority DESC, id DESC";
        }
        
        $tempList = $ruleBaseMdl->db->select($sql);
        if(empty($tempList)){
            return array('rsp'=>'fail', 'error_msg'=>'没有有效的补发赠品规则');
        }
        
        //格式化赠品规则
        $ruleList = array();
        $ruleBaseIds = array();
        foreach($tempList as $ruleKey => $ruleInfo)
        {
            $apply_id = $ruleInfo['id'];
            
            //赠品判断条件
            $ruleInfo['filter_arr'] = json_decode($ruleInfo['filter_arr'], true);
            
            //check
            if(empty($ruleInfo['filter_arr'])) {
                $reason[] = '应用规则:'. $ruleInfo['title'] .'(' . $apply_id . ')-不符合赠送条件';
                continue;
            }
            
            if(empty($ruleInfo['filter_arr']['add_or_divide'])) {
                $reason[] = '应用规则:' . $ruleInfo['title'] . '(' . $apply_id . ')-请使用新的规则';
                continue;
            }
            
            if(empty($ruleInfo['filter_arr']['id'])) {
                $reason[] = '应用规则:'. $ruleInfo['title'] .'(' . $apply_id . ')-没有指定赠品规则';
                continue;
            }
            
            //[非全选]检测选择的店铺
            if($ruleInfo['shop_ids'] != '_ALL_'){
                if($ruleInfo['shop_ids']){
                    $ruleInfo['shop_ids'] = explode(',', $ruleInfo['shop_ids']);
                }
                
                if($ruleInfo['shop_ids'] && !in_array($shop_id, $ruleInfo['shop_ids'])){
                    $reason[] = '应用规则:' . $ruleInfo['title'] . '(' . $apply_id . ')-不符合指定店铺';
                    continue;
                }
            }
            
            //[非全选]检测择的收货地区
            if($ruleInfo['filter_arr']['province'][0] != '_ALL_'){
                if(!$province or ! in_array($province, $ruleInfo['filter_arr']['province'])){
                    $reason[] = '应用规则:' . $ruleInfo['title'] . '(' . $apply_id . ')-不符合指定收货区域';
                    continue;
                }
            }
            
            //[补发方式]订单完成后，再触发
            if($ruleInfo['trigger_type'] != 'order_complete'){
                $reason[] = '应用规则:'. $ruleInfo['title'] .'('. $apply_id .')-不是补发规则';
                continue;
            }
            
            if(empty($ruleInfo['defer_day']) || $ruleInfo['defer_day'] < 1){
                $reason[] = '应用规则:'. $ruleInfo['title'] .'('. $apply_id .')-延迟天数填写错误';
                continue;
            }
            
            //赠品规则ID
            foreach ($ruleInfo['filter_arr']['id'] as $rule_base_id)
            {
                $ruleBaseIds[$rule_base_id] = $rule_base_id;
            }
            
            $ruleList[$apply_id] = $ruleInfo;
        }
        
        //log
        if(empty($ruleList) || empty($ruleBaseIds)){
            //log
            if($reason){
                $logInfo = implode('<br/>', $reason);
                $logMdl->write_log('order_preprocess@ome', $order_id, $logInfo, time(), $opinfo);
            }
            
            $result['error_msg'] .= '没有符合条件的应用规则;';
            
            return $result;
        }
        
        //赠品发放规则
        $tempList = $ruleBaseMdl->getList('*', array('id'=>$ruleBaseIds));
        if(empty($tempList)){
            //log
            $logInfo = '没有获取到赠品发放规则';
            $logMdl->write_log('order_preprocess@ome', $order_id, $logInfo, time(), $opinfo);
            
            $result['error_msg'] = $logInfo;
            return $result;
        }
        
        //format
        $ruleBaseList = array();
        foreach ($tempList as $val)
        {
            $rule_base_id = $val['id'];
            
            $val['gift_list'] = unserialize($val['gift_list']);
            $val['filter_arr'] = json_decode($val['filter_arr'], true);
            
            //check已被删除
            if($val['disabled'] != 'false') {
                $reason[] = '&nbsp;&nbsp;赠品规则：'. $val['rule_bn'] .'(' . $rule_base_id . ')-该规则已被删除';
                continue;
            }
            
            //check赠送商品
            if(empty($val['gift_list']) || !is_array($val['gift_list'])) {
                $reason[] = '&nbsp;&nbsp;赠品规则：'. $val['rule_bn'] .'(' . $rule_base_id . ')-没有配置赠送商品';
                continue;
            }
            
            $ruleBaseList[$rule_base_id] = $val;
        }
        
        //log
        if(empty($ruleBaseList)){
            //log
            if($reason){
                $logInfo = implode('<br/>', $reason);
                $logMdl->write_log('order_preprocess@ome', $order_id, $logInfo, time(), $opinfo);
            }
            
            $result['error_msg'] = '没有符合条件的赠品规则';
            return $result;
        }
        
        //exec
        $giftList = array();
        $gift_send_log = array();//赠品发送日志
        $last_exclude_flag = 1; //固定只使用一条赠品规则
        $applyInfo = array();
        foreach($ruleList as $ruleKey => $rule)
        {
            $apply_id = $rule['id'];
            
            //check
            if($giftList){
                //上个满足的规则是排他的 忽略后续任何规则
                if($last_exclude_flag == 1) {
                    break;
                }
                
                //上个满足的规则不排他 当前规则排他的话直接跳过
                if($rule['is_exclude'] == 1) {
                    continue;
                }
            }
            
            //是否排他
            //$last_exclude_flag = $rule['is_exclude'];
            $last_exclude_flag = 1; //固定只使用一条赠品规则
            
            //赠品规则
            $reason[] = '执行赠品规则:'. $rule['title'] .'(' . $apply_id .')';
            foreach ($rule['filter_arr']['id'] as $rule_base_id)
            {
                //check赠品规则(只要有一个赠品规则是无效的,就跳过)
                if(empty($ruleBaseList[$rule_base_id])) {
                    break;
                }
                
                $ruleBaseList[$rule_base_id]['rule_id'] = $apply_id;
                
                //仅buygoods中引用
                $suite = 1;
                $msg = '&nbsp;&nbsp;&nbsp;&nbsp;'. $ruleBaseList[$rule_base_id]['rule_bn'] .'('. $rule_base_id .')-';
                
                //验证插件列表
                foreach ($this->crmGiftPlugin as $plugin)
                {
                    //订单审核时赠送了赠品,订单完成后进行延迟补发,每ID是第二次赠送
//                    if($plugin == 'buyernick'){
//                        continue; //不进行验证
//                    }
                    
                    //每天前多少名限量赠送
//                    if($plugin == 'buygoods'){
//                        continue; //不进行验证
//                    }
                    
                    //verify
                    $rs = kernel::single('crm_gift_'. $plugin)->process($ruleBaseList[$rule_base_id], $orderInfo, $suite);
                    if(!$rs[0]) {
                        if(strpos($rs[1], '库存不足') !== false) {
                            $return = array('err'=>1, 'msg'=>$msg . $rs[1]);
                        }
                        
                        $reason[] = $msg . $rs[1];
                        
                        continue 2;
                    } else {
                        if($rs[1]) {
                            $msg .= $rs[1] . '-';
                        }
                    }
                }
                
                //赠送方式
                $reason[] = $msg . '补发赠品通过';
                
                //符合条件的赠品规则
                foreach ($ruleBaseList[$rule_base_id]['gift_list'] as $gift_id => $num)
                {
                    //赠送数量
                    $gift_nums = $num * $suite;
                    
                    //赠送应用
                    $applyInfo = array(
                        'apply_id' => $apply_id,
                        'defer_day' => $rule['defer_day'],
                    );
                    
                    //赠品商品
                    $giftList[$gift_id] += $gift_nums;
                    
                    //赠品发送日志
                    $gift_send_log[$gift_id][$apply_id .'-'. $rule_base_id] = $gift_nums;
                }
            }
        }
        
        //check
        if(empty($giftList)){
            //log
            if($reason){
                $logInfo = implode('<br/>', $reason);
                $logMdl->write_log('order_preprocess@ome', $order_id, $logInfo, time(), $opinfo);
            }
            
            $result['error_msg'] = '没有可执行的赠品规则';
            return $result;
        }
        
        //获取CRM赠品列表
        $giftSdf = array();
        $rule_gift_ids = array();
        $giftGoods = $crmGiftMdl->getList('*', array('gift_id'=>array_keys($giftList)));
        foreach((array)$giftGoods as $giftInfo)
        {
            $gift_id = $giftInfo['gift_id'];
            $gift_bn = $giftInfo['gift_bn'];
            
            //赠送数量
            $gift_num = intval($giftList[$gift_id]);
            
            //记录赠品商品列表
            $giftSdf[$gift_bn] += $gift_num;
            
            //更新已赠送数量
            $sqlstr = '';
            if ($giftInfo['giftset'] == '0') {
                $sqlstr = ', gift_num=gift_num-' . $gift_num;
            }
            $sql = "UPDATE sdb_crm_gift SET send_num=send_num+" . $gift_num . "{$sqlstr} WHERE gift_id=". $gift_id;
            $crmGiftMdl->db->exec($sql);
            
            //记录赠品发送日志
            $log_time = time();
            foreach ($gift_send_log[$gift_id] as $rbid => $num)
            {
                list($apply_id, $rule_base_id) = explode('-', $rbid);
                
                $md5_key = md5($order_bn . $apply_id . $gift_bn . $log_time . $rule_base_id);
                $logSdf = array(
                    'order_source' => $shop_name,
                    'order_bn' => $order_bn,
                    'buyer_account' => $buyer_account,
                    'member_id' => isset($orderInfo['member_id']) ? intval($orderInfo['member_id']) : 0,
                    'shop_id' => $shop_id,
                    'paid_amount' => $payed,
                    'gift_num' => $num,
                    'gift_rule_id' => $apply_id,
                    'rule_base_id' => $rule_base_id,
                    'gift_bn' => $gift_bn,
                    'gift_name' => $giftInfo['gift_name'],
                    'create_time' => $log_time,
                    'md5_key' => $md5_key,
                    'status' => 0,
                );
                $giftLogMdl->save($logSdf);
                
                //赠送记录关系
                $rule_gift_ids[$rule_base_id][$gift_id] = $gift_num;
            }
        }
        
        //check
        if(empty($giftSdf)){
            //log
            if($reason){
                $logInfo = implode('<br/>', $reason);
                $logMdl->write_log('order_preprocess@ome', $order_id, $logInfo, time(), $opinfo);
            }
            
            $result['error_msg'] = '没有可以赠送的商品';
            return $result;
        }
        
        //更新[赠品规则记录 ]已赠送数量
        foreach ($rule_gift_ids as $rule_base_id => $giftItems)
        {
            foreach ($giftItems as $gift_id => $gift_num)
            {
                $update_sql = "UPDATE sdb_crm_gift_rule_logs SET send_num=send_num+". $gift_num .",send_time=". time();
                $update_sql .= " WHERE rule_id=". $rule_base_id ." AND gift_id=". $gift_id;
                $crmGiftMdl->db->exec($update_sql);
            }
        }
        
        //log
        if($reason){
            $logInfo = implode('<br/>', $reason);
            $logMdl->write_log('order_preprocess@ome', $order_id, $logInfo, time(), $opinfo);
        }
        
        //返回结果
        $result['rsp'] = 'succ';
        $result['order_bn'] = $order_bn;
        $result['applyInfo'] = $applyInfo;
        $result['gifts'] = $giftSdf;
        
        return $result;
    }
}