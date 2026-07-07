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

class material_sales_material_import
{
    /**
     * Model对象
     */
    protected $_salesMaterialObj = null;
    protected $_basicMaterialObj = null;
    protected $_combineMdl = null;
    
    //检测导入数据有错误
    protected $_isCheckFail = false;
    protected $_isSaveFail = false;
    
    //导入的销售物料编码列表
    protected $_saleMasterBns = [];
    
    const IMPORT_TITLE = [
        '*:销售物料编码',
        '*:销售物料名称',
        '*:物料类型',
        '*:所属店铺',
        '*:包装单位',
        '*:销售物料售价',
        '*:序号',
        '*:基础物料编码/福袋组合编码',
        '*:基础物料数量',
        '*:组合价格贡献占比',
        '*:组合贡献价',
    ];
    
    /**
     * 获取ExcelTitle
     * @return mixed 返回结果
     */
    public function getExcelTitle()
    {
        return ['销售物料导入模板.xlsx',[
            self::IMPORT_TITLE,
            ['material_001','样例1：普通销售物料','普通','全部店铺','件','125','1','product_001','1','100','125'],
            ['material_002','样例2：组合销售物料','组合','全部店铺','',  '1000','1','product_001','1','20','200'],
            ['','','','','','1000','2','product_002','5','30','300'],
            ['','','','','','1000','3','product_003','5','50','500'],
            ['material_003','样例3：赠品销售物料','赠品','全部店铺','','0','1','product_002','1','100','0'],
            ['material_004','样例4：福袋组合销售物料','福袋组合','全部店铺','','1000','1','product_001','1','10','100'],
            ['','','','','','1000','2','product_002','1','30','300'],
            ['','','','','','1000','3','product_003','1','60','600'],
        ]];
    }

    /**
     * undocumented function
     * 
     * @return void
     * @author
     * */
    public function processExcelRow($import_file, $post)
    {
        $this->_salesMaterialObj = app::get('material')->model('sales_material');
        $this->_basicMaterialObj = app::get('material')->model('basic_material');
        $this->_combineMdl = app::get('material')->model('fukubukuro_combine');
        
        //按行检测数据有效性
        $this->_checkImportRow($import_file, $post);
        if($this->_isCheckFail){
            $this->_outputMsg('导入数据有报错，请检查!');
            
            return [false, '导入数据失败，请检查!'];
        }
        
        //按行创建销售物料
        $this->_saveImportRow($import_file, $post);
        if($this->_isSaveFail){
            $this->_outputMsg('保存数据有报错，请检查!');
            
            return [false, '保存数据失败，请检查!'];
        }
        
        return [true, '导入销售物料成功'];
    }
    
