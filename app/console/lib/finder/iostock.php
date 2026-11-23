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

class console_finder_iostock{
    var $detail_basic = '详情';

    public function detail_basic($bn_branch)
    {
        // 取货号和库ID
        $arr        = explode('*$**',$bn_branch);
        $bn         = $arr[1];
        $branch_id  = $arr[0];

        // 整理查询条件
        $time_from  = $_GET['time_from'];
        $time_to    = $_GET['time_to'];
        $filter     = array('time_from' => $time_from, 'time_to' => $time_to);

        // 查询
        $mels       = app::get('console')->model('interface_iostocksearchs');
        $row        = $mels->details($bn, $branch_id, $filter);
        
        $render = app::get('console')->render();
        $render->pagedata['rows'] = $row;

        return $render->display('admin/detail_goods.html');
    }
}