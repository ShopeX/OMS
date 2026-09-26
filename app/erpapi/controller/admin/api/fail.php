<?php
/**
 * 失败请求
 *
 * @category
 * @package
 * @author chenping<chenping@shopex.cn>
 * @version $Id: Z
 */
class erpapi_ctl_admin_api_fail extends desktop_controller
{
    /**
     * 失败列表
     *
     * @return void
     * @author
     **/
    function retry(){
        $params = array(
            'title' =>'失败请求',
            'actions' => array(
                array('label'=>'批量重试','submit'=>'index.php?app=erpapi&ctl=admin_api_fail&act=retry_view','target'=>"dialog::{width:690,height:200,title:'批量重试'}"),
            ),
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    =>false,
            'use_buildin_recycle'    =>false,
            'use_buildin_export'     =>false,
            'use_buildin_import'     =>false,
            'use_buildin_filter'     =>true,
            'orderBy'                => 'id desc',
        );
        if(!isset($_GET['action'])) {
            $panel = new desktop_panel($this);
            $panel->setId('apifail_finder_top');
            $panel->setTmpl('admin/finder/finder_panel_filter.html');
            $panel->show('erpapi_mdl_api_fail', $params);
        }
        $this->finder('erpapi_mdl_api_fail',$params);
    }

    /**
     * undocumented function
     *
     * @return void
     * @author
     **/
    public function retry_view()
    {
        if(empty($_POST['id']) && $_POST['isSelectedAll'] != '_ALL_'){
            echo('没有选择执行的记录!');
            exit;
        }

        $filter = array();

        if($_POST['isSelectedAll'] != '_ALL_'){
            $filter['id']    = $_POST['id'];
        }else{
            if($_POST['obj_type'] == '') unset($_POST['obj_type']);
            $filter = $_POST;
        }
        
        $filter['status|noequal']    = 'succ';

        $apiModel = app::get('erpapi')->model('api_fail');
        $total = $apiModel->count($filter);
        if($total > 500){
            echo('每次最多可执行500条记录!');
            exit;
        }

        $ids = array();
        $apilist = $apiModel->getList('id', $filter);

        foreach ($apilist as $key => $val)
        {
            $ids[]    = $val['id'];
        }
        
        $this->pagedata['postIds'] = json_encode($ids);
        $this->pagedata['count'] = $total;

        $this->display('admin/api/retry_view.html');
    }

    /**
     * undocumented function
     *
     * @return void
     * @author
     **/
    public function retry_do()
    {
        $id = intval($_POST['id']);

        $filter = array('id' => $id, 'status|noequal' => 'succ');

        $apiModel = app::get('erpapi')->model('api_fail');
        $apiRow = $apiModel->getList('*', $filter, 0, 1);
        $apiRow = $apiRow[0];
        
        $err_msg = '';
        $result = array(
                    'rsp' => 'fail',
                    'id' => $apiRow['id'],
                    'obj_bn' => $apiRow['obj_bn'],
        );

        if(empty($apiRow)){
            $result['err_msg']    = '没有相关记录';
            echo json_encode($result);
            exit;
        }
        
        // 设置为失败状态，让手工可以重试
        if($apiRow['status'] == 'running'){
            $apiModel->update(array('status'=>'fail'),array('id'=>$apiRow['id']));
        }
        
        //发起请求
        $rs = kernel::single('erpapi_autotask_task_retryapi')->process($apiRow, $err_msg);
        if ($rs === true){
            $result['rsp']    = 'succ';
        }else{
            $result['err_msg'] = $err_msg;
        }

        echo json_encode($result);
        exit;
    }

    /*------------------------------------------------------ */
    //-- 重试执行完成后_刷新页面
    /*------------------------------------------------------ */
    function refresh()
    {
        $backurl    = 'index.php?app=erpapi&ctl=admin_api_fail&act=retry';

        $this->begin($backurl);

        $this->end(true,'处理成功!');
    }
}
