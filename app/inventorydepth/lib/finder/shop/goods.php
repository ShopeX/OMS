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

class inventorydepth_finder_shop_goods {
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    
    function __construct($app) {
        $this->app = $app;
        $this->_request = kernel::single('base_component_request');
    }

    var $column_control = '操作';
    var $column_control_order = 200;
    public function column_control($row) {

        $get = $_GET;
        $get['app'] = 'inventorydepth';$get['ctl'] = 'shop';$get['act'] = 'premove'; $get['p'][0] = $row['id'];
        $url = "index.php?".http_build_query($get);

        $finder_id = $get['_finder']['finder_id'];
        $control = <<<EOF
    <a href='javascript:void(0);' onclick='javascript:W.page("$url",{
        async:false
    });finderGroup["$finder_id"].refresh();'>移除</a>
EOF;
        return $control;
    }
}
