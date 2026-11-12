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


class desktop_finder_favstar{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    function run($task,$ctl){
        $user_id = $ctl->user->user_id;
		$finder_model = (key($task));
		$rows = current($task);
		$old_fav_rows = app::get('desktop')->getConf('favstar.'.$finder_model.'.'.$user_id);
		$old_fav_rows = (array)$old_fav_rows;
		$fav_rows = array_filter(array_merge($old_fav_rows,$rows));

		app::get('desktop')->setConf('favstar.'.$finder_model.'.'.$user_id,$fav_rows);

    }
}
