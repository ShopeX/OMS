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
 * Date: 2019/4/30
 * Time: 15:57
 */

class console_mdl_useful_life extends dbeav_model
{
    public function updateNum($id, $num, $op='-') {
        if(!$id || !$num || !in_array($op, array('-','+'))) {
            return false;
        }
        if($op == '+') {
            $sql = 'update sdb_console_useful_life set num = num+' . $num . ' where life_id = ' . $id;
        } else {
            $sql = 'update sdb_console_useful_life set num = num-' . $num . ' where life_id = ' . $id;
        }
        if($this->db->exec($sql)){
            if($rs = $this->db->affect_row()){
                return $rs;
            }else{
                return true;
            }
        }else{
            return false;
        }
    }
}