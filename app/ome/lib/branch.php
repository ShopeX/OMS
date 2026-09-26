<?php
/**
* 仓库类
*
* @copyright shopex.cn 2013.4.10
* @author dongqiujin<123517746@qq.com>
*/
class ome_branch{

    /**
    * 获取仓库所关联的WMS
    *
    * @access public
    * @param String $branch_bn 仓库编号
    * @return String wms_id
    */
    public function getWmsId($branch_bn=''){
        $branchMdl = app::get('ome')->model('branch');
        $branch = $branchMdl->db->selectRow("select wms_id from sdb_ome_branch WHERE branch_bn='$branch_bn'");
        return isset($branch) ? $branch['wms_id'] : '';
    }

    /**
    * 获取WMS渠道列表
    *
    * @access public
    * @return Array
    */
    public function getWmsChannelList(){
        return kernel::single('channel_func')->getWmsChannelList();
    }

    /**
     *
     * 获取绑定过仓储类型的仓库列表
     * @access public
     * @return Array
     */
    public function getBindWmsBranchList(){
        $branchMdl = app::get('ome')->model('branch');
        $branch = $branchMdl->db->select("select * from sdb_ome_branch where wms_id > 0");
        return isset($branch) ? $branch : '';
    }

    /**
     *
     * 根据仓库ID获取仓库编码BN
     * @param string $branch_id 仓库ID
     * @return string $branch_bn 仓库编号
     */
    public function getBranchBnById($branch_id){
        $branchMdl = app::get('ome')->model('branch');
        $branch = $branchMdl->db->selectrow("select branch_bn from sdb_ome_branch WHERE branch_id=".$branch_id);
        return isset($branch) ? $branch['branch_bn'] : '';
    }

    /**
     *
     * 根据仓库ID获取仓库编码BN
     * @param string $branch_id 仓库ID
     * @return string $wms_id 仓库类型ID
     */
    public function getWmsIdById($branch_id){
        if(empty($branch_id)) {
            return '';
        }
        $branchMdl = app::get('ome')->model('branch');
        $branch = $branchMdl->db->selectrow("select wms_id from sdb_ome_branch WHERE branch_id='" . $branch_id . "'");
        return isset($branch) ? $branch['wms_id'] : '';
    }

    
    /**
     * 根据仓库ID返回节点类型.
     * @param
     * @return
     * @access  public
     * @author sunjing@shopex.cn
     */
    function getNodetypBybranchId($branch_id)
    {

        $wms_id = $this->getWmsIdById($branch_id);

        $channel_adapter = app::get('channel')->model('channel');
        $detail = $channel_adapter->dump(array('channel_id'=>$wms_id),'node_type');
        
        return $detail['node_type'];
    }

    /**
     *
     * 判断仓库是否是门店关联线下仓
     * @param string $branch_id 仓库ID
     * @return boolean true/false
     */
    function isStoreBranch($branch_id){
        if (empty($branch_id)) {
            return false;
        }
        if (is_array($branch_id)) {
            $branch = ' in ('.implode(',', $branch_id).')';
        } else {
            $branch = ' = '.$branch_id;
        }
        
        $branchObj = app::get('ome')->model('branch');
        
        //filter条件中加载了权限判断,修改为SQL语句读取
        $row    = $branchObj->db->selectrow("SELECT branch_id FROM sdb_ome_branch WHERE branch_id ". $branch ." AND b_type=2");
        //$row = $branchObj->getList('branch_id',array('branch_id'=>$branch_id, 'b_type'=>2), 0, 1);
        //o2o门店应用
        if(app::get('o2o')->is_installed())
        {
            $storeObj = app::get('o2o')->model('store');
            $row2 = $storeObj->getList('store_id',array('branch_id'=>$branch_id), 0, 1);
        }
        
        if($row && $row2){
            return $row2[0]['store_id'];
        }else{
            return false;
        }
    }

    /*
    * 获取自有仓储对应仓库列表
    *
    * return array
    */
    public function getBranchListBywms(){
        $branchMdl = app::get('ome')->model('branch');
        $sql = "SELECT b.branch_id FROM sdb_ome_branch as b LEFT JOIN sdb_channel_adapter as a ON b.wms_id=a.channel_id WHERE a.adapter = 'selfwms' AND b.type='main' AND b.is_deliv_branch='true'";
        $rows = $branchMdl->db->select($sql);
        $branch_list = array();
        $branch_list[] = 0;
        foreach($rows as $row){
            $branch_list[] = $row['branch_id'];
        }
        return $branch_list;
    }

