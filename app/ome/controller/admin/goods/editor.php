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
class ome_ctl_admin_goods_editor extends desktop_controller{
    //var $workground = 'goods_manager';
    var $simpleGoodsId = 1;

    function nospec($cat_id=0){
        $this->_editor($_POST['type_id']);
        $goods_id = intval($_GET['goods_id']);
        
        $basicMaterialObj = app::get('material')->model('basic_material');
        $basicMaterialBarcode    = kernel::single('material_basic_material_barcode');
        
        $goods = $basicMaterialObj->dump(array('bm_id'=>$goods_id), 'bm_id');
        
        $goods['barcode']    = $basicMaterialBarcode->getBarcodeById($goods['bm_id']);
        $goods['goods_id']    = $goods['bm_id'];
        
        $this->display('admin/goods/detail/spec/nospec.html');
    }

   //新增商品页面ctl
    function add(){
        $this->pagedata['title'] = '添加商品';
        $this->pagedata['cat']['type_id'] = $this->simpleGoodsId;
        $this->pagedata['goods']['type']['type_id'] = $this->simpleGoodsId;
        $this->_editor($this->simpleGoodsId);
        $oGtype = $this->app->model('goods_type');

        $this->pagedata['gtype']['status'] = $oGtype->checkDefined();
        header("Cache-Control:no-store");
        $this->singlepage('admin/goods/detail/frame.html');
    }

    function _editor($type_id){

        $cat = $this->app->model('goods_cat');
        $this->pagedata['cats'] = $cat->getMapTree(0,'');
        $this->pagedata['goodsbn_display_switch'] = $this->app->getConf('goodsbn.display.switch');
        $objGtype = $this->app->model('goods_type');
        $this->pagedata['gtype'] = $objGtype->getList('*','',0,-1);
        if( !$this->pagedata['gtype'] ){
            echo '请先添加商品类型';
            exit;
        }
//        $gimage = $this->app->model('gimages');
//        $this->pagedata['uploader'] = $gimage->uploader();

/*{{{*/
        $prototype = $objGtype->dump($type_id,'*',array('brand'=>array('*',array(':brand'=>array('brand_id,brand_name')))));
        $oBrand = $this->app->model('brand');
        if( $type_id == 1 ){
            $this->pagedata['brandList'] = $oBrand->getList('brand_id,brand_name','',0,-1);
        }else{
           if ($prototype['brand']){
               $brand_ids = array();
               foreach( $prototype['brand'] as $typeBrand ){
                  $brand_ids[] = $typeBrand['brand_id'];
               }
               if(!empty($brand_ids)){
                  $this->pagedata['brandList'] = $oBrand->getList('brand_id,brand_name',array('brand_id'=>$brand_ids),0,-1);
               }
           }
        }

        $this->pagedata['sections'] = array();
        $sections = array(
            'basic'=>array(
                'label'=>app::get('base')->_('基本信息'),
                'options'=>'',
                'file'=>'admin/goods/detail/basic.html',
            ),

            'content'=>array(
                'label'=>app::get('base')->_('详细介绍'),
                'options'=>'',
                'file'=>'admin/goods/detail/content.html',
            ),
            'params'=>array(
                'label'=>app::get('base')->_('属性参数'),
                'options'=>'',
                'file'=>'admin/goods/detail/params.html',
            ),
        );
        if ($sections)
        foreach($sections as $key=>$section){
            if (!isset($prototype['setting']['use_'.$key]) || ($prototype['setting']['use_'.$key] && !empty($prototype[$key]))){
                if(method_exists($this,($func = '_editor_'.$key))){
                    $this->$func();
                }
                $this->pagedata['sections'][$key] = $section;
            }
        }
        $this->pagedata['goods']['type']['type_id'] = $type_id;
        if($this->pagedata['goods']['spec']){ // || $prototype['spec']
            $prototype['setting']['use_spec'] = 1;
            if(!$this->pagedata['goods']['products']){
                $this->pagedata['goods']['products'] = array(1);
            }
        }

        $this->pagedata['goods']['type'] = $prototype;
/*}}}*/
        $this->pagedata['point_setting'] = $this->app->getConf('point.get_policy');
        $this->pagedata['url'] = str_replace('\\','/',dirname($_SERVER['PHP_SELF']));
//        $memberLevel = $this->app->model('member_lv');
//        $this->pagedata['mLevels'] = $memberLevel->getList('member_lv_id,dis_count');
        $oTag = app::get('desktop')->model('tag');
        $this->pagedata['tagList'] = $oTag->getList('*',array('tag_mode'=>'normal','tag_type'=>'goods'),0,-1);
//        $oStorager = $this->app->model('system/storager');
//        $this->pagedata['max_upload'] = $oStorager->get_pic_upload_max();
    }

