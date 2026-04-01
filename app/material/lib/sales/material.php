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
 * 销售物料Lib类
 * @author xiayuanjun@shopex.cn
 * @version 1.0
 */
class material_sales_material{
    //销售物料类型列表
    static public $sales_material_type = array(
        '1' =>  array('name'=>'普通','type'=>'goods'),
        '2' =>  array('name'=>'组合','type'=>'pkg'),
        '3' =>  array('name'=>'赠品','type'=>'gift'),
        '5' =>  array('name'=>'多选一','type'=>'pko'),
        '6' =>  array('name'=>'礼盒','type'=>'giftpackage'),
        '7' =>  array('name'=>'福袋','type'=>'lkb'),
    );
    
    function __construct(){
        $this->_salesMaterialObj = app::get('material')->model('sales_material');
        $this->_salesBasicMaterialObj = app::get('material')->model('sales_basic_material');
        $this->_basicMaterialObj = app::get('material')->model('basic_material');
        $this->_basicMaterialExtObj = app::get('material')->model('basic_material_ext');
        $this->_basicMaterialStore  = app::get('ome')->model('branch_product');
        $this->db    = kernel::database();
    }
    
    /**
     * 获取销售物料类型列表
     * 
     * @return array
     */

    public function getSalesMaterialTypeList()
    {
        $tempList = self::$sales_material_type;
        
        //format
        $typeList = [];
        foreach ($tempList as $type_id => $typeVal)
        {
            $typeList[$type_id] = $typeVal;
        }
        
        return $typeList;
    }
    
    /**
     * 获取销售物料类型名称列表
     * 
     * @return array
     */
    public function getSalesMaterialTypes()
    {
        $tempList = self::$sales_material_type;
        
        //format
        $typeList = [];
        foreach ($tempList as $type_id => $typeVal)
        {
            $typeList[$type_id] = $typeVal['name'];
        }
        
        return $typeList;
    }
    
    /**
     * 获取销售物料类型名称对应关系
     * 
     * @return array
     */
    public function getSalesMaterialTypeNames()
    {
        $tempList = self::$sales_material_type;
        
        //format
        $typeList = [];
        foreach ($tempList as $type_id => $typeVal)
        {
            $type_name = $typeVal['name'];
            
            $typeList[$type_name] = $type_id;
        }
        
        return $typeList;
    }
    
    /**
     * 
     * 根据来源店铺及销售物料货号获取销售物料信息
     * @param String $shop_id
     * @param String $bn
     * @return Boolean
     */
    public function getSalesMByBn($shop_id, $bn){
        //check
        //@todo：需要过滤bn为空,天猫平台订单有商品编码为空的情况;
        if(empty($bn)){
            return false;
        }
        
        $salesMaterialInfo = $this->_salesMaterialObj->getList('sm_id,sales_material_name,sales_material_bn,shop_id,is_bind,sales_material_type',array('sales_material_bn'=>$bn,'shop_id'=>array($shop_id,'_ALL_')), 0, 1);
        if($salesMaterialInfo){
            return $salesMaterialInfo[0];
        }
        return false;
    }

    /**
     * 
     * 根据来源店铺及销售物料ID获取销售物料信息
     * @param String $shop_id
     * @param Int $sm_id
     * @return Boolean
     */
    public function getSalesMById($shop_id, $sm_id){
        $salesMaterialInfo = $this->_salesMaterialObj->getList('sm_id,sales_material_name,sales_material_bn,shop_id,is_bind,sales_material_type',array('sm_id'=>$sm_id,'shop_id'=>array($shop_id,'_ALL_')), 0, 1);
        if($salesMaterialInfo){
            return $salesMaterialInfo[0];
        }
        return false;
    }

    /**
     * 
     * 根据来源店铺及货号获取销售物料信息
     * @param String $shop_id
     * @param String $bn
     * @return Boolean
     */
    public function getBasicMBySalesMId($sm_id){
        $salesBasicMInfos = $this->_salesBasicMaterialObj->getList('*',array('sm_id'=>$sm_id), 0, -1);
        if($salesBasicMInfos){
            foreach($salesBasicMInfos as $k => $salesBasicMInfo){
                $bmIds[] = $salesBasicMInfo['bm_id'];
                $bmAndSmRates[$salesBasicMInfo['bm_id']] = $salesBasicMInfo;
            }

            $basicMaterialExtInfos = $this->_basicMaterialExtObj->getList('bm_id,cost,weight,retail_price,unit,cat_id',array('bm_id'=>$bmIds), 0, -1);
            if($basicMaterialExtInfos){
                foreach($basicMaterialExtInfos as $key => $basicMaterialExtInfo){
                    if(isset($basicMaterialExtInfo['cat_id'])) {
                        $basicMaterialExtInfo['ext_cat_id'] = $basicMaterialExtInfo['cat_id'];
                        unset($basicMaterialExtInfo['cat_id']);
                    }
                    $bmExts[$basicMaterialExtInfo['bm_id']] = $basicMaterialExtInfo;
                }
            }

            $basicMaterialInfos = $this->_basicMaterialObj->getList('bm_id,material_name,material_bn',array('bm_id'=>$bmIds), 0, -1);
            if($basicMaterialInfos){
                foreach($basicMaterialInfos as $key => $basicMaterialInfo){
                    if (empty($bmExts[$basicMaterialInfo['bm_id']])) {
                        $bmExts[$basicMaterialInfo['bm_id']] = [];
                    }
                    $bmList[] = array_merge($basicMaterialInfo, $bmAndSmRates[$basicMaterialInfo['bm_id']], $bmExts[$basicMaterialInfo['bm_id']]);
                }
                return $bmList;
            }
        }
        return false;
    }

