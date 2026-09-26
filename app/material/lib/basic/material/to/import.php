<?php
/**
 * 基础物料的队列任务导入最终执行Lib类
 *
 * @author kamisama.xia@gmail.com
 * @version 0.1
 */

class material_basic_material_to_import {

    /**
     * 基础物料关联条码的队列任务执行
     *
     * @param String $cursor_id
     * @param Array $params
     * @param String $errmsg
     * @return Boolean
     */
    function run(&$cursor_id,$params,&$errmsg){
        $importObj = app::get($params['app'])->model($params['mdl']);
        $barcodeObj = app::get('material')->model('barcode');
        $basicMaterialExtObj = app::get('material')->model('basic_material_ext');
        $basicMaterialFeatureGrpObj = app::get('material')->model('basic_material_feature_group');
        $basicMaterialStockObj = app::get('material')->model('basic_material_stock');
        $basicMaterialConfObj = app::get('material')->model('basic_material_conf');

        $basicMaterialConfObjSpe    = app::get('material')->model('basic_material_conf_special');
        $dataSdf               = $params['sdfdata'];
        $opInfo                = !empty($params['opInfo']) ? $params['opInfo'] : null;
        $importLogLib          = kernel::single('material_basic_material');
        $createSalesByBmIds    = array();//自动生成销售物料的基础物料bm_id

        foreach ($dataSdf as $v){
            $importData = array(
                'material_name' => $v['material_name'],
                'material_bn' => $v['material_bn'],
                'material_bn_crc32' => $v['material_bn_crc32'],
                'type' => $v['type'],
                'visibled' => $v['visibled'],
                'create_time' => time(),
                'omnichannel' => intval($v['omnichannel']),
                'serial_number'=>$v['serial_number'],
                'material_spu'=>$v['material_spu'],
                'tax_rate'=> !empty($v['tax_rate']) ? $v['tax_rate'] : 0,
                'tax_name'=>$v['tax_name'],
                'tax_code'=>$v['tax_code'],
                'color'=>$v['color'],
                'size'=>$v['size'],
                'is_o2o_sales'=>$v['is_o2o_sales'],
            );
            if($v['cat_id']){
                $importData['cat_id'] = $v['cat_id'];
            }
            if($v['cat_path']){
                $importData['cat_path'] = $v['cat_path'];
            }
            $is_save = $importObj->save($importData);
            if($is_save){
                //保存条码信息
                $sdf = array(
                    'bm_id' => $importData['bm_id'],
                    'type' => material_codebase::getBarcodeType(),
                    'code' => $v['material_code'],
                );
                $barcodeObj->insert($sdf);

                //保存保质期配置
                $useExpireConfData = array(
                    'bm_id' => $importData['bm_id'],
                    'use_expire' => $v['use_expire'],
                    'warn_day' => $v['warn_day'] ?  $v['warn_day'] : 0,
                    'quit_day' => $v['quit_day'] ? $v['quit_day'] : 0,
                    'create_time' => time(),
                );
                $basicMaterialConfObj->save($useExpireConfData);

                //如果是组合物料保存相关数据
                if(in_array($v['type'],array('1','4'))){
                    $basicMaterialCombinationItemsObj = app::get('material')->model('basic_material_combination_items');
                    if(isset($v['at'])){
                        foreach($v['at'] as $k=>$num){
                            $tmpChildMaterialInfo = $importObj->dump($k, 'material_name,material_bn');

                            $addCombinationData = array(
                                'pbm_id' => $importData['bm_id'],
                                'bm_id' => $k,
                                'material_num' => $num,
                                'material_name' => $tmpChildMaterialInfo['material_name'],
                                'material_bn' => $tmpChildMaterialInfo['material_bn'],
                                'material_bn_crc32' => sprintf('%u',crc32($tmpChildMaterialInfo['material_bn'])),
                            );
                            $basicMaterialCombinationItemsObj->insert($addCombinationData);
                            $addCombinationData = null;
                        }
                    }
                }

                //保存基础物料的关联的特性
                //to do 暂时去掉这块逻辑，有待实现

                //保存物料扩展信息
                $addExtData = array(
                    'bm_id' => $importData['bm_id'],
                    'cost' => floatval($v['cost']),
                    'retail_price' =>floatval($v['retail_price']),
                    'weight' => floatval($v['weight']),
                    'unit' => $v['unit'],
                    'cat_id' => (int)$v['goods_type_id'],
                    'specifications' => $v['specifications'],
                    'brand_id' => (int)$v['brand_id'],
                );
                $basicMaterialExtObj->insert($addExtData);

                //保存物料库存信息
                $addStockData = array(
                    'bm_id' => $importData['bm_id'],
                    'store' => 0,
                    'store_freeze' => 0,
                );
                $basicMaterialStockObj->insert($addStockData);

                //保存特殊扫码配置信息
                $addScanConfInfo    = array(
                                        'bm_id' => $importData['bm_id'],
                                        'openscan' => $v['special_setting'],
                                        'fromposition' => $v['first_num'],
                                        'toposition' => $v['last_num'],
                                    );
                $basicMaterialConfObjSpe->insert($addScanConfInfo);

                //
                 //新增属性参数
                $season = $v['season'];
                $uppermatnm = $v['uppermatnm'];
                $widthnm = $v['widthnm'];
                $gendernm = $v['gendernm'];
                $subbrand = $v['subbrand'];
                $modelnm = $v['modelnm'];
                $props = array();
                
                if($season){

                    $props['season'] = $season;
                }
                if($uppermatnm){
                    
                    $props['uppermatnm'] = $uppermatnm;
                    
                }
                if($widthnm){
                    $props['widthnm'] = $widthnm;
                    
                }
                if($gendernm){
                    $props['gendernm'] = $gendernm;
                    
                }
                if($modelnm){
                    $props['modelnm'] = $modelnm;
                }
                
                if ($v['custom']) {
                    $customcolsLib = kernel::single('material_customcols');
                    foreach ($customcolsLib->normalizeImportCustomProps($v['custom']) as $ck => $cv) {
                        $props[$ck] = $cv;
                    }
                }

                if ($props) {
                    $propsMdl = app::get('material')->model('basic_material_props');
                    $customcolsLib = isset($customcolsLib) ? $customcolsLib : kernel::single('material_customcols');

                    foreach ($props as $pk => $pv) {
                        if (!$customcolsLib->isPropValueSavable($pv)) {
                            continue;
                        }
                        $propsdata = array(
                            'bm_id'       => $importData['bm_id'],
                            'props_col'   => $pk,
                            'props_value' => $pv,
                        );
                        $propsMdl->save($propsdata);
                    }
                }


                //是否自动生成销售物料
                $v['create_material_sales']    = intval($v['create_material_sales']);
                if($v['create_material_sales'] === 1)
                {
                    $createSalesByBmIds[]    = $importData['bm_id'];
                }
                $importLogLib->writeImportLog(
                    'basic_material_import@wms',
                    $importData['bm_id'],
                    '批量导入新增基础物料：' . $importData['material_bn'] . ' ' . $importData['material_name'],
                    $opInfo
                );
            }else{
                $m = $importObj->db->errorinfo();
                if(!empty($m)){
                    $errmsg.=$m.";";
                }
            }
        }

        //自动生成销售物料
        if($createSalesByBmIds)
        {
            $bm_ids    = implode(',', $createSalesByBmIds);
            $result    = kernel::single('material_basic_exchange')->process($bm_ids);

            if($result['fail'] > 0)
            {
                $errmsg    .= "自动生成销售物料成功:". $result['total'] ."个,失败:". $result['fail'] ."个";
            }
        }

        return false;
    }
}
