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
 * CONFIG
 *
 * @category
 * @package
 * @author chenping<chenping@shopex.cn>
 * @version $Id: Z
 */
class channel_auth_config 
{
    // 应用类型 -> 对接适配器 -> 具体平台
    private $__config = array(
            'kuaidi' => [
                'matrix' => array(
                    'label' => '商派矩阵',
                    'desc' => '通过WEBSERVICE和第三方快递进行对接,如(快递鸟)',
                    'platform' => array(
                        'other' => array(
                            'label' => '其他',
                            'desc' => '未指定具体快递，需在绑定时指定',
                            'params' => array(),
                        ),
                        'kdn' => array(
                            'label' => '快递鸟',
                            'desc' => '物流跟踪（默认）',
                            'params' => array(),
                        ),
                        'sf' => array(
                            'label' => '顺丰',
                            'desc' => '物流跟踪（仅查询）',
                            'params' => array(),
                        ),
                    ),
                )
            ],
            'ticket' => [
                'openapi' => [
                    'label' => '本地API直联',
                    'desc' => '可通过API接口直接对接第三方渠道',
                    'platform' => [
                        'feisuo' => [
                            'label' => '飞梭',
                            'desc' => '通过API接口直接对接飞梭',
                            'params' => []
                        ]
                    ]
                ]
            ],
            'cloudprint' => [
                'openapi' => [
                    'label' => '本地API直联',
                    'desc' => '可通过API接口直接对接第三方渠道',
                    'platform' => [
                        'yilianyun' => [
                            'label' => '易联云',
                            'desc' => '通过API接口直接对接易联云',
                            'params' => []
                        ]
                    ]
                ]
            ],
            'sms' => [
                'openapi' => [
                    'label' => '本地API直联',
                    'desc' => '可通过API接口直接对接短信供应商',
                    'platform' => [
                        'shopex' => [
                            'label' => '商派短信',
                            'desc' => '复用商派短信发送能力',
                            'params' => [],
                        ],
                        'soap' => [
                            'label' => 'WSDL短信',
                            'desc' => '通过WSDL SOAP接口发送短信',
                            'params' => [
                                'wsdl_url' => 'WSDL地址',
                                'username' => '用户名',
                                'password' => '密码',
                                'companyCode' => '公司编码',
                                'timeMsg' => '定时发送时间',
                            ],
                        ],
                    ],
                ],
            ],
    );

    /**
     * 获取Config
     * @return mixed 返回结果
     */

    public function getConfig()
    {
        return $this->__config;
    }

    /**
     * 获取AdapterList
     * @param mixed $chanel_type chanel_type
     * @return mixed 返回结果
     */
    public function getAdapterList($chanel_type)
    {
        $adapter = array();

        if(!isset($this->__config[$chanel_type])){
            return $adapter;
        }
        
        foreach ($this->__config[$chanel_type] as $key => $value) {
            $adapter[] = array('value'=>$key, 'label'=>$value['label'], 'desc'=>$value['desc']);
        }

        return $adapter;
    }

    /**
     * 获取PlatformList
     * @param mixed $channel_type channel_type
     * @param mixed $adapter adapter
     * @return mixed 返回结果
     */
    public function getPlatformList($channel_type,$adapter)
    {
        $platform = array();
        
        if(!isset($this->__config[$channel_type]) || !isset($this->__config[$channel_type][$adapter])){
            return $platform;
        }

        foreach ($this->__config[$channel_type][$adapter]['platform'] as $key => $value) {
            $platform[] = array('value'=>$key,  'label'=>$value['label']);
        }

        return $platform;
    }

    /**
     * 获取PlatformParam
     * @param mixed $channel_type channel_type
     * @param mixed $adapter adapter
     * @param mixed $platform platform
     * @return mixed 返回结果
     */
    public function getPlatformParam($channel_type, $adapter, $platform)
    {
        $params = [];
        if(!isset($this->__config[$channel_type]) || !isset($this->__config[$channel_type][$adapter]) || !isset($this->__config[$channel_type][$adapter]['platform'][$platform])){
            return $params;
        }
        return $this->__config[$channel_type][$adapter]['platform'][$platform]['params'] ?: [];
    }
}
