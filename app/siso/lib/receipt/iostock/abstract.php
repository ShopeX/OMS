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
abstract class siso_receipt_iostock_abstract
{
    const PURCH_STORAGE      = 1; //采购入库Purchasing storage
    const PURCH_RETURN       = 10; //采购退货Purchase Returns
    const LIBRARY_SOLD       = 3; //销售出库Library sold
    const RETURN_STORAGE     = 30; //退货入库Return storage
    const RE_STORAGE         = 31; //换货入库Replacement storage
    const REFUSE_STORAGE     = 32; //拒收退货入库Refuse storage
    const ALLOC_STORAGE      = 4; //调拨入库Storage allocation
    const ALLOC_LIBRARY      = 40; //调拨出库Allocation of a library
    const DAMAGED_LIBRARY    = 5; //残损出库Damaged a library
    const DAMAGED_STORAGE    = 50; //残损入库Damaged storage
    const INVENTORY          = 6; //盘亏Inventory shortage
    const OVERAGE            = 60; //盘盈Overage
    const DIRECT_LIBRARAY    = 7; //直接出库 direct Library
    const DIRECT_STORAGE     = 70; //直接入库 direct storage
    const GIFT_LIBRARAY      = 100; //赠品出库 gift Library
    const GIFT_STORAGE       = 200; //赠品入库 gift storage
    const SAMPLE_LIBRARAY    = 300; //样品出库 sample Library
    const SAMPLE_STORAGE     = 400; //样品入库 sample storage
    const DEFAULT_STORE      = 500; //盘点期初入库 inventory
    const STOCK_OTYPE_TZRK   = 8; //调账入库
    const STOCK_OTYPE_TZCK   = 80; //调账出库
    const STOCKDUMP_STORAGE  = 600; //转储入库
    const STOCKDUMP_LIBRARAY = 9; //转储出库
    const DISTR_STORAGE      = 800; //分销入库
    const DISTR_LIBRARAY     = 700; //分销出库
    const VOP_STOCKOUT       = 900; //唯品会出库
    const WAREHOUSE_STORAGE  = 1000;//转仓入库
    
    /**
     * 检查是否统计进销存成本
     *
     * @var string
     **/
    protected $_io_cost = true;

    public $_iostock_types = array(
        '1'   => array('code' => 'I', 'info' => '采购入库', 'io' => 1, 'class' => 'purchase'),
        '10'  => array('code' => 'H', 'info' => '采购退货', 'io' => 0, 'class' => 'purchaseReturn'),
        '3'   => array('code' => 'O', 'info' => '销售出库', 'io' => 0, 'class' => 'sold'),
        '30'  => array('code' => 'M', 'info' => '退货入库', 'io' => 1, 'class' => 'aftersaleReturn'),
        '31'  => array('code' => 'C', 'info' => '换货入库', 'io' => 1, 'class' => 'aftersaleChange'),
        '32'  => array('code' => 'U', 'info' => '拒收退货入库', 'io' => 1, 'class' => 'deliveryRefuse'),
        '4'   => array('code' => 'T', 'info' => '调拨入库', 'io' => 1, 'class' => 'allocationIn'),
        '40'  => array('code' => 'R', 'info' => '调拨出库', 'io' => 0, 'class' => 'allocationOut'),
        '5'   => array('code' => 'B', 'info' => '残损出库', 'is_new' => true, 'io' => 0, 'class' => 'amagedOut'),
        '50'  => array('code' => 'D', 'info' => '残损入库', 'is_new' => true, 'io' => 1, 'class' => 'amagedIn'),
        '6'   => array('code' => 'L', 'info' => '盘亏', 'io' => 0, 'class' => 'shortage'),
        '60'  => array('code' => 'P', 'info' => '盘盈', 'io' => 1, 'class' => 'overage'),
        '7'   => array('code' => 'A', 'info' => '直接出库', 'is_new' => true, 'io' => 0, 'class' => 'directOut'),
        '70'  => array('code' => 'E', 'info' => '直接入库', 'is_new' => true, 'io' => 1, 'class' => 'directIn'),
        '100' => array('code' => 'F', 'info' => '赠品出库', 'is_new' => true, 'io' => 0, 'class' => 'giftOut'),
        '200' => array('code' => 'G', 'info' => '赠品入库', 'is_new' => true, 'io' => 1, 'class' => 'giftIn'),
        '300' => array('code' => 'J', 'info' => '样品出库', 'is_new' => true, 'io' => 0, 'class' => 'sampleOut'),
        '400' => array('code' => 'K', 'info' => '样品入库', 'is_new' => true, 'io' => 1, 'class' => 'sampleIn'),
        '500' => array('code' => 'Q', 'info' => '期初', 'io' => 1),
        '9'   => array('code' => 'W', 'info' => '转储出库', 'io' => 0),
        '600' => array('code' => 'V', 'info' => '转储入库', 'io' => 1),
        '8'   => array('code' => 'N', 'info' => '调账入库', 'io' => 1),
        '80'  => array('code' => 'S', 'info' => '调账出库', 'io' => 0),
        '11'  => array('code' => 'X', 'info' => '调拨入库取消', 'io' => 1, 'is_new' => true),
        '700' => array('code' => 'Z', 'info' => '分销出库', 'io' => 0, 'is_new' => true),
        '800' => array('code' => 'Y', 'info' => '分销入库', 'io' => 1, 'is_new' => true),
        '900' => array('code' => 'VOP', 'info' => '唯品会出库', 'io' => 0, 'is_new' => true, 'class' => 'vopstockout'),
        '1000'=> array('code'=>'DC','info'=>'转仓入库','is_new'=>true,'io'=>1),
    );

