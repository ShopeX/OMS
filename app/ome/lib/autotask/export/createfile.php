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
class ome_autotask_export_createfile
{

    //导出数据的存储过期时效
    static private $__task_id = 0;

    //任务分片数
    static private $__sheet_sum = 1;

    //任务是否含明细
    static private $__has_detail = 2;

    //导出任务总记录数
    static private $__records = 0;

    /**
     * 处理
     * @param mixed $params 参数
     * @param mixed $error_msg error_msg
     * @return mixed 返回值
     */
    public function process($params, &$error_msg=''){
        set_time_limit(0);
        @ini_set('memory_limit','512M');
        ignore_user_abort(1);

        //根据传入参数查询具体数据
        self::$__task_id = $params['task_id'];
        self::$__sheet_sum = $params['sheet_sum'];
        self::$__has_detail = $params['has_detail'];
        self::$__records = $params['records'];

        //加载存储介质
        $cacheLib = kernel::single('taskmgr_interface_cache',self::$__task_id);
        $charset = kernel::single('base_charset');
        //记录导出文件类型
        if(!$cacheLib->fetch('exp_task_'.self::$__task_id.'_type',$ioType)) {
            $ioType = 'csv';
        }
        $path = DATA_DIR.'/export/tmp_remote';
        $filename = $path."/".md5(microtime().KV_PREFIX).self::$__task_id;
        if($ioType == 'xls') {
            $filename = $filename . '.xlsx';
            $rs = $this->_processXLS($filename, $error_msg);
        } else {
            $filename .= '.csv';
            $rs = $this->_processCSV($filename, $error_msg);
        }
        if (!$rs) {
            return true;
        }
        //临时文件生成后往ftp服务器迁移
        $storageLib = kernel::single('taskmgr_interface_storage');
        $move_res = $storageLib->save($filename, self::$__task_id, $remote_url);
        if($move_res){
            //保存任务执行的相关结果信息
            //改成update方式，有的客户嫌慢直接把任务删了，save方式有问题
            $ietaskObj = app::get('taoexlib')->model('ietask');

            $ietask_data = array(
                'file_name' => $remote_url,
                'total_count' => self::$__records,
                'last_time' => time(),
                'expire_time' => strtotime(date('Ymd'))+3*86400,
                'status' => 'finished',
                'msg'   => '',
            );
            $ietaskObj->update($ietask_data, array('task_id' => self::$__task_id));

            //删除临时数据缓存，主要针对是本地文件形式的
             $cacheLib->delete('exp_task_'.self::$__task_id.'_counter');
             $cacheLib->delete('exp_task_'.self::$__task_id.'_records');

            for($i=1;$i<=self::$__sheet_sum;$i++) {
                $cacheLib->delete('exp_body_main_'.self::$__task_id.'_'.$i);
            }

            if(self::$__has_detail == 1){
                for($i=1;$i<=self::$__sheet_sum;$i++) {
                    $cacheLib->delete('exp_body_pair_'.self::$__task_id.'_'.$i);
                }
                for($i=1;$i<=self::$__sheet_sum;$i++) {
                    $cacheLib->delete('exp_body_snlists_'.self::$__task_id.'_'.$i);
                }
            }

            //删除导出内容在本地生成的临时文件
            @unlink($filename);
        }else{
            $error_msg = 'remote create file fail';
            //删除导出内容在本地生成的临时文件
            @unlink($filename);
            //标记当前任务临时文件生成但是没迁移成功
            return false;
        }

        return true;
    }

    protected function _processCSV($filename, &$error_msg) {
        $handle = fopen($filename, "a");
        $cacheLib = kernel::single('taskmgr_interface_cache',self::$__task_id);

        $ietaskMdl = app::get('taoexlib')->model('ietask');
        $ietask = $ietaskMdl->db_dump(self::$__task_id, 'app,model');
        if (!$ietask) {
            $error_msg = 'task not found';
            return false;
        }
        $mdl = app::get($ietask['app'])->model($ietask['model']);

        for($i=1;$i<=self::$__sheet_sum;$i++) {
            if($cacheLib->fetch('exp_body_main_'.self::$__task_id.'_'.$i,$main_content)){
                fwrite($handle,$main_content);
            }else{
                $error_msg = 'No '.$i.' main sheet no data or can\'t get data';

                $ietaskMdl->update(['status' => 'fail', 'msg' => $error_msg], ['task_id' => self::$__task_id]);

                fclose($handle);
                @unlink($filename);
                return false;
            }
        }



        if(self::$__has_detail == 1 && !method_exists($mdl, 'getExportDetailV2')){
            for($i=1;$i<=self::$__sheet_sum;$i++) {
                if($cacheLib->fetch('exp_body_pair_'.self::$__task_id.'_'.$i,$pair_content)){
                    fwrite($handle,$pair_content);
                }else{
                    $error_msg = 'No '.$i.' pair sheet no data or can\'t get data';

                    $ietaskMdl->update(['status' => 'fail', 'msg' => $error_msg], ['task_id' => self::$__task_id]);

                    fclose($handle);
                    @unlink($filename);
                    return false;
                }
            }

        }

        if(self::$__has_detail == 1 && method_exists($mdl, 'getexportsnlists')){
            for($i=1;$i<=self::$__sheet_sum;$i++) {
                if($cacheLib->fetch('exp_body_snlists_'.self::$__task_id.'_'.$i,$snlists_content)){
                    fwrite($handle,$snlists_content);
                }
            }
        }

        fclose($handle);
        return true;
    }

