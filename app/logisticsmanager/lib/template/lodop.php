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

class logisticsmanager_template_lodop{
    protected $dpi = 96;
    //面单组件
    protected  $plugins_map = array(
        'report_label'=>'sp-label',
        'report_field'=>'sp-input',
        // 'report_barcode'=>'sp-qrcode',
        'report_box'=>'sp-rectangle',
        'report_picture'=>'sp-image',
        'report_grid'=>'sp-table',
        'report_line'=>array(
            'level'=>'sp-level',
            'vertical'=>'sp-vertical'
        ),
        'report_barcode'=>array(
            'mobile_barcode'=>'sp-barcode',
            'mobile_qrcode'=>'sp-qrcode'
        )
    );

    //面单详情
    /**
     * tmplData
     * @param mixed $template_id ID
     * @param mixed $type type
     * @return mixed 返回值
     */
    public function tmplData($template_id = null, $type = 'electron'){
        // $template_id = $_GET['template_id']?$_GET['template_id']:0;
        $templateObj = app::get('logisticsmanager')->model('express_template');
        // $type =  $_GET['type'] ? $_GET['type'] : 'normal';

        if($template_id){
            $template = $templateObj->dump($template_id);
            $template['title'] = '编辑模板';
        }else{
            switch ($type) {
                case 'stock':
                    $templateWidth = 240;
                    $templateHeight = 160;
                    $templateType = 'stock';
                    $title = '新增备货面单模板';
                    break;
                case 'delivery':
                    $templateWidth = 240;
                    $templateHeight = 160;
                    $templateType = 'delivery';
                    $title = '新增发货面单模板';
                    break;
                case 'electron':
                    $templateWidth = 100;
                    $templateHeight = 150;
                    $templateType = 'electron';
                    $title = '新增电子面单模板';
                    break;
                // case 'normal':
                //     $templateWidth = 240;
                //     $templateHeight = 160;
                //     $templateType = 'normal';
                //     $title = '新增普通面单模板';
                //     break;
            }

            $template = array(
                'template_width'  => $templateWidth,
                'template_height' => $templateHeight,
                'template_type'   => $templateType,
                'title'           => $title
            );
        }

        // 打印项
        if (in_array($type,array('delivery','stock'))) {
            $elements = $templateObj->getElementsItem($type);
            //获取用户自定义的打印项
            $_arr_self_elments = app::get('logisticsmanager')->getConf('ome.delivery.print.'.$type.'.selfElments');
            $arr_self_elments = $_arr_self_elments['element_'.$template_id]; //获取每个快递单对应的自定义打印项
            if (!empty($arr_self_elments)) {
                $_value =  array_values($arr_self_elments['element']);
                $_key = array_keys($arr_self_elments['element']);
                $new_key = str_replace('+', '_', $_key[0]); //把原来键中的+号替换掉
                $new_self_elments[$new_key] = $_value[0];
                $elements = array_merge($elements, $new_self_elments); //合并系统打印项和用户自定义打印项
            }
        }else{
            $elements = $templateObj->getElements();
        }

        // 条码/二维码/表头/logo
        $barcode = $table_header = $qrcode = $logo = array();
        switch ($type) {
            case 'stock':
                $barcode = array(
                    array('label' => '发货单号', 'value' => 'delivery_bn'),
                    array('label' => '物流单号', 'value' => 'logi_no'),
                    array('label' => '订单号', 'value' => 'order_bn'),
                    array('label' => '批次号', 'value' => 'batch_number'),
                    array('label' => '自定义', 'value' => 'custom'),
                );
                $table = array(
                    'name' => 'stock_items',
                    'header' => array(
                        array('label' => '货号', 'value' => 'bn'),
                        array('label' => '批次号', 'value' => 'purchase_code'),
                        array('label' => '货位', 'value' => 'store_position'),
                        array('label' => '商品名称', 'value' => 'name'),
                        array('label' => '商品规格', 'value' => 'spec_info'),
                        array('label' => '商品数量', 'value' => 'num'),
                        array('label' => '商品类型', 'value' => 'goods_type'),
                        array('label' => '合计金额', 'value' => 'box_price'),
                        array('label' => '盒子号', 'value' => 'box'),
                        array('label' => '货品重量', 'value' => 'product_weight'),
                        array('label' => '条形码号', 'value' => 'barcode'),
                        array('label' => '序号', 'value' => 'No'),
                    ),
                    'footer' => array(
                        array('label' => '数量总计', 'value' => 'sku_num'),
                        array('label' => '备货金额', 'value' => 'total_amount'),
                        array('label' => '优惠金额', 'value' => 'total_pmt_price'),
                    ),
                );


                $qrcode = array(
                    array('label' => '自定义', 'value' => 'empty'),
                );
                break;
            case 'delivery':
                $barcode = array(
                    array('label' => '发货单号', 'value' => 'delivery_bn'),
                    array('label' => '物流单号', 'value' => 'logi_no'),
                    array('label' => '订单号', 'value' => 'order_bn'),
                    array('label' => '批次号', 'value' => 'batch_number'),
                    array('label' => '自定义', 'value' => 'custom'),
                );

                $table = array(
                    'name' => 'delivery_items',
                    'header' => array(
                        array('label' => '商品名称', 'value' => 'name'),
                        array('label' => '货号', 'value' => 'bn'),
                        array('label' => '批次号', 'value' => 'purchase_code'),
                        array('label' => '数量', 'value' => 'number'),
                        array('label' => '单价', 'value' => 'price'),
                        array('label' => '实收金额', 'value' => 'sale_price'),
                        array('label' => '规格', 'value' => 'spec_info'),
                        array('label' => '优惠价', 'value' => 'pmt_price'),
                        array('label' => '商品货号', 'value' => 'goods_bn'),
                        array('label' => '货品重量', 'value' => 'product_weight'),
                        array('label' => '单位', 'value' => 'unit'),
                        array('label' => '品牌', 'value' => 'brand_name'),
                        array('label' => '商品类型', 'value' => 'type_name'),
                        array('label' => '货位', 'value' => 'store_position'),
                        array('label' => '商品条形码', 'value' => 'barcode'),
                        array('label' => '商品图片', 'value' => 'picurl'),
                        array('label' => '序号', 'value' => 'No'),
                    ),
                    'footer' => array(
                        array('label' => '商品数量', 'value' => 'item_num'),
                        array('label' => '货品数量', 'value' => 'sku_num'),
                        array('label' => '累计品种', 'value' => 'sku_count'),
                        array('label' => '总重量', 'value' => 'total_weight'),
                        array('label' => '订单总额', 'value' => 'total_amount'),
                    ),
                );

                $table['header'] = $this->extendTableFields($type, $table['header']);

                $qrcode = array(
                    array('label' => '自定义', 'value' => 'empty'),
                );
                break;
            case 'electron':
                $barcode = array(
                    array('label' => '发货单号', 'value' => 'delivery_bn'),
                    array('label' => '物流单号', 'value' => 'logi_no'),
                    array('label' => '物流单条形码', 'value' => 'mailno_barcode'),
                    array('label' => '多包裹物流单号', 'value' => 'batch_logi_no'),
                    array('label' => '集包地编码', 'value' => 'package_wd'),
                    array('label' => '自定义', 'value' => 'custom'),
                );
                $qrcode = array(
                    array('label' => '运单二维码(韵达)', 'value' => 'mailno_qrcode'),
                    array('label' => '手机呼叫二维码', 'value' => 'phonecall_qrcode'),
                    array('label' => '顺丰-中转分拣二维码', 'value' => 'sf_twoDimensionCode_qrcode'),
                    array('label' => '物流单号(得物)', 'value' => 'dewu_qrcode'),
                    array('label' => '自定义', 'value' => 'empty'),
                );
                $table = array(
                    'name' => 'delivery_items',
                    'header' => array(
                        array('label' => '商品名称', 'value' => 'name'),
                        array('label' => '数量', 'value' => 'number'),
                        array('label' => '货号', 'value' => 'bn'),
                        array('label' => '规格', 'value' => 'spec_info'),
                    ),
                    'footer' => array(),
                );
                $logo = array(
                    array('label' => '顺丰', 'value' => 'sf.jpg'),
                    array('label' => '圆通', 'value' => 'yt.jpg'),
                    array('label' => '宅急送', 'value' => 'zjs.jpg'),
                    array('label' => '申通', 'value' => 'stkd.jpg'),
                    array('label' => '天天', 'value' => 'ttkd.jpg'),
                    array('label' => '全峰', 'value' => 'quanfeng.jpg'),
                    array('label' => '百世汇通', 'value' => 'bsht.jpg'),
                    array('label' => '中通', 'value' => 'zto.jpg'),
                    array('label' => '中国邮政', 'value' => 'chinapost.jpg'),
                    array('label' => '跨越速运', 'value' => 'kyexpress.jpg'),
                    array('label' => '韵达', 'value' => 'yunda.jpg'),
                );
                break;
        }


        $page = array(
            'width'      => $template['template_width'],
            'height'     => $template['template_height'],
            'type'       => $template['page_type']?$template['page_type']:0,//默认为0
            'file_id'    => $template['file_id']?$template['file_id']:0,
            'background' => ''
        );
        //如果存在背景图
        if ($template['file_id']) {
            $bgUrl = $this->getImgUrl($template['file_id']);
            $page['background'] = $bgUrl;
        }
        //如果是lodop则采用的新的插件面板格式
        if($template['control_type']=='lodop'){
            $plugins=unserialize($template['template_data']);
        }else{
            // 格式转换template_data
            $plugins = $this->transformTemplateData($template['template_data'],$template['template_type']);
        }

        $data = array(
            'title'           => $template['title'],
            'page'            => $page,
            'template_id'     => $template_id,
            'template_name'   => $template['template_name'],
            'template_type'   => $template['template_type'],
            'is_log'          => $template['is_logo']?$template['is_logo']:false,
            'is_default'      => $template['is_default']?$template['is_default']:false,
            'source'          => $template['source']?$template['source']:'local',
            'status'          => $template['status']?true:false,
            'cp_code'         => $template['cp_code']?$template['cp_code']:'',
            'template_select' => $template['template_select']?$template['template_select']:'',
            'base_dir'        => kernel::base_url(),
            'dpi'             => $this->dpi,
            'plugins'         => $plugins,
            'elements'        => $elements,
            'options'         => array(
                'barcode' => $barcode,
                'table'   => $table,
                'qrcode'  => $qrcode,
                'logo'    => $logo,
            ),
        );

        return $data;
    }

