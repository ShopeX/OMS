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
class taoguaninventory_iostock{
    const PURCH_STORAGE = 1;   //采购入库Purchasing storage
    const PURCH_RETURN = 10;   //采购退货Purchase Returns
    const LIBRARY_SOLD = 3;    //销售出库Library sold
    const RETURN_STORAGE = 30; //退货入库Return storage
    const RE_STORAGE = 31;     //换货入库Replacement storage
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
    const DEFAULT_STORE = 500;        //期初入库 default storage

    var $iostock_types = array('1'=>array('code'=>'I','info'=>'采购入库','io'=>1),
 					'10'=>array('code'=>'H','info'=>'采购退货','io'=>0),
			 		'3'=>array('code'=>'O','info'=>'销售出库','io'=>0),
			 		'30'=>array('code'=>'M','info'=>'退货入库','io'=>1),
			 		'31'=>array('code'=>'C','info'=>'换货入库','io'=>1),
			 		'4'=>array('code'=>'T','info'=>'调拨入库','io'=>1),
			 		'40'=>array('code'=>'R','info'=>'调拨出库','io'=>0),
			 		'5'=>array('code'=>'B','info'=>'残损出库','io'=>0),
			 		'50'=>array('code'=>'D','info'=>'残损入库','io'=>1),
			 		'6'=>array('code'=>'L','info'=>'盘亏','io'=>0),
			 		'60'=>array('code'=>'P','info'=>'盘盈','io'=>1),
    				'7'=>array('code'=>'A','info'=>'直接出库','is_new'=>true,'io'=>0),
			 		'70'=>array('code'=>'E','info'=>'直接入库','is_new'=>true,'io'=>1),
			 		'100'=>array('code'=>'F','info'=>'赠品出库','is_new'=>true,'io'=>0),
			 		'200'=>array('code'=>'G','info'=>'赠品入库','is_new'=>true,'io'=>1),
			 		'300'=>array('code'=>'J','info'=>'样品出库','is_new'=>true,'io'=>0),
			 		'400'=>array('code'=>'K','info'=>'样品入库','is_new'=>true,'io'=>1),
      '500'=>array('code'=>'Q','info'=>'期初','io'=>1),
 		);

	/**
	*功能插入数据
	*
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
                    
                    #基础物料信息
                    $result = $basicMaterialObj->dump(array('material_bn'=>$v['bn']), '*');
                    
                    if(!$result){
                        return false;

                    }
                    $v['product_id'] = $result['bm_id'];

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

                $result = kernel::database()->exec($sql);

                if ( $result ){
                    $iostock_cost = kernel::service("iostock_cost");
                    if(is_object($iostock_cost) && method_exists($iostock_cost,"iostock_set"))
                    {
                        $iostock_cost->iostock_set($io,$data);
                    }
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
        $id = $sec.strval($msec*1000000);
        $conObj = app::get('ome')->model('concurrent');
        if($conObj->is_pass($id,'iostock')){
            return $id;
        } else {
            return $this->gen_id();
        }
    }

    /**
     * 初始化库存数量为NULL的货品
     */
    public function initNullStore($table,$product_id,$branch_id){
        if($product_id && $table) {
            if($branch_id) {
                $sql = "UPDATE $table SET store=0 WHERE branch_id='" . $branch_id . "' AND product_id='" . $product_id ."' AND ISNULL(store) LIMIT 1";
            }else{
                $sql = "UPDATE $table SET store=0 WHERE product_id='" . $product_id ."' AND ISNULL(store) LIMIT 1";
            }

            $result = kernel::database()->exec($sql);

            return $result;
        }else{
            return false;
        }
    }

    #更新仓库库存
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

