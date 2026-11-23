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
 * 人工库存预占公用类
 * by wangjianjun 20171014
 */
class console_stock_artificial_freeze{

    private $branch_list; //仓库列表数据 数组key为branch_id
    private $bm_list; //基础物料数据 数组key为bm_id

    //执行导入
    function process(){
        //处理文件
        $file_info = $this->upload_file();
        if($file_info['rsp'] == 'fail'){
            return $file_info;
        }
        //验证数据
        $rs_check = $this->valid_data($file_info["data"]);
        if($rs_check['rsp'] == 'fail'){
            return $rs_check;
        }
        //人工库存预占处理
        $rs_freeze = $this->do_freeze($file_info["data"],$error_msg);
        if($rs_freeze['rsp'] == 'fail'){
            return $rs_freeze;
        }
        //成功
        return $rs_freeze;
    }

    //人工库存预占处理（走队列）
    private function do_freeze($content_arr,&$err_msg){
        //分片数组走队列
        $number = $page = 0;
        $limit = 100;
        $sdfs = array();
        foreach($content_arr as $var_ca){
            $row_data = $this->get_row_data_by_position($var_ca);
            //获取当前仓库branch_id
            foreach($this->branch_list as $var_bl){
                if($row_data["branch_name"] == $var_bl["name"]){
                    $branch_id = $var_bl["branch_id"];
                    break;
                }
            }
            //获取当前bm_id
            foreach($this->bm_list as $var_bl_v2){
                if($row_data["basic_ma_bn"] == $var_bl_v2["material_bn"]){
                    $bm_id = $var_bl_v2["bm_id"];
                    $bm_bn = $row_data["basic_ma_bn"];
                }
            }
            $current_data_arr = array(
                "branch_id" => $branch_id,
                "bm_id" => $bm_id,
                "freeze_num" => $row_data["freeze_num"],
                "reason" => $row_data["reason"],
                "op_id" => kernel::single('desktop_user')->get_id(),
                "group_name" => $row_data["group_name"],
                "bn" => $bm_bn,
            );
            if ($number < $limit){
                $number++;
            }else{
                $page++;
                $number = 0;
            }
            $sdfs[$page][] = $current_data_arr;
        }
        //加入队列任务
        $oQueue = app::get('base')->model('queue');
        foreach ($sdfs as $i){
            $queueData = array(
                    'queue_title'=>'人工库存预占导入',
                    'start_time'=>time(),
                    'params'=>array(
                        'sdfdata'=>$i,
                        'app' => 'console',
                        'mdl' => 'basic_material_stock_artificial_freeze'
                    ),
                    'worker'=>'console_stock_artificial_import.run',
            );
            $oQueue->save($queueData);
        }
        //成功
        return kernel::single('ome_func')->getApiResponse('');
    }

    //人工库存预占释放处理(支持批量)
    function do_unfreeze($bmsaf_ids,$batch = false){
        $ome_func_lib = kernel::single('ome_func');
        $check_result = $this->check_unfreeze_and_update($bmsaf_ids,$err_msg,$rs_freeze);
        if(!$check_result){
            $error_arr = array($err_msg);
            return $ome_func_lib->getErrorApiResponse($error_arr);
        }
        //check_unfreeze_and_update更新为已释放后 记录操作日志（区分单个和批量操作）
        $mdl_ome_operation_log = app::get('ome')->model('operation_log');
        $log_message = "释放人工库存预占记录";
        if($batch){
            $log_message = "批量".$log_message;
        }
        foreach($bmsaf_ids as $var_bi){
            $mdl_ome_operation_log->write_log('release_artificial_freeze@ome',$var_bi,$log_message);
        }
        //这里以branch_id为key 分组数据数组
        $rl_branch_id_info = array();
        foreach($rs_freeze as $var_rf){
            $rl_branch_id_info[$var_rf["branch_id"]][] = $var_rf;
        }
        //库存管控
        $storeManageLib = kernel::single('ome_store_manage');
        $params['node_type'] = "artificialUnfreeze";
        foreach($rl_branch_id_info as $key_branch_id => $var_bii){
            $storeManageLib->loadBranch(array('branch_id'=>$key_branch_id));
            $params['params'] = $var_bii;
            $processResult = $storeManageLib->processBranchStore($params,$err_msg);
            if(!$processResult){
                $error_arr = array($err_msg);
                return $ome_func_lib->getErrorApiResponse($error_arr);
            }
        }
        //成功
        return $ome_func_lib->getApiResponse($error_arr);
    }

