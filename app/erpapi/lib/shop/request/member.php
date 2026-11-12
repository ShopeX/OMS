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

class erpapi_shop_request_member extends erpapi_shop_request_abstract
{
    /**
     * 获取Ouid
     * @param mixed $uname uname
     * @return mixed 返回结果
     */
    public function getOuid($uname)
    {
        $title = '获取买家open_uid';
        $params = array(
            'api' => 'taobao.user.openuid.getbynick',
            'data' => json_encode([
                'buyer_nicks'=>$uname
            ])
        );
        $rsp   = $this->__caller->call(TAOBAO_COMMON_TOP_SEND, $params, array(), $title, 10, $uname);
        $return = [];
        if($rsp['data']) {
            $data = json_decode($rsp['data'], 1);
            if(is_array($data) 
                && is_array($data['user_openuid_getbynick_response']) 
                && $data['user_openuid_getbynick_response']['open_uids']
                && $data['user_openuid_getbynick_response']['open_uids']['open_uid_info']
            ) {
                foreach ($data['user_openuid_getbynick_response']['open_uids']['open_uid_info'] as $key => $value) {
                    $return[] = $value['buyer_open_uid'];
                }
            }
        }
        return $return;
    }

    /**
     * 京东 pin 转 xid / ouid（非 360buy 平台默认不支持）
     *
     * @param array $sdf
     * @return array
     */
    public function pin2xid(array $sdf)
    {
        return array('rsp' => 'fail', 'msg' => '当前平台不支持pin转xid', 'data' => array());
    }
}
