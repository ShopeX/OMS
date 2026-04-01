<?php
/**
 * 审批流节点控制器
 *
 * @author shopex开发团队
 * @version 2025.07.10
 */
class ticket_ctl_admin_workflow_node extends desktop_controller {
    var $title = '审批流节点列表';
    var $workground = 'ticket_center';
    private $_appName = 'ticket';
    
    private $_mdl = null; //model类
    private $_primary_id = null; //主键ID字段名
    private $_primary_bn = null; //单据编号字段名
    
    /**
     * Lib对象
     */
    protected $_workflowNodeLib = null;
    
    public function __construct($app)
    {
        parent::__construct($app);
        
        $this->_mdl = app::get($this->_appName)->model('workflow_node');
        $this->_workflowNodeLib = kernel::single('ticket_workflow_node');
        
        //primary_id
        $this->_primary_id = 'id';
        
        //primary_bn
        $this->_primary_bn = 'node_name';
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
        $buttonList['add'] = [
            'label' => '新增',
            'href' => $this->url.'&act=add'
        ];
        
        //view
        $_GET['view'] = (empty($_GET['view']) ? '0' : $_GET['view']);
        switch ($_GET['view'])
        {
            case '0':
                // 新建
                if($user->has_permission('ticket_workflow_node_operate')){
                    $actions[] = $buttonList['add'];
                }
                break;
        }
        
        //params
        $orderby = 'template_id ASC, step_order ASC';
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
        $this->finder('ticket_mdl_workflow_node', $params);
    }

    /**
     * 添加页面
     */
    public function add() {
        $nodeMdl = app::get('ticket')->model('workflow_node');
        $templateLib = kernel::single('ticket_workflow_template');
        $nodeLib = kernel::single('ticket_workflow_node');
        
        // 审批模板列表
        $templateList = $templateLib->getTemplateList();
        
        // 节点类型列表
        $noteTypeList = $nodeMdl->getNodeTypes();
        
        // 审批人类型列表
        $assigneeTypeList = $nodeMdl->getAssigneeTypes();
        
        // 操作员列表
        $userList = $nodeLib->getOperatorList();
        
        $this->pagedata['templateList'] = $templateList;
        $this->pagedata['noteTypeList'] = $noteTypeList;
        $this->pagedata['assigneeTypeList'] = $assigneeTypeList;
        $this->pagedata['userList'] = $userList;
        $this->pagedata['data'] = [];
        
        $this->page('admin/workflow/node/add.html');
    }

    /**
     * 编辑页面
     */
    public function edit($id) {
        $nodeMdl = app::get('ticket')->model('workflow_node');
        $templateLib = kernel::single('ticket_workflow_template');
        $nodeLib = kernel::single('ticket_workflow_node');
        
        // info
        $data = $nodeMdl->dump($id);
        if(empty($data)){
            $error_msg = '数据不存在，请检查';
            die($error_msg);
        }
        
        // 审批模板列表
        $templateList = $templateLib->getTemplateList();
        
        // 节点类型列表
        $noteTypeList = $nodeMdl->getNodeTypes();
        
        // 审批人类型列表
        $assigneeTypeList = $nodeMdl->getAssigneeTypes();
        
        // 操作员列表
        $userList = $nodeLib->getOperatorList();
        
        $this->pagedata['templateList'] = $templateList;
        $this->pagedata['noteTypeList'] = $noteTypeList;
        $this->pagedata['assigneeTypeList'] = $assigneeTypeList;
        $this->pagedata['userList'] = $userList;
        $this->pagedata['data'] = $data;
        
        $this->page('admin/workflow/node/add.html');
    }

    /**
     * 保存数据
     */
    public function save() {
        $this->begin($this->url.'&act=index');
        
        // post
        $data = $_POST;
        
        // check
        $checkResult = kernel::single('ticket_workflow_node')->checkParams($data);
        if($checkResult['rsp'] != 'succ'){
            $error_msg = '检查失败：'. $checkResult['error_msg'];
            $this->end(false, $error_msg);
        }
        
        // save
        $saveResult = kernel::single('ticket_workflow_node')->saveData($data, $error_msg);
        if($saveResult['rsp'] != 'succ'){
            $error_msg = '保存失败：'. $error_msg;
            $this->end(false, $error_msg);
        }
        
        $this->end(true, '操作成功');
    }
} 