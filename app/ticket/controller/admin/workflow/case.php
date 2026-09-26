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
            3 => array('label'=>app::get('base')->_('取消'), 'filter'=>['status'=>['rejected','cancelled','source_cancelled']], 'optional'=>false),
            4 => array('label'=>app::get('base')->_('异常'), 'filter'=>['abnormal_status'=>'true'], 'optional'=>false),
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
     * 审核赠品订单页面
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
        }elseif(in_array($row['status'], ['rejected','cancelled','source_cancelled'])){
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

        if ($row['scene_type'] == 'approve_orders') {
            $row['items_list'] = $orderResult['data']['approvalItemList'] ?? array();
        } else {
            $row['items_list'] = $orderResult['data']['addGiftList'] ?? array();
        }
        $row['order_retail_amount'] = $orderResult['data']['order_retail_amount'] ?? ($row['order_retail_amount'] ?? 0);
        
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
    
    /**
     * 审核补发单页面
     */
    public function audit_bufa($id)
    {
        $nodeLib = kernel::single('ticket_workflow_node');
        $businessLib = kernel::single('ticket_workflow_business');
        
        // info
        $row = $this->_mdl->dump($id);
        
        // check
        if(empty($row)){
            die('数据不存在，请检查！');
        }
        
        if($row['current_node_type'] == 'end'){
            die('审批流已经结束，不能操作！');
        }elseif(in_array($row['status'], ['rejected','cancelled','source_cancelled'])){
            die('审批已拒绝或取消，不能操作！');
        }elseif(!empty($row['bpm_status']) && $row['bpm_status'] == 'pending'){
            die('该审批单正在等待BPM三级审批结果，不能在OMS继续操作！');
        }

        $zeroLib = kernel::single('ticket_workflow_case_zero');
        if ($zeroLib->shouldRefreshBufaAssigneeOnOpen($row)) {
            $refresh = $zeroLib->refreshApprovalCaseAssignee($row);
            if ($refresh['rsp'] != 'succ') {
                die($refresh['error_msg']);
            }
        }
        
        // 去除补发原因中的固定文字
        if($row['reason']){
            $row['reason'] = str_replace('零元订单补发：', '', $row['reason']);
        }
        
        // order info
        $result = $businessLib->getOrderDetails($row);
        if($result['rsp'] != 'succ'){
            die($result['error_msg']);
        }
        
        $orderInfo = $result['data'];
        
        $approvalItemList = $orderInfo['approvalItemList'] ?? array();
        
        // 当前节点
        $currentNode = $nodeLib->getCurrentNode($row['current_node_id']);
        
        $this->pagedata['currentNode'] = $currentNode;
        $this->pagedata['approvalItemList'] = $approvalItemList;
        $this->pagedata['approvalAmount'] = $orderInfo['order_retail_amount'] ?? 0;
        $this->pagedata['sceneType'] = $row['scene_type'];
        $this->pagedata['orderInfo'] = $orderInfo;
        $this->pagedata['data'] = $row;
        $this->page('admin/workflow/case/bufa_audit.html');
    }
    
    /**
     * 审核确认
     */
    public function do_audit_bufa()
    {
        $url = $this->url.'&act=index';
        
        $zeroLib = kernel::single('ticket_workflow_case_zero');
        
        // POST
        $data = $_POST;
        
        // check
        $checkResult = $zeroLib->checkAuditParams($data);
        if($checkResult['rsp'] != 'succ'){
            $error_msg = '审批检测失败：'. $checkResult['error_msg'];
            $this->splash('error', $url, $error_msg);
        }
        
        // save
        $saveResult = $zeroLib->processAudit($data);
        if($saveResult['rsp'] != 'succ'){
            $error_msg = '审批处理失败：'. $saveResult['error_msg'];
            $this->splash('error', $url, $error_msg);
        }
        
        $this->splash('success', $url, '审批操作成功');
    }
}