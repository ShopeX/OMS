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
 * 供应商基础物料
 *
 * @access public
 * @author wangbiao<wangbiao@shopex.cn>
 */
class purchase_ctl_admin_supplier_goods extends desktop_controller{

    var $name = "供应商基础物料";
    var $workground = "purchase_manager";

    /*
     * 供应商基础物料列表
     */
    function index()
    {
        $finder_id = $_REQUEST['_finder']['finder_id'];

        $base_filter = array();
        $params = array('title'=>'供应商基础物料管理',
                'actions'=>array(
                        array(
                                'label' => '物料关联',
                                'href' => 'index.php?app=purchase&ctl=admin_supplier_goods&&act=dispatch&view='.$_GET['view']."&finder_id=".$finder_id,
                                'target' => "_blank",
                        ),
                        array(
                                'label' => '导出模板',
                                'href' => 'index.php?app=purchase&ctl=admin_supplier_goods&act=exportTemplate',
                                'target' => "_blank",
                        ),
                ),
                'use_buildin_import'=>true,
                'use_buildin_filter'=>true,
                'use_buildin_recycle'=>true,
                'use_buildin_selectrow'=>true,
                'use_bulidin_view'=>true,
                'base_filter'=>$base_filter,
                'orderBy'=>'supplier_id ASC',
        );

        $this->finder('purchase_mdl_supplier_goods', $params);
    }

    //门店关联物料
    function dispatch()
    {
        $view = $_REQUEST['view'];
        $finder_id = $_REQUEST['finder_id'];
        $page = $_REQUEST['page'] ? $_REQUEST['page'] : 1;
        $pagelimit = 50;
        $offset = ($page-1) * $pagelimit;

        //供应商列表
        $supplierList    = array();
        $supplierObj     = app::get('purchase')->model('supplier');
        $supplierList    = $supplierObj->getList('supplier_id, name', array(), 0, -1, 'supplier_id DESC');
        $this->pagedata['supplierList'] = $supplierList;

        //搜索项
        if($_REQUEST['search'])
        {
            //搜索操作
            $params['search_key'] = $_REQUEST['search_key'];

            //选择品牌或者分类 此值为空
            if(empty($_REQUEST['search_value'])){
                $params['search_value'] = $_REQUEST['search_value_'.$_REQUEST['search_key']];
                $this->pagedata['search_value_key'][$_REQUEST['search_key']] = $params['search_value'];
            }else{
                $params['search_value'] = $_REQUEST['search_value'];
                $this->pagedata['search_value'] = $params['search_value'];
            }

            $this->pagedata['search_key'] = $params['search_key'];
            $this->pagedata['search_value_last'] = $params['search_value'];

            //获取基础物料列表
            $data = $this->get_product_info($offset, $pagelimit, $params);

            //获取记录数
            $count    = $this->do_count($params);
            $link     = 'index.php?app=purchase&ctl=admin_supplier_goods&&act=dispatch&view='.$view;
            $link     .= '&search=true&search_value='.$params['search_value'].'&search_key='.$params['search_key'].'&target=container&page=%d&finder_id='. $finder_id;
        }else{
            //获取基础物料列表
            $data = $this->get_product_info($offset, $pagelimit);

            //获取记录数
            $count = $this->do_count();
            $link = 'index.php?app=purchase&ctl=admin_supplier_goods&&act=dispatch&view='.$view.'&target=container&page=%d&finder_id='. $finder_id;
        }

        $total_page = ceil($count/$pagelimit);
        $pager = $this->ui()->pager(array(
                'current'=>$page,
                'total'=>$total_page,
                'link'=>$link,
        ));

        $this->pagedata['rows'] = $data;

        //获取搜索选项
        $this->pagedata['search'] = $this->get_search_options();

        //获取自定义搜索项下拉列表
        $this->pagedata['search_list'] = $this->get_search_list();

        $this->pagedata['count'] = $count;
        $this->pagedata['pager'] = $pager;
        $this->pagedata['finder_id'] = $finder_id;

        if($_GET['target'] || $_POST['search'] == 'true')
        {
            return $this->display('admin/supplier/product_index.html');
        }

        $this->singlepage('admin/supplier/product_index.html');
    }

