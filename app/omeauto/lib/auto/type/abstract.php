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
 *
 */

abstract class omeauto_auto_type_abstract{
    
    /**
     * 保存规则内容
     * 
     * @var Array
     */
    protected $content;

    /**
     * Vue 模式开关
     * 
     * @var bool
     */
    protected $vue_mode = false;

    /**
     * 设置 Vue 模式
     */
    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }
    
    /**
     * 获取输入UI
     * 
     * @param mixed $val
     * @return String|Array  vue_mode=false返回HTML字符串，vue_mode=true返回表单schema数组
     */
    public function getUI($val) {

        $tpl = kernel::single('base_render');
        $role = array_shift($val);
        $tpl->pagedata['role'] = $role;
        $tpl->pagedata['init'] = json_decode(base64_decode($role), true);
        if (method_exists($this, '_prepareUI')) {
            $this->_prepareUI($tpl, $val);
        }

        if ($this->vue_mode) {
            // Vue 模式：返回结构化数据（不 fetch 模板）
            return $tpl->pagedata;
        }

        // Legacy HTML 模式
        return $tpl->fetch($this->getTemplateName(), 'omeauto');
    }


    /**
     * 获取用于输出的模板名
     * 
     * @param void
     * @return String
     */
    public function getTemplateName() {
        
        $className = strtolower(get_class($this));
        $className = str_replace('omeauto_auto_', '', $className);
        $fileName = sprintf('order/%s.html', str_replace('_', '/', $className));

        return $fileName;
    }
    
    /**
     * 创建规则
     * 
     */
    public function createRole($params) {
    
        $result = $this->checkParams($params);
        
        if ($result === true) {
            
            return json_encode(array('code' => 'SUCC', 'msg' => $this->roleToString($params)));
        } else {
            
            return json_encode(array('code' => 'FAIL', 'msg' => $result));
        }
    }
    
    /**
     * 设置已经创建好的配置内容
     * 
     */
   public function setRole($params) {
       
       $this->content = $params;
   }
}