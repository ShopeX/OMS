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

class omeauto_controller extends desktop_controller{

    /**
     * Whether a string is a safe omeauto class name for dynamic resolution.
     *
     * @param mixed $className
     * @return bool
     */
    protected function isSafeOmeautoClass($className)
    {
        return is_string($className) && preg_match('/^omeauto_[a-zA-Z0-9_]+$/', $className) === 1;
    }

    /**
     * ajax_data may only resolve auto-type condition classes (CNVD omeauto eval follow-up).
     *
     * @param mixed $className
     * @return bool
     */
    protected function isSafeAjaxConditionClass($className)
    {
        return is_string($className) && preg_match('/^omeauto_auto_type_[a-zA-Z0-9_]+$/', $className) === 1;
    }

    /**
     * Explicit method whitelist for condition UI AJAX (case-insensitive).
     *
     * @param mixed $method
     * @return bool
     */
    protected function isSafeAjaxMethod($method)
    {
        if (!is_string($method) || preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $method) !== 1) {
            return false;
        }
        $allowed = array('get_shop_by_type', 'getshopbytype');
        return in_array(strtolower($method), $allowed, true);
    }

    function show_condition_detail($condition,$data=array()){
        if (!$this->isSafeOmeautoClass($condition)) {
            echo '';
            return;
        }
        echo kernel::single('omeauto_auto')->get_condition_detail($condition,$data);
    }

    /*
     * 所有的条件各自的特殊的异步都走这边，第一个参数是所属的条件的类名，第二个参数是该类的某个方法（获取html界面），后面所有的参数都作为第二个参数方法的参数
     */
    function ajax_data(){
        $params = func_get_args();
        $condition = array_shift($params);
        $method = array_shift($params);

        if (!$this->isSafeAjaxConditionClass($condition) || !$this->isSafeAjaxMethod($method)) {
            echo '';
            return;
        }
        if (!class_exists($condition)) {
            echo '';
            return;
        }

        $obj = kernel::single($condition);
        if (!is_object($obj) || !is_callable(array($obj, $method))) {
            echo '';
            return;
        }

        $data = call_user_func_array(array($obj, $method), array_values($params));
        echo $data;
    }
}
