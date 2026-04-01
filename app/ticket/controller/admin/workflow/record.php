<?php
/**
 * 审批流记录控制器
 *
 * @author shopex开发团队
 * @version 2025.07.10
 */
class ticket_ctl_admin_workflow_record extends desktop_controller {
    var $title = '审批流记录列表';
    var $workground = 'ticket_center';
    private $_appName = 'ticket';
    
    private $_mdl = null; //model类
    private $_primary_id = null; //主键ID字段名
    private $_primary_bn = null; //单据编号字段名
    
    /**
     * Lib对象
     */
    protected $_workflowRecordLib = null;
    
    public function __construct($app)
    {
        parent::__construct($app);
        
        $this->_mdl = app::get($this->_appName)->model('workflow_record');
        $this->_workflowRecordLib = kernel::single('ticket_workflow_record');
        
        //primary_id
        $this->_primary_id = 'id';
        
        //primary_bn
        $this->_primary_bn = 'id';
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
                //--
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
        $this->finder('ticket_mdl_workflow_record', $params);
    }
}