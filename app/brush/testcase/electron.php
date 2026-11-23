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
 * @author ykm 2015-12-18
 * @describe 电子面单单号请求发起测试
 */
class electron extends PHPUnit_Framework_TestCase{

    public function ems() {
        $url = 'http://www.local_bugfix.com/index.php?app=brush&ctl=admin_print&act=toPrintExpre&status=ready&finder_id=5ab72d';
        $params = array(
            'delivery_id' => array(1)
        );
        return array($url, $params);
    }

    public function testElectron()
    {
        $paramMethod = 'ems';
        
        list($url, $params) = $this->$paramMethod();
        $response = $this->post($url, $params);
        
        print_r($response);
        exit();
        
        error_log(var_export($response, true));
    }

    private function post($url, $params) {
        $core_http = kernel::single('base_httpclient');
        $header['Cookie'] = 'Hm_lvt_e91082bfb57970bd361b8e1a38e23960=1452560021,1452568987,1452645305,1452733156; s=715a7fedab615e201816a85fd2c226d8; autologin=43200; XDEBUG_SESSION=netbeans-xdebug;Hm_lpvt_e91082bfb57970bd361b8e1a38e23960=1452739848';
        $response = $core_http->set_timeout(10)->post($url, $params, $header);
        return $response;
    }
}