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
 * 基础物料控制层
 *
 * @author kamisama.xia@gmail.com
 * @version 0.1
 */
class material_ctl_admin_material_cat extends desktop_controller
{

    public $workground  = 'goods_manager';
    public $view_source = 'normal';

    /**
     * __construct
     * @param mixed $app app
     * @return mixed 返回值
     */

    public function __construct($app)
    {
        parent::__construct($app);
        header("cache-control: no-store, no-cache, must-revalidate");
    }

    /**
     * index
     * @return mixed 返回值
     */
    public function index()
    {
        $objCat = $this->app->model('basic_material_cat');
        $objCat->init_data(); //初始化分类数据
        if ($objCat->checkTreeSize()) {
            $this->pagedata['hidenplus'] = true;
        }
        // exit('sds');
        $tree                          = $objCat->get_cat_list();
        $this->pagedata['tree_number'] = count($tree);
        if ($tree) {
            foreach ($tree as $k => $v) {
                $tree[$k]['link'] = array('cat_id' => array(
                    'v' => $v['cat_id'],
                    't' => app::get('ome')->_('类别') . app::get('ome')->_('是') . $v['cat_name'],
                ));
            }
        }
        //var_dump($tree);
        // echo "<pre>";print_r($tree);exit('sdsd');

        $this->pagedata['tree']   = $tree;
        $depath                   = array_fill(0, $objCat->get_cat_depth(), '1');
        $this->pagedata['depath'] = $depath;
        $this->page('admin/material/category/map.html');
    }

    /**
     * 获取_cat_prop
     * @return mixed 返回结果
     */
    public function get_cat_prop()
    {
        $cat_id = intval($_GET['cat_id']);

        $catObj         = $this->app->model('basic_material_cat');
        $propertyObj    = $this->app->model('basic_material_prop');
        $propertyValObj = $this->app->model('basic_material_prop_value');

        $rs         = $catObj->dump($cat_id);
        $cat_path   = explode(',', $rs['cat_path']);
        $cat_path[] = $cat_id;

        $all_cat = array();
        $rs      = $catObj->getList('*', array('cat_id' => $cat_path));
        foreach ($rs as $v) {
            $all_cat[$v['cat_id']] = $v;
        }

        //属性值
        $parentPropetyList = array();
        $propetyList       = array();
        $dataList          = $propertyObj->getList('*', array('cat_id' => $cat_path), 0, -1, 'prop_key ASC');
        if ($dataList) {
            $li = 0;
            foreach ($dataList as $key => $val) {
                $li++;
                $prop_id   = $val['prop_id'];
                $prop_key  = $val['prop_key'];
                $prop_name = 'prop' . $prop_key . '_value'; //对应商品表属性字段名

                $val['edit_del'] = ($val['is_lock'] ? false : true); //锁定状态不允许删除

                $proValList = array();
                $tempData   = $propertyValObj->getList('*', array('prop_id' => $prop_id));
                if ($tempData) {
                    foreach ($tempData as $proKey => $proVal) {
                        $proValList[$proVal['value_id']] = $proVal['value_name'];
                    }

                    $propetyList[$li]              = $val;
                    $propetyList[$li]['select']    = $proValList;
                    $propetyList[$li]['selectVal'] = implode(',', $proValList);
                }
            }

            $this->pagedata['parentPropetyList'] = $parentPropetyList;
            $this->pagedata['propetyList']       = $propetyList;
            $this->pagedata['item_propety_num']  = count($propetyList);
        }

        if ($_GET['type'] == 'topsales') {
            $this->display('admin/material/category/topsales_get_prop.html');
        } else {
            $this->display('admin/material/category/get_prop.html');
        }
    }

    /**
     * 获取_cat_select
     * @return mixed 返回结果
     */
    public function get_cat_select()
    {
        $cat_id    = intval($_GET['cat_id']);
        $cat_ids   = array();
        $categorys = array();
        $objCat    = $this->app->model('basic_material_cat');

        //获取分类
        $sql = "select cat_id from sdb_material_basic_material_prop
                where cat_id>{$cat_id} or cat_id<{$cat_id}
                group by cat_id ";
        $rs = $objCat->db->select($sql);
        foreach ($rs as $v) {
            $cat_ids[] = $v['cat_id'];
        }

        if ($cat_ids) {
            $rs = $objCat->getList('cat_id,cat_name', array('cat_id' => $cat_ids), 0, -1);
            foreach ($rs as $v) {
                $categorys[$v['cat_id']] = $v['cat_name'];
            }
        }

        $this->pagedata['categorys'] = $categorys;
        $this->page('admin/material/category/select_cat.html');
    }

