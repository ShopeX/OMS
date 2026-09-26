<?php
/**
 * 出入库单据相关处理
 */
class console_receipt_stock
{
    private $_isoObj = null;

    private $_itemsObj = null;

    private $_isoInfo = array();

    private $_items = array();

    private static $iso_status = array(
        'PARTIN' => 2,
        'FINISH' => 3,
        'CLOSE'  => 4,
        'CANCEL' => 4,
        'FAILED' => 4,
    );

    public function __construct()
    {
        $this->_isoObj    = app::get('taoguaniostockorder')->model("iso");
        $this->_itemsObj  = app::get('taoguaniostockorder')->model("iso_items");
        $this->_branchLib = kernel::single('ome_branch');
    }

    /**
     * 出入库据保存
     * io 0 出库 1 入库
     * 入库会有残损
     * 出库不会
     * $array $data
     */
    public function do_save($data, $io, &$msg)
    {

        set_time_limit(0);
        $iostock_update = true;
        $auto_iostock   = false;
        $io_status      = $data['io_status'];

        // $shippackages = $data['shippackages'];
        $approMdl       = app::get('taoguanallocate')->model('appropriation');

        $wmsdata =$data;

        kernel::database()->beginTransaction();

        //更新主表，让并发分出先后
        $this->_isoObj->update(['iso_id' => $this->_isoInfo['iso_id']], array('iso_id' => $this->_isoInfo['iso_id']));
        $iostockData = $this->_format_items($data, $io, $iostock_update, $auto_iostock,$diff_status);

        if ($auto_iostock == true && $this->_isoInfo['iso_status'] == '1' && $io_status == 'FINISH') {
            $msg         = '因仓库反馈数量大于申请数量,人工介入';
            $iso__update = array('receive_status' => console_const::_FINISH_CODE);
            if (!$iostock_update) {
                //是否需要确认
                $iso__update['defective_status'] = '1'; #未确认

            }
            $iso_result = $this->_isoObj->update($iso__update, array('iso_id' => $this->_isoInfo['iso_id']));
            kernel::database()->commit();
            return true;
        }
        if ($io == '0') {
            $stockLib = kernel::single('siso_receipt_iostock_stockout');
            $wsoMdl = app::get('console')->model('wms_stockout');
            $wsoRow = $wsoMdl->db_dump(['stockout_bn'=>$this->_isoInfo['iso_bn'], 'iso_status'=>'1'], 'id');
            if($wsoRow) {
                $wsoRs = $wsoMdl->update(['iso_id'=>$this->_isoInfo['iso_id'], 'iso_status'=>'2'], ['id'=>$wsoRow['id'], 'iso_status'=>'1']);
                if(!is_bool($wsoRs)) {
                    app::get('ome')->model('operation_log')->write_log('wms_stockout@console',$wsoRow['id'], '出库完成');
                }
            }
        } else {
            $stockLib = kernel::single('siso_receipt_iostock_stockin');
            $wsiMdl = app::get('console')->model('wms_stockin');
            $wsiRow = $wsiMdl->db_dump(['stockin_bn'=>$this->_isoInfo['iso_bn'], 'iso_status'=>'1'], 'id');
            if($wsiRow) {
                $wsiRs = $wsiMdl->update(['iso_id'=>$this->_isoInfo['iso_id'], 'iso_status'=>'2'], ['id'=>$wsiRow['id'], 'iso_status'=>'1']);
                if(!is_bool($wsiRs)) {
                    app::get('ome')->model('operation_log')->write_log('wms_stockin@console',$wsiRow['id'], '入库完成');
                }
            }
        }
        $stockLib->_typeId = $this->_isoInfo['type_id'];

        if ($iostockData['items']) {
            $result = $stockLib->create($iostockData, $data, $msg);
            if (!$result) {
                $msg = '出入库失败';

                kernel::database()->rollBack();

                return false;
            }
        }

        $oper           = kernel::single('ome_func')->getDesktopUser();
        $io_update_data = array(
            'iso_status' => self::$iso_status[$io_status],
            'confirm'    => 'Y',
            'operator'   => $oper['op_name'],
            'complete_time' => time(),
        );

        if (!$iostock_update) {
            //是否需要确认
            $io_update_data['defective_status'] = '1'; #未确认

        }
    
        if ($io_status == 'FINISH' && $diff_status) {
            $io_update_data['diff_status'] = $diff_status; #未确认
        }
        
        //是否有备注
        if ($data['memo']) {
            $memo = '';
            if (!$this->_isoInfo['memo']) {
                $memo .= $this->_isoInfo['memo'];
            }
            $memo .= htmlspecialchars($data['memo']);
            $io_update_data['memo'] = $memo;
        }

        if($wmsdata['logi_no']){
            $io_update_data['logi_no'] = $wmsdata['logi_no'];
        }
        if($wmsdata['logi_id']){
            $io_update_data['logi_code'] = $wmsdata['logi_id'];
        }
        $iso_result = $this->_isoObj->update($io_update_data, array('iso_id' => $this->_isoInfo['iso_id'], 'iso_status|notin'=>['3','4']));
        if (is_bool($iso_result)) {
            $msg = '出入库单据状态更新失败';

            kernel::database()->rollBack();
            
            return false;
        }
        kernel::database()->commit();
        if ($io == '0') {
            //减冻结
            $this->clear_stockout_store_freeze($iostockData, $io_status);
        }

        if ($io != '0' && $io_status == 'FINISH') {
            $this->reduceArriveStore();
        }
        if ($this->_isoInfo['type_id'] == '4' && $io_status == 'FINISH') {
            $filter = array('appropriation_no' => $this->_isoInfo['appropriation_no']);
            $approMdl->update(array('process_status' => 5, 'delivery_time' => time()), $filter);
        }
        if ($this->_isoInfo['type_id'] == '40' && $io_status == 'FINISH') {
            #调拔出库且为完成时执行调拔入库
            $stockin_id = kernel::single('console_iostockdata')->allocate_out($this->_isoInfo['iso_id']);
            
            //如果是补货自动审核
            if($stockin_id && in_array($this->_isoInfo['bill_type'],array('transfer','replenishment','o2otransfer','returnnormal'))){
                kernel::single('console_iostockorder')->doCkeck($stockin_id, 1);
            }
        }
        if ($this->_isoInfo['type_id'] == '70' && $io_status == 'FINISH') {
            if($this->_isoInfo['bill_type'] == 'oms_reship_diff') {
                kernel::single('console_reship')->addReshipDiff($this->_isoInfo['iso_id']);
            }
            if ($this->_isoInfo['bill_type'] == 'vopjitrk' && app::get('billcenter')->is_installed()) {
                kernel::single('console_vopreturn')->finishStockin($this->_isoInfo['iso_id']);
            }
        }

        if (app::get('machinecenter')->is_installed()) {
            $this->machineCenterOtherStockAdd($this->_isoInfo['original_id'], $iostockData);
        }

        //JDL处理
        if(in_array($this->_isoInfo['bill_type'],array('jdlreturn')) && $io_status == 'FINISH'){

            kernel::single('ediws_jdlvmi')->dealStockinItems($this->_isoInfo['iso_id']);
            
        }



        if ($io_status == 'FINISH' && $wmsdata['packages']) {

            $this->_processPackage($this->_isoInfo['iso_id'],$wmsdata['packages']);
        }

        return true;

    }

