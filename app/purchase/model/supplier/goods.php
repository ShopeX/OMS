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
 * 供应商商品
 */

class purchase_mdl_supplier_goods extends dbeav_model{
    
    /*
     * 获取供应商商品列表
     */
    function getSupplierGoods($supplier_id=null){
        
        $filter = array("supplier_id"=>$supplier_id);
        $goods = $this->getList('bm_id', $filter, 0, -1);
        
        foreach ($goods as $v)
        {
            $goodsArr[] = $v['bm_id'];
        }
        if ($supplier_id and !$goodsArr){
            
            $base_filter = array('bm_id'=>'-1');
            
        }elseif ($supplier_id and $goodsArr){
            
            $base_filter = array('bm_id'=>$goodsArr);
        }
        
        return $base_filter;
    }
    
    public function searchOptions()
    {
        return array(
                'material_bn'=>'基础物料编码',
                'supplier_bn'=>'供应商编码',
        );
    }
    
    public function _filter($filter,$tableAlias=null,$baseWhere=null)
    {
        $where = ' 1 ';
        
        //基础物料编码
        if(isset($filter['material_bn']))
        {
            $basicMaterialObj    = app::get('material')->model('basic_material');
            
            $tempData    = $basicMaterialObj->dump(array('material_bn'=>$filter['material_bn']), 'bm_id');
            $bm_id       = $tempData['bm_id'];
            
            $where .= " AND bm_id='". $bm_id ."'";
            unset($filter['material_bn']);
        }
        
        //供应商编码
        if(isset($filter['supplier_bn']))
        {
            $supplierObj    = app::get('purchase')->model('supplier');
            
            $tempData       = $supplierObj->dump(array('bn'=>$filter['supplier_bn']), 'supplier_id');
            $supplier_id    = $tempData['supplier_id'];
            
            $where .= " AND supplier_id='". $supplier_id ."'";
            unset($filter['supplier_bn']);
        }
        
        return parent::_filter($filter,$tableAlias,$baseWhere)." AND ".$where;
    }
    
    /*
     * 导出供应商模板
    */
    function exportTemplate()
    {
        foreach ($this->io_title() as $v)
        {
            $title[] = kernel::single('base_charset')->utf2local($v);
        }
        
        return $title;
    }
    
    function io_title($ioType='csv' )
    {
        $this->oSchema['csv'] = array(
                '*:供应商编码' => 'bn',
                '*:供应商名称' => 'name',
                '*:基础物料编码' => 'brief',
                '*:基础物料名称' => 'company',
        );
        
        $this->ioTitle[$ioType]    = array_keys($this->oSchema[$ioType]);
        
        return $this->ioTitle[$ioType];
    }
    
    /**
     * CSV导入
     */
    function prepared_import_csv()
    {
        $this->ioObj->cacheTime = time();
    }
    
    function finish_import_csv()
    {
        base_kvstore::instance('purchase_supplier_goods')->fetch('supplier-'.$this->ioObj->cacheTime,$data);
        base_kvstore::instance('purchase_supplier_goods')->store('supplier-'.$this->ioObj->cacheTime,'');
        $pTitle = array_flip($this->io_title());
        $pSchema = $this->oSchema['csv'];
        $oQueue = app::get('base')->model('queue');
        
        $aP = $data;
        $pSdf = array();
        
        $count = 0;
        $limit = 50;
        $page = 0;
        $orderSdfs = array();
        
        foreach ($aP['supplier']['contents'] as $k => $aPi)
        {
            if($count < $limit){
                $count ++;
            }else{
                $count = 0;
                $page ++;
            }
            $pSdf[$page][] = $aPi;
        }
        
        foreach($pSdf as $v){
            $queueData = array(
                    'queue_title'=>'供应商基础物料导入',
                    'start_time'=>time(),
                    'params'=>array(
                            'sdfdata'=>$v,
                            'app' => 'purchase',
                            'mdl' => 'supplier_goods'
                    ),
                    'worker'=>'purchase_supplier_goods_to_import.run',
            );
            $oQueue->save($queueData);
        }
        $oQueue->flush();
        
        return null;
    }
    
    function prepared_import_csv_obj($data,$mark,$tmpl,&$msg = '')
    {
        return null;
    }
    
    //CSV导入业务处理
    function prepared_import_csv_row($row,$title,&$tmpl,&$mark,&$newObjFlag,&$msg)
    {
        if (empty($row))
        {
            return true;
        }
        
        $mark = false;
        $re = base_kvstore::instance('purchase_supplier_goods')->fetch('supplier-'.$this->ioObj->cacheTime,$fileData);
        
        if( !$re ) $fileData = array();
        
        if(substr($row[0],0,1) == '*')
        {
            $titleRs =  array_flip($row);
            $mark = 'title';
            
            return $titleRs;
        }
        else
        {
            if($row[0] && $row[2])
            {
                $basicMaterialObj    = app::get('material')->model('basic_material');
                $supplierObj         = app::get('purchase')->model('supplier');
                $sdfRow              = array();
                
                //供应商信息
                $tempData    = $supplierObj->dump(array('bn'=>$row[0]), 'supplier_id');
                if(empty($tempData))
                {
                    $msg['error'] = '供应商编码不存在!';
                    return false;
                }
                $sdfRow['supplier_id']    = $tempData['supplier_id'];
                
                //基础物料信息
                $tempData    = $basicMaterialObj->dump(array('material_bn'=>$row[2]), 'bm_id');
                if(empty($tempData))
                {
                    $msg['error'] = '基础物料编码不存在!';
                    return false;
                }
                $sdfRow['bm_id']    = $tempData['bm_id'];
                
                //检查数据是否已存在
                $tempData    = $this->dump(array('supplier_id'=>$sdfRow['supplier_id'], 'bm_id'=>$sdfRow['bm_id']), '*');
                if($tempData)
                {
                    $msg['error'] = '供应商基础物料关系已经存在，不能重复导入!';
                    return false;
                }
                
                //组织数据
                $fileData['supplier']['contents'][] = $sdfRow;
                
                base_kvstore::instance('purchase_supplier_goods')->store('supplier-'.$this->ioObj->cacheTime, $fileData);
            }else{
                $msg['error'] = "供应商编码与基础物料编码必须填写";
                return false;
            }
        }
        
        return null;
    }
}
?>
