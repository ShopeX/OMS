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
 * 基础物料Lib类
 * @author xiayuanjun@shopex.cn
 * @version 1.0
 */
class material_basic_material{

     function __construct(){
        $this->_basicMaterialObj = app::get('material')->model('basic_material');
        $this->_basicMaterialExtObj = app::get('material')->model('basic_material_ext');
        $this->_basicMaterialStockObj = app::get('material')->model('basic_material_stock');
        $this->_basicMaterialCombinationItemsObj = app::get('material')->model('basic_material_combination_items');
     }

    /**
     * 自有仓储库存查询，获取有仓库库存的物料列表显示
     * @param string $row 物料是否可见字段
     * @return string
     */

    function countAnother($filter=null){
        $other_table_name = app::get('ome')->model('branch_product')->table_name(1);
        $count = ' COUNT(*) ';
        if (isset($filter['product_group'])){
            $count = ' COUNT( DISTINCT '.$this->_basicMaterialObj->table_name(1).'.product_id ) ';
        }

        $strWhere = '';

        if(isset($filter['branch_id'])){
            if (is_array($filter['branch_id'])){
                $strWhere = ' AND '.$other_table_name.'.branch_id IN ('.implode(',', $filter['branch_id']).') ';
            }else {
                $strWhere = ' AND '.$other_table_name.'.branch_id = '.$filter['branch_id'];
            }
        }else{
            if ($filter['branch_ids']) {
                if (is_array($filter['branch_ids'])){
                    $strWhere = ' AND '.$other_table_name.'.branch_id IN ('.implode(',', $filter['branch_ids']).') ';
                }else {
                    $strWhere = ' AND '.$other_table_name.'.branch_id = '.$filter['branch_ids'];
                }
            }
        }
        $sql = 'SELECT '.$count.'as _count FROM `'.$this->_basicMaterialObj->table_name(1).'` LEFT JOIN  '.$other_table_name.'  ON '.$this->_basicMaterialObj->table_name(1).'.bm_id = '.$other_table_name.'.product_id WHERE '.$this->_basicMaterialObj->_filter($filter) . $strWhere;

        $row = $this->_basicMaterialObj->db->selectrow($sql);

        return intval($row['_count']);
    }

    /**
     * 获取基础物料信息及扩展信息
     * @param intval $bm_id
     * @return string
     */
    public function getBasicMaterialExt($bm_id)
    {
        $basicMateriaItem    = $this->_basicMaterialObj->dump(array('bm_id'=>$bm_id), '*');
        if(empty($basicMateriaItem))
        {
            return [];
        }

        #扩展信息
        $basicMateriaExt    = $this->_basicMaterialExtObj->dump(array('bm_id'=>$bm_id), 'cost, retail_price, weight, unit, specifications, brand_id, cat_id, purchasing_price');

        #条形码
        $basicMateriaItem['barcode']    = $this->getBasicMaterialCode($bm_id);

        return array_merge($basicMateriaItem, (array)$basicMateriaExt);
    }

    /**
     * 获取基础物料信息及库存信息
     * @param intval $bm_id
     * @return string
     */
    public function getBasicMaterialStock($bm_id)
    {
        $basicMateriaItem    = $this->_basicMaterialObj->dump(array('bm_id'=>$bm_id), '*');
        if(empty($basicMateriaItem))
        {
            return '';
        }

        #库存信息
        $basicMateriaStock    = $this->_basicMaterialStockObj->dump(array('bm_id'=>$bm_id), 'store, store_freeze, alert_store, last_modified');

        //根据基础物料ID获取对应的冻结库存
        $basicMStockFreezeLib  = kernel::single('material_basic_material_stock_freeze');
        $basicMateriaStock['store_freeze']    = $basicMStockFreezeLib->getMaterialStockFreeze($bm_id);

        return array_merge($basicMateriaItem, $basicMateriaStock);
    }

    /**
     * 获取基础物料信息及库存信息
     * @param intval $bm_id
     * @return string
     */
    public function getBasicMaterialDetail($bm_id)
    {
        $basicMateriaExt    = $this->getBasicMaterialExt($bm_id);
        if(empty($basicMateriaExt))
        {
            return '';
        }

        #库存信息
        $basicMateriaStock    = $this->getBasicMaterialStock($bm_id);

        return array_merge($basicMateriaExt, $basicMateriaStock);
    }

    /**
     * 获取基础物料对应条形码
     * @param intval $bm_id
     * @return string
     */
    public function getBasicMaterialCode($bm_id)
    {
        $codebaseObj    = app::get('material')->model('codebase');
        $codeBaseLib    = kernel::single('material_codebase');

        #基础物料条码
        $codType      = $codeBaseLib->getBarcodeType();
        $code_info    = $codebaseObj->dump(array('bm_id'=>$bm_id, 'type'=>$codType), 'code');

        return $code_info['code'];
    }

