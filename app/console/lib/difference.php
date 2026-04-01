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
 * ============================
 * @Author:   yaokangming
 * @Version:  1.0
 * @DateTime: 2022/11/21 11:37:57
 * @describe: 差异单
 * ============================
 */
class console_difference {

    /**
     * insertBill
     * @param mixed $data 数据
     * @return mixed 返回值
     */

    public function insertBill($data) {
        $differenceObj = app::get('console')->model('difference');
        $opInfo = kernel::single('ome_func')->getDesktopUser();
        $inData = [
            'diff_bn' => $differenceObj->gen_id(),
            'task_id' => $data['task_id'],
            'task_bn' => $data['task_bn'],
            'branch_id' => $data['branch_id'],
            'negative_branch_id' => $data['negative_branch_id'] ? json_encode($data['negative_branch_id']) : '',
            'operate_type' => $data['operate_type'],
            'status' => $data['status'] ? : '2',
            'adjust_oper' => $opInfo['op_name'],
            'adjust_time' => time(),
        ];
        if($data['total_amount']) $inData['total_amount'] = $data['total_amount'];
        if($data['physics_id']) $inData['physics_id']=$data['physics_id'];
        $rs = $differenceObj->insert($inData);
        if(!$rs) {
            return [false, ['msg'=>'差异单写入失败']];
        }
        $inItems = [];
        $wms_stores = $oms_stores = $diff_stores = 0;
        foreach ($data['items'] as $key => $value) {
            if($value['diff_stores'] == 0) {
                continue;
            }
            $inItems[$value['bm_id']] = [
                'diff_id' => $inData['id'],
                'bm_id' => $value['bm_id'],
                'material_bn' => $value['material_bn'],
                'wms_stores' => $value['wms_stores'],
                'oms_stores' => $value['oms_stores'],
                'diff_stores' => $value['diff_stores'],
                'oms_diff_stores' => ($value['wms_stores']+$value['diff_stores']-$value['oms_stores']),
                'number' => $value['diff_stores'],
                'pos_accounts_num'=>$value['pos_accounts_num'],
                'batch'    =>  $value['batch'],
            ];
            $wms_stores += $value['wms_stores'];
            $oms_stores += $value['oms_stores'];
            $diff_stores += $value['diff_stores'];
        }
        if(empty($inItems)) {
            return [false, ['msg'=> '缺少差异明细']];
        }
        $diffItemObj = app::get('console')->model('difference_items');
        $sql = kernel::single('ome_func')->get_insert_sql($diffItemObj, $inItems);
        $rs = $diffItemObj->db->exec($sql);
        if(!$rs) {
            return [false, ['msg'=>'差异明细写入失败']];
        }
        list($rs, $rsData) = $this->dealFreeze($inData);
        if(!$rs) {
            return [false, $rsData];
        }
        $differenceObj->update(['wms_stores'=>$wms_stores, 'oms_stores'=>$oms_stores, 'diff_stores'=>$diff_stores], ['id'=>$inData['id']]);
        app::get('ome')->model('operation_log')->write_log('difference@console',$inData['id'],"新建成功");
        return [true, ['msg'=>'操作完成', 'diff_id'=>$inData['id']]];
    }