    /**
     *
     * 出入库单取消
     * @param $data 出入库单数据
     * @param $io 出 入标识
     */
    public function cancel($data, $io)
    {
        $iostockdata = $this->_isoInfo;
        $result = $this->_isoObj->update(array('iso_status' => '4'), array('iso_id' => $iostockdata['iso_id'], 'iso_status|notin'=>['3','4']));
        if(is_bool($result)) {
            return false;
        }
        //释放冻结库存
        if ($io == '0') {
            $iostockdata['items'] = $this->_items;
            $this->clear_stockout_store_freeze($iostockdata, '');
        }
        //取消在途
        if ($io != '0') {
            $this->reduceArriveStore();
        }
        return $result;
    }

    /**
     * 检查传过来的货号是否都存在于单据中
     * @param iso_id 出入库单ID
     * @param items array 货品明细
     */
    public function checkBnexist($iso_id, $items)
    {
#taoguaniostockorder_iso

        $bn_array = array();
        foreach ($items as $item) {
            $bn_array[] = $item['bn'];
        }
        $bn_total = count($bn_array);

        $bn_array = '\'' . implode('\',\'', $bn_array) . '\'';

        $iso_items = $this->_isoObj->db->selectrow('SELECT count(iso_items_id) as count FROM sdb_taoguaniostockorder_iso_items WHERE iso_id=' . $iso_id . ' AND bn in (' . $bn_array . ')');

        if ($bn_total != $iso_items['count']) {
#比较数目是否相等
            return false;
        }
        return true;
    }

