<?php
/**
 * 每日金价
 *
 * @author xiayuanjun@shopex.cn
 * @version 0.1
 */

class goldentra_mdl_daily_goldprice extends dbeav_model {

    var $defaultOrder = array('price_date',' DESC');

    public function modifier_price_date($row){
       if($row){
           return date('Y-m-d', $row);
       }else{
           return '-';
       }
    }
}
