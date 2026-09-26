<?php

/**
 * ============================
 * @Author:   yaokangming
 * @Version:  1.0
 * @DateTime: 库存调整单
 * @describe: 类
 * ============================
 */
class console_adjust {

    public function dealSave($data) {
        if(empty($data['branch_id'])) {
            return [false, ['msg'=>'仓库不存在']];
        }
        if(empty($data['negative_branch_id'])) {
            return [false, ['msg'=>'出库仓库不存在']];
        }
        if(mb_strlen($data['memo']) > 255) {
            return [false, ['msg'=>'备注过长，建议80字以下']];
        }
        if(empty($data['items'])) {
            return [false, ['msg'=>'缺少明细']];
        }
        $branch = app::get('ome')->model('branch')->db_dump(array('branch_id'=>$data['branch_id'],'check_permission' => 'false'), 'branch_bn,type');
        if(empty($branch)) {
            return [false, ['msg'=>'仓库不存在']];
        }
        $op = kernel::single('ome_func')->getDesktopUser();
        $adjustMdl = app::get('console')->model('adjust');
        kernel::database()->beginTransaction();
        $main = [
            'adjust_bn' => $data['adjust_bn'] ? : $adjustMdl->gen_id($branch['branch_bn']),
            'adjust_type' => $data['adjust_type'] ? : 'tz',
            'adjust_bill_type' => (string) $data['adjust_bill_type'],
            'adjust_mode' => $data['adjust_mode'] ? : 'zl',
            'branch_id' => $data['branch_id'],
            'negative_branch_id' => json_encode($data['negative_branch_id'], 1),
            'is_check' => $data['is_check'],
            'iso_status' => $data['iso_status'] ? : 'check',
            'bill_status' => '1',
            'operator' => $data['application_name'] ? $data['application_name'] : $op['op_name'],
            'origin_id' => $data['origin_id'] ? : 0,
            'origin_bn' => (string)$data['origin_bn'],
            'business_bn' => (string)($data['business_bn'] ? : $data['origin_bn']),
            'memo' => (string)$data['memo'],
            'adjust_channel' => $data['adjust_channel']=='storeadjust'?'storeadjust':'branchadjust',
        ];
        if($data['source']){
            $main['source'] = $data['source'];
        }
        if($data['create_time']) {
            $main['at_time'] = $data['create_time'];
        }
        $rs = $adjustMdl->insert($main);
        if(!$rs) {
            $errmsg = '主表保存失败:'.kernel::database()->errorinfo();
            kernel::database()->rollBack();
            return [false, ['msg'=>$errmsg]];
        }
        if($main['origin_id']) {
            $oiRows = $adjustMdl->getList('id', ['origin_id'=>$main['origin_id'], 'adjust_type'=>$main['adjust_type'], 'adjust_bill_type'=>$main['adjust_bill_type']]);
            if(count($oiRows) > 1) {
                kernel::database()->rollBack();
                return [false, ['msg'=>$main['origin_bn'].'已经存在调整单类型：'.$adjustMdl->schema['columns']['adjust_type']['type'][$main['adjust_type']].',子类型：'.$main['adjust_bill_type']]];
            }
        }
        $productIds = array_keys($data['items']);
        $oldProductStore = app::get('ome')->model('branch_product')->getList('product_id,branch_id,store,store_freeze', ['branch_id'=>$data['negative_branch_id'], 'product_id'=>$productIds]);
       
        $productStore = [];
        foreach($oldProductStore as $v) {
            $productStore[$v['product_id']]['store'] += $v['store'];
            $productStore[$v['product_id']]['store_freeze'] += $v['store_freeze'];
        }
        $products = app::get('material')->model('basic_material')->getList('bm_id,material_bn,material_name', ['bm_id'=>$productIds]);
        $products = array_column($products, null, 'bm_id');

        $batch = $data['batch'];
        $items = [];
        $freezeItems = [];
        foreach ($data['items'] as $bmId => $number) {
            if($main['adjust_mode'] == 'zl' && bccomp($number, 0, 0) == 0) {
                continue;
            }
            $tmp = [
                'adjust_id' => $main['id'],
                'bm_id' => $bmId,
                'bm_bn' => $products[$bmId]['material_bn'],
                'bm_name' => $products[$bmId]['material_name'],
                'origin_number' => (int)$productStore[$bmId]['store'],

            ];
            
            $tmp['number'] = $main['adjust_mode'] == 'zl' ? (int)$number : bcsub($number, $tmp['origin_number'], 0);
            $tmp['final_number'] = bcadd($tmp['origin_number'], $tmp['number'], 0);
            if(bccomp($tmp['final_number'], 0, 0) == -1) {
                kernel::database()->rollBack();
                return [false, ['msg'=>'商品:'.$tmp['bm_bn'].'调整后数量不能为负数 调整后数量为:'.$tmp['final_number'].' 调整前数量为:'.$tmp['origin_number'].' 调整数量为:'.$tmp['number']]];
            }
            if($data['sn'][$bmId]) {
                if(count($data['sn'][$bmId]) != abs($tmp['number'])) {
                    kernel::database()->rollBack();
                    return [false, ['msg'=>'商品:'.$tmp['bm_bn'].' SN数量不对']];
                }
                $tmp['sn'] = json_encode($data['sn'][$bmId]);
            }

            if($batch[$bmId]){
                $tmp['batch'] = $batch[$bmId];
            }
            if(($productStore[$bmId]['store'] - $productStore[$bmId]['store_freeze'] + $tmp['number']) < 0
                && $data['source'] != '初始化导入') {
                kernel::database()->rollBack();
                return [false, ['msg'=>'商品:'.$tmp['bm_bn'].'库存不足 库存为:'.$productStore[$bmId]['store'].' 冻结为:'.$productStore[$bmId]['store_freeze'].' 调整数量为:'.$tmp['number']]];
            }
            $items[] = $tmp;
        }
        if(empty($items)) {
            kernel::database()->rollBack();
            return [false, ['msg'=>'缺少保存明细']];
        }
        $adjustItemsMdl = app::get('console')->model('adjust_items');
        $sql = kernel::single('ome_func')->get_insert_sql($adjustItemsMdl, $items);
        $adjustItemsMdl->db->exec($sql);
        app::get('ome')->model('operation_log')->write_log('adjust@console',$main['id'],"新建成功，来源：".($data['source']?:'新建操作'));
        kernel::database()->commit();
     
        if ($data['is_check'] == '0') {
            list($confirmRs, $confirmData) = $this->confirmBill($main);
            if (!$confirmRs && !empty($confirmData['msg'])) {
                return [true, ['msg' => '保存成功，自动确认失败：' . $confirmData['msg']]];
            }
        }
        return [true, ['msg'=>'操作成功', 'adjust_id'=>$main['id'], 'adjust_bn'=>$main['adjust_bn']]];
    }

   
    public function confirmBill($main) {
        if (empty($main['id'])) {
            return [false, ['msg' => '调整单主键缺失']];
        }
        $main = app::get('console')->model('adjust')->db_dump($main['id']);
        if (empty($main)) {
            return [false, ['msg' => '调整单不存在']];
        }

        $items = app::get('console')->model('adjust_items')->getList('id', ['adjust_id' => $main['id'], 'adjust_status' => '0']);
        $itemIds = array_column($items, 'id');
        if (empty($itemIds)) {
            return [true, ['msg' => '明细已经调整完']];
        }

        $lastResult = [true, ['msg' => '操作成功']];
        foreach (array_chunk($itemIds, 100000) as $val) {
            $lastResult = $this->confirmItems($val, $main);
            if (!$lastResult[0]) {
                return $lastResult;
            }
        }
        return $lastResult;
    }