    /**
     * 通过 service 扩展 LODOP 明细表字段，未注册 service 时保持标品字段不变。
     */
    protected function extendTableFields($templateType, array $fields)
    {
        $fieldValues = array_column($fields, 'value');
        foreach (kernel::servicelist('logisticsmanager.service.lodop.table.fields') as $service) {
            if (!method_exists($service, 'getTableFields')) {
                continue;
            }

            $extendFields = $service->getTableFields($templateType);
            if (!is_array($extendFields)) {
                continue;
            }

            foreach ($extendFields as $field) {
                if (empty($field['label']) || empty($field['value']) || in_array($field['value'], $fieldValues, true)) {
                    continue;
                }
                $fields[] = $field;
                $fieldValues[] = $field['value'];
            }
        }

        return $fields;
    }

    //格式转化template_data
    /**
     * transformTemplateData
     * @param mixed $templateData 数据
     * @param mixed $templateType templateType
     * @return mixed 返回值
     */
    public function transformTemplateData($templateData,$templateType = 'normal'){
        $reportArr = explode(";\n",$templateData);//分割成数组
        $reportArr = array_splice($reportArr,1);//去除页面信息如 paper:210,297,NONE,100,172;
        array_pop($reportArr);//去除版权信息如 POWERED BY SHOPEXERP
        $plugins = array();//最终返回的控件数组值
        foreach ($reportArr as $report){
            $arr = explode(':',$report);//分割每个控件 取得控件名
            //标签
            if($arr[0]=='report_label'){
                $params = explode(',',$arr[1]);
                $plugin = array();
                $plugin['plugin'] = $this->plugins_map['report_label'];
                $plugin['name'] = '标签';
                $plugin['edit'] = $params[4];
                $plugin['x'] = $this->transLen($params[0]);
                $plugin['y'] = $this->transLen($params[1]);
                $plugin['z'] = 9999;
                $plugin['width'] = $this->transLen($params[2]-$params[0]);
                $plugin['fontFamily'] = $params[7];
                $plugin['fontSize'] = ceil($params[8]/10);
                $plugin['Arrangement'] = array ('ll' => 1, 'lm' => '', 'lr' => '', 'vt' => 1, 'vm' => '', 'vb' => '', );
                if($params[9]){
                    $plugin['bold'] = 600;
                }
                $plugins[] = $plugin;
            }
            //打印项
            if($arr[0]=='report_field'){
                $params = explode(',',$arr[1]);
                $plugin = array();
                $plugin['plugin'] = $this->plugins_map['report_field'];
                $plugin['name'] = '单据打印项';
                $plugin['edit'] = $params[4];
                $plugin['x'] = $this->transLen($params[0]);
                $plugin['y'] = $this->transLen($params[1]);
                $plugin['width'] = $this->transLen($params[2]-$params[0]);
                $plugin['z'] = 9999;
                $plugin['color'] = '#000';
                $plugin['fontFamily'] = $params[7];
                $plugin['fontSize'] = ceil($params[8]/10);
                $plugin['Arrangement'] = array ('ll' => 1, 'lm' => '', 'lr' => '', 'vt' => 1, 'vm' => '', 'vb' => '', );
                if($params[9]){
                    $plugin['bold'] = 600;
                }
                $plugin['type'] = array(
                    'label'=>$params[4],
                    'value'=>$params[5]
                );
                $plugins[] = $plugin;
            }
            //图片
            if($arr[0]=='report_picture'){
                $params = explode(',',$arr[1]);
                $plugin = array();
                $plugin['plugin'] = $this->plugins_map['report_picture'];
                $plugin['name'] = '图片';
                $plugin['x'] = $this->transLen($params[0]);
                $plugin['y'] = $this->transLen($params[1]);
                $plugin['z'] = 9999;
                $plugin['src'] = $this->transImgUrl($params[11]);
                $plugin['width'] = $this->transLen($params[2]-$params[0]);
                $plugin['height'] = $this->transLen($params[3]-$params[1]);
                $plugin['logoType'] = substr($params[4],0,strrpos($params[4],'.'));
                $plugins[] = $plugin;
            }
            //矩形
            if($arr[0]=='report_box'){
                $params = explode(',',$arr[1]);
                $plugin = array();
                $plugin['plugin'] = $this->plugins_map['report_box'];
                $plugin['name'] = '矩形';
                $plugin['x'] = $this->transLen($params[0]);
                $plugin['y'] = $this->transLen($params[1]);
                $plugin['z'] = 9999;
                $plugin['color'] = '#000';
                $plugin['width'] = $this->transLen($params[2]-$params[0]);
                $plugin['height'] = $this->transLen($params[3]-$params[1]);
                $plugin['thickness'] = 1;
                $plugin['lineStyle'] = 'solid';
                $plugins[] = $plugin;
            }
            //条形码、二维码
            if($arr[0]=='report_barcode'){
                $params = explode(',',$arr[1]);
                $plugin = array();
                if(!is_numeric($params[4])){
                    $plugin['plugin'] = $this->plugins_map['report_barcode']['mobile_qrcode'];
                    $plugin['name'] = '二维码';
                    $plugin['code'] = $this->transImgUrl($params[4]);
                }else{
                    $plugin['plugin'] = $this->plugins_map['report_barcode']['mobile_barcode'];
                    $plugin['name'] = '条形码';
                    $plugin['code'] = $params[4];
                    $plugin['type'] = array(
                        'label'=>'条形码',
                        'value'=>$params[5]
                    );
                }
                $plugin['x'] = $this->transLen($params[0]);
                $plugin['y'] = $this->transLen($params[1]);
                $plugin['width'] = $this->transLen($params[2]-$params[0]);
                $plugin['height'] = $this->transLen($params[3]-$params[1]);
                $plugin['z'] = 9999;
                $plugins[] = $plugin;
            }
            //水平、垂直线
            if($arr[0]=='report_line'){
                $params = explode(',',$arr[1]);
                $plugin = array();
                //如果是水平线
                if(!round($params[3]-$params[1])){
                    $plugin['plugin'] = $this->plugins_map['report_line']['level'];
                    $plugin['name'] = '水平线';
                    $plugin['width'] = $this->transLen($params[2]-$params[0]);
                }
                //如果是竖直线
                if(!round($params[2]-$params[0])){
                    $plugin['plugin'] = $this->plugins_map['report_line']['vertical'];
                    $plugin['name'] = '垂直线';
                    $plugin['height'] = $this->transLen($params[3]-$params[1]);
                }
                //考虑到从左到右和从下到上
                if($params[2]-$params[0] || $params[3]-$params[1]){
                    $plugin['x'] = $this->transLen($params[0]);
                    $plugin['y'] = $this->transLen($params[1]);
                }else{
                    $plugin['x'] = $this->transLen($params[2]);
                    $plugin['y'] = $this->transLen($params[3]);
                }
                $plugin['thickness'] = 1;
                $plugin['color'] = '#000';
                $plugin['lineStyle'] = $this->lineStyle($params[8]);
                $plugins[] = $plugin;
            }
            //表格
            if($arr[0]=='report_grid'){
                $params = explode(',',$arr[1]);
                $plugin = array();
                $plugin['plugin'] = $this->plugins_map['report_grid'];
                $plugin['name'] = '表格';
                $plugin['x'] = $this->transLen($params[0]);
                $plugin['y'] = $this->transLen($params[1]);
                $plugin['z'] = 9999;
                $plugin['width'] = $this->transLen($params[2]-$params[0]);
                $plugin['height'] = $this->transLen($params[3]-$params[1]);
                $row = array();
                $row['border_width'] = $this->transLen($params[12]);
                $row['lineStyle'] = $this->lineStyle($params[13]);
                $plugin['row'] = $row;
                $col = array();
                $col['border_width'] = $this->transLen($params[16]);
                $col['lineStyle'] = $this->lineStyle($params[17]);
                $plugin['column'] = $col;
                $border = array();
                $border['border_width'] = $this->transLen($params[8]);
                $border['lineStyle'] = $this->lineStyle($params[9]);
                $plugin['border'] = $border;
                $tableType = array('label'=>'','value'=>$params[5]);
                if($templateType=='delivery'){
                    $tableType['label'] = '发货单table';
                }else if($templateType=='stock'){
                    $tableType['label'] = '备货单table';
                }
                $plugin['type'] = $tableType;
                $headers = explode('|',$params[20]);
                $tableHeader  = array();
                foreach ($headers as $key=>$header){
                    $info = explode('#',$header);
                    $tableHeader[$key]['name'] = $info[10];
                    $tableHeader[$key]['fontSize']  = ceil($info[2]/10);
                    $tableHeader[$key]['width'] = sprintf("%.2f",$info[0]*100/($params[2]-$params[0]));
                    $tableHeader[$key]['type']['label'] = $info[10]."[".$info[9]."]";
                    $tableHeader[$key]['type']['value'] = $info[9];
                }
                $plugin['tableHeader'] = $tableHeader;
                $plugin['col'] = 6;
                $plugin['colHeight'] = 20;
                $plugins[] = $plugin;
            }
        }
        return $plugins;
    }

