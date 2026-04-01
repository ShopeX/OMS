<?php
/**
 * 会计科目控制层
 *
 * @author 334395174@qq.com
 * @version 0.1
 */

class financebase_ctl_admin_shop_settlement_account_chart extends desktop_controller
{

    /**
     * 会计科目列表分栏菜单
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

        $accountChartObj = app::get('financebase')->model('account_chart');

        $sub_menu = array(
            0 => array('label' => app::get('base')->_('有效'), 'filter' => array('disabled' => 'false'), 'optional' => false),
            1 => array('label' => app::get('base')->_('无效'), 'filter' => array('disabled' => 'true'), 'optional' => false),
            // 2 => array('label' => app::get('base')->_('全部'), 'optional' => false),
        );

        foreach ($sub_menu as $k => $v) {
            $sub_menu[$k]['filter'] = $v['filter'] ? $v['filter'] : null;
            $sub_menu[$k]['addon']  = $accountChartObj->count($v['filter']);
            $sub_menu[$k]['href']   = 'index.php?app=financebase&ctl=admin_shop_settlement_account_chart&act=index&view=' . $k;
        }

        return $sub_menu;
    }

    // 会计科目列表
    public function index()
    {
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
        // view == 2 是全部，不需要设置base_filter
        
        $actions = array();
        
        // 新增按钮权限控制
        if (kernel::single('desktop_user')->has_permission('shop_settlement_account_chart_add')) {
            $actions[] = array(
                'label'=>'新增',
                'href'=>'index.php?app=financebase&ctl=admin_shop_settlement_account_chart&act=setAccount&p[0]=0&singlepage=false&finder_id='.$_GET['finder_id'],
                'target'=>'dialog::{width:550,height:520,resizeable:false,title:\'新增会计科目\'}'
            );
        }

        // 置为无效按钮权限控制 - 只在有效tab中显示
        if (kernel::single('desktop_user')->has_permission('shop_settlement_account_chart_delete') && $view == 0) {
            $actions[] = array(
                'label'   => '置为无效',
                'confirm' => '你确定要将选中的会计科目置为无效吗？',
                'submit'  => 'index.php?app=financebase&ctl=admin_shop_settlement_account_chart&act=disableAccount',
                'target'  => 'refresh'
            );
        }

        // 恢复按钮权限控制 - 只在无效tab中显示
        if (kernel::single('desktop_user')->has_permission('shop_settlement_account_chart_restore') && $view == 1) {
            $actions[] = array(
                'label'   => '置为有效',
                'confirm' => '你确定要将选中的会计科目置为有效吗？',
                'submit'  => 'index.php?app=financebase&ctl=admin_shop_settlement_account_chart&act=restoreAccount',
                'target'  => 'refresh'
            );
        }

        $params = array(
            'title'=>'会计科目设置',
            'actions' => $actions,
            'base_filter' => $base_filter,
            'use_buildin_set_tag'=>false,
            'use_buildin_recycle'=>false,
            'use_buildin_filter'=>false,
            'use_buildin_export'=> false,
            'use_buildin_import'=> false,
            'orderBy'=>'id',
        );

        $this->finder('financebase_mdl_account_chart',$params);
    }

    // 设置会计科目
    public function setAccount($id=0)
    {
        // 检查权限
        if ($id > 0) {
            // 编辑模式
            if (!kernel::single('desktop_user')->has_permission('shop_settlement_account_chart_edit')) {
                $this->splash('error', null, '您没有编辑会计科目的权限');
            }
        } else {
            // 新增模式
            if (!kernel::single('desktop_user')->has_permission('shop_settlement_account_chart_add')) {
                $this->splash('error', null, '您没有新增会计科目的权限');
            }
        }
        
        $account_info = array('id'=>$id);
        $oAccount = app::get('financebase')->model("account_chart");
        // 检查已存在的应用场景
        $existing_categories = array();
        if($id){
            $account_info=$oAccount->db_dump(array('id'=>$id));

            // 编辑模式：排除当前记录
            $platform_exists = $oAccount->count(array('account_category' => 'platform_amount', 'id|noequal' => $id));
            $actually_exists = $oAccount->count(array('account_category' => 'actually_amount', 'id|noequal' => $id));
            $refund_platform_exists = $oAccount->count(array('account_category' => 'refundplatform_amount', 'id|noequal' => $id));
            $refund_actually_exists = $oAccount->count(array('account_category' => 'refundactually_amount', 'id|noequal' => $id));
        } else {
            // 新增模式：检查所有记录
            $platform_exists = $oAccount->count(array('account_category' => 'platform_amount'));
            $actually_exists = $oAccount->count(array('account_category' => 'actually_amount'));
            $refund_platform_exists = $oAccount->count(array('account_category' => 'refundplatform_amount'));
            $refund_actually_exists = $oAccount->count(array('account_category' => 'refundactually_amount'));
        }
        
        if($platform_exists > 0) {
            $existing_categories[] = 'platform_amount';
        }
        if($actually_exists > 0) {
            $existing_categories[] = 'actually_amount';
        }
        if($refund_platform_exists > 0) {
            $existing_categories[] = 'refundplatform_amount';
        }
        if($refund_actually_exists > 0) {
            $existing_categories[] = 'refundactually_amount';
        }

        $this->pagedata['account_info'] = $account_info;
        $this->pagedata['existing_categories'] = $existing_categories;
        $this->display("admin/account/chart.html");
    }

    // 保存会计科目
    public function saveAccount()
    {
        $this->begin('index.php?app=financebase&ctl=admin_shop_settlement_account_chart&act=index');
        
        // 检查权限
        $is_edit = !empty($_POST['id']);
        if ($is_edit) {
            if (!kernel::single('desktop_user')->has_permission('shop_settlement_account_chart_edit')) {
                $this->end(false, '您没有编辑会计科目的权限');
            }
        } else {
            if (!kernel::single('desktop_user')->has_permission('shop_settlement_account_chart_add')) {
                $this->end(false, '您没有新增会计科目的权限');
            }
        }
        
        $oAccount = app::get('financebase')->model("account_chart");
        $data = array();

        $data['id'] = intval($_POST['id']);
        
        // 获取原始数据用于日志记录（仅编辑时需要）
        $existing = null;
        if ($is_edit) {
            $existing = $oAccount->dump(array('id' => $data['id']));
        }
        
        // 生成会计科目编码的逻辑
        // 新增时自动生成，编辑时如果现有数据没有编码也生成
        $need_generate_code = !$is_edit || empty($existing['account_code']);
        
        if ($need_generate_code) {
            // 获取最后一条数据的account_code
            $sql = "SELECT account_code FROM sdb_financebase_account_chart WHERE disabled = 'false' ORDER BY account_code DESC";
            $last_account = $oAccount->db->selectrow($sql);
            
            if ($last_account && !empty($last_account['account_code'])) {
                // 提取数字部分并加1
                $last_num = intval(substr($last_account['account_code'], 4));
                $new_num = $last_num + 1;
                $data['account_code'] = 'KJKM' . str_pad($new_num, 3, '0', STR_PAD_LEFT);
            } else {
                // 第一条数据
                $data['account_code'] = 'KJKM001';
            }
        }

        $data['account'] = htmlspecialchars(trim($_POST['account']));
        $data['description'] = htmlspecialchars(trim($_POST['description']));
        $data['postingkey'] = htmlspecialchars(trim($_POST['postingkey']));
        $data['customer'] = htmlspecialchars(trim($_POST['customer']));
        $data['costcenter'] = htmlspecialchars(trim($_POST['costcenter']));
        
        // 验证会计科目和客户至少填写一项
        if (empty($data['account']) && empty($data['customer'])) {
            $this->end(false, "会计科目 和 客户 至少填写一项");
        }
        if (($_POST['tax_rate'])!=='') { 
            $data['tax_rate'] = $_POST['tax_rate'];
        }
        $data['tax_account'] = htmlspecialchars(trim($_POST['tax_account']));
        $data['account_category'] = trim($_POST['account_category']);
        $data['reason_code'] = trim($_POST['reason_code']);
        // 验证税率和税率会计科目的关联关系
        if (!empty($data['tax_rate']) && $data['tax_rate']>0 && empty($data['tax_account'])) {
            $this->end(false, "如果有税率，税率会计科目为必填项");
        }

        // 编辑模式：如果设置了应用场景，需要检查该会计科目是否已被使用
        if ($is_edit && !empty($data['account_category']) && in_array($data['account_category'], array('platform_amount', 'actually_amount', 'refundplatform_amount', 'refundactually_amount'))) {
            // 检查收支分类是否使用了该科目
            $oRules = app::get('financebase')->model("bill_category_rules");
            $rules_used = $oRules->getList('bill_category', array(
                'account_id_plus' => $data['id']
            ));
            if(empty($rules_used)){
                $rules_used = $oRules->getList('bill_category', array(
                    'account_id_minus' => $data['id']
                ));
            }
            
            if(!empty($rules_used)){
                $used_categories = array_column($rules_used, 'bill_category');
                $this->end(false, "会计科目【{$data['account']}】已被收支分类【" . implode('、', $used_categories) . "】使用，不得设置应用场景");
            }
            
            // 检查差异类型是否使用了该科目
            $oGap = app::get('financebase')->model("gap");
            $gap_used = $oGap->getList('gap_name', array(
                'account_id_plus' => $data['id']
            ));
            if(empty($gap_used)){
                $gap_used = $oGap->getList('gap_name', array(
                    'account_id_minus' => $data['id']
                ));
            }
            
            if(!empty($gap_used)){
                $used_gaps = array_column($gap_used, 'gap_name');
                $this->end(false, "会计科目【{$data['account']}】已被差异类型【" . implode('、', $used_gaps) . "】使用，不得设置应用场景");
            }
        }
        
        // 验证应收分类的唯一性
        if (!empty($data['account_category']) && in_array($data['account_category'], array('platform_amount', 'actually_amount', 'refundplatform_amount', 'refundactually_amount'))) {
            $filter = array('account_category' => $data['account_category']);
            if ($data['id']) {
                $filter['id|noequal'] = $data['id'];
            }
            if ($oAccount->count($filter) > 0) {
                $category_names = array(
                    'platform_amount' => '平台承担',
                    'actually_amount' => '客户实付',
                    'refundplatform_amount' => '平台补贴退',
                    'refundactually_amount' => '客户应退'
                );
                $category_name = $category_names[$data['account_category']];
                $this->end(false, $category_name . "只能有一条数据");
            }
        }

        // 注释：已去掉会计科目+记账码组合的唯一性判断
        // 允许相同的会计科目+记账码组合存在

        if($oAccount->save($data))
        {
            // 记录操作日志和快照
            $action = $is_edit ? 'edit' : 'add';
            $this->_recordLog($action, $data['id'], $data, $existing);
            $this->end(true, app::get('base')->_('保存成功'));
        }
        else
        {
            $this->end(false, app::get('base')->_('保存失败'));
        }
    }

    // 置为无效会计科目
    public function disableAccount()
    {
        $this->begin('index.php?app=financebase&ctl=admin_shop_settlement_account_chart&act=index');
        
        // 检查置为无效权限
        if (!kernel::single('desktop_user')->has_permission('shop_settlement_account_chart_delete')) {
            $this->end(false, '您没有置为无效会计科目的权限');
        }
        
        if($_POST['isSelectedAll'] == '_ALL_'){
            $this->end(false,'不支持全选置为无效');
        }
        
        if(empty($_POST['id'])){
            $this->end(false,'请选择要置为无效的会计科目');
        }
        
        $oAccount = app::get('financebase')->model("account_chart");
        $oRules = app::get('financebase')->model("bill_category_rules");
        $oGap = app::get('financebase')->model("gap");
        
        foreach($_POST['id'] as $account_id){
            if($account_id){
                // 获取会计科目信息
                $account_info = $oAccount->dump(array('id'=>$account_id), 'account');
                if(!$account_info){
                    $this->end(false, '会计科目不存在');
                }
                
                $account_name = $account_info['account'];
                
                // 检查收支分类是否使用了该科目
                $rules_used = $oRules->getList('bill_category', array(
                    'account_id_plus' => $account_id
                ));
                if(empty($rules_used)){
                    $rules_used = $oRules->getList('bill_category', array(
                        'account_id_minus' => $account_id
                    ));
                }
                
                if(!empty($rules_used)){
                    $used_categories = array_column($rules_used, 'bill_category');
                    $this->end(false, "会计科目【{$account_name}】已被收支分类【" . implode('、', $used_categories) . "】使用，无法删除");
                }
                
                // 检查差异类型是否使用了该科目
                $gap_used = $oGap->getList('gap_name', array(
                    'account_id_plus' => $account_id
                ));
                if(empty($gap_used)){
                    $gap_used = $oGap->getList('gap_name', array(
                        'account_id_minus' => $account_id
                    ));
                }
                
                if(!empty($gap_used)){
                    $used_gaps = array_column($gap_used, 'gap_name');
                    $this->end(false, "会计科目【{$account_name}】已被差异类型【" . implode('、', $used_gaps) . "】使用，无法置为无效");
                }
                
                // 执行软删除 - 将disabled设置为true
                if(!$oAccount->update(array('disabled' => 'true'), array('id'=>$account_id))){
                    $this->end(false, "置为无效会计科目【{$account_name}】失败");
                }
                
                // 记录操作日志
                $this->_recordLog('delete', $account_id);
            }
        }
        
        $this->end(true, '置为无效成功');
    }

    // 恢复会计科目
    public function restoreAccount()
    {
        $this->begin('index.php?app=financebase&ctl=admin_shop_settlement_account_chart&act=index');
        
        // 检查恢复权限
        if (!kernel::single('desktop_user')->has_permission('shop_settlement_account_chart_restore')) {
            $this->end(false, '您没有恢复会计科目的权限');
        }
        
        if($_POST['isSelectedAll'] == '_ALL_'){
            $this->end(false,'不支持全选恢复');
        }
        
        if(empty($_POST['id'])){
            $this->end(false,'请选择要恢复的会计科目');
        }
        
        $oAccount = app::get('financebase')->model("account_chart");
        
        foreach($_POST['id'] as $account_id){
            if($account_id){
                // 获取会计科目信息
                $account_info = $oAccount->dump(array('id'=>$account_id), 'account');
                if(!$account_info){
                    $this->end(false, '会计科目不存在');
                }
                
                $account_name = $account_info['account'];
                
                // 执行恢复 - 将disabled设置为false
                if(!$oAccount->update(array('disabled' => 'false'), array('id'=>$account_id))){
                    $this->end(false, "恢复会计科目【{$account_name}】失败");
                }
                
                // 记录操作日志
                $this->_recordLog('restore', $account_id);
            }
        }
        
        $this->end(true, '置为有效成功');
    }

    /**
     * 记录操作日志
     * 
     * @param string $action 操作类型：add/edit/delete/restore
     * @param int $account_id 会计科目ID
     * @param array $data 新数据（编辑时需要）
     * @param array $existing 原始数据（编辑时需要）
     */
    private function _recordLog($action, $account_id, $data = null, $existing = null)
    {
        $omeLogMdl = app::get('ome')->model('operation_log');
        
        // 根据操作类型设置日志参数
        switch ($action) {
            case 'add':
                $log_key = 'account_chart_add@financebase';
                $memo = '新增会计科目';
                break;
            case 'edit':
                $log_key = 'account_chart_edit@financebase';
                $memo = '编辑会计科目';
                break;
            case 'delete':
                $log_key = 'account_chart_delete@financebase';
                $memo = '置为无效会计科目';
                break;
            case 'restore':
                $log_key = 'account_chart_restore@financebase';
                $memo = '恢复会计科目';
                break;
            default:
                return;
        }
        
        // 记录操作日志
        $log_id = $omeLogMdl->write_log($log_key, $account_id, $memo);
        
        // 生成操作快照（只有编辑时才存储）
        if ($log_id && $action == 'edit' && $existing && $data) {
            $shootMdl = app::get('ome')->model('operation_log_snapshoot');
            $snapshoot = json_encode($existing, JSON_UNESCAPED_UNICODE);
            $updated = json_encode($data, JSON_UNESCAPED_UNICODE);
            $tmp = [
                'log_id' => $log_id, 
                'snapshoot' => $snapshoot,
                'updated' => $updated
            ];
            $shootMdl->insert($tmp);
        }
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
            $diff_data = $this->_compareAccountData($row, $updated_data);
        }
        