    /**
     * 检查导入的数据
     * 
     * @param $import_file
     * @param $post
     * @return array
     */
    public function _checkImportRow($import_file, $post)
    {
        $format = [];
        
        //读取文件
        return kernel::single('omecsv_phpoffice')->import($import_file, function ($line, $buffer, $post, $highestRow)
        {
            static $title, $salesMaterial;
            
            //title
            if ($line == 1) {
                $title = $buffer;
                
                // 验证模板是否正确
                if (array_filter($title) != self::IMPORT_TITLE) {
                    //flag
                    $this->_isCheckFail = true;
                    
                    return [false, '导入模板不正确'];
                }
                
                return [true, ''];
            }
            
            //检查导入的销售物料列信息
            if(count($buffer) < count(self::IMPORT_TITLE)) {
                //flag
                $this->_isCheckFail = true;
                
                return [false, '导入数据列不正确'];
            }
            
            //格式化销售物料列信息key=value,例如：('*:销售物料编码' => '10007008')
            $buffer = array_combine(self::IMPORT_TITLE, array_slice($buffer, 0, count(self::IMPORT_TITLE)));
            
            //check sales_material_bn
            if(in_array($buffer['*:销售物料编码'], $this->_saleMasterBns)){
                return [false, $buffer['*:销售物料编码'] .'销售物料编码重复导入'];
            }
            
            $msg = '';
            if($buffer['*:销售物料编码']) {
                if($salesMaterial['*:销售物料编码'] == $buffer['*:销售物料编码']) {
                    $salesMaterial['items'][] = $buffer;
                } else {
                    //当前销售物料与上一条不相同时,进行检查数据有效性
                    if($salesMaterial) {
                        list($rs, $rsData) = $this->_checkSalesMaterial($salesMaterial);
                        if(!$rs) {
                            //flag
                            $this->_isCheckFail = true;
                            
                            $msg .= $salesMaterial['*:销售物料编码'] .','. $rsData['msg'];
                        }
                        
                        //sales_material_bn
                        $this->_saleMasterBns[] = $salesMaterial['*:销售物料编码'];
                    }
                    
                    $salesMaterial = $buffer;
                    $salesMaterial['items'][] = $buffer;
                }
            } else {
                //组合类型：未填写销售物料编码,只需赋值基础物料信息
                if($salesMaterial) {
                    $salesMaterial['items'][] = $buffer;
                }
            }
            
            //最后一个销售物料
            if($line == $highestRow && $salesMaterial) {
                list($rs, $rsData) = $this->_checkSalesMaterial($salesMaterial);
                if(!$rs) {
                    //flag
                    $this->_isCheckFail = true;
                    
                    $msg .= $salesMaterial['*:销售物料编码'] .','. $rsData['msg'];
                }
                $salesMaterial = [];
                
                //sales_material_bn
                $this->_saleMasterBns[] = $salesMaterial['*:销售物料编码'];
            }
            
            //@todo：固定返回：true，这样有报错也会继续执行下一条记录;
            if($msg){
                return [true, $msg, 'lastrow'];
            }else{
                return [true, ''];
            }
        }, [], $format);
    }
    
