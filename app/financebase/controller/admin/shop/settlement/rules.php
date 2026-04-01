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
 * 对账规则控制层
 *
 * @author 334395174@qq.com
 * @version 0.1
 */

class financebase_ctl_admin_shop_settlement_rules extends desktop_controller
{

    /**
     * 收支分类列表分栏菜单
     *
     * @param Null
     * @return Array
     */
    public function _views()
    {
        #不是列表时隐藏Tab
        if ($_GET['act'] != 'index') {
            return array();
        }

        $rulesObj = app::get('financebase')->model('bill_category_rules');

        $sub_menu = array(
            0 => array('label' => app::get('base')->_('有效'), 'filter' => array('disabled' => 'false'), 'optional' => false),
            1 => array('label' => app::get('base')->_('无效'), 'filter' => array('disabled' => 'true'), 'optional' => false),
        );

        foreach ($sub_menu as $k => $v) {
            $sub_menu[$k]['filter'] = $v['filter'] ? $v['filter'] : null;
            $sub_menu[$k]['addon']  = $rulesObj->count($v['filter']);
            $sub_menu[$k]['href']   = 'index.php?app=financebase&ctl=admin_shop_settlement_rules&act=index&view=' . $k;
        }

        return $sub_menu;
    }

	// 收支分类列表
	public function index()
	{
        $use_buildin_export    = false;
        $use_buildin_import    = false;
        
        // 根据view设置base_filter
        $base_filter = array();
        $view = isset($_GET['view']) ? intval($_GET['view']) : 0;
        
        if ($view == 0) {
            // 有效
            $base_filter = array('disabled' => 'false');
        } elseif ($view == 1) {
            // 无效
            $base_filter = array('disabled' => 'true');
        }
        
        $actions = array();
        
        // 新增按钮权限控制
        if (kernel::single('desktop_user')->has_permission('shop_settlement_rules_add')) {
            $actions[] = array(
                'label'=>'新增',
                'href'=>'index.php?app=financebase&ctl=admin_shop_settlement_rules&act=setCategory&p[0]=0&singlepage=false&finder_id='.$_GET['finder_id'],
                'target'=>'dialog::{width:550,height:400,resizeable:false,title:\'新增收支分类\'}'
            );
        }

        // 置为无效按钮权限控制 - 只在有效tab中显示
        if (kernel::single('desktop_user')->has_permission('shop_settlement_rules_delete') && $view == 0) {
            $actions[] = array(
                'label'   => '置为无效',
                'confirm' => '你确定要将选中的收支分类置为无效吗？',
                'submit'  => 'index.php?app=financebase&ctl=admin_shop_settlement_rules&act=disableRules',
                'target'  => 'refresh'
            );
        }

        // 恢复按钮权限控制 - 只在无效tab中显示
        if (kernel::single('desktop_user')->has_permission('shop_settlement_rules_restore') && $view == 1) {
            $actions[] = array(
                'label'   => '置为有效',
                'confirm' => '你确定要将选中的收支分类置为有效吗？',
                'submit'  => 'index.php?app=financebase&ctl=admin_shop_settlement_rules&act=restoreRules',
                'target'  => 'refresh'
            );
        }



        $params = array(
            'title'=>'收支分类设置',
            'actions' => $actions,
            'base_filter' => $base_filter,
            'use_buildin_set_tag'=>false,
            'use_buildin_recycle'=>false,
            'use_buildin_filter'=>false,
            'use_buildin_export'=> $use_buildin_export,
            'use_buildin_import'=> $use_buildin_import,
            'orderBy'=>'ordernum',
        );

        $this->finder('financebase_mdl_bill_category_rules',$params);
    }


