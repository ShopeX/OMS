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
 * ============================
 * @Author:   yaokangming
 * @describe: 库存同步设置
 * ============================
 */
class inventorydepth_ctl_sync_set extends desktop_controller {
    public function index() {
        //配置信息保存
        if($_POST['set']){
            $this->begin($this->url);
            $settins    = $_POST['set'];
            
            //保存配置
            foreach($settins as $set => $value)
            {
                $curSet = app::get('inventorydepth')->getConf($set);
                
                if($curSet != $settins[$set])
                {
                    $curSet = $settins[$set];
                    app::get('inventorydepth')->setConf($set, $settins[$set]);
                }
            }
            
            $this->end(true,'保存成功');
        }
        
        //读取配置
        $setData                                = array();
        $setData['stock_sync_set']         = app::get('inventorydepth')->getConf('stock.sync.set');
        $setData['stock_sync_mode']        = app::get('inventorydepth')->getConf('stock.sync.mode');
        $setData['stock_sync_trigger_basis'] = kernel::single('inventorydepth_sync_set')->getStockTriggerBasis();
        
        $this->pagedata['setData'] = $setData;
        $this->pagedata['support_name'] = kernel::single('inventorydepth_sync_set')->getSupportName();
        $this->pagedata['mode_support_name'] = kernel::single('inventorydepth_sync_set')->getModeSupportName();
        $this->page("sync/setting.html");
    }
}
