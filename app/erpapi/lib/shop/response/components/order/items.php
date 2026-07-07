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
 * 订单明细
 *
 * @author chenping<chenping@shopex.cn>
 * @version $Id: items.php 2013-3-12 17:23Z
 */
class erpapi_shop_response_components_order_items extends erpapi_shop_response_components_order_abstract
{
    const _APP_NAME = 'ome';
    
    /**
     * 数据格式转换
     *
     * @return void
     * @author
     **/
    public function convert()
    {
        $orderItemMdl = app::get('ome')->model('order_items');
        
        $salesMLib = kernel::single('material_sales_material');
        $fudaiLib = kernel::single('material_fukubukuro_dispatch');
        
        //obj_type
        $objTypeList = $orderItemMdl->_obj_alias;
        
        //定制订单类型
        $is_custom_order = false;
        if($this->_platform->_newOrder['order_type'] == 'custom'){
            $is_custom_order = true;
        }
        
        //asdp_biz_type
        $asdp_biz_type = $this->_platform->_ordersdf['cn_info']['asdp_biz_type'];
        if(empty($asdp_biz_type) && $this->_platform->_ordersdf['cn_info']){
            $asdp_biz_type = $this->_platform->_ordersdf['cn_info']['logistics_agreement']['asdp_biz_type'];
        }
        
        //[翱象]子单纬度的择仓、择配
        $logisObjects = array();
        if($asdp_biz_type == 'aox'){
            $logisObjects = $this->_formatAoxiangObjectData($this->_platform->_ordersdf);
        }
        
        //o2o
        $storeCode = '';
        $estimateConTime = 0;
        if ($this->_platform->_ordersdf['o2o_info'] && $this->_platform->_ordersdf['o2o_info']['store_code']) {
            if ($this->_platform->_ordersdf['o2o_info']['store_deliver_time']) {
                $dlyTime = strtotime($this->_platform->_ordersdf['o2o_info']['store_deliver_time']);
                $ahead = (int) app::get('ome')->getConf('ome.o2o.confirm.ahead');
                $estimateConTime = $dlyTime - $ahead * 3600;

                $estimateConTime = $this->_platform->_ordersdf['o2o_info']['timeConfirm'] ? $this->_platform->_ordersdf['o2o_info']['timeConfirm'] : $estimateConTime;
            }
            $storeCode = $this->_platform->_ordersdf['o2o_info']['store_code'];
        }
    
        //顺手买一件活动
        $activityOidList = $this->getActivityOidList($this->_platform->_ordersdf);
        
        $abnormalList = [];
        $is_fail_order = false;
        $shop_id       = $this->_platform->__channelObj->channel['shop_id'];
        
        $presaleNum = 0;
        foreach ($this->_platform->_ordersdf['order_objects'] as $object)
        {
            $order_oid = $object['oid'];
            $goods_bn = $object['bn'];
            $obj_status = $object['status'];
            $is_setmapping = false; // 是否SET商品映射
            
            //子单纬度的择仓、择配
            $logisObjInfo = $logisObjects[$order_oid];
            
            //store_code
            if($this->_platform->__channelObj->channel['config']['aging']) {
                $storeCode = ($pIndex = strpos($object['store_code'], '___')) ? substr($object['store_code'], 0, $pIndex) : $object['store_code'];
                $aging = $this->_platform->__channelObj->channel['config']['aging'];
                if($aging[$storeCode]) {
                    $storeCode = $aging[$storeCode];
                } elseif ($storeCode && $this->_platform->_ordersdf['cn_info']['asdp_biz_type'] == 'logistics_upgrade') {
            
                    $this->_platform->_newOrder['abnormal_status'] = ome_preprocess_const::__CPUPAB_CODE;
                }
            }
            
            $quantity   = $object['quantity'] ? $object['quantity'] : 1;
            $obj_amount = $object['amount'] ? $object['amount'] : bcmul($quantity, $object['price'], 3);

            $pmt_price = (isset($object['pmt_price']) && is_numeric($object['pmt_price'])) ? (string)$object['pmt_price'] : '0';
            $obj_sale_price = (isset($object['sale_price']) && is_numeric($object['sale_price']) && -1 != bccomp($object['sale_price'], 0, 3)) ? $object['sale_price'] : bcsub($obj_amount, $pmt_price, 3);
            $obj_type       = 'goods';
            
            // 根据来源店铺及销售物料货号获取销售物料信息
            $salesMInfo = $salesMLib->getSalesMByBn($shop_id, $object['bn']);
            if (!$salesMInfo) {
                if($is_custom_order && $obj_status == 'close'){
                    //定制订单object层close状态时,不进行失败订单
                }else{
                    $is_fail_order = true;
                    
                    //异常信息
                    $abnormalList[$goods_bn] = '销售物料编码：'.$goods_bn.'不存在';
                }
            }
            
            $order_items = array();
            if ($salesMInfo) {
                $basicMInfos = array();
                if ($salesMInfo['sales_material_type'] == 5) {
                    //多选一
                    $basicMInfos = $salesMLib->get_order_pickone_bminfo($salesMInfo['sm_id'], $quantity, $shop_id);
                    $obj_type = 'pko';
                }elseif ($salesMInfo['sales_material_type'] == 7) {
                    //福袋组合
                    $luckybagParams = $object;
                    $luckybagParams['sm_id'] = $salesMInfo['sm_id'];
                    $luckybagParams['sale_material_nums'] = $quantity;
                    $luckybagParams['shop_bn'] = $this->_platform->__channelObj->channel['shop_bn'];
                    
                    $fdResult = $fudaiLib->process($luckybagParams);
                    if($fdResult['rsp'] == 'succ'){
                        $basicMInfos = $fdResult['data'];
                    }else{
                        //标记福袋分配错误信息
                        $this->_platform->_newOrder['luckybag_error'] = $fdResult['error_msg'];
                    }
                    
                    $obj_type = 'lkb';
                } else {
                    $basicMInfos = $salesMLib->getBasicMBySalesMId($salesMInfo['sm_id']);
                    
                    //sales_material_type
                    if ($salesMInfo['sales_material_type'] == 2){
                        $obj_type = 'pkg';
                        
                        // SET商品打标记
                        // @todo：只要销售物料是PKG组合类型，都需要按SET商品价格计算；
                        $is_setmapping = true;
                        
                    }elseif ($salesMInfo['sales_material_type'] == 3) {
                        $obj_type = 'gift';
                    }
                }
                
                //check
                if(empty($basicMInfos)){
                    //失败订单标识
                    $is_fail_order = true;
                    
                    //异常信息
                    if($obj_type == 'lkb'){
                        $abnormalList[$goods_bn] = '销售物料编码：'.$goods_bn.'('. $fdResult['error_msg'] .')';
                    }else{
                        $abnormalList[$goods_bn] = '销售物料编码：'.$goods_bn.'没有关联基础物料';
                    }
                }else{
                    //组织item数据
                    switch ($salesMInfo['sales_material_type']) {
                        case "2":
                            $salesMLib->calProSaleMPriceByRate($obj_sale_price, $basicMInfos);
                            $price_rate     = $salesMLib->calProPriceByRate($object['price'], $basicMInfos);
                            $pmt_price_rate = $salesMLib->calpmtpriceByRate($object['pmt_price'], $basicMInfos);
                            $obj_type       = 'pkg';
                            break;
                        case "3":
                            $obj_type = 'gift';
                            break;
                        case "5":
                            $obj_type = 'pko';
                            break;
                        case '6':
                            $obj_type = 'giftpackage';
                            break;
                        case '7':
                            //福袋组合
                            $obj_type = 'lkb';
                            break;
                    }

                    foreach ($basicMInfos as $k => $basicMInfo)
                    {
                        //福袋组合ID
                        $luckybag_id = ($basicMInfo['combine_id'] ? $basicMInfo['combine_id'] : 0);
                        
                        //obj_type
                        if ($obj_type == 'pkg') {
                            $cost = $basicMInfo['cost'];
                            //$price      = $price_rate[$basicMInfo['material_bn']] ? bcdiv($price_rate[$basicMInfo['material_bn']]['rate_price'], $price_rate[$basicMInfo['material_bn']]['number'], 2) : 0.00;

                            $pmt_price = $pmt_price_rate[$basicMInfo['material_bn']] ? ($pmt_price_rate[$basicMInfo['material_bn']]['rate_price'] > 0 ? $pmt_price_rate[$basicMInfo['material_bn']]['rate_price'] : 0) : 0.00;

                            $sale_price        = $basicMInfo['rate_price'] > 0 ? $basicMInfo['rate_price'] : 0;
                            $amount            = bcadd((float)$pmt_price, (float)$sale_price, 2);
                            $price             = bcdiv($amount, $basicMInfo['number'] * $object['quantity'], 2);
                            $weight            = $basicMInfo['weight'];
                            $divide_order_fee  = 0;
                            $part_mjz_discount = 0;
                            $item_type         = 'pkg';
                            $quantity          = $basicMInfo['number'] * $object['quantity'];
                        } elseif ($obj_type == 'pko') {
                            //多选一
                            $cost              = (float) $object['cost'] ? $object['cost'] : $basicMInfo['cost'];
                            $price             = (float) $object['price'];
                            $pmt_price         = 0;
                            $sale_price        = bcmul($obj_sale_price / $object['quantity'], $basicMInfo['number'], 3);
                            $amount            = $sale_price;
                            $weight            = $basicMInfo['weight'] ? $basicMInfo['weight'] * $basicMInfo['number'] : 0.00;
                            $divide_order_fee  = 0;
                            $part_mjz_discount = 0;
                            $item_type         = 'pko';
                            $quantity          = $basicMInfo['number'];
                        } elseif ($obj_type == 'lkb') {
                            //福袋组合
                            $item_type = 'lkb';
                            
                            $pmt_price = 0;
                            $part_mjz_discount = 0;
                            
                            $cost = $basicMInfo['cost'];
                            $price = $basicMInfo['price'];
                            
                            //@todo：福袋分配基础物料时,已经按照销售物料购买数量分摊好了基础物料数量;
                            //$sale_price = $price * $object['quantity'] * $basicMInfo['number'];
                            //$amount = $price * $object['quantity'] * $basicMInfo['number'];
                            //$divide_order_fee  = $price * $object['quantity'] * $basicMInfo['number'];
                            //$weight = $basicMInfo['weight'] * $object['quantity'] * $basicMInfo['number'];
                            
                            $sale_price = $price * $basicMInfo['number'];
                            $amount = $sale_price;
                            $divide_order_fee = $sale_price;
                            $weight = $basicMInfo['weight'] * $basicMInfo['number'];
                            
                            //基础物料购买数量
                            //@todo：福袋分配基础物料时,已经按照销售物料购买数量分摊好了基础物料数量;
                            //$quantity = $basicMInfo['number'] * $object['quantity'];
                            $quantity = $basicMInfo['number'];
                        } else {
                            $cost              = (float) $object['cost'] ? $object['cost'] : $basicMInfo['cost'];
                            $price             = (float) $object['price'];
                            $pmt_price         = (isset($object['pmt_price']) && is_numeric($object['pmt_price'])) ? (string)$object['pmt_price'] : '0';
                            $sale_price        = (isset($object['sale_price']) && is_numeric($object['sale_price']) && -1 != bccomp($object['sale_price'], 0, 3)) ? $object['sale_price'] : bcsub($obj_amount, $pmt_price, 3);
                            $amount            = $obj_amount;
                            $weight            = (float) $object['weight'] ? $object['weight'] : ($basicMInfo['weight'] ? $basicMInfo['weight'] : 0.00);
                            $item_type         = $obj_type == 'goods' ? 'product' : $obj_type;
                            $divide_order_fee  = $object['divide_order_fee'];
                            $part_mjz_discount = $object['part_mjz_discount'];
                            $quantity          = $basicMInfo['number'] * $object['quantity'];
                        }
                        
                        $order_items[] = array(
                            'shop_goods_id'     => $object['shop_goods_id'] ? $object['shop_goods_id'] : 0,//平台商品ID
                            'product_id'        => $basicMInfo['bm_id'] ? $basicMInfo['bm_id'] : 0,
                            'shop_product_id'   => $object['shop_product_id'] ? $object['shop_product_id'] : 0,//平台SkuID
                            'bn'                => $basicMInfo['material_bn'],
                            'name'              => $basicMInfo['material_name'],
                            'cost'              => $cost ? $cost : 0.00,
                            'price'             => $price ? $price : 0.00,
                            'pmt_price'         => $pmt_price,
                            'sale_price'        => $sale_price ? $sale_price : 0.00,
                            'amount'            => $amount ? $amount : 0.00,
                            'weight'            => $weight ? $weight : 0.00,
                            'quantity'          => $quantity,
                            'addon'             => '',
                            'item_type'         => $item_type,
                            'delete'            => ($object['status'] == 'close') ? 'true' : 'false',
                            'divide_order_fee'  => $divide_order_fee,
                            'part_mjz_discount' => $part_mjz_discount,
                            'product_attr'      => $object['product_attr'] ? $object['product_attr'] : "",
                            'luckybag_id' => $luckybag_id, //福袋组合ID
                        );
                    }
                }
            }
            
            $estimateConTimeOne = (int) ($estimateConTime? $estimateConTime : (is_numeric($object['estimate_con_time']) ? $object['estimate_con_time'] : 0));
            $presaleStatus = '0';
            if($this->_platform->_ordersdf['order_type'] == 'presale') {
                $presaleNum += 1;
                $presaleStatus = '1';
            }
            
            //[翱象]建议仓编码
            if($asdp_biz_type == 'aox' && $logisObjInfo['biz_store_code']){
                //仓建议为0、1时,不用使用翱象仓审单
                if(!in_array($logisObjInfo['biz_sd_type'], array('0','1'))){
                    $storeCode = $logisObjInfo['biz_store_code'];
                }
            }
    
            $objectBoolType = 0;
            if(in_array($object['oid'],$activityOidList)){
                $objectBoolType = $objectBoolType | ome_order_bool_objecttype::__ACTIVITY_PURCHASE_CODE;
            }

            // 统一gift_mids格式
            $gift_mids = '';
            if (isset($object['gift_mids']) && $object['gift_mids']) {
                if (is_string($object['gift_mids'])) {
                    $is_arr = json_decode($object['gift_mids'], 1);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($is_arr)) { 
                        // json转成以逗号隔开的字符串
                        $gift_mids = implode(',', $is_arr);
                    } else {
                        $gift_mids = $object['gift_mids'];
                    }
                } elseif (is_array($object['gift_mids'])) {
                    $gift_mids = implode(',', $object['gift_mids']);
                } else {
                    $gift_mids = $object['gift_mids'];
                }
            }
            
            $object_addon = $this->_formatObjectAddon($object);

            $this->_platform->_newOrder['order_objects'][] = array(
                'obj_type'          => $obj_type ? $obj_type : 'goods',
                'obj_alias'         => $object['obj_alias'] ? $object['obj_alias'] : $objTypeList[$obj_type],
                'shop_goods_id'     => $object['shop_goods_id'] ? $object['shop_goods_id'] : 0,
                'goods_id'          => $salesMInfo['sm_id'] ? $salesMInfo['sm_id'] : 0,
                'bn'                => $object['bn'] ? $object['bn'] : null,
                'name'              => $object['name'],
                'price'             => $object['price'] ? (float) $object['price'] : bcdiv($obj_amount, $object['quantity'], 3),
                'amount'            => $obj_amount,
                'quantity'          => $object['quantity'],
                'weight'            => (float) $object['weight'],
                'score'             => (float) $object['score'],
                'pmt_price'         => (float) $object['pmt_price'],
                'sale_price'        => $obj_sale_price,
                'order_items'       => $order_items,
                'is_oversold'       => ($object['is_oversold'] == true) ? 1 : 0,
                'oid'               => $object['oid'],
                //flag可能从item上移到obj层上要保留的数据
                'delete'            => ($object['status'] == 'close') ? 'true' : 'false',
                'original_str'      => $object['original_str'],
                'product_attr'      => $object['product_attr'],
                'promotion_id'      => $object['promotion_id'],
                'divide_order_fee'  => $object['divide_order_fee'],
                'part_mjz_discount' => $object['part_mjz_discount'],
                'settlement_amount' => $object['settlement_amount'],
                'actually_amount'   => $object['actually_amount'],
                'sku_uuid'          => $object['sku_uuid'],
                'store_code'        => $this->_platform->__channelObj->channel['config']['clear_plate_branch'] == 'true' ? '' : ($storeCode ? : $object['store_code']), //预选仓库编码
                'estimate_con_time' => $estimateConTimeOne > 0 ? $estimateConTimeOne : null,
                'ship_status'       => (int) $object['ship_status'],
                'presale_status'    => $presaleStatus,
                'author_id'         => $object['authod_id'], //主播ID
                'author_name'       => $object['author_name'], //主播姓名
                'warehouse_ids'     => $object['warehouse_ids'], //抖音平台仓库编码
                'out_warehouse_ids' => $object['out_warehouse_ids'], //指定区域仓编码
                'is_sh_ship'        => $object['is_sh_ship'] == 'true' ? 'true' : 'false',
                's_type'            => $object['is_daixiao'] == 'true' ? 'dx' : 'zx',
                'promised_collect_time' => ($logisObjInfo['promise_collect_time'] ? $logisObjInfo['promise_collect_time'] : 0), //承诺-最晚揽收时间
                'promise_outbound_time' => ($logisObjInfo['promise_outbound_time'] ? $logisObjInfo['promise_outbound_time'] : 0), //承诺-最晚出库时间
                'biz_sd_type' => ($logisObjInfo['biz_sd_type'] ? $logisObjInfo['biz_sd_type'] : 0), //建议仓类型
                // 补充 main_oid
                'main_oid' => $gift_mids,
                'biz_delivery_type' => ($logisObjInfo['biz_delivery_type'] ? $logisObjInfo['biz_delivery_type'] : 0), //择配建议
                'object_bool_type' => $objectBoolType,
                'addon'             => $object_addon, //扩展信息
                'customization' => $object['customization'], //商品定制信息
                'is_sales_material_setmapping' => $is_setmapping, // 是否SET商品映射
            );
            
            unset($order_items);
        }
        
