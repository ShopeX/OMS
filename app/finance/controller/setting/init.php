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

class finance_ctl_setting_init extends desktop_controller{
    var $name = "账期设置";

    public function index(){
        #账单起始年月日
        $tyear = date('Y');
        for($y=$tyear-5;$y<=$tyear+5;$y++){
             $year[$y] = $y.'年';
        }
        for($m=1;$m<=12;$m++){
             $month[$m] = $m.'月';
        }
        for($d=1;$d<=28;$d++){
             $day[$d] = $d.'日';
        }
        $init_time = app::get('finance')->getConf('finance_setting_init_time');

        
     

        $this->pagedata['year'] = $year;
        $this->pagedata['month'] = $month;
        $this->pagedata['day'] = $day;
        $this->pagedata['init_time'] = $init_time;
        $this->pagedata['isSaveInitTime'] = 'false';
        $this->pagedata['isInit'] = $init_time['flag'];

        $this->pagedata['feeTypeCount'] = app::get('financebase')->model('bill_fee_type')->count();

        #判断是否可更改初始化时间
        /*$finance_ar_mdl = app::get('finance')->model('ar');
        $finance_bill_mdl = app::get('finance')->model('bill');
        $filter['charge_status'] = 0;
        if($finance_ar_mdl->count($filter) > 0 || $finance_bill_mdl->count($filter) > 0) $isSaveInitTime = 'false';
        else $isSaveInitTime = 'true';*/
   

        // $this->pagedata['canImport'] = in_array($_SERVER['SERVER_NAME'],array('mmfs.erp.shopexdrp.cn','jylmall.erp.shopexdrp.cn')) ? 'true' : 'false';
        
        $this->display('setting/init.html');
    }

    public function save_init_time(){
        if($_POST){
            $this->begin('index.php#app=finance&ctl=monthend&act=index');
            $init_time = $_POST['init_time'];
            $init_time['flag'] = 'false';

            $oQueue = app::get('financebase')->model('queue');
            app::get('finance')->setConf('finance_setting_init_time',$init_time);

            $init_date_time = strtotime(sprintf("%d-%d-%d",$init_time['year'],$init_time['month'],$init_time['day']));
            $incr = $init_time['cycle'] == "day" ? "+1 day" : "+1 month";
            $next_time = strtotime($incr);
            while (true) {
                if ($init_date_time > $next_time ) break;
                $next_data_time = strtotime($incr,$init_date_time);
                $monthly_date = $init_time['cycle'] == "day" ? date('Y年m月d日账期', $init_date_time) : date('Y年m月账期', $init_date_time);

                $queueData = array();
                $queueData['queue_mode'] = 'initMonthlyReport';
                $queueData['create_time'] = time();
                $queueData['queue_name']  = sprintf("账期初始化_%s", $monthly_date);
                $queueData['queue_data']  = array('begin_time' => $init_date_time, 'end_time' => $next_data_time - 1, 'monthly_date' => $monthly_date);
              
                $queue_id = $oQueue->insert($queueData);
                //$queue_id and financebase_func::addTaskQueue(array('queue_id'=>$queue_id),'initmonthlyreport');

                $init_date_time = $next_data_time;
            }

            $this->end(true);
        }
    }

    // public function save_init(){
    //     if($_POST){
    //         $this->begin('index.php#app=finance&ctl=setting_init&act=index');
    //         $isInit = $_POST['isInit'];
    //         if($isInit == 'true'){
    //             $rs = kernel::single('finance_monthly_report')->set_init_charge();
    //             if($rs == 'true'){
    //                 $init_time = app::get('finance')->getConf('finance_setting_init_time');
    //                 $init_time['flag'] = 'true';
    //                 app::get('finance')->setConf('finance_setting_init_time',$init_time);
    //                 $this->end(true);
    //             }
    //         }
    //         $this->end(false);
    //     }
    // }

    // public function exportTemplate_act($filter = ''){
    //     return $this->fetch('setting/export.html');
    // }


}