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

class iostock_mdl_iostocksearch extends iostock_mdl_iostock{

    var $has_export_cnf = true;

    public $export_flag = false;
    public $export_name = '出入库明细';
     //所用户信息
    static $__USERS = null;
    function searchOptions(){
        $arr = parent::searchOptions();
        return array_merge($arr,array(
				'original_bn'=>__('原始单据号'),
                'iostock_bn'=>__('出入库单号'),
                'bn'=>__('基础物料编码'),
                'appropriation_no'=>__('调拨单号'),
            ));
    }


    function io_title( $filter=null,$ioType='csv'){
        switch( $ioType ){
            case 'csv':
            default:
                $this->oSchema['csv']['main'] = array(
                    '*:仓库编号' => 'branch_bn',
                    '*:仓库名称' => 'branch_name',
                    '*:出入库单号' => 'iostock_bn',
                    '*:出入库类型' => 'iostock_type',
                    '*:原始单据号' => 'original_bn',
                    '*:发货单号' => 'delivery_bn',
                    '*:供应商/用户名/公司名称' => 'supplier_name',
                    '*:店铺编码' => 'shop_bn',
                    '*:店铺名称' => 'shop_name',
                    '*:基础物料编码' => 'bn',
                    '*:货品名称' => 'bn_name',
                    '*:出入库数量' => 'nums',
                    '*:出入库价格' => 'iostock_price',
                    '*:经手人' => 'oper',
                    '*:出入库时间' => 'create_time',
                    '*:操作员' => 'operator',
                    '*:备注' => 'memo',
                    '*:订单号' => 'order_bn',
                    '*:结存数量'=>'now_num',
                    '*:规格'=>'spec_value'
                );
        }
        #增加导出列
        if($this->export_flag){
            $title = array(
                    '*出入单名称'=>'name',
                    '*库存结存'=>'balance_nums'
                    );
            $this->oSchema['csv']['main'] = array_merge($this->oSchema['csv']['main'],$title);
        }
        $this->ioTitle[$ioType][$filter] = array_keys( $this->oSchema[$ioType]['main'] );
        return $this->ioTitle[$ioType][$filter];
    }


     //csv导出
     function fgetlist_csv( &$data,$filter,$offset,$exportType = 1 ){
        $this->export_flag = true;
        $obj_iostockOrder = app::get('taoguaniostockorder')->model('iso');
		@ini_set('memory_limit','1024M');
        if( !$data['title']['main'] ){
            $title = array();
            foreach( $this->io_title('main') as $k => $v ){
                $title[] = $this->charset->utf2local($v);
            }
            $data['title']['main'] = '"'.implode('","',$title).'"';
        }

        $this->iostock = app::get('ome')->model('iostock');
        $this->iostock->filter_use_like = true;
        $limit = 100;

        //bugfix 修正出入库明细导出全部数据的时候branch_id引起的查询结果为空的问题
        if($filter['branch_id'] < 1){
            unset($filter['branch_id']);
        }

        if( !$list=$this->iostock->getList('*',$filter,$offset*$limit,$limit) )return false;
        $branchObj = app::get('ome')->model('branch');
        $ioTypeObj = app::get('ome')->model('iostock_type');
        $basicMaterialObj = app::get('material')->model('basic_material');
        $orderObJ = app::get('ome')->model('orders');
        $_originalId = array();$id_bn = array();
        foreach($list as $k=>$val){
            if(3 == $val['type_id']){
                if(isset($val['original_id'])){
                    //获取本次导出记录所有的original_id
                    $_originalId[] = $val['original_id'];
                }
            }
        }
        $_count = count($_originalId);
        if($_count > 1){
            $_originalId = array_unique($_originalId);
            $_originalId = implode(',', $_originalId);//包含分割一个数组元素
        }elseif(1 == $_count){
            $_originalId = $_originalId[0];
        }
        
        if($_originalId){
            //获取本次导出记录所有销售订单号
            $_orderInfo = $orderObJ->getOrdersBnById($_originalId);
        }

        //生成成delivery_id和order_bn的键值对
        foreach( (array) $_orderInfo as $_val){
            if(array_key_exists($_val['delivery_id'],$id_bn)){
                //把同一个delivery_id对应的多个order_bn逗号拼接起来
                $id_bn[$_val['delivery_id']] = $id_bn[$_val['delivery_id']].' , '.$_val['order_bn'];
            }else{
                $id_bn[$_val['delivery_id']] = $_val['order_bn'];
            }
        }
        foreach( $list as $aFilter ){
            
            $branch = $branchObj->dump(array('branch_id'=>$aFilter['branch_id']),'branch_bn,name');
            $iostock_type = $ioTypeObj->dump(array('type_id'=>$aFilter['type_id']),'type_name');
            $product_info = $basicMaterialObj->dump(array('material_bn'=>$aFilter['bn']),'material_name');
            if(3 ==$aFilter['type_id']){
                if(isset($aFilter['original_id'])){
                    $aFilter['order_bn'] = (string)$id_bn[$aFilter['original_id']];
                }
            }
            #不属于销售出库时，获取出入单名称
            if(3 !=$aFilter['type_id']){
                $iostock_order_info = $obj_iostockOrder->dump($aFilter['original_id'],'name');
                if(!empty($iostock_order_info['name'])){
                    $aFilter['name'] =  $iostock_order_info['name'];
                }
            }
          
            if( !$aFilter )continue;
            $aFilter['branch_bn'] =  $branch['branch_bn'];
            $aFilter['branch_name'] = $branch['name'];
            $aFilter['iostock_type'] = $iostock_type['type_name'];
            $aFilter['bn_name']  = $product_info['material_name'];
            $original_bn = $aFilter['original_bn'];
            //处理货品多规格值 新物料版本暂时没这数据注释掉 xiayuanjun
            //$spec_value = implode('|',(array) $product_info['spec_desc']['spec_value']);
            $aFilter['spec_value']  = '';
            $aFilter['create_time'] = date('Y-m-d H:i:s',$aFilter['create_time']);
            $aFilter['original_bn'] = "=\"\"".$aFilter['original_bn']."\"\"";
            $aFilter['order_bn'] = "=\"\"".$aFilter['order_bn']."\"\"";
            $aFilter['delivery_bn'] = '';
            //退货入库取发货单号
            if (in_array($aFilter['type_id'],array('30'))) {
                //
                $delivery_bn = $this->getDeliveryByreshipId($original_bn);
                $delivery_bn = $delivery_bn ? "=\"\"".$delivery_bn."\"\"" : '';
                $aFilter['delivery_bn'] = $delivery_bn;
            }
            foreach( $this->oSchema['csv']['main'] as $k => $v ){
                $iostockRow[$k] = $this->charset->utf2local($aFilter[$v]);
            }
            $data['content']['main'][] = '"'.implode('","',$iostockRow).'"';
        }
        $data['name'] = 'iostock' . date('YmdHis');
        return true;
    }

