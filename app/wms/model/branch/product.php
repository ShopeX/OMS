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

class wms_mdl_branch_product extends ome_mdl_branch_product{
     var $export_name = '仓库库存';

     public function table_name($real = false){
        if($real){
           $table_name = 'sdb_ome_branch_product';
        }else{
           $table_name = 'branch_product';
        }
        return $table_name;
    }

    public function get_schema()
    {
        $brObj = app::get('ome')->model('branch');
        $is_super = kernel::single('desktop_user')->is_super();
        $branch_ids = kernel::single('wms_branch')->getBranchwmsByUser($is_super);
        $branch_rows   = $brObj->getList('branch_id, name',array('branch_id'=>$branch_ids),0,-1);
        $branch_list = array();
        foreach($branch_rows as $branch){
            $branch_list [$branch['branch_id']] = $branch['name'];
        }
        $schema = app::get('ome')->model('branch_product')->get_schema();
        unset($schema['columns']['branch_id']);
        $schema['columns']['branch_id'] = array (
                    'type' => $branch_list,
                    'editable' => false,
                    'label' => '仓库',
                    'width' => 110,
                    'default_value'=>$branch_rows[0]['branch_id'],
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'in_list' => true,
                    'panel_id' => 'wms_branch_finder_top',
                );
        $schema['columns']['bn'] = array (
                    'type' => 'varchar(200)',
                    'editable' => false,
                    'label' => '基础物料编码',
                    'filterdefault' => false,
                    'in_list' => false,
                );
        
        return $schema;
    }

    function fgetlist_csv( &$data,$filter,$offset,$exportType = 1 ) {

        $branchObj = app::get('ome')->model('branch');
        $basicMStockFreezeLib  = kernel::single('material_basic_material_stock_freeze');
        
        if( !$data['title']){
            $title = array();

            foreach($this->io_title('branch_product') as $k => $v ){
                $title[] = $this->charset->utf2local($v);
            }
            $data['content']['main'][] = '"'.implode('","',$title).'"';
        }
        //$limit =100;
        $barcodeLib = kernel::single('material_basic_material_barcode');

        if( !$list=$this->getlists('*',$filter,0,-1) )return false;
        foreach( $list as $aFilter ){
            $branch = $branchObj->dump($aFilter['branch_id'],'name');
            $barcode = $barcodeLib->getBarcodeById($aFilter['product_id']);

            $pRow = array();
            
            //根据仓库ID、基础物料ID获取该物料仓库级的预占
            $aFilter['store_freeze']    = $basicMStockFreezeLib->getBranchFreeze($aFilter['product_id'], $aFilter['branch_id']);
            
            $detail['store'] = $aFilter['store'];
            $detail['store_freeze'] = $aFilter['store_freeze'];
            $detail['barcode'] = $this->charset->utf2local($barcode)."\t";
            $detail['name'] = $this->charset->utf2local($aFilter['name']);
            $detail['bn'] = $this->charset->utf2local($aFilter['bn'])."\t";
            $detail['branch_name'] = $this->charset->utf2local($branch['name']);
            $detail['arrive_store'] = $aFilter['arrive_store'];
            foreach( $this->oSchema['csv']['branch_product'] as $k => $v ){

                $pRow[$k] =  utils::apath( $detail,explode('/',$v) );
            }
            $data['content']['main'][] = '"'.implode('","',$pRow).'"';
        }
        $data['records'] = count($data['content']['main'])-1;

        return true;
    }

    public function fcount_csv($filter = NULL)
    {
        return 600;
    }

    function io_title( $filter, $ioType='csv' ){

        switch( $filter ){
            case 'branch_product':
                $this->oSchema['csv'][$filter] = array(
                '*:仓库' => 'branch_name',
                '*:货号' => 'bn',
                '*:条形码' => 'barcode',
                '*:基础物料名称' => 'name',
                '*:库存' => 'store',
                '*:冻结库存' => 'store_freeze',
                '*:在途库存'=>'arrive_store'
                );
                break;
        }
        $this->ioTitle[$ioType][$filter] = array_keys( $this->oSchema[$ioType][$filter] );
        return $this->ioTitle[$ioType][$filter];
     }

