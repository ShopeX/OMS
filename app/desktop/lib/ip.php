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

class desktop_ip{
    /**
     * IP限制
     *
     * @return void
     * @author 
     */
    public function limit($ip_white)
    {
        $ip_addr = array();
        if ($ip_white['ip_addr']) $ip_addr = array_filter(explode("\n", $ip_white['ip_addr']));
        if (!array_intersect($this->seg(), $ip_addr)) {
            return true;
        }

        return false;
    }

    /**
     * IP段
     *
     * @return void
     * @author 
     */
    public function seg()
    {
        $ip_list = array();

        $ip = $this->getIp();
        if(strpos($ip, ',')) {
            list($ip, ) = explode(',', $ip);
        }
        $ip_seg = explode('.', $ip);

        $ip_list[] = $ip;
        $ip_list[] = $ip_seg[0].'.'.$ip_seg[1].'.'.$ip_seg[2].'.'.'*';
        $ip_list[] = $ip_seg[0].'.'.$ip_seg[1].'.*'.'.*';

        return $ip_list;
    }

    public function getIp() {
        return kernel::single('base_component_request')->get_server('HTTP_X_FORWARDED_FOR') ?
            : kernel::single('base_component_request')->get_server('REMOTE_ADDR');;
    }
}