    // 设置具体类别名称
    public function setCategory($rule_id=0)
    {
        // 检查权限
        if ($rule_id > 0) {
            // 编辑模式
            if (!kernel::single('desktop_user')->has_permission('shop_settlement_rules_edit')) {
                $this->splash('error', null, '您没有编辑收支分类的权限');
            }
        } else {
            // 新增模式
            if (!kernel::single('desktop_user')->has_permission('shop_settlement_rules_add')) {
                $this->splash('error', null, '您没有新增收支分类的权限');
            }
        }
        
        $rule_info = array('rule_id'=>$rule_id);
        $oRules = app::get('financebase')->model("bill_category_rules");
        if($rule_id)
        {
            $rule_info=$oRules->getRow('rule_id,bill_category,ordernum,account_id_plus,account_id_minus,need_ar_verify',array('rule_id'=>$rule_id));
        }else{
            $row = $oRules->getRow('max(ordernum) as max',array());
            $rule_info['ordernum'] = $row ? $row['max'] + 1 : 1;
            $rule_info['need_ar_verify'] = 1; // 默认需要核对
        }

        // 获取会计科目列表 - 只获取有效且排除内置特殊场景科目
        $oAccount = app::get('financebase')->model("account_chart");
        $account_list = $oAccount->getList('id,account,description,postingkey', array('disabled' => 'false', 'account_category' => ''));
        $this->pagedata['account_list'] = $account_list;
        $this->pagedata['rule_info'] = $rule_info;
        $this->display("admin/bill/category.html");
    }

    // 保存具体类别名称
    public function saveCategory()
    {
        $this->begin('index.php?app=financebase&ctl=admin_shop_settlement_rules&act=index');
        
        // 检查权限
        $is_edit = !empty($_POST['rule_id']);
        if ($is_edit) {
            if (!kernel::single('desktop_user')->has_permission('shop_settlement_rules_edit')) {
                $this->end(false, '您没有编辑收支分类的权限');
            }
        } else {
            if (!kernel::single('desktop_user')->has_permission('shop_settlement_rules_add')) {
                $this->end(false, '您没有新增收支分类的权限');
            }
        }
        
        $oRules = app::get('financebase')->model("bill_category_rules");
        $data = array();

        $data['rule_id'] = intval($_POST['rule_id']);
        if(!$data['rule_id'])
        {
            $data['create_time'] = time();
        }

        $data['ordernum'] = intval($_POST['ordernum']);
        $data['bill_category'] = htmlspecialchars($_POST['bill_category']);
        $data['account_id_plus'] = intval($_POST['account_id_plus']);
        $data['account_id_minus'] = intval($_POST['account_id_minus']);
        $data['need_ar_verify'] = intval($_POST['need_ar_verify']);

        // 判断具体类别是否唯一
        if($oRules->isExist(array('bill_category'=>$data['bill_category']),$data['rule_id']))
        {
            $this->end(false, "具体类别有重名");
        }

        // 判断优先级是否唯一
        if($oRules->isExist(array('ordernum'=>$data['ordernum']),$data['rule_id']))
        {
            $this->end(false, app::get('base')->_('优先级已存在'));
        }

        // 获取原始数据用于日志记录
        $existing = null;
        if ($data['rule_id']) {
            $existing = $oRules->dump(array('rule_id' => $data['rule_id']));
        }

        if($oRules->save($data))
        {
            // 记录操作日志和快照
            $action = $data['rule_id'] ? 'edit' : 'add';
            $this->_recordLog($action, $data['rule_id'], $data, $existing);
            $this->end(true, app::get('base')->_('保存成功'));
        }
        else
        {
            $this->end(false, app::get('base')->_('保存失败'));
        }
    }