    protected function _processXLS($filename, &$error_msg) {
        $cacheLib = kernel::single('taskmgr_interface_cache',self::$__task_id);
        
        $path = pathinfo($filename);
        $excel = new \Vtiful\Kernel\Excel(['path' => $path['dirname']]);

        // fileName 会自动创建一个工作表，你可以自定义该工作表名称，工作表名称为可选参数
        $fileObject = $excel->constMemory($path['basename'], 'main', false);

        $ietaskMdl = app::get('taoexlib')->model('ietask');
        $ietask = $ietaskMdl->db_dump(self::$__task_id, 'app,model');

        if (!$ietask) {
            $error_msg = 'task not found';
            return false;
        }

        $mdl = app::get($ietask['app'])->model($ietask['model']);

        for($i=1;$i<=self::$__sheet_sum;$i++) {
            if($cacheLib->fetch('exp_body_main_'.self::$__task_id.'_'.$i,$main_content)){
                $arrData = explode("\n", $main_content);
                foreach ($arrData as $k => $v) {
                    if(empty($v)) {
                        unset($arrData[$k]);
                        continue;
                    }
                    $arrData[$k] = explode(',', $v);
                    foreach ($arrData[$k] as $kk => $vv) {
                        $arrData[$k][$kk] = mb_convert_encoding($vv, 'UTF-8', 'GBK');
                    }
                }
                if($i == 1) {
                    $title = array_shift($arrData);
                    $title = $title ? : [];
                    $fileObject = $fileObject->header($title);
                }
                $fileObject->data($arrData);
            }else{
                $error_msg = 'No '.$i.' main sheet no data or can\'t get data';

                $ietaskMdl->update(['status' => 'fail', 'msg' => $error_msg], ['task_id' => self::$__task_id]);
                return false;
            }
        }



        if(self::$__has_detail == 1 && !method_exists($mdl, 'getExportDetailV2')){
            $fileObject = $fileObject->addSheet('items');
            for($i=1;$i<=self::$__sheet_sum;$i++) {
                if($cacheLib->fetch('exp_body_pair_'.self::$__task_id.'_'.$i,$pair_content)){
                    $arrData = explode("\n", $pair_content);
                    foreach ($arrData as $k => $v) {
                        if(empty($v)) {
                            unset($arrData[$k]);
                            continue;
                        }
                        $arrData[$k] = explode(',', $v);
                        foreach ($arrData[$k] as $kk => $vv) {
                            $arrData[$k][$kk] = mb_convert_encoding($vv, 'UTF-8', 'GBK');
                        }
                    }
                    if($i == 1) {
                        $title = array_shift($arrData);
                        $title = $title ? : [];
                        $fileObject = $fileObject->header($title);
                    }
                    $fileObject->data($arrData);
                }else{
                    $error_msg = 'No '.$i.' pair sheet no data or can\'t get data';

                    $ietaskMdl->update(['status' => 'fail', 'msg' => $error_msg], ['task_id' => self::$__task_id]);

                    return false;
                }
            }

        }

        if(self::$__has_detail == 1 && method_exists($mdl, 'getexportsnlists')){
            $fileObject = $fileObject->addSheet('snlists');
            for($i=1;$i<=self::$__sheet_sum;$i++) {
                if($cacheLib->fetch('exp_body_snlists_'.self::$__task_id.'_'.$i,$snlists_content)){
                    $arrData = explode("\n", $snlists_content);
                    foreach ($arrData as $k => $v) {
                        if(empty($v)) {
                            unset($arrData[$k]);
                            continue;
                        }
                        $arrData[$k] = explode(',', $v);
                        foreach ($arrData[$k] as $kk => $vv) {
                            $arrData[$k][$kk] = mb_convert_encoding($vv, 'UTF-8', 'GBK');
                        }
                    }
                    if($i == 1) {
                        $title = array_shift($arrData);
                        $title = $title ? : [];
                        if (!empty($title)) {
                            $fileObject = $fileObject->header($title);
                        }
                    }
                    if (!empty($arrData)) {
                        $fileObject->data($arrData);
                    }
                }
            }
        }
        $fileObject->output();
        return true;
    }
}
