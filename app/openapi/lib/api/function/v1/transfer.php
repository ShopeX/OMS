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

class openapi_api_function_v1_transfer extends openapi_api_function_abstract implements openapi_api_function_interface{

    public function add($params,&$code,&$sub_msg){
        $data = array();
        
        $data['name'] = $this->charFilter($params['name']);
        $data['vendor'] = $params['vendor'];
        $data['type'] = $params['t_type'];
        $data['branch_bn'] = $params['branch_bn'];
        $data['delivery_cost'] = $params['delivery_cost'];
        $data['operator'] = $params['operator'];
        $data['memo'] = $params['memo'];
        $data['confirm'] = $params['confirm'];
        $data['items'] = json_decode($params['items'],true);
        //外部仓库参数
        $data["extrabranch_bn"] = trim($params["extrabranch_bn"]); //编码
        $data["extrabranch_name"] = trim($params["extrabranch_name"]); //名称
        $data["extrabranch_uname"] = trim($params["extrabranch_uname"]); //联系人
        $data["extrabranch_email"] = trim($params["extrabranch_email"]); //邮件
        $data["extrabranch_phone"] = trim($params["extrabranch_phone"]); //电话
        $data["extrabranch_mobile"] = trim($params["extrabranch_mobile"]); //手机
        $data["extrabranch_memo"] = trim($params["extrabranch_memo"]); //备注
        
        $data['io_bn'] = trim($params["io_bn"]);
        $data['bill_type'] = trim($params['bill_type']);//业务类型
        $rs = kernel::single('openapi_data_original_transfer')->add($data);
        return $rs;
    }
    
    public function getList($params,&$code,&$sub_msg){
    
        $start_time = strtotime($params['start_time']);
        $end_time = strtotime($params['end_time']);
        $page_no = intval($params['page_no']) > 0 ? intval($params['page_no']) : 1;
        $limit = (intval($params['page_size']) > 100 || intval($params['page_size']) <= 0) ? 100 : intval($params['page_size']);
        $original_bn = trim($params['original_bn']);
        $supplier_bn = trim($params['supplier_bn']);
        $branch_bn = trim($params['branch_bn']);
        $t_type = trim($params['t_type']);
    
        //只获取有来源的出入库单(外部仓库)
        $is_source = intval($params['is_source']);
        
        if($page_no == 1){
            $offset = 0;
        }else{
            $offset = ($page_no-1)*$limit;
        }
    
        $iostock_data = kernel::single('openapi_data_original_transfer')->getList($start_time,$end_time,$original_bn,$supplier_bn,$branch_bn,$t_type,$is_source,$offset,$limit);
    
        $iostock_arr = array();
        foreach ($iostock_data['lists'] as $k => $iostock){
            $iostock_arr[$k]['iostock_id'] = $iostock['iostock_id'];
    
            $iostock_arr[$k]['iostock_bn'] = $this->charFilter($iostock['iostock_bn']);
            $iostock_arr[$k]['branch_bn'] = $this->charFilter($iostock['branch_bn']);
            $iostock_arr[$k]['branch_name'] = $this->charFilter($iostock['branch_name']);
            $iostock_arr[$k]['bn'] = $this->charFilter($iostock['bn']);
            $iostock_arr[$k]['name'] = $this->charFilter($iostock['name']);
            $iostock_arr[$k]['barcode'] = $iostock['barcode'];            $iostock_arr[$k]['nums'] = $iostock['nums'];
            $iostock_arr[$k]['type'] = $this->charFilter($iostock['type_name']);
            $iostock_arr[$k]['iostock_time'] = date('Y-m-d H:i:s',$iostock['create_time']);
            $iostock_arr[$k]['memo'] = $this->charFilter($iostock['memo']);
            $iostock_arr[$k]['original_bn'] = $this->charFilter($iostock['original_bn']);
            $iostock_arr[$k]['iostock_price'] = $iostock['iostock_price'];
            $iostock_arr[$k]['unit_cost'] = $iostock['unit_cost'];
            $iostock_price_num += $iostock['iostock_price']*$iostock['nums'];
            $unit_cost_num += $iostock['unit_cost']*$iostock['nums'];
    
            //出入库类型标识(in为入库、out为出库)
            $iostock_arr[$k]['iso_type'] = $iostock['iso_type'];
    
            //外部仓库名称
            if($is_source){
                $iostock_arr[$k]['extrabranch_name'] = $iostock['extrabranch_name'];
            }
        }
    
        unset($iostock_data['lists']);
        $iostock_data['iostock_price_num'] = sprintf("%.3f", $iostock_price_num);
        $iostock_data['unit_cost_num'] = sprintf("%.3f", $unit_cost_num);
        $iostock_data['lists'] = $iostock_arr;
        return $iostock_data;
    
    }

    public function getIsoList($params, &$code, &$sub_msg)
    {
        $filter['start_time'] = $params['start_time'];
        $filter['end_time']   = $params['end_time'];
        $filter['type']       = $params['t_type'];
        $filter['status']     = $params['status'];
        $filter['iso_bn']     = $params['iso_bn'];
        $filter['branch_bn']  = $params['branch_bn'];
        $filter['bill_type']  = $params['bill_type'];
        $filter['bill_type_not']  = $params['bill_type_not'];

        $page_no = intval($params['page_no']);
        $limit   = intval($params['page_size']);

        $page_no = $page_no > 0 ? $page_no : 1;
        $limit   = ($limit > 100 || $limit <= 0) ? 100 : $limit;
        $offset  = ($page_no - 1) * $limit;

        $data = kernel::single('openapi_data_original_transfer')->getIsoList($filter, $offset, $limit);

        return $data;
    }
    
}