    /**
     * 
     * 根据促销总价格计算每个物料的贡献金额值
     * @param String $shop_id
     * @param String $bn
     * @return Boolean
     */
    public function calProSaleMPriceByRate($sale_price, &$bm_bns){
        if($sale_price <=0){
            foreach($bm_bns as $k =>$bm_bn){
                $bm_bns[$k]['rate_price'] = 0.00;
            }
            return true;
        }

        $less_price = $sale_price;
        $count_sku = count($bm_bns);
        $i = 1;
        foreach($bm_bns as $k =>$bm_bn){
            if($i == $count_sku){
                $bm_bns[$k]['rate_price'] = $less_price;
            }else{
                $tmp_rate = $bm_bn['rate']/100;
                $bm_bns[$k]['rate_price'] = bcmul($sale_price, $tmp_rate, 2);

                $less_price = bcsub($less_price, $bm_bns[$k]['rate_price'], 2);
            }
            $i++;
        }

        return true;
    }

    /**
     * 可删除，没有地方调用!!!
     * 
     * @param $sm_id
     * @param $salse_num
     * @return bool
     */
    public function validSalesBasicMaterial($sm_id, $salse_num){
        $relations = $this->_salesBasicMaterialObj->getList('sm_id, bm_id, number', array('sm_id'=>$sm_id), 0, -1);
        foreach($relations as $k => $v){
            $all_nums = $salse_num * $v['number'];
            $stores = $this->_basicMaterialStore->dump(array('product_id' => $v['bm_id']));
            
            //团购导入时,可用库存 = 总库存 - 冻结库存
            $store_num    = $stores['store'] - $stores['store_freeze'];
            if($store_num - $all_nums < 0){
                return false;
            }
        }
        return true;

    }

    /**
     * 
     * 根据来源店铺及货号获取促销[捆绑]销售物料信息
     * @param String $shop_id
     * @param String $bn
     * @return Boolean
     */
    public function getBasicMBySalesMIds($sm_id, $smbc = null)
    {
        ini_set('memory_limit','64M');
        $salesBasicMInfos = ($smbc ? : $this->_salesBasicMaterialObj)->getList('bm_id,sm_id,number,rate',array('sm_id'=>$sm_id), 0, -1);
        if($salesBasicMInfos)
        {
            foreach($salesBasicMInfos as $k => $salesBasicMInfo)
            {
                $bmIds[] = $salesBasicMInfo['bm_id'];
                $bmAndSmRates[$salesBasicMInfo['sm_id']][$salesBasicMInfo['bm_id']] = $salesBasicMInfo;
            }
            
            $bmExts    = array();
            $basicMaterialExtInfos = $this->_basicMaterialExtObj->getList('bm_id,cost,weight,retail_price,unit',array('bm_id'=>$bmIds), 0, -1);
            if($basicMaterialExtInfos)
            {
                foreach($basicMaterialExtInfos as $key => $basicMaterialExtInfo)
                {
                    $bmExts[$basicMaterialExtInfo['bm_id']] = $basicMaterialExtInfo;
                }
            }
            
            $basic_material_infos    = array();
            $basicMaterialInfos = $this->_basicMaterialObj->getList('bm_id,material_name,material_bn',array('bm_id'=>$bmIds), 0, -1);
            if($basicMaterialInfos)
            {
                foreach($basicMaterialInfos as $key => $basicMaterialInfo)
                {
                    $basic_material_infos[$basicMaterialInfo['bm_id']]    = $basicMaterialInfo;
                }
            }
            
            #合并信息
            $bmList    = array();
            foreach ($bmAndSmRates as $sm_key => $sales_basic_material_list)
            {
                foreach ($sales_basic_material_list as $bm_key => $bm_item)
                {
                    $bmList[$sm_key][]    = array_merge($basic_material_infos[$bm_key], $bm_item, ($bmExts[$bm_key] ? : []));
                }
            }
            
            return $bmList;
        }
        return false;
    }

    /**
     * 
     * 根据分销总价格计算每个物料的贡献金额值
     * 
     * 
     * @return array
     */
    public function calProbuyerMPriceByRate($shop_id,$buyer_payment,$obj_bn){
        
        //检查货品是否存在销售物料中
        $salesMInfo = $this->getSalesMByBn($shop_id,$obj_bn);

        $bm_bns = array();
        if($salesMInfo){
            $basicMInfos = $this->getBasicMBySalesMId($salesMInfo['sm_id']);
            if($basicMInfos) {
                if($buyer_payment <=0){
                    foreach($basicMInfos as $bm){
                        $bm_bns[$bm['material_bn']]['rate_price'] = 0.00;
                    }

                }else{
                    $less_price = $buyer_payment;
                    $count_sku = count($basicMInfos);
                    $i = 1;
                    foreach($basicMInfos as $bm){
                        if($i == $count_sku){
                            $bm_bns[$bm['material_bn']]['rate_price'] = $less_price;
                        }else{
                            $tmp_rate = $bm['rate']/100;
                            $bm_bns[$bm['material_bn']]['rate_price'] = bcmul($buyer_payment, $tmp_rate, 2);
                            $less_price -= $bm_bns[$bm['material_bn']]['rate_price'];
                        }
                        $i++;
                    }
                }


            }
        }
        return $bm_bns;


    }

    /**
     * 
     * 根据销售物料ID数组获取对应的基础物料ID数组
     * @param array $sm_ids
     * @return Array
     */
    public function getBmIdsBySmIds($sm_ids){
        $sm_and_bms = array();
        $salesBasicMInfos = $this->_salesBasicMaterialObj->getList('bm_id,sm_id',array('sm_id'=>$sm_ids), 0, -1);
        if($salesBasicMInfos){
            foreach($salesBasicMInfos as $salesBasicMInfo){
                if(isset($sm_and_bms[$salesBasicMInfo['sm_id']])){
                    $sm_and_bms[$salesBasicMInfo['sm_id']] = array_merge((array)$sm_and_bms[$salesBasicMInfo['sm_id']], (array)$salesBasicMInfo['bm_id']);
                }else{
                    $sm_and_bms[$salesBasicMInfo['sm_id']] = $salesBasicMInfo['bm_id'];
                }
            }
            return $sm_and_bms;
        }
    }
    
