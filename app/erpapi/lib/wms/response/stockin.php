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
 * 入库单
 *
 * @category 
 * @package 
 * @author chenping<chenping@shopex.cn>
 * @version $Id: Z
 */
class erpapi_wms_response_stockin extends erpapi_wms_response_abstract
{
    /**
     * wms.stockin.status_update
     *
     **/
    public function status_update($params){
        $this->__apilog['title']       = $this->__channelObj->wms['channel_name'].'入库单'.$params['stockin_bn'];
        $this->__apilog['original_bn'] = $params['stockin_bn'];
        $this->_dealWMSParams($params);
        // 如果是MS打头代表退货入库
        $data = array(
          'io_bn'        => $params['stockin_bn'],
          'branch_bn'    => $params['warehouse'],
          'io_status'    => $params['status'] ? $params['status'] : $params['io_status'],
          'memo'         => $params['remark'],
          'operate_time' => $params['operate_time'] ? $params['operate_time'] : date('Y-m-d H:i:s'),
          'wms_id'       => $this->__channelObj->wms['channel_id'],
        );
        //博世官旗生产系统节点:1483180733,测试环境:1236120430
        $arrival_no = '';
        if (base_shopnode::node_id('ome') == 1483180733) {
            $arrival_no = $params['remark'];
        }
        $data['arrival_no'] = $arrival_no;
        switch(substr($data['io_bn'], 0, 1)){
          case 'I': $data['io_type'] = 'PURCHASE';break; // 采购入库
          case 'T': $data['io_type'] = 'ALLCOATE';break; // 调拨入库
          case 'D': $data['io_type'] = 'DEFECTIVE';break; // 残损入库
          case 'O': 
          default:
            $data['io_type'] = 'OTHER';break;
        }

        // 如果定义了类型
        if ($params['type']){
            switch($params['type']){
                case 'CGRK':
                        $data['io_type'] = 'PURCHASE';
                        //SAP采购入库逻辑特殊处理
                        $oPo = app::get('purchase')->model("po")->db_dump(['po_bn' => $data['io_bn']], 'po_id,po_bn,po_status,branch_id');
                        if (!$oPo) {
                            $isoAsn = app::get('taoguaniostockorder')->model("iso")->db_dump(['iso_bn' => $data['io_bn'], 'bill_type' => 'asn'], 'iso_bn,bill_type');
                            if ($isoAsn) {
                                $data['io_type'] = 'OTHER';
                            }
                        }
                        break;  // 采购入库
                default : $data['io_type']    = 'OTHER';break;     // 其他入库
            }
        }
    
        if (substr($data['io_bn'], 0, 2) == "DC" && app::get('warehouse')->is_installed()){ //转仓入库
            $data['io_type'] = 'WAREHOUSE';
        }
        
        $stockin_items = array();
        $items = isset($params['item']) ? json_decode($params['item'],true) : array();

        if($items){
          foreach($items as $key=>$val){
            if (!$val['product_bn'])  continue;
            
            $stockin_items[$val['product_bn']]['bn']            = $val['product_bn'];
            $stockin_items[$val['product_bn']]['normal_num']    = (int) $stockin_items[$val['product_bn']]['normal_num'] + (int) $val['normal_num'];
            $stockin_items[$val['product_bn']]['defective_num'] = (int) $stockin_items[$val['product_bn']]['defective_num'] + (int) $val['defective_num'];
            $stockin_items[$val['product_bn']]['details'][]     = [
                'bn'            => $val['product_bn'], 
                'normal_num'    => $val['normal_num'], 
                'defective_num' => $val['defective_num'],
                'extendProps'   => $val['extendProps'] && is_array($val['extendProps']) ? $val['extendProps'] : [],
                'orderLineNo'   => $val['orderLineNo'],
            ];
            
            if($val['batch']) {
                $v = $val['batch'];
                // 如果是多维数组
                if (isset($v[0])) {
                    foreach ($v as $vv) {
                        if ($vv['actualQty'] == 0) continue;

                        $stockin_items[$val['product_bn']]['batch'][] = array(
                            'purchase_code' => $vv['batchCode'],
                            'produce_code'  => $vv['produceCode'],
                            'product_time'  => strtotime($vv['productDate']) ? strtotime($vv['productDate']) : 0,
                            'expire_time'   => strtotime($vv['expireDate']) ? strtotime($vv['expireDate']) : 0,
                            'normal_defective' => ($vv['inventoryType'] == 'CC' ? 'defective' : 'normal'),
                            'num'        => $vv['actualQty'],
                        );
                    }                         
                } else {
                    if ($v['actualQty'] == 0) continue;

                    $stockin_items[$val['product_bn']]['batch'][] = array(
                        'purchase_code' => $v['batchCode'],
                        'produce_code'  => $v['produceCode'],
                        'product_time'  => strtotime($v['productDate']) ? strtotime($v['productDate']) : 0,
                        'expire_time'   => strtotime($v['expireDate']) ? strtotime($v['expireDate']) : 0,
                        'normal_defective' => ($v['inventoryType'] == 'CC' ? 'defective' : 'normal'),
                        'num'        => $v['actualQty'],
                    );
                }
            }
          }
        }

        $data['items'] = $stockin_items;
        // $data['shippackages'] = $items;
        return $data;
    }

