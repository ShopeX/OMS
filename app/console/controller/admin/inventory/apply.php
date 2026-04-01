<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
class console_ctl_admin_inventory_apply extends desktop_controller{
    var $workground = "console_center";
    function index(){
        $params = array(
            'title'=>'盘点申请',
            'use_buildin_recycle'=>false,
            'use_buildin_export'=>true,
            'orderBy' => 'inventory_apply_id desc',
        );

       $this->finder('console_mdl_inventory_apply',$params);
    }

    function do_confirm($apply_id=0){
        $objInAp = app::get('console')->model('inventory_apply');
        $main = $objInAp->db_dump(['inventory_apply_id'=>$apply_id], '*');
        if (empty($main)) {
            header("content-type:text/html; charset=utf-8");
            echo "<script>alert('盘点单不存在');window.close();</script>";
            exit;
        }
        if ($main['status'] == 'confirmed') {
            header("content-type:text/html; charset=utf-8");
            echo "<script>alert('盘点单已确认');window.close();</script>";
            exit;
        }
        $branch = app::get('ome')->model('branch')->db_dump(['branch_id'=>$main['branch_id']], 'name');
        if(empty($branch)) {
            header("content-type:text/html; charset=utf-8");
            echo "<script>alert('没有仓库权限');window.close();</script>";
            exit;
        }
        $main['branch_name'] = $branch['name'];
        $main['status'] = $objInAp->schema['columns']['status']['type'][$main['status']];
        $items = app::get('console')->model('inventory_apply_items')->getList('bm_id,material_bn,wms_stores,oms_stores,diff_stores,m_type',['inventory_apply_id'=>$apply_id, 'diff_stores|noequal'=>0]);
        $this->pagedata['main'] = $main;
        $this->pagedata['items'] = $items;
        $this->singlepage('admin/inventory/confirm_items.html');
    }

    function do_close($apply_id){
        $inv_aObj = app::get('console')->model('inventory_apply');
        $this->begin("index.php?app=console&ctl=admin_inventory_apply&act=index");
        $sdf = $inv_aObj->dump($apply_id);
        if ($sdf['status'] == 'confirmed' || $sdf['status'] == 'closed') $this->end(false, '盘点流水已确认或取消');
        $rs = $inv_aObj->update(array('status'=>'closed','process_date'=>time()),array('inventory_apply_id'=>$apply_id));
        if ($rs) {
            app::get('ome')->model('operation_log')->write_log('inventory_apply@console',$apply_id,"关闭成功");
            $this->end(true, '处理成功');
        }
        $this->end(false, '处理失败');
    }

    function finish_confirm(){
        $this->begin($this->url);
        $apply_id = (int) $_POST['inventory_apply_id'];
        $objInAp = app::get('console')->model('inventory_apply');
        $main = $objInAp->db_dump(['inventory_apply_id'=>$apply_id], 'status');
        if (!empty($main) && $main['status'] == 'confirmed') {
            $this->end(true, '已确认');
        }
        list($rs, $rsData) = kernel::single('console_inventory_apply')->confirm($apply_id);
        $this->end($rs, $rsData['msg']);
    }

    function do_view(){
        $this->singlepage("admin/inventory/view.html");
    }

    function do_choice($applySdf, $branch){
        $this->pagedata['finder_id']  = $_GET['finder_id'];
        $this->pagedata['apply'] = $applySdf;
        $this->pagedata['branch'] = $branch;

        $this->singlepage("admin/inventory/choice.html");
    }

    function finish_choice(){
        if ($_POST){
            $post = $_POST;
            if (!$post['branch']) return false;
            $_POST['apply_id'] = $post['inventory_apply_id'];
            $_POST['branch_bn'] = $post['branch'];
            $this->finish_confirm();
        }
    }

    function view_item(){
        $base_filter = array();
        if ($_GET['apply_id']){
            $base_filter = array('inventory_apply_id'=>$_GET['apply_id']);
        }
        $params = array(
            'title'=>'盘点申请详情',
            'use_buildin_recycle'=>false,
            'use_buildin_selectrow'=>false,
            'base_filter'=>$base_filter,
        );
        $this->finder('console_mdl_inventory_apply_items',$params);
    }
}
