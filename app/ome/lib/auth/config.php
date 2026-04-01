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
 * Class ome_auth_config
 */
class ome_auth_config
{
    private $__config = array(
        'matrixonline' => array(
            'label' => '商派矩阵对接',
            'desc'  => '<ul class="matrix-desc">'
                . '<li>商派矩阵是商派提供的统一对接平台，快速对接主流电商平台（淘宝、天猫、京东、拼多多、抖音、快手等）。</li>'
                . '<li>自动处理订单同步、库存同步、发货回传等核心功能，安全稳定，<strong>无需自研接口</strong>。</li>'
                . '<li>首次使用赠送 <strong>500条订单流量</strong>，超出部分按增量包购买。</li>'
                . '<li><strong>订单流量价格：</strong><br/>'
                . '• 部署在商派聚石塔：<strong>1万订单/300元/年</strong>、<strong>10万订单/2000元/年</strong>、<strong>100万订单/10000元/年</strong><br/>'
                . '• 非部署在商派聚石塔：<strong>1万订单/1500元/年</strong>、<strong>10万订单/10000元/年</strong>、<strong>100万订单/50000元/年</strong></li>'
                . '<li>依据法律法规要求，使用商派矩阵需完成 <strong>企业实名认证</strong>。</li>'
                . '</ul>',
        ),
        'openapi'      => array(
            'label'    => '商家自研对接',
            'platform' => array(),
            'desc'     => '<ul class="openapi-desc openapi-desc-primary">'
                . '<li>通过 API 接口自研对接各电商平台及其他渠道，灵活控制。</li>'
                . '<li><a href="https://op.shopex.cn/doc_oms_dev/erpapi/shop/order.html" target="_blank">OMS 订单接口字段映射参考</a></li>'
                . '<li>更多内容参考各电商开放平台官方指导，技术问题请咨询对应平台。</li>'
                . '<li class="platform-label">主要开放平台链接：</li>'
                . '<ul class="openapi-platform-list">'
                . '<li class="platform-links">'
                . '<span><a href="https://open.taobao.com/supportCenter" target="_blank">淘宝/天猫</a></span>'
                . '<span><a href="https://open.jd.com/v2/#/doc/center" target="_blank">京东</a></span>'
                . '<span><a href="https://op.jinritemai.com/docs/guide-docs/213/14" target="_blank">抖音</a></span>'
                . '<span><a href="https://open.pinduoduo.com/application/document/browse?idStr=04DD98845AD2977D" target="_blank">拼多多</a></span>'
                . '<span><a href="https://open.kwaixiaodian.com/" target="_blank">快手</a></span>'
                . '<span><a href="https://open.xiaohongshu.com/home" target="_blank">小红书</a></span>'
                . '<span><a href="https://developer.meituan.com/ability-center/list" target="_blank">美团</a></span>'
                . '<span><a href="https://vop.vip.com/" target="_blank">唯品会</a></span>'
                . '<span><a href="https://open.dewu.com/#/" target="_blank">得物</a></span>'
                . '<span><a href="https://developers.weixin.qq.com/doc/store/shop/product/develop_guide.html" target="_blank">微信小店</a></span>'
                . '<span><a href="https://doc.youzanyun.com/resource/doc/3004" target="_blank">有赞</a></span>'
                . '<span><a href="https://doc.weimobcloud.com/word?menuId=46&childMenuId=47&tag=2970&isold=1&ccmenu=1" target="_blank">微盟</a></span>'
                . '<span><a href="https://open.suning.com/" target="_blank">苏宁</a></span>'
                . '<span><a href="https://open.aikucun.com/docCenter" target="_blank">爱库存</a></span>'
                . '</li>'
                . '</ul>'
                . '</ul>',
        ),
    );
    
    /**
     * __construct
     * @return mixed 返回值
     */

    public function __construct()
    {
        
        $openapi_platform = array(
            'website'    =>
                array(
                    'label'  => 'website',
                    'desc'   => 'desc',
                    'params' =>
                        array(
                            'website_url'             => 'API地址',
                            'website_response_secret' => 'SECRET',
                            'node_id'                 => '节点',
                        ),
                ),
            'website_d1m' =>
                array(
                    'label'  => 'website_d1m',
                    'desc'   => 'desc',
                    'params' =>
                        array(
                            'website_d1m_url'            => 'API地址',
                            'website_d1m_request_appkey' => 'D1M APPKEY',
                            'website_d1m_request_secret' => 'D1M SECRET',
                            'd1m_response_secret'        => 'SECRET',
                            'node_id'                    => '节点',
                        ),
                ),
            'website_v2'    =>
                array(
                    'label'  => 'website_v2',
                    'desc'   => '支持接收未支付订单',
                    'params' =>
                        array(
                            'website_url'             => 'API地址',
                            'website_response_secret' => 'SECRET',
                            'node_id'                 => '节点',
                        ),
                ),
            'b2b'    =>
                array(
                    'label'  => 'B2B',
                    'desc'   => 'B2B平台对接',
                    'params' =>
                        array(
                            'b2b_url'             => 'API地址',
                            'b2b_response_secret' => 'RESPONSE SECRET',
                            'node_id'                 => '节点',
                        ),
                ),
        );

        foreach (ome_shop_type::get_shop_type() as $key => $value) {
            if (isset($openapi_platform[$key])) {
                continue;
            }

            $openapi_platform[$key] = array(
                'label'  => $key,
                'desc'   => $value,
                'params' =>
                    array(
                        'website_url'             => 'API地址',
                        'website_response_secret' => 'SECRET',
                        'node_id'                 => '节点',
                    ),
            );
        }
        
        $this->__config['openapi']['platform'] = $openapi_platform;
        
    }
    
    /**
     * 获取AdapterList
     * @return mixed 返回结果
     */
    public function getAdapterList()
    {
        $adapter = array();
        
        foreach ($this->__config as $key => $value) {
            $adapter[] = array('value' => $key, 'label' => $value['label'], 'desc' => $value['desc']);
        }
        
        return $adapter;
    }
    
    /**
     * 获取PlatformList
     * @param mixed $adapter adapter
     * @return mixed 返回结果
     */
    public function getPlatformList($adapter, $shop_type = '')
    {
        $platform = array();
        
        foreach ($this->__config[$adapter]['platform'] as $key => $value) {
            if ($shop_type && $key != $shop_type) {
                continue;
            }
            $platform[] = array('value' => $key, 'label' => $value['label']);
        }
        
        return $platform;
    }
    
    /**
     * 获取PlatformParam
     * @param mixed $platform platform
     * @return mixed 返回结果
     */
    public function getPlatformParam($platform)
    {
        return $this->__config['openapi']['platform'][$platform]['params'];
    }
}
