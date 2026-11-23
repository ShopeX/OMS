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
 * @author ykm 2015-12-03
 * @describe 特殊订单打印类
 */
class brush_print
{
    public $delivery;       //需要打印的发货单
    public $noLogiDelivery; //需要打印没有运单号的发货单
    public $logi_id;        //物流公司ID
    public $msg;
    public $showError;      //用于页面弹窗的错误信息
    public $hasPrint;       //已经打印过的发货单

    public function checkDelivery($filter)
    {
        $noNeed = 0;
        $objDelivery = app::get('brush')->model('delivery');
        $delivery = $objDelivery->getList('*', $filter, 0, -1);
        foreach($delivery as $k =>$dly){
            if($this->logi_id) {
                if($this->logi_id != $dly['logi_id']) {
                    $this->msg['error_msg'] = "当前系统不支持同时打印二种不同快递类型的单据，请重新选择后再试。";
                    return false;
                }
            } else {
                $this->logi_id = $dly['logi_id'];
            }
            
            if($dly['status'] == 'succ' && $dly['logi_id'] == 0) {
                $noNeed++;
                continue;
            }
            
            if($dly['expre_status'] == 'true') {
                $this->hasPrint[] = $dly['delivery_bn'];
            }

            $this->delivery[$dly['delivery_id']] = $dly;
            if(empty($dly['logi_no']) && !empty($dly['logi_id'])) {
                $this->noLogiDelivery[$dly['delivery_id']] = $dly;
            } else {
                //==
            }
        }
        if($noNeed) {
            $this->msg['warning_msg'][] = '所选单据有' . $noNeed . '张无需打印';
        }
    }

    public function setDelivery($delivery) {
        if(count($delivery)) {
            foreach($delivery as $k => $val) {
                if(is_array($val)) {
                    $this->delivery[$val['delivery_id']] = $val;
                } else {
                    $this->delivery[$delivery['delivery_id']] = $delivery;
                }
            }
        }
    }

    public function delDelivery($idBn, $msg) {
        if(count($idBn)) {
            foreach($idBn as $k => $val) {
                if($this->delivery[$k]) {
                    unset($this->delivery[$k]);
                }
                $this->showError['errIds'][] = $k;
                $this->showError['errBns'][$k] = $val;
                $this->showError['errInfo'][$k] = $msg;
            }
        }
    }

    public function noRepeatPrint(){
        $deliveryIds = array_keys($this->delivery);
        if(empty($deliveryIds)) {
            $this->msg['error_msg'] = '还没有获取到需要打印的发货单';
            return false;
        }
        $_inner_key = sprintf("brush_print_ids_%s", md5(implode(',',$deliveryIds)));
        $aData = cachecore::fetch($_inner_key);
        if ($aData === false) {
            cachecore::store($_inner_key, 'printed', 5);
            return true;
        }else{
            $this->msg['error_msg'] = "选中的发货单已在打印快递单中，请不要重复打印！！！如没有打印完成，请稍后重试！！！";
            return false;
        }
    }
}