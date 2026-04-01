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
class ome_return_process{
	
	public function do_iostock($por_id,$io,&$msg){
    	//生成出入库明细
		$allow_commit = false;
        $trans = kernel::database()->beginTransaction();

        $iostockData = $this->get_iostock_data($por_id);
        foreach ($iostockData as $iostock){
            
            if(kernel::single('siso_receipt_iostock_reship')->create($iostock, $data, $msg)){
                $allow_commit = true;
            }else{
                $allow_commit = false;
                break;
            }
        }
        if ($allow_commit == true){
            kernel::database()->commit($trans);
            return true;
        }else{
            
            kernel::database()->rollBack();
            return false;
        }
    }
    
/**
     * 根据仓库组织出库数据
     * @access public
     * @param String $iso_id 出入库ID
     * @return sdf 出库数据
     */
    public function get_iostock_data($pro_id){
        $pro_items_detail = $this->getProItems($pro_id);
        $iostock_data = array();
        $pro_detail = $this->getRetrunProcess($pro_id);
        $reship_id = $pro_detail['reship_id'];
        if ($pro_items_detail){
            foreach ($pro_items_detail as $k=>$v){
                $iostock_data[$v['branch_id']]['items'][$v['item_id']] = array(
                    'item_id' => $v['item_id'],
                    'bn' => $v['bn'],
                    'price' => $v['need_money'],
                    'normal_num' => $v['num'],
                    'create_time' => time(),
                );
                $iostock_data[$v['branch_id']]['reship_id'] = $reship_id;
                $iostock_data[$v['branch_id']]['branch_id'] = $v['branch_id'];
                $iostock_data[$v['branch_id']]['memo'] = $pro_detail['memo'];
            }
        }
       
        return $iostock_data;
    }
    
	function getProItems($pro_id){
		$objProItems = app::get('ome')->model('return_process_items');
		$pro_items_detail = $objProItems->getList('*', array('por_id'=>$pro_id), 0, -1);
        
		return $pro_items_detail;

    }
    
    function getRetrunProcess($pro_id,$field='*'){
    	$db = kernel::database();
        $sql = 'SELECT '.$field.' FROM `sdb_ome_return_process` WHERE `por_id`=\''.$pro_id.'\'';
        return $db->selectrow($sql);
    }
    
    //根据reship_id获取发货信息
    public function get_delivery_list_by_reship_id($reship_id){
        $mdl_ome_reship = app::get('ome')->model('reship');
        $lib_archive_interface_delivery = kernel::single('archive_interface_delivery');
        $filter = array('reship_id'=>$reship_id,'is_check|notin'=>array('0','2','4','5','7','8','9'));
        $reship_row = $mdl_ome_reship->dump($filter,'reship_id,order_id,logi_id,branch_id,archive');
        if(empty($reship_row)){
            return array("error_msg"=>"没有查找到有效退换单据记录");
        }
        if(in_array($reship_row['archive'],array('1'))){ //归档的 
            $delivery_list = array();
            $deliverys = $lib_archive_interface_delivery->get_delivery($reship_row['order_id']);
            foreach($deliverys as $delivery){
                foreach($delivery['items'] as $item){
                    $item['delivery_bn'] = $delivery['delivery_bn'];
                    $item['parent_id'] = $delivery['parent_id'];
                    $item['delivery_id'] = $delivery['delivery_id'];
                    $delivery_list[] = $item;
                }
            }
        }else{ //订单对应发货单明细
            $sql = "SELECT dt.delivery_id, dt.product_id, dt.bn, dt.number, d.delivery_bn, d.parent_id FROM sdb_ome_delivery_order AS dord
                   LEFT JOIN sdb_ome_delivery AS d ON(dord.delivery_id=d.delivery_id)
                   LEFT JOIN sdb_ome_delivery_items AS dt ON d.delivery_id = dt.delivery_id
                   WHERE dord.order_id=". $reship_row['order_id']." AND d.is_bind='false' AND d.disabled='false' AND d.status='succ' AND d.pause='false'";
            $delivery_list = kernel::database()->select($sql);
        }
        if(empty($delivery_list)){
            return array("error_msg"=>"没有查找到对应的发货单记录");
        }
        return $delivery_list;
    }