    //根据销售物料sm_id获取信息福袋信息
    /**
     * 获取_luckybag_by_sm_id
     * @param mixed $sm_id ID
     * @return mixed 返回结果
     */
    public function get_luckybag_by_sm_id($sm_id){
        $mdl_ma_lu_ru = app::get('material')->model('luckybag_rules');
        $mdl_ma_ba_ma = app::get('material')->model('basic_material');
        $mdl_ma_ba_ma_ext = app::get('material')->model('basic_material_ext');
        $rs = $mdl_ma_lu_ru->getList("*",array("sm_id"=>$sm_id));
        if(empty($rs)){
            return array();
        }
        $return_arr = array();
        $load_part_number = 1;
        foreach($rs as $var_rs){
            $temp_bm_ids = explode(",",$var_rs["bm_ids"]);
            $rs_bm_info = $mdl_ma_ba_ma->getList("bm_id,material_bn,material_name",array("bm_id"=>$temp_bm_ids));
            $rs_bm_info_ext = $mdl_ma_ba_ma_ext->getList("bm_id,cost",array("bm_id"=>$temp_bm_ids));
            $rl_bm_id_ext = array();
            foreach($rs_bm_info_ext as $var_rs_bie){
                $rl_bm_id_ext[$var_rs_bie["bm_id"]]["cost"] = $var_rs_bie["cost"];
            }
            $bm_list = array();
            foreach($rs_bm_info as $var_rbi){
                $bm_list[] = array(
                    "bm_id" => $var_rbi["bm_id"],
                    "material_bn" => $var_rbi["material_bn"],
                    "material_name" => $var_rbi["material_name"],
                    "cost" => $rl_bm_id_ext[$var_rbi["bm_id"]]["cost"],
                );
            }
            $return_arr[] = array(
                "lbr_id" =>  $var_rs["lbr_id"],
                "lbr_name" =>  $var_rs["lbr_name"],
                "bm_ids" =>  $var_rs["bm_ids"],
                "sku_num" =>  $var_rs["sku_num"],
                "send_num" =>  $var_rs["send_num"],
                "price" =>  $var_rs["price"],
                "bm_list" =>  $bm_list,
                "load_part_number" => $load_part_number,
                "current_retail_price" => $var_rs["sku_num"]*$var_rs["send_num"]*$var_rs["price"],
            );
            $load_part_number++;
        }
        return $return_arr;
    }
    
    //获取订单福袋明细数据
    /**
     * 获取_order_luckybag_bminfo
     * @param mixed $sm_id ID
     * @return mixed 返回结果
     */
    public function get_order_luckybag_bminfo($sm_id){
        $mdl_ma_lu_ru = app::get('material')->model('luckybag_rules');
        $mdl_ma_ba_ma_st = app::get('material')->model('basic_material_stock');
        $lib_ma_ba_ma_st_fr  = kernel::single('material_basic_material_stock_freeze');
        $rs_luckybag_groups = $mdl_ma_lu_ru->getList("*",array("sm_id"=>$sm_id));
        $return_bm_id_info = array();
        foreach($rs_luckybag_groups as $var_rlgs){
            $current_bm_ids = explode(",", $var_rlgs["bm_ids"]);
            if(count($current_bm_ids) == $var_rlgs["sku_num"]){ //当前bm总数量和sku数量一致 不用随机 直接全部拿
                $this->get_order_luckybag_single_bminfo($current_bm_ids,$var_rlgs["lbr_id"],$var_rlgs["send_num"],$var_rlgs["price"],$return_bm_id_info);
            }else{
                //获取bm可用库存
                $current_stock_info = $mdl_ma_ba_ma_st->getList('bm_id,store,store_freeze',array('bm_id'=>$current_bm_ids));
                $current_real_store = array();
                foreach($current_stock_info as $var_csi){
                    $var_csi['store_freeze'] = $lib_ma_ba_ma_st_fr->getMaterialStockFreeze($var_csi['bm_id']);
                    $tmp_abled_store = ($var_csi['store']-$var_csi['store_freeze']) > 0 ? ($var_csi['store']-$var_csi['store_freeze']) : 0;
                    $current_real_store[$var_csi["bm_id"]] = $tmp_abled_store > 0 ? floor($tmp_abled_store/$var_rlgs["send_num"]) : 0;
                }
                //随机数组 后获取库存足和不足的两个数组
                $count_arr = count($current_real_store);
                $has_stock_bm_ids = array();
                $hasnot_stock_bm_ids = array();
                for($temp_number=1;$temp_number<=$count_arr;$temp_number++){
                    $rand_bm_id = array_rand($current_real_store);
                    if($current_real_store[$rand_bm_id] > 0){
                        $has_stock_bm_ids[] = $rand_bm_id;
                    }else{
                        $hasnot_stock_bm_ids[] = $rand_bm_id;
                    }
                    unset($current_real_store[$rand_bm_id]);
                }
                $count_has_stock_bm_ids = count($has_stock_bm_ids);
                if($count_has_stock_bm_ids == $var_rlgs["sku_num"]){ //sku数量和有库存的bm数量一样时 直接拿有库存的基础物料即可
                    $this->get_order_luckybag_single_bminfo($has_stock_bm_ids,$var_rlgs["lbr_id"],$var_rlgs["send_num"],$var_rlgs["price"],$return_bm_id_info);
                }elseif($count_has_stock_bm_ids > $var_rlgs["sku_num"]){ //有库存的bm数量大于sku数量时 直接拿相应sku数量的有库存的基础物料即可
                    $chunk_arr = array_chunk($has_stock_bm_ids,$var_rlgs["sku_num"]);
                    $result_bm_ids = array();
                    foreach($chunk_arr[0] as $var_ca){
                        $result_bm_ids[] = $var_ca;
                    }
                    $this->get_order_luckybag_single_bminfo($result_bm_ids,$var_rlgs["lbr_id"],$var_rlgs["send_num"],$var_rlgs["price"],$return_bm_id_info);
                }elseif($count_has_stock_bm_ids < $var_rlgs["sku_num"]){
                    //少的sku数量用库存不足的补上
                    $less_number = $var_rlgs["sku_num"] - $count_has_stock_bm_ids;
                    $chunk_arr = array_chunk($hasnot_stock_bm_ids,$less_number);
                    //把库存不足的补上后 获取数据
                    foreach($chunk_arr[0] as $var_ca){
                        $has_stock_bm_ids[] = $var_ca;
                    }
                    $this->get_order_luckybag_single_bminfo($has_stock_bm_ids,$var_rlgs["lbr_id"],$var_rlgs["send_num"],$var_rlgs["price"],$return_bm_id_info);
                }
            }
        }
        return $return_bm_id_info;
    }
    