    /**
     *
     * 检查出入库单是否有效
     * @param  $iso_bn 出入库单编号
     * @param $status 需要执行状态
     * @msg 返回结果
     *
     */
    public function checkValid($iso_bn, $status, &$msg)
    {
        $iso = $this->checkExist($iso_bn);
        if (!$iso) {
            $msg = '单据号不存在!';
            return false;
        }
        $iso_status = $iso['iso_status'];

        switch ($status) {
            case 'PARTIN':
            case 'FINISH':
                if ($iso_status == '3') {
                    $msg = '单据已完成,不可以入库';
                    return false;
                }
                if ($iso_status == '4') {
                    $msg = '单据已取消，不可以入库';
                    return false;
                }
                break;
            case 'CANCEL':
            case 'CLOSE':
                if ($iso_status == '3' || $iso_status == '2') {

                    $msg = '单据已部分或全部入库,不可以取消';
                    return false;
                }
                if ($iso_status == '4') {
                    $msg = '单据已取消，不可以再次取消';
                    return false;
                }
                break;
        }
        return true;
    }

    /**
     * 释放冻结库存
     *
     * array data 当有明细时,操作对应明细，否则操作所有
     */
    public function clear_stockout_store_freeze($data, $io_status = '')
    {
        //$basicMaterialStock    = kernel::single('material_basic_material_stock');
        //$libBranchProduct    = kernel::single('ome_branch_product');

        $branch_id = $data['branch_id'];

        //库存管控处理
        $storeManageLib = kernel::single('ome_store_manage');
        $storeManageLib->loadBranch(array('branch_id' => $branch_id));

        $params              = array();
        $params['node_type'] = 'finishStockout';
        $params['params']    = array('iso_id' => $data['iso_id'], 'branch_id' => $branch_id);

        //释放冻结
        $_items     = $this->_items;
        $productBns = array();
        $items      = $data['items'];
        foreach ((array) $items as $item) {
            if ($item['nums'] <= 0) {
                continue;
            }

            $effective_num = isset($item['effective_num']) ? $item['effective_num'] : ($item['nums'] - $item['normal_num']);
            $num           = 0;
            if ($io_status == 'FINISH' || $io_status == '') {
                $num = $effective_num;
            } else {
                $num = $effective_num > 0 ? $item['nums'] : $effective_num;
            }

            $product_id = $item['product_id'];

            if ($num > 0) {
                $productBns[$item['bn']] = $item['bn'];

                //库存管控处理
                $params['params']['product_id'] = $product_id;
                $params['params']['num']        = $num;
                $params['params']['bn']         = $item['bn'];

                $processResult = $storeManageLib->processBranchStore($params, $err_msg);
            }
        }

        //当状态为全部完成时需将未产生过出入库记录释放冻结
        if ($io_status == 'FINISH') {

            $iostock_list = $this->getIostockList($this->_isoInfo['type_id'], $data['iso_id']);
            foreach ($_items as $_item) {
                $num = 0;
                if ($_item['normal_num'] == 0 && !in_array($_item['bn'], $iostock_list)) {
                    $num = $_item['nums'] - $_item['normal_num'];

                }
                //处理之前部分出库
                if (($_item['normal_num'] > 0 && $_item['nums'] > $_item['normal_num']) && in_array($_item['bn'], $iostock_list) && (in_array($_item['bn'], $productBns) == false)) {

                    $num = $_item['nums'] - $_item['normal_num'];

                }
                if ($num > 0) {
                    //库存管控处理
                    $params['params']['product_id'] = $_item['product_id'];
                    $params['params']['num']        = $num;
                    $params['params']['bn']         = $_item['bn'];

                    $processResult = $storeManageLib->processBranchStore($params, $err_msg);
                }
            }
        }

        //删除预占流水
        if ($io_status == 'FINISH' || $io_status == '') {
            $basicMStockFreezeLib = kernel::single('material_basic_material_stock_freeze');
            $basicMStockFreezeLib->delOtherFreeze($data['iso_id'], material_basic_material_stock_freeze::__STOCKOUT);
        }
    }

    /**
     * 判断出入库明细是否存在
     *
     */
    public function checkExist($io_bn)
    {

        $iso = $this->_isoObj->dump(array('iso_bn' => $io_bn), '*');
        if ($iso) {
            $this->_isoInfo = $iso;

            $iso_id       = $iso['iso_id'];
            $_items       = $this->_itemsObj->getList('iso_id,iso_items_id,defective_num,normal_num,nums,price,product_id,bn', array('iso_id' => $iso_id));
            $this->_items = array();
            foreach ($_items as $k => $item) {
                $this->_items[$item['bn']] = $item;
            }
        }

        return $iso;

    }

