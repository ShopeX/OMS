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
 * 产品线列表
 * @author wangjianjun@shopex.cn
 * @version 2024.04.12
 */
class dealer_ctl_admin_series extends desktop_controller{

    /**
     * 产品线列表项方法
     * @param Post
     * @return String
     */
    public function index(){
        $actions = array(
            array(
                'label' => '创建产品线',
                'href' => 'index.php?app=dealer&ctl=admin_series&act=series_add',
            ),
            [
                'label'  => '产品线导入模板',
                'href' => 'index.php?app=dealer&ctl=admin_series&act=exportSeriesTemplate',
                'target' => '_blank',
            ],
            [
                'label'  => '物料导入模板',
                'href' => 'index.php?app=dealer&ctl=admin_series_products&act=exportSeriesProductsTemplate',
                'target' => '_blank',
            ],
            [
                'label'  => '导入产品线',
                'href' => 'index.php?app=omecsv&ctl=admin_import&act=main&ctler=dealer_mdl_series&add='.$this->app->app_id,
                'target' => 'dialog::{width:500,height:250,title:\'' . app::get('desktop')->_('导入产品线') . '\'}',
            ],
        );
        $params = array(
            'title'=>'产品线设置',
            'use_buildin_set_tag' => false,
            'use_buildin_filter' => false,
            'use_buildin_export' => false,
            'use_buildin_filter' => true,
            'use_buildin_recycle' => false,
            'actions' => $actions,
        );
        $this->finder('dealer_mdl_series',$params);

        $html = <<<EOF
        <script>
              $$(".show_list").addEvent('click',function(e){
                  var series_id = this.get('series_id');
                  var t_url ='index.php?app=dealer&ctl=admin_series_products&act=findseriesProducts&series_id='+series_id;
              var url='index.php?app=desktop&act=alertpages&goto='+encodeURIComponent(t_url);
        Ex_Loader('modedialog',function() {
            new finderDialog(url,{width:1000,height:660,

            });
        });
              });

        </script>
EOF;
        echo $html;exit;
    }
    
    /**
     * 新增展示页
     * @param void
     * @return void
     */
    public function series_add(){
        $mdl_dealer_betc = app::get('dealer')->model('betc');
        $dealer_betc_list = $mdl_dealer_betc->getList('betc_id,betc_name');
        $this->pagedata['betcs']      = $dealer_betc_list;
        $this->pagedata['finder_vid'] = $_GET['finder_vid'];
        $this->page('admin/series/series_add.html');
    }
    
    /**
     * 产品线新增提交方法
     * @param Post
     * @return Boolean
     */
    public function toAdd(){
        $this->begin('index.php?app=dealer&ctl=admin_series&act=index');
        // 定义kernel model等
        $lib_ome_func = kernel::single('ome_func');
        $mdl_ool = app::get('ome')->model('operation_log');
        $mdl_dealer_series = app::get('dealer')->model('series');
        $lib_dealer_series = kernel::single("dealer_series");

        $error_msg = "";
        $check_result = $lib_dealer_series->check_series_data($_POST,$error_msg);
        if(!$check_result){
            $this->end(false,$error_msg);
        }

        //再验证是否存在（产品线编码维度）
        $rs_series = $mdl_dealer_series->dump(array("series_code"=>$_POST["series_code"]),"series_id");
        if(!empty($rs_series)){
            $this->end(false,"产品线编码已存在。");
        }
        //获取贸易公司的组织架构ID
        $mdl_dealer_betc = app::get('dealer')->model('betc');
        $rs_dealer_betc = $mdl_dealer_betc->dump(array("betc_id"=>$_POST["betc_id"]),"cos_id");
        if(!$rs_dealer_betc["cos_id"]){
            $this->end(false,"缺少组织架构ID。");
        }
        // 获取操作人信息
        $opinfo = $lib_ome_func->getDesktopUser();
        // 产品线保存
        $insert_arr = array(
            "series_code" => $_POST["series_code"], //产品线编码
            "series_name" => $_POST["series_name"], //产品线名称
            "description" => $_POST["description"], //产品线描述
            "cat_name" => $_POST["cat_name"], //产品线分类
            "sku_nums" => 0, //绑定基础物料数量
            "betc_id" => $_POST["betc_id"], //贸易公司ID
            "cos_id" => $rs_dealer_betc["cos_id"], //组织架构ID
            "remark" => $_POST["remark"], //备注
            "op_name" => $opinfo["op_name"], //创建人
        );
        $res = $mdl_dealer_series->insert($insert_arr); 
        if (!$res) {
            $this->end(false, '保存失败');
        }
        $mdl_ool->write_log('dealer_series_add@dealer',$insert_arr['series_id'],"新增产品线。");
        $this->end(true, '操作成功');
    }
    
