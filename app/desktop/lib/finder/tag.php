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


class desktop_finder_tag{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $column_control = '编辑';
    function column_control($row){
       return '<a target="dialog::{title:\''.app::get('desktop')->_('链接编辑').'\', width:400, height:400}" href="index.php?app='.$_GET['app'].'&ctl='.$_GET['ctl'].'&act=tag_edit&type='.$_GET['type'].'&finder_id='.$_GET['_finder']['finder_id'].'&_finder[finder_id]='.$_GET['_finder']['finder_id'].'&_finder_name='.$_GET['_finder']['finder_id'].'&p[0]='.$row['tag_id'].'">'.app::get('desktop')->_('编辑').'</a>';
    }

}
