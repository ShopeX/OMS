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
 * 基础物料扩展模型层
 *
 * @author kamisama.xia@gmail.com 
 * @version 0.1
 */

class material_mdl_basic_material_ext extends dbeav_model{

    public function templateColumn(){
        $customcols = kernel::single('material_customcols')->getcolstemplate();
        $templateColumn = $this->defaulttemplateColumn;
       
        if($customcols){
            
            $templateColumn = array_merge($templateColumn,$customcols);

        }
        return $templateColumn;
    }

    private $defaulttemplateColumn = array(
        '基础物料编码' => 'material_bn',
        '基础物料名称' => 'basic/material_name',
        '物料条码' => 'barcode/material_code',
        '物料成本价' => 'cost',
        '物料零售价' => 'retail_price',
        '重量' => 'weight',
        '长度' => 'length',
        '宽度' => 'width',
        '高度' => 'high',
        '包装单位' => 'unit',
        '开票税率' => 'basic/tax_rate',
        '开票名称' => 'basic/tax_name',
        '发票分类编码' => 'basic/tax_code',
        '保质期开启(是/否)' => 'conf/use_expire_wms',
        '保质期(小时)' => 'conf/shelf_life',
        '禁收天数' => 'conf/reject_life_cycle',
        '禁售天数' => 'conf/lockup_life_cycle',
        '临期预警天数' => 'conf/advent_life_cycle',
        '颜色'=>'basic/color',
        '尺码'=>'basic/size',
        '季节'=>'props/season',
        '材质'=>'props/uppermatnm',
        '适用对象'=>'props/gendernm',
        '鞋型'=>'props/widthnm',
        '风格款式'=>'props/modelnm',
        '门店销售'=>'basic/is_o2o_sales',
        '物料分类一级'=>'basic/cat_level_1',
        '物料分类二级'=>'basic/cat_level_2',
        '物料分类三级'=>'basic/cat_level_3',
        '物料分类四级'=>'basic/cat_level_4',
        '物料分类五级'=>'basic/cat_level_5',
    );

    public function getTemplateColumn() {
        return array_keys($this->templateColumn());
    }
    public function prepared_import_csv(){
        $this->ioObj->cacheTime = time();
    }

    public function prepared_import_csv_row($row,&$title,&$tmpl,&$mark,&$newObjFlag,&$msg)
    {
        if(empty($row) || empty(array_filter($row))) return false;
        if( $row[0] == '基础物料编码' ){
            $this->nums = 1;
            $title = array_flip($row);
            foreach($this->templateColumn() as $k => $val) {
                if(!isset($title[$k])) {
                    $msg['error'] = '请使用正确的模板';
                    return false;
                }
            }
            return false;
        }
        if (empty($title)) {
            $msg['error'] = "请使用正确的模板格式！";
            return false;
        }
        $arrData = array();
        foreach($this->templateColumn() as $k => $val) {
            if(strpos($val, '/')) {
                list($v1, $v2) = explode('/', $val);
                $arrData[$v1][$v2] = trim($row[$title[$k]]);
            } else {
                $arrData[$val] = trim($row[$title[$k]]);
            }
        }
        if(empty($arrData['material_bn'])) {
            $msg['warning'][] = 'Line '.$this->nums.'：基础物料编码不能为空！';
            return false;
        }
        $row = app::get('material')->model('basic_material')->db_dump(['material_bn'=>$arrData['material_bn']], 'bm_id');
        if(empty($row)) {
            $msg['warning'][] = 'Line '.$this->nums.'：基础物料编码'.$arrData['material_bn'].'不存在！';
            return false;
        }
        $material_bn = $arrData['material_bn'];
        unset($arrData['material_bn']);
        if(isset($this->nums)){
            $this->nums++;
            if($this->nums > 10000){
                $msg['error'] = "导入的数据量过大，请减少到10000条以下！";
                return false;
            }
        }
        $sdf = [];
        foreach ($arrData as $key => $value) {
            if(is_array($value)) {
                foreach ($value as $k => $v) {
                    if(in_array($k, ['cat_level_1', 'cat_level_2', 'cat_level_3', 'cat_level_4', 'cat_level_5'])) {
                        continue;
                    }
                    if($v != '') {
                        if(in_array($k, ['use_expire','use_expire_wms'])) {
                            $v = ($v == '是' ? '1' : '2');
                            $sdf[$key]['use_expire'] = $v;
                            $sdf[$key]['use_expire_wms'] = $v;
                            continue;
                        }

                        if(in_array($k,['is_o2o_sales'])){
                            $v = ($v == '是' ? 1 : 0);
                        }
                        $sdf[$key][$k] = $v;
                    }
                }
            }elseif($value != '') {
                $sdf[$key] = $value;
            }
        }
        if(isset($arrData['basic'])) {
            $levelNames = [
                isset($arrData['basic']['cat_level_1']) ? trim($arrData['basic']['cat_level_1']) : '',
                isset($arrData['basic']['cat_level_2']) ? trim($arrData['basic']['cat_level_2']) : '',
                isset($arrData['basic']['cat_level_3']) ? trim($arrData['basic']['cat_level_3']) : '',
                isset($arrData['basic']['cat_level_4']) ? trim($arrData['basic']['cat_level_4']) : '',
                isset($arrData['basic']['cat_level_5']) ? trim($arrData['basic']['cat_level_5']) : '',
            ];
            $hasAny = !empty(array_filter($levelNames));
            if($hasAny) {
                $cats = $this->getCatsByLevelNames($levelNames, $msg, $this->nums, $material_bn);
                if($cats === false) {
                    return false;
                }
                if(is_array($cats)) {
                    $sdf['basic']['cat_id'] = $cats['cat_id'];
                    $sdf['basic']['cat_path'] = $cats['cat_path'];
                }
            }
        }
        if(empty($sdf)) {
            $msg['warning'][] = '第'.$this->nums.'行：属性不能都为空！'.($material_bn ? ' 基础物料编码：'.$material_bn : '');
            return false;
        }
        $sdf['bm_id'] = $row['bm_id'];

        if($sdf['custom']){
            

            foreach($sdf['custom'] as $ck=>$cv){
                if($cv){
                    $sdf['props'][$ck] = $cv;
                }
            }
           
        }
        $this->import_data[] = $sdf;
        
        return true;
    }