    /**
     * AJax加载选择基础物料模板
     * @param $series_id 产品线主键ID
     * @return void
     */
    public function ajax_basic_material_html($series_id = ''){
        if($series_id){
            $mdl_dealer_series_products = app::get('dealer')->model('series_products');
            $rs_dealer_series_products = $mdl_dealer_series_products->getList("bm_id",array("series_id"=>$series_id));
            $bm_ids = array();
            foreach($rs_dealer_series_products as $var_rdsp){
                $bm_ids[] = $var_rdsp["bm_id"];
            }
            $count = is_array($bm_ids) ? count($bm_ids) : 0;
            $data['bind_bm_ids'] = $bm_ids;
            $sign = '基础物料';
            $func = 'product_selected_show';
            $domid = 'hand-selected-product';
            $this->pagedata['replacehtml'] = <<<EOF
<div id='{$domid}'>已选择了{$count}{$sign},<a href='javascript:void(0);' onclick='{$func}();'>查看选中{$sign}.</a></div>
EOF;
        }
        if (isset($_POST['betc_id']) && $_POST['betc_id']) {
            $shopBnArr = [0];
            $betcMdl  = app::get('dealer')->model('betc');
            $cosMdl   = app::get('organization')->model('cos');
            $betcInfo = $betcMdl->db_dump(['betc_id' => $_POST['betc_id']]);
            $cosInfo  = $cosMdl->db_dump(['cos_id'=>$betcInfo['cos_id']]);
            $bbuCosId = $cosInfo['parent_id'];
            $data['filter']  = implode('&', [
                'cos_id='.$bbuCosId,
            ]);
        }
        $this->pagedata['data'] = $data;
        $this->display('admin/series/select_basic_material.html');
    }
    
    /**
     * AJax加载选择经销店铺模板
     * @param $series_id 产品线主键ID
     * @return void
     */
    public function ajax_shopyjdf_html($series_id = ''){
        $data = [];
        if ($series_id) {
            //经销店铺
            $mdl_dealer_series_endorse = app::get('dealer')->model('series_endorse');
            $rs_dealer_series_endorse = $mdl_dealer_series_endorse->getList("shop_id",array("series_id"=>$series_id));
            $shop_ids = array();
            foreach($rs_dealer_series_endorse as $var_rdse){
                $shop_ids[] = $var_rdse["shop_id"];
            }
            $count_shop = is_array($shop_ids) ? count($shop_ids) : 0;
            $data['bind_shop_ids']  = $shop_ids;
            $data['filter'] = 'delivery_mode = shopyjdf';
            $sign = '经销店铺';
            $func = 'shop_selected_show';
            $domid = 'hand-selected-shop';
            $count = $count_shop;
            $this->pagedata['replacehtml'] = <<<EOF
<div id='{$domid}' style='float:left;margin:10px 10px 10px 5px'>已选择了{$count}{$sign},<a href='javascript:void(0);' onclick='{$func}();'>查看选中的{$sign}.</a></div>
EOF;
        }
        if (isset($_POST['betc_id']) && $_POST['betc_id']) {
            $shopBnArr = [0];
            $betcMdl = app::get('dealer')->model('betc');
            $betcInfo = $betcMdl->db_dump(['betc_id' => $_POST['betc_id']]);
            $cosList = kernel::single('organization_cos')->getCosList($betcInfo['cos_id']);
            foreach ($cosList[1] as $k => $v) {
                if ($v['cos_type'] == 'shop') {
                    $shopBnArr[] = $v['cos_code'];
                }
            }
            $data['filter']  = implode('&', [
                'delivery_mode=shopyjdf',
                'shop_bn_in=("'.implode('","', $shopBnArr).'")',
            ]);
        }
        $this->pagedata['data'] = $data;
        $this->display('admin/series/select_shopyjdf.html');
    }
    
    /**
     * 显示基础物料明细
     * @param void
     * @return void
     */
    public function showProducts(){
        $basicMaterialObj = app::get('material')->model('basic_material');
        $bm_id = kernel::single('base_component_request')->get_post('bm_id');
        if ($bm_id) {
            if (!is_array($bm_id)) {
                $bm_id= explode(',', $bm_id);
            }
            $this->pagedata['_input'] = array(
                'name'     => 'bm_id',
                'idcol'    => 'bm_id',
                '_textcol' => 'material_name',
                '_bncol' => 'material_bn',
            );
            $list = $basicMaterialObj->getList('bm_id,material_name,material_bn', array('bm_id' => $bm_id), 0, -1, 'bm_id asc');
            $this->pagedata['_input']['items'] = $list;
        }
        $this->display('admin/series/show_products.html');
    }
    
    /**
     * 显示经销店铺明细
     * @param void
     * @return void
     */
    public function showShops(){
        $mdl_ome_shop = app::get('ome')->model('shop');
        $shop_id = kernel::single('base_component_request')->get_post('shop_id');
        if ($shop_id) {
            if (!is_array($shop_id)) {
                $shop_id = explode(',', $shop_id);
            }
            $this->pagedata['_input'] = array(
                'name' => 'shop_id',
                'idcol' => 'shop_id',
                '_textcol' => 'name',
                '_bncol' => 'shop_bn',
            );
            $list = $mdl_ome_shop->getList('shop_id,name,shop_bn', array('shop_id' => $shop_id), 0, -1, 'shop_id asc');
            $this->pagedata['_input']['items'] = $list;
        }
        $this->display('admin/series/show_shops.html');
    }
    
