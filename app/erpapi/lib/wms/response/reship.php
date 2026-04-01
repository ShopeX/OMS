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
 * 退货单
 *
 * @category 
 * @package 
 * @author chenping<chenping@shopex.cn>
 * @version $Id: Z
 */
class erpapi_wms_response_reship extends erpapi_wms_response_abstract
{    
    /**
     * wms.reship.status_update
     *
     **/
    public function status_update($params){

        $this->__apilog['title'] = $this->__channelObj->wms['channel_name'] . '退货单' . $params['reship_bn'];
        $this->__apilog['original_bn'] = $params['reship_bn'];
        $params['reship_bn'] = trim($params['reship_bn']);
        $this->_dealWMSParams($params);
        $extendProps = $params['extendProps'] ? is_string($params['extendProps']) ? json_decode($params['extendProps'], true) : $params['extendProps'] : [];

        if($extendProps['is_sxts'] == '1'){

            return $this->lanjieReship($params);

        }
        $data = array(
          'reship_bn'    => $params['reship_bn'],
          'return_order_id' => $params['return_order_id'],
          'order_type'   => $params['order_type'],
          'logi_code'    => $params['logistics'],
          'logi_no'      => $params['logi_no'],
          'branch_bn'    => $params['warehouse'],
          'memo'         => $params['remark'],
          'operate_time' => $params['operate_time'] ? $params['operate_time'] : date('Y-m-d H:i:s'),
          'node_id'      => $this->__channelObj->wms['node_id'],
          'wms_id'       => $this->__channelObj->wms['channel_id'],
          'extend_props' => $extendProps,
        );
        $params['status'] = $params['status'] ? $params['status'] : $params['io_status'];
        switch($params['status']){
          case 'FINISH': $data['status']='FINISH';break;
          case 'PARTIN': $data['status']='PARTIN';break;
          case 'CLOSE':
          case 'FAILED':
          case 'DENY':
            $data['status'] = 'CLOSE'; break;
          default:
            $data['status'] = $params['status'];break;
        }

        $reship_items = array();
        $items = isset($params['item']) ? json_decode($params['item'],true) : array();

        if($items){
            foreach($items as $key=>$val){
                if (!$val['product_bn']) continue;
                $key = trim($val['product_bn']);
                if(app::get('ome')->getConf('wms.goods.type.branch') == 1) {
                    if(empty($val['inventoryType'])) {
                        $this->__apilog['result']['msg'] = '缺少商品库位';
                        return false;
                    }
                    $arrTypeBranch = [];
                    if(!$arrTypeBranch[$val['inventoryType']]) {
                        $arrTypeBranch[$val['inventoryType']] = kernel::single('ome_branch_type')->getBranchByTypeWms($val['inventoryType'], $this->__channelObj->wms['channel_id'], $params['warehouse']);
                    }
                    if(!$arrTypeBranch[$val['inventoryType']]) {
                        $this->__apilog['result']['msg'] = '商品库位对应的仓库不存在';
                        return false;
                    }
                    $key = $key . '-|-' . $arrTypeBranch[$val['inventoryType']]['branch_id'];
                    $reship_items[$key]['branch_id'] = $arrTypeBranch[$val['inventoryType']]['branch_id'];
                    $val['normal_num'] = (int)$val['normal_num'] + (int)$val['defective_num'];
                    $val['defective_num'] = 0;
                }
                $reship_items[$key]['bn']            = $val['product_bn'];
                $reship_items[$key]['normal_num']    = (int)$reship_items[$key]['normal_num'] + (int)$val['normal_num'];
                $reship_items[$key]['defective_num'] = (int)$reship_items[$key]['defective_num'] + (int)$val['defective_num'];
              
                if(is_array($val['sn_list']) && $val['sn_list']['sn']) {
                    $sn_list = is_array($val['sn_list']['sn']) ? $val['sn_list']['sn'] : [$val['sn_list']['sn']];
                    $reship_items[$key]['sn_list'] = $reship_items[$key]['sn_list'] ? array_merge($reship_items[$key]['sn_list'], $sn_list) : $sn_list;
                }
                if($val['batch']) {
                    $v = $val['batch'];
                    // 如果是多维数组
                    if (isset($v[0])) {
                        foreach ($v as $vv) {
                            if ($vv['actualQty'] == 0) continue;

                            $reship_items[$key]['batch'][] = array(
                                'purchase_code' => $vv['batchCode'],
                                'produce_code'  => $vv['produceCode'],
                                'product_time'  => strtotime($vv['productDate']),
                                'expire_time'   => strtotime($vv['expireDate']),
                                'normal_defective' => ($vv['inventoryType'] == 'CC' ? 'defective' : 'normal'),
                                'num'        => $vv['actualQty'],
                            );
                        }                         
                    } else {
                        if ($v['actualQty'] == 0) continue;

                        $reship_items[$key]['batch'][] = array(
                            'purchase_code' => $v['batchCode'],
                            'produce_code'  => $v['produceCode'],
                            'product_time'  => strtotime($v['productDate']),
                            'expire_time'   => strtotime($v['expireDate']),
                            'normal_defective' => ($v['inventoryType'] == 'CC' ? 'defective' : 'normal'),
                            'num'        => $v['actualQty'],
                        );
                    }
                }
            }
        }
        $virtualItems = $this->_getVirtualItems($data['reship_bn']);
        if(!empty($virtualItems)) {
            $reship_items = array_merge($reship_items, $virtualItems);
        }
        $data['items'] = $reship_items;
        return $data;
    }

