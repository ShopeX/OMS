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
 * Created by PhpStorm.
 * User: yaokangming
 * Date: 2019/6/28
 * Time: 9:30
 */

class crm_gift_sendgoods
{
    public function process(&$ruleBase, $sdf, $suite) {
        $error_msg_giftset = '';
        if ($ruleBase["filter_arr"]["sendgoods"]["type"] == 2) {
            //选择 “选择” 在库存不足的情况下会多次匹配
            $sendgoods_content_arr = $this->get_sendgoods_content_arr($ruleBase['gift_list'], $ruleBase["filter_arr"]["sendgoods"]["content"]);
            if (empty($sendgoods_content_arr)) {return [false, '没有找到选择的赠品'];}
            foreach ($sendgoods_content_arr as $var_hca) {
                $check_rs = $this->check_giftset_by_rule($var_hca, $error_msg_giftset);
                if(!$check_rs){
                    continue; //不符合条件则跳过
                }
                
                //检查赠品规则记录
                $error_msg = '';
                $check_rs = $this->check_gift_rule_logs($ruleBase, $var_hca, $error_msg);
                
                //符合的规则
                $ruleBase['gift_list'] = $var_hca;
                
                return [true];
            }
        } else {
            $check_rs = $this->check_giftset_by_rule($ruleBase['gift_list'], $error_msg_giftset);
            if(!$check_rs){
                return array(false, '赠品检查失败：' .$error_msg_giftset);
            }
            
            //检查赠品规则记录
            $error_msg = '';
            $check_rs = $this->check_gift_rule_logs($ruleBase, $ruleBase['gift_list'], $error_msg);
            
            //符合的规则
            return [true];
        }
        
        return array(false, '库存不足：' .$error_msg_giftset);
    }


    /*
     * 获取赠送方式是“选择”类型的匹配数组
     * $gift_list 全部表格中勾选的赠品
     * $handling_content 输入框中填写的内容  “|”表示“或”；“#”表示“和”
     */
    private function get_sendgoods_content_arr($gift_list, $handling_content)
    {
        //赠品id和数量的关系数组
        $rl_gift_id_num = $gift_list;
        $mdl_crm_gift = app::get('crm')->model('gift'); //这里统一用gift_id作为数组的key值，用bn的话可能出现大小写没匹配上的情况。
        //获取“选择”的输入框设置内容
        $arr_handling_content_or = explode("|", $handling_content);
        $return_arr              = array();
        foreach ($arr_handling_content_or as $var_hco) {
            if (strpos($handling_content, "#")) {
                //存在“和”关系 有多个gift_bn
                $inner_ids_arr            = array();
                $inner_num_arr            = array();
                $arr_handling_content_and = explode("#", $var_hco);
                $tmpReturn = [];
                foreach ($arr_handling_content_and as $var_hca) {
                    $rs_one_gift = $mdl_crm_gift->dump(array("gift_bn" => $var_hca), "gift_id");
                    if (isset($rl_gift_id_num[$rs_one_gift["gift_id"]])) {
                        $tmpReturn[$rs_one_gift["gift_id"]] = $rl_gift_id_num[$rs_one_gift["gift_id"]];
                    }
                }
                if (!empty($tmpReturn)) {
                    //正常情况的 并且都能通过gift_bn匹配到gift_id
                    $return_arr[] = $tmpReturn;
                }
            } else {
                //单个gift_bn
                $rs_one_gift = $mdl_crm_gift->dump(array("gift_bn" => $var_hco), "gift_id");
                if (isset($rl_gift_id_num[$rs_one_gift["gift_id"]])) {
                    $return_arr[] = array($rs_one_gift["gift_id"] => $rl_gift_id_num[$rs_one_gift["gift_id"]]);
                }
            }
        }
        return $return_arr;
    }

