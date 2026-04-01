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
 * 销售物料模型层
 *
 * @author kamisama.xia@gmail.com 
 * @version 0.1
 */

class material_mdl_sales_material extends dbeav_model{
    //是否有导出配置
    var $has_export_cnf = true;
    //导出的文件名
    var $export_name = '销售物料';

    var $defaultOrder = array('sm_id',' DESC');

    static public $__shopLists = array();


    /**
     * 销售物料类型字段格式化
     * @param string $row 物料类型字段
     * @return string
     */
    function modifier_sales_material_type($row){
        if($row == '1'){
            return '普通';
        }elseif($row == '2'){
            return '组合';
        }elseif($row == '3'){
            return '赠品';
        }elseif($row == '4'){
            return '福袋已禁用';
        }elseif($row == '5'){
            return '多选一';
        }elseif($row == '6'){
            return '礼盒';
        }elseif($row == '7'){
            return '福袋';
        }else{
            return '-';
        }
    }
    
    /**
     * 搜索项
     *
     * @return array
     */
    public function searchOptions()
    {
        $parentOptions = parent::searchOptions();
        
        $childOptions = array(
            'fuzzy_sales_material_bn'=>app::get('ome')->_('销售物料编码(模糊)'),
            'pz_basic_material_bn'=>app::get('ome')->_('基础物料编码(普通/组合)'),
        );
        
        return $Options = array_merge($parentOptions,$childOptions);
    }
    
    /**
     * 物料是否可见字段格式化
     * @param string $row 物料是否可见字段
     * @return string
     */
    function modifier_shop_id($row){

        if(empty(self::$__shopLists)){
            self::$__shopLists['_ALL_'] = '全部店铺';
            $shopObj = app::get('ome')->model('shop');
            $tmp_shops = $shopObj->getList('shop_id,name',array(),0,-1);
            foreach($tmp_shops as $k => $shop){
                self::$__shopLists[$shop['shop_id']] = $shop['name'];
            }
        }
        
        return isset(self::$__shopLists[$row]) ? self::$__shopLists[$row] : $row;
    }

    //导出字段配置 移除不需要的字段
    public function disabled_export_cols(&$cols){
        unset($cols['column_edit']);
    }

    /**
     * 基础物料列表项扩展字段
     */
    function extra_cols(){
        return array(
            // 'column_retail_price' => array('label'=>'售价','width'=>'60','func_suffix'=>'retail_price'),
            // 'column_unit' => array('label'=>'包装单位','width'=>'60','func_suffix'=>'unit'),
//             'column_associated_material' => array('label'=>'关联基础物料信息','width'=>'300','func_suffix'=>'associated_material'),
//            'column_branch' => array('label'=>'仓库','width'=>'300','func_suffix'=>'branch'),
//            'column_store' => array('label'=>'库存','width'=>'300','func_suffix'=>'store'),
        );
    }

    /**
     * 售价扩展字段格式化
     */
    function extra_retail_price($rows){
        return kernel::single('material_extracolumn_salesmaterial_retailprice')->process($rows);
    }

    /**
     * 包装单位扩展字段格式化
     */
    function extra_unit($rows){
        return kernel::single('material_extracolumn_salesmaterial_unit')->process($rows);
    }

    /**
     * 关联的基础物料信息
     */
    function extra_associated_material($rows){
        return kernel::single('material_extracolumn_salesmaterial_associatedmaterial')->process($rows);
    }
    
    function extra_branch($rows){
        return kernel::single('material_extracolumn_salesmaterial_branch')->process($rows);
    }
    
    function extra_store($rows){
        return kernel::single('material_extracolumn_salesmaterial_store')->process($rows);
    }
    
