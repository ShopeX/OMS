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
 * 京东虎符WMS相关业务Lib类
 *
 * @author wangbiao@shopex.cn
 * @version 2026.10.30
 */
class erpapi_wms_matrix_hufu_request_goods extends erpapi_wms_request_goods
{
    public function goods_add($sdf){
        $title = $this->__channelObj->wms['channel_name'].'商品添加';

        $callback = array(
            'class'  => get_class($this),
            'method' => 'goods_callback',
            'params' => array('node_id'=>$this->__channelObj->wms['node_id']),
        );

        $warehouse_code = '';
        if ($sdf[0]['branch_bn']) {
            $warehouse_code = $this->get_warehouse_code($this->__channelObj->wms['channel_id'],$sdf[0]['branch_bn']); 
        }

        foreach ($sdf as $good) {
            if (!$good || !is_array($good)) continue;

            $params = $this->_format_goods_params($good);
            $params['warehouse_code'] = $warehouse_code;

            if ($good['owner_code']) {
                $params['ownerCode'] = $good['owner_code'];
            }

            $callback['params']['inner_sku'] = array($good['bn']);

            $this->__caller->call(WMS_ITEM_ADD, $params, $callback, $title, 10, $good['bn']);
        }
    }

    public function goods_update($sdf)
    {
        $title = $this->__channelObj->wms['channel_name'].'商品更新';

        $callback = array(
            'class'  => get_class($this),
            'method' => 'goods_callback',
            'params' => array('node_id'=>$this->__channelObj->wms['node_id']),
        );

        $warehouse_code = '';
        if ($sdf[0]['branch_bn']) {
            $warehouse_code = $this->get_warehouse_code($this->__channelObj->wms['channel_id'],$sdf[0]['branch_bn']); 
        }

        foreach ($sdf as $good) {
            if (!$good || !is_array($good)) continue;

            $params = $this->_format_goods_params($good);
            $params['warehouse_code'] = $warehouse_code;

            if ($good['owner_code']) {
                $params['ownerCode'] = $good['owner_code'];
            }

            $callback['params']['inner_sku'] = array($good['bn']);

            $this->__caller->call(WMS_ITEM_UPDATE, $params, $callback, $title, 10, $good['bn']);
        }
    }

    protected function _format_goods_params($p)
    {
        $foreignsku = app::get('console')->model('foreign_sku')->dump(array('wms_id'=>$this->__channelObj->wms['channel_id'],'inner_sku'=>$p['bn']));

        $params = $items = array();

        $product_ids = array();

        $product_ids[] = $p['product_id'];
        $spec_info = preg_replace(array('/：/','/、/'),array(':',';'),$p['property']);
        $props = $p['props'];
        $extendProps = [
            'package_size'           => $props['package_size'],
            'carton_size'            => $props['carton_size'],
            'net_weight'             => $props['net_weight'],
            'package_weight'         => $props['package_weight'],
        ];
        
        $tmpitem = array(
            'name'                => $p['name'],
            'title'               => $p['name'],// 商品标题
            'item_code'           => $p['bn'],
            'remark'              => '',//商品备注
            'type'                => ($p['type'] =='pkg' || $p['material_type'] == '4') ? 'COMBINE' : 'NORMAL',
            'is_sku'              => '1',
            'gross_weight'        => $p['weight'] ? $p['weight']/1000 : '',// 毛重,单位G
            'net_weight'          => $p['net_weight'] ? $p['net_weight']/1000 : '',// 商品净重,单位G
            'tare_weight'         => '',// 商品皮重，单位G
            'is_friable'          => '',// 是否易碎品
            'is_dangerous'        => '',// 是否危险品
            'pricing_cat'         => '',// 计价货类
            'package_material'    => '',// 商品包装材料类型
            'price'               => '',
            'support_batch'       => '否',
            'support_sn_mgmt'     => $p['serial_number'] == 'true' ? '1' : '0',
            'support_expire_date' => $p['use_expire_wms'],
            'support_batch_mgmt' => $p['use_expire_wms'],
            'shelf_life' => $p['shelf_life'],
            'reject_life_cycle' => $p['reject_life_cycle'],
            'lockup_life_cycle' => $p['lockup_life_cycle'],
            'advent_life_cycle' => $p['advent_life_cycle'],
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
            'item_id'             => $foreignsku['outer_sku'] ? $foreignsku['outer_sku'] : $p['bn'],
            'pcs'                 => $p['box_spec'] ?: '',
            'goods_cat_id'        => $p['good_cat_id'] ? $p['good_cat_id'] : 0,
            'goods_cat'           => $p['cat_name'],
        );

        if($p['length']>0) $tmpitem['length'] = $p['length'];
        if($p['width']>0) $tmpitem['width'] = $p['width'];
        if($p['high']>0) $tmpitem['height'] = $p['high'];
        
        $items[] = $tmpitem;    
        $params['item_lists'] = json_encode(array('item'=>$items));
        $params['extendProps'] = json_encode($extendProps);
        $params['uniqid'] = self::uniqid();
        $params['to_version'] = '2.0';
        
        return $params;
    }