    /**
     * 查看差异数据
     */
    public function difference_stock($iso_bn)
    {

        $iso    = $this->_isoObj->dump(array('iso_bn' => $iso_bn), 'iso_id');
        $iso_id = $iso['iso_id'];

        $sql = 'SELECT i.nums,i.normal_num,i.defective_num,i.bn, p.material_name AS name FROM sdb_taoguaniostockorder_iso_items as i
                LEFT JOIN sdb_material_basic_material as p ON i.bn=p.material_bn
                WHERE i.iso_id=' . $iso_id . ' AND (i.normal_num!=i.nums OR i.defective_num>0)';

        $iso_item = $this->_isoObj->db->select($sql);
        return $iso_item;
    }

    public function _format_items($data, $io, &$iostock_update, &$auto_iostock, &$diff_status)
    {
        $itemDetailMdl = app::get('taoguaniostockorder')->model('iso_items_detail');
        $isoItemDetailList = [];
        foreach ($itemDetailMdl->getList('*', array('iso_id' => $this->_isoInfo['iso_id'])) as $detail) {
            if (!$detail['iso_items_id']) continue;

            // num > normal_num + defective_num
            if ($detail['nums'] == $detail['normal_num'] + $detail['defective_num']) {
                continue;
            }

            $detail['extendpro'] = $detail['extendpro'] ? @unserialize($detail['extendpro']) : [];

            $isoItemDetailList[$detail['iso_items_id']][$detail['id']] = $detail;
        }

        $iostock_autoConf = app::get('ome')->getConf('ome.iostock.auto_finish');
        $items            = array();
        $iostock          = array(
            'iso_id'       => $this->_isoInfo['iso_id'],
            'type_id'      => $this->_isoInfo['type_id'],
            'iso_bn'       => $this->_isoInfo['iso_bn'],
            'memo'         => $data['memo'],
            'operate_time' => $data['operate_time'],
            'branch_id'    => $this->_isoInfo['branch_id'],
        );

        foreach ($data['items'] as $item) {

            $num = $io == '0' ? intval($item['num']) : intval($item['normal_num']);

            if ($this->_items[$item['bn']]) {
                $itemdata = array();
                if ($num > 0) {
                    $itemdata['normal_num'] = $this->_items[$item['bn']]['normal_num'] + $num;
                    $effective_num          = $this->_items[$item['bn']]['nums'] - $this->_items[$item['bn']]['normal_num'];
                }
                if ($item['defective_num'] > 0) {
                    $itemdata['defective_num'] = $this->_items[$item['bn']]['defective_num'] + $item['defective_num'];
                    $iostock_update            = false;
                }

                if (($itemdata['normal_num'] + $itemdata['defective_num']) > $this->_items[$item['bn']]['nums']) {
                    //入库单允许超收
                    if ($iostock['type_id'] != '4' && $iostock_autoConf == 'true') {
                        $auto_iostock = true;
                    }
                }
    
                if (($itemdata['normal_num'] + $itemdata['defective_num']) != $this->_items[$item['bn']]['nums']) {
                    $diff_status = '1';
                }

                $new_sn_array = [];
                if ($item['sn_list']) {
                    $old_sn_list = $this->_items[$item['bn']]['sn_list'] ? json_decode($this->_items[$item['bn']]['sn_list'], 1) : [];
                    $old_sn_array = [];
                    if (is_array($old_sn_list) && isset($old_sn_list['sn'])) {
                        $old_sn_array = is_array($old_sn_list['sn']) ? $old_sn_list['sn'] : [$old_sn_list['sn']];
                    } elseif (is_array($old_sn_list) && !isset($old_sn_list['sn'])) {
                        $old_sn_array = $old_sn_list;
                    }
                    if (is_array($item['sn_list']) && isset($item['sn_list']['sn'])) {
                        $new_sn_array = is_array($item['sn_list']['sn']) ? $item['sn_list']['sn'] : [$item['sn_list']['sn']];
                    } elseif (is_array($item['sn_list']) && !isset($item['sn_list']['sn'])) {
                        $new_sn_array = $item['sn_list'];
                    }
                    $merged_sn_array = array_merge($old_sn_array, $new_sn_array);
                    $merged_sn_array = array_filter($merged_sn_array);
                    $itemdata['sn'] = !empty($merged_sn_array) ? implode(',', $merged_sn_array) : '';
                    $new_sn_array = array_filter($new_sn_array);
                }

                $this->_itemsObj->update($itemdata, array('iso_items_id' => $this->_items[$item['bn']]['iso_items_id'], 'iso_id' => $this->_items[$item['bn']]['iso_id']));

                // 更新ISO DETAIL 明细
                if ($item['details'] && $isoItemDetail = $isoItemDetailList[$this->_items[$item['bn']]['iso_items_id']]) {

                    if ($itemdata['normal_num'] == $this->_items[$item['bn']]['nums']) {
                        $itemDetailMdl->update([
                            'normal_num_upset_sql'    => '`nums`',
                        ],[
                            'iso_id' => $this->_items[$item['bn']]['iso_id'],
                            'iso_items_id' => $this->_items[$item['bn']]['iso_items_id'],
                        ]);
                    } elseif ($itemdata['defective_num'] == $this->_items[$item['bn']]['nums']) {
                        $itemDetailMdl->update([
                            'defective_num_upset_sql'    => '`nums`',
                        ],[
                            'iso_id' => $this->_items[$item['bn']]['iso_id'],
                            'iso_items_id' => $this->_items[$item['bn']]['iso_items_id'],
                        ]);
                    } elseif (in_array($this->_isoInfo['bill_type'],['vopjitrk'])) {
                        foreach ($item['details'] as $detail) {
                            $detail['normal_num']    = intval($detail['normal_num']);
                            $detail['defective_num'] = intval($detail['defective_num']);

                            if ($isoItemDetail[$detail['orderLineNo']]) {
                                $itemDetailMdl->update([
                                    'normal_num_upset_sql'    => '`normal_num` + '.$detail['normal_num'],
                                    'defective_num_upset_sql' => '`defective_num` + '.$detail['defective_num'],
                                ],[
                                    'id' => $isoItemDetail[$detail['orderLineNo']]['id'],
                                    'iso_id' => $this->_items[$item['bn']]['iso_id'],
                                    'iso_items_id' => $this->_items[$item['bn']]['iso_items_id'],
                                    'filter_sql' => '`nums` >= (`normal_num` + `defective_num` + '. $detail['normal_num'].' + '.$detail['defective_num'].')',
                                ]);
                            }
                        }
                    }
                }

                if($num>0){
                    $return_sn_list = '';
                    if (!empty($new_sn_array)) {
                        $return_sn_list = json_encode($new_sn_array, JSON_UNESCAPED_UNICODE);
                    } elseif (isset($item['sn_list'])) {
                        $return_sn_list = is_string($item['sn_list']) ? $item['sn_list'] : json_encode($item['sn_list'], JSON_UNESCAPED_UNICODE);
                    }

                    $items[$item['bn']] = array(
                        'bn'            => $item['bn'],
                        'nums'          => $num, #请求入库数量
                        'price'         => $this->_items[$item['bn']]['price'],
                        'iso_items_id'  => $this->_items[$item['bn']]['iso_items_id'],
                        'product_id'    => $this->_items[$item['bn']]['product_id'],
                        'effective_num' => $effective_num,
                        'sn_list'       => $return_sn_list,
                    );

                }
                
                if($item['batch']) {
                    $useLogModel = app::get('console')->model('useful_life_log');
                    $useful = [];
                    foreach ($item['batch'] as $bv) {
                        $tmpUseful = [];
                        $tmpUseful['product_id'] = $this->_items[$item['bn']]['product_id'];
                        $tmpUseful['bn'] = $item['bn'];
                        $tmpUseful['original_bn'] = $this->_isoInfo['iso_bn'];
                        $tmpUseful['original_id'] = $this->_isoInfo['iso_id'];
                        $tmpUseful['business_bn'] = $this->_isoInfo['business_bn'] ? $this->_isoInfo['business_bn'] : $this->_isoInfo['iso_bn'];
                        $tmpUseful['bill_type'] = 'iso';
                        $tmpUseful['sourcetb'] = 'iso';
                        $tmpUseful['create_time'] = time();
                        $tmpUseful['stock_status'] = '0';
                        $tmpUseful['num'] = $bv['num'];
                        $tmpUseful['normal_defective'] = $bv['normal_defective'];
                        $tmpUseful['product_time'] = $bv['product_time']?$bv['product_time']:0;
                        $tmpUseful['expire_time'] = $bv['expire_time']?$bv['expire_time']:0;
                        $tmpUseful['purchase_code'] = $bv['purchase_code'];
                        $tmpUseful['produce_code'] = $bv['produce_code'];
                        $useful[] = $tmpUseful;
                    }
                    $useLogModel->db->exec(ome_func::get_insert_sql($useLogModel, $useful));
                }
            }else{
                //新差异处理使用，暂时跳过（下面代码先注释）
                 continue;
                // todo 表里新增商品明细，$items也拼接上明细
//                if ($iostock['type_id'] == '4') {
//                    $isoItemsMdl    = app::get('taoguaniostockorder')->model("iso_items");
//                    $materialMdl    = app::get('material')->model("basic_material");
//                    $materialExtMdl = app::get('material')->model("basic_material_ext");
//                    $materialInfo   = $materialMdl->db_dump(array('material_bn' => $item['bn']), 'bm_id,material_bn,material_name');
//                    $extInfo        = $materialExtMdl->db_dump(['bm_id' => $materialInfo['bm_id']], 'bm_id,retail_price');
//                    $moreItem       = array(
//                        'iso_id'        => $iostock['iso_id'],
//                        'iso_bn'        => $iostock['iso_bn'],
//                        'product_id'    => $materialInfo['bm_id'],
//                        'product_name'  => $materialInfo['material_name'],
//                        'bn'            => $item['bn'],
//                        'price'         => $extInfo['retail_price'],
//                        'nums'          => 0,
//                        'normal_num'    => $item['normal_num'],
//                        'defective_num' => $item['defective_num'] ? $item['defective_num'] : 0,
//                    );
//                    $isoItemsMdl->insert($moreItem);
//
//                    $items[$item['bn']] = array(
//                        'bn'            => $moreItem['bn'],
//                        'nums'          => $moreItem['normal_num'], #请求入库数量
//                        'price'         => $moreItem['price'],
//                        'iso_items_id'  => $moreItem['iso_items_id'],
//                        'product_id'    => $moreItem['product_id'],
//                        'effective_num' => 0,
//                    );
//                }
            }

        }
        $iostock['items'] = $items;

        return $iostock;

    }

