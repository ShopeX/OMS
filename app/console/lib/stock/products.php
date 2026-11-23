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
* 货品库存外部调用类
* 
* 
*/
class console_stock_products extends console_stock_stock{

    private $__transaction = false;
    private $__db = false;

    /**
    * 销售预占+ 店铺销售预占+
    * @access public
    * @param Int $product_id 货品ID
    * @param Int $nums 数量
    * @param String $shop_id 店铺ID
    * @param array $log_data 日志数组array('originale_id'=>'单据id','memo'=>'',)
    * @return bool
    */
    public function sale_freeze($product_id='',$nums='',$shop_id='',$log_data=array()){
        if (empty($product_id) || empty($nums)) return false;
        
        $this->start_transaction();
        $status = false;
        if($this->chg_sale_freeze($product_id,$nums)){
            if($this->chg_shop_sale_freeze($product_id,$nums,$shop_id)){
                $status = true;
            }
        }
        $this->end_transaction($status);

        #日志记录
        $log_data['memo'] .= sprintf("(销售预占+ %1\$u 店铺销售预占+ %1\$u)",$nums);
        $this->_write_log($status,$product_id,$shop_id,$branch_id='',$nums,$operator='+',$log_data);

        return $status;
    }

    /**
    * 销售预占- 店铺销售预占-
    * @access public
    * @param Int $product_id 货品ID
    * @param Int $nums 数量
    * @param String $shop_id 店铺ID
    * @param array $log_data 日志数组array('originale_id'=>'单据id','memo'=>'',)
    * @return bool
    */
    public function sale_unfreeze($product_id='',$nums='',$shop_id='',$log_data=array()){
        if (empty($product_id) || empty($nums)) return false;
        
        $this->start_transaction();
        $status = false;
        if($this->chg_sale_unfreeze($product_id,$nums)){
            if($this->chg_shop_sale_unfreeze($product_id,$nums,$shop_id)){
                $status = true;
            }
        }
        $this->end_transaction($status);

        #日志记录
        $log_data['memo'] .= sprintf("(销售预占- %1\$u 店铺销售预占- %1\$u)",$nums);
        $this->_write_log($status,$product_id,$shop_id,$branch_id='',$nums,$operator='-',$log_data);

        return $status;
    }

    /**
    * 销售预占(-) 店铺销售预占- 仓库冻结(+)
    * 只针对基础货品
    * @access public
    * @param Int $branch_id 仓库ID
    * @param Int $product_id 基础货品ID
    * @param Int $nums 数量
    * @param String $shop_id 店铺ID
    * @param array $log_data 日志数组array('originale_id'=>'单据id','memo'=>'')
    * @return bool
    */
    public function p2b_freeze($branch_id='',$product_id='',$nums='',$shop_id='',$log_data=array()){
        if (empty($branch_id) || empty($product_id) || empty($nums) || empty($shop_id)) return false;
        
        $status = false;
        if ($this->pObj->get_product_type($product_id) == 'normal'){
            $this->start_transaction();
            if($this->chg_shop_sale_unfreeze($product_id,$nums,$shop_id)){
                if($this->chg_sale_unfreeze($product_id,$nums)){
                    if($this->chg_branch_freeze($branch_id,$product_id,$nums)){
                        $status = true;
                    }
                }
            }
            $this->end_transaction($status);
            $log_data['memo'] .= sprintf("(销售预占- %1\$u 仓库冻结+ %1\$u)",$nums);
        }else{
            $log_data['memo'] .= '(非基础货品不进行库存变更)';
        }

        #日志记录
        $this->_write_log($status,$product_id,$shop_id,$branch_id,$nums,$operator='-',$log_data);

        return $status;
    }

