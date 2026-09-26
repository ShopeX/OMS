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


class finance_io_bill_verify{

    //验证订单号是否存在
    public static function isOrder($order_bn='',$msg='订单号不存在'){
        $result['status'] = 'success';
        $isExists = finance_io_bill_func::order_is_exists($order_bn);
        if($isExists == false){
            $result['msg'] = $msg;
            $result['status'] = 'fail';
        }else{
            $result['order'] = $isExists['order'];
        }
        return $result;
    }

    //金额验证（整数最多13位，小数最多3位）
    public static function isPrice($price='',$msg=''){
        $result['status'] = 'success';
        if ($price != '') {
            if (!preg_match('/^-?\d+(\.\d{1,3})?$/', $price)) {
                $result['msg']    = $msg;
                $result['status'] = 'fail';
            } else {
                $intPart = ltrim(strtok($price, '.'), '-');
                if (strlen($intPart) >= 14) {
                    $result['msg']    = $msg;
                    $result['status'] = 'fail';
                }
            }
        }
        return $result;
    }

    //日期格式验证
    public static function isDate($date='',$msg=''){
        $result['status'] = 'success';
        if($date && !preg_match('/^((\d{2,4})(-|\/)(\d{1,2})(-|\/)(\d{1,2})|(\d{4})(-|\/)(\d{1,2})(-|\/)(\d{1,2}) ((\d{1,2})(:|)(\d{1,2})|(\d{1,2})(:|)(\d{1,2})(:|)(\d{1,2})))$/',$date)){
            $result['msg'] = $msg;
            $result['status'] = 'fail';
        }
        return $result;
    }

    public static function checkInitTime($date='',$checkTime='',$msg=''){
        $result['status'] = 'success';
        $date = $date == '' ? time() : strtotime($date);

        #账期
        $initTime = app::get('finance')->getConf('finance_setting_init_time');
        $_initTime = strtotime($initTime['year'].'-'.$initTime['month'].'-'.$initTime['day']);

        switch($checkTime){
            case 'before':#before只允许导账期之前的数据
                if($date > $_initTime){
                    $result = array(
                        'status' => 'fail',
                        'msg' => '只可导入设置账期之前的数据',
                    );
                }
                break;
            case 'after':#after只允许导账期之后的数据
                if($date < $_initTime){
                    $result = array(
                        'status' => 'fail',
                        'msg' => '只可导入设置账期之后的数据',
                    );
                }
                break;
            default:
                $result = array(
                    'status' => 'fail',
                    'msg' => '账期类型错误',
                );
                break;
        }

        return $result;
    }

    public static function checkOrder($order_bn='',$msg='订单号不存在'){
        $result['status'] = 'success';
        static $hasCheckOrder;

        if ( isset($hasCheckOrder[$order_bn]) ) {
            return $hasCheckOrder[$order_bn];
        }

        $order = finance_io_bill_func::order_is_exists($order_bn);
        if($order == false){
            $result['msg'] = $msg;
            $result['status'] = 'fail';
        }else{
            $result['order'] = $order;
        }
    
        $hasCheckOrder[$order_bn] = $result;

        return $result;
    }

    //账期验证
    public static function isTaskCheckInitTime($date='',$task_id=''){
        $result['status'] = 'success';
        $date = $date == '' ? time() : strtotime($date);
        if($task_id){
            $public = finance_io_bill_func::get_public($task_id);
            $checkTime = $public['checkTime'];
            #账期
            $initTime = app::get('finance')->getConf('finance_setting_init_time');
            $_initTime = strtotime($initTime['year'].'-'.$initTime['month'].'-'.$initTime['day']);

            switch($checkTime){
                case 'before':#before只允许导账期之前的数据
                    if($date > $_initTime){
                        $result = array(
                            'status' => 'fail',
                            'msg' => '只可导入设置账期之前的数据',
                        );
                    }
                break;
                case 'after':#after只允许导账期之后的数据
                    if($date < $_initTime){
                        $result = array(
                            'status' => 'fail',
                            'msg' => '只可导入设置账期之后的数据',
                        );
                    }
                break;
            }
        }
        return $result;
    }

    public static function checkFee($fee_item='',$msg=''){
        $result['status'] = 'success';
        static $fee;

        if ( isset($fee[$fee_item]) ) {
            return $fee[$fee_item];
        }

        $feeExist = kernel::single('finance_bill')->get_fee_by_fee_item($fee_item);
        if(!$feeExist){
            $result['msg'] = $msg;
            $result['status'] = 'fail';
        } else {
            $result['fee'] = $feeExist;
        }
        
        $fee[$fee_item] = $result;
    
        return $result;
    }

    public static function checkEmpty($data='',$msg=''){
        $result = array('status'=>'success','msg'=>'');
        $required = array('order_bn'=>'业务单据号','fee_obj'=>'费用对象','unique_id'=>'唯一标识');
        foreach ($required as $key=>$value) {
            if (empty($data[$key])) {
                $result['status'] = 'fail';
                $result['msg'] = $value.'不能为空！';

                return $result;
            }
        }

        return $result;
    }

    public static function checkUniqueId($unique_id='',$msg=''){
        $result = array('status'=>'success','msg'=>'');
        
        $billObj = app::get('finance')->model('bill');
        $bill = $billObj->getlist('bill_id',array('unique_id'=>$unique_id),0,1);
        if(!empty($bill[0]['bill_id'])){
            $result = array('status'=> 'fail','msg'=>'该单据已存在','msg_code'=>'exists');
            return $result;
        }

        return $result;
    }


    /**
     * EXCEL  中读取到的时间原型是 浮点型，现在要转成 格式化的标准时间格式
     *        返回的时间是 UTC 时间（世界协调时间，加上8小时就是北京时间）
     * @param float|int $dateValue Excel浮点型数值
     * @param int $calendar_type 设备类型 默认Windows 1900.Windows  1904.MAC
     * @return int 时间戳
     */
    public static function getDateByFloatValue($dateValue = 0,$calendar_type = 1900){
        // Excel中的日期存储的是数值类型，计算的是从1900年1月1日到现在的数值
        if (1900 == $calendar_type) { // WINDOWS中EXCEL 日期是从1900年1月1日的基本日期
            $myBaseDate = 25569;// php是从 1970-01-01 25569是到1900-01-01所相差的天数
            if ($dateValue < 60) {
                --$myBaseDate;
            }
        } else {// MAC中EXCEL日期是从1904年1月1日的基本日期(25569-24107 = 4*365 + 2) 其中2天是润年的时间差？
            $myBaseDate = 24107;
        }

        // 执行转换
        if ($dateValue >= 1) {
            $utcDays = $dateValue - $myBaseDate;
            $returnValue = round($utcDays * 86400);
            if (($returnValue <= PHP_INT_MAX) && ($returnValue >= -PHP_INT_MAX)) {
                $returnValue = (integer)$returnValue;
            }
        } else {
            // 函数对浮点数进行四舍五入
            $hours = round($dateValue * 24);
            $mins = round($dateValue * 1440) - round($hours * 60);
            $secs = round($dateValue * 86400) - round($hours * 3600) - round($mins * 60);
            $returnValue = (integer)gmmktime($hours, $mins, $secs);
        }

        return $returnValue;// 返回时间戳
    }


}
?>