    /**
     * 产品线编辑的展示页面方法
     * @param Int $series_id 产品线
     * @param String $part 编辑哪个部分
     * @return Boolean
     */
    public function edit($series_id, $part = 'all'){
        if(!$series_id){
            die("产品线主键ID缺失。");
        }
        $mdl_dealer_series = app::get("dealer")->model("series");
        $rs_dealer_series = $mdl_dealer_series->dump(array("series_id"=>$series_id),"*");
        if(empty($rs_dealer_series)){
            die("产品线数据为空。");
        }
        //产品线数据
        $this->pagedata["series_info"] = $rs_dealer_series; 
        //贸易公司数据
        $mdl_dealer_betc = app::get('dealer')->model('betc');
        $dealer_betc_list = $mdl_dealer_betc->getList('betc_id,betc_name');
        $this->pagedata['betcs'] = $dealer_betc_list;
        if ($part == 'material') {
            // 获取当前账户下公司业务组织的cos_id
            $bbuCosList = kernel::single('organization_cos')->getBbuFromCosId();
            if (!$bbuCosList[0]) {
                $this->pagedata['bm_filter'] = 'cos_id=0';
            } elseif ($bbuCosList[1] != '_ALL_') {
                $bbu_cos_ids = array_column($bbuCosList[1], 'cos_id');
                $this->pagedata['bm_filter'] = 'cos_id=' . ($bbu_cos_ids ? implode(',', $bbu_cos_ids) : '0');
            } else {
                $this->pagedata['bm_filter'] = '1=1';
            }

            //基础物料
            $bm_ids = [];
            $mdl_dealer_series_products = app::get('dealer')->model('series_products');
            $rs_dealer_series_products = $mdl_dealer_series_products->getList("bm_id",array("series_id"=>$series_id));
            if($rs_dealer_series_products){
                // die("产品线绑物料数据为空");
                $bm_ids = array_column($rs_dealer_series_products, 'bm_id');
            }
            $count = is_array($bm_ids) ? count($bm_ids) : 0;
            $this->pagedata['bind_bm_ids']  = $bm_ids;
            $this->pagedata['replacehtml'] = <<<EOF
    <div id='hand-selected-product' style='float:left;margin:10px 10px 10px 5px'>已选择了{$count}个基础物料,<a href='javascript:void(0);' onclick='product_selected_show();'>查看选中的基础物料.</a></div>
    EOF;
        }
    //     if ($part == 'shop') {
    //         //经销店铺
    //         $shop_ids = [];
    //         $mdl_dealer_series_endorse = app::get('dealer')->model('series_endorse');
    //         $rs_dealer_series_endorse = $mdl_dealer_series_endorse->getList("shop_id",array("series_id"=>$series_id));
    //         if(empty($rs_dealer_series_endorse)){
    //             $shop_ids = array_column($rs_dealer_series_endorse, 'shop_id');
    //         }
    //         $count_shop = is_array($shop_ids) ? count($shop_ids) : 0;
    //         $this->pagedata['bind_shop_ids']  = $shop_ids;
    //         $this->pagedata['replacehtml_shop'] = <<<EOF
    //         <div id='hand-selected-shop' style='float:left;margin:10px 10px 10px 5px'>已选择了{$count_shop}个经销店铺,<a href='javascript:void(0);' onclick='shop_selected_show();'>查看选中的经销店铺.</a></div>
    // EOF;
    //     }
        if ($part == 'shop') { // 授权店铺
            $this->singlepage('admin/series/edit_shop.html');
        } elseif ($part == 'material') { // 添加物料
            $this->singlepage('admin/series/edit_material.html');
        } else { // 编辑产品线信息
            $this->singlepage('admin/series/edit.html');
        }
    }
    
