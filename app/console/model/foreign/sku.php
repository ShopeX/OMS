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

class console_mdl_foreign_sku extends dbeav_model{
    var $defaultOrder = array('sync_status,inner_sku');

    function exportTemplate(){
        
        $title = $this->import_title();
        foreach($title as $k=>$v){
            $title[$k] = kernel::single('base_charset')->utf2local($v);
        }
        
        return $title;
    }

    //定义导入文件模版字段
    public function import_title(){
        $title = array(
            '*:货品编码',
            '*:货品名称',
            '*:wms物料编码',
            '*:外部oms物料编码',
        );
        
        return $title;
    }

    public function searchOptions(){
        return array(
            'inner_sku'       =>  '基础物料编码',
            'inner_sku_fuzzy' =>  '基础物料编码模糊',
            'name'      =>  '基础物料名称',
            'outer_sku' =>  'WMS物料编码',
            'oms_sku'   =>  '外部OMS物料编码',
        );
    }

    public function _filter($filter,$tableAlias=null,$baseWhere=null)
    {
        $basicMaterialObj    = app::get('material')->model('basic_material');

        if($filter['inner_sku'] && is_string($filter['inner_sku']) && strpos($filter['inner_sku'], "\n") !== false){
            $filter['inner_sku'] = array_unique(array_map('trim', array_filter(explode("\n", $filter['inner_sku']))));
        }
        
        $where = ' 1 ';
        if(isset($filter['name']))
        {
            $products     = $basicMaterialObj->dump(array('material_name'=>$filter['name']), 'bm_id, material_bn');
            
            $product_id = $products['bm_id'];

            $where .= " AND inner_product_id = '".$product_id."'";
            unset($filter['name']);
        }
        
        //商品品牌处理
        if(isset($filter['brand_id']))
        {
            $sql    = "select bm_id from sdb_material_basic_material_ext where brand_id=". $filter['brand_id'];
            $products_tmp = kernel::database()->select($sql);
            $products = array();
            
            if($products_tmp)
            {
                foreach($products_tmp as $extVal)
                {
                    $products[]    = $extVal['bm_id'];
                }
                $where .= " AND inner_product_id in (".implode($products,',').")";
            }
            unset($filter['brand_id']);
        }

        if (isset($filter['inner_sku_fuzzy'])) {
            if ($filter['inner_sku_fuzzy'] != '' && !is_null($filter['inner_sku_fuzzy'])) {
                $baseWhere[] = sprintf(' inner_sku like "%%%s%%" ', addslashes($filter['inner_sku_fuzzy']));
            }
            unset($filter['inner_sku_fuzzy']);
        }

        return parent::_filter($filter,$tableAlias,$baseWhere)." AND ".$where;
    }

    public function modifier_sync_status($row){
        $row_name = $this->get_sync_name($row);
        if($row == '4'){
            $render = kernel::single("base_render");
            $msg = '此商品同步成功后，被编辑过，用户可与仓库协商，编辑后的商品是否需要再次进行同步';
            $rs = kernel::single('desktop_view_helper')->block_help('',$msg,$render);
            $data =$row_name."<div style='float:right'>".$rs."</div>";
        }else{
            $data = $row_name;
        }
        return $data;
    }

    public function get_sync_name($sync_status){
        switch($sync_status){
            case 0:
                $name = '未同步';
                break;
            case 1:
                $name = '同步失败';
                break;
            case 2:
                $name = '同步中';
                break;
            case 3:
                $name = '同步成功';
                break;
            case 4:
                $name = '同步后编辑';
                break;
        }
        return $name;
    }

    function prepared_import_csv(){
        set_time_limit(0);
        $this->ioObj->cacheTime = time();
    	   $this->kvdata = '';
        $this->aa = 0;
    }

    function finish_import_csv(){
        $data = $this->kvdata;
        $queueObj = app::get('base')->model('queue');
        unset($this->kvdata);
        $queueData = array(
            'queue_title'=>'货品分配导入',
            'start_time'=>time(),
            'params'=>array(
                'sdfdata'=>$data['sku']['contents'],
                'app' => 'console',
                'mdl' => 'foreign_sku'
            ),
            'worker'=>'console_foreignsku_import.run',
        );
        $queue_result = $queueObj->save($queueData);
        $queueObj->flush();
        return null;
    }

