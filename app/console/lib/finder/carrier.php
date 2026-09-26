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

class console_finder_carrier{
    
    var $addon_cols = 'carrier_isvalid,platform_status';
    
    var $column_carrier_isvalid = '承运商状态';
    var $column_carrier_isvalid_width = 120;
    var $column_carrier_isvalid_order = 89;
    function column_carrier_isvalid($row)
    {
        if($row[$this->col_prefix .'carrier_isvalid'] == 1){
            return '启用';
        }
        
        return '停用';
    }
    
    var $column_platform_status = '平台状态';
    var $column_platform_status_width = 120;
    var $column_platform_status_order = 50;
    function column_platform_status($row)
    {
        if($row[$this->col_prefix .'platform_status'] == 'abandon') {
            return '已废弃';
        }
        
        return '允许使用';
    }
}