    //组装福袋基础物料信息
    function get_order_luckybag_single_bminfo($bm_ids,$lbr_id,$send_num,$price,&$return_bm_id_info){
        $mdl_ma_ba_ma = app::get('material')->model('basic_material');
        $mdl_ma_ba_ma_ext = app::get('material')->model('basic_material_ext');
        $rs_ba = $mdl_ma_ba_ma->getList("bm_id,material_name,material_bn,type",array("bm_id"=>$bm_ids));
        $rs_ba_ext = $mdl_ma_ba_ma_ext->getList("bm_id,cost,weight",array("bm_id"=>$bm_ids));
        //保持bm_ids顺序获取数据
        $rl_bm_id_info_ext = array();
        foreach($rs_ba_ext as $var_be){
            $rl_bm_id_info_ext[$var_be["bm_id"]] = array(
                "weight" => $var_be["weight"],
                "cost" => $var_be["cost"],
            );
        }
        $rl_bm_id_info = array();
        foreach($rs_ba as $var_rb){
            $rl_bm_id_info[$var_rb["bm_id"]] = $var_rb;
        }
        foreach($bm_ids as $var_bi){
            $return_bm_id_info[] = array(
                "lbr_id" => $lbr_id,
                "bm_id" => $var_bi,
                "material_name" => $rl_bm_id_info[$var_bi]["material_name"],
                "material_bn" => $rl_bm_id_info[$var_bi]["material_bn"],
                "number" => $send_num, //参考原有代码用number
                "price" => $price,
                "weight" => $rl_bm_id_info_ext[$var_bi]["weight"],
                "cost" => $rl_bm_id_info_ext[$var_bi]["cost"],
                "type" => $rl_bm_id_info[$var_bi]["type"] //售后getProducts方法用了先带上
            );
        }
    }
    
    //根据销售物料sm_id获取多选一信息
    /**
     * 获取_pickone_by_sm_id
     * @param mixed $sm_id ID
     * @return mixed 返回结果
     */
    public function get_pickone_by_sm_id($sm_id){
        $mdl_ma_pickone_ru = app::get('material')->model('pickone_rules');
        $return_arr = array();
        $rs_pickone = $mdl_ma_pickone_ru->getList("*",array("sm_id"=>$sm_id));
        if(!empty($rs_pickone)){
            $mdl_ma_ba_ma = app::get('material')->model('basic_material');
            $arr_bm_ids = array();
            foreach($rs_pickone as $var_pickone){
                if(!in_array($var_pickone["bm_id"],$arr_bm_ids)){
                    $arr_bm_ids[] = $var_pickone["bm_id"];
                }
            }
            $rs_basic_material = $mdl_ma_ba_ma->getList("bm_id,material_name,material_bn",array("bm_id|in"=>$arr_bm_ids));
            $rl_bm_id_bm_info = array();
            foreach($rs_basic_material as $var_bm){
                $rl_bm_id_bm_info[$var_bm["bm_id"]] = $var_bm;
            }
            foreach($rs_pickone as $v_pickone){
                $return_arr[] = array(
                        "bm_id" => $v_pickone["bm_id"],
                        "material_name" => $rl_bm_id_bm_info[$v_pickone["bm_id"]]["material_name"],
                        "material_bn" => $rl_bm_id_bm_info[$v_pickone["bm_id"]]["material_bn"],
                        "sort" => $v_pickone["sort"],
                        "select_type" => $v_pickone["select_type"],
                );
            }
        }
        return $return_arr;
    }
    
