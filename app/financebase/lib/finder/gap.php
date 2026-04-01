<?php

class financebase_finder_gap {

    function __construct(){
        if(in_array($_REQUEST['action'], ['exportcnf', 'to_export', 'export'])){
            unset($this->column_edit);
        }
    }

    var $addon_cols = 'account_id_plus,account_id_minus';
    var $col_prefix = ''; // 动态设置的前缀，用于addon_cols字段

    // 添加贷会计科目列
    public $column_account_id_plus = "贷会计科目";
    public $column_account_id_plus_width = 110;
    public $column_account_id_plus_order = 20;

    // 添加借会计科目列
    public $column_account_id_minus = "借会计科目";
    public $column_account_id_minus_width = 110;
    public $column_account_id_minus_order = 22;

    var $column_edit = "操作";
    var $column_edit_order = "1";
    var $column_edit_width = "50";

    function column_edit($row) {
        $finder_id = $_GET['_finder']['finder_id'];

        // 检查编辑权限
        if (!kernel::single('desktop_user')->has_permission('shop_settlement_gap_edit')) {
            return '';
        }

        $ret = '<a href="index.php?app=financebase&ctl=admin_shop_settlement_gap&act=setGap&p[0]='.$row['id'].'&_finder[finder_id]=' . $finder_id . '&finder_id=' . $finder_id . '" target="dialog::{width:600,height:400,resizeable:false,title:\'编辑差异类型\'}">编辑</a>';

        return $ret;
    }

    private function _getAccountCache()
    {
        static $accountCache;
        
        if (!isset($accountCache)) {
            $accountObj = app::get('financebase')->model('account_chart');
            $accountList = $accountObj->getList('id,account,description,postingkey');
            $accountCache = array();
            foreach ($accountList as $account) {
                $accountCache[$account['id']] = '[' . $account['account'] . '-' . $account['postingkey'] . ']' . $account['description'];
            }
        }
        
        return $accountCache;
    }

    public function column_account_id_plus($row, $list)
    {
        if (empty($row[$this->col_prefix.'account_id_plus'])) {
            return '';
        }
        
        $accountCache = $this->_getAccountCache();
        
        // 格式化显示
        if (isset($accountCache[$row[$this->col_prefix.'account_id_plus']])) {
            return $accountCache[$row[$this->col_prefix.'account_id_plus']];
        }
        
        return $row[$this->col_prefix.'account_id_plus'];
    }

    public function column_account_id_minus($row, $list)
    {
        if (empty($row[$this->col_prefix.'account_id_minus'])) {
            return '';
        }
        
        $accountCache = $this->_getAccountCache();
        
        // 格式化显示
        if (isset($accountCache[$row[$this->col_prefix.'account_id_minus']])) {
            return $accountCache[$row[$this->col_prefix.'account_id_minus']];
        }
        
        return $row[$this->col_prefix.'account_id_minus'];
    }

    // 添加操作日志功能
    public $detail_show_log = '操作记录';
    public function detail_show_log($gap_id)
    {
        // 使用ome模块的read_log方法，与会计科目保持一致
        $omeLogMdl = app::get('ome')->model('operation_log');
        $logList = $omeLogMdl->read_log(array('obj_id' => $gap_id, 'obj_type' => 'gap@financebase'), 0, -1);
        
        $finder_id = $_GET['_finder']['finder_id'];
        
        if ($logList) {
            foreach ($logList as $k => $v) {
                $logList[$k]['operate_time'] = date('Y-m-d H:i:s', $v['operate_time']);

                // 检查操作类型，为编辑操作添加快照链接
                if (strpos($v['operation'], '编辑') !== false) {
                    $logList[$k]['memo'] = "<a href='index.php?app=financebase&ctl=admin_shop_settlement_gap&act=show_history&p[0]={$v['log_id']}&finder_id={$finder_id}' onclick=\"window.open(this.href, '_blank', 'width=801,height=570'); return false;\">查看快照</a>";
                } else {
                    // 其他操作（如新建）不显示快照链接，但保留原有的memo内容
                    $logList[$k]['memo'] = $v['memo'] ?: '';
                }
            }
        }
        
        $render = app::get('financebase')->render();
        $render->pagedata['logs'] = $logList ?: array();
        return $render->fetch('finder/gap/operation_log.html');
    }
}
