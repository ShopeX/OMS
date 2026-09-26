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
 * 账单导入控制层
 *
 * @author 334395174@qq.com
 * @version 0.1
 */
class financebase_ctl_admin_shop_settlement_import extends desktop_controller
{
    /**
     * treat
     * @return mixed 返回值
     */

    public function treat(){
        @ini_set('memory_limit','512M');
        $this->begin('index.php?app=financebase&ctl=admin_shop_settlement_bill&act=bill_import');

        $type = $_POST['type'] ? $_POST['type'] : 'alipay';



        if( $_FILES['import_file']['name'] && $_FILES['import_file']['error'] == 0 ){
            $file_type = substr($_FILES['import_file']['name'],strrpos($_FILES['import_file']['name'],'.')+1);
            $file_type = strtolower($file_type);
            if(in_array($file_type, array('csv','xls','xlsx','et'))){

                if ($file_type === 'et') {
                    $file_type = 'xls';
                }

                $oProcess = kernel::single('financebase_data_bill_'.$type);
                $oFunc = kernel::single('financebase_func');

                list($checkRs,$errmsg) = $oProcess->checkFile($_FILES['import_file']['tmp_name'],$file_type);
                if(!$checkRs){
                    $this->end(false, app::get('base')->_('上传文件数据不对,'.$errmsg));
                }

                $shop_info = app::get('ome')->model('shop')->getList('shop_id,name',array('shop_id'=>$_POST['shop_id']));
                $shop_info = $shop_info[0];
                $bill_date = date('Y-m-d');

                //临时文件生成后往ftp服务器迁移
                $storageLib = kernel::single('taskmgr_interface_storage');
                $remote_url = '';
                $move_res = $storageLib->save($_FILES['import_file']['tmp_name'], md5($_FILES['import_file']['name'].time()).'.'.$file_type, $remote_url);
                
                if(!$move_res)
                {
                    $this->end(false, app::get('base')->_('文件上传失败'));
                }else{

                    $mdlQueue = app::get('financebase')->model('queue');
                    $queueData = array();
                    
                    // 京东钱包导入需要特殊处理
                    if ($type === 'jdwallet') {
                        // 创建导入记录
                        $importData = array(
                            'shop_id' => $_POST['shop_id'],
                            'shop_name' => $shop_info['name'],
                            'file_name' => $_FILES['import_file']['name'],
                            'file_size' => $_FILES['import_file']['size'],
                            'import_time' => time(),
                            'status' => 'processing'
                        );
                        $mdlImport = app::get('financebase')->model('bill_import_jdwallet');
                        $import_id = $mdlImport->insert($importData);
                        
                        $queueData['queue_mode'] = 'billJdWalletAssign';
                        $queueData['queue_name'] = sprintf("%s_%s_京东钱包导入_分派任务",$shop_info['name'],$bill_date);
                        $queueData['queue_data']['import_id'] = $import_id;
                    } else {
                        $queueData['queue_mode'] = 'billAssign';
                        $queueData['queue_name'] = sprintf("%s_%s_导入文件_分派任务",$shop_info['name'],$bill_date);
                    }
                    
                    $queueData['create_time'] = time();
                    $queueData['queue_data']['shop_id'] = $_POST['shop_id'];
                    $queueData['queue_data']['shop_name'] = $shop_info['name'];
                    $queueData['queue_data']['bill_date'] = $bill_date;
                    $queueData['queue_data']['shop_type'] = $type;
                    $queueData['queue_data']['task_name'] = base64_encode(basename($_FILES['import_file']['name']));
                    $queueData['queue_data']['file_type'] = $file_type;
                    $queueData['queue_data']['remote_url']= base64_encode($remote_url);

                    $queue_id = $mdlQueue->insert($queueData);
                    
                    if ($type === 'jdwallet') {
                        financebase_func::addTaskQueue(array('queue_id'=>$queue_id),'billjdwalletassign');
                    } else {
                        financebase_func::addTaskQueue(array('queue_id'=>$queue_id),'billassign');
                    }

                    $this->end(true, app::get('base')->_('上传成功 已加入队列 系统会自动跑完队列'));
                }

            }else{
                $this->end(false, app::get('base')->_('不支持此文件'));
            }

        }else{
            $this->end(false, "上传失败");
        }

        // $echoMsg = '';
        // header("content-type:text/html; charset=utf-8");
        // echo "<script>parent.MessageBox.success(\"上传成功\");alert(\"".$echoMsg."\");if(parent.$('import_form').getParent('.dialog'))parent.$('import_form').getParent('.dialog').retrieve('instance').close();if(parent.window.finderGroup&&parent.window.finderGroup['".$_GET['finder_id']."'])parent.window.finderGroup['".$_GET['finder_id']."'].refresh();</script>";
        
    }
}