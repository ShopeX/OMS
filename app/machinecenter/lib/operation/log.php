<?php

/**
 * @Author: xueding@shopex.cn
 * @Date: 2025/5/28
 * @Describe: 机器中心日志
 */
class machinecenter_operation_log
{
    
    function get_operations()
    {
        $operations = array (
            'update'            => array ('name' => '更新操作', 'type' => 'material_sn@machinecenter'),
        );
        return array ('machinecenter' => $operations);
    }
}

?>