    //保存面单
    /**
     * 保存Tmpl
     * @return mixed 返回操作结果
     */
    public function saveTmpl(){
        $request_body = file_get_contents('php://input');
        $params = json_decode($request_body,true);
        $this->save($params);
    }
    /**
     * 保存
     * @param mixed $params 参数
     * @return mixed 返回操作结果
     */
    public function save($params){
        $data = array(
            'template_type'   => $params['template_type'],
            'template_name'   => $params['template_name'],
            'status'          => $params['status'] ? $params['status'] : 'true',
            'template_width'  => $params['template_width'],
            'template_height' => $params['template_height'],
            'file_id'         => $params['file_id'] ? $params['file_id'] : 0,
            'is_logo'         => $params['is_logo'] ? $params['is_logo'] : 'true',
            'template_select' => $params['template_select'] ? serialize($params['template_select']) : null,
            'template_data'   => $params['template_data'],
            'control_type'    => 'lodop',//更新类型为lodop
            // 'is_default'      => isset($params['is_default']) ? $params['is_default'] : 'false',
            'page_type'       => isset($params['page_type']) ? $params['page_type'] : '1',
            'aloneBtn'        => isset($params['aloneBtn']) ?  $params['aloneBtn'] : 'false',
            'btnName'         => isset($params['btnName'])?$params['btnName']:'',
            'source'          => $params['source'] ? $params['source'] : 'local',
        );
        if ($data['template_name'] == ''){
            switch ($data['template_type']) {
                case 'delivery':
                    $msg = '请输入发货单名称';
                    break;
                case 'stock':
                    $msg = '请输入备货单名称';
                    break;
                default :
                    $msg = '请输入快递单名称';
                    break;
            }
            echo json_encode(array('status'=>0, 'msg'=>$msg));
            return;
        }
        if (!in_array($data['template_type'],array('normal', 'electron', 'delivery', 'stock'))) {
            echo json_encode(array('status'=>0, 'msg'=>'面单类型不符合规则'));
            return;
        }
        if((!$data['template_width'] || !$data['template_height']) && $data['file_id']>0){
            $bgUrl = $this->getImgUrl($data['file_id']);
            list($width, $height) = getimagesize($bgUrl);
            if($width && $height){
                $data['template_width'] = intval($width*25.4/96);
                $data['template_height'] = intval($height*25.4/96);
            }
        }
        $templateObj = app::get('logisticsmanager')->model('express_template');
        if ($params['template_id']){
            $filter = array('template_id' => $params['template_id']);
            $re = $templateObj->update($data,$filter );
            $data['template_id'] = $params['template_id'];
        }else {
            $re = $templateObj->insert($data);
        }
        if($re){
            echo json_encode(array('status'=>1,'msg'=>'success','data'=>$re));
            return;
        }
        echo json_encode(array('status'=>0,'msg'=>'error','data'=>null));
    }