    /**
     * 销售物料编辑提交方法
     * @param Int $series_id
     * @return Boolean
     */
    public function toEdit(){
        $this->begin('index.php?app=dealer&ctl=admin_series&act=index');
        if(!$_POST["series_id"]){
            $this->end(false,'产品线主键ID缺失。');
        }
        $lib_ome_func = kernel::single('ome_func');
        $mdl_ool = app::get('ome')->model('operation_log');
        $lib_dealer_series = kernel::single("dealer_series");
        $shopMdl  = app::get('ome')->model('shop');
        $mdl_dealer_series = app::get('dealer')->model('series');
        $mdl_dealer_series_products = app::get('dealer')->model('series_products');
        $mdl_dealer_series_endorse = app::get('dealer')->model('series_endorse');
        $mdl_dealer_series_endorse_products = app::get('dealer')->model('series_endorse_products');
        //先验证一遍值
        $error_msg = "";
        $check_result = $lib_dealer_series->check_series_data($_POST,$error_msg);
        if(!$check_result){
            $this->end(false,$error_msg);
        }
        // 重新获取贸易公司，防止多人同事操作，比如：一人先编辑授权店铺单还未保存，一人后编辑贸易公司并保存，这样前一个人的贸易公司还是老的，对应的经销店铺也是老的所属贸易公司的
        $filter_arr = array("series_id"=>$_POST["series_id"]);
        $seriesInfo = $mdl_dealer_series->db_dump($filter_arr);
        $_POST['betc_id'] = $seriesInfo['betc_id'];

        //获取贸易公司的组织架构ID
        $mdl_dealer_betc = app::get('dealer')->model('betc');
        $rs_dealer_betc = $mdl_dealer_betc->dump(array("betc_id"=>$_POST["betc_id"]),"cos_id");
        if(!$rs_dealer_betc["cos_id"]){
            $this->end(false,"缺少组织架构ID。");
        }

        // 验证经销店铺是否是该贸易公司下的
        $cosShops = [];
        $cosList  = kernel::single('organization_cos')->getCosList($rs_dealer_betc['cos_id']);
        foreach ($cosList[1] as $k => $v) {
            if ($v['cos_type'] == 'shop') $cosShops[] = $v['cos_code'];
        }
        $shopList = $shopMdl->getList('shop_bn', ['shop_id' => $_POST["shop_id"]]);
        $shopList = array_column($shopList, 'shop_bn');
        $intersection = array_intersect($shopList, $cosShops); // 获取交集
        if (count($intersection) != count($shopList)) {
            $this->end(false,"店铺不属于所属贸易公司");
        }

        $snapshoot = []; //日志快照
        $snapshoot['sdb_dealer_series'] = $seriesInfo;

        //获取操作人信息
        $opinfo = $lib_ome_func->getDesktopUser();
        //产品线维度sku数量
        $sku_nums = count($_POST["bm_id"]); //单店铺的sku数量
        // 产品线更新
        $update_arr = array(
            "series_name" => $_POST["series_name"], //产品线名称
            "description" => $_POST["description"], //产品线描述
            "cat_name" => $_POST["cat_name"], //产品线分类
            "sku_nums" => $sku_nums, //绑定基础物料数量
            "betc_id" => $_POST["betc_id"], //贸易公司ID
            "cos_id" => $rs_dealer_betc["cos_id"], //组织架构ID
            "remark" => $_POST["remark"], //备注
        );
        $mdl_dealer_series->update($update_arr,$filter_arr);
        //基础物料维度 获取现有表中的数据比对提交的bm_id 先删除不存在的数据
        $post_bm_ids = array(); //post提交的数据
        foreach($_POST["bm_id"] as $var_bm_id){
            $post_bm_ids[] = $var_bm_id;
        }
        //key为bm_id value为sp_id的数组
        $rl_bmid_spid = array();
        $rs_current_dsp = $mdl_dealer_series_products->getList("*",array("series_id" => $_POST["series_id"]));
        $snapshoot['sdb_dealer_series_products'] = $rs_current_dsp;
        foreach($rs_current_dsp as $var_dsp){
            $rl_bmid_spid[$var_dsp["bm_id"]] = $var_dsp["sp_id"];
            if(!in_array($var_dsp["bm_id"],$post_bm_ids) && $var_dsp["sp_id"]){ //数据表中的bm_id不在提交范围内 做删除
                $mdl_dealer_series_products->delete(array("sp_id"=>$var_dsp["sp_id"]));
            }
        }
        //产品线绑物料更新和新增
        foreach($_POST["bm_id"] as $var_bm_id){
            if(isset($rl_bmid_spid[$var_bm_id])){ //存在的做“修改时间”的更新
                $filter_dsp = array("sp_id"=>$rl_bmid_spid[$var_bm_id]);
                $update_dsp = array("up_time" => date('Y-m-d H:i:s'));
                $mdl_dealer_series_products->update($update_dsp,$filter_dsp);
            }else{ //不存在的做新增
                $insert_dsp = array(
                    "series_id" => $_POST["series_id"],
                    "bm_id" => $var_bm_id,
                    "op_name" => $opinfo["op_name"],
                );
                $mdl_dealer_series_products->insert($insert_dsp);
            }
        }
        //店铺维度  获取现有表中的数据比对提交的shop_id 先删除不存在的数据
        $post_shop_ids = array(); //post提交的数据
        foreach($_POST["shop_id"] as $var_shop_id){
            $post_shop_ids[] = $var_shop_id;
        }
        //key为shop_id value为sp_id的数组
        $rl_shopid_enid = array();
        $rs_current_dse = $mdl_dealer_series_endorse->getList("*",array("series_id"=>$_POST["series_id"]));
        $snapshoot['sdb_dealer_series_endorse'] = $rs_current_dse;
        foreach($rs_current_dse as $var_dse){
            $rl_shopid_enid[$var_dse["shop_id"]] = $var_dse["en_id"];
            if(!in_array($var_dse["shop_id"],$post_shop_ids)){ //数据表中的shop_id不在提交范围内 做删除 按店铺维度
                if($var_dse["en_id"]){ //必须有效en_id 才能做删除
                    $mdl_dealer_series_endorse->delete(array("en_id"=>$var_dse["en_id"]));
                    $mdl_dealer_series_endorse_products->delete(array("en_id"=>$var_dse["en_id"]));
                }
            }else{ //按shop_id + bm_id的维度 判断做删除
                $rs_dsep_del = $mdl_dealer_series_endorse_products->getList("sep_id,bm_id",array("en_id"=>$var_dse["en_id"],"series_id"=>$_POST["series_id"]));
                if(!empty($rs_dsep_del)){
                    foreach($rs_dsep_del as $var_dsep_del){
                        if(!in_array($var_dsep_del["bm_id"],$post_shop_ids) && $var_dsep_del["sep_id"]){
                            $mdl_dealer_series_endorse_products->delete(array("sep_id"=>$var_dsep_del["sep_id"]));
                        }
                    }
                }
            }
        }
        $shopBsList = kernel::single('organization_cos')->getBsFromShopId($_POST["shop_id"]);
        //产品线授权到店和产品授权到店更新和新增
        foreach($_POST["shop_id"] as $var_shop_id){
            if(isset($rl_shopid_enid[$var_shop_id])){ //存在的做“修改时间”的更新
                $filter_dse = array("en_id"=>$rl_shopid_enid[$var_shop_id]);
                $update_dse = array(
                    "sku_nums" => $sku_nums,
                );
                if ($shopBsList[$var_shop_id]) {
                    $update_dse['bs_id'] = $shopBsList[$var_shop_id]['bs_id'];
                }
                $mdl_dealer_series_endorse->update($update_dse,$filter_dse);
                foreach($_POST["bm_id"] as $var_bm_id_dse){
                    $filter_dsep = array("bm_id"=>$var_bm_id_dse,"en_id"=>$rl_shopid_enid[$var_shop_id]);
                    $rs_dsep = $mdl_dealer_series_endorse_products->dump($filter_dsep);
                    if($rs_dsep["sep_id"]){ //存在的 做更新
                        $snapshoot['sdb_dealer_series_endorse_products'][] = $rs_dsep;
                        $filter_dsep_inner = array("sep_id" => $rs_dsep["sep_id"]);
                        $update_dsep_inner = array("up_time" => date('Y-m-d H:i:s'));
                        $mdl_dealer_series_endorse_products->update($update_dsep_inner,$filter_dsep_inner);
                    }else{ //不存在的 做新增
                        $insert_desp_inner = array(
                            "en_id" => $rl_shopid_enid[$var_shop_id],
                            "series_id" => $_POST["series_id"],
                            "shop_id" => $var_shop_id,
                            "bm_id" => $var_bm_id_dse,
                            "op_name" => $opinfo["op_name"],
                        );
                        $mdl_dealer_series_endorse_products->insert($insert_desp_inner);
                    }
                }
            }else{ //不存在的做新增
                $insert_arr_dse = array(
                    "series_id" => $_POST["series_id"],
                    "shop_id" => $var_shop_id,
                    "sku_nums" => $sku_nums,
                );
                if ($shopBsList[$var_shop_id]) {
                    $insert_arr_dse['bs_id'] = $shopBsList[$var_shop_id]['bs_id'];
                }
                $mdl_dealer_series_endorse->insert($insert_arr_dse);
                $en_id = $mdl_dealer_series_endorse->db->lastInsertId();
                foreach($_POST["bm_id"] as $var_bm_id_dse){
                    $insert_arr_dsep = array(
                        "en_id" => $en_id,
                        "series_id" => $_POST["series_id"],
                        "shop_id" => $var_shop_id,
                        "bm_id" => $var_bm_id_dse,
                        "op_name" => $opinfo["op_name"],
                    );
                    $mdl_dealer_series_endorse_products->insert($insert_arr_dsep);
                }
            }
        }
        $log_id = $mdl_ool->write_log('dealer_series_edit@dealer',$_POST["series_id"], isset($_POST["log_memo"]) ? $_POST["log_memo"] : "编辑产品线");
        if ($log_id && $snapshoot) {
            $shootMdl  = app::get('ome')->model('operation_log_snapshoot');
            $snapshoot = json_encode($snapshoot, JSON_UNESCAPED_UNICODE);
            $tmp       = ['log_id' => $log_id, 'snapshoot' => $snapshoot];
            $shootMdl->insert($tmp);
        }
        $this->end(true, '操作成功');
    }
    