    /**
     *
     * 根据仓库ID获取仓库信息默认返回全部
     * @param string $branch_id 仓库ID
     * @return array
     */
    public function getBranchInfo($branch_id,$field='*'){
        if(empty($branch_id)) {
            return [];
        }
        $branchMdl = app::get('ome')->model('branch');
        $branch = $branchMdl->db->selectrow('select ' . $field . ' from sdb_ome_branch WHERE branch_id='.$branch_id);
        return isset($branch) ? $branch : [];
    }

    /**
     * 根据仓库ID判断是否可合并
     * @param  string  $branchId 仓库ID
     * @return boolean
     */
    public function isCanMerge($branchId) {
        $wmsId = $this->getWmsIdById($branchId);
        if(!$wmsId) {
            return true;
        }
        $wms = app::get('channel')->model('channel')->dump($wmsId, 'config');
        $config = @unserialize($wms['config']);
        return $config && $config['node_type'] == 'yph' ? false : true;
    }
    
    /**
     * 根据仓库编码获取仓库信息
     * @todo：使用getList方法查询,filter条件会默认增加用户拥有的仓库权限
     *
     * @param array $branch_bn 仓库编号
     * @return array
     */
    public function getBranchByBns($branch_bns)
    {
        $branchObj = app::get('ome')->model('branch');
        
        //check
        if(empty($branch_bns)){
            return false;
        }
        
        //select
        $dataList = $branchObj->db->select("SELECT branch_id,branch_bn,wms_id,type,name FROM sdb_ome_branch WHERE branch_bn IN('". implode("','", $branch_bns) ."')");
        if(empty($dataList)){
            return false;
        }
        
        $branchList = array();
        foreach ($dataList as $key => $val)
        {
            $branch_bn = $val['branch_bn'];
            $branchList[$branch_bn] = $val;
        }
        
        return $branchList;
    }
    
    /**
     * 获取指定平台仓库编码
     *
     * @param string $type
     * @return array
     */
    public function getRelationBranch($type)
    {
        $branchObj = app::get('ome')->model('branch');
        $relationObj = app::get('ome')->model('branch_relation');
        
        //check
        if(empty($type)){
            return false;
        }
        
        //list
        $tempList = $relationObj->getList('*', array('type'=>$type));
        if(empty($tempList)){
            return false;
        }
        
        $relationList = array();
        foreach ($tempList as $key => $val)
        {
            $branch_id = $val['branch_id'];
            $relation_branch_bn = $val['relation_branch_bn'];
            
            //check
            if(empty($relation_branch_bn)){
                continue;
            }
            
            $relationList[$branch_id] = $relation_branch_bn;
        }
        
        //check
        if(empty($relationList)){
            return false;
        }
        
        //list
        $branchList = array();
        $tempList = $branchObj->getList('branch_id,branch_bn', array('branch_id'=>array_keys($relationList)));
        foreach ($tempList as $key => $val)
        {
            $branch_id = $val['branch_id'];
            $branch_bn = $val['branch_bn'];
            
            //平台仓库编码
            $val['relation_branch_bn'] = $relationList[$branch_id];
            
            $branchList[$branch_bn] = $val;
        }
        
        return $branchList;
    }
    
    /**
     * 获取已绑定抖音平台的区域仓(按branch_bn为下标)
     *
     * @param $shop_type 店铺平台
     * @return array
     */
    public function getLogisticWarehouse($shop_type=null)
    {
        $branchObj = app::get('ome')->model('branch');
        
        $sql = "SELECT a.warehouse_id,b.branch_bn FROM sdb_logisticsmanager_warehouse_shop AS a LEFT JOIN sdb_ome_branch AS b ON a.branch_id=b.branch_id ";
        $sql .= " WHERE a.outwarehouse_id!='' GROUP BY a.branch_id";
        $dataList = $branchObj->db->select($sql);
        if(empty($dataList)){
            return false;
        }
        
        $branchList = array();
        foreach ($dataList as $key => $val)
        {
            $branch_bn = $val['branch_bn'];
            if(empty($branch_bn)){
                continue;
            }
            
            $branchList[$branch_bn] = $val;
        }
        
        return $branchList;
    }
    
    /**
     * 获取已绑定抖音平台的区域仓(按branch_id为下标)
     *
     * @param $shop_type 店铺平台
     * @return array
     */
    public function getLogisticWarehouseIds($shop_type=null)
    {
        $branchObj = app::get('ome')->model('branch');
        
        $sql = "SELECT a.warehouse_id,b.branch_bn,b.branch_id,b.name FROM sdb_logisticsmanager_warehouse_shop AS a LEFT JOIN sdb_ome_branch AS b ON a.branch_id=b.branch_id ";
        $sql .= " WHERE a.outwarehouse_id!='' GROUP BY a.branch_id";
        $dataList = $branchObj->db->select($sql);
        if(empty($dataList)){
            return false;
        }
        
        $branchList = array();
        foreach ($dataList as $key => $val)
        {
            $branch_id = $val['branch_id'];
            if(empty($branch_id)){
                continue;
            }
            
            $branchList[$branch_id] = $val;
        }
        
        return $branchList;
    }
    
