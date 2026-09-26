<?php
class omeanalysts_ctl_ome_salestatistics extends desktop_controller{
    
    var $name = '销售统计';

    function index(){

        //修改日期范围
        $_SESSION['data'] = $_REQUEST;
        //print_r($_SESSION);
//        $this->mod_query_time();
        //售后统计crontab的手动调用
        //kernel::single('omeanalysts_crontab_script_sale')->statistics();
        kernel::single('omeanalysts_ome_salestatistics')->set_params($_POST)->display();
    }

    /**
     * 菜单/历史链接兼容：desktop.xml 曾配置 act=salestatistics
     */
    function salestatistics()
    {
        $this->index();
    }

    //销售统计
    public function export(){
        $name = $_POST['name'];
        $filetype = $_POST['filetype'];
        $filter = unserialize($_POST['filter']);
        $data = $_SESSION['data'];
        $filter = $data;
        $filter['time_from'] = strtotime($filter['time_from'] );
        $filter['time_to'] = strtotime($filter['time_to'] );

        $params = array(
            'name' => $name,
            'app' => 'omeanalysts',
            'model' => 'ome_salestatistics',
            'type' => 'export',
            'filetype' => $filetype,
            'filter' => $filter,
            'single'=> array(
                '1'=> array(
                    'method' => 'get_export_main',
                    'offset' => 0,
                    'limit' => 500,
                    'filename' => 'salestatisticsContent',
                ),
            ),
        );
        $task = kernel::service('service.queue.ietask');
        $task->create($params);
    }
    
    function mod_query_time(){
        
        $date_arr = $this->get_time_start();
        
        //点击销售统计链接请求的页面,默认显示当月的日视角

        if((empty($_REQUEST) || empty($_REQUEST['report'])) && !isset($_REQUEST['time_from']) && !isset($_REQUEST['time_to'])){
            //echo '111';echo '<br>';
            $_POST['report'] = 'day';
            $_POST['time_from'] = $date_arr['now_zero_month'];
            $_POST['time_to'] = $date_arr['now_end_month'];
        }elseif(isset($_REQUEST['report']) && !isset($_REQUEST['time_from']) && !isset($_REQUEST['time_to'])){
            //echo '222';echo '<br>';
            if($_REQUEST['report'] == 'day'){
                $_POST['report'] = 'day';
                $_POST['time_from'] = $date_arr['now_zero_month'];
                $_POST['time_to'] = $date_arr['now_end_month'];
            }elseif($_REQUEST['report'] == 'month'){
                $_POST['report'] = 'month';
                $_POST['time_from'] = $date_arr['now_zero_year'];
                $_POST['time_to'] = $date_arr['now_end_year'];
            }
        }elseif(isset($_REQUEST['report']) && $_REQUEST['report'] == 'month' && isset($_REQUEST['time_from']) && isset($_REQUEST['time_to'])){
            //echo '333';echo '<br>';
            $pp = $this->set_abscissa($_POST['report'],$_POST['time_from'],$_POST['time_to']);
            if(count($pp['days']) == 1){
                $_POST['report'] = 'day';
            }
        }elseif(!isset($_REQUEST['report']) && isset($_REQUEST['time_from']) && isset($_REQUEST['time_to'])){
            //echo '444';echo '<br>';
            $r = '';
            base_kvstore::instance('omeanalysts_report')->fetch('report',$r);
            $_POST['report'] = $r;
            $_POST['time_from'] = $_REQUEST['time_from'];
            $_POST['time_to'] = $_REQUEST['time_to'];
            
        }else{
            //echo '555';echo '<br>';
            $_POST['report'] = $_REQUEST['report'];
            $_POST['time_from'] = $_REQUEST['time_from'];
            $_POST['time_to'] = $_REQUEST['time_to'];
        }
        base_kvstore::instance('omeanalysts_report')->store('report',$_POST['report']);
        //base_kvstore::instance('omeanalysts_goodsrma')->fetch('goodsrma_time',$old_time);
        //base_kvstore::instance('omeanalysts_goodsrma')->store('goodsrma_time',$old_time);
        //base_kvstore::instance('omeanalysts_goodsrma')->delete('goodsrma_time');
    }
    
