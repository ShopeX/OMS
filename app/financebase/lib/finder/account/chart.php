<?php

class financebase_finder_account_chart {

    function __construct(){
        if(in_array($_REQUEST['action'], ['exportcnf', 'to_export', 'export'])){
            unset($this->column_edit);
        }
    }

    var $column_edit = "操作";
    var $column_edit_order = "1";
    var $column_edit_width = "50";

    function column_edit($row) {
        $finder_id = $_GET['_finder']['finder_id'];

        // 检查编辑权限
        if (!kernel::single('desktop_user')->has_permission('shop_settlement_account_chart_edit')) {
            return '';
        }

        $title = '编辑会计科目';
        if (!empty($row['account_code'])) {
            $title .= ' - ' . $row['account_code'];
        }
        $ret = '<a href="index.php?app=financebase&ctl=admin_shop_settlement_account_chart&act=setAccount&p[0]='.$row['id'].'&_finder[finder_id]=' . $finder_id . '&finder_id=' . $finder_id . '" target="dialog::{width:550,height:520,resizeable:false,title:\'' . $title . '\'}">编辑</a>';

        return $ret;
    }

    // 添加操作日志功能
    public $detail_show_log = '操作记录';
    public function detail_show_log($account_id)
    {
        // 使用ome模块的read_log方法，与经销商品价格保持一致
        $omeLogMdl = app::get('ome')->model('operation_log');
        $logList = $omeLogMdl->read_log(array('obj_id' => $account_id, 'obj_type' => 'account_chart@financebase'), 0, -1);
        
        $finder_id = $_GET['_finder']['finder_id'];
        
        if ($logList) {
            foreach ($logList as $k => $v) {
                $logList[$k]['operate_time'] = date('Y-m-d H:i:s', $v['operate_time']);

                // 检查操作类型，为编辑操作添加快照链接
                if (strpos($v['operation'], '编辑') !== false) {
                    $logList[$k]['memo'] = "<a href='index.php?app=financebase&ctl=admin_shop_settlement_account_chart&act=show_history&p[0]={$v['log_id']}&finder_id={$finder_id}' onclick=\"window.open(this.href, '_blank', 'width=550,height=520'); return false;\">查看快照</a>";
                } else {
                    // 其他操作（如新建）不显示快照链接，但保留原有的memo内容
                    $logList[$k]['memo'] = $v['memo'] ?: '';
                }
            }
        }
        
        $render = app::get('financebase')->render();
        $render->pagedata['logs'] = $logList ?: array();
        return $render->fetch('finder/account/chart/operation_log.html');
    }
}
