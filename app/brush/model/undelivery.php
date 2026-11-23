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
 * @author ykm 2015-12-29
 * @describe 特殊订单未发货导出
 */

class brush_mdl_undelivery extends brush_mdl_delivery {
    public $has_export_cnf = false;

    public function table_name($real=false)
    {
        $table_name = 'delivery';
        if($real){
            return DB_PREFIX.'brush_'.$table_name;
        }else{
            return $table_name;
        }
    }

    public function exportName(&$data)
    {
        $data['name'] = '特殊订单快速发货'.date('Ymd');
    }

    public function io_title( $filter=null,$ioType='csv'){
        switch( $ioType ){
            case 'csv':
            default:
                $this->oSchema['csv']['main'] = array(
                    '*:发货单号' => 'delivery_bn',
                    '*:物流单号' => 'logi_no',
                    '*:物流公司' => 'logi_name',
                );
        }
        $this->ioTitle[$ioType][$filter] = array_keys( $this->oSchema[$ioType]['main'] );
        return $this->ioTitle[$ioType][$filter];
    }

    public function fgetlist_csv(&$data,$filter,$offset,$exportType = 1)
    {
        $this->export_flag = true;
        $title = array();
        if ( !$data['title'] ) {
            foreach( $this->io_title() as $k => $v ){
                $title[] = $v;
            }
            $data['title'] = '"'.implode('","',$title).'"';
            return true;
        }
        $list = $this->getList('delivery_bn,logi_id,logi_no',$filter,0,1000);
        $corp = app::get('ome')->model('dly_corp')->getList('corp_id,name', array('disabled'=>'false'));
        $corpIdName = array();
        foreach($corp as $val) {
            $corpIdName[$val['corp_id']] = $val['name'];
        }
        if ($list) {
            foreach ($list as $v) {
                $contents = array(
                    'delivery_bn' => $v['delivery_bn']."\t",
                    'logi_no'     => $v['logi_no']."\t",
                    'logi_name' => $corpIdName[$v['logi_id']],
                );
                $data['contents'][] = implode(',', $contents);
            }
        }
        return false;
    }
    
    public function prepared_import_csv(){
        $this->ioObj->cacheTime = time();
    }

    public function prepared_import_csv_row($row,&$title,&$tmpl,&$mark,&$newObjFlag,&$msg)
    {
        if(empty($row)) return false;
        if( substr($row[0],0,1) == '*' ){
            $this->nums = 1;
            $title = array_flip($row);
            return false;
        }
        if (empty($title)) {
            $msg['error'] = "请使用正确的模板格式！";
            return false;
        }
        
        static $corp = array();
        if(empty($corp)) {
            $corpRows = app::get('ome')->model('dly_corp')->getList('corp_id,name', array('disabled'=>'false'));
            foreach($corpRows as $corpVal) {
                $corp[$corpVal['name']] = $corpVal['corp_id'];
            }
        }
        
        $delivery_bn = trim($row[$title['*:发货单号']]);
        $logi_no = trim($row[$title['*:物流单号']]);
        $logi_name = trim($row[$title['*:物流公司']]);
        if(isset($this->nums)){
            $this->nums++;
            if($this->nums > 5000){
                $msg['error'] = "导入的数据量过大，请减少到5000条以下！";
                return false;
            }
        }
        
        if (empty($logi_no)) {
            $msg['warning'][] = 'Line '.$this->nums.'：运单号不能都为空！';
            return false;
        }
        
        if (empty($delivery_bn)) {
            $msg['warning'][] = 'Line '.$this->nums.'：发货单号不能都为空！';
            return false;
        }
        
        //判断发货单是否存在
        $deliModel = $this->app->model('delivery');
        $delivery = $deliModel->dump(array('delivery_bn'=>$delivery_bn),'delivery_id,status,logi_id,logi_no,expre_status');
        if (!$delivery) {
            $msg['warning'][] = 'Line '.$this->nums.'：发货单号不存在！';
            return false;
        }
        
        //验证运单号是否被使用过
        $logi_no_exist = $deliModel->getList('delivery_id',array('logi_no'=>$logi_no,'delivery_bn|noequal'=>$delivery_bn),0,1);
        if ($logi_no_exist) {
            $msg['warning'][] = 'Line '.$this->nums.'：运单号已被使用！';
            return false;
        }
        
        if ($delivery['status'] == 'succ') {
            $msg['warning'][] = 'Line '.$this->nums.'：发货单号【'.$delivery_bn.'】已发货！';
            return false;
        }
        
        $corp_info = array();
        if(!empty($logi_name)){
            if(empty($corp[$logi_name])) {
                $msg['warning'][] = 'Line '.$this->nums.'：物流公司不存在或停用！';
                return false;
            }
            
            if($corp[$logi_name] != $delivery['logi_id']) {
                $corp_info['corp_id'] = $corp[$logi_name];
            }
        }
        
        $opInfo = kernel::single('ome_func')->getDesktopUser();
        $order = app::get('brush')->model('delivery_order')->dump(array('delivery_id'=>$delivery['delivery_id']));
        
        $sdf = array(
            'delivery_id' => $delivery['delivery_id'],
            'logi_no' => $logi_no,
            'logi_id'=>$corp_info['corp_id'],
            'delivery_logi_id' => $delivery['logi_id'],
            'opInfo' => $opInfo,
            'order_id' => $order['order_id'],
            'user_data' => kernel::single('desktop_user')->user_data,
            'expre_status' => $delivery['expre_status'],
        );
        $this->import_data[] = $sdf;
        
        return true;
    }