    /**
    * 销售预占(+) 店铺销售预占+ 仓库冻结(-)
    * 只针对基础货品
    * @access public
    * @param Int $branch_id 仓库ID
    * @param Int $product_id 基础货品ID
    * @param Int $nums 数量
    * @param String $shop_id 店铺ID
    * @param array $log_data 日志数组array('originale_id'=>'单据id','memo'=>'')
    * @return bool
    */
    public function p2b_unfreeze($branch_id='',$product_id='',$nums='',$shop_id='',$log_data=array()){
        if (empty($branch_id) || empty($product_id) || empty($nums) || empty($shop_id)) return false;
       
        $status = false;
        if ($this->pObj->get_product_type($product_id) == 'normal'){
            $this->start_transaction();
            if($this->chg_branch_unfreeze($branch_id,$product_id,$nums)){
                if($this->chg_shop_sale_freeze($product_id,$nums,$shop_id)){
                    if($this->chg_sale_freeze($product_id,$nums)){
                        $status = true;
                    }
                }
            }
            $this->end_transaction($status);
            $log_data['memo'] .= sprintf("(销售预占+ %1\$u 仓库冻结- %1\$u)",$nums);
        }else{
            $log_data['memo'] .= '(非基础货品不进行库存变更)';
        }

        #日志记录
        $this->_write_log($status,$product_id,$shop_id,$branch_id,$nums,$operator='+',$log_data);

        return $status;
    }

    /**
    * 仓库冻结+
    * 只针对基础货品
    * @access public
    * @param Int $branch_id 仓库ID
    * @param Int $product_id 货品ID
    * @param Int $nums 数量
    * @param array $log_data 日志数组array('originale_id'=>'单据id','memo'=>'')
    * @return bool
    */
    public function branch_freeze($branch_id='',$product_id='',$nums='',$log_data=array()){
        if (empty($branch_id) || empty($product_id) || empty($nums)) return false;

        $status = false;
        if ($this->pObj->get_product_type($product_id) == 'normal'){
            $status = $this->chg_branch_freeze($branch_id,$product_id,$nums);
            $log_data['memo'] .= sprintf("(仓库冻结+ %u)",$nums);
        }else{
            $log_data['memo'] .= '(非基础货品不进行库存变更)';
        }

        #日志记录
        $this->_write_log($status,$product_id,$shop_id='',$branch_id,$nums,$operator='+',$log_data);

        return $status;
    }

    /**
    * 仓库冻结-
    * 只针对基础货品
    * @access public
    * @param Int $branch_id 仓库ID
    * @param Int $product_id 货品ID
    * @param Int $nums 数量
    * @param array $log_data 日志数组array('originale_id'=>'单据id','memo'=>'')
    * @return bool
    */
    public function branch_unfreeze($branch_id='',$product_id='',$nums='',$log_data=array()){
        if (empty($branch_id) || empty($product_id) || empty($nums)) return false;
        
        $status = false;
        if ($this->pObj->get_product_type($product_id) == 'normal'){
            $status = $this->chg_branch_unfreeze($branch_id,$product_id,$nums);
            $log_data['memo'] .= sprintf("(仓库冻结- %u)",$nums);
        }else{
            $log_data['memo'] .= '(非基础货品不进行库存变更)';
        }

        #日志记录
        $this->_write_log($status,$product_id,$shop_id='',$branch_id,$nums,$operator='-',$log_data);

        return $status;
    }

    /**
    * 实际库存+
    * 非组合，即普通/捆绑明细：仓库实际库存+ 货品实际库存+ 使用事务处理
    * 组合：组合商品本身实际库存+
    * @access public
    * @param Int $branch_id 仓库ID
    * @param Int $product_id 货品ID
    * @param Int $nums 数量
    * @param array $log_data 日志数组array('originale_id'=>'单据id','memo'=>'')
    * @return bool
    */
    public function storein($branch_id='',$product_id='',$nums,$log_data=array()){
        if (empty($branch_id) || empty($product_id) || empty($nums)) return false;
        
        $goods_type = $this->pObj->get_product_type($product_id);
        $class_name = 'ome_stock_'.$goods_type;
        $status = false;
        if (ome_func::class_exists($class_name) && $instance = kernel::single($class_name)){
            if(method_exists($instance,'storein')){
                $goods_type == 'normal' ? $this->start_transaction() : '';
                $status = $instance->storein($branch_id,$product_id,$nums);
                $goods_type == 'normal' ? $this->end_transaction($status) : '';
            }else{
                $log_data['memo'] .= 'Class'.$class_name.' method:storein not exists';
            }
        }else{
            $log_data['memo'] .= 'Class'.$class_name.' not exists';
        }

        #日志记录
        if($goods_type == 'normal'){
            $log_data['memo'] .= sprintf("(仓库实际库存+ %1\$u 货品实际库存+ %1\$u)",$nums);
        }else{
            $log_data['memo'] .= sprintf('(组合商品本身实际库存+ %s)',$nums);
        }
        //$this->_write_log($status,$product_id,$shop_id,$branch_id,$nums,$operator='+',$log_data);

        return $status;
    }

