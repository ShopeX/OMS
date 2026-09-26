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

class omeauto_ctl_autoconfirm extends omeauto_controller {

    var $workground = "setting_tools";
    
    function index() {
        $params = array(
            'title' => '自动审单规则',
            'actions' => array(
                array(
                    'label' => '新建',
                    'href' => 'index.php?app=omeauto&ctl=autoconfirm&act=add',
                    'target' => 'dialog::{width:700,height:480,title:\'新建审单规则\'}',
                )
            ),
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag' => false,
            'use_buildin_recycle' => true,
            'use_buildin_export' => false,
            'use_buildin_import' => false,
            'use_buildin_filter' => false,
            'use_view_tab' => false,
            'finder_cols' => 'column_confirm,column_disabled,name,column_order,column_content',
        );
        $this->finder('omeauto_mdl_autoconfirm', $params);
    }

    function add() {
        $this->_edit();
    }

    function edit($oid) {

        $this->pagedata['data'] = app::get('omeauto')->model('autoconfirm')->dump(array('oid' => $oid), '*');
	
	    if ($this->pagedata['data']['config']['corp_id']) {
		    $this->pagedata['corp'] = app::get('ome')->model('dly_corp')->db_dump($this->pagedata['data']['config']['corp_id']);
	    }
	    
        $this->_edit($oid);
    }

    private function _edit($oid=NULL) {

        $this->pagedata['orderType'] = $this->getOrderType();
        $this->page('autoconfirm/add.html');
    }

    function do_add()
    {
        $data = $_POST;
        
        $result = array('rsp'=>'fail', 'error_msg'=>'');
        
        //时间范围
        $error_msg = '';
        $res = $this->_formatConfirmData($data, $error_msg);
        if(!$res){
            $result['error_msg'] = '错误：'. $error_msg;
            echo(json_encode($result));
            exit;
        }
        
        //修改
        if ($data['oid']) {
             kernel::database()->query(sprintf("update sdb_omeauto_order_type set oid=0 where oid=%s", $data["oid"]));
        }
        app::get('omeauto')->model('autoconfirm')->save($data);
        
        //更新订单类型相关表
        foreach( (array)$data['config']['autoOrders'] as $tid)
        {
            kernel::database()->query(sprintf("update sdb_omeauto_order_type set oid=%s where tid=%s", $data["oid"], $tid));
        }
        
        $result['rsp'] = 'succ';
        echo(json_encode($result));
        exit;
    }
    
    function setStatus($oid, $status) {
        
        if ($status == 'true') {
            $disabled = 'false';
        } else {
            $disabled = 'true';
        }
        kernel::database()->query("update sdb_omeauto_autoconfirm set disabled='{$disabled}' where oid={$oid}");
        
        echo "<script>parent.MessageBox.success('命令已经被成功发送！！');parent.finderGroup['{$_GET['finder_id']}'].refresh();</script>";
        exit;
    }
    
    function setDefaulted($oid) {
        
        if ($oid && $oid > 0) {
            $confirmObj = app::get('omeauto')->model('autoconfirm');
            $data = $confirmObj->dump($oid, 'oid,config');
            unset($data['config']['autoOrders']);
            $upData = array(
                'defaulted'=>'true',
                'config'=>$data['config'],
            );
            //全部取消缺省
            kernel::database()->query("update sdb_omeauto_autoconfirm set defaulted='false'");
            //全部取消缺省
            kernel::database()->query("update sdb_omeauto_order_type set oid=0 where oid={$oid}");
            //置指定仓库为缺省发货仓库
            $confirmObj->update($upData,array('oid'=>$oid));
        }
        //$this->end(true, '默认发货仓设置成功！！');
        echo "<script>alert('默认审单规则设置成功！！');top.finderGroup['{$_REQUEST['finder_id']}'].refresh();</script>";
    }
    
    function removeDefaulted($oid) {
        
        if ($oid && $oid > 0) {
            //置指定仓库为缺省发货仓库
            kernel::database()->query("update sdb_omeauto_autoconfirm set defaulted='false' where oid={$oid}");
        }
        echo "<script>alert('取消默认审单规则设置成功！！');top.finderGroup['{$_REQUEST['finder_id']}'].refresh();</script>";
    }