    function prepared_import_csv_obj($data,$mark,$tmpl,&$msg = ''){
        return null;
    }

    public function finish_import_csv(){
        if(empty($this->import_data)) {
            return null;
        }
        $oQueue = app::get('base')->model('queue');
                
        $aP = $this->import_data;

        foreach(array_chunk($aP, 100) as $v){

            $queueData = array(
                    'queue_title'=>'基础物料更新导入',
                    'start_time'=>time(),
                    'params'=>array(
                            'sdfdata'=>$v,
                    ),
                    'worker'=>'material_mdl_basic_material_ext.import_run',
            );
            
            
            $oQueue->save($queueData);
        }
        $oQueue->flush();
                
        return null;
    }

    public function import_run(&$cursor_id,$params,&$errmsg) {
        $basicObj = app::get('material')->model('basic_material');
        $confObj = app::get('material')->model('basic_material_conf');
        $salesMaterialObj           = app::get('material')->model('sales_material');

        foreach ($params['sdfdata'] as $v) {
            $old = $this->db_dump($v['bm_id']);
            $upData = [];
            $log = '导入更新：';
            foreach ($v as $kk => $vv) {
                if($kk == 'basic') {

                    $oldBasic = $basicObj->db_dump($v['bm_id']);
                    $upBasic = [];
                    foreach ($vv as $basick => $basicv) {
                        if($oldBasic[$basick] != $basicv) {
                            $upBasic[$basick] = $basicv;
                            $log .= $basick . ',' . $oldBasic[$basick] . '->' . $basicv . ';';
                        }
                    }
                    if(isset($vv['cat_id']) && isset($vv['cat_path'])) {
                        $upBasic['cat_id'] = $vv['cat_id'];
                        $upBasic['cat_path'] = $vv['cat_path'];
                    }
                   
                    if($upBasic) {

                        //
                        $basicObj->update($upBasic, ['bm_id'=>$v['bm_id']]);
                        $saleMaterialInfo = $salesMaterialObj->dump(array('sales_material_bn'=>$oldBasic['material_bn'],'sales_material_type'=>['1','6']), '*');
                        // 同步更新销售物料信息
                        if ($saleMaterialInfo && $saleMaterialInfo['sales_material_bn'] == $oldBasic['material_bn']){
                            // 提取允许更新的字段：税率、开票名称、开票编码、销售状态
                            $saleUpdateData = array_intersect_key($upBasic, array_flip(['tax_rate', 'tax_name', 'tax_code', 'visibled']));
                            
                            // 转换visibled字段值：基础物料(1=销售,2=停售) -> 销售物料(1=销售,0=停售)
                            if (isset($saleUpdateData['visibled'])) {
                                if ($saleUpdateData['visibled'] == 1) {
                                    $saleUpdateData['visibled'] = 1; // 销售状态保持一致
                                } elseif ($saleUpdateData['visibled'] == 2) {
                                    $saleUpdateData['visibled'] = 0; // 停售状态：基础物料2 -> 销售物料0
                                }
                            }
                            
                            // 过滤掉空值，只保留有值的字段进行更新
                            $saleUpdateData = array_filter($saleUpdateData, function($value) {
                                return $value !== '' && $value !== null;
                            });
                            
                            // 如果有需要更新的字段，则执行更新操作
                            if ($saleUpdateData) {
                                $salesMaterialLib = kernel::single('material_sales_material');
                                $salesMaterialLib->updateSalesMaterial($saleMaterialInfo['sm_id'], $saleUpdateData, '基础物料批量更新');
                            }
                        }
                    }
                } elseif($kk == 'conf') {
                    $oldConf = $confObj->db_dump($v['bm_id']);
                    $upConf = [];
                    foreach ($vv as $confk => $confv) {
                        if($oldConf[$confk] != $confv) {
                            $upConf[$confk] = $confv;
                            $log .= $confk . ',' . $oldConf[$confk] . '->' . $confv . ';';
                        }
                    }
                    if($upConf) {
                        $confObj->update($upConf, ['bm_id'=>$v['bm_id']]);
                    }
                }elseif($kk=='props'){
                    $propsMdl = app::get('material')->model('basic_material_props');
                    $propslist = $propsMdl->getlist('props_col,props_value,id',array('bm_id'=>$v['bm_id']));

                    $propslist = array_column($propslist, null,'props_col');
                    foreach ($vv as $pk => $pv) {
                        $proconf = array(
                            'bm_id'         =>  $v['bm_id'],
                            'props_col'     =>  $pk,
                            'props_value'   =>  $pv,
                        );
                        if($propslist[$pk]['id']){
                            $proconf['id'] = $propslist[$pk]['id'];

                        }

                        $propsMdl->save($proconf);
                    }

                } elseif($kk=='barcode'){
                    // 处理条码更新
                    $barcodeObj = app::get('material')->model('barcode');
                    $filter = array('bm_id' => $v['bm_id']);
                    
                    // 检查是否已存在条码记录
                    $existingBarcode = $barcodeObj->dump($filter, 'bm_id');
                    
                    if(!$existingBarcode){
                        // 如果不存在条码记录，则插入新记录
                        $barcodeData = array(
                            'bm_id' => $v['bm_id'],
                            'type'  => material_codebase::getBarcodeType(),
                            'code'  => $vv['material_code'],
                        );
                        $barcodeObj->insert($barcodeData);
                        $log .= 'barcode,新增->' . $vv['material_code'] . ';';
                    } else {
                        // 如果存在条码记录，则更新
                        $barcodeData = array(
                            'code' => $vv['material_code'],
                        );
                        $barcodeObj->update($barcodeData, $filter);
                        $log .= 'barcode,更新->' . $vv['material_code'] . ';';
                    }

                } elseif($old[$kk] != $vv) {
                    $upData[$kk] = $vv;
                    $log .= $kk . ',' . $old[$kk] . '->' . $vv . ';';
                }
            }
            if(!empty($upData)) {
                $this->update($upData, ['bm_id'=>$v['bm_id']]);
            }
            if($log != '导入更新：') {
                app::get('ome')->model('operation_log')->write_log('basic_material_property@wms',$v['bm_id'],$log);
            }
        }
        return false;
    }

