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
 * 分片查询任务处理类
 *
 * @author kamisama.xia@gmail.com 
 * @version 0.1
 */

class ome_autotask_export_dataquerybyquicksheet
{
    //当前导出app
    static private $__app = '';

    //当前导出model
    static private $__model = '';

    //当前导出过滤条件
    static private $__filter = array();

    //当前导出查询起始位置
    static private $__start = 0;

    //当前导出查询结束位置
    static private $__end = 200;

    //当前导出任务号
    static private $__task_id = '';

    //当前导出任务的操作员
    static private $__op_id = '';

    //当前导出数据源配置
    static private $__data_source_cnf = array();

    //导出字段
    static private $__fields ='';

    //导出内容是否包含明细结构内容
    static private $__has_detail = 2;

    //导出任务当前分片数
    static private $__curr_sheet = 0;

    //导出任务总分片数
    static private $__sheet_sum = 1;

    //导出数据的存储过期时效
    static private $__expire_time = 86400;

    //最终过期的时间点
    static private $__ttl = '';

    public function __construct(){
        //当前存储的文件过期时间
        self::$__ttl = time() + self::$__expire_time;
    }

    public function process($params, &$error_msg=''){
        set_time_limit(0);
        ignore_user_abort(1);

        $date_source = $params['app'].'_mdl_'.$params['model'];

        //根据传入参数查询具体数据
        self::$__app = $params['app'];
        self::$__model = $params['model'];
        self::$__filter = unserialize($params['filter']);
        foreach (self::$__filter as $k => $val) {
            if(is_string($val) && strpos($val, "\r\n")) {
                self::$__filter[$k] = str_replace("\r\n", "\n", $val);
            }
        }
        self::$__data_source_cnf = ome_export_whitelist::allowed_lists($date_source);
        self::$__task_id = $params['task_id'];
        self::$__op_id = $params['op_id'];
        self::$__curr_sheet = $params['curr_sheet'];
        self::$__sheet_sum = $params['sheet_sum'];
        self::$__has_detail = $params['has_detail'];
//        self::$__fields = $params['fields'];
        //导出字段根据导出任务查询
        $ieTaskMdl = app::get('taoexlib')->model('ietask');
        $ieTaskDetail = $ieTaskMdl->dump(array('task_id'=>$params['task_id']),'filter_data');
        $filterDetail = unserialize($ieTaskDetail['filter_data']);
        if ($filterDetail['_export_mb'] == 1) {
            self::$__fields = $filterDetail['export_fields'];
        }elseif ($filterDetail['_export_mb'] == 2){
            $exptempObj = app::get('desktop')->model('export_template');
            $tempInfo = $exptempObj->dump(array('et_id'=>$filterDetail['extemp_id']),'et_filter');
            $curr_filter = unserialize($tempInfo['et_filter']);
            self::$__fields = $curr_filter['fields'];
        }
        self::$__start = isset($params['start']) ? $params['start'] : 0;
        self::$__end = isset($params['end']) ? $params['end'] : 0;

        //加载存储介质
        $cacheLib = kernel::single('taskmgr_interface_cache',self::$__task_id);

        //识别当前任务是否被删除，如果已删除的直接跳出返回成功
        $cacheLib->fetch('exp_task_'.self::$__task_id.'_status',$task_status);
        if ($task_status == 'del'){
            return true;
        }

        $exportObj = app::get(self::$__app)->model(self::$__model);
    
        //队列导出数据时，根据用户op_id判断权限。
        kernel::single('desktop_user')->setVirtualLogin(self::$__op_id);
        
        //根据当前导出对象获取导出数据，判断是否是自定义方法
        $data = array();
        if(method_exists($exportObj, 'getExportDataByCustom')){
            //开启高级搜索
            $exportObj->filter_use_like = true;
            $exportObj->is_export_data = true;
            $data = $exportObj->getExportDataByCustom(self::$__fields, self::$__filter, self::$__has_detail, self::$__curr_sheet, self::$__start, self::$__end, self::$__op_id);
        }else{
            //导出数据需要的传入参数
            $params = array(
                'fields' => self::$__fields,
                'filter' => self::$__filter,
                'has_detail' => self::$__has_detail,
                'curr_sheet' => self::$__curr_sheet,
                'op_id' => self::$__op_id,
            );

            $exportLib = kernel::single('desktop_finder_export');
            $data = $exportLib->work($date_source,$params);
        }

        //具体数据存储storage
        switch(self::$__data_source_cnf['structure']){
            //多层数据结构
            case 'multi':
                if(isset($data['content']['main']) && is_array($data['content']['main'])){
                    $main_content = '';
                    foreach($data['content']['main'] as $v) {
                        $main_content .= $v."\n";
                    }

                    $sm_res = $cacheLib->store('exp_body_main_'.self::$__task_id.'_'.self::$__curr_sheet,$main_content,self::$__ttl);
                    if(!$sm_res){
                        $error_msg = 'No '.self::$__curr_sheet.' sheet save main data fail';
                        return false;
                    }

                    //累加记录数统计
                    $cacheLib->increment('exp_task_'.self::$__task_id.'_records', count($data['content']['main']));
                }

                if(isset($data['content']['pair']) && is_array($data['content']['pair'])){
                    $pair_content = '';
                    foreach($data['content']['pair'] as $v) {
                        $pair_content .= $v."\n";
                    }

                    $sp_res = $cacheLib->store('exp_body_pair_'.self::$__task_id.'_'.self::$__curr_sheet,$pair_content,self::$__ttl);
                    if(!$sp_res){
                        $error_msg = 'No '.self::$__curr_sheet.' sheet save pair data fail';
                        return false;
                    }
                }

                if(isset($data['content']['snlists']) && is_array($data['content']['snlists'])){
                    $snlists_content = '';
                    foreach($data['content']['snlists'] as $v) {
                        $snlists_content .= $v."\n";
                    }

                    $ss_res = $cacheLib->store('exp_body_snlists_'.self::$__task_id.'_'.self::$__curr_sheet,$snlists_content,self::$__ttl);
                    if(!$ss_res){
                        $error_msg = 'No '.self::$__curr_sheet.' sheet save snlists data fail';
                        return false;
                    }
                }
                
                break;
            //单层数据结构
            case 'single':
                if(isset($data['content']['main']) && is_array($data['content']['main'])){
                    $main_content = '';
                    foreach($data['content']['main'] as $v) {
                        $main_content .= $v."\n";
                    }

                    $sm_res = $cacheLib->store('exp_body_main_'.self::$__task_id.'_'.self::$__curr_sheet,$main_content,self::$__ttl);
                    if(!$sm_res){
                        $error_msg = 'No '.self::$__curr_sheet.' sheet save main data fail';
                        return false;
                    }

                    //累加记录数统计
                    $cacheLib->increment('exp_task_'.self::$__task_id.'_records', count($data['content']['main']));
                    //error_log(count($data['content']['main'])."\n\t",3,"/www/count.log");
                }

                break;
            //omedlyexport_mdl_ome_delivery特殊的单层含明细
            case 'spec':
                if(isset($data['content']['main']) && is_array($data['content']['main'])){
                    $main_content = '';
                    foreach($data['content']['main'] as $v) {
                        $main_content .= $v."\n";
                    }

                    $sm_res = $cacheLib->store('exp_body_main_'.self::$__task_id.'_'.self::$__curr_sheet,$main_content,self::$__ttl);
                    if(!$sm_res){
                        $error_msg = 'No '.self::$__curr_sheet.' sheet save main data fail';
                        return false;
                    }

                    //累加记录数统计
                    $cacheLib->increment('exp_task_'.self::$__task_id.'_records', count($data['content']['main']));
                }

                //数据获取完标记任务为单层结构
                self::$__has_detail = 2;

                break;
        }

        //标记分片任务完成
        $cacheLib->store('exp_sheet_'.self::$__task_id.'_'.self::$__curr_sheet, 'succ',self::$__ttl);
        //总完成分片计数
        $cacheLib->increment('exp_task_'.self::$__task_id.'_counter',1);

        //判断是否整个任务已完成，加入归整任务队列
        if($cacheLib->fetch('exp_task_'.self::$__task_id.'_counter',$count)){
            if($count == self::$__sheet_sum){
                //取总记录数
                if(!$cacheLib->fetch('exp_task_'.self::$__task_id.'_records',$records)){
                    $records = 0;
                }else{
                    //去掉标题的那一行
                    $records = $records-1;
                }

                //判断是否所有分片任务都完成，完成往归档任务队列添加任务
                $push_params = array(
                    'data' => array(
                        'task_id' => self::$__task_id,
                        'sheet_sum' => self::$__sheet_sum,
                        'records' => $records,
                        'has_detail' => self::$__has_detail,
                        'task_type' => 'createfile'
                    ),
                    'url' => kernel::openapi_url('openapi.autotask','service')
                );
                kernel::single('taskmgr_interface_connecter')->push($push_params);
            }
        }
        //error_log($ooo."\n\t",3,"/www/count.log");

        return true;
    }

}