    private function getOrderType() {
        
        $info = app::get('omeauto')->model('order_type')->getList('*', array('disabled' => 'false','group_type'=>'order'), 0, -1);
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
    
    /**
     * 格式化POST提交审单规则数据
     * 
     * @param array $data
     * @param string $error_msg
     * @return bool
     */
    public function _formatConfirmData(&$data, &$error_msg=null)
    {
        //check
        if(empty($data['confirmStartTime']) || empty($data['confirmEndTime'])){
            $error_msg = '请设置生效时间范围';
            return false;
        }
        
        //生效时间范围
        $confirmStartTime = strtotime($data['confirmStartTime'] . ' ' . $data['_DTIME_']['H']['confirmStartTime'] . ':' . $data['_DTIME_']['M']['confirmStartTime']);
        $confirmEndTime = strtotime($data['confirmEndTime'] . ' ' . $data['_DTIME_']['H']['confirmEndTime'] . ':' . $data['_DTIME_']['M']['confirmEndTime']);
        
        if(empty($confirmStartTime) || empty($confirmEndTime)){
            $error_msg = '生效时间范围设置错误,请检查';
            return false;
        }
        
        //排除时间范围
        $excludeStartTime = $excludeEndTime = 0;
        if($data['excludeStartTime'] && $data['excludeEndTime']){
            $excludeStartTime = strtotime($data['excludeStartTime'] . ' ' . $data['_DTIME_']['H']['excludeStartTime'] . ':' . $data['_DTIME_']['M']['excludeStartTime']);
            $excludeEndTime = strtotime($data['excludeEndTime'] . ' ' . $data['_DTIME_']['H']['excludeEndTime'] . ':' . $data['_DTIME_']['M']['excludeEndTime']);
        }
        
        if(empty($excludeStartTime) || empty($excludeEndTime)){
            $excludeStartTime = $excludeEndTime = 0;
        }
        
        //check
        if($confirmStartTime > $confirmEndTime){
            $error_msg = '(生效时间范围)开始时间不能大于结束时间';
            return false;
        }
        
        $diff_time = $confirmEndTime - $confirmStartTime;
        if($diff_time < 3600){
            $error_msg = '(生效时间范围)时间范围不能小于1个小时';
            return false;
        }
        
        if($excludeStartTime && $excludeEndTime){
            if($excludeStartTime > $excludeEndTime){
                $error_msg = '(排除时间范围)开始时间不能大于结束时间';
                return false;
            }
            
            $diff_time = $excludeEndTime - $excludeStartTime;
            if($diff_time < 3600){
                $error_msg = '(排除时间范围)时间范围不能小于1个小时';
                return false;
            }
            
            if($excludeStartTime <= $confirmStartTime){
                $error_msg = '排除时间范围不得早于生效时间范围';
                return false;
            }
            
            if($excludeEndTime >= $confirmEndTime){
                $error_msg = '排除时间范围不得晚于生效时间范围';
                return false;
            }
        }
        
        //data
        if($confirmStartTime && $confirmEndTime){
            $data['config']['confirmStartTime'] = $confirmStartTime;
            $data['config']['confirmEndTime'] = $confirmEndTime;
        }else{
            unset($data['config']['confirmStartTime'], $data['config']['confirmEndTime']);
        }
        
        if($excludeStartTime && $excludeEndTime){
            $data['config']['excludeStartTime'] = $excludeStartTime;
            $data['config']['excludeEndTime'] = $excludeEndTime;
        }else{
            unset($data['config']['excludeStartTime'], $data['config']['excludeEndTime']);
        }
        
        $data['config']['member_remark'] = isset($data['config']['member_remark']) ? trim($data['config']['member_remark']) : '';
        $data['config']['order_remark'] = isset($data['config']['order_remark']) ? trim($data['config']['order_remark']) : '';
        if ((!isset($data['config']['memo'])) || $data['config']['memo'] != '1') {
            unset($data['config']['member_remark']);
        }
        
        if ((!isset($data['config']['mark'])) || $data['config']['mark'] != '1') {
            unset($data['config']['order_remark']);
        }
        
        unset($data['confirmStartTime'], $data['confirmEndTime'], $data['excludeStartTime'], $data['excludeEndTime'], $data['_DTIME_'], $data['_DTYPE_TIME']);
        
        return true;
    }
	
	/**
	 * 选择指定物流审单
	 */
	function showbranchcorp(){
		$branchMdl = app::get('ome')->model('branch');
		
		$branches = array();
		foreach ($branchMdl->getList('branch_id,name', array('disabled' => 'false','is_deliv_branch'=>'true')) as $value) {
			$value['corps']['auto'] = '智能优化';
			
			$branches[$value['branch_id']] = $value;
		}
		
		$corpMdl = app::get('ome')->model('dly_corp');
		$corps = $corpMdl->getList('corp_id,name,branch_id,all_branch',array('disabled'=>'false'));
		if ($branches) {
			foreach ($corps as $value) {
				if ($value['all_branch'] == 'true') {
					foreach ($branches as $k => $v) {
						$branches[$k]['corps'][$value['corp_id']] = $value['name'];
					}
					
					continue;
				}
				
				
				if ($value['branch_id']) {
					foreach (explode(',', $value['branch_id']) as $k => $v) {
						if ($branches[$v]) $branches[$v]['corps'][$value['corp_id']] = $value['name'];
					}
				}
			}
		}
        
        $branch_corp = array();
        if($_POST['branch_corp']){
            $branch_corp = json_decode($_POST['branch_corp'], true);
        }
        
        $this->pagedata['branch_corp'] = $branch_corp;
		$this->pagedata['common_corp'] = $_POST['common_corp'];
		$this->pagedata['corps']       = $corps;
		
		$this->pagedata['branches'] = $branches;
		$this->display('autoconfirm/branch_corp.html');
	}
}