    public function goods_addCombination($sdf) {
        $title = $this->__channelObj->channel['channel_name'].'组合商品关系添加';
        $id = $sdf['material']['id'];
        $primaryBn = $sdf['material']['inner_sku'];
        $params = array();
        $params['item_code'] = $sdf['material']['inner_sku'];
        $params['item_id'] = $sdf['material']['outer_sku'];
        $params['warehouse_code'] = $this->get_warehouse_code($this->__channelObj->wms['channel_id'],$sdf['branch']['branch_bn']);;
        $items = array('item'=>array());
        
        foreach($sdf['material_items'] as $val) {
            $items['item'][] = array(
                'item_code' => $val['inner_sku'],
                'item_id' => $val['outer_sku'],
                'quantity' => $val['num']
            );
        }
        
        $params['item_lists'] = json_encode($items);
        $rs = $this->__caller->call(WMS_COMBINE_CREATE, $params, array(), $title, 10, $primaryBn);
        if($rs['rsp'] == 'succ') {
            app::get('console')->model('foreign_sku')->update(array('sync_combination'=>'3'), array('id' =>$id));
        } else {
            app::get('console')->model('foreign_sku')->update(array('sync_combination'=>'1'), array('id' =>$id));
            $rs['err_msg'] = $primaryBn . '：' .$rs['err_msg'];
        }
        return $rs;
    }

    public function goods_syncMap($sdf)
    {
        $title = $this->__channelObj->channel['channel_name'].'商品关系映射';
        
        //解除映射关系
        if($sdf['operate_type']=='del' || $sdf['operate_type']=='delete'){
            $title = $this->__channelObj->channel['channel_name'].'解除商品映射关系';
        }
        
        $id = $sdf['map_goods']['id'];
        $primaryBn = $sdf['material']['inner_sku'];
        $params = array();
        $params['action_type'] = $sdf['operate_type'] == 'add' ? 'add' : 'delete';
        $params['shop_nick'] = $this->_getShopCode($sdf['shop']);
        $params['item_source'] = '1';#代表淘系
        $params['item_id'] = $sdf['material']['outer_sku'];
        $params['shop_item_id'] = $sdf['map_goods']['shop_iid'];
        $params['sku_id'] = $sdf['map_goods']['shop_sku_id'];
        
        $rs = $this->__caller->call(WMS_MAP_CREATE, $params, array(), $title, 10, $primaryBn);
        if($rs['rsp'] == 'succ') {
            $syncMap = $params['action_type'] == 'add' ? '2' : '4';
            app::get('console')->model('map_goods')->update(array('sync_map'=>$syncMap), array('id' =>$id));
        } else {
            $syncMap = $params['action_type'] == 'add' ? '1' : '3';
            app::get('console')->model('map_goods')->update(array('sync_map'=>$syncMap), array('id' =>$id));
            $rs['err_msg'] = $primaryBn . '：' .$rs['err_msg'];
        }
        
        return $rs;
    }
}