    //复制分类的扩展属性，暂时没用上
    /**
     * copy_cat_prop
     * @return mixed 返回值
     */
    public function copy_cat_prop()
    {
        $cat_id         = intval($_GET['cat_id']);
        $new_propety_i  = intval($_GET['new_propety_i']);
        $propertyObj    = $this->app->model('basic_material_prop');
        $propertyValObj = $this->app->model('basic_material_prop_value');

        //属性值
        $propetyList = array();
        $dataList    = $propertyObj->getList('*', array('cat_id' => $cat_id), 0, -1, 'prop_key ASC');
        if ($dataList) {
            $li = 0;
            foreach ($dataList as $key => $val) {
                $li++;
                $prop_id   = $val['prop_id'];
                $prop_key  = $val['prop_key'];
                $prop_name = 'prop' . $prop_key . '_value'; //对应商品表属性字段名

                $val['edit_del'] = ($val['is_lock'] ? false : true); //锁定状态不允许删除

                $proValList = array();
                $tempData   = $propertyValObj->getList('*', array('prop_id' => $prop_id));
                if ($tempData) {
                    foreach ($tempData as $proKey => $proVal) {
                        $proValList[] = $proVal['value_name'];

                        //todo 判断属性值是否已被使用,如使用不允许编辑
                        /*
                    if($val['edit_del']){
                    $params = array('cate_id'=>$cate_id, 'prop_id'=>$prop_id, 'prop_key'=>$prop_key, 'prop_value'=>$proVal['value_name']);
                    $is_use = $productLib->checkPropertyUse($params, $error_msg);
                    if($is_use){
                    $val['edit_del'] = false;
                    }
                    }*/
                    }

                    $propetyList[$li]              = $val;
                    $propetyList[$li]['select']    = $proValList;
                    $propetyList[$li]['selectVal'] = implode(',', $proValList);
                }
            }

            $html = array();
            foreach ($propetyList as $v) {
                $new_propety_i++;
                $item = '';
                $item .= '<td width="90" nowrap="nowrap" align="center">' . $new_propety_i . '<input name="property_ids[]" type="hidden" value="0" /></td>
                          <td align="center"><input name="property_name[]" type="text" size="30" maxlength="30" vtype="required" value="' . $v['prop_name'] . '" ' . (!$v['edit_del'] ? 'readonly="readonly"' : '') . ' /></td>
                          <td><span id="property_select_' . $new_propety_i . '">';
                if ($v['select']) {
                    $item .= '<select name="property_select">';
                    foreach ($v['select'] as $kk => $vv) {
                        $item .= '<option value="' . $kk . '">' . $vv . '</option>';
                    }
                    $item .= '</select>';
                }

                $item .= '</span>
                              &nbsp;<button type="button" prop_id="' . $v['prop_id'] . '" edit_id="' . $new_propety_i . '" class="btn editItem editItem_2 btn-has-icon" app="desktop"><span><span><i class="btn-icon"><img src="/oms-intelligent-platform/app/desktop/statics/bundle/btn_edit.gif" app="desktop"></i>编辑选择项</span></span></button>
                              <input type="hidden" name="property_select_values[]" id="property_select_values_' . $new_propety_i . '" value="' . $v['selectVal'] . '" />
                          </td>
                          <td align="center"><img app="desktop" src="/oms-intelligent-platform/app/desktop/statics/bundle/icon_asc.gif" class="pointer btn-asc btn-asc_2" title="向上移动"> &nbsp; &nbsp; <img app="desktop" src="/oms-intelligent-platform/app/desktop/statics/bundle/icon_desc.gif" class="pointer btn-desc btn-desc_2" title="向下移动"> ';
                if ($v['edit_del']) {
                    $item .= '<img src="/oms-intelligent-platform/app/desktop/statics/bundle/delete.gif" app="desktop" key="state" class="pointer btn-delete-item btn-delete-item_2">';
                }
                $item .= '&nbsp; &nbsp;</td>';

                $html[] = $item;
            }

            echo (json_encode($html));
        }
    }

