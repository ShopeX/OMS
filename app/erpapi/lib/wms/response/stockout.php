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
 * 出库单
 *
 * @category 
 * @package 
 * @author chenping<chenping@shopex.cn>
 * @version $Id: Z
 */
class erpapi_wms_response_stockout extends erpapi_wms_response_abstract
{    
    protected $itemNumIndex = 'num';
    protected $weightChangeUnite = 1000;

   
    /**
     * wms.stockout.status_update
     *
     **/
    public function status_update($params){
        $this->__apilog['title']       = $this->__channelObj->wms['channel_name'].'出库单'.$params['stockout_bn'];
        $this->__apilog['original_bn'] = $params['stockout_bn'];
        $this->_dealWMSParams($params);
        $data = array(
           'io_bn'           => $params['stockout_bn'],  
           'branch_bn'       => $params['warehouse'],
           'io_status'       => $params['status'] ? $params['status'] : $params['io_status'],
           'memo'            => $params['remark'],
           'operate_time'    => $params['operate_time'] ? $params['operate_time'] : date('Y-m-d H:i:s'),
           'logi_no'         => $params['logi_no'],
           'out_delivery_bn' => $params['out_delivery_bn'],
           'logi_id'         => $params['logistics'],
           'wms_id'         => $this->__channelObj->wms['channel_id'],
        );

        switch(substr($data['io_bn'], 0, 1)){
          case 'H': $data['io_type'] = 'PURCHASE_RETURN'; break;
          case 'R': $data['io_type'] = 'ALLCOATE';break;
          case 'B': $data['io_type'] = 'DEFECTIVE';break;
          case 'U':
          default:
            $data['io_type'] = 'OTHER';break;
          
        }

        if ($params['type']){
            switch($params['type']){
                case 'CGTH': $data['io_type'] = 'PURCHASE_RETURN';break;
                case 'JITCK': $data['io_type'] = 'VOPSTOCKOUT';break;
               // default : $data['io_type']    = 'OTHER';break;
            }
        }

        // 出库包裹明细
        if($params['packages'] && $params['packages'] = @json_decode($params['packages'],true)){

            // 如果只有一个PACKAGE
            if (!is_array(current($params['packages']['package']))) $params['packages']['package'] = array($params['packages']['package']);

            $packages = array();

            foreach($params['packages']['package'] as $package){
                // 如果主单无运单号，取包裹上的
                if(!$data['logi_no']) $data['logi_no'] = $package['expressCode'];
                if(!$data['logi_id']) $data['logi_id'] = $package['logisticsCode'];
                $data['weight'] += floatval($package['weight']) * $this->weightChangeUnite;
                // 箱号中，只有一个明细
                if ($package['items']['item']['itemCode']) $package['items']['item'] = array($package['items']['item']);

                #箱号中，有N个明细
                foreach($package['items']['item'] as $value){
                    $packages[] = array(
                        'bn'               => $value['itemCode'],       // 货号
                        'entry_normal_num' => $value['quantity'],       // 数量 
                        'package_code'     => $package['packageCode'], // 箱号
                    );
                }
            }

            $data['packages'] = $packages;
        }


        $stockout_items = array();
        $items = isset($params['item']) ? json_decode($params['item'], true) : array();
        if($items){
          foreach($items as $key=>$val)
          {
              //过滤空格和全角空格
              $val['product_bn'] = str_replace(array("\r\n", "\r", "\n", ' ', '　', "\t"), '',  $val['product_bn']);
              
              if (!$val['product_bn'])  continue;
              
              $stockout_items[$val['product_bn']]['bn'] =  $val['product_bn'];
              $stockout_items[$val['product_bn']]['num'] =  (int)$stockout_items[$val['product_bn']]['num'] + (int)$val['num'];

              if(is_array($val['batch']) && $val['batch']['batch']) {
                  $val['batch'] = isset($val['batch']['batch'][0]) ? $val['batch']['batch'] : [$val['batch']['batch']];
                  foreach ($val['batch'] as $v) {
                      if ($v['actualQty'] == 0) continue;
                      
                      // 过滤掉batchCode批次号为空的记录
                      //@todo：birkenStock客户反馈仓库那边有些商品不做批次管理，所以batchCode批次号为空；
                      if(empty($v['batchCode'])){
                          continue;
                      }
                      
                      $stockout_items[$val['product_bn']]['batch'][] = array(
                          'purchase_code' => $v['batchCode'],
                          'produce_code'  => $v['produceCode'],
                          'product_time'  => strtotime($v['productDate']),
                          'expire_time'   => strtotime($v['expireDate']),
                          'normal_defective' => 'normal',
                          'num'        => $v['actualQty'],
                      );
                  }
              }
          }
        }

        $data['items'] = $stockout_items;
        return $data;
    }

