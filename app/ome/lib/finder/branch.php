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

class ome_finder_branch {
    /**
     * 是否仓库配置
     * 
     * @var boolean
     */
    private $isConfig = false;

    /**
     * 订单分组缓存
     * 
     * @var array 
     */
    static $orderTypes = null;

    /**
     * 析构
     */
    function __construct() {
        //根据APP做判断
        if ($_REQUEST['app'] == 'ome') {
            $this->isConfig = false;
        } else {
            $this->isConfig = true;
        }

        if (self::$orderTypes === null) {
            $types = app::get('omeauto')->model('order_type')->getList('tid,name,disabled');
            foreach ((array) $types as $t) {
                self::$orderTypes[$t['tid']] = $t;
            }
        }
    }

    var $detail_basic = "仓库详情";
    function detail_basic($branch_id) {
        $render = app::get('ome')->render();
        $branchObj = app::get('ome')->model('branch');

        $render->pagedata['branch'] = $branchObj->dump($branch_id);

        return $render->fetch('admin/system/branch_detail.html');
    }

    var $addon_cols = "branch_id,area_conf,defaulted,wms_id,cutoff_time,latest_delivery_time,parent_id";
    var $column_edit = "操作";
    var $column_edit_width = "100";
    function column_edit($row) {
        $finder_id = $_GET['_finder']['finder_id'];
        $ret = '';
        if (!$this->isConfig) {
              return '<a href="index.php?app=ome&ctl=admin_branch&act=editbranch&p[0]=' . $row[$this->col_prefix . 'branch_id'] . '&p[1]=true&_finder[finder_id]=' . $finder_id . '&finder_id=' . $finder_id . '&finder_vid='.$_GET['finder_vid'].'">编辑</a>';
        } else {
            if ($row['_0_defaulted'] == 'false') {
                $ret .= "&nbsp;&nbsp;<a href='index.php?app=omeauto&ctl=autobranch&act=setDefault&p[0]={$row[$this->col_prefix . 'branch_id']}&finder_id={$finder_id}' target='download'>默认</a>";
            } else {
                $ret .= "&nbsp;&nbsp;<a href='index.php?app=omeauto&ctl=autobranch&act=removeDefault&p[0]={$row[$this->col_prefix . 'branch_id']}&finder_id={$finder_id}' target='download'>取消默认</a>";
            }
            
            return $ret;
        }
    }

    var $column_owner = '仓库归属';
    function column_owner($row){
        $branch_model  = app::get('ome')->model('branch');
        $channel_model = app::get('channel')->model('channel');
        $branchs = $branch_model->dump(array('branch_id' => $row['branch_id']), 'wms_id, owner');
        $node_type = $channel_model->dump(array('channel_id' => $branchs['wms_id']), 'node_type');
        if($node_type['node_type'] == 'selfwms'){
            if ($branchs['owner'] == 1) {
                return '自建仓库';
            } elseif($branchs['owner'] == 2) {
                return '第三方仓库';
            }else{
                return '平台自发仓库';
            }
        } else {
            return '-';
        }
    }

    public $detail_branch = '平台仓库编码配置';
    /**
     * detail_branch
     * @param mixed $branchId ID
     * @return mixed 返回值
     */
    public function detail_branch($branchId){
        $render = app::get('ome')->render();
        $oBranch_relation = app::get('ome')->model('branch_relation');
        if($_POST['branch']){
            foreach($_POST['branch'] as $k=>$v){
                $sdata = array('branch_id'=>$branchId,'relation_branch_bn'=>trim($v['branch_bn']),'type'=>$k);
                $old = $oBranch_relation->getList('id',
                    array('branch_id'=>$branchId,'type'=>$k),0,1,'id desc');
                if($old) {
                    $oBranch_relation->update($sdata, array('id'=>$old[0]['id']));
                } else {
                    $oBranch_relation->insert($sdata);
                }
            }
        }
        $branchRelation = array();
        $rows = $oBranch_relation->getlist('*',array('branch_id'=>$branchId));
        foreach ($rows as $key => $value) {
            $branchRelation[$value['type']] = $value;
        }

        $render->pagedata['branch_relation'] = $branchRelation;
        return $render->fetch("admin/system/branch_config.html");
    }
    
