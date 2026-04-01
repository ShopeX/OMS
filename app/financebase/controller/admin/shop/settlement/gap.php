<?php
/**
 * 差异类型控制层
 *
 * @author 334395174@qq.com
 * @version 0.1
 */

class financebase_ctl_admin_shop_settlement_gap extends desktop_controller
{

    /**
     * 差异类型列表分栏菜单
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

        $gapObj = app::get('financebase')->model('gap');

        $sub_menu = array(
            0 => array('label' => app::get('base')->_('有效'), 'filter' => array('status' => '1'), 'optional' => false),
            1 => array('label' => app::get('base')->_('无效'), 'filter' => array('status' => '0'), 'optional' => false),
        );

        foreach ($sub_menu as $k => $v) {
            $sub_menu[$k]['filter'] = $v['filter'] ? $v['filter'] : null;
            $sub_menu[$k]['addon']  = $gapObj->count($v['filter']);
            $sub_menu[$k]['href']   = 'index.php?app=financebase&ctl=admin_shop_settlement_gap&act=index&view=' . $k;
        }

        return $sub_menu;
    }

    // 差异类型列表
    public function index()
    {
        // 根据view设置base_filter
        $base_filter = array();
        $view = isset($_GET['view']) ? intval($_GET['view']) : 0;
        
        if ($view == 0) {
            // 有效
            $base_filter = array('status' => '1');
        } elseif ($view == 1) {
            // 无效
            $base_filter = array('status' => '0');
        }
        
        $actions = array();
        
        // 新增按钮权限控制
        if (kernel::single('desktop_user')->has_permission('shop_settlement_gap_add')) {
            $actions[] = array(
                'label'=>'新增',
                'href'=>'index.php?app=financebase&ctl=admin_shop_settlement_gap&act=setGap&p[0]=0&singlepage=false&finder_id='.$_GET['finder_id'],
                'target'=>'dialog::{width:600,height:400,resizeable:false,title:\'新增差异类型\'}'
            );
        }

        // 置为无效按钮权限控制 - 只在有效tab中显示
        if (kernel::single('desktop_user')->has_permission('shop_settlement_gap_delete') && $view == 0) {
            $actions[] = array(
                'label'   => '置为无效',
                'confirm' => '你确定要将选中的差异类型置为无效吗？',
                'submit'  => 'index.php?app=financebase&ctl=admin_shop_settlement_gap&act=disableGap',
                'target'  => 'refresh'
            );
        }

        // 恢复按钮权限控制 - 只在无效tab中显示
        if (kernel::single('desktop_user')->has_permission('shop_settlement_gap_restore') && $view == 1) {
            $actions[] = array(
                'label'   => '置为有效',
                'confirm' => '你确定要将选中的差异类型置为有效吗？',
                'submit'  => 'index.php?app=financebase&ctl=admin_shop_settlement_gap&act=restoreGap',
                'target'  => 'refresh'
            );
        }

        $params = array(
            'title'=>'差异类型设置',
            'actions' => $actions,
            'base_filter' => $base_filter,
            'use_buildin_set_tag'=>false,
            'use_buildin_recycle'=>false,
            'use_buildin_filter'=>false,
            'use_buildin_export'=> false,
            'use_buildin_import'=> false,
            'orderBy'=>'id',
        );

        $this->finder('financebase_mdl_gap',$params);
    }

    // 设置差异类型
    public function setGap($id=0)
    {
        // 检查权限
        if ($id > 0) {
            // 编辑模式
            if (!kernel::single('desktop_user')->has_permission('shop_settlement_gap_edit')) {
                $this->splash('error', null, '您没有编辑差异类型的权限');
            }
        } else {
            // 新增模式
            if (!kernel::single('desktop_user')->has_permission('shop_settlement_gap_add')) {
                $this->splash('error', null, '您没有新增差异类型的权限');
            }
        }
        
        $gap_info = array('id'=>$id);
        $oGap = app::get('financebase')->model("gap");
        if($id)
        {
            $gap_info=$oGap->db_dump(array('id'=>$id));
        }

        // 获取会计科目列表 - 只获取有效且排除内置特殊场景科目
        $oAccount = app::get('financebase')->model("account_chart");
        $account_list = $oAccount->getList('id,account,description,postingkey', array('disabled' => 'false', 'account_category' => ''));
        $this->pagedata['account_list'] = $account_list;
        $this->pagedata['gap_info'] = $gap_info;
        $this->display("admin/gap/gap.html");
    }

    // 保存差异类型
    public function saveGap()
    {
        $this->begin('index.php?app=financebase&ctl=admin_shop_settlement_gap&act=index');
        
        // 检查权限
        $is_edit = !empty($_POST['id']);
        if ($is_edit) {
            if (!kernel::single('desktop_user')->has_permission('shop_settlement_gap_edit')) {
                $this->end(false, '您没有编辑差异类型的权限');
            }
        } else {
            if (!kernel::single('desktop_user')->has_permission('shop_settlement_gap_add')) {
                $this->end(false, '您没有新增差异类型的权限');
            }
        }
        
        $oGap = app::get('financebase')->model("gap");
        $data = array();

        $data['id'] = intval($_POST['id']);

        $data['gap_name'] = htmlspecialchars($_POST['gap_name']);
        $data['account_id_plus'] = intval($_POST['account_id_plus']);
        $data['account_id_minus'] = intval($_POST['account_id_minus']);

        // 判断差异类型是否唯一
        if($oGap->isExist(array('gap_name'=>$data['gap_name']),$data['id']))
        {
            $this->end(false, "差异类型有重名");
        }

        // 获取原始数据用于日志记录（仅编辑时需要）
        $existing = null;
        if ($is_edit) {
            $existing = $oGap->dump(array('id' => $data['id']));
        }

        if($oGap->save($data))
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

    // 置为无效差异类型
    public function disableGap()
    {
        $this->begin('index.php?app=financebase&ctl=admin_shop_settlement_gap&act=index');
        
        // 检查置为无效权限
        if (!kernel::single('desktop_user')->has_permission('shop_settlement_gap_delete')) {
            $this->end(false, '您没有置为无效差异类型的权限');
        }
        
        if($_POST['isSelectedAll'] == '_ALL_'){
            $this->end(false,'不支持全选置为无效');
        }
        
        if(empty($_POST['id'])){
            $this->end(false,'请选择要置为无效的差异类型');
        }
        
        $oGap = app::get('financebase')->model("gap");
        
        foreach($_POST['id'] as $gap_id){
            if($gap_id){
                // 获取差异类型信息
                $gap_info = $oGap->dump(array('id'=>$gap_id), 'gap_name');
                if(!$gap_info){
                    $this->end(false, '差异类型不存在');
                }
                
                $gap_name = $gap_info['gap_name'];
                
                // 执行软删除 - 将status设置为0
                if(!$oGap->update(array('status' => '0'), array('id'=>$gap_id))){
                    $this->end(false, "置为无效差异类型【{$gap_name}】失败");
                }
                
                // 记录操作日志
                $this->_recordLog('delete', $gap_id);
            }
        }
        
        $this->end(true, '置为无效成功');
    }

    // 恢复差异类型
    public function restoreGap()
    {
        $this->begin('index.php?app=financebase&ctl=admin_shop_settlement_gap&act=index');
        
        // 检查恢复权限
        if (!kernel::single('desktop_user')->has_permission('shop_settlement_gap_restore')) {
            $this->end(false, '您没有恢复差异类型的权限');
        }
        
        if($_POST['isSelectedAll'] == '_ALL_'){
            $this->end(false,'不支持全选恢复');
        }
        
        if(empty($_POST['id'])){
            $this->end(false,'请选择要恢复的差异类型');
        }
        
        $oGap = app::get('financebase')->model("gap");
        
        foreach($_POST['id'] as $gap_id){
            if($gap_id){
                // 获取差异类型信息
                $gap_info = $oGap->dump(array('id'=>$gap_id), 'gap_name');
                if(!$gap_info){
                    $this->end(false, '差异类型不存在');
                }
                
                $gap_name = $gap_info['gap_name'];
                
                // 执行恢复 - 将status设置为1
                if(!$oGap->update(array('status' => '1'), array('id'=>$gap_id))){
                    $this->end(false, "恢复差异类型【{$gap_name}】失败");
                }
                
                // 记录操作日志
                $this->_recordLog('restore', $gap_id);
            }
        }
        
        $this->end(true, '置为有效成功');
    }

    /**
     * 记录操作日志
     * 
     * @param string $action 操作类型：add/edit/delete/restore
     * @param int $gap_id 差异类型ID
     * @param array $data 新数据（编辑时需要）
     * @param array $existing 原始数据（编辑时需要）
     */
    private function _recordLog($action, $gap_id, $data = null, $existing = null)
    {
        $omeLogMdl = app::get('ome')->model('operation_log');
        
        // 根据操作类型设置日志参数
        switch ($action) {
            case 'add':
                $log_key = 'gap_add@financebase';
                $memo = '新增差异类型';
                break;
            case 'edit':
                $log_key = 'gap_edit@financebase';
                $memo = '编辑差异类型';
                break;
            case 'delete':
                $log_key = 'gap_delete@financebase';
                $memo = '置为无效差异类型';
                break;
            case 'restore':
                $log_key = 'gap_restore@financebase';
                $memo = '恢复差异类型';
                break;
            default:
                return;
        }
        
        // 记录操作日志
        $log_id = $omeLogMdl->write_log($log_key, $gap_id, $memo);
        
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
            $diff_data = $this->_compareGapData($row, $updated_data);
        }
        
        // 获取会计科目列表 - 只获取有效的科目
        $oAccount = app::get('financebase')->model("account_chart");
        $account_list = $oAccount->getList('id,account,description,postingkey', array('disabled' => 'false'));
        
        $this->pagedata['gap_info'] = $row;
        $this->pagedata['diff_data'] = $diff_data;
        $this->pagedata['account_list'] = $account_list;
        $this->pagedata['history'] = true;
        $this->singlepage("admin/gap/gap.html");
    }
    
    /**
     * 对比差异类型数据差异
     */
    private function _compareGapData($old_data, $new_data)
    {
        $diff_data = array();
        
        // 需要对比的字段
        $compare_fields = array('gap_name', 'account_id_plus', 'account_id_minus');
        
        foreach ($compare_fields as $field) {
            $old_value = isset($old_data[$field]) ? $old_data[$field] : '';
            $new_value = isset($new_data[$field]) ? $new_data[$field] : '';
            
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