    /**
     * 检查销售物料数据有效性
     * 
     * @param $salesMaterial
     * @return array
     */
    public function _checkSalesMaterial($salesMaterial)
    {
        $required = [
            '*:销售物料编码',
            '*:销售物料名称',
            '*:物料类型',
            '*:所属店铺',
            '*:基础物料编码/福袋组合编码',
            '*:基础物料数量',
        ];
        
        //check fields
        foreach ($required as $v)
        {
            if(empty($salesMaterial[$v])) {
                return [false, ['msg'=>$v.' 不能为空']];
            }
        }
        
        //获取销售物料类型名称对应关系
        $typeNames = kernel::single('material_sales_material')->getSalesMaterialTypeNames();
        
        //额外加入
        $typeNames['福袋组合'] = 7;
        
        //check sales_material_type
        if(!in_array($salesMaterial['*:物料类型'], array_keys($typeNames))) {
            return [false, ['msg'=> '物料类型仅限：'. implode('、', array_keys($typeNames))]];
        }
        
        $type_name = $salesMaterial['*:物料类型'];
        $sales_material_type = $typeNames[$type_name];
        if(empty($sales_material_type)){
            return [false, ['msg'=> '物料类型：'. $type_name .'不存在']];
        }
        
        //sales_material_type
        $salesMaterial['sales_material_type'] = $sales_material_type;
        
        //check Simple
        if(!in_array($salesMaterial['sales_material_type'], [2,3,5,7])){
            if(count($salesMaterial['items']) > 1 || array_sum(array_column($salesMaterial['items'], '*:基础物料数量')) > 1) {
                return [false, ['msg'=>$type_name. '类型，只能有一条一个基础物料']];
            }
        }
        
        //check shop
        if($salesMaterial['*:所属店铺'] == '全部店铺') {
            $shop = ['shop_id'=>'_ALL_'];
        } else {
            $shop = app::get('ome')->model('shop')->db_dump(['name'=>$salesMaterial['*:所属店铺']], 'shop_id,org_id');
            if(empty($shop)) {
                return [false, ['msg'=>'店铺不存在']];
            }
        }
        
        $salesMaterial['shop'] = $shop;
        
        //福袋组合,检查：组合价格贡献占比or组合贡献价
        if($salesMaterial['sales_material_type'] == '7') {
            $error_msg = '';
            $luckyItems = $this->_formatLuckyBagData($salesMaterial['items'], $error_msg);
            if(!$luckyItems){
                return [false, ['msg'=>$error_msg]];
            }
            
            //cover
            $salesMaterial['items'] = $luckyItems;
        }else{
            //items
            $productBns = [];
            foreach ($salesMaterial['items'] as $val)
            {
                $product_bn = $val['*:基础物料编码/福袋组合编码'];
                
                if(empty($product_bn)){
                    return [false, ['msg'=>'基础物料不能为空']];
                }
                
                //check exits
                if(isset($productBns[$product_bn]) && $productBns[$product_bn]){
                    return [false, ['msg'=>'基础物料'. $product_bn .'重复了']];
                }
                
                $productBns[$product_bn] = $product_bn;
            }
            
            //matrial
            $productList = $this->_basicMaterialObj->getList('bm_id,material_bn', ['material_bn'=>$productBns]);
            if(empty($productList)){
                return [false, ['msg'=>'基础物料'. implode('、', $productBns) .'不存在']];
            }
            $productBnList = array_column($productList, 'material_bn');
            
            //diff
            $diffBns = array_diff($productBns, $productBnList);
            if($diffBns) {
                return [false, ['msg'=>'基础物料编码'. implode('、', $diffBns) .'不存在']];
            }
        }
        
        //组合、福袋组合
        if (in_array($salesMaterial['sales_material_type'], ['2', '7'])) {
            $is_check_rate = false;
            $tmp_rate      = 0;
            foreach ($salesMaterial['items'] as $key => $val)
            {
                if ($salesMaterial['sales_material_type'] == '7' && empty($val['*:组合价格贡献占比'])) {
                    return [false, ['msg' => sprintf('%s组合价格贡献占比不能为空', $val['*:基础物料编码/福袋组合编码'])]];
                }
                
                if (!empty($val['*:组合价格贡献占比'])) {
                    if (!preg_match('/^\d+(\.\d+)?$/', $val['*:组合价格贡献占比'])) {
                        return [false, ['msg' => sprintf('%s组合价格贡献占比：%s错误，请填写数字', $val['*:基础物料编码/福袋组合编码'], $val['*:组合价格贡献占比'])]];
                    }
                    $tmp_rate += $val['*:组合价格贡献占比'];
                    
                    // 是否检测贡献占比
                    $is_check_rate = true;
                } else {
                    $salesMaterial['items'][$key]['*:组合价格贡献占比'] = 0;
                }
            }
            
            //检测贡献占比是否等于100
            if($is_check_rate){
                if ($tmp_rate > 100) {
                    return [false, ['msg' => sprintf('%s组合价格贡献百分比：%s,已超100%s', $salesMaterial['*:销售物料编码'], $tmp_rate,'%')]];
                } elseif ($tmp_rate < 100) {
                    return [false, ['msg' => sprintf('%s组合价格贡献百分比：%s,不足100%s', $salesMaterial['*:销售物料编码'], $tmp_rate,'%')]];
                }
            }
        }
        
        //check items
        $items = [];
        foreach ($salesMaterial['items'] as $val)
        {
            if(!is_numeric($val['*:基础物料数量'])){
                return [false, ['msg'=>'基础物料数量必须填写数字']];
            }
            
            if($val['*:基础物料数量'] < 1) {
                return [false, ['msg'=>'基础物料数量不能小于1']];
            }
            
            //items
            $items[] = [
                'bn' => $val['*:基础物料编码/福袋组合编码'],
                'quantity' => $val['*:基础物料数量'],
            ];
        }
        
        if(empty($items)) {
            return [false, ['msg'=>'缺少基础物料']];
        }
        
        //check exist
        $salesMaterialInfo = $this->_salesMaterialObj->db_dump(['sales_material_bn'=>$salesMaterial['*:销售物料编码']]);
        if($salesMaterialInfo) {
            return [false, ['msg'=>'已经存在,不允许重复导入']];
        }
        
        return [true, ['msg'=>'检测成功']];
    }
    
