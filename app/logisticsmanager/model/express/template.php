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

class logisticsmanager_mdl_express_template extends dbeav_model{    
    function getElements(){
        $elements = array();
        //获取快递单打印项的配置列表
        foreach(kernel::servicelist('ome.service.template') as $object=>$instance){
            if (method_exists($instance, 'getElements')){
                $tmp = $instance->getElements();
            }
            $elements = array_merge($elements, $tmp);
        }
        return $elements;
    }

    /**
     * 获得单据项
     * @param String $type 类型
     */
    public function getElementsItem($type) {
        $elements = array();
        $class = 'ome_service_template_' . $type;
        $instance = kernel::single($class);
        $elements = $instance->getElements();
        return $elements;
    }

    public function modifier_source($col, $list) {
        if($col == 'local' || !$col) {
            return '本地';
        }
        static $shopName = array();
        if($shopName[$col]) {
            return $shopName[$col];
        }
        $shopId = array();
        foreach($list as $val) {
            if($val['source'] && $val['source'] != 'local') {
                $shopId[$val['source']] = $val['source'];
            }
        }
        $shop = app::get('ome')->model('shop')->getList('shop_id, name', array('shop_id'=>$shopId));
        foreach($shop as $val) {
            $shopName[$val['shop_id']] = $val['name'];
        }
        return $shopName[$col];
    }

    /**
     * 获取打印模板列表
     * default模板置顶
     * 
     * @param string $type: ['normal','electron','cainiao','cainiao_standard','cainiao_user','delivery','stock']
     * @param string $control: ['default','lodop']
     * @return void
     * @author 
     */
    public function getPrintTemplates($type, $control = 'default')
    {
        $filter = array(
            'template_type' => $type, 
            'status'        => 'true', 
            'control_type'  => $control
        );

        $list = $this->getList('*',$filter,0,-1,'is_default DESC');

        $templates = array();
        foreach ($list as $value) {
            // 如果是lodop需要特殊转
            if ($value['control_type'] == 'lodop') {
                $value = $this->lodopTemplate($value);
            }

            $templates[$value['template_id']] = $value;
        }

        return $templates;
    }

    /**
     * summary
     * 
     * @return void
     * @author 
     */
    public function lodopTemplate($template)
    {
        $template['title']   = sprintf('打印%s', $template['template_name']);
        $template['plugins'] = array_filter(unserialize($template['template_data'])); unset($template['template_data']);
        $template['page'] = array(
            'width'      => $template['template_width'],
            'height'     => $template['template_height'],
            'type'       => $template['page_type'] ? $template['page_type'] : 0,//默认为0
            'file_id'    => $template['file_id'],
            'background' =>''
        );

        return $template;
    }

    public function saveTemplate($info)
    {
        $filter = [
            'template_id' => $info['template_id']
        ];
        $template = $this->db_dump($filter);

        if (!$template) {
            return '模板不存在';
        }

        if ($template['template_type'] == 'wxshipin_standard') {
            $templateSelect = @unserialize($template['template_select']);
            $templateSelect = is_array($templateSelect) ? $templateSelect : array();
            $customTplId = isset($info['wxshipin_custom_tpl_id']) ? intval($info['wxshipin_custom_tpl_id']) : 0;
            if ($customTplId) {
                if (!$this->hasWxshipinCustomConfig($template['template_data'])) {
                    return '微信视频号标准模板缺少 custom_config，请重新同步模板后再关联';
                }
                $customTpl = $this->db_dump(array('template_id' => $customTplId), 'template_id,template_type,control_type,template_data,status');
                if (!$customTpl || $customTpl['template_type'] != 'electron' || $customTpl['control_type'] != 'lodop' || $customTpl['status'] != 'true') {
                    return '请选择有效的 Lodop 电子面单模板';
                }
                $unsupported = $this->getWxshipinUnsupportedPlugins($customTpl['template_data']);
                if ($unsupported) {
                    return '所选 Lodop 模板包含暂不支持的控件：' . implode('、', $unsupported);
                }
                $templateSelect['wxshipin_custom_tpl_id'] = $customTplId;
            } else {
                unset($templateSelect['wxshipin_custom_tpl_id']);
            }
            $this->update(array('template_select' => $templateSelect ? serialize($templateSelect) : null), $filter);
            return true;
        }
        if ($template['template_type'] == 'wxshipin_user') {
            return '微信视频号用户模板不支持关联自定义区';
        }
        if (in_array($template['control_type'], ['youzan'])) {
            $template_select = @unserialize($template['template_select']) ?: [];
            $template_select['user_url'] = trim($info['customer_template_url']);            

            $upData = [
                'template_select' => serialize($template_select),
            ];

        } else {
            $template['template_data'] = @json_decode($template['template_data'] ,1);
            $template['template_data']['customerTemplateUrl'] = trim($info['customer_template_url']);
    
            $upData = [
                'template_data' => json_encode($template['template_data']),
            ];
        }

        $this->update($upData, $filter);
        return true;
    }

