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
class ome_iostock{
    const PURCH_STORAGE = 1;   //采购入库Purchasing storage
    const PURCH_RETURN = 10;   //采购退货Purchase Returns
    const LIBRARY_SOLD = 3;    //销售出库Library sold
    const RETURN_STORAGE = 30; //退货入库Return storage
    const RE_STORAGE = 31;     //换货入库Replacement storage
    const REFUSE_STORAGE = 32; //拒收退货入库Refuse storage
    const ALLOC_STORAGE = 4;   //调拨入库Storage allocation
    const ALLOC_LIBRARY = 40;  //调拨出库Allocation of a library
    const DAMAGED_LIBRARY = 5; //残损出库Damaged a library
    const DAMAGED_STORAGE = 50;//残损入库Damaged storage
    const INVENTORY = 6;       //盘亏Inventory shortage
    const OVERAGE = 60;        //盘盈Overage
    const DIRECT_LIBRARAY = 7;       //直接出库 direct Library
    const DIRECT_STORAGE = 70;        //直接入库 direct storage
    const GIFT_LIBRARAY = 100;       //赠品出库 gift Library
    const GIFT_STORAGE = 200;        //赠品入库 gift storage
    const SAMPLE_LIBRARAY = 300;       //样品出库 sample Library
    const SAMPLE_STORAGE = 400;        //样品入库 sample storage
    const DEFAULT_STORE = 500;       //盘点期初入库 inventory
    //调账入库
	const STOCK_OTYPE_TZRK = 8;
	//调账出库
	const STOCK_OTYPE_TZCK = 80;
    const STOCKDUMP_STORAGE = 600;//转储入库
    const STOCKDUMP_LIBRARAY = 9;//转储出库
    const DISTR_STORAGE = 800;//分销入库
    const DISTR_LIBRARAY = 700;//分销出库

    //唯品会出库
    const VOP_STOCKOUT = 900;
    
    //转仓入库
    const WAREHOUSE_STORAGE = 1000;
    var $iostock_types = array(
                    '1'=>array('code'=>'I','info'=>'采购入库','io'=>1),
 					'10'=>array('code'=>'H','info'=>'采购退货','io'=>0),
			 		'3'=>array('code'=>'O','info'=>'销售出库','io'=>0),
			 		'30'=>array('code'=>'M','info'=>'退货入库','io'=>1),
			 		'31'=>array('code'=>'C','info'=>'换货入库','io'=>1),
    		        '32'=>array('code'=>'U','info'=>'拒收退货入库','io'=>1),
			 		'4'=>array('code'=>'T','info'=>'调拨入库','io'=>1),
			 		'40'=>array('code'=>'R','info'=>'调拨出库','io'=>0),
			 		'5'=>array('code'=>'B','info'=>'残损出库','is_new'=>true,'io'=>0),
			 		'50'=>array('code'=>'D','info'=>'残损入库','is_new'=>true,'io'=>1),
			 		'6'=>array('code'=>'L','info'=>'盘亏','io'=>0),
			 		'60'=>array('code'=>'P','info'=>'盘盈','io'=>1),
    				'7'=>array('code'=>'A','info'=>'直接出库','is_new'=>true,'io'=>0),
			 		'70'=>array('code'=>'E','info'=>'直接入库','is_new'=>true,'io'=>1),
			 		'100'=>array('code'=>'F','info'=>'赠品出库','is_new'=>true,'io'=>0),
			 		'200'=>array('code'=>'G','info'=>'赠品入库','is_new'=>true,'io'=>1),
			 		'300'=>array('code'=>'J','info'=>'样品出库','is_new'=>true,'io'=>0),
			 		'400'=>array('code'=>'K','info'=>'样品入库','is_new'=>true,'io'=>1),
                    '500'=>array('code'=>'Q','info'=>'期初','io'=>1),
                    '600'=>array('code'=>'V','info'=>'转储入库','io'=>1),
                    '9'=>array('code'=>'W','info'=>'转储出库','io'=>0),
                    '8'=>array('code'=>'N','info'=>'调账入库','io'=>1),
                    '80'=>array('code'=>'S','info'=>'调账出库','io'=>0),
                    '11'=>array('code'=>'X','info'=>'调拨入库取消','is_new'=>true,'io'=>1),
                    '700'=>array('code'=>'Z','info'=>'分销出库','is_new'=>true,'io'=>0),
                    '800'=>array('code'=>'Y','info'=>'分销入库','is_new'=>true,'io'=>1),
                    '900'=>array('code'=>'VOP','info'=>'唯品会出库','io'=>0, 'class'=>'vopstockout'),
                    '1000'=>array('code'=>'DC','info'=>'转仓入库','is_new'=>true,'io'=>1),
    );