     function export_csv($data,$exportType = 1 ){
        $output = array();
        foreach( $data['title'] as $k => $val ){

            $output[] = $val."\n".implode("\n",(array)$data['contents'][$k]);
        }

        echo implode("\n",$output);
    }
    public function exportName(&$data){
        $branch_id = $_POST['branch_id'];

        $branchObj = app::get('ome')->model('branch');
        if(isset($branch_id) && trim($branch_id)){
            $branch = $branchObj->getlist('name',array('branch_id'=>$branch_id));
            $export_name = $branch[0]['name'];
        }else{
            $export_name='全部仓库';
        }
        $data['name'] = $export_name.'库存'.date('Ymd');
    }
    
    function getlists($cols='*', $filter=array(), $offset=0, $limit=-1, $orderby=null)
    {
        $strWhere = '';
        
        $col_tmp = array();
        $col_tmp[] = 'bp.*';
        
        //仓库号
        if(isset($filter['branch_id']) && $filter['branch_id']){
            if (is_array($filter['branch_id'])){
                $strWhere = ' AND bp.branch_id IN ('.implode(',', $filter['branch_id']).') ';
            }else {
                $strWhere = ' AND bp.branch_id = '.$filter['branch_id'];
            }
        }
        
        //货号
        if(isset($filter['bn']) && $filter['bn']!=''){
            $strWhere.=' AND a.material_bn like \''.$filter['bn'].'%\'';
        }
        
        //真实库存
        if(isset($filter['actual_store']) && $filter['actual_store']!=''){
            $strWhere.=' AND bp.store';
            if($filter['_actual_store_search']=='nequal'){
                $strWhere.=' =';
            }else if($filter['_actual_store_search']=='than'){
                $strWhere.=' >';
            }else if($filter['_actual_store_search']=='lthan'){
                $strWhere.=' <';
            }
            $strWhere.=$filter['actual_store'];
        }
        
        //可用库存
        if(isset($filter['enum_store']) && $filter['enum_store']!=''){
            if($filter['_enum_store_search']=='nequal'){
                $strWhere.=' AND bp.store-bp.store_freeze='.$filter['enum_store'];
            }else if($filter['_enum_store_search']=='than'){
                $strWhere.=' AND (if(bp.store_freeze>bp.store,-1,bp.store - bp.store_freeze))>'.$filter['enum_store'];
            }else if($filter['_enum_store_search']=='lthan'){
                $strWhere.=' AND (if(bp.store_freeze>bp.store,-1,bp.store - bp.store_freeze))<'.$filter['enum_store'];
            }
        }
        
        //visibility
        if(isset($filter['visibility'])){
            $filter['visibility']    = ($filter['visibility'] == 'true' ? 1 : 0);
            $strWhere .= ' and a.visibled='."'{$filter['visibility']}'";
        }
        
        //col
        $col_tmp[]    = 'a.bm_id, a.material_name AS name, a.material_bn AS bn, a.visibled AS visibility';
        $cols = implode(",",$col_tmp);
        
        //sql
        $sql = 'SELECT '.$cols.' FROM sdb_ome_branch_product AS bp 
                LEFT join '. DB_PREFIX .'material_basic_material as a on bp.product_id=a.bm_id 
                WHERE a.material_bn != \'\' '.$strWhere;
        
        //order by
        $orderType = $orderby ? $orderby : $this->defaultOrder;
        if($orderType){
            if(is_array($orderType)){
                $sql .= ' ORDER BY '. implode(' ', $orderType);
            }else{
                $sql .= ' ORDER BY '. $orderType;
            }
        }
        
        //select
        $data = $this->db->selectLimit($sql,$limit,$offset);
        
        return $data;
    }
}
?>
