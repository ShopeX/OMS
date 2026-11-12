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
 * 福袋组合Finder类
 *
 * @author wangbiao@shopex.cn
 * @version 2024.09.10
 */
class material_finder_fukubukuro_combine
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    //model
    private $_appName = 'material';
    private $_modelName = 'fukubukuro_combine';
    private $_primary_id = 'combine_id';
    
    static $_reMaterials = array();
    
    public $addon_cols = '';
    
    var $column_edit = '操作';
    var $column_edit_width = 110;
    var $column_edit_order = 1;
    /**
     * column_edit
     * @param mixed $row row
     * @return mixed 返回值
     */

    public function column_edit($row)
    {
        $url = 'index.php?app=material&ctl=admin_fukubukuro_combine';
        $finder_id = $_GET['_finder']['finder_id'];
        $btn = '-';
        
        if(kernel::single('desktop_user')->has_permission('material_fukubukuro_edit')) {
            $btn = '<a href="'. $url .'&act=editCombine&p[0]='. $row[$this->_primary_id] .'&finder_id='. $finder_id .'">编辑</a>&nbsp;&nbsp;';
        }
        
        return $btn;
    }
    
    var $detail_basic = '基本信息';
    /**
     * detail_basic
     * @param mixed $combine_id ID
     * @return mixed 返回值
     */
    public function detail_basic($combine_id)
    {
        $render = app::get($this->_appName)->render();
        
        $combineMdl = app::get('material')->model($this->_modelName);
        $combineLib = kernel::single('material_fukubukuro_combine');
        
        //获取信息
        $masterInfo = $combineMdl->dump(array($this->_primary_id=>$combine_id), '*');
        
        //items
        $error_msg = '';
        $itemList = $combineLib->formatCombineItems($combine_id, $error_msg);
        
        $render->pagedata['data'] = $masterInfo;
        $render->pagedata['items'] = $itemList;
        
        return $render->fetch('admin/fukubukuro/detail_basic.html');
    }
    
    //关联基础物料
    var $column_relation_material = '关联基础物料';
    var $column_relation_material_width = 350;
    var $column_relation_material_order = 20;
    /**
     * column_relation_material
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_relation_material($row, $list)
    {
        //load
        $this->_getRelationMaterial($list);
        
        $combine_id = $row[$this->col_prefix .'combine_id'];
        
        $materialBns = array();
        $mateiralList = isset(self::$_reMaterials[$combine_id]) ? self::$_reMaterials[$combine_id] : array();
        if($mateiralList){
            $materialBns = array_column($mateiralList, 'material_bn');
        }
        
        $material_str = implode(',', $materialBns);
        $materialBns = array_splice($materialBns, 0, 5);
        
        return '<a title="'. $material_str .'">'. implode(',', $materialBns) .'</a>';
    }
    
    /**
     * 批量获取指定经销商列表(包含贸易公司信息)
     * 
     * @param $list
     * @return boolean
     */
    private function _getRelationMaterial($list)
    {
        //check
        if(self::$_reMaterials){
            return true;
        }
        
        $combineItemMdl = app::get('material')->model('fukubukuro_combine_items');
        $basicMaterialObj = app::get('material')->model('basic_material');
        
        $combineIds = array_column($list, 'combine_id');
        
        //items
        $itemList = $combineItemMdl->getList('combine_id,bm_id', array('combine_id'=>$combineIds));
        if(empty($itemList)){
            return false;
        }
        
        //material
        $bmIds = array_column($itemList, 'bm_id');
        $materialList = $basicMaterialObj->getList('bm_id,material_bn,material_name', array('bm_id'=>$bmIds));
        $materialList = array_column($materialList, null, 'bm_id');
        if(empty($materialList)){
            return false;
        }
        
        //format
        foreach ($itemList as $key => $val)
        {
            $combine_id = $val['combine_id'];
            $bm_id = $val['bm_id'];
            
            self::$_reMaterials[$combine_id][$bm_id] = array(
                'material_bn' => $materialList[$bm_id]['material_bn'],
                'material_name' => $materialList[$bm_id]['material_bn']
            );
        }
        
        return true;
    }
    
    var $detail_log = "操作日志";
    /**
     * detail_log
     * @param mixed $combine_id ID
     * @return mixed 返回值
     */
    public function detail_log($combine_id)
    {
        $render = app::get($this->_appName)->render();
        
        //log
        $operLogMdl = app::get('ome')->model('operation_log');
        $logList = $operLogMdl->read_log(array('obj_id'=>$combine_id, 'obj_type'=>'fukubukuro_combine@material'), 0, -1);
        
        $render->pagedata['logList'] = $logList;
        
        return $render->fetch('admin/fukubukuro/detail_log.html');
    }
}