    /**
     * 扣除在途库存
     *
     * @return void
     * @author
     **/
    private function reduceArriveStore()
    {
        $iso              = $this->_isoInfo;
        $items            = $this->_items;
        //取消在途
        $storeManageLib = kernel::single('ome_store_manage');
        $storeManageLib->loadBranch(array('branch_id' => $iso['branch_id']));
        $params                    = array();
        $params['node_type']       = 'deleteArriveStore';
        $params['params']          = array(
            'obj_id' => $iso['iso_id'], 
            'branch_id' => $iso['branch_id'], 
            'obj_type' => 'iostockorder',
        );
        $storeManageLib->processBranchStore($params, $err_msg);
    }

    public function getIostockList($type_id, $iso_id)
    {
        $db    = kernel::database();
        $sql   = "SELECT bn FROM sdb_ome_iostock WHERE type_id in(" . $type_id . ") AND original_id=" . $iso_id . " AND nums>0";
        $items = array();
        $iso   = $db->select($sql);
        foreach ($iso as $v) {
            $items[] = $v['bn'];
        }
        return $items;
    }
    
    //判断转仓单据是否存在 并获取数据
    public function checkExistWarehouse($io_bn){
        $isoObj = app::get('warehouse')->model("iso");
        $itemsObj = app::get('warehouse')->model("iso_items_simple");
        $iso = $isoObj->dump(array('iso_bn'=>$io_bn),'*');
        if ($iso){
            $this->_isoInfo = $iso;
            $iso_id = $iso['iso_id'];
            $_items = $itemsObj->getList('iso_items_simple_id,iso_id,defective_num,normal_num,nums,price,product_id,bn',array('iso_id'=>$iso_id));
            $this->_items= array();
            foreach ($_items as $k=>$item){
                $current_item_key = $item['bn'];
                $this->_items[$current_item_key] = $item;
            }
        }
        return $iso;
    }
    