    function export_csv($data,$exportType = 1 ){
        $output = array();
      //  if( $exportType == 2 ){
            foreach( $data['title'] as $k => $val ){
                $output[] = $val."\n".implode("\n",(array)$data['content'][$k]);
            }
      //  }
        echo implode("\n",$output);
    }


    /**
     * _filter
     * @param mixed $filter filter
     * @param mixed $tableAlias tableAlias
     * @param mixed $baseWhere baseWhere
     * @return mixed 返回值
     */
    public function _filter($filter, $tableAlias=null, $baseWhere=null) {
        /*
        $op_id = kernel::single('desktop_user')->get_id();
        if ($op_id) {//默认拥有所有仓库权限
            $is_super = kernel::single('desktop_user')->is_super();
            if (!$is_super) {
                $branch_ids = app::get('ome')->model('branch')->getBranchByUser(true);
                if ($branch_ids) {
                    if ($filter['branch_id'] && !is_array($filter['branch_id']) && in_array($filter['branch_id'], $branch_ids)) {
                        $filter['branch_id'] = $filter['branch_id'];
                    } elseif ($filter['branch_id'] && is_array($filter['branch_id'])) {
                        $realIds = array();
                        foreach($filter['branch_id'] as $id) {
                            if (in_array($id,  $branch_ids)) {
                                $realIds[] = $id;
                            }
                        }
                        if (!empty($realIds)) {
                            $filter['branch_id'] = $realIds;
                        } else {
                            $filter['branch_id'] = $branch_ids;
                        }
                    } else {
                        $filter['branch_id'] = $branch_ids;
                    }
                } else {
                    $filter['branch_id'] = 'false';
                }
            }
        }
        */
        
        //扩展字段 调拨单号右上角筛选
        if(isset($filter['appropriation_no'])){
            $taoguaniostockorder_iso_obj = app::get('taoguaniostockorder')->model('iso');
            $taoguaniostockorder_iso_infos = $taoguaniostockorder_iso_obj->getList("iso_id",array("appropriation_no"=>$filter['appropriation_no']));
            $filter["original_id"] = array();
            foreach ($taoguaniostockorder_iso_infos as $var_taoguaniostockorder_iso_info){
                $filter["original_id"][]=$var_taoguaniostockorder_iso_info["iso_id"];
            }
            unset($filter['appropriation_no']);
        }

        // 处理name字段查询 - 通过基础物料ID查询
        if(isset($filter['name']) && $filter['name']){
            $basicMaterialObj = app::get('material')->model('basic_material');
            $material_list = $basicMaterialObj->getList('material_bn', array('bm_id' => $filter['name']));
            if($material_list){
                $material_bns = array_column($material_list, 'material_bn');
                $filter['bn|in'] = $material_bns;
            } else {
                // 如果没有找到匹配的物料ID，设置一个不可能的条件
                $filter['iostock_id'] = 'false';
            }
            unset($filter['name']);
        }
        
        return parent::_filter($filter, $tableAlias, $baseWhere);
    }