    public function confirmItems($itemIds, $main) {
        $adjustItemsMdl = app::get('console')->model('adjust_items');
        
        $itemIds = array_values(array_filter(array_map('intval', (array) $itemIds)));
        if (empty($itemIds) || empty($main['id'])) {
            return [false, ['msg' => '缺少有效的调整单或明细', 'fail_num' => count((array) $itemIds)]];
        }

        $items = $adjustItemsMdl->getList('id,bm_id,bm_bn,number,sn,batch', ['id'=>$itemIds, 'adjust_id'=>$main['id'], 'adjust_status'=>'0']);

        if(empty($items)) {
            $pending = $adjustItemsMdl->count(['adjust_id' => $main['id'], 'adjust_status' => '0']);
            if ($pending > 0) {
                return [false, ['msg' => '未找到匹配的待确认明细，请刷新页面后重试', 'fail_num' => count($itemIds)]];
            }
            return [true, ['msg'=>'明细已经调整完']];
        }
        $adjust_channel = $main['adjust_channel'].($main['adjust_bill_type'] == '库存初始化' ? '_init' : '');
        if($main['adjust_type'] == 'pd') {
            $adjust_channel = $main['adjust_channel'] == 'storeadjust' ? 'storeinventory' : 'branchinventory';
        }
        $op = kernel::single('ome_func')->getDesktopUser();
        $adjustMdl = app::get('console')->model('adjust');
        $adjustMdl->update(['bill_status'=>'2'], ['id'=>$main['id']]);
        $addData= $subData = array(
            'original_bn' => $main['adjust_bn'],
            'original_id' => $main['id'],
            'branch_id' => $main['branch_id'],
            'negative_branch_id' => json_decode($main['negative_branch_id'], 1),
            'iso_status' => $main['iso_status'],
            'adjust_channel' => $adjust_channel,
            'operator' => $op['op_name'],
            'memo' => $main['memo'],
            'business_bn' => $main['business_bn'] ? : $main['adjust_bn'],
        );
       
        foreach ($items as $v) {
            if($v['number'] > 0) {
                $addData['item_id'][] = $v['id'];
                $addData['items'][] = array(
                    'product_id'=>$v['bm_id'],
                    'bn'   =>$v['bm_bn'],
                    'nums'=>$v['number'],
                 
                    'sn'   =>$v['sn'] ? json_decode($v['sn'],true) : [],
                    'memo'=>$main['memo'],
                    'batch'=>$v['batch'] ? json_decode($v['batch'],true) : [],
                );
            }
            if($v['number'] < 0) {
                $subData['item_id'][$v['bm_id']] = $v['id'];
                if(is_array($v['batch'])) {
                    foreach($v['batch'] as $bak => $bav) {
                        $v['batch'][$bak]['num'] = abs($bav['num']);
                    }
                }
                $subData['items'][] = array(
                    'product_id'=>$v['bm_id'],
                    'bn'   =>$v['bm_bn'],
                 
                    'sn'   =>$v['sn'] ? json_decode($v['sn'],true) : [],
                    'nums'=>abs($v['number']),
                    'memo'=>$main['memo'],
                    'batch'=>$v['batch'] ? json_decode($v['batch'],true) : [],
                );
            }
            if($v['number'] == 0) {
                $adjustItemsMdl->update(['adjust_status'=>'1'], ['id'=>$v['id']]);
            }
        }

        $msg = '';
        $failNum = 0;
        if($addData['items']) {
            list($rs, $rsData)  =$this->_dealInItems($addData);
            if(!$rs) {
                $msg .= '入库失败：' . $this->_formatIostockMsg(isset($rsData['msg']) ? $rsData['msg'] : '');
                $failNum += count($addData['items']);
            }
        }
        if($subData['items']) {
            list($rs, $rsData) = $this->_dealOutItems($subData);
            if(!$rs) {
                $msg .= '出库失败：' . $this->_formatIostockMsg(isset($rsData['msg']) ? $rsData['msg'] : '');
                $failNum += count($subData['items']);
            }
        }
        $opLog = "调整单部分确认,";
        if(!$adjustItemsMdl->db_dump(['adjust_id'=>$main['id'], 'adjust_status'=>'0'], 'id')) {
            $adjustMdl->update(['bill_status'=>'4'], ['id'=>$main['id']]);
            $opLog = "调整单确认完成,";
        }
        $opLog .= $msg;
        app::get('ome')->model('operation_log')->write_log('adjust@console',$main['id'],$opLog);
        if($msg) {
            return [false, ['msg'=>$msg, 'fail_num'=>$failNum]];
        }
        return [true, ['msg'=>'操作成功']];
    }