    /**
     * @param $arrItemUpData = array (
                                  1 => #product_id
                                  array (
                                    'check_num' => '1', #明细中num的总和
                                    'items' => [
                                        array (
                                        'memo' => '',
                                        'store_type' => '0', #array('新仓','残仓','报废')
                                        'branch_id' => '1',
                                        'num' => '1',
                                        'reship_item_id' => '1',
                                        ),
                                    ]
                                  ),
                                )
     * @param array $returnProcess sdb_ome_return_process 表的内容
     * @param bool $isCheckNum 是否检查校验数量与退货数量的关系
     * @return array
     */
    public function qualityCheckItemsSave($arrItemUpData, $returnProcess, $isCheckNum = true) {
        $modelItems = app::get('ome')->model('return_process_items');
        $items = $modelItems->getList('*', array('por_id'=>$returnProcess['por_id']));
        $arrItem = array();
        $deleteItemId = array();
        $arrHadCheckItem = array();
        foreach($items as $item) {
            if($item['is_check'] == 'false') {
                if ($arrItem[$item['product_id']]) {#兼容旧写入方式
                    $deleteItemId[$item['product_id']][] = $item['item_id'];
                    $arrItem[$item['product_id']]['num'] += $item['num'];
                    continue;
                }
                $arrItem[$item['product_id']] = $item;
            } else {
                $arrHadCheckItem[$item['product_id']] = $item;
            }
        }
        if($deleteItemId) {#兼容旧写入方式 一个一条
            foreach($deleteItemId as $k => $val) {
                $modelItems->delete(array('item_id'=>$val));
                $modelItems->update(array('num'=>$arrItem[$k]['num']), array('item_id' => $arrItem[$k]['item_id']));
            }
        }
        $reshipItemMdl = app::get('ome')->model('reship_items');

        $reshipItems = $reshipItemMdl->getList('product_id,price', array('reship_id'=>$returnProcess['reship_id']));
        $productPrice = array();
        foreach($reshipItems as $val) {
            $val['price'] && $productPrice[$val['product_id']] = $val['price'];
        }
        $hasUpdateItemId = array();
        $oOperation_log = app::get('ome')->model('operation_log');
        $oProblem = app::get('ome')->model('return_product_problem');
        $oBranch = app::get('ome')->model('branch');
        foreach($arrItemUpData as $productId => $val) {
            if($isCheckNum) {
                if ($arrItem[$productId]['num'] != $val['check_num']) {
                    return array('rsp' => 'fail', 'msg' => '请全部扫描完!');
                }
            } else {
                if(!isset($arrItem[$productId])) {
                    $arrItem[$productId] = $arrHadCheckItem[$productId];
                    $hasUpdateItemId[] = $arrItem[$productId]['item_id'];
                }
            }
            $branchUpData = $val['items'];
            foreach($branchUpData as $upData){
                if($upData['num'] > 0) {
                    if (!isset($arrItem[$productId])) {
                        return array('rsp' => 'fail', 'msg' => '可质检明细不存在');
                    }
                    $upData['need_money'] = $upData['num'] * $productPrice[$productId];
                    $upData['is_check'] = 'true';
                    $upData['acttime'] = time();
                    if (in_array($arrItem[$productId]['item_id'], $hasUpdateItemId)) {
                        $insertData = array_merge($arrItem[$productId], $upData);
                        unset($insertData['item_id']);
                        $rs = $modelItems->insert($insertData);
                        if (!$rs) {
                            return array('rsp' => 'fail', 'msg' => '质检明细写入失败!');
                        }
                    } else {
                        $rs = $modelItems->update($upData, array('item_id' => $arrItem[$productId]['item_id'], 'is_check' => 'false'));
                        if (is_bool($rs)) {
                            return array('rsp' => 'fail', 'msg' => '质检明细更新失败!');
                        }
                        $hasUpdateItemId[] = $arrItem[$productId]['item_id'];
                    }

                    // 更新退货明细
                    // if ($upData['branch_id'] && $returnProcess['reship_id'] ){
                    //     $reshipItemMdl->update(['branch_id' => $upData['branch_id']],[
                    //         'reship_id'     => $returnProcess['reship_id'],
                    //         'product_id'    => $productId,
                    //     ]);
                    // }
                    
                    // 更新reship_items表的良品/不良品数量,解决自有仓自动退款不会回写的问题
                    if ($isCheckNum) {
                        $upFilter = [
                            'reship_id'  => $returnProcess['reship_id'],
                            'product_id' => $productId,
                        ];
                        if($arrItem[$productId]['reship_item_id']){
                            $upFilter['reship_item_id'] = $upData['reship_item_id'];
                        }
                        $upItem = [];
                        if (in_array($upData['store_type'], [1,2])) {
                            $upItem['defective_num_upset_sql'] = '`defective_num`+'.$upData['num'];
                        } else {
                            $upItem['normal_num_upset_sql'] = '`normal_num`+'.$upData['num'];
                        }
                        $reshipItemMdl->update($upItem, $upFilter);
                    }

                    # 写日志
                    $memo = '有' . $upData['num'] . '件货品【' . $arrItem[$productId]['bn'] . '】质检成功,进入' . $oProblem->get_store_type($upData['store_type']) . ':' . $oBranch->Getlist_name($upData['branch_id']);
                    if ($returnProcess['return_id']) {
                        $oOperation_log->write_log('return@ome', $returnProcess['return_id'], $memo);
                    }
                    $oOperation_log->write_log('reship@ome', $returnProcess['reship_id'], $memo);
                }
            }
        }
        return array('rsp'=>'succ');
    }
}
