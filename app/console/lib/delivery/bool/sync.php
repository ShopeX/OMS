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
 * @author ykm 2022/8/25 16:00:48
 * @describe 发货单布尔型同步状态
 */

class console_delivery_bool_sync {
    #发起请求
    const __SEND_CODE         = 0x001;
    #请求成功
    const __SEND_SUCC         = 0x002;
    #请求失败
    const __SEND_FAIL         = 0x004;
    #取消失败
    const __CANCEL_FAIL       = 0x008;
    #取消成功
    const __CANCEL_SUCC       = 0x010;
    #查询失败
    const __SEARCH_FAIL       = 0x020;
    #查询成功
    const __SEARCH_SUCC       = 0x040;

    public function getBoolSync($filter) {
        $where = array();
        if($filter['in']) {
            $in = 0;
            foreach((array)$filter['in'] as $val) {
                $in = $in | $val;
            }
            $where[] = 'sync & ' . $in . ' = ' . $in;
        }
        if($filter['out']) {
            $out = 0;
            foreach((array)$filter['out'] as $val) {
                $out = $out | $val;
            }
            $where[] = '!(sync & ' . $out . ')';
        }
        if(empty($where)) {
            return array();
        }
        $sql = 'select sync from sdb_ome_delivery where ' . implode(' and ', $where) . ' group by sync';
        $boolData = kernel::database()->select($sql);
        $boolStatus = array('-1');
        foreach($boolData as $val) {
            $boolStatus[] = $val['sync'];
        }
        return $boolStatus;
    }
}