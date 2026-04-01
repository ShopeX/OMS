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
* 更新库存RPC接口实现
*
* @author chenping<chenping@shopex.cn>
* @version 2012-5-30 18:04
*/
class inventorydepth_taog_rpc_request_stock extends ome_rpc_request
{
    /**
     * @description
     * @access public
     * @param void
     * @return void
     */
    public function __construct() 
    {
        $this->app = $app;

    }

    /**
     * 前端店铺更新库存
     *
     * @param Array $stocks 矩阵更新库存结构
     * @param String $shop_id 店铺ID
     *
     **/
    public function items_quantity_list_update($stocks,$shop_id,$dorelease = false)
    {

        if(empty($stocks)) return false;

        kernel::single('erpapi_router_request')->set('shop', $shop_id)->product_updateStock($stocks,$dorelease);
    }

    /**
     * @description 更新库存回调
     * @access public
     * @param void
     * @return void
     */
    public function items_quantity_list_update_callback($result)
    {
        $callback_params = $result->get_callback_params();
        $status = $result->get_status();
        $res = $result->get_result();
        $data = $result->get_data();
        $request_params = $result->get_request_params();
        $msg_id = $result->get_msg_id();

        $request_params['all_list_quantity'] = $request_params['list_quantity'];
        $log_params = array('store.items.quantity.list.update',$request_params,array(get_class($this),'items_quantity_list_update_callback',$callback_params));


        $log_id = $callback_params['log_id'];
        $oApi_log = app::get('ome')->model('api_log');

        $rsp = 'succ';
        if ($status != 'succ' && $status != 'fail' ){
            $res = $status . ome_api_func::api_code2msg('re001', '', 'public');
            $rsp = 'fail';
        }

        if($status == 'succ'){
            $api_status = 'success';
        }else{
            $api_status = 'fail';
        }

        //更新失败的bn会返回，然后下次retry时，只执行失败的bn更新库存
        $err_item_bn = $data['error_response'];
        $addon['error_lv'] = $data['error_level'];//错误等级
        if (!is_array($err_item_bn)){
            $err_item_bn = json_decode($data['error_response'],true);
        }

        $itemsnum = json_decode($log_params[1]['list_quantity'],true);

        $adjustmentModel = app::get('inventorydepth')->model('shop_adjustment');
        $new_itemsnum = $true_itemsnum = array();
        foreach($itemsnum as $k=>$v){
            if(in_array($v['bn'],$err_item_bn)){
                $new_itemsnum[] = $v;
            } else {
                $true_itemsnum[] = $v;
                $adjustmentModel->update(array('shop_stock'=>$v['quantity']),array('shop_id'=>$callback_params['shop_id'],'shop_product_bn'=>$v['bn']));
            }
        }

        if ($err_item_bn) {
            $adjustmentModel->update(array('release_status'=>'fail'),array('shop_id'=>$callback_params['shop_id'],'shop_product_bn'=>$err_item_bn));
        }


        if ($data['true_bn']) {
            $adjustmentModel->update(array('release_status'=>'success'),array('shop_id'=>$callback_params['shop_id'],'shop_product_bn'=>$data['true_bn']));
        }



        //当返回失败且BN为空时不更新list_quantity
        if ($api_status != 'fail' || $new_itemsnum){
            $log_params[1]['list_quantity'] = json_encode($new_itemsnum);
        }else{
            $new_itemsnum = $itemsnum;
        }

        if ($data['error_bn'] || $data['no_bn']) {
            if ($data['error_bn']) {
                $msg[] = '更新失败货号【'.implode(',', $data['error_bn']).'】';
            }
            if ($data['no_bn']) {
                $msg[] = '无效货号【'.implode(',', $data['no_bn']).'】';
            }
            $msg = $res.':<br/>'.implode('<br/>',$msg);
        } elseif($status == 'succ') {
            $msg = '成功';
        } else {
            $msg = '失败';
        }

        $oApi_log->update_log($log_id,$msg,$api_status,$log_params,$addon);

        $log_detail = array(
            'msg_id' => $msg_id,
            'params' => serialize($log_params),
        );

        //更新库存同步管理的执行状态
        $oApiLogToStock = kernel::single('ome_api_log_to_stock');
        if ($true_itemsnum) {
            $oApiLogToStock->save_callback($true_itemsnum,'success',$callback_params['shop_id'],$res,$log_detail);
        }
        if ($new_itemsnum) {
            $oApiLogToStock->save_callback($new_itemsnum,'fail',$callback_params['shop_id'],$res,$log_detail);
        }

        return array('rsp'=>$rsp,'res'=>$res,'msg_id'=>$msg_id);
    }

