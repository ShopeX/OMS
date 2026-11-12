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
 * 供应商管理
 */
class console_ctl_admin_supplier extends desktop_controller{

    var $name = "供应商";
    var $workground = "console_center";

    /**
     * 供应商列表显示
     */
    function index(){
        $singleselect = $_GET['singleselect'];
        if (!$singleselect){
            $actions = array(
                 array(
                   'label'=>'添加供应商',
                   'href'=>'index.php?app=console&ctl=admin_supplier&act=addsupplier',
                   'target' => '_blank',
                 ),
                 array(
                    'label' => '导出模板',
                    'href' => 'index.php?app=console&ctl=admin_supplier&act=exportTemplate',
                    'target' => '_blank',
                 ),
            );
            
            $export = true;
            $import = true;
            $filter = true;
        }
        
        $params = array(
          'title'=>$this->name,
          'actions' => $actions,
          'use_buildin_new_dialog' => false,
          'use_buildin_set_tag'=>false,
          'use_buildin_recycle'=>true,
          'use_buildin_export'=>$export,
          'use_buildin_import'=>$import,
          'use_buildin_filter'=>$filter,
          'finder_cols'=>'column_edit,name,brief,arrive_days,operator,credit_lv,telphone,addr,zip,bank,fax',
          'orderBy' => 'supplier_id desc',
        );
        
        $this->finder('purchase_mdl_supplier', $params);
    }

    /*
     * 导出模板
     */
    function exportTemplate(){
        header("Content-Type: text/csv");
        $filename = "供应商模板.csv";
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
        $oSupplier = app::get('purchase')->model('supplier');
        $title = $oSupplier->exportTemplate('supplier');
        echo '"'.implode('","',$title).'"';
    }

    /**
     * 添加供应商
     * @param array
     * @return string
     */
    function addsupplier(){
        //调用ome/model/supplier模块
        $oSuuplier = app::get('purchase')->model("supplier");

        if ($_POST['addSubmit']=="do"){
            // unset
            unset($_POST['addSubmit']);
            
            $gotourl = 'index.php?app=console&ctl=admin_supplier';
            $this->begin($gotourl);
            
            //表单验证
            $oSuuplier->validate($_POST);
            $result = $oSuuplier->save_supplierDo($_POST);
            $message = $result ? '保存成功' : '保存失败';
            
            $this->end($result, app::get('base')->_($message));
        }else{
            //信用等级
            $this->pagedata['credit_lev'] = $oSuuplier->getCreditlve();
            
            //供应品牌
            $this->pagedata['brand'] = app::get('purchase')->model("supplier")->getBrand();

            //显示添加供应商窗口
            $this->singlepage('admin/supplier/add_supplier.html');
        }
    }

    /**
     * 编辑供应商
     *
     * @param number
     * @return boolean
     */
    function editsupplier($supplier_id){
        if($_POST['arrive_days'] == ''){
            $_POST['arrive_days'] = NULL;
        }

        //供应商业务模块加载
        $oSupplier = app::get('purchase')->model("supplier");

        if ($_POST['editSubmit']=="do"){
            // unset
            unset($_POST['editSubmit']);
            
            $gotourl = 'index.php?app=console&ctl=admin_supplier';

            $this->begin($gotourl);
            //TODO:
            //表单验证
            $oSupplier->validate($_POST);
            $result = $oSupplier->save_supplierDo($_POST,true);
            $msg = $result ? '成功' : '失败';
            $this->end($result, app::get('base')->_('修改'.$msg));
        }else{
            //获取供应商详情
            $filter = array("supplier_id"=>$supplier_id);
            $supplierDetail = $oSupplier->dump($filter,'*');

            //信用等级
            $this->pagedata['credit_lev'] = $oSupplier->getCreditlve();

            //读取供应商提供的品牌
            $oBrand = app::get('purchase')->model('supplier_brand');
            $filter = array("supplier_id"=>$supplier_id);
            $brand_detail = $oBrand->getlist('*',$filter);
            $oBrandlist = app::get('purchase')->model('supplier');
            if ($brand_detail)
            foreach ($brand_detail as $k=>$v){
                $brandname = $oBrandlist->getBrand($v['brand_id']);
                $brand[$k] = $brandname[0]['brand_id'];
                //显示checkbox表单
                $brand_checkbox .= "<input type=\"checkbox\" style=\"display:none;\" name=\"brand[]\" id=\"brand[]\" value=\"{$brandname[0]['brand_id']}\" checked=\"checked\" />{$brandname[0]['brand_name']} &nbsp;&nbsp;";
            }

            //读取品牌数据表
            $brandlist = $oBrandlist->getBrand();

            $supplierDetail['contacter'] = unserialize($supplierDetail['contacter']);
            if (!strstr($supplierDetail['area'],":")){
                $supplierDetail['area_defined'] = true;
            }
            $this->pagedata['detail'] = $supplierDetail;
            $this->pagedata['brandselect'] = $brand;
            $this->pagedata['brandlist'] = $brandlist;
            $this->pagedata['brand_checkbox'] = $brand_checkbox;
            $this->singlepage('admin/supplier/edit_supplier.html');
        }
    }

