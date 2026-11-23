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
 * Class console_mdl_vopreturn_items
 */
class console_mdl_vopreturn_items extends dbeav_model
{
    
    public function updateSplitNum($itemId, $num, $op = '+')
    {
        $updateSql = 'update sdb_console_vopreturn_items set split_num = ';
        if ($op == '+') {
            $updateSql .= "(split_num+{$num})";
            $filter    = "split_num+{$num}<=qty";
        } elseif ($op == '-') {
            $updateSql .= "(split_num-{$num})";
            $filter    = "split_num>={$num}";
        } else {
            return 0;
        }
        $updateSql .= ' where id = "' . $itemId . '" and ' . $filter;
        $this->db->exec($updateSql);
        return $this->db->affect_row();
    }
    
    public function updateReturnNum($itemId, $num, $op = '+')
    {
        $updateSql = 'update sdb_console_vopreturn_items set num = ';
        if ($op == '+') {
            $updateSql .= "(num+{$num})";
            $filter    = "num+{$num}<=qty";
        } elseif ($op == '-') {
            $updateSql .= "(num-{$num})";
            $filter    = "num>={$num}";
        } else {
            return 0;
        }
        $updateSql .= ' where id = "' . $itemId . '" and ' . $filter;
        $this->db->exec($updateSql);
        return $this->db->affect_row();
    }
}