    /**
    * 销售物料的过滤方法
    */
    public function _filter($filter,$tableAlias=null,$baseWhere=null){
        $where = '';
        
        if (isset($filter['not_gift']) && $filter['not_gift'] == 1){
            $where .= '  AND sales_material_type IN (1,2)';
            unset($filter['not_gift']);
        }

        #对应店铺
        if(isset($filter['in_shop_id']) && !empty($filter['in_shop_id']))
        {
            if(!is_array($filter['in_shop_id']))
            {
                $filter['in_shop_id']    = explode(',', $filter['in_shop_id']);
            }
            foreach ($filter['in_shop_id'] as $key => $val)
            {
                if(empty($val)) unset($filter['in_shop_id'][$key]);
            }
            
            $where    .= " AND shop_id IN ('". implode("','", $filter['in_shop_id']) ."') ";
            unset($filter['in_shop_id']);
        }

        if($filter['slaes_material_type'] == 12){
            $where .= " AND sales_material_type IN (1,2)";
        }
        if($filter['pz_basic_material_bn']){
            $bmId = app::get('material')->model('basic_material')->db_dump(['material_bn'=>$filter['pz_basic_material_bn']], 'bm_id')['bm_id'];
            if($bmId) {
                $smId = kernel::single('material_basic_material')->getSmIdsBmIds([$bmId]);
                $smId[] = -1;
                $where .= ' AND sm_id in("'.implode('","', $smId).'")';
            } else {
                $where .= ' AND sm_id = -1';
            }
            unset($filter['pz_basic_material_bn']);
        }
        if($filter['brand_id']) {
            $salesExt = app::get('material')->model('sales_material_ext')->getList('sm_id', ['brand_id'=>$filter['brand_id']]);
            $smId = array_column($salesExt, 'sm_id');
            $smId[] = -1;
            $where .= ' AND sm_id in("'.implode('","', $smId).'")';
            unset($filter['brand_id']);
        }
        if($filter['sales_material_bn'] && is_string($filter['sales_material_bn']) && strpos($filter['sales_material_bn'], "\n") !== false){
            $filter['sales_material_bn'] = array_unique(array_map('trim', array_filter(explode("\n", $filter['sales_material_bn']))));
        }
        
        //模糊搜索：销售物料编码
        if($filter['fuzzy_sales_material_bn']){
            // $filter['fuzzy_sales_material_bn'] = str_replace(["\r", "\n", '"', "'", '“', '”', '‘', '’', "\t"], '', $filter['fuzzy_sales_material_bn']);
            
            // $where .= " AND sales_material_bn LIKE '%" . $filter['fuzzy_sales_material_bn'] . "%' ";
            if (strpos($filter['fuzzy_sales_material_bn'], "\n") !== false) {
                $filter['fuzzy_sales_material_bn'] = array_unique(array_map('trim', array_filter(explode("\n", $filter['fuzzy_sales_material_bn']))));
            } else {
                $filter['fuzzy_sales_material_bn'] = [
                    trim($filter['fuzzy_sales_material_bn'])
                ];
            }
            $where .= ' AND sales_material_bn REGEXP ' . kernel::database()->quote(implode('|', $filter['fuzzy_sales_material_bn'])) . ' ';

            unset($filter['fuzzy_sales_material_bn']);
        }
        
        return parent::_filter($filter,$tableAlias,$baseWhere).$where;
    }

    /**
     * 导入模板的标题
     *
     * @param Null
     * @return Array
     */
    function exportTemplate($filter){
        return $this->io_title($filter);
    }

    private $templateColumn = array(
        '*:销售物料名称' => 'sales_material_name',
        '*:销售物料编码' => 'sales_material_bn',
        '*:物料类型' => 'sales_material_type',#普通、组合（暂时不支持）、 赠品 (只支持全部店铺)
        '*:所属店铺' => 'shop_id',
        '*:品牌' => 'brand_id',
        '*:物料成本价' => 'cost',
        '*:物料零售价' => 'retail_price',
        '*:重量(g)' => 'weight',
        '*:包装单位' => 'unit',
        
        '*:关联基础物料信息(组合类型多个以|竖线分隔)' => 'material_bn',
        #普通的填写基础物料编号(组合类型格式：物料编号1:数量1|物料编号2:数量2)
        #(福袋类型格式：组合名称：物料编码1|物料编码2-sku数量|送件数量|单品售价)
        #(多选一类型格式：选择方式名称#物料编码1:排序值|物料编码2:排序值)
    );
    /**
     * 导入导出的标题
     *
     * @param Null
     * @return Array
     */
    function io_title( $filter, $ioType='csv' ){
        switch( $filter )
        {
            case 'salesMaterial':
                $this->oSchema['csv'][$filter] = $this->templateColumn;
                break;
        }
        
        $this->ioTitle[$ioType][$filter] = array_keys( $this->oSchema[$ioType][$filter] );
        return $this->ioTitle[$ioType][$filter];
    }

    /**
     * 准备导入的参数定义
     *
     * @param Null
     * @return Null
     */
    function prepared_import_csv(){
        $this->ioObj->cacheTime = time();
        $this->import_data = array();
        $this->material_bn_list = array();
    }
    