    function prepared_import_csv_row($row,$title,&$tmpl,&$mark,&$newObjFlag,&$msg)
    {
        $basicMaterialObj    = app::get('material')->model('basic_material');
        
        $this->aa++;
        $fileData = $this->kvdata;
        $wms_id = $_POST['wms_id'];
        if( !$fileData ) $fileData = array();

        if($row){
            if( substr($row[0],0,1) == '*' ){

            }else{
                
                //替换空格及特殊字符
                $row[0] = trim($row[0]);
                $row[0] = str_replace(array("'", '"'), '', $row[0]);
                
                $row[2] = trim($row[2]);
                $row[2] = str_replace(array("'", '"'), '', $row[2]);
                
                $row[3] = trim($row[3]);
                $row[3] = str_replace(array("'", '"'), '', $row[3]);
                
                if(trim($row[0])==''){
                    $msg['error']='货品编码不能为空!';
                    return false;
                  }else if(trim($row[1])==''){
                    $msg['error']='货品名称不能为空!';
                    return false;
                  }else{
                      $product    = $basicMaterialObj->getList('bm_id, material_bn', array('material_bn'=>$row[0]), 0, 1);
                      
                    if(count($product) == '0'){
                        $msg['error']='货品编码在系统中不存在:'.$row[0];
                        return false;
                    }

                    if($wms_id=='_ALL_'){
                        $wms_list = kernel::single('channel_func')->getWmsChannelList();
                        foreach($wms_list as $v){
                            $wmsid_list[] = $v['wms_id'];
                        }
                    }else{
                        $wmsid_list[] = $wms_id;
                    }
                    
                    //货品编码不能重复
                    $wfsObj = app::get('console')->model('foreign_sku');
                    $info = $wfsObj->getlist('inner_sku',array('inner_sku'=>$row[0],'wms_id'=>$wmsid_list));
                    if(count($info) != '0'){
                           // $msg['error'] = '货品编码已存在 ';
                            //return false;
                    }
                    
                    foreach($wmsid_list as $wmsid){
                        $data = array(
                            'inner_sku'         =>  $row[0],
                            'inner_product_id'  =>  $product[0]['bm_id'],
                            'wms_id'            =>  $wmsid,
                            'oms_sku'           =>  $row[3],
                            );
                        
                        if ($row[2]){
                            $data['outer_sku'] = $row[2];
                        }
                        $fileData['sku']['contents'][] = $data;
                    }
                }
            }
        }

        $this->kvdata = $fileData;
        
        
        return null;
    }

    
    /**
     *返回商品外部sku
     * @param   
     * @return  
     * @access  public
     * @author sunjing@shopex.cn
     */
    function get_product_outer_sku( $wms_id,$bn )
    {
        
        $oForeign_sku = $this->dump(array('inner_sku'=>$bn,'wms_id'=>$wms_id),'outer_sku');
        return $oForeign_sku['outer_sku'];
    }

    
    /**
     * 返回商品内部sku
     * @param   bn
     * @return  
     * @access  public
     * @author sunjing@shopex.cn
     */
    function get_product_inner_sku( $wms_id,$bn )
    {

        $oForeign_sku = $this->dump(array('outer_sku'=>$bn,'wms_id'=>$wms_id),'inner_sku');
        return $oForeign_sku['inner_sku'];
    }
    /**
    * 更新货品同步状态
    */
    function update_status($product_id){
        $SQL = "update sdb_console_foreign_sku SET sync_status='4' WHERE inner_product_id=".$product_id." AND sync_status='3'";
        $result = $this->db->exec($SQL);

    }


    public function replaceinsert($data)
    {
        $columns = $this->schema['columns'];

        $strFields=$strValue=array();
        foreach ($data as $d) {
            $insertValues = array();
            foreach ($d as $c => $v) {
                if (!isset($columns[$c])) continue;

                $insertValues[$c] = $this->db->quote($v);

            }

            if (!$insertValues) continue;

            $strValue[] = "(".implode(',',$insertValues).")";
        }

        $strFields = array_keys($insertValues);

        if (!$strFields || !$strValue) return ;

        $strFields = implode('`,`', $strFields);$strValue = implode(',', $strValue);

        $sql = 'REPLACE INTO `'.$this->table_name(true).'` ( `'.$strFields.'` ) VALUES '.$strValue;

        $this->db->exec($sql);
    }
}