    //处理上传的图片
    function uploadBg(){
        $ss = kernel::single('base_storager');
        $extname = strtolower($this->extName($_FILES['file']['name']));
        $res = array(
            'msg'=>'success',
            'status'=>1
        );
        if($extname=='.jpg' || $extname=='.jpeg' || $extname=='.png'){
            $id = $ss->save_upload($_FILES['file'],"file","",$msg);//返回file_id;
        }else{
            $res['msg'] = '请上传jpg格式的图片';
            $res['status'] = 0;
            echo json_encode($res);
            return;
        }
        $data = $this->doneUploadBg(basename($id));
        $res['data'] = $data;
        echo json_encode($res);
    }
    //图片扩展名
    function extName($file){
        return substr($file,strrpos($file,'.'));
    }
    //长度换算
    /**
     * transLen
     * @param mixed $len len
     * @return mixed 返回值
     */
    public function transLen($len){
        $len = abs(round(((float)$len*$this->dpi),3));
        return $len > 0 ? $len :10;
    }
    //图片链接转换
    /**
     * transImgUrl
     * @param mixed $url url
     * @return mixed 返回值
     */
    public function transImgUrl($url){
        if(!$url) return '';
        $re = substr($url,strrpos($url,'//'));
        return "http:".$re;
    }
    //线条样式
    /**
     * lineStyle
     * @param mixed $style style
     * @return mixed 返回值
     */
    public function lineStyle($style){
        if($style==0){
            return 'solid';//实线
        }else if($style==1){
            return 'dashed';//虚线
        }else{
            return 'dotted';//点线
        }
    }
    //上传图片url
    function getImgUrl($file){
        $ss = kernel::single('base_storager');
        $url = $ss->getUrl($file,"file");

        return $url;
    }

