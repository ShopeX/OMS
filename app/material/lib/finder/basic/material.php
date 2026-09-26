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

class material_finder_basic_material{
    var $detail_basic = '物料信息';
    var $column_images = '物料图片';
    var $detail_channel = '对应渠道';
    var $addon_cols = 'bbu_id';
    
    // 缓存图片数据，避免重复查询
    private static $imageCache = null;
    
    var $column_edit = '操作';
    var $column_edit_width = "75";
    function column_edit($row){
        if($_GET['ctl'] == 'admin_material_basic' && $_GET['act'] == 'index'){
            $btn = '-';
            if(kernel::single('desktop_user')->has_permission('basic_material_edit')) {
                $btn = '<a href="index.php?app=material&ctl=admin_material_basic&act=edit&p[0]='.$row['bm_id'].'&finder_id='.$_GET['_finder']['finder_id'].'">编辑</a>&nbsp;&nbsp;';
    
            }
            $use_buildin_look = kernel::single('desktop_user')->has_permission('basic_material_detail');
            if ($use_buildin_look) {
                if ($btn == '-') {
                    $btn = '';
                }
                $btn .= '<a href="index.php?app=material&ctl=admin_material_basic&act=detail&p[0]='.$row['bm_id'].'&finder_id='.$_GET['_finder']['finder_id'].'" target="_blank">查看</a>';
            }
            return $btn;
        }else{
            return '-';
        }
    }

    /**
     * 物料标记列（复用订单标记 bill_label，bill_type=basic_material）
     */
    public $column_material_label = '标记';
    public $column_material_label_width = 260;
    public $column_material_label_order = 30;

    public function column_material_label($row, $list)
    {
        $bm_id = $row['bm_id'];
        $labelList = $this->__getMaterialLabel($list);
        $dataList = !empty($labelList[$bm_id]) ? $labelList[$bm_id] : array();
        if (empty($dataList)) {
            return '';
        }

        $str = array();
        foreach ($dataList as $val) {
            $label_desc = $val['label_desc'];
            if ($label_desc) {
                $str[] = sprintf(
                    "<span onmouseover='bindFinderColTip(event)' rel='%s' style='filter: brightness(0.9) contrast(0.9);border:1px solid %s; color:%s;margin: 2px;padding: 0px 2px;border-radius: 5px;white-space: nowrap;'>%s</span>",
                    $label_desc,
                    $val['label_color'],
                    $val['label_color'],
                    $val['label_name']
                );
            } else {
                $str[] = sprintf(
                    "<span style='filter: brightness(0.9) contrast(0.9);border:1px solid %s; color:%s;margin: 2px;padding: 0px 2px;border-radius: 5px;white-space: nowrap;'>%s</span>",
                    $val['label_color'],
                    $val['label_color'],
                    $val['label_name']
                );
            }
        }

        return '<div style="overflow: auto;word-break: break-word;white-space: normal;width: 100%;flex-wrap: wrap;display: flex;">' . implode('', $str) . '</div>';
    }

    private function __getMaterialLabel($list)
    {
        static $arrMaterialLabel;

        if (isset($arrMaterialLabel)) {
            return $arrMaterialLabel;
        }

        $arrMaterialLabel = array();
        $bmIds = array();
        foreach ($list as $val) {
            if (!empty($val['bm_id'])) {
                $bmIds[] = $val['bm_id'];
            }
        }
        if (empty($bmIds)) {
            return $arrMaterialLabel;
        }

        $billLabelObj = app::get('ome')->model('bill_label');
        $labelData = $billLabelObj->getBIllLabelList($bmIds, 'basic_material');
        foreach ((array) $labelData as $val) {
            $bm_id = $val['bill_id'];
            $arrMaterialLabel[$bm_id][] = array(
                'label_id' => $val['label_id'],
                'label_name' => $val['label_name'],
                'label_color' => $val['label_color'],
                'label_desc' => $val['label_desc'],
            );
        }

        return $arrMaterialLabel;
    }

