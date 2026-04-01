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
 * WMS 发货单
 *
 * @category
 * @package
 * @author chenping<chenping@shopex.cn>
 * @version $Id: Z
 */
class erpapi_wms_response_delivery extends erpapi_wms_response_abstract
{
    protected $unitConversion = 1000;
    /**
     * wms.delivery.status_update
     *
     **/
    public function status_update($params)
    {
        // 参数校验
        $this->__apilog['title']       = $this->__channelObj->wms['channel_name'] . '发货单[' . $params['delivery_bn'] . ']' . $params['status'];
        $this->__apilog['original_bn'] = $params['delivery_bn'];

        $batch_logi_no = preg_replace('/\s/', '', trim($params['logi_no']));
        $batch_logi_no = array_filter(explode(';', $batch_logi_no));
        $logi_no       = array_pop($batch_logi_no);

        if ($this->__channelObj->wms['adapter'] != 'selfwms' && $params['status'] == 'DELIVERY' && $params['logistics'] && !$logi_no) {
            $this->__apilog['result']['msg'] = '缺少运单号';
            return false;
        }

        $params['oid'] = trim($params['oid']);
        $this->_dealWMSParams($params);
        $data = array(
            'delivery_bn'          => trim($params['delivery_bn']),
            'logi_no'              => $logi_no,
            'logi_id'              => $params['logistics'],
            'weight'               => sprintf("%.3f", (float)$params['weight'] * $this->unitConversion),
            'branch_bn'            => $params['warehouse'],
            'volume'               => $params['volume'],
            'memo'                 => $params['remark'],
            'operate_time'         => $params['operate_time'] ? $params['operate_time'] : date('Y-m-d H:i:s'),
            'wms_id'               => $this->__channelObj->wms['channel_id'],
            'bill_logi_no'         => $batch_logi_no,
            'original_delivery_bn' => ($params['oid'] ? $params['oid'] : '0'), //子包裹号(外部发货单号)
            'node_type'            => $this->__channelObj->wms['node_type'],
            'operate_info'         => $params['operate_info'],
        );

        if ($data['logi_id']) {
            $erModel         = app::get('wmsmgr')->model('express_relation');
            $r               = $erModel->dump(array('wms_id' => $this->__channelObj->wms['channel_id'], 'wms_express_bn' => $data['logi_id']), 'sys_express_bn');
            $data['logi_id'] = $r['sys_express_bn'] ? $r['sys_express_bn'] : $data['logi_id'];
        }

        if ($params['other_list_0']) {
            $logiList   = @json_decode($params['other_list_0'], true);
            $logiWeight = array();
            foreach ((array)$logiList as $val) {
                if ($val['logi_no'] != $logi_no) {
                    $logiWeight[$val['logi_no']] = (float)$val['weight'] * $this->unitConversion;
                }
            }
            $data['bill_logi_weight'] = $logiWeight;
        }

        if ($params['out_delivery_bn']) {
            $data['out_delivery_bn'] = $params['out_delivery_bn'];
        }

        switch ($params['status']) {
            case 'CLOSE':
            case 'FAILED':
                $data['status'] = 'cancel';
                break;
            case 'ACCEPT':
                $data['status'] = 'accept';
                break;
            case 'PRINT':
                $data['status'] = 'print';
                break;
            case 'PICK':
                $data['status'] = 'pick';
                break;
            case 'CHECK':
                $data['status'] = 'check';
                break;
            case 'PACKAGE':
                $data['status'] = 'package';
                break;
            case 'DELIVERY':
                $data['status'] = 'delivery';
                break;
            case 'UPDATE':
                $data['status'] = 'update';
                break;
            default:
                $data['status'] = strtolower($params['status']);
                break;
        }

        $delivery_items = array();
        $items          = $params['item'] ? @json_decode($params['item'], true) : array();
        if ($items) {
            foreach ($items as $key => $val) {
                if (!$val['product_bn']) {
                    continue;
                }

                $delivery_items[$val['product_bn']]['bn']  = $val['product_bn'];
                $delivery_items[$val['product_bn']]['num'] = (int) $delivery_items[$val['product_bn']]['num'] + (int) $val['num'];
                if(is_array($val['sn_list']) && $val['sn_list']['sn']) {
                    $sn_list = is_array($val['sn_list']['sn']) ? $val['sn_list']['sn'] : [$val['sn_list']['sn']];
                    $delivery_items[$val['product_bn']]['sn_list'] = $delivery_items[$val['product_bn']]['sn_list'] ? array_merge($delivery_items[$val['product_bn']]['sn_list'], $sn_list) : $sn_list;
                }

                if ($val['batch']) {
                    foreach ($val['batch'] as $v) {
                        // 如果是多维数组
                        if (isset($v[0])) {
                            foreach ($v as $vv) {
                                if ($vv['actualQty'] == 0) {
                                    continue;
                                }

                                $delivery_items[$val['product_bn']]['batch'][] = array(
                                    'purchase_code'    => $vv['batchCode'],
                                    'produce_code'     => $vv['produceCode'],
                                    'product_time'     => strtotime($vv['productDate']),
                                    'expire_time'      => strtotime($vv['expireDate']),
                                    'normal_defective' => 'normal',
                                    'num'              => $vv['actualQty'],
                                );
                            }
                        } else {
                            if ($v['actualQty'] == 0) {
                                continue;
                            }

                            $delivery_items[$val['product_bn']]['batch'][] = array(
                                'purchase_code'    => $v['batchCode'],
                                'produce_code'     => $v['produceCode'],
                                'product_time'     => strtotime($v['productDate']),
                                'expire_time'      => strtotime($v['expireDate']),
                                'normal_defective' => 'normal',
                                'num'              => $v['actualQty'],
                            );
                        }
                    }
                }
            }
        }

//        foreach($delivery_items as $v) {
//            $bm = app::get('material')->model('basic_material')->db_dump(['material_bn'=>$v['bn']], 'serial_number');
//            if($bm['serial_number'] == 'true') {
//                if(empty($v['sn_list']) || count($v['sn_list']) < $v['num']) {
//                    $msg = $v['bn'].'开启了唯一码，本次数据未传输或数量不够';
//                    kernel::single('monitor_event_notify')->addNotify('wms_delivery_consign', [
//                        'delivery_bn' => $params['delivery_bn'],
//                        'errmsg'      => $msg,
//                    ]);
//                    $this->__apilog['result']['msg'] = $msg;
//                    return false;
//                }
//            }
//        }

        $data['items'] = $delivery_items;
        if($params['packages']) {
            $packages = json_decode($params['packages'], true);
            if($packages['package']) {
                $package = isset($packages['package'][0]) ? $packages['package'] : [$packages['package']];
                $data['package'] = $this->_dealDeliveryPackage($package);
            }
        }
        return $data;
    }

