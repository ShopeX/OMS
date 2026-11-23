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
 * 赠品规则记录
 *
 * @author wangbiao@shopex.cn
 * @version v0.1
 */
class crm_ctl_admin_gift_rule_logs extends desktop_controller
{
    public function index()
    {
        $base_filter = array();
        
        $params = array(
            'title' => '赠品规则记录',
            'actions' => array(),
            'base_filter' => $base_filter,
            'orderBy' => 'rule_id DESC',
            'use_buildin_recycle' => false,
            'use_buildin_filter' => true,
            'use_buildin_export' => true,
        );
        
        $this->finder('crm_mdl_gift_rule_logs', $params);
    }
    
    function edit()
    {
        $ruleLogObj = app::get('crm')->model('gift_rule_logs');
        $giftObj = app::get('crm')->model('gift');
        $ruleObj = app::get('crm')->model('gift_rule_base');
        
        $sid = intval($_GET['sid']);
        if(empty($sid)){
            die('无效的操作');
        }
        
        $ruleLogInfo = $ruleLogObj->dump(array('sid'=>$sid), '*');
        if(empty($ruleLogInfo)){
            die('记录不存在');
        }
        
        //规则信息
        $ruleInfo = $ruleObj->dump(array('id'=>$ruleLogInfo['rule_id']), 'title');
        if(empty($ruleInfo)){
            $ruleLogInfo['rule_status'] = 'false';
            
            die('关联规则信息已经被删除,无法编辑');
        }else{
            $ruleLogInfo['rule_status'] = 'true';
            $ruleLogInfo['rule_title'] = $ruleInfo['title'];
        }
        
        //赠品信息
        $productInfo = $giftObj->dump(array('gift_id'=>$ruleLogInfo['gift_id'], 'product_id'=>$ruleLogInfo['product_id']), 'gift_name,is_del');
        if($productInfo['is_del'] == '1'){
            $ruleLogInfo['rule_status'] = 'false';
            
            die('关联赠品信息已经被删除,无法编辑');
        }else{
            $ruleLogInfo['rule_status'] = 'true';
            $ruleLogInfo['gift_name'] = $productInfo['gift_name'];
        }
        
        $this->pagedata['data'] = $ruleLogInfo;
        $this->page('admin/gift/rule_log_edit.html');
    }
    
    /**
     * 保存赠品规则记录设置
     */
    public function save()
    {
        $this->begin('index.php?app=crm&ctl=admin_gift_rule_logs&act=index');
        
        $ruleLogObj = app::get('crm')->model('gift_rule_logs');
        $funcLib = kernel::single('ome_func');
        
        $sid = intval($_POST['sid']);
        $data = array(
                'is_warning' => $_POST['is_warning'],
                'warning_num' => intval($_POST['warning_num']),
                'warning_mobile' => trim($_POST['warning_mobile']),
        );
        
        //check
        if(empty($sid)){
            $this->end(false,'无效的操作');
        }
        
        $ruleLogInfo = $ruleLogObj->dump(array('sid'=>$sid), 'sid');
        if(empty($ruleLogInfo)){
            $this->end(false,'记录不存在');
        }
        
        if(!in_array($data['is_warning'], array('true', 'false'))){
            $this->end(false,'预警设置错误');
        }
        
        if($data['is_warning'] == 'true'){
            if(empty($data['warning_num'])){
                $this->end(false,'预警数量填写错误');
            }
            
            if($data['warning_mobile']){
                $mobileList = array_filter(explode('#', $data['warning_mobile']));
                foreach ($mobileList as $key => $val)
                {
                    $isCheck = $funcLib->isMobile($val);
                    if(!$isCheck){
                        $this->end(false,'手机号：'. $val .' 填写错误');
                    }
                }
                
                $mobileList = array_unique($mobileList);
                if(count($mobileList)>10){
                    $this->end(false,'最多可写10个手机号');
                }
                
                $data['warning_mobile'] = implode('#', $mobileList);
            }
        }
        
        $data['update_time'] = time();
        $result = $ruleLogObj->update($data, array('sid'=>$sid));
        if(!$result){
            $this->end(false,'保存数据失败');
        }
        
        $this->end(true,'设置成功');
    }
}