    //更新背景图显示
    function doneUploadBg($file){
        if($file){
            $bgUrl = $this->getImgUrl($file);
            list($width, $height) = getimagesize($bgUrl);
            $pager_width = intval($width*25.4/96);
            $pager_height = intval($height*25.4/96);
            return array(
                'file_id'=>$file,
                'template_width'=>$pager_width,
                'template_height'=>$pager_height,
                'url'=>$bgUrl
            );
        }else{
            return "";
        }
    }

    /**
     * order_by_y
     * @param mixed $a a
     * @param mixed $b b
     * @return mixed 返回值
     */
    public function order_by_y($a, $b) {
        if($a['y'] == $b['y']) {
            return 0;
        }
        return $a['y'] < $b['y'] ? -1 : 1;
    }

    /**
     * 获取ToCNTpl
     * @return mixed 返回结果
     */
    public function getToCNTpl() {
        $tplId = $_GET['tpl_id'];
        $tpl = app::get('logisticsmanager')->model('express_template')->db_dump(array('template_id'=>$tplId));
        $tplData = unserialize($tpl['template_data']);
        header('content-Type:text/plain');
        $xml = '';
        $headerY = 0;
        $headerHeight = 0;
        uasort($tplData, [$this, 'order_by_y']);
        foreach ($tplData as $key => $value) {
            $pixelToMM = 1 / 96 * 25.4;
            $value['x'] = $value['x'] * $pixelToMM;
            $value['y'] = $value['y'] * $pixelToMM;
            $value['height'] = $value['height'] * $pixelToMM;
            $value['width'] = $value['width'] * $pixelToMM;
            $xmlName = 'xml';
            if($xmlTableBottom && $value['y'] > $xmlTableBottom) {
                $xmlName = 'xmlTable';
                $value['y'] -= $xmlTableBottom;
            }
            if($xmlName == 'xml' && $value['plugin'] != 'sp-table') {
                $headerY = $value['y'];
                $headerHeight = $value['height'];
            }

            switch ($value['plugin']) {
                case 'sp-input'://打印项
                    $align = $value['Arrangement']['ll'] ? 'left' : ($value['Arrangement']['lm'] ? 'center' : ($value['Arrangement']['lr'] ? 'right' : 'left'));
                    $valign = $value['Arrangement']['vt'] ? 'top' : ($value['Arrangement']['vm'] ? 'middle' : ($value['Arrangement']['vb'] ? 'bottom' : 'top'));
                    ${$xmlName} .= '<layout width="'.$value['width'].'" height="'.$value['height'].'" left="'.$value['x'].'" top="'.$value['y'].'"  style="zIndex:'.$value['z'].';">
                                <text style="fontFamily:'.($value['fontFamily']?:'simhei').';fontSize:'.($value['fontSize']?:'auto').';alpha:'.($value['opacity']?:1).';fontColor:'.($value['color']?:'#000000').';fontWeight:'.($value['bold']?:'light').';align:'.$align.';valign:'.$valign.';">
                                    <![CDATA[<%=_data.'.$value['type']['value'].'%>]]>
                                </text>
                            </layout>';
                    break;
                case 'sp-label'://标签
                    $align = $value['Arrangement']['ll'] ? 'left' : ($value['Arrangement']['lm'] ? 'center' : ($value['Arrangement']['lr'] ? 'right' : 'left'));
                    $valign = $value['Arrangement']['vt'] ? 'top' : ($value['Arrangement']['vm'] ? 'middle' : ($value['Arrangement']['vb'] ? 'bottom' : 'top'));
                    ${$xmlName} .= '<layout width="'.$value['width'].'" height="'.$value['height'].'" left="'.$value['x'].'" top="'.$value['y'].'"  style="zIndex:'.$value['z'].';">
                                <text style="fontFamily:'.($value['fontFamily']?:'simhei').';fontSize:'.($value['fontSize']?:'auto').';alpha:'.($value['opacity']?:1).';fontColor:'.($value['color']?:'#000000').';fontWeight:'.($value['bold']?:'light').';align:'.$align.';valign:'.$valign.';">
                                    <![CDATA['.$value['edit'].']]>
                                </text>
                            </layout>';
                    break;
                case 'sp-level'://水平线
                    ${$xmlName} .= '<line startX="'.$value['x'].'" startY="'.$value['y'].'" endX="'.($value['x'] + $value['width']).'" endY="'.$value['y'].'"  style="lineType:'.$value['lineStyle'].';lineWidth:'.$value['thickness'].';lineColor:'.$value['color'].';" />';
                    break;
                case 'sp-vertical'://垂直线
                    ${$xmlName} .= '<line startX="'.$value['x'].'" startY="'.$value['y'].'" endX="'.$value['x'].'" endY="'.($value['y'] + $value['height']).'"  style="lineType:'.$value['lineStyle'].';lineWidth:'.$value['thickness'].';lineColor:'.$value['color'].';" />';
                    break;
                case 'sp-rectangle'://矩形
                    ${$xmlName} .= '<layout left="'.$value['x'].'" top="'.$value['y'].'" width="'.$value['width'].'" height="'.$value['height'].'" style="zIndex:'.$value['z'].';">
                                <rect width="'.$value['width'].'" height="'.$value['height'].'" style="borderStyle:'.$value['lineStyle'].';borderWidth:'.$value['thickness'].';"/>  
                            </layout>';
                    break;
                case 'sp-image'://图片
                    $src = strpos($value['src'], 'http') === false ? kernel::base_url(1) . $value['src'] : $value['src'];
                    ${$xmlName} .= '<layout left="'.$value['x'].'" top="'.$value['y'].'" width="'.$value['width'].'" height="'.$value['height'].'" style="zIndex:'.$value['z'].';">
                                <image width="'.$value['width'].'" height="'.$value['height'].'" src="'.$src.'" allowFailure="false"/>
                            </layout>';
                    break;
                case 'sp-barcode'://条形码
                    $barcode = $value['type']['value'] && $value['type']['value'] != "custom" ? '<%=_data.'.$value['type']['value'].'%>' : $value['code'];
                    ${$xmlName} .= '<layout left="'.$value['x'].'" top="'.$value['y'].'" width="'.$value['width'].'" height="'.$value['height'].'" style="zIndex:'.$value['z'].';">
                                <barcode width="'.$value['width'].'" height="'.$value['height'].'" type="code128" style="hideText:'.($value['text']=='false'?'true':'false').'">
                                    <![CDATA['.$barcode.']]>
                                </barcode>
                            </layout>';
                    break;
                case 'sp-qrcode'://二维码
                    $barcode = $value['type']['value'] ? '<%=_data.'.$value['type']['value'].'%>' : ($value['code'] ? : '');
                    ${$xmlName} .= '<layout left="'.$value['x'].'" top="'.$value['y'].'" width="'.$value['width'].'" height="'.$value['height'].'" style="zIndex:'.$value['z'].';">
                                <barcode width="'.$value['width'].'" height="'.$value['height'].'" type="qrcode" style="hideText:true">
                                    <![CDATA['.$barcode.']]>
                                </barcode>
                            </layout>';
                    break;
                case 'sp-table'://表格 '.($value['column']['border_width']?:1).' .$value['border']['border_width'].'
                    $xmlTableY = $value['y'];
                    $xmlTable = '<layout left="'.$value['x'].'" top="{xmlTableY}" orientation="vertical" style="zIndex:'.$value['z'].';"><layout>';
                    $xmlTableBottom = $xmlTableY + $value['height'];
                    $xmlTable .= '<table width="'.$value['width'].'" style="borderStyle:'.($value['border']['lineStyle']?:'solid').';borderWidth:1;cellBorderWidth:1;cellBorderStyle:'.($value['column']['lineStyle']?:'solid').';">';
                    $xmlTable .= '<tr>';
                    foreach ($value['tableHeader'] as $k => $v) {
                        $name = ($v['name'] && strpos($v['name'], 'row') === false ? $v['name'] : $v['type']['label']);
                        $xmlTable .= '<th width="'.$v['width'].'%"><text style="fontSize:'.($v['fontSize']?:'10').';align:center;"><![CDATA['.$name.']]></text></th>';
                    }
                    $xmlTable .= '</tr>';
                    $xmlTable .= '<% for(i=0;i<_data.'.$value['type']['value'].'.length;i++) {%> <tr>';
                    foreach ($value['tableHeader'] as $k => $v) {
                        $xmlTable .= '<td style="padding:0.5"><text style="fontSize:'.($v['fontSize']?:'10').';"><![CDATA[<%=_data.'.$value['type']['value'].'[i].'.$v['type']['value'].'%>]]></text></td>';
                    }
                    $xmlTable .= '</tr>';
                        $xmlTable .= '<%if(_data.'.$value['type']['value'].'[i].items) {%>';
                            $xmlTable .= '<% for(j=0;j<_data.'.$value['type']['value'].'[i].items.length;j++) {%> <tr>';
                            foreach ($value['tableHeader'] as $k => $v) {
                                $xmlTable .= '<td style="padding:1"><text style="fontSize:'.($v['fontSize']?:'10').';"><![CDATA[<%=_data.'.$value['type']['value'].'[i].items[j].'.$v['type']['value'].'%>]]></text></td>';
                            }
                            $xmlTable .= '</tr>';
                            $xmlTable .= '<%}%>';
                        $xmlTable .= '<%}%>';
                    $xmlTable .= '<%}%>';
                    if($value['footer']) {
                        $xmlTable .= '<tr><td colspan="'.count($value['tableHeader']).'" style="padding:1"><text style="align:right;"><![CDATA[';
                        foreach ($value['footer'] as $k => $v) {
                            $xmlTable .= $v['label'] . '：<%=_data.countDeliveryMsg.'.$v['value'].'%>      ';
                        }
                        $xmlTable .= ']]></text></td></tr>';
                    }
                    $xmlTable .= '</table></layout><layout>';
                    break;
                default:
                    # code...
                    break;
            }
        }
        if($xmlTable) {
            $xml = '<?xml version="1.0" encoding="UTF-8"?><page xmlns="http://shopex.com" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" width="'.$tpl['template_width'].'" height="'.$tpl['template_height'].'"  splitable="true" ><header width="'.$tpl['template_width'].'" style="overflow:visible;zIndex:1;">'.$xml.'</header><layout width="'.$tpl['template_width'].'"  style="overflow:visible;zIndex:1;">' . str_replace("{xmlTableY}", ($xmlTableY - ($headerY + $headerHeight)),$xmlTable) . '</layout></layout>';
        } else {
            $xml = '<?xml version="1.0" encoding="UTF-8"?><page xmlns="http://shopex.com" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" width="'.$tpl['template_width'].'" height="'.$tpl['template_height'].'"  splitable="true" ><header height="0" ></header><layout width="'.$tpl['template_width'].'"  style="overflow:visible;zIndex:1;">' . $xml;
        }
        $xml .= '</layout>';
        $xml .= '<!--页脚数据-->
    <footer width="205" height="10" style="zIndex:1;overflow:visible;">
        <!--页面索引 当前页数：currentPageNumber 总页数：totalPageNumber-->
        <layout left="10" top="0" width="60" height="10" style="zIndex:1;overflow:visible;">
            <pageIndex format="第currentPageNumber页 共totalPageNumber页" style="fontSize:12;fontFamily:Arial">
            </pageIndex>
        </layout>
    </footer>';
        $xml .= '</page>';
        echo $xml;
    }