    function _prepareGoodsData( &$data ){
        $goods = $data['goods'];

        # 可视状态
        $goods['visibility'] = $goods['visibility']=='false' ? 'false' : 'true' ;

        //$goods['adjunct'] = $data['adjunct'];
        //$goods['image_default_id'] = $data['image_default'];
        foreach( explode( '|', $data['keywords']) as $keyword ){
            $goods['keywords'][] = array(
                'keyword' => $keyword,
                'res_type' => 'goods'
            );
        }
        if( $goods['spec'] ){
            $goods['spec'] = $this->_parse_serialized_array($goods['spec'], null);
            if( !is_array($goods['spec']) ){
                $goods['spec'] = null;
            }
        }else{
            $goods['spec'] = null;
        }
        //处理配件

        if( !$goods['min_buy'] )unset( $goods['min_buy'] );
        if( !$goods['brand']['brand_id'] ) $goods['brand']['brand_id']=null;
//        $images = array();
//        foreach( (array)$goods['images'] as $imageId ){
//            $images[] = array(
//                'target_type'=>'goods',
//                'image_id'=>$imageId,
//                );
//        }
//        $goods['images'] = $images;
//        unset($images);
        if(isset($goods['adjunct']['name'])){
           foreach($goods['adjunct']['name'] as $key => $name){
                $aItem['name'] = $name;
                $aItem['type'] = $goods['adjunct']['type'][$key];
                $aItem['min_num'] = $goods['adjunct']['min_num'][$key];
                $aItem['max_num'] = $goods['adjunct']['max_num'][$key];
                $aItem['set_price'] = $goods['adjunct']['set_price'][$key];
                $aItem['price'] = $goods['adjunct']['price'][$key];
                if($aItem['type'] == 'goods') $aItem['items']['product_id'] = $goods['adjunct']['items'][$key];
                else $aItem['items'] = $goods['adjunct']['items'][$key];//.'&dis_goods[]='.$aData['goods_id']
                $aAdj[] = $aItem;
            }
        }
        $goods['adjunct'] = $aAdj;

        $goods['product'][key($goods['product'])]['default'] = '1';
        foreach( $goods['product'] as $prok => $pro ){
            if( !$pro['product_id'] || substr( $pro['product_id'],0,4 ) == 'new_' )
                unset( $goods['product'][$prok]['product_id'] );
            if( $pro['status'] != 'true' )
                $goods['product'][$prok]['status'] = 'false';
            $mprice = array();
            if( $pro['weight'] === '' )
                $goods['product'][$prok]['weight'] = '0';
            if( $pro['store'] === '' )
                $goods['product'][$prok]['store'] = null;
          //  foreach( (array)$pro['price']['member_lv_price'] as $mLvId => $mLvPrice )
//                if( $mLvPrice )
//                    $mprice[] = array( 'level_id'=>$mLvId,'price'=>$mLvPrice );
            //$goods['product'][$prok]['price']['member_lv_price'] = $mprice;
            foreach( array('mktprice','cost','price') as $pCol ){
                if( !$pro['price'][$pCol]['price'] && $pro['price'][$pCol]['price'] !== 0 ){
                    $goods['product'][$prok]['price'][$pCol]['price'] = '0';
                }
            }
            $goods['product'][$prok]['unit'] = $goods['unit'];

            # 可视状态
            $goods['product'][$prok]['visibility'] = $goods['visibility'];

            // 去前后空格
            $goods['product'][$prok]['bn'] = trim($pro['bn']);
        }
        if( !$goods['tag'] ) $goods['tag'] = array();
        return $goods;
    }
    