    protected function _dealWMSParams($params) {
        if((empty($params['stockout_bn']) && empty($params['delivery_order_id'])) || !in_array($params['status'], ['FINISH', 'PARTIN'])) {
            return [false, ['msg'=>'参数不符合规范']];
        }
        $nodeId = $this->__channelObj->channel['node_id'];
        $wsoMdl = app::get('console')->model('wms_stockout');
        $wmsStatus = $params['status'] ? $params['status'] : $params['io_status'];
        if($params['delivery_order_id']) {
            if($row = $wsoMdl->db_dump(['delivery_order_id'=>$params['delivery_order_id'], 'wms_node_id'=>$nodeId], 'id,wms_status')) {
                if($row['wms_status'] == 'PARTIN') {
                    $this->_dealWMSItems($params, $row['id']);
                    $wsoMdl->update(['wms_status'=>$wmsStatus], ['id'=>$row['id']]);
                    app::get('ome')->model('operation_log')->write_log('wms_stockout@console',$row['id'], '更新成功：'.$wmsStatus);
                }
                return [true, ['msg'=>'已经存在']];
            }
        } else {
            if($row = $wsoMdl->db_dump(['stockout_bn'=>$params['stockout_bn']], 'id, wms_status')) {
                if($row['wms_status'] == 'PARTIN') {
                    $this->_dealWMSItems($params, $row['id']);
                    $wsoMdl->update(['wms_status'=>$wmsStatus], ['id'=>$row['id']]);
                    app::get('ome')->model('operation_log')->write_log('wms_stockout@console',$row['id'], '更新成功：'.$wmsStatus);
                }
                return [true, ['msg'=>'已经存在']];
            }
        }
        $inData = [];
        $inData['stockout_bn'] = $params['stockout_bn'];
        $inData['delivery_order_id'] = $params['delivery_order_id'];
        $inData['wms_status'] = $wmsStatus;
        $inData['wms_node_id'] = $nodeId;
        $inData['type'] = $params['type'];
        $inData['logi_no'] = $params['logi_no'];
        $inData['extend_props'] = $params['extendProps'];
        $inData['packages'] = $params['packages'];
        $inData['operate_time'] = $params['operate_time'];
        $inData['warehouse'] = $params['warehouse'];
        $id = $wsoMdl->insert($inData);
        if(!$id) {
            return [false, ['msg'=>'主表写入失败']];
        }
        $this->_dealWMSItems($params, $id);
        app::get('ome')->model('operation_log')->write_log('wms_stockout@console',$id, '接收成功：'.$wmsStatus);
        return [true, ['msg'=>'处理完成']];
    }

    protected function _dealWMSItems($params, $id) {
        $items = isset($params['item']) ? json_decode($params['item'],true) : array();
        if($items){
            $inItems = [];
            foreach($items as $key=>$val){
                $inItems[] = [
                    'wso_id' => $id,
                    'product_bn' => $val['product_bn'],
                    'normal_num' => $val['num'] ?? $val['normal_num'],
                    'defective_num' => $val['defective_num'],
                    'sn_list' => $val['sn_list'] ? json_encode($val['sn_list'], JSON_UNESCAPED_UNICODE) : '',
                    'batch' => $val['batch'] ? json_encode($val['batch'], JSON_UNESCAPED_UNICODE) : '',
                    'wms_item_id' => $val['item_id'],
                ];
            }
            $wriMdl = app::get('console')->model('wms_stockout_items');
            $sql = kernel::single('ome_func')->get_insert_sql($wriMdl, $inItems);
            $wriMdl->db->exec($sql);
        }
    }
}
