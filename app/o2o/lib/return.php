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
 * 门店退换货单处理Lib类
 * 20170509 by @author: wangjianjun@shopex.cn
 */
class o2o_return{

    /**
     * 获取售后退入仓候选列表
     *
     * @param int   $branch_id 原单发货仓 branch_id
     * @param array $ctx       shop_id/logi_id/order_id/return_type/flag_type/source
     * @return array
     */
    public function get_aftersale_o2o_branch($branch_id, $ctx = array())
    {
        $ctx['delivery_branch_id'] = $branch_id;
        $result = kernel::single('o2o_return_branch')->resolve($ctx);

        return $result['branch_list'];
    }

    /**
     * 解析退入仓（含默认 branch_id）
     *
     * @param array $ctx
     * @return array
     */
    public function resolve_return_branch($ctx = array())
    {
        return kernel::single('o2o_return_branch')->resolve($ctx);
    }
    
    /**
     * 换货订单验证仓库
     * $branch_id 退入仓
     * $changebranch_id 换出仓
     * return boolean
     */
    public function check_reship_branch($branch_id,$changebranch_id,&$error_msg){
        $branchLib = kernel::single('ome_branch');
        $store_id = $branchLib->isStoreBranch($branch_id);
        if($store_id){
            $change_store_id= $branchLib->isStoreBranch($changebranch_id);
            if ($change_store_id){
            }else{
                $error_msg = "退入为门店仓，换出仓不允许是电商仓。";
                return false;
            }
        }
        return true;
    }
    
    /**
     * 基础物料门店库存状态信息获取
     * $branch_id 门店仓id
     * $bm_id 基础物料id
     * @return array
     */
    public function o2o_store_stock($branch_id,$bm_id){
        
        $basicMStockFreezeLib  = kernel::single('material_basic_material_stock_freeze');
        
        //返回数组 默认不检查门店仓库存 并 不管控库存
        $return_array = array("need_check_stock"=>false,"store"=>"-","share_store"=>"-"); 
        //是否管控门店供货关系(系统->门店配置)
        $supply_relation = app::get('o2o')->getConf('o2o.ctrl.supply.relation');
        if ($supply_relation == "true"){
            //判是否有供货关系
            $rs_relation = app::get('o2o')->model('branch_product')->dump(array("bm_id"=>$bm_id,"branch_id"=>$branch_id),"is_ctrl_store, status AS sale_status");
            if (!empty($rs_relation)){
                //获取仓库
                $branch = app::get('ome')->model('branch')->dump(array('branch_id' => $branch_id));
                $is_relation = true;
                //停售状态"隐藏"供货关系 参照class o2o_branch_product{ 的 function getO2oBranchStore($bm_id)
                if($rs_relation["sale_status"] != 1){
                    $is_relation = false;
                }
                if($is_relation){
                    //判门店仓库是否选择了“支持”库存管控
                    $rs_store = app::get('o2o')->model('store')->dump(array("branch_id"=>$branch_id),"is_ctrl_store");
                    if ($rs_store["is_ctrl_store"] == "1"){//支持
                        //判门店供货管理 基础物料 配置->库存 选择是/否
                        if($rs_relation["is_ctrl_store"] == "1"){//选择有
                            //获取库存
                            $rs_product_store = app::get('ome')->model('branch_product')->dump(array("branch_id"=>$branch_id,"product_id"=>$bm_id),"store,store_freeze,share_store,share_freeze"); 
                            
                            //根据门店仓库ID、基础物料ID获取该物料仓库级的预占
                            $rs_product_store['store_freeze']    = $basicMStockFreezeLib->getO2oBranchFreeze($bm_id, $branch_id);
                            
                            $store = $rs_product_store['store'] - $rs_product_store['store_freeze'];
                            $share_store = $rs_product_store['share_store'] - $rs_product_store['share_freeze'];
                            $return_array["need_check_stock"] = true;
                            $return_array["store"] = $store > 0 ? $store : 0;
                            $return_array["share_store"] = $share_store > 0 ? $share_store : 0;
                            $return_array['branch_name'] = $branch['name'];
                            $return_array['branch_id'] = $branch['branch_id'];
                        }
                    }
                }else{
                    //管控门店供货关系 隐藏供货关系
                    $return_array["store"] = "x";
                    $return_array["share_store"] = "x";
                    $return_array['branch_name'] = "x";
                }
            }else{
                //管控门店供货关系 没有供货关系 参照审单处是给x
                $return_array["store"] = "x";
                $return_array["share_store"] = "x";
                $return_array['branch_name'] = "x";
            }
        }
        return $return_array;
    }
    
    /**
     * 门店仓换货库存处理
     * $reship_id 退换货单id
     * $operation + 预占  - 释放
     */
    public function do_return_store_freeze($reship_id,$operation){
        // bmsq_id=-2 已经废弃，方法do_return_store_freeze无调用
        /*
        //必要参数检查
        if(!$reship_id || !$operation){
            return false;
        }
        //换货单数据
        $mdl_ome_reship = app::get('ome')->model('reship');
        $rs_reship = $mdl_ome_reship->dump(array("reship_id"=>$reship_id),"reship_bn,shop_id,changebranch_id");
        if(empty($rs_reship)){
            return false;
        }
        //换货明细数据
        $change_item = kernel::single('console_reship')->change_items($reship_id);
        if(empty($change_item)){
            return false;
        }
        //获取数据 处理
        $batchList = [];
        $basicMStockFreezeLib = kernel::single('material_basic_material_stock_freeze');
        foreach ($change_item as $item) {
            //收集门店仓的库存进行预占操作的数据 （因为会存在是否管控库存的选择）
            $arr_stock = kernel::single('o2o_return')->o2o_store_stock($rs_reship['changebranch_id'],$item['product_id']);
            if ($arr_stock["need_check_stock"]){
                switch ($operation){
                    case "+":
                        $freezeData = [];
                        $freezeData['bm_id'] = $item['product_id'];
                        $freezeData['obj_type'] = material_basic_material_stock_freeze::__BRANCH;
                        $freezeData['bill_type'] = material_basic_material_stock_freeze::__RESHIP;
                        $freezeData['obj_id'] = $reship_id;
                        $freezeData['shop_id'] = $rs_reship['shop_id'];
                        $freezeData['branch_id'] = $rs_reship['changebranch_id'];
                        $freezeData['bmsq_id'] = material_basic_material_stock_freeze::__STORE_CONFIRM;
                        $freezeData['num'] = $item['num'];
                        $freezeData['obj_bn'] = $rs_reship['reship_bn'];
                        //门店货品预占冻结
                        $batchList['+'][] = $freezeData;
                        break;
                    case "-" :
                        //门店货品预占冻结释放
                        $batchList['-'][] = [
                            'bm_id'     =>  $item['product_id'],
                            'obj_type'  =>  material_basic_material_stock_freeze::__BRANCH,
                            'bill_type' =>  material_basic_material_stock_freeze::__RESHIP,
                            'obj_id'    =>  $reship_id,
                            'branch_id' =>  $rs_reship['changebranch_id'],
                            'bmsq_id'   =>  material_basic_material_stock_freeze::__STORE_CONFIRM,
                            'num'       =>  $item['num'],
                        ];
                        break;
                }
            }
        }
        //门店货品预占冻结 
        $basicMStockFreezeLib->freezeBatch($batchList['+'], __CLASS__.'::'.__FUNCTION__, $err);
        //门店货品预占冻结 
        $basicMStockFreezeLib->unfreezeBatch($batchList['-'], __CLASS__.'::'.__FUNCTION__, $err);
        */
    }
    
}