    var $column_use_expire='保质期监控';
    var $column_use_expire_width = "75";
    function column_use_expire($row){
        $basicMaterialConfObj = app::get('material')->model('basic_material_conf');
        $tmp_bm_id = $row['bm_id'];
        $bm_conf = $basicMaterialConfObj->getList('use_expire',array('bm_id'=>$tmp_bm_id));
        if($bm_conf[0]['use_expire'] == 1){
            return '开启';
        }else{
            return '关闭';
        }
    }

    var $column_bbu_id='所属公司业务组织';
    var $column_bbu_id_width = "125";
    function column_bbu_id($row){
        static $bbuList;
        if (!isset($bbuList)) {
            $bbuList = [];
            //检查是否安装dealer应用
            if (app::get('dealer')->is_installed()) {
                $bbuList = [];
                $bbuList = app::get('dealer')->model('bbu')->getList('bbu_id,bbu_code');
                if ($bbuList) {
                    $bbuList = array_column($bbuList, null, 'bbu_id');
                }
            }
        }

        if ($bbuList[$row[$this->col_prefix.'bbu_id']]) {
            return $bbuList[$row[$this->col_prefix.'bbu_id']]['bbu_code'];
        }

        return $row[$this->col_prefix.'bbu_id'];
    }
    
    var $detail_history = '操作日志';
    
    // =============== 物料详情页面方法 ===============
    function detail_basic($bm_id){
        
        $render   = app::get('material')->render();
        
        $basicMaterialObj        = app::get('material')->model('basic_material');
        $basicMaterialExtObj     = app::get('material')->model('basic_material_ext');
        $basicMaterialCodeObj    = app::get('material')->model('codebase');
        $basicMaterialConfObj    = app::get('material')->model('basic_material_conf');
        $basicMaterialConfObjSpe = app::get('material')->model('basic_material_conf_special');
        $basicMaterialBrand      = app::get('ome')->model('brand');
        $catMdl = app::get('material')->model('basic_material_cat');
        $materialLib = kernel::single('material_basic_material');
        
        $tmp_bm_id = $bm_id = intval($bm_id);
        
        $basicMaterialInfo = $basicMaterialObj->dump($bm_id);
       
        $basicMaterialExtInfo = $basicMaterialExtObj->dump($bm_id);
        //这里的cat_id实际上是类型 type_id
        $basicMaterialExtInfo['type_id'] = $basicMaterialExtInfo['cat_id'];
        unset($basicMaterialExtInfo['cat_id'],$basicMaterialExtInfo['color'],$basicMaterialExtInfo['size']);
        $basicMaterialConfInfo  = $basicMaterialConfObj->dump($tmp_bm_id);
        
       
        $material_info = array_merge($basicMaterialInfo, (array) $basicMaterialExtInfo, (array) $basicMaterialConfInfo);
        
        $basicBarCode                   = $basicMaterialCodeObj->dump(array('bm_id' => $bm_id, 'type' => material_codebase::getBarcodeType()), 'code');
        $material_info['material_cdoe'] = empty($basicBarCode) ? '' : $basicBarCode['code'];
       

        $render->pagedata['material_info'] = $material_info;
     
        $basicMaterialBrandInfo = $basicMaterialBrand->dump(array('brand_id'=>$material_info['brand_id']),'brand_id,brand_name');
        $render->pagedata['brandList'] = $basicMaterialBrandInfo;
        
        $cats = $catMdl->dump(array('cat_id'=>$basicMaterialInfo['cat_id']),'cat_name');


        $render->pagedata['cats'] = $cats;
        #获取物料分类
        $goods_type_obj               = app::get('ome')->model('goods_type');
        $goods_types                  = $goods_type_obj->dump(array('type_id'=>$basicMaterialExtInfo['type_id']),'type_id,name');
        $render->pagedata['goods_types'] = $goods_types;
        

      
       
        
        $basicMaterialCombinationItemsObj = app::get('material')->model('basic_material_combination_items');

        $rows  = array();
        $items = $basicMaterialCombinationItemsObj->getList('bm_id,material_bn,material_name,material_num', array('pbm_id' => $bm_id), 0, -1);
        $render->pagedata['items'] = $items;
        //
        $propsMdl = app::get('material')->model('basic_material_props');

        $propsList = $propsMdl->getlist('*', ['bm_id' => $tmp_bm_id]);

        $arr_props = array();
        foreach($propsList as $v){

            $arr_props[$v['props_col']] = $v['props_value'];

        }

        $render->pagedata['arr_props'] = $arr_props;

        $customcols = kernel::single('material_customcols')->attachPropsValues(
            kernel::single('material_customcols')->getcols(),
            $arr_props
        );
        $render->pagedata['customcols'] = $customcols;



        return $render->fetch('admin/material/basic/basic.html');
    }