    protected function _getVirtualItems($reship_bn) {
        // 获取退货单信息
        $reshipMdl = app::get('ome')->model('reship');
        $reshipInfo = $reshipMdl->db_dump(array('reship_bn' => $reship_bn), 'reship_id');
        
        if (empty($reshipInfo)) {
            return array();
        }
        
        // 获取退货明细
        $reshipItemMdl = app::get('ome')->model('reship_items');
        $reshipItems = $reshipItemMdl->getList('bn, num', array(
            'reship_id' => $reshipInfo['reship_id'],
            'is_del' => 'false'
        ));
        
        if (empty($reshipItems)) {
            return array();
        }
        
        // 提取所有bn
        $bns = array_column($reshipItems, 'bn');
        if (empty($bns)) {
            return array();
        }
        
        // 查询虚拟类型的基础物料
        $basicMaterialMdl = app::get('material')->model('basic_material');
        $virtualMaterials = $basicMaterialMdl->getList('material_bn', array(
            'material_bn' => $bns,
            'type' => 5,  // 虚拟类型
            'disabled' => 'false'
        ));
        
        if (empty($virtualMaterials)) {
            return array();
        }
        
        // 获取虚拟物料的bn列表
        $virtualBns = array_column($virtualMaterials, 'material_bn');
        
        // 组装结果数据
        $result = array();
        foreach ($reshipItems as $item) {
            if (in_array($item['bn'], $virtualBns)) {
                $result[] = array(
                    'bn' => $item['bn'],
                    'normal_num' => (int)$item['num']
                );
            }
        }
        
        return $result;
    }