    /**
     * 添加 OR 编辑 对账规则页面
     * @param  integer    $rule_id       规则ID
     * @param  string     $platform_type 类型
     * 如果rule_id为0时显示添加页面，否则显示编辑页面
     */
    public function setRule($rule_id=0,$platform_type='alipay')
    {
        // $rule_info = array('rule_id'=>$rule_id);
        $oRules = app::get('financebase')->model("bill_category_rules");
        $rule_info=$oRules->getRow('rule_id,business_type,bill_category,rule_content',array('rule_id'=>$rule_id));

        $rule_content = json_decode($rule_info['rule_content'],1);


        $rule_info['rule_content'] = isset($rule_content[$platform_type]) ? $rule_content[$platform_type] : array();
        // if($rule_id)
        // {
        //     $rule_info=$oRules->getRow('rule_id,bill_category,rule_content,ordernum',array('rule_id'=>$rule_id));

        //     $rule_info['rule_content'] = json_decode($rule_info['rule_content'],1);
        // }else{
        //     $row = $oRules->getRow('max(ordernum) as max',array());
        //     $rule_info['ordernum'] = $row ? $row['max'] + 1 : 1;
        //     $rule_info['rule_content'] = array( array( array('rule_type'=>'trade_type','rule_op'=>'and','rule_filter'=>'contain','rule_value'=>'') ) );
        // }

        $oFunc = kernel::single('financebase_func');
        $platform = $oFunc->getShopPlatform();

        $this->pagedata['title'] = $platform[$platform_type]."规则设置";
        $this->pagedata['platform_type'] = $platform_type;
        $this->pagedata['rule_num'] = count($rule_info['rule_content']);
        $this->pagedata['rule_info'] = $rule_info;
        $this->singlepage("admin/bill/rules.html");
    }

    // 保存规则
    public function saveRule()
    {

        $this->begin('index.php?app=financebase&ctl=admin_shop_settlement_rules&act=index');
        $oRules = app::get('financebase')->model("bill_category_rules");

        $rule_content = array();

        if(!$_POST['rule_id'])
        {
            $this->end(false, app::get('base')->_('参数错误'));
        }

        $i = 0;
        if(isset($_POST['rule_op']) && is_array($_POST['rule_op'])){
            foreach ($_POST['rule_op'] as $k => $v) {
                foreach ($v as $k2 => $v2) {
                    $rule_content[$i][$k2] = ['rule_type'=>$_POST['rule_type'][$k][$k2],'rule_value'=>$_POST['rule_value'][$k][$k2],'rule_op'=>$v2,'rule_filter'=>$_POST['rule_filter'][$k][$k2]];
                }
                $i++;
            }
        }

        // 允许保存空规则数据
        // if(!$rule_content)
        // {
        //     $this->end(false, app::get('base')->_('规则不能为空'));
        // }

        $rule_info=$oRules->getRow('rule_id,rule_content',array('rule_id'=>$_POST['rule_id']));

        $rule_info['rule_content'] = json_decode($rule_info['rule_content'],1);
        if(!is_array($rule_info['rule_content'])){
            $rule_info['rule_content'] = array();
        }

        $rule_info['rule_content'][$_POST['platform_type']] =  $rule_content;

        $rule_info['rule_content'] = json_encode($rule_info['rule_content'],JSON_UNESCAPED_UNICODE);

        $rule_info['business_type'] = (string) $_POST['business_type'];
        // // 判断具体类别是否唯一
        // if($oRules->isExist(array('bill_category'=>$data['bill_category']),$data['rule_id']))
        // {
        //     $this->end(false, json_encode($filter));
        // }

        // // 判断优先级是否唯一
        // if($oRules->isExist(array('ordernum'=>$data['ordernum']),$data['rule_id']))
        // {
        //     $this->end(false, app::get('base')->_('优先级已存在'));
        // }

        if($oRules->save($rule_info))
        {
            kernel::single('financebase_data_bill_category_rules')->setRules();
            $this->end(true, app::get('base')->_('保存成功'));
        }
        else
        {
            $this->end(false, app::get('base')->_('保存失败'));
        }
    }