    protected function _dealWMSParams($params) {
        if((empty($params['stockin_bn']) && empty($params['entry_order_id'])) || !in_array($params['status'], ['FINISH', 'PARTIN'])) {
            return [false, ['msg'=>'参数不符合规范']];
        }
        $nodeId = $this->__channelObj->channel['node_id'];
        $wsiMdl = app::get('console')->model('wms_stockin');
        $wmsStatus = $params['status'] ? $params['status'] : $params['io_status'];
        if($params['entry_order_id']) {
            if($row = $wsiMdl->db_dump(['entry_order_id'=>$params['entry_order_id'], 'wms_node_id'=>$nodeId], 'id,wms_status')) {
                if($row['wms_status'] == 'PARTIN') {
                    $this->_dealWMSItems($params, $row['id']);
                    $wsiMdl->update(['wms_status'=>$wmsStatus], ['id'=>$row['id']]);
                    app::get('ome')->model('operation_log')->write_log('wms_stockin@console',$row['id'], '更新成功：'.$wmsStatus);
                }
                return [true, ['msg'=>'已经存在']];
            }
        } else {
            if($row = $wsiMdl->db_dump(['stockin_bn'=>$params['stockin_bn']], 'id, wms_status')) {
                if($row['wms_status'] == 'PARTIN') {
                    $this->_dealWMSItems($params, $row['id']);
                    $wsiMdl->update(['wms_status'=>$wmsStatus], ['id'=>$row['id']]);
                    app::get('ome')->model('operation_log')->write_log('wms_stockin@console',$row['id'], '更新成功：'.$wmsStatus);
                }
                return [true, ['msg'=>'已经存在']];
            }
        }
        $inData = [];
        $inData['stockin_bn'] = $params['stockin_bn'];
        $inData['entry_order_id'] = $params['entry_order_id'];
        $inData['wms_status'] = $wmsStatus;
        $inData['wms_node_id'] = $nodeId;
        $inData['type'] = $params['type'];
        $inData['remark'] = $params['remark'];
        $inData['extend_props'] = $params['extendProps'];
        $inData['operate_time'] = $params['operate_time'];
        $inData['warehouse'] = $params['warehouse'];
        $id = $wsiMdl->insert($inData);
        if(!$id) {
            return [false, ['msg'=>'主表写入失败']];
        }
        $this->_dealWMSItems($params, $id);
        app::get('ome')->model('operation_log')->write_log('wms_stockin@console',$id, '接收成功：'.$wmsStatus);
        return [true, ['msg'=>'处理完成']];
    }

    protected function _dealWMSItems($params, $id) {
        $items = isset($params['item']) ? json_decode($params['item'],true) : array();
        if($items){
            $inItems = [];
            foreach($items as $key=>$val){
                $inItems[] = [
                    'wsi_id' => $id,
                    'tid' => $val['tid'],
                    'oid' => $val['oid'],
                    'product_bn' => $val['product_bn'],
                    'normal_num' => $val['normal_num'],
                    'defective_num' => $val['defective_num'],
                    'sn_list' => $val['sn_list'] ? json_encode($val['sn_list'], JSON_UNESCAPED_UNICODE) : '',
                    'batch' => $val['batch'] ? json_encode($val['batch'], JSON_UNESCAPED_UNICODE) : '',
                    'wms_item_id' => $val['item_id'],
                    'inventoryType' => $val['inventoryType'] ? $val['inventoryType'] : '',
                ];
            }
            $wriMdl = app::get('console')->model('wms_stockin_items');
            $sql = kernel::single('ome_func')->get_insert_sql($wriMdl, $inItems);
            $wriMdl->db->exec($sql);
        }
    }
}