    /**
     * 授权店铺保存
     * @param Int $series_id
     * @return Boolean
     */
    public function toEditShop(){
        if (!$_POST['series_id']) {
            $this->begin('index.php?app=dealer&ctl=admin_series&act=index');
            $this->end(false, '产品线ID无效');
        }
        
        $_POST['bm_id'] = [];
        $serProductsMdl = app::get('dealer')->model('series_products');
        $prductsList    = $serProductsMdl->getList("bm_id",array("series_id"=>$_POST['series_id']));
        if ($prductsList) {
            $_POST['bm_id'] = array_column($prductsList,'bm_id');
        }
        
        $_POST['log_memo'] = '授权店铺';
        return $this->toEdit();
    }

    /**
     * 产品线物料保存
     * @param Int $series_id
     * @return Boolean
     */
    public function toEditMaterial(){
        if (!$_POST['series_id']) {
            $this->begin('index.php?app=dealer&ctl=admin_series&act=index');
            $this->end(false, '产品线ID无效');
        }
        
        $_POST['shop_id'] = [];
        $serEndorseMdl = app::get('dealer')->model('series_endorse');
        $endorseList = $serEndorseMdl->getList("shop_id", ["series_id"=>$_POST['series_id']]);
        if ($endorseList) {
            $_POST['shop_id'] = array_column($endorseList,'shop_id');
        }
        
        $_POST['log_memo'] = '添加物料';
        return $this->toEdit();
    }