    function toAdd(){
        #获取操作类型，检测是不是属于编辑操作
        $type = $_GET['type'];
        foreach ($_POST['goods']['product'] as $k=>$v){
        	$_POST['goods']['product'][$k]['bn'] = trim($v['bn']);
        	$_POST['goods']['product'][$k]['barcode'] = trim($v['barcode']);
        }
        $_POST['goods']['name'] = trim($_POST['goods']['name']);
        
        $url = 'index.php?app=ome&ctl=admin_goods_editor&act=add';
        $autohide = array("autohide"=>5000);
        //$this->begin($gotourl);
        $goods = $this->_prepareGoodsData($_POST);
        foreach($goods['product'] as $val){
            $bnRet = $this->app->model('products')->checkProductBn($val['bn']);
            if(!$bnRet['success']) {
                $this->splash('error',$url, $bnRet['msg'],'',$autohide);
            }
        }
        if( count( $goods['product'] ) == 0 ){
            //echo '货品未添加';
            $this->splash('error',$url,'货品未添加','',$autohide);
            //$this->end(false,'货品未添加',$gotourl);
            exit;
        }
        $oGoods = $this->app->model('goods');
        #属于编辑的操作类型时
        $difff = null;//计划删除的product_id
        if('update' == $type){
            $obj_products = $this->app->model('products');//已不会调用该表和这段代码pdts，废弃 xiayuanjun
            #同一个product_id，如果货号发生改变,后台程序需判断货号是否可以修改
            foreach($goods['product'] as $key=>$_newProduct){
                if(isset($_newProduct['product_id'])){
                    $_oldbn = $obj_products->dump($_newProduct['product_id'],'bn');
                    if($_oldbn ['bn'] != $_newProduct['bn']){
                        #新老货号不一样，则需要判断货号是否可以修改
                        $rs = $this->checkedBn($_oldbn['bn']);
                        if(!$rs['result']){
                            $this->begin('index.php?app=ome&ctl=admin_goods_editor&act=add');
                            $this->end(false,__($rs['msg']));exit;
                        }
                    }
                }
            }
            #获取货品表的数据
            $_productData = utils::apath($goods,array('product'));
            #编辑前，存储在session中的product_id数组
            //$old_product_id = $_SESSION['product_id_key'];
            //unset($_SESSION['product_id_key']);
            $old_product_id = array();
            if ($goods['goods_id']) {
                $tmp = $this->app->model('products')->getList('product_id',array('goods_id'=>$goods['goods_id']));
                foreach ($tmp as $key=>$value) {
                    $old_product_id[] = $value['product_id'];
                }
            }

            #编辑后，新的product_id数组
            $new_product_id = array_keys($_productData);
            #不等于0，意味着已经开启规格或者准备开启规格
            if(0 != $new_product_id[0] ){
                #比较编辑前后的信息,经编辑后，$difff是计划删除的product_id
                $difff = array_diff($old_product_id,$new_product_id);
                #计划删除对应product_id货品前，验证判断相关表
                if(!empty($difff))
                    foreach($difff as $product_id){
                        #根据product_id,检测库存是否存在,并同时获取货号,如果库存大于0，则货品不能删除
                        $storeInfo = $oGoods->checkStoreById($product_id);
                        if($storeInfo['store']>0){
                            $this->begin('index.php?app=ome&ctl=admin_goods_editor&act=add');
                            $this->end(false,__("货号".$storeInfo['bn']."有库存，不能删除！"));
                            break;
                        }
                        #根据货号bn，检测出入库明细，如果存在货号对应数据，则货品不能删除
                        $iostockInfo = $oGoods->checkIostockByBn( $storeInfo['bn']);
                        if($iostockInfo['num']>0){
                            $this->begin('index.php?app=ome&ctl=admin_goods_editor&act=add');
                            $this->end(false,__("货号".$storeInfo['bn']."已经产生出入库明细，不能删除！"));
                            break;
                        }
                        #根据货号，检测对应订单数据，如果数据存在，则货品不能删除
                        $orderInfo = $oGoods->checkOrderByBn($storeInfo['bn']);
                        if($orderInfo['num']>0){
                            $this->begin('index.php?app=ome&ctl=admin_goods_editor&act=add');
                            $this->end(false,__("货号".$storeInfo['bn']."存在关联订单，不能删除！"));
                            break;
                        }
                    }
                } elseif (!$goods['spec'] && count($old_product_id)>1) {
                    $difff = array_diff($old_product_id,$new_product_id);
                    $goods['spec_desc'] = null;
                }
           }
        $ret = array();
        $bar = array();
       // $oGoods = $this->app->model('goods');
        if($oGoods->checkProductBn($goods['bn'], $goods['goods_id'])){
            $this->begin('index.php?app=ome&ctl=admin_goods_editor&act=add');
            $this->end(false,__('您所填写的商品编号已被使用，请检查！'));
        }
        if($oGoods->checkBarcode($goods['barcode'], $goods['goods_id'])){
            $this->begin('index.php?app=ome&ctl=admin_goods_editor&act=add');
            $this->end(false,__('您所填写的商品条形码已被使用，请检查！'));
        }

        $separate = $this->app->getConf('ome.product.barcode.separate');
        if(strpos($goods['barcode'], $separate) !== false){
            $this->begin('index.php?app=ome&ctl=admin_goods_editor&act=add');
            $this->end(false,__('您所填写的商品条形码存在系统分隔符，请检查！'));
        }
        $barcode = array();#记录前端提交的条形码是否存在重复
        $i = 0;
        foreach($_POST['goods']['product'] as $k=>$v){
            $v['bn'] = trim($v['bn']);
            $i++;
            if($v['bn'] == ''){
                 $this->begin('index.php?app=ome&ctl=admin_goods_editor&act=add');
                 $this->end(false,app::get('base')->_('货号不能为空，请检查！'));
            }
            $_weight =  kernel::single('ome_goods_product')->valiPositive($v['weight']);
            if(empty($_weight)){
                $goods['product'][$k]['weight'] = 0;
            }
            if($oGoods->checkProductBn($v['bn'], $goods['goods_id'])){
                $this->begin('index.php?app=ome&ctl=admin_goods_editor&act=add');
                $this->end(false,app::get('base')->_('您所填写的货号已被使用，请检查！'));
                //echo '货号不可以重复!';
                //$this->splash('error',$url,'货号不可以重复','',$autohide);
                //$this->end(false,'货号不可以重复!',$gotourl);
            }else{
                $ret[$v['bn']] = $v['bn'];
            }
            if($oGoods->checkBarcode($v['barcode'], $goods['goods_id'])){
                $this->begin('index.php?app=ome&ctl=admin_goods_editor&act=add');
                $this->end(false,app::get('base')->_('您所填写的条形码已被使用，请检查！'));
            }
            if(strpos($v['barcode'], $separate) !== false){
                $this->begin('index.php?app=ome&ctl=admin_goods_editor&act=add');
                $this->end(false,__('您所填写的商品条形码存在系统分隔符，请检查！'));
            }
            #检测重复条形码
            if($i>0){
                if(!empty($v['barcode'])){
                    if(array_search($v['barcode'], $barcode) !== false){
                        $this->begin('index.php?app=ome&ctl=admin_goods_editor&act=add');
                        $this->end(false,__("条形码{$v['barcode']}重复，请检查！"));
                    }
                }
            }
            $barcode[] = $v['barcode'];
            foreach(kernel::servicelist('ome.product') as $name=>$object){
                if(method_exists($object, 'checkProductByBn')){
                    $checkBn = $object->checkProductByBn($v['bn']);
                    if((!$v['product_id'] && $checkBn) || ($v['product_id'] && $checkBn && $checkBn != $v['product_id'])){
                        $this->begin('index.php?app=ome&ctl=admin_goods_editor&act=add');
                        $this->end(false,app::get('base')->_('您所填写的货号已被其它商品模块使用！'));
                        break;
                    }
                }
            }
            if ($k==0)
            $goods['barcode'] = $v['barcode'];
        }
        if(!empty($goods['spec'])){
            $goods['barcode'] = null;
        }
        if(count($ret) != count($_POST['goods']['product'])){
            $this->begin('index.php?app=ome&ctl=admin_goods_editor&act=add');
            $this->end(false,app::get('base')->_('您所填写的货号存在重复，请检查！'));
        }

      $goods_detail = $oGoods->dump(array('name'=>trim($goods['title'])),'*');
      if($goods_detail){

          //echo '商品名称已存在!';
          $this->splash('error',$url,'商品名称已存在','',$autohide);
      }

        //增加商品保存前的扩展
        foreach(kernel::servicelist('ome.goods') as $o){
            if(method_exists($o,'pre_save')){
                $o->pre_save($goods);
            }
        }
        if ($goods['goods_id']) {
            $log_memo = $oGoods->dump($goods['goods_id'],'*','default');
            $log_memo = serialize($log_memo);
            $log_operation = 'goods_modify@ome';
        }else{
            $log_memo = '添加商品';
            $log_operation = 'goods_add@ome';
        }
        //$oGoods->save($goods);
        $oGoods->saveGoods($goods,$difff);
        
        #
        # 商品日志添加
        
        $opObj  = app::get('ome')->model('operation_log');
        $opObj->write_log($log_operation, $goods['goods_id'], $log_memo);
        //增加商品保存后的扩展
        foreach(kernel::servicelist('ome.goods') as $o){
            if(method_exists($o,'after_save')){
                $o->after_save($goods);
            }
        }

        header('Content-Type:text/jcmd; charset=utf-8');
        ///$this->splash('success','index.php?app=ome&ctl=admin_goods','商品添加成功');
        echo '{success:"添加成功",_:null,goods_id:"'.$goods['goods_id'].'"}';
    }
    #点击开启规格时，使用异步方式检测相关数据
    function checkRelationInfo(){
        $bn = $_POST['bn'];
        $oGoods = $this->app->model('goods');
        #根据货号,检测库存，如果库存大于0，则货品不能再开启规格
        $storeInfo = $oGoods->checkStoreById(null,$bn);
        if($storeInfo['store']>0){
            $msg = "货号 ".$bn." 已经有库存，不能再开启规格！";echo  $msg;exit;
        }
        #根据货号bn，检测出入库明细，如果存在货号对应数据，则货品再开启规格
        $iostockInfo = $oGoods->checkIostockByBn( $bn);
        if($iostockInfo['num']>0){
        $msg =  "货号 ".$bn." 已经存在出入库记录，不能再开启规格！";echo $msg;exit;
        }
        #根据货号，检测对应订单数据，如果数据存在，则货品再开启规格
        $orderInfo = $oGoods->checkOrderByBn($bn);
        if($orderInfo['num']>0){
            $msg =  "货号 ".$bn." 已经存在关联订单，不能再开启规格！";echo $msg;exit;
        }
    }
    function edit($goods_id){
        header('Content-type: text/html; charset=utf-8');
        $this->goods_id = $goods_id;
        $oGoods = $this->app->model('goods');
        $goods = $oGoods->dump($goods_id,'*','default');
         if (is_array($goods['spec'])) {
            ksort($goods['spec']);
        }
       
        
        // 如果销售价为NULL，则赋值0
        $pbnList = array();
        foreach ($goods['product'] as $pid=>$product) {
            if (is_null($product['price']['price']['price']))  $goods['product'][$pid]['price']['price']['price'] = '0.000';

            $pbnList[] = $product['bn'];
        }
        
        if ($goods['spec']) {
            $goods['canCloseSpec'] = 'true';
            $iostockBn = $oGoods->iostockExsit($pbnList);
            foreach ($goods['product'] as $pid => $product) {
                $canChange = 'true';
                if ($product['store'] > 0 || $product['store_freeze'] > 0) {
                    $canChange = 'false';
                    $goods['canCloseSpec'] = 'false';
                }
                
                if (in_array($product['bn'],$iostockBn)) {
                    $canChange = 'false';
                    $goods['canCloseSpec'] = 'false';
                }

                $goods['product'][$pid]['canChange'] = $canChange;
            }
        }else{
            #检测货号是不是可以再编辑
            $msg = $this->checkedGoodsInfo($pbnList[0]);
            if($msg == 'true'){
                $this->pagedata['do_edit'] = 'true';#可编辑
            }else{
                $this->pagedata['do_edit'] = $msg;#不可编辑，提示原因
            }
        }
        $oGoods->checkIsUse($goods);
        
        #获取货品表的数据
        $_productData = utils::apath($goods,array('product'));
        #把product_id存到session中
        $_SESSION['product_id_key'] = array_keys($_productData);

        //$oImageAttach = app::get('image')->model('image_attach');
        //$images = $oImageAttach->get_list($goods_id);
        $this->_editor($goods['type']['type_id']);

        $this->pagedata['goods'] = array_merge($goods,$this->pagedata['goods']);
        $this->pagedata['app_dir'] = app::get('ome')->app_dir;
        $this->pagedata['goods_id'] = $goods_id;
        $this->pagedata['title'] = '编辑商品';
        $this->pagedata['type'] = 'update';

        #新增货品，不需要去验证，即可开启规格；当编辑商品，点击开启规格按钮，需要经过验证才能开启
        $this->pagedata['goods']['_typeInfo'] =  'update';
        $this->singlepage('admin/goods/detail/frame.html');
    }

