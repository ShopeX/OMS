<?php
/**
 * 短信规则类
 *
 * @package taoexlib
 * @author   zhangxuehui
 **/
class taoexlib_ctl_admin_sms_rule extends desktop_controller {
    /**
     * 列表所在组
     *
     * @var string
     **/
    var $workground = 'rolescfg';

    function __construct(&$app)
    {
        parent::__construct($app);
        $this->_request = kernel::single('base_component_request');
        $this->order_type = app::get('omeauto')->model('order_type');
    }
    /**
     * 短信规则列表
     *
     * @param  void
     * @return html
     * @author 
     **/

    public function index()
    {
        $params = array(
            'title'=>'短信分组规则列表',
            'use_buildin_recycle'=>false,
            'actions'=>array(
                array(
                    'label' => '新建',
                    'href' => 'index.php?app=taoexlib&ctl=admin_sms_rule&act=add_rule', 
                    'target' => 'dialog::{width:760,height:480,title:\'新建分组规则\'}'
                ),
                array(
                    'label' => '删除',
                    'submit' => 'index.php?app=taoexlib&ctl=admin_sms_rule&act=del_rule', 
                ),
            ),
            'finder_cols' => 'column_confirm,column_disabled,name,column_memo,column_order,column_content,group_type',
            'base_filter'=>array('group_type'=>'sms'),
        );
        $this->finder('omeauto_mdl_order_type', $params);
    } 

    /**
     * 显示添加规则页面
     *
     * @param  void
     * @return html
     * @author 
     **/
    public function add_rule()
    {   
        $filter = array('status'=>true,'disabled'=>'false');
        $this->pagedata['filter']  = $filter;
        $this->pagedata['is_send'] = 'true';
        $this->page('admin/sms/add.html');
    }

    /**
     * 显示编辑规则页面
     *
     * @param  void
     * @return html
     * @author 
     **/
    public function edit_rule($tid)
    {
        $info = app::get('omeauto')->model('order_type')->dump(intval($tid));
        if (!empty($info)) {
            foreach ($info['config'] as $key => $row) {
                $info['config'][$key] = array('json' => $row, 'attr' => json_decode($row, true));
            }
            $this->pagedata['info'] = $info;
        } else {
            $this->pagedata['info'] = array();
        }
        //规则模板
        $info = app::get('taoexlib')->model('sms_bind')->getSmsContentByRuleId($tid);
        $this->pagedata['sample_id'] = $info ? $info['id'] : '';
        $this->pagedata['is_send'] = ($info && $info['is_send'] != 0) ? 'true' : 'false';
        //规则条件
        $this->pagedata['filter'] = array('status'=>true,'disabled'=>'false');
        $this->page('admin/sms/add.html');
    }
    /**
     * 更新规则状态
     *
     * @param  string
     * @return json
     * @author 
     **/
    public function setStatus($tid, $status) 
    {
        $res = $this->isOpen($tid);
        if ($status == 'true') {
                $disabled = 'false';//开启不做判断
        } else {
            if(!$this->isStop($tid)){
                echo "<script>parent.MessageBox.error('分组规则已对应发送规则，无法暂停！');</script>";
                exit;
            }else{
                $disabled = 'true';
            }
        }
        kernel::database()->query("update sdb_omeauto_order_type set disabled='{$disabled}' where tid={$tid}");

        echo "<script>parent.MessageBox.success('命令已经被成功发送！！');parent.finderGroup['{$_GET[finder_id]}'].refresh();</script>";
        exit;
    }
    /**
     * 检查模板是否可以关闭
     *
     * @param  $tid 规则id
     * @return bool
     * @author 
     **/
    public function isStop($tid)
    {
        $info = app::get('taoexlib')->model('sms_bind')->getBindByRuleId($tid);
        if ($info['status'] == '1') {
            return false;
        }else{
            return true;
        }
    }
    public function isOpen($tid)
    {
        $info = app::get('taoexlib')->model('sms_bind')->getBindByRuleId($tid);
        if ($info['status'] == '0') {
            return false;
        }else{
            return true;
        }
    }
    /**
     * 保存规则
     *
     * @param  string
     * @return json
     * @author 
     **/
    public function save() 
    {
        $sdf['name']= $_POST['name'];
        $sdf['memo']= $_POST['memo'];
        $sdf['weight']= $_POST['weight']?$_POST['weight']:0;
        $sdf['config'] = explode('|||', $_POST['roles']);
        $sdf['group_type'] = $_POST['group_type']=='sms'?'sms':'order';
        $tid = intval($_REQUEST['tid']) ;
        if (!empty($tid) && $tid>0) {
            $sdf['tid'] = $tid;
        }
        $res = app::get('omeauto')->model('order_type')->save($sdf);
        if($res){
            //$this->save_rule_sample($sdf['tid'],$sample_id,$is_send); 
            ob_end_clean();
            echo "SUCC";
        }else{
            echo "规则添加失败，请重试";
        }
    }
    /**
     * 删除规则
     *
     * @param  void
     * @return void
     * @author 
     **/
    public function del_rule()
    {
        $this->begin("index.php?app=taoexlib&ctl=admin_sms_rule&act=index");
        $tids = $this->_request->get_post('tid');
        foreach ($tids as $tid) {
            $filter = array('tid'=>$tid);
            $ruleinfo = $this->order_type->getList('disabled,name',$filter);
            if ($ruleinfo[0]['disabled'] == 'false') {
                $this->end(false,app::get('taoexlib')->_('请先暂停规则'.$ruleinfo[0]['name']));
            }
            $sampleInfo = $this->order_type->delete($filter);
            $this->app->model('sms_bind')->delete($filter);
            app::get('taoexlib')->model('sms_sample')->delete($filter);
        }
        $this->end(true,app::get('taoexlib')->_('删除成功'));
    }
}