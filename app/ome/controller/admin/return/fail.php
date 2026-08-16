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
class ome_ctl_admin_return_fail extends desktop_controller {

    var $name = "同步失败售后申请";
    var $workground = "aftersale_center";
   
    function index(){
        $base_filter = array('is_fail'=>'true','status|notin'=>array('5'));

        //check shop permission
        $organization_permissions = kernel::single('desktop_user')->get_organization_permission();
        if($organization_permissions){
            $base_filter['org_id'] = $organization_permissions;
        }
        
        // action
        $actions = [];
        
        $params = array(
            'title'=>'同步失败售后申请',
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'=>false,
            'use_buildin_recycle'=>false,
            'use_buildin_export'=>false,
            'use_buildin_import'=>false,
            'use_buildin_filter'=>true,
            'base_filter'=>$base_filter,
            'actions' => $actions,
            'finder_cols'=>'return_bn,return_id,order_id,title,content,add_time,shop_id,member_id,memo,money',
        );
        $this->finder ( 'ome_mdl_return_fail' , $params );
    }

    /**
     * 修复售后申请货号
     * @author sunjing@shopex.cn
     */
    function dosave(){
        $url = 'index.php?app=ome&ctl=admin_return_fail&act=index';
        $pbn = $_POST['pbn'];
        $oldPbn = $_POST['oldPbn'];        
        $return_id = $_POST['return_id'];
        if (!$pbn) {
            $this->splash('error',$url,'请输入对应修复货号！');
        }
       //修正订单项
        if(kernel::single("ome_return_fail")->modifyReturnItems($return_id,$oldPbn,$pbn)){
            $this->splash('success',$url,'申请单处理成功');
        }else{
            $this->splash('error',$url,'存在异常商品，申请修正失败！');
        }
    }
    
    /**
     * 拒绝失败售后申请.
     * @author sunjing@shopex.cn
     */
    function refuse($return_id){
        $data = array('rsp'=>'succ','msg'=>'成功');
        $returnObj = app::get('ome')->model('return_product');
        $return = $returnObj->dump($return_id,'is_fail,status');
        if ($return['is_fail'] == 'fase') {
            $data = array('rsp'=>'fail','msg'=>'当前不可以拒绝!');
        }
        if (in_array($return['status'],array('3','4','5'))) {
            $data = array('rsp'=>'fail','msg'=>'当前状态不可以拒绝!');
        }
        $up_data = array ('return_id' => $return_id, 'status' => '5', 'last_modified' => time () );
        $result = $returnObj->save( $up_data );
        if (!$result) {
            $data = array('rsp'=>'fail','msg'=>'拒绝失败!');
        }
        echo json_encode($data);
    }

}

?>