    public function set_io_cost($io_cost)
    {
        $this->_io_cost = $io_cost;

        return $this;
    }

    /**
     * 出入库明细创建生成方法
     *
     * @param array $params
     * @param string $msg
     */
    public function create($params, &$data, &$msg = null)
    {
        $dlyDetailMdl = app::get('ome')->model('delivery_items_detail');
        
        //检查参数信息
        if (!$this->checkParams($params, $msg)) {
            return false;
        }

        //获取出入库类型相关数据
        $this->_io_data = $this->get_io_data($params);
        if (!$this->_io_data) {
            $msg = '获取出入库数据失败';
            return false;
        }
        
        //检测仓库是否管控库存
        $isCtrlStore = kernel::single('ome_branch')->getBranchCtrlStore(current($this->_io_data)['branch_id']);
        if ($isCtrlStore === false) {
            $data = $this->_io_data;
            return true;
        }
        
        //校验数据内容
        if (!$this->checkData($this->_io_data, $msg)) {
            $msg = '校验数据内容失败'.$msg;
            return false;
        }
        
        //格式化出入库信息内容
        $error_msg = '';
        $this->_io_data = $this->convertSdf($this->_io_data, $error_msg);
        if (!$this->_io_data) {
            $msg = 'convert data fail.'. $error_msg;
            return false;
        }
        
        //直接出入库数据的保存
        if ($this->save($this->_io_data)) {
            //其他事务处理
            if ($this->_io_cost) {
                $iostock_cost = kernel::service("iostock_cost");
                if (is_object($iostock_cost) && method_exists($iostock_cost, "iostock_set")) {
                    $ioObj = app::get('ome')->model('iostock');
                    $iosInfo = current($this->_io_data);
                    $this->_io_data = $ioObj->getList('*',array('type_id'=>$iosInfo['type_id'],'original_id'=>$iosInfo['original_id'],'original_bn'=>$iosInfo['original_bn'],'iostock_bn'=>$iosInfo['iostock_bn']));
    
                    //获取发货单明细详情关联的订单信息
                    $dlyDetailIds = array_column($this->_io_data, 'original_item_id');
    
                    $deItemList = $dlyDetailMdl->getList('item_detail_id,order_id', array('item_detail_id'=>$dlyDetailIds));
                    $deItemList = array_column($deItemList, null, 'item_detail_id');
    
                    //获取order_id
                    foreach($this->_io_data as $ioKey => $ioVal)
                    {
                        $original_item_id = $ioVal['original_item_id'];
        
                        $this->_io_data[$ioKey]['order_id'] = $deItemList[$original_item_id]['order_id'];
                    }
                    
                    $iostock_cost->iostock_set($this->_io_type, $this->_io_data);
                }
            }
            
            //data
            $data = $this->_io_data;
            
            return true;
        } else {
            $msg = 'save fail:'.kernel::database()->errorinfo();
            return false;
        }
    }
    