    //组装多选一明细数据
    /**
     * 获取_order_pickone_bminfo
     * @param mixed $sm_id ID
     * @param mixed $quantity quantity
     * @param mixed $shop_id ID
     * @return mixed 返回结果
     */
    public function get_order_pickone_bminfo($sm_id,$quantity,$shop_id){
        $mdl_ma_pick_ru = app::get('material')->model('pickone_rules');
        $mdl_ma_ba_ma_st = app::get('material')->model('basic_material_stock');
        $lib_ma_ba_ma_st_fr = kernel::single('material_basic_material_stock_freeze');
        $rs_pickone = $mdl_ma_pick_ru->getList("*",array("sm_id" => $sm_id));
        if(empty($rs_pickone)){
            return array();
        }
        $arr_bm_ids = array();
        foreach($rs_pickone as $var_pko){
            $arr_bm_ids[] = $var_pko["bm_id"];
        }
        
        $mdl_ome_shop = app::get('ome')->model('shop');
        $rs_shop = $mdl_ome_shop->dump(array("shop_id"=>$shop_id),"shop_bn");
        $rl_shop_bn_branch_bns = app::get('ome')->getConf('shop.branch.relationship');
        //获取bm可用库存
        $current_real_store = array();
        if($rl_shop_bn_branch_bns[$rs_shop["shop_bn"]]){ //有供货仓库的
            $mdl_ome_branch = app::get('ome')->model('branch');
            $mdl_ome_branch_product = app::get('ome')->model('branch_product');
            $rs_branch_info = $mdl_ome_branch->getList("branch_id",array("branch_bn"=>$rl_shop_bn_branch_bns[$rs_shop["shop_bn"]]));
            $branch_ids = array();
            foreach($rs_branch_info as $var_bi){
                $branch_ids[] = $var_bi["branch_id"];
            }
            $rs_branch_product = $mdl_ome_branch_product->getList("product_id,store,store_freeze",array("branch_id"=>$branch_ids,"product_id"=>$arr_bm_ids));
            foreach($rs_branch_product as $var_bp){
                $shop_freeze = $lib_ma_ba_ma_st_fr->getShopFreezeByBmid($var_bp["product_id"]);
                $current_store = ($var_bp['store']-$var_bp['store_freeze']-$shop_freeze) > 0 ? ($var_bp['store']-$var_bp['store_freeze']-$shop_freeze) : 0;
                $current_real_store[$var_bp["product_id"]] +=  $current_store;
            }
        }else{ //拿总的
            $current_stock_info = $mdl_ma_ba_ma_st->getList('bm_id,store,store_freeze',array('bm_id'=>$arr_bm_ids));
            foreach($current_stock_info as $var_csi){
                //这里的$var_csi['store_freeze']包含仓库级预占和店铺级预占
                $current_real_store[$var_csi["bm_id"]] = ($var_csi['store']-$var_csi['store_freeze']) > 0 ? ($var_csi['store']-$var_csi['store_freeze']) : 0;
            }
        }
        $bm_ids = array();
        $rl_bm_id_number = array();
        if($rs_pickone[0]["select_type"] == "1"){ //随机
            shuffle($rs_pickone); //随机打乱数组
            foreach($rs_pickone as $var_pko_random){
                $current_bm_id = $var_pko_random["bm_id"];
                if($current_real_store[$current_bm_id] > 0){
                    if($current_real_store[$current_bm_id] >= $quantity){ //库存够了
                        $rl_bm_id_number[$current_bm_id] = $quantity;
                        $bm_ids[] = $current_bm_id;
                        $quantity = 0; //剩余的购买数量置为0
                        break; //可以跳出
                    }elseif($current_real_store[$current_bm_id] < $quantity){ //库存不足
                        $rl_bm_id_number[$current_bm_id] = $current_real_store[$current_bm_id];
                        $bm_ids[] = $current_bm_id;
                        $quantity = $quantity - $current_real_store[$current_bm_id];
                    }
                }
            }
            if(empty($bm_ids)){ //没有库存够的bm_id 直接拿第一个数组值
                $bm_ids[] = $rs_pickone[0]["bm_id"];
                $rl_bm_id_number[$rs_pickone[0]["bm_id"]] = $quantity;
            }else{ //有基础物料信息的
                if($quantity > 0){ //还有剩余数量的
                    foreach($rl_bm_id_number as $key_bm_id => &$var_number){
                        $rl_bm_id_number[$key_bm_id] = $var_number + $quantity;
                        break;
                    }
                    unset($var_number);
                }
            }
        }elseif($rs_pickone[0]["select_type"] == "2"){ //排序
            $rl_sort_arr = array(); //按sort为key 做排序
            foreach($rs_pickone as $var_pko_sort){
                $rl_sort_arr[$var_pko_sort["sort"]][] = $var_pko_sort;
            }
            ksort($rl_sort_arr);
            $arr_pickone = array(); //最终的数组
            foreach ($rl_sort_arr as $var_rsa){
                if(count($var_rsa) > 1){ //存在sort相同的值 做随机
                    shuffle($var_rsa); //随机打乱数组
                    foreach($var_rsa as $var_rsa_inner){
                        $arr_pickone[] = $var_rsa_inner;
                    }
                }else{
                    $arr_pickone[] = $var_rsa[0];
                }
            }
            foreach($arr_pickone as $var_pko_sort){
                $current_bm_id = $var_pko_sort["bm_id"];
                if($current_real_store[$current_bm_id] > 0){
                    if($current_real_store[$current_bm_id] >= $quantity){ //库存够了
                        $rl_bm_id_number[$current_bm_id] = $quantity;
                        $bm_ids[] = $current_bm_id;
                        $quantity = 0;
                        break; //可以跳出
                    }elseif($current_real_store[$current_bm_id] < $quantity){ //库存不足
                        $rl_bm_id_number[$current_bm_id] = $current_real_store[$current_bm_id];
                        $bm_ids[] = $current_bm_id;
                        $quantity = $quantity - $current_real_store[$current_bm_id];
                    }
                }
            }
            if(empty($bm_ids)){ //没有库存够的bm_id 直接拿第一个数组值
                $bm_ids[] = $arr_pickone[0]["bm_id"];
                $rl_bm_id_number[$arr_pickone[0]["bm_id"]] = $quantity;
            }else{ //有基础物料信息的
                if($quantity > 0){ //还有剩余数量的
                    foreach($rl_bm_id_number as $key_bm_id => &$var_number){
                        $rl_bm_id_number[$key_bm_id] = $var_number + $quantity;
                        break;
                    }
                    unset($var_number);
                }
            }
        }
        //统一根据bm_ids获取返回的基础物料信息
        $return_bm_id_info = array();
        $mdl_ma_ba_ma = app::get('material')->model('basic_material');
        $mdl_ma_ba_ma_ext = app::get('material')->model('basic_material_ext');
        $rs_ba = $mdl_ma_ba_ma->getList("bm_id,material_name,material_bn,type",array("bm_id"=>$bm_ids));
        $rs_ba_ext = $mdl_ma_ba_ma_ext->getList("bm_id,cost,weight",array("bm_id"=>$bm_ids));
        $rl_bm_id_info_ext = array();
        foreach($rs_ba_ext as $var_be){
            $rl_bm_id_info_ext[$var_be["bm_id"]] = array(
                    "weight" => $var_be["weight"],
                    "cost" => $var_be["cost"],
            );
        }
        $rl_bm_id_info = array();
        foreach($rs_ba as $var_rb){
            $rl_bm_id_info[$var_rb["bm_id"]] = $var_rb;
        }
        foreach($bm_ids as $var_bi){
            $return_bm_id_info[] = array(
                    "bm_id" => $var_bi,
                    "material_name" => $rl_bm_id_info[$var_bi]["material_name"],
                    "material_bn" => $rl_bm_id_info[$var_bi]["material_bn"],
                    "number" => $rl_bm_id_number[$var_bi],
                    "weight" => $rl_bm_id_info_ext[$var_bi]["weight"],
                    "cost" => $rl_bm_id_info_ext[$var_bi]["cost"],
                    "type" => $rl_bm_id_info[$var_bi]["type"] //售后getProducts方法用了先带上
            );
        }
        return $return_bm_id_info;
    }
    