    /**
     * dealFreeze
     * @param mixed $inData 数据
     * @return mixed 返回值
     */
    public function dealFreeze($inData) {
        $diffItemObj = app::get('console')->model('difference_items');
        $freezeItems = $diffItemObj->getList('id, bm_id, material_bn, number', ['diff_id'=>$inData['id'], 'number|lthan'=>0]);
        if($freezeItems) {
            //增加冻结
            $storeManageLib = kernel::single('ome_store_manage');
            $storeManageLib->loadBranch(array('branch_id' => $inData['branch_id']));
            $negative_branch_id = $inData['negative_branch_id'] ? json_decode($inData['negative_branch_id'], 1) : [$inData['branch_id']];
            $arrBmIds = array_column($freezeItems, 'bm_id');
            $bpRows = app::get('ome')->model('branch_product')->getList('*', ['branch_id'=>$negative_branch_id, 'product_id'=>$arrBmIds]);
            
            $bpList = [];
            foreach ($bpRows as $v) {
                $bpList[$v['product_id']][$v['branch_id']] = (int)($v['store'] - $v['store_freeze']);
            }
            $items = [];
            foreach ($freezeItems as $k => $v) {
                $num = abs($v['number']);
                foreach ($negative_branch_id as $bid) {
                    $validNum = $bpList[$v['bm_id']][$bid];
                    if($num > $validNum) {
                        $n = $validNum;
                    } else {
                        $n = $num;
                    }
                    $num -= $n;
                    $items[] = [
                        'diff_id' => $inData['id'],
                        'di_id' => $v['id'],
                        'branch_id' => $bid,
                        'bm_id' => $v['bm_id'],
                        'material_bn' => $v['material_bn'],
                        'freeze_num' => $n,
                    ];
                    if($num < 1) {
                        break;
                    }
                }
                if($num > 0) {
                    return [false, ['msg' => $v['material_bn'] . '库存不足']];
                }
            }
            $difObj = app::get('console')->model('difference_items_freeze');
            $sql = kernel::single('ome_func')->get_insert_sql($difObj, $items);
            $difObj->db->exec($sql);
            $params = array();
            $params['difference']    = $inData;
            $params['items']    = $items;
            $params = ['params'=>$params];
            $params['node_type'] = 'addDifference';
            $processResult       = $storeManageLib->processBranchStore($params, $err_msg);

            if (!$processResult) {
                return [false, ['msg' => $err_msg]];
            }
        }
        return [true, ['msg'=>'操作成功']];
    }

    /**
     * retryFreeze
     * @param mixed $id ID
     * @return mixed 返回值
     */
    public function retryFreeze($id) {
        $differenceMdl = app::get('console')->model('difference');
        $diff = $differenceMdl->db_dump($id);
        if(empty($diff)) {
            return [false, ['msg'=>'缺少盘差单']];
        }
        $differenceItemsMdl = app::get('console')->model('difference_items_freeze');
        $freezeItems = $differenceItemsMdl->getList('*', ['diff_id'=>$id]);
        if($freezeItems) {
            $storeManageLib = kernel::single('ome_store_manage');
            $storeManageLib->loadBranch(array('branch_id' => $diff['branch_id']));
            $params = $diff;
            $params['items'] = $freezeItems;
            $params = ['params'=>$params];
            $params['node_type'] = 'cancelDifference';
            $processResult       = $storeManageLib->processBranchStore($params, $err_msg);
            if (!$processResult) {
                return [false, ['msg'=>'重新冻结失败：'.$err_msg]];
            }
            $differenceItemsMdl->delete(['diff_id'=>$id]);
        }
        return $this->dealFreeze($diff);
    }