    #更新仓库和商品库存
    // redis库存高可用，迭代本类updateBranchProduct方法
    private function updateBranchProductBatch($_items, $operator='-', &$err_msg='')
    {
        $libBranchProduct    = kernel::single('ome_branch_product');#基础物料 ExBOY
        $basicMaterialStock  = kernel::single('material_basic_material_stock');

        $items = $batchList = [];
        foreach ($_items as $_item) {
            $num         =  $_item['num'];
            $product_id  =  $_item['product_id'];
            $bn          =  $_item['bn'];
            $branch_id   =  $_item['branch_id'];
            $iostock_bn  =  $_item['iostock_bn'] ? $_item['iostock_bn'] : '';

            #初始化仓库库存
            $libBranchProduct->initNullStore($product_id, $branch_id);

            #更新仓库库存
            if ($num == 0) {
                continue;
            }
            $items[] = [
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

        $rs  = ome_branch_product::storeInRedis($items, $operator, __CLASS__.'::'.__FUNCTION__);
        $result  = $rs[0];
        $err_msg = $rs[1];
        return $result;
    }
    
    #更新基础物料库存
    // 废弃，集成到updateBranchProductBatch中的change_store_batch
    function updateProduct($num, $product_id, $operator='-')
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
    
    #获取基础物料对应仓库库存
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
*检验字段类型是否符合要求
*
**/
    function check_value($data,&$msg){
        $msg = array();
        $rea = '字段类型不符';
        foreach($data as $keys=>$val){
            foreach($val as $key=>$value){
                switch($key){
                    case 'iostock_bn':
                        if(!empty($value)){
                            if(is_string($value) && strlen($value)<=32){
                                continue;
                            } else{
                                $msg[] = $keys .'-'. $key.'-'.$rea;
                            }
                        }
                        break;
                    case 'original_bn':
                        if(!empty($value)){
                            if(is_string($value) && strlen($value)<=32){
                                continue;
                            } else{
                                $msg[] = $keys .'-'. $key.'-'.$rea;
                            }
                        }
                        break;
                    case 'original_id':
                        if(!empty($value)){
                            if(is_numeric($value) && strlen($value)<=10 && $value>0){
                                continue;
                            } else{
                                $msg[] = $keys .'-'. $key.'-'.$rea;
                            }
                        }
                        break;
                    case 'original_item_id':
                       if(!empty($value)){
                            if(is_numeric($value) && strlen($value)<=10 && $value>0){
                                continue;
                            } else{
                                $msg[] = $keys .'-'. $key.'-'.$rea;
                            }
                        }
                        break;
                    case 'supplier_id':
                        if(!empty($value)){
                            if(is_numeric($value) && strlen($value)<=10 && $value>0){
                                continue;
                            } else{
                                $msg[] = $keys .'-'. $key.'-'.$rea;
                            }
                        }
                        break;
                    case 'bn':
                        if(is_string($value) && mb_strlen($value,'utf-8')<=32){
                            continue;
                        } else{
                            $msg[] = $keys .'-'. $key.':'.$value.'('.mb_strlen($value,'utf-8').')-'.$rea;
                        }
                        break;
                    case 'nums':
//                        if(is_numeric($value) && strlen($value)<=8 && $value>0){
//                            continue;
//                        } else{
//                            $msg[] = $keys .'-'. $key.'-'.$rea;
//                        }
                        break;
                    case 'balance_nums':
                        if(is_numeric($value) && strlen($value)<=8 && $value>0){
                            continue;
                        } else{
                            $msg[] = $keys .'-'. $key.'-'.$rea;
                        }
                        break;
                    case 'cost_tax':
                        if(!empty($value)){
                            if(is_numeric($value) && strlen($value)<=20){
                                continue;
                            } else{
                                $msg[] = $keys .'-'. $key.'-'.$rea;
                            }
                        }
                        break;
                    case 'oper':
                        if(!empty($value)){
                            if(is_string($value) && strlen($value)<=30){
                                continue;
                            } else{
                                $msg[] = $keys .'-'. $key.'-'.$rea;
                            }
                        }
                        break;
                    case 'operator':
                        if(is_string($value) && strlen($value)<=30){
                            continue;
                        } else{
                            $msg[] = $keys .'-'. $key.'-'.$rea;
                        }
                        break;
                    case 'settle_method':
                        if(!empty($value)){
                            if(is_string($value) && strlen($value)<=32){
                                continue;
                            } else{
                                $msg[] = $keys .'-'. $key.'-'.$rea;
                            }
                        }
                        break;
                    case 'settle_status':
                        if(!empty($value)){
                            if(is_numeric($value) && strlen($value)<=2){
                                continue;
                            } else{
                                $msg[] = $keys .'-'. $key.'-'.$rea;
                            }
                        }
                        break;
                    case 'settle_operator':
                        if(!empty($value)){
                            if(is_string($value) && strlen($value)<=30){
                                continue;
                            } else{
                                $msg[] = $keys .'-'. $key.'-'.$rea;
                            }
                        }
                        break;
                    case 'settle_time':
                        if(!empty($value)){
                            if(is_numeric($value) && strlen($value)<=10 && $value>0){
                                continue;
                            } else{
                                $msg[] = $keys .'-'. $key.'-'.$rea;
                            }
                        }
                        break;
                    case 'settle_num':
                        if(!empty($value)){
                            if(is_numeric($value) && strlen($value)<=8 && $value>0){
                                continue;
                            } else{
                                $msg[] = $keys .'-'. $key.'-'.$rea;
                            }
                        }
                        break;
                    case 'settlement_bn':
                        if(!empty($value)){
                            if(is_string($value) && strlen($value)<=32){
                                continue;
                            } else{
                                $msg[] = $keys .'-'. $key.'-'.$rea;
                            }
                        }
                        break;
                    case 'settlement_money':
                        if(!empty($value)){
                            if(is_numeric($value) && strlen($value)<=20){
                                continue;
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
* $type 类型 如：iostock-1
**/
    function get_iostock_bn($type,$num = 0){
        $kt = $this->iostock_rules($type);
        $iostock_type = 'iostock-'.$type;

        if($num >= 1){
            $num++;
        }else{
            $sql = "SELECT id FROM sdb_ome_concurrent WHERE `type`='$iostock_type' and `current_time`>'".strtotime(date('Y-m-d'))."' and `current_time`<=".time()." order by id desc limit 0,1";
            $arr = kernel::database()->select($sql);
            if($id = $arr[0]['id']){
                $num = substr($id,-6);
                $num = intval($num)+1;
            }else{
                $num = 1;
            }
        }
        $po_num = str_pad($num,6,'0',STR_PAD_LEFT);
        $iostock_bn = $kt.date(Ymd).$po_num;

        $conObj = app::get('ome')->model('concurrent');
        if($conObj->is_pass($iostock_bn,$iostock_type)){
            return $iostock_bn;
        } else {
            if($num > 999999){
                return false;
            }else{
                return $this->get_iostock_bn($type,$num);
            }
        }
    }
/**
* 出入库类型标识
* $rules 出入库类型编号 如：30
**/
    function iostock_rules($rules){

    	return $this->iostock_types[$rules]['code'];
        /*switch($rules){
            case '1'://采购入库
                return 'I';
                break;
            case '10'://采购退货
                return 'H';
                break;
            case '3'://销售出库
                return 'O';
                break;
            case '30': //退货入库
                return 'M';
                break;
            case '31'://换货入库
                return 'C';
                break;
            case '4'://调拨入库
                return 'T';
                break;
            case '40'://调拨出库
                return 'R';
                break;
            case '5'://残损出库
                return 'B';
                break;
            case '50':;//残损入库
                return 'D';
                break;
            case '6'://盘亏
                return 'L';
                break;
            case '60':
                return 'P';//盘盈
                break;
        }*/
    }

 	function get_iostock_types(){

 		return $this->iostock_types;
 		/*return array('1'=>'采购入库',
 					'10'=>'采购退货',
			 		'3'=>'销售出库',
			 		'30'=>'退货入库',
			 		'31'=>'换货入库',
			 		'4'=>'调拨入库',
			 		'40'=>'调拨出库',
			 		'5'=>'残损出库',
			 		'50'=>'残损入库',
			 		'6'=>'盘亏',
			 		'60'=>'盘盈',
 		);*/
    }

    function getIoByType($type){
    	if(isset($this->iostock_types[$type])){
    		return $this->iostock_types[$type]['io'];
    	}else{
    		return 1;
    	}
    }

}
