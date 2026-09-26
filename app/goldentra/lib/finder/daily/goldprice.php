<?php
/**
 * 每日金价Finder
 *
 * @author sunjing@shopex.cn
 * @version 0.1
 */

class goldentra_finder_daily_goldprice{


    var $detail_history = '操作日志';
    
    function detail_history($goldprice_id){

        $render = app::get('goldentra')->render();

        $logObj = app::get('ome')->model('operation_log');
        $ooObj = app::get('ome')->model('operations_order');

        $history = $logObj->read_log(array('obj_id'=>$goldprice_id,'obj_type'=>'daily_goldprice@goldentra'),0,-1);
		foreach($history as $k=>$v){
            $data = $ooObj->getList('operation_id',array('log_id'=>$v['log_id']));
            if(!empty($data)){
                $history[$k]['flag'] ='true';
            }else{
                $history[$k]['flag'] ='false';
            }
			$history[$k]['operate_time'] = date('Y-m-d H:i:s',$v['operate_time']);
		}
        $render->pagedata['history'] = $history;

        return $render->fetch('admin/daily_goldprice/detail_history.html');
    }


    var $column_edit = "操作";
    var $column_edit_width = "200";
    function column_edit($row){
        $goldprice_id = $row['goldprice_id'];
        $shop_bn = $row['shop_bn'];

        $finder_id = $_GET['_finder']['finder_id'];

        $button = <<<EOF
<a href='javascript:void(0);' onclick="new Dialog('index.php?app=goldentra&ctl=admin_daily_goldprice&act=edit&p[0]={$goldprice_id}&finder_id={$finder_id}',{width:600,height:300,title:'编辑'});">编辑</a>
EOF;

        //<a href='javascript:void(0);' onclick="new Dialog('index.php?app=goldentra&ctl=admin_daily_goldprice&act=async_update_pricetag&p[0]={$shop_bn}&finder_id={$finder_id}',{width:600,height:300,title:'更新价格'});">更新价格</a>
        //return "<a href='javascript:void(0);' onclick=\"new Dialog('index.php?app=goldentra&ctl=admin_daily_goldprice&act=edit&p[0]={$dgp_id}&finder_id={$finder_id}',{width:600,height:300,title:'编辑'});\">编辑</a>";

        return $button;
    }
    
}