    /**
     * 准备导入的数据主体内容部分检查和处理
     *
     * @param Array $data
     * @param Boolean $mark
     * @param String $tmpl
     * @param String $msg
     * @return Null
     */
    function prepared_import_csv_obj($data,$mark,$tmpl,&$msg = ''){
        return null;
    }
    
    /**
     * 准备导入的数据明细内容部分检查和处理
     *
     * @param Array $row
     * @param String $title
     * @param String $tmpl
     * @param Boolean $mark
     * @param Boolean $newObjFlag
     * @param String $msg
     * @return Null
     */
    function prepared_import_csv_row($row,&$title,&$tmpl,&$mark,&$newObjFlag,&$msg)
    {
        $basicMaterialObj    = app::get('material')->model('basic_material');
        $salesMaterialObj    = app::get('material')->model('sales_material');
        $shopObj             = app::get('ome')->model('shop');
        $checkSalesLib    = kernel::single('material_sales_check');#数据检验有效性Lib类
        if( $row[0] == '*:销售物料名称' ){
            $this->nums = 1;
            $title = array_flip($row);
            foreach($this->templateColumn as $k => $val) {
                if(!isset($title[$k])) {
                    $msg['error'] = '请使用正确的模板';
                    return false;
                }
            }
            return false;
        }
        $this->nums++;
        if (empty($title)) {
            $msg['error'] = "请使用正确的模板格式！";
            return false;
        }
        $arrRequired = ['sales_material_name', 'sales_material_bn', 'sales_material_type', 'shop_id'];
        $arrData = array();
        foreach($this->templateColumn as $k => $val) {
            $arrData[$val] = trim($row[$title[$k]]);
            if(in_array($val, $arrRequired) && empty($arrData[$val])) {
                $msg['error'] = 'Line '.$this->nums.'：'.$k.'不能都为空！';
                return false;
            }
        }
        if($this->nums > 10000){
            $msg['error'] = "导入的数量量过大，请减少到10000行以下！";
            return false;
        }
        if(in_array($arrData['sales_material_bn'], $this->material_bn_list))
        {
            $msg['error'] = 'Line '.$this->nums.'：物料编号【'. $arrData['sales_material_bn'] .'】重复！';
            return false;
        }
        $this->material_bn_list[]    = $arrData['sales_material_bn'];
        //销售物料类型(注：暂时只支持普通或赠品类型)
        if($arrData['sales_material_type'] == "普通"){
            $arrData['sales_material_type'] = 1;
        }elseif($arrData['sales_material_type'] =="组合"){
            $arrData['sales_material_type'] = 2;
        }elseif($arrData['sales_material_type'] =="赠品"){
            $arrData['sales_material_type'] = 3;
        }elseif($arrData['sales_material_type'] == "福袋"){
            $arrData['sales_material_type'] = 4;
        }elseif($arrData['sales_material_type'] == "多选一"){
            $arrData['sales_material_type'] = 5;
        }elseif($arrData['sales_material_type'] == '礼盒'){
            $arrData['sales_material_type'] = 6;
        }else{
            $msg['error'] = "销售物料类型填写的内容错误：".$arrData['sales_material_type']."，请填写普通、组合、赠品、多选一";
            return false;
        }
        
        //所属店铺
        if($arrData['shop_id'] == '全部店铺'){
            $arrData['shop_id']    = '_ALL_';
        }elseif(($arrData['sales_material_type'] == 3) && ($arrData['shop_id'] != '全部店铺')){
            $msg['error']    = "赠品物料类型只能选择全部店铺,销售物料编码：". $arrData['sales_material_type'];
            return false;
        }elseif($arrData['shop_id']){
            $shopRow    = $shopObj->dump(array('name'=>$arrData['shop_id'], 's_type'=>1), 'shop_id');
            if(empty($shopRow))
            {
                $msg['error']    = "未找到填写的所属店铺,销售物料编码：". $arrData['sales_material_type'];
                return false;
            }
            
            $arrData['shop_id']    = $shopRow['shop_id'];
        }else{
            $msg['error']    = "所属店铺不能为空,销售物料编码：". $arrData['sales_material_type'];
            return false;
        }
        $arrBmId = [];
        #数据检查
        if(($arrData['sales_material_type'] == 2)){ //组合物料类型(至少关联一个基础物料)
            $tmp_at = array();
            //关联基础物料
            if($arrData['material_bn'] == ''){
                $msg['error']    = "组合类型销售物料请至少填写一个关联的基础物料编号,销售物料编码：". $arrData['sales_material_type'];
                return false;
            }

            $tmp_salesMInfos    = explode('|', $arrData['material_bn']);
            foreach($tmp_salesMInfos as $tmp_basicMInfo){
                $tmp_bnInfo    = explode(':', $tmp_basicMInfo);#格式：物料编号1:数量1:组合价格贡献占比|物料编号2:数量2:组合价格贡献占比

                if (!isset($tmp_bnInfo[2])) {
                    $tmp_bnInfo[2] = 0;
                    $auto_calculation_percentage = true; #没有组合价格贡献占比参数，需要自动计算组合价格贡献占比
                }
                $tmp_binds[$tmp_bnInfo[0]]    = array(intval($tmp_bnInfo[1]), intval($tmp_bnInfo[2]));
            }
            unset($arrData['material_bn']);

            #只有一种物料时，数量必须大于1
            foreach ($tmp_binds as $bn => $val){
                if((count($tmp_binds) == 1) && ($val[0] < 2)){
                    $msg['error']    = "销售物料编码：". $arrData['sales_material_type'] ."行中,只有一种基础物料：". $bn ."时数量必须大于1（例格式：物料编号1:数量2）";
                    return false;
                }
                if ($val[0] < 1){
                    $msg['error']    = "销售物料编码：". $arrData['sales_material_type'] ."行中,基础物料：". $bn ."的数量必须大于0（例格式：物料编号1:数量1|物料编号2:数量2）";
                    return false;
                }
            }

            //组合价格贡献占比 累加起来为100
            /*if (array_sum(array_column($tmp_binds, 1)) != 100) {
                $msg['error']    = "销售物料编码：". $arrData['sales_material_type'] ."行中,基础物料：". $bn ."的组合价格贡献占比累加起来必须等于100（例格式：物料编号1:数量1:组合价格贡献占比1|物料编号2:数量2:组合价格贡献占比2）";
                return false;
            }*/

            $arrData['material_bn']    = $tmp_binds;
            foreach($arrData['material_bn'] as $bn => $val){
                $basicInfo    = $basicMaterialObj->getList('bm_id', array('material_bn'=>$bn), 0, 1);
                if(!$basicInfo){
                    $msg['error']    = "销售物料编码：". $arrData['sales_material_type'] ."行中找不到关联的基础物料：". $bn ."（例格式：物料编号1:数量1|物料编号2:数量2）";
                    return false;
                }else{
                    $tmp_at[$basicInfo[0]['bm_id']]    = $val;
                }
                $arrBmId[] = $basicInfo[0]['bm_id'];
            }

            $sum_cost = 0;
            $material_rows = array();
            if ($auto_calculation_percentage) { #需要自动计算组合价格百分比

                #基础物料信息
                $material_rows = $this->getBasicMaterial(array_keys($tmp_at));
                if (array_sum(array_column($material_rows, 'cost')) == 0) {#基础物料成本价都为0
                    $msg['error']    = "销售物料编码：". $arrData['sales_material_type'] ."行中,基础物料的成本价都为0且没有填写组合价格贡献占比,请调整基础物料成本价或者填写组合价格贡献占比并累加起来必须等于100（例格式：物料编号1:数量1:组合价格贡献占比1|物料编号2:数量2:组合价格贡献占比2）";
                    return false;
                }

                foreach ($tmp_at as $bm_id=> $tmp_at_item) { //计算总成本价多少
                    $cost = $material_rows[$bm_id]['cost'];
                    $number = $tmp_at_item[0];
                    $sum_cost += $cost * $number;
                }
            }


            #组合价格贡献占比
            $arrData['material_bn'] = array();
            $tmp_pr = array();
            $total = 100;
            $count_basicM = count($tmp_at);
            $count_i = 1;
            foreach ($tmp_at as $key => $val){
                if($count_basicM == 1){
                    #只有一种物料时
                    $arrData['material_bn'][$key]['number']    = $val[0];
                    $arrData['material_bn'][$key]['rate']      = $total;
                    #验证数据时使用
                    $tmp_pr[$key]    = $arrData['material_bn'][$key]['rate'];
                }else{
                    #多种物料时
                    /*$arrData['material_bn'][$key]['number']    = $val;
                    $tmp_rate                  = intval($total / $count_basicM);
                    $arrData['material_bn'][$key]['rate']      = $tmp_rate;
                    if($count_basicM == $count_i){
                        $arrData['material_bn'][$key]['rate']      = $total - ($tmp_rate * $count_basicM) + $tmp_rate;
                    }
                    #验证数据时使用
                    $tmp_pr[$key]    = $arrData['material_bn'][$key]['rate'];
                    $count_i++;*/

                    if ($auto_calculation_percentage) {#自动计算组合价格百分比
                        $number = $val[0];
                        $cost = $material_rows[$key]['cost'];

                        $rate = $count_basicM == $count_i
                            ? 100 - array_sum($tmp_pr)
                            : round(($number * $cost) / $sum_cost * 100);

                        $arrData['material_bn'][$key]['number']  = $number;
                        $arrData['material_bn'][$key]['rate']    = $rate;
                        #验证数据时使用
                        $tmp_pr[$key] = $rate;
                        $count_i++;
                    } else {
                        $arrData['material_bn'][$key]['number']    = $val[0];
                        $arrData['material_bn'][$key]['rate']      = $val[1];
                        $tmp_pr[$key] = $val[1];
                    }
                }
            }
        }elseif($arrData['sales_material_type'] == 4){ //福袋类型
            $csv_data_str = trim($arrData['material_bn']);
            $tmp_lbr = array();
            //福袋基础物料组
            if(!$csv_data_str){
                $msg['error'] = "福袋类型销售物料请至少填写一个关联的福袋组合规则,销售物料编码：". $arrData['sales_material_type'];
                return false;
            }
            $luckybag_example_str = "，销售物料编码：".$arrData['sales_material_type']."。参考格式（组合名称A：物料编码1|物料编码2-sku数量|送件数量|单品售价#组合名称B：物料编码1|物料编码3-sku数量|送件数量|单品售价）。";
            $tmp_luckybag_rules = explode('#', $csv_data_str);
            foreach($tmp_luckybag_rules as $var_tlr){
                $temp_lbr = array();
                $tmp_luckybag_name = explode(':', $var_tlr);
                if(count($tmp_luckybag_name) == 2){
                    if(!$tmp_luckybag_name[0]){
                        $msg['error'] = "福袋组合名称异常".$luckybag_example_str; break;
                    }
                    if(!$tmp_luckybag_name[1]){
                        $msg['error'] = "福袋组合内容异常".$luckybag_example_str; break;
                    }
                    $temp_lbr["name"] = $tmp_luckybag_name[0];
                    $tmp_luckybag_detail = explode('-', $tmp_luckybag_name[1]);
                    if(count($tmp_luckybag_detail) == 2){
                        if(!$tmp_luckybag_detail[0]){
                            $msg['error'] = $temp_lbr["name"]."的福袋组合基础物料异常".$luckybag_example_str; break;
                        }
                        if(!$tmp_luckybag_detail[1]){
                            $msg['error'] = $temp_lbr["name"]."的福袋组合规则参数异常".$luckybag_example_str; break;
                        }
                        $luckybag_bm_bns = explode("|", $tmp_luckybag_detail[0]);
                        $rs_bm_ids = $basicMaterialObj->getList("bm_id",array("material_bn"=>$luckybag_bm_bns));
                        if(empty($rs_bm_ids)){
                            $msg['error'] = $temp_lbr["name"]."的福袋组合基础物料不存在".$luckybag_example_str; break;
                        }
                        if(count($rs_bm_ids) < count($luckybag_bm_bns)){
                            $msg['error'] = $temp_lbr["name"]."的福袋组合基础物料编码重复或不存在".$luckybag_example_str; break;
                        }
                        foreach($rs_bm_ids as $var_bi){
                            $temp_lbr["bm_ids"][] = $var_bi["bm_id"];
                            $arrBmId[] = $var_bi["bm_id"];
                        }
                        $luckybag_params = explode("|", $tmp_luckybag_detail[1]);
                        if(count($luckybag_params) != 3){
                            $msg['error'] = $temp_lbr["name"]."的福袋组合参数异常".$luckybag_example_str; break;
                        }
                        $temp_lbr["sku_num"] = $luckybag_params[0];
                        $temp_lbr["send_num"] = $luckybag_params[1];
                        $temp_lbr["single_price"] = $luckybag_params[2];
                    }else{
                        $msg['error'] = $temp_lbr["name"]."格式错误，".$luckybag_example_str; break;
                    }
                }else{
                    $msg['error'] = "格式错误，".$luckybag_example_str; break;
                }
                $tmp_lbr[] = $temp_lbr;
            }
            if($msg['error']){
                return false;
            }
        }elseif($arrData['sales_material_type'] == 5){ //多选一类型
            $csv_data_str = trim($arrData['material_bn']);
            //福袋基础物料组
            if(!$csv_data_str){
                $msg['error'] = "多选一类型销售物料请至少填写一个关联的基础物料规则,销售物料编码：". $arrData['sales_material_type']; return false;
            }
            $tmp_sort = array();
            $pickone_example_str = "，销售物料编码：".$arrData['sales_material_type']."。参考格式（随机#sku01:0|sku02:0 或 排序#sku01:10|sku02:20）。";
            $tmp_pickone_rules = explode('#', $csv_data_str);
            $count_pickone_data = count($tmp_pickone_rules);
            if($count_pickone_data <= 1 || $count_pickone_data > 2){
                $msg['error'] = "多选一关联基础物料信息异常".$pickone_example_str; return false;
            }
            if($tmp_pickone_rules[0] == "随机"){
                $pickone_select_type = 1;
            }elseif($tmp_pickone_rules[0] == "排序"){
                $pickone_select_type = 2;
            }else{
                $msg['error'] = "多选一关联基础物料选择方式信息异常".$pickone_example_str; return false;
            }
            $tmp_pickone_items = explode('|', $tmp_pickone_rules[1]);
            $arr_bm_id = array();
            foreach($tmp_pickone_items as $var_p_i){
                $current_pickone_items = explode(':',$var_p_i);
                if(count($current_pickone_items) != 2){
                    $msg['error'] = "多选一关联基础物料明细信息异常".$pickone_example_str; break;
                }
                $rs_basic = $basicMaterialObj->dump(array("material_bn"=>$current_pickone_items[0]),"bm_id");
                if(empty($rs_basic)){
                    $msg['error'] = "基础物料编码".$current_pickone_items[0]."不存在".$pickone_example_str; break;
                }
                if(in_array($rs_basic["bm_id"],$arr_bm_id)){
                    $msg['error'] = "基础物料编码".$current_pickone_items[0]."重复了".$pickone_example_str; break;
                }
                $arr_bm_id[] = $rs_basic["bm_id"];
                $arrBmId[] = $rs_basic["bm_id"];
                $tmp_sort[$rs_basic["bm_id"]] = $current_pickone_items[1] ? $current_pickone_items[1] : 0;
            }
            if($msg['error']){
                return false;
            }
        }else{ #普通、赠品物料类型(默认关联的基础物料可以为空)
            if($arrData['material_bn']){
                $basic_filter = array('material_bn'=>$arrData['material_bn']);
                if ($arrData['sales_material_type'] ==6){
                    $basic_filter['type'] = 4;
                }
                $basicInfo    = $basicMaterialObj->getList('bm_id', $basic_filter, 0, 1);
                if(!$basicInfo){
                    $msg['error']    = "销售物料编码：". $arrData['sales_material_type'] ."行中找不到关联的基础物料：". $arrData['material_bn'];
                    return false;
                }else{
                    $arrData['material_bn']    = $basicInfo[0]['bm_id'];
                    $arrBmId[]    = $basicInfo[0]['bm_id'];
                }
            }
        }
        
        #销售物料扩展信息
        $arrData['cost']    = floatval($arrData['cost']);
        $arrData['retail_price']    = floatval($arrData['retail_price']);
        $arrData['weight']    = intval($arrData['weight']);
        $arrData['unit']    = trim($arrData['unit']);
        if($arrData['brand_id']) {
            $brandInfo = app::get('ome')->model('brand')->db_dump(['brand_name'=>$arrData['brand_id']], 'brand_id');
            if(empty($brandInfo)) {
                $msg['error'] = 'Line '.$this->nums.'：品牌【'. $arrData['brand_id'] .'】不存在！';
                return false;
            }
            $basicBrandInfo = app::get('material')->model('basic_material_ext')->getList('brand_id', ['bm_id'=>$arrBmId]);
            $basicBrandInfo = array_column($basicBrandInfo, 'brand_id');
            if(!in_array($brandInfo['brand_id'], $basicBrandInfo)) {
                $msg['error'] = 'Line '.$this->nums.'：品牌【'. $arrData['brand_id'] .'】填写错误，品牌名称与子商品品牌不一致！';
                return false;
            }
            $arrData['brand_id'] = $brandInfo['brand_id'];
        }
        #赠品物料类型_成本价和售价必须等于0
        if($arrData['sales_material_type'] == 3){
            if(($arrData['cost'] != 0) || ($arrData['retail_price'] != 0)){
                $msg['error']    = "赠品物料类型成本价和售价必须等于0,销售物料编码：". $arrData['sales_material_type'];
                return false;
            }
        }
        
        #拼接数据
        $sdf = array(
                    'sales_material_name' => $arrData['sales_material_name'],
                    'sales_material_bn' => trim($arrData['sales_material_bn']),
                    'sales_material_type' => $arrData['sales_material_type'],
                    'shop_id' => $arrData['shop_id'],
                    'is_bind' => 2,
                    'bind_bm_id' => $arrData['material_bn'],
                    'at' => $tmp_at,
                    'pr' => $tmp_pr,
                    'lbr' => $tmp_lbr,
                    'sort' => $tmp_sort,
                    'pickone_select_type' => $pickone_select_type,
                    'sales_material_bn_crc32' => '',
                    'cost' => $arrData['cost'],
                    'retail_price' => $arrData['retail_price'],
                    'weight' => $arrData['weight'],
                    'unit' => $arrData['unit'],
                    'brand_id' => $arrData['brand_id'],
        );
        #数据有效性检查
        $err_msg = '';
        if(!$checkSalesLib->checkParams($sdf, $err_msg)){
            $msg['error']    = $err_msg .",物料编码:". $arrData['sales_material_type'];
            return false;
        }
        $this->import_data[] = $sdf;
        return null;
    }