    /**
     * 保存导入的数据
     * 
     * @param $import_file
     * @param $post
     * @return void
     */
    public function _saveImportRow($import_file, $post)
    {
        $format = [];
        
        //读取文件
        return kernel::single('omecsv_phpoffice')->import($import_file, function ($line, $buffer, $post, $highestRow)
        {
            static $title, $salesMaterial;
            
            //title
            if ($line == 1) {
                $title = $buffer;
                
                // 验证模板是否正确
                if (array_filter($title) != self::IMPORT_TITLE) {
                    //flag
                    $this->_isSaveFail = true;
                    
                    return [false, '导入的模板不正确'];
                }
                
                return [true, ''];
            }
            
            //检查导入的销售物料列信息
            if(count($buffer) < count(self::IMPORT_TITLE)) {
                //flag
                $this->_isSaveFail = true;
                
                return [false, '导入的数据列不正确'];
            }
            
            //格式化销售物料列信息key=value,例如：('*:销售物料编码' => '10007008')
            $buffer = array_combine(self::IMPORT_TITLE, array_slice($buffer, 0, count(self::IMPORT_TITLE)));
            
            $msg = '';
            if($buffer['*:销售物料编码']) {
                if($salesMaterial['*:销售物料编码'] == $buffer['*:销售物料编码']) {
                    $salesMaterial['items'][] = $buffer;
                } else {
                    //当前销售物料与上一条不相同时,进行检查数据有效性
                    if($salesMaterial) {
                        list($rs, $rsData) = $this->_saveSalesMaterial($salesMaterial);
                        if(!$rs) {
                            //flag
                            $this->_isSaveFail = true;
                            
                            $msg .= $rsData['msg'];
                        }
                    }
                    
                    $salesMaterial = $buffer;
                    $salesMaterial['items'][] = $buffer;
                }
            } else {
                //组合类型：未填写销售物料编码,只需赋值基础物料信息
                if($salesMaterial) {
                    $salesMaterial['items'][] = $buffer;
                }
            }
            
            //最后一个销售物料
            if($line == $highestRow && $salesMaterial) {
                list($rs, $rsData) = $this->_saveSalesMaterial($salesMaterial);
                if(!$rs) {
                    //flag
                    $this->_isSaveFail = true;
                    
                    $msg .= $rsData['msg'];
                }
                $salesMaterial = [];
            }
            
            //return
            return [($msg ? false : true), $msg];
        }, [], $format);
    }
    