    /**
     * 获取单个物料的图片数据
     * @param int $bm_id 物料ID
     * @return array|null 图片数据
     */
    private function __getSingleMaterialImage($bm_id) {
        if (!$bm_id) {
            return null;
        }
        
        try {
            $imageAttachModel = app::get('image')->model('image_attach');
            $imageModel = app::get('image')->model('image');
            
            // 获取最新的图片关联记录
            $attach = $imageAttachModel->getRow('target_id,image_id,attach_id,last_modified', array(
                'target_type' => 'material',
                'target_id' => $bm_id
            ), 'last_modified DESC');
            
            if (empty($attach)) {
                return null;
            }
            
            // 获取图片信息
            $image_info = $imageModel->getRow('image_id,image_name,url,ident,width,height,storage', array(
                'image_id' => $attach['image_id']
            ));
            
            if (empty($image_info)) {
                return null;
            }
            
            // 构建图片数据
            $image = array(
                'image_id' => $image_info['image_id'],
                'image_name' => $image_info['image_name'],
                'url' => $image_info['url'],
                'ident' => $image_info['ident'],
                'width' => $image_info['width'],
                'height' => $image_info['height'],
                'storage' => $image_info['storage'],
                'attach_id' => $attach['attach_id'],
                'last_modified' => $attach['last_modified']
            );
            
            // 获取完整路径
            if ($image['storage'] !== 'network') {
                try {
                    $image['full_url'] = base_storager::image_path($image['image_id'], '');
                } catch (Exception $e) {
                    $image['full_url'] = $image['url'];
                }
            } else {
                $image['full_url'] = $image['url'];
            }
            
            return $image;
            
        } catch (Exception $e) {
            // 避免在生产环境使用error_log
            return null;
        }
    }

