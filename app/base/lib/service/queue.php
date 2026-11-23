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

class base_service_queue 
{

    public function worker(){
        set_time_limit(0);
        ignore_user_abort(true);
        $task_id = intval($_POST['task_id']);
        $now = time();
        $queueModel = app::get('base')->model('queue');
        if($_POST['runkey']){
            $runkey = $_POST['runkey'];
            $queueModel->db->exec('update sdb_base_queue set status="running",worker_active='.$now.'
                where queue_id='.intval($task_id).' and runkey='.$queueModel->db->quote($runkey)); 
        }else{
            $runkey = md5(microtime().rand(0,9999));
            $queueModel->db->exec($sql='update sdb_base_queue set status="running",runkey="'.$runkey.'",worker_active='.$now.'
                where queue_id='.intval($task_id).' and status="hibernate" 
                or (status="running" and worker_active<'.($now-$queueModel->task_timeout).')');
        }
        if($queueModel->db->affect_row()){
            $task_info = $queueModel->dump($task_id);
            list($worker,$method) = explode('.',$task_info['worker']);
            $errmsg = null;
			$obj_work = kernel::single($worker);
            //$remaining = call_user_func_array( array(  $worker ,$method),array(&$task_info['cursor_id'],$task_info['params'], &$errmsg));
			$remaining = call_user_func_array( array(  $obj_work ,$method),array(&$task_info['cursor_id'],$task_info['params'], &$errmsg));
            //$remaining = kernel::single($worker)->$method(&$task_info['cursor_id'] , $task_info['params'] , &$errmsg);
            if($remaining){
                $queueModel->db->exec('update sdb_base_queue set status="hibernate",cursor_id='.$queueModel->db->quote($task_info['cursor_id']).' where queue_id='.intval($task_id));
                $queueModel->runtask($task_id,$remaining);
            }else{
                if(is_null($errmsg)){
                    $queueModel->db->exec('delete from sdb_base_queue where queue_id='.intval($task_id));
                }else{
                    $queueModel->db->exec('update sdb_base_queue set status="failure",errmsg='.$queueModel->db->quote($errmsg).' where queue_id='.intval($task_id));    //todo:如果有错误信息
                }
                exit;
            }
        }
    }

}//End Class