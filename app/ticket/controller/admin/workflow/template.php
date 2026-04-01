<?php
/**
 * 审批流模板控制器
 *
 * @author shopex开发团队
 * @version 2025.07.10
 */
class ticket_ctl_admin_workflow_template extends desktop_controller {
    var $title = '审批流模板列表';
    var $workground = 'ticket_center';
    private $_appName = 'ticket';
    
    private $_mdl = null; //model类
    private $_primary_id = null; //主键ID字段名
    private $_primary_bn = null; //单据编号字段名
    
    /**
     * Lib对象
     */
    protected $_workflowTemplateLib = null;
    
    public function __construct($app)
    {
        parent::__construct($app);
        
        $this->_mdl = app::get($this->_appName)->model('workflow_template');
        $this->_workflowTemplateLib = kernel::single('ticket_workflow_template');
        
        //primary_id
        $this->_primary_id = 'id';
        
        //primary_bn
        $this->_primary_bn = 'template_bn';
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
                if($user->has_permission('ticket_workflow_template_operate')){
                    $actions[] = $buttonList['add'];
                }
                break;
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
        $this->finder('ticket_mdl_workflow_template', $params);
    }

    /**
     * 添加页面
     */
    public function add() {
        $this->pagedata['data'] = [];
        $this->page('admin/workflow/template/add.html');
    }

    /**
     * 编辑页面
     */
    public function edit($id) {
        $model = app::get('ticket')->model('workflow_template');
        $row = $model->dump($id);
        
        $this->pagedata['data'] = $row;
        $this->page('admin/workflow/template/add.html');
    }

    /**
     * 保存数据
     */
    public function save() {
        $this->begin($this->url.'&act=index');
        
        // post
        $data = $_POST;
        $error_msg = '';
        
        // check
        $checkResult = kernel::single('ticket_workflow_template')->checkParams($data);
        if($checkResult['rsp'] != 'succ'){
            $error_msg = '检查失败：'. $checkResult['error_msg'];
            $this->end(false, $error_msg);
        }
        
        // save
        $saveResult = kernel::single('ticket_workflow_template')->saveData($data, $error_msg);
        if($saveResult['rsp'] != 'succ'){
            $error_msg = '保存失败：'. $error_msg;
            $this->end(false, $error_msg);
        }
        
        $this->end(true, '操作成功');
    }
} 