    public function hasWxshipinCustomConfig($templateData)
    {
        return (bool)$this->getWxshipinCustomConfig($templateData);
    }

    public function getWxshipinCustomConfig($templateData)
    {
        $data = @json_decode($templateData, true);
        if (!is_array($data)) {
            return array();
        }
        foreach ($data as $config) {
            if (is_array($config) && !empty($config['custom_config']['width']) && !empty($config['custom_config']['height'])) {
                return array(
                    'width' => floatval($config['custom_config']['width']),
                    'height' => floatval($config['custom_config']['height']),
                );
            }
        }
        return array();
    }

    public function getWxshipinUnsupportedPlugins($templateData)
    {
        $plugins = @unserialize($templateData);
        if (!is_array($plugins)) {
            return array('无效模板数据');
        }

        $supported = array('sp-input', 'sp-label', 'sp-image', 'sp-barcode', 'sp-qrcode', 'sp-table');
        $pluginNames = array(
            'sp-level' => '水平线',
            'sp-vertical' => '垂直线',
            'sp-rectangle' => '矩形',
        );
        $unsupported = array();
        foreach ($plugins as $plugin) {
            if (!is_array($plugin)) {
                $unsupported[] = '无效控件数据';
                continue;
            }
            $pluginType = isset($plugin['plugin']) ? $plugin['plugin'] : '';
            if (!$pluginType) {
                $unsupported[] = '无效控件数据';
                continue;
            }
            if (in_array($pluginType, $supported)) {
                if ($pluginType == 'sp-table') {
                    $supportedFields = array('name', 'number', 'bn', 'spec_info');
                    foreach ((array)(isset($plugin['tableHeader']) ? $plugin['tableHeader'] : array()) as $header) {
                        $field = isset($header['type']['value']) ? $header['type']['value'] : '';
                        if ($field && !in_array($field, $supportedFields)) {
                            $label = !empty($header['name']) ? $header['name'] : (isset($header['type']['label']) ? $header['type']['label'] : '');
                            $unsupported['sp-table-' . $field] = '商品列表字段' . ($label ? '（' . $label . '）' : '（' . $field . '）');
                        }
                    }
                }
                continue;
            }
            $name = isset($pluginNames[$pluginType]) ? $pluginNames[$pluginType] : (!empty($plugin['name']) ? $plugin['name'] : $pluginType);
            $unsupported[$pluginType] = $name;
        }
        return array_values($unsupported);
    }

    public function pre_recycle($rows)
    {
        $lodopIds = array();
        foreach ($rows as $row) {
            if ($row['control_type'] == 'lodop' && $row['template_type'] == 'electron') {
                $lodopIds[] = intval($row['template_id']);
            }
        }
        if (!$lodopIds) {
            return true;
        }

        $wxTemplates = $this->getList('template_name,template_select', array('template_type' => 'wxshipin_standard'));
        foreach ($wxTemplates as $wxTemplate) {
            $templateSelect = @unserialize($wxTemplate['template_select']);
            $templateSelect = is_array($templateSelect) ? $templateSelect : array();
            $customTplId = isset($templateSelect['wxshipin_custom_tpl_id']) ? intval($templateSelect['wxshipin_custom_tpl_id']) : 0;
            if ($customTplId && in_array($customTplId, $lodopIds)) {
                $this->recycle_msg = 'Lodop 模板已被微信视频号标准模板“' . $wxTemplate['template_name'] . '”关联，不能删除';
                return false;
            }
        }
        return true;
    }
}
