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

class openapi_api_params_v1_po extends openapi_api_params_abstract implements openapi_api_params_interface{

    public function checkParams($method,$params,&$sub_msg){
        if(parent::checkParams($method,$params,$sub_msg)){
            return true;
        }else{
            return false;
        }
    }

    public function getAppParams($method){
        $params = array(
            'add'=>array(
                'name'=>array('type'=>'string','required'=>'true','name'=>'采购单名称','desc'=>'必填'),
                'vendor'=>array('type'=>'string','required'=>'false','name'=>'供应商','desc'=>''),
                'po_type'=>array('type'=>'string','required'=>'true','name'=>'采购方式','desc'=>'必填  1 现购    2 赊购'),
                'branch_bn'=>array('type'=>'string','required'=>'true','name'=>'到货仓库编号','desc'=>'必填'),
                'delivery_cost'=>array('type'=>'number','required'=>'false','name'=>'物流费用','desc'=>'必填'),
                'deposit_balance'=>array('type'=>'number','required'=>'false','name'=>'预付款金额'),
                'arrive_time'=>array('type'=>'number','required'=>'false','name'=>'预计到货天数','desc'=>'必填'),
                'operator'=>array('type'=>'string','required'=>'false','name'=>'采购员'),
                'po_bn'=>array('type'=>'string','required'=>'false','name'=>'采购单号'),
                'memo'=>array('type'=>'string','required'=>'false','name'=>'备注'),
                'props'=>array('type'=>'string','required'=>'false','name'=>'扩展属性','desc'=>'JSON格式字符串，例如：{"owner_code":"xxx"}'),
                'items'=>array('type'=>'string','required'=>'true','name'=>'明细','desc'=>'必填   格式为：bn:test1,name:测试1,price:10,nums:1;bn:test2,name:测试2,price:20,nums:2'),
            ),
            'getList'=>array(
                'po_bn'=>array('type'=>'string','require'=>'false','name'=>'采购单编号'),
                'supplier'=>array('type'=>'string','require'=>'false','name'=>'供应商名称'),
                'start_time'=>array('type'=>'string','require'=>'false','name'=>'开始时间','desc'=>'例如2012-12-08 18:50:30'),
                'end_time'=>array('type'=>'string','require'=>'false','name'=>'结束时间','desc'=>'例如2012-12-08 18:50:30'),
                'last_modify_start_time'=>array('type'=>'string','require'=>'false','name'=>'最后更新开始时间','desc'=>'例如2012-12-08 18:50:30'),
                'last_modify_end_time'=>array('type'=>'string','require'=>'false','name'=>'最后更新结束时间','desc'=>'例如2012-12-08 18:50:30'),
                'eo_status'=>array('type'=>'string','require'=>'false','name'=>'入库状态'),
                'check_status'=>array('type'=>'string','require'=>'false','name'=>'审核状态'),
                'po_status'=>array('type'=>'string','require'=>'false','name'=>'采购状态'),
                'statement_status'=>array('type'=>'string','require'=>'false','name'=>'结算状态'),
                'page_no'=>array('type'=>'number','require'=>'false','name'=>'页码','desc'=>'默认1,第一页'),
                'page_size'=>array('type'=>'number','require'=>'false','name'=>'每页最大数量','desc'=>'最大100'),
            ),
            'cancel'=>array(
                'po_bn'=>array('type'=>'string','required'=>'true','name'=>'采购单编号','desc'=>'必填'),
                'operator'=>array('type'=>'string','required'=>'false','name'=>'操作员','desc'=>'选填，默认为system'),
                'memo'=>array('type'=>'string','required'=>'false','name'=>'取消备注','desc'=>'选填'),
            )
        );

        return $params[$method];
    }
    
    public function description($method){
        $desccription = array('add'=>array('name'=>'新建采购单','description'=>'创建一个采购指令'),
                              'getList'=>array('name'=>'返回采购单信息','description'=>'创建一个采购指令'),
                              'cancel'=>array('name'=>'取消采购单','description'=>'取消一个未入库或部分入库的采购单'));
        return $desccription[$method];
    }
}
