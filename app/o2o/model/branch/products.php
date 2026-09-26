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

class o2o_mdl_branch_products extends console_mdl_branch_product{
    var $export_name = '门店库存';

    /**
     * table_name
     * @param mixed $real real
     * @return mixed 返回值
     */
    public function table_name($real = false){
        if($real){
           $table_name = 'sdb_ome_branch_product';
        }else{
           $table_name = 'branch_products';
        }
        return $table_name;
    }

    /**
     * __construct
     * @return mixed 返回值
     */
    public function __construct() {
        parent::__construct();
        // 初始化导出相关属性
        $this->oSchema = array();
        $this->ioTitle = array();
    }
    
    /**
     * 重写导出方法，确保只导出门店类型的库存数据
     */
    function fgetlist_csv(&$data, $filter, $offset, $exportType = 1) {
        // 确保只导出门店类型的库存数据
        if (!isset($filter['b_type'])) {
            $filter['b_type'] = '2';
        }

        $branchObj = app::get('ome')->model('branch');
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

        $list = material_basic_material_master::fillListRowsWithIdField($list, 'product_id');
        $masterCols = material_basic_material_master::extraCols();

        foreach( $list as $aFilter ){
            $branch = $branchObj->dump($aFilter['branch_id'],'name');
            $barcode = $barcodeLib->getBarcodeById($aFilter['product_id']);

            $pRow = array();
            $detail['store'] = $aFilter['store'];
            $detail['store_freeze'] = $aFilter['store_freeze'];
            $detail['barcode'] = "\t".$this->charset->utf2local($barcode);
            $detail['name'] = $this->charset->utf2local($aFilter['name']);
            $detail['bn'] = "\t".$this->charset->utf2local($aFilter['bn']);
            $detail['spec_info'] = $this->charset->utf2local($aFilter['spec_info']);
            $detail['branch_name'] = $this->charset->utf2local($branch['name']);
            $detail['arrive_store'] = $aFilter['arrive_store'];
            $detail['material_spu'] = $this->charset->utf2local($aFilter['material_spu']);
            $detail['weight'] = $this->charset->utf2local($aFilter['weight']);
            $detail['unit'] = $this->charset->utf2local($aFilter['unit']);
            foreach ($masterCols as $colKey => $colDef) {
                $detail[$colKey] = isset($aFilter[$colKey]) ? $this->charset->utf2local($aFilter[$colKey]) : '';
            }

            foreach( $this->oSchema['csv']['branch_product'] as $k => $v ){

                $pRow[$k] =  utils::apath( $detail,explode('/',$v) );
            }
            $data['content']['main'][] = '"'.implode('","',$pRow).'"';
        }
        $data['records'] = count($data['content']['main'])-1;

        return true;        
    }
    
    /**
     * 重写导出标题，添加货品重量和包装单位字段
     */
    function io_title($filter, $ioType='csv') {
        switch($filter) {
            case 'branch_product':
                $this->oSchema['csv'][$filter] = array(
                    '*:门店名称' => 'branch_name',
                    '*:货号' => 'bn',
                    '*:条形码' => 'barcode',
                    '*:货品名称' => 'name',
                    '*:规格' => 'spec_info',
                    '*:款号' => 'material_spu',
                    '*:库存' => 'store',
                    '*:冻结库存' => 'store_freeze',
                    '*:在途库存' => 'arrive_store',
                    '*:货品重量(g)' => 'weight',
                    '*:包装单位' => 'unit'
                );
                foreach (material_basic_material_master::extraCols() as $colKey => $colDef) {
                    $this->oSchema['csv'][$filter]['*:' . $colDef['label']] = $colKey;
                }
                break;
        }
        $this->ioTitle[$ioType][$filter] = array_keys($this->oSchema['csv'][$filter]);
        return $this->ioTitle[$ioType][$filter];
    }
    
    /**
     * 重写导出文件名，使用门店名称
     */
    public function exportName(&$data) {
        $branch_id = $_POST['branch_id'];
        
        $branchObj = app::get('ome')->model('branch');
        if (isset($branch_id) && trim($branch_id)) {
            $branch = $branchObj->getlist('name', array('branch_id' => $branch_id));
            $export_name = $branch[0]['name'];
        } else {
            $export_name = '全部门店';
        }
        $data['name'] = $export_name . '库存' . date('Ymd');
    }
}
?>


