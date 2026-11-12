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

class dealer_finder_extend_filter_betc
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    public function get_extend_colums()
    {
        $bbuMdl  = app::get('dealer')->model('bbu');
        $bbuList = [];
        foreach ($bbuMdl->getList('*') as $k => $v) {
            $bbuList[$v['bbu_id']] = $v['bbu_name'] . ' (' . $v['bbu_code'] . ')';
        }

        $db['betc'] = array(
            'columns' => array(
                'bbu_id' => array(
                    'type'          => $bbuList,
                    'label'         => '销售团队',
                    'filtertype'    => 'normal',
                    'filterdefault' => true,
                ),
            ),
        );

        return $db;
    }
}
