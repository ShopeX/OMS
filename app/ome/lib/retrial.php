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
 * [清除]复审日志任务计划
 *
 * @author shopex开发团队
 * @version 2025.01.01
 */
class ome_retrial
{
    /**
     * 自动清除[180天 ||90天]复审日志
     *
     * @return void
     */
    public function clean()
    {
        $setting_is_monitor    = app::get('ome')->getConf('ome.order.is_monitor');//是否开启价格监控
        $setting_is_retrial    = app::get('ome')->getConf('ome.order.is_retrial');//开启复审
        $clean_time            = app::get('ome')->getConf('ome.order.clean_day');//复审日志保留天数
        $clean_time            = intval($clean_time) ? intval($clean_time) : 90;
        
        // check
        if($setting_is_monitor=='true' || $setting_is_retrial=='true'){
            $time     = time();
            $where    = " WHERE obj_type='orders@ome' AND operation='order_retrial@ome' AND operate_time<'".($time - $clean_time*24*60*60)."' ";
            $sql = "select log_id from sdb_ome_operation_log ".$where;
            $log_id = kernel::database()->select($sql);
            
            // log_id
            $log_id = array_column($log_id, 'log_id');
            
            // delete log
            kernel::database()->exec("DELETE FROM ". DB_PREFIX ."ome_operation_log where log_id in ('".implode("','", $log_id)."')");    
        }
        
        return true;
    }
}