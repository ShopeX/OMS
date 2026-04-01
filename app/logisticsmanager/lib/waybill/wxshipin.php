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

class logisticsmanager_waybill_wxshipin
{

    /**
     * service_code
     * @param mixed $param param
     * @return mixed 返回值
     */
    public function service_code($param)
    {
        $cpCode  = $param['logistics'];
        $service = [
            /*
            'ZTO' => array(
                'site_code' => array(
                    'text'       => '网点编码',
                    'code'       => 'site_code',
                    'input_type' => 'input',
                    // 'require'    => 'true',
                ),
                'acct_id'   => array(
                    'text'       => '电子面单账号id',
                    'code'       => 'acct_id',
                    'input_type' => 'input',
                    // 'require'    => 'true',
                ),
                'shop_id'   => array(
                    'text'       => '店铺id',
                    'code'       => 'shop_id',
                    'input_type' => 'input',
                    // 'require'    => 'true',
                ),
            ),
            'YTO' => array(
                'site_code' => array(
                    'text'       => '网点编码',
                    'code'       => 'site_code',
                    'input_type' => 'input',
                    // 'require'    => 'true',
                ),
                'acct_id'   => array(
                    'text'       => '电子面单账号id',
                    'code'       => 'acct_id',
                    'input_type' => 'input',
                    // 'require'    => 'true',
                ),
                'shop_id'   => array(
                    'text'       => '店铺id',
                    'code'       => 'shop_id',
                    'input_type' => 'input',
                    // 'require'    => 'true',
                ),
            ),
            'YUNDA' => array(
                'site_code' => array(
                    'text'       => '网点编码',
                    'code'       => 'site_code',
                    'input_type' => 'input',
                    // 'require'    => 'true',
                ),
                'acct_id'   => array(
                    'text'       => '电子面单账号id',
                    'code'       => 'acct_id',
                    'input_type' => 'input',
                    // 'require'    => 'true',
                ),
                'shop_id'   => array(
                    'text'       => '店铺id',
                    'code'       => 'shop_id',
                    'input_type' => 'input',
                    // 'require'    => 'true',
                ),
            ),
            'JTSD' => array(
                'site_code' => array(
                    'text'       => '网点编码',
                    'code'       => 'site_code',
                    'input_type' => 'input',
                    // 'require'    => 'true',
                ),
                'acct_id'   => array(
                    'text'       => '电子面单账号id',
                    'code'       => 'acct_id',
                    'input_type' => 'input',
                    // 'require'    => 'true',
                ),
                'shop_id'   => array(
                    'text'       => '店铺id',
                    'code'       => 'shop_id',
                    'input_type' => 'input',
                    // 'require'    => 'true',
                ),
            ),
            'STO' => array(
                'site_code' => array(
                    'text'       => '网点编码',
                    'code'       => 'site_code',
                    'input_type' => 'input',
                    // 'require'    => 'true',
                ),
                'acct_id'   => array(
                    'text'       => '电子面单账号id',
                    'code'       => 'acct_id',
                    'input_type' => 'input',
                    // 'require'    => 'true',
                ),
                'shop_id'   => array(
                    'text'       => '店铺id',
                    'code'       => 'shop_id',
                    'input_type' => 'input',
                    // 'require'    => 'true',
                ),
            ),
            */
        ];
        return isset($service[$cpCode]) ? $service[$cpCode] : [];

    }

    /**
     * 打印组件三个接口
     * 获取打印数据 /logistics/waybillApply
     * 获取标准模板 /logistics/templateList
     * 获取自定义模板 /logistics/customTemplateList
     * @return array [description]
     */
    public function template_cfg()
    {
        $arr = array(
            'template_name' => '微信视频号',
            'shop_name'     => '微信视频号',
            'print_url'     => 'https://support.weixin.qq.com/cgi-bin/mmsupportacctnodeweb-bin/pages/e4TWKgMu17AalV2l',
            // windows：https://res.wx.qq.com/shop/print/ChannelsShopPrintClient-setup.exe
            // mac：https://mmec-shop-1258344707.cos.ap-shanghai.myqcloud.com/shop/print/ChannelsShopPrintClient.dmg
            // 'template_url'  => '',
            'shop_type'     => 'wxshipin',
            'control_type'  => 'wxshipin',
            'request_again' => false,
        );
        return $arr;
    }

    /**
     * 获取物流公司编码
     * @param Sring $logistics_code 物流代码
     * @return array
     */
    public function logistics($logistics_code = '')
    {
        $logistics = [
            'ZTO'   => ['code' => 'ZTO', 'name' => '中通'],
            'YTO'   => ['code' => 'YTO', 'name' => '圆通'],
            'YUNDA' => ['code' => 'YUNDA', 'name' => '韵达'],
            'JTSD'  => ['code' => 'JTSD', 'name' => '极兔'],
            'STO'   => ['code' => 'STO', 'name' => '申通'],
            'SF'    => ['code' => 'SF', 'name' => '顺丰'],
            'JD'    => ['code' => 'JD', 'name' => '京东'],
            'EMS'   => ['code' => 'EMS', 'name' => '中国邮政'],
            'CNSD'  => ['code' => 'CNSD', 'name' => '菜鸟速递(丹鸟)'],
            'DBKD'  => ['code' => 'DBKD', 'name' => '德邦快递'],
            'SXJD'  => ['code' => 'SXJD', 'name' => '顺心捷达'],
            'ZTOKY' => ['code' => 'ZTOKY', 'name' => '中通快运'],
            'YDKY'  => ['code' => 'YDKY', 'name' => '韵达快运'],
            'ANEKY' => ['code' => 'ANEKY', 'name' => '安能物流'],
            'BTWYL' => ['code' => 'BTWYL', 'name' => '百世快运'],
            'KYSY'  => ['code' => 'KYSY', 'name' => '跨越速运'],
            'YMDD'  => ['code' => 'YMDD', 'name' => '壹米滴答'],
            'RJTCSD' => ['code' => 'RJTCSD', 'name' => '锐界同城速递'],
            'ZJS'   => ['code' => 'ZJS', 'name' => '宅急送'],
            'XFEX'  => ['code' => 'XFEX', 'name' => '信丰物流'],
            'JGSD'  => ['code' => 'JGSD', 'name' => '京广速递'],
        ];

        if (!empty($logistics_code)) {
            return $logistics[$logistics_code];
        }
        return $logistics;
    }
}
