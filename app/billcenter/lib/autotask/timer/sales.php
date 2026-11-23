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

class billcenter_autotask_timer_sales
{
    public function process($params, &$error_msg=''){
        set_time_limit(0); ignore_user_abort(1);
        
        if (!app::get('finance')->is_installed()) {
            $error_msg = 'JIT转AR失败：APP:finance未安装';
            return true;
        }
        
        // 判断有没有开启账期
        $init_time = app::get('finance')->getConf('finance_setting_init_time');
        if (!$init_time) {
            $error_msg = 'JIT转AR失败：未配置账期';
            return true;
        }
        
        $execTime = 600;
        
        $startTime = microtime(true);
        
        // 查询销售单
        do {
            $endTime = microtime(true);
            if (($startTime - $endTime) >= $execTime) {
                break;
            }
            
            $sale = app::get('billcenter')->model('sales')->db_dump(['in_ar' => '0']);
            
            if (!$sale) {
                break;
            }
            
            $res = kernel::single('billcenter_sales')->transferAr($sale['id']);
            
        } while (true);
        
        return true;
    }
    
    
}