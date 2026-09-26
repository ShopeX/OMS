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
 * @author ykm 2021/7/15 14:42:57
 * @describe 模板数据获取
 */
class erpapi_logistics_matrix_wxshipin_request_template extends erpapi_logistics_request_template
{

    /**
     * syncStandardTpl
     * @return mixed 返回值
     */

    public function syncStandardTpl()
    {
        $this->title = '获取微信视频号标准模板';

        $rs = $this->requestCall(STORE_WAYBILL_STANDARD_TEMPLATE, []);

        if ($rs['rsp'] == 'succ' && $rs['data']) {
            $resData      = json_decode($rs['data'], 1);
            $rs['data']   = [];
            $templateList = isset($resData['config']) && is_array($resData['config']) ? $resData['config'] : array();
            foreach ($templateList as $code => $_detail) {
                foreach ((array)$_detail as $_type => $v) {
                    if (!is_array($v) || empty($v['width']) || empty($v['height'])) {
                        continue;
                    }
                    $rs['data'][] = array(
                        'tpl_index'       => 'standard' . '-' . $_type . '_' . $code,
                        'cp_code'         => $code,
                        'out_template_id' => $_type . '_' . $code,
                        'template_name'   => $code . $v['desc'] . '(微信视频号)',
                        'template_type'   => 'wxshipin_standard',
                        'template_data'   => json_encode([$code => $v]),
                        'template_width'  => $v['width'],
                        'template_height' => $v['height'],
                    );
                }
            }
        } else {
            $rs['data'] = [];
        }
        return $rs;
    }

    /**
     * syncUserTpl
     * @return mixed 返回值
     */
    public function syncUserTpl()
    {
        $this->title = '获取微信视频号用户模板';

        $rs = $this->requestCall(STORE_WAYBILL_USER_TEMPLATE, []);

        if ($rs['rsp'] == 'succ' && $rs['data']) {
            $resData    = json_decode($rs['data'], 1);
            $rs['data'] = [];
            $totalList  = isset($resData['total_template']) && is_array($resData['total_template']) ? $resData['total_template'] : array();
            foreach ($totalList as $k => $tList) {
                $templateList = isset($tList['template_list']) && is_array($tList['template_list']) ? $tList['template_list'] : array();
                foreach ($templateList as $k => $v) {

                    $v['delivery_id'] = $tList['delivery_id'];

                    $rs['data'][] = array(
                        'tpl_index'       => 'user' . '-' . $v['template_id'],
                        'cp_code'         => $tList['delivery_id'],
                        'out_template_id' => $v['template_id'],
                        'template_name'   => $v['template_name'] . '(微信视频号)',
                        'template_type'   => 'wxshipin_user',
                        'template_data'   => json_encode($v),
                        'template_width'  => '76', // 接口没有值，给个默认值
                        'template_height' => '130', // 接口没有值，给个默认值
                        'is_default'      => $v['is_default'],
                    );
                }
            }
        } else {
            $rs['data'] = [];
        }
        return $rs;
    }

    /**
     * 获取UserDefinedTpl
     * @param mixed $params 参数
     * @return mixed 返回结果
     */
    public function getUserDefinedTpl($params)
    {
        return ['rsp' => 'succ'];
    }

    // 查询电子面单订购关系
    /**
     * 获取UserSubscribesTpl
     * @param mixed $value value
     * @return mixed 返回结果
     */
    public function getUserSubscribesTpl($value = '')
    {
        $this->title = '获取微信视频号已开通的快递服务';

        $rs = $this->requestCall(STORE_STANDARD_XHS_SEARCH, []);

        return $rs;
    }
}