	/**
	*功能插入数据
	*(已废弃)
	**/
    function set($iostock_bn,&$data,$type,&$msg=null,$io=1)
    {
        $basicMaterialObj = app::get('material')->model('basic_material');

        if($data)
        {
             $ioObj = app::get('ome')->model('iostock');
             $branchObj = app::get('ome')->model('branch_product');

            foreach($ioObj->schema['columns'] as $key=>$value){
               $columns[] = $key;
            }
            $this->columns = $columns;

            if(!$this->check_required($data,$msg)){

                return false;
            }

            if($this->check_value($data,$msg)){

                $batchList = [];
                foreach($data as $key=>$v){
                    $data[$key]['iostock_id'] = $v['iostock_id'] = $this->gen_id();
                    $v['create_time'] = time();
                    $v['iostock_bn'] = $iostock_bn;
                    $v['type_id'] = $type;
                    $data[$key] = $v;

                    #基础物料
                    $bMaterialRow    = $basicMaterialObj->dump(array('material_bn'=>$v['bn']), 'bm_id');

                    $v['product_id'] = $bMaterialRow['bm_id'];

                    //如果仓库无此货品，则先创建一个此仓库的货品
                    $branch_goods  = $branchObj->dump(array('branch_id'=>$v['branch_id'],'product_id'=>$v['product_id']),'*');
                    if(!$branch_goods){
                        $branch_arr['branch_id'] = $v['branch_id'];
                        $branch_arr['product_id'] = $v['product_id'];
                        $branch_arr['store'] = 0;
                        $branch_arr['last_modified'] = time();
                        $branchObj->insert($branch_arr);
                    }
                    if( $io ){ //入库
                        $batchList['+'] = [];
                        $batchList['+'][] = [
                            'num'           =>  $v['nums'],
                            'product_id'    =>  $v['product_id'],
                            'bn'            =>  $v['bn'],
                            'branch_id'     =>  $v['branch_id'],
                            'iostock_bn'    =>  $iostock_bn,
                        ];
                        // $this->updateProduct($v['nums'],$v['product_id'],'+');
                        // 因为出入库明细表需要获取balance_nums，所以一次处理一个商品
                        $this->updateBranchProductBatch($batchList['+'], '+', $err);
                    } else { //出库
                        $batchList['-'] = [];
                        $batchList['-'][] = [
                            'num'           =>  $v['nums'],
                            'product_id'    =>  $v['product_id'],
                            'bn'            =>  $v['bn'],
                            'branch_id'     =>  $v['branch_id'],
                            'iostock_bn'    =>  $iostock_bn,
                        ];
                        // $this->updateProduct($v['nums'],$v['product_id']);
                        // 因为出入库明细表需要获取balance_nums，所以一次处理一个商品
                        $this->updateBranchProductBatch($batchList['-'], '-', $err);
                    }

                    $data[$key]['balance_nums'] = $this->get_branch_store($v['branch_id'],$v['product_id']);
                    $data[$key]['balance_nums'] = $data[$key]['balance_nums'] ? $data[$key]['balance_nums'] : 0;
                }

                $sql = ome_func::get_insert_sql($ioObj,$data);

                if ( kernel::database()->exec($sql) ){
                    kernel::single('tgstockcost_instance_router')->iostock_set($io,$data);
                    return true;
                }else{
                    return false;
                }
            }
        }
        return false;
    }


    function gen_id(){
        list($msec, $sec) = explode(" ",microtime());
        $msec = substr((string) $msec,(strpos((string) $msec, '.')+1),6);
        $msec = str_pad($msec,6,0);
        $id = $sec.$msec;
        //$id = $sec.strval($msec*1000000);
        $conObj = app::get('ome')->model('concurrent');
        if($conObj->is_pass($id,'iostock')){
            return $id;
        } else {
            return $this->gen_id();
        }
    }

    /**
     * 初始化库存数量为NULL的货品
     * 
     * 已弃用
     */
    public function initNullStore($table,$product_id,$branch_id){
        if($product_id && $table) {
            if($branch_id) {
                $sql = "UPDATE $table SET store=0 WHERE branch_id='" . $branch_id . "' AND product_id='" . $product_id ."' AND ISNULL(store) LIMIT 1";
            }else{
                $sql = "UPDATE $table SET store=0 WHERE product_id='" . $product_id ."' AND ISNULL(store) LIMIT 1";
            }
            return kernel::database()->exec($sql);
        }else{
            return false;
        }
    }

    //更新仓库库存
    // 弃用，redis库存高可用，改用本类的updateBranchProductBatch方法
    function updateBranchProduct($num, $product_id, $branch_id, $operator='-')
    {
        return false;
        return false;
        return false;

        $libBranchProduct    = kernel::single('ome_branch_product');
    
        #初始化仓库库存
        $libBranchProduct->initNullStore($product_id, $branch_id);

        #更新仓库库存
        $result    = $libBranchProduct->change_store($branch_id, $product_id, $num, $operator, false);

        return $result;
    }