    /**
     * 更新店铺商品;只更新回写成功的
     *
     * @return void
     * @author
     **/
    private function updateItems($skus,$shop_id)
    {
        $salesMaterialObj = app::get('material')->model('sales_material');
        
        if(!$skus) return;

        $shop_bn = $skus[0]['shop_bn'];

        # 更新商品上下架状态，店铺库存，实际库存
        $iid = array();
        foreach ($skus as $key => $sku) {
            $id = md5($shop_id.$sku['shop_iid']);
            $iid[$id] = $sku['shop_iid'];
        }
        $filter = array(
            'shop_iid' => $iid,
            'shop_id' => $shop_id,
        );
        $skus = app::get('inventorydepth')->model('shop_skus')->getList('shop_product_bn,shop_iid,bind,shop_stock',$filter);

        $bn = array(); $items = array();
        foreach ($skus as $key => $sku) {
            $bn[] = $sku['shop_product_bn'];

            $items[$sku['shop_iid']][] = $sku;
        }
        unset($skus);

        # [普通]销售物料
        $products = $salesMaterialObj->getList('sm_id,sales_material_name,sales_material_bn,shop_id',array('sales_material_bn'=>$bn, 'sales_material_type'=>1));
        # [促销]销售物料
        $products_pkg = $salesMaterialObj->getList('sm_id,sales_material_name,sales_material_bn,shop_id',array('sales_material_bn'=>$bn, 'sales_material_type'=>2));
        # [多选一]销售物料
        $products_pko = $salesMaterialObj->getList('sm_id,sales_material_name,sales_material_bn,shop_id',array('sales_material_bn'=>$bn, 'sales_material_type'=>5));
        
       // kernel::single('inventorydepth_stock_products')->writeMemory($products);
       // kernel::single('inventorydepth_stock_pkg')->writeMemory($products_pkg);
       // kernel::single('inventorydepth_stock_pko')->writeMemory($products_pko);
        
        $stores = $approve_status = array();
        foreach ($items as $shop_iid => $item) {
            $taog_store = $shop_store = 0;
            foreach ($item as $value) {
                if ($value['bind'] == '1') {
                    $taog_store += kernel::single('inventorydepth_stock_calculation')->get_pkg_actual_stock($value['shop_product_bn'],$shop_bn,$shop_id);
                }elseif($value['bind'] == '2'){
                    $taog_store += kernel::single('inventorydepth_stock_calculation')->get_pko_actual_stock($value['shop_product_bn'],$shop_bn,$shop_id);
                }else {
                    $taog_store += kernel::single('inventorydepth_stock_calculation')->get_actual_stock($value['shop_product_bn'],$shop_bn,$shop_id);
                }

                $shop_store += $value['shop_stock'];
            }
            $stores['taog_store'][$shop_iid] = $taog_store > 0 ? $taog_store : 0;
            $stores['shop_store'][$shop_iid] = $shop_store > 0 ? $shop_store : 0;
            $approve_status[$shop_iid] = $shop_store==0 ? 'instock' : 'onsale';
        }

        $filter = array(
            'id' => array_keys($iid),
        );

        $itemModel = app::get('inventorydepth')->model('shop_items');
        $items = $itemModel->getList('*',$filter);
        foreach ($items as &$item) {
            $item['taog_store'] = $stores['taog_store'][$item['iid']];
            $item['shop_store'] = $stores['shop_store'][$item['iid']];
            $item['approve_status'] = $approve_status[$item['iid']];
        }

        $sql = inventorydepth_func::get_replace_sql($itemModel,$items);
        $itemModel->db->exec($sql);
    }

    /**
     * 更新店铺货品;只更新回写成功的
     *
     * @return void
     * @author
     **/
    private function updateSkus($skus,$shop_id,$itemsnum)
    {
        if(!$skus) return;

        $quantity = array();
        foreach ($itemsnum as $key => $value) {
            $quantity[$value['bn']] = $value['quantity'];
        }

        foreach ($skus as &$sku) {
            $sku['shop_stock'] = $quantity[$sku['shop_product_bn']];
            $sku['release_status'] = 'success';
        }

        $skusModel = app::get('inventorydepth')->model('shop_skus');

        $sql = inventorydepth_func::get_replace_sql($skusModel,$skus);
        $skusModel->db->exec($sql);
    }
}