    /**
     * 获取指定WMS平台的仓库列表
     *
     * @param string $wms_type 仓储类型
     * return array
     */
    public function getWmsBranchIds($wms_type, &$error_msg=null)
    {
        $branchObj = app::get('ome')->model('branch');
        
        //获取京东一件代发WMS仓储
        $sql = "SELECT channel_id FROM sdb_channel_channel WHERE node_type='". $wms_type ."'";
        $tempList = $branchObj->db->select($sql);
        if(empty($tempList)){
            $error_msg = '没有获取到WMS仓储类型';
            return false;
        }
        
        $channel_ids = array();
        foreach ($tempList as $key => $val)
        {
            $channel_id = $val['channel_id'];
            
            $channel_ids[$channel_id] = $channel_id;
        }
        
        //仓库
        $sql = "SELECT branch_id,branch_bn FROM sdb_ome_branch WHERE wms_id IN(". implode(',', $channel_ids) .")";
        $tempList = $branchObj->db->select($sql);
        if(empty($tempList)){
            $error_msg = '没有获取到关联的仓库';
            return false;
        }
        
        $branchList = array_column($tempList, 'branch_bn', 'branch_id');
        
        return $branchList;
    }

    public function getBranchTypes(){
        $typeMdl = app::get('ome')->model('branch_type');

        $types = $typeMdl->getlist('type_name,type_code');

        $branchtypes = array();
        foreach($types as $v){
            $branchtypes[$v['type_code']] = $v['type_name'];
        }
        return $branchtypes;
    }
    
    /**
     * 获取门店对应仓库是否管控库存
     * @param $branch_id
     * @return bool
     * @date 2025-06-03 下午3:05
     */
    public function getBranchCtrlStore($branch_id)
    {
        $is_ctrl_store = true;
        $branch        = app::get('ome')->model('branch')->db_dump([
            'branch_id'        => $branch_id,
            'b_type'           => 2,
            'check_permission' => 'false',
        ]);
        if ($branch && $branch['is_ctrl_store'] == '2') {
            $is_ctrl_store = false;
        }
        
        return $is_ctrl_store;
    }

    /**
     * 获取仓库扩展字段配置
     * @return array 扩展字段配置列表
     */
    public function getcols(){
        $customcolsMdl = app::get('desktop')->model('customcols');
        $customcolslist = $customcolsMdl->getlist('col_name,col_key',array('tbl_name'=>'sdb_ome_branch'));
        
        if($customcolslist){
            return $customcolslist;
        }
        return array();
    }

    /**
     * 根据仓库ID获取门店发件人信息（WAP 录入运单号等）
     * @param int $branch_id
     * @return array|false
     */
    public function getStoreSenderInfoByBranchId($branch_id)
    {
        if (empty($branch_id)) {
            return false;
        }

        $branch_id = intval($branch_id);
        $fields = 'a.branch_id,a.branch_bn,a.store_id,b.store_bn,b.name,b.area,b.addr,b.contacter,b.mobile,b.tel,b.zip';
        $sql = "SELECT {$fields} FROM `sdb_ome_branch` AS a LEFT JOIN sdb_o2o_store AS b ON a.store_id = b.store_id"
            . " WHERE a.branch_id = {$branch_id}";
        $storeList = kernel::database()->select($sql);
        if (empty($storeList)) {
            return false;
        }

        $row = $storeList[0];
        $result = array(
            'branch_id' => $row['branch_id'],
            'branch_bn' => $row['branch_bn'],
            'store_id' => $row['store_id'],
            'store_bn' => $row['store_bn'],
            'store_name' => $row['name'],
            'consignee' => array(
                'name' => $row['contacter'],
                'area' => $row['area'],
                'addr' => $row['addr'],
                'telephone' => $row['tel'],
                'mobile' => $row['mobile'],
                'zip' => $row['zip'],
            ),
        );
        if (!empty($row['area']) && strpos($row['area'], ':') !== false) {
            list(, $to_area) = explode(':', $row['area']);
            $areaData = explode('/', $to_area);
            $result['consignee']['province'] = isset($areaData[0]) ? $areaData[0] : '';
            $result['consignee']['city'] = isset($areaData[1]) ? $areaData[1] : '';
            $result['consignee']['district'] = isset($areaData[2]) ? $areaData[2] : '';
            if (!empty($areaData[3])) {
                $result['consignee']['town'] = $areaData[3];
            }
        }
        return $result;
    }
}