    //保存
    function do_save()
    {
        $this->begin();

        $bm_ids = $_POST['bm_id'];
        if(!$bm_ids){
            $this->end(false,'请选择基础物料');
        }

        $supplier_id    = intval($_POST['supplier_id']);
        if(empty($supplier_id)){
            $this->end(false,'请选择供应商');
        }

        //全选时候的处理
        if($_POST['select_all'] == 'true')
        {
            $bm_ids                 = array();
            $params                 = array();
            $params['search_key']   = $_POST['search_key'];
            $params['search_value'] = $_POST['search_value'];

            if(!empty($params['search_key']) && !empty($params['search_value']))
            {
                $bm_ids    = $this->get_product_info('', '', $params, true);
            }
            else
            {
                $bm_ids    = $this->get_product_info('', '', '', true);
            }
        }

        //格式化bm_ids
        $bm_id_list    = array();
        foreach ($bm_ids as $key => $val)
        {
            $bm_id_list[$val]    = $val;
        }

        $bm_ids    = $bm_id_list;
        unset($bm_id_list);

        //获取已关联的基础物料
        $supGoodsObj    = app::get('purchase')->model('supplier_goods');
        $supGoodsList   = $supGoodsObj->getList('bm_id', array('supplier_id'=>$supplier_id));
        if($supGoodsList)
        {
            foreach ($supGoodsList as $key => $val)
            {
                if(in_array($val['bm_id'], $bm_ids))
                {
                    unset($bm_ids[$val['bm_id']]);
                }
            }
        }

        if(empty($bm_ids)){
            $this->end(false,'没有可关联的基础物料');
        }

        $values    = array();
        foreach ($bm_ids as $key => $val)
        {
            $values[]    = "(". $supplier_id .", ". $val .")";
        }

        $sql    = "INSERT INTO sdb_purchase_supplier_goods (supplier_id, bm_id) VALUES";
        $sql    .= implode(',', $values);
        $supGoodsObj->db->exec($sql);

        $this->end(true,'操作成功');
    }

    //计算记录条数
    function do_count($params=array())
    {
        $mdlMaterialBasic = app::get('material')->model('basic_material');
        $sql = "SELECT count(mbm.bm_id) as total_count FROM sdb_material_basic_material as mbm";
        $sql_filter = $this->get_filter($params);
        $sql = $sql.$sql_filter;
        $count = $mdlMaterialBasic->db->select($sql);
        return $count[0]["total_count"];
    }

    /**
     * 获取自定义搜素选项
     * @return multitype:multitype:unknown
     */
    public function get_search_list()
    {
        //品牌
        $brandObj = app::get('ome')->model('brand');
        $brand_tmp =$brandObj->getList('brand_name,brand_id');
        $brand = array();
        foreach($brand_tmp as $branddata){
            $brand[$branddata['brand_id']] = $branddata['brand_name'];
        }

        //类型
        $typeObj = app::get('ome')->model('goods_type');
        $type_tmp = $typeObj->getList('type_id,name');
        $type = array();
        foreach($type_tmp as $typedata){
            $type[$typedata['type_id']] = $typedata['name'];
        }

        $list = array(
                'brand_name'=>$brand,
                'type_name'=>$type,
        );

        return $list;
    }

    /**
     * 获取自定义搜素选项
     */
    function get_search_options(){
        $options = array(
                'material_name'=>'物料名称',
                'material_bn'=>'物料编码',
                'brand_name'=>'品牌',
                'type_name'=>'分类',
        );
        return $options;
    }