    //编辑扩展属性
    /**
     * edit_prop
     * @param mixed $cat_id ID
     * @return mixed 返回值
     */
    public function edit_prop($cat_id = 0)
    {
        $catObj         = $this->app->model('basic_material_cat');
        $propertyObj    = $this->app->model('basic_material_prop');
        $propertyValObj = $this->app->model('basic_material_prop_value');

        $rs         = $catObj->dump($cat_id);
        $cat_path   = explode(',', $rs['cat_path']);
        $cat_path[] = $cat_id;

        $all_cat = array();
        $rs      = $catObj->getList('*', array('cat_id' => $cat_path));
        foreach ($rs as $v) {
            $all_cat[$v['cat_id']] = $v;
        }

        //属性值
        $parentPropetyList = array();
        $propetyList       = array();

        $dataList = $propertyObj->getList('*', array('cat_id' => $cat_path), 0, -1, 'cat_id asc,prop_key ASC');
        if ($dataList) {
            $li = 0;
            foreach ($dataList as $key => $val) {
                $prop_id   = $val['prop_id'];
                $prop_key  = $val['prop_key'];
                $prop_name = 'prop' . $prop_key . '_value'; //对应商品表属性字段名

                $val['edit_del'] = ($val['is_lock'] ? false : true); //锁定状态不允许删除

                $proValList = array();
                $tempData   = $propertyValObj->getList('*', array('prop_id' => $prop_id));
                if ($tempData) {
                    foreach ($tempData as $proKey => $proVal) {
                        $proValList[] = $proVal['value_name'];

                        //todo 判断属性值是否已被使用,如使用不允许编辑
                        /*
                    if($val['edit_del']){
                    $params = array('cate_id'=>$cate_id, 'prop_id'=>$prop_id, 'prop_key'=>$prop_key, 'prop_value'=>$proVal['value_name']);
                    $is_use = $productLib->checkPropertyUse($params, $error_msg);
                    if($is_use){
                    $val['edit_del'] = false;
                    }
                    }*/
                    }

                    if ($val['cat_id'] == $cat_id) {
                        $li++;
                        $propetyList[$li]              = $val;
                        $propetyList[$li]['select']    = $proValList;
                        $propetyList[$li]['selectVal'] = implode(',', $proValList);
                    } else {
                        $val['select']       = $proValList;
                        $val['selectVal']    = implode(',', $proValList);
                        $parentPropetyList[] = $val;
                    }
                }
            }

            $this->pagedata['parentPropetyList'] = $parentPropetyList;
            $this->pagedata['propetyList']       = $propetyList;
            $this->pagedata['item_propety_num']  = count($propetyList);
        }

        $this->pagedata['cat_id']  = $cat_id;
        $this->pagedata['all_cat'] = $all_cat;
        $this->display('admin/material/category/edit_prop.html');
    }

    /**
     * 添加_property_select
     * @return mixed 返回值
     */
    public function add_property_select()
    {
        $categoryObj = $this->app->model('basic_material_cat');
        $propertyObj = $this->app->model('basic_material_prop');

        $cate_id                = intval($_POST['cate_id']);
        $prop_id                = intval($_POST['prop_id']);
        $edit_id                = $_POST['edit_id'];
        $property_select_values = $_POST['property_select_values'];

        //已添加的选项列表值
        $select_list = array();
        if ($property_select_values) {
            $select_list = explode(',', $property_select_values);
        }

        //大类属性信息
        $is_lock = false;
        if ($prop_id) {
            $propInfo = $propertyObj->dump(array('prop_id' => $prop_id), '*');
            $cate_id  = $propInfo['cate_id'];
            $is_lock  = $propInfo['is_lock']; //是否已锁定
            $prop_key = $propInfo['prop_key']; //对应商品属性字段名
        }

        //判断是否被商品使用
        if ($select_list) {
            $li = 0;
            foreach ($select_list as $key => $val) {
                $li++;

                //判断属性值是否已被使用,如使用不允许编辑
                $edit_del = true;
                if ($is_lock) {
                    $prop_name = 'prop' . intval($prop_key) . '_value';
                    $sql       = "SELECT product_id FROM sdb_pim_product WHERE cate_id=" . intval($cate_id) . " AND " . $prop_name . "=" . $categoryObj->db->quote($val);
                    $tempInfo  = $categoryObj->db->selectrow($sql);
                    if ($tempInfo) {
                        $edit_del = false;
                    }
                }

                $select_list[$key] = array('value' => $val, 'edit_del' => $edit_del);
            }
        }

        $this->pagedata['edit_id']     = $edit_id;
        $this->pagedata['select_list'] = $select_list;
        $this->page('admin/material/category/add_property_select.html');
    }