    /**
     * 保存销售物料数据
     * 
     * @param $salesMaterial
     * @return array
     */
    public function _saveSalesMaterial($salesMaterial)
    {
        $required = [
            '*:销售物料编码',
            '*:销售物料名称',
            '*:物料类型',
            '*:所属店铺',
            '*:基础物料编码/福袋组合编码',
            '*:基础物料数量',
        ];
        
        //check fields
        foreach ($required as $v)
        {
            if(empty($salesMaterial[$v])) {
                return [false, ['msg'=>$v.' 不能为空']];
            }
        }
        
        //获取销售物料类型名称对应关系
        $typeNames = kernel::single('material_sales_material')->getSalesMaterialTypeNames();
        
        //额外加入
        $typeNames['福袋组合'] = 7;
        
        //check sales_material_type
        if(!in_array($salesMaterial['*:物料类型'], array_keys($typeNames))) {
            return [false, ['msg'=> '物料类型仅限：'. implode('、', array_keys($typeNames))]];
        }
        
        $type_name = $salesMaterial['*:物料类型'];
        $sales_material_type = $typeNames[$type_name];
        if(empty($sales_material_type)){
            return [false, ['msg'=> '物料类型：'. $type_name .'不存在']];
        }
        
        //sales_material_type
        $salesMaterial['sales_material_type'] = $sales_material_type;
        
        //check Simple
        if(!in_array($salesMaterial['sales_material_type'], [2,3,5,7])){
            if(count($salesMaterial['items']) > 1 || array_sum(array_column($salesMaterial['items'], '*:基础物料数量')) > 1) {
                return [false, ['msg'=>$type_name. '类型，只能有一条一个基础物料']];
            }
        }
        
        //check shop
        if($salesMaterial['*:所属店铺'] == '全部店铺') {
            $shop = ['shop_id'=>'_ALL_'];
        } else {
            $shop = app::get('ome')->model('shop')->db_dump(['name'=>$salesMaterial['*:所属店铺']], 'shop_id,org_id');
            if(empty($shop)) {
                return [false, ['msg'=>'店铺不存在']];
            }
        }
        
        //福袋组合,检查：组合价格贡献占比or组合贡献价
        if($salesMaterial['sales_material_type'] == '7') {
            $error_msg = '';
            $luckyItems = $this->_formatLuckyBagData($salesMaterial['items'], $error_msg);
            if(!$luckyItems){
                $error_msg = $salesMaterial['*:销售物料编码'] .'：'. $error_msg;
                return [false, ['msg'=>$error_msg]];
            }
            
            //cover
            $salesMaterial['items'] = $luckyItems;
        }
        
        //组合、福袋组合
        if (in_array($salesMaterial['sales_material_type'], ['2', '7'])) {
            $is_check_rate = false;
            $tmp_rate      = 0;
            foreach ($salesMaterial['items'] as $key => $val) {
                if ($salesMaterial['sales_material_type'] == '7' && empty($val['*:组合价格贡献占比'])) {
                    return [false, ['msg' => sprintf('%s组合价格贡献占比不能为空', $val['*:基础物料编码/福袋组合编码'])]];
                }
                if (!empty($val['*:组合价格贡献占比'])) {
                    if (!preg_match('/^\d+(\.\d+)?$/', $val['*:组合价格贡献占比'])) {
                        return [false, ['msg' => sprintf('%s组合价格贡献占比：%s错误，请填写数字', $val['*:基础物料编码/福袋组合编码'], $val['*:组合价格贡献占比'])]];
                    }
                    $tmp_rate += $val['*:组合价格贡献占比'];
                    
                    // 是否检测贡献占比
                    $is_check_rate = true;
                } else {
                    $salesMaterial['items'][$key]['*:组合价格贡献占比'] = 0;
                }
            }
            
            //检测贡献占比是否等于100
            if($is_check_rate){
                if ($tmp_rate > 100) {
                    return [false, ['msg' => sprintf('%s组合价格贡献百分比：%s,已超100%s', $salesMaterial['*:销售物料编码'], $tmp_rate,'%')]];
                } elseif ($tmp_rate < 100) {
                    return [false, ['msg' => sprintf('%s组合价格贡献百分比：%s,不足100%s', $salesMaterial['*:销售物料编码'], $tmp_rate,'%')]];
                }
            }
        }
        
        $salesMaterial['shop'] = $shop;
        
        //insert
        return $this->_insert($salesMaterial);
    }
    