    /**
    * 基础货品实际库存- 货品实际库存-
    * 只针对基础货品并使用事务处理
    * @access public
    * @param Int $branch_id 仓库ID
    * @param Int $product_id 货品ID
    * @param Int $nums 数量
    * @param array $log_data 日志数组array('originale_id'=>'单据id','memo'=>'')
    * @return bool
    */
    public function storeout($branch_id='',$product_id='',$nums,$log_data=array()){
        if (empty($branch_id) || empty($product_id) || empty($nums)) return false;
        
        $goods_type = $this->pObj->get_product_type($product_id);
        $status = false;
        if ($goods_type == 'normal'){
            $this->start_transaction();
            $status = kernel::single('ome_stock_normal')->storeout($branch_id,$product_id,$nums);
            $this->end_transaction($status);
            $log_data['memo'] .= sprintf("(仓库实际库存- %1\$u 货品实际库存- %1\$u)",$nums);
        }else{
            $log_data['memo'] .= '(非基础货品不进行库存变更)';
        }

        #日志记录
        $this->_write_log($status,$product_id,$shop_id='',$branch_id,$nums,$operator='-',$log_data);

        return $status;
    }

    /**
    * 组合商品实际库存- 销售预占- 店铺销售预占-
    * 只针对组合商品 并使用事务处理
    * @access public
    * @param String $shop_id 店铺ID
    * @param Int $product_id 货品ID
    * @param Int $nums 数量
    * @param array $log_data 日志数组array('originale_id'=>'单据id','memo'=>'')
    * @return bool
    */
    public function combine_storeout($shop_id='',$product_id='',$nums,$log_data=array()){
        if (empty($shop_id) || empty($product_id) || empty($nums)) return false;
        
        $goods_type = $this->pObj->get_product_type($product_id);
        $class_name = 'ome_stock_'.$goods_type;
        $status = false;
        if($goods_type == 'combination'){
            $this->start_transaction();
            $status = kernel::single('ome_stock_combination')->storeout($shop_id,$product_id,$nums);
            $this->end_transaction($status);
            $log_data['memo'] .= sprintf("(组合商品本身实际库存- %1\$u 销售预占- %1\$u 店铺销售预占- %1\$u)",$nums);
        }else{
            $log_data['memo'] .= '(非组合商品不进行库存变更)';
        }

        #日志记录
        $this->_write_log($status,$product_id,$shop_id,$branch_id='',$nums,$operator='-',$log_data);

        return $status;
    }

    /**
    * 组合商品明细销售预占+ 店铺销售预占+,组合预占-
    * 只针对基础货品
    * @access public
    * @param String $shop_id 店铺ID
    * @param Int $product_id 基础货品ID
    * @param Int $nums 数量
    * @param array $log_data 日志数组array('originale_id'=>'单据id','memo'=>'')
    * @return bool
    */
    public function sale2combine_freeze($shop_id,$product_id='',$nums='',$log_data=array()){
        if (empty($product_id) || empty($nums) || empty($shop_id)) return false;

        $this->start_transaction();

        $status = false;
        if($this->chg_shop_sale_freeze($product_id,$nums,$shop_id)){
            if($this->chg_sale_freeze($product_id,$nums)){
                if($this->chg_combine_unfreeze($product_id,$nums)){
                    $status = true;
                }
            }
        }
        $this->end_transaction($status);

        #日志记录
        $log_data['memo'] .= sprintf("(组合商品明细销售预占+ %1\$u 店铺销售预占+ %1\$u 组合预占- %1\$u)",$nums);
        $this->_write_log($status,$product_id,$shop_id,$branch_id='',$nums,$operator='+',$log_data);

        return $status;
    }