    public function getToWxshipinTpl()
    {
        $tplId = intval($_GET['tpl_id']);
        $tpl = app::get('logisticsmanager')->model('express_template')->db_dump(
            array('template_id' => $tplId),
            'template_id,template_type,control_type,template_width,template_height,template_data,status'
        );
        header('Content-Type: text/html; charset=utf-8');
        if (!$tpl || $tpl['template_type'] != 'electron' || $tpl['control_type'] != 'lodop' || $tpl['status'] != 'true') {
            echo '<div>自定义区模板不存在或已失效</div>';
            return;
        }

        $plugins = @unserialize($tpl['template_data']);
        $unsupported = app::get('logisticsmanager')->model('express_template')->getWxshipinUnsupportedPlugins($tpl['template_data']);
        if (!is_array($plugins) || $unsupported) {
            echo '<div>自定义区模板包含暂不支持的控件</div>';
            return;
        }

        $canvasWidth = floatval($tpl['template_width']) / 25.4 * $this->dpi;
        $canvasHeight = floatval($tpl['template_height']) / 25.4 * $this->dpi;
        if ($canvasWidth <= 0 || $canvasHeight <= 0) {
            echo '<div>自定义区模板尺寸无效</div>';
            return;
        }

        // width/height are the final custom-area pixels; custom_width/custom_height
        // remain accepted for old generated URLs during the transition.
        $templateWidth = isset($_GET['width']) ? floatval($_GET['width']) : (isset($_GET['custom_width']) ? floatval($_GET['custom_width']) : 0);
        $templateHeight = isset($_GET['height']) ? floatval($_GET['height']) : (isset($_GET['custom_height']) ? floatval($_GET['custom_height']) : 0);
        $templateWidth = $templateWidth > 0 ? round($templateWidth, 2) : round(floatval($tpl['template_width']) * 10, 2);
        $templateHeight = $templateHeight > 0 ? round($templateHeight, 2) : round(floatval($tpl['template_height']) * 10, 2);

        uasort($plugins, array($this, 'order_by_y'));
        $html = array();
        foreach ($plugins as $plugin) {
            if (!is_array($plugin) || empty($plugin['plugin'])) {
                continue;
            }
            $html[] = $this->wxshipinPluginHtml($plugin, $canvasWidth, $canvasHeight, $templateWidth);
        }

        echo '<style>'
            . '.wxshipin-custom-area{position:relative;overflow:hidden;color:#000;background:#fff;box-sizing:border-box;}'
            . '.wxshipin-item{position:absolute;box-sizing:border-box;overflow:hidden;}'
            . '.wxshipin-text{display:flex;word-break:break-all;white-space:pre-wrap;}'
            . '.wxshipin-item img{display:block;width:100%;height:100%;object-fit:contain;}'
            . '.wxshipin-item bar-code,.wxshipin-item qr-code{display:block;width:100%;height:100%;}'
            . '.wxshipin-table{width:100%;border-collapse:collapse;table-layout:fixed;}'
            . '.wxshipin-table th,.wxshipin-table td{border:1px solid #000;padding:1px;word-break:break-all;}'
            . '</style><div class="wxshipin-custom-area" style="width:' . $templateWidth . 'px;height:' . $templateHeight . 'px;">'
            . implode('', $html)
            . '</div>';
    }