    //检查当前预占释放的
    private function check_unfreeze_and_update($bmsaf_ids,&$err_msg,&$rs_unfreeze){
        if(empty($bmsaf_ids)){
            $err_msg = "请选择数据。";
            return false;
        }
        $mdl_af = app::get('material')->model('basic_material_stock_artificial_freeze');
        $rs_unfreeze = $mdl_af->getList("bmsaf_id,branch_id,bm_id,freeze_num,status,bn",array("bmsaf_id"=>$bmsaf_ids));
        //仓库权限
        $is_super = kernel::single('desktop_user')->is_super();
        if(!$is_super){ //不是超级管理员 仓库权限判断
            $branch_arr = $this->get_user_branch();
            if($branch_arr["branch_id"] == "-1"){
                $err_msg = "该用户无权操作仓库数据。";
                return false;
            }
            foreach($rs_unfreeze as $var_ru){
                if(!in_array($var_ru["branch_id"],$branch_arr["branch_id"])){
                    $err_msg = "该用户无权操作相关仓库数据。";
                    return false;
                }
            }
        }
        foreach($rs_unfreeze as $var_ru){
            if($var_ru["status"] != "1"){
                $err_msg = "选择的数据状态必须是“预占中”。";
                return false;
            }
            $_inner_key = sprintf("artunfreez_%s", $var_ru["bmsaf_id"]);
            $aData = cachecore::fetch($_inner_key);
            if ($aData === false) {
                cachecore::store($_inner_key, 'artunfreez', 300);
            }else{
                $err_msg = "选择的数据状态必须是“预占中”。";
                return false;
            }
        }
        //统一更新成已释放
        $update_arr = array("status"=>2,"op_id"=>kernel::single('desktop_user')->get_id(),"update_modified"=>time());
        $filter_arr = array("bmsaf_id"=>$bmsaf_ids);
        $mdl_af->update($update_arr,$filter_arr);
        return true;
    }

    //检查数据有效性
    private function valid_data($content_arr){
        $ome_func_lib = kernel::single('ome_func');
        //先判断是否有空值
        foreach($content_arr as $var_ca){
            if (!trim($var_ca[0]) || !trim($var_ca[1]) || !trim($var_ca[2])){
                $empty_error = "除了“预占原因”，其余字段不能为空，预占数量不能为0。";
                break;
            }
        }
        if($empty_error){
            return $ome_func_lib->getErrorApiResponse($empty_error);
        }
        //再验证数据
        $mdl_ome_branch = app::get('ome')->model('branch');
        $mdl_ma_ba = app::get('material')->model('basic_material');
        $mdl_ome_branch_product = app::get('ome')->model('branch_product');
        $error_arr = array();
        foreach ($content_arr as $ca){
            $row_data = $this->get_row_data_by_position($ca);
            if(is_numeric($row_data["freeze_num"]) && floor($row_data["freeze_num"]) == $row_data["freeze_num"] && $row_data["freeze_num"]>0){ //预占数量必须是大于0的整数（正整数）
            }else{
                $error_arr[] = "货号：".$row_data["basic_ma_bn"]."的预占数量必须是大于0的整数（正整数）。";
            }
            $rs_branch = $mdl_ome_branch->dump(array("name"=>$row_data["branch_name"],"b_type"=>1)); //目前只支持电商线上仓
            if(empty($rs_branch)){
                $error_arr[] = "仓库：".$row_data["branch_name"]."不存在。";
            }else{ //获取私有数组变量branch_list
                if(!isset($this->branch_list[$rs_branch["branch_id"]])){
                    $this->branch_list[$rs_branch["branch_id"]] = $rs_branch;
                }
            }
            $rs_material = $mdl_ma_ba->dump(array("material_bn"=>$row_data["basic_ma_bn"]));
            if(empty($rs_material)){
                $error_arr[] = "货号：".$row_data["basic_ma_bn"]."不存在。";
            }else{ //获取私有数组变量bm_list
                if(!isset($this->bm_list[$rs_material["bm_id"]])){
                    $this->bm_list[$rs_material["bm_id"]] = $rs_material;
                }
            }
            $rs_branch_product = $mdl_ome_branch_product->dump(array("branch_id"=>$rs_branch["branch_id"],"product_id"=>$rs_material["bm_id"]));
            if(empty($rs_branch_product)){
                $error_arr[] = "仓库：".$row_data["branch_name"]."下的货号：".$row_data["basic_ma_bn"]."不存在。";
            }
        }
        if(!empty($error_arr)){ //有错误信息
            return $ome_func_lib->getErrorApiResponse($error_arr);
        }else{ //无错误信息
            return $ome_func_lib->getApiResponse($error_arr);
        }
    }