    function _set_type_spec($typeId){
        $oGtype = $this->app->model('goods_type');
        $spec = $oGtype->dump($typeId,'type_id',array(
                'spec'=>array('spec_id',
                    array(
                        'spec:specification'=>array('*',
                            array(
                                'spec_value' =>array('*')
                            )
                        )
                    )
                )
            )
        );
        if(is_array($spec)){
            $this->pagedata['spec'] = next($spec);
        }

    }

    function _set_spec($spec){
        $oSpec = $this->app->model('specification');
        $subSdf = array(
            'spec_value' =>array('*')
        );
        $this->pagedata['spec'] = $oSpec->batch_dump( array('spec_id'=>array_keys($spec)), '*' , $subSdf, 0 ,-1 );

        $this->pagedata['goods_spec'] = $spec;
    }

    private function _parse_serialized_array($data, $default = array()){
        if( is_array($data) ){
            return $data;
        }
        if( !is_string($data) || $data === '' ){
            return $default;
        }
        $parsed = @unserialize($data, array('allowed_classes' => false));
        return is_array($parsed) ? $parsed : $default;
    }

    function set_spec($typeId=0){
        $_POST['spec'] = $this->_parse_serialized_array(isset($_POST['spec']) ? $_POST['spec'] : '');
        if( $_POST['spec'] ){
            $this->_set_spec($_POST['spec']);
        }else{
            $this->_set_type_spec($typeId);
        }
        $this->display('admin/goods/detail/spec/set_spec.html');
    }

