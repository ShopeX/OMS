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
class desktop_finder_builder_filter2packet extends desktop_finder_builder_prototype{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    function main(){
        $render = app::get('desktop')->render();
        $render->pagedata['app'] = $_GET['app'];
        $render->pagedata['act'] = $_GET['act'];
        $render->pagedata['ctl'] = $_GET['ctl'];
        $render->pagedata['model'] = $this->object_name;
        
        
        $filterquery = $_POST['filterquery'];
        $tabs = $this->get_views();
        if(is_array($tabs)&&$_GET['view']&&is_array($tabs[$_GET['view']])&&is_array($tabs[$_GET['view']]['filter'])){
            $filterquery = $filterquery.'&'.http_build_query($tabs[$_GET['view']]['filter']);
        }
        $render->pagedata['filterquery'] = $filterquery;
        echo $render->fetch('finder/view/filter2packet.html');
    }
}