    /**
     * 完成基础物料的导入
     *
     * @param Null
     * @return Null
     */
    function finish_import_csv(){

        $oQueue = app::get('base')->model('queue');
        $aP = $this->import_data;
        $pSdf = array();

        $count = 0;
        $limit = 50;
        $page = 0;
        $orderSdfs = array();

        foreach ($aP as $k => $aPi){
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
                'queue_title'=>'销售物料导入',
                'start_time'=>time(),
                'params'=>array(
                    'sdfdata'=> $v,
                    'app' => 'material',
                    'mdl' => 'sales_material'
                ),
                'worker'=>'material_sales_material_to_import.run',
            );
            $oQueue->save($queueData);
//             $cursor_id = "111"; //测试用 直接吊队列run方法
//             kernel::single('material_sales_material_to_import')->run($cursor_id,$queueData["params"]);
        }
        $oQueue->flush();
        
        //记录日志
        $operationLogObj    = app::get('ome')->model('operation_log');
        $operationLogObj->write_log('sales_material_import@wms', 0, "批量导入销售物料,本次共导入". count($aP['salesm']['contents']) ."条记录!");
        
        return null;
    }

    /**
     * 获取基础物料信息
     * @param $bm_ids
     * @return array
     */
    private function getBasicMaterial($bm_ids)
    {
        $basic_material_ext_model = app::get('material')->model('basic_material_ext');

        $rows = $basic_material_ext_model->getList('bm_id,cost,retail_price', array('bm_id|in'=> $bm_ids), 0, -1);

        $material_rows = array();

        foreach ($rows as $row) {
            $material_rows[$row['bm_id']] = array(
                'cost'=> $row['cost'],
                'retail_price'=> $row['retail_price'],
            );
        }

        return $material_rows;
    }

    //根据配置字段获取导出的标题行
    public function getExportTitle($fields){
        $title = array();
        $finderObj = kernel::single('material_finder_material_sales');
        $main_columns = $this->get_schema();
        foreach( explode(',', $fields) as $k => $col ){

            if(isset($main_columns['columns'][$col])){
                $title[] = "*:".$main_columns['columns'][$col]['label'];
                
            } elseif ('column_' == substr($col, 0, 7) && method_exists($finderObj, $col)){
                $title[] = "*:" . ($finderObj->$col ? $finderObj->$col : substr($col,7));
            }
        }
        return mb_convert_encoding(implode(',',$title), 'GBK', 'UTF-8');
    }

    public function getExportDataByCustom($fields, $filter, $has_detail, $curr_sheet, $start, $end, $op_id)
    {
        
        //根据选择的字段定义导出的第一行标题
        if ($curr_sheet == 1) {
            $data['content']['main'][] = $this->getExportTitle($fields).mb_convert_encoding(',基础物料编码,基础物料名称,数量,组合价格贡献占比,固定价,福袋组合编码', 'GBK', 'UTF-8');
        }
        
        if (!$list = $this->getList('*', $filter)) {
            return false;
        }
        
        $salesMLib = kernel::single('material_sales_material');
        $rl_arr_select_type = array("1"=>"随机","2"=>"排序");
        foreach ($list as $salesMInfo) {
            $salesMInfo = array_map(function ($val) {
                return str_replace(',', '，', $val);
            }, $salesMInfo);
            $listRow = $salesMInfo;
            $basicMInfos = array();
            if ($salesMInfo['sales_material_type'] == 5) {
                //多选一
                $basicMInfos = $salesMLib->get_pickone_by_sm_id($salesMInfo['sm_id']);
            }elseif ($salesMInfo['sales_material_type'] == 7) {
                //福袋
                $basicMInfos = $salesMLib->get_fukubukuro_combine_by_bm_id($salesMInfo['sm_id']);
            } else {
                $basicMInfos = $salesMLib->getBasicMBySalesMId($salesMInfo['sm_id']);
            }
            $listRow['create_time'] = $salesMInfo['create_time'] ? date('Y-m-d H:i:s',$salesMInfo['create_time']) : '';
            $listRow['last_modify'] = $salesMInfo['last_modify'] ? date('Y-m-d H:i:s',$salesMInfo['last_modify']) : '';
            $listRow['sales_material_type'] = $this->modifier_sales_material_type($salesMInfo['sales_material_type']);
            $listRow['visibled'] = $salesMInfo['visibled']=='0' ? '停售' : '在售';
            $listRow['shop_id'] = $this->modifier_shop_id($salesMInfo['shop_id']);

            $finderObj = kernel::single('material_finder_material_sales');
            $exptmp_data = array();
            $main_columns = $this->get_schema();
            foreach (explode(',', $fields) as $key => $col) {
                if(!isset($main_columns['columns'][$col])){
                    if ('column_' == substr($col, 0, 7) && method_exists($finderObj, $col)) {
                        $listRow[$col] = $finderObj->$col($listRow, $list);
                    } else {
                        continue;
                    }
                }
                if (isset($listRow[$col])) {
                    $listRow[$col] = mb_convert_encoding($listRow[$col], 'GBK', 'UTF-8');
                    $exptmp_data[] = $listRow[$col];
                } else {
                    $exptmp_data[] = '';
                }
            }
            foreach ($basicMInfos as $basicMInfo) {
                $tmp = $exptmp_data;
                if ($salesMInfo['sales_material_type'] == 5) {
                    //多选一
                    
                    $tmp[] = $basicMInfo['material_bn'];
                    $tmp[] = isset($basicMInfo['material_name']) ? mb_convert_encoding($basicMInfo['material_name'], 'GBK', 'UTF-8') : '';
                    $tmp[] = mb_convert_encoding($rl_arr_select_type[$basicMInfo['select_type']], 'GBK', 'UTF-8');
                    $tmp[] = isset($basicMInfo['rate']) ? $basicMInfo['rate'] : '';
                    $tmp[] = isset($basicMInfo['fixed_price']) ? $basicMInfo['fixed_price'] : '';
                    $tmp[] = '';
                }elseif ($salesMInfo['sales_material_type'] == 7) {
                    //福袋
                    
                    $tmp[] = $basicMInfo['material_bn'];
                    $tmp[] = isset($basicMInfo['material_name']) ? mb_convert_encoding($basicMInfo['material_name'], 'GBK', 'UTF-8') : '';
                    $tmp[] = $basicMInfo['number'];
                    $tmp[] = isset($basicMInfo['rate']) ? $basicMInfo['rate'] : '';
                    $tmp[] = isset($basicMInfo['fixed_price']) ? $basicMInfo['fixed_price'] : '';
                    $tmp[] = $basicMInfo['combine_bn'];
                } else {
                    
                    $tmp[] = $basicMInfo['material_bn'];
                    $tmp[] = isset($basicMInfo['material_name']) ? mb_convert_encoding($basicMInfo['material_name'], 'GBK', 'UTF-8') : '';
                    $tmp[] = $basicMInfo['number'];
                    $tmp[] = isset($basicMInfo['rate']) ? $basicMInfo['rate'] : '';
                    $tmp[] = isset($basicMInfo['fixed_price']) ? $basicMInfo['fixed_price'] : '';
                    $tmp[] = '';
                }
                $data['content']['main'][] = implode(',', $tmp);
            }
        }
        
        return $data;
    }
}
