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
 * 经销商商品
 */

class dealer_mdl_goods extends dbeav_model{
    private $import_data = [];
    private $templateColumn = array(
                '*:经销商编码' => 'bs_bn',
                '*:基础物料编码' => 'material_bn',
                '*:成本' => 'cost',
            );

    public function searchOptions()
    {
        return array(
                'material_bn'=>'基础物料编码',
                'bs_bn'=>'经销商编码',
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
            
            $where .= " AND bm_id=". $bm_id;
            unset($filter['material_bn']);
        }
        
        //经销商编码
        if(isset($filter['bs_bn']))
        {
            $dealerObj    = app::get('dealer')->model('business');
            
            $tempData       = $dealerObj->dump(array('bs_bn'=>$filter['bs_bn']), 'bs_id');
            $bs_id    = $tempData['bs_id'];
            
            $where .= " AND bs_id=". $bs_id;
            unset($filter['bs_bn']);
        }
        
        return parent::_filter($filter,$tableAlias,$baseWhere)." AND ".$where;
    }
    
    /*
     * 导出经销商模板
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
        $this->oSchema['csv'] = $this->templateColumn;
        
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
        $oQueue = app::get('base')->model('queue');
        
        $aP = $this->import_data;
        $pSdf = array();
        
        $count = 0;
        $limit = 50;
        $page = 0;
        $orderSdfs = array();
        
        foreach ($aP as $k => $aPi)
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
                    'queue_title'=>'经销商货品导入',
                    'start_time'=>time(),
                    'params'=>array(
                            'sdfdata'=>$v,
                    ),
                    'worker'=>'dealer_mdl_goods.import_run',
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
        if( substr($row[0],0,1) == '*' ){
            $title = array_flip($row);
            $mark = 'title';
            foreach($this->templateColumn as $k => $val) {
                if(!isset($title[$k])) {
                    $msg['error'] = '请使用正确的模板';
                    return false;
                }
            }
            return $title;
        }
        if (empty($title)) {
            $msg['error'] = "请使用正确的模板格式！";
            return false;
        }
        $arrData = array();
        foreach($this->templateColumn as $k => $val) {
            $arrData[$val] = trim($row[$title[$k]]);
        }
        if(count($this->import_data) > 10000){
            $msg['error'] = "导入的数据量过大，请减少到10000条以下！";
            return false;
        }
        if($arrData['bs_bn'] && $arrData['material_bn'])
        {
            $basicMaterialObj    = app::get('material')->model('basic_material');
            $dealerObj         = app::get('dealer')->model('business');
            $sdfRow              = array();
            
            //经销商信息
            $tempData    = $dealerObj->dump(array('bs_bn'=>$arrData['bs_bn']), 'bs_id');
            if(empty($tempData))
            {
                $msg['error'] = '经销商编码不存在!';
                return false;
            }
            $sdfRow['bs_id']    = $tempData['bs_id'];
            
            //基础物料信息
            $tempData    = $basicMaterialObj->dump(array('material_bn'=>$arrData['material_bn']), 'bm_id');
            if(empty($tempData))
            {
                $msg['error'] = '基础物料编码不存在!';
                return false;
            }
            $sdfRow['bm_id']    = $tempData['bm_id'];
            
            $sdfRow['cost'] = $arrData['cost'];
            
            //组织数据
            $this->import_data[] = $sdfRow;
            
        }else{
            $msg['error'] = "经销商编码与基础物料编码必须填写";
            return false;
        }
    
        
        return null;
    }

    public function import_run(&$cursor_id, $params) {
        $sdfdata    = $params['sdfdata'];
        foreach ($sdfdata as $v) {
            $oldRow = $this->db_dump(array('bs_id'=>$v['bs_id'], 'bm_id'=>$v['bm_id']), 'id');
            if($oldRow) {
                $this->update(array('cost'=>$v['cost'], 'modify_time'=>time()), array('id'=>$oldRow['id']));
            } else {
                $v['create_time'] = time();
                $v['modify_time'] = time();
                $this->insert($v);
            }
        }
        return false;
    }
}