    protected function _dealWMSParams($params) {
        if(empty($params['reship_bn']) && empty($params['return_order_id'])) {
            return [false, ['msg'=>'缺少参数']];
        }
        $wmsStatus = $params['status'] ? $params['status'] : $params['io_status'];
        $nodeId = $this->__channelObj->channel['node_id'];
        $wrMdl = app::get('console')->model('wms_reship');
        if($params['return_order_id']) {
            if($row = $wrMdl->db_dump(['return_order_id'=>$params['return_order_id'], 'wms_node_id'=>$nodeId], 'id')) {
                if($row['wms_status'] != $wmsStatus) {
                    $wrMdl->update(['wms_status'=>$wmsStatus], ['id'=>$row['id']]);
                    app::get('ome')->model('operation_log')->write_log('wms_reship@console',$row['id'], '更新成功：'.$wmsStatus);
                }
                return [true, ['msg'=>'已经存在']];
            }
        } else {
            if($row = $wrMdl->db_dump(['reship_bn'=>$params['reship_bn']], 'id, wms_status')) {
                if($row['wms_status'] != $wmsStatus) {
                    $wrMdl->update(['wms_status'=>$wmsStatus], ['id'=>$row['id']]);
                    app::get('ome')->model('operation_log')->write_log('wms_reship@console',$row['id'], '更新成功：'.$wmsStatus);
                }
                return [true, ['msg'=>'已经存在']];
            }
        }
        $inData = [];
        $inData['reship_bn'] = $params['reship_bn'];
        $inData['return_order_id'] = $params['return_order_id'];
        $inData['wms_status'] = $wmsStatus;
        $inData['wms_node_id'] = $nodeId;
        $inData['logistics'] = $params['logistics'];
        $inData['logi_no'] = $params['logi_no'];
        $inData['remark'] = $params['remark'];
        $inData['extend_props'] = $params['extendProps'];
        $inData['order_type'] = $params['order_type'];
        $inData['warehouse'] = $params['warehouse'];
        $id = $wrMdl->insert($inData);
        if(!$id) {
            return [false, ['msg'=>'主表写入失败']];
        }
        $items = isset($params['item']) ? json_decode($params['item'],true) : array();
        if($items){
            $inItems = [];
            foreach($items as $key=>$val){
                $inItems[] = [
                    'wr_id' => $id,
                    'product_bn' => $val['product_bn'],
                    'inventory_type' => $val['inventoryType'],
                    'normal_num' => $val['normal_num'],
                    'defective_num' => $val['defective_num'],
                    'sn_list' => $val['sn_list'] ? json_encode($val['sn_list'], JSON_UNESCAPED_UNICODE) : '',
                    'batch' => $val['batch'] ? json_encode($val['batch'], JSON_UNESCAPED_UNICODE) : '',
                    'wms_item_id' => $val['item_id'],
                    'extend_props' => $val['extendProps'],
                ];
            }
            $wriMdl = app::get('console')->model('wms_reship_items');
            $sql = kernel::single('ome_func')->get_insert_sql($wriMdl, $inItems);
            $wriMdl->db->exec($sql);
        }
        app::get('ome')->model('operation_log')->write_log('wms_reship@console',$id, '接收成功：'.$wmsStatus);
        return [true, ['msg'=>'处理完成']];
    }
    
    #wms.reship.add_complete
    public function add_complete($params) {
        $this->__apilog['title'] = $this->__channelObj->channel['channel_name'] . '新建与完成退货单';
        $this->__apilog['original_bn'] = $params['returnOrderId'];
        $data = array(
            'reship_bn' => trim($params['return_order_id']),
            'logi_code' => $params['logistics_code'],
            'logi_name' => $params['logistics_name'],
            'logi_no' => $params['express_code'],
            'branch_bn' => $params['warehouse_code'],
            'memo' => $params['remark'],
            'operate_time' => $params['order_confirm_time'] ? $params['order_confirm_time'] : date('Y-m-d H:i:s'),
            'wms_id' => $this->__channelObj->channel['channel_id'],
        );
        $filter = array('branch_bn'=>$params['branch_bn']);
        $wmsBranchRelation = app::get('wms')->model('branch_relation')->db_dump(array('wms_id'=>$data['wms_id'],'wms_branch_bn'=>$data['branch_bn'],'is_virtual'=>'0'),'branch_id');
        if ($wmsBranchRelation) {
            $filter = array('branch_id'=>$wmsBranchRelation['branch_id']);
        }
        $branch = app::get('ome')->model('branch')->db_dump($filter, 'branch_id,name');
        if (!$branch) {
            $this->__apilog['result']['msg'] = '仓库不存在';
            return false;
        }
        $data['branch'] = $branch;
        $items = isset($params['items']) ? json_decode($params['items'], true) : array();
        $reship_items = array();
        if ($items) {
            foreach ($items as $key => $val) {
                if(empty($val['subSourceOrderCode'])) {
                    continue;
                }
                $ccnum = 0;
                if($val['batchs']['batch']) {
                    $v = $val['batchs']['batch'];
                    // 如果是多维数组
                    if (isset($v[0])) {
                        foreach ($v as $vv) {
                            if ($vv['inventoryType'] == 'CC') {
                                $ccnum += $vv['actualQty'];
                            }
                        }                         
                    } else {
                        if ($v['inventoryType'] == 'CC') {
                            $ccnum += $v['actualQty'];
                        }
                    }
                }
                $reship_items[] = array(
                    'order_bn' => $val['sourceOrderCode'],
                    'oid' => $val['subSourceOrderCode'],
                    'bn' => $val['itemCode'],
                    'num' => $val['actualQty'],
                    'ccnum' => $ccnum,
                );
            }
        }
        $data['items'] = $reship_items;
        return $data;
    }
    
