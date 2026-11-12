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


class desktop_finder_builder_packet extends desktop_finder_builder_prototype{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


    function main(){

        $this->controller->pagedata['data'] = $this->get_views();
		/** 判断是否要显示归类视图 **/
		$is_display_packet = 'false';
		foreach ($this->controller->pagedata['data'] as $arr){
			if ($arr['addon'])
			{
				$is_display_packet = 'true';
				break;
			}
			else
			{
				$is_display_packet = 'false';
			}
		}
		$this->controller->pagedata['haspacket'] = ($is_display_packet=='true') ? true : false;
        $this->controller->display('finder/view/packet.html','desktop');
            
    }

}