    /**
     * confirm
     * @param mixed $id ID
     * @return mixed 返回值
     */
    public function confirm($id) {
        $dfObj = app::get('console')->model('difference');
        $dfRow = $dfObj->db_dump(['id'=>$id]);
        if($dfRow['status'] != '2') {
            return [false, ['msg'=>'状态不对']];
        }

        //判断是否门店。如果是需要打接口
        if($dfRow['operate_type'] == 'store'){
            $rs = kernel::single('pos_event_trigger_inventory')->addAdjust($id);

            if($rs['rsp'] != 'succ'){
                return [false, ['msg'=>'POS调整单创建失败']];

            }
        }
        kernel::database()->beginTransaction();

        $opInfo = kernel::single('ome_func')->getDesktopUser();
        $upData = [
            'status'=>'1',
            'confirm_oper'=>$opInfo['op_name'],
            'confirm_time'=>time()
        ];
        $rs = $dfObj->update($upData, ['id'=>$id, 'status'=>'2']);
        if ($rs === false || $rs === true) {
            kernel::database()->rollBack();
            return [false, ['msg'=>'状态不对或已确认']];
        }
        app::get('ome')->model('operation_log')->write_log('difference@console',$id,"确认完成");
        $difRows = app::get('console')->model('difference_items_freeze')->getList('*', ['diff_id'=>$id]);
        $storeManageLib = kernel::single('ome_store_manage');
        $storeManageLib->loadBranch(array('branch_id' => $dfRow['branch_id']));
        if($difRows) {
            $params = array();
            $params['diff_id']    = $id;
            $params['branch_id']  = $dfRow['branch_id'];
            $params['items']    = $difRows;
            $params = ['params'=>$params];
            $params['node_type'] = 'confirmDifference';
            $processResult       = $storeManageLib->processBranchStore($params, $err_msg);
            if (!$processResult) {

                kernel::database()->rollBack();
                return [false, ['msg' => $err_msg]];
            }
        }
        $basicMStockFreezeLib = kernel::single('material_basic_material_stock_freeze');
        $basicMStockFreezeLib->delOtherFreeze($id, material_basic_material_stock_freeze::__DIFFERENCEOUT);
        kernel::database()->commit();
        $dfiObj = app::get('console')->model('difference_items');
        $items = $dfiObj->getList('*', ['diff_id'=>$id]);
        $in_items = $out_items = [];
        $batch = [];
        foreach ($items as $v) {
            $batch[$v['bm_id']]=$v['batch'];
            if($v['number'] > 0) {
                $in_items[] = $v;
            }
            if($v['number'] < 0) {
                $out_items[] = $v;
            }
        }
        if($batch) $dfRow['batch'] = $batch;
        list($rs, $rsData) = $this->_dealOutItems($dfRow, $difRows);
        if(!$rs) {
            $dfObj->update(['out_status'=>'1'], ['id'=>$id]);
            app::get('console')->model('difference_items_freeze')->update(['out_status'=>'1'], ['diff_id'=>$id, 'branch_id'=>$rsData['branch_id']]);
            app::get('ome')->model('operation_log')->write_log('difference@console',$id,"出库单失败:".$rsData['msg']);
        }
        list($rs, $rsData) = $this->_dealInItems($dfRow, $in_items);
        if(!$rs) {
            $dfObj->update(['in_status'=>'1'], ['id'=>$id]);
            app::get('ome')->model('operation_log')->write_log('difference@console',$id,"入库单失败:".$rsData['msg']);
        }
        return [true, ['msg'=>'操作成功']];
    }

    /**
     * retryInAndOut
     * @param mixed $id ID
     * @return mixed 返回值
     */
    public function retryInAndOut($id) {
        $dfObj = app::get('console')->model('difference');
        $dfRow = $dfObj->db_dump(['id'=>$id]);
        if(empty($dfRow)) {
            return[false, ['msg'=>'缺少单据']];
        }
        $cdObj = kernel::single('console_difference');
        if($dfRow['out_status'] == '1') {
            list($rs, $rsData) = $this->retryOut($dfRow);
            if(!$rs) {
                return [false, $rsData];
            }
        }
        if($dfRow['in_status'] == '1') {
            list($rs, $rsData) = $this->retryIn($dfRow);
            if(!$rs) {
                return [false, $rsData];
            }
        }
        return [true, ['msg'=>'操作成功']];
    }

    /**
     * retryOut
     * @param mixed $dfRow dfRow
     * @return mixed 返回值
     */
    public function retryOut($dfRow) {
        $id = $dfRow['id'];
        $dfObj = app::get('console')->model('difference');
        $rs = $dfObj->update(['out_status'=>'0'], ['id'=>$id, 'out_status'=>'1']);
        if(is_bool($rs)) {
            return [true, ['msg'=>'已经被处理']];
        }
        $difObj = app::get('console')->model('difference_items_freeze');
        $difRows = $difObj->getList('*', ['diff_id'=>$id, 'out_status'=>'1']);
        if(empty($difRows)) {
            return [true, ['msg'=>'不存在明细']];
        }
        $difObj->update(['out_status'=>'0'], ['id'=>array_column($difRows, 'id')]);
        list($rs, $rsData) = $this->_dealOutItems($dfRow, $difRows);
        if(!$rs) {
            $dfObj->update(['out_status'=>'1'], ['id'=>$id]);
            $difObj->update(['out_status'=>'1'], ['diff_id'=>$id, 'branch_id'=>$rsData['branch_id']]);
            //app::get('ome')->model('operation_log')->write_log('difference@console',$id,"出库单失败:".$rsData['msg']);
            return [false, ['msg'=>"出库单失败:".$rsData['msg']]];
        }
        return [true, $rsData];
    }