    /**
     * 检查出仓库单是否有效
     * @param  $iso_bn 出入库单编号
     * @param $status 需要执行状态
     * @msg 返回结果
     */
    public function checkValidWarehouse($iso_bn,$status,&$msg){
        $iso = $this->checkExistWarehouse($iso_bn);
        if (!$iso){
            $msg = '单据号不存在!';
            return false;
        }
        $iso_status = $iso['iso_status'];
        
        switch($status){
            case 'PARTIN':
            case 'FINISH':
                if ($iso_status=='3'){
                    $msg = '单据已完成,不可以入库';
                    return false;
                }
                if ($iso_status == '4'){
                    $msg = '单据已取消，不可以入库';
                    return false;
                }
                break;
            case 'CANCEL':
            case 'CLOSE':
                if ($iso_status=='3' || $iso_status=='2'){
                    $msg = '单据已部分或全部入库,不可以取消';
                    return false;
                }
                if ($iso_status == '4'){
                    $msg = '单据已取消，不可以再次取消';
                    return false;
                }
                break;
        }
        return true;
    }
    
    /**
     *
     * 出入库单取消
     * @param $io_bn 出入库单号
     * @param $io 出 入标识
     */
    public function cancel_warehouse($io_bn){
        $isoObj = app::get('warehouse')->model("iso");
        $result = $isoObj->update(array('iso_status'=>'4'),array('iso_bn'=>$io_bn));
        return $result;
    }
    