    /**
     * 添加new
     * @param mixed $nCatId ID
     * @return mixed 返回值
     */
    public function addnew($nCatId = 0)
    {
        $this->_info($nCatId);
    }

    /**
     * _info
     * @param mixed $id ID
     * @param mixed $type type
     * @return mixed 返回值
     */
    public function _info($id = 0, $type = 'add')
    {
        $objCat          = $this->app->model('basic_material_cat');
        $catList         = $objCat->get_cat_list();
        $res             = $objCat->dump($id);
        $seoCat          = $res['seo_info'];
        $gallery_setting = $res['gallery_setting'];
        /*
        $aCatNull[] = array('cat_id'=>0,'cat_name'=>app::get('b2c')->_('----无----'),'step'=>1);
        if(empty($catList)){
        $catList = $aCatNull;
        }else{
        $catList = array_merge($aCatNull, $catList);
        }*/
        $this->pagedata['catList'] = $catList;

        $aCat                               = $objCat->dump($id);
        $this->pagedata['cat']['parent_id'] = $aCat['cat_id'];
        $this->pagedata['cat']['type_id']   = $aCat['type_id'];
        $this->pagedata['cat']['max_price'] = $aCat['max_price'];
        $this->pagedata['cat']['min_price'] = $aCat['min_price'];
        if ($type == 'edit') {
            $this->pagedata['cat']['cat_id']    = $aCat['cat_id'];
            $this->pagedata['cat']['cat_name']  = $aCat['cat_name'];
            $this->pagedata['cat']['cat_code']  = $aCat['cat_code'];
            $this->pagedata['cat']['parent_id'] = $aCat['parent_id'];
            $this->pagedata['cat']['p_order']   = $aCat['p_order'];
        }
        $this->pagedata['seo_info']        = $seoCat;
        $this->pagedata['gallery_setting'] = $gallery_setting;
        $this->display('admin/material/category/info.html');
    }

    /**
     * 保存
     * @return mixed 返回操作结果
     */
    public function save()
    {
        $this->begin('index.php?app=material&ctl=admin_material_cat&act=index');
        if ($_POST['p_order'] === '') {
            $_POST['p_order'] = 0;
        }

        $_POST['create_time'] = $_POST['last_modify'] = time();
        $cat_data             = $_POST['cat'];

        $objCat = $this->app->model('basic_material_cat');
        #↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓记录管理员操作日志@lujy↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓
        if ($obj_operatorlogs = kernel::service('operatorlog.goods')) {
            $olddata = $objCat->dump($cat_data['cat_id']);
        }

        //$cat_data = utils::addslashes_array($cat_data);
        $cat_data['cat_name'] = utils::safe_input($cat_data['cat_name']);
        $cat_data['parent_id'] = (int) $cat_data['parent_id'];

        //检测分类是否重复
        $rs = $objCat->dump(array('cat_name' => $cat_data['cat_name'],'parent_id'=>$cat_data['parent_id']));
        if ($rs['cat_id'] && $rs['cat_id'] != $cat_data['cat_id']) {
            $this->end(false, app::get('ome')->_($cat_data['cat_name'] . ' 名称不能重复'));
        }

        $min_price = intval($cat_data['min_price']);
        $max_price = intval($cat_data['max_price']);
        if ($max_price > 0 && $max_price < $min_price) {
            $this->end(false, '最高价必须大于最低价');
        }

        #↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑记录管理员操作日志@lujy↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑
        if ($objCat->save($cat_data)) {
            #↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓记录管理员操作日志@lujy↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓
            if ($obj_operatorlogs = kernel::service('operatorlog.goods')) {
                if (method_exists($obj_operatorlogs, 'goodscat_log')) {
                    $obj_operatorlogs->goodscat_log($cat_data, $olddata);
                }
            }
            #↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑记录管理员操作日志@lujy↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑
            $this->end(true, app::get('ome')->_('保存成功'));
        } else {
            $this->end(false, app::get('ome')->_('保存失败'));
        }
    }

    /**
     * toRemove
     * @param mixed $nCatId ID
     * @return mixed 返回值
     */
    public function toRemove($nCatId)
    {
        $this->begin('index.php?app=' . $_GET['app'] . '&ctl=' . $_GET['ctl'] . '&act=index');
        $objCat  = $this->app->model('basic_material_cat');
        $cat_sdf = $objCat->dump($nCatId);

        if ($objCat->toRemove($nCatId, $msg)) {
            $this->end(true, $cat_sdf['cat_name'] . app::get('ome')->_('已删除'));

        }
        $this->end(false, $msg);
    }