    //更新仓库以及商品库存
    // redis库存高可用，迭代本类updateBranchProduct方法
    private function updateBranchProductBatch($items, $operator='-', &$err_msg='')
    {
        $libBranchProduct    = kernel::single('ome_branch_product');
        $basicMaterialStock  = kernel::single('material_basic_material_stock');

        $newItems = $batchList = [];
        foreach ($items as $_item) {
            $num         =  $_item['num'];
            $product_id  =  $_item['product_id'];
            $bn          =  $_item['bn'];
            $branch_id   =  $_item['branch_id'];
            $iostock_bn  =  $_item['iostock_bn'] ? $_item['iostock_bn'] : '';

            #初始化仓库库存
            $libBranchProduct->initNullStore($product_id, $branch_id);

            if ($num == 0) {
                continue;
            }
            #更新仓库库存
            $newItems[] = [
                'branch_id'     =>  $branch_id,
                'product_id'    =>  $product_id,
                'quantity'      =>  $num,
                'bn'            =>  $bn,
                'iostock_bn'    =>  $iostock_bn,
            ];

            $batchList[] = [
                'bm_id'                 =>  $product_id,
                'num'                   =>  $num,
                'real_store_lastmodify' =>  true,
                'branch_id'             =>  $branch_id,
                'iostock_bn'            =>  $iostock_bn,
            ];
        }
        $basicMaterialStock->change_store_batch($batchList, $operator, __CLASS__.'::'.__FUNCTION__);

        $rs  = ome_branch_product::storeInRedis($newItems, $operator, __CLASS__.'::'.__FUNCTION__);
        $result  = $rs[0];
        $err_msg = $rs[1];
        return $result;
    }

    //更新基础物料库存
    // 废弃，集成到updateBranchProductBatch中的change_store_batch
    function updateProduct($num, $product_id,$operator='-')
    {
        return false;
        return false;
        return false;

        $basicMaterialStock    = kernel::single('material_basic_material_stock');
        
        #初始化基础物料库存
        $basicMaterialStock->initNullStore($product_id);

        #更新基础物料库存
        if($operator == '-'){
             $sql = "UPDATE sdb_material_basic_material_stock SET store=IF(store<".$num.",0,store-$num),last_modified=" . time().",real_store_lastmodify=" .time(). ",max_store_lastmodify=" .time(). " WHERE bm_id='" . $product_id . "'";
        } else {
             $sql = "UPDATE sdb_material_basic_material_stock SET store=store+" . $num . ",last_modified=" . time().",real_store_lastmodify=" .time(). ",max_store_lastmodify=" .time(). ",inc_store_lastmodify=" . time() . " WHERE bm_id='" . $product_id . "'";
        }
        return kernel::database()->exec($sql);
    }

    //获取基础物料对应仓库库存
    function get_branch_store($branch_id, $product_id)
    {
        $libBranchProduct    = kernel::single('ome_branch_product');
    
        $store    = $libBranchProduct->getStoreByBranch($product_id, $branch_id);
        return $store;
    }

    /**
     * 检验必填字段是否全部填写
     *
     **/
    function check_required($data,&$msg){
        $msg = array();
        $arrFrom = array('branch_id','bn','iostock_price','nums','operator');
        if($data){
            foreach($data as $key=>$val){
                $arrExit = array_keys($val);
                if( count(array_diff($arrFrom,$arrExit)) ){
                   $msg[] =$key . '- -所有必填字段';
                }
            }
            if(count($msg)){
                return false;
            }
        }
        return true;
    }