    /**
     * 出入库据保存
     * 入库会有残损
     * 出库不会
     * $array $data
     */
    public function do_save_warehouse($data,&$msg){
        set_time_limit(0);
        $iostock_update = true;
        $auto_iostock = false;
        $io_status = $data['io_status'];
        kernel::database()->beginTransaction();

        $iostockData = $this->_format_items_warehouse($data,$iostock_update,$auto_iostock);
        $isoObj = app::get('warehouse')->model("iso");
        if($auto_iostock == true && $this->_isoInfo['iso_status']=='1' && $io_status == 'FINISH'){
            $msg = '因仓库反馈数量大于申请数量,人工介入';
            //$iso__update = array('receive_status'=>console_const::_FINISH_CODE);
            if (!$iostock_update){#是否需要确认
                $iso__update['defective_status'] = '1';#未确认
            }
            $iso_result = $isoObj->update($iso__update,array('iso_id'=>$this->_isoInfo['iso_id']));
            kernel::database()->commit();
            return true;
        }
        $stockLib = kernel::single('siso_receipt_iostock_stockinwarehouse');
        $stockLib->_typeId = $this->_isoInfo['type_id'];
        if($iostockData['items']){
            $result = $stockLib->create($iostockData, $data, $msg);
            if (!$result){
                $msg = '出入库失败';

                kernel::database()->rollBack();

                return false;
            }
        }
        $io_update_data = array('iso_status'=>self::$iso_status[$io_status],'confirm'=>'Y');
        if (!$iostock_update){#是否需要确认
            $io_update_data['defective_status'] = '1';#未确认
        }
        #是否有备注
        if ($data['memo']){
            $memo = '';
            if (!$this->_isoInfo['memo']){
                $memo.= $_isoInfo['memo'];
            }
            $memo.=htmlspecialchars($data['memo']);
            $io_update_data['memo'] = $memo;
        }
        //当完成时更新完成时间
        if($io_status == 'FINISH'){
            $io_update_data["complete_time"] = time();
        }
        $iso_result = $isoObj->update($io_update_data,array('iso_id'=>$this->_isoInfo['iso_id']));
        if (!$iso_result){
            $msg = '出入库单据状态更新失败';

            kernel::database()->rollBack();
            return false;
        }
        kernel::database()->commit();
        return true;
    }
    
    function _format_items_warehouse($data,&$iostock_update,&$auto_iostock){
        $itemsObj = app::get('warehouse')->model("iso_items_simple");
        $iostock_autoConf = app::get('ome')->getConf('ome.iostock.auto_finish');
        $items = array();
        $iostock = array(
            'iso_id'=>$this->_isoInfo['iso_id'],
            'type_id'=>$this->_isoInfo['type_id'],
            'iso_bn'=>$this->_isoInfo['iso_bn'],
            'memo'=>$data['memo'],
            'operate_time'=>$data['operate_time'],
            'branch_id'=>$this->_isoInfo['branch_id'],
        );
        foreach ($data['items'] as $item){
            $num = intval($item['normal_num']);
            $current_item_key = $item['bn'];
            if($this->_items[$current_item_key]){ //存在的数据库明细数据
                $itemdata = array();
                if($num>0){
                    $itemdata['normal_num'] = $this->_items[$current_item_key]['normal_num']+$num;
                }
                if ($item['defective_num']>0){
                    $itemdata['defective_num'] = $this->_items[$current_item_key]['defective_num']+$item['defective_num'];
                    $iostock_update = false;
                }
                if(($itemdata['normal_num']+$itemdata['defective_num'])>$this->_items[$current_item_key]['nums']){
                    if($iostock_autoConf=='true'){
                        $auto_iostock = true;
                    }
                }
                $itemsObj->update($itemdata,array('iso_items_simple_id'=>$this->_items[$current_item_key]['iso_items_simple_id'],'iso_id'=>$this->_items[$current_item_key]['iso_id']));
                $items[$current_item_key] = array(
                    'bn' => $item['bn'],
                    'nums' => $num,#请求入库数量
                    'price' => $this->_items[$current_item_key]['price'],
                    'iso_items_id' => $this->_items[$current_item_key]['iso_items_simple_id'],
                    'product_id' => $this->_items[$current_item_key]['product_id'],
                );
            }
        }
        $iostock['items'] = $items;
        return $iostock;
    }