    /**
     * WMS退货服务单退款消息
     * 
     * @param array $params
     * @return array
     */
    public function service_refund($params)
    {
        $this->__apilog['title'] = $this->__channelObj->wms['channel_name'] . '云交易订单退款成功MQ消息' . $params['reship_bn'];
        $this->__apilog['original_bn'] = $params['reship_bn'];
        
        return $params;
    }

    public function lanjieReship($params) {
        $this->__apilog['title'] = $this->__channelObj->channel['channel_name'] . '拦截退货单';
        $this->__apilog['original_bn'] = $params['reship_bn'];
        $data = array(
            'reship_bn' => trim($params['reship_bn']),
            'logi_code' => $params['logistics_code'],
            'logi_name' => $params['logistics_name'],
            'logi_no' => $params['express_code'],
            'branch_bn' => $params['warehouse'],
            'memo' => $params['remark'],
            'operate_time' => $params['order_confirm_time'] ? $params['order_confirm_time'] : date('Y-m-d H:i:s'),
            'wms_id' => $this->__channelObj->channel['channel_id'],
            'act'   =>'lanjiereship',
        );
        $params['status'] = $params['status'] ? $params['status'] : $params['io_status'];
        switch($params['status']){
          case 'FINISH': $data['status']='FINISH';break;
          case 'PARTIN': $data['status']='PARTIN';break;
          case 'CLOSE':
          case 'FAILED':
          case 'DENY':
            $data['status'] = 'CLOSE'; break;
          default:
            $data['status'] = $params['status'];break;
        }
        $filter = array('branch_bn'=>$params['branch_bn']);
        $wmsBranchRelation = app::get('wmsmgr')->model('branch_relation')->db_dump(array('wms_id'=>$data['wms_id'],'wms_branch_bn'=>$data['branch_bn']),'sys_branch_bn');
        if ($wmsBranchRelation) {
            $filter = array('branch_bn'=>$wmsBranchRelation['sys_branch_bn'],'check_permission'=>'false');
        }
        $branch = app::get('ome')->model('branch')->db_dump($filter, 'branch_id,name,branch_bn');
        if (!$branch) {
            $this->__apilog['result']['msg'] = '仓库不存在';
            return false;
        }
        $data['branch'] = $branch;
        $items = isset($params['item']) ? json_decode($params['item'], true) : array();
        $reship_items = array();
        $refund_ids = [];
        if ($items) {
            foreach ($items as $key => $val) {
                if(empty($val['subSourceOrderCode'])) {
                    continue;
                }
                $extendProps = $val['extendProps'];
                $extendProps = is_string($extendProps) ? json_decode($extendProps, true) : $extendProps;
                if($extendProps){
                    if($extendProps['tradeReturnId']){
                        $refund_ids[] = $extendProps['tradeReturnId'];
                    }
                }
                $reship_items[] = array(
                    'order_bn' => $val['sourceOrderCode'],
                    'oid' => $val['subSourceOrderCode'],
                    'bn' => $val['product_bn'],
                    'num' => $val['normal_num'],
                    'ccnum' => $val['defective_num'],
                    'extendProps'=>$val['extendProps'],
                );
            }
        }
        $data['items'] = $reship_items;

        return $data;
    }
}
