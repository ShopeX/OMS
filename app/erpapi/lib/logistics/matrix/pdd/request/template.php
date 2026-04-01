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

class erpapi_logistics_matrix_pdd_request_template extends erpapi_logistics_request_template {

    /**
     * syncStandardTpl
     * @return mixed 返回值
     */
    public function syncStandardTpl() {
        return $this->succ();
    }

    /**
     * syncUserTpl
     * @return mixed 返回值
     */
    public function syncUserTpl() {
        $this->title = '获取拼多多模板';
        $rs = $this->requestCall(STORE_WAYBILL_STANDARD_TEMPLATE, array());
        if($rs['rsp'] == 'succ' && $rs['data']) {
            $data = json_decode($rs['data'], true);
            $rs['data'] = array();
            if($data) {
                foreach ($data as $val) {
                    $firstTemplateId = $val['standard_templates'][0]['standard_template_id'];
                    foreach ($val['standard_templates'] as $sVal) {
                        $templateType = $sVal['standard_waybill_type'] == 1 ? 'standard' : 'user';
                        $outTemplateId = $sVal['standard_template_id'] 
                            ? $sVal['standard_template_id'] 
                            : (int) $firstTemplateId;
                        $rs['data'][] = array(
                            'tpl_index' => $templateType . '-' . $outTemplateId,
                            'cp_code' => $val['wp_code'],
                            'out_template_id' => $outTemplateId,
                            'template_name' => $sVal['standard_template_name'] . '-' . $val['wp_code'] . '(拼多多)',
                            'template_type' => 'pdd_' . $templateType,
                            'template_data' => 'url:' . $sVal['standard_template_url']
                        );
                        if($sVal['standard_template_id']) {
                            $sdf = array(
                                'template_id' => $sVal['standard_template_id']
                            );
                            $udtRs = $this->requestCall(STORE_USER_DEFINE_AREA, $sdf);
                            if($udtRs['rsp'] == 'succ' && $udtRs['data']) {
                                $udt_data = json_decode($udtRs['data'], true);
                                $customResult = $udt_data['datas'];
                                if($customResult) {
                                    foreach ($customResult as $cr) {
                                        if($cr['keys']) {
                                            $rs['data'][] = array(
                                                'tpl_index' => 'user-' . $cr['custom_area_id'],
                                                'cp_code' => $val['wp_code'],
                                                'out_template_id' => $cr['custom_area_id'],
                                                'template_name' => $cr['custom_area_name'] . '-' . $val['wp_code'] . '(拼多多)',
                                                'template_type' => 'pdd_user',
                                                'template_data' => 'url:' . $sVal['standard_template_url'],
                                                'template_select' => array('standard_template_id' => $sVal['standard_template_id'])
                                            );
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } else {
            $rs['data'] = array();
        }
        return $rs;
    }

    /**
     * 获取UserDefinedTpl
     * @param mixed $params 参数
     * @return mixed 返回结果
     */
    public function getUserDefinedTpl($params) {
        $this->title = '获取模板(' . $this->__channelObj->channel['logistics_code'] . ')自定义区域内容';
        $template = kernel::single('logisticsmanager_waybill_pdd')->logistics($this->__channelObj->channel['logistics_code']);
        $templateId = $params['template_id'] ? $params['template_id'] : $template['template_id'];
        if(!$templateId) {
            return $this->succ('没有自定义ID');
        }
        $sdf = array(
            'template_id' => $templateId
        );
        $rs = $this->requestCall(STORE_USER_DEFINE_AREA, $sdf);
        if($rs['rsp'] == 'succ' && $rs['data']) {
            $data = json_decode($rs['data'], true);
            $customResult = $data['datas'];
            $rs['data'] = array();
            if($customResult) {
                foreach ($customResult as $val) {
                    $tplSelect = array();
                    if($val['keys']) {
                        foreach ($val['keys'] as $kVal) {
                            if($kVal['key_name']) {
                                $tplSelect[] = str_replace(array('data.'), '', trim($kVal['key_name']));
                            }
                        }
                        $rs['data'] = array(
                            'custom_area_id' => $val['custom_area_id'],
                            'custom_area_url' => $val['custom_area_url'],
                            'template_select' => $tplSelect
                        );
                        if(empty($params['custom_area_id']) || ($params['custom_area_id'] == $val['custom_area_id'])) {
                            break;
                        }
                    }
                }
            }
        } else {
            $rs['data'] = array();
        }
        return $rs;
    }
}