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
 * 插件接口
 *
 * @author xiayuanjun@shopex.cn
 * @version 1.0
 */

interface desktop_extracolumn_interface {

    /**
     * 定义要执行数据处理的主键ids
     *
     * @param Array $group 要处理的列表数组数据
     * @return Array
     */
    public function init($params);

    /**
     * 处理字段数据组信息
     *
     * @param null
     * @return Array
     */
    public function process($params);

    /**
     * 转换相应字段的内容
     *
     * @param null
     * @return Array
     */
    public function outPut();
}