    /*
     * 获取采购员 getOperator
     */
    public function getOperator()
    {
        $sid = $_GET['sid'];
        if (!is_numeric($sid)) die;

        $oSupplier = app::get('purchase')->model("supplier");
        $operator = $oSupplier->supplier_detail($sid, 'operator');
        if (!$operator['operator']) $operator['operator'] = '未知';

        echo json_encode($operator);
    }

    /*
     * 供应商所有采购单预付款
     */
    public function balance($supplier_id=null)
    {
        $oSupplier = app::get('purchase')->model("supplier");
        $result = $oSupplier->get_balance($supplier_id);
        //供应商名称
        $supplier = $oSupplier->supplier_detail($supplier_id, 'name');

        $this->pagedata['supplier_name'] = $supplier['name'];
        $this->pagedata['balance'] = $result;
        $this->display('admin/supplier/balance.html');
    }

    /*
     * 验证添加的供应商是否存在
     */
    public function is_supplier()
    {
        $bn = $_POST['bn'];
        $name = $_POST['name'];
        $oSupplier = app::get('purchase')->model("supplier");
        if ( $oSupplier->dump(array('bn'=>$bn), 'supplier_id') or $oSupplier->dump(array('name'=>$name), 'supplier_id') ){
            die('1');#已存在
        }else{
            die('0');#未存在
        }
    }
    
    /*
     * 商品历史价格查询
     */
    public function history_price($supplier_id,$goods_id)
    {
        if(!is_numeric($goods_id) or !is_numeric($supplier_id)) die('访问出错');

        $oSupplier = app::get('purchase')->model("supplier");
        
        $basicMaterialObj    = app::get('material')->model('basic_material');
        
        $his_price = $oSupplier->history_price_search($supplier_id,$goods_id);

        if ($his_price)
        foreach ($his_price as $k=>$v)
        {
            $products    = $basicMaterialObj->dump(array('bm_id'=>$v['product_id']), 'bm_id, material_bn, material_name');
            
            $v['name'] = $products['material_name'];
            $v['bn'] = $products['material_bn'];
            $price[] = $v;
        }

        $this->pagedata['his_price'] = $price;
        $this->display('admin/supplier/history_price.html');
    }

    /*
     * 品牌选择 choice_brand
     */
    public function choice_brand()
    {
        $oBrand = app::get('purchase')->model("supplier");

        //获取品牌
        $this->pagedata['brand'] = $oBrand->getBrand();
        $this->display('admin/supplier/choice_brand.html');
    }

    /*
     * 供应商查找
     */
    function getSupplier(){
        $name = $_GET['name'];
        if ($name){
            $supplier = app::get('purchase')->model('supplier');
            $data = $supplier->getSupplier($name);

            echo "window.autocompleter_json=".json_encode($data);
        }
    }

    /**
     * 供应商查找
     */
    function getSupplierById(){
        $supplier_id = $_POST['id'];
        if ($supplier_id){
            $supplier = app::get('purchase')->model('supplier');
            $data = $supplier->dump(array('supplier_id'=>$supplier_id), 'supplier_id,name');

            //echo json_encode($data);
            echo "{id:'".$data['supplier_id']."',name:'".$data['name']."'}";
        }
    }
}
?>
