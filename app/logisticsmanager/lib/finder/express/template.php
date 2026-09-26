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

class logisticsmanager_finder_express_template{
    var $addon_cols = "template_id,out_template_id,is_default,control_type,template_select,template_data";
    var $column_confirm = "操作";
    var $column_confirm_width = "150";
    var $column_confirm_order = COLUMN_IN_HEAD;
    function column_confirm($row){
        $id = $row[$this->col_prefix.'template_id'];
        $finder_id = $_GET['_finder']['finder_id'];
        if($row[$this->col_prefix . 'out_template_id']) {
            if ($row['template_type'] == 'wxshipin_standard') {
                return  <<<EOF
            <span class="lnk" onclick="new Dialog('index.php?app=logisticsmanager&ctl=admin_express_template&act=editField&p[0]=$id&finder_id=$finder_id',{title:'关联微信视频号自定义区',width:560,height:230})">关联自定义区</span>
EOF;
            }
            if (in_array($row['control_type'], ['xhs', 'youzan'])) {
                return  <<<EOF
            <span class="lnk" onclick="new Dialog('index.php?app=logisticsmanager&ctl=admin_express_template&act=editField&p[0]=$id&finder_id=$finder_id',{title:'快递面单编辑',width:500,height:140})">编辑</span>
EOF;
            }
            return '';
        }

        $type = $row['template_type'];

        if ($row[$this->col_prefix.'control_type'] == 'lodop') {
            $act = 'editLodopTemplate';
        } elseif ($type == 'delivery') {
            $act = 'editDeliveryTmpl';
            $act1 = 'copyDeliveryTmpl';
        } elseif ($type == 'stock') {
            $act = 'editStockTmpl';
            $act1 = 'copyStockTmpl';
        } else {
            $act  = 'editTmpl';
            $act1 = 'copyTmpl';
        }

        $button = '';
        if (in_array($type, array('delivery','stock'))) {
            if ($row[$this->col_prefix.'is_default'] == 'true') {
                $button = sprintf('<a href="index.php?app=logisticsmanager&ctl=admin_express_template&act=unsetDefault&p[0]=%s&finder_id=%s"><font class="c-red">取消默认</font></a>',$row['template_id'],$finder_id);
            } else {
                $button = sprintf('<a href="index.php?app=logisticsmanager&ctl=admin_express_template&act=setDefault&p[0]=%s&finder_id=%s">设置默认</a>',$row['template_id'],$finder_id);
            }
        }


        $button .= <<<EOF
        <a href="index.php?app=logisticsmanager&ctl=admin_express_template&act=$act&p[0]=$id&finder_id=$finder_id" class="lnk" target="_blank">编辑</a>
EOF;

        if ($row[$this->col_prefix.'control_type'] != 'lodop') {
            $button.= <<<EOF
                        <a href="index.php?app=logisticsmanager&ctl=admin_express_template&act=$act1&p[0]=$id&finder_id=$finder_id" class="lnk" target="_blank">复制</a>
EOF;
        }

        if (!in_array($type,array('delivery','stock'))) {


        $button.= <<<EOF
        <span onclick="window.open('index.php?app=logisticsmanager&ctl=admin_express_template&act=downloadTmpl&p[0]=$id&finder_id=$finder_id')" class="lnk">下载</span>
EOF;
        }

        return $button;
    }
    
    var $column_isdefault = "是否默认";
    var $column_isdefault_width = "60";
    var $column_isdefault_order = '32';
    var $column_isdefault_order_field = 'is_default';
    /**
     * column_isdefault
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_isdefault($row) {
        $is_default = $row[$this->col_prefix.'is_default'];
        $title = '';
        if ($is_default == 'false') {
            $title = '否';
        }
        else {
            $title = '是';
        }
        return $title;
    }


    public $column_custom_template_url = "自定义区";
    public $column_custom_template_url_width = "260";
    public $column_custom_template_url_order = "31";
    /**
     * column_custom_template_url
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_custom_template_url($row)
    {
        $control_type = $row[$this->col_prefix . 'control_type'];
        if(in_array($control_type, ['douyin','kuaishou'])) {
            $template_data = @json_decode($row[$this->col_prefix . 'template_data'], 1);
            return $template_data['custom_template_url'];
        }
        if(in_array($control_type, ['xhs'])) {
            $template_data = @json_decode($row[$this->col_prefix . 'template_data'], 1);
            return $template_data['customerTemplateUrl'];
        }
        if ($row['template_type'] == 'wxshipin_standard') {
            $template_select = @unserialize($row[$this->col_prefix . 'template_select']);
            $template_select = is_array($template_select) ? $template_select : array();
            $customTplId = isset($template_select['wxshipin_custom_tpl_id']) ? intval($template_select['wxshipin_custom_tpl_id']) : 0;
            if (!$customTplId) {
                return '-';
            }
            $template = app::get('logisticsmanager')->model('express_template')->db_dump(
                array('template_id' => $customTplId),
                'template_name,status'
            );
            return $template && $template['status'] == 'true' ? $template['template_name'] : '关联模板已失效';
        }

        // if (in_array($control_type, ['youzan'])) {
        //     return $row[$this->col_prefix . 'template_select'];
        // }

        if (!in_array($control_type, ['jd','cainiao','pdd'])) {
            return '-';
        }

        $template_select = (array)@unserialize($row[$this->col_prefix . 'template_select']);

        return $template_select['user_url'];
    }

    public $column_template_url = "标准模板";
    public $column_template_url_width = "260";
    public $column_template_url_order = "30";
    /**
     * column_template_url
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_template_url($row)
    {
        $control_type = $row[$this->col_prefix . 'control_type'];
        if(in_array($control_type, ['douyin','kuaishou'])) {
            $template_data = @json_decode($row[$this->col_prefix . 'template_data'], 1);
            return $template_data['template_url'];
        }
        if(in_array($control_type, ['xhs'])) {
            $template_data = @json_decode($row[$this->col_prefix . 'template_data'], 1);
            return $template_data['standardTemplateUrl'];
        }
        /*if (!in_array($control_type, ['jd','cainiao','pdd'])) {
            return '-';
        }*/

        return $row[$this->col_prefix . 'template_data'];
    }
}
?>