    //获取指定仓库“多选一”的总库存
    /**
     * 获取_pickone_branch_store
     * @param mixed $sm_id ID
     * @param mixed $branch_id ID
     * @return mixed 返回结果
     */
    public function get_pickone_branch_store($sm_id,$branch_id){
        $libBranchProduct = kernel::single('ome_branch_product');
        $mdl_ma_pick_ru = app::get('material')->model('pickone_rules');
        $rs_pickone_ru = $mdl_ma_pick_ru->getList("bm_id",array("sm_id" => $sm_id));
        $bm_ids = array();
        foreach($rs_pickone_ru as $var_pickone){
            $bm_ids[] = $var_pickone["bm_id"];
        }
        $rs_product_store = $libBranchProduct->getAvailableStore($branch_id,$bm_ids);
        $return_store = 0;
        foreach($rs_product_store as $var_rps){
            $return_store = $return_store + $var_rps;
        }
        return $return_store;
    }
    
    //获取“多选一”销售物料对应基础物料信息
    /**
     * 获取_pickone_sm_bm
     * @param mixed $sm_ids ID
     * @return mixed 返回结果
     */
    public function get_pickone_sm_bm($sm_ids){
        $mdl_ma_pick_ru = app::get('material')->model('pickone_rules');
        $mdl_ma_ba_ma = app::get('material')->model('basic_material');
        $mdl_ma_ba_ma_ext = app::get('material')->model('basic_material_ext');
        $rs_pickone_ru = $mdl_ma_pick_ru->getList("bm_id,sm_id",array("sm_id" => $sm_ids));
        $bm_ids = array();
        foreach($rs_pickone_ru as $var_pko){
            if(!in_array($var_pko["bm_id"],$bm_ids)){
                $bm_ids[] = $var_pko["bm_id"];
            }
        }
        $rs_basic = $mdl_ma_ba_ma->getList("bm_id,material_name,material_bn",array("bm_id"=>$bm_ids));
        $rl_basic = array();
        foreach($rs_basic as $var_rb){
            $rl_basic[$var_rb["bm_id"]] = $var_rb;
        }
        $rs_basic_ext = $mdl_ma_ba_ma_ext->getList("bm_id,cost,weight,retail_price,unit",array("bm_id"=>$bm_ids));
        $rl_basic_ext = array();
        foreach($rs_basic_ext as $var_rbe){
            $rl_basic_ext[$var_rbe["bm_id"]] = array(
                    "cost" => $var_rbe["cost"],
                    "weight" => $var_rbe["weight"],
                    "retail_price" => $var_rbe["retail_price"],
                    "unit" => $var_rbe["unit"],
            );
        }
        $return_arr = array();
        foreach($rs_pickone_ru as $var_pickone_ru){
            $current_bm_info_arr = array_merge($rl_basic[$var_pickone_ru["bm_id"]],$rl_basic_ext[$var_pickone_ru["bm_id"]]);
            $return_arr[$var_pickone_ru["sm_id"]][] = $current_bm_info_arr;
        }
        return $return_arr;
    }

    /**
     * 
     * 根据促销总价格计算每个物料的贡献金额值
     * @param String $shop_id
     * @param String $bn
     * @return Boolean
     */
    public function calProPriceByRate($price, $bm_bns){
        if($price <=0){
            foreach($bm_bns as $k =>$bm_bn){
                $bm_bns[$k]['rate_price'] = 0.00;
            }
            return true;
        }

        $less_price = $price;
        $count_sku = count($bm_bns);
        $i = 1;
        $rate_bn = array();
        foreach($bm_bns as $k =>$bm_bn){
            if($i == $count_sku){
                $rate_bn[$bm_bn['material_bn']]['rate_price'] = $less_price;

            }else{
                $tmp_rate = $bm_bn['rate']/100;
                $rate_bn[$bm_bn['material_bn']]['rate_price'] = bcmul($price, $tmp_rate, 2);
               
                $less_price = bcsub($less_price, $rate_bn[$bm_bn['material_bn']]['rate_price'], 2);
            }
            $rate_bn[$bm_bn['material_bn']]['number'] = $bm_bn['number'];
            $i++;
        }

        return $rate_bn;
    }

    /**
     * 
     * 根据优惠价格计算每个物料的贡献金额值
     * @param String $shop_id
     * @param String $bn
     * @return Boolean
     */
    public function calpmtpriceByRate($pmt_price, $bm_bns){
        if($pmt_price <=0){
            foreach($bm_bns as $k =>$bm_bn){
                $bm_bns[$k]['rate_price'] = 0.00;
            }
            return true;
        }

        $less_price = $pmt_price;
        $count_sku = count($bm_bns);
        $i = 1;
        $rate_bn = array();
        foreach($bm_bns as $k =>$bm_bn){
            if($i == $count_sku){
                $rate_bn[$bm_bn['material_bn']]['rate_price'] = $less_price;

            }else{
                $tmp_rate = $bm_bn['rate']/100;
                $rate_bn[$bm_bn['material_bn']]['rate_price'] = bcmul($pmt_price, $tmp_rate, 2);
               
                $less_price = bcsub($less_price,  $rate_bn[$bm_bn['material_bn']]['rate_price'], 2);
            }
            $rate_bn[$bm_bn['material_bn']]['number'] = $bm_bn['number'];
            $i++;
        }

        return $rate_bn;
    }
    
