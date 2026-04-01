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
 * 商品分配推送
 *
 * @category 
 * @package 
 * @author chenping<chenping@shopex.cn>
 * @version $Id: Z
 */
class erpapi_wms_request_goods extends erpapi_wms_request_abstract
{

    /**
     * 商品添加
     *
     * @return void
     * @author 
     **/
    public function goods_add($sdf){
        $title = $this->__channelObj->wms['channel_name'] . '商品添加';

        $inner_sku = array();
        foreach ((array) $sdf as $key=>$pro) {
            if (!is_array($pro)) {
                unset($sdf[$key]); continue;
            }

            if ($pro['bn']) $inner_sku[] = $pro['bn'];
        }

        if (!$sdf) return;

        $params = $this->_format_goods_params($sdf);

        $callback = array(
            'class'  => get_class($this),
            'method' => 'goods_callback',
            'params' => array('inner_sku'=>$inner_sku,'node_id'=>$this->__channelObj->wms['node_id']),
        );

        return $this->__caller->call(WMS_ITEM_ADD, $params, $callback, $title,10);
    }

    public function goods_callback($response, $callback_params)
    {
        $rsp     = $response['rsp'];
        $err_msg = $response['err_msg'];
        $data    = $response['data'];
        $msg_id  = $response['msg_id'];
        $res     = $response['res'];

        $resync_inner_sku = $inner_sku = $callback_params['inner_sku'];
        $node_id          = $callback_params['node_id'];

       //第三方仓储无此接口时,商品同步状态设为成功
        if ($res == 'w03020' || $err_msg == 'w03020'){
            $rsp = 'succ';
            $msg = '仓储物流系统无此接口';
        }

        if (!is_array($data)) $data = json_decode($data,true);

        $succ_inner_sku = array();
        if ($data && $data['succ']) {
            foreach ((array)$data['succ'] as $sku ){
                $succ_inner_sku[] = array(
                    'inner_sku' => $sku['item_code'],
                    'outer_sku' => $sku['wms_item_code'],
                    'status'    => '3',
                );


                if (false !== ($key=array_search($sku['item_code'], $resync_inner_sku))) {
                    unset($resync_inner_sku[$key]);
                }
            }
        }

        if ($succ_inner_sku) kernel::single('console_foreignsku')->set_sync_status($succ_inner_sku,$node_id);

        $foreignSkuModel = app::get('console')->model('foreign_sku');
        if ($rsp == 'succ') {  // 请求成功
            // 选置状态，后更新外部编码
            $wms_id = kernel::single('channel_func')->getWmsIdByNodeId($node_id);
            
            $foreignSkuModel->update(array('new_tag'=>'1','sync_status'=>'3'),array('inner_sku'=>$inner_sku, 'wms_id'=>$wms_id));
            
        } else {    // 请求失败
            $error_inner_sku = array();
            $succ_inner_sku = array();
            if ($data && $data['error']) {
                foreach ((array) $data['error'] as $sku ){
                    if ($sku['error_code'] != 'w03109'){
                        $error_inner_sku[] = array(
                            'inner_sku' => $sku['item_code'],
                            'outer_sku' => $sku['wms_item_code'],
                            'status'    => '1',
                        );
                    } else {
                        // 代表已经同步过了，更新状态
                        $succ_inner_sku[] = $sku['item_code'];
                    }

                    if (false !== ($key=array_search($sku['item_code'], $resync_inner_sku)) ) {
                        unset($resync_inner_sku[$key]);
                    }
                }
            }

            if ($error_inner_sku) kernel::single('console_foreignsku')->set_sync_status($error_inner_sku,$node_id);
            
            $wms_id = kernel::single('channel_func')->getWmsIdByNodeId($node_id);
            
            if ($succ_inner_sku) $foreignSkuModel->update(array('sync_status'=>'3'),array('inner_sku'=>$succ_inner_sku, 'wms_id'=>$wms_id));

            if ($resync_inner_sku) $foreignSkuModel->update(array('sync_status'=>'0'),array('inner_sku'=>$resync_inner_sku, 'wms_id'=>$wms_id));
        }

        return $this->callback($response,$callback_params);
    }

    /**
     * 商品编辑
     *
     * @return void
     * @author 
     **/
    public function goods_update($sdf){
        $title = $this->__channelObj->wms['channel_name'] . '商品编辑';

        $inner_sku = array();
        foreach ((array) $sdf as $key=>$pro) {
            if (!is_array($pro)) {
                unset($sdf[$key]); continue;
            }

            if ($pro['bn']) $inner_sku[] = $pro['bn'];
        }

        if (!$sdf) return ;
        $params = $this->_format_goods_params($sdf);

        $callback = array(
            'class'  => get_class($this),
            'method' => 'goods_callback',
            'params' => array('inner_sku'=>$inner_sku,'node_id'=>$this->__channelObj->wms['node_id']),
        );

        return $this->__caller->call(WMS_ITEM_UPDATE, $params, $callback,$title,10);
    }