    function get_time_start(){
        $t   =   time();   
        $nowday   =   date('Y-m-d',mktime(0,0,0,date("m",$t),date("d",$t),date("Y",$t)));           //当天零点的时间戳 
        $nowmonth   =   date('Y-m-d',mktime(0,0,0,date("m",$t),1,date("Y",$t)));                    //当月一号零点的时间戳
        $nextmonth =  date('Y-m-d',mktime(0,0,0,date("m",$t)+1,1,date("Y",$t)));                    //下月一号零点时间戳
        $nowyear    =   date('Y-m-d',mktime(0,0,0,1,1,date("Y",$t)));                               //当年一月一号零点时间戳
        $lastmonth =  date('Y-m-d',mktime(0,0,0,date("m",$t)-1,1,date("Y",$t)));                    //上月一号零点时间戳
        $nowendmonth = date('Y-m-d',time()-24*60*60);
        $nowendyear = date('Y-m-d',mktime(0,0,0,12,31,date("Y",$t)));
        return array('now_zero_day' => $nowday,
                     'now_zero_month'=>$nowmonth,
                     'now_zero_year'=>$nowyear,
                     'next_zero_month'=>$nextmonth,
                     'last_zero_month'=>$lastmonth,
                     'now_end_month'=>$nowendmonth,
                     'now_end_year'=>$nowendyear
        );
    }
    
    function set_abscissa($form,$time_from,$time_to){
        
        $start = strtotime($time_from);
        $end = strtotime($time_to);
        $day_diff = ($end-$start)/(24*60*60);

        for($i=1;$i<=$day_diff;$i++){
            $days[] = $start+$i*(24*60*60);
        }
        $darray = array();

        if($form == 'day'){
            $darray[] = date('Y-m-d',$start);
            foreach($days as $day){
                $darray[] = date('Y-m-d',$day);
            }
        }elseif($form == 'month'){
            $darray[] = date('Y-m',$start);
            foreach($days as $day){
                $darray[] = date('Y-m',$day);
            }
            $darray = array_unique($darray);
        }
        $days[] = $start;

        $datestr = "[";
        foreach ($darray as $d){
            $datestr .= "'".$d."', ";
        }
        return array('categories'=>rtrim($datestr," ,")."]",'times'=>$days,'days'=>$darray);
    }

    function chart_view(){
        
        $report = $_GET['report'];
        $filter = array('time_from'=>$_GET['time_from'],
                        'time_to'=>$_GET['time_to'],
                        'report'=>$report,
                        'type_id'=>$_GET['type_id'] ? $_GET['type_id'] : 0);
                
        $list = $this->set_abscissa($report,$_GET['time_from'],$_GET['time_to']);

        $statObj = $this->app->model('ome_salestatistics');
        $statdata = $statObj->getlist('*',$filter);
        
        //下单量，发货量，销售额，售后量，完成售后量，负销售额
        $datastr = "[{name: '下单量',data: [%s]},{name: '发货量',data: [%s]},{name: '销售额',data: [%s]},{name: '负销售额',data: [%s]},{name: '售后量',data: [%s]},{name: '完成售后量',data: [%s]}]";
        if($statdata){
            $last_datetime = '';
            
            foreach($statdata as $data){
                //$datestr .= "'".$data['day']."',";
                $date_array[$data['day']] = $data['day'];
                $order_sum[$data['day']] += $data['order_num'];
                $delivery_sum[$data['day']] += $data['delivery_num'];
                $sale_total_sum[$data['day']] += $data['sale_total'];
                $minus_sale_total_sum[$data['day']] += $data['minus_sale_total'];
                $return_total_sum[$data['day']] += $data['return_total'];
                $ok_return_total_sum[$data['day']] += $data['ok_return_total'];
            }
            $order_sum_str = implode(", ", $order_sum);
            $delivery_sum_str = implode(", ", $delivery_sum);
            $sale_total_sum_str = implode(", ", $sale_total_sum);
            $minus_sale_total_sum_str = implode(", ", $minus_sale_total_sum);
            $return_total_sum_str = implode(", ", $return_total_sum);
            $ok_return_total_sum_str = implode(", ", $ok_return_total_sum);
            $datestr = "['".implode("','", $date_array)."']";

        }else{
            $datestr = $list['categories'];
            for($i = 0; $i<count($list['days']); $i++){
                $order_sum_str                  .= '0, ';
                $delivery_sum_str               .= '0, ';
                $sale_total_sum_str             .= '0, ';
                $minus_sale_total_sum_str       .= '0, ';
                $return_total_sum_str           .= '0, ';
                $ok_return_total_sum_str        .= '0, ';
            }
        }
        $order_sum_str              = rtrim($order_sum_str,', ');
        $delivery_sum_str           = rtrim($delivery_sum_str,', ');
        $sale_total_sum_str         = rtrim($sale_total_sum_str,', ');
        $minus_sale_total_sum_str   = rtrim($minus_sale_total_sum_str,', ');
        $return_total_sum_str       = rtrim($return_total_sum_str,', ');
        $ok_return_total_sum_str    = rtrim($ok_return_total_sum_str,', ');
        
        
        
        $this->pagedata['categories'] = $datestr;
        $this->pagedata['data'] = sprintf($datastr,$order_sum_str,$delivery_sum_str,$sale_total_sum_str,$minus_sale_total_sum_str,$return_total_sum_str,$ok_return_total_sum_str);
        $this->display("ome/chart_type_line.html");
    }
}
