<?php

class openapi_data_original_basicmaterial
{

    protected $_type = array(
        '1' => '成品',
        '2' => '半成品',
    );

    protected $_visibled = array(
        '1' => '显示',
        '2' => '隐藏',
    );
    
    // 允许绑定明细物料属性, 1 成品 4 礼盒
    public $allowBindItemTypes = [1, 4];

    public function getList($filter, $offset = 0, $limit = 40)
    {

        $basicMaterialObj                 = app::get('material')->model('basic_material');
        $basicMaterialExtObj              = app::get('material')->model('basic_material_ext');
        $basicMaterialBarcodeObj          = app::get('material')->model('barcode');
        $basicMaterialCombinationItemsObj = app::get('material')->model('basic_material_combination_items');
        $materialLib                      = kernel::single('material_basic_material');

        $count         = $basicMaterialObj->count($filter);
        $arr_type_info = $arr_brand_info = [];

        if ($count > 0) {
            $basicMaterialList = $basicMaterialObj->getList('*', $filter, $offset, $limit);
            //商品id
            $arr_bm_id = array_column($basicMaterialList, 'bm_id');

            //商品拓展信息
            $arr_basic_materiaExt = $basicMaterialExtObj->getList('*', array('bm_id' => $arr_bm_id));
            $arr_basic_materiaExt = array_column($arr_basic_materiaExt, null, 'bm_id');

            //条码
            $arr_barcode = $basicMaterialBarcodeObj->getList('*', array('bm_id' => $arr_bm_id));
            $arr_barcode = array_column($arr_barcode, null, 'bm_id');

            //商品类型
            $arr_tmp_type_id = array_unique(array_column($arr_basic_materiaExt, 'cat_id'));
            foreach ($arr_tmp_type_id as $tp_id) {
                if ($tp_id > 0) $arr_type_id[] = $tp_id;
            }
            if (!empty($arr_type_id)) {
                $arr_type_info = app::get('ome')->model('goods_type')->getList('*', array('type_id' => $arr_type_id));
                $arr_type_info = array_column($arr_type_info, null, 'type_id');
            }

            //商品分类
            $arr_cat_id   = array_unique(array_column($basicMaterialList, 'cat_id'));
            $arr_cat_info = app::get('material')->model('basic_material_cat')->getList('*', array('cat_id' => $arr_cat_id));
            $arr_cat_info = array_column($arr_cat_info, null, 'cat_id');

            //商品品牌
            $arr_tmp_brand_id = array_unique(array_column($arr_basic_materiaExt, 'brand_id'));
            foreach ($arr_tmp_brand_id as $b_id) {
                if ($b_id > 0) $arr_brand_id[] = $b_id;
            }
            if (!empty($arr_brand_id)) {
                $arr_brand_info = app::get('ome')->model('brand')->getList('*', array('brand_id' => $arr_brand_id));
                $arr_brand_info = array_column($arr_brand_info, null, 'brand_id');
            }

            //物料属性
            $materialTypes = $materialLib->get_material_types();
            $objM = kernel::single('openapi_api_function_v1_basicmaterial');

            // 自定义字段定义 + 批量取值
            $customcols = kernel::single('material_customcols')->getcols();
            $customKeys = array();
            foreach ($customcols as $cv) {
                if (!empty($cv['col_key']) && preg_match('/^[a-zA-Z0-9_]+$/', $cv['col_key'])) {
                    $customKeys[] = $cv['col_key'];
                }
            }
            $propsMap = array(); // bm_id => [col_key => props_value]
            if ($arr_bm_id && $customKeys) {
                $propsList = app::get('material')->model('basic_material_props')->getList(
                    'bm_id,props_col,props_value',
                    array(
                        'bm_id|in'     => $arr_bm_id,
                        'props_col|in' => $customKeys,
                    ),
                    0,
                    -1
                );
                if (is_array($propsList)) {
                    foreach ($propsList as $p) {
                        $propsMap[$p['bm_id']][$p['props_col']] = $p['props_value'];
                    }
                }
            }

            $lists = array();
            foreach ($basicMaterialList as $basicMaterial) {
                $basicMExtInfo = $arr_basic_materiaExt[$basicMaterial['bm_id']];
                $typeRow       = empty($arr_type_info[$basicMExtInfo['cat_id']]) ? [] : $arr_type_info[$basicMExtInfo['cat_id']];
                $basicMBarcode = $arr_barcode[$basicMaterial['bm_id']];
                $cat_info      = $arr_cat_info[$basicMaterial['cat_id']];
                $brand_info    = $arr_brand_info[$basicMExtInfo['brand_id']];


                $lists[$basicMaterial['bm_id']]['material_name']    = $objM->charFilter($basicMaterial['material_name']);
                $lists[$basicMaterial['bm_id']]['material_bn']      = $basicMaterial['material_bn'];
                $lists[$basicMaterial['bm_id']]['material_spu']     = $basicMaterial['material_spu'];
                $lists[$basicMaterial['bm_id']]['visibled']         = $basicMaterial['visibled'];
                $lists[$basicMaterial['bm_id']]['serial_number']    = $basicMaterial['serial_number'];
                $lists[$basicMaterial['bm_id']]['type']             = $basicMaterial['type'];
                $lists[$basicMaterial['bm_id']]['color']            = $objM->charFilter($basicMaterial['color']);
                $lists[$basicMaterial['bm_id']]['size']             = $objM->charFilter($basicMaterial['size']);
                $lists[$basicMaterial['bm_id']]['type_name']        = $materialTypes[$basicMaterial['type']];
                $lists[$basicMaterial['bm_id']]['type_name']        = empty($materialTypes[$basicMaterial['type']]) ? '' : $materialTypes[$basicMaterial['type']];
                $lists[$basicMaterial['bm_id']]['goods_type_id']    = $typeRow['type_id'];
                $lists[$basicMaterial['bm_id']]['goods_type_name']  = $objM->charFilter($typeRow['name']);
                $lists[$basicMaterial['bm_id']]['goods_cat_name']   = $objM->charFilter($cat_info['cat_name']);
                $lists[$basicMaterial['bm_id']]['goods_cat_code']   = $cat_info['cat_code'];
                $lists[$basicMaterial['bm_id']]['goods_brand_name'] = $objM->charFilter($brand_info['brand_name']);
                $lists[$basicMaterial['bm_id']]['goods_brand_code'] = $brand_info['brand_code'];
                $lists[$basicMaterial['bm_id']]['cost']             = $basicMExtInfo['cost'];
                $lists[$basicMaterial['bm_id']]['retail_price']     = $basicMExtInfo['retail_price'];
                $lists[$basicMaterial['bm_id']]['weight']           = $basicMExtInfo['weight'];
                $lists[$basicMaterial['bm_id']]['unit']             = $basicMExtInfo['unit'];
                $lists[$basicMaterial['bm_id']]['specifications']   = $objM->charFilter($basicMExtInfo['specifications']);
                $lists[$basicMaterial['bm_id']]['barcode']          = empty($basicMBarcode['code']) ? '' : $basicMBarcode['code'];

                // 自定义字段（与桌面自定义列配置一致）
                $bmProps = isset($propsMap[$basicMaterial['bm_id']]) ? $propsMap[$basicMaterial['bm_id']] : array();
                $customProps  = array();
                foreach ($customcols as $cv) {
                    $ck = isset($cv['col_key']) ? $cv['col_key'] : '';
                    if ($ck === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $ck)) {
                        continue;
                    }
                    $customProps[$ck] = array_key_exists($ck, $bmProps) ? $bmProps[$ck] : '';
                }
                $lists[$basicMaterial['bm_id']]['props'] = $customProps;

                if (in_array($basicMaterial['type'], ['1','4'])) {
                    $seMiBasicMInfos = $basicMaterialCombinationItemsObj->getList('bm_id,material_name,material_bn,material_num', array('pbm_id' => $basicMaterial['bm_id']), 0, -1);
                    if ($seMiBasicMInfos) {
                        foreach ($seMiBasicMInfos as $basicMInfo) {
                            if(!$arr_basic_materiaExt[$basicMInfo['bm_id']]) {
                                $arr_basic_materiaExt[$basicMInfo['bm_id']] = $basicMaterialExtObj->db_dump(array('bm_id' => $basicMInfo['bm_id']), 'retail_price');
                            }
                            $lists[$basicMaterial['bm_id']]['semi_materials'][] = array(
                                'material_bn'     => $basicMInfo['material_bn'],
                                'material_name'   => $objM->charFilter($basicMInfo['material_name']),
                                'retail_price'    => $arr_basic_materiaExt[$basicMInfo['bm_id']]['retail_price'],
                                'material_number' => $basicMInfo['material_num'],
                            );
                        }
                    }
                }
            }
            return array(
                'lists' => $lists,
                'count' => $count,
            );
        } else {
            return array(
                'lists' => array(),
                'count' => 0,
            );
        }
    }

    /**
     * 基础物料新增时的参数检查方法
     *
     * @param Array $params
     * @param String $err_msg
     * @return Boolean
     */
    function checkAddParams(&$params, &$err_msg)
    {
        if (empty($params['material_name']) || empty($params['material_bn'])) {
            $err_msg = "必填信息不能为空";
            return false;
        }
    
        if ($params['shelf_life'] && !is_numeric($params['shelf_life'])) {
            $err_msg = "保质期信息请填写整数";
            return false;
        }

        //去除空格
        $params['material_bn']   = trim($params['material_bn']);
        $params['material_code'] = trim($params['material_code']);

        //基础物料信息
        $basicMaterialObj  = app::get('material')->model('basic_material');
        $basicMaterialInfo = $basicMaterialObj->getList('material_bn', array('material_bn' => $params['material_bn']));
        if ($basicMaterialInfo) {
            $err_msg = "当前新增的物料编码已被使用，不能重复";
            return false;
        }

        $params['material_bn_crc32'] = sprintf('%u', crc32($params['material_bn']));
    
        if (in_array($params['type'], $this->allowBindItemTypes)) {
            if (isset($params['at'])) {
                $basicM_bns = $tmp_at = array();
                foreach ($params['at'] as $bn => $val) {
                    if ($val < 1) {
                        $err_msg = "数量必须大于0";
                        return false;
                    }

                    $basicMaterialObj = app::get('material')->model('basic_material');
                    $basicInfo        = $basicMaterialObj->getList('bm_id', array('material_bn' => $bn), 0, 1);
                    if (!$basicInfo) {
                        $err_msg = "找不到关联的基础物料";
                        return false;
                    } else {
                        $tmp_at[$basicInfo[0]['bm_id']] = $val;
                        $basicM_bns[$bn]                = $basicInfo[0]['bm_id'];
                    }
                }
                unset($params['at']);
                $params['at'] = $tmp_at;
            }
        }
        if (!empty($params['gtype_name'])) {
            $params['gtype_name'] = trim($params['gtype_name']);
            $goods_type           = app::get('ome')->model('goods_type')->dump(array('name' => $params['gtype_name'], 'disabled' => "false"), 'type_id,name');
            if (empty($goods_type)) {
                $add               = [
                    'name'    => $params['gtype_name'],
                    'alias'   => '',
                    'setting' => 'a:1:{s:9:"use_brand";s:0:"";}',
                    'addon'   => 'a:1:{s:7:"jd_code";s:0:"";}'
                ];
                $params['type_id'] = app::get('ome')->model('goods_type')->insert($add);
            } else {
                $params['type_id'] = $goods_type['type_id'];
            }
        }

        if (!empty($params['brand_code'])) {
            $params['brand_code'] = trim($params['brand_code']);
            $brand                = app::get('ome')->model('brand')->dump(array('brand_code' => $params['brand_code'], 'disabled' => "false"), 'brand_id,brand_code');
            if (empty($brand)) {
                if (empty($params['brand_name'])) {
                    $err_msg = "品牌名称不能为空";
                    return false;
                }
                $add                = [
                    'brand_name'     => trim($params['brand_name']),
                    'brand_code'     => $params['brand_code'],
                    'brand_keywords' => '',
                    'brand_url'      => '',
                ];
                $params['brand_id'] = app::get('ome')->model('brand')->insert($add);
            } else {
                $params['brand_id'] = $brand['brand_id'];
            }
        }


        if(isset($params['category']) && !empty($params['category'])){
            $catLib = kernel::single('material_basic_material_cat');
            $rs = $catLib->multiLevelSave($params['category']);
            if(!$rs || $rs['rsp'] == 'fail'){
                $err_msg = $rs['msg'] ?? '分类信息保存失败';
                return false;
            }
            $params['cat_id'] = $rs['data']['cat_id'];
            $params['cat_path'] = substr($rs['data']['cat_path'] . $params['cat_id'], 1);
        }

        return true;
    }

    public function add($data, &$code, &$sub_msg)
    {
        $result = array('rsp' => 'succ');

        if (!$this->checkAddParams($data, $error_msg)) {
            $result['rsp'] = 'fail';
            $result['msg'] = $error_msg;
            return $result;
        }

        $basicMaterialObj           = app::get('material')->model('basic_material');
        $basicMaterialExtObj        = app::get('material')->model('basic_material_ext');
        $basicMaterialFeatureGrpObj = app::get('material')->model('basic_material_feature_group');
        $basicMaterialStockObj      = app::get('material')->model('basic_material_stock');
        $basicMaterialConfObj       = app::get('material')->model('basic_material_conf');
        $codebaseObj                = app::get('material')->model('codebase');

        //保存物料主表信息
        $addData = array(
            'material_name'     => $data['material_name'],
            'material_bn'       => $data['material_bn'],
            'material_spu'      => $data['material_spu'],
            'material_bn_crc32' => $data['material_bn_crc32'],
            'serial_number'     => $data['serial_number'] ? $data['serial_number'] : 'false',
            'type'              => $data['type'],
            'visibled'          => $data['visibled'],

            'color'             => $data['color'], 
            'size'              => $data['size'], 
            'create_time'       => time(),
            'source'            =>  $data['source'] ? $data['source'] : 'openapi',
            'tax_code'          =>  $data['tax_code'],
            'tax_name'          =>  $data['tax_name'],
            'tax_rate'          =>  $data['tax_rate'],
        );

        if(isset($data['cat_id'])){
            $addData['cat_id'] = $data['cat_id'];
        }
        if(isset($data['cat_path'])){
            $addData['cat_path'] = $data['cat_path'];
        }

        if($data['source'] && in_array($data['source'],array('microsoft'))){
            $addData['is_o2o_sales'] = 1;
        }
        $is_save = $basicMaterialObj->save($addData);

        if ($is_save) {
            //保存物料条码信息
            if (isset($data['material_code']) && $data['material_code']) {
                $code_type   = material_codebase::getBarcodeType();
                $addCodeData = array(
                    'bm_id' => $addData['bm_id'],
                    'type'  => $code_type,
                    'code'  => $data['material_code'],
                );
                $codebaseObj->insert($addCodeData);
            }
            //保存保质期配置
            $useExpireConfData = array(
                'bm_id'       => $addData['bm_id'],
                'use_expire'  => $data['use_expire'] == 1 ? 1 : 2,
                'warn_day'    => $data['warn_day'] ? $data['warn_day'] : 0,
                'quit_day'    => $data['quit_day'] ? $data['quit_day'] : 0,
                'create_time' => time(),
            );
            if (isset($data['shelf_life']) && $data['shelf_life']) {
                $useExpireConfData['shelf_life']     = $data['shelf_life'];//保质期(小时)
            }
            $useExpireConfData['use_expire_wms'] = $useExpireConfData['use_expire'];
            $basicMaterialConfObj->save($useExpireConfData);

            //如果关联半成品数据
            if (in_array($addData['type'], $this->allowBindItemTypes)) {
                $basicMaterialCombinationItemsObj = app::get('material')->model('basic_material_combination_items');
                if (isset($data['at'])) {
                    foreach ($data['at'] as $k => $v) {
                        $tmpChildMaterialInfo = $basicMaterialObj->dump($k, 'material_name,material_bn');

                        $addCombinationData = array(
                            'pbm_id'            => $addData['bm_id'],
                            'bm_id'             => $k,
                            'material_num'      => $v,
                            'material_name'     => $tmpChildMaterialInfo['material_name'],
                            'material_bn'       => $tmpChildMaterialInfo['material_bn'],
                            'material_bn_crc32' => sprintf('%u', crc32($tmpChildMaterialInfo['material_bn'])),
                        );
                        $basicMaterialCombinationItemsObj->insert($addCombinationData);
                        $addCombinationData = null;
                    }
                }
            }

            //保存基础物料的关联的特性
            if ($data['ftgp_id']) {
                $addBindFeatureData = array(
                    'bm_id'            => $addData['bm_id'],
                    'feature_group_id' => $data['ftgp_id'],
                );
                $basicMaterialFeatureGrpObj->insert($addBindFeatureData);
                $addBindFeatureData = null;
            }

            //保存物料扩展信息
            $addExtData = array(
                'bm_id'        => $addData['bm_id'],
                'cost'         => $data['cost'] ? $data['cost'] : 0.00,
                'retail_price' => $data['retail_price'] ? $data['retail_price'] : 0.00,
                'weight'       => $data['weight'] ? $data['weight'] : 0.00,
                'unit'         => $data['unit'],
                'brand_id'     => $data['brand_id'] ? $data['brand_id'] : 0,
                'cat_id'       => $data['type_id'] ? $data['type_id'] : 0,
                'specifications' => isset($data['spec']) ? $data['spec'] : '',
                'box_spec'     => isset($data['box_spec']) ? $data['box_spec'] : '',//箱规
                'net_weight'   => $data['net_weight'], 
                'length'        => $data['length'],
                'width'         => $data['width'],
                'high'          => $data['high'],
            );
            $basicMaterialExtObj->insert($addExtData);

            //保存物料库存信息
            // * redis库存高可用，废弃掉直接修改db库存、冻结的方法
            $addStockData = array(
                'bm_id'        => $addData['bm_id'],
                // 'store'        => $data['store'],
                // 'store_freeze' => $data['store_freeze'],
                'store'        => 0,
                'store_freeze' => 0,
            );
            $basicMaterialStockObj->insert($addStockData);
            
            //自动生成销售物料并创建绑定关系
            if($data['is_auto_generate'] == 1){

                $sales_material_type = $data['type']=='4' ? '6' : '1';
                $sm_data = array(
                    'sales_material_name' => $data['material_name'],
                    'sales_material_bn' => $data['material_bn'],
                    'sales_material_type' => $sales_material_type,
                    'bind_bn' => $data['material_bn'],
                    'retail_price' => $data['retail_price'], //零售价
                    'cost' => $data['cost'] ? $data['cost'] : 0.00, //成本价
                    'tax_code'          =>  $data['tax_code'],
                    'tax_name'          =>  $data['tax_name'],
                    'tax_rate'          =>  $data['tax_rate'],
                );
                $result = kernel::single('openapi_data_original_salesmaterial')->add($sm_data, $code, $sub_msg);
            }
            
            //新增属性参数
            $season = $data['season'];
            $uppermatnm = $data['uppermatnm'];
            $widthnm = $data['widthnm'];
            $gendernm = $data['gendernm'];
            $subbrand = $data['subbrand'];
            $modelnm = $data['modelnm'];
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
            
           
            $customcols = kernel::single('material_customcols')->getcols();

            foreach($customcols as $cv){
             
                if(isset($data[$cv['col_key']])){
                    $props[$cv['col_key']] = $data[$cv['col_key']];
                }
            }
           
            if($props){
                $propsMdl = app::get('material')->model('basic_material_props');

                $propsdata = array();
                foreach($props as $pk=>$pv){

                    if($pv){
                        $propsdata = array(
                            'bm_id'         =>  $addData['bm_id'],
                            'props_col'     =>  $pk,
                            'props_value'   =>  $pv,
                        );
                        $propsMdl->save($propsdata);
                    }
                }
            }
            //是否推送WMS
            if (isset($data['is_wms']) && in_array($data['is_wms'], ['true', true], true)) {
                kernel::single('console_goodssync')->addProductSyncWms([$addData['bm_id']]);
            }

            //logs
            $operationLogObj = app::get('ome')->model('operation_log');
            $operationLogObj->write_log('basic_material_add@wms', $addData['bm_id'], 'openapi添加基础物料');
        } else {
            $result = array('msg' => '基础物料添加失败', 'rsp' => 'fail');
        }

        return $result;
    }

    /**
     * 基础物料编辑时的参数检查方法
     *
     * @param Array $params
     * @param String $err_msg
     * @return Boolean
     */
    function checkEditParams(&$params, &$err_msg)
    {

        if (empty($params['material_name']) || empty($params['material_bn'])) {
            $err_msg = "必填信息不能为空";
            return false;
        }
    
        if ($params['shelf_life'] && !is_numeric($params['shelf_life'])) {
            $err_msg = "保质期信息请填写整数";
            return false;
        }

        //去除空格
        $params['material_bn']   = trim($params['material_bn']);
        $params['material_code'] = trim($params['material_code']);

        //基础物料信息
        $basicMaterialObj       = app::get('material')->model('basic_material');
        $basicMaterialExistInfo = $basicMaterialObj->getList('bm_id', array('material_bn' => $params['material_bn']));
        if (!$basicMaterialExistInfo) {
            $err_msg = "当前物料不存在";
            return false;
        } else {
            $params['bm_id']       = $basicMaterialExistInfo[0]['bm_id'];
            $params['old_bm_info'] = $basicMaterialExistInfo[0];
        }
    
    
        if (in_array($params['type'], $this->allowBindItemTypes)) {
            if (isset($params['at'])) {
                $basicM_bns = $tmp_at = array();
                foreach ($params['at'] as $bn => $val) {
                    if ($val < 1) {
                        $err_msg = "数量必须大于0";
                        return false;
                    }

                    $basicMaterialObj = app::get('material')->model('basic_material');
                    $basicInfo        = $basicMaterialObj->getList('bm_id', array('material_bn' => $bn), 0, 1);
                    if (!$basicInfo) {
                        $err_msg = "找不到关联的基础物料";
                        return false;
                    } else {
                        $tmp_at[$basicInfo[0]['bm_id']] = $val;
                        $basicM_bns[$bn]                = $basicInfo[0]['bm_id'];
                    }
                }
                unset($params['at']);
                $params['at'] = $tmp_at;
            }
        }


        if (!empty($params['gtype_name'])) {
            $params['gtype_name'] = trim($params['gtype_name']);
            $goods_type           = app::get('ome')->model('goods_type')->dump(array('name' => $params['gtype_name'], 'disabled' => "false"), 'type_id,name');
            if (empty($goods_type)) {
                $add               = [
                    'name'    => $params['gtype_name'],
                    'alias'   => '',
                    'setting' => 'a:1:{s:9:"use_brand";s:0:"";}',
                    'addon'   => 'a:1:{s:7:"jd_code";s:0:"";}'
                ];
                $params['type_id'] = app::get('ome')->model('goods_type')->insert($add);
            } else {
                $params['type_id'] = $goods_type['type_id'];
            }
        }

        if (!empty($params['brand_code'])) {
            $params['brand_code'] = trim($params['brand_code']);
            $brand                = app::get('ome')->model('brand')->dump(array('brand_code' => $params['brand_code'], 'disabled' => "false"), 'brand_id,brand_code');
            if (empty($brand)) {
                if (empty($params['brand_name'])) {
                    $err_msg = "品牌名称不能为空";
                    return false;
                }
                $add                = [
                    'brand_name'     => trim($params['brand_name']),
                    'brand_code'     => $params['brand_code'],
                    'brand_keywords' => '',
                    'brand_url'      => '',
                ];
                $params['brand_id'] = app::get('ome')->model('brand')->insert($add);
            } else {
                $params['brand_id'] = $brand['brand_id'];
            }
        }


        if(isset($params['category']) && !empty($params['category'])){
            $catLib = kernel::single('material_basic_material_cat');
            $rs = $catLib->multiLevelSave($params['category']);
            if(!$rs || $rs['rsp'] == 'fail'){
                $err_msg = $rs['msg'] ?? '分类信息保存失败';
                return false;
            }
            $params['cat_id'] = $rs['data']['cat_id'];
            $params['cat_path'] = substr($rs['data']['cat_path'] . $params['cat_id'], 1);
        }

        return true;
    }

    /**
     * 检查基础物料个别参数是否可编辑
     *
     * @param Int $bm_id
     * @return Array
     */
    function checkEditReadOnly($bm_id)
    {
        $readonly = array('type' => false);

        $basicMStockFreezeLib = kernel::single('material_basic_material_stock_freeze');

        //如果基础物料有库存、冻结、采购、订单或者绑定过成品、半成品，那么物料属性不能编辑
        $basicMStockObj = app::get('material')->model('basic_material_stock');
        $storeInfo      = $basicMStockObj->getList('store,store_freeze', array('bm_id' => $bm_id));

        //根据基础物料ID获取对应的冻结库存
        $storeInfo[0]['store_freeze'] = $basicMStockFreezeLib->getMaterialStockFreeze($bm_id);

        if ($storeInfo[0]['store'] > 0 || $storeInfo[0]['store_freeze'] > 0) {
            $is_type_readonly = true;
        }

        $purchaseItemObj = app::get('purchase')->model('po_items');
        $purchaseInfo    = $purchaseItemObj->getList('product_id', array('product_id' => $bm_id), 0, 1);
        if ($purchaseInfo) {
            $is_type_readonly       = true;
            $is_use_expire_readonly = true;
        }

        $orderItemObj = app::get('ome')->model('order_items');
        $orderInfo    = $orderItemObj->getList('product_id', array('product_id' => $bm_id), 0, 1);
        if ($orderInfo) {
            $is_type_readonly = true;
        }

        $basicMaterialCombinationItemsObj = app::get('material')->model('basic_material_combination_items');
        $basicMaterialCombinationInfo     = $basicMaterialCombinationItemsObj->getList('bm_id', array('bm_id' => $bm_id));
        if ($basicMaterialCombinationInfo) {
            $is_type_readonly = true;
        }

        $basicMaterialCombinationPInfo = $basicMaterialCombinationItemsObj->getList('pbm_id', array('pbm_id' => $bm_id));
        if ($basicMaterialCombinationPInfo) {
            $is_type_readonly = true;
        }

        //如果有批次明细就不能变更保质期的开关
        $basicMaterialStorageLifeObj = app::get('material')->model('basic_material_storage_life');
        $expireItemsInfo             = $basicMaterialStorageLifeObj->getList('bm_id', array('bm_id' => $bm_id), 0, 1);
        if ($expireItemsInfo) {
            $is_use_expire_readonly = true;
        }

        //类目绑定后什么情况下可以解绑换成别的?需要判断么?

        if ($is_type_readonly) {
            $readonly['type'] = true;
        }

        if ($is_use_expire_readonly) {
            $readonly['use_expire'] = true;
        }

        return $readonly;
    }

    public function edit($data, &$code, &$sub_msg)
    {
        $result = array('rsp' => 'succ');

        //检查参数
        if (!$this->checkEditParams($data, $error_msg)) {
            $result['rsp'] = 'fail';
            $result['msg'] = $error_msg;
            return $result;
        }

        $basicMaterialObj           = app::get('material')->model('basic_material');
        $basicMaterialExtObj        = app::get('material')->model('basic_material_ext');
        $basicMaterialFeatureGrpObj = app::get('material')->model('basic_material_feature_group');
        $basicMaterialStockObj      = app::get('material')->model('basic_material_stock');
        $basicMaterialConfObj       = app::get('material')->model('basic_material_conf');
        $codebaseObj                = app::get('material')->model('codebase');

        //检查部分按钮是否只读不可能修改
        $readonly     = $this->checkEditReadOnly($data['bm_id']);
        $data['type'] = $readonly['type'] ? ($data['old_bm_info']['type'] ? $data['old_bm_info']['type'] : $data['type']) : $data['type'];

        $old_bm_cnf         = $basicMaterialConfObj->dump($data['bm_id']);
        $data['use_expire'] = $readonly['use_expire'] ? $old_bm_cnf['use_expire'] : $data['use_expire'];

        //更新基础物料基本信息
        $updateData['material_name'] = $data['material_name'];
        $updateData['material_spu']  = $data['material_spu'];

        if ($data['serial_number']) {
            $updateData['serial_number'] = $data['serial_number'];
        }

        $updateData['type']     = $data['type'];
        $updateData['visibled'] = $data['visibled'];

        if($data['color']){
            $updateData['color']     = $data['color'];
            
        }
        if($data['size']){
            $updateData['size']     = $data['size'];
        }
        
        if($data['source']){
            $updateData['source']     = $data['source'];
            if(in_array($data['source'],array('microsoft'))){
                $updateData['is_o2o_sales'] = 1;
            }
        }

        if($data['cat_id']){
            $updateData['cat_id']     = $data['cat_id'];
        }

        if($data['cat_path']){
            $updateData['cat_path']     = $data['cat_path'];
        }

        if($data['tax_code']){
            $updateData['tax_code']     = $data['tax_code'];
        }
        if($data['tax_name']){
            $updateData['tax_name']     = $data['tax_name'];
        }
        if($data['tax_rate']){
            $updateData['tax_rate']     = $data['tax_rate'];
        }

        $filter['bm_id']        = $data['bm_id'];


        $is_update = $basicMaterialObj->update($updateData, $filter);
        if ($is_update) {
            //更新物料条码信息
            if (isset($data['material_code']) && $data['material_code']) {
                $editCodeData = array(
                    'code' => $data['material_code'],
                );
                $code_type             = material_codebase::getBarcodeType();
                $barcodeInfo = $codebaseObj->db_dump(array('type' => $code_type, 'bm_id' => $filter['bm_id']), 'bm_id,code');
                if ($barcodeInfo) {
                    $codebaseObj->update($editCodeData, ['bm_id' => $filter['bm_id'], 'type' => $code_type]);
                } else {
                    $editCodeData['bm_id'] = $filter['bm_id'];
                    $editCodeData['type']  = $code_type;
                    $codebaseObj->insert($editCodeData);
                }
            }
            
            //保存保质期配置
            $useExpireConfData = array(
                'bm_id'      => $filter['bm_id'],
                'use_expire' => $data['use_expire'] == 1 ? 1 : 2,
                'warn_day'   => $data['warn_day'] ? $data['warn_day'] : 0,
                'quit_day'   => $data['quit_day'] ? $data['quit_day'] : 0,
            );
            if (isset($data['shelf_life']) && $data['shelf_life']) {
                $useExpireConfData['shelf_life']     = $data['shelf_life'];//保质期(小时)
            }
            $useExpireConfData['use_expire_wms'] = $useExpireConfData['use_expire'];

            $basicMaterialConfObj->save($useExpireConfData);
            //如果关联半成品数据
            if (in_array($updateData['type'], $this->allowBindItemTypes)) {
                $basicMaterialCombinationItemsObj = app::get('material')->model('basic_material_combination_items');
                //删除原有半成品数据
                
                //新增半成品数据
                if (isset($data['at'])) {
                    $basicMaterialCombinationItemsObj->delete(array('pbm_id' => $filter['bm_id']));
                    foreach ($data['at'] as $k => $v) {
                        $tmpChildMaterialInfo = $basicMaterialObj->dump($k, 'material_name,material_bn');

                        $addCombinationData = array(
                            'pbm_id'            => $filter['bm_id'],
                            'bm_id'             => $k,
                            'material_num'      => $v,
                            'material_name'     => $tmpChildMaterialInfo['material_name'],
                            'material_bn'       => $tmpChildMaterialInfo['material_bn'],
                            'material_bn_crc32' => sprintf('%u', crc32($tmpChildMaterialInfo['material_bn'])),
                        );
                        $basicMaterialCombinationItemsObj->insert($addCombinationData);
                        $addCombinationData = null;
                    }
                }
            } else {
                //如果是半成品的，更新下绑定的名称信息
                $basicMaterialCombinationItemsObj = app::get('material')->model('basic_material_combination_items');
                $basicMaterialCombinationItemsObj->update(array('material_name' => $updateData['material_name']), $filter);
            }

            //删除原有的关联特性
            $basicMaterialFeatureGrpObj->delete(array('bm_id' => $filter['bm_id']));
            //保存基础物料的关联的特性
            if ($data['ftgp_id']) {
                $addBindFeatureData = array(
                    'bm_id'            => $filter['bm_id'],
                    'feature_group_id' => $data['ftgp_id'],
                );
                $basicMaterialFeatureGrpObj->insert($addBindFeatureData);
                $addBindFeatureData = null;
            }

            //保存物料扩展信息
            $updateExtData = array(
                'cost'         => $data['cost'] ? $data['cost'] : 0.00,
                'retail_price' => $data['retail_price'] ? $data['retail_price'] : 0.00,
                'weight'       => $data['weight'] ? $data['weight'] : 0.00,
                'unit'         => $data['unit'],
                'box_spec'     => isset($data['box_spec']) ? $data['box_spec'] : '',
                'net_weight'   => $data['net_weight'] ? $data['net_weight'] : 0.00, 
                'length'        => $data['length'],
                'width'         => $data['width'],
                'high'          => $data['high'],

            );

            $spec = $data['spec'];
            if($spec){
                $updateExtData['specifications'] = $spec;
            }
            if($data['brand_id']){
                $updateExtData['brand_id']     = $data['brand_id'];
            }
            if($data['type_id']){
                $updateExtData['cat_id']     = $data['type_id'];
            
            }
            $basicMaterialExtObj->update($updateExtData, $filter);
            
            //自动更新销售物料名称和金额
            if($data['is_auto_generate'] == 1){
                $sales_material_type = $data['type']=='4' ? '6' : '1';
                $sm_data = array(
                    'sales_material_name' => $data['material_name'],
                    'sales_material_bn' => $data['material_bn'],
                    'sales_material_type' => $sales_material_type,
                    'bind_bn' => $data['material_bn'],
                    'retail_price' => $data['retail_price'],
                    'tax_code'  =>$data['tax_code'],
                    'tax_name'=>$data['tax_name'],
                    'tax_rate'=>$data['tax_rate'],
                );


                $result = kernel::single('openapi_data_original_salesmaterial')->edit($sm_data, $code, $sub_msg);
            }


             //新增属性参数
            $season = $data['season'];
            $uppermatnm = $data['uppermatnm'];
            $widthnm = $data['widthnm'];
            $gendernm = $data['gendernm'];
            $subbrand = $data['subbrand'];
            $modelnm = $data['modelnm'];
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

            $customcols = kernel::single('material_customcols')->getcols();

            foreach($customcols as $cv){
             
                if(isset($data[$cv['col_key']])){
                    $props[$cv['col_key']] = $data[$cv['col_key']];
                }
            }
            if($props){
                $propsMdl = app::get('material')->model('basic_material_props');

                $propsdata = array();

                foreach($props as $pk=>$pv){

                    if($pv){
                        $propsdata = array(
                            'bm_id'         =>  $filter['bm_id'],
                            'props_col'     =>  $pk,
                            'props_value'   =>  $pv,
                        );
                        $props = $propsMdl->db_dump(array('bm_id'=>$filter['bm_id'],'props_col'=>$pk),'id');
                        if($props){
                            $propsdata['id'] = $props['id'];
                        }
                        $propsMdl->save($propsdata);
                    }
                }
            }
            //logs
            $operationLogObj = app::get('ome')->model('operation_log');
            $operationLogObj->write_log('basic_material_edit@wms', $filter['bm_id'], 'openapi编辑基础物料');
        } else {
            $result = array('msg' => '基础物料更新失败', 'rsp' => 'fail');
        }

        return $result;
    }


    public function getBrand($brand_code,$brand_name){

        $brandMdl = app::get('ome')->model('brand');
        $brand = $brandMdl->db_dump(array('brand_code'=>$brand_code),'brand_id');

        if($brand){
            return $brand['brand_id'];
        }else{

            $data = array(

                'brand_code'=>$brand_code,
                'brand_name'=>$brand_name,
            );

            $brandMdl->save($data);
            return $data['brand_id'];
        }
    }

    public function getMaterialType($type_name){

        $typeMdl = app::get('ome')->model('goods_type');
        $type_name = trim($type_name);
        $types = $typeMdl->db_dump(array('name'=>$type_name),'type_id');

        if($types){
            return $types['type_id'];
        }else{
            $data = array(

                'name'=>$type_name
            );

            $typeMdl->save($data);
            return $data['type_id'];
        }
    }
}