    //处理上传文件并返回结果数据
    private function upload_file(){
        $ome_func_lib = kernel::single('ome_func');
        if(!$_FILES['import_file']['name']){
            return $ome_func_lib->getErrorApiResponse("未上传文件");
        }
        $oIo = kernel::servicelist('desktop_io');
        foreach( $oIo as $aIo ){
            if( $aIo->io_type_name == substr($_FILES['import_file']['name'],-3 ) ){
                $oImportType = $aIo;
                break;
            }
        }
        unset($oIo);
        if( !$oImportType ){
            return  $ome_func_lib->getErrorApiResponse("导入格式不正确");
        }
        //获取内容
        $tmpFileHandle = fopen( $_FILES['import_file']['tmp_name'],"r" );
        $contents = array();
        $oImportType->fgethandle($tmpFileHandle,$contents);
        fclose($tmpFileHandle);
        //去除标题行数据
        unset($contents[0]);
        //获取首列内容不为空的有效数据
        $real_contents = array();
        foreach($contents as $row){
            if(!empty($row[0])){
                $real_contents[] = $row;
            }
        }
        if(empty($real_contents)){
            return  $ome_func_lib->getErrorApiResponse("导入数据项为空");
        }else{
            return  $ome_func_lib->getApiResponse($real_contents);
        }
    }

    //根据位置获取导入数据
    private function get_row_data_by_position($row_arr){
        return array(
            "branch_name" => trim($row_arr[0]),
            "basic_ma_bn" => trim($row_arr[1]),
            "freeze_num" => trim($row_arr[2]),
            "reason" => trim($row_arr[3]),
            "group_name" => trim($row_arr[4]),
        );
    }

    //新增预占数据 返回lastInsert
    public function insert_freeze_data($current_data_arr){
        $mdl_artificial_freeze = app::get('material')->model('basic_material_stock_artificial_freeze');
        $insert_arr = array(
                "branch_id" => $current_data_arr["branch_id"],
                "bm_id" => $current_data_arr["bm_id"],
                "freeze_num" => $current_data_arr["freeze_num"],
                "freeze_reason" => $current_data_arr["reason"],
                "freeze_time" => time(),
                "op_id" => $current_data_arr["op_id"] ? $current_data_arr["op_id"] : kernel::single('desktop_user')->get_id(),
                "bn" => $current_data_arr["bn"],
        );
        if($current_data_arr["group_name"]){
            $mdl_artificial_freeze_group = app::get('material')->model('basic_material_stock_artificial_freeze_group');
            $rs_group = $mdl_artificial_freeze_group->dump(array("group_name"=>$current_data_arr["group_name"]));
            if(!empty($rs_group)){
                $insert_arr["group_id"] = $rs_group["group_id"];
            }else{
                $insert_group_arr = array(
                    "group_name" => $current_data_arr["group_name"]
                );
                $mdl_artificial_freeze_group->insert($insert_group_arr);
                $insert_arr["group_id"] = $mdl_artificial_freeze_group->db->lastInsertId();
            }
        }
        $mdl_artificial_freeze->insert($insert_arr);
        return $mdl_artificial_freeze->db->lastInsertId();
    }