    /**
     * edit
     * @param mixed $nCatId ID
     * @return mixed 返回值
     */
    public function edit($nCatId)
    {
        $this->_info($nCatId, 'edit');
    }

    /**
     * 更新
     * @return mixed 返回值
     */
    public function update()
    {
        $this->begin('index.php?app=material&ctl=admin_material_cat&act=index');
        $o = $this->app->model('basic_material_cat');
        foreach ($_POST['p_order'] as $k => $v) {
            $o->update(array('p_order' => ($v === '' ? null : $v)), array('cat_id' => $k));
        }
        $o->cat2json();
        $this->end(true, app::get('ome')->_('操作成功'));
    }

    /**
     * 获取ByStr
     * @return mixed 返回结果
     */
    public function getByStr()
    {
        header('Content-type: application/json');

        $objCat = $this->app->model('basic_material_cat');
        $data   = $objCat->getCatLikeStr($_POST['kw']);

        echo $data;
    }

    /**
     * 获取_subcat_list
     * @param mixed $cat_id ID
     * @return mixed 返回结果
     */
    public function get_subcat_list($cat_id)
    {
        $objCat  = $this->app->model('basic_material_cat');
        $row     = $objCat->dump($cat_id);
        $path_id = explode(',', $row['cat_path']);
        array_shift($path_id);
        array_pop($path_id);
        $path_id[] = $cat_id;
        $cat_path  = array();
        if ($path_id) {
            $filter   = array('cat_id' => $path_id);
            $cat_path = $objCat->getList('*', $filter);
        }
        $list = $objCat->get_subcat_list($cat_id);
        foreach ($list as $key => &$val) {
            if ($val['child_count'] > 0) {
                $val['isParent'] = 'isParent';
            } else {
                //unset($list['isParent']);
            }
        }

        $count                  = $objCat->get_subcat_count($cat_id);
        $this->pagedata['cats'] = json_encode($list);
        $catPath = [];
        if (is_array($cat_path)) {
            foreach ($cat_path as $ck => $cv) {
                $catPath[] = $cv['cat_id'];
            }
        }
        echo json_encode($list);

    }

    /**
     * 获取_subcat
     * @param mixed $cat_id ID
     * @return mixed 返回结果
     */
    public function get_subcat($cat_id)
    {
        if (empty($cat_id)) {
            $cat_id = 0;
        }

        $objCat  = $this->app->model('basic_material_cat');
        $row     = $objCat->dump($cat_id);
        $path_id = explode(',', $row['cat_path']);
        array_shift($path_id);
        array_pop($path_id);
        $path_id[] = $cat_id;
        $cat_path  = array();
        if ($path_id) {
            $filter   = array('cat_id' => $path_id);
            $cat_path = $objCat->getList('*', $filter);
        }
        $list = $objCat->get_subcat_list(0);
        foreach ($list as $key => &$val) {
            if ($val['child_count'] > 0) {
                $val['isParent'] = 'isParent';
            } else {
                //unset($list['isParent']);
            }
        }
        $newCat = $objCat->get_new_cat(10);
        foreach ($newCat as $nk => &$nv) {
            $nv['cat_path'] = substr($nv['cat_path'], 1);
            $nv['cat_path'] = $nv['cat_path'] . $nv['cat_id'];
        }

        //     error_log(var_export($list,true),3,'c:/dd.txt');

        $count                  = $objCat->get_subcat_count($cat_id);
        $list[]['cat_id']       = 0;
        $list[]['cat_name']     = '分类不限';
        $this->pagedata['cats'] = json_encode($list);
        $catPath = [];
        if (is_array($cat_path)) {
            foreach ($cat_path as $ck => $cv) {
                $catPath[] = $cv['cat_id'];
            }
        }
        $this->pagedata['catPath'] = implode(',', $catPath);
        $this->pagedata['newCat']  = $newCat;

        $this->display('admin/material/category/cat_list.html');
    }

