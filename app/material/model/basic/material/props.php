<?php

class material_mdl_basic_material_props extends dbeav_model{


     private $templateColumn = array(

    );
    function prepared_import_csv_row($row,&$title,&$tmpl,&$mark,&$newObjFlag,&$msg){
       
        if(empty($row) || empty(array_filter($row))) return false;
        if( trim($row[0]) == '基础物料编码' ){
            $this->nums = 1;
            $mark = 'title';
            $title = array_flip($row);
            
            $this->basicm_nums           = 1;
            $this->fileData              = [];
            $this->templateColumn = $title;

            return $title;
        }else{

            $arrData = array();
        
            foreach($this->templateColumn as $k => $val) {
                if(empty($row[$val])) continue;

                if($k=='基础物料编码') $k='material_bn';
                $arrData[$k] = trim($row[$val]);
               
            }
          
            if(isset($this->nums)){
                $this->nums++;
                if($this->nums > 10000){
                    $msg['error'] = "导入的数据量过大，请减少到10000条以下！";
                    return false;
                }
            }

            $row = app::get('material')->model('basic_material')->db_dump(['material_bn'=>$arrData['material_bn']], 'bm_id');
            if(empty($row)) {
                $msg['warning'][] = 'Line '.$this->nums.'：基础物料编码'.$arrData['material_bn'].'不存在！'.var_export(['material_bn'=>$arrData['material_bn']]);
                return false;
            }
            
            $arrData['bm_id'] = $row['bm_id'];
            $this->fileData['specis'][] = $arrData;
       
            #销毁
            unset($row);


        }
        
        return null;
    }

    /**
     * 完成基础物料的导入
     *
     * @param Null
     * @return Null
     */
    function finish_import_csv(){
       
        $aP = $this->fileData['specis'];
        
        if(empty($aP)) {
            return null;
        }
        $oQueue = app::get('base')->model('queue');
                
   
        $opInfo = kernel::single('material_basic_material')->getImportOpInfo();
        foreach(array_chunk($aP, 100) as $v){
            
            $queueData = array(
                    'queue_title'=>'基础物料规格更新导入',
                    'start_time'=>time(),
                    'params'=>array(
                            'sdfdata'=>$v,
                            'opInfo' => $opInfo,
                    ),
                    'worker'=>'material_mdl_basic_material_props.import_run',
            );

            
            $oQueue->save($queueData);
        }
        $oQueue->flush();
                
        return null;

    }



     public function import_run(&$cursor_id,$params,&$errmsg) {
        $opInfo = !empty($params['opInfo']) ? $params['opInfo'] : null;
        $importLogLib = kernel::single('material_basic_material');

        foreach ($params['sdfdata'] as $k=>$v) {
            
            $bm_id = $v['bm_id'];
            $log = '导入更新物料属性：';

            $cols = array();
            foreach($v as $vk=>$vv){
                if(in_array($vk,array('material_bn','bm_id'))) continue;
                if(empty($vv)) continue;

                $olds = $this->db_dump(array('props_col'=>$vk,'bm_id'=>$bm_id),'id');
                if($olds){
                    $propsdata = array(
                       
                        'props_value'   =>  $vv,
                    );
                    $this->update($propsdata,array('props_col'=>$vk,'bm_id'=>$bm_id));
                }else{
                    $propsdata = array(
                        'bm_id'         =>  $bm_id,
                        'props_col'     =>  $vk,
                        'props_value'   =>  $vv,
                    );
                    $this->save($propsdata);
                }
                $log .= $vk . '->' . $vv . ';';
            }

            if ($log != '导入更新物料属性：') {
                $importLogLib->writeImportLog('basic_material_property@wms', $bm_id, $log, $opInfo);
            }
        }
        return false;
    }
   
}