    /**
     * 记录操作日志和快照
     * 
     * @param string $action 操作类型：add/edit/delete/restore
     * @param int $rule_id 规则ID
     * @param array $data 新数据（编辑时使用）
     * @param array $existing 原始数据（编辑时使用）
     */
    private function _recordLog($action, $rule_id, $data = null, $existing = null)
    {
        $omeLogMdl = app::get('ome')->model('operation_log');
        
        // 根据操作类型设置日志参数
        switch ($action) {
            case 'add':
                $log_key = 'bill_category_rules_add@financebase';
                $memo = '新增收支分类';
                break;
            case 'edit':
                $log_key = 'bill_category_rules_edit@financebase';
                $memo = '编辑收支分类';
                break;
            case 'delete':
                $log_key = 'bill_category_rules_delete@financebase';
                $memo = '置为无效收支分类';
                break;
            case 'restore':
                $log_key = 'bill_category_rules_restore@financebase';
                $memo = '恢复收支分类';
                break;
            default:
                return;
        }
        
        // 记录操作日志
        $log_id = $omeLogMdl->write_log($log_key, $rule_id, $memo);
        
        // 生成操作快照（只有编辑时才存储）
        if ($log_id && $action == 'edit' && $existing && $data) {
            $shootMdl = app::get('ome')->model('operation_log_snapshoot');
            
            // 处理快照数据中的会计科目显示格式
            $snapshoot_data = $this->_formatAccountDisplay($existing);
            $updated_data = $this->_formatAccountDisplay($data);
            
            $snapshoot = json_encode($snapshoot_data, JSON_UNESCAPED_UNICODE);
            $updated = json_encode($updated_data, JSON_UNESCAPED_UNICODE);
            $tmp = [
                'log_id' => $log_id, 
                'snapshoot' => $snapshoot,
                'updated' => $updated
            ];
            $shootMdl->insert($tmp);
        }
    }

    /**
     * 格式化会计科目显示格式
     * 
     * @param array $data 数据数组
     * @return array 格式化后的数据数组
     */
    private function _formatAccountDisplay($data)
    {
        if (empty($data['account_id_plus']) && empty($data['account_id_minus'])) {
            return $data;
        }
        
        // 获取会计科目列表
        $oAccount = app::get('financebase')->model("account_chart");
        $account_list = $oAccount->getList('id,account,description,postingkey', array('disabled' => 'false'));
        
        // 使用array_column创建会计科目映射
        $account_map = array();
        foreach ($account_list as $account) {
            $account_map[$account['id']] = '[' . $account['account'] . '-' . $account['postingkey'] . ']' . $account['description'];
        }
        
        // 格式化会计科目显示
        if (!empty($data['account_id_plus']) && isset($account_map[$data['account_id_plus']])) {
            $data['account_id_plus'] = $account_map[$data['account_id_plus']];
        }
        
        if (!empty($data['account_id_minus']) && isset($account_map[$data['account_id_minus']])) {
            $data['account_id_minus'] = $account_map[$data['account_id_minus']];
        }
        
        return $data;
    }

    /**
     * 查看快照
     */
    public function show_history($log_id)
    {
        $logSnapshootMdl = app::get('ome')->model('operation_log_snapshoot');
        $log = $logSnapshootMdl->db_dump(['log_id' => $log_id]);
        $row = json_decode($log['snapshoot'], 1);
        
        if (!$row) {
            $this->splash('error', null, '快照数据不存在');
        }
        
        // 对比数据差异
        $diff_data = array();
        if (!empty($log['updated'])) {
            $updated_data = json_decode($log['updated'], 1);
            $diff_data = $this->_compareRuleData($row, $updated_data);
        }
        
        // 获取会计科目列表 - 只获取有效的科目
        $oAccount = app::get('financebase')->model("account_chart");
        $account_list = $oAccount->getList('id,account,description,postingkey', array('disabled' => 'false'));
        
        $this->pagedata['rule_info'] = $row;
        $this->pagedata['diff_data'] = $diff_data;
        $this->pagedata['account_list'] = $account_list;
        $this->pagedata['history'] = true;
        $this->singlepage("admin/bill/category.html");
    }
    