    /**
     * 保存数据
     * 
     * @param $salesMaterial
     * @return array
     */
    private function _insert($salesMaterial)
    {
        $salesMaterialExtObj        = app::get('material')->model('sales_material_ext');
        $salesMaterialShopFreezeObj = app::get('material')->model('sales_material_shop_freeze');
        $basicMaterialObj           = app::get('material')->model('basic_material');
        $basicMaterialExtObj        = app::get('material')->model('basic_material_ext');
        $combineItemsMdl            = app::get('material')->model('fukubukuro_combine_items');
        $saleFukuMdl                = app::get('material')->model('sales_material_fukubukuro');
        $luckybagLib                = kernel::single('material_luckybag');
        $salesBasicMaterialObj      = app::get('material')->model('sales_basic_material');
        
        $items = [];
        foreach ($salesMaterial['items'] as $v)
        {
            if($salesMaterial['sales_material_type'] == '7'){
                $fc = $this->_combineMdl->db_dump(['combine_bn'=>$v['*:基础物料编码/福袋组合编码']], 'combine_id,include_number');
                if(empty($fc)) {
                    return [false, ['msg'=>$v['*:基础物料编码/福袋组合编码'].'福袋组合编码不存在']];
                }
                
                //items
                $items[] = [
                    'combine_id' => $fc['combine_id'],
                    'number' => $fc['include_number'],
                    'rate' => $v['*:组合价格贡献占比'],
                    'rate_price' => floatval($v['*:组合贡献价']),
                ];
            }else{
                if($v['*:基础物料数量'] < 1) {
                    return [false, ['msg'=>'基础物料数量不能小于1']];
                }
                
                $bm = $basicMaterialObj->db_dump(['material_bn'=>$v['*:基础物料编码/福袋组合编码']], 'bm_id');
                if(empty($bm)) {
                    return [false, ['msg'=>$v['*:基础物料编码/福袋组合编码'].'基础物料编码不存在']];
                }
                
                //extend
                $bmExt = $basicMaterialExtObj->db_dump(['bm_id'=>$bm['bm_id']], 'cost,retail_price');
                if(empty($bmExt)){
                    $bmExt['retail_price'] = 0;
                    $bmExt['cost'] = 0;
                }
                
                // 获取价格计算方式配置
                $priceRate = $this->_getPriceRateConfig();
                $priceField = ($priceRate == 'cost') ? 'cost' : 'retail_price';
                $priceValue = isset($bmExt[$priceField]) ? $bmExt[$priceField] : 0;
                
                // 记录价格计算方式到日志
                $salesMaterial['price_rate'] = $priceRate;
                $salesMaterial['price_field'] = $priceField;
                $salesMaterial['price_value'] = $priceValue;
                
                //items
                $items[] = [
                    'bm_id' => $bm['bm_id'],
                    'quantity' => $v['*:基础物料数量'],
                    'cost' => $priceValue,
                    'amount' => $v['*:基础物料数量'] * $priceValue,
                    'rate' => floatval($v['*:组合价格贡献占比']),
                    'rate_price' => floatval($v['*:组合贡献价']),
                ];
            }
        }
        
        //check
        if(empty($items)) {
            return [false, ['msg'=>'缺少基础物料']];
        }
        
        //保存物料主表信息
        $addData = array(
            'sales_material_name'     => $salesMaterial['*:销售物料名称'],
            'sales_material_bn'       => $salesMaterial['*:销售物料编码'],
            'sales_material_bn_crc32' => crc32($salesMaterial['*:销售物料编码']),
            'sales_material_type'     => $salesMaterial['sales_material_type'],
            'shop_id'                 => $salesMaterial['shop']['shop_id'],
            'create_time'             => time(),
            'is_bind'                 => 1
        );
        $is_save = $this->_salesMaterialObj->db_save($addData);
        if ($is_save) {
            if($salesMaterial['sales_material_type'] == '7'){
                //福袋组合规则
                foreach ($items as $rateKey => $rateVal)
                {
                    $addBindData = array(
                        'sm_id' => $addData['sm_id'],
                        'combine_id' => $rateVal['combine_id'],
                        'rate_price' => $rateVal['rate_price'],//组合贡献价
                        'rate' => $rateVal['rate'], //销售价贡献占比
                    );
                    
                    $saleFukuMdl->insert($addBindData);
                }
                
                //重新保存销售物料关联的福袋组合规中的基础物料
                $cursor_id = 1;
                $error_msg = '';
                $params = array('app'=>'material', 'mdl'=>'sales_basic_material');
                $params['sdfdata'] = array('sm_id'=>$addData['sm_id']);
                $isReSave = $luckybagLib->resaveLuckySalesBmids($cursor_id, $params, $error_msg);
                if(!$isReSave && $error_msg){
                    return [false, ['msg'=>'创建基础物料关联关系失败'. $error_msg]];
                }
                
                $addBindData = $combineItemsMdl->db_dump(['combine_id'=>$addBindData['combine_id']],'bm_id');
            }else{
                $itemRateSum = array_sum(array_column($items, 'rate'));
                if($itemRateSum == 0){
                    $itemSum = array_sum(array_column($items, 'amount'));
                    $options = array (
                        'part_total'  => 100,
                        'part_field'  => 'rate',
                        'porth_field' => $itemSum > 0 ? 'amount' : 'quantity',
                    );
                    $items = kernel::single('ome_order')->calculate_part_porth($items, $options);
                }
                
                //items
                foreach ($items as $k => $v)
                {
                    $addBindData = array(
                        'sm_id'  => $addData['sm_id'],
                        'bm_id'  => $v['bm_id'],
                        'number' => $v['quantity'],
                        'rate'   => $v['rate']
                    );
                    $salesBasicMaterialObj->insert($addBindData);
                }
            }
            
            //brand
            $brandInfo = app::get('material')->model('basic_material_ext')->db_dump(['bm_id'=>$addBindData['bm_id']], 'brand_id');
            $brand_id = $brandInfo['brand_id'];
            
            //基础物料信息
            $baseMaterialInfo = $basicMaterialObj->dump(array('bm_id'=>$addBindData['bm_id']), 'cat_id');
            $cat_id = $baseMaterialInfo['cat_id'];
            
            //保存销售物料扩展信息
            $addExtData = array(
                'sm_id'        => $addData['sm_id'],
                'retail_price' => $salesMaterial['*:销售物料售价'] ?  : 0.00,
                'unit'         => (string) $salesMaterial['*:包装单位'],
                'brand_id'     => (int)$brand_id,
                'cat_id' => (int)$cat_id, //分类
            );
            $salesMaterialExtObj->insert($addExtData);
            
            //保存销售物料店铺级冻结
            if ($addData['shop_id'] != '_ALL_') {
                $addStockData = array(
                    'sm_id'       => $addData['sm_id'],
                    'shop_id'     => $addData['shop_id'],
                    'shop_freeze' => 0,
                );
                $salesMaterialShopFreezeObj->insert($addStockData);
            }
            
            //记录快照
            kernel::single('material_sales_material')->logSalesMaterialSnapshot($addData['sm_id'], '销售物料导入添加');
        }
        
        return [true, ['msg'=>'新增成功']];
    }
    