     function _processPackage($iso_id,$packages){

      

        $bn_packages = $package_data = array();
        foreach ($packages as $value) {
            $bn =$value['bn'];
            if($value['package_code']){
                $bn_packages[$bn]['bn']               = $value['bn'];
                $bn_packages[$bn]['package_code']     = $value['package_code'];
            }
            
        }

        $items       = $this->_itemsObj->getList('iso_id,iso_items_id,nums,product_id,bn', array('iso_id' => $iso_id));

        $detailMdl = app::get('taoguaniostockorder')->model('iso_items_detail');

        $items_details = $detailMdl->getlist('id,bn,extendpro',array('iso_id' => $iso_id));

        if($items_details){
            foreach($items_details as $iv){
                $bn = $iv['bn'];
                $package_code = $bn_packages[$bn] ? $bn_packages[$bn]['package_code'] : '';
                if($package_code){
                    $extendpro = array('package_code'=>$package_code);
                    $extendpro = serialize($extendpro);
                    $updata = array('extendpro'=>$extendpro);

                    $detailMdl->update($updata,array('id'=>$iv['id']));

                }
                
            }

        }else{
           
            foreach($items as $v){
                $bn = $v['bn'];
                if($bn_packages[$bn]){
                    $package_code = $bn_packages[$bn]['package_code'];

                    $extendpro = array('package_code'=>$package_code);
                    $extendpro = serialize($extendpro);
                    $package_data[] = array(

                        'iso_items_id'  =>  $v['iso_items_id'],
                        'iso_id'        =>  $v['iso_id'],
                        'product_id'    =>  $v['product_id'],
                        'product_name'  =>  $v['product_name'],
                        'bn'            =>  $v['bn'],
                        'nums'          =>  $v['nums'],
                        'price'         =>  $v['price'] ? $v['price'] : 0,
                        'extendpro'     =>  $extendpro,

                    );
                }

                
                
            }

            if($package_data){
                $sql = kernel::single('ome_func')->get_insert_sql($detailMdl, $package_data);
                
                $detailMdl->db->exec($sql);
            }
            
        }
        
        //
        

    }

    public function machineCenterOtherStockAdd($original_id, $isoInfo)
    {
        $io = kernel::single('ome_iostock')->getIoByType($isoInfo['type_id']);
        $status = strval($io);
        $iso_id      = $isoInfo['iso_id'];
        $iso_bn      = $isoInfo['iso_bn'];
        $branch_id   = $isoInfo['branch_id'];
        $branchInfo  = app::get('ome')->model('branch')->db_dump($branch_id, 'branch_bn');
        $items       = $isoInfo['items'];
        $snParams    = $this->isoSnParams();

        foreach ($items as $item) {
            $snList = json_decode($item['sn_list']);
            if ($snList) {
                foreach ($snList as $serial) {
                    if (!$serial) {
                        continue;
                    }
                    $bn = $item['bn'];
                    $bm_id = $item['product_id'];
                    $barcode = material_codebase::getBarcodeBybn($bn) ?: '';

                    $tmpSnParams = [];
                    $tmpSnParams['sn']                = $serial;
                    $tmpSnParams['bm_id']             = $bm_id;
                    $tmpSnParams['material_bn']       = $bn;
                    $tmpSnParams['barcode']           = $barcode;
                    $tmpSnParams['imei_number']       = $item['imei_number'] ?? '';
                    $tmpSnParams['order_bn']          = '';
                    $tmpSnParams['branch_id']         = $branch_id;
                    $tmpSnParams['branch_bn']         = $branchInfo['branch_bn'];
                    $tmpSnParams['original_id']       = $iso_id;
                    $tmpSnParams['original_bn']       = $iso_bn;
                    $tmpSnParams['bill_bn']           = $iso_bn;
                    $tmpSnParams['original_type']     = 'iso';
                    $tmpSnParams['status']            = $status;
                    $tmpSnParams['io_status']         = $status;
                    $tmpSnParams['io_time']           = $isoInfo['finish_time'] ?: time();
                    $tmpSnParams['platform_order_bn'] = '';
                   
                    kernel::single('machinecenter_event_receive_sn')->add(array_merge($snParams, $tmpSnParams));
                }
            }
        }
    }

    public function isoSnParams()
    {
        return [
            'buyer_open_id'  => '',
            'buyer_nickname' => '',
            'buyer_name'     => '',
            'buyer_mobile'   => '',
            'buyer_email'    => '',
            'buyer_address'  => '',
            'ship_oaid'      => '',
            'ship_name'      => '',
            'ship_mobile'    => '',
            'ship_email'     => '',
            'ship_address'   => '',
            'ship_province'  => '',
            'ship_city'      => '',
            'ship_district'  => '',
            'ship_town'      => '',
            'ship_village'   => '',
            'user_name'      => '',
            'user_mobile'    => '',
            'user_email'     => '',
            'user_address'   => '',
            'user_province'  => '',
            'user_city'      => '',
            'user_district'  => '',
            'user_town'      => '',
            'user_village'   => '',
        ];
    }
}