    /**
     * 搜索基础物料
     */
    function get_product_info($offset='', $limit='', $params=[], $flag=false)
    {
        $mdlMaterialBasic = app::get('material')->model('basic_material');
        $mdlMaterialBasicExt = app::get('material')->model('basic_material_ext');
        $mdlOmeBrand = app::get('ome')->model('brand');
        $mdlOmeGoodsType = app::get('ome')->model('goods_type');

        $sql = "SELECT mbm.bm_id,mbm.material_name,mbm.material_bn FROM sdb_material_basic_material as mbm";
        $sql_filter = $this->get_filter($params);
        if($limit){
            $sql = $sql.$sql_filter." limit ". $offset .",". $limit;
        }else{
            $sql = $sql.$sql_filter;
        }

        $rs_material = $mdlMaterialBasic->db->select($sql);

        if(empty($rs_material)){
            return array();
        }

        $bm_ids = array();
        foreach ($rs_material as $var_material){
            $bm_ids[] = $var_material["bm_id"];
        }

        //直接返回bm_ids
        if($flag)
        {
            return $bm_ids;
        }

        $rs_material_ext = $mdlMaterialBasicExt->getList("bm_id,specifications,brand_id,cat_id",array("bm_id|in"=>$bm_ids));
        $brand_ids = array();
        $cat_ids = array();
        foreach ($rs_material_ext as $var_material_ext){
            if($var_material_ext["brand_id"] && !in_array($var_material_ext["brand_id"],$brand_ids)){
                $brand_ids[] = $var_material_ext["brand_id"];
            }
            if($var_material_ext["cat_id"] && !in_array($var_material_ext["cat_id"],$cat_ids)){
                $cat_ids[] = $var_material_ext["cat_id"];
            }
        }

        //获取品牌
        if($brand_ids){
            $rs_brand = $mdlOmeBrand->getList("brand_id,brand_name",array("brand_id|in"=>$brand_ids));
            $rl_brand_id_name = array();
            foreach ($rs_brand as $var_brand){
                $rl_brand_id_name[$var_brand["brand_id"]] = $var_brand["brand_name"];
            }
        }

        //获取类型
        if($cat_ids){
            $rs_cat = $mdlOmeGoodsType->getList("type_id,name",array("type_id|in"=>$cat_ids));
            $rl_type_id_name = array();
            foreach ($rs_cat as $var_cat){
                $rl_type_id_name[$var_cat["type_id"]] = $var_cat["name"];
            }
        }

        //获取bm_id和规格、品牌、类型
        $rl_bm_id_info = array();
        foreach ($rs_material_ext as $item_material_ext){
            $rl_bm_id_info[$item_material_ext["bm_id"]] = array(
                    "specifications" => $item_material_ext["specifications"],
                    "brand_name" => $rl_brand_id_name[$item_material_ext["brand_id"]],
                    "type_name" => $rl_type_id_name[$item_material_ext["cat_id"]],
            );
        }

        foreach ($rs_material as &$item_material){
            $item_material["specifications"] = "-";
            $item_material["brand_name"] = "-";
            $item_material["type_name"] = "-";
            if($rl_bm_id_info[$item_material["bm_id"]]["specifications"]){
                $item_material["specifications"] = $rl_bm_id_info[$item_material["bm_id"]]["specifications"];
            }
            if($rl_bm_id_info[$item_material["bm_id"]]["brand_name"]){
                $item_material["brand_name"] = $rl_bm_id_info[$item_material["bm_id"]]["brand_name"];
            }
            if($rl_bm_id_info[$item_material["bm_id"]]["type_name"]){
                $item_material["type_name"] = $rl_bm_id_info[$item_material["bm_id"]]["type_name"];
            }
        }
        unset($item_material);

        return $rs_material;
    }

    /**
     * 查询条件转换
     *
     * @param Array $params 查询条件参数
     * @return String
     */
    function get_filter($params=array())
    {
        $sql_filter = " where mbm.visibled=1";

        if(!empty($params)){
            switch ($params["search_key"]){
                case "material_name":
                    $sql_filter = $sql_filter." and mbm.material_name like '".$params['search_value']."%'";
                    break;
                case "material_bn":
                    $sql_filter = $sql_filter." and mbm.material_bn like '".$params['search_value']."%'";
                    break;
                case "brand_name":
                    $sql_join = " left join sdb_material_basic_material_ext as mbme on mbm.bm_id=mbme.bm_id";
                    $sql_filter = $sql_join.$sql_filter." and mbme.brand_id=".intval($params['search_value']);
                    break;
                case "type_name":
                    $sql_join = " left join sdb_material_basic_material_ext as mbme on mbm.bm_id=mbme.bm_id";
                    $sql_filter = $sql_join.$sql_filter." and mbme.cat_id=".intval($params['search_value']);
                    break;
            }
        }

        return $sql_filter;
    }

    /*
     * 导出模板
    */
    function exportTemplate()
    {
        header("Content-Type: text/csv");

        $filename = "供应商基础物料模板.csv";
        $encoded_filename = urlencode($filename);
        $encoded_filename = str_replace("+", "%20", $encoded_filename);

        $ua = $_SERVER["HTTP_USER_AGENT"];
        if (preg_match("/MSIE/", $ua)) {
            header('Content-Disposition: attachment; filename="' . $encoded_filename . '"');
        } else if (preg_match("/Firefox/", $ua)) {
            header('Content-Disposition: attachment; filename*="utf8\'\'' . $filename . '"');
        } else {
            header('Content-Disposition: attachment; filename="' . $filename . '"');
        }

        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');

        //模板
        $supGoodsObj    = app::get('purchase')->model('supplier_goods');
        $title          = $supGoodsObj->exportTemplate();

        echo '"'.implode('","',$title).'"';
    }
}
?>