    private function _dealInItems($addData) {
        if(empty($addData['items'])) {
            return [true, ['msg'=>'缺少明细']];
        }
        kernel::database()->beginTransaction();
        $products = [];
        foreach ($addData['items'] as $v) {
            $products[$v['product_id']] = [
                'bn' => $v['bn'],
                'name' => '',
                'nums' => $v['nums'],
               
                'sn_list' => $v['sn'],
                'unit' => '',
                'price' => 0,
                'batch'=>$v['batch'],
            ];
        }
        $op = kernel::single('ome_func')->getDesktopUser();
        $data = array(
            'iostockorder_name' => date('Ymd') . '入库单',
            'supplier'          => '',
            'supplier_id'       => 0,
            'branch'            => $addData['branch_id'],
            'extrabranch_id'    => 0,
            'type_id'           => ome_iostock::DIRECT_STORAGE,
            'iso_price'         => 0,
            'memo'              => (string)$addData['memo'],
            'operator'          => $op['op_name'],
            'original_bn'       => $addData['original_bn'],
            'original_id'       => $addData['original_id'],
            'products'          => $products,
            'appropriation_no'  => '',
            'bill_type'         => $addData['adjust_channel'],
            'business_bn'       => $addData['business_bn'],
        );
        $branch = app::get('ome')->model('branch')->db_dump(['branch_id'=>$addData['branch_id']], 'type');
        if($branch['type']=='damaged') {
            $data['type_id'] = ome_iostock::DAMAGED_STORAGE;
        }
        if($addData['iso_status'] == 'confirm') {
            $data['confirm'] = 'Y';
        } else {
            $data['check'] = 'Y';
        }
        $iostockorder_instance = kernel::single('console_iostockorder');
        $rs = $iostockorder_instance->save_iostockorder($data, $msg);
        if($rs) {
            if ($addData['iso_status'] == 'confirm') {
                $this->machineCenterAdjustSnAdd($rs);
            }
            $adjustItemsMdl = app::get('console')->model('adjust_items');
            $adjustItemsMdl->update(['adjust_status'=>'1'], ['id'=>$addData['item_id']]);
            kernel::database()->commit();
        } else {
            kernel::database()->rollBack();

        }
        return [$rs, ['msg'=>$this->_formatIostockMsg($msg)]];
    }

