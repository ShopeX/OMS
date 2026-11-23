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
class omeauto_ctl_autobranch extends omeauto_controller {

    var $workground = "setting_tools";

    function index() {

        #过滤o2o门店虚拟仓库
        $params = array(
            'title' => '仓库分配规则',
            'base_filter' => array('disabled' => 'false', 'is_deliv_branch' => 'true', 'b_type'=>1),
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag' => false,
            'use_buildin_recycle' => false,
            'use_buildin_export' => false,
            'use_buildin_import' => false,
            'use_buildin_filter' => false,
            'use_view_tab' => false,
            'finder_cols' => 'column_edit,name,branch_bn,column_order',
        );
        $this->finder('ome_mdl_branch', $params);
    }

    /**
     * 修改仓库对应的订单分组设置
     * 
     * @param integer $bid 仓库ID
     * @return void
     */
    function edit($bid) {
        $this->pagedata['data'] = app::get('ome')->model('branch')->dump(array('branch_id' => $bid));
        $this->pagedata['data']['area_conf'] = unserialize($this->pagedata['data']['area_conf']);
        $this->pagedata['orderType'] = $this->getOrderType();
        $this->page('autobranch/edit.html');
    }

    /**
     * 设置默认仓库
     * 
     * @param Integer $bid 仓库ID
     * @return void 
     */
    function setDefault($bid) {

        //$this->begin('');
        $bid = intval($bid);
        if ($bid && $bid > 0) {
            //全部取消缺省
            kernel::database()->query("update sdb_ome_branch set defaulted='false'");
            //全部取消缺省
            kernel::database()->query("update sdb_omeauto_order_type set bid=0 where bid={$bid}");
            //置指定仓库为缺省发货仓库
            kernel::database()->query("update sdb_ome_branch set defaulted='true',area_conf=''  where branch_id={$bid}");
        }
        //$this->end(true, '默认发货仓设置成功！！');
        echo "<script>alert('默认发货仓设置成功！！');top.finderGroup['{$_REQUEST['finder_id']}'].refresh();</script>";
    }
    
    /**
     * 取消默认仓库
     * 
     * @param Integer $bid 仓库ID
     * @return void 
     */
    function removeDefault($bid) {

        //$this->begin('');
        $bid = intval($bid);
        if ($bid && $bid > 0) {
            //全部取消缺省
            kernel::database()->query("update sdb_ome_branch set defaulted='false'");
            //全部取消缺省
            kernel::database()->query("update sdb_omeauto_order_type set bid=0 where bid={$bid}");
            //置指定仓库为缺省发货仓库
            kernel::database()->query("update sdb_ome_branch set defaulted='false',area_conf='N;'  where branch_id={$bid}");
        }
        //$this->end(true, '<script>alert('xxx');</script>取消默认发货仓成功！！');
        //echo '取消默认发货仓成功！！';
        echo "<script>alert('取消默认发货仓成功！！');top.finderGroup['{$_REQUEST['finder_id']}'].refresh();</script>";
    }

    /**
     * 保存仓库对应信息
     * 
     * @return void 
     */
    function save() {

        //$this->begin("index.php?app=omeauto&ctl=autoconfirm&act=index");
        $data = $_POST;
        if (empty($data['area_conf'])) {

            $data['area_conf'] = array();
        }
        $data['branch_id'] = intval($data['branch_id']);
        //修改
        if ($data['branch_id']) {
            kernel::database()->query("update sdb_omeauto_order_type set bid=0 where bid={$data['branch_id']}");
        }
        app::get('ome')->model('branch')->save($data);
        //更新订单类型相关表
        foreach ((array) $data['area_conf'] as $tid) {
            $tid = intval($tid);
            kernel::database()->query("update sdb_omeauto_order_type set bid={$data['branch_id']} where tid={$tid}");
        }

        echo "SUCC";
    }

    private function getOrderType() {

        $info = app::get('omeauto')->model('order_type')->getList('*', array('disabled' => 'false'), 0, -1);
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

}