    /**
     * 处理有效期
     *
     * @param $iostock_data
     * @param $sourcetb
     * @param $opType
     * @return void
     */
    public function dealBatch($iostock_data, $sourcetb, $opType, $need_normal_defective = false) {
        $filter = [];
        if($need_normal_defective) {
            $filter['normal_defective'] = $this->_typeId==50 ? 'defective' : 'normal';
        }
        $filter['stock_status'] = '0';
        $filter['sourcetb'] = $sourcetb;
        $firstIO = current($iostock_data);
        $filter['original_bn'] = $firstIO['original_bn'];
        $filter['original_id'] = $firstIO['original_id'];
        $filter['bn'] = array_column($iostock_data, 'bn');
        $useLogModel = app::get('console')->model('useful_life_log');
        $batch = $useLogModel->getList('*', $filter);
        if(empty($batch)) {
            return ;
        }
        $iostock_data = array_column($iostock_data, null,'bn');
        foreach ($batch as $k => $v) {
            $upData = [
                'stock_status'=>'1',
                'branch_id'=>$iostock_data[$v['bn']]['branch_id'],
                'stock_time'=>time(),
                'type_id'=>$this->_typeId,
            ];
            $useLogModel->update($upData, ['life_log_id'=>$v['life_log_id']]);
            $batch[$k] = array_merge($v, $upData);
        }
        kernel::single('console_useful_life')->inOutUsefulLife($batch, $opType);
    }

    /**
     *
     * 检查参数
     * @param array $params
     * @param string $msg
     */
    protected function checkParams($params, &$msg)
    {
        return true;
    }

    public function get_io_data($params)
    {

        return '';
    }

    public function cmp_by_product_id($a, $b) {
        if($a['product_id'] == $b['product_id']) {
            return 0;
        }
        return $a['product_id'] < $b['product_id'] ? -1 : 1;
    }
    
