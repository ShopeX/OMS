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

$db['express_template'] = array(
    'columns' =>
        array(
            'template_id' =>
                array(
                    'type' => 'int unsigned',
                    'required' => true,
                    'pkey' => true,
                    'extra' => 'auto_increment',
                    'label' => 'ID',
                    'width' => 75,
                    'editable' => false,
                ),
            'out_template_id' =>
                array(
                    'type' => 'varchar(128)',
                    'editable' => false,
                    'default' => '0',
                    'comment' => '外部模板ID'
                ),
            'template_name' =>
                array(
                    'type' => 'varchar(100)',
                    'required' => true,
                    'default' => '',
                    'label' => '模板名称',
                    'width' => 290,
                    'unique' => true,
                    'editable' => true,
                    'in_list' => true,
                    'default_in_list' => true,
                    'searchtype' => 'has',
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'order' => 10,
                ),
            'template_type' =>
                array(
                    'type' => array(
                        'normal' => '普通面单',
                        'electron' => '电子面单',
                        'cainiao' => '菜鸟面单',
                        'cainiao_standard' => '菜鸟面单',
                        'cainiao_user' => '菜鸟面单', #与菜鸟标准面单组合使用
                        'pdd_standard' => '拼多多面单',
                        'pdd_user' => '拼多多面单',
                        'delivery' => '发货面单',
                        'stock' => '备货面单',
                        'jd_standard' => '京东面单',
                        'jd_user' => '京东面单',
                        'douyin_standard' => '抖音面单',
                        'douyin_user' => '抖音面单',
                        'kuaishou_standard' => '快手面单',
                        'kuaishou_user' => '快手面单',
                        'wphvip_standard' => '唯品会vip面单',
                        'wphvip_user' => '唯品会vip面单',
                        'dewu_ppzf'      => '得物面单',
                        'dewu_ppzf_zy'     => '得物自研面单',
                        'sf' => '顺丰面单',
                        'xhs_standard'=>'小红书面单',
                        'xhs_user'=>'小红书面单',
                        'aikucun_standard'=>'爱库存面单',
                        'aikucun_user'=>'爱库存自定义面单',
                        'wxshipin_standard'=>'微信视频号面单',
                        'wxshipin_user'=>'微信视频号面单',
                        'meituan4bulkpurchasing_user'=>'美团电商面单',
                        'youzan_standard'=> '有赞面单',
                        'yilianyun'=> '易联云',
                    ),
                    'required' => true,
                    'default' => 'normal',
                    'label' => '面单类型',
                    'width' => 110,
                    'editable' => true,
                    'in_list' => true,
                    'default_in_list' => true,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'order' => 20,
                ),
            'page_type' =>
                array(
                    'type' => 'tinyint unsigned',
                    'required' => true,
                    'default' => '1',
                    'label' => '纸张类型',
                    'width' => 110,
                ),
            'control_type' =>
                array(
                    'type' => 'varchar(32)',
                    'required' => true,
                    'default' => 'shopexplugin',
                    'label' => '控件类型',
                    'width' => 110,
                    'default_in_list' => true,
                    'in_list' => true,
                ),
            'status' =>
                array(
                    'type' => 'bool',
                    'default' => 'true',
                    'label' => '是否启用',
                    'width' => 80,
                    'editable' => true,
                    'in_list' => true,
                    'default_in_list' => true,
                    'order' => 30,
                ),
            'template_width' =>
                array(
                    'type' => 'float',
                    'default' => 100,
                    'label' => '宽度',
                    'required' => true,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                    'order' => 40,
                ),
            'template_height' =>
                array(
                    'type' => 'float',
                    'default' => 100,
                    'label' => '高度',
                    'required' => true,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                    'order' => 50,
                ),
            'file_id' =>
                array(
                    'type' => 'number',
                    'required' => true,
                    'editable' => false,
                    'default' => 0,
                    'label' => '背景文件ID',
                    'width' => 75,
                ),
            'is_logo' =>
                array(
                    'type' => 'bool',
                    'default' => 'true',
                    'label' => '打印LOGO',
                    'editable' => false,
                ),
            'template_select' =>
                array(
                    'type' => 'longtext',
                    'editable' => false,
                ),
            'template_data' =>
                array(
                    'type' => 'longtext',
                    'editable' => false,
                ),
            'is_default' => array(
                'type' => 'bool',
                'label' => app::get('ome')->_('默认'),
                'required' => true,
                'default' => 'false',
                //'default_in_list' => true,
                //'in_list'         => true,
                'width' => 'auto',
                'order' => 90,
            ),
            'aloneBtn' => array(
                'type' => 'bool',
                'label' => app::get('ome')->_('独立按钮'),
                'required' => true,
                'default' => 'false',
                'default_in_list' => false,
                'in_list' => true,
                'width' => 'auto',
                'order' => 60,
            ),
            'btnName' => array(
                'type' => 'varchar(20)',
                'label' => app::get('ome')->_('独立按钮名称'),
                'default' => '',
                'default_in_list' => false,
                'in_list' => true,
                'width' => 'auto',
                'searchtype' => 'has',
                'filtertype' => 'yes',
                'filterdefault' => true,
                'order' => 70,
            ),
            'source' =>
                array(
                    'type' => 'varchar(32)',
                    'editable' => false,
                    'label' => '模板来源',
                    'default_in_list' => true,
                    'in_list' => true,
                    'default' => 'local'
                ),
            'cp_code' => array(
                'type' => 'varchar(32)',
                'editable' => false,
            ),
        ),
    'index' =>
        array(
            'ind_out_template_id' => array(
                'columns' => array(
                    0 => 'out_template_id',
                ),
            ),
        ),
    'comment' => '面单模板表',
    'engine' => 'innodb',
    'version' => '$Rev:  $',
);