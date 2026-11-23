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
 * Created by PhpStorm.
 * User: yaokangming
 * Date: 2019/5/10
 * Time: 17:08
 */

class console_mdl_useful_life_log extends dbeav_model
{

    public function modifier_type_id($col) {
        $ioType = kernel::single('ome_iostock')->get_iostock_types();
        return $ioType[$col]['info'];
    }
}