    /**
    * 组合商品明细销售预占- 店铺销售预占-,组合预占+
    * 只针对基础货品
    * @access public
    * @param String $shop_id 店铺ID
    * @param Int $product_id 基础货品ID
    * @param Int $nums 数量
    * @param array $log_data 日志数组array('originale_id'=>'单据id','memo'=>'')
    * @return bool
    */
    public function sale2combine_unfreeze($shop_id,$product_id='',$nums='',$log_data=array()){
        if (empty($product_id) || empty($nums) || empty($shop_id)) return false;


        $tron = kernel::database()->beginTransaction();

        try {

            $this->_try_catch($shop_id, $product_id, $nums, $log_data);

            kernel::database()->commit($tron);

        } catch (BranchStoreFreezeException $e) {

            kernel::database()->rollBack();

        }

// =====================================================================
/*
        $this->start_transaction();
        $status = false;
        if($this->chg_shop_sale_unfreeze($product_id,$nums,$shop_id)){
            if($this->chg_sale_unfreeze($product_id,$nums)){
                if($this->chg_combine_freeze($product_id,$nums)){
                    $status = true;
                }
            }
        }
        $this->end_transaction($status);
*/
// =====================================================================

        #日志记录
        $log_data['memo'] .= sprintf("(组合商品明细销售预占- %1\$u 店铺销售预占- %1\$u 组合预占+ %1\$u)",$nums);
        $this->_write_log($status,$product_id,$shop_id,$branch_id='',$nums,$operator='-',$log_data);

        return $status;
    }

    private function _try_catch($shop_id, $product_id, $nums, $log_data)
    {
        if($this->chg_shop_sale_unfreeze($product_id,$nums,$shop_id)){
            if($this->chg_sale_unfreeze($product_id,$nums)){
                $res = $this->chg_combine_freeze($product_id,$nums);

                if ($res[0]) {
                    return true;
                } else {
                    throw new BranchStoreFreezeException("基础物料冻结失败:".$res[1], 0, null, $res[2]);
                }
            }
        }
        throw new BranchStoreFreezeException("exec fail", 0, null, []);
    }

    /**
    * 保存组合商品：明细组合预占+/- 本身实际库存+/-
    * 只针对组合商品,在现有库存基础上做差异变更
    * @access public
    * @param Int $product_id 组合商品ID
    * @param Array $pre_product 修改前组合商品数据，增加时可为空
       $old_product = array(
           'nums' => '组合套数',
           'items' => array(
                array('item_prouct_id'=>'基础货品ID1','item_nums'=>'数量'),
                array('item_prouct_id'=>'基础货品ID2','item_nums'=>'数量'),
           );
       );
    * @param Array $after_product 修改后组合商品数据,增加/编辑都需要提供
       $old_product = array(
           'nums' => '组合套数',
           'items' => array(
                array('item_prouct_id'=>'基础货品ID1','item_nums'=>'数量'),
                array('item_prouct_id'=>'基础货品ID2','item_nums'=>'数量'),
                array('item_prouct_id'=>'基础货品ID3','item_nums'=>'数量'),
           );
       );
    * @param array $log_data 日志数组array('originale_id'=>'单据id','memo'=>'')
    * @return bool
    */
    public function save_combination($product_id,$pre_product='',$after_product='',$log_data=array()){
        if (empty($product_id) || empty($after_product)) return false;
        
        $goods_type = $this->pObj->get_product_type($product_id);
        if($goods_type == 'combination'){
            #修改前明细格式化
            $pre_product_items = array();
            $pre_nums = $pre_product['nums'];
            if(isset($pre_product['items'])){
                foreach ($pre_product['items'] as $item){
                    $item_nums = $pre_nums*$item['item_nums'];
                    $pre_product_items[$item['item_product_id']] = $item_nums;
                }
            }

            #修改后明细格式化
            $after_product_items = array();
            $after_nums = $after_product['nums'];
            if(isset($after_product['items'])){
                foreach ($after_product['items'] as $item){
                    $item_nums = $after_nums*$item['item_nums'];
                    $after_product_items[$item['item_product_id']] = $item_nums;
                }
            }

            #计算商品明细数量差异一:未修改和新增的差异明细
            $new_product_item = array();
            if($after_product_items){
                foreach ($after_product_items as $item_product_id=>$item_nums){
                    if (isset($pre_product_items[$item_product_id])){
                        $new_product_item[$item_product_id] = abs($pre_product_items[$item_product_id]) - abs($item_nums);
                    }else{
                        $new_product_item[$item_product_id] = -abs($item_nums);
                    }
                }
            }

            #计算商品明细数量差异二:删除的差异明细
            if($pre_product_items){
                foreach ($pre_product_items as $item_product_id=>$item_nums){
                    if (!isset($after_product_items[$item_product_id])){
                        $new_product_item[$item_product_id] = abs($item_nums);
                    }
                }
            }
            
            #库存更新
            $diff_flag = false;
            $this->start_transaction();
            $status = true;
            if ($new_product_item){
                foreach ($new_product_item as $item_product_id=>$item_nums){
                    if ($item_nums != '0'){
                        $diff_flag = true;
                        $func = $item_nums > '0' ? 'chg_combine_unfreeze' : 'chg_combine_freeze';
                        if (!$this->$func($item_product_id,abs($item_nums))){
                            $status = false;
                            break;
                        }
                    }
                }
            }
            if ($status){
                $diff_nums = $pre_nums - $after_nums;
                if ($diff_nums != '0'){
                    $diff_flag = true;
                    $func = $diff_nums > '0' ? 'chg_storeout' : 'chg_storein';
                    $operator = $diff_nums > '0' ? '-' : '+';
                    if(!$this->$func($product_id,abs($diff_nums))){
                        $status = false;
                    }
                }
            }
            $this->end_transaction($status);
            $save_data = serialize(array('product_id'=>$product_id,'pre_product'=>$pre_product,'after_product'=>$after_product));
            if ($diff_flag){
                $log_data['memo'] .= '(保存组合商品,数据明细:'.$save_data.')';
            }else{
                $log_data['memo'] .= '(保存组合商品无差异-无需更新,数据明细:'.$save_data.')';
            }
        }else{
            $log_data['memo'] .= '(非组合商品不进行库存变更)';
        }

        #日志记录
        $this->_write_log($status,$product_id,$shop_id,$branch_id='',$after_nums,$operator,$log_data);

        return $status;
    }