    /**
     * 格式化数据
     *
     * @param $data
     * @param $err_msg
     * @return boolean
     */
    private function convertSdf($data, &$err_msg=null)
    {
        $basicMaterialObj   = app::get('material')->model('basic_material');
        $basicMaterialStock = kernel::single('material_basic_material_stock');
        
        $err_msg = '';
        
        foreach ($data as $key => $v) {
            $result = $basicMaterialObj->dump(array('material_bn' => $v['bn']), 'bm_id');
            $data[$key]['product_id'] = $result['bm_id'];
        }
        
        //uasort
        uasort($data, [$this, 'cmp_by_product_id']);
        
        $iostock_bn = $this->get_iostock_bn($this->_typeId);
        $batchList = $branchBatchList = [];
        foreach ($data as $key => $v)
        {
            // $data[$key]['iostock_id'] = $v['iostock_id'] = $this->gen_id();
            $v['create_time']         = time();
            $v['iostock_bn']          = $iostock_bn;
            $v['type_id']             = $this->_typeId;
            
            //开始 SHIPED && 线下订单 order_type  && 仓允许负库存
            $negative_stock = false;
            if (isset($v['negative_stock'])) {
                $negative_stock = $v['negative_stock'];
                unset($v['negative_stock']);
            }
            
            $data[$key] = $v;

            // 库存管控类
            $storeManageLib = kernel::single('ome_store_manage', uniqid());
            $storeManageLib->loadBranch(array('branch_id' => $v['branch_id']));

            //保质期物料_直接更新仓库库存(跳过初始化仓库库存)
            if ($v['is_use_expire']) {
                if ($this->_io_type) {
                    //库存管控(更新基础物料库存)
                    $batchList['+'] = [];
                    $batchList['+'][] = [
                        'bm_id'         =>  $v['product_id'],
                        'num'           =>  $v['nums'],
                        'branch_id'     =>  $v['branch_id'],
                        'iostock_bn'    =>  $iostock_bn,
                    ];
                    $rs = $basicMaterialStock->change_store_batch($batchList['+'], '+', __CLASS__.'::'.__FUNCTION__);
                    if (!$rs[0]) {
                        $err_msg = '更新商品库存失败:'.$rs[1];
                        return false;
                    }
                    // $rs = $basicMaterialStock->change_store($v['product_id'], $v['nums'], '+');
                    // if ($rs == false) {
                    //     $err_msg = '库存管控失败';
                    //     return false;
                    // }
                    
                    // 因为出入库明细表需要获取balance_nums，所以一次处理一个商品
                    $branchBatchList['+'] = [];
                    $branchBatchList['+'][] = [
                        'branch_id'       => $v['branch_id'],
                        'product_id'      => $v['product_id'],
                        'bn'              => $v['bn'],
                        'nums'            => $v['nums'],
                        'iostock_bn'      => $iostock_bn,
                    ];
                    $rs = $storeManageLib->processBranchStore(array(
                        'node_type' => 'changeStoreBatch',
                        'params'    => array(
                            'items'     =>  $branchBatchList['+'],
                            'operator'  => '+',
                        ),
                    ), $err_msg);
                    if ($rs == false) {
                        $err_msg = '更新入库库存失败:'.$err_msg;
                        return false;
                    }
                } else {
                    $batchList['-'] = [];
                    $batchList['-'][] = [
                        'bm_id'         =>  $v['product_id'],
                        'num'           =>  $v['nums'],
                        'branch_id'     =>  $v['branch_id'],
                        'iostock_bn'    =>  $iostock_bn,
                        'negative_stock'=>  $negative_stock,
                    ];
                    $rs = $basicMaterialStock->change_store_batch($batchList['-'], '-', __CLASS__.'::'.__FUNCTION__);
                    if (!$rs[0]) {
                        $err_msg = '更新商品库存失败:'.$rs[1];
                        return false;
                    }
                    // $rs = $basicMaterialStock->change_store($v['product_id'], $v['nums'], '-');
                    // if ($rs == false) {
                    //     $err_msg = '更新仓库库存失败';
                    //     return false;
                    // }

                    // 因为出入库明细表需要获取balance_nums，所以一次处理一个商品
                    $branchBatchList['-'] = [];
                    $branchBatchList['-'][] = [
                        'branch_id'       => $v['branch_id'],
                        'product_id'      => $v['product_id'],
                        'bn'              => $v['bn'],
                        'nums'            => $v['nums'],
                        'iostock_bn'      => $iostock_bn,
                        'negative_stock'  => $negative_stock,
                    ];
                    $rs = $storeManageLib->processBranchStore(array(
                        'node_type' => 'changeStoreBatch',
                        'params'    => array(
                            'items'     =>  $branchBatchList['-'],
                            'operator'  => '-',
                        ),
                    ), $err_msg);
                    if ($rs == false) {
                        $err_msg = '更新出库库存失败:'.$err_msg;
                        return false;
                    }
                }
            } else {
                if ($this->_io_type) {
                    //入库
                    // $rs = $this->updateProduct($v['nums'], $v['product_id'], '+');
                    // if ($rs == false) {
                    //     $err_msg = '更新入库库存失败';
                    //     return false;
                    // }
                    $batchList['+'] = [];
                    $batchList['+'][] = [
                        'bm_id'                 =>  $v['product_id'],
                        'num'                   =>  $v['nums'],
                        'real_store_lastmodify' =>  true,
                        'branch_id'             =>  $v['branch_id'],
                        'iostock_bn'            =>  $iostock_bn,
                    ];
                    $rs = $basicMaterialStock->change_store_batch($batchList['+'], '+', __CLASS__.'::'.__FUNCTION__);
                    if (!$rs[0]) {
                        $err_msg = '更新商品库存失败:'.$rs[1];
                        return false;
                    }
                    
                    // 因为出入库明细表需要获取balance_nums，所以一次处理一个商品
                    $branchBatchList['+'] = [];
                    $branchBatchList['+'][] = [
                        'branch_id'       => $v['branch_id'],
                        'product_id'      => $v['product_id'],
                        'bn'              => $v['bn'],
                        'nums'            => $v['nums'],
                        'iostock_bn'      => $iostock_bn,
                    ];
                    $rs = $storeManageLib->processBranchStore(array(
                        'node_type' => 'changeStoreBatch',
                        'params'    => array(
                            'items'     =>  $branchBatchList['+'],
                            'operator'  => '+',
                        ),
                    ), $err_msg);
                    if ($rs == false) {
                        $err_msg = '更新入库库存失败:'.$err_msg;
                        return false;
                    }
                } else {
                    //出库
                    // $rs = $this->updateProduct($v['nums'], $v['product_id']);
                    // if ($rs == false) {
                    //     $err_msg = '更新出库库存失败';
                    //     return false;
                    // }
                    $batchList['-'] = [];
                    $batchList['-'][] = [
                        'bm_id'                 =>  $v['product_id'],
                        'num'                   =>  $v['nums'],
                        'real_store_lastmodify' =>  true,
                        'iostock_bn'            =>  $iostock_bn,
                        'branch_id'             =>  $v['branch_id'],
                        'negative_stock'        =>  $negative_stock,
                    ];
                    $rs = $basicMaterialStock->change_store_batch($batchList['-'], '-', __CLASS__.'::'.__FUNCTION__);
                    if (!$rs[0]) {
                        $err_msg = '更新商品库存失败:'.$rs[1];
                        return false;
                    }
                    
                    // 因为出入库明细表需要获取balance_nums，所以一次处理一个商品
                    $branchBatchList['-'] = [];
                    $branchBatchList['-'][] = [
                        'branch_id'       => $v['branch_id'],
                        'product_id'      => $v['product_id'],
                        'bn'              => $v['bn'],
                        'nums'            => $v['nums'],
                        'iostock_bn'      => $iostock_bn,
                        'negative_stock'  => $negative_stock,
                    ];
                    $rs = $storeManageLib->processBranchStore(array(
                        'node_type' => 'changeStoreBatch',
                        'params'    => array(
                            'items'     =>  $branchBatchList['-'],
                            'operator'  => '-',
                        ),
                    ), $err_msg);
                    if ($rs == false) {
                        $err_msg = '更新出库库存失败:'.$err_msg;
                        return false;
                    }
                }
            }

            $balance_nums = $storeManageLib->processBranchStore(array(
                'node_type' => 'getStoreByBranch',
                'params'    => array(
                    'from_mysql' => 'true',
                    'branch_id'  => $v['branch_id'],
                    'product_id' => $v['product_id']),

            ), $err_msg);
            
            $data[$key]['balance_nums'] = $balance_nums ? $balance_nums : 0;
        }
        /*
        //库存管控(更新基础物料库存)
        if ($batchList['+']) {
            $rs = $basicMaterialStock->change_store_batch($batchList['+'], '+', __CLASS__.'::'.__FUNCTION__);
            if (!$rs[0]) {
                $err_msg = '更新商品库存失败:'.$rs[1];
                return false;
            }
        }
        if ($batchList['-']) {
            $rs = $basicMaterialStock->change_store_batch($batchList['-'], '-', __CLASS__.'::'.__FUNCTION__);
            if (!$rs[0]) {
                $err_msg = '更新商品库存失败:'.$rs[1];
                return false;
            }
        }
        */

        return $data;
    }

