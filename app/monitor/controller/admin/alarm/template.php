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
 * @Author: xueding@shopex.cn
 * @Vsersion: 2022/10/13
 * @Describe: 监控预警控制
 */
class monitor_ctl_admin_alarm_template extends desktop_controller
{
    
    function index()
    {
        $actions   = array();
        $actions[] = array(
            'label'  => '新增',
            'href'   => 'index.php?app=monitor&ctl=admin_alarm_template&act=add',
        );
        
        $params = array(
            'title'               => '预警模板',
            'actions'             => $actions,
            'use_buildin_set_tag' => false,
            'use_buildin_recycle' => false,
        );
        
        $this->finder('monitor_mdl_event_template', $params);
    }
    
    //新增弹窗页
    function add()
    {
        $this->_edit('add');
    }
    
    //编辑弹窗页
    function edit($id)
    {
        $this->_edit('edit', $id);
    }
    
    //新增和编辑弹窗页的展示
    function _edit($action, $id = 0)
    {
        $eventTemplateMdl = app::get('monitor')->model('event_template');
        $data             = array();
        
        if ($id) {
            $data = $eventTemplateMdl->dump(array('template_id' => $id), '*');
        }
        $eventTemplateLib = kernel::single('monitor_event_template');
        
        //模板类型
        $eventType                   = $eventTemplateLib->getEventType();
        $this->pagedata['eventType'] = $eventType;
        // 默认模板内容映射
        $this->pagedata['eventDefaultContent'] = kernel::single('monitor_event_template')->getEventDefaultContent();
        $this->pagedata['data']      = $data;
        $sendType = $eventTemplateMdl->schema['columns']['send_type']['type'];
        $this->pagedata['send_type'] = $sendType;
//        $pmtKeywords = app::get('monitor')->getConf('monitor.check.pmt.name');
//        $this->pagedata['pmt_keywords'] = $pmtKeywords;
        $this->page('admin/alarm/event/template/addTemplate.html');
    }
    
    //保存
    function save()
    {
        $this->begin('index.php?app=monitor&ctl=admin_alarm_template&act=index');
        
        $eventTemplateMdl = app::get('monitor')->model('event_template');
        
        $data = $_POST;
        
        if (empty($data['template_bn']) || empty($data['template_name'])) {
            $this->end(false, '请填写模板名称和模板编号，请检查!');
        }
        
        if ($data['send_type'] == 'sms' && !$data['template_id']) {
            $data['status'] = '0';
        }
        
        if (empty($data['content'])) {
            $this->end(false, '模板内容不能为空，请检查!');
        }
//        $data['content'] = htmlspecialchars($data['content']);
        $data['content'] = str_replace('<script>','',$data['content']);
        $data['content'] = str_replace('</script>','',$data['content']);
    
    
        if ($data['template_id']) {
            $data['template_id'] = intval($data['template_id']);
        }
        
        if (isset($data['event_type']) && $data['event_type'] === 'order_fail_notify') {
            $data['send_type'] = 'email';
        }

        if (!$data['template_id']) {
            $row = $eventTemplateMdl->db_dump(array(
                'event_type' => $data['event_type'],
                'send_type'  => $data['send_type']
            ));
            if ($row) {
                $this->end(false, '模板类型不能重复，请检查!');
            }
        }
        
        $row = $eventTemplateMdl->db_dump(array('template_bn' => $data['template_bn']), '*');
        
        if ($row) {
            if ($data['template_id'] && ($row['template_id'] != $data['template_id'])) {
                $this->end(false, '模板编码不能重复，请检查!');
            } elseif (empty($data['template_id'])) {
                $this->end(false, '模板编码不能重复，请检查!');
            }
        }
        if ($data['pmt_name']) {
            app::get('monitor')->setConf('monitor.check.pmt.name',trim($data['pmt_name']));
        }

        $configResult = $eventTemplateLib->normalizeTemplateConfig(
            $data['event_type'],
            isset($data['template_config']) ? $data['template_config'] : array()
        );
        if (is_string($configResult)) {
            $this->end(false, $configResult);
        }
        if (isset($data['event_type']) && $data['event_type'] === 'order_fail_notify') {
            $oldConfig = array();
            $oldDisabled = 'true';
            if (!empty($data['template_id'])) {
                $oldRow = $eventTemplateMdl->db_dump(array('template_id' => intval($data['template_id'])), 'template_config,disabled');
                if ($oldRow) {
                    $oldDisabled = isset($oldRow['disabled']) ? $oldRow['disabled'] : 'true';
                    $oldConfig = $oldRow['template_config'];
                    if (is_string($oldConfig)) {
                        $un = @unserialize($oldConfig);
                        $oldConfig = is_array($un) ? $un : array();
                    }
                    if (!is_array($oldConfig)) {
                        $oldConfig = array();
                    }
                }
            }
            $disabled = isset($data['disabled']) ? $data['disabled'] : 'false';
            $configResult = $eventTemplateLib->mergeOrderFailNotifyRuntimeConfig(
                $configResult,
                $oldConfig,
                $disabled,
                $oldDisabled
            );
        }
        $data['template_config'] = $configResult;

        $result = $eventTemplateMdl->save($data);
        if (!$result) {
            $this->end(false, '保存失败');
        }
        
        $this->end(true, '保存成功');
    }
}