    /*
     * 以一条规则为单位 依据赠品数量设置 判断是否有完成赠送的数量
     * $gift_list 当前rule的gift_ids对应的gift_num
     * $error_msg_giftset 失败信息
     */
    private function check_giftset_by_rule($gift_list, &$error_msg_giftset)
    {
        $stockLib  = kernel::single('material_sales_material_stock');
        $rs        = app::get('crm')->model('gift')->getList('gift_id,gift_bn,gift_name,is_yujing,yj_num,yj_mobile,gift_num,giftset,product_id', array('gift_id' => array_keys($gift_list)));
        foreach ($rs as $v) {
            $gift_num = $gift_list[$v['gift_id']];
            if ($v['giftset'] == '0') {
                //指定数量
                $left_num = $v['gift_num'] - $gift_num;
                if($v['is_yujing'] == 'true' && $left_num <= $v['yj_num']) {
                    $this->crmGiftSendSms($v['gift_name'], $v['yj_mobile']);
                }
                //当前指定数量的值小于等于0 或者 剩余数量小于0
                if ($v['gift_num'] <= 0 || $left_num < 0) {
                    $error_msg_giftset .= $v['gift_bn'] . '库存不足;';
                    return false;
                }
            }
            if ($v['giftset'] == '2') {
                //实际库存数量
                $store = $this->getSalesMStock($v['product_id']);
                $left_num = $store-$gift_num;
                if($v['is_yujing'] == 'true' && $left_num <= $v['yj_num']) {
                    $this->crmGiftSendSms($v['gift_name'], $v['yj_mobile']);
                }
                if ($store < $gift_num) {
                    $error_msg_giftset .= $v['gift_bn'] . '可用库存不足;';
                    return false;
                }
            }
        }
        return true;
    }

    public function crmGiftSendSms($giftName, $mobile) {
        foreach (explode('#', $mobile) as $telephone) {
            $errorMsg = '';
            kernel::single('taoexlib_sms')->sendSms(array('event_type'=>'crmgift','gift_name'=>$giftName,'telephone'=>$telephone), $errorMsg);
        }
    }
    
    /**
     * 检查赠品规则记录
     * 
     * @param int $rule_id
     * @param array $gift_ids
     * @param string $error_msg
     * @return bool
     */
    public function check_gift_rule_logs($ruleBaseInfo, $gift_ids, &$error_msg=null)
    {
        $ruleLogObj = app::get('crm')->model('gift_rule_logs');
        
        $rule_id = $ruleBaseInfo['id'];
        $gift_list = $ruleBaseInfo['gift_list'];
        
        //赠品规则记录
        $ruleLogList = $ruleLogObj->getList('*', array('rule_id'=>$rule_id, 'gift_id'=>array_keys($gift_ids)));
        if(empty($ruleLogList)){
            return true;
        }
        
        //检查预警数量
        foreach ($ruleLogList as $key => $val)
        {
            $gift_id = $val['gift_id'];
            $warning_num = intval($val['warning_num']);
            $send_num = intval($val['send_num']);
            
            if($val['is_warning'] == 'false'){
                continue;
            }
            
            if(empty($warning_num)){
                continue;
            }
            
            //本次要赠送的数量
            $gift_num = intval($gift_list[$gift_id]);
            $send_num += $gift_num;
            
            if($send_num > $warning_num){
                $giftObj = app::get('crm')->model('gift');
                $giftInfo = $giftObj->dump(array('gift_id'=>$gift_id), 'gift_name');
                
                //短信提醒
                if($val['warning_mobile']){
                    $sms_msg = '[赠品规则预警]'.$giftInfo['gift_name'];
                    $this->crmGiftSendSms($sms_msg, $val['warning_mobile']);
                }
                
                $error_msg = sprintf('赠品规则[%s],赠送货品[%s],达到预警数量[%d]', $ruleBaseInfo['id'], $giftInfo['gift_name'], $warning_num);
                
                return false;
            }
        }
        
        return true;
    }