    /**
     * retryIn
     * @param mixed $dfRow dfRow
     * @return mixed 返回值
     */
    public function retryIn($dfRow) {
        $id = $dfRow['id'];
        $dfObj = app::get('console')->model('difference');
        $rs = $dfObj->update(['in_status'=>'0'], ['id'=>$id, 'in_status'=>'1']);
        if(is_bool($rs)) {
            return [true, ['msg'=>'已经被处理']];
        }
        $dfiObj = app::get('console')->model('difference_items');
        $in_items = $dfiObj->getList('*', ['diff_id'=>$id, 'number|than'=>0]);
        list($rs, $rsData) = $this->_dealInItems($dfRow, $in_items);
        if(!$rs) {
            $dfObj->update(['in_status'=>'1'], ['id'=>$id]);
            //app::get('ome')->model('operation_log')->write_log('difference@console',$id,"入库单失败:".$rsData['msg']);
            return [false, ['msg'=>"入库单失败:".$rsData['msg']]];
        }
        return [true, $rsData];
    }

    private function _dealInItems($main, $items) {
        //return [false, ['msg'=>'测试重试']];
        if(empty($items)) {
            return [true, ['msg'=>'缺少明细']];
        }
        $products = [];
        foreach ($items as $v) {
            $products[$v['bm_id']] += $v['number'];
        }
        $data = [
            'adjust_type' => 'pd',
            'adjust_bill_type' => (string)'盘盈',
            'adjust_mode' => 'zl',
            'branch_id' => $main['branch_id'],
            'negative_branch_id' => [$main['branch_id']],
            'is_check' => '0',
            'iso_status' => 'confirm',
            'bill_status' => '1',
            'origin_id' => $main['id'],
            'origin_bn' => (string)$main['diff_bn'],
            'business_bn' => (string)$main['task_bn'],
            'memo' => (string)($main['memo'] ? mb_substr($main['memo'], 0, 255) : ''),
            'adjust_channel' => $main['operate_type']=='store'?'storeadjust':'branchadjust',
            'source' => '盘点',
            'batch'=>$main['batch'],
            'items' => $products
        ];
        list($rs, $rsData) = kernel::single('console_adjust')->dealSave($data);
        return [$rs, ['msg'=>$rsData['msg']]];
    }

    private function _dealOutItems($main, $difRows){
        //return [false, ['msg'=>'测试重试', 'branch_id'=>array_column($difRows, 'branch_id')]];
        if(empty($difRows)) {
            return [true, ['msg'=>'缺少明细']];
        }
        $arrBranchRows = [];
        foreach ($difRows as $v) {
            $arrBranchRows[$v['branch_id']][$v['bm_id']] = $v;
        }
        $branch = app::get('ome')->model('branch')->getList('branch_id, branch_bn', ['branch_id'=>array_keys($arrBranchRows)]);
        $branch = array_column($branch, null, 'branch_id');
        $rsbool = true;
        $failBranchId = [];
        $rsMsg = '';
        foreach ($arrBranchRows as $branch_id => $v) {
            $products = [];
            foreach ($v as $bm_id => $vv) {
                $products[$bm_id] = -$vv['freeze_num'];
            }
            $data = [
                'adjust_type' => 'pd',
                'adjust_bill_type' => (string) $branch[$branch_id]['branch_bn'].'-盘亏',
                'adjust_mode' => 'zl',
                'branch_id' => $branch_id,
                'negative_branch_id' => [$branch_id],
                'is_check' => '0',
                'iso_status' => 'confirm',
                'bill_status' => '1',
                'origin_id' => $main['id'],
                'origin_bn' => (string)$main['diff_bn'],
                'business_bn' => (string)$main['task_bn'],
                'memo' => (string)($main['memo'] ? mb_substr($main['memo'], 0, 255) : ''),
                'adjust_channel' => $main['operate_type']=='store'?'storeadjust':'branchadjust',
                'source' => '盘点',
                'batch'=>$main['batch'],
                'items' => $products
            ];
            list($rs, $rsData) = kernel::single('console_adjust')->dealSave($data);
            if(!$rs) {
                $rsbool = false;
                $failBranchId[] = $branch_id;
                $rsMsg .= '仓库ID:'.$branch_id.','.$rsData['msg'].'</br>';
            }
        }
        return [$rsbool, ['msg'=>$rsMsg, 'branch_id'=>$failBranchId]];
    }
}