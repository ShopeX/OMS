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
 * @author ykm 2016/5/17
 * @describe 添加商家备注 数据保存
 */
class erpapi_shop_response_process_remark {
    
    /**
     * 添加
     * @param mixed $params 参数
     * @return mixed 返回值
     */

    public function add($params) {
        $remark = $params['mark_text'];
        $remark[] = $params['new_mark'];
        app::get('ome')->model('orders')->update(array('mark_text'=>serialize($remark)),array('order_id'=>$params['order_id']));
        $orderPauseAllow = app::get('ome')->getConf('ome.orderpause.to.syncmarktext');#同步订单备注是否暂停订单配置
        $orderPauseMarkText = app::get('ome')->getConf('ome.orderpause.to.syncmarktext.content');
        #备注发生变更，已审核订单暂停
        if (in_array($params['process_status'], array('splited', 'splitting'))
            && $orderPauseAllow == 'true'
            && ($orderPauseMarkText === '' || strstr($params['new_mark']['op_content'], $orderPauseMarkText))) {
            app::get('ome')->model('orders')->pauseOrder($params['order_id']);
        }
        $memo ='更新商家备注';
        
        //brush特殊订单
        if($params['farm_id']) {
            $rs = app::get('ome')->model('orders')->update(array('order_type'=>'brush'), array('order_id'=>$params['order_id'], 'process_status'=>'unconfirmed'));
            if(is_bool($rs)){
                $memo .= ',订单已确认不能转为特殊订单';
            } else {
                $memo .= ',转为特殊订单';
                
                $brush = array();
                $brush['farm_id'] = $params['farm_id'];
                $brush['order_id'] = $params['order_id'];
                
                app::get('brush')->model('farm_order')->save($brush);

                if ($params['order_objects']) {
                    $params['order_objects'] = array_column($params['order_objects'], null, 'obj_id');
                } else {
                    $params['order_objects'] = [];
                }
                
                //释放冻结
                $basicMStockFreezeLib = kernel::single('material_basic_material_stock_freeze');

                //释放基础物料冻结
                $branchBatchList = [];
                foreach((array)$params['order_items'] as $order_item){
                    if($order_item['product_id'] && $order_item['delete'] == 'false'){

                        //[扣减]基础物料店铺冻结
                        $branchBatchList[] = [
                            'bm_id'     =>  $order_item['product_id'],
                            'sm_id'     =>  $params['order_objects'][$order_item['obj_id']]['goods_id'],
                            'obj_type'  =>  material_basic_material_stock_freeze::__ORDER,
                            'bill_type' =>  0,
                            'obj_id'    =>  $params['order_id'],
                            'branch_id' =>  '',
                            'bmsq_id'   =>  material_basic_material_stock_freeze::__SHARE_STORE,
                            'num'       =>  $order_item['nums'], 
                        ];
                    }
                }
                //[扣减]基础物料店铺冻结
                $basicMStockFreezeLib->unfreezeBatch($branchBatchList, __CLASS__.'::'.__FUNCTION__, $err);
                
                //清除订单级预占店铺冻结流水
                // unfreezeBatch已经清除
                // $basicMStockFreezeLib->delOrderFreeze($params['order_id']);
            }
        }
        
        app::get('ome')->model('operation_log')->write_log('order_edit@ome',$params['order_id'],$memo);
        return array('rsp'=>'succ', 'msg'=>'更新商家备注成功');
    }
}