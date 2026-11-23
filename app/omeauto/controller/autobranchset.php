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

/**
 * 仓库分配规则
 * 
 * @version 0.1b
 * @author hzjsq
 */
class omeauto_ctl_autobranchset extends omeauto_controller {

    var $workground = "setting_tools";

    function index() {

        $params = array(
            'title' => '设置仓库分配规则',
            'actions' => array(
                array(
                    'label' => '新建',
                    'href' => 'index.php?app=omeauto&ctl=order_type&act=add&group_type=branch',
                    'target' => 'dialog::{width:700,height:480,title:\'新建分组规则\'}',
                ),
//                array(
//                    'label' => '新建',
//                    'href' => 'index.php?app=omeauto&ctl=autobranch&act=pre_add',
//                    'target' => 'dialog::{width:700,height:480,title:\'新建分组规则\'}',
//                ),
            ),
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag' => false,
            'use_buildin_recycle' => true,
            'use_buildin_export' => false,
            'use_buildin_import' => false,
            'use_buildin_filter' => false,
            'use_view_tab' => false,
            'base_filter' => array('group_type'=>'branch'),
            'finder_cols' => 'column_confirm,column_disabled,name,column_autoconfirm,column_autodispatch,column_autobranch,column_memo,column_order,column_content',
        );
        $this->finder('omeauto_mdl_order_type', $params);
    }

   
    /**
     * 保存仓库对应信息
     * 
     * @return void 
     */
    function save() {

        //$this->begin("index.php?app=omeauto&ctl=autoconfirm&act=index");
        $data = $_POST;
        $data['tid'] = intval($data['tid']);
        $autobranchObj = app::get('omeauto')->model('autobranch');
        $autobranch = $autobranchObj ->getlist('bid',array('tid'=>$data['tid']));
        $old_branch = array();
        foreach ($autobranch as $branch ) {
            $old_branch[] = $branch['bid'];
        }
       $delet_branch = array_diff($old_branch,(array)$data['bind_conf']);
        if (isset($data['bind_conf']) && is_array($data['bind_conf']) && count($data['bind_conf']) > 1) {
            $branchIds            = array_map('intval', array_values($data['bind_conf']));
            $branchList           = app::get('ome')->model('branch')->getList('branch_id,is_ctrl_store', ['branch_id' => $branchIds]);
            $is_ctrl_store_values = array_column($branchList, 'is_ctrl_store');
            // 去重后检查是否只有一种值
            $unique_ctrl_store = array_unique($is_ctrl_store_values);
            if (count($unique_ctrl_store) > 1) {
                echo "绑定的仓库中同时包含【管控库存】和【不管控库存】，请保持一致！";
                return true;
            }
        }
        
       foreach ( $delet_branch as $dv ) {
           $dv = intval($dv);
           kernel::database()->exec("delete from sdb_omeauto_autobranch  where tid={$data['tid']} AND bid={$dv}");
       }
       $is_default = array_flip((array)$data['is_default']);
       kernel::database()->query("update sdb_omeauto_order_type set bid=0 where tid=".$data['tid']);
       
        foreach ((array)$data['bind_conf'] as $k=>$v ) {
            $v = intval($v);
            if ($is_default[$v] =='0') {
                $branch_id = $v;
                
                kernel::database()->query("update sdb_omeauto_order_type set bid=".$branch_id." where tid=".$data['tid']);
            }
            $auto_data = array(
                    'tid'=>$data['tid'],
                    'bid'=>$v,
                    'weight'=>intval($data['weight'][$v]),
                    'is_default'=>$is_default[$v] === '0' ? '1' : '0',
                );
                
                app::get('omeauto')->model('autobranch')->save($auto_data);
        }
        
        $objBranchGet = app::get('omeauto')->model('autobranchget');
        $objBranchGet->delete(array('tid'=>$data['tid']));
        foreach ($data['classify'] as $v) {
            $inData = array(
                'tid'=>$data['tid'],
                'classify'=>$v,
                'weight'=>(int)$data[$v]['weight'],
            );
            $objBranchGet->insert($inData);
        }

        echo "SUCC";
    }

   
    
    /**
     *
     * @
     * @return  
     * @access  public
     * @author sunjing@shopex.cn
     */
    function pre_add()
    {
        $this->page('autobranch/pre_add.html');
    }
    
    /**
     * 设置绑定仓库
     * @param   
     * @return  
     * @access  public
     * @author sunjing@shopex.cn
     */
    
    function setBind($tid) {
        $branchObj = app::get('ome')->model('branch');
        $branchList = $branchObj->getList('*',array('disabled' => 'false','is_deliv_branch' => 'true','b_type' => '1'));
        // $branch = $branchObj->dump($branch_id);
        // $branch['bind_conf'] = unserialize($branch['bind_conf']);
        $autobranchObj = app::get('omeauto')->model('autobranch');
        $autobranch = $autobranchObj ->getlist('*',array('tid'=>$tid));
        $autobranch_list = array();
        foreach ($autobranch as $auto ) {
            $autobranch_list[$auto['bid']] = $auto;
        }
        $this->pagedata['autobranch_list'] = $autobranch_list;
        
        // 如果存在参加O2O的门店，则在列表中追加一条“参加O2O的门店”的特殊选项
        // 规则：先取门店类型仓库(b_type=2)的branch_id，再到o2o门店表中查询is_o2o=1是否存在
        $storeBranchList = $branchObj->getList('branch_id', array(
            'disabled' => 'false',
            'is_deliv_branch' => 'true',
            'b_type' => '2',
            'check_permission' => 'false',
        ));
        if ($storeBranchList) {
            $storeBranchIds = array();
            foreach ($storeBranchList as $sb) {
                if (isset($sb['branch_id'])) {
                    $storeBranchIds[] = $sb['branch_id'];
                }
            }
            if (!empty($storeBranchIds)) {
                $o2oStoreObj = app::get('o2o')->model('store');
                $o2oStores = $o2oStoreObj->getList('store_id', array(
                    'branch_id' => $storeBranchIds,
                    'is_o2o' => '1',
                ));
                if (!empty($o2oStores)) {
                    $branchList[] = array(
                        'branch_id' => -1,
                        'name' => '参加O2O的门店',
                    );
                }
            }
        }
        
        foreach ( $branchList as $k=>$v ) {
            if ($autobranch_list[$v['branch_id']]) {
                $branchList[$k]['checked'] = '1';
                $branchList[$k]['auto_weight'] = $autobranch_list[$v['branch_id']]['weight'];
            }
        }
        // $this->pagedata['branch'] = $branch;
        $this->pagedata['branchList'] = $branchList;
        $order_typeObj = app::get('omeauto')->model('order_type');
        $order_type = $order_typeObj->dump($tid,'*');
        $this->pagedata['order_type'] = $order_type;
        $objBranchGet = app::get('omeauto')->model('autobranchget');
        $bg = $objBranchGet->getList('*', array('tid'=>$tid));
        $classify = array();
        foreach ($bg as $v) {
            $classify[$v['classify']] = $v;
        }
        $this->pagedata['classify'] = $classify;
        unset($order_type);
        unset($autobranch);
        $this->page('autobranch/setBind.html');
    }
}