    /**
     *
     * 检查数据内容
     * @param array $data
     * @param string $msg
     * @return boolean
     */
    protected function checkData($data, &$msg)
    {
        if (!$this->check_required($data, $msg)) {

            return false;
        }

        if (!$this->check_value($data, $msg)) {
            return false;
        }
        return true;
    }

    /**
     *
     * 检验必填字段是否全部填写
     * @param array $data
     * @param string $msg
     * @return boolean
     */
    private function check_required($data, &$msg)
    {
        $msg     = array();
        $arrFrom = array('branch_id', 'bn', 'iostock_price', 'nums', 'operator');
        if ($data) {
            foreach ($data as $key => $val) {
                $arrExit = array_keys($val);
                if (count(array_diff($arrFrom, $arrExit))) {
                    $msg[] = $key . '- -所有必填字段';
                }
            }
            
            if (count($msg)) {
                return false;
            }
        }
        return true;
    }

    /**
     *
     * 检验字段类型是否符合要求
     * @param array $data
     * @param string $msg
     * @return boolean
     */
    private function check_value($data, &$msg)
    {
        $msg     = array();
        $rea     = '字段类型不符';
        $type_id = $this->_typeId;

        foreach ($data as $keys => $val) {
            foreach ($val as $key => $value) {
                switch ($key) {
                    case 'iostock_bn':
                        if (!empty($value)) {
                            if (is_string($value) && strlen($value) <= 32) {
                                continue 2;
                            } else {
                                $msg[] = $keys . '-' . $key . '-' . $rea;
                            }
                        }
                        break;
                    case 'original_bn':
                        if (!empty($value)) {
                            if (is_string($value) && strlen($value) <= 255) {
                                continue 2;
                            } else {
                                $msg[] = $keys . '-' . $key . '-' . $rea;
                            }
                        }
                        break;
                    case 'original_id':
                        if (!empty($value)) {
                            if (is_numeric($value) && strlen($value) <= 10 && $value > 0) {
                                continue 2;
                            } else {
                                $msg[] = $keys . '-' . $key . '-' . $rea;
                            }
                        }
                        break;
                    case 'original_item_id':
                        if (!empty($value)) {
                            if (is_numeric($value) && strlen($value) <= 10 && $value > 0) {
                                continue 2;
                            } else {
                                $msg[] = $keys . '-' . $key . '-' . $rea;
                            }
                        }
                        break;
                    case 'supplier_id':
                        if (!empty($value)) {
                            if (is_numeric($value) && strlen($value) <= 10 && $value > 0) {
                                continue 2;
                            } else {
                                $msg[] = $keys . '-' . $key . '-' . $rea;
                            }
                        }
                        break;
                    case 'bn':
                        if (is_string($value) && mb_strlen($value, 'utf-8') <= 200) {
                            continue 2;
                        } else {
                            $msg[] = $keys . '-' . $key . ':' . $value . '(' . mb_strlen($value, 'utf-8') . ')-' . $rea;
                        }
                        break;
                    case 'nums':
                        if ($type_id != '60' && $type_id != '6') {
                            if (is_numeric($value)) {
                                if (strlen($value) <= 8 && $value >= 0) {
                                    continue 2;
                                } else {
                                    $msg[] = $keys . '-' . $key . '-' . $rea;
                                }
                            } else {
                                $msg[] = $keys . '-' . $key . '-' . $rea;
                            }
                        }
                        break;
                    case 'balance_nums':
                        if (is_numeric($value) && strlen($value) <= 8 && $value > 0) {
                            continue 2;
                        } else {
                            $msg[] = $keys . '-' . $key . '-' . $rea;
                        }
                        break;
                    case 'cost_tax':
                        if (!empty($value)) {
                            if (is_numeric($value) && strlen($value) <= 20) {
                                continue 2;
                            } else {
                                $msg[] = $keys . '-' . $key . '-' . $rea;
                            }
                        }
                        break;
                      /*case 'oper':
                      if (!empty($value)) {
                            if (is_string($value) && strlen($value) <= 100) {
                                continue 2;
                            } else {
                                $msg[] = $keys . '-' . $key . '-' . $rea;
                            }
                        }
                        break;

                        case 'operator':
                        if (is_string($value) && strlen($value) <= 30) {
                            continue 2;
                        } else {
                            $msg[] = $keys . '-' . $key . '-' . $rea;
                        }
                        break;*/
                    case 'settle_method':
                        if (!empty($value)) {
                            if (is_string($value) && strlen($value) <= 32) {
                                continue 2;
                            } else {
                                $msg[] = $keys . '-' . $key . '-' . $rea;
                            }
                        }
                        break;
                    case 'settle_status':
                        if (!empty($value)) {
                            if (is_numeric($value) && strlen($value) <= 2) {
                                continue 2;
                            } else {
                                $msg[] = $keys . '-' . $key . '-' . $rea;
                            }
                        }
                        break;
                    case 'settle_operator':
                        if (!empty($value)) {
                            if (is_string($value) && strlen($value) <= 30) {
                                continue 2;
                            } else {
                                $msg[] = $keys . '-' . $key . '-' . $rea;
                            }
                        }
                        break;
                    case 'settle_time':
                        if (!empty($value)) {
                            if (is_numeric($value) && strlen($value) <= 10 && $value > 0) {
                                continue 2;
                            } else {
                                $msg[] = $keys . '-' . $key . '-' . $rea;
                            }
                        }
                        break;
                    case 'settle_num':
                        if (!empty($value)) {
                            if (is_numeric($value) && strlen($value) <= 8 && $value > 0) {
                                continue 2;
                            } else {
                                $msg[] = $keys . '-' . $key . '-' . $rea;
                            }
                        }
                        break;
                    case 'settlement_bn':
                        if (!empty($value)) {
                            if (is_string($value) && strlen($value) <= 32) {
                                continue 2;
                            } else {
                                $msg[] = $keys . '-' . $key . '-' . $rea;
                            }
                        }
                        break;
                    case 'settlement_money':
                        if (!empty($value)) {
                            if (is_numeric($value) && strlen($value) <= 20) {
                                continue 2;
                            } else {
                                $msg[] = $keys . '-' . $key . '-' . $rea;
                            }
                        }
                        break;
                }
            }
        }

        if (!count($msg)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     *
     * 出入库明细保存
     */
    public function save($data)
    {
        $ioObj = app::get('ome')->model('iostock');
        $sql   = ome_func::get_insert_sql($ioObj, $data);
        if (kernel::database()->exec($sql)) {
            return true;
        } else {
            return false;
        }
    }

    public function gen_id()
    {
        list($msec, $sec) = explode(" ", microtime());
        $id               = $sec . strval($msec * 1000000);
        $conObj           = app::get('ome')->model('concurrent');
        if ($conObj->is_pass($id, 'iostock')) {
            return $id;
        } else {
            return $this->gen_id();
        }
    }

    /**
     * 初始化库存数量为NULL的货品
     */
    public function initNullStore($table, $product_id, $branch_id)
    {
        if ($product_id && $table) {
            if ($branch_id) {
                $sql = "UPDATE $table SET store=0 WHERE branch_id='" . $branch_id . "' AND product_id='" . $product_id . "' AND ISNULL(store) LIMIT 1";
            } else {
                $sql = "UPDATE $table SET store=0 WHERE product_id='" . $product_id . "' AND ISNULL(store) LIMIT 1";
            }
            return kernel::database()->exec($sql);
        } else {
            return false;
        }
    }
    
    //更新仓库库存
    public function updateBranchProduct($num, $product_id, $branch_id, $operator = '-', $bn, $iostock_bn='')
    {
        $libBranchProduct = kernel::single('ome_branch_product');

        //初始化仓库库存
        $libBranchProduct->initNullStore($product_id, $branch_id);

        // //更新仓库库存
        // $result = $libBranchProduct->change_store($branch_id, $product_id, $num, $operator, false);

        #更新仓库库存
        if ($num == 0) {
            return true;
        }
        $items = [[
            'branch_id'     =>  $branch_id,
            'product_id'    =>  $product_id,
            'quantity'      =>  $num,
            'bn'            =>  $bn,
            'iostock_bn'    =>  $iostock_bn,
        ]];
        $rs  = ome_branch_product::storeInRedis($items, $operator, __CLASS__.'::'.__FUNCTION__);
        $result  = $rs[0];
        $err_msg = $rs[1];
        return $result;
    }

    //更新基础物料库存
    // 废弃，用change_store_batch处理
    public function updateProduct($num, $product_id, $operator = '-')
    {
        return false;
        return false;
        return false;

        $basicMaterialStock = kernel::single('material_basic_material_stock');
    
        //初始化基础物料库存
        $basicMaterialStock->initNullStore($product_id);
    
        //更新基础物料库存
        if ($operator == '-') {
            $sql = "UPDATE sdb_material_basic_material_stock SET store=IF(store<" . $num . ",0,store-$num),last_modified=" . time() . ",real_store_lastmodify=" . time() . ",max_store_lastmodify=" . time() . " WHERE bm_id='" . $product_id . "'";
        } else {
            $sql = "UPDATE sdb_material_basic_material_stock SET store=store+" . $num . ",last_modified=" . time() . ",real_store_lastmodify=" . time() . ",max_store_lastmodify=" . time() . ",inc_store_lastmodify=" . time() . " WHERE bm_id='" . $product_id . "'";
        }

        return kernel::database()->exec($sql);
    }
    
    //获取基础物料对应仓库库存
    public function get_branch_store($branch_id, $product_id)
    {
        $libBranchProduct = kernel::single('ome_branch_product');

        $store = $libBranchProduct->getStoreByBranch($product_id, $branch_id);
        return $store;
    }

    /**
     * 生成出入库单号
     * $type 类型 如：iostock-1
     **/
    public function get_iostock_bn($type, $num = 0)
    {
        $kt           = $this->iostock_rules($type);
        $iostock_type = 'iostock-' . $type;

        $prefix = $kt . date('Ymd');
        $sign   = kernel::single('eccommon_guid')->incId($iostock_type, $prefix, 6, true);
        
        return $sign;
    }

    /**
     * 出入库类型标识
     * $rules 出入库类型编号 如：30
     */
    public function iostock_rules($rules)
    {
        return $this->_iostock_types[$rules]['code'];
    }

    public function get_iostock_types()
    {
        return $this->_iostock_types;
    }

    public function getIoByType($type)
    {
        if (isset($this->_iostock_types[$type])) {
            return $this->_iostock_types[$type]['io'];
        } else {
            return 1;
        }
    }
}