    /**
    * 删除组合商品：明细组合预占- 本身实际库存-
    * 只针对组合商品
    * @access public
    * @param Int $combine_product_id 组合商品ID
    * @param array $log_data 日志数组array('originale_id'=>'单据id','memo'=>'')
    * @return bool
    */
    public function del_combination($combine_product_id,$log_data=array()){
        if (empty($combine_product_id)) return false;


        //此方法没有地方调用，getRow方法不存在
        $p = $this->pObj->getRow($combine_product_id,'type,store_freeze,store');
        if ($p['store_freeze'] > '0'){
            $log_data['memo'] .= '(组合商品销售中无法删除)';
        }elseif($p['store'] <= '0'){
            $log_data['memo'] .= '(组合商品套数为0无需更新库存)';
        }else{
            $goods_type = $p['type'];
            $combine_nums = $p['store'];
            if($goods_type == 'combination'){
                $this->start_transaction();

                $status = true;
                $sku_items = $this->pObj->get_product_item($combine_product_id);
                if ($sku_items){
                    foreach ($sku_items as $val){
                        $item_nums = $combine_nums * $val['nums'];
                        if (!$this->chg_combine_unfreeze($val['product_id'],$item_nums)){
                            $status = false;
                        }
                    }
                }
                if ($status){
                    if(!$this->chg_storeout($combine_product_id,$combine_nums)){
                        $status = false;
                    }
                }
                $this->end_transaction($status);

                $combine_items = serialize($sku_items);
                $log_data['memo'] .= '(删除组合商品明细:'.$combine_items.')';
            }else{
                $log_data['memo'] .= '(非组合商品不进行库存变更)';
            }
        }

        #日志记录
        $this->_write_log($status,$combine_product_id,$shop_id='',$branch_id='',$combine_nums,$operator='-',$log_data);
        return $status;
    }

    /**
    * 货品可售库存：线上仓库库存
    * 普通：线上仓库库存-销售预占-组合预占-线上仓库冻结库存
    * 捆绑：捆绑最大可售 = min(捆绑商品明细可售库存/捆绑数量,...)
    * 组合：组合本身库存 - 组合本身销售预占
    * @access public
    * @param Int $product_id 货品ID
    * @param Int $nums 数量
    * @return Int
    */
    public function get_usable_sale_store($product_id=''){
        $store = 0;
        if (empty($product_id)) return $store;
        
        $goods_type = kernel::single('ome_products')->get_product_type($product_id);
        $class_name = 'ome_stock_'.$goods_type;
        if (ome_func::class_exists($class_name) && $instance = kernel::single($class_name)){
            if(method_exists($instance,'get_usable_sale_store')){
                $store = $instance->get_usable_sale_store($product_id);
            }
        }
        
        return $store;
    }