    /**
     * 产品线编辑提交方法
     * @param Int $series_id
     * @return Boolean
     */
    public function toEditMain(){
        $this->begin('index.php?app=dealer&ctl=admin_series&act=index');
        if(!$_POST["series_id"]){
            $this->end(false,'产品线主键ID缺失。');
        }

        $lib_dealer_series = kernel::single("dealer_series");
        $error_msg = "";
        $check_result = $lib_dealer_series->check_series_data($_POST,$error_msg);
        if(!$check_result){
            $this->end(false,$error_msg);
        }

        $mdl_ool = app::get('ome')->model('operation_log');
        $mdl_dealer_series = app::get('dealer')->model('series');
        $mdl_dealer_series_endorse = app::get('dealer')->model('series_endorse');

        $filter_arr = array("series_id"=>$_POST["series_id"]);
        $seriesInfo = $mdl_dealer_series->db_dump($filter_arr);
        if ($seriesInfo['betc_id']!=$_POST['betc_id']) {
            if ($seriesInfo['sku_nums']>0) {
                $this->end(false, '已添加商品，不能修改所属贸易公司。');
            }
            $endorseList = $mdl_dealer_series_endorse->db_dump(["series_id"=>$_POST["series_id"]]);
            if ($endorseList) {
                $this->end(false, '已授权店铺，不能修改所属贸易公司。');
            }
        }
        
        //获取贸易公司的组织架构ID
        $mdl_dealer_betc = app::get('dealer')->model('betc');
        $rs_dealer_betc = $mdl_dealer_betc->dump(array("betc_id"=>$_POST["betc_id"]),"cos_id");
        if(!$rs_dealer_betc["cos_id"]){
            $this->end(false,"缺少组织架构ID。");
        }
        
        $snapshoot = []; //日志快照
        $snapshoot['sdb_dealer_series'] = $seriesInfo;

        // 产品线更新
        $update_arr = array(
            "series_name"   => $_POST["series_name"], //产品线名称
            "description"   => $_POST["description"], //产品线描述
            "cat_name"      => $_POST["cat_name"], //产品线分类
            "betc_id"       => $_POST["betc_id"], //贸易公司ID
            "cos_id"        => $rs_dealer_betc["cos_id"], //组织架构ID
            "remark"        => $_POST["remark"], //备注
        );
        $mdl_dealer_series->update($update_arr,$filter_arr);

        $log_id = $mdl_ool->write_log('dealer_series_edit@dealer', $_POST["series_id"], "编辑产品线");
        if ($log_id && $snapshoot) {
            $shootMdl  = app::get('ome')->model('operation_log_snapshoot');
            $snapshoot = json_encode($snapshoot, JSON_UNESCAPED_UNICODE);
            $tmp       = ['log_id' => $log_id, 'snapshoot' => $snapshoot];
            $shootMdl->insert($tmp);
        }
        $this->end(true, '操作成功');
    }
  
    /**
     * 定制弹窗显示方法
     * @param void
     * @return void
     */
    public function object_rows(){
        if ($_POST['data']) {
            if ($_POST['app_id']) {
                $app = app::get($_POST['app_id']);
            } else {
                $app = $this->app;
            }
            $obj        = $app->model($_POST['object']);
            $schema     = $obj->get_schema();
            $textColumn = $_POST['textcol'] ? $_POST['textcol'] : $schema['textColumn'];
            $textColumn = explode(',', $textColumn);
            $_textcol   = $textColumn;
            $textColumn = $textColumn[0];
            $keycol = $_POST['key'] ? $_POST['key'] : $schema['idColumn'];
            //统一做掉了。
            if ($_POST['data'][0] === '_ALL_') {
                if (isset($_POST['filter']['advance']) && $_POST['filter']['advance']) {
                    $arr_filters = explode(',', $_POST['filter']['advance']);
                    foreach ($arr_filters as $obj_filter) {
                        $arr                      = explode('=', $obj_filter);
                        $_POST['filter'][$arr[0]] = $arr[1];
                    }
                    unset($_POST['filter']['advance']);
                }
                $all_filter    = !empty($obj->__all_filter) ? $obj->__all_filter : array();
                $filter        = !empty($_POST['filter']) ? $_POST['filter'] : $all_filter;
                $arr_list      = $obj->getList($keycol, $filter);
                $_POST['data'] = array_map('current', $arr_list);
            }
            $items = $obj->getList('*', array($keycol => $_POST['data']));
            $name  = $items[0][$textColumn];
            if ($_POST['type'] == 'radio') {
                if (strpos($textColumn, '@') !== false) {
                    list($field, $table, $app_) = explode('@', $textColumn);
                    if ($app_) {
                        $app = app::get($app_);
                    }
                    $mdl    = $app->model($table);
                    $schema = $mdl->get_schema();
                    $row    = $mdl->getList('*', array($schema['idColumn'] => $items[0][$keycol]));
                    $name   = $row[0][$field];
                }
                echo json_encode(array('id' => $items[0][$keycol], 'name' => $name));
                exit;
            }
            $this->pagedata['_input'] = array('items' => $items,
                'idcol'  => $schema['idColumn'],
                'keycol' => $keycol,
                'textcol' => $textColumn,
                '_textcol' => $_textcol,
                'name' => $_POST['name'],
            );
            $this->pagedata['_input']['view_app'] = 'desktop';
            $this->pagedata['_input']['view']     = $_POST['view'];
            if ($_POST['view_app']) {
                $this->pagedata['_input']['view_app'] = $_POST['view_app'];
            }
            if (strpos($_POST['view'], ':') !== false) {
                list($view_app, $view)                = explode(':', $_POST['view']);
                $this->pagedata['_input']['view_app'] = $view_app;
                $this->pagedata['_input']['view']     = $view;
            }
            $this->display('admin/series/input-row.html');
        }
    }
    