    private function wxshipinPluginHtml($plugin, $canvasWidth, $canvasHeight, $templateWidth)
    {
        $style = $this->wxshipinPositionStyle($plugin, $canvasWidth, $canvasHeight);
        $fontStyle = $this->wxshipinFontStyle($plugin, $canvasWidth, $templateWidth);
        $field = isset($plugin['type']['value']) ? $plugin['type']['value'] : '';
        switch ($plugin['plugin']) {
            case 'sp-input':
                $mustacheField = $field == 'shop_name' ? 'shopName' : $field;
                $content = ($mustacheField && $mustacheField != 'empty') ? '{{' . $mustacheField . '}}' : '';
                return '<div class="wxshipin-item wxshipin-text" style="' . $style . $fontStyle . $this->wxshipinFlexStyle($plugin) . '">' . $content . '</div>';
            case 'sp-label':
                return '<div class="wxshipin-item wxshipin-text" style="' . $style . $fontStyle . $this->wxshipinFlexStyle($plugin) . '">' . $this->wxshipinEscape(isset($plugin['edit']) ? $plugin['edit'] : '') . '</div>';
            case 'sp-image':
                $src = isset($plugin['src']) ? $plugin['src'] : '';
                if ($src && strpos($src, 'http://') !== 0 && strpos($src, 'https://') !== 0) {
                    $src = kernel::base_url(1) . $src;
                }
                return '<div class="wxshipin-item" style="' . $style . '"><img src="' . $this->wxshipinEscape($src) . '" alt=""></div>';
            case 'sp-barcode':
                $content = ($field && $field != 'custom' && $field != 'empty') ? '{{productBarcode}}' : (isset($plugin['code']) ? $plugin['code'] : '');
                $config = '{&quot;displayValue&quot;:' . (isset($plugin['text']) && $plugin['text'] == 'false' ? 'false' : 'true') . ',&quot;format&quot;:&quot;auto&quot;}';
                return '<div class="wxshipin-item" style="' . $style . '"><bar-code content="' . $this->wxshipinEscape($content) . '" config="' . $config . '"></bar-code></div>';
            case 'sp-qrcode':
                $content = ($field && $field != 'empty' && $field != 'custom') ? '{{productQRcode}}' : (isset($plugin['code']) ? $plugin['code'] : '');
                return '<div class="wxshipin-item" style="' . $style . '"><qr-code content="' . $this->wxshipinEscape($content) . '"></qr-code></div>';
            case 'sp-table':
                return $this->wxshipinTableHtml($plugin, $style, $fontStyle);
        }
        return '';
    }