    function set_spec_desc(){
        $spec = $_POST['spec'];
        $spec[$_POST['addSpecId']] = null;
        $this->_set_spec( $spec );


        $this->display('admin/goods/detail/spec/set_spec_desc.html');
    }

    function addSpecValue(){
        $_POST = utils::stripslashes_array($_POST);
        $specValue = array(
            'spec_type' => $_POST['specType'],
            'spec_value' => array(
                'spec_value_id' => $_POST['specValueId'],
                'spec_value' => $_POST['specValue'],
                'private_spec_value_id'=>time().$_POST['sIteration'],
                'spec_image'=>$_POST['specImage'],
                'spec_goods_images'=>$_POST['specGoodsImages']
            )
        );
        $this->pagedata['aSpec'] = array(
            'spec_type' => $_POST['spec']['specType'],
            'spec_id' => $_POST['spec']['specId']
        );
        $this->pagedata['specValue'] = array(
            'spec_value_id' => $_POST['spec']['specValueId'],
            'spec_value' => $_POST['spec']['specValue'],
            'private_spec_value_id'=>time().$_POST['sIteration'],
            'spec_image'=>$_POST['spec']['specImage'],
            'spec_goods_images'=>$_POST['spec']['specGoodsImages']
        );

        $this->pagedata['spec_default_pic'] = $this->app->getConf('spec.default.pic');
        $this->display('admin/goods/detail/spec/spec_value.html');
    }

