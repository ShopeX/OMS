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
 * 门店的队列任务导入最终执行Lib类
 *
 * @access public
 * @author wangbiao<wangbiao@shopex.cn>
 * @version $Id: import.php 2016-07-26 15:00
 */
class o2o_store_to_import
{
    /**
     * 门店导入的队列任务执行
     * 
     * @param String $cursor_id
     * @param Array $params
     * @param String $errmsg
     * @return Boolean
     */

    function run(&$cursor_id, $params, &$errmsg)
    {
        $storeLib        = kernel::single('o2o_store');
        $dataSdf         = $params['sdfdata'];
        
        foreach ($dataSdf as $val)
        {
            #门店数据处理
            $result    = $storeLib->create_store($val, $errmsg);
            if(!$result)
            {
                return false;
            }
            
            // 处理覆盖区域
            if (!empty($val['coverage_area'])) {
                kernel::single('o2o_store_coverage')->saveByStoreBn(
                    $val['org_no'],
                    (array) $val['coverage_area'],
                    $errmsg
                );
            }
        }
        
        return false;
    }
}
