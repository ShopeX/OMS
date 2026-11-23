<?php
class omeauto_ctl_autodispatch extends omeauto_controller{
    var $workground = "setting_tools";

    function index(){
        //check shop permission
        $organization_permissions = kernel::single('desktop_user')->get_organization_permission();
        if($organization_permissions){
            $base_filter['org_id'] = $organization_permissions;
        }

        $params = array(
            'title'=>'订单自动分派规则',
            'actions' => array(
                array(
                    'label' => '新建',
                    'href' => 'index.php?app=omeauto&ctl=autodispatch&act=add',
                    'target' => 'dialog::{width:760,height:400,title:\'新建订单分派规则\'}',
                )
            ),
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'=>false,
            'use_buildin_recycle'=>true,
            'use_buildin_export'=>false,
            'use_buildin_import'=>false,
            'use_buildin_filter'=>false,
            'use_view_tab'=>false,
            'finder_cols' => 'column_confirm,column_disabled,name,column_order,group_id,op_id',
            'base_filter' => $base_filter,
       );
        $this->finder('omeauto_mdl_autodispatch',$params);
    }



    function add(){
        $this->_edit();
    }

    function edit($oid){

        $this->pagedata['data'] = app::get('omeauto')->model('autodispatch')->dump(array('oid' => $oid), '*');
        $org_id = $this->pagedata['data']['org_id'];
        $this->pagedata['orderType'] = $this->getOrderType($org_id);

        $this->pagedata['groups'] = app::get('ome')->model('groups')->getList('group_id,name',array('g_type'=>'confirm','org_id'=>$org_id));
        $this->_edit($oid);
    }

    private function _edit($oid=NULL){
        $operationOrgObj = app::get('ome')->model('operation_organization');
        $orgs = $operationOrgObj->getList('*', $filter, 0, -1);
        $this->pagedata['orgs'] = $orgs;

        $this->page('autodispatch/add.html');
    }

    function do_add(){

        $data = array_filter($_POST);
         //修改
        if ($data['oid']) {
             $data['oid'] = intval($data['oid']);
             kernel::database()->query(sprintf("update sdb_omeauto_order_type set did=0 where did=%s", $data["oid"]));
        }
        $data['config'] = is_array($data['config'])?$data['config']:NULL;
        $data['op_id'] = isset($data['op_id'])?$data['op_id']:NULL;
        $data['org_id'] = isset($data['org_id']) ? $data['org_id'] : 1; //默认为1,后面添加组织关系,就会自动关联.
        app::get('omeauto')->model('autodispatch')->save($data);
        foreach( (array)$data['config']['autoOrders'] as $tid) {
            $tid = intval($tid);
            kernel::database()->query(sprintf("update sdb_omeauto_order_type set did=%s where tid=%s",$data["oid"], $tid));
        }
        echo "SUCC";
    }

    function setStatus($oid, $status) {

        $oid = intval($oid);
        if ($status == 'true') {
            $disabled = 'false';
        } else {
            $disabled = 'true';
        }
        kernel::database()->query("update sdb_omeauto_autodispatch set disabled='{$disabled}' where oid={$oid}");

        echo "<script>parent.MessageBox.success('命令已经被成功发送！！');parent.finderGroup['{$_GET["finder_id"]}'].refresh();</script>";
        exit;
    }

    function setDefaulted($oid) {

        $oid = intval($oid);
        if ($oid && $oid > 0) {
            $dispatchObj = app::get('omeauto')->model('autodispatch');
            $data = $dispatchObj->dump($oid, 'oid,config');
            unset($data['config']['autoOrders']);
            $upData = array(
                'defaulted'=>'true',
                'config'=>$data['config'],
            );
            //全部取消缺省
            kernel::database()->query("update sdb_omeauto_autodispatch set defaulted='false'");
            //全部取消缺省
            kernel::database()->query("update sdb_omeauto_order_type set did=0 where did={$oid}");
            //置指定仓库为缺省发货仓库
            $dispatchObj->update($upData,array('oid'=>$oid));
        }
        //$this->end(true, '默认发货仓设置成功！！');
        echo "<script>alert('默认审单规则设置成功！！');top.finderGroup['".$_REQUEST["finder_id"]."'].refresh();</script>";
    }

    function removeDefaulted($oid) {

        $oid = intval($oid);
        if ($oid && $oid > 0) {
            //置指定仓库为缺省发货仓库
            kernel::database()->query("update sdb_omeauto_autodispatch set defaulted='false' where oid={$oid}");
        }
        echo "<script>alert('取消默认审单规则设置成功！！');top.finderGroup['".$_REQUEST["finder_id"]."'].refresh();</script>";
    }

    private function getOrderType($org_id) {

        $info = app::get('omeauto')->model('order_type')->getList('*', array('disabled' => 'false','group_type'=>'order','org_id'=>$org_id), 0, -1);
        foreach ($info as $idx => $rows) {
            $title = '';
            foreach ($rows['config'] as $row) {

                $role = json_decode($row, true);
                $title .= $role['caption'] . "\n";
            }
            $info[$idx]['title'] = $title;
        }

        return $info;
    }

    public function ajax_get_group($org_id){
        $groupObj = app::get('ome')->model('groups');
        $ordTypeObj = app::get('omeauto')->model('order_type');

        //$_POST['oid']

        $groupInfo = $groupObj->getList('group_id,name',array('g_type'=>'confirm','org_id'=>$org_id));
        //$this->pagedata['groups'] = $groupInfo;

        $ordTypeInfo = $ordTypeObj->getList('*', array('disabled' => 'false','group_type'=>'order','org_id'=>$org_id), 0, -1);
        foreach ($ordTypeInfo as $idx => $rows) {
            $title = '';
            foreach ($rows['config'] as $row) {

                $role = json_decode($row, true);
                $title .= $role['caption'] . "\n";
            }
            $ordTypeInfo[$idx]['title'] = $title;

            if($rows['did']) $ordTypeInfo[$idx]['disabled'] = 'disabled';
        }

        //<{if $item.tid|in_array:$data.config.autoOrders}>checked<{else}><{if $item.did || $data.defaulted=='true'}>disabled<{/if}><{/if}>
        //$this->pagedata['ordTypeInfo'] = $ordTypeInfo;
        //$this->display('autodispatch/ajax_get_group.html');
        $result = array('groups' => $groupInfo, 'ordTypeInfo'=>$ordTypeInfo);
        echo json_encode($result);exit;
    }
}
?>