    /**
     * 获取基础物料关联的图片信息
     * @param intval $bm_id 物料ID
     * @param string $size 图片尺寸 (L/M/S) 默认为原图
     * @return array 图片信息
     */
    public function getBasicMaterialImages($bm_id, $size = null)
    {
        if (!$bm_id) {
            return [];
        }

        // 使用统一的图片服务
        $imageModel = app::get('image')->model('image');
        return $imageModel->getAttachedImages('material', $bm_id, $size);
    }

    /**
     * 获取基础物料的主图片
     * @param intval $bm_id 物料ID
     * @param string $size 图片尺寸 (L/M/S) 默认为原图
     * @return array|null 主图片信息
     */
    public function getBasicMaterialMainImage($bm_id, $size = null)
    {
        $images = $this->getBasicMaterialImages($bm_id, $size);
        return !empty($images) ? $images[0] : null;
    }

    /**
     * 为物料上传图片（便捷方法）
     * @param intval $bm_id 物料ID
     * @param string $file 图片文件路径
     * @param string $name 图片名称
     * @param boolean $watermark 是否添加水印
     * @return array|false 成功返回图片信息，失败返回false
     */
    public function uploadMaterialImage($bm_id, $file, $name = null, $watermark = false)
    {
        if (!$bm_id || !$file) {
            return false;
        }

        $imageModel = app::get('image')->model('image');
        return $imageModel->uploadAndAttach(
            $file, 
            'material', 
            $bm_id, 
            $name, 
            null,  // 不生成不同尺寸，只保存原图
            $watermark
        );
    }

    /**
     * 删除物料图片
     * @param intval $bm_id 物料ID
     * @param string $image_id 图片ID
     * @param boolean $delete_file 是否删除文件
     * @return boolean 是否成功
     */
    public function deleteMaterialImage($bm_id, $image_id, $delete_file = true)
    {
        if (!$bm_id || !$image_id) {
            return false;
        }

        $imageModel = app::get('image')->model('image');
        return $imageModel->detach($image_id, 'material', $bm_id, $delete_file);
    }

    /**
     * 根据货号获取基础物料信息及扩展信息
     * 
     * @param string  $material_bn
     * @return string
     */
    public function getBasicMaterialBybn($material_bn)
    {
        $basicMateriaItem    = $this->_basicMaterialObj->dump(array('material_bn'=>$material_bn), 'bm_id, material_bn, material_name');
        if(empty($basicMateriaItem))
        {
            return [];
        }

        #扩展信息
        $basicMateriaExt    = $this->_basicMaterialExtObj->dump(array('bm_id'=>$basicMateriaItem['bm_id']), 'cost, retail_price, weight, unit, specifications, brand_id, cat_id');

        return array_merge($basicMateriaItem, $basicMateriaExt);
    }

    /**
     * 传入多个bm_id获取基础物料信息及扩展信息列表
     * @param  Array $bm_ids
     * @return Array
     */
    public function getBasicMaterialByBmids($bm_ids)
    {
        $codeBaseLib    = kernel::single('material_codebase');
        $codType        = $codeBaseLib->getBarcodeType();

        if(empty($bm_ids) || !is_array($bm_ids))
        {
            return [];
        }

        $sql    = "SELECT a.bm_id, a.material_bn, a.material_name, a.visibled, b.cost, b.retail_price, b.weight, b.unit, b.specifications, b.brand_id, b.cat_id, c.code
                   FROM sdb_material_basic_material AS a
                   LEFT JOIN sdb_material_basic_material_ext AS b ON a.bm_id=b.bm_id
                   LEFT JOIN sdb_material_codebase AS c ON a.bm_id=c.bm_id
                   WHERE a.bm_id in(". implode(',', $bm_ids) .") AND c.type=". $codType;
        $result = $this->_basicMaterialObj->db->select($sql);

        return $result;
    }
    
    /**
     * 传入多个bns获取barcode
     * 
     * @param  Array $bm_ids
     * @return Array
     */
    public function getBasicMaterialByBns($bns)
    {
        if(empty($bns) || !is_array($bns)){
            return [];
        }

        $codeBaseLib    = kernel::single('material_codebase');
        $codType        = $codeBaseLib->getBarcodeType();

        $sql    = "SELECT a.bm_id, a.material_bn, c.code
                   FROM sdb_material_basic_material AS a
                   LEFT JOIN sdb_material_codebase AS c ON a.bm_id = c.bm_id
                   WHERE a.material_bn in('" . implode("','", $bns) ."') AND c.type=". $codType;
        return $this->_basicMaterialObj->db->select($sql);
    }

    /**
     * 根据Bn获取基础物料信息及库存信息
     * @param  string  $material_bn
     * @return Array
     */
    public function getMaterialStockByBn($material_bn)
    {
        $basicMateriaItem    = $this->_basicMaterialObj->dump(array('material_bn'=>$material_bn), 'bm_id, material_bn, material_name');
        if(empty($basicMateriaItem))
        {
            return '';
        }

        #库存信息
        $basicMateriaStock    = $this->_basicMaterialStockObj->dump(array('bm_id'=>$basicMateriaItem['bm_id']), 'store, store_freeze, alert_store, last_modified');

        //根据基础物料ID获取对应的冻结库存
        $basicMStockFreezeLib  = kernel::single('material_basic_material_stock_freeze');
        $basicMateriaStock['store_freeze']    = $basicMStockFreezeLib->getMaterialStockFreeze($basicMateriaItem['bm_id']);

        return array_merge($basicMateriaItem, $basicMateriaStock);
    }

