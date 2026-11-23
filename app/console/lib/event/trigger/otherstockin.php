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
* 其它入库事件
*/
class console_event_trigger_otherstockin extends console_event_trigger_stockinabstract{

    /**
    * 其他出入库数据整理
    */
    function getStockInParam($param){
        $iostockObj = kernel::single('console_iostockdata');
        $iso_id = $param['iso_id'];
        $data = $iostockObj->get_iostockData($iso_id);
        $type_id = $data['type_id'];
        switch($type_id){
            case '4'://调拔入库
            case '40'://调拔出库
                
                $data['io_type'] = 'ALLCOATE';
                break;
            case '5'://残损出库
            case '50'://残损入
                $data['io_type'] = 'DEFECTIVE';
                break;
            case '7'://直接出入库
            case '70':
                $data['io_type'] = 'DIRECT';
                break;
            case '800':
            case '700':
                $data['io_type'] = 'DISTRIBUTION';//分销入库
                break;
            
            default:
            $data['io_type'] = 'OTHER';
            break;
        }

       return $data;
    }

    protected function update_out_bn($io_bn,$result)
    {
        $out_iso_bn = $result['data']['wms_order_code'];
        $oIso = app::get('taoguaniostockorder')->model('iso');
        $data = array(
            'out_iso_bn'=>(string)$out_iso_bn,
            'check_time' => time(),
        );
        
        if($result['rsp'] == 'fail') {
            $data['sync_status'] = '2';
            $data['sync_msg'] = $result['msg'];
        }else{
            if($out_iso_bn) {
                $data['sync_status'] = '3';
                $data['sync_msg'] = '';
            }
        }
        $oIso->update($data,array('iso_bn'=>$io_bn));
    }
}
?>