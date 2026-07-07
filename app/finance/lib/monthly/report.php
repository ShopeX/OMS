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
* @copyright shopex.cn
* @author Kris.wang 406048119@qq.com
* @version ocs
*/
class finance_monthly_report
{
    public $monthly_report = array();
    /*
    **设置期初数据,只有初始化时调用此方法
    **@return 'true/false 字符串'
    */
    // public function set_init_charge(){
    //     return 'true';
    //     $mrObj = app::get('finance')->model('monthly_report');
    //     $billObj = app::get('finance')->model('bill');
    //     $arObj = app::get('finance')->model('ar');
    //     $time = $this->get_init_time();
    //     $time = $time['time'];
    //     $data = array(
    //         'monthly_date'=>'期初',
    //         'begin_time'=>'0',
    //         'end_time'=>$time,
    //         'status'=>'0',
    //     );
    //     if($mrObj->save($data)){
    //         //将期初数据存入KV，以便以后查询
    //         $ar_money = $this->get_ar_money('0',$time);
    //         $br_money = $this->get_br_money('0',$time);
    //         $total_money = ($ar_money - $br_money);
    //         $kv_data = array('monthly_id'=>
    //         $data['monthly_id'],'ar_money'=>$ar_money,'br_money'=>$br_money,'total_money'=>$total_money,'is_delete'=>'false');
    //         app::get('finance')->setConf('monthly_report_money',$kv_data);
    //         return 'true';
    //     }else{
    //         return 'false';
    //     }
    // }

    /*
    **获取本期应收金额
    **@params $time_from 开始时间,没有开始时间，请传'0'
    **@params $time_to 结束时间
    **@return 应收金额
    */
    // public function get_ar_money($time_from,$time_to){
    //     $arObj = app::get('finance')->model('ar');
    //     $rs = $arObj->getList('sum(money) as money',array('trade_time|than'=>$time_from,'trade_time|sthan'=>$time_to));
    //     if(empty($rs['money'])) return '0';
    //     return $rs[0]['money'];
    // }

    /*
    **获取本期实收金额
    **@params $time_from 开始时间,没有开始时间，请传'0'
    **@params $time_to 结束时间
    **@return 实收金额
    */
    // public function get_br_money($time_from,$time_to){
    //     $arObj = app::get('finance')->model('bill');
    //     $rs = $arObj->getList('sum(money) as money',array('trade_time|than'=>$time_from,'trade_time|sthan'=>$time_to));
    //     if(empty($rs['money'])) return 0;
    //     return $rs[0]['money'];
    // }

    /*
    **通过时间判断所在账期月结状态
    **@params $time 账单完成时间
    **@return 月结状态未启用(0) 未结算(1) 已结算(2)
    */
    public function get_monthly_report_status_by_time($time){
        $arObj = app::get('finance')->model('monthly_report');
        $rs = $arObj->getList('status',array('begin_time|sthan'=>$time,'end_time|than'=>$time));
        if(empty($rs)){
            return '1';
        }
        return $rs[0]['status'];
    }

    /*插入月末结账数据,从期初时间开始计算到当前时间范围内的月份*/
    function save_monthly_report($now_time){
        $mrObj = app::get('finance')->model('monthly_report');
        $last_row_data = $mrObj->db->select("select end_time from sdb_finance_monthly_report order by end_time DESC limit 0,1");
        $start_time = $last_row_data[0]['end_time'];
        #var_dump($start_time);
        if(!$start_time) return ;
        #echo date('Y-m-d H:i:s',$start_time);
        $months = self::dateMonths($start_time,time());
        for($i=0;$i<=$months;$i++){
            $start_month = self::get_next_month($start_time,$i);
            $next_month = self::get_next_month($start_time,$i+1);
            $sdf = array();
            $sdf['monthly_date'] = date('Y',$start_month)."年".date('m',$start_month)."月";
            $sdf['begin_time'] = $start_month;
            $sdf['end_time'] = $next_month;
            $sdf['status'] = 1;
            #echo date('Y-m-d H:i:s',$sdf['begin_time']).'--'.date('Y-m-d H:i:s',$sdf['end_time'])."<hr/>";
            $mrObj->save($sdf);
        }
    }

    /*计算两个时间间隔相册的月份数
    *@params $d1,$d2为时间戳
    */
    static function dateMonths($d1,$d2)
    {
        $m1 = date("m",$d1);
        $m2 = date("m",$d2);
        $y1 = date("Y",$d1);
        $y2 = date("Y",$d2);
        $months = ($y2-$y1)*12+($m2-$m1);
        return $months;
    }
    /*获取时间间隔月份明细时间戳
    *@params $time起始时间戳 $months偏移量
    */
    static function get_next_month($time,$months){
        return strtotime("+{$months} month", $time);
    }

    /*
    **获取初始化设置数组
    **@return array('flag'=>'true/false 字符串','time'=>'时间戳')
    */
    public function get_init_time(){
        $data = array();
        $kv_data = app::get('finance')->getConf('finance_setting_init_time');
        $data['flag'] = $kv_data['flag'] === 'true' ? $kv_data['flag'] : 'false';
        $time = strtotime($kv_data['year'].'-'.$kv_data['month'].'-'.$kv_data['day'].' 00:00:00');
        $data['time'] = $time;
        return $data;
    }

    /**
     * 根据时间获取账期ID
     *
     * @param      String  $shop_id  
     * @param      String $time     
     */
    public function getMonthlyReportIdByTime($shop_id,$time)
    {
        if(!isset($this->monthly_report[$shop_id]))
        {
            $mdlMonthlyReport = app::get('finance')->model('monthly_report');
            $this->monthly_report[$shop_id] = $mdlMonthlyReport->getList('monthly_id,begin_time,end_time',array('shop_id'=>$shop_id,'status|lthan'=>2));
        }

        $monthly_id = 0;

        foreach ($this->monthly_report[$shop_id] as $v) 
        {
            if($v['begin_time'] <= $time and $v['end_time'] >= $time )
            {
                return $v['monthly_id'];
            }
        }

        return $monthly_id;
    }