    /**
     * 获取BranchSalesList
     * @param mixed $filter filter
     * @return mixed 返回结果
     */
    public function getBranchSalesList($filter) {
        $sm_id = '('.implode(',',$filter['sm_id']).')';
        $branch_id = '('.implode(',',$filter['branch_id']).')';;
        $is_bind = $filter['is_bind'];
        $sql = 'select smsm.sm_id,smsm.sales_material_name,smsm.sales_material_bn,smsm.sales_material_type,sob.branch_id,smbm.bm_id
from sdb_material_sales_material smsm
left join sdb_material_sales_basic_material smsbm on smsm.sm_id = smsbm.sm_id
left join sdb_material_basic_material smbm on smbm.bm_id = smsbm.bm_id
left join sdb_ome_branch_product sobp on sobp.product_id = smbm.bm_id
left join sdb_ome_branch sob on sob.branch_id = sobp.branch_id
where smsm.sm_id in '.$sm_id.' and smsm.is_bind = '.$is_bind.'
and sobp.branch_id in '.$branch_id.' 
group by smsm.sm_id, sob.name';
        
        return $this->db->select($sql);
    }

    #获取销售物料在仓库的库存
    /**
     * 获取SmBranchStock
     * @param mixed $filter filter
     * @return mixed 返回结果
     */
    public function getSmBranchStock($filter) {
        $smRows = $this->_salesMaterialObj->getList('sm_id,sales_material_bn', $filter);
        if(empty($smRows)) {
            return [];
        }
        $smRows = array_column($smRows, null, 'sm_id');
        $smBc = $this->_salesBasicMaterialObj->getList('sm_id, bm_id, number', array('sm_id'=>array_keys($smRows)));
        $smBmNumber = array();
        foreach ($smBc as $v) {
            $smBmNumber[$v['sm_id']][$v['bm_id']] = $v['number'];
        }
        $bbs = $this->_basicMaterialStore->getList('branch_id, product_id as bm_id, store, store_freeze', ['product_id'=>array_column($smBc, 'bm_id')]);
        $bmBranchNum = [];
        foreach ($bbs as $v) {
            $bmBranchNum[$v['bm_id']][$v['branch_id']] = $v;
        }
        $return = [];
        foreach ($smBmNumber as $sm_id => $bmNumber) {
            $smBranchStock = [];
            $branchBm =[];
            foreach ($bmNumber as $bm_id => $number) {
                foreach ($bmBranchNum[$bm_id] as $branch_id => $bbsRow) {
                    $branchBm[$branch_id][$bm_id] = $bm_id;
                    $smStore = floor($bbsRow['store']/$number);
                    $smStoreFreeze = floor($bbsRow['store_freeze']/$number);
                    $smNum = $smStore - $smStoreFreeze;
                    if(!isset($smBranchStock[$branch_id]) || $smBranchStock[$branch_id]['valid_stock'] > $smNum) {
                        $smBranchStock[$branch_id]['store'] = $smStore;
                        $smBranchStock[$branch_id]['store_freeze'] = $smStoreFreeze;
                        $smBranchStock[$branch_id]['valid_stock'] = $smNum;
                    }
                }
            }
            foreach ($smBranchStock as $branch_id => $v) {
                if(count($bmNumber) == count($branchBm[$branch_id])) {
                    $return[] = [
                        'sm_id'=>$sm_id, 
                        'sales_material_bn'=>$smRows[$sm_id]['sales_material_bn'],
                        'branch_id' => $branch_id,
                        'store' => $v['store'],
                        'store_freeze' => $v['store_freeze'],
                        'valid_stock' => $v['valid_stock'],
                        'bm_ids' => $branchBm[$branch_id]
                    ];
                }
            }
        }
        return $return;
    }
    
    /**
     * 根据总优惠金额计算每个物料的优惠金额值
     * 
     * @param String $shop_id
     * @param String $bn
     * @return Boolean
     */
    public function calProPmtPriceByRate($pmt_price, &$bm_bns)
    {
        if($pmt_price <=0){
            foreach($bm_bns as $k => $bm_bn){
                $bm_bns[$k]['pmt_price'] = 0.00;
            }
            
            return true;
        }
        
        $less_price = $pmt_price;
        $count_sku = count($bm_bns);
        $i = 1;
        foreach($bm_bns as $k => $bm_bn)
        {
            if($i == $count_sku){
                $bm_bns[$k]['pmt_price'] = $less_price;
            }else{
                $tmp_rate = $bm_bn['rate'] / 100;
                
                $bm_bns[$k]['pmt_price'] = bcmul($pmt_price, $tmp_rate, 2);
                
                $less_price -= $bm_bns[$k]['pmt_price'];
            }
            
            $i++;
        }
        
        return true;
    }
    
    /**
     * 根据销售物料sm_id获取福袋组合
     * 
     * @param $sm_id
     * @return array
     */
    public function get_fukubukuro_by_sm_id($sm_id)
    {
        $saleFukuMdl = app::get('material')->model('sales_material_fukubukuro');
        $combineMdl = app::get('material')->model('fukubukuro_combine');
        
        //items
        $itemList = $saleFukuMdl->getList('*', array('sm_id'=>$sm_id), 0, -1);
        if(empty($itemList)){
            return array();
        }
        
        $combineIds = array_column($itemList, 'combine_id');
        $combineList = $combineMdl->getList('*', array('combine_id'=>$combineIds), 0, -1);
        if(empty($combineList)){
            return array();
        }
        $combineList = array_column($combineList, null, 'combine_id');
        
        //format
        foreach ($itemList as $itemKey => $tempVal)
        {
            $combine_id = $tempVal['combine_id'];
            
            //check
            if(!isset($combineList[$combine_id])){
                continue;
            }
            
            $itemList[$itemKey]['combine_bn'] = $combineList[$combine_id]['combine_bn'];
            $itemList[$itemKey]['combine_name'] = $combineList[$combine_id]['combine_name'];
            
            //最低价~最高价
            if($combineList[$combine_id]['lowest_price'] == $combineList[$combine_id]['highest_price']){
                $itemList[$itemKey]['combine_price'] = $combineList[$combine_id]['lowest_price'];
            }else{
                $itemList[$itemKey]['combine_price'] = $combineList[$combine_id]['lowest_price'] .' ~ '. $combineList[$combine_id]['highest_price'];
            }
            
            //最低价
            $itemList[$itemKey]['lowest_price'] = $combineList[$combine_id]['lowest_price'];
        }
        
        return $itemList;
    }
    