    //保存扩展属性
    /**
     * 保存_prop
     * @return mixed 返回操作结果
     */
    public function save_prop()
    {
        $this->begin('index.php?app=material&ctl=admin_material_cat&act=index');

        $categoryObj    = $this->app->model('basic_material_cat');
        $propertyObj    = $this->app->model('basic_material_prop');
        $propertyValObj = $this->app->model('basic_material_prop_value');

        $productLib = kernel::single('material_basic_prop');

        //post
        $cat_id = intval($_POST['cat_id']);

        //扩展属性
        $property_ids           = $_POST['property_ids'];
        $property_names         = $_POST['property_name'];
        $property_auto_create   = $_POST['property_auto_create'];
        $property_select_values = $_POST['property_select_values'];

        if (!$property_names) {
            $property_names = array();
            //$this->end(false, app::get('base')->_('请添加扩展属性'));
        }

        //check property
        $property_list = array();
        $prop_id_list  = array();
        $prop_key      = 0;
        foreach ($property_names as $key => $val) {
            $property_name = trim($val);

            $prop_key++;

            //property_select
            $property_select = array();
            $temp_data       = trim($property_select_values[$key]);
            $auto_create     = intval($property_auto_create[$key]);
            if (empty($temp_data)) {
                $this->end(false, app::get('base')->_('属性名称：' . $property_name . '，关联的选项值不能为空'));
            }

            $select_data = explode(',', $temp_data);
            foreach ($select_data as $selKey => $selVal) {
                $selVal            = trim($selVal);
                $property_select[] = str_replace(',', '', $selVal);
            }

            //check property
            if (empty($property_name)) {
                $this->end(false, app::get('base')->_('属性名称不能为空'));
            }

            //之前已有的属性prop_id,只做更新
            $prop_id = intval($property_ids[$key]);
            if ($prop_id) {
                $prop_id_list[] = $prop_id;
            }

            //format
            $property_list[] = array(
                'prop_id'     => $prop_id,
                'prop_name'   => $property_name,
                'prop_select' => $property_select,
                'order_num'   => intval($key),
                'prop_key'    => $prop_key,
                'auto_create' => $auto_create,
                'is_lock'     => 0,
            );
        }

        //edit
        if ($cat_id) {
            $cateInfo = $categoryObj->dump(array('cat_id' => $cat_id), '*');
            if (empty($cateInfo)) {
                $this->end(false, app::get('base')->_('大类信息不存在,请检查!'));
            }

            //格式化提交的大类属性的prop_key
            $property_list = $productLib->formatPropertyList($cat_id, $property_list, $error_msg);

            //删除所有关联属性值
            $propertyList = $propertyObj->getList('prop_id', array('cat_id' => $cat_id));
            if ($propertyList) {
                foreach ($propertyList as $key => $val) {
                    $propertyValObj->delete(array('prop_id' => $val['prop_id']));
                }

                $delWhere = " WHERE cat_id=" . $cat_id;
                if ($prop_id_list) {
                    $delWhere .= " AND prop_id NOT IN(" . implode(',', $prop_id_list) . ")";
                }
                $categoryObj->db->exec("DELETE FROM sdb_material_basic_material_prop " . $delWhere);
            }
        }

        //save property
        foreach ($property_list as $key => $val) {
            $propertySdf = array(
                'cat_id'      => $cat_id,
                'prop_name'   => $val['prop_name'],
                'status'      => 1,
                'create_time' => time(),
                'order_num'   => $val['order_num'],
                'prop_key'    => $val['prop_key'],
                'auto_create' => $val['auto_create'],
                'is_lock'     => $val['is_lock'],
            );

            if ($val['prop_id']) {
                //更新
                unset($propertySdf['create_time']);
                if (!$propertyObj->update($propertySdf, array('prop_id' => $val['prop_id']))) {
                    $this->end(false, app::get('base')->_('更新大类属性失败'));
                }

                $propertySdf['prop_id'] = $val['prop_id'];
            } else {
                if (!$propertyObj->save($propertySdf)) {
                    $this->end(false, app::get('base')->_('保存大类属性失败'));
                }
            }

            $prop_id = $propertySdf['prop_id'];

            //save property_value
            foreach ($val['prop_select'] as $selKey => $selVal) {
                $selectSdf = array(
                    'prop_id'    => $prop_id,
                    'value_name' => $selVal,
                );
                if (!$propertyValObj->save($selectSdf)) {
                    $this->end(false, app::get('base')->_('保存大类属性选项值失败'));
                }
            }
        }

        $this->end(true, app::get('base')->_('保存成功'));
    }

    /**
     * 获取CatById
     * @param mixed $parent_id ID
     * @return mixed 返回结果
     */
    public function getCatById($parent_id)
    {
        $object = kernel::single('material_basic_material_cat');
        echo json_encode($object->getCatById($parent_id));
    }
}
