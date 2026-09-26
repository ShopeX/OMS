<?php

class openapi_api_params_v1_basicmaterial extends openapi_api_params_abstract implements openapi_api_params_interface{

    public function checkParams($method,$params,&$sub_msg){
        if(parent::checkParams($method,$params,$sub_msg)){
            return true;
        }else{
            return false;
        }
    }

    public function getAppParams($method){
        $params = array(
            'getList'=>array(
                'start_time'=>array('type'=>'string','require'=>'false','name'=>'开始时间','desc'=>'例如2012-12-08 18:50:30'),
                'end_time'=>array('type'=>'string','require'=>'false','name'=>'结束时间','desc'=>'例如2012-12-08 18:50:30'),
                'material_bn'   => array('type' => 'string','name' => '基础物料编码'),
               	'page_no'=>array('type'=>'number','require'=>'false','name'=>'页码','desc'=>'默认1,第一页'),
       			'page_size'=>array('type'=>'number','require'=>'false','name'=>'每页数量','desc'=>'最大100'),
            ),
            'add'=>array(
                'material_name'  => array('type'=>'string','required'=>'true','name'=>'基础物料名称','desc'=>'必填'),
                'material_bn'   => array('type' => 'string','required'=>'true','name' => '基础物料编号','desc'=>'必填'),
                'material_spu'   => array('type' => 'string','name' => '基础物料款号'),
                'material_type'   => array('type' => 'number','required'=>'true','name' => '基础物料属性','desc' => '必填，1代表成品、2代表半成品、4代表礼盒'),
                'gtype_name'   => array('type' => 'string','required'=>'false','name' => '物料类型','desc' => '非必填'),
                'brand_code'   => array('type' => 'string','required'=>'false','name' => '物料品牌code','desc' => '非必填'),
                'brand_name'   => array('type' => 'string','required'=>'false','name' => '物料品牌名称','desc' => '非必填 如物料品牌code存在，此值必填'),
                'serial_number'   => array('type' => 'string','name' => '是否启用唯一码','desc' => 'true 开启、false 关闭'),
                'visibled' => array('type' => 'number','required'=>'true','name' => '是否可售','desc'=>'必填，1代表可售、2代表停售'),
                'material_code' => array('type' => 'string','name' => '条码'),
                'unit'   => array('type' => 'string','name' => '计量单位'),
                'retail_price'   => array('type' => 'string','name' => '销售价'),
                'cost'   => array('type' => 'string','name' => '成本价'),
                'weight'   => array('type' => 'string','name' => '重量'),
//                'bind_info'   => array('type' => 'string','name'=>'关联半成品信息','desc' => '例子：test001:1|test002:2'),//已弃用，请使用sub_items
                'sub_items'   => array('type' => 'string','name'=>'关联半成品信息','desc' => '格式：material_bn:test1,material_num:1;'),
                'use_expire'   => array('type' => 'number','name' => '是否启用保质期监控','desc'=>'必填，1代表开启、2代表关闭'),
                'warn_day'   => array('type' => 'number','name' => '预警天数配置'),
                'quit_day'   => array('type' => 'number','name' => '自动退出库存天数配置'),
                'shelf_life' => array('type' => 'number','name' => '保质期(小时)','desc'=>'格式：72'),
                'box_spec'   => array('type' => 'string','name' => '箱规','desc'=>'格式：24'),
                'is_wms'     => array('type' => 'string','name' => '是否推送WMS','desc' => 'true 是、false 否'),
            ),
            'edit'=>array(
                'material_name'  => array('type'=>'string','required'=>'true','name'=>'基础物料名称','desc'=>'必填'),
                'material_bn'   => array('type' => 'string','required'=>'true','name' => '基础物料编号','desc'=>'必填'),
                'material_spu'   => array('type' => 'string','name' => '基础物料款号'),
                'material_type'   => array('type' => 'number','required'=>'true','name' => '基础物料属性','desc'=>'必填，1代表成品、2代表半成品、4代表礼盒'),
                'serial_number'   => array('type' => 'string','name' => '是否启用唯一码','desc' => 'true 开启、false 关闭'),
                'visibled' => array('type' => 'number','required'=>'true','name' => '是否可售','desc'=>'必填，1代表可售、2代表停售'),
                'material_code' => array('type' => 'string','name' => '条码'),
                'unit'   => array('type' => 'string','name' => '计量单位'),
                'retail_price'   => array('type' => 'string','name' => '销售价'),
                'cost'   => array('type' => 'string','name' => '成本价'),
                'weight'   => array('type' => 'string','name' => '重量'),
//                'bind_info'   => array('type' => 'string','name'=>'关联半成品信息','desc' => '例子：test001:1|test002:2'),
                'sub_items'   => array('type' => 'string','name'=>'关联半成品信息','desc' => '格式：material_bn:test1,material_num:1;'),
                'use_expire'   => array('type' => 'number','name' => '是否启用保质期监控','desc'=>'必填，1代表开启、2代表关闭'),
                'warn_day'   => array('type' => 'number','name' => '预警天数配置'),
                'quit_day'   => array('type' => 'number','name' => '自动退出库存天数配置'),
                'shelf_life' => array('type' => 'number','name' => '保质期(小时)','desc'=>'格式：72'),
                'box_spec'   => array('type' => 'string','name' => '箱规','desc'=>'格式：24'),
            ),
        );

        return $params[$method];
    }
    
    public function description($method){

        $desccription = array(
            'getList'=>array(
                'name'=>'基础物料查询接口',
                'description'=>'实时批量获取特定条件下的基础物料；返回含 props 自定义字段键值'
            ),
            'add'=>array(
                'name'        =>'基础物料添加接口',
                'description' =>'添加基础物料',
            ),
            'edit'=>array(
                'name'        =>'基础物料修改接口',
                'description' =>'修改基础物料',
            ),
        );

        return $desccription[$method];

    }
}