    protected function _dealWMSParams($params) {
        if((empty($params['delivery_bn']) && empty($params['out_delivery_bn'])) || $params['status'] != 'DELIVERY') {
            return [false, ['msg'=>'参数不符合规范']];
        }
        $nodeId = $this->__channelObj->channel['node_id'];
        $wdMdl = app::get('console')->model('wms_delivery');
        $wmsStatus = $params['status'];
        if($params['out_delivery_bn']) {
            if($row = $wdMdl->db_dump(['out_delivery_bn'=>$params['out_delivery_bn'], 'wms_node_id'=>$nodeId], 'id')) {
                if($row['wms_status'] != $wmsStatus) {
                    $wdMdl->update(['wms_status'=>$wmsStatus], ['id'=>$row['id']]);
                    app::get('ome')->model('operation_log')->write_log('wms_delivery@console',$row['id'], '更新成功：'.$wmsStatus);
                }
                return [true, ['msg'=>'已经存在']];
            }
        } else {
            if($row = $wdMdl->db_dump(['delivery_bn'=>$params['delivery_bn']], 'id, wms_status')) {
                if($row['wms_status'] != $wmsStatus) {
                    $wdMdl->update(['wms_status'=>$wmsStatus], ['id'=>$row['id']]);
                    app::get('ome')->model('operation_log')->write_log('wms_delivery@console',$row['id'], '更新成功：'.$wmsStatus);
                }
                return [true, ['msg'=>'已经存在']];
            }
        }
        $inData = [];
        $inData['delivery_bn'] = $params['delivery_bn'];
        $inData['out_delivery_bn'] = $params['out_delivery_bn'];
        $inData['wms_status'] = $wmsStatus;
        $inData['wms_node_id'] = $nodeId;
        $inData['logistics'] = $params['logistics'];
        $inData['logi_no'] = $params['logi_no'];
        $inData['weight'] = $params['weight'];
        $inData['remark'] = $params['remark'];
        $inData['volume'] = $params['volume'];
        $inData['extend_props'] = $params['extend_props'];
        $inData['operate_time'] = $params['operate_time'];
        $inData['warehouse'] = $params['warehouse'];
        $inData['oid'] = $params['oid'];
        $inData['other_list_0'] = $params['other_list_0'];
        $inData['packages'] = $params['packages'];
        $id = $wdMdl->insert($inData);
        if(!$id) {
            return [false, ['msg'=>'主表写入失败']];
        }
        $items = isset($params['item']) ? json_decode($params['item'],true) : array();
        if($items){
            $inItems = [];
            foreach($items as $key=>$val){
                $inItems[] = [
                    'wd_id' => $id,
                    'product_bn' => $val['product_bn'],
                    'num' => $val['num'],
                    'sn_list' => $val['sn_list'] ? json_encode($val['sn_list'], JSON_UNESCAPED_UNICODE) : '',
                    'batch' => $val['batch'] ? json_encode($val['batch'], JSON_UNESCAPED_UNICODE) : '',
                    'wms_item_id' => $val['item_id'],
                ];
            }
            $wriMdl = app::get('console')->model('wms_delivery_items');
            $sql = kernel::single('ome_func')->get_insert_sql($wriMdl, $inItems);
            $wriMdl->db->exec($sql);
        }
        app::get('ome')->model('operation_log')->write_log('wms_delivery@console',$id, '接收成功：'.$wmsStatus);
        return [true, ['msg'=>'处理完成']];
    }

    protected function _dealDeliveryPackage($package) {
        $rt = [];
        foreach($package as $pv) {
            if(!is_array($pv['items']) || !is_array($pv['items']['item'])) {
                continue;
            }
            $items = isset($pv['items']['item'][0]) ? $pv['items']['item'] : [$pv['items']['item']];
            foreach($items as $iv) {
                $bnBm = app::get('material')->model('basic_material')->db_dump(array('material_bn'=>$iv['itemCode']), 'bm_id');
                if(empty($bnBm)) {
                    continue;
                }
                $rt[] = [
                    'package_bn' => $pv['packageCode'],
                    'logi_bn' => $pv['logisticsCode'],
                    'logi_no' => $pv['expressCode'],
                    'product_id' => $bnBm['bm_id'],
                    'bn' => $iv['itemCode'],
                    'outer_sku' => $iv['itemId'],
                    'status' => 'delivery',
                    'number' => $iv['quantity']
                ];
            }
        }
        return $rt;
    }
}
