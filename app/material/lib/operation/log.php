<?php
class material_operation_log{

    /**
     * 定义当前APP下的操作日志的所有操作名称列表
     * type键值由表名@APP名称组成
     * @access public
     * @return Array
     */
    function get_operations(){
        $operations = array(
            'seller' => array('name'=>'销售人员', 'type'=>'seller@material'),
        );
        
        return array('material'=>$operations);
    }
}
?>
