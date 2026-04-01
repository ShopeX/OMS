<?php
/**
 * 审批流实例控制器
 *
 * @author shopex开发团队
 * @version 2025.07.10
 */
class ticket_ctl_admin_workflow_case extends desktop_controller {
    var $title = '审批流实例列表';
    var $workground = 'ticket_center';
    private $_appName = 'ticket';
    
    private $_mdl = null; //model类
    private $_primary_id = null; //主键ID字段名
    private $_primary_bn = null; //单据编号字段名
    
    /**
     * Lib对象
     */
    protected $_workflowCaseLib = null;
    
    public function __construct($app)
    {
        parent::__construct($app);
        
        $this->_mdl = app::get($this->_appName)->model('workflow_case');
        $this->_workflowCaseLib = kernel::single('ticket_workflow_case');
        
        //primary_id
        $this->_primary_id = 'id';
        
        //primary_bn
        $this->_primary_bn = 'case_bn';
    }

    /**
     * 列表页面
     */
    public function index() {
        $user = kernel::single('desktop_user');
        $actions = array();
        
        //filter
        $base_filter = [];
        
        //button
        $buttonList = array();
        
        //view
        $_GET['view'] = (empty($_GET['view']) ? '0' : $_GET['view']);
        switch ($_GET['view'])
        {
            case '0':
                //--
        }
        
        //params
        $orderby = 'id DESC';
        $params = array(
            'title' => $this->title,
            'base_filter' => $base_filter,
            'actions'=> $actions,
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag' => false,
            'use_buildin_recycle' => false,
            'use_buildin_export' => false,
            'use_buildin_import' => false,
            'use_buildin_filter' => true,
            'orderBy' => $orderby,
        );
        $this->finder('ticket_mdl_workflow_case', $params);
    }
    
    public function _views()
    {
        //filter
        $base_filter = [];
        
        //menu
        $sub_menu = array(
            0 => array('label'=>app::get('base')->_('全部'), 'filter'=>$base_filter, 'optional'=>false),
            1 => array('label'=>app::get('base')->_('待审批'), 'filter'=>['status'=>['pending','processing','approved'], 'current_node_type'=>['start','approval']], 'optional'=>false),
            2 => array('label'=>app::get('base')->_('已完成'), 'filter'=>['current_node_type'=>'end'], 'optional'=>false),
            3 => array('label'=>app::get('base')->_('取消'), 'filter'=>['status'=>['rejected','cancelled']], 'optional'=>false),
        );
        
        foreach($sub_menu as $k => $v)
        {
            if (isset($v['filter'])){
                $v['filter'] = array_merge($base_filter, $v['filter']);
            }
            
            $sub_menu[$k]['filter'] = $v['filter'] ? $v['filter'] : null;
            $sub_menu[$k]['href'] = 'index.php?app='. $_GET['app'] .'&ctl='.$_GET['ctl'].'&act='.$_GET['act'].'&view='.$k;
            
            //第一个TAB菜单没有数据时显示全部
            if($k == 0){
                //count
                $sub_menu[$k]['addon'] = $this->_mdl->count($v['filter']);
                if($sub_menu[$k]['addon'] == 0){
                    unset($sub_menu[$k]);
                }
            }else{
                //count
                $sub_menu[$k]['addon'] = $this->_mdl->viewcount($v['filter']);
            }
        }
        
        return $sub_menu;
    }
    
    /**
     * 审核页面
     */
    public function audit($id)
    {
        $nodeLib = kernel::single('ticket_workflow_node');
        
        // info
        $row = $this->_mdl->dump($id);
        
        // check
        if(empty($row)){
            die('数据不存在，请检查！');
        }
        
        if(empty($row['items_context'])){
            die('审批流实例没有赠品信息，请检查！');
        }
        
        if($row['current_node_type'] == 'end'){
            die('审批流已经结束，不能操作！');
        }elseif(in_array($row['status'], ['rejected','cancelled'])){
            die('审批已拒绝或取消，不能操作！');
        }
        
        // order info
        $result = $this->_workflowCaseLib->getOrderDetails($row);
        if($result['rsp'] != 'succ'){
            die($result['error_msg']);
        }
        
        $orderInfo = $result['data'];
        
        // addGiftList
        $addGiftList = $orderInfo['addGiftList'];
        
        // 当前节点
        $currentNode = $nodeLib->getCurrentNode($row['current_node_id']);
        
        $this->pagedata['currentNode'] = $currentNode;
        $this->pagedata['addGiftList'] = $addGiftList;
        $this->pagedata['orderInfo'] = $orderInfo;
        $this->pagedata['data'] = $row;
        $this->page('admin/workflow/case/audit.html');
    }
    
