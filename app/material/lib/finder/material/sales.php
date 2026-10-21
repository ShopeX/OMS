<?php
class material_finder_material_sales{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    
    var $column_edit = '操作';
    function column_edit($row){
        if($_GET['ctl'] == 'admin_material_sales' && $_GET['act'] == 'index'){
            $use_buildin_edit = kernel::single('desktop_user')->has_permission('sales_material_edit');
            $btn = '-';
            if ($use_buildin_edit) {
                $btn = '<a href="index.php?app=material&ctl=admin_material_sales&act=edit&p[0]='.$row['sm_id'].'&finder_id='.$_GET['_finder']['finder_id'].'" target="_blank">编辑</a>&nbsp;&nbsp;';
            }
            $use_buildin_look = kernel::single('desktop_user')->has_permission('sales_detail');
            if ($use_buildin_look) {
                if ($btn == '-') {
                    $btn = '';
                }
                $btn .= '<a href="index.php?app=material&ctl=admin_material_sales&act=detail&p[0]='.$row['sm_id'].'&finder_id='.$_GET['_finder']['finder_id'].'" target="_blank">查看</a>';
            }
            return $btn;
        }else{
            return '-';
        }
    }

    var $column_brand = '品牌';
    function column_brand($row){
        $ext = app::get('material')->model('sales_material_ext')->db_dump($row['sm_id'], 'brand_id');
        if(empty($ext['brand_id'])) {
            return '-';
        }
        $brand = app::get('ome')->model('brand')->db_dump($ext['brand_id'], 'brand_name');
        return $brand['brand_name'];
    }
    
    var $column_specifications = '物料规格';
    function column_specifications($row, $list){
        $sm_id = $row[$this->col_prefix.'sm_id'];
        if($sm_id && $sm_id > 0){
            $specifications_data = $this->_getSpecificationsData($sm_id, $list);
            if($specifications_data && isset($specifications_data['specifications'])){
                return $specifications_data['specifications'] ?: '-';
            } else {
                return '-';
            }
        } else {
            return '-';
        }
    }

    /**
     * 批量查询规格数据 - 标准模板方法
     * 
     * 使用静态缓存确保整个列表只查询一次数据库
     * 避免 N+1 查询问题，提升页面性能
     * 
     * @param int $sm_id 销售物料ID
     * @param array $list 当前列表数据
     * @return array|null 规格数据
     */
    private function _getSpecificationsData($sm_id, $list)
    {
        static $specificationsDataList;
        
        if (isset($specificationsDataList)) {
            return $specificationsDataList[$sm_id];
        }
        
        $specificationsDataList = [];
        $sm_ids = array();
        
        // 收集所有需要查询的销售物料ID
        foreach($list as $val) {
            $sid = $val[$this->col_prefix.'sm_id'];
            if($sid && $sid > 0) {
                $sm_ids[] = $sid;
            }
        }
        
        if($sm_ids) {
            $sm_ids = array_unique($sm_ids);
            
            // 批量查询销售物料与基础物料的关联关系
            $salesBasicMaterialObj = app::get('material')->model('sales_basic_material');
            $salesBasicMInfos = $salesBasicMaterialObj->getList('sm_id,bm_id,number', array('sm_id|in' => $sm_ids), 0, -1);
            
            if ($salesBasicMInfos) {
                // 按销售物料ID分组，判断是否为一对一关系
                $smBmMapping = array();
                foreach($salesBasicMInfos as $info) {
                    $smId = $info['sm_id'];
                    if (!isset($smBmMapping[$smId])) {
                        $smBmMapping[$smId] = array();
                    }
                    $smBmMapping[$smId][] = $info;
                }
                
                // 获取一对一关系的销售物料ID和对应的基础物料ID
                $oneToOneSmIds = array();
                $bmIds = array();
                foreach($smBmMapping as $smId => $bmList) {
                    // 只有关联一个基础物料且数量为1的才是一对一关系
                    if (count($bmList) == 1 && $bmList[0]['number'] == 1) {
                        $oneToOneSmIds[] = $smId;
                        $bmIds[] = $bmList[0]['bm_id'];
                    }
                }
                
                if (!empty($bmIds)) {
                    // 批量获取基础物料规格信息
                    $basicMaterialExtObj = app::get('material')->model('basic_material_ext');
                    $specificationsList = $basicMaterialExtObj->getList('bm_id,specifications', array('bm_id|in' => $bmIds));
                    
                    // 构建返回数据映射
                    $bmSpecificationsMap = array();
                    foreach($specificationsList as $spec) {
                        $bmSpecificationsMap[$spec['bm_id']] = $spec;
                    }
                    
                    foreach($oneToOneSmIds as $smId) {
                        $bmId = $smBmMapping[$smId][0]['bm_id'];
                        if (isset($bmSpecificationsMap[$bmId])) {
                            $specificationsDataList[$smId] = $bmSpecificationsMap[$bmId];
                        }
                    }
                }
            }
        }
        
        return $specificationsDataList[$sm_id];
    }


    var $column_unit = '包装单位';
    function column_unit($row, $list){
        $sm_id = $row['sm_id'];
        $extData = $this->_getSalesMaterialExtData($list);
        return isset($extData[$sm_id]['unit']) ? $extData[$sm_id]['unit'] : '-';
    }

    var $column_retail_price = '售价';
    function column_retail_price($row, $list){
        $sm_id = $row['sm_id'];
        $extData = $this->_getSalesMaterialExtData($list);
        return isset($extData[$sm_id]['retail_price']) ? $extData[$sm_id]['retail_price'] : '-';
    }

    var $column_lowest_price = '最低售价';
    function column_lowest_price($row, $list){
        $sm_id = $row['sm_id'];
        $extData = $this->_getSalesMaterialExtData($list);
        return isset($extData[$sm_id]['lowest_price']) ? $extData[$sm_id]['lowest_price'] : '-';
    }

    /**
     * 批量查询销售物料扩展数据 - 静态缓存优化
     * 
     * 使用静态缓存确保整个列表只查询一次数据库
     * 避免 N+1 查询问题，提升页面性能
     * 
     * @param array $list 当前列表数据
     * @return array 以sm_id为key的扩展数据数组
     */
    private function _getSalesMaterialExtData($list)
    {
        static $extDataList;
        
        if (isset($extDataList)) {
            return $extDataList;
        }
        
        $extDataList = [];
        $sm_ids = array_column($list, 'sm_id');
        
        if($sm_ids) {
            $sm_ids = array_unique($sm_ids);
            
            // 批量查询销售物料扩展数据
            $extModel = app::get('material')->model('sales_material_ext');
            $extList = $extModel->getList('sm_id,unit,retail_price,lowest_price', array('sm_id|in' => $sm_ids), 0, -1);
            
            // 构建以sm_id为key的数据映射
            $extDataList = array_column($extList, null, 'sm_id');
        }
        
        return $extDataList;
    }

    
//    var $detail_history = '操作日志';
//
//    function detail_history($sm_id){
//        $logObj   = app::get('ome')->model('operation_log');
//        $render   = app::get('material')->render();
//
//        /* 本订单日志 */
//        $logList    = $logObj->read_log(array('obj_id'=>$sm_id, 'obj_type'=>'sales_material@material'), 0, -1);
//        foreach($logList as $k => $v)
//        {
//            $logList[$k]['operate_time'] = date('Y-m-d H:i:s', $v['operate_time']);
//        }
//
//        $render->pagedata['data'] = $logList;
//
//        return $render->fetch('admin/material/sales/logs.html');
//    }
}