    /**
     * save_iostockorder / confirm_iostockorder 失败时 $msg 可能是 string 或 array，统一转成可读文本。
     *
     * @param mixed $msg
     * @return string
     */
    private function _formatIostockMsg($msg)
    {
        if ($msg === null || $msg === '') {
            return '';
        }
        if (is_string($msg) || is_numeric($msg)) {
            return (string) $msg;
        }
        if (!is_array($msg)) {
            return (string) $msg;
        }
        if (isset($msg['iostock_msg'])) {
            $inner = $msg['iostock_msg'];
            if (is_array($inner)) {
                return implode('; ', array_map('strval', array_filter($inner, function ($v) {
                    return $v !== '' && $v !== null;
                })));
            }
            return (string) $inner;
        }
        if (isset($msg['msg'])) {
            return $this->_formatIostockMsg($msg['msg']);
        }
        $parts = array();
        foreach ($msg as $k => $v) {
            if ($v === '' || $v === null) {
                continue;
            }
            if (is_array($v)) {
                $text = $this->_formatIostockMsg($v);
            } else {
                $text = (string) $v;
            }
            if ($text !== '') {
                $parts[] = $text;
            }
        }
        return implode('; ', $parts);
    }

    private function _dealOutItems($subData){
        if(empty($subData['items'])) {
            return [true, ['msg'=>'缺少明细']];
        }
        $arrBmNum = [];
        $arrBmBn = [];
       
        $arrSn = [];
        $batchlist = [];
        foreach ($subData['items'] as $v) {
            $arrBmNum[$v['product_id']] = $v['nums'];
            $arrBmBn[$v['product_id']] = $v['bn'];
           
            $arrSn[$v['product_id']] = $v['sn'];
            $batchlist[$v['product_id']] = $v['batch'];
        }
        $arrBmIds = array_keys($arrBmNum);
        $bpRows = app::get('ome')->model('branch_product')->getList('*', ['branch_id'=>$subData['negative_branch_id'], 'product_id'=>$arrBmIds]);
       
       
        $arrBranchRows = [];
        foreach ($bpRows as $v) {
            $arrBranchRows[$v['branch_id']][$v['product_id']] = (int)($v['store'] - $v['store_freeze']);
        }
        $op = kernel::single('ome_func')->getDesktopUser();
        $rsbool = true;
        $failBranchId = [];
        $rsMsg = '';
        foreach ($arrBranchRows as $branch_id => $v) {
            kernel::database()->beginTransaction();

            $products = [];
            $itemIds = [];
            foreach ($v as $bm_id => $vv) {
                if($arrBmNum[$bm_id] < 1) {
                    continue;
                }
                $itemIds[] = $subData['item_id'][$bm_id];
                if($arrBmNum[$bm_id] > $vv) {
                    $n = $vv;
                } else {
                    $n = $arrBmNum[$bm_id];
                }
                $arrBmNum[$bm_id] -= $n;
                $products[$bm_id] = [
                    'bn' => $arrBmBn[$bm_id],
                    'name' => '',
                    'sn_list' => $arrSn[$bm_id],
                    'nums' => $n,
                    'unit' => '',
                    'price' => 0,
                    'batch'=>$batchlist[$bm_id],
                ];
            }
            $data = array(
                'iostockorder_name' => date('Ymd') . '出库单',
                'supplier'          => '',
                'supplier_id'       => 0,
                'branch'            => $branch_id,
                'extrabranch_id'    => 0,
                'type_id'           => ome_iostock::DIRECT_LIBRARAY,
                'iso_price'         => 0,
                'memo'              => (string)$subData['memo'],
                'operator'          => $op['op_name'],
                'original_bn'       => $subData['original_bn'],
                'original_id'       => $subData['original_id'],
                'products'          => $products,
                'appropriation_no'  => '',
                'bill_type'         => $subData['adjust_channel'],
                'business_bn'       => $subData['business_bn'],
            );
            if($subData['iso_status'] == 'confirm') {
                $data['confirm'] = 'Y';
            } else {
                $data['check'] = 'Y';
            }
            $iostockorder_instance = kernel::single('console_iostockorder');
            $rs = $iostockorder_instance->save_iostockorder($data, $msg);
            if($rs) {
                if ($subData['iso_status'] == 'confirm') {
                    $this->machineCenterAdjustSnAdd($rs);
                }
                $adjustItemsMdl = app::get('console')->model('adjust_items');
                $adjustItemsMdl->update(['adjust_status'=>'1'], ['id'=>$itemIds]);
                kernel::database()->commit();
            } else {

                $rsbool = false;
                $failBranchId[] = $branch_id;
                $rsMsg .= '仓库ID:'.$branch_id.','.$this->_formatIostockMsg($msg).'</br>';

                kernel::database()->rollBack();
            }
        }
        return [$rsbool, ['msg'=>$rsMsg, 'branch_id'=>$failBranchId]];
    }