    /**
     * 定制input方法
     * @param void
     * @return void
     */
    public function finder_common(){
        $params = array(
            'title'                  => app::get('desktop')->_('列表'),
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            'use_buildin_recycle'    => false,
            'use_buildin_export'     => false,
            'use_buildin_import'     => false,
            'use_buildin_filter'     => true,
            'use_buildin_setcol'     => true,
            'use_buildin_refresh'    => true,
            'finder_aliasname'       => 'finder_common',
            'alertpage_finder'       => true,
            'use_buildin_tagedit'    => false,
        );
        if ($_GET['findercount']) {
            $params['object_method']['count'] = $_GET['findercount'];
        }
        if ($_GET['findergetlist']) {
            $params['object_method']['getlist'] = $_GET['findergetlist'];
        }
        if (substr($_GET['name'], 0, 7) == 'adjunct') {
            $params['orderBy'] = 'goods_id desc';
        }
        $this->finder($_GET['app_id'] . '_mdl_' . $_GET['object'], $params);
    }
    
    /**
     * 设置状态（启、停用）
     * @param $series_id 产品线主键ID
     * @param $status active做启用  close做停用
     * @return void
     */
    function setStatus($series_id,$status){
        $this->begin('index.php?app=dealer&ctl=admin_series&act=index');
        if($status == 'true'){
            $status     = "active";
            $operation  = 'dealer_series_on@dealer';
            $memo       = '产品线开启';
        } else {
            $status     = "close";
            $operation  = 'dealer_series_off@dealer';
            $memo       = '产品线关停';
        }
        kernel::database()->query("update sdb_dealer_series set status='{$status}' where series_id={$series_id} limit 1");

        $mdl_ool = app::get('ome')->model('operation_log');
        $log_id = $mdl_ool->write_log($operation, $series_id, $memo);

        if ($status == 'close') {
            // 更新代发商品为自发
            $dsepMdl  = app::get('dealer')->model('series_endorse_products');
            $list     = $dsepMdl->getList('*',array("series_id"=>$series_id,'is_shopyjdf_type'=>'2'));
            if ($list) {
                $list   = array_column($list, null, 'sep_id');
                $upInfo = [
                    'is_shopyjdf_type' => '1',
                    'from_time'        => strtotime(date('Y-m-d H:i:00')),
                    'end_time'         => null,
                ];
                $dsepMdl->update($upInfo, ['sep_id|in' => array_keys($list)]);

                $memo = '关停产品线后自动设置自发货模式,生效时间:' . date('Y-m-d H:i', $upInfo['from_time']) . ',结束时间:';

                // 保存操作记录
                $shootMdl  = app::get('ome')->model('operation_log_snapshoot');
                foreach ($list as $sep_id => $snapshoot) {
                    $log_id = $mdl_ool->write_log('set_shop_yjdfType@dealer', $sep_id, $memo);
                    if ($log_id && $snapshoot) {
                        $snapshoot = json_encode($snapshoot, JSON_UNESCAPED_UNICODE);
                        $tmp       = ['log_id' => $log_id, 'snapshoot' => $snapshoot];
                        $shootMdl->insert($tmp);
                    }
                }
            }
        }
        $this->end(true, '操作成功');
        // echo "<script>parent.MessageBox.success('设置已成功！');parent.finderGroup['{$_GET[finder_id]}'].refresh();</script>";
        // exit;
    }
    
    /**
     * 产品线查询详情
     * @param $series_id 产品线主键ID
     * @return void 
     */
    public function detail($series_id){
        if (!$series_id) {
            die("缺失主键ID");
        }
        $mdl_dealer_series = app::get('dealer')->model('series');
        $rs_dealer_series = $mdl_dealer_series->dump(array("series_id"=>$series_id));
        if(empty($rs_dealer_series)){
            die("产品线数据为空");
        }
        //贸易公司
        $mdl_dealer_betc = app::get('dealer')->model('betc');
        $dealer_betc_list = $mdl_dealer_betc->getList('betc_id,betc_name');
        $this->pagedata['betcs'] = $dealer_betc_list;
        //基础物料
        $mdl_dealer_series_products = app::get('dealer')->model('series_products');
        $rs_dealer_series_products = $mdl_dealer_series_products->getList("bm_id",array("series_id"=>$series_id));
        if(empty($rs_dealer_series_products)){
            die("产品线绑物料数据为空");
        }
        $bm_ids = array();
        foreach($rs_dealer_series_products as $var_rdsp){
            $bm_ids[] = $var_rdsp["bm_id"];
        }
        $count = is_array($bm_ids) ? count($bm_ids) : 0;
        $this->pagedata['bind_bm_ids']  = $bm_ids;
        $this->pagedata['replacehtml'] = <<<EOF
<div id='hand-selected-product' style='float:left;margin:10px 10px 10px 5px'>已选择了{$count}个基础物料,<a href='javascript:void(0);' onclick='product_selected_show();'>查看选中的基础物料.</a></div>
EOF;
        //经销店铺
        $mdl_dealer_series_endorse = app::get('dealer')->model('series_endorse');
        $rs_dealer_series_endorse = $mdl_dealer_series_endorse->getList("shop_id",array("series_id"=>$series_id));
        if(empty($rs_dealer_series_endorse)){
            die("产品线绑经销店铺为空");
        }
        $shop_ids = array();
        foreach($rs_dealer_series_endorse as $var_rdse){
            $shop_ids[] = $var_rdse["shop_id"];
        }
        $count_shop = is_array($shop_ids) ? count($shop_ids) : 0;
        $this->pagedata['bind_shop_ids']  = $shop_ids;
        $this->pagedata['replacehtml_shop'] = <<<EOF
        <div id='hand-selected-shop' style='float:left;margin:10px 10px 10px 5px'>已选择了{$count_shop}个经销店铺,<a href='javascript:void(0);' onclick='shop_selected_show();'>查看选中的经销店铺.</a></div>
EOF;
        $this->pagedata['series_info'] = $rs_dealer_series;
        // //操作日志
        // $logObj = app::get('ome')->model('operation_log');
        // //产品线日志
        // $logList = $logObj->read_log(array('obj_id'=>$series_id, 'obj_type'=>'series@dealer'), 0, -1);
        // foreach($logList as $k => $v){
        //     $logList[$k]['operate_time'] = date('Y-m-d H:i:s', $v['operate_time']);
        // }
        // $this->pagedata['data'] = $logList;
        $this->singlepage('admin/series/detail.html');
    }