    /**
    * 货品可用库存:线上和线下所有仓库库存
    * 普通：所有库存-销售预占-组合预占-仓库冻结库存
    * 捆绑：捆绑最大可售 = min(捆绑商品明细可售库存/捆绑数量,...)
    * 组合：组合本身库存 - 组合本身销售预占
    * @access public
    * @param Int $product_id 货品ID
    * @return Int
    */
    public function get_usable_store($product_id,$attr=''){
        $store = 0;
        if (empty($product_id)) return $store;
        
        $goods_type = kernel::single('ome_products')->get_product_type($product_id);
        $class_name = 'ome_stock_'.$goods_type;
        if (ome_func::class_exists($class_name) && $instance = kernel::single($class_name)){
            if(method_exists($instance,'get_usable_store')){
                $store = $instance->get_usable_store($product_id,$attr);
            }
        }
        
        return $store;
    }

    /**
    * 获取货品所在仓库的可用库存[已弃用,直接用single('ome_branch_product')]
    * @access public
    * @param Int $branch_id 仓库ID
    * @param Int $product_id 货品ID
    * @return Int
    */
    public function get_branch_usable_store($branch_id='',$product_id=''){
        $store = 0;
        if (empty($branch_id) || empty($product_id)) return $store;
        
        $goods_type = $this->get_product_type($product_id);
        $class_name = 'console_stock_'.$goods_type;
        if (ome_func::class_exists($class_name) && $instance = kernel::single($class_name)){
            if(method_exists($instance,'get_branch_usable_store')){
                $store = $instance->get_branch_usable_store($branch_id,$product_id);
            }
        }
        
        return $store;
    }




    //--------------------------------私有方法 -----------------------------------------

    /*
    * 开启事务
    * @access private
    * @param bool $status 库存更新状态:true成功 false失败
    * @return bool
    */
    private function start_transaction(){
        $this->__db = kernel::database();
        $is_transaction = $this->__db->is_transaction();//TODO:获取是否已开启事务
        if(!$is_transaction){
           $this->__db->beginTransaction();
           $this->__transaction = true;
        }
    }

    /*
    * 事务处理
    * @access private
    * @param bool $status 库存更新状态:true成功 false失败
    * @return bool
    */
    private function end_transaction($status){
        if($status === true){
            return $this->__transaction === true ? $this->__db->commit() : '';
        }else{
            return $this->__transaction === true ? $this->__db->rollBack() : '';
        }
    }

    /*
    * 日志记录
    * @access private 
    * @param bool $status 库存处理状态,:true成功 false失败
    * @return bool
    */
    private function _write_log($status,$product_id,$shop_id,$branch_id,$nums,$operator,$addon=array()){
        if (empty($product_id) && empty($shop_id) && empty($branch_id) && empty($nums)){
            return false;
        }
        $addon['status'] = $status === true ? 'succ' : 'fail';
        $operator = $operator == '+' ? '1' : '0';
        return kernel::single('console_store_freezelog')->add_log($product_id,$shop_id,$branch_id,$nums,$operator,$addon);
    }

    /**
    * 获取货品类型
    * @access public 
    * @param Int $product_id 货品ID
    * @return string normal/combination/pkg
    */
    public function get_product_type($product_id = ''){
        $product = $this->get_product_data($product_id);
        return $product['type'];
    }

    /**
    * 获取货品基础信息
    * @access public 
    * @param Int $product_id 货品ID
    * @return array
    */
    public function get_product_data($product_id = '')
    {
        $basicMaterialObj    = app::get('material')->model('basic_material');
        
        if($product_id == '') return null;
        
        $product      = $basicMaterialObj->dump(array('bm_id'=>$product_id), '*');
        $product['product_id']    = $product['bm_id'];
        
        //[强制转换]基础物料商品类型
        $product['type']    = ($product['type'] == '2' ? 'pkg' : 'normal');
        
        return $product ? $product : null;
    }

}