    /**
     * count
     * @param mixed $filter filter
     * @return mixed 返回值
     */
    public function count($filter=null){
        foreach ($filter as $k => $v) {
            if (!is_array($v) && $v !== false) $filter[$k] = trim($v);
            if ($filter[$k] === '') {
                unset($filter[$k]);
            }
        }

        $count  = parent::count($filter);
        return $count;
    }

    /**
     * modifier_oper
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function modifier_oper($row){
        switch ($row) {
            
            case 16777215:
                $row = '系统';
                break;
            
        }

        return $row;
    }

    /**
     * 获得日志类型(non-PHPdoc)
     * @see dbeav_model::getLogType()
     */
    public function getLogType($logParams) {
        $type = $logParams['type'];
        $logType = 'none';
        if ($type == 'export') {
            $logType = $this->exportLogType($logParams);
        }
        elseif ($type == 'import') {
            $logType = $this->importLogType($logParams);
        }
        return $logType;
    }
    /**
     * 导出日志类型
     * @param Array $logParams 日志参数
     */
    public function exportLogType($logParams) {
        $params = $logParams['params'];
        $type = 'warehouse';
        if ($logParams['app'] == 'iostock' && $logParams['ctl'] == 'admin_iostocksearch') {
            $type .= '_stockManager_ioStockDetail';
        }
        $type .= '_export';
        return $type;
    }
    /**
     * 导入操作日志类型
     * @param Array $logParams 日志参数
     */
    public function importLogType($logParams) {
        $params = $logParams['params'];
        $type = 'warehouse';
        if ($logParams['app'] == 'iostock' && $logParams['ctl'] == 'admin_iostocksearch') {
            $type .= '_stockManager_ioStockDetail';
        }
        $type .= '_import';
        return $type;
    }

    
    /**
     * 根据退货单号返回发货单号
     * @param   type
     * @return 
     * @access  public
     * @author 
     */
    function getDeliveryByreshipId($original_bn)
    {
        $SQL = "SELECT d.delivery_bn FROM sdb_ome_reship as r LEFT JOIN sdb_ome_delivery_order as do ON do.order_id=r.order_id LEFT JOIN sdb_ome_delivery as d ON d.delivery_id=do.delivery_id WHERE d.parent_id=0 AND d.status='succ' AND r.reship_bn='".$original_bn."'";
        error_log($SQL,3,__FILE__.'.log');
        $reship = $this->db->selectrow($SQL);
        $delivery_bn = $reship ? $reship['delivery_bn'] : '';
        return $delivery_bn;
    }


    /**
     * 获取PrimaryIdsByCustom
     * @param mixed $filter filter
     * @param mixed $op_id ID
     * @return mixed 返回结果
     */
    public function getPrimaryIdsByCustom($filter, $op_id){
        //支持like等比较查询
        $this->filter_use_like = true;

        //修正导出全部数据的时候branch_id引起的查询结果为空的问题
        if($filter['branch_id'] < 1){
            unset($filter['branch_id']);
        }

        $rows = $this->getList('iostock_id',$filter,0,-1);

        $ids = array();
        foreach ($rows as $k => $row){
            $ids[] = $row['iostock_id'];
        }

        return $ids;
    }
    
    /**
     * [出入库明细]导出列表扩展字段
     */
    function export_extra_cols()
    {
        return array(
            'column_iostock_name' => array('label'=>'出入单名称','width'=>'100','func_suffix'=>'iostock_name'),
            'column_order_bn' => array('label'=>'订单号','width'=>'100','func_suffix'=>'order_bn'),
            'column_iostock_id' => array('label'=>'ID','width'=>'100','func_suffix'=>'iostock_id'),
        );
    }
    
    /**
     * [出入单名称]扩展导出字段格式化
     */
    function export_extra_iostock_name($rows)
    {
        return kernel::single('ome_exportextracolumn_iostock_iso')->process($rows);
    }

    function export_extra_order_bn($rows)
    {
        return kernel::single('ome_exportextracolumn_iostock_orderbn')->process($rows);
    }

    function export_extra_iostock_id($rows)
    {
        foreach ($rows as $k => $row) {
            $rows[$k]['column_iostock_id'] = $row['iostock_id'];
        }
        return $rows;
    }
    
    /**
     * modifier_entity_branch_detail
     * @param mixed $value value
     * @return mixed 返回值
     */
    public function modifier_entity_branch_detail($value)
    {
        if (!empty($value)) {
            $branch_list = unserialize($value)['branch_list'];
            $html1 = '<span style="color:#0000ff">查看仓库详情</span>';
            $html2 = '';
            foreach ($branch_list as $item) {
                $html2 .= '<span style="color:#0000ff">仓库名称：'. app::get('ome')->model('branch')->Get_name($item['branch_id'])
                    .'，库存数量：'.$item['store'] .'，平均成本：'.$item['unit_cost']  .'，结存成本：'.$item['inventory_cost']  .'</span><br>';
            }
            return  '<div class="desc-tip" onmouseover="bindFinderColTip(event);">'.$html1.'<textarea style="display:none;"><h3>相关仓库库存</h3>'.$html2.'</textarea></div>';
        } else {
            return  '-';
        }
    }
}
?>