    public function closeSeries($series_id)
    {
        if (!$series_id) {
            die("缺失主键ID");
        }
        $mdl_dealer_series = app::get('dealer')->model('series');
        $rs_dealer_series  = $mdl_dealer_series->dump(array("series_id"=>$series_id));
        if(empty($rs_dealer_series)){
            die("产品线数据为空");
        }
        //代发货的基础物料
        $mdl_dealer_series_products = app::get('dealer')->model('series_products');

        $dsepMdl  = app::get('dealer')->model('series_endorse_products');
        $dsepList = $dsepMdl->getList("bm_id",array("series_id"=>$series_id,'is_shopyjdf_type'=>'2'));
        if ($dsepList) {
            $bMaterialMdl = app::get('material')->model('basic_material');
            $bm_ids       = array_column($dsepList, 'bm_id');
            $materialList = $bMaterialMdl->getList('material_bn,material_name', ['bm_id|in'=>$bm_ids]);
            $this->pagedata['drop_shipping'] = $materialList;
        } else {
            $this->pagedata['drop_shipping'] = [];
        }
        $this->pagedata['series_id'] = $series_id;
        $this->display('admin/series/close.html');
    }

    /**
     * 查看快照
     */
    public function show_history($log_id)
    {
        $logSnapshootMdl = app::get('ome')->model('operation_log_snapshoot');
        //日志
        $log = $logSnapshootMdl->db_dump(['log_id' => $log_id]);
        $row = json_decode($log['snapshoot'], 1);

        // 产品线
        $rs_dealer_series = $row['sdb_dealer_series'];
        $this->pagedata['series_info'] = $rs_dealer_series;

        //贸易公司
        $mdl_dealer_betc = app::get('dealer')->model('betc');
        $dealer_betc_list = $mdl_dealer_betc->getList('betc_id,betc_name');
        $this->pagedata['betcs'] = $dealer_betc_list;

        //基础物料
        $rs_dealer_series_products = $row['sdb_dealer_series_products'];
        $bm_ids = array();
        foreach($rs_dealer_series_products as $var_rdsp){
            $bm_ids[] = $var_rdsp["bm_id"];
        }
        $count = is_array($bm_ids) ? count($bm_ids) : 0;
        $this->pagedata['bind_bm_ids']  = $bm_ids;
        $this->pagedata['replacehtml'] = <<<EOF
<div id='hand-selected-product' style='float:left;margin:10px 10px 10px 5px'>已选择了{$count}个基础物料,<a href='javascript:void(0);' onclick='product_selected_show();'>查看选中的基础物料.</a></div>
EOF;
        //经销店铺
        $rs_dealer_series_endorse = $row['sdb_dealer_series_endorse'];
        $shop_ids = array();
        foreach($rs_dealer_series_endorse as $var_rdse){
            $shop_ids[] = $var_rdse["shop_id"];
        }
        $count_shop = is_array($shop_ids) ? count($shop_ids) : 0;
        $this->pagedata['bind_shop_ids']  = $shop_ids;
        $this->pagedata['replacehtml_shop'] = <<<EOF
        <div id='hand-selected-shop' style='float:left;margin:10px 10px 10px 5px'>已选择了{$count_shop}个经销店铺,<a href='javascript:void(0);' onclick='shop_selected_show();'>查看选中的经销店铺.</a></div>
EOF;
        $this->pagedata['history']          = true;
        
        $this->singlepage('admin/series/detail.html');
    }

    public function exportSeriesTemplate()
    {
        $seriesMdl   = app::get('dealer')->model('series');
        $row = $seriesMdl->exportTemplate();
        $lib = kernel::single('omecsv_phpexcel');
        $lib->newExportExcel(null, '产品线导入模板', 'xls', $row);
    }
    
}