    /**
     * 批量获取物料图片数据，避免重复查询
     * @param array $list 物料列表数据
     * @return array 图片数据缓存
     */
    private function __getMaterialImages($list) {
        // 如果已经缓存过，直接返回
        if (self::$imageCache !== null) {
            return self::$imageCache;
        }
        
        // 收集所有需要查询图片的 bm_id
        $bm_ids = array();
        if (is_array($list) && !empty($list)) {
            foreach ($list as $row) {
                if (isset($row['bm_id']) && intval($row['bm_id'])) {
                    $bm_ids[] = intval($row['bm_id']);
                }
            }
        }
        
        if (empty($bm_ids)) {
            self::$imageCache = array();
            return self::$imageCache;
        }
        
        // 使用model的getList方法替代直接SQL查询
        $imageAttachModel = app::get('image')->model('image_attach');
        $imageModel = app::get('image')->model('image');
        
        try {
            // 1. 通过model获取所有关联记录
            $attachList = $imageAttachModel->getList('target_id,image_id,attach_id,last_modified', array(
                'target_type' => 'material',
                'target_id|in' => $bm_ids
            ), 0, -1, 'last_modified DESC');
            
            if (empty($attachList)) {
                self::$imageCache = array();
                return self::$imageCache;
            }
            
            // 2. 收集所有图片ID，并建立关联映射
            $image_ids = array();
            $attach_map = array(); // 用于快速查找关联关系
            
            foreach ($attachList as $attach) {
                $bm_id = $attach['target_id'];
                $image_id = $attach['image_id'];
                
                // 只保留最新的关联记录（attach_id最大的）
                if (!isset($attach_map[$bm_id]) || $attach['attach_id'] > $attach_map[$bm_id]['attach_id']) {
                    $attach_map[$bm_id] = $attach;
                }
                
                $image_ids[] = $image_id;
            }
            
            // 去重图片ID
            $image_ids = array_unique($image_ids);
            
            if (empty($image_ids)) {
                self::$imageCache = array();
                return self::$imageCache;
            }
            
            // 3. 通过model获取图片信息
            $imageList = $imageModel->getList('image_id,image_name,url,ident,width,height,storage', array(
                'image_id|in' => $image_ids
            ));
            
            // 4. 组装数据
            $imageCache = array();
            foreach ($attach_map as $bm_id => $attach) {
                $image_id = $attach['image_id'];
                
                // 查找对应的图片信息
                $image_info = null;
                foreach ($imageList as $img) {
                    if ($img['image_id'] == $image_id) {
                        $image_info = $img;
                        break;
                    }
                }
                
                if ($image_info) {
                    // 构建图片数据
                    $image = array(
                        'image_id' => $image_info['image_id'],
                        'image_name' => $image_info['image_name'],
                        'url' => $image_info['url'],
                        'ident' => $image_info['ident'],
                        'width' => $image_info['width'],
                        'height' => $image_info['height'],
                        'storage' => $image_info['storage'],
                        'attach_id' => $attach['attach_id'],
                        'last_modified' => $attach['last_modified']
                    );
                    
                    // 获取完整路径
                    if ($image['storage'] !== 'network') {
                        try {
                            $image['full_url'] = base_storager::image_path($image['image_id'], '');
                        } catch (Exception $e) {
                            $image['full_url'] = $image['url'];
                        }
                    } else {
                        $image['full_url'] = $image['url'];
                    }
                    
                    $imageCache[$bm_id] = $image;
                }
            }
            
            self::$imageCache = $imageCache;
            return $imageCache;
            
        } catch (Exception $e) {
            // 避免在生产环境使用error_log
            self::$imageCache = array();
            return array();
        }
    }

    /**
     * 物料图片列显示处理（处理单行数据）
     * @param array $row 单行物料数据
     * @param array $list 完整列表数据（用于批量查询优化）
     * @return string 图片HTML
     */
    function column_images($row, $list = array()) {
        if (!is_array($row) || !isset($row['bm_id'])) {
            return '<span style="color: #999; font-size: 12px;">无图片</span>';
        }
        
        $bm_id = intval($row['bm_id']);
        if (!$bm_id) {
            return '<span style="color: #999; font-size: 12px;">无图片</span>';
        }
        
        // 使用批量查询的图片数据
        $images = $this->__getMaterialImages($list);
        
        if (empty($images) || !isset($images[$bm_id])) {
            return '<span style="color: #999; font-size: 12px;">无图片</span>';
        }
        
        $image = $images[$bm_id]; // 直接获取图片对象
        
        // 检查图片数据完整性
        if (!isset($image['full_url']) || empty($image['full_url'])) {
            return '<span style="color: #999; font-size: 12px;">图片加载中...</span>';
        }
        
        $imageHtml = '<img src="' . htmlspecialchars($image['full_url']) . '" '
                   . 'alt="' . htmlspecialchars($image['image_name'] ?: '物料图片') . '" '
                   . 'style="width: 50px; height: 50px; object-fit: cover; border: 1px solid #ddd; border-radius: 4px; cursor: zoom-in;" '
                   . 'class="img-magnifier" '
                   . 'rel="' . htmlspecialchars($image['full_url']) . '" '
                   . 'data-bm-id="' . $bm_id . '" '
                   . 'onmouseenter="showImageMagnifier(event, this)" '
                   . 'onmousemove="updateImageMagnifierPosition(event)" '
                   . 'onmouseleave="hideImageMagnifier()" />';
        return $imageHtml;
    }
}
