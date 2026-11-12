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

class console_finder_inventory_snapshot
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    public $column_opt       = '操作';
    public $column_opt_order = 1;
    /**
     * column_opt
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_opt($row)
    {
        $buttons = [];

        $url = 'index.php?app=desktop&act=alertpages&goto='.urlencode(sprintf("index.php?app=console&ctl=admin_inventory_snapshot&p[]=%s&act=itemIndex&finder_vid=%s",$row['id'], $_GET['finder_vid']));

        $buttons['items'] = sprintf('<a target="_blank" href="%s">查看明细</a>',$url);

        return implode(' | ', $buttons);
    }
}