    private function machineCenterAdjustSnAdd($iso_id)
    {
        if (!app::get('machinecenter')->is_installed()) {
            return;
        }

        $isoMdl = app::get('taoguaniostockorder')->model('iso');
        $itemsMdl = app::get('taoguaniostockorder')->model('iso_items');
        $isoInfo = $isoMdl->db_dump($iso_id, 'iso_id,iso_bn,type_id,branch_id,complete_time');
        if (empty($isoInfo)) {
            return;
        }

        $isoInfo['finish_time'] = $isoInfo['complete_time'];
        $isoInfo['items'] = $itemsMdl->getList('product_id,bn,sn_list', array('iso_id' => $iso_id));
        kernel::single('console_receipt_stock')->machineCenterOtherStockAdd($isoInfo['iso_id'], $isoInfo);
    }

    public function createAdjustDiff($data)
    {
        $adjsutMdl      = app::get('console')->model('adjust');
        $diff_bn = $data['original_bn'];
    
        $branch = app::get('ome')->model('branch')->db_dump(['branch_id' => $data['branch'], 'check_permission' => 'false']);
    
        $filter = ['adjust_type' => 'tz', 'origin_bn' => $diff_bn, 'branch_id' => $data['branch']];
        //盘盈和盘亏分别生成调整单
        if (isset($data['type_id']) && $data['type_id']) {
            $filter['adjust_bill_type'] = $data['type_id'] == '921' ? 'less' : 'more';
        }
        $adjustInfo = $adjsutMdl->db_dump($filter);
        if ($adjustInfo) {
            return [false, "差异单" . $diff_bn . "已生成" . $branch['branch_bn'] . "的库存调整单"];
        }
        $adjust_bill_type = $data['type_id'] == '921' ? 'less' : 'more';
        $adjustMain = [
            'adjust_mode'        => 'zl',
            'is_check'           => 0,
            'iso_status'         => 'confirm',
            'branch_id'          => $data['branch'],
            'negative_branch_id' => [$data['branch']],
            'memo'               => '差异处理',
            'source'             => '差异处理',
            'adjust_type'        => 'cy',
            'adjust_bill_type'   => $branch['branch_bn'].'_'.$adjust_bill_type,
            'origin_id'          => $data['original_id'],
            'origin_bn'          => $diff_bn,
        ];
        if ($data['at_time']) {
            if (is_string($data['at_time'])) {
                $adjustMain['create_time'] = date('Y-m-d H:i:s', strtotime($data['at_time']));
            } elseif (is_numeric($data['at_time'])) {
                $adjustMain['create_time'] = date('Y-m-d H:i:s', $data['at_time']);
            }
        }

        $items = [];
        foreach ($data['products'] as $bm_id => $v) {
            $items[$bm_id] = $data['type_id'] == '921' ? $v['nums'] : (-1) * $v['nums'];
        }
        
    
        $adjustMain['items'] = $items;
        list($res,$arr_msg) = $this->dealSave($adjustMain);
        if(!$res){
            return [false,$arr_msg['msg']];
        }
        return [true,'差异处理成功'];
    }
}
