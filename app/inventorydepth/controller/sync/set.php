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

            try {
                // 与 Vue api 共用同一 capability（校验/归一化/持久化集中一处，避免两处漂移）
                kernel::single('inventorydepth_capability_sync_set')->saveSettings((array) $_POST['set']);
            } catch (InvalidArgumentException $exception) {
                $this->end(false, $exception->getMessage());
                return;
            }

            $this->end(true,'保存成功');
        }

        //读取配置
        $data = kernel::single('inventorydepth_capability_sync_set')->loadSettings();

        $this->pagedata['setData']           = $data['setData'];
        $this->pagedata['support_name']      = $data['support_name'];
        $this->pagedata['mode_support_name'] = $data['mode_support_name'];
        $this->page("sync/setting.html");
    }
}