    public function getCats($cat_name){
        $basicMaterialCatObj        = app::get('material')->model('basic_material_cat');
        
        $rs = $basicMaterialCatObj->dump(array('cat_name'=>$cat_name, 'disabled'=>'false'), 'cat_id,cat_path,min_price');
        if ($rs) {
            $cat_id    = $rs['cat_id'];
            $cat_path  = substr($rs['cat_path'] . $cat_id, 1);
            $data = array('cat_id'=>$cat_id,'cat_path'=>$cat_path);

            return $data;

        }else{
            return false;
        }
    }

    /**
     * 根据5级分类名称逐级精确匹配，解决同名分类歧义
     * @param array $level_names [一级,二级,三级,四级,五级]，每项为 trim 后字符串
     * @param array &$msg 错误信息引用
     * @param int $lineNum 行号，用于错误提示
     * @param string $material_bn 基础物料编码，用于错误提示便于排查
     * @return array|false 成功返回 ['cat_id'=>leaf_id, 'cat_path'=>'id1,id2,...']，失败返回 false
     */
    public function getCatsByLevelNames($level_names, &$msg, $lineNum = 0, $material_bn = '') {
        $basicMaterialCatObj = app::get('material')->model('basic_material_cat');
        $parent_id = 0;
        $path_ids = [];
        $path_names = [];

        for ($i = 0; $i < 5; $i++) {
            $name = isset($level_names[$i]) ? trim($level_names[$i]) : '';
            if ($name === '') {
                if (!empty(array_filter(array_slice($level_names, $i)))) {
                    $msg['warning'][] = '第' . $lineNum . '行：请从分类一级开始连续填写' . ($material_bn ? ' 基础物料编码：' . $material_bn : '');
                    return false;
                }
                break;
            }
            $filter = ['parent_id' => $parent_id, 'cat_name' => $name, 'disabled' => 'false'];
            $rs = $basicMaterialCatObj->dump($filter, 'cat_id,cat_path');
            if (!$rs) {
                $path_names[] = $name;
                $pathStr = implode('/', $path_names);
                $msg['warning'][] = '第' . $lineNum . '行：分类「' . $pathStr . '」不存在' . ($material_bn ? ' 基础物料编码：' . $material_bn : '');
                return false;
            }
            $path_ids[] = $rs['cat_id'];
            $path_names[] = $name;
            $parent_id = $rs['cat_id'];
        }

        if (empty($path_ids)) {
            return false;
        }
        $leaf_id = end($path_ids);
        $cat_path = implode(',', $path_ids);
        return ['cat_id' => $leaf_id, 'cat_path' => $cat_path];
    }
}
