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
 * 插件接口类
 *
 * @author xiayuanjun@shopex.cn
 * @version 1.0
 */

abstract class desktop_extracolumn_abstract {

    /**
     * 传入的关联主键id数组
     */
    private $__Ids = array();

    /**
     * 传入的列表页数据数组
     */
    private $__params = array();

    /**
     * 关联数据数组
     */
    private $__formatData = array();

    /**
     * 初始化相关参数
     * @param Array $params 要处理的列表数组数据
     */
    public function init($params){
        if(is_null($this->__Ids) || count($this->__Ids) <=0){
            foreach($params as $k => $param){
                if (strpos($param[$this->__pkey], '_') !== false) {
                    $this->__Ids[] = substr($param[$this->__pkey],0,strpos($param[$this->__pkey], '_'));
                } else {
                    $this->__Ids[] = $param[$this->__pkey];
                }
            }
        }
        $this->__params = $params;
    }

    /**
     * 转化关联的实际数据输出
     */
    public function outPut(){
        foreach($this->__params as $k =>$_param){
            if(isset($this->__formatData[$_param[$this->__pkey]]) || isset($this->__formatData[substr($_param[$this->__pkey],0,strpos($_param[$this->__pkey], '_'))])){
                $this->__params[$k][$this->__extra_column] = isset($this->__formatData[$_param[$this->__pkey]]) ? $this->__formatData[$_param[$this->__pkey]] : $this->__formatData[substr($_param[$this->__pkey],0,strpos($_param[$this->__pkey], '_'))];
            }else{
                $this->__params[$k][$this->__extra_column] ='';
            }
            
        }
        return $this->__params;
    }

    /**
     * 处理列表数组里的扩展字段
     * @param Array $params 要处理的列表数组数据
     * @return Array 转换扩展字段后的列表数据
     */
    public function process($params){
        $this->init($params);
    
        $this->__formatData = $this->associatedData($this->__Ids);

        return $this->outPut();
    }
}