    protected function _format_goods_params($sdf)
    {
        $params = $items = array();
        if ($sdf){
            $product_ids = array();
            foreach ((array) $sdf as $p){
                if (!is_array($p)) continue;

                $product_ids[] = $p['product_id'];
                $spec_info = preg_replace(array('/：/','/、/'),array(':',';'),$p['property']);
                $items[] = array(
                    'name'                => $p['name'],
                    'title'               => $p['name'],// 商品标题
                    'item_code'           => $p['bn'],
                    'remark'              => '',//商品备注
                    'type'                => 'NORMAL',
                    'is_sku'              => '1',
                    'gross_weight'        => $p['weight'] ? $p['weight'] : '',// 毛重,单位G
                    'net_weight'          => $p['weight'] ? $p['weight'] : '',// 商品净重,单位G
                    'tare_weight'         => '',// 商品皮重，单位G
                    'is_friable'          => '',// 是否易碎品
                    'is_dangerous'        => '',// 是否危险品
                    //'weight'            => $p['weight'] ? $p['weight'] : '0',
                    //'length'            => '0.00',// 商品长度，单位厘米
                    //'width'             => '0.00',// 商品宽度，单位厘米
                    //'height'            => '0.00',// 商品高度，单位厘米
                    //'volume'            => '0.00',// 商品体积，单位立方厘米
                    'pricing_cat'         => '',// 计价货类
                    'package_material'    => '',// 商品包装材料类型
                    'price'               => '',
                    'support_batch'       => '否',
                    'support_expire_date' => '否',
                    'expire_date'         => date('Y-m-d'),
                    'support_barcode'     => '0',
                    'barcode'             => $p['barcode'] ? $p['barcode'] : '',
                    'support_antifake'    => '否',
                    'unit'                => $p['unit'] ? $p['unit'] : '',
                    'package_spec'        => '',// 商品包装规格
                    'ename'               => '',// 商品英文名称
                    'brand'               => '',
                    'batch_no'            => '',
                    'goods_cat'           => '',// 商品分类
                    'color'               => '',// 商品颜色
                    'property'            => $spec_info,//规格
                );
            }
        }
        $params['item_lists'] = json_encode(array('item'=>$items));
        $params['uniqid'] = self::uniqid();
        return $params;
    }

    public function goods_addCombination($sdf) {
        return $this->error('没有该接口');
    }

    public function goods_syncMap($sdf) {
        return $this->error('没有该接口');
    }

    /**
     * 同步库存
     *
     * @return void
     * @author 
     */
    public function goods_syncStore($sdf)
    {
        return $this->error('没有该接口');
    }

    public function goods_syncGet($sdf)
    {
        return $this->error('没有该接口');
    }

    public function goods_syncPrice($sdf)
    {
        return $this->error('没有该接口');
    }
    
    public function goods_syncDetail($sdf)
    {
        return $this->error('没有该接口');
    }

    //https://git.ishopex.cn/matrix/nest-webapp/blob/master/qimen/adapter/store_wms_stock_query.py
    //https://open.taobao.com/api.htm?docId=27271&docType=2
    private $goodsInventoryType = [
        'ZP'=>'正品','CC'=>'残次','JS'=>'机损','XS'=>'箱损','ZT'=>'在途库存'
    ];
    public function goods_stockQuery($sdf) {
        $title = '【'.$this->__channelObj->wms['channel_name'].'】查询商品库存';
        $params = [
            'page' => $sdf['page_no'],
            'page_size' => $sdf['page_size'],
        ];
        if($sdf['material']['material_bn']) {
            $params['item_code'] = $sdf['material']['material_bn'];
        }
        if($sdf['wms_branch_bn']) {
            $params['warehouse_code'] = $this->get_warehouse_code($this->__channelObj->wms['channel_id'], $sdf['wms_branch_bn']);
        }
        $rs = $this->__caller->call(WMS_STOCK_QUERY, $params, [],$title,10,$sdf['wms_branch_bn']);
        if(isset($rs['data'])) {
            $data = @json_decode($rs['data'], 1);
            
            // total_count
            $rs['total_count'] = isset($data['succ'][0]['total_count']) ? intval($data['succ'][0]['total_count']) : 0;
            $data = isset($data['succ'][0]['item_list']) ? $data['succ'][0]['item_list'] : [];
            $rs['data'] = [];
            
            // check
            if(empty($data)){
                return $rs;
            }

            foreach ($data as $v) {
                $rs['data'][] = [
                    'warehouse_code' => $v['warehouse_code'],
                    'item_code' => $v['item_code'],# 商品编码
                    'item_id' => $v['item_id'],# 仓储系统商品ID
                    'inventory_type' => $this->goodsInventoryType[$v['inventory_type']],
                    'quantity' => (int)$v['quantity'],
                    'lock_quantity' => (int)$v['lock_quantity'],
                    'batch_code' => $v['batch_code'],
                    'produce_code' => $v['produce_code'],
                    'product_date' => $v['product_date'] && is_numeric($v['product_date']) ? date('Y-m-d', $v['product_date']/1000) : (string) $v['product_date'],
                    'expire_date' => $v['expire_date'] && is_numeric($v['expire_date']) ? date('Y-m-d', $v['expire_date']/1000) : (string) $v['expire_date'],
                ];
            }
        }
        return $rs;
    }
}