    public function getSalesMStock($sm_id){
        $salesMaterialObj = app::get('material')->model('sales_material');
        $salesBasicMaterialObj = app::get('material')->model('sales_basic_material');
        $basicMaterialStockObj = app::get('material')->model('basic_material_stock');
        $salesMaterialInfo = $salesMaterialObj->getList('sm_id,shop_id,is_bind,sales_material_type',array('sm_id'=>$sm_id), 0, 1);

        if($salesMaterialInfo){
            //已绑定的才有映射关系有库存
            if($salesMaterialInfo[0]['is_bind'] == 1){
                $salesBasicBindInfo = $salesBasicMaterialObj->getList('sm_id,bm_id,number',array('sm_id'=>$sm_id), 0, -1);

                foreach($salesBasicBindInfo as $k => $bindInfo){
                    $bm_ids[] = $bindInfo['bm_id'];
                    $bm_combine_items[$bindInfo['bm_id']] = $bindInfo['number'];
                }
                $bmStoreInfo = $this->getEcMaterialStock($bm_ids);
                $bmStoreInfo = array_column($bmStoreInfo,null,'product_id');
                foreach($bm_ids as $v){
                    $storeInfo = $bmStoreInfo[$v];
                    $tmp_abled_store =  ($storeInfo['store']-$storeInfo['store_freeze']) > 0 ? ($storeInfo['store']-$storeInfo['store_freeze']) : 0;
                    $bm_bind_abled_store[] = $tmp_abled_store > 0 ? floor($tmp_abled_store/$bm_combine_items[$storeInfo['product_id']]) : 0;

                }
               
                //升序排列
                sort($bm_bind_abled_store);

                return $bm_bind_abled_store[0];
            }
        }
        return 0;
    }


    public function getEcMaterialStock($bm_ids){
        
        // 获取main仓库的ID列表
        $mdl_ome_branch = app::get('ome')->model('branch');
        $branchList = $mdl_ome_branch->db->select('SELECT branch_id FROM sdb_ome_branch WHERE type=\'main\' and b_type=1');
        $main_branch_ids = array();
        if(!empty($branchList)){
            foreach($branchList as $var_branch){
                $main_branch_ids[] = $var_branch["branch_id"];
            }
        }
        
        // 获取main仓库在branch_product表中的store总和
        $main_filter_str = "product_id in(".implode(",", $bm_ids).") and store_id=0";
        if(!empty($main_branch_ids)){
            $main_filter_str.= " and branch_id in(".implode(",", $main_branch_ids).")";
        }
        $main_sql = "SELECT SUM(store) as store, product_id FROM sdb_ome_branch_product WHERE ".$main_filter_str.' group by product_id';
        $main_products = kernel::database()->select($main_sql);
        
        // 将main仓库的store按product_id索引
        $main_store_map = array();
        foreach($main_products as $product){
            $main_store_map[$product['product_id']] = intval($product['store']);
        }
        
        // 获取非main仓库的store_freeze总和
        $non_main_filter_str = "product_id in(".implode(",", $bm_ids).") and store_id=0";
        if(!empty($main_branch_ids)){
            $non_main_filter_str.= " and branch_id not in(".implode(",", $main_branch_ids).")";
        }
        $non_main_sql = "SELECT SUM(store_freeze) as store_freeze, product_id FROM sdb_ome_branch_product WHERE ".$non_main_filter_str.' group by product_id';
        $non_main_products = kernel::database()->select($non_main_sql);
        
        // 将非main仓库的store_freeze按product_id索引
        $non_main_store_freeze_map = array();
        foreach($non_main_products as $product){
            $non_main_store_freeze_map[$product['product_id']] = intval($product['store_freeze']);
        }
        
        // 获取storeFromRedis中的store_freeze
        $products = array();
        foreach($bm_ids as $bm_id){
            list($success, $msg, $stock_data) = material_basic_material_stock::storeFromRedis(array('bm_id' => $bm_id));
            
            if($success && !empty($stock_data)){
                $redis_store_freeze = intval($stock_data['store_freeze']);
                $main_branch_store = isset($main_store_map[$bm_id]) ? $main_store_map[$bm_id] : 0;
                $non_main_store_freeze = isset($non_main_store_freeze_map[$bm_id]) ? $non_main_store_freeze_map[$bm_id] : 0;
                
                // 计算总可用库存 = main仓库在表branch_product中的store总和 - storeFromRedis中的store_freeze + 非main仓库在表branch_product中的store_freeze总和
                $total_store = $main_branch_store - $redis_store_freeze + $non_main_store_freeze;
                
                $products[] = array(
                    'product_id' => $bm_id,
                    'store' => $total_store > 0 ? $total_store : 0,
                    'store_freeze' => 0  // 已经在计算中扣除了，这里返回0
                );
            }
        }
        
        return $products;
    }
}