    /**
     * 对比规则数据差异
     */
    private function _compareRuleData($old_data, $new_data)
    {
        $diff_data = array();
        
        // 需要对比的字段
        $compare_fields = array('bill_category', 'ordernum', 'account_id_plus', 'account_id_minus', 'need_ar_verify');
        
        foreach ($compare_fields as $field) {
            $old_value = isset($old_data[$field]) ? $old_data[$field] : '';
            $new_value = isset($new_data[$field]) ? $new_data[$field] : '';
            
            // 特殊处理AR核对状态字段
            if ($field == 'need_ar_verify') {
                $old_value = $old_value == 1 ? '需核对AR' : '无需核对AR';
                $new_value = $new_value == 1 ? '需核对AR' : '无需核对AR';
            }
            
            // 特殊处理会计科目字段
            if (in_array($field, array('account_id_plus', 'account_id_minus'))) {
                $old_value = $old_value ?: '未设置';
                $new_value = $new_value ?: '未设置';
            }
            
            // 只有有变化时才添加到diff_data
            if ($old_value != $new_value) {
                $diff_data[$field] = array(
                    'old_value' => $old_value,
                    'new_value' => $new_value
                );
            }
        }
        
        return $diff_data;
    }

    // 置为无效收支分类
    public function disableRules()
    {
        $this->begin('index.php?app=financebase&ctl=admin_shop_settlement_rules&act=index');
        
        // 检查置为无效权限
        if (!kernel::single('desktop_user')->has_permission('shop_settlement_rules_delete')) {
            $this->end(false, '您没有置为无效收支分类的权限');
        }
        
        if($_POST['isSelectedAll'] == '_ALL_'){
            $this->end(false,'不支持全选置为无效');
        }
        
        if(empty($_POST['rule_id'])){
            $this->end(false,'请选择要置为无效的收支分类');
        }
        
        $oRules = app::get('financebase')->model("bill_category_rules");
        
        foreach($_POST['rule_id'] as $rule_id){
            if($rule_id){
                // 获取收支分类信息
                $rule_info = $oRules->dump(array('rule_id'=>$rule_id), 'bill_category');
                if(!$rule_info){
                    $this->end(false, '收支分类不存在');
                }
                
                $rule_name = $rule_info['bill_category'];
                
                // 执行软删除 - 将disabled设置为true
                if(!$oRules->update(array('disabled' => 'true'), array('rule_id'=>$rule_id))){
                    $this->end(false, "置为无效收支分类【{$rule_name}】失败");
                }
                
                // 记录操作日志
                $this->_recordLog('delete', $rule_id);
            }
        }
        
        $this->end(true, '置为无效成功');
    }

    // 恢复收支分类
    public function restoreRules()
    {
        $this->begin('index.php?app=financebase&ctl=admin_shop_settlement_rules&act=index');
        
        // 检查恢复权限
        if (!kernel::single('desktop_user')->has_permission('shop_settlement_rules_restore')) {
            $this->end(false, '您没有恢复收支分类的权限');
        }
        
        if($_POST['isSelectedAll'] == '_ALL_'){
            $this->end(false,'不支持全选恢复');
        }
        
        if(empty($_POST['rule_id'])){
            $this->end(false,'请选择要恢复的收支分类');
        }
        
        $oRules = app::get('financebase')->model("bill_category_rules");
        
        foreach($_POST['rule_id'] as $rule_id){
            if($rule_id){
                // 获取收支分类信息
                $rule_info = $oRules->dump(array('rule_id'=>$rule_id), 'bill_category');
                if(!$rule_info){
                    $this->end(false, '收支分类不存在');
                }
                
                $rule_name = $rule_info['bill_category'];
                
                // 执行恢复 - 将disabled设置为false
                if(!$oRules->update(array('disabled' => 'false'), array('rule_id'=>$rule_id))){
                    $this->end(false, "恢复收支分类【{$rule_name}】失败");
                }
                
                // 记录操作日志
                $this->_recordLog('restore', $rule_id);
            }
        }
        
        $this->end(true, '置为有效成功');
    }


}