    private function wxshipinTableHtml($plugin, $style, $fontStyle)
    {
        $fieldMap = array(
            'name' => 'name',
            'number' => 'count',
            'bn' => 'code',
            'spec_info' => 'description',
        );
        if ($this->isWxshipinMaterialList($plugin)) {
            $parts = array();
            $materialFieldHtml = array(
                'name' => '{{name}}',
                'number' => '{{count}}件',
                'bn' => '【{{code}}】',
            );
            foreach ((array)$plugin['tableHeader'] as $header) {
                $parts[] = $materialFieldHtml[$header['type']['value']];
            }
            return '<div class="wxshipin-item" style="' . $style . $fontStyle . '"><div>{{#productInfo}}'
                . '<div>' . implode(' ', $parts) . '</div>'
                . '{{/productInfo}}</div></div>';
        }

        $head = $body = '';
        foreach ((array)(isset($plugin['tableHeader']) ? $plugin['tableHeader'] : array()) as $header) {
            $label = !empty($header['name']) ? $header['name'] : (isset($header['type']['label']) ? $header['type']['label'] : '');
            $width = isset($header['width']) ? floatval($header['width']) : 0;
            $head .= '<th style="width:' . $width . '%">' . $this->wxshipinEscape($label) . '</th>';
            $fieldKey = isset($header['type']['value']) ? $header['type']['value'] : '';
            $field = isset($fieldMap[$fieldKey]) ? $fieldMap[$fieldKey] : '';
            if (!$field) {
                continue;
            }
            $body .= '<td>{{' . $field . '}}</td>';
        }
        return '<div class="wxshipin-item" style="' . $style . $fontStyle . '"><table class="wxshipin-table"><thead><tr>'
            . $head . '</tr></thead><tbody>{{#productInfo}}<tr>' . $body . '</tr>{{/productInfo}}</tbody></table></div>';
    }

    private function isWxshipinMaterialList($plugin)
    {
        $headers = array_values((array)(isset($plugin['tableHeader']) ? $plugin['tableHeader'] : array()));
        if (count($headers) != 3) {
            return false;
        }

        $supported = array(
            'name' => '物料名称',
            'number' => '数量',
            'bn' => '物料编码',
        );
        $fields = array();
        foreach ($headers as $header) {
            $label = !empty($header['name']) ? $header['name'] : (isset($header['type']['label']) ? $header['type']['label'] : '');
            $field = isset($header['type']['value']) ? $header['type']['value'] : '';
            if (!isset($supported[$field]) || $label != $supported[$field] || in_array($field, $fields)) {
                return false;
            }
            $fields[] = $field;
        }
        return count($fields) == count($supported);
    }

    private function wxshipinPositionStyle($plugin, $canvasWidth, $canvasHeight)
    {
        return 'left:' . $this->wxshipinPercent(isset($plugin['x']) ? $plugin['x'] : 0, $canvasWidth)
            . '%;top:' . $this->wxshipinPercent(isset($plugin['y']) ? $plugin['y'] : 0, $canvasHeight)
            . '%;width:' . $this->wxshipinPercent(isset($plugin['width']) ? $plugin['width'] : 0, $canvasWidth)
            . '%;height:' . $this->wxshipinPercent(isset($plugin['height']) ? $plugin['height'] : 0, $canvasHeight)
            . '%;z-index:' . intval(isset($plugin['z']) ? $plugin['z'] : 0) . ';';
    }

    private function wxshipinFontStyle($plugin, $canvasWidth, $templateWidth)
    {
        $fontSize = floatval(isset($plugin['fontSize']) ? $plugin['fontSize'] : 0);
        $fontSize = $fontSize > 0 ? $fontSize : 12;
        $fontFamily = !empty($plugin['fontFamily']) ? $plugin['fontFamily'] : 'Arial,sans-serif';
        $color = !empty($plugin['color']) ? $plugin['color'] : '#000';
        $weight = intval(isset($plugin['bold']) ? $plugin['bold'] : 0) >= 600 ? 'bold' : 'normal';
        $fontSize = round($fontSize * $templateWidth / $canvasWidth, 2);
        return 'font-size:' . $fontSize . 'px;font-family:' . $this->wxshipinEscape($fontFamily)
            . ';color:' . $this->wxshipinEscape($color) . ';font-weight:' . $weight . ';';
    }

    private function wxshipinFlexStyle($plugin)
    {
        $arrangement = isset($plugin['Arrangement']) ? (array)$plugin['Arrangement'] : array();
        $justify = !empty($arrangement['lm']) ? 'center' : (!empty($arrangement['lr']) ? 'flex-end' : 'flex-start');
        $align = !empty($arrangement['vm']) ? 'center' : (!empty($arrangement['vb']) ? 'flex-end' : 'flex-start');
        return 'justify-content:' . $justify . ';align-items:' . $align . ';';
    }

    private function wxshipinPercent($value, $total)
    {
        return round(floatval($value) * 100 / $total, 4);
    }

    private function wxshipinEscape($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}