    public $detail_wms_branch = '仓库对应WMS配置';
    public function detail_wms_branch($branchId)
    {
        $render = app::get('ome')->render();
        $oBranch = app::get('ome')->model('branch');
        $oBranchWms = app::get('ome')->model('branch_wms_channel');
        $oChannel = app::get('channel')->model('channel');

        if ($_POST) {
            $updateId = array();
            foreach ((array) $_POST['shop_type'] as $k => $shopType) {
                $channelId = $_POST['channel_id'][$k];
                if ($shopType === '' || $shopType === null || !$channelId) {
                    continue;
                }
                $row = $oBranchWms->db_dump(
                    array('branch_id' => $branchId, 'shop_type' => $shopType),
                    'id,channel_id'
                );
                if ($row) {
                    if ($row['channel_id'] != $channelId) {
                        $oBranchWms->update(array('channel_id' => $channelId), array('id' => $row['id']));
                    }
                    $updateId[] = $row['id'];
                } else {
                    $iData = array(
                        'branch_id'  => $branchId,
                        'shop_type'  => $shopType,
                        'channel_id' => $channelId,
                    );
                    $oBranchWms->insert($iData);
                    $updateId[] = $iData['id'];
                }
            }
            $filter = array('branch_id' => $branchId);
            if ($updateId) {
                $filter['id|notin'] = $updateId;
            }
            $oBranchWms->delete($filter);
        }

        $branch = $oBranch->dump($branchId, 'branch_id,wms_id');
        $defaultWmsName = '-';
        if (!empty($branch['wms_id'])) {
            $defaultChannel = $oChannel->dump(array('channel_id' => $branch['wms_id']), 'channel_name');
            if ($defaultChannel) {
                $defaultWmsName = $defaultChannel['channel_name'];
            }
        }

        // 仅已绑定（node_id 非空），排除自有仓 selfwms
        $wmsList = $oChannel->getList(
            'channel_id,channel_name',
            array(
                'channel_type' => 'wms',
                'node_id|notin' => array('selfwms'),
                'node_type|noequal' => 'selfwms',
                'filter_sql' => "node_id IS NOT NULL AND node_id != ''",
            )
        );
        
        // 平台类型列表
        $shopTypeList = ome_shop_type::get_shop_type();
        
        // 删除掉平台类型：shopex_b2c、shopex_b2b、ecos.b2c、ecos.dzg
        unset($shopTypeList['shopex_b2c'], $shopTypeList['shopex_b2b'], $shopTypeList['ecos.b2c'], $shopTypeList['ecos.dzg']);
        
        // 删除掉平台类型：paipai、yihaodian
        unset($shopTypeList['paipai'], $shopTypeList['yihaodian']);
        
        $render->pagedata['shop_type'] = $shopTypeList;
        $render->pagedata['wms_list'] = $wmsList;
        $render->pagedata['default_wms_name'] = $defaultWmsName;
        $render->pagedata['branch_wms_channel'] = $oBranchWms->getList('*', array('branch_id' => $branchId));
        
        return $render->fetch('admin/system/branch_wms_channel.html');
    }

    var $column_wms = 'WMS名称';
    function column_wms($row){
        $wms_id = $row[$this->col_prefix.'wms_id'];
        $channelMdl = app::get('channel')->model('channel');
        $channel = $channelMdl->dump(array('channel_id' => $wms_id), 'channel_name');
        if($channel){
            return $channel['channel_name'];
        } else {
            return '-';
        }
    }
    
    var $column_cutoff_time = '截单时间';
    var $column_cutoff_time_width = "80";
    function column_cutoff_time($row){
        $cutoff_time = $row[$this->col_prefix.'cutoff_time'];
        if($cutoff_time){
            $hour = intval(substr($cutoff_time, 0, 2));
            $minute = intval(substr($cutoff_time, 2, 2));
            $str = $hour.'点'.$minute.'分';
        }else{
            $str = '';
        }
        
        return $str;
    }

    var $column_latest_delivery_time = '最晚出库时间';
    var $column_latest_delivery_time_width = "90";
    function column_latest_delivery_time($row){
        $time = $row[$this->col_prefix.'latest_delivery_time'];
        if($time){
            $hour = intval(substr($time, 0, 2));
            $minute = intval(substr($time, 2, 2));
            $str = $hour.'点'.$minute.'分';
        }else{
            $str = '';
        }
        return $str;
    }

    var $column_parent = '关联主仓';
    var $column_parent_width = "120";
    function column_parent($row, $list){
        $parent_id = $row[$this->col_prefix.'parent_id'];
        if($parent_id && $parent_id > 0){
            $parent_branch = $this->_getParentBranch($parent_id, $list);
            if($parent_branch){
                return $parent_branch['branch_bn'];
            } else {
                return '主仓ID: ' . $parent_id;
            }
        } else {
            return '-';
        }
    }

    private function _getParentBranch($parent_id, $list)
    {
        static $parentBranchList;
        
        if (isset($parentBranchList)) {
            return $parentBranchList[$parent_id];
        }
        
        $parentBranchList = [];
        $parent_ids = array();
        
        // 收集所有需要查询的parent_id
        foreach($list as $val) {
            $pid = $val[$this->col_prefix.'parent_id'];
            if($pid && $pid > 0) {
                $parent_ids[] = $pid;
            }
        }
        
        if($parent_ids) {
            $parent_ids = array_unique($parent_ids);
            $branchObj = app::get('ome')->model('branch');
            $branchList = $branchObj->getList('branch_id,branch_bn,name', array('branch_id' => $parent_ids));
            $parentBranchList = array_column($branchList, null, 'branch_id');
        }
        
        return $parentBranchList[$parent_id];
    }
}