    /**
     * 根据销售物料sm_id获取福袋组合及组合关联的基础物料
     * @param $smId
     * @return array
     * @date 2024-12-31 4:36 下午
     */
    public function get_fukubukuro_combine_by_bm_id($smId)
    {
        $combineList = $this->get_fukubukuro_by_sm_id($smId);
        if (empty($combineList)) {
            return [];
        }
        
        $combineIds = array_column($combineList, 'combine_id');
        $combineListIndexed = array_column($combineList, null, 'combine_id');
        
        $combineItemModel = app::get('material')->model('fukubukuro_combine_items');
        $itemList = $combineItemModel->getList('item_id,combine_id,bm_id', ['combine_id' => $combineIds]);
        if (empty($itemList)) {
            return [];
        }
        
        $bmIds = array_column($itemList, 'bm_id');
        $basicMaterialList = $this->_basicMaterialObj->getList('bm_id,material_bn,material_name',['bm_id'=>$bmIds]);
        $basicMaterialListIndexed = array_column($basicMaterialList, null, 'bm_id');
        
        $newItemList = [];
        foreach ($itemList as $item) {
            // 获取基础物料信息
            $basicMaterialInfo = $basicMaterialListIndexed[$item['bm_id']] ?? [];
            // 获取福袋组合信息
            $combineInfo = $combineListIndexed[$item['combine_id']] ?? [];
        
            $combineInfo['material_bn']   = $basicMaterialInfo['material_bn'] ?? '';
            $combineInfo['material_name'] = $basicMaterialInfo['material_name'] ?? '';
        
            $newItemList[] = $combineInfo;
        }
        return $newItemList;
    }

    /**
     * 记录销售物料快照
     * 
     * @param int $sm_id 销售物料ID
     * @param string $action 操作类型
     */
    public function logSalesMaterialSnapshot($sm_id, $action)
    {
        $salesMaterialObj = app::get('material')->model('sales_material');
        $salesMaterialExtObj = app::get('material')->model('sales_material_ext');
        
        //获取销售物料基础数据
        $logSmData = $salesMaterialObj->dump(array('sm_id' => $sm_id), '*');
        $logSmExtInfo = $salesMaterialExtObj->dump(array('sm_id' => $sm_id), '*');
        if ($logSmExtInfo) {
            $logSmData = array_merge($logSmData, $logSmExtInfo);
        }
        
        //根据物料类型获取关联数据
        if ($logSmData["sales_material_type"] == "5") {
            //多选一
            $logSmData["pickone_rules_list"] = $this->get_pickone_by_sm_id($sm_id);
        } elseif ($logSmData["sales_material_type"] == '7') {
            //福袋组合规则
            $logSmData['fukubukuro_rules_list'] = $this->get_fukubukuro_by_sm_id($sm_id);
        } else {
            $bmInfoList = $this->getBasicMBySalesMId($sm_id);
            if ($bmInfoList) {
                $logSmData['bm_list'] = $bmInfoList;
            }
        }

        //获取价格配置信息
        $priceConfig = kernel::single('material_sales_setting')->getPriceConfig();

        //添加操作信息和价格配置
        $logSmData = array_merge($logSmData, array(
            'action' => $action,
            'price_rate' => $priceConfig['price_rate'],
            'price_field' => $priceConfig['price_field'],
            'price_label' => $priceConfig['price_label']
        ));

        //根据操作类型确定日志类型
        $logType = 'sales_material_add@wms';
        if (strpos($action, '编辑') !== false) {
            $logType = 'sales_material_edit@wms';
        }
        
        //写入操作日志
        $operationLogObj = app::get('ome')->model('operation_log');
        $operationLogObj->write_log($logType, $sm_id, serialize($logSmData));
    }
    
    /**
     * 更新销售物料
     * 
     * @param int $sm_id 销售物料ID
     * @param array $updateData 更新数据
     * @param string $source 数据来源
     * @return bool
     */
    public function updateSalesMaterial($sm_id, $updateData, $source = '')
    {
        if (empty($sm_id) || empty($updateData)) {
            return false;
        }
        
        // 获取销售物料信息
        $salesMaterialInfo = $this->_salesMaterialObj->dump(array('sm_id' => $sm_id), '*');
        if (!$salesMaterialInfo) {
            return false;
        }
        
        // 定义允许更新的字段
        $allowedFields = array('tax_rate', 'tax_name', 'tax_code', 'visibled');
        
        // 检查字段是否有变化，只更新有变化的字段
        $filteredUpdateData = array();
        foreach ($allowedFields as $field) {
            if (isset($updateData[$field])) {
                // 比较新值与数据库中的值
                $newValue = $updateData[$field];
                $oldValue = isset($salesMaterialInfo[$field]) ? $salesMaterialInfo[$field] : null;
                
                // 如果值有变化，则加入更新数据
                if ($newValue != $oldValue) {
                    $filteredUpdateData[$field] = $newValue;
                }
            }
        }
        
        // 如果没有字段发生变化，返回成功（无需更新）
        if (empty($filteredUpdateData)) {
            return true;
        }
        
        // 添加更新时间
        $filteredUpdateData['last_modify'] = time();
        
        // 执行更新
        $result = $this->_salesMaterialObj->update($filteredUpdateData, array('sm_id' => $sm_id));
        
        if ($result) {
            // 记录操作日志
            $this->logSalesMaterialSnapshot($sm_id, $source . '更新销售物料');
        }
        
        return $result ? true : false;
    }
    
}