	 function doAddSpec(){
        //$oImage = app::get('image')->model('image');//fetch($_POST['']);

        $this->pagedata['goods']['spec'] = &$_POST['spec'];
        if( $_GET['create'] == 'true' ){
            $pro = $this->_doCreatePro( $pro, $_POST['spec'] );
            $this->pagedata['fromType'] = 'create';
            $this->pagedata['goods']['product'] = $pro;
        }
        $this->_set_spec( $_POST['spec'] );
        $this->pagedata['spec_tmpl'] = $this->pagedata['spec'];
        $this->pagedata['needUpValue'] = json_encode($_POST['needUpValue']);
//        $this->pagedata['spec_default_pic'] = $this->app->getConf('spec.default.pic');
        //$memberLevel = $this->app->model('member_lv');
        //$this->pagedata['mLevels'] = $memberLevel->getList('member_lv_id,dis_count');
        $this->pagedata['app_dir'] = app::get('ome')->app_dir;

        $this->pagedata['spec_default_pic'] = $this->app->getConf('spec.default.pic');

        $this->display('admin/goods/detail/spec/spec.html');
    }

    function _doCreatePro( $pro, $spec ){
        if( empty( $spec ) ){
            $res = array();
            foreach( $pro as $pk => $pv ){
                foreach( $pv as $pvk => $pvv ){
                    $res['new_'.$pk]['spec_desc']['spec_value'][$pvv['spec_id']] = $pvv['spec_value'];
                    $res['new_'.$pk]['spec_desc']['spec_private_value_id'][$pvv['spec_id']] = $pvv['private_spec_value_id'];
                    $res['new_'.$pk]['spec_desc']['spec_value_id'][$pvv['spec_id']] = $pvv['spec_value_id'];
                }
            }
            return $res;
        }
        $firstSpec = array_shift( $spec );

        $rs = array();
        foreach( $firstSpec['option'] as $sitem ){
            foreach( (array)$pro as $pitem ){
                $apitem = $pitem ;
                array_push( $apitem , array('spec_id'=>$firstSpec['spec_id']) + $sitem );
                $rs[] = $apitem;
            }
            if( empty($pro) )
                $rs[] = array( array_merge( array('spec_id'=>$firstSpec['spec_id']) , $sitem) );
        }
       return $this->_doCreatePro( $rs, $spec );
    }