    public static function getMonthlyStatus($monthly_status=0)
    {
        $status = array(0=>'未关账',1=>'已关账');
        return isset($status[$monthly_status]) ? $status[$monthly_status] : '未关账';
    }

    public static function getAll($key='monthly_id')
    {
        $mdlMonthlyReport = app::get('finance')->model('monthly_report');
        $list = $mdlMonthlyReport->getList('monthly_id,begin_time,end_time,shop_id,status,monthly_date',array());
        return $list ? array_column($list,null,$key) : array();
    }

    public static function updateMonthlyAmount($filter = array())
    {
        !$filter and $filter['status'] = 1;
        $mdlMonthlyReport = app::get('finance')->model('monthly_report');
        $monthly_list = $mdlMonthlyReport->getList('monthly_id',$filter);
        if($monthly_list)
        {
            $monthly_list = array_column($monthly_list,'monthly_id');

            $monthly_amount = array();

            $bill_list = $mdlMonthlyReport->db->select('select sum(money) as amount,monthly_id,bill_type from sdb_finance_bill where monthly_id in ('.implode(',', $monthly_list).') and charge_status = 1 group by monthly_id,bill_type ');
            if($bill_list)
            {
                foreach ($bill_list as $v) {
                    if($v['bill_type'])
                    {
                        $monthly_amount[$v['monthly_id']]['bill_out_amount'] = $v['amount'];
                    }
                    else
                    {
                        $monthly_amount[$v['monthly_id']]['bill_in_amount'] = $v['amount'];
                    }
                }
            }

            $ar_list = $mdlMonthlyReport->db->select('select sum(money) as amount,sum(actually_money) as actually_amount,sum(platform_amount) as platform_amount,monthly_id,ar_type from sdb_finance_ar where monthly_id in ('.implode(',', $monthly_list).') and charge_status = 1 group by monthly_id,ar_type');
            if($ar_list)
            {
                foreach ($ar_list as $v) {
                    if($v['ar_type'])
                    {
                        $monthly_amount[$v['monthly_id']]['ar_out_amount'] = $v['amount'];
                        // 累计退款客户实付和退款平台承担金额（只计算流出）
                        $monthly_amount[$v['monthly_id']]['refund_actually_amount'] = $v['actually_amount'];
                        $monthly_amount[$v['monthly_id']]['refund_platform_amount'] = $v['platform_amount'];
                    }
                    else
                    {
                        $monthly_amount[$v['monthly_id']]['ar_in_amount'] = $v['amount'];
                        // 累计客户实付和平台承担金额（只计算流入）
                        $monthly_amount[$v['monthly_id']]['actually_amount'] = $v['actually_amount'];
                        $monthly_amount[$v['monthly_id']]['platform_amount'] = $v['platform_amount'];
                    }
                }
            }

            $monthly_count = array();
            $item_list = $mdlMonthlyReport->db->select('select count(*) as cnt,monthly_id,verification_status from sdb_finance_monthly_report_items where monthly_id in ('.implode(',', $monthly_list).') group by monthly_id,verification_status');
            if($item_list)
            {
                foreach ($item_list as $v) {
                    if($v['verification_status'] == 2)
                    {
                        $monthly_count[$v['monthly_id']]['verified_count'] = $v['cnt'];
                    }
                    else
                    {
                        $monthly_count[$v['monthly_id']]['unverified_count'] = $v['cnt'];
                    }
                }
            }

            foreach ($monthly_list as $monthly_id) {
                $data = array();

                $data['bill_in_amount'] = isset($monthly_amount[$monthly_id]['bill_in_amount']) ? $monthly_amount[$monthly_id]['bill_in_amount'] : 0;
                $data['bill_out_amount'] = isset($monthly_amount[$monthly_id]['bill_out_amount']) ? $monthly_amount[$monthly_id]['bill_out_amount'] : 0;
                $data['ar_in_amount'] = isset($monthly_amount[$monthly_id]['ar_in_amount']) ? $monthly_amount[$monthly_id]['ar_in_amount'] : 0;
                $data['ar_out_amount'] = isset($monthly_amount[$monthly_id]['ar_out_amount']) ? $monthly_amount[$monthly_id]['ar_out_amount'] : 0;
                $data['actually_amount'] = isset($monthly_amount[$monthly_id]['actually_amount']) ? $monthly_amount[$monthly_id]['actually_amount'] : 0;
                $data['platform_amount'] = isset($monthly_amount[$monthly_id]['platform_amount']) ? $monthly_amount[$monthly_id]['platform_amount'] : 0;
                $data['refund_actually_amount'] = isset($monthly_amount[$monthly_id]['refund_actually_amount']) ? $monthly_amount[$monthly_id]['refund_actually_amount'] : 0;
                $data['refund_platform_amount'] = isset($monthly_amount[$monthly_id]['refund_platform_amount']) ? $monthly_amount[$monthly_id]['refund_platform_amount'] : 0;
                $data['verified_count'] = isset($monthly_count[$monthly_id]['verified_count']) ? $monthly_count[$monthly_id]['verified_count'] : 0;
                $data['unverified_count'] = isset($monthly_count[$monthly_id]['unverified_count']) ? $monthly_count[$monthly_id]['unverified_count'] : 0;
                $mdlMonthlyReport->update($data,array('monthly_id'=>$monthly_id));
            }
        }
        return $monthly_list;
    }
}