    /**
     * 审核确认
     */
    public function do_audit() {
        $this->begin($this->url.'&act=index');
        
        $data = $_POST;
        $error_msg = '';
        
        // check
        $checkResult = $this->_workflowCaseLib->checkAuditParams($data);
        if($checkResult['rsp'] != 'succ'){
            $error_msg = '审批检测失败：'. $checkResult['error_msg'];
            $this->end(false, $error_msg);
        }
        
        // save
        $saveResult = $this->_workflowCaseLib->processAudit($data, $error_msg);
        if($saveResult['rsp'] != 'succ'){
            $error_msg = '审批保存失败：'. $error_msg;
            $this->end(false, $error_msg);
        }
        
        $this->end(true, '操作成功');
    }
    
    /**
     * 创建订单赠品审批实例
     */
    public function create_gift_approval() {
        $this->begin($this->url.'&act=index');
        
        $data = $_POST;
        
        // check
        $checkResult = $this->_workflowCaseLib->checkCreateGiftApprovalParams($data);
        if($checkResult['rsp'] != 'succ'){
            $error_msg = '检查失败：'. $checkResult['error_msg'];
            $this->end(false, $error_msg);
        }
        
        // create
        $createResult = $this->_workflowCaseLib->createGiftApprovalCase($data, $error_msg);
        if($createResult['rsp'] != 'succ'){
            $error_msg = '创建失败：'. $error_msg;
            $this->end(false, $error_msg);
        }
        
        $this->end(true, '审批实例创建成功');
    }
    
    /**
     * 获取待审批列表
     */
    public function get_pending_list() {
        $user = kernel::single('desktop_user');
        $user_id = $user->get_id();
        
        $result = $this->_workflowCaseLib->getPendingCasesByUser($user_id);
        
        $this->pagedata['pending_list'] = $result['data'];
        $this->page('admin/workflow/case/pending_list.html');
    }
    
    /**
     * 审批流程详情
     */
    public function detail($id) {
        // info
        $row = $this->_mdl->dump($id);
        
        // check
        if(empty($row)){
            die('数据不存在,请检查！');
        }
        
        // get approval records
        $recordResult = $this->_workflowCaseLib->getApprovalRecords($id);
        if($recordResult['rsp'] != 'succ'){
            die($recordResult['error_msg']);
        }
        
        // order info
        $orderResult = $this->_workflowCaseLib->getOrderDetails($row);
        if($orderResult['rsp'] != 'succ'){
            die($orderResult['error_msg']);
        }
        
        $this->pagedata['data'] = $row;
        $this->pagedata['records'] = $recordResult['data'];
        $this->pagedata['orderInfo'] = $orderResult['data'];
        $this->page('admin/workflow/case/detail.html');
    }
    
    /**
     * 取消审批
     */
    public function cancel_approval() {
        $this->begin($this->url.'&act=index');
        
        $data = $_POST;
        
        // check
        $checkResult = $this->_workflowCaseLib->checkCancelParams($data);
        if($checkResult['rsp'] != 'succ'){
            $error_msg = '检查失败：'. $checkResult['error_msg'];
            $this->end(false, $error_msg);
        }
        
        // cancel
        $cancelResult = $this->_workflowCaseLib->cancelApproval($data, $error_msg);
        if($cancelResult['rsp'] != 'succ'){
            $error_msg = '取消失败：'. $error_msg;
            $this->end(false, $error_msg);
        }
        
        $this->end(true, '审批已取消');
    }
    
    /**
     * 重新提交审批
     */
    public function resubmit_approval() {
        $this->begin($this->url.'&act=index');
        
        $data = $_POST;
        
        // check
        $checkResult = $this->_workflowCaseLib->checkResubmitParams($data);
        if($checkResult['rsp'] != 'succ'){
            $error_msg = '检查失败：'. $checkResult['error_msg'];
            $this->end(false, $error_msg);
        }
        
        // resubmit
        $resubmitResult = $this->_workflowCaseLib->resubmitApproval($data, $error_msg);
        if($resubmitResult['rsp'] != 'succ'){
            $error_msg = '重新提交失败：'. $error_msg;
            $this->end(false, $error_msg);
        }
        
        $this->end(true, '审批已重新提交');
    }
    
    /**
     * 导出审批记录
     */
    public function export_records() {
        $data = $_GET;
        
        $result = $this->_workflowCaseLib->exportApprovalRecords($data);
        if($result['rsp'] != 'succ'){
            die($result['error_msg']);
        }
        
        // 输出CSV文件
        $filename = 'approval_records_' . date('YmdHis') . '.csv';
        header('Content-Type: application/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        echo $result['data'];
        exit;
    }
}