    function update(){
        $goods = $this->_prepareGoodsData($_POST);
        $oType = $this->app->model('goods_type');
        $goods['type'] = $oType->dump($goods['type']['type_id'],'*');
        //unset($goods['spec'],$goods['product']);
        $this->_editor($goods['type']['type_id']);
        $this->pagedata['goods'] = $goods;
        $this->display('admin/goods/detail/page.html');
    }

    function addGrp(){
        $this->pagedata['aOptions'] = array('goods'=>app::get('base')->_('选择几件商品作为配件'), 'filter'=>app::get('base')->_('选择一组商品搜索结果作为配件'));
        $this->display('admin/goods/detail/adj/info.html');
    }

    function doAddGrp(){
        $this->pagedata['adjunct'] =array('name'=>$_POST['name'],'type'=>$_POST['type']);
        $this->pagedata['key'] = time();
        $this->display('admin/goods/detail/adj/row.html');
    }

    function showfilter($type_id){
        $obj = $this->app->model('goods');
        $this->pagedata['filter'] = $obj->getFilterByTypeId(array('type_id'=>$type_id));
        $this->pagedata['filter_interzone'] = $_POST;
        $this->pagedata['view'] = $_POST['view'];
        $this->display('admin/goods/filter_addon.html');
    }

    function set_mprice(){
//        $memberLevel = $this->app->model('member_lv');
//        foreach($memberLevel->getList('member_lv_id,name,dis_count,name') as $level){
//            $level['dis_count'] = ($level['dis_count']>0 ? $level['dis_count'] : 1);
//            $level['price'] = $_POST['level'][$level['member_lv_id']];
//            $this->pagedata['mPrice'][$level['member_lv_id']] = $level;
//        }
//        $this->display('admin/goods/detail/level_price.html');
    }