    //导出模板内容
    function exportTemplate(){
        $arr = array('*:仓库名称','*:货号','*:预占数量','*:预占原因','*:组名');
        foreach ($arr as $v){
            $title[] = kernel::single('base_charset')->utf2local($v);
        }
        return $title;
    }

    //新增货品预占
    function do_add($post){
        $branch_id = $post["branch_id"];
        $bm_ids = $post["bm_id"];
        $freeze_num = $post["freeze_num"];
        $freeze_reason = $post["freeze_reason"];
        $group_name = trim($post["group_name"]);
        //数据验证
        if(!$branch_id){
            return array("res"=>"请重新选择仓库。");
        }
        if(empty($bm_ids)){
            return array("res"=>"请重新选择基础物料。");
        }
        if(is_numeric($freeze_num) && floor($freeze_num) == $freeze_num && $freeze_num>0){ //预占数量必须是大于0的整数（正整数）
        }else{
            return array("res"=>"预占数量必须是大于0的整数（正整数）。");
        }
        $mdl_ome_branch = app::get('ome')->model('branch');
        $mdl_ma_ba = app::get('material')->model('basic_material');
        $mdl_ome_branch_product = app::get('ome')->model('branch_product');
        $rs_branch = $mdl_ome_branch->dump(array("branch_id"=>$branch_id,"b_type"=>1)); //目前只支持电商线上仓
        if(empty($rs_branch)){
            return array("res"=>"所选仓库不存在。");
        }
        $bmIdBns = [];
        foreach($bm_ids as $var_bm_id){
            $rs_material = $mdl_ma_ba->dump(array("bm_id"=>$var_bm_id));
            if(empty($rs_material)){
                return array("res"=>"所选基础物料不存在。");
            }
            $rs_branch_product = $mdl_ome_branch_product->dump(array("branch_id"=>$branch_id,"product_id"=>$var_bm_id));
            if(empty($rs_branch_product)){
                return array("res"=>"所选仓库与所选物料无关联。");
            }
            $bmIdBns[$var_bm_id] = $rs_material['material_bn'];
        }
        //执行预占 库存管控
        $mdl_ome_operation_log = app::get('ome')->model('operation_log');
        $storeManageLib = kernel::single('ome_store_manage');
        $params['node_type'] = "artificialFreeze";
        $storeManageLib->loadBranch(array('branch_id'=>$branch_id));
        foreach($bm_ids as $var_bm_id_v2){
            $current_data_arr = array(
                "branch_id" => $branch_id,
                "bm_id" => $var_bm_id_v2,
                "freeze_num" => $freeze_num,
                "reason" => $freeze_reason,
                "group_name" => $group_name,
                "bn" => $bmIdBns[$var_bm_id_v2],
            );
            //新增人工预占数据
            $last_bmsaf_id= $this->insert_freeze_data($current_data_arr);
            $mdl_ome_operation_log->write_log('add_artificial_freeze@ome',$last_bmsaf_id,"新增人工库存预占记录");

            $params['params'][] = array_merge(array("obj_id"=>$last_bmsaf_id),$current_data_arr);
        }
        $processResult = $storeManageLib->processBranchStore($params,$err_msg);
        if(!$processResult){
            return array("res" => $err_msg);
        }
        //成功
        return array("rsp" => "succ");
    }

    //获取非超级管理员的branch_id权限
    public function get_user_branch(){
        $mdl_ome_branch = app::get('ome')->model('branch');
        $rs_branch = $mdl_ome_branch->getList();
        $branch_ids = array();
        foreach($rs_branch as $var_rb){
            $branch_ids[] = $var_rb["branch_id"];
        }
        if(!empty($branch_ids)){
            $branch_arr = array(
                "branch_id" => $branch_ids
            );
        }else{
            $branch_arr = array(
                "branch_id" => "-1"
            );
        }
        return $branch_arr;
    }

}