        if($presaleNum > 0) {
            if($presaleNum == count($this->_platform->_ordersdf['order_objects'])) {
                $this->_platform->_newOrder['presale_status'] = '1';
            } else {
                $this->_platform->_newOrder['presale_status'] = '2';
            }
        }
        
        if ($is_fail_order) {
            $this->_platform->_newOrder['is_fail']     = 'true';
            $this->_platform->_newOrder['edit_status'] = 'true';
            $this->_platform->_newOrder['archive']     = '1';
        }
        
        //订单异常信息
        if($abnormalList){
            $this->_platform->_newOrder['abnormal_msg'] = implode(';', $abnormalList);
        }
        
        //定制订单转换定制SKU异常信息
        if($is_custom_order){
            $abnormal_msg = (isset($this->_platform->_newOrder['abnormal_msg']) ? $this->_platform->_newOrder['abnormal_msg'] : '');
            if($this->_platform->_newOrder['custom_transform_status']=='fail' && $this->_platform->_newOrder['custom_abnormal_msg']){
                $this->_platform->_newOrder['abnormal_msg'] = $this->_platform->_newOrder['custom_abnormal_msg'] .'；'. $abnormal_msg;
            }
        }
    }

    /**
     * 格式化订单对象扩展信息。
     *
     * 微信视频号失败订单恢复时需要重建 item，提前备份原始平台商品和 SKU 信息，
     * 避免恢复后发货回写缺少 sku_id。
     *
     * @param array $object 平台订单对象明细
     * @return string
     */
    private function _formatObjectAddon($object)
    {
        $addon = $object['addon'];
        if ($this->_platform->_newOrder['shop_type'] != 'wxshipin') {
            return is_array($addon) ? json_encode($addon) : $addon;
        }

        // 微信视频号失败订单可能因销售物料不存在而无法生成 item 层。
        // 先把原始平台 SKU 信息备份到 object addon，供失败订单恢复重建 item 时继承。
        $platformItems = $this->_getWxshipinPlatformItems($object);
        if (!$platformItems) {
            return is_array($addon) ? json_encode($addon) : $addon;
        }

        if (is_array($addon)) {
            $addonData = $addon;
        } else {
            $addonData = $addon ? json_decode($addon, true) : array();
            if (!is_array($addonData)) {
                $addonData = array();
            }
        }
        $addonData['wxshipin_platform_items'] = $platformItems;

        return json_encode($addonData, JSON_UNESCAPED_UNICODE);
    }

    private function _getWxshipinPlatformItems($object)
    {
        $items = array();
        // formatItemsSdf 会把单 item 订单合并到 object 层，并移除 order_items。
        // 因此这里同时兼容 object 层和原始 item 层的平台 SKU，避免失败订单恢复时丢失 sku_id。
        $shopProductId = ($object['shop_product_id'] && $object['shop_product_id'] != '0') ? $object['shop_product_id'] : '';
        foreach ((array)$object['order_items'] as $item) {
            if (!$shopProductId && $item['shop_product_id'] && $item['shop_product_id'] != '0') {
                $shopProductId = $item['shop_product_id'];
            }

            $items[] = array(
                'bn'              => $item['bn'],
                'shop_goods_id'   => $item['shop_goods_id'] ? $item['shop_goods_id'] : $object['shop_goods_id'],
                'shop_product_id' => $item['shop_product_id'],
                'oid'             => $object['oid'],
            );
        }
        if (!$items && $shopProductId) {
            $items[] = array(
                'bn'              => $object['bn'],
                'shop_goods_id'   => $object['shop_goods_id'],
                'shop_product_id' => $shopProductId,
                'oid'             => $object['oid'],
            );
        }

        if (!$items && !$shopProductId) {
            return array();
        }

        return array(
            'shop_goods_id'   => $object['shop_goods_id'],
            'shop_product_id' => $shopProductId,
            'oid'             => $object['oid'],
            'items'           => $items,
        );
    }

    /**
     * 更新订单明细
     *
     * @return void
     * @author
     **/
    public function update()
    {
        $orderItemMdl = app::get('ome')->model('order_items');
        
        $fudaiLib = kernel::single('material_fukubukuro_dispatch');
        
        //obj_type
        $objTypeList = $orderItemMdl->_obj_alias;
        
        // 后期修改
        if ($this->_platform->_tgOrder['ship_status'] == '0') {

            // 原单处理
            $tgOrder_object = array();
            foreach ((array) $this->_platform->_tgOrder['order_objects'] as $object) {
                
                #ERP处理过的赠品,过滤掉。具体是不是要变更赠品，放在crm这个插件
                if($object['obj_type'] == 'gift' && $object['shop_goods_id'] == '-1') continue;

                $objkey = $this->_get_obj_key($object);

                $tgOrder_object[$objkey] = $object;

                $order_items = array();
                foreach ((array) $object['order_items'] as $item) {
                    $itemkey = $this->_get_item_key($item);
                    if($item['sendnum'] > 0) {
                        $arrSendObj[] = $objkey;
                        unset($tgOrder_object[$objkey]);
                        continue 2;
                    }
                    $order_items[$itemkey] = $item;
                }
                $tgOrder_object[$objkey]['order_items'] = $order_items;
            }

            $ordersdf = $this->_platform->_ordersdf;

            //组织天下掉下来的新数据
            $sky_ordersdf_is_fail_order = false;
            $salesMLib                  = kernel::single('material_sales_material');
            $basicMStockFreezeLib       = kernel::single('material_basic_material_stock_freeze');
            
            //asdp_biz_type
            $asdp_biz_type = $this->_platform->_ordersdf['cn_info']['asdp_biz_type'];
            if(empty($asdp_biz_type) && $this->_platform->_ordersdf['cn_info']){
                $asdp_biz_type = $this->_platform->_ordersdf['cn_info']['logistics_agreement']['asdp_biz_type'];
            }
            
            //[翱象]子单纬度的择仓、择配
            $logisObjects = array();
            if($asdp_biz_type == 'aox'){
                $logisObjects = $this->_formatAoxiangObjectData($this->_platform->_ordersdf);
            }
    
            // 接收的参数
            $ordersdf_object = array();
            foreach ((array) $ordersdf['order_objects'] as $object)
            {
                $order_oid = $object['oid'];
                $is_setmapping = false; // 是否SET商品映射
                
                //子单纬度的择仓、择配
                $logisObjInfo = $logisObjects[$order_oid];
                
                //check
                if($object['is_update'] === 'false') continue;
                
                //obj基础数据格式化
                $obj_amount     = $object['amount'] ? $object['amount'] : bcmul($object['quantity'], $object['price'], 3);
                $pmt_price = (isset($object['pmt_price']) && is_numeric($object['pmt_price'])) ? (string)$object['pmt_price'] : '0';
                $obj_sale_price = (isset($object['sale_price']) && is_numeric($object['sale_price']) && -1 != bccomp($object['sale_price'], 0, 3)) ? $object['sale_price'] : bcsub($obj_amount, $pmt_price, 3);
                $obj_type       = $object['obj_type'] ? $object['obj_type'] : 'goods';
                $order_items = array();
                
                // 根据来源店铺及销售物料货号获取销售物料信息
                $salesMInfo = $salesMLib->getSalesMByBn($this->_platform->__channelObj->channel['shop_id'], $object['bn']);
                if (!$salesMInfo) {
                    $sky_ordersdf_is_fail_order = true;
                }
                
                if ($salesMInfo) {
                    $basicMInfos = array();
                    if ($salesMInfo['sales_material_type'] == 5) {
                        //多选一
                        $basicMInfos = $salesMLib->get_order_pickone_bminfo($salesMInfo['sm_id'], $object['quantity'], $this->_platform->__channelObj->channel['shop_id']);
                    }elseif ($salesMInfo['sales_material_type'] == 7) {
                        //福袋组合
                        $luckybagParams = $object;
                        $luckybagParams['sm_id'] = $salesMInfo['sm_id'];
                        $luckybagParams['sale_material_nums'] = $object['quantity'];
                        $luckybagParams['shop_bn'] = $this->_platform->__channelObj->channel['shop_bn'];
                        
                        $fdResult = $fudaiLib->process($luckybagParams);
                        if($fdResult['rsp'] == 'succ'){
                            $basicMInfos = $fdResult['data'];
                        }else{
                            //标记福袋分配错误信息
                            $this->_platform->_newOrder['luckybag_error'] = $fdResult['error_msg'];
                        }
                        
                        //unset
                        unset($luckybagParams, $fdResult);
                    }else {
                        $basicMInfos = $salesMLib->getBasicMBySalesMId($salesMInfo['sm_id']);
                        
                        // sales_material_type
                        if ($salesMInfo['sales_material_type'] == 2) {
                            // SET商品打标记
                            // @todo：只要销售物料是PKG组合类型，都需要按SET商品价格计算；
                            $is_setmapping = true;
                        }
                    }
                    
                    if (!$basicMInfos) {
                        $sky_ordersdf_is_fail_order = true;
                    }
                    if ($basicMInfos) {
                        switch ($salesMInfo['sales_material_type']) {
                            case "2":
                                $salesMLib->calProSaleMPriceByRate($obj_sale_price, $basicMInfos);
                                $price_rate     = $salesMLib->calProPriceByRate($object['price'], $basicMInfos);
                                $pmt_price_rate = $salesMLib->calpmtpriceByRate($object['pmt_price'], $basicMInfos);
                                $obj_type       = 'pkg';
                                break;
                            case "3":
                                $obj_type = 'gift';
                                break;
                            case "5":
                                $obj_type = 'pko';
                                break;
                            case '6':
                                $obj_type = 'giftpackage';
                                break;
                            case '7':
                                //福袋组合
                                $obj_type = 'lkb';
                                break;
                            default:
                                $obj_type = 'goods';
                                break;
                        }

                        //组织item数据
                        foreach ($basicMInfos as $k => $basicMInfo)
                        {
                            //福袋组合ID
                            $luckybag_id = ($basicMInfo['combine_id'] ? $basicMInfo['combine_id'] : 0);
                            
                            $cost              = (float) $object['cost'] ? $object['cost'] : $basicMInfo['cost'];
                            $price             = (float) $object['price'];
                            $pmt_price         = (isset($object['pmt_price']) && is_numeric($object['pmt_price'])) ? (string)$object['pmt_price'] : '0';
                            $sale_price        = $obj_sale_price;
                            $amount            = $obj_amount;
                            $weight            = (float) $object['weight'] ? $object['weight'] : ($basicMInfo['weight'] ? $basicMInfo['weight'] : 0.00);
                            $quantity          = $basicMInfo['number'] * $object['quantity'];
                            $shop_product_id   = $object['shop_product_id'];
                            $divide_order_fee  = $object['divide_order_fee'];
                            $part_mjz_discount = $object['part_mjz_discount'];
                            $item_type         = $obj_type == 'goods' ? 'product' : $obj_type;
                            if ($obj_type == 'pkg') {
                                $cost = $basicMInfo['cost'];
                                //$price           = $basicMInfo['rate_price'] ? bcdiv($basicMInfo['rate_price'], $quantity, 2) : 0.00;
                                $pmt_price = $pmt_price_rate[$basicMInfo['material_bn']] ? ($pmt_price_rate[$basicMInfo['material_bn']]['rate_price'] > 0 ? $pmt_price_rate[$basicMInfo['material_bn']]['rate_price'] : 0) : 0.00;

                                $sale_price        = $basicMInfo['rate_price'] > 0 ? $basicMInfo['rate_price'] : 0;

                                $amount = bcadd((float)$pmt_price, (float)$sale_price, 2);

                                $price             = bcdiv($amount, $basicMInfo['number'] * $object['quantity'], 2);
                                $weight            = $basicMInfo['weight'];
                                $divide_order_fee  = 0;
                                $part_mjz_discount = 0;

                                $item_type = 'pkg';
                            } elseif ($obj_type == 'lkb') {
                                //福袋组合
                                $item_type = 'lkb';
                                
                                //基础物料购买数量
                                //@todo：福袋分配基础物料时,已经按照销售物料购买数量分摊好了基础物料数量;
                                //$quantity = $basicMInfo['number'] * $object['quantity'];
                                $quantity = $basicMInfo['number'];
                                
                                $cost              = $basicMInfo['cost'];
                                $price             = $basicMInfo['price'];
                                $pmt_price         = 0;
                                $sale_price        = $basicMInfo['price'] * $quantity;
                                $amount            = $basicMInfo['price'] * $quantity;
                                $weight            = $basicMInfo['weight'] * $quantity;
                                $divide_order_fee  = 0;
                                $part_mjz_discount = 0;
                                $shop_product_id   = 0;
                            } elseif ($obj_type == 'pko') {
                                $pmt_price         = 0;
                                $sale_price        = bcmul($obj_sale_price / $object['quantity'], $basicMInfo['number'], 3);
                                $amount            = $sale_price;
                                $weight            = $basicMInfo['weight'] ? $basicMInfo['weight'] * $basicMInfo['number'] : 0.00;
                                $divide_order_fee  = 0;
                                $part_mjz_discount = 0;
                                $item_type         = 'pko';
                                $quantity          = $basicMInfo['number'];
                            }
                            
                            //item
                            $itemtmp = array(
                                'shop_goods_id'     => $object['shop_goods_id'] ? $object['shop_goods_id'] : 0,
                                'product_id'        => $basicMInfo['bm_id'] ? $basicMInfo['bm_id'] : 0,
                                'shop_product_id'   => (int)$shop_product_id,
                                'bn'                => $basicMInfo['material_bn'],
                                'name'              => $basicMInfo['material_name'],
                                'cost'              => $cost,
                                'price'             => $price,
                                'pmt_price'         => $pmt_price,
                                'sale_price'        => $sale_price,
                                'amount'            => $amount,
                                'weight'            => $weight,
                                'quantity'          => $quantity,
                                'addon'             => '',
                                'item_type'         => $item_type,
                                'delete'            => ($object['status'] == 'close') ? 'true' : 'false',
                                'order_id'          => $this->_platform->_tgOrder['order_id'],
                                'divide_order_fee'  => $divide_order_fee,
                                'part_mjz_discount' => $part_mjz_discount,
                                'luckybag_id' => $luckybag_id, //福袋组合ID
                            );
                            
                            $itemkey               = $this->_get_item_key($itemtmp);
                            $order_items[$itemkey] = $itemtmp;
                        }
                    }
                }
    
                $objecttmp = array(
                    'obj_type'          => $obj_type,
                    'obj_alias'         => $object['obj_alias'] ? $object['obj_alias'] : $objTypeList[$obj_type],
                    'shop_goods_id'     => $object['shop_goods_id'] ? $object['shop_goods_id'] : 0,
                    'goods_id'          => $salesMInfo['sm_id'] ? $salesMInfo['sm_id'] : 0,
                    'bn'                => $object['bn'] ? $object['bn'] : null,
                    'name'              => $object['name'],
                    'price'             => $object['price'] ? (float) $object['price'] : bcdiv($obj_amount, $object['quantity'], 3),
                    'amount'            => $obj_amount,
                    'quantity'          => $object['quantity'],
                    'weight'            => (float) $object['weight'],
                    'score'             => (float) $object['score'],
                    'pmt_price'         => (float) $object['pmt_price'],
                    'sale_price'        => (float) $obj_sale_price,
                    'order_items'       => $order_items,
                    'is_oversold'       => ($object['is_oversold'] == true) ? 1 : 0,
                    'oid'               => $object['oid'],
                    'order_id'          => $this->_platform->_tgOrder['order_id'],
                    'delete'            => ($object['status'] == 'close') ? 'true' : 'false',
                    'divide_order_fee'  => $object['divide_order_fee'],
                    'part_mjz_discount' => $object['part_mjz_discount'],
                    'settlement_amount' => $object['settlement_amount'],
                    'actually_amount'   => $object['actually_amount'],
                    'sku_uuid'          => $object['sku_uuid'],
                    'promised_collect_time' => ($logisObjInfo['promise_collect_time'] ? $logisObjInfo['promise_collect_time'] : 0), //承诺-最晚揽收时间
                    'promise_outbound_time' => ($logisObjInfo['promise_outbound_time'] ? $logisObjInfo['promise_outbound_time'] : 0), //承诺-最晚出库时间
                    'biz_sd_type' => ($logisObjInfo['biz_sd_type'] ? $logisObjInfo['biz_sd_type'] : 0), //建议仓类型
                    'biz_delivery_type' => ($logisObjInfo['biz_delivery_type'] ? $logisObjInfo['biz_delivery_type'] : 0), //择配建议
                    //'customization' => $object['customization'], //商品定制信息
                    'is_sales_material_setmapping' => $is_setmapping, // 是否SET商品映射
                );
                
                //store_code
                $store_code = ($pIndex = strpos($object['store_code'], '___')) ? substr($object['store_code'], 0, $pIndex) : $object['store_code'];
                if($this->_platform->__channelObj->channel['config']['aging']) {
                    $aging = $this->_platform->__channelObj->channel['config']['aging'];
                    if($aging[$store_code]) {
                        $store_code = $aging[$store_code];
                    }
                }
                
                //[翱象]建议仓编码
                if($asdp_biz_type == 'aox' && $logisObjInfo['biz_store_code']){
                    //仓建议为0、1时,不用使用翱象仓审单
                    if(!in_array($logisObjInfo['biz_sd_type'], array('0','1'))){
                        $store_code = $logisObjInfo['biz_store_code'];
                    }
                }
                if($this->_platform->__channelObj->channel['config']['clear_plate_branch'] == 'true') {
                    $store_code = '';
                }
                //store_code
                $objecttmp['store_code'] = $store_code;
                
                unset($order_items);
                $objkey                   = $this->_get_obj_key($objecttmp);
                $ordersdf_object[$objkey] = $objecttmp;
            }
            $doti = [
                'discount' => $ordersdf['discount'],
                'pmt_order' => $ordersdf['pmt_order'],
                'order_objects'=>$ordersdf_object
            ];
            kernel::single('ome_order')->create_divide_pay($doti);
            $ordersdf_object = $doti['order_objects'];
            
            // 判断ITEM有没有
            $need_del_info = array();
            $branchBatchList = [];
            foreach ($tgOrder_object as $objkey => $object) {
                $has_old_item_del = false;
                foreach ($object['order_items'] as $itemkey => $item) {
                    // 如果已经被删除，则跳过
                    if ($item['delete'] == 'true') {
                        continue;
                    }

                    // ITEM被删除
                    if (!$ordersdf_object[$objkey]['order_items'][$itemkey]) {
                        $this->_platform->_newOrder['order_objects'][$objkey]['obj_id']                = $object['obj_id'];
                        $this->_platform->_newOrder['order_objects'][$objkey]['delete']                = 'true';
                        if($ordersdf['old_obj_id'] && in_array($object['obj_id'], $ordersdf['old_obj_id'])) {
                            $this->_platform->_newOrder['order_objects'][$objkey]['pay_status'] = '5';
                        }
                        $this->_platform->_newOrder['order_objects'][$objkey]['order_items'][$itemkey] = array('item_id' => $item['item_id'], 'delete' => 'true');

                        //后续操作删除数据用
                        if ($item["item_type"] == "pko" || $item["item_type"] == "lkb") {
                            if (!isset($need_del_info[$object['obj_id']])) {
                                $need_del_info[$object['obj_id']] = array(
                                    "del_objkey" => $objkey,
                                );
                            }
                            $need_del_info[$object['obj_id']]["items"][] = array(
                                "item_id"       => $item["item_id"],
                                "itemkey"       => $itemkey,
                                "item_log_text" => "基础物料ID：" . $item["product_id"] . "；编码：" . $item["bn"] . "；单价：" . $item["price"] . "；数量：" . $item["quantity"] . "；类型：" . $item["item_type"] . "。",
                            );
                        }

                        // 扣库存
                        if ($item['product_id']) {

                            //[扣减]基础物料店铺冻结
                            $branchBatchList[] = [
                                'bm_id'     =>  $item['product_id'],
                                'sm_id'     =>  $object['goods_id'],
                                'obj_type'  =>  material_basic_material_stock_freeze::__ORDER,
                                'bill_type' =>  0,
                                'obj_id'    =>  $this->_platform->_tgOrder['order_id'],
                                'branch_id' =>  '',
                                'bmsq_id'   =>  material_basic_material_stock_freeze::__SHARE_STORE,
                                'num'       =>  $item['quantity'], 
                            ];
                            $has_old_item_del = true;

                        }
                    }
                }
            }
            
            //[扣减]基础物料店铺冻结
            $err = '';
            $basicMStockFreezeLib->unfreezeBatch($branchBatchList, __CLASS__.'::'.__FUNCTION__, $err);

            $branchBatchList = [];
            $actually_amount_diff = 0;
            $settlement_amount_diff = 0;
            // 字段比较
            foreach ($ordersdf_object as $objkey => $object) {
                $obj_id      = $tgOrder_object[$objkey]['obj_id'];
                $order_items = $object['order_items'];unset($object['order_items']);

                $object = array_filter($object, array($this, 'filter_null'));
                // OBJECT比较
                $diff_obj = array_udiff_assoc((array) $object, (array) $tgOrder_object[$objkey], array($this, 'comp_array_value'));

                if ($diff_obj) {
                    $diff_obj['obj_id'] = $obj_id;

                    $this->_platform->_newOrder['order_objects'][$objkey] = array_merge((array) $this->_platform->_newOrder['order_objects'][$objkey], (array) $diff_obj);
                    if($this->_platform->_newOrder['order_objects'][$objkey]['delete'] == 'true' && $ordersdf['old_obj_id'] && in_array($obj_id, $ordersdf['old_obj_id'])) {
                        $this->_platform->_newOrder['order_objects'][$objkey]['pay_status'] = '5';
                    }
                    if($diff_obj['delete'] == 'true') {
                        $actually_amount_diff += 0 - $tgOrder_object[$objkey]['actually_amount'];
                        $settlement_amount_diff += 0 - $tgOrder_object[$objkey]['settlement_amount'];
                    } else {
                        $actually_amount_diff += $object['actually_amount'] - $tgOrder_object[$objkey]['actually_amount'];
                        $settlement_amount_diff += $object['settlement_amount'] - $tgOrder_object[$objkey]['settlement_amount'];
                    }

                }
                
                foreach ($order_items as $itemkey => $item) {
                    $item = array_filter($item, array($this, 'filter_null'));
                    if(isset($item['porth_field'])) {
                        unset($item['porth_field']);
                    }
                    // ITEM比较
                    $item_id   = $tgOrder_object[$objkey]['order_items'][$itemkey]['item_id'];
                    $diff_item = array_udiff_assoc((array) $item, (array) $tgOrder_object[$objkey]['order_items'][$itemkey], array($this, 'comp_array_value'));

                    if ($diff_item) {
                        $diff_item['item_id'] = $item_id;

                        $this->_platform->_newOrder['order_objects'][$objkey]['order_items'][$itemkey] = array_merge((array) $this->_platform->_newOrder['order_objects'][$objkey]['order_items'][$itemkey], (array) $diff_item);

                        if ($diff_item['delete'] == 'false' && $item['product_id']) {

                            $freezeData = [];
                            $freezeData['bm_id'] = $item['product_id'];
                            $freezeData['sm_id'] = $object['goods_id'];
                            $freezeData['obj_type'] = material_basic_material_stock_freeze::__ORDER;
                            $freezeData['bill_type'] = 0;
                            $freezeData['obj_id'] = $this->_platform->_tgOrder['order_id'];
                            $freezeData['shop_id'] = $this->_platform->_tgOrder['shop_id'];
                            $freezeData['branch_id'] = 0;
                            $freezeData['bmsq_id'] = material_basic_material_stock_freeze::__SHARE_STORE;
                            $freezeData['num'] = $item['quantity'];
                            $freezeData['obj_bn'] = $this->_platform->_tgOrder['order_bn'];
                            //[增加]基础物料店铺冻结
                            $branchBatchList['+'][] = $freezeData;

                        } elseif ($diff_item['delete'] == 'true' && $item['product_id']) {

                            //[扣减]基础物料店铺冻结
                            $branchBatchList['-'][] = [
                                'bm_id'     =>  $item['product_id'],
                                'sm_id'     =>  $object['goods_id'],
                                'obj_type'  =>  material_basic_material_stock_freeze::__ORDER,
                                'bill_type' =>  0,
                                'obj_id'    =>  $this->_platform->_tgOrder['order_id'],
                                'branch_id' =>  '',
                                'bmsq_id'   =>  material_basic_material_stock_freeze::__SHARE_STORE,
                                'num'       =>  $tgOrder_object[$objkey]['order_items'][$itemkey]['quantity'], 
                            ];

                        } elseif (isset($diff_item['quantity']) && $item['product_id']) {
                            // 如果库存发生变化，
                            $diff_quantity = bcsub($diff_item['quantity'], $tgOrder_object[$objkey]['order_items'][$itemkey]['quantity']);

                            if ($diff_quantity > 0) {

                                $freezeData = [];
                                $freezeData['bm_id'] = $item['product_id'];
                                $freezeData['sm_id'] = $object['goods_id'];
                                $freezeData['obj_type'] = material_basic_material_stock_freeze::__ORDER;
                                $freezeData['bill_type'] = 0;
                                $freezeData['obj_id'] = $this->_platform->_tgOrder['order_id'];
                                $freezeData['shop_id'] = $this->_platform->_tgOrder['shop_id'];
                                $freezeData['branch_id'] = 0;
                                $freezeData['bmsq_id'] = material_basic_material_stock_freeze::__SHARE_STORE;
                                $freezeData['num'] = abs($diff_quantity);
                                $freezeData['obj_bn'] = $this->_platform->_tgOrder['order_bn'];
                                //[增加]基础物料店铺冻结
                                $branchBatchList['+'][] = $freezeData;

                            } elseif ($diff_quantity < 0) {

                                //[扣减]基础物料店铺冻结
                                $branchBatchList['-'][] = [
                                    'bm_id'     =>  $item['product_id'],
                                    'sm_id'     =>  $object['goods_id'],
                                    'obj_type'  =>  material_basic_material_stock_freeze::__ORDER,
                                    'bill_type' =>  0,
                                    'obj_id'    =>  $this->_platform->_tgOrder['order_id'],
                                    'branch_id' =>  '',
                                    'bmsq_id'   =>  material_basic_material_stock_freeze::__SHARE_STORE,
                                    'num'       =>  abs($diff_quantity), 
                                ];

                            }

                        }

                        $this->_platform->_newOrder['order_objects'][$objkey]['obj_id'] = $obj_id;
                    }

                }
            }

            //[增加]基础物料店铺冻结
            if ($branchBatchList['+']) {
                $basicMStockFreezeLib->freezeBatch($branchBatchList['+'], __CLASS__.'::'.__FUNCTION__, $err);
            }

            //[扣减]基础物料店铺冻结
            if ($branchBatchList['-']) {
                $basicMStockFreezeLib->unfreezeBatch($branchBatchList['-'], __CLASS__.'::'.__FUNCTION__, $err);
            }
            if ($actually_amount_diff != 0) {
                $this->_platform->_newOrder['actually_amount'] = $this->_platform->_tgOrder['actually_amount'] + $actually_amount_diff;
            }
            if ($settlement_amount_diff != 0) {
                $this->_platform->_newOrder['settlement_amount'] = $this->_platform->_tgOrder['settlement_amount'] + $settlement_amount_diff;
            }
            if ($sky_ordersdf_is_fail_order) {
                $this->_platform->_newOrder['is_fail']     = 'true';
                $this->_platform->_newOrder['edit_status'] = 'true';
                $this->_platform->_newOrder['archive']     = '1';
            }
            if ($this->_platform->_newOrder['is_fail'] != 'true' && $this->_platform->_tgOrder['is_fail'] == 'true') {
                $this->_platform->_newOrder['is_fail']     = 'false';
                $this->_platform->_newOrder['edit_status'] = 'false';
                $this->_platform->_newOrder['archive']     = '0';
            }

            if (!empty($need_del_info)) {
                //写删除日志并删除明细数据
                $mdl_ome_order_items = app::get('ome')->model('order_items');
                $write_log           = array();
                foreach ($need_del_info as $obj_id => $obj_info) {
                    $current_memo = "";
                    foreach ($obj_info["items"] as $var_item) {
                        unset($this->_platform->_newOrder["order_objects"][$obj_info["del_objkey"]]["order_items"][$var_item["itemkey"]]);
                        $current_memo .= $var_item["item_log_text"];
                        $mdl_ome_order_items->delete(array("item_id" => $var_item["item_id"]));
                    }
                    $write_log[] = array(
                        'obj_id'    => $this->_platform->_tgOrder['order_id'],
                        'obj_name'  => $this->_platform->_tgOrder['order_bn'],
                        'operation' => 'order_modify@ome',
                        'memo'      => $current_memo,
                    );
                }
                if (!empty($write_log)) {
                    app::get('ome')->model('operation_log')->batch_write_log2($write_log);
                }
            }

        }
    }

    private function _get_obj_key($object)
    {
        $objkey = '';
        foreach (explode('-', $this->_platform->object_comp_key) as $field) {
            $objkey .= ($object[$field] ? trim($object[$field]) : '') . '-';
        }

        return sprintf('%u', crc32(ltrim($objkey, '-')));
    }

    private function _get_item_key($item)
    {
        $itemkey = '';
        foreach (explode('-', $this->_platform->item_comp_key) as $field) {
            if ($field == 'unit_sale_price') {
                $itemkey .= bcdiv((float) $item['sale_price'], $item['quantity'], 3) . '-';
            } else {
                $itemkey .= ($item[$field] ? $item[$field] : '') . '-';
            }
        }

        return sprintf('%u', crc32(ltrim($itemkey, '-')));
    }
    
    /**
     * 格式化翱象数据
     *
     * @param $ordersdf
     * @return void
     */
    public function _formatAoxiangObjectData($ordersdf)
    {
        //子单纬度的择仓、择配
        $logistics_infos = $ordersdf['logistics_infos'];
        
        //check
        if(empty($logistics_infos) || !is_array($logistics_infos)) {
            return array();
        }
        
        //子单物流发货信息
        $logisObjects = array();
        foreach($logistics_infos as $key => $val)
        {
            $oid = $val['sub_trade_id'];
            
            //check
            if(empty($oid)){
                continue;
            }
            
            //format
            $val['promise_collect_time'] = ($val['promise_collect_time'] ? strtotime($val['promise_collect_time']) : 0);
            $val['promise_outbound_time'] = ($val['promise_outbound_time'] ? strtotime($val['promise_outbound_time']) : 0);
            
            //item
            $logisObjects[$oid] = $val;
        }
        
        return $logisObjects;
    }
    
    /**
     * 顺手买一件活动明细标识
     * @param $sdf
     * @return array
     * @date 2024-04-23 3:35 下午
     */
    public function getActivityOidList($sdf)
    {
        $oidList = [];//顺手买一件oid
        if ($sdf['pmt_detail']) {
            foreach ((array)$sdf['pmt_detail'] as $key => $pmt) {
                if (strpos($pmt['pmt_describe'], '顺手买一件活动') !== false) {
                    $oidList[] = $pmt['pmt_id'];//pmt_id=oid
                }
            }
        }
        return $oidList;
    }
}