    /**
     * 获取全渠道基础物料(注意:最新修改,判断只要有一个不是全渠道的货品,就是非全渠道订单)
     * 
     * @param Array $bm_ids
     * @return Boolean
     */
    public function isOmnichannelOrder($bm_ids)
    {
        $bm_ids = array_unique($bm_ids);
        $items = $this->_basicMaterialObj->getList('bm_id, omnichannel', array('bm_id'=>$bm_ids));
        if(empty($items)){
            return false;
        }
        
        foreach ($items as $key => $val){
            if($val['omnichannel'] != 1){
                return false;
            }
        }
        
        return true;
    }

    /**
     * 根据基础物料ID数组识别是否是全渠道货品
     * 
     * @param Int $bm_ids 基础物料ID数组
     * @return Array
     */
    public function isOmnichannelBms($bm_ids){
        $bms = array();
        $rs = $this->_basicMaterialObj->getList("bm_id",array("bm_id|in"=>$bm_ids,"omnichannel"=>1), 0, -1);
        if($rs){
            $bms = array_map('current',$rs);
        }
        return  $bms;
    }

    /**
     * 根据条形码获取基础物料bm_id
     * 
     * @param string $code
     * @return intval
     */
    public function getMaterialBmidByCode($code)
    {
        $codebaseObj    = app::get('material')->model('codebase');
        $codeBaseLib    = kernel::single('material_codebase');

        #基础物料条码
        $codType      = $codeBaseLib->getBarcodeType();
        $code_info    = $codebaseObj->dump(array('code'=>$code, 'type'=>$codType), 'bm_id');

        return ($code_info['bm_id'] ? $code_info['bm_id'] : 0);
    }

    /**
     * 
     * 根据成品基础物料ID获取半成品物料的明细信息
     * @param Int $bm_id
     * @return Array
     */
    public function getSeMiBasicMsByBasicMId($bm_id){
        $basicMaterialCombinationItems = $this->_basicMaterialCombinationItemsObj->getList('bm_id,material_num',array('pbm_id'=>$bm_id), 0, -1);
        if($basicMaterialCombinationItems){
            foreach($basicMaterialCombinationItems as $k => $combinationItems){
                $bmIds[] = $combinationItems['bm_id'];
                $bmCombinationItems[$combinationItems['bm_id']] = $combinationItems;
            }

            $basicMaterialExtInfos = $this->_basicMaterialExtObj->getList('bm_id,cost,weight,retail_price,unit',array('bm_id'=>$bmIds), 0, -1);
            if($basicMaterialExtInfos){
                foreach($basicMaterialExtInfos as $key => $basicMaterialExtInfo){
                    $bmExts[$basicMaterialExtInfo['bm_id']] = $basicMaterialExtInfo;
                }
            }

            $basicMaterialInfos = $this->_basicMaterialObj->getList('bm_id,material_name,material_bn',array('bm_id'=>$bmIds), 0, -1);
            if($basicMaterialInfos){
                foreach($basicMaterialInfos as $key => $basicMaterialInfo){
                    $bmList[] = array_merge($basicMaterialInfo, $bmCombinationItems[$basicMaterialInfo['bm_id']], $bmExts[$basicMaterialInfo['bm_id']]);
                }
                return $bmList;
            }
        }
        return false;
    }
    
    /**
     * 物料属性
     */
    public function get_material_types($type=null)
    {
        $dataList = array(
                '1' => '成品',
                '2' => '半成品',
                '3' => '普通',
                '4' => '礼盒',
                '5' => '虚拟',
        );
        
        if($type){
            return $dataList[$type];
        }
        
        return $dataList;
    }

    public function getSmIdsBmIds($bm_ids){
        $smIds = array();
        $rs = app::get('material')->model('sales_basic_material')->getList("bm_id,sm_id",array("bm_id|in"=>$bm_ids), 0, -1);
        if($rs){
            foreach($rs as $v){
                $smIds[$v['sm_id']] = $v['sm_id'];
            }
        }
        return $smIds;
    }
    
    /**
     * 获取指定不需要管控库存的基础物料
     *
     * @param $bmIds
     * @return void
     */
    public function getNotCtrlStoreProducts($bmIds)
    {
        $basicMaterialObj = app::get('material')->model('basic_material');
        
        // check
        if(empty($bmIds)){
            return [];
        }
        
        // 不需要管控库存的基础物料
        $materialList = $basicMaterialObj->getList('bm_id,material_bn,type,is_ctrl_store', array('bm_id'=>$bmIds, 'is_ctrl_store'=>2));
        if(empty($materialList)){
            return [];
        }
        
        $materialList = array_column($materialList, null, 'bm_id');
        
        return $materialList;
    }
}