        // 检查已存在的应用场景（用于显示当前状态）
        $existing_categories = array();
        $oAccount = app::get('financebase')->model("account_chart");
        $platform_exists = $oAccount->count(array('account_category' => 'platform_amount', 'id|noequal' => $row['id']));
        $actually_exists = $oAccount->count(array('account_category' => 'actually_amount', 'id|noequal' => $row['id']));
        $refund_platform_exists = $oAccount->count(array('account_category' => 'refundplatform_amount', 'id|noequal' => $row['id']));
        $refund_actually_exists = $oAccount->count(array('account_category' => 'refundactually_amount', 'id|noequal' => $row['id']));
        
        if($platform_exists > 0) {
            $existing_categories[] = 'platform_amount';
        }
        if($actually_exists > 0) {
            $existing_categories[] = 'actually_amount';
        }
        if($refund_platform_exists > 0) {
            $existing_categories[] = 'refundplatform_amount';
        }
        if($refund_actually_exists > 0) {
            $existing_categories[] = 'refundactually_amount';
        }
        
        $this->pagedata['account_info'] = $row;
        $this->pagedata['diff_data'] = $diff_data;
        $this->pagedata['existing_categories'] = $existing_categories;
        $this->pagedata['history'] = true;
        $this->singlepage("admin/account/chart.html");
    }
    
    /**
     * 对比会计科目数据差异
     */
    private function _compareAccountData($old_data, $new_data)
    {
        $diff_data = array();
        
        // 需要对比的字段
        $compare_fields = array('account', 'description', 'postingkey', 'customer', 'costcenter', 'tax_rate', 'tax_account', 'account_category', 'reason_code');
        
        foreach ($compare_fields as $field) {
            $old_value = isset($old_data[$field]) ? $old_data[$field] : '';
            $new_value = isset($new_data[$field]) ? $new_data[$field] : '';
            
            // 特殊处理应收分类字段
            if ($field == 'account_category') {
                $category_names = array(
                    'platform_amount' => '平台承担',
                    'actually_amount' => '客户实付',
                    'refundplatform_amount' => '平台补贴退',
                    'refundactually_amount' => '客户应退'
                );
                
                $old_value = isset($category_names[$old_value]) ? $category_names[$old_value] : ($old_value ?: '未设置');
                $new_value = isset($category_names[$new_value]) ? $category_names[$new_value] : ($new_value ?: '未设置');
            }
            
            // 特殊处理税率字段
            if ($field == 'tax_rate') {
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
}