    function show_history($log_id) {
        $logObj = app::get('ome')->model('operation_log');
        $oCat = app::get('ome')->model('goods_cat');
        $oType = app::get('ome')->model('goods_type');
        $oBrand = app::get('ome')->model('brand');
        $goodslog = $logObj->dump($log_id,'memo');
        $memo = $this->_parse_serialized_array($goodslog['memo']);
        $cat = $oCat->dump($memo['category']['cat_id'],'cat_name');
        $memo['cat_name'] = $cat['cat_name'];
        $type = $oType->dump($memo['type']['type_id'],'name');
        $memo['type_name'] = $type['name'];
        $brand = $oBrand->dump($memo['brand']['brand_id'],'brand_name');
        $memo['brand_name'] = $brand['brand_name'];

        $this->pagedata['goods'] = $memo;
        unset($goodslog);
        $this->singlepage('admin/goods/detail/history_log.html');
    }
    #判断货号是否可以再编辑
    function checkedGoodsInfo($bn = null){
        #检测商品有没有生成订单记录
        $oGoods = $this->app->model('goods');
        $_orderInfo = $oGoods->checkOrderByBn($bn);
        if($_orderInfo['num']>0){
            return $msg = "该商品存在关联订单，货号不能再编辑";
        }
        #检测商品有没有生成出入库明细记录
        $_iostockInfo = $oGoods->checkIostockByBn($bn);
        if($_iostockInfo['num']>0){
            return $msg = "该商品存在出入库明细，货号不能再编辑";
        }
        
        #检测商品有没有采购记录
        $_purchaseInfo = $oGoods->checkPurchaseByBn($bn);
        if($_purchaseInfo['num']>0){
            return $msg = "该商品存在采购记录，货号不能再编辑";
        }
        #检测商品有没有盘点记录
        $_inventoryInfo = $oGoods->checkInventoryByBn($bn);
        if($_inventoryInfo['num']>0){
            return $msg = "该商品存在盘点记录，货号不能再编辑";
        }
        return 'true';
    }
    #后台判断货号是否可以再编辑
    function checkedBn($bn = null){
        $rs['result'] = true;
        
        #检测货品有没有生成订单记录
        $oGoods = $this->app->model('goods');
        $_orderInfo = $oGoods->checkOrderByBn($bn);
        if($_orderInfo['num']>0){
            $rs['result'] = false;
            $rs['msg'] = '货号'.$bn.'存在关联订单，不能再编辑';
            return $rs; 
        }
        #检测货品有没有生成出入库明细记录
        $_iostockInfo = $oGoods->checkIostockByBn($bn);
        if($_iostockInfo['num']>0){
            $rs['result'] = false;
            $rs['msg'] = '货号'.$bn.'存在出入库明细，不能再编辑';
            return $rs;
        }
        
        #检测货品有没有采购记录
        $_purchaseInfo = $oGoods->checkPurchaseByBn($bn);
        if($_purchaseInfo['num']>0){
            $rs['result'] = false;
            $rs['msg'] = '货号'.$bn.'存在采购记录，不能再编辑';
            return $rs;
        }
        #检测货品有没有盘点记录
        $_inventoryInfo = $oGoods->checkInventoryByBn($bn);
        if($_inventoryInfo['num']>0){
            $rs['result'] = false;
            $rs['msg'] = '货号'.$bn.'存在盘点记录，不能再编辑';
            return $rs;
        }
        return $rs;
    }
}
