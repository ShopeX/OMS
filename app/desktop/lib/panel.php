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

class desktop_panel {
    
    private $id;
    private $tmpl;
    private $controller;
    
    public function __construct(&$controller) {
        $this->controller = $controller;
    }
    
    public function show($object_name, $params) {
        $finder = new desktop_finder_builder_panel_filter($this->controller);

        foreach ($params as $k => $v) {
            $finder->$k = $v;
        }
        
        $app_id = substr($object_name, 0, strpos($object_name, '_'));
        $app = app::get($app_id);
        
        $finder->app = $app;
        $finder->setId($this->id);
        $finder->setFile($this->tmpl);
        
        $finder->work($object_name);
    }
    
    public function setId($id) {
        $this->id = $id;
    }
    
    public function setTmpl($tmpl) {
        $this->tmpl = $tmpl;
    }
    
}