    /**
     * 格式化导入福袋类型的销售物料数据
     * 
     * @param $itemList
     * @return array or bool
     */
    public function _formatLuckyBagData($itemList, &$error_msg=null)
    {
        $item_count = count($itemList);
        
        $combineBns = [];
        $rateList = [];
        $ratePrices = [];
        $total_rate = 0;
        $total_rate_price = 0;
        foreach ($itemList as $key => $val)
        {
            $combine_bn = $val['*:基础物料编码/福袋组合编码'];
            $item_rate = $val['*:组合价格贡献占比'];
            $rate_price = floatval($val['*:组合贡献价']);
            
            //check
            if(empty($combine_bn)){
                $error_msg = '福袋组合编码不能为空';
                return false;
            }
            
            if(isset($combineBns[$combine_bn])){
                $error_msg = sprintf('%s已经存在,不能重复使用', $combine_bn, $item_rate);
                return false;
            }
            
            //combine_bn
            $combineBns[$combine_bn] = $combine_bn;
            
            //rate
            if (!empty($item_rate)) {
                //number
                if (!preg_match('/^\d+(\.\d+)?$/', $item_rate)) {
                    $error_msg = sprintf('%s组合价格贡献占比：%s错误，请填写数字', $combine_bn, $item_rate);
                    return false;
                }
                
                //total
                $total_rate += $item_rate;
                
                $rateList[] = $item_rate;
            }
            
            //rate_price
            if (!empty($rate_price)) {
                //total
                $total_rate_price += $rate_price;
                
                $ratePrices[] = $rate_price;
            }
        }
        
        //check
        $combineList = $this->_combineMdl->getList('combine_id,combine_bn,include_number,lowest_price', array('combine_bn'=>$combineBns), 0, -1);
        if(empty($combineList)) {
            $error_msg = '福袋组合编码都不存在';
            return false;
        }
        
        $combineBnList = array_column($combineList, 'combine_bn');
        
        //diff
        $diffBns = array_diff($combineBns, $combineBnList);
        if($diffBns) {
            $error_msg = '福袋组合编码'. implode('、', $diffBns) .'不存在';
            return false;
        }
        
        //rate
        $is_rate = false;
        if(count($rateList) == $item_count){
            $is_rate = true;
        }
        
        //rate_price
        $is_rate_price = false;
        if(count($ratePrices) == $item_count){
            $is_rate_price = true;
        }
        
        //check
        if(!$is_rate && !$is_rate_price){
            //场景一：组合价格贡献占比、组合贡献价,都没有填写完整;
            $error_msg = '组合价格贡献占比、组合贡献价，其中一项必须填写完整';
            return false;
        }
        
        //获取福袋组合最低售价
        $combine_sum_price = 0;
        if(!$is_rate_price){
            $lowestPrices = array_column($combineList, 'lowest_price');
            $combine_sum_price = array_sum($lowestPrices);
        }
        
        //场景二：[组合价格贡献占比]填写完整，优先使用;
        if($is_rate){
            if($total_rate > 100){
                $error_msg = '组合价格贡献占比之和，不能超过100%';
                return false;
            }elseif($total_rate < 100){
                $error_msg = '组合价格贡献占比之和，不能低于100%';
                return false;
            }
            
            //[组合贡献价]没有填写完整,
            if(!$is_rate_price){
                //重新计算贡献比
                $sum_price = $combine_sum_price;
                $line_i = 0;
                foreach ($itemList as $key => $val)
                {
                    $item_rate = $val['*:组合价格贡献占比'];
                    
                    $line_i++;
                    
                    if($line_i == $item_count){
                        $sum_price = sprintf("%.2f", $sum_price); //保留两位小数
                        $itemList[$key]['*:组合贡献价'] = $sum_price;
                    }else{
                        //rate_price
                        $rate_price = $combine_sum_price * $item_rate / 100;
                        $rate_price = sprintf("%.2f", $rate_price); //保留两位小数
                        
                        //sum_price
                        $sum_price = $sum_price - $rate_price;
                        
                        $itemList[$key]['*:组合贡献价'] = $rate_price;
                    }
                }
            }
        }else{
            //场景三：[组合价格贡献占比]没有填写完整,[组合贡献价]填写完整;
            if($total_rate_price < 0){
                $error_msg = sprintf('%s组合贡献价之和，不能小于0元', $combine_bn);
                return false;
            }elseif($total_rate_price == 0){
                $error_msg = sprintf('%s组合贡献价之和，不能等于0元', $combine_bn);
                return false;
            }
            
            //重新计算：组合价格贡献占比
            $line_i = 0;
            $sum_rate = 100;
            foreach ($itemList as $key => $val)
            {
                $rate_price = floatval($val['*:组合贡献价']);
                
                $line_i++;
                
                if($line_i == $item_count){
                    $sum_rate = sprintf("%.2f", $sum_rate); //保留两位小数
                    $itemList[$key]['*:组合价格贡献占比'] = $sum_rate;
                }else{
                    //rate
                    $rate = $rate_price / $total_rate_price * 100;
                    $rate = sprintf("%.2f", $rate); //保留两位小数
                    
                    //sum_rate
                    $sum_rate = $sum_rate - $rate;
                    
                    $itemList[$key]['*:组合价格贡献占比'] = $rate;
                }
            }
        }
        
        return $itemList;
    }
    
    /**
     * 获取价格计算方式配置
     * @return string 价格计算方式：'cost' 或 'retail_price'
     */
    private function _getPriceRateConfig()
    {
        return kernel::single('material_sales_setting')->getConfig('price_rate', 'retail_price');
    }


    
    /**
     * 页面展示提示信息
     * 
     * @param $msg
     * @param $level
     * @return void
     */
    public function _outputMsg($msg, $level='notice')
    {
        $msg = addslashes($msg);
        echo sprintf("<script>parent.$('iMsg').setText('%s');</script>", $msg);
        
        if ($level != 'notice') {
            echo <<<JS
        
        <script>
            var c = parent.$('iMsg').clone().setStyle('color','#8a1f11')
            c.inject('iMsg','after')
        </script>
JS;
        }
        
        flush();
        ob_flush();
    }
}
