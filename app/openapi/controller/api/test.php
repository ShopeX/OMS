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

use app\desktop\lib\annotation\RequirePermission;

class openapi_ctl_api_test extends desktop_ctl_api_base
{
    /**
     * GET/POST - 接口测试分派
     * ?app=openapi&ctl=api_test&act=test&action=list|form|execute
     */
    #[RequirePermission('setting_openapi_basic')]
    public function test(): void
    {
        $action = $_GET['action'] ?? $_POST['action'] ?? 'list';

        try {
            switch ($action) {
                case 'list':
                    $this->succ(['list' => $this->apiNameList()]);
                    return;
                case 'form':
                    $this->succ($this->apiForm((string) ($_GET['api_name'] ?? $_POST['api_name'] ?? '')));
                    return;
                case 'execute':
                    $this->succ($this->executeApiCall());
                    return;
                default:
                    $this->fail('Invalid action: ' . $action);
            }
        } catch (Throwable $e) {
            $this->fail($e->getMessage(), 500);
        }
    }

    /** 兼容 act=index 调用 */
    #[RequirePermission('setting_openapi_basic')]
    public function index(): void
    {
        $this->test();
    }

    /**
     * 接口清单 —— 照搬 legacy openapi_ctl_admin_test::test()
     *
     * @return array<int,string>
     */
    private function apiNameList(): array
    {
        $list = [];
        $conf = openapi_conf::getMethods();

        foreach ($conf as $k => $v) {
            $paramsObj = $this->paramsObject((string) $k);
            foreach ($v['methods'] as $method => $vv) {
                if ($paramsObj->getAppParams($method)) {
                    $list[] = $k . '.' . $method;
                }
            }
        }

        return $list;
    }

    /**
     * 参数表单 —— 照搬 legacy ::ajaxResult()
     *
     * @param string $apiName 形如 app.class.method
     * @return array{list: mixed, description: mixed, post: array{apiName: string}}
     */
    private function apiForm(string $apiName): array
    {
        if ($apiName === '') {
            return ['list' => [], 'description' => [], 'post' => ['apiName' => '']];
        }

        $pos = strrpos($apiName, '.');
        $k = substr($apiName, 0, $pos);
        $function = substr($apiName, $pos + 1);

        $obj = $this->paramsObject((string) $k);

        return [
            'list'        => $obj->getAppParams($function),
            'description' => $obj->description($function),
            'post'        => ['apiName' => $apiName],
        ];
    }

    /**
     * RPC 代理调用 —— 照搬 legacy ::result()
     *
     * 与 legacy 等价说明：legacy 的 `$http->set_timeout($time_out)->post($url, $params, $headers)`
     * 中 `$time_out` / `$headers` 均为未定义变量（= null），等价于不设置超时与请求头，
     * 故此处直接 `post($url, $params)`。
     *
     * @return array{response: string}
     */
    private function executeApiCall(): array
    {
        $url = kernel::base_url(1) . kernel::url_prefix() . '/openapi/rpc/service/';
        $token = (string) ($_POST['token'] ?? '');
        $method = (string) ($_POST['method1'] ?? '');
        $dataFormat = (string) ($_POST['data_format'] ?? '');

        $params = $_POST;
        unset($params['token'], $params['method1']);

        $params['ver'] = 1;
        $params['method'] = $method;
        $params['type'] = $dataFormat;
        $params['charset'] = 'utf-8';

        if (!empty($params['items'])) {
            $tempe = [];
            foreach (explode(';', (string) $params['items']) as $item) {
                if (!$item) {
                    continue;
                }
                $tempd = [];
                foreach (explode(',', $item) as $tempb) {
                    $tempc = explode(':', $tempb);
                    $tempd[$tempc[0]] = $tempc[1] ?? '';
                }
                $tempe[] = $tempd;
            }
            $params['items'] = json_encode($tempe);
        }

        $params['sign'] = $this->genSign($params, $token);

        $http = kernel::single('base_httpclient');
        $response = $http->post($url, $params);

        return ['response' => $response];
    }

    /**
     * 参数对象 —— 照搬 legacy 的 kernel::single 兜底逻辑
     *
     * @param string $k 形如 app.class
     * @return object
     */
    private function paramsObject(string $k)
    {
        try {
            return kernel::single('openapi_api_params_v1_' . $k);
        } catch (Throwable $e) {
            list($app, $class) = explode('.', $k);
            return kernel::single($app . '_openapi_params_v1_' . $class);
        }
    }

    /**
     * 签名 —— 照搬 legacy ::gen_sign()
     *
     * @param array<string,mixed> $params
     * @param string              $token
     * @return string|false
     */
    private function genSign(array $params, string $token)
    {
        if (!$token) {
            return false;
        }

        return strtoupper(md5(strtoupper(md5($this->assemble($params))) . $token));
    }

    /**
     * 签名串拼装 —— 照搬 legacy ::assemble()
     *
     * @param mixed $params
     * @return string|null
     */
    private function assemble($params)
    {
        if (!is_array($params)) {
            return null;
        }

        ksort($params, SORT_STRING);

        $sign = '';
        foreach ($params as $key => $val) {
            if (is_null($val)) {
                continue;
            }
            if (is_bool($val)) {
                $val = $val ? 1 : 0;
            }
            $sign .= $key . (is_array($val) ? $this->assemble($val) : $val);
        }

        return $sign;
    }
}