    function prepared_import_csv_obj($data,$mark,$tmpl,&$msg = ''){
        return null;
    }

    public function finish_import_csv()
    {
        $oQueue = app::get('base')->model('queue');

        $queueData = array(
            'queue_title'=>'特殊订单外部运单号导入',
            'start_time'=>time(),
            'params'=>array(
                'sdfdata'=>$this->import_data,
            ),
            'worker'=>'brush_mdl_undelivery.import_run',
        );
        $oQueue->save($queueData);

        $oQueue->flush();
    }

    public function import_run($cursor_id,$params,$errormsg)
    {
        $now = time();
        
        $opObj = app::get('ome')->model('operation_log');
        $deliModel = $this->app->model('delivery');
        
        $transaction = $this->db->beginTransaction();
        
        foreach ($params['sdfdata'] as $key=>$value)
        {
            $params = array();
            $params['logi_no'] = $value['logi_no'];
            
            $this->db->exec('SAVEPOINT deliveryImport');
            
            $opInfo = $value['opInfo'];
            unset($value['opInfo']);
            
            kernel::single('desktop_user')->user_data = $value['user_data']; unset($value['user_data']);
            kernel::single('desktop_user')->user_id = $opInfo['op_id'];
            if($value['logi_id']){
                $params['logi_id'] = $value['logi_id'];
                $opObj->write_log('delivery_brush_modify@brush', $value['delivery_id'], '特殊订单发货，导入更改快递公司',$now,$opInfo);
            }else{
                $params['logi_id'] = $value['delivery_logi_id'];
                unset($value['logi_id']);
            }
            unset($value['delivery_logi_id']);
            
            if ($value['expre_status'] != 'true') {
                $opObj->write_log('delivery_brush_expre@brush', $value['delivery_id'], '快递单打印(导入模拟)',$now,$opInfo);
            }
            
            $value['expre_status'] = 'true';
            $value['status'] = 'progress';
            $result = $deliModel->update($value,array('delivery_id'=>$value['delivery_id'], 'status'=>array('progress','ready')));
            if ($result) {
                $brushDelivery = kernel::single('brush_delivery');
                
                $brushDelivery->log_msg = '导入发货成功';
                $result = $brushDelivery->finishDeliver($value['delivery_id'], $value['order_id'], $params);
                if (!$result) {
                    $this->db->exec('ROLLBACK TO SAVEPOINT deliveryImport');
                    continue;
                }
            } else {
                $this->db->exec('ROLLBACK TO SAVEPOINT deliveryImport');
            }
        }
        
        $this->db->commit($transaction);

        return false;
    }
}