    /**
     * 检验字段类型是否符合要求
     * 
     * @param unknown $data
     * @param unknown $msg
     * @return boolean
     */
    function check_value($data,&$msg){
        $msg = array();
        $rea = '字段类型不符';

        foreach($data as $keys=>$val){
            foreach($val as $key=>$value){
                switch($key){
                    case 'iostock_bn':
                        if(!empty($value)){
                            if(is_string($value) && strlen($value)<=32){
                            } else{
                                $msg[] = $keys .'-'. $key.'-'.$rea;
                            }
                        }
                        break;
                    case 'original_bn':
                        if(!empty($value)){
                            if(is_string($value) && strlen($value)<=255){
                            } else{
                                $msg[] = $keys .'-'. $key.'-'.$rea;
                            }
                        }
                        break;
                    case 'original_id':
                        if(!empty($value)){
                            if(is_numeric($value) && strlen($value)<=10 && $value>0){
                            } else{
                                $msg[] = $keys .'-'. $key.'-'.$rea;
                            }
                        }
                        break;
                    case 'original_item_id':
                       if(!empty($value)){
                            if(is_numeric($value) && strlen($value)<=10 && $value>0){
                            } else{
                                $msg[] = $keys .'-'. $key.'-'.$rea;
                            }
                        }
                        break;
                    case 'supplier_id':
                        if(!empty($value)){
                            if(is_numeric($value) && strlen($value)<=10 && $value>0){
                            } else{
                                $msg[] = $keys .'-'. $key.'-'.$rea;
                            }
                        }
                        break;
                    case 'bn':
                        if(is_string($value) && mb_strlen($value,'utf-8')<=32){
                        } else{
                            $msg[] = $keys .'-'. $key.':'.$value.'('.mb_strlen($value,'utf-8').')-'.$rea;
                        }
                        break;
                    case 'nums':
                        if(is_numeric($value) && strlen($value)<=8 && $value>0){
                        } else{
                            $msg[] = $keys .'-'. $key.'-'.$rea;
                        }
                        break;
                    case 'balance_nums':
                        if(is_numeric($value) && strlen($value)<=8 && $value>0){
                        } else{
                            $msg[] = $keys .'-'. $key.'-'.$rea;
                        }
                        break;
                    case 'cost_tax':
                        if(!empty($value)){
                            if(is_numeric($value) && strlen($value)<=20){
                            } else{
                                $msg[] = $keys .'-'. $key.'-'.$rea;
                            }
                        }
                        break;
                    case 'oper':
                        if(!empty($value)){
                            if($value && strlen($value)<=100){
                            } else{
                                $msg[] = $keys .'-'. $key.'-'.$rea;
                            }
                        }
                        break;
                    case 'operator':
                        if($value && strlen($value)<=100){
                        } else{
                            $msg[] = $keys .'-'. $key.'-'.$rea;
                        }
                        break;
                    case 'settle_method':
                        if(!empty($value)){
                            if(is_string($value) && strlen($value)<=32){
                            } else{
                                $msg[] = $keys .'-'. $key.'-'.$rea;
                            }
                        }
                        break;
                    case 'settle_status':
                        if(!empty($value)){
                            if(is_numeric($value) && strlen($value)<=2){
                            } else{
                                $msg[] = $keys .'-'. $key.'-'.$rea;
                            }
                        }
                        break;
                    case 'settle_operator':
                        if(!empty($value)){
                            if(is_string($value) && strlen($value)<=30){
                            } else{
                                $msg[] = $keys .'-'. $key.'-'.$rea;
                            }
                        }
                        break;
                    case 'settle_time':
                        if(!empty($value)){
                            if(is_numeric($value) && strlen($value)<=10 && $value>0){
                            } else{
                                $msg[] = $keys .'-'. $key.'-'.$rea;
                            }
                        }
                        break;
                    case 'settle_num':
                        if(!empty($value)){
                            if(is_numeric($value) && strlen($value)<=8 && $value>0){
                            } else{
                                $msg[] = $keys .'-'. $key.'-'.$rea;
                            }
                        }
                        break;
                    case 'settlement_bn':
                        if(!empty($value)){
                            if(is_string($value) && strlen($value)<=32){
                            } else{
                                $msg[] = $keys .'-'. $key.'-'.$rea;
                            }
                        }
                        break;
                    case 'settlement_money':
                        if(!empty($value)){
                            if(is_numeric($value) && strlen($value)<=20){
                            } else{
                                $msg[] = $keys .'-'. $key.'-'.$rea;
                            }
                        }
                        break;
                }
            }
        }

        if(!count($msg)){
            return true;
        } else {
            return false;
        }
    }

    /**
     * 生成出入库单号
     * 
     * @param unknown $type
     * @param number $num
     * @return unknown
     */
    function get_iostock_bn($type,$num = 0){
        $kt = $this->iostock_rules($type);
        $iostock_type = 'iostock-'.$type;

        $prefix = $kt.date('Ymd');
        $sign = kernel::single('eccommon_guid')->incId($iostock_type, $prefix, 6, true);
        return $sign;
    }
    
    /**
     * 出入库类型标识
     * 
     * @param unknown $rules
     */
    function iostock_rules($rules){
    	return $this->iostock_types[$rules]['code'];
    }

 	function get_iostock_types(){
 		return $this->iostock_types;
    }

    function getIoByType($type){
    	if(isset($this->iostock_types[$type])){
    		return $this->iostock_types[$type]['io'];
    	}else{
    		return 1;
    	}
    }

    /**
     * 获取出入库类型
     *
     * @return array
     * @author chenping@shopex.cn
     * @since PHP74
     **/
    public function getTypeId($io = null)
    {
        if (is_null($io)) {
            return array_keys($this->iostock_types);
        }

        if ($io == '1' || $io == '0') {
            //return array_keys(array_filter($this->iostock_types, fn ($t) => $t['